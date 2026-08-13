<?php if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); } ?>
<?php /** @var bool $sent; @var string $error; @var string $method */ ?>

<div style="max-width:520px;margin:0 auto">
  <div class="section-heading">🔑 Passwort vergessen</div>

  <div class="form-card">
    <?php if ($sent): ?>
      <div class="flash flash-success">✅ Falls die E-Mail-Adresse bei uns hinterlegt ist, wurde dir ein Link geschickt. Schau auch im Spam-Ordner nach.</div>
      <p style="margin-top:14px;text-align:center">
        <a href="<?= e(FORUM_URL) ?>/login.php" class="btn btn-ghost btn-sm">← Zum Login</a>
      </p>
    <?php else: ?>

      <?php if ($error): ?>
        <div class="flash flash-error">❌ <?= e($error) ?></div>
      <?php endif; ?>

      <div class="recovery-tabs">
        <a href="<?= e(FORUM_URL) ?>/forgot.php?method=email"
           class="recovery-tab <?= $method === 'email' ? 'active' : '' ?>">✉️ E-Mail</a>
        <a href="<?= e(FORUM_URL) ?>/forgot.php?method=2fa"
           class="recovery-tab <?= $method === '2fa' ? 'active' : '' ?>">🔐 2FA-Code</a>
        <a href="<?= e(FORUM_URL) ?>/forgot.php?method=backup"
           class="recovery-tab <?= $method === 'backup' ? 'active' : '' ?>">🔑 Backup-Code</a>
      </div>

      <form method="post" autocomplete="off">
        <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
        <input type="hidden" name="_bt" value="<?= e($behaviorToken ?? '') ?>">
        <input type="hidden" name="method" value="<?= e($method) ?>">

        <?php if ($method === 'email'): ?>
          <input type="text" name="website" style="position:absolute;left:-9999px" tabindex="-1" autocomplete="off">
          <p style="font-size:13px;color:var(--text-muted);margin-bottom:14px">
            Gib deine E-Mail-Adresse ein. Wir senden dir einen Link zum Zurücksetzen.
          </p>
          <div class="form-row">
            <label>E-Mail-Adresse</label>
            <input type="email" name="email" required autofocus>
          </div>

        <?php elseif ($method === '2fa'): ?>
          <p style="font-size:13px;color:var(--text-muted);margin-bottom:14px">
            Wenn du 2FA aktiv hast: Gib deinen Benutzernamen und den aktuellen
            6-stelligen Code aus deiner Authenticator-App ein.
          </p>
          <div class="form-row">
            <label>Benutzername</label>
            <input type="text" name="username" required autofocus>
          </div>
          <div class="form-row">
            <label>2FA-Code</label>
            <input type="text" name="code" required inputmode="numeric" maxlength="6"
                   pattern="[0-9]{6}"
                   style="font-family:var(--font-mono);font-size:18px;letter-spacing:3px;text-align:center"
                   placeholder="000000">
          </div>

        <?php elseif ($method === 'backup'): ?>
          <p style="font-size:13px;color:var(--text-muted);margin-bottom:14px">
            Wenn du beim 2FA-Setup Backup-Codes erhalten hast: Gib deinen Benutzernamen
            und einen unverbrauchten Backup-Code ein. Der Code wird danach verbraucht.
          </p>
          <div class="form-row">
            <label>Benutzername</label>
            <input type="text" name="username" required autofocus>
          </div>
          <div class="form-row">
            <label>Backup-Code</label>
            <input type="text" name="code" required
                   style="font-family:var(--font-mono);font-size:16px;letter-spacing:2px"
                   placeholder="XXXXXXXX">
            <div class="form-hint">8 Zeichen, normalerweise hexadezimal (0-9, A-F).</div>
          </div>
        <?php endif; ?>

        <?php if (!empty($showCaptcha) && !empty($captcha)): ?>
          <div class="form-row captcha-row">
            <label>
              🤖 Sicherheitsfrage:
              <strong style="font-size:18px;font-family:var(--font-mono)"><?= e($captcha['question']) ?> = ?</strong>
            </label>
            <input type="number" name="_captcha_answer" required autocomplete="off"
                   inputmode="numeric" min="-99" max="99"
                   style="max-width:120px;text-align:center;font-size:18px">
            <input type="hidden" name="_captcha_token" value="<?= e($captcha['token']) ?>">
            <div class="form-hint">
              Diese Frage erscheint, weil zu viele fehlgeschlagene Versuche von deiner IP kamen.
            </div>
          </div>
        <?php endif; ?>

        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:14px">
          <?php if ($method === 'email'): ?>
            <button type="submit" class="btn btn-primary">Link senden</button>
          <?php elseif ($method === '2fa'): ?>
            <button type="submit" class="btn btn-primary">Mit 2FA bestätigen</button>
          <?php else: ?>
            <button type="submit" class="btn btn-primary">Mit Backup-Code bestätigen</button>
          <?php endif; ?>
          <a href="<?= e(FORUM_URL) ?>/login.php" class="btn btn-ghost">← Zurück</a>
        </div>
      </form>
    <?php endif; ?>
  </div>

  <?php if (!$sent): ?>
    <p style="text-align:center;margin-top:14px;font-size:12px;color:var(--text-faint)">
      Du hast 2FA nicht eingerichtet? Nutze die E-Mail-Methode.
    </p>
  <?php endif; ?>
</div>
