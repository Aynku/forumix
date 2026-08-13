<?php
/**
 * Aynku Forum – Web-Installer
 * --------------------------------------------------------------------------
 * Autark: lädt NICHT bootstrap.php, nutzt eine eigene PDO-Verbindung.
 * Setzt bei Aufruf von /install/ ein frisches Forum auf:
 *   1. Voraussetzungs-Check   (PHP, Erweiterungen, Schreibrechte, install.sql)
 *   2. DB-Verbindung          (Eingabe + Live-Test, Daten in Session)
 *   3. Schema anlegen         (install.sql ausführen)
 *   4. Owner anlegen          (user_id=1, is_owner=1) + forum_secret/-name/-url
 *   5. Fertig                 (config.php + Lockfile .installed schreiben)
 *
 * config.php wird ERST in Schritt 5 geschrieben (nach erfolgreichem Owner-
 * Anlegen), damit ein Abbruch nie eine "installiert"-markierte config zurück-
 * lässt. Nach Abschluss blockt das Lockfile jeden weiteren Aufruf (403).
 */
declare(strict_types=1);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
session_start();

define('INSTALL_DIR', __DIR__);
define('FORUM_ROOT',  dirname(__DIR__));
define('CONFIG_FILE', FORUM_ROOT . '/config.php');
define('LOCK_FILE',   INSTALL_DIR . '/.installed');
define('SQL_FILE',    INSTALL_DIR . '/install.sql');
define('MIN_PHP',     '8.0.0');

