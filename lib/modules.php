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
            'author'       => $manifest['author']      ?? 'unbekannt',
            'requires'     => $manifest['requires']    ?? [],
            'menu'         => $manifest['menu']        ?? null,
            'platzhalter'  => $manifest['platzhalter'] ?? [],
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
/**
 * Modulname prüfen, bevor er in einen Pfad wandert — der Name kommt aus der
 * Anfrage und landet in einem include.
 */
function module_code_is_valid(string $code): bool {
    return preg_match('/^[a-z0-9_-]+$/i', $code) === 1;
}

function enable_module(string $code): array {
    if (!module_code_is_valid($code)) {
        return ['ok' => false, 'error' => 'Ungültiger Modulname'];
    }
    $modulesDir = __DIR__ . '/../modules';
    $manifestFile = $modulesDir . '/' . $code . '/module.php';
    if (!file_exists($manifestFile)) {
        return ['ok' => false, 'error' => 'Modul nicht gefunden'];
    }

    $manifest = include $manifestFile;
    $code     = $manifest['code'];

    try {
        $existing = db_fetch('SELECT * FROM modules WHERE code = ?', [$code]);

        // Das Schema kommt bei JEDEM Aktivieren, nicht nur beim ersten.
        //
        // Der Grund ist ein Fall, der am 22.08.2026 live aufgeschlagen ist:
        // Ein Modul bekam in einer neuen Fassung eine zusaetzliche Tabelle.
        // Weil install.sql nur bei der Erstinstallation lief, fehlte sie —
        // und aus- und wieder einschalten half nicht, denn das Modul war ja
        // installiert. Es gab schlicht keinen Weg, eine Tabelle nachzuliefern.
        //
        // Ausgefuehrt werden nur CREATE-TABLE-Anweisungen. Sie tragen
        // IF NOT EXISTS und richten deshalb beim zweiten Lauf nichts an.
        // Alles andere — INSERT etwa — bleibt der Erstinstallation
        // vorbehalten: ein wiederholtes INSERT legt Daten doppelt an.
        $sqlFile = $modulesDir . '/' . $code . '/install.sql';
        if (file_exists($sqlFile)) {
            $anweisungen = sql_in_anweisungen((string) file_get_contents($sqlFile));
            foreach ($anweisungen as $stmt) {
                $istTabelle = (bool) preg_match('/^CREATE\s+TABLE/i', $stmt);
                if (!$existing || $istTabelle) {
                    DB::get()->exec($stmt);
                }
            }
        }

        if (!$existing) {
            // name gehoert mit in das INSERT: die Spalte ist NOT NULL ohne
            // Vorgabewert, und mit STRICT_TRANS_TABLES — der Voreinstellung —
            // scheitert das Einfuegen sonst mit Fehler 1364. Damit liess sich
            // ein Modul kein einziges Mal aktivieren.
            db_execute('INSERT INTO modules (code, name, enabled, version) VALUES (?, ?, 1, ?)',
                [$code, (string) ($manifest['name'] ?? $code), $manifest['version'] ?? '1.0']);
        } else {
            // Name und Fassung mitziehen: sie stehen im Manifest und koennen
            // sich mit einer neuen Fassung aendern. Angezeigt wird ohnehin der
            // aus dem Manifest — ein veralteter Wert in der Tabelle ist
            // trotzdem eine Falle fuer jeden, der dort nachsieht.
            db_execute('UPDATE modules SET enabled = 1, name = ?, version = ? WHERE code = ?',
                [(string) ($manifest['name'] ?? $code), $manifest['version'] ?? '1.0', $code]);
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
 * Zerlegt eine .sql-Datei in einzelne Anweisungen.
 *
 * Getrennt wird am Semikolon — mit einer Ausnahme, die einen halben Abend
 * kosten kann: Zeilenkommentare fliegen vorher raus. Ein Semikolon MITTEN IM
 * KOMMENTAR ("… bleibt erhalten; die Anzeige nicht.") teilt sonst den
 * Kommentar, und die zweite Haelfte landet als eigene Anweisung bei der
 * Datenbank. Der Fehler zeigt dann auf deutschen Fliesstext und sagt
 * "Syntax error" — man sucht ihn ueberall, nur nicht dort.
 *
 * Entfernt werden nur Zeilen, die mit -- beginnen. Ein -- mitten in einer
 * Zeile koennte in einem Text stehen und bleibt deshalb unangetastet.
 */
function sql_in_anweisungen(string $sql): array {
    $zeilen = preg_split('/\R/', $sql) ?: [];
    $ohne   = [];
    foreach ($zeilen as $z) {
        if (str_starts_with(ltrim($z), '--')) continue;
        $ohne[] = $z;
    }

    $teile = array_map('trim', explode(';', implode("\n", $ohne)));
    return array_values(array_filter($teile, static fn ($t) => $t !== ''));
}

/**
 * Modul deaktivieren (Daten bleiben in der DB erhalten)
 */
function disable_module(string $code): array {
    if (!module_code_is_valid($code)) {
        return ['ok' => false, 'error' => 'Ungültiger Modulname'];
    }
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
 * Welche Tabellen hat dieses Modul angelegt?
 *
 * Gelesen aus seiner install.sql — das ist die einzige Stelle, an der ein
 * Modul Schema anlegt. Kern-Tabellen werden ausgenommen: Ein Modul, dessen
 * install.sql versehentlich (oder absichtlich) `CREATE TABLE users` enthaelt,
 * darf beim Entfernen nicht die Benutzer mitnehmen.
 *
 * @return array<int, string> Tabellennamen
 */
function module_tabellen(string $code): array {
    if (!module_code_is_valid($code)) return [];

    $sql = @file_get_contents(__DIR__ . '/../modules/' . $code . '/install.sql');
    if ($sql === false) return [];

    $gefunden = [];
    foreach (sql_in_anweisungen($sql) as $stmt) {
        if (preg_match('/^CREATE\\s+TABLE\\s+(?:IF\\s+NOT\\s+EXISTS\\s+)?`?([a-z0-9_]+)`?/i', $stmt, $t)) {
            $gefunden[] = strtolower($t[1]);
        }
    }

    $kern = module_kern_tabellen();
    return array_values(array_unique(array_filter(
        $gefunden,
        static fn (string $n): bool => !in_array($n, $kern, true)
    )));
}

/**
 * Die Tabellen des Forums selbst, aus install/install.sql.
 *
 * Sie stehen hier nicht als Liste im Code: Die waere bei der naechsten neuen
 * Kern-Tabelle veraltet, und zwar unbemerkt — bis jemand ein Modul entfernt
 * und die Tabelle mitgeht.
 */
function module_kern_tabellen(): array {
    static $liste = null;
    if ($liste !== null) return $liste;

    $liste = [];
    $sql = @file_get_contents(__DIR__ . '/../install/install.sql');
    if ($sql !== false && preg_match_all('/CREATE\\s+TABLE\\s+(?:IF\\s+NOT\\s+EXISTS\\s+)?`?([a-z0-9_]+)`?/i', $sql, $t)) {
        $liste = array_map('strtolower', $t[1]);
    }
    // Fallback, falls install.sql nach der Installation entfernt wurde: die
    // Tabellen, ohne die kein Forum laeuft.
    if (!$liste) {
        $liste = ['users', 'posts', 'threads', 'boards', 'categories', 'settings',
                  'modules', 'reports', 'user_groups', 'group_members', 'pages'];
    }
    return $liste;
}

/**
 * Entfernt ein Modul vollstaendig: Dateien, Tabellen, Eintrag.
 *
 * Entfernen heisst entfernen. Wer die Daten behalten will, schaltet das Modul
 * ab — dafuer gibt es die Stufe dazwischen, und dort bleibt alles stehen.
 *
 * Ein aktives Modul wird nicht entfernt: Sonst verschwinden mitten im Betrieb
 * die Dateien unter einer Seite, die gerade jemand offen hat.
 *
 * @return array{ok: bool, error?: string, geloescht?: int, tabellen?: array}
 */
function module_entfernen(string $code): array {
    if (!module_code_is_valid($code)) {
        return ['ok' => false, 'error' => 'Ungültiger Modulname'];
    }

    // Frisch aus der Datenbank, nicht ueber den Anfrage-Cache: der merkt sich
    // seine Antwort. Wer im selben Aufruf abschaltet und entfernt, bekaeme
    // sonst die Antwort von vorher.
    try {
        $zeile = db_fetch('SELECT enabled FROM modules WHERE code = ?', [$code]);
    } catch (Exception $e) {
        $zeile = null;
    }
    if ($zeile && (int) $zeile['enabled'] === 1) {
        return ['ok' => false, 'error' => 'Das Modul ist noch aktiv. Erst abschalten, dann entfernen.'];
    }

    $wurzel = realpath(__DIR__ . '/../modules');
    $ziel   = realpath(__DIR__ . '/../modules/' . $code);

    if ($wurzel === false || $ziel === false) {
        return ['ok' => false, 'error' => 'Das Modulverzeichnis gibt es nicht.'];
    }
    // Der Name kommt aus der Anfrage. module_code_is_valid() laesst keine
    // Schraegstriche zu, aber ein Symlink im Modulordner koennte woanders
    // hinzeigen — geprueft wird deshalb das Ergebnis, nicht die Absicht.
    if ($ziel === $wurzel || !str_starts_with($ziel . '/', $wurzel . '/')) {
        return ['ok' => false, 'error' => 'Der Pfad zeigt aus dem Modulverzeichnis heraus.'];
    }

    // Die Tabellennamen VOR dem Loeschen der Dateien holen — danach ist die
    // install.sql weg, aus der sie stammen.
    $tabellen = module_tabellen($code);

    $zahl = module_verzeichnis_leeren($ziel);
    if ($zahl < 0) {
        return ['ok' => false, 'error' => 'Nicht alles liess sich löschen — fehlen Schreibrechte?'];
    }
    @rmdir($ziel);

    $weg = [];
    foreach ($tabellen as $t) {
        try {
            // Der Name stammt aus einem CREATE TABLE der eigenen install.sql
            // und ist gegen [a-z0-9_] geprueft — trotzdem in Anfuehrungszeichen.
            DB::get()->exec('DROP TABLE IF EXISTS `' . $t . '`');
            $weg[] = $t;
        } catch (Exception $e) {
            error_log('[modul ' . $code . '] Tabelle ' . $t . ' blieb: ' . $e->getMessage());
        }
    }

    try {
        db_execute('DELETE FROM modules WHERE code = ?', [$code]);
    } catch (Exception $e) {
        error_log('[modul ' . $code . '] Eintrag blieb: ' . $e->getMessage());
    }

    return ['ok' => true, 'geloescht' => $zahl, 'tabellen' => $weg];
}

/**
 * Loescht ein Verzeichnis samt Inhalt.
 *
 * Symlinks werden entfernt, nicht verfolgt: Ein Verweis im Modulordner auf
 * /var/www zeigt sonst dorthin, wohin er zeigt.
 *
 * @return int Zahl der geloeschten Dateien, -1 bei einem Fehler
 */
function module_verzeichnis_leeren(string $pfad): int {
    $zahl = 0;
    $eintraege = @scandir($pfad);
    if ($eintraege === false) return -1;

    foreach ($eintraege as $e) {
        if ($e === '.' || $e === '..') continue;
        $voll = $pfad . '/' . $e;

        if (is_link($voll) || is_file($voll)) {
            if (!@unlink($voll)) return -1;
            $zahl++;
            continue;
        }
        if (is_dir($voll)) {
            $tiefer = module_verzeichnis_leeren($voll);
            if ($tiefer < 0) return -1;
            $zahl += $tiefer;
            if (!@rmdir($voll)) return -1;
        }
    }
    return $zahl;
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
 * Menuepunkte der aktiven Module, fuer Navigation und Fussbereich.
 *
 * Vorher stand jeder Modul-Link fest im Layout — die FAQ mit einem eigenen
 * if darum. Jedes weitere Modul haette eine Zeile im Kern gebraucht, obwohl
 * Module genau das nicht sollen. Jetzt meldet ein Modul seinen Punkt im
 * Manifest an:
 *
 *   'menu' => ['label' => '📅 Termine', 'url' => 'modules/termine/termine.php']
 *
 * Mehrere Punkte gehen auch, dann eine Liste solcher Arrays. Die URL ist
 * relativ zur Forum-Wurzel; etwas anderes wird nicht angenommen, damit ein
 * Modul keinen fremden Link in die Navigation des Forums haengen kann.
 */
function module_menu_links(): array {
    $links = [];
    foreach (available_modules() as $mod) {
        if (!$mod['enabled'] || empty($mod['menu'])) continue;

        $menu      = $mod['menu'];
        $eintraege = isset($menu['url']) ? [$menu] : $menu;
        if (!is_array($eintraege)) continue;

        foreach ($eintraege as $e) {
            if (!is_array($e) || empty($e['url']) || empty($e['label'])) continue;

            // 'admin' => true blendet den Punkt fuer alle anderen aus. Das ist
            // Bequemlichkeit, keine Absicherung — die Seite dahinter muss ihre
            // Rechte selbst pruefen.
            if (!empty($e['admin']) && !(is_admin() || is_owner())) continue;

            $url = ltrim((string) $e['url'], '/');
            // Keine absoluten Adressen und kein Ausbrechen aus dem Forum.
            if (preg_match('#^[a-z]+://#i', $url) || str_contains($url, '..')) continue;
            // 'ort' sagt, wo der Punkt erscheint: 'menue', 'fuss' oder
            // 'beides'. Ohne Angabe bleibt es bei 'beides' — so war es vorher,
            // und bestehende Module sollen sich nicht ploetzlich anders
            // verhalten. Sinnvoll ist die Angabe trotzdem: ein Punkt, der oben
            // UND unten steht, sagt zweimal dasselbe.
            $ort = strtolower((string) ($e['ort'] ?? 'beides'));
            if (!in_array($ort, ['menue', 'fuss', 'beides'], true)) $ort = 'beides';

            $links[] = [
                'label' => (string) $e['label'],
                'url'   => FORUM_URL . '/' . $url,
                'ort'   => $ort,
            ];
        }
    }
    return $links;
}

/**
 * Die Modulpunkte fuer eine Stelle: 'menue' (Kopfleiste) oder 'fuss'.
 */
function module_menu_links_fuer(string $ort): array {
    return array_values(array_filter(
        module_menu_links(),
        fn ($l) => ($l['ort'] ?? 'beides') === 'beides' || ($l['ort'] ?? '') === $ort
    ));
}

/**
 * Platzhalter, die Module fuer Seitentexte beisteuern.
 *
 * Ein Modul legt dafuer `platzhalter.php` an und gibt ein Array zurueck:
 *   ['{{ki_hinweis}}' => ['text' => '…', 'hilfe' => 'Wofuer das gut ist']]
 *
 * Warum ueberhaupt: Rechtstexte sind bearbeitbare Seiten. Ein Modul, das
 * einen Absatz beisteuern muss, kann ihn nicht einfach unten anhaengen — dort
 * steht das Datum ("Stand: 08.2026"), und danach gehoert nichts mehr hin. Mit
 * einem Platzhalter setzt der Betreiber die Stelle selbst.
 *
 * $kontext ist der Text, in den eingesetzt wird — leer, wenn nur die Liste
 * gebraucht wird (Adminhilfe).
 *
 * @return array<string, array{text: string, hilfe: string}>
 */
function module_platzhalter(string $kontext = ''): array {
    $alle = [];

    foreach (available_modules() as $code => $mod) {
        // Die NAMEN stehen im Manifest und gelten auch fuer abgeschaltete
        // Module. Das ist der ganze Sinn: Wer ein Modul abschaltet, will
        // seinen Absatz los — und nicht "{{ki_hinweis}}" mitten in der
        // Datenschutzerklaerung stehen haben. Ohne Eintrag hier bliebe der
        // Platzhalter unersetzt im Text sichtbar.
        foreach ((array) ($mod['platzhalter'] ?? []) as $name => $hilfe) {
            if (!preg_match('/^\{\{[a-z0-9_]+\}\}$/', (string) $name)) continue;
            $alle[$name] = [
                'text'  => '',
                'hilfe' => (string) $hilfe . ($mod['enabled'] ? '' : ' — Modul ist abgeschaltet'),
            ];
        }

        // Der TEXT kommt nur von aktiven Modulen.
        if (!$mod['enabled']) continue;
        $datei = __DIR__ . '/../modules/' . $code . '/platzhalter.php';
        if (!file_exists($datei)) continue;

        try {
            // Der Text, in den eingesetzt wird, steht der eingebundenen Datei
            // als $kontext zur Verfuegung — damit ein Absatz sich einpassen
            // kann, etwa in eine laufende Nummerierung.
            $liste = include $datei;
        } catch (Throwable $e) {
            error_log('[modul ' . $code . '] Platzhalter: ' . $e->getMessage());
            continue;
        }
        if (!is_array($liste)) continue;

        foreach ($liste as $name => $eintrag) {
            // Nur echte Platzhalter-Namen: der Wert wandert durch strtr() in
            // einen Rechtstext.
            if (!preg_match('/^\{\{[a-z0-9_]+\}\}$/', (string) $name)) continue;
            $alle[$name] = [
                'text'  => (string) ($eintrag['text']  ?? ''),
                'hilfe' => (string) ($eintrag['hilfe'] ?? $code),
            ];
        }
    }
    return $alle;
}

/**
 * Wie render_module_hook(), gibt die Ausgabe aber zurueck statt sie
 * auszugeben.
 *
 * Fuer Vorlagen: dort steht der Haken mitten im Text, und was er ausgibt,
 * muss an genau dieser Stelle landen. Ein echo waere je nach Pufferung vorher
 * oder nachher da. Aufruf: {{ module_hook_html('name')|raw }}
 */
function module_hook_html(string $hookName, array $context = []): string {
    ob_start();
    render_module_hook($hookName, $context);
    return (string) ob_get_clean();
}

/**
 * Ereignis-Haken: ein Modul erfaehrt, dass etwas geschehen ist.
 *
 * Der Unterschied zu render_module_hook(): der hier zeigt nichts an, er
 * verarbeitet. Beide Eigenschaften sind Absicht und beide sind wichtig:
 *
 *  - Ausgabe wird verworfen. Ein Modul, das hier versehentlich etwas
 *    ausgibt, wuerde sonst mitten in eine Weiterleitung schreiben und den
 *    Seitenaufbau zerlegen — an einer Stelle, die niemand mit dem Modul in
 *    Verbindung bringt.
 *  - Ausnahmen werden gefangen. Ein Ereignis laeuft NACH der eigentlichen
 *    Arbeit: der Beitrag steht schon in der Datenbank. Ein Modul, das hier
 *    stolpert, darf den Benutzer nicht auf eine Fehlerseite schicken und ihn
 *    glauben lassen, sein Beitrag sei verloren.
 *
 * Die Dateien liegen unter modules/<code>/events/<name>.php.
 */
function module_event(string $name, array $context = []): void {
    if (!preg_match('/^[a-z0-9_]+$/', $name)) return;

    $currentUser = current_user();
    if ($context) extract($context, EXTR_SKIP);

    foreach (available_modules() as $code => $mod) {
        if (!$mod['enabled']) continue;
        $datei = __DIR__ . '/../modules/' . $code . '/events/' . $name . '.php';
        if (!file_exists($datei)) continue;

        ob_start();
        try {
            include $datei;
        } catch (Throwable $e) {
            error_log('[modul ' . $code . '] Ereignis ' . $name . ': ' . $e->getMessage());
        } finally {
            ob_end_clean();
        }
    }
}

/**
 * Render-Hook: Modul kann an verschiedenen Stellen Inhalt injizieren.
 * Wird im Layout aufgerufen, z.B. {render_module_hook('home_top')}
 */
function render_module_hook(string $hookName, array $context = []): void {
    // Wichtige Variablen für Hooks im Scope verfügbar machen
    $currentUser = current_user();
    $csrfToken   = $_SESSION['csrf_token'] ?? '';

    // Ein Haken mitten in einer Schleife nuetzt nichts, wenn er nicht weiss,
    // um welchen Beitrag es geht. EXTR_SKIP, damit ein Modul $currentUser
    // nicht ueberschreiben kann.
    if ($context) extract($context, EXTR_SKIP);

    foreach (available_modules() as $code => $mod) {
        if (!$mod['enabled']) continue;
        $hookFile = __DIR__ . '/../modules/' . $code . '/hooks/' . $hookName . '.php';
        if (file_exists($hookFile)) {
            include $hookFile;
        }
    }
}
