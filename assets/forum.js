// forum.js – Dark Mode + BBCode + Notifications

// ── DARK / LIGHT MODE ────────────────────────────────────────────────
(function () {
  const KEY  = 'forum_theme';
  const html = document.documentElement;

  // Wenn das Layout schon ein data-theme gesetzt hat (User-Einstellung),
  // hat das Vorrang. Sonst: localStorage > Default 'dark'.
  const dataTheme = html.getAttribute('data-theme');
  if (!dataTheme || dataTheme === 'auto') {
    const saved = localStorage.getItem(KEY);
    html.setAttribute('data-theme', saved || 'dark');
  }

  window.toggleTheme = function () {
    const next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    html.setAttribute('data-theme', next);
    localStorage.setItem(KEY, next);
    updateToggleIcon();

    // Persistent auf Server speichern (DB für User, Cookie für Gast)
    if (window.FORUM_CSRF && window.FORUM_URL) {
      const fd = new FormData();
      fd.append('theme', next);
      fd.append('csrf', window.FORUM_CSRF);
      fetch(window.FORUM_URL + '/api/set_theme.php', {
        method: 'POST',
        body: fd,
        credentials: 'same-origin',
      }).catch(() => {}); // Fehler ignorieren, lokal ist ja schon umgeschaltet
    }
  };

  function updateToggleIcon() {
    const btn = document.getElementById('theme-toggle');
    if (btn) btn.textContent = html.getAttribute('data-theme') === 'dark' ? '☀️' : '🌙';
  }

  document.addEventListener('DOMContentLoaded', updateToggleIcon);
})();

// ── BBCODE TOOLBAR ───────────────────────────────────────────────────
function bbWrap(tag, textareaId) {
  textareaId = textareaId || 'content';
  const ta = document.getElementById(textareaId);
  if (!ta) return;
  const s = ta.selectionStart, e = ta.selectionEnd;
  const sel = ta.value.substring(s, e) || 'Text';
  ta.value = ta.value.substring(0, s) + '[' + tag + ']' + sel + '[/' + tag + ']' + ta.value.substring(e);
  ta.focus();
  ta.selectionStart = s + tag.length + 2;
  ta.selectionEnd   = s + tag.length + 2 + sel.length;
}

function bbInsert(open, close, placeholder, textareaId) {
  textareaId = textareaId || 'content';
  const ta = document.getElementById(textareaId);
  if (!ta) return;
  const s = ta.selectionStart, e = ta.selectionEnd;
  const sel = ta.value.substring(s, e) || placeholder;
  ta.value = ta.value.substring(0, s) + open + sel + close + ta.value.substring(e);
  ta.focus();
}

// ── QUOTE INSERT ────────────────────────────────────────────────────
function insertQuote(author, text, textareaId) {
  textareaId = textareaId || 'content';
  const ta = document.getElementById(textareaId);
  if (!ta) return;
  const q = '[quote=' + author + ']' + text + '[/quote]\n';
  ta.value += q;
  ta.focus();
  ta.scrollTop = ta.scrollHeight;
}

// ── CONFIRM DELETE ──────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('[data-confirm]').forEach(function (el) {
    el.addEventListener('click', function (e) {
      if (!confirm(el.dataset.confirm)) e.preventDefault();
    });
  });
});

// ── SMILEY PICKER ───────────────────────────────────────────────────
function toggleSmileyPicker(textareaId) {
  textareaId = textareaId || 'content';
  let picker = document.getElementById('smiley-picker-' + textareaId);
  if (picker) {
    picker.remove();
    return;
  }

  const ta = document.getElementById(textareaId);
  if (!ta) return;

  // Smileys aus globaler Variable (vom Server gerendert)
  const smileys = window.FORUM_SMILEYS || {};

  picker = document.createElement('div');
  picker.id = 'smiley-picker-' + textareaId;
  picker.className = 'smiley-picker';

  Object.entries(smileys).forEach(([code, emoji]) => {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'smiley-btn';
    btn.textContent = emoji;
    btn.title = code;
    btn.onclick = () => {
      insertSmiley(code, textareaId);
      picker.remove();
    };
    picker.appendChild(btn);
  });

  ta.parentNode.insertBefore(picker, ta);
}

function insertSmiley(code, textareaId) {
  const ta = document.getElementById(textareaId || 'content');
  if (!ta) return;
  const s = ta.selectionStart, e = ta.selectionEnd;
  // Leerzeichen davor wenn nötig
  const before = ta.value.substring(0, s);
  const needSpace = before.length > 0 && !/\s$/.test(before);
  const insert = (needSpace ? ' ' : '') + code + ' ';
  ta.value = before + insert + ta.value.substring(e);
  ta.focus();
  ta.selectionStart = ta.selectionEnd = s + insert.length;
}

// Klick außerhalb schließt Picker
document.addEventListener('click', function (e) {
  if (e.target.closest('.smiley-picker') || e.target.matches('[onclick*="toggleSmileyPicker"]')) return;
  document.querySelectorAll('.smiley-picker').forEach(p => p.remove());
});

