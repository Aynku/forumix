<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// lib/reactions.php

/**
 * Verfügbare Reaktionstypen
 */
function reaction_types(): array {
    return [
        'like'   => ['👍', 'Gefällt mir'],
        'love'   => ['❤️', 'Liebe'],
        'haha'   => ['😄', 'Lustig'],
        'wow'    => ['😮', 'Wow'],
        'sad'    => ['😢', 'Traurig'],
        'thanks' => ['🙏', 'Danke'],
    ];
}

/**
 * Toggle: bereits gesetzt → entfernen, sonst hinzufügen.
 * Return: 'added' | 'removed' | 'invalid'
 */
function toggle_reaction(int $postId, int $userId, string $type): string {
    if (!isset(reaction_types()[$type])) return 'invalid';

    try {
        $exists = db_count(
            'SELECT COUNT(*) FROM reactions WHERE post_id = ? AND user_id = ? AND type = ?',
            [$postId, $userId, $type]
        );

        if ($exists > 0) {
            db_execute(
                'DELETE FROM reactions WHERE post_id = ? AND user_id = ? AND type = ?',
                [$postId, $userId, $type]
            );
            return 'removed';
        }

        db_execute(
            'INSERT INTO reactions (post_id, user_id, type) VALUES (?, ?, ?)',
            [$postId, $userId, $type]
        );

        // Achievement-Check
        try { check_achievements($userId); } catch (Exception $e) {}

        // Auch den Autor benachrichtigen / achievements
        $post = db_fetch('SELECT user_id FROM posts WHERE post_id = ?', [$postId]);
        if ($post && $post['user_id'] != $userId) {
            try { check_achievements((int) $post['user_id']); } catch (Exception $e) {}
        }

        return 'added';
    } catch (Exception $e) {
        return 'invalid';
    }
}

/**
 * Reaktionen pro Post: gruppiert nach Typ mit Count und ob current_user reagiert hat
 */
function post_reactions(int $postId, ?int $userId = null): array {
    try {
        $rows = db_fetchAll(
            'SELECT type, COUNT(*) AS cnt FROM reactions WHERE post_id = ? GROUP BY type',
            [$postId]
        );
        $userReactions = [];
        if ($userId) {
            $u = db_fetchAll(
                'SELECT type FROM reactions WHERE post_id = ? AND user_id = ?',
                [$postId, $userId]
            );
            $userReactions = array_column($u, 'type');
        }
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'type'  => $r['type'],
                'count' => (int) $r['cnt'],
                'by_me' => in_array($r['type'], $userReactions, true),
            ];
        }
        return $out;
    } catch (Exception $e) { return []; }
}

/**
 * Anzahl der erhaltenen Reaktionen eines Users
 */
function user_received_reactions(int $userId): int {
    try {
        return db_count(
            'SELECT COUNT(*) FROM reactions r JOIN posts p ON p.post_id = r.post_id WHERE p.user_id = ?',
            [$userId]
        );
    } catch (Exception $e) { return 0; }
}
