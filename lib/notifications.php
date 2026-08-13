<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// lib/notifications.php – Benachrichtigungen

/**
 * Erstellt eine Benachrichtigung. Silent fail wenn die Tabelle nicht existiert,
 * aber Fehler bei unbekanntem Typ werden geloggt (zur Erkennung von fehlenden Migrationen).
 */
function create_notification(int $userId, string $type, array $params = []): void {
    if ($userId <= 0) return;
    try {
        db_execute(
            'INSERT INTO notifications (user_id, type, from_user_id, thread_id, post_id, pm_id)
             VALUES (?, ?, ?, ?, ?, ?)',
            [
                $userId,
                $type,
                $params['from_user_id'] ?? null,
                $params['thread_id']    ?? null,
                $params['post_id']      ?? null,
                $params['pm_id']        ?? null,
            ]
        );
    } catch (PDOException $e) {
        // ENUM-Fehler typischerweise: SQLSTATE 01000 (Data truncated)
        // Loggen aber Forum nicht crashen lassen
        error_log('[create_notification] Konnte Benachrichtigung "' . $type . '" für User ' . $userId .
                  ' nicht speichern: ' . $e->getMessage());
    }
}

function unread_notifications_count(int $userId): int {
    try {
        return db_count('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', [$userId]);
    } catch (Exception $e) { return 0; }
}

