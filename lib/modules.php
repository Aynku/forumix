<?php
// lib/modules.php – Modul-System
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }

/**
 * Liefert alle verfügbaren Module aus dem /modules/-Ordner
 * Kombiniert mit dem DB-Status (enabled/disabled, installed_at)
 */
function available_modules(): array {
    $modules = [];
    $modulesDir = __DIR__ . '/../modules';
    if (!is_dir($modulesDir)) return [];

    // Status aller Module aus DB
    $dbStatus = [];
    try {
        $rows = db_fetchAll('SELECT * FROM modules');
        foreach ($rows as $r) $dbStatus[$r['code']] = $r;
    } catch (Exception $e) {}

    foreach (scandir($modulesDir) as $dir) {
        if ($dir === '.' || $dir === '..') continue;
        $manifestFile = $modulesDir . '/' . $dir . '/module.php';
        if (!file_exists($manifestFile)) continue;

        $manifest = include $manifestFile;
        if (!is_array($manifest) || empty($manifest['code'])) continue;

        $code = $manifest['code'];
        $modules[$code] = [
            'code'         => $code,
            'name'         => $manifest['name']        ?? $code,
            'description'  => $manifest['description'] ?? '',
            'icon'         => $manifest['icon']        ?? '🧩',
            'version'      => $manifest['version']     ?? '1.0',
            'author'       => $manifest['author']      ?? 'aynku.de',
            'requires'     => $manifest['requires']    ?? [],
            'enabled'      => !empty($dbStatus[$code]['enabled']),
            'installed'    => isset($dbStatus[$code]),
            'installed_at' => $dbStatus[$code]['installed_at'] ?? null,
        ];
    }
    return $modules;
}

/**
 * Prüft ob ein Modul aktiv ist (Cache pro Request)
 */
function module_enabled(string $code): bool {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            $rows = db_fetchAll('SELECT code FROM modules WHERE enabled = 1');
            foreach ($rows as $r) $cache[$r['code']] = true;
        } catch (Exception $e) {}
    }
    return isset($cache[$code]);
}

/**
 * Modul aktivieren – führt install.sql aus falls Erstinstallation
 */
function enable_module(string $code): array {
    $modulesDir = __DIR__ . '/../modules';
    $manifestFile = $modulesDir . '/' . $code . '/module.php';
    if (!file_exists($manifestFile)) {
        return ['ok' => false, 'error' => 'Modul nicht gefunden'];
    }

    $manifest = include $manifestFile;
    $code     = $manifest['code'];

    try {
        // Erstinstallation? → install.sql ausführen
        $existing = db_fetch('SELECT * FROM modules WHERE code = ?', [$code]);
        if (!$existing) {
            $sqlFile = $modulesDir . '/' . $code . '/install.sql';
            if (file_exists($sqlFile)) {
                $sql = file_get_contents($sqlFile);
                // Statements ggf. splitten an ; (best-effort, einfaches Schema)
                $stmts = array_filter(array_map('trim', explode(';', $sql)));
                foreach ($stmts as $stmt) {
                    if ($stmt !== '') DB::get()->exec($stmt);
                }
            }
            db_execute('INSERT INTO modules (code, enabled, version) VALUES (?, 1, ?)',
                [$code, $manifest['version'] ?? '1.0']);
        } else {
            db_execute('UPDATE modules SET enabled = 1 WHERE code = ?', [$code]);
        }

        // Activation-Hook (falls definiert)
        if (!empty($manifest['on_enable']) && is_callable($manifest['on_enable'])) {
            ($manifest['on_enable'])();
        }

        return ['ok' => true];
    } catch (Exception $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Modul deaktivieren (Daten bleiben in der DB erhalten)
 */
function disable_module(string $code): array {
    try {
        $modulesDir = __DIR__ . '/../modules';
        $manifestFile = $modulesDir . '/' . $code . '/module.php';

        // Deactivation-Hook (falls definiert)
        if (file_exists($manifestFile)) {
            $manifest = include $manifestFile;
            if (!empty($manifest['on_disable']) && is_callable($manifest['on_disable'])) {
                ($manifest['on_disable'])();
            }
        }

        db_execute('UPDATE modules SET enabled = 0 WHERE code = ?', [$code]);
        return ['ok' => true];
    } catch (Exception $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Config eines Moduls abrufen (JSON-Feld)
 */
function module_config(string $code): array {
    try {
        $row = db_fetch('SELECT config FROM modules WHERE code = ?', [$code]);
        if ($row && !empty($row['config'])) {
            $cfg = json_decode($row['config'], true);
            return is_array($cfg) ? $cfg : [];
        }
    } catch (Exception $e) {}
    return [];
}

/**
 * Modul-Config speichern
 */
function set_module_config(string $code, array $config): bool {
    try {
        db_execute('UPDATE modules SET config = ? WHERE code = ?',
            [json_encode($config), $code]);
        return true;
    } catch (Exception $e) { return false; }
}

/**
 * Render-Hook: Modul kann an verschiedenen Stellen Inhalt injizieren.
 * Wird im Layout aufgerufen, z.B. {render_module_hook('home_top')}
 */
function render_module_hook(string $hookName): void {
    // Wichtige Variablen für Hooks im Scope verfügbar machen
    $currentUser = current_user();
    $csrfToken   = $_SESSION['csrf_token'] ?? '';

    foreach (available_modules() as $code => $mod) {
        if (!$mod['enabled']) continue;
        $hookFile = __DIR__ . '/../modules/' . $code . '/hooks/' . $hookName . '.php';
        if (file_exists($hookFile)) {
            include $hookFile;
        }
    }
}
