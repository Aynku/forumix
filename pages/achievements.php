<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// achievements.php
require_once __DIR__ . '/../bootstrap.php';
require_login();

$cu       = current_user();
$showUser = get_int('user', (int) $cu['user_id']);
$profile  = db_fetch('SELECT * FROM users WHERE user_id = ?', [$showUser]);
if (!$profile) { http_response_code(404); die('User nicht gefunden.'); }

check_achievements((int) $profile['user_id']);

$overview = user_achievements_overview((int) $profile['user_id']);
$earned   = array_filter($overview, fn($a) => $a['earned']);
$total    = count($overview);
$points   = user_total_points((int) $profile['user_id']);

render('achievements', [
    'title'    => '🏆 Achievements',
    'profile'  => $profile,
    'overview' => $overview,
    'earned'   => $earned,
    'total'    => $total,
    'points'   => $points,
]);
