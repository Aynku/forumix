<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// lib/profile_fields.php – Definition der erweiterten Profil-Felder
// und Privacy-Filter-Logik (v0.18.31)

/**
 * Liefert die Schema-Definition aller erweiterten Profil-Felder.
 * Format pro Feld:
 *   - 'icon'    : Emoji für die Anzeige
 *   - 'label'   : Sichtbarer Name
 *   - 'type'    : 'select' | 'date' | 'text' | 'textarea'
 *   - 'options' : nur bei 'select' – Auswahl-Werte
 *   - 'max'     : max. Zeichen (für text/textarea)
 *   - 'column'  : DB-Spalten-Name
 */
function profile_field_schema(): array {
    return [
        'gender' => [
            'icon'   => '🚻',
            'label'  => 'profile_field_gender',
            'type'   => 'select',
            'column' => 'profile_gender',
            'options' => [
                ''         => 'profile_gender_none',
                'female'   => 'profile_gender_female',
                'male'     => 'profile_gender_male',
                'diverse'  => 'profile_gender_diverse',
                'other'    => 'profile_gender_other',
            ],
        ],
        'birthday' => [
            'icon'   => '🎂',
            'label'  => 'profile_field_birthday',
            'type'   => 'date',
            'column' => 'profile_birthday',
        ],
        'location' => [
            'icon'   => '📍',
            'label'  => 'profile_field_place',
            'type'   => 'text',
            'column' => 'profile_location',
            'max'    => 100,
            'placeholder' => 'profile_place_placeholder',
        ],
        'occupation' => [
            'icon'   => '💼',
            'label'  => 'profile_field_job',
            'type'   => 'text',
            'column' => 'profile_occupation',
            'max'    => 100,
            'placeholder' => 'profile_job_placeholder',
        ],
        'interests' => [
            'icon'   => '🎯',
            'label'  => 'profile_field_interests',
            'type'   => 'text',
            'column' => 'profile_interests',
            'max'    => 500,
            'placeholder' => 'profile_interests_placeholder',
        ],
        'about' => [
            'icon'   => '📝',
            'label'  => 'profile_field_about',
            'type'   => 'textarea',
            'column' => 'profile_about',
            'placeholder' => 'profile_about_placeholder',
        ],
    ];
}

/**
 * Liefert die Privacy-Map des Users (deserialisiert aus dem JSON-Feld).
 * Defaults zu 'public' wenn nichts gesetzt.
 *
 * @return array<string,string> e.g. ['gender' => 'public', 'birthday' => 'members']
 */
function profile_privacy_settings(?array $user): array {
    $defaults = [];
    foreach (array_keys(profile_field_schema()) as $key) {
        $defaults[$key] = 'public';
    }
    if (!$user || empty($user['profile_privacy'])) return $defaults;

    $stored = json_decode($user['profile_privacy'], true);
    if (!is_array($stored)) return $defaults;

    $result = $defaults;
    foreach ($stored as $key => $value) {
        if (!isset($defaults[$key])) continue;
        if (in_array($value, ['public', 'members', 'private'], true)) {
            $result[$key] = $value;
        }
    }
    return $result;
}

/**
 * Speichert die Privacy-Map als JSON. Validiert Werte.
 */
function profile_privacy_serialize(array $input): string {
    $valid = ['public', 'members', 'private'];
    $schema = profile_field_schema();
    $out = [];
    foreach ($schema as $key => $_) {
        $val = $input[$key] ?? 'public';
        if (!in_array($val, $valid, true)) $val = 'public';
        $out[$key] = $val;
    }
    return json_encode($out);
}

/**
 * Entscheidet ob ein Profil-Feld für den Betrachter sichtbar ist.
 *
 * @param string $fieldKey       z.B. 'gender'
 * @param array  $profileUser    der angezeigte User
 * @param ?array $viewer         der eingeloggte User (oder null = Gast)
 * @return bool
 */
function profile_field_is_visible(string $fieldKey, array $profileUser, ?array $viewer): bool {
    // Eigenes Profil: alles sichtbar
    if ($viewer && (int) $viewer['user_id'] === (int) $profileUser['user_id']) {
        return true;
    }
    // Admins/Mods sehen alles (kann später per Setting eingeschränkt werden)
    if ($viewer && (!empty($viewer['is_admin']) || !empty($viewer['is_owner']))) {
        return true;
    }

    $privacy = profile_privacy_settings($profileUser);
    $level   = $privacy[$fieldKey] ?? 'public';

    return match ($level) {
        'public'  => true,
        'members' => $viewer !== null,  // eingeloggte User
        'private' => false,             // niemand außer Owner/Admin (oben behandelt)
        default   => true,
    };
}

/**
 * Holt den Anzeigewert eines Feldes (mit type-spezifischer Formatierung).
 * Liefert null wenn das Feld leer/nicht gesetzt.
 */
function profile_field_display(string $fieldKey, array $profileUser): ?string {
    $schema = profile_field_schema();
    if (!isset($schema[$fieldKey])) return null;
    $def = $schema[$fieldKey];
    $raw = $profileUser[$def['column']] ?? null;

    if ($raw === null || $raw === '') return null;

    return match ($def['type']) {
        'select' => isset($def['options'][$raw]) ? t($def['options'][$raw]) : $raw,
        'date'   => profile_format_birthday($raw),
        default  => (string) $raw,
    };
}

