<?php
/**
 * Forumix – Installations-Kern
 * --------------------------------------------------------------------------
 * Geladen von install/index.php (Browser) und install/cli.php (Kommandozeile).
 *
 * Beide Wege muessen dasselbe Forum aufsetzen. Stuende die Arbeit zweimal
 * nebeneinander, wuerde eine neue Tabelle irgendwann nur in einem der beiden
 * Wege ankommen — und das faellt erst auf, wenn ein fertig aufgesetztes Forum
 * beim ersten Klick auf die Nase faellt. Deshalb liegt hier alles, was
 * tatsaechlich etwas anlegt; die Entry Points machen nur noch Eingabe und
 * Ausgabe.
 *
 * Autark: laedt NICHT bootstrap.php, bringt eine eigene PDO-Verbindung mit.
 */
declare(strict_types=1);

if (!defined('FORUM_INSTALLER')) { http_response_code(403); exit('Forbidden'); }

define('INSTALL_DIR', __DIR__);
define('FORUM_ROOT',  dirname(__DIR__));
define('CONFIG_FILE', FORUM_ROOT . '/config.php');
define('LOCK_FILE',   INSTALL_DIR . '/.installed');
define('SQL_FILE',    INSTALL_DIR . '/install.sql');
define('MIN_PHP',     '8.1.0');

/**
 * Ist hier schon ein Forum installiert?
 *
 * Zwei Merkmale, weil eins allein zu wenig ist: die Sperrdatei kann beim
 * Aufraeumen mitgehen, und eine config.php kann von Hand angelegt worden sein.
 */
function install_already_done(): bool {
    if (file_exists(LOCK_FILE)) return true;
    if (file_exists(CONFIG_FILE)) {
        $cfg = @file_get_contents(CONFIG_FILE);
        if ($cfg !== false && preg_match('/FORUM_INSTALLED\s*,\s*true/i', $cfg)) return true;
    }
    return false;
}

/** Liste [Bezeichnung, erfuellt?, Detail]. Der letzte Eintrag ist weich. */
function check_requirements(): array {
    $r = [];
    $r[] = ['PHP ≥ ' . MIN_PHP, version_compare(PHP_VERSION, MIN_PHP, '>='), 'Aktuell: ' . PHP_VERSION];
    foreach (['pdo', 'pdo_mysql', 'mbstring', 'json', 'openssl'] as $ext) {
        $r[] = ['Erweiterung ' . $ext, extension_loaded($ext), ''];
    }
    $r[] = ['install.sql vorhanden', is_readable(SQL_FILE), SQL_FILE];
    $r[] = ['Forum-Root beschreibbar (für config.php)', is_writable(FORUM_ROOT), FORUM_ROOT];
    // uploads/ ist optional vorhanden – nur warnen, nicht blockieren
    $up = FORUM_ROOT . '/uploads';
    $r[] = ['uploads/ beschreibbar', !is_dir($up) || is_writable($up),
            is_dir($up) ? $up : '(wird ggf. später angelegt)'];
    return $r;
}

function pdo_connect(array $c): PDO {
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $c['host'], $c['name'], $c['charset']);
    return new PDO($dsn, $c['user'], $c['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
}

/** Zerlegt eine SQL-Datei in einzelne Statements – string-aware (respektiert
 *  ' und " sowie Backslash-Escapes), damit Semikolons in Werten nicht trennen. */
function split_sql(string $sql): array {
    $out = []; $buf = ''; $inStr = false; $strCh = ''; $esc = false;
    $len = strlen($sql);
    for ($i = 0; $i < $len; $i++) {
        $ch = $sql[$i];
        $buf .= $ch;
        if ($inStr) {
            if ($esc)              { $esc = false; }
            elseif ($ch === '\\')  { $esc = true; }
            elseif ($ch === $strCh){ $inStr = false; }
            continue;
        }
        if ($ch === "'" || $ch === '"') { $inStr = true; $strCh = $ch; }
        elseif ($ch === ';') {
            $out[] = $buf; $buf = '';
        }
    }
    if (trim($buf) !== '') { $out[] = $buf; }

    // Kommentarzeilen entfernen, Leeres verwerfen
    $clean = [];
    foreach ($out as $stmt) {
        $lines = array_filter(
            explode("\n", $stmt),
            static fn($l) => strncmp(ltrim($l), '--', 2) !== 0
        );
        $s = trim(rtrim(trim(implode("\n", $lines)), ';'));
        if ($s !== '') { $clean[] = $s; }
    }
    return $clean;
}

function run_schema(PDO $pdo): int {
    $sql = file_get_contents(SQL_FILE);
    if ($sql === false) {
        throw new RuntimeException('install.sql konnte nicht gelesen werden.');
    }
    $count = 0;
    foreach (split_sql($sql) as $stmt) {
        $pdo->exec($stmt);
        $count++;
    }
    return $count;
}

function setting_set(PDO $pdo, string $key, string $value): void {
    // Die settings-Tabelle hat KEINE updated_at-Spalte (siehe install.sql).
    // Statement identisch zum Laufzeit-Code in lib/settings.php::set_setting().
    $pdo->prepare(
        'INSERT INTO settings (setting_key, setting_value)
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    )->execute([$key, $value]);
}

/** Legt den Inhaber an (user_id = 1). $ip nur, wenn es eine gibt – auf der
 *  Kommandozeile gibt es keine. */
function create_owner(PDO $pdo, string $user, string $email, string $pass, ?string $ip = null): void {
    $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);
    $pdo->prepare(
        'INSERT INTO users
            (user_id, username, password, email, is_admin, is_owner, is_mod,
             email_verified, `rank`, registered_at, registration_ip, last_activity_at)
         VALUES (1, ?, ?, ?, 1, 1, 0, 1, ?, NOW(), ?, NOW())'
    )->execute([$user, $hash, $email, 'Inhaber', $ip]);
}

