<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }

/**
 * lib/template.php – Forumix Template Engine
 *
 * Eine schlanke, eigene Template-Engine im Stil von Twig/Blade.
 * - Auto-Escape standardmäßig
 * - Compile-Cache (Templates werden in PHP übersetzt)
 * - Mischbetrieb mit alten PHP-Views möglich
 *
 * Syntax:
 *   {{ var }}                    – escaped output
 *   {{ var|raw }}                – unescaped output
 *   {{ var|date('d.m.Y') }}      – mit Filter
 *   {{ user.name }}              – Punkt-Zugriff (Array oder Objekt)
 *   {% if expr %} ... {% elseif expr %} ... {% else %} ... {% endif %}
 *   {% for item in list %} ... {% endfor %}
 *     - innerhalb: loop.index, loop.index0, loop.first, loop.last, loop.length
 *   {% set var = expr %}
 *   {% include 'partial' %}       – andere Template einbinden
 *   {% include 'partial' with x=1, y=2 %}
 *
 * Filter:
 *   |raw, |e, |escape       – Escape-Kontrolle
 *   |upper, |lower, |trim
 *   |length                  – Strings und Arrays
 *   |default('fallback')     – wenn null/leer
 *   |date('format')          – Datums-Formatierung
 *   |truncate(50)            – mit '…' kürzen
 *   |nl2br                   – Zeilenumbrüche zu <br>
 *   |json                    – als JSON ausgeben
 *
 * Funktionen (in Templates aufrufbar):
 *   url('/x')                – FORUM_URL . '/x'
 *   asset('style.css')       – FORUM_URL . '/assets/style.css?v=...'
 *   t('translation_key')     – Lang-Helper (wenn lang.php geladen)
 *   csrf_token()             – aktueller CSRF-Token
 */

const TPL_DIR        = __DIR__ . '/../views_new';
const TPL_CACHE_DIR  = __DIR__ . '/../cache/templates';
const TPL_EXTENSION  = '.tpl.html';

/**
 * Hauptfunktion: rendert ein Template mit Daten und gibt das Ergebnis zurück.
 * Compile-Cache wird automatisch verwaltet.
 */
function tpl_render(string $name, array $data = []): string {
    $source = TPL_DIR . '/' . $name . TPL_EXTENSION;
    if (!file_exists($source)) {
        throw new RuntimeException("Template not found: $name ($source)");
    }

    $compiled = tpl_compile_path($name);

    extract($data, EXTR_SKIP);
    ob_start();
    try {
        include $compiled;
        return ob_get_clean();
    } catch (Throwable $e) {
        ob_end_clean();
        throw $e;
    }
}

/**
 * Gibt den Pfad zur kompilierten PHP-Datei zurück.
 * Erstellt/aktualisiert den Cache wenn nötig.
 *
 * Berücksichtigt Override aus der DB (Funktion get_template_override).
 * Wenn ein Override existiert, wird DIESER kompiliert statt der Datei.
 */
function tpl_compile_path(string $name): string {
    $source   = TPL_DIR . '/' . $name . TPL_EXTENSION;
    $compiled = TPL_CACHE_DIR . '/' . str_replace('/', '_', $name) . '.php';

    if (!is_dir(TPL_CACHE_DIR)) {
        @mkdir(TPL_CACHE_DIR, 0775, true);
    }

    // Override aus DB prüfen (nur wenn templates_cache verfügbar ist)
    $override = null;
    if (function_exists('get_template_override')) {
        $override = get_template_override($name);
    }

    // Re-compile wenn:
    //  - Cache fehlt
    //  - ODER Cache älter als Source-Datei (und kein Override)
    //  - Bei Overrides wird der Cache direkt durch save/delete invalidiert
    $needsCompile = !file_exists($compiled);
    if (!$needsCompile && $override === null) {
        $needsCompile = filemtime($source) > filemtime($compiled);
    }

    if ($needsCompile) {
        $sourceCode = $override !== null ? $override : file_get_contents($source);
        // Derselbe Riegel wie in jeder anderen PHP-Datei des Forums. Die
        // kompilierten Vorlagen liegen unter cache/ und werden normalerweise
        // vom Webserver gesperrt (.htaccess, nginx) — wenn diese Sperre auf
        // einer Installation fehlt oder verlorengeht, laeuft eine Vorlage
        // sonst direkt aufrufbar los, mit allem was darin steht.
        $php = "<?php if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); } ?>\n"
             . tpl_compile($sourceCode);
        file_put_contents($compiled, $php);
    }

    return $compiled;
}

