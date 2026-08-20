<?php
/**
 * Code-Bloecke: was drinsteht, bleibt drin.
 *
 * Der Umbau von 1.1.0 nimmt [code] ganz aus der BBCode-Kette heraus und setzt
 * ihn erst am Ende wieder ein. Vorher liefen fett, Erwaehnungen, nl2br und die
 * Smiley-Ersetzung auch durch den Code — aus einem :) im Quelltext wurde ein
 * Bild, und an jeder Zeile klebte ein <br>. Diese Pruefungen halten das fest.
 *
 * Braucht keine Datenbank: bbcode() faengt den Fehlschlag der Erwaehnungs-
 * Abfrage selbst ab.
 */

if (!defined('FORUM_PRUEFLAUF')) { http_response_code(403); die('Forbidden'); }

$wurzel = dirname(__DIR__, 2);
if (!defined('FORUM_URL')) define('FORUM_URL', 'https://pruefstand.invalid');
require_once $wurzel . '/lib/functions.php';

$satz = function (string $roh): string { return bbcode($roh); };

return [
    'Inline-Code gibt BBCode als Text aus' => function () use ($satz) {
        // Der Grund, warum es [c] ueberhaupt gibt: ueber BBCode schreiben,
        // ohne dass es wirkt. Als Kursiv-Inhalt haette das Tag zugeschlagen.
        $aus = $satz('Ein [c][b]fett[/b][/c] mitten im Satz.');
        if (str_contains($aus, '<strong>')) throw new RuntimeException('[b] wirkte innerhalb von [c]');
        if (!str_contains($aus, '<code class="bb-inline">')) throw new RuntimeException('Kein bb-inline erzeugt');
        if (!str_contains($aus, '[b]fett[/b]')) throw new RuntimeException('Der Text ist unterwegs verlorengegangen');
    },

    'Inline-Code steht neben Code-Bloecken, ohne sie zu stoeren' => function () use ($satz) {
        $aus = $satz("Vorher [c]eins[/c].\n[code]\nzwei\n[/code]\nNachher [c]drei[/c].");
        if (substr_count($aus, 'class="bb-inline"') !== 2) throw new RuntimeException('Erwartet zwei Inline-Stellen');
        if (!str_contains($aus, 'class="bb-code"')) throw new RuntimeException('Der Block ist abhanden gekommen');
        if (str_contains($aus, "\x02CODE")) throw new RuntimeException('Ein Platzhalter blieb stehen');
    },

    'Fettschrift bleibt im Code stehen' => function () use ($satz) {
        $aus = $satz("[code]\n[b]nicht fett[/b]\n[/code]");
        if (str_contains($aus, '<strong>')) throw new RuntimeException('[b] wurde im Code ausgefuehrt');
        if (!str_contains($aus, '[b]nicht fett[/b]')) throw new RuntimeException('Quelltext verlorengegangen');
    },

    'Kein <br> im Code' => function () use ($satz) {
        $aus = $satz("[code]\nzeile eins\nzeile zwei\n[/code]");
        if (str_contains($aus, '<br')) throw new RuntimeException('nl2br() hat im Code zugeschlagen');
    },

    'Jede Zeile bekommt ein eigenes span' => function () use ($satz) {
        $aus = $satz("[code]\neins\nzwei\ndrei\n[/code]");
        if (substr_count($aus, 'class="bb-zeile"') !== 3) {
            throw new RuntimeException('Erwartet 3 Zeilen, gezaehlt ' . substr_count($aus, 'class="bb-zeile"'));
        }
    },

    'Erwaehnung im Code wird nicht verlinkt' => function () use ($satz) {
        $aus = $satz("[code]\n@Aynku\n[/code]");
        if (str_contains($aus, 'class="mention"')) throw new RuntimeException('@name wurde im Code verlinkt');
    },

    'Bekannte Sprache wird beschriftet und gefaerbt' => function () use ($satz) {
        $aus = $satz("[code=php]\n\$a = 1;\n[/code]");
        if (!str_contains($aus, '>PHP<')) throw new RuntimeException('Beschriftung fehlt');
        if (!str_contains($aus, 'data-modus="application/x-httpd-php-open"')) throw new RuntimeException('Modus fehlt');
    },

    'Unbekannte Sprache faerbt nicht' => function () use ($satz) {
        $aus = $satz("[code=klingonisch]\nHab SoSlI Quch\n[/code]");
        if (str_contains($aus, 'data-modus')) throw new RuntimeException('Fuer Unbekanntes wurde ein Modus gesetzt');
        if (!str_contains($aus, '>Code<')) throw new RuntimeException('Ersatzbeschriftung fehlt');
    },

    'Sprache ohne Modus wird nur beschriftet' => function () use ($satz) {
        $aus = $satz("[code=bash]\nls -la\n[/code]");
        if (!str_contains($aus, '>Shell<')) throw new RuntimeException('Beschriftung fehlt');
        if (str_contains($aus, 'data-modus')) throw new RuntimeException('Modus gesetzt, obwohl keiner vorhanden');
    },

    'Spitze Klammern bleiben maskiert' => function () use ($satz) {
        $aus = $satz("[code]\n<script>alert(1)</script>\n[/code]");
        if (str_contains($aus, '<script>')) throw new RuntimeException('Unmaskiertes <script> im Code');
        if (!str_contains($aus, '&lt;script&gt;')) throw new RuntimeException('Maskierung fehlt');
    },

    'Zitat bekommt eine Kopfzeile' => function () use ($satz) {
        $aus = $satz('[quote=Melina]Hallo[/quote]');
        if (!str_contains($aus, 'bb-quote-kopf')) throw new RuntimeException('Kopfzeile fehlt');
        if (str_contains($aus, '<cite>')) throw new RuntimeException('Alte cite-Zeile noch da');
    },

    'Zitat mit Beitrags-Verweis springt zum Original' => function () use ($satz) {
        $aus = $satz('[quote=Melina;post=42]Hallo[/quote]');
        if (!str_contains($aus, 'href="#post42"')) throw new RuntimeException('Sprungziel fehlt');
        if (!str_contains($aus, 'bb-quote-sprung')) throw new RuntimeException('Sprung-Verweis fehlt');
    },

    'Verschachtelte Zitate behalten ihre Namen' => function () use ($satz) {
        $aus = $satz('[quote=Melina;post=42]Aussen [quote=Aynku;post=41]Innen[/quote][/quote]');
        if (str_contains($aus, 'Aynku;post=41')) {
            throw new RuntimeException('Das innere Zitat traegt die Rohangabe als Namen');
        }
        if (substr_count($aus, 'href="#post41"') < 1) throw new RuntimeException('Sprung zum inneren Beitrag fehlt');
        if (substr_count($aus, 'href="#post42"') < 1) throw new RuntimeException('Sprung zum aeusseren Beitrag fehlt');
        if (substr_count($aus, '<blockquote') !== 2) throw new RuntimeException('Erwartet zwei Zitate');
    },

    'Zitat ohne Namen bekommt keine leere Kopfzeile' => function () use ($satz) {
        $aus = $satz('[quote]Nur Inhalt[/quote]');
        if (str_contains($aus, 'bb-quote-kopf')) throw new RuntimeException('Leere Kopfzeile erzeugt');
        if (!str_contains($aus, 'Nur Inhalt')) throw new RuntimeException('Inhalt verloren');
    },

    'Geloeschte Konten erscheinen ohne laufende Nummer' => function () {
        // Die Loeschung braucht die Nummer, weil username eindeutig ist. Zu
        // sehen ist sie nicht: delete_account.tpl.html verspricht "Geloeschter
        // Benutzer" ohne Zusatz.
        if (zeigename('Gelöschter Benutzer #2') !== 'Gelöschter Benutzer') {
            throw new RuntimeException('Die Nummer steht noch da');
        }
        if (zeigename('Aynku') !== 'Aynku') {
            throw new RuntimeException('Ein normaler Name wurde veraendert');
        }
        // Kein Nachahmen: nur die genaue Form wird gekuerzt. (Registrieren
        // kann man solche Namen ohnehin nicht, siehe register.php.)
        if (zeigename('Gelöschter Benutzer #2 aber nicht wirklich') === 'Gelöschter Benutzer') {
            throw new RuntimeException('Ein aehnlicher Name wurde mitgekuerzt');
        }
    },

    'Mit Beitragskennung bekommt jede Codezeile eine Sprungmarke' => function () {
        $aus = bbcode("[code=php]\neins\nzwei\ndrei\n[/code]", 12);
        foreach ([1, 2, 3] as $n) {
            if (!str_contains($aus, 'id="p12c1-' . $n . '"')) {
                throw new RuntimeException("Marke fuer Zeile $n fehlt");
            }
            if (!str_contains($aus, 'href="#p12c1-' . $n . '"')) {
                throw new RuntimeException("Verweis auf Zeile $n fehlt");
            }
        }
        // Die Nummer darf NICHT im Text stehen, sonst wandert sie beim
        // Kopieren mit.
        if (preg_match('/<a class="bb-zeilennr"[^>]*>[^<]/', $aus)) {
            throw new RuntimeException('Im Nummern-Verweis steht Text');
        }
    },

    'Zwei Codebloecke im selben Beitrag kommen sich nicht ins Gehege' => function () {
        $aus = bbcode("[code]a[/code]\n\n[code]b[/code]", 7);
        if (!str_contains($aus, 'id="p7c1-1"')) throw new RuntimeException('Erster Block ohne Marke');
        if (!str_contains($aus, 'id="p7c2-1"')) throw new RuntimeException('Zweiter Block nutzt dieselbe Marke');
    },

    'Ohne Beitragskennung gibt es keine Marken' => function () {
        // Vorschau, Suchtreffer, Modulausgabe: dort gibt es keinen Beitrag, auf
        // den ein Verweis zeigen koennte. Dann lieber gar keine Marke als eine,
        // die zweimal auf der Seite steht.
        $aus = bbcode("[code]eins[/code]");
        if (str_contains($aus, 'bb-zeilennr')) throw new RuntimeException('Marke ohne Beitragskennung erzeugt');
        if (!str_contains($aus, 'class="bb-zeile"')) throw new RuntimeException('Zeile fehlt ganz');
    },
];
