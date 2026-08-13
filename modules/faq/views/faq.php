<?php if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); } ?>
<?php
  /** @var array $categories; @var array $byCategory; @var bool $canAdmin; @var bool $adminMode */
?>

<div class="section-heading" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
  <span>❓ Häufig gestellte Fragen</span>
  <?php if ($canAdmin): ?>
    <?php if ($adminMode): ?>
      <a href="<?= e(FORUM_URL) ?>/modules/faq/faq.php" class="btn btn-ghost btn-sm">👁️ Normale Ansicht</a>
    <?php else: ?>
      <a href="<?= e(FORUM_URL) ?>/modules/faq/faq.php?admin=1" class="btn btn-primary btn-sm">⚙️ Verwalten</a>
    <?php endif; ?>
  <?php endif; ?>
</div>

<?php if (empty($categories)): ?>
  <div class="card" style="padding:40px;text-align:center">
    <div style="font-size:48px;margin-bottom:12px">🤷</div>
    <strong>Noch keine FAQs vorhanden</strong>
    <?php if ($canAdmin): ?>
      <p style="font-size:13px;color:var(--text-muted);margin-top:8px">
        <a href="?admin=1">Klick hier um die erste Frage anzulegen</a>
      </p>
    <?php endif; ?>
  </div>
<?php else: ?>

  <?php /* ─── KATEGORIE-NAV (Quick-Jump) ────────────────────────── */ ?>
  <div class="faq-cat-nav">
    <?php foreach ($categories as $c): ?>
      <a href="#faq-cat-<?= (int) $c['cat_id'] ?>"><?= $c['icon'] ?> <?= e($c['title']) ?></a>
    <?php endforeach; ?>
  </div>

  <?php /* ─── KATEGORIEN MIT FRAGEN ────────────────────────────── */ ?>
  <?php foreach ($categories as $c):
    $items = $byCategory[(int) $c['cat_id']] ?? [];
  ?>
    <div class="faq-category" id="faq-cat-<?= (int) $c['cat_id'] ?>">
      <h2 class="faq-cat-title"><?= $c['icon'] ?> <?= e($c['title']) ?>
        <?php if ($adminMode): ?>
          <form method="post" style="display:inline-block;margin-left:auto"
                onsubmit="return confirm('Kategorie löschen? (nur wenn leer)')">
            <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="faq_act" value="delete_cat">
            <input type="hidden" name="cat_id" value="<?= (int) $c['cat_id'] ?>">
            <button type="submit" class="btn btn-ghost btn-sm" style="font-size:10px">🗑️</button>
          </form>
        <?php endif; ?>
      </h2>

      <?php if (empty($items)): ?>
        <div class="faq-empty">Keine Fragen in dieser Kategorie.</div>
      <?php else: ?>
        <div class="faq-list">
          <?php $idx = 0; $totalItems = count($items); ?>
          <?php foreach ($items as $item): $idx++; ?>
            <details class="faq-item <?= empty($item['visible']) ? 'faq-hidden' : '' ?>">
              <summary class="faq-question">
                <span class="faq-q-text"><?= e($item['question']) ?></span>
                <?php if ($adminMode && empty($item['visible'])): ?>
                  <span class="faq-status-badge">unsichtbar</span>
                <?php endif; ?>
                <span class="faq-chevron">▾</span>
              </summary>
              <div class="faq-answer-wrap">
                <div class="faq-answer"><?= bbcode($item['answer']) ?></div>

                <?php if ($adminMode): ?>
                  <details class="faq-admin-edit">
                    <summary>✏️ Bearbeiten</summary>
                    <form method="post" class="faq-edit-form">
                      <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
                      <input type="hidden" name="faq_act" value="edit_item">
                      <input type="hidden" name="faq_id" value="<?= (int) $item['faq_id'] ?>">
                      <label>Kategorie
                        <select name="cat_id">
                          <?php foreach ($categories as $c2): ?>
                            <option value="<?= (int) $c2['cat_id'] ?>"
                              <?= (int) $c2['cat_id'] === (int) $item['cat_id'] ? 'selected' : '' ?>>
                              <?= $c2['icon'] ?> <?= e($c2['title']) ?>
                            </option>
                          <?php endforeach; ?>
                        </select>
                      </label>
                      <label>Frage
                        <input type="text" name="question" value="<?= e($item['question']) ?>" required>
                      </label>
                      <label>Antwort (BBCode erlaubt)
                        <textarea name="answer" rows="5" required><?= e($item['answer']) ?></textarea>
                      </label>
                      <label class="checkbox-line">
                        <input type="checkbox" name="visible" value="1" <?= !empty($item['visible']) ? 'checked' : '' ?>>
                        <span>Für alle sichtbar</span>
                      </label>
                      <div class="faq-edit-actions">
                        <button type="submit" class="btn btn-primary btn-sm">💾 Speichern</button>
                      </div>
                    </form>

                    <div class="faq-edit-actions" style="margin-top:8px;padding-top:8px;border-top:1px dashed var(--border)">
                      <?php if ($idx > 1): ?>
                        <form method="post" style="display:inline">
                          <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
                          <input type="hidden" name="faq_act" value="move_item">
                          <input type="hidden" name="faq_id" value="<?= (int) $item['faq_id'] ?>">
                          <input type="hidden" name="dir" value="up">
                          <button class="btn btn-ghost btn-sm">↑ Hoch</button>
                        </form>
                      <?php endif; ?>
                      <?php if ($idx < $totalItems): ?>
                        <form method="post" style="display:inline">
                          <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
                          <input type="hidden" name="faq_act" value="move_item">
                          <input type="hidden" name="faq_id" value="<?= (int) $item['faq_id'] ?>">
                          <input type="hidden" name="dir" value="down">
                          <button class="btn btn-ghost btn-sm">↓ Runter</button>
                        </form>
                      <?php endif; ?>
                      <form method="post" style="display:inline;margin-left:auto"
                            onsubmit="return confirm('Frage wirklich löschen?')">
                        <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
                        <input type="hidden" name="faq_act" value="delete_item">
                        <input type="hidden" name="faq_id" value="<?= (int) $item['faq_id'] ?>">
                        <button class="btn btn-danger btn-sm">🗑️ Löschen</button>
                      </form>
                    </div>
                  </details>
                <?php endif; ?>
              </div>
            </details>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>

  <?php /* ─── ADMIN: NEUE FRAGE ANLEGEN ──────────────────────── */ ?>
  <?php if ($adminMode): ?>
    <div class="card" style="margin-top:20px;padding:20px">
      <h3 style="margin-bottom:12px">➕ Neue Frage hinzufügen</h3>
      <form method="post" class="faq-edit-form">
        <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
        <input type="hidden" name="faq_act" value="add_item">
        <label>Kategorie
          <select name="cat_id" required>
            <?php foreach ($categories as $c): ?>
              <option value="<?= (int) $c['cat_id'] ?>"><?= $c['icon'] ?> <?= e($c['title']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Frage
          <input type="text" name="question" required placeholder="Wie funktioniert ...?">
        </label>
        <label>Antwort (BBCode erlaubt)
          <textarea name="answer" rows="5" required placeholder="Hier die Antwort..."></textarea>
        </label>
        <button type="submit" class="btn btn-primary btn-sm">➕ Hinzufügen</button>
      </form>
    </div>

    <div class="card" style="margin-top:14px;padding:20px">
      <h3 style="margin-bottom:12px">📂 Neue Kategorie</h3>
      <form method="post" class="faq-edit-form" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">
        <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
        <input type="hidden" name="faq_act" value="add_cat">
        <label style="flex:0 0 80px">Icon
          <input type="text" name="icon" value="📂" maxlength="4">
        </label>
        <label style="flex:1;min-width:160px">Name
          <input type="text" name="title" required placeholder="z.B. Zahlung">
        </label>
        <button type="submit" class="btn btn-primary btn-sm">➕ Anlegen</button>
      </form>
    </div>
  <?php endif; ?>
<?php endif; ?>
