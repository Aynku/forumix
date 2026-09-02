<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
/**
 * modules/ki/lib/befehle.php – Was die Schreibhilfe kann
 *
 * Jeder Befehl ist eine Anweisung an das Modell plus die Frage, ob sein
 * Ergebnis eine Kennzeichnung verdient.
 *
 * 'kennzeichnet' unterscheidet Korrektur von Autorschaft: wer seine Tippfehler
 * beheben laesst, hat den Beitrag trotzdem selbst geschrieben. Wer einen Text
 * zusammenfassen oder uebersetzen laesst, nicht mehr ganz. Der Betreiber kann
 * die Grenze im Adminbereich verschieben, die Vorgabe steht hier.
 */

/**
 * Regeln, die fuer jeden Befehl gelten.
 *
 * Der letzte Absatz ist der wichtigste: das Modell soll nichts hinzuerfinden.
 * Eine Schreibhilfe, die beim Glattbuegeln eine Angabe verbessert, die gar
 * nicht falsch war, richtet in einem Forum mehr Schaden an als ein Tippfehler.
 */
const KI_GRUNDREGELN = <<<'TXT'
Du bearbeitest den Beitrag eines Forenbenutzers.

Gib ausschliesslich den bearbeiteten Text zurueck. Keine Einleitung, keine
Erklaerung, keine Anfuehrungszeichen um das Ganze, keine Rueckfrage.

Der Text ist in BBCode geschrieben. Tags wie [b], [i], [url], [code], [quote],
[img] bleiben unveraendert stehen, samt ihrer Schreibweise und Reihenfolge.
Inhalt zwischen [code] und [/code] wird nie angefasst — das ist Quelltext.
Inhalt zwischen [quote] und [/quote] wird nie angefasst — das sind die Worte
eines anderen.

Aendere nichts an der Aussage. Fuege keine Angaben hinzu, die nicht im Text
stehen, und entferne keine Einschraenkungen. Verneinungen bleiben Verneinungen.
Wenn du eine Angabe fuer sachlich falsch haeltst, laesst du sie trotzdem stehen.

Die Stimme des Verfassers bleibt. Wer umgangssprachlich schreibt, schreibt
danach umgangssprachlich; wer knapp schreibt, schreibt danach knapp. Mache
keinen gehobenen Text aus einem lockeren. Anreden an die anderen Leser
("Nicht dass es bei euch auch so lange dauert") gehoeren zum Beitrag und
bleiben stehen.

Antworte in der Sprache des Textes.
TXT;

/**
 * Die Befehle. Reihenfolge = Reihenfolge im Menue.
 */
function ki_befehle(): array {
    return [
        'rechtschreibung' => [
            'name'         => 'ki_cmd_spelling',
            'hinweis'      => 'ki_cmd_spelling_hint',
            'system'       => KI_GRUNDREGELN . "\n\n"
                . "Korrigiere Rechtschreibung, Zeichensetzung und Grammatik.\n"
                . "Formuliere nicht um. Wortwahl, Satzbau und Ton bleiben, wie sie sind.\n"
                . "Was richtig geschrieben ist, bleibt unveraendert stehen.",
            'kennzeichnet' => false,
            'urteil'       => false,
        ],
        'kuerzen' => [
            'name'         => 'ki_cmd_shorten',
            'hinweis'      => 'ki_cmd_shorten_hint',
            'system'       => KI_GRUNDREGELN . "\n\n"
                . "Kuerze den Text. Streiche, was zweimal dasteht, und Umwege in der\n"
                . "Formulierung — nicht die Umgangssprache, nicht die Anrede, nicht den Ton.\n"
                . "Jede eigenstaendige Aussage bleibt erhalten — auch Nebenbedingungen,\n"
                . "Einschraenkungen, Ausnahmen und der letzte Satz. Lieber weniger kuerzen\n"
                . "als etwas verlieren.",
            'kennzeichnet' => true,
            'urteil'       => true,
        ],
        'zusammenfassen' => [
            'name'         => 'ki_cmd_summarise',
            'hinweis'      => 'ki_cmd_summarise_hint',
            'system'       => KI_GRUNDREGELN . "\n\n"
                . "Fasse den Text in wenigen Saetzen zusammen.\n"
                . "Nenne die Kernaussagen. Was offen oder strittig ist, bleibt als offen\n"
                . "oder strittig erkennbar — mache aus einer Vermutung keine Tatsache.",
            'kennzeichnet' => true,
            'urteil'       => true,
        ],
        'uebersetzen' => [
            'name'         => 'ki_cmd_translate',
            'hinweis'      => 'ki_cmd_translate_hint',
            'system'       => KI_GRUNDREGELN . "\n\n"
                . "Uebersetze den Text nach {ZIEL}. Diese Anweisung ersetzt die Regel,\n"
                . "in der Sprache des Textes zu antworten.\n"
                . "Uebersetze natuerlich, nicht Wort fuer Wort. Fachbegriffe und\n"
                . "Eigennamen bleiben stehen, wenn sie in der Zielsprache ueblich sind.",
            'kennzeichnet' => true,
            'urteil'       => false,
            'ziel'         => true,   // braucht eine Zielsprache
        ],
    ];
}

