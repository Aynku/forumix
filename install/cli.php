<?php
/**
 * Forumix – Installer für die Kommandozeile
 * --------------------------------------------------------------------------
 * Derselbe Ablauf wie install/index.php, nur ohne Formular. Gedacht für zwei
 * Fälle: eigener Server mit SSH, und das Bereitstellen gehosteter Instanzen,
 * wo niemand von Hand durch fünf Schritte klickt.
 *
 *   php install/cli.php --config=setup.json
 *   php erzeuge-setup.php | php install/cli.php --config=-
 *
 * Die Angaben kommen als JSON, NICHT als Argumente. Grund: Argumente stehen in
 * `ps` und sind damit für jeden anderen Benutzer der Maschine lesbar — das
 * Datenbank- und das Inhaber-Passwort wären offen. Über eine Datei (0600) oder
 * über die Standardeingabe sind sie es nicht.
 *
 * Aufbau der JSON-Datei:
 * {
 *   "db":    {"host":"localhost","name":"forum_db","user":"forum","pass":"…",
 *             "charset":"utf8mb4"},
 *   "forum": {"name":"Mein Forum","url":"https://forum.example.com"},
 *   "owner": {"user":"Chef","email":"chef@example.com","pass":"…"},
 *   "extra": {"FORUM_MAIL_FROM":"noreply@example.com"},
 *   "demo": false,
 *   "cleanup": true
 * }
 *
 * "extra"   schreibt zusätzliche Konstanten in die config.php (optional).
 * "demo"    legt vier Beispiel-Foren an (optional, Vorgabe aus). Ohne sie faengt
 *           das Forum mit einer leeren Uebersicht an — richtig fuer jeden, der
 *           eigene Foren anlegt oder ein bestehendes uebernimmt.
 * "cleanup" räumt den Installer nach Erfolg selbst weg (optional, Vorgabe aus).
 *
 * Rückgabe: 0 = fertig, 1 = abgebrochen. Fehler gehen nach STDERR.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Dieser Installer läuft nur auf der Kommandozeile. Für den Browser: /install/');
}

error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

define('FORUM_INSTALLER', 'cli');
require __DIR__ . '/lib.php';

/* ── Ausgabe ──────────────────────────────────────────────────────────── */

$quiet = in_array('--quiet', $argv, true);

function sagen(string $text): void {
    global $quiet;
    if (!$quiet) { fwrite(STDOUT, $text . "\n"); }
}
function abbrechen(string $text, array $details = []): never {
    fwrite(STDERR, 'Abgebrochen: ' . $text . "\n");
    foreach ($details as $d) { fwrite(STDERR, '  - ' . $d . "\n"); }
    exit(1);
}

/* ── Angaben einlesen ─────────────────────────────────────────────────── */

$pfad = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--config=')) { $pfad = substr($arg, 9); }
}
if ($pfad === null) {
    fwrite(STDERR, "Aufruf: php install/cli.php --config=<datei.json>\n"
                 . "        php install/cli.php --config=-   (JSON über die Standardeingabe)\n");
    exit(1);
}

$roh = $pfad === '-' ? stream_get_contents(STDIN) : @file_get_contents($pfad);
if ($roh === false || $roh === '') {
    abbrechen($pfad === '-' ? 'nichts auf der Standardeingabe.' : "Datei nicht lesbar: $pfad");
}

try {
    $eingabe = json_decode($roh, true, 32, JSON_THROW_ON_ERROR);
} catch (JsonException $e) {
    abbrechen('Die Angaben sind kein gültiges JSON: ' . $e->getMessage());
}
if (!is_array($eingabe)) { abbrechen('Die Angaben müssen ein JSON-Objekt sein.'); }

$db    = $eingabe['db']    ?? [];
$forum = $eingabe['forum'] ?? [];
$owner = $eingabe['owner'] ?? [];

$c = [
    'host'       => trim((string) ($db['host'] ?? 'localhost')),
    'name'       => trim((string) ($db['name'] ?? '')),
    'user'       => trim((string) ($db['user'] ?? '')),
    'pass'       => (string) ($db['pass'] ?? ''),
    'charset'    => trim((string) ($db['charset'] ?? '')) ?: 'utf8mb4',
    'forum_name' => trim((string) ($forum['name'] ?? '')),
    'forum_url'  => rtrim(trim((string) ($forum['url'] ?? '')), '/'),
    'extra'      => $eingabe['extra'] ?? [],
];
$ownerUser  = trim((string) ($owner['user']  ?? ''));
$ownerMail  = trim((string) ($owner['email'] ?? ''));
$ownerPass  = (string) ($owner['pass'] ?? '');

$fehler = [];
if ($c['name'] === '' || $c['user'] === '') {
    $fehler[] = 'db.name und db.user sind Pflicht.';
}
if ($c['forum_name'] === '') { $fehler[] = 'forum.name fehlt.'; }
if (!filter_var($c['forum_url'], FILTER_VALIDATE_URL)) {
    $fehler[] = 'forum.url fehlt oder ist keine gültige Adresse (mit https://).';
}
if (!is_array($c['extra'])) { $fehler[] = 'extra muss ein Objekt sein.'; }
$fehler = array_merge($fehler, validate_owner($ownerUser, $ownerMail, $ownerPass));
if ($fehler) { abbrechen('Die Angaben stimmen nicht.', $fehler); }

/* ── 0. Ist hier schon ein Forum? ─────────────────────────────────────── */

if (install_already_done()) {
    abbrechen('An dieser Stelle ist bereits ein Forum installiert.', [
        'config.php bzw. install/.installed vorhanden in ' . FORUM_ROOT,
        'Der Installer überschreibt nichts von selbst — bitte von Hand entscheiden.',
    ]);
}

