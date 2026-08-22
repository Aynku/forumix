<?php
/**
 * lib/update_package.php – Update-Paket-Installer (v0.18.54)
 *
 * Ermöglicht das Hochladen eines Update-ZIPs im Admin-Bereich, das nach
 * strenger Validierung automatisch Dateien kopiert und SQL ausführt.
 *
 * ════════════════════════════════════════════════════════════════════
 *  ⚠️  SICHERHEITS-HINWEIS
 * ════════════════════════════════════════════════════════════════════
 *  Dieses Feature kann beliebige PHP-Dateien ins Live-System schreiben
 *  und SQL ausführen. Es ist damit ein Remote-Code-Execution-Vektor und
 *  NUR für den Owner zugänglich. Schutzschichten:
 *    - Nur Owner (is_owner), nicht normale Admins
 *    - CSRF-Pflicht
 *    - Whitelist erlaubter Datei-Endungen
 *    - Zip-Slip-Schutz (kein ../, kein absoluter Pfad, Ziel muss im Root bleiben)
 *    - Manifest-Pflicht (update.json mit version + name)
 *    - PHP-Dateien werden NUR kopiert, niemals während des Uploads inkludiert
 *    - SQL nur aus genau einer Datei (update.sql)
 *    - Größenlimit
 * ════════════════════════════════════════════════════════════════════
 *
 * ZIP-Struktur die erwartet wird:
 *   update.json          (Pflicht — Manifest: {"version":"0.18.54","name":"..."})
 *   update.sql           (optional — wird nach dem Kopieren ausgeführt)
 *   files/               (alles hierunter wird relativ ins Forum-Root kopiert)
 *     bootstrap.php
 *     lib/xyz.php
 *     ...
 */
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }

// ── Konfiguration ────────────────────────────────────────────────────
const UPDATE_MAX_ZIP_BYTES   = 20 * 1024 * 1024; // 20 MB
const UPDATE_ALLOWED_EXT = [
    'php', 'css', 'js', 'sql', 'html', 'tpl', 'md', 'txt', 'json',
    'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'ico',
    'woff', 'woff2', 'ttf', 'eot',
    'htaccess',  // für .htaccess (Sonderfall, siehe ext_of)
];

/**
 * Liefert die "Endung" eines Pfades zur Whitelist-Prüfung.
 * Sonderfall: .htaccess hat technisch keine Endung → wir behandeln den
 * Dateinamen.
 */
function update_ext_of(string $path): string {
    $base = strtolower(basename($path));
    if ($base === '.htaccess') return 'htaccess';
    $dot = strrpos($base, '.');
    if ($dot === false) return '';
    return substr($base, $dot + 1);
}

/**
 * Prüft EINEN ZIP-Eintrag auf Sicherheit.
 * Returns null wenn ok, sonst Fehlertext.
 */
/**
 * $wurzelDateien: was im ZIP-Wurzelverzeichnis liegen darf. Alles andere muss
 * unter files/ stehen. Als Parameter, weil Modul-Pakete dieselbe Pruefung
 * brauchen, aber ein anderes Manifest mitbringen (module.json) — und
 * Sicherheitscode zweimal zu haben ist die schlechteste aller Loesungen: die
 * zweite Kopie wird beim naechsten Fund vergessen.
 */
function update_validate_entry(string $entryName, array $wurzelDateien = ['update.json', 'update.sql']): ?string {
    // Verzeichnis-Einträge (enden auf /) sind ok
    if (str_ends_with($entryName, '/')) return null;

    // Leerer Name
    if ($entryName === '') return 'Leerer Datei-Name im Archiv.';

    // Absolute Pfade verbieten
    if ($entryName[0] === '/' || preg_match('#^[a-zA-Z]:[\\\\/]#', $entryName)) {
        return 'Absoluter Pfad nicht erlaubt: ' . $entryName;
    }

    // Path-Traversal (Zip-Slip) verbieten — kein ".." in irgendeinem Segment
    $segments = preg_split('#[\\\\/]+#', $entryName);
    foreach ($segments as $seg) {
        if ($seg === '..') {
            return 'Pfad-Traversal (..) nicht erlaubt: ' . $entryName;
        }
        // Null-Byte
        if (str_contains($seg, "\0")) {
            return 'Ungültiges Zeichen im Pfad: ' . $entryName;
        }
    }

    // Manifest (und ggf. SQL) sind im ZIP-Root erlaubt
    $lower = strtolower($entryName);
    if (in_array($lower, array_map('strtolower', $wurzelDateien), true)) return null;

    // Alles andere MUSS unter files/ liegen
    if (!str_starts_with($lower, 'files/')) {
        return 'Datei außerhalb von files/ (und nicht ' . implode('/', $wurzelDateien) . '): ' . $entryName;
    }

    // Endung gegen Whitelist
    $ext = update_ext_of($entryName);
    if (!in_array($ext, UPDATE_ALLOWED_EXT, true)) {
        return 'Datei-Typ nicht erlaubt (.' . $ext . '): ' . $entryName;
    }

    return null;
}

