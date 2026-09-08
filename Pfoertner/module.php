<?php

// ===========================================================================
// Pförtner (DG65 Toolkit) — optionaler Passwortschutz für Konfigurations-
// formulare in der IP-Symcon-Verwaltungskonsole.
//
// WAS ES IST: eine Hürde gegen zufälliges oder unbefugtes Klicken. Wer ein
// Passwort setzt, bekommt dieses Formular beim nächsten Öffnen nur noch als
// Passwortabfrage zu sehen, bis jemand entsperrt. Andere Module (erster
// Konsument: OCPPHub, Kundenverwaltung/Abrechnung) fragen über
// PFOE_CheckAccess() dasselbe Passwort für ihre EIGENEN sensiblen Panels ab.
//
// WAS ES NICHT IST — und das muss bei jeder Erweiterung so bleiben:
// KEIN Sicherheitsmechanismus. Die Symcon-Konsole kennt keine Nutzer-
// identität (ein gemeinsames Login), es gibt keinen Kernel-Hook "wer klickt
// gerade". IPS_GetConfiguration()/IPS_SetProperty()/jedes Skript gehen an
// diesem Schutz komplett vorbei. Nur ein Passwort, keine Rollen. Das steht
// im Formular, im README und in CLAUDE.md — bewusst mehrfach.
//
// Eigenständig: setzt kein anderes Modul voraus und wird von keinem
// vorausgesetzt (jeder Konsument ruft hinter function_exists() auf).
// ===========================================================================

class Pfoertner extends IPSModule
{
    private const LIBRARY_GUID = '{99177A7D-637B-4A9D-AA26-87FEFE79ADB1}';
    private const MODULE_GUID  = '{E526E4DF-8066-4572-8D80-03C80079DD2E}';

    // Formular-Konvention (SUITE.md "Einheitliche Formular-Optik").
    private const NEWS_VERSION = '0.1.0';
    private const REPO_URL     = 'https://github.com/DG65/Pfoertner';
    private const LICENSE_URL  = 'https://github.com/DG65/Pfoertner/blob/main/LICENSE';
    private const PAYPAL_URL   = 'https://paypal.me/DietmarGureth';

    public function Create()
    {
        parent::Create();

        // Datenmodell: ATTRIBUTE, keine Properties (Verbund-Konvention
        // Zugangsdaten). Zwei Gründe: Properties tauchen im Klartext in
        // IPS_GetConfiguration()/Exporten auf, Attribute nicht — und ein
        // Property-Feld wäre über "Übernehmen" editierbar, der Hash aber
        // nicht sinnvoll von Hand zu setzen.
        //
        // Kaveat (SUITE.md Stolperstein 5): ein Modul-Resync per
        // MC_DeleteModule()+MC_CreateModule() löscht Instanz-Attribute.
        // Deshalb FAIL-OPEN PER DESIGN: leerer Hash bedeutet IMMER volles,
        // offenes Formular — nie "gesperrt ohne bekanntes Passwort". Ein
        // Resync sperrt niemanden aus; man setzt das Passwort danach neu.
        $this->RegisterAttributeString('VaultPasswordHash', ''); // password_hash()-Ausgabe, '' = inaktiv
        $this->RegisterAttributeBoolean('VaultUnlocked', false);

        // Dismiss-Zustände der Rahmen-Panels (Zweck-Intro einmalig, News je
        // Version, Forum-Hinweis einmalig).
        $this->RegisterAttributeBoolean('PurposeIntroGone', false);
        $this->RegisterAttributeString('SeenNews', '');
        $this->RegisterAttributeBoolean('ForumHintGone', false);
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();
        $this->refreshStatus();
    }

    // -----------------------------------------------------------------
    // Öffentlicher Vertrag für ANDERE Module
    // -----------------------------------------------------------------

