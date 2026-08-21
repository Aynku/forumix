<?php
/**
 * Das Bild einer Gruppe steht hinter den festen Rollen.
 *
 * Gruppen koennen ein Bild tragen — gedacht fuer Konten, die keine Person
 * sind. Die Reihenfolge ist dabei das, was still falsch werden kann: Ein
 * Moderator, der nebenbei in einer Gruppe mit Bild steht, muss sein
 * Rollenbild behalten. Andersherum saehe man einem Beitrag nicht mehr an,
 * dass er von der Moderation kommt.
 *
 * Geprueft wird ausserdem, dass ein selbst hochgeladenes Bild alles schlaegt
 * — es ist die einzige Wahl, die der Mensch selbst getroffen hat.
 */

if (!defined('FORUM_PRUEFLAUF')) { http_response_code(403); die('Forbidden'); }

$wurzel = defined('FORUM_WURZEL') ? FORUM_WURZEL : dirname(__DIR__, 2);
$konfig = (defined('PRUEFSTAND') && PRUEFSTAND !== '') ? PRUEFSTAND . '/config.php' : '';
if ($konfig === '' || !is_file($konfig)) {
    return ['Gruppenbild (uebersprungen, kein Pruefstand)' => fn() => null];
}

$db   = getenv('FORUM_TEST_DB') ?: 'forumix_test';
$roh  = file_get_contents($konfig);
$hole = function (string $name) use ($roh): string {
    preg_match("/define\(\s*'$name'\s*,\s*'([^']*)'/", $roh, $t);
    return $t[1] ?? '';
};
$pdo = new PDO("mysql:host={$hole('DB_HOST')};dbname=$db;charset=utf8mb4",
               $hole('DB_USER'), $hole('DB_PASS'), PRUEF_PDO_OPTIONEN);

$GLOBALS['__pdo'] = $GLOBALS['__pdo'] ?? $pdo;
if (!function_exists('db_fetch')) {
    function db_fetch(string $sql, array $p = []) {
        $s = $GLOBALS['__pdo']->prepare($sql); $s->execute($p); return $s->fetch(PDO::FETCH_ASSOC);
    }
}
if (!function_exists('db_execute')) {
    function db_execute(string $sql, array $p = []) {
        $s = $GLOBALS['__pdo']->prepare($sql); $s->execute($p);
        return $GLOBALS['__pdo']->lastInsertId();
    }
}

/* Die Spalte gibt es erst ab v31 — ohne sie hat die Pruefung keinen Gegenstand. */
$spalteDa = (bool) $pdo->query("SHOW COLUMNS FROM user_groups LIKE 'avatar'")->fetch();
if (!$spalteDa) {
    return ['Gruppenbild (uebersprungen, Anpassung v31 fehlt)' => fn() => null];
}

/**
 * Legt Gruppe, Bilddatei und ein Konto an, ruft den Koerper und raeumt auf.
 * $rolle setzt eines der festen Kennzeichen.
 */
