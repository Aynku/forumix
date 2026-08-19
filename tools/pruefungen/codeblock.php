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
];
