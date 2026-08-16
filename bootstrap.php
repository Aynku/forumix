<?php
// bootstrap.php
// Direkter Aufruf verboten – wird nur via index.php / Router geladen
if (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'bootstrap.php') {
    http_response_code(403);
    die('Forbidden');
}

// Fehler ins Protokoll, nicht auf den Bildschirm. FORUM_DEBUG wird weiter
// unten ausgewertet – erst wenn die config.php geladen ist.
error_reporting(E_ALL);
ini_set('display_errors',         '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors',             '1');

$configFile = __DIR__ . '/config.php';
if (!file_exists($configFile)) {
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    if (str_ends_with($base, '/admin')) $base = dirname($base);
    header('Location: ' . $base . '/install/');
    exit;
}
require_once $configFile;

if (!defined('FORUM_INSTALLED') || FORUM_INSTALLED !== true) {
    header('Location: install/');
    exit;
}

// Zum Entwickeln: define('FORUM_DEBUG', true) in der config.php
if (defined('FORUM_DEBUG') && FORUM_DEBUG === true) {
    ini_set('display_errors',         '1');
    ini_set('display_startup_errors', '1');
}

// ── Content-Security-Policy ─────────────────────────────────────
//
// Ehrlich zum Nutzen: script-src traegt 'unsafe-inline', weil das Forum an
// vielen Stellen mit Inline-Skripten arbeitet. Gegen eingeschleustes Inline-JS
// hilft diese Regel also NICHT. Was sie tut: Skripte, Stile, Schriften und
// Verbindungen duerfen nur noch von der eigenen Domain kommen, <object> ist
// tot, <base> und Formularziele sind festgenagelt. Ein Angreifer, der Markup
// unterbringt, kann damit kein fremdes Skript nachladen und keine Daten
// woanders hinschicken.
//
// Bilder duerfen von ueberall per https kommen — der [img]-BBCode erlaubt das
// ausdruecklich. Wer das zumacht, bricht bestehende Beitraege.
//
// Notausgang: define('FORUM_CSP', false) in der config.php.
if (!defined('FORUM_CSP') || FORUM_CSP !== false) {
    header("Content-Security-Policy: "
        . "default-src 'self'; "
        . "base-uri 'self'; "
        . "object-src 'none'; "
        . "frame-ancestors 'self'; "
        . "form-action 'self'; "
        . "img-src 'self' data: https:; "
        . "font-src 'self' data:; "
        . "style-src 'self' 'unsafe-inline'; "
        . "script-src 'self' 'unsafe-inline'; "
        . "connect-src 'self'");
}

// ── Zeitzone festlegen ──────────────────────────────────────────
// Bevorzugt aus config.php (FORUM_TIMEZONE), dann Settings-Default, sonst Berlin
if (defined('FORUM_TIMEZONE')) {
    date_default_timezone_set(FORUM_TIMEZONE);
} else {
    date_default_timezone_set('Europe/Berlin');
}

require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/crypto.php';
require_once __DIR__ . '/lib/functions.php';
require_once __DIR__ . '/lib/smileys.php';
require_once __DIR__ . '/lib/settings.php';
require_once __DIR__ . '/lib/pages.php';
require_once __DIR__ . '/lib/templates.php';
require_once __DIR__ . '/lib/backup.php';
require_once __DIR__ . '/lib/captcha.php';
require_once __DIR__ . '/lib/ranks.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/remember.php';
require_once __DIR__ . '/lib/lang.php';
require_once __DIR__ . '/lib/notifications.php';
require_once __DIR__ . '/lib/attachments.php';
require_once __DIR__ . '/lib/polls.php';
require_once __DIR__ . '/lib/reactions.php';
require_once __DIR__ . '/lib/bookmarks.php';
require_once __DIR__ . '/lib/online.php';
require_once __DIR__ . '/lib/achievements.php';
require_once __DIR__ . '/lib/user_stats.php';
require_once __DIR__ . '/lib/reports.php';
require_once __DIR__ . '/lib/warnings.php';
require_once __DIR__ . '/lib/edits.php';
require_once __DIR__ . '/lib/tags.php';
require_once __DIR__ . '/lib/email.php';
require_once __DIR__ . '/lib/ratelimit.php';
require_once __DIR__ . '/lib/reads.php';
require_once __DIR__ . '/lib/password_reset.php';
require_once __DIR__ . '/lib/email_verify.php';
require_once __DIR__ . '/lib/twofactor.php';
require_once __DIR__ . '/lib/qrcode.php';
require_once __DIR__ . '/lib/board_mods.php';
require_once __DIR__ . '/lib/board_access.php';
require_once __DIR__ . '/lib/migrations.php';
require_once __DIR__ . '/lib/update_package.php';
require_once __DIR__ . '/lib/approval.php';
require_once __DIR__ . '/lib/invitations.php';
require_once __DIR__ . '/lib/profile_fields.php';
require_once __DIR__ . '/lib/dashboard.php';
require_once __DIR__ . '/lib/mod.php';
require_once __DIR__ . '/lib/modules.php';
require_once __DIR__ . '/lib/view.php';
require_once __DIR__ . '/lib/user_deletion.php';
require_once __DIR__ . '/lib/template.php';

