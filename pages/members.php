<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// members.php – Mitglieder-Übersicht mit Team & allen Mitgliedern
require_once __DIR__ . '/../bootstrap.php';

$page    = max(1, get_int('page', 1));
$perPage = 30;

$sort = $_GET['sort'] ?? 'posts';
$sortMap = [
    'posts'    => 'post_count DESC',
    'newest'   => 'registered_at DESC',
    'oldest'   => 'registered_at ASC',
    'name'     => 'username ASC',
    'active'   => 'last_login DESC',
];
$orderBy = $sortMap[$sort] ?? $sortMap['posts'];

$team        = team_members();
$totalUsers  = db_count('SELECT COUNT(*) FROM users WHERE is_banned = 0 AND COALESCE(is_deleted, 0) = 0');
$pag         = paginate($totalUsers, $perPage, $page);

$allMembers = db_fetchAll("
    SELECT user_id, username, avatar, post_count, registered_at, last_login,
           rank, is_admin, is_mod, COALESCE(is_owner, 0) AS is_owner
    FROM users
    WHERE is_banned = 0 AND COALESCE(is_deleted, 0) = 0
    ORDER BY $orderBy
    LIMIT ? OFFSET ?
", [$perPage, $pag['offset']]);

render('members', [
    'title'      => 'Mitglieder',
    'team'       => $team,
    'members'    => $allMembers,
    'pag'        => $pag,
    'pagination' => pagination_html($pag, FORUM_URL . '/members.php?sort=' . urlencode($sort)),
    'sort'       => $sort,
    'totalUsers' => $totalUsers,
]);
