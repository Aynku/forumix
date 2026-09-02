<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// lib/lang.php – Mehrsprachigkeit

/**
 * Alle Texte einer Sprache — die des Kerns und die der Module.
 *
 * Die Reihenfolge ist Absicht:
 *
 *  1. Module auf Deutsch. Das ist die Grundlage; fehlt einem Modul die
 *     Uebersetzung, steht dort Deutsch statt des nackten Schluessels.
 *  2. Module in der gewaehlten Sprache, darueber.
 *  3. Der Kern zuletzt. Ein Modul kann damit keinen Kerntext ueberschreiben —
 *     weder aus Versehen noch mit Absicht.
 *
 * WARUM je Modul eine eigene Datei: Ein Modul ist ein eigener Ordner mit
 * eigenem Manifest, das sich abschalten und entfernen laesst. Laegen seine
 * Texte im Kern, blieben sie beim Entfernen liegen — und ein fremdes Modul
 * muesste in die Datei des Kerns schreiben, um uebersetzt zu sein.
 */
function load_language(string $code = 'de'): array {
    $code = preg_replace('/[^a-z]/', '', $code);
    if ($code === '' || !file_exists(__DIR__ . '/../lang/' . $code . '.php')) {
        $code = 'de';
    }

    $texte = [];

    foreach (array_unique(['de', $code]) as $stufe) {
        foreach (glob(__DIR__ . '/../modules/*/lang/' . $stufe . '.php') ?: [] as $datei) {
            $texte = array_merge($texte, (array) require $datei);
        }
    }

    foreach (array_unique(['de', $code]) as $stufe) {
        $texte = array_merge($texte, (array) require __DIR__ . '/../lang/' . $stufe . '.php');
    }

    return $texte;
}

/**
 * Welche Sprache gilt gerade?
 *
 * Reihenfolge: Konto-Einstellung, dann Cookie, dann Deutsch.
 *
 * Das Konto wird vorsichtig geholt: Der Installer hat noch keine Datenbank,
 * und die Fehlerseite bei fehlender Datenbank hat sie nicht mehr. In beiden
 * Faellen darf die Sprachwahl nicht selbst zum Fehler werden — dann
 * entscheidet der Cookie, sonst Deutsch.
 */
function current_language(): string {
    if (function_exists('current_user')) {
        try {
            $u = current_user();
            if ($u && !empty($u['language'])) return $u['language'];
        } catch (Throwable) {
            // Keine Datenbank erreichbar — dann eben ohne Konto.
        }
    }

    if (!empty($_COOKIE['forum_lang'])) {
        $c = preg_replace('/[^a-z]/', '', $_COOKIE['forum_lang']);
        if (in_array($c, ['de', 'en'], true)) return $c;
    }
    return 'de';
}

function t(string $key, ...$args): string {
    static $lang = null;
    if ($lang === null) {
        $lang = load_language(current_language());
    }
    $str = $lang[$key] ?? $key;

    // Steht dort eine Mehrzahl-Angabe, nimmt t() die Mehrzahlform. Sonst
    // gaebe es "Array to string conversion" an einer Stelle, die mit dem
    // Fehler nichts zu tun hat.
    if (is_array($str)) {
        $str = $str[1] ?? $str[0] ?? $key;
    }

    if ($args) return vsprintf($str, $args);
    return $str;
}

/**
 * Ein Satz, der sich nach einer Anzahl richtet.
 *
 *   tn('beitraege_zahl', $n)        1 Beitrag / 5 Beiträge
 *   tn('vor_x_tagen', $n, $name)    weitere Werte kommen hinten dran
 *
 * In der Sprachdatei stehen beide Formen als Liste:
 *
 *   'beitraege_zahl' => ['%d Beitrag', '%d Beiträge'],
 *
 * Die Anzahl ist IMMER der erste Platzhalter. Damit steht sie in jeder
 * Sprache dort, wo der Satz sie braucht — im Deutschen vorn, anderswo
 * mittendrin. Genau deshalb geht "1 " . t('beitrag') nicht.
 *
 * ZWEI Formen, nicht mehr: Deutsch und Englisch brauchen Einzahl und
 * Mehrzahl, sonst nichts. Sprachen mit mehr Formen (Polnisch, Russisch)
 * bekaeme man damit nicht hin — dann waere hier eine Regel je Sprache
 * faellig. Solange available_languages() de und en kennt, waere das Vorrat
 * auf Verdacht.
 */
function tn(string $key, int $anzahl, ...$args): string {
    static $lang = null;
    if ($lang === null) {
        $lang = load_language(current_language());
    }
    $eintrag = $lang[$key] ?? $key;

    if (is_array($eintrag)) {
        // Fehlt eine der beiden Formen, ist die vorhandene besser als nichts.
        $str = $anzahl === 1
            ? ($eintrag[0] ?? $eintrag[1] ?? $key)
            : ($eintrag[1] ?? $eintrag[0] ?? $key);
    } else {
        // Kein Mehrzahl-Eintrag: dann eben der eine Satz, den es gibt.
        $str = $eintrag;
    }

    return vsprintf($str, array_merge([$anzahl], $args));
}

/**
 * Die Texte, die das JavaScript braucht.
 *
 * WARUM eine feste Liste und nicht alle: Das Sprachpaket hat ueber 700
 * Eintraege. Die alle in jede Seite zu schreiben waere Ballast in jedem
 * Aufruf, fuer ein Dutzend Saetze.
 *
 * Wer in assets/forum.js einen neuen Text braucht, traegt den Schluessel
 * hier ein — sonst faellt T() im Browser auf den Schluessel zurueck, und
 * das sieht man sofort.
 */
function js_texte(): array {
    $schluessel = [
        'js_search_running', 'js_no_matches', 'js_posts_suffix',
        'js_reaction_failed', 'js_reaction_failed_console',
        'js_draft_restore', 'js_draft_restored', 'js_draft_saved',
        'js_network_error', 'js_preview_empty',
        'bookmarks', 'js_saved',
        'react_like', 'react_love', 'react_haha', 'react_wow', 'react_sad', 'react_thanks',
        'js_design_zuruecksetzen', 'js_abtrennen_titel', 'js_email_ungueltig',
        'js_fehler_praefix', 'js_vorschau_fehlgeschlagen', 'js_schliessen',
    ];

    $aus = [];
    foreach ($schluessel as $k) {
        $aus[$k] = t($k);
    }

    return $aus;
}

/**
 * Eine Zahl so schreiben, wie die gewaehlte Sprache sie schreibt.
 *
 * 1.234,5 im Deutschen, 1,234.5 im Englischen — Punkt und Komma sind
 * vertauscht. In den Vorlagen stand bisher number_format($n, 0, ',', '.')
 * mit fest eingetragenen deutschen Zeichen. Das faellt kaum auf, macht aus
 * "1.234 Beiträge" auf Englisch aber "1.234 posts" — also tausend Mal
 * weniger, als dastehen sollte.
 */
function zahl(int|float $wert, int $nachkomma = 0): string {
    return number_format($wert, $nachkomma, t('zahl_dezimal'), t('zahl_tausender'));
}

function available_languages(): array {
    return ['de' => 'Deutsch', 'en' => 'English'];
}
