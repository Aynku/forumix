<?php
// admin/index.php
define('FORUM_RUNNING', true);
require_once __DIR__ . '/../bootstrap.php';
require_admin();

$action = $_GET['action'] ?? 'dashboard';

// ─── AKTIONEN ─────────────────────────────────────────────────────

if ($action === 'add_cat' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf(post_str('csrf'))) error_page(400, t('adm_sicherheits_token_ungueltig'), t('adm_bitte_lade_die_seite'));
    db_execute('INSERT INTO categories (title, position) VALUES (?, ?)',
        [post_str('title'), post_int('position')]);
    flash(t('adm_kategorie_angelegt'), 'success');
    redirect(FORUM_URL . '/admin/?action=categories');
}

if ($action === 'update_cat' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf(post_str('csrf'))) error_page(400, t('adm_sicherheits_token_ungueltig'), t('adm_bitte_lade_die_seite'));
    $catId = post_int('cat_id');
    if ($catId > 0) {
        db_execute('UPDATE categories SET title = ?, position = ? WHERE cat_id = ?',
            [post_str('title'), post_int('position'), $catId]);
        flash(t('adm_kategorie_aktualisiert'), 'success');
    }
    redirect(FORUM_URL . '/admin/?action=categories');
}

if ($action === 'delete_cat') {
    if (!verify_csrf($_GET['csrf'] ?? '')) error_page(400, t('adm_sicherheits_token_ungueltig'), t('adm_bitte_lade_die_seite'));
    $cid = get_int('id');

    // An der Kategorie haengen die Foren, an denen wiederum die Rechte je
    // Gruppe — und die haben keinen Fremdschluessel, bleiben also liegen.
    // Bei einer Kategorie mit fuenf Foren sind das gleich zehn Zeilen.
    try {
        db_execute('DELETE FROM board_permissions
                    WHERE board_id IN (SELECT board_id FROM boards WHERE cat_id = ?)', [$cid]);
    } catch (Exception $e) {}

    $weg = db_affected('DELETE FROM categories WHERE cat_id = ?', [$cid]);
    flash($weg > 0 ? t('adm_kategorie_geloescht') : t('adm_diese_kategorie_gibt_es'),
          $weg > 0 ? 'success' : 'info');
    redirect(FORUM_URL . '/admin/?action=categories');
}

if ($action === 'add_board' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf(post_str('csrf'))) error_page(400, t('adm_sicherheits_token_ungueltig'), t('adm_bitte_lade_die_seite'));

    // Optional: Passwort beim Anlegen direkt setzen
    $newPw = (string) ($_POST['new_password'] ?? '');
    $passwordHash = $newPw !== '' ? password_hash($newPw, PASSWORD_BCRYPT) : null;

    db_execute('INSERT INTO boards (cat_id, title, description, position, password_hash) VALUES (?, ?, ?, ?, ?)',
        [post_int('cat_id'), post_str('title'), post_str('description'), post_int('position'), $passwordHash]);

    flash($passwordHash ? t('adm_forum_angelegt_mit_passwort') : t('adm_forum_angelegt'), 'success');
    redirect(FORUM_URL . '/admin/?action=boards');
}

if ($action === 'delete_board') {
    if (!verify_csrf($_GET['csrf'] ?? '')) error_page(400, t('adm_sicherheits_token_ungueltig'), t('adm_bitte_lade_die_seite'));
    $bid = get_int('id');

    // Die Rechte je Gruppe haengen an keinem Fremdschluessel und bleiben
    // deshalb liegen, wenn das Forum verschwindet. Zeigen auf nichts mehr,
    // sammeln sich aber.
    try { db_execute('DELETE FROM board_permissions WHERE board_id = ?', [$bid]); }
    catch (Exception $e) {}

    $weg = db_affected('DELETE FROM boards WHERE board_id = ?', [$bid]);
    flash($weg > 0 ? t('adm_forum_geloescht') : t('adm_dieses_forum_gibt_es'),
          $weg > 0 ? 'success' : 'info');
    redirect(FORUM_URL . '/admin/?action=boards');
}

/* ── GRUPPEN ─────────────────────────────────────────────────────────
   Anlegen, umbenennen, loeschen, Mitglieder pflegen. Alles nur mit Token und
   nur fuer Admins — die Rechtepruefung des Adminbereichs gilt fuer die ganze
   Datei. */
if ($action === 'create_group' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf(post_str('csrf'))) error_page(400, t('adm_sicherheits_token_ungueltig'), t('adm_bitte_lade_die_seite'));
    try {
        group_create(post_str('name'), !empty($_POST['show_badge']));
        flash(t('adm_gruppe_angelegt'), 'success');
    } catch (Exception $e) {
        flash($e->getMessage(), 'error');
    }
    redirect(FORUM_URL . '/admin/?action=groups');
}

if ($action === 'update_group' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf(post_str('csrf'))) error_page(400, t('adm_sicherheits_token_ungueltig'), t('adm_bitte_lade_die_seite'));
    try {
        group_rename(post_int('group_id'), post_str('name'),
                     !empty($_POST['show_badge']), !empty($_POST['hide_rank']));
        flash(t('adm_gruppe_gespeichert'), 'success');
    } catch (Exception $e) {
        flash($e->getMessage(), 'error');
    }
    redirect(FORUM_URL . '/admin/?action=groups&group=' . post_int('group_id'));
}

if ($action === 'delete_group') {
    if (!verify_csrf($_GET['csrf'] ?? '')) error_page(400, t('adm_sicherheits_token_ungueltig'), t('adm_bitte_lade_die_seite'));
    try {
        group_delete(get_int('id'));
        flash(t('adm_gruppe_geloescht_die_rechte'), 'success');
    } catch (Exception $e) {
        flash($e->getMessage(), 'error');
    }
    redirect(FORUM_URL . '/admin/?action=groups');
}

if ($action === 'group_member_add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf(post_str('csrf'))) error_page(400, t('adm_sicherheits_token_ungueltig'), t('adm_bitte_lade_die_seite'));
    $gid = post_int('group_id');
    try {
        group_member_add($gid, post_int('user_id'));
        flash(t('adm_mitglied_eingetragen'), 'success');
    } catch (Exception $e) {
        flash($e->getMessage(), 'error');
    }
    redirect(FORUM_URL . '/admin/?action=groups&group=' . $gid);
}

if ($action === 'group_avatar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf(post_str('csrf'))) error_page(400, t('adm_sicherheits_token_ungueltig'), t('adm_bitte_lade_die_seite'));
    $gid = post_int('group_id');
    $ziel = FORUM_URL . '/admin/?action=groups&group=' . $gid;

    // Entfernen
    if (!empty($_POST['entfernen'])) {
        $g = group_by_id($gid);
        if ($g && !empty($g['avatar'])) {
            $pfad = rtrim(UPLOAD_DIR, '/') . '/gruppen/' . basename((string) $g['avatar']);
            if (is_file($pfad)) @unlink($pfad);
        }
        try { db_execute('UPDATE user_groups SET avatar = NULL WHERE group_id = ?', [$gid]); }
        catch (Exception $e) {}
        flash(t('adm_bild_entfernt'), 'success');
        redirect($ziel);
    }

    if (empty($_FILES['bild']) || $_FILES['bild']['error'] !== UPLOAD_ERR_OK) {
        flash(t('adm_keine_datei_angekommen'), 'error');
        redirect($ziel);
    }
    $datei = $_FILES['bild'];
    if (!is_uploaded_file($datei['tmp_name'])) {
        flash('Sicherheits-Abbruch: keine echte Upload-Datei.', 'error');
        redirect($ziel);
    }

    // Kein SVG, aus demselben Grund wie beim Rollen-Avatar: eine SVG-Datei
    // ist ein XML-Dokument und darf Skripte enthalten. Sie laege hier unter
    // einer festen Adresse in der eigenen Herkunft; wer sie oeffnet, fuehrt
    // das Skript im Namen des Forums aus.
    $erlaubt = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
    $mime = mime_content_type($datei['tmp_name']) ?: '';
    if (!isset($erlaubt[$mime])) {
        flash(t('adm_nur_png_jpg_oder_2'), 'error');
        redirect($ziel);
    }
    if ($datei['size'] > 512 * 1024) {
        flash(t('adm_datei_zu_gross_hoechstens'), 'error');
        redirect($ziel);
    }

    $ordner = rtrim(UPLOAD_DIR, '/') . '/gruppen/';
    if (!is_dir($ordner)) @mkdir($ordner, 0755, true);

    // Vorheriges Bild derselben Gruppe wegräumen, sonst bleibt es liegen.
    $g = group_by_id($gid);
    if ($g && !empty($g['avatar'])) {
        $alt = $ordner . basename((string) $g['avatar']);
        if (is_file($alt)) @unlink($alt);
    }

    $name = 'gruppe_' . $gid . '_' . bin2hex(random_bytes(5)) . '.' . $erlaubt[$mime];
    if (!move_uploaded_file($datei['tmp_name'], $ordner . $name)) {
        flash(t('adm_konnte_die_datei_nicht'), 'error');
        redirect($ziel);
    }
    @chmod($ordner . $name, 0644);

    try {
        db_execute('UPDATE user_groups SET avatar = ? WHERE group_id = ?', [$name, $gid]);
        flash(t('adm_bild_gespeichert'), 'success');
    } catch (Exception $e) {
        @unlink($ordner . $name);
        flash(t('adm_die_spalte_fehlt_bitte'), 'error');
    }
    redirect($ziel);
}

if ($action === 'group_member_remove') {
    if (!verify_csrf($_GET['csrf'] ?? '')) error_page(400, t('adm_sicherheits_token_ungueltig'), t('adm_bitte_lade_die_seite'));
    $gid = get_int('group');
    group_member_remove($gid, get_int('user'));
    flash(t('adm_mitglied_entfernt'), 'success');
    redirect(FORUM_URL . '/admin/?action=groups&group=' . $gid);
}

if ($action === 'update_board' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf(post_str('csrf'))) error_page(400, t('adm_sicherheits_token_ungueltig'), t('adm_bitte_lade_die_seite'));
    $bid = post_int('board_id');
    try {
        db_execute(
            'UPDATE boards SET cat_id = ?, title = ?, description = ?, position = ? WHERE board_id = ?',
            [post_int('cat_id'), post_str('title'), post_str('description'), post_int('position'), $bid]
        );

        // Sichtbarkeit und Gruppenrechte. Beides steht im selben Formular, also
        // wird es hier mitgeschrieben — und zwar nur, wenn die Matrix wirklich
        // mitgeschickt wurde: sonst wuerde ein anderes Formular, das nur Titel
        // und Position kennt, versehentlich alle Rechte auf "nichts" setzen.
        if (isset($_POST['rechte']) && is_array($_POST['rechte'])) {
            $sicht = post_str('visibility') === 'locked' ? 'locked' : 'hidden';
            db_execute('UPDATE boards SET visibility = ? WHERE board_id = ?', [$sicht, $bid]);

            $stufen = [];
            foreach ($_POST['rechte'] as $gid => $stufe) {
                $stufen[(int) $gid] = (string) $stufe;
            }

            // Feinrechte: unangehakte Kaestchen schickt der Browser gar nicht
            // mit, deshalb wird je Gruppe eine leere Liste vorbelegt. Fehlt
            // der Block ganz, laesst board_permissions_set() alles erlaubt.
            $fein = null;
            if (isset($_POST['fein']) && is_array($_POST['fein'])) {
                $fein = [];
                foreach (array_keys($stufen) as $gid) {
                    $haken = $_POST['fein'][$gid] ?? [];
                    $fein[$gid] = [];
                    foreach (array_keys(BRETT_FEINRECHTE) as $recht) {
                        $fein[$gid][$recht] = !empty($haken[$recht]);
                    }
                }
            }
            board_permissions_set($bid, $stufen, $fein ?? []);
        }

        // Passwort-Behandlung:
        //   - "remove_password" = explizit entfernen
        //   - Eingabefeld leer = nichts ändern (Passwort bleibt wie es ist)
        //   - Eingabefeld gefüllt = neues Passwort setzen
        if (!empty($_POST['remove_password'])) {
            board_set_password($bid, '');
            flash(t('adm_forum_aktualisiert_passwort_schutz'), 'success');
        } else {
            $newPw = (string) ($_POST['new_password'] ?? '');
            if ($newPw !== '') {
                board_set_password($bid, $newPw);
                flash(t('adm_forum_aktualisiert_neues_passwort'), 'success');
            } else {
                flash(t('adm_forum_aktualisiert'), 'success');
            }
        }
    } catch (Exception $e) {
        flash('Fehler: ' . $e->getMessage(), 'error');
    }
    redirect(FORUM_URL . '/admin/?action=boards');
}

if ($action === 'update_board_approval' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf(post_str('csrf'))) error_page(400, t('adm_sicherheits_token_ungueltig'), t('adm_bitte_lade_die_seite'));
    $bid = post_int('board_id');
    $reqThreads = isset($_POST['require_approval_threads']) ? 1 : 0;
    $reqReplies = isset($_POST['require_approval_replies']) ? 1 : 0;
    try {
        db_execute('UPDATE boards SET require_approval_threads = ?, require_approval_replies = ? WHERE board_id = ?',
            [$reqThreads, $reqReplies, $bid]);
        flash(t('adm_freigabe_einstellungen_gespeichert'), 'success');
    } catch (Exception $e) {
        flash('Fehler: ' . $e->getMessage(), 'error');
    }
    redirect(FORUM_URL . '/admin/?action=boards');
}

