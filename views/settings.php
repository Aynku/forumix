<?php if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); } ?>
<?php /** @var array $profile; @var array $sessions; @var ?int $currentSessionId; @var string $activeTab; @var string $error */ ?>

<div class="section-heading">⚙️ Einstellungen</div>

<!-- ── Tab-Navigation ────────────────────────────────── -->
<div class="settings-tabs">
  <a href="<?= e(FORUM_URL) ?>/settings.php?tab=profile"
     class="settings-tab <?= $activeTab === 'profile' ? 'active' : '' ?>">
    👤 Profil
  </a>
  <a href="<?= e(FORUM_URL) ?>/settings.php?tab=security"
     class="settings-tab <?= $activeTab === 'security' ? 'active' : '' ?>">
    🔐 Sicherheit
  </a>
  <a href="<?= e(FORUM_URL) ?>/settings.php?tab=sessions"
     class="settings-tab <?= $activeTab === 'sessions' ? 'active' : '' ?>">
    💻 Sitzungen
    <?php if (count($sessions) > 0): ?>
      <span class="settings-tab-badge"><?= count($sessions) ?></span>
    <?php endif; ?>
  </a>
  <a href="<?= e(FORUM_URL) ?>/settings.php?tab=preferences"
     class="settings-tab <?= $activeTab === 'preferences' ? 'active' : '' ?>">
    🎨 Darstellung
  </a>
  <a href="<?= e(FORUM_URL) ?>/settings.php?tab=danger"
     class="settings-tab <?= $activeTab === 'danger' ? 'active' : '' ?>">
    ⚠️ Konto
  </a>
</div>

<?php if (!empty($error)): ?>
  <div class="flash flash-error" style="margin-bottom:14px">❌ <?= e($error) ?></div>
<?php endif; ?>

