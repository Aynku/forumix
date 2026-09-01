<?php

declare(strict_types=1);

/*
 * Sucht deutschen Text, der noch fest im Code steht.
 *
 *   php tools/texte-finden.php                  alle Bereiche, Übersicht
 *   php tools/texte-finden.php lib pages        nur diese Bereiche
 *   php tools/texte-finden.php --liste lib      jeden Fund einzeln
 *   php tools/texte-finden.php --json > x.json  zum Weiterverarbeiten
 *
 * WARUM ein Werkzeug und keine Handarbeit: Es sind über tausend Stellen, und
 * sie sehen alle gleich aus. Was sich zählen lässt, soll nicht geschätzt
 * werden — und was sich finden lässt, nicht gesucht.
 *
 * WARUM es NICHT umschreibt: Ein deutscher Satz kann in einer SQL-Abfrage
 * stehen, in einem HTML-Attribut oder aus Stücken zusammengesetzt sein. Ob
 * ein Fund ein Satz für den Besucher ist, entscheidet ein Mensch. Dieses
 * Werkzeug findet und schlägt vor, mehr nicht.
 *
 * Gelesen wird mit dem PHP-Zerleger, nicht mit Suchmustern: Nur so ist
 * sicher, was eine Zeichenkette ist und was Markup zwischen zwei Blöcken.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('Nur von der Kommandozeile.');
}

$wurzel = dirname(__DIR__);

/** Die Bereiche, in denen gesucht wird. */
const BEREICHE = ['lib', 'views', 'views_new', 'modules', 'pages', 'admin', 'api', 'install'];

/**
 * Funktionen, deren erstes Argument schon übersetzt ist.
 *
 * Was dort steht, ist ein Schlüssel und kein Satz — das darf nicht noch
 * einmal als Fund auftauchen.
 */
const SCHON_UEBERSETZT = ['t', '__', 'text'];

/**
 * Was nie ein Satz für den Besucher ist.
 *
 * Grob und absichtlich: Lieber ein Fund zu viel, den man wegwinkt, als einer
 * zu wenig, den nachher niemand findet.
 */
const KEIN_SATZ = [
    /*
     * Bezeichner, Pfade, Schlüssel — GROSS/klein beachtet.
     *
     * Vorher stand hier /i, und damit galt „Zwei-Faktor-Authentifizierung"
     * als Bezeichner: nur Buchstaben und Bindestriche. Deutsche
     * Wortketten sehen so aus, sind aber Text. Ein grosser Buchstabe
     * unterscheidet sie von avatar_url und views_new.
     */
    '/^[a-z0-9_.\/-]*$/',
    '/^[\s\W\d]*$/u',                       // nur Zeichen und Ziffern
    '/^[a-z-]+\s*:\s*[^;]+;/i',             // CSS
    '/^(https?:|mailto:|\/|\.\.?\/)/i',     // Adressen und Pfade
    '/^%[sd]$/',                            // reiner Platzhalter
    '/^[A-Z_]{3,}$/',                        // Konstantenname

    /*
     * SQL — und zwar auch die Bruchstücke.
     *
     * Abfragen werden hier aus mehreren Zeichenketten zusammengesetzt, und
     * dann fängt eine davon mit ORDER BY an statt mit SELECT. Ohne diese
     * Zeile sind "ORDER BY p2.created" und "GROUP BY t.thread_id" die
     * häufigsten Funde im ganzen Forum.
     */
    '/^\s*(SELECT|INSERT|UPDATE|DELETE|CREATE|ALTER|DROP|REPLACE|TRUNCATE|FROM'
        . '|WHERE|ORDER\s+BY|GROUP\s+BY|HAVING|LIMIT|OFFSET|UNION|VALUES|SET'
        . '|(LEFT|RIGHT|INNER|OUTER|CROSS)\s+JOIN|JOIN|ON|AND|OR|NOT|AS)\b/i',
    '/\b(COALESCE|COUNT|SUM|MAX|MIN|IFNULL|DISTINCT)\s*\(/i',
    '/\b(IS\s+(NOT\s+)?NULL|!=|<=|>=|=\s*\?|,\s*\?)/i',
    '/^[a-z_]+\.[a-z_]+/i',                 // tabelle.spalte
    '/^[#&?][^\s]*$/',                       // Adressteile wie #post oder &page=last

    /*
     * Vorlagen-Befehle sind kein Text.
     *
     * In den .tpl.html stehen {% if %}, {% endfor %} und {{ wert }} zwischen
     * den Tags und sehen damit aus wie ein Textknoten. Wer sie uebersetzt,
     * macht die Vorlage kaputt — beim ersten Versuch ist mir genau das
     * passiert: aus {% endif %} wurde {{ t('login_endif') }}.
     */
    '/\{[{%#]|[}%#]\}/',

    /*
     * Ein Textknoten enthaelt keine spitzen Klammern.
     *
     * In PHP-Ansichten zerschneidet ein <?= ?> mitten in einem Tag den
     * HTML-Block, und dann findet die Zerlegung das schliessende > nicht
     * mehr. Herausgekommen ist dabei der Fund
     * 'Frage <input type="text" name="question" value="' — Text UND halbes
     * Tag. Wer den ersetzt, macht das Formular kaputt.
     */
    '/[<>]/',

    /*
     * Und zwar auch die HAELFTEN davon.
     *
     * Geht ein {{ ... }} ueber zwei Zeilen, sieht der zweite Teil aus wie
     * Text und endet auf }}. Beim ersten Versuch wurde daraus
     * „0 ? floor(dashAch.unlocked / dashAch.total * 100) : 0 }}%" — ein
     * Fund, der beim Ersetzen die halbe Vorlage zerlegt haette.
     */
];