// ── QUICK SEARCH ─────────────────────────────────────────────────────
(function () {
  const input    = document.getElementById('quicksearch-input');
  const dropdown = document.getElementById('quicksearch-dropdown');
  if (!input || !dropdown) return;

  // Nutze aktuelle Origin (verhindert CORS-Probleme bei www-Redirects)
  const FORUM_PATH = (() => {
    try {
      const u = new URL(window.FORUM_URL || window.location.href);
      return u.pathname.replace(/\/$/, '');
    } catch (e) { return ''; }
  })();
  const FORUM_URL = window.location.origin + FORUM_PATH;
  let debounceTimer = null;
  let currentRequest = null;
  let selectedIdx = -1;
  let resultItems = [];

  function escapeHTML(str) {
    return String(str).replace(/[&<>"']/g, c =>
      ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  }

  function highlight(text, query) {
    const safe = escapeHTML(text);
    if (!query || query.length < 2) return safe;
    const re = new RegExp('(' + query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
    return safe.replace(re, '<mark>$1</mark>');
  }

  function closeDropdown() {
    dropdown.classList.remove('open');
    dropdown.innerHTML = '';
    dropdown.setAttribute('aria-hidden', 'true');
    selectedIdx = -1;
    resultItems = [];
  }

  function showLoading() {
    dropdown.classList.add('open');
    dropdown.setAttribute('aria-hidden', 'false');
    dropdown.innerHTML = '<div class="qs-loading">⏳ Suche läuft…</div>';
  }

  function showEmpty(query) {
    dropdown.classList.add('open');
    dropdown.innerHTML = '<div class="qs-empty">Keine Treffer für <strong>'
      + escapeHTML(query) + '</strong></div>'
      + '<a class="qs-all" href="' + FORUM_URL + '/search.php?q=' + encodeURIComponent(query) + '">'
      + '🔍 Erweiterte Suche →</a>';
  }

  function renderResults(data) {
    if (!data.has_results) { showEmpty(data.query); return; }

    let html = '';
    resultItems = [];

    // Threads
    if (data.threads.length > 0) {
      html += '<div class="qs-section">💬 Threads</div>';
      data.threads.forEach(t => {
        const url = FORUM_URL + '/thread.php?id=' + t.thread_id;
        resultItems.push(url);
        html += '<a class="qs-item" href="' + url + '" data-idx="' + (resultItems.length - 1) + '">'
              + '<div class="qs-item-main">'
              +   '<div class="qs-item-title">' + highlight(t.title, data.query) + '</div>'
              +   '<div class="qs-item-meta">'
              +     'in <strong>' + escapeHTML(t.board_title) + '</strong> '
              +     'von ' + escapeHTML(t.author) + ' &bull; '
              +     t.reply_count + ' Antworten'
              +   '</div>'
              + '</div>'
              + '</a>';
      });
    }

    // Users
    if (data.users.length > 0) {
      html += '<div class="qs-section">👤 Mitglieder</div>';
      data.users.forEach(u => {
        const url = FORUM_URL + '/profile.php?id=' + u.user_id;
        resultItems.push(url);
        let badge = '';
        if (u.badge === 'banned') badge = '<span class="qs-badge qs-badge-banned">🚫 Gesperrt</span>';
        else if (u.badge === 'admin') badge = '<span class="qs-badge qs-badge-admin">Admin</span>';
        else if (u.badge === 'mod')   badge = '<span class="qs-badge qs-badge-mod">Mod</span>';

        html += '<a class="qs-item" href="' + url + '" data-idx="' + (resultItems.length - 1) + '">'
              + '<img class="qs-avatar" src="' + escapeHTML(u.avatar_url) + '" alt="">'
              + '<div class="qs-item-main">'
              +   '<div class="qs-item-title">' + highlight(u.username, data.query) + ' ' + badge + '</div>'
              +   '<div class="qs-item-meta">' + u.post_count + ' Beiträge &bull; '
              +     escapeHTML(u.rank || '') + '</div>'
              + '</div>'
              + '</a>';
      });
    }

    // Alle-Ergebnisse-Link
    html += '<a class="qs-all" href="' + FORUM_URL + '/search.php?q=' + encodeURIComponent(data.query) + '">'
          + '🔍 Alle Ergebnisse anzeigen →</a>';

    dropdown.innerHTML = html;
    dropdown.classList.add('open');
    dropdown.setAttribute('aria-hidden', 'false');
    selectedIdx = -1;
  }

  function fetchResults(query) {
    // Vorherige Anfrage abbrechen
    if (currentRequest) currentRequest.abort();
    currentRequest = new AbortController();

    fetch('/api/quicksearch.php?q=' + encodeURIComponent(query), {
      signal: currentRequest.signal,
      credentials: 'same-origin',
    })
      .then(r => r.json())
      .then(data => renderResults(data))
      .catch(err => {
        if (err.name !== 'AbortError') {
          console.error('Suchfehler:', err);
          closeDropdown();
        }
      });
  }

  // Input-Event mit Debouncing
  input.addEventListener('input', function () {
    const q = input.value.trim();
    clearTimeout(debounceTimer);
    if (q.length < 2) { closeDropdown(); return; }
    showLoading();
    debounceTimer = setTimeout(() => fetchResults(q), 250);
  });

  // Fokus aktiviert ggf. Dropdown wenn schon Text drin ist
  input.addEventListener('focus', function () {
    if (input.value.trim().length >= 2 && dropdown.innerHTML.trim() !== '') {
      dropdown.classList.add('open');
    }
  });

  // Keyboard-Navigation
  input.addEventListener('keydown', function (e) {
    const items = dropdown.querySelectorAll('.qs-item');
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      selectedIdx = Math.min(selectedIdx + 1, items.length - 1);
      updateSelection(items);
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      selectedIdx = Math.max(selectedIdx - 1, -1);
      updateSelection(items);
    } else if (e.key === 'Enter' && selectedIdx >= 0 && resultItems[selectedIdx]) {
      e.preventDefault();
      window.location.href = resultItems[selectedIdx];
    } else if (e.key === 'Escape') {
      closeDropdown();
      input.blur();
    }
  });

  function updateSelection(items) {
    items.forEach((el, i) => el.classList.toggle('qs-selected', i === selectedIdx));
    if (selectedIdx >= 0 && items[selectedIdx]) {
      items[selectedIdx].scrollIntoView({ block: 'nearest' });
    }
  }

  // Außerhalb-Klick schließt Dropdown
  document.addEventListener('click', function (e) {
    if (!e.target.closest('#quicksearch-wrap')) closeDropdown();
  });
})();

// ── REACTIONS ────────────────────────────────────────────────────────
(function () {
  // Nutze aktuelle Origin (verhindert CORS-Probleme bei www-Redirects)
  const FORUM_PATH = (() => {
    try {
      const u = new URL(window.FORUM_URL || window.location.href);
      return u.pathname.replace(/\/$/, '');
    } catch (e) { return ''; }
  })();
  const FORUM_URL = window.location.origin + FORUM_PATH;
  const REACTION_TYPES = {
    like:   ['👍', 'Gefällt mir'],
    love:   ['❤️', 'Liebe'],
    haha:   ['😄', 'Lustig'],
    wow:    ['😮', 'Wow'],
    sad:    ['😢', 'Traurig'],
    thanks: ['🙏', 'Danke'],
  };
  const csrfMeta = () => window.FORUM_CSRF || '';

  // Klick auf bestehende Reaktion → toggle
  document.addEventListener('click', function (e) {
    const pill = e.target.closest('.reaction-pill');
    if (pill) {
      e.preventDefault();
      const bar = pill.closest('.reactions-bar');
      const postId = bar.dataset.postId;
      const type = pill.dataset.type;
      sendReaction(postId, type, bar);
      return;
    }

    // Klick auf "+" → Picker öffnen
    const addBtn = e.target.closest('.reaction-add');
    if (addBtn) {
      e.preventDefault();
      e.stopPropagation();
      togglePicker(addBtn);
      return;
    }

    // Klick auf Reaktion im Picker
    const pickerBtn = e.target.closest('.reaction-picker-btn');
    if (pickerBtn) {
      e.preventDefault();
      const type = pickerBtn.dataset.type;
      const bar  = pickerBtn.closest('.post-card').querySelector('.reactions-bar');
      sendReaction(bar.dataset.postId, type, bar);
      closePickers();
      return;
    }

    // Außerhalb-Klick: Picker zu
    if (!e.target.closest('.reaction-picker')) closePickers();
  });

  function togglePicker(btn) {
    closePickers();
    const picker = document.createElement('div');
    picker.className = 'reaction-picker';
    Object.entries(REACTION_TYPES).forEach(([type, [emoji, label]]) => {
      const b = document.createElement('button');
      b.type = 'button';
      b.className = 'reaction-picker-btn';
      b.dataset.type = type;
      b.title = label;
      b.textContent = emoji;
      picker.appendChild(b);
    });
    btn.parentNode.insertBefore(picker, btn.nextSibling);
  }

  function closePickers() {
    document.querySelectorAll('.reaction-picker').forEach(p => p.remove());
  }

  function sendReaction(postId, type, bar) {
    const fd = new FormData();
    fd.append('csrf', csrfMeta());
    fd.append('post_id', postId);
    fd.append('type', type);

    fetch('/api/react.php', {
      method: 'POST',
      body: fd,
      credentials: 'same-origin',
    })
      .then(r => {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
      })
      .then(data => {
        if (data.error) {
          console.error('Reaktion-Fehler:', data.error);
          alert('Reaktion fehlgeschlagen: ' + data.error);
          return;
        }
        renderReactions(bar, data.reactions);
      })
      .catch(err => {
        console.error('Reaktion-Netzwerkfehler:', err);
        alert('Reaktion fehlgeschlagen. Details in der Konsole (F12).');
      });
  }

  function renderReactions(bar, reactions) {
    // Alle pills entfernen, "+"-Button stehen lassen
    bar.querySelectorAll('.reaction-pill').forEach(p => p.remove());
    const addBtn = bar.querySelector('.reaction-add');
    reactions.forEach(r => {
      const info = REACTION_TYPES[r.type];
      if (!info) return;
      const pill = document.createElement('button');
      pill.type = 'button';
      pill.className = 'reaction-pill' + (r.by_me ? ' by-me' : '');
      pill.dataset.type = r.type;
      pill.title = info[1];
      pill.innerHTML = '<span class="reaction-emoji">' + info[0] + '</span>'
                     + '<span class="reaction-count">' + r.count + '</span>';
      bar.insertBefore(pill, addBtn);
    });
  }
})();

// ── BOOKMARK TOGGLE ──────────────────────────────────────────────────
(function () {
  const btn = document.getElementById('bookmark-toggle');
  if (!btn) return;

  // Nutze aktuelle Origin (verhindert CORS-Probleme bei www-Redirects)
  const FORUM_PATH = (() => {
    try {
      const u = new URL(window.FORUM_URL || window.location.href);
      return u.pathname.replace(/\/$/, '');
    } catch (e) { return ''; }
  })();
  const FORUM_URL = window.location.origin + FORUM_PATH;
  const csrf = () => window.FORUM_CSRF || '';

  btn.addEventListener('click', function () {
    const fd = new FormData();
    fd.append('csrf', csrf());
    fd.append('thread_id', btn.dataset.thread);

    fetch('/api/bookmark.php', {
      method: 'POST',
      body: fd,
      credentials: 'same-origin',
    })
      .then(r => r.json())
      .then(data => {
        if (data.error) { alert(data.error); return; }
        const icon  = document.getElementById('bookmark-icon');
        const label = document.getElementById('bookmark-label');
        if (data.action === 'added') {
          icon.textContent = '🔖';
          label.textContent = 'Gespeichert';
          btn.dataset.bookmarked = '1';
        } else {
          icon.textContent = '📑';
          label.textContent = 'Lesezeichen';
          btn.dataset.bookmarked = '0';
        }
      })
      .catch(err => console.error(err));
  });
})();

// ── AUTO-SAVE DRAFTS ────────────────────────────────────────────────
// (Live-Preview ist in den neuen Editor unten gewandert)
(function () {
  const ta = document.getElementById('content');
  if (!ta) return;

  // Storage-Key basierend auf URL (so dass z.B. /thread.php?id=1 einen anderen Entwurf hat als /new_thread)
  const storageKey = 'forum_draft_' + location.pathname + location.search;

  // Status-Anzeige in den Editor einbauen (rechts neben Vorschau-Button)
  const editor = ta.closest('.editor');
  let statusEl = null;
  if (editor) {
    const toolbarRight = editor.querySelector('.editor-toolbar-right');
    if (toolbarRight) {
      statusEl = document.createElement('span');
      statusEl.className = 'editor-status';
      statusEl.style.cssText = 'margin-right:8px;font-size:12px;color:var(--text-muted)';
      toolbarRight.insertBefore(statusEl, toolbarRight.firstChild);
    }
  }

  function showStatus(text) {
    if (statusEl) statusEl.textContent = text;
  }

  // Wiederherstellen wenn Entwurf vorhanden
  try {
    const saved = localStorage.getItem(storageKey);
    if (saved && saved.length > 5 && !ta.value.trim()) {
      const restore = confirm('🔄 Es gibt einen gespeicherten Entwurf für diese Seite. Wiederherstellen?');
      if (restore) {
        ta.value = saved;
        showStatus('💾 Entwurf wiederhergestellt');
        // Live-Vorschau aktualisieren falls aktiv
        ta.dispatchEvent(new Event('input', { bubbles: true }));
      } else {
        localStorage.removeItem(storageKey);
      }
    }
  } catch (e) {}

  // Bei jedem Tippen 1s später speichern
  let saveTimer = null;
  ta.addEventListener('input', () => {
    clearTimeout(saveTimer);
    saveTimer = setTimeout(() => {
      try {
        if (ta.value.trim()) {
          localStorage.setItem(storageKey, ta.value);
          showStatus('💾 Entwurf gespeichert');
          setTimeout(() => showStatus(''), 1500);
        }
      } catch (e) {}
    }, 1000);
  });

  // Beim Submit Entwurf löschen
  const form = ta.closest('form');
  if (form) {
    form.addEventListener('submit', () => {
      try { localStorage.removeItem(storageKey); } catch (e) {}
    });
  }
})();

// ── USER-MENÜ DROPDOWN ──────────────────────────────────────────────
(function () {
  const menu = document.querySelector('.user-menu');
  if (!menu) return;
  const toggle = menu.querySelector('.user-menu-toggle');

  toggle.addEventListener('click', function (e) {
    e.stopPropagation();
    menu.classList.toggle('open');
    toggle.setAttribute('aria-expanded', menu.classList.contains('open') ? 'true' : 'false');
  });

  document.addEventListener('click', function (e) {
    if (!menu.contains(e.target)) {
      menu.classList.remove('open');
      toggle.setAttribute('aria-expanded', 'false');
    }
  });

  // ESC schließt
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      menu.classList.remove('open');
      toggle.setAttribute('aria-expanded', 'false');
    }
  });
})();

