<?php if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); } ?>
<?php /** @var string $title; @var string $message */ ?>
<div style="max-width:520px;margin:60px auto;text-align:center">
  <div class="form-card">
    <div style="font-size:48px;margin-bottom:14px">🔍</div>
    <h2 style="margin-bottom:10px"><?= e($title ?? 'Fehler') ?></h2>
    <p style="font-size:13px;color:var(--text-muted);margin-bottom:20px">
      <?= e($message ?? 'Ein unbekannter Fehler ist aufgetreten.') ?>
    </p>
    <a href="<?= e(FORUM_URL) ?>/" class="btn btn-primary">← Zur Startseite</a>
  </div>
</div>
