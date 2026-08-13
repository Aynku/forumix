<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// lib/invitations.php – Einladungs-System
// Codes umgehen die Admin-Approval-Pflicht: Wer einen gültigen Code mitbringt
// wird direkt freigeschaltet (is_approved=1) ohne in die Queue zu kommen.

/**
 * Generiert einen lesbaren Code: 4-4-Format (z.B. "X7K2-9PQM")
 * Vermeidet I/O/0/1/L für bessere Ablesbarkeit.
 */
function invitation_generate_code(): string {
    $chars = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $code = '';
    for ($i = 0; $i < 8; $i++) {
        if ($i === 4) $code .= '-';
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $code;
}

/**
 * Erstellt eine neue Einladung.
 * @param int     $adminId   wer hat die Einladung erstellt
 * @param ?string $email     optional E-Mail-Bindung (NULL = universal)
 * @param ?string $note      interne Notiz
 * @param int     $validDays Gültigkeit in Tagen (default 7)
 * @return ?array Die erstellte Einladung als Array, oder NULL bei Fehler
 */
function invitation_create(int $adminId, ?string $email = null, ?string $note = null, int $validDays = 7): ?array {
    if ($validDays < 1) $validDays = 7;
    if ($validDays > 365) $validDays = 365;
    if ($email !== null) {
        $email = trim($email);
        if ($email === '') $email = null;
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) return null;
    }
    if ($note !== null) {
        $note = trim($note);
        if ($note === '') $note = null;
        elseif (mb_strlen($note) > 255) $note = mb_substr($note, 0, 255);
    }

    // Code erzeugen, Kollisionen abfangen (extrem unwahrscheinlich, aber safe)
    for ($try = 0; $try < 5; $try++) {
        $code = invitation_generate_code();
        $exists = db_count('SELECT COUNT(*) FROM invitations WHERE code = ?', [$code]);
        if ($exists === 0) break;
        $code = null;
    }
    if ($code === null) return null;

    try {
        db_execute(
            'INSERT INTO invitations (code, email, note, created_by, expires_at)
             VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? DAY))',
            [$code, $email, $note, $adminId, $validDays]
        );

        // Mod-Log
        try {
            $emailPart = $email ? " an {$email}" : ' (universal)';
            db_execute(
                'INSERT INTO mod_log (mod_id, action, target_type, target_id, reason)
                 VALUES (?, ?, ?, ?, ?)',
                [$adminId, 'invite_create', 'invitation', 0, "Code {$code} erstellt{$emailPart}"]
            );
        } catch (Exception $e) {}

        return db_fetch('SELECT * FROM invitations WHERE code = ?', [$code]);
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Sucht eine Einladung anhand des Codes.
 * Liefert auch verbrauchte/abgelaufene/widerrufene zurück (für Status-Anzeige).
 */
