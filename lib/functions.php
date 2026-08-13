<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// lib/functions.php

function e(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function bbcode(string $text): string {
    $text = e($text);

    // ── QUOTES zuerst (mit Post-ID-Link, dann mit Author, dann simpel) ──
    // [quote=username;post=42]Inhalt[/quote]  → mit Sprung zum Original
    $text = preg_replace_callback(
        '/\[quote=([^;\]]+);post=(\d+)\](.*?)\[\/quote\]/is',
        function ($m) {
            $author = $m[1];
            $postId = (int) $m[2];
            $body   = $m[3];
            return '<blockquote class="bb-quote">'
                 . '<cite><a href="#post' . $postId . '" class="quote-link">' . $author . ' schrieb:</a></cite>'
                 . $body
                 . '</blockquote>';
        },
        $text
    );
    // [quote=username]Inhalt[/quote]  → mit Autor
    $text = preg_replace(
        '/\[quote=([^\]]+)\](.*?)\[\/quote\]/is',
        '<blockquote class="bb-quote"><cite>$1 schrieb:</cite>$2</blockquote>',
        $text
    );
    // [quote]Inhalt[/quote]  → ohne Autor
    $text = preg_replace(
        '/\[quote\](.*?)\[\/quote\]/is',
        '<blockquote class="bb-quote">$1</blockquote>',
        $text
    );

    $patterns = [
        '/\[b\](.*?)\[\/b\]/is'   => '<strong>$1</strong>',
        '/\[i\](.*?)\[\/i\]/is'   => '<em>$1</em>',
        '/\[u\](.*?)\[\/u\]/is'   => '<u>$1</u>',
        '/\[s\](.*?)\[\/s\]/is'   => '<s>$1</s>',
        '/\[h2\](.*?)\[\/h2\]/is' => '<h2 class="bb-h2">$1</h2>',
        '/\[h3\](.*?)\[\/h3\]/is' => '<h3 class="bb-h3">$1</h3>',
        '/\[hr\]/is' => '<hr class="bb-hr">',
        '/\[code\](.*?)\[\/code\]/is' => '<pre class="bb-code">$1</pre>',
        '/\[spoiler\](.*?)\[\/spoiler\]/is' => '<details class="bb-spoiler"><summary>Spoiler anzeigen</summary>$1</details>',
        '/\[url\](https?:\/\/[^\[]+)\[\/url\]/is' => '<a href="$1" rel="nofollow noopener" target="_blank">$1</a>',
        '/\[url=(https?:\/\/[^\]]+)\](.*?)\[\/url\]/is' => '<a href="$1" rel="nofollow noopener" target="_blank">$2</a>',
        '/\[url=(mailto:[^\]]+)\](.*?)\[\/url\]/is' => '<a href="$1">$2</a>',
        '/\[url=(\/[^\]]+)\](.*?)\[\/url\]/is' => '<a href="$1">$2</a>',
        '/\[img\](https?:\/\/[^\[]+)\[\/img\]/is' => '<img src="$1" alt="" class="bb-img">',
        '/\[color=(#[0-9a-fA-F]{3,6}|[a-zA-Z]+)\](.*?)\[\/color\]/is' => '<span style="color:$1">$2</span>',
    ];
    $text = preg_replace(array_keys($patterns), array_values($patterns), $text);

    // ── LISTS ────────────────────────────────────────────────────────
    // [list]...[/list] mit [*] für Listenpunkte
    $text = preg_replace_callback(
        '/\[list(?:=(1|a|i))?\](.*?)\[\/list\]/is',
        function ($m) {
            $type = $m[1] ?? '';
            $body = $m[2];
            $tag  = $type ? 'ol' : 'ul';
            $typeAttr = '';
            if ($type === '1') $typeAttr = ' type="1"';
            elseif ($type === 'a') $typeAttr = ' type="a"';
            elseif ($type === 'i') $typeAttr = ' type="i"';

            // [*]-Marker durch <li>-Tags ersetzen
            $items = preg_split('/\[\*\]/', $body);
            $html = '';
            foreach ($items as $item) {
                $item = trim($item);
                if ($item !== '') {
                    $html .= '<li>' . $item . '</li>';
                }
            }
            return '<' . $tag . $typeAttr . ' class="bb-list">' . $html . '</' . $tag . '>';
        },
        $text
    );

    // ── @MENTIONS ──
    // @username → klickbarer Link zum Profil (sofern User existiert)
    $text = preg_replace_callback(
        '/(^|[\s>])@([a-zA-Z0-9_äöüÄÖÜß\-]{1,30})\b/u',
        function ($m) {
            $prefix = $m[1];
            $name   = $m[2];
            try {
                $u = db_fetch('SELECT user_id FROM users WHERE username = ? LIMIT 1', [$name]);
                if ($u) {
                    return $prefix . '<a href="' . FORUM_URL . '/profile.php?id=' . (int) $u['user_id'] . '" class="mention">@' . e($name) . '</a>';
                }
            } catch (Exception $e) {}
            return $prefix . '@' . $name;
        },
        $text
    );

    $text = nl2br($text);

    // Smileys ersetzen (geschützt vor [code]/[quote])
    if (function_exists('parse_smileys')) {
        $text = parse_smileys($text);
    }

    return $text;
}

function paginate(int $total, int $perPage, int $currentPage): array {
    $totalPages  = max(1, (int) ceil($total / $perPage));
    $currentPage = max(1, min($currentPage, $totalPages));
    return [
        'total'       => $total,
        'per_page'    => $perPage,
        'current'     => $currentPage,
        'total_pages' => $totalPages,
        'offset'      => ($currentPage - 1) * $perPage,
        'has_prev'    => $currentPage > 1,
        'has_next'    => $currentPage < $totalPages,
    ];
}

function pagination_html(array $p, string $baseUrl): string {
    if ($p['total_pages'] <= 1) return '';
    $sep = str_contains($baseUrl, '?') ? '&' : '?';
    $html = '<nav class="pagination">';
    if ($p['has_prev']) {
        $html .= '<a href="' . $baseUrl . $sep . 'page=' . ($p['current'] - 1) . '">&laquo;</a>';
    }
    for ($i = 1; $i <= $p['total_pages']; $i++) {
        $cls = $i === $p['current'] ? ' class="active"' : '';
        $html .= '<a href="' . $baseUrl . $sep . 'page=' . $i . '"' . $cls . '>' . $i . '</a>';
    }
    if ($p['has_next']) {
        $html .= '<a href="' . $baseUrl . $sep . 'page=' . ($p['current'] + 1) . '">&raquo;</a>';
    }
    return $html . '</nav>';
}

function format_date(?string $datetime): string {
    if (!$datetime) return '';
    try {
        $dt  = new DateTime($datetime);
        $now = new DateTime();

        // Beide auf Datum normalisieren (ohne Uhrzeit), dann Kalendertage vergleichen
        $dtDay  = (clone $dt)->setTime(0, 0, 0);
        $nowDay = (clone $now)->setTime(0, 0, 0);
        $dayDiff = (int) $nowDay->diff($dtDay)->format('%r%a'); // negativ wenn Datum in Vergangenheit

        if ($dayDiff === 0)  return 'Heute, '       . $dt->format('H:i') . ' Uhr';
        if ($dayDiff === -1) return 'Gestern, '     . $dt->format('H:i') . ' Uhr';
        if ($dayDiff === 1)  return 'Morgen, '      . $dt->format('H:i') . ' Uhr';
        return $dt->format('d.m.Y, H:i') . ' Uhr';
    } catch (Exception $e) {
        return $datetime;
    }
}

/**
 * Liefert die Avatar-URL für einen User.
 *
 * Übergabe wahlweise:
 *   - Avatar-Dateiname (String) → klassisch
 *   - User-Array → mit Rollen-Erkennung (Owner/Admin/Mod-Avatar wenn kein eigenes hochgeladen)
 *   - null → Default-Avatar
 *
 * Priorität bei User-Array: eigenes Upload > Owner-Avatar > Admin-Avatar > Mod-Avatar > Default
 */
function avatar_url($userOrAvatar): string {
    // Fall A: User-Array übergeben
    if (is_array($userOrAvatar)) {
        $user = $userOrAvatar;
        // Gelöschte User bekommen immer den Default-Avatar (keine Rolle, kein Bild)
        if (!empty($user['is_deleted'])) {
            return FORUM_URL . '/assets/default_avatar.svg';
        }
        // 1. Eigener Upload hat immer Vorrang
        $avatar = $user['avatar'] ?? '';
        if ($avatar && file_exists(AVATAR_DIR . $avatar)) {
            return FORUM_URL . '/uploads/avatars/' . rawurlencode($avatar);
        }
        // 2. Rollen-Avatar (Owner > Admin > Mod), nur wenn kein eigenes Bild
        if (!empty($user['is_owner'])) {
            return _role_avatar_url('owner');
        }
        if (!empty($user['is_admin'])) {
            return _role_avatar_url('admin');
        }
        if (!empty($user['is_mod'])) {
            return _role_avatar_url('mod');
        }
        // 3. Fallback
        return FORUM_URL . '/assets/default_avatar.svg';
    }

    // Fall B: Avatar-String (Backwards-Compat)
    $avatar = $userOrAvatar;
    if ($avatar && file_exists(AVATAR_DIR . $avatar)) {
        return FORUM_URL . '/uploads/avatars/' . rawurlencode($avatar);
    }
    return FORUM_URL . '/assets/default_avatar.svg';
}

/**
 * Liefert die URL eines Rollen-Avatars (owner/admin/mod).
 * Bevorzugt selbst hochgeladene .png/.jpg/.webp, fällt zurück auf SVG.
 */
function _role_avatar_url(string $role): string {
    $assetsDir = __DIR__ . '/../assets/';
    // Custom-Uploads zuerst (kann Admin im Backend austauschen)
    foreach (['png', 'jpg', 'jpeg', 'webp', 'svg'] as $ext) {
        $file = $assetsDir . 'role_' . $role . '_custom.' . $ext;
        if (file_exists($file)) {
            return FORUM_URL . '/assets/role_' . $role . '_custom.' . $ext;
        }
    }
    // Standard-SVG
    return FORUM_URL . '/assets/role_' . $role . '.svg';
}

function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

function flash(string $msg, string $type = 'info'): void {
    $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
}

function get_flash(): ?array {
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function get_int(string $key, int $default = 0): int {
    return isset($_GET[$key]) ? (int) $_GET[$key] : $default;
}

function post_int(string $key, int $default = 0): int {
    return isset($_POST[$key]) ? (int) $_POST[$key] : $default;
}

function post_str(string $key, string $default = ''): string {
    return isset($_POST[$key]) ? trim((string) $_POST[$key]) : $default;
}

function truncate(string $s, int $len = 60): string {
    if (mb_strlen($s) <= $len) return $s;
    return mb_substr($s, 0, $len - 1) . '…';
}

/**
 * render_editor() – Gibt das Editor-Partial als HTML-String zurück.
 * v0.18.53: Ermöglicht die Nutzung des BBCode-Editors auch in den
 * .tpl.html-Templates (die kein PHP-include können), z.B. für Signatur
 * und "Über mich" in den Profil-Einstellungen.
 *
 * @param string $id          HTML-ID/editor-id (muss eindeutig sein pro Seite)
 * @param string $name        name-Attribut der Textarea
 * @param string $value       Vorbelegter Inhalt (roh, wird im Partial escaped)
 * @param string $placeholder Placeholder-Text
 * @param bool   $required    required-Attribut
 * @param bool   $allowHeadings  H2/H3/HR-Buttons anzeigen
 */
function render_editor(string $id, string $name, ?string $value = '',
                       string $placeholder = '', bool $required = false,
                       bool $allowHeadings = false): string {
    $value = (string) ($value ?? '');
    $editorId          = $id;
    $editorName        = $name;
    $editorValue       = $value;
    $editorPlaceholder = $placeholder;
    $editorRequired    = $required;
    $editorAllowHeadings = $allowHeadings;

    ob_start();
    include __DIR__ . '/../views/partials/editor.php';
    return (string) ob_get_clean();
}