// ── ACTION DROPDOWNS ────────────────────────────────────────────────
(function () {
  document.addEventListener('click', function (e) {
    const toggle = e.target.closest('.action-dropdown-toggle');
    if (toggle) {
      e.preventDefault();
      e.stopPropagation();
      const wrap = toggle.closest('.action-dropdown');
      const wasOpen = wrap.classList.contains('open');
      // Erst alle anderen schließen
      document.querySelectorAll('.action-dropdown.open').forEach(d => d.classList.remove('open'));
      // Dann diesen toggeln
      if (!wasOpen) wrap.classList.add('open');
      return;
    }
    // Außerhalb-Klick: alle schließen
    if (!e.target.closest('.action-dropdown-menu')) {
      document.querySelectorAll('.action-dropdown.open').forEach(d => d.classList.remove('open'));
    }
  });

  // ESC schließt alle
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      document.querySelectorAll('.action-dropdown.open').forEach(d => d.classList.remove('open'));
    }
  });
})();

// ══════════ QUOTE-FUNKTION ══════════════════════════════════════════════
(function () {
  document.addEventListener('click', function (e) {
    const btn = e.target.closest('.quote-btn');
    if (!btn) return;
    e.preventDefault();

    const postId = btn.dataset.postId;
    const author = btn.dataset.author;
    const rawEl  = document.querySelector('.post-raw[data-post-id="' + postId + '"]');
    if (!rawEl) return;

    // Inhalt aus dem versteckten Element (HTML-decoded)
    const content = rawEl.textContent.trim();

    // Verschachtelte Quotes entfernen (sonst wird's unleserlich)
    const cleaned = content.replace(/\[quote(=[^\]]*)?\][\s\S]*?\[\/quote\]/gi, '').trim();

    const quoted = '[quote=' + author + ';post=' + postId + ']' + cleaned + '[/quote]\n';

    // In das Antwort-Textfeld einfügen
    const textarea = document.querySelector('#reply textarea[name="content"]');
    if (!textarea) {
      // Falls Reply-Form fehlt (z.B. geschlossener Thread): zur Antwort scrollen
      window.location.hash = '#reply';
      return;
    }

    // An aktueller Cursor-Position oder am Ende einfügen
    const cur  = textarea.selectionStart || textarea.value.length;
    const pre  = textarea.value.substring(0, cur);
    const post = textarea.value.substring(cur);
    textarea.value = (pre ? pre + '\n' : '') + quoted + post;

    // Fokus und zum Antwort-Bereich scrollen
    textarea.focus();
    textarea.scrollTop = textarea.scrollHeight;
    const target = document.getElementById('reply');
    if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
  });
})();

