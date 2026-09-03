<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// lib/sprachen.php – Sprachen und geaenderte Texte aus der Datenbank

/**
 * Die geaenderten Texte einer Sprache, aus der Datenbank.
 *
 * Eine Abfrage je Anfrage, danach im Speicher — wie templates_cache(). Ohne
 * Tabelle (vor der Migration) ist das Ergebnis leer, und alles laeuft weiter
 * wie vorher: die Dateien allein.
 */
function text_overrides(string $sprache): array {
    static $cache = [];

    if (!isset($cache[$sprache])) {
        try {
            $zeilen = db_fetchAll(
                'SELECT schluessel, text FROM text_overrides WHERE sprache = ?',
                [$sprache]
            );
            $cache[$sprache] = array_column($zeilen, 'text', 'schluessel');
        } catch (Throwable $e) {
            $cache[$sprache] = [];
        }
    }

    return $cache[$sprache];
}

/**
 * Welche Sprachen gibt es? Dateien im Paket plus die aus der Datenbank.
 *
 * Der Name kommt aus der Datenbank, wenn er dort steht — so laesst sich auch
 * "Deutsch" umbenennen, ohne eine Datei anzufassen.
 */
function sprachen_alle(): array {
    static $cache = null;
    if ($cache !== null) return $cache;

    $aus = [];

    foreach (glob(__DIR__ . '/../lang/*.php') ?: [] as $datei) {
        $code = basename($datei, '.php');
        if (preg_match('/^[a-z]{2,8}$/', $code)) {
            $aus[$code] = ['name' => strtoupper($code), 'quelle' => 'datei', 'aktiv' => true];
        }
    }

    // Namen, die wir kennen, ohne dass sie jemand eintragen musste.
    $bekannt = ['de' => 'Deutsch', 'en' => 'English'];
    foreach ($bekannt as $code => $name) {
        if (isset($aus[$code])) $aus[$code]['name'] = $name;
    }

    try {
        foreach (db_fetchAll('SELECT code, name, aktiv FROM sprachen ORDER BY code') as $z) {
            $aus[$z['code']] = [
                'name'   => $z['name'],
                'quelle' => isset($aus[$z['code']]) ? 'datei' : 'datenbank',
                'aktiv'  => (bool) $z['aktiv'],
            ];
        }
    } catch (Throwable $e) {
        // Vor der Migration gibt es die Tabelle nicht — dann eben nur Dateien.
    }

    return $cache = $aus;
}

/**
 * Die Texte einer Sprache, wie sie im Paket stehen — ohne die Datenbank.
 *
 * Das ist der Vergleichswert: Er sagt, was der Betreiber geaendert hat und
 * ob ein Update das Original inzwischen angefasst hat.
 */
function texte_aus_dateien(string $sprache): array {
    static $cache = [];

    if (!isset($cache[$sprache])) {
        $code = preg_replace('/[^a-z]/', '', $sprache);
        $texte = [];

        foreach (array_unique(['de', $code]) as $stufe) {
            foreach (glob(__DIR__ . '/../modules/*/lang/' . $stufe . '.php') ?: [] as $datei) {
                $texte = array_merge($texte, (array) require $datei);
            }
        }
        foreach (array_unique(['de', $code]) as $stufe) {
            $pfad = __DIR__ . '/../lang/' . $stufe . '.php';
            if (is_file($pfad)) {
                $texte = array_merge($texte, (array) require $pfad);
            }
        }

        $cache[$sprache] = $texte;
    }

    return $cache[$sprache];
}

/**
 * Zaehlt die Platzhalter eines Eintrags.
 *
 * WARUM das beim Speichern geprueft wird: Wer "%s von %s freigeschaltet"
 * uebernimmt und dabei ein %s verliert, laesst vsprintf() werfen — und die
 * Seite bleibt weiss. Der Fehler faellt dann nicht dem auf, der ihn gemacht
 * hat, sondern dem naechsten Besucher.
 */
function platzhalter_zahl($wert): int {
    $text = is_array($wert) ? implode(' ', $wert) : (string) $wert;
    return preg_match_all('/%[sd]/', $text);
}

/**
 * Prueft einen geaenderten Text gegen das Original.
 *
 * Gibt null zurueck, wenn er in Ordnung ist — sonst den Grund im Klartext.
 */
function text_pruefen(string $sprache, string $schluessel, string $neu): ?string {
    if ($schluessel === '' || !preg_match('/^[a-z0-9_]{2,190}$/', $schluessel)) {
        return t('sprachen_fehler_schluessel');
    }

    if (mb_strlen($neu) > 5000) {
        return t('sprachen_fehler_zu_lang');
    }

    $dateien = texte_aus_dateien($sprache);

    if (!array_key_exists($schluessel, $dateien)) {
        return t('sprachen_fehler_unbekannt');
    }

    $original = $dateien[$schluessel];

    // Mehrzahl-Eintraege lassen sich hier nicht bearbeiten: sie sind eine
    // Liste aus zwei Formen, kein Text. Dafuer braeuchte die Oberflaeche
    // zwei Felder — bis dahin bleiben sie unangetastet.
    if (is_array($original)) {
        return t('sprachen_fehler_mehrzahl');
    }

    if (platzhalter_zahl($neu) !== platzhalter_zahl($original)) {
        return t('sprachen_fehler_platzhalter', platzhalter_zahl($original), platzhalter_zahl($neu));
    }

    return null;
}

