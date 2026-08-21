<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
/**
 * modules/ki/lib/schutz.php – Was nicht bearbeitet werden darf
 *
 * Zwei Sorten Text stehen in einem Beitrag, die niemand umformulieren lassen
 * darf:
 *
 *  [quote] — die Worte eines anderen. Wer sie glaettet, legt jemandem etwas
 *            in den Mund, das er so nicht gesagt hat. Das ist kein
 *            Schoenheitsfehler, das ist eine Faelschung.
 *  [code]  — Quelltext. Ein Modell, das "verbessert", macht aus
 *            PassivePorts 49152-65534 schnell etwas Aufgeraeumtes, das nicht
 *            mehr laeuft.
 *
 * Die Anweisung im Systemtext sagt das auch. Eine Anweisung ist aber eine
 * Bitte: sie kann ueberlesen werden, und je kleiner das Modell, desto eher.
 * Hier wird der Text stattdessen gar nicht erst mitgeschickt — an seiner
 * Stelle steht eine Marke, und danach steht er wieder da, Zeichen fuer
 * Zeichen.
 */

/**
 * Die Marke, die statt eines Blocks mitgeht.
 *
 * Sie muss drei Dinge koennen: unveraendert durch ein Sprachmodell kommen,
 * in normalem Text nicht vorkommen, und beim Ruecktausch eindeutig sein.
 * Die eckigen Doppelklammern sind in BBCode-Texten nicht ueblich, und die
 * Nummer macht jede Marke einzeln wiederfindbar.
 */
function ki_marke(int $nummer): string {
    return '⟦BLOCK' . $nummer . '⟧';
}

/**
 * Schneidet [quote]- und [code]-Bloecke heraus.
 *
 * Zaehlt Verschachtelung mit: [quote][quote]x[/quote]y[/quote] ist EIN Block,
 * kein regulaerer Ausdruck bekommt das richtig hin. Gezaehlt wird nur das
 * jeweils eigene Tag — ein [code] in einem [quote] gehoert zum Zitat.
 *
 * @return array{0: string, 1: array<int, string>} Text mit Marken, Bloecke
 */
function ki_bloecke_heraus(string $text): array {
    $tags    = ['quote', 'code'];
    $bloecke = [];
    $aus     = '';
    $i       = 0;
    $laenge  = strlen($text);

    while ($i < $laenge) {
        $offen = null;
        foreach ($tags as $tag) {
            // [quote] und [quote=Name] gelten beide.
            if (preg_match('/\G\[' . $tag . '(=[^\]]*)?\]/i', $text, $t, 0, $i)) {
                $offen = ['tag' => $tag, 'laenge' => strlen($t[0])];
                break;
            }
        }

        if ($offen === null) {
            $aus .= $text[$i];
            $i++;
            continue;
        }

        // Ab hier das passende Ende suchen, Verschachtelung mitzaehlen.
        $start = $i;
        $tiefe = 0;
        $j     = $i;
        while ($j < $laenge) {
            if (preg_match('/\G\[' . $offen['tag'] . '(=[^\]]*)?\]/i', $text, $t, 0, $j)) {
                $tiefe++;
                $j += strlen($t[0]);
                continue;
            }
            if (preg_match('/\G\[\/' . $offen['tag'] . '\]/i', $text, $t, 0, $j)) {
                $tiefe--;
                $j += strlen($t[0]);
                if ($tiefe === 0) break;
                continue;
            }
            $j++;
        }

        if ($tiefe !== 0) {
            // Kein Ende gefunden — offenes Tag. Dann ist es kein Block,
            // sondern Text, der zufaellig so aussieht.
            $aus .= $text[$i];
            $i++;
            continue;
        }

        $bloecke[] = substr($text, $start, $j - $start);
        $aus      .= ki_marke(count($bloecke) - 1);
        $i         = $j;
    }

    return [$aus, $bloecke];
}

/**
 * Trennt Bloecke am Anfang und am Ende ganz ab.
 *
 * Der Grund ist gemessen, nicht gedacht: ein Zitat am Beitragsanfang — in
 * einem Forum der Normalfall — wurde vom Modell samt Marke weggelassen. Der
 * Auffang in ki_bloecke_zurueck() haengt es dann hinten an, was zwar nichts
 * verliert, aber die Reihenfolge zerstoert.
 *
 * Was am Rand steht, braucht ueberhaupt keine Marke: es wird abgeschnitten,
 * der Rest geht zur Bearbeitung, und danach wird es wieder davor- und
 * dahintergesetzt. Kein Durchreichen, kein Vertrauen noetig. Nur Bloecke
 * MITTEN im Text brauchen weiterhin eine Marke — dort steht rechts und links
 * Text, der zusammengehoert.
 *
 * @return array{0: string, 1: string, 2: string} Vorspann, Mitte, Nachspann
 */
