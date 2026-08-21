<?php if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); } ?>
<?php /** @var string $action */ ?>

<div class="admin-wrap">

  <nav class="admin-sidebar">
    <div class="admin-sidebar-nav">
      <?php
      $pendingApproval     = pending_approval_count();
      $pendingUserApproval = pending_user_approvals_count();
      $activeInvitations   = invitations_active_count();
      $openReports         = open_reports_count();

      $primary = [
          'dashboard' => '📊 Dashboard',
          'approval'  => '🚦 Freigabe' . ($pendingApproval > 0 ? ' <span class="nav-badge">' . $pendingApproval . '</span>' : ''),
          'reports'   => '🚨 Meldungen' . ($openReports > 0 ? ' <span class="nav-badge">' . $openReports . '</span>' : ''),
      ];

      $groups = [
          'users' => [
              'label' => '👥 Benutzer',
              'items' => [
                  'users'           => '👥 Benutzerliste',
                  'groups'          => '👪 Gruppen',
                  'approvals_users' => '🆕 Reg.-Freigaben' . ($pendingUserApproval > 0 ? ' <span class="nav-badge">' . $pendingUserApproval . '</span>' : ''),
                  'invitations'     => '✉️ Einladungen' . ($activeInvitations > 0 ? ' <span class="nav-badge">' . $activeInvitations . '</span>' : ''),
                  'warnings'        => '⚠️ Warnungen',
                  'modlog'          => '📜 Mod-Log',
                  'iplookup'        => '🌐 IP-Verwaltung',
                  'bot_log'         => '🤖 Bot-Verkehr',
              ],
          ],
          'content' => [
              'label' => '📁 Inhalt',
              'items' => [
                  'categories' => '📁 Kategorien',
                  'boards'     => '📋 Foren',
                  'tags'       => '🏷️ Tags',
                  'ranks'      => '🏅 Ränge',
                  'smileys'    => '😀 Smileys',
                  'legal'      => '📄 Seiten',
              ],
          ],
          'appearance' => [
              'label' => '🎨 Darstellung',
              'items' => [
                  'design'    => '🎨 Design',
                  'templates' => '🖼️ Templates',
              ],
          ],
          // System ist, wie die Installation EINGESTELLT ist. Wartung ist, was
          // man an ihr TUT. Backups und Updates standen bei den Einstellungen
          // und gehoeren nach dieser Regel herueber — zumal sie ein Handgriff
          // sind: Sicherung anlegen, Update einspielen, notfalls Wartungsmodus.
          'system' => [
              'label' => '⚙️ System',
              'items' => [
                  'settings'     => '🔧 Einstellungen',
                  'email'        => '📧 E-Mail',
                  'registration' => '📝 Registrierung',
                  'modules'      => '🧩 Module',
              ],
          ],
          'maintenance_group' => [
              'label' => '🛠️ Wartung',
              'items' => [
                  'backups'           => '💾 Backups',
                  'updates'           => '🚀 Updates',
                  'import'            => '📦 Import aus phpBB',
                  'maintenance_mode'  => '🛠️ Wartungsmodus',
                  'maintenance'       => '🧹 Tools',
              ],
          ],
      ];

      // Welcher Group-Header ist aktiv?
      $activeGroup = null;
      foreach ($groups as $gKey => $g) {
          if (isset($g['items'][$action])) {
              $activeGroup = $gKey;
              break;
          }
      }
      ?>

      <?php foreach ($primary as $k => $label): ?>
        <a href="?action=<?= e($k) ?>" class="admin-nav-item <?= $action === $k ? 'active' : '' ?>">
          <?= $label ?>
        </a>
      <?php endforeach; ?>

      <?php foreach ($groups as $gKey => $g): ?>
        <div class="admin-nav-group">
          <button type="button" class="admin-nav-item admin-nav-group-toggle <?= $activeGroup === $gKey ? 'active' : '' ?>">
            <?= $g['label'] ?> <span style="font-size:10px;opacity:.6;margin-left:2px">▾</span>
          </button>
          <div class="admin-nav-group-menu">
            <?php foreach ($g['items'] as $k => $label): ?>
              <a href="?action=<?= e($k) ?>" class="admin-nav-subitem <?= $action === $k ? 'active' : '' ?>">
                <?= $label ?>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </nav>

  <div class="admin-content">

    <?php /* ════════════ DASHBOARD ════════════ */ ?>
    <?php if ($action === 'dashboard'): ?>
      <h2>📊 Dashboard</h2>

      <div class="stat-grid">
        <?php foreach ($stats as $stat): ?>
        <div class="stat-box">
          <div class="stat-icon"><?= $stat['icon'] ?></div>
          <div class="stat-num"><?= number_format((int) $stat['value'], 0, ',', '.') ?></div>
          <div class="stat-label"><?= e($stat['label']) ?></div>
        </div>
        <?php endforeach; ?>
      </div>

      <div class="section-heading">🆕 Neueste Mitglieder</div>
      <div class="card">
        <table class="forum-table">
          <thead><tr><th>Name</th><th>E-Mail</th><th>Registrierungs-IP</th><th>Registriert</th></tr></thead>
          <tbody>
            <?php foreach ($newestUsers as $u): ?>
            <tr>
              <td><a href="<?= e(FORUM_URL) ?>/profile.php?id=<?= (int) $u['user_id'] ?>"><?= e($u['username']) ?></a></td>
              <td style="font-size:12px"><?= e($u['email']) ?></td>
              <td><code style="font-size:12px"><?= e($u['registration_ip'] ?? '–') ?></code></td>
              <td style="font-size:12px;color:var(--text-muted)"><?= e(format_date($u['registered_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="section-heading">🔑 Letzte Logins</div>
      <div class="card">
        <table class="forum-table">
          <thead><tr><th>Benutzer</th><th>IP</th><th>Zeitpunkt</th></tr></thead>
          <tbody>
            <?php if (empty($recentLogins)): ?>
              <tr><td colspan="3" style="text-align:center;color:var(--text-faint);padding:16px">Noch keine Logins protokolliert.</td></tr>
            <?php endif; ?>
            <?php foreach ($recentLogins as $l): ?>
            <tr>
              <td><a href="<?= e(FORUM_URL) ?>/profile.php?id=<?= (int) $l['user_id'] ?>"><?= e($l['username']) ?></a></td>
              <td>
                <a href="?action=iplookup&ip=<?= e(urlencode($l['ip'])) ?>"><code><?= e($l['ip']) ?></code></a>
              </td>
              <td style="font-size:12px;color:var(--text-muted)"><?= e(format_date($l['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

    <?php /* ════════════ BENUTZER ════════════ */ ?>
    <?php elseif ($action === 'users'): ?>
      <h2>👥 Benutzerverwaltung</h2>

      <form method="get" style="margin-bottom:14px">
        <input type="hidden" name="action" value="users">
        <div style="display:flex;gap:8px">
          <input type="text" name="q" value="<?= e($searchQuery ?? '') ?>"
                 placeholder="🔍 Name, E-Mail oder IP suchen…"
                 style="flex:1;padding:8px 12px;border:1px solid var(--border);border-radius:6px;background:var(--bg-surface);color:var(--text-base)">
          <button type="submit" class="btn btn-primary">Suchen</button>
          <?php if (!empty($searchQuery)): ?>
            <a href="?action=users" class="btn btn-ghost">Zurücksetzen</a>
          <?php endif; ?>
        </div>
      </form>

      <div class="card">
        <table class="forum-table">
          <thead>
            <tr>
              <th>Name / E-Mail</th>
              <th>Reg.-IP</th>
              <th>Letzte IP</th>
              <th>Beiträge</th>
              <th>Status</th>
              <th>Aktionen</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($allUsers as $u): ?>
            <tr>
              <td>
                <a href="<?= e(FORUM_URL) ?>/profile.php?id=<?= (int) $u['user_id'] ?>"><?= e($u['username']) ?></a>
                <br><span style="font-size:11px;color:var(--text-faint)"><?= e($u['email']) ?></span>
              </td>
              <td>
                <?php if ($u['registration_ip']): ?>
                  <a href="?action=iplookup&ip=<?= e(urlencode($u['registration_ip'])) ?>">
                    <code style="font-size:11px"><?= e($u['registration_ip']) ?></code>
                  </a>
                <?php else: ?>
                  <span style="color:var(--text-faint)">–</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($u['last_ip']): ?>
                  <a href="?action=iplookup&ip=<?= e(urlencode($u['last_ip'])) ?>">
                    <code style="font-size:11px"><?= e($u['last_ip']) ?></code>
                  </a>
                <?php else: ?>
                  <span style="color:var(--text-faint)">–</span>
                <?php endif; ?>
              </td>
              <td><?= (int) $u['post_count'] ?></td>
              <td>
                <?php if (!empty($u['is_owner'])): ?>
                  <span class="role-tag" style="background:#7c3aed">👑 Owner</span>
                <?php elseif ($u['is_banned']): ?>
                  <span class="role-tag" style="background:#6b7280">Gesperrt</span>
                <?php elseif ($u['is_admin']): ?>
                  <span class="role-tag" style="background:#dc2626">Admin</span>
                <?php elseif ($u['is_mod']): ?>
                  <span class="role-tag" style="background:#f59e0b">Mod</span>
                <?php else: ?>
                  <span class="role-tag" style="background:var(--bg-surface3);color:var(--text-muted)">Mitglied</span>
                <?php endif; ?>
              </td>
              <td>
                <?php
                  $csrfQS = '&csrf=' . e($csrfToken);
                  $base   = '?action=';
                  $uid    = (int) $u['user_id'];
                  $cuid   = (int) ($currentUser['user_id'] ?? 0);
                  $isFounderViewer = $cuid === 1;
                  $targetIsFounder = $uid === 1;
                ?>
                <?php /* Founder (User-ID 1) ist hart geschützt – keinerlei Aktionen für ihn */ ?>
                <?php if ($targetIsFounder): ?>
                  <span style="font-size:11px;color:var(--text-faint);font-style:italic">🏛️ Gründer (geschützt)</span>
                <?php /* Andere Owner: nur der Founder kann die anfassen */ ?>
                <?php elseif (!empty($u['is_owner']) && !$isFounderViewer): ?>
                  <span style="font-size:11px;color:var(--text-faint);font-style:italic">🛡️ Inhaber (geschützt)</span>
                <?php else: ?>
                  <div class="action-dropdown">
                    <button type="button" class="action-dropdown-toggle action-toggle-icon btn btn-ghost btn-xs">⋯</button>
                    <div class="action-dropdown-menu">

                      <?php if (!empty($u['is_owner'])): ?>
                        <?php /* Founder sieht hier: Owner-Status entfernen */ ?>
                        <a href="<?= $base ?>remove_owner&id=<?= $uid . $csrfQS ?>"
                           class="action-danger"
                           onclick="return confirm('Owner-Status wirklich entziehen? <?= e($u['username']) ?> wird zum normalen Administrator.')">⬇️ Owner entziehen</a>
                      <?php elseif ($u['is_banned']): ?>
                        <a href="<?= $base ?>unban_user&id=<?= $uid . $csrfQS ?>">✅ Entsperren</a>
                      <?php else: ?>
                        <?php /* Founder-only: Owner ernennen */ ?>
                        <?php if ($isFounderViewer): ?>
                          <a href="<?= $base ?>make_owner&id=<?= $uid . $csrfQS ?>"
                             onclick="return confirm('<?= e($u['username']) ?> zum Inhaber ernennen? Inhaber haben volle Forum-Rechte – das ist eine wichtige Entscheidung!')">👑 Zum Inhaber</a>
                          <div class="action-dropdown-divider"></div>
                        <?php endif; ?>

                        <?php if ($u['is_admin']): ?>
                          <?php if ($uid !== $cuid): ?>
                            <a href="<?= $base ?>remove_admin&id=<?= $uid . $csrfQS ?>"
                               onclick="return confirm('Admin-Rechte wirklich entziehen?')">⬇️ Admin entziehen</a>
                          <?php endif; ?>
                        <?php elseif ($u['is_mod']): ?>
                          <a href="<?= $base ?>make_admin&id=<?= $uid . $csrfQS ?>"
                             onclick="return confirm('Zum Administrator ernennen?')">🔴 Zum Admin</a>
                          <a href="<?= $base ?>remove_mod&id=<?= $uid . $csrfQS ?>">⬇️ Mod entziehen</a>
                        <?php else: ?>
                          <a href="<?= $base ?>make_admin&id=<?= $uid . $csrfQS ?>"
                             onclick="return confirm('Zum Administrator ernennen?')">🔴 Zum Admin</a>
                          <a href="<?= $base ?>make_mod&id=<?= $uid . $csrfQS ?>">🛡️ Zum Moderator</a>
                        <?php endif; ?>

                        <div class="action-dropdown-divider"></div>

                        <?php if ($uid !== $cuid): ?>
                          <a href="<?= $base ?>ban_user&id=<?= $uid . $csrfQS ?>"
                             class="action-danger"
                             onclick="return confirm('Benutzer wirklich sperren?')">🚫 Sperren</a>
                        <?php endif; ?>
                      <?php endif; ?>

                      <?php
                        // Soft-Delete des Accounts (DSGVO): Owner immer, Admin auch – aber nicht gegen Owner
                        $canDelete = (
                          (!empty($currentUser['is_owner']) || !empty($currentUser['is_admin']))
                          && $uid !== $cuid
                          && empty($u['is_owner'])
                        );
                      ?>
                      <?php if ($canDelete): ?>
                        <a href="<?= $base ?>delete_user&id=<?= $uid . $csrfQS ?>"
                           class="action-danger"
                           onclick="return confirm('⚠️ Account von „<?= e($u['username']) ?>“ wirklich löschen?\n\nDer User wird zu „Gelöschter Benutzer“ anonymisiert. Beiträge bleiben erhalten, aber alle persönlichen Daten werden entfernt.\n\nDiese Aktion ist endgültig!')">🗑️ Account löschen</a>
                      <?php endif; ?>
                    </div>
                  </div>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

    <?php /* ════════════ KATEGORIEN ════════════ */ ?>
    <?php elseif ($action === 'categories'): ?>
      <h2>📁 Kategorien</h2>
      <p style="font-size:13px;color:var(--text-muted);margin-bottom:14px">
        Kategorien fassen mehrere Foren zusammen. Klick auf „✏️ Bearbeiten" um Titel oder Position zu ändern.
      </p>
      <div class="card" style="margin-bottom:16px">
        <table class="forum-table">
          <thead><tr><th>ID</th><th>Titel</th><th>Position</th><th>Aktion</th></tr></thead>
          <tbody>
            <?php foreach ($categories as $c): ?>
            <tr>
              <td><?= (int) $c['cat_id'] ?></td>
              <td><?= e($c['title']) ?></td>
              <td><?= (int) $c['position'] ?></td>
              <td>
                <details class="cat-edit-details">
                  <summary class="btn btn-ghost btn-xs">✏️ Bearbeiten</summary>
                  <form method="post" action="?action=update_cat" class="cat-edit-form">
                    <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="cat_id" value="<?= (int) $c['cat_id'] ?>">
                    <div class="form-row">
                      <label>Titel</label>
                      <input type="text" name="title" value="<?= e($c['title']) ?>" required maxlength="100">
                    </div>
                    <div class="form-row">
                      <label>Position</label>
                      <input type="number" name="position" value="<?= (int) $c['position'] ?>" min="0" style="width:80px">
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">💾 Speichern</button>
                  </form>
                </details>
                <a href="?action=delete_cat&id=<?= (int) $c['cat_id'] ?>&csrf=<?= e($csrfToken) ?>"
                   class="btn btn-danger btn-xs"
                   onclick="return confirm('Kategorie löschen? Alle enthaltenen Foren werden ebenfalls gelöscht!')">🗑️</a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="form-card">
        <h2>➕ Neue Kategorie</h2>
        <form method="post" action="?action=add_cat">
          <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
          <div class="form-row"><label>Titel</label><input type="text" name="title" required></div>
          <div class="form-row"><label>Position</label><input type="number" name="position" value="0" min="0"></div>
          <button type="submit" class="btn btn-primary">Anlegen</button>
        </form>
      </div>

    <?php /* ════════════ FOREN ════════════ */ ?>
    <?php elseif ($action === 'boards'): ?>
      <h2>📋 Foren</h2>
      <div class="card" style="margin-bottom:16px">
        <table class="forum-table">
          <thead><tr><th>Titel</th><th>Kategorie</th><th>Threads</th><th>Posts</th><th>Freigabe</th><th>Moderatoren</th><th>Aktion</th></tr></thead>
          <tbody>
            <?php foreach ($boards as $b):
              $mods = $boardMods[(int) $b['board_id']] ?? [];
            ?>
            <tr>
              <td>
                <?php if (!empty($b['password_hash'])): ?>
                  <span title="Passwort-geschützt" style="color:var(--color-accent)">🔒</span>
                <?php endif; ?>
                <?= e($b['title']) ?>
              </td>
              <td><?= e($b['cat']) ?></td>
              <td><?= (int) $b['thread_count'] ?></td>
              <td><?= (int) $b['post_count'] ?></td>
              <td style="font-size:11px">
                <?php
                  $approveThr = !empty($b['require_approval_threads']);
                  $approveRep = !empty($b['require_approval_replies']);
                ?>
                <?php if (!$approveThr && !$approveRep): ?>
                  <span style="color:var(--text-faint)">aus</span>
                <?php else: ?>
                  <?php if ($approveThr): ?><span style="color:var(--color-warning)">📝 Themen</span><?php endif; ?>
                  <?php if ($approveRep): ?><?php if ($approveThr): ?><br><?php endif; ?><span style="color:var(--color-warning)">💬 Antworten</span><?php endif; ?>
                <?php endif; ?>
              </td>
              <td style="font-size:12px">
                <?php if (empty($mods)): ?>
                  <span style="color:var(--text-faint);font-style:italic">keine</span>
                <?php else:
                  $names = [];
                  foreach ($mods as $m) {
                    $role = user_role_style($m);
                    $names[] = '<span style="color:' . $role['color'] . ';font-weight:600" title="' . e($role['title']) . '">' . e($m['username']) . '</span>';
                  }
                  echo implode(', ', $names);
                endif; ?>
              </td>
              <td style="white-space:nowrap">
                <a href="?action=boards&board=<?= (int) $b['board_id'] ?>&edit=details" class="btn btn-ghost btn-xs">✏️ Bearbeiten</a>
                <a href="?action=boards&board=<?= (int) $b['board_id'] ?>&edit=approval" class="btn btn-ghost btn-xs">🚦 Freigabe</a>
                <a href="?action=boards&board=<?= (int) $b['board_id'] ?>" class="btn btn-ghost btn-xs">🛡️ Mods</a>
                <a href="?action=delete_board&id=<?= (int) $b['board_id'] ?>&csrf=<?= e($csrfToken) ?>"
                   class="btn btn-danger btn-xs" onclick="return confirm('Forum löschen?')">Löschen</a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <?php /* ── Forum-Editor ── */ ?>
      <?php if (!empty($selectedBoard) && ($_GET['edit'] ?? '') === 'details'): ?>
        <div class="form-card" style="margin-bottom:16px;border:2px solid var(--color-accent)">
          <h2>✏️ Forum bearbeiten: „<?= e($selectedBoard['title']) ?>"</h2>
          <form method="post" action="?action=update_board">
            <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="board_id" value="<?= (int) $selectedBoard['board_id'] ?>">

            <div class="form-row">
              <label>Titel</label>
              <input type="text" name="title" value="<?= e($selectedBoard['title']) ?>" required>
            </div>

            <div class="form-row">
              <label>Beschreibung</label>
              <input type="text" name="description" value="<?= e($selectedBoard['description'] ?? '') ?>">
            </div>

            <div class="form-row">
              <label>Kategorie</label>
              <select name="cat_id" required>
                <?php foreach ($categories as $c): ?>
                  <option value="<?= (int) $c['cat_id'] ?>" <?= (int) $selectedBoard['cat_id'] === (int) $c['cat_id'] ? 'selected' : '' ?>>
                    <?= e($c['title']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <div class="form-hint">Verschiebe das Forum in eine andere Kategorie.</div>
            </div>

            <div class="form-row">
              <label>Position</label>
              <input type="number" name="position" value="<?= (int) $selectedBoard['position'] ?>" min="0" max="999">
              <div class="form-hint">Niedrigere Zahl = weiter oben in der Kategorie.</div>
            </div>

            <?php /* ── GRUPPENRECHTE ── */ ?>
            <?php
              $rechte  = $brettRechte ?? [];
              $fein    = $brettFeinrechte ?? [];
              $feinDa  = !empty($feinrechteDa);
              // Beschriftung der Feinrechte. Reihenfolge wie in BRETT_FEINRECHTE,
              // damit Kopfzeile und Kaestchen nicht auseinanderlaufen.
              $feinSpalten = [
                'thema'      => ['Thema',  'Neue Themen eröffnen'],
                'antwort'    => ['Antwort', 'Auf Themen antworten'],
                'anhang'     => ['Anhang',  'Dateien anhängen'],
                'bearbeiten' => ['Ändern',  'Eigene Beiträge nachträglich ändern'],
                'umfrage'    => ['Umfrage', 'Umfragen anlegen'],
              ];
              $kopf = 'padding:8px 10px;font-size:11px;text-transform:uppercase;'
                    . 'letter-spacing:.05em;color:var(--text-muted);border-bottom:1px solid var(--border)';
              $trenn = 'border-left:1px solid var(--border)';
            ?>
            <div class="form-row" style="border-top:1px dashed var(--border);padding-top:14px;margin-top:14px">
              <label>👪 Gruppenrechte</label>
              <div style="overflow-x:auto">
                <table style="width:100%;border-collapse:collapse">
                  <tr>
                    <th style="text-align:left;<?= $kopf ?>">Gruppe</th>
                    <?php foreach (['none' => 'nichts', 'read' => 'lesen', 'write' => 'schreiben'] as $stufe => $beschriftung): ?>
                      <th style="width:90px;<?= $kopf ?>"><?= e($beschriftung) ?></th>
                    <?php endforeach; ?>
                    <?php if ($feinDa): ?>
                      <?php $erste = true; foreach ($feinSpalten as $recht => [$kurz, $lang]): ?>
                        <th style="width:78px;<?= $kopf ?><?= $erste ? ';' . $trenn : '' ?>"
                            title="<?= e($lang) ?>"><?= e($kurz) ?></th>
                        <?php $erste = false; ?>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tr>
                  <?php foreach (($gruppen ?? []) as $g): ?>
                    <?php
                      $gid = (int) $g['group_id'];
                      // Ohne Eintrag gilt das Forum als offen — dann steht hier
                      // vorbelegt, was tatsaechlich passiert: Gaeste lesen,
                      // Angemeldete schreiben.
                      $vorgabe = $rechte[$gid] ?? ($g['slug'] === 'guests' ? 'read'
                                                : ($g['slug'] === 'members' ? 'write' : 'none'));
                      // Genauso bei den Haken: keine Zeile heisst "alles erlaubt",
                      // weil board_darf() es ebenso auswertet.
                      $haken = $fein[$gid] ?? array_fill_keys(array_keys($feinSpalten), true);
                      $zelle = 'text-align:center;padding:8px 10px;border-bottom:1px solid var(--border)';
                    ?>
                    <tr>
                      <td style="padding:8px 10px;border-bottom:1px solid var(--border)">
                        <?= e($g['name']) ?>
                        <?php if (!empty($g['is_builtin'])): ?>
                          <span style="color:var(--text-faint);font-size:12px;margin-left:6px">eingebaut</span>
                        <?php endif; ?>
                      </td>
                      <?php foreach (['none', 'read', 'write'] as $stufe): ?>
                        <td style="<?= $zelle ?>">
                          <input type="radio" name="rechte[<?= $gid ?>]" value="<?= $stufe ?>"
                                 data-stufe-fuer="<?= $gid ?>"
                                 <?= $vorgabe === $stufe ? 'checked' : '' ?>>
                        </td>
                      <?php endforeach; ?>
                      <?php if ($feinDa): ?>
                        <?php $erste = true; foreach ($feinSpalten as $recht => [$kurz, $lang]): ?>
                          <td style="<?= $zelle ?><?= $erste ? ';' . $trenn : '' ?>"
                              data-fein-fuer="<?= $gid ?>"
                              title="<?= e($lang) ?>">
                            <input type="checkbox" name="fein[<?= $gid ?>][<?= $recht ?>]" value="1"
                                   <?= !empty($haken[$recht]) ? 'checked' : '' ?>>
                          </td>
                          <?php $erste = false; ?>
                        <?php endforeach; ?>
                      <?php endif; ?>
                    </tr>
                  <?php endforeach; ?>
                </table>
              </div>
              <div class="form-hint">
                Inhaber, Admins und Moderatoren sind ausgenommen — sie dürfen immer.
                Wer in mehreren Gruppen ist, bekommt das höhere Recht.
                <?php if ($feinDa): ?>
                  Die rechten Häkchen gelten nur bei „schreiben“ — bei „nichts“ und „lesen“
                  sind sie wirkungslos und deshalb blass.
                <?php else: ?>
                  <br><strong>Feinere Rechte stehen noch nicht bereit.</strong>
                  Sie kommen mit der Anpassung <em>v1.5.0 – Feinere Rechte je Forum und Gruppe</em>
                  unter <a href="<?= FORUM_URL ?>/admin/?action=updates">Forum-Updates</a>.
                <?php endif; ?>
              </div>
            </div>
            <?php if ($feinDa): ?>
            <script>
            // Die Haken bleiben bedienbar, auch wenn die Stufe nicht "schreiben"
            // ist — sie werden nur blass. Sie abzuschalten waere ein Fallstrick:
            // abgeschaltete Kaestchen schickt der Browser nicht mit, ein Umweg
            // ueber "lesen" und zurueck haette damit alle Haken geloescht.
            (function () {
              function anpassen(gid) {
                var gewaehlt = document.querySelector('input[name="rechte[' + gid + ']"]:checked');
                var aktiv = gewaehlt && gewaehlt.value === 'write';
                document.querySelectorAll('[data-fein-fuer="' + gid + '"]').forEach(function (td) {
                  td.style.opacity = aktiv ? '1' : '.35';
                });
              }
              document.querySelectorAll('[data-stufe-fuer]').forEach(function (r) {
                r.addEventListener('change', function () { anpassen(r.dataset.stufeFuer); });
              });
              document.querySelectorAll('[data-fein-fuer]').forEach(function (td) {
                anpassen(td.dataset.feinFuer);
              });
            })();
            </script>
            <?php endif; ?>

            <div class="form-row">
              <label>Wenn jemand kein Recht hat</label>
              <?php $sicht = $selectedBoard['visibility'] ?? 'hidden'; ?>
              <label class="checkbox-line" style="margin-bottom:6px">
                <input type="radio" name="visibility" value="hidden" <?= $sicht !== 'locked' ? 'checked' : '' ?>>
                <span><strong>Verstecken</strong> — das Forum taucht nirgends auf, direkter Aufruf endet auf 404</span>
              </label>
              <label class="checkbox-line">
                <input type="radio" name="visibility" value="locked" <?= $sicht === 'locked' ? 'checked' : '' ?>>
                <span><strong>Zeigen, aber gesperrt</strong> — sichtbar mit Schloss, der Inhalt bleibt zu</span>
              </label>
              <div class="form-hint">
                Verstecken heißt wirklich weg: nicht in der Suche, nicht im RSS, nicht über Schlagworte,
                nicht im Profil eines Mitglieds.
              </div>
            </div>

            <?php /* ── PASSWORT-SCHUTZ ── */ ?>
            <div class="form-row" style="border-top:1px dashed var(--border);padding-top:14px;margin-top:14px">
              <label>🔒 Passwort-Schutz</label>
              <?php $isProtected = !empty($selectedBoard['password_hash']); ?>
              <?php if ($isProtected): ?>
                <div style="padding:10px 12px;background:var(--color-accent-soft);border-radius:var(--radius-sm);margin-bottom:8px">
                  <strong>✓ Dieses Forum ist aktuell passwort-geschützt.</strong>
                  <div style="font-size:12px;color:var(--text-muted);margin-top:4px">
                    Owner, Admins, globale Mods und Forum-Mods sind ausgenommen.
                    Alle anderen User müssen das Passwort einmal pro Session eingeben.
                  </div>
                </div>
                <label class="checkbox-line" style="margin-bottom:8px">
                  <input type="checkbox" name="remove_password" value="1">
                  <span>🔓 Passwort-Schutz entfernen (Forum wird öffentlich)</span>
                </label>
              <?php endif; ?>
              <input type="password" name="new_password" value="" autocomplete="new-password"
                     placeholder="<?= $isProtected ? 'Neues Passwort eingeben um es zu ändern...' : 'Passwort setzen (leer = kein Schutz)' ?>">
              <div class="form-hint">
                <?php if ($isProtected): ?>
                  Aus Sicherheitsgründen wird das aktuelle Passwort nicht angezeigt.
                  Wird hier ein neues eingegeben, ersetzt es das alte.
                  Feld leer lassen = Passwort bleibt unverändert.
                <?php else: ?>
                  Wenn gesetzt: User müssen dieses Passwort eingeben um das Forum zu betreten.
                  Owner/Admins/Mods (auch Forum-Mods) sind ausgenommen.
                <?php endif; ?>
              </div>
            </div>

            <div style="display:flex;gap:8px">
              <button type="submit" class="btn btn-primary">💾 Speichern</button>
              <a href="?action=boards" class="btn btn-ghost">Abbrechen</a>
            </div>
          </form>
        </div>
      <?php endif; ?>

      <?php /* ── Freigabe-Editor pro Board ── */ ?>
      <?php if (!empty($selectedBoard) && ($_GET['edit'] ?? '') === 'approval'): ?>
        <div class="form-card" style="margin-bottom:16px;border:2px solid var(--color-warning)">
          <h2>🚦 Freigabe-Einstellungen für „<?= e($selectedBoard['title']) ?>"</h2>
          <p style="font-size:12px;color:var(--text-muted);margin-bottom:14px">
            Hier festlegen, welche neuen Beiträge in diesem Forum erst freigegeben werden müssen.
            <strong>Admins und Moderatoren sind immer ausgenommen</strong> – ihre Beiträge erscheinen sofort.
          </p>
          <form method="post" action="?action=update_board_approval">
            <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="board_id" value="<?= (int) $selectedBoard['board_id'] ?>">
            <div class="form-row" style="padding:14px;background:var(--bg-surface2);border-radius:var(--radius-sm);margin-bottom:12px">
              <label style="display:flex;align-items:flex-start;gap:10px;font-size:13px;cursor:pointer">
                <input type="checkbox" name="require_approval_threads" value="1"
                  <?= !empty($selectedBoard['require_approval_threads']) ? 'checked' : '' ?>
                  style="margin-top:3px">
                <div>
                  <strong>📝 Neue Themen freigeben</strong>
                  <div style="font-size:12px;color:var(--text-muted);margin-top:3px">
                    Neue Themen im Forum müssen erst von einem Mod genehmigt werden, bevor sie sichtbar sind.
                  </div>
                </div>
              </label>
            </div>
            <div class="form-row" style="padding:14px;background:var(--bg-surface2);border-radius:var(--radius-sm);margin-bottom:12px">
              <label style="display:flex;align-items:flex-start;gap:10px;font-size:13px;cursor:pointer">
                <input type="checkbox" name="require_approval_replies" value="1"
                  <?= !empty($selectedBoard['require_approval_replies']) ? 'checked' : '' ?>
                  style="margin-top:3px">
                <div>
                  <strong>💬 Antworten freigeben</strong>
                  <div style="font-size:12px;color:var(--text-muted);margin-top:3px">
                    Antworten auf bestehende Themen müssen erst genehmigt werden.
                  </div>
                </div>
              </label>
            </div>
            <button type="submit" class="btn btn-primary">💾 Speichern</button>
            <a href="?action=boards" class="btn btn-ghost">Abbrechen</a>
          </form>
        </div>
      <?php endif; ?>

      <?php if (!empty($selectedBoard)): ?>
        <div class="form-card" style="margin-bottom:16px;border:2px solid var(--text-base)">
          <h2>🛡️ Moderatoren für „<?= e($selectedBoard['title']) ?>"</h2>
          <p style="font-size:12px;color:var(--text-muted);margin-bottom:14px">
            <strong>Hinweis:</strong> Globale Moderatoren (is_mod=1) und Administratoren dürfen sowieso überall moderieren.
            Hier zugeordnete User dürfen ausschließlich in diesem Forum moderieren.
          </p>

          <div class="section-heading">Aktuelle Moderatoren</div>
          <?php if (empty($selectedMods)): ?>
            <p style="color:var(--text-faint);font-style:italic;padding:14px 0">
              Keine Moderatoren zugewiesen.
            </p>
          <?php else: ?>
            <table class="forum-table" style="margin-bottom:16px">
              <thead><tr><th>Benutzer</th><th>Rolle</th><th>Zugewiesen am</th><th></th></tr></thead>
              <tbody>
                <?php foreach ($selectedMods as $m): ?>
                  <tr>
                    <td><a href="<?= e(FORUM_URL) ?>/profile.php?id=<?= (int) $m['user_id'] ?>"><?= e($m['username']) ?></a></td>
                    <td style="font-size:11px">
                      <?php if ($m['is_admin']): ?>
                        <span style="color:#dc2626;font-weight:600">Administrator</span>
                      <?php elseif ($m['is_mod']): ?>
                        <span style="color:#f59e0b;font-weight:600">Globaler Moderator</span>
                      <?php else: ?>
                        <span style="color:#f59e0b;font-weight:600">Forum-Moderator</span>
                      <?php endif; ?>
                    </td>
                    <td style="font-size:11px;color:var(--text-muted)"><?= e(format_date($m['assigned_at'])) ?></td>
                    <td>
                      <a href="?action=remove_board_mod&board=<?= (int) $selectedBoard['board_id'] ?>&user=<?= (int) $m['user_id'] ?>&csrf=<?= e($csrfToken) ?>"
                         class="btn btn-danger btn-xs"
                         onclick="return confirm('Moderator entfernen?')">Entfernen</a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>

          <div class="section-heading">Moderator hinzufügen</div>
          <form method="post" action="?action=add_board_mod">
            <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="board_id" value="<?= (int) $selectedBoard['board_id'] ?>">
            <div class="form-row">
              <label>Benutzer auswählen</label>
              <select name="user_id" required>
                <option value="">– Bitte wählen –</option>
                <?php foreach ($availableUsers as $u):
                  $isAlreadyMod = false;
                  foreach ($selectedMods as $sm) if ($sm['user_id'] == $u['user_id']) { $isAlreadyMod = true; break; }
                  if ($isAlreadyMod) continue;
                ?>
                  <option value="<?= (int) $u['user_id'] ?>">
                    <?= e($u['username']) ?>
                    <?= $u['is_admin'] ? ' (Admin)' : ($u['is_mod'] ? ' (Global-Mod)' : '') ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <button type="submit" class="btn btn-primary">+ Hinzufügen</button>
            <a href="?action=boards" class="btn btn-ghost">Schließen</a>
          </form>
        </div>
      <?php endif; ?>

      <div class="form-card">
        <h2>➕ Neues Forum</h2>
        <form method="post" action="?action=add_board" autocomplete="off">
          <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
          <div class="form-row">
            <label>Kategorie</label>
            <select name="cat_id">
              <?php foreach ($categories as $c): ?>
              <option value="<?= (int) $c['cat_id'] ?>"><?= e($c['title']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-row"><label>Titel</label><input type="text" name="title" required></div>
          <div class="form-row"><label>Beschreibung</label><input type="text" name="description"></div>
          <div class="form-row"><label>Position</label><input type="number" name="position" value="0" min="0"></div>

          <div class="form-row" style="border-top:1px dashed var(--border);padding-top:14px;margin-top:14px">
            <label>🔒 Passwort-Schutz <span style="font-weight:400;color:var(--text-muted);font-size:11px">(optional)</span></label>
            <input type="password" name="new_password" value="" autocomplete="new-password"
                   placeholder="Passwort setzen (leer = öffentliches Forum)">
            <div class="form-hint">
              Wenn gesetzt: User müssen dieses Passwort eingeben um das Forum zu betreten.
              Owner, Admins, globale Mods und Forum-Mods sind ausgenommen.
              Kann jederzeit nachträglich gesetzt oder geändert werden.
            </div>
          </div>

          <button type="submit" class="btn btn-primary">Anlegen</button>
        </form>
      </div>

    <?php /* ════════════ RÄNGE ════════════ */ ?>
    <?php elseif ($action === 'groups'): ?>
      <?php /* ── GRUPPEN ────────────────────────────────────────────────── */ ?>
      <h2>👪 Gruppen</h2>
      <p style="font-size:13px;color:var(--text-muted);margin-bottom:14px">
        Gruppen entscheiden, wer welches Forum sehen, lesen und beschreiben darf.
        Eingestellt wird das je Forum unter <a href="?action=boards">Foren</a>.
      </p>

      <div class="card" style="margin-bottom:16px">
        <div style="overflow-x:auto">
          <table class="forum-table">
            <thead>
              <tr><th>Gruppe</th><th>Mitglieder</th><th>Abzeichen</th><th>In Rechten</th><th></th></tr>
            </thead>
            <tbody>
              <?php foreach (($gruppen ?? []) as $g): ?>
                <?php $gid = (int) $g['group_id']; $eingebaut = !empty($g['is_builtin']); ?>
                <tr>
                  <td>
                    <strong><?= e($g['name']) ?></strong>
                    <?php if ($eingebaut): ?>
                      <span style="color:var(--text-faint);font-size:12px;margin-left:6px">eingebaut</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if ($g['slug'] === 'guests'): ?>
                      <span style="color:var(--text-muted)">alle nicht Angemeldeten</span>
                    <?php elseif ($g['slug'] === 'members'): ?>
                      <span style="color:var(--text-muted)">automatisch</span>
                    <?php else: ?>
                      <?= (int) ($mitgliederZahl[$gid] ?? 0) ?>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?= !empty($g['show_badge']) ? 'wird gezeigt' : '<span style="color:var(--text-faint)">aus</span>' ?>
                  </td>
                  <?php $nForen = (int) ($rechteJeGruppe[$gid] ?? 0); ?>
                  <td><?= $nForen ?> <?= $nForen === 1 ? 'Forum' : 'Foren' ?></td>
                  <td style="white-space:nowrap">
                    <a href="?action=groups&group=<?= $gid ?>" class="btn btn-ghost btn-xs">Bearbeiten</a>
                    <?php if (!$eingebaut): ?>
                      <a href="?action=delete_group&id=<?= $gid ?>&csrf=<?= e($csrfToken) ?>"
                         class="btn btn-ghost btn-xs"
                         onclick="return confirm('Gruppe „<?= e($g['name']) ?>“ löschen? Die Rechte, die daran hängen, verschwinden mit.')">Löschen</a>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="card" style="margin-bottom:16px">
        <h3 style="margin-bottom:12px">Neue Gruppe</h3>
        <form method="post" action="?action=create_group">
          <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
          <div class="form-row">
            <label>Name</label>
            <input type="text" name="name" required maxlength="60" placeholder="z. B. Kunden">
          </div>
          <label class="checkbox-line">
            <input type="checkbox" name="show_badge" value="1">
            <span>Als Abzeichen neben dem Namen zeigen</span>
          </label>
          <div class="form-hint">
            Ohne Häkchen bleibt die Gruppe unsichtbar — für eine interne Gruppe ist das der Sinn der Sache.
          </div>
          <div style="margin-top:14px"><button class="btn btn-primary">Anlegen</button></div>
        </form>
      </div>

      <?php if (!empty($gruppe)): ?>
        <div class="card" style="margin-bottom:16px">
          <h3 style="margin-bottom:12px"><?= e($gruppe['name']) ?></h3>

          <form method="post" action="?action=update_group">
            <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="group_id" value="<?= (int) $gruppe['group_id'] ?>">
            <div class="form-row">
              <label>Name</label>
              <input type="text" name="name" value="<?= e($gruppe['name']) ?>" required maxlength="60">
              <?php if (!empty($gruppe['is_builtin'])): ?>
                <div class="form-hint">
                  Der Anzeigename lässt sich ändern, der interne Kurzname
                  (<code><?= e($gruppe['slug']) ?></code>) nicht — daran hängt der Code.
                </div>
              <?php endif; ?>
            </div>
            <label class="checkbox-line">
              <input type="checkbox" name="show_badge" value="1" <?= !empty($gruppe['show_badge']) ? 'checked' : '' ?>>
              <span>Als Abzeichen neben dem Namen zeigen</span>
            </label>
            <div style="margin-top:14px"><button class="btn btn-primary">Speichern</button></div>
          </form>

          <?php if (empty($gruppe['is_builtin'])): ?>
            <div style="border-top:1px dashed var(--border);margin-top:18px;padding-top:16px">
              <h3 style="font-size:14px;margin-bottom:10px">Mitglieder (<?= count($mitglieder ?? []) ?>)</h3>

              <?php if (empty($mitglieder)): ?>
                <p style="color:var(--text-muted);font-size:14px">Noch niemand eingetragen.</p>
              <?php else: ?>
                <div style="overflow-x:auto">
                  <table class="forum-table">
                    <tbody>
                      <?php foreach ($mitglieder as $m): ?>
                        <tr>
                          <td><?= e($m['username']) ?></td>
                          <td style="color:var(--text-muted)"><?= e((string) $m['added_at']) ?></td>
                          <td style="text-align:right">
                            <a class="btn btn-ghost btn-xs"
                               href="?action=group_member_remove&group=<?= (int) $gruppe['group_id'] ?>&user=<?= (int) $m['user_id'] ?>&csrf=<?= e($csrfToken) ?>">Entfernen</a>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php endif; ?>

              <form method="post" action="?action=group_member_add" style="margin-top:14px">
                <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="group_id" value="<?= (int) $gruppe['group_id'] ?>">
                <div class="form-row">
                  <label>Mitglied hinzufügen</label>
                  <select name="user_id" required>
                    <?php foreach (($waehlbar ?? []) as $u): ?>
                      <option value="<?= (int) $u['user_id'] ?>"><?= e($u['username']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <button class="btn btn-primary">Eintragen</button>
              </form>
            </div>
          <?php else: ?>
            <div class="form-hint" style="margin-top:14px">
              In diese Gruppe wird niemand eingetragen: wer angemeldet ist, gehört zu
              „Angemeldete", alle anderen zu „Gäste". Eine gepflegte Liste wäre sofort veraltet.
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

    <?php elseif ($action === 'ranks'): ?>
      <h2>🏅 Rangsystem</h2>
      <p style="font-size:13px;color:var(--text-muted);margin-bottom:14px">
        Ränge werden automatisch nach Beitragsanzahl vergeben.
        <a href="?action=recalc_ranks&csrf=<?= e($csrfToken) ?>" class="btn btn-primary btn-sm"
           onclick="return confirm('Alle Ränge neu berechnen?')" style="margin-left:8px">
          🔄 Alle neu berechnen
        </a>
      </p>
      <div class="card" style="margin-bottom:16px">
        <table class="forum-table">
          <thead><tr><th>Rang</th><th>Beiträge ab</th><th>Sterne</th><th>Mitglieder</th></tr></thead>
          <tbody>
            <?php foreach ($rankData as $r): ?>
            <tr>
              <td><strong style="color:<?= e($r['color']) ?>"><?= e($r['title']) ?></strong></td>
              <td><?= e($r['range']) ?></td>
              <td><?= $r['stars_html'] ?></td>
              <td><?= number_format((int) $r['count'], 0, ',', '.') ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="section-heading">🏆 Top 10 Mitglieder</div>
      <div class="card">
        <table class="forum-table">
          <thead><tr><th>#</th><th>Mitglied</th><th>Rang</th><th>Beiträge</th></tr></thead>
          <tbody>
            <?php foreach ($topUsers as $i => $u): ?>
            <tr>
              <td style="font-weight:700;color:var(--text-faint)"><?= $i + 1 ?></td>
              <td><a href="<?= e(FORUM_URL) ?>/profile.php?id=<?= (int) $u['user_id'] ?>"><?= e($u['username']) ?></a></td>
              <td><?= rank_badge($u['rank'], (int) $u['post_count']) ?></td>
              <td><?= number_format((int) $u['post_count'], 0, ',', '.') ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

    <?php /* ════════════ EINSTELLUNGEN ════════════ */ ?>
    <?php elseif ($action === 'settings'): ?>
      <h2>🔧 Forum-Einstellungen</h2>

      <?php
        // Welche Custom-Uploads gibt es?
        $rolePics = ['owner' => null, 'admin' => null, 'mod' => null];
        foreach ($rolePics as $role => $_) {
            foreach (['png', 'jpg', 'jpeg', 'webp', 'svg'] as $ext) {
                $p = __DIR__ . '/../../assets/role_' . $role . '_custom.' . $ext;
                if (file_exists($p)) { $rolePics[$role] = 'role_' . $role . '_custom.' . $ext; break; }
            }
        }
      ?>
      <div class="form-card" style="margin-bottom:20px">
        <h2>👤 Rollen-Avatare</h2>
        <p style="font-size:13px;color:var(--text-muted);margin-bottom:14px">
          Diese Bilder werden für User mit einer Rolle angezeigt, die <strong>kein eigenes Avatar</strong> hochgeladen haben.
          Wird die Rolle entzogen, bekommt der User automatisch wieder das Standard-Bild.
          Falls du keine eigenen Bilder hochlädst, werden hübsche SVG-Standard-Avatare verwendet.
        </p>

        <div class="role-avatars-grid">
          <?php foreach (['owner' => '👑 Owner', 'admin' => '🛡️ Admin', 'mod' => '🔰 Mod'] as $role => $label):
            $hasCustom = !empty($rolePics[$role]);
            $imgSrc    = $hasCustom
                ? FORUM_URL . '/assets/' . $rolePics[$role] . '?t=' . time()
                : FORUM_URL . '/assets/role_' . $role . '.svg?t=' . time();
          ?>
            <div class="role-avatar-box">
              <div class="role-avatar-preview">
                <img src="<?= e($imgSrc) ?>" alt="<?= e($label) ?>">
              </div>
              <div class="role-avatar-controls">
                <div class="role-avatar-label"><?= $label ?></div>
                <div class="role-avatar-status">
                  <?php if ($hasCustom): ?>
                    <span style="color:var(--color-success)">✓ Eigenes Bild aktiv</span>
                  <?php else: ?>
                    <span style="color:var(--text-muted)">Standard-SVG aktiv</span>
                  <?php endif; ?>
                </div>
                <form method="post" action="?action=upload_role_avatar" enctype="multipart/form-data"
                      style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;margin-top:8px">
                  <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
                  <input type="hidden" name="role" value="<?= e($role) ?>">
                  <input type="file" name="image" accept="image/png,image/jpeg,image/webp,image/svg+xml" required
                         style="font-size:11px;flex:1;min-width:140px">
                  <button type="submit" class="btn btn-primary btn-sm">⬆️ Hochladen</button>
                </form>
                <?php if ($hasCustom): ?>
                  <form method="post" action="?action=delete_role_avatar" style="margin-top:4px"
                        onsubmit="return confirm('Eigenes <?= e($label) ?>-Bild löschen und zum Standard zurück?')">
                    <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="role" value="<?= e($role) ?>">
                    <button type="submit" class="btn btn-ghost btn-sm" style="font-size:11px">🗑️ Zurück zum Standard</button>
                  </form>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <form method="post" action="?action=save_settings">
        <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
        <?php /* Ohne Marker gilt das alte Verhalten: dann zaehlt save_settings
                 auch die Schalter der eigenen Menuepunkte zu diesem Formular
                 und setzt sie auf 0, weil sie hier fehlen. */ ?>
        <input type="hidden" name="settings_section" value="general">

        <div class="alert alert-info" style="margin-bottom:16px">
          💡 <strong>Hinweis:</strong> Branding (Logo, Name, Slogan), Farben, Footer und Custom CSS findest du jetzt im neuen Bereich
          <a href="?action=design"><strong>🎨 Design</strong></a>. Hier sind nur noch funktionale Einstellungen.
        </div>

        <?php /* ═══ 1. ALLGEMEIN ═════════════════════════════════════ */ ?>
        <div class="form-card" style="margin-bottom:16px">
          <h2>📋 Allgemein</h2>
          <div class="form-row">
            <label>Forenbeschreibung</label>
            <input type="text" name="forum_description" value="<?= e($settings['forum_description'] ?? '') ?>"
                   placeholder="Eine kurze Beschreibung deines Forums">
            <div class="form-hint">Erscheint im Browser, in Suchmaschinen und als Meta-Beschreibung.</div>
          </div>
          <div class="form-row">
            <label>Standard-Sprache</label>
            <select name="forum_default_lang">
              <?php $lang = $settings['forum_default_lang'] ?? 'de'; ?>
              <option value="de" <?= $lang === 'de' ? 'selected' : '' ?>>🇩🇪 Deutsch</option>
              <option value="en" <?= $lang === 'en' ? 'selected' : '' ?>>🇬🇧 English</option>
            </select>
            <div class="form-hint">Wird für nicht-eingeloggte Besucher verwendet.</div>
          </div>
        </div>

        <?php /* ═══ 2. BEITRÄGE & THREADS ════════════════════════════ */ ?>
        <div class="form-card" style="margin-bottom:16px">
          <h2>📝 Beiträge & Threads</h2>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
            <div class="form-row">
              <label>Min. Beitragslänge (Zeichen)</label>
              <input type="number" name="min_post_length" min="1" max="1000"
                     value="<?= e($settings['min_post_length'] ?? '3') ?>">
            </div>
            <div class="form-row">
              <label>Max. Beitragslänge (Zeichen)</label>
              <input type="number" name="max_post_length" min="100" max="100000"
                     value="<?= e($settings['max_post_length'] ?? '20000') ?>">
            </div>
            <div class="form-row">
              <label>Bearbeitungsfenster (Minuten)</label>
              <input type="number" name="edit_window_minutes" min="0" max="10080"
                     value="<?= e($settings['edit_window_minutes'] ?? '0') ?>">
              <div class="form-hint">0 = unbegrenzt. Mods/Admins können immer.</div>
            </div>
            <div class="form-row">
              <label>Bot-Erkennung</label>
              <select name="bot_detection_level">
                <?php $bd = $settings['bot_detection_level'] ?? 'medium'; ?>
                <option value="off"    <?= $bd === 'off'    ? 'selected' : '' ?>>🚫 Aus</option>
                <option value="low"    <?= $bd === 'low'    ? 'selected' : '' ?>>🟢 Locker</option>
                <option value="medium" <?= $bd === 'medium' ? 'selected' : '' ?>>🟡 Normal</option>
                <option value="high"   <?= $bd === 'high'   ? 'selected' : '' ?>>🔴 Streng</option>
              </select>
              <div class="form-hint">Honeypots, Submission-Timing, User-Agent-Checks.</div>
            </div>
            <div class="form-row">
              <label>Beiträge pro Seite</label>
              <input type="number" name="posts_per_page" min="5" max="100"
                     value="<?= e($settings['posts_per_page'] ?? '20') ?>">
            </div>
            <div class="form-row">
              <label>Threads pro Seite</label>
              <input type="number" name="threads_per_page" min="5" max="100"
                     value="<?= e($settings['threads_per_page'] ?? '25') ?>">
            </div>
          </div>
        </div>

        <?php /* ═══ 3. MODERATION & SICHERHEIT ═══════════════════════ */ ?>
        <div class="form-card" style="margin-bottom:16px">
          <h2>🛡️ Moderation & Sicherheit</h2>

          <div class="form-row">
            <label>Auto-Bann ab X Warnungs-Punkten</label>
            <input type="number" name="warning_ban_threshold" min="0" max="100"
                   value="<?= e($settings['warning_ban_threshold'] ?? '10') ?>">
            <div class="form-hint">Sammelt ein User so viele aktive Punkte, wird er automatisch gesperrt. 0 = deaktiviert.</div>
          </div>

          <div class="form-row">
            <label>
              <input type="checkbox" name="show_ips_to_mods" value="1"
                <?= ($settings['show_ips_to_mods'] ?? '1') === '1' ? 'checked' : '' ?>>
              IP-Adressen für Moderatoren sichtbar
            </label>
            <div class="form-hint">Wenn aus: nur Admins/Owner sehen IPs (z.B. im IP-Lookup).</div>
          </div>

          <div class="form-row">
            <label>
              <input type="checkbox" name="notify_mods_on_report" value="1"
                <?= ($settings['notify_mods_on_report'] ?? '1') === '1' ? 'checked' : '' ?>>
              Mods bei neuer Meldung benachrichtigen
            </label>
            <div class="form-hint">Sendet eine In-App-Benachrichtigung an alle Mods/Admins bei einem neuen Report.</div>
          </div>

          <h3 style="margin-top:18px;font-size:14px;color:var(--text-muted)">Anti-Spam Rate-Limits</h3>
          <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px">
            <div class="form-row">
              <label>Cooldown zwischen Beiträgen (Sek.)</label>
              <input type="number" name="post_cooldown_seconds" min="0" max="600"
                     value="<?= e($settings['post_cooldown_seconds'] ?? '15') ?>">
            </div>
            <div class="form-row">
              <label>Max. Login-Versuche / 15 Min.</label>
              <input type="number" name="rate_limit_login" min="1" max="100"
                     value="<?= e($settings['rate_limit_login'] ?? '5') ?>">
            </div>
            <div class="form-row">
              <label>Max. Registrierungen / Std.</label>
              <input type="number" name="rate_limit_register" min="1" max="100"
                     value="<?= e($settings['rate_limit_register'] ?? '3') ?>">
            </div>
          </div>

          <h3 style="margin-top:18px;font-size:14px;color:var(--text-muted)">🤖 CAPTCHA (Bot-Schutz)</h3>
          <p style="font-size:12px;color:var(--text-muted);margin-bottom:10px">
            Adaptive Anti-Bot-Schicht. Versteckt im Hintergrund aktiv. Mathe-CAPTCHA erscheint
            nur bei wiederholt fehlgeschlagenen Versuchen. Kein Google-reCAPTCHA – komplett DSGVO-konform.
          </p>
          <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px">
            <div class="form-row">
              <label>CAPTCHA aktiv</label>
              <select name="captcha_enabled">
                <option value="1" <?= ($settings['captcha_enabled'] ?? '1') === '1' ? 'selected' : '' ?>>✅ An</option>
                <option value="0" <?= ($settings['captcha_enabled'] ?? '1') === '0' ? 'selected' : '' ?>>❌ Aus</option>
              </select>
            </div>
            <div class="form-row">
              <label>CAPTCHA nach … Fehlversuchen</label>
              <input type="number" name="captcha_threshold" min="1" max="20"
                     value="<?= e($settings['captcha_threshold'] ?? '3') ?>">
              <div class="form-hint">Mathe-Aufgabe wird ab dem N-ten Fehler gezeigt.</div>
            </div>
            <div class="form-row">
              <label>Min. Submit-Zeit (ms)</label>
              <input type="number" name="captcha_min_time" min="0" max="10000" step="100"
                     value="<?= e($settings['captcha_min_time'] ?? '1500') ?>">
              <div class="form-hint">Formular schneller als X ms = Bot.</div>
            </div>
          </div>
        </div>

        <?php /* ═══ 4. RECHTLICHES & DATENSCHUTZ ═════════════════════ */ ?>
        <div class="form-card" style="margin-bottom:16px">
          <h2>🍪 Cookie-Hinweis (optional)</h2>
          <div class="form-row">
            <label>
              <input type="checkbox" name="cookie_banner_enabled" value="1"
                <?= ($settings['cookie_banner_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
              Cookie-Banner anzeigen
            </label>
            <div class="form-hint">Zeigt Besuchern einen einmaligen Hinweis. Wird per Cookie/localStorage gemerkt.</div>
          </div>
          <div class="form-row">
            <label>Banner-Text</label>
            <textarea name="cookie_banner_text" style="min-height:60px"
                      placeholder="Diese Seite nutzt funktionale Cookies (z.B. für den Login). Mit der weiteren Nutzung erklärst du dich damit einverstanden."><?= e($settings['cookie_banner_text'] ?? '') ?></textarea>
            <div class="form-hint">Wenn leer: Standard-Text wird verwendet.</div>
          </div>
        </div>

        <button type="submit" class="btn btn-primary">💾 Einstellungen speichern</button>
      </form>

    <?php /* ════════════ IP-LOOKUP ════════════ */ ?>
    <?php /* ════════════ NEU v0.18.49: 📧 E-MAIL (SMTP) ════════════ */ ?>
    <?php elseif ($action === 'email'): ?>
      <h2>📧 E-Mail-Versand (SMTP)</h2>

      <div class="form-card" style="margin-bottom:16px">
        <p style="color:var(--text-muted);font-size:14px;line-height:1.6">
          Wird für Passwort-Reset, E-Mail-Verifikation und Benachrichtigungen benötigt.
          Wenn kein SMTP-Server konfiguriert ist, fällt das System auf die PHP-mail()-Funktion zurück.
        </p>

        <form method="post" action="?action=save_settings">
          <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
          <input type="hidden" name="settings_section" value="email">

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
            <div class="form-row">
              <label>SMTP-Host</label>
              <input type="text" name="smtp_host" value="<?= e($settings['smtp_host'] ?? '') ?>"
                     placeholder="smtp-relay.brevo.com">
              <div class="form-hint">Leer lassen für PHP-mail()-Fallback.</div>
            </div>
            <div class="form-row">
              <label>Verbindung</label>
              <select name="smtp_connection">
                <?php
                  $port = (string) ($settings['smtp_port'] ?? '587');
                  $enc  = (string) ($settings['smtp_encryption'] ?? 'tls');
                  $cur  = ($enc === 'ssl') ? 'ssl' : (($port === '2525') ? 'tls2525' : (($enc === '' || $enc === 'none') ? 'none' : 'tls'));
                ?>
                <option value="tls"     <?= $cur === 'tls'     ? 'selected' : '' ?>>🔒 STARTTLS (Port 587)</option>
                <option value="ssl"     <?= $cur === 'ssl'     ? 'selected' : '' ?>>🔒 SSL/TLS (Port 465)</option>
                <option value="tls2525" <?= $cur === 'tls2525' ? 'selected' : '' ?>>🔒 STARTTLS (Port 2525)</option>
                <option value="none"    <?= $cur === 'none'    ? 'selected' : '' ?>>⚠️ Unverschlüsselt</option>
              </select>
              <div class="form-hint">Falls 587 nicht funktioniert (Hoster-Firewall): Port 2525 probieren.</div>
            </div>
            <div class="form-row">
              <label>SMTP-Benutzername</label>
              <input type="text" name="smtp_user" value="<?= e($settings['smtp_user'] ?? '') ?>"
                     placeholder="dein@account.com">
            </div>
            <div class="form-row">
              <label>SMTP-Passwort / API-Key</label>
              <input type="password" name="smtp_pass"
                     placeholder="<?= empty($settings['smtp_pass']) ? 'noch nicht gesetzt' : '••••••••••••' ?>">
              <div class="form-hint">
                Leer lassen = bestehendes Passwort behalten.
                <?php if (!empty($cryptReady)): ?>
                  Wird verschlüsselt in der Datenbank abgelegt.
                <?php else: ?>
                  <strong>Liegt unverschlüsselt in der Datenbank.</strong>
                <?php endif; ?>
              </div>
            </div>
            <div class="form-row">
              <label>Absender-E-Mail</label>
              <?php if (defined('FORUM_MAIL_FROM')): ?>
                <input type="email" value="<?= e(FORUM_MAIL_FROM) ?>" disabled>
                <div class="form-hint">
                  Diese Installation hat den Absender in der <code>config.php</code>
                  festgelegt. Er lässt sich hier nicht ändern — der Versand läuft über
                  den Server des Betreibers, und dessen Zustellbarkeit hängt daran.
                </div>
              <?php else: ?>
              <input type="email" name="smtp_from" value="<?= e($settings['smtp_from'] ?? '') ?>"
                     placeholder="noreply@deine-domain.de">
              <div class="form-hint">
                <strong>Gilt auch ohne SMTP</strong>, also auch beim Versand über
                <code>mail()</code>. Leer lassen baut eine Adresse aus der Forum-Adresse —
                das geht schief, sobald das Forum auf einer Subdomain läuft: SPF und
                DKIM hängen fast immer an der Domain ohne Vorsatz, und eine Mail von
                einer Subdomain ohne eigene Einträge geht unbeglaubigt raus.
                Nimm eine Adresse der Domain, für die Versand eingerichtet ist.
              </div>
              <?php endif; ?>
            </div>
            <div class="form-row">
              <label>Absender-Name</label>
              <?php if (defined('FORUM_MAIL_FROM_NAME')): ?>
                <input type="text" value="<?= e(FORUM_MAIL_FROM_NAME) ?>" disabled>
                <div class="form-hint">In der <code>config.php</code> festgelegt.</div>
              <?php else: ?>
              <input type="text" name="smtp_from_name" value="<?= e($settings['smtp_from_name'] ?? '') ?>"
                     placeholder="<?= e(FORUM_NAME) ?>">
              <?php endif; ?>
            </div>
          </div>

          <?php if (empty($cryptReady) && !empty($cryptKeyVorschlag)): ?>
            <div class="note" style="margin-top:14px">
              <strong>🔐 Passwort verschlüsselt ablegen</strong>
              <p style="margin:6px 0">
                Diese Installation hat noch keinen Schlüssel, das SMTP-Passwort liegt
                deshalb im Klartext in der Datenbank. Nimm diese Zeile in deine
                <code>config.php</code> auf und speichere das Passwort danach einmal neu:
              </p>
              <pre style="white-space:pre-wrap;word-break:break-all;background:var(--bg-body);padding:10px;border-radius:6px;margin:0"><code>define('FORUM_CRYPT_KEY', '<?= e($cryptKeyVorschlag) ?>');</code></pre>
              <p style="margin:8px 0 0;font-size:13px">
                Den Schlüssel danach <strong>nicht mehr ändern</strong> und beim Umzug
                mitnehmen — sonst ist das Gespeicherte nicht mehr lesbar. Er schützt gegen
                einen abhandengekommenen Datenbank-Abzug, nicht gegen jemanden, der den
                Server hat: der liest die <code>config.php</code> mit.
              </p>
            </div>
          <?php endif; ?>

          <button type="submit" class="btn btn-primary" style="margin-top:14px">💾 SMTP-Einstellungen speichern</button>
        </form>
      </div>

      <?php /* Die IDs gehoeren zum Skript in forum.js: es schickt die Adresse
               an ?action=send_test_mail und schreibt die Antwort in den Hinweis
               darunter. Vorher stand hier ein Formular auf send_test_email —
               diese Aktion gibt es nicht, der Knopf lief also ins Leere. */ ?>
      <div class="form-card">
        <h3 style="margin-top:0">🧪 Test-Mail senden</h3>
        <p style="font-size:13px;color:var(--text-muted);margin-bottom:14px">
          Sendet eine Test-Mail mit den aktuellen Einstellungen an die angegebene Adresse.
          <strong>Speichere zuerst</strong> deine Änderungen.
        </p>
        <div class="form-row">
          <label>An welche Adresse?</label>
          <div style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">
            <input type="email" id="test-mail-to" placeholder="deine@email.de" style="flex:1;min-width:200px">
            <button type="button" id="send-test-mail-btn" class="btn btn-ghost">📤 Test senden</button>
          </div>
          <div class="form-hint" id="test-mail-status"></div>
        </div>
      </div>

    <?php /* ════════════ NEU v0.18.49: 📝 REGISTRIERUNG ════════════ */ ?>
    <?php elseif ($action === 'registration'): ?>
      <h2>📝 Registrierungs-Einstellungen</h2>

      <form method="post" action="?action=save_settings">
        <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
        <input type="hidden" name="settings_section" value="registration">

        <div class="form-card" style="margin-bottom:16px">
          <h3 style="margin-top:0">Registrierungs-Modus</h3>
          <div class="form-row">
            <label>
              <input type="checkbox" name="registration_enabled" value="1"
                <?= ($settings['registration_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
              Registrierungen erlauben
            </label>
            <div class="form-hint">Wenn deaktiviert: nur bestehende User können sich einloggen.</div>
          </div>
          <div class="form-row">
            <label>
              <input type="checkbox" name="email_verification_required" value="1"
                <?= ($settings['email_verification_required'] ?? '0') === '1' ? 'checked' : '' ?>>
              E-Mail-Bestätigung bei Registrierung erforderlich
            </label>
            <div class="form-hint">User können sich erst einloggen, nachdem sie ihre E-Mail bestätigt haben. Benötigt funktionierende SMTP-Konfiguration (siehe 📧 E-Mail).</div>
          </div>
          <div class="form-row">
            <label>
              <input type="checkbox" name="registration_approval_required" value="1"
                <?= ($settings['registration_approval_required'] ?? '0') === '1' ? 'checked' : '' ?>>
              Admin-Freigabe für neue Registrierungen erforderlich
            </label>
            <div class="form-hint">Neue Accounts müssen vom Admin-Team freigeschaltet werden. Empfohlen für Test-/Privat-Foren.</div>
          </div>
          <?php if (($settings['registration_approval_required'] ?? '0') === '1'): ?>
            <div style="background:rgba(74,157,74,0.06);padding:14px;border-radius:6px;border-left:3px solid #4a9d4a;margin:6px 0 14px">
              <div style="font-weight:600;margin-bottom:6px">✉️ Einladungs-Codes</div>
              <p style="font-size:12px;color:var(--text-muted);margin:0 0 10px;line-height:1.5">
                Du kannst Personen einladen die du persönlich kennst – sie umgehen die Admin-Freigabe-Pflicht.
              </p>
              <a href="?action=invitations" class="btn btn-ghost btn-sm">→ Einladungen verwalten</a>
            </div>
          <?php endif; ?>
          <div class="form-row">
            <label>
              <input type="checkbox" name="registration_terms_required" value="1"
                <?= ($settings['registration_terms_required'] ?? '1') === '1' ? 'checked' : '' ?>>
              Zustimmung zu Nutzungsbedingungen erforderlich
            </label>
            <div class="form-hint">User müssen bei der Registrierung den <a href="?action=legal">Nutzungsbedingungen</a> aktiv zustimmen (DSGVO-konform).</div>
          </div>
        </div>

        <div class="form-card" style="margin-bottom:16px">
          <h3 style="margin-top:0">Benutzername & Passwort</h3>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
            <div class="form-row">
              <label>Min. Benutzernamen-Länge</label>
              <input type="number" name="min_username_length" min="2" max="50"
                     value="<?= e($settings['min_username_length'] ?? '3') ?>">
            </div>
            <div class="form-row">
              <label>Min. Passwortlänge</label>
              <input type="number" name="min_password_length" min="4" max="100"
                     value="<?= e($settings['min_password_length'] ?? '6') ?>">
            </div>
          </div>
        </div>

        <button type="submit" class="btn btn-primary">💾 Registrierungs-Einstellungen speichern</button>
      </form>

    <?php /* ════════════ NEU v0.18.49: 🛠️ WARTUNGSMODUS ════════════ */ ?>
    <?php elseif ($action === 'maintenance_mode'): ?>
      <h2>🛠️ Wartungsmodus</h2>

      <form method="post" action="?action=save_settings">
        <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
        <input type="hidden" name="settings_section" value="maintenance_mode">

        <div class="form-card" style="margin-bottom:16px">
          <p style="color:var(--text-muted);font-size:14px;line-height:1.6;margin-bottom:14px">
            Wenn der Wartungsmodus aktiv ist, sehen normale Besucher eine Wartungs-Seite.
            Admins und Owner können das Forum weiterhin normal nutzen, um Updates oder Reparaturen durchzuführen.
          </p>

          <div class="form-row">
            <label style="font-size:15px">
              <input type="checkbox" name="maintenance_mode" value="1"
                <?= ($settings['maintenance_mode'] ?? '0') === '1' ? 'checked' : '' ?>
                style="transform:scale(1.3);margin-right:8px">
              <strong>Wartungsmodus aktivieren</strong>
            </label>
            <div class="form-hint">
              ⚠️ <strong>Wichtig:</strong> Bevor du den Wartungsmodus aktivierst, stelle sicher dass du als Admin eingeloggt bist
              und bleibst — sonst sperrst du dich selbst aus. Notausgang: phpMyAdmin → SQL →
              <code>UPDATE settings SET setting_value='0' WHERE setting_key='maintenance_mode';</code>
            </div>
          </div>

          <div class="form-row">
            <label>Wartungs-Nachricht</label>
            <textarea name="maintenance_message" style="min-height:80px"
                      placeholder="Wir sind kurz weg und basteln am Forum. Bis gleich!"><?= e($settings['maintenance_message'] ?? '') ?></textarea>
            <div class="form-hint">Wird normalen Besuchern angezeigt. Wenn leer, kommt ein Standard-Text.</div>
          </div>
        </div>

        <button type="submit" class="btn btn-primary">💾 Wartungs-Einstellungen speichern</button>
      </form>

      <?php if (($settings['maintenance_mode'] ?? '0') === '1'): ?>
        <div class="form-card" style="margin-top:16px;border-left:3px solid var(--color-danger);background:rgba(220,38,38,0.05)">
          <h3 style="margin-top:0;color:var(--color-danger)">🚨 Wartungsmodus ist AKTIV</h3>
          <p style="line-height:1.6">
            Normale Besucher sehen aktuell die Wartungs-Seite. Du bist als Admin eingeloggt und siehst das Forum normal.
            <strong>Logge dich nicht aus, ohne den Wartungsmodus vorher zu deaktivieren!</strong>
          </p>
        </div>
      <?php endif; ?>

    <?php elseif ($action === 'iplookup'): ?>
      <h2>🌐 IP-Verwaltung</h2>
      <p style="font-size:13px;color:var(--text-muted);margin-bottom:14px">
        Suche nach Benutzern und Beiträgen anhand einer IP, oder verwalte gesperrte IP-Adressen.
      </p>

      <?php /* ─── Sub-Tabs: Lookup vs Banns ─── */ ?>
      <?php $ipTab = $_GET['sub'] ?? 'lookup'; ?>
      <div class="card" style="margin-bottom:18px;padding:4px;display:inline-flex;gap:2px;background:var(--bg-surface2)">
        <a href="?action=iplookup&sub=lookup<?= !empty($lookupIP) ? '&ip=' . urlencode($lookupIP) : '' ?>"
           class="btn btn-ghost btn-sm" style="<?= $ipTab === 'lookup' ? 'background:var(--bg-surface);font-weight:600' : '' ?>">
          🔍 IP-Suche
        </a>
        <a href="?action=iplookup&sub=banns"
           class="btn btn-ghost btn-sm" style="<?= $ipTab === 'banns' ? 'background:var(--bg-surface);font-weight:600' : '' ?>">
          🚫 IP-Banns (<?= count($ipBans) ?>)
        </a>
      </div>

      <?php if ($ipTab === 'lookup'): ?>
        <?php /* ─── LOOKUP-TAB ─── */ ?>
        <form method="get" style="margin-bottom:20px">
          <input type="hidden" name="action" value="iplookup">
          <input type="hidden" name="sub" value="lookup">
          <div style="display:flex;gap:8px">
            <input type="text" name="ip" value="<?= e($lookupIP) ?>"
                   placeholder="z.B. 192.168.1.1"
                   style="flex:1;padding:8px 12px;border:1px solid var(--border);border-radius:6px;background:var(--bg-surface);color:var(--text-base)">
            <button type="submit" class="btn btn-primary">Suchen</button>
          </div>
        </form>

        <?php if (!empty($lookupIP)): ?>
          <div class="section-heading">
            👤 Benutzer mit dieser IP (<?= count($lookupUsers) ?>)
            <a href="?action=iplookup&sub=banns&prefill=<?= e(urlencode($lookupIP)) ?>" class="btn btn-danger btn-sm">🚫 IP sperren</a>
          </div>
          <div class="card" style="margin-bottom:16px">
            <?php if (empty($lookupUsers)): ?>
              <div style="padding:16px;text-align:center;color:var(--text-faint)">Keine Benutzer gefunden.</div>
            <?php else: ?>
            <table class="forum-table">
              <thead><tr><th>Benutzer</th><th>E-Mail</th><th>Registriert</th><th>Status</th></tr></thead>
              <tbody>
                <?php foreach ($lookupUsers as $u): ?>
                <tr>
                  <td><a href="<?= e(FORUM_URL) ?>/profile.php?id=<?= (int) $u['user_id'] ?>"><?= e($u['username']) ?></a></td>
                  <td style="font-size:12px"><?= e($u['email']) ?></td>
                  <td style="font-size:12px"><?= e(format_date($u['registered_at'])) ?></td>
                  <td><?= $u['is_banned'] ? '<span style="color:var(--color-danger)">Gesperrt</span>' : 'Aktiv' ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            <?php endif; ?>
          </div>

          <div class="section-heading">📝 Beiträge von dieser IP (<?= count($lookupPosts) ?>)</div>
          <div class="card">
            <?php if (empty($lookupPosts)): ?>
              <div style="padding:16px;text-align:center;color:var(--text-faint)">Keine Beiträge gefunden.</div>
            <?php else: ?>
            <table class="forum-table">
              <thead><tr><th>Thread</th><th>Autor</th><th>Datum</th></tr></thead>
              <tbody>
                <?php foreach ($lookupPosts as $p): ?>
                <tr>
                  <td><a href="<?= e(FORUM_URL) ?>/thread.php?id=<?= (int) $p['thread_id'] ?>#post<?= (int) $p['post_id'] ?>"><?= e($p['thread_title']) ?></a></td>
                  <td><a href="<?= e(FORUM_URL) ?>/profile.php?id=<?= (int) $p['user_id'] ?>"><?= e($p['username']) ?></a></td>
                  <td style="font-size:12px;color:var(--text-muted)"><?= e(format_date($p['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            <?php endif; ?>
          </div>
        <?php endif; ?>

      <?php else: /* ─── BANNS-TAB ─── */ ?>
        <div class="form-card" style="margin-bottom:16px">
          <h3 style="margin-top:0">➕ IP sperren</h3>
          <form method="post" action="?action=ban_ip">
            <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
            <div class="form-row">
              <label>IP-Adresse</label>
              <input type="text" name="ip" value="<?= e($_GET['prefill'] ?? '') ?>" placeholder="z.B. 192.168.1.1" required>
            </div>
            <div class="form-row">
              <label>Grund (optional)</label>
              <input type="text" name="reason" placeholder="z.B. Spam, Trolling…">
            </div>
            <div class="form-row">
              <label>Läuft ab am (optional)</label>
              <input type="datetime-local" name="expires">
              <div class="form-hint">Leer lassen für permanenten Bann.</div>
            </div>
            <button type="submit" class="btn btn-danger">🚫 Sperren</button>
          </form>
        </div>

        <div class="section-heading">Aktive IP-Banns (<?= count($ipBans) ?>)</div>
        <div class="card">
          <?php if (empty($ipBans)): ?>
            <div style="padding:20px;text-align:center;color:var(--text-faint)">Keine IP-Banns vorhanden.</div>
          <?php else: ?>
          <table class="forum-table">
            <thead><tr><th>IP</th><th>Grund</th><th>Gesperrt von</th><th>Seit</th><th>Läuft ab</th><th>Aktion</th></tr></thead>
            <tbody>
              <?php foreach ($ipBans as $b):
                $expired = $b['expires_at'] && strtotime($b['expires_at']) < time();
              ?>
              <tr <?= $expired ? 'style="opacity:.5"' : '' ?>>
                <td><code><?= e($b['ip']) ?></code></td>
                <td style="font-size:12px"><?= e($b['reason'] ?: '–') ?></td>
                <td><?= e($b['banned_by_name']) ?></td>
                <td style="font-size:12px;color:var(--text-muted)"><?= e(format_date($b['created_at'])) ?></td>
                <td style="font-size:12px">
                  <?php if ($b['expires_at']): ?>
                    <?= $expired ? '<span style="color:var(--color-warning)">abgelaufen</span>' : e(format_date($b['expires_at'])) ?>
                  <?php else: ?>
                    <span style="color:var(--color-danger)">permanent</span>
                  <?php endif; ?>
                </td>
                <td>
                  <a href="?action=unban_ip&id=<?= (int) $b['ban_id'] ?>&csrf=<?= e($csrfToken) ?>"
                     class="btn btn-ghost btn-xs" onclick="return confirm('Bann aufheben?')">Aufheben</a>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <?php endif; ?>
        </div>
      <?php endif; /* end ipTab */ ?>

    <?php /* ════════════ IP-BANNS ════════════ */ ?>
    <?php elseif ($action === 'ipbans'): ?>
      <h2>🚫 IP-Banns</h2>
      <div class="form-card" style="margin-bottom:16px">
        <h2>➕ IP sperren</h2>
        <form method="post" action="?action=ban_ip">
          <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
          <div class="form-row">
            <label>IP-Adresse</label>
            <input type="text" name="ip" value="<?= e($_GET['prefill'] ?? '') ?>" placeholder="z.B. 192.168.1.1" required>
          </div>
          <div class="form-row">
            <label>Grund (optional)</label>
            <input type="text" name="reason" placeholder="z.B. Spam, Trolling…">
          </div>
          <div class="form-row">
            <label>Läuft ab am (optional)</label>
            <input type="datetime-local" name="expires">
            <div class="form-hint">Leer lassen für permanenten Bann.</div>
          </div>
          <button type="submit" class="btn btn-danger">🚫 Sperren</button>
        </form>
      </div>

      <div class="section-heading">Aktive IP-Banns (<?= count($ipBans) ?>)</div>
      <div class="card">
        <?php if (empty($ipBans)): ?>
          <div style="padding:20px;text-align:center;color:var(--text-faint)">Keine IP-Banns vorhanden.</div>
        <?php else: ?>
        <table class="forum-table">
          <thead><tr><th>IP</th><th>Grund</th><th>Gesperrt von</th><th>Seit</th><th>Läuft ab</th><th>Aktion</th></tr></thead>
          <tbody>
            <?php foreach ($ipBans as $b):
              $expired = $b['expires_at'] && strtotime($b['expires_at']) < time();
            ?>
            <tr <?= $expired ? 'style="opacity:.5"' : '' ?>>
              <td><code><?= e($b['ip']) ?></code></td>
              <td style="font-size:12px"><?= e($b['reason'] ?: '–') ?></td>
              <td><?= e($b['banned_by_name']) ?></td>
              <td style="font-size:12px;color:var(--text-muted)"><?= e(format_date($b['created_at'])) ?></td>
              <td style="font-size:12px">
                <?php if ($b['expires_at']): ?>
                  <?= $expired ? '<span style="color:var(--color-warning)">abgelaufen</span>' : e(format_date($b['expires_at'])) ?>
                <?php else: ?>
                  <span style="color:var(--color-danger)">permanent</span>
                <?php endif; ?>
              </td>
              <td>
                <a href="?action=unban_ip&id=<?= (int) $b['ban_id'] ?>&csrf=<?= e($csrfToken) ?>"
                   class="btn btn-ghost btn-xs" onclick="return confirm('Bann aufheben?')">Aufheben</a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>

    <?php /* ════════════ STATISTIK ════════════ */ ?>
    <?php elseif ($action === 'statistics'): ?>
      <h2>📈 Statistik</h2>

      <div class="stat-grid" style="margin-bottom:18px">
        <div class="stat-box">
          <div class="stat-icon">⌀</div>
          <div class="stat-num"><?= number_format($avgPostsPerUser, 1, ',', '.') ?></div>
          <div class="stat-label">Ø Beiträge pro User</div>
        </div>
        <div class="stat-box">
          <div class="stat-icon">🟢</div>
          <div class="stat-num"><?= (int) $activeLastWeek ?></div>
          <div class="stat-label">Aktiv letzte 7 Tage</div>
        </div>
      </div>

      <div class="section-heading">📊 Registrierungen letzte 30 Tage</div>
      <div class="card" style="margin-bottom:16px">
        <?php if (empty($userRegs30d)): ?>
          <div style="padding:16px;text-align:center;color:var(--text-faint)">Keine Daten.</div>
        <?php else:
          $max = max(array_column($userRegs30d, 'n'));
        ?>
        <div style="padding:16px">
          <?php foreach ($userRegs30d as $row):
            $w = $max > 0 ? (int) round((int) $row['n'] / $max * 100) : 0;
          ?>
          <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px;font-size:12px">
            <span style="width:90px;color:var(--text-muted)"><?= e($row['d']) ?></span>
            <div style="flex:1;background:var(--bg-surface2);border-radius:4px;height:18px;position:relative;overflow:hidden">
              <div style="background:var(--color-accent);height:100%;width:<?= $w ?>%;border-radius:4px"></div>
            </div>
            <span style="width:30px;text-align:right;font-weight:600"><?= (int) $row['n'] ?></span>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <div class="section-heading">📝 Beiträge letzte 30 Tage</div>
      <div class="card" style="margin-bottom:16px">
        <?php if (empty($posts30d)): ?>
          <div style="padding:16px;text-align:center;color:var(--text-faint)">Keine Daten.</div>
        <?php else:
          $max = max(array_column($posts30d, 'n'));
        ?>
        <div style="padding:16px">
          <?php foreach ($posts30d as $row):
            $w = $max > 0 ? (int) round((int) $row['n'] / $max * 100) : 0;
          ?>
          <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px;font-size:12px">
            <span style="width:90px;color:var(--text-muted)"><?= e($row['d']) ?></span>
            <div style="flex:1;background:var(--bg-surface2);border-radius:4px;height:18px;position:relative;overflow:hidden">
              <div style="background:var(--color-success);height:100%;width:<?= $w ?>%;border-radius:4px"></div>
            </div>
            <span style="width:30px;text-align:right;font-weight:600"><?= (int) $row['n'] ?></span>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <div class="section-heading">🏆 Top 10 Poster</div>
      <div class="card" style="margin-bottom:16px">
        <table class="forum-table">
          <thead><tr><th>#</th><th>Benutzer</th><th>Rang</th><th>Beiträge</th></tr></thead>
          <tbody>
            <?php foreach ($topPosters as $i => $u): ?>
            <tr>
              <td style="font-weight:700;color:var(--text-faint)"><?= $i + 1 ?></td>
              <td><a href="<?= e(FORUM_URL) ?>/profile.php?id=<?= (int) $u['user_id'] ?>"><?= e($u['username']) ?></a></td>
              <td><?= e($u['rank']) ?></td>
              <td><?= number_format((int) $u['post_count'], 0, ',', '.') ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="section-heading">🔥 Aktivste Foren</div>
      <div class="card" style="margin-bottom:16px">
        <table class="forum-table">
          <thead><tr><th>Forum</th><th>Threads</th><th>Posts</th></tr></thead>
          <tbody>
            <?php foreach ($activeBoards as $b): ?>
            <tr>
              <td><a href="<?= e(FORUM_URL) ?>/board.php?id=<?= (int) $b['board_id'] ?>"><?= e($b['title']) ?></a></td>
              <td><?= (int) $b['thread_count'] ?></td>
              <td><?= (int) $b['post_count'] ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="section-heading">🌡️ Aktivitäts-Heatmap (letzte 60 Tage)</div>
      <div class="card" style="padding:16px;margin-bottom:16px">
        <p style="font-size:11px;color:var(--text-muted);margin-bottom:10px">
          Wann wird im Forum gepostet? Dunklere Felder = mehr Aktivität.
        </p>
        <div style="display:grid;grid-template-columns:auto repeat(24, 1fr);gap:2px;font-size:9px">
          <div></div>
          <?php for ($h = 0; $h < 24; $h++): ?>
            <div style="text-align:center;color:var(--text-faint);padding:2px 0"><?= $h ?></div>
          <?php endfor; ?>
          <?php $days = ['Mo','Di','Mi','Do','Fr','Sa','So'];
          foreach ($days as $d => $label): ?>
            <div style="color:var(--text-muted);font-weight:600;padding:2px 6px 2px 0;text-align:right"><?= $label ?></div>
            <?php for ($h = 0; $h < 24; $h++):
              $v = $heatmap[$d][$h];
              $alpha = $v > 0 ? max(0.1, $v / $heatMax) : 0;
              $bg = $v > 0 ? "background:rgba(34,197,94,$alpha)" : 'background:var(--bg-surface2)';
            ?>
              <div style="<?= $bg ?>;height:18px;border-radius:3px" title="<?= $label ?> <?= $h ?>:00 – <?= $v ?> Posts"></div>
            <?php endfor; ?>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="section-heading">💎 Engagement-Stats</div>
      <div class="stat-grid" style="margin-bottom:0">
        <div class="stat-box">
          <div class="stat-icon">👍</div>
          <div class="stat-num"><?= number_format((int) ($totalReactions ?? 0), 0, ',', '.') ?></div>
          <div class="stat-label">Reaktionen gesamt</div>
        </div>
        <div class="stat-box">
          <div class="stat-icon">🔖</div>
          <div class="stat-num"><?= number_format((int) ($totalBookmarks ?? 0), 0, ',', '.') ?></div>
          <div class="stat-label">Lesezeichen</div>
        </div>
      </div>

    <?php /* ════════════ MELDUNGEN ════════════ */ ?>
    <?php elseif ($action === 'reports'): ?>
      <h2>🚨 Meldungen</h2>

      <div style="display:flex;gap:6px;margin-bottom:14px;flex-wrap:wrap">
        <a href="?action=reports&status=open"      class="btn btn-sm <?= $reportStatus === 'open' ? 'btn-primary' : 'btn-ghost' ?>">🔴 Offen (<?= (int) $counts['open'] ?>)</a>
        <a href="?action=reports&status=reviewed"  class="btn btn-sm <?= $reportStatus === 'reviewed' ? 'btn-primary' : 'btn-ghost' ?>">👁️ Geprüft (<?= (int) $counts['reviewed'] ?>)</a>
        <a href="?action=reports&status=actioned"  class="btn btn-sm <?= $reportStatus === 'actioned' ? 'btn-primary' : 'btn-ghost' ?>">✅ Bearbeitet (<?= (int) $counts['actioned'] ?>)</a>
        <a href="?action=reports&status=dismissed" class="btn btn-sm <?= $reportStatus === 'dismissed' ? 'btn-primary' : 'btn-ghost' ?>">❌ Abgelehnt (<?= (int) $counts['dismissed'] ?>)</a>
      </div>

      <?php if (empty($reports)): ?>
        <div class="card" style="padding:30px;text-align:center;color:var(--text-faint)">
          Keine Meldungen in dieser Kategorie. 🎉
        </div>
      <?php endif; ?>

      <?php foreach ($reports as $r): ?>
        <div class="card" style="margin-bottom:12px">
          <div class="card-header">
            <span class="card-title">
              <?= e($reasons[$r['reason']] ?? $r['reason']) ?>
              <span style="font-weight:400;color:var(--text-muted);font-size:11px;margin-left:6px">
                #<?= (int) $r['report_id'] ?>
              </span>
            </span>
            <span style="font-size:11px;color:var(--text-muted)">
              <?= e(format_date($r['created_at'])) ?>
            </span>
          </div>

          <div style="padding:12px 16px;font-size:13px">
            <div style="margin-bottom:8px;color:var(--text-muted)">
              📨 Gemeldet von: <a href="<?= e(FORUM_URL) ?>/profile.php?id=<?= (int) $r['reporter_id'] ?>"><?= e($r['reporter_name']) ?></a>
            </div>
            <?php if (!empty($r['comment'])): ?>
              <div style="padding:8px 12px;background:var(--bg-surface2);border-left:3px solid var(--border-strong);border-radius:0 6px 6px 0;margin-bottom:10px;font-size:12px;font-style:italic">
                💬 <?= e($r['comment']) ?>
              </div>
            <?php endif; ?>

            <div style="border:1px solid var(--border);border-radius:6px;padding:10px 12px;background:var(--bg-surface2);margin-bottom:10px">
              <div style="font-size:11px;color:var(--text-muted);margin-bottom:4px">
                Beitrag von <a href="<?= e(FORUM_URL) ?>/profile.php?id=<?= (int) $r['author_id'] ?>"><?= e($r['author_name']) ?></a>
                in „<a href="<?= e(FORUM_URL) ?>/thread.php?id=<?= (int) $r['thread_id'] ?>#post<?= (int) $r['post_id'] ?>"><?= e($r['thread_title']) ?></a>"
              </div>
              <div style="font-size:12px;color:var(--text-base)">
                <?= e(mb_substr(strip_tags($r['post_content']), 0, 300)) ?><?= mb_strlen($r['post_content']) > 300 ? '…' : '' ?>
              </div>
            </div>

            <?php if ($r['status'] === 'open'): ?>
              <div style="display:flex;gap:6px;flex-wrap:wrap">
                <a href="?action=resolve_report&id=<?= (int) $r['report_id'] ?>&status=reviewed&csrf=<?= e($csrfToken) ?>"
                   class="btn btn-ghost btn-sm">👁️ Geprüft</a>
                <a href="?action=resolve_report&id=<?= (int) $r['report_id'] ?>&status=dismissed&csrf=<?= e($csrfToken) ?>"
                   class="btn btn-ghost btn-sm">❌ Abweisen</a>
                <a href="?action=warnings&user=<?= (int) $r['author_id'] ?>" class="btn btn-danger btn-sm">⚠️ Warnen</a>
                <a href="<?= e(FORUM_URL) ?>/mod_action.php?action=delete_post&post=<?= (int) $r['post_id'] ?>&csrf=<?= e($csrfToken) ?>"
                   class="btn btn-danger btn-sm"
                   onclick="return confirm('Beitrag löschen?')">🗑️ Beitrag löschen</a>
                <a href="?action=resolve_report&id=<?= (int) $r['report_id'] ?>&status=actioned&csrf=<?= e($csrfToken) ?>"
                   class="btn btn-primary btn-sm">✅ Erledigt</a>
              </div>
            <?php else: ?>
              <div style="font-size:11px;color:var(--text-muted)">
                Bearbeitet von <?= e($r['reviewer_name'] ?? '–') ?> am <?= e(format_date($r['reviewed_at'])) ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>

    <?php /* ════════════ WARNUNGEN ════════════ */ ?>
    <?php elseif ($action === 'warnings'): ?>
      <h2>⚠️ Warnungen</h2>

      <p style="font-size:13px;color:var(--text-muted);margin-bottom:14px">
        Auto-Bann ab <strong><?= (int) $threshold ?></strong> Punkten
        (änderbar in den <a href="?action=settings">Einstellungen</a>).
      </p>

      <?php if ($warnUser): ?>
        <div class="card" style="margin-bottom:16px">
          <div class="card-header">
            <span class="card-title">
              Warnungen für
              <a href="<?= e(FORUM_URL) ?>/profile.php?id=<?= (int) $warnUser['user_id'] ?>"><?= e($warnUser['username']) ?></a>
            </span>
            <span style="font-size:12px">
              Aktuell: <strong style="color:<?= (int) $warnUser['warning_points'] >= $threshold ? 'var(--color-danger)' : 'var(--color-warning)' ?>">
                <?= (int) $warnUser['warning_points'] ?> / <?= (int) $threshold ?> Pkt
              </strong>
            </span>
          </div>
          <div style="padding:16px">
            <form method="post" action="?action=issue_warning">
              <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
              <input type="hidden" name="user_id" value="<?= (int) $warnUser['user_id'] ?>">
              <div style="display:grid;grid-template-columns:80px 1fr;gap:10px;margin-bottom:10px">
                <div class="form-row" style="margin:0">
                  <label>Punkte</label>
                  <input type="number" name="points" min="1" max="20" value="1" required>
                </div>
                <div class="form-row" style="margin:0">
                  <label>Grund</label>
                  <input type="text" name="reason" required placeholder="z.B. Spam, Beleidigung, Trolling…">
                </div>
              </div>
              <div class="form-row">
                <label>Läuft ab (optional)</label>
                <input type="datetime-local" name="expires">
                <div class="form-hint">Leer = Warnung gilt unbegrenzt.</div>
              </div>
              <button type="submit" class="btn btn-danger">⚠️ Warnung aussprechen</button>
            </form>
          </div>
        </div>

        <?php if (!empty($warningsList)): ?>
          <div class="section-heading">Bisherige Warnungen</div>
          <div class="card">
            <table class="forum-table">
              <thead>
                <tr><th>Punkte</th><th>Grund</th><th>Von</th><th>Datum</th><th>Status</th><th></th></tr>
              </thead>
              <tbody>
                <?php foreach ($warningsList as $w):
                  $expired = $w['expires_at'] && strtotime($w['expires_at']) < time();
                  $active  = $w['is_active'] && !$expired;
                ?>
                  <tr <?= !$active ? 'style="opacity:.5"' : '' ?>>
                    <td><strong><?= (int) $w['points'] ?></strong></td>
                    <td><?= e($w['reason']) ?></td>
                    <td><?= e($w['mod_name']) ?></td>
                    <td style="font-size:12px;color:var(--text-muted)"><?= e(format_date($w['created_at'])) ?></td>
                    <td style="font-size:11px">
                      <?php if (!$w['is_active']): ?>
                        <span style="color:var(--color-success)">aufgehoben</span>
                      <?php elseif ($expired): ?>
                        <span style="color:var(--text-faint)">abgelaufen</span>
                      <?php else: ?>
                        <span style="color:var(--color-warning)">aktiv</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php if ($active): ?>
                        <a href="?action=revoke_warning&id=<?= (int) $w['warning_id'] ?>&user=<?= (int) $warnUser['user_id'] ?>&csrf=<?= e($csrfToken) ?>"
                           class="btn btn-ghost btn-xs">Aufheben</a>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>

      <?php else: ?>
        <div class="section-heading">Benutzer mit Warnungen</div>
        <div class="card">
          <?php if (empty($recentWarned)): ?>
            <div style="padding:30px;text-align:center;color:var(--text-faint)">
              Keine User mit aktiven Warnungen. 🎉
            </div>
          <?php else: ?>
            <table class="forum-table">
              <thead><tr><th>Benutzer</th><th>Punkte</th><th>Letzte Warnung</th><th>Status</th><th></th></tr></thead>
              <tbody>
                <?php foreach ($recentWarned as $u): ?>
                  <tr>
                    <td><a href="<?= e(FORUM_URL) ?>/profile.php?id=<?= (int) $u['user_id'] ?>"><?= e($u['username']) ?></a></td>
                    <td><strong style="color:<?= (int) $u['warning_points'] >= $threshold ? 'var(--color-danger)' : 'var(--color-warning)' ?>"><?= (int) $u['warning_points'] ?></strong></td>
                    <td style="font-size:12px"><?= e(format_date($u['last_warning'])) ?></td>
                    <td><?= $u['is_banned'] ? '<span style="color:var(--color-danger)">🚫 Gesperrt</span>' : 'Aktiv' ?></td>
                    <td><a href="?action=warnings&user=<?= (int) $u['user_id'] ?>" class="btn btn-ghost btn-xs">Details</a></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>

        <p style="margin-top:14px;font-size:13px;color:var(--text-muted)">
          💡 Um einen anderen User zu warnen, gehe zu seinem Profil oder Benutzer-Bereich → „Warnen".
        </p>
      <?php endif; ?>

    <?php /* ════════════ TAGS ════════════ */ ?>
    <?php elseif ($action === 'tags'): ?>
      <h2>🏷️ Tag-Verwaltung</h2>

      <div class="card" style="margin-bottom:14px">
        <?php if (empty($tagsList)): ?>
          <div style="padding:20px;text-align:center;color:var(--text-faint)">Keine Tags vorhanden.</div>
        <?php else: ?>
          <table class="forum-table">
            <thead><tr><th>Tag</th><th>Slug</th><th>Beschreibung</th><th>Verwendung</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($tagsList as $tg): ?>
                <tr>
                  <td><span class="tag-chip" style="border-color:<?= e($tg['color']) ?>;color:<?= e($tg['color']) ?>"><?= e($tg['name']) ?></span></td>
                  <td><code><?= e($tg['slug']) ?></code></td>
                  <td style="font-size:12px"><?= e($tg['description'] ?: '–') ?></td>
                  <td><?= (int) $tg['usage_count'] ?>×</td>
                  <td>
                    <a href="?action=delete_tag&id=<?= (int) $tg['tag_id'] ?>&csrf=<?= e($csrfToken) ?>"
                       class="btn btn-danger btn-xs"
                       onclick="return confirm('Tag löschen? (Threads behalten Inhalt)')">Löschen</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>

      <div class="form-card">
        <h2>➕ Neuer Tag</h2>
        <form method="post" action="?action=add_tag">
          <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div class="form-row" style="margin:0">
              <label>Slug (URL)</label>
              <input type="text" name="slug" required pattern="[a-z0-9]+" placeholder="z.B. tutorial">
              <div class="form-hint">Nur Kleinbuchstaben + Ziffern.</div>
            </div>
            <div class="form-row" style="margin:0">
              <label>Name</label>
              <input type="text" name="name" required placeholder="z.B. Tutorial">
            </div>
          </div>
          <div class="form-row">
            <label>Beschreibung / Emoji</label>
            <input type="text" name="description" placeholder="z.B. 📖">
          </div>
          <div class="form-row">
            <label>Farbe</label>
            <input type="color" name="color" value="#888888">
          </div>
          <button type="submit" class="btn btn-primary">Tag anlegen</button>
        </form>
      </div>

    <?php /* ════════════ MOD-LOG ════════════ */ ?>
    <?php elseif ($action === 'modlog'): ?>
      <h2>📜 Moderations-Log</h2>
      <div class="card">
        <table class="forum-table">
          <thead><tr><th>Datum</th><th>Moderator</th><th>Aktion</th><th>Typ</th><th>Ziel</th><th>Grund</th></tr></thead>
          <tbody>
            <?php if (empty($modLog)): ?>
              <tr><td colspan="6" style="text-align:center;color:var(--text-faint);padding:20px">Keine Einträge.</td></tr>
            <?php endif; ?>
            <?php foreach ($modLog as $log): ?>
            <tr>
              <td style="font-size:12px;color:var(--text-muted)"><?= e(format_date($log['created_at'])) ?></td>
              <td><a href="<?= e(FORUM_URL) ?>/profile.php?id=<?= (int) $log['mod_id'] ?>"><?= e($log['username']) ?></a></td>
              <td><?= e($log['action']) ?></td>
              <td><?= e($log['target_type']) ?></td>
              <td>#<?= (int) $log['target_id'] ?></td>
              <td style="font-size:12px"><?= e($log['reason'] ?: '–') ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

    <?php /* ════════════ FREIGABE-WARTESCHLANGE ════════════ */ ?>
    <?php elseif ($action === 'invitations'): ?>
      <h2>✉️ Einladungen</h2>

      <?php if (!user_approval_required()): ?>
        <div class="card" style="padding:16px;margin-bottom:14px;background:rgba(170,102,68,0.1);border-left:3px solid #aa6644">
          <strong>⚠️ Hinweis:</strong> Admin-Freigabe für Registrierungen ist aktuell <strong>nicht aktiv</strong>.
          Einladungen funktionieren weiterhin, aber sie sind nur dann nützlich wenn neue User normalerweise
          in die Freigabe-Queue kämen.
          <a href="?action=registration">→ Registrierungs-Einstellungen öffnen</a>
        </div>
      <?php endif; ?>

      <div class="card" style="padding:18px 22px;margin-bottom:18px">
        <h3 style="margin:0 0 12px;font-size:15px">➕ Neue Einladung erstellen</h3>
        <form method="post" action="?action=invitation_create">
          <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
            <div class="form-row">
              <label>E-Mail-Adresse (optional)</label>
              <input type="email" name="email" placeholder="freund@example.com">
              <div class="form-hint">
                Wenn angegeben: Code ist nur für diese E-Mail-Adresse gültig.
                Leer = universal (jeder mit dem Code kann ihn nutzen).
              </div>
            </div>
            <div class="form-row">
              <label>Gültigkeit (Tage)</label>
              <input type="number" name="valid_days" value="7" min="1" max="365">
              <div class="form-hint">Standard: 7 Tage. Max. 365.</div>
            </div>
          </div>
          <div class="form-row">
            <label>Interne Notiz (optional)</label>
            <input type="text" name="note" maxlength="255" placeholder="z.B. „Schulfreund Markus"">
            <div class="form-hint">Nur für dich – wird nicht versendet.</div>
          </div>
          <div class="form-row">
            <label style="display:flex;align-items:center;gap:8px">
              <input type="checkbox" name="send_mail" value="1" checked>
              Einladungs-Mail direkt versenden (nur wenn E-Mail angegeben)
            </label>
          </div>
          <button type="submit" class="btn btn-primary">✉️ Einladung erstellen</button>
        </form>
      </div>

      <h3 style="margin:24px 0 10px;font-size:15px">📋 Alle Einladungen (<?= count($invitations) ?>)</h3>

      <?php if (empty($invitations)): ?>
        <div class="card" style="padding:30px;text-align:center;color:var(--text-muted)">
          Noch keine Einladungen erstellt.
        </div>
      <?php else: ?>
        <div style="display:grid;gap:10px">
          <?php foreach ($invitations as $inv):
              $status = invitation_status($inv);
              $statusLabel = [
                  'valid'    => ['🟢 Aktiv',      '#4a9d4a'],
                  'used'     => ['✓ Eingelöst',  '#888'],
                  'expired'  => ['⏰ Abgelaufen', '#aa6644'],
                  'revoked'  => ['🚫 Widerrufen', '#aa4444'],
              ][$status] ?? ['?', '#888'];
          ?>
            <div class="card" style="padding:14px 18px">
              <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:14px;flex-wrap:wrap">
                <div style="flex:1;min-width:200px">
                  <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px">
                    <code style="font-size:15px;font-weight:bold;background:var(--bg-surface2);padding:4px 8px;border-radius:4px;letter-spacing:2px"><?= e($inv['code']) ?></code>
                    <span style="font-size:12px;color:<?= $statusLabel[1] ?>;font-weight:600"><?= $statusLabel[0] ?></span>
                  </div>
                  <div style="font-size:12px;color:var(--text-muted);line-height:1.7">
                    <?php if (!empty($inv['email'])): ?>
                      📧 Für: <strong><?= e($inv['email']) ?></strong><br>
                    <?php else: ?>
                      🌍 Universal (jeder mit Code)<br>
                    <?php endif; ?>
                    <?php if (!empty($inv['note'])): ?>
                      📝 <?= e($inv['note']) ?><br>
                    <?php endif; ?>
                    Erstellt: <?= e(format_date($inv['created_at'])) ?>
                    <?php if (!empty($inv['creator_username'])): ?>
                      von <?= e($inv['creator_username']) ?>
                    <?php endif; ?>
                    <br>
                    <?php if ($status === 'valid'): ?>
                      ⏰ Gültig bis: <strong><?= e(format_date($inv['expires_at'])) ?></strong>
                    <?php elseif ($status === 'used'): ?>
                      ✓ Eingelöst von <strong><?= e($inv['used_by_username'] ?? '?') ?></strong>
                      am <?= e(format_date($inv['used_at'])) ?>
                    <?php elseif ($status === 'expired'): ?>
                      ⏰ Abgelaufen am <?= e(format_date($inv['expires_at'])) ?>
                    <?php elseif ($status === 'revoked'): ?>
                      🚫 Widerrufen am <?= e(format_date($inv['revoked_at'])) ?>
                    <?php endif; ?>
                    <?php if (!empty($inv['mail_sent_at'])): ?>
                      <br>✉️ Mail versendet am <?= e(format_date($inv['mail_sent_at'])) ?>
                    <?php endif; ?>
                  </div>
                </div>

                <?php if ($status === 'valid'): ?>
                  <div style="display:flex;gap:6px;flex-direction:column">
                    <?php if (!empty($inv['email'])): ?>
                      <form method="post" action="?action=invitation_resend" style="margin:0">
                        <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
                        <input type="hidden" name="invitation_id" value="<?= (int) $inv['invitation_id'] ?>">
                        <button type="submit" class="btn btn-ghost btn-sm" title="Einladungs-Mail erneut versenden">✉️ Resend</button>
                      </form>
                    <?php endif; ?>
                    <form method="post" action="?action=invitation_revoke" style="margin:0"
                          onsubmit="return confirm('Diese Einladung widerrufen? Der Code wird ungültig.')">
                      <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
                      <input type="hidden" name="invitation_id" value="<?= (int) $inv['invitation_id'] ?>">
                      <button type="submit" class="btn btn-danger btn-sm">🚫 Widerrufen</button>
                    </form>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

    <?php elseif ($action === 'approvals_users'): ?>
      <h2>🆕 Wartende Registrierungs-Freigaben</h2>

      <?php if (empty($pendingUsers)): ?>
        <div class="card" style="padding:40px;text-align:center">
          <div style="font-size:48px;margin-bottom:12px">✅</div>
          <strong style="font-size:16px">Keine wartenden Registrierungen</strong>
          <p style="font-size:13px;color:var(--text-muted);margin-top:8px">
            Sobald sich jemand registriert (mit aktiver Admin-Freigabe-Pflicht), erscheint hier ein Eintrag.
          </p>
          <p style="font-size:12px;color:var(--text-faint);margin-top:14px">
            Status: Admin-Freigabe ist
            <?php if (user_approval_required()): ?>
              <strong style="color:#4a9d4a">aktiv</strong>.
            <?php else: ?>
              <strong style="color:#aa6644">inaktiv</strong>.
              <a href="?action=registration">→ Aktivieren</a>
            <?php endif; ?>
          </p>
        </div>
      <?php else: ?>
        <div style="margin-bottom:14px;font-size:13px;color:var(--text-muted)">
          <?= count($pendingUsers) ?> Account<?= count($pendingUsers) === 1 ? '' : 's' ?> warten auf Freigabe.
          Bei „Ablehnen" wird der Account gelöscht und der User per E-Mail mit Begründung informiert.
        </div>

        <?php foreach ($pendingUsers as $u): ?>
          <div class="card" style="margin-bottom:12px;padding:16px 20px">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;margin-bottom:12px;flex-wrap:wrap">
              <div style="flex:1;min-width:200px">
                <h3 style="font-size:16px;margin-bottom:6px"><?= e($u['username']) ?></h3>
                <div style="font-size:12px;color:var(--text-muted);line-height:1.7">
                  📧 <?= e($u['email']) ?>
                  <?php if (!empty($u['email_verified'])): ?>
                    <span style="color:#4a9d4a">✓ verifiziert</span>
                  <?php else: ?>
                    <span style="color:#aa6644">⊘ unverifiziert</span>
                  <?php endif; ?>
                  <br>
                  🌐 IP: <code style="font-size:11px"><?= e($u['registration_ip'] ?? '?') ?></code>
                  <br>
                  🕒 Registriert: <?= e(format_date($u['registered_at'])) ?>
                </div>
              </div>

              <form method="post" action="?action=approve_user_action" style="margin:0"
                    onsubmit="return confirm('Diesen Account freigeben? Der User bekommt eine Bestätigungs-Mail.')">
                <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="user_id" value="<?= (int) $u['user_id'] ?>">
                <button type="submit" class="btn btn-primary btn-sm">✓ Freigeben</button>
              </form>
            </div>

            <details style="margin-top:8px">
              <summary style="cursor:pointer;font-size:12px;color:var(--text-muted)">
                ❌ Account ablehnen (mit Begründung)
              </summary>
              <form method="post" action="?action=reject_user_action" style="margin-top:12px"
                    onsubmit="return confirm('Diesen Account ablehnen und löschen? Der User bekommt eine E-Mail mit der Begründung. Aktion kann nicht rückgängig gemacht werden.')">
                <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="user_id" value="<?= (int) $u['user_id'] ?>">
                <div class="form-row">
                  <label style="font-size:12px">Begründung (wird dem User per E-Mail mitgeteilt) <span style="color:#c44">*</span></label>
                  <textarea name="reason" rows="3" required maxlength="500"
                            placeholder="z.B.: Account erscheint automatisiert (Bot). Wenn das ein Irrtum war, melde dich gerne über die Kontaktseite."></textarea>
                </div>
                <button type="submit" class="btn btn-danger btn-sm">❌ Ablehnen & löschen</button>
              </form>
            </details>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>

    <?php elseif ($action === 'approval'): ?>
      <h2>🚦 Freigabe-Warteschlange</h2>

      <?php if (empty($pendingThreads) && empty($pendingPosts)): ?>
        <div class="card" style="padding:40px;text-align:center">
          <div style="font-size:48px;margin-bottom:12px">✅</div>
          <strong style="font-size:16px">Alles freigegeben!</strong>
          <p style="font-size:13px;color:var(--text-muted);margin-top:8px">
            Keine wartenden Beiträge.
          </p>
        </div>
      <?php endif; ?>

      <?php /* ── Wartende Themen ── */ ?>
      <?php if (!empty($pendingThreads)): ?>
        <div class="section-heading">🆕 Neue Themen (<?= count($pendingThreads) ?>)</div>
        <?php foreach ($pendingThreads as $t): ?>
          <div class="card" style="margin-bottom:12px;padding:16px 20px">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;margin-bottom:10px">
              <div>
                <div style="font-size:11px;color:var(--text-muted);margin-bottom:4px">
                  In <strong><?= e($t['board_title']) ?></strong> · vor <?= e(format_date($t['created_at'])) ?>
                </div>
                <h3 style="font-size:16px;margin-bottom:6px"><?= e($t['title']) ?></h3>
                <div style="font-size:12px;color:var(--text-muted)">
                  Von <a href="<?= e(FORUM_URL) ?>/profile.php?id=<?= (int) $t['user_id'] ?>"><strong><?= e($t['username']) ?></strong></a>
                </div>
              </div>
              <div style="display:flex;gap:6px;flex-shrink:0">
                <a href="?action=approve_thread&id=<?= (int) $t['thread_id'] ?>&csrf=<?= e($csrfToken) ?>"
                   class="btn btn-primary btn-sm"
                   onclick="return confirm('Thema freigeben?')">✅ Freigeben</a>
                <a href="?action=reject_thread&id=<?= (int) $t['thread_id'] ?>&csrf=<?= e($csrfToken) ?>&reason=<?= urlencode('Verstoß gegen Forenregeln') ?>"
                   class="btn btn-danger btn-sm"
                   onclick="return confirm('Thema ablehnen und löschen?')">🗑️ Ablehnen</a>
              </div>
            </div>
            <?php if (!empty($t['content'])): ?>
              <div style="padding:12px 14px;background:var(--bg-surface2);border-radius:var(--radius-sm);font-size:13px;max-height:160px;overflow-y:auto">
                <?= bbcode(mb_substr($t['content'], 0, 500)) ?>
                <?php if (mb_strlen($t['content']) > 500): ?>…<?php endif; ?>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>

      <?php /* ── Wartende Antworten ── */ ?>
      <?php if (!empty($pendingPosts)): ?>
        <div class="section-heading" style="margin-top:24px">💬 Neue Antworten (<?= count($pendingPosts) ?>)</div>
        <?php foreach ($pendingPosts as $p): ?>
          <div class="card" style="margin-bottom:12px;padding:16px 20px">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;margin-bottom:10px">
              <div>
                <div style="font-size:11px;color:var(--text-muted);margin-bottom:4px">
                  In <strong><?= e($p['board_title']) ?></strong> · Thread:
                  <a href="<?= e(FORUM_URL) ?>/thread.php?id=<?= (int) $p['thread_id'] ?>"><?= e($p['thread_title']) ?></a>
                  · vor <?= e(format_date($p['created_at'])) ?>
                </div>
                <div style="font-size:12px;color:var(--text-muted)">
                  Von <a href="<?= e(FORUM_URL) ?>/profile.php?id=<?= (int) $p['user_id'] ?>"><strong><?= e($p['username']) ?></strong></a>
                </div>
              </div>
              <div style="display:flex;gap:6px;flex-shrink:0">
                <a href="?action=approve_post&id=<?= (int) $p['post_id'] ?>&csrf=<?= e($csrfToken) ?>"
                   class="btn btn-primary btn-sm"
                   onclick="return confirm('Beitrag freigeben?')">✅ Freigeben</a>
                <a href="?action=reject_post&id=<?= (int) $p['post_id'] ?>&csrf=<?= e($csrfToken) ?>&reason=<?= urlencode('Verstoß gegen Forenregeln') ?>"
                   class="btn btn-danger btn-sm"
                   onclick="return confirm('Beitrag ablehnen und löschen?')">🗑️ Ablehnen</a>
              </div>
            </div>
            <div style="padding:12px 14px;background:var(--bg-surface2);border-radius:var(--radius-sm);font-size:13px;max-height:160px;overflow-y:auto">
              <?= bbcode(mb_substr($p['content'], 0, 500)) ?>
              <?php if (mb_strlen($p['content']) > 500): ?>…<?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>

    <?php /* ════════════ DESIGN ════════════ */ ?>
    <?php elseif ($action === 'design'): ?>
      <h2>🎨 Design</h2>
      <p style="font-size:13px;color:var(--text-muted);margin-bottom:14px">
        Farben, Schriften und Form deines Forums. Änderungen werden links live in der Vorschau gezeigt.
        Erst wenn du auf <strong>"💾 Speichern"</strong> klickst, werden sie für alle Nutzer wirksam.
      </p>

      <?php if ($hasPreview ?? false): ?>
        <div class="alert alert-warning" style="margin-bottom:14px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
          <span>⚠️ Du hast eine ungespeicherte Vorschau aktiv. Andere Admins sehen die jetzt auch (in deiner Session).</span>
          <a href="?action=clear_preview&csrf=<?= e($csrfToken) ?>" class="btn btn-ghost btn-sm">Vorschau verwerfen</a>
        </div>
      <?php endif; ?>

      <div class="design-editor">
        <?php /* ─── Controls (linke Seite) ─── */ ?>
        <form method="post" action="?action=save_design" id="design-form" class="design-controls">
          <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">

          <div class="design-tabs">
            <button type="button" class="design-tab-btn active" data-tab="identity">🖼️ Identität</button>
            <button type="button" class="design-tab-btn" data-tab="colors">🌈 Farben</button>
            <button type="button" class="design-tab-btn" data-tab="fonts">✍️ Schrift</button>
            <button type="button" class="design-tab-btn" data-tab="form">📐 Form</button>
            <button type="button" class="design-tab-btn" data-tab="layout">📐 Layout</button>
            <button type="button" class="design-tab-btn" data-tab="presets">🎁 Presets</button>
            <button type="button" class="design-tab-btn" data-tab="advanced">🛠️ Erweitert</button>
          </div>

          <?php /* ═══ TAB: IDENTITÄT (neu) ═══════════════════════ */ ?>
          <div class="design-tab-pane active" data-pane="identity">
            <h3>Forum-Name & Slogan</h3>
            <div class="form-row">
              <label>Forum-Name</label>
              <input type="text" name="forum_name" value="<?= e($branding['forum_name']) ?>"
                     placeholder="<?= e(defined('FORUM_NAME') ? FORUM_NAME : 'Forum') ?>" maxlength="100"
                     data-live="forum_name">
              <div class="form-hint">Erscheint im Browser-Titel, Header, Footer und in E-Mails.</div>
            </div>

            <div class="form-row">
              <label>Slogan / Tagline</label>
              <input type="text" name="forum_slogan" value="<?= e($branding['forum_slogan']) ?>"
                     placeholder="z.B. Die Community für Bastler &amp; Tüftler" maxlength="150"
                     data-live="forum_slogan">
              <div class="form-hint">Erscheint rechts neben dem Logo im Banner.</div>
            </div>

            <h3 style="margin-top:18px">Logo</h3>
            <div style="padding:14px;background:var(--bg-surface2);border-radius:var(--radius-sm);text-align:center;margin-bottom:10px">
              <div style="font-size:11px;color:var(--text-muted);margin-bottom:8px;text-transform:uppercase;letter-spacing:.5px">
                Aktuelle Vorschau
                <?php if (function_exists('is_default_logo') && is_default_logo()): ?>
                  <span style="color:var(--color-warning)">· Standard-Logo</span>
                <?php endif; ?>
              </div>
              <?php // Dasselbe wie im Kopf der Seite, sonst zeigt die Vorschau etwas anderes als die Besucher sehen ?>
              <div style="max-height:100px;color:var(--text-base)"
                   class="logo-preview"><?= forum_logo_render('Logo-Vorschau') ?></div>
            </div>
            <style>.logo-preview svg, .logo-preview img { max-height:100px; max-width:100%; height:auto }</style>
            <div class="form-row">
              <label>Logo-URL (optional)</label>
              <input type="text" name="forum_logo" value="<?= e($branding['forum_logo']) ?>"
                     placeholder="z.B. assets/logo.png oder https://example.com/logo.svg">
              <div class="form-hint">
                Wenn leer: <code>assets/logo.png</code>, <code>assets/logo.svg</code> etc. wird probiert.
                Empfohlene Höhe: ca. 100px.
              </div>
            </div>

            <h3 style="margin-top:18px">Akzentfarbe</h3>
            <div class="form-row">
              <?php
                $currentAccent = $branding['forum_accent_color'];
                $accentColors = [
                  ['4f46e5', 'Indigo (Standard)'],
                  ['7c3aed', 'Lila'],
                  ['db2777', 'Pink'],
                  ['dc2626', 'Rot'],
                  ['ea580c', 'Orange'],
                  ['ca8a04', 'Gold'],
                  ['16a34a', 'Grün'],
                  ['0891b2', 'Cyan'],
                  ['2563eb', 'Blau'],
                  ['475569', 'Schiefer'],
                ];
              ?>
              <div class="color-picker-grid">
                <?php foreach ($accentColors as [$hex, $label]): ?>
                  <label class="color-option <?= $currentAccent === $hex ? 'selected' : '' ?>">
                    <input type="radio" name="forum_accent_color" value="<?= e($hex) ?>"
                           <?= $currentAccent === $hex ? 'checked' : '' ?>
                           style="position:absolute;opacity:0;pointer-events:none"
                           data-live="forum_accent_color">
                    <span class="color-swatch" style="background:#<?= e($hex) ?>"></span>
                    <span class="color-label"><?= e($label) ?></span>
                  </label>
                <?php endforeach; ?>
              </div>
              <div class="form-hint">Bestimmt die Highlight-Farbe (Buttons, Links, Akzente).</div>
            </div>

            <h3 style="margin-top:18px">Standard-Theme</h3>
            <div class="form-row">
              <select name="forum_default_theme" data-live="forum_default_theme">
                <option value="dark"  <?= $branding['forum_default_theme'] === 'dark'  ? 'selected' : '' ?>>🌑 Dark Mode</option>
                <option value="light" <?= $branding['forum_default_theme'] === 'light' ? 'selected' : '' ?>>☀️ Light Mode</option>
              </select>
              <div class="form-hint">
                Wird verwendet wenn ein User „Automatisch" als Theme gewählt hat.
                Eingeloggte User können in ihrem Profil ein eigenes Theme festlegen.
              </div>
            </div>
          </div>

          <?php /* ─── TAB: FARBEN ─── */ ?>
          <div class="design-tab-pane" data-pane="colors">
            <h3>🌑 Dark Mode</h3>
            <?php
              $darkColors = [
                'theme_color_bg_body'       => ['Body-Hintergrund',   'Hauptfarbe der Seite'],
                'theme_color_bg_surface'    => ['Cards',              'Karten, Formularblöcke'],
                'theme_color_bg_surface2'   => ['Tiefere Cards',      'Tabellen-Header, Code-Blöcke'],
                'theme_color_text_base'     => ['Schrift',            'Haupttext'],
                'theme_color_text_muted'    => ['Schrift dezent',     'Sekundärer Text'],
                'theme_color_text_faint'    => ['Schrift verblasst',  'Metadaten, Zeitangaben'],
                'theme_color_border'        => ['Border',             'Linien, Trennungen'],
                'theme_color_border_strong' => ['Border kräftig',     'Hover-Borders'],
              ];
              foreach ($darkColors as $key => [$label, $hint]):
                $val = $theme[$key] ?? '';
            ?>
              <div class="color-input-row">
                <label><?= e($label) ?>
                  <span class="form-hint" style="font-weight:400"><?= e($hint) ?></span>
                </label>
                <div class="color-input-wrap">
                  <input type="color" class="color-picker" value="#<?= e($val) ?>" data-target="<?= e($key) ?>">
                  <input type="text" name="<?= e($key) ?>" value="<?= e($val) ?>" maxlength="6" pattern="[a-fA-F0-9]{6}" class="color-text" data-color-text>
                </div>
              </div>
            <?php endforeach; ?>

            <h3 style="margin-top:18px">☀️ Light Mode</h3>
            <?php
              $lightColors = [
                'theme_color_bg_body_light'       => ['Body-Hintergrund',   ''],
                'theme_color_bg_surface_light'    => ['Cards',              ''],
                'theme_color_bg_surface2_light'   => ['Tiefere Cards',      ''],
                'theme_color_text_base_light'     => ['Schrift',            ''],
                'theme_color_text_muted_light'    => ['Schrift dezent',     ''],
                'theme_color_text_faint_light'    => ['Schrift verblasst',  ''],
                'theme_color_border_light'        => ['Border',             ''],
                'theme_color_border_strong_light' => ['Border kräftig',     ''],
              ];
              foreach ($lightColors as $key => [$label, $hint]):
                $val = $theme[$key] ?? '';
            ?>
              <div class="color-input-row">
                <label><?= e($label) ?></label>
                <div class="color-input-wrap">
                  <input type="color" class="color-picker" value="#<?= e($val) ?>" data-target="<?= e($key) ?>">
                  <input type="text" name="<?= e($key) ?>" value="<?= e($val) ?>" maxlength="6" pattern="[a-fA-F0-9]{6}" class="color-text" data-color-text>
                </div>
              </div>
            <?php endforeach; ?>

            <h3 style="margin-top:18px">🚦 Status-Farben</h3>
            <?php
              $statusColors = [
                'theme_color_success' => ['Erfolg',  'Bestätigungen'],
                'theme_color_warning' => ['Warnung', 'Hinweise'],
                'theme_color_danger'  => ['Fehler',  'Lösch-Buttons, Fehler-Boxen'],
              ];
              foreach ($statusColors as $key => [$label, $hint]):
                $val = $theme[$key] ?? '';
            ?>
              <div class="color-input-row">
                <label><?= e($label) ?>
                  <span class="form-hint" style="font-weight:400"><?= e($hint) ?></span>
                </label>
                <div class="color-input-wrap">
                  <input type="color" class="color-picker" value="#<?= e($val) ?>" data-target="<?= e($key) ?>">
                  <input type="text" name="<?= e($key) ?>" value="<?= e($val) ?>" maxlength="6" pattern="[a-fA-F0-9]{6}" class="color-text" data-color-text>
                </div>
              </div>
            <?php endforeach; ?>
          </div>

          <?php /* ─── TAB: SCHRIFT ─── */ ?>
          <div class="design-tab-pane" data-pane="fonts">
            <h3>Schriftart</h3>
            <div class="form-row">
              <select name="theme_font_family" id="theme_font_family" data-live="theme_font_family">
                <?php
                  $fontOpts = [
                    'inter'     => '✨ Inter (Standard) – modern, gut lesbar',
                    'system'    => '🖥️ System-Schrift (jeweilige OS-Schrift)',
                    'roboto'    => '🤖 Roboto – Google-Schrift, klar',
                    'open-sans' => '📖 Open Sans – freundlich',
                    'lato'      => '🎯 Lato – semi-rund',
                    'nunito'    => '🍩 Nunito – verspielt, runde Formen',
                    'poppins'   => '⭕ Poppins – geometrisch, modern',
                    'chakra-petch' => '🎮 Chakra Petch – technisch, kantig',
                    'custom'    => '🛠️ Custom – eigene Schriftart',
                  ];
                  foreach ($fontOpts as $k => $lab):
                ?>
                  <option value="<?= e($k) ?>" <?= ($theme['theme_font_family'] ?? '') === $k ? 'selected' : '' ?>><?= e($lab) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-row" id="custom-font-row" style="<?= ($theme['theme_font_family'] ?? '') === 'custom' ? '' : 'display:none' ?>">
              <label>Custom Font-Stack</label>
              <input type="text" name="theme_font_custom" value="<?= e($theme['theme_font_custom'] ?? '') ?>"
                     placeholder="z.B. 'Comic Sans MS', cursive" data-live="theme_font_custom">
              <div class="form-hint">CSS-Font-Stack (Komma-getrennt, mit Quotes wenn der Name Leerzeichen enthält)</div>
            </div>

            <h3 style="margin-top:18px">Schriftgröße (Basis)</h3>
            <div class="form-row">
              <select name="theme_font_size_base" data-live="theme_font_size_base">
                <?php foreach ([12, 13, 14, 15, 16] as $sz): ?>
                  <option value="<?= $sz ?>" <?= (int) ($theme['theme_font_size_base'] ?? 14) === $sz ? 'selected' : '' ?>>
                    <?= $sz ?>px <?= $sz === 14 ? '(Standard)' : '' ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <div class="form-hint">Beeinflusst die Größe aller Texte im Forum proportional.</div>
            </div>
          </div>

          <?php /* ─── TAB: FORM ─── */ ?>
          <div class="design-tab-pane" data-pane="form">
            <h3>Rundungen</h3>
            <div class="form-row">
              <select name="theme_radius_preset" data-live="theme_radius_preset">
                <option value="sharp"  <?= ($theme['theme_radius_preset'] ?? '') === 'sharp'  ? 'selected' : '' ?>>⬜ Eckig – ganz scharf</option>
                <option value="small"  <?= ($theme['theme_radius_preset'] ?? '') === 'small'  ? 'selected' : '' ?>>🔲 Klein – leichte Rundung</option>
                <option value="medium" <?= ($theme['theme_radius_preset'] ?? '') === 'medium' ? 'selected' : '' ?>>🔘 Mittel (Standard)</option>
                <option value="large"  <?= ($theme['theme_radius_preset'] ?? '') === 'large'  ? 'selected' : '' ?>>⭕ Groß – sehr rund</option>
                <option value="pill"   <?= ($theme['theme_radius_preset'] ?? '') === 'pill'   ? 'selected' : '' ?>>💊 Pille – extra-rund</option>
              </select>
            </div>

            <h3 style="margin-top:18px">Maximalbreite</h3>
            <div class="form-row">
              <select name="theme_max_width" data-live="theme_max_width">
                <option value="980"  <?= (int) ($theme['theme_max_width'] ?? 1200) === 980  ? 'selected' : '' ?>>980px – schmal</option>
                <option value="1100" <?= (int) ($theme['theme_max_width'] ?? 1200) === 1100 ? 'selected' : '' ?>>1100px</option>
                <option value="1200" <?= (int) ($theme['theme_max_width'] ?? 1200) === 1200 ? 'selected' : '' ?>>1200px (Standard)</option>
                <option value="1400" <?= (int) ($theme['theme_max_width'] ?? 1200) === 1400 ? 'selected' : '' ?>>1400px</option>
                <option value="1600" <?= (int) ($theme['theme_max_width'] ?? 1200) === 1600 ? 'selected' : '' ?>>1600px – breit</option>
              </select>
              <div class="form-hint">Maximale Breite des Forum-Inhalts auf großen Bildschirmen.</div>
            </div>

            <h3 style="margin-top:18px">Dichte</h3>
            <div class="form-row">
              <select name="theme_density" data-live="theme_density">
                <option value="compact"     <?= ($theme['theme_density'] ?? '') === 'compact'     ? 'selected' : '' ?>>🤏 Kompakt</option>
                <option value="normal"      <?= ($theme['theme_density'] ?? '') === 'normal'      ? 'selected' : '' ?>>👌 Normal (Standard)</option>
                <option value="comfortable" <?= ($theme['theme_density'] ?? '') === 'comfortable' ? 'selected' : '' ?>>🛋️ Komfortabel</option>
              </select>
              <div class="form-hint">Beeinflusst Abstände und Padding in der UI.</div>
            </div>
          </div>

          <?php /* ═══ TAB: LAYOUT ═══════════════════════════════ */ ?>
          <div class="design-tab-pane" data-pane="layout">
            <h3>Header-Stil</h3>
            <div class="form-row">
              <select name="theme_header_style" data-live="theme_header_style">
                <option value="default"  <?= ($theme['theme_header_style'] ?? 'default') === 'default'  ? 'selected' : '' ?>>📋 Standard (Logo links, Suche + User-Menü rechts)</option>
                <option value="centered" <?= ($theme['theme_header_style'] ?? '') === 'centered' ? 'selected' : '' ?>>⚖️ Zentriert (Logo mittig, klassisch)</option>
                <option value="compact"  <?= ($theme['theme_header_style'] ?? '') === 'compact'  ? 'selected' : '' ?>>🤏 Kompakt (kleineres Banner)</option>
                <option value="inline"   <?= ($theme['theme_header_style'] ?? '') === 'inline'   ? 'selected' : '' ?>>📏 Eine Zeile (Logo, Menü und Suche nebeneinander)</option>
              </select>
              <div class="form-hint">Bestimmt die Anordnung im Forum-Header.</div>
            </div>

            <h3 style="margin-top:18px">📝 Footer-Text</h3>
            <div class="form-row">
              <label>Footer-Text (Zeile 1)</label>
              <input type="text" name="forum_footer_text" value="<?= e($branding['forum_footer_text']) ?>"
                     placeholder="© <?= date('Y') ?> &bull; Alle Rechte vorbehalten" maxlength="200">
              <div class="form-hint">
                Wenn leer: Standard-Copyright-Text mit Forum-Name. HTML wird escaped (nur Plain-Text).
              </div>
            </div>

            <div class="form-row">
              <label>Zusätzliche Footer-Links</label>
              <input type="text" name="forum_footer_links" value="<?= e($branding['forum_footer_links']) ?>"
                     placeholder="Discord|https://discord.gg/xyz, GitHub|https://github.com/...">
              <div class="form-hint">
                Format: <code>Label1|URL1, Label2|URL2</code> – kommagetrennt. Links öffnen in neuem Tab.
              </div>
            </div>

            <div class="form-row">
              <label>Quelltext-URL</label>
              <input type="url" name="source_url" value="<?= e($branding['source_url']) ?>"
                     placeholder="<?= e(FORUMIX_URL) ?>">
              <div class="form-hint">
                Ziel des Forumix-Zeichens im Fußbereich. Leer zeigt auf
                <code><?= e(FORUMIX_URL) ?></code>. <strong>Wenn du den Code geändert hast,
                gehört hier dein eigenes Repository hin</strong> — die AGPLv3 verlangt in § 13,
                dass Besucher an den Quelltext der laufenden Fassung kommen.
              </div>
            </div>
          </div>

          <?php /* ═══ TAB: PRESETS ═════════════════════════════ */ ?>
          <div class="design-tab-pane" data-pane="presets">
            <h3>Komplett-Themes mit einem Klick</h3>
            <p style="font-size:13px;color:var(--text-muted);margin-bottom:12px">
              Diese Presets überschreiben alle Farb-Einstellungen mit aufeinander abgestimmten Werten.
              Schriftart, Form und Identität bleiben unberührt. Du kannst nachher noch einzelne Farben anpassen.
            </p>

            <div class="theme-presets-grid">
              <?php
                $presets = [
                  'original' => [
                    'name' => '🌑 Original (Standard)', 'desc' => 'Neutral-schwarz mit Indigo-Akzent',
                    'preview' => ['#0a0a0b', '#131316', '#4f46e5', '#e7e7ea'],
                  ],
                  'discord' => [
                    'name' => '💬 Discord-Style', 'desc' => 'Blau-grau wie der Chat-Klassiker',
                    'preview' => ['#36393f', '#2f3136', '#5865f2', '#dcddde'],
                  ],
                  'mintgrun' => [
                    'name' => '🌿 Mintgrün', 'desc' => 'Frisch, ruhig, naturnah',
                    'preview' => ['#0f1f1c', '#162a25', '#10b981', '#e8f5e9'],
                  ],
                  'sonne' => [
                    'name' => '🌅 Sonnen-Orange', 'desc' => 'Warm, einladend, energisch',
                    'preview' => ['#1a0f08', '#2a1a10', '#f97316', '#fef3c7'],
                  ],
                  'cyberpunk' => [
                    'name' => '🌃 Cyberpunk', 'desc' => 'Knalliges Lila/Pink auf Schwarz',
                    'preview' => ['#0a0014', '#15001f', '#ec4899', '#e0c3fc'],
                  ],
                  'softlight' => [
                    'name' => '☀️ Soft Light', 'desc' => 'Helles, freundliches Theme',
                    'preview' => ['#f7f7f8', '#ffffff', '#4f46e5', '#18181b'],
                  ],
                ];
                foreach ($presets as $id => $p):
              ?>
                <div class="theme-preset" data-preset="<?= e($id) ?>">
                  <div class="theme-preset-preview">
                    <?php foreach ($p['preview'] as $color): ?>
                      <span style="background:<?= e($color) ?>"></span>
                    <?php endforeach; ?>
                  </div>
                  <div class="theme-preset-info">
                    <strong><?= e($p['name']) ?></strong>
                    <span><?= e($p['desc']) ?></span>
                  </div>
                  <button type="button" class="btn btn-primary btn-sm theme-preset-apply" data-preset="<?= e($id) ?>">Anwenden</button>
                </div>
              <?php endforeach; ?>
            </div>
            <div class="form-hint" style="margin-top:10px">
              💡 Nach „Anwenden" werden die Felder im Farben-Tab überschrieben.
              Schau dir die Vorschau an – wenn dir's gefällt, speichern. Sonst Tab wechseln und neu auswählen.
            </div>
          </div>

          <?php /* ═══ TAB: ERWEITERT ═══════════════════════════ */ ?>
          <div class="design-tab-pane" data-pane="advanced">
            <h3>🎨 Custom CSS <span style="font-size:11px;color:var(--color-warning);margin-left:6px">⚠️ Nur für Experten</span></h3>
            <div class="form-row">
              <label>Eigenes CSS (optional)</label>
              <textarea name="forum_custom_css" rows="12" style="font-family:var(--font-mono);font-size:12px"
                        placeholder=".thread-title-link { font-weight: 800 !important; }"><?= e($branding['forum_custom_css']) ?></textarea>
              <div class="form-hint">
                Dieses CSS wird direkt im <code>&lt;head&gt;</code> jeder Seite eingebunden.
                <strong>Vorsicht:</strong> Falsches CSS kann das Forum kaputt machen.
                Bei Problemen: einfach leeren und speichern.
              </div>
            </div>
          </div>

          <div class="design-actions">
            <button type="submit" class="btn btn-primary">💾 Speichern</button>
            <button type="button" id="reload-preview" class="btn btn-ghost">🔄 Vorschau neu laden</button>
            <button type="button" id="reset-design-btn" class="btn btn-ghost btn-sm" style="color:var(--color-warning);margin-left:auto">↺ Auf Standard zurücksetzen</button>
          </div>
        </form>

        <?php /* Reset-Form: AUSSERHALB des Design-Forms (sonst verschachtelt!) */ ?>
        <form method="post" action="?action=reset_design" id="reset-design-form" style="display:none">
          <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
        </form>

        <?php /* ─── Live-Vorschau (rechte Seite) ─── */ ?>
        <div class="design-preview">
          <div class="design-preview-label">
            <span>👁️ Live-Vorschau</span>
            <a href="<?= e(FORUM_URL) ?>/?preview=1" target="_blank" rel="noopener" title="In neuem Tab öffnen">↗ Großansicht</a>
          </div>
          <iframe id="design-preview-frame" src="<?= e(FORUM_URL) ?>/?preview=1"
                  style="width:100%;height:600px;border:1px solid var(--border);border-radius:var(--radius-sm);background:#fff"></iframe>
        </div>
      </div>

    <?php /* ════════════ MODULE ════════════ */ ?>
    <?php elseif ($action === 'modules'): ?>
      <h2>🧩 Module</h2>
      <p style="font-size:13px;color:var(--text-muted);margin-bottom:16px">
        Aktivierbare Erweiterungen für dein Forum. Beim Deaktivieren bleiben alle Daten erhalten – das Modul ist nur „aus".
      </p>

      <?php if (empty($modules)): ?>
        <div class="card" style="padding:40px;text-align:center">
          <div style="font-size:48px;margin-bottom:12px">📦</div>
          <strong>Keine Module gefunden</strong>
          <p style="font-size:13px;color:var(--text-muted);margin-top:8px">
            Module liegen unter <code>/modules/</code>. Stelle sicher dass dort mindestens ein gültiges Modul liegt.
          </p>
        </div>
      <?php else: ?>
        <div class="modules-grid">
          <?php foreach ($modules as $m): ?>
            <div class="module-card <?= $m['enabled'] ? 'is-enabled' : '' ?>">
              <div class="module-icon"><?= $m['icon'] ?></div>
              <div class="module-content">
                <div class="module-title"><?= e($m['name']) ?></div>
                <div class="module-desc"><?= e($m['description']) ?></div>
                <div class="module-meta">
                  <code>v<?= e($m['version']) ?></code> ·
                  von <?= e($m['author']) ?>
                  <?php if ($m['installed_at']): ?>
                    · installiert <?= e(format_date($m['installed_at'])) ?>
                  <?php endif; ?>
                </div>
              </div>
              <div class="module-toggle">
                <?php if ($m['enabled']): ?>
                  <a href="?action=module_toggle&code=<?= urlencode($m['code']) ?>&enable=0&csrf=<?= e($csrfToken) ?>"
                     class="btn btn-danger btn-sm"
                     onclick="return confirm('Modul „<?= e($m['name']) ?>“ deaktivieren?')">
                    🔴 Deaktivieren
                  </a>
                <?php else: ?>
                  <a href="?action=module_toggle&code=<?= urlencode($m['code']) ?>&enable=1&csrf=<?= e($csrfToken) ?>"
                     class="btn btn-primary btn-sm">
                    🟢 Aktivieren
                  </a>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

    <?php /* ════════════ UPDATES (DB-MIGRATIONEN) ════════════ */ ?>
    <?php elseif ($action === 'import'): ?>
      <h2>📦 Aus phpBB übernehmen</h2>

      <?php $b = $importBericht ?? null; $o = $importOpt ?? null; ?>

      <?php if (!empty($importLaeuft)): ?>
        <?php /* ── Ein Import steht bereits ── */ ?>
        <div class="card" style="padding:18px 22px;margin-bottom:18px;border-left:3px solid var(--color-success)">
          <h3 style="margin:0 0 10px">✅ Ein Import ist eingespielt</h3>
          <div class="stat-grid" style="margin-bottom:14px">
            <?php foreach (['user' => ['👤','Konten'], 'board' => ['📁','Foren'],
                            'thema' => ['💬','Themen'], 'beitrag' => ['✉️','Beiträge'],
                            'anhang' => ['📎','Anhänge']] as $art => [$icon, $label]): ?>
              <?php if (($importStand[$art] ?? 0) > 0): ?>
              <div class="stat-box">
                <div class="stat-icon"><?= $icon ?></div>
                <div class="stat-num"><?= number_format((int) $importStand[$art], 0, ',', '.') ?></div>
                <div class="stat-label"><?= e($label) ?></div>
              </div>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>
          <p style="font-size:13px;color:var(--text-muted);line-height:1.7;margin:0 0 14px">
            Alles Übernommene ist als solches vermerkt. Ein zweiter Lauf ergänzt nur, was fehlt —
            und das Zurücknehmen entfernt ausschließlich, was der Import angelegt hat.
            Was du seither von Hand angelegt hast, bleibt.
          </p>
          <form method="post" action="?action=import_zurueck"
                onsubmit="return confirm('Alles zurücknehmen, was der Import angelegt hat?')">
            <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
            <button type="submit" class="btn btn-danger btn-sm">Import zurücknehmen</button>
          </form>
        </div>
      <?php endif; ?>

      <?php if ($b === null): ?>
        <?php /* ── Schritt 1: Zugangsdaten ── */ ?>
        <div class="card" style="padding:18px 22px;margin-bottom:18px">
          <p style="font-size:13px;color:var(--text-muted);line-height:1.7;margin:0">
            Übernimmt Konten, Foren, Themen, Beiträge, Anhänge, Umfragen und private Nachrichten
            aus einem phpBB der Fassungen 3.2 und 3.3. <strong>Die Passwörter kommen mit</strong> —
            die Mitglieder melden sich hinterher mit ihrem gewohnten an.
            Es wird nur gelesen; das alte Forum bleibt unverändert.
          </p>
        </div>

        <form method="post" action="?action=import_pruefen">
          <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">

          <div class="form-card" style="margin-bottom:16px">
            <h3 style="margin-top:0">Datenbank des alten Forums</h3>

            <div class="form-row">
              <label>Datenbank <span style="color:var(--color-danger)">*</span></label>
              <input type="text" name="db_name" placeholder="phpbb" required autofocus>
              <div class="form-hint">Der Name der Datenbank, in der phpBB liegt.</div>
            </div>

            <div class="form-row">
              <label>Benutzer <span style="color:var(--color-danger)">*</span></label>
              <input type="text" name="db_user" placeholder="phpbb_user" required>
            </div>

            <div class="form-row">
              <label>Passwort</label>
              <input type="password" name="db_pass" autocomplete="new-password">
            </div>

            <div class="form-row">
              <label>Server</label>
              <input type="text" name="db_host" value="localhost">
              <div class="form-hint">
                Liegt das alte Forum auf demselben Server, bleibt es bei <code>localhost</code>.
              </div>
            </div>

            <div class="form-row">
              <label>Tabellen-Präfix</label>
              <input type="text" name="db_praefix" value="phpbb_">
              <div class="form-hint">
                Steht in der <code>config.php</code> des alten Forums unter <code>$table_prefix</code>.
              </div>
            </div>
          </div>

          <div class="form-card" style="margin-bottom:16px">
            <h3 style="margin-top:0">Anhänge</h3>
            <div class="form-row">
              <label>Ordner mit den Dateien</label>
              <input type="text" name="dateien" placeholder="/pfad/zu/phpBB3/files">
              <div class="form-hint">
                Der Ordner, den phpBB unter <em>Anhänge → Einstellungen</em> als Upload-Pfad führt —
                meist <code>files</code> im phpBB-Verzeichnis. Bleibt das Feld leer, kommen die
                Beiträge ohne ihre Dateien.
              </div>
            </div>
          </div>

          <button type="submit" class="btn btn-primary">Nachsehen, was da ist</button>
          <span style="font-size:12px;color:var(--text-faint);margin-left:10px">
            Liest nur — schreibt noch nichts.
          </span>
        </form>

      <?php else: ?>
        <?php /* ── Schritt 2: Was gefunden wurde ── */ ?>
        <?php
          $bs   = $b['bestand'];
          $pw   = $b['passwoerter'];
          $dat  = $b['dateien'];
          $alt32 = $bs['fassung'] !== '' && version_compare($bs['fassung'], '3.2.0', '<');
        ?>

        <div class="card" style="padding:18px 22px;margin-bottom:18px">
          <div style="display:flex;justify-content:space-between;align-items:baseline;flex-wrap:wrap;gap:8px">
            <h3 style="margin:0">Gefunden in <code><?= e($o['db'] ?? '') ?></code></h3>
            <span style="font-size:13px;color:var(--text-muted)">
              phpBB <?= $bs['fassung'] !== '' ? e($bs['fassung']) : 'unbekannter Fassung' ?>
            </span>
          </div>
        </div>

        <?php if ($alt32): ?>
          <div class="card" style="padding:16px 20px;margin-bottom:16px;background:rgba(170,102,68,0.1);border-left:3px solid #aa6644">
            <strong>⚠️ Diese Fassung ist älter als 3.2.</strong>
            Beiträge werden dort anders abgelegt. Der Import versucht es, aber die Formatierung
            kann darunter leiden. Ein Blick in ein paar Beiträge lohnt sich hinterher.
          </div>
        <?php endif; ?>

        <div class="stat-grid" style="margin-bottom:18px">
          <?php foreach ([
            ['👤', $bs['konten'],     'Konten'],
            ['📂', $bs['kategorien'], 'Kategorien'],
            ['📁', $bs['foren'],      'Foren'],
            ['💬', $bs['themen'],     'Themen'],
            ['✉️', $bs['beitraege'],  'Beiträge'],
            ['📎', $bs['anhaenge'],   'Anhänge'],
            ['📊', $bs['umfragen'],   'Umfragen'],
            ['🔒', $bs['pn'],         'Nachrichten'],
          ] as [$icon, $zahl, $label]): ?>
            <div class="stat-box"<?= $zahl === 0 ? ' style="opacity:.45"' : '' ?>>
              <div class="stat-icon"><?= $icon ?></div>
              <div class="stat-num"><?= number_format((int) $zahl, 0, ',', '.') ?></div>
              <div class="stat-label"><?= e($label) ?></div>
            </div>
          <?php endforeach; ?>
        </div>

        <?php if ($bs['bots'] > 0): ?>
          <p style="font-size:12px;color:var(--text-faint);margin:-8px 0 18px">
            Dazu <?= number_format((int) $bs['bots'], 0, ',', '.') ?> Bot-Konten, die phpBB für seine
            Wer-ist-online-Liste führt — die bleiben draußen.
          </p>
        <?php endif; ?>

        <?php /* ── Passwörter: die Zahl, nach der man den Umzug plant ── */ ?>
        <div class="card" style="padding:18px 22px;margin-bottom:16px">
          <h3 style="margin:0 0 12px">🔑 Passwörter</h3>
          <?php
            $gesamt = max(1, $pw['uebernehmbar'] + $pw['neu_noetig']);
            $anteil = round($pw['uebernehmbar'] / $gesamt * 100);
          ?>
          <div style="display:flex;height:10px;border-radius:5px;overflow:hidden;background:var(--bg-surface2);margin-bottom:12px">
            <div style="width:<?= $anteil ?>%;background:var(--color-success)"></div>
            <div style="width:<?= 100 - $anteil ?>%;background:#aa6644"></div>
          </div>
          <div style="display:flex;gap:24px;flex-wrap:wrap;font-size:13px">
            <div>
              <strong style="color:var(--color-success);font-size:18px">
                <?= number_format((int) $pw['uebernehmbar'], 0, ',', '.') ?></strong>
              melden sich normal an
            </div>
            <?php if ($pw['neu_noetig'] > 0): ?>
            <div>
              <strong style="color:#aa6644;font-size:18px">
                <?= number_format((int) $pw['neu_noetig'], 0, ',', '.') ?></strong>
              brauchen ein neues Passwort
            </div>
            <?php endif; ?>
          </div>
          <div class="form-hint" style="margin-top:10px">
            <?php if ($pw['neu_noetig'] > 0): ?>
              Ältere phpBB-Fassungen haben Passwörter anders verschlüsselt; diese lassen sich hier
              nicht prüfen. Die Konten kommen mit — ihre Inhaber benutzen einmal
              „Passwort vergessen“.
            <?php else: ?>
              Alle Passwörter lassen sich übernehmen. Niemand muss sein Passwort neu setzen.
            <?php endif; ?>
          </div>
        </div>

        <?php if ($bs['anhaenge'] > 0): ?>
          <div class="card" style="padding:16px 20px;margin-bottom:16px<?= $dat['fehlen'] > 0 || !$dat['ordner'] ? ';border-left:3px solid #aa6644' : '' ?>">
            <h3 style="margin:0 0 8px">📎 Anhänge</h3>
            <?php if (!$dat['ordner']): ?>
              <p style="margin:0;font-size:13px;color:var(--text-muted)">
                Kein Ordner angegeben — die <?= number_format((int) $bs['anhaenge'], 0, ',', '.') ?>
                Anhänge bleiben draußen. <a href="?action=import_vergessen">Nochmal eingeben</a>.
              </p>
            <?php elseif ($dat['fehlen'] > 0): ?>
              <p style="margin:0;font-size:13px">
                <strong><?= number_format((int) $dat['fehlen'], 0, ',', '.') ?></strong> von
                <?= number_format((int) $bs['anhaenge'], 0, ',', '.') ?> Dateien liegen nicht in
                <code><?= e($o['dateien'] ?? '') ?></code>.
                <a href="?action=import_vergessen">Pfad ändern</a>
              </p>
            <?php else: ?>
              <p style="margin:0;font-size:13px;color:var(--text-muted)">
                Alle <?= number_format((int) $dat['gefunden'], 0, ',', '.') ?> Dateien sind da.
              </p>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <?php /* ── Schritt 3: Der Lauf ── */ ?>
        <div class="card" style="padding:18px 22px" id="import-lauf">
          <h3 style="margin:0 0 12px">Übernehmen</h3>

          <div id="import-fortschritt" hidden>
            <div style="height:10px;border-radius:5px;background:var(--bg-surface2);overflow:hidden;margin-bottom:12px">
              <div id="import-balken" style="height:100%;width:0;background:var(--color-accent);transition:width .3s"></div>
            </div>
            <table style="width:100%;border-collapse:collapse;font-size:13px">
              <?php foreach (IMPORT_SCHRITTE as $sch => $label): ?>
                <tr data-schritt="<?= e($sch) ?>">
                  <td style="padding:5px 0;width:26px" class="import-zeichen">·</td>
                  <td style="padding:5px 0"><?= e($label) ?></td>
                  <td style="padding:5px 0;text-align:right;color:var(--text-muted)" class="import-zahl"></td>
                </tr>
              <?php endforeach; ?>
            </table>
            <p id="import-fehler" style="display:none;margin:12px 0 0;color:var(--color-danger);font-size:13px"></p>
          </div>

          <div id="import-start">
            <?php
              $h = $b['hier'] ?? [];
              // Was hier schon steht, ohne das eigene Konto: nur das koennte weg.
              $vorhanden = (int) ($h['foren'] ?? 0) + (int) ($h['themen'] ?? 0)
                         + (int) ($h['beitraege'] ?? 0) + max(0, (int) ($h['konten'] ?? 0) - 1);
            ?>
            <?php if ($vorhanden > 0): ?>
              <div style="border:1px solid #aa6644;background:rgba(170,102,68,0.08);border-radius:6px;
                          padding:14px 16px;margin-bottom:16px">
                <strong style="display:block;margin-bottom:8px">⚠️ Hier steht schon etwas</strong>
                <div style="font-size:13px;line-height:1.8;color:var(--text-muted)">
                  <?php
                    $teile = [];
                    foreach ([['foren','Forum','Foren'], ['themen','Thema','Themen'],
                              ['beitraege','Beitrag','Beiträge'], ['anhaenge','Anhang','Anhänge'],
                              ['pn','Nachricht','Nachrichten']] as [$k, $ein, $viele]) {
                        $n = (int) ($h[$k] ?? 0);
                        if ($n > 0) $teile[] = number_format($n, 0, ',', '.') . ' ' . ($n === 1 ? $ein : $viele);
                    }
                    $fremde = max(0, (int) ($h['konten'] ?? 0) - 1);
                    if ($fremde > 0) $teile[] = number_format($fremde, 0, ',', '.')
                                              . ' ' . ($fremde === 1 ? 'weiteres Konto' : 'weitere Konten');
                    echo e(implode(' · ', $teile));
                  ?>
                </div>
                <label class="checkbox-line" style="margin-top:12px">
                  <input type="checkbox" id="import-leeren">
                  <span><strong>Das alles vorher löschen.</strong>
                    Dein eigenes Konto bleibt — sonst könntest du dich hinterher nicht anmelden.</span>
                </label>
                <div class="form-hint" style="margin-top:8px">
                  Ohne Häkchen kommt das Übernommene zu dem hinzu, was schon da ist.
                  Das Löschen lässt sich <strong>nicht</strong> zurücknehmen — der Import selbst schon.
                </div>
              </div>
            <?php endif; ?>
            <p style="font-size:13px;color:var(--text-muted);line-height:1.7;margin:0 0 14px">
              Der Import läuft in Runden und meldet, wie weit er ist. Das Fenster darf dabei nicht
              geschlossen werden. Bricht er ab, setzt ein neuer Lauf dort an, wo er stehengeblieben
              ist — und zurücknehmen lässt sich alles.
            </p>
            <button type="button" class="btn btn-primary" id="import-knopf">Import starten</button>
            <a href="?action=import_vergessen" class="btn btn-ghost btn-sm" style="margin-left:8px">Abbrechen</a>
          </div>

          <div id="import-fertig" hidden>
            <p style="margin:14px 0 0;font-size:14px">
              <strong style="color:var(--color-success)">✅ Fertig.</strong>
              <a href="<?= e(FORUM_URL) ?>/index.php">Zum Forum</a> ·
              <a href="?action=boards">Rechte je Forum einrichten</a>
            </p>
          </div>
        </div>

        <script>
        (function () {
          var knopf   = document.getElementById('import-knopf');
          if (!knopf) return;
          var schritte = <?= json_encode(array_keys(IMPORT_SCHRITTE)) ?>;
          // Wie viel jeder Schritt zu tun hat — sonst stuende der Balken
          // waehrend der Beitraege minutenlang still.
          var gesamt = <?= json_encode([
              'user'     => (int) $bs['konten'],
              'struktur' => (int) $bs['kategorien'] + (int) $bs['foren'],
              'thema'    => (int) $bs['themen'],
              'beitrag'  => (int) $bs['beitraege'],
              'anhang'   => (int) $bs['anhaenge'],
              'umfrage'  => (int) $bs['umfragen'],
              'pn'       => (int) $bs['pn'],
              'zaehler'  => 1,
          ]) ?>;
          var alle = Object.values(gesamt).reduce(function (a, b) { return a + b; }, 0) || 1;
          var csrf     = <?= json_encode($csrfToken) ?>;
          var balken   = document.getElementById('import-balken');
          var fehlerP  = document.getElementById('import-fehler');
          var i = 0, summe = {}, neu = {};

          function zeile(s) { return document.querySelector('tr[data-schritt="' + s + '"]'); }

          function malen() {
            // Anteilig an der Gesamtmenge, nicht an der Zahl der Schritte:
            // die Beitraege sind hundertmal mehr Arbeit als die Umfragen.
            var fertig = 0;
            for (var k = 0; k < schritte.length; k++) {
              var s = schritte[k];
              fertig += (k < i) ? (gesamt[s] || 0) : Math.min(summe[s] || 0, gesamt[s] || 0);
            }
            balken.style.width = Math.min(100, Math.round(fertig / alle * 100)) + '%';
          }

          function fehler(text) {
            fehlerP.textContent = text;
            fehlerP.style.display = '';
            var z = zeile(schritte[i]);
            if (z) z.querySelector('.import-zeichen').textContent = '✕';
          }

          function runde() {
            if (i >= schritte.length) {
              balken.style.width = '100%';
              document.getElementById('import-fertig').hidden = false;
              return;
            }
            var s = schritte[i], z = zeile(s);
            z.querySelector('.import-zeichen').textContent = '⟳';

            var daten = new FormData();
            daten.append('csrf', csrf);
            daten.append('schritt', s);

            fetch('?action=import_runde', { method: 'POST', body: daten })
              .then(function (a) { return a.json(); })
              .then(function (e) {
                if (e.fehler) { fehler(e.fehler); return; }
                summe[s] = (summe[s] || 0) + (e.getan || 0);
                neu[s]   = (neu[s]   || 0) + (e.neu   || 0);
                z.querySelector('.import-zahl').textContent =
                  neu[s] > 0 ? neu[s].toLocaleString('de-DE') : '';
                if (e.fertig) {
                  z.querySelector('.import-zeichen').textContent = '✓';
                  i++;
                }
                malen();
                runde();
              })
              .catch(function (ex) { fehler('Abgebrochen: ' + ex.message); });
          }

          knopf.addEventListener('click', function () {
            var leerenAn = document.getElementById('import-leeren');
            if (leerenAn && leerenAn.checked &&
                !confirm('Wirklich alles löschen, was hier steht? Das lässt sich nicht zurücknehmen.')) {
              return;
            }
            document.getElementById('import-start').hidden = true;
            document.getElementById('import-fortschritt').hidden = false;
            malen();

            if (leerenAn && leerenAn.checked) {
              var d = new FormData();
              d.append('csrf', csrf);
              fetch('?action=import_leeren', { method: 'POST', body: d })
                .then(function (a) { return a.json(); })
                .then(function (e) {
                  if (e.fehler) { fehler(e.fehler); return; }
                  runde();
                })
                .catch(function (ex) { fehler('Leeren abgebrochen: ' + ex.message); });
            } else {
              runde();
            }
          });
        })();
        </script>
      <?php endif; ?>

    <?php elseif ($action === 'updates'): ?>
      <h2>🚀 Forum-Updates</h2>

      <?php /* ── Von aussen betreut (v0.25.0) ── */ ?>
      <?php if (!empty($extern)): ?>
      <div class="card" style="margin-bottom:18px;padding:18px 22px;border-left:3px solid var(--color-accent)">
        <h3 style="margin:0 0 8px">🛠️ Updates macht der Betreiber des Dienstes</h3>
        <p style="font-size:13px;color:var(--text-muted);line-height:1.7;margin:0">
          Dieses Forum wird gehostet: Server, Datenbank und Zertifikat stellt der
          Betreiber des Dienstes, und von dort kommen auch neue Forumix-Versionen.
          Du musst dafür nichts tun, und hier lässt sich nichts einspielen.
        </p>
        <p style="font-size:13px;color:var(--text-muted);line-height:1.7;margin:10px 0 0">
          Installierte Version: <strong><?= e(forum_version()) ?></strong>.
          Unten im Verlauf steht, was bisher eingespielt wurde.
        </p>
      </div>
      <?php endif; ?>

      <?php /* ── Update-Paket hochladen (v0.18.54, nur Owner) ── */ ?>
      <?php if (!empty($canUpload)): ?>
      <div class="card" style="margin-bottom:18px;padding:20px 22px">
        <h3 style="margin:0 0 10px">📦 Update-Paket einspielen</h3>
        <p style="font-size:13px;color:var(--text-muted);line-height:1.6;margin:0 0 14px">
          Lade ein Update-ZIP hoch. Es wird automatisch geprüft (nur erlaubte Datei-Typen),
          die Dateien werden kopiert und enthaltenes SQL wird ausgeführt. Das Ergebnis
          erscheint anschließend im Verlauf.
        </p>

        <form method="post" action="?action=upload_update" enctype="multipart/form-data"
              onsubmit="return confirm('Update-Paket jetzt einspielen?\n\nDateien werden überschrieben und SQL ausgeführt. Vorher ein Backup anlegen!')">
          <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
          <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
            <input type="file" name="update_zip" accept=".zip,application/zip" required
                   style="flex:1;min-width:240px">
            <button type="submit" class="btn btn-primary">📦 Einspielen</button>
          </div>
        </form>

        <div style="margin-top:14px;padding:12px 14px;background:var(--bg-surface2);border-radius:6px;font-size:12px;color:var(--text-muted);line-height:1.7">
          <strong>Aufbau des Pakets:</strong>
          <code>update.json</code> (Pflicht: Version + Name) ·
          <code>update.sql</code> (optional, wird ausgeführt) ·
          <code>files/…</code> (wird ins Forum kopiert)<br>
          <strong>Erlaubte Typen:</strong> php, css, js, sql, html, tpl, json, md, txt, Bilder, Fonts.
          Alles andere → Abbruch. Pfad-Traversal (<code>..</code>) wird blockiert.
        </div>
      </div>
      <?php elseif (empty($extern)): ?>
      <div class="card" style="margin-bottom:18px;padding:16px 20px;background:var(--bg-surface2);border-left:3px solid var(--color-warning)">
        <p style="font-size:13px;color:var(--text-muted);margin:0">
          ℹ️ Update-Pakete können nur vom <strong>Owner</strong> eingespielt werden.
        </p>
      </div>
      <?php endif; ?>

      <?php /* ── Detail-Ergebnis der letzten Installation (v0.18.54) ── */ ?>
      <?php if (!empty($updateResult)): ?>
        <?php $ur = $updateResult; ?>
        <div class="card" style="margin-bottom:18px;padding:18px 22px;border-left:3px solid <?= $ur['success'] ? '#16a34a' : 'var(--color-danger)' ?>">
          <h3 style="margin:0 0 4px">
            <?= $ur['success'] ? '✅' : '⚠️' ?>
            Ergebnis: <?= e($ur['manifest']['name'] ?? 'Update') ?>
            <span style="font-weight:400;color:var(--text-muted);font-size:13px">v<?= e($ur['manifest']['version'] ?? '?') ?></span>
          </h3>
          <?php if (!$ur['success']): ?>
            <p style="color:var(--color-danger);font-size:13px;margin:6px 0"><?= e($ur['error'] ?? 'Fehler') ?></p>
          <?php endif; ?>
          <div style="margin-top:10px;background:var(--bg-surface2);border-radius:6px;padding:12px 14px;font-family:var(--font-mono,monospace);font-size:12px;line-height:1.7;max-height:340px;overflow:auto">
            <?php foreach ($ur['log'] as $line): ?>
              <div><?= e($line) ?></div>
            <?php endforeach; ?>
          </div>
          <p style="font-size:11px;color:var(--text-faint);margin:8px 0 0">
            🆕 = neue Datei · ✏️ = geänderte Datei · = unverändert (übersprungen)
          </p>
        </div>
      <?php endif; ?>

      <?php /* ── Datei-basierte Migrationen (Legacy, falls vorhanden) ── */ ?>
      <?php
        $pendingMigs = array_filter($migrations, fn($m) => !$m['executed']);
      ?>
      <?php if (!empty($pendingMigs)): ?>
      <div class="section-heading">Verfügbare Datei-Migrationen</div>
      <div class="card">
        <table class="forum-table">
          <thead>
            <tr>
              <th style="width:80px">Version</th>
              <th>Beschreibung</th>
              <th style="width:160px">Status</th>
              <th style="width:160px;text-align:right">Aktion</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($pendingMigs as $m): ?>
              <tr>
                <td><code style="font-weight:700"><?= e($m['version']) ?></code></td>
                <td><?= e($m['name']) ?></td>
                <td><span class="role-tag" style="background:#f59e0b">⏳ Ausstehend</span></td>
                <td style="text-align:right">
                  <?php if (!empty($canMigrate)): ?>
                  <form method="post" action="?action=run_migration" style="display:inline">
                    <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="version" value="<?= e($m['version']) ?>">
                    <button type="submit" class="btn btn-primary btn-sm"
                            onclick="return confirm('Migration <?= e($m['version']) ?> jetzt ausführen?\n\nVorher unbedingt ein Datenbank-Backup anlegen!')">
                      ▶️ Jetzt ausführen
                    </button>
                  </form>
                  <?php elseif (!empty($extern)): ?>
                  <span style="font-size:12px;color:var(--text-muted)">macht der Betreiber</span>
                  <?php else: ?>
                  <span style="font-size:12px;color:var(--text-muted)">nur der Owner</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>

      <?php /* ── Verlauf (begrenzt + ausklappbar) ── */ ?>
      <?php if (!empty($executed)): ?>
      <div class="section-heading" style="margin-top:24px">
        Verlauf <span style="font-weight:400;color:var(--text-muted);font-size:13px">(<?= count($executed) ?> gesamt)</span>
      </div>
      <div class="card">
        <table class="forum-table">
          <thead>
            <tr>
              <th style="width:110px">Version</th>
              <th>Beschreibung</th>
              <th style="width:140px">Ausgeführt am</th>
              <th style="width:120px">Durch</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($historyVisible as $e): ?>
              <tr>
                <td><code><?= e($e['version']) ?></code></td>
                <td style="font-size:13px"><?= e($e['name']) ?></td>
                <td style="font-size:12px;color:var(--text-muted)"><?= e(format_date($e['executed_at'])) ?></td>
                <td style="font-size:12px"><?= e($e['executor_name'] ?? '–') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <?php if (!empty($historyHidden)): ?>
          <details style="margin-top:4px">
            <summary style="cursor:pointer;padding:12px 14px;color:var(--color-accent);font-size:13px;font-weight:500">
              ▾ <?= count($historyHidden) ?> ältere Einträge anzeigen
            </summary>
            <table class="forum-table">
              <tbody>
                <?php foreach ($historyHidden as $e): ?>
                  <tr>
                    <td style="width:110px"><code><?= e($e['version']) ?></code></td>
                    <td style="font-size:13px"><?= e($e['name']) ?></td>
                    <td style="width:140px;font-size:12px;color:var(--text-muted)"><?= e(format_date($e['executed_at'])) ?></td>
                    <td style="width:120px;font-size:12px"><?= e($e['executor_name'] ?? '–') ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </details>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <?php /* ── Hinweise ──
             Nur dort, wo man selbst einspielt. Auf einer betreuten Installation
             wäre es Rat für eine Handlung, die es nicht gibt — und stünde zwei
             Zeilen unter dem Satz, dass sich der Betreiber kümmert. ── */ ?>
      <?php if (empty($extern)): ?>
      <div class="card" style="margin-top:18px;padding:16px 20px;background:var(--bg-surface2);border-left:3px solid var(--color-warning)">
        <strong style="font-size:13px">⚠️ Wichtige Hinweise</strong>
        <ul style="margin:8px 0 0 18px;font-size:12px;color:var(--text-muted);line-height:1.8">
          <li>Vor jedem Update: <strong>Datenbank-Backup</strong> anlegen</li>
          <li>SQL im Paket wird statement-weise ausgeführt; bereits vorhandene Spalten werden übersprungen (idempotenz-freundlich)</li>
          <li>Nur der <strong>Owner</strong> kann Pakete einspielen (kann beliebigen Code installieren)</li>
          <li>Bei Fehlern: Backup zurückspielen, Paket korrigieren, erneut einspielen</li>
        </ul>
      </div>
      <?php endif; ?>

    <?php /* ════════════ WARTUNG ════════════ */ ?>
    <?php elseif ($action === 'smileys'): ?>
      <div class="admin-page-head">
        <h1>😀 Smileys verwalten</h1>
        <p>Bearbeite die Smileys die im Forum verwendet werden können. „Im Picker" bestimmt, ob das Smiley in der Schnellauswahl-Leiste über dem Eingabefeld erscheint.</p>
      </div>

      <div class="form-card">
        <h2>➕ Neues Smiley anlegen</h2>
        <form method="post" action="?action=add_smiley" class="smiley-add-form">
          <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
          <label>Code
            <input type="text" name="code" placeholder=":example:" required maxlength="30">
          </label>
          <label>Emoji
            <input type="text" name="emoji" placeholder="🙂" required maxlength="10" class="smiley-emoji-input">
          </label>
          <label>Beschreibung
            <input type="text" name="description" placeholder="z.B. Lächeln" maxlength="100">
          </label>
          <label class="smiley-picker-toggle">
            <input type="checkbox" name="is_in_picker" value="1">
            <span>Im Picker</span>
          </label>
          <button type="submit" class="btn btn-primary">Anlegen</button>
        </form>
      </div>

      <?php
        $smileysInPicker = array_filter($smileys, fn($s) => (int)$s['is_in_picker'] === 1);
        $smileysOther    = array_filter($smileys, fn($s) => (int)$s['is_in_picker'] !== 1);
      ?>

      <div class="form-card">
        <h2>⭐ Im Picker (<?= count($smileysInPicker) ?>)</h2>
        <p class="form-hint">
          Diese Smileys werden in der Schnellauswahl-Leiste über dem Eingabefeld angezeigt. Drag &amp; Drop zum Sortieren – die Reihenfolge wird automatisch gespeichert.
        </p>

        <div class="smiley-list" data-sortable="true">
          <?php foreach ($smileysInPicker as $s): ?>
            <form method="post" action="?action=update_smiley" class="smiley-row" data-id="<?= (int) $s['smiley_id'] ?>" draggable="true">
              <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
              <input type="hidden" name="smiley_id" value="<?= (int) $s['smiley_id'] ?>">
              <div class="drag-handle" title="Zum Sortieren ziehen">≡</div>
              <input type="text" name="emoji" value="<?= e($s['emoji']) ?>" maxlength="10" class="smiley-emoji-input" required>
              <input type="text" name="code" value="<?= e($s['code']) ?>" maxlength="30" required class="smiley-code-input">
              <input type="text" name="description" value="<?= e($s['description']) ?>" maxlength="100" class="smiley-desc-input">
              <label class="smiley-picker-toggle">
                <input type="checkbox" name="is_in_picker" value="1" checked>
                <span>Picker</span>
              </label>
              <div class="smiley-actions">
                <button type="submit" class="btn btn-sm" title="Speichern">💾</button>
                <a href="?action=delete_smiley&id=<?= (int) $s['smiley_id'] ?>&csrf=<?= e($csrfToken) ?>" class="btn btn-sm btn-danger" onclick="return confirm('Smiley „<?= e($s['code']) ?>“ wirklich löschen?')" title="Löschen">🗑️</a>
              </div>
            </form>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="form-card">
        <h2>📋 Weitere Smileys (<?= count($smileysOther) ?>)</h2>
        <p class="form-hint">
          Diese Smileys funktionieren als Codes (z.B. <code>:beer:</code>), erscheinen aber nicht in der Schnellauswahl. Häkchen bei „Picker" setzen und speichern, um ins Picker aufzunehmen.
          Smileys mit dem Hinweis „<em>Alias von</em>" haben das gleiche Emoji wie ein Picker-Smiley – sie sind alternative Schreibweisen.
        </p>

        <?php
          // Aliase aufspüren: gleicher Emoji wie ein Picker-Smiley
          $pickerEmojis = [];
          foreach ($smileysInPicker as $ps) {
              $pickerEmojis[$ps['emoji']] = $ps['code'];
          }
        ?>

        <div class="smiley-list">
          <?php foreach ($smileysOther as $s): ?>
            <?php $aliasOf = $pickerEmojis[$s['emoji']] ?? null; ?>
            <form method="post" action="?action=update_smiley" class="smiley-row<?= $aliasOf ? ' smiley-alias' : '' ?>">
              <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
              <input type="hidden" name="smiley_id" value="<?= (int) $s['smiley_id'] ?>">
              <input type="text" name="emoji" value="<?= e($s['emoji']) ?>" maxlength="10" class="smiley-emoji-input" required>
              <div class="smiley-code-wrap">
                <input type="text" name="code" value="<?= e($s['code']) ?>" maxlength="30" required class="smiley-code-input">
                <?php if ($aliasOf): ?>
                  <small class="smiley-alias-hint">Alias von <code><?= e($aliasOf) ?></code></small>
                <?php endif; ?>
              </div>
              <input type="text" name="description" value="<?= e($s['description']) ?>" maxlength="100" class="smiley-desc-input">
              <label class="smiley-picker-toggle">
                <input type="checkbox" name="is_in_picker" value="1">
                <span>Picker</span>
              </label>
              <div class="smiley-actions">
                <button type="submit" class="btn btn-sm" title="Speichern">💾</button>
                <a href="?action=delete_smiley&id=<?= (int) $s['smiley_id'] ?>&csrf=<?= e($csrfToken) ?>" class="btn btn-sm btn-danger" onclick="return confirm('Smiley „<?= e($s['code']) ?>“ wirklich löschen?')" title="Löschen">🗑️</a>
              </div>
            </form>
          <?php endforeach; ?>
        </div>
      </div>

      <style>
        .smiley-add-form {
          display: grid;
          grid-template-columns: 1fr 80px 2fr auto auto;
          gap: 12px;
          align-items: end;
        }
        .smiley-add-form label {
          display: flex;
          flex-direction: column;
          gap: 4px;
          font-size: 13px;
          color: var(--text-muted);
        }
        .smiley-list {
          display: flex;
          flex-direction: column;
          gap: 6px;
        }
        .smiley-row {
          display: grid;
          grid-template-columns: auto 60px 1fr 2fr auto auto;
          gap: 10px;
          align-items: center;
          padding: 8px;
          background: var(--bg-surface2);
          border: 1px solid var(--border);
          border-radius: 8px;
        }
        .smiley-row.smiley-alias {
          opacity: 0.7;
          border-style: dashed;
        }
        .smiley-code-wrap {
          display: flex;
          flex-direction: column;
          gap: 2px;
        }
        .smiley-alias-hint {
          font-size: 11px;
          color: var(--text-faint);
          font-style: italic;
        }
        .smiley-alias-hint code {
          background: var(--bg-surface3);
          padding: 0 4px;
          border-radius: 3px;
          font-style: normal;
        }
        .smiley-list[data-sortable="true"] .smiley-row {
          grid-template-columns: 30px 60px 1fr 2fr auto auto;
        }
        .smiley-list:not([data-sortable="true"]) .smiley-row {
          grid-template-columns: 60px 1fr 2fr auto auto;
        }
        .smiley-row.dragging { opacity: 0.4; }
        .drag-handle {
          cursor: grab;
          user-select: none;
          color: var(--text-faint);
          font-size: 20px;
          text-align: center;
        }
        .drag-handle:active { cursor: grabbing; }
        .smiley-emoji-input {
          text-align: center;
          font-size: 18px;
          width: 100%;
        }
        .smiley-code-input,
        .smiley-desc-input {
          width: 100%;
          font-family: monospace;
        }
        .smiley-desc-input { font-family: inherit; }
        .smiley-picker-toggle {
          display: flex;
          align-items: center;
          gap: 6px;
          white-space: nowrap;
          font-size: 13px;
          padding-bottom: 4px;
        }
        .smiley-actions { display: flex; gap: 4px; }
        .form-hint {
          font-size: 13px;
          color: var(--text-muted);
          margin-bottom: 12px;
        }
        .form-hint code {
          background: var(--bg-surface2);
          padding: 1px 6px;
          border-radius: 4px;
        }
      </style>

      <script>
      // Drag-&-Drop-Sortierung für Picker-Smileys
      (function() {
        const container = document.querySelector('.smiley-list[data-sortable="true"]');
        if (!container) return;

        container.addEventListener('dragstart', e => {
          const row = e.target.closest('.smiley-row');
          if (!row) return;
          row.classList.add('dragging');
          e.dataTransfer.effectAllowed = 'move';
        });

        container.addEventListener('dragend', e => {
          const row = e.target.closest('.smiley-row');
          if (row) row.classList.remove('dragging');
          saveOrder();
        });

        container.addEventListener('dragover', e => {
          e.preventDefault();
          const dragging = container.querySelector('.dragging');
          if (!dragging) return;
          const rows = [...container.querySelectorAll('.smiley-row')].filter(r => r !== dragging);
          const after = rows.find(child => {
            const r = child.getBoundingClientRect();
            return e.clientY < r.top + r.height / 2;
          });
          if (after) container.insertBefore(dragging, after);
          else container.appendChild(dragging);
        });

        let saveTimer = null;
        function saveOrder() {
          clearTimeout(saveTimer);
          saveTimer = setTimeout(() => {
            const ids = [...container.querySelectorAll('.smiley-row')].map(row => row.dataset.id).filter(Boolean);
            const fd = new FormData();
            fd.append('csrf', '<?= e($csrfToken) ?>');
            ids.forEach(id => fd.append('order[]', id));
            fetch('?action=reorder_smileys', { method: 'POST', body: fd });
          }, 200);
        }
      })();
      </script>

    <?php elseif ($action === 'legal'): ?>
      <div class="admin-page-head">
        <h1>📄 Rechtliches</h1>
        <p>
          Hier verwaltest du <strong>Impressum</strong>, <strong>Datenschutzerklärung</strong> und <strong>Nutzungsbedingungen</strong>.
          Daten wie Name oder Adresse werden zentral hier gepflegt und automatisch in alle Seiten eingesetzt.
        </p>
      </div>

      <!-- ─── Daten-Felder (Settings für Platzhalter) ─── -->
      <div class="form-card">
        <h2>👤 Kontaktdaten</h2>
        <p class="form-hint">
          Diese Daten werden in den Seiten als Platzhalter eingesetzt: <code>{{name}}</code>, <code>{{street}}</code>, <code>{{city}}</code>, <code>{{email}}</code>.
          Wenn du sie hier änderst, wirken sich die Änderungen sofort auf alle Seiten aus.
        </p>
        <form method="post" action="?action=save_legal">
          <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">

          <div class="form-row">
            <label for="imprint_name">Name / Verantwortliche Person</label>
            <input type="text" id="imprint_name" name="imprint_name"
                   value="<?= e($imprint['name']) ?>"
                   placeholder="z.B. Max Mustermann oder Mustermann GmbH"
                   maxlength="200">
          </div>

          <div class="form-row">
            <label for="imprint_street">Straße und Hausnummer</label>
            <input type="text" id="imprint_street" name="imprint_street"
                   value="<?= e($imprint['street']) ?>"
                   placeholder="z.B. Musterstraße 1"
                   maxlength="200">
          </div>

          <div class="form-row">
            <label for="imprint_city">PLZ und Ort</label>
            <input type="text" id="imprint_city" name="imprint_city"
                   value="<?= e($imprint['city']) ?>"
                   placeholder="z.B. 12345 Musterstadt"
                   maxlength="200">
          </div>

          <div class="form-row">
            <label for="imprint_email">E-Mail-Adresse</label>
            <input type="email" id="imprint_email" name="imprint_email"
                   value="<?= e($imprint['email']) ?>"
                   placeholder="z.B. kontakt@example.com"
                   maxlength="200">
          </div>

          <button type="submit" class="btn btn-primary">💾 Daten speichern</button>
        </form>
      </div>

      <!-- ─── Page-Editor ─── -->
      <?php if (!empty($editingPage) && !empty($pageData)): ?>
        <div class="form-card">
          <h2>✏️ Seite bearbeiten: <?= e($pageData['title']) ?></h2>
          <p class="form-hint">
            Bearbeite den Inhalt im BBCode-Editor. Verfügbare Platzhalter:
            <?php $first = true; foreach ($placeholders as $ph => $desc): ?>
              <?php if (!$first) echo ', '; ?>
              <code title="<?= e($desc) ?>"><?= e($ph) ?></code>
              <?php $first = false; endforeach; ?>
          </p>

          <form method="post" action="?action=save_page">
            <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="slug" value="<?= e($pageData['slug']) ?>">

            <?php
              $editorValue          = $pageData['content'];
              $editorPlaceholder    = 'Seiten-Inhalt im BBCode-Format…';
              $editorAllowHeadings  = true;
              $editorRequired       = true;
              include __DIR__ . '/../partials/editor.php';
            ?>

            <div style="display:flex;gap:8px;margin-top:14px">
              <button type="submit" class="btn btn-primary">💾 Seite speichern</button>
              <a href="<?= e(FORUM_URL) ?>/<?= e($pageData['slug']) ?>.php" target="_blank" class="btn btn-ghost">👁️ Live-Vorschau (öffnet in neuem Tab)</a>
              <a href="?action=legal" class="btn btn-ghost">↶ Zurück zur Übersicht</a>
            </div>
          </form>
        </div>

      <?php else: ?>
        <!-- Page-Liste -->
        <div class="form-card">
          <h2>📑 Verfügbare Seiten</h2>
          <p class="form-hint">
            Klick auf eine Seite, um sie zu bearbeiten.
          </p>

          <div style="display:flex;flex-direction:column;gap:8px">
            <?php foreach ($pages as $p): ?>
              <a href="?action=legal&page=<?= e($p['slug']) ?>" class="page-list-item">
                <span class="page-list-icon">
                  <?php
                    $icons = ['imprint' => '📄', 'privacy' => '🔒', 'terms' => '📜'];
                    echo $icons[$p['slug']] ?? '📃';
                  ?>
                </span>
                <span class="page-list-title"><?= e($p['title']) ?></span>
                <span class="page-list-meta">
                  <code><?= e($p['slug']) ?></code>
                  <?php if (!empty($p['updated_at'])): ?>
                    · zuletzt geändert: <?= e(date('d.m.Y H:i', strtotime($p['updated_at']))) ?>
                  <?php endif; ?>
                </span>
                <span class="page-list-arrow">→</span>
              </a>
            <?php endforeach; ?>
          </div>

          <?php if (empty($pages)): ?>
            <p style="color:var(--text-muted);font-style:italic;margin-top:14px">
              ⚠️ Keine Pages in der Datenbank gefunden.
              Wenn du gerade von v0.18.5 oder älter geupdatet hast, führe bitte die <a href="?action=updates">Migration v0.18.6</a> aus.
            </p>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <div class="form-card" style="background:var(--bg-surface2)">
        <h3 style="margin-bottom:8px">ℹ️ Hinweise</h3>
        <ul style="margin-left:18px;line-height:1.7;color:var(--text-muted);font-size:14px">
          <li>Die Texte sind in BBCode geschrieben. Verfügbare Tags: <code>[b]</code>, <code>[i]</code>, <code>[u]</code>, <code>[h2]</code>, <code>[h3]</code>, <code>[list]</code>/<code>[*]</code>, <code>[hr]</code>, <code>[url=...]</code> usw.</li>
          <li>Platzhalter wie <code>{{name}}</code> werden beim Anzeigen automatisch ersetzt.</li>
          <li>Bei rechtlichen Unsicherheiten bitte einen Anwalt konsultieren – diese Templates sind <strong>kein Rechtsrat</strong>.</li>
        </ul>
      </div>

      <style>
        .page-list-item {
          display: grid;
          grid-template-columns: 36px 1fr auto 24px;
          gap: 14px;
          align-items: center;
          padding: 14px 18px;
          background: var(--bg-surface2);
          border: 1px solid var(--border);
          border-radius: 8px;
          color: var(--text);
          text-decoration: none;
          transition: background 0.1s, border-color 0.1s;
        }
        .page-list-item:hover {
          background: var(--bg-surface3);
          border-color: var(--border-strong);
        }
        .page-list-icon { font-size: 22px; }
        .page-list-title { font-weight: 600; }
        .page-list-meta { color: var(--text-muted); font-size: 13px; }
        .page-list-meta code {
          background: var(--bg-surface3);
          padding: 1px 6px;
          border-radius: 3px;
        }
        .page-list-arrow { color: var(--text-faint); font-size: 18px; }
      </style>

    <?php elseif ($action === 'templates'): ?>
      <div class="admin-page-head">
        <h1>🖼️ Template-Editor</h1>
        <p>
          Hier kannst du Templates der Forum-Oberfläche anpassen. Änderungen werden
          <strong>als Override in der Datenbank</strong> gespeichert – die Original-Dateien
          bleiben unverändert. So überstehen deine Anpassungen Updates.
        </p>
      </div>

      <?php if (!empty($editingTpl)): ?>
        <!-- ─── Template bearbeiten ─── -->
        <div class="form-card">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
            <h2 style="margin:0">✏️ <?= e($editingTpl) ?>.tpl.html</h2>
            <div style="display:flex;gap:8px">
              <a href="?action=templates" class="btn btn-ghost btn-sm">↶ Zurück</a>
              <?php if (!empty($tplOverride)): ?>
                <form method="post" action="?action=reset_template" style="display:inline"
                      onsubmit="return confirm('Wirklich auf Original zurücksetzen? Deine Anpassungen gehen verloren.');">
                  <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
                  <input type="hidden" name="name" value="<?= e($editingTpl) ?>">
                  <button type="submit" class="btn btn-ghost btn-sm">↩️ Auf Original zurücksetzen</button>
                </form>
              <?php endif; ?>
            </div>
          </div>

          <?php if (!empty($tplIsStale)): ?>
            <!-- ─── STALE-WARNUNG ─── -->
            <div class="flash flash-warning" style="margin-bottom:14px">
              <strong>⚠️ Das Original wurde aktualisiert seit du dein Override gespeichert hast!</strong>
              <p style="margin:8px 0 0;font-size:13px">
                Möglicherweise enthält das neue Original Bugfixes oder Features die in deinem
                Override nicht enthalten sind. Schau dir den Diff unten an und entscheide:
              </p>
              <ul style="margin:8px 0 0 18px;font-size:13px;line-height:1.6">
                <li><strong>Mein Override behalten</strong>: blendet den Hinweis aus, der Override bleibt aktiv</li>
                <li><strong>Override neu starten</strong>: löscht den Override, zeigt das neue Original – dann kannst du deine Anpassungen neu einarbeiten</li>
                <li><strong>Manuell mergen</strong>: bearbeite den Override unten und übernimm die Original-Änderungen die du brauchst</li>
              </ul>
              <div style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap">
                <form method="post" action="?action=ack_template_update" style="display:inline">
                  <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
                  <input type="hidden" name="name" value="<?= e($editingTpl) ?>">
                  <button type="submit" class="btn btn-ghost btn-sm">✓ Mein Override behalten</button>
                </form>
                <form method="post" action="?action=reset_template" style="display:inline"
                      onsubmit="return confirm('Override löschen und mit neuem Original starten?');">
                  <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
                  <input type="hidden" name="name" value="<?= e($editingTpl) ?>">
                  <button type="submit" class="btn btn-ghost btn-sm">🔄 Override neu starten</button>
                </form>
                <button type="button" id="btn-show-diff" class="btn btn-ghost btn-sm">🔍 Original-Änderungen anzeigen</button>
              </div>
            </div>

            <div id="tpl-diff-view" hidden style="margin-bottom:14px">
              <h3 style="margin-bottom:8px;font-size:14px">Diff: Original beim Save → Aktuelles Original</h3>
              <div class="tpl-diff">
                <?php foreach ($tplDiff as $line): ?>
                  <div class="tpl-diff-line tpl-diff-<?= e($line['type']) ?>">
                    <span class="tpl-diff-marker">
                      <?= $line['type'] === 'add' ? '+' : ($line['type'] === 'del' ? '−' : ' ') ?>
                    </span><span class="tpl-diff-text"><?= e($line['text']) ?></span>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>

          <?php elseif (empty($tplOverride)): ?>
            <p class="form-hint">
              ℹ️ Aktuell unverändert (zeigt das Original). Beim Speichern wird ein Override angelegt.
            </p>
          <?php else: ?>
            <p class="form-hint">
              ✏️ Dieses Template ist <strong>angepasst</strong>. Beim Speichern wird der Override aktualisiert.
            </p>
          <?php endif; ?>

          <form method="post" action="?action=save_template" id="tpl-form">
            <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="name" value="<?= e($editingTpl) ?>">

            <div class="form-row">
              <textarea name="content" id="tpl-editor" rows="24" style="width:100%;font-family:var(--font-mono);font-size:13px"><?= e($tplEffective) ?></textarea>
            </div>

            <div style="display:flex;gap:8px;flex-wrap:wrap">
              <button type="submit" class="btn btn-primary">💾 Speichern</button>
              <button type="button" id="btn-show-original" class="btn btn-ghost">👁️ Original anzeigen</button>
            </div>
          </form>

          <div id="tpl-original-view" hidden style="margin-top:14px">
            <h3 style="margin-bottom:8px">📄 Original (read-only):</h3>
            <pre style="background:var(--bg-surface2);padding:14px;border-radius:6px;overflow:auto;font-family:var(--font-mono);font-size:12px;max-height:400px"><?= e($tplOriginal) ?></pre>
          </div>

          <div class="form-card" style="margin-top:20px;background:var(--bg-surface2)">
            <h3 style="margin-bottom:8px">📚 Template-Syntax (Twig-ähnlich)</h3>
            <ul style="margin-left:18px;line-height:1.8;color:var(--text-muted);font-size:13px">
              <li><code>{{ variable }}</code> – Variable ausgeben (HTML-escaped)</li>
              <li><code>{{ variable|raw }}</code> – ohne Escape (vorsicht!)</li>
              <li><code>{% if expr %}…{% else %}…{% endif %}</code> – Bedingung</li>
              <li><code>{% for item in list %}…{% endfor %}</code> – Schleife</li>
              <li><code>{% include 'partial' %}</code> – Anderes Template einbinden</li>
              <li>Filter: <code>|upper</code>, <code>|lower</code>, <code>|trim</code>, <code>|length</code>, <code>|date('d.m.Y')</code>, <code>|truncate(50)</code>, <code>|nl2br</code>, <code>|default('-')</code></li>
              <li>Funktionen: <code>url('/x')</code>, <code>asset('style.css')</code>, <code>t('key')</code>, <code>csrf_token()</code></li>
            </ul>
            <p style="margin-top:10px;color:var(--text-faint);font-size:12px">
              ⚠️ <strong>PHP-Tags sind verboten</strong> (<code>&lt;?php</code>, <code>&lt;?=</code>) – das Forum lehnt das Speichern dann ab.
            </p>
          </div>
        </div>

        <!-- ─── CodeMirror einbinden ─── -->
        <link rel="stylesheet" href="<?= e(FORUM_URL) ?>/assets/codemirror/codemirror.min.css">
        <link rel="stylesheet" href="<?= e(FORUM_URL) ?>/assets/codemirror/theme/dracula.min.css">
        <script src="<?= e(FORUM_URL) ?>/assets/codemirror/codemirror.min.js"></script>
        <script src="<?= e(FORUM_URL) ?>/assets/codemirror/mode/htmlmixed/htmlmixed.min.js"></script>
        <script src="<?= e(FORUM_URL) ?>/assets/codemirror/mode/xml/xml.min.js"></script>
        <script src="<?= e(FORUM_URL) ?>/assets/codemirror/mode/javascript/javascript.min.js"></script>
        <script src="<?= e(FORUM_URL) ?>/assets/codemirror/mode/css/css.min.js"></script>
        <script src="<?= e(FORUM_URL) ?>/assets/codemirror/addon/edit/matchbrackets.min.js"></script>
        <script src="<?= e(FORUM_URL) ?>/assets/codemirror/addon/edit/closebrackets.min.js"></script>

        <script>
        (function () {
          const ta = document.getElementById('tpl-editor');
          if (!ta || typeof CodeMirror === 'undefined') return;

          const editor = CodeMirror.fromTextArea(ta, {
            mode: 'htmlmixed',
            theme: 'dracula',
            lineNumbers: true,
            lineWrapping: true,
            indentUnit: 2,
            tabSize: 2,
            matchBrackets: true,
            autoCloseBrackets: true,
            extraKeys: {
              'Ctrl-S': function (cm) {
                document.getElementById('tpl-form').requestSubmit();
              },
            },
          });
          editor.setSize(null, '500px');

          // Original-Anzeige Toggle
          const btn = document.getElementById('btn-show-original');
          const view = document.getElementById('tpl-original-view');
          if (btn && view) {
            btn.addEventListener('click', () => {
              const showing = !view.hasAttribute('hidden');
              if (showing) {
                view.setAttribute('hidden', '');
                btn.textContent = '👁️ Original anzeigen';
              } else {
                view.removeAttribute('hidden');
                btn.textContent = '👁️ Original verbergen';
              }
            });
          }

          // Diff-Toggle
          const btnDiff = document.getElementById('btn-show-diff');
          const viewDiff = document.getElementById('tpl-diff-view');
          if (btnDiff && viewDiff) {
            btnDiff.addEventListener('click', () => {
              const showing = !viewDiff.hasAttribute('hidden');
              if (showing) {
                viewDiff.setAttribute('hidden', '');
                btnDiff.textContent = '🔍 Original-Änderungen anzeigen';
              } else {
                viewDiff.removeAttribute('hidden');
                btnDiff.textContent = '🔍 Original-Änderungen verbergen';
              }
            });
          }
        })();
        </script>

      <?php else: ?>
        <!-- ─── Template-Liste ─── -->
        <?php if (empty($templates)): ?>
          <div class="form-card">
            <p style="color:var(--text-muted)">
              Aktuell gibt es noch keine Templates im <code>views_new/</code>-Verzeichnis,
              die hier editierbar wären. Im Laufe der Entwicklung werden mehr PHP-Views
              auf das Template-Format umgestellt.
            </p>
          </div>
        <?php else: ?>
          <div class="form-card">
            <h2>📑 Verfügbare Templates (<?= count($templates) ?>)</h2>
            <p class="form-hint">
              <span class="tpl-badge tpl-badge-original">📄 Original</span> = unverändert ·
              <span class="tpl-badge tpl-badge-override">✏️ Angepasst</span> = vom Admin überschrieben ·
              <span class="tpl-badge tpl-badge-stale">⚠️ Veraltet</span> = Original wurde seither aktualisiert
            </p>

            <div style="display:flex;flex-direction:column;gap:8px;margin-top:14px">
              <?php foreach ($templates as $t): ?>
                <a href="?action=templates&edit=<?= e($t['name']) ?>" class="tpl-list-item">
                  <span class="tpl-list-name"><?= e($t['name']) ?><code>.tpl.html</code></span>
                  <span class="tpl-list-meta">
                    <?php if (!empty($t['is_stale'])): ?>
                      <span class="tpl-badge tpl-badge-stale">⚠️ Veraltet</span>
                    <?php endif; ?>
                    <?php if ($t['has_override']): ?>
                      <span class="tpl-badge tpl-badge-override">✏️ Angepasst</span>
                      <?php if (!empty($t['updated_at'])): ?>
                        · seit <?= e(date('d.m.Y H:i', strtotime($t['updated_at']))) ?>
                      <?php endif; ?>
                    <?php else: ?>
                      <span class="tpl-badge tpl-badge-original">📄 Original</span>
                    <?php endif; ?>
                  </span>
                  <span class="tpl-list-arrow">→</span>
                </a>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

        <div class="form-card" style="background:var(--bg-surface2)">
          <h3 style="margin-bottom:8px">ℹ️ Wie funktioniert das?</h3>
          <ul style="margin-left:18px;line-height:1.7;color:var(--text-muted);font-size:14px">
            <li>Originale liegen im Code (<code>views_new/*.tpl.html</code>) und kommen mit jedem Update mit</li>
            <li>Deine Anpassungen werden als <strong>Override in der Datenbank</strong> gespeichert (Tabelle <code>template_overrides</code>)</li>
            <li>Bei Updates bleiben deine Overrides erhalten – Originale werden aber aktualisiert</li>
            <li>Mit „↩️ Auf Original zurücksetzen" wird der Override gelöscht, das Original wieder aktiv</li>
            <li>PHP-Tags sind aus Sicherheitsgründen <strong>nicht erlaubt</strong> in Template-Inhalten</li>
          </ul>
        </div>
      <?php endif; ?>

      <style>
        .tpl-list-item {
          display: grid;
          grid-template-columns: 1fr auto 24px;
          gap: 14px;
          align-items: center;
          padding: 12px 16px;
          background: var(--bg-surface2);
          border: 1px solid var(--border);
          border-radius: 8px;
          color: var(--text);
          text-decoration: none;
          transition: background 0.1s, border-color 0.1s;
        }
        .tpl-list-item:hover {
          background: var(--bg-surface3);
          border-color: var(--border-strong);
        }
        .tpl-list-name {
          font-family: var(--font-mono);
          font-size: 14px;
          font-weight: 600;
        }
        .tpl-list-name code {
          color: var(--text-muted);
          font-weight: 400;
        }
        .tpl-list-meta { color: var(--text-muted); font-size: 13px; }
        .tpl-list-arrow { color: var(--text-faint); font-size: 18px; }
        .tpl-badge {
          display: inline-block;
          padding: 2px 10px;
          border-radius: 11px;
          font-size: 12px;
          font-weight: 600;
        }
        .tpl-badge-original {
          background: var(--bg-surface3);
          color: var(--text-muted);
        }
        .tpl-badge-override {
          background: rgba(149, 117, 220, 0.18);
          color: #b39ddb;
        }
        .tpl-badge-stale {
          background: rgba(255, 167, 38, 0.18);
          color: #ffb74d;
        }
        .CodeMirror {
          border: 1px solid var(--border);
          border-radius: 6px;
          font-family: var(--font-mono);
        }
        .tpl-diff {
          background: var(--bg-surface2);
          border: 1px solid var(--border);
          border-radius: 6px;
          font-family: var(--font-mono);
          font-size: 12px;
          line-height: 1.5;
          max-height: 400px;
          overflow: auto;
          padding: 8px 0;
        }
        .tpl-diff-line {
          padding: 1px 12px;
          white-space: pre-wrap;
          display: flex;
          gap: 8px;
        }
        .tpl-diff-marker {
          color: var(--text-faint);
          font-weight: 700;
          width: 12px;
          flex-shrink: 0;
        }
        .tpl-diff-text {
          flex: 1;
          word-break: break-all;
        }
        .tpl-diff-context { color: var(--text-muted); }
        .tpl-diff-add {
          background: rgba(76, 175, 80, 0.12);
          color: #a5d6a7;
        }
        .tpl-diff-add .tpl-diff-marker { color: #66bb6a; }
        .tpl-diff-del {
          background: rgba(244, 67, 54, 0.12);
          color: #ef9a9a;
        }
        .tpl-diff-del .tpl-diff-marker { color: #e57373; }
      </style>

    <?php elseif ($action === 'backups'): ?>
      <div class="admin-page-head">
        <h1>💾 Backups</h1>
        <p>
          Erstellt Sicherungen der Datenbank und/oder Dateien. Backups landen
          im geschützten <code>backup/</code>-Verzeichnis und können von hier
          heruntergeladen werden. Lade sie regelmäßig auf einen anderen Speicherort.
        </p>
      </div>

      <?php if (empty($zipAvailable)): ?>
        <div class="flash flash-warning" style="margin-bottom:14px">
          ⚠️ <strong>PHP-Erweiterung „ZipArchive" fehlt!</strong>
          Datei- und Komplett-Backups sind ohne diese Extension nicht möglich.
          Frag deinen Hoster nach Aktivierung. DB-Backup funktioniert trotzdem.
        </div>
      <?php endif; ?>

      <!-- ─── Erstellen ─── -->
      <div class="form-card">
        <h2>📦 Neues Backup erstellen</h2>
        <p class="form-hint">
          Backups können je nach Forum-Größe einige Sekunden bis Minuten dauern.
          Komplett-Backups sind besonders speicherintensiv – pack erst ein DB-Backup,
          wenn du nur die Inhalte sichern willst.
        </p>

        <form method="post" action="?action=create_backup" id="backup-form">
          <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">

          <div class="form-row">
            <label>Notiz (optional)</label>
            <input type="text" name="note" maxlength="200"
                   placeholder="z.B. „vor v0.18.13 Update" oder „nach Smiley-Refactor">
            <div class="form-hint">Hilft dir später beim Identifizieren des Backups.</div>
          </div>

          <div style="display:flex;gap:8px;flex-wrap:wrap">
            <button type="submit" name="type" value="db" class="btn btn-primary">
              💾 Nur Datenbank
            </button>
            <button type="submit" name="type" value="files" class="btn btn-ghost" <?= empty($zipAvailable) ? 'disabled' : '' ?>>
              📁 Nur Dateien
            </button>
            <button type="submit" name="type" value="full" class="btn btn-ghost" <?= empty($zipAvailable) ? 'disabled' : '' ?>
                    onclick="return confirm('Komplett-Backup (DB + alle Dateien) kann je nach Forumsgröße sehr lange dauern und viel Speicherplatz brauchen. Fortfahren?');">
              📦 Komplett (DB + Dateien)
            </button>
          </div>
        </form>
      </div>

      <!-- ─── Liste ─── -->
      <div class="form-card">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
          <h2 style="margin:0">📋 Vorhandene Backups (<?= count($backups) ?>)</h2>
          <span style="font-size:13px;color:var(--text-muted)">
            Speicherbedarf: <?= e(backup_format_size($backupTotalSize)) ?>
          </span>
        </div>

        <?php if (empty($backups)): ?>
          <p style="color:var(--text-muted);font-style:italic">
            Noch keine Backups vorhanden. Erstell oben dein erstes Backup.
          </p>
        <?php else: ?>
          <div style="display:flex;flex-direction:column;gap:8px">
            <?php foreach ($backups as $b):
              $icon = match($b['type']) {
                'db'    => '💾',
                'files' => '📁',
                'full'  => '📦',
                default => '📄',
              };
              $label = match($b['type']) {
                'db'    => 'Datenbank',
                'files' => 'Dateien',
                'full'  => 'Komplett',
                default => 'Unbekannt',
              };
            ?>
              <div class="backup-item">
                <div class="backup-icon"><?= $icon ?></div>
                <div class="backup-info">
                  <div class="backup-name"><?= e($b['filename']) ?></div>
                  <div class="backup-meta">
                    <span class="backup-type-badge backup-type-<?= e($b['type']) ?>"><?= e($label) ?></span>
                    · <?= e(backup_format_size($b['size'])) ?>
                    · erstellt <?= e(date('d.m.Y H:i:s', $b['created_at'])) ?>
                  </div>
                  <?php if (!empty($b['note'])): ?>
                    <div class="backup-note">📝 <?= e($b['note']) ?></div>
                  <?php endif; ?>
                </div>
                <div class="backup-actions">
                  <a href="?action=download_backup&file=<?= e(urlencode($b['filename'])) ?>"
                     class="btn btn-primary btn-sm" title="Herunterladen">
                    ⬇️ Download
                  </a>
                  <form method="post" action="?action=delete_backup" style="display:inline"
                        onsubmit="return confirm('Backup wirklich löschen?');">
                    <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="filename" value="<?= e($b['filename']) ?>">
                    <button type="submit" class="btn btn-danger btn-sm" title="Löschen">🗑️</button>
                  </form>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- ─── Hinweise ─── -->
      <div class="form-card" style="background:var(--bg-surface2)">
        <h3 style="margin-bottom:8px">ℹ️ Wichtige Hinweise</h3>
        <ul style="margin-left:18px;line-height:1.7;color:var(--text-muted);font-size:14px">
          <li><strong>Backups gehören NICHT auf den gleichen Server</strong> – lade sie regelmäßig auf einen anderen Speicherort (lokaler Rechner, Cloud, etc.)</li>
          <li>Das <code>backup/</code>-Verzeichnis ist per <code>.htaccess</code> vor Direktzugriff geschützt; Download nur über diese Seite</li>
          <li><code>config.php</code> ist <strong>nicht</strong> im Dateien-Backup enthalten (Passwörter würden sonst exportiert)</li>
          <li>Du kannst Backups bei Bedarf per phpMyAdmin / FTP wiederherstellen – Restore-Funktion direkt im Admin folgt evtl. später</li>
          <li>Beim ersten Backup wird automatisch ein Schutz-<code>.htaccess</code> angelegt – aber prüf trotzdem dass dein Hoster <code>.htaccess</code> respektiert</li>
        </ul>
      </div>

      <style>
        .backup-item {
          display: grid;
          grid-template-columns: 36px 1fr auto;
          gap: 14px;
          align-items: center;
          padding: 12px 16px;
          background: var(--bg-surface2);
          border: 1px solid var(--border);
          border-radius: 8px;
        }
        .backup-icon { font-size: 24px; }
        .backup-name {
          font-family: var(--font-mono);
          font-size: 13px;
          font-weight: 600;
          word-break: break-all;
        }
        .backup-meta {
          font-size: 12px;
          color: var(--text-muted);
          margin-top: 2px;
        }
        .backup-note {
          font-size: 12px;
          color: var(--text-faint);
          font-style: italic;
          margin-top: 4px;
        }
        .backup-actions {
          display: flex;
          gap: 4px;
        }
        .backup-type-badge {
          display: inline-block;
          padding: 1px 8px;
          border-radius: 10px;
          font-size: 11px;
          font-weight: 600;
          text-transform: uppercase;
          letter-spacing: 0.05em;
        }
        .backup-type-db    { background: rgba(76, 175, 80, 0.18); color: #a5d6a7; }
        .backup-type-files { background: rgba(33, 150, 243, 0.18); color: #90caf9; }
        .backup-type-full  { background: rgba(149, 117, 220, 0.18); color: #b39ddb; }
      </style>

    <?php elseif ($action === 'maintenance'): ?>
      <h2>🛠️ Wartungs-Tools</h2>

      <div class="form-card" style="margin-bottom:14px">
        <h2>📊 Statistiken neu berechnen</h2>
        <p style="font-size:13px;color:var(--text-muted);margin-bottom:12px">
          Berechnet Thread-Counter, Post-Counter und Beitragsanzahlen der Nutzer aus den echten Daten neu.
          Hilfreich, wenn Statistiken nicht stimmen.
        </p>
        <form method="post" action="?action=maintenance_action">
          <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
          <input type="hidden" name="task" value="recount_stats">
          <button type="submit" class="btn btn-primary">🔄 Statistiken neu berechnen</button>
        </form>
      </div>

      <div class="form-card" style="margin-bottom:14px">
        <h2>🗄️ Datenbank optimieren</h2>
        <p style="font-size:13px;color:var(--text-muted);margin-bottom:12px">
          Führt <code>OPTIMIZE TABLE</code> auf allen Tabellen aus. Sollte gelegentlich ausgeführt werden.
        </p>
        <form method="post" action="?action=maintenance_action">
          <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
          <input type="hidden" name="task" value="optimize_tables">
          <button type="submit" class="btn btn-primary">⚡ Tabellen optimieren</button>
        </form>
      </div>

      <div class="form-card" style="margin-bottom:14px">
        <h2>🌐 IP-Logs aufräumen</h2>
        <p style="font-size:13px;color:var(--text-muted);margin-bottom:12px">
          Aktuell <strong><?= number_format((int) $ipLogSize, 0, ',', '.') ?></strong> IP-Log-Einträge.
        </p>
        <form method="post" action="?action=maintenance_action">
          <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
          <input type="hidden" name="task" value="cleanup_ip_logs">
          <div class="form-row">
            <label>Einträge älter als (Tage)</label>
            <input type="number" name="cleanup_days" value="90" min="7">
          </div>
          <button type="submit" class="btn btn-danger" onclick="return confirm('Wirklich löschen?')">🗑️ IP-Logs löschen</button>
        </form>
      </div>

      <div class="form-card">
        <h2>⏰ Abgelaufene IP-Banns entfernen</h2>
        <p style="font-size:13px;color:var(--text-muted);margin-bottom:12px">
          Aktuell <strong><?= (int) $expiredIpBans ?></strong> abgelaufene IP-Banns vorhanden.
        </p>
        <form method="post" action="?action=maintenance_action">
          <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
          <input type="hidden" name="task" value="cleanup_expired_ipbans">
          <button type="submit" class="btn btn-primary">🧹 Abgelaufene IP-Banns löschen</button>
        </form>
      </div>

      <div class="form-card" style="margin-top:14px">
        <h2>🗑️ Cache leeren</h2>
        <p style="font-size:13px;color:var(--text-muted);margin-bottom:12px">
          Leert den Template-Compile-Cache. Nützlich nach manuellen Template-Änderungen
          per FTP oder wenn nach einem Update neue Templates nicht erscheinen.
          Normal nicht nötig – Templates werden automatisch neu kompiliert, wenn sich
          die Source-Datei ändert.
        </p>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;margin-bottom:14px">
          <div class="cache-stat">
            <div class="cache-stat-label">Template-Cache</div>
            <div class="cache-stat-value">
              <?= (int) $cacheInfo['tpl_count'] ?>
              <span class="cache-stat-unit"><?= $cacheInfo['tpl_count'] === 1 ? 'Datei' : 'Dateien' ?></span>
            </div>
            <div class="cache-stat-meta">
              <?php
                $kb = $cacheInfo['tpl_bytes'] / 1024;
                echo $kb < 1 ? number_format($cacheInfo['tpl_bytes'], 0, ',', '.') . ' Bytes'
                             : number_format($kb, 1, ',', '.') . ' KB';
              ?>
            </div>
          </div>
        </div>

        <form method="post" action="?action=clear_cache" style="display:inline-flex;gap:8px;flex-wrap:wrap">
          <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
          <button type="submit" name="type" value="templates" class="btn btn-primary">
            🗑️ Template-Cache leeren
          </button>
          <button type="submit" name="type" value="all" class="btn btn-ghost">
            🧹 Alle Caches leeren
          </button>
        </form>
      </div>

      <style>
        .cache-stat {
          background: var(--bg-surface2);
          border: 1px solid var(--border);
          border-radius: 8px;
          padding: 14px 16px;
        }
        .cache-stat-label {
          font-size: 12px;
          color: var(--text-muted);
          text-transform: uppercase;
          letter-spacing: 0.05em;
          margin-bottom: 6px;
        }
        .cache-stat-value {
          font-size: 24px;
          font-weight: 700;
        }
        .cache-stat-unit {
          font-size: 13px;
          color: var(--text-muted);
          font-weight: 400;
        }
        .cache-stat-meta {
          font-size: 12px;
          color: var(--text-faint);
          margin-top: 4px;
        }
      </style>

    <?php elseif ($action === 'bot_log'): ?>
      <h2>🤖 Bot-Verkehr</h2>
      <p style="color:var(--text-muted);margin-bottom:18px;line-height:1.6">
        Diese Liste zeigt alle Bots (Suchmaschinen-Crawler, KI-Indexer, SEO-Tools, Social-Media-Crawler)
        die das Forum besucht haben. Einträge älter als 90 Tage werden automatisch entfernt.
      </p>

      <!-- Tab-Filter -->
      <div class="bot-log-tabs">
        <?php
          $tabs = [
            'all'     => ['🌐 Alle',           $botStats['all']],
            'search'  => ['🔍 Suchmaschinen',  $botStats['search']],
            'ai'      => ['🤖 KI-Bots',        $botStats['ai']],
            'suspect' => ['🕵️ Verdächtige',   $botStats['suspect']],
          ];
          foreach ($tabs as $key => [$label, $count]):
            $active = $botFilter === $key;
        ?>
          <a href="?action=bot_log&filter=<?= e($key) ?>"
             class="bot-log-tab <?= $active ? 'active' : '' ?>">
            <?= e($label) ?>
            <span class="bot-log-tab-badge"><?= (int) $count ?></span>
          </a>
        <?php endforeach; ?>
      </div>

      <?php if (empty($botList)): ?>
        <div class="empty-state" style="text-align:center;padding:40px 20px;color:var(--text-muted);background:var(--bg-surface2);border-radius:8px">
          <div style="font-size:48px;margin-bottom:12px">🤖</div>
          <p style="font-size:14px;margin:0 0 6px">
            <?php if ($botFilter === 'all'): ?>
              Noch keine Bot-Besuche registriert.
            <?php else: ?>
              Keine Einträge in dieser Kategorie.
            <?php endif; ?>
          </p>
          <p style="font-size:12px;margin:0">
            Bot-Besuche werden seit v0.18.38 automatisch geloggt.
            <?php if ($botFilter === 'all'): ?>
              Die ersten Einträge erscheinen sobald ein Bot vorbeischaut.
            <?php endif; ?>
          </p>
        </div>
      <?php else: ?>
        <div class="card">
          <table class="forum-table">
            <thead>
              <tr>
                <th>Bot</th>
                <th>Kategorie</th>
                <th>IP-Adresse</th>
                <th style="text-align:right">Besuche</th>
                <th>Letzter Besuch</th>
                <th>Letzte Seite</th>
                <th>Aktion</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($botList as $b):
                $catLabels = [
                  'search'     => '🔍 Suchmaschine',
                  'ai'         => '🤖 KI-Bot',
                  'seo'        => '📊 SEO-Tool',
                  'social'     => '📱 Social',
                  'monitoring' => '🛡️ Monitor',
                  'archive'    => '📦 Archiv',
                  'suspect'    => '🕵️ Verdächtig',
                  'generic'    => '🤖 Allgemein',
                ];
              ?>
                <tr>
                  <td>
                    <strong><?= e($b['bot_icon']) ?> <?= e($b['bot_name']) ?></strong>
                  </td>
                  <td><?= e($catLabels[$b['category']] ?? $b['category']) ?></td>
                  <td><code style="font-size:11px"><?= e($b['ip']) ?></code></td>
                  <td style="text-align:right"><strong><?= (int) $b['visit_count'] ?></strong></td>
                  <td style="font-size:12px;color:var(--text-muted)"><?= e(format_date($b['last_seen'])) ?></td>
                  <td style="font-size:12px"><?= e($b['last_url'] ?: '–') ?></td>
                  <td>
                    <a href="?action=ipbans&prefill=<?= e(urlencode($b['ip'])) ?>"
                       class="btn btn-ghost btn-xs"
                       title="IP sperren">🚫</a>
                    <a href="?action=iplookup&ip=<?= e(urlencode($b['ip'])) ?>"
                       class="btn btn-ghost btn-xs"
                       title="Details">🔍</a>
                  </td>
                </tr>
                <?php if (!empty($b['user_agent']) && $b['category'] === 'suspect'): ?>
                  <tr>
                    <td colspan="7" style="background:var(--bg-surface2);font-size:11px;color:var(--text-muted);padding:6px 14px">
                      <strong>User-Agent:</strong> <code><?= e(mb_substr($b['user_agent'], 0, 200)) ?></code>
                    </td>
                  </tr>
                <?php endif; ?>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

      <style>
        .bot-log-tabs {
          display: flex;
          gap: 4px;
          margin-bottom: 16px;
          border-bottom: 1px solid var(--border);
          flex-wrap: wrap;
        }
        .bot-log-tab {
          padding: 10px 16px;
          font-size: 13px;
          color: var(--text-muted);
          text-decoration: none;
          border-bottom: 2px solid transparent;
          transition: color .15s, border-color .15s, background .15s;
          display: inline-flex;
          align-items: center;
          gap: 8px;
        }
        .bot-log-tab:hover {
          color: var(--text-base);
          background: var(--bg-surface2);
        }
        .bot-log-tab.active {
          color: var(--color-accent);
          border-bottom-color: var(--color-accent);
          font-weight: 600;
        }
        .bot-log-tab-badge {
          background: var(--bg-surface2);
          color: var(--text-muted);
          font-size: 11px;
          padding: 1px 7px;
          border-radius: 9px;
          font-weight: 600;
        }
        .bot-log-tab.active .bot-log-tab-badge {
          background: var(--color-accent);
          color: white;
        }
      </style>

    <?php endif; ?>

  </div>
</div>