/**
 * Übersetzt Template-Quellcode in ausführbares PHP.
 */
function tpl_compile(string $source): string {
    // Reihenfolge wichtig: zuerst Tags ({% %}), dann Ausgabe ({{ }}),
    // sonst wird etwa {% if x == "{{...}}" %} kaputt.

    // 1. Kommentare {# ... #} → komplett raus
    $source = preg_replace('/\{#.*?#\}/s', '', $source);

    // 2. {% include 'name' %} und {% include 'name' with x=1, y=2 %}
    $source = preg_replace_callback(
        '/\{%\s*include\s+[\'"](?<name>[^\'"]+)[\'"](?:\s+with\s+(?<args>[^%]+))?\s*%\}/',
        function ($m) {
            $name = $m['name'];
            if (!empty($m['args'])) {
                // 'x=1, y=foo' → ['x' => 1, 'y' => $foo]
                $argsExpr = tpl_compile_with_args($m['args']);
                return '<?= tpl_render(' . var_export($name, true) . ', array_merge(get_defined_vars(), ' . $argsExpr . ')) ?>';
            }
            return '<?= tpl_render(' . var_export($name, true) . ', get_defined_vars()) ?>';
        },
        $source
    );

    // 3. {% set var = expr %}
    $source = preg_replace_callback(
        '/\{%\s*set\s+(\w+)\s*=\s*(.+?)\s*%\}/',
        function ($m) {
            $var = $m[1];
            $expr = tpl_compile_expression($m[2]);
            return '<?php $' . $var . ' = ' . $expr . '; ?>';
        },
        $source
    );

    // 4. {% if expr %} / {% elseif %} / {% else %} / {% endif %}
    $source = preg_replace_callback(
        '/\{%\s*if\s+(.+?)\s*%\}/',
        fn($m) => '<?php if (' . tpl_compile_expression($m[1]) . '): ?>',
        $source
    );
    $source = preg_replace_callback(
        '/\{%\s*elseif\s+(.+?)\s*%\}/',
        fn($m) => '<?php elseif (' . tpl_compile_expression($m[1]) . '): ?>',
        $source
    );
    $source = preg_replace('/\{%\s*else\s*%\}/', '<?php else: ?>', $source);
    $source = preg_replace('/\{%\s*endif\s*%\}/', '<?php endif; ?>', $source);

    // 5. {% for item in list %} ... {% endfor %}
    $source = preg_replace_callback(
        '/\{%\s*for\s+(\w+)\s+in\s+(.+?)\s*%\}/',
        function ($m) {
            $item = $m[1];
            $list = tpl_compile_expression($m[2]);
            // loop-Variable mit metadaten füllen.
            //
            // Sie wird auf einen Stapel gelegt und am Ende zurueckgeholt: es
            // gibt nur EIN $loop, und eine innere Schleife hat die aeussere
            // sonst ueberschrieben. Sichtbar wurde das in der Themenansicht —
            // dort trugen alle Beitraege die Nummer #1, weil die (leere)
            // Reaktionsschleife $loop bei jedem Durchgang neu anlegte.
            return '<?php $__loop_stapel[] = $loop ?? null; '
                 . '$__loop_list = ' . $list . ' ?? []; '
                 . '$loop = (object)["index" => 0, "index0" => -1, "first" => true, "last" => false, "length" => is_countable($__loop_list) ? count($__loop_list) : 0]; '
                 . 'foreach ($__loop_list as $' . $item . '): '
                 . '$loop->index0++; $loop->index = $loop->index0 + 1; '
                 . '$loop->first = ($loop->index0 === 0); '
                 . '$loop->last = ($loop->index === $loop->length); ?>';
        },
        $source
    );
    $source = preg_replace('/\{%\s*endfor\s*%\}/',
        '<?php endforeach; $loop = array_pop($__loop_stapel); ?>', $source);

    // 6. {{ expr|filter|filter }} – Ausgabe mit Filter-Kette
    $source = preg_replace_callback(
        '/\{\{\s*(.+?)\s*\}\}/',
        function ($m) {
            return tpl_compile_output($m[1]);
        },
        $source
    );

    return $source;
}

/**
 * Übersetzt einen Ausgabe-Ausdruck inkl. Filter.
 * Default: auto-escape mit e().
 */
