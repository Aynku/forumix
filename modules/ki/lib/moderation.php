<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
/**
 * modules/ki/lib/moderation.php – Beitraege vorsortieren
 *
 * Was hier passiert und was ausdruecklich nicht:
 *
 * Der Beitrag wird VEROEFFENTLICHT, immer. Findet die Pruefung etwas, legt
 * sie eine Meldung an — dieselbe, die ein Mensch anlegt, wenn er auf "Melden"
 * drueckt. Ein Moderator sieht sie in der Liste und entscheidet.
 *
 * Nichts wird geloescht, nichts gesperrt, nichts zurueckgehalten. Der Grund
 * ist nicht Vorsicht, sondern Arithmetik: Bei einer Trefferquote von 95 %
 * liegt der Fehler bei jedem zwanzigsten Beitrag. Zurueckgehaltene Beitraege
 * von Unschuldigen kosten mehr, als durchgelassener Spam kostet — der steht
 * ein paar Minuten und ist dann weg.
 */

require_once __DIR__ . '/anbieter.php';
require_once __DIR__ . '/einstellungen.php';

/** Wonach gesucht wird. Die Schluessel sind Meldegruende aus lib/reports.php. */
const KI_BEFUNDE = ['spam', 'offensive'];

const KI_MOD_SYSTEM = <<<'TXT'
Du sortierst Forenbeitraege vor. Ein Mensch entscheidet danach; du entscheidest
nichts.

Antworte mit genau einem Wort, ohne Satzzeichen:

spam       Werbung, Verkaufsangebote, Linkschleudern, sinnlose Textwueste,
           dieselbe Nachricht in mehreren Themen.
offensive  Beleidigung, Beschimpfung, Hetze gegen Personen oder Gruppen,
           Drohungen.
ok         alles andere.

"ok" ist die Vorgabe und die haeufigste Antwort. Antworte nur dann nicht "ok",
wenn du es einem Moderator gegenueber begruenden koenntest.

Was ausdruecklich "ok" ist: harte Kritik in der Sache, Streit ueber ein Thema,
Unhoeflichkeit, schlechte Rechtschreibung, kurze Antworten, ein einzelner Link
zu etwas Passendem, Fachsprache und derbe Sprache unter Bekannten. Ein Forum
ist kein Wartezimmer.
TXT;

/**
 * Reiht einen Beitrag zur Pruefung ein.
 *
 * Geprueft wird spaeter, nicht hier — siehe ki_pruefung_arbeiten().
 */
function ki_pruefung_einreihen(int $postId): void {
    try {
        db_execute('INSERT IGNORE INTO ki_pruefung (post_id) VALUES (?)', [$postId]);
    } catch (Exception $e) {
        error_log('[ki] Pruefung nicht eingereiht: ' . $e->getMessage());
    }
}

/**
 * Soll dieser Beitrag ueberhaupt geprueft werden?
 *
 * Wer schon eine Weile dabei ist, faengt selten mit Werbung an. Die Grenze
 * spart den grossen Teil der Anfragen und laesst die Faelle uebrig, um die es
 * geht. Wer moderiert, wird nie geprueft — das waere eine Meldung, die der
 * Betroffene selbst bearbeiten soll.
 */
