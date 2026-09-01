<?php
/**
 * lib/import_phpbb.php – der Kern des phpBB-Imports.
 *
 * Hier liegt alles, was tatsaechlich etwas liest oder schreibt. Die beiden
 * Wege dorthin — `install/import-phpbb.php` fuer die Kommandozeile und der
 * Adminbereich fuer den Browser — bringen nur Ein- und Ausgabe mit.
 *
 * Der Grund fuer die Trennung ist derselbe wie beim Installer: Stuende die
 * Arbeit zweimal nebeneinander, wuerde eine Aenderung irgendwann nur in einem
 * der beiden Wege ankommen.
 *
 * ── Warum in Haeppchen ────────────────────────────────────────────────
 *
 * Ein Forum mit hunderttausend Beitraegen laeuft im Browser in jedes
 * Zeitlimit. Deshalb arbeitet der Import in Runden: jede macht ein Stueck und
 * sagt, wie weit sie gekommen ist. Moeglich wird das durch die
 * Zuordnungstabelle — sie haelt fest, was schon erledigt ist, und die
 * naechste Runde setzt genau dort an.
 */

if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }

require_once __DIR__ . '/../install/import_text.php';

/** Die Schritte des Imports, in dieser Reihenfolge. */
const IMPORT_SCHRITTE = [
    'user'      => 'sys_konten',
    'struktur'  => 'sys_kategorien_und_foren',
    'thema'     => 'sys_themen',
    'beitrag'   => 'sys_beitraege',
    'anhang'    => 'sys_anhaenge',
    'umfrage'   => 'sys_umfragen',

    'pn'        => 'sys_private_nachrichten',
    'zaehler'   => 'sys_zaehler',
];

/* ══════════════════════════════════════════════════════════════════════
   VERBINDUNG UND BESTAND
   ══════════════════════════════════════════════════════════════════════ */

/**
 * Oeffnet die Verbindung zur phpBB-Datenbank.
 * Wirft bei Fehlschlag — die Meldung ist fuer den Betreiber gedacht.
 */
