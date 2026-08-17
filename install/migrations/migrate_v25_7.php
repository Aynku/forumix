<?php
/**
 * v0.25.7 – Zwei-Faktor: Geheimnis und Ersatzcodes verschluesseln
 *
 * Beides lag im Klartext in user_2fa. Wer einen Datenbankauszug in die Hand
 * bekommt — eine Sicherung, ein falsch abgelegter Dump, ein zweiter Dienst auf
 * derselben Datenbank — kann daraus fuer jedes Konto den zweiten Faktor
 * nachrechnen. Der Faktor ist damit keiner mehr.
 *
 * Verschluesselt wird mit demselben Verfahren wie das SMTP-Passwort
 * (forum_encrypt, AES-256-GCM). OHNE FORUM_CRYPT_KEY passiert hier nichts:
 * forum_encrypt() gibt den Wert dann unveraendert zurueck, und die Anmeldung
 * laeuft weiter wie bisher. Lieber Klartext als ausgesperrte Benutzer.
 */
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }

// Erst Platz schaffen: secret ist VARCHAR(64), der verschluesselte Wert traegt
// Vorzeichen, IV, Pruefsumme und Base64 und braucht rund 90 Zeichen. Ohne diese
// Verbreiterung endet schon das EINRICHTEN von 2FA in "Data too long" — und
// zwar unabhaengig davon, ob ein Schluessel hinterlegt ist, denn spaeter kann
// jederzeit einer dazukommen.
$spalte = db_fetch("SHOW COLUMNS FROM user_2fa LIKE 'secret'");
if ($spalte && stripos((string) $spalte['Type'], 'varchar(255)') === false) {
    db_execute('ALTER TABLE user_2fa MODIFY `secret` VARCHAR(255) NOT NULL');
    echo '<div class="req-row">user_2fa.secret von ' . htmlspecialchars((string) $spalte['Type'])
       . ' auf VARCHAR(255) verbreitert.</div>';
}

if (!function_exists('forum_crypt_available') || !forum_crypt_available()) {
    echo '<div class="req-row">Kein FORUM_CRYPT_KEY hinterlegt – die Werte bleiben im '
       . 'Klartext, und die Anmeldung funktioniert unveraendert. Wer sie verschluesseln '
       . 'will, traegt einen Schluessel in die config.php ein und faehrt diese '
       . 'Migration erneut.</div>';
    return;
}

$reihen = db_fetchAll('SELECT user_id, secret, backup_codes FROM user_2fa');
$fertig = 0;
$offen  = 0;

foreach ($reihen as $r) {
    $secret = (string) ($r['secret'] ?? '');
    $codes  = (string) ($r['backup_codes'] ?? '');

    // Schon verschluesselt? forum_decrypt() erkennt das am Vorzeichen; ein
    // bereits verschluesselter Wert wird nicht ein zweites Mal eingepackt.
    $secretNeu = ($secret !== '' && forum_decrypt($secret) === $secret)
        ? forum_encrypt($secret) : $secret;
    $codesNeu  = ($codes !== '' && forum_decrypt($codes) === $codes)
        ? forum_encrypt($codes) : $codes;

    if ($secretNeu === $secret && $codesNeu === $codes) { $offen++; continue; }

    db_execute('UPDATE user_2fa SET secret = ?, backup_codes = ? WHERE user_id = ?',
               [$secretNeu, $codesNeu, (int) $r['user_id']]);
    $fertig++;
}

echo '<div class="req-row">Zwei-Faktor: ' . (int) $fertig . ' Eintraege verschluesselt, '
   . (int) $offen . ' waren es bereits.</div>';
