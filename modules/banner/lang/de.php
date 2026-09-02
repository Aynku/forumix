<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// modules/banner/lang/de.php
//
// Die Texte dieses Moduls. Der Kern legt sie unter seine eigenen —
// siehe load_language() in lib/lang.php.

return [
    'banner_denied' => 'Das Band dürfen nur Inhaber und Administratoren setzen.',
    'banner_disabled' => 'Das Banner-Modul ist nicht aktiv.',
    'banner_kind_good' => 'Gute Nachricht',
    'banner_kind_info' => 'Hinweis',
    'banner_kind_warning' => 'Warnung',
    'banner_saved' => 'Band gespeichert.',
    'banner_title' => 'Ankündigungsband',
    'banner_art' => 'Art',
    'banner_bestimmt_nur_die_farbe' => 'Bestimmt nur die Farbe.',
    'banner_das_band_steht_ueber' => 'Das Band steht über allem, auf jeder Seite.',
    'banner_das_merkt_sich_der' => 'Das merkt sich der Browser des Besuchers, nicht das Forum.',
    'banner_nur_text_kein_html' => 'Nur Text, kein HTML. Höchstens 300 Zeichen.',
    'banner_ohne_haken_bleibt_es' => 'Ohne Haken bleibt es aus, der Text bleibt aber gespeichert.',
    'banner_speichern' => 'Speichern',
    'banner_text' => 'Text',
    'banner_wer_es_wegklickt_sieht' => 'Wer es wegklickt, sieht es nicht wieder — bis der Text sich ändert.',
    'banner_wie_es_sich_verhaelt' => 'Wie es sich verhält',
    'banner_heading' => '📣 Ankündigungsband',
    'banner_nav' => 'Band',
    'banner_modul_name' => 'Ankündigungsband',
    'banner_modul_beschreibung' => 'Band über der ganzen Seite, für Wartungshinweise und Ankündigungen. Text und Farbe im Modul selbst, Besucher können es wegklicken.',

    'banner_hinweis_schliessen' => 'Hinweis schließen',
];