if ($action === 'recalc_ranks') {
    if (!verify_csrf($_GET['csrf'] ?? '')) error_page(400, t('adm_sicherheits_token_ungueltig'), t('adm_bitte_lade_die_seite'));
    $n = recalculate_all_ranks();
    flash($n . t('adm_raenge_neu_berechnet'), 'success');
    redirect(FORUM_URL . '/admin/?action=ranks');
}

// ─── EINSTELLUNGEN SPEICHERN ──────────────────────────────────────
if ($action === 'save_settings' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf(post_str('csrf'))) error_page(400, t('adm_sicherheits_token_ungueltig'), t('adm_bitte_lade_die_seite'));

    // v0.18.49: Sub-Sektion-Marker — welche Settings-Sektion wurde abgesendet?
    // Ohne Marker = altes Verhalten (alle Felder). Mit Marker = nur die Felder dieser Sektion.
    $section = post_str('settings_section', '');  // 'general', 'email', 'registration', 'maintenance_mode'

    $sectionFields = [
        // Ohne Branding: Name, Logo, Slogan, Akzentfarbe, Standard-Theme,
        // Fusszeile und Custom CSS liegen im Designbereich und werden von
        // save_design geschrieben. Hier standen sie nur noch als Karteileichen.
        'general' => [
            'forum_description',
            'forum_default_lang',
            'cookie_banner_enabled', 'cookie_banner_text',
            'min_post_length', 'max_post_length',
            'edit_window_minutes',
            'posts_per_page', 'threads_per_page',
            'warning_ban_threshold', 'notify_mods_on_report', 'show_ips_to_mods',
            'post_cooldown_seconds',
            'rate_limit_login', 'rate_limit_register',
            'bot_detection_level',
            'captcha_enabled', 'captcha_threshold', 'captcha_min_time',
        ],
        'email' => [
            'smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass',
            'smtp_encryption', 'smtp_from', 'smtp_from_name',
        ],
        'registration' => [
            'registration_enabled', 'email_verification_required',
            'registration_approval_required', 'registration_terms_required',
            'min_username_length', 'min_password_length',
        ],
        'maintenance_mode' => [
            'maintenance_mode', 'maintenance_message',
        ],
        // Eigener Abschnitt: Der Modulbezug ist die einzige Stelle, an der
        // dieses Forum einen fremden Server aufruft. Ein Schalter dafuer
        // gehoert nicht zwischen Seitentitel und Zeitzone.
        'module' => [
            'module_quelle_an',
        ],
    ];

    // Wenn keine Sektion angegeben → altes Verhalten (alle Felder, Kompatibilität)
    if ($section === '' || !isset($sectionFields[$section])) {
        $allowed = array_merge(
            $sectionFields['general'],
            $sectionFields['email'],
            $sectionFields['registration'],
            $sectionFields['maintenance_mode'],
            $sectionFields['module']
        );
    } else {
        $allowed = $sectionFields[$section];
    }

    // Nur Ankreuzfelder gehoeren hierher: die schicken nichts, wenn sie leer
    // sind, deshalb zaehlt allein ihre Anwesenheit. captcha_enabled stand hier
    // faelschlich mit drin, ist im Formular aber ein Auswahlfeld — und das
    // sendet immer einen Wert. "Aus" kam dadurch nie an.
    $booleans = [
        'registration_enabled', 'maintenance_mode', 'show_ips_to_mods',
        'email_verification_required', 'notify_mods_on_report',
        'cookie_banner_enabled',
        'registration_approval_required',
        'registration_terms_required',
        'module_quelle_an',
    ];

    // Felder, die im Formular absichtlich leer bleiben, wenn sich nichts
    // aendern soll — sonst loescht ein Speichern das gesetzte Passwort. Das
    // Feld traegt kein value-Attribut, damit das Passwort nicht im Quelltext
    // der Seite steht.
    $keepIfEmpty = ['smtp_pass'];

    // Steht der Absender fest in der config.php, ist das Feld im Formular
    // gesperrt. Das allein reicht nicht: gesperrt ist es nur im Browser, und
    // ein Formular laesst sich auch ohne Browser abschicken. Deshalb faellt
    // der Wert hier heraus, bevor irgendetwas gespeichert wird.
    $festeAbsender = array_filter([
        'smtp_from'      => defined('FORUM_MAIL_FROM'),
        'smtp_from_name' => defined('FORUM_MAIL_FROM_NAME'),
    ]);
    $allowed = array_diff($allowed, array_keys($festeAbsender));

    foreach ($allowed as $key) {
        if (in_array($key, $booleans, true)) {
            set_setting($key, isset($_POST[$key]) ? '1' : '0');
        } elseif (array_key_exists($key, $_POST)) {
            $val = trim((string) $_POST[$key]);
            // Mailfelder duerfen keine Zeilenumbrueche enthalten — sonst haengt
            // ein Absendername eine eigene Kopfzeile an die ausgehende Post.
            // lib/email.php saeubert ebenfalls; hier wird es abgewiesen, damit
            // gar nichts Unbrauchbares in den Einstellungen landet.
            if (str_starts_with($key, 'smtp_') && preg_match('/[\r\n\0]/', $val)) {
                flash(t('adm_zeilenumbrueche_sind_in_mail'), 'error');
                continue;
            }
            if ($val === '' && in_array($key, $keepIfEmpty, true)) continue;
            // Das SMTP-Passwort geht verschluesselt in die Datenbank, sofern
            // ein Schluessel in der config.php steht. Ohne Schluessel bleibt
            // es wie bisher im Klartext — siehe lib/crypto.php.
            set_setting($key, $key === 'smtp_pass' ? forum_encrypt($val) : $val);
        }
    }

    // ── SMTP-Verbindungs-Combo: ein Feld → smtp_port + smtp_encryption
    if (array_key_exists('smtp_connection', $_POST)) {
        $conn = $_POST['smtp_connection'];
        switch ($conn) {
            case 'ssl':
                set_setting('smtp_encryption', 'ssl');
                set_setting('smtp_port', '465');
                break;
            case 'tls2525':
                set_setting('smtp_encryption', 'tls');
                set_setting('smtp_port', '2525');
                break;
            case 'none':
                set_setting('smtp_encryption', 'none');
                set_setting('smtp_port', '25');
                break;
            case 'tls':
            default:
                set_setting('smtp_encryption', 'tls');
                set_setting('smtp_port', '587');
                break;
        }
    }
    $cu = current_user();
    db_execute('INSERT INTO mod_log (mod_id, action, target_type, target_id) VALUES (?, ?, ?, ?)',
        [$cu['user_id'], 'update_settings', 'setting', 0]);
    flash(t('adm_einstellungen_gespeichert'), 'success');
    // v0.18.49: Zurück zur richtigen Sektion redirecten
    $redirectAction = ($section === '' || !isset($sectionFields[$section])) ? 'settings' : $section;
    redirect(FORUM_URL . '/admin/?action=' . $redirectAction);
}

// ─── IP-BANN ──────────────────────────────────────────────────────
if ($action === 'ban_ip') {
    $csrf = $_GET['csrf'] ?? post_str('csrf');
    if (!verify_csrf($csrf)) error_page(400, t('adm_sicherheits_token_ungueltig'), t('adm_bitte_lade_die_seite'));

    $ip      = trim($_GET['ip'] ?? post_str('ip'));
    $reason  = post_str('reason');
    $expires = post_str('expires');

    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        flash(t('adm_ungueltige_ip_adresse') . $ip, 'error');
    } else {
        $expiresAt = null;
        if ($expires) {
            $ts = strtotime($expires);
            if ($ts) $expiresAt = date('Y-m-d H:i:s', $ts);
        }
        $cu = current_user();
        db_execute(
            'INSERT INTO ip_bans (ip, reason, banned_by, expires_at) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE reason = VALUES(reason),
                                     expires_at = VALUES(expires_at),
                                     banned_by  = VALUES(banned_by)',
            [$ip, $reason, $cu['user_id'], $expiresAt]
        );
        db_execute('INSERT INTO mod_log (mod_id, action, target_type, target_id, reason) VALUES (?, ?, ?, ?, ?)',
            [$cu['user_id'], 'ban_ip', 'ip', 0, $ip . ($reason ? ' – ' . $reason : '')]);
        flash('IP ' . $ip . ' gesperrt.', 'success');
    }
    redirect(FORUM_URL . '/admin/?action=ipbans');
}

if ($action === 'unban_ip') {
    if (!verify_csrf($_GET['csrf'] ?? '')) error_page(400, t('adm_sicherheits_token_ungueltig'), t('adm_bitte_lade_die_seite'));
    db_execute('DELETE FROM ip_bans WHERE ban_id = ?', [get_int('id')]);
    flash(t('adm_ip_bann_aufgehoben'), 'success');
    redirect(FORUM_URL . '/admin/?action=ipbans');
}

// ─── WARTUNGS-TOOLS ───────────────────────────────────────────────
if ($action === 'maintenance_action' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf(post_str('csrf'))) error_page(400, t('adm_sicherheits_token_ungueltig'), t('adm_bitte_lade_die_seite'));
    $task = post_str('task');

    if ($task === 'recount_stats') {
        foreach (db_fetchAll('SELECT board_id FROM boards') as $b) {
            $tc = db_count('SELECT COUNT(*) FROM threads WHERE board_id = ?', [$b['board_id']]);
            $pc = db_count('SELECT COUNT(*) FROM posts p JOIN threads t ON t.thread_id=p.thread_id WHERE t.board_id = ?', [$b['board_id']]);
            db_execute('UPDATE boards SET thread_count = ?, post_count = ? WHERE board_id = ?',
                [$tc, $pc, $b['board_id']]);
        }
        foreach (db_fetchAll('SELECT thread_id FROM threads') as $t) {
            $rc = db_count('SELECT COUNT(*) FROM posts WHERE thread_id = ?', [$t['thread_id']]);
            db_execute('UPDATE threads SET reply_count = GREATEST(0, ? - 1) WHERE thread_id = ?',
                [$rc, $t['thread_id']]);
        }
        foreach (db_fetchAll('SELECT user_id FROM users') as $u) {
            $pc = db_count('SELECT COUNT(*) FROM posts WHERE user_id = ?', [$u['user_id']]);
            db_execute('UPDATE users SET post_count = ? WHERE user_id = ?', [$pc, $u['user_id']]);
        }
        flash(t('adm_alle_statistiken_neu_berechnet'), 'success');

    } elseif ($task === 'optimize_tables') {
        try {
            // Alle Tabellen dynamisch aus der DB holen
            $stmt = DB::get()->query('SHOW TABLES');
            $allTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $stmt->closeCursor();

            $optimized = 0;
            $failed = [];
            foreach ($allTables as $t) {
                try {
                    // OPTIMIZE liefert ein Result-Set zurück – das muss konsumiert + geschlossen werden
                    $s = DB::get()->query('OPTIMIZE TABLE `' . $t . '`');
                    if ($s) {
                        $s->fetchAll();
                        $s->closeCursor();
                    }
                    $optimized++;
                } catch (Exception $e) {
                    $failed[] = $t;
                }
            }

            if (empty($failed)) {
                flash($optimized . t('adm_tabellen_optimiert'), 'success');
            } else {
                flash($optimized . t('adm_tabellen_optimiert_fehlgeschlagen') . implode(', ', $failed), 'warning');
            }
        } catch (Exception $e) {
            flash('Fehler: ' . $e->getMessage(), 'error');
        }

    } elseif ($task === 'cleanup_ip_logs') {
        $days = max(7, post_int('cleanup_days', 90));
        $n = db_count('SELECT COUNT(*) FROM user_ip_log WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)', [$days]);
        db_execute('DELETE FROM user_ip_log WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)', [$days]);
        flash($n . t('adm_ip_log_eintraege_geloescht'), 'success');

    } elseif ($task === 'cleanup_expired_ipbans') {
        $n = db_count('SELECT COUNT(*) FROM ip_bans WHERE expires_at IS NOT NULL AND expires_at < NOW()');
        db_execute('DELETE FROM ip_bans WHERE expires_at IS NOT NULL AND expires_at < NOW()');
        flash($n . ' abgelaufene IP-Banns entfernt.', 'success');
    }
    redirect(FORUM_URL . '/admin/?action=maintenance');
}

