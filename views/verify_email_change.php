<?php if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); } ?>
<?php /** @var array $result */ ?>
<div style="max-width:520px;margin:40px auto">
  <div class="form-card">
    <?php if (!empty($result['ok'])): ?>
      <h2 style="margin-top:0">✅ E-Mail-Adresse erfolgreich geändert</h2>
      <p style="line-height:1.6">
        Deine neue E-Mail-Adresse <strong><?= e($result['new_email']) ?></strong>
        ist jetzt aktiv. Zukünftige Passwort-Resets und Benachrichtigungen
        gehen an diese Adresse.
      </p>
      <p style="margin-top:20px;text-align:center">
        <a href="<?= e(FORUM_URL) ?>/settings.php?tab=security" class="btn btn-primary">
          → Zu meinen Einstellungen
        </a>
      </p>

    <?php else: ?>
      <h2 style="margin-top:0;color:var(--color-danger)">❌ E-Mail-Änderung fehlgeschlagen</h2>
      <p style="line-height:1.6">
        <?= e($result['error'] ?? 'Unbekannter Fehler.') ?>
      </p>
      <p style="font-size:13px;color:var(--text-muted);margin-top:14px;line-height:1.6">
        Bitte gehe in deine <a href="<?= e(FORUM_URL) ?>/settings.php?tab=security">Einstellungen</a>
        und starte die E-Mail-Änderung erneut.
      </p>
      <p style="margin-top:20px;text-align:center">
        <a href="<?= e(FORUM_URL) ?>/" class="btn btn-ghost">← Zur Startseite</a>
      </p>
    <?php endif; ?>
  </div>
</div>
