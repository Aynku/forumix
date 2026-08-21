<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// lib/functions.php

// Erlaubte Bild-Uploads: Inhaltstyp → Dateiendung. Die Endung kommt aus
// dieser Tabelle, nie aus dem hochgeladenen Dateinamen.
const UPLOAD_IMAGE_EXTENSIONS = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
];

function e(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Fehlerseite im Forum-Layout statt nacktem die().
 *
 * Faellt auf eine schlichte HTML-Seite zurueck, wenn das Layout selbst nicht
 * mehr laeuft (z.B. Datenbank weg) — eine Fehlerseite, die einen Fehler
 * braucht um zu erscheinen, waere keine.
 * API-Endpunkte bekommen JSON, sonst bricht die Antwort dem Browser-Skript
 * im Hals ab.
 */
function error_page(int $code, string $title, string $message = ''): void {
    if (!headers_sent()) http_response_code($code);

    $isApi = str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/api/')
          || str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/modules/');
    if ($isApi) {
        if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => $message !== '' ? $message : $title]);
        exit;
    }

    try {
        render('error', ['title' => $title, 'message' => $message]);
    } catch (Throwable $e) {
        echo '<!doctype html><html lang="de"><head><meta charset="UTF-8"><title>' . e($title)
           . '</title></head><body style="font-family:system-ui;background:#0a0a0b;color:#e7e7ea;'
           . 'text-align:center;padding:60px"><h1>' . e($title) . '</h1><p>' . e($message)
           . '</p><p><a href="' . e(FORUM_URL) . '/" style="color:#4f46e5">Zur Startseite</a></p></body></html>';
    }
    exit;
}

/**
 * Sprachen, die im Code-Block beschriftet und eingefaerbt werden.
 * Wert: [Beschriftung, CodeMirror-Modus]. Ohne Modus wird nur beschriftet —
 * lieber grauer Code als falsch eingefaerbter.
 */
const BB_CODE_SPRACHEN = [
    'php'        => ['PHP',        'application/x-httpd-php-open'],  // -open: faengt IM PHP an. Die normale Kennung startet in HTML und laesst
    //                                                    Ausschnitte ohne <?php voellig ungefaerbt.
    'sql'        => ['SQL',        'text/x-sql'],
    'js'         => ['JavaScript', 'text/javascript'],
    'javascript' => ['JavaScript', 'text/javascript'],
    'json'       => ['JSON',       'application/json'],
    'html'       => ['HTML',       'text/html'],
    'css'        => ['CSS',        'text/css'],
    'xml'        => ['XML',        'application/xml'],
    'bash'       => ['Shell',      ''],
    'shell'      => ['Shell',      ''],
    'text'       => ['Text',       ''],
];

/**
 * Baut den Code-Block. $inhalt ist bereits durch e() gegangen und wird hier
 * NICHT erneut maskiert.
 *
 * Jede Zeile bekommt ein eigenes <span>, damit die Nummerierung ohne
 * JavaScript funktioniert. Leere Zeilen bleiben leer statt ein Leerzeichen zu
 * bekommen — sonst schleppt jedes Kopieren unsichtbare Zeichen mit; die Hoehe
 * macht das Stylesheet.
 */
