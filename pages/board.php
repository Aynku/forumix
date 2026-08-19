<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// board.php
require_once __DIR__ . '/../bootstrap.php';

$boardId = get_int('id');
$board = db_fetch('
    SELECT b.*, c.title AS cat_title
    FROM boards b JOIN categories c ON c.cat_id = b.cat_id
    WHERE b.board_id = ?
', [$boardId]);

if (!$board) {
    http_response_code(404);
    error_page(404, 'Forum nicht gefunden', 'Dieses Forum gibt es nicht.');
}

// Kein Zugang? Dann haengt es am Passwort oder an der Gruppe.
if (!board_user_can_access($board)) {
    // Fehlt die Gruppe, verhaelt sich das Forum wie nicht vorhanden. Eine
    // Weiterleitung zur Passwortseite wuerde verraten, dass es existiert, und
    // ein Eingabefeld anbieten, das hier nichts oeffnen kann.
    if (board_zugangsgrund($board) === 'gruppe') {
        http_response_code(404);
        error_page(404, 'Forum nicht gefunden', 'Dieses Forum gibt es nicht.');
    }
    redirect(FORUM_URL . '/board_unlock.php?id=' . $boardId);
}

// Approval-Filter
$cu = current_user();
$canSeeAllThreads = $cu && ($cu['is_admin'] || $cu['is_mod'] || !empty($cu['is_owner']) || is_mod_for_board((int) $cu['user_id'], (int) $boardId));
$cuId = $cu ? (int) $cu['user_id'] : 0;

if ($canSeeAllThreads) {
    $approvalFilter = '';
    $approvalParams = [];
} else {
    $approvalFilter = ' AND (COALESCE(t.is_approved, 1) = 1 OR t.user_id = ?)';
    $approvalParams = [$cuId];
}

$page  = max(1, get_int('page', 1));
$total = db_count(
    'SELECT COUNT(*) FROM threads t WHERE t.board_id = ?' . $approvalFilter,
    array_merge([$boardId], $approvalParams)
);
$pag   = paginate($total, THREADS_PER_PAGE, $page);

$threads = db_fetchAll(
    'SELECT t.*, u.username AS author,
            p.created_at   AS last_post_date,
            pu.username    AS last_poster,
            pu.user_id     AS last_poster_id,
            COALESCE(t.is_approved, 1) AS is_approved
     FROM threads t
     JOIN users u       ON u.user_id  = t.user_id
     LEFT JOIN posts p  ON p.post_id  = t.last_post_id
     LEFT JOIN users pu ON pu.user_id = p.user_id
     WHERE t.board_id = ?' . $approvalFilter . '
     ORDER BY t.is_sticky DESC, COALESCE(p.created_at, t.created_at) DESC
     LIMIT ? OFFSET ?',
    array_merge([$boardId], $approvalParams, [$pag['per_page'], $pag['offset']])
);

// Ungelesen-Status für eingeloggte User
$unreadStatus = [];
if (current_user() && !empty($threads)) {
    $threadIds = array_map(fn($t) => (int) $t['thread_id'], $threads);
    $unreadStatus = unread_status_for_threads((int) current_user()['user_id'], $threadIds);
}

// Ohne Schreibrecht wird der Knopf gar nicht erst gezeichnet — er fuehrte
// sonst auf eine Absage.
$darfSchreiben = board_can_write($boardId);

render('board', [
    'darfSchreiben' => $darfSchreiben,
    'title'      => $board['title'],
    'board'      => $board,
    'threads'    => $threads,
    'unreadStatus' => $unreadStatus,
    'pagination' => pagination_html($pag, FORUM_URL . '/board.php?id=' . $boardId),
    'breadcrumb' => [
        ['title' => $board['cat_title'], 'url' => FORUM_URL . '/index.php'],
        ['title' => $board['title'],     'url' => FORUM_URL . '/board.php?id=' . $boardId],
    ],
]);
