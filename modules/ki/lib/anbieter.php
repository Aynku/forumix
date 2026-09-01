<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
/**
 * modules/ki/lib/anbieter.php – Die Verbindung zum Sprachmodell
 *
 * Eine Anfrage, eine Antwort, fertig. Kein SDK, kein Composer: das Forum
 * kommt ohne aus, und was hier gebraucht wird, ist ein POST mit JSON.
 *
 * Zwei Anbieter hinter derselben Tuer. Der Unterschied zwischen ihnen ist
 * nur der Umschlag — Adresse, Kopfzeilen, wo der System-Text steht und wo
 * die Antwort herauskommt. Deshalb steht das in einer Tabelle und nicht in
 * zwei Klassen.
 */

const KI_ANBIETER = [
    'anthropic' => [
        'name'    => 'Anthropic (Claude)',
        'url'     => 'https://api.anthropic.com/v1/messages',
        'vorgabe' => 'claude-haiku-4-5-20251001',
        'hilfe'   => 'console.anthropic.com → API Keys',
    ],
    'openai' => [
        'name'    => 'OpenAI',
        'url'     => 'https://api.openai.com/v1/chat/completions',
        'vorgabe' => 'gpt-4o-mini',
        'hilfe'   => 'platform.openai.com → API keys',
    ],
];

/** Wie lange auf eine Antwort gewartet wird, bevor abgebrochen wird. */
const KI_ZEITGRENZE = 45;

/**
 * Ein Fehler, den der Benutzer zu sehen bekommt.
 *
 * Getrennt von den ueblichen Ausnahmen, weil der Text hier bewusst
 * unverfaenglich ist: was der Anbieter zurueckmeldet, kann Teile der Anfrage
 * enthalten, und die Anfrage enthaelt fremde Beitragstexte.
 */
class KiFehler extends RuntimeException {}

/**
 * Schickt eine Anfrage und gibt den Antworttext zurueck.
 *
 * @param string $system  Die Anweisung an das Modell (was es tun soll).
 * @param string $text    Der Text des Benutzers (woran es arbeiten soll).
 * @param array  $cfg     Modulkonfiguration, siehe ki_einstellungen().
 * @param int    $max     Obergrenze fuer die Antwortlaenge in Tokens.
 */
function ki_anfrage(string $system, string $text, array $cfg, int $max = 1500): string {
    $code = (string) ($cfg['anbieter'] ?? 'anthropic');
    if (!isset(KI_ANBIETER[$code])) {
        throw new KiFehler(t('ki_no_provider'));
    }
    $anbieter = KI_ANBIETER[$code];

    $schluessel = (string) ($cfg['schluessel'] ?? '');
    if ($schluessel === '') {
        throw new KiFehler(t('ki_no_key'));
    }
    $modell = (string) ($cfg['modell'] ?? '') ?: $anbieter['vorgabe'];

    if ($code === 'anthropic') {
        $kopf = [
            'x-api-key: ' . $schluessel,
            'anthropic-version: 2023-06-01',
            'content-type: application/json',
        ];
        $rumpf = [
            'model'      => $modell,
            'max_tokens' => $max,
            'system'     => $system,
            'messages'   => [['role' => 'user', 'content' => $text]],
        ];
    } else {
        $kopf = [
            'Authorization: Bearer ' . $schluessel,
            'Content-Type: application/json',
        ];
        // OpenAI kennt kein eigenes system-Feld im Rumpf: die Anweisung ist
        // dort die erste Nachricht.
        $rumpf = [
            'model'      => $modell,
            'max_tokens' => $max,
            'messages'   => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user',   'content' => $text],
            ],
        ];
    }

    $antwort = ki_post($anbieter['url'], $kopf, $rumpf);

    // Herausholen, was der jeweilige Anbieter als Text zurueckgibt.
    if ($code === 'anthropic') {
        $stuecke = $antwort['content'] ?? [];
        $ergebnis = '';
        foreach ($stuecke as $s) {
            if (($s['type'] ?? '') === 'text') $ergebnis .= (string) ($s['text'] ?? '');
        }
    } else {
        $ergebnis = (string) ($antwort['choices'][0]['message']['content'] ?? '');
    }

    $ergebnis = trim($ergebnis);
    if ($ergebnis === '') {
        throw new KiFehler(t('ki_empty_answer'));
    }
    return $ergebnis;
}

/**
 * Der eigentliche Aufruf. Trennt Netzfehler von Antwortfehlern, weil sich
 * beide fuer den Benutzer verschieden anfuehlen: das eine ist ein Aussetzer,
 * das andere ein falscher Schluessel.
 */
function ki_post(string $url, array $kopf, array $rumpf): array {
    if (!function_exists('curl_init')) {
        throw new KiFehler(t('ki_no_curl'));
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($rumpf, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_HTTPHEADER     => $kopf,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => KI_ZEITGRENZE,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    $roh  = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $netz = curl_error($ch);
    // Kein curl_close(): seit PHP 8.0 ohne Wirkung, seit 8.5 veraltet — die
    // Verbindung raeumt PHP selbst ab, sobald $ch aus dem Gueltigkeitsbereich
    // faellt. Der Aufruf brachte nichts ausser einer Warnung im Protokoll.
    unset($ch);

    if ($roh === false) {
        // Der curl-Text nennt Adresse und Grund, keinen Inhalt — der darf ins
        // Protokoll.
        error_log('[ki] Verbindung fehlgeschlagen: ' . $netz);
        throw new KiFehler(t('ki_unreachable'));
    }

    $daten = json_decode((string) $roh, true);
    if (!is_array($daten)) {
        error_log('[ki] Antwort war kein JSON (HTTP ' . $code . ')');
        throw new KiFehler(t('ki_unreadable'));
    }

    if ($code >= 400) {
        // Die Fehlermeldung des Anbieters kann den Anfragetext zitieren, und
        // der gehoert einem Benutzer. Ins Protokoll geht nur die Art.
        $art = (string) ($daten['error']['type'] ?? $daten['error']['code'] ?? 'unbekannt');
        error_log('[ki] HTTP ' . $code . ' (' . $art . ')');
        throw new KiFehler(match (true) {
            $code === 401 || $code === 403 => t('ki_key_rejected'),
            $code === 429                  => t('ki_too_many'),
            $code >= 500                   => t('ki_provider_broken'),
            default                        => t('ki_request_rejected', $code),
        });
    }

    return $daten;
}

/**
 * Prueft Schluessel und Modell mit einer winzigen Anfrage.
 * Gibt null zurueck, wenn alles stimmt, sonst den Grund.
 */
function ki_probe(array $cfg): ?string {
    try {
        ki_anfrage('Antworte mit genau einem Wort: bereit', 'Test', $cfg, 20);
        return null;
    } catch (KiFehler $e) {
        return $e->getMessage();
    }
}
