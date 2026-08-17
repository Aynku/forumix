<?php
/**
 * Zwei-Faktor: ein Code gilt genau einmal — Audit-Fund vom 17.08.2026.
 *
 * Der Fund: verify_totp() merkte sich nichts. Ein abgefangener Code war bis zu
 * 90 Sekunden lang beliebig oft einloesbar, und weil forgot.php eine
 * Ruecksetzung allein gegen Benutzername + Code herausgibt, reichte EIN Code
 * fuer Ruecksetzung, Anmeldung und Zwei-Faktor-Schritt.
 *
 * Faehrt gegen die Testdatenbank (FORUM_TEST_DB, sonst forumix_test) und legt
 * sich ein eigenes Konto an, das es hinterher wieder abraeumt.
 */

if (!defined('FORUM_PRUEFLAUF')) { http_response_code(403); die('Forbidden'); }

$wurzel = dirname(__DIR__, 2);
$db     = getenv('FORUM_TEST_DB') ?: 'forumix_test';

// Zugang aus der Konfiguration des Pruefstands, nie aus einem Live-Forum.
$konfig = '/root/forumix-test/instanz/config.php';
if (!is_file($konfig)) return ['Zwei-Faktor (uebersprungen, kein Pruefstand)' => fn() => null];

$roh = file_get_contents($konfig);
$hole = function (string $name) use ($roh): string {
    preg_match("/define\(\s*'$name'\s*,\s*'([^']*)'/", $roh, $t);
    return $t[1] ?? '';
};

$pdo = new PDO(
    "mysql:host={$hole('DB_HOST')};dbname=$db;charset=utf8mb4",
    $hole('DB_USER'), $hole('DB_PASS'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// twofactor.php entschluesselt beim Lesen — crypto.php gehoert dazu.
if (!defined('CRYPT_PREFIX')) require_once $wurzel . '/lib/crypto.php';
require_once $wurzel . '/lib/twofactor.php';

/** Die geprueften Funktionen brauchen db_fetch/db_affected — hier auf $pdo gelegt. */
if (!function_exists('db_fetch')) {
    $GLOBALS['__pdo'] = $pdo;
    function db_fetch(string $sql, array $p = []) {
        $s = $GLOBALS['__pdo']->prepare($sql); $s->execute($p);
        return $s->fetch(PDO::FETCH_ASSOC);
    }
    function db_affected(string $sql, array $p = []): int {
        $s = $GLOBALS['__pdo']->prepare($sql); $s->execute($p);
        return $s->rowCount();
    }
}

$anlegen = function () use ($pdo): int {
    $pdo->prepare("INSERT INTO users (username, password, email) VALUES (?, ?, ?)")
        ->execute(['pruef_2fa_' . bin2hex(random_bytes(4)), 'x', bin2hex(random_bytes(6)) . '@example.invalid']);
    $id = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO user_2fa (user_id, secret, enabled, confirmed_at)
                   VALUES (?, 'JBSWY3DPEHPK3PXP', 1, NOW())")->execute([$id]);
    return $id;
};
$abraeumen = function (int $id) use ($pdo): void {
    $pdo->prepare('DELETE FROM user_2fa WHERE user_id = ?')->execute([$id]);
    $pdo->prepare('DELETE FROM users WHERE user_id = ?')->execute([$id]);
};

return [
    'Spalte last_window ist vorhanden' => function () use ($pdo) {
        if (!$pdo->query("SHOW COLUMNS FROM user_2fa LIKE 'last_window'")->fetch()) {
            throw new RuntimeException('user_2fa.last_window fehlt — Migration v25_6 nicht gefahren');
        }
    },

    'Ein gueltiger Code wird angenommen' => function () use ($anlegen, $abraeumen) {
        $id = $anlegen();
        try {
            $code = totp_code('JBSWY3DPEHPK3PXP', (int) floor(time() / 30));
            if (!verify_totp_for_user($id, $code)) {
                throw new RuntimeException('gueltiger Code wurde abgewiesen');
            }
        } finally { $abraeumen($id); }
    },

    'DERSELBE Code wird beim zweiten Mal abgewiesen' => function () use ($anlegen, $abraeumen) {
        $id = $anlegen();
        try {
            $code = totp_code('JBSWY3DPEHPK3PXP', (int) floor(time() / 30));
            verify_totp_for_user($id, $code);
            if (verify_totp_for_user($id, $code)) {
                throw new RuntimeException(
                    'Code war ein zweites Mal gueltig — ein abgefangener Code reicht '
                    . 'damit fuer Ruecksetzung UND Anmeldung');
            }
        } finally { $abraeumen($id); }
    },

    'Ein aelteres Zeitfenster wird nicht nachgereicht' => function () use ($anlegen, $abraeumen) {
        $id = $anlegen();
        try {
            $jetzt = (int) floor(time() / 30);
            verify_totp_for_user($id, totp_code('JBSWY3DPEHPK3PXP', $jetzt));
            $alt = totp_code('JBSWY3DPEHPK3PXP', $jetzt - 1);
            if (verify_totp_for_user($id, $alt)) {
                throw new RuntimeException('Code aus dem vorigen Fenster wurde noch angenommen');
            }
        } finally { $abraeumen($id); }
    },

    'Falscher Code bleibt falsch' => function () use ($anlegen, $abraeumen) {
        $id = $anlegen();
        try {
            if (verify_totp_for_user($id, '000000') && verify_totp_for_user($id, '123456')) {
                throw new RuntimeException('beliebiger Code wurde angenommen');
            }
        } finally { $abraeumen($id); }
    },
];
