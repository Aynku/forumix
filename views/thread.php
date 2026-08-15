<?php if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); } ?>
<?php /** @var array $thread; @var array $posts; @var array $pag; @var string $pagination; @var ?array $poll; @var bool $hasVoted; @var bool $isSubscribed */ ?>

<?php $canModThis = $isAdmin || ($currentUser && is_mod_for_board((int) $currentUser['user_id'], (int) $thread['board_id'])); ?>

<div class="section-heading">
  <span>
    <?php if ($thread['is_sticky']): ?><span class="badge badge-sticky">Angepinnt</span><?php endif; ?>
    <?php if ($thread['is_closed']): ?><span class="badge badge-closed">Geschlossen</span><?php endif; ?>
    <?= e($thread['title']) ?>
  </span>
  <span style="display:inline-flex;gap:6px;align-items:center">
    <?php if ($canModThis): ?>
      <div class="action-dropdown">
        <button type="button" class="action-dropdown-toggle btn btn-ghost btn-sm">🛡️ Mod-Tools ▾</button>
        <div class="action-dropdown-menu" style="left:auto;right:0">
          <?php if (!$thread['is_sticky']): ?>
            <a href="<?= e(FORUM_URL) ?>/mod_action.php?action=sticky&thread=<?= (int) $thread['thread_id'] ?>&csrf=<?= e($csrfToken) ?>">📌 Anpinnen</a>
          <?php else: ?>
            <a href="<?= e(FORUM_URL) ?>/mod_action.php?action=unsticky&thread=<?= (int) $thread['thread_id'] ?>&csrf=<?= e($csrfToken) ?>">📌 Loslösen</a>
          <?php endif; ?>
          <?php if (!$thread['is_closed']): ?>
            <a href="<?= e(FORUM_URL) ?>/mod_action.php?action=close&thread=<?= (int) $thread['thread_id'] ?>&csrf=<?= e($csrfToken) ?>">🔒 Schließen</a>
          <?php else: ?>
            <a href="<?= e(FORUM_URL) ?>/mod_action.php?action=open&thread=<?= (int) $thread['thread_id'] ?>&csrf=<?= e($csrfToken) ?>">🔓 Öffnen</a>
          <?php endif; ?>
          <div class="action-dropdown-divider"></div>
          <a href="<?= e(FORUM_URL) ?>/mod_action.php?action=delete_thread&thread=<?= (int) $thread['thread_id'] ?>&csrf=<?= e($csrfToken) ?>"
             class="action-danger"
             onclick="return confirm('Thread wirklich löschen?')">🗑️ Thread löschen</a>
        </div>
      </div>
    <?php endif; ?>
    <?php if ($currentUser): ?>
      <button class="btn btn-ghost btn-sm" id="bookmark-toggle"
              data-thread="<?= (int) $thread['thread_id'] ?>"
              data-bookmarked="<?= $isBookmarked ? '1' : '0' ?>"
              type="button">
        <span id="bookmark-icon"><?= $isBookmarked ? '🔖' : '📑' ?></span>
        <span id="bookmark-label"><?= $isBookmarked ? 'Gespeichert' : 'Lesezeichen' ?></span>
      </button>
      <?php if ($isSubscribed): ?>
        <a href="<?= e(FORUM_URL) ?>/subscribe.php?thread=<?= (int) $thread['thread_id'] ?>&action=unsubscribe&csrf=<?= e($csrfToken) ?>"
           class="btn btn-ghost btn-sm">🔔 <?= e(t('subscribed')) ?></a>
      <?php else: ?>
        <a href="<?= e(FORUM_URL) ?>/subscribe.php?thread=<?= (int) $thread['thread_id'] ?>&action=subscribe&csrf=<?= e($csrfToken) ?>"
           class="btn btn-ghost btn-sm">🔕 <?= e(t('subscribe')) ?></a>
      <?php endif; ?>
    <?php endif; ?>
  </span>
</div>