function code_block_html(string $sprache, string $inhalt, string $praefix = ''): string {
    $sprache = strtolower(trim($sprache));
    $bekannt = BB_CODE_SPRACHEN[$sprache] ?? null;

    // Erste und letzte Leerzeile weg: [code] steht meist auf eigener Zeile.
    $inhalt = preg_replace('/\A\R|\R\z/', '', $inhalt);

    $zeilen = preg_split('/\R/', $inhalt);
    $rumpf  = '';
    $nr     = 0;
    foreach ($zeilen as $z) {
        $nr++;
        if ($praefix !== '') {
            // Jede Zeile bekommt eine Sprungmarke und die Nummer davor einen
            // Verweis darauf. Damit laesst sich auf EINE Zeile zeigen —
            // "schau dir Zeile 14 an" wird zum Klick. Die Nummer selbst steht
            // im ::before des Verweises, nicht im Text: sonst wanderte sie
            // beim Kopieren mit.
            $marke  = $praefix . '-' . $nr;
            $rumpf .= '<span class="bb-zeile" id="' . e($marke) . '">'
                    . '<a class="bb-zeilennr" href="#' . e($marke) . '"'
                    . ' aria-label="Zeile ' . $nr . '"></a>'
                    . $z . '</span>';
        } else {
            $rumpf .= '<span class="bb-zeile">' . $z . '</span>';
        }
    }

    $modus = ($bekannt && $bekannt[1] !== '') ? ' data-modus="' . e($bekannt[1]) . '"' : '';
    $marke = $praefix !== '' ? ' data-marke="' . e($praefix) . '"' : '';
    return '<div class="bb-code"' . $modus . $marke . '>'
         . '<div class="bb-code-kopf">'
         . '<span class="bb-code-sprache">' . e($bekannt[0] ?? 'Code') . '</span>'
         . '<button type="button" class="bb-code-kopieren">Kopieren</button>'
         . '</div>'
         . '<pre>' . $rumpf . '</pre></div>';
}

/**
 * Anzeigename eines Kontos.
 *
 * Die Loeschung ersetzt den Benutzernamen durch "Geloeschter Benutzer #<id>".
 * Die Nummer muss sein — die Spalte ist eindeutig, und zwei geloeschte Konten
 * duerfen sich nicht ins Gehege kommen. Zu sehen braucht sie aber niemand:
 * delete_account.tpl.html verspricht ausdruecklich "Geloeschter Benutzer", ohne
 * Nummer. Genau das liefert diese Funktion.
 */
function zeigename(?string $name): string {
    $name = (string) $name;
    return preg_match('/^Gelöschter Benutzer #\d+$/u', $name) ? 'Gelöschter Benutzer' : $name;
}

/**
 * $postId ist die Beitragskennung, sofern bekannt. Sie geht in die
 * Sprungmarken der Codezeilen ein, damit ein Verweis auf Zeile 14 auch dann
 * noch stimmt, wenn der Beitrag auf eine andere Seite des Themas rutscht.
 * Ohne Kennung — Vorschau, Suchtreffer, Modul — gibt es keine Marken.
 */
