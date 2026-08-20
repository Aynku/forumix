<?php
/**
 * Volltextsuche — und der Rückweg, wenn sie nicht zur Verfügung steht.
 * --------------------------------------------------------------------------
 * Bis 1.3.x lief die Suche mit `LIKE '%wort%'`: kein Index, keine Treffergüte,
 * und die ganze Tabelle bei jedem Aufruf. Ab 1.4.0 fragt sie den Volltext-Index
 * (Migration v29) und sortiert nach Güte.
 *
 * DREI FAELLE, in denen weiterhin LIKE gesucht wird — sonst faende die Suche
 * ploetzlich weniger als vorher:
 *
 *   1. Die Migration ist noch nicht gelaufen. (Wie beim Papierkorb: ein Update
 *      darf eine Installation nicht halb lahmlegen, nur weil zwei Schritte
 *      nicht in derselben Sekunde passieren.)
 *   2. Alle Suchbegriffe sind kuerzer als die Mindestwortlaenge des Index —
 *      der kennt sie gar nicht. "SQL" mit drei Zeichen geht, "js" nicht.
 *   3. Die Suche steht in Anfuehrungszeichen: "genau diese Wendung". Das ist
 *      eine Aussage ueber die Reihenfolge, und die beantwortet LIKE ehrlicher.
 */
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }

/** Steht der Volltext-Index bereit? Einmal je Aufruf geholt und gemerkt. */
function volltext_bereit(): bool
{
    static $bereit = null;
    if ($bereit === null) {
        try {
            $bereit = (bool) db_fetch("SHOW INDEX FROM posts WHERE Key_name = 'ft_inhalt'")
                   && (bool) db_fetch("SHOW INDEX FROM threads WHERE Key_name = 'ft_titel'");
        } catch (Throwable $e) {
            $bereit = false;
        }
    }
    return $bereit;
}

/** Mindestwortlaenge des Index, wie die Datenbank sie gerade fuehrt. */
function volltext_mindestlaenge(): int
{
    static $laenge = null;
    if ($laenge === null) {
        try {
            $r = db_fetch('SELECT @@innodb_ft_min_token_size AS n');
            $laenge = max(1, (int) ($r['n'] ?? 3));
        } catch (Throwable $e) {
            $laenge = 3;
        }
    }
    return $laenge;
}

/**
 * Zerlegt die Eingabe in Begriffe, die der Index kennt.
 *
 * Die Operatoren des booleschen Modus (+ - * " ( ) ~ < >) werden entfernt.
 * Sonst baut sich jemand — aus Versehen oder mit Absicht — einen Ausdruck,
 * den MariaDB mit einem Fehler quittiert.
 *
 * @return string[] Leer, wenn kein Begriff lang genug ist.
 */
function volltext_begriffe(string $eingabe): array
{
    $mindest = volltext_mindestlaenge();
    $raus    = [];

    foreach (preg_split('/\s+/u', trim($eingabe)) ?: [] as $wort) {
        $wort = preg_replace('/[+\-*"()~<>@]/u', '', $wort) ?? '';
        if (mb_strlen($wort) < $mindest) continue;
        $raus[] = $wort;
    }
    return $raus;
}

/** Ein Begriff als boolescher Ausdruck: muss vorkommen, auch als Wortanfang. */
function volltext_ausdruck(string $begriff): string
{
    return '+' . $begriff . '*';
}

/**
 * Baut die Suchbedingung.
 *
 * JE BEGRIFF eine Klammer, darin Titel ODER Text — und die Klammern mit UND
 * verbunden. Nicht andersherum: Wer "Zwiebelkuchen Hefe" sucht, findet sonst
 * nichts, wenn das eine Wort im Titel und das andere im Beitrag steht. Genau
 * das ist beim Bauen passiert.
 *
 * @return array{sql: string, params: array<int, string>, volltext: bool}
 */
function such_bedingung(string $eingabe): array
{
    $woertlich = mb_strlen($eingabe) > 1
              && str_starts_with($eingabe, '"')
              && str_ends_with($eingabe, '"');

    if (!$woertlich && volltext_bereit()) {
        $begriffe = volltext_begriffe($eingabe);
        if ($begriffe !== []) {
            $teile = [];
            $params = [];
            foreach ($begriffe as $b) {
                $teile[] = '(MATCH(p.content) AGAINST (? IN BOOLEAN MODE)'
                         . ' OR MATCH(t.title) AGAINST (? IN BOOLEAN MODE))';
                $params[] = volltext_ausdruck($b);
                $params[] = volltext_ausdruck($b);
            }
            return [
                'sql'      => '(' . implode(' AND ', $teile) . ')',
                'params'   => $params,
                'volltext' => true,
            ];
        }
    }

    // Rueckweg mit LIKE — und zwar mit derselben Bedeutung wie oben: jedes Wort
    // muss vorkommen, im Titel oder im Text. Ein einziges LIKE ueber die ganze
    // Eingabe sucht die WENDUNG und findet "Zwiebelkuchen Hefe" nur dort, wo
    // beides genau so hintereinander steht. Das ist etwas anderes als eine
    // Suche nach zwei Woertern.
    //
    // In Anfuehrungszeichen ist die Wendung dagegen genau das, was gemeint ist:
    // dann bleibt es bei einem Vergleich.
    $begriffe = $woertlich
        ? [trim($eingabe, '"')]
        : array_values(array_filter(preg_split('/\s+/u', trim($eingabe)) ?: [], fn($w) => $w !== ''));

    if ($begriffe === []) $begriffe = [$eingabe];

    $teile = []; $params = [];
    foreach ($begriffe as $wort) {
        $teile[]  = '(t.title LIKE ? OR p.content LIKE ?)';
        $params[] = '%' . $wort . '%';
        $params[] = '%' . $wort . '%';
    }

    return [
        'sql'      => '(' . implode(' AND ', $teile) . ')',
        'params'   => $params,
        'volltext' => false,
    ];
}

/**
 * Die Treffergüte fuer die Sortierung: alle Begriffe zusammengezaehlt, der
 * Titel doppelt gewichtet — wonach ein Thema heisst, ist meist naeher dran als
 * ein Wort irgendwo im Text.
 *
 * @return array{sql: string, params: array<int, string>}
 */
function such_relevanz(string $eingabe): array
{
    $teile = []; $params = [];
    foreach (volltext_begriffe($eingabe) as $b) {
        $teile[] = 'MATCH(p.content) AGAINST (? IN BOOLEAN MODE)';
        $teile[] = 'MATCH(t.title)  AGAINST (? IN BOOLEAN MODE) * 2';
        $params[] = volltext_ausdruck($b);
        $params[] = volltext_ausdruck($b);
    }
    return ['sql' => $teile ? '(' . implode(' + ', $teile) . ')' : '0', 'params' => $params];
}
