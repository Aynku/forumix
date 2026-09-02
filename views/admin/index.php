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
          'dashboard' => '📊 ' . t('dashboard'),
          'approval'  => '🚦 ' . t('admin_freigabe') . ($pendingApproval > 0 ? ' <span class="nav-badge">' . $pendingApproval . '</span>' : ''),
          'reports'   => '🚨 ' . t('admin_nav_reports') . ($openReports > 0 ? ' <span class="nav-badge">' . $openReports . '</span>' : ''),
      ];

      $groups = [
          'users' => [
              'label' => '👥 ' . t('admin_nav_users'),
              'items' => [
                  'users'           => '👥 ' . t('admin_nav_userlist'),
                  'groups'          => '👪 ' . t('admin_nav_groups'),
                  'approvals_users' => '🆕 ' . t('admin_nav_approvals') . ($pendingUserApproval > 0 ? ' <span class="nav-badge">' . $pendingUserApproval . '</span>' : ''),
                  'invitations'     => '✉️ ' . t('admin_nav_invitations') . ($activeInvitations > 0 ? ' <span class="nav-badge">' . $activeInvitations . '</span>' : ''),
                  'warnings'        => '⚠️ ' . t('admin_nav_warnings'),
                  'modlog'          => '📜 ' . t('admin_nav_modlog'),
                  'iplookup'        => '🌐 ' . t('admin_nav_iplookup'),
                  'bot_log'         => '🤖 ' . t('admin_nav_bots'),
              ],
          ],
          'content' => [
              'label' => '📁 ' . t('admin_nav_content'),
              'items' => [
                  'categories' => '📁 ' . t('admin_nav_categories'),
                  'boards'     => '📋 ' . t('admin_nav_boards'),
                  'tags'       => '🏷️ ' . t('admin_nav_tags'),
                  'ranks'      => '🏅 ' . t('admin_nav_ranks'),
                  'smileys'    => '😀 ' . t('admin_nav_smileys'),
                  'legal'      => '📄 ' . t('admin_nav_pages'),
              ],
          ],
          'appearance' => [
              'label' => '🎨 ' . t('admin_nav_appearance'),
              'items' => [
                  'design'    => '🎨 ' . t('admin_nav_design'),
                  'templates' => '🖼️ ' . t('admin_nav_templates'),
              ],
          ],
          // System ist, wie die Installation EINGESTELLT ist. Wartung ist, was
          // man an ihr TUT. Backups und Updates standen bei den Einstellungen
          // und gehoeren nach dieser Regel herueber — zumal sie ein Handgriff
          // sind: Sicherung anlegen, Update einspielen, notfalls Wartungsmodus.
          'system' => [
              'label' => '⚙️ ' . t('admin_nav_system'),
              'items' => [
                  'settings'     => '🔧 ' . t('settings'),
                  'email'        => '📧 ' . t('admin_e_mail'),
                  'registration' => '📝 ' . t('admin_nav_registration'),
                  'modules'      => '🧩 ' . t('admin_nav_modules'),
              ],
          ],
          'maintenance_group' => [
              'label' => '🛠️ ' . t('admin_nav_maintenance'),
              'items' => [
                  'backups'           => '💾 ' . t('admin_nav_backups'),
                  'updates'           => '🚀 ' . t('admin_nav_updates'),
                  'import'            => '📦 ' . t('admin_nav_import'),
                  'maintenance_mode'  => '🛠️ ' . t('admin_nav_maintenance_mode'),
                  'maintenance'       => '🧹 ' . t('admin_nav_tools'),
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

      <?php
        // Platz fuer Module mit eigener Einstellungsseite. Ohne ihn bliebe
        // ihnen nur die Hauptnavigation des Forums — und dort steht eine
        // Einstellung, die nur Inhaber und Administratoren angeht, falsch.
        if (function_exists('render_module_hook')) {
            render_module_hook('admin_nav', ['action' => $action]);
        }
      ?>
    </div>
  </nav>

  <div class="admin-content">

    <?php /* ════════════ DASHBOARD ════════════ */ ?>
    <?php if ($action === 'dashboard'): ?>
      <h2><?= t('admin_dashboard') ?></h2>

      <div class="stat-grid">
        <?php foreach ($stats as $stat): ?>
        <div class="stat-box">
          <div class="stat-icon"><?= $stat['icon'] ?></div>
          <div class="stat-num"><?= number_format((int) $stat['value'], 0, ',', '.') ?></div>
          <div class="stat-label"><?= e($stat['label']) ?></div>
        </div>
        <?php endforeach; ?>
      </div>

      <div class="section-heading"><?= t('admin_neueste_mitglieder') ?></div>
      <div class="card">
        <table class="forum-table">
          <thead><tr><th><?= t('admin_name') ?></th><th><?= t('admin_e_mail') ?></th><th><?= t('admin_registrierungs_ip') ?></th><th><?= t('admin_registriert') ?></th></tr></thead>
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

      <div class="section-heading"><?= t('admin_letzte_logins') ?></div>
      <div class="card">
        <table class="forum-table">
          <thead><tr><th><?= t('admin_benutzer') ?></th><th><?= t('admin_ip') ?></th><th><?= t('admin_zeitpunkt') ?></th></tr></thead>
          <tbody>
            <?php if (empty($recentLogins)): ?>
              <tr><td colspan="3" style="text-align:center;color:var(--text-faint);padding:16px"><?= t('admin_noch_keine_logins_protokolliert') ?></td></tr>
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
      <h2><?= t('admin_benutzerverwaltung') ?></h2>

      <form method="get" style="margin-bottom:14px">
        <input type="hidden" name="action" value="users">
        <div style="display:flex;gap:8px">
          <input type="text" name="q" value="<?= e($searchQuery ?? '') ?>"
                 placeholder="<?= e(t('adm_suche_platzhalter')) ?>"
                 style="flex:1;padding:8px 12px;border:1px solid var(--border);border-radius:6px;background:var(--bg-surface);color:var(--text-base)">
          <button type="submit" class="btn btn-primary"><?= t('admin_suchen') ?></button>
          <?php if (!empty($searchQuery)): ?>
            <a href="?action=users" class="btn btn-ghost"><?= t('admin_zuruecksetzen') ?></a>
          <?php endif; ?>
        </div>
      </form>

      <div class="card">
        <table class="forum-table">
          <thead>
            <tr>
              <th><?= t('admin_name_e_mail') ?></th>
              <th><?= t('admin_reg_ip') ?></th>
              <th><?= t('admin_letzte_ip') ?></th>
              <th><?= t('admin_beitraege') ?></th>
              <th><?= t('admin_status') ?></th>
              <th><?= t('admin_aktionen') ?></th>
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
                  <span class="role-tag" style="background:#7c3aed"><?= t('admin_owner') ?></span>
                <?php elseif ($u['is_banned']): ?>
                  <span class="role-tag" style="background:#6b7280"><?= t('admin_gesperrt') ?></span>
                <?php elseif ($u['is_admin']): ?>
                  <span class="role-tag" style="background:#dc2626"><?= t('admin_admin') ?></span>
                <?php elseif ($u['is_mod']): ?>
                  <span class="role-tag" style="background:#f59e0b"><?= t('admin_mod') ?></span>
                <?php else: ?>
                  <span class="role-tag" style="background:var(--bg-surface3);color:var(--text-muted)"><?= t('admin_mitglied') ?></span>
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
                  <span style="font-size:11px;color:var(--text-faint);font-style:italic"><?= t('admin_gruender_geschuetzt') ?></span>
                <?php /* Andere Owner: nur der Founder kann die anfassen */ ?>
                <?php elseif (!empty($u['is_owner']) && !$isFounderViewer): ?>
                  <span style="font-size:11px;color:var(--text-faint);font-style:italic"><?= t('admin_inhaber_geschuetzt') ?></span>
                <?php else: ?>
                  <div class="action-dropdown">
                    <button type="button" class="action-dropdown-toggle action-toggle-icon btn btn-ghost btn-xs">⋯</button>
                    <div class="action-dropdown-menu">

                      <?php if (!empty($u['is_owner'])): ?>
                        <?php /* Founder sieht hier: Owner-Status entfernen */ ?>
                        <a href="<?= $base ?>remove_owner&id=<?= $uid . $csrfQS ?>"
                           class="action-danger"
                           onclick="return confirm(<?= e(json_encode(t('adm_owner_entziehen_frage', $u['username']), JSON_UNESCAPED_UNICODE)) ?>)">⬇️ Owner entziehen</a>
                      <?php elseif ($u['is_banned']): ?>
                        <a href="<?= $base ?>unban_user&id=<?= $uid . $csrfQS ?>">✅ Entsperren</a>
                      <?php else: ?>
                        <?php /* Founder-only: Owner ernennen */ ?>
                        <?php if ($isFounderViewer): ?>
                          <a href="<?= $base ?>make_owner&id=<?= $uid . $csrfQS ?>"
                             onclick="return confirm(<?= e(json_encode(t('adm_zum_inhaber_frage', $u['username']), JSON_UNESCAPED_UNICODE)) ?>)">👑 <?= t('adm_zum_inhaber') ?></a>
                          <div class="action-dropdown-divider"></div>
                        <?php endif; ?>

                        <?php if ($u['is_admin']): ?>
                          <?php if ($uid !== $cuid): ?>
                            <a href="<?= $base ?>remove_admin&id=<?= $uid . $csrfQS ?>"
                               onclick="return confirm(<?= e(json_encode(t('adm_admin_entziehen_frage'), JSON_UNESCAPED_UNICODE)) ?>)">⬇️ Admin entziehen</a>
                          <?php endif; ?>
                        <?php elseif ($u['is_mod']): ?>
                          <a href="<?= $base ?>make_admin&id=<?= $uid . $csrfQS ?>"
                             onclick="return confirm(<?= e(json_encode(t('adm_zum_admin_frage'), JSON_UNESCAPED_UNICODE)) ?>)">🔴 <?= t('adm_zum_admin') ?></a>
                          <a href="<?= $base ?>remove_mod&id=<?= $uid . $csrfQS ?>">⬇️ Mod entziehen</a>
                        <?php else: ?>
                          <a href="<?= $base ?>make_admin&id=<?= $uid . $csrfQS ?>"
                             onclick="return confirm(<?= e(json_encode(t('adm_zum_admin_frage'), JSON_UNESCAPED_UNICODE)) ?>)">🔴 <?= t('adm_zum_admin') ?></a>
                          <a href="<?= $base ?>make_mod&id=<?= $uid . $csrfQS ?>">🛡️ <?= t('adm_zum_moderator') ?></a>
                        <?php endif; ?>

                        <div class="action-dropdown-divider"></div>

                        <?php if ($uid !== $cuid): ?>
                          <a href="<?= $base ?>ban_user&id=<?= $uid . $csrfQS ?>"
                             class="action-danger"
                             onclick="return confirm(<?= e(json_encode(t('adm_sperren_frage'), JSON_UNESCAPED_UNICODE)) ?>)"><?= t('admin_sperren') ?></a>
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
                           onclick="return confirm(<?= e(json_encode(t('adm_konto_loeschen_frage', $u['username']), JSON_UNESCAPED_UNICODE)) ?>)">🗑️ <?= t('adm_konto_loeschen') ?></a>
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
      <h2><?= t('admin_kategorien_2') ?></h2>
      <p style="font-size:13px;color:var(--text-muted);margin-bottom:14px">
        <?= t('admin_kategorien_fassen_mehrere_foren') ?>
      </p>
      <div class="card" style="margin-bottom:16px">
        <table class="forum-table">
          <thead><tr><th><?= t('admin_id') ?></th><th><?= t('admin_titel') ?></th><th><?= t('admin_position') ?></th><th><?= t('admin_aktion') ?></th></tr></thead>
          <tbody>
            <?php foreach ($categories as $c): ?>
            <tr>
              <td><?= (int) $c['cat_id'] ?></td>
              <td><?= e($c['title']) ?></td>
              <td><?= (int) $c['position'] ?></td>
              <td>
                <details class="cat-edit-details">
                  <summary class="btn btn-ghost btn-xs"><?= t('admin_bearbeiten') ?></summary>
                  <form method="post" action="?action=update_cat" class="cat-edit-form">
                    <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="cat_id" value="<?= (int) $c['cat_id'] ?>">
                    <div class="form-row">
                      <label><?= t('admin_titel') ?></label>
                      <input type="text" name="title" value="<?= e($c['title']) ?>" required maxlength="100">
                    </div>
                    <div class="form-row">
                      <label><?= t('admin_position') ?></label>
                      <input type="number" name="position" value="<?= (int) $c['position'] ?>" min="0" style="width:80px">
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm"><?= t('admin_speichern_2') ?></button>
                  </form>
                </details>
                <a href="?action=delete_cat&id=<?= (int) $c['cat_id'] ?>&csrf=<?= e($csrfToken) ?>"
                   class="btn btn-danger btn-xs"
                   onclick="return confirm(<?= e(json_encode(t('adm_kategorie_loeschen_frage'), JSON_UNESCAPED_UNICODE)) ?>)">🗑️</a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="form-card">
        <h2><?= t('admin_neue_kategorie') ?></h2>
        <form method="post" action="?action=add_cat">
          <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
          <div class="form-row"><label><?= t('admin_titel') ?></label><input type="text" name="title" required></div>
          <div class="form-row"><label><?= t('admin_position') ?></label><input type="number" name="position" value="0" min="0"></div>
          <button type="submit" class="btn btn-primary"><?= t('admin_anlegen') ?></button>
        </form>
      </div>

    <?php /* ════════════ FOREN ════════════ */ ?>
    <?php elseif ($action === 'boards'): ?>
      <h2><?= t('admin_foren_2') ?></h2>
      <div class="card" style="margin-bottom:16px">
        <table class="forum-table">
          <thead><tr><th><?= t('admin_titel') ?></th><th><?= t('admin_kategorie') ?></th><th><?= t('admin_threads') ?></th><th><?= t('admin_posts') ?></th><th><?= t('admin_freigabe') ?></th><th><?= t('admin_moderatoren') ?></th><th><?= t('admin_aktion') ?></th></tr></thead>
          <tbody>
            <?php foreach ($boards as $b):
              $mods = $boardMods[(int) $b['board_id']] ?? [];
            ?>
            <tr>
              <td>
                <?php if (!empty($b['password_hash'])): ?>
                  <span title="<?= e(t('adm_passwort_geschuetzt')) ?>" style="color:var(--color-accent)">🔒</span>
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
                  <span style="color:var(--text-faint)"><?= t('adm_aus') ?></span>
                <?php else: ?>
                  <?php if ($approveThr): ?><span style="color:var(--color-warning)"><?= t('admin_themen_2') ?></span><?php endif; ?>
                  <?php if ($approveRep): ?><?php if ($approveThr): ?><br><?php endif; ?><span style="color:var(--color-warning)"><?= t('admin_antworten') ?></span><?php endif; ?>
                <?php endif; ?>
              </td>
              <td style="font-size:12px">
                <?php if (empty($mods)): ?>
                  <span style="color:var(--text-faint);font-style:italic"><?= t('adm_keine') ?></span>
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
                <a href="?action=boards&board=<?= (int) $b['board_id'] ?>&edit=details" class="btn btn-ghost btn-xs"><?= t('admin_bearbeiten') ?></a>
                <a href="?action=boards&board=<?= (int) $b['board_id'] ?>&edit=approval" class="btn btn-ghost btn-xs">🚦 Freigabe</a>
                <a href="?action=boards&board=<?= (int) $b['board_id'] ?>" class="btn btn-ghost btn-xs">🛡️ Mods</a>
                <a href="?action=delete_board&id=<?= (int) $b['board_id'] ?>&csrf=<?= e($csrfToken) ?>"
                   class="btn btn-danger btn-xs" onclick="return confirm(<?= e(json_encode(t('adm_forum_loeschen_frage'), JSON_UNESCAPED_UNICODE)) ?>)"><?= t('admin_x_loeschen') ?></a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <?php /* ── Forum-Editor ── */ ?>
      <?php if (!empty($selectedBoard) && ($_GET['edit'] ?? '') === 'details'): ?>
        <div class="form-card" style="margin-bottom:16px;border:2px solid var(--color-accent)">
          <h2>✏️ <?= t('adm_forum_bearbeiten', e($selectedBoard['title'])) ?></h2>
          <form method="post" action="?action=update_board">
            <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="board_id" value="<?= (int) $selectedBoard['board_id'] ?>">

            <div class="form-row">
              <label><?= t('admin_titel') ?></label>
              <input type="text" name="title" value="<?= e($selectedBoard['title']) ?>" required>
            </div>

            <div class="form-row">
              <label><?= t('admin_beschreibung') ?></label>
              <input type="text" name="description" value="<?= e($selectedBoard['description'] ?? '') ?>">
            </div>

            <div class="form-row">
              <label><?= t('admin_kategorie') ?></label>
              <select name="cat_id" required>
                <?php foreach ($categories as $c): ?>
                  <option value="<?= (int) $c['cat_id'] ?>" <?= (int) $selectedBoard['cat_id'] === (int) $c['cat_id'] ? 'selected' : '' ?>>
                    <?= e($c['title']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <div class="form-hint"><?= t('admin_verschiebe_das_forum_in') ?></div>
            </div>

            <div class="form-row">
              <label><?= t('admin_position') ?></label>
              <input type="number" name="position" value="<?= (int) $selectedBoard['position'] ?>" min="0" max="999">
              <div class="form-hint"><?= t('admin_niedrigere_zahl_weiter_oben') ?></div>
            </div>

            <?php /* ── GRUPPENRECHTE ── */ ?>
            <?php
              $rechte  = $brettRechte ?? [];
              $fein    = $brettFeinrechte ?? [];
              $feinDa  = !empty($feinrechteDa);
              // Beschriftung der Feinrechte. Reihenfolge wie in BRETT_FEINRECHTE,
              // damit Kopfzeile und Kaestchen nicht auseinanderlaufen.
              $feinSpalten = [
                'thema'      => [t('adm_thema'),  t('admin_neue_themen_eroeffnen')],
                'antwort'    => [t('adm_antwort'), t('admin_auf_themen_antworten')],
                'anhang'     => ['Anhang',  t('admin_dateien_anhaengen')],
                'bearbeiten' => [t('admin_aendern'),  t('admin_eigene_beitraege_nachtraeglich_aendern')],
                'umfrage'    => ['Umfrage', t('admin_umfragen_anlegen')],
              ];
              $kopf = 'padding:8px 10px;font-size:11px;text-transform:uppercase;'
                    . 'letter-spacing:.05em;color:var(--text-muted);border-bottom:1px solid var(--border)';
              $trenn = 'border-left:1px solid var(--border)';
            ?>
            <div class="form-row" style="border-top:1px dashed var(--border);padding-top:14px;margin-top:14px">
              <label><?= t('admin_gruppenrechte') ?></label>
              <div style="overflow-x:auto">
                <table style="width:100%;border-collapse:collapse">
                  <tr>
                    <th style="text-align:left;<?= $kopf ?>"><?= t('admin_gruppe') ?></th>
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
                <?= t('adm_rechte_hinweis') ?>
                <?php if ($feinDa): ?>
                  <?= t('adm_haekchen_hinweis') ?>
                <?php else: ?>
                  <br><strong><?= t('admin_feinere_rechte_stehen_noch') ?></strong>
                  <?= t('admin_sie_kommen_mit_der') ?> <em><?= t('adm_feinere_rechte_version') ?></em>
                  <?= t('adm_unter_forum_updates', FORUM_URL . '/admin/?action=updates') ?>
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
              <label><?= t('admin_wenn_jemand_kein_recht') ?></label>
              <?php $sicht = $selectedBoard['visibility'] ?? 'hidden'; ?>
              <label class="checkbox-line" style="margin-bottom:6px">
                <input type="radio" name="visibility" value="hidden" <?= $sicht !== 'locked' ? 'checked' : '' ?>>
                <span><strong><?= t('admin_verstecken') ?></strong> <?= t('adm_verstecken_erklaerung') ?></span>
              </label>
              <label class="checkbox-line">
                <input type="radio" name="visibility" value="locked" <?= $sicht === 'locked' ? 'checked' : '' ?>>
                <span><strong><?= t('admin_zeigen_aber_gesperrt') ?></strong> <?= t('adm_gesperrt_erklaerung') ?></span>
              </label>
              <div class="form-hint">
                <?= t('admin_verstecken_heisst_wirklich_weg') ?>
              </div>
            </div>

            <?php /* ── PASSWORT-SCHUTZ ── */ ?>
            <div class="form-row" style="border-top:1px dashed var(--border);padding-top:14px;margin-top:14px">
              <label><?= t('admin_passwort_schutz') ?></label>
              <?php $isProtected = !empty($selectedBoard['password_hash']); ?>
              <?php if ($isProtected): ?>
                <div style="padding:10px 12px;background:var(--color-accent-soft);border-radius:var(--radius-sm);margin-bottom:8px">
                  <strong><?= t('admin_dieses_forum_ist_aktuell') ?></strong>
                  <div style="font-size:12px;color:var(--text-muted);margin-top:4px">
                    <?= t('admin_owner_admins_globale_mods') ?>
                  </div>
                </div>
                <label class="checkbox-line" style="margin-bottom:8px">
                  <input type="checkbox" name="remove_password" value="1">
                  <span><?= t('admin_passwort_schutz_entfernen_forum') ?></span>
                </label>
              <?php endif; ?>
              <input type="password" name="new_password" value="" autocomplete="new-password"
                     placeholder="<?= $isProtected ? t('admin_neues_passwort_eingeben_um') : t('admin_passwort_setzen_leer_kein') ?>">
              <div class="form-hint">
                <?php if ($isProtected): ?>
                  <?= t('adm_passwort_nicht_angezeigt') ?>
                <?php else: ?>
                  <?= t('adm_passwort_wirkung') ?>
                <?php endif; ?>
              </div>
            </div>

            <div style="display:flex;gap:8px">
              <button type="submit" class="btn btn-primary"><?= t('admin_speichern_2') ?></button>
              <a href="?action=boards" class="btn btn-ghost"><?= t('admin_abbrechen') ?></a>
            </div>
          </form>
        </div>
      <?php endif; ?>

      <?php /* ── Freigabe-Editor pro Board ── */ ?>
      <?php if (!empty($selectedBoard) && ($_GET['edit'] ?? '') === 'approval'): ?>
        <div class="form-card" style="margin-bottom:16px;border:2px solid var(--color-warning)">
          <h2>🚦 <?= t('adm_freigabe_fuer', e($selectedBoard['title'])) ?></h2>
          <p style="font-size:12px;color:var(--text-muted);margin-bottom:14px">
            <?= t('admin_hier_festlegen_welche_neuen') ?>
            <strong><?= t('admin_admins_und_moderatoren_sind') ?></strong> <?= t('adm_beitraege_sofort') ?>
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
                  <strong><?= t('admin_neue_themen_freigeben') ?></strong>
                  <div style="font-size:12px;color:var(--text-muted);margin-top:3px">
                    <?= t('admin_neue_themen_im_forum') ?>
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
                  <strong><?= t('admin_antworten_freigeben') ?></strong>
                  <div style="font-size:12px;color:var(--text-muted);margin-top:3px">
                    <?= t('admin_antworten_auf_bestehende_themen') ?>
                  </div>
                </div>
              </label>
            </div>
            <button type="submit" class="btn btn-primary"><?= t('admin_speichern_2') ?></button>
            <a href="?action=boards" class="btn btn-ghost"><?= t('admin_abbrechen') ?></a>
          </form>
        </div>
      <?php endif; ?>

      <?php if (!empty($selectedBoard)): ?>
        <div class="form-card" style="margin-bottom:16px;border:2px solid var(--text-base)">
          <h2>🛡️ <?= t('adm_moderatoren_fuer', e($selectedBoard['title'])) ?></h2>
          <p style="font-size:12px;color:var(--text-muted);margin-bottom:14px">
            <strong><?= t('admin_hinweis') ?></strong> <?= t('admin_globale_moderatoren_is_mod') ?>
          </p>

          <div class="section-heading"><?= t('admin_aktuelle_moderatoren') ?></div>
          <?php if (empty($selectedMods)): ?>
            <p style="color:var(--text-faint);font-style:italic;padding:14px 0">
              <?= t('admin_keine_moderatoren_zugewiesen') ?>
            </p>
          <?php else: ?>
            <table class="forum-table" style="margin-bottom:16px">
              <thead><tr><th><?= t('admin_benutzer') ?></th><th><?= t('admin_rolle') ?></th><th><?= t('admin_zugewiesen_am') ?></th><th></th></tr></thead>
              <tbody>
                <?php foreach ($selectedMods as $m): ?>
                  <tr>
                    <td><a href="<?= e(FORUM_URL) ?>/profile.php?id=<?= (int) $m['user_id'] ?>"><?= e($m['username']) ?></a></td>
                    <td style="font-size:11px">
                      <?php if ($m['is_admin']): ?>
                        <span style="color:#dc2626;font-weight:600"><?= t('admin_administrator') ?></span>
                      <?php elseif ($m['is_mod']): ?>
                        <span style="color:#f59e0b;font-weight:600"><?= t('admin_globaler_moderator') ?></span>
                      <?php else: ?>
                        <span style="color:#f59e0b;font-weight:600"><?= t('admin_forum_moderator') ?></span>
                      <?php endif; ?>
                    </td>
                    <td style="font-size:11px;color:var(--text-muted)"><?= e(format_date($m['assigned_at'])) ?></td>
                    <td>
                      <a href="?action=remove_board_mod&board=<?= (int) $selectedBoard['board_id'] ?>&user=<?= (int) $m['user_id'] ?>&csrf=<?= e($csrfToken) ?>"
                         class="btn btn-danger btn-xs"
                         onclick="return confirm('Moderator entfernen?')"><?= t('admin_entfernen') ?></a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>

          <div class="section-heading"><?= t('admin_moderator_hinzufuegen') ?></div>
          <form method="post" action="?action=add_board_mod">
            <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="board_id" value="<?= (int) $selectedBoard['board_id'] ?>">
            <div class="form-row">
              <label><?= t('admin_benutzer_auswaehlen') ?></label>
              <select name="user_id" required>
                <option value=""><?= t('report_bitte_auswaehlen') ?></option>
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
            <button type="submit" class="btn btn-primary"><?= t('admin_hinzufuegen') ?></button>
            <a href="?action=boards" class="btn btn-ghost"><?= t('admin_schliessen') ?></a>
          </form>
        </div>
      <?php endif; ?>

      <div class="form-card">
        <h2><?= t('admin_neues_forum') ?></h2>
        <form method="post" action="?action=add_board" autocomplete="off">
          <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
          <div class="form-row">
            <label><?= t('admin_kategorie') ?></label>
            <select name="cat_id">
              <?php foreach ($categories as $c): ?>
              <option value="<?= (int) $c['cat_id'] ?>"><?= e($c['title']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-row"><label><?= t('admin_titel') ?></label><input type="text" name="title" required></div>
          <div class="form-row"><label><?= t('admin_beschreibung') ?></label><input type="text" name="description"></div>
          <div class="form-row"><label><?= t('admin_position') ?></label><input type="number" name="position" value="0" min="0"></div>

          <div class="form-row" style="border-top:1px dashed var(--border);padding-top:14px;margin-top:14px">
            <label><?= t('admin_passwort_schutz') ?> <span style="font-weight:400;color:var(--text-muted);font-size:11px"><?= t('admin_optional') ?></span></label>
            <input type="password" name="new_password" value="" autocomplete="new-password"
                   placeholder="<?= e(t('adm_passwort_setzen_platzhalter')) ?>">
            <div class="form-hint">
              <?= t('admin_wenn_gesetzt_user_muessen_2') ?>
            </div>
          </div>

          <button type="submit" class="btn btn-primary"><?= t('admin_anlegen') ?></button>
        </form>
      </div>

    <?php /* ════════════ RÄNGE ════════════ */ ?>
    <?php elseif ($action === 'groups'): ?>
      <?php /* ── GRUPPEN ────────────────────────────────────────────────── */ ?>
      <h2><?= t('admin_gruppen') ?></h2>
      <p style="font-size:13px;color:var(--text-muted);margin-bottom:14px">
        <?= t('admin_gruppen_entscheiden_wer_welches') ?> <a href="?action=boards"><?= t('admin_foren') ?></a>.
      </p>

      <div class="card" style="margin-bottom:16px">
        <div style="overflow-x:auto">
          <table class="forum-table">
            <thead>
              <tr><th><?= t('admin_gruppe') ?></th><th><?= t('admin_mitglieder') ?></th><th><?= t('admin_abzeichen') ?></th><th><?= t('admin_in_rechten') ?></th><th></th></tr>
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
                      <span style="color:var(--text-muted)"><?= t('adm_alle_gaeste') ?></span>
                    <?php elseif ($g['slug'] === 'members'): ?>
                      <span style="color:var(--text-muted)">automatisch</span>
                    <?php else: ?>
                      <?= (int) ($mitgliederZahl[$gid] ?? 0) ?>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?= !empty($g['show_badge'])
                          ? e(t('adm_wird_gezeigt'))
                          : '<span style="color:var(--text-faint)">' . e(t('adm_aus')) . '</span>' ?>
                  </td>
                  <?php $nForen = (int) ($rechteJeGruppe[$gid] ?? 0); ?>
                  <td><?= $nForen ?> <?= $nForen === 1 ? 'Forum' : 'Foren' ?></td>
                  <td style="white-space:nowrap">
                    <a href="?action=groups&group=<?= $gid ?>" class="btn btn-ghost btn-xs"><?= t('admin_x_bearbeiten') ?></a>
                    <?php if (!$eingebaut): ?>
                      <a href="?action=delete_group&id=<?= $gid ?>&csrf=<?= e($csrfToken) ?>"
                         class="btn btn-ghost btn-xs"
                         onclick="return confirm(<?= e(json_encode(t('adm_gruppe_loeschen_frage', $g['name']), JSON_UNESCAPED_UNICODE)) ?>)"><?= t('admin_x_loeschen') ?></a>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="card" style="margin-bottom:16px">
        <h3 style="margin-bottom:12px"><?= t('admin_neue_gruppe') ?></h3>
        <form method="post" action="?action=create_group">
          <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
          <div class="form-row">
            <label><?= t('admin_name') ?></label>
            <input type="text" name="name" required maxlength="60" placeholder="z. B. Kunden">
          </div>
          <label class="checkbox-line">
            <input type="checkbox" name="show_badge" value="1">
            <span><?= t('admin_als_abzeichen_neben_dem') ?></span>
          </label>
          <div class="form-hint">
            <?= t('admin_ohne_haekchen_bleibt_die') ?>
          </div>
          <div style="margin-top:14px"><button class="btn btn-primary"><?= t('admin_anlegen') ?></button></div>
        </form>
      </div>

      <?php if (!empty($gruppe)): ?>
        <div class="card" style="margin-bottom:16px">
          <h3 style="margin-bottom:12px"><?= e($gruppe['name']) ?></h3>

          <form method="post" action="?action=update_group">
            <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="group_id" value="<?= (int) $gruppe['group_id'] ?>">
            <div class="form-row">
              <label><?= t('admin_name') ?></label>
              <input type="text" name="name" value="<?= e($gruppe['name']) ?>" required maxlength="60">
              <?php if (!empty($gruppe['is_builtin'])): ?>
                <div class="form-hint">
                  <?= t('admin_der_anzeigename_laesst_sich') ?><code><?= e($gruppe['slug']) ?></code>)<?= t('adm_slug_nicht_aenderbar') ?>
                </div>
              <?php endif; ?>
            </div>
            <label class="checkbox-line">
              <input type="checkbox" name="show_badge" value="1" <?= !empty($gruppe['show_badge']) ? 'checked' : '' ?>>
              <span><?= t('admin_als_abzeichen_neben_dem') ?></span>
            </label>
            <label class="checkbox-line" style="margin-top:8px">
              <input type="checkbox" name="hide_rank" value="1" <?= !empty($gruppe['hide_rank']) ? 'checked' : '' ?>>
              <span><?= t('admin_statt_des_rangs_zeigen') ?></span>
            </label>
            <div class="form-hint" style="margin-top:6px">
              <?= t('admin_der_rang_kommt_aus') ?>
            </div>
            <div style="margin-top:14px"><button class="btn btn-primary"><?= t('admin_speichern') ?></button></div>
          </form>

          <?php /* ── Bild der Gruppe ── */ ?>
          <div style="border-top:1px dashed var(--border);margin-top:18px;padding-top:16px">
            <h3 style="font-size:14px;margin:0 0 10px"><?= t('admin_bild') ?></h3>
            <div style="display:flex;gap:16px;align-items:flex-start">
              <?php if (!empty($gruppe['avatar'])): ?>
                <img src="<?= e(FORUM_URL . '/uploads/gruppen/' . rawurlencode($gruppe['avatar'])) ?>"
                     alt="" width="64" height="64"
                     style="border-radius:50%;background:var(--bg-surface2);object-fit:cover">
              <?php else: ?>
                <div style="width:64px;height:64px;border-radius:50%;background:var(--bg-surface2);
                            display:flex;align-items:center;justify-content:center;color:var(--text-faint)">–</div>
              <?php endif; ?>
              <div style="flex:1">
                <form method="post" action="?action=group_avatar" enctype="multipart/form-data">
                  <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
                  <input type="hidden" name="group_id" value="<?= (int) $gruppe['group_id'] ?>">
                  <input type="file" name="bild" accept="image/png,image/jpeg,image/webp">
                  <div style="margin-top:10px;display:flex;gap:8px">
                    <button class="btn btn-primary btn-sm"><?= t('admin_hochladen') ?></button>
                    <?php if (!empty($gruppe['avatar'])): ?>
                      <button name="entfernen" value="1" class="btn btn-ghost btn-sm"><?= t('admin_entfernen') ?></button>
                    <?php endif; ?>
                  </div>
                </form>
                <div class="form-hint" style="margin-top:8px">
                  <?= t('admin_wer_in_dieser_gruppe') ?>
                </div>
              </div>
            </div>
          </div>

          <?php if (empty($gruppe['is_builtin'])): ?>
            <div style="border-top:1px dashed var(--border);margin-top:18px;padding-top:16px">
              <h3 style="font-size:14px;margin-bottom:10px"><?= t('adm_mitglieder') ?> (<?= count($mitglieder ?? []) ?>)</h3>

              <?php if (empty($mitglieder)): ?>
                <p style="color:var(--text-muted);font-size:14px"><?= t('admin_noch_niemand_eingetragen') ?></p>
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
                               href="?action=group_member_remove&group=<?= (int) $gruppe['group_id'] ?>&user=<?= (int) $m['user_id'] ?>&csrf=<?= e($csrfToken) ?>"><?= t('admin_entfernen') ?></a>
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
                  <label><?= t('admin_mitglied_hinzufuegen') ?></label>
                  <select name="user_id" required>
                    <?php foreach (($waehlbar ?? []) as $u): ?>
                      <option value="<?= (int) $u['user_id'] ?>"><?= e($u['username']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <button class="btn btn-primary"><?= t('admin_eintragen') ?></button>
              </form>
            </div>
          <?php else: ?>
            <div class="form-hint" style="margin-top:14px">
              <?= t('admin_in_diese_gruppe_wird') ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

    <?php elseif ($action === 'ranks'): ?>
      <h2><?= t('admin_rangsystem') ?></h2>
      <p style="font-size:13px;color:var(--text-muted);margin-bottom:14px">
        <?= t('admin_raenge_automatisch') ?>
        <a href="?action=recalc_ranks&csrf=<?= e($csrfToken) ?>" class="btn btn-primary btn-sm"
           onclick="return confirm(<?= e(json_encode(t('adm_raenge_neu_frage'), JSON_UNESCAPED_UNICODE)) ?>)" style="margin-left:8px">
          🔄 <?= t('adm_alle_neu_berechnen') ?>
        </a>
      </p>
      <div class="card" style="margin-bottom:16px">
        <table class="forum-table">
          <thead><tr><th><?= t('admin_rang') ?></th><th><?= t('admin_beitraege_ab') ?></th><th><?= t('admin_sterne') ?></th><th><?= t('admin_mitglieder') ?></th></tr></thead>
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
      <div class="section-heading"><?= t('admin_top_10_mitglieder') ?></div>
      <div class="card">
        <table class="forum-table">
          <thead><tr><th>#</th><th><?= t('admin_mitglied') ?></th><th><?= t('admin_rang') ?></th><th><?= t('admin_beitraege') ?></th></tr></thead>
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
      <h2><?= t('admin_forum_einstellungen') ?></h2>

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
        <h2><?= t('admin_rollen_avatare') ?></h2>
        <p style="font-size:13px;color:var(--text-muted);margin-bottom:14px">
          <?= t('admin_rollenbilder_hinweis', '<strong>' . e(t('admin_kein_eigenes_avatar')) . '</strong>') ?>
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
                    <span style="color:var(--color-success)"><?= t('admin_eigenes_bild_aktiv') ?></span>
                  <?php else: ?>
                    <span style="color:var(--text-muted)"><?= t('admin_standard_svg_aktiv') ?></span>
                  <?php endif; ?>
                </div>
                <form method="post" action="?action=upload_role_avatar" enctype="multipart/form-data"
                      style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;margin-top:8px">
                  <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
                  <input type="hidden" name="role" value="<?= e($role) ?>">
                  <input type="file" name="image" accept="image/png,image/jpeg,image/webp,image/svg+xml" required
                         style="font-size:11px;flex:1;min-width:140px">
                  <button type="submit" class="btn btn-primary btn-sm"><?= t('admin_hochladen') ?></button>
                </form>
                <?php if ($hasCustom): ?>
                  <form method="post" action="?action=delete_role_avatar" style="margin-top:4px"
                        onsubmit="return confirm(<?= e(json_encode(t('adm_eigenes_bild_loeschen_frage', $label), JSON_UNESCAPED_UNICODE)) ?>)">
                    <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="role" value="<?= e($role) ?>">
                    <button type="submit" class="btn btn-ghost btn-sm" style="font-size:11px"><?= t('admin_zurueck_zum_standard') ?></button>
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
          💡 <strong><?= t('admin_hinweis') ?></strong> <?= t('admin_branding_logo_name_slogan') ?>
          <a href="?action=design"><strong><?= t('admin_design') ?></strong></a>. <?= t('adm_nur_funktionale') ?>
        </div>

        <?php /* ═══ 1. ALLGEMEIN ═════════════════════════════════════ */ ?>
        <div class="form-card" style="margin-bottom:16px">
          <h2><?= t('admin_allgemein') ?></h2>
          <div class="form-row">
            <label><?= t('admin_forenbeschreibung') ?></label>
            <input type="text" name="forum_description" value="<?= e($settings['forum_description'] ?? '') ?>"
                   placeholder="<?= e(t('adm_beschreibung_platzhalter')) ?>">
            <div class="form-hint"><?= t('admin_erscheint_im_browser_in') ?></div>
          </div>
          <div class="form-row">
            <label><?= t('admin_standard_sprache') ?></label>
            <select name="forum_default_lang">
              <?php $lang = $settings['forum_default_lang'] ?? 'de'; ?>
              <option value="de" <?= $lang === 'de' ? 'selected' : '' ?>>🇩🇪 Deutsch</option>
              <option value="en" <?= $lang === 'en' ? 'selected' : '' ?>>🇬🇧 English</option>
            </select>
            <div class="form-hint"><?= t('admin_wird_fuer_nicht_eingeloggte') ?></div>
          </div>
        </div>

        <?php /* ═══ 2. BEITRÄGE & THREADS ════════════════════════════ */ ?>
        <div class="form-card" style="margin-bottom:16px">
          <h2><?= t('admin_beitraege_threads') ?></h2>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
            <div class="form-row">
              <label><?= t('admin_min_beitragslaenge_zeichen') ?></label>
              <input type="number" name="min_post_length" min="1" max="1000"
                     value="<?= e($settings['min_post_length'] ?? '3') ?>">
            </div>
            <div class="form-row">
              <label><?= t('admin_max_beitragslaenge_zeichen') ?></label>
              <input type="number" name="max_post_length" min="100" max="100000"
                     value="<?= e($settings['max_post_length'] ?? '20000') ?>">
            </div>
            <div class="form-row">
              <label><?= t('admin_bearbeitungsfenster_minuten') ?></label>
              <input type="number" name="edit_window_minutes" min="0" max="10080"
                     value="<?= e($settings['edit_window_minutes'] ?? '0') ?>">
              <div class="form-hint"><?= t('admin_0_unbegrenzt_mods_admins') ?></div>
            </div>
            <div class="form-row">
              <label><?= t('admin_bot_erkennung') ?></label>
              <select name="bot_detection_level">
                <?php $bd = $settings['bot_detection_level'] ?? 'medium'; ?>
                <option value="off"    <?= $bd === 'off'    ? 'selected' : '' ?>>🚫 <?= t('adm_aus') ?></option>
                <option value="low"    <?= $bd === 'low'    ? 'selected' : '' ?>>🟢 Locker</option>
                <option value="medium" <?= $bd === 'medium' ? 'selected' : '' ?>>🟡 Normal</option>
                <option value="high"   <?= $bd === 'high'   ? 'selected' : '' ?>>🔴 Streng</option>
              </select>
              <div class="form-hint"><?= t('admin_honeypots_submission_timing_user') ?></div>
            </div>
            <div class="form-row">
              <label><?= t('admin_beitraege_pro_seite') ?></label>
              <input type="number" name="posts_per_page" min="5" max="100"
                     value="<?= e($settings['posts_per_page'] ?? '20') ?>">
            </div>
            <div class="form-row">
              <label><?= t('admin_threads_pro_seite') ?></label>
              <input type="number" name="threads_per_page" min="5" max="100"
                     value="<?= e($settings['threads_per_page'] ?? '25') ?>">
            </div>
          </div>
        </div>

        <?php /* ═══ 3. MODERATION & SICHERHEIT ═══════════════════════ */ ?>
        <div class="form-card" style="margin-bottom:16px">
          <h2><?= t('admin_moderation_sicherheit') ?></h2>

          <div class="form-row">
            <label><?= t('admin_auto_bann_ab_x') ?></label>
            <input type="number" name="warning_ban_threshold" min="0" max="100"
                   value="<?= e($settings['warning_ban_threshold'] ?? '10') ?>">
            <div class="form-hint"><?= t('admin_sammelt_ein_user_so') ?></div>
          </div>

          <div class="form-row">
            <label>
              <input type="checkbox" name="show_ips_to_mods" value="1"
                <?= ($settings['show_ips_to_mods'] ?? '1') === '1' ? 'checked' : '' ?>>
              <?= t('adm_ips_fuer_mods') ?>
            </label>
            <div class="form-hint"><?= t('admin_wenn_aus_nur_admins') ?></div>
          </div>

          <div class="form-row">
            <label>
              <input type="checkbox" name="notify_mods_on_report" value="1"
                <?= ($settings['notify_mods_on_report'] ?? '1') === '1' ? 'checked' : '' ?>>
              <?= t('adm_mods_benachrichtigen') ?>
            </label>
            <div class="form-hint"><?= t('admin_sendet_eine_in_app') ?></div>
          </div>

          <h3 style="margin-top:18px;font-size:14px;color:var(--text-muted)"><?= t('admin_anti_spam_rate_limits') ?></h3>
          <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px">
            <div class="form-row">
              <label><?= t('admin_cooldown_zwischen_beitraegen_sek') ?></label>
              <input type="number" name="post_cooldown_seconds" min="0" max="600"
                     value="<?= e($settings['post_cooldown_seconds'] ?? '15') ?>">
            </div>
            <div class="form-row">
              <label><?= t('admin_max_login_versuche_15') ?></label>
              <input type="number" name="rate_limit_login" min="1" max="100"
                     value="<?= e($settings['rate_limit_login'] ?? '5') ?>">
            </div>
            <div class="form-row">
              <label><?= t('admin_max_registrierungen_std') ?></label>
              <input type="number" name="rate_limit_register" min="1" max="100"
                     value="<?= e($settings['rate_limit_register'] ?? '3') ?>">
            </div>
          </div>

          <h3 style="margin-top:18px;font-size:14px;color:var(--text-muted)"><?= t('admin_captcha_bot_schutz') ?></h3>
          <p style="font-size:12px;color:var(--text-muted);margin-bottom:10px">
            <?= t('admin_adaptive_anti_bot_schicht') ?>
          </p>
          <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px">
            <div class="form-row">
              <label><?= t('admin_captcha_aktiv') ?></label>
              <select name="captcha_enabled">
                <option value="1" <?= ($settings['captcha_enabled'] ?? '1') === '1' ? 'selected' : '' ?>>✅ An</option>
                <option value="0" <?= ($settings['captcha_enabled'] ?? '1') === '0' ? 'selected' : '' ?>>❌ <?= t('adm_aus') ?></option>
              </select>
            </div>
            <div class="form-row">
              <label><?= t('admin_captcha_nach_fehlversuchen') ?></label>
              <input type="number" name="captcha_threshold" min="1" max="20"
                     value="<?= e($settings['captcha_threshold'] ?? '3') ?>">
              <div class="form-hint"><?= t('admin_mathe_aufgabe_wird_ab') ?></div>
            </div>
            <div class="form-row">
              <label><?= t('admin_min_submit_zeit_ms') ?></label>
              <input type="number" name="captcha_min_time" min="0" max="10000" step="100"
                     value="<?= e($settings['captcha_min_time'] ?? '1500') ?>">
              <div class="form-hint"><?= t('admin_formular_schneller_als_x') ?></div>
            </div>
          </div>
        </div>

        <?php /* ═══ 4. RECHTLICHES & DATENSCHUTZ ═════════════════════ */ ?>
        <div class="form-card" style="margin-bottom:16px">
          <h2><?= t('admin_cookie_hinweis_optional') ?></h2>
          <div class="form-row">
            <label>
              <input type="checkbox" name="cookie_banner_enabled" value="1"
                <?= ($settings['cookie_banner_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
              <?= t('adm_cookie_banner_zeigen') ?>
            </label>
            <div class="form-hint"><?= t('admin_zeigt_besuchern_einen_einmaligen') ?></div>
          </div>
          <div class="form-row">
            <label><?= t('admin_banner_text') ?></label>
            <textarea name="cookie_banner_text" style="min-height:60px"
                      placeholder="<?= e(t('adm_cookie_platzhalter')) ?>"><?= e($settings['cookie_banner_text'] ?? '') ?></textarea>
            <div class="form-hint"><?= t('admin_wenn_leer_standard_text') ?></div>
          </div>
        </div>

        <button type="submit" class="btn btn-primary"><?= t('admin_einstellungen_speichern') ?></button>
      </form>

    <?php /* ════════════ IP-LOOKUP ════════════ */ ?>
    <?php /* ════════════ NEU v0.18.49: 📧 E-MAIL (SMTP) ════════════ */ ?>
    <?php elseif ($action === 'email'): ?>
      <h2><?= t('admin_e_mail_versand_smtp') ?></h2>

      <div class="form-card" style="margin-bottom:16px">
        <p style="color:var(--text-muted);font-size:14px;line-height:1.6">
          <?= t('admin_wird_fuer_passwort_reset') ?>
        </p>

        <form method="post" action="?action=save_settings">
          <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
          <input type="hidden" name="settings_section" value="email">

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
            <div class="form-row">
              <label><?= t('admin_smtp_host') ?></label>
              <input type="text" name="smtp_host" value="<?= e($settings['smtp_host'] ?? '') ?>"
                     placeholder="smtp-relay.brevo.com">
              <div class="form-hint"><?= t('admin_leer_lassen_fuer_php') ?></div>
            </div>
            <div class="form-row">
              <label><?= t('admin_verbindung') ?></label>
              <select name="smtp_connection">
                <?php
                  $port = (string) ($settings['smtp_port'] ?? '587');
                  $enc  = (string) ($settings['smtp_encryption'] ?? 'tls');
                  $cur  = ($enc === 'ssl') ? 'ssl' : (($port === '2525') ? 'tls2525' : (($enc === '' || $enc === 'none') ? 'none' : 'tls'));
                ?>
                <option value="tls"     <?= $cur === 'tls'     ? 'selected' : '' ?>>🔒 STARTTLS (Port 587)</option>
                <option value="ssl"     <?= $cur === 'ssl'     ? 'selected' : '' ?>>🔒 SSL/TLS (Port 465)</option>
                <option value="tls2525" <?= $cur === 'tls2525' ? 'selected' : '' ?>>🔒 STARTTLS (Port 2525)</option>
                <option value="none"    <?= $cur === 'none'    ? 'selected' : '' ?>>⚠️ <?= t('adm_unverschluesselt') ?></option>
              </select>
              <div class="form-hint"><?= t('admin_falls_587_nicht_funktioniert') ?></div>
            </div>
            <div class="form-row">
              <label><?= t('admin_smtp_benutzername') ?></label>
              <input type="text" name="smtp_user" value="<?= e($settings['smtp_user'] ?? '') ?>"
                     placeholder="<?= e(t('adm_beispiel_konto_mail')) ?>">
            </div>
            <div class="form-row">
              <label><?= t('admin_smtp_passwort_api_key') ?></label>
              <input type="password" name="smtp_pass"
                     placeholder="<?= empty($settings['smtp_pass']) ? t('adm_noch_nicht_gesetzt') : '••••••••••••' ?>">
              <div class="form-hint">
                <?= t('admin_leer_behaelt_passwort') ?>
                <?php if (!empty($cryptReady)): ?>
                  <?= t('admin_wird_verschluesselt_abgelegt') ?>
                <?php else: ?>
                  <strong><?= t('admin_liegt_unverschluesselt_in_der') ?></strong>
                <?php endif; ?>
              </div>
            </div>
            <div class="form-row">
              <label><?= t('admin_absender_e_mail') ?></label>
              <?php if (defined('FORUM_MAIL_FROM')): ?>
                <input type="email" value="<?= e(FORUM_MAIL_FROM) ?>" disabled>
                <div class="form-hint">
                  <?= t('admin_diese_installation_hat_den') ?> <code>config.php</code>
                  <?= t('adm_absender_fest') ?>
                </div>
              <?php else: ?>
              <input type="email" name="smtp_from" value="<?= e($settings['smtp_from'] ?? '') ?>"
                     placeholder="<?= e(t('adm_beispiel_absender_mail')) ?>">
              <div class="form-hint">
                <?= t('admin_absender_hinweis', '<strong>' . e(t('admin_gilt_auch_ohne_smtp')) . '</strong>', '<code>mail()</code>') ?>
                <?= t('admin_adresse_der_domain') ?>
              </div>
              <?php endif; ?>
            </div>
            <div class="form-row">
              <label><?= t('admin_absender_name') ?></label>
              <?php if (defined('FORUM_MAIL_FROM_NAME')): ?>
                <input type="text" value="<?= e(FORUM_MAIL_FROM_NAME) ?>" disabled>
                <div class="form-hint"><?= t('admin_in_der') ?> <code>config.php</code> festgelegt.</div>
              <?php else: ?>
              <input type="text" name="smtp_from_name" value="<?= e($settings['smtp_from_name'] ?? '') ?>"
                     placeholder="<?= e(FORUM_NAME) ?>">
              <?php endif; ?>
            </div>
          </div>

          <?php if (empty($cryptReady) && !empty($cryptKeyVorschlag)): ?>
            <div class="note" style="margin-top:14px">
              <strong><?= t('admin_passwort_verschluesselt_ablegen') ?></strong>
              <p style="margin:6px 0">
                <?= t('admin_diese_installation_hat_noch') ?>
                <code>config.php</code> <?= t('adm_config_aufmachen') ?>
              </p>
              <pre style="white-space:pre-wrap;word-break:break-all;background:var(--bg-body);padding:10px;border-radius:6px;margin:0"><code>define('FORUM_CRYPT_KEY', '<?= e($cryptKeyVorschlag) ?>');</code></pre>
              <p style="margin:8px 0 0;font-size:13px">
                <?= t('adm_schluessel_hinweis', '<strong>' . e(t('adm_nicht_mehr_aendern')) . '</strong>',
                                              '<code>config.php</code>') ?>
              </p>
            </div>
          <?php endif; ?>

          <button type="submit" class="btn btn-primary" style="margin-top:14px"><?= t('admin_smtp_einstellungen_speichern') ?></button>
        </form>
      </div>

      <?php /* Die IDs gehoeren zum Skript in forum.js: es schickt die Adresse
               an ?action=send_test_mail und schreibt die Antwort in den Hinweis
               darunter. Vorher stand hier ein Formular auf send_test_email —
               diese Aktion gibt es nicht, der Knopf lief also ins Leere. */ ?>
      <div class="form-card">
        <h3 style="margin-top:0"><?= t('admin_test_mail_senden') ?></h3>
        <p style="font-size:13px;color:var(--text-muted);margin-bottom:14px">
          <?= t('admin_sendet_eine_test_mail') ?>
          <strong><?= t('admin_speichere_zuerst') ?></strong> <?= t('adm_deine_aenderungen') ?>
        </p>
        <div class="form-row">
          <label><?= t('admin_an_welche_adresse') ?></label>
          <div style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">
            <input type="email" id="test-mail-to" placeholder="<?= e(t('adm_beispiel_mail')) ?>" style="flex:1;min-width:200px">
            <button type="button" id="send-test-mail-btn" class="btn btn-ghost"><?= t('admin_test_senden') ?></button>
          </div>
          <div class="form-hint" id="test-mail-status"></div>
        </div>
      </div>

    <?php /* ════════════ NEU v0.18.49: 📝 REGISTRIERUNG ════════════ */ ?>
    <?php elseif ($action === 'registration'): ?>
      <h2><?= t('admin_registrierungs_einstellungen') ?></h2>

      <form method="post" action="?action=save_settings">
        <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
        <input type="hidden" name="settings_section" value="registration">

        <div class="form-card" style="margin-bottom:16px">
          <h3 style="margin-top:0"><?= t('admin_registrierungs_modus') ?></h3>
          <div class="form-row">
            <label>
              <input type="checkbox" name="registration_enabled" value="1"
                <?= ($settings['registration_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
              Registrierungen erlauben
            </label>
            <div class="form-hint"><?= t('admin_wenn_deaktiviert_nur_bestehende') ?></div>
          </div>
          <div class="form-row">
            <label>
              <input type="checkbox" name="email_verification_required" value="1"
                <?= ($settings['email_verification_required'] ?? '0') === '1' ? 'checked' : '' ?>>
              <?= t('adm_mail_bestaetigung_pflicht') ?>
            </label>
            <div class="form-hint"><?= t('admin_user_koennen_sich_erst') ?></div>
          </div>
          <div class="form-row">
            <label>
              <input type="checkbox" name="registration_approval_required" value="1"
                <?= ($settings['registration_approval_required'] ?? '0') === '1' ? 'checked' : '' ?>>
              <?= t('adm_freigabe_pflicht') ?>
            </label>
            <div class="form-hint"><?= t('admin_neue_accounts_muessen_vom') ?></div>
          </div>
          <?php if (($settings['registration_approval_required'] ?? '0') === '1'): ?>
            <div style="background:rgba(74,157,74,0.06);padding:14px;border-radius:6px;border-left:3px solid #4a9d4a;margin:6px 0 14px">
              <div style="font-weight:600;margin-bottom:6px"><?= t('admin_einladungs_codes') ?></div>
              <p style="font-size:12px;color:var(--text-muted);margin:0 0 10px;line-height:1.5">
                <?= t('admin_du_kannst_personen_einladen') ?>
              </p>
              <a href="?action=invitations" class="btn btn-ghost btn-sm"><?= t('admin_einladungen_verwalten') ?></a>
            </div>
          <?php endif; ?>
          <div class="form-row">
            <label>
              <input type="checkbox" name="registration_terms_required" value="1"
                <?= ($settings['registration_terms_required'] ?? '1') === '1' ? 'checked' : '' ?>>
              Zustimmung zu Nutzungsbedingungen erforderlich
            </label>
            <div class="form-hint"><?= t('admin_user_muessen_bei_der') ?> <a href="?action=legal"><?= t('admin_nutzungsbedingungen') ?></a> aktiv zustimmen (DSGVO-konform).</div>
          </div>
        </div>

        <div class="form-card" style="margin-bottom:16px">
          <h3 style="margin-top:0"><?= t('admin_benutzername_passwort') ?></h3>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
            <div class="form-row">
              <label><?= t('admin_min_benutzernamen_laenge') ?></label>
              <input type="number" name="min_username_length" min="2" max="50"
                     value="<?= e($settings['min_username_length'] ?? '3') ?>">
            </div>
            <div class="form-row">
              <label><?= t('admin_min_passwortlaenge') ?></label>
              <input type="number" name="min_password_length" min="4" max="100"
                     value="<?= e($settings['min_password_length'] ?? '6') ?>">
            </div>
          </div>
        </div>

        <button type="submit" class="btn btn-primary"><?= t('admin_registrierungs_einstellungen_speichern') ?></button>
      </form>

    <?php /* ════════════ NEU v0.18.49: 🛠️ WARTUNGSMODUS ════════════ */ ?>
    <?php elseif ($action === 'maintenance_mode'): ?>
      <h2><?= t('admin_wartungsmodus') ?></h2>

      <form method="post" action="?action=save_settings">
        <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
        <input type="hidden" name="settings_section" value="maintenance_mode">

        <div class="form-card" style="margin-bottom:16px">
          <p style="color:var(--text-muted);font-size:14px;line-height:1.6;margin-bottom:14px">
            <?= t('admin_wenn_der_wartungsmodus_aktiv') ?>
          </p>

          <div class="form-row">
            <label style="font-size:15px">
              <input type="checkbox" name="maintenance_mode" value="1"
                <?= ($settings['maintenance_mode'] ?? '0') === '1' ? 'checked' : '' ?>
                style="transform:scale(1.3);margin-right:8px">
              <strong><?= t('admin_wartungsmodus_aktivieren') ?></strong>
            </label>
            <div class="form-hint">
              ⚠️ <strong><?= t('admin_wichtig') ?></strong> <?= t('admin_bevor_du_den_wartungsmodus') ?>
              <code>UPDATE settings SET setting_value='0' WHERE setting_key='maintenance_mode';</code>
            </div>
          </div>

          <div class="form-row">
            <label><?= t('admin_wartungs_nachricht') ?></label>
            <textarea name="maintenance_message" style="min-height:80px"
                      placeholder="<?= e(t('adm_wartung_platzhalter')) ?>"><?= e($settings['maintenance_message'] ?? '') ?></textarea>
            <div class="form-hint"><?= t('admin_wird_normalen_besuchern_angezeigt') ?></div>
          </div>
        </div>

        <button type="submit" class="btn btn-primary"><?= t('admin_wartungs_einstellungen_speichern') ?></button>
      </form>

      <?php if (($settings['maintenance_mode'] ?? '0') === '1'): ?>
        <div class="form-card" style="margin-top:16px;border-left:3px solid var(--color-danger);background:rgba(220,38,38,0.05)">
          <h3 style="margin-top:0;color:var(--color-danger)"><?= t('admin_wartungsmodus_ist_aktiv') ?></h3>
          <p style="line-height:1.6">
            <?= t('admin_normale_besucher_sehen_aktuell') ?>
            <strong><?= t('admin_logge_dich_nicht_aus') ?></strong>
          </p>
        </div>
      <?php endif; ?>

    <?php elseif ($action === 'iplookup'): ?>
      <h2><?= t('admin_ip_verwaltung') ?></h2>
      <p style="font-size:13px;color:var(--text-muted);margin-bottom:14px">
        <?= t('admin_suche_nach_benutzern_und') ?>
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
            <button type="submit" class="btn btn-primary"><?= t('admin_suchen') ?></button>
          </div>
        </form>

        <?php if (!empty($lookupIP)): ?>
          <div class="section-heading">
            👤 <?= t('adm_benutzer_mit_ip') ?> (<?= count($lookupUsers) ?>)
            <a href="?action=iplookup&sub=banns&prefill=<?= e(urlencode($lookupIP)) ?>" class="btn btn-danger btn-sm">🚫 IP sperren</a>
          </div>
          <div class="card" style="margin-bottom:16px">
            <?php if (empty($lookupUsers)): ?>
              <div style="padding:16px;text-align:center;color:var(--text-faint)"><?= t('admin_keine_benutzer_gefunden') ?></div>
            <?php else: ?>
            <table class="forum-table">
              <thead><tr><th><?= t('admin_benutzer') ?></th><th><?= t('admin_e_mail') ?></th><th><?= t('admin_registriert') ?></th><th><?= t('admin_status') ?></th></tr></thead>
              <tbody>
                <?php foreach ($lookupUsers as $u): ?>
                <tr>
                  <td><a href="<?= e(FORUM_URL) ?>/profile.php?id=<?= (int) $u['user_id'] ?>"><?= e($u['username']) ?></a></td>
                  <td style="font-size:12px"><?= e($u['email']) ?></td>
                  <td style="font-size:12px"><?= e(format_date($u['registered_at'])) ?></td>
                  <td><?= $u['is_banned'] ? '<span style="color:var(--color-danger)">' . e(t('admin_x_gesperrt')) . '</span>' : e(t('admin_x_aktiv')) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            <?php endif; ?>
          </div>

          <div class="section-heading">📝 <?= t('adm_beitraege_von_ip') ?> (<?= count($lookupPosts) ?>)</div>
          <div class="card">
            <?php if (empty($lookupPosts)): ?>
              <div style="padding:16px;text-align:center;color:var(--text-faint)"><?= t('admin_keine_beitraege_gefunden') ?></div>
            <?php else: ?>
            <table class="forum-table">
              <thead><tr><th><?= t('admin_thread') ?></th><th><?= t('admin_autor') ?></th><th><?= t('admin_datum') ?></th></tr></thead>
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
          <h3 style="margin-top:0"><?= t('admin_ip_sperren') ?></h3>
          <form method="post" action="?action=ban_ip">
            <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
            <div class="form-row">
              <label><?= t('admin_ip_adresse') ?></label>
              <input type="text" name="ip" value="<?= e($_GET['prefill'] ?? '') ?>" placeholder="z.B. 192.168.1.1" required>
            </div>
            <div class="form-row">
              <label><?= t('admin_grund_optional') ?></label>
              <input type="text" name="reason" placeholder="z.B. Spam, Trolling…">
            </div>
            <div class="form-row">
              <label><?= t('admin_laeuft_ab_am_optional') ?></label>
              <input type="datetime-local" name="expires">
              <div class="form-hint"><?= t('admin_leer_lassen_fuer_permanenten') ?></div>
            </div>
            <button type="submit" class="btn btn-danger"><?= t('admin_sperren') ?></button>
          </form>
        </div>

        <div class="section-heading"><?= t('adm_aktive_ip_banns') ?> (<?= count($ipBans) ?>)</div>
        <div class="card">
          <?php if (empty($ipBans)): ?>
            <div style="padding:20px;text-align:center;color:var(--text-faint)"><?= t('admin_keine_ip_banns_vorhanden') ?></div>
          <?php else: ?>
          <table class="forum-table">
            <thead><tr><th><?= t('admin_ip') ?></th><th><?= t('admin_grund') ?></th><th><?= t('admin_gesperrt_von') ?></th><th><?= t('admin_seit') ?></th><th><?= t('admin_laeuft_ab') ?></th><th><?= t('admin_aktion') ?></th></tr></thead>
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
                     class="btn btn-ghost btn-xs" onclick="return confirm('Bann aufheben?')"><?= t('admin_x_aufheben') ?></a>
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
      <h2><?= t('admin_ip_banns') ?></h2>
      <div class="form-card" style="margin-bottom:16px">
        <h2><?= t('admin_ip_sperren') ?></h2>
        <form method="post" action="?action=ban_ip">
          <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
          <div class="form-row">
            <label><?= t('admin_ip_adresse') ?></label>
            <input type="text" name="ip" value="<?= e($_GET['prefill'] ?? '') ?>" placeholder="z.B. 192.168.1.1" required>
          </div>
          <div class="form-row">
            <label><?= t('admin_grund_optional') ?></label>
            <input type="text" name="reason" placeholder="z.B. Spam, Trolling…">
          </div>
          <div class="form-row">
            <label><?= t('admin_laeuft_ab_am_optional') ?></label>
            <input type="datetime-local" name="expires">
            <div class="form-hint"><?= t('admin_leer_lassen_fuer_permanenten') ?></div>
          </div>
          <button type="submit" class="btn btn-danger"><?= t('admin_sperren') ?></button>
        </form>
      </div>

      <div class="section-heading"><?= t('adm_aktive_ip_banns') ?> (<?= count($ipBans) ?>)</div>
      <div class="card">
        <?php if (empty($ipBans)): ?>
          <div style="padding:20px;text-align:center;color:var(--text-faint)"><?= t('admin_keine_ip_banns_vorhanden') ?></div>
        <?php else: ?>
        <table class="forum-table">
          <thead><tr><th><?= t('admin_ip') ?></th><th><?= t('admin_grund') ?></th><th><?= t('admin_gesperrt_von') ?></th><th><?= t('admin_seit') ?></th><th><?= t('admin_laeuft_ab') ?></th><th><?= t('admin_aktion') ?></th></tr></thead>
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
                   class="btn btn-ghost btn-xs" onclick="return confirm('Bann aufheben?')"><?= t('admin_x_aufheben') ?></a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>

    <?php /* ════════════ STATISTIK ════════════ */ ?>
    <?php elseif ($action === 'statistics'): ?>
      <h2><?= t('admin_statistik') ?></h2>

      <div class="stat-grid" style="margin-bottom:18px">
        <div class="stat-box">
          <div class="stat-icon">⌀</div>
          <div class="stat-num"><?= number_format($avgPostsPerUser, 1, ',', '.') ?></div>
          <div class="stat-label"><?= t('admin_beitraege_pro_user') ?></div>
        </div>
        <div class="stat-box">
          <div class="stat-icon">🟢</div>
          <div class="stat-num"><?= (int) $activeLastWeek ?></div>
          <div class="stat-label"><?= t('admin_aktiv_letzte_7_tage') ?></div>
        </div>
      </div>

      <div class="section-heading"><?= t('admin_registrierungen_letzte_30_tage') ?></div>
      <div class="card" style="margin-bottom:16px">
        <?php if (empty($userRegs30d)): ?>
          <div style="padding:16px;text-align:center;color:var(--text-faint)"><?= t('admin_keine_daten') ?></div>
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

      <div class="section-heading"><?= t('admin_beitraege_letzte_30_tage') ?></div>
      <div class="card" style="margin-bottom:16px">
        <?php if (empty($posts30d)): ?>
          <div style="padding:16px;text-align:center;color:var(--text-faint)"><?= t('admin_keine_daten') ?></div>
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

      <div class="section-heading"><?= t('admin_top_10_poster') ?></div>
      <div class="card" style="margin-bottom:16px">
        <table class="forum-table">
          <thead><tr><th>#</th><th><?= t('admin_benutzer') ?></th><th><?= t('admin_rang') ?></th><th><?= t('admin_beitraege') ?></th></tr></thead>
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

      <div class="section-heading"><?= t('admin_aktivste_foren') ?></div>
      <div class="card" style="margin-bottom:16px">
        <table class="forum-table">
          <thead><tr><th><?= t('admin_forum') ?></th><th><?= t('admin_threads') ?></th><th><?= t('admin_posts') ?></th></tr></thead>
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

      <div class="section-heading"><?= t('admin_aktivitaets_heatmap_letzte_60') ?></div>
      <div class="card" style="padding:16px;margin-bottom:16px">
        <p style="font-size:11px;color:var(--text-muted);margin-bottom:10px">
          <?= t('admin_wann_wird_im_forum') ?>
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

      <div class="section-heading"><?= t('admin_engagement_stats') ?></div>
      <div class="stat-grid" style="margin-bottom:0">
        <div class="stat-box">
          <div class="stat-icon">👍</div>
          <div class="stat-num"><?= number_format((int) ($totalReactions ?? 0), 0, ',', '.') ?></div>
          <div class="stat-label"><?= t('admin_reaktionen_gesamt') ?></div>
        </div>
        <div class="stat-box">
          <div class="stat-icon">🔖</div>
          <div class="stat-num"><?= number_format((int) ($totalBookmarks ?? 0), 0, ',', '.') ?></div>
          <div class="stat-label"><?= t('admin_lesezeichen') ?></div>
        </div>
      </div>

    <?php /* ════════════ MELDUNGEN ════════════ */ ?>
    <?php elseif ($action === 'reports'): ?>
      <h2><?= t('admin_meldungen') ?></h2>

      <div style="display:flex;gap:6px;margin-bottom:14px;flex-wrap:wrap">
        <a href="?action=reports&status=open"      class="btn btn-sm <?= $reportStatus === 'open' ? 'btn-primary' : 'btn-ghost' ?>">🔴 <?= t('adm_meldung_offen') ?> (<?= (int) $counts['open'] ?>)</a>
        <a href="?action=reports&status=reviewed"  class="btn btn-sm <?= $reportStatus === 'reviewed' ? 'btn-primary' : 'btn-ghost' ?>">👁️ <?= t('adm_meldung_geprueft') ?> (<?= (int) $counts['reviewed'] ?>)</a>
        <a href="?action=reports&status=actioned"  class="btn btn-sm <?= $reportStatus === 'actioned' ? 'btn-primary' : 'btn-ghost' ?>">✅ <?= t('adm_meldung_bearbeitet') ?> (<?= (int) $counts['actioned'] ?>)</a>
        <a href="?action=reports&status=dismissed" class="btn btn-sm <?= $reportStatus === 'dismissed' ? 'btn-primary' : 'btn-ghost' ?>">❌ <?= t('adm_meldung_abgelehnt') ?> (<?= (int) $counts['dismissed'] ?>)</a>
      </div>

      <?php if (empty($reports)): ?>
        <div class="card" style="padding:30px;text-align:center;color:var(--text-faint)">
          <?= t('admin_keine_meldungen_in_dieser') ?>
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
              📨 <?= t('adm_gemeldet_von') ?> <a href="<?= e(FORUM_URL) ?>/profile.php?id=<?= (int) $r['reporter_id'] ?>"><?= e($r['reporter_name']) ?></a>
            </div>
            <?php if (!empty($r['comment'])): ?>
              <div style="padding:8px 12px;background:var(--bg-surface2);border-left:3px solid var(--border-strong);border-radius:0 6px 6px 0;margin-bottom:10px;font-size:12px;font-style:italic">
                💬 <?= e($r['comment']) ?>
              </div>
            <?php endif; ?>

            <div style="border:1px solid var(--border);border-radius:6px;padding:10px 12px;background:var(--bg-surface2);margin-bottom:10px">
              <div style="font-size:11px;color:var(--text-muted);margin-bottom:4px">
                <?= t('adm_beitrag_von') ?> <a href="<?= e(FORUM_URL) ?>/profile.php?id=<?= (int) $r['author_id'] ?>"><?= e($r['author_name']) ?></a>
                in „<a href="<?= e(FORUM_URL) ?>/thread.php?id=<?= (int) $r['thread_id'] ?>#post<?= (int) $r['post_id'] ?>"><?= e($r['thread_title']) ?></a>"
              </div>
              <div style="font-size:12px;color:var(--text-base)">
                <?= e(mb_substr(strip_tags($r['post_content']), 0, 300)) ?><?= mb_strlen($r['post_content']) > 300 ? '…' : '' ?>
              </div>
            </div>

            <?php if ($r['status'] === 'open'): ?>
              <div style="display:flex;gap:6px;flex-wrap:wrap">
                <a href="?action=resolve_report&id=<?= (int) $r['report_id'] ?>&status=reviewed&csrf=<?= e($csrfToken) ?>"
                   class="btn btn-ghost btn-sm">👁️ <?= t('adm_meldung_geprueft') ?></a>
                <a href="?action=resolve_report&id=<?= (int) $r['report_id'] ?>&status=dismissed&csrf=<?= e($csrfToken) ?>"
                   class="btn btn-ghost btn-sm">❌ Abweisen</a>
                <a href="?action=warnings&user=<?= (int) $r['author_id'] ?>" class="btn btn-danger btn-sm">⚠️ Warnen</a>
                <a href="<?= e(FORUM_URL) ?>/mod_action.php?action=delete_post&post=<?= (int) $r['post_id'] ?>&csrf=<?= e($csrfToken) ?>"
                   class="btn btn-danger btn-sm"
                   onclick="return confirm(<?= e(json_encode(t('adm_beitrag_loeschen_frage'), JSON_UNESCAPED_UNICODE)) ?>)">🗑️ <?= t('adm_beitrag_loeschen') ?></a>
                <a href="?action=resolve_report&id=<?= (int) $r['report_id'] ?>&status=actioned&csrf=<?= e($csrfToken) ?>"
                   class="btn btn-primary btn-sm">✅ Erledigt</a>
              </div>
            <?php else: ?>
              <div style="font-size:11px;color:var(--text-muted)">
                <?= t('adm_bearbeitet_von_am', e($r['reviewer_name'] ?? '–'), e(format_date($r['reviewed_at']))) ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>

    <?php /* ════════════ WARNUNGEN ════════════ */ ?>
    <?php elseif ($action === 'warnings'): ?>
      <h2><?= t('admin_warnungen') ?></h2>

      <p style="font-size:13px;color:var(--text-muted);margin-bottom:14px">
        <?= t('admin_auto_bann_ab') ?> <strong><?= (int) $threshold ?></strong> <?= t('admin_punkten_aenderbar_in_den') ?> <a href="?action=settings"><?= t('admin_einstellungen') ?></a>).
      </p>

      <?php if ($warnUser): ?>
        <div class="card" style="margin-bottom:16px">
          <div class="card-header">
            <span class="card-title">
              <?= t('adm_warnungen_fuer') ?>
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
                  <label><?= t('admin_punkte') ?></label>
                  <input type="number" name="points" min="1" max="20" value="1" required>
                </div>
                <div class="form-row" style="margin:0">
                  <label><?= t('admin_grund') ?></label>
                  <input type="text" name="reason" required placeholder="z.B. Spam, Beleidigung, Trolling…">
                </div>
              </div>
              <div class="form-row">
                <label><?= t('admin_laeuft_ab_optional') ?></label>
                <input type="datetime-local" name="expires">
                <div class="form-hint"><?= t('admin_leer_warnung_gilt_unbegrenzt') ?></div>
              </div>
              <button type="submit" class="btn btn-danger"><?= t('admin_warnung_aussprechen') ?></button>
            </form>
          </div>
        </div>

        <?php if (!empty($warningsList)): ?>
          <div class="section-heading"><?= t('admin_bisherige_warnungen') ?></div>
          <div class="card">
            <table class="forum-table">
              <thead>
                <tr><th><?= t('admin_punkte') ?></th><th><?= t('admin_grund') ?></th><th><?= t('admin_von') ?></th><th><?= t('admin_datum') ?></th><th><?= t('admin_status') ?></th><th></th></tr>
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
                           class="btn btn-ghost btn-xs"><?= t('admin_x_aufheben') ?></a>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>

      <?php else: ?>
        <div class="section-heading"><?= t('admin_benutzer_mit_warnungen') ?></div>
        <div class="card">
          <?php if (empty($recentWarned)): ?>
            <div style="padding:30px;text-align:center;color:var(--text-faint)">
              <?= t('admin_keine_user_mit_aktiven') ?>
            </div>
          <?php else: ?>
            <table class="forum-table">
              <thead><tr><th><?= t('admin_benutzer') ?></th><th><?= t('admin_punkte') ?></th><th><?= t('admin_letzte_warnung') ?></th><th><?= t('admin_status') ?></th><th></th></tr></thead>
              <tbody>
                <?php foreach ($recentWarned as $u): ?>
                  <tr>
                    <td><a href="<?= e(FORUM_URL) ?>/profile.php?id=<?= (int) $u['user_id'] ?>"><?= e($u['username']) ?></a></td>
                    <td><strong style="color:<?= (int) $u['warning_points'] >= $threshold ? 'var(--color-danger)' : 'var(--color-warning)' ?>"><?= (int) $u['warning_points'] ?></strong></td>
                    <td style="font-size:12px"><?= e(format_date($u['last_warning'])) ?></td>
                    <td><?= $u['is_banned'] ? '<span style="color:var(--color-danger)">🚫 Gesperrt</span>' : 'Aktiv' ?></td>
                    <td><a href="?action=warnings&user=<?= (int) $u['user_id'] ?>" class="btn btn-ghost btn-xs"><?= t('admin_x_details') ?></a></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>

        <p style="margin-top:14px;font-size:13px;color:var(--text-muted)">
          <?= t('admin_um_einen_anderen_user') ?>
        </p>
      <?php endif; ?>

    <?php /* ════════════ TAGS ════════════ */ ?>
    <?php elseif ($action === 'tags'): ?>
      <h2><?= t('admin_tag_verwaltung') ?></h2>

      <div class="card" style="margin-bottom:14px">
        <?php if (empty($tagsList)): ?>
          <div style="padding:20px;text-align:center;color:var(--text-faint)"><?= t('admin_keine_tags_vorhanden') ?></div>
        <?php else: ?>
          <table class="forum-table">
            <thead><tr><th><?= t('admin_tag') ?></th><th><?= t('admin_slug') ?></th><th><?= t('admin_beschreibung') ?></th><th><?= t('admin_verwendung') ?></th><th></th></tr></thead>
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
                       onclick="return confirm(<?= e(json_encode(t('adm_tag_loeschen_frage'), JSON_UNESCAPED_UNICODE)) ?>)"><?= t('admin_x_loeschen') ?></a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>

      <div class="form-card">
        <h2><?= t('admin_neuer_tag') ?></h2>
        <form method="post" action="?action=add_tag">
          <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div class="form-row" style="margin:0">
              <label><?= t('admin_slug_url') ?></label>
              <input type="text" name="slug" required pattern="[a-z0-9]+" placeholder="z.B. tutorial">
              <div class="form-hint"><?= t('admin_nur_kleinbuchstaben_ziffern') ?></div>
            </div>
            <div class="form-row" style="margin:0">
              <label><?= t('admin_name') ?></label>
              <input type="text" name="name" required placeholder="z.B. Tutorial">
            </div>
          </div>
          <div class="form-row">
            <label><?= t('admin_beschreibung_emoji') ?></label>
            <input type="text" name="description" placeholder="z.B. 📖">
          </div>
          <div class="form-row">
            <label><?= t('admin_farbe') ?></label>
            <input type="color" name="color" value="#888888">
          </div>
          <button type="submit" class="btn btn-primary"><?= t('admin_tag_anlegen') ?></button>
        </form>
      </div>

    <?php /* ════════════ MOD-LOG ════════════ */ ?>
    <?php elseif ($action === 'modlog'): ?>
      <h2><?= t('admin_moderations_log') ?></h2>
      <div class="card">
        <table class="forum-table">
          <thead><tr><th><?= t('admin_datum') ?></th><th><?= t('admin_moderator') ?></th><th><?= t('admin_aktion') ?></th><th><?= t('admin_typ') ?></th><th><?= t('admin_ziel') ?></th><th><?= t('admin_grund') ?></th></tr></thead>
          <tbody>
            <?php if (empty($modLog)): ?>
              <tr><td colspan="6" style="text-align:center;color:var(--text-faint);padding:20px"><?= t('admin_keine_eintraege') ?></td></tr>
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
      <h2><?= t('admin_einladungen') ?></h2>

      <?php if (!user_approval_required()): ?>
        <div class="card" style="padding:16px;margin-bottom:14px;background:rgba(170,102,68,0.1);border-left:3px solid #aa6644">
          <strong><?= t('admin_hinweis_2') ?></strong>
          <?= t('admin_freigabe_aus_hinweis', '<strong>' . e(t('admin_nicht_aktiv')) . '</strong>') ?>
          <a href="?action=registration"><?= t('admin_registrierungs_einstellungen_oeffnen') ?></a>
        </div>
      <?php endif; ?>

      <div class="card" style="padding:18px 22px;margin-bottom:18px">
        <h3 style="margin:0 0 12px;font-size:15px"><?= t('admin_neue_einladung_erstellen') ?></h3>
        <form method="post" action="?action=invitation_create">
          <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
            <div class="form-row">
              <label><?= t('admin_e_mail_adresse_optional') ?></label>
              <input type="email" name="email" placeholder="freund@example.com">
              <div class="form-hint">
                <?= t('admin_wenn_angegeben_code_ist') ?>
              </div>
            </div>
            <div class="form-row">
              <label><?= t('admin_gueltigkeit_tage') ?></label>
              <input type="number" name="valid_days" value="7" min="1" max="365">
              <div class="form-hint"><?= t('admin_standard_7_tage_max') ?></div>
            </div>
          </div>
          <div class="form-row">
            <label><?= t('admin_interne_notiz_optional') ?></label>
            <input type="text" name="note" maxlength="255" placeholder="z.B. „Schulfreund Markus"">
            <div class="form-hint"><?= t('admin_nur_fuer_dich_wird') ?></div>
          </div>
          <div class="form-row">
            <label style="display:flex;align-items:center;gap:8px">
              <input type="checkbox" name="send_mail" value="1" checked>
              <?= t('admin_einladungs_mail_direkt_versenden') ?>
            </label>
          </div>
          <button type="submit" class="btn btn-primary"><?= t('admin_einladung_erstellen') ?></button>
        </form>
      </div>

      <h3 style="margin:24px 0 10px;font-size:15px"><?= t('adm_alle_einladungen') ?> (<?= count($invitations) ?>)</h3>

      <?php if (empty($invitations)): ?>
        <div class="card" style="padding:30px;text-align:center;color:var(--text-muted)">
          <?= t('admin_noch_keine_einladungen_erstellt') ?>
        </div>
      <?php else: ?>
        <div style="display:grid;gap:10px">
          <?php foreach ($invitations as $inv):
              $status = invitation_status($inv);
              $statusLabel = [
                  'valid'    => ['🟢 Aktiv',      '#4a9d4a'],
                  'used'     => [t('admin_y_eingeloest'),  '#888'],
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
                      📧 <?= t('adm_fuer') ?> <strong><?= e($inv['email']) ?></strong><br>
                    <?php else: ?>
                      🌍 <?= t('adm_universal_code') ?><br>
                    <?php endif; ?>
                    <?php if (!empty($inv['note'])): ?>
                      📝 <?= e($inv['note']) ?><br>
                    <?php endif; ?>
                    <?= t('settings_erstellt_am', e(format_date($inv['created_at']))) ?>
                    <?php if (!empty($inv['creator_username'])): ?>
                      <?= t('index_von') ?> <?= e($inv['creator_username']) ?>
                    <?php endif; ?>
                    <br>
                    <?php if ($status === 'valid'): ?>
                      ⏰ <?= t('adm_gueltig_bis') ?> <strong><?= e(format_date($inv['expires_at'])) ?></strong>
                    <?php elseif ($status === 'used'): ?>
                      ✓ <?= t('adm_eingeloest_von') ?> <strong><?= e($inv['used_by_username'] ?? '?') ?></strong>
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
                        <button type="submit" class="btn btn-ghost btn-sm" title="Einladungs-Mail erneut versenden"><?= t('admin_resend') ?></button>
                      </form>
                    <?php endif; ?>
                    <form method="post" action="?action=invitation_revoke" style="margin:0"
                          onsubmit="return confirm(<?= e(json_encode(t('adm_einladung_widerrufen_frage'), JSON_UNESCAPED_UNICODE)) ?>)">
                      <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
                      <input type="hidden" name="invitation_id" value="<?= (int) $inv['invitation_id'] ?>">
                      <button type="submit" class="btn btn-danger btn-sm"><?= t('admin_widerrufen') ?></button>
                    </form>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

    <?php elseif ($action === 'approvals_users'): ?>
      <h2><?= t('admin_wartende_registrierungs_freigaben') ?></h2>

      <?php if (empty($pendingUsers)): ?>
        <div class="card" style="padding:40px;text-align:center">
          <div style="font-size:48px;margin-bottom:12px">✅</div>
          <strong style="font-size:16px"><?= t('admin_keine_wartenden_registrierungen') ?></strong>
          <p style="font-size:13px;color:var(--text-muted);margin-top:8px">
            <?= t('admin_sobald_sich_jemand_registriert') ?>
          </p>
          <p style="font-size:12px;color:var(--text-faint);margin-top:14px">
            <?= t('adm_status_freigabe') ?>
            <?php if (user_approval_required()): ?>
              <strong style="color:#4a9d4a"><?= t('adm_aktiv') ?></strong>.
            <?php else: ?>
              <strong style="color:#aa6644"><?= t('adm_inaktiv') ?></strong>.
              <a href="?action=registration"><?= t('admin_aktivieren') ?></a>
            <?php endif; ?>
          </p>
        </div>
      <?php else: ?>
        <div style="margin-bottom:14px;font-size:13px;color:var(--text-muted)">
          <?= tn('adm_konten_warten', count($pendingUsers)) ?>
          <?= t('adm_ablehnen_wirkung') ?>
        </div>

        <?php foreach ($pendingUsers as $u): ?>
          <div class="card" style="margin-bottom:12px;padding:16px 20px">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;margin-bottom:12px;flex-wrap:wrap">
              <div style="flex:1;min-width:200px">
                <h3 style="font-size:16px;margin-bottom:6px"><?= e($u['username']) ?></h3>
                <div style="font-size:12px;color:var(--text-muted);line-height:1.7">
                  📧 <?= e($u['email']) ?>
                  <?php if (!empty($u['email_verified'])): ?>
                    <span style="color:#4a9d4a"><?= t('admin_verifiziert') ?></span>
                  <?php else: ?>
                    <span style="color:#aa6644"><?= t('admin_unverifiziert') ?></span>
                  <?php endif; ?>
                  <br>
                  <?= t('admin_ip') ?> <code style="font-size:11px"><?= e($u['registration_ip'] ?? '?') ?></code>
                  <br>
                  🕒 Registriert: <?= e(format_date($u['registered_at'])) ?>
                </div>
              </div>

              <form method="post" action="?action=approve_user_action" style="margin:0"
                    onsubmit="return confirm(<?= e(json_encode(t('adm_konto_freigeben_frage'), JSON_UNESCAPED_UNICODE)) ?>)">
                <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="user_id" value="<?= (int) $u['user_id'] ?>">
                <button type="submit" class="btn btn-primary btn-sm"><?= t('admin_freigeben') ?></button>
              </form>
            </div>

            <details style="margin-top:8px">
              <summary style="cursor:pointer;font-size:12px;color:var(--text-muted)">
                <?= t('admin_account_ablehnen_mit_begruendung') ?>
              </summary>
              <form method="post" action="?action=reject_user_action" style="margin-top:12px"
                    onsubmit="return confirm(<?= e(json_encode(t('adm_konto_ablehnen_frage'), JSON_UNESCAPED_UNICODE)) ?>)">
                <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="user_id" value="<?= (int) $u['user_id'] ?>">
                <div class="form-row">
                  <label style="font-size:12px"><?= t('admin_begruendung_wird_dem_user') ?> <span style="color:#c44">*</span></label>
                  <textarea name="reason" rows="3" required maxlength="500"
                            placeholder="<?= e(t('adm_ablehnung_platzhalter')) ?>"></textarea>
                </div>
                <button type="submit" class="btn btn-danger btn-sm"><?= t('admin_ablehnen_loeschen') ?></button>
              </form>
            </details>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>

    <?php elseif ($action === 'approval'): ?>
      <h2><?= t('admin_freigabe_warteschlange') ?></h2>

      <?php if (empty($pendingThreads) && empty($pendingPosts)): ?>
        <div class="card" style="padding:40px;text-align:center">
          <div style="font-size:48px;margin-bottom:12px">✅</div>
          <strong style="font-size:16px"><?= t('admin_alles_freigegeben') ?></strong>
          <p style="font-size:13px;color:var(--text-muted);margin-top:8px">
            <?= t('admin_keine_wartenden_beitraege') ?>
          </p>
        </div>
      <?php endif; ?>

      <?php /* ── Wartende Themen ── */ ?>
      <?php if (!empty($pendingThreads)): ?>
        <div class="section-heading"><?= t('mod_neue_themen') ?> (<?= count($pendingThreads) ?>)</div>
        <?php foreach ($pendingThreads as $t): ?>
          <div class="card" style="margin-bottom:12px;padding:16px 20px">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;margin-bottom:10px">
              <div>
                <div style="font-size:11px;color:var(--text-muted);margin-bottom:4px">
                  <?= t('admin_in') ?> <strong><?= e($t['board_title']) ?></strong> · <?= t('adm_vor_zeit', e(format_date($t['created_at']))) ?>
                </div>
                <h3 style="font-size:16px;margin-bottom:6px"><?= e($t['title']) ?></h3>
                <div style="font-size:12px;color:var(--text-muted)">
                  <?= t('admin_von') ?> <a href="<?= e(FORUM_URL) ?>/profile.php?id=<?= (int) $t['user_id'] ?>"><strong><?= e($t['username']) ?></strong></a>
                </div>
              </div>
              <div style="display:flex;gap:6px;flex-shrink:0">
                <a href="?action=approve_thread&id=<?= (int) $t['thread_id'] ?>&csrf=<?= e($csrfToken) ?>"
                   class="btn btn-primary btn-sm"
                   onclick="return confirm(<?= e(json_encode(t('mod_thema_freigeben_frage'), JSON_UNESCAPED_UNICODE)) ?>)">✅ <?= t('adm_freigeben') ?></a>
                <a href="?action=reject_thread&id=<?= (int) $t['thread_id'] ?>&csrf=<?= e($csrfToken) ?>&reason=<?= urlencode(t('mod_verstoss_gegen_forenregeln')) ?>"
                   class="btn btn-danger btn-sm"
                   onclick="return confirm(<?= e(json_encode(t('mod_thema_ablehnen_frage'), JSON_UNESCAPED_UNICODE)) ?>)">🗑️ <?= t('adm_ablehnen') ?></a>
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
        <div class="section-heading" style="margin-top:24px"><?= t('adm_neue_antworten') ?> (<?= count($pendingPosts) ?>)</div>
        <?php foreach ($pendingPosts as $p): ?>
          <div class="card" style="margin-bottom:12px;padding:16px 20px">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;margin-bottom:10px">
              <div>
                <div style="font-size:11px;color:var(--text-muted);margin-bottom:4px">
                  <?= t('admin_in') ?> <strong><?= e($p['board_title']) ?></strong> · Thread:
                  <a href="<?= e(FORUM_URL) ?>/thread.php?id=<?= (int) $p['thread_id'] ?>"><?= e($p['thread_title']) ?></a>
                  · <?= t('adm_vor_zeit', e(format_date($p['created_at']))) ?>
                </div>
                <div style="font-size:12px;color:var(--text-muted)">
                  <?= t('admin_von') ?> <a href="<?= e(FORUM_URL) ?>/profile.php?id=<?= (int) $p['user_id'] ?>"><strong><?= e($p['username']) ?></strong></a>
                </div>
              </div>
              <div style="display:flex;gap:6px;flex-shrink:0">
                <a href="?action=approve_post&id=<?= (int) $p['post_id'] ?>&csrf=<?= e($csrfToken) ?>"
                   class="btn btn-primary btn-sm"
                   onclick="return confirm(<?= e(json_encode(t('mod_beitrag_freigeben_frage'), JSON_UNESCAPED_UNICODE)) ?>)">✅ <?= t('adm_freigeben') ?></a>
                <a href="?action=reject_post&id=<?= (int) $p['post_id'] ?>&csrf=<?= e($csrfToken) ?>&reason=<?= urlencode(t('mod_verstoss_gegen_forenregeln')) ?>"
                   class="btn btn-danger btn-sm"
                   onclick="return confirm(<?= e(json_encode(t('mod_beitrag_ablehnen_frage'), JSON_UNESCAPED_UNICODE)) ?>)">🗑️ <?= t('adm_ablehnen') ?></a>
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
      <h2><?= t('admin_design') ?></h2>
      <p style="font-size:13px;color:var(--text-muted);margin-bottom:14px">
        <?= t('admin_design_hinweis', '<strong>' . e(t('admin_speichern')) . '</strong>') ?>
      </p>

      <?php if ($hasPreview ?? false): ?>
        <div class="alert alert-warning" style="margin-bottom:14px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
          <span><?= t('admin_du_hast_eine_ungespeicherte') ?></span>
          <a href="?action=clear_preview&csrf=<?= e($csrfToken) ?>" class="btn btn-ghost btn-sm"><?= t('admin_x_vorschau_verwerfen') ?></a>
        </div>
      <?php endif; ?>

      <div class="design-editor">
        <?php /* ─── Controls (linke Seite) ─── */ ?>
        <form method="post" action="?action=save_design" id="design-form" class="design-controls">
          <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">

          <div class="design-tabs">
            <button type="button" class="design-tab-btn active" data-tab="identity"><?= t('admin_identitaet') ?></button>
            <button type="button" class="design-tab-btn" data-tab="colors"><?= t('admin_farben') ?></button>
            <button type="button" class="design-tab-btn" data-tab="fonts"><?= t('admin_schrift_2') ?></button>
            <button type="button" class="design-tab-btn" data-tab="form"><?= t('admin_form') ?></button>
            <button type="button" class="design-tab-btn" data-tab="layout"><?= t('admin_layout') ?></button>
            <button type="button" class="design-tab-btn" data-tab="presets"><?= t('admin_presets') ?></button>
            <button type="button" class="design-tab-btn" data-tab="advanced"><?= t('admin_erweitert') ?></button>
          </div>

          <?php /* ═══ TAB: IDENTITÄT (neu) ═══════════════════════ */ ?>
          <div class="design-tab-pane active" data-pane="identity">
            <h3><?= t('admin_forum_name_slogan') ?></h3>
            <div class="form-row">
              <label><?= t('admin_forum_name') ?></label>
              <input type="text" name="forum_name" value="<?= e($branding['forum_name']) ?>"
                     placeholder="<?= e(defined('FORUM_NAME') ? FORUM_NAME : 'Forum') ?>" maxlength="100"
                     data-live="forum_name">
              <div class="form-hint"><?= t('admin_erscheint_im_browser_titel') ?></div>
            </div>

            <div class="form-row">
              <label><?= t('admin_slogan_tagline') ?></label>
              <input type="text" name="forum_slogan" value="<?= e($branding['forum_slogan']) ?>"
                     placeholder="<?= e(t('adm_slogan_platzhalter')) ?>" maxlength="150"
                     data-live="forum_slogan">
              <div class="form-hint"><?= t('admin_erscheint_rechts_neben_dem') ?></div>
            </div>

            <h3 style="margin-top:18px"><?= t('admin_logo') ?></h3>
            <div style="padding:14px;background:var(--bg-surface2);border-radius:var(--radius-sm);text-align:center;margin-bottom:10px">
              <div style="font-size:11px;color:var(--text-muted);margin-bottom:8px;text-transform:uppercase;letter-spacing:.5px">
                Aktuelle Vorschau
                <?php if (function_exists('is_default_logo') && is_default_logo()): ?>
                  <span style="color:var(--color-warning)"><?= t('admin_standard_logo') ?></span>
                <?php endif; ?>
              </div>
              <?php // Dasselbe wie im Kopf der Seite, sonst zeigt die Vorschau etwas anderes als die Besucher sehen ?>
              <div style="max-height:100px;color:var(--text-base)"
                   class="logo-preview"><?= forum_logo_render('Logo-Vorschau') ?></div>
            </div>
            <style>.logo-preview svg, .logo-preview img { max-height:100px; max-width:100%; height:auto }</style>
            <div class="form-row">
              <label><?= t('admin_logo_url_optional') ?></label>
              <input type="text" name="forum_logo" value="<?= e($branding['forum_logo']) ?>"
                     placeholder="<?= e(t('adm_logo_platzhalter')) ?>">
              <div class="form-hint">
                <?= t('admin_logo_leer_hinweis', '<code>assets/logo.png</code>', '<code>assets/logo.svg</code>') ?>
              </div>
            </div>

            <h3 style="margin-top:18px"><?= t('admin_akzentfarbe') ?></h3>
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
                  ['16a34a', t('admin_gruen')],
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
              <div class="form-hint"><?= t('admin_bestimmt_die_highlight_farbe') ?></div>
            </div>

            <h3 style="margin-top:18px"><?= t('admin_standard_theme') ?></h3>
            <div class="form-row">
              <select name="forum_default_theme" data-live="forum_default_theme">
                <option value="dark"  <?= $branding['forum_default_theme'] === 'dark'  ? 'selected' : '' ?>><?= t('admin_dark_mode') ?></option>
                <option value="light" <?= $branding['forum_default_theme'] === 'light' ? 'selected' : '' ?>><?= t('admin_light_mode') ?></option>
              </select>
              <div class="form-hint">
                <?= t('admin_wird_verwendet_wenn_ein') ?>
              </div>
            </div>
          </div>

          <?php /* ─── TAB: FARBEN ─── */ ?>
          <div class="design-tab-pane" data-pane="colors">
            <h3><?= t('admin_dark_mode') ?></h3>
            <?php
              $darkColors = [
                'theme_color_bg_body'       => ['Body-Hintergrund',   t('admin_hauptfarbe_der_seite')],
                'theme_color_bg_surface'    => ['Cards',              t('admin_karten_formularbloecke')],
                'theme_color_bg_surface2'   => [t('admin_tiefere_cards'),      t('admin_tabellen_header_code_bloecke')],
                'theme_color_text_base'     => ['Schrift',            'Haupttext'],
                'theme_color_text_muted'    => [t('admin_schrift_dezent'),     t('admin_sekundaerer_text')],
                'theme_color_text_faint'    => [t('admin_schrift_verblasst'),  'Metadaten, Zeitangaben'],
                'theme_color_border'        => ['Border',             'Linien, Trennungen'],
                'theme_color_border_strong' => [t('admin_border_kraeftig'),     'Hover-Borders'],
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

            <h3 style="margin-top:18px"><?= t('admin_light_mode') ?></h3>
            <?php
              $lightColors = [
                'theme_color_bg_body_light'       => ['Body-Hintergrund',   ''],
                'theme_color_bg_surface_light'    => ['Cards',              ''],
                'theme_color_bg_surface2_light'   => [t('admin_tiefere_cards'),      ''],
                'theme_color_text_base_light'     => ['Schrift',            ''],
                'theme_color_text_muted_light'    => [t('admin_schrift_dezent'),     ''],
                'theme_color_text_faint_light'    => [t('admin_schrift_verblasst'),  ''],
                'theme_color_border_light'        => ['Border',             ''],
                'theme_color_border_strong_light' => [t('admin_border_kraeftig'),     ''],
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

            <h3 style="margin-top:18px"><?= t('admin_status_farben') ?></h3>
            <?php
              $statusColors = [
                'theme_color_success' => [t('adm_farbe_erfolg'),  t('admin_bestaetigungen')],
                'theme_color_warning' => [t('adm_farbe_warnung'), t('adm_hinweise')],
                'theme_color_danger'  => [t('adm_farbe_fehler'),  t('admin_loesch_buttons_fehler_boxen')],
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
            <h3><?= t('admin_schriftart') ?></h3>
            <div class="form-row">
              <select name="theme_font_family" id="theme_font_family" data-live="theme_font_family">
                <?php
                  $fontOpts = [
                    'inter'     => '✨ Inter (Standard) – modern, gut lesbar',
                    'system'    => '🖥️ System-Schrift (jeweilige OS-Schrift)',
                    'roboto'    => '🤖 Roboto – Google-Schrift, klar',
                    'open-sans' => t('admin_y_open_sans_freundlich'),
                    'lato'      => '🎯 Lato – semi-rund',
                    'nunito'    => '🍩 Nunito – verspielt, runde Formen',
                    'poppins'   => '⭕ Poppins – geometrisch, modern',
                    'chakra-petch' => t('admin_y_chakra_petch_technisch_kantig'),
                    'custom'    => '🛠️ Custom – eigene Schriftart',
                  ];
                  foreach ($fontOpts as $k => $lab):
                ?>
                  <option value="<?= e($k) ?>" <?= ($theme['theme_font_family'] ?? '') === $k ? 'selected' : '' ?>><?= e($lab) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-row" id="custom-font-row" style="<?= ($theme['theme_font_family'] ?? '') === 'custom' ? '' : 'display:none' ?>">
              <label><?= t('admin_custom_font_stack') ?></label>
              <input type="text" name="theme_font_custom" value="<?= e($theme['theme_font_custom'] ?? '') ?>"
                     placeholder="z.B. 'Comic Sans MS', cursive" data-live="theme_font_custom">
              <div class="form-hint"><?= t('admin_css_font_stack_komma') ?></div>
            </div>

            <h3 style="margin-top:18px"><?= t('admin_schriftgroesse_basis') ?></h3>
            <div class="form-row">
              <select name="theme_font_size_base" data-live="theme_font_size_base">
                <?php foreach ([12, 13, 14, 15, 16] as $sz): ?>
                  <option value="<?= $sz ?>" <?= (int) ($theme['theme_font_size_base'] ?? 14) === $sz ? 'selected' : '' ?>>
                    <?= $sz ?>px <?= $sz === 14 ? '(Standard)' : '' ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <div class="form-hint"><?= t('admin_beeinflusst_die_groesse_aller') ?></div>
            </div>
          </div>

          <?php /* ─── TAB: FORM ─── */ ?>
          <div class="design-tab-pane" data-pane="form">
            <h3><?= t('admin_rundungen') ?></h3>
            <div class="form-row">
              <select name="theme_radius_preset" data-live="theme_radius_preset">
                <option value="sharp"  <?= ($theme['theme_radius_preset'] ?? '') === 'sharp'  ? 'selected' : '' ?>>⬜ Eckig – ganz scharf</option>
                <option value="small"  <?= ($theme['theme_radius_preset'] ?? '') === 'small'  ? 'selected' : '' ?>>🔲 Klein – leichte Rundung</option>
                <option value="medium" <?= ($theme['theme_radius_preset'] ?? '') === 'medium' ? 'selected' : '' ?>>🔘 Mittel (Standard)</option>
                <option value="large"  <?= ($theme['theme_radius_preset'] ?? '') === 'large'  ? 'selected' : '' ?>>⭕ <?= t('adm_radius_gross') ?></option>
                <option value="pill"   <?= ($theme['theme_radius_preset'] ?? '') === 'pill'   ? 'selected' : '' ?>>💊 Pille – extra-rund</option>
              </select>
            </div>

            <h3 style="margin-top:18px"><?= t('admin_maximalbreite') ?></h3>
            <div class="form-row">
              <select name="theme_max_width" data-live="theme_max_width">
                <option value="980"  <?= (int) ($theme['theme_max_width'] ?? 1200) === 980  ? 'selected' : '' ?>>980px – schmal</option>
                <option value="1100" <?= (int) ($theme['theme_max_width'] ?? 1200) === 1100 ? 'selected' : '' ?>>1100px</option>
                <option value="1200" <?= (int) ($theme['theme_max_width'] ?? 1200) === 1200 ? 'selected' : '' ?>>1200px (Standard)</option>
                <option value="1400" <?= (int) ($theme['theme_max_width'] ?? 1200) === 1400 ? 'selected' : '' ?>>1400px</option>
                <option value="1600" <?= (int) ($theme['theme_max_width'] ?? 1200) === 1600 ? 'selected' : '' ?>>1600px – breit</option>
              </select>
              <div class="form-hint"><?= t('admin_maximale_breite_des_forum') ?></div>
            </div>

            <h3 style="margin-top:18px"><?= t('admin_dichte') ?></h3>
            <div class="form-row">
              <select name="theme_density" data-live="theme_density">
                <option value="compact"     <?= ($theme['theme_density'] ?? '') === 'compact'     ? 'selected' : '' ?>>🤏 Kompakt</option>
                <option value="normal"      <?= ($theme['theme_density'] ?? '') === 'normal'      ? 'selected' : '' ?>>👌 Normal (Standard)</option>
                <option value="comfortable" <?= ($theme['theme_density'] ?? '') === 'comfortable' ? 'selected' : '' ?>>🛋️ Komfortabel</option>
              </select>
              <div class="form-hint"><?= t('admin_beeinflusst_abstaende_und_padding') ?></div>
            </div>
          </div>

          <?php /* ═══ TAB: LAYOUT ═══════════════════════════════ */ ?>
          <div class="design-tab-pane" data-pane="layout">
            <h3><?= t('admin_header_stil') ?></h3>
            <div class="form-row">
              <select name="theme_header_style" data-live="theme_header_style">
                <option value="default"  <?= ($theme['theme_header_style'] ?? 'default') === 'default'  ? 'selected' : '' ?>>📋 <?= t('adm_kopf_standard') ?></option>
                <option value="centered" <?= ($theme['theme_header_style'] ?? '') === 'centered' ? 'selected' : '' ?>>⚖️ Zentriert (Logo mittig, klassisch)</option>
                <option value="compact"  <?= ($theme['theme_header_style'] ?? '') === 'compact'  ? 'selected' : '' ?>>🤏 Kompakt (kleineres Banner)</option>
                <option value="inline"   <?= ($theme['theme_header_style'] ?? '') === 'inline'   ? 'selected' : '' ?>>📏 <?= t('adm_kopf_eine_zeile') ?></option>
              </select>
              <div class="form-hint"><?= t('admin_bestimmt_die_anordnung_im') ?></div>
            </div>

            <h3 style="margin-top:18px"><?= t('admin_footer_text') ?></h3>
            <div class="form-row">
              <label><?= t('admin_footer_text_zeile_1') ?></label>
              <input type="text" name="forum_footer_text" value="<?= e($branding['forum_footer_text']) ?>"
                     placeholder="© <?= date('Y') ?> &bull; <?= e(t('adm_alle_rechte')) ?>" maxlength="200">
              <div class="form-hint">
                <?= t('admin_wenn_leer_standard_copyright') ?>
              </div>
            </div>

            <div class="form-row">
              <label><?= t('admin_zusaetzliche_footer_links') ?></label>
              <input type="text" name="forum_footer_links" value="<?= e($branding['forum_footer_links']) ?>"
                     placeholder="Discord|https://discord.gg/xyz, GitHub|https://github.com/...">
              <div class="form-hint">
                <?= t('admin_format') ?> <code><?= t('admin_label1_url1_label2_url2') ?></code> <?= t('adm_links_kommagetrennt') ?>
              </div>
            </div>

            <div class="form-row">
              <label><?= t('admin_quelltext_url') ?></label>
              <input type="url" name="source_url" value="<?= e($branding['source_url']) ?>"
                     placeholder="<?= e(FORUMIX_URL) ?>">
              <div class="form-hint">
                <?= t('admin_ziel_des_forumix_zeichens') ?>
                <code><?= e(FORUMIX_URL) ?></code>. <strong><?= t('admin_wenn_du_den_code') ?></strong> <?= t('adm_agpl_hinweis') ?>
              </div>
            </div>
          </div>

          <?php /* ═══ TAB: PRESETS ═════════════════════════════ */ ?>
          <div class="design-tab-pane" data-pane="presets">
            <h3><?= t('admin_komplett_themes_mit_einem') ?></h3>
            <p style="font-size:13px;color:var(--text-muted);margin-bottom:12px">
              <?= t('admin_diese_presets_ueberschreiben_alle') ?>
            </p>

            <div class="theme-presets-grid">
              <?php
                $presets = [
                  'original' => [
                    'name' => '🌑 Original (Standard)', 'desc' => t('adm_theme_original_desc'),
                    'preview' => ['#0a0a0b', '#131316', '#4f46e5', '#e7e7ea'],
                  ],
                  'discord' => [
                    'name' => '💬 Discord-Style', 'desc' => t('adm_theme_discord_desc'),
                    'preview' => ['#36393f', '#2f3136', '#5865f2', '#dcddde'],
                  ],
                  'mintgrun' => [
                    'name' => t('admin_y_mintgruen'), 'desc' => t('adm_theme_mint_desc'),
                    'preview' => ['#0f1f1c', '#162a25', '#10b981', '#e8f5e9'],
                  ],
                  'sonne' => [
                    'name' => '🌅 Sonnen-Orange', 'desc' => t('adm_theme_sonne_desc'),
                    'preview' => ['#1a0f08', '#2a1a10', '#f97316', '#fef3c7'],
                  ],
                  'cyberpunk' => [
                    'name' => '🌃 Cyberpunk', 'desc' => t('admin_knalliges_lila_pink_auf'),
                    'preview' => ['#0a0014', '#15001f', '#ec4899', '#e0c3fc'],
                  ],
                  'softlight' => [
                    'name' => t('admin_y_soft_light'), 'desc' => t('adm_theme_soft_desc'),
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
                  <button type="button" class="btn btn-primary btn-sm theme-preset-apply" data-preset="<?= e($id) ?>"><?= t('admin_x_anwenden') ?></button>
                </div>
              <?php endforeach; ?>
            </div>
            <div class="form-hint" style="margin-top:10px">
              <?= t('admin_nach_anwenden_werden_die') ?>
            </div>
          </div>

          <?php /* ═══ TAB: ERWEITERT ═══════════════════════════ */ ?>
          <div class="design-tab-pane" data-pane="advanced">
            <h3><?= t('admin_custom_css') ?> <span style="font-size:11px;color:var(--color-warning);margin-left:6px"><?= t('admin_nur_fuer_experten') ?></span></h3>
            <div class="form-row">
              <label><?= t('admin_eigenes_css_optional') ?></label>
              <textarea name="forum_custom_css" rows="12" style="font-family:var(--font-mono);font-size:12px"
                        placeholder=".thread-title-link { font-weight: 800 !important; }"><?= e($branding['forum_custom_css']) ?></textarea>
              <div class="form-hint">
                <?= t('admin_dieses_css_wird_direkt') ?> <code>&lt;head&gt;</code> <?= t('adm_jeder_seite') ?>
                <strong><?= t('admin_vorsicht') ?></strong> <?= t('admin_falsches_css_kann_das') ?>
              </div>
            </div>
          </div>

          <div class="design-actions">
            <button type="submit" class="btn btn-primary"><?= t('admin_speichern_2') ?></button>
            <button type="button" id="reload-preview" class="btn btn-ghost"><?= t('admin_vorschau_neu_laden') ?></button>
            <button type="button" id="reset-design-btn" class="btn btn-ghost btn-sm" style="color:var(--color-warning);margin-left:auto"><?= t('admin_auf_standard_zuruecksetzen') ?></button>
          </div>
        </form>

        <?php /* Reset-Form: AUSSERHALB des Design-Forms (sonst verschachtelt!) */ ?>
        <form method="post" action="?action=reset_design" id="reset-design-form" style="display:none">
          <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
        </form>

        <?php /* ─── Live-Vorschau (rechte Seite) ─── */ ?>
        <div class="design-preview">
          <div class="design-preview-label">
            <span><?= t('admin_live_vorschau') ?></span>
            <a href="<?= e(FORUM_URL) ?>/?preview=1" target="_blank" rel="noopener" title="<?= e(t('adm_neuer_tab')) ?>">↗ <?= t('adm_grossansicht') ?></a>
          </div>
          <iframe id="design-preview-frame" src="<?= e(FORUM_URL) ?>/?preview=1"
                  style="width:100%;height:600px;border:1px solid var(--border);border-radius:var(--radius-sm);background:#fff"></iframe>
        </div>
      </div>

    <?php /* ════════════ MODULE ════════════ */ ?>
    <?php elseif ($action === 'modules'): ?>
      <h2><?= t('admin_module') ?></h2>
      <p style="font-size:13px;color:var(--text-muted);margin-bottom:16px">
        <?= t('admin_aktivierbare_erweiterungen_fuer_dein') ?>
      </p>

      <?php /* ── Beim Hersteller nachsehen ── */ ?>
      <?php if (is_owner() && empty($quelleAn)): ?>
        <form method="post" action="?action=save_settings" class="card"
              style="padding:14px 16px;margin-bottom:18px">
          <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
          <input type="hidden" name="settings_section" value="module">
          <strong style="font-size:14px"><?= t('admin_module_beim_hersteller_nachschlagen') ?></strong>
          <p style="font-size:13px;color:var(--text-muted);margin:6px 0 10px;line-height:1.6">
            <?= t('admin_forumix_ruft_von_sich') ?><br>
            <?= t('admin_der_abruf_geschieht_nur_2') ?>
          </p>
          <label style="display:flex;align-items:center;gap:8px;margin-bottom:10px">
            <input type="checkbox" name="module_quelle_an" value="1">
            <?= t('admin_nachschlagen_erlauben') ?>
          </label>
          <button type="submit" class="btn"><?= t('admin_speichern') ?></button>
        </form>
      <?php endif; ?>

      <?php if (is_owner() && !empty($quelleAn)): ?>
        <div class="card" style="padding:14px 16px;margin-bottom:18px">
          <form method="post" action="<?= e(FORUM_URL) ?>/admin/?action=module_quelle"
                style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin:0">
            <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
            <strong style="font-size:14px"><?= t('admin_module_beim_hersteller') ?></strong>
            <button type="submit" name="suchen" value="1" class="btn"><?= t('admin_nachsehen') ?></button>
            <?php if (!empty($quelleStand)): ?>
              <span style="font-size:12px;color:var(--text-faint)">
                <?= t('adm_stand_zeit', e(date(t('datum_format'), (int) $quelleStand))) ?>
              </span>
            <?php endif; ?>
          </form>

          <?php if (!empty($quelleModule)): ?>
            <table class="forum-table" style="margin-top:12px">
              <tbody>
                <?php foreach ($quelleModule as $q): ?>
                  <tr>
                    <td>
                      <strong><?= e($q['icon']) ?> <?= e($q['name']) ?></strong>
                      <div style="font-size:12px;color:var(--text-muted)"><?= e($q['beschreibung']) ?></div>
                    </td>
                    <td style="width:150px;font-size:12px;color:var(--text-muted)">
                      <?php if ($q['hier'] === null): ?>
                        <?= t('adm_nicht_installiert') ?>
                      <?php elseif ($q['neuer']): ?>
                        <?= e((string) $q['hier']) ?> → <strong><?= e($q['dort']) ?></strong>
                      <?php else: ?>
                        <?= e((string) $q['hier']) ?> · aktuell
                      <?php endif; ?>
                    </td>
                    <td style="width:130px;text-align:right">
                      <?php if (!$q['passt']): ?>
                        <span style="font-size:12px;color:var(--color-warning)">braucht neueres Forumix</span>
                      <?php elseif ($q['hier'] === null || $q['neuer']): ?>
                        <form method="post" action="<?= e(FORUM_URL) ?>/admin/?action=module_quelle" style="margin:0">
                          <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
                          <input type="hidden" name="code" value="<?= e($q['code']) ?>">
                          <button type="submit" class="btn btn-primary" style="font-size:12px">
                            <?= $q['hier'] === null ? 'Holen' : 'Aktualisieren' ?>
                          </button>
                        </form>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>

          <p style="font-size:12px;color:var(--text-faint);margin:10px 0 0">
            <?= t('admin_der_abruf_geschieht_nur') ?>
          </p>
          <form method="post" action="?action=save_settings" style="margin:6px 0 0">
            <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="settings_section" value="module">
            <button type="submit" class="btn btn-link" style="font-size:12px;padding:0;background:none;border:0;color:var(--text-faint);text-decoration:none;cursor:pointer">
              <?= t('admin_nachschlagen_wieder_abschalten') ?>
            </button>
          </form>
        </div>
      <?php endif; ?>

      <?php /* Nur der Inhaber: hier landen PHP-Dateien auf dem Server. */ ?>
      <?php if (is_owner()): ?>
        <form method="post" enctype="multipart/form-data"
              action="<?= e(FORUM_URL) ?>/admin/?action=module_upload"
              class="card" style="padding:14px 16px;margin-bottom:18px">
          <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
          <strong style="font-size:14px"><?= t('admin_modul_einspielen') ?></strong>
          <p style="font-size:13px;color:var(--text-muted);margin:6px 0 10px">
            <?= t('admin_module_gibt_es_einzeln') ?> <code>module.json</code><?= t('adm_modul_einspielen_hinweis') ?>
          </p>
          <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
            <input type="file" name="modulpaket" accept=".zip" required>
            <button type="submit" class="btn btn-primary"><?= t('admin_einspielen') ?></button>
          </div>
        </form>
      <?php endif; ?>

      <?php if (empty($modules)): ?>
        <div class="card" style="padding:40px;text-align:center">
          <div style="font-size:48px;margin-bottom:12px">📦</div>
          <strong><?= t('admin_keine_module_gefunden') ?></strong>
          <p style="font-size:13px;color:var(--text-muted);margin-top:8px">
            <?= t('admin_module_liegen_unter') ?> <code>/modules/</code>. <?= t('adm_modul_ordner_hinweis') ?>
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
                  <?= t('index_von') ?> <?= e($m['author']) ?>
                  <?php if ($m['installed_at']): ?>
                    · <?= t('adm_installiert_am', e(format_date($m['installed_at']))) ?>
                  <?php endif; ?>
                </div>
              </div>
              <div class="module-toggle">
                <?php if ($m['enabled']): ?>
                  <?php /* Kein rotes Rot: Abschalten ist reversibel und laesst
                          alles stehen. Rot gehoert an den Entfernen-Knopf. */ ?>
                  <a href="?action=module_toggle&code=<?= urlencode($m['code']) ?>&enable=0&csrf=<?= e($csrfToken) ?>"
                     class="btn btn-ghost btn-sm"
                     onclick="return confirm('Modul „<?= e($m['name']) ?>“ deaktivieren?')">
                    ⏸️ Abschalten
                  </a>
                <?php else: ?>
                  <a href="?action=module_toggle&code=<?= urlencode($m['code']) ?>&enable=1&csrf=<?= e($csrfToken) ?>"
                     class="btn btn-primary btn-sm">
                    🟢 Aktivieren
                  </a>
                  <?php /* Entfernen nur, wenn abgeschaltet — und nur fuer den
                          Inhaber, weil hier Dateien vom Server verschwinden. */ ?>
                  <?php if (is_owner()): ?>
                    <?php
                      // Die Rueckfrage nennt die Tabellen beim Namen. "Alle Daten
                      // gehen verloren" liest sich wie eine Floskel; "shoutbox_messages
                      // wird gelöscht" nicht.
                      $tab = function_exists('module_tabellen') ? module_tabellen($m['code']) : [];
                      // Doppelte Anfuehrungszeichen: in einfachen bleibt \n ein
                      // Backslash mit einem n, und die Rueckfrage stuende als
                      // eine lange Zeile mit sichtbaren \n darin.
                      $warnung = t('adm_modul_entfernen_frage', $m['name'])
                          . "\n\n" . t('adm_modul_geloescht_werden')
                          . ($tab ? t('adm_modul_und_tabellen') . "\n  " . implode("\n  ", $tab) : '')
                          . ".\n\n" . t('adm_modul_endgueltig');
                    ?>
                    <form method="post" action="?action=module_entfernen" style="display:inline;margin-left:6px"
                          <?php /* Durch e(): json_encode liefert einen String MIT
                                  Anfuehrungszeichen, und das erste beendet sonst
                                  das onsubmit-Attribut. Die Rueckfrage fiel damit
                                  ersatzlos aus — geloescht wurde trotzdem. */ ?>
                          onsubmit="return confirm(<?= e(json_encode($warnung, JSON_UNESCAPED_UNICODE)) ?>)">
                      <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
                      <input type="hidden" name="code" value="<?= e($m['code']) ?>">
                      <button type="submit" class="btn btn-danger btn-sm"><?= t('admin_entfernen_2') ?></button>
                    </form>
                  <?php endif; ?>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

    <?php /* ════════════ UPDATES (DB-MIGRATIONEN) ════════════ */ ?>
    <?php elseif ($action === 'import'): ?>
      <h2><?= t('admin_aus_phpbb_uebernehmen') ?></h2>

      <?php $b = $importBericht ?? null; $o = $importOpt ?? null; ?>

      <?php if (!empty($importLaeuft)): ?>
        <?php /* ── Ein Import steht bereits ── */ ?>
        <div class="card" style="padding:18px 22px;margin-bottom:18px;border-left:3px solid var(--color-success)">
          <h3 style="margin:0 0 10px"><?= t('admin_ein_import_ist_eingespielt') ?></h3>
          <div class="stat-grid" style="margin-bottom:14px">
            <?php foreach (['user' => ['👤',t('adm_konten')], 'board' => ['📁',t('adm_foren')],
                            'thema' => ['💬',t('mod_themen')], 'beitrag' => ['✉️',t('mod_beitraege')],
                            'anhang' => ['📎',t('admin_anhaenge')]] as $art => [$icon, $label]): ?>
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
            <?= t('admin_alles_uebernommene_ist_als') ?>
          </p>
          <form method="post" action="?action=import_zurueck"
                onsubmit="return confirm(<?= e(json_encode(t('adm_import_zuruecknehmen_frage'), JSON_UNESCAPED_UNICODE)) ?>)">
            <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
            <button type="submit" class="btn btn-danger btn-sm"><?= t('admin_import_zuruecknehmen') ?></button>
          </form>
        </div>
      <?php endif; ?>

      <?php if ($b === null): ?>
        <?php /* ── Schritt 1: Zugangsdaten ── */ ?>
        <div class="card" style="padding:18px 22px;margin-bottom:18px">
          <p style="font-size:13px;color:var(--text-muted);line-height:1.7;margin:0">
            <?= t('admin_import_hinweis', '<strong>' . e(t('admin_die_passwoerter_kommen_mit')) . '</strong>') ?>
          </p>
        </div>

        <form method="post" action="?action=import_pruefen">
          <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">

          <div class="form-card" style="margin-bottom:16px">
            <h3 style="margin-top:0"><?= t('admin_datenbank_des_alten_forums') ?></h3>

            <div class="form-row">
              <label><?= t('admin_datenbank') ?> <span style="color:var(--color-danger)">*</span></label>
              <input type="text" name="db_name" placeholder="phpbb" required autofocus>
              <div class="form-hint"><?= t('admin_der_name_der_datenbank') ?></div>
            </div>

            <div class="form-row">
              <label><?= t('admin_benutzer') ?> <span style="color:var(--color-danger)">*</span></label>
              <input type="text" name="db_user" placeholder="phpbb_user" required>
            </div>

            <div class="form-row">
              <label><?= t('admin_passwort') ?></label>
              <input type="password" name="db_pass" autocomplete="new-password">
            </div>

            <div class="form-row">
              <label><?= t('admin_server') ?></label>
              <input type="text" name="db_host" value="localhost">
              <div class="form-hint">
                <?= t('admin_liegt_das_alte_forum') ?> <code>localhost</code>.
              </div>
            </div>

            <div class="form-row">
              <label><?= t('admin_tabellen_praefix') ?></label>
              <input type="text" name="db_praefix" value="phpbb_">
              <div class="form-hint">
                <?= t('admin_steht_in_der') ?> <code>config.php</code> <?= t('adm_import_prefix_hinweis', '<code>$table_prefix</code>') ?>
              </div>
            </div>
          </div>

          <div class="form-card" style="margin-bottom:16px">
            <h3 style="margin-top:0"><?= t('admin_anhaenge') ?></h3>
            <div class="form-row">
              <label><?= t('admin_ordner_mit_den_dateien') ?></label>
              <input type="text" name="dateien" placeholder="/pfad/zu/phpBB3/files">
              <div class="form-hint">
                <?= t('admin_phpbb_upload_pfad', '<em>' . e(t('admin_anhaenge_einstellungen')) . '</em>') ?>
                <?= t('admin_upload_pfad_leer', '<code>files</code>') ?>
              </div>
            </div>
          </div>

          <button type="submit" class="btn btn-primary"><?= t('admin_nachsehen_was_da_ist') ?></button>
          <span style="font-size:12px;color:var(--text-faint);margin-left:10px">
            <?= t('admin_liest_nur_schreibt_noch') ?>
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
            <h3 style="margin:0"><?= t('admin_gefunden_in') ?> <code><?= e($o['db'] ?? '') ?></code></h3>
            <span style="font-size:13px;color:var(--text-muted)">
              phpBB <?= $bs['fassung'] !== '' ? e($bs['fassung']) : 'unbekannter Fassung' ?>
            </span>
          </div>
        </div>

        <?php if ($alt32): ?>
          <?php /* Der Umbau des Beitragsformats ist phpBBs eigene Arbeit — sein
                    Text-Reparser erledigt ihn beim Update auf 3.2. Ihn hier
                    nachzubauen hiesse, einen gepflegten Weg zu verdoppeln. */ ?>
          <div class="card" style="padding:16px 20px;margin-bottom:16px;
                      background:rgba(229,83,75,0.1);border-left:3px solid var(--color-danger)">
            <strong style="display:block;margin-bottom:6px"><?= t('admin_diese_fassung_ist_zu') ?></strong>
            <p style="margin:0 0 10px;font-size:13px;line-height:1.7">
              <?= t('admin_ab_3_2_legt') ?> <?= t('adm_import_zu_alt_hinweis', '<strong>' . e(t('adm_import_auf_33')) . '</strong>') ?>
            </p>
            <a href="?action=import_vergessen" class="btn btn-ghost btn-sm"><?= t('admin_zurueck') ?></a>
          </div>
        <?php endif; ?>

        <div class="stat-grid" style="margin-bottom:18px">
          <?php foreach ([
            ['👤', $bs['konten'],     'Konten'],
            ['📂', $bs['kategorien'], t('adm_kategorien')],
            ['📁', $bs['foren'],      t('adm_foren')],
            ['💬', $bs['themen'],     t('mod_themen')],
            ['✉️', $bs['beitraege'],  t('mod_beitraege')],
            ['📎', $bs['anhaenge'],   t('admin_anhaenge')],
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
            <?= tn('adm_import_bots', (int) $bs['bots']) ?>
          </p>
        <?php endif; ?>

        <?php /* ── Passwörter: die Zahl, nach der man den Umzug plant ── */ ?>
        <div class="card" style="padding:18px 22px;margin-bottom:16px">
          <h3 style="margin:0 0 12px"><?= t('admin_passwoerter') ?></h3>
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
              <?= t('adm_import_neues_passwort_noetig') ?>
            </div>
            <?php endif; ?>
          </div>
          <div class="form-hint" style="margin-top:10px">
            <?php if ($pw['neu_noetig'] > 0): ?>
              <?= t('adm_import_alte_passwoerter') ?>
            <?php else: ?>
              <?= t('adm_import_alle_passwoerter_ok') ?>
            <?php endif; ?>
          </div>
        </div>

        <?php if ($bs['anhaenge'] > 0): ?>
          <div class="card" style="padding:16px 20px;margin-bottom:16px<?= $dat['fehlen'] > 0 || !$dat['ordner'] ? ';border-left:3px solid #aa6644' : '' ?>">
            <h3 style="margin:0 0 8px"><?= t('admin_anhaenge') ?></h3>
            <?php if (!$dat['ordner']): ?>
              <p style="margin:0;font-size:13px;color:var(--text-muted)">
                <?= tn('adm_import_kein_ordner', (int) $bs['anhaenge']) ?> <a href="?action=import_vergessen"><?= t('admin_nochmal_eingeben') ?></a>.
              </p>
            <?php elseif ($dat['fehlen'] > 0): ?>
              <p style="margin:0;font-size:13px">
                <?= t('adm_import_dateien_fehlen', '<strong>' . zahl((int) $dat['fehlen']) . '</strong>', zahl((int) $bs['anhaenge'])) ?>
                <code><?= e($o['dateien'] ?? '') ?></code>.
                <a href="?action=import_vergessen"><?= t('admin_pfad_aendern') ?></a>
              </p>
            <?php else: ?>
              <p style="margin:0;font-size:13px;color:var(--text-muted)">
                <?= t('adm_import_dateien_da', zahl((int) $dat['gefunden'])) ?>
              </p>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <?php /* ── Schritt 3: Der Lauf ── */ ?>
        <?php if (!$alt32): ?>
        <div class="card" style="padding:18px 22px" id="import-lauf">
          <h3 style="margin:0 0 12px"><?= t('admin_uebernehmen') ?></h3>

          <div id="import-fortschritt" hidden>
            <div style="height:10px;border-radius:5px;background:var(--bg-surface2);overflow:hidden;margin-bottom:12px">
              <div id="import-balken" style="height:100%;width:0;background:var(--color-accent);transition:width .3s"></div>
            </div>
            <table style="width:100%;border-collapse:collapse;font-size:13px">
              <?php foreach (IMPORT_SCHRITTE as $sch => $labelKey): $label = t($labelKey); ?>
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
                <strong style="display:block;margin-bottom:8px"><?= t('admin_hier_steht_schon_etwas') ?></strong>
                <div style="font-size:13px;line-height:1.8;color:var(--text-muted)">
                  <?php
                    $teile = [];
                    foreach ([['foren', t('adm_forum'), t('adm_foren')], ['themen', t('adm_thema'), t('mod_themen')],
                              ['beitraege', t('adm_beitrag'), t('mod_beitraege')], ['anhaenge', t('adm_anhang'), t('admin_anhaenge')],
                              ['pn', t('adm_nachricht'), t('adm_nachrichten')]] as [$k, $ein, $viele]) {
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
                  <span><strong><?= t('admin_das_alles_vorher_loeschen') ?></strong>
                    <?= t('admin_dein_eigenes_konto_bleibt') ?></span>
                </label>
                <div class="form-hint" style="margin-top:8px">
                  <?= t('admin_ohne_haekchen_kommt_das') ?> <strong><?= t('adm_nicht') ?></strong> <?= t('adm_import_nicht_zuruecknehmbar') ?>
                </div>
              </div>
            <?php endif; ?>
            <p style="font-size:13px;color:var(--text-muted);line-height:1.7;margin:0 0 14px">
              <?= t('admin_der_import_laeuft_in') ?>
            </p>
            <button type="button" class="btn btn-primary" id="import-knopf"><?= t('admin_import_starten') ?></button>
            <a href="?action=import_vergessen" class="btn btn-ghost btn-sm" style="margin-left:8px"><?= t('admin_abbrechen') ?></a>
          </div>

          <div id="import-fertig" hidden>
            <p style="margin:14px 0 0;font-size:14px">
              <strong style="color:var(--color-success)"><?= t('admin_fertig') ?></strong>
              <a href="<?= e(FORUM_URL) ?>/index.php"><?= t('admin_x_zum_forum') ?></a> ·
              <a href="?action=boards"><?= t('admin_rechte_je_forum_einrichten') ?></a>
            </p>
          </div>
        </div>

        <?php endif; ?>

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
                !confirm(<?= e(json_encode(t('adm_alles_loeschen_frage'), JSON_UNESCAPED_UNICODE)) ?>)) {
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
      <h2><?= t('admin_forum_updates') ?></h2>

      <?php /* ── Von aussen betreut (v0.25.0) ── */ ?>
      <?php if (!empty($extern)): ?>
      <div class="card" style="margin-bottom:18px;padding:18px 22px;border-left:3px solid var(--color-accent)">
        <h3 style="margin:0 0 8px"><?= t('admin_updates_macht_der_betreiber') ?></h3>
        <p style="font-size:13px;color:var(--text-muted);line-height:1.7;margin:0">
          <?= t('admin_dieses_forum_wird_gehostet') ?>
        </p>
        <p style="font-size:13px;color:var(--text-muted);line-height:1.7;margin:10px 0 0">
          <?= t('admin_installierte_version') ?> <strong><?= e(forum_version()) ?></strong>.
          <?= t('adm_verlauf_hinweis') ?>
        </p>
      </div>
      <?php endif; ?>

      <?php /* ── Update-Paket hochladen (v0.18.54, nur Owner) ── */ ?>
      <?php if (!empty($canUpload)): ?>
      <div class="card" style="margin-bottom:18px;padding:20px 22px">
        <h3 style="margin:0 0 10px"><?= t('admin_update_paket_einspielen') ?></h3>
        <p style="font-size:13px;color:var(--text-muted);line-height:1.6;margin:0 0 14px">
          <?= t('admin_lade_ein_update_zip') ?>
        </p>

        <form method="post" action="?action=upload_update" enctype="multipart/form-data"
              onsubmit="return confirm(<?= e(json_encode(t('adm_update_einspielen_frage'), JSON_UNESCAPED_UNICODE)) ?>)">
          <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
          <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
            <input type="file" name="update_zip" accept=".zip,application/zip" required
                   style="flex:1;min-width:240px">
            <button type="submit" class="btn btn-primary"><?= t('admin_einspielen') ?></button>
          </div>
        </form>

        <div style="margin-top:14px;padding:12px 14px;background:var(--bg-surface2);border-radius:6px;font-size:12px;color:var(--text-muted);line-height:1.7">
          <strong><?= t('admin_aufbau_des_pakets') ?></strong>
          <code>update.json</code> <?= t('admin_pflicht_version_name') ?>
          <code>update.sql</code> <?= t('admin_optional_wird_ausgefuehrt') ?>
          <code>files/…</code> <?= t('admin_wird_ins_forum_kopiert') ?><br>
          <strong><?= t('admin_erlaubte_typen') ?></strong> php, css, js, sql, html, tpl, json, md, txt, Bilder, Fonts.
          <?= t('admin_typen_abbruch', '<code>..</code>') ?>
        </div>
      </div>
      <?php elseif (empty($extern)): ?>
      <div class="card" style="margin-bottom:18px;padding:16px 20px;background:var(--bg-surface2);border-left:3px solid var(--color-warning)">
        <p style="font-size:13px;color:var(--text-muted);margin:0">
          ℹ️ <?= t('adm_update_nur_owner', '<strong>' . e(t('admin_owner')) . '</strong>') ?>
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
            <p style="color:var(--color-danger);font-size:13px;margin:6px 0"><?= e($ur['error'] ?? t('error_titel')) ?></p>
          <?php endif; ?>
          <div style="margin-top:10px;background:var(--bg-surface2);border-radius:6px;padding:12px 14px;font-family:var(--font-mono,monospace);font-size:12px;line-height:1.7;max-height:340px;overflow:auto">
            <?php foreach ($ur['log'] as $line): ?>
              <div><?= e($line) ?></div>
            <?php endforeach; ?>
          </div>
          <p style="font-size:11px;color:var(--text-faint);margin:8px 0 0">
            <?= t('adm_datei_legende') ?>
          </p>
        </div>
      <?php endif; ?>

      <?php /* ── Datei-basierte Migrationen (Legacy, falls vorhanden) ── */ ?>
      <?php
        $pendingMigs = array_filter($migrations, fn($m) => !$m['executed']);
      ?>
      <?php if (!empty($pendingMigs)): ?>
      <div class="section-heading"><?= t('admin_verfuegbare_datei_migrationen') ?></div>
      <div class="card">
        <table class="forum-table">
          <thead>
            <tr>
              <th style="width:80px"><?= t('admin_version') ?></th>
              <th><?= t('admin_beschreibung') ?></th>
              <th style="width:160px"><?= t('admin_status') ?></th>
              <th style="width:160px;text-align:right"><?= t('admin_aktion') ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($pendingMigs as $m): ?>
              <tr>
                <td><code style="font-weight:700"><?= e($m['version']) ?></code></td>
                <td><?= e($m['name']) ?></td>
                <td><span class="role-tag" style="background:#f59e0b"><?= t('admin_ausstehend') ?></span></td>
                <td style="text-align:right">
                  <?php if (!empty($canMigrate)): ?>
                  <form method="post" action="?action=run_migration" style="display:inline">
                    <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="version" value="<?= e($m['version']) ?>">
                    <button type="submit" class="btn btn-primary btn-sm"
                            onclick="return confirm(<?= e(json_encode(t('adm_migration_ausfuehren_frage', $m['version']), JSON_UNESCAPED_UNICODE)) ?>)">
                      ▶️ <?= t('adm_jetzt_ausfuehren') ?>
                    </button>
                  </form>
                  <?php elseif (!empty($extern)): ?>
                  <span style="font-size:12px;color:var(--text-muted)"><?= t('adm_macht_der_betreiber') ?></span>
                  <?php else: ?>
                  <span style="font-size:12px;color:var(--text-muted)"><?= t('adm_nur_der_owner') ?></span>
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
        <?= t('admin_verlauf') ?> <span style="font-weight:400;color:var(--text-muted);font-size:13px">(<?= count($executed) ?> gesamt)</span>
      </div>
      <div class="card">
        <table class="forum-table">
          <thead>
            <tr>
              <th style="width:110px"><?= t('admin_version') ?></th>
              <th><?= t('admin_beschreibung') ?></th>
              <th style="width:140px"><?= t('admin_ausgefuehrt_am') ?></th>
              <th style="width:120px"><?= t('admin_durch') ?></th>
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
              ▾ <?= tn('adm_aeltere_eintraege', count($historyHidden)) ?>
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
        <strong style="font-size:13px"><?= t('admin_wichtige_hinweise') ?></strong>
        <ul style="margin:8px 0 0 18px;font-size:12px;color:var(--text-muted);line-height:1.8">
          <li><?= t('admin_vor_jedem_update') ?> <strong><?= t('admin_datenbank_backup') ?></strong> anlegen</li>
          <li><?= t('admin_sql_im_paket_wird') ?></li>
          <li><?= t('admin_nur_der') ?> <strong><?= t('admin_owner') ?></strong> <?= t('adm_owner_kann_pakete') ?></li>
          <li><?= t('admin_bei_fehlern_backup_zurueckspielen') ?></li>
        </ul>
      </div>
      <?php endif; ?>

    <?php /* ════════════ WARTUNG ════════════ */ ?>
    <?php elseif ($action === 'smileys'): ?>
      <div class="admin-page-head">
        <h1><?= t('admin_smileys_verwalten') ?></h1>
        <p><?= t('admin_bearbeite_die_smileys_die') ?></p>
      </div>

      <div class="form-card">
        <h2><?= t('admin_neues_smiley_anlegen') ?></h2>
        <form method="post" action="?action=add_smiley" class="smiley-add-form">
          <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
          <label><?= t('admin_code') ?>
            <input type="text" name="code" placeholder=":example:" required maxlength="30">
          </label>
          <label><?= t('admin_emoji') ?>
            <input type="text" name="emoji" placeholder="🙂" required maxlength="10" class="smiley-emoji-input">
          </label>
          <label><?= t('admin_beschreibung') ?>
            <input type="text" name="description" placeholder="<?= e(t('adm_smiley_platzhalter')) ?>" maxlength="100">
          </label>
          <label class="smiley-picker-toggle">
            <input type="checkbox" name="is_in_picker" value="1">
            <span><?= t('admin_im_picker') ?></span>
          </label>
          <button type="submit" class="btn btn-primary"><?= t('admin_anlegen') ?></button>
        </form>
      </div>

      <?php
        $smileysInPicker = array_filter($smileys, fn($s) => (int)$s['is_in_picker'] === 1);
        $smileysOther    = array_filter($smileys, fn($s) => (int)$s['is_in_picker'] !== 1);
      ?>

      <div class="form-card">
        <h2>⭐ Im Picker (<?= count($smileysInPicker) ?>)</h2>
        <p class="form-hint">
          <?= t('admin_smileys_picker_hinweis') ?>
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
                <span><?= t('admin_picker') ?></span>
              </label>
              <div class="smiley-actions">
                <button type="submit" class="btn btn-sm" title="Speichern">💾</button>
                <a href="?action=delete_smiley&id=<?= (int) $s['smiley_id'] ?>&csrf=<?= e($csrfToken) ?>" class="btn btn-sm btn-danger" onclick="return confirm(<?= e(json_encode(t('adm_smiley_loeschen_frage', $s['code']), JSON_UNESCAPED_UNICODE)) ?>)" title="<?= e(t('delete')) ?>">🗑️</a>
              </div>
            </form>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="form-card">
        <h2>📋 Weitere Smileys (<?= count($smileysOther) ?>)</h2>
        <p class="form-hint">
          <?= t('admin_smileys_als_code', '<code>:beer:</code>') ?>
          <?= t('admin_alias_hinweis', '<em>' . e(t('admin_alias_von')) . '</em>') ?><?= '' ?>
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
                  <small class="smiley-alias-hint"><?= t('admin_alias_von') ?> <code><?= e($aliasOf) ?></code></small>
                <?php endif; ?>
              </div>
              <input type="text" name="description" value="<?= e($s['description']) ?>" maxlength="100" class="smiley-desc-input">
              <label class="smiley-picker-toggle">
                <input type="checkbox" name="is_in_picker" value="1">
                <span><?= t('admin_picker') ?></span>
              </label>
              <div class="smiley-actions">
                <button type="submit" class="btn btn-sm" title="Speichern">💾</button>
                <a href="?action=delete_smiley&id=<?= (int) $s['smiley_id'] ?>&csrf=<?= e($csrfToken) ?>" class="btn btn-sm btn-danger" onclick="return confirm(<?= e(json_encode(t('adm_smiley_loeschen_frage', $s['code']), JSON_UNESCAPED_UNICODE)) ?>)" title="<?= e(t('delete')) ?>">🗑️</a>
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
        <h1><?= t('admin_rechtliches') ?></h1>
        <p>
          <?= t('admin_rechtliches_hinweis', '<strong>' . e(t('admin_impressum')) . '</strong>', '<strong>' . e(t('admin_datenschutzerklaerung')) . '</strong>', '<strong>' . e(t('admin_nutzungsbedingungen')) . '</strong>') ?>
        </p>
      </div>

      <!-- ─── Daten-Felder (Settings für Platzhalter) ─── -->
      <div class="form-card">
        <h2><?= t('admin_kontaktdaten') ?></h2>
        <p class="form-hint">
          <?= t('admin_platzhalter_daten_hinweis', '<code>{{name}}</code>, <code>{{street}}</code>, <code>{{city}}</code>, <code>{{email}}</code>') ?>
        </p>
        <form method="post" action="?action=save_legal">
          <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">

          <div class="form-row">
            <label for="imprint_name"><?= t('admin_name_verantwortliche_person') ?></label>
            <input type="text" id="imprint_name" name="imprint_name"
                   value="<?= e($imprint['name']) ?>"
                   placeholder="<?= e(t('adm_name_platzhalter')) ?>"
                   maxlength="200">
          </div>

          <div class="form-row">
            <label for="imprint_street"><?= t('admin_strasse_und_hausnummer') ?></label>
            <input type="text" id="imprint_street" name="imprint_street"
                   value="<?= e($imprint['street']) ?>"
                   placeholder="<?= e(t('adm_strasse_platzhalter')) ?>"
                   maxlength="200">
          </div>

          <div class="form-row">
            <label for="imprint_city"><?= t('admin_plz_und_ort') ?></label>
            <input type="text" id="imprint_city" name="imprint_city"
                   value="<?= e($imprint['city']) ?>"
                   placeholder="z.B. 12345 Musterstadt"
                   maxlength="200">
          </div>

          <div class="form-row">
            <label for="imprint_email"><?= t('admin_e_mail_adresse') ?></label>
            <input type="email" id="imprint_email" name="imprint_email"
                   value="<?= e($imprint['email']) ?>"
                   placeholder="z.B. kontakt@example.com"
                   maxlength="200">
          </div>

          <button type="submit" class="btn btn-primary"><?= t('admin_daten_speichern') ?></button>
        </form>
      </div>

      <!-- ─── Page-Editor ─── -->
      <?php if (!empty($editingPage) && !empty($pageData)): ?>
        <div class="form-card">
          <h2>✏️ Seite bearbeiten: <?= e($pageData['title']) ?></h2>
          <p class="form-hint">
            <?= t('adm_bbcode_platzhalter_hinweis') ?>
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
              $editorPlaceholder    = t('admin_seiten_inhalt_im_bbcode');
              $editorAllowHeadings  = true;
              $editorRequired       = true;
              include __DIR__ . '/../partials/editor.php';
            ?>

            <div style="display:flex;gap:8px;margin-top:14px">
              <button type="submit" class="btn btn-primary"><?= t('admin_seite_speichern') ?></button>
              <a href="<?= e(FORUM_URL) ?>/<?= e($pageData['slug']) ?>.php" target="_blank" class="btn btn-ghost">👁️ <?= t('adm_live_vorschau') ?></a>
              <a href="?action=legal" class="btn btn-ghost"><?= t('admin_zurueck_zur_uebersicht') ?></a>
            </div>
          </form>
        </div>

      <?php else: ?>
        <!-- Page-Liste -->
        <div class="form-card">
          <h2><?= t('admin_verfuegbare_seiten') ?></h2>
          <p class="form-hint">
            <?= t('admin_klick_auf_eine_seite') ?>
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
                    · <?= t('adm_zuletzt_geaendert', e(date(t('datum_format'), strtotime($p['updated_at'])))) ?>
                  <?php endif; ?>
                </span>
                <span class="page-list-arrow">→</span>
              </a>
            <?php endforeach; ?>
          </div>

          <?php if (empty($pages)): ?>
            <p style="color:var(--text-muted);font-style:italic;margin-top:14px">
              <?= t('admin_keine_pages_in_der') ?> <a href="?action=updates"><?= t('admin_migration_v0_18_6') ?></a><?= t('adm_migration_ausfuehren_satzende') ?>
            </p>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <div class="form-card" style="background:var(--bg-surface2)">
        <h3 style="margin-bottom:8px">ℹ️ <?= t('adm_hinweise') ?></h3>
        <ul style="margin-left:18px;line-height:1.7;color:var(--text-muted);font-size:14px">
          <li><?= t('admin_die_texte_sind_in') ?> <code>[b]</code>, <code>[i]</code>, <code>[u]</code>, <code>[h2]</code>, <code>[h3]</code>, <code>[list]</code>/<code>[*]</code>, <code>[hr]</code>, <code>[url=...]</code> usw.</li>
          <li><?= t('admin_platzhalter_ersetzt', '<code>{{name}}</code>') ?></li>
          <li><?= t('admin_bei_rechtlichen_unsicherheiten_bitte') ?> <strong><?= t('adm_kein_rechtsrat') ?></strong>.</li>
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
        <h1><?= t('admin_template_editor') ?></h1>
        <p>
          <?= t('admin_hier_kannst_du_templates') ?>
          <?= t('admin_als_override_gespeichert', '<strong>' . e(t('admin_override_in_der_datenbank')) . '</strong>') ?>
        </p>
      </div>

      <?php if (!empty($editingTpl)): ?>
        <!-- ─── Template bearbeiten ─── -->
        <div class="form-card">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
            <h2 style="margin:0">✏️ <?= e($editingTpl) ?>.tpl.html</h2>
            <div style="display:flex;gap:8px">
              <a href="?action=templates" class="btn btn-ghost btn-sm"><?= t('admin_zurueck') ?></a>
              <?php if (!empty($tplOverride)): ?>
                <form method="post" action="?action=reset_template" style="display:inline"
                      onsubmit="return confirm(<?= e(json_encode(t('adm_auf_original_frage'), JSON_UNESCAPED_UNICODE)) ?>);">
                  <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
                  <input type="hidden" name="name" value="<?= e($editingTpl) ?>">
                  <button type="submit" class="btn btn-ghost btn-sm"><?= t('admin_auf_original_zuruecksetzen') ?></button>
                </form>
              <?php endif; ?>
            </div>
          </div>

          <?php if (!empty($tplIsStale)): ?>
            <!-- ─── STALE-WARNUNG ─── -->
            <div class="flash flash-warning" style="margin-bottom:14px">
              <strong><?= t('admin_das_original_wurde_aktualisiert') ?></strong>
              <p style="margin:8px 0 0;font-size:13px">
                <?= t('admin_moeglicherweise_enthaelt_das_neue') ?>
              </p>
              <ul style="margin:8px 0 0 18px;font-size:13px;line-height:1.6">
                <li><strong><?= t('admin_mein_override_behalten') ?></strong><?= t('adm_override_behalten_text') ?></li>
                <li><strong><?= t('admin_override_neu_starten') ?></strong><?= t('adm_override_neu_text') ?></li>
                <li><strong><?= t('admin_manuell_mergen') ?></strong><?= t('adm_override_merge_text') ?></li>
              </ul>
              <div style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap">
                <form method="post" action="?action=ack_template_update" style="display:inline">
                  <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
                  <input type="hidden" name="name" value="<?= e($editingTpl) ?>">
                  <button type="submit" class="btn btn-ghost btn-sm"><?= t('admin_mein_override_behalten') ?></button>
                </form>
                <form method="post" action="?action=reset_template" style="display:inline"
                      onsubmit="return confirm(<?= e(json_encode(t('adm_override_loeschen_frage'), JSON_UNESCAPED_UNICODE)) ?>);">
                  <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
                  <input type="hidden" name="name" value="<?= e($editingTpl) ?>">
                  <button type="submit" class="btn btn-ghost btn-sm"><?= t('admin_override_neu_starten') ?></button>
                </form>
                <button type="button" id="btn-show-diff" class="btn btn-ghost btn-sm"><?= t('admin_original_aenderungen_anzeigen') ?></button>
              </div>
            </div>

            <div id="tpl-diff-view" hidden style="margin-bottom:14px">
              <h3 style="margin-bottom:8px;font-size:14px"><?= t('admin_diff_original_beim_save') ?></h3>
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
              ℹ️ <?= t('adm_tpl_unveraendert') ?>
            </p>
          <?php else: ?>
            <p class="form-hint">
              <?= t('admin_dieses_template_ist') ?> <strong><?= t('adm_angepasst') ?></strong>. <?= t('adm_tpl_override_aktualisiert') ?>
            </p>
          <?php endif; ?>

          <form method="post" action="?action=save_template" id="tpl-form">
            <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="name" value="<?= e($editingTpl) ?>">

            <div class="form-row">
              <textarea name="content" id="tpl-editor" rows="24" style="width:100%;font-family:var(--font-mono);font-size:13px"><?= e($tplEffective) ?></textarea>
            </div>

            <div style="display:flex;gap:8px;flex-wrap:wrap">
              <button type="submit" class="btn btn-primary"><?= t('admin_speichern_2') ?></button>
              <button type="button" id="btn-show-original" class="btn btn-ghost"><?= t('admin_original_anzeigen') ?></button>
            </div>
          </form>

          <div id="tpl-original-view" hidden style="margin-top:14px">
            <h3 style="margin-bottom:8px"><?= t('admin_original_read_only') ?></h3>
            <pre style="background:var(--bg-surface2);padding:14px;border-radius:6px;overflow:auto;font-family:var(--font-mono);font-size:12px;max-height:400px"><?= e($tplOriginal) ?></pre>
          </div>

          <div class="form-card" style="margin-top:20px;background:var(--bg-surface2)">
            <h3 style="margin-bottom:8px"><?= t('admin_template_syntax_twig_aehnlich') ?></h3>
            <ul style="margin-left:18px;line-height:1.8;color:var(--text-muted);font-size:13px">
              <li><code>{{ variable }}</code> – Variable ausgeben (HTML-escaped)</li>
              <li><code>{{ variable|raw }}</code> <?= t('adm_ohne_escape') ?></li>
              <li><code>{% if expr %}…{% else %}…{% endif %}</code> – Bedingung</li>
              <li><code>{% for item in list %}…{% endfor %}</code> – Schleife</li>
              <li><code>{% include 'partial' %}</code> – Anderes Template einbinden</li>
              <li><?= t('admin_filter') ?> <code>|upper</code>, <code>|lower</code>, <code>|trim</code>, <code>|length</code>, <code>|date('d.m.Y')</code>, <code>|truncate(50)</code>, <code>|nl2br</code>, <code>|default('-')</code></li>
              <li><?= t('admin_funktionen') ?> <code>url('/x')</code>, <code>asset('style.css')</code>, <code>t('key')</code>, <code>csrf_token()</code></li>
            </ul>
            <p style="margin-top:10px;color:var(--text-faint);font-size:12px">
              ⚠️ <strong><?= t('admin_php_tags_sind_verboten') ?></strong> (<code>&lt;?php</code>, <code>&lt;?=</code>) <?= t('adm_php_tags_abgelehnt') ?>
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
                btn.textContent = <?= json_encode(t('adm_original_anzeigen_kurz'), JSON_UNESCAPED_UNICODE) ?>;
              } else {
                view.removeAttribute('hidden');
                btn.textContent = <?= json_encode(t('adm_original_verbergen_kurz'), JSON_UNESCAPED_UNICODE) ?>;
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
                btnDiff.textContent = <?= json_encode(t('adm_original_anzeigen'), JSON_UNESCAPED_UNICODE) ?>;
              } else {
                viewDiff.removeAttribute('hidden');
                btnDiff.textContent = <?= json_encode(t('adm_original_verbergen'), JSON_UNESCAPED_UNICODE) ?>;
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
              <?= t('admin_aktuell_gibt_es_noch') ?> <code>views_new/</code><?= t('adm_tpl_noch_keine') ?>
            </p>
          </div>
        <?php else: ?>
          <div class="form-card">
            <h2>📑 <?= t('adm_verfuegbare_templates') ?> (<?= count($templates) ?>)</h2>
            <p class="form-hint">
              <span class="tpl-badge tpl-badge-original"><?= t('admin_original') ?></span> <?= t('admin_unveraendert') ?>
              <span class="tpl-badge tpl-badge-override"><?= t('admin_angepasst') ?></span> <?= t('admin_vom_admin_ueberschrieben') ?>
              <span class="tpl-badge tpl-badge-stale"><?= t('admin_veraltet') ?></span> <?= t('admin_original_wurde_seither_aktualisiert') ?>
            </p>

            <div style="display:flex;flex-direction:column;gap:8px;margin-top:14px">
              <?php foreach ($templates as $t): ?>
                <a href="?action=templates&edit=<?= e($t['name']) ?>" class="tpl-list-item">
                  <span class="tpl-list-name"><?= e($t['name']) ?><code>.tpl.html</code></span>
                  <span class="tpl-list-meta">
                    <?php if (!empty($t['is_stale'])): ?>
                      <span class="tpl-badge tpl-badge-stale"><?= t('admin_veraltet') ?></span>
                    <?php endif; ?>
                    <?php if ($t['has_override']): ?>
                      <span class="tpl-badge tpl-badge-override"><?= t('admin_angepasst') ?></span>
                      <?php if (!empty($t['updated_at'])): ?>
                        · <?= t('members_seit', e(date(t('datum_format'), strtotime($t['updated_at'])))) ?>
                      <?php endif; ?>
                    <?php else: ?>
                      <span class="tpl-badge tpl-badge-original"><?= t('admin_original') ?></span>
                    <?php endif; ?>
                  </span>
                  <span class="tpl-list-arrow">→</span>
                </a>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

        <div class="form-card" style="background:var(--bg-surface2)">
          <h3 style="margin-bottom:8px">ℹ️ <?= t('admin_wie_funktioniert_das') ?></h3>
          <ul style="margin-left:18px;line-height:1.7;color:var(--text-muted);font-size:14px">
            <li><?= t('admin_originale_im_code', '<code>views_new/*.tpl.html</code>') ?></li>
            <li><?= t('admin_deine_anpassungen_werden_als') ?> <strong><?= t('admin_override_in_der_datenbank') ?></strong> gespeichert (Tabelle <code>template_overrides</code>)</li>
            <li><?= t('admin_bei_updates_bleiben_deine') ?></li>
            <li><?= t('admin_mit_auf_original_zuruecksetzen') ?></li>
            <li><?= t('admin_php_tags_verboten', '<strong>' . e(t('admin_nicht_erlaubt')) . '</strong>') ?></li>
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
        <h1><?= t('admin_backups') ?></h1>
        <p>
          <?= t('admin_backups_hinweis', '<code>backup/</code>') ?>
        </p>
      </div>

      <?php if (empty($zipAvailable)): ?>
        <div class="flash flash-warning" style="margin-bottom:14px">
          ⚠️ <strong><?= t('admin_php_erweiterung_ziparchive_fehlt') ?></strong>
          <?= t('admin_datei_und_komplett_backups') ?>
        </div>
      <?php endif; ?>

      <!-- ─── Erstellen ─── -->
      <div class="form-card">
        <h2><?= t('admin_neues_backup_erstellen') ?></h2>
        <p class="form-hint">
          <?= t('admin_backups_koennen_je_nach') ?>
        </p>

        <form method="post" action="?action=create_backup" id="backup-form">
          <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">

          <div class="form-row">
            <label><?= t('admin_notiz_optional') ?></label>
            <input type="text" name="note" maxlength="200"
                   placeholder="<?= e(t('adm_backup_notiz_platzhalter')) ?>">
            <div class="form-hint"><?= t('admin_hilft_dir_spaeter_beim') ?></div>
          </div>

          <div style="display:flex;gap:8px;flex-wrap:wrap">
            <button type="submit" name="type" value="db" class="btn btn-primary">
              <?= t('admin_nur_datenbank') ?>
            </button>
            <button type="submit" name="type" value="files" class="btn btn-ghost" <?= empty($zipAvailable) ? 'disabled' : '' ?>>
              📁 <?= t('adm_nur_dateien') ?>
            </button>
            <button type="submit" name="type" value="full" class="btn btn-ghost" <?= empty($zipAvailable) ? 'disabled' : '' ?>
                    onclick="return confirm(<?= e(json_encode(t('adm_komplett_backup_frage'), JSON_UNESCAPED_UNICODE)) ?>);">
              📦 <?= t('adm_komplett_db_dateien') ?>
            </button>
          </div>
        </form>
      </div>

      <!-- ─── Liste ─── -->
      <div class="form-card">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
          <h2 style="margin:0">📋 <?= t('adm_vorhandene_backups') ?> (<?= count($backups) ?>)</h2>
          <span style="font-size:13px;color:var(--text-muted)">
            <?= t('adm_speicherbedarf', e(backup_format_size($backupTotalSize))) ?>
          </span>
        </div>

        <?php if (empty($backups)): ?>
          <p style="color:var(--text-muted);font-style:italic">
            <?= t('admin_noch_keine_backups_vorhanden') ?>
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
                'db'    => t('adm_backup_typ_db'),
                'files' => t('adm_backup_typ_dateien'),
                'full'  => t('adm_backup_typ_komplett'),
                default => t('adm_backup_typ_unbekannt'),
              };
            ?>
              <div class="backup-item">
                <div class="backup-icon"><?= $icon ?></div>
                <div class="backup-info">
                  <div class="backup-name"><?= e($b['filename']) ?></div>
                  <div class="backup-meta">
                    <span class="backup-type-badge backup-type-<?= e($b['type']) ?>"><?= e($label) ?></span>
                    · <?= e(backup_format_size($b['size'])) ?>
                    · <?= t('adm_erstellt_am', e(date(t('datum_format_sekunden'), $b['created_at']))) ?>
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
                        onsubmit="return confirm(<?= e(json_encode(t('adm_backup_loeschen_frage'), JSON_UNESCAPED_UNICODE)) ?>);">
                    <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="filename" value="<?= e($b['filename']) ?>">
                    <button type="submit" class="btn btn-danger btn-sm" title="<?= e(t('delete')) ?>">🗑️</button>
                  </form>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- ─── Hinweise ─── -->
      <div class="form-card" style="background:var(--bg-surface2)">
        <h3 style="margin-bottom:8px">ℹ️ <?= t('adm_wichtige_hinweise') ?></h3>
        <ul style="margin-left:18px;line-height:1.7;color:var(--text-muted);font-size:14px">
          <li><strong><?= t('admin_backups_gehoeren_nicht_auf') ?></strong> <?= t('adm_backup_woanders') ?></li>
          <li><?= t('admin_das') ?> <code>backup/</code><?= t('adm_backup_geschuetzt', '<code>.htaccess</code>') ?></li>
          <li><?= t('admin_config_nicht_im_backup', '<code>config.php</code>', '<strong>' . e(t('admin_nicht_erlaubt_kurz')) . '</strong>') ?></li>
          <li><?= t('admin_du_kannst_backups_bei') ?></li>
          <li><?= t('admin_beim_ersten_backup_wird') ?><code>.htaccess</code> <?= t('adm_backup_htaccess_pruefen', '<code>.htaccess</code>') ?></li>
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
      <h2><?= t('admin_wartungs_tools') ?></h2>

      <div class="form-card" style="margin-bottom:14px">
        <h2><?= t('admin_statistiken_neu_berechnen') ?></h2>
        <p style="font-size:13px;color:var(--text-muted);margin-bottom:12px">
          <?= t('admin_berechnet_thread_counter_post') ?>
        </p>
        <form method="post" action="?action=maintenance_action">
          <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
          <input type="hidden" name="task" value="recount_stats">
          <button type="submit" class="btn btn-primary"><?= t('admin_statistiken_neu_berechnen_2') ?></button>
        </form>
      </div>

      <div class="form-card" style="margin-bottom:14px">
        <h2><?= t('admin_datenbank_optimieren') ?></h2>
        <p style="font-size:13px;color:var(--text-muted);margin-bottom:12px">
          <?= t('admin_optimize_hinweis', '<code>OPTIMIZE TABLE</code>') ?>
        </p>
        <form method="post" action="?action=maintenance_action">
          <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
          <input type="hidden" name="task" value="optimize_tables">
          <button type="submit" class="btn btn-primary"><?= t('admin_tabellen_optimieren') ?></button>
        </form>
      </div>

      <div class="form-card" style="margin-bottom:14px">
        <h2><?= t('admin_ip_logs_aufraeumen') ?></h2>
        <p style="font-size:13px;color:var(--text-muted);margin-bottom:12px">
          <?= t('admin_aktuell') ?> <strong><?= number_format((int) $ipLogSize, 0, ',', '.') ?></strong> <?= t('admin_ip_log_eintraege') ?>
        </p>
        <form method="post" action="?action=maintenance_action">
          <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
          <input type="hidden" name="task" value="cleanup_ip_logs">
          <div class="form-row">
            <label><?= t('admin_eintraege_aelter_als_tage') ?></label>
            <input type="number" name="cleanup_days" value="90" min="7">
          </div>
          <button type="submit" class="btn btn-danger" onclick="return confirm(<?= e(json_encode(t('adm_wirklich_loeschen_frage'), JSON_UNESCAPED_UNICODE)) ?>)"><?= t('admin_ip_logs_loeschen') ?></button>
        </form>
      </div>

      <div class="form-card">
        <h2><?= t('admin_abgelaufene_ip_banns_entfernen') ?></h2>
        <p style="font-size:13px;color:var(--text-muted);margin-bottom:12px">
          <?= t('admin_aktuell') ?> <strong><?= (int) $expiredIpBans ?></strong> <?= t('adm_abgelaufene_ip_banns') ?>
        </p>
        <form method="post" action="?action=maintenance_action">
          <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
          <input type="hidden" name="task" value="cleanup_expired_ipbans">
          <button type="submit" class="btn btn-primary"><?= t('admin_abgelaufene_ip_banns_loeschen') ?></button>
        </form>
      </div>

      <div class="form-card" style="margin-top:14px">
        <h2><?= t('admin_cache_leeren') ?></h2>
        <p style="font-size:13px;color:var(--text-muted);margin-bottom:12px">
          <?= t('admin_leert_den_template_compile') ?>
        </p>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;margin-bottom:14px">
          <div class="cache-stat">
            <div class="cache-stat-label"><?= t('admin_template_cache') ?></div>
            <div class="cache-stat-value">
              <?= (int) $cacheInfo['tpl_count'] ?>
              <span class="cache-stat-unit"><?= $cacheInfo['tpl_count'] === 1 ? t('adm_datei') : t('adm_dateien') ?></span>
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
            <?= t('admin_template_cache_leeren') ?>
          </button>
          <button type="submit" name="type" value="all" class="btn btn-ghost">
            <?= t('admin_alle_caches_leeren') ?>
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
      <h2><?= t('admin_bot_verkehr') ?></h2>
      <p style="color:var(--text-muted);margin-bottom:18px;line-height:1.6">
        <?= t('admin_diese_liste_zeigt_alle') ?>
      </p>

      <!-- Tab-Filter -->
      <div class="bot-log-tabs">
        <?php
          $tabs = [
            'all'     => ['🌐 Alle',           $botStats['all']],
            'search'  => ['🔍 Suchmaschinen',  $botStats['search']],
            'ai'      => ['🤖 KI-Bots',        $botStats['ai']],
            'suspect' => [t('admin_y_verdaechtige'),   $botStats['suspect']],
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
              <?= t('adm_keine_bot_besuche') ?>
            <?php else: ?>
              <?= t('adm_keine_eintraege') ?>
            <?php endif; ?>
          </p>
          <p style="font-size:12px;margin:0">
            <?= t('adm_bot_besuche_seit') ?>
            <?php if ($botFilter === 'all'): ?>
              <?= t('adm_erste_eintraege') ?>
            <?php endif; ?>
          </p>
        </div>
      <?php else: ?>
        <div class="card">
          <table class="forum-table">
            <thead>
              <tr>
                <th><?= t('admin_bot') ?></th>
                <th><?= t('admin_kategorie') ?></th>
                <th><?= t('admin_ip_adresse') ?></th>
                <th style="text-align:right"><?= t('admin_besuche') ?></th>
                <th><?= t('admin_letzter_besuch') ?></th>
                <th><?= t('admin_letzte_seite') ?></th>
                <th><?= t('admin_aktion') ?></th>
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
                  'suspect'    => t('admin_y_verdaechtig'),
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
                      <strong><?= t('admin_user_agent') ?></strong> <code><?= e(mb_substr($b['user_agent'], 0, 200)) ?></code>
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
