<?php
/**
 * Eine Neuinstallation muss dasselbe Schema ergeben wie ein gewachsenes Forum.
 *
 * Anlass: `install.sql` beschrieb das Schema ein zweites Mal, von Hand. Seit
 * 1.1.0 wurde darin nur die Versionsnummer hochgezogen, die Tabellen nicht —
 * ein frisch aufgesetztes Forumix 1.5.0 hatte weder Gruppenrechte noch
 * Papierkorb noch Volltextsuche und behauptete trotzdem, 1.5.0 zu sein.
 * Aufgefallen ist es vier Fassungen lang nicht, weil jede dieser Funktionen
 * einen sauberen Rueckfall hat: das Forum lief, es konnte nur weniger.
 *
 * Seit 1.5.1 faehrt der Installer dieselben Anpassungen wie ein Update. Dieser
 * Lauf beweist es: er setzt ein Forum in einer Wegwerf-Datenbank auf und
 * vergleicht Spalten und Indizes mit dem Pruefstand, der alle Anpassungen
 * hinter sich hat.
 *
 * Die Wegwerf-Datenbank heisst `<Pruefdatenbank>_neu` und wird am Ende
 * weggeworfen — auch wenn der Lauf scheitert.
 */

if (!defined('FORUM_PRUEFLAUF')) { http_response_code(403); die('Forbidden'); }

$wurzel = dirname(__DIR__, 2);
$db     = getenv('FORUM_TEST_DB') ?: 'forumix_test';
$konfig = (defined('PRUEFSTAND') && PRUEFSTAND !== '') ? PRUEFSTAND . '/config.php' : '';
if (!is_file($konfig)) return ['Neuinstallation (uebersprungen, kein Pruefstand)' => fn() => null];

$roh  = file_get_contents($konfig);
$hole = function (string $name) use ($roh): string {
    preg_match("/define\(\s*'$name'\s*,\s*'([^']*)'/", $roh, $t);
    return $t[1] ?? '';
};

return [
    'Eine Neuinstallation hat dasselbe Schema wie ein gewachsenes Forum' =>
    function () use ($wurzel, $db, $hole) {
        $probeDb  = $db . '_neu';
        $ordner   = sys_get_temp_dir() . '/forumix-neuinstallation';
        $pdo = new PDO("mysql:host={$hole('DB_HOST')};charset=utf8mb4",
                       $hole('DB_USER'), $hole('DB_PASS'),
                       [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

        $aufraeumen = function () use ($pdo, $probeDb, $ordner) {
            try { $pdo->exec("DROP DATABASE IF EXISTS `$probeDb`"); } catch (Throwable $e) {}
            if (is_dir($ordner)) exec('rm -rf ' . escapeshellarg($ordner));
        };
        $aufraeumen();

        try {
            $pdo->exec("CREATE DATABASE `$probeDb` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

            exec(sprintf('rsync -a --exclude .git --exclude "cache/*" --exclude "uploads/*" '
                       . '--exclude config.php %s/ %s/ 2>&1',
                         escapeshellarg($wurzel), escapeshellarg($ordner)), $aus, $rc);
            if ($rc !== 0) throw new RuntimeException('Kopie nicht anlegbar: ' . implode(' ', $aus));
            @unlink($ordner . '/install/.installed');

            $angaben = json_encode([
                'db'    => ['host' => $hole('DB_HOST'), 'name' => $probeDb,
                            'user' => $hole('DB_USER'), 'pass' => $hole('DB_PASS'),
                            'charset' => 'utf8mb4'],
                'forum' => ['name' => 'Prueflauf', 'url' => 'https://prueflauf.example.invalid'],
                'owner' => ['user' => 'prueflauf', 'email' => 'prueflauf@example.invalid',
                            'pass' => bin2hex(random_bytes(12))],
                'cleanup' => false,
            ], JSON_UNESCAPED_SLASHES);

            $datei = $ordner . '/setup.json';
            file_put_contents($datei, $angaben);
            chmod($datei, 0600);

            exec(sprintf('cd %s && %s install/cli.php --config=setup.json --quiet 2>&1',
                escapeshellarg($ordner), escapeshellarg(PHP_BINARY)), $zeilen, $rc2);
            if ($rc2 !== 0) throw new RuntimeException('Installer: ' . implode(' ', $zeilen));

            // Spalten und Indizes vergleichen. Tabellen, die nur im Pruefstand
            // stehen, bleiben aussen vor: Module legen ihre eigenen an, und
            // Betriebswerkzeuge gehoeren ohnehin nicht ins Paket.
            $lesen = function (string $sql) use ($pdo) {
                $s = $pdo->query($sql);
                return $s->fetchAll(PDO::FETCH_NUM);
            };
            $spaltenSql = fn($d) => "SELECT table_name, column_name, column_type
                                     FROM information_schema.columns
                                     WHERE table_schema = '$d'";
            $indexSql   = fn($d) => "SELECT table_name, index_name, index_type
                                     FROM information_schema.statistics
                                     WHERE table_schema = '$d'
                                     GROUP BY table_name, index_name, index_type";

            $neu = $alt = [];
            foreach ($lesen($spaltenSql($probeDb)) as [$t, $c, $ty]) $neu['s'][$t][$c] = $ty;
            foreach ($lesen($spaltenSql($db))      as [$t, $c, $ty]) $alt['s'][$t][$c] = $ty;
            foreach ($lesen($indexSql($probeDb))   as [$t, $i, $ty]) $neu['i']["$t|$i|$ty"] = true;
            foreach ($lesen($indexSql($db))        as [$t, $i, $ty]) $alt['i']["$t|$i|$ty"] = true;

            $gemeinsam = array_intersect_key($alt['s'], $neu['s']);
            $fehler = [];
            foreach ($gemeinsam as $t => $spalten) {
                foreach ($spalten as $c => $ty) {
                    if (!isset($neu['s'][$t][$c]))      $fehler[] = "$t.$c fehlt";
                    elseif ($neu['s'][$t][$c] !== $ty)  $fehler[] = "$t.$c: neu={$neu['s'][$t][$c]} statt $ty";
                }
            }
            foreach (array_keys($alt['i']) as $schluessel) {
                [$t] = explode('|', $schluessel);
                if (!isset($gemeinsam[$t])) continue;
                if (!isset($neu['i'][$schluessel])) $fehler[] = "Index $schluessel fehlt";
            }

            if ($fehler) {
                throw new RuntimeException('Neuinstallation weicht ab: ' . implode('; ', array_slice($fehler, 0, 8))
                    . (count($fehler) > 8 ? ' (und ' . (count($fehler) - 8) . ' weitere)' : ''));
            }

            // Und keine Anpassung darf danach noch offenstehen.
            $offen = $pdo->query("SELECT COUNT(*) FROM `$probeDb`.migrations")->fetchColumn();
            $dateien = count(glob($wurzel . '/install/migrations/migrate*.php') ?: []);
            if ((int) $offen !== $dateien) {
                throw new RuntimeException("Es liegen $dateien Anpassungen bereit, eingetragen sind $offen");
            }
        } finally {
            $aufraeumen();
        }
    },
];