    /**
     * PFOE_CheckAccess($id, string $password): bool
     *
     * Zustandsloser Passwort-Vergleich für andere Module, die dasselbe
     * Passwort für ihre EIGENEN Formular-Panels nutzen wollen (hinter
     * function_exists('PFOE_CheckAccess'), wie jeder Fremdaufruf im
     * Verbund). Verändert NICHTS an dieser Instanz — kein Entsperren hier,
     * nur die Antwort auf "ist dieses Passwort richtig?".
     *
     * Ein Modul kann das Formular eines anderen Moduls nicht von außen
     * sperren (kein IPS-Hook dafür, GetConfigurationForm() ist immer pro
     * Instanz). Jeder Konsument baut seine Sperr-UI selbst nach demselben
     * Muster wie hier (lockedForm()/GetConfigurationForm() als Referenz)
     * und ruft nur zur Prüfung diese Funktion auf.
     *
     * Liefert false, solange kein Passwort gesetzt ist (Pförtner inaktiv)
     * — Konsumenten sollten in diesem Fall FAIL-OPEN reagieren (Panels
     * zeigen), dafür gibt es PFOE_IsActive().
     *
     * Kein Sicherheitsmechanismus (siehe Kopfkommentar): nur ein Passwort,
     * keine Nutzeridentität; jeder, der es kennt, hat Zugriff. Das Passwort
     * niemals im Klartext loggen.
     */
    public function CheckAccess(string $password): bool
    {
        $hash = $this->ReadAttributeString('VaultPasswordHash');
        return $hash !== '' && password_verify($password, $hash);
    }

    /**
     * PFOE_IsActive($id): bool
     *
     * true, sobald ein Passwort gesetzt ist. Konsumenten prüfen das VOR dem
     * Rendern ihrer Sperr-UI: inaktiver Pförtner (oder gar keine Instanz)
     * = Panels normal zeigen, keine Passwortabfrage — dasselbe Fail-open
     * wie beim eigenen Formular.
     */
    public function IsActive(): bool
    {
        return $this->ReadAttributeString('VaultPasswordHash') !== '';
    }

    // -----------------------------------------------------------------
    // Formular-Aktionen (Buttons) — jede gibt einen Ergebnistext zurück,
    // der per `echo` als Popup erscheint (SUITE.md "Sichtbare Rückmeldung").
    // -----------------------------------------------------------------

    public function UnlockVault(string $password): string
    {
        $hash = $this->ReadAttributeString('VaultPasswordHash');
        if ($hash === '') {
            return 'ℹ️ Kein Passwort gesetzt — der Pförtner ist inaktiv, das Formular ist ohnehin offen.';
        }
        if (!password_verify($password, $hash)) {
            // Bewusst OHNE das eingegebene Passwort im Log/Debug.
            $this->SendDebug('UnlockVault', 'Falsches Passwort', 0);
            return '⛔ Falsches Passwort.';
        }
        $this->WriteAttributeBoolean('VaultUnlocked', true);
        // ReloadForm() ist hier unbedenklich: im gesperrten Zustand ist das
        // Passwortfeld das EINZIGE Feld im Formular (SUITE.md Stolperstein
        // 12, Faustregel: riskant nur, wenn ANDERE Felder gerade
        // unbeachtete Eingaben haben könnten).
        $this->ReloadForm();
        return '✅ Entsperrt — das Formular wird neu aufgebaut.';
    }

    public function LockVaultNow(): string
    {
        if ($this->ReadAttributeString('VaultPasswordHash') === '') {
            return 'ℹ️ Kein Passwort gesetzt — es gibt nichts zu sperren. Erst im Panel „🔒 Pförtner aktivieren“ ein Passwort vergeben.';
        }
        $this->WriteAttributeBoolean('VaultUnlocked', false);
        // ReloadForm() ist in DIESEM Modul unbedenklich: das volle Formular
        // hat außer den Passwortfeldern keine Eingaben, die verloren gehen
        // könnten — die Sperre wirkt deshalb sofort sichtbar. ACHTUNG für
        // Konsumenten (OCPPHub & Co.): in einem Formular MIT Fachfeldern
        // gilt das Gegenteil — dort "Jetzt sperren" OHNE ReloadForm(), die
        // Sperre erst beim nächsten Öffnen (SUITE.md Stolperstein 12).
        $this->ReloadForm();
        return '🔒 Gesperrt — ab jetzt fragt dieses Formular nach dem Passwort.';
    }

