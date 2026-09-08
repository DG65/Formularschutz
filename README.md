# Pförtner

![Symcon](https://img.shields.io/badge/Symcon-PHPModul-blue)
![Modul Version](https://img.shields.io/badge/Modul_Version-0.1.0-blue)
![Symcon Version](https://img.shields.io/badge/Symcon_Version-9.0%2B-blue)
![License](https://img.shields.io/badge/License-PolyForm_Noncommercial_1.0.0-lightgrey)
![Check Style](https://github.com/DG65/Pfoertner/actions/workflows/check-style.yml/badge.svg)
[![PayPal](https://img.shields.io/badge/PayPal-Me-blue?logo=paypal)](https://paypal.me/DietmarGureth)

Der **Pförtner** legt ein Passwort vor Konfigurationsformulare in der IP-Symcon-Verwaltungskonsole.
Gedacht für Installationen, in denen mehrere Menschen die Konsole benutzen — etwa ein Integrator,
dessen Kunde selbst Skripte schreiben darf, aber nicht versehentlich in fremden Instanzen
herumklicken soll, oder Formulare mit Kundendaten, die nicht jeder auf den ersten Blick sehen soll.

Andere Module können dasselbe Passwort für ihre **eigenen** sensiblen Formular-Panels abfragen
(Vertrag `PFOE_CheckAccess()`, siehe unten). Erster Konsument: OCPPHub (Kundenverwaltung/Abrechnung).

## Wichtige Einschränkung — bitte zuerst lesen

**Der Pförtner ist kein Sicherheitsmechanismus.** Er ist eine Hürde gegen zufälliges oder
unbefugtes *Klicken*, mehr nicht:

- Die Symcon-Konsole läuft über **ein gemeinsames Login** und kennt keine Nutzeridentität. Es gibt
  keinen Kernel-Hook, der weiß, welcher Mensch gerade vor der Konsole sitzt. Der Pförtner kennt
  deshalb nur **ein Passwort**, keine Rollen — jeder, der es kennt, hat Zugriff.
- Er schützt **ausschließlich** vor Klicks im Formular. `IPS_GetConfiguration()`,
  `IPS_SetProperty()`, jedes Skript und die Skriptausführung in der Konsole gehen daran komplett
  vorbei.
- Echte Zugriffskontrolle gibt es in Symcon nur mit der kostenpflichtigen
  [Benutzerverwaltung](https://www.symcon.de/de/produkt/erweiterungen/benutzerverwaltung/) — und
  auch die bezieht sich laut eigener Doku primär auf die Visualisierung, nicht auf die Konsole.
  Ein Modul kann Konsolen-Zugriffskontrolle nicht nachrüsten.

Diese Einschränkung steht bewusst im Formular, hier und in `CLAUDE.md` — und bleibt bei jeder
Erweiterung erhalten.

## So funktioniert es

1. Instanz „Pförtner“ anlegen (Kategorie „DG65 Toolkit“ bzw. per Suche nach „Pförtner“).
2. Im Panel **„🔒 Pförtner aktivieren“** ein Passwort zweimal eingeben, „Aktivieren“ klicken.
3. Ab dem nächsten Öffnen zeigt das Formular nur noch ein Passwortfeld und „🔓 Entsperren“ — sonst
   nichts.
4. Entsperrt bleibt entsperrt, bis **„🔒 Jetzt sperren“** geklickt oder das Formular geschlossen und
   neu geöffnet wird. Es gibt **bewusst keinen Auto-Timeout**.
5. Passwort ändern oder den Pförtner deaktivieren (beide Felder leer lassen) geht jederzeit im
   entsperrten Zustand.

**Passwort vergessen?** Weil das Modul absichtlich kein Sicherheitsmechanismus ist, gibt es einen
Ausweg per Skript (Konsole → Skript ausführen):

```php
PFOE_SetVaultPassword(12345, 'neu', 'neu');   // neues Passwort setzen
PFOE_SetVaultPassword(12345, '', '');         // Pförtner deaktivieren
```

**Speicherung:** Das Passwort wird nur als Hash gespeichert (`password_hash()`), nie im Klartext —
in einem Instanz-**Attribut**, nicht in einer Property (taucht damit auch nicht in
`IPS_GetConfiguration()`/Exporten auf). Attribute gehen bei einem erzwungenen Modul-Resync
(Modul in der Modulverwaltung löschen + neu anlegen) verloren. Der Pförtner fällt dann bewusst
**offen** zurück — nie „gesperrt ohne bekanntes Passwort“ —, damit ein Resync niemanden dauerhaft
aussperrt. Danach das Passwort einfach neu setzen.

**Mehrere Instanzen** sind ausdrücklich erlaubt (z. B. ein Passwort pro Kunde oder Gewerk). Jede
Instanz hat ihr eigenes Passwort; Partnermodule nennen die Instanz, gegen die sie prüfen.

## Vertrag für andere Module

```php
PFOE_CheckAccess(int $instanzID, string $password): bool   // reiner Passwort-Vergleich, zustandslos
PFOE_IsActive(int $instanzID): bool                        // ist überhaupt ein Passwort gesetzt?
```

- `CheckAccess()` verändert **nichts** an der Pförtner-Instanz — kein Entsperren dort, nur die
  Antwort „ist dieses Passwort richtig?“. Liefert `false`, solange kein Passwort gesetzt ist.
- `IsActive()` ist für das Fail-open des Konsumenten gedacht: kein Passwort gesetzt (oder gar keine
  Pförtner-Instanz installiert) → sensible Panels normal anzeigen, keine Passwortabfrage.
- Modul-GUID für `IPS_GetInstanceListByModuleID()`: `{E526E4DF-8066-4572-8D80-03C80079DD2E}`.
- Immer hinter `function_exists('PFOE_CheckAccess')` aufrufen und in `try/catch (Throwable)`
  einpacken — jedes Partnermodul ist optional, ohne Pförtner bleibt jedes andere Modul
  unverändert funktionsfähig.
- Einmal veröffentlichte Signaturen werden nie umbenannt, nur additiv um **neue Funktionen**
  erweitert (kein Anbau von Parametern — ein zusätzlicher Parameter ist in Symcon auch mit
  PHP-Standardwert ein Bruch für externe Aufrufer).

### Muster für ein Partnermodul (Abschnittsschutz einzelner Panels)

**Ein Modul kann das Formular eines anderen Moduls nicht von außen sperren** — Symcon hat dafür
keinen Mechanismus, `GetConfigurationForm()` ist immer pro Instanz. Der Pförtner beantwortet nur
die Passwortfrage; die Sperr-UI baut jedes Partnermodul selbst, nach demselben Muster wie der
Pförtner (`LockedForm()`/`GetConfigurationForm()` in `Pfoertner/module.php` als Referenz):

```php
// Create():
$this->RegisterPropertyInteger('PfoertnerID', 0);            // welche Pförtner-Instanz (SelectInstance im Formular)
$this->RegisterAttributeBoolean('SensitiveUnlocked', false);   // eigener Sperrzustand, NICHT der des Pförtners

private function sensitiveLocked(): bool
{
    $tid = $this->ReadPropertyInteger('PfoertnerID');
    if ($tid <= 0 || !function_exists('PFOE_IsActive')) {
        return false;                                          // Fail-open: kein Pförtner → alles sichtbar
    }
    try {
        return PFOE_IsActive($tid) && !$this->ReadAttributeBoolean('SensitiveUnlocked');
    } catch (Throwable $e) {
        return false;                                          // Vertragsbruch/fehlende Instanz → offen, nicht tot
    }
}

// GetConfigurationForm(): Basis-/Steuerungs-Panels immer, sensible Panels nur wenn !sensitiveLocked(),
// sonst an ihrer Stelle:
//   ['type' => 'Label', 'caption' => '🔒 Kundenverwaltung/Abrechnung gesperrt — Passwort eingeben.'],
//   ['type' => 'PasswordTextBox', 'name' => 'SensitivePassword', 'caption' => 'Passwort'],
//   ['type' => 'Button', 'caption' => '🔓 Entsperren', 'onClick' => 'echo PREFIX_UnlockSensitive($id, $SensitivePassword);'],

public function UnlockSensitive(string $password): string
{
    $tid = $this->ReadPropertyInteger('PfoertnerID');
    if ($tid <= 0 || !function_exists('PFOE_CheckAccess')) {
        return 'ℹ️ Kein Pförtner verknüpft — die Abschnitte sind ohnehin sichtbar.';
    }
    try {
        $ok = PFOE_CheckAccess($tid, $password);
    } catch (Throwable $e) {
        return '⚠️ Pförtner nicht erreichbar: ' . $e->getMessage();
    }
    if (!$ok) {
        return '⛔ Falsches Passwort.';
    }
    $this->WriteAttributeBoolean('SensitiveUnlocked', true);
    $this->ReloadForm();   // ACHTUNG: verwirft ungespeicherte Eingaben in anderen Feldern — nur
                           // vertretbar, wenn das Passwortfeld realistisch das einzige gerade
                           // benutzte Feld ist; sonst UpdateFormField() je Panel (SUITE.md Stolperstein 12)
    return '✅ Entsperrt.';
}

public function LockSensitiveNow(): string
{
    $this->WriteAttributeBoolean('SensitiveUnlocked', false);
    // Bewusst KEIN ReloadForm() aus dem vollen Formular heraus — die Sperre gilt ab dem nächsten Öffnen.
    return 'ℹ️ Gesperrt — wirkt beim nächsten Öffnen dieses Formulars.';
}
```

Der Vorbehalt von oben gilt für Konsumenten genauso: nicht als alleinige Absicherung für
personenbezogene Daten verkaufen.

## Prüfen ohne Symcon

```
php .tools/test-form.php          # Lebenszyklus inaktiv → aktiv → gesperrt → entsperrt, Vertrag, Fail-open
php .tools/check-standalone.php   # kein ungesicherter Fremdaufruf (function_exists-Wächter)
```

Beides läuft auch in der GitHub-Actions-CI (`check-style.yml`).

## Lizenz

PolyForm Noncommercial 1.0.0, siehe [LICENSE](LICENSE) — privat und nicht-kommerziell frei
nutzbar, für den gewerblichen Einsatz ist eine gesonderte Lizenz vom Rechteinhaber nötig
(Kontakt: dietmar@gureth.eu). Spenden willkommen: [paypal.me/DietmarGureth](https://paypal.me/DietmarGureth).

---

Teil des **DG65 Toolkit** — eigenständige, domänenfreie Symcon-Bausteine von DG65, ohne
Energiebezug: [GleitenderMittelwert](https://github.com/DG65/NRGGleitenderMittelwert),
[StrukturHub](https://github.com/DG65/NRGStrukturHub),
[MigrationsHub](https://github.com/DG65/NRGMigrationsHub) und Pförtner. Kein NRG-Stack-Modul,
auch wenn der erste Konsument des `PFOE_CheckAccess()`-Vertrags (OCPPHub) aus NRG-Stack kommt —
das ist Reihenfolge, keine fachliche Zugehörigkeit.
