<?php
/**
 * Sprachpaket: Einzahl, Mehrzahl und was passiert, wenn etwas fehlt.
 *
 * Die Mehrzahl kam mit der Umstellung auf das Sprachpaket dazu. Sie ist die
 * Stelle, an der ein Uebersetzungssystem als Erstes bricht: "1 Beiträge"
 * faellt niemandem beim Programmieren auf, sondern dem Besucher.
 *
 * WARUM die Anzahl der erste Platzhalter ist: Im Deutschen steht sie vorn,
 * in anderen Sprachen mittendrin. Wer den Satz aus "1 " . t('beitrag')
 * zusammensetzt, legt die Reihenfolge fuer alle Sprachen fest — und genau
 * das soll ein Sprachpaket verhindern.
 *
 * Braucht keine Datenbank.
 */

if (!defined('FORUM_PRUEFLAUF')) { http_response_code(403); die('Forbidden'); }

$wurzel = dirname(__DIR__, 2);

if (!function_exists('current_user')) {
    function current_user() { return null; }
}

require_once $wurzel . '/lib/lang.php';

/*
 * t() und tn() halten die geladene Sprache in einer statischen Variablen.
 * Fuer den Prueflauf wird deshalb nicht die echte Datei genommen, sondern
 * direkt gerechnet — sonst haengt das Ergebnis daran, welche Pruefung
 * zuerst lief.
 */
$form = function (array $eintrag, int $anzahl): string {
    $str = $anzahl === 1
        ? ($eintrag[0] ?? $eintrag[1] ?? '')
        : ($eintrag[1] ?? $eintrag[0] ?? '');

    return vsprintf($str, [$anzahl]);
};