function user_notifications(int $userId, int $limit = 20): array {
    try {
        return db_fetchAll('
            SELECT n.*, u.username AS from_username, t.title AS thread_title, pm.subject AS pm_subject
            FROM notifications n
            LEFT JOIN users   u  ON u.user_id = n.from_user_id
            LEFT JOIN threads t  ON t.thread_id = n.thread_id
            LEFT JOIN private_messages pm ON pm.pm_id = n.pm_id
            WHERE n.user_id = ?
            ORDER BY n.created_at DESC
            LIMIT ?
        ', [$userId, $limit]);
    } catch (Exception $e) { return []; }
}

function mark_notifications_read(int $userId, ?int $notifId = null): void {
    try {
        if ($notifId) {
            db_execute('UPDATE notifications SET is_read = 1 WHERE notif_id = ? AND user_id = ?', [$notifId, $userId]);
        } else {
            db_execute('UPDATE notifications SET is_read = 1 WHERE user_id = ?', [$userId]);
        }
    } catch (Exception $e) {}
}

/**
 * Einzelne Benachrichtigung löschen
 */
function delete_notification(int $userId, int $notifId): bool {
    try {
        $stmt = DB::get()->prepare('DELETE FROM notifications WHERE notif_id = ? AND user_id = ?');
        $stmt->execute([$notifId, $userId]);
        return $stmt->rowCount() > 0;
    } catch (Exception $e) { return false; }
}

/**
 * Alle Benachrichtigungen löschen
 */
function delete_all_notifications(int $userId, bool $onlyRead = false): int {
    try {
        if ($onlyRead) {
            $stmt = DB::get()->prepare('DELETE FROM notifications WHERE user_id = ? AND is_read = 1');
            $stmt->execute([$userId]);
        } else {
            $stmt = DB::get()->prepare('DELETE FROM notifications WHERE user_id = ?');
            $stmt->execute([$userId]);
        }
        return $stmt->rowCount();
    } catch (Exception $e) { return 0; }
}

/**
 * Erwähnungen im Text finden (z. B. @benutzername) und benachrichtigen
 */
function process_mentions(string $content, int $threadId, int $postId, int $authorId): void {
    if (!preg_match_all('/(?:^|\s)@([a-zA-Z0-9_äöüÄÖÜß\-]{1,30})\b/u', $content, $matches)) return;
    $unique = array_unique($matches[1]);
    foreach ($unique as $name) {
        $u = db_fetch('SELECT user_id FROM users WHERE username = ?', [$name]);
        if (!$u || $u['user_id'] == $authorId) continue;
        create_notification((int)$u['user_id'], 'mention', [
            'from_user_id' => $authorId,
            'thread_id'    => $threadId,
            'post_id'      => $postId,
        ]);
    }
}

/**
 * Subscribers (Thread-Abonnenten) benachrichtigen
 *
 * 1. In-App-Notification erstellen (Glocke) — immer
 * 2. E-Mail senden, wenn User notify_email = 1 hat
 *
 * Mit 1-Klick-Abmelde-Token im E-Mail-Footer (HMAC-signiert, 30 Tage gültig).
 */
function notify_thread_subscribers(int $threadId, int $postId, int $authorId): void {
    try {
        $subs = db_fetchAll(
            'SELECT u.user_id, u.username, u.email, u.notify_email
             FROM thread_subscriptions ts
             JOIN users u ON u.user_id = ts.user_id
             WHERE ts.thread_id = ? AND ts.user_id != ?',
            [$threadId, $authorId]
        );

        if (empty($subs)) return;

        // Thread + Post + Author einmal holen — gleich für alle Subscribers
        $thread = db_fetch('SELECT thread_id, title FROM threads WHERE thread_id = ?', [$threadId]);
        $post   = db_fetch('SELECT content FROM posts WHERE post_id = ?', [$postId]);
        $author = db_fetch('SELECT username FROM users WHERE user_id = ?', [$authorId]);

        foreach ($subs as $s) {
            // In-App-Notification (immer)
            create_notification((int)$s['user_id'], 'reply', [
                'from_user_id' => $authorId,
                'thread_id'    => $threadId,
                'post_id'      => $postId,
            ]);

            // E-Mail (nur wenn User es will UND wir die Daten haben)
            if (empty($s['notify_email'])) continue;
            if (empty($s['email']))        continue;
            if (!$thread || !$post || !$author) continue;

            try {
                _send_reply_notification_email(
                    (int) $s['user_id'],
                    (string) $s['username'],
                    (string) $s['email'],
                    (string) $thread['title'],
                    (string) $post['content'],
                    (string) $author['username'],
                    (int) $threadId,
                    (int) $postId
                );
            } catch (Exception $e) {
                // Mail-Fail nicht weitergeben — In-App-Notification bleibt
            }
        }
    } catch (Exception $e) {}
}

/**
 * Versendet die E-Mail-Benachrichtigung für eine Thread-Antwort.
 * Wird aus notify_thread_subscribers() heraus aufgerufen.
 */
function _send_reply_notification_email(
    int $userId, string $username, string $email,
    string $threadTitle, string $postContent, string $authorName,
    int $threadId, int $postId
): void {
    if (!function_exists('send_email')) return;
    if (!function_exists('unsubscribe_token_create')) {
        require_once __DIR__ . '/unsubscribe_token.php';
    }

    // Vorschau: 200 Zeichen vom Post-Inhalt, Tags raus
    $preview = strip_tags($postContent);
    $preview = preg_replace('/\s+/', ' ', $preview);
    $preview = trim($preview);
    if (mb_strlen($preview) > 200) {
        $preview = mb_substr($preview, 0, 200) . '…';
    }

    $forumName  = defined('FORUM_NAME') ? FORUM_NAME : 'Forum';
    $threadUrl  = FORUM_URL . '/thread.php?id=' . $threadId . '&page=last#post' . $postId;
    $token      = unsubscribe_token_create($userId, $threadId);
    $unsubUrl   = FORUM_URL . '/unsubscribe.php?token=' . urlencode($token);
    $settingsUrl = FORUM_URL . '/settings.php?tab=notifications';

    $subject = '[' . $forumName . '] Neue Antwort: ' . $threadTitle;

    $bodyInner = '
        <p>Hallo <strong>' . htmlspecialchars($username, ENT_QUOTES, 'UTF-8') . '</strong>,</p>
        <p><strong>' . htmlspecialchars($authorName, ENT_QUOTES, 'UTF-8') . '</strong>
           hat im Thread
           <strong>"' . htmlspecialchars($threadTitle, ENT_QUOTES, 'UTF-8') . '"</strong>
           geantwortet:</p>
        <blockquote style="border-left:3px solid #ddd;padding:8px 14px;color:#555;margin:14px 0;background:#f8f8f8;border-radius:4px">
            ' . htmlspecialchars($preview, ENT_QUOTES, 'UTF-8') . '
        </blockquote>
        <p style="margin-top:18px">
            <a href="' . htmlspecialchars($threadUrl, ENT_QUOTES, 'UTF-8') . '"
               style="display:inline-block;padding:10px 18px;background:#4f46e5;color:#fff;text-decoration:none;border-radius:6px;font-weight:600">
                → Zur Antwort
            </a>
        </p>
        <hr style="border:none;border-top:1px solid #eee;margin:24px 0">
        <p style="font-size:12px;color:#888;line-height:1.6">
            Du bekommst diese Mail weil du den Thread abonniert hast.<br>
            <a href="' . htmlspecialchars($unsubUrl, ENT_QUOTES, 'UTF-8') . '" style="color:#888">
                Mit einem Klick von diesem Thread abmelden
            </a>
            ·
            <a href="' . htmlspecialchars($settingsUrl, ENT_QUOTES, 'UTF-8') . '" style="color:#888">
                Alle E-Mail-Benachrichtigungen verwalten
            </a>
        </p>
    ';

    $body = email_template('Neue Antwort: ' . $threadTitle, $bodyInner);
    send_email($email, $subject, $body, true);
}

/**
 * Liefert das Icon (Emoji) für einen Benachrichtigungs-Typ.
 *
 * Ausgelagert aus views/notifications.php im Zuge der Template-Migration:
 * die Template-Engine kann kein match(), daher lebt die Typ→Präsentation-
 * Zuordnung hier in der Domain-Schicht. Reine Funktion (nur $n['type']).
 */
function notification_icon(array $n): string {
    return match ($n['type'] ?? '') {
        'reply'    => '💬',
        'mention'  => '📌',
        'pm'       => '📨',
        'warning'  => '⚠️',
        'report'   => '🚨',
        'approved' => '✅',
        'rejected' => '🚫',
        'mod_edit' => '✏️',
        default    => '🔔',
    };
}

/**
 * Liefert den fertigen (HTML-sicheren) Anzeigetext einer Benachrichtigung.
 *
 * Ausgelagert aus views/notifications.php (siehe notification_icon). Escaped die
 * Nutzer-/Thread-Felder mit e() und gibt HTML zurück – im Template mit |raw
 * ausgeben. Verhalten 1:1 wie der frühere Inline-match()-Block.
 */
function notification_text(array $n): string {
    $from = e($n['from_username'] ?? 'System');
    $tt   = e($n['thread_title']  ?? '');
    $ps   = e($n['pm_subject']    ?? '');
    return match ($n['type'] ?? '') {
        'reply'    => t('notif_reply',   $from, $tt),
        'mention'  => t('notif_mention', $from, $tt),
        'pm'       => t('notif_pm',      $from, $ps),
        'warning'  => '⚠️ Du hast eine Verwarnung von <strong>' . $from . '</strong> erhalten',
        'report'   => '🚨 Neue Meldung von <strong>' . $from . '</strong>' . ($tt ? ' in „' . $tt . '"' : ''),
        'approved' => '✅ Dein Beitrag' . ($tt ? ' in „' . $tt . '"' : '') . ' wurde <strong>freigegeben</strong>',
        'rejected' => '🚫 Dein Beitrag' . ($tt ? ' in „' . $tt . '"' : '') . ' wurde leider <strong>abgelehnt</strong>',
        'mod_edit' => '✏️ Dein Beitrag' . ($tt ? ' in „' . $tt . '"' : '') . ' wurde von <strong>' . $from . '</strong> bearbeitet',
        default    => 'Neue Benachrichtigung',
    };
}