/**
 * Formatiert Geburtstag: "24.05.1985" oder nur "24.05." (wenn User Jahr verbergen will).
 * (Aktuell: immer mit Jahr. „Jahr verbergen"-Option könnte später dazukommen.)
 */
function profile_format_birthday(string $iso): string {
    $ts = strtotime($iso);
    if (!$ts) return $iso;
    return date('d.m.Y', $ts);
}

/**
 * Liefert nur die sichtbaren Felder eines Profils (mit ihrem Anzeigewert).
 * Für die View komfortabel: ['gender' => ['label' => '...', 'value' => '...', 'icon' => '...'], ...]
 */
function profile_visible_fields(array $profileUser, ?array $viewer): array {
    $schema = profile_field_schema();
    $out = [];
    foreach ($schema as $key => $def) {
        if (!profile_field_is_visible($key, $profileUser, $viewer)) continue;
        $value = profile_field_display($key, $profileUser);
        if ($value === null) continue;  // leeres Feld → nicht anzeigen
        $out[$key] = [
            'icon'  => $def['icon'],
            'label' => t($def['label']),
            'value' => $value,
            'type'  => $def['type'],
        ];
    }
    return $out;
}

/**
 * Rendert die Profile-Field-Inputs für die Settings-View als HTML.
 * Damit das Template (settings.tpl.html) eine einfache Funktion aufrufen kann
 * statt die ganze Logik im Template zu haben (Engine hat kein loop.key).
 */
function render_profile_field_inputs(array $profile, array $privacy): string {
    $schema = profile_field_schema();
    $csrfToken = csrf_token();
    ob_start();
    foreach ($schema as $key => $def) {
        $val  = $profile[$def['column']] ?? '';
        $priv = $privacy[$key] ?? 'public';
        $keyE = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
        ?>
        <div class="form-row profile-field-row">
          <div class="profile-field-main">
            <label><?= $def['icon'] ?> <?= htmlspecialchars(t($def['label']), ENT_QUOTES, 'UTF-8') ?></label>
            <?php if ($def['type'] === 'select'): ?>
              <select name="profile_<?= $keyE ?>">
                <?php foreach ($def['options'] as $optVal => $optLabel): ?>
                  <option value="<?= htmlspecialchars($optVal, ENT_QUOTES, 'UTF-8') ?>"
                          <?= (string) $val === (string) $optVal ? 'selected' : '' ?>>
                    <?= htmlspecialchars(t($optLabel), ENT_QUOTES, 'UTF-8') ?>
                  </option>
                <?php endforeach; ?>
              </select>
            <?php elseif ($def['type'] === 'date'): ?>
              <input type="date" name="profile_<?= $keyE ?>"
                     value="<?= htmlspecialchars((string) ($val ?: ''), ENT_QUOTES, 'UTF-8') ?>"
                     max="<?= date('Y-m-d') ?>">
            <?php elseif ($def['type'] === 'textarea'): ?>
              <?php if ($key === 'about'): ?>
                <?php /* v0.18.53: "Über mich" nutzt den vollen BBCode-Editor (inkl. Farben) */ ?>
                <?= render_editor(
                      'about_editor',
                      'profile_' . $key,
                      (string) ($val ?? ''),
                      t($def['placeholder'] ?? 'profile_about_placeholder'),
                      false,
                      false
                    ) ?>
              <?php else: ?>
                <textarea name="profile_<?= $keyE ?>" rows="3"
                          placeholder="<?= htmlspecialchars(isset($def['placeholder']) ? t($def['placeholder']) : '', ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ($val ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
              <?php endif; ?>
            <?php else: ?>
              <input type="text" name="profile_<?= $keyE ?>"
                     value="<?= htmlspecialchars((string) ($val ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                     maxlength="<?= (int) ($def['max'] ?? 100) ?>"
                     placeholder="<?= htmlspecialchars(isset($def['placeholder']) ? t($def['placeholder']) : '', ENT_QUOTES, 'UTF-8') ?>">
            <?php endif; ?>
          </div>
          <div class="profile-field-privacy">
            <label style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.4px;margin-bottom:6px">
              Sichtbar für
            </label>
            <select name="privacy_<?= $keyE ?>">
              <option value="public"  <?= $priv === 'public'  ? 'selected' : '' ?>>🌐 Öffentlich</option>
              <option value="members" <?= $priv === 'members' ? 'selected' : '' ?>>🔒 Mitglieder</option>
              <option value="private" <?= $priv === 'private' ? 'selected' : '' ?>>🔐 Nur ich</option>
            </select>
          </div>
        </div>
        <?php
    }
    return ob_get_clean();
}

/**
 * Helper für Template: kann sich der User löschen? (boolean direkt)
 * Wrappt can_user_delete_self() das ein Array zurückgibt.
 */
function can_user_delete_self_ok(int $userId): bool {
    if (!function_exists('can_user_delete_self')) return false;
    $r = can_user_delete_self($userId);
    return !empty($r['ok']);
}

/**
 * Helper für Template: Backup-Codes als ein-pro-Zeile-String escapen
 * (für data-Attribut + Clipboard-API).
 */
function join_codes(array $codes): string {
    return htmlspecialchars(implode("\n", $codes), ENT_QUOTES, 'UTF-8');
}
