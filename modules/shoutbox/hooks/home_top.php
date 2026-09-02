<?php
// modules/shoutbox/hooks/home_top.php – wird auf der Startseite oben angezeigt
if (!defined('FORUM_RUNNING')) return;

// Defensiv: Variablen aus dem aufrufenden Scope übernehmen oder selbst holen
if (!isset($currentUser)) $currentUser = current_user();
if (!isset($csrfToken))   $csrfToken   = $_SESSION['csrf_token'] ?? '';
?>
<div class="shoutbox-card card" id="shoutbox-card">
  <div class="shoutbox-header">
    <span class="shoutbox-title">Shoutbox</span>
    <button type="button" class="shoutbox-toggle" id="shoutbox-toggle" title="Ein-/Ausklappen">▾</button>
  </div>
  <div class="shoutbox-body" id="shoutbox-body">
    <div class="shoutbox-list" id="shoutbox-list">
      <div class="shoutbox-empty"><?= t('shoutbox_loading') ?></div>
    </div>
    <?php if ($currentUser): ?>
      <form class="shoutbox-form" id="shoutbox-form" autocomplete="off">
        <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
        <input type="text" name="message" id="shoutbox-input"
               placeholder="<?= e(t('shoutbox_platzhalter')) ?>"
               maxlength="500" required>
        <button type="submit" class="btn btn-primary btn-sm">Posten</button>
      </form>
    <?php else: ?>
      <div class="shoutbox-login-hint">
        <a href="<?= e(FORUM_URL) ?>/login.php"><?= e(t('shoutbox_einloggen')) ?></a><?= e(t('shoutbox_zum_nutzen')) ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
