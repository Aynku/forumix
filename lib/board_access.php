<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// lib/board_access.php – Zentrale Logik für passwort-geschützte Foren
//
// KONVENTIONEN:
//   - Ein Board ist „geschützt" wenn `boards.password_hash` nicht leer ist
//   - Ein User hat „immer-Zugang" wenn er Owner/Admin/Mod ODER Forum-Mod dieses Boards ist
//   - Ein User hat „Session-Zugang" wenn er aktuell Passwort eingegeben hat
//     ($_SESSION['unlocked_boards'][board_id] = true)

/**
 * Hat der aktuelle User „privilegierten Zugang" (also Owner/Admin/Mod/Forum-Mod)?
 */
function board_has_privileged_access(int $boardId): bool {
    $cu = current_user();
    if (!$cu) return false;
    if (!empty($cu['is_owner']) || !empty($cu['is_admin']) || !empty($cu['is_mod'])) {
        return true;
    }
    // Forum-spezifischer Mod?
    try {
        $isMod = (int) db_count(
            'SELECT COUNT(*) FROM board_moderators WHERE board_id = ? AND user_id = ?',
            [$boardId, (int) $cu['user_id']]
        );
        return $isMod > 0;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Hat der aktuelle User in dieser Session das Passwort eingegeben?
 */
function board_session_unlocked(int $boardId): bool {
    return !empty($_SESSION['unlocked_boards'][$boardId]);
}

/**
 * Markiert das Board in der aktuellen Session als entsperrt.
 */
function board_unlock_in_session(int $boardId): void {
    if (!isset($_SESSION['unlocked_boards'])) {
        $_SESSION['unlocked_boards'] = [];
    }
    $_SESSION['unlocked_boards'][$boardId] = true;
}

/**
 * Darf der aktuelle User dieses Board sehen/betreten?
 * - Boards ohne password_hash: immer ja
 * - Boards mit password_hash: nur wenn privileged-access oder session-unlocked
 */
function board_user_can_access(array $board): bool {
    if (empty($board['password_hash'])) return true; // öffentlich
    $bid = (int) $board['board_id'];
    if (board_has_privileged_access($bid)) return true;
    if (board_session_unlocked($bid)) return true;
    return false;
}

/**
 * Prüft Zugang per Board-ID (lädt das Board selbst falls nötig).
 */
function board_id_can_access(int $boardId): bool {
    if ($boardId <= 0) return false;
    try {
        $b = db_fetch('SELECT board_id, password_hash FROM boards WHERE board_id = ?', [$boardId]);
    } catch (Exception $e) { return false; }
    if (!$b) return false;
    return board_user_can_access($b);
}

/**
 * Liefert die IDs aller Boards, die der aktuelle User NICHT sehen darf.
 * Wird in vielen Queries als Filter genutzt (NOT IN ...).
 * Returnt leer wenn keine Restriktionen vorliegen.
 */
function board_ids_hidden_for_user(): array {
    static $cache = null;
    if ($cache !== null) return $cache;

    try {
        // Alle geschützten Boards
        $protected = db_fetchAll('SELECT board_id FROM boards WHERE password_hash IS NOT NULL AND password_hash != ""');
        if (empty($protected)) return $cache = [];

        $hidden = [];
        foreach ($protected as $b) {
            $bid = (int) $b['board_id'];
            if (board_has_privileged_access($bid)) continue;
            if (board_session_unlocked($bid)) continue;
            $hidden[] = $bid;
        }
        return $cache = $hidden;
    } catch (Exception $e) {
        return $cache = [];
    }
}

/**
 * Liefert SQL-Snippet zum Filtern: ' AND b.board_id NOT IN (...)' oder ''.
 * Tabellen-Alias übergeben (z.B. 'b' oder 't'); wenn das Board-Feld auf einer
 * anderen Tabelle liegt, muss man entsprechend einsetzen.
 *
 * Beispiel:
 *   $sql = "SELECT ... WHERE ... " . board_filter_sql('b') . " ORDER BY ...";
 */
function board_filter_sql(string $alias = 'b', string $col = 'board_id'): string {
    $hidden = board_ids_hidden_for_user();
    if (empty($hidden)) return '';
    $list = implode(',', array_map('intval', $hidden));
    return " AND $alias.$col NOT IN ($list) ";
}

/**
 * Verifiziert ein Passwort gegen den Hash eines Boards.
 */
function board_check_password(int $boardId, string $password): bool {
    if ($password === '') return false;
    try {
        $b = db_fetch('SELECT password_hash FROM boards WHERE board_id = ?', [$boardId]);
    } catch (Exception $e) { return false; }
    if (!$b || empty($b['password_hash'])) return false;
    return password_verify($password, $b['password_hash']);
}

/**
 * Setzt ein neues Passwort (Hash) für ein Board.
 * Leere $password entfernt den Schutz.
 */
function board_set_password(int $boardId, string $password): void {
    if ($password === '') {
        db_execute('UPDATE boards SET password_hash = NULL WHERE board_id = ?', [$boardId]);
        // Beim Entfernen des Schutzes: alle Session-Entsperrungen für dieses Board wegwerfen
        return;
    }
    $hash = password_hash($password, PASSWORD_BCRYPT);
    db_execute('UPDATE boards SET password_hash = ? WHERE board_id = ?', [$hash, $boardId]);
}

/**
 * Maskiert den letzten Beitrag eines geschützten Boards für nicht-berechtigte User.
 * Modifiziert das Board-Array direkt.
 */
function board_mask_last_post(array &$board): void {
    if (empty($board['password_hash'])) return;
    if (board_user_can_access($board)) return;
    // Last post: Ersteller bleibt, Titel/Inhalt wird zu ***
    $board['last_post_title'] = '••••••••••';
    if (isset($board['last_post_subject'])) $board['last_post_subject'] = '••••••••••';
    // Falls Vorschau-Text vorhanden, auch maskieren
    if (isset($board['last_post_excerpt'])) $board['last_post_excerpt'] = '••••••••••';
}