<!-- ════════════════ TAB: Profil ════════════════ -->
<?php if ($activeTab === 'profile'): ?>
<div class="form-card">
  <p style="font-size:13px;color:var(--text-muted);margin-bottom:18px">
    So sehen andere User dein Profil. Änderungen werden sofort wirksam.
    <br>
    <a href="<?= e(FORUM_URL) ?>/profile.php?id=<?= (int) $profile['user_id'] ?>" style="color:var(--color-accent)">
      → Mein öffentliches Profil anschauen
    </a>
  </p>

  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
    <input type="hidden" name="action" value="save_profile">

    <div class="form-row">
      <label>🖼️ Avatar</label>
      <div class="media-edit-row">
        <img src="<?= e(avatar_url($profile)) ?>" alt="Aktuelles Avatar"
             class="media-edit-preview avatar-preview zoomable-avatar"
             data-username="<?= e($profile['username']) ?>">
        <div class="media-edit-controls">
          <input type="file" name="avatar" accept="image/*">
          <div class="form-hint">Max. 512 KB · JPEG/PNG/GIF/WebP</div>
          <?php if (!empty($profile['avatar'])): ?>
            <label class="checkbox-line" style="margin-top:6px">
              <input type="checkbox" name="delete_avatar" value="1">
              <span>🗑️ Aktuelles Avatar entfernen (zurück zum Standard-Bild)</span>
            </label>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="form-row">
      <label>🎨 Profilbanner</label>
      <div class="media-edit-row">
        <?php if (!empty($profile['profile_banner'])): ?>
          <img src="<?= e(FORUM_URL) ?>/uploads/avatars/<?= e(rawurlencode($profile['profile_banner'])) ?>"
               alt="Aktueller Banner" class="media-edit-preview banner-preview">
        <?php else: ?>
          <div class="media-edit-preview banner-preview media-empty">Kein Banner</div>
        <?php endif; ?>
        <div class="media-edit-controls">
          <input type="file" name="banner" accept="image/*">
          <div class="form-hint">Max. 2 MB · ideal 1200×300px</div>
          <?php if (!empty($profile['profile_banner'])): ?>
            <label class="checkbox-line" style="margin-top:6px">
              <input type="checkbox" name="delete_banner" value="1">
              <span>🗑️ Aktuellen Banner entfernen</span>
            </label>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="form-row">
      <label>Signatur (BBCode erlaubt)</label>
      <textarea name="signature" rows="3"><?= e($profile['signature'] ?? '') ?></textarea>
      <div class="form-hint">Wird unter deinen Beiträgen angezeigt.</div>
    </div>

    <hr style="border:none;border-top:1px solid var(--border);margin:24px 0">
    <h3 style="font-size:14px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.6px;margin:0 0 6px">
      Persönliche Angaben
    </h3>
    <p style="font-size:12px;color:var(--text-muted);margin:0 0 18px;line-height:1.6">
      Alle Felder sind optional. Pro Feld kannst du wählen wer es sehen darf:
      <span style="white-space:nowrap">🌐 Öffentlich</span> ·
      <span style="white-space:nowrap">🔒 Nur Mitglieder</span> ·
      <span style="white-space:nowrap">🔐 Nur ich</span>
    </p>

    <?php
      $schema  = profile_field_schema();
      $privacy = profile_privacy_settings($profile);
      foreach ($schema as $key => $def):
        $val  = $profile[$def['column']] ?? '';
        $priv = $privacy[$key] ?? 'public';
    ?>
      <div class="form-row profile-field-row">
        <div class="profile-field-main">
          <label><?= $def['icon'] ?> <?= e($def['label']) ?></label>
          <?php if ($def['type'] === 'select'): ?>
            <select name="profile_<?= e($key) ?>">
              <?php foreach ($def['options'] as $optVal => $optLabel): ?>
                <option value="<?= e($optVal) ?>" <?= (string) $val === (string) $optVal ? 'selected' : '' ?>>
                  <?= e($optLabel) ?>
                </option>
              <?php endforeach; ?>
            </select>
          <?php elseif ($def['type'] === 'date'): ?>
            <input type="date" name="profile_<?= e($key) ?>"
                   value="<?= e($val ?: '') ?>"
                   max="<?= e(date('Y-m-d')) ?>">
          <?php elseif ($def['type'] === 'textarea'): ?>
            <textarea name="profile_<?= e($key) ?>" rows="3"
                      placeholder="<?= e($def['placeholder'] ?? '') ?>"><?= e($val ?? '') ?></textarea>
          <?php else: ?>
            <input type="text" name="profile_<?= e($key) ?>"
                   value="<?= e($val ?? '') ?>"
                   maxlength="<?= (int) ($def['max'] ?? 100) ?>"
                   placeholder="<?= e($def['placeholder'] ?? '') ?>">
          <?php endif; ?>
        </div>
        <div class="profile-field-privacy">
          <label style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.4px;margin-bottom:6px">
            Sichtbar für
          </label>
          <select name="privacy_<?= e($key) ?>">
            <option value="public"  <?= $priv === 'public'  ? 'selected' : '' ?>>🌐 Öffentlich</option>
            <option value="members" <?= $priv === 'members' ? 'selected' : '' ?>>🔒 Mitglieder</option>
            <option value="private" <?= $priv === 'private' ? 'selected' : '' ?>>🔐 Nur ich</option>
          </select>
        </div>
      </div>
    <?php endforeach; ?>

    <button type="submit" class="btn btn-primary">💾 Profil speichern</button>
  </form>
</div>
<?php endif; ?>

