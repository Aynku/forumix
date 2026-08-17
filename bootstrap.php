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
const FORUM_VERSION_CODE = '0.25.5 Beta';

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
    // Default-Indigo. Dieselbe Farbe steht in install/install.sql — beide
    // muessen zusammenpassen, sonst kommt jede frische Installation in einer
    // anderen Farbe hoch als der Code fuer den Standard haelt.
    return '4f46e5';
}

/**
 * Zwei Buchstaben als Erkennungszeichen eines Forums: Anfangsbuchstaben der
 * ersten beiden Woerter, sonst die ersten beiden Zeichen. "Fragen & Hilfe"
 * wird FH, "Off-Topic" wird OT.
 */
function board_initials(string $title): string {
    $worte = preg_split('/[\s\-–_&\/]+/u', trim($title), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $kurz  = '';
    foreach ($worte as $w) {
        $kurz .= mb_substr($w, 0, 1);
        if (mb_strlen($kurz) === 2) break;
    }
    if ($kurz === '') $kurz = mb_substr(trim($title), 0, 2);
    if (mb_strlen($kurz) === 1) $kurz .= mb_substr($worte[0] ?? $title, 1, 1);
    return mb_strtoupper($kurz);
}

/**
 * Hat in dieser Kategorie ueberhaupt ein Forum Moderatoren? Wenn nicht,
 * faellt die Spalte weg, statt in jeder Zeile einen Gedankenstrich zu zeigen.
 */
function boards_have_mods(array $boards, array $boardMods): bool {
    foreach ($boards as $b) {
        if (!empty($boardMods[(int) ($b['board_id'] ?? 0)])) return true;
    }
    return false;
}

/**
 * Liefert alle Theme-Settings (Farben, Schrift, Form) mit Fallbacks.
 * Wird vom Layout aufgerufen um die CSS-Variablen zu erzeugen.
 */
function theme_settings(): array {
    static $cache = null;
    if ($cache !== null) return $cache;

    $defaults = theme_defaults();

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
 * Die Werkseinstellung des Themes — an EINER Stelle, weil die Verwaltung
 * dieselbe Liste zum Zuruecksetzen braucht. Standen sie doppelt, wuerde
 * "Zuruecksetzen" irgendwann eine andere Palette herstellen als eine
 * Neuinstallation. (install/install.sql haelt die dritte Kopie — die laesst
 * sich nicht vermeiden, sie legt die Zeilen ja erst an.)
 */
function theme_defaults(): array {
    // Die Grautoene sind leicht ins Blaue gezogen. Ein neutrales Grau wirkt
    // neben dem Indigo-Akzent stumpf; dieselbe Helligkeit mit etwas Blau
    // darin liest sich als gewaehlt statt als Standardwert.
    return [
        // Dark
        'theme_color_bg_body'       => '0d0f13',
        'theme_color_bg_surface'    => '15181e',
        'theme_color_bg_surface2'   => '1b1f26',
        'theme_color_text_base'     => 'e4e6ea',
        'theme_color_text_muted'    => '9aa0ab',
        'theme_color_text_faint'    => '6b7280',
        'theme_color_border'        => '252a33',
        'theme_color_border_strong' => '333a45',
        'theme_color_success'       => '4ade80',
        'theme_color_warning'       => 'fbbf24',
        'theme_color_danger'        => 'f87171',
        // Light
        'theme_color_bg_body_light'       => 'f5f6f8',
        'theme_color_bg_surface_light'    => 'ffffff',
        'theme_color_bg_surface2_light'   => 'eef0f4',
        'theme_color_text_base_light'     => '1a1d23',
        'theme_color_text_muted_light'    => '585e6b',
        'theme_color_text_faint_light'    => '8b91a0',
        'theme_color_border_light'        => 'e3e6ec',
        'theme_color_border_strong_light' => 'ccd1da',
        // Schrift
        // Wie im Entwurf: die Schrift des Geraets. Inter liegt weiter bei
        // und ist einen Klick entfernt, falls ueberall dasselbe Bild
        // wichtiger ist als die vertraute Schrift.
        'theme_font_family'    => 'system',
        'theme_font_custom'    => '',
        'theme_font_size_base' => '14',
        // Form
        'theme_radius_preset'  => 'medium',
        'theme_density'        => 'normal',
        'theme_max_width'      => '1200',
        'theme_header_style'   => 'inline',
    ];
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
// Der PFAD entscheidet, nicht ein Teilstueck der ganzen Adresse: mit
// str_contains() genuegte ein beliebiger Aufruf wie /index.php?x=/admin/
// oder /suche?q=/install/, um den Wartungsmodus zu umgehen — die Zeichen
// standen ja irgendwo in der Adresse. Der Abfragestring gehoert nicht dazu.
$pfad = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$basis = rtrim(parse_url(FORUM_URL, PHP_URL_PATH) ?? '', '/');
if ($basis !== '' && str_starts_with($pfad, $basis)) {
    $pfad = substr($pfad, strlen($basis));
}
$bypassMaintenance = in_array($scriptName, ['login.php', 'logout.php'], true)
                  || str_starts_with($pfad, '/install/')
                  || str_starts_with($pfad, '/admin/');

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
//
// Das Cookie darf ein Aufruf setzen — es betrifft nur die eigene Ansicht und
// ist mit einem zweiten Klick zurueckgestellt. Die DAUERHAFTE Einstellung im
// Konto ist etwas anderes: die aenderte bisher jede fremde Seite mit, indem
// sie ein Bild auf /index.php?lang=en einbettete. Dafuer gibt es jetzt die
// Sprachwahl in den Einstellungen (mit Token).
if (isset($_GET['lang']) && in_array($_GET['lang'], ['de', 'en'], true)) {
    setcookie('forum_lang', $_GET['lang'], time() + 60 * 60 * 24 * 365, '/');
    $_COOKIE['forum_lang'] = $_GET['lang'];
}

// Online-Status tracken (außer im API-Endpunkt, um Last zu sparen)
if (!str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/api/')) {
    try { track_online(); } catch (Exception $e) {}
}

// Remember-Me-Auto-Login (nur wenn Session leer und Cookie da)
try { remember_try_login(); } catch (Exception $e) {}

// Hin und wieder abgelaufene Tokens entsorgen
try { remember_garbage_collect(); } catch (Exception $e) {}
