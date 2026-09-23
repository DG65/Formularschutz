# Pförtner — Hinweise für die Arbeit an diesem Repository

## Einordnung

- **Marke: DG65 Toolkit**, nicht NRG-Stack (Dietmars Entscheidung, 30.08.2026). Toolkit =
  horizontale, domänenfreie Plattform-Werkzeuge ohne Energie-Fachwissen (Gründungspaar
  GleitenderMittelwert + StrukturHub, dann MigrationsHub, jetzt Pförtner). NRG-Stack =
  vertikale Energie-Module. `library.json→name` folgt dem Muster `"DG65 Toolkit <Modulname>"`.
- **Name:** Pförtner (Dietmar, 01.09.2026). ASCII-Schreibweise für Repo/Ordner/Klasse:
  `Pfoertner` (Klassenname = Modulname, PHP-Klasse ohne Umlaut; die Anzeige übersetzt
  `locale.json` auf „Pförtner“). Präfix `PFOE`.
- **Herkunft:** Das Design wurde zuerst in StrukturHub gebaut (Commit `c4a2c5a` im Repo
  `DG65/NRGStrukturHub`, revertiert in `ae8b88f`) und auf Dietmars Entscheidung als eigenes
  Modul ausgekoppelt — Zugriffsschutz hat fachlich nichts mit Objektbaum-Struktur zu tun.
  Kickoff-Briefing: `/Users/dietmar/Nextcloud/Claude/Pfoertner-Kickoff.md`.
- **Verbund-Manifest SUITE.md** liegt NICHT im Repo, sondern nur lokal unter
  `/Users/dietmar/Nextcloud/Claude/SUITE.md` (öffentliche Repos vs. Architektur-Know-how).
  Bei Konventionsfragen zuerst dort nachlesen, nicht Code zwischen Modulen vergleichen. Die
  Stolpersteine/Store-Review/Formular-Optik dort gelten für Symcon-Module generell, nicht nur
  für NRG-Stack. Der Chef im Ring für neue Konventionen ist EMS.

## Grundregeln — nicht verhandelbar

1. **KEIN Sicherheitsmechanismus, und das muss so bleiben.** Bei jeder Erweiterung/Erwähnung
   ehrlich sagen: schützt nur Formular-Klicks in der Konsole, nicht Skript-/API-Zugriff
   (`IPS_GetConfiguration()`/`IPS_SetProperty()` gehen komplett vorbei), kennt nur ein Passwort,
   keine Nutzeridentität (Symcon-Konsole = ein gemeinsames Login, kein Kernel-Hook „wer klickt“).
   Steht im Formular (Zweck-Intro, Doku-Panel), im README und hier — an allen drei Stellen
   mitführen, nie als „damit ist es sicher“ darstellen.
2. **Passwort immer gehasht** (`password_hash()`/`password_verify()`), nie im Klartext
   speichern, loggen, debuggen oder anzeigen. Auch ein falsch eingegebenes Passwort gehört
   nicht ins Debug.
3. **Attribute, nicht Properties** (`VaultPasswordHash`, `VaultUnlocked`) — Verbund-Konvention
   Zugangsdaten; hält den Hash aus `IPS_GetConfiguration()` heraus.
4. **Fail-open per Design.** Leerer Hash = IMMER volles, offenes Formular, nie „gesperrt ohne
   bekanntes Passwort“. Grund: `MC_DeleteModule()`+`MC_CreateModule()`-Resyncs löschen
   Attribute (SUITE.md Stolperstein 5). Konsumenten sollen sich gleich verhalten
   (`PFOE_IsActive()` existiert genau dafür). Niemals in Richtung „bei Zweifel sperren“ umbauen.
5. **Kein Auto-Timeout** (Dietmars ausdrückliche Entscheidung, 30.08.2026). Entsperrt bleibt
   entsperrt, bis „Jetzt sperren“ geklickt oder das Formular geschlossen und neu geöffnet wird.
6. **`ReloadForm()`-Regel.** In DIESEM Modul ist `ReloadForm()` in allen Aktionen unbedenklich,
   weil das Formular außer den Passwortfeldern keine Eingaben hat, die verloren gehen könnten —
   deshalb wirkt „Jetzt sperren“ hier sofort. Sobald jemals ein Fachfeld ins Formular kommt, gilt
   SUITE.md Stolperstein 12: `ReloadForm()` nur aus dem gesperrten Zustand (einziges Feld =
   Passwort), sonst `UpdateFormField()`. Für Konsumenten mit Fachfeldern (OCPPHub) gilt das
   schon jetzt — siehe Muster im README.
