<?php if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); } ?>
<?php
  /** @var array $nachKategorie; @var array $kategorien; @var bool $canAdmin;
      @var string $maxGroesse; @var string $selbst */
?>

<div class="section-heading">📥 Downloads</div>

<?php if ($canAdmin): ?>
  <form method="post" enctype="multipart/form-data" class="form-card" style="margin-bottom:18px">
    <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
    <input type="hidden" name="tun" value="hochladen">

    <h3 style="margin-top:0">Datei einstellen</h3>

    <div class="form-row">
      <label>Titel</label>
      <input type="text" name="titel" maxlength="140" required>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
      <div class="form-row">
        <label>Kategorie <span style="color:var(--text-faint)">(optional)</span></label>
        <input type="text" name="kategorie" maxlength="80" list="dl-kategorien"
               placeholder="z.B. Anleitungen">
        <datalist id="dl-kategorien">
          <?php foreach ($kategorien as $k): ?>
            <option value="<?= e($k) ?>"></option>
          <?php endforeach; ?>
        </datalist>
      </div>
      <div class="form-row">
        <label>Datei</label>
        <input type="file" name="datei" required>
        <div class="form-hint">Bis <?= e($maxGroesse) ?>. Bilder, PDF, TXT, ZIP, RAR.</div>
      </div>
    </div>

    <div class="form-row">
      <label>Beschreibung <span style="color:var(--text-faint)">(optional)</span></label>
      <textarea name="beschreibung" maxlength="2000" style="min-height:80px"></textarea>
    </div>

    <button class="btn btn-primary">Hochladen</button>
  </form>
<?php endif; ?>

<?php if (empty($nachKategorie)): ?>
  <div class="card" style="padding:40px;text-align:center">
    <div style="font-size:48px;margin-bottom:12px">📥</div>
    <strong>Noch keine Dateien eingestellt</strong>
    <?php if (!$canAdmin): ?>
      <div style="color:var(--text-muted);margin-top:6px">Schau später noch einmal vorbei.</div>
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
                onsubmit="return confirm('Diese Datei löschen?')">
            <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="tun" value="loeschen">
            <input type="hidden" name="id" value="<?= (int) $d['download_id'] ?>">
            <button class="btn btn-ghost btn-sm">Löschen</button>
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
