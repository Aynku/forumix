<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// profile.php – Reine Anzeige-Seite (Read-Only)
// Bearbeitung läuft komplett über /settings.php (seit v0.18.27)
require_once __DIR__ . '/../bootstrap.php';

$userId  = get_int('id');
$profile = db_fetch('SELECT * FROM users WHERE user_id = ?', [$userId]);
if (!$profile) {
    http_response_code(404);
    die('Benutzer nicht gefunden.');
}

// Gelöschte User: minimale Profilseite
if (!empty($profile['is_deleted'])) {
    render('profile_deleted', [
        'title'   => $profile['username'],
        'profile' => $profile,
    ]);
    exit;
}

$cu      = current_user();
$isOwner = $cu && $cu['user_id'] === $profile['user_id'];

// Alte ?edit=1-URLs auf neue Settings-Seite umleiten (Bookmark-Kompatibilität)
if ($isOwner && isset($_GET['edit'])) {
    redirect(FORUM_URL . '/settings.php');
}

// IP-Verlauf für Mods/Admins
$ipHistory = [];
if (is_mod() && (string) get_setting('show_ips_to_mods', '1') === '1') {
    $ipHistory = db_fetchAll('
        SELECT ip, action, MAX(created_at) AS last_seen, COUNT(*) AS hits
        FROM user_ip_log
        WHERE user_id = ?
        GROUP BY ip, action
        ORDER BY last_seen DESC
        LIMIT 50
    ', [$userId]);
}

$latestPosts = db_fetchAll('
    SELECT p.post_id, p.created_at, t.title AS thread_title, t.thread_id
    FROM posts p JOIN threads t ON t.thread_id = p.thread_id
    WHERE p.user_id = ? ' . board_filter_sql('t', 'board_id') . '
    ORDER BY p.created_at DESC
    LIMIT 10
', [$userId]);

// v0.18.50: Erweiterte Stats für die Aktivitäts-Übersicht
$extendedStats = [
    'threads_count'    => user_threads_count($userId),
    'reactions_given'  => user_given_reactions($userId),
    'posts_last_30d'   => user_posts_in_last_days($userId, 30),
    'activity_level'   => user_activity_level($userId),
];
$topThreads = user_top_threads($userId, 3);

render('profile', [
    'title'            => 'Profil: ' . $profile['username'],
    'profile'          => $profile,
    'latestPosts'      => $latestPosts,
    'extendedStats'    => $extendedStats,
    'topThreads'       => $topThreads,
    'isOwner'          => $isOwner,
    'ipHistory'        => $ipHistory,
    'breadcrumb'       => [
        ['title' => $profile['username'], 'url' => FORUM_URL . '/profile.php?id=' . $userId],
    ],
]);