<?php if (!empty($threadTags)): ?>
<div style="margin-bottom:14px;display:flex;flex-wrap:wrap;gap:6px">
  <?php foreach ($threadTags as $tg): ?>
    <?= render_tag_chip($tg) ?>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php /* ── UMFRAGE ── */ ?>
<?php if ($poll): ?>
<div class="card" style="margin-bottom:16px;padding:18px 22px">
  <h3 style="font-size:16px;margin-bottom:14px">📊 <?= e($poll['question']) ?></h3>
  <?php if ($currentUser && !$hasVoted && (!$poll['expires_at'] || strtotime($poll['expires_at']) > time())): ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
      <input type="hidden" name="poll_vote" value="1">
      <input type="hidden" name="poll_id" value="<?= (int) $poll['poll_id'] ?>">
      <?php foreach ($poll['options'] as $opt): ?>
        <label style="display:block;padding:8px 12px;background:var(--bg-surface2);border:1px solid var(--border);border-radius:6px;margin-bottom:6px;cursor:pointer">
          <input type="<?= $poll['multi_choice'] ? 'checkbox' : 'radio' ?>"
                 name="option<?= $poll['multi_choice'] ? '[]' : '' ?>"
                 value="<?= (int) $opt['option_id'] ?>"
                 style="margin-right:8px">
          <?= e($opt['text']) ?>
        </label>
      <?php endforeach; ?>
      <button type="submit" class="btn btn-primary btn-sm" style="margin-top:8px">🗳️ <?= e(t('vote')) ?></button>
    </form>
  <?php else: ?>
    <?php foreach ($poll['options'] as $opt):
      $pct = $poll['total_votes'] > 0 ? round((int) $opt['vote_count'] / $poll['total_votes'] * 100) : 0;
    ?>
    <div style="margin-bottom:8px">
      <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:3px">
        <span><?= e($opt['text']) ?></span>
        <span style="color:var(--text-muted);font-size:12px">
          <?= (int) $opt['vote_count'] ?> <?= e(t('votes')) ?> (<?= $pct ?>%)
        </span>
      </div>
      <div style="background:var(--bg-surface2);border-radius:4px;height:8px;overflow:hidden">
        <div style="background:var(--text-base);height:100%;width:<?= $pct ?>%"></div>
      </div>
    </div>
    <?php endforeach; ?>
    <p style="margin-top:10px;font-size:11px;color:var(--text-faint)">
      <?= (int) $poll['total_votes'] ?> <?= e(t('votes')) ?> insgesamt
    </p>
  <?php endif; ?>
</div>
<?php endif; ?>

<?= $pagination ?>

<?= $threadTopHtml ?? '' ?>

