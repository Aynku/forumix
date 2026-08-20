<?php
/**
 * Papierkorb: löschen, zurückholen, endgültig löschen.
 * --------------------------------------------------------------------------
 * Gelöschtes verschwindet nicht mehr aus der Datenbank, sondern bekommt einen
 * Zeitstempel. `deleted_at IS NULL` heisst sichtbar — und weil das an rund
 * siebzig Abfragestellen haengt, steht der Zusatz nicht siebzigmal im Klartext
 * da, sondern kommt aus einer Hand: sichtbar_sql().
 *
 * Die Zaehler (Brett, Thema, Benutzer) werden beim Loeschen heruntergezaehlt
 * und beim Zurueckholen wieder hoch. Ein Beitrag im Papierkorb zaehlt also
 * nirgends mit — genau wie frueher der geloeschte.
 *
 * Wer moderieren darf, sieht ihn trotzdem an seiner Stelle im Thema. Deshalb
 * gibt es zwei Sichten und nicht eine: sichtbar_sql() fuer alle, und in
 * pages/thread.php ausdruecklich ohne Filter fuer Moderatoren.
 */
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }

/**
 * Der Zusatz für jede Abfrage, die Beiträge oder Themen liest.
 *
 *   db_fetchAll('SELECT … FROM posts p WHERE p.thread_id = ?' . sichtbar_sql('p'), …)
 *
 * Kein Parameter, kein Zustand — damit laesst er sich ueberall anhaengen, auch
 * mitten in einer zusammengesetzten Abfrage.
 */
function sichtbar_sql(string $alias = ''): string
{
    $p = $alias !== '' ? $alias . '.' : '';
    return " AND {$p}deleted_at IS NULL";
}

/** Liegt der Beitrag im Papierkorb? */
function beitrag_geloescht(array $beitrag): bool
{
    return !empty($beitrag['deleted_at']);
}

/**
 * Beitrag in den Papierkorb legen. Die Zaehler gehen runter, der Beitrag
 * bleibt stehen.
 *
 * @return bool false, wenn es ihn nicht gibt oder er schon drin liegt.
 */
