<?php
/**
 * install/import_text.php – phpBB-Text nach Forumix-BBCode.
 *
 * Eigene Datei, damit die Wandlung geprueft werden kann, ohne den Import
 * anzuwerfen: `install/import-phpbb.php` bricht am Ende ab, diese hier
 * enthaelt nur Funktionen.
 *
 * ── Wie phpBB 3.2/3.3 Beitraege ablegt ────────────────────────────────
 *
 * Nicht als BBCode, sondern als XML:
 *
 *   <r>Ein <B><s>[b]</s>fettes<e>[/b]</e></B> Wort</r>
 *
 * Der Glücksfall dabei: Der urspruengliche BBCode steht vollstaendig im XML —
 * die oeffnende Auszeichnung in <s>, die schliessende in <e>. Das XML muss
 * also nicht ausgewertet werden; es reicht, die Elemente abzustreifen. Zwei
 * Testbeitraege mit Zitaten, Code, Listen, spitzen Klammern, Umlauten und
 * Emoji kamen so zeichengenau zurueck.
 *
 * Zwei Wurzelelemente kommen vor: <t> fuer Text ohne jede Auszeichnung,
 * <r> fuer alles andere.
 */

if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }

/**
 * phpBB kennt Auszeichnungen, die Forumix nicht hat. Was hier nicht steht,
 * bleibt unveraendert — die Namen stimmen ueberwiegend ueberein.
 */
const IMPORT_UNBEKANNT = [
    // Schriftgroesse gibt es in Forumix nicht. Der Text bleibt, die
    // Auszeichnung faellt weg — besser als eine Zeile roher BBCode.
    'size',
    // Schriftart ebenso.
    'font',
    // Flash ist seit 2020 tot.
    'flash',
];

/**
 * Wandelt einen phpBB-Beitrag in Forumix-BBCode.
 *
 * $anhaenge ordnet den Index aus [attachment=N] dem Anzeigenamen zu; ohne
 * die Angabe verschwindet die Auszeichnung, der Dateiname bleibt stehen.
 */
function phpbb_text_zu_bbcode(string $roh, array $anhaenge = []): string
{
    $roh = trim($roh);
    if ($roh === '') return '';

    // Kein XML? Dann steht hier BBCode mit einer eingestreuten Kennung:
    // [b:1a2b3c]. Der Import weist Fassungen unter 3.2 zwar ab — sie sollen
    // erst mit phpBB selbst aktualisiert werden, dessen Text-Reparser den
    // Umbau ohnehin macht. Aber auch in einem 3.3 koennen einzelne Reste im
    // alten Format liegen: der Reparser laeuft im Hintergrund, und was er
    // noch nicht erwischt hat, kaeme sonst mit Kennungen im Text an.
    if (!str_starts_with($roh, '<')) {
        $text = preg_replace('/:[a-z0-9]{5,8}(?=\])/i', '', $roh) ?? $roh;
        return bbcode_angleichen($text, $anhaenge);
    }

    // <br/> ersatzlos: der echte Zeilenumbruch steht daneben.
    $text = str_replace(['<br/>', '<br />', '<br>'], '', $roh);

    // Alles Uebrige abstreifen — der BBCode steckt im Textinhalt.
    $text = strip_tags($text);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1 | ENT_HTML5, 'UTF-8');

    return bbcode_angleichen($text, $anhaenge);
}

/**
 * Gleicht phpBB-BBCode an das an, was Forumix kennt.
 */
function bbcode_angleichen(string $text, array $anhaenge = []): string
{
    // Anhaenge: [attachment=0]bild.png[/attachment]. Forumix haengt Dateien
    // an den Beitrag, nicht in den Text — die Auszeichnung faellt weg, der
    // Dateiname bleibt als Hinweis stehen.
    $text = preg_replace_callback(
        '/\[attachment=(\d+)\](.*?)\[\/attachment\]/is',
        function ($t) use ($anhaenge) {
            $name = trim($t[2]) !== '' ? trim($t[2]) : ($anhaenge[(int) $t[1]] ?? '');
            return $name !== '' ? $name : '';
        },
        $text
    ) ?? $text;

    // Auszeichnungen, die es hier nicht gibt: weg, Inhalt bleibt.
    foreach (IMPORT_UNBEKANNT as $tag) {
        $text = preg_replace('/\[' . $tag . '(=[^\]]*)?\](.*?)\[\/' . $tag . '\]/is', '$2', $text) ?? $text;
    }

    // Nummerierte Listen kennt Forumix nicht — sie werden zur einfachen.
    $text = preg_replace('/\[list=[^\]]*\]/i', '[list]', $text) ?? $text;

    // phpBB schreibt [quote="Name"], Forumix erwartet [quote=Name].
    $text = preg_replace('/\[quote="([^"]*)"\]/i', '[quote=$1]', $text) ?? $text;

    return trim($text);
}