<?php foreach ($posts as $i => $post): ?>
<div class="post-card<?= empty($post['is_approved']) ? ' post-pending' : '' ?>" id="post<?= (int) $post['post_id'] ?>">
  <?php if (empty($post['is_approved'])): ?>
    <div class="post-pending-bar">⏳ Dieser Beitrag wartet auf Freigabe</div>
  <?php endif; ?>
  <div class="post-header">
    <span>
      <strong>#<?= $pag['offset'] + $i + 1 ?></strong>
    </span>
    <span style="color:var(--text-faint)"><?= e(format_date($post['created_at'])) ?></span>
  </div>
  <div class="post-body">
    <div class="post-user-col">
      <img class="post-avatar zoomable-avatar" src="<?= e(avatar_url($post)) ?>" alt="<?= e($post['username']) ?>"
           data-username="<?= e($post['username']) ?>"
           data-profile-url="<?= e(FORUM_URL) ?>/profile.php?id=<?= (int) $post['user_id'] ?>">
      <div class="post-username">
        <?= online_dot($post) ?>
        <a href="<?= e(FORUM_URL) ?>/profile.php?id=<?= (int) $post['user_id'] ?>"><?= e($post['username']) ?></a>
      </div>
      <?php if (!empty($post['is_owner'])): ?>
        <div class="post-admin-badge" style="background:#7c3aed !important">👑 Owner</div>
      <?php elseif ($post['is_banned']): ?>
        <div class="post-banned-badge">Gesperrt</div>
      <?php elseif ($post['is_admin']): ?>
        <div class="post-admin-badge">Admin</div>
      <?php elseif ($post['is_mod']): ?>
        <div class="post-mod-badge">Mod</div>
      <?php else: ?>
        <?= rank_badge($post['rank'], (int) $post['post_count']) ?>
      <?php endif; ?>
      <div class="post-count-label">Beiträge: <?= (int) $post['post_count'] ?></div>
    </div>
    <div class="post-content-col">
      <div class="post-text"><?= bbcode($post['content']) ?></div>
      <script type="text/plain" class="post-raw" data-post-id="<?= (int) $post['post_id'] ?>"><?= htmlspecialchars($post['content'], ENT_QUOTES, 'UTF-8') ?></script>

      <?php /* ── Anhänge ── */ ?>
      <?php if (!empty($post['attachments'])): ?>
      <div style="margin-top:14px;padding-top:10px;border-top:1px dashed var(--border)">
        <strong style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px">
          📎 <?= e(t('attachments')) ?> (<?= count($post['attachments']) ?>)
        </strong>
        <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:6px">
          <?php foreach ($post['attachments'] as $att): ?>
            <?php if ($att['is_image']): ?>
              <a href="<?= e(attachment_url($att['filename'])) ?>" target="_blank" style="display:block">
                <img src="<?= e(attachment_url($att['filename'])) ?>"
                     style="max-width:200px;max-height:200px;border-radius:6px;border:1px solid var(--border)"
                     alt="<?= e($att['original_name']) ?>">
              </a>
            <?php else: ?>
              <a href="<?= e(attachment_url($att['filename'])) ?>" target="_blank"
                 style="display:inline-flex;align-items:center;gap:8px;padding:6px 12px;background:var(--bg-surface2);border:1px solid var(--border);border-radius:6px;font-size:12px;color:var(--text-base);text-decoration:none">
                📎 <?= e($att['original_name']) ?>
                <span style="color:var(--text-faint)">(<?= e(format_filesize((int) $att['size_bytes'])) ?>)</span>
              </a>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <?php if (!empty($post['signature'])): ?>
        <div class="post-signature">– <?= bbcode($post['signature']) ?></div>
      <?php endif; ?>
      <?php if (!empty($post['edited_at'])): ?>
        <div style="font-size:11px;color:var(--text-faint);margin-top:8px">
          Bearbeitet: <?= e(format_date($post['edited_at'])) ?>
        </div>
      <?php endif; ?>

      <?php /* ── Mod-Edit-Marker ── */ ?>
      <?php if (!empty($post['mod_edited_by']) && !empty($post['mod_editor_name'])): ?>
        <div class="mod-edit-notice">
          <strong>✏️ Bearbeitet von Moderator <?= e($post['mod_editor_name']) ?></strong>
          <?php if (!empty($post['mod_edited_at'])): ?>
            <span class="mod-edit-date">am <?= e(format_date($post['mod_edited_at'])) ?></span>
          <?php endif; ?>
          <?php if (!empty($post['mod_edit_reason'])): ?>
            <div class="mod-edit-reason">Grund: <?= e($post['mod_edit_reason']) ?></div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <?php /* ── Reaktionen ── */ ?>
      <div class="reactions-bar" data-post-id="<?= (int) $post['post_id'] ?>">
        <?php foreach ($post['reactions'] as $r):
          $info = reaction_types()[$r['type']] ?? null;
          if (!$info) continue;
        ?>
          <button type="button" class="reaction-pill <?= $r['by_me'] ? 'by-me' : '' ?>"
                  data-type="<?= e($r['type']) ?>" title="<?= e($info[1]) ?>">
            <span class="reaction-emoji"><?= $info[0] ?></span>
            <span class="reaction-count"><?= (int) $r['count'] ?></span>
          </button>
        <?php endforeach; ?>
        <?php if ($currentUser): ?>
          <button type="button" class="reaction-add" data-post-id="<?= (int) $post['post_id'] ?>" title="Reagieren">
            <span style="font-size:14px">＋</span>
          </button>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="post-footer">
    <?php if ($canModThis && !empty($post['author_ip']) && (string) get_setting('show_ips_to_mods', '1') === '1'): ?>
      <a class="post-action post-ip"
         href="<?= e(FORUM_URL) ?>/admin/?action=iplookup&ip=<?= e(urlencode($post['author_ip'])) ?>"
         title="IP-Lookup">
        🌐 <code><?= e($post['author_ip']) ?></code>
      </a>
    <?php endif; ?>
    <span style="flex:1"></span>
    <?php if ($currentUser): ?>
      <a class="post-action quote-btn" href="#reply"
         data-post-id="<?= (int) $post['post_id'] ?>"
         data-author="<?= e($post['username']) ?>">
        💬 <?= e(t('quote')) ?>
      </a>
    <?php endif; ?>
    <?php if ($currentUser && $currentUser['user_id'] != $post['user_id']): ?>
      <a class="post-action" href="<?= e(FORUM_URL) ?>/report.php?post=<?= (int) $post['post_id'] ?>"
         title="Beitrag melden">🚨 Melden</a>
    <?php endif; ?>
    <?php
      $editCount = post_edit_count((int) $post['post_id']);
      $canSeeHistory = $canModThis || ($currentUser && $currentUser['user_id'] == $post['user_id']);
    ?>
    <?php if ($editCount > 0 && $canSeeHistory): ?>
      <a class="post-action" href="<?= e(FORUM_URL) ?>/post_history.php?post=<?= (int) $post['post_id'] ?>"
         title="<?= $editCount ?> Bearbeitung(en)">📜 Verlauf (<?= $editCount ?>)</a>
    <?php endif; ?>
    <?php if ($currentUser && ($currentUser['user_id'] == $post['user_id'] || $canModThis)): ?>
      <a class="post-action" href="<?= e(FORUM_URL) ?>/edit_post.php?id=<?= (int) $post['post_id'] ?>">✏️ <?= e(t('edit')) ?></a>
    <?php endif; ?>
    <?php if ($canModThis): ?>
      <a class="post-action" href="<?= e(FORUM_URL) ?>/mod_action.php?action=delete_post&post=<?= (int) $post['post_id'] ?>&csrf=<?= e($csrfToken) ?>"
         onclick="return confirm('Beitrag löschen?')">🗑️ <?= e(t('delete')) ?></a>
    <?php endif; ?>
  </div>
  <?= $post['hookFooter'] ?? '' ?>
