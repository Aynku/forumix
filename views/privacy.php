<?php if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); } ?>
<?php
  $imp  = imprint_data();
  $page = get_page('privacy');
?>
<div class="section-heading">🔒 <?= e($page['title'] ?? 'Datenschutzerklärung') ?></div>

<div class="card" style="padding:28px 32px;line-height:1.7">

  <?php if (($imp['is_default'] ?? false) && is_admin(current_user())): ?>
    <div class="flash flash-warning" style="margin-bottom:20px">
      ⚠️ <strong>Hinweis für Administratoren:</strong> Die Kontaktdaten sind noch nicht ausgefüllt.
      Bitte unter <a href="<?= e(FORUM_URL) ?>/admin/?action=legal">Admin → Rechtliches</a> die Daten eintragen.
    </div>
  <?php endif; ?>

  <?php if ($page): ?>
    <?= render_page_content('privacy') ?>
  <?php else: ?>
    <p>Die Datenschutzerklärung wurde noch nicht eingerichtet.</p>
  <?php endif; ?>

</div>
