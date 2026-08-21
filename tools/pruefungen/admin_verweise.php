<?php
/**
 * Verweise im Adminbereich zeigen auf einen Bereich, den es gibt.
 *
 * Anlass: Am 21.08.2026 stand in der Forenverwaltung ein Hinweis mit dem Weg
 * zu den Datenbank-Anpassungen — unter `?action=migrations`. Den Bereich gibt
 * es nicht, er heisst `updates`. Der Link fuehrte auf eine leere Seite, und
 * gemerkt hat es niemand, weil ein falscher `action`-Wert einfach im
 * Standardzweig landet statt einen Fehler zu werfen.
 *
 * Dieselbe Sorte Fehler, zweimal am selben Tag: zwei weitere Verweise zeigten
 * auf `?action=settings`, obwohl die Registrierungs-Einstellungen laengst in
 * einem eigenen Bereich stehen. Ein Link ins Leere faellt hier auf, ein Link
 * auf den falschen von zwei existierenden Bereichen nicht — deshalb wird
 * dieser Lauf nur den ersten Fall los.
 *
 * Braucht keine Datenbank: gelesen wird der Quelltext.
 */

if (!defined('FORUM_PRUEFLAUF')) { http_response_code(403); die('Forbidden'); }

$wurzel = dirname(__DIR__, 2);

return [
    'Jeder Verweis im Adminbereich hat ein Ziel' => function () use ($wurzel) {
        $view = (string) file_get_contents($wurzel . '/views/admin/index.php');
        $ctrl = (string) file_get_contents($wurzel . '/admin/index.php');

        // Ein Bereich entsteht auf drei Arten: als eigener Zweig, als Fall in
        // einem switch, oder als Eintrag in einer in_array()-Liste.
        $ziele = [];
        preg_match_all("/\\\$action\s*===\s*'([a-z_]+)'/", $view . $ctrl, $t);
        $ziele = array_merge($ziele, $t[1]);
        preg_match_all("/case\s+'([a-z_]+)'\s*:/", $ctrl, $t);
        $ziele = array_merge($ziele, $t[1]);
        preg_match_all("/in_array\(\s*\\\$action\s*,\s*\[([^\]]+)\]/", $ctrl, $t);
        foreach ($t[1] as $liste) {
            preg_match_all("/'([a-z_]+)'/", $liste, $e);
            $ziele = array_merge($ziele, $e[1]);
        }
        $ziele = array_flip($ziele);

        // Verweise auf fremde Einstiege (mod_action.php und Co.) gehen den
        // Adminbereich nichts an — die haben ihre eigene Liste.
        $fehlt = [];
        preg_match_all('/([\w.\/?=<>\\\\ ]{0,40})\?action=([a-z_]+)/', $view, $treffer, PREG_SET_ORDER);
        foreach ($treffer as [$ganz, $davor, $name]) {
            if (str_contains($davor, '.php')) continue;   // z.B. /mod_action.php?action=...
            if (!isset($ziele[$name])) $fehlt[$name] = true;
        }

        if ($fehlt) {
            throw new RuntimeException('Verweis ohne Ziel: ?action=' . implode(', ?action=', array_keys($fehlt)));
        }
    },
];
