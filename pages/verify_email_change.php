<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
/**
 * verify_email_change.php – Bestätigt die neue E-Mail-Adresse via Token-Link.
 *
 * Wird vom User durch Klick auf den Link in der Verifikations-Mail aufgerufen.
 * Kein Login erforderlich — der Token authentifiziert.
 */
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../lib/email_change.php';

$token = trim($_GET['token'] ?? '');
$result = ['ok' => false, 'error' => t('verify_no_token')];

if ($token !== '') {
    $result = email_change_verify($token);
}

render('verify_email_change', [
    'title'     => t('verify_email_change_title'),
    'result'    => $result,
    'breadcrumb' => [],
]);
