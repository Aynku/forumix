<?php
/**
 * Thema verschieben: die Zaehler beider Foren muessen danach stimmen.
 *
 * Beim Verschieben wechselt ein Thema samt allen Beitraegen das Forum. Wer
 * dabei nur hoch- und runterzaehlt, schreibt jede vorhandene Abweichung fest —
 * deshalb rechnet board_zaehler_neu() nach. Genau das wird hier geprueft, samt
 * dem Fall, dass unbestaetigte Themen nicht mitzaehlen duerfen.
 *
 * Faehrt gegen die Testdatenbank (FORUM_TEST_DB, sonst forumix_test) und
 * raeumt hinterher alles wieder ab.
 */

if (!defined('FORUM_PRUEFLAUF')) { http_response_code(403); die('Forbidden'); }

$wurzel = dirname(__DIR__, 2);
$db     = getenv('FORUM_TEST_DB') ?: 'forumix_test';

$konfig = (defined('PRUEFSTAND') && PRUEFSTAND !== '') ? PRUEFSTAND . '/config.php' : '';
if (!is_file($konfig)) return ['Verschieben (uebersprungen, kein Pruefstand)' => fn() => null];

$roh  = file_get_contents($konfig);
$hole = function (string $name) use ($roh): string {
    preg_match("/define\(\s*'$name'\s*,\s*'([^']*)'/", $roh, $t);
    return $t[1] ?? '';
};

$pdo = new PDO(
    "mysql:host={$hole('DB_HOST')};dbname=$db;charset=utf8mb4",
    $hole('DB_USER'), $hole('DB_PASS'),
    PRUEF_PDO_OPTIONEN
);

$GLOBALS['__pdo'] = $GLOBALS['__pdo'] ?? $pdo;
if (!function_exists('db_execute')) {
    function db_execute(string $sql, array $p = []): bool {
        $s = $GLOBALS['__pdo']->prepare($sql);
        return $s->execute($p);
    }
}
// board_zaehler_neu() haengt seit 1.3.0 an sichtbar_sql() — was im Papierkorb
// liegt, zaehlt nicht mit.
require_once $wurzel . '/lib/papierkorb.php';
require_once $wurzel . '/lib/board_mods.php';

/** Legt Kategorie, zwei Foren, Benutzer und ein Thema mit zwei Beitraegen an. */
$aufbauen = function () use ($pdo): array {
    $pdo->prepare('INSERT INTO categories (title, position) VALUES (?, ?)')->execute(['Pruefstand', 99]);
    $cat = (int) $pdo->lastInsertId();

    $pdo->prepare('INSERT INTO boards (cat_id, title, position) VALUES (?, ?, ?)')->execute([$cat, 'Quelle', 1]);
    $quelle = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO boards (cat_id, title, position) VALUES (?, ?, ?)')->execute([$cat, 'Ziel', 2]);
    $ziel = (int) $pdo->lastInsertId();

    $pdo->prepare('INSERT INTO users (username, password, email) VALUES (?, ?, ?)')
        ->execute(['pruef_verschieben_' . bin2hex(random_bytes(4)), 'x', 'v' . bin2hex(random_bytes(4)) . '@example.invalid']);
    $user = (int) $pdo->lastInsertId();

    $pdo->prepare('INSERT INTO threads (board_id, user_id, title, is_approved) VALUES (?, ?, ?, 1)')
        ->execute([$quelle, $user, 'Wanderndes Thema']);
    $thread = (int) $pdo->lastInsertId();

    $posts = [];
    foreach (['erster', 'zweiter'] as $text) {
        $pdo->prepare('INSERT INTO posts (thread_id, user_id, content, is_approved) VALUES (?, ?, ?, 1)')
            ->execute([$thread, $user, $text]);
        $posts[] = (int) $pdo->lastInsertId();
    }

    // Zaehler absichtlich falsch setzen: die Pruefung soll das Nachrechnen
    // belegen, nicht zufaellig passende Ausgangswerte.
    $pdo->prepare('UPDATE boards SET thread_count = 5, post_count = 9  WHERE board_id = ?')->execute([$quelle]);
    $pdo->prepare('UPDATE boards SET thread_count = 7, post_count = 7  WHERE board_id = ?')->execute([$ziel]);

    return compact('cat', 'quelle', 'ziel', 'user', 'thread', 'posts');
};

