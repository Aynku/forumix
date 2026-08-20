<?php
/**
 * Volltextsuche gegen den Pruefstand: findet sie, sortiert sie, faellt sie
 * zurueck, wenn der Index fehlt?
 */
declare(strict_types=1);

$instanz = $argv[1] ?? '';
$modus   = $argv[2] ?? 'mit';   // mit = Index da, ohne = Index fehlt
if ($instanz === '' || !is_file($instanz . '/bootstrap.php')) {
    fwrite(STDERR, "Kein Pruefstand unter '$instanz'.\n"); exit(2);
}
define('FORUM_RUNNING', true);
require $instanz . '/bootstrap.php';

$pdo = DB::get();
$fehler = [];
$pruefe = function (bool $b, string $was) use (&$fehler) { if (!$b) $fehler[] = $was; };

if ($modus === 'ohne') {
    // Ohne Index: die Suche muss trotzdem finden.
    try { DB::get()->exec('ALTER TABLE posts DROP INDEX ft_inhalt'); } catch (Throwable $e) {}
    try { DB::get()->exec('ALTER TABLE threads DROP INDEX ft_titel'); } catch (Throwable $e) {}
} else {
    ob_start(); require $instanz . '/install/migrations/migrate_v29.php'; ob_end_clean();
}

$pdo->exec("INSERT INTO categories (title, position) VALUES ('Prueflauf Suche', 99)");
$cat = (int) $pdo->lastInsertId();
$pdo->exec("INSERT INTO boards (cat_id, title, position) VALUES ($cat, 'Prueflauf Suche', 1)");
$brett = (int) $pdo->lastInsertId();
$pdo->exec("INSERT INTO users (username, password, email) VALUES ('pruef_suche', 'x', 'suche@example.invalid')");
$user = (int) $pdo->lastInsertId();

$anlegen = function (string $titel, string $text) use ($brett, $user) {
    return thread_erstellen($brett, $user, $titel, $text, ['ip' => '127.0.0.1', 'abo' => false]);
};

try {
    $a = $anlegen('Zwiebelkuchen backen', 'Der Teig braucht Hefe und Geduld.');
    $b = $anlegen('Hefeteig für Anfänger', 'Zwiebelkuchen kommt später, erst der Teig.');
    $c = $anlegen('Ganz was anderes', 'Hier steht nichts Verwandtes.');

    $treffer = function (string $begriff) use ($brett) {
        $bed = such_bedingung($begriff);
        $sql = 'SELECT t.thread_id FROM threads t JOIN posts p ON p.thread_id = t.thread_id
                 WHERE t.board_id = ? AND ' . $bed['sql'] . ' GROUP BY t.thread_id';
        $ids = array_column(db_fetchAll($sql, array_merge([$brett], $bed['params'])), 'thread_id');
        return array_map('intval', $ids);
    };

    if ($modus === 'ohne') {
        $pruefe(!volltext_bereit(), 'Ohne Index gilt der Volltext trotzdem als bereit');
        $pruefe(such_bedingung('Zwiebelkuchen')['volltext'] === false, 'Ohne Index wird trotzdem MATCH gebaut');
    } else {
        $pruefe(volltext_bereit(), 'Der Index gilt als nicht vorhanden');
    }

    $t = $treffer('Zwiebelkuchen');
    $pruefe(in_array($a['thread_id'], $t, true), 'Wort im Titel nicht gefunden');
    $pruefe(in_array($b['thread_id'], $t, true), 'Wort im Beitrag nicht gefunden');
    $pruefe(!in_array($c['thread_id'], $t, true), 'Fremdes Thema mitgeliefert');

    // Zwei Woerter heisst BEIDE — auch wenn eines im Titel und eines im Text steht.
    $pruefe($treffer('Zwiebelkuchen Hefe') !== [], 'Zwei Begriffe finden nichts');
    $pruefe(!in_array($c['thread_id'], $treffer('Zwiebelkuchen Hefe'), true), 'UND-Verknuepfung greift nicht');

    // Wortanfang: "Hefe" muss "Hefeteig" finden.
    if ($modus === 'mit') {
        $pruefe(in_array($b['thread_id'], $treffer('Hefe'), true), 'Wortanfang findet nichts');
    }

    // Anfuehrungszeichen gehen ueber LIKE, nicht ueber den Index.
    $woertlich = such_bedingung('"Hefe und Geduld"');
    $pruefe($woertlich['volltext'] === false, 'Woertliche Suche laeuft ueber den Index');
    $pruefe(in_array($a['thread_id'], $treffer('"Hefe und Geduld"'), true), 'Woertliche Suche findet die Wendung nicht');

    // Zu kurz fuer den Index → LIKE, aber es muss trotzdem finden.
    $kurz = such_bedingung('ge');
    $pruefe($kurz['volltext'] === false, 'Zu kurzer Begriff laeuft ueber den Index');

    // Operatoren aus der Eingabe duerfen die Abfrage nicht zerlegen.
    foreach (['+++', 'a" OR "1', 'test*(', '~<>'] as $boese) {
        try { $treffer($boese); } catch (Throwable $e) { $fehler[] = "Eingabe '$boese': " . $e->getMessage(); }
    }

} finally {
    // Der Schalter merkt sich seine Antwort je Prozess — hier endet er ohnehin.
    try { $pdo->exec('ALTER TABLE posts ADD FULLTEXT INDEX ft_inhalt (content)'); } catch (Throwable $e) {}
    try { $pdo->exec('ALTER TABLE threads ADD FULLTEXT INDEX ft_titel (title)'); } catch (Throwable $e) {}
    $pdo->exec("DELETE FROM posts WHERE user_id = $user");
    $pdo->exec("DELETE FROM threads WHERE board_id = $brett");
    $pdo->exec("DELETE FROM thread_subscriptions WHERE user_id = $user");
    $pdo->exec("DELETE FROM user_ip_log WHERE user_id = $user");
    $pdo->exec("DELETE FROM users WHERE user_id = $user");
    $pdo->exec("DELETE FROM boards WHERE board_id = $brett");
    $pdo->exec("DELETE FROM categories WHERE cat_id = $cat");
}

if ($fehler) { fwrite(STDERR, implode('; ', $fehler) . "\n"); exit(1); }
echo "ok\n";
