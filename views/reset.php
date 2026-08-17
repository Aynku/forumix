<?php if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); } ?>
<?php /** @var bool $invalid; @var bool $done; @var string $error; @var string $token */ ?>

<div style="max-width:460px;margin:0 auto">
  <div class="section-heading">Passwort zurücksetzen</div>
  <div class="form-card">
    <?php if ($invalid): ?>
      <div class="flash flash-error">Dieser Link ist ungültig oder abgelaufen. Bitte fordere einen neuen an.</div>
      <p style="margin-top:14px;text-align:center">
        <a href="<?= e(FORUM_URL) ?>/forgot.php" class="btn btn-primary btn-sm">Neuen Link anfordern</a>
      </p>
    <?php elseif ($done): ?>
      <div class="flash flash-success">Passwort erfolgreich gesetzt! Du kannst dich jetzt anmelden.</div>
      <p style="margin-top:14px;text-align:center">
        <a href="<?= e(FORUM_URL) ?>/login.php" class="btn btn-primary">→ Zum Login</a>
      </p>
    <?php else: ?>
      <?php if ($error): ?>
        <div class="flash flash-error"><?= e($error) ?></div>
      <?php endif; ?>
      <p style="font-size:13px;color:var(--text-muted);margin-bottom:16px">
        Hallo <strong><?= e($username) ?></strong>, wähle ein neues Passwort:
      </p>
      <form method="post" autocomplete="off" id="resetForm">
        <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
        <div class="form-row">
          <label>Neues Passwort</label>
          <div class="password-input-wrap">
            <input type="password" name="password" id="reset_pw" required autofocus
                   minlength="6" autocomplete="new-password">
            <button type="button" class="password-toggle-btn" onclick="
              const i=document.getElementById('reset_pw');
              const isPw=i.type==='password';
              i.type=isPw?'text':'password';
              this.textContent=isPw?'🙈':'👁';
              this.title=isPw?'Passwort verbergen':'Passwort anzeigen';
            " title="Passwort anzeigen" tabindex="-1">👁</button>
          </div>
          <div class="password-strength-wrap" id="reset_pw_strength" hidden>
            <div class="password-strength-bar"><div class="password-strength-fill" id="reset_pw_fill"></div></div>
            <div class="password-strength-label" id="reset_pw_label">Passwort-Stärke</div>
          </div>
          <div class="form-hint">Mindestens 6 Zeichen · empfohlen mindestens 10 mit Zahlen + Sonderzeichen</div>
        </div>
        <div class="form-row">
          <label>Passwort wiederholen</label>
          <div class="password-input-wrap">
            <input type="password" name="password2" id="reset_pw2" required
                   minlength="6" autocomplete="new-password">
            <button type="button" class="password-toggle-btn" onclick="
              const i=document.getElementById('reset_pw2');
              const isPw=i.type==='password';
              i.type=isPw?'text':'password';
              this.textContent=isPw?'🙈':'👁';
              this.title=isPw?'Passwort verbergen':'Passwort anzeigen';
            " title="Passwort anzeigen" tabindex="-1">👁</button>
          </div>
          <div class="password-match-hint" id="reset_pw_match" hidden></div>
        </div>
        <button type="submit" class="btn btn-primary">Passwort speichern</button>
      </form>
      <script>
      (function() {
        const pwField = document.getElementById('reset_pw');
        const pw2Field = document.getElementById('reset_pw2');
        const strengthWrap = document.getElementById('reset_pw_strength');
        const strengthFill = document.getElementById('reset_pw_fill');
        const strengthLabel = document.getElementById('reset_pw_label');
        const matchHint = document.getElementById('reset_pw_match');
        function calcStrength(pw) {
          if (!pw) return {label: '', class: '', pct: 0};
          let score = 0;
          if (pw.length >= 6)  score += 1;
          if (pw.length >= 10) score += 1;
          if (pw.length >= 14) score += 1;
          if (/[a-z]/.test(pw) && /[A-Z]/.test(pw)) score += 1;
          if (/\d/.test(pw))   score += 1;
          if (/[^A-Za-z0-9]/.test(pw)) score += 1;
          if (score <= 2) return {label: 'Schwach',     class: 'weak',   pct: 25};
          if (score <= 3) return {label: 'Mittelmäßig', class: 'medium', pct: 50};
          if (score <= 4) return {label: 'Gut',         class: 'good',   pct: 75};
          return                {label: 'Stark',       class: 'strong', pct: 100};
        }
        function updateStrength() {
          const v = pwField.value;
          if (!v) { strengthWrap.hidden = true; return; }
          const s = calcStrength(v);
          strengthWrap.hidden = false;
          strengthFill.style.width = s.pct + '%';
          strengthFill.className = 'password-strength-fill ' + s.class;
          strengthLabel.textContent = 'Passwort-Stärke: ' + s.label;
        }
        function updateMatch() {
          const v1 = pwField.value, v2 = pw2Field.value;
          if (!v2) { matchHint.hidden = true; return; }
          matchHint.hidden = false;
          if (v1 === v2) {
            matchHint.textContent = 'Passwörter stimmen überein';
            matchHint.className = 'password-match-hint match-ok';
          } else {
            matchHint.textContent = 'Passwörter stimmen nicht überein';
            matchHint.className = 'password-match-hint match-fail';
          }
        }
        pwField.addEventListener('input', () => { updateStrength(); updateMatch(); });
        pw2Field.addEventListener('input', updateMatch);
      })();
      </script>
    <?php endif; ?>
  </div>
</div>
