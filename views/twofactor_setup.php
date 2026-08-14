<?php if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); } ?>
<?php /** @var bool $has2fa; @var string $newSecret; @var string $otpUrl; @var string $error; @var string $success; @var array $backupCodes */ ?>

<div style="max-width:560px;margin:0 auto">
  <div class="section-heading">🔐 Zwei-Faktor-Authentifizierung</div>

  <?php if ($error): ?><div class="flash flash-error">❌ <?= e($error) ?></div><?php endif; ?>
  <?php if ($success): ?><div class="flash flash-success">✅ <?= e($success) ?></div><?php endif; ?>

  <?php if (!empty($backupCodes)): ?>
    <div class="card" style="margin-bottom:16px;padding:18px">
      <h3 style="font-size:14px;margin-bottom:10px">📋 Deine Backup-Codes</h3>
      <p style="font-size:12px;color:var(--text-muted);margin-bottom:12px">
        Bewahre diese Codes sicher auf! Wenn du deine Authenticator-App verlierst, kannst du dich damit einloggen. Jeder Code ist nur einmal verwendbar.
      </p>
      <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:6px;font-family:var(--font-mono)">
        <?php foreach ($backupCodes as $bc): ?>
          <code style="padding:8px;background:var(--bg-surface2);border:1px solid var(--border);border-radius:4px;text-align:center;font-size:13px"><?= e($bc) ?></code>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <div class="form-card">
    <?php if ($has2fa): ?>
      <h2>✅ 2FA ist aktiv</h2>
      <p style="font-size:13px;color:var(--text-muted);margin-bottom:14px">
        Dein Konto ist durch 2-Faktor-Authentifizierung geschützt.
      </p>

      <form method="post">
        <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
        <input type="hidden" name="action" value="disable">
        <div class="form-row">
          <label>Passwort zur Bestätigung</label>
          <input type="password" name="password" required>
        </div>
        <button type="submit" class="btn btn-danger" onclick="return confirm('2FA wirklich deaktivieren?')">🔓 2FA deaktivieren</button>
      </form>

    <?php else: ?>
      <h2>📲 2FA einrichten</h2>
      <p style="font-size:13px;color:var(--text-muted);margin-bottom:16px">
        Scanne den QR-Code mit einer Authenticator-App (Google Authenticator, Authy, 1Password, …) oder gib den Secret-Code manuell ein.
      </p>

      <div style="text-align:center;margin:20px 0">
        <?php /* Kein QR vom Fremddienst — die URL trüge den 2FA-Schlüssel. */ ?>
        <div style="padding:14px;border:1px dashed var(--border);border-radius:8px;color:var(--text-muted);font-size:13px">
          Trage den Code unten in deiner Authenticator-App unter
          <strong>„Schlüssel manuell eingeben"</strong> ein.
        </div>
      </div>

      <div style="background:var(--bg-surface2);border:1px solid var(--border);border-radius:6px;padding:12px;margin-bottom:16px;text-align:center">
        <div style="font-size:11px;color:var(--text-muted);margin-bottom:4px">Secret-Code (manuell):</div>
        <code style="font-size:14px;font-weight:600;letter-spacing:1px;word-break:break-all"><?= e($newSecret) ?></code>
      </div>

      <form method="post">
        <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
        <input type="hidden" name="action" value="enable">
        <input type="hidden" name="secret" value="<?= e($newSecret) ?>">
        <div class="form-row">
          <label>6-stelligen Code zur Bestätigung eingeben</label>
          <input type="text" name="code" required autofocus inputmode="numeric"
                 maxlength="6" pattern="[0-9]{6}"
                 style="font-family:var(--font-mono);font-size:18px;letter-spacing:3px;text-align:center"
                 placeholder="000000">
        </div>
        <button type="submit" class="btn btn-primary">✓ 2FA aktivieren</button>
        <a href="<?= e(FORUM_URL) ?>/profile.php?id=<?= (int) $currentUser['user_id'] ?>" class="btn btn-ghost">Abbrechen</a>
      </form>
    <?php endif; ?>
  </div>
</div>