/** Speichert einen geaenderten Text und merkt sich den Stand aus dem Paket. */
function text_override_speichern(string $sprache, string $schluessel, string $neu, ?int $userId = null): bool {
    $dateien  = texte_aus_dateien($sprache);
    $original = $dateien[$schluessel] ?? '';

    try {
        db_execute(
            'INSERT INTO text_overrides (sprache, schluessel, text, original_beim_speichern, updated_by)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                text = VALUES(text),
                original_beim_speichern = VALUES(original_beim_speichern),
                updated_by = VALUES(updated_by)',
            [$sprache, $schluessel, $neu, is_array($original) ? '' : (string) $original, $userId]
        );
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

/** Nimmt eine Aenderung zurueck — danach gilt wieder der Text aus dem Paket. */
function text_override_loeschen(string $sprache, string $schluessel): bool {
    try {
        db_execute('DELETE FROM text_overrides WHERE sprache = ? AND schluessel = ?', [$sprache, $schluessel]);
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Welche Aenderungen haengen einem Update hinterher?
 *
 * Steht im Paket inzwischen ein anderer Originaltext als beim Speichern, hat
 * ein Update den Text geaendert und die Anpassung kennt die Aenderung nicht.
 */
function text_overrides_veraltet(string $sprache): array {
    $dateien = texte_aus_dateien($sprache);
    $aus     = [];

    try {
        $zeilen = db_fetchAll(
            'SELECT schluessel, text, original_beim_speichern FROM text_overrides WHERE sprache = ?',
            [$sprache]
        );
    } catch (Throwable $e) {
        return [];
    }

    foreach ($zeilen as $z) {
        $jetzt = $dateien[$z['schluessel']] ?? null;

        if ($jetzt === null || is_array($jetzt)) continue;
        if ($z['original_beim_speichern'] === null) continue;

        if ((string) $jetzt !== (string) $z['original_beim_speichern']) {
            $aus[$z['schluessel']] = [
                'eigener'  => $z['text'],
                'damals'   => $z['original_beim_speichern'],
                'jetzt'    => (string) $jetzt,
            ];
        }
    }

    return $aus;
}

/**
 * Liest eine hochgeladene Sprachdatei ein.
 *
 * NUR JSON. Eine PHP-Datei anzunehmen hiesse, ein Eingabefeld fuer Code zu
 * bauen: lang/xx.php wird per require ausgefuehrt, und wer sie hochlaedt,
 * bestimmt damit, was auf dem Server passiert. JSON sind Daten, sonst nichts.
 *
 * Gibt ['texte' => [...], 'fehler' => [...]] zurueck.
 */
function sprachdatei_lesen(string $inhalt, string $sprache): array {
    $fehler = [];

    if (strlen($inhalt) > 2 * 1024 * 1024) {
        return ['texte' => [], 'fehler' => [t('sprachen_fehler_datei_gross')]];
    }

    $daten = json_decode($inhalt, true);

    if (!is_array($daten)) {
        return ['texte' => [], 'fehler' => [t('sprachen_fehler_kein_json')]];
    }

    $dateien = texte_aus_dateien($sprache);
    $texte   = [];

    foreach ($daten as $schluessel => $wert) {
        if (!is_string($schluessel) || !preg_match('/^[a-z0-9_]{2,190}$/', $schluessel)) {
            $fehler[] = t('sprachen_fehler_schluessel_name', (string) $schluessel);
            continue;
        }

        // Nur Zeichenketten. Verschachtelte Strukturen waeren ein Weg,
        // etwas anderes einzuschleusen als Text.
        if (!is_string($wert)) {
            $fehler[] = t('sprachen_fehler_wert_kein_text', $schluessel);
            continue;
        }

        if (!array_key_exists($schluessel, $dateien)) {
            $fehler[] = t('sprachen_fehler_unbekannter_schluessel', $schluessel);
            continue;
        }

        $grund = text_pruefen($sprache, $schluessel, $wert);

        if ($grund !== null) {
            $fehler[] = $schluessel . ': ' . $grund;
            continue;
        }

        $texte[$schluessel] = $wert;
    }

    return ['texte' => $texte, 'fehler' => $fehler];
}

/** Legt eine Sprache an, die es nur in der Datenbank gibt. */
function sprache_anlegen(string $code, string $name): ?string {
    $code = strtolower(trim($code));

    if (!preg_match('/^[a-z]{2,8}$/', $code)) {
        return t('sprachen_fehler_code');
    }

    $name = trim($name);

    if ($name === '' || mb_strlen($name) > 60) {
        return t('sprachen_fehler_name');
    }

    if (isset(sprachen_alle()[$code])) {
        return t('sprachen_fehler_gibt_es_schon');
    }

    try {
        db_execute('INSERT INTO sprachen (code, name, aktiv) VALUES (?, ?, 1)', [$code, $name]);
        return null;
    } catch (Throwable $e) {
        return t('sprachen_fehler_anlegen');
    }
}

/**
 * Entfernt eine Sprache aus der Datenbank — samt ihrer Texte.
 *
 * Sprachen, die als Datei im Paket liegen, lassen sich so nicht entfernen:
 * die Datei bliebe ja liegen, und beim naechsten Aufruf waere die Sprache
 * wieder da. Wer sie loswerden will, schaltet sie ab.
 */
function sprache_entfernen(string $code): ?string {
    if (is_file(__DIR__ . '/../lang/' . preg_replace('/[^a-z]/', '', $code) . '.php')) {
        return t('sprachen_fehler_datei_sprache');
    }

    try {
        db_execute('DELETE FROM text_overrides WHERE sprache = ?', [$code]);
        db_execute('DELETE FROM sprachen WHERE code = ?', [$code]);
        db_execute('UPDATE users SET language = ? WHERE language = ?', ['de', $code]);
        return null;
    } catch (Throwable $e) {
        return t('sprachen_fehler_entfernen');
    }
}
