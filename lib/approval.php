<?php
// lib/approval.php – Freigabe-System
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }

/**
 * Anzahl der Beiträge die auf Freigabe warten
 */
function pending_approval_count(): int {
    try {
        $threads = db_count('SELECT COUNT(*) FROM threads WHERE COALESCE(is_approved, 1) = 0' . sichtbar_sql());
        $posts   = db_count('SELECT COUNT(*) FROM posts   WHERE COALESCE(is_approved, 1) = 0' . sichtbar_sql());
        return $threads + $posts;
    } catch (Exception $e) { return 0; }
}

/**
 * Alle wartenden Threads mit User-/Board-Daten
 */
function pending_threads(): array {
    try {
        return db_fetchAll('
            SELECT t.thread_id, t.title, t.created_at, t.board_id,
                   u.user_id, u.username, u.avatar,
                   b.title AS board_title,
                   p.post_id, p.content
            FROM threads t
            JOIN users u ON u.user_id = t.user_id
            JOIN boards b ON b.board_id = t.board_id
            LEFT JOIN posts p ON p.thread_id = t.thread_id AND p.user_id = t.user_id
            WHERE COALESCE(t.is_approved, 1) = 0' . sichtbar_sql('t') . '
            ORDER BY t.created_at ASC
        ');
    } catch (Exception $e) { return []; }
}

/**
 * Alle wartenden Antwort-Posts (nicht Thread-Eröffnung)
 */
function pending_posts(): array {
    try {
        return db_fetchAll('
            SELECT p.post_id, p.content, p.created_at, p.thread_id,
                   t.title AS thread_title, t.board_id,
                   u.user_id, u.username, u.avatar,
                   b.title AS board_title
            FROM posts p
            JOIN threads t ON t.thread_id = p.thread_id
            JOIN boards b ON b.board_id = t.board_id
            JOIN users u ON u.user_id = p.user_id
            WHERE COALESCE(p.is_approved, 1) = 0' . sichtbar_sql('p') . '
              AND COALESCE(t.is_approved, 1) = 1
              AND p.post_id != COALESCE((SELECT MIN(post_id) FROM posts WHERE thread_id = t.thread_id), 0)
            ORDER BY p.created_at ASC
        ');
    } catch (Exception $e) { return []; }
}

/**
 * Thread freigeben (samt erstem Post + Counter aktualisieren)
 */
function approve_thread(int $threadId): bool {
    try {
        $t = db_fetch('SELECT * FROM threads WHERE thread_id = ?', [$threadId]);
        if (!$t || !empty($t['is_approved'])) return false;

        $firstPost = db_fetch('SELECT post_id, content FROM posts WHERE thread_id = ? ORDER BY post_id ASC LIMIT 1', [$threadId]);

        DB::get()->beginTransaction();
        db_execute('UPDATE threads SET is_approved = 1 WHERE thread_id = ?', [$threadId]);
        if ($firstPost) {
            db_execute('UPDATE posts SET is_approved = 1 WHERE post_id = ?', [$firstPost['post_id']]);
        }

        // Counter erhöhen
        db_execute('UPDATE boards SET thread_count = thread_count + 1, post_count = post_count + 1, last_post_id = ? WHERE board_id = ?',
            [$firstPost['post_id'] ?? null, $t['board_id']]);
        db_execute('UPDATE users SET post_count = post_count + 1 WHERE user_id = ?', [$t['user_id']]);

        DB::get()->commit();

        // Mentions + Achievements nachträglich
        if ($firstPost) {
            try { process_mentions($firstPost['content'], $threadId, (int) $firstPost['post_id'], (int) $t['user_id']); } catch (Exception $e) {}
        }
        try { update_user_rank((int) $t['user_id']); } catch (Exception $e) {}
        try { check_achievements((int) $t['user_id']); } catch (Exception $e) {}

        // Autor benachrichtigen
        try {
            create_notification((int) $t['user_id'], 'approved', [
                'thread_id' => $threadId,
                'type'      => 'thread',
            ]);
        } catch (Exception $e) {}

        return true;
    } catch (Exception $e) {
        if (DB::get()->inTransaction()) DB::get()->rollBack();
        return false;
    }
}

/**
 * Einzelnen Post freigeben (Antwort)
 */
function approve_post(int $postId): bool {
    try {
        $p = db_fetch('SELECT p.*, t.board_id FROM posts p JOIN threads t ON t.thread_id = p.thread_id WHERE p.post_id = ?', [$postId]);
        if (!$p || !empty($p['is_approved'])) return false;

        DB::get()->beginTransaction();
        db_execute('UPDATE posts SET is_approved = 1 WHERE post_id = ?', [$postId]);
        db_execute('UPDATE threads SET reply_count = reply_count + 1, last_post_id = ? WHERE thread_id = ?',
            [$postId, $p['thread_id']]);
        db_execute('UPDATE boards SET post_count = post_count + 1, last_post_id = ? WHERE board_id = ?',
            [$postId, $p['board_id']]);
        db_execute('UPDATE users SET post_count = post_count + 1 WHERE user_id = ?', [$p['user_id']]);
        DB::get()->commit();

        // Mentions + Subscriber benachrichtigen
        try { process_mentions($p['content'], (int) $p['thread_id'], $postId, (int) $p['user_id']); } catch (Exception $e) {}
        try { notify_thread_subscribers((int) $p['thread_id'], $postId, (int) $p['user_id']); } catch (Exception $e) {}
        try { update_user_rank((int) $p['user_id']); } catch (Exception $e) {}
        try { check_achievements((int) $p['user_id']); } catch (Exception $e) {}

        // Autor benachrichtigen
        try {
            create_notification((int) $p['user_id'], 'approved', [
                'thread_id' => (int) $p['thread_id'],
                'post_id'   => $postId,
                'type'      => 'post',
            ]);
        } catch (Exception $e) {}

        return true;
    } catch (Exception $e) {
        if (DB::get()->inTransaction()) DB::get()->rollBack();
        return false;
    }
}

/**
 * Beitrag ablehnen (löschen + Notification)
 */
function reject_post(int $postId, string $reason = ''): bool {
    try {
        $p = db_fetch('SELECT * FROM posts WHERE post_id = ?', [$postId]);
        if (!$p) return false;

        // Autor benachrichtigen vor dem Löschen
        try {
            create_notification((int) $p['user_id'], 'rejected', [
                'thread_id' => (int) $p['thread_id'],
                'reason'    => $reason,
                'type'      => 'post',
            ]);
        } catch (Exception $e) {}

        delete_attachments_for_posts([$postId]);
        db_execute('DELETE FROM posts WHERE post_id = ?', [$postId]);
        return true;
    } catch (Exception $e) { return false; }
}

function reject_thread(int $threadId, string $reason = ''): bool {
    try {
        $t = db_fetch('SELECT * FROM threads WHERE thread_id = ?', [$threadId]);
        if (!$t) return false;

        try {
            create_notification((int) $t['user_id'], 'rejected', [
                'thread_id' => $threadId,
                'reason'    => $reason,
                'type'      => 'thread',
            ]);
        } catch (Exception $e) {}

        // Erst zugehörige Posts löschen, dann den Thread
        delete_attachments_for_thread($threadId);
        db_execute('DELETE FROM posts WHERE thread_id = ?', [$threadId]);
        db_execute('DELETE FROM threads WHERE thread_id = ?', [$threadId]);
        return true;
    } catch (Exception $e) { return false; }
}

// ═══════════════════════════════════════════════════════════════════════
// USER-APPROVAL (v0.18.24) – Admin-Freigabe für Registrierungen
// Getrennt vom Post/Thread-Approval-System oben
// ═══════════════════════════════════════════════════════════════════════

/**
 * Ist die Admin-Approval-Pflicht für Neuregistrierungen aktiv?
 */
function user_approval_required(): bool {
    try {
        return (string) get_setting('registration_approval_required') === '1';
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Anzahl Benutzer die auf Freigabe warten
 */
function pending_user_approvals_count(): int {
    try {
        return (int) db_count(
            'SELECT COUNT(*) FROM users WHERE COALESCE(is_approved, 1) = 0 AND COALESCE(is_deleted, 0) = 0'
        );
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Liste der Benutzer die auf Freigabe warten
 */
function pending_user_approvals(): array {
    try {
        return db_fetchAll(
            'SELECT user_id, username, email, registered_at, registration_ip, email_verified
             FROM users
             WHERE COALESCE(is_approved, 1) = 0 AND COALESCE(is_deleted, 0) = 0
             ORDER BY registered_at ASC'
        );
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Einen User freigeben (is_approved = 1) und Bestätigungs-Mail senden.
 */
function approve_user(int $userId, int $adminId): bool {
    try {
        $user = db_fetch('SELECT * FROM users WHERE user_id = ?', [$userId]);
        if (!$user) return false;
        if ((int) ($user['is_approved'] ?? 1) === 1) return true;  // bereits freigegeben

        db_execute(
            'UPDATE users SET is_approved = 1, approval_note = NULL WHERE user_id = ?',
            [$userId]
        );

        // Mod-Log
        try {
            db_execute(
                'INSERT INTO mod_log (mod_id, action, target_type, target_id, reason)
                 VALUES (?, ?, ?, ?, ?)',
                [$adminId, 'approve_user', 'user', $userId, t('sys_registrierung_freigegeben')]
            );
        } catch (Exception $e) {}

        // User benachrichtigen
        send_user_approved_email($user);

        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Einen User ablehnen: zuerst E-Mail mit Begründung, dann Account löschen.
 */
function reject_user(int $userId, int $adminId, string $reason): bool {
    try {
        $user = db_fetch('SELECT * FROM users WHERE user_id = ?', [$userId]);
        if (!$user) return false;
        if ((int) ($user['is_approved'] ?? 1) === 1) return false;  // schon freigegeben – kein Reject mehr
        if (!empty($user['is_owner']) || !empty($user['is_admin'])) return false; // Schutz

        // Erst Mail senden (User hat sonst nie eine Chance es zu erfahren)
        send_user_rejected_email($user, $reason);

        // Mod-Log
        try {
            db_execute(
                'INSERT INTO mod_log (mod_id, action, target_type, target_id, reason)
                 VALUES (?, ?, ?, ?, ?)',
                [$adminId, 'reject_user', 'user', $userId, 'Abgelehnt: ' . mb_substr($reason, 0, 200)]
            );
        } catch (Exception $e) {}

        // Account vollständig löschen (Username/E-Mail werden wieder frei)
        try { db_execute('DELETE FROM email_verifications WHERE user_id = ?', [$userId]); } catch (Exception $e) {}
        try { db_execute('DELETE FROM user_ip_log WHERE user_id = ?', [$userId]); } catch (Exception $e) {}
        db_execute('DELETE FROM users WHERE user_id = ?', [$userId]);

        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Sendet allen Admins eine Mail über eine neue Registrierung
 */
function send_admin_new_registration_email(array $newUser): void {
    if (!function_exists('send_email')) return;
    try {
        $admins = db_fetchAll(
            "SELECT email, username FROM users
             WHERE (is_admin = 1 OR is_owner = 1)
               AND is_banned = 0 AND COALESCE(is_deleted, 0) = 0
               AND email IS NOT NULL AND email <> ''"
        );
    } catch (Exception $e) {
        return;
    }
    if (empty($admins)) return;

    $forum    = forum_name();
    $url      = FORUM_URL . '/admin/?action=approvals_users';
    $username = $newUser['username'] ?? '?';
    $email    = $newUser['email'] ?? '?';
    $ip       = $newUser['registration_ip'] ?? '?';
    $when     = date('d.m.Y H:i', strtotime($newUser['registered_at'] ?? 'now'));

    $body = email_template('🆕 Neue Registrierung wartet auf Freigabe',
        '<p>Hallo Admin-Team,</p>'
      . '<p>Ein neuer Account hat sich registriert und wartet auf deine Freigabe:</p>'
      . '<table cellpadding="6" cellspacing="0" style="border-collapse:collapse;margin:14px 0">'
      . '<tr><td style="color:#888;padding-right:20px">Benutzername:</td><td><strong>' . e($username) . '</strong></td></tr>'
      . '<tr><td style="color:#888;padding-right:20px">E-Mail:</td><td>' . e($email) . '</td></tr>'
      . '<tr><td style="color:#888;padding-right:20px">IP-Adresse:</td><td>' . e($ip) . '</td></tr>'
      . '<tr><td style="color:#888;padding-right:20px">Registriert:</td><td>' . e($when) . '</td></tr>'
      . '</table>'
      . '<p><a href="' . e($url) . '" style="display:inline-block;background:#1a1a1a;color:#fff;padding:10px 20px;text-decoration:none;border-radius:6px">'
      . '→ Zur Freigabe-Übersicht</a></p>'
    );

    foreach ($admins as $admin) {
        @send_email($admin['email'], "[{$forum}] Neue Registrierung: {$username}", $body);
    }
}

/**
 * Sendet dem User die "Konto freigeschaltet"-Mail
 */
function send_user_approved_email(array $user): void {
    if (!function_exists('send_email')) return;
    if (empty($user['email'])) return;

    $forum = forum_name();
    $url   = FORUM_URL . '/login.php';

    $body = email_template('✅ Dein Konto wurde freigeschaltet',
        '<p>Hallo <strong>' . e($user['username']) . '</strong>,</p>'
      . '<p>Dein Account auf <strong>' . e($forum) . '</strong> wurde gerade '
      . 'vom Admin-Team freigeschaltet. Du kannst dich jetzt einloggen!</p>'
      . '<p><a href="' . e($url) . '" style="display:inline-block;background:#1a1a1a;color:#fff;padding:10px 20px;text-decoration:none;border-radius:6px">'
      . '→ Jetzt einloggen</a></p>'
      . '<p>Willkommen in der Community! 🎉</p>'
    );

    @send_email($user['email'], "[{$forum}] Konto freigeschaltet", $body);
}

/**
 * Sendet dem abgelehnten User die Begründungs-Mail
 */
function send_user_rejected_email(array $user, string $reason): void {
    if (!function_exists('send_email')) return;
    if (empty($user['email'])) return;

    $forum = forum_name();

    $body = email_template('❌ Deine Registrierung wurde abgelehnt',
        '<p>Hallo <strong>' . e($user['username']) . '</strong>,</p>'
      . '<p>Leider wurde deine Registrierung auf <strong>' . e($forum) . '</strong> '
      . 'vom Admin-Team abgelehnt.</p>'
      . '<p><strong>Begründung:</strong></p>'
      . '<div style="background:#f5f5f5;padding:14px;border-left:3px solid #888;margin:10px 0;color:#444">'
      . nl2br(e($reason))
      . '</div>'
      . '<p style="font-size:12px;color:#888;margin-top:20px">'
      . 'Falls du Fragen dazu hast, kontaktiere bitte das Forum-Team.</p>'
    );

    @send_email($user['email'], "[{$forum}] Registrierung abgelehnt", $body);
}
