<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// modules/banner/lang/en.php
//
// Die Texte dieses Moduls. Der Kern legt sie unter seine eigenen —
// siehe load_language() in lib/lang.php.

return [
    'banner_denied' => 'Only owners and administrators may set the banner.',
    'banner_disabled' => 'The banner module is not switched on.',
    'banner_kind_good' => 'Good news',
    'banner_kind_info' => 'Note',
    'banner_kind_warning' => 'Warning',
    'banner_saved' => 'Banner saved.',
    'banner_title' => 'Announcement banner',
    'banner_art' => 'Kind',
    'banner_bestimmt_nur_die_farbe' => 'Only sets the colour.',
    'banner_das_band_steht_ueber' => 'The banner sits above everything, on every page.',
    'banner_das_merkt_sich_der' => 'The visitor’s browser remembers that, not the forum.',
    'banner_nur_text_kein_html' => 'Text only, no HTML. 300 characters at most.',
    'banner_ohne_haken_bleibt_es' => 'Without the tick it stays off, but the text is kept.',
    'banner_speichern' => 'Save',
    'banner_text' => 'Text',
    'banner_wer_es_wegklickt_sieht' => 'Whoever dismisses it does not see it again — until the text changes.',
    'banner_wie_es_sich_verhaelt' => 'How it behaves',
    'banner_heading' => '📣 Announcement banner',
    'banner_nav' => 'Banner',
    'banner_modul_name' => 'Announcement banner',
    'banner_modul_beschreibung' => 'A band across the whole page, for maintenance notices and announcements. Text and colour are set in the module itself; visitors can dismiss it.',

    'banner_hinweis_schliessen' => 'Dismiss notice',

    'banner_platzhalter' => 'On Saturday from 8 pm the forum will be briefly unavailable.',

    // ── Nachgezogen ──
    'banner_band_anzeigen' => 'Show the banner',
];
