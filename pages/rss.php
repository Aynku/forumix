<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// rss.php – RSS-Feeds für Forum/Board/Thread
require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/rss+xml; charset=UTF-8');

$type = $_GET['type'] ?? 'forum';
$id   = get_int('id');

$feedTitle = FORUM_NAME;
$feedDesc  = 'Neueste Beiträge';
$feedLink  = FORUM_URL;
$items     = [];

if ($type === 'board' && $id > 0) {
    $board = db_fetch('SELECT * FROM boards WHERE board_id = ?', [$id]);
    if ($board && !board_user_can_access($board)) {
        // Geschütztes Board → kein RSS für Nicht-Berechtigte
        http_response_code(403);
        die('🔒 Dieses Forum ist passwort-geschützt. RSS nicht verfügbar.');
    }
    if ($board) {
        $feedTitle = FORUM_NAME . ' – ' . $board['title'];
        $feedDesc  = $board['description'] ?: 'Neueste Threads';
        $feedLink  = FORUM_URL . '/board.php?id=' . $id;
        $items = db_fetchAll('
            SELECT t.thread_id, t.title, t.created_at, u.username, p.content AS first_post
            FROM threads t
            JOIN users u ON u.user_id = t.user_id
            LEFT JOIN posts p ON p.thread_id = t.thread_id
            WHERE t.board_id = ?
            GROUP BY t.thread_id
            ORDER BY t.created_at DESC
            LIMIT 20
        ', [$id]);
    }
} elseif ($type === 'thread' && $id > 0) {
    $thread = db_fetch('SELECT * FROM threads WHERE thread_id = ?', [$id]);
    if ($thread) {
        // Auch hier: Board-Zugang prüfen
        $tBoard = db_fetch('SELECT board_id, password_hash FROM boards WHERE board_id = ?', [$thread['board_id']]);
        if ($tBoard && !board_user_can_access($tBoard)) {
            http_response_code(403);
            die('🔒 Dieses Forum ist passwort-geschützt. RSS nicht verfügbar.');
        }
        $feedTitle = FORUM_NAME . ' – ' . $thread['title'];
        $feedDesc  = 'Beiträge im Thread';
        $feedLink  = FORUM_URL . '/thread.php?id=' . $id;
        $items = db_fetchAll('
            SELECT p.post_id AS thread_id, t.title, p.created_at, u.username, p.content AS first_post
            FROM posts p
            JOIN threads t ON t.thread_id = p.thread_id
            JOIN users u   ON u.user_id   = p.user_id
            WHERE p.thread_id = ?
            ORDER BY p.created_at DESC
            LIMIT 20
        ', [$id]);
    }
} else {
    // Forum-weit: alle neuen Threads – aber Threads aus geschützten Foren ausschließen
    $boardFilter = board_filter_sql('t', 'board_id');
    $items = db_fetchAll('
        SELECT t.thread_id, t.title, t.created_at, u.username, p.content AS first_post,
               b.title AS board_title
        FROM threads t
        JOIN users u   ON u.user_id   = t.user_id
        JOIN boards b  ON b.board_id  = t.board_id
        LEFT JOIN posts p ON p.thread_id = t.thread_id
        WHERE 1=1 ' . $boardFilter . '
        GROUP BY t.thread_id
        ORDER BY t.created_at DESC
        LIMIT 20
    ');
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<rss version="2.0">
<channel>
  <title><?= htmlspecialchars($feedTitle, ENT_XML1) ?></title>
  <link><?= htmlspecialchars($feedLink, ENT_XML1) ?></link>
  <description><?= htmlspecialchars($feedDesc, ENT_XML1) ?></description>
  <language>de-DE</language>
  <lastBuildDate><?= date(DATE_RSS) ?></lastBuildDate>
  <?php foreach ($items as $item):
    $link = FORUM_URL . '/thread.php?id=' . (int) $item['thread_id'];
    $excerpt = mb_substr(strip_tags($item['first_post'] ?? ''), 0, 300);
  ?>
  <item>
    <title><?= htmlspecialchars($item['title'], ENT_XML1) ?></title>
    <link><?= htmlspecialchars($link, ENT_XML1) ?></link>
    <guid isPermaLink="true"><?= htmlspecialchars($link, ENT_XML1) ?></guid>
    <pubDate><?= date(DATE_RSS, strtotime($item['created_at'])) ?></pubDate>
    <author><?= htmlspecialchars($item['username'] ?? '', ENT_XML1) ?></author>
    <description><![CDATA[<?= $excerpt ?>]]></description>
  </item>
  <?php endforeach; ?>
</channel>
</rss>
