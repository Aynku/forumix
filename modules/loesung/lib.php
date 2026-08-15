<?php
// modules/loesung/lib.php – gemeinsame Helfer der beiden Einhaengepunkte
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }

/**
 * Welcher Beitrag ist im Thema als Loesung markiert?
 *
 * Der Haken unter dem Beitrag laeuft einmal je Beitrag. Ohne Zwischenspeicher
 * waere das eine Abfrage pro Beitrag auf jeder Themenseite.
 */
function loesung_fuer_thread(int $threadId): ?array {
    static $cache = [];
    if (array_key_exists($threadId, $cache)) return $cache[$threadId];

    try {
        $cache[$threadId] = db_fetch(
            'SELECT * FROM loesungen WHERE thread_id = ?', [$threadId]) ?: null;
    } catch (Exception $e) {
        // Modul aktiv, Tabelle fehlt: lieber nichts anzeigen als die
        // Themenseite mit einem Fehler zerlegen.
        $cache[$threadId] = null;
    }
    return $cache[$threadId];
}

/**
 * Der erste Beitrag eines Themas — das ist die Frage selbst.
 *
 * Die Tabelle threads fuehrt kein first_post_id, deshalb einmal je Thema die
 * kleinste Beitragsnummer holen statt sie je Beitrag zu suchen.
 */
function loesung_erster_beitrag(int $threadId): int {
    static $cache = [];
    if (isset($cache[$threadId])) return $cache[$threadId];
    try {
        $cache[$threadId] = db_count(
            'SELECT COALESCE(MIN(post_id), 0) FROM posts WHERE thread_id = ?', [$threadId]);
    } catch (Exception $e) {
        $cache[$threadId] = 0;
    }
    return $cache[$threadId];
}

/**
 * Darf der Betrachter die Markierung setzen oder aufheben?
 * Der Fragesteller, weil es seine Frage ist. Moderation, weil sie aufraeumt.
 */
function loesung_darf_markieren(?array $thread): bool {
    $cu = current_user();
    if (!$cu || !$thread) return false;
    if ((int) $thread['user_id'] === (int) $cu['user_id']) return true;
    return is_admin() || is_mod();
}