/* ── 1. Voraussetzungen ───────────────────────────────────────────────── */

$reqs   = check_requirements();
$hart   = array_slice($reqs, 0, count($reqs) - 1); // letzte (uploads) ist weich
$fehlt  = [];
foreach ($hart as [$label, $ok, $det]) {
    if (!$ok) { $fehlt[] = $label . ($det ? " ($det)" : ''); }
}
if ($fehlt) { abbrechen('Voraussetzungen fehlen.', $fehlt); }
sagen('✓ Voraussetzungen erfüllt');

/* ── 2. Datenbank ─────────────────────────────────────────────────────── */

try {
    $pdo = pdo_connect($c);
} catch (Throwable $e) {
    abbrechen('Keine Verbindung zur Datenbank: ' . $e->getMessage());
}
sagen('✓ Datenbank erreichbar (' . $c['name'] . '@' . $c['host'] . ')');

/* ── 3. Schema ────────────────────────────────────────────────────────── */
//
// install.sql beginnt mit DROP TABLE IF EXISTS. Eine bereits gefüllte
// Forum-Datenbank wäre danach leer. Deshalb hier die Bremse, die es im
// Browser als Warntext gibt: sind schon Forumix-Tabellen da, ist Schluss.

$vorhanden = $pdo->query(
    "SELECT COUNT(*) FROM information_schema.tables
      WHERE table_schema = DATABASE() AND table_name IN ('users','posts','settings')"
)->fetchColumn();
if ((int) $vorhanden > 0) {
    abbrechen('In dieser Datenbank stehen bereits Forumix-Tabellen.', [
        'install.sql würde sie löschen. Bitte eine leere Datenbank angeben.',
    ]);
}

try {
    $anzahl = run_schema($pdo);
} catch (Throwable $e) {
    abbrechen('Schema-Import fehlgeschlagen: ' . $e->getMessage());
}
// Vorgabe: keine Beispiel-Foren. Etwas, das man aktiv wegraeumen muss, ist
// aufgezwungen; wer sie will, traegt "demo": true ein.
if (empty($eingabe['demo'])) {
    try {
        drop_demo_content($pdo);
    } catch (Throwable $e) {
        abbrechen('Beispiel-Foren entfernen fehlgeschlagen: ' . $e->getMessage());
    }
}
sagen('✓ Schema angelegt (' . $anzahl . ' Anweisungen'
    . (empty($eingabe['demo']) ? ', ohne Beispiel-Foren' : ', mit Beispiel-Foren') . ')');

// install.sql ist der Stand von 1.0; alles danach kommt aus
// install/migrations/ — derselbe Weg, den auch ein Update geht.
try {
    $anpassungen = run_migrations($pdo);
} catch (Throwable $e) {
    abbrechen('Anpassungen fehlgeschlagen: ' . $e->getMessage());
}
sagen('✓ Anpassungen gefahren (' . count($anpassungen) . ')');

/* ── 4. Inhaber + Grundeinstellungen ──────────────────────────────────── */

try {
    create_owner($pdo, $ownerUser, $ownerMail, $ownerPass);
    setting_set($pdo, 'forum_secret', bin2hex(random_bytes(32)));
    setting_set($pdo, 'forum_name',   $c['forum_name']);
    setting_set($pdo, 'forum_url',    $c['forum_url']);
} catch (Throwable $e) {
    abbrechen('Inhaber-Konto anlegen fehlgeschlagen: ' . $e->getMessage());
}
sagen('✓ Inhaber angelegt (' . $ownerUser . ')');

/* ── 5. config.php + Sperrdatei ───────────────────────────────────────── */
//
// Reihenfolge wie im Browser-Installer: die config kommt zuletzt. Bricht es
// vorher ab, liegt hier keine Datei, die "installiert" behauptet.

try {
    if (!write_config($c)) {
        abbrechen('config.php konnte nicht geschrieben werden.', [
            'Schreibrechte prüfen in ' . FORUM_ROOT,
        ]);
    }
} catch (Throwable $e) {
    abbrechen('config.php konnte nicht geschrieben werden: ' . $e->getMessage());
}
sagen('✓ config.php geschrieben');

if (file_put_contents(LOCK_FILE, 'installed ' . date('c') . "\n") === false) {
    fwrite(STDERR, "Warnung: install/.installed konnte nicht geschrieben werden.\n"
                 . "Der Browser-Installer ist trotzdem gesperrt (FORUM_INSTALLED in der config).\n");
}

/* ── 6. Aufräumen ─────────────────────────────────────────────────────── */
//
// migrations/ bleibt stehen. Daraus holt der Adminbereich die Datenbank-
// Anpassungen späterer Updates; ist der Ordner weg, zeigt die Update-Seite
// stillschweigend nichts an und die Anpassungen laufen nie.

if (!empty($eingabe['cleanup'])) {
    $weg = 0;
    foreach (['index.php', 'lib.php', 'install.sql', '.installed', 'cli.php'] as $datei) {
        if (@unlink(INSTALL_DIR . '/' . $datei)) { $weg++; }
    }
    sagen('✓ Installer entfernt (' . $weg . ' Dateien, migrations/ bleibt)');
}

sagen('');
sagen('Fertig. ' . $c['forum_name'] . ' läuft unter ' . $c['forum_url']);
if (empty($eingabe['cleanup'])) {
    sagen('Noch zu tun: in install/ die Dateien index.php, cli.php, lib.php und');
    sagen('install.sql löschen — den Ordner migrations/ stehen lassen.');
}
exit(0);
