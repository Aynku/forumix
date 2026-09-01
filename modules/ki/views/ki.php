<?php if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); } ?>
<?php /** @var array $cfg; @var array $anbieter; @var array $befehle; @var array $gruppen;
        @var array $verbrauch; @var int $gesamt; @var ?string $probe;
        @var bool $crypt_da; @var bool $hat_schluessel */ ?>

<div class="section-heading">✨ KI-Funktionen</div>

<?php if ($probe !== null): ?>
  <div class="flash <?= $probe === '' ? 'flash-success' : 'flash-error' ?>" style="margin-bottom:16px">
    <?= $probe === '' ? t('ki_connection_ok') : e($probe) ?>
  </div>
<?php endif; ?>

<div class="card" style="margin-bottom:16px;padding:14px 16px;font-size:13px;line-height:1.7">
  <strong><?= t('ki_wer_zahlt') ?></strong> der Betreiber dieses Forums. Jeder Klick auf einen
  KI-Knopf ist eine Anfrage an den gewählten Anbieter und erscheint auf dessen
  Rechnung. Deshalb gibt es weiter unten zwei Bremsen — Zeichenzahl je Anfrage
  und Anfragen je Stunde und Person. Wer nichts einträgt, gibt nichts frei:
  ohne Schlüssel bleibt der Knopf im Editor unsichtbar.
</div>

<?php if (!empty($nachgezogen)): ?>
  <div class="flash flash-success" style="margin-bottom:16px">
    <?= t('ki_dein_api_schluessel_lag') ?>
  </div>
<?php endif; ?>

<?php if (!$crypt_da): ?>
  <div class="card" style="margin-bottom:16px;padding:14px 16px;
       border-left:3px solid var(--color-warning)">
    <strong><?= t('ki_key_first') ?></strong>
    <p style="margin:6px 0;font-size:13px;line-height:1.6">
      <?= t('ki_ohne_ihn_liegt_dein') ?>
      <code>config.php</code> auf:
    </p>
    <pre style="white-space:pre-wrap;word-break:break-all;background:var(--bg-body);
         padding:10px;border-radius:6px;margin:0"><code>define('FORUM_CRYPT_KEY', '<?= e($crypt_vorschlag) ?>');</code></pre>
    <p style="margin:8px 0 0;font-size:13px;line-height:1.6">
      <?= t('ki_danach') ?> <strong>nicht mehr ändern</strong> und beim Umzug mitnehmen — sonst ist
      nicht mehr lesbar, was damit gespeichert wurde. Der Schlüssel hilft gegen einen
      abhandengekommenen Datenbank-Abzug, nicht gegen jemanden, der den Server hat:
      der liest die <code>config.php</code> mit.
    </p>
  </div>
<?php endif; ?>

