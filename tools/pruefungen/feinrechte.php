<?php
/**
 * Feinere Rechte je Forum und Gruppe (1.5.0).
 *
 * "Schreiben" zerfaellt ab hier in fuenf Einzelrechte. Geprueft wird, was
 * dabei schiefgehen kann:
 *   - die Migration ergaenzt die Spalten und laesst alles erlaubt
 *   - ein weggenommener Haken wirkt, ein gesetzter bei "lesen" nicht
 *   - wer in mehreren Gruppen ist, bekommt die Vereinigung — aber nur aus den
 *     Gruppen, die hier ueberhaupt schreiben duerfen
 *   - ein Formular ohne Haken-Block raeumt die Haken NICHT ab
 *
 * Faehrt gegen die Testdatenbank und raeumt hinterher auf.
 */

if (!defined('FORUM_PRUEFLAUF')) { http_response_code(403); die('Forbidden'); }

$wurzel = dirname(__DIR__, 2);
$db     = getenv('FORUM_TEST_DB') ?: 'forumix_test';
$konfig = (defined('PRUEFSTAND') && PRUEFSTAND !== '') ? PRUEFSTAND . '/config.php' : '';
if (!is_file($konfig)) return ['Feinrechte (uebersprungen, kein Pruefstand)' => fn() => null];

$roh  = file_get_contents($konfig);
$hole = function (string $name) use ($roh): string {
    preg_match("/define\(\s*'$name'\s*,\s*'([^']*)'/", $roh, $t);
    return $t[1] ?? '';
};
$pdo = new PDO("mysql:host={$hole('DB_HOST')};dbname=$db;charset=utf8mb4",
               $hole('DB_USER'), $hole('DB_PASS'), PRUEF_PDO_OPTIONEN);

$GLOBALS['__pdo'] = $GLOBALS['__pdo'] ?? $pdo;
$GLOBALS['__testNutzer'] = $GLOBALS['__testNutzer'] ?? null;

if (!function_exists('db_fetch')) {
    function db_fetch(string $sql, array $p = []) {
        $s = $GLOBALS['__pdo']->prepare($sql); $s->execute($p); return $s->fetch(PDO::FETCH_ASSOC);
    }
}
if (!function_exists('db_fetchAll')) {
    function db_fetchAll(string $sql, array $p = []): array {
        $s = $GLOBALS['__pdo']->prepare($sql); $s->execute($p); return $s->fetchAll(PDO::FETCH_ASSOC);
    }
}
if (!function_exists('db_execute')) {
    function db_execute(string $sql, array $p = []): bool {
        $s = $GLOBALS['__pdo']->prepare($sql); return $s->execute($p);
    }
}
if (!function_exists('db_count')) {
    function db_count(string $sql, array $p = []): int {
        $s = $GLOBALS['__pdo']->prepare($sql); $s->execute($p); return (int) $s->fetchColumn();
    }
}
if (!function_exists('db_affected')) {
    function db_affected(string $sql, array $p = []): int {
        $s = $GLOBALS['__pdo']->prepare($sql); $s->execute($p); return $s->rowCount();
    }
}
if (!function_exists('current_user')) {
    function current_user() { return $GLOBALS['__testNutzer']; }
}

require_once $wurzel . '/lib/board_access.php';
require_once $wurzel . '/lib/groups.php';

$frisch = function () { board_access_neu_laden(); };

/** Legt Kategorie, Forum, Gruppe und Nutzer an und raeumt danach auf. */
$buehne = function (callable $koerper) use ($pdo) {
    $pdo->exec("INSERT INTO categories (title, position) VALUES ('Pruef-Feinrechte', 93)");
    $cat = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO boards (cat_id, title, position) VALUES ($cat, 'Feinrechte', 1)");
    $brett = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO user_groups (name, slug, position) VALUES ('Pruefgruppe', 'pruef_fein', 51)");
    $gruppe = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO users (username, password, email)
                VALUES ('pruef_fein', 'x', 'fein@example.invalid')");
    $uid = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO group_members (group_id, user_id) VALUES ($gruppe, $uid)");
    $mit = (int) $pdo->query("SELECT group_id FROM user_groups WHERE slug='members'")->fetchColumn();

    try {
        $koerper($brett, $gruppe, $uid, $mit);
    } finally {
        $GLOBALS['__testNutzer'] = null;
        board_access_neu_laden();
        $pdo->exec("DELETE FROM board_permissions WHERE board_id = $brett");
        $pdo->exec("DELETE FROM group_members WHERE user_id = $uid");
        $pdo->exec("DELETE FROM users WHERE user_id = $uid");
        $pdo->exec("DELETE FROM user_groups WHERE group_id = $gruppe");
        $pdo->exec("DELETE FROM boards WHERE board_id = $brett");
        $pdo->exec("DELETE FROM categories WHERE cat_id = $cat");
    }
};

