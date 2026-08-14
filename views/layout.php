<?php if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); } ?>
<?php /** @var string $content */
// Theme-Auflösung in dieser Reihenfolge:
// 1. Eingeloggter User: User-Theme aus DB
// 2. Gast: forum_theme-Cookie
// 3. Fallback: Forum-Default aus Settings (oder "dark")
$userTheme = '';
if (!empty($currentUser['theme'])) {
    $userTheme = $currentUser['theme'];
} elseif (!empty($_COOKIE['forum_theme'])) {
    $userTheme = $_COOKIE['forum_theme'];
}
if (!in_array($userTheme, ['light', 'dark'], true)) {
    // 'auto' oder leer → Forum-Default
    try {
        $defTheme = get_setting('forum_default_theme');
        $themeAttr = in_array($defTheme, ['light', 'dark'], true) ? $defTheme : 'dark';
    } catch (Exception $e) {
        $themeAttr = 'dark';
    }
} else {
    $themeAttr = $userTheme;
}

// Akzentfarbe
$accentHex = forum_accent_color();
// Helle Variante für `--color-accent-soft` (rgba mit Alpha) – aus dem Hex berechnen
$accentSoft = (function($hex) {
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    }
    if (strlen($hex) !== 6) return 'rgba(99,102,241,0.15)';
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    return "rgba($r, $g, $b, 0.15)";
})($accentHex);

// Theme-Settings holen
$theme = theme_settings();

// Custom CSS aus Admin-Settings (Admin → Design → Erweitert)
$customCss = '';
try {
    $customCss = trim((string) get_setting('forum_custom_css'));
} catch (Exception $e) {}

// ── Live-Preview-Override (für ?preview=1) ─────────────────────────
// Im Design-Bereich kann das Admin live Werte testen ohne zu speichern
$isPreview = false;
if (isset($_GET['preview']) && is_admin() && !empty($_SESSION['theme_preview'])) {
    $isPreview = true;
    foreach ($_SESSION['theme_preview'] as $k => $v) {
        $theme[$k] = $v;
    }
}

$radius   = theme_radius_preset($theme['theme_radius_preset']);
$fontStack = theme_font_stack($theme['theme_font_family'], $theme['theme_font_custom']);
$densityF = theme_density_factor($theme['theme_density']);
$fontSize = (int) ($theme['theme_font_size_base'] ?? 14);
$maxWidth = (int) ($theme['theme_max_width'] ?? 1200);

