<?php if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); } ?>
<?php /** @var string $status; @var string $threadTitle */ ?>
<div style="max-width:480px;margin:40px auto">
  <div class="form-card">
    <?php if ($status === 'success'): ?>
      <h2 style="margin-top:0">Abmeldung erfolgreich</h2>
      <p style="line-height:1.6">
        Du wirst keine Benachrichtigungen mehr für
        <?php if ($threadTitle): ?>
          den Thread <strong>"<?= e($threadTitle) ?>"</strong>
        <?php else: ?>
          diesen Thread
        <?php endif; ?>
        erhalten.
      </p>
      <p style="font-size:13px;color:var(--text-muted);margin-top:14px;line-height:1.6">
        Falls du auch generell keine E-Mail-Benachrichtigungen mehr möchtest, kannst du das
        in deinen <a href="<?= e(FORUM_URL) ?>/settings.php?tab=notifications">Einstellungen</a> abschalten.
      </p>

    <?php elseif ($status === 'error'): ?>
      <h2 style="margin-top:0;color:var(--color-danger)">Etwas ist schiefgegangen</h2>
      <p style="line-height:1.6">
        Wir konnten dein Abo gerade nicht entfernen. Bitte versuche es später nochmal oder
        melde dich in deinen <a href="<?= e(FORUM_URL) ?>/settings.php?tab=notifications">Einstellungen</a>
        manuell ab.
      </p>

    <?php else: ?>
      <h2 style="margin-top:0;color:var(--color-danger)">Ungültiger Link</h2>
      <p style="line-height:1.6">
        Dieser Abmelde-Link ist nicht gültig oder bereits abgelaufen (Links sind 30 Tage gültig).
      </p>
      <p style="font-size:13px;color:var(--text-muted);margin-top:14px;line-height:1.6">
        Du kannst dich auch direkt in deinen
        <a href="<?= e(FORUM_URL) ?>/settings.php?tab=notifications">Einstellungen</a>
        von E-Mail-Benachrichtigungen abmelden.
      </p>
    <?php endif; ?>

    <p style="margin-top:20px;text-align:center">
      <a href="<?= e(FORUM_URL) ?>/" class="btn btn-ghost">← Zur Startseite</a>
    </p>
  </div>
</div>
