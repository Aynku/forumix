<?php
/**
 * Forumix – Web-Installer
 * --------------------------------------------------------------------------
 * Die eigentliche Arbeit steht in install/lib.php — dort, wo cli.php sie
 * ebenfalls findet. Hier ist nur der Weg durch den Browser:
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

define('FORUM_INSTALLER', 'web');
require __DIR__ . '/lib.php';

/*
 * Sprache des Installers.
 *
 * Hier gibt es noch kein Konto und keine Datenbank — die Wahl steht deshalb
 * in der Adresse (?lang=en) und wird im Cookie gemerkt, damit sie ueber die
 * fuenf Schritte haelt. current_language() kommt damit zurecht: Es holt das
 * Konto nur, wenn es eines gibt.
 *
 * FORUM_RUNNING braucht lib/lang.php als Riegel gegen direkten Aufruf; der
 * Installer laeuft, also ist er hier gesetzt.
 */
if (!defined('FORUM_RUNNING')) define('FORUM_RUNNING', true);

if (isset($_GET['lang'])) {
    $gewaehlt = preg_replace('/[^a-z]/', '', (string) $_GET['lang']);
    if (in_array($gewaehlt, ['de', 'en'], true)) {
        setcookie('forum_lang', $gewaehlt, ['expires' => 0, 'path' => '/', 'samesite' => 'Lax']);
        $_COOKIE['forum_lang'] = $gewaehlt;
    }
}