$forumName = forum_name();
?>
<!DOCTYPE html>
<html lang="de" data-theme="<?= e($themeAttr) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?> – <?= e($forumName) ?></title>
  <link rel="stylesheet" href="<?= e(FORUM_URL) ?>/assets/style.css?v=<?= e(forum_version()) ?>">
  <link rel="alternate" type="application/rss+xml" title="<?= e($forumName) ?> RSS" href="<?= e(FORUM_URL) ?>/rss.php">
  <?php
    // Schriften liegen lokal unter assets/fonts/ — kein Aufruf bei Google,
    // damit keine Besucher-IP dorthin abfliesst (DSGVO).
    $localFonts = ['inter', 'roboto', 'open-sans', 'lato', 'nunito', 'poppins', 'chakra-petch'];
    if (in_array($theme['theme_font_family'], $localFonts, true)):
  ?>
    <link rel="stylesheet" href="<?= e(FORUM_URL) ?>/assets/fonts.css?v=<?= e(forum_version()) ?>">
  <?php endif; ?>
  <style>
    /* ─── Theme-Tokens aus DB-Settings ─── */
    :root {
      --color-accent: #<?= e($accentHex) ?>;
      --color-accent-soft: <?= e($accentSoft) ?>;
      --color-success: #<?= e($theme['theme_color_success']) ?>;
      --color-warning: #<?= e($theme['theme_color_warning']) ?>;
      --color-danger:  #<?= e($theme['theme_color_danger']) ?>;

      --font: <?= $fontStack ?>;
      --font-family: <?= $fontStack ?>; /* Alias für Layout-Kompatibilität */
      --font-size-base: <?= $fontSize ?>px;

      --radius-sm: <?= e($radius['sm']) ?>;
      --radius-md: <?= e($radius['md']) ?>;
      --radius-lg: <?= e($radius['lg']) ?>;

      --density: <?= $densityF ?>;
      --max-width: <?= $maxWidth ?>px;
    }
    html[data-theme="dark"] {
      --bg-body:       #<?= e($theme['theme_color_bg_body']) ?>;
      --bg-surface:    #<?= e($theme['theme_color_bg_surface']) ?>;
      --bg-surface2:   #<?= e($theme['theme_color_bg_surface2']) ?>;
      --text-base:     #<?= e($theme['theme_color_text_base']) ?>;
      --text-muted:    #<?= e($theme['theme_color_text_muted']) ?>;
      --text-faint:    #<?= e($theme['theme_color_text_faint']) ?>;
      --border:        #<?= e($theme['theme_color_border']) ?>;
      --border-strong: #<?= e($theme['theme_color_border_strong']) ?>;
    }
    html[data-theme="light"] {
      --bg-body:       #<?= e($theme['theme_color_bg_body_light']) ?>;
      --bg-surface:    #<?= e($theme['theme_color_bg_surface_light']) ?>;
      --bg-surface2:   #<?= e($theme['theme_color_bg_surface2_light']) ?>;
      --text-base:     #<?= e($theme['theme_color_text_base_light']) ?>;
      --text-muted:    #<?= e($theme['theme_color_text_muted_light']) ?>;
      --text-faint:    #<?= e($theme['theme_color_text_faint_light']) ?>;
      --border:        #<?= e($theme['theme_color_border_light']) ?>;
      --border-strong: #<?= e($theme['theme_color_border_strong_light']) ?>;
    }
    body { font-family: var(--font-family); font-size: var(--font-size-base); }
    #app {
      max-width: var(--max-width);
      width: 100%;
      margin: 0 auto;  /* zentriert horizontal, falls max-width kleiner als Viewport */
    }
    <?php if ($customCss): ?>
    /* ─── Custom CSS aus Admin-Settings ─── */
    <?= $customCss ?>
    <?php endif; ?>
  </style>