// ══════════ @MENTION AUTOCOMPLETE ══════════════════════════════════════
(function () {
  let popup = null;
  let activeTextarea = null;
  let selectedIndex = 0;
  let currentMatches = [];
  let triggerStart = -1;
  let abortController = null;

  function createPopup() {
    const p = document.createElement('div');
    p.id = 'mention-popup';
    p.style.display = 'none';
    document.body.appendChild(p);
    return p;
  }

  function hidePopup() {
    if (popup) popup.style.display = 'none';
    currentMatches = [];
    triggerStart = -1;
  }

  function showPopup(textarea) {
    if (!popup) popup = createPopup();
    const rect = textarea.getBoundingClientRect();

    // Position relativ zum Cursor – wir nehmen einfach unter den Textarea
    const caret = getCaretCoordinates(textarea);
    popup.style.left = (rect.left + window.scrollX + caret.left) + 'px';
    popup.style.top  = (rect.top + window.scrollY + caret.top + 24) + 'px';
    popup.style.display = 'block';
  }

  // Cursor-Position im Textarea ermitteln (vereinfacht)
  function getCaretCoordinates(textarea) {
    // Vereinfacht: wir nehmen ungefähr Mitte – für präzise Position bräuchten wir
    // einen Mirror-Div, was komplex ist. Reicht für Forum-Bedürfnisse.
    return { left: 0, top: textarea.offsetHeight };
  }

  function mentionEscape(str) {
    return String(str ?? '').replace(/[&<>"']/g, c =>
      ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  }

  function renderMatches() {
    if (!popup) return;
    if (currentMatches.length === 0) {
      popup.innerHTML = '<div class="mention-empty">Keine Treffer</div>';
      return;
    }
    popup.innerHTML = currentMatches.map((u, i) => {
      let badge = '';
      if (u.is_owner) badge = '<span class="mention-badge" style="background:#7c3aed">👑</span>';
      else if (u.is_admin) badge = '<span class="mention-badge" style="background:#dc2626">A</span>';
      else if (u.is_mod) badge = '<span class="mention-badge" style="background:#f59e0b">M</span>';
      // Name und Avatar-URL kommen roh aus der API und landen in innerHTML —
      // beides muss escaped werden, Attribute inklusive Anführungszeichen.
      return '<div class="mention-item' + (i === selectedIndex ? ' active' : '') + '" data-idx="' + i + '">'
           + '<img src="' + mentionEscape(u.avatar) + '" alt="">'
           + '<span class="mention-name">' + mentionEscape(u.name) + '</span>'
           + badge
           + '</div>';
    }).join('');
  }

  function searchUsers(query) {
    if (abortController) abortController.abort();
    abortController = new AbortController();

    fetch('/api/users_search.php?q=' + encodeURIComponent(query), {
      signal: abortController.signal,
      credentials: 'same-origin'
    })
      .then(r => r.ok ? r.json() : { users: [] })
      .then(data => {
        currentMatches = data.users || [];
        selectedIndex = 0;
        renderMatches();
        if (currentMatches.length > 0 && activeTextarea) {
          showPopup(activeTextarea);
        } else {
          hidePopup();
        }
      })
      .catch(() => {});
  }

  function insertMention(user) {
    if (!activeTextarea || triggerStart < 0) return;

    const value = activeTextarea.value;
    const before = value.substring(0, triggerStart);
    const after  = value.substring(activeTextarea.selectionStart);
    const inserted = '@' + user.name + ' ';

    activeTextarea.value = before + inserted + after;
    const newPos = (before + inserted).length;
    activeTextarea.setSelectionRange(newPos, newPos);
    activeTextarea.focus();
    hidePopup();
  }

  function onInput(e) {
    const ta = e.target;
    if (!ta.matches('textarea[name="content"]')) return;

    activeTextarea = ta;
    const pos = ta.selectionStart;
    const before = ta.value.substring(0, pos);

    // Letzte @-Trigger suchen (ohne Leerzeichen danach)
    const match = before.match(/(^|\s)@([a-zA-Z0-9_äöüÄÖÜß-]{1,30})$/);
    if (!match) {
      hidePopup();
      return;
    }
    const query = match[2];
    triggerStart = pos - query.length - 1; // position des @-Zeichens

    if (query.length === 0) {
      hidePopup();
      return;
    }
    searchUsers(query);
  }

  function onKeyDown(e) {
    if (!popup || popup.style.display === 'none') return;
    if (!e.target.matches('textarea[name="content"]')) return;

    if (e.key === 'ArrowDown') {
      e.preventDefault();
      selectedIndex = (selectedIndex + 1) % currentMatches.length;
      renderMatches();
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      selectedIndex = (selectedIndex - 1 + currentMatches.length) % currentMatches.length;
      renderMatches();
    } else if (e.key === 'Enter' || e.key === 'Tab') {
      if (currentMatches.length > 0) {
        e.preventDefault();
        insertMention(currentMatches[selectedIndex]);
      }
    } else if (e.key === 'Escape') {
      hidePopup();
    }
  }

  document.addEventListener('input', onInput);
  document.addEventListener('keydown', onKeyDown, true);

  // Klick auf Vorschlag
  document.addEventListener('click', function (e) {
    const item = e.target.closest('.mention-item');
    if (item && currentMatches[+item.dataset.idx]) {
      insertMention(currentMatches[+item.dataset.idx]);
    } else if (popup && !popup.contains(e.target)) {
      hidePopup();
    }
  });
})();

// ══════════ ADMIN NAV GROUPS ════════════════════════════════════════════
(function () {
  document.addEventListener('click', function (e) {
    const toggle = e.target.closest('.admin-nav-group-toggle');
    if (toggle) {
      e.preventDefault();
      e.stopPropagation();
      const group = toggle.closest('.admin-nav-group');
      const wasOpen = group.classList.contains('open');
      // Alle anderen schließen
      document.querySelectorAll('.admin-nav-group.open').forEach(g => {
        if (g !== group) g.classList.remove('open');
      });
      // Aktiven toggeln
      if (!wasOpen) group.classList.add('open');
      else group.classList.remove('open');
      return;
    }
    // Außerhalb-Klick: alle schließen
    if (!e.target.closest('.admin-nav-group-menu')) {
      document.querySelectorAll('.admin-nav-group.open').forEach(g => g.classList.remove('open'));
    }
  });

  // ESC schließt
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      document.querySelectorAll('.admin-nav-group.open').forEach(g => g.classList.remove('open'));
    }
  });
})();

