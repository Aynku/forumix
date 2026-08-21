<?php
/**
 * modules/banner/hooks/admin_nav.php – Der Punkt im Adminbereich
 *
 * Text und Farbe des Bandes setzen Inhaber und Administratoren. In der Hauptnavigation des
 * Forums stand die Seite zwischen Punkten, die alle Besucher angehen — nur
 * weil das Modulsystem lange keinen anderen Platz kannte.
 */
if (!defined('FORUM_RUNNING')) return;
if (!(is_admin() || is_owner())) return;
?>
<a href="<?= FORUM_URL ?>/modules/banner/banner.php" class="admin-nav-item">📣 Band</a>
