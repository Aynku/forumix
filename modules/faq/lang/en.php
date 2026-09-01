<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// modules/faq/lang/en.php
//
// Die Texte dieses Moduls. Der Kern legt sie unter seine eigenen —
// siehe load_language() in lib/lang.php.

return [
    'faq_category_added' => 'Category created.',
    'faq_category_deleted' => 'Category deleted.',
    'faq_category_not_empty' => 'The category still holds questions — delete or move those first.',
    'faq_disabled' => 'The FAQ is not switched on at the moment.',
    'faq_question_added' => 'Question added.',
    'faq_question_deleted' => 'Question deleted.',
    'faq_question_updated' => 'Question updated.',
    'faq_title' => 'FAQ – frequently asked questions',
    'faq_anlegen' => '➕ Create',
    'faq_antwort_bbcode_erlaubt' => 'Answer (BBCode allowed)',
    'faq_frage' => 'Question',
    'faq_fuer_alle_sichtbar' => 'Visible to everyone',
    'faq_haeufig_gestellte_fragen' => '❓ Frequently asked questions',
    'faq_hinzufuegen' => '➕ Add',
    'faq_icon' => 'Icon',
    'faq_kategorie' => 'Category',
    'faq_keine_fragen_in_dieser' => 'No questions in this category.',
    'faq_klick_hier_um_die' => 'Click here to add the first question',
    'faq_loeschen' => '🗑️ Delete',
    'faq_name' => 'Name',
    'faq_neue_frage_hinzufuegen' => '➕ Add a new question',
    'faq_neue_kategorie' => '📂 New category',
    'faq_noch_keine_faqs_vorhanden' => 'No FAQs yet',
    'faq_menu' => 'FAQ',
    'faq_modul_name' => 'FAQ / frequently asked questions',
    'faq_modul_beschreibung' => 'A structured help page with categories (general, posts, account, moderation). Admins maintain the questions, everyone can read them. Footer link.',
];
