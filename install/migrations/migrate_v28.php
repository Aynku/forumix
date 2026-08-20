<?php
/**
 * v1.3.0 – Papierkorb für Beiträge und Themen
 *
 * Bis hierher war Löschen endgültig: `DELETE FROM posts`, samt Anhängen, ohne
 * Rückweg. Ein Fehlgriff in den Mod-Werkzeugen war nicht zu heilen.
 *
 * Ab jetzt wird nur noch gekennzeichnet. Wer moderieren darf, sieht den
 * Beitrag weiterhin an seiner Stelle im Thema — ausgegraut, mit dem Vermerk,
 * wer ihn wann gelöscht hat — und kann ihn zurückholen. Endgültig löschen geht
 * weiterhin, dann aber ausdrücklich als zweiter Schritt.
 *
 * ZUR NUMMER: v27 ist verbrannt (siehe README in diesem Verzeichnis). Sie kam
 * mit 1.2.0 und ging mit 1.2.1 wieder; wo sie gelaufen ist, steht sie im
 * Protokoll. Deshalb geht es bei v28 weiter.
 *
 * BESTEHENDE BEITRÄGE ÄNDERN SICH NICHT: `deleted_at` ist NULL, und NULL heißt
 * sichtbar. Nach der Migration ist also nichts verschwunden, was vorher da war.
 */
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }

$zeile = function (string $text) { echo '<div class="req-row">' . htmlspecialchars($text) . '</div>'; };

foreach (['posts' => 'post_id', 'threads' => 'thread_id'] as $tabelle => $schluessel) {
    $spalte = db_fetch("SHOW COLUMNS FROM `$tabelle` LIKE 'deleted_at'");
    if (!$spalte) {
        db_execute("ALTER TABLE `$tabelle`
                    ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL,
                    ADD COLUMN deleted_by INT UNSIGNED NULL DEFAULT NULL");
        // Der Index traegt die Hauptlast: fast jede Abfrage im Forum haengt
        // ab jetzt ein "deleted_at IS NULL" an.
        db_execute("ALTER TABLE `$tabelle` ADD INDEX deleted_at (deleted_at)");
        $zeile("$tabelle: Papierkorb-Spalten angelegt.");
    } else {
        $zeile("$tabelle: Papierkorb-Spalten waren schon da.");
    }
}

// Beitraege, die MIT ihrem Thema in den Papierkorb gegangen sind, tragen es
// ausdruecklich. Am Zeitstempel liesse sich das nicht festmachen: wer erst
// einen Beitrag und dann das Thema loescht, tut das leicht in derselben
// Sekunde — und beim Zurueckholen kaeme dann auch der Beitrag mit, der einzeln
// weggelegt worden war.
$spalte = db_fetch("SHOW COLUMNS FROM posts LIKE 'deleted_with_thread'");
if (!$spalte) {
    db_execute("ALTER TABLE posts ADD COLUMN deleted_with_thread TINYINT(1) NOT NULL DEFAULT 0");
    $zeile('posts: Vermerk "mit dem Thema geloescht" angelegt.');
} else {
    $zeile('posts: Vermerk "mit dem Thema geloescht" war schon da.');
}
