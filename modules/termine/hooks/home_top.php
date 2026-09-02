<?php
// Wird auf der Startseite eingebunden, solange das Modul aktiv ist.
// $currentUser und $csrfToken stehen hier bereit.
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }

$naechste = db_fetchAll(
    'SELECT termin_id, titel, beginn, ort FROM termine
     WHERE COALESCE(ende, beginn) >= NOW() ORDER BY beginn ASC LIMIT 3'
);

// Steht nichts an, steht hier auch nichts. Ein leerer Kasten ist schlechter
// als gar keiner.
if ($naechste):
?>
<div class="card" style="margin-bottom:16px">
  <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap">
    <strong><?= t('termine_next') ?></strong>
    <a class="btn btn-ghost btn-sm" href="<?= e(FORUM_URL) ?>/modules/termine/termine.php"><?= t('termine_alle_ansehen') ?></a>
  </div>
  <div style="margin-top:10px;display:grid;gap:6px;font-size:14px">
    <?php foreach ($naechste as $t): ?>
      <div>
        <span style="color:var(--text-muted)"><?= e(format_date($t['beginn'])) ?></span>
        &nbsp;·&nbsp;<?= e($t['titel']) ?>
        <?php if ($t['ort'] !== ''): ?>
          <span style="color:var(--text-muted)">· <?= e($t['ort']) ?></span>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>