/**
 * Prueft die Angaben zum Inhaber-Konto. Gibt die Fehlerliste zurueck (leer = ok).
 * Dieselben Regeln wie in pages/register.php.
 */
function validate_owner(string $user, string $email, string $pass): array {
    $errors = [];
    if (mb_strlen($user) < 3 || mb_strlen($user) > 30) {
        $errors[] = 'Benutzername: 3 bis 30 Zeichen.';
    } elseif (!preg_match('/^[\p{L}\p{N} ._-]+$/u', $user)) {
        $errors[] = 'Benutzername darf nur Buchstaben, Ziffern, Leerzeichen, '
                  . 'Punkt, Unterstrich und Bindestrich enthalten.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Bitte eine gültige E-Mail-Adresse angeben.';
    }
    if (mb_strlen($pass) < 8) {
        $errors[] = 'Passwort: mindestens 8 Zeichen.';
    }
    return $errors;
}

/**
 * Schreibt config.php.
 *
 * $c['extra'] nimmt zusaetzliche Konstanten auf (Name => Wert). Gebraucht wird
 * das beim Bereitstellen gehosteter Foren: dort steht der Mail-Absender fest
 * in der config und nicht im Adminbereich, weil der Versand ueber fremde
 * Reputation laeuft.
 */
function write_config(array $c): bool {
    $v = static fn($s) => var_export($s, true); // sicheres String-Quoting
    $tpl = <<<PHP
<?php
// config.php – automatisch erzeugt durch den Installer am {DATE}

define('DB_HOST',    {HOST});
define('DB_NAME',    {NAME});
define('DB_USER',    {USER});
define('DB_PASS',    {PASS});
define('DB_CHARSET', {CHARSET});

define('FORUM_NAME',    {FNAME});
define('FORUM_URL',     {FURL});
define('FORUM_INSTALLED', true);

// Schluessel fuer Werte, die verschluesselt in der Datenbank liegen — derzeit
// das SMTP-Passwort. Er steht hier und nicht in der Datenbank, sonst laege er
// neben dem, was er schuetzen soll.
//
// NICHT AENDERN und nicht verlieren: danach laesst sich das Gespeicherte nicht
// mehr lesen. Beim Umzug auf einen anderen Server muss diese Zeile mit.
define('FORUM_CRYPT_KEY', {CRYPTKEY});
{EXTRA}
define('UPLOAD_DIR',           __DIR__ . '/uploads/');
define('AVATAR_DIR',           __DIR__ . '/uploads/avatars/');
define('MAX_AVATAR_SIZE',      512 * 1024);
define('ALLOWED_AVATAR_TYPES', ['image/jpeg','image/png','image/gif','image/webp']);

define('SESSION_LIFETIME', 60 * 60 * 24 * 14);
define('POSTS_PER_PAGE',   20);
define('THREADS_PER_PAGE', 25);
define('HASH_COST',        12);
PHP;

    $extra = '';
    foreach (($c['extra'] ?? []) as $name => $wert) {
        // Nur echte Konstantennamen – sonst schreibt ein Tippfehler in der
        // Bereitstellung eine config.php, die PHP nicht mehr parst.
        if (!preg_match('/^[A-Z][A-Z0-9_]*$/', (string) $name)) {
            throw new InvalidArgumentException('Ungültiger Konstantenname: ' . $name);
        }
        $extra .= sprintf("define(%-22s %s);\n", "'$name',", $v($wert));
    }
    if ($extra !== '') { $extra = "\n" . $extra; }

    $out = strtr($tpl, [
        '{DATE}'    => date('Y-m-d H:i'),
        '{HOST}'    => $v($c['host']),
        '{NAME}'    => $v($c['name']),
        '{USER}'    => $v($c['user']),
        '{PASS}'    => $v($c['pass']),
        '{CHARSET}' => $v($c['charset']),
        '{FNAME}'   => $v($c['forum_name']),
        '{FURL}'    => $v($c['forum_url']),
        '{CRYPTKEY}'=> $v(bin2hex(random_bytes(32))),
        '{EXTRA}'   => $extra,
    ]);
    if (file_put_contents(CONFIG_FILE, $out) === false) {
        return false;
    }
    // enthält die DB-Zugangsdaten im Klartext, nicht für andere lesbar
    @chmod(CONFIG_FILE, 0640);
    return true;
}
