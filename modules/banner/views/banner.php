<?php if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); } ?>
<?php /** @var string $text; @var string $art; @var bool $aktiv; @var array $arten */ ?>

<div class="section-heading">📣 Ankündigungsband</div>

<form method="post" class="form-card">
  <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">

  <div class="form-row">
    <label>
      <input type="checkbox" name="aktiv" value="1" <?= $aktiv ? 'checked' : '' ?>>
      Band anzeigen
    </label>
    <div class="form-hint">Ohne Haken bleibt es aus, der Text bleibt aber gespeichert.</div>
  </div>

  <div class="form-row">
    <label>Text</label>
    <textarea name="text" maxlength="300" style="min-height:70px"
              placeholder="Am Samstag ab 20 Uhr ist das Forum kurz nicht erreichbar."><?= e($text) ?></textarea>
    <div class="form-hint">Nur Text, kein HTML. Höchstens 300 Zeichen.</div>
  </div>

  <div class="form-row">
    <label>Art</label>
    <select name="art">
      <?php foreach ($arten as $wert => $label): ?>
        <option value="<?= e($wert) ?>" <?= $art === $wert ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
    <div class="form-hint">Bestimmt nur die Farbe.</div>
  </div>

  <button class="btn btn-primary">Speichern</button>
</form>

<div class="card" style="margin-top:16px">
  <strong>Wie es sich verhält</strong>
  <ul style="margin:8px 0 0;padding-left:18px;color:var(--text-muted);font-size:14px;line-height:1.8">
    <li>Das Band steht über allem, auf jeder Seite.</li>
    <li>Wer es wegklickt, sieht es nicht wieder — bis der Text sich ändert.</li>
    <li>Das merkt sich der Browser des Besuchers, nicht das Forum.</li>
  </ul>
</div>