$argumente = array_slice($argv, 1);
$alsJson   = in_array('--json', $argumente, true);
$alsListe  = in_array('--liste', $argumente, true);
$gewaehlt  = array_values(array_filter(
    $argumente,
    static fn(string $a): bool => !str_starts_with($a, '--')
));

$bereiche = $gewaehlt !== [] ? $gewaehlt : BEREICHE;

foreach ($bereiche as $b) {
    if (!in_array($b, BEREICHE, true)) {
        fwrite(STDERR, sprintf("Unbekannter Bereich \"%s\". Bekannt: %s\n", $b, implode(', ', BEREICHE)));
        exit(2);
    }
}

$funde = [];

foreach ($bereiche as $bereich) {
    $ordner = $wurzel . '/' . $bereich;

    if (!is_dir($ordner)) {
        continue;
    }

    foreach (dateien($ordner) as $datei) {
        foreach (ausDatei($datei) as $fund) {
            $fund['bereich'] = $bereich;
            $fund['datei']   = substr($datei, strlen($wurzel) + 1);
            $funde[]         = $fund;
        }
    }
}

/*
 * Derselbe Satz an zehn Stellen braucht einen Schlüssel, nicht zehn. Deshalb
 * wird nach Text gruppiert und nicht nach Fundstelle.
 */
$nachText = [];

foreach ($funde as $fund) {
    $nachText[$fund['text']]['text']    = $fund['text'];
    $nachText[$fund['text']]['schluessel'] = $fund['schluessel'];
    $nachText[$fund['text']]['stellen'][] = $fund['datei'] . ':' . $fund['zeile'];
    $nachText[$fund['text']]['art']     = $fund['art'];
    $nachText[$fund['text']]['sicher']  = $fund['sicher'];
    $nachText[$fund['text']]['bruchstueck'] = $fund['bruchstueck'];
}

ksort($nachText);

