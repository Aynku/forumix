<?php
/**
 * Die mittleren Audit-Funde vom 17.08.2026.
 *
 * Jede Pruefung stellt den Weg nach, den der Bericht beschreibt.
 */

if (!defined('FORUM_PRUEFLAUF')) { http_response_code(403); die('Forbidden'); }

$wurzel = dirname(__DIR__, 2);
$lies = fn(string $d) => file_get_contents($wurzel . '/' . $d);

return [
    'Wartungsmodus: Umgehung ueber die Adresse ist zu' => function () use ($lies) {
        $q = $lies('bootstrap.php');
        if (preg_match("/str_contains\(\\\$_SERVER\['REQUEST_URI'\].*?'\/admin\/'\)/s", $q)) {
            throw new RuntimeException(
                'Teilstring-Pruefung auf REQUEST_URI ist wieder da — /index.php?x=/admin/ '
                . 'umgeht den Wartungsmodus');
        }
        if (!str_contains($q, "parse_url(\$_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH)")) {
            throw new RuntimeException('Es wird nicht der Pfad geprueft');
        }
        if (!preg_match("/str_starts_with\(\\\$pfad, '\/admin\/'\)/", $q)) {
            throw new RuntimeException('Der Pfad wird nicht am Anfang verglichen');
        }
    },

    'RSS liefert keine unfreigegebenen Themen' => function () use ($lies) {
        $q = $lies('pages/rss.php');
        if (substr_count($q, 'is_approved') < 2) {
            throw new RuntimeException(
                'Der Feed filtert nicht auf freigegeben — Beitraege aus der '
                . 'Warteschlange sind ohne Anmeldung lesbar');
        }
    },

    'RSS: ]]> im Beitrag zerlegt den Feed nicht mehr' => function () use ($lies) {
        $q = $lies('pages/rss.php');
        if (!str_contains($q, "str_replace(']]>'")) {
            throw new RuntimeException('CDATA-Ende wird nicht aufgeteilt — ein Beitrag kann den ganzen Feed ungueltig machen');
        }
        // Gegenprobe an der Regel selbst
        $text = 'harmlos ]]> danach';
        $sicher = str_replace(']]>', ']]]]><![CDATA[>', $text);
        $zusammen = '<![CDATA[' . $sicher . ']]>';
        if (substr_count($zusammen, ']]>') !== 2) {
            throw new RuntimeException('Aufteilung ergibt kein gueltiges CDATA');
        }
    },

    'Anhaenge werden beim Loeschen mit entfernt' => function () use ($lies, $wurzel) {
        require_once $wurzel . '/lib/attachments.php';
        if (!function_exists('delete_attachments_for_posts')) {
            throw new RuntimeException('delete_attachments_for_posts() fehlt');
        }
        // Seit 1.3.0 loescht nicht mehr die Seite, sondern lib/papierkorb.php —
        // die Eigenschaft bleibt: wer einen Beitrag entfernt, entfernt seine
        // Anhaenge mit, sonst liegen die Dateien weiter abrufbar herum.
        foreach (['lib/papierkorb.php', 'lib/approval.php'] as $datei) {
            if (!str_contains($lies($datei), 'delete_attachments_for_')) {
                throw new RuntimeException("$datei loescht Beitraege ohne ihre Anhaenge");
            }
        }
    },

    'Anhaenge-Loeschen bleibt im Upload-Ordner' => function () use ($lies) {
        $q = $lies('lib/attachments.php');
        $ab = strpos($q, 'function delete_attachments_for_posts(');
        $rumpf = substr($q, $ab, 2000);
        if (!str_contains($rumpf, 'realpath') || !str_contains($rumpf, 'str_starts_with')) {
            throw new RuntimeException(
                'Kein Pfadvergleich vor dem unlink — ein manipulierter Dateiname '
                . 'koennte aus dem Ordner herausfuehren');
        }
    },

    'Beitragszaehler wird beim Loeschen eines Themas mitgefuehrt' => function () use ($lies) {
        // Auch das steht seit 1.3.0 in lib/papierkorb.php. Ohne diese Zeile
        // zeigt jedes Profil dauerhaft zu viele Beitraege an.
        $q = $lies('lib/papierkorb.php');
        $ab = strpos($q, 'function thema_in_papierkorb');
        if ($ab === false) throw new RuntimeException('thema_in_papierkorb() fehlt');
        $rumpf = substr($q, $ab, 2000);
        if (!str_contains($rumpf, 'users SET post_count')) {
            throw new RuntimeException('Die Zaehler der Verfasser bleiben stehen');
        }
    },

    'Vorlagenordner sind vor direktem Zugriff geschuetzt' => function () use ($wurzel) {
        foreach (['views_new', 'cache'] as $ordner) {
            $datei = "$wurzel/$ordner/.htaccess";
            if (!is_file($datei) || !str_contains(file_get_contents($datei), 'denied')) {
                throw new RuntimeException("$ordner/.htaccess fehlt — Vorlagenquelltext waere lesbar");
            }
        }
    },

    'Kompilierte Vorlagen tragen den FORUM_RUNNING-Riegel' => function () use ($lies) {
        $q = $lies('lib/template.php');
        $ab = strpos($q, 'if ($needsCompile) {');
        $rumpf = substr($q, $ab, 900);
        if (!str_contains($rumpf, "defined('FORUM_RUNNING')")) {
            throw new RuntimeException(
                'Kompilat wird ohne Riegel geschrieben — direkt aufgerufen laeuft es los');
        }
    },

    'Benachrichtigungsmail prueft den Forumzugang beim Versand' => function () use ($lies) {
        $q = $lies('lib/notifications.php');
        $ab = strpos($q, 'function notify_thread_subscribers(');
        $rumpf = substr($q, $ab, 3000);
        if (!str_contains($rumpf, 'board_privileged_for_user')) {
            throw new RuntimeException(
                'Der Beitragstext geht an jeden Abonnenten — auch an den, der '
                . 'den Zugang zum geschuetzten Forum nicht (mehr) hat');
        }
    },
];
