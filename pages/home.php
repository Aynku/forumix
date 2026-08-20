<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// index.php – Startseite
require_once __DIR__ . '/../bootstrap.php';

$rawCats    = db_fetchAll('SELECT * FROM categories ORDER BY position');
$categories = [];

// Erst alle Board-IDs sammeln, dann Mods in einer Query laden
$allBoardIds = [];
foreach ($rawCats as $cat) {
    $boards = db_fetchAll('
        SELECT b.*,
               -- Subquery: jüngster freigegebener Post im Board (robust auch wenn last_post_id NULL ist)
               (SELECT p2.created_at FROM posts p2
                JOIN threads t2 ON t2.thread_id = p2.thread_id
                WHERE t2.board_id = b.board_id
                  AND COALESCE(p2.is_approved, 1) = 1
                  AND COALESCE(t2.is_approved, 1) = 1
                  ' . sichtbar_sql('p2') . sichtbar_sql('t2') . '
                ORDER BY p2.created_at DESC LIMIT 1) AS last_post_date,
               (SELECT p2.thread_id FROM posts p2
                JOIN threads t2 ON t2.thread_id = p2.thread_id
                WHERE t2.board_id = b.board_id
                  AND COALESCE(p2.is_approved, 1) = 1
                  AND COALESCE(t2.is_approved, 1) = 1
                  ' . sichtbar_sql('p2') . sichtbar_sql('t2') . '
                ORDER BY p2.created_at DESC LIMIT 1) AS last_thread_id,
               (SELECT u2.username FROM posts p2
                JOIN threads t2 ON t2.thread_id = p2.thread_id
                JOIN users u2   ON u2.user_id   = p2.user_id
                WHERE t2.board_id = b.board_id
                  AND COALESCE(p2.is_approved, 1) = 1
                  AND COALESCE(t2.is_approved, 1) = 1
                  ' . sichtbar_sql('p2') . sichtbar_sql('t2') . '
                ORDER BY p2.created_at DESC LIMIT 1) AS last_username,
               (SELECT p2.user_id FROM posts p2
                JOIN threads t2 ON t2.thread_id = p2.thread_id
                WHERE t2.board_id = b.board_id
                  AND COALESCE(p2.is_approved, 1) = 1
                  AND COALESCE(t2.is_approved, 1) = 1
                  ' . sichtbar_sql('p2') . sichtbar_sql('t2') . '
                ORDER BY p2.created_at DESC LIMIT 1) AS last_user_id,
               (SELECT t2.title FROM posts p2
                JOIN threads t2 ON t2.thread_id = p2.thread_id
                WHERE t2.board_id = b.board_id
                  AND COALESCE(p2.is_approved, 1) = 1
                  AND COALESCE(t2.is_approved, 1) = 1
                  ' . sichtbar_sql('p2') . sichtbar_sql('t2') . '
                ORDER BY p2.created_at DESC LIMIT 1) AS last_thread_title
        FROM boards b
        WHERE b.cat_id = ? ' . board_filter_sql('b') . '
        ORDER BY b.position
    ', [$cat['cat_id']]);

    foreach ($boards as $b) $allBoardIds[] = (int) $b['board_id'];

    // Last-Post für passwort-geschützte Foren maskieren (für nicht-berechtigte User)
    foreach ($boards as &$b) {
        if (!empty($b['password_hash']) && !board_user_can_access($b)) {
            // Titel des letzten Threads maskieren; Username + Datum bleiben
            $b['last_thread_title'] = '••••••••••';
        }
    }
    unset($b);

    // Eine Kategorie, in der nichts mehr sichtbar ist, wird nicht gezeichnet.
    // Sonst bliebe die Ueberschrift "Hosting" ueber einer leeren Flaeche stehen
    // und verriete genau das, was das Verstecken verhindern soll.
    if (empty($boards)) continue;

    $cat['boards'] = $boards;
    $categories[]  = $cat;
}

$boardMods = board_moderators_map($allBoardIds);

// Modul-Hook 'home_top' (z.B. Shoutbox) als HTML einfangen: render_module_hook()
// gibt void zurück und echo't direkt – die Template-Engine kann eine void-Funktion
// nicht einbinden, daher hier puffern und als fertigen String ans Template geben.
ob_start();
render_module_hook('home_top');
$homeTopHtml = ob_get_clean();

// Online-Liste einmal holen und die „benannten" Nutzer (mit user_id) vorfiltern –
// die Engine kann kein array_filter() mit Closure. Komma-Trennung passiert im
// Template über loop.first.
$onlineList  = online_users();
$namedOnline = array_values(array_filter($onlineList, fn($u) => !empty($u['user_id'])));

render('index', [
    'title'         => 'Startseite',
    'categories'    => $categories,
    'boardMods'     => $boardMods,
    'showStatsBox'  => true,
    'homeTopHtml'   => $homeTopHtml,
    'onlineList'    => $onlineList,
    'namedOnline'   => $namedOnline,
    'onlineStats'   => online_stats(),
    'forumStats'    => [
        // Geloeschte Konten zaehlen nicht mit und werden erst recht nicht als
        // "Neuestes Mitglied" beworben. Fuer den Leser ist ein anonymisiertes
        // Konto kein Mitglied mehr — die Beitraege bleiben ja trotzdem stehen.
        'members' => db_count('SELECT COUNT(*) FROM users WHERE COALESCE(is_deleted, 0) = 0'),
        'threads' => db_count('SELECT COUNT(*) FROM threads WHERE 1 = 1' . sichtbar_sql()),
        'posts'   => db_count('SELECT COUNT(*) FROM posts WHERE 1 = 1' . sichtbar_sql()),
        'newest'  => db_fetch('SELECT user_id, username, registered_at FROM users
                               WHERE COALESCE(is_deleted, 0) = 0 AND COALESCE(is_banned, 0) = 0
                               ORDER BY registered_at DESC LIMIT 1'),
    ],
]);
