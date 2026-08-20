<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// lib/board_mods.php – Forum-spezifische Moderatoren

/**
 * Liste der Mods für ein bestimmtes Forum
 */
function board_moderators(int $boardId): array {
    try {
        return db_fetchAll('
            SELECT u.user_id, u.username, u.is_admin, u.is_mod, u.is_banned,
                   bm.assigned_at
            FROM board_moderators bm
            JOIN users u ON u.user_id = bm.user_id
            WHERE bm.board_id = ?
            ORDER BY u.is_admin DESC, u.username
        ', [$boardId]);
    } catch (Exception $e) { return []; }
}

/**
 * Map: board_id => [array of mods] für mehrere Boards in einer Query.
 * Effizient für die Forenübersicht.
 */
function board_moderators_map(array $boardIds): array {
    $result = [];
    foreach ($boardIds as $id) $result[(int) $id] = [];
    if (empty($boardIds)) return $result;

    try {
        $placeholders = implode(',', array_fill(0, count($boardIds), '?'));
        $rows = db_fetchAll("
            SELECT bm.board_id, u.user_id, u.username,
                   COALESCE(u.is_owner, 0) AS is_owner,
                   u.is_admin, u.is_mod, u.is_banned
            FROM board_moderators bm
            JOIN users u ON u.user_id = bm.user_id
            WHERE bm.board_id IN ($placeholders) AND u.is_banned = 0
            ORDER BY COALESCE(u.is_owner, 0) DESC, u.is_admin DESC, u.is_mod DESC, u.username
        ", $boardIds);
        foreach ($rows as $r) {
            $result[(int) $r['board_id']][] = $r;
        }
    } catch (Exception $e) {}
    return $result;
}

/**
 * Prüft ob ein User Mod für ein bestimmtes Board ist.
 *
 * Regel-Hierarchie:
 *  - Admins dürfen alles
 *  - Globale Mods (is_mod=1) dürfen alles
 *  - Board-spezifische Mods nur ihr Board
 */
function is_mod_for_board(int $userId, int $boardId): bool {
    if ($userId <= 0) return false;
    try {
        $u = db_fetch('SELECT is_admin, is_mod, is_banned FROM users WHERE user_id = ?', [$userId]);
        if (!$u || $u['is_banned']) return false;
        if ($u['is_admin']) return true;
        if ($u['is_mod'])   return true;
        // Board-spezifisch?
        return db_count(
            'SELECT COUNT(*) FROM board_moderators WHERE user_id = ? AND board_id = ?',
            [$userId, $boardId]
        ) > 0;
    } catch (Exception $e) { return false; }
}

/**
 * Convenience-Wrapper: current_user darf in $boardId moderieren?
 */
function can_moderate_board(int $boardId): bool {
    $u = current_user();
    if (!$u) return false;
    return is_mod_for_board((int) $u['user_id'], $boardId);
}

/**
 * Mod zuordnen / entfernen
 */
function add_board_moderator(int $boardId, int $userId, int $assignedBy): bool {
    try {
        db_execute(
            'INSERT IGNORE INTO board_moderators (board_id, user_id, assigned_by) VALUES (?, ?, ?)',
            [$boardId, $userId, $assignedBy]
        );
        return true;
    } catch (Exception $e) { return false; }
}

function remove_board_moderator(int $boardId, int $userId): bool {
    try {
        db_execute('DELETE FROM board_moderators WHERE board_id = ? AND user_id = ?', [$boardId, $userId]);
        return true;
    } catch (Exception $e) { return false; }
}

/**
 * Alle Boards, in denen User Mod ist (board-spezifisch)
 */
function user_moderated_boards(int $userId): array {
    try {
        return db_fetchAll('
            SELECT b.* FROM boards b
            JOIN board_moderators bm ON bm.board_id = b.board_id
            WHERE bm.user_id = ?
            ORDER BY b.position
        ', [$userId]);
    } catch (Exception $e) { return []; }
}

/**
 * Lädt alle Team-Mitglieder gruppiert nach Rolle:
 *   ['admins' => [...], 'mods' => [...], 'board_mods' => [...]]
 * Forum-Mods enthalten zusätzlich `boards` mit den Foren-Titeln.
 */
function team_members(): array {
    $owners = [];
    $admins = $mods = $boardMods = [];

    try {
        // Owner (höchste Hierarchie) – kann mehrere geben
        try {
            $owners = db_fetchAll('
                SELECT user_id, username, avatar, post_count, registered_at, last_login, `rank`,
                       COALESCE(is_owner, 0) AS is_owner,
                       COALESCE(is_admin, 0) AS is_admin,
                       COALESCE(is_mod, 0)   AS is_mod
                FROM users
                WHERE is_owner = 1 AND is_banned = 0
                ORDER BY user_id ASC
            ');
        } catch (Exception $e) { $owners = []; }

        // Admins (ohne Owner)
        $admins = db_fetchAll('
            SELECT user_id, username, avatar, post_count, registered_at, last_login, `rank`,
                   COALESCE(is_owner, 0) AS is_owner,
                   COALESCE(is_admin, 0) AS is_admin,
                   COALESCE(is_mod, 0)   AS is_mod
            FROM users
            WHERE is_admin = 1 AND COALESCE(is_owner, 0) = 0 AND is_banned = 0
            ORDER BY post_count DESC, username
        ');
        $mods = db_fetchAll('
            SELECT user_id, username, avatar, post_count, registered_at, last_login, `rank`,
                   COALESCE(is_owner, 0) AS is_owner,
                   COALESCE(is_admin, 0) AS is_admin,
                   COALESCE(is_mod, 0)   AS is_mod
            FROM users
            WHERE is_mod = 1 AND is_admin = 0 AND is_banned = 0
            ORDER BY post_count DESC, username
        ');

        // Forum-spezifische Mods (User die KEINE globale Rolle haben –
        // also weder Owner noch Admin noch globaler Mod)
        // Diese erscheinen nur in der „Forum-Moderatoren"-Sektion und
        // bekommen bewusst KEIN Rollen-Avatar (Forum-Mod ist kontextuell)
        $rawBoardMods = db_fetchAll('
            SELECT u.user_id, u.username, u.avatar, u.post_count, u.registered_at, u.last_login, u.`rank`,
                   b.board_id, b.title AS board_title
            FROM board_moderators bm
            JOIN users u ON u.user_id = bm.user_id
            JOIN boards b ON b.board_id = bm.board_id
            WHERE u.is_admin = 0 AND u.is_mod = 0 AND COALESCE(u.is_owner, 0) = 0 AND u.is_banned = 0
            ORDER BY u.username, b.position
        ');

        // Pro User die Boards sammeln
        $byUser = [];
        foreach ($rawBoardMods as $r) {
            $uid = (int) $r['user_id'];
            if (!isset($byUser[$uid])) {
                $byUser[$uid] = [
                    'user_id'       => $uid,
                    'username'      => $r['username'],
                    'avatar'        => $r['avatar'],
                    'post_count'    => $r['post_count'],
                    'registered_at' => $r['registered_at'],
                    'last_login'    => $r['last_login'],
                    'rank'          => $r['rank'],
                    'boards'        => [],
                ];
            }
            $byUser[$uid]['boards'][] = ['id' => (int) $r['board_id'], 'title' => $r['board_title']];
        }
        $boardMods = array_values($byUser);
    } catch (Exception $e) {}

    return [
        'owners'     => $owners,                          // Liste aller Owner
        'owner'      => $owners[0] ?? null,               // Backward-Compat: erster Owner
        'admins'     => $admins,
        'mods'       => $mods,
        'board_mods' => $boardMods,
    ];
}

/**
 * Zaehlt Themen, Beitraege und letzten Beitrag eines Forums neu. Beim
 * Verschieben wechselt ein Thema samt allen Beitraegen das Forum — dann
 * stimmen die mitgefuehrten Zaehler auf beiden Seiten nicht mehr. Hoch- und
 * Runterzaehlen wuerde jede Abweichung dauerhaft festschreiben, deshalb hier
 * einmal sauber nachzaehlen.
 */
function board_zaehler_neu(int $boardId): void {
    // Was im Papierkorb liegt, zaehlt nirgends mit — sonst meldet das Forum
    // Beitraege, die niemand sehen kann.
    db_execute('UPDATE boards SET
            thread_count = (SELECT COUNT(*) FROM threads t
                            WHERE t.board_id = ? AND t.is_approved = 1
                              AND t.deleted_at IS NULL),
            post_count   = (SELECT COUNT(*) FROM posts p
                            JOIN threads t ON t.thread_id = p.thread_id
                            WHERE t.board_id = ? AND p.is_approved = 1
                              AND p.deleted_at IS NULL AND t.deleted_at IS NULL),
            last_post_id = (SELECT MAX(p.post_id) FROM posts p
                            JOIN threads t ON t.thread_id = p.thread_id
                            WHERE t.board_id = ? AND p.is_approved = 1
                              AND p.deleted_at IS NULL AND t.deleted_at IS NULL)
        WHERE board_id = ?', [$boardId, $boardId, $boardId, $boardId]);
}