// ══════════ TASTATUR-SHORTCUTS ════════════════════════════════════════
(function () {
  // Aktiv-Check: nicht greifen wenn der User in einem Input/Textarea ist
  function isTyping() {
    const el = document.activeElement;
    if (!el) return false;
    if (el.isContentEditable) return true;
    const tag = el.tagName;
    return tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT';
  }

  // Aktuelle Seite ermitteln
  function getCurrentPage() {
    const params = new URLSearchParams(window.location.search);
    return params.get('_page') || (window.location.pathname.includes('thread') ? 'thread'
                                 : window.location.pathname.includes('board') ? 'board'
                                 : 'home');
  }

  // Posts im Thread sammeln
  function getPosts() {
    return Array.from(document.querySelectorAll('.post-card'));
  }

  // Sichtbaren Post ermitteln (der gerade im Viewport ist)
  function getCurrentPostIndex() {
    const posts = getPosts();
    const scrollY = window.scrollY + window.innerHeight / 3;
    for (let i = 0; i < posts.length; i++) {
      const rect = posts[i].getBoundingClientRect();
      const top = rect.top + window.scrollY;
      if (top > scrollY) return Math.max(0, i - 1);
    }
    return posts.length - 1;
  }

  // Zu Post navigieren
  function scrollToPost(idx) {
    const posts = getPosts();
    if (posts[idx]) {
      posts[idx].scrollIntoView({ behavior: 'smooth', block: 'start' });
      // Kurzes Highlight
      posts[idx].classList.add('post-highlighted');
      setTimeout(() => posts[idx].classList.remove('post-highlighted'), 1500);
    }
  }

  // Such-Feld fokussieren
  function focusSearch() {
    const search = document.getElementById('quicksearch-input');
    if (search) {
      search.focus();
      search.select();
    }
  }

  // Antwort-Feld fokussieren (im Thread)
  function focusReply() {
    const reply = document.querySelector('#reply textarea[name="content"]');
    if (reply) {
      reply.focus();
      reply.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
  }

  // Shortcut-Overlay anzeigen
  function toggleShortcutsHelp() {
    let overlay = document.getElementById('shortcuts-overlay');
    if (overlay) {
      overlay.remove();
      return;
    }

    overlay = document.createElement('div');
    overlay.id = 'shortcuts-overlay';
    overlay.innerHTML = `
      <div class="shortcuts-modal">
        <div class="shortcuts-header">
          <h2>⌨️ Tastatur-Shortcuts</h2>
          <button type="button" class="shortcuts-close" title="Schließen">✕</button>
        </div>
        <div class="shortcuts-body">
          <div class="shortcuts-section">
            <h3>Navigation</h3>
            <div class="shortcut-row"><kbd>j</kbd> <span>Nächster Beitrag</span></div>
            <div class="shortcut-row"><kbd>k</kbd> <span>Vorheriger Beitrag</span></div>
            <div class="shortcut-row"><kbd>g</kbd> <kbd>g</kbd> <span>Zum Seitenanfang</span></div>
            <div class="shortcut-row"><kbd>G</kbd> <span>Zum Seitenende</span></div>
          </div>
          <div class="shortcuts-section">
            <h3>Aktionen</h3>
            <div class="shortcut-row"><kbd>r</kbd> <span>Antworten (im Thread)</span></div>
            <div class="shortcut-row"><kbd>n</kbd> <span>Neues Thema (im Board)</span></div>
            <div class="shortcut-row"><kbd>/</kbd> <span>Suche fokussieren</span></div>
          </div>
          <div class="shortcuts-section">
            <h3>Allgemein</h3>
            <div class="shortcut-row"><kbd>?</kbd> <span>Diese Hilfe</span></div>
            <div class="shortcut-row"><kbd>Esc</kbd> <span>Dialog/Overlay schließen</span></div>
          </div>
        </div>
        <div class="shortcuts-footer">
          Funktioniert nicht wenn du gerade in einem Eingabefeld bist.
        </div>
      </div>
    `;
    document.body.appendChild(overlay);

    overlay.addEventListener('click', function (e) {
      if (e.target === overlay || e.target.closest('.shortcuts-close')) {
        overlay.remove();
      }
    });
  }

  // "g g" Buffer
  let gPressed = false;
  let gTimer = null;

  document.addEventListener('keydown', function (e) {
    // Im Input? Nichts tun (außer ESC)
    if (isTyping()) {
      if (e.key === 'Escape') {
        document.activeElement.blur();
      }
      return;
    }

    // Modifier-Tasten ignorieren (Strg+C, etc.)
    if (e.ctrlKey || e.metaKey || e.altKey) return;

    const page = getCurrentPage();
    const key = e.key;

    // ESC → Overlay schließen
    if (key === 'Escape') {
      const overlay = document.getElementById('shortcuts-overlay');
      if (overlay) { overlay.remove(); return; }
    }

    // ? → Hilfe-Overlay
    if (key === '?') {
      e.preventDefault();
      toggleShortcutsHelp();
      return;
    }

    // / → Suche
    if (key === '/') {
      e.preventDefault();
      focusSearch();
      return;
    }

    // g g → ganz nach oben
    if (key === 'g' && !e.shiftKey) {
      if (gPressed) {
        clearTimeout(gTimer);
        gPressed = false;
        e.preventDefault();
        window.scrollTo({ top: 0, behavior: 'smooth' });
        return;
      }
      gPressed = true;
      gTimer = setTimeout(() => { gPressed = false; }, 500);
      return;
    }

    // G (Shift+g) → ganz nach unten
    if (key === 'G') {
      e.preventDefault();
      window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
      return;
    }

    // Thread-Navigation: j/k
    if ((key === 'j' || key === 'k') && page === 'thread') {
      e.preventDefault();
      const posts = getPosts();
      if (posts.length === 0) return;
      const cur = getCurrentPostIndex();
      const next = key === 'j' ? Math.min(posts.length - 1, cur + 1)
                               : Math.max(0, cur - 1);
      scrollToPost(next);
      return;
    }

    // r → Antworten (im Thread)
    if (key === 'r' && page === 'thread') {
      e.preventDefault();
      focusReply();
      return;
    }

    // n → Neues Thema (im Board)
    if (key === 'n' && page === 'board') {
      const newBtn = document.querySelector('a[href*="new_thread.php"]');
      if (newBtn) {
        e.preventDefault();
        window.location.href = newBtn.href;
      }
      return;
    }
  });
})();

// ══════════ MOBILE NAV TOGGLE ════════════════════════════════════════
(function() {
  const toggle = document.getElementById('mobile-nav-toggle');
  const items = document.getElementById('header-nav-items');
  if (!toggle || !items) return;

  toggle.addEventListener('click', function(e) {
    e.stopPropagation();
    items.classList.toggle('open');
  });

  // Klick außerhalb schließt das Menü
  document.addEventListener('click', function(e) {
    if (!items.contains(e.target) && !toggle.contains(e.target)) {
      items.classList.remove('open');
    }
  });
})();

// ══════════ COLOR-PICKER für Admin-Branding ════════════════════════════
(function() {
  document.addEventListener('change', function(e) {
    if (e.target.matches('.color-option input[type="radio"]')) {
      document.querySelectorAll('.color-option').forEach(o => o.classList.remove('selected'));
      e.target.closest('.color-option').classList.add('selected');
    }
  });
  // Klick auf das Label aktiviert das Radio (auch wenn das input hidden ist)
  document.addEventListener('click', function(e) {
    const opt = e.target.closest('.color-option');
    if (opt) {
      const radio = opt.querySelector('input[type="radio"]');
      if (radio && !radio.checked) {
        radio.checked = true;
        radio.dispatchEvent(new Event('change', { bubbles: true }));
      }
    }
  });
})();

// ══════════ AVATAR-LIGHTBOX ════════════════════════════════════════
(function() {
  const lb       = document.getElementById('avatar-lightbox');
  if (!lb) return;
  const img      = document.getElementById('avatar-lightbox-img');
  const nameEl   = document.getElementById('avatar-lightbox-name');
  const linkEl   = document.getElementById('avatar-lightbox-link');
  const closeBtn = lb.querySelector('.avatar-lightbox-close');

  function open(src, username, profileUrl) {
    img.src = src;
    img.alt = username || '';
    if (username) {
      nameEl.textContent = username;
      nameEl.style.display = '';
    } else {
      nameEl.style.display = 'none';
    }
    if (profileUrl) {
      linkEl.href = profileUrl;
      linkEl.style.display = '';
    } else {
      linkEl.style.display = 'none';
    }
    lb.classList.add('open');
    lb.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
  }

  function close() {
    lb.classList.remove('open');
    lb.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
  }

  // Klick auf einen zoombaren Avatar öffnet die Lightbox.
  // Event-Delegation: funktioniert auch für später ins DOM eingefügte Avatare (z.B. Shoutbox-Nachrichten).
  document.addEventListener('click', function(e) {
    const av = e.target.closest('.zoomable-avatar');
    if (!av) return;
    // Falls Avatar in einem <a>-Tag liegt: nicht zur Profilseite springen, sondern Lightbox öffnen
    e.preventDefault();
    e.stopPropagation();
    const username   = av.dataset.username || av.getAttribute('alt') || '';
    const profileUrl = av.dataset.profileUrl || (av.closest('a') ? av.closest('a').getAttribute('href') : '');
    open(av.src, username, profileUrl);
  });

  // Klick außerhalb des Bildes schließt
  lb.addEventListener('click', function(e) {
    if (e.target === lb || e.target === closeBtn) close();
  });

  // ESC schließt
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && lb.classList.contains('open')) close();
  });
})();

