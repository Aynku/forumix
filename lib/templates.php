<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// lib/templates.php – Verwaltung von Template-Overrides aus der DB

/**
 * In-Memory Cache pro Request
 */
function templates_cache(): array {
    static $cache = null;
    if ($cache === null) {
        try {
            $rows = db_fetchAll('SELECT template_name, content FROM template_overrides');
            $cache = [];
            foreach ($rows as $r) {
                $cache[$r['template_name']] = $r['content'];
            }
        } catch (Exception $e) {
            $cache = [];
        }
    }
    return $cache;
}

/**
 * Liefert den Override-Inhalt für ein Template (oder null wenn keiner).
 */
function get_template_override(string $name): ?string {
    $c = templates_cache();
    return $c[$name] ?? null;
}

/**
 * Liefert den damaligen Original-Stand (zum Zeitpunkt des letzten Save).
 * null wenn kein Override.
 */
function get_template_original_at_save(string $name): ?string {
    try {
        $row = db_fetch(
            'SELECT original_at_save FROM template_overrides WHERE template_name = ?',
            [$name]
        );
        return $row ? $row['original_at_save'] : null;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Liefert den Original-Inhalt eines Templates (aus Datei).
 */
function get_template_original(string $name): ?string {
    $path = __DIR__ . '/../views_new/' . $name . '.tpl.html';
    if (!file_exists($path)) return null;
    return file_get_contents($path);
}

/**
 * Liefert den effektiv genutzten Template-Inhalt: Override wenn vorhanden, sonst Original.
 */
function get_template_effective(string $name): ?string {
    $override = get_template_override($name);
    if ($override !== null) return $override;
    return get_template_original($name);
}

/**
 * Prüft ob ein Override "veraltet" ist:
 * = Datei-Original ist anders als das damalige Original beim letzten Save.
 * Bedeutet: Update hat Datei geändert, Override kennt die Änderung nicht.
 */
function is_template_override_stale(string $name): bool {
    $savedOriginal = get_template_original_at_save($name);
    if ($savedOriginal === null) return false;  // kein Override
    $currentOriginal = get_template_original($name);
    if ($currentOriginal === null) return false;  // Datei weg, behandeln wir wie "ok"
    return $savedOriginal !== $currentOriginal;
}

/**
 * Speichert einen Override und merkt sich den aktuellen Datei-Stand.
 */
function save_template_override(string $name, string $content, ?int $userId = null): bool {
    try {
        $originalNow = get_template_original($name) ?? '';
        db_execute(
            'INSERT INTO template_overrides (template_name, content, original_at_save, updated_by) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                content = VALUES(content),
                original_at_save = VALUES(original_at_save),
                updated_by = VALUES(updated_by)',
            [$name, $content, $originalNow, $userId]
        );
        templates_invalidate_cache($name);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * "Akzeptiert" das aktuelle Datei-Original ohne den Override-Inhalt zu ändern:
 * setzt original_at_save = aktuelles Datei-Original. Damit verschwindet die
 * Stale-Warnung; der Admin sagt damit "Mein Override ist OK so wie er ist".
 */
function acknowledge_template_override_update(string $name): bool {
    try {
        $current = get_template_original($name);
        if ($current === null) return false;
        db_execute(
            'UPDATE template_overrides SET original_at_save = ? WHERE template_name = ?',
            [$current, $name]
        );
        templates_invalidate_cache($name);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Löscht einen Override (Reset zu Original). Invalidiert den Cache.
 */
function delete_template_override(string $name): bool {
    try {
        db_execute('DELETE FROM template_overrides WHERE template_name = ?', [$name]);
        templates_invalidate_cache($name);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Löscht den Compile-Cache eines Templates, damit es beim nächsten Rendern neu kompiliert wird.
 */
function templates_invalidate_cache(string $name): void {
    $cacheFile = __DIR__ . '/../cache/templates/' . str_replace('/', '_', $name) . '.php';
    if (file_exists($cacheFile)) {
        @unlink($cacheFile);
    }
}

/**
 * Liste aller verfügbaren Templates (aus views_new/) mit Override-Status.
 *
 * @return array<int, array{name:string, has_override:bool, is_stale:bool, updated_at:?string}>
 */
function list_templates_with_status(): array {
    $dir = __DIR__ . '/../views_new';
    if (!is_dir($dir)) return [];

    $files = glob($dir . '/*.tpl.html');
    if ($files === false) return [];

    // Overrides mit Timestamp + original_at_save holen
    try {
        $rows = db_fetchAll('SELECT template_name, updated_at, original_at_save FROM template_overrides');
        $overrides = [];
        foreach ($rows as $r) {
            $overrides[$r['template_name']] = $r;
        }
    } catch (Exception $e) {
        $overrides = [];
    }

    $list = [];
    foreach ($files as $file) {
        $base = basename($file, '.tpl.html');
        $isStale = false;
        $hasOverride = isset($overrides[$base]);
        if ($hasOverride) {
            $current = file_get_contents($file);
            $isStale = ($current !== $overrides[$base]['original_at_save']);
        }
        $list[] = [
            'name'         => $base,
            'has_override' => $hasOverride,
            'is_stale'     => $isStale,
            'updated_at'   => $hasOverride ? $overrides[$base]['updated_at'] : null,
        ];
    }
    usort($list, fn($a, $b) => strcmp($a['name'], $b['name']));
    return $list;
}

/**
 * Validiert minimal: Template darf keine `<?php`-Tags enthalten.
 */
function template_content_is_safe(string $content): bool {
    if (stripos($content, '<?php') !== false) return false;
    if (stripos($content, '<?=')   !== false) return false;
    if (strpos($content, '<?')     !== false) return false;
    return true;
}

/**
 * Erzeugt einen einfachen Line-by-Line Diff zwischen zwei Strings.
 * Liefert ein Array aus Zeilen mit Typ-Marker:
 *   ['type' => 'context'|'add'|'del', 'text' => '…']
 */
function template_diff(string $old, string $new): array {
    $a = explode("\n", $old);
    $b = explode("\n", $new);

    // Klassische LCS-Tabelle (für moderate Template-Größen ok)
    $m = count($a);
    $n = count($b);
    $lcs = array_fill(0, $m + 1, array_fill(0, $n + 1, 0));

    for ($i = 1; $i <= $m; $i++) {
        for ($j = 1; $j <= $n; $j++) {
            if ($a[$i - 1] === $b[$j - 1]) {
                $lcs[$i][$j] = $lcs[$i - 1][$j - 1] + 1;
            } else {
                $lcs[$i][$j] = max($lcs[$i - 1][$j], $lcs[$i][$j - 1]);
            }
        }
    }

    // Backtrace
    $result = [];
    $i = $m; $j = $n;
    while ($i > 0 && $j > 0) {
        if ($a[$i - 1] === $b[$j - 1]) {
            array_unshift($result, ['type' => 'context', 'text' => $a[$i - 1]]);
            $i--; $j--;
        } elseif ($lcs[$i - 1][$j] >= $lcs[$i][$j - 1]) {
            array_unshift($result, ['type' => 'del', 'text' => $a[$i - 1]]);
            $i--;
        } else {
            array_unshift($result, ['type' => 'add', 'text' => $b[$j - 1]]);
            $j--;
        }
    }
    while ($i > 0) { array_unshift($result, ['type' => 'del', 'text' => $a[$i - 1]]); $i--; }
    while ($j > 0) { array_unshift($result, ['type' => 'add', 'text' => $b[$j - 1]]); $j--; }

    return $result;
}