// ── Unbehandelte Fehler landen im Forum, nicht im Nichts ────────
//
// Seit display_errors aus ist, endete ein Fatal in einer weissen Seite.
// Hier bekommt er eine Fehlerseite im Layout — und immer einen Eintrag im
// Protokoll. Bei FORUM_DEBUG bleibt alles wie es ist, sonst verdeckt die
// huebsche Seite genau das, was man beim Entwickeln sehen will.
if (!defined('FORUM_DEBUG') || FORUM_DEBUG !== true) {
    set_exception_handler(static function (Throwable $e): void {
        error_log('[uncaught] ' . get_class($e) . ': ' . $e->getMessage()
                . ' in ' . $e->getFile() . ':' . $e->getLine());
        if (headers_sent()) { echo '<p style="padding:20px">Es ist ein Fehler aufgetreten.</p>'; return; }
        error_page(500, 'Es ist ein Fehler aufgetreten',
            'Der Fehler wurde protokolliert. Bitte versuche es später erneut.');
    });

    register_shutdown_function(static function (): void {
        $e = error_get_last();
        if (!$e || !in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) return;
        error_log('[fatal] ' . $e['message'] . ' in ' . $e['file'] . ':' . $e['line']);
        if (headers_sent()) return;  // Seite ist schon halb raus, nichts mehr zu retten
        error_page(500, 'Es ist ein Fehler aufgetreten',
            'Der Fehler wurde protokolliert. Bitte versuche es später erneut.');
    });
}

// ────────────────────────────────────────────────────────────────
// Forum-Version
// ────────────────────────────────────────────────────────────────
//
// In Code: die aktuelle Version festgenagelt (wird mit jedem Update
// in dieser Konstante mit hochgezogen).
//
// In DB: das Setting `forum_version` zeigt was die DB glaubt.
//
// Wenn DB < Code → automatisches Hochsetzen (Self-Healing). So bleibt
// die Anzeige im Footer und im Cache-Busting immer aktuell, ohne dass
// manuell SQL gefahren werden muss.
//
const FORUM_VERSION_CODE = '0.25.1 Beta';

// Heimat der Software. Das Forumix-Zeichen im Fussbereich verweist hierher —
// dort liegt auch der Quelltext, womit AGPLv3 § 13 erfuellt ist. Wer den Code
// aendert, traegt im Adminbereich unter "Quelltext-URL" sein eigenes
// Repository ein; dann zeigt das Zeichen dorthin.
const FORUMIX_URL = 'https://forumix.de';

