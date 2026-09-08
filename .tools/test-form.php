<?php
/**
 * test-form.php — Prüfstand für Pförtner OHNE laufendes Symcon.
 *
 * Simuliert die IPSModule-Basisklasse (Attribute, Status, ReloadForm,
 * UpdateFormField) und spielt den kompletten Lebenszyklus durch:
 * inaktiv → aktivieren → sperren → falsch/richtig entsperren → Vertrag
 * (CheckAccess/IsActive) → Passwort ändern/deaktivieren → Fail-open nach
 * simuliertem Modul-Resync (Attribut-Verlust, SUITE.md Stolperstein 5).
 *
 * Aufruf:   php .tools/test-form.php
 * Rückgabe: 0 = alle Blöcke bestanden, 1 = mindestens ein Fehlschlag
 *
 * Läuft auch in der CI (check-style.yml). Warum der Ordner .tools heißt:
 * siehe check-standalone.php (Store-Scanner überspringt Punkt-Ordner).
 */

class IPSModule
{
    public int $InstanceID = 12345;
    public array $attrs = [];
    public int $status = 0;
    public int $reloads = 0;
    public array $fieldUpdates = [];
    public array $debug = [];

    public function Create() {}
    public function ApplyChanges() {}
    public function RegisterAttributeString(string $n, string $d): void { if (!array_key_exists($n, $this->attrs)) { $this->attrs[$n] = $d; } }
    public function RegisterAttributeBoolean(string $n, bool $d): void { if (!array_key_exists($n, $this->attrs)) { $this->attrs[$n] = $d; } }
    public function ReadAttributeString(string $n): string { return $this->attrs[$n]; }
    public function ReadAttributeBoolean(string $n): bool { return $this->attrs[$n]; }
    public function WriteAttributeString(string $n, string $v): void { $this->attrs[$n] = $v; }
    public function WriteAttributeBoolean(string $n, bool $v): void { $this->attrs[$n] = $v; }
    public function SetStatus(int $s): void { $this->status = $s; }
    public function ReloadForm(): void { $this->reloads++; }
    public function UpdateFormField(string $n, string $p, $v): void { $this->fieldUpdates[] = [$n, $p, $v]; }
    public function SendDebug(string $a, string $b, int $c): void { $this->debug[] = $b; }

    /** Simuliert MC_DeleteModule()+MC_CreateModule(): Attribute weg, Create() läuft neu. */
    public function simulateResync(): void { $this->attrs = []; $this->Create(); $this->ApplyChanges(); }
}

if (!function_exists('IPS_GetLibrary')) {
    function IPS_GetLibrary(string $guid): array { return ['Version' => '0.0.0-test', 'Build' => 0]; }
}

require dirname(__DIR__) . '/Pfoertner/module.php';

$fails = 0;
$block = '';
function check(string $what, bool $ok): void
{
    global $fails, $block;
    echo ($ok ? '  ✅ ' : '  ❌ ') . $what . "\n";
    if (!$ok) { $fails++; }
}
function heading(string $t): void { global $block; $block = $t; echo "\n[$t]\n"; }

/** Formular als Array + Kurz-Analyse. */
function form(Pfoertner $m): array
{
    $json = $m->GetConfigurationForm();
    $f = json_decode($json, true);
    if (!is_array($f)) { throw new RuntimeException('GetConfigurationForm() liefert kein gültiges JSON'); }
    return $f;
}
function formText(array $f): string { return json_encode($f, JSON_UNESCAPED_UNICODE); }
function hasPanel(array $f, string $captionPart): bool
{
    foreach ($f['elements'] as $e) {
        if (($e['type'] ?? '') === 'ExpansionPanel' && str_contains($e['caption'] ?? '', $captionPart)) { return true; }
    }
    return false;
}
function isLocked(array $f): bool
{
    $t = formText($f);
    return str_contains($t, 'lässt dich noch nicht rein') && count($f['elements']) === 3;
}

$m = new Pfoertner();
$m->Create();
$m->ApplyChanges();

