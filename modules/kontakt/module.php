<?php
// modules/kontakt/module.php – Manifest
if (!defined('FORUM_RUNNING')) return [];

return [
    'code'        => 'kontakt',
    'name'        => 'Kontaktformular',
    'description' => 'Seite, über die auch Nicht-Mitglieder eine Nachricht schicken können. Geht per E-Mail raus und wird zusätzlich in der Datenbank abgelegt, damit nichts verloren geht.',
    'icon'        => '✉️',
    'version'     => '1.0',
    // Nur in die Fusszeile: neben Impressum und Datenschutz sucht man den
    // Kontakt, nicht neben "Foren" und "Mitglieder". Und ohne Bildchen —
    // vor "Impressum" steht auch keines.
    'menu'        => ['label' => 'Kontakt', 'url' => 'modules/kontakt/kontakt.php', 'ort' => 'fuss'],
    'author'      => 'Forumix',
];