/* ── Self-Destruct: bereits installiert? ──────────────────────────────── */
$alreadyInstalled = file_exists(LOCK_FILE);
if (!$alreadyInstalled && file_exists(CONFIG_FILE)) {
    // Zusatz-Absicherung: config mit FORUM_INSTALLED=true => als installiert behandeln
    $cfg = @file_get_contents(CONFIG_FILE);
    if ($cfg !== false && preg_match('/FORUM_INSTALLED\s*,\s*true/i', $cfg)) {
        $alreadyInstalled = true;
    }
}
if ($alreadyInstalled) {
    http_response_code(403);
    render_page('Bereits installiert', '
        <div class="card">
            <h2>Forum ist bereits installiert</h2>
            <p>Dieser Installer wurde gesperrt, weil das Forum schon eingerichtet ist.</p>
            <p class="hint">Aus Sicherheitsgründen sollte der Ordner <code>install/</code>
            jetzt vom Server gelöscht werden.</p>
        </div>');
    exit;
}

/* ── Freigabe: nur wer Dateizugriff hat, darf installieren ─────────────
 *
 * Zwischen dem Hochladen der Dateien und dem Aufruf des Installers steht
 * ein unfertiges Forum offen im Netz. Wer in diesem Fenster als Erster
 * /install/ aufruft, richtet es ein: Datenbank-Host ist ein freies Feld,
 * er kann also seinen eigenen Server eintragen, legt das Owner-Konto mit
 * seinem Passwort an und schreibt die config.php. Danach gehört die
 * Installation ihm, und der rechtmässige Betreiber steht vor einem
 * gesperrten Installer.
 *
 * Die Freigabe verlangt deshalb einen Nachweis, den nur jemand mit
 * Dateizugriff erbringen kann: eine leere Datei neben dieser hier. Kein
 * Geheimnis, das ausgelesen werden könnte — es zählt allein, dass sie
 * angelegt wurde. Nach der Installation kann sie mit install/ verschwinden.
 */
$unlockCandidates = [INSTALL_DIR . '/.unlock', INSTALL_DIR . '/UNLOCK.txt'];
$unlocked = false;
foreach ($unlockCandidates as $u) { if (file_exists($u)) { $unlocked = true; break; } }
if (!$unlocked) {
    http_response_code(403);
    render_page('Installer gesperrt', '
        <div class="card">
            <h2>Installer ist gesperrt</h2>
            <p>Damit niemand Fremdes das frisch hochgeladene Forum einrichtet,
               muss die Freigabe über das Dateisystem erfolgen.</p>
            <p>Lege per FTP oder SSH eine <strong>leere Datei</strong> an:</p>
            <p><code>install/.unlock</code></p>
            <p class="hint">Versteckt der FTP-Client Dateien mit führendem Punkt,
               geht auch <code>install/UNLOCK.txt</code>. Der Inhalt ist egal.
               Danach diese Seite neu laden.</p>
            <p class="hint">Per SSH: <code>touch install/.unlock</code></p>
        </div>');
    exit;
}

/* ── CSRF ──────────────────────────────────────────────────────────────── */
if (empty($_SESSION['install_csrf'])) {
    $_SESSION['install_csrf'] = bin2hex(random_bytes(16));
}
function csrf_field(): string {
    return '<input type="hidden" name="csrf" value="' . htmlspecialchars($_SESSION['install_csrf']) . '">';
}
function csrf_ok(): bool {
    return isset($_POST['csrf'], $_SESSION['install_csrf'])
        && hash_equals($_SESSION['install_csrf'], (string) $_POST['csrf']);
}

$step   = (int) ($_GET['step'] ?? 1);
$errors = [];

/* ════════════════════════════════════════════════════════════════════════
 *  Hilfsfunktionen
 * ════════════════════════════════════════════════════════════════════════ */

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

function create_owner(PDO $pdo, string $user, string $email, string $pass): void {
    $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);
    $ip   = $_SERVER['REMOTE_ADDR'] ?? null;
    $pdo->prepare(
        'INSERT INTO users
            (user_id, username, password, email, is_admin, is_owner, is_mod,
             email_verified, `rank`, registered_at, registration_ip, last_activity_at)
         VALUES (1, ?, ?, ?, 1, 1, 0, 1, ?, NOW(), ?, NOW())'
    )->execute([$user, $hash, $email, 'Inhaber', $ip]);
}

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

define('UPLOAD_DIR',           __DIR__ . '/uploads/');
define('AVATAR_DIR',           __DIR__ . '/uploads/avatars/');
define('MAX_AVATAR_SIZE',      512 * 1024);
define('ALLOWED_AVATAR_TYPES', ['image/jpeg','image/png','image/gif','image/webp']);

define('SESSION_LIFETIME', 60 * 60 * 24 * 14);
define('POSTS_PER_PAGE',   20);
define('THREADS_PER_PAGE', 25);
define('HASH_COST',        12);
PHP;
    $out = strtr($tpl, [
        '{DATE}'    => date('Y-m-d H:i'),
        '{HOST}'    => $v($c['host']),
        '{NAME}'    => $v($c['name']),
        '{USER}'    => $v($c['user']),
        '{PASS}'    => $v($c['pass']),
        '{CHARSET}' => $v($c['charset']),
        '{FNAME}'   => $v($c['forum_name']),
        '{FURL}'    => $v($c['forum_url']),
    ]);
    if (file_put_contents(CONFIG_FILE, $out) === false) {
        return false;
    }
    // Die Datei enthält die Datenbank-Zugangsdaten im Klartext. Ohne diesen
    // Schritt bekommt sie die Standard-Rechte (meist 0644) und ist damit für
    // jeden anderen Systembenutzer lesbar — auf geteiltem Hosting also für
    // fremde Kunden auf derselben Maschine.
    @chmod(CONFIG_FILE, 0640);
    return true;
}

/* ════════════════════════════════════════════════════════════════════════
 *  POST-Handling pro Schritt
 * ════════════════════════════════════════════════════════════════════════ */

