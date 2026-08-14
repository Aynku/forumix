<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// lib/email.php – E-Mail-Versand via PHP mail() oder echtem SMTP (mit Auth)

/**
 * Sendet eine E-Mail.
 * Nutzt SMTP wenn smtp_host gesetzt ist, sonst Fallback auf PHP mail().
 *
 * Return: true bei Erfolg, false bei Fehler.
 */
function send_email(string $to, string $subject, string $body, bool $html = true): bool {
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return false;

    $fromAddr = (string) get_setting('smtp_from', '');
    $fromName = (string) get_setting('smtp_from_name', FORUM_NAME);

    if (!$fromAddr) {
        $host = parse_url(FORUM_URL, PHP_URL_HOST) ?: 'localhost';
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
    $pass = (string) get_setting('smtp_pass', '');
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

    $send('RCPT TO: <' . $to . '>');
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
