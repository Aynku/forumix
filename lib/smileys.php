<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// lib/smileys.php – Smileys aus der Datenbank mit Picker und Parser

/**
 * Cache pro Request, damit nicht bei jedem Post-Rendering DB-Calls fliegen.
 */
function smileys_cache_clear(): void {
    $GLOBALS['__smileys_cache_all']    = null;
    $GLOBALS['__smileys_cache_picker'] = null;
}

/**
 * Alle Smileys aus der DB: ['code' => ['emoji', 'beschreibung'], ...]
 *
 * Sortierung: längere Codes zuerst, damit beim Parsen z.B. :-)) nicht
 * vorab als :-) gematcht wird.
 *
 * Fallback bei DB-Fehler: leere Liste (Smileys werden dann nicht ersetzt,
 * aber der Post wird trotzdem korrekt angezeigt).
 */
function smileys_list(): array {
    if (isset($GLOBALS['__smileys_cache_all']) && is_array($GLOBALS['__smileys_cache_all'])) {
        return $GLOBALS['__smileys_cache_all'];
    }
    try {
        $rows = db_fetchAll('SELECT `code`, `emoji`, `description` FROM smileys');
    } catch (Exception $e) {
        $GLOBALS['__smileys_cache_all'] = [];
        return [];
    }

    $list = [];
    foreach ($rows as $r) {
        $list[$r['code']] = [$r['emoji'], $r['description'] ?? ''];
    }

    // Längere Codes zuerst sortieren
    uksort($list, fn($a, $b) => mb_strlen($b) - mb_strlen($a));

    $GLOBALS['__smileys_cache_all'] = $list;
    return $list;
}

/**
 * Picker-Smileys (kuratierte Auswahl für die Toolbar)
 * Liefert ['code' => 'emoji', ...] sortiert nach position.
 */
function smileys_picker(): array {
    if (isset($GLOBALS['__smileys_cache_picker']) && is_array($GLOBALS['__smileys_cache_picker'])) {
        return $GLOBALS['__smileys_cache_picker'];
    }
    try {
        $rows = db_fetchAll(
            'SELECT `code`, `emoji` FROM smileys WHERE is_in_picker = 1 ORDER BY position ASC'
        );
    } catch (Exception $e) {
        $GLOBALS['__smileys_cache_picker'] = [];
        return [];
    }

    $picker = [];
    foreach ($rows as $r) {
        $picker[$r['code']] = $r['emoji'];
    }

    $GLOBALS['__smileys_cache_picker'] = $picker;
    return $picker;
}

/**
 * Ersetzt Smiley-Codes im Text durch Emojis.
 * WICHTIG: nach bbcode() aufrufen, damit Codes in [code] nicht ersetzt werden.
 * Zitate werden NICHT geschützt — Smileys sollen auch in Zitaten gerendert werden.
 */
function parse_smileys(string $html): string {
    $list = smileys_list();
    if (empty($list)) return $html; // Bei leerer Liste nichts ersetzen

    // Nur Code-Blöcke vor Ersetzung schützen (Zitate sollen Smileys haben)
    $placeholders = [];
    $i = 0;
    $html = preg_replace_callback(
        '/<pre class="bb-code">.*?<\/pre>/is',
        function ($m) use (&$placeholders, &$i) {
            $key = "\x01SMILEY_PROTECT_{$i}\x01";
            $placeholders[$key] = $m[0];
            $i++;
            return $key;
        },
        $html
    );

    foreach ($list as $code => [$emoji, $desc]) {
        $pattern = '/(?<![a-zA-Z0-9])' . preg_quote($code, '/') . '(?![a-zA-Z0-9])/u';
        $replace = '<span class="smiley" title="' . e($desc) . '">' . $emoji . '</span>';
        $html = preg_replace($pattern, $replace, $html);
    }

    foreach ($placeholders as $key => $original) {
        $html = str_replace($key, $original, $html);
    }
    return $html;
}
