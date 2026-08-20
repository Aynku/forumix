<?php
/**
 * Ein geloeschtes Konto hinterlaesst nichts Sichtbares (1.2.3).
 *
 * Die Zeile in `users` bleibt — sie ist der Anker unter den Beitraegen, die
 * bewusst stehen bleiben, und `posts.user_id` ist NOT NULL. Alles andere hat
 * zu verschwinden: die Anmelde-IPs, der Platz in der Benutzerliste, die
 * Mitgliederzahl, die neuesten Mitglieder, die letzten Logins.
 *
 * Aufgefallen am 20.08.2026 im Adminbereich: das Konto stand weiter im
 * Dashboard, und unter Benutzer war es nur weggeklappt, nicht weg.
 */

if (!defined('FORUM_PRUEFLAUF')) { http_response_code(403); die('Forbidden'); }

$wurzel = dirname(__DIR__, 2);
$lies   = fn(string $d) => (string) file_get_contents($wurzel . '/' . $d);

/* Schneidet den Rumpf eines case-Zweigs aus admin/index.php heraus. */
$zweig = function (string $name) use ($lies): string {
    $q = $lies('admin/index.php');
    $i = strpos($q, "case '$name':");
    if ($i === false) throw new RuntimeException("Zweig '$name' nicht gefunden");
    $j = strpos($q, "\n    case '", $i + 10);
    return substr($q, $i, ($j === false ? strlen($q) : $j) - $i);
};

return [
    'Die Kontoloeschung raeumt das IP-Protokoll ab' => function () use ($lies) {
        $q = $lies('lib/user_deletion.php');
        if (!preg_match("/DELETE FROM user_ip_log WHERE user_id = \?/", $q)) {
            throw new RuntimeException(
                'Die Anmelde-IPs bleiben stehen — der Name ist ersetzt, die Person '
                . 'ueber die IP weiter erkennbar');
        }
    },

    'Die Benutzerliste kennt kein Einblenden mehr' => function () use ($zweig) {
        $z = $zweig('users');
        foreach (["\$_GET['geloescht']", 'mitGeloeschten', 'zahlGeloescht'] as $rest) {
            if (str_contains($z, $rest)) {
                throw new RuntimeException("Der Schalter ist wieder da ($rest)");
            }
        }
        if (!str_contains($z, "COALESCE(is_deleted, 0) = 0")) {
            throw new RuntimeException('Die Liste filtert geloeschte Konten nicht mehr');
        }
    },

    'Das Dashboard zaehlt und zeigt keine geloeschten Konten' => function () use ($zweig) {
        $z = $zweig('dashboard');
        if (!preg_match("/COUNT\(\*\) FROM users WHERE COALESCE\(is_deleted, 0\) = 0/", $z)) {
            throw new RuntimeException('Die Mitgliederzahl zaehlt geloeschte Konten mit');
        }
        // Neueste Mitglieder, letzte Logins und die 30-Tage-Kurve.
        if (substr_count($z, 'is_deleted') < 4) {
            throw new RuntimeException(
                'Nicht alle vier Stellen filtern: Zahl, neueste Mitglieder, letzte '
                . 'Logins, Registrierungen der letzten 30 Tage');
        }
    },

    'Auch die Statistik-Seite laesst sie weg' => function () use ($lies) {
        $q = $lies('admin/index.php');
        $anzahl = substr_count($q, 'FROM users
            WHERE registered_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
              AND COALESCE(is_deleted, 0) = 0');
        if ($anzahl !== 2) {
            throw new RuntimeException(
                "Die 30-Tage-Kurve steht zweimal im Haus (Dashboard und Statistik); "
                . "gefiltert sind $anzahl davon");
        }
    },
];
