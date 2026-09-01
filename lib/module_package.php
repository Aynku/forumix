<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
/**
 * lib/module_package.php – Module als eigenes Paket einspielen
 *
 * ════════════════════════════════════════════════════════════════════
 *  ⚠️  Dasselbe wie beim Updatepaket: Hier landen PHP-Dateien auf dem
 *  Server. Das ist ein Weg zur Codeausfuehrung und deshalb nur fuer den
 *  Inhaber. Die Pruefungen kommen aus lib/update_package.php und sind
 *  dieselben — Zip-Slip, Endungen, Groesse, kein Einbinden beim Entpacken.
 * ════════════════════════════════════════════════════════════════════
 *
 * Warum ueberhaupt getrennte Pakete: Ein Modul entwickelt sich in seinem
 * eigenen Takt. Steckt es im Kernpaket, braucht jede Kleinigkeit daran eine
 * neue Forumix-Fassung — an einem einzigen Abend sind so fuenf Fassungen
 * entstanden, von denen keine den Kern betraf.
 *
 * ZIP-Aufbau:
 *   module.json     Pflicht: {"code":"ki","name":"…","version":"1.1",
 *                             "requires":{"forumix":"1.8.0"}}
 *   files/          alles darunter landet in modules/<code>/
 *     module.php
 *     install.sql   (optional — der Kern faehrt sie beim Aktivieren)
 *     …
 *
 * Kein SQL im Paket: Ein Modul bringt sein Schema in install.sql mit, und die
 * laeuft beim Aktivieren. Damit gibt es hier keinen zweiten Weg, SQL
 * auszufuehren — einer ist genug.
 */

require_once __DIR__ . '/update_package.php';

const MODULPAKET_MAX_BYTES = 10 * 1024 * 1024;   // 10 MB

/**
 * Prueft ein hochgeladenes Modul-ZIP vollstaendig, bevor etwas geschrieben wird.
 *
 * @return array{ok: bool, error?: string, manifest?: array, files?: array, zip?: ZipArchive}
 */
function modulpaket_pruefen(string $zipPfad): array {
    if (!class_exists('ZipArchive')) {
        return ['ok' => false, 'error' => t('sys_die_zip_erweiterung_fehlt')];
    }
    $groesse = @filesize($zipPfad);
    if ($groesse === false || $groesse <= 0) {
        return ['ok' => false, 'error' => t('sys_die_datei_ist_leer')];
    }
    if ($groesse > MODULPAKET_MAX_BYTES) {
        return ['ok' => false, 'error' => t('sys_das_paket_ist_groesser')
            . round(MODULPAKET_MAX_BYTES / 1024 / 1024) . ' MB.'];
    }

    $zip = new ZipArchive();
    if ($zip->open($zipPfad) !== true) {
        return ['ok' => false, 'error' => t('sys_das_archiv_laesst_sich')];
    }

    $dateien = [];
    $manifestRoh = null;

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $eintrag = (string) $zip->getNameIndex($i);

        // Dieselbe Pruefung wie beim Updatepaket, nur mit anderem Manifest.
        $fehler = update_validate_entry($eintrag, ['module.json']);
        if ($fehler !== null) {
            $zip->close();
            return ['ok' => false, 'error' => $fehler];
        }
        if (str_ends_with($eintrag, '/')) continue;

        if (strtolower($eintrag) === 'module.json') {
            $manifestRoh = $zip->getFromIndex($i);
            continue;
        }
        $dateien[] = $eintrag;
    }

    if ($manifestRoh === null || $manifestRoh === false) {
        $zip->close();
        return ['ok' => false, 'error' => t('sys_im_paket_fehlt_module')];
    }
    if (!$dateien) {
        $zip->close();
        return ['ok' => false, 'error' => t('sys_das_paket_enthaelt_keine')];
    }

    $manifest = json_decode((string) $manifestRoh, true);
    if (!is_array($manifest)) {
        $zip->close();
        return ['ok' => false, 'error' => 'module.json ist kein gültiges JSON.'];
    }

    $code = (string) ($manifest['code'] ?? '');
    if (!function_exists('module_code_is_valid') || !module_code_is_valid($code)) {
        $zip->close();
        return ['ok' => false, 'error' => t('sys_ungueltiger_modulname_in_module')];
    }
    if (trim((string) ($manifest['version'] ?? '')) === '') {
        $zip->close();
        return ['ok' => false, 'error' => t('sys_in_module_json_fehlt')];
    }

    // module.php muss dabei sein, sonst erkennt der Kern das Modul nicht.
    $hatManifestDatei = false;
    foreach ($dateien as $d) {
        if (strtolower($d) === 'files/module.php') { $hatManifestDatei = true; break; }
    }
    if (!$hatManifestDatei) {
        $zip->close();
        return ['ok' => false, 'error' => t('sys_im_paket_fehlt_files')];
    }

    $verlangt = modulpaket_fassung_pruefen($manifest);
    if ($verlangt !== null) {
        $zip->close();
        return ['ok' => false, 'error' => $verlangt];
    }

    return ['ok' => true, 'manifest' => $manifest, 'files' => $dateien, 'zip' => $zip];
}

