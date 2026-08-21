<?php
// modules/ki/module.php – Manifest
if (!defined('FORUM_RUNNING')) return [];

return [
    'code'        => 'ki',
    'name'        => 'KI-Schreibhilfe',
    'description' => 'Knöpfe im Editor: Rechtschreibung, Kürzen, Zusammenfassen, Übersetzen. '
                   . 'Jedes Ergebnis erscheint erst in einer Vorschau — übernommen wird nur, '
                   . 'was der Verfasser übernimmt. Der API-Schlüssel kommt vom Betreiber.',
    'icon'        => '✨',
    'version'     => '1.0',
    // Kein 'menu': die Einstellungen stehen im Adminbereich, nicht in der
    // Hauptnavigation des Forums — siehe hooks/admin_nav.php.
    'author'      => 'Forumix',
];
