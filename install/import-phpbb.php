<?php
/**
 * install/import-phpbb.php – ein phpBB-Forum nach Forumix holen.
 *
 *   php install/import-phpbb.php --host=localhost --db=phpbb --user=… --pass=… --probe
 *   php install/import-phpbb.php --host=localhost --db=phpbb --user=… --pass=… \
 *                                --dateien=/pfad/zu/phpBB3/files
 *
 *   php install/import-phpbb.php --zuruecknehmen
 *
 * `--probe` liest und zaehlt, schreibt aber nichts. Der erste Aufruf gehoert
 * immer dorthin: er sagt, was ankommen wuerde und was nicht.
 *
 * `--zuruecknehmen` entfernt wieder, was ein Import angelegt hat — und nur
 * das. Wer von Hand etwas dazugestellt hat, behaelt es.
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
require_once __DIR__ . '/import_text.php';

/* ── Aufruf lesen ─────────────────────────────────────────────────────── */

$opt = [
    'host' => 'localhost', 'db' => '', 'user' => '', 'pass' => '',
    'praefix' => 'phpbb_', 'dateien' => '', 'port' => '3306',
];
$probe = false;
$zurueck = false;
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--probe') { $probe = true; continue; }
    if ($arg === '--zuruecknehmen') { $zurueck = true; continue; }
    if (preg_match('/^--([a-z]+)=(.*)$/s', $arg, $t) && array_key_exists($t[1], $opt)) {
        $opt[$t[1]] = $t[2];
        continue;
    }
    fwrite(STDERR, "Unbekannter Schalter: $arg\n");
    exit(2);
}

foreach ($zurueck ? [] : ['db', 'user'] as $pflicht) {
    if ($opt[$pflicht] === '') {
        fwrite(STDERR, "--$pflicht fehlt. Aufruf siehe Kopf dieser Datei.\n");
        exit(2);
    }
}

/* ── Zuruecknehmen ────────────────────────────────────────────────────── */

/**
 * Nimmt zurueck, was ein frueherer Lauf angelegt hat — und nur das.
 *
 * Moeglich wird das durch die Zuordnungstabelle: Sie haelt fest, welche Zeile
 * hier aus welcher Zeile dort entstanden ist. Angefasst wird ausschliesslich,
 * was dort steht. Was von Hand dazukam, bleibt.
 *
 * Ein Import, den man zurueckdrehen kann, ist ein Import, den jemand wagt.
 */
