<?php if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); } ?>
<?php
  /** @var string $query; @var int $boardId; @var string $author; @var string $dateFrom; @var string $dateTo;
   *  @var string $sortBy; @var bool $hasReactions; @var bool $hasAttachments;
   *  @var array $results; @var int $total; @var array $boards; @var string $paginationHtml;
   *  @var bool $hasQuery; @var array $savedSearches */
  $isFiltered = $boardId || $author || $dateFrom || $dateTo || $hasReactions || $hasAttachments || $sortBy !== 'date_desc';
?>

<div class="section-heading">🔍 <?= e(t('search')) ?></div>

<?php if ($currentUser && !empty($savedSearches)): ?>
  <div class="saved-searches-bar">
    <div class="saved-searches-label">⭐ Deine gespeicherten Suchen:</div>
    <div class="saved-searches-items">
      <?php foreach ($savedSearches as $s): ?>
        <span class="saved-search-chip">
          <a href="?saved=load&id=<?= (int) $s['search_id'] ?>"><?= e($s['name']) ?></a>
          <a href="?saved=delete&id=<?= (int) $s['search_id'] ?>&csrf=<?= e($csrfToken) ?>"
             class="saved-search-x"
             title="Löschen"
             onclick="return confirm('Diese gespeicherte Suche löschen?')">✕</a>
        </span>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<form method="get" action="<?= e(FORUM_URL) ?>/search.php" class="form-card search-form">
  <div class="search-main-row">
    <input type="text" name="q" value="<?= e($query) ?>" placeholder="🔍 Suchbegriff (min. 3 Zeichen)…" autofocus>
    <button type="submit" class="btn btn-primary">Suchen</button>
    <button type="button" class="btn btn-ghost" id="toggle-filters">
      <span id="filter-toggle-label">⚙️ Filter <?= $isFiltered ? '(aktiv)' : '' ?></span>
    </button>
  </div>

  <div class="search-filters <?= $isFiltered ? 'open' : '' ?>" id="search-filters">
    <div class="search-filter-grid">
      <div class="form-row">
        <label>📋 Forum</label>
        <select name="board">
          <option value="0">– alle Foren –</option>
          <?php foreach ($boards as $b): ?>
            <option value="<?= (int) $b['board_id'] ?>" <?= $boardId === (int) $b['board_id'] ? 'selected' : '' ?>>
              <?= e($b['cat']) ?> › <?= e($b['title']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-row">
        <label>👤 Autor (Benutzername)</label>
        <input type="text" name="author_name" value="<?= e($author) ?>" placeholder="z.B. Aynku">
      </div>

      <div class="form-row">
        <label>📅 Datum von</label>
        <input type="date" name="date_from" value="<?= e($dateFrom) ?>">
      </div>

      <div class="form-row">
        <label>📅 Datum bis</label>
        <input type="date" name="date_to" value="<?= e($dateTo) ?>">
      </div>

      <div class="form-row">
        <label>🔀 Sortierung</label>
        <select name="sort">
          <option value="date_desc" <?= $sortBy === 'date_desc' ? 'selected' : '' ?>>Neueste zuerst</option>
          <option value="date_asc"  <?= $sortBy === 'date_asc'  ? 'selected' : '' ?>>Älteste zuerst</option>
          <option value="reactions" <?= $sortBy === 'reactions' ? 'selected' : '' ?>>Meiste Reaktionen</option>
          <option value="replies"   <?= $sortBy === 'replies'   ? 'selected' : '' ?>>Meiste Antworten</option>
          <option value="views"     <?= $sortBy === 'views'     ? 'selected' : '' ?>>Meiste Aufrufe</option>
        </select>
      </div>

      <div class="form-row search-filter-checkboxes">
        <label>🎯 Erweitert</label>
        <label class="search-chk">
          <input type="checkbox" name="has_reactions" value="1" <?= $hasReactions ? 'checked' : '' ?>>
          ⭐ Mit Reaktionen
        </label>
        <label class="search-chk">
          <input type="checkbox" name="has_attachments" value="1" <?= $hasAttachments ? 'checked' : '' ?>>
          📎 Mit Anhängen
        </label>
      </div>
    </div>

    <?php if ($currentUser && $hasQuery): ?>
      <div class="search-save-row">
        <input type="text" id="save_name" placeholder="Name für diese Suche, z.B. „Aynkus News"" maxlength="100" style="max-width:280px">
        <button type="button" class="btn btn-ghost btn-sm" id="save-search-btn">⭐ Diese Suche speichern</button>
      </div>
    <?php endif; ?>
  </div>
</form>

<?php if ($hasQuery): ?>
  <?php if (empty($results)): ?>
    <div class="card" style="padding:30px;text-align:center;color:var(--text-faint)">
      Keine Treffer. Versuche andere Suchbegriffe oder weniger Filter.
    </div>
  <?php else: ?>
    <div style="margin-bottom:8px;color:var(--text-muted);font-size:13px">
      <?= (int) $total ?> Treffer
    </div>
    <div class="card" style="padding:6px">
      <?php foreach ($results as $r): ?>
        <a href="<?= e(FORUM_URL) ?>/thread.php?id=<?= (int) $r['thread_id'] ?>#post<?= (int) $r['post_id'] ?>"
           class="search-result">
          <div class="search-result-meta">
            <?= e($r['cat_title']) ?> › <?= e($r['board_title']) ?>
            · von <strong><?= e($r['author']) ?></strong>
            · <?= e(format_date($r['post_date'])) ?>
          </div>
          <div class="search-result-title"><?= e($r['thread_title']) ?></div>
          <div class="search-result-snippet"><?= $r['snippet'] ?>…</div>
          <div class="search-result-stats">
            💬 <?= (int) $r['reply_count'] ?>
            <?php if ((int) $r['reaction_count'] > 0): ?> · ⭐ <?= (int) $r['reaction_count'] ?><?php endif; ?>
            <?php if ((int) $r['attachment_count'] > 0): ?> · 📎 <?= (int) $r['attachment_count'] ?><?php endif; ?>
            · 👁 <?= (int) $r['view_count'] ?>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
    <?= $paginationHtml ?>
  <?php endif; ?>
<?php endif; ?>

<?php /* Hidden Save-Form, durch JS getriggert */ ?>
<form method="post" action="?saved=save" id="save-search-form" style="display:none">
  <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
  <input type="hidden" name="save_name" id="save_name_hidden">
  <input type="hidden" name="q" value="<?= e($query) ?>">
  <input type="hidden" name="board" value="<?= (int) $boardId ?>">
  <input type="hidden" name="author_name" value="<?= e($author) ?>">
  <input type="hidden" name="date_from" value="<?= e($dateFrom) ?>">
  <input type="hidden" name="date_to" value="<?= e($dateTo) ?>">
  <input type="hidden" name="sort" value="<?= e($sortBy) ?>">
  <?php if ($hasReactions): ?><input type="hidden" name="has_reactions" value="1"><?php endif; ?>
  <?php if ($hasAttachments): ?><input type="hidden" name="has_attachments" value="1"><?php endif; ?>
</form>

<script>
(function() {
  const toggle = document.getElementById('toggle-filters');
  const filters = document.getElementById('search-filters');
  toggle?.addEventListener('click', () => filters.classList.toggle('open'));

  const saveBtn = document.getElementById('save-search-btn');
  const saveInput = document.getElementById('save_name');
  saveBtn?.addEventListener('click', function() {
    const name = (saveInput?.value || '').trim();
    if (!name) {
      saveInput?.focus();
      alert('Bitte einen Namen für die Suche eingeben.');
      return;
    }
    document.getElementById('save_name_hidden').value = name;
    document.getElementById('save-search-form').submit();
  });
})();
</script>
