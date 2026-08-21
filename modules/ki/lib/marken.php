<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
/**
 * modules/ki/lib/marken.php – Wer hat mitgeschrieben?
 *
 * Steht hier und nicht im Haken: der wird je Beitrag eingebunden, und eine
 * Funktion laesst sich nur einmal deklarieren.
 */

/**
 * Die Kennzeichnungen eines Themas, in einem Zug geholt.
 * Eine Abfrage je Beitrag waeren auf einer vollen Seite zwanzig.
 */
function ki_marken_fuer_thema(int $threadId): array {
    static $cache = [];
    if (isset($cache[$threadId])) return $cache[$threadId];

    $karte = [];
    try {
        $zeilen = db_fetchAll(
            'SELECT k.post_id, k.befehl
               FROM ki_kennzeichnung k
               JOIN posts p ON p.post_id = k.post_id
              WHERE p.thread_id = ?', [$threadId]);
        foreach ($zeilen as $z) $karte[(int) $z['post_id']] = (string) $z['befehl'];
    } catch (Exception $e) {
        // Tabelle fehlt: dann gibt es hier nichts anzuzeigen.
    }
    return $cache[$threadId] = $karte;
}
