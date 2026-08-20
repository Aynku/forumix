<?php
/**
 * Papierkorb, Teilen und Zusammenführen — der ganze Weg gegen den Pruefstand.
 * Warum als eigener Prozess: siehe hilfe/thema_anlegen_lauf.php.
 */
declare(strict_types=1);

$instanz = $argv[1] ?? '';
if ($instanz === '' || !is_file($instanz . '/bootstrap.php')) {
    fwrite(STDERR, "Kein Pruefstand unter '$instanz'.\n"); exit(2);
}
define('FORUM_RUNNING', true);
require $instanz . '/bootstrap.php';

$pdo = DB::get();
$fehler = [];
$pruefe = function (bool $bedingung, string $was) use (&$fehler) { if (!$bedingung) $fehler[] = $was; };

$pdo->exec("INSERT INTO categories (title, position) VALUES ('Prueflauf Papierkorb', 98)");
$cat = (int) $pdo->lastInsertId();
$pdo->exec("INSERT INTO boards (cat_id, title, position) VALUES ($cat, 'Prueflauf Papierkorb', 1)");
$brett = (int) $pdo->lastInsertId();
$pdo->exec("INSERT INTO users (username, password, email) VALUES ('pruef_pk', 'x', 'pk@example.invalid')");
$user = (int) $pdo->lastInsertId();
$mod = $user;

$zahl = fn(string $sql, array $p = []) => db_count($sql, $p);