/**
 * Verlangt das Modul eine neuere Forumix-Fassung, als hier laeuft?
 *
 * Ohne diese Pruefung installiert sich ein Modul und faellt danach mit
 * "Call to undefined function" um — an einer Stelle, die niemand mit dem
 * Modul in Verbindung bringt.
 *
 * @return string|null Fehlertext, oder null wenn alles passt
 */
function modulpaket_fassung_pruefen(array $manifest): ?string {
    $verlangt = (string) ($manifest['requires']['forumix'] ?? '');
    if ($verlangt === '') return null;

    $hier = defined('FORUM_VERSION_CODE') ? FORUM_VERSION_CODE : '0';
    // " Beta" und Aehnliches stoert den Vergleich.
    $sauber = static fn (string $v): string => preg_replace('/[^0-9.].*$/', '', trim($v)) ?: '0';

    if (version_compare($sauber($hier), $sauber($verlangt), '<')) {
        return t('sys_dieses_modul_verlangt_forumix') . $verlangt . ', hier läuft ' . $hier . '.';
    }
    return null;
}

/**
 * Entpackt das geprüfte Paket nach modules/<code>/.
 *
 * Vorhandene Dateien werden ueberschrieben, nicht vorhandene bleiben liegen:
 * Ein Modul, das eine Datei nicht mehr braucht, laesst sie zurueck. Das ist
 * unschoen, aber harmlos — und die Alternative waere, ein Verzeichnis zu
 * loeschen, in dem moeglicherweise etwas liegt, das jemand hineingelegt hat.
 *
 * @return array{ok: bool, error?: string, geschrieben?: int, code?: string}
 */
function modulpaket_einspielen(ZipArchive $zip, array $manifest, array $dateien): array {
    $code  = (string) $manifest['code'];
    $ziel  = dirname(__DIR__) . '/modules/' . $code;

    if (!is_dir($ziel) && !@mkdir($ziel, 0755, true) && !is_dir($ziel)) {
        return ['ok' => false, 'error' => t('sys_das_verzeichnis_modules') . $code . ' liess sich nicht anlegen.'];
    }

    $geschrieben = 0;
    foreach ($dateien as $eintrag) {
        $relativ = substr($eintrag, strlen('files/'));
        if ($relativ === '' || $relativ === false) continue;

        $pfad = $ziel . '/' . $relativ;

        // Zweiter Riegel gegen Ausbrechen: der fertige Pfad muss unter dem
        // Zielverzeichnis liegen. Der erste steckt in update_validate_entry();
        // hier zaehlt das Ergebnis, nicht die Absicht.
        $echtZiel = realpath($ziel);
        $echtDir  = dirname($pfad);
        if (!is_dir($echtDir) && !@mkdir($echtDir, 0755, true) && !is_dir($echtDir)) {
            return ['ok' => false, 'error' => t('sys_unterverzeichnis_liess_sich_nicht') . $relativ];
        }
        $echtVater = realpath(dirname($pfad));
        if ($echtZiel === false || $echtVater === false
            || !str_starts_with($echtVater . '/', $echtZiel . '/')) {
            return ['ok' => false, 'error' => t('sys_pfad_zeigt_aus_dem') . $relativ];
        }

        $inhalt = $zip->getFromName($eintrag);
        if ($inhalt === false) {
            return ['ok' => false, 'error' => t('sys_datei_liess_sich_nicht') . $relativ];
        }
        if (file_put_contents($pfad, $inhalt) === false) {
            return ['ok' => false, 'error' => t('sys_datei_liess_sich_nicht_2') . $relativ];
        }
        @chmod($pfad, 0644);
        $geschrieben++;
    }

    return ['ok' => true, 'geschrieben' => $geschrieben, 'code' => $code];
}
