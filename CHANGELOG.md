# Changelog

Alle nennenswerten Änderungen an diesem Modul werden hier dokumentiert.
Format angelehnt an [Keep a Changelog](https://keepachangelog.com/de/1.0.0/).

## [0.2.0] - 2026-10-13

### Changed
- **Umbenennung auf den Funktionsnamen** (Dietmar, 13.10.2026, TOOLKIT.md „Umbenennung auf
  Funktionsnamen“): „Pförtner“ → **„DG65-Toolkit Formularschutz“**. Klasse und Modulordner
  `Formularschutz`, Funktionspräfix `FSCHUTZ_` statt `PFOE_`, Repo `DG65/Formularschutz`
  (GitHub-Umbenennung). **Bibliotheks- und Modul-GUID unverändert**, damit bestehende Instanzen
  sich wieder anhängen. Der Vertrag (`CheckAccess`/`IsActive`) ist inhaltlich unverändert, nur der
  Präfix wandert — Konsumenten rufen `FSCHUTZ_` zuerst und `PFOE_` als Rückfall auf.
- Genau ein Alias („DG65-Toolkit Formularschutz“), Bindestrich-Schreibweise „DG65-Toolkit“.
- Sperrtext ohne Pförtner-Bild: „Dieses Formular ist gesperrt — bitte Passwort eingeben.“
- News-Panel nach der verbundweiten `NEWS_VERSIONS`-Konvention (zeigt nur die Lücke seit der
  zuletzt bestätigten Version, „Neu bis Version X“; „Verstanden“ speichert die installierte
  Bibliotheksversion).

### Hinweis zum Umstieg
- Beim Neu-Anlegen des Moduls in der Modulverwaltung gehen Instanz-**Attribute** verloren — hier das
  Passwort (Hash) und die weggeklickten Hinweise. Der Formularschutz fällt dann bewusst **offen**
  zurück (nie „gesperrt ohne Passwort“); Passwort danach neu setzen.

## [0.1.0] - 2026-09-08

### Added
- Erstes Release. Passwortschutz fürs eigene Konfigurationsformular: Panel „🔒 Pförtner
  aktivieren“ (Passwort zweimal, Tippfehler-Schutz), danach zeigt das Formular beim Öffnen nur noch
  Passwortfeld + „🔓 Entsperren“. Im entsperrten Zustand „🔒 Jetzt sperren“ (wirkt sofort) und
  „Passwort ändern/deaktivieren“ (beide Felder leer = deaktivieren).
  `PFOE_UnlockVault`/`PFOE_LockVaultNow`/`PFOE_SetVaultPassword`.
- Vertrag für andere Module: `PFOE_CheckAccess($id, $password): bool` (zustandsloser
  Passwort-Vergleich) und `PFOE_IsActive($id): bool` (Fail-open-Hilfe für Konsumenten). Erster
  Konsument: OCPPHub (Abschnittsschutz Kundenverwaltung/Abrechnung). Muster für Partnermodule im
  README.
- Passwort gehasht (`password_hash()`), nie Klartext; Attribute statt Properties. Fail-open bei
  Attribut-Verlust durch Modul-Resync — nie „gesperrt ohne bekanntes Passwort“. Kein Auto-Timeout
  (Dietmars Entscheidung). Ausweg per Skript dokumentiert (`PFOE_SetVaultPassword`).
- **Ausdrücklich KEIN echter Sicherheitsmechanismus** — schützt nur Formular-Klicks, nicht
  Skript-/API-Zugriff, kennt nur ein Passwort statt Nutzeridentität. Steht im Formular, README und
  CLAUDE.md.
- Formular nach Verbund-Konvention (Zweck-Intro, News, Doku, Rückmeldungen, Über-dieses-Modul),
  Prüfstand `.tools/test-form.php` (Lebenszyklus ohne Symcon, läuft in der CI),
  `.tools/check-standalone.php`, PolyForm-Noncommercial-Lizenz.

### Herkunft
- Design und Code entstammen dem kurzzeitig in StrukturHub gebauten „Formular-Tresor“
  (Repo `DG65/NRGStrukturHub`, Commit `c4a2c5a`, revertiert in `ae8b88f`) — auf Dietmars
  Entscheidung (30.08.2026) als eigenständiges DG65-Toolkit-Modul ausgekoppelt.