/** Meldet den Prueflauf als einfaches Mitglied an. */
$als = function (int $uid) use ($frisch) {
    $GLOBALS['__testNutzer'] = ['user_id' => $uid, 'is_admin' => 0, 'is_mod' => 0, 'is_owner' => 0];
    $frisch();
};

return [
    'Migration ergaenzt die Spalten und laesst alles erlaubt' => function () use ($pdo, $wurzel, $buehne) {
        foreach (array_values(BRETT_FEINRECHTE) as $spalte) {
            try { $pdo->exec("ALTER TABLE board_permissions DROP COLUMN `$spalte`"); } catch (Exception $e) {}
        }

        ob_start();
        require $wurzel . '/install/migrations/migrate_v30.php';
        ob_end_clean();

        foreach (array_values(BRETT_FEINRECHTE) as $spalte) {
            $s = $pdo->query("SHOW COLUMNS FROM board_permissions LIKE '$spalte'")->fetch(PDO::FETCH_ASSOC);
            if (!$s) throw new RuntimeException("Spalte $spalte fehlt");
            if ((string) $s['Default'] !== '1') {
                throw new RuntimeException("Spalte $spalte hat Vorgabe '{$s['Default']}' statt 1");
            }
        }
    },

    'Zweimal gefahren aendert nichts' => function () use ($pdo, $wurzel) {
        // Der zweite Lauf muss durchgehen, nicht nur nichts aendern. Am
        // 21.08.2026 tat er das nicht: die Pruefung, ob eine Spalte schon
        // existiert, benutzte "SHOW COLUMNS ... LIKE ?" mit Platzhalter. Den
        // lehnt MariaDB ab, wenn das Statement serverseitig vorbereitet wird
        // — der Fehler landete im catch, die Pruefung meldete "nicht da", und
        // das ALTER TABLE scheiterte mit "Duplicate column name".
        ob_start();
        try {
            require $wurzel . '/install/migrations/migrate_v30.php';
        } catch (Throwable $e) {
            ob_end_clean();
            throw new RuntimeException('Der zweite Lauf scheitert: ' . $e->getMessage());
        }
        ob_end_clean();
        $n = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.columns
                                WHERE table_schema = DATABASE() AND table_name = 'board_permissions'
                                  AND column_name = 'can_thread'")->fetchColumn();
        if ($n !== 1) throw new RuntimeException("can_thread steht $n mal da");
    },

    'Ohne Haken darf niemand, mit Haken schon' => function () use ($pdo, $buehne, $als) {
        $buehne(function ($brett, $gruppe, $uid) use ($pdo, $als) {
            $pdo->exec("INSERT INTO board_permissions
                        (board_id, group_id, level, can_thread, can_reply, can_attach, can_edit, can_poll)
                        VALUES ($brett, $gruppe, 'write', 0, 1, 0, 1, 0)");
            $als($uid);

            $erwartet = ['thema' => false, 'antwort' => true, 'anhang' => false,
                         'bearbeiten' => true, 'umfrage' => false];
            foreach ($erwartet as $recht => $soll) {
                $ist = board_darf($brett, $recht);
                if ($ist !== $soll) {
                    throw new RuntimeException("$recht: erwartet " . var_export($soll, true)
                                             . ', bekommen ' . var_export($ist, true));
                }
            }
        });
    },

    'Ein Haken bei "lesen" wirkt nicht' => function () use ($pdo, $buehne, $als) {
        $buehne(function ($brett, $gruppe, $uid, $mit) use ($pdo, $als) {
            // Lesegruppe mit allen Haken, Angemeldete duerfen gar nichts.
            $pdo->exec("INSERT INTO board_permissions
                        (board_id, group_id, level, can_thread, can_reply, can_attach, can_edit, can_poll)
                        VALUES ($brett, $gruppe, 'read', 1, 1, 1, 1, 1),
                               ($brett, $mit,    'none', 1, 1, 1, 1, 1)");
            $als($uid);

            foreach (array_keys(BRETT_FEINRECHTE) as $recht) {
                if (board_darf($brett, $recht)) {
                    throw new RuntimeException("$recht erlaubt, obwohl die Stufe nur 'lesen' ist");
                }
            }
        });
    },

    'Mehrere Gruppen: Vereinigung, aber nur aus Schreibgruppen' => function () use ($pdo, $buehne, $als) {
        $buehne(function ($brett, $gruppe, $uid, $mit) use ($pdo, $als) {
            // Angemeldete duerfen schreiben, aber nicht anhaengen.
            // Die Pruefgruppe darf schreiben und anhaengen — das gewinnt.
            $pdo->exec("INSERT INTO board_permissions
                        (board_id, group_id, level, can_thread, can_reply, can_attach, can_edit, can_poll)
                        VALUES ($brett, $mit,    'write', 1, 1, 0, 0, 0),
                               ($brett, $gruppe, 'write', 0, 0, 1, 0, 0)");
            $als($uid);

            if (!board_darf($brett, 'anhang'))  throw new RuntimeException('Anhang muesste aus der Pruefgruppe kommen');
            if (!board_darf($brett, 'thema'))   throw new RuntimeException('Thema muesste von den Angemeldeten kommen');
            if (board_darf($brett, 'umfrage'))  throw new RuntimeException('Umfrage darf keine der beiden');
        });
    },

    'Eine Lesegruppe schmuggelt kein Recht herein' => function () use ($pdo, $buehne, $als) {
        $buehne(function ($brett, $gruppe, $uid, $mit) use ($pdo, $als) {
            // Angemeldete duerfen schreiben ohne Anhang; die Pruefgruppe hat
            // den Anhang-Haken, aber nur Lesestufe. Sie darf ihn nicht beitragen.
            $pdo->exec("INSERT INTO board_permissions
                        (board_id, group_id, level, can_thread, can_reply, can_attach, can_edit, can_poll)
                        VALUES ($brett, $mit,    'write', 1, 1, 0, 1, 1),
                               ($brett, $gruppe, 'read',  1, 1, 1, 1, 1)");
            $als($uid);

            if (board_darf($brett, 'anhang')) {
                throw new RuntimeException('Der Haken einer Lesegruppe wurde mitgezaehlt');
            }
            if (!board_darf($brett, 'antwort')) throw new RuntimeException('Antworten muesste erlaubt sein');
        });
    },

    'Ein Forum ohne Eintrag bleibt offen' => function () use ($buehne, $als) {
        $buehne(function ($brett, $gruppe, $uid) use ($als) {
            $als($uid);   // gar keine Zeile in board_permissions
            foreach (array_keys(BRETT_FEINRECHTE) as $recht) {
                if (!board_darf($brett, $recht)) {
                    throw new RuntimeException("$recht verboten, obwohl fuer das Forum nichts eingetragen ist");
                }
            }
        });
    },

    'Ein unbekanntes Recht wird abgelehnt' => function () use ($buehne, $als) {
        $buehne(function ($brett, $gruppe, $uid) use ($als) {
            $als($uid);
            if (board_darf($brett, 'raketenstart')) {
                throw new RuntimeException('Ein erfundenes Recht wurde erteilt');
            }
        });
    },

    'Moderatoren duerfen auch ohne Haken' => function () use ($pdo, $buehne) {
        $buehne(function ($brett, $gruppe, $uid, $mit) use ($pdo) {
            $pdo->exec("INSERT INTO board_permissions
                        (board_id, group_id, level, can_thread, can_reply, can_attach, can_edit, can_poll)
                        VALUES ($brett, $mit, 'none', 0, 0, 0, 0, 0)");
            $GLOBALS['__testNutzer'] = ['user_id' => $uid, 'is_admin' => 0, 'is_mod' => 1, 'is_owner' => 0];
            board_access_neu_laden();

            foreach (array_keys(BRETT_FEINRECHTE) as $recht) {
                if (!board_darf($brett, $recht)) {
                    throw new RuntimeException("Ein Moderator darf $recht nicht");
                }
            }
        });
    },

    'board_darf_for_user stimmt mit board_darf ueberein' => function () use ($pdo, $buehne, $als) {
        $buehne(function ($brett, $gruppe, $uid, $mit) use ($pdo, $als) {
            $pdo->exec("INSERT INTO board_permissions
                        (board_id, group_id, level, can_thread, can_reply, can_attach, can_edit, can_poll)
                        VALUES ($brett, $mit,    'write', 1, 1, 0, 0, 1),
                               ($brett, $gruppe, 'write', 0, 1, 1, 0, 0)");
            $als($uid);

            foreach (array_keys(BRETT_FEINRECHTE) as $recht) {
                $hier   = board_darf($brett, $recht);
                $fremd  = board_darf_for_user($brett, $uid, $recht);
                if ($hier !== $fremd) {
                    throw new RuntimeException("$recht: board_darf sagt " . var_export($hier, true)
                                             . ', board_darf_for_user sagt ' . var_export($fremd, true));
                }
            }

            // Und fuer einen Gast, der in keiner der beiden Gruppen ist.
            if (board_darf_for_user($brett, 0, 'antwort')) {
                throw new RuntimeException('Ein Gast darf antworten, obwohl nichts fuer Gaeste eingetragen ist');
            }
        });
    },

    'Speichern ohne Haken-Block raeumt die Haken nicht ab' => function () use ($pdo, $buehne) {
        $buehne(function ($brett, $gruppe, $uid, $mit) use ($pdo) {
            $pdo->exec("INSERT INTO board_permissions
                        (board_id, group_id, level, can_thread, can_reply, can_attach, can_edit, can_poll)
                        VALUES ($brett, $mit, 'write', 0, 1, 0, 1, 0)");

            // Ein Formular, das nur die Stufen kennt (aeltere Vorlage, Skript).
            board_permissions_set($brett, [$mit => 'write', $gruppe => 'read']);

            $z = $pdo->query("SELECT can_thread, can_attach FROM board_permissions
                              WHERE board_id = $brett AND group_id = $mit")->fetch(PDO::FETCH_ASSOC);
            if ((int) $z['can_thread'] !== 1 || (int) $z['can_attach'] !== 1) {
                throw new RuntimeException('Ohne Haken-Block muesste alles erlaubt bleiben');
            }
        });
    },

    'Speichern mit Haken-Block schreibt genau das' => function () use ($pdo, $buehne) {
        $buehne(function ($brett, $gruppe, $uid, $mit) use ($pdo) {
            board_permissions_set(
                $brett,
                [$mit => 'write', $gruppe => 'write'],
                [$mit    => ['thema' => true,  'antwort' => true, 'anhang' => false,
                             'bearbeiten' => false, 'umfrage' => true],
                 $gruppe => ['thema' => false, 'antwort' => true, 'anhang' => true,
                             'bearbeiten' => true,  'umfrage' => false]]
            );

            $karte = board_feinrechte_map($brett);
            if ($karte[$mit]['anhang'] !== false)      throw new RuntimeException('Anhang haette aus sein muessen');
            if ($karte[$mit]['umfrage'] !== true)      throw new RuntimeException('Umfrage haette an sein muessen');
            if ($karte[$gruppe]['thema'] !== false)    throw new RuntimeException('Thema haette aus sein muessen');
            if ($karte[$gruppe]['bearbeiten'] !== true) throw new RuntimeException('Bearbeiten haette an sein muessen');
        });
    },

    'Jedes Recht wird auch wirklich abgefragt' => function () use ($wurzel) {
        // Ein Recht, das die Verwaltung anbietet, aber keine Seite abfragt,
        // waere ein Schalter ohne Draht. Gesucht wird der Aufruf im Quelltext.
        $wo = [
            'thema'      => ['pages/new_thread.php', 'pages/board.php'],
            'antwort'    => ['pages/thread.php'],
            'anhang'     => ['pages/thread.php', 'pages/new_thread.php'],
            'bearbeiten' => ['pages/edit_post.php', 'pages/thread.php'],
            'umfrage'    => ['pages/new_thread.php'],
        ];
        foreach ($wo as $recht => $dateien) {
            $gefunden = false;
            foreach ($dateien as $d) {
                $inhalt = (string) file_get_contents($wurzel . '/' . $d);
                if (str_contains($inhalt, "'$recht'")) { $gefunden = true; break; }
            }
            if (!$gefunden) {
                throw new RuntimeException("Das Recht '$recht' wird in " . implode(', ', $dateien) . ' nicht abgefragt');
            }
        }
    },
];