(function() {
  const list  = document.getElementById('shoutbox-list');
  const form  = document.getElementById('shoutbox-form');
  const input = document.getElementById('shoutbox-input');
  const card  = document.getElementById('shoutbox-card');
  const toggle = document.getElementById('shoutbox-toggle');
  const body  = document.getElementById('shoutbox-body');

  if (!list) return;

  let lastId = 0;
  const cu = <?= json_encode($currentUser ? [
      'id'       => (int) $currentUser['user_id'],
      'admin'    => (bool) ($currentUser['is_admin'] ?? false),
      'mod'      => (bool) ($currentUser['is_mod']   ?? false),
      'owner'    => (bool) ($currentUser['is_owner'] ?? false),
  ] : null) ?>;

  function esc(s) {
    // auch Anführungszeichen – der Name landet in Attributen (alt, src)
    return String(s ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function formatTime(iso) {
    const dt = new Date(iso.replace(' ', 'T'));
    return dt.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
  }

  function avatarSrc(u) {
    // 1. Eigenes Upload
    if (u.avatar) return <?= json_encode(FORUM_URL . '/uploads/avatars/') ?> + u.avatar;
    // 2. Rollen-Avatar (Owner > Admin > Mod)
    if (+u.is_owner) return <?= json_encode(FORUM_URL . '/assets/role_owner.svg') ?>;
    if (+u.is_admin) return <?= json_encode(FORUM_URL . '/assets/role_admin.svg') ?>;
    if (+u.is_mod)   return <?= json_encode(FORUM_URL . '/assets/role_mod.svg') ?>;
    // 3. Fallback
    return <?= json_encode(FORUM_URL . '/assets/default_avatar.svg') ?>;
  }

  function renderMessages(msgs, append = false) {
    if (!append) list.innerHTML = '';
    if (msgs.length === 0 && !append) {
      list.innerHTML = '<div class="shoutbox-empty"><?= e(t('shoutbox_empty')) ?></div>';
      return;
    }
    for (const m of msgs) {
      lastId = Math.max(lastId, m.shout_id);
      const canDelete = cu && (cu.admin || cu.mod || cu.owner || cu.id === m.user_id);
      let badge = '';
      if (+m.is_owner) badge = '<span class="post-owner-badge" style="font-size:9px">👑</span>';
      else if (+m.is_admin) badge = '<span class="post-admin-badge" style="font-size:9px">A</span>';
      else if (+m.is_mod) badge = '<span class="post-mod-badge" style="font-size:9px">M</span>';

      const html =
        '<div class="shoutbox-msg" data-id="' + m.shout_id + '">' +
          '<img class="shoutbox-avatar zoomable-avatar" src="' + esc(avatarSrc(m)) + '" alt="' + esc(m.username) + '"' +
               ' data-username="' + esc(m.username) + '"' +
               ' data-profile-url="<?= e(FORUM_URL) ?>/profile.php?id=' + m.user_id + '">' +
          '<div class="shoutbox-msg-body">' +
            '<div class="shoutbox-msg-header">' +
              '<a href="<?= e(FORUM_URL) ?>/profile.php?id=' + m.user_id + '" class="shoutbox-name">' +
                esc(m.username) + '</a> ' + badge +
              '<span class="shoutbox-time">' + esc(formatTime(m.created_at)) + '</span>' +
              (canDelete ? '<button type="button" class="shoutbox-del" data-id="' + m.shout_id + '" title="<?= e(t('delete')) ?>">✕</button>' : '') +
            '</div>' +
            '<div class="shoutbox-msg-text">' + esc(m.message) + '</div>' +
          '</div>' +
        '</div>';
      list.insertAdjacentHTML('beforeend', html);
    }
    list.scrollTop = list.scrollHeight;
  }

  function loadMessages() {
    fetch('<?= e(FORUM_URL) ?>/modules/shoutbox/api.php?shout_action=list')
      .then(r => r.json())
      .then(data => {
        if (data.ok) renderMessages(data.messages);
      })
      .catch(() => {});
  }

  function pollNew() {
    // Auch mit lastId=0 funktionieren: Server liefert dann alle Nachrichten.
    // Wenn 0, dann komplett neu rendern (kein append), sonst nur dranhängen.
    const isInitial = (lastId === 0);
    fetch('<?= e(FORUM_URL) ?>/modules/shoutbox/api.php?shout_action=list&since=' + lastId)
      .then(r => r.json())
      .then(data => {
        if (!data.ok) return;
        if (isInitial) {
          // Bei leerer Box: komplett rendern (auch wenn 0 Messages → "Sei der/die Erste!")
          renderMessages(data.messages);
        } else if (data.messages.length > 0) {
          // Im laufenden Betrieb: nur neue anhängen
          renderMessages(data.messages, true);
        }
      })
      .catch(() => {});
  }

  // Posten
  if (form) {
    form.addEventListener('submit', function(e) {
      e.preventDefault();
      e.stopPropagation();
      const msg = input.value.trim();
      if (!msg) return;
      const btn = form.querySelector('button');
      btn.disabled = true;
      const fd = new FormData(form);
      fd.append('shout_action', 'post');
      fetch('<?= e(FORUM_URL) ?>/modules/shoutbox/api.php', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(r => r.text().then(txt => {
          let data;
          try { data = JSON.parse(txt); }
          catch (err) {
            console.error('Shoutbox: Antwort ist kein JSON:', txt);
            alert(<?= json_encode(t('shoutbox_server_fehler')) ?>);
            btn.disabled = false;
            return;
          }
          btn.disabled = false;
          if (data.ok) {
            input.value = '';
            // Bei der allerersten Nachricht ist lastId=0 → komplette Liste laden
            // sonst nur die Neuen pollen. pollNew handhabt beides korrekt.
            pollNew();
          } else {
            alert(data.error || <?= json_encode(t('shoutbox_senden_fehlgeschlagen')) ?>);
          }
        }))
        .catch(err => {
          console.error('Shoutbox-Fehler:', err);
          alert(<?= json_encode(t('shoutbox_netzwerk_fehler')) ?>);
          btn.disabled = false;
        });
      return false;
    });
  }

  // Löschen
  list.addEventListener('click', function(e) {
    const btn = e.target.closest('.shoutbox-del');
    if (!btn) return;
    if (!confirm(<?= json_encode(t('shoutbox_loeschen_frage')) ?>)) return;
    const fd = new FormData();
    fd.append('shout_action', 'delete');
    fd.append('shout_id', btn.dataset.id);
    fd.append('csrf', <?= json_encode($csrfToken) ?>);
    fetch('<?= e(FORUM_URL) ?>/modules/shoutbox/api.php', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(data => {
        if (data.ok) {
          const row = btn.closest('.shoutbox-msg');
          row.style.transition = 'opacity .2s';
          row.style.opacity = '0';
          setTimeout(() => row.remove(), 200);
        } else {
          alert(data.error || <?= json_encode(t('error_titel')) ?>);
        }
      });
  });

  // Toggle
  if (toggle) {
    toggle.addEventListener('click', function() {
      const open = body.style.display !== 'none';
      body.style.display = open ? 'none' : '';
      toggle.textContent = open ? '▸' : '▾';
      try { localStorage.setItem('shoutbox-collapsed', open ? '1' : '0'); } catch(e) {}
    });
    // Zustand wiederherstellen
    try {
      if (localStorage.getItem('shoutbox-collapsed') === '1') {
        body.style.display = 'none';
        toggle.textContent = '▸';
      }
    } catch(e) {}
  }

  // Initial laden
  loadMessages();

  // Auto-Refresh alle 10 Sekunden
  setInterval(pollNew, 10000);
})();
</script>