// ─── DESIGN: SPEICHERN ────────────────────────────────────────────
if ($action === 'save_design' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf(post_str('csrf'))) error_page(400, t('adm_sicherheits_token_ungueltig'), t('adm_bitte_lade_die_seite'));

    $themeKeys = [
        'theme_color_bg_body', 'theme_color_bg_surface', 'theme_color_bg_surface2',
        'theme_color_text_base', 'theme_color_text_muted', 'theme_color_text_faint',
        'theme_color_border', 'theme_color_border_strong',
        'theme_color_success', 'theme_color_warning', 'theme_color_danger',
        'theme_color_bg_body_light', 'theme_color_bg_surface_light', 'theme_color_bg_surface2_light',
        'theme_color_text_base_light', 'theme_color_text_muted_light', 'theme_color_text_faint_light',
        'theme_color_border_light', 'theme_color_border_strong_light',
        'theme_font_family', 'theme_font_custom', 'theme_font_size_base',
        'theme_radius_preset', 'theme_density', 'theme_max_width',
        'theme_header_style',
    ];

    foreach ($themeKeys as $k) {
        if (!isset($_POST[$k])) continue;
        $v = trim((string) $_POST[$k]);
        // Hex-Validierung für Farben
        if (str_starts_with($k, 'theme_color_') && $k !== 'theme_color_accent') {
            $v = ltrim($v, '#');
            if (!preg_match('/^[a-fA-F0-9]{6}$/', $v)) continue;
        }
        set_setting($k, $v);
    }

    // ── Identitäts-Felder (ehemals im Settings-Bereich)
    $identityKeys = [
        'forum_name', 'forum_slogan', 'forum_logo',
        'forum_accent_color', 'forum_default_theme',
    ];
    foreach ($identityKeys as $k) {
        if (array_key_exists($k, $_POST)) {
            set_setting($k, trim((string) $_POST[$k]));
        }
    }

    // ── Layout-Felder (Footer)
    $layoutKeys = ['forum_footer_text', 'forum_footer_links', 'source_url'];
    foreach ($layoutKeys as $k) {
        if (array_key_exists($k, $_POST)) {
            set_setting($k, trim((string) $_POST[$k]));
        }
    }

    // ── Custom CSS
    if (array_key_exists('forum_custom_css', $_POST)) {
        set_setting('forum_custom_css', trim((string) $_POST['forum_custom_css']));
    }

    // Preview-Session leeren – Settings sind jetzt persistent
    unset($_SESSION['theme_preview']);

    flash(t('adm_design_gespeichert'), 'success');
    redirect(FORUM_URL . '/admin/?action=design');
}

// ─── DESIGN: LIVE-PREVIEW SETZEN (AJAX) ───────────────────────────
if ($action === 'preview_design' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    if (!verify_csrf(post_str('csrf'))) {
        echo json_encode(['ok' => false]); exit;
    }
    $_SESSION['theme_preview'] = $_POST['theme'] ?? [];
    echo json_encode(['ok' => true]);
    exit;
}

// ─── DESIGN: PREVIEW LÖSCHEN ──────────────────────────────────────
if ($action === 'clear_preview') {
    if (!verify_csrf($_GET['csrf'] ?? '')) error_page(400, t('adm_sicherheits_token_ungueltig'), t('adm_bitte_lade_die_seite'));
    unset($_SESSION['theme_preview']);
    redirect(FORUM_URL . '/admin/?action=design');
}

// ─── TEST-MAIL VERSENDEN (AJAX) ──────────────────────────────────
if ($action === 'send_test_mail' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    if (!verify_csrf(post_str('csrf'))) {
        echo json_encode(['ok' => false, 'error' => 'CSRF-Fehler']); exit;
    }
    $to = trim((string) ($_POST['to'] ?? ''));
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['ok' => false, 'error' => t('adm_ungueltige_e_mail_adresse')]); exit;
    }
    $forum  = forum_name();
    $body   = email_template(t('adm_test_mail_von') . $forum,
        '<p>Hallo!</p>'
      . '<p>Diese Mail wurde aus dem Admin-Bereich als <strong>Test</strong> gesendet, '
      . 'um deine SMTP-Einstellungen zu prüfen.</p>'
      . '<p>Wenn du das hier liest, funktioniert alles. 🎉</p>'
      . '<p style="color:#888;font-size:12px;margin-top:20px">'
      . t('adm_gesendet_am') . date('d.m.Y H:i:s') . ' Uhr.</p>'
    );
    $ok = send_email($to, t('adm_test_mail_von') . $forum, $body);
    if ($ok) {
        echo json_encode([
            'ok'  => true,
            'msg' => t('adm_server_hat_die_mail') . $to . ' angenommen. Wenn sie nicht ankommt: Spam-Ordner prüfen, '
                   . 'sonst evtl. Bounce-Mail im Postfach des Absenders ansehen (zeigt den genauen Fehler).'
        ]);
    } else {
        echo json_encode(['ok' => false, 'error' => t('adm_versand_fehlgeschlagen_pruefe_smtp')]);
    }
    exit;
}

// ─── DESIGN: AUF DEFAULTS ZURÜCKSETZEN ───────────────────────────
if ($action === 'reset_design' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf(post_str('csrf'))) error_page(400, t('adm_sicherheits_token_ungueltig'), t('adm_bitte_lade_die_seite'));
    // Dieselbe Liste, die auch ein frisches Forum bekommt — sonst stellt
    // "Zuruecksetzen" irgendwann eine Palette her, die es nirgends sonst gibt.
    foreach (theme_defaults() as $k => $v) set_setting($k, $v);
    unset($_SESSION['theme_preview']);
    flash(t('adm_design_auf_standardwerte_zurueckgesetzt'), 'success');
    redirect(FORUM_URL . '/admin/?action=design');
}

// ─── ROLLEN-AVATARE: UPLOAD / DELETE ──────────────────────────────
if ($action === 'upload_role_avatar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf(post_str('csrf'))) error_page(400, t('adm_sicherheits_token_ungueltig'), t('adm_bitte_lade_die_seite'));
    $role = post_str('role');
    if (!in_array($role, ['owner', 'admin', 'mod'], true)) {
        flash(t('adm_ungueltige_rolle'), 'error');
        redirect(FORUM_URL . '/admin/?action=settings');
    }
    if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        flash(t('adm_datei_upload_fehlgeschlagen'), 'error');
        redirect(FORUM_URL . '/admin/?action=settings');
    }
    $file = $_FILES['image'];
    // Kein SVG. Eine SVG-Datei ist ein XML-Dokument und darf Skripte
    // enthalten; sie liegt hier unter einer festen, vorhersagbaren Adresse in
    // der eigenen Herkunft (assets/role_admin_custom.svg). Wer sie oeffnet,
    // fuehrt das Skript im Namen des Forums aus. Der Gewinn — ein
    // beliebig skalierbares Abzeichen — wiegt das nicht auf.
    $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
    $mime = mime_content_type($file['tmp_name']) ?: '';
    if (!isset($allowed[$mime])) {
        flash(t('adm_nur_png_jpg_oder'), 'error');
        redirect(FORUM_URL . '/admin/?action=settings');
    }
    if ($file['size'] > 2 * 1024 * 1024) {
        flash(t('adm_datei_zu_gross_max'), 'error');
        redirect(FORUM_URL . '/admin/?action=settings');
    }
    $assetsDir = __DIR__ . '/../assets/';
    // Vorherige Custom-Datei löschen (egal welche Endung)
    foreach (['png', 'jpg', 'jpeg', 'webp', 'svg'] as $ext) {
        $old = $assetsDir . 'role_' . $role . '_custom.' . $ext;
        if (file_exists($old)) @unlink($old);
    }
    $target = $assetsDir . 'role_' . $role . '_custom.' . $allowed[$mime];
    if (move_uploaded_file($file['tmp_name'], $target)) {
        flash(t('adm_rollen_avatar_gespeichert'), 'success');
    } else {
        flash(t('adm_konnte_datei_nicht_speichern'), 'error');
    }
    redirect(FORUM_URL . '/admin/?action=settings');
}

if ($action === 'delete_role_avatar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf(post_str('csrf'))) error_page(400, t('adm_sicherheits_token_ungueltig'), t('adm_bitte_lade_die_seite'));
    $role = post_str('role');
    if (!in_array($role, ['owner', 'admin', 'mod'], true)) {
        redirect(FORUM_URL . '/admin/?action=settings');
    }
    $assetsDir = __DIR__ . '/../assets/';
    $removed = false;
    foreach (['png', 'jpg', 'jpeg', 'webp', 'svg'] as $ext) {
        $f = $assetsDir . 'role_' . $role . '_custom.' . $ext;
        if (file_exists($f)) { @unlink($f); $removed = true; }
    }
    flash($removed ? t('adm_eigenes_bild_entfernt_standard') : t('adm_kein_eigenes_bild_gefunden'),
        $removed ? 'success' : 'info');
    redirect(FORUM_URL . '/admin/?action=settings');
}

// ─── MODUL-VERWALTUNG ─────────────────────────────────────────────
if ($action === 'module_toggle') {
    if (!verify_csrf($_GET['csrf'] ?? '')) error_page(400, t('adm_sicherheits_token_ungueltig'), t('adm_bitte_lade_die_seite'));
    $code = $_GET['code'] ?? '';
    $turnOn = ($_GET['enable'] ?? '0') === '1';
    if ($turnOn) {
        $r = enable_module($code);
        flash($r['ok'] ? t('adm_modul_aktiviert') : 'Fehler: ' . ($r['error'] ?? '?'), $r['ok'] ? 'success' : 'error');
    } else {
        $r = disable_module($code);
        flash($r['ok'] ? t('adm_modul_deaktiviert') : 'Fehler: ' . ($r['error'] ?? '?'), $r['ok'] ? 'success' : 'error');
    }
    redirect(FORUM_URL . '/admin/?action=modules');
}

// ─── MODUL ENTFERNEN ──────────────────────────────────────────────
//
// Nur der Inhaber, und nur was abgeschaltet ist. Die Datenbank bleibt
// unangetastet — siehe module_entfernen().
if ($action === 'module_entfernen' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? '')) {
        error_page(400, t('adm_sicherheits_token_ungueltig'), t('adm_bitte_lade_die_seite'));
    }
    if (!is_owner()) {
        error_page(403, t('adm_kein_zugriff'), t('adm_module_darf_nur_der_3'));
    }

    $code = (string) ($_POST['code'] ?? '');
    $name = available_modules()[$code]['name'] ?? $code;

    $r = module_entfernen($code);
    $tabellen = $r['tabellen'] ?? [];
    flash($r['ok']
        ? 'Modul „' . $name . '" entfernt: ' . (int) $r['geloescht'] . ' Dateien'
          . ($tabellen ? ' und ' . count($tabellen) . ' '
             . (count($tabellen) === 1 ? 'Tabelle' : 'Tabellen') . ' (' . implode(', ', $tabellen) . ')' : '')
          . '.'
        : t('adm_nicht_entfernt') . ($r['error'] ?? '?'),
        $r['ok'] ? 'success' : 'error');
    redirect(FORUM_URL . '/admin/?action=modules');
}