if ($zurueck) {
    try {
        db_fetch('SELECT 1 FROM import_zuordnung LIMIT 1');
    } catch (Throwable $e) {
        fwrite(STDERR, "Es gibt nichts zurückzunehmen (keine Zuordnung vorhanden).\n");
        exit(1);
    }

    // Reihenfolge von innen nach aussen, sonst haengen Verweise in der Luft.
    $schritte = [
        ['beitrag',   'posts',            'post_id'],
        ['thema',     'threads',          'thread_id'],
        ['board',     'boards',           'board_id'],
        ['kategorie', 'categories',       'cat_id'],
        ['pn',        'private_messages', 'pm_id'],
        ['user',      'users',            'user_id'],
    ];

    echo "── Zurücknehmen ──\n";
    foreach ($schritte as [$art, $tabelle, $spalte]) {
        try {
            $n = db_affected(
                "DELETE t FROM `$tabelle` t
                 JOIN import_zuordnung z ON z.art = ? AND z.neu_id = t.`$spalte`", [$art]);
            if ($n > 0) printf("  %-20s %d entfernt\n", $tabelle, $n);
        } catch (Throwable $e) {
            fwrite(STDERR, "  $tabelle: " . $e->getMessage() . "\n");
        }
    }
    db_execute('DROP TABLE import_zuordnung');
    echo "  Zuordnung            entfernt\n";
    exit(0);
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

/* ── Was beim Schreiben passieren wuerde ──────────────────────────────── */

/**
 * Die Zuordnung alter auf neue Kennungen.
 *
 * Sie steht in einer Tabelle und nicht im Speicher, aus zwei Gruenden: Ein
 * Forum mit hunderttausend Beitraegen sprengt sonst den Arbeitsspeicher, und
 * ein Lauf, der auf halber Strecke abbricht, laesst sich fortsetzen statt von
 * vorn zu beginnen.
 *
 * Sie bleibt nach dem Import stehen. Wer spaeter wissen will, woher ein
 * Beitrag stammt, findet es hier — und ein zweiter Lauf ueberspringt, was
 * schon da ist, statt Doppelgaenger anzulegen.
 */
function import_zuordnung_bereit(): void
{
    db_execute("
        CREATE TABLE IF NOT EXISTS `import_zuordnung` (
            `art`    VARCHAR(20)  NOT NULL,
            `alt_id` INT UNSIGNED NOT NULL,
            `neu_id` INT UNSIGNED NOT NULL,
            `notiz`  VARCHAR(100) NOT NULL DEFAULT '',
            PRIMARY KEY (`art`, `alt_id`),
            KEY `neu` (`art`, `neu_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function import_merken(string $art, int $alt, int $neu, string $notiz = ''): void
{
    db_execute('INSERT INTO import_zuordnung (art, alt_id, neu_id, notiz) VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE neu_id = VALUES(neu_id), notiz = VALUES(notiz)',
               [$art, $alt, $neu, mb_substr($notiz, 0, 100)]);
}

function import_neu_id(string $art, int $alt): ?int
{
    $z = db_fetch('SELECT neu_id FROM import_zuordnung WHERE art = ? AND alt_id = ?', [$art, $alt]);
    return $z ? (int) $z['neu_id'] : null;
}

/** Ein Zeitstempel von phpBB (Unix) als Datum fuer Forumix. */
function import_zeit(?int $unix): string
{
    return date('Y-m-d H:i:s', ($unix !== null && $unix > 0) ? $unix : time());
}

if ($probe) {
    echo "\n── Was der Import tun würde ──\n";

    // Namen und Adressen, die es hier schon gibt. Beide sind in Forumix
    // eindeutig; ein Zusammenlegen kaeme einer Uebergabe fremder Konten
    // gleich und findet deshalb nicht statt.
    $namen = $mails = [];
    foreach (db_fetchAll('SELECT username, email FROM users') as $z) {
        $namen[mb_strtolower((string) $z['username'])] = true;
        $mails[mb_strtolower((string) $z['email'])]    = true;
    }

    $neu = $nameDoppelt = $mailDoppelt = 0;
    foreach ($alt->query("SELECT username, user_email FROM {$p}users WHERE user_type <> 2") as $z) {
        $n = mb_strtolower((string) $z['username']);
        $m = mb_strtolower((string) $z['user_email']);
        if (isset($namen[$n]))      { $nameDoppelt++; continue; }
        if ($m !== '' && isset($mails[$m])) { $mailDoppelt++; continue; }
        $neu++;
    }

    printf("  Konten anlegen       %d\n", $neu);
    if ($nameDoppelt > 0) printf("  Name schon vergeben  %d (werden übersprungen)\n", $nameDoppelt);
    if ($mailDoppelt > 0) printf("  Adresse schon da     %d (werden übersprungen)\n", $mailDoppelt);
    printf("  Kategorien anlegen   %d\n", $kategorien);
    printf("  Foren anlegen        %d\n", $foren);

    echo "\n── Probelauf — es wurde nichts geschrieben ──\n";
    exit(0);
}

/* ── Schreiben ────────────────────────────────────────────────────────── */

echo "\n── Import ──\n";
import_zuordnung_bereit();

/* Konten. Uebersprungen wird, wessen Name oder Adresse hier schon vergeben
   ist: beide sind eindeutig, und ein Zusammenlegen waere die Uebergabe eines
   fremden Kontos an jemanden, der zufaellig dieselbe Adresse benutzt. */
$namen = $mails = [];
foreach (db_fetchAll('SELECT username, email FROM users') as $z) {
    $namen[mb_strtolower((string) $z['username'])] = true;
    $mails[mb_strtolower((string) $z['email'])]    = true;
}

$angelegt = $uebersprungen = $schon = 0;

/* Wohnort, Beruf und Interessen liegen seit 3.1 nicht mehr in der
   Benutzertabelle, sondern als Profilfelder daneben — und die Tabelle gibt es
   nur, wenn welche eingerichtet sind. Fehlt sie, bleiben die Felder leer. */
$profilDa = $tabelle_da($p . 'profile_fields_data');
$profilSpalten = [];
if ($profilDa) {
    try {
        foreach ($alt->query("SHOW COLUMNS FROM {$p}profile_fields_data") as $sp) {
            $profilSpalten[$sp['Field']] = true;
        }
    } catch (Throwable $e) { $profilDa = false; }
}
$hatProfil = fn(string $f): bool => $profilDa && isset($profilSpalten[$f]);

$felder = "u.user_id, u.username, u.user_email, u.user_password, u.user_type,
           u.user_regdate, u.user_lastvisit, u.user_posts, u.user_sig";
foreach (['pf_phpbb_location' => 'ort', 'pf_phpbb_occupation' => 'beruf',
          'pf_phpbb_interests' => 'interessen'] as $sp => $als) {
    $felder .= $hatProfil($sp) ? ", d.`$sp` AS $als" : ", NULL AS $als";
}
$join = $profilDa ? "LEFT JOIN {$p}profile_fields_data d ON d.user_id = u.user_id" : '';

$abfrage = $alt->query("SELECT $felder FROM {$p}users u $join
                        WHERE u.user_type <> 2 ORDER BY u.user_id");

foreach ($abfrage as $z) {
    $altId = (int) $z['user_id'];
    if (import_neu_id('user', $altId) !== null) { $schon++; continue; }

    $name = trim((string) $z['username']);
    $mail = mb_strtolower(trim((string) $z['user_email']));
    if ($name === '' || $mail === '') { $uebersprungen++; continue; }
    if (isset($namen[mb_strtolower($name)]) || isset($mails[$mail])) {
        $uebersprungen++;
        continue;
    }

    // Die Signatur steht wie der Beitragstext als XML da.
    $signatur = phpbb_text_zu_bbcode((string) ($z['user_sig'] ?? ''));

    try {
        $ort = mb_substr(trim((string) ($z['ort'] ?? '')), 0, 100);
        $neuId = (int) db_execute(
            'INSERT INTO users (username, password, email, signature, post_count, `rank`,
                                registered_at, last_login, email_verified, location, is_banned,
                                profile_location, profile_occupation, profile_interests)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?)',
            [
                $name,
                (string) $z['user_password'],
                $mail,
                $signatur !== '' ? $signatur : null,
                (int) $z['user_posts'],
                'Mitglied',
                import_zeit((int) $z['user_regdate']),
                ((int) $z['user_lastvisit']) > 0 ? import_zeit((int) $z['user_lastvisit']) : null,
                $ort,
                ((int) $z['user_type']) === 1 ? 1 : 0,   // 1 = inaktiv/gesperrt
                $ort !== '' ? $ort : null,
                mb_substr(trim((string) ($z['beruf'] ?? '')), 0, 100) ?: null,
                mb_substr(trim((string) ($z['interessen'] ?? '')), 0, 500) ?: null,
            ]
        );
    } catch (Throwable $e) {
        fwrite(STDERR, "  Konto \"$name\" nicht angelegt: " . $e->getMessage() . "\n");
        $uebersprungen++;
        continue;
    }

    import_merken('user', $altId, $neuId, $name);
    $namen[mb_strtolower($name)] = true;
    $mails[$mail] = true;
    $angelegt++;
}

printf("  Konten               %d angelegt", $angelegt);
if ($schon > 0)          printf(", %d schon aus einem früheren Lauf", $schon);
if ($uebersprungen > 0)  printf(", %d übersprungen", $uebersprungen);
echo "\n";

/* Kategorien und Foren. phpBB fuehrt beide in einer Tabelle und unterscheidet
   sie am forum_type; Forumix hat zwei Tabellen. Verschachtelte Foren (ein
   Forum in einem Forum) gibt es in Forumix nicht — sie landen flach in der
   Kategorie ihres obersten Elternteils. */
$katAngelegt = $forenAngelegt = 0;

foreach ($alt->query("SELECT forum_id, forum_name, forum_desc, left_id
                      FROM {$p}forums WHERE forum_type = 0 ORDER BY left_id") as $z) {
    $altId = (int) $z['forum_id'];
    if (import_neu_id('kategorie', $altId) !== null) continue;
    $neuId = (int) db_execute('INSERT INTO categories (title, position) VALUES (?, ?)',
                              [mb_substr((string) $z['forum_name'], 0, 100), (int) $z['left_id']]);
    import_merken('kategorie', $altId, $neuId, (string) $z['forum_name']);
    $katAngelegt++;
}

// Ein Forum ohne eigene Kategorie braucht trotzdem eine.
$sammel = null;
$sammel_holen = function () use (&$sammel): int {
    if ($sammel === null) {
        $da = db_fetch('SELECT cat_id FROM categories ORDER BY position, cat_id LIMIT 1');
        $sammel = $da ? (int) $da['cat_id']
                      : (int) db_execute('INSERT INTO categories (title, position) VALUES (?, ?)',
                                         ['Übernommen', 99]);
    }
    return $sammel;
};

foreach ($alt->query("SELECT forum_id, forum_name, forum_desc, parent_id, left_id
                      FROM {$p}forums WHERE forum_type = 1 ORDER BY left_id") as $z) {
    $altId = (int) $z['forum_id'];
    if (import_neu_id('board', $altId) !== null) continue;

    $kat = import_neu_id('kategorie', (int) $z['parent_id']) ?? $sammel_holen();
    $beschreibung = trim(strip_tags((string) $z['forum_desc']));

    $neuId = (int) db_execute(
        'INSERT INTO boards (cat_id, title, description, position) VALUES (?, ?, ?, ?)',
        [$kat, mb_substr((string) $z['forum_name'], 0, 100),
         mb_substr($beschreibung, 0, 255), (int) $z['left_id']]
    );
    import_merken('board', $altId, $neuId, (string) $z['forum_name']);
    $forenAngelegt++;
}

printf("  Kategorien           %d angelegt\n", $katAngelegt);
printf("  Foren                %d angelegt\n", $forenAngelegt);

fwrite(STDERR, "\nThemen, Beiträge, Anhänge und Nachrichten folgen in der nächsten Stufe.\n");
exit(0);
