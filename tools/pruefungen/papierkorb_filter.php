<?php
/**
 * Der Papierkorb darf nirgends durchscheinen (1.3.0).
 *
 * `deleted_at IS NULL` haengt an rund siebzig Abfragen. Genau bei den
 * versteckten Foren (1.1.0) ist eine Stelle durchgerutscht — die Startseite —
 * und hat ein Forum gezeigt, das niemand sehen sollte. Damit das hier nicht
 * wieder passiert, haelt diese Pruefung je Datei fest, wie viele Abfragen den
 * Filter tragen muessen. Faellt einer weg, faellt es auf.
 *
 * Reine Textpruefung, keine Datenbank.
 */

if (!defined('FORUM_PRUEFLAUF')) { http_response_code(403); die('Forbidden'); }

$wurzel = dirname(__DIR__, 2);
$lies = function (string $d) use ($wurzel): string {
    $pfad = "$wurzel/$d";
    if (!is_file($pfad)) throw new RuntimeException("$d fehlt");
    return (string) file_get_contents($pfad);
};

/* Datei => wie viele Stellen mindestens filtern muessen. Die Zahlen stammen
   aus dem Stand von 1.3.0; sie duerfen wachsen, nur nicht schrumpfen. */
$erwartet = [
    'pages/home.php'        => 6,   // letzter Beitrag je Forum (3 Unterabfragen), Themen- und Beitragszahl
    'pages/board.php'       => 1,
    'pages/thread.php'      => 2,   // Beitragsliste und das Thema selbst
    'pages/rss.php'         => 5,
    'pages/search.php'      => 2,
    'pages/profile.php'     => 1,
    'api/quicksearch.php'   => 1,
    'lib/tags.php'          => 1,
    'lib/bookmarks.php'     => 1,
    'lib/user_stats.php'    => 3,
    'lib/dashboard.php'     => 7,
    'lib/reads.php'         => 2,
    'lib/board_mods.php'    => 3,   // Themen-, Beitragszahl und letzter Beitrag des Bretts
    'lib/approval.php'      => 4,
    'lib/mod.php'           => 2,
];

$pruefungen = [];
foreach ($erwartet as $datei => $mindestens) {
    $pruefungen["$datei filtert den Papierkorb"] = function () use ($lies, $datei, $mindestens) {
        $q = $lies($datei);
        $n = substr_count($q, 'deleted_at') + substr_count($q, 'sichtbar_sql');
        if ($n < $mindestens) {
            throw new RuntimeException(
                "Erwartet mindestens $mindestens Filterstellen, gezaehlt $n — "
                . 'da zeigt jetzt vermutlich etwas, was im Papierkorb liegt');
        }
    };
}

$pruefungen['Schreibwege weisen geloeschte Beitraege ab'] = function () use ($lies) {
    // Bearbeiten, Melden, Reagieren: ueber die Kennung soll ein Beitrag im
    // Papierkorb genauso wenig erreichbar sein wie ueber die Oberflaeche.
    foreach (['pages/edit_post.php', 'pages/report.php', 'api/react.php'] as $datei) {
        if (!str_contains($lies($datei), 'sichtbar_sql')) {
            throw new RuntimeException("$datei laesst einen geloeschten Beitrag durch");
        }
    }
};

$pruefungen['Loeschen legt in den Papierkorb, es entfernt nichts'] = function () use ($lies) {
    $q = $lies('pages/mod_action.php');
    foreach (["case 'delete_post'", "case 'delete_thread'"] as $fall) {
        $rumpf = substr($q, strpos($q, $fall), 1200);
        if (str_contains($rumpf, 'DELETE FROM')) {
            throw new RuntimeException("$fall loescht wieder unmittelbar statt in den Papierkorb zu legen");
        }
    }
    foreach (['restore_post', 'restore_thread', 'purge_post', 'purge_thread'] as $weg) {
        if (!str_contains($q, "case '$weg'")) throw new RuntimeException("Der Weg '$weg' fehlt");
    }
};

return $pruefungen;