    /**
     * Passwort setzen/ändern/entfernen. Beide Felder leer = Pförtner
     * deaktivieren (Hash löschen, Formular dauerhaft offen). Das zweite
     * Feld schützt nur vor Tippfehlern beim Setzen — ein vertipptes,
     * unbekanntes Passwort wäre sonst die einzige Art, sich (bis zum
     * Skript-Ausweg, siehe README) selbst auszusperren.
     */
    public function SetVaultPassword(string $newPassword, string $repeat): string
    {
        $wasActive = $this->ReadAttributeString('VaultPasswordHash') !== '';

        if ($newPassword === '' && $repeat === '') {
            if (!$wasActive) {
                return 'ℹ️ Kein Passwort eingegeben — der Pförtner bleibt inaktiv.';
            }
            $this->WriteAttributeString('VaultPasswordHash', '');
            $this->WriteAttributeBoolean('VaultUnlocked', false);
            $this->refreshStatus();
            $this->ReloadForm();
            return 'ℹ️ Pförtner deaktiviert — dieses Formular ist ab jetzt ohne Passwort erreichbar.';
        }
        if ($newPassword !== $repeat) {
            return '⛔ Die beiden Eingaben stimmen nicht überein — nichts geändert.';
        }

        $this->WriteAttributeString('VaultPasswordHash', password_hash($newPassword, PASSWORD_DEFAULT));
        $this->WriteAttributeBoolean('VaultUnlocked', true);
        $this->refreshStatus();
        $this->ReloadForm();
        return $wasActive
            ? '✅ Passwort geändert — gilt ab sofort auch für Partnermodule, die PFOE_CheckAccess() nutzen.'
            : '✅ Pförtner aktiviert — dieses Formular bleibt jetzt entsperrt, bis du „Jetzt sperren“ klickst oder es nach dem Schließen erneut öffnest.';
    }

    // -----------------------------------------------------------------
    // Rahmen-Panels der Verbund-Formular-Konvention
    // -----------------------------------------------------------------

    public function AckPurposeIntro(): void
    {
        $this->WriteAttributeBoolean('PurposeIntroGone', true);
        $this->UpdateFormField('PurposeIntroPanel', 'visible', false);
    }

    public function AckNews(): void
    {
        $this->WriteAttributeString('SeenNews', self::NEWS_VERSION);
        $this->UpdateFormField('NewsPanel', 'visible', false);
    }

    public function AckForumHint(): void
    {
        $this->WriteAttributeBoolean('ForumHintGone', true);
        $this->UpdateFormField('ForumHintPanel', 'visible', false);
    }

    private function PurposeIntro(): ?array
    {
        if ($this->ReadAttributeBoolean('PurposeIntroGone')) {
            return null;
        }
        return [
            'type' => 'ExpansionPanel', 'name' => 'PurposeIntroPanel', 'expanded' => true,
            'caption' => '👋  Wozu dieses Modul?',
            'items' => [
                ['type' => 'Label', 'caption' => 'Der Pförtner legt ein Passwort vor Konfigurationsformulare in der Symcon-Konsole. Gedacht für Installationen mit mehreren Konsolen-Nutzern — z. B. ein Integrator, dessen Kunde selbst Skripte schreiben darf, aber nicht versehentlich in fremden Instanzen herumklicken soll, oder Formulare mit Kundendaten, die nicht jeder auf den ersten Blick sehen soll.'],
                ['type' => 'Label', 'caption' => 'Ehrlich gesagt: Das ist eine Hürde, kein Schloss. Symcons Konsole kennt keine Nutzeridentität, und Skripte sowie IPS_SetProperty() gehen daran komplett vorbei. Für echte Zugriffskontrolle gibt es nur Symcons kostenpflichtige Benutzerverwaltung — der Pförtner ersetzt sie nicht.'],
                ['type' => 'Label', 'caption' => 'Andere Module (z. B. OCPPHub für Kundenverwaltung/Abrechnung) können dasselbe Passwort für ihre eigenen sensiblen Panels abfragen — siehe Panel „🔌 Für andere Module“.'],
                ['type' => 'Button', 'caption' => 'Verstanden – nicht mehr anzeigen', 'onClick' => 'PFOE_AckPurposeIntro($id);'],
            ],
        ];
    }