function ki_raender_abtrennen(string $text): array {
    $vorne  = '';
    $hinten = '';

    // Vorne: solange der Text mit einem Block (samt Leerraum davor) beginnt.
    while (true) {
        if (!preg_match('/^(\s*)(\[(quote|code)(=[^\]]*)?\])/i', $text, $t)) break;
        [$block, $rest] = ki_ersten_block($text, strlen($t[1]), strtolower($t[3]));
        if ($block === null) break;
        $vorne .= substr($text, 0, strlen($t[1])) . $block;
        $text   = $rest;
    }

    // Hinten: dieselbe Frage von der anderen Seite. Gesucht wird der letzte
    // Block, dem nur noch Leerraum folgt.
    while (preg_match('/\[\/(quote|code)\]\s*$/i', $text, $t)) {
        $tag = strtolower($t[1]);
        $pos = ki_letzter_blockanfang($text, $tag);
        if ($pos === null) break;
        $hinten = substr($text, $pos) . $hinten;
        $text   = substr($text, 0, $pos);
    }

    return [$vorne, $text, $hinten];
}

/**
 * Liest ab $von einen vollstaendigen Block, Verschachtelung mitgezaehlt.
 * @return array{0: ?string, 1: string} Block (oder null), Rest dahinter
 */
function ki_ersten_block(string $text, int $von, string $tag): array {
    $tiefe  = 0;
    $j      = $von;
    $laenge = strlen($text);

    while ($j < $laenge) {
        if (preg_match('/\G\[' . $tag . '(=[^\]]*)?\]/i', $text, $t, 0, $j)) {
            $tiefe++;
            $j += strlen($t[0]);
            continue;
        }
        if (preg_match('/\G\[\/' . $tag . '\]/i', $text, $t, 0, $j)) {
            $tiefe--;
            $j += strlen($t[0]);
            if ($tiefe === 0) {
                return [substr($text, $von, $j - $von), substr($text, $j)];
            }
            continue;
        }
        $j++;
    }
    return [null, $text];
}

/** Wo beginnt der Block, der den Text abschliesst? null, wenn keiner. */
function ki_letzter_blockanfang(string $text, string $tag): ?int {
    $tiefe = 0;
    for ($i = strlen($text) - 1; $i >= 0; $i--) {
        if (preg_match('/\G\[\/' . $tag . '\]/i', $text, $t, 0, $i)) { $tiefe++; continue; }
        if (preg_match('/\G\[' . $tag . '(=[^\]]*)?\]/i', $text, $t, 0, $i)) {
            $tiefe--;
            if ($tiefe === 0) return $i;
        }
    }
    return null;
}

/**
 * Setzt die Bloecke wieder ein.
 *
 * Fehlt eine Marke in der Antwort, hat das Modell sie verschluckt. Der Block
 * geht dann NICHT verloren — er wird angehaengt, und der Aufrufer erfaehrt
 * es. Ein stillschweigend fehlendes Zitat waere das Schlimmste von allem:
 * der Beitrag saehe vollstaendig aus.
 *
 * @return array{0: string, 1: int} Text, Zahl der verschluckten Bloecke
 */
function ki_bloecke_zurueck(string $text, array $bloecke): array {
    $fehlend = 0;

    foreach ($bloecke as $nr => $block) {
        $marke = ki_marke($nr);
        if (str_contains($text, $marke)) {
            $text = str_replace($marke, $block, $text);
        } else {
            $fehlend++;
            $text = rtrim($text) . "\n\n" . $block;
        }
    }

    return [$text, $fehlend];
}

/**
 * Bleibt nach dem Herausschneiden ueberhaupt etwas uebrig?
 *
 * Ein Beitrag, der nur aus einem Zitat besteht, hat nichts zu bearbeiten.
 * Ohne diese Frage ginge eine Anfrage raus, die aus einer Marke besteht, und
 * das Modell wuerde sich etwas dazu ausdenken.
 */
function ki_hat_eigenen_text(string $ohneBloecke): bool {
    $rest = preg_replace('/⟦BLOCK\d+⟧/u', '', $ohneBloecke);
    return trim((string) $rest) !== '';
}