7. **Vertrag ist öffentliche API.** `PFOE_CheckAccess(string): bool` und `PFOE_IsActive(): bool`
   werden nie umbenannt, Rückgabetyp bleibt `bool` (Text-als-bool-Falle, SUITE.md „Sichtbare
   Rückmeldung“ Punkt 3). Keine PHP-Standardwerte auf öffentlichen Methoden, nur skalare
   Parametertypen (Stolperstein 8/20) — der Prüfstand `.tools/test-form.php` Block 8 erzwingt
   das. Erweiterungen nur als NEUE Funktionen, nie als angebaute Parameter.
8. **Eigenständigkeit.** Pförtner setzt kein anderes Modul voraus und wird von keinem
   vorausgesetzt — jeder Konsument ruft hinter `function_exists()` auf. Prüfwerkzeug:
   `.tools/check-standalone.php`.
9. **Sprachregel:** alles Nutzersichtbare auf Deutsch, keine vermeidbaren Anglizismen; Idents,
   Methodennamen und feststehende Fachbegriffe ausgenommen. **Ergänzung SUITE.md Regel 9b
   (Dietmar, 13.09.2026):** echte Umlaute (ü/ä/ö/ß) statt ue/ae/oe/ss in jedem sichtbaren Text
   (Idents bleiben ASCII); Datumsangaben im Formular/in Hinweistexten TT.MM.JJJJ, nie
   JJJJ-MM-TT (dieses Modul zeigt aktuell ohnehin kein Datum an — bei einer künftigen
   Datumsanzeige diese Regel direkt einhalten, nicht nachträglich umstellen müssen). Stand
   13.09.2026 bereits konform geprüft (kein ISO-Datum in Formular/README/CLAUDE.md, keine
   ue/ae/oe/ss-Transliteration in Nutzertexten).
10. **Keine eigene Anlage als Norm:** dieses Modul hat keine „Anlage“, aber trotzdem keine
    Integrator-/Kunden-Details von Dietmar hartkodieren — Szenarien nur als „z. B.“.
11. **Store-/Stable-Regeln von Anfang an:** Formular-Buttons nur per `echo`/`UpdateFormField`
    (nie `IPS_SetProperty`+`ApplyChanges` selbst), `vendor=""`, `library.json` nur mit den
    erlaubten Schlüsseln, Link-Buttons als `'onClick' => "echo '<URL>';", 'link' => true`.
    Modul nie zusätzlich über den Symcon Module Store buchen (Stolperstein 6).

## Formular-Verzweigung (Kernmechanik)

`GetConfigurationForm()` verzweigt GANZ AM ANFANG, bevor irgendein anderer Inhalt gebaut wird:

1. kein Passwort → volles Formular + Panel „🔒 Pförtner aktivieren“ (aufgeklappt)
2. Passwort gesetzt, gesperrt → NUR Label + `PasswordTextBox` + „🔓 Entsperren“ (`LockedForm()`)
3. Passwort gesetzt, entsperrt → volles Formular + Panel „🔓 Pförtner — entsperrt“ mit „Jetzt
   sperren“ und Unterpanel „Passwort ändern/deaktivieren“ (leer+leer = deaktivieren)

Rahmen nach SUITE.md „Einheitliche Formular-Optik“: 👋 Zweck-Intro (einmalig) → 🆕 News (je
Version) → 📖 Doku (mit Version) → Fachpanels (Tresor, „🔌 Für andere Module“) → 💬 Rückmeldungen
(einmalig) → 🧡 Über dieses Modul (nie dismissible). `LICENSE_URL` zeigt auf `main` — dieses Repo
hat von Anfang an PolyForm, keinen MIT-Altstand.

## Prüfen

```
php .tools/test-form.php          # 0 = alle Blöcke bestanden (läuft auch in der CI)
php .tools/check-standalone.php   # 0 = kein ungesicherter Fremdaufruf
```