    private function NewsBanner(): ?array
    {
        if ($this->ReadAttributeString('SeenNews') === self::NEWS_VERSION) {
            return null;
        }
        return [
            'type' => 'ExpansionPanel', 'name' => 'NewsPanel', 'expanded' => true,
            'caption' => '🆕  Neu in Version ' . self::NEWS_VERSION,
            'items' => [
                ['type' => 'Label', 'caption' => '• Erstes Release: Passwortschutz für dieses Formular (Panel „🔒 Pförtner aktivieren“), Vertrag PFOE_CheckAccess()/PFOE_IsActive() für andere Module.'],
                ['type' => 'Label', 'caption' => '• Kein Auto-Timeout: entsperrt bleibt entsperrt, bis „Jetzt sperren“ geklickt oder das Formular geschlossen und neu geöffnet wird.'],
                ['type' => 'Label', 'caption' => '• 👋 Zweck-Einführung ganz oben im Formular (einmalig wegklickbar).'],
                ['type' => 'Button', 'caption' => 'Verstanden – nicht mehr anzeigen', 'onClick' => 'PFOE_AckNews($id);'],
            ],
        ];
    }

    private function DocPanel(): array
    {
        $lib    = @IPS_GetLibrary(self::LIBRARY_GUID);
        $verTxt = (is_array($lib) && isset($lib['Version']))
            ? 'ℹ️ Pförtner Version ' . $lib['Version'] . ' (Build ' . ($lib['Build'] ?? '?') . ')'
            : 'ℹ️ Pförtner';
        return [
            'type' => 'ExpansionPanel', 'expanded' => false,
            'caption' => '📖  Dokumentation & Hilfe',
            'items' => [
                ['type' => 'Label', 'caption' => $verTxt],
                ['type' => 'Label', 'caption' => 'Aktivieren: im Panel „🔒 Pförtner aktivieren“ ein Passwort zweimal eingeben und „Aktivieren“ klicken. Ab dem nächsten Öffnen zeigt dieses Formular nur noch ein Passwortfeld — bis jemand mit dem richtigen Passwort entsperrt.'],
                ['type' => 'Label', 'caption' => 'Sperren/Entsperren: „Jetzt sperren“ wirkt sofort. Es gibt bewusst keinen automatischen Ablauf — entsperrt bleibt entsperrt, bis gesperrt wird oder das Formular geschlossen und neu geöffnet wird. Passwort ändern oder deaktivieren (beide Felder leer lassen) geht jederzeit im entsperrten Zustand.'],
                ['type' => 'Label', 'caption' => 'WICHTIG — kein Sicherheitsmechanismus: Der Pförtner verhindert nur Klicks in Formularen. Skripte, die Konsolen-Skriptausführung und IPS_GetConfiguration()/IPS_SetProperty() gehen daran komplett vorbei. Er kennt nur EIN Passwort, keine Nutzeridentität — jeder, der es kennt, hat Zugriff. Für echte Rollen/Rechte braucht es Symcons kostenpflichtige Benutzerverwaltung.'],
                ['type' => 'Label', 'caption' => 'Passwort vergessen? Weil das Modul absichtlich kein Sicherheitsmechanismus ist, gibt es einen Ausweg per Skript: PFOE_SetVaultPassword(<InstanzID>, \'neu\', \'neu\') setzt ein neues Passwort, PFOE_SetVaultPassword(<InstanzID>, \'\', \'\') deaktiviert den Pförtner.'],
                ['type' => 'Label', 'caption' => 'Das Passwort wird nur als Hash gespeichert (password_hash), nie im Klartext — in einem Instanz-Attribut, nicht in einer Property. Bei einem erzwungenen Modul-Resync (Modul löschen + neu anlegen in der Modulverwaltung) geht das Attribut verloren: Der Pförtner fällt dann bewusst OFFEN zurück (nie „gesperrt ohne bekanntes Passwort“) — Passwort danach einfach neu setzen.'],
                ['type' => 'Label', 'caption' => 'Mehrere Instanzen sind erlaubt (z. B. ein Passwort pro Kunde/Gewerk). Jede Instanz hat ihr eigenes Passwort; Partnermodule nennen die Instanz, gegen die sie prüfen.'],
            ],
        ];
    }

