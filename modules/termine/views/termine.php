<?php if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); } ?>
<?php
  /** @var array $kommende; @var array $vergangene; @var bool $canAdmin;
      @var ?array $bearbeiten; @var string $selbst */

  // Wandelt den DB-Wert in das Format, das <input type="datetime-local"> erwartet.
  $fuersFeld = static function (?string $dt): string {
      return $dt ? str_replace(' ', 'T', substr($dt, 0, 16)) : '';
  };

  // Eine Zeile der Liste. Zweimal gebraucht, deshalb einmal geschrieben.
  $zeile = static function (array $t) use ($canAdmin, $selbst, $fuersFeld) {
      $vorbei = strtotime($t['ende'] ?: $t['beginn']) < time();
      ?>
      <div class="card" style="margin-bottom:10px<?= $vorbei ? ';opacity:.6' : '' ?>">
        <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap">
          <div>
            <strong style="font-size:15px"><?= e($t['titel']) ?></strong>
            <div style="font-size:13px;color:var(--text-muted);margin-top:4px">
              🗓️ <?= e(format_date($t['beginn'])) ?>
              <?php if (!empty($t['ende'])): ?>
                &nbsp;bis&nbsp;<?= e(format_date($t['ende'])) ?>
              <?php endif; ?>
              <?php if ($t['ort'] !== ''): ?>
                &nbsp;·&nbsp;📍 <?= e($t['ort']) ?>
              <?php endif; ?>
            </div>
          </div>

          <?php if ($canAdmin): ?>
            <div style="display:flex;gap:6px;align-items:flex-start">
              <a class="btn btn-ghost btn-sm"
                 href="<?= e($selbst) ?>?bearbeiten=<?= (int) $t['termin_id'] ?>">Ändern</a>
              <form method="post" style="display:inline"
                    onsubmit="return confirm('Diesen Termin löschen?')">
                <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="tun" value="loeschen">
                <input type="hidden" name="id" value="<?= (int) $t['termin_id'] ?>">
                <button class="btn btn-ghost btn-sm"><?= t('termine_loeschen') ?></button>
              </form>
            </div>
          <?php endif; ?>
        </div>

        <?php if (!empty($t['beschreibung'])): ?>
          <div style="margin-top:10px;font-size:14px;line-height:1.7">
            <?= nl2br(e($t['beschreibung'])) ?>
          </div>
        <?php endif; ?>
      </div>
      <?php
  };
?>

<div class="section-heading">📅 Termine</div>

<?php if ($canAdmin): ?>
  <form method="post" class="form-card" style="margin-bottom:18px">
    <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
    <input type="hidden" name="tun" value="<?= $bearbeiten ? 'aendern' : 'anlegen' ?>">
    <?php if ($bearbeiten): ?>
      <input type="hidden" name="id" value="<?= (int) $bearbeiten['termin_id'] ?>">
    <?php endif; ?>

    <h3 style="margin-top:0"><?= $bearbeiten ? t('termine_edit') : t('termine_new') ?></h3>

    <div class="form-row">
      <label><?= t('termine_titel') ?></label>
      <input type="text" name="titel" maxlength="140" required
             value="<?= e($bearbeiten['titel'] ?? '') ?>">
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
      <div class="form-row">
        <label><?= t('termine_beginn') ?></label>
        <input type="datetime-local" name="beginn" required
               value="<?= e($fuersFeld($bearbeiten['beginn'] ?? null)) ?>">
      </div>
      <div class="form-row">
        <label><?= t('termine_ende') ?> <span style="color:var(--text-faint)">(optional)</span></label>
        <input type="datetime-local" name="ende"
               value="<?= e($fuersFeld($bearbeiten['ende'] ?? null)) ?>">
      </div>
    </div>

    <div class="form-row">
      <label><?= t('termine_ort') ?> <span style="color:var(--text-faint)">(optional)</span></label>
      <input type="text" name="ort" maxlength="140"
             value="<?= e($bearbeiten['ort'] ?? '') ?>">
    </div>

    <div class="form-row">
      <label><?= t('termine_beschreibung') ?> <span style="color:var(--text-faint)">(optional)</span></label>
      <textarea name="beschreibung" maxlength="2000" style="min-height:90px"><?= e($bearbeiten['beschreibung'] ?? '') ?></textarea>
    </div>

    <button class="btn btn-primary"><?= $bearbeiten ? t('termine_save_change') : t('termine_create') ?></button>
    <?php if ($bearbeiten): ?>
      <a class="btn btn-ghost" href="<?= e($selbst) ?>">Abbrechen</a>
    <?php endif; ?>
  </form>
<?php endif; ?>

<?php if (empty($kommende) && empty($vergangene)): ?>
  <div class="card" style="padding:40px;text-align:center">
    <div style="font-size:48px;margin-bottom:12px">📅</div>
    <strong><?= t('termine_noch_keine_termine_eingetragen') ?></strong>
    <?php if (!$canAdmin): ?>
      <div style="color:var(--text-muted);margin-top:6px"><?= t('termine_schau_spaeter_noch_einmal') ?></div>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php if ($kommende): ?>
  <h3 style="margin:18px 0 10px"><?= t('termine_kommend') ?></h3>
  <?php foreach ($kommende as $t) $zeile($t); ?>
<?php endif; ?>

<?php if ($vergangene): ?>
  <h3 style="margin:24px 0 10px;color:var(--text-muted)"><?= t('termine_vorbei') ?></h3>
  <?php foreach ($vergangene as $t) $zeile($t); ?>
<?php endif; ?>
