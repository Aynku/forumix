<?php
/**
 * modules/ki/hooks/layout_top.php – Stylesheet einbinden
 *
 * Frueh und einmal pro Seite. Nicht im Editor-Haken, weil die Kennzeichnung
 * am Beitrag auch dann richtig aussehen muss, wenn der Leser die Schreibhilfe
 * gar nicht benutzen darf und deshalb keinen Editor bekommt.
 */
if (!defined('FORUM_RUNNING')) return;
if (!module_enabled('ki')) return;
?>
<link rel="stylesheet" href="<?= FORUM_URL ?>/modules/ki/assets/ki.css?v=1.0">