</div>
<?php endforeach; ?>

<?= $pagination ?>

<?php if ($currentUser && !$thread['is_closed']): ?>
<div id="reply" class="form-card" style="margin-top:20px">
  <h2>💬 <?= e(t('reply')) ?></h2>
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
    <div class="form-row">
      <?php
        $editorValue = '';
        $editorPlaceholder = 'Dein Beitrag… (@user erwähnen möglich)';
        include __DIR__ . '/partials/editor.php';
      ?>
    </div>
    <div class="form-row">
      <label>📎 <?= e(t('attach_file')) ?> (max. 5 MB pro Datei, mehrere möglich)</label>
      <input type="file" name="attachments[]" multiple accept="image/*,application/pdf,text/plain,.zip,.rar">
    </div>
    <button type="submit" class="btn btn-primary"><?= e(t('reply')) ?></button>
  </form>
</div>
<?php elseif ($thread['is_closed']): ?>
  <p style="margin-top:16px;color:var(--text-muted);font-style:italic">🔒 Dieser Thread ist geschlossen.</p>
<?php elseif (!$currentUser): ?>
  <p style="margin-top:16px">
    <a href="<?= e(FORUM_URL) ?>/login.php" class="btn btn-primary">Anmelden, um zu antworten</a>
  </p>
<?php endif; ?>