<!-- ════════════════ TAB: Sicherheit ════════════════ -->
<?php if ($activeTab === 'security'): ?>
<div class="form-card">
  <h3 style="margin-top:0">✉️ E-Mail-Adresse</h3>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
    <input type="hidden" name="action" value="save_profile">

    <div class="form-row">
      <label>E-Mail-Adresse</label>
      <input type="email" name="email" value="<?= e($profile['email']) ?>" required>
      <div class="form-hint">Wird für Passwort-Reset und Benachrichtigungen verwendet.</div>
    </div>

    <hr style="border:none;border-top:1px solid var(--border);margin:20px 0">

    <h3>🔑 Passwort ändern</h3>
    <div class="form-row">
      <label>Neues Passwort</label>
      <input type="password" name="new_password" autocomplete="new-password">
      <div class="form-hint">Leer lassen = nicht ändern · Mindestens 6 Zeichen<br>
        ⚠️ Bei Passwort-Änderung werden alle anderen Geräte abgemeldet.
      </div>
    </div>

    <button type="submit" class="btn btn-primary">💾 Sicherheits-Einstellungen speichern</button>
  </form>
</div>

<div class="form-card" style="margin-top:16px">
  <h3 style="margin-top:0">🔐 Zwei-Faktor-Authentifizierung</h3>

  <?php if (!empty($newBackupCodes)): ?>
    <!-- Frisch aktiviert: Backup-Codes EINMALIG zeigen -->
    <div class="card" style="margin-bottom:16px;padding:16px;border:2px solid #4a9d4a;background:rgba(74,157,74,0.06)">
      <h4 style="font-size:14px;margin:0 0 10px;color:#4a9d4a">📋 Deine Backup-Codes — JETZT speichern!</h4>
      <p style="font-size:12px;color:var(--text-muted);margin-bottom:12px;line-height:1.6">
        Bewahre diese Codes <strong>sicher</strong> auf (Passwort-Manager, ausdrucken).
        Wenn du deine Authenticator-App verlierst, kannst du dich damit einloggen.
        <strong>Jeder Code ist nur einmal verwendbar.</strong> Diese Liste wird nicht erneut angezeigt!
      </p>
      <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:6px;font-family:var(--font-mono)">
        <?php foreach ($newBackupCodes as $bc): ?>
          <code style="padding:10px;background:var(--bg-surface2);border:1px solid var(--border);border-radius:4px;text-align:center;font-size:14px;letter-spacing:1px"><?= e($bc) ?></code>
        <?php endforeach; ?>
      </div>
      <p style="margin-top:12px;font-size:12px">
        <button type="button" onclick="
          const codes = <?= json_encode(implode("\n", $newBackupCodes)) ?>;
          navigator.clipboard.writeText(codes).then(() => this.textContent='✓ Kopiert!');
        " class="btn btn-ghost btn-sm">📋 Alle Codes kopieren</button>
      </p>
    </div>
  <?php endif; ?>

  <?php if ($has2fa): ?>
    <!-- 2FA ist aktiv → Deaktivieren-Form -->
    <p style="font-size:13px;color:var(--text-muted);margin-bottom:14px">
      ✅ 2FA ist <strong>aktiv</strong>. Du wirst bei jedem Login nach einem 6-stelligen Code gefragt.
      Falls du die Authenticator-App verlierst, kannst du dich mit einem Backup-Code einloggen.
    </p>
    <details>
      <summary style="cursor:pointer;font-size:13px;color:var(--text-muted);padding:8px 0">
        🔓 2FA deaktivieren (mit Passwort-Bestätigung)
      </summary>
      <form method="post" style="margin-top:12px"
            onsubmit="return confirm('2FA wirklich deaktivieren? Dein Konto ist danach nur noch durch das Passwort geschützt.')">
        <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
        <input type="hidden" name="action" value="disable_2fa">
        <div class="form-row">
          <label>Dein aktuelles Passwort zur Bestätigung</label>
          <input type="password" name="password_confirm" required autocomplete="current-password">
        </div>
        <button type="submit" class="btn btn-danger btn-sm">🔓 2FA deaktivieren</button>
      </form>
    </details>

  <?php else: ?>
    <!-- 2FA noch nicht aktiv → Setup-UI -->
    <p style="font-size:13px;color:var(--text-muted);margin-bottom:14px">
      Mit 2FA ist dein Konto deutlich besser geschützt. Bei jedem Login wird zusätzlich
      ein 6-stelliger Code aus einer Authenticator-App auf deinem Handy abgefragt.
    </p>

    <details>
      <summary style="cursor:pointer;font-size:14px;color:var(--color-accent);font-weight:600;padding:8px 0">
        📲 2FA jetzt einrichten
      </summary>

      <div style="padding:14px 0">
        <p style="font-size:13px;color:var(--text-muted);margin-bottom:14px">
          <strong>Schritt 1:</strong> Scanne den QR-Code mit einer Authenticator-App
          (Google Authenticator, Authy, 1Password, …) oder gib den Secret-Code manuell ein.
        </p>

        <div style="text-align:center;margin:18px 0">
          <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=<?= e(urlencode($otpUrl)) ?>"
               alt="QR Code"
               style="background:#fff;padding:10px;border-radius:8px;border:1px solid var(--border)">
        </div>

        <div style="background:var(--bg-surface2);border:1px solid var(--border);border-radius:6px;padding:12px;margin-bottom:16px;text-align:center">
          <div style="font-size:11px;color:var(--text-muted);margin-bottom:4px">Secret-Code (für manuelle Eingabe):</div>
          <code style="font-size:14px;font-weight:600;letter-spacing:1px;word-break:break-all"><?= e($newSecret) ?></code>
        </div>

        <p style="font-size:13px;color:var(--text-muted);margin-bottom:8px">
          <strong>Schritt 2:</strong> Gib den 6-stelligen Code aus der App ein, um zu bestätigen.
        </p>

        <form method="post">
          <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
          <input type="hidden" name="action" value="enable_2fa">
          <input type="hidden" name="secret" value="<?= e($newSecret) ?>">
          <div class="form-row">
            <label>6-stelliger Code zur Bestätigung</label>
            <input type="text" name="code" required autofocus inputmode="numeric"
                   maxlength="6" pattern="[0-9]{6}"
                   style="font-family:var(--font-mono);font-size:18px;letter-spacing:3px;text-align:center;max-width:180px"
                   placeholder="000000">
          </div>
          <button type="submit" class="btn btn-primary">✓ 2FA aktivieren</button>
        </form>
      </div>
    </details>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- ════════════════ TAB: Sessions ════════════════ -->
