<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
/**
 * lib/user_deletion.php
 *
 * DSGVO-konforme Account-Löschung. Strategie:
 * - Beiträge / Threads bleiben mit User-ID verknüpft (für Diskussions-Kontext)
 * - PMs bleiben beim anderen User sichtbar (Empfänger-Daten)
 * - Username wird zu „Gelöschter Benutzer #ID"
 * - Persönliche Felder (Email, Avatar, Bio, Signatur) werden geleert
 * - Login unmöglich (Passwort-Hash auf '' gesetzt)
 * - Remember-Tokens, Sessions, Notifications, Bookmarks: alle weg
 *
 * Rückgabe: ['ok' => bool, 'error' => string]
 */

/**
 * Prüft ob ein User der einzige Owner ist (Sicherheits-Check).
 */
function is_last_owner(int $userId): bool {
    try {
        $owners = db_count('SELECT COUNT(*) FROM users WHERE COALESCE(is_owner, 0) = 1 AND COALESCE(is_deleted, 0) = 0');
        if ($owners <= 1) {
            // Ist der User selbst Owner?
            $u = db_fetch('SELECT is_owner FROM users WHERE user_id = ?', [$userId]);
            return $u && !empty($u['is_owner']);
        }
    } catch (Exception $e) {}
    return false;
}

/**
 * Führt die eigentliche Account-Löschung durch.
 * Gibt ['ok' => true] zurück oder ['ok' => false, 'error' => '...'].
 */
function delete_user_account(int $userId): array {
    // Sicherheits-Check: letzter Owner darf nicht gelöscht werden
    if (is_last_owner($userId)) {
        return ['ok' => false, 'error' => 'Der letzte Owner kann nicht gelöscht werden. Bitte mache einen anderen User zum Owner, bevor du diesen Account löschst.'];
    }

    try {
        // Username anonymisieren – „Gelöschter Benutzer #42"
        $newUsername = 'Gelöschter Benutzer #' . $userId;

        // Sicherstellen dass der Username eindeutig bleibt
        // (falls jemand zufällig diesen Namen schon hat – sehr unwahrscheinlich aber sauber)
        $existing = db_count('SELECT COUNT(*) FROM users WHERE username = ? AND user_id != ?', [$newUsername, $userId]);
        if ($existing > 0) {
            $newUsername .= '_' . time();
        }

        // Welche Spalten existieren tatsächlich in users? (Schema kann variieren)
        // SHOW COLUMNS liefert auch ob die Spalte NULL erlaubt – wichtig für NOT NULL Spalten wie email
        $existingCols = [];
        try {
            $cols = db_fetchAll('SHOW COLUMNS FROM users');
            foreach ($cols as $c) {
                $existingCols[$c['Field']] = [
                    'nullable' => ($c['Null'] === 'YES'),
                ];
            }
        } catch (Exception $e) {}

        // Eindeutiger Email-Platzhalter (wegen evtl. UNIQUE-Constraint)
        $deletedEmail = 'deleted_' . $userId . '_' . time() . '@deleted.local';

        // Diese Spalten werden geleert – jeweils mit „NULL falls erlaubt, sonst leerer String"
        // Format: spaltenname => [nullable_value, notnull_value, needsParam (bool, default false)]
        $clearMap = [
            'email'          => ['NULL', '?', true, $deletedEmail],  // hat UNIQUE-Constraint → eindeutiger Platzhalter
            'avatar'         => ['NULL', "''"],
            'profile_banner' => ['NULL', "''"],
            'banner'         => ['NULL', "''"],
            'bio'            => ['NULL', "''"],
            'signature'      => ['NULL', "''"],
            'location'       => ['NULL', "''"],
            'website'        => ['NULL', "''"],
            'last_ip'        => ['NULL', "''"],
            'registration_ip'=> ['NULL', "''"],
            'ban_reason'     => ['NULL', "''"],
        ];

        $setParts = [
            'username    = ?',
            'password    = ""',
            '`rank`      = "Mitglied"',
            'is_admin    = 0',
            'is_mod      = 0',
            'is_owner    = 0',
            'is_banned   = 0',
            'is_deleted  = 1',
            'deleted_at  = NOW()',
        ];
        $params = [$newUsername];

        foreach ($clearMap as $col => $spec) {
            if (!isset($existingCols[$col])) continue;
            $nullValue   = $spec[0];
            $notNullValue= $spec[1];
            $hasParam    = $spec[2] ?? false;
            $paramValue  = $spec[3] ?? null;

            $valueExpr = $existingCols[$col]['nullable'] ? $nullValue : $notNullValue;
            $setParts[] = "`$col` = $valueExpr";
            if ($hasParam && !$existingCols[$col]['nullable']) {
                // Nur wenn wir den NOT-NULL-Pfad gehen und der Wert ein Parameter ist
                $params[] = $paramValue;
            }
        }

        $params[] = $userId;
        $sql = 'UPDATE users SET ' . implode(', ', $setParts) . ' WHERE user_id = ?';
        db_execute($sql, $params);

        // Remember-Tokens komplett löschen (kein Auto-Login mehr möglich)
        try {
            db_execute('DELETE FROM remember_tokens WHERE user_id = ?', [$userId]);
        } catch (Exception $e) {}

        // User aus user_online entfernen
        try {
            db_execute('DELETE FROM user_online WHERE user_id = ?', [$userId]);
        } catch (Exception $e) {}

        // Eigene Benachrichtigungen löschen
        try {
            db_execute('DELETE FROM notifications WHERE user_id = ?', [$userId]);
        } catch (Exception $e) {}

        // Eigene Lesezeichen löschen
        try {
            db_execute('DELETE FROM bookmarks WHERE user_id = ?', [$userId]);
        } catch (Exception $e) {}

        // Eigene Thread-Abos löschen
        try {
            db_execute('DELETE FROM thread_subscriptions WHERE user_id = ?', [$userId]);
        } catch (Exception $e) {}

        // Forum-Mod-Einträge löschen
        try {
            db_execute('DELETE FROM board_moderators WHERE user_id = ?', [$userId]);
        } catch (Exception $e) {}

        // 2FA-Daten löschen
        try {
            db_execute('DELETE FROM user_2fa WHERE user_id = ?', [$userId]);
        } catch (Exception $e) {}

        // Achievements löschen
        try {
            db_execute('DELETE FROM user_achievements WHERE user_id = ?', [$userId]);
        } catch (Exception $e) {}

        // Email-Verify-Tokens und Passwort-Reset-Tokens
        try {
            db_execute('DELETE FROM email_verifications WHERE user_id = ?', [$userId]);
            db_execute('DELETE FROM password_resets WHERE user_id = ?', [$userId]);
        } catch (Exception $e) {}

        // Reads (gelesene Threads) – persönliche Daten, weg damit
        try {
            db_execute('DELETE FROM thread_reads WHERE user_id = ?', [$userId]);
        } catch (Exception $e) {}

        // Avatar-Datei vom Server löschen (falls vorhanden)
        // Geht erst NACH dem UPDATE damit wir aus dem User noch den alten Avatar lesen können
        // → Daher hier nicht mehr, das machen wir in einem separaten Schritt vor dem Update.

        return ['ok' => true];

    } catch (Exception $e) {
        return ['ok' => false, 'error' => 'Datenbank-Fehler: ' . $e->getMessage()];
    }
}

