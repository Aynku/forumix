<?php
// api/quicksearch.php – Schnellsuche-Endpunkt
define('FORUM_RUNNING', true);
require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

$query = trim($_GET['q'] ?? '');

// Minimum 2 Zeichen
if (mb_strlen($query) < 2) {
    echo json_encode(['threads' => [], 'users' => [], 'query' => $query]);
    exit;
}

// Anti-DOS: Query auf 100 Zeichen begrenzen
$query = mb_substr($query, 0, 100);
$like  = '%' . $query . '%';

try {
    // Threads suchen (Top 5), passwort-geschützte Foren raus
    $threads = db_fetchAll('
        SELECT t.thread_id, t.title, t.reply_count, t.view_count,
               b.title AS board_title, b.board_id,
               u.username AS author
        FROM threads t
        JOIN boards b ON b.board_id = t.board_id
        JOIN users u  ON u.user_id  = t.user_id
        WHERE t.title LIKE ?
          AND COALESCE(t.is_approved, 1) = 1' . sichtbar_sql('t') . board_filter_sql('t', 'board_id') . '
        ORDER BY t.is_sticky DESC, t.thread_id DESC
        LIMIT 5
    ', [$like]);

    // User suchen (Top 3)
    $users = db_fetchAll('
        SELECT user_id, username, avatar, post_count, `rank`, is_admin, is_mod, is_banned
        FROM users
        WHERE username LIKE ?
        ORDER BY post_count DESC
        LIMIT 3
    ', [$like]);

    // User-Daten anreichern
    $usersOut = [];
    foreach ($users as $u) {
        $usersOut[] = [
            'user_id'    => (int) $u['user_id'],
            'username'   => $u['username'],
            'avatar_url' => avatar_url($u),
            'post_count' => (int) $u['post_count'],
            'badge'      => $u['is_banned'] ? 'banned'
                          : ($u['is_admin'] ? 'admin'
                          : ($u['is_mod']   ? 'mod' : '')),
            'rank'       => $u['rank'],
        ];
    }

    // Thread-Daten anreichern
    $threadsOut = [];
    foreach ($threads as $t) {
        $threadsOut[] = [
            'thread_id'    => (int) $t['thread_id'],
            'title'        => $t['title'],
            'board_title'  => $t['board_title'],
            'board_id'     => (int) $t['board_id'],
            'author'       => $t['author'],
            'reply_count'  => (int) $t['reply_count'],
            'view_count'   => (int) $t['view_count'],
        ];
    }

    echo json_encode([
        'query'       => $query,
        'threads'     => $threadsOut,
        'users'       => $usersOut,
        'has_results' => !empty($threadsOut) || !empty($usersOut),
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Suchfehler']);
}