<?php if ($activeTab === 'sessions'): ?>
<div class="form-card" id="sessions">
  <h3 style="margin-top:0">💻 Aktive Sitzungen (<?= count($sessions) ?>)</h3>
  <?php if (empty($sessions)): ?>
    <p style="font-size:13px;color:var(--text-muted);text-align:center;padding:30px">
      Keine aktiven „Angemeldet bleiben"-Sitzungen.<br>
      Du wirst beim nächsten Browser-Schließen ausgeloggt.
    </p>
  <?php else: ?>
    <p style="font-size:13px;color:var(--text-muted);margin-bottom:14px">
      Diese Geräte sind aktuell mit „Angemeldet bleiben" eingeloggt. Wenn dir eine Sitzung
      nicht bekannt vorkommt – sofort abmelden!
    </p>

    <div class="sessions-list">
      <?php foreach ($sessions as $s):
          $isCurrent = (int) $s['token_id'] === ($currentSessionId ?? -1);
          $lastUsed  = strtotime($s['last_used']);
          $created   = strtotime($s['created_at']);
          $expires   = strtotime($s['expires_at']);
      ?>
        <div class="session-row <?= $isCurrent ? 'session-current' : '' ?>">
          <div class="session-info">
            <strong><?= e(remember_format_user_agent($s['user_agent'] ?? '')) ?></strong>
            <?php if ($isCurrent): ?>
              <span class="session-badge">📍 Diese Sitzung</span>
            <?php endif; ?>
            <div class="session-meta">
              🌐 IP: <code><?= e($s['ip_address'] ?? '–') ?></code>
              · Erstellt: <?= e(date('d.m.Y', $created)) ?>
              · Zuletzt: <?= e(date('d.m.Y H:i', $lastUsed)) ?>
              · Läuft ab: <?= e(date('d.m.Y', $expires)) ?>
            </div>
          </div>
          <?php if (!$isCurrent): ?>
            <form method="post" style="margin:0" onsubmit="return confirm('Dieses Gerät abmelden?')">
              <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
              <input type="hidden" name="session_action" value="revoke_one">
              <input type="hidden" name="token_id" value="<?= (int) $s['token_id'] ?>">
              <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--color-danger)">🚪 Abmelden</button>
            </form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>

    <?php if (count($sessions) > 1): ?>
      <div style="margin-top:12px;padding-top:12px;border-top:1px solid var(--border)">
        <form method="post" style="margin:0" onsubmit="return confirm('Alle ANDEREN Geräte abmelden? (Diese Sitzung bleibt aktiv)')">
          <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
          <input type="hidden" name="session_action" value="revoke_others">
          <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--color-warning)">
            🚪 Alle anderen Geräte abmelden
          </button>
        </form>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- ════════════════ TAB: Darstellung ════════════════ -->