function beitrag_in_papierkorb(int $postId, int $modId): bool
{
    $p = db_fetch('SELECT p.post_id, p.thread_id, p.user_id, p.deleted_at, t.board_id
                     FROM posts p JOIN threads t ON t.thread_id = p.thread_id
                    WHERE p.post_id = ?', [$postId]);
    if (!$p || !empty($p['deleted_at'])) return false;

    db_execute('UPDATE posts SET deleted_at = NOW(), deleted_by = ?, deleted_with_thread = 0
                 WHERE post_id = ?', [$modId, $postId]);
    zaehler_verschieben((int) $p['thread_id'], (int) $p['board_id'], (int) $p['user_id'], -1);
    letzten_beitrag_neu_setzen((int) $p['thread_id'], (int) $p['board_id']);
    return true;
}

/** Beitrag zurückholen. */
function beitrag_zurueckholen(int $postId): bool
{
    $p = db_fetch('SELECT p.post_id, p.thread_id, p.user_id, p.deleted_at, t.board_id
                     FROM posts p JOIN threads t ON t.thread_id = p.thread_id
                    WHERE p.post_id = ?', [$postId]);
    if (!$p || empty($p['deleted_at'])) return false;

    db_execute('UPDATE posts SET deleted_at = NULL, deleted_by = NULL, deleted_with_thread = 0
                 WHERE post_id = ?', [$postId]);
    zaehler_verschieben((int) $p['thread_id'], (int) $p['board_id'], (int) $p['user_id'], +1);
    letzten_beitrag_neu_setzen((int) $p['thread_id'], (int) $p['board_id']);
    return true;
}

/**
 * Endgültig löschen — das, was frueher jedes Loeschen tat.
 *
 * Nur aus dem Papierkorb heraus: was noch sichtbar ist, muss erst dorthin.
 * Sonst waere der zweite Schritt kein zweiter Schritt.
 */
function beitrag_endgueltig_loeschen(int $postId): bool
{
    $p = db_fetch('SELECT post_id, thread_id, deleted_at FROM posts WHERE post_id = ?', [$postId]);
    if (!$p || empty($p['deleted_at'])) return false;

    // Anhaenge zuerst: nach dem DELETE gibt es den Beitrag nicht mehr, an dem
    // sie haengen — die Dateien blieben sonst auf der Platte liegen.
    try { delete_attachments_for_posts([$postId]); } catch (Throwable $e) {}
    db_execute('DELETE FROM posts WHERE post_id = ?', [$postId]);
    return true;
}

/** Thema in den Papierkorb: das Thema selbst und alles, was drinsteht. */
function thema_in_papierkorb(int $threadId, int $modId): bool
{
    $t = db_fetch('SELECT thread_id, board_id, deleted_at FROM threads WHERE thread_id = ?', [$threadId]);
    if (!$t || !empty($t['deleted_at'])) return false;

    $sichtbare = db_fetchAll('SELECT post_id, user_id FROM posts WHERE thread_id = ?' . sichtbar_sql(),
                             [$threadId]);

    db_execute('UPDATE threads SET deleted_at = NOW(), deleted_by = ? WHERE thread_id = ?', [$modId, $threadId]);
    // Die Beitraege bekommen denselben Stempel. Ohne ihn stuenden sie nach dem
    // Zurueckholen des Themas anders da als vorher — und zaehlten falsch mit.
    db_execute('UPDATE posts SET deleted_at = NOW(), deleted_by = ?, deleted_with_thread = 1
                 WHERE thread_id = ?' . sichtbar_sql(), [$modId, $threadId]);

    foreach ($sichtbare as $p) {
        db_execute('UPDATE users SET post_count = GREATEST(CAST(post_count AS SIGNED) - 1, 0) WHERE user_id = ?',
                   [$p['user_id']]);
    }
    db_execute('UPDATE boards SET thread_count = GREATEST(CAST(thread_count AS SIGNED) - 1, 0),
                                  post_count   = GREATEST(CAST(post_count AS SIGNED) - ?, 0)
                 WHERE board_id = ?', [count($sichtbare), $t['board_id']]);
    letzten_beitrag_am_brett_neu_setzen((int) $t['board_id']);
    return true;
}

/** Thema zurückholen, samt der Beiträge, die mit ihm gegangen sind. */
function thema_zurueckholen(int $threadId): bool
{
    $t = db_fetch('SELECT thread_id, board_id, deleted_at, deleted_by FROM threads WHERE thread_id = ?', [$threadId]);
    if (!$t || empty($t['deleted_at'])) return false;

    // Nur die, die MIT dem Thema gegangen sind. Was vorher einzeln im
    // Papierkorb lag, bleibt drin.
    $mit = db_fetchAll('SELECT post_id, user_id FROM posts
                         WHERE thread_id = ? AND deleted_with_thread = 1', [$threadId]);

    db_execute('UPDATE threads SET deleted_at = NULL, deleted_by = NULL WHERE thread_id = ?', [$threadId]);
    db_execute('UPDATE posts SET deleted_at = NULL, deleted_by = NULL, deleted_with_thread = 0
                 WHERE thread_id = ? AND deleted_with_thread = 1', [$threadId]);

    foreach ($mit as $p) {
        db_execute('UPDATE users SET post_count = post_count + 1 WHERE user_id = ?', [$p['user_id']]);
    }
    db_execute('UPDATE boards SET thread_count = thread_count + 1, post_count = post_count + ?
                 WHERE board_id = ?', [count($mit), $t['board_id']]);
    letzten_beitrag_neu_setzen($threadId, (int) $t['board_id']);
    return true;
}

/** Thema endgültig löschen — nur aus dem Papierkorb heraus. */
function thema_endgueltig_loeschen(int $threadId): bool
{
    $t = db_fetch('SELECT thread_id, deleted_at FROM threads WHERE thread_id = ?', [$threadId]);
    if (!$t || empty($t['deleted_at'])) return false;

    try { delete_attachments_for_thread($threadId); } catch (Throwable $e) {}
    db_execute('DELETE FROM posts WHERE thread_id = ?', [$threadId]);
    db_execute('DELETE FROM threads WHERE thread_id = ?', [$threadId]);
    return true;
}

/**
 * Zaehler eines Beitrags um $richtung verschieben (-1 loeschen, +1 zurueck).
 * Steht hier einmal, weil Loeschen und Zurueckholen sonst zwangslaeufig
 * auseinanderlaufen.
 */
function zaehler_verschieben(int $threadId, int $boardId, int $userId, int $richtung): void
{
    if ($richtung < 0) {
        db_execute('UPDATE threads SET reply_count = GREATEST(CAST(reply_count AS SIGNED) - 1, 0) WHERE thread_id = ?', [$threadId]);
        db_execute('UPDATE boards  SET post_count  = GREATEST(CAST(post_count AS SIGNED) - 1, 0) WHERE board_id = ?', [$boardId]);
        db_execute('UPDATE users   SET post_count  = GREATEST(CAST(post_count AS SIGNED) - 1, 0) WHERE user_id = ?', [$userId]);
    } else {
        db_execute('UPDATE threads SET reply_count = reply_count + 1 WHERE thread_id = ?', [$threadId]);
        db_execute('UPDATE boards  SET post_count  = post_count + 1 WHERE board_id = ?', [$boardId]);
        db_execute('UPDATE users   SET post_count  = post_count + 1 WHERE user_id = ?', [$userId]);
    }
}

/** Letzter sichtbarer Beitrag eines Themas und seines Bretts. */
function letzten_beitrag_neu_setzen(int $threadId, int $boardId): void
{
    $letzter = db_fetch('SELECT post_id FROM posts WHERE thread_id = ?' . sichtbar_sql()
                        . ' ORDER BY post_id DESC LIMIT 1', [$threadId]);
    db_execute('UPDATE threads SET last_post_id = ? WHERE thread_id = ?',
               [$letzter['post_id'] ?? null, $threadId]);
    letzten_beitrag_am_brett_neu_setzen($boardId);
}

function letzten_beitrag_am_brett_neu_setzen(int $boardId): void
{
    $letzter = db_fetch('SELECT p.post_id FROM posts p
                           JOIN threads t ON t.thread_id = p.thread_id
                          WHERE t.board_id = ?' . sichtbar_sql('p') . sichtbar_sql('t')
                        . ' ORDER BY p.post_id DESC LIMIT 1', [$boardId]);
    db_execute('UPDATE boards SET last_post_id = ? WHERE board_id = ?',
               [$letzter['post_id'] ?? null, $boardId]);
}

/**
 * Zaehler eines Themas neu ausrechnen statt hoch- und herunterzuzaehlen.
 * Gebraucht ueberall dort, wo Beitraege in Mengen wandern: teilen,
 * zusammenfuehren, verschieben.
 *
 * reply_count zaehlt ANTWORTEN, also alles ausser dem ersten Beitrag.
 */
function thema_zaehler_neu(int $threadId): void
{
    $n = db_count('SELECT COUNT(*) FROM posts WHERE thread_id = ?' . sichtbar_sql(), [$threadId]);
    $letzter = db_fetch('SELECT post_id FROM posts WHERE thread_id = ?' . sichtbar_sql()
                        . ' ORDER BY post_id DESC LIMIT 1', [$threadId]);
    db_execute('UPDATE threads SET reply_count = ?, last_post_id = ? WHERE thread_id = ?',
               [max(0, $n - 1), $letzter['post_id'] ?? null, $threadId]);
}

/** Themen im Papierkorb. Für die Mod-Ansicht. */
function papierkorb_themen(?array $boardIds = null): array
{
    $wo = ''; $args = [];
    if ($boardIds !== null) {
        if (!$boardIds) return [];
        $wo = ' AND t.board_id IN (' . implode(',', array_fill(0, count($boardIds), '?')) . ')';
        $args = $boardIds;
    }
    return db_fetchAll('
        SELECT t.thread_id, t.title, t.deleted_at, t.board_id,
               b.title AS brett, u.username AS verfasser, m.username AS geloescht_von,
               (SELECT COUNT(*) FROM posts p WHERE p.thread_id = t.thread_id) AS beitraege
          FROM threads t
          JOIN boards b ON b.board_id = t.board_id
     LEFT JOIN users u ON u.user_id  = t.user_id
     LEFT JOIN users m ON m.user_id  = t.deleted_by
         WHERE t.deleted_at IS NOT NULL' . $wo . '
      ORDER BY t.deleted_at DESC
         LIMIT 200', $args);
}

/** Einzeln gelöschte Beiträge — ohne die, die mit ihrem Thema gegangen sind. */
function papierkorb_beitraege(?array $boardIds = null): array
{
    $wo = ''; $args = [];
    if ($boardIds !== null) {
        if (!$boardIds) return [];
        $wo = ' AND t.board_id IN (' . implode(',', array_fill(0, count($boardIds), '?')) . ')';
        $args = $boardIds;
    }
    return db_fetchAll('
        SELECT p.post_id, p.thread_id, p.deleted_at, p.content,
               t.title AS thema, t.board_id, b.title AS brett,
               u.username AS verfasser, m.username AS geloescht_von,
               t.deleted_at AS thema_geloescht
          FROM posts p
          JOIN threads t ON t.thread_id = p.thread_id
          JOIN boards  b ON b.board_id  = t.board_id
     LEFT JOIN users   u ON u.user_id   = p.user_id
     LEFT JOIN users   m ON m.user_id   = p.deleted_by
         WHERE p.deleted_at IS NOT NULL AND t.deleted_at IS NULL' . $wo . '
      ORDER BY p.deleted_at DESC
         LIMIT 200', $args);
}
