<?php
// modules/kontakt/module.php – Manifest
if (!defined('FORUM_RUNNING')) return [];

return [
    'code'        => 'kontakt',
    'name'        => 'kontakt_modul_name',
    'description' => 'kontakt_modul_beschreibung',
    'icon'        => '✉️',
    'version'     => '1.0',
    // Nur in die Fusszeile: neben Impressum und Datenschutz sucht man den
    // Kontakt, nicht neben "Foren" und "Mitglieder". Und ohne Bildchen —
    // vor "Impressum" steht auch keines.
    'menu'        => ['label' => 'kontakt_menu', 'url' => 'modules/kontakt/kontakt.php', 'ort' => 'fuss'],
    'author'      => 'Forumix',
];
