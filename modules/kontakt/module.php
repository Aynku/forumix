<?php
// modules/kontakt/module.php – Manifest
if (!defined('FORUM_RUNNING')) return [];

return [
    'code'        => 'kontakt',
    'name'        => 'Kontaktformular',
    'description' => 'Seite, über die auch Nicht-Mitglieder eine Nachricht schicken können. Geht per E-Mail raus und wird zusätzlich in der Datenbank abgelegt, damit nichts verloren geht.',
    'icon'        => '✉️',
    'version'     => '1.0',
    'menu'        => ['label' => '✉️ Kontakt', 'url' => 'modules/kontakt/kontakt.php'],
    'author'      => 'Forumix',
];
