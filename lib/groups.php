<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// lib/groups.php – Benutzergruppen und ihre Rechte je Forum
//
// Die Rechteauswertung steht in board_access.php (board_level, board_can_write,
// board_ids_hidden_for_user). Hier liegt, was die Verwaltung braucht: anlegen,
// aendern, Mitglieder pflegen, Rechte schreiben.
//
// "guests" und "members" sind eingebaut und lassen sich nicht loeschen: jeder
// Besucher faellt in genau eine von beiden, und ohne sie waere jede Rechtezeile
// unvollstaendig.

/** Alle Gruppen, eingebaute zuerst. */
function groups_all(): array {
    try {
        return db_fetchAll('SELECT * FROM user_groups ORDER BY is_builtin DESC, position, name');
    } catch (Exception $e) {
        return [];
    }
}

function group_by_id(int $id): ?array {
    try {
        return db_fetch('SELECT * FROM user_groups WHERE group_id = ?', [$id]) ?: null;
    } catch (Exception $e) { return null; }
}

/** Mitgliederzahl je Gruppe: group_id => Anzahl. */
function group_member_counts(): array {
    try {
        $karte = [];
        // Geloeschte Konten zaehlen nicht mit. Seit 1.1.3 raeumt die Loeschung
        // die Mitgliedschaft zwar selbst ab, aber Zeilen aus der Zeit davor
        // sollen die Zahl nicht verfaelschen.
        foreach (db_fetchAll('SELECT m.group_id, COUNT(*) AS n
                              FROM group_members m JOIN users u ON u.user_id = m.user_id
                              WHERE u.is_deleted = 0
                              GROUP BY m.group_id') as $r) {
            $karte[(int) $r['group_id']] = (int) $r['n'];
        }
        return $karte;
    } catch (Exception $e) { return []; }
}

function group_members(int $groupId): array {
    try {
        return db_fetchAll('
            SELECT u.user_id, u.username, u.is_admin, u.is_mod, m.added_at, m.woher
            FROM group_members m JOIN users u ON u.user_id = m.user_id
            WHERE m.group_id = ? AND u.is_deleted = 0 ORDER BY u.username
        ', [$groupId]);
    } catch (Exception $e) {
        // Vor Migration v27 gibt es die Spalte woher nicht. Ohne diesen zweiten
        // Versuch bliebe die Mitgliederliste im Adminbereich einfach leer —
        // ein Fehler, der wie "da ist niemand drin" aussieht.
        try {
            return db_fetchAll('
                SELECT u.user_id, u.username, u.is_admin, u.is_mod, m.added_at
                FROM group_members m JOIN users u ON u.user_id = m.user_id
                WHERE m.group_id = ? AND u.is_deleted = 0 ORDER BY u.username
            ', [$groupId]);
        } catch (Exception $e2) { return []; }
    }
}

/**
 * Legt eine Gruppe an. Der Kurzname wird aus dem Namen gebildet und bei Bedarf
 * durchnummeriert — er ist der Schluessel, ueber den der Code die eingebauten
 * Gruppen findet, und darf deshalb nicht zweimal vorkommen.
 */
function group_create(string $name, bool $abzeichen = false): int {
    $name = trim($name);
    if ($name === '') throw new InvalidArgumentException('Die Gruppe braucht einen Namen.');

    $basis = strtolower($name);
    $basis = strtr($basis, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss']);
    $basis = preg_replace('/[^a-z0-9]+/', '_', $basis);
    $basis = trim((string) $basis, '_');
    if ($basis === '') $basis = 'gruppe';

    $slug = $basis;
    $n = 2;
    while (db_fetch('SELECT group_id FROM user_groups WHERE slug = ?', [$slug])) {
        $slug = $basis . '_' . $n++;
    }

    db_execute('INSERT INTO user_groups (name, slug, is_builtin, show_badge, position)
                VALUES (?, ?, 0, ?, 10)', [$name, $slug, $abzeichen ? 1 : 0]);
    return (int) db_fetch('SELECT group_id FROM user_groups WHERE slug = ?', [$slug])['group_id'];
}

function group_rename(int $id, string $name, bool $abzeichen): void {
    $name = trim($name);
    if ($name === '') throw new InvalidArgumentException('Die Gruppe braucht einen Namen.');
    $g = group_by_id($id);
    if (!$g) throw new RuntimeException('Diese Gruppe gibt es nicht.');

    // Bei den eingebauten darf der Name geaendert werden (manche nennen ihre
    // Gaeste "Besucher"), der Kurzname aber nicht — daran haengt der Code.
    db_execute('UPDATE user_groups SET name = ?, show_badge = ? WHERE group_id = ?',
               [$name, $abzeichen ? 1 : 0, $id]);
}

function group_delete(int $id): void {
    $g = group_by_id($id);
    if (!$g) return;
    if (!empty($g['is_builtin'])) {
        throw new RuntimeException('Gäste und Angemeldete lassen sich nicht löschen.');
    }
    db_execute('DELETE FROM board_permissions WHERE group_id = ?', [$id]);
    db_execute('DELETE FROM group_members WHERE group_id = ?', [$id]);
    db_execute('DELETE FROM user_groups WHERE group_id = ?', [$id]);
}

/**
 * @param string $woher 'hand' = jemand hat es eingetragen, 'liste' = aus einer
 *   Adressliste uebernommen (tools/kundenabgleich.php). Der Abgleich entfernt
 *   spaeter nur, was er selbst angelegt hat — deshalb muss die Herkunft mit.
 *
 * INSERT IGNORE mit Absicht: steht jemand schon von Hand drin, bleibt es dabei.
 * Ein Abgleich soll eine Mitgliedschaft nicht heimlich in seine eigene
 * umwidmen und sie beim naechsten Lauf wieder entfernen koennen.
 */
function group_member_add(int $groupId, int $userId, string $woher = 'hand'): void {
    $g = group_by_id($groupId);
    if (!$g) throw new RuntimeException('Diese Gruppe gibt es nicht.');
    if (!empty($g['is_builtin'])) {
        // Wer angemeldet ist, ist "Angemeldete" — das ergibt sich, statt
        // gepflegt zu werden. Eine Liste dafuer waere sofort veraltet.
        throw new RuntimeException('In eingebaute Gruppen wird niemand eingetragen.');
    }
    if (!in_array($woher, ['hand', 'liste'], true)) $woher = 'hand';
    try {
        db_execute('INSERT IGNORE INTO group_members (group_id, user_id, woher) VALUES (?, ?, ?)',
            [$groupId, $userId, $woher]);
    } catch (PDOException $e) {
        // Vor Migration v27 gibt es die Spalte nicht.
        db_execute('INSERT IGNORE INTO group_members (group_id, user_id) VALUES (?, ?)', [$groupId, $userId]);
    }
}

function group_member_remove(int $groupId, int $userId): void {
    db_execute('DELETE FROM group_members WHERE group_id = ? AND user_id = ?', [$groupId, $userId]);
}

/** Rechte eines Forums: group_id => 'none'|'read'|'write'. */
function board_permissions_map(int $boardId): array {
    try {
        $karte = [];
        foreach (db_fetchAll('SELECT group_id, level FROM board_permissions WHERE board_id = ?',
                             [$boardId]) as $r) {
            $karte[(int) $r['group_id']] = $r['level'];
        }
        return $karte;
    } catch (Exception $e) { return []; }
}

/**
 * Schreibt die Rechte eines Forums neu. Uebergeben wird group_id => Stufe;
 * Gruppen, die fehlen, bekommen 'none'.
 *
 * Es wird IMMER fuer jede Gruppe eine Zeile geschrieben. Ein Forum, fuer das
 * gar nichts eingetragen ist, gilt naemlich als offen — das ist fuer den
 * Uebergang richtig, waere hier aber falsch: wer alle Haken wegnimmt, meint
 * "niemand", nicht "alle".
 */
function board_permissions_set(int $boardId, array $stufen): void {
    $erlaubt = ['none', 'read', 'write'];
    db_execute('DELETE FROM board_permissions WHERE board_id = ?', [$boardId]);
    foreach (groups_all() as $g) {
        $gid   = (int) $g['group_id'];
        $stufe = $stufen[$gid] ?? 'none';
        if (!in_array($stufe, $erlaubt, true)) $stufe = 'none';
        db_execute('INSERT INTO board_permissions (board_id, group_id, level) VALUES (?, ?, ?)',
                   [$boardId, $gid, $stufe]);
    }
}

/** Die Gruppen eines Benutzers, die als Abzeichen gezeigt werden sollen. */
function user_badge_groups(int $userId): array {
    try {
        return db_fetchAll('
            SELECT g.name FROM group_members m
            JOIN user_groups g ON g.group_id = m.group_id
            WHERE m.user_id = ? AND g.show_badge = 1
            ORDER BY g.position, g.name
        ', [$userId]);
    } catch (Exception $e) { return []; }
}
