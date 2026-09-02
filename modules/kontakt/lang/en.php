<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// modules/kontakt/lang/en.php
//
// Die Texte dieses Moduls. Der Kern legt sie unter seine eigenen —
// siehe load_language() in lib/lang.php.

return [
    'kontakt_bad_email' => 'That email address does not look right.',
    'kontakt_deliver_failed' => 'The message could not be delivered. Please try again later.',
    'kontakt_disabled' => 'The contact module is not switched on.',
    'kontakt_fields_required' => 'Please fill in name, subject and message.',
    'kontakt_mail_subject' => 'New message from the contact form',
    'kontakt_no_recipient' => 'No recipient address is set. Please reach us another way.',
    'kontakt_send_failed' => 'The message could not be sent.',
    'kontakt_too_many' => 'Too many messages in a short time. Please try again later.',
    'kontakt_adminbereich_rechtliches' => 'Admin area → Legal',
    'kontakt_betreff' => 'Subject',
    'kontakt_danke_wir_melden_uns' => 'Thank you. We will get back to you once someone has looked at it.',
    'kontakt_das_formular_nimmt_nichts' => 'The form accepts nothing while no address is set. Enter it under',
    'kontakt_dein_name' => 'Your name',
    'kontakt_deine_e_mail' => 'Your email',
    'kontakt_e_mail' => 'Email',
    'kontakt_eingegangen' => 'Received',
    'kontakt_nachricht' => 'Message',
    'kontakt_nachricht_ist_raus' => 'Message sent',
    'kontakt_nachricht_senden' => 'Send message',
    'kontakt_nur_damit_wir_antworten' => 'Only so that we can reply.',
    'kontakt_website' => 'Website',
    'kontakt_no_recipient_heading' => '⚠️ No recipient address yet',
    'kontakt_menu' => 'Contact',
    'kontakt_modul_name' => 'Contact form',
    'kontakt_modul_beschreibung' => 'A page where even non-members can send a message. It goes out by email and is also stored in the database, so nothing is lost.',

    // ── Nachgezogen ──
    'kontakt_hinweis_adresse' => 'Enter a contact email under %s, or set a sender address under %s.',
    'kontakt_nicht_zugestellt' => 'not delivered by email',
];
