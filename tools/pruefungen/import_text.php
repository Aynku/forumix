<?php
/**
 * phpBB-Text nach Forumix-BBCode.
 *
 * Der Kern des Importers: phpBB 3.2 und 3.3 legen Beitraege nicht als BBCode
 * ab, sondern als XML — der urspruengliche BBCode steckt darin in <s> und
 * <e>. Abgestreift kommt er zeichengenau zurueck; geprueft wurde das an zwei
 * echten Beitraegen aus einem phpBB 3.3.14.
 *
 * Was hier faellt, faellt still: ein Beitrag mit rohem BBCode sieht aus wie
 * ein Tippfehler des Verfassers, nicht wie ein Fehler des Importers. Deshalb
 * steht jede Auszeichnung, die vorkommen kann, als eigener Fall da.
 *
 * Braucht weder Datenbank noch Pruefstand.
 */

if (!defined('FORUM_PRUEFLAUF')) { http_response_code(403); die('Forbidden'); }

$wurzel = defined('FORUM_WURZEL') ? FORUM_WURZEL : dirname(__DIR__, 2);
require_once $wurzel . '/install/import_text.php';

/** Kurzform: XML-Huelle um einen Ausschnitt. */
$r = fn(string $inneres): string => '<r>' . $inneres . '</r>';

