<?php
/**
 * Sitzungen: Fixation beim Anmelden — Audit-Fund vom 17.08.2026.
 *
 * Geprueft wird die Regel selbst, nicht ein Zufallsergebnis: JEDER Weg in eine
 * angemeldete Sitzung muss die Kennung wechseln und den CSRF-Token verwerfen.
 * Es gibt drei — Passwort (lib/auth.php), Zwei-Faktor (pages/login.php) und
 * die Angemeldet-bleiben-Marke (lib/remember.php). Der dritte war es nicht.
 */

if (!defined('FORUM_PRUEFLAUF')) { http_response_code(403); die('Forbidden'); }

$wurzel = dirname(__DIR__, 2);

/** Setzt user_id irgendwo im Quelltext, ohne vorher die Kennung zu wechseln? */
$wechseltVorher = function (string $datei, string $funktion) use ($wurzel): bool {
    $quelle = file_get_contents($wurzel . '/' . $datei);
    if ($quelle === false) throw new RuntimeException("$datei nicht lesbar");

    // Ausschnitt ab dem Funktionskopf bis zur naechsten Funktion
    $ab = strpos($quelle, "function $funktion(");
    if ($ab === false) throw new RuntimeException("$funktion() gibt es in $datei nicht mehr");
    $bis = strpos($quelle, "\nfunction ", $ab + 10);
    $rumpf = substr($quelle, $ab, $bis === false ? null : $bis - $ab);

    // Die ZUWEISUNG suchen, nicht die Abfrage: remember_try_login() prueft
    // oben mit isset(), ob schon jemand angemeldet ist.
    if (!preg_match("/\\\$_SESSION\\['user_id'\\]\\s*=[^=]/", $rumpf, $t, PREG_OFFSET_CAPTURE)) {
        throw new RuntimeException("$funktion() setzt keine user_id mehr — Pruefung veraltet");
    }
    $setzt = $t[0][1];
    $davor = substr($rumpf, 0, $setzt);
    return str_contains($davor, 'session_regenerate_id');
};

$verwirftToken = function (string $datei, string $funktion) use ($wurzel): bool {
    $quelle = file_get_contents($wurzel . '/' . $datei);
    $ab = strpos($quelle, "function $funktion(");
    $bis = strpos($quelle, "\nfunction ", $ab + 10);
    $rumpf = substr($quelle, $ab, $bis === false ? null : $bis - $ab);
    preg_match("/\\\$_SESSION\\['user_id'\\]\\s*=[^=]/", $rumpf, $t, PREG_OFFSET_CAPTURE);
    $davor = substr($rumpf, 0, $t[0][1] ?? 0);
    return (bool) preg_match("/unset\(\s*\\\$_SESSION\['csrf_token'\]/", $davor);
};

return [
    'Anmeldung mit Passwort wechselt die Sitzungskennung' => function () use ($wechseltVorher) {
        if (!$wechseltVorher('lib/auth.php', 'login_user')) {
            throw new RuntimeException('login_user() setzt user_id ohne session_regenerate_id davor');
        }
    },

    'Anmeldung mit Passwort verwirft den alten CSRF-Token' => function () use ($verwirftToken) {
        if (!$verwirftToken('lib/auth.php', 'login_user')) {
            throw new RuntimeException('login_user() behaelt den vor der Anmeldung erzeugten Token');
        }
    },

    'Angemeldet-bleiben wechselt die Sitzungskennung' => function () use ($wechseltVorher) {
        if (!$wechseltVorher('lib/remember.php', 'remember_try_login')) {
            throw new RuntimeException(
                'remember_try_login() setzt user_id ohne session_regenerate_id davor — '
                . 'untergeschobene Sitzung bleibt nach der automatischen Anmeldung gueltig');
        }
    },

    'Angemeldet-bleiben verwirft den alten CSRF-Token' => function () use ($verwirftToken) {
        if (!$verwirftToken('lib/remember.php', 'remember_try_login')) {
            throw new RuntimeException('remember_try_login() behaelt den alten Token');
        }
    },

    'session.use_strict_mode ist an' => function () use ($wurzel) {
        $quelle = file_get_contents($wurzel . '/lib/auth.php');
        if (!str_contains($quelle, "use_strict_mode")) {
            throw new RuntimeException('use_strict_mode wird nicht gesetzt — fremde Kennungen werden angenommen');
        }
    },
];
