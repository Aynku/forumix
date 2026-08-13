<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// reset.php – Passwort mit Token zurücksetzen
require_once __DIR__ . '/../bootstrap.php';

$token = trim($_GET['token'] ?? '');
$reset = $token ? verify_reset_token($token) : null;
$error = '';
$done  = false;

if (!$reset) {
    render('reset', ['title' => 'Link ungültig', 'invalid' => true, 'error' => '', 'done' => false, 'token' => '']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf(post_str('csrf'))) die('CSRF');

    $pw  = post_str('password');
    $pw2 = post_str('password2');
    $min = (int) get_setting('min_password_length', 6);

    if (mb_strlen($pw) < $min) {
        $error = 'Passwort mindestens ' . $min . ' Zeichen.';
    } elseif ($pw !== $pw2) {
        $error = 'Passwörter stimmen nicht überein.';
    } else {
        $hash = password_hash($pw, PASSWORD_BCRYPT, ['cost' => HASH_COST]);
        db_execute('UPDATE users SET password = ? WHERE user_id = ?', [$hash, $reset['user_id']]);
        // Sicherheit: alle bestehenden Remember-Tokens dieses Users widerrufen
        try { remember_destroy_all_for_user((int) $reset['user_id']); } catch (Exception $e) {}
        consume_reset_token($token);
        $done = true;
    }
}

render('reset', [
    'title'   => 'Passwort zurücksetzen',
    'invalid' => false,
    'error'   => $error,
    'done'    => $done,
    'token'   => $token,
    'username' => $reset['username'],
]);
