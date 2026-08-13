<?php if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); } ?>
<?php
  $imp  = imprint_data();
  $page = get_page('imprint');
?>
<div class="section-heading">📄 <?= e($page['title'] ?? 'Impressum') ?></div>

<div class="card" style="padding:28px 32px">

  <?php if (($imp['is_default'] ?? false) && is_admin(current_user())): ?>
    <div class="flash flash-warning" style="margin-bottom:20px">
      ⚠️ <strong>Hinweis für Administratoren:</strong> Die Impressums-Daten sind noch nicht ausgefüllt.
      Bitte unter <a href="<?= e(FORUM_URL) ?>/admin/?action=legal">Admin → Rechtliches</a> die Daten eintragen.
    </div>
  <?php endif; ?>

  <?php if ($page): ?>
    <?= render_page_content('imprint') ?>
  <?php else: ?>
    <p>Der Impressums-Inhalt wurde noch nicht eingerichtet.</p>
  <?php endif; ?>

</div>
