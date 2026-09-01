<?php
// modules/ki/module.php – Manifest
if (!defined('FORUM_RUNNING')) return [];

return [
    'code'        => 'ki',
    'name'        => 'ki_modul_name',
    'description' => 'ki_modul_beschreibung',
    'icon'        => '✨',
    'version'     => '1.1',
    // Kein 'menu': die Einstellungen stehen im Adminbereich, nicht in der
    // Hauptnavigation des Forums — siehe hooks/admin_nav.php.

    // Der Name gehoert ins Manifest, der Text in platzhalter.php. Grund: Das
    // Manifest wird auch bei abgeschaltetem Modul gelesen, und dann muss der
    // Platzhalter durch nichts ersetzt werden — sonst steht "{{ki_hinweis}}"
    // sichtbar in der Datenschutzerklaerung.
    'platzhalter' => ['{{ki_hinweis}}' => 'Absatz zur KI-Verarbeitung (Datenschutzerklärung)'],
    'author'      => 'Forumix',
];
