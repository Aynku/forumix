<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
/**
 * unsubscribe.php – 1-Klick-Abmeldung von Thread-Benachrichtigungen
 *
 * Wird aus E-Mail-Footer aufgerufen mit ?token=...
 * Verifiziert HMAC-Token und entfernt das Abo.
 *
 * Funktioniert auch wenn User NICHT eingeloggt ist (deshalb Token-basiert).
 * Kein CSRF nötig da der Token selbst als Authentifizierung dient.
 */
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../lib/unsubscribe_token.php';

$token = trim($_GET['token'] ?? '');
$status = 'invalid';
$threadTitle = '';

if ($token !== '') {
    $verified = unsubscribe_token_verify($token);
    if ($verified !== null) {
        $userId   = $verified['user_id'];
        $threadId = $verified['thread_id'];

        try {
            // Thread-Titel für Anzeige holen (auch wenn DELETE darunter fehlschlägt)
            $thread = db_fetch('SELECT title FROM threads WHERE thread_id = ?', [$threadId]);
            if ($thread) $threadTitle = (string) $thread['title'];

            // Abo entfernen (idempotent — wenn schon weg, kein Fehler)
            db_execute(
                'DELETE FROM thread_subscriptions WHERE user_id = ? AND thread_id = ?',
                [$userId, $threadId]
            );
            $status = 'success';
        } catch (Exception $e) {
            $status = 'error';
        }
    }
}

render('unsubscribe', [
    'title'       => 'Abo-Benachrichtigung entfernt',
    'status'      => $status,
    'threadTitle' => $threadTitle,
    'breadcrumb'  => [],
]);
