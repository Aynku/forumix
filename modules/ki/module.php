<?php
// modules/ki/module.php – Manifest
if (!defined('FORUM_RUNNING')) return [];

return [
    'code'        => 'ki',
    'name'        => 'KI-Schreibhilfe',
    'description' => 'Zwei Dinge: Knöpfe im Editor für Rechtschreibung, Kürzen, '
                   . 'Zusammenfassen und Übersetzen — jedes Ergebnis erscheint erst in einer '
                   . 'Vorschau, übernommen wird nur, was der Verfasser übernimmt. Und eine '
                   . 'Vorsortierung neuer Beiträge auf Werbung und Beleidigungen, die meldet '
                   . 'statt zu löschen. Beides einzeln abschaltbar, der API-Schlüssel kommt '
                   . 'vom Betreiber.',
    'icon'        => '✨',
    'version'     => '1.1',
    // Kein 'menu': die Einstellungen stehen im Adminbereich, nicht in der
    // Hauptnavigation des Forums — siehe hooks/admin_nav.php.
    'author'      => 'Forumix',
];