return [
    'Die gaengigen Auszeichnungen kommen unveraendert durch' => function () use ($r) {
        $faelle = [
            '<B><s>[b]</s>fett<e>[/b]</e></B>'                   => '[b]fett[/b]',
            '<I><s>[i]</s>kursiv<e>[/i]</e></I>'                 => '[i]kursiv[/i]',
            '<U><s>[u]</s>unter<e>[/u]</e></U>'                  => '[u]unter[/u]',
            '<COLOR color="#FF0000"><s>[color=#FF0000]</s>rot<e>[/color]</e></COLOR>'
                                                                 => '[color=#FF0000]rot[/color]',
            '<URL url="https://a.example"><s>[url=https://a.example]</s>A<e>[/url]</e></URL>'
                                                                 => '[url=https://a.example]A[/url]',
            '<CODE><s>[code]</s>x = 1<e>[/code]</e></CODE>'      => '[code]x = 1[/code]',
        ];
        foreach ($faelle as $rein => $soll) {
            $ist = phpbb_text_zu_bbcode($r($rein));
            if ($ist !== $soll) throw new RuntimeException("\"$rein\" wurde zu \"$ist\" statt \"$soll\"");
        }
    },

    'Text ohne Auszeichnung steht in <t> statt <r>' => function () {
        $ist = phpbb_text_zu_bbcode('<t>Nur ein Satz.</t>');
        if ($ist !== 'Nur ein Satz.') throw new RuntimeException("Bekommen: \"$ist\"");
    },

    'Was Forumix nicht kennt, verliert die Auszeichnung, nicht den Text' => function () use ($r) {
        // [size] und [font] gibt es hier nicht. Roh stehenzulassen waere das
        // Schlechteste: der Leser sieht dann eckige Klammern im Beitrag.
        $ist = phpbb_text_zu_bbcode($r('<SIZE size="150"><s>[size=150]</s>Gross<e>[/size]</e></SIZE>'));
        if ($ist !== 'Gross') throw new RuntimeException("Bekommen: \"$ist\"");
    },

    'Nummerierte Listen werden zu einfachen' => function () use ($r) {
        $ist = phpbb_text_zu_bbcode($r('<LIST type="decimal"><s>[list=1]</s><LI><s>[*]</s>Eins</LI><e>[/list]</e></LIST>'));
        if (!str_starts_with($ist, '[list]')) throw new RuntimeException("Bekommen: \"$ist\"");
        if (str_contains($ist, '[list='))     throw new RuntimeException('Die Nummerierung blieb stehen');
    },

    'Zitate verlieren die Anfuehrungszeichen um den Namen' => function () use ($r) {
        // phpBB schreibt [quote="Hans"], Forumix erwartet [quote=Hans] —
        // sonst stuende in der Zeile: »"Hans" schrieb«.
        $ist = phpbb_text_zu_bbcode($r('<QUOTE author="Hans"><s>[quote=&quot;Hans&quot;]</s>Text<e>[/quote]</e></QUOTE>'));
        if ($ist !== '[quote=Hans]Text[/quote]') throw new RuntimeException("Bekommen: \"$ist\"");
    },

    'Spitze Klammern im Text ueberleben' => function () use ($r) {
        // Der Fall, an dem ein naiver Wandler Daten zerstoert: Markup, das
        // der Verfasser gemeint hat, gegen Markup, das phpBB erzeugt hat.
        $ist = phpbb_text_zu_bbcode($r('<CODE><s>[code]</s>&lt;div class="a"&gt;&amp;amp;&lt;/div&gt;<e>[/code]</e></CODE>'));
        $soll = '[code]<div class="a">&amp;</div>[/code]';
        if ($ist !== $soll) throw new RuntimeException("Bekommen: \"$ist\" statt \"$soll\"");
    },

    'Umbrueche verdoppeln sich nicht' => function () {
        // phpBB schreibt <br/> UND den echten Umbruch. Wer <br/> zu "\n"
        // macht, bekommt doppelte Leerzeilen durch den ganzen Beitrag.
        $ist = phpbb_text_zu_bbcode("<r>Eine Zeile<br/>\nZweite Zeile</r>");
        if ($ist !== "Eine Zeile\nZweite Zeile") {
            throw new RuntimeException('Bekommen: ' . var_export($ist, true));
        }
    },

    'Die alte Fassung mit Kennung im Tag wird auch gelesen' => function () {
        // phpBB bis 3.1 legt BBCode mit einer eingestreuten Kennung ab.
        $ist = phpbb_text_zu_bbcode('[b:1a2b3c]fett[/b:1a2b3c] und [url=x:1a2b3c]A[/url:1a2b3c]');
        if (str_contains($ist, '1a2b3c')) throw new RuntimeException("Kennung blieb: \"$ist\"");
        if (!str_contains($ist, '[b]fett[/b]')) throw new RuntimeException("Bekommen: \"$ist\"");
    },

    'Anhaenge lassen den Dateinamen stehen' => function () use ($r) {
        // Forumix haengt Dateien an den Beitrag, nicht in den Text. Die
        // Auszeichnung faellt weg, der Name bleibt als Hinweis.
        $ist = phpbb_text_zu_bbcode($r('Siehe <ATTACHMENT filename="plan.pdf" index="0"><s>[attachment=0]</s>plan.pdf<e>[/attachment]</e></ATTACHMENT>'));
        if ($ist !== 'Siehe plan.pdf') throw new RuntimeException("Bekommen: \"$ist\"");
    },

    'Ein leerer Beitrag bleibt leer' => function () {
        foreach (['', '<t></t>', '<r></r>', '   '] as $leer) {
            if (phpbb_text_zu_bbcode($leer) !== '') {
                throw new RuntimeException('Aus ' . var_export($leer, true) . ' wurde etwas');
            }
        }
    },

    'Das Ergebnis ueberlebt den BBCode-Parser ohne Rest' => function () use ($wurzel, $r) {
        // Der eigentliche Zweck: was hier herauskommt, muss Forumix zeichnen
        // koennen. Bleibt rohe Auszeichnung stehen, sieht der Leser Klammern.
        require_once $wurzel . '/lib/functions.php';
        $text = phpbb_text_zu_bbcode($r(
            '<B><s>[b]</s>Fett<e>[/b]</e></B> und '
            . '<QUOTE author="A"><s>[quote=&quot;A&quot;]</s>Zitat<e>[/quote]</e></QUOTE> und '
            . '<LIST><s>[list]</s><LI><s>[*]</s>Punkt</LI><e>[/list]</e></LIST>'
        ));
        $html = strip_tags(bbcode($text));
        if (preg_match('/\[\/?[a-z]+[\]=]/i', $html, $t)) {
            throw new RuntimeException('Rohe Auszeichnung nach dem Zeichnen: ' . $t[0]);
        }
    },
];