try {
    $dbVersion = (string) get_setting('forum_version');

    // Self-Healing: wenn DB-Version niedriger als Code-Version → aktualisieren
    // version_compare ignoriert Suffixe wie " Beta" weitestgehend, aber wir
    // normalisieren sicherheitshalber.
    $normalize = static function (string $v): string {
        $v = trim($v);
        // Alles ab erstem Leerzeichen ignorieren ("0.17.6 Beta" → "0.17.6")
        $sp = strpos($v, ' ');
        if ($sp !== false) $v = substr($v, 0, $sp);
        return $v;
    };

    $needsUpdate = empty($dbVersion)
        || version_compare($normalize($dbVersion), $normalize(FORUM_VERSION_CODE), '<');

    if ($needsUpdate) {
        try {
            db_execute(
                "INSERT INTO settings (`setting_key`, `setting_value`) VALUES ('forum_version', ?)
                 ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`)",
                [FORUM_VERSION_CODE]
            );
            $dbVersion = FORUM_VERSION_CODE;
        } catch (Exception $e) {
            // DB-Schreiben fehlgeschlagen (z.B. settings-Tabelle nicht da) – nicht tragisch
        }
    }

    if ($dbVersion && !defined('FORUM_VERSION_DB')) {
        define('FORUM_VERSION_DB', $dbVersion);
    }
} catch (Exception $e) {}

/**
 * Wird diese Installation von aussen aktuell gehalten?
 *
 * `define('FORUM_UPDATE_EXTERN', true)` in der config.php schaltet den
 * Update-Weg im Adminbereich ab: keine Pakete einspielen, keine Migrationen
 * ausloesen. Gedacht fuers Hosting — dort steht in der Leistungsbeschreibung,
 * dass der Betreiber des Dienstes die Installation aktuell haelt, und ein
 * Paket-Upload ist per Bauart Codeausfuehrung auf fremdem Webspace.
 *
 * Die Seite bleibt sichtbar und zeigt Version und Verlauf weiter. Deaktiviert,
 * nicht geloescht: eine Seite, die fehlt, liest sich wie ein Fehler.
 */
function update_extern(): bool {
    return defined('FORUM_UPDATE_EXTERN') && FORUM_UPDATE_EXTERN;
}

/**
 * Liefert die aktuelle Forum-Version.
 * Bevorzugt aus DB (per Self-Healing immer aktuell), Fallback auf Code-Konstante.
 */
function forum_version(): string {
    if (defined('FORUM_VERSION_DB')) return FORUM_VERSION_DB;
    return FORUM_VERSION_CODE;
}

/**
 * Liefert den Forum-Namen – bevorzugt aus DB-Setting, Fallback auf die Konstante.
 */
function forum_name(): string {
    try {
        $name = get_setting('forum_name');
        if ($name) return $name;
    } catch (Exception $e) {}
    return defined('FORUM_NAME') ? FORUM_NAME : 'Forum';
}

/**
 * Akzentfarbe abrufen (HEX-Code ohne #)
 */
function forum_accent_color(): string {
    try {
        $c = get_setting('forum_accent_color');
        if ($c && preg_match('/^[a-fA-F0-9]{6}$/', $c)) return $c;
    } catch (Exception $e) {}
    return '4f46e5'; // Default-Indigo
}

/**
 * Liefert alle Theme-Settings (Farben, Schrift, Form) mit Fallbacks.
 * Wird vom Layout aufgerufen um die CSS-Variablen zu erzeugen.
 */
function theme_settings(): array {
    static $cache = null;
    if ($cache !== null) return $cache;

    $defaults = [
        // Dark – Original-Theme (Discord/GitHub-Stil, aus style.css)
        'theme_color_bg_body'       => '0a0a0b',
        'theme_color_bg_surface'    => '131316',
        'theme_color_bg_surface2'   => '1a1a1f',
        'theme_color_text_base'     => 'e7e7ea',
        'theme_color_text_muted'    => 'a1a1aa',
        'theme_color_text_faint'    => '71717a',
        'theme_color_border'        => '24242b',
        'theme_color_border_strong' => '34343d',
        'theme_color_success'       => '4ade80',
        'theme_color_warning'       => 'fbbf24',
        'theme_color_danger'        => 'f87171',
        // Light
        'theme_color_bg_body_light'       => 'f7f7f8',
        'theme_color_bg_surface_light'    => 'ffffff',
        'theme_color_bg_surface2_light'   => 'f3f3f5',
        'theme_color_text_base_light'     => '18181b',
        'theme_color_text_muted_light'    => '52525b',
        'theme_color_text_faint_light'    => '71717a',
        'theme_color_border_light'        => 'e8e8eb',
        'theme_color_border_strong_light' => 'd4d4d8',
        // Schrift
        'theme_font_family'    => 'inter',
        'theme_font_custom'    => '',
        'theme_font_size_base' => '14',
        // Form
        'theme_radius_preset'  => 'medium',
        'theme_density'        => 'normal',
        'theme_max_width'      => '1200',
        'theme_header_style'   => 'default',
    ];

    $out = [];
    foreach ($defaults as $key => $def) {
        try {
            $val = get_setting($key);
            $out[$key] = ($val !== null && $val !== '') ? $val : $def;
        } catch (Exception $e) {
            $out[$key] = $def;
        }
    }
    return $cache = $out;
}

/**
 * Mappt theme_font_family auf eine CSS-Stack
 */
function theme_font_stack(string $key, string $custom = ''): string {
    $fonts = [
        'inter'     => "'Inter', system-ui, sans-serif",
        'system'    => "system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif",
        'roboto'    => "'Roboto', system-ui, sans-serif",
        'open-sans' => "'Open Sans', system-ui, sans-serif",
        'lato'      => "'Lato', system-ui, sans-serif",
        'nunito'    => "'Nunito', system-ui, sans-serif",
        'poppins'   => "'Poppins', system-ui, sans-serif",
        'chakra-petch' => "'Chakra Petch', system-ui, sans-serif",
        'custom'    => $custom ?: "system-ui, sans-serif",
    ];
    return $fonts[$key] ?? $fonts['inter'];
}

/**
 * Rundungs-Preset → konkrete Pixel-Werte
 * Liefert assoziativ: ['sm' => '4px', 'md' => '8px', 'lg' => '14px']
 */
function theme_radius_preset(string $key): array {
    $presets = [
        'sharp'  => ['sm' => '0',    'md' => '0',    'lg' => '0'],
        'small'  => ['sm' => '4px',  'md' => '6px',  'lg' => '8px'],
        'medium' => ['sm' => '8px',  'md' => '12px', 'lg' => '18px'],
        'large'  => ['sm' => '12px', 'md' => '18px', 'lg' => '24px'],
        'pill'   => ['sm' => '16px', 'md' => '24px', 'lg' => '32px'],
    ];
    return $presets[$key] ?? $presets['medium'];
}

/**
 * Density-Preset → Padding-Multiplikator
 */
function theme_density_factor(string $key): float {
    return match ($key) {
        'compact'    => 0.8,
        'comfortable'=> 1.15,
        default      => 1.0,
    };
}

// IP-Bann
if (is_ip_banned()) {
    http_response_code(403);
    die('<html><head><meta charset="UTF-8"><title>Zugriff verweigert</title>'
      . '<style>body{font-family:sans-serif;text-align:center;padding:60px;background:#0f1120;color:#e2e8ff}h1{color:#ef4444}</style>'
      . '</head><body><h1>🚫 Zugriff verweigert</h1><p>Deine IP-Adresse wurde gesperrt.</p></body></html>');
}

// Wartungsmodus
$scriptName = basename($_SERVER['SCRIPT_NAME'] ?? '');
$inMaintenance = (string) get_setting('maintenance_mode', '0') === '1';
$bypassMaintenance = in_array($scriptName, ['login.php', 'logout.php'], true)
                  || str_contains($_SERVER['REQUEST_URI'] ?? '', '/install/')
                  || str_contains($_SERVER['REQUEST_URI'] ?? '', '/admin/');

if ($inMaintenance && !$bypassMaintenance && !is_admin()) {
    http_response_code(503);
    $msg = e(get_setting('maintenance_message', 'Wartungsarbeiten – bitte später wiederkommen.'));
    die('<html><head><meta charset="UTF-8"><title>Wartung</title>'
      . '<style>body{font-family:sans-serif;text-align:center;padding:60px;background:#0f1120;color:#e2e8ff}'
      . 'h1{color:#f59e0b}.box{max-width:500px;margin:0 auto;background:#181b2e;padding:30px;border-radius:12px;border:1px solid #2a3060}'
      . 'a{color:#6c8aff}</style></head><body>'
      . '<div class="box"><h1>🛠️ Wartungsmodus</h1><p>' . $msg . '</p>'
      . '<p style="margin-top:20px"><a href="' . e(FORUM_URL) . '/login.php">Admin-Login</a> · '
      . '<a href="' . e(FORUM_URL) . '/admin/">Admin-Bereich</a></p></div></body></html>');
}

// Sprache via ?lang=xx setzen
if (isset($_GET['lang']) && in_array($_GET['lang'], ['de', 'en'], true)) {
    setcookie('forum_lang', $_GET['lang'], time() + 60 * 60 * 24 * 365, '/');
    $_COOKIE['forum_lang'] = $_GET['lang'];
    if (is_logged_in()) {
        $cu = current_user();
        try { db_execute('UPDATE users SET language = ? WHERE user_id = ?', [$_GET['lang'], $cu['user_id']]); }
        catch (Exception $e) {}
    }
}

// Online-Status tracken (außer im API-Endpunkt, um Last zu sparen)
if (!str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/api/')) {
    try { track_online(); } catch (Exception $e) {}
}

// Remember-Me-Auto-Login (nur wenn Session leer und Cookie da)
try { remember_try_login(); } catch (Exception $e) {}

// Hin und wieder abgelaufene Tokens entsorgen
try { remember_garbage_collect(); } catch (Exception $e) {}