if ($alsJson) {
    echo json_encode(array_values($nachText), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
    exit(0);
}

if ($alsListe) {
    foreach ($nachText as $eintrag) {
        printf(
            "%s  %s\n    %s\n    %s\n\n",
            $eintrag['sicher'] ? '  ' : '? ',
            $eintrag['schluessel'],
            $eintrag['text'],
            implode(', ', array_slice($eintrag['stellen'], 0, 4))
                . (count($eintrag['stellen']) > 4 ? sprintf(' … und %d weitere', count($eintrag['stellen']) - 4) : '')
        );
    }
}

// ── Übersicht ────────────────────────────────────────────────────────
$jeBereich = [];

foreach ($funde as $fund) {
    $jeBereich[$fund['bereich']]['gesamt'] = ($jeBereich[$fund['bereich']]['gesamt'] ?? 0) + 1;

    if (!$fund['sicher']) {
        $jeBereich[$fund['bereich']]['unsicher'] = ($jeBereich[$fund['bereich']]['unsicher'] ?? 0) + 1;
    }

    if ($fund['bruchstueck']) {
        $jeBereich[$fund['bereich']]['stueck'] = ($jeBereich[$fund['bereich']]['stueck'] ?? 0) + 1;
    }
}

echo "\n";
printf("  %-12s %8s %10s %12s\n", 'Bereich', 'Stellen', 'davon ?', 'Bruchstueck');
printf("  %-12s %8s %10s %12s\n", str_repeat('─', 12), str_repeat('─', 8), str_repeat('─', 10), str_repeat('─', 12));

foreach ($bereiche as $b) {
    if (!isset($jeBereich[$b])) {
        continue;
    }

    printf(
        "  %-12s %8d %10d %12d\n",
        $b,
        $jeBereich[$b]['gesamt'],
        $jeBereich[$b]['unsicher'] ?? 0,
        $jeBereich[$b]['stueck'] ?? 0
    );
}

$unsicher = count(array_filter($nachText, static fn(array $e): bool => !$e['sicher']));
$stuecke  = count(array_filter($nachText, static fn(array $e): bool => $e['bruchstueck']));

printf("\n  %d Fundstellen, %d verschiedene Sätze, davon %d unsicher.\n", count($funde), count($nachText), $unsicher);
printf("  Mit ? markiert heißt: sieht nach Text aus, könnte aber etwas anderes sein.\n");
printf("  %d Sätze sind Bruchstücke — sie fangen klein oder mit Satzzeichen an und\n", $stuecke);
printf("  gehören zu einem Satz, der im Code aus Teilen zusammengesetzt wird. Die\n");
printf("  lassen sich nicht einzeln übersetzen; sie müssen erst zu einem Satz mit\n");
printf("  Platzhaltern werden. Das ist die eigentliche Arbeit.\n\n");

// ── Ab hier die Arbeit ───────────────────────────────────────────────

/** @return list<string> */
function dateien(string $ordner): array
{
    $aus  = [];
    $eisen = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ordner, FilesystemIterator::SKIP_DOTS));

    foreach ($eisen as $d) {
        $pfad = $d->getPathname();

        // Sprachdateien sind die Uebersetzung, nicht der Fund.
        if ($d->isFile() && preg_match('/\.(php|html)$/i', $pfad)
            && !preg_match('#/lang/[a-z]+\.php$#', $pfad)) {
            $aus[] = $pfad;
        }
    }

    sort($aus);

    return $aus;
}

/**
 * Holt aus einer Datei, was nach deutschem Text aussieht.
 *
 * @return list<array{text:string,zeile:int,art:string,schluessel:string,sicher:bool}>
 */
function ausDatei(string $datei): array
{
    $inhalt = file_get_contents($datei);

    if ($inhalt === false) {
        return [];
    }

    // Reines HTML hat keinen PHP-Teil — dann ist alles Markup.
    if (!str_contains($inhalt, '<?php')) {
        return ausMarkup($inhalt, 1);
    }

    $aus    = [];
    $stuecke = token_get_all($inhalt);
    $anzahl  = count($stuecke);

    for ($i = 0; $i < $anzahl; $i++) {
        $stueck = $stuecke[$i];

        if (!is_array($stueck)) {
            continue;
        }

        [$art, $wert, $zeile] = $stueck;

        if ($art === T_INLINE_HTML) {
            foreach (ausMarkup($wert, $zeile) as $fund) {
                $aus[] = $fund;
            }

            continue;
        }

        if ($art !== T_CONSTANT_ENCAPSED_STRING) {
            continue;
        }

        if (istSchonUebersetzt($stuecke, $i)) {
            continue;
        }

        $text = substr($wert, 1, -1);
        $text = str_replace(["\\'", '\\"'], ["'", '"'], $text);

        if (!istSatz($text)) {
            continue;
        }

        $aus[] = [
            'text'       => $text,
            'zeile'      => $zeile,
            'art'        => 'zeichenkette',
            'schluessel' => schluesselAus($text),
            'sicher'     => sicher($text),
            'bruchstueck' => istBruchstueck($text),
        ];
    }

    return $aus;
}

/**
 * Steht die Zeichenkette als erstes Argument in t(), __() oder text()?
 *
 * Dann ist sie ein Schlüssel und kein Satz. Geprüft wird rückwärts: erst die
 * Klammer, dann der Name davor.
 */