heading('1. Frisch angelegt — inaktiv, Formular offen');
$f = form($m);
check('IsActive() ist false', $m->IsActive() === false);
check('Status 104 (inaktiv)', $m->status === 104);
check('Formular ist NICHT gesperrt', !isLocked($f));
check('Panel „Pförtner aktivieren“ vorhanden', hasPanel($f, 'Pförtner aktivieren'));
check('Zweck-Intro steht ganz vorn', ($f['elements'][0]['name'] ?? '') === 'PurposeIntroPanel');
check('News-Panel an zweiter Stelle', ($f['elements'][1]['name'] ?? '') === 'NewsPanel');
check('Doku-Panel an dritter Stelle', str_contains($f['elements'][2]['caption'] ?? '', 'Dokumentation'));
check('Lizenz-Panel ganz unten', str_contains(end($f['elements'])['caption'] ?? '', 'Über dieses Modul'));
check('Status-Codes 102/104 im Formular beschriftet', count($f['status'] ?? []) === 2);
check('Sicherheits-Einschränkung steht im Formular', str_contains(formText($f), 'kein Sicherheitsmechanismus'));
check('CheckAccess(\'\') ist false, solange inaktiv', $m->CheckAccess('') === false);
check('UnlockVault() im inaktiven Zustand gibt ℹ️-Hinweis', str_starts_with($m->UnlockVault('x'), 'ℹ️'));
check('LockVaultNow() im inaktiven Zustand gibt ℹ️-Hinweis', str_starts_with($m->LockVaultNow(), 'ℹ️'));

heading('2. Aktivieren — Tippfehler-Schutz und Erfolg');
$r = $m->SetVaultPassword('geheim', 'anders');
check('Ungleiche Wiederholung wird abgelehnt (⛔)', str_starts_with($r, '⛔'));
check('… und bleibt inaktiv', $m->IsActive() === false);
$r = $m->SetVaultPassword('', '');
check('Leer+leer im inaktiven Zustand: bleibt inaktiv (ℹ️)', str_starts_with($r, 'ℹ️') && !$m->IsActive());
$reloadsBefore = $m->reloads;
$r = $m->SetVaultPassword('geheim', 'geheim');
check('Aktivieren meldet ✅', str_starts_with($r, '✅'));
check('IsActive() ist true', $m->IsActive() === true);
check('Status 102 (aktiv)', $m->status === 102);
check('Formular wird neu aufgebaut (ReloadForm)', $m->reloads === $reloadsBefore + 1);
$hash = $m->attrs['VaultPasswordHash'];
check('Passwort liegt NICHT im Klartext im Attribut', $hash !== 'geheim' && !str_contains($hash, 'geheim'));
check('Attribut ist ein password_hash()-Hash', password_get_info($hash)['algo'] !== null && password_verify('geheim', $hash));
$f = form($m);
check('Direkt nach Aktivieren: entsperrt (volles Formular)', !isLocked($f));
check('Panel „entsperrt“ mit „Jetzt sperren“ sichtbar', hasPanel($f, 'entsperrt') && str_contains(formText($f), 'Jetzt sperren'));
check('Panel „aktivieren“ nicht mehr sichtbar', !hasPanel($f, 'Pförtner aktivieren'));

heading('3. Sperren');
$r = $m->LockVaultNow();
check('LockVaultNow meldet 🔒', str_starts_with($r, '🔒'));
$f = form($m);
check('Formular ist jetzt gesperrt (nur 3 Elemente)', isLocked($f));
check('Gesperrt: genau EIN PasswordTextBox, sonst keine Eingabefelder', count(array_filter($f['elements'], fn($e) => $e['type'] === 'PasswordTextBox')) === 1);
check('Gesperrt: nichts vom Fach-/Doku-Inhalt sichtbar', !str_contains(formText($f), 'Dokumentation') && !str_contains(formText($f), 'Jetzt sperren'));

heading('4. Entsperren — falsch und richtig');
$reloadsBefore = $m->reloads;
$r = $m->UnlockVault('falsch');
check('Falsches Passwort → ⛔', str_starts_with($r, '⛔'));
check('… kein ReloadForm', $m->reloads === $reloadsBefore);
check('… Formular bleibt gesperrt', isLocked(form($m)));
check('… Passwort taucht nicht im Debug auf', !in_array('falsch', $m->debug, true) && !str_contains(implode('|', $m->debug), 'falsch'));
$r = $m->UnlockVault('geheim');
check('Richtiges Passwort → ✅', str_starts_with($r, '✅'));
check('… ReloadForm ausgelöst', $m->reloads === $reloadsBefore + 1);
check('… Formular ist offen', !isLocked(form($m)));

