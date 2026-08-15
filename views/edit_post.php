<?php if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); } ?>
<?php /** @var array $post; @var string $error; @var bool $isModEdit */ ?>
<div class="section-heading">✏️ Beitrag bearbeiten</div>
<div class="form-card">
    <?php if (!empty($error)): ?><div class="flash flash-error">❌ <?= e($error) ?></div><?php endif; ?>

    <?php if (!empty($isModEdit)): ?>
      <div class="flash" style="background:rgba(220,38,38,.12);border:1px solid var(--color-danger);color:var(--color-danger);margin-bottom:16px">
        ⚠️ Du bearbeitest den Beitrag eines anderen Benutzers.
        Der Grund ist Pflicht und wird sichtbar unter dem Beitrag angezeigt.
      </div>
    <?php endif; ?>

    <form method="post">
      <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
      <div class="form-row">
        <?php
          $editorValue = $post['content'];
          $editorPlaceholder = 'Beitrag bearbeiten…';
          include __DIR__ . '/partials/editor.php';
        ?>
      </div>

      <?php if (!empty($tagsEditable) && !empty($availableTags)): ?>
      <div class="form-row">
        <label>🏷️ Tags (max. 5)</label>
        <div style="display:flex;flex-wrap:wrap;gap:6px">
          <?php foreach ($availableTags as $tg): ?>
            <label class="tag-checkbox" style="border-color:<?= e($tg['color']) ?>">
              <input type="checkbox" name="tags[]" value="<?= (int) $tg['tag_id'] ?>" style="margin:0"
                     <?= !empty($tg['checked']) ? 'checked' : '' ?>>
              <span style="color:<?= e($tg['color']) ?>"><?= e($tg['description'] ?? '') ?> <?= e($tg['name']) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
        <div class="form-hint">Gelten für das ganze Thema, nicht für diesen Beitrag.</div>
      </div>
      <?php endif; ?>

      <div class="form-row">
        <label>
          Bearbeitungsgrund
          <?php if (!empty($isModEdit)): ?>
            <span style="color:var(--color-danger)">(Pflicht)</span>
          <?php else: ?>
            (optional)
          <?php endif; ?>
        </label>
        <input type="text" name="edit_reason" maxlength="500"
               placeholder="<?= !empty($isModEdit) ? 'z.B. Beleidigender Inhalt entfernt, Bildgröße reduziert' : 'z.B. Tippfehler korrigiert' ?>"
               <?= !empty($isModEdit) ? 'required minlength="3"' : '' ?>>
        <div class="form-hint">
          <?php if (!empty($isModEdit)): ?>
            🔴 Der Grund erscheint rot unter dem Beitrag und ist für alle sichtbar.
          <?php else: ?>
            Sichtbar für Moderatoren im Edit-Verlauf.
          <?php endif; ?>
        </div>
      </div>
      <div style="display:flex;gap:8px">
        <button type="submit" class="btn btn-primary">Speichern</button>
        <a href="<?= e(FORUM_URL) ?>/thread.php?id=<?= (int) $post['thread_id'] ?>#post<?= (int) $post['post_id'] ?>"
           class="btn btn-ghost">Abbrechen</a>
      </div>
    </form>
</div>
