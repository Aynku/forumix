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
    $bid = (int) $board['board_id'];
    if (board_has_privileged_access($bid)) return true;

    // Seit 1.1.0 kommt die Gruppenstufe dazu. Wer nichts darf, kommt auch mit
    // dem richtigen Passwort nicht hinein — die beiden Schutzarten stehen
    // nebeneinander, nicht gegeneinander.
    if (board_level($bid) === 'none') return false;

    if (empty($board['password_hash'])) return true; // öffentlich
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
    $c = &board_access_cache();
    if (isset($c['verborgen'])) return $c['verborgen'];

    try {
        $hidden = [];

        // (1) Passwortgeschuetzte Foren, solange nicht entsperrt.
        $protected = db_fetchAll('SELECT board_id FROM boards WHERE password_hash IS NOT NULL AND password_hash != ""');
        foreach ($protected as $b) {
            $bid = (int) $b['board_id'];
            if (board_has_privileged_access($bid)) continue;
            if (board_session_unlocked($bid)) continue;
            $hidden[] = $bid;
        }

        // (2) Foren, fuer die dem Besucher die Gruppe fehlt — aber nur die, die
        // auf "verstecken" stehen. Ein Forum auf "gesperrt" soll ja gerade
        // sichtbar bleiben, mit Schloss davor.
        $sicht = db_fetchAll("SELECT board_id, visibility FROM boards");
        $stufen = board_levels_for_user();
        foreach ($sicht as $b) {
            $bid = (int) $b['board_id'];
            if (in_array($bid, $hidden, true)) continue;
            if (board_has_privileged_access($bid)) continue;
            if (($stufen[$bid] ?? 'write') !== 'none') continue;
            if (($b['visibility'] ?? 'hidden') === 'hidden') $hidden[] = $bid;
        }

        return $c['verborgen'] = array_values(array_unique($hidden));
    } catch (Exception $e) {
        // Spalte oder Tabelle fehlt (Migration nicht gefahren): nichts verbergen,
        // sonst waere nach einem halben Update das ganze Forum leer.
        return $c['verborgen'] = [];
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

/**
 * Wie board_has_privileged_access(), aber fuer einen beliebigen Benutzer.
 *
 * Noetig ueberall dort, wo NICHT der gerade Angemeldete gemeint ist — beim
 * Versand von Benachrichtigungen etwa wird fuer fremde Konten entschieden.
 * Die Sitzungsfreischaltung (board_session_unlocked) kann dort nicht gelten:
 * sie haengt an einer Sitzung, die es beim Versand gar nicht gibt.
 */
function board_privileged_for_user(int $boardId, int $userId): bool {
    try {
        $u = db_fetch('SELECT is_owner, is_admin, is_mod FROM users WHERE user_id = ?', [$userId]);
        if (!$u) return false;
        if (!empty($u['is_owner']) || !empty($u['is_admin']) || !empty($u['is_mod'])) return true;
        return db_count('SELECT COUNT(*) FROM board_moderators WHERE board_id = ? AND user_id = ?',
                        [$boardId, $userId]) > 0;
    } catch (Exception $e) {
        return false;
    }
}

/* ══════════════════════════════════════════════════════════════════════
   GRUPPENRECHTE (ab 1.1.0)

   Je Forum und Gruppe eine Stufe: nichts, lesen, schreiben. Wer in mehreren
   Gruppen ist, bekommt die hoechste davon. Inhaber, Admins und Moderatoren
   ueberspringen die Pruefung — wie beim Passwortschutz auch.

   Ein Forum OHNE Eintraege gilt als offen (Gaeste lesen, Angemeldete
   schreiben). Das ist Absicht: nach einem Update darf kein Forum verschwinden,
   das gestern noch offen war. Die Migration traegt fuer alle vorhandenen Foren
   ausdruecklich ein, was heute gilt.
   ══════════════════════════════════════════════════════════════════════ */

/**
 * Gemeinsamer Zwischenspeicher der Zugangsschicht.
 *
 * Alle drei Abfragen unten gelten fuer EINEN Besucher und EINEN Seitenaufruf,
 * deshalb wird pro Aufruf nur einmal nachgesehen. Im Prueflauf wechselt der
 * Benutzer mehrfach im selben Vorgang — dafuer gibt es board_access_neu_laden().
 */
function &board_access_cache(): array {
    static $c = [];
    return $c;
}

function board_access_neu_laden(): void {
    $c = &board_access_cache();
    $c = [];
}

const BRETT_STUFEN = ['none' => 0, 'read' => 1, 'write' => 2];

/**
 * Die Gruppen des aktuellen Besuchers. Nicht angemeldet → nur "Gäste",
 * angemeldet → "Angemeldete" plus alles, worin er eingetragen ist.
 */
function user_group_ids(): array {
    $c = &board_access_cache();
    if (isset($c['gruppen'])) return $c['gruppen'];

    $cu = current_user();
    try {
        $slug = $cu ? 'members' : 'guests';
        $eingebaut = db_fetch('SELECT group_id FROM user_groups WHERE slug = ?', [$slug]);
        $ids = $eingebaut ? [(int) $eingebaut['group_id']] : [];

        if ($cu) {
            foreach (db_fetchAll('SELECT group_id FROM group_members WHERE user_id = ?',
                                 [(int) $cu['user_id']]) as $g) {
                $ids[] = (int) $g['group_id'];
            }
        }
        return $c['gruppen'] = array_values(array_unique($ids));
    } catch (Exception $e) {
        // Tabellen noch nicht da (Migration nicht gefahren): wie bisher weiter.
        return $c['gruppen'] = [];
    }
}

/**
 * board_id → hoechste Stufe des aktuellen Besuchers. Foren ohne Eintrag
 * tauchen hier nicht auf und gelten als offen.
 */
function board_levels_for_user(): array {
    $c = &board_access_cache();
    if (isset($c['stufen'])) return $c['stufen'];

    $gruppen = user_group_ids();
    try {
        $alle = db_fetchAll('SELECT board_id, group_id, level FROM board_permissions');
    } catch (Exception $e) {
        return $c['stufen'] = [];
    }

    $karte = [];
    foreach ($alle as $r) {
        $bid = (int) $r['board_id'];
        // Jedes Forum, fuer das ueberhaupt etwas eingetragen ist, startet bei
        // "nichts" — sonst waere ein Forum, das nur EINER Gruppe gehoert, fuer
        // alle anderen versehentlich offen.
        if (!isset($karte[$bid])) $karte[$bid] = 'none';
        if (!in_array((int) $r['group_id'], $gruppen, true)) continue;
        if (BRETT_STUFEN[$r['level']] > BRETT_STUFEN[$karte[$bid]]) {
            $karte[$bid] = $r['level'];
        }
    }
    return $c['stufen'] = $karte;
}

/**
 * Stufe fuer ein bestimmtes Forum: 'none', 'read' oder 'write'.
 */
function board_level(int $boardId): string {
    if (board_has_privileged_access($boardId)) return 'write';
    $karte = board_levels_for_user();
    return $karte[$boardId] ?? 'write';   // ohne Eintrag: offen wie bisher
}

/**
 * Darf der Besucher in diesem Forum schreiben?
 */
function board_can_write(int $boardId): bool {
    return board_level($boardId) === 'write';
}

/**
 * Warum kommt jemand nicht hinein? 'frei', 'passwort' oder 'gruppe'.
 *
 * Der Unterschied ist wichtig: ein Passwort kann der Besucher selbst eingeben,
 * also fuehrt der Weg zur Entsperrseite. Eine fehlende Gruppenmitgliedschaft
 * kann er NICHT selbst aendern — dort waere ein Eingabefeld eine Einladung zum
 * Raten und wuerde nebenbei verraten, dass es das Forum ueberhaupt gibt.
 */
function board_zugangsgrund(array $board): string {
    $bid = (int) $board['board_id'];
    if (board_has_privileged_access($bid)) return 'frei';
    if (board_level($bid) === 'none') return 'gruppe';
    if (!empty($board['password_hash']) && !board_session_unlocked($bid)) return 'passwort';
    return 'frei';
}

/**
 * Wie in board_zugangsgrund(), nur ueber die ID.
 */
function board_id_zugangsgrund(int $boardId): string {
    try {
        $b = db_fetch('SELECT board_id, password_hash FROM boards WHERE board_id = ?', [$boardId]);
    } catch (Exception $e) { return 'gruppe'; }
    if (!$b) return 'gruppe';
    return board_zugangsgrund($b);
}