$abraeumen = function (array $w) use ($pdo): void {
    $pdo->prepare('DELETE FROM posts   WHERE thread_id = ?')->execute([$w['thread']]);
    $pdo->prepare('DELETE FROM threads WHERE thread_id = ?')->execute([$w['thread']]);
    $pdo->prepare('DELETE FROM boards  WHERE board_id IN (?, ?)')->execute([$w['quelle'], $w['ziel']]);
    $pdo->prepare('DELETE FROM categories WHERE cat_id = ?')->execute([$w['cat']]);
    $pdo->prepare('DELETE FROM users   WHERE user_id = ?')->execute([$w['user']]);
};

$brett = fn(int $id) => $pdo->query("SELECT thread_count, post_count, last_post_id FROM boards WHERE board_id = $id")
                            ->fetch(PDO::FETCH_ASSOC);

return [
    'Nach dem Verschieben ist das Quellforum leer' => function () use ($aufbauen, $abraeumen, $pdo, $brett) {
        $w = $aufbauen();
        try {
            $pdo->prepare('UPDATE threads SET board_id = ? WHERE thread_id = ?')->execute([$w['ziel'], $w['thread']]);
            board_zaehler_neu($w['quelle']);
            board_zaehler_neu($w['ziel']);

            $q = $brett($w['quelle']);
            if ((int) $q['thread_count'] !== 0 || (int) $q['post_count'] !== 0) {
                throw new RuntimeException("Quelle zaehlt noch {$q['thread_count']} Themen / {$q['post_count']} Beitraege");
            }
            if ($q['last_post_id'] !== null) {
                throw new RuntimeException('Quelle zeigt noch einen letzten Beitrag');
            }
        } finally { $abraeumen($w); }
    },

    'Nach dem Verschieben zaehlt das Zielforum Thema und Beitraege' => function () use ($aufbauen, $abraeumen, $pdo, $brett) {
        $w = $aufbauen();
        try {
            $pdo->prepare('UPDATE threads SET board_id = ? WHERE thread_id = ?')->execute([$w['ziel'], $w['thread']]);
            board_zaehler_neu($w['quelle']);
            board_zaehler_neu($w['ziel']);

            $z = $brett($w['ziel']);
            if ((int) $z['thread_count'] !== 1) throw new RuntimeException("Ziel zaehlt {$z['thread_count']} Themen statt 1");
            if ((int) $z['post_count']   !== 2) throw new RuntimeException("Ziel zaehlt {$z['post_count']} Beitraege statt 2");
            if ((int) $z['last_post_id'] !== max($w['posts'])) {
                throw new RuntimeException('Ziel zeigt den falschen letzten Beitrag');
            }
        } finally { $abraeumen($w); }
    },

    'Unbestaetigte Themen zaehlen nicht mit' => function () use ($aufbauen, $abraeumen, $pdo, $brett) {
        $w = $aufbauen();
        try {
            $pdo->prepare('UPDATE threads SET board_id = ?, is_approved = 0 WHERE thread_id = ?')
                ->execute([$w['ziel'], $w['thread']]);
            board_zaehler_neu($w['ziel']);

            $z = $brett($w['ziel']);
            if ((int) $z['thread_count'] !== 0) {
                throw new RuntimeException("Ziel zaehlt ein unbestaetigtes Thema mit ({$z['thread_count']})");
            }
        } finally { $abraeumen($w); }
    },

    'Zaehler auf 0 laufen beim Verringern nicht ueber' => function () use ($aufbauen, $abraeumen, $pdo) {
        // Der Fund vom 19.08.2026: GREATEST(0, spalte - 1) schuetzt bei einer
        // UNSIGNED-Spalte nicht — die Subtraktion laeuft vorher ueber, und
        // MariaDB bricht mit 1690 ab. Getroffen hat es das Loeschen des
        // einzigen Beitrags eines Themas.
        $w = $aufbauen();
        try {
            $pdo->prepare('UPDATE threads SET reply_count = 0 WHERE thread_id = ?')->execute([$w['thread']]);
            $pdo->prepare('UPDATE boards  SET post_count = 0, thread_count = 0 WHERE board_id = ?')->execute([$w['quelle']]);

            $pdo->prepare('UPDATE threads SET reply_count = GREATEST(CAST(reply_count AS SIGNED) - 1, 0)
                           WHERE thread_id = ?')->execute([$w['thread']]);
            $pdo->prepare('UPDATE boards SET post_count = GREATEST(CAST(post_count AS SIGNED) - 1, 0)
                           WHERE board_id = ?')->execute([$w['quelle']]);

            $rc = (int) $pdo->query("SELECT reply_count FROM threads WHERE thread_id = {$w['thread']}")->fetchColumn();
            if ($rc !== 0) throw new RuntimeException("reply_count steht auf $rc statt 0");
        } finally { $abraeumen($w); }
    },
];
