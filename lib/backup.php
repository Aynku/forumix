<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// lib/backup.php – Backup-System für DB und Dateien

const BACKUP_DIR = __DIR__ . '/../backup';

/**
 * Stellt sicher, dass das Backup-Verzeichnis existiert und geschützt ist.
 */
function backup_ensure_dir(): void {
    if (!is_dir(BACKUP_DIR)) {
        @mkdir(BACKUP_DIR, 0755, true);
    }
    // .htaccess-Schutz (falls beim FTP-Upload verloren gegangen)
    $ht = BACKUP_DIR . '/.htaccess';
    if (!file_exists($ht)) {
        @file_put_contents($ht, t('sys_order_deny_allow_ndeny'));
    }
    // index.html als zusätzlicher Schutz
    $idx = BACKUP_DIR . '/index.html';
    if (!file_exists($idx)) {
        @file_put_contents($idx, '<!DOCTYPE html><title>403</title>');
    }
}

/**
 * Liefert alle Backup-Dateien sortiert nach Datum (neueste zuerst).
 *
 * @return array<int, array{filename:string, type:string, size:int, created_at:int, note:?string}>
 */
function list_backups(): array {
    backup_ensure_dir();
    // Neue Sicherungen heissen forumix_*, aeltere aynku_* werden weiter
    // gefunden — sonst waeren vorhandene Backups nach dem Umbenennen weg.
    $files = array_merge(
        glob(BACKUP_DIR . '/forumix_backup_*.{sql,zip}', GLOB_BRACE) ?: [],
        glob(BACKUP_DIR . '/aynku_backup_*.{sql,zip}', GLOB_BRACE) ?: []
    );
    $result = [];
    foreach ($files as $f) {
        $name = basename($f);
        // Format: aynku_backup_<type>_<timestamp>[_<note>].<ext>
        // type: db | files | full
        $type = 'unknown';
        if (preg_match('/(?:forumix|aynku)_backup_(db|files|full)_/', $name, $m)) {
            $type = $m[1];
        }
        // Notiz nachladen falls vorhanden
        $noteFile = $f . '.note';
        $note = file_exists($noteFile) ? file_get_contents($noteFile) : null;

        $result[] = [
            'filename'   => $name,
            'type'       => $type,
            'size'       => filesize($f) ?: 0,
            'created_at' => filemtime($f) ?: 0,
            'note'       => $note,
        ];
    }
    usort($result, fn($a, $b) => $b['created_at'] <=> $a['created_at']);
    return $result;
}

/**
 * Validiert dass ein Filename ein sicheres Backup-File ist.
 * Schutz vor Path-Traversal etc.
 */
function backup_is_safe_filename(string $filename): bool {
    if (str_contains($filename, '/') || str_contains($filename, '\\')) return false;
    if (str_contains($filename, '..')) return false;
    if (!preg_match('/^(?:forumix|aynku)_backup_(db|files|full)_[\d\-_]+\.(sql|zip)$/', $filename)) return false;
    return true;
}

/**
 * Erzeugt einen DB-Dump und speichert ihn als .sql-Datei.
 * Liefert den Dateinamen (ohne Pfad) bei Erfolg, null bei Fehler.
 */
function backup_database(?string $note = null): ?string {
    backup_ensure_dir();
    @set_time_limit(0);

    $timestamp = date('Y-m-d_H-i-s');
    $filename  = "forumix_backup_db_{$timestamp}.sql";
    $path      = BACKUP_DIR . '/' . $filename;

    $fp = @fopen($path, 'w');
    if (!$fp) return null;

    try {
        // Header
        fwrite($fp, "-- Forumix DB-Backup\n");
        fwrite($fp, "-- Erstellt: " . date('Y-m-d H:i:s') . "\n");
        fwrite($fp, "-- Version: " . FORUM_VERSION_CODE . "\n");
        fwrite($fp, "-- DB: " . DB_NAME . "\n\n");
        fwrite($fp, "SET FOREIGN_KEY_CHECKS=0;\n");
        fwrite($fp, "SET NAMES utf8mb4;\n\n");

        // Alle Tabellen finden
        $tables = db_fetchAll("SHOW TABLES");
        $tableKey = array_key_first($tables[0] ?? []) ?: 0;

        foreach ($tables as $row) {
            $table = array_values($row)[0];
            backup_dump_table($fp, $table);
        }

        fwrite($fp, "\nSET FOREIGN_KEY_CHECKS=1;\n");
        fclose($fp);

        if ($note !== null && trim($note) !== '') {
            @file_put_contents($path . '.note', trim($note));
        }

        return $filename;
    } catch (Exception $e) {
        @fclose($fp);
        @unlink($path);
        return null;
    }
}

/**
 * Schreibt CREATE TABLE und INSERT-Statements einer Tabelle in einen Stream.
 * Schoner Memory: arbeitet zeilenweise per PDO-Cursor.
 */
