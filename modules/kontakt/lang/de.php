<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// modules/kontakt/lang/de.php
//
// Die Texte dieses Moduls. Der Kern legt sie unter seine eigenen —
// siehe load_language() in lib/lang.php.

return [
    'kontakt_bad_email' => 'Die E-Mail-Adresse sieht nicht richtig aus.',
    'kontakt_deliver_failed' => 'Die Nachricht konnte nicht zugestellt werden. Bitte versuche es später noch einmal.',
    'kontakt_disabled' => 'Das Kontakt-Modul ist nicht aktiv.',
    'kontakt_fields_required' => 'Bitte Name, Betreff und Nachricht ausfüllen.',
    'kontakt_mail_subject' => 'Neue Nachricht über das Kontaktformular',
    'kontakt_no_recipient' => 'Es ist keine Empfängeradresse hinterlegt. Bitte wende dich anders an uns.',
    'kontakt_send_failed' => 'Die Nachricht konnte nicht gesendet werden.',
    'kontakt_too_many' => 'Zu viele Nachrichten in kurzer Zeit. Bitte versuche es später noch einmal.',
    'kontakt_adminbereich_rechtliches' => 'Adminbereich → Rechtliches',
    'kontakt_betreff' => 'Betreff',
    'kontakt_danke_wir_melden_uns' => 'Danke. Wir melden uns, sobald jemand hineingeschaut hat.',
    'kontakt_das_formular_nimmt_nichts' => 'Das Formular nimmt nichts an, solange keine Adresse feststeht. Trage sie unter',
    'kontakt_dein_name' => 'Dein Name',
    'kontakt_deine_e_mail' => 'Deine E-Mail',
    'kontakt_e_mail' => 'E-Mail',
    'kontakt_eingegangen' => 'Eingegangen',
    'kontakt_nachricht' => 'Nachricht',
    'kontakt_nachricht_ist_raus' => 'Nachricht ist raus',
    'kontakt_nachricht_senden' => 'Nachricht senden',
    'kontakt_nur_damit_wir_antworten' => 'Nur damit wir antworten können.',
    'kontakt_website' => 'Website',
    'kontakt_no_recipient_heading' => '⚠️ Noch keine Empfängeradresse',
    'kontakt_menu' => 'Kontakt',
    'kontakt_modul_name' => 'Kontaktformular',
    'kontakt_modul_beschreibung' => 'Seite, über die auch Nicht-Mitglieder eine Nachricht schicken können. Geht per E-Mail raus und wird zusätzlich in der Datenbank abgelegt, damit nichts verloren geht.',
];