// Schritt 2: DB-Verbindung testen
if ($step === 2 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        $errors[] = 'Sicherheits-Token ungültig. Bitte Formular erneut absenden.';
    } else {
        $c = [
            'host'       => trim($_POST['db_host'] ?? ''),
            'name'       => trim($_POST['db_name'] ?? ''),
            'user'       => trim($_POST['db_user'] ?? ''),
            'pass'       => (string) ($_POST['db_pass'] ?? ''),
            'charset'    => trim($_POST['db_charset'] ?? '') ?: 'utf8mb4',
            'forum_name' => trim($_POST['forum_name'] ?? ''),
            'forum_url'  => rtrim(trim($_POST['forum_url'] ?? ''), '/'),
        ];
        if ($c['host'] === '' || $c['name'] === '' || $c['user'] === '') {
            $errors[] = 'Host, Datenbankname und Benutzer sind Pflichtfelder.';
        }
        if ($c['forum_name'] === '') { $errors[] = 'Bitte einen Forum-Namen angeben.'; }
        if (!filter_var($c['forum_url'], FILTER_VALIDATE_URL)) {
            $errors[] = 'Bitte eine gültige Forum-URL angeben (inkl. https://).';
        }
        if (!$errors) {
            try {
                pdo_connect($c); // wirft bei Fehler
                $_SESSION['install_db'] = $c;
                header('Location: ?step=3');
                exit;
            } catch (Throwable $e) {
                $errors[] = 'Verbindung fehlgeschlagen: ' . $e->getMessage();
            }
        }
        $_SESSION['install_db_form'] = $c; // Eingaben für Re-Render merken
    }
}

// Schritt 3: Schema importieren
$schemaStmts = null;
if ($step === 3) {
    if (empty($_SESSION['install_db'])) { header('Location: ?step=2'); exit; }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!csrf_ok()) {
            $errors[] = 'Sicherheits-Token ungültig.';
        } else {
            try {
                $pdo = pdo_connect($_SESSION['install_db']);
                $schemaStmts = run_schema($pdo);
                $_SESSION['install_schema_done'] = true;
                header('Location: ?step=4');
                exit;
            } catch (Throwable $e) {
                $errors[] = 'Schema-Import fehlgeschlagen: ' . $e->getMessage();
            }
        }
    }
}

// Schritt 4: Owner anlegen + finalisieren (config + Lockfile)
if ($step === 4) {
    if (empty($_SESSION['install_db']) || empty($_SESSION['install_schema_done'])) {
        header('Location: ?step=2'); exit;
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!csrf_ok()) {
            $errors[] = 'Sicherheits-Token ungültig.';
        } else {
            $user  = trim($_POST['owner_user'] ?? '');
            $email = trim($_POST['owner_email'] ?? '');
            $pass  = (string) ($_POST['owner_pass'] ?? '');
            $pass2 = (string) ($_POST['owner_pass2'] ?? '');

            if (mb_strlen($user) < 3 || mb_strlen($user) > 30) {
                $errors[] = 'Benutzername: 3 bis 30 Zeichen.';
            } elseif (!preg_match('/^[\p{L}\p{N} ._-]+$/u', $user)) {
                // Gleiche Regel wie bei der Registrierung (pages/register.php):
                // Namen landen auch in HTML, das erst im Browser gebaut wird,
                // und dort genügt ein Anführungszeichen zum Ausbrechen.
                $errors[] = 'Benutzername darf nur Buchstaben, Ziffern, Leerzeichen, '
                          . 'Punkt, Unterstrich und Bindestrich enthalten.';
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Bitte eine gültige E-Mail-Adresse angeben.';
            }
            if (mb_strlen($pass) < 8) {
                $errors[] = 'Passwort: mindestens 8 Zeichen.';
            }
            if ($pass !== $pass2) {
                $errors[] = 'Die beiden Passwörter stimmen nicht überein.';
            }
            if (!$errors) {
                try {
                    $c   = $_SESSION['install_db'];
                    $pdo = pdo_connect($c);

                    // Owner bereits vorhanden? (z.B. Re-Run nach Teilabbruch)
                    $exists = $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
                    if ((int) $exists > 0) {
                        $errors[] = 'Es existiert bereits ein Benutzer. Bitte die '
                                  . 'users-Tabelle leeren oder den Schema-Schritt erneut ausführen.';
                    } else {
                        create_owner($pdo, $user, $email, $pass);
                        // CAPTCHA-HMAC-Secret erzeugen (gleiches Lazy-Pattern wie unsubscribe_secret)
                        setting_set($pdo, 'forum_secret', bin2hex(random_bytes(32)));
                        setting_set($pdo, 'forum_name',   $c['forum_name']);
                        setting_set($pdo, 'forum_url',    $c['forum_url']);

                        if (!write_config($c)) {
                            $errors[] = 'config.php konnte nicht geschrieben werden. '
                                      . 'Schreibrechte im Forum-Root prüfen.';
                        } elseif (file_put_contents(LOCK_FILE, "installed " . date('c') . "\n") === false) {
                            // Nicht verschlucken: ohne Lockfile bliebe der
                            // Installer offen. Die config.php mit
                            // FORUM_INSTALLED=true fängt es zwar ab, aber der
                            // Betreiber soll es erfahren statt es zu glauben.
                            $errors[] = 'Installation abgeschlossen, aber die Sperrdatei '
                                      . 'install/.installed konnte nicht geschrieben werden. '
                                      . 'Bitte den Ordner install/ jetzt von Hand löschen.';
                        } else {
                            // Session-Reste aufräumen
                            unset($_SESSION['install_db'], $_SESSION['install_db_form'],
                                  $_SESSION['install_schema_done']);
                            // Fertig-Seite im SELBEN Request rendern: ein Redirect zu
                            // ?step=5 würde nun vom Lockfile-Self-Destruct abgefangen.
                            $step = 5;
                        }
                    }
                } catch (Throwable $e) {
                    $errors[] = 'Owner-Anlage fehlgeschlagen: ' . $e->getMessage();
                }
            }
        }
    }
}

