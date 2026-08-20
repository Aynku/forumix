<?php
/**
 * Laeuft als eigener Prozess (siehe tools/pruefungen/thema_anlegen.php).
 * --------------------------------------------------------------------------
 * Warum getrennt: die uebrigen Pruefungen ersetzen db_fetch() und Freunde
 * durch eigene Fassungen ueber eine PDO-Verbindung. lib/db.php danach zu laden
 * waere ein "Cannot redeclare". Hier wird aber genau das gebraucht — das echte
 * Forum, echte Transaktion, echte Zaehler — also laeuft es in einem eigenen
 * Prozess gegen den Pruefstand.
 *
 *   php hilfe/thema_anlegen_lauf.php /root/forumix-test/instanz
 *
 * Gibt "ok" aus oder nennt, was nicht stimmt, und geht mit 1 raus.
 */
declare(strict_types=1);

$instanz = $argv[1] ?? '';
if ($instanz === '' || !is_file($instanz . '/bootstrap.php')) {
    fwrite(STDERR, "Kein Pruefstand unter '$instanz'.\n");
    exit(2);
}

define('FORUM_RUNNING', true);
require $instanz . '/bootstrap.php';

$pdo = DB::get();
$fehler = [];

$pdo->exec("INSERT INTO categories (title, position) VALUES ('Prueflauf Thema', 96)");
$cat = (int) $pdo->lastInsertId();
$pdo->exec("INSERT INTO boards (cat_id, title, position) VALUES ($cat, 'Prueflauf Thema', 1)");
$brett = (int) $pdo->lastInsertId();
$pdo->exec("INSERT INTO users (username, password, email)
            VALUES ('pruef_thema_autor', 'x', 'thema@example.invalid')");
$user = (int) $pdo->lastInsertId();

try {
    $vorB = db_fetch("SELECT thread_count, post_count FROM boards WHERE board_id = $brett");
    $vorU = db_fetch("SELECT post_count FROM users WHERE user_id = $user");

    $nach = null;
    $neu = thread_erstellen($brett, $user, 'Prueflauf Thema', 'Erster Beitrag.', [
        'ip'            => '127.0.0.1',
        'nachbereitung' => function ($t, $p) use (&$nach) { $nach = [$t, $p]; },
    ]);

    $b = db_fetch("SELECT thread_count, post_count, last_post_id FROM boards WHERE board_id = $brett");
    $u = db_fetch("SELECT post_count FROM users WHERE user_id = $user");
    $t = db_fetch("SELECT last_post_id, is_approved FROM threads WHERE thread_id = {$neu['thread_id']}");

    if ((int) $b['thread_count'] !== (int) $vorB['thread_count'] + 1) $fehler[] = 'Themenzahl des Forums';
    if ((int) $b['post_count']   !== (int) $vorB['post_count'] + 1)   $fehler[] = 'Beitragszahl des Forums';
    if ((int) $b['last_post_id'] !== $neu['post_id'])                 $fehler[] = 'letzter Beitrag am Forum';
    if ((int) $t['last_post_id'] !== $neu['post_id'])                 $fehler[] = 'letzter Beitrag am Thema';
    if ((int) $u['post_count']   !== (int) $vorU['post_count'] + 1)   $fehler[] = 'Beitragszahl des Benutzers';
    if ((int) $t['is_approved']  !== 1)                               $fehler[] = 'Freigabe';
    if ($nach !== [$neu['thread_id'], $neu['post_id']])               $fehler[] = 'Nachbereitung';
    if (db_count("SELECT COUNT(*) FROM thread_subscriptions
                   WHERE user_id = $user AND thread_id = {$neu['thread_id']}") !== 1) {
        $fehler[] = 'Auto-Abo';
    }

    // Ein Thema, das auf Freigabe wartet, darf nirgends mitzaehlen — sonst
    // meldet das Forum Beitraege, die niemand sehen kann.
    $vor2 = db_fetch("SELECT thread_count, post_count FROM boards WHERE board_id = $brett");
    $neu2 = thread_erstellen($brett, $user, 'Prueflauf Wartend', 'Wartet.', [
        'freigegeben' => false,
        'abo'         => false,
    ]);
    $b2 = db_fetch("SELECT thread_count, post_count FROM boards WHERE board_id = $brett");
    if ((int) $b2['thread_count'] !== (int) $vor2['thread_count']) $fehler[] = 'wartendes Thema mitgezaehlt';
    if ((int) $b2['post_count']   !== (int) $vor2['post_count'])   $fehler[] = 'wartender Beitrag mitgezaehlt';
    if (db_count("SELECT COUNT(*) FROM thread_subscriptions WHERE thread_id = {$neu2['thread_id']}") !== 0) {
        $fehler[] = 'Abo trotz abo=false';
    }

    $themen = implode(',', [(int) $neu['thread_id'], (int) $neu2['thread_id']]);
    $pdo->exec("DELETE FROM posts WHERE thread_id IN ($themen)");
    $pdo->exec("DELETE FROM threads WHERE thread_id IN ($themen)");
} finally {
    $pdo->exec("DELETE FROM thread_subscriptions WHERE user_id = $user");
    $pdo->exec("DELETE FROM users WHERE user_id = $user");
    $pdo->exec("DELETE FROM boards WHERE board_id = $brett");
    $pdo->exec("DELETE FROM categories WHERE cat_id = $cat");
}

if ($fehler) {
    fwrite(STDERR, implode(', ', $fehler) . "\n");
    exit(1);
}
echo "ok\n";
