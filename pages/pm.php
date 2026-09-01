<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// pm.php
require_once __DIR__ . '/../bootstrap.php';
require_login();

$cu     = current_user();
$action = $_GET['action'] ?? (isset($_GET['new']) ? 'new' : 'inbox');
$error  = '';

// ── NEUE PN ───────────────────────────────────────────────────────
if ($action === 'new' || isset($_GET['new'])) {
    $toId   = get_int('to');
    $toUser = $toId ? db_fetch('SELECT username FROM users WHERE user_id = ?', [$toId]) : null;

    $formReceiver = $toUser ? $toUser['username'] : '';
    $formSubject  = isset($_GET['subject']) ? urldecode($_GET['subject']) : '';
    $formContent  = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verify_csrf(post_str('csrf'))) error_page(400, t('error_csrf_title'), t('error_csrf_text'));

        $recv    = db_fetch('SELECT * FROM users WHERE username = ?', [post_str('receiver')]);
        $subject = post_str('subject');
        $content = post_str('content');

        $formReceiver = post_str('receiver');
        $formSubject  = $subject;
        $formContent  = $content;

        if (!$recv) {
            $error = t('pm_recipient_missing');
        } elseif (!empty($recv['is_deleted'])) {
            $error = t('pm_recipient_deleted');
        } elseif (!empty($recv['is_banned'])) {
            $error = t('pm_recipient_banned');
        } elseif ($recv['user_id'] == $cu['user_id']) {
            $error = t('pm_self');
        } elseif (mb_strlen($subject) < 1) {
            $error = t('pm_subject_missing');
        } elseif (mb_strlen($content) < 2) {
            $error = t('pm_too_short');
        } else {
            $pmId = db_execute('INSERT INTO private_messages (sender_id, receiver_id, subject, content) VALUES (?, ?, ?, ?)',
                [$cu['user_id'], $recv['user_id'], $subject, $content]);
            create_notification((int) $recv['user_id'], 'pm', [
                'from_user_id' => (int) $cu['user_id'],
                'pm_id'        => (int) $pmId,
            ]);
            try { check_achievements((int) $cu['user_id']); } catch (Exception $e) {}
            flash(t('pm_sent'), 'success');
            redirect(FORUM_URL . '/pm.php?action=sent');
        }
    }

    render('pm', [
        'title'        => t('pm_new_title'),
        'pmView'       => 'new',
        'error'        => $error,
        'formReceiver' => $formReceiver,
        'formSubject'  => $formSubject,
        'formContent'  => $formContent,
        'messages'     => [],
        'pm'           => null,
    ]);
    exit;
}

// ── LESEN ─────────────────────────────────────────────────────────
if ($action === 'read') {
    $pmId = get_int('id');
    // Nachricht laden – darf gesehen werden vom Sender ODER Empfänger,
    // jeweils mit Rücksicht auf das passende "gelöscht"-Flag
    $pm = db_fetch('
        SELECT pm.*, u.username AS sender_name, r.username AS receiver_name
        FROM private_messages pm
        JOIN users u ON u.user_id = pm.sender_id
        JOIN users r ON r.user_id = pm.receiver_id
        WHERE pm.pm_id = ?
          AND (
            (pm.receiver_id = ? AND pm.deleted_by_receiver = 0)
            OR
            (pm.sender_id = ? AND pm.deleted_by_sender = 0)
          )
    ', [$pmId, $cu['user_id'], $cu['user_id']]);

    if (!$pm) {
        http_response_code(403);
        error_page(404, t('pm_missing_title'), t('pm_missing_text'));
    }

    // is_read nur setzen wenn der aktuelle User der Empfänger ist
    if ((int) $pm['receiver_id'] === (int) $cu['user_id'] && !$pm['is_read']) {
        db_execute('UPDATE private_messages SET is_read = 1 WHERE pm_id = ?', [$pmId]);
    }

    render('pm', [
        'title'    => t('pm_read_title', $pm['subject']),
        'pmView'   => 'read',
        'pm'       => $pm,
        'messages' => [],
        'error'    => '',
    ]);
    exit;
}

// ── LÖSCHEN ───────────────────────────────────────────────────────
if ($action === 'delete') {
    if (!verify_csrf($_GET['csrf'] ?? '')) error_page(400, t('error_csrf_title'), t('error_csrf_text'));

    $pmId = get_int('id');
    $pm = db_fetch('SELECT sender_id, receiver_id FROM private_messages WHERE pm_id = ?', [$pmId]);

    if (!$pm) {
        flash(t('pm_missing_flash'), 'error');
        redirect(FORUM_URL . '/pm.php');
    }

    // Empfänger oder Sender? Entsprechende Flag setzen
    if ((int) $pm['receiver_id'] === (int) $cu['user_id']) {
        db_execute('UPDATE private_messages SET deleted_by_receiver = 1 WHERE pm_id = ?', [$pmId]);
    } elseif ((int) $pm['sender_id'] === (int) $cu['user_id']) {
        db_execute('UPDATE private_messages SET deleted_by_sender = 1 WHERE pm_id = ?', [$pmId]);
    } else {
        flash(t('error_denied_flash'), 'error');
        redirect(FORUM_URL . '/pm.php');
    }

    // Wenn BEIDE gelöscht haben: Komplett aus DB entfernen
    try {
        db_execute(
            'DELETE FROM private_messages WHERE pm_id = ? AND deleted_by_sender = 1 AND deleted_by_receiver = 1',
            [$pmId]
        );
    } catch (Exception $e) {}

    flash(t('pm_deleted'), 'success');

    // Zurück wohin (Inbox oder Gesendet)?
    $back = $_GET['from'] ?? '';
    redirect(FORUM_URL . '/pm.php' . ($back === 'sent' ? '?action=sent' : ''));
}

// ── INBOX / GESENDET ──────────────────────────────────────────────
$isSent = $action === 'sent';
if ($isSent) {
    $messages = db_fetchAll('
        SELECT pm.*, u.username AS other_name, u.user_id AS other_id
        FROM private_messages pm JOIN users u ON u.user_id = pm.receiver_id
        WHERE pm.sender_id = ? AND pm.deleted_by_sender = 0
        ORDER BY pm.sent_at DESC
    ', [$cu['user_id']]);
} else {
    $messages = db_fetchAll('
        SELECT pm.*, u.username AS other_name, u.user_id AS other_id
        FROM private_messages pm JOIN users u ON u.user_id = pm.sender_id
        WHERE pm.receiver_id = ? AND pm.deleted_by_receiver = 0
        ORDER BY pm.sent_at DESC
    ', [$cu['user_id']]);
}

render('pm', [
    'title'    => $isSent ? t('pm_sent_title') : t('pm_inbox'),
    'pmView'   => $isSent ? 'sent' : 'inbox',
    'messages' => $messages,
    'pm'       => null,
    'error'    => '',
]);