</head>
<body class="header-<?= e($theme['theme_header_style'] ?? 'default') ?> density-<?= e($theme['theme_density'] ?? 'normal') ?>">
<div id="app">

  <header id="site-header">

    <!-- ── ZEILE 0: Logo-Banner ── -->
    <div class="header-banner">
      <div class="header-inner">
        <a href="<?= e(FORUM_URL) ?>/index.php" id="forum-logo-banner" title="<?= e($forumName) ?>">
          <?= forum_logo_render($forumName) ?>
        </a>
        <?php $slogan = '';
              try { $slogan = (string) get_setting('forum_slogan'); } catch (Exception $e) {} ?>
        <?php if ($slogan): ?>
          <div class="header-slogan"><?= e($slogan) ?></div>
        <?php endif; ?>
      </div>
    </div>

    <!-- ── ZEILE 1: Suche, User-Bereich ── -->
    <div class="header-top">
      <div class="header-inner">
        <form id="header-search" method="get" action="<?= e(FORUM_URL) ?>/search.php" role="search" autocomplete="off">
          <div id="quicksearch-wrap">
            <input type="text" name="q" id="quicksearch-input"
                   placeholder="🔍 <?= e(t('search')) ?>…"
                   autocomplete="off"
                   aria-label="<?= e(t('search')) ?>"
                   aria-autocomplete="list"
                   aria-controls="quicksearch-dropdown">
            <div id="quicksearch-dropdown" role="listbox" aria-hidden="true"></div>
          </div>
          <button type="submit"><?= e(t('search')) ?></button>
        </form>

        <div id="header-user">
          <?php if ($currentUser): ?>
            <a href="<?= e(FORUM_URL) ?>/notifications.php" class="user-icon" title="<?= e(t('notifications')) ?>">
              🔔<?php if ($unreadNotifications > 0): ?><span class="nav-badge"><?= (int) $unreadNotifications ?></span><?php endif; ?>
            </a>
            <a href="<?= e(FORUM_URL) ?>/pm.php" class="user-icon" title="<?= e(t('messages')) ?>">
              📬<?php if ($unreadPMs > 0): ?><span class="nav-badge"><?= (int) $unreadPMs ?></span><?php endif; ?>
            </a>

            <div class="user-menu">
              <button type="button" class="user-menu-toggle" aria-haspopup="true" aria-expanded="false">
                <img src="<?= e(avatar_url($currentUser)) ?>" alt="" class="user-menu-avatar" width="26" height="26">
                <span class="user-menu-name"><?= e($currentUser['username']) ?></span>
                <span style="font-size:10px;opacity:.6">▾</span>
              </button>
              <div class="user-menu-dropdown">
                <a href="<?= e(FORUM_URL) ?>/profile.php?id=<?= (int) $currentUser['user_id'] ?>">👤 Mein Profil</a>
                <a href="<?= e(FORUM_URL) ?>/settings.php">⚙️ Einstellungen</a>
                <div class="user-menu-divider"></div>
                <?php
                  // Mod-Link: für Admins, Mods, Owner und Board-Mods
                  $showModLink = $isAdmin || $isMod || !empty($currentUser['is_owner']);
                  if (!$showModLink) {
                      try {
                          $showModLink = !empty(mod_for_boards((int) $currentUser['user_id']));
                      } catch (Exception $e) {}
                  }
                ?>
                <?php if ($showModLink): ?>
                  <a href="<?= e(FORUM_URL) ?>/mod.php">🛡️ Moderator-Bereich</a>
                <?php endif; ?>
                <?php if ($isAdmin): ?>
                  <a href="<?= e(FORUM_URL) ?>/admin/">⚙️ <?= e(t('admin')) ?></a>
                <?php endif; ?>
                <?php if ($showModLink || $isAdmin): ?>
                  <div class="user-menu-divider"></div>
                <?php endif; ?>
                <button type="button" onclick="toggleTheme(); document.querySelector('.user-menu')?.classList.remove('open');" class="user-menu-theme-btn">
                  <span id="theme-icon">🌙</span> <span>Theme wechseln</span>
                </button>
                <div class="user-menu-divider"></div>
                <a href="<?= e(FORUM_URL) ?>/logout.php" class="user-menu-logout">🚪 <?= e(t('logout')) ?></a>
              </div>
            </div>
          <?php else: ?>
            <a href="<?= e(FORUM_URL) ?>/login.php" class="btn btn-ghost btn-sm"><?= e(t('login')) ?></a>
            <a href="<?= e(FORUM_URL) ?>/register.php" class="btn btn-primary btn-sm"><?= e(t('register')) ?></a>
            <button type="button" onclick="toggleTheme()" class="user-icon" title="<?= e(t('theme')) ?>">🌙</button>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- ── ZEILE 2: Hauptnavigation ── -->
    <nav class="header-nav-main">
      <div class="header-inner">
        <button type="button" id="mobile-nav-toggle" class="mobile-nav-toggle" aria-label="Menü">
          <span class="hamburger-icon">☰</span>
          <span class="hamburger-label">Menü</span>
        </button>
        <div class="header-nav-items" id="header-nav-items">
          <?php
            $script = $_GET['_page'] ?? '';
            if ($script === '') $script = 'home';
            $isHome = in_array($script, ['home', 'index', 'board', 'thread', 'new_thread', 'edit_post', 'tag'], true);
          ?>
          <a href="<?= e(FORUM_URL) ?>/index.php" class="<?= $isHome ? 'active' : '' ?>">
            📋 Forum
          </a>
          <a href="<?= e(FORUM_URL) ?>/members.php" class="<?= ($script === 'members' || $script === 'online') ? 'active' : '' ?>">
            👥 Mitglieder
          </a>
          <?php if ($currentUser): ?>
            <a href="<?= e(FORUM_URL) ?>/dashboard.php" class="<?= $script === 'dashboard' ? 'active' : '' ?>">
              📊 Dashboard
            </a>
            <a href="<?= e(FORUM_URL) ?>/bookmarks.php" class="<?= $script === 'bookmarks' ? 'active' : '' ?>">
              🔖 Lesezeichen
            </a>
            <a href="<?= e(FORUM_URL) ?>/achievements.php" class="<?= $script === 'achievements' ? 'active' : '' ?>">
              🏆 Achievements
            </a>
          <?php else: ?>
            <a href="<?= e(FORUM_URL) ?>/search.php?recent=1" class="<?= $script === 'search' ? 'active' : '' ?>">
              📊 Aktuelles
            </a>
          <?php endif; ?>
          <?php if (function_exists('module_enabled') && module_enabled('faq')): ?>
            <a href="<?= e(FORUM_URL) ?>/modules/faq/faq.php">
              ❓ FAQ
            </a>
          <?php endif; ?>
        </div>
      </div>
    </nav>
  </header>

  <?php if (!empty($breadcrumb)): ?>
  <nav id="breadcrumb">
    <div class="bc-inner">
      <a href="<?= e(FORUM_URL) ?>/index.php">🏠 <?= e(t('home')) ?></a>
      <?php foreach ($breadcrumb as $bc): ?>
        <span class="sep">›</span>
        <a href="<?= e($bc['url']) ?>"><?= e($bc['title']) ?></a>
      <?php endforeach; ?>
    </div>
  </nav>
  <?php endif; ?>

  <main>
    <div id="wrapper">
      <div class="page-content">
        <?php if ($flash): ?>
          <div class="flash flash-<?= e($flash['type']) ?>">
            <?php if ($flash['type'] === 'success'): ?>✅<?php elseif ($flash['type'] === 'error'): ?>❌<?php else: ?>ℹ️<?php endif; ?>
            <?= e($flash['msg']) ?>
          </div>
        <?php endif; ?>

        <?= $content ?>
      </div>
    </div>
  </main>

  <footer id="site-footer">
    <?php
      $footerText = '';
      $footerLinks = '';
      try {
          $footerText  = (string) get_setting('forum_footer_text');
          $footerLinks = (string) get_setting('forum_footer_links');
      } catch (Exception $e) {}
    ?>

    <?php /* ── Zeile 1: Copyright ── */ ?>
    <p style="margin-bottom:8px;text-align:center">
      <?php if ($footerText): ?>
        <?= e($footerText) ?>
      <?php else: ?>
        © <?= date('Y') ?> <?= e($forumName) ?> &bull; Alle Rechte vorbehalten
      <?php endif; ?>
    </p>

    <?php /* ── Zeile 2: Links ── */ ?>
    <p style="margin-bottom:8px;text-align:center">
      <a href="<?= e(FORUM_URL) ?>/imprint.php">Impressum</a>
      &bull; <a href="<?= e(FORUM_URL) ?>/privacy.php">Datenschutz</a>
      &bull; <a href="<?= e(FORUM_URL) ?>/terms.php">Nutzungsbedingungen</a>
      &bull; <a href="#" onclick="document.dispatchEvent(new KeyboardEvent('keydown',{key:'?'}));return false;" title="Tastatur-Shortcuts anzeigen">⌨️ Shortcuts</a>
      <?php if (function_exists('module_enabled') && module_enabled('faq')): ?>
        &bull; <a href="<?= e(FORUM_URL) ?>/modules/faq/faq.php">❓ FAQ</a>
      <?php endif; ?>
      <?php if ($footerLinks):
        // Format: "Label1|URL1, Label2|URL2"
        $pairs = array_filter(array_map('trim', explode(',', $footerLinks)));
        foreach ($pairs as $pair):
          $parts = array_map('trim', explode('|', $pair, 2));
          if (count($parts) === 2):
      ?>
        &bull; <a href="<?= e($parts[1]) ?>" target="_blank" rel="noopener"><?= e($parts[0]) ?></a>
      <?php   endif;
        endforeach;
      endif; ?>
    </p>

    <?php /* ── Zeile 3: Version ── */ ?>
    <p style="text-align:center;font-size:11px;color:var(--text-faint)">
      Version <?= e(forum_version()) ?>
    </p>

    <?php
      /* ── Zeile 4: Powered by Forumix ──
         AGPLv3 § 13 verlangt, dass Nutzer einer im Netz betriebenen Fassung
         an den Quelltext kommen. Der Link steht deshalb hier und nicht in
         einer Unterseite. Wer den Code aendert, traegt im Adminbereich unter
         "Quelltext-URL" sein eigenes Repository ein. */
      $sourceUrl = '';
      try { $sourceUrl = trim((string) get_setting('source_url', FORUMIX_SOURCE_URL)); }
      catch (Exception $e) { $sourceUrl = FORUMIX_SOURCE_URL; }
    ?>
    <p class="powered-by" style="margin-top:14px;padding-top:12px;border-top:1px solid var(--border);font-size:13px;color:var(--text-muted);display:flex;align-items:center;justify-content:center;gap:8px;flex-wrap:wrap">
      Powered by
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 220 60" height="24" style="vertical-align:middle"
           role="img" aria-label="Forumix">
        <g transform="translate(2, 4)">
          <path d="M 28 6 L 46 6 Q 51 6 51 11 L 51 25 Q 51 30 46 30 L 44 30 L 44 38 L 36 30 L 28 30 Q 23 30 23 25 L 23 11 Q 23 6 28 6 Z"
                fill="currentColor" opacity="0.55" />
          <path d="M 8 0 L 36 0 Q 42 0 42 6 L 42 22 Q 42 28 36 28 L 18 28 L 10 36 L 10 28 L 8 28 Q 2 28 2 22 L 2 6 Q 2 0 8 0 Z"
                fill="#2563eb" />
          <rect x="8" y="8" width="28" height="3" rx="1.5" fill="#ffffff" />
          <rect x="8" y="14" width="22" height="3" rx="1.5" fill="#ffffff" />
          <rect x="8" y="20" width="18" height="3" rx="1.5" fill="#ffffff" />
        </g>
        <text x="62" y="34" font-family="system-ui, -apple-system, sans-serif" font-size="26" font-weight="700" letter-spacing="-0.5">
          <tspan fill="currentColor">Forum</tspan><tspan fill="#2563eb">ix</tspan>
        </text>
      </svg>
      <?php if ($sourceUrl !== ''): ?>
        <span style="color:var(--text-faint)">·</span>
        <a href="<?= e($sourceUrl) ?>" rel="nofollow noopener" target="_blank"
           style="font-size:12px;color:var(--text-muted)">Quelltext (AGPLv3)</a>
      <?php endif; ?>
    </p>
  </footer>

