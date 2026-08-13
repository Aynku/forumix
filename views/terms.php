<?php if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); } ?>
<?php $page = get_page('terms'); ?>
<div class="section-heading">📜 <?= e($page['title'] ?? 'Nutzungsbedingungen') ?></div>

<div class="card" style="padding:28px 32px;line-height:1.7">

  <?php if ($page): ?>
    <?= render_page_content('terms') ?>
  <?php else: ?>
    <p>Die Nutzungsbedingungen wurden noch nicht eingerichtet.</p>
  <?php endif; ?>

</div>
