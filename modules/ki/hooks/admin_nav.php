<?php
/**
 * modules/ki/hooks/admin_nav.php – Der Punkt im Adminbereich
 *
 * Die Einstellungen der Schreibhilfe gehen nur Inhaber und Administratoren
 * etwas an. In
 * der Hauptnavigation des Forums stuenden sie zwischen Mitglieder und Suche,
 * also zwischen Dingen, die alle betreffen.
 *
 * Aus render_module_hook(): $action (welche Adminseite gerade offen ist).
 */
if (!defined('FORUM_RUNNING')) return;
if (!(is_admin() || is_owner())) return;
?>
<a href="<?= FORUM_URL ?>/modules/ki/ki.php" class="admin-nav-item">✨ KI-Schreibhilfe</a>
