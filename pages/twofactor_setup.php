<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// twofactor_setup.php – seit v0.18.28 nur noch Redirect.
// 2FA-Setup ist in /settings.php?tab=security integriert.
require_once __DIR__ . '/../bootstrap.php';
require_login();
redirect(FORUM_URL . '/settings.php?tab=security');
