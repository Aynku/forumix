<?php
/**
 * v1.4.0 – Volltext-Index für die Suche
 *
 * Die Suche lief mit `LIKE '%wort%'`. Das kann kein Index bedienen: MariaDB
 * liest bei jeder Suche jeden Beitrag. Bei ein paar hundert faellt es nicht
 * auf, bei zehntausend wird die Suche zur Bremse — und ein Ergebnis nach
 * Treffergüte gibt es dabei ueberhaupt nicht, weil LIKE nur kennt "kommt vor"
 * oder "kommt nicht vor".
 *
 * Zwei Volltext-Indizes, mehr braucht es nicht: einer auf den Beitragstext,
 * einer auf den Titel des Themas.
 *
 * DAUER: Das Anlegen liest die Tabellen einmal ganz. Auf einem grossen Forum
 * dauert das spuerbar, und waehrenddessen ist die Tabelle gesperrt. Deshalb
 * gehoert diese Migration in eine ruhige Minute — nicht zur Hauptzeit.
 *
 * WORTLAENGE: InnoDB nimmt Woerter ab `innodb_ft_min_token_size` Zeichen auf,
 * ueblich sind 3. Kuerzere Suchbegriffe findet der Index nicht; die Suche
 * faellt dafuer auf den alten Weg zurueck (siehe lib/suche.php).
 */
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }

$zeile = function (string $text) { echo '<div class="req-row">' . htmlspecialchars($text) . '</div>'; };

$hat = function (string $tabelle, string $name): bool {
    try {
        return (bool) db_fetch("SHOW INDEX FROM `$tabelle` WHERE Key_name = ?", [$name]);
    } catch (Throwable $e) {
        return false;
    }
};

if (!$hat('posts', 'ft_inhalt')) {
    db_execute('ALTER TABLE posts ADD FULLTEXT INDEX ft_inhalt (content)');
    $zeile('posts: Volltext-Index über den Beitragstext angelegt.');
} else {
    $zeile('posts: Volltext-Index war schon da.');
}

if (!$hat('threads', 'ft_titel')) {
    db_execute('ALTER TABLE threads ADD FULLTEXT INDEX ft_titel (title)');
    $zeile('threads: Volltext-Index über den Titel angelegt.');
} else {
    $zeile('threads: Volltext-Index war schon da.');
}
