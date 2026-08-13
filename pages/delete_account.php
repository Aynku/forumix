<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// delete_account.php – User löscht eigenen Account
require_once __DIR__ . '/../bootstrap.php';
require_login();

$cu = current_user();

// SICHERHEITS-CHECK: Darf dieser User sich selbst löschen?
// (Founder + letzter Owner sind geschützt)
$canDelete = can_user_delete_self((int) $cu['user_id']);
if (!$canDelete['ok']) {
    render('delete_account_blocked', [
        'title'  => 'Account löschen nicht möglich',
        'reason' => $canDelete['reason'],
    ]);
    exit;
}

$error = '';
$step  = 'confirm';  // confirm | done

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf(post_str('csrf'))) {
        $error = 'Sicherheits-Token ungültig. Bitte Seite neu laden und nochmal versuchen.';
    } else {
        $password = post_str('password');
        $confirm  = post_str('confirm_text');

        // Sicherheits-Schritt 1: korrektes Passwort
        if (empty($password) || empty($cu['password']) || !password_verify($password, $cu['password'])) {
            $error = 'Falsches Passwort.';
        }
        // Sicherheits-Schritt 2: Bestätigungs-Text muss exakt stimmen
        elseif ($confirm !== 'LÖSCHEN') {
            $error = 'Bitte gib das Wort „LÖSCHEN" exakt so ein (großgeschrieben).';
        }
        // Sicherheits-Schritt 3: nochmal can_user_delete_self prüfen (Race-Condition-Schutz)
        else {
            $recheck = can_user_delete_self((int) $cu['user_id']);
            if (!$recheck['ok']) {
                $error = $recheck['reason'];
            } else {
                // Avatar-Dateien physisch vom Server löschen (vor dem Anonymisieren der DB-Werte)
                delete_user_files((int) $cu['user_id']);

                // Account anonymisieren
                $result = delete_user_account((int) $cu['user_id']);

                if (!$result['ok']) {
                    $error = $result['error'] ?? 'Account konnte nicht gelöscht werden.';
                } else {
                    // Komplett ausloggen
                    try { remember_destroy_all_for_user((int) $cu['user_id']); } catch (Exception $e) {}
                    logout_user();
                    session_destroy();

                    $step = 'done';
                }
            }
        }
    }
}

render('delete_account', [
    'title' => 'Account löschen',
    'error' => $error,
    'step'  => $step,
]);
