<?php
/**
 * install/import-phpbb.php – ein phpBB-Forum nach Forumix holen.
 *
 *   php install/import-phpbb.php --host=localhost --db=phpbb --user=… --pass=… --probe
 *   php install/import-phpbb.php --host=localhost --db=phpbb --user=… --pass=… \
 *                                --dateien=/pfad/zu/phpBB3/files
 *
 * `--probe` liest und zaehlt, schreibt aber nichts. Der erste Aufruf gehoert
 * immer dorthin: er sagt, was ankommen wuerde und was nicht.
 *
 * ── Was mitkommt ──────────────────────────────────────────────────────
 *
 * Konten (samt Passwort), Kategorien, Foren, Themen, Beitraege, Anhaenge,
 * Umfragen und private Nachrichten.
 *
 * ── Was NICHT mitkommt, und warum ─────────────────────────────────────
 *
 * Bot-Konten (`user_type = 2`): phpBB legt bei der Installation rund sechzig
 * davon an — "Google [Bot]", "Yahoo Slurp" und so weiter. Sie sind
 * Anzeigehilfen der Wer-ist-online-Liste, keine Menschen.
 *
 * Rechte, Rollen und Gruppen: phpBB hat ein eigenes, sehr feingliedriges
 * System. Es maschinell auf die Gruppen von Forumix abzubilden wuerde
 * Rechte erfinden, die niemand vergeben hat. Nach dem Import stehen alle
 * Foren offen; wer sie einschraenken will, tut das im Adminbereich.
 *
 * ── Passwoerter ───────────────────────────────────────────────────────
 *
 * phpBB 3.2 und 3.3 legen sie als `$argon2id$` oder `$2y$` (bcrypt) ab.
 * Beides versteht `password_verify()` unveraendert — die Mitglieder melden
 * sich nach dem Umzug mit ihrem gewohnten Passwort an.
 *
 * Aeltere Konten koennen `$H$` tragen (phpBB 3.0, gesalzenes MD5). Das kann
 * Forumix nicht pruefen. Solche Konten kommen mit, muessen aber einmal
 * "Passwort vergessen" benutzen. Der Probelauf sagt vorher, wie viele es
 * sind.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('Dieser Import läuft nur auf der Kommandozeile.');
}

$wurzel = dirname(__DIR__);
if (!is_file($wurzel . '/config.php')) {
    fwrite(STDERR, "config.php fehlt — ist Forumix installiert?\n");
    exit(1);
}

// Wie install/migrate.php: eigene Sitzung im temporaeren Verzeichnis, damit
// der Aufruf als root keine Sitzungsdatei hinterlaesst, die niemandem gehoert.
ini_set('session.save_path', sys_get_temp_dir());
ini_set('session.use_cookies', '0');
@session_start();

define('FORUM_RUNNING', true);
require_once $wurzel . '/bootstrap.php';

/* ── Aufruf lesen ─────────────────────────────────────────────────────── */

$opt = [
    'host' => 'localhost', 'db' => '', 'user' => '', 'pass' => '',
    'praefix' => 'phpbb_', 'dateien' => '', 'port' => '3306',
];
$probe = false;
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--probe') { $probe = true; continue; }
    if (preg_match('/^--([a-z]+)=(.*)$/s', $arg, $t) && array_key_exists($t[1], $opt)) {
        $opt[$t[1]] = $t[2];
        continue;
    }
    fwrite(STDERR, "Unbekannter Schalter: $arg\n");
    exit(2);
}

foreach (['db', 'user'] as $pflicht) {
    if ($opt[$pflicht] === '') {
        fwrite(STDERR, "--$pflicht fehlt. Aufruf siehe Kopf dieser Datei.\n");
        exit(2);
    }
}

/* ── Verbindung zur phpBB-Datenbank ───────────────────────────────────── */