/* ════════════════════════════════════════════════════════════════════════
 *  Darstellung
 * ════════════════════════════════════════════════════════════════════════ */

function steps_nav(int $cur): string {
    $labels = [1 => 'Check', 2 => 'Datenbank', 3 => 'Schema', 4 => 'Owner', 5 => 'Fertig'];
    $h = '<ol class="steps">';
    foreach ($labels as $n => $l) {
        $cls = $n < $cur ? 'done' : ($n === $cur ? 'active' : '');
        $h .= "<li class=\"$cls\"><span>$n</span>$l</li>";
    }
    return $h . '</ol>';
}

function err_box(array $errors): string {
    if (!$errors) return '';
    $h = '<div class="errors"><strong>Bitte korrigieren:</strong><ul>';
    foreach ($errors as $e) { $h .= '<li>' . htmlspecialchars($e) . '</li>'; }
    return $h . '</ul></div>';
}

function render_page(string $title, string $body, int $cur = 0): void {
    $nav = $cur ? steps_nav($cur) : '';
    echo <<<HTML
<!doctype html>
<html lang="de"><head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>{$title} – Aynku Forum Installer</title>
<style>
  :root{--bg:#15171a;--card:#1d2024;--bd:#2c3036;--fg:#e6e8ea;--mut:#9aa0a6;
        --acc:#7aa2f7;--okc:#5bbf63;--err:#e5534b;--errbg:#2a1d1d}
  *{box-sizing:border-box}
  body{margin:0;background:var(--bg);color:var(--fg);
       font:15px/1.55 system-ui,-apple-system,Segoe UI,Roboto,sans-serif}
  .wrap{max-width:640px;margin:40px auto;padding:0 18px}
  h1{font-size:22px;font-weight:600;margin:0 0 4px}
  .sub{color:var(--mut);margin:0 0 24px;font-size:14px}
  .card{background:var(--card);border:1px solid var(--bd);border-radius:12px;
        padding:24px;margin-bottom:18px}
  h2{font-size:17px;margin:0 0 14px}
  .steps{list-style:none;display:flex;gap:6px;padding:0;margin:0 0 22px;font-size:12px}
  .steps li{flex:1;text-align:center;color:var(--mut);border-top:2px solid var(--bd);padding-top:8px}
  .steps li span{display:block;width:24px;height:24px;line-height:24px;border-radius:50%;
        margin:0 auto 4px;background:var(--bd);color:var(--mut)}
  .steps li.active{color:var(--fg)} .steps li.active span{background:var(--acc);color:#0b0d10}
  .steps li.active{border-top-color:var(--acc)}
  .steps li.done{color:var(--okc)} .steps li.done span{background:var(--okc);color:#0b0d10}
  .steps li.done{border-top-color:var(--okc)}
  label{display:block;margin:14px 0 5px;font-size:13px;color:var(--mut)}
  input[type=text],input[type=password],input[type=email],input[type=url]{
        width:100%;padding:10px 12px;background:#0f1114;border:1px solid var(--bd);
        border-radius:8px;color:var(--fg);font-size:14px}
  input:focus{outline:none;border-color:var(--acc)}
  .row{display:flex;gap:12px} .row>div{flex:1}
  button,.btn{display:inline-block;margin-top:20px;padding:11px 20px;border:0;border-radius:8px;
        background:var(--acc);color:#0b0d10;font-size:14px;font-weight:600;cursor:pointer;
        text-decoration:none}
  button:hover,.btn:hover{filter:brightness(1.08)}
  .reqs{list-style:none;padding:0;margin:0}
  .reqs li{display:flex;justify-content:space-between;align-items:center;
        padding:9px 0;border-bottom:1px solid var(--bd)}
  .reqs li:last-child{border-bottom:0}
  .reqs .det{color:var(--mut);font-size:12px;margin-left:auto;padding-right:12px}
  .pass{color:var(--okc)} .fail{color:var(--err)}
  .errors{background:var(--errbg);border:1px solid var(--err);border-radius:8px;
        padding:12px 14px;margin-bottom:16px;font-size:13px}
  .errors ul{margin:6px 0 0;padding-left:18px} .errors strong{color:var(--err)}
  .warn{background:#2a2410;border:1px solid #8a6d2f;border-radius:8px;padding:12px 14px;
        margin:14px 0;font-size:13px;color:#e8d39a}
  .hint{color:var(--mut);font-size:13px} code{background:#0f1114;padding:2px 6px;border-radius:5px}
  .ok-big{color:var(--okc);font-size:15px}
</style></head><body><div class="wrap">
<h1>Aynku Forum</h1><p class="sub">Installation</p>
{$nav}{$body}
</div></body></html>
HTML;
}

/* ── Schritt-Inhalte ─────────────────────────────────────────────────── */
ob_start();

if ($step === 1) {
    $reqs = check_requirements();
    $hard = array_slice($reqs, 0, count($reqs) - 1); // letzte (uploads) ist weich
    $blocked = false;
    foreach ($hard as $r) { if (!$r[1]) { $blocked = true; break; } }
    echo '<div class="card"><h2>Voraussetzungen</h2><ul class="reqs">';
    foreach ($reqs as [$label, $ok, $det]) {
        $icon = $ok ? '<span class="pass">✓ OK</span>' : '<span class="fail">✗ fehlt</span>';
        $d = $det ? '<span class="det">' . htmlspecialchars($det) . '</span>' : '';
        echo "<li><span>{$label}</span>{$d}{$icon}</li>";
    }
    echo '</ul>';
    if ($blocked) {
        echo '<div class="warn">Es fehlen Voraussetzungen. Bitte beheben und neu laden.</div>';
    } else {
        echo '<a class="btn" href="?step=2">Weiter zur Datenbank →</a>';
    }
    echo '</div>';
}

if ($step === 2) {
    $f = $_SESSION['install_db_form'] ?? $_SESSION['install_db'] ?? [];
    $val = static fn($k, $d = '') => htmlspecialchars((string) ($f[$k] ?? $d));
    echo err_box($errors);
    echo '<div class="card"><h2>Datenbank-Verbindung</h2>
    <form method="post" action="?step=2">' . csrf_field() . '
      <div class="row">
        <div><label>Host</label><input type="text" name="db_host" value="' . $val('host', 'localhost') . '" required></div>
        <div><label>Zeichensatz</label><input type="text" name="db_charset" value="' . $val('charset', 'utf8mb4') . '"></div>
      </div>
      <label>Datenbank-Name</label><input type="text" name="db_name" value="' . $val('name') . '" required>
      <div class="row">
        <div><label>Benutzer</label><input type="text" name="db_user" value="' . $val('user') . '" required></div>
        <div><label>Passwort</label><input type="password" name="db_pass" value=""></div>
      </div>
      <hr style="border:0;border-top:1px solid var(--bd);margin:22px 0">
      <label>Forum-Name</label><input type="text" name="forum_name" value="' . $val('forum_name', 'Aynku Forum') . '" required>
      <label>Forum-URL</label><input type="url" name="forum_url" value="' . $val('forum_url', 'https://www.aynku.de') . '" required>
      <button type="submit">Verbindung testen →</button>
    </form></div>';
}

if ($step === 3) {
    echo err_box($errors);
    echo '<div class="card"><h2>Schema anlegen</h2>
    <div class="warn">⚠ <strong>Achtung:</strong> Dieser Schritt führt
      <code>DROP TABLE IF EXISTS</code> für alle Forum-Tabellen aus.
      Eine eventuell vorhandene Forum-Datenbank wird dabei <strong>überschrieben</strong>.
      Nur fortfahren, wenn die Datenbank leer ist oder neu aufgesetzt werden soll.</div>
    <p class="hint">Es werden alle Tabellen, Indizes und Fremdschlüssel angelegt sowie
      die nicht-personenbezogenen Grunddaten (Kategorien, Foren, Tags, Smileys,
      Achievements, Seiten, FAQ, Einstellungen) eingespielt.</p>
    <form method="post" action="?step=3">' . csrf_field() . '
      <button type="submit">Schema jetzt anlegen →</button>
    </form></div>';
}

if ($step === 4) {
    echo err_box($errors);
    $f = static fn($k) => htmlspecialchars((string) ($_POST[$k] ?? ''));
    echo '<div class="card"><h2>Owner-Konto anlegen</h2>
    <p class="hint">Dieses Konto erhält volle Rechte (Inhaber). Es wird als
      <code>user_id = 1</code> angelegt.</p>
    <form method="post" action="?step=4">' . csrf_field() . '
      <label>Benutzername</label><input type="text" name="owner_user" value="' . $f('owner_user') . '" required>
      <label>E-Mail</label><input type="email" name="owner_email" value="' . $f('owner_email') . '" required>
      <div class="row">
        <div><label>Passwort (min. 8 Zeichen)</label><input type="password" name="owner_pass" required></div>
        <div><label>Passwort wiederholen</label><input type="password" name="owner_pass2" required></div>
      </div>
      <button type="submit">Owner anlegen & abschließen →</button>
    </form></div>';
}

if ($step === 5) {
    echo '<div class="card"><h2 class="ok-big">✓ Installation abgeschlossen</h2>
    <p>Das Forum ist eingerichtet. <code>config.php</code> wurde geschrieben und der
       Installer ist ab jetzt gesperrt.</p>
    <div class="warn">🔒 <strong>Letzter Schritt:</strong> Bitte den Ordner
       <code>install/</code> vom Server löschen.</div>
    <a class="btn" href="' . htmlspecialchars(rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '', 2), '/') . '/') . '">Zum Forum →</a>
    </div>';
}

render_page(['1'=>'Voraussetzungen','2'=>'Datenbank','3'=>'Schema','4'=>'Owner','5'=>'Fertig'][$step] ?? 'Installer',
            ob_get_clean(), $step);