/* ── Self-Destruct: bereits installiert? ──────────────────────────────── */
if (install_already_done()) {
    http_response_code(403);
    render_page(t('inst_bereits_installiert'), '
        <div class="card">
            <h2>' . t('inst_already_done') . '</h2>
            <p>Dieser Installer wurde gesperrt, weil das Forum schon eingerichtet ist.</p>
            <p class="hint">Aus Sicherheitsgründen sollte im Ordner <code>install/</code>
            jetzt alles gelöscht werden — <strong>außer</strong> dem Unterordner
            <code>install/migrations/</code>, den das Forum für spätere Updates braucht.</p>
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
 *  POST-Handling pro Schritt
 * ════════════════════════════════════════════════════════════════════════ */

// Schritt 2: DB-Verbindung testen
if ($step === 2 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        $errors[] = t('inst_sicherheits_token_ungueltig_bitte');
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
            $errors[] = t('inst_host_datenbankname_und_benutzer');
        }
        if ($c['forum_name'] === '') { $errors[] = t('inst_bitte_einen_forum_namen'); }
        if (!filter_var($c['forum_url'], FILTER_VALIDATE_URL)) {
            $errors[] = t('inst_bitte_eine_gueltige_forum');
        }
        if (!$errors) {
            try {
                pdo_connect($c); // wirft bei Fehler
                $_SESSION['install_db'] = $c;
                header('Location: ?step=3');
                exit;
            } catch (Throwable $e) {
                $errors[] = t('inst_verbindung_fehlgeschlagen') . $e->getMessage();
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
            $errors[] = t('inst_sicherheits_token_ungueltig');
        } else {
            try {
                $pdo = pdo_connect($_SESSION['install_db']);
                $schemaStmts = run_schema($pdo);
                // Vorgabe: keine Beispiel-Foren. Etwas, das man aktiv
                // wegklicken muss, ist aufgezwungen — wer sie will, hakt an.
                if (empty($_POST['demo'])) { drop_demo_content($pdo); }
                // install.sql ist der Stand von 1.0; alles danach kommt aus
                // install/migrations/ — derselbe Weg, den ein Update geht.
                $_SESSION['install_migrations'] = count(run_migrations($pdo));
                $_SESSION['install_schema_done'] = true;
                header('Location: ?step=4');
                exit;
            } catch (Throwable $e) {
                $errors[] = t('inst_schema_import_fehlgeschlagen') . $e->getMessage();
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
            $errors[] = t('inst_sicherheits_token_ungueltig');
        } else {
            $user  = trim($_POST['owner_user'] ?? '');
            $email = trim($_POST['owner_email'] ?? '');
            $pass  = (string) ($_POST['owner_pass'] ?? '');
            $pass2 = (string) ($_POST['owner_pass2'] ?? '');

            $errors = array_merge($errors, validate_owner($user, $email, $pass));
            if ($pass !== $pass2) {
                $errors[] = t('inst_die_beiden_passwoerter_stimmen');
            }
            if (!$errors) {
                try {
                    $c   = $_SESSION['install_db'];
                    $pdo = pdo_connect($c);

                    // Owner bereits vorhanden? (z.B. Re-Run nach Teilabbruch)
                    $exists = $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
                    if ((int) $exists > 0) {
                        $errors[] = t('inst_es_existiert_bereits_ein')
                                  . 'users-Tabelle leeren oder den Schema-Schritt erneut ausführen.';
                    } else {
                        create_owner($pdo, $user, $email, $pass,
                                     $_SERVER['REMOTE_ADDR'] ?? null);
                        // CAPTCHA-HMAC-Secret erzeugen (gleiches Lazy-Pattern wie unsubscribe_secret)
                        setting_set($pdo, 'forum_secret', bin2hex(random_bytes(32)));
                        setting_set($pdo, 'forum_name',   $c['forum_name']);
                        setting_set($pdo, 'forum_url',    $c['forum_url']);

                        if (!write_config($c)) {
                            $errors[] = 'config.php konnte nicht geschrieben werden. '
                                      . t('inst_schreibrechte_im_forum_root');
                        } elseif (file_put_contents(LOCK_FILE, "installed " . date('c') . "\n") === false) {
                            $errors[] = t('inst_installation_abgeschlossen_aber_die')
                                      . 'install/.installed konnte nicht geschrieben werden. '
                                      . t('inst_bitte_den_ordner_install');
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
                    $errors[] = t('inst_owner_anlage_fehlgeschlagen') . $e->getMessage();
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
    $h = '<div class="errors"><strong>' . t('inst_please_fix') . '</strong><ul>';
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
<title>{$title} – Forumix Installer</title>
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
  /* Beschriftungen stehen sonst ueber dem Feld. Ein Haekchen gehoert daneben. */
  label.check{display:flex;align-items:center;gap:9px;margin:18px 0 0;
              font-size:14px;color:var(--fg);cursor:pointer}
  label.check input{width:16px;height:16px;margin:0;accent-color:var(--acc);cursor:pointer}
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
<h1>Forumix</h1><p class="sub">Installation</p>
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
    echo '<div class="card"><h2>' . t('inst_requirements') . '</h2><ul class="reqs">';
    foreach ($reqs as [$label, $ok, $det]) {
        $icon = $ok ? '<span class="pass">✓ OK</span>' : '<span class="fail">✗ fehlt</span>';
        $d = $det ? '<span class="det">' . htmlspecialchars($det) . '</span>' : '';
        echo "<li><span>{$label}</span>{$d}{$icon}</li>";
    }
    echo '</ul>';
    if ($blocked) {
        echo '<div class="warn">' . t('inst_requirements_missing') . '</div>';
    } else {
        echo '<a class="btn" href="?step=2">' . t('inst_next_db') . '</a>';
    }
    echo '</div>';
}

if ($step === 2) {
    $f = $_SESSION['install_db_form'] ?? $_SESSION['install_db'] ?? [];
    $val = static fn($k, $d = '') => htmlspecialchars((string) ($f[$k] ?? $d));
    echo err_box($errors);
    echo '<div class="card"><h2>' . t('inst_db_connection') . '</h2>
    <form method="post" action="?step=2">' . csrf_field() . '
      <div class="row">
        <div><label>' . t('inst_host') . '</label><input type="text" name="db_host" value="' . $val('host', 'localhost') . '" required></div>
        <div><label>' . t('inst_charset') . '</label><input type="text" name="db_charset" value="' . $val('charset', 'utf8mb4') . '"></div>
      </div>
      <label>' . t('inst_db_name') . '</label><input type="text" name="db_name" value="' . $val('name') . '" required>
      <div class="row">
        <div><label>' . t('inst_db_user') . '</label><input type="text" name="db_user" value="' . $val('user') . '" required></div>
        <div><label>' . t('inst_password') . '</label><input type="password" name="db_pass" value=""></div>
      </div>
      <hr style="border:0;border-top:1px solid var(--bd);margin:22px 0">
      <label>' . t('inst_forum_name') . '</label><input type="text" name="forum_name" value="' . $val('forum_name', t('inst_mein_forum')) . '" required>
      <label>' . t('inst_forum_url') . '</label><input type="url" name="forum_url" value="' . $val('forum_url', ($_SERVER['REQUEST_SCHEME'] ?? 'https') . '://' . ($_SERVER['HTTP_HOST'] ?? '')) . '" required>
      <button type="submit">' . t('inst_test_connection') . '</button>
    </form></div>';
}

if ($step === 3) {
    echo err_box($errors);
    echo '<div class="card"><h2>' . t('inst_schema') . '</h2>
    <div class="warn">⚠ <strong>' . t('inst_warning') . '</strong> Dieser Schritt führt
      <code>DROP TABLE IF EXISTS</code> für alle Forum-Tabellen aus.
      Eine eventuell vorhandene Forum-Datenbank wird dabei <strong>überschrieben</strong>.
      Nur fortfahren, wenn die Datenbank leer ist oder neu aufgesetzt werden soll.</div>
    <p class="hint">Es werden alle Tabellen, Indizes und Fremdschlüssel angelegt sowie
      die nicht-personenbezogenen Grunddaten (Tags, Smileys, Achievements,
      Seiten, FAQ, Einstellungen) eingespielt.</p>
    <form method="post" action="?step=3">' . csrf_field() . '
      <label class="check">
        <input type="checkbox" name="demo" value="1">
        Vier Beispiel-Foren anlegen
      </label>
      <p class="hint" style="margin-top:6px">
        <em>Ankündigungen</em>, <em>Off-Topic</em>, <em>Hilfe &amp; Fragen</em> und
        <em>Fehlerberichte</em> in zwei Kategorien — zum Ausprobieren.
        Wer eigene Foren anlegen oder ein bestehendes Forum übernehmen will,
        lässt das Häkchen weg und fängt mit einer leeren Übersicht an.
      </p>
      <button type="submit">' . t('inst_create_schema') . '</button>
    </form></div>';
}

if ($step === 4) {
    echo err_box($errors);
    $f = static fn($k) => htmlspecialchars((string) ($_POST[$k] ?? ''));
    echo '<div class="card"><h2>' . t('inst_owner') . '</h2>
    <p class="hint">Dieses Konto erhält volle Rechte (Inhaber). Es wird als
      <code>user_id = 1</code> angelegt.</p>
    <form method="post" action="?step=4">' . csrf_field() . '
      <label>' . t('inst_username') . '</label><input type="text" name="owner_user" value="' . $f('owner_user') . '" required>
      <label>' . t('inst_email') . '</label><input type="email" name="owner_email" value="' . $f('owner_email') . '" required>
      <div class="row">
        <div><label>' . t('inst_password_min') . '</label><input type="password" name="owner_pass" required></div>
        <div><label>' . t('inst_password_repeat') . '</label><input type="password" name="owner_pass2" required></div>
      </div>
      <button type="submit">' . t('inst_create_owner') . '</button>
    </form></div>';
}

if ($step === 5) {
    $anz = (int) ($_SESSION['install_migrations'] ?? 0);
    echo '<div class="card"><h2 class="ok-big">✓ Installation abgeschlossen</h2>
    <p>Das Forum ist eingerichtet. <code>config.php</code> wurde geschrieben und der
       Installer ist ab jetzt gesperrt.'
       . ($anz > 0 ? t('inst_dabei_wurden') . $anz . ' Datenbank-Anpassungen mitgefahren —
       im Adminbereich unter <em>Forum-Updates</em> steht die Liste.' : '')
       . '</p>
    <div class="warn">🔒 <strong>' . t('inst_last_step') . '</strong> Bitte im Ordner
       <code>install/</code> die Dateien <code>index.php</code>, <code>cli.php</code>,
       <code>lib.php</code> und <code>install.sql</code> löschen.<br>
       Den Unterordner <code>install/migrations/</code> <strong>stehen lassen</strong> —
       daraus holt sich der Adminbereich die Datenbank-Anpassungen späterer Updates.</div>
    <a class="btn" href="' . htmlspecialchars(rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '', 2), '/') . '/') . '">' . t('inst_to_forum') . '</a>
    </div>';
}

render_page(['1'=>'Voraussetzungen','2'=>'Datenbank','3'=>'Schema','4'=>'Owner','5'=>'Fertig'][$step] ?? 'Installer',
            ob_get_clean(), $step);