try {
    $alt = new PDO(
        "mysql:host={$opt['host']};port={$opt['port']};dbname={$opt['db']};charset=utf8mb4",
        $opt['user'], $opt['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (PDOException $e) {
    fwrite(STDERR, "Keine Verbindung zur phpBB-Datenbank: " . $e->getMessage() . "\n");
    exit(1);
}

$p = $opt['praefix'];

/** Gibt es diese Tabelle? */
$tabelle_da = function (string $name) use ($alt): bool {
    try { return (bool) $alt->query("SHOW TABLES LIKE " . $alt->quote($name))->fetch(); }
    catch (Throwable $e) { return false; }
};

foreach (['users', 'forums', 'topics', 'posts'] as $noetig) {
    if (!$tabelle_da($p . $noetig)) {
        fwrite(STDERR, "Tabelle {$p}{$noetig} fehlt — falsches Präfix (--praefix) oder keine phpBB-Datenbank.\n");
        exit(1);
    }
}

$zahl = function (string $sql) use ($alt): int {
    try { return (int) $alt->query($sql)->fetchColumn(); } catch (Throwable $e) { return 0; }
};

/* ── Fassung feststellen ──────────────────────────────────────────────── */

$fassung = '';
try {
    $fassung = (string) $alt->query(
        "SELECT config_value FROM {$p}config WHERE config_name = 'version'")->fetchColumn();
} catch (Throwable $e) {}

echo "── Gefunden ──\n";
echo "  phpBB-Fassung        " . ($fassung !== '' ? $fassung : 'unbekannt') . "\n";

if ($fassung !== '' && version_compare($fassung, '3.2.0', '<')) {
    echo "\n  ACHTUNG  Diese Fassung legt Beiträge in einem älteren Format ab.\n";
    echo "           Unterstützt sind 3.2 und 3.3. Ein Import würde die\n";
    echo "           Formatierung als rohen Text übernehmen.\n";
}

/* ── Zaehlen ──────────────────────────────────────────────────────────── */

$echte     = $zahl("SELECT COUNT(*) FROM {$p}users WHERE user_type <> 2");
$bots      = $zahl("SELECT COUNT(*) FROM {$p}users WHERE user_type = 2");
$kategorien= $zahl("SELECT COUNT(*) FROM {$p}forums WHERE forum_type = 0");
$foren     = $zahl("SELECT COUNT(*) FROM {$p}forums WHERE forum_type = 1");
$themen    = $zahl("SELECT COUNT(*) FROM {$p}topics");
$beitraege = $zahl("SELECT COUNT(*) FROM {$p}posts");
$anhaenge  = $tabelle_da($p . 'attachments')
           ? $zahl("SELECT COUNT(*) FROM {$p}attachments WHERE is_orphan = 0") : 0;
$umfragen  = $tabelle_da($p . 'poll_options')
           ? $zahl("SELECT COUNT(DISTINCT topic_id) FROM {$p}poll_options") : 0;
$pn        = $tabelle_da($p . 'privmsgs')
           ? $zahl("SELECT COUNT(*) FROM {$p}privmsgs") : 0;

printf("  Konten               %s\n", number_format($echte, 0, ',', '.'));
printf("  Kategorien           %s\n", number_format($kategorien, 0, ',', '.'));
printf("  Foren                %s\n", number_format($foren, 0, ',', '.'));
printf("  Themen               %s\n", number_format($themen, 0, ',', '.'));
printf("  Beiträge             %s\n", number_format($beitraege, 0, ',', '.'));
printf("  Anhänge              %s\n", number_format($anhaenge, 0, ',', '.'));
printf("  Umfragen             %s\n", number_format($umfragen, 0, ',', '.'));
printf("  Private Nachrichten  %s\n", number_format($pn, 0, ',', '.'));

/* ── Passwoerter ──────────────────────────────────────────────────────── */

$formate = [];
try {
    foreach ($alt->query("SELECT user_password FROM {$p}users WHERE user_type <> 2") as $z) {
        $h = (string) $z['user_password'];
        if ($h === '')                        $k = 'leer';
        elseif (str_starts_with($h, '$argon2')) $k = 'argon2';
        elseif (str_starts_with($h, '$2y$')
             || str_starts_with($h, '$2a$'))    $k = 'bcrypt';
        elseif (str_starts_with($h, '$H$')
             || str_starts_with($h, '$P$'))     $k = 'phpass';
        else                                    $k = 'anderes';
        $formate[$k] = ($formate[$k] ?? 0) + 1;
    }
} catch (Throwable $e) {}

$uebernehmbar = ($formate['argon2'] ?? 0) + ($formate['bcrypt'] ?? 0);
$manuell      = $echte - $uebernehmbar;

echo "\n── Passwörter ──\n";
printf("  Werden übernommen    %s (argon2: %d, bcrypt: %d)\n",
       number_format($uebernehmbar, 0, ',', '.'),
       $formate['argon2'] ?? 0, $formate['bcrypt'] ?? 0);
if ($manuell > 0) {
    printf("  Müssen neu gesetzt   %s", number_format($manuell, 0, ',', '.'));
    $rest = array_diff_key($formate, ['argon2' => 1, 'bcrypt' => 1]);
    if ($rest) {
        $teile = [];
        foreach ($rest as $k => $n) $teile[] = "$k: $n";
        echo ' (' . implode(', ', $teile) . ')';
    }
    echo "\n";
    echo "                       Diese Konten kommen mit, ihre Inhaber benutzen\n";
    echo "                       einmal \"Passwort vergessen\".\n";
}

/* ── Dateien ──────────────────────────────────────────────────────────── */

if ($anhaenge > 0) {
    echo "\n── Anhänge ──\n";
    if ($opt['dateien'] === '') {
        echo "  Kein Ordner angegeben (--dateien). Die Anhänge blieben leer.\n";
        echo "  Gesucht wird der Ordner, den phpBB unter \"upload_path\" führt —\n";
        echo "  in der Regel phpBB3/files.\n";
    } elseif (!is_dir($opt['dateien'])) {
        echo "  Ordner nicht gefunden: {$opt['dateien']}\n";
    } else {
        $fehlen = 0; $gefunden = 0;
        foreach ($alt->query("SELECT physical_filename FROM {$p}attachments WHERE is_orphan = 0") as $z) {
            is_file(rtrim($opt['dateien'], '/') . '/' . $z['physical_filename']) ? $gefunden++ : $fehlen++;
        }
        printf("  Vorhanden            %d von %d\n", $gefunden, $anhaenge);
        if ($fehlen > 0) printf("  FEHLEN               %d — der Ordner passt nicht zur Datenbank\n", $fehlen);
    }
}

/* ── Ziel ─────────────────────────────────────────────────────────────── */

echo "\n── Ziel ──\n";
try {
    printf("  Forumix              %s\n", forum_version());
    printf("  Vorhandene Konten    %d\n", db_count('SELECT COUNT(*) FROM users'));
    printf("  Vorhandene Beiträge  %d\n", db_count('SELECT COUNT(*) FROM posts'));
} catch (Throwable $e) {
    fwrite(STDERR, "Die Forumix-Datenbank ist nicht lesbar: " . $e->getMessage() . "\n");
    exit(1);
}

if ($probe) {
    echo "\n── Probelauf — es wurde nichts geschrieben ──\n";
    exit(0);
}

fwrite(STDERR, "\nDas Schreiben ist noch nicht gebaut. Bis dahin: --probe.\n");
exit(2);