try {
    // Ein Thema mit vier Beitraegen.
    $t = thread_erstellen($brett, $user, 'Papierkorb-Thema', 'Erster', ['ip' => '127.0.0.1']);
    $thema = $t['thread_id'];
    $beitraege = [$t['post_id']];
    for ($i = 2; $i <= 4; $i++) {
        db_execute('INSERT INTO posts (thread_id, user_id, content, is_approved) VALUES (?, ?, ?, 1)',
                   [$thema, $user, "Beitrag $i"]);
        $beitraege[] = (int) DB::get()->lastInsertId();
        db_execute('UPDATE threads SET reply_count = reply_count + 1, last_post_id = ? WHERE thread_id = ?',
                   [end($beitraege), $thema]);
        db_execute('UPDATE boards SET post_count = post_count + 1, last_post_id = ? WHERE board_id = ?',
                   [end($beitraege), $brett]);
        db_execute('UPDATE users SET post_count = post_count + 1 WHERE user_id = ?', [$user]);
    }

    $vorBrett = db_fetch("SELECT post_count, thread_count FROM boards WHERE board_id = $brett");
    $vorUser  = $zahl("SELECT post_count FROM users WHERE user_id = $user");

    // ── 1. Beitrag in den Papierkorb ────────────────────────────────
    $pruefe(beitrag_in_papierkorb($beitraege[3], $mod), 'Loeschen meldet Fehlschlag');
    $pruefe($zahl("SELECT COUNT(*) FROM posts WHERE post_id = {$beitraege[3]}") === 1,
            'Der Beitrag ist wirklich geloescht statt in den Papierkorb gelegt');
    $pruefe($zahl("SELECT COUNT(*) FROM posts WHERE thread_id = $thema AND deleted_at IS NULL") === 3,
            'Sichtbare Beitraege stimmen nach dem Loeschen nicht');
    $nachBrett = db_fetch("SELECT post_count FROM boards WHERE board_id = $brett");
    $pruefe((int) $nachBrett['post_count'] === (int) $vorBrett['post_count'] - 1, 'Brett-Zaehler nach dem Loeschen');
    $pruefe($zahl("SELECT post_count FROM users WHERE user_id = $user") === $vorUser - 1, 'Benutzer-Zaehler nach dem Loeschen');
    $pruefe((int) db_fetch("SELECT last_post_id FROM threads WHERE thread_id = $thema")['last_post_id'] === $beitraege[2],
            'Letzter Beitrag zeigt noch auf den geloeschten');

    // ── 2. Zurueckholen ─────────────────────────────────────────────
    $pruefe(beitrag_zurueckholen($beitraege[3]), 'Zurueckholen meldet Fehlschlag');
    $pruefe((int) db_fetch("SELECT post_count FROM boards WHERE board_id = $brett")['post_count'] === (int) $vorBrett['post_count'],
            'Brett-Zaehler nach dem Zurueckholen');
    $pruefe($zahl("SELECT post_count FROM users WHERE user_id = $user") === $vorUser, 'Benutzer-Zaehler nach dem Zurueckholen');

    // ── 3. Endgueltig nur aus dem Papierkorb ────────────────────────
    $pruefe(!beitrag_endgueltig_loeschen($beitraege[3]), 'Endgueltig loeschen ging ohne Umweg ueber den Papierkorb');
    beitrag_in_papierkorb($beitraege[3], $mod);
    $pruefe(beitrag_endgueltig_loeschen($beitraege[3]), 'Endgueltig loeschen aus dem Papierkorb schlug fehl');
    $pruefe($zahl("SELECT COUNT(*) FROM posts WHERE post_id = {$beitraege[3]}") === 0, 'Endgueltig geloescht, steht aber noch da');

    // ── 4. Thema in den Papierkorb und zurueck ──────────────────────
    beitrag_in_papierkorb($beitraege[2], $mod);   // einer liegt vorher schon drin
    $pruefe(thema_in_papierkorb($thema, $mod), 'Thema loeschen meldet Fehlschlag');
    $pruefe($zahl("SELECT COUNT(*) FROM threads WHERE thread_id = $thema AND deleted_at IS NOT NULL") === 1, 'Thema nicht im Papierkorb');
    $pruefe($zahl("SELECT COUNT(*) FROM posts WHERE thread_id = $thema AND deleted_at IS NULL") === 0, 'Beitraege sind nicht mitgegangen');
    $pruefe((int) db_fetch("SELECT thread_count FROM boards WHERE board_id = $brett")['thread_count'] === (int) $vorBrett['thread_count'] - 1,
            'Themenzahl des Bretts nach dem Loeschen');

    $pruefe(thema_zurueckholen($thema), 'Thema zurueckholen meldet Fehlschlag');
    $pruefe($zahl("SELECT COUNT(*) FROM posts WHERE thread_id = $thema AND deleted_at IS NULL") === 2,
            'Zurueck kam nicht genau das, was mitgegangen ist — der vorher einzeln geloeschte Beitrag muss drin bleiben');
    $pruefe($zahl("SELECT COUNT(*) FROM posts WHERE post_id = {$beitraege[2]} AND deleted_at IS NOT NULL") === 1,
            'Der einzeln geloeschte Beitrag wurde mit zurueckgeholt');

    // ── 5. Teilen ───────────────────────────────────────────────────
    beitrag_zurueckholen($beitraege[2]);
    $neu = thema_teilen($beitraege[1], 'Abgetrennt');
    $pruefe($neu > 0, 'Teilen hat nichts geliefert');
    $pruefe($zahl("SELECT COUNT(*) FROM posts WHERE thread_id = $thema") === 1, 'Im alten Thema blieb nicht genau der erste Beitrag');
    $pruefe($zahl("SELECT COUNT(*) FROM posts WHERE thread_id = $neu") === 2, 'Im neuen Thema stehen nicht die abgetrennten Beitraege');
    $pruefe((int) db_fetch("SELECT reply_count FROM threads WHERE thread_id = $thema")['reply_count'] === 0, 'Antwortzahl des alten Themas');
    $pruefe((int) db_fetch("SELECT reply_count FROM threads WHERE thread_id = $neu")['reply_count'] === 1, 'Antwortzahl des neuen Themas');
    $pruefe(thema_teilen($beitraege[0], 'Geht nicht') === 0, 'Am ersten Beitrag liess sich abtrennen');

    // ── 6. Zusammenfuehren ──────────────────────────────────────────
    $pruefe(themen_zusammenfuehren($neu, $thema), 'Zusammenfuehren meldet Fehlschlag');
    $pruefe($zahl("SELECT COUNT(*) FROM threads WHERE thread_id = $neu") === 0, 'Das leere Thema steht noch da');
    $pruefe($zahl("SELECT COUNT(*) FROM posts WHERE thread_id = $thema") === 3, 'Nicht alle Beitraege sind gewandert');
    $pruefe((int) db_fetch("SELECT reply_count FROM threads WHERE thread_id = $thema")['reply_count'] === 2, 'Antwortzahl nach dem Zusammenfuehren');
    $pruefe(!themen_zusammenfuehren($thema, $thema), 'Ein Thema liess sich mit sich selbst zusammenfuehren');
} finally {
    $pdo->exec("DELETE FROM posts WHERE thread_id IN (SELECT thread_id FROM (SELECT thread_id FROM threads WHERE board_id = $brett) x)");
    $pdo->exec("DELETE FROM threads WHERE board_id = $brett");
    $pdo->exec("DELETE FROM thread_subscriptions WHERE user_id = $user");
    $pdo->exec("DELETE FROM user_ip_log WHERE user_id = $user");
    $pdo->exec("DELETE FROM users WHERE user_id = $user");
    $pdo->exec("DELETE FROM boards WHERE board_id = $brett");
    $pdo->exec("DELETE FROM categories WHERE cat_id = $cat");
}

if ($fehler) { fwrite(STDERR, implode('; ', $fehler) . "\n"); exit(1); }
echo "ok\n";