// ─── MODUL EINSPIELEN ─────────────────────────────────────────────
//
// Nur der Inhaber, nicht jeder Administrator: Hier landen PHP-Dateien auf dem
// Server, das ist ein Weg zur Codeausfuehrung. Dieselbe Grenze gilt beim
// Updatepaket, und aus demselben Grund.
if ($action === 'module_upload' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? '')) {
        error_page(400, t('adm_sicherheits_token_ungueltig'), t('adm_bitte_lade_die_seite'));
    }
    if (!is_owner()) {
        error_page(403, t('adm_kein_zugriff'), t('adm_module_darf_nur_der_2'));
    }

    require_once __DIR__ . '/../lib/module_package.php';

    $datei = $_FILES['modulpaket'] ?? null;
    if (!$datei || ($datei['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        flash(t('adm_es_wurde_keine_datei'), 'error');
        redirect(FORUM_URL . '/admin/?action=modules');
    }
    if (!is_uploaded_file($datei['tmp_name'])) {
        flash(t('adm_die_datei_kam_nicht'), 'error');
        redirect(FORUM_URL . '/admin/?action=modules');
    }

    $ergebnis = modulpaket_pruefen($datei['tmp_name']);
    if (empty($ergebnis['ok'])) {
        flash(t('adm_paket_abgelehnt') . ($ergebnis['error'] ?? 'unbekannter Grund'), 'error');
        redirect(FORUM_URL . '/admin/?action=modules');
    }

    $eingespielt = modulpaket_einspielen($ergebnis['zip'], $ergebnis['manifest'], $ergebnis['files']);
    $ergebnis['zip']->close();

    if (empty($eingespielt['ok'])) {
        flash(t('adm_einspielen_fehlgeschlagen') . ($eingespielt['error'] ?? '?'), 'error');
    } else {
        flash('Modul „' . ($ergebnis['manifest']['name'] ?? $eingespielt['code']) . '" '
            . ($ergebnis['manifest']['version'] ?? '') . ' eingespielt ('
            . (int) $eingespielt['geschrieben'] . t('adm_dateien_es_ist_noch'), 'success');
    }
    redirect(FORUM_URL . '/admin/?action=modules');
}

// ─── MODULE BEIM HERSTELLER NACHSCHLAGEN ──────────────────────────
//
// Nur auf Klick, nie im Hintergrund: Das ist der einzige Aufruf eines fremden
// Servers, den Forumix macht, und er soll erkennbar von einem Menschen
// ausgeloest sein.
if ($action === 'module_quelle' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? '')) {
        error_page(400, t('adm_sicherheits_token_ungueltig'), t('adm_bitte_lade_die_seite'));
    }
    if (!is_owner()) {
        error_page(403, t('adm_kein_zugriff'), t('adm_module_darf_nur_der'));
    }

    require_once __DIR__ . '/../lib/module_quelle.php';

    // Nachsehen, was es gibt.
    if (isset($_POST['suchen'])) {
        $liste = modulquelle_liste(true);
        flash($liste['ok']
            ? t('adm_liste_abgerufen') . count($liste['module']) . t('adm_module_verfuegbar')
            : t('adm_abruf_fehlgeschlagen') . ($liste['error'] ?? '?'),
            $liste['ok'] ? 'success' : 'error');
        redirect(FORUM_URL . '/admin/?action=modules');
    }

    // Eines davon holen und einspielen.
    $code = (string) ($_POST['code'] ?? '');
    $liste = modulquelle_liste();
    if (!$liste['ok']) {
        flash(t('adm_abruf_fehlgeschlagen') . ($liste['error'] ?? '?'), 'error');
        redirect(FORUM_URL . '/admin/?action=modules');
    }

    $eintrag = null;
    foreach ($liste['module'] as $m) {
        if (($m['code'] ?? '') === $code) { $eintrag = $m; break; }
    }
    if ($eintrag === null) {
        flash(t('adm_dieses_modul_steht_beim'), 'error');
        redirect(FORUM_URL . '/admin/?action=modules');
    }

    $geladen = modulquelle_paket_laden($eintrag);
    if (!$geladen['ok']) {
        flash(t('adm_laden_fehlgeschlagen') . ($geladen['error'] ?? '?'), 'error');
        redirect(FORUM_URL . '/admin/?action=modules');
    }

    // Ab hier derselbe Weg wie beim Hochladen von Hand — dieselben Pruefungen,
    // egal woher die Datei kommt.
    $geprueft = modulpaket_pruefen($geladen['datei']);
    if (empty($geprueft['ok'])) {
        @unlink($geladen['datei']);
        flash(t('adm_paket_abgelehnt') . ($geprueft['error'] ?? '?'), 'error');
        redirect(FORUM_URL . '/admin/?action=modules');
    }

    $eingespielt = modulpaket_einspielen($geprueft['zip'], $geprueft['manifest'], $geprueft['files']);
    $geprueft['zip']->close();
    @unlink($geladen['datei']);

    flash(empty($eingespielt['ok'])
        ? t('adm_einspielen_fehlgeschlagen') . ($eingespielt['error'] ?? '?')
        : 'Modul „' . ($geprueft['manifest']['name'] ?? $code) . '" '
          . ($geprueft['manifest']['version'] ?? '') . ' eingespielt.',
        empty($eingespielt['ok']) ? 'error' : 'success');
    redirect(FORUM_URL . '/admin/?action=modules');
}

// ─── SMILEY-VERWALTUNG ───────────────────────────────────────
if ($action === 'add_smiley' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? '')) error_page(400, t('adm_sicherheits_token_ungueltig'), t('adm_bitte_lade_die_seite'));
    $code  = trim($_POST['code']  ?? '');
    $emoji = trim($_POST['emoji'] ?? '');
    $desc  = trim($_POST['description'] ?? '');
    $picker = isset($_POST['is_in_picker']) ? 1 : 0;

    if ($code === '' || $emoji === '') {
        flash(t('adm_code_und_emoji_muessen'), 'error');
    } else {
        // Position bestimmen: nächste freie Stelle im Picker
        $pos = 0;
        if ($picker) {
            $pos = (int) db_count('SELECT COALESCE(MAX(position), 0) FROM smileys WHERE is_in_picker = 1') + 1;
        }
        try {
            db_execute(
                'INSERT INTO smileys (`code`, `emoji`, `description`, `is_in_picker`, `position`) VALUES (?, ?, ?, ?, ?)',
                [$code, $emoji, $desc, $picker, $pos]
            );
            smileys_cache_clear();
            flash('Smiley „' . $code . '" angelegt.', 'success');
        } catch (Exception $e) {
            flash('Fehler: ' . $e->getMessage(), 'error');
        }
    }
    redirect(FORUM_URL . '/admin/?action=smileys');
}

if ($action === 'update_smiley' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? '')) error_page(400, t('adm_sicherheits_token_ungueltig'), t('adm_bitte_lade_die_seite'));
    $id    = (int) ($_POST['smiley_id'] ?? 0);
    $code  = trim($_POST['code']  ?? '');
    $emoji = trim($_POST['emoji'] ?? '');
    $desc  = trim($_POST['description'] ?? '');
    $picker = isset($_POST['is_in_picker']) ? 1 : 0;

    if ($id && $code !== '' && $emoji !== '') {
        try {
            // Aktueller Status: war's vorher im Picker?
            $row = db_fetch('SELECT is_in_picker, position FROM smileys WHERE smiley_id = ?', [$id]);
            $pos = (int) ($row['position'] ?? 0);
            // Wenn neu in Picker hineinkommt → Position vergeben
            if ($picker && empty($row['is_in_picker'])) {
                $pos = (int) db_count('SELECT COALESCE(MAX(position), 0) FROM smileys WHERE is_in_picker = 1') + 1;
            } elseif (!$picker) {
                $pos = 0;
            }
            db_execute(
                'UPDATE smileys SET `code` = ?, `emoji` = ?, `description` = ?, `is_in_picker` = ?, `position` = ? WHERE smiley_id = ?',
                [$code, $emoji, $desc, $picker, $pos, $id]
            );
            smileys_cache_clear();
            flash(t('adm_smiley_aktualisiert'), 'success');
        } catch (Exception $e) {
            flash('Fehler: ' . $e->getMessage(), 'error');
        }
    }
    redirect(FORUM_URL . '/admin/?action=smileys');
}

if ($action === 'delete_smiley') {
    if (!verify_csrf($_GET['csrf'] ?? '')) error_page(400, t('adm_sicherheits_token_ungueltig'), t('adm_bitte_lade_die_seite'));
    $id = (int) ($_GET['id'] ?? 0);
    if ($id) {
        try {
            db_execute('DELETE FROM smileys WHERE smiley_id = ?', [$id]);
            smileys_cache_clear();
            flash(t('adm_smiley_geloescht'), 'success');
        } catch (Exception $e) {
            flash('Fehler: ' . $e->getMessage(), 'error');
        }
    }
    redirect(FORUM_URL . '/admin/?action=smileys');
}

if ($action === 'reorder_smileys' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? '')) error_page(400, t('adm_sicherheits_token_ungueltig'), t('adm_bitte_lade_die_seite'));
    $ids = $_POST['order'] ?? [];
    if (is_array($ids)) {
        $pos = 0;
        foreach ($ids as $id) {
            $pos++;
            try {
                db_execute(
                    'UPDATE smileys SET `position` = ? WHERE smiley_id = ? AND is_in_picker = 1',
                    [$pos, (int) $id]
                );
            } catch (Exception $e) {}
        }
        smileys_cache_clear();
    }
    // AJAX-freundliche Antwort
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}

// ─── RECHTLICHES (Impressum / Datenschutz) ──────────────────────
if ($action === 'save_legal' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? '')) error_page(400, t('adm_sicherheits_token_ungueltig'), t('adm_bitte_lade_die_seite'));
    set_setting('imprint_name',   trim($_POST['imprint_name']   ?? ''));
    set_setting('imprint_street', trim($_POST['imprint_street'] ?? ''));
    set_setting('imprint_city',   trim($_POST['imprint_city']   ?? ''));
    set_setting('imprint_email',  trim($_POST['imprint_email']  ?? ''));
    flash(t('adm_impressum_daten_gespeichert'), 'success');
    redirect(FORUM_URL . '/admin/?action=legal');
}

if ($action === 'save_page' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? '')) error_page(400, t('adm_sicherheits_token_ungueltig'), t('adm_bitte_lade_die_seite'));
    $slug    = $_POST['slug'] ?? '';
    $content = $_POST['content'] ?? '';
    if (!in_array($slug, ['imprint', 'privacy', 'terms'], true)) {
        flash(t('adm_unbekannte_seite'), 'error');
    } else {
        if (save_page($slug, $content)) {
            flash(t('adm_seite_gespeichert'), 'success');
        } else {
            flash(t('adm_speichern_fehlgeschlagen'), 'error');
        }
    }
    redirect(FORUM_URL . '/admin/?action=legal&page=' . urlencode($slug));
}

if ($action === 'save_template' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? '')) error_page(400, t('adm_sicherheits_token_ungueltig'), t('adm_bitte_lade_die_seite'));
    $name    = trim($_POST['name'] ?? '');
    $content = $_POST['content'] ?? '';
    $cu      = current_user();

    // STRENG: nur der Owner — genau wie upload_update. Der Template-Übersetzer
    // erzeugt aus {{ system("...") }} selbst PHP; template_content_is_safe()
    // blockt nur wörtliche <?php-Tags und kann das nicht verhindern. Ein
    // Template-Override läuft danach bei jedem Besucher.
    if (empty($cu['is_owner'])) {
        flash(t('adm_nur_der_owner_darf_2'), 'error');
        redirect(FORUM_URL . '/admin/?action=templates');
    }

    // Whitelist: nur Templates die als Datei existieren dürfen geändert werden.
    // template_name_gueltig() prueft zusaetzlich den Namen selbst und dass der
    // aufgeloeste Pfad wirklich in views_new/ liegt — file_exists() allein
    // laesst "../../etwas" durch, wenn es die Datei dort gibt.
    if (!template_name_gueltig($name)) {
        flash(t('adm_unbekanntes_template'), 'error');
    } elseif (!template_content_is_safe($content)) {
        flash(t('adm_template_enthaelt_php_tags'), 'error');
    } else {
        if (save_template_override($name, $content, $cu ? (int) $cu['user_id'] : null)) {
            flash(t('adm_template_gespeichert'), 'success');
        } else {
            flash(t('adm_speichern_fehlgeschlagen'), 'error');
        }
    }
    redirect(FORUM_URL . '/admin/?action=templates&edit=' . urlencode($name));
}

if ($action === 'reset_template' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? '')) error_page(400, t('adm_sicherheits_token_ungueltig'), t('adm_bitte_lade_die_seite'));
    $name = trim($_POST['name'] ?? '');
    if ($name && delete_template_override($name)) {
        flash(t('adm_template_auf_original_zurueckgesetzt'), 'success');
    } else {
        flash(t('adm_zuruecksetzen_fehlgeschlagen'), 'error');
    }
    redirect(FORUM_URL . '/admin/?action=templates&edit=' . urlencode($name));
}

if ($action === 'ack_template_update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? '')) error_page(400, t('adm_sicherheits_token_ungueltig'), t('adm_bitte_lade_die_seite'));
    $name = trim($_POST['name'] ?? '');
    if ($name && acknowledge_template_override_update($name)) {
        flash('Update-Hinweis ausgeblendet. Dein Override bleibt aktiv.', 'success');
    } else {
        flash(t('adm_aktion_fehlgeschlagen'), 'error');
    }
    redirect(FORUM_URL . '/admin/?action=templates&edit=' . urlencode($name));
}

if ($action === 'approve_user_action' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? '')) error_page(400, t('adm_sicherheits_token_ungueltig'), t('adm_bitte_lade_die_seite'));
    $uid = (int) ($_POST['user_id'] ?? 0);
    $cu  = current_user();
    if ($uid > 0 && $cu && approve_user($uid, (int) $cu['user_id'])) {
        flash(t('adm_benutzer_freigegeben_eine_benachrichtigungs'), 'success');
    } else {
        flash(t('adm_freigabe_fehlgeschlagen'), 'error');
    }
    redirect(FORUM_URL . '/admin/?action=approvals_users');
}

if ($action === 'reject_user_action' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? '')) error_page(400, t('adm_sicherheits_token_ungueltig'), t('adm_bitte_lade_die_seite'));
    $uid    = (int) ($_POST['user_id'] ?? 0);
    $reason = trim((string) ($_POST['reason'] ?? ''));
    $cu     = current_user();
    if ($reason === '') {
        flash(t('adm_bitte_eine_begruendung_angeben'), 'error');
        redirect(FORUM_URL . '/admin/?action=approvals_users');
    }
    if ($uid > 0 && $cu && reject_user($uid, (int) $cu['user_id'], $reason)) {
        flash(t('adm_benutzer_abgelehnt_eine_begruendungs'), 'success');
    } else {
        flash(t('adm_ablehnung_fehlgeschlagen'), 'error');
    }
    redirect(FORUM_URL . '/admin/?action=approvals_users');
}