function bbcode(string $text, ?int $postId = null): string {
    $text = e($text);

    // ── CODE ZUERST HERAUSNEHMEN ──
    // Alles Weitere in dieser Funktion greift sonst auch im Code zu: [b] macht
    // fett, @name wird zum Verweis, nl2br() haengt an jede Zeile ein <br>, und
    // parse_smileys() macht aus :) ein Bild. Deshalb wandert der Block als
    // Platzhalter durch die ganze Kette und kommt erst ganz am Ende zurueck.
    $codeBloecke = [];
    $text = preg_replace_callback(
        '/\[code(?:=([a-zA-Z0-9+#_-]{1,16}))?\](.*?)\[\/code\]/is',
        function ($m) use (&$codeBloecke, $postId) {
            $nummer     = count($codeBloecke);
            $schluessel = "\x02CODE" . $nummer . "\x02";
            $praefix    = $postId !== null ? 'p' . $postId . 'c' . ($nummer + 1) : '';
            $codeBloecke[$schluessel] = code_block_html($m[1] ?? '', $m[2], $praefix);
            return $schluessel;
        },
        $text
    );

    // ── INLINE-CODE ──
    // [c]...[/c] ist der kleine Bruder: mitten im Satz, ohne Kasten. Er wandert
    // in dieselbe Ablage und aus demselben Grund — was drinsteht, ist Text und
    // keine Auszeichnung. Genau dafuer wird er gebraucht: um ueber BBCode zu
    // schreiben, ohne dass es wirkt.
    $text = preg_replace_callback(
        '/\[c\](.*?)\[\/c\]/is',
        function ($m) use (&$codeBloecke) {
            // Eigener Platzhalter, nicht \x02CODE: der Kasten ist ein Block und
            // schluckt unten die Umbrueche neben sich. Ein Wort mitten im Satz
            // darf das nicht — sonst verschwindet der Zeilenumbruch daneben.
            $schluessel = "\x02INLINE" . count($codeBloecke) . "\x02";
            $codeBloecke[$schluessel] = '<code class="bb-inline">' . $m[1] . '</code>';
            return $schluessel;
        },
        $text
    );

    // ── ZITATE ──
    // Von innen nach aussen: der Ausdruck trifft nur Zitate, deren Inhalt kein
    // weiteres [quote] enthaelt, und wird so lange wiederholt, bis keines mehr
    // uebrig ist.
    //
    // Vorher gab es drei Muster nacheinander, jedes mit einem faulen .*? — bei
    // verschachtelten Zitaten nahm das erste den Schluss-Tag des INNEREN und
    // liess den aeusseren stehen. Sichtbar wurde das am Namen: aus
    // [quote=Aynku;post=41] wurde die Zeile "Aynku;post=41 schrieb".
    $runden = 0;
    while ($runden++ < 20) {
        $vorher = $text;
        $text = preg_replace_callback(
            '/\[quote(?:=([^\]]*))?\]((?:(?!\[quote(?:=[^\]]*)?\]).)*?)\[\/quote\]/is',
            function ($m) {
                $attribut = trim($m[1] ?? '');
                $inhalt   = $m[2];

                $autor  = $attribut;
                $postId = 0;
                if ($attribut !== '' && preg_match('/^(.*?);post=(\d+)$/', $attribut, $t)) {
                    $autor  = trim($t[1]);
                    $postId = (int) $t[2];
                }

                $kopf = '';
                if ($autor !== '' && $postId > 0) {
                    $kopf = '<div class="bb-quote-kopf">'
                          . '<a href="#post' . $postId . '" class="bb-quote-autor">' . $autor . '</a> schrieb'
                          . '<a href="#post' . $postId . '" class="bb-quote-sprung">zum Beitrag &rarr;</a>'
                          . '</div>';
                } elseif ($autor !== '') {
                    $kopf = '<div class="bb-quote-kopf">'
                          . '<span class="bb-quote-autor">' . $autor . '</span> schrieb</div>';
                }

                return '<blockquote class="bb-quote">' . $kopf
                     . '<div class="bb-quote-inhalt">' . $inhalt . '</div></blockquote>';
            },
            $text
        );
        if ($text === null || $text === $vorher) { $text = $text ?? $vorher; break; }
    }

    $patterns = [
        '/\[b\](.*?)\[\/b\]/is'   => '<strong>$1</strong>',
        '/\[i\](.*?)\[\/i\]/is'   => '<em>$1</em>',
        '/\[u\](.*?)\[\/u\]/is'   => '<u>$1</u>',
        '/\[s\](.*?)\[\/s\]/is'   => '<s>$1</s>',
        '/\[h2\](.*?)\[\/h2\]/is' => '<h2 class="bb-h2">$1</h2>',
        '/\[h3\](.*?)\[\/h3\]/is' => '<h3 class="bb-h3">$1</h3>',
        '/\[hr\]/is' => '<hr class="bb-hr">',
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
    $text = bb_umbrueche_an_bloecken_kuerzen($text);

    // Smileys ersetzen (geschützt vor [code]/[quote])
    if (function_exists('parse_smileys')) {
        $text = parse_smileys($text);
    }

    // Code zurueck an seinen Platz — nach allem anderen, unberuehrt.
    if ($codeBloecke) {
        $text = strtr($text, $codeBloecke);
    }

    return $text;
}

/**
 * Nimmt die Umbrueche weg, die direkt an einem Block kleben.
 *
 * Eine Leerzeile zwischen zwei Absaetzen wird zu <br><br>. Steht daneben ein
 * Code-Kasten, ein Zitat, eine Linie oder eine Liste, bringt der schon eigenen
 * Abstand mit — und beides zusammen ergibt zwei Zeilen Luft zu viel. Sichtbar
 * war das an jedem Beitrag mit Code, nur hat es niemand benannt.
 *
 * Der Umbruch wird nur DIREKT daneben entfernt, nicht mitten im Absatz. Und
 * Inline-Code faellt nicht darunter: der traegt \x02INLINE, nicht \x02CODE.
 */
function bb_umbrueche_an_bloecken_kuerzen(string $html): string {
    $br  = '(?:<br\s*/?>\s*)+';
    $auf = '(?:\x02CODE\d+\x02|<blockquote\b|<hr\b|<ul\b|<ol\b|<details\b|<h2\b|<h3\b)';
    $zu  = '(?:\x02CODE\d+\x02|</blockquote>|<hr class="bb-hr">|</ul>|</ol>|</details>|</h2>|</h3>)';

    $html = preg_replace('#' . $br . '(?=' . $auf . ')#i', '', $html) ?? $html;
    $html = preg_replace('#(' . $zu . ')\s*' . $br . '#i', '$1', $html) ?? $html;
    return $html;
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
        // 3. Bild einer Gruppe. Nach den festen Rollen, damit ein Moderator
        // sein Rollenbild behaelt, auch wenn er nebenbei in einer Gruppe mit
        // eigenem Bild steht. Unter mehreren Gruppen gewinnt die vorderste —
        // dieselbe Reihenfolge wie bei den Abzeichen.
        $gruppenBild = gruppen_avatar_url((int) ($user['user_id'] ?? 0));
        if ($gruppenBild !== null) return $gruppenBild;

        // 4. Fallback
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
 * Das Bild der vordersten Gruppe eines Kontos, oder null.
 *
 * Gruppen koennen ein Bild tragen — gedacht fuer Konten, die keine Person
 * sind: ein Bot, der Ankuendigungen schreibt, ein Archiv-Konto. Es tritt an
 * die Stelle des grauen Standard-Avatars, nicht an die der festen Rollen.
 *
 * Das Ergebnis wird je Aufruf gemerkt: In einer Themenseite steht derselbe
 * Verfasser oft mehrfach, und jedes Mal nachzusehen waere eine Abfrage je
 * Beitrag.
 */
function gruppen_avatar_url(int $userId): ?string
{
    static $gemerkt = [];
    if ($userId <= 0) return null;
    if (array_key_exists($userId, $gemerkt)) return $gemerkt[$userId];

    try {
        $z = db_fetch('SELECT g.avatar FROM group_members m
                       JOIN user_groups g ON g.group_id = m.group_id
                       WHERE m.user_id = ? AND g.avatar IS NOT NULL AND g.avatar <> \'\'
                       ORDER BY g.position, g.name LIMIT 1', [$userId]);
    } catch (Exception $e) {
        // Spalte fehlt (Migration v31 nicht gefahren): dann eben kein Bild.
        return $gemerkt[$userId] = null;
    }

    // Aus UPLOAD_DIR abgeleitet, nicht als eigene Konstante: die config.php
    // bestehender Installationen kennt sie nicht, und dort etwas zu ergaenzen
    // hiesse, jede einzelne anzufassen.
    if (!$z || !is_file(rtrim(UPLOAD_DIR, '/') . '/gruppen/' . basename((string) $z['avatar']))) {
        return $gemerkt[$userId] = null;
    }
    return $gemerkt[$userId] = FORUM_URL . '/uploads/gruppen/' . rawurlencode((string) $z['avatar']);
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