// ══════════ DESIGN-EDITOR (Admin) ════════════════════════════════════
(function() {
  const form = document.getElementById('design-form');
  if (!form) return;

  // ─── Tabs ─────────────────────────────────────────
  document.querySelectorAll('.design-tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const tab = btn.dataset.tab;
      document.querySelectorAll('.design-tab-btn').forEach(b => b.classList.toggle('active', b === btn));
      document.querySelectorAll('.design-tab-pane').forEach(p => p.classList.toggle('active', p.dataset.pane === tab));
    });
  });

  // ─── Color-Picker ↔ Text-Input synchronisieren ────
  form.querySelectorAll('.color-picker').forEach(picker => {
    const target = picker.dataset.target;
    const text   = form.querySelector(`input[name="${target}"]`);
    picker.addEventListener('input', () => {
      text.value = picker.value.replace('#','').toUpperCase();
      schedulePreview();
    });
    text.addEventListener('input', () => {
      const v = text.value.replace('#','').trim();
      if (/^[a-fA-F0-9]{6}$/.test(v)) {
        picker.value = '#' + v;
        schedulePreview();
      }
    });
  });

  // ─── Andere Felder: bei Änderung Preview aktualisieren ────
  form.querySelectorAll('[data-live]').forEach(el => {
    el.addEventListener('change', schedulePreview);
    el.addEventListener('input', schedulePreview);
  });

  // ─── Custom-Font-Feld ein-/ausblenden ────
  const fontSel = document.getElementById('theme_font_family');
  const customRow = document.getElementById('custom-font-row');
  if (fontSel && customRow) {
    fontSel.addEventListener('change', () => {
      customRow.style.display = fontSel.value === 'custom' ? '' : 'none';
    });
  }

  // ─── Preview-Updates debouncen ────
  let previewTimer = null;
  function schedulePreview() {
    clearTimeout(previewTimer);
    previewTimer = setTimeout(submitPreview, 400);
  }

  async function submitPreview() {
    const fd = new FormData();
    fd.append('csrf', window.FORUM_CSRF || '');
    // Alle theme_* Felder einsammeln
    form.querySelectorAll('input[name^="theme_"], select[name^="theme_"]').forEach(el => {
      fd.append('theme[' + el.name + ']', el.value);
    });
    try {
      await fetch(window.FORUM_URL + '/admin/?action=preview_design', {
        method: 'POST', body: fd, credentials: 'same-origin',
      });
      // Iframe neu laden – mit Query-Parameter (Hash würde nicht neu laden!)
      reloadPreviewIframe();
    } catch (e) {
      console.error('Preview-Update fehlgeschlagen', e);
    }
  }

  function reloadPreviewIframe() {
    const ifr = document.getElementById('design-preview-frame');
    if (!ifr) return;
    const base = ifr.src.split('?')[0].split('#')[0];
    ifr.src = base + '?preview=1&_t=' + Date.now();
  }

  // ─── Manueller Reload-Button ────
  document.getElementById('reload-preview')?.addEventListener('click', reloadPreviewIframe);

  // ─── Reset-Button: triggert externes Form ────
  document.getElementById('reset-design-btn')?.addEventListener('click', () => {
    if (confirm('Alle Design-Einstellungen auf Standard zurücksetzen? Das überschreibt deine aktuellen Werte!')) {
      document.getElementById('reset-design-form').submit();
    }
  });

  // ─── Theme-Presets ────
  const themePresets = {
    original: {
      theme_color_bg_body:'0a0a0b', theme_color_bg_surface:'131316', theme_color_bg_surface2:'1a1a1f',
      theme_color_text_base:'e7e7ea', theme_color_text_muted:'a1a1aa', theme_color_text_faint:'71717a',
      theme_color_border:'24242b', theme_color_border_strong:'34343d',
      theme_color_success:'4ade80', theme_color_warning:'fbbf24', theme_color_danger:'f87171',
    },
    discord: {
      theme_color_bg_body:'36393f', theme_color_bg_surface:'2f3136', theme_color_bg_surface2:'202225',
      theme_color_text_base:'dcddde', theme_color_text_muted:'b9bbbe', theme_color_text_faint:'72767d',
      theme_color_border:'202225', theme_color_border_strong:'4f545c',
      theme_color_success:'3ba55d', theme_color_warning:'faa61a', theme_color_danger:'ed4245',
    },
    mintgrun: {
      theme_color_bg_body:'0f1f1c', theme_color_bg_surface:'162a25', theme_color_bg_surface2:'1c3530',
      theme_color_text_base:'e8f5e9', theme_color_text_muted:'a7d7c5', theme_color_text_faint:'6ba292',
      theme_color_border:'265c4f', theme_color_border_strong:'3a8676',
      theme_color_success:'10b981', theme_color_warning:'eab308', theme_color_danger:'f87171',
    },
    sonne: {
      theme_color_bg_body:'1a0f08', theme_color_bg_surface:'2a1a10', theme_color_bg_surface2:'3a2418',
      theme_color_text_base:'fef3c7', theme_color_text_muted:'fdba74', theme_color_text_faint:'a16207',
      theme_color_border:'78350f', theme_color_border_strong:'b45309',
      theme_color_success:'84cc16', theme_color_warning:'fbbf24', theme_color_danger:'ef4444',
    },
    cyberpunk: {
      theme_color_bg_body:'0a0014', theme_color_bg_surface:'15001f', theme_color_bg_surface2:'1f002a',
      theme_color_text_base:'e0c3fc', theme_color_text_muted:'c084fc', theme_color_text_faint:'7e22ce',
      theme_color_border:'4c1d95', theme_color_border_strong:'7e22ce',
      theme_color_success:'22d3ee', theme_color_warning:'fde047', theme_color_danger:'ec4899',
    },
    softlight: {
      theme_color_bg_body_light:'f7f7f8', theme_color_bg_surface_light:'ffffff', theme_color_bg_surface2_light:'f3f3f5',
      theme_color_text_base_light:'18181b', theme_color_text_muted_light:'52525b', theme_color_text_faint_light:'71717a',
      theme_color_border_light:'e8e8eb', theme_color_border_strong_light:'d4d4d8',
    },
  };

  document.querySelectorAll('.theme-preset-apply').forEach(btn => {
    btn.addEventListener('click', () => {
      const presetId = btn.dataset.preset;
      const values = themePresets[presetId];
      if (!values) return;
      Object.entries(values).forEach(([key, hex]) => {
        // Text-Input setzen
        const inp = form.querySelector(`input[name="${key}"]`);
        if (inp) inp.value = hex.toUpperCase();
        // Color-Picker mit data-target=key setzen
        const picker = form.querySelector(`.color-picker[data-target="${key}"]`);
        if (picker) picker.value = '#' + hex;
      });
      schedulePreview();
      // Visuelles Feedback
      const originalText = btn.textContent;
      btn.textContent = '✓ Angewendet';
      btn.disabled = true;
      setTimeout(() => { btn.textContent = originalText; btn.disabled = false; }, 1500);
    });
  });

  // Initial einen Preview triggern damit Session aktuell ist
  submitPreview();
})();