<form method="post" class="form-card">
  <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">

  <div class="form-row">
    <label><?= t('ki_anbieter') ?></label>
    <select name="anbieter">
      <?php foreach ($anbieter as $code => $a): ?>
        <option value="<?= e($code) ?>" <?= ($cfg['anbieter'] ?? 'anthropic') === $code ? 'selected' : '' ?>>
          <?= e($a['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <div class="form-hint">
      <?= t('ki_den_schluessel_gibt_es') ?>
      <?php foreach ($anbieter as $a): ?>
        <?= e($a['name']) ?> → <code><?= e($a['hilfe']) ?></code>&nbsp;&nbsp;
      <?php endforeach; ?>
    </div>
  </div>

  <div class="form-row">
    <label><?= t('ki_api_schluessel') ?></label>
    <input type="password" name="schluessel" autocomplete="off"
           placeholder="<?= $hat_schluessel ? '•••••••• (hinterlegt — leer lassen, um ihn zu behalten)' : 'noch keiner hinterlegt' ?>">
    <div class="form-hint">
      <?= t('ki_wird_verschluesselt_gespeichert_und') ?>
      <?php if ($hat_schluessel): ?>
        <label style="display:inline-block;margin-left:8px">
          <input type="checkbox" name="schluessel_weg" value="1"> hinterlegten Schlüssel entfernen
        </label>
      <?php endif; ?>
    </div>
  </div>

  <div class="form-row">
    <label><?= t('ki_modell') ?></label>
    <input type="text" name="modell" value="<?= e((string) ($cfg['modell'] ?? '')) ?>"
           placeholder="<?= e($anbieter[$cfg['anbieter'] ?? 'anthropic']['vorgabe'] ?? '') ?>">
    <div class="form-hint">
      <?= t('ki_leer_lassen_fuer_die') ?>
    </div>
  </div>

  <div class="section-heading" style="margin-top:24px"><?= t('ki_befehle') ?></div>
  <div class="form-hint" style="margin-bottom:10px">
    <strong><?= t('ki_kennzeichnen') ?></strong> heißt: der Beitrag trägt sichtbar den Hinweis, dass
    ein Sprachmodell mitgeschrieben hat. Bei einer reinen Rechtschreibkorrektur ist
    das übertrieben — beim Zusammenfassen nicht, denn dabei entscheidet das Modell,
    was wegfällt.
  </div>

  <table class="forum-table" style="margin-bottom:8px">
    <thead>
      <tr>
        <th><?= t('ki_befehl') ?></th>
        <th style="width:200px"><?= t('ki_modell') ?></th>
        <th style="width:90px;text-align:center"><?= t('ki_freigegeben') ?></th>
        <th style="width:110px;text-align:center"><?= t('ki_kennzeichnen') ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($befehle as $code => $b): ?>
        <?php
          $frei    = !in_array($code, (array) ($cfg['gesperrt'] ?? []), true);
          $marke   = ki_kennzeichnet($code, $cfg);
          $eigenes = (string) (($cfg['modelle'] ?? [])[$code] ?? '');
          $urteil  = ki_braucht_urteil($code);
        ?>
        <tr>
          <td>
            <strong><?= e(t($b['name'])) ?></strong>
            <div style="font-size:12px;color:var(--text-muted)"><?= e(t($b['hinweis'])) ?></div>
          </td>
          <td>
            <input type="text" name="modell_je[<?= e($code) ?>]" value="<?= e($eigenes) ?>"
                   placeholder="wie oben" style="width:100%;font-size:12px">
            <?php if ($urteil): ?>
              <div style="font-size:11px;color:var(--color-warning);margin-top:3px">
                entscheidet mit, was wegfällt
              </div>
            <?php endif; ?>
          </td>
          <td style="text-align:center">
            <input type="checkbox" name="frei[<?= e($code) ?>]" value="1" <?= $frei ? 'checked' : '' ?>>
          </td>
          <td style="text-align:center">
            <input type="checkbox" name="marke[<?= e($code) ?>]" value="1" <?= $marke ? 'checked' : '' ?>>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <div class="form-hint" style="margin-bottom:8px">
    <strong><?= t('ki_modell_je_befehl') ?></strong> leer heißt „das oben eingetragene". Sinnvoll ist
    der Unterschied dort, wo <em>entscheidet mit, was wegfällt</em> steht — beim Kürzen
    und Zusammenfassen wählt das Modell aus, was im Beitrag bleibt. Bei Rechtschreibung
    und Übersetzen macht ein größeres Modell messbar keinen Unterschied, kostet aber
    ein Vielfaches.
  </div>

  <div class="section-heading" style="margin-top:24px"><?= t('ki_wer_darf') ?></div>
  <div class="form-row">
    <?php if (!$gruppen): ?>
      <div class="form-hint"><?= t('ki_es_gibt_noch_keine') ?></div>
    <?php else: ?>
      <div style="display:flex;flex-wrap:wrap;gap:14px">
        <?php foreach ($gruppen as $g): ?>
          <label style="display:flex;align-items:center;gap:6px">
            <input type="checkbox" name="gruppen[]" value="<?= (int) $g['group_id'] ?>"
                   <?= in_array((int) $g['group_id'], array_map('intval', (array) ($cfg['gruppen'] ?? [])), true) ? 'checked' : '' ?>>
            <?= e($g['name']) ?>
          </label>
        <?php endforeach; ?>
      </div>
      <div class="form-hint">
        <?= t('ki_ohne_haken_duerfen_nur') ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="section-heading" style="margin-top:24px"><?= t('ki_bremsen') ?></div>
  <div class="form-row">
    <label><?= t('ki_hoechstens_zeichen_je_anfrage') ?></label>
    <input type="number" name="max_zeichen" min="500" max="40000"
           value="<?= (int) ($cfg['max_zeichen'] ?? 8000) ?>">
    <div class="form-hint"><?= t('ki_laengere_texte_weist_der') ?></div>
  </div>
  <div class="form-row">
    <label><?= t('ki_anfragen_je_stunde_und') ?></label>
    <input type="number" name="pro_stunde" min="0" max="500"
           value="<?= (int) ($cfg['pro_stunde'] ?? 30) ?>">
    <div class="form-hint"><?= t('ki_0_schaltet_die_bremse') ?></div>
  </div>

  <div class="section-heading" style="margin-top:24px"><?= t('ki_beitraege_vorsortieren') ?></div>
  <div class="form-hint" style="margin-bottom:10px">
    <?= t('ki_neue_beitraege_werden_nach') ?>
  </div>

  <div class="form-row">
    <label>
      <input type="checkbox" name="mod_an" value="1" <?= !empty($cfg['mod_an']) ? 'checked' : '' ?>>
      Vorsortierung einschalten
    </label>
    <div class="form-hint">
      <?= t('ki_im_auslieferungszustand_aus_sobald') ?>
    </div>
  </div>

  <div class="form-row">
    <label><?= t('ki_meldendes_konto') ?></label>
    <input type="text" name="mod_melder" list="ki-melder-vorschlaege" autocomplete="off"
           value="<?= e($melder_name) ?>" placeholder="Benutzername">
    <datalist id="ki-melder-vorschlaege">
      <?php foreach ($melder_wahl as $m): ?>
        <option value="<?= e($m['username']) ?>"></option>
      <?php endforeach; ?>
    </datalist>
    <div class="form-hint">
      <?= t('ki_unter_diesem_namen_erscheinen') ?>
    </div>
  </div>

  <div class="form-row">
    <label><?= t('ki_nur_bis_zu_dieser') ?></label>
    <input type="number" name="mod_bis" min="0" max="10000"
           value="<?= (int) ($cfg['mod_bis'] ?? 20) ?>">
    <div class="form-hint">
      <?= t('ki_wer_schon_laenger_dabei') ?>
    </div>
  </div>

  <div class="form-row">
    <label><?= t('ki_modell_fuer_die_vorpruefung') ?></label>
    <input type="text" name="mod_modell" value="<?= e((string) ($cfg['mod_modell'] ?? '')) ?>"
           placeholder="wie oben">
    <div class="form-hint">
      <?= t('ki_hier_reicht_das_kleinste') ?>
    </div>
  </div>

  <?php if (!empty($mod_offen) || !empty($mod_befunde)): ?>
    <div class="form-hint" style="margin-bottom:14px">
      <?php if (!empty($mod_offen)): ?>
        <strong><?= (int) $mod_offen ?></strong> <?= t('ki_beitraege_warten_noch_auf') ?>
      <?php endif; ?>
      <?php if (!empty($mod_befunde)): ?>
        <?= t('ki_sieben_tage') ?>
        <?php foreach ($mod_befunde as $b): ?>
          <?= e((string) $b['befund']) ?> <strong><?= (int) $b['anzahl'] ?></strong><?= $b === end($mod_befunde) ? '' : ' ·' ?>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <div class="form-actions" style="margin-top:20px;display:flex;gap:10px;flex-wrap:wrap">
    <button type="submit" class="btn btn-primary"><?= t('ki_speichern') ?></button>
    <button type="submit" name="probe" value="1" class="btn"><?= t('ki_speichern_und_verbindung_pruefen') ?></button>
  </div>
</form>

<?php if ($gesamt > 0): ?>
  <div class="section-heading" style="margin-top:28px"><?= t('ki_die_letzten_sieben_tage') ?></div>
  <table class="forum-table">
    <thead>
      <tr><th><?= t('ki_befehl') ?></th><th style="width:110px;text-align:right"><?= t('ki_anfragen') ?></th><th style="width:140px;text-align:right"><?= t('ki_zeichen_gesendet') ?></th></tr>
    </thead>
    <tbody>
      <?php foreach ($verbrauch as $v): ?>
        <tr>
          <td><?= e($befehle[$v['befehl']]['name'] ?? $v['befehl']) ?></td>
          <td style="text-align:right"><?= number_format((int) $v['anzahl'], 0, ',', '.') ?></td>
          <td style="text-align:right"><?= number_format((int) $v['zeichen'], 0, ',', '.') ?></td>
        </tr>
      <?php endforeach; ?>
      <tr>
        <td><strong><?= t('ki_zusammen') ?></strong></td>
        <td style="text-align:right"><strong><?= number_format($gesamt, 0, ',', '.') ?></strong></td>
        <td></td>
      </tr>
    </tbody>
  </table>
  <div class="form-hint" style="margin-top:8px">
    <?= t('ki_gezaehlt_werden_anfragen_nicht') ?>
  </div>
<?php endif; ?>