// ─── EINLADUNGEN ──────────────────────────────────────────────────
if ($action === 'invitation_create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? '')) error_page(400, t('adm_sicherheits_token_ungueltig'), t('adm_bitte_lade_die_seite'));
    $cu = current_user();
    if (!$cu) redirect(FORUM_URL . '/admin/');

    $email    = trim((string) ($_POST['email'] ?? ''));
    $note     = trim((string) ($_POST['note'] ?? ''));
    $sendMail = !empty($_POST['send_mail']);
    $days     = max(1, min(365, (int) ($_POST['valid_days'] ?? 7)));

    $inv = invitation_create((int) $cu['user_id'],
                             $email !== '' ? $email : null,
                             $note  !== '' ? $note  : null,
                             $days);
    if (!$inv) {
        flash(t('adm_einladung_konnte_nicht_erstellt'), 'error');
        redirect(FORUM_URL . '/admin/?action=invitations');
    }

    if ($sendMail && $email !== '') {
        $ok = invitation_send_email($inv, $email);
        if ($ok) {
            flash(t('adm_einladung_erstellt_und_mail') . $email . ' versendet. Code: ' . $inv['code'], 'success');
        } else {
            flash(t('adm_einladung_erstellt_aber_mail') . $inv['code'] . ' (manuell weitergeben)', 'warning');
        }
    } else {
        flash(t('adm_einladung_erstellt_code') . $inv['code'], 'success');
    }
    redirect(FORUM_URL . '/admin/?action=invitations');
}

if ($action === 'invitation_resend' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? '')) error_page(400, t('adm_sicherheits_token_ungueltig'), t('adm_bitte_lade_die_seite'));
    $iid = (int) ($_POST['invitation_id'] ?? 0);
    $override = trim((string) ($_POST['email_override'] ?? ''));
    $inv = db_fetch('SELECT * FROM invitations WHERE invitation_id = ?', [$iid]);
    if (!$inv) {
        flash(t('adm_einladung_nicht_gefunden'), 'error');
    } elseif (!empty($inv['used_by']) || !empty($inv['revoked_at']) || strtotime($inv['expires_at']) < time()) {
        flash(t('adm_einladung_ist_nicht_mehr'), 'error');
    } else {
        $targetEmail = $override !== '' ? $override : ($inv['email'] ?? '');
        if ($targetEmail === '') {
            flash(t('adm_keine_e_mail_adresse'), 'error');
        } else {
            $ok = invitation_send_email($inv, $targetEmail);
            flash($ok ? t('adm_mail_erneut_versendet_an') . $targetEmail : t('adm_mail_versand_fehlgeschlagen'), $ok ? 'success' : 'error');
        }
    }
    redirect(FORUM_URL . '/admin/?action=invitations');
}

if ($action === 'invitation_revoke' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? '')) error_page(400, t('adm_sicherheits_token_ungueltig'), t('adm_bitte_lade_die_seite'));
    $iid = (int) ($_POST['invitation_id'] ?? 0);
    $cu  = current_user();
    if ($iid > 0 && $cu && invitation_revoke($iid, (int) $cu['user_id'])) {
        flash(t('adm_einladung_widerrufen_der_code'), 'success');
    } else {
        flash(t('adm_widerruf_fehlgeschlagen_einladung_schon'), 'error');
    }
    redirect(FORUM_URL . '/admin/?action=invitations');
}

if ($action === 'create_backup' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? '')) error_page(400, t('adm_sicherheits_token_ungueltig'), t('adm_bitte_lade_die_seite'));
    $type = $_POST['type'] ?? 'db';
    $note = trim($_POST['note'] ?? '');

    $result = null;
    switch ($type) {
        case 'db':    $result = backup_database($note ?: null); break;
        case 'files': $result = backup_files($note ?: null);    break;
        case 'full':  $result = backup_full($note ?: null);     break;
    }

    if ($result) {
        flash("Backup erstellt: {$result}", 'success');
    } else {
        flash(t('adm_backup_erstellung_fehlgeschlagen_schau'), 'error');
    }
    redirect(FORUM_URL . '/admin/?action=backups');
}

if ($action === 'delete_backup' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? '')) error_page(400, t('adm_sicherheits_token_ungueltig'), t('adm_bitte_lade_die_seite'));
    $filename = $_POST['filename'] ?? '';
    if (delete_backup($filename)) {
        flash("Backup gelöscht: {$filename}", 'success');
    } else {
        flash(t('adm_loeschen_fehlgeschlagen_ungueltiger_name'), 'error');
    }
    redirect(FORUM_URL . '/admin/?action=backups');
}

if ($action === 'download_backup') {
    if (!is_admin(current_user())) {
        http_response_code(403);
        error_page(403, t('adm_zugriff_verweigert'), t('adm_dieser_bereich_ist_administratoren'));
    }
    $filename = $_GET['file'] ?? '';
    $path = backup_path($filename);
    if (!$path) {
        http_response_code(404);
        error_page(404, t('adm_backup_nicht_gefunden'), t('adm_diese_sicherung_gibt_es'));
    }
    // Force-Download
    $mime = str_ends_with($filename, '.sql') ? 'application/sql' : 'application/zip';
    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($path));
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    exit;
}

if ($action === 'clear_cache' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? '')) error_page(400, t('adm_sicherheits_token_ungueltig'), t('adm_bitte_lade_die_seite'));
    $type = $_POST['type'] ?? 'all';

    $cleared = [];

    if ($type === 'templates' || $type === 'all') {
        $n = tpl_cache_clear();
        $cleared[] = $n . ' Template-Cache-Dateien';
    }

    if ($type === 'smileys' || $type === 'all') {
        if (function_exists('smileys_cache_clear')) {
            smileys_cache_clear();
            $cleared[] = 'Smiley-Cache (in-memory)';
        }
    }

    flash(t('adm_cache_geleert') . implode(', ', $cleared), 'success');
    redirect(FORUM_URL . '/admin/?action=maintenance');
}

// ─── FREIGABE-WARTESCHLANGE ───────────────────────────────────────
if (in_array($action, ['approve_thread', 'approve_post', 'reject_thread', 'reject_post'], true)) {
    if (!verify_csrf($_GET['csrf'] ?? '')) error_page(400, t('adm_sicherheits_token_ungueltig'), t('adm_bitte_lade_die_seite'));

    $id = get_int('id');
    $reason = $_GET['reason'] ?? '';
    $ok = false;

    switch ($action) {
        case 'approve_thread': $ok = approve_thread($id); break;
        case 'approve_post':   $ok = approve_post($id);   break;
        case 'reject_thread':  $ok = reject_thread($id, $reason); break;
        case 'reject_post':    $ok = reject_post($id, $reason); break;
    }

    if ($ok) {
        $verb = str_contains($action, 'approve') ? 'freigegeben' : 'abgelehnt';
        flash(t('adm_beitrag_erfolgreich') . $verb . '.', 'success');
    } else {
        flash(t('adm_aktion_fehlgeschlagen'), 'error');
    }
    redirect(FORUM_URL . '/admin/?action=approval');
}

// ─── PHPBB-IMPORT ─────────────────────────────────────────────────
//
// Der Import laeuft in Runden, weil ein Forum mit hunderttausend Beitraegen
// im Browser in jedes Zeitlimit liefe. Jede Runde macht ein Stueck und sagt,
// wie weit sie gekommen ist; die Seite ruft die naechste selbst auf.
//
// Die Zugangsdaten stehen in der Sitzung, nicht in versteckten Feldern: sie
// gehen sonst bei jeder Runde durch den Browser.

if (str_starts_with($action, 'import_')) {
    require_once __DIR__ . '/../lib/import_phpbb.php';
}

if ($action === 'import_pruefen' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf(post_str('csrf'))) error_page(400, t('adm_sicherheits_token_ungueltig'), t('adm_bitte_lade_die_seite'));

    $opt = [
        'host'    => post_str('db_host') ?: 'localhost',
        'port'    => post_str('db_port') ?: '3306',
        'db'      => post_str('db_name'),
        'user'    => post_str('db_user'),
        'pass'    => (string) ($_POST['db_pass'] ?? ''),
        'praefix' => post_str('db_praefix') ?: 'phpbb_',
        'dateien' => rtrim(post_str('dateien'), '/'),
    ];

    if ($opt['db'] === '' || $opt['user'] === '') {
        flash(t('adm_datenbank_und_benutzer_sind'), 'error');
        redirect(FORUM_URL . '/admin/?action=import');
    }

    try {
        $alt = import_verbindung($opt);
    } catch (Throwable $e) {
        flash(t('adm_keine_verbindung') . $e->getMessage(), 'error');
        redirect(FORUM_URL . '/admin/?action=import');
    }

    $grund = import_pruefe_quelle($alt, $opt['praefix']);
    if ($grund !== null) {
        flash($grund, 'error');
        redirect(FORUM_URL . '/admin/?action=import');
    }

    $_SESSION['import_opt']    = $opt;
    $_SESSION['import_bericht'] = [
        'bestand'    => import_bestand($alt, $opt['praefix']),
        'passwoerter'=> import_passwoerter($alt, $opt['praefix']),
        'dateien'    => import_dateien_pruefen($alt, $opt['praefix'], $opt['dateien']),
        'hier'       => import_ziel_bestand(),
    ];
    redirect(FORUM_URL . '/admin/?action=import');
}

if ($action === 'import_leeren' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    if (!verify_csrf(post_str('csrf'))) {
        echo json_encode(['fehler' => t('adm_sicherheits_token_ungueltig_bitte')]);
        exit;
    }
    // Das eigene Konto bleibt — wer sich selbst mitloescht, kann hinterher
    // nicht einmal nachsehen, ob der Import geklappt hat.
    $ich = (int) (current_user()['user_id'] ?? 0);
    if ($ich <= 0) {
        echo json_encode(['fehler' => t('adm_kein_angemeldetes_konto_abgebrochen')]);
        exit;
    }
    @set_time_limit(300);
    try {
        $bericht = import_leeren($ich);
        echo json_encode(['weg' => array_sum($bericht)]);
    } catch (Throwable $ex) {
        echo json_encode(['fehler' => $ex->getMessage()]);
    }
    exit;
}

if ($action === 'import_runde' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    if (!verify_csrf(post_str('csrf'))) {
        echo json_encode(['fehler' => t('adm_sicherheits_token_ungueltig_bitte')]);
        exit;
    }
    $opt = $_SESSION['import_opt'] ?? null;
    if (!$opt) {
        echo json_encode(['fehler' => t('adm_die_zugangsdaten_sind_nicht')]);
        exit;
    }

    $schritt = post_str('schritt');
    if (!isset(IMPORT_SCHRITTE[$schritt])) {
        echo json_encode(['fehler' => t('adm_unbekannter_schritt')]);
        exit;
    }

    // Ein Zeitlimit waehrend einer Runde traefe mitten in die Arbeit. Also so
    // viel Zeit geben, wie der Server hergibt — die Runde selbst ist kurz.
    @set_time_limit(300);

    try {
        $alt = import_verbindung($opt);
        $e   = import_runde($alt, $opt['praefix'], $schritt, $opt);
        echo json_encode([
            'getan'   => (int) ($e['getan'] ?? 0),
            'neu'     => (int) ($e['neu'] ?? 0),
            'fertig'  => (bool) ($e['fertig'] ?? true),
            'stand'   => import_erledigt($schritt),
        ]);
    } catch (Throwable $ex) {
        echo json_encode(['fehler' => $ex->getMessage()]);
    }
    exit;
}

if ($action === 'import_zurueck' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf(post_str('csrf'))) error_page(400, t('adm_sicherheits_token_ungueltig'), t('adm_bitte_lade_die_seite'));
    $bericht = import_zuruecknehmen();
    unset($_SESSION['import_bericht']);
    $summe = array_sum($bericht);
    flash($summe > 0
        ? t('adm_der_import_wurde_zurueckgenommen') . number_format($summe, 0, ',', '.') . t('adm_eintraege_entfernt')
        : t('adm_es_gab_nichts_zurueckzunehmen'), 'success');
    redirect(FORUM_URL . '/admin/?action=import');
}

if ($action === 'import_vergessen') {
    unset($_SESSION['import_opt'], $_SESSION['import_bericht']);
    redirect(FORUM_URL . '/admin/?action=import');
}

// ─── DATENBANK-MIGRATIONEN ────────────────────────────────────────
if ($action === 'run_migration' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf(post_str('csrf'))) error_page(400, t('adm_sicherheits_token_ungueltig'), t('adm_bitte_lade_die_seite'));

    // Von aussen betreute Installation: der Betreiber des Dienstes faehrt die
    // Migrationen, hier ist der Weg zu. Riegel im Handler, nicht nur in der
    // Ansicht — eine ausgeblendete Schaltflaeche ist keine Sicherung.
    if (update_extern()) {
        flash(t('adm_diese_installation_aktualisiert_der_2'), 'error');
        redirect(FORUM_URL . '/admin/?action=updates');
    }

    // Nur der Owner. Eine Migration aendert das Schema unter laufendem Betrieb;
    // das ist Sache des Inhabers, nicht jedes Admins, den er ernannt hat.
    $cu = current_user();
    if (empty($cu['is_owner'])) {
        flash(t('adm_nur_der_owner_darf'), 'error');
        redirect(FORUM_URL . '/admin/?action=updates');
    }

    $version = post_str('version');
    $result  = execute_migration($version);

    if ($result['success']) {
        flash('Migration ' . htmlspecialchars($version) . ' erfolgreich ausgeführt.', 'success');
    } else {
        flash(t('adm_migration_fehlgeschlagen') . htmlspecialchars($result['error'] ?? 'unbekannt'), 'error');
    }
    redirect(FORUM_URL . '/admin/?action=updates');
}

