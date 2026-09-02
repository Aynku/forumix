<?php if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); } ?>
<?php /** @var string $text; @var string $art; @var bool $aktiv; @var array $arten */ ?>

<div class="section-heading"><?= t('banner_heading') ?></div>

<form method="post" class="form-card">
  <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">

  <div class="form-row">
    <label>
      <input type="checkbox" name="aktiv" value="1" <?= $aktiv ? 'checked' : '' ?>>
      Band anzeigen
    </label>
    <div class="form-hint"><?= t('banner_ohne_haken_bleibt_es') ?></div>
  </div>

  <div class="form-row">
    <label><?= t('banner_text') ?></label>
    <textarea name="text" maxlength="300" style="min-height:70px"
              placeholder="<?= e(t('banner_platzhalter')) ?>"><?= e($text) ?></textarea>
    <div class="form-hint"><?= t('banner_nur_text_kein_html') ?></div>
  </div>

  <div class="form-row">
    <label><?= t('banner_art') ?></label>
    <select name="art">
      <?php foreach ($arten as $wert => $label): ?>
        <option value="<?= e($wert) ?>" <?= $art === $wert ? 'selected' : '' ?>><?= e(t($label)) ?></option>
      <?php endforeach; ?>
    </select>
    <div class="form-hint"><?= t('banner_bestimmt_nur_die_farbe') ?></div>
  </div>

  <button class="btn btn-primary"><?= t('banner_speichern') ?></button>
</form>

<div class="card" style="margin-top:16px">
  <strong><?= t('banner_wie_es_sich_verhaelt') ?></strong>
  <ul style="margin:8px 0 0;padding-left:18px;color:var(--text-muted);font-size:14px;line-height:1.8">
    <li><?= t('banner_das_band_steht_ueber') ?></li>
    <li><?= t('banner_wer_es_wegklickt_sieht') ?></li>
    <li><?= t('banner_das_merkt_sich_der') ?></li>
  </ul>
</div>