return [
    'Einzahl und Mehrzahl kommen aus derselben Angabe' => function () use ($form) {
        $eintrag = ['%d Beitrag', '%d Beiträge'];

        if ($form($eintrag, 1) !== '1 Beitrag') {
            throw new RuntimeException('Einzahl falsch: ' . $form($eintrag, 1));
        }

        if ($form($eintrag, 5) !== '5 Beiträge') {
            throw new RuntimeException('Mehrzahl falsch: ' . $form($eintrag, 5));
        }
    },

    'Null nimmt die Mehrzahl' => function () use ($form) {
        // "0 Beiträge", nicht "0 Beitrag" — im Deutschen wie im Englischen.
        $eintrag = ['%d Beitrag', '%d Beiträge'];

        if ($form($eintrag, 0) !== '0 Beiträge') {
            throw new RuntimeException('Null sollte die Mehrzahl nehmen, kam: ' . $form($eintrag, 0));
        }
    },

    'Fehlt eine Form, wird die vorhandene genommen' => function () use ($form) {
        // Eine halb uebersetzte Sprachdatei soll keine leere Seite ergeben.
        if ($form(['%d Beitrag'], 5) !== '5 Beitrag') {
            throw new RuntimeException('Ohne Mehrzahlform kam: ' . $form(['%d Beitrag'], 5));
        }

        if ($form([1 => '%d Beiträge'], 1) !== '1 Beiträge') {
            throw new RuntimeException('Ohne Einzahlform kam: ' . $form([1 => '%d Beiträge'], 1));
        }
    },

    'Keine Sprache kennt einen Schluessel, den Deutsch nicht kennt' => function () use ($wurzel) {
        /*
         * Deutsch ist die Grundlage: load_language() laedt es zuerst und legt
         * die gewaehlte Sprache darueber. Ein Schluessel, der NUR auf Deutsch
         * steht, ist deshalb kein Fehler — er faellt auf Deutsch zurueck, und
         * das ist besser als der nackte Schluessel.
         *
         * Umgekehrt schon: Ein Schluessel, den nur die Uebersetzung kennt,
         * ist eine Leiche. Entweder wurde er im Code umbenannt, oder er wurde
         * nie benutzt.
         */
        $de = require $wurzel . '/lang/de.php';

        foreach (['en'] as $sprache) {
            $andere = require $wurzel . '/lang/' . $sprache . '.php';
            $ueberzaehlig = array_diff(array_keys($andere), array_keys($de));

            if ($ueberzaehlig !== []) {
                throw new RuntimeException(sprintf(
                    'In %s.php stehen Schluessel, die de.php nicht kennt: %s',
                    $sprache,
                    implode(', ', array_slice($ueberzaehlig, 0, 8))
                ));
            }
        }
    },

    'Mehrzahl-Eintraege sind in beiden Sprachen Mehrzahl-Eintraege' => function () use ($wurzel) {
        // Sonst zeigt eine Sprache "5 Beiträge" und die andere "%d Beitrag".
        $de = require $wurzel . '/lang/de.php';
        $en = require $wurzel . '/lang/en.php';

        foreach ($de as $schluessel => $wert) {
            if (!array_key_exists($schluessel, $en)) {
                continue;   // noch nicht uebersetzt, faellt auf Deutsch zurueck
            }

            if (is_array($wert) !== is_array($en[$schluessel] ?? null)) {
                throw new RuntimeException(sprintf(
                    'Schluessel "%s" ist nur in einer Sprache eine Mehrzahl-Angabe.',
                    $schluessel
                ));
            }
        }
    },

    'Kein Schluessel steht zweimal drin' => function () use ($wurzel) {
        /*
         * PHP nimmt bei einem doppelten Schluessel klaglos den letzten und
         * verwirft den ersten — kein Fehler, keine Warnung. Beim Umstellen
         * auf das Sprachpaket ist mir genau das passiert: 'home' stand oben
         * schon, und ich habe es unten noch einmal angehaengt.
         */
        foreach (['de', 'en'] as $sprache) {
            $roh = file_get_contents($wurzel . '/lang/' . $sprache . '.php');
            preg_match_all("/^\s*'([a-z0-9_]+)'\s*=>/m", $roh, $treffer);

            $zaehler = array_count_values($treffer[1]);
            $doppelt = array_keys(array_filter($zaehler, static fn(int $n): bool => $n > 1));

            if ($doppelt !== []) {
                throw new RuntimeException(sprintf(
                    'In %s.php steht doppelt: %s',
                    $sprache,
                    implode(', ', array_slice($doppelt, 0, 8))
                ));
            }
        }
    },

    'Platzhalter stimmen zwischen den Sprachen ueberein' => function () use ($wurzel) {
        /*
         * Ein fehlender Platzhalter in der Uebersetzung ist kein Schoenheits-
         * fehler: vsprintf wirft dann, und die Seite bleibt weiss. Gefunden
         * wird das sonst erst, wenn jemand auf Englisch umstellt.
         */
        $de = require $wurzel . '/lang/de.php';
        $en = require $wurzel . '/lang/en.php';

        $zaehlen = static function ($wert): int {
            $text = is_array($wert) ? implode(' ', $wert) : (string) $wert;

            return preg_match_all('/%[sd]/', $text);
        };

        foreach ($de as $schluessel => $wert) {
            if (!isset($en[$schluessel])) {
                continue;
            }

            if ($zaehlen($wert) !== $zaehlen($en[$schluessel])) {
                throw new RuntimeException(sprintf(
                    'Schluessel "%s": %d Platzhalter auf Deutsch, %d auf Englisch.',
                    $schluessel,
                    $zaehlen($wert),
                    $zaehlen($en[$schluessel])
                ));
            }
        }
    },
    'Jeder benutzte Schluessel steht auch im Sprachpaket' => function () use ($wurzel) {
        /*
         * Genau anders herum als die Pruefung darueber: Dort ging es um
         * Schluessel ohne Verwendung, hier um Verwendungen ohne Schluessel.
         *
         * Der Fall ist real: Beim Uebersetzen der Profilseite habe ich
         * t('my_profile') in die Vorlage geschrieben und den Eintrag in
         * lang/de.php vergessen. Angezeigt wurde daraufhin "my_profile" —
         * und zwar in BEIDEN Sprachen, weil t() auf den Schluessel selbst
         * zurueckfaellt. Aufgefallen ist es erst nach der Freigabe.
         *
         * Gelesen wird mit dem Tokenizer, nicht mit einer Suche nach Text:
         * Sonst zaehlten die Beispiele in den Kommentaren mit, und die
         * meinen keinen echten Schluessel.
         */
        $texte = load_language('de');
        $fehlt = [];

        $ordner = ['lib', 'views', 'views_new', 'pages', 'admin', 'api', 'modules', 'install'];

        foreach ($ordner as $o) {
            if (!is_dir($wurzel . '/' . $o)) {
                continue;
            }

            $lauf = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($wurzel . '/' . $o, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($lauf as $datei) {
                $pfad = $datei->getPathname();

                // Die Sprachdateien selbst nennen keine Schluessel, sie sind welche.
                if (preg_match('#/lang/[a-z]+\.php$#', $pfad)) {
                    continue;
                }

                if (str_ends_with($pfad, '.tpl.html')) {
                    // Vorlagen sind kein PHP — dort tut es die Suche, weil es
                    // in ihnen keine Kommentare mit Beispielen gibt.
                    preg_match_all('/\bt\(\s*\'([a-z0-9_]+)\'/', file_get_contents($pfad), $treffer);

                    foreach ($treffer[1] as $k) {
                        if (!isset($texte[$k])) { $fehlt[$k] = basename($pfad); }
                    }
                    continue;
                }

                if (!str_ends_with($pfad, '.php')) {
                    continue;
                }

                $marken = token_get_all(file_get_contents($pfad));

                foreach ($marken as $i => $marke) {
                    if (!is_array($marke) || $marke[0] !== T_STRING) { continue; }
                    if ($marke[1] !== 't' && $marke[1] !== 'tn') { continue; }

                    // t( direkt dahinter, und darin ein fester Text
                    $j = $i + 1;
                    while (isset($marken[$j]) && is_array($marken[$j]) && $marken[$j][0] === T_WHITESPACE) { $j++; }
                    if (($marken[$j] ?? null) !== '(') { continue; }

                    $j++;
                    while (isset($marken[$j]) && is_array($marken[$j]) && $marken[$j][0] === T_WHITESPACE) { $j++; }
                    if (!is_array($marken[$j] ?? null) || $marken[$j][0] !== T_CONSTANT_ENCAPSED_STRING) { continue; }

                    $k = trim($marken[$j][1], '"\'');

                    if ($k !== '' && preg_match('/^[a-z0-9_]+$/', $k) && !isset($texte[$k])) {
                        $fehlt[$k] = basename($pfad);
                    }
                }
            }
        }

        if ($fehlt !== []) {
            $liste = [];
            foreach (array_slice($fehlt, 0, 8, true) as $k => $wo) { $liste[] = "$k ($wo)"; }

            throw new RuntimeException(sprintf(
                '%d Schluessel werden benutzt, stehen aber in keiner Sprachdatei: %s',
                count($fehlt),
                implode(', ', $liste)
            ));
        }
    },
    'In den Vorlagen steht kein deutscher Text mehr' => function () use ($wurzel) {
        /*
         * Der Fall, der diese Pruefung ausgeloest hat: Nach der Umstellung
         * auf das Sprachpaket war die Startseite auf Englisch immer noch
         * halb deutsch — "1 Mitglieder · 1 Gäste", "0 Themen · 0 Beiträge".
         *
         * Uebersehen wurde das, weil der Text nicht als Text dastand,
         * sondern in einem Ausdruck: {{ n == 1 ? 'Thema' : 'Themen' }}.
         * Das Suchwerkzeug ueberspringt {{ }} absichtlich — sonst haelt es
         * {% endif %} fuer einen Satz.
         *
         * Hier wird deshalb umgekehrt gesucht: Eine Vorlage darf ueberhaupt
         * keinen deutschen Text enthalten, weder zwischen den Marken noch in
         * einem Ausdruck. Deutsch gehoert in lang/de.php.
         *
         * Erkannt wird es an Umlauten und an den haeufigsten deutschen
         * Funktionswoertern. Das faengt nicht jeden Satz — "Password" und
         * "Avatar" schreiben sich in beiden Sprachen gleich — aber es faengt
         * die, an denen ein Besucher sofort sieht, dass etwas fehlt.
         */
        $deutsch = '/[ÄÖÜäöüß]|\b(der|die|das|und|oder|nicht|noch|kein|keine|dein|deine|'
                 . 'mein|meine|von|für|über|zurück|seit|beim|wurde|wird|sind|werden|'
                 . 'Beitrag|Beiträge|Thema|Themen|Mitglied|Mitglieder|Gast|Gäste)\b/u';

        $funde = [];

        foreach (glob($wurzel . '/views_new/*.tpl.html') ?: [] as $datei) {
            $roh = file_get_contents($datei);

            /*
             * Kommentare duerfen deutsch sein — sie erklaeren den Code und
             * stehen nie auf der Seite. Das gilt fuer die der Vorlagen-
             * Sprache wie fuer die im mitgelieferten JavaScript.
             *
             * Bei den JavaScript-Zeilen wird // nur am Zeilenanfang als
             * Kommentar gelesen. Sonst faellt "https://..." mit darunter.
             */
            $roh = preg_replace('/\{#.*?#\}/s', '', $roh);
            $roh = preg_replace('#/\*.*?\*/#s', '', $roh);
            $roh = preg_replace('#^\s*//.*$#m', '', $roh);

            $zeilen = explode("\n", $roh);

            foreach ($zeilen as $nr => $zeile) {
                // Marken raus, Entities raus — uebrig bleibt, was jemand liest.
                $sichtbar = preg_replace('/\{[{%].*?[}%]\}/', ' ', $zeile);
                $sichtbar = preg_replace('/<[^>]*>/', ' ', $sichtbar);
                $sichtbar = preg_replace('/&[a-z]+;|&#\d+;/i', ' ', $sichtbar);

                /*
                 * Dazu die Attribute, die der Besucher zu sehen bekommt.
                 * Die stehen INNERHALB einer Marke und waeren oben gerade
                 * mit weggeschnitten worden — genau so ist mir
                 * data-label="Letzter Beitrag" entgangen.
                 */
                if (preg_match_all('/\b(?:title|placeholder|aria-label|alt|data-label)="([^"<]*)"/', $zeile, $a)) {
                    $sichtbar .= ' ' . implode(' ', $a[1]);
                }

                // Dazu die festen Texte INNERHALB der Ausdruecke.
                $inAusdruck = '';
                if (preg_match_all('/\{[{%](.*?)[}%]\}/', $zeile, $m)) {
                    foreach ($m[1] as $ausdruck) {
                        // Schluessel des Sprachpakets sind klein_mit_strich
                        // und stehen hier zu Recht.
                        if (preg_match_all("/'([^']*)'/", $ausdruck, $s)) {
                            foreach ($s[1] as $wert) {
                                if (preg_match('/^[a-z0-9_.\/#?=&-]*$/', $wert)) { continue; }
                                $inAusdruck .= ' ' . $wert;
                            }
                        }
                    }
                }

                foreach ([$sichtbar, $inAusdruck] as $text) {
                    if (trim($text) !== '' && preg_match($deutsch, $text)) {
                        $funde[] = basename($datei) . ':' . ($nr + 1) . ' — ' . trim(preg_replace('/\s+/', ' ', $text));
                        break;
                    }
                }
            }
        }

        if ($funde !== []) {
            throw new RuntimeException(sprintf(
                "%d Stellen mit deutschem Text in den Vorlagen:\n        %s",
                count($funde),
                implode("\n        ", array_slice($funde, 0, 10))
            ));
        }
    },
];
