<?php if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); } ?>

<div class="form-card" style="max-width:560px;margin:40px auto;text-align:center;padding:40px 32px">
  <div style="font-size:48px;margin-bottom:16px">🔒</div>
  <h2 style="margin-bottom:12px">Account-Löschung nicht möglich</h2>
  <p style="color:var(--text-muted);font-size:14px;line-height:1.7;margin-top:14px">
    <?= e($reason) ?>
  </p>
  <p style="margin-top:24px">
    <a href="<?= e(FORUM_URL) ?>/profile.php?id=<?= (int) current_user()['user_id'] ?>" class="btn btn-primary">
      ← Zurück zum Profil
    </a>
  </p>
</div>