$buehne = function (callable $koerper, string $rolle = '') use ($pdo) {
    $ordner = rtrim(PRUEFSTAND, '/') . '/uploads/gruppen/';
    if (!is_dir($ordner)) @mkdir($ordner, 0755, true);
    $datei = 'pruef_' . bin2hex(random_bytes(4)) . '.png';
    file_put_contents($ordner . $datei, base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));

    // Slug und Benutzername sind eindeutig — mit festen Namen scheitert schon
    // der zweite Fall, wenn der erste etwas liegenliess.
    $kennung = bin2hex(random_bytes(4));
    $pdo->exec("INSERT INTO user_groups (name, slug, show_badge, position, avatar)
                VALUES ('Pruef-Bild', 'pruef_bild_$kennung', 1, 60, '$datei')");
    $gid = (int) $pdo->lastInsertId();


    $pdo->exec("INSERT INTO users (username, password, email, registered_at)
                VALUES ('pruef_bild_$kennung', 'x', 'bild_$kennung@example.invalid', NOW())");
    $uid = (int) $pdo->lastInsertId();
    if ($rolle !== '') $pdo->exec("UPDATE users SET `$rolle` = 1 WHERE user_id = $uid");
    $pdo->exec("INSERT INTO group_members (group_id, user_id) VALUES ($gid, $uid)");

    try {
        $koerper($uid, $gid, $datei);
    } finally {
        @unlink($ordner . $datei);
        $pdo->exec("DELETE FROM group_members WHERE group_id = $gid");
        $pdo->exec("DELETE FROM user_groups WHERE group_id = $gid");
        $pdo->exec("DELETE FROM users WHERE user_id = $uid");
    }
};

/** Ruft avatar_url() in einem eigenen Prozess — die Funktion merkt sich Ergebnisse. */
$bildFuer = function (int $uid) use ($wurzel): string {
    $php = escapeshellarg(PHP_BINARY);
    $inst = escapeshellarg(PRUEFSTAND);
    $skript = 'define("FORUM_RUNNING", true); ob_start(); require ' . $inst . ' . "/bootstrap.php"; ob_end_clean();'
            . '$u = db_fetch("SELECT * FROM users WHERE user_id = ?", [' . $uid . ']);'
            . 'echo $u ? basename(avatar_url($u)) : "kein Konto";';
    exec(sprintf('cd %s && %s -r %s 2>&1', $inst, $php, escapeshellarg($skript)), $aus, $rc);
    return trim(implode(' ', $aus));
};

return [
    'Wer in einer Gruppe mit Bild ist, bekommt es' => function () use ($buehne, $bildFuer) {
        $buehne(function (int $uid, int $gid, string $datei) use ($bildFuer) {
            $ist = $bildFuer($uid);
            if ($ist !== $datei) throw new RuntimeException("Bekommen: \"$ist\", erwartet: \"$datei\"");
        });
    },

    'Ein Moderator behaelt sein Rollenbild' => function () use ($buehne, $bildFuer) {
        // Sonst saehe man einem Beitrag nicht mehr an, dass er von der
        // Moderation kommt — nur weil jemand nebenbei in einer Gruppe steht.
        $buehne(function (int $uid, int $gid, string $datei) use ($bildFuer) {
            $ist = $bildFuer($uid);
            if ($ist !== 'role_mod.svg') throw new RuntimeException("Bekommen: \"$ist\", erwartet: role_mod.svg");
        }, 'is_mod');
    },

    'Ein eigenes Bild schlaegt das der Gruppe' => function () use ($buehne, $bildFuer, $pdo) {
        $buehne(function (int $uid, int $gid, string $datei) use ($bildFuer, $pdo) {
            $eigen = 'pruef_eigen_' . bin2hex(random_bytes(4)) . '.png';
            $ordner = rtrim(PRUEFSTAND, '/') . '/uploads/avatars/';
            if (!is_dir($ordner)) @mkdir($ordner, 0755, true);
            file_put_contents($ordner . $eigen, base64_decode(
                'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
            $pdo->exec("UPDATE users SET avatar = '$eigen' WHERE user_id = $uid");
            try {
                $ist = $bildFuer($uid);
                if ($ist !== $eigen) throw new RuntimeException("Bekommen: \"$ist\", erwartet: \"$eigen\"");
            } finally {
                @unlink($ordner . $eigen);
            }
        });
    },

    'Fehlt die Datei, bleibt es beim Standard' => function () use ($buehne, $bildFuer) {
        // Ein Eintrag ohne Datei darf keinen toten Verweis liefern.
        $buehne(function (int $uid, int $gid, string $datei) use ($bildFuer) {
            @unlink(rtrim(PRUEFSTAND, '/') . '/uploads/gruppen/' . $datei);
            $ist = $bildFuer($uid);
            if ($ist !== 'default_avatar.svg') {
                throw new RuntimeException("Bekommen: \"$ist\", erwartet: default_avatar.svg");
            }
        });
    },
];