// ─── UPDATE-PAKET HOCHLADEN (v0.18.54) ────────────────────────────
if ($action === 'upload_update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf(post_str('csrf'))) error_page(400, t('adm_sicherheits_token_ungueltig'), t('adm_bitte_lade_die_seite'));

    // Von aussen betreute Installation: hier wird nichts eingespielt.
    if (update_extern()) {
        flash(t('adm_diese_installation_aktualisiert_der'), 'error');
        redirect(FORUM_URL . '/admin/?action=updates');
    }

    // STRENG: Nur der Owner darf Pakete einspielen (RCE-Vektor)
    $cu = current_user();
    if (empty($cu['is_owner'])) {
        flash(t('adm_nur_der_owner_darf_3'), 'error');
        redirect(FORUM_URL . '/admin/?action=updates');
    }

    // Upload prüfen
    if (empty($_FILES['update_zip']) || $_FILES['update_zip']['error'] !== UPLOAD_ERR_OK) {
        flash(t('adm_kein_gueltiges_zip_hochgeladen'), 'error');
        redirect(FORUM_URL . '/admin/?action=updates');
    }

    $tmpPath = $_FILES['update_zip']['tmp_name'];
    if (!is_uploaded_file($tmpPath)) {
        flash('Sicherheits-Abbruch: keine echte Upload-Datei.', 'error');
        redirect(FORUM_URL . '/admin/?action=updates');
    }

    // Validieren (schreibt noch nichts)
    $val = update_validate_zip($tmpPath);
    if (empty($val['ok'])) {
        flash(t('adm_paket_abgelehnt') . htmlspecialchars($val['error']), 'error');
        redirect(FORUM_URL . '/admin/?action=updates');
    }

    // Installieren
    $install = update_install_package($val['zip'], $val['manifest'], $val['files'], $val['has_sql']);
    $val['zip']->close();

    // Volles Log für die Detail-Anzeige im View merken
    $_SESSION['_update_result'] = [
        'success'  => $install['success'],
        'manifest' => $val['manifest'],
        'log'      => $install['log'],
        'error'    => $install['error'] ?? null,
    ];

    if ($install['success']) {
        update_record_package($val['manifest'], $install['log']);
        flash('✅ Update "' . htmlspecialchars($val['manifest']['name'])
            . '" (v' . htmlspecialchars($val['manifest']['version']) . ') installiert. Details unten.', 'success');
    } else {
        flash(t('adm_installation_abgebrochen') . htmlspecialchars($install['error']), 'error');
    }
    redirect(FORUM_URL . '/admin/?action=updates');
}

// ─── BENUTZER-AKTIONEN ────────────────────────────────────────────
if (in_array($action, ['make_admin', 'remove_admin', 'make_mod', 'remove_mod', 'ban_user', 'unban_user', 'delete_user', 'make_owner', 'remove_owner'], true)) {
    if (!verify_csrf($_GET['csrf'] ?? '')) error_page(400, t('adm_sicherheits_token_ungueltig'), t('adm_bitte_lade_die_seite'));

    $targetId = get_int('id');
    $target   = db_fetch('SELECT * FROM users WHERE user_id = ?', [$targetId]);
    $cu       = current_user();

    if (!$target) {
        flash(t('adm_benutzer_nicht_gefunden'), 'error');
        redirect(FORUM_URL . '/admin/?action=users');
    }

    $isFounderActor = (int) $cu['user_id'] === 1;
    $ownerActions   = ['make_owner', 'remove_owner'];

    // Nur der Founder (User-ID 1) darf Owner ernennen/entlassen
    if (in_array($action, $ownerActions, true) && !$isFounderActor) {
        flash(t('adm_nur_der_forum_gruender'), 'error');
        redirect(FORUM_URL . '/admin/?action=users');
    }

    // OWNER-SCHUTZ: Owner sind tabu für alle Aktionen AUSSER remove_owner durch den Founder
    if (!empty($target['is_owner']) && !($action === 'remove_owner' && $isFounderActor)) {
        flash(t('adm_forum_inhaber_koennen_nicht'), 'error');
        redirect(FORUM_URL . '/admin/?action=users');
    }

    // Founder (User-ID 1) kann sich selbst NICHT degradieren/sperren (Lock-Out-Schutz)
    if ((int) $target['user_id'] === 1
        && in_array($action, ['remove_owner', 'remove_admin', 'ban_user', 'delete_user'], true)) {
        flash(t('adm_der_forum_gruender_user'), 'error');
        redirect(FORUM_URL . '/admin/?action=users');
    }

    // Sich selbst kann man nicht degradieren/sperren (Mehrfach-Check)
    if ($target['user_id'] == $cu['user_id'] && in_array($action, ['remove_admin', 'ban_user', 'delete_user', 'remove_owner'], true)) {
        flash(t('adm_du_kannst_dich_nicht'), 'error');
        redirect(FORUM_URL . '/admin/?action=users');
    }

    try {
        switch ($action) {
            case 'make_owner':
                // Owner ist über Admin → is_admin wird zurückgesetzt (Owner braucht es nicht extra)
                db_execute('UPDATE users SET is_owner = 1, is_admin = 0, is_mod = 0, is_banned = 0 WHERE user_id = ?', [$targetId]);
                db_execute("UPDATE users SET `rank` = 'Inhaber' WHERE user_id = ?", [$targetId]);
                flash($target['username'] . ' ist jetzt Inhaber:in.', 'success');
                db_execute('INSERT INTO mod_log (mod_id, action, target_type, target_id) VALUES (?, ?, ?, ?)',
                    [$cu['user_id'], 'make_owner', 'user', $targetId]);
                break;

            case 'remove_owner':
                // Owner-Status weg, aber Admin-Status bleibt (Soft-Demotion)
                db_execute('UPDATE users SET is_owner = 0, is_admin = 1 WHERE user_id = ?', [$targetId]);
                db_execute("UPDATE users SET `rank` = 'Administrator' WHERE user_id = ?", [$targetId]);
                flash($target['username'] . ' ist kein Inhaber mehr (jetzt Administrator).', 'success');
                db_execute('INSERT INTO mod_log (mod_id, action, target_type, target_id) VALUES (?, ?, ?, ?)',
                    [$cu['user_id'], 'remove_owner', 'user', $targetId]);
                break;
            case 'make_admin':
                db_execute('UPDATE users SET is_admin = 1, is_mod = 0, is_banned = 0 WHERE user_id = ?', [$targetId]);
                db_execute("UPDATE users SET `rank` = 'Administrator' WHERE user_id = ?", [$targetId]);
                flash($target['username'] . ' ist jetzt Administrator.', 'success');
                db_execute('INSERT INTO mod_log (mod_id, action, target_type, target_id) VALUES (?, ?, ?, ?)',
                    [$cu['user_id'], 'make_admin', 'user', $targetId]);
                break;

            case 'remove_admin':
                db_execute('UPDATE users SET is_admin = 0 WHERE user_id = ?', [$targetId]);
                // Rank zurück auf Post-Count-basiert
                try { update_user_rank($targetId); } catch (Exception $e) {}
                flash($target['username'] . ' ist kein Administrator mehr.', 'success');
                db_execute('INSERT INTO mod_log (mod_id, action, target_type, target_id) VALUES (?, ?, ?, ?)',
                    [$cu['user_id'], 'remove_admin', 'user', $targetId]);
                break;

            case 'make_mod':
                db_execute('UPDATE users SET is_mod = 1, is_admin = 0, is_banned = 0 WHERE user_id = ?', [$targetId]);
                flash($target['username'] . ' ist jetzt Moderator.', 'success');
                db_execute('INSERT INTO mod_log (mod_id, action, target_type, target_id) VALUES (?, ?, ?, ?)',
                    [$cu['user_id'], 'make_mod', 'user', $targetId]);
                break;

            case 'remove_mod':
                db_execute('UPDATE users SET is_mod = 0 WHERE user_id = ?', [$targetId]);
                flash($target['username'] . ' ist kein Moderator mehr.', 'success');
                db_execute('INSERT INTO mod_log (mod_id, action, target_type, target_id) VALUES (?, ?, ?, ?)',
                    [$cu['user_id'], 'remove_mod', 'user', $targetId]);
                break;

            case 'ban_user':
                $reason = $_GET['reason'] ?? t('adm_vom_admin_gesperrt');
                db_execute('UPDATE users SET is_banned = 1, is_admin = 0, is_mod = 0, ban_reason = ? WHERE user_id = ?',
                    [$reason, $targetId]);
                flash($target['username'] . ' wurde gesperrt.', 'success');
                db_execute('INSERT INTO mod_log (mod_id, action, target_type, target_id, reason) VALUES (?, ?, ?, ?, ?)',
                    [$cu['user_id'], 'ban', 'user', $targetId, $reason]);
                break;

            case 'unban_user':
                db_execute('UPDATE users SET is_banned = 0, ban_reason = NULL WHERE user_id = ?', [$targetId]);
                flash($target['username'] . ' wurde entsperrt.', 'success');
                db_execute('INSERT INTO mod_log (mod_id, action, target_type, target_id) VALUES (?, ?, ?, ?)',
                    [$cu['user_id'], 'unban', 'user', $targetId]);
                break;

            case 'delete_user':
                // Owner und Admin dürfen User-Account löschen (Soft-Delete: anonymisiert)
                if (empty($cu['is_owner']) && empty($cu['is_admin'])) {
                    flash(t('adm_nur_inhaber_und_administratoren'), 'error');
                    redirect(FORUM_URL . '/admin/?action=users');
                }

                // Avatar-/Banner-Dateien physisch entfernen
                delete_user_files($targetId);

                // DSGVO-Soft-Delete: Account anonymisieren, Beiträge bleiben
                $delResult = delete_user_account($targetId);

                if (!$delResult['ok']) {
                    flash($delResult['error'] ?? t('adm_account_konnte_nicht_geloescht'), 'error');
                } else {
                    flash($target['username'] . ' wurde gelöscht (anonymisiert). Beiträge bleiben erhalten.', 'success');
                    db_execute('INSERT INTO mod_log (mod_id, action, target_type, target_id) VALUES (?, ?, ?, ?)',
                        [$cu['user_id'], 'delete_user', 'user', $targetId]);
                }
                break;
        }
    } catch (Exception $e) {
        flash('Fehler: ' . $e->getMessage(), 'error');
    }

    redirect(FORUM_URL . '/admin/?action=users');
}

// ─── MELDUNGEN ────────────────────────────────────────────────────
if ($action === 'resolve_report') {
    if (!verify_csrf($_GET['csrf'] ?? '')) error_page(400, t('adm_sicherheits_token_ungueltig'), t('adm_bitte_lade_die_seite'));
    $rid    = get_int('id');
    $newSt  = $_GET['status'] ?? 'reviewed';
    $cu     = current_user();
    resolve_report($rid, (int) $cu['user_id'], $newSt);
    flash(t('adm_meldung_als') . $newSt . '" markiert.', 'success');
    redirect(FORUM_URL . '/admin/?action=reports');
}

// ─── WARNUNGEN ────────────────────────────────────────────────────
if ($action === 'issue_warning' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf(post_str('csrf'))) error_page(400, t('adm_sicherheits_token_ungueltig'), t('adm_bitte_lade_die_seite'));
    $cu = current_user();
    $uid = post_int('user_id');
    $pts = post_int('points', 1);
    $rsn = post_str('reason');
    $exp = post_str('expires');
    $target = $uid > 0 ? db_fetch('SELECT * FROM users WHERE user_id = ?', [$uid]) : null;
    if ($target && !can_modify_user($cu, $target)) {
        // Sonst laesst sich der Inhaber ueber die Warn-Schwelle automatisch
        // sperren — make_owner setzt is_admin = 0, und check_warning_threshold
        // prueft nur auf is_admin.
        flash(t('adm_dieser_benutzer_darf_nicht'), 'error');
        redirect(FORUM_URL . '/admin/?action=warnings&user=' . $uid);
    }
    if ($uid > 0 && $pts > 0 && mb_strlen($rsn) >= 3) {
        issue_warning($uid, (int) $cu['user_id'], $pts, $rsn, $exp ?: null);
        flash(t('adm_warnung_ausgesprochen') . $pts . ' Pkt).', 'success');
    } else {
        flash(t('adm_ungueltige_eingaben'), 'error');
    }
    redirect(FORUM_URL . '/admin/?action=warnings&user=' . $uid);
}

if ($action === 'revoke_warning') {
    if (!verify_csrf($_GET['csrf'] ?? '')) error_page(400, t('adm_sicherheits_token_ungueltig'), t('adm_bitte_lade_die_seite'));
    $cu = current_user();
    revoke_warning(get_int('id'), (int) $cu['user_id']);
    flash(t('adm_warnung_aufgehoben'), 'success');
    redirect(FORUM_URL . '/admin/?action=warnings&user=' . get_int('user'));
}