// ══════════ TEST-MAIL-BUTTON (Settings → SMTP) ════════════════════════
(function() {
  const btn  = document.getElementById('send-test-mail-btn');
  if (!btn) return;
  const inp    = document.getElementById('test-mail-to');
  const status = document.getElementById('test-mail-status');

  btn.addEventListener('click', async () => {
    const to = (inp.value || '').trim();
    if (!to || !to.includes('@')) {
      status.innerHTML = '<span style="color:var(--color-danger)">⚠️ Bitte gültige E-Mail-Adresse eingeben.</span>';
      return;
    }
    btn.disabled = true;
    const originalText = btn.textContent;
    btn.textContent = '⏳ Sende...';
    status.innerHTML = '<span style="color:var(--text-muted)">Verbinde mit SMTP-Server...</span>';

    try {
      const fd = new FormData();
      fd.append('csrf', window.FORUM_CSRF || '');
      fd.append('to', to);
      const r = await fetch(window.FORUM_URL + '/admin/?action=send_test_mail', {
        method: 'POST', body: fd, credentials: 'same-origin',
      });
      const data = await r.json();
      if (data.ok) {
        status.innerHTML = '<span style="color:var(--color-success)">✅ ' + data.msg + '</span>';
      } else {
        status.innerHTML = '<span style="color:var(--color-danger)">❌ ' + data.error + '</span>';
      }
    } catch (e) {
      status.innerHTML = '<span style="color:var(--color-danger)">❌ Netzwerk-Fehler. Prüfe Browser-Konsole.</span>';
      console.error(e);
    }
    btn.textContent = originalText;
    btn.disabled = false;
  });
})();

// ══════════ COOKIE-BANNER ════════════════════════════════════════
(function() {
  const banner = document.getElementById('cookie-banner');
  if (!banner) return;
  // Anzeigen wenn noch nicht akzeptiert
  if (localStorage.getItem('cookie_accepted') !== '1') {
    banner.hidden = false;
  }
  document.getElementById('cookie-banner-accept')?.addEventListener('click', () => {
    localStorage.setItem('cookie_accepted', '1');
    banner.style.display = 'none';
  });
})();

// ══════════ TABELLEN MOBILE-RESPONSIVE (Auto-data-label) ══════════
// Macht alle .forum-table mobile-tauglich indem es jeder <td> ein
// data-label-Attribut aus dem korrespondierenden <th> mitgibt.
// Opt-out: Klasse .forum-table-keep-wide (für komplexe Tabellen)
//          oder .forum-table-responsive / .thread-table-responsive (haben eigene CSS-Logik)
(function() {
  const tables = document.querySelectorAll(
    '.forum-table:not(.forum-table-keep-wide):not(.forum-table-responsive):not(.thread-table-responsive)'
  );
  tables.forEach(table => {
    const headers = Array.from(table.querySelectorAll('thead th'))
                         .map(th => th.textContent.trim());
    if (headers.length === 0) return;

    table.querySelectorAll('tbody tr').forEach(row => {
      Array.from(row.children).forEach((td, idx) => {
        if (td.tagName !== 'TD') return;
        if (td.hasAttribute('data-label')) return; // schon manuell gesetzt
        const colspan = parseInt(td.getAttribute('colspan') || '1', 10);
        if (colspan > 1) return; // colspan-Zellen überspringen (z.B. „keine Einträge")
        if (headers[idx]) {
          td.setAttribute('data-label', headers[idx]);
        }
      });
    });
  });
})();