function backup_dump_table($fp, string $table): void {
    // CREATE TABLE
    fwrite($fp, "\n-- ─── Table: `{$table}` ─────────────────────\n");
    fwrite($fp, "DROP TABLE IF EXISTS `{$table}`;\n");
    $create = db_fetch("SHOW CREATE TABLE `{$table}`");
    if ($create) {
        $ddl = array_values($create)[1] ?? '';
        fwrite($fp, $ddl . ";\n\n");
    }

    // INSERT-Statements (gestreamed)
    $pdo  = DB::get();
    $stmt = $pdo->prepare("SELECT * FROM `{$table}`");
    $stmt->execute();

    $count = 0;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $cols = '`' . implode('`, `', array_keys($row)) . '`';
        $vals = [];
        foreach ($row as $v) {
            if ($v === null) {
                $vals[] = 'NULL';
            } else {
                $vals[] = $pdo->quote((string) $v);
            }
        }
        fwrite($fp, "INSERT INTO `{$table}` ({$cols}) VALUES (" . implode(', ', $vals) . ");\n");
        $count++;
    }
    if ($count === 0) {
        fwrite($fp, "-- (Tabelle ist leer)\n");
    }
}

/**
 * Erzeugt ein Datei-Backup als ZIP.
 * Erfasst alles AUSSER:
 *  - backup/ (sich selbst)
 *  - cache/
 *  - config.php (enthält Passwörter)
 */
function backup_files(?string $note = null): ?string {
    if (!class_exists('ZipArchive')) return null;

    backup_ensure_dir();
    @set_time_limit(0);

    $timestamp = date('Y-m-d_H-i-s');
    $filename  = "forumix_backup_files_{$timestamp}.zip";
    $path      = BACKUP_DIR . '/' . $filename;

    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        return null;
    }

    $root = realpath(__DIR__ . '/..');
    if (!$root) {
        $zip->close();
        @unlink($path);
        return null;
    }

    $excludeDirs = [
        $root . '/backup',
        $root . '/cache',
    ];
    $excludeFiles = [
        $root . '/config.php',
    ];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $file) {
        $realPath = $file->getRealPath();
        if (!$realPath) continue;

        // Excludes prüfen
        foreach ($excludeDirs as $ex) {
            if (str_starts_with($realPath, $ex . '/') || $realPath === $ex) {
                continue 2;
            }
        }
        if (in_array($realPath, $excludeFiles, true)) continue;

        $relativePath = substr($realPath, strlen($root) + 1);
        if ($file->isDir()) {
            $zip->addEmptyDir($relativePath);
        } elseif ($file->isFile()) {
            $zip->addFile($realPath, $relativePath);
        }
    }

    $zip->close();

    if ($note !== null && trim($note) !== '') {
        @file_put_contents($path . '.note', trim($note));
    }

    return $filename;
}

/**
 * Erzeugt ein Komplett-Backup: DB-Dump + Dateien in einem ZIP.
 */
function backup_full(?string $note = null): ?string {
    if (!class_exists('ZipArchive')) return null;

    // 1. DB-Dump (temporär)
    $dbName = backup_database('(Teil von Komplett-Backup)');
    if (!$dbName) return null;
    $dbPath = BACKUP_DIR . '/' . $dbName;

    // 2. Files-Backup (temporär)
    $fileName = backup_files('(Teil von Komplett-Backup)');
    if (!$fileName) {
        @unlink($dbPath);
        @unlink($dbPath . '.note');
        return null;
    }
    $filePath = BACKUP_DIR . '/' . $fileName;

    // 3. Beides in ein Komplett-ZIP packen
    $timestamp = date('Y-m-d_H-i-s');
    $fullName  = "forumix_backup_full_{$timestamp}.zip";
    $fullPath  = BACKUP_DIR . '/' . $fullName;

    $zip = new ZipArchive();
    if ($zip->open($fullPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        @unlink($dbPath); @unlink($dbPath . '.note');
        @unlink($filePath); @unlink($filePath . '.note');
        return null;
    }
    $zip->addFile($dbPath, 'database.sql');
    $zip->addFile($filePath, 'files.zip');
    $zip->setArchiveComment(
        t('sys_forumix_komplett_backup_n') .
        "Erstellt: " . date('Y-m-d H:i:s') . "\n" .
        "Version: " . FORUM_VERSION_CODE
    );
    $zip->close();

    // 4. Temporäre Teile löschen
    @unlink($dbPath); @unlink($dbPath . '.note');
    @unlink($filePath); @unlink($filePath . '.note');

    if ($note !== null && trim($note) !== '') {
        @file_put_contents($fullPath . '.note', trim($note));
    }

    return $fullName;
}

/**
 * Löscht ein Backup. Filename muss validiert sein.
 */
function delete_backup(string $filename): bool {
    if (!backup_is_safe_filename($filename)) return false;
    $path = BACKUP_DIR . '/' . $filename;
    if (!file_exists($path)) return false;
    @unlink($path . '.note');
    return @unlink($path);
}

/**
 * Liefert den vollständigen Pfad eines Backups (nach Validierung).
 */
function backup_path(string $filename): ?string {
    if (!backup_is_safe_filename($filename)) return null;
    $path = BACKUP_DIR . '/' . $filename;
    return file_exists($path) ? $path : null;
}

/**
 * Bytes als menschenlesbare Größe formatieren.
 */
function backup_format_size(int $bytes): string {
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1024 * 1024) return number_format($bytes / 1024, 1, ',', '.') . ' KB';
    if ($bytes < 1024 * 1024 * 1024) return number_format($bytes / 1024 / 1024, 1, ',', '.') . ' MB';
    return number_format($bytes / 1024 / 1024 / 1024, 2, ',', '.') . ' GB';
}
