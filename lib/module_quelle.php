<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
/**
 * lib/module_quelle.php – Module beim Hersteller nachschlagen
 *
 * ════════════════════════════════════════════════════════════════════
 *  Das ist die EINZIGE Stelle, an der Forumix einen fremden Server
 *  aufruft. Deshalb gelten hier engere Regeln als sonst wo:
 *
 *   - Im Auslieferungszustand ist das aus. Wer nichts einschaltet,
 *     dessen Forum ruft nichts auf.
 *   - Der Abruf geschieht nur auf ausdruecklichen Klick, nie im
 *     Hintergrund, nie beim Seitenaufbau, nie zeitgesteuert.
 *   - Es geht nichts mit: keine Adresse des Forums, keine Fassung,
 *     keine Kennung, kein eigener User-Agent. Was der Hersteller
 *     sieht, ist der Abruf einer statischen Datei — mehr nicht.
 *   - Die Quelle steht fest. Eine frei einstellbare Adresse waere
 *     eine Einladung, Code von irgendwoher zu holen.
 * ════════════════════════════════════════════════════════════════════
 *
 * Was das NICHT leistet: Es schuetzt nicht davor, dass beim Hersteller
 * etwas Falsches liegt. Die Pruefsumme kommt aus derselben Liste wie die
 * Datei — sie faengt einen Uebertragungsfehler ab, keinen veraenderten
 * Server. Wer das nicht will, laedt von Hand und sieht selbst nach.
 */

require_once __DIR__ . '/module_package.php';

const MODULQUELLE_URL   = 'https://forumix.de/download/module-liste.json';
const MODULQUELLE_BASIS = 'https://forumix.de/download/';
const MODULQUELLE_ZEIT  = 15;          // Sekunden
const MODULQUELLE_CACHE = 3600;        // eine Stunde

/** Ist der Bezug eingeschaltet? Vorgabe: nein. */
function modulquelle_erlaubt(): bool {
    return (int) get_setting('module_quelle_an', '0') === 1;
}

/**
 * Holt die Liste. Aus dem Zwischenspeicher, solange er frisch ist.
 *
 * @return array{ok: bool, error?: string, module?: array, stand?: int}
 */
function modulquelle_liste(bool $frisch = false): array {
    if (!modulquelle_erlaubt()) {
        return ['ok' => false, 'error' => t('sys_der_bezug_vom_hersteller')];
    }

    $cache = rtrim(sys_get_temp_dir(), '/') . '/forumix-module-' . substr(sha1(FORUM_URL), 0, 12) . '.json';

    if (!$frisch && is_file($cache) && (time() - (int) filemtime($cache)) < MODULQUELLE_CACHE) {
        $daten = json_decode((string) file_get_contents($cache), true);
        if (is_array($daten)) {
            return ['ok' => true, 'module' => $daten['module'] ?? [], 'stand' => (int) filemtime($cache)];
        }
    }

    $antwort = modulquelle_holen(MODULQUELLE_URL);
    if (!$antwort['ok']) return $antwort;

    $daten = json_decode($antwort['inhalt'], true);
    if (!is_array($daten) || !isset($daten['module'])) {
        return ['ok' => false, 'error' => t('sys_die_liste_war_nicht')];
    }

    @file_put_contents($cache, $antwort['inhalt']);
    return ['ok' => true, 'module' => $daten['module'], 'stand' => time()];
}

/**
 * Ein einzelner Abruf.
 *
 * Bewusst ohne eigenen User-Agent: Die Vorgabe von curl verraet nichts ueber
 * dieses Forum. Ein "Forumix/1.9.0 (https://forum.example)" waere genau die
 * Rueckmeldung, die es hier nicht geben soll.
 */
function modulquelle_holen(string $url): array {
    if (!str_starts_with($url, 'https://')) {
        return ['ok' => false, 'error' => t('sys_nur_https')];
    }
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'error' => t('sys_auf_diesem_server_fehlt')];
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => MODULQUELLE_ZEIT,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_FOLLOWLOCATION => false,   // eine Umleitung koennte woanders hinfuehren
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_MAXFILESIZE    => MODULPAKET_MAX_BYTES,
    ]);
    $inhalt = curl_exec($ch);
    $code   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $fehler = curl_error($ch);
    unset($ch);

    if ($inhalt === false) {
        error_log('[modulquelle] ' . $fehler);
        return ['ok' => false, 'error' => t('sys_der_hersteller_war_nicht')];
    }
    if ($code !== 200) {
        return ['ok' => false, 'error' => t('sys_der_hersteller_antwortete_mit') . $code . '.'];
    }
    return ['ok' => true, 'inhalt' => (string) $inhalt];
}

/**
 * Laedt ein Paket und legt es als Datei ab. Prueft die Pruefsumme aus der
 * Liste — die faengt einen Uebertragungsfehler ab.
 *
 * @return array{ok: bool, error?: string, datei?: string}
 */
function modulquelle_paket_laden(array $eintrag): array {
    $datei = (string) ($eintrag['datei'] ?? '');
    // Der Dateiname kommt aus einer fremden Antwort und wandert in eine URL.
    if (!preg_match('/^forumix-modul-[a-z0-9_-]+-[0-9.]+\.zip$/i', $datei)) {
        return ['ok' => false, 'error' => t('sys_unerwarteter_dateiname_in_der')];
    }

    $antwort = modulquelle_holen(MODULQUELLE_BASIS . $datei);
    if (!$antwort['ok']) return $antwort;

    $soll = strtolower((string) ($eintrag['sha256'] ?? ''));
    if ($soll !== '') {
        if (!hash_equals($soll, hash('sha256', $antwort['inhalt']))) {
            return ['ok' => false, 'error' => t('sys_die_pruefsumme_stimmt_nicht')];
        }
    }

    $ziel = rtrim(sys_get_temp_dir(), '/') . '/forumix-modul-' . bin2hex(random_bytes(8)) . '.zip';
    if (file_put_contents($ziel, $antwort['inhalt']) === false) {
        return ['ok' => false, 'error' => t('sys_das_paket_liess_sich')];
    }
    return ['ok' => true, 'datei' => $ziel];
}

/**
 * Was ist installiert, was gibt es beim Hersteller, was ist neuer?
 */
function modulquelle_vergleich(array $entfernt): array {
    $hier = function_exists('available_modules') ? available_modules() : [];
    $aus  = [];

    foreach ($entfernt as $m) {
        $code = (string) ($m['code'] ?? '');
        if ($code === '') continue;

        $vorhanden = $hier[$code]['version'] ?? null;
        $aus[] = [
            'code'         => $code,
            'name'         => (string) ($m['name'] ?? $code),
            'icon'         => (string) ($m['icon'] ?? '🧩'),
            'beschreibung' => (string) ($m['description'] ?? ''),
            'dort'         => (string) ($m['version'] ?? ''),
            'hier'         => $vorhanden,
            'neuer'        => $vorhanden !== null
                              && version_compare((string) $m['version'], (string) $vorhanden, '>'),
            'passt'        => modulpaket_fassung_pruefen($m) === null,
            'eintrag'      => $m,
        ];
    }
    return $aus;
}
