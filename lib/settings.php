<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// lib/settings.php – Forum-Einstellungen aus der Datenbank

/**
 * In-memory cache für Einstellungen
 */
function settings_cache(): array {
    static $cache = null;
    if ($cache === null) {
        try {
            $rows  = db_fetchAll('SELECT setting_key, setting_value FROM settings');
            $cache = [];
            foreach ($rows as $r) {
                $cache[$r['setting_key']] = $r['setting_value'];
            }
        } catch (Exception $e) {
            $cache = [];
        }
    }
    return $cache;
}

function get_setting(string $key, $default = null) {
    $c = settings_cache();
    return $c[$key] ?? $default;
}

function set_setting(string $key, $value): void {
    db_execute(
        'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
        [$key, (string) $value]
    );
    // Cache invalidieren
    $GLOBALS['_settings_cache_reset'] = true;
}

/**
 * Liefert die URL zum Forum-Logo.
 * Reihenfolge: 1) gesetztes Setting 'forum_logo' → 2) /assets/logo.png (FTP) → 3) Standard-SVG
 */
function forum_logo_url(): string {
    // 1. Setting aus DB
    $custom = get_setting('forum_logo');
    if ($custom) {
        // Wenn relative Pfad: vorne FORUM_URL davor
        if (!preg_match('/^https?:\/\//', $custom)) {
            return FORUM_URL . '/' . ltrim($custom, '/');
        }
        return $custom;
    }

    // 2. /assets/logo.png oder logo.svg via FTP
    foreach (['logo.svg', 'logo.png', 'logo.jpg', 'logo.webp'] as $name) {
        if (file_exists(__DIR__ . '/../assets/' . $name)) {
            return FORUM_URL . '/assets/' . $name;
        }
    }

    // 3. Standard-SVG-Fallback
    return FORUM_URL . '/assets/logo_default.svg';
}

/**
 * Prüft ob das Logo das Standard-Fallback-SVG ist
 */
function is_default_logo(): bool {
    return str_ends_with(forum_logo_url(), '/logo_default.svg');
}

/**
 * Rendert das Forum-Logo als HTML.
 * Inlines theme-adaptive SVGs (mit currentColor) damit sie sich an Light/Dark anpassen können.
 * Sonst klassisches <img>-Tag.
 *
 * @param string $alt Alternative-Text für Accessibility
 * @return string HTML-Code
 */
function forum_logo_render(string $alt): string {
    $url = forum_logo_url();
    // Custom Setting / Custom Logo aus DB?
    $custom = get_setting('forum_logo');

    // Wenn's das Default-SVG ist (oder ein lokales SVG): inline einbetten für currentColor-Support
    if (!$custom && (is_default_logo() || str_ends_with($url, '.svg'))) {
        // Pfad zur Datei lokal auflösen
        $localPath = __DIR__ . '/../assets/' . basename(parse_url($url, PHP_URL_PATH) ?: '');
        if (file_exists($localPath)) {
            $svg = @file_get_contents($localPath);
            if ($svg) {
                // Aria-Label aus dem Alt-Text setzen, Größen-Attribute erhalten
                // SVG ist schon mit role/aria-label versehen, das überschreiben wir nicht
                return $svg;
            }
        }
    }

    // Fallback: klassisches img-Tag mit Cache-Buster
    return '<img src="' . e($url) . '?v=' . e(forum_version()) . '" alt="' . e($alt) . '">';
}

/**
 * Aktuelle Client-IP zuverlässig ermitteln
 */
function client_ip(): string {
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $k) {
        if (!empty($_SERVER[$k])) {
            $ip = $_SERVER[$k];
            if (str_contains($ip, ',')) {
                $ip = trim(explode(',', $ip)[0]);
            }
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return '0.0.0.0';
}

/**
 * Prüft ob die aktuelle IP gesperrt ist
 */
function is_ip_banned(?string $ip = null): bool {
    $ip = $ip ?? client_ip();
    try {
        $ban = db_fetch(
            'SELECT ban_id FROM ip_bans
             WHERE ip = ? AND (expires_at IS NULL OR expires_at > NOW())
             LIMIT 1',
            [$ip]
        );
        return (bool) $ban;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * IP-Eintrag protokollieren (register/login/post)
 */
function log_user_ip(int $userId, string $action, ?string $ip = null): void {
    $ip = $ip ?? client_ip();
    try {
        db_execute(
            'INSERT INTO user_ip_log (user_id, ip, action) VALUES (?, ?, ?)',
            [$userId, $ip, $action]
        );
    } catch (Exception $e) {
        // still nichts tun, ist nur Log
    }
}

/**
 * Liefert die Impressum-/Datenschutz-Daten als Array.
 * Felder die nicht gesetzt sind, bekommen einen Platzhalter-Default.
 * Mit Flag 'is_default' falls ALLE Felder noch ungesetzt sind
 * (= User hat im Admin nichts ausgefüllt).
 *
 * @return array{name:string, street:string, city:string, email:string, is_default:bool}
 */
function imprint_data(): array {
    $name   = (string) get_setting('imprint_name', '');
    $street = (string) get_setting('imprint_street', '');
    $city   = (string) get_setting('imprint_city', '');
    $email  = (string) get_setting('imprint_email', '');

    $isDefault = ($name === '' && $street === '' && $city === '' && $email === '');

    return [
        'name'       => $name !== '' ? $name : '[Name des Forenbetreibers]',
        'street'     => $street !== '' ? $street : '[Straße und Hausnummer]',
        'city'       => $city !== '' ? $city : '[PLZ und Ort]',
        'email'      => $email !== '' ? $email : 'admin@example.com',
        'is_default' => $isDefault,
    ];
}