/**
 * Validiert ein hochgeladenes Update-ZIP VOLLSTÄNDIG, bevor irgendetwas
 * geschrieben wird. Gibt strukturiertes Ergebnis zurück.
 *
 * Returns:
 *   ['ok' => true, 'manifest' => [...], 'files' => [...], 'has_sql' => bool, 'zip' => ZipArchive]
 *   ['ok' => false, 'error' => '...']
 */
function update_validate_zip(string $zipPath): array {
    if (!class_exists('ZipArchive')) {
        return ['ok' => false, 'error' => 'ZipArchive-Erweiterung ist auf dem Server nicht verfügbar.'];
    }

    $size = filesize($zipPath);
    if ($size === false || $size > UPDATE_MAX_ZIP_BYTES) {
        return ['ok' => false, 'error' => 'Archiv zu groß (max. ' . (UPDATE_MAX_ZIP_BYTES / 1024 / 1024) . ' MB).'];
    }

    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        return ['ok' => false, 'error' => 'Archiv konnte nicht geöffnet werden (kein gültiges ZIP?).'];
    }

    $files       = [];
    $hasManifest = false;
    $hasSql      = false;

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $entry = $zip->getNameIndex($i);
        if ($entry === false) {
            $zip->close();
            return ['ok' => false, 'error' => 'Archiv-Eintrag #' . $i . ' nicht lesbar.'];
        }

        $err = update_validate_entry($entry);
        if ($err !== null) {
            $zip->close();
            return ['ok' => false, 'error' => $err];
        }

        $lower = strtolower($entry);
        if ($lower === 'update.json') { $hasManifest = true; continue; }
        if ($lower === 'update.sql')  { $hasSql = true; continue; }
        if (str_ends_with($entry, '/')) continue; // Verzeichnis

        // Zieldatei (files/xyz → xyz relativ zum Root)
        $rel = substr($entry, strlen('files/'));
        if ($rel !== '') $files[] = $rel;
    }

    if (!$hasManifest) {
        $zip->close();
        return ['ok' => false, 'error' => 'Kein update.json im Archiv gefunden. Das ist kein gültiges Update-Paket.'];
    }

    // Manifest lesen + validieren
    $manifestRaw = $zip->getFromName('update.json');
    if ($manifestRaw === false) {
        $manifestRaw = $zip->getFromName('UPDATE.JSON'); // Fallback Groß
    }
    $manifest = json_decode((string) $manifestRaw, true);
    if (!is_array($manifest) || empty($manifest['version']) || empty($manifest['name'])) {
        $zip->close();
        return ['ok' => false, 'error' => 'update.json ist ungültig (benötigt "version" und "name").'];
    }

    // Version-Format prüfen (nur Ziffern, Punkte, Buchstaben, Leerzeichen)
    if (!preg_match('/^[\w.\- ]{1,40}$/', (string) $manifest['version'])) {
        $zip->close();
        return ['ok' => false, 'error' => 'Ungültige Versions-Angabe im Manifest.'];
    }

    // Passt das Paket auf diesen Stand?
    //
    // Ein Updatepaket enthaelt NUR die Dateien, die sich seit der genannten
    // Fassung geaendert haben — bei 1.5.1 waren das acht. Auf einem aelteren
    // Forum eingespielt legt es diese acht ab, laesst alles andere auf dem
    // alten Stand und setzt die Versionsnummer trotzdem hoch. Danach behauptet
    // ein Forum, 1.5.1 zu sein, waehrend die Haelfte seiner Dateien von 1.2.0
    // stammt — und der Fehler faellt erst auf, wenn eine Seite bricht.
    //
    // Ein Vollpaket traegt kein "from" und laeuft deshalb durch: es bringt
    // alle Dateien mit und ist von jedem Stand aus richtig.
    $von = trim((string) ($manifest['from'] ?? ''));
    if ($von !== '') {
        $hier = forum_version();
        // Suffixe wie " Beta" abschneiden, damit ein Altbestand nicht haengen
        // bleibt (bis 1.5.2 hat der Einspielweg eines angehaengt).
        $blank = static fn(string $v): string => trim(strtok(trim($v), ' ') ?: '');
        if (version_compare($blank($hier), $blank($von), '!=')) {
            $zip->close();
            return ['ok' => false, 'error' =>
                'Dieses Updatepaket setzt Fassung ' . $von . ' voraus, hier läuft '
                . $hier . '. Ein Updatepaket enthält nur die geänderten Dateien und '
                . 'lässt den Rest auf dem alten Stand. Für den Sprung über mehrere '
                . 'Fassungen bitte das Vollpaket nehmen — es geht von jedem Stand aus.'];
        }
    }

    return [
        'ok'       => true,
        'manifest' => $manifest,
        'files'    => $files,
        'has_sql'  => $hasSql,
        'zip'      => $zip,  // bleibt offen für die Installation
    ];
}

