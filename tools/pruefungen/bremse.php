<?php
/**
 * Bremse und Mailkopf — Audit-Funde vom 17.08.2026.
 *
 * Bremse: rate_limit_check() las den Stand, prüfte ihn und erhöhte danach.
 * Zwischen Lesen und Erhöhen passen beliebig viele gleichzeitige Anfragen —
 * die sahen alle denselben Stand und kamen alle durch. Geprüft wird deshalb
 * nicht "zählt hoch", sondern "zählt unteilbar".
 *
 * Mailkopf: Absendername und -adresse gingen ungefiltert in die Kopfzeilen und
 * in MAIL FROM. Ein Zeilenumbruch darin hängt eine eigene Kopfzeile an.
 */

if (!defined('FORUM_PRUEFLAUF')) { http_response_code(403); die('Forbidden'); }

$wurzel = dirname(__DIR__, 2);

return [
    'Bremse zaehlt und prueft in EINEM Schritt' => function () use ($wurzel) {
        $q = file_get_contents($wurzel . '/lib/ratelimit.php');
        $ab = strpos($q, 'function rate_limit_check(');
        $bis = strpos($q, "\nfunction ", $ab + 10);
        $rumpf = substr($q, $ab, $bis - $ab);

        // Das INSERT muss VOR der Entscheidung stehen.
        $schreibt = strpos($rumpf, 'ON DUPLICATE KEY UPDATE');
        $entscheidet = strpos($rumpf, 'return $stand');
        if ($schreibt === false || $entscheidet === false) {
            throw new RuntimeException('Aufbau geaendert — Pruefung veraltet');
        }
        if ($schreibt > $entscheidet) {
            throw new RuntimeException('Es wird entschieden, bevor gezaehlt wurde');
        }
        if (preg_match('/count\s*>=\s*\$maxAttempts.*?UPDATE .*?count = count \+ 1/s', $rumpf)) {
            throw new RuntimeException('Lesen-Pruefen-Erhoehen ist wieder da — parallele Anfragen kommen durch');
        }
    },

    'Erfolgreiche Anmeldung loest nur die eigene Kontobremse' => function () use ($wurzel) {
        $q = file_get_contents($wurzel . '/pages/login.php');
        if (preg_match("/rate_limit_reset\(\s*'login'\s*\)/", $q)) {
            throw new RuntimeException(
                "rate_limit_reset('login') ohne Schluessel raeumt den Zaehler der ganzen "
                . 'IP ab — damit loescht ein Angreifer mit eigenem Konto die Bremse fuer '
                . 'seine Versuche gegen alle anderen');
        }
        if (!str_contains($q, "rate_limit_reset('login_konto'")) {
            throw new RuntimeException('Kontobremse wird nirgends zurueckgesetzt');
        }
    },

    'Anmeldung wird auch je Konto gebremst' => function () use ($wurzel) {
        $q = file_get_contents($wurzel . '/pages/login.php');
        if (!str_contains($q, "rate_limit_check('login_konto'")) {
            throw new RuntimeException(
                'Nur die IP wird gebremst — wer die Adresse wechselt, probiert ein '
                . 'Passwort unbegrenzt weiter durch');
        }
    },

    'Mailkopf: Zeilenumbrueche werden entfernt' => function () use ($wurzel) {
        require_once $wurzel . '/lib/email.php';
        $boese = "Forum\r\nBcc: angreifer@example.net";
        $sauber = mail_kopf_saeubern($boese);
        if (preg_match('/[\r\n\0]/', $sauber)) {
            throw new RuntimeException('CR/LF ueberlebt — zweite Kopfzeile einschleusbar');
        }
        if ($sauber !== 'ForumBcc: angreifer@example.net') {
            throw new RuntimeException('unerwartetes Ergebnis: ' . $sauber);
        }
    },

    'Mailkopf: Absender wird vor dem Einsetzen gesaeubert' => function () use ($wurzel) {
        $q = file_get_contents($wurzel . '/lib/email.php');
        $ab = strpos($q, 'function send_email(');
        $rumpf = substr($q, $ab, 4000);
        $saeubert = strpos($rumpf, 'mail_kopf_saeubern($fromName)');
        $benutzt  = strpos($rumpf, "'From: ' . \$fromName");
        if ($saeubert === false) throw new RuntimeException('fromName wird nicht gesaeubert');
        if ($benutzt !== false && $saeubert > $benutzt) {
            throw new RuntimeException('gesaeubert wird erst NACH dem Einsetzen');
        }
    },

    'Mailfelder mit Zeilenumbruch werden beim Speichern abgewiesen' => function () use ($wurzel) {
        $q = file_get_contents($wurzel . '/admin/index.php');
        if (!preg_match("/str_starts_with\(\\\$key, 'smtp_'\).*?\\\\r\\\\n/s", $q)) {
            throw new RuntimeException('Adminbereich nimmt Zeilenumbrueche in smtp_-Feldern noch an');
        }
    },
];
