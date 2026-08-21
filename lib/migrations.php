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
    $titles = [
        'v1' => 'v0.2 – IP-Tracking',
        'v2' => 'v0.2 – Anhänge, Notifications, Polls',
        'v3' => 'v0.3 – Reaktionen, Lesezeichen, Online, Achievements',
        'v4' => 'v0.4 – Meldungen, Warnungen, Edit-Log, Tags',
        'v5' => 'v0.5 – Passwort-Reset, 2FA, Rate-Limit, Gelesen-Marker',
        'v6' => 'v0.6 – Forum-spezifische Moderatoren',
        'v7' => 'v0.7 – Owner-Rolle',
        'v8' => 'v0.8 – Freigabe-System + Mod-Edits',
        'v9' => 'v0.9 – Version in DB',
        'v10' => 'v0.10 – Bot-Erkennung in Online-Liste',
        'v11' => 'v0.11 – Notification-Typen erweitert',
        'v12' => 'v0.12 – Gespeicherte Suchen',
        'v13' => 'v0.13 – Modul-System',
        'v14' => 'v0.14 – Design-System',
        'v14_2' => 'v0.14.2 – Original-Farben wiederherstellen',
        'v15' => 'v0.15 – Remember-Me-Tokens',
        'v15_2' => 'v0.15.3 – Owner-Rang-Aufräumung',
        'v16' => 'v0.16 – Passwort-geschützte Foren',
        'v16_3' => 'v0.16.3 – User-Löschung (DSGVO)',
        'v18_1'  => 'v0.18.1 – Smiley-Verwaltung',
        'v18_6'  => 'v0.18.6 – Editierbare Pages',
        'v18_7'  => 'v0.18.7 – Template-Editor',
        'v18_10' => 'v0.18.10 – Stale-Override-Detection',
        'v18_16' => 'v0.18.16 – CAPTCHA-System',
        'v18_24' => 'v0.18.24 – Admin-Approval für Registrierungen',
        'v18_25' => 'v0.18.25 – Einladungs-System',
        'v18_31' => 'v0.18.31 – Erweiterte Profil-Felder',
        'v18_36' => 'v0.18.36 – AGB-Pflicht bei Registrierung',
        'v18_38' => 'v0.18.38 – Bot-Verkehr-Log',
        'v18_42' => 'v0.18.42 – Default-Tags wiederherstellen',
        'v18_48' => 'v0.18.48 – Doppel-Opt-In für E-Mail-Änderung',
        'v18_52' => 'v0.18.52 – Spalte last_activity_at',
        'v18_56' => 'v0.18.56 – pending_email_token verbreitern',
        'v25_6'  => 'v0.25.6 – Zwei-Faktor: verbrauchte Codes vermerken',
        'v25_7'  => 'v0.25.7 – Zwei-Faktor: Geheimnis verschlüsselt ablegen',
        'v26'    => 'v1.1.0 – Gruppen und Rechte pro Forum',
        'v28'    => 'v1.3.0 – Papierkorb für Beiträge und Themen',
        'v29'    => 'v1.4.0 – Volltext-Index für die Suche',
        'v30'    => 'v1.5.0 – Feinere Rechte je Forum und Gruppe',
        'v31'    => 'v1.6.2 – Gruppen: Rang ersetzen und eigenes Bild',
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

    if (!$found) return ['success' => false, 'output' => 'Migration nicht gefunden.'];
    if (!is_file($found['file'])) return ['success' => false, 'output' => 'Datei nicht lesbar.'];

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
        $cleanOutput = $error ?: 'Migration ausgeführt.';
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
