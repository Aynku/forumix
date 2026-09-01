<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// verify.php – E-Mail-Verifizierungs-Link
require_once __DIR__ . '/../bootstrap.php';

$token = trim($_GET['token'] ?? '');
$ok    = $token ? activate_email_verification($token) : false;

render('verify', [
    'title' => $ok ? t('verify_ok_title') : t('verify_failed_title'),
    'ok'    => $ok,
]);
