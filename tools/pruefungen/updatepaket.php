<?php
/**
 * Ein Updatepaket passt nur auf die Fassung, fuer die es gebaut wurde.
 *
 * Anlass: Bis 1.5.2 nannte `update.json` nur die Zielversion. Ein Paket liess
 * sich damit auf jeden beliebigen Stand einspielen — es legte seine paar
 * geaenderten Dateien ab (bei 1.5.1 waren es acht), liess alles andere auf dem
 * alten Stand und setzte die Versionsnummer trotzdem hoch. Danach behauptete
 * ein Forum, 1.5.1 zu sein, waehrend die Haelfte seiner Dateien von 1.2.0
 * stammte. Gemeldet wurde nichts.
 *
 * Der zweite Fund derselben Stelle: das Einspielen hing " Beta" an jede
 * Versionsnummer. Bei der Freigabe von 1.0.0 fiel das Wort an drei Stellen
 * weg, diese vierte wurde uebersehen — dort steht es nicht in einer
 * Zeichenkette, sondern wird zur Laufzeit angehaengt.
 *
 * Braucht keine Datenbank: gebaut werden echte ZIP-Dateien im Temp.
 */

if (!defined('FORUM_PRUEFLAUF')) { http_response_code(403); die('Forbidden'); }

$wurzel = defined('FORUM_WURZEL') ? FORUM_WURZEL : dirname(__DIR__, 2);
require_once $wurzel . '/lib/update_package.php';

/* forum_version() kommt aus bootstrap.php, das hier nicht laeuft — die
   Pruefung stellt sie selbst bereit. Der Wert wird aus bootstrap.php gelesen
   statt hier hineingeschrieben: eine feste Nummer waere bei der naechsten
   Fassung falsch, und die Pruefung wuerde ohne eigenes Zutun rot. */
if (!function_exists('forum_version')) {
    preg_match("/FORUM_VERSION_CODE = '([^']+)'/",
               (string) file_get_contents($wurzel . '/bootstrap.php'), $t);
    $GLOBALS['__pruef_version'] = $t[1] ?? '1.0.0';
    function forum_version(): string { return $GLOBALS['__pruef_version']; }
}
$hier = forum_version();

/** Baut ein Updatepaket mit dem gegebenen Manifest und einer Datei darin. */
$paket = function (array $manifest) : string {
    $pfad = tempnam(sys_get_temp_dir(), 'pruef_upd_') . '.zip';
    $zip = new ZipArchive();
    $zip->open($pfad, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('update.json', json_encode($manifest));
    $zip->addFromString('files/README.md', "Nur zum Pruefen.\n");
    $zip->close();
    return $pfad;
};

$pruefe = function (string $pfad): array {
    $e = update_validate_zip($pfad);
    if (!empty($e['zip'])) $e['zip']->close();
    @unlink($pfad);
    return $e;
};

return [
    'Ein Paket fuer eine andere Fassung wird abgewiesen' => function () use ($paket, $pruefe, $hier) {
        // Das Paket ist fuer einen Stand gebaut, der hier nicht laeuft.
        $e = $pruefe($paket(['version' => '9.9.9', 'from' => '0.9.0', 'name' => 'Forumix 9.9.9']));
        if (!empty($e['ok'])) throw new RuntimeException('Das Paket wurde angenommen');
        foreach (['0.9.0', $hier, 'Vollpaket'] as $wort) {
            if (!str_contains((string) $e['error'], $wort)) {
                throw new RuntimeException("Die Absage nennt '$wort' nicht: " . $e['error']);
            }
        }
    },

    'Ein Paket fuer genau diese Fassung wird angenommen' => function () use ($paket, $pruefe, $hier) {
        $e = $pruefe($paket(['version' => '9.9.9', 'from' => $hier, 'name' => 'Forumix 9.9.9']));
        if (empty($e['ok'])) throw new RuntimeException('Abgewiesen: ' . ($e['error'] ?? '?'));
    },

    'Ein Paket ohne Angabe laeuft durch' => function () use ($paket, $pruefe) {
        // Vollpakete und alles, was vor 1.5.2 ausgeliefert wurde, tragen kein
        // "from". Sie duerfen nicht ungueltig werden.
        $e = $pruefe($paket(['version' => '9.9.9', 'name' => 'Forumix 9.9.9']));
        if (empty($e['ok'])) throw new RuntimeException('Abgewiesen: ' . ($e['error'] ?? '?'));
    },

    'Ein angehaengtes " Beta" stoert den Vergleich nicht' => function () use ($paket, $pruefe, $hier) {
        // Wer vor 1.5.2 ein Paket eingespielt hat, traegt die Nummer mit einem
        // angehaengten " Beta" in der Datenbank. Das darf ihn nicht vom
        // naechsten Update aussperren.
        $e = $pruefe($paket(['version' => '9.9.9', 'from' => $hier . ' Beta', 'name' => 'Forumix 9.9.9']));
        if (empty($e['ok'])) throw new RuntimeException('Abgewiesen: ' . ($e['error'] ?? '?'));
    },

    'Kein Paket macht die Fassung mehr zur Beta' => function () use ($wurzel) {
        $quelle = (string) file_get_contents($wurzel . '/lib/update_package.php');
        if (preg_match("/set_setting\(\s*'forum_version'\s*,[^;]*Beta/", $quelle)) {
            throw new RuntimeException('Das Einspielen haengt weiterhin " Beta" an');
        }
    },
];
