<?php
/**
 * Wer ein Forum oder eine Kategorie loescht, laesst nichts liegen.
 *
 * Anlass: `board_permissions` haengt an keinem Fremdschluessel — die Rechte
 * je Gruppe blieben stehen, wenn ihr Forum verschwand. Sie zeigen auf nichts
 * mehr und richten keinen Schaden an, sammeln sich aber. Bei einer Kategorie
 * faellt es staerker ins Gewicht: an ihr haengen die Foren, und die
 * verschwinden ueber den Fremdschluessel gleich mit.
 *
 * Geprueft wird die Wirkung, nicht der Weg — das SQL steht im Adminbereich,
 * hier wird nachgestellt, was er tut.
 */

if (!defined('FORUM_PRUEFLAUF')) { http_response_code(403); die('Forbidden'); }

$wurzel = defined('FORUM_WURZEL') ? FORUM_WURZEL : dirname(__DIR__, 2);
$db     = getenv('FORUM_TEST_DB') ?: 'forumix_test';
$konfig = (defined('PRUEFSTAND') && PRUEFSTAND !== '') ? PRUEFSTAND . '/config.php' : '';
if ($konfig === '' || !is_file($konfig)) {
    return ['Löschen räumt auf (uebersprungen, kein Pruefstand)' => fn() => null];
}

$roh  = file_get_contents($konfig);
$hole = function (string $name) use ($roh): string {
    preg_match("/define\(\s*'$name'\s*,\s*'([^']*)'/", $roh, $t);
    return $t[1] ?? '';
};
$pdo = new PDO("mysql:host={$hole('DB_HOST')};dbname=$db;charset=utf8mb4",
               $hole('DB_USER'), $hole('DB_PASS'), PRUEF_PDO_OPTIONEN);

/** Legt Kategorie, Forum und Rechte an; raeumt hinterher auf. */
$buehne = function (callable $koerper) use ($pdo) {
    $pdo->exec("INSERT INTO categories (title, position) VALUES ('Pruef-Loeschen', 95)");
    $cat = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO boards (cat_id, title, position) VALUES ($cat, 'Wird geloescht', 1)");
    $brett = (int) $pdo->lastInsertId();
    $gruppen = $pdo->query('SELECT group_id FROM user_groups LIMIT 2')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($gruppen as $g) {
        $pdo->exec("INSERT INTO board_permissions (board_id, group_id, level)
                    VALUES ($brett, " . (int) $g . ", 'read')");
    }
    try {
        $koerper($cat, $brett, count($gruppen));
    } finally {
        $pdo->exec("DELETE FROM board_permissions WHERE board_id = $brett");
        $pdo->exec("DELETE FROM boards WHERE board_id = $brett");
        $pdo->exec("DELETE FROM categories WHERE cat_id = $cat");
    }
};

return [
    'Ein gelöschtes Forum lässt keine Rechte zurück' => function () use ($pdo, $buehne) {
        $buehne(function (int $cat, int $brett, int $anzahl) use ($pdo) {
            if ($anzahl === 0) return;   // ohne Gruppen nichts zu prüfen

            // Genau das, was admin/index.php bei delete_board tut.
            $pdo->exec("DELETE FROM board_permissions WHERE board_id = $brett");
            $pdo->exec("DELETE FROM boards WHERE board_id = $brett");

            $rest = (int) $pdo->query("SELECT COUNT(*) FROM board_permissions
                                       WHERE board_id = $brett")->fetchColumn();
            if ($rest > 0) throw new RuntimeException("$rest verwaiste Rechte-Zeilen geblieben");
        });
    },

    'Eine gelöschte Kategorie nimmt die Rechte ihrer Foren mit' => function () use ($pdo, $buehne) {
        $buehne(function (int $cat, int $brett, int $anzahl) use ($pdo) {
            if ($anzahl === 0) return;

            // Und das, was er bei delete_cat tut — die Foren fallen ueber den
            // Fremdschluessel, ihre Rechte muessen vorher weg.
            $pdo->exec("DELETE FROM board_permissions
                        WHERE board_id IN (SELECT board_id FROM boards WHERE cat_id = $cat)");
            $pdo->exec("DELETE FROM categories WHERE cat_id = $cat");

            $rest = (int) $pdo->query("SELECT COUNT(*) FROM board_permissions
                                       WHERE board_id = $brett")->fetchColumn();
            if ($rest > 0) throw new RuntimeException("$rest verwaiste Rechte-Zeilen geblieben");
        });
    },

    'Der Adminbereich räumt beide Wege ab' => function () use ($wurzel) {
        // Die beiden Pruefungen oben stellen nach, was der Adminbereich tut.
        // Damit sie nicht ins Leere laufen, wenn dort jemand das Aufraeumen
        // wieder herausnimmt, wird der Quelltext mitgeprueft.
        $inhalt = (string) file_get_contents($wurzel . '/admin/index.php');
        foreach ([
            'delete_board' => "DELETE FROM board_permissions WHERE board_id = ?",
            'delete_cat'   => "DELETE FROM board_permissions\n                    WHERE board_id IN (SELECT board_id FROM boards WHERE cat_id = ?)",
        ] as $weg => $muster) {
            if (!str_contains($inhalt, $muster)) {
                throw new RuntimeException("Beim $weg wird board_permissions nicht mehr abgeräumt");
            }
        }

        // Und die Erfolgsmeldung darf nicht behaupten, es sei etwas geschehen,
        // wenn keine Zeile betroffen war.
        foreach (['DELETE FROM boards WHERE board_id = ?', 'DELETE FROM categories WHERE cat_id = ?'] as $sql) {
            if (!preg_match('/db_affected\(\s*.' . preg_quote($sql, '/') . './', $inhalt)) {
                throw new RuntimeException("Löschen ohne Prüfung der betroffenen Zeilen: $sql");
            }
        }
    },
];
