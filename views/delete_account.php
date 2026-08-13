<?php if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); } ?>
<?php if ($step === 'done'): ?>

<div class="form-card" style="max-width:560px;margin:40px auto;text-align:center;padding:40px 32px">
  <div style="font-size:48px;margin-bottom:16px">👋</div>
  <h2 style="margin-bottom:12px">Account gelöscht</h2>
  <p style="color:var(--text-muted);font-size:14px;line-height:1.6">
    Dein Account wurde unwiederbringlich gelöscht. Deine Beiträge sind erhalten geblieben,
    werden aber nur noch unter „Gelöschter Benutzer" angezeigt.
  </p>
  <p style="color:var(--text-muted);font-size:13px;margin-top:14px">
    Schade, dass du gehst. Auf Wiedersehen.
  </p>
  <p style="margin-top:24px">
    <a href="<?= e(FORUM_URL) ?>/index.php" class="btn btn-primary">Zur Startseite</a>
  </p>
</div>

<?php else: ?>

<div class="section-heading">💀 Account löschen</div>

<div class="form-card" style="max-width:640px;margin:0 auto">

  <div style="background:#dc262615;border-left:3px solid #dc2626;padding:14px 18px;border-radius:var(--radius-sm);margin-bottom:20px">
    <strong style="color:#dc2626;font-size:14px">⚠️ Achtung: Diese Aktion ist endgültig.</strong>
    <p style="font-size:13px;color:var(--text-muted);margin-top:8px;line-height:1.6">
      Mit der Löschung deines Accounts wird folgendes passieren:
    </p>
    <ul style="font-size:13px;color:var(--text-muted);margin-top:6px;padding-left:22px;line-height:1.7">
      <li>Dein <strong>Benutzername</strong> wird durch „Gelöschter Benutzer" ersetzt</li>
      <li>Deine <strong>E-Mail-Adresse</strong>, dein <strong>Avatar</strong>, deine <strong>Signatur</strong>,
          deine <strong>Bio</strong> und alle <strong>persönlichen Profil-Daten</strong> werden gelöscht</li>
      <li>Deine <strong>Beiträge und Themen bleiben</strong> bestehen (sonst würden Diskussionen zerfallen),
          erscheinen aber unter „Gelöschter Benutzer"</li>
      <li>Deine <strong>privaten Nachrichten</strong> sind beim Empfänger noch lesbar, aber ohne deinen Namen</li>
      <li>Deine <strong>Lesezeichen, Abos, Benachrichtigungen, Achievements</strong> werden gelöscht</li>
      <li><strong>Login wird unmöglich</strong> – du kannst dich nie wieder anmelden</li>
    </ul>
    <p style="font-size:13px;color:var(--text-muted);margin-top:10px;line-height:1.6">
      <strong>Es gibt keine Wiederherstellung.</strong> Auch nicht durch einen Administrator.
    </p>
  </div>

  <?php if ($error): ?>
    <div style="background:#dc262625;border:1px solid #dc2626;color:#fca5a5;padding:10px 14px;border-radius:var(--radius-sm);margin-bottom:16px">
      <?= e($error) ?>
    </div>
  <?php endif; ?>

  <form method="post" autocomplete="off">
    <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">

    <div class="form-row">
      <label>Dein Passwort <span style="color:#dc2626">*</span></label>
      <input type="password" name="password" required autocomplete="current-password"
             placeholder="Dein aktuelles Passwort">
      <div class="form-hint">
        Zur Sicherheit: bestätige deine Identität mit deinem Passwort.
      </div>
    </div>

    <div class="form-row">
      <label>Bestätigung <span style="color:#dc2626">*</span></label>
      <input type="text" name="confirm_text" required autocomplete="off"
             placeholder="Tippe das Wort LÖSCHEN ein">
      <div class="form-hint">
        Gib das Wort <strong>LÖSCHEN</strong> ein (groß geschrieben), um zu bestätigen dass
        du das wirklich willst.
      </div>
    </div>

    <div style="display:flex;gap:10px;margin-top:24px">
      <button type="submit" class="btn btn-danger">💀 Meinen Account endgültig löschen</button>
      <a href="<?= e(FORUM_URL) ?>/profile.php?id=<?= (int) current_user()['user_id'] ?>" class="btn btn-ghost">Abbrechen</a>
    </div>
  </form>

</div>

<?php endif; ?>