// ─── FORUM-MODERATOREN ────────────────────────────────────────────
if ($action === 'add_board_mod' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf(post_str('csrf'))) error_page(400, t('adm_sicherheits_token_ungueltig'), t('adm_bitte_lade_die_seite'));
    $cu  = current_user();
    $bid = post_int('board_id');
    $uid = post_int('user_id');
    if ($bid > 0 && $uid > 0) {
        add_board_moderator($bid, $uid, (int) $cu['user_id']);
        flash(t('adm_moderator_zugeordnet'), 'success');
    }
    redirect(FORUM_URL . '/admin/?action=boards&board=' . $bid);
}

if ($action === 'remove_board_mod') {
    if (!verify_csrf($_GET['csrf'] ?? '')) error_page(400, t('adm_sicherheits_token_ungueltig'), t('adm_bitte_lade_die_seite'));
    $bid = get_int('board');
    $uid = get_int('user');
    if ($bid > 0 && $uid > 0) {
        remove_board_moderator($bid, $uid);
        flash(t('adm_moderator_entfernt'), 'success');
    }
    redirect(FORUM_URL . '/admin/?action=boards&board=' . $bid);
}

// ─── TAGS ─────────────────────────────────────────────────────────
if ($action === 'add_tag' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf(post_str('csrf'))) error_page(400, t('adm_sicherheits_token_ungueltig'), t('adm_bitte_lade_die_seite'));
    $slug = preg_replace('/[^a-z0-9]/', '', strtolower(post_str('slug')));
    $name = post_str('name');
    $desc = post_str('description');
    // Der Wert landet unescaped-wirksam in style="color:…" — ohne Formatpruefung
    // liesse sich dort CSS unterbringen, das von jedem Besucher eine fremde URL
    // abruft.
    $col  = post_str('color', '#888');
    if (!preg_match('/^#[0-9a-fA-F]{3,8}$/', $col)) $col = '#888';
    if ($slug && $name) {
        try {
            db_execute('INSERT INTO tags (slug, name, description, color) VALUES (?, ?, ?, ?)',
                [$slug, $name, $desc, $col]);
            flash(t('adm_tag_angelegt'), 'success');
        } catch (Exception $e) {
            flash('Fehler: ' . $e->getMessage(), 'error');
        }
    }
    redirect(FORUM_URL . '/admin/?action=tags');
}

if ($action === 'delete_tag') {
    if (!verify_csrf($_GET['csrf'] ?? '')) error_page(400, t('adm_sicherheits_token_ungueltig'), t('adm_bitte_lade_die_seite'));
    db_execute('DELETE FROM tags WHERE tag_id = ?', [get_int('id')]);
    flash(t('adm_tag_geloescht'), 'success');
    redirect(FORUM_URL . '/admin/?action=tags');
}

// ─── DATEN PRO ACTION ─────────────────────────────────────────────
$vars = ['action' => $action, 'title' => 'Adminbereich'];