/**
 * Löscht die persönlichen Dateien (Avatar, Banner) des Users vom Server.
 * Sollte VOR delete_user_account() aufgerufen werden.
 */
function delete_user_files(int $userId): void {
    try {
        // Profile-Banner-Spalte ist optional – defensiv abfragen
        $u = db_fetch('SELECT * FROM users WHERE user_id = ?', [$userId]);
        if (!$u) return;

        $uploadsDir = __DIR__ . '/../uploads/';

        // Avatar löschen (nur wenn lokal, kein URL)
        if (!empty($u['avatar']) && !str_starts_with($u['avatar'], 'http')) {
            $path = $uploadsDir . 'avatars/' . basename($u['avatar']);
            if (file_exists($path) && is_file($path)) {
                @unlink($path);
            }
        }

        // Banner löschen – Spalten-Namen kann je nach Migration variieren
        $bannerField = $u['profile_banner'] ?? $u['banner'] ?? null;
        if (!empty($bannerField) && !str_starts_with($bannerField, 'http')) {
            $path = $uploadsDir . 'banners/' . basename($bannerField);
            if (file_exists($path) && is_file($path)) {
                @unlink($path);
            }
        }
    } catch (Exception $e) {}
}

/**
 * Prüft ob ein User-Datensatz einen gelöschten Account repräsentiert.
 */
function is_deleted_user(array $user): bool {
    return !empty($user['is_deleted']);
}

/**
 * Prüft ob ein User sich selbst löschen darf.
 * Gibt entweder ['ok' => true] oder ['ok' => false, 'reason' => '...'] zurück.
 *
 * Sperren:
 * - Founder (User-ID 1) kann sich NIE selbst löschen (Lock-Out-Schutz)
 * - Letzter Owner darf sich nicht selbst löschen
 */
function can_user_delete_self(int $userId): array {
    if ($userId === 1) {
        return [
            'ok' => false,
            'reason' => 'Der Forum-Gründer (User-ID 1) kann seinen Account nicht selbst löschen. Das ist ein Schutz gegen versehentlichen Lock-Out des Forums.'
        ];
    }
    if (is_last_owner($userId)) {
        return [
            'ok' => false,
            'reason' => 'Du bist der einzige verbliebene Owner. Bitte mache zuerst einen anderen Benutzer zum Owner, bevor du deinen Account löschst.'
        ];
    }
    return ['ok' => true];
}