function import_verbindung(array $opt): PDO
{
    $host = $opt['host'] ?: 'localhost';
    $port = $opt['port'] ?: '3306';
    return new PDO(
        "mysql:host=$host;port=$port;dbname={$opt['db']};charset=utf8mb4",
        $opt['user'], $opt['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
}

/** Gibt es diese Tabelle in der fremden Datenbank? */
function import_tabelle_da(PDO $alt, string $name): bool
{
    try { return (bool) $alt->query('SHOW TABLES LIKE ' . $alt->quote($name))->fetch(); }
    catch (Throwable $e) { return false; }
}

/**
 * Sieht nach, ob das dort drueben ueberhaupt ein phpBB ist.
 * Gibt null zurueck, wenn alles passt, sonst den Grund.
 */
function import_pruefe_quelle(PDO $alt, string $p): ?string
{
    foreach (['users', 'forums', 'topics', 'posts'] as $noetig) {
        if (!import_tabelle_da($alt, $p . $noetig)) {
            return "Die Tabelle {$p}{$noetig} gibt es nicht. Stimmt das Präfix?";
        }
    }
    return null;
}

/** Zaehlt, was drueben liegt. */
function import_bestand(PDO $alt, string $p): array
{
    $zahl = function (string $sql) use ($alt): int {
        try { return (int) $alt->query($sql)->fetchColumn(); } catch (Throwable $e) { return 0; }
    };

    $fassung = '';
    try {
        $fassung = (string) $alt->query(
            "SELECT config_value FROM {$p}config WHERE config_name = 'version'")->fetchColumn();
    } catch (Throwable $e) {}

    return [
        'fassung'    => $fassung,
        'konten'     => $zahl("SELECT COUNT(*) FROM {$p}users WHERE user_type <> 2"),
        'bots'       => $zahl("SELECT COUNT(*) FROM {$p}users WHERE user_type = 2"),
        'kategorien' => $zahl("SELECT COUNT(*) FROM {$p}forums WHERE forum_type = 0"),
        'foren'      => $zahl("SELECT COUNT(*) FROM {$p}forums WHERE forum_type = 1"),
        'themen'     => $zahl("SELECT COUNT(*) FROM {$p}topics"),
        'beitraege'  => $zahl("SELECT COUNT(*) FROM {$p}posts"),
        'anhaenge'   => import_tabelle_da($alt, $p . 'attachments')
                      ? $zahl("SELECT COUNT(*) FROM {$p}attachments WHERE is_orphan = 0") : 0,
        'umfragen'   => import_tabelle_da($alt, $p . 'poll_options')
                      ? $zahl("SELECT COUNT(DISTINCT topic_id) FROM {$p}poll_options") : 0,
        'pn'         => import_tabelle_da($alt, $p . 'privmsgs')
                      ? $zahl("SELECT COUNT(*) FROM {$p}privmsgs") : 0,
    ];
}

/**
 * Zaehlt die Passwort-Formate.
 *
 * Die wichtigste Zahl vor einem Umzug: wie viele Mitglieder sich hinterher
 * mit ihrem gewohnten Passwort anmelden koennen. argon2 und bcrypt versteht
 * password_verify() unveraendert; das aeltere phpass nicht.
 */
function import_passwoerter(PDO $alt, string $p): array
{
    $formate = [];
    try {
        foreach ($alt->query("SELECT user_password FROM {$p}users WHERE user_type <> 2") as $z) {
            $h = (string) $z['user_password'];
            if ($h === '')                          $k = 'leer';
            elseif (str_starts_with($h, '$argon2')) $k = 'argon2';
            elseif (str_starts_with($h, '$2y$')
                 || str_starts_with($h, '$2a$'))    $k = 'bcrypt';
            elseif (str_starts_with($h, '$H$')
                 || str_starts_with($h, '$P$'))     $k = 'phpass';
            else                                    $k = 'anderes';
            $formate[$k] = ($formate[$k] ?? 0) + 1;
        }
    } catch (Throwable $e) {}

    $gut = ($formate['argon2'] ?? 0) + ($formate['bcrypt'] ?? 0);
    return [
        'formate'      => $formate,
        'uebernehmbar' => $gut,
        'neu_noetig'   => array_sum($formate) - $gut,
    ];
}

/** Prueft, ob die Anhang-Dateien am angegebenen Ort wirklich liegen. */
function import_dateien_pruefen(PDO $alt, string $p, string $ordner): array
{
    if ($ordner === '' || !is_dir($ordner)) return ['gefunden' => 0, 'fehlen' => 0, 'ordner' => false];
    $gefunden = $fehlen = 0;
    try {
        foreach ($alt->query("SELECT physical_filename FROM {$p}attachments WHERE is_orphan = 0") as $z) {
            is_file(rtrim($ordner, '/') . '/' . $z['physical_filename']) ? $gefunden++ : $fehlen++;
        }
    } catch (Throwable $e) {}
    return ['gefunden' => $gefunden, 'fehlen' => $fehlen, 'ordner' => true];
}

/* ══════════════════════════════════════════════════════════════════════
   ZUORDNUNG
   ══════════════════════════════════════════════════════════════════════ */

function import_zuordnung_bereit(): void
{
    db_execute("
        CREATE TABLE IF NOT EXISTS `import_zuordnung` (
            `art`    VARCHAR(20)  NOT NULL,
            `alt_id` BIGINT UNSIGNED NOT NULL,
            `neu_id` INT UNSIGNED NOT NULL,
            `notiz`  VARCHAR(100) NOT NULL DEFAULT '',
            PRIMARY KEY (`art`, `alt_id`),
            KEY `neu` (`art`, `neu_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function import_zuordnung_da(): bool
{
    try { return (bool) db_fetch("SHOW TABLES LIKE 'import_zuordnung'"); }
    catch (Throwable $e) { return false; }
}

function import_merken(string $art, int $alt, int $neu, string $notiz = ''): void
{
    db_execute('INSERT INTO import_zuordnung (art, alt_id, neu_id, notiz) VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE neu_id = VALUES(neu_id), notiz = VALUES(notiz)',
               [$art, $alt, $neu, mb_substr($notiz, 0, 100)]);
}

function import_neu_id(string $art, int $alt): ?int
{
    $z = db_fetch('SELECT neu_id FROM import_zuordnung WHERE art = ? AND alt_id = ?', [$art, $alt]);
    return $z ? (int) $z['neu_id'] : null;
}

/** Wie viele Zeilen einer Art sind schon uebernommen? */
function import_erledigt(string $art): int
{
    try { return (int) db_count('SELECT COUNT(*) FROM import_zuordnung WHERE art = ?', [$art]); }
    catch (Throwable $e) { return 0; }
}

/** Ein Zeitstempel von phpBB (Unix) als Datum fuer Forumix. */
function import_zeit(?int $unix): string
{
    return date('Y-m-d H:i:s', ($unix !== null && $unix > 0) ? $unix : time());
}

/**
 * Ein Konto fuer Beitraege, deren Verfasser nicht mitgekommen ist — Gaeste
 * und uebersprungene Konten. Ohne den Platzhalter fehlten halbe Unterhaltungen.
 */
function import_platzhalter(): int
{
    static $id = null;
    if ($id !== null) return $id;

    $da = db_fetch('SELECT user_id FROM users WHERE username = ?', [t('sys_uebernommener_beitrag')]);
    if ($da) return $id = (int) $da['user_id'];

    return $id = (int) db_execute(
        "INSERT INTO users (username, password, email, `rank`, is_deleted, email_verified, registered_at)
         VALUES (?, ?, ?, ?, 1, 1, NOW())",
        [t('sys_uebernommener_beitrag'), '', 'uebernommen@localhost.invalid', 'Archiv']
    );
}

/* ══════════════════════════════════════════════════════════════════════
   DIE SCHRITTE

   Jeder nimmt sich hoechstens $menge Zeilen vor und sagt, ob er fertig ist.
   Wo er ansetzt, steht in der Zuordnung: die hoechste alte Kennung, die schon
   uebernommen wurde. Damit ist jede Runde fuer sich abgeschlossen — ein
   Zeitlimit unterbricht den Import, es zerstoert ihn nicht.
   ══════════════════════════════════════════════════════════════════════ */

/** Die hoechste bereits uebernommene Kennung einer Art. */
function import_stand(string $art): int
{
    try {
        return (int) db_count('SELECT COALESCE(MAX(alt_id), 0) FROM import_zuordnung WHERE art = ?',
                              [$art]);
    } catch (Throwable $e) { return 0; }
}

function import_konten(PDO $alt, string $p, int $menge): array
{
    $ab = import_stand('user');

    // Wohnort, Beruf und Interessen liegen seit phpBB 3.1 daneben, nicht in
    // der Benutzertabelle — und die Tabelle gibt es nur, wenn welche
    // eingerichtet sind.
    $profil = import_tabelle_da($alt, $p . 'profile_fields_data');
    $spalten = [];
    if ($profil) {
        try {
            foreach ($alt->query("SHOW COLUMNS FROM {$p}profile_fields_data") as $sp) {
                $spalten[$sp['Field']] = true;
            }
        } catch (Throwable $e) { $profil = false; }
    }

    $felder = "u.user_id, u.username, u.user_email, u.user_password, u.user_type,
               u.user_regdate, u.user_lastvisit, u.user_posts, u.user_sig";
    foreach (['pf_phpbb_location' => 'ort', 'pf_phpbb_occupation' => 'beruf',
              'pf_phpbb_interests' => 'interessen'] as $sp => $als) {
        $felder .= ($profil && isset($spalten[$sp])) ? ", d.`$sp` AS $als" : ", NULL AS $als";
    }
    $join = $profil ? "LEFT JOIN {$p}profile_fields_data d ON d.user_id = u.user_id" : '';

    $s = $alt->prepare("SELECT $felder FROM {$p}users u $join
                        WHERE u.user_type <> 2 AND u.user_id > ?
                        ORDER BY u.user_id LIMIT $menge");
    $s->execute([$ab]);
    $zeilen = $s->fetchAll();

    // Namen und Adressen sind hier eindeutig. Wer schon da ist, wird
    // uebersprungen — ein Zusammenlegen waere die Uebergabe eines fremden
    // Kontos an jemanden, der zufaellig dieselbe Adresse benutzt.
    $getan = $uebersprungen = 0;
    foreach ($zeilen as $z) {
        $altId = (int) $z['user_id'];
        $name  = trim((string) $z['username']);
        $mail  = mb_strtolower(trim((string) $z['user_email']));

        if ($name === '' || $mail === ''
            || db_fetch('SELECT user_id FROM users WHERE LOWER(username) = ? OR LOWER(email) = ? LIMIT 1',
                        [mb_strtolower($name), $mail])) {
            import_merken('user_weg', $altId, 0, $name);
            $uebersprungen++;
            continue;
        }

        $ort = mb_substr(trim((string) ($z['ort'] ?? '')), 0, 100);
        try {
            $neuId = (int) db_execute(
                'INSERT INTO users (username, password, email, signature, post_count, `rank`,
                                    registered_at, last_login, email_verified, location, is_banned,
                                    profile_location, profile_occupation, profile_interests)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?)',
                [
                    $name, (string) $z['user_password'], $mail,
                    phpbb_text_zu_bbcode((string) ($z['user_sig'] ?? '')) ?: null,
                    (int) $z['user_posts'], 'Mitglied',
                    import_zeit((int) $z['user_regdate']),
                    ((int) $z['user_lastvisit']) > 0 ? import_zeit((int) $z['user_lastvisit']) : null,
                    $ort,
                    ((int) $z['user_type']) === 1 ? 1 : 0,
                    $ort !== '' ? $ort : null,
                    mb_substr(trim((string) ($z['beruf'] ?? '')), 0, 100) ?: null,
                    mb_substr(trim((string) ($z['interessen'] ?? '')), 0, 500) ?: null,
                ]
            );
            import_merken('user', $altId, $neuId, $name);
            $getan++;
        } catch (Throwable $e) {
            import_merken('user_weg', $altId, 0, mb_substr($e->getMessage(), 0, 100));
            $uebersprungen++;
        }
    }

    return ['getan' => $getan + $uebersprungen, 'neu' => $getan,
            'uebersprungen' => $uebersprungen, 'fertig' => count($zeilen) < $menge];
}

function import_struktur(PDO $alt, string $p): array
{
    $getan = 0;

    foreach ($alt->query("SELECT forum_id, forum_name, left_id FROM {$p}forums
                          WHERE forum_type = 0 ORDER BY left_id") as $z) {
        if (import_neu_id('kategorie', (int) $z['forum_id']) !== null) continue;
        $neuId = (int) db_execute('INSERT INTO categories (title, position) VALUES (?, ?)',
                                  [mb_substr((string) $z['forum_name'], 0, 100), (int) $z['left_id']]);
        import_merken('kategorie', (int) $z['forum_id'], $neuId, (string) $z['forum_name']);
        $getan++;
    }

    // Ein Forum, dessen Kategorie nicht mitkam (oder das keine hat), braucht
    // trotzdem eine.
    $sammel = null;
    foreach ($alt->query("SELECT forum_id, forum_name, forum_desc, parent_id, left_id
                          FROM {$p}forums WHERE forum_type = 1 ORDER BY left_id") as $z) {
        if (import_neu_id('board', (int) $z['forum_id']) !== null) continue;

        $kat = import_neu_id('kategorie', (int) $z['parent_id']);
        if ($kat === null) {
            if ($sammel === null) {
                $da = db_fetch('SELECT cat_id FROM categories ORDER BY position, cat_id LIMIT 1');
                $sammel = $da ? (int) $da['cat_id']
                    : (int) db_execute('INSERT INTO categories (title, position) VALUES (?, ?)',
                                       [t('sys_uebernommen'), 99]);
            }
            $kat = $sammel;
        }

        $neuId = (int) db_execute(
            'INSERT INTO boards (cat_id, title, description, position) VALUES (?, ?, ?, ?)',
            [$kat, mb_substr((string) $z['forum_name'], 0, 100),
             mb_substr(trim(strip_tags((string) $z['forum_desc'])), 0, 255), (int) $z['left_id']]
        );
        import_merken('board', (int) $z['forum_id'], $neuId, (string) $z['forum_name']);
        $getan++;
    }

    return ['getan' => $getan, 'neu' => $getan, 'fertig' => true];
}

function import_themen(PDO $alt, string $p, int $menge): array
{
    $ab = import_stand('thema');
    $papierkorb = function_exists('papierkorb_bereit') && papierkorb_bereit();

    $s = $alt->prepare("SELECT topic_id, forum_id, topic_poster, topic_title, topic_time,
                               topic_status, topic_type, topic_views, topic_visibility
                        FROM {$p}topics WHERE topic_id > ? ORDER BY topic_id LIMIT $menge");
    $s->execute([$ab]);
    $zeilen = $s->fetchAll();

    $getan = 0;
    foreach ($zeilen as $z) {
        $altId = (int) $z['topic_id'];
        $board = import_neu_id('board', (int) $z['forum_id']);
        $sicht = (int) $z['topic_visibility'];

        if ($board === null || ($sicht === 2 && !$papierkorb)) {
            import_merken('thema_weg', $altId, 0);
            continue;
        }

        $titel = trim((string) $z['topic_title']) ?: '(ohne Titel)';
        try {
            $neuId = (int) db_execute(
                'INSERT INTO threads (board_id, user_id, title, is_sticky, is_closed, is_approved,
                                      view_count, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $board,
                    import_neu_id('user', (int) $z['topic_poster']) ?? import_platzhalter(),
                    mb_substr($titel, 0, 200),
                    ((int) $z['topic_type']) > 0 ? 1 : 0,
                    ((int) $z['topic_status']) === 1 ? 1 : 0,
                    $sicht === 0 ? 0 : 1,
                    (int) $z['topic_views'],
                    import_zeit((int) $z['topic_time']),
                ]
            );
            if ($sicht === 2) {
                db_execute('UPDATE threads SET deleted_at = ?, deleted_by = 0 WHERE thread_id = ?',
                           [import_zeit((int) $z['topic_time']), $neuId]);
            }
            import_merken('thema', $altId, $neuId, mb_substr($titel, 0, 100));
            $getan++;
        } catch (Throwable $e) {
            import_merken('thema_weg', $altId, 0, mb_substr($e->getMessage(), 0, 100));
        }
    }

    return ['getan' => count($zeilen), 'neu' => $getan, 'fertig' => count($zeilen) < $menge];
}

function import_beitraege(PDO $alt, string $p, int $menge): array
{
    $ab = import_stand('beitrag');
    $papierkorb = function_exists('papierkorb_bereit') && papierkorb_bereit();

    $s = $alt->prepare("SELECT post_id, topic_id, poster_id, post_text, post_time,
                               post_edit_time, post_visibility
                        FROM {$p}posts WHERE post_id > ? ORDER BY post_id LIMIT $menge");
    $s->execute([$ab]);
    $zeilen = $s->fetchAll();

    // Die Dateinamen der Anhaenge dieses Haeppchens — sie stehen im
    // Beitragstext als [attachment=0].
    $namen = [];
    if ($zeilen && import_tabelle_da($alt, $p . 'attachments')) {
        $ids = implode(',', array_map(fn($z) => (int) $z['post_id'], $zeilen));
        try {
            foreach ($alt->query("SELECT post_msg_id, real_filename FROM {$p}attachments
                                  WHERE is_orphan = 0 AND post_msg_id IN ($ids)
                                  ORDER BY attach_id") as $a) {
                $namen[(int) $a['post_msg_id']][] = (string) $a['real_filename'];
            }
        } catch (Throwable $e) {}
    }

    $getan = 0;
    foreach ($zeilen as $z) {
        $altId = (int) $z['post_id'];
        $thema = import_neu_id('thema', (int) $z['topic_id']);
        $sicht = (int) $z['post_visibility'];

        if ($thema === null || ($sicht === 2 && !$papierkorb)) {
            import_merken('beitrag_weg', $altId, 0);
            continue;
        }

        // Eine IP aus einem fremden Forum ist eine personenbezogene Angabe,
        // die hier niemand braucht — sie bleibt draussen.
        try {
            $neuId = (int) db_execute(
                'INSERT INTO posts (thread_id, user_id, content, created_at, edited_at, is_approved)
                 VALUES (?, ?, ?, ?, ?, ?)',
                [
                    $thema,
                    import_neu_id('user', (int) $z['poster_id']) ?? import_platzhalter(),
                    phpbb_text_zu_bbcode((string) $z['post_text'], $namen[$altId] ?? []) ?: '(leerer Beitrag)',
                    import_zeit((int) $z['post_time']),
                    ((int) $z['post_edit_time']) > 0 ? import_zeit((int) $z['post_edit_time']) : null,
                    $sicht === 0 ? 0 : 1,
                ]
            );
            if ($sicht === 2) {
                db_execute('UPDATE posts SET deleted_at = ?, deleted_by = 0 WHERE post_id = ?',
                           [import_zeit((int) $z['post_time']), $neuId]);
            }
            import_merken('beitrag', $altId, $neuId);
            $getan++;
        } catch (Throwable $e) {
            import_merken('beitrag_weg', $altId, 0, mb_substr($e->getMessage(), 0, 100));
        }
    }

    return ['getan' => count($zeilen), 'neu' => $getan, 'fertig' => count($zeilen) < $menge];
}

function import_anhaenge(PDO $alt, string $p, string $ordner, int $menge): array
{
    if ($ordner === '' || !is_dir($ordner) || !import_tabelle_da($alt, $p . 'attachments')) {
        return ['getan' => 0, 'neu' => 0, 'fertig' => true];
    }

    $ab   = import_stand('anhang');
    $ziel = rtrim(UPLOAD_DIR, '/') . '/attachments';
    if (!is_dir($ziel)) @mkdir($ziel, 0755, true);

    // Wer den Import als root faehrt, legt Dateien an, die root gehoeren —
    // und die kann der Webserver nicht ausliefern. Also den Besitzer des
    // Ordners uebernehmen.
    $besitzer = null;
    $stat = @stat($ziel);
    if ($stat !== false && function_exists('posix_geteuid') && posix_geteuid() === 0
        && $stat['uid'] !== 0) {
        $besitzer = ['uid' => $stat['uid'], 'gid' => $stat['gid']];
    }

    $s = $alt->prepare("SELECT attach_id, post_msg_id, poster_id, real_filename,
                               physical_filename, mimetype, filesize
                        FROM {$p}attachments WHERE is_orphan = 0 AND attach_id > ?
                        ORDER BY attach_id LIMIT $menge");
    $s->execute([$ab]);
    $zeilen = $s->fetchAll();

    $getan = 0;
    foreach ($zeilen as $z) {
        $altId   = (int) $z['attach_id'];
        $beitrag = import_neu_id('beitrag', (int) $z['post_msg_id']);
        $von     = rtrim($ordner, '/') . '/' . basename((string) $z['physical_filename']);

        if ($beitrag === null || !is_file($von)) {
            import_merken('anhang_weg', $altId, 0, (string) $z['real_filename']);
            continue;
        }

        // Endung aus dem Anzeigenamen, nicht aus dem MIME-Typ: eine Datei
        // ohne Endung laesst sich hinterher nicht ausliefern.
        $endung = strtolower(pathinfo((string) $z['real_filename'], PATHINFO_EXTENSION));
        if ($endung === '' || !preg_match('/^[a-z0-9]{1,5}$/', $endung)) $endung = 'dat';

        $name = 'att_' . $beitrag . '_' . bin2hex(random_bytes(6)) . '.' . $endung;
        if (!@copy($von, $ziel . '/' . $name)) {
            import_merken('anhang_weg', $altId, 0, t('sys_kopie_fehlgeschlagen'));
            continue;
        }
        @chmod($ziel . '/' . $name, 0644);
        if ($besitzer !== null) {
            @chown($ziel . '/' . $name, $besitzer['uid']);
            @chgrp($ziel . '/' . $name, $besitzer['gid']);
        }

        $mime  = (string) $z['mimetype'];
        $neuId = (int) db_execute(
            'INSERT INTO attachments (post_id, user_id, filename, original_name, mime_type,
                                      size_bytes, is_image) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $beitrag,
                import_neu_id('user', (int) $z['poster_id']) ?? import_platzhalter(),
                $name, mb_substr((string) $z['real_filename'], 0, 255),
                $mime !== '' ? $mime : 'application/octet-stream',
                (int) $z['filesize'], str_starts_with($mime, 'image/') ? 1 : 0,
            ]
        );
        import_merken('anhang', $altId, $neuId, (string) $z['real_filename']);
        $getan++;
    }

    return ['getan' => count($zeilen), 'neu' => $getan, 'fertig' => count($zeilen) < $menge];
}

function import_umfragen(PDO $alt, string $p, int $menge): array
{
    if (!import_tabelle_da($alt, $p . 'poll_options')) {
        return ['getan' => 0, 'neu' => 0, 'fertig' => true];
    }

    $ab = import_stand('umfrage');
    $s = $alt->prepare("SELECT topic_id, poll_title, poll_start, poll_length, poll_max_options
                        FROM {$p}topics WHERE poll_title <> '' AND topic_id > ?
                        ORDER BY topic_id LIMIT $menge");
    $s->execute([$ab]);
    $zeilen = $s->fetchAll();

    $getan = 0;
    foreach ($zeilen as $z) {
        $thema = import_neu_id('thema', (int) $z['topic_id']);
        if ($thema === null) { import_merken('umfrage_weg', (int) $z['topic_id'], 0); continue; }

        $start  = (int) $z['poll_start'];
        $laenge = (int) $z['poll_length'];
        $pollId = (int) db_execute(
            'INSERT INTO polls (thread_id, question, multi_choice, expires_at, created_at)
             VALUES (?, ?, ?, ?, ?)',
            [$thema, mb_substr((string) $z['poll_title'], 0, 255),
             ((int) $z['poll_max_options']) > 1 ? 1 : 0,
             $laenge > 0 ? import_zeit($start + $laenge) : null, import_zeit($start)]
        );
        import_merken('umfrage', (int) $z['topic_id'], $pollId,
                      mb_substr((string) $z['poll_title'], 0, 100));

        $pos = 0;
        $o = $alt->prepare("SELECT poll_option_id, poll_option_text, poll_option_total
                            FROM {$p}poll_options WHERE topic_id = ? ORDER BY poll_option_id");
        $o->execute([(int) $z['topic_id']]);
        foreach ($o as $opt) {
            $optId = (int) db_execute(
                'INSERT INTO poll_options (poll_id, text, vote_count, position) VALUES (?, ?, ?, ?)',
                [$pollId, mb_substr((string) $opt['poll_option_text'], 0, 200),
                 (int) $opt['poll_option_total'], $pos++]
            );
            import_merken('umfrage_option',
                          ((int) $z['topic_id']) * 1000 + (int) $opt['poll_option_id'], $optId);
        }

        // Die einzelnen Stimmen, soweit Konto und Antwort mitgekommen sind —
        // ohne sie koennte ein Mitglied ein zweites Mal abstimmen.
        if (import_tabelle_da($alt, $p . 'poll_votes')) {
            $v = $alt->prepare("SELECT poll_option_id, vote_user_id FROM {$p}poll_votes WHERE topic_id = ?");
            $v->execute([(int) $z['topic_id']]);
            foreach ($v as $stimme) {
                $optNeu = import_neu_id('umfrage_option',
                            ((int) $z['topic_id']) * 1000 + (int) $stimme['poll_option_id']);
                $userNeu = import_neu_id('user', (int) $stimme['vote_user_id']);
                if ($optNeu === null || $userNeu === null) continue;
                try {
                    db_execute('INSERT IGNORE INTO poll_votes (poll_id, option_id, user_id) VALUES (?, ?, ?)',
                               [$pollId, $optNeu, $userNeu]);
                } catch (Throwable $e) {}
            }
        }
        $getan++;
    }

    return ['getan' => count($zeilen), 'neu' => $getan, 'fertig' => count($zeilen) < $menge];
}

function import_pn(PDO $alt, string $p, int $menge): array
{
    if (!import_tabelle_da($alt, $p . 'privmsgs') || !import_tabelle_da($alt, $p . 'privmsgs_to')) {
        return ['getan' => 0, 'neu' => 0, 'fertig' => true];
    }

    // phpBB kennt mehrere Empfaenger je Nachricht, Forumix einen. Aus einer
    // Nachricht an drei Leute werden deshalb drei — anders liesse sich nicht
    // abbilden, dass jeder sie einzeln liest und loescht. Die Kopie im
    // Postausgang (folder_id = -1) bleibt draussen, sonst stuende alles doppelt.
    $ab = import_stand('pn');
    $s = $alt->prepare("SELECT m.msg_id, t.user_id AS empfaenger, m.author_id, m.message_subject,
                               m.message_text, m.message_time, t.pm_unread
                        FROM {$p}privmsgs m
                        JOIN {$p}privmsgs_to t ON t.msg_id = m.msg_id
                        WHERE t.folder_id <> -1
                          AND (m.msg_id * 100000 + t.user_id) > ?
                        ORDER BY m.msg_id, t.user_id LIMIT $menge");
    $s->execute([$ab]);
    $zeilen = $s->fetchAll();

    $getan = 0;
    foreach ($zeilen as $z) {
        $schluessel = ((int) $z['msg_id']) * 100000 + (int) $z['empfaenger'];
        $von = import_neu_id('user', (int) $z['author_id']);
        $an  = import_neu_id('user', (int) $z['empfaenger']);
        if ($von === null || $an === null) { import_merken('pn_weg', $schluessel, 0); continue; }

        $betreff = trim((string) $z['message_subject']) ?: '(ohne Betreff)';
        $neuId = (int) db_execute(
            'INSERT INTO private_messages (sender_id, receiver_id, subject, content, is_read, sent_at)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$von, $an, mb_substr($betreff, 0, 200),
             phpbb_text_zu_bbcode((string) $z['message_text']),
             ((int) $z['pm_unread']) === 1 ? 0 : 1, import_zeit((int) $z['message_time'])]
        );
        import_merken('pn', $schluessel, $neuId);
        $getan++;
    }

    return ['getan' => count($zeilen), 'neu' => $getan, 'fertig' => count($zeilen) < $menge];
}

/**
 * Setzt die Zaehler aus dem, was tatsaechlich dasteht.
 *
 * Sie waehrend des Imports Zeile fuer Zeile mitzurechnen waere langsam und
 * nach einem Abbruch falsch.
 */
function import_zaehler(): array
{
    $sichtbar = (function_exists('papierkorb_bereit') && papierkorb_bereit())
              ? ' AND p.deleted_at IS NULL' : '';

    db_execute("UPDATE threads t SET
        reply_count = GREATEST((SELECT COUNT(*) FROM posts p
                                WHERE p.thread_id = t.thread_id AND p.is_approved = 1$sichtbar) - 1, 0),
        last_post_id = (SELECT MAX(p.post_id) FROM posts p
                        WHERE p.thread_id = t.thread_id AND p.is_approved = 1$sichtbar)");

    db_execute("UPDATE boards b SET
        thread_count = (SELECT COUNT(*) FROM threads t WHERE t.board_id = b.board_id AND t.is_approved = 1),
        post_count   = (SELECT COUNT(*) FROM posts p JOIN threads t ON t.thread_id = p.thread_id
                        WHERE t.board_id = b.board_id AND p.is_approved = 1$sichtbar),
        last_post_id = (SELECT MAX(p.post_id) FROM posts p JOIN threads t ON t.thread_id = p.thread_id
                        WHERE t.board_id = b.board_id AND p.is_approved = 1$sichtbar)");

    db_execute("UPDATE users u SET post_count = (SELECT COUNT(*) FROM posts p
                WHERE p.user_id = u.user_id AND p.is_approved = 1$sichtbar)
                WHERE u.user_id IN (SELECT neu_id FROM import_zuordnung WHERE art = 'user')");

    return ['getan' => 1, 'neu' => 0, 'fertig' => true];
}

/**
 * Faehrt eine Runde des genannten Schritts.
 * Gibt zurueck, wie viele Zeilen dran waren und ob der Schritt fertig ist.
 */
function import_runde(PDO $alt, string $p, string $schritt, array $opt, int $menge = 400): array
{
    import_zuordnung_bereit();
    return match ($schritt) {
        'user'     => import_konten($alt, $p, $menge),
        'struktur' => import_struktur($alt, $p),
        'thema'    => import_themen($alt, $p, $menge),
        'beitrag'  => import_beitraege($alt, $p, $menge),
        'anhang'   => import_anhaenge($alt, $p, (string) ($opt['dateien'] ?? ''), $menge),
        'umfrage'  => import_umfragen($alt, $p, $menge),
        'pn'       => import_pn($alt, $p, $menge),
        'zaehler'  => import_zaehler(),
        default    => ['getan' => 0, 'neu' => 0, 'fertig' => true],
    };
}

/**
 * Nimmt zurueck, was ein Import angelegt hat — und nur das. Angefasst wird
 * ausschliesslich, was in der Zuordnung steht; von Hand Dazugestelltes bleibt.
 */
function import_zuruecknehmen(): array
{
    if (!import_zuordnung_da()) return [];
    $bericht = [];

    // Die Dateien zuerst: sie stehen in der Tabelle, die gleich geleert wird.
    $dateien = 0;
    try {
        foreach (db_fetchAll(
            "SELECT a.filename FROM attachments a
             JOIN import_zuordnung z ON z.art = 'anhang' AND z.neu_id = a.attachment_id") as $d) {
            $pfad = rtrim(UPLOAD_DIR, '/') . '/attachments/' . basename((string) $d['filename']);
            if (is_file($pfad) && @unlink($pfad)) $dateien++;
        }
    } catch (Throwable $e) {}
    if ($dateien > 0) $bericht['Dateien'] = $dateien;

    // Von innen nach aussen, sonst haengen Verweise in der Luft.
    foreach ([
        ['anhang',         'attachments',      'attachment_id'],
        ['umfrage_option', 'poll_options',     'option_id'],
        ['umfrage',        'polls',            'poll_id'],
        ['beitrag',        'posts',            'post_id'],
        ['thema',          'threads',          'thread_id'],
        ['board',          'boards',           'board_id'],
        ['kategorie',      'categories',       'cat_id'],
        ['pn',             'private_messages', 'pm_id'],
        ['user',           'users',            'user_id'],
    ] as [$art, $tabelle, $spalte]) {
        try {
            $n = db_affected("DELETE t FROM `$tabelle` t
                              JOIN import_zuordnung z ON z.art = ? AND z.neu_id = t.`$spalte`", [$art]);
            if ($n > 0) $bericht[$tabelle] = $n;
        } catch (Throwable $e) {}
    }

    db_execute('DROP TABLE import_zuordnung');
    return $bericht;
}

/* ══════════════════════════════════════════════════════════════════════
   LEEREN VOR DEM IMPORT

   Wer ein Forum uebernimmt, will meist das uebernommene und nicht die Reste
   der Neuinstallation daneben. Das Leeren ist deshalb angeboten — aber nie
   die Vorgabe, und nie ohne Zahlen.

   EINE GRENZE, DIE NICHT VERHANDELBAR IST: Das Konto, mit dem gerade
   gearbeitet wird, bleibt. Wer sich beim Leeren selbst mitloescht, kann
   hinterher nicht einmal nachsehen, ob der Import geklappt hat.
   ══════════════════════════════════════════════════════════════════════ */

/** Was steht hier schon? Die Zahlen fuer die Warnung vor dem Leeren. */
function import_ziel_bestand(): array
{
    $z = function (string $tabelle): int {
        try { return (int) db_count("SELECT COUNT(*) FROM `$tabelle`"); }
        catch (Throwable $e) { return 0; }
    };
    return [
        'kategorien' => $z('categories'),
        'foren'      => $z('boards'),
        'themen'     => $z('threads'),
        'beitraege'  => $z('posts'),
        'anhaenge'   => $z('attachments'),
        'pn'         => $z('private_messages'),
        'konten'     => $z('users'),
    ];
}

/**
 * Leert das Forum bis auf das genannte Konto.
 *
 * Der grosse Teil der Arbeit erledigen die Fremdschluessel: an `categories`
 * haengen die Foren, daran die Themen, daran die Beitraege und daran wieder
 * Anhaenge, Reaktionen und Umfragen. An `users` haengt alles Persoenliche.
 * Zwei Dinge kaskadieren nicht und stehen deshalb ausdruecklich hier:
 * `board_permissions` hat keinen Fremdschluessel, und Dateien im
 * Dateisystem kennt keine Datenbank.
 *
 * Gibt zurueck, was entfernt wurde.
 */
function import_leeren(int $behalten): array
{
    $bericht = [];

    // Die Dateien zuerst — nach dem DELETE sind ihre Namen nicht mehr da.
    $dateien = 0;
    try {
        foreach (db_fetchAll('SELECT filename FROM attachments') as $a) {
            $pfad = rtrim(UPLOAD_DIR, '/') . '/attachments/' . basename((string) $a['filename']);
            if (is_file($pfad) && @unlink($pfad)) $dateien++;
        }
    } catch (Throwable $e) {}
    if ($dateien > 0) $bericht['Dateien'] = $dateien;

    // Inhalte: eine Zeile, der Rest folgt ueber die Fremdschluessel.
    foreach (['private_messages' => 'DELETE FROM private_messages',
              'categories'       => 'DELETE FROM categories'] as $was => $sql) {
        try {
            $n = db_affected($sql);
            if ($n > 0) $bericht[$was] = $n;
        } catch (Throwable $e) {}
    }

    // Was ohne Kategorie dastand — sollte es nicht geben, kostet aber nichts.
    foreach (['posts', 'threads', 'boards'] as $tabelle) {
        try {
            $n = db_affected("DELETE FROM `$tabelle`");
            if ($n > 0) $bericht[$tabelle] = ($bericht[$tabelle] ?? 0) + $n;
        } catch (Throwable $e) {}
    }

    // Konten — bis auf das eine.
    try {
        $n = db_affected('DELETE FROM users WHERE user_id <> ?', [$behalten]);
        if ($n > 0) $bericht['users'] = $n;
    } catch (Throwable $e) {}

    // Was keinen Fremdschluessel hat und sonst liegenbliebe.
    foreach (['board_permissions', 'group_members'] as $tabelle) {
        try { db_execute("DELETE FROM `$tabelle`"); } catch (Throwable $e) {}
    }
    try {
        db_execute('DELETE FROM group_members WHERE user_id = ?', [$behalten]);
    } catch (Throwable $e) {}

    // Und die Zuordnung eines frueheren Imports: sie zeigt jetzt ins Leere.
    if (import_zuordnung_da()) {
        try { db_execute('DROP TABLE import_zuordnung'); } catch (Throwable $e) {}
    }

    return $bericht;
}