function tpl_compile_output(string $expr): string {
    // Filter abtrennen
    $parts = preg_split('/\s*\|\s*/', $expr);
    $valueExpr = array_shift($parts);
    $valueExpr = tpl_compile_expression($valueExpr);

    $escape = true;  // default: auto-escape

    foreach ($parts as $filter) {
        // Filter mit Argument: name(arg1, arg2)
        if (preg_match('/^(\w+)\((.*)\)$/', trim($filter), $fm)) {
            $fname = $fm[1];
            $fargs = $fm[2];
        } else {
            $fname = trim($filter);
            $fargs = '';
        }

        switch ($fname) {
            case 'raw':
                $escape = false;
                break;
            case 'e':
            case 'escape':
                $escape = true;
                break;
            case 'upper':
                $valueExpr = 'mb_strtoupper((string)(' . $valueExpr . '))';
                break;
            case 'lower':
                $valueExpr = 'mb_strtolower((string)(' . $valueExpr . '))';
                break;
            case 'trim':
                $valueExpr = 'trim((string)(' . $valueExpr . '))';
                break;
            case 'length':
                $valueExpr = 'tpl_filter_length(' . $valueExpr . ')';
                break;
            case 'default':
                $valueExpr = 'tpl_filter_default(' . $valueExpr . ', ' . $fargs . ')';
                break;
            case 'date':
                $valueExpr = 'tpl_filter_date(' . $valueExpr . ', ' . ($fargs ?: var_export('d.m.Y H:i', true)) . ')';
                break;
            case 'truncate':
                $valueExpr = 'tpl_filter_truncate(' . $valueExpr . ', ' . ($fargs ?: '50') . ')';
                break;
            case 'nl2br':
                $valueExpr = 'nl2br(htmlspecialchars((string)(' . $valueExpr . '), ENT_QUOTES, "UTF-8"))';
                $escape = false;  // wir haben schon escaped, nl2br fügt erlaubtes <br> ein
                break;
            case 'json':
                $valueExpr = 'json_encode(' . $valueExpr . ')';
                $escape = false;  // JSON wird auch unsafe behandelt im JS-Kontext
                break;
            default:
                // Unbekannter Filter: als Funktion versuchen
                $valueExpr = 'tpl_filter_apply(' . var_export($fname, true) . ', ' . $valueExpr . ($fargs ? ', ' . $fargs : '') . ')';
        }
    }

    if ($escape) {
        return '<?= htmlspecialchars((string)(' . $valueExpr . '), ENT_QUOTES, "UTF-8") ?>';
    }
    return '<?= ' . $valueExpr . ' ?>';
}

/**
 * Übersetzt einen Ausdruck. Hauptsächlich: punkt-zugriff `user.name` → $user['name']
 * Aber auch: Strings, Zahlen, Funktionen, Operatoren bleiben unverändert.
 */
function tpl_compile_expression(string $expr): string {
    $expr = trim($expr);
    if ($expr === '') return '';

    // Token-Stream durchgehen und Punkt-Zugriffe ersetzen
    // Wir machen das vorsichtig: Strings ('...' / "...") werden NICHT angetastet.
    $out = '';
    $len = strlen($expr);
    $i = 0;
    while ($i < $len) {
        $c = $expr[$i];

        // String-Literal: alles bis zum schließenden Anführungszeichen durchreichen
        if ($c === '"' || $c === "'") {
            $quote = $c;
            $out .= $c;
            $i++;
            while ($i < $len) {
                if ($expr[$i] === '\\' && $i + 1 < $len) {
                    $out .= $expr[$i] . $expr[$i+1];
                    $i += 2; continue;
                }
                $out .= $expr[$i];
                if ($expr[$i] === $quote) { $i++; break; }
                $i++;
            }
            continue;
        }

        // Identifier-Start
        if (preg_match('/[a-zA-Z_]/', $c)) {
            $start = $i;
            while ($i < $len && preg_match('/[a-zA-Z0-9_]/', $expr[$i])) $i++;
            $name = substr($expr, $start, $i - $start);

            // Keywords stehen lassen
            $keywords = ['and', 'or', 'not', 'in', 'true', 'false', 'null', 'TRUE', 'FALSE', 'NULL'];
            if (in_array($name, $keywords, true)) {
                $out .= $name;
                continue;
            }

            // Funktionsaufruf? -> als Funktion behandeln, nicht als Variable
            $j = $i;
            while ($j < $len && $expr[$j] === ' ') $j++;
            if ($j < $len && $expr[$j] === '(') {
                $out .= $name;
                continue;
            }

            // Punkt-Zugriffe sammeln
            $path = [$name];
            while ($i < $len && $expr[$i] === '.') {
                $i++;
                $s = $i;
                while ($i < $len && preg_match('/[a-zA-Z0-9_]/', $expr[$i])) $i++;
                $segment = substr($expr, $s, $i - $s);
                if ($segment === '') break;
                $path[] = $segment;
            }

            $base = '$' . $path[0];
            for ($k = 1; $k < count($path); $k++) {
                $seg = $path[$k];
                // Array oder Objekt? Wir nutzen einen Helper der beides kann
                $base = 'tpl_get(' . $base . ', ' . var_export($seg, true) . ')';
            }
            $out .= $base;
            continue;
        }

        // Operator-Aliasse (Twig-Stil) übersetzen
        // 'and' / 'or' / 'not' / 'in' wurden oben als Identifier erkannt – hier behandeln:
        // (das passiert in einer Nach-Verarbeitung)

        $out .= $c;
        $i++;
    }

    // 'and'/'or'/'not'/'in' nach PHP-Operatoren übersetzen
    // Wichtig: 'not' kann von beliebigem nicht-Wort-Zeichen gefolgt sein,
    // inkl. '$', '!', '(', etc. (z.B. "not $var", "not ($x or $y)")
    $out = preg_replace('/\bnot\s+/', '!', $out);
    $out = preg_replace('/\band\b/',   '&&', $out);
    $out = preg_replace('/\bor\b/',    '||', $out);
    // 'in' nicht trivial – lassen wir aus, oder als helper

    return $out;
}

