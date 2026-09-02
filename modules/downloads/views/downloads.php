<?php if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); } ?>
<?php
  /** @var array $nachKategorie; @var array $kategorien; @var bool $canAdmin;
      @var string $maxGroesse; @var string $selbst */
?>

<div class="section-heading">📥 <?= t('downloads_titel_seite') ?></div>

<?php if ($canAdmin): ?>
  <form method="post" enctype="multipart/form-data" class="form-card" style="margin-bottom:18px">
    <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
    <input type="hidden" name="tun" value="hochladen">

    <h3 style="margin-top:0"><?= t('downloads_datei_einstellen') ?></h3>

    <div class="form-row">
      <label><?= t('downloads_titel') ?></label>
      <input type="text" name="titel" maxlength="140" required>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
      <div class="form-row">
        <label><?= t('downloads_kategorie') ?> <span style="color:var(--text-faint)">(<?= t('optional') ?>)</span></label>
        <input type="text" name="kategorie" maxlength="80" list="dl-kategorien"
               placeholder="<?= e(t('downloads_kategorie_beispiel')) ?>">
        <datalist id="dl-kategorien">
          <?php foreach ($kategorien as $k): ?>
            <option value="<?= e($k) ?>"></option>
          <?php endforeach; ?>
        </datalist>
      </div>
      <div class="form-row">
        <label><?= t('downloads_datei') ?></label>
        <input type="file" name="datei" required>
        <div class="form-hint"><?= t('downloads_bis') ?> <?= e($maxGroesse) ?>. <?= t('downloads_typen') ?></div>
      </div>
    </div>

    <div class="form-row">
      <label><?= t('downloads_beschreibung') ?> <span style="color:var(--text-faint)">(<?= t('optional') ?>)</span></label>
      <textarea name="beschreibung" maxlength="2000" style="min-height:80px"></textarea>
    </div>

    <button class="btn btn-primary"><?= t('downloads_hochladen') ?></button>
  </form>
<?php endif; ?>

<?php if (empty($nachKategorie)): ?>
  <div class="card" style="padding:40px;text-align:center">
    <div style="font-size:48px;margin-bottom:12px">📥</div>
    <strong><?= t('downloads_noch_keine_dateien_eingestellt') ?></strong>
    <?php if (!$canAdmin): ?>
      <div style="color:var(--text-muted);margin-top:6px"><?= t('downloads_schau_spaeter_noch_einmal') ?></div>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php foreach ($nachKategorie as $kategorie => $dateien): ?>
  <h3 style="margin:22px 0 10px"><?= e($kategorie) ?></h3>

  <?php foreach ($dateien as $d): ?>
    <div class="card" style="margin-bottom:10px">
      <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap">
        <div>
          <a href="<?= e(FORUM_URL) ?>/modules/downloads/datei.php?id=<?= (int) $d['download_id'] ?>"
             style="font-size:15px;font-weight:600"><?= e($d['titel']) ?></a>
          <div style="font-size:13px;color:var(--text-muted);margin-top:4px">
            <?php
              $groesse = (int) $d['groesse'];
              echo e($groesse >= 1048576
                  ? number_format($groesse / 1048576, 1, ',', '.') . ' MB'
                  : number_format(max(1, $groesse / 1024), 0, ',', '.') . ' KB');
            ?>
            &nbsp;·&nbsp;<?= (int) $d['klicks'] ?>&nbsp;<?= (int) $d['klicks'] === 1 ? 'Abruf' : 'Abrufe' ?>
            &nbsp;·&nbsp;<?= e(format_date($d['created_at'])) ?>
          </div>
        </div>

        <?php if ($canAdmin): ?>
          <form method="post" style="display:inline"
                onsubmit="return confirm(<?= json_encode(t('downloads_loeschen_frage')) ?>)">
            <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="tun" value="loeschen">
            <input type="hidden" name="id" value="<?= (int) $d['download_id'] ?>">
            <button class="btn btn-ghost btn-sm"><?= t('downloads_loeschen') ?></button>
          </form>
        <?php endif; ?>
      </div>

      <?php if (!empty($d['beschreibung'])): ?>
        <div style="margin-top:10px;font-size:14px;line-height:1.7">
          <?= nl2br(e($d['beschreibung'])) ?>
        </div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
<?php endforeach; ?>
