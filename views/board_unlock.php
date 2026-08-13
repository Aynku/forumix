<?php if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); } ?>
<?php /** @var array $board; @var string $error */ ?>
<div style="max-width:480px;margin:0 auto">
  <div class="form-card">
    <h2>🔒 Passwort-geschütztes Forum</h2>

    <div style="padding:14px;background:var(--bg-surface2);border-radius:var(--radius-sm);margin-bottom:16px">
      <div style="font-size:11px;color:var(--text-muted);margin-bottom:4px">FORUM</div>
      <strong style="font-size:16px"><?= e($board['title']) ?></strong>
      <?php if (!empty($board['description'])): ?>
        <div style="font-size:13px;color:var(--text-muted);margin-top:6px"><?= e($board['description']) ?></div>
      <?php endif; ?>
    </div>

    <p style="font-size:13px;color:var(--text-muted);margin-bottom:14px">
      Dieses Forum ist passwort-geschützt. Bitte gib das Passwort ein, das du
      vom Forum-Team erhalten hast. Du bleibst entsperrt bis zum Logout.
    </p>

    <?php if (!empty($error)): ?>
      <div class="flash flash-error" style="margin-bottom:12px">❌ <?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
      <div class="form-row">
        <label>Passwort</label>
        <input type="password" name="password" required autofocus
               placeholder="Passwort eingeben...">
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center">
        🔓 Entsperren
      </button>
    </form>

    <p style="text-align:center;margin-top:14px;font-size:12px">
      <a href="<?= e(FORUM_URL) ?>/index.php" style="color:var(--text-muted)">← Zurück zur Startseite</a>
    </p>
  </div>
</div>
