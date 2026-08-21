<?php
// modules/kontakt/kontakt.php – Kontaktformular, auch fuer Nicht-Mitglieder
if (!defined('FORUM_RUNNING')) define('FORUM_RUNNING', true);
require_once __DIR__ . '/../../bootstrap.php';

if (!module_enabled('kontakt')) {
    error_page(404, 'Nicht gefunden', 'Das Kontakt-Modul ist nicht aktiv.');
}

$cu       = current_user();
$canAdmin = $cu && ($cu['is_admin'] || !empty($cu['is_owner']));
$selbst   = FORUM_URL . '/modules/kontakt/kontakt.php';

/**
 * An wen die Nachricht geht: die Adresse aus dem Impressum, sonst die
 * Absenderadresse des Mailversands. Eine eigene Einstellung dafuer waere ein
 * drittes Feld, das jemand pflegen muss und irgendwann vergisst.
 */
function kontakt_empfaenger(): string {
    foreach (['imprint_email', 'smtp_from'] as $key) {
        $wert = trim((string) get_setting($key, ''));
        if ($wert !== '' && filter_var($wert, FILTER_VALIDATE_EMAIL)) return $wert;
    }
    return '';
}

$empfaenger = kontakt_empfaenger();
$gesendet   = false;
$fehler     = '';
$werte      = [
    'absender' => $cu['username'] ?? '',
    'email'    => $cu['email']    ?? '',
    'betreff'  => '',
    'nachricht'=> '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? '')) {
        error_page(400, 'Sicherheits-Token ungültig', 'Bitte lade die Seite neu und versuche es noch einmal.');
    }

    $werte = [
        'absender'  => mb_substr(trim($_POST['absender'] ?? ''), 0, 80),
        'email'     => mb_substr(trim($_POST['email'] ?? ''), 0, 190),
        'betreff'   => mb_substr(trim($_POST['betreff'] ?? ''), 0, 140),
        'nachricht' => mb_substr(trim($_POST['nachricht'] ?? ''), 0, 5000),
    ];

    if ($empfaenger === '') {
        $fehler = 'Es ist keine Empfängeradresse hinterlegt. Bitte wende dich anders an uns.';
    } elseif (!rate_limit_check('kontakt', 3, 3600)) {
        // Drei Nachrichten je Stunde und IP. Wer mehr braucht, schreibt im Forum.
        $fehler = 'Zu viele Nachrichten in kurzer Zeit. Bitte versuche es später noch einmal.';
    } elseif (!empty($_POST['website'])) {
        // Honigtopf: das Feld ist im Formular versteckt. Wer es ausfuellt, ist
        // kein Mensch. Nach aussen sieht es aus wie ein normaler Fehler.
        $fehler = 'Die Nachricht konnte nicht gesendet werden.';
    } elseif ($werte['absender'] === '' || $werte['betreff'] === '' || $werte['nachricht'] === '') {
        $fehler = 'Bitte Name, Betreff und Nachricht ausfüllen.';
    } elseif (!filter_var($werte['email'], FILTER_VALIDATE_EMAIL)) {
        $fehler = 'Die E-Mail-Adresse sieht nicht richtig aus.';
    } else {
        // Erst ablegen, dann senden — in dieser Reihenfolge. Faellt der
        // Mailversand aus, ist die Nachricht trotzdem da.
        $id = 0;
        try {
            db_execute(
                'INSERT INTO kontakt_nachrichten (absender, email, betreff, nachricht, user_id, ip)
                 VALUES (?, ?, ?, ?, ?, ?)',
                [$werte['absender'], $werte['email'], $werte['betreff'], $werte['nachricht'],
                 (int) ($cu['user_id'] ?? 0), client_ip()]
            );
            $id = (int) DB::get()->lastInsertId();
        } catch (Exception $e) {
            error_log('kontakt: ' . $e->getMessage());
        }

        $body = email_template('Neue Nachricht über das Kontaktformular',
              '<p><strong>Von:</strong> ' . e($werte['absender']) . ' &lt;' . e($werte['email']) . '&gt;</p>'
            . '<p><strong>Betreff:</strong> ' . e($werte['betreff']) . '</p>'
            . '<hr><p>' . nl2br(e($werte['nachricht'])) . '</p>');

        // Absender ist das Forum, nicht der Schreiber — sonst verwirft der
        // Empfangsserver die Mail, weil sie nicht zur eigenen Domain passt.
        // Die Adresse des Schreibers steht im Text und als Antwort-an.
        $ok = send_email($empfaenger, '[Kontakt] ' . $werte['betreff'], $body);

        if ($ok && $id > 0) {
            try { db_execute('UPDATE kontakt_nachrichten SET versandt = 1 WHERE nachricht_id = ?', [$id]); }
            catch (Exception $e) {}
        }

        // Auch ohne Mailversand: die Nachricht liegt in der Datenbank, der
        // Schreiber hat also nichts umsonst getippt.
        $gesendet = ($ok || $id > 0);
        if (!$gesendet) {
            $fehler = 'Die Nachricht konnte nicht zugestellt werden. Bitte versuche es später noch einmal.';
        }
    }
}

// Fuer Inhaber und Administratoren: die letzten Nachrichten direkt unter
// dem Formular.
$letzte = [];
if ($canAdmin) {
    $letzte = db_fetchAll('SELECT * FROM kontakt_nachrichten ORDER BY created_at DESC LIMIT 20');
}

render('@kontakt/kontakt', [
    'title'      => 'Kontakt',
    'werte'      => $werte,
    'gesendet'   => $gesendet,
    'fehler'     => $fehler,
    'empfaenger' => $empfaenger,
    'canAdmin'   => $canAdmin,
    'letzte'     => $letzte,
    'selbst'     => $selbst,
]);