/**
 * Führt die eigentliche Installation aus (Dateien kopieren + SQL).
 * MUSS nach erfolgreicher update_validate_zip() aufgerufen werden.
 *
 * @param ZipArchive $zip      Geöffnetes, validiertes Archiv
 * @param array      $manifest Das geparste Manifest
 * @param array      $fileList Liste relativer Ziel-Pfade (ohne "files/")
 * @param bool       $hasSql   Ob update.sql ausgeführt werden soll
 * @return array ['success'=>bool, 'log'=>string[], 'error'=>?string]
 */
function update_install_package(ZipArchive $zip, array $manifest, array $fileList, bool $hasSql): array {
    $log    = [];
    $error  = null;
    $forumRoot = realpath(__DIR__ . '/..');
    if ($forumRoot === false) {
        return ['success' => false, 'log' => [], 'error' => 'Forum-Root nicht auflösbar.'];
    }

    // ── Phase 1: Dateien kopieren ────────────────────────────────────
    $statNew = 0; $statChanged = 0; $statSame = 0;
    $detail  = [];  // pro Datei ein Eintrag fürs Log
    foreach ($fileList as $rel) {
        $entryName = 'files/' . $rel;
        $content   = $zip->getFromName($entryName);
        if ($content === false) {
            $error = 'Datei aus Archiv nicht lesbar: ' . $entryName;
            break;
        }

        // Ziel-Pfad bestimmen + final gegen Zip-Slip absichern
        $target = $forumRoot . '/' . $rel;
        $targetDir = dirname($target);

        // Status bestimmen BEVOR geschrieben wird: neu / geändert / unverändert
        $fileStatus = 'new';
        if (is_file($target)) {
            $oldContent = @file_get_contents($target);
            if ($oldContent !== false && hash('sha256', $oldContent) === hash('sha256', $content)) {
                $fileStatus = 'same';
            } else {
                $fileStatus = 'changed';
            }
        }

        // Verzeichnis ggf. anlegen
        if (!is_dir($targetDir)) {
            if (!@mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
                $error = 'Verzeichnis konnte nicht angelegt werden: ' . $rel;
                break;
            }
        }
        // Sicherstellen dass das Ziel WIRKLICH im Forum-Root bleibt
        // (doppelter Boden zusätzlich zur Entry-Validierung)
        $realTargetDir = realpath($targetDir);
        if ($realTargetDir === false || !str_starts_with($realTargetDir . '/', $forumRoot . '/')) {
            $error = 'Sicherheits-Abbruch: Ziel außerhalb des Forum-Verzeichnisses (' . $rel . ').';
            break;
        }

        // Unveränderte Dateien gar nicht erst neu schreiben (spart I/O, klarere Logs)
        if ($fileStatus === 'same') {
            $statSame++;
            $detail[] = '= ' . $rel;
            continue;
        }

        // Schreiben
        if (file_put_contents($target, $content) === false) {
            $error = 'Datei konnte nicht geschrieben werden: ' . $rel;
            break;
        }
        if ($fileStatus === 'new') {
            $statNew++;
            $detail[] = '🆕 ' . $rel;
        } else {
            $statChanged++;
            $detail[] = '✏️ ' . $rel;
        }
    }

    if ($error !== null) {
        return ['success' => false, 'log' => $log, 'error' => $error];
    }
    // Zusammenfassung + Detail-Liste
    $log[] = "📊 Dateien: {$statNew} neu, {$statChanged} geändert, {$statSame} unverändert";
    foreach ($detail as $d) $log[] = $d;

    // ── Phase 2: SQL ausführen ───────────────────────────────────────
    if ($hasSql) {
        $sql = $zip->getFromName('update.sql');
        if ($sql === false) $sql = $zip->getFromName('UPDATE.SQL');
        if ($sql === false) {
            return ['success' => false, 'log' => $log, 'error' => 'update.sql angekündigt, aber nicht lesbar.'];
        }

        $stmtResult = update_run_sql((string) $sql);
        foreach ($stmtResult['log'] as $l) $log[] = $l;
        if (!$stmtResult['success']) {
            return ['success' => false, 'log' => $log, 'error' => $stmtResult['error']];
        }
    } else {
        $log[] = 'ℹ️ Kein SQL im Paket — nur Dateien aktualisiert.';
    }

    return ['success' => true, 'log' => $log, 'error' => null];
}