// ═══════════════════════════════════════════════════════════════════════
// EDITOR (WYSIWYG / BBCode) – v0.18.2
// ═══════════════════════════════════════════════════════════════════════
// Sucht alle Elemente mit .editor[data-editor-id] und initialisiert sie.
// Toolbar-Buttons via data-action, Smiley-Picker, optionale Live-Vorschau.
(function () {
  'use strict';

  /**
   * BBCode-Tag um Selection wickeln
   */
  function wrapSelection(ta, tag) {
    const s = ta.selectionStart, e = ta.selectionEnd;
    const sel = ta.value.substring(s, e) || 'Text';
    const before = ta.value.substring(0, s);
    const after = ta.value.substring(e);
    ta.value = before + '[' + tag + ']' + sel + '[/' + tag + ']' + after;
    ta.focus();
    ta.selectionStart = s + tag.length + 2;
    ta.selectionEnd = s + tag.length + 2 + sel.length;
    fireInput(ta);
  }

  /**
   * Beliebigen Text vor und nach Selection einfügen
   */
  function insertAround(ta, open, close, placeholder) {
    const s = ta.selectionStart, e = ta.selectionEnd;
    const sel = ta.value.substring(s, e) || placeholder;
    ta.value = ta.value.substring(0, s) + open + sel + close + ta.value.substring(e);
    ta.focus();
    ta.selectionStart = s + open.length;
    ta.selectionEnd = s + open.length + sel.length;
    fireInput(ta);
  }

  /**
   * Liste einfügen (ul oder ol)
   */
  function insertList(ta, type) {
    const s = ta.selectionStart, e = ta.selectionEnd;
    const sel = ta.value.substring(s, e);
    const items = sel ? sel.split('\n').map(l => '[*]' + l.trim()).join('\n') : '[*]Erster Punkt\n[*]Zweiter Punkt';
    const block = '[list' + (type === 'ol' ? '=1' : '') + ']\n' + items + '\n[/list]';
    ta.value = ta.value.substring(0, s) + block + ta.value.substring(e);
    ta.focus();
    fireInput(ta);
  }

  /**
   * Farb-Palette ein-/ausblenden. Klick auf eine Farbe wickelt die Selektion
   * in [color=...]...[/color].
   */
  var COLOR_PALETTE = [
    { name: 'Rot',       value: '#e53935' },
    { name: 'Orange',    value: '#fb8c00' },
    { name: 'Gelb',      value: '#fdd835' },
    { name: 'Grün',      value: '#43a047' },
    { name: 'Türkis',    value: '#00acc1' },
    { name: 'Blau',      value: '#1e88e5' },
    { name: 'Violett',   value: '#8e24aa' },
    { name: 'Pink',      value: '#d81b60' },
    { name: 'Braun',     value: '#6d4c41' },
    { name: 'Grau',      value: '#757575' },
    { name: 'Schwarz',   value: '#000000' },
    { name: 'Weiß',      value: '#ffffff' }
  ];

  function toggleColorPalette(editor, ta, btn) {
    var existing = editor.querySelector('.editor-color-palette');
    if (existing) { existing.remove(); return; }

    var pal = document.createElement('div');
    pal.className = 'editor-color-palette';

    COLOR_PALETTE.forEach(function (c) {
      var sw = document.createElement('button');
      sw.type = 'button';
      sw.className = 'editor-color-cell';
      sw.style.background = c.value;
      sw.title = c.name;
      // weiße/helle Farben brauchen einen Rahmen damit sie sichtbar sind
      if (c.value === '#ffffff' || c.value === '#fdd835') {
        sw.style.border = '1px solid var(--border)';
      }
      sw.addEventListener('click', function () {
        insertAround(ta, '[color=' + c.value + ']', '[/color]', 'farbiger Text');
        pal.remove();
      });
      pal.appendChild(sw);
    });

    // Eigene Farbe (nativer Color-Picker als Zusatz-Option)
    var customWrap = document.createElement('label');
    customWrap.className = 'editor-color-custom';
    customWrap.title = 'Eigene Farbe wählen';
    customWrap.textContent = '🎨';
    var customInput = document.createElement('input');
    customInput.type = 'color';
    customInput.value = '#1e88e5';
    customInput.addEventListener('change', function () {
      insertAround(ta, '[color=' + customInput.value + ']', '[/color]', 'farbiger Text');
      pal.remove();
    });
    customWrap.appendChild(customInput);
    pal.appendChild(customWrap);

    // Palette unter die Toolbar setzen
    editor.querySelector('.editor-toolbar').insertAdjacentElement('afterend', pal);
  }

  /**
   * Smiley-Code einfügen (an Cursor-Position, mit Leerzeichen-Padding)
   */
  function insertSmileyCode(ta, code) {
    const s = ta.selectionStart, e = ta.selectionEnd;
    const before = ta.value.substring(0, s);
    const needSpaceBefore = before.length > 0 && !/\s$/.test(before);
    const insert = (needSpaceBefore ? ' ' : '') + code + ' ';
    ta.value = before + insert + ta.value.substring(e);
    ta.focus();
    ta.selectionStart = ta.selectionEnd = s + insert.length;
    fireInput(ta);
  }

  /**
   * input-Event manuell feuern für Live-Vorschau-Listener
   */
  function fireInput(ta) {
    ta.dispatchEvent(new Event('input', { bubbles: true }));
  }

  /**
   * Smiley-Picker anzeigen oder ausblenden
   */
  function toggleSmileyGrid(editor, ta) {
    let grid = editor.querySelector('.editor-smiley-grid');
    if (grid) {
      grid.remove();
      return;
    }
    const smileys = window.FORUM_SMILEYS || {};
    if (Object.keys(smileys).length === 0) {
      // Keine Smileys verfügbar - stillschweigend nichts tun
      return;
    }

    grid = document.createElement('div');
    grid.className = 'editor-smiley-grid';
    Object.entries(smileys).forEach(([code, emoji]) => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'editor-smiley-btn';
      btn.textContent = emoji;
      btn.title = code;
      btn.addEventListener('click', () => {
        insertSmileyCode(ta, code);
        grid.remove();
      });
      grid.appendChild(btn);
    });
    // Vor dem Textarea einsetzen (sichtbar zwischen Toolbar und Textarea)
    editor.querySelector('.editor-toolbar').insertAdjacentElement('afterend', grid);
  }

  /**
   * Live-Vorschau toggeln
   */
  let previewTimers = new WeakMap();
  function togglePreview(editor, ta) {
    const previewBox = editor.querySelector('.editor-preview');
    const previewBody = editor.querySelector('.editor-preview-body');
    const btn = editor.querySelector('[data-action="preview"]');
    if (!previewBox || !previewBody) return;

    const showing = !previewBox.hasAttribute('hidden');
    if (showing) {
      previewBox.setAttribute('hidden', '');
      btn.classList.remove('active');
      return;
    }
    previewBox.removeAttribute('hidden');
    btn.classList.add('active');
    refreshPreview(ta, previewBody);

    // Bei Tipp-Eingaben Vorschau aktualisieren (debounced)
    if (!ta.dataset.previewWired) {
      ta.dataset.previewWired = '1';
      ta.addEventListener('input', () => {
        if (previewBox.hasAttribute('hidden')) return;
        clearTimeout(previewTimers.get(ta));
        previewTimers.set(ta, setTimeout(() => refreshPreview(ta, previewBody), 400));
      });
    }
  }

  /**
   * Vorschau-AJAX zum Server
   */
  function refreshPreview(ta, target) {
    // Leerer Text → freundlicher Hinweis statt Server-Request
    if (!ta.value.trim()) {
      target.innerHTML = '<em style="color:var(--text-muted)">Schreibe etwas, um die Vorschau zu sehen…</em>';
      return;
    }
    target.innerHTML = '<em style="color:var(--text-muted)">Lade Vorschau…</em>';
    const fd = new FormData();
    fd.append('content', ta.value);
    fetch('/api/preview.php', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(d => {
        if (d && d.html) target.innerHTML = d.html;
        else if (d && d.error) target.textContent = 'Fehler: ' + d.error;
        else target.innerHTML = '<em style="color:var(--text-muted)">Schreibe etwas, um die Vorschau zu sehen…</em>';
      })
      .catch(() => {
        target.textContent = 'Vorschau konnte nicht geladen werden.';
      });
  }

  /**
   * Klick-Handler für alle Toolbar-Buttons
   */
  function handleToolbarClick(editor, ta, btn) {
    const action = btn.dataset.action;
    switch (action) {
      case 'bb':
        wrapSelection(ta, btn.dataset.tag);
        break;
      case 'insert-raw':
        insertAround(ta, btn.dataset.raw || '', '', '');
        break;
      case 'list':
        insertList(ta, btn.dataset.list || 'ul');
        break;
      case 'link': {
        const url = prompt('URL eingeben:', 'https://');
        if (url) insertAround(ta, '[url=' + url + ']', '[/url]', 'Linktext');
        break;
      }
      case 'image': {
        const url = prompt('Bild-URL eingeben:', 'https://');
        if (url) insertAround(ta, '[img]' + url, '[/img]', '');
        // Image-Tag ist self-contained, kein Selection-Wrap nötig
        // Cursor hinter [/img] setzen
        break;
      }
      case 'smileys':
        toggleSmileyGrid(editor, ta);
        break;
      case 'color':
        toggleColorPalette(editor, ta, btn);
        break;
      case 'preview':
        togglePreview(editor, ta);
        break;
    }
  }

  /**
   * Keyboard-Shortcuts: Ctrl+B/I/U
   */
  function handleKeyboard(ta, ev) {
    if (!(ev.ctrlKey || ev.metaKey)) return;
    let tag = null;
    if (ev.key === 'b' || ev.key === 'B') tag = 'b';
    else if (ev.key === 'i' || ev.key === 'I') tag = 'i';
    else if (ev.key === 'u' || ev.key === 'U') tag = 'u';
    if (tag) {
      ev.preventDefault();
      wrapSelection(ta, tag);
    }
  }

  /**
   * Einen Editor initialisieren
   */
  function initEditor(editor) {
    if (editor.dataset.editorInit === '1') return;
    editor.dataset.editorInit = '1';

    const ta = editor.querySelector('.editor-textarea');
    if (!ta) return;

    // Toolbar-Button-Klicks
    editor.querySelectorAll('.editor-btn').forEach(btn => {
      btn.addEventListener('click', () => handleToolbarClick(editor, ta, btn));
    });

    // Keyboard-Shortcuts
    ta.addEventListener('keydown', ev => handleKeyboard(ta, ev));

    // Vorschau standardmäßig aktiviert (User kann ausblenden per Klick)
    // Etwas verzögert, damit der DOM komplett initialisiert ist und ein evtl.
    // vorhandener Entwurf schon eingespielt wurde
    setTimeout(() => togglePreview(editor, ta), 50);
  }

  // Auto-Init bei DOMContentLoaded
  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.editor[data-editor-id]').forEach(initEditor);
  });

  // Manuell aufrufbar (für dynamisch hinzugefügte Editoren)
  window.ForumixEditor = { init: initEditor };
})();
