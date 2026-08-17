<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// online.php
require_once __DIR__ . '/../bootstrap.php';

$users = online_users();
$stats = online_stats();

// Die IP-Spalte haengt an der Einstellung, nicht allein an der Mod-Rolle —
// so wie es profile.php und thread.php schon machen.
$showIps = is_mod() && (string) get_setting('show_ips_to_mods', '1') === '1';

render('online', [
    'title'   => 'Wer ist online',
    'users'   => $users,
    'stats'   => $stats,
    'showIps' => $showIps,
]);