/** Sprachen, in die uebersetzt werden kann. */
const KI_SPRACHEN = [
    'de' => 'Deutsch',
    'en' => 'Englisch',
    'fr' => 'Französisch',
    'es' => 'Spanisch',
    'it' => 'Italienisch',
    'nl' => 'Niederländisch',
    'pl' => 'Polnisch',
    'tr' => 'Türkisch',
];

/**
 * Dieselben Sprachen, wie sie im Auswahlfeld stehen sollen.
 *
 * WARUM getrennt von KI_SPRACHEN: Die Konstante liefert den Namen, der im
 * Auftrag an das Modell steht ("Uebersetze nach Franzoesisch"). Der ist
 * Teil eines durchgehend deutschen Systemtextes und bleibt deutsch. Was der
 * Schreibende im Auswahlfeld liest, ist etwas anderes — das gehoert in seine
 * Sprache.
 */
function ki_sprachen_anzeige(): array {
    $aus = [];

    foreach (KI_SPRACHEN as $code => $deutsch) {
        $schluessel = 'ki_sprache_' . $code;
        $text       = t($schluessel);
        $aus[$code] = $text === $schluessel ? $deutsch : $text;
    }

    return $aus;
}

/**
 * Setzt den Systemtext eines Befehls zusammen.
 * Wirft, wenn der Befehl nicht existiert — der Name kommt aus der Anfrage.
 */
function ki_system_text(string $befehl, string $ziel = ''): string {
    $alle = ki_befehle();
    if (!isset($alle[$befehl])) {
        throw new KiFehler('Unbekannter Befehl.');
    }
    $text = $alle[$befehl]['system'];

    if (!empty($alle[$befehl]['ziel'])) {
        if (!isset(KI_SPRACHEN[$ziel])) {
            throw new KiFehler('Keine gültige Zielsprache.');
        }
        $text = str_replace('{ZIEL}', KI_SPRACHEN[$ziel], $text);
    }
    return $text;
}

/**
 * Kennzeichnet dieser Befehl den Beitrag?
 * Der Betreiber darf die Vorgabe verschieben, aber nur je Befehl.
 */
function ki_kennzeichnet(string $befehl, array $cfg): bool {
    $alle = ki_befehle();
    if (!isset($alle[$befehl])) return true;   // im Zweifel kennzeichnen

    $eigene = $cfg['kennzeichnet'] ?? null;
    if (is_array($eigene) && array_key_exists($befehl, $eigene)) {
        return (bool) $eigene[$befehl];
    }
    return (bool) $alle[$befehl]['kennzeichnet'];
}

/**
 * Welches Modell fuer diesen Befehl?
 *
 * Warum ueberhaupt je Befehl: gemessen am selben Beitrag setzen ein kleines
 * und ein grosses Modell dieselben Kommas — bei der Rechtschreibung ist der
 * Unterschied keiner. Beim Kuerzen schon: das kleine Modell schrieb den
 * Beitrag um statt ihn zu straffen und liess den Schlusssatz ganz weg.
 *
 * Deshalb traegt jeder Befehl den Vermerk 'urteil'. Wo er false ist, reicht
 * das schnelle Modell; wo er true ist, entscheidet das Modell mit, was
 * wegfaellt — und das ist keine Aufgabe fuer die guenstigste Stufe.
 *
 * Leer heisst: die Vorgabe des Anbieters (siehe KI_ANBIETER).
 */
function ki_modell_fuer(string $befehl, array $cfg): string {
    $eigene = $cfg['modelle'] ?? [];
    if (is_array($eigene) && !empty($eigene[$befehl])) {
        return trim((string) $eigene[$befehl]);
    }
    return trim((string) ($cfg['modell'] ?? ''));
}

/** Braucht dieser Befehl Urteilskraft? Fuer den Hinweis im Adminbereich. */
function ki_braucht_urteil(string $befehl): bool {
    return !empty(ki_befehle()[$befehl]['urteil']);
}

/**
 * Ist dieser Befehl freigegeben? Ohne Eintrag gilt: ja.
 * Ein frisch dazugekommener Befehl soll nicht stillschweigend gesperrt sein —
 * das faellt niemandem auf, und der Betreiber sucht den Fehler woanders.
 */
function ki_befehl_frei(string $befehl, array $cfg): bool {
    $aus = $cfg['gesperrt'] ?? [];
    return !(is_array($aus) && in_array($befehl, $aus, true));
}