switch ($action) {

    case 'dashboard':
        $vars['stats'] = [
            // Geloeschte Konten sind keine Mitglieder mehr — sie stehen nur noch
            // als Anker unter ihren Beitraegen. Mitgezaehlt waere die Zahl hier
            // groesser als die auf der Startseite.
            ['icon' => '👥', 'label' => 'Mitglieder', 'value' => db_count('SELECT COUNT(*) FROM users WHERE COALESCE(is_deleted, 0) = 0')],
            ['icon' => '💬', 'label' => 'Threads',    'value' => db_count('SELECT COUNT(*) FROM threads')],
            ['icon' => '📝', 'label' => t('adm_beitraege'),   'value' => db_count('SELECT COUNT(*) FROM posts')],
            ['icon' => '🚨', 'label' => t('adm_offene_meldungen'), 'value' => open_reports_count()],
            ['icon' => '🚫', 'label' => 'IP-Banns',   'value' => db_count('SELECT COUNT(*) FROM ip_bans WHERE expires_at IS NULL OR expires_at > NOW()')],
            ['icon' => '🟢', 'label' => t('adm_online_jetzt'), 'value' => online_stats()['total']],
        ];
        $vars['newestUsers']  = db_fetchAll('
            SELECT * FROM users WHERE COALESCE(is_deleted, 0) = 0
            ORDER BY registered_at DESC LIMIT 10
        ');
        $vars['recentLogins'] = db_fetchAll('
            SELECT u.username, u.user_id, l.ip, l.created_at
            FROM user_ip_log l JOIN users u ON u.user_id = l.user_id
            WHERE l.action = "login" AND COALESCE(u.is_deleted, 0) = 0
            ORDER BY l.created_at DESC LIMIT 10
        ');

        // v0.18.49: Statistik-Daten gleich mitladen (Statistik ist jetzt Teil des Dashboards)
        $vars['userRegs30d'] = db_fetchAll('
            SELECT DATE(registered_at) AS d, COUNT(*) AS n
            FROM users
            WHERE registered_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
              AND COALESCE(is_deleted, 0) = 0
            GROUP BY DATE(registered_at)
            ORDER BY d ASC
        ');
        $vars['posts30d'] = db_fetchAll('
            SELECT DATE(created_at) AS d, COUNT(*) AS n
            FROM posts
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(created_at)
            ORDER BY d ASC
        ');
        $vars['topPosters'] = db_fetchAll('
            SELECT user_id, username, post_count, rank
            FROM users
            WHERE post_count > 0
            ORDER BY post_count DESC LIMIT 10
        ');
        $vars['activeBoards'] = db_fetchAll('
            SELECT board_id, title, thread_count, post_count
            FROM boards
            ORDER BY post_count DESC LIMIT 10
        ');
        $vars['avgPostsPerUser'] = (float) db_count('SELECT IFNULL(AVG(post_count),0) FROM users');
        $vars['activeLastWeek']  = db_count('SELECT COUNT(*) FROM users WHERE last_login >= DATE_SUB(NOW(), INTERVAL 7 DAY)');

        // Aktivitäts-Heatmap: 7 Tage × 24 Stunden
        $heatRaw = db_fetchAll('
            SELECT DAYOFWEEK(created_at) AS dow, HOUR(created_at) AS hr, COUNT(*) AS n
            FROM posts
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 60 DAY)
            GROUP BY dow, hr
        ');
        $heat = array_fill(0, 7, array_fill(0, 24, 0));
        foreach ($heatRaw as $h) {
            $d = ((int) $h['dow'] + 5) % 7;
            $heat[$d][(int) $h['hr']] = (int) $h['n'];
        }
        $vars['heatmap'] = $heat;
        $vars['heatMax'] = max(1, max(array_map('max', $heat)));

        try {
            $vars['totalReactions'] = db_count('SELECT COUNT(*) FROM reactions');
            $vars['totalBookmarks'] = db_count('SELECT COUNT(*) FROM bookmarks');
        } catch (Exception $e) {
            $vars['totalReactions'] = 0;
            $vars['totalBookmarks'] = 0;
        }
        break;

    case 'reports':
        $status = $_GET['status'] ?? 'open';
        if (!in_array($status, ['open','reviewed','dismissed','actioned'], true)) $status = 'open';
        $vars['reportStatus'] = $status;
        $vars['reports']      = list_reports($status, 100);
        $vars['reasons']      = report_reasons();
        $vars['counts']       = [
            'open'      => db_count("SELECT COUNT(*) FROM reports WHERE status='open'"),
            'reviewed'  => db_count("SELECT COUNT(*) FROM reports WHERE status='reviewed'"),
            'dismissed' => db_count("SELECT COUNT(*) FROM reports WHERE status='dismissed'"),
            'actioned'  => db_count("SELECT COUNT(*) FROM reports WHERE status='actioned'"),
        ];
        break;

    case 'warnings':
        $uid = get_int('user');
        $vars['warnUser']     = $uid > 0 ? db_fetch('SELECT * FROM users WHERE user_id = ?', [$uid]) : null;
        $vars['warningsList'] = $uid > 0 ? user_warnings($uid) : [];
        $vars['threshold']    = (int) get_setting('warning_ban_threshold', 10);
        $vars['recentWarned'] = db_fetchAll('
            SELECT u.user_id, u.username, u.warning_points, u.is_banned,
                   (SELECT MAX(created_at) FROM warnings WHERE user_id = u.user_id) AS last_warning
            FROM users u
            WHERE u.warning_points > 0
            ORDER BY u.warning_points DESC, last_warning DESC
            LIMIT 50
        ');
        break;

    case 'tags':
        $vars['tagsList'] = db_fetchAll('SELECT * FROM tags ORDER BY usage_count DESC, name');
        break;

    case 'users':
        $q = trim($_GET['q'] ?? '');
        // Geloeschte Konten stehen hier nicht mehr — auch nicht auf Wunsch.
        // Bis 1.2.2 liess sich das umschalten; das war eine halbe Loeschung.
        // An einem anonymisierten Konto ist nichts zu verwalten. Die Zeile
        // selbst bleibt in der Datenbank, weil posts.user_id und
        // threads.user_id NOT NULL sind: sie ist der Anker unter den
        // Beitraegen, die bewusst stehen bleiben.
        $wo = ' AND COALESCE(is_deleted, 0) = 0';

        if ($q !== '') {
            $like = '%' . $q . '%';
            $vars['allUsers'] = db_fetchAll(
                'SELECT * FROM users WHERE (username LIKE ? OR email LIKE ? OR last_ip LIKE ? OR registration_ip LIKE ?)'
                . $wo . ' ORDER BY registered_at DESC LIMIT 200',
                [$like, $like, $like, $like]
            );
        } else {
            $vars['allUsers'] = db_fetchAll(
                'SELECT * FROM users WHERE 1 = 1' . $wo . ' ORDER BY registered_at DESC LIMIT 200'
            );
        }
        $vars['searchQuery'] = $q;
        break;

    case 'categories':
        $vars['categories'] = db_fetchAll('SELECT * FROM categories ORDER BY position');
        break;

    case 'groups':
        $vars['gruppen']      = groups_all();
        $vars['mitgliederZahl'] = group_member_counts();
        $gid = get_int('group');
        if ($gid > 0) {
            $vars['gruppe']      = group_by_id($gid);
            $vars['mitglieder']  = group_members($gid);
            $vars['waehlbar']    = db_fetchAll('
                SELECT user_id, username FROM users
                WHERE is_banned = 0 AND is_deleted = 0
                  AND user_id NOT IN (SELECT user_id FROM group_members WHERE group_id = ?)
                ORDER BY username
            ', [$gid]);
        }
        // Wo taucht die Gruppe ueberall in Rechten auf? Zeigt beim Loeschen,
        // was daran haengt.
        $vars['rechteJeGruppe'] = [];
        foreach (db_fetchAll('SELECT group_id, COUNT(*) AS n FROM board_permissions
                              WHERE level <> \'none\' GROUP BY group_id') as $r) {
            $vars['rechteJeGruppe'][(int) $r['group_id']] = (int) $r['n'];
        }
        break;

    case 'boards':
        $vars['boards'] = db_fetchAll('
            SELECT b.*, c.title AS cat FROM boards b
            JOIN categories c ON c.cat_id = b.cat_id
            ORDER BY c.position, b.position
        ');
        $vars['categories'] = db_fetchAll('SELECT * FROM categories ORDER BY position');
        // Board-Mods für jedes Board
        $allIds = array_map(fn($b) => (int) $b['board_id'], $vars['boards']);
        $vars['boardMods'] = board_moderators_map($allIds);
        // Selektiertes Board für Mod-Verwaltung
        $vars['gruppen'] = groups_all();
        $selBoard = get_int('board');
        if ($selBoard > 0) {
            $vars['selectedBoard']  = db_fetch('SELECT * FROM boards WHERE board_id = ?', [$selBoard]);
            $vars['brettRechte']    = board_permissions_map($selBoard);
            $vars['brettFeinrechte'] = board_feinrechte_map($selBoard);
            $vars['feinrechteDa']    = feinrechte_bereit();
            $vars['selectedMods']   = board_moderators($selBoard);
            $vars['availableUsers'] = db_fetchAll('
                SELECT user_id, username, is_admin, is_mod FROM users
                WHERE is_banned = 0
                ORDER BY is_admin DESC, is_mod DESC, username
            ');
        }
        break;

    case 'ranks':
        global $RANKS;
        $rankData = [];
        foreach ($RANKS as $i => $r) {
            $next  = $RANKS[$i + 1]['min_posts'] ?? PHP_INT_MAX;
            $count = db_count('SELECT COUNT(*) FROM users WHERE post_count >= ? AND post_count < ? AND is_admin = 0',
                [$r['min_posts'], $next]);
            $rankData[] = [
                'title'      => $r['title'],
                'color'      => $r['color'],
                'range'      => $r['min_posts'] . ($next < PHP_INT_MAX ? ' – ' . ($next - 1) : '+'),
                'stars_html' => rank_stars_html($r['stars'], $r['color']),
                'count'      => $count,
            ];
        }
        $vars['rankData'] = $rankData;
        $vars['topUsers'] = db_fetchAll('SELECT * FROM users ORDER BY post_count DESC LIMIT 10');
        break;

    case 'modlog':
        $vars['modLog'] = db_fetchAll('
            SELECT ml.*, u.username FROM mod_log ml
            JOIN users u ON u.user_id = ml.mod_id
            ORDER BY ml.created_at DESC LIMIT 100
        ');
        break;

    case 'settings':
        $rows = db_fetchAll('SELECT setting_key, setting_value FROM settings');
        $s = [];
        foreach ($rows as $r) $s[$r['setting_key']] = $r['setting_value'];
        $vars['settings'] = $s;
        break;

    // ─── v0.18.49: Aufgespaltene System-Sektionen ───────────
    case 'email':
        $rows = db_fetchAll('SELECT setting_key, setting_value FROM settings');
        $s = [];
        foreach ($rows as $r) $s[$r['setting_key']] = $r['setting_value'];
        $vars['settings'] = $s;

        // Installationen von vor dem Schluessel haben keinen. Statt sie nur
        // darauf hinzuweisen, gibt es die fertige Zeile zum Kopieren — einmal
        // je Sitzung dieselbe, sonst waere sie nach jedem Neuladen eine andere.
        // Der Hinweis erscheint nur, wenn es auch etwas zu schuetzen gibt. Wer
        // ueber mail() versendet, hat gar kein SMTP-Passwort — dem muss man
        // keinen Kasten hinstellen.
        $vars['cryptReady'] = forum_crypt_available();
        if (!$vars['cryptReady'] && !empty($s['smtp_pass'])) {
            if (empty($_SESSION['crypt_key_vorschlag'])) {
                $_SESSION['crypt_key_vorschlag'] = forum_crypt_new_key();
            }
            $vars['cryptKeyVorschlag'] = $_SESSION['crypt_key_vorschlag'];
        }
        break;

    case 'registration':
        $rows = db_fetchAll('SELECT setting_key, setting_value FROM settings');
        $s = [];
        foreach ($rows as $r) $s[$r['setting_key']] = $r['setting_value'];
        $vars['settings'] = $s;
        break;

    case 'maintenance_mode':
        $rows = db_fetchAll('SELECT setting_key, setting_value FROM settings');
        $s = [];
        foreach ($rows as $r) $s[$r['setting_key']] = $r['setting_value'];
        $vars['settings'] = $s;
        break;

    case 'iplookup':
        $ip = trim($_GET['ip'] ?? '');
        $vars['lookupIP']    = $ip;
        $vars['lookupUsers'] = [];
        $vars['lookupPosts'] = [];
        if ($ip !== '') {
            $vars['lookupUsers'] = db_fetchAll('
                SELECT DISTINCT u.user_id, u.username, u.email, u.registered_at, u.is_banned
                FROM user_ip_log l JOIN users u ON u.user_id = l.user_id
                WHERE l.ip = ?
                ORDER BY u.username
            ', [$ip]);
            $vars['lookupPosts'] = db_fetchAll('
                SELECT p.post_id, p.created_at, p.thread_id, t.title AS thread_title, u.username, u.user_id
                FROM posts p JOIN users u ON u.user_id = p.user_id JOIN threads t ON t.thread_id = p.thread_id
                WHERE p.author_ip = ?
                ORDER BY p.created_at DESC LIMIT 50
            ', [$ip]);
        }
        // v0.18.49: IP-Banns auch hier laden (Combined View)
        $vars['ipBans'] = db_fetchAll('
            SELECT b.*, u.username AS banned_by_name
            FROM ip_bans b JOIN users u ON u.user_id = b.banned_by
            ORDER BY b.created_at DESC
        ');
        break;

    case 'ipbans':
        $vars['ipBans'] = db_fetchAll('
            SELECT b.*, u.username AS banned_by_name
            FROM ip_bans b JOIN users u ON u.user_id = b.banned_by
            ORDER BY b.created_at DESC
        ');
        break;

    case 'statistics':
        $vars['userRegs30d'] = db_fetchAll('
            SELECT DATE(registered_at) AS d, COUNT(*) AS n
            FROM users
            WHERE registered_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
              AND COALESCE(is_deleted, 0) = 0
            GROUP BY DATE(registered_at)
            ORDER BY d ASC
        ');
        $vars['posts30d'] = db_fetchAll('
            SELECT DATE(created_at) AS d, COUNT(*) AS n
            FROM posts
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(created_at)
            ORDER BY d ASC
        ');
        $vars['topPosters'] = db_fetchAll('
            SELECT user_id, username, post_count, rank
            FROM users
            WHERE post_count > 0
            ORDER BY post_count DESC LIMIT 10
        ');
        $vars['activeBoards'] = db_fetchAll('
            SELECT board_id, title, thread_count, post_count
            FROM boards
            ORDER BY post_count DESC LIMIT 10
        ');
        $vars['avgPostsPerUser'] = (float) db_count('SELECT IFNULL(AVG(post_count),0) FROM users');
        $vars['activeLastWeek']  = db_count('SELECT COUNT(*) FROM users WHERE last_login >= DATE_SUB(NOW(), INTERVAL 7 DAY)');

        // Aktivitäts-Heatmap: 7 Tage × 24 Stunden
        $heatRaw = db_fetchAll('
            SELECT DAYOFWEEK(created_at) AS dow, HOUR(created_at) AS hr, COUNT(*) AS n
            FROM posts
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 60 DAY)
            GROUP BY dow, hr
        ');
        $heat = array_fill(0, 7, array_fill(0, 24, 0));
        foreach ($heatRaw as $h) {
            // MySQL DAYOFWEEK: 1=So, 2=Mo, … 7=Sa → wir wollen Mo=0
            $d = ((int) $h['dow'] + 5) % 7;
            $heat[$d][(int) $h['hr']] = (int) $h['n'];
        }
        $vars['heatmap'] = $heat;
        $vars['heatMax'] = max(1, max(array_map('max', $heat)));

        // Reaktions-Stats
        try {
            $vars['totalReactions'] = db_count('SELECT COUNT(*) FROM reactions');
            $vars['totalBookmarks'] = db_count('SELECT COUNT(*) FROM bookmarks');
        } catch (Exception $e) {
            $vars['totalReactions'] = 0;
            $vars['totalBookmarks'] = 0;
        }
        break;

    case 'maintenance':
        $vars['ipLogSize']     = db_count('SELECT COUNT(*) FROM user_ip_log');
        $vars['expiredIpBans'] = db_count('SELECT COUNT(*) FROM ip_bans WHERE expires_at IS NOT NULL AND expires_at < NOW()');

        // Cache-Statistiken
        $tplCacheDir = __DIR__ . '/../cache/templates';
        $tplFiles    = is_dir($tplCacheDir) ? (glob($tplCacheDir . '/*.php') ?: []) : [];
        $tplBytes    = 0;
        foreach ($tplFiles as $f) $tplBytes += @filesize($f) ?: 0;
        $vars['cacheInfo'] = [
            'tpl_count' => count($tplFiles),
            'tpl_bytes' => $tplBytes,
        ];
        break;

    case 'import':
        require_once __DIR__ . '/../lib/import_phpbb.php';
        $vars['importBericht'] = $_SESSION['import_bericht'] ?? null;
        $vars['importOpt']     = $_SESSION['import_opt'] ?? null;
        $vars['importLaeuft']  = import_zuordnung_da();
        $vars['importStand']   = [];
        if ($vars['importLaeuft']) {
            foreach (array_keys(IMPORT_SCHRITTE) as $sch) {
                $vars['importStand'][$sch] = import_erledigt($sch);
            }
        }
        break;

    case 'updates':
        $available = available_migrations();
        $executed  = executed_migrations();

        // Map: version => execution info
        $executedMap = [];
        foreach ($executed as $e) $executedMap[$e['version']] = $e;

        // Status pro Migration
        foreach ($available as &$m) {
            $m['executed']  = isset($executedMap[$m['version']]);
            $m['exec_info'] = $executedMap[$m['version']] ?? null;
        }
        unset($m);

        // v0.18.54: Verlauf auf X sichtbare Einträge begrenzen, Rest ausklappbar
        $historyLimit = 15;
        $vars['historyVisible'] = array_slice($executed, 0, $historyLimit);
        $vars['historyHidden']  = array_slice($executed, $historyLimit);
        $vars['historyLimit']   = $historyLimit;

        $vars['migrations'] = $available;
        $vars['executed']   = $executed;
        $vars['extern']     = update_extern();
        // Bei externer Betreuung liegt der Weg still — dann auch keine
        // Schaltflaeche, die beim Druecken nur eine Absage zurueckgibt.
        $vars['canUpload']  = !update_extern() && !empty(current_user()['is_owner']);
        $vars['canMigrate'] = !update_extern() && !empty(current_user()['is_owner']);

        // v0.18.54: Detail-Ergebnis der letzten Installation (einmalig anzeigen)
        $vars['updateResult'] = $_SESSION['_update_result'] ?? null;
        unset($_SESSION['_update_result']);
        break;

    case 'approval':
        $vars['pendingThreads'] = pending_threads();
        $vars['pendingPosts']   = pending_posts();
        break;

    case 'approvals_users':
        $vars['pendingUsers'] = pending_user_approvals();
        break;

    case 'invitations':
        $vars['invitations'] = invitations_list(200);
        break;

    case 'bot_log':
        $filter = $_GET['filter'] ?? 'all';
        $allowedFilters = ['all', 'search', 'ai', 'suspect'];
        if (!in_array($filter, $allowedFilters, true)) $filter = 'all';
        $vars['botFilter'] = $filter;
        $vars['botStats']  = bot_log_stats();
        $vars['botList']   = bot_log_list($filter, 200);
        break;

    case 'modules':
        $vars['modules'] = available_modules();

        // Nur aus dem Zwischenspeicher — beim blossen Aufrufen der Seite wird
        // nichts abgerufen. Wer nachsehen will, drueckt den Knopf.
        require_once __DIR__ . '/../lib/module_quelle.php';
        $vars['quelleAn']      = modulquelle_erlaubt();
        $vars['quelleModule']  = [];
        $vars['quelleStand']   = null;
        if ($vars['quelleAn']) {
            $liste = modulquelle_liste();
            if (!empty($liste['ok'])) {
                $vars['quelleModule'] = modulquelle_vergleich($liste['module']);
                $vars['quelleStand']  = $liste['stand'] ?? null;
            }
        }
        break;

    case 'design':
        $vars['theme'] = theme_settings();
        $vars['hasPreview'] = !empty($_SESSION['theme_preview']);
        // Branding-Felder die jetzt zu Design gehören
        $vars['branding'] = [
            'forum_name'         => (string) get_setting('forum_name'),
            'forum_slogan'       => (string) get_setting('forum_slogan'),
            'forum_logo'         => (string) get_setting('forum_logo'),
            'forum_accent_color' => strtolower((string) (get_setting('forum_accent_color') ?: '4f46e5')),
            'forum_default_theme'=> (string) (get_setting('forum_default_theme') ?: 'dark'),
            'forum_footer_text'  => (string) get_setting('forum_footer_text'),
            'forum_footer_links' => (string) get_setting('forum_footer_links'),
            'forum_custom_css'   => (string) get_setting('forum_custom_css'),
            'source_url'         => (string) get_setting('source_url', ''),
        ];
        break;

    case 'smileys':
        smileys_cache_clear();
        $vars['smileys'] = db_fetchAll(
            'SELECT * FROM smileys ORDER BY is_in_picker DESC, position ASC, code ASC'
        );
        break;

    case 'legal':
        $vars['imprint'] = [
            'name'   => (string) get_setting('imprint_name', ''),
            'street' => (string) get_setting('imprint_street', ''),
            'city'   => (string) get_setting('imprint_city', ''),
            'email'  => (string) get_setting('imprint_email', ''),
        ];
        $vars['pages']        = list_pages();
        $vars['placeholders'] = page_placeholder_list();
        $vars['editingPage']  = $_GET['page'] ?? null;
        if ($vars['editingPage']) {
            $vars['pageData'] = get_page($vars['editingPage']);
        }
        break;

    case 'templates':
        $vars['templates']    = list_templates_with_status();
        $vars['editingTpl']   = $_GET['edit'] ?? null;
        if ($vars['editingTpl'] && !template_name_gueltig((string) $vars['editingTpl'])) {
            $vars['editingTpl'] = null;   // Name aus dem Parameter, nicht vertrauenswuerdig
        }
        if ($vars['editingTpl']) {
            $name = $vars['editingTpl'];
            $vars['tplOriginal']     = get_template_original($name);
            $vars['tplOverride']     = get_template_override($name);
            $vars['tplEffective']    = $vars['tplOverride'] ?? $vars['tplOriginal'];
            $vars['tplIsStale']      = is_template_override_stale($name);
            $vars['tplOriginalSaved']= get_template_original_at_save($name);
            $vars['tplDiff']         = null;
            if ($vars['tplIsStale']) {
                $vars['tplDiff'] = template_diff(
                    $vars['tplOriginalSaved'] ?? '',
                    $vars['tplOriginal'] ?? ''
                );
            }
        }
        break;

    case 'backups':
        $vars['backups'] = list_backups();
        $vars['zipAvailable'] = class_exists('ZipArchive');
        $totalSize = 0;
        foreach ($vars['backups'] as $b) $totalSize += $b['size'];
        $vars['backupTotalSize'] = $totalSize;
        break;
}

render('admin/index', $vars);
