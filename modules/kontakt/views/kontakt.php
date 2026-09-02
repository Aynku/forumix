<?php if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); } ?>
<?php
  /** @var array $werte; @var bool $gesendet; @var string $fehler;
      @var string $empfaenger; @var bool $canAdmin; @var array $letzte; @var string $selbst */
?>

<div class="section-heading">✉️ Kontakt</div>

<?php if ($gesendet): ?>
  <div class="card" style="padding:34px;text-align:center">
    <div style="font-size:44px;margin-bottom:10px">✅</div>
    <strong><?= t('kontakt_nachricht_ist_raus') ?></strong>
    <div style="color:var(--text-muted);margin-top:6px">
      <?= t('kontakt_danke_wir_melden_uns') ?>
    </div>
    <div style="margin-top:16px">
      <a class="btn btn-ghost" href="<?= e($selbst) ?>">Noch eine Nachricht schreiben</a>
    </div>
  </div>

<?php else: ?>

  <?php if ($empfaenger === '' && $canAdmin): ?>
    <div class="card" style="border-left:3px solid var(--color-warning);margin-bottom:16px">
      <strong><?= t('kontakt_no_recipient_heading') ?></strong>
      <div style="color:var(--text-muted);margin-top:6px;font-size:14px">
        <?= t('kontakt_hinweis_adresse', '<em>' . e(t('kontakt_adminbereich_rechtliches')) . '</em>',
                                          '<em>' . e(t('kontakt_e_mail')) . '</em>') ?>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($fehler !== ''): ?>
    <div class="card" style="border-left:3px solid var(--color-danger);margin-bottom:16px">
      <?= e($fehler) ?>
    </div>
  <?php endif; ?>

  <form method="post" class="form-card">
    <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">

    <?php /* Honigtopf: fuer Menschen unsichtbar, fuer Skripte ein Feld wie jedes andere. */ ?>
    <div style="position:absolute;left:-9999px" aria-hidden="true">
      <label><?= t('kontakt_website') ?></label>
      <input type="text" name="website" tabindex="-1" autocomplete="off">
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
      <div class="form-row">
        <label><?= t('kontakt_dein_name') ?></label>
        <input type="text" name="absender" maxlength="80" required
               value="<?= e($werte['absender']) ?>">
      </div>
      <div class="form-row">
        <label><?= t('kontakt_deine_e_mail') ?></label>
        <input type="email" name="email" maxlength="190" required
               value="<?= e($werte['email']) ?>">
        <div class="form-hint"><?= t('kontakt_nur_damit_wir_antworten') ?></div>
      </div>
    </div>

    <div class="form-row">
      <label><?= t('kontakt_betreff') ?></label>
      <input type="text" name="betreff" maxlength="140" required
             value="<?= e($werte['betreff']) ?>">
    </div>

    <div class="form-row">
      <label><?= t('kontakt_nachricht') ?></label>
      <textarea name="nachricht" maxlength="5000" required
                style="min-height:150px"><?= e($werte['nachricht']) ?></textarea>
    </div>

    <button class="btn btn-primary"><?= t('kontakt_nachricht_senden') ?></button>
  </form>
<?php endif; ?>

<?php if ($canAdmin && $letzte): ?>
  <h3 style="margin:26px 0 10px"><?= t('kontakt_eingegangen') ?></h3>
  <?php foreach ($letzte as $n): ?>
    <div class="card" style="margin-bottom:10px">
      <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap">
        <div>
          <strong><?= e($n['betreff']) ?></strong>
          <div style="font-size:13px;color:var(--text-muted);margin-top:4px">
            <?= e($n['absender']) ?> &lt;<?= e($n['email']) ?>&gt;
            &nbsp;·&nbsp;<?= e(format_date($n['created_at'])) ?>
            <?php if (!(int) $n['versandt']): ?>
              &nbsp;·&nbsp;<span style="color:var(--color-warning)"><?= e(t('kontakt_nicht_zugestellt')) ?></span>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php if (!empty($n['nachricht'])): ?>
        <div style="margin-top:10px;font-size:14px;line-height:1.7">
          <?= nl2br(e($n['nachricht'])) ?>
        </div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
