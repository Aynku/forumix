<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// lib/pages.php – Editierbare statische Seiten (Impressum, Datenschutz, Nutzungsbedingungen)

/**
 * Eine Page aus der DB laden.
 *
 * @return array{page_id:int, slug:string, title:string, content:string}|null
 */
function get_page(string $slug): ?array {
    try {
        $row = db_fetch('SELECT * FROM pages WHERE slug = ?', [$slug]);
        return $row ?: null;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Alle Pages auflisten (für Admin).
 */
function list_pages(): array {
    try {
        return db_fetchAll('SELECT page_id, slug, title, updated_at FROM pages ORDER BY slug');
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Eine Page speichern (Update only — Pages werden über Migration angelegt).
 */
function save_page(string $slug, string $content): bool {
    try {
        db_execute(
            'UPDATE pages SET content = ? WHERE slug = ?',
            [$content, $slug]
        );
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Platzhalter im Page-Inhalt durch echte Werte ersetzen.
 *
 * Unterstützt:
 *   {{name}}, {{street}}, {{city}}, {{email}}, {{forum_name}}, {{forum_url}}
 */
function replace_page_placeholders(string $content): string {
    $imp = imprint_data();
    $replacements = [
        '{{name}}'       => $imp['name'],
        '{{street}}'     => $imp['street'],
        '{{city}}'       => $imp['city'],
        '{{email}}'      => $imp['email'],
        '{{forum_name}}' => (string) (get_setting('forum_name') ?: 'Forum'),
        '{{forum_url}}'  => FORUM_URL,
        '{{date}}'       => date('d.m.Y'),
        '{{month_year}}' => date('m.Y'),
    ];

    // Module duerfen eigene Platzhalter beisteuern — etwa einen Absatz fuer
    // die Datenschutzerklaerung, wenn sie Daten an Dritte geben. Sie stehen
    // hinten: einen der festen Platzhalter kann ein Modul so nicht ersetzen.
    if (function_exists('module_platzhalter')) {
        foreach (module_platzhalter() as $name => $eintrag) {
            if (isset($replacements[$name])) continue;
            $replacements[$name] = $eintrag['text'];
        }
    }

    return strtr($content, $replacements);
}

/**
 * Komplette Page rendern: Content laden, Platzhalter ersetzen, BBCode parsen.
 * Liefert HTML-String. Wenn Page nicht existiert: leerer String.
 */
function render_page_content(string $slug): string {
    $page = get_page($slug);
    if (!$page) return '';
    $content = replace_page_placeholders($page['content']);
    return bbcode($content);
}

/**
 * Liste der verfügbaren Platzhalter für die Admin-Hilfe.
 *
 * @return array<string,string>  ['{{platzhalter}}' => 'Beschreibung']
 */
function page_placeholder_list(): array {
    $liste = [
        '{{name}}'       => 'Name des Verantwortlichen (aus Settings)',
        '{{street}}'     => 'Straße und Hausnummer',
        '{{city}}'       => 'PLZ und Ort',
        '{{email}}'      => 'Kontakt-E-Mail',
        '{{forum_name}}' => 'Name des Forums',
        '{{forum_url}}'  => 'URL des Forums',
        '{{date}}'       => 'Heutiges Datum (dd.mm.yyyy)',
        '{{month_year}}' => 'Aktueller Monat und Jahr (mm.yyyy)',
    ];

    // Was aktive Module mitbringen, gehoert in die Hilfe — sonst weiss
    // niemand, dass es den Platzhalter gibt.
    if (function_exists('module_platzhalter')) {
        foreach (module_platzhalter() as $name => $eintrag) {
            $liste[$name] = $eintrag['hilfe'];
        }
    }
    return $liste;
}