<?php if ($activeTab === 'preferences'): ?>
<div class="form-card">
  <h3 style="margin-top:0">🎨 Darstellung & Benachrichtigungen</h3>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
    <input type="hidden" name="action" value="save_profile">

    <div class="form-row">
      <label>Theme</label>
      <select name="theme">
        <option value="auto"  <?= ($profile['theme'] ?? 'auto') === 'auto'  ? 'selected' : '' ?>>🌓 Automatisch</option>
        <option value="dark"  <?= ($profile['theme'] ?? 'auto') === 'dark'  ? 'selected' : '' ?>>🌙 Dark</option>
        <option value="light" <?= ($profile['theme'] ?? 'auto') === 'light' ? 'selected' : '' ?>>☀️ Light</option>
      </select>
    </div>

    <div class="form-row">
      <label>
        <input type="checkbox" name="notify_email" value="1" <?= !empty($profile['notify_email']) ? 'checked' : '' ?>>
        E-Mail-Benachrichtigungen
      </label>
      <div class="form-hint">Erhalte zusätzlich eine E-Mail bei Antworten und PNs (Server-Versand erforderlich).</div>
    </div>

    <button type="submit" class="btn btn-primary">💾 Darstellung speichern</button>
  </form>
</div>
<?php endif; ?>

<!-- ════════════════ TAB: Konto / Danger ════════════════ -->
<?php if ($activeTab === 'danger'):
    $_canSelfDelete = function_exists('can_user_delete_self')
        && can_user_delete_self((int) $profile['user_id'])['ok'];
?>
<div class="form-card" style="border:1px solid #dc262640" id="danger-zone">
  <h3 style="margin-top:0;color:#dc2626">💀 Account löschen</h3>
  <?php if ($_canSelfDelete): ?>
    <p style="font-size:13px;color:var(--text-muted);margin-bottom:14px;line-height:1.6">
      Wenn du dein Konto nicht mehr nutzen möchtest, kannst du es endgültig löschen. Deine Beiträge
      bleiben erhalten (sonst würden Diskussionen unverständlich), erscheinen aber unter
      „Gelöschter Benutzer". Persönliche Daten (E-Mail, Avatar, Bio, Signatur) werden entfernt.
      <strong>Diese Aktion ist unwiderruflich.</strong>
    </p>
    <a href="<?= e(FORUM_URL) ?>/delete_account.php" class="btn btn-danger btn-sm">
      💀 Meinen Account löschen…
    </a>
  <?php else: ?>
    <p style="font-size:13px;color:var(--text-muted);line-height:1.6">
      Dein Konto kann momentan nicht gelöscht werden (z.B. weil du der einzige Owner bist
      oder ein geschützter Account-Typ vorliegt). Wende dich bei Fragen an die Admins.
    </p>
  <?php endif; ?>
</div>
<?php endif; ?>