heading('5. Vertrag für andere Module (zustandslos)');
$m->LockVaultNow();
check('CheckAccess(richtig) → true', $m->CheckAccess('geheim') === true);
check('CheckAccess(falsch) → false', $m->CheckAccess('falsch') === false);
check('CheckAccess(\'\') → false', $m->CheckAccess('') === false);
check('CheckAccess verändert den Sperrzustand NICHT', isLocked(form($m)));
check('IsActive() → true', $m->IsActive() === true);
$m->UnlockVault('geheim');

heading('6. Passwort ändern und deaktivieren');
$r = $m->SetVaultPassword('neu', 'neu');
check('Ändern meldet ✅', str_starts_with($r, '✅'));
check('Altes Passwort gilt nicht mehr', $m->CheckAccess('geheim') === false);
check('Neues Passwort gilt', $m->CheckAccess('neu') === true);
$r = $m->SetVaultPassword('', '');
check('Leer+leer im aktiven Zustand deaktiviert (ℹ️)', str_starts_with($r, 'ℹ️'));
check('IsActive() → false, Status 104', $m->IsActive() === false && $m->status === 104);
check('Formular offen, Panel „aktivieren“ zurück', !isLocked(form($m)) && hasPanel(form($m), 'Pförtner aktivieren'));

heading('7. Fail-open nach Modul-Resync (Attribut-Verlust)');
$m->SetVaultPassword('geheim', 'geheim');
$m->LockVaultNow();
check('Vorher: gesperrt', isLocked(form($m)));
$m->simulateResync();
check('Nach Resync: IsActive() false', $m->IsActive() === false);
check('Nach Resync: Formular OFFEN, nie „gesperrt ohne Passwort“', !isLocked(form($m)));
check('Nach Resync: Status 104', $m->status === 104);

heading('8. Vertrags-Hygiene (SUITE.md Stolperstein 8/20)');
$rc = new ReflectionClass(Pfoertner::class);
$publicApi = ['CheckAccess', 'IsActive', 'UnlockVault', 'LockVaultNow', 'SetVaultPassword', 'AckPurposeIntro', 'AckNews', 'AckForumHint'];
foreach ($publicApi as $name) {
    $rm = $rc->getMethod($name);
    $okTypes = true; $okDefaults = true;
    foreach ($rm->getParameters() as $p) {
        $t = $p->getType();
        if ($t === null || !in_array($t->getName(), ['bool', 'int', 'float', 'string'], true)) { $okTypes = false; }
        if ($p->isOptional()) { $okDefaults = false; }
    }
    check("$name(): alle Parameter skalar typisiert", $okTypes);
    check("$name(): keine PHP-Standardwerte", $okDefaults);
}
check('CheckAccess() liefert bool (kein Text-als-bool-Risiko)', (string)$rc->getMethod('CheckAccess')->getReturnType() === 'bool');
check('IsActive() liefert bool', (string)$rc->getMethod('IsActive')->getReturnType() === 'bool');

heading('9. Jeder onClick-Aufruf zeigt auf eine existierende öffentliche Methode');
$allForms = [];
$m->SetVaultPassword('', '');
$allForms[] = formText(form($m));               // Zustand 1
$m->SetVaultPassword('geheim', 'geheim');
$allForms[] = formText(form($m));               // Zustand 3
$m->LockVaultNow();
$allForms[] = formText(form($m));               // Zustand 2
preg_match_all('/PFOE_([A-Za-z]+)\(/', implode("\n", $allForms), $mm);
$called = array_unique($mm[1]);
check('Mindestens die Kern-Aktionen werden im Formular aufgerufen', count(array_diff(['UnlockVault', 'LockVaultNow', 'SetVaultPassword'], $called)) === 0);
foreach ($called as $fn) {
    check("PFOE_$fn → public function $fn() existiert", $rc->hasMethod($fn) && $rc->getMethod($fn)->isPublic());
}
check('Kein Link-Button trägt die URL direkt in "link" (muss true/false sein)', !preg_match('/"link":"http/', implode("\n", $allForms)));

echo "\n" . str_repeat('-', 62) . "\n";
if ($fails === 0) {
    echo "OK — alle Prüfungen bestanden.\n";
    exit(0);
}
echo "FEHLER — $fails Prüfung(en) fehlgeschlagen.\n";
exit(1);
