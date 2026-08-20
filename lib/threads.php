<?php
/**
 * Ein Thema anlegen — an einer Stelle.
 * --------------------------------------------------------------------------
 * Das Anlegen ist mehr als zwei INSERTs: Zaehler am Brett, Zaehler und Rang
 * am Benutzer, letzter Beitrag am Thema UND am Brett, Erwaehnungen, Auto-Abo,
 * Auszeichnungen. Wer das nachbaut, vergisst davon etwas — und die Zaehler
 * stehen danach dauerhaft schief, ohne dass es jemandem auffaellt.
 *
 * Deshalb steht der Ablauf hier, und pages/new_thread.php benutzt ihn wie
 * jedes andere Werkzeug auch (tools/versionsbeitrag.php zum Beispiel).
 *
 * Was NICHT hier steht, weil es zum Formular gehoert und nicht zum Thema:
 * Rechtepruefung, Laengenpruefung, Wartezeit zwischen Beitraegen, Anhaenge,
 * Umfrage, Schlagworte. Das bleibt beim Aufrufer.
 */
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }

/**
 * Legt Thema und ersten Beitrag an.
 *
 * @param array $optionen
 *   'freigegeben'   bool   Sichtbar oder wartet es auf einen Moderator?
 *                          Vorgabe: sichtbar. Die Entscheidung faellt beim
 *                          Aufrufer, weil sie an dessen Rechten haengt.
 *   'ip'            string Fuer das Beitrags-Protokoll. Vorgabe: die des
 *                          Aufrufers. Leer oder ungueltig heisst: kein
 *                          Eintrag im IP-Protokoll.
 *   'abo'           bool   Autor das Thema abonnieren. Vorgabe: ja.
 *   'nachbereitung' callable(int $themaId, int $beitragId)
 *                          Laeuft NACH dem Commit und VOR den Erwaehnungen.
 *                          Genau dort haengt pages/new_thread.php die Anhaenge
 *                          ein: die Benachrichtigung soll nicht auf einen
 *                          Beitrag zeigen, an dem die Datei noch fehlt.
 *
 * @return array{thread_id:int, post_id:int, freigegeben:bool}
 */
function thread_erstellen(int $boardId, int $userId, string $titel, string $inhalt, array $optionen = []): array
{
    $freigegeben = (bool) ($optionen['freigegeben'] ?? true);
    $ip          = $optionen['ip'] ?? (function_exists('client_ip') ? client_ip() : '');
    $abo         = (bool) ($optionen['abo'] ?? true);

    DB::get()->beginTransaction();
    try {
        // Der Fallback ohne is_approved faengt Datenbanken ab, die vor der
        // Freigabe-Spalte angelegt wurden. Er stand vorher zweimal in
        // new_thread.php und gehoert genau hierher.
        try {
            $threadId = db_execute(
                'INSERT INTO threads (board_id, user_id, title, is_approved) VALUES (?, ?, ?, ?)',
                [$boardId, $userId, $titel, $freigegeben ? 1 : 0]
            );
        } catch (PDOException $e) {
            $threadId = db_execute(
                'INSERT INTO threads (board_id, user_id, title) VALUES (?, ?, ?)',
                [$boardId, $userId, $titel]
            );
        }

        try {
            $postId = db_execute(
                'INSERT INTO posts (thread_id, user_id, content, author_ip, is_approved) VALUES (?, ?, ?, ?, ?)',
                [$threadId, $userId, $inhalt, $ip, $freigegeben ? 1 : 0]
            );
        } catch (PDOException $e) {
            $postId = db_execute(
                'INSERT INTO posts (thread_id, user_id, content) VALUES (?, ?, ?)',
                [$threadId, $userId, $inhalt]
            );
        }

        // Nur mit echter Adresse protokollieren. Auf der Kommandozeile gibt es
        // keine, und client_ip() liefert dort die Ersatzadresse 0.0.0.0 — die
        // stuende danach im IP-Protokoll, als waere jemand von dort gekommen.
        // Kein Eintrag ist richtiger als ein erfundener.
        if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
            try { log_user_ip($userId, 'post', $ip); } catch (Exception $e) {}
        }

        db_execute('UPDATE threads SET last_post_id = ? WHERE thread_id = ?', [$postId, $threadId]);

        // Wartet das Thema noch auf Freigabe, zaehlt es nirgends mit — sonst
        // meldet das Brett Beitraege, die niemand sehen kann.
        if ($freigegeben) {
            db_execute('UPDATE boards SET thread_count = thread_count + 1, post_count = post_count + 1, last_post_id = ? WHERE board_id = ?',
                [$postId, $boardId]);
            db_execute('UPDATE users SET post_count = post_count + 1, last_post_at = NOW(), last_activity_at = NOW() WHERE user_id = ?', [$userId]);
            update_user_rank($userId);
        }

        DB::get()->commit();
    } catch (Throwable $e) {
        if (DB::get()->inTransaction()) DB::get()->rollBack();
        throw $e;
    }

    if (isset($optionen['nachbereitung']) && is_callable($optionen['nachbereitung'])) {
        ($optionen['nachbereitung'])((int) $threadId, (int) $postId);
    }

    // Erwaehnungen erst nach der Freigabe verschicken, sonst laesst sich ueber
    // einen wartenden Beitrag beliebig benachrichtigen.
    if ($freigegeben) {
        process_mentions($inhalt, (int) $threadId, (int) $postId, $userId);
    }

    if ($abo) {
        try {
            db_execute('INSERT IGNORE INTO thread_subscriptions (user_id, thread_id) VALUES (?, ?)',
                [$userId, $threadId]);
        } catch (Exception $e) {}
    }

    try { check_achievements($userId); } catch (Exception $e) {}

    return ['thread_id' => (int) $threadId, 'post_id' => (int) $postId, 'freigegeben' => $freigegeben];
}