/**
 * Führt eine SQL-Datei statement-weise aus.
 * Splittet an Semikolon+Zeilenende. Reicht für ALTER/INSERT/UPDATE/CREATE.
 * (Stored Procedures mit DELIMITER werden NICHT unterstützt — bewusst simpel.)
 */
function update_run_sql(string $sql): array {
    $log = [];

    // Kommentare entfernen (-- ... und /* ... */)
    $sql = preg_replace('!/\*.*?\*/!s', '', $sql);
    $lines = explode("\n", $sql);
    $clean = [];
    foreach ($lines as $line) {
        $trim = ltrim($line);
        if (str_starts_with($trim, '--') || str_starts_with($trim, '#')) continue;
        $clean[] = $line;
    }
    $sql = implode("\n", $clean);

    // An Semikolon splitten (simpel — keine Semikola in String-Literalen erwartet
    // bei Schema-Migrationen; falls doch, kann der Owner es in PHP-Migration packen)
    $statements = array_filter(
        array_map('trim', preg_split('/;\s*(?:\n|$)/', $sql)),
        fn($s) => $s !== ''
    );

    if (empty($statements)) {
        $log[] = 'ℹ️ update.sql enthielt keine ausführbaren Statements.';
        return ['success' => true, 'log' => $log, 'error' => null];
    }

    $pdo = DB::get();  // bestehende PDO-Verbindung
    $count = 0;
    foreach ($statements as $stmt) {
        try {
            $pdo->exec($stmt);
            $count++;
        } catch (PDOException $e) {
            // Idempotenz-freundlich: "Duplicate column" / "already exists" tolerieren
            $msg = $e->getMessage();
            if (stripos($msg, 'Duplicate column') !== false
                || stripos($msg, 'already exists') !== false
                || stripos($msg, 'Duplicate key name') !== false) {
                $log[] = '⊘ Übersprungen (existiert bereits): ' . update_sql_preview($stmt);
                continue;
            }
            return [
                'success' => false,
                'log'     => $log,
                'error'   => 'SQL-Fehler bei: ' . update_sql_preview($stmt) . ' → ' . $msg,
            ];
        }
    }
    $log[] = "✓ {$count} SQL-Statement(s) ausgeführt";
    return ['success' => true, 'log' => $log, 'error' => null];
}

/** Kürzt ein SQL-Statement für die Log-Anzeige. */
function update_sql_preview(string $stmt): string {
    $stmt = preg_replace('/\s+/', ' ', trim($stmt));
    return mb_strlen($stmt) > 60 ? mb_substr($stmt, 0, 60) . '…' : $stmt;
}

/**
 * Trägt ein installiertes Paket in den Verlauf (migrations-Tabelle) ein
 * und setzt die forum_version.
 */
function update_record_package(array $manifest, array $log): void {
    $cu = current_user();
    $version = (string) $manifest['version'];
    $name    = (string) $manifest['name'];
    $output  = implode("\n", $log);

    try {
        ensure_migrations_table();
        // Versions-Key fürs Tracking: "pkg-0.18.54" damit es nicht mit
        // den v18_xx-Migrationen kollidiert
        $key = 'pkg-' . $version;
        db_execute(
            'INSERT INTO migrations (version, name, executed_by, output) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE executed_at = NOW(), executed_by = VALUES(executed_by), output = VALUES(output)',
            [$key, $name, (int) ($cu['user_id'] ?? 0), $output]
        );
        // forum_version auf die Paket-Version setzen.
        //
        // Hier stand bis 1.5.2 ein angehaengtes " Beta". Bei der Freigabe von
        // 1.0.0 fiel das Wort an drei Stellen weg (README, bootstrap.php,
        // install.sql) — diese vierte wurde uebersehen, weil es dort nicht in
        // einer Zeichenkette steht, sondern zur Laufzeit angehaengt wird. Ein
        // ueber den Adminbereich eingespieltes Paket machte damit aus 1.5.1
        // ein "1.5.1 Beta", und die Selbstheilung in bootstrap.php holte es
        // nicht zurueck: sie schneidet alles ab dem ersten Leerzeichen weg und
        // sieht deshalb keinen Unterschied.
        set_setting('forum_version', $version);
    } catch (Exception $e) {}
}
