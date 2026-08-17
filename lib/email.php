<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// lib/email.php – E-Mail-Versand via PHP mail() oder echtem SMTP (mit Auth)

/**
 * Sendet eine E-Mail.
 * Nutzt SMTP wenn smtp_host gesetzt ist, sonst Fallback auf PHP mail().
 *
 * Return: true bei Erfolg, false bei Fehler.
 */
/**
 * Entfernt alles, was eine Kopfzeile oder einen SMTP-Befehl beenden koennte:
 * CR, LF und NUL. Keine Maskierung, kein Ersetzen — solche Zeichen haben in
 * einem Absendernamen oder einer Adresse nichts zu suchen.
 */
function mail_kopf_saeubern(string $wert): string {
    return trim(str_replace(["\r", "\n", "\0"], '', $wert));
}

function send_email(string $to, string $subject, string $body, bool $html = true): bool {
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return false;

    // Stundengrenze, falls sie in der config.php steht. Ohne die Konstante
    // aendert sich nichts.
    //
    // Gedacht fuer Installationen, die jemand anderes betreibt: der Versand
    // laeuft ueber dessen Server und dessen Ruf. Ein Forum, das ploetzlich
    // tausend Einladungen verschickt, kostet nicht sich selbst die
    // Zustellbarkeit, sondern alle anderen auf derselben Maschine.
    //
    // Der Zaehler gilt fuer die ganze Installation, nicht je Empfaenger —
    // sonst waere er wirkungslos: man schickt ja an viele verschiedene.
    if (defined('FORUM_MAIL_MAX_PER_HOUR') && (int) FORUM_MAIL_MAX_PER_HOUR > 0) {
        if (!rate_limit_check('mail_out', (int) FORUM_MAIL_MAX_PER_HOUR, 3600, 'instanz')) {
            // Nicht stumm verschlucken: eine Mail, die nie ankommt, sucht
            // sonst niemand — und der Betreiber wundert sich, warum die
            // Bestaetigung ausbleibt.
            error_log(sprintf(
                'Forumix: Mail-Stundengrenze von %d erreicht, Nachricht an %s nicht verschickt (%s)',
                (int) FORUM_MAIL_MAX_PER_HOUR, $to, $subject
            ));
            return false;
        }
    }

    // Steht der Absender in der config.php, gewinnt er immer. Gedacht fuer
    // Installationen, die jemand anderes betreibt als der Admin des Forums:
    // dort haengt der Versand an fremder Reputation, und ein frei gesetzter
    // Absender kostet nicht den Absender, sondern den Betreiber.
    // Ohne die Konstante bleibt alles wie gehabt — Feld im Adminbereich.
    $fromAddr = defined('FORUM_MAIL_FROM')
        ? (string) FORUM_MAIL_FROM
        : (string) get_setting('smtp_from', '');
    $fromName = defined('FORUM_MAIL_FROM_NAME')
        ? (string) FORUM_MAIL_FROM_NAME
        : (string) get_setting('smtp_from_name', FORUM_NAME);

    // Zeilenumbrueche raus, bevor irgendetwas davon in eine Kopfzeile oder in
    // einen SMTP-Befehl geraet. Beide Werte kommen aus einem Eingabefeld im
    // Adminbereich; ein \r\n darin haengt eine zweite Kopfzeile an (Bcc: …)
    // oder ein zweites RCPT TO in den SMTP-Dialog. Der Empfaenger wird schon
    // seit jeher geprueft, der Absender war es nie.
    $fromName = mail_kopf_saeubern($fromName);
    $fromAddr = mail_kopf_saeubern($fromAddr);
    if ($fromAddr !== '' && !filter_var($fromAddr, FILTER_VALIDATE_EMAIL)) {
        error_log('[mail] Absenderadresse unbrauchbar, benutze Standard: ' . $fromAddr);
        $fromAddr = '';
    }

    if (!$fromAddr) {
        $host = parse_url(FORUM_URL, PHP_URL_HOST) ?: 'localhost';
        // Ein fuehrendes www. faellt weg: DKIM-Signierung und SPF haengen fast
        // immer an der Domain ohne, und eine Absenderadresse auf einer
        // Subdomain ohne eigene Eintraege geht unbeglaubigt raus.
        //
        // Bei anderen Subdomains (forum.example.com) hilft das nicht — welcher
        // Teil die eigentliche Domain ist, laesst sich ohne Liste nicht sagen.
        // Deshalb steht am Feld im Adminbereich, dass man es setzen soll.
        if (str_starts_with($host, 'www.')) $host = substr($host, 4);
        $fromAddr = 'noreply@' . $host;
    }

    $smtpHost = (string) get_setting('smtp_host', '');

    // Wenn SMTP konfiguriert ist: echten SMTP-Versand probieren
    if ($smtpHost !== '') {
        return _send_via_smtp($to, $subject, $body, $html, $fromAddr, $fromName);
    }

    // Fallback: PHP mail()
    $headers = [
        'From: ' . $fromName . ' <' . $fromAddr . '>',
        'Reply-To: ' . $fromAddr,
        'X-Mailer: PHP/' . phpversion(),
        'MIME-Version: 1.0',
        'Content-Type: ' . ($html ? 'text/html' : 'text/plain') . '; charset=UTF-8',
    ];
    $subjectEnc = '=?UTF-8?B?' . base64_encode($subject) . '?=';

    try {
        return @mail($to, $subjectEnc, $body, implode("\r\n", $headers));
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Sendet eine E-Mail per echtem SMTP (mit AUTH LOGIN).
 * Unterstützt STARTTLS und SSL/TLS.
 *
 * Return: true bei Erfolg, false bei Fehler. Detail-Fehler im Log.
 */
function _send_via_smtp(string $to, string $subject, string $body, bool $html,
                       string $fromAddr, string $fromName): bool {

    $host = (string) get_setting('smtp_host', '');
    $port = (int)    get_setting('smtp_port', 587);
    $user = (string) get_setting('smtp_user', '');
    // Kann verschluesselt abgelegt sein, siehe lib/crypto.php. Ohne
    // Vorzeichen kommt der Wert unveraendert zurueck.
    $pass = forum_decrypt((string) get_setting('smtp_pass', ''));
    $enc  = (string) get_setting('smtp_encryption', 'tls');

    $timeout = 10;
    $errno = 0; $errstr = '';

    // Verbindungstyp bestimmen
    $remote = ($enc === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;

    $sock = @stream_socket_client($remote, $errno, $errstr, $timeout);
    if (!$sock) {
        error_log('SMTP-Verbindung fehlgeschlagen: ' . $errstr);
        return false;
    }
    stream_set_timeout($sock, $timeout);

    $read = function () use ($sock) {
        $data = '';
        while ($line = fgets($sock, 515)) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return $data;
    };
    $send = function ($cmd) use ($sock) { fwrite($sock, $cmd . "\r\n"); };

    $expect = function ($code) use ($read) {
        $r = $read();
        if (strpos($r, $code) !== 0) {
            error_log("SMTP-Fehler erwartet $code: " . trim($r));
            return false;
        }
        return true;
    };

    if (!$expect('220')) { fclose($sock); return false; }

    $myHost = parse_url(FORUM_URL, PHP_URL_HOST) ?: 'localhost';
    $send('EHLO ' . $myHost);
    if (!$expect('250')) { fclose($sock); return false; }

    // STARTTLS wenn nötig
    if ($enc === 'tls') {
        $send('STARTTLS');
        if (!$expect('220')) { fclose($sock); return false; }
        if (!@stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            error_log('STARTTLS fehlgeschlagen');
            fclose($sock);
            return false;
        }
        $send('EHLO ' . $myHost);
        if (!$expect('250')) { fclose($sock); return false; }
    }

    // AUTH LOGIN
    if ($user !== '') {
        $send('AUTH LOGIN');
        if (!$expect('334')) { fclose($sock); return false; }
        $send(base64_encode($user));
        if (!$expect('334')) { fclose($sock); return false; }
        $send(base64_encode($pass));
        if (!$expect('235')) { fclose($sock); return false; }
    }

    $send('MAIL FROM: <' . $fromAddr . '>');
    if (!$expect('250')) { fclose($sock); return false; }

    $send('RCPT TO: <' . mail_kopf_saeubern($to) . '>');
    if (!$expect('250')) { fclose($sock); return false; }

    $send('DATA');
    if (!$expect('354')) { fclose($sock); return false; }

    $subjectEnc = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $contentType = $html ? 'text/html' : 'text/plain';

    // Body als Base64 enkodieren – sicherer als quoted-printable:
    // 1. Keine Probleme mit zu langen Zeilen (HTML mit inline-CSS ist oft >76 Zeichen)
    // 2. UTF-8-Zeichen sauber transportiert
    // 3. Wird von allen Mail-Clients korrekt dekodiert (GMX/Outlook/Gmail)
    // chunk_split fügt RFC-konforme CRLF alle 76 Zeichen ein
    $encodedBody = chunk_split(base64_encode($body), 76, "\r\n");

    $headers = [
        'From: ' . $fromName . ' <' . $fromAddr . '>',
        'To: ' . $to,
        'Subject: ' . $subjectEnc,
        'Reply-To: ' . $fromAddr,
        'Date: ' . date('r'),
        'Message-ID: <' . uniqid('', true) . '@' . $myHost . '>',
        'MIME-Version: 1.0',
        'Content-Type: ' . $contentType . '; charset=UTF-8',
        'Content-Transfer-Encoding: base64',
        'X-Mailer: Forumix',
    ];

    fwrite($sock, implode("\r\n", $headers) . "\r\n\r\n" . $encodedBody . "\r\n.\r\n");
    if (!$expect('250')) { fclose($sock); return false; }

    $send('QUIT');
    fclose($sock);
    return true;
}

/**
 * E-Mail-Template-Wrapper mit Schwarz/Grau-Design
 */
function email_template(string $title, string $bodyHtml): string {
    $forum = e(forum_name());
    $url   = e(FORUM_URL);
    return '<!DOCTYPE html>
<html><head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f5f5f5;font-family:Arial,sans-serif;color:#1a1a1a">
<table cellpadding="0" cellspacing="0" width="100%" style="background:#f5f5f5;padding:24px 0">
<tr><td align="center">
  <table cellpadding="0" cellspacing="0" width="560" style="background:#fff;border:1px solid #ddd;border-radius:8px;overflow:hidden">
    <tr><td style="background:#1a1a1a;color:#fff;padding:20px 28px;font-size:20px;font-weight:bold">
      ' . $forum . '
    </td></tr>
    <tr><td style="padding:28px">
      <h2 style="margin:0 0 16px;font-size:18px;color:#1a1a1a">' . e($title) . '</h2>
      <div style="font-size:14px;line-height:1.6;color:#333">
        ' . $bodyHtml . '
      </div>
    </td></tr>
    <tr><td style="background:#f5f5f5;padding:14px 28px;font-size:11px;color:#888;border-top:1px solid #ddd">
      Diese E-Mail wurde von <a href="' . $url . '" style="color:#1a1a1a">' . $forum . '</a> gesendet.
    </td></tr>
  </table>
</td></tr></table>
</body></html>';
}