    private function VaultActivatePanel(): array
    {
        return [
            'type' => 'ExpansionPanel', 'expanded' => true,
            'caption' => '🔒  Pförtner aktivieren',
            'items' => [
                ['type' => 'Label', 'caption' => 'Noch kein Passwort gesetzt — dieses Formular ist offen. Passwort zweimal eingeben und „Aktivieren“ klicken.'],
                ['type' => 'Label', 'caption' => 'Schützt nur vor Klicks in der Konsole, NICHT vor Skript-/API-Zugriff (siehe „📖 Dokumentation & Hilfe“). Fürs Tippfehler-Risiko wird das Passwort zweimal abgefragt.'],
                ['type' => 'PasswordTextBox', 'name' => 'VaultNewPassword', 'caption' => 'Neues Passwort'],
                ['type' => 'PasswordTextBox', 'name' => 'VaultNewPasswordRepeat', 'caption' => 'Neues Passwort wiederholen'],
                ['type' => 'Button', 'caption' => '🔒 Aktivieren', 'onClick' => 'echo PFOE_SetVaultPassword($id, $VaultNewPassword, $VaultNewPasswordRepeat);'],
            ],
        ];
    }

    private function VaultUnlockedPanel(): array
    {
        return [
            'type' => 'ExpansionPanel', 'expanded' => true,
            'caption' => '🔓  Pförtner — entsperrt',
            'items' => [
                ['type' => 'Label', 'caption' => 'Passwort ist gesetzt, dieses Formular ist gerade entsperrt. „Jetzt sperren“ sperrt sofort; ansonsten bleibt es entsperrt, bis das Formular geschlossen und neu geöffnet wird.'],
                ['type' => 'Button', 'caption' => '🔒 Jetzt sperren', 'onClick' => 'echo PFOE_LockVaultNow($id);'],
                [
                    'type' => 'ExpansionPanel', 'expanded' => false,
                    'caption' => '🔑  Passwort ändern oder Pförtner deaktivieren',
                    'items' => [
                        ['type' => 'Label', 'caption' => 'Neues Passwort zweimal eingeben. Beide Felder leer lassen und „Übernehmen“ klicken = Pförtner deaktivieren (Formular dauerhaft offen).'],
                        ['type' => 'PasswordTextBox', 'name' => 'VaultNewPassword', 'caption' => 'Neues Passwort'],
                        ['type' => 'PasswordTextBox', 'name' => 'VaultNewPasswordRepeat', 'caption' => 'Neues Passwort wiederholen'],
                        ['type' => 'Button', 'caption' => 'Übernehmen', 'onClick' => 'echo PFOE_SetVaultPassword($id, $VaultNewPassword, $VaultNewPasswordRepeat);'],
                    ],
                ],
            ],
        ];
    }

    private function ConsumerPanel(): array
    {
        $id = $this->InstanceID;
        return [
            'type' => 'ExpansionPanel', 'expanded' => false,
            'caption' => '🔌  Für andere Module (Vertrag)',
            'items' => [
                ['type' => 'Label', 'caption' => 'Andere Module können dasselbe Passwort für ihre eigenen sensiblen Formular-Panels abfragen. Diese Instanz hat die ID ' . $id . '.'],
                ['type' => 'Label', 'caption' => 'PFOE_CheckAccess(' . $id . ', $passwort): bool — reiner Passwort-Vergleich, verändert nichts an dieser Instanz. PFOE_IsActive(' . $id . '): bool — ob überhaupt ein Passwort gesetzt ist (wenn nicht: Panels normal zeigen, Fail-open).'],
                ['type' => 'Label', 'caption' => 'Der Pförtner kann kein fremdes Formular sperren (Symcon hat dafür keinen Mechanismus). Jedes Partnermodul baut seine Sperr-UI selbst: eigenes „Entsperrt“-Attribut, Passwortfeld + „Entsperren“-Button, der PFOE_CheckAccess() aufruft. Aufrufe immer hinter function_exists(\'PFOE_CheckAccess\'). Muster und Beispielcode: README im Repository.'],
            ],
        ];
    }

    private function ForumHint(): ?array
    {
        if ($this->ReadAttributeBoolean('ForumHintGone')) {
            return null;
        }
        return [
            'type' => 'ExpansionPanel', 'name' => 'ForumHintPanel', 'expanded' => true,
            'caption' => '💬  Rückmeldungen',
            'items' => [
                ['type' => 'Label', 'caption' => '🧪 Der Pförtner ist neu — Fragen, Wünsche oder Fehler sind willkommen (Symcon-Forum bzw. GitHub).'],
                ['type' => 'Button', 'caption' => 'Zum Repository', 'onClick' => "echo '" . self::REPO_URL . "';", 'link' => true],
                ['type' => 'Button', 'caption' => 'Verstanden – nicht mehr anzeigen', 'onClick' => 'PFOE_AckForumHint($id);'],
            ],
        ];
    }