</div>

<?php
  // Cookie-Banner anzeigen?
  $cookieEnabled = (string) (function_exists('get_setting') ? get_setting('cookie_banner_enabled') : '0');
  if ($cookieEnabled === '1'):
    $cookieText = trim((string) get_setting('cookie_banner_text'));
    if ($cookieText === '') {
      $cookieText = 'Diese Seite nutzt funktionale Cookies (z.B. für den Login). Mit der weiteren Nutzung erklärst du dich damit einverstanden.';
    }
?>
<div id="cookie-banner" class="cookie-banner" hidden>
  <div class="cookie-banner-inner">
    <div class="cookie-banner-text">🍪 <?= e($cookieText) ?></div>
    <button type="button" id="cookie-banner-accept" class="btn btn-primary btn-sm">Verstanden</button>
  </div>
</div>
<?php endif; ?>

<!-- ── AVATAR-LIGHTBOX ─────────────────────────────────────── -->
<div id="avatar-lightbox" class="avatar-lightbox" aria-hidden="true">
  <button type="button" class="avatar-lightbox-close" aria-label="Schließen" title="Schließen (Esc)">✕</button>
  <div class="avatar-lightbox-inner">
    <img id="avatar-lightbox-img" src="" alt="">
    <div class="avatar-lightbox-meta">
      <div id="avatar-lightbox-name"></div>
      <a id="avatar-lightbox-link" href="#" class="btn btn-primary btn-sm">→ Profil ansehen</a>
    </div>
    <div class="avatar-lightbox-hint">ESC zum Schließen · Klick außerhalb</div>
  </div>
</div>

<script>
window.FORUM_URL = <?= json_encode(FORUM_URL) ?>;
window.FORUM_CSRF = <?= json_encode($csrfToken) ?>;
window.FORUM_SMILEYS = <?= json_encode(smileys_picker(), JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="<?= e(FORUM_URL) ?>/assets/forum.js?v=<?= e(forum_version()) ?>"></script>
</body>
</html>