function invitation_find_by_code(string $code): ?array {
    $code = strtoupper(trim($code));
    try {
        $row = db_fetch('SELECT * FROM invitations WHERE code = ?', [$code]);
        return $row ?: null;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Liefert den Gültigkeits-Status einer Einladung.
 * @return string  'valid' | 'used' | 'expired' | 'revoked' | 'not_found' | 'email_mismatch'
 */
function invitation_status(?array $inv, ?string $forEmail = null): string {
    if (!$inv) return 'not_found';
    if (!empty($inv['revoked_at'])) return 'revoked';
    if (!empty($inv['used_by']))    return 'used';
    if (strtotime($inv['expires_at']) < time()) return 'expired';
    if ($forEmail !== null && !empty($inv['email'])
        && strcasecmp($inv['email'], $forEmail) !== 0) {
        return 'email_mismatch';
    }
    return 'valid';
}

/**
 * Markiert eine Einladung als verbraucht. Nur aufrufen nach erfolgreicher
 * Registrierung. Idempotent: bei doppelter Verwendung false.
 */
function invitation_consume(int $invitationId, int $userId): bool {
    try {
        $stmt = DB::get()->prepare(
            'UPDATE invitations
             SET used_by = ?, used_at = NOW()
             WHERE invitation_id = ?
               AND used_by IS NULL
               AND revoked_at IS NULL
               AND expires_at > NOW()'
        );
        $stmt->execute([$userId, $invitationId]);
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Markiert eine Einladung als widerrufen.
 * Schon verbrauchte können nicht widerrufen werden (User existiert ja schon).
 */
function invitation_revoke(int $invitationId, int $adminId): bool {
    try {
        $inv = db_fetch('SELECT * FROM invitations WHERE invitation_id = ?', [$invitationId]);
        if (!$inv) return false;
        if (!empty($inv['used_by']))    return false;
        if (!empty($inv['revoked_at'])) return true;  // schon widerrufen

        db_execute(
            'UPDATE invitations SET revoked_at = NOW() WHERE invitation_id = ?',
            [$invitationId]
        );

        try {
            db_execute(
                'INSERT INTO mod_log (mod_id, action, target_type, target_id, reason)
                 VALUES (?, ?, ?, ?, ?)',
                [$adminId, 'invite_revoke', 'invitation', $invitationId, "Code {$inv['code']} widerrufen"]
            );
        } catch (Exception $e) {}

        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Liefert alle Einladungen für Admin-Übersicht.
 * Gruppierung wird in der View gemacht.
 */
function invitations_list(int $limit = 100): array {
    try {
        return db_fetchAll(
            'SELECT i.*,
                    creator.username AS creator_username,
                    consumer.username AS used_by_username
             FROM invitations i
             LEFT JOIN users creator  ON creator.user_id  = i.created_by
             LEFT JOIN users consumer ON consumer.user_id = i.used_by
             ORDER BY i.created_at DESC
             LIMIT ?',
            [$limit]
        );
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Anzahl der aktuell aktiven (gültigen) Einladungen.
 */
function invitations_active_count(): int {
    try {
        return (int) db_count(
            'SELECT COUNT(*) FROM invitations
             WHERE used_by IS NULL AND revoked_at IS NULL AND expires_at > NOW()'
        );
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Sendet eine Einladungsmail mit dem Code + Link.
 * @return bool  Versand erfolgreich?
 */
function invitation_send_email(array $inv, ?string $overrideEmail = null): bool {
    $email = $overrideEmail ?: ($inv['email'] ?? null);
    if (!$email) return false;
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return false;
    if (!function_exists('send_email')) return false;

    $forum   = forum_name();
    $code    = $inv['code'];
    $expires = date('d.m.Y H:i', strtotime($inv['expires_at']));
    $link    = FORUM_URL . '/register.php?invite=' . urlencode($code);

    $body = email_template('✉️ Einladung zu ' . $forum,
        '<p>Hallo,</p>'
      . '<p>du wurdest persönlich eingeladen, dich bei <strong>' . e($forum) . '</strong> zu registrieren!</p>'
      . '<p>Dein persönlicher Einladungs-Code:</p>'
      . '<div style="background:#1a1a1a;color:#fff;padding:18px 24px;border-radius:8px;'
      . 'text-align:center;font-family:Courier New,monospace;font-size:24px;font-weight:bold;'
      . 'letter-spacing:3px;margin:18px 0">' . e($code) . '</div>'
      . '<p>Klicke einfach auf den folgenden Link, der Code ist dann automatisch eingetragen:</p>'
      . '<p><a href="' . e($link) . '" style="display:inline-block;background:#1a1a1a;color:#fff;'
      . 'padding:10px 20px;text-decoration:none;border-radius:6px">→ Jetzt registrieren</a></p>'
      . '<p style="font-size:12px;color:#888;margin-top:24px">'
      . '⏰ Der Code ist gültig bis <strong>' . e($expires) . '</strong> '
      . 'und kann nur <strong>einmal</strong> verwendet werden.<br>'
      . 'Falls du diese E-Mail nicht erwartet hast, ignoriere sie einfach.</p>'
    );

    $ok = @send_email($email, "[{$forum}] Deine Einladung", $body);

    if ($ok) {
        try {
            db_execute(
                'UPDATE invitations SET mail_sent_at = NOW() WHERE invitation_id = ?',
                [$inv['invitation_id']]
            );
        } catch (Exception $e) {}
    }

    return $ok;
}

/**
 * Räumt alte Einladungen auf (z.B. via Cron oder Wartung).
 * Löscht: verbrauchte > 90 Tage, abgelaufene > 30 Tage.
 */
function invitations_cleanup(): int {
    try {
        $pdo = DB::get();
        $a = $pdo->prepare('DELETE FROM invitations WHERE used_at IS NOT NULL AND used_at < DATE_SUB(NOW(), INTERVAL 90 DAY)');
        $a->execute();
        $b = $pdo->prepare('DELETE FROM invitations WHERE used_at IS NULL AND expires_at < DATE_SUB(NOW(), INTERVAL 30 DAY)');
        $b->execute();
        return $a->rowCount() + $b->rowCount();
    } catch (Exception $e) {
        return 0;
    }
}