Der Prüfstand simuliert `IPSModule` (Attribute/Status/ReloadForm) und spielt den kompletten
Lebenszyklus inkl. Resync-Fail-open durch. Live-Test in Symcon bleibt trotzdem Pflicht vor jedem
Release: Installation nur manuell über die Modulverwaltung (keine API/Skript-Auslösung), Formular
in allen drei Zuständen durchklicken, jeden Button auf sichtbare Rückmeldung prüfen.

## Branch-Konvention: `beta` ist der Arbeitsstand, `main` nur nach Live-Bewährung

**Wichtig, seit dem allerersten Push (08.09.2026) so zu halten** (Hinweis Katasteramt/Dietmar,
Verbund-Konvention aus SUITE.md „Repo-Namen"/Suite-Release-Abschnitt): `main` bedeutet im
Verbund „schon mal an einer echten Instanz gelaufen", nicht „letzter Commit". Der allererste
Push auf ein leeres Repo durfte `main`=`beta` setzen (identischer Code, noch nichts zu
verlieren) — das war ein Einmalfall, KEIN Muster für künftige Commits.

**Ab jetzt:** jeder Commit geht zuerst auf `beta`. `main` wird erst nach einem echten Live-Test
an einer laufenden Symcon-Instanz manuell nachgezogen (Fast-Forward), nie automatisch mit
jedem Push. Wann genau promotet wird, entscheidet Dietmar selbst — nicht proaktiv nachfragen
oder erinnern (siehe Memory `feedback-beta-main-freigabe`), sondern warten, bis er es sagt
oder es von sich aus tut.

## Konsumenten

- **OCPPHub** (Sitzung „OCPPHub“, `local_de03f444-bab6-4008-bd51-3ba2a67e4e27`): Vertrag am
  08.09.2026 übergeben und von OCPPHub als vollständig/passend bestätigt — deckt den
  Abschnittsschutz Kundenverwaltung/Abrechnung ab (`.docs/architektur.md` „Formular-Struktur“,
  Pflichtenheft S7 dort), der alte StrukturHub-Platzhalter-Vertrag dort ist aufgelöst. Kein
  separater `vaultInstanceId`-Parameter nötig, das README-Muster
  (`UnlockSensitive`/`LockSensitiveNow`) passt direkt. Anwendungsfall: NICHT das ganze
  Formular, nur einzelne Panels sperren, Basis/Steuerung bleiben sichtbar. **Noch nicht
  gebaut** (kein akuter Auftrag von Dietmar bei OCPPHub, Stand 08.09.2026) — bei einer
  künftigen Breaking-Change-Erwägung am Vertrag zuerst dort nachfragen, ob inzwischen gebaut
  wurde.
- Neue Konsumenten: Muster im README („Muster für ein Partnermodul“), Aufruf immer hinter
  `function_exists()` und in `try/catch (Throwable)`.

## Roadmap

- **v0.1 (aktuell):** Passwortschutz fürs eigene Formular + Vertrag `CheckAccess`/`IsActive`.
- Offen/bewusst nicht drin: Auto-Timeout (abgelehnt), mehrere Passwörter/Rollen je Instanz
  (wäre Schein-Sicherheit — für Rollen gibt es Symcons Benutzerverwaltung), zentrales Sperren
  fremder Formulare (technisch unmöglich in Symcon).
- **Migrations-Vormerkung, keine Sofort-Pflicht** (Dashboard/Dietmar, 23.09.2026, SUITE.md
  „Einheitliche Formular-Optik“ Punkt 1): `NEWS_VERSION`-String + `SeenNews`-Gleichheitscheck
  (aktuell in `NewsBanner()`/`AckNews()`) auf `NEWS_VERSIONS`-Array umstellen (Schlüssel =
  Version ohne Beta-/Build-Suffix, Wert = Zeilen wie bisher), Caption „🆕 Neu bis Version X“,
  `version_compare()` gegen `SeenNews`, `AckNews()` speichert die installierte
  Bibliotheksversion (`IPS_GetLibrary()`, Suffix per Regex entfernt) statt des zuletzt
  gezeigten Schlüssels. Referenz: `NRGDashboardTile` Build 201. Nicht jetzt anfassen, nur wenn
  das News-Panel ohnehin wegen einer neuen Version geändert wird (aktuell nur EIN Eintrag,
  „Lücke seit letzter Version“-Problem existiert hier noch nicht).
