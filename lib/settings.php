<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// lib/settings.php – Forum-Einstellungen aus der Datenbank

/**
 * In-memory cache für Einstellungen
 */
function settings_cache(bool $reset = false): array {
    static $cache = null;
    if ($reset) { $cache = null; return []; }
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
    // Cache wirklich verwerfen — das frühere Flag hat nie jemand gelesen,
    // get_setting() lieferte im selben Request weiter den alten Wert.
    settings_cache(true);
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
 * Erzeugt das Standard-Logo aus dem Forum-Namen.
 *
 * Kein fester Name mehr in der Datei: Sprechblase, Name und Akzentpunkt
 * werden zur Laufzeit gesetzt, damit jede Installation ihr eigenes Logo hat,
 * ohne dass jemand eine SVG-Datei bearbeiten muss. Farbe kommt aus der
 * Akzentfarbe des Themes, der Punkt rutscht mit der Namenslaenge mit.
 */
function forum_logo_svg(?string $name = null, ?string $accent = null): string {
    $name = trim($name ?? forum_name());

    // „Aynku Forum" würde über dem Untertitel FORUM zu „Aynku Forum FORUM“.
    // Ein angehängtes Forum/Board/Community fällt deshalb im Schriftzug weg —
    // solange etwas übrig bleibt.
    $short = preg_replace('/\s+(forum|board|community)\s*$/iu', '', $name);
    if (is_string($short) && trim($short) !== '') $name = trim($short);

    if ($name === '') $name = 'Forum';
    $accent = ltrim($accent ?? forum_accent_color(), '#');
    if (!preg_match('/^[0-9a-fA-F]{6}$/', $accent)) $accent = '4f46e5';

    // Lange Namen kleiner setzen, damit das Logo nicht ausufert
    $len  = mb_strlen($name);
    $size = $len <= 8 ? 40 : ($len <= 14 ? 32 : ($len <= 22 ? 25 : 20));

    // Breite je Zeichenklasse schaetzen. Das ist NUR fuer die Zeichenflaeche —
    // wo der Punkt sitzt, misst der Browser selbst. Lieber etwas zu breit
    // rechnen: zu schmal schneidet den Namen rechts ab.
    $em = 0.45;   // Vorschub des Punktes plus Abstand
    foreach (preg_split('//u', $name, -1, PREG_SPLIT_NO_EMPTY) as $ch) {
        if (preg_match('/[MWmw@]/u', $ch))                 $em += 0.95;
        elseif (preg_match('/[ijltfI.,;:!\'| ]/u', $ch))    $em += 0.36;
        elseif (preg_match('/\p{Lu}/u', $ch))              $em += 0.70;
        else                                               $em += 0.58;
    }
    $textX = 95;
    // Aufschlag von 12 %: die Schaetzung darf danebenliegen, und bis die
    // eigene Schrift geladen ist zeichnet der Browser mit einer Ersatzschrift,
    // die breiter sein kann. Zu viel Flaeche kostet Rand, zu wenig schneidet.
    $viewW = max(320, $textX + (int) round($em * $size * 1.12) + 16);

    // Heller Verlaufspartner zur Akzentfarbe (einfach aufgehellt)
    $light = sprintf('%02x%02x%02x',
        min(255, (int) (hexdec(substr($accent, 0, 2)) * 0.45 + 140)),
        min(255, (int) (hexdec(substr($accent, 2, 2)) * 0.45 + 140)),
        min(255, (int) (hexdec(substr($accent, 4, 2)) * 0.45 + 140)));

    $esc = static fn(string $s) => htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
    $uid = 'lg' . substr(md5($accent . $name), 0, 6);   // eindeutige Verlaufs-IDs je Logo

    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $viewW . ' 120" height="100"'
      . ' preserveAspectRatio="xMinYMid meet" role="img" aria-label="' . $esc($name) . '">'
      . '<defs>'
      . '<linearGradient id="' . $uid . 'a" x1="0%" y1="0%" x2="100%" y2="100%">'
      . '<stop offset="0%" stop-color="#' . $light . '"/><stop offset="100%" stop-color="#' . $accent . '"/></linearGradient>'
      . '<linearGradient id="' . $uid . 'b" x1="0%" y1="0%" x2="100%" y2="0%">'
      . '<stop offset="0%" stop-color="#' . $accent . '"/><stop offset="100%" stop-color="#' . $light . '"/></linearGradient>'
      . '</defs>'
      . '<g transform="translate(10, 20)">'
      . '<rect x="0" y="0" width="68" height="60" rx="18" ry="18" fill="url(#' . $uid . 'a)"/>'
      . '<path d="M 18 60 L 22 76 L 32 60 Z" fill="url(#' . $uid . 'a)"/>'
      . '<circle cx="18" cy="30" r="5" fill="white" opacity="0.95"/>'
      . '<circle cx="34" cy="30" r="5" fill="white" opacity="0.95"/>'
      . '<circle cx="50" cy="30" r="5" fill="white" opacity="0.95"/>'
      . '</g>'
      . '<text x="' . $textX . '" y="62" font-family="Inter, Segoe UI, system-ui, sans-serif"'
      . ' font-size="' . $size . '" font-weight="800" fill="url(#' . $uid . 'b)" letter-spacing="-1">'
      . $esc($name)
      // Der Punkt steht IM Text, damit der Browser den Abstand misst. Als
      // eigener Kreis sass er an einer gerechneten Stelle und rutschte je nach
      // Name mal in den Text, mal weit daneben. Die Faktoren stammen aus den
      // Maßen von Inter 800 (Punkt 0.312em breit, 0.290em ueber der
      // Grundlinie) und ergeben denselben Punkt wie vorher: Durchmesser ein
      // Fuenftel der Schriftgroesse, Mitte ein Zehntel darueber.
      . '<tspan dx="' . round($size * 0.02 + 1, 1) . '" dy="' . round($size * 0.086, 1) . '"'
      . ' font-size="' . round($size * 0.64, 1) . '" fill="#' . $light . '">&#8226;</tspan>'
      . '</text>'
      . '<text x="' . ($textX + 3) . '" y="86" font-family="Inter, Segoe UI, system-ui, sans-serif"'
      . ' font-size="11" font-weight="600" fill="#888a98" letter-spacing="4">FORUM</text>'
      . '</svg>';
}

/**
 * Rendert das Forum-Logo als HTML.
 * Ohne eigenes Logo wird es aus dem Forum-Namen erzeugt; lokale SVGs werden
 * eingebettet (damit currentColor auf Light und Dark greift), alles andere
 * kommt als <img>.
 */
function forum_logo_render(string $alt): string {
    $url = forum_logo_url();
    // Custom Setting / Custom Logo aus DB?
    $custom = get_setting('forum_logo');

    // Kein eigenes Logo hinterlegt → aus dem Forum-Namen erzeugen
    if (!$custom && is_default_logo()) {
        return forum_logo_svg();
    }

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
/**
 * Liefert die IP des Aufrufers. Geglaubt wird nur REMOTE_ADDR — die
 * Forwarded-Kopfzeilen schickt der Client selbst, wenn kein Proxy davorsteht.
 * Mit Proxy: define('TRUSTED_PROXIES', ['127.0.0.1']) in der config.php.
 */
function client_ip(): string {
    $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

    $trusted = defined('TRUSTED_PROXIES') ? (array) TRUSTED_PROXIES : [];
    if ($remote !== '' && in_array($remote, $trusted, true)) {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP'] as $k) {
            if (empty($_SERVER[$k])) continue;
            $ip = (string) $_SERVER[$k];
            // Kette "client, proxy1, proxy2" – der erste ist der Aufrufer
            if (str_contains($ip, ',')) {
                $ip = trim(explode(',', $ip)[0]);
            }
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }

    if (filter_var($remote, FILTER_VALIDATE_IP)) {
        return $remote;
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