function istSchonUebersetzt(array $stuecke, int $i): bool
{
    $j = $i - 1;

    while ($j >= 0 && is_array($stuecke[$j]) && $stuecke[$j][0] === T_WHITESPACE) {
        $j--;
    }

    if ($j < 0 || $stuecke[$j] !== '(') {
        return false;
    }

    $j--;

    while ($j >= 0 && is_array($stuecke[$j]) && $stuecke[$j][0] === T_WHITESPACE) {
        $j--;
    }

    return $j >= 0
        && is_array($stuecke[$j])
        && $stuecke[$j][0] === T_STRING
        && in_array(strtolower($stuecke[$j][1]), SCHON_UEBERSETZT, true);
}

/**
 * Text zwischen den Tags — nicht in ihnen.
 *
 * Attribute wie class oder href fallen damit von selbst weg; title und alt
 * gehen leider mit weg, die muss man von Hand nachtragen. Das ist der
 * Kompromiss dafür, dass hier nicht hundert Fehlfunde entstehen.
 *
 * @return list<array{text:string,zeile:int,art:string,schluessel:string,sicher:bool}>
 */
function ausMarkup(string $markup, int $abZeile): array
{
    $aus = [];

    // Skripte und Stilblöcke sind kein Text für den Besucher.
    $sauber = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', $markup) ?? $markup;

    $teile = preg_split('/(<[^>]*>)/', $sauber, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
    $zeile = $abZeile;

    foreach ($teile as $teil) {
        if ($teil === '') {
            continue;
        }

        if ($teil[0] !== '<') {
            $text = trim(html_entity_decode($teil, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

            if (istSatz($text)) {
                $aus[] = [
                    'text'       => $text,
                    'zeile'      => $zeile,
                    'art'        => 'markup',
                    'schluessel' => schluesselAus($text),
                    'sicher'     => sicher($text),
                    'bruchstueck' => istBruchstueck($text),
                ];
            }
        }

        $zeile += substr_count($teil, "\n");
    }

    return $aus;
}

/** Sieht das nach einem Satz für den Besucher aus? */
function istSatz(string $text): bool
{
    $text = trim($text);

    if ($text === '' || mb_strlen($text) < 2 || mb_strlen($text) > 400) {
        return false;
    }

    foreach (KEIN_SATZ as $muster) {
        if (preg_match($muster, $text) === 1) {
            return false;
        }
    }

    // Ohne Buchstaben ist es kein Satz.
    if (preg_match('/\p{L}/u', $text) !== 1) {
        return false;
    }

    // PHP-Variablen darin heissen: zusammengesetzt, das entscheidet ein Mensch.
    return true;
}

/**
 * Deutsch oder nur irgendein Wort?
 *
 * Sicher ist, was einen Umlaut trägt oder aus mehreren Wörtern besteht, von
 * denen eines gross anfängt. Alles andere wird mit ? gemeldet — „Login" und
 * „Reply" stehen auch im Englischen so da.
 */
function sicher(string $text): bool
{
    if (preg_match('/[äöüÄÖÜß]/u', $text) === 1) {
        return true;
    }

    if (str_contains($text, '$') || str_contains($text, '<?')) {
        return false;
    }

    return preg_match('/\p{Lu}\p{Ll}+\s+\p{L}/u', $text) === 1;
}

/**
 * Ist das nur ein Stück eines Satzes?
 *
 * Fängt es klein an oder mit einem Satzzeichen, dann klebt im Code davor
 * noch etwas — meist eine Variable. Solche Stellen kann man nicht einzeln
 * übersetzen: In einer anderen Sprache steht die Zahl woanders im Satz.
 * Sie müssen erst zu EINEM Satz mit %s werden.
 */
function istBruchstueck(string $text): bool
{
    return preg_match('/^[\p{Ll}.,;:!?)\]\-–—]/u', trim($text)) === 1;
}

/**
 * Ein Vorschlag für den Schlüssel.
 *
 * Kleinbuchstaben, Unterstriche, höchstens vier Wörter — lang genug, dass man
 * ihn wiedererkennt, kurz genug, dass er in eine Zeile passt. Der Mensch
 * ändert ihn ohnehin, wo er daneben liegt.
 */
function schluesselAus(string $text): string
{
    $roh = strtr(mb_strtolower(trim($text)), [
        'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss',
    ]);

    $roh   = preg_replace('/%[sd]/', ' ', $roh) ?? $roh;
    $roh   = preg_replace('/[^a-z0-9]+/', ' ', $roh) ?? $roh;
    $worte = array_values(array_filter(explode(' ', trim($roh))));

    if ($worte === []) {
        return 'text_' . substr(hash('xxh128', $text), 0, 6);
    }

    return implode('_', array_slice($worte, 0, 4));
}
