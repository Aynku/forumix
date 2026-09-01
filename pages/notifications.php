<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// notifications.php
require_once __DIR__ . '/../bootstrap.php';
require_login();

$cu = current_user();

// Einzelne Benachrichtigung löschen
if (isset($_GET['delete'])) {
    if (verify_csrf($_GET['csrf'] ?? '')) {
        delete_notification((int) $cu['user_id'], (int) $_GET['delete']);
        flash(t('notification_deleted'), 'success');
    }
    redirect(FORUM_URL . '/notifications.php');
}

// Alle gelesenen löschen
if (isset($_GET['delete_read'])) {
    if (verify_csrf($_GET['csrf'] ?? '')) {
        $n = delete_all_notifications((int) $cu['user_id'], true);
        flash(tn('notifications_read_deleted', (int) $n), 'success');
    }
    redirect(FORUM_URL . '/notifications.php');
}

// Alle löschen
if (isset($_GET['delete_all'])) {
    if (verify_csrf($_GET['csrf'] ?? '')) {
        $n = delete_all_notifications((int) $cu['user_id'], false);
        flash(tn('notifications_deleted', (int) $n), 'success');
    }
    redirect(FORUM_URL . '/notifications.php');
}

if (isset($_GET['mark_all'])) {
    if (verify_csrf($_GET['csrf'] ?? '')) {
        mark_notifications_read((int) $cu['user_id']);
    }
    redirect(FORUM_URL . '/notifications.php');
}

if (isset($_GET['read'])) {
    // Auch der Lese-Sprung braucht das Token: sonst markiert ein fremdes
    // <img src="…/notifications.php?read=1"> die Benachrichtigung still ab.
    if (!verify_csrf($_GET['csrf'] ?? '')) {
        redirect(FORUM_URL . '/notifications.php');
    }
    $nid = (int) $_GET['read'];
    mark_notifications_read((int) $cu['user_id'], $nid);
    // direkt zur Quelle springen
    $n = db_fetch('SELECT * FROM notifications WHERE notif_id = ? AND user_id = ?', [$nid, $cu['user_id']]);
    if ($n) {
        if ($n['type'] === 'report') {
            // Admins → Admin-Bereich. Alle anderen Moderatoren (globale, Owner, Board-Mods) → Mod-Bereich.
            if (is_admin()) {
                redirect(FORUM_URL . '/admin/?action=reports');
            }
            $boardModFor = mod_for_boards((int) $cu['user_id']);
            if (is_mod() || !empty($boardModFor)) {
                redirect(FORUM_URL . '/mod.php?action=reports');
            }
            // Sonst: kein Zugriff – einfach zur Notifications-Übersicht
        }
        if ($n['type'] === 'warning') {
            redirect(FORUM_URL . '/profile.php?id=' . $cu['user_id']);
        }
        if ($n['thread_id']) redirect(FORUM_URL . '/thread.php?id=' . $n['thread_id'] . '#post' . (int) $n['post_id']);
        if ($n['pm_id'])     redirect(FORUM_URL . '/pm.php?action=read&id=' . $n['pm_id']);
    }
    redirect(FORUM_URL . '/notifications.php');
}

$notifications = user_notifications((int) $cu['user_id'], 50);

render('notifications', [
    'title'         => t('notifications'),
    'notifications' => $notifications,
]);
