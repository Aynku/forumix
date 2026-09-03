<?php
// lib/migrations.php – Migration-Manager für den Admin-Bereich
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }

/**
 * Stellt sicher dass die Tracking-Tabelle existiert
 */
function ensure_migrations_table(): void {
    try {
        db_execute("
            CREATE TABLE IF NOT EXISTS `migrations` (
                `version`      VARCHAR(20) PRIMARY KEY,
                `name`         VARCHAR(100) NOT NULL,
                `executed_at`  DATETIME DEFAULT CURRENT_TIMESTAMP,
                `executed_by`  INT UNSIGNED DEFAULT NULL,
                `output`       TEXT DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    } catch (Exception $e) {}
}

/**
 * Verfügbare Migrationen vom Dateisystem auslesen.
 * Gibt sortiertes Array zurück: [['version' => 'v7', 'file' => '...', 'name' => 'Owner-Rolle'], ...]
 */
function available_migrations(): array {
    $dir = __DIR__ . '/../install/migrations';
    if (!is_dir($dir)) return [];

    $files = glob($dir . '/migrate*.php');
    $migrations = [];

    foreach ($files as $file) {
        $name = basename($file, '.php');

        // Version aus Dateinamen extrahieren:
        //  migrate.php          → v1
        //  migrate_v2.php       → v2
        //  migrate_v14.php      → v14
        //  migrate_v14_2.php    → v14_2  (Sub-Version z.B. für Fixes)
        if ($name === 'migrate') {
            $version = 'v1';
            $title = 'IP-Tracking (v0.2)';
        } elseif (preg_match('/^migrate_v(\d+(?:_\d+)?)$/', $name, $m)) {
            $version = 'v' . $m[1];
            $title = migration_title($version);
        } else {
            continue;
        }

        // Sort-Key: "14_2" → 14.02 für korrekte Sortierung
        $sortKey = substr($version, 1);
        if (str_contains($sortKey, '_')) {
            [$maj, $min] = explode('_', $sortKey, 2);
            $sortValue = (float) ($maj . '.' . str_pad($min, 2, '0', STR_PAD_LEFT));
        } else {
            $sortValue = (float) $sortKey;
        }

        $migrations[] = [
            'version' => $version,
            'file'    => $file,
            'name'    => $title,
            'sort'    => $sortValue,
        ];
    }

    // Nach Version sortieren
    usort($migrations, fn($a, $b) => $a['sort'] <=> $b['sort']);
    return $migrations;
}

function migration_title(string $version): string {
    /*
     * Die Titel stehen im Sprachpaket, nicht hier. Sie erscheinen im
     * Adminbereich unter "Forum-Updates" — also dort, wo jemand nachliest,
     * was eine Migration getan hat. Der Schluessel traegt die Kennung der
     * Migration; die aendert sich nie, der Text darf es.
     */
    $titles = [
        'v1' => t('mig_v1'),
        'v2' => t('mig_v2'),
        'v3' => t('mig_v3'),
        'v4' => t('mig_v4'),
        'v5' => t('mig_v5'),
        'v6' => t('mig_v6'),
        'v7' => t('mig_v7'),
        'v8' => t('mig_v8'),
        'v9' => t('mig_v9'),
        'v10' => t('mig_v10'),
        'v11' => t('mig_v11'),
        'v12' => t('mig_v12'),
        'v13' => t('mig_v13'),
        'v14' => t('mig_v14'),
        'v14_2' => t('mig_v14_2'),
        'v15' => t('mig_v15'),
        'v15_2' => t('mig_v15_2'),
        'v16' => t('mig_v16'),
        'v16_3' => t('mig_v16_3'),
        'v18_1' => t('mig_v18_1'),
        'v18_6' => t('mig_v18_6'),
        'v18_7' => t('mig_v18_7'),
        'v18_10' => t('mig_v18_10'),
        'v18_16' => t('mig_v18_16'),
        'v18_24' => t('mig_v18_24'),
        'v18_25' => t('mig_v18_25'),
        'v18_31' => t('mig_v18_31'),
        'v18_36' => t('mig_v18_36'),
        'v18_38' => t('mig_v18_38'),
        'v18_42' => t('mig_v18_42'),
        'v18_48' => t('mig_v18_48'),
        'v18_52' => t('mig_v18_52'),
        'v18_56' => t('mig_v18_56'),
        'v25_6' => t('mig_v25_6'),
        'v25_7' => t('mig_v25_7'),
        'v26' => t('mig_v26'),
        'v28' => t('mig_v28'),
        'v29' => t('mig_v29'),
        'v30' => t('mig_v30'),
        'v31' => t('mig_v31'),
        'v32' => t('mig_v32'),
    ];
    return $titles[$version] ?? $version;
}

/**
 * Hat eine bestimmte Migration schon stattgefunden?
 */
function migration_was_executed(string $version): bool {
    try {
        ensure_migrations_table();
        return db_count('SELECT COUNT(*) FROM migrations WHERE version = ?', [$version]) > 0;
    } catch (Exception $e) { return false; }
}

/**
 * Liste aller bisher gelaufenen Migrationen mit Datum.
 *
 * Hinweis (seit v0.18.39): Der angezeigte Titel wird vorrangig aus
 * migration_title() geholt (das aktuelle Mapping im Code). Das gespeicherte
 * 'name' in der DB ist nur ein Fallback. So aktualisieren sich Titel
 * rückwirkend wenn der Code-Eintrag verbessert wird – auch wenn die Migration
 * längst gelaufen ist und nicht neu ausgeführt werden kann.
 */
function executed_migrations(): array {
    try {
        ensure_migrations_table();
        $rows = db_fetchAll('
            SELECT m.*, u.username AS executor_name
            FROM migrations m
            LEFT JOIN users u ON u.user_id = m.executed_by
            ORDER BY m.executed_at DESC
        ');
        // Display-Name aus aktuellem Mapping nachziehen
        foreach ($rows as &$r) {
            $current = migration_title($r['version']);
            // Nur überschreiben wenn das Mapping einen "echten" Titel liefert
            // (sonst gibt es ja nur die Version zurück, dann lieber der DB-Name)
            if ($current !== $r['version']) {
                $r['name'] = $current;
            }
        }
        return $rows;
    } catch (Exception $e) { return []; }
}

/**
 * Migration ausführen (inkludiert die Datei in einer geschützten Umgebung)
 * Gibt ['success' => bool, 'output' => string] zurück
 */
function execute_migration(string $version): array {
    $migs = available_migrations();
    $found = null;
    foreach ($migs as $m) {
        if ($m['version'] === $version) { $found = $m; break; }
    }

    if (!$found) return ['success' => false, 'output' => t('sys_migration_nicht_gefunden')];
    if (!is_file($found['file'])) return ['success' => false, 'output' => t('sys_datei_nicht_lesbar')];

    // Output capturen
    ob_start();
    $error = null;
    try {
        include $found['file'];
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
    $output = ob_get_clean();

    // Aus dem HTML-Output nur den relevanten Teil extrahieren
    $cleanOutput = '';
    if (preg_match_all('/<div class="req-row">.*?<\/div>/s', $output, $matches)) {
        foreach ($matches[0] as $row) {
            // HTML-Tags entfernen, nur Text
            $text = trim(strip_tags($row));
            $text = preg_replace('/\s+/', ' ', $text);
            $cleanOutput .= $text . "\n";
        }
    } else {
        $cleanOutput = $error ?: t('sys_migration_ausgefuehrt');
    }

    // In Tracking-Tabelle eintragen
    $cu = current_user();
    try {
        ensure_migrations_table();
        db_execute(
            'INSERT INTO migrations (version, name, executed_by, output) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE executed_at = NOW(), executed_by = VALUES(executed_by), output = VALUES(output)',
            [$version, $found['name'], (int) ($cu['user_id'] ?? 0), $cleanOutput]
        );

        // Wenn erfolgreich: forum_version auf den entsprechenden Wert setzen
        if ($error === null) {
            $versionMap = [
                'v1' => '0.2 Beta',
                'v2' => '0.2 Beta',
                'v3' => '0.3 Beta',
                'v4' => '0.4 Beta',
                'v5' => '0.5 Beta',
                'v6' => '0.6 Beta',
                'v7' => '0.7 Beta',
                'v8' => '0.8 Beta',
                'v9' => '0.9 Beta',
                'v10' => '0.10 Beta',
                'v11' => '0.11 Beta',
                'v12' => '0.12 Beta',
                'v13' => '0.13 Beta',
                'v14' => '0.14 Beta',
                'v14_2' => '0.14.2 Beta',
                'v15' => '0.15 Beta',
                'v15_2' => '0.15.3 Beta',
                'v16' => '0.16 Beta',
            ];
            if (isset($versionMap[$version])) {
                set_setting('forum_version', $versionMap[$version]);
            }
        }
    } catch (Exception $e) {}

    return [
        'success' => $error === null,
        'output'  => $cleanOutput,
        'error'   => $error,
    ];
}