    /**
     * Lizenz-/Unterstützungs-Hinweis — Wortlaut verbundweit identisch
     * (SUITE.md "Einheitliche Formular-Optik", Variante A). Bewusst NICHT
     * dismissible, ganz unten nach dem Forum-Hinweis.
     */
    private function LicenseHint(): array
    {
        return [
            'type' => 'ExpansionPanel', 'expanded' => false,
            'caption' => '🧡  Über dieses Modul',
            'items' => [
                ['type' => 'Label', 'caption' => 'Entstanden aus echter Begeisterung für die eigene Anlage — und ein paar durchgetippten Abenden. Trotzdem: Software-Hobby hin oder her, das hier ist geistiges Eigentum und echte Arbeit steckt drin.'],
                ['type' => 'Label', 'caption' => 'Lizenz: PolyForm Noncommercial 1.0.0 — privat und nicht-kommerziell frei nutzbar, für den gewerblichen Einsatz braucht es eine gesonderte Lizenz vom Rechteinhaber.'],
                ['type' => 'Button', 'caption' => 'Lizenztext ansehen', 'onClick' => "echo '" . self::LICENSE_URL . "';", 'link' => true],
                ['type' => 'Label', 'caption' => 'Gewerbliche Nutzung oder Fragen zur Lizenz? Einfach melden: dietmar@gureth.eu'],
                ['type' => 'Label', 'caption' => 'Gefällt dir das Modul und du möchtest trotzdem etwas dalassen? Über eine kleine Spende freue ich mich — völlig freiwillig, keine Gegenleistung nötig.'],
                ['type' => 'Button', 'caption' => '☕  Spenden via PayPal', 'onClick' => "echo '" . self::PAYPAL_URL . "';", 'link' => true],
            ],
        ];
    }

    // -----------------------------------------------------------------
    // Formular — verzweigt GANZ AM ANFANG, bevor irgendetwas anderes
    // gebaut wird:
    //   1. kein Passwort:            volles Formular + Panel "aktivieren"
    //   2. Passwort, gesperrt:       NUR Passwortfeld + "Entsperren"
    //   3. Passwort, entsperrt:      volles Formular + Panel "entsperrt"
    // -----------------------------------------------------------------

    /** Referenz-Muster für Konsumenten: das gesperrte Minimal-Formular. */
    private function LockedForm(): array
    {
        return [
            ['type' => 'Label', 'caption' => '🔒 Der Pförtner lässt dich noch nicht rein — bitte Passwort eingeben.'],
            ['type' => 'PasswordTextBox', 'name' => 'VaultPasswordInput', 'caption' => 'Passwort'],
            ['type' => 'Button', 'caption' => '🔓 Entsperren', 'onClick' => 'echo PFOE_UnlockVault($id, $VaultPasswordInput);'],
        ];
    }

    public function GetConfigurationForm()
    {
        $base   = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        $status = $base['status'] ?? [];

        $hasPassword = $this->ReadAttributeString('VaultPasswordHash') !== '';
        $unlocked    = $this->ReadAttributeBoolean('VaultUnlocked');

        if ($hasPassword && !$unlocked) {
            return json_encode(['elements' => $this->LockedForm(), 'actions' => [], 'status' => $status]);
        }

        $elements = array_values(array_filter(array_merge(
            [$this->PurposeIntro(), $this->NewsBanner(), $this->DocPanel()],
            [$hasPassword ? $this->VaultUnlockedPanel() : $this->VaultActivatePanel(), $this->ConsumerPanel()],
            [$this->ForumHint(), $this->LicenseHint()]
        )));

        return json_encode(['elements' => $elements, 'actions' => [], 'status' => $status]);
    }

    private function refreshStatus(): void
    {
        $this->SetStatus($this->ReadAttributeString('VaultPasswordHash') !== '' ? 102 : 104);
    }
}