function ki_pruefung_faellig(int $userId): bool {
    $cfg = ki_einstellungen();
    if (empty($cfg['mod_an'])) return false;

    try {
        $u = db_fetch('SELECT post_count, is_admin, is_mod, COALESCE(is_owner, 0) AS is_owner
                         FROM users WHERE user_id = ?', [$userId]);
    } catch (Exception $e) {
        return false;
    }
    if (!$u) return false;
    if (!empty($u['is_admin']) || !empty($u['is_mod']) || !empty($u['is_owner'])) return false;

    $grenze = (int) ($cfg['mod_bis'] ?? 20);
    if ($grenze <= 0) return true;              // 0 = alle pruefen
    return (int) $u['post_count'] <= $grenze;
}

/**
 * Arbeitet die Warteschlange ab, hoechstens $menge Eintraege.
 *
 * @return int Zahl der bearbeiteten Eintraege
 */
function ki_pruefung_arbeiten(int $menge = 3): int {
    $cfg = ki_einstellungen();
    if (empty($cfg['mod_an']) || (string) ($cfg['schluessel'] ?? '') === '') return 0;

    try {
        $offen = db_fetchAll(
            "SELECT k.post_id, k.versuche, p.content, p.user_id
               FROM ki_pruefung k
               JOIN posts p ON p.post_id = k.post_id
              WHERE k.status = 'offen' AND k.versuche < 3
           ORDER BY k.created_at
              LIMIT " . max(1, min(20, $menge)));
    } catch (Exception $e) {
        return 0;
    }

    $getan = 0;
    foreach ($offen as $z) {
        $postId = (int) $z['post_id'];

        // Vor der Anfrage hochzaehlen, und zwar als Sperre: Nur wer die Zeile
        // tatsaechlich aendert, arbeitet sie ab. Zwei gleichzeitige Aufrufe
        // wuerden sonst denselben Beitrag zweimal ans Modell schicken — das
        // faellt nicht auf, steht aber doppelt auf der Rechnung.
        //
        // Der Zaehler begrenzt zugleich die Wiederholungen: bricht der Aufruf
        // mitten drin ab (Zeitgrenze, Neustart), laeuft derselbe Beitrag sonst
        // im Kreis.
        $gegriffen = db_affected(
            'UPDATE ki_pruefung SET versuche = versuche + 1 WHERE post_id = ? AND versuche = ?',
            [$postId, (int) $z['versuche']]);
        if ($gegriffen !== 1) continue;

        try {
            $befund = ki_beitrag_pruefen((string) $z['content'], $cfg);
        } catch (KiFehler $e) {
            error_log('[ki] Pruefung fehlgeschlagen fuer Beitrag ' . $postId . ': ' . $e->getMessage());
            continue;                                   // Versuch verbraucht, spaeter noch einmal
        }

        db_execute("UPDATE ki_pruefung SET status = 'fertig', befund = ? WHERE post_id = ?",
            [$befund, $postId]);

        if ($befund !== 'ok') {
            ki_meldung_anlegen($postId, $befund, $cfg);
        }
        $getan++;
    }
    return $getan;
}

/**
 * Arbeitet die Warteschlange NACH dem Ausliefern der Seite ab.
 *
 * Bei PHP-FPM schliesst fastcgi_finish_request() die Antwort ab; der Browser
 * hat seine Seite, waehrend hier noch gearbeitet wird. Ohne FPM laeuft es am
 * Ende der Anfrage — dann dauert der Aufruf laenger, aber die Ausgabe steht
 * bereits.
 */
function ki_pruefung_spaeter(int $menge = 3): void {
    register_shutdown_function(static function () use ($menge) {
        if (function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();
        }
        try {
            ki_pruefung_arbeiten($menge);
        } catch (Throwable $e) {
            error_log('[ki] Pruefung im Nachlauf: ' . $e->getMessage());
        }
    });
}

/** Fragt das Modell. Gibt 'spam', 'offensive' oder 'ok' zurueck. */
function ki_beitrag_pruefen(string $text, array $cfg): string {
    // Zitate gehen auch hier nicht mit: sonst wird jemand fuer die Worte
    // gemeldet, die er zitiert hat.
    require_once __DIR__ . '/schutz.php';
    [$vorne, $mitte, $hinten] = ki_raender_abtrennen($text);
    [$ohne, ]                 = ki_bloecke_heraus($mitte);
    if (trim($ohne) === '') return 'ok';

    $cfg['modell'] = trim((string) ($cfg['mod_modell'] ?? '')) ?: (string) ($cfg['modell'] ?? '');

    $antwort = strtolower(trim(ki_anfrage(KI_MOD_SYSTEM, mb_substr($ohne, 0, 4000), $cfg, 10)));
    $antwort = preg_replace('/[^a-z]/', '', $antwort);

    return in_array($antwort, KI_BEFUNDE, true) ? $antwort : 'ok';
}

/**
 * Legt die Meldung an — dieselbe, die ein Mensch anlegt.
 *
 * Melder ist ein Konto, das der Betreiber bestimmt. Ohne Konto keine Meldung:
 * `reports.reporter_id` haengt an einem Fremdschluessel, und eine Meldung ohne
 * Absender waere im Meldebereich eine Zeile ohne Herkunft.
 */
function ki_meldung_anlegen(int $postId, string $befund, array $cfg): void {
    $melder = (int) ($cfg['mod_melder'] ?? 0);
    if ($melder <= 0) {
        error_log('[ki] Befund ' . $befund . ' fuer Beitrag ' . $postId . ', aber kein Melder-Konto eingestellt');
        return;
    }
    if (!function_exists('create_report')) return;

    $text = match ($befund) {
        'spam'      => 'Automatisch vorsortiert: sieht nach Werbung oder Spam aus.',
        'offensive' => 'Automatisch vorsortiert: enthält womöglich Beleidigungen.',
        default     => 'Automatisch vorsortiert.',
    };

    try {
        create_report($postId, $melder, $befund, $text . ' Bitte selbst ansehen — '
            . 'die Einschätzung stammt von einem Sprachmodell und kann falsch sein.');
    } catch (Exception $e) {
        error_log('[ki] Meldung nicht angelegt: ' . $e->getMessage());
    }
}
