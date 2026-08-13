<?php if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); } ?>
<div class="form-card" style="max-width:560px;margin:40px auto;text-align:center;padding:40px 32px">
  <div style="font-size:48px;margin-bottom:16px;opacity:0.5">👤</div>
  <h2 style="margin-bottom:12px;color:var(--text-muted)">
    <em><?= e($profile['username']) ?></em>
  </h2>
  <p style="color:var(--text-muted);font-size:14px;line-height:1.6">
    Dieser Account wurde gelöscht.
  </p>
  <?php if (!empty($profile['deleted_at'])): ?>
    <p style="color:var(--text-faint);font-size:12px;margin-top:8px">
      Gelöscht am <?= e(format_date($profile['deleted_at'])) ?>
    </p>
  <?php endif; ?>
  <p style="color:var(--text-faint);font-size:12px;margin-top:14px;line-height:1.6">
    Beiträge dieses Benutzers bleiben erhalten, persönliche Daten wurden vollständig entfernt.
  </p>
  <p style="margin-top:24px">
    <a href="<?= e(FORUM_URL) ?>/index.php" class="btn btn-ghost btn-sm">← Zur Startseite</a>
  </p>
</div>
