<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// board_unlock.php – Passwort-Eingabe für geschützte Foren
require_once __DIR__ . '/../bootstrap.php';

$boardId = get_int('id');
$board = db_fetch('
    SELECT b.*, c.title AS cat_title
    FROM boards b JOIN categories c ON c.cat_id = b.cat_id
    WHERE b.board_id = ?
', [$boardId]);

if (!$board) {
    http_response_code(404);
    error_page(404, t('error_board_missing_title'), t('error_board_missing_text'));
}

// Wenn das Board nicht (mehr) geschützt ist oder der User bereits Zugang hat → direkt rein
if (board_user_can_access($board)) {
    redirect(FORUM_URL . '/board.php?id=' . $boardId);
}

// Fehlt die Gruppe, gibt es hier nichts zu entsperren. Das Formular ueberhaupt
// zu zeigen waere eine Einladung zum Raten — und die Seite selbst schon eine
// Auskunft darueber, dass es dieses Forum gibt.
if (board_zugangsgrund($board) === 'gruppe') {
    http_response_code(404);
    error_page(404, t('error_board_missing_title'), t('error_board_missing_text'));
}

// Login erforderlich (Gäste können nicht entsperren)
if (!is_logged_in()) {
    flash(t('board_unlock_login_required'), 'info');
    redirect(FORUM_URL . '/login.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf(post_str('csrf'))) error_page(400, t('error_csrf_title'), t('error_csrf_text'));

    $pw = (string) ($_POST['password'] ?? '');

    // Sanftes Rate-Limit gegen Brute-Force (per User+Board, 5 Versuche/15 Min)
    $rateKey = 'board_unlock_' . $boardId;
    if (!rate_limit_check($rateKey, 5, 900)) {
        $error = tn('board_unlock_too_many', (int) ceil(rate_limit_remaining($rateKey) / 60));
    } else {
        if (board_check_password($boardId, $pw)) {
            board_unlock_in_session($boardId);
            rate_limit_reset($rateKey);
            flash(t('board_unlock_done'), 'success');
            redirect(FORUM_URL . '/board.php?id=' . $boardId);
        } else {
            $error = t('error_wrong_password');
        }
    }
}

render('board_unlock', [
    'title' => '' . $board['title'],
    'board' => $board,
    'error' => $error,
]);
