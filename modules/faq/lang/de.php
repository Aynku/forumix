<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// modules/faq/lang/de.php
//
// Die Texte dieses Moduls. Der Kern legt sie unter seine eigenen —
// siehe load_language() in lib/lang.php.

return [
    'faq_category_added' => 'Kategorie angelegt.',
    'faq_category_deleted' => 'Kategorie gelöscht.',
    'faq_category_not_empty' => 'Kategorie enthält noch Fragen – diese erst löschen oder verschieben.',
    'faq_disabled' => 'Die FAQ ist aktuell nicht aktiv.',
    'faq_question_added' => 'Frage hinzugefügt.',
    'faq_question_deleted' => 'Frage gelöscht.',
    'faq_question_updated' => 'Frage aktualisiert.',
    'faq_title' => 'FAQ – Häufige Fragen',
    'faq_anlegen' => '➕ Anlegen',
    'faq_antwort_bbcode_erlaubt' => 'Antwort (BBCode erlaubt)',
    'faq_frage' => 'Frage',
    'faq_fuer_alle_sichtbar' => 'Für alle sichtbar',
    'faq_haeufig_gestellte_fragen' => '❓ Häufig gestellte Fragen',
    'faq_hinzufuegen' => '➕ Hinzufügen',
    'faq_icon' => 'Icon',
    'faq_kategorie' => 'Kategorie',
    'faq_keine_fragen_in_dieser' => 'Keine Fragen in dieser Kategorie.',
    'faq_klick_hier_um_die' => 'Klick hier um die erste Frage anzulegen',
    'faq_loeschen' => '🗑️ Löschen',
    'faq_name' => 'Name',
    'faq_neue_frage_hinzufuegen' => '➕ Neue Frage hinzufügen',
    'faq_neue_kategorie' => '📂 Neue Kategorie',
    'faq_noch_keine_faqs_vorhanden' => 'Noch keine FAQs vorhanden',
    'faq_menu' => 'FAQ',
    'faq_modul_name' => 'FAQ / Häufige Fragen',
    'faq_modul_beschreibung' => 'Strukturierte Hilfe-Seite mit Kategorien (Allgemein, Beiträge, Konto, Moderation). Admins können Fragen pflegen, alle User können sie lesen. Footer-Link.',
];
