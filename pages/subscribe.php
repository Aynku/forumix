<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// subscribe.php
require_once __DIR__ . '/../bootstrap.php';
require_login();
if (!verify_csrf($_GET['csrf'] ?? '')) error_page(400, t('error_csrf_title'), t('error_csrf_text'));

$threadId = (int) ($_GET['thread'] ?? 0);
$action   = $_GET['action'] ?? 'subscribe';
$cu = current_user();

// Ein Abo liefert Titel und Textvorschau spaeter per Mail aus. Wer ein Board
// nicht betreten darf, darf sich auch nicht eintragen — sonst ist das Abo ein
// dauerhafter Kanal aus einem geschuetzten Forum heraus. Abbestellen bleibt
// immer erlaubt, damit ein spaeter geschuetztes Board niemanden festhaelt.
if ($action === 'subscribe') {
    $t = $threadId > 0 ? db_fetch('SELECT board_id FROM threads WHERE thread_id = ?', [$threadId]) : null;
    if (!$t || !board_id_can_access((int) $t['board_id'])) {
        error_page(403, t('error_denied_title'), t('subscribe_board_locked'));
    }
}

try {
    if ($action === 'subscribe') {
        db_execute('INSERT IGNORE INTO thread_subscriptions (user_id, thread_id) VALUES (?, ?)',
            [$cu['user_id'], $threadId]);
        flash(t('subscribe_done'), 'success');
    } else {
        db_execute('DELETE FROM thread_subscriptions WHERE user_id = ? AND thread_id = ?',
            [$cu['user_id'], $threadId]);
        flash(t('subscribe_removed'), 'info');
    }
} catch (Exception $e) {
    flash(t('error_with_message', $e->getMessage()), 'error');
}

redirect(FORUM_URL . '/thread.php?id=' . $threadId);