/**
 * Kompiliert "x=1, y=expr" zu PHP-Array.
 */
function tpl_compile_with_args(string $argsStr): string {
    $pairs = explode(',', $argsStr);
    $out = '[';
    foreach ($pairs as $p) {
        if (!str_contains($p, '=')) continue;
        [$k, $v] = array_map('trim', explode('=', $p, 2));
        $out .= var_export($k, true) . ' => ' . tpl_compile_expression($v) . ', ';
    }
    return $out . ']';
}

/* ════════════ RUNTIME-HELPER (von kompilierten Templates aufgerufen) ════════════ */

/**
 * Sicherer Zugriff auf Array oder Objekt – beides funktioniert transparent.
 * tpl_get($user, 'name') → $user['name'] ODER $user->name
 */
function tpl_get($obj, string $key) {
    if (is_array($obj)) {
        return $obj[$key] ?? null;
    }
    if (is_object($obj)) {
        if (isset($obj->$key)) return $obj->$key;
        if (method_exists($obj, $key)) return $obj->$key();
        return null;
    }
    return null;
}

function tpl_filter_length($v): int {
    if (is_string($v)) return mb_strlen($v);
    if (is_countable($v)) return count($v);
    return 0;
}

function tpl_filter_default($v, $fallback) {
    if ($v === null || $v === '' || $v === false) return $fallback;
    if (is_array($v) && count($v) === 0) return $fallback;
    return $v;
}

function tpl_filter_date($v, string $format = 'd.m.Y H:i'): string {
    if (empty($v)) return '';
    if (is_numeric($v)) return date($format, (int) $v);
    $ts = strtotime((string) $v);
    return $ts ? date($format, $ts) : (string) $v;
}

function tpl_filter_truncate($v, int $length = 50, string $suffix = '…'): string {
    $v = (string) $v;
    if (mb_strlen($v) <= $length) return $v;
    return mb_substr($v, 0, $length) . $suffix;
}

function tpl_filter_apply(string $name, ...$args) {
    // Erst eigenen Filter-Namespace prüfen, dann globalen
    $fn = 'tpl_filter_' . $name;
    if (function_exists($fn)) return $fn(...$args);
    if (function_exists($name)) return $name(...$args);
    throw new RuntimeException("Unknown template filter: $name");
}

/* ════════════ TEMPLATE-FUNKTIONEN (in Templates aufrufbar) ════════════ */

/**
 * url('/login') → FORUM_URL . '/login'
 */
if (!function_exists('url')) {
    function url(string $path = ''): string {
        return FORUM_URL . ($path && !str_starts_with($path, '/') ? '/' : '') . $path;
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string {
        $v = function_exists('forum_version') ? forum_version() : '1';
        return FORUM_URL . '/assets/' . $path . '?v=' . urlencode($v);
    }
}

/**
 * Manuell Cache leeren – nützlich bei Deploys.
 */
function tpl_cache_clear(): int {
    $count = 0;
    if (!is_dir(TPL_CACHE_DIR)) return 0;
    foreach (glob(TPL_CACHE_DIR . '/*.php') ?: [] as $f) {
        if (@unlink($f)) $count++;
    }
    return $count;
}
