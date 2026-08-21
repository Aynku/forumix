<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
/**
 * modules/ki/lib/einstellungen.php – Einstellungen des Moduls
 *
 * Der API-Schluessel liegt verschluesselt in `modules.config` (siehe
 * lib/crypto.php). Klartext waere hier besonders unangenehm: an dem Schluessel
 * haengt eine Rechnung, und eine Datenbank-Sicherung wandert schnell einmal
 * auf einen anderen Rechner.
 *
 * Ohne FORUM_CRYPT_KEY laeuft es trotzdem — dann eben unverschluesselt, wie
 * beim SMTP-Passwort auch. Der Adminbereich sagt es deutlich.
 */

require_once __DIR__ . '/anbieter.php';

/**
 * Das Gemerkte dieser Anfrage. Als Referenz, damit ki_einstellungen_vergessen()
 * es auch wirklich leeren kann — dasselbe Muster wie board_access_cache()
 * im Kern.
 */
function &ki_cache(): array {
    static $c = [];
    return $c;
}

/**
 * Liest die Einstellungen, Schluessel entschluesselt.
 *
 * Gemerkt fuer die Dauer der Anfrage: ki_darf() wird beim Zeichnen jedes
 * Editors gefragt, und module_config() geht jedes Mal an die Datenbank.
 */
function ki_einstellungen(): array {
    $c = &ki_cache();
    if (isset($c['cfg'])) return $c['cfg'];

    $cfg = module_config('ki');

    $roh = (string) ($cfg['schluessel'] ?? '');
    $cfg['schluessel'] = $roh === '' ? '' : forum_decrypt($roh);

    return $c['cfg'] = $cfg;
}

/** Schreibt die Einstellungen, Schluessel verschluesselt. */
function ki_einstellungen_speichern(array $cfg): bool {
    if (!empty($cfg['schluessel'])) {
        $cfg['schluessel'] = forum_encrypt((string) $cfg['schluessel']);
    }
    $ok = set_module_config('ki', $cfg);
    ki_einstellungen_vergessen();
    return $ok;
}

/** Nach dem Speichern ist das Gemerkte veraltet. */
function ki_einstellungen_vergessen(): void {
    $c = &ki_cache();
    $c = [];
}

/**
 * Darf dieser Benutzer die Schreibhilfe benutzen?
 *
 * Die Freigabe laeuft ueber Gruppen. `board_permissions` hilft hier nicht —
 * das ist forumsbezogen, und die Schreibhilfe haengt an der Person, nicht am
 * Forum. Ohne Eintrag darf niemand ausser der Verwaltung: eine Funktion, die
 * Geld kostet, wird nicht versehentlich fuer alle offen sein.
 */
function ki_darf(?array $u = null): bool {
    $u = $u ?? current_user();
    if (!$u) return false;
    if (!empty($u['is_admin']) || !empty($u['is_owner'])) return true;

    $frei = ki_einstellungen()['gruppen'] ?? [];
    if (!is_array($frei) || !$frei) return false;
    $frei = array_map('intval', $frei);

    // user_group_ids() aus dem Kern kennt nur den aktuellen Besucher — mehr
    // wird hier nicht gebraucht, die Schreibhilfe benutzt jeder fuer sich.
    if (!function_exists('user_group_ids')) return false;
    foreach (user_group_ids() as $gid) {
        if (in_array((int) $gid, $frei, true)) return true;
    }
    return false;
}

/**
 * Die Gruppen, die im Adminbereich zur Wahl stehen.
 *
 * Alle ausser den Gaesten: die Schreibhilfe setzt eine Anmeldung voraus —
 * der Endpunkt antwortet Nichtangemeldeten mit 401, und das soll auch so
 * bleiben, sonst erzeugt jeder Vorbeikommende Kosten. Ein waehlbarer Haken
 * "Gaeste" wuerde etwas versprechen, das nie eintritt.
 */
function ki_waehlbare_gruppen(): array {
    if (!function_exists('groups_all')) return [];
    return array_values(array_filter(
        groups_all(),
        static fn ($g) => ($g['slug'] ?? '') !== 'guests'
    ));
}

/** Ist das Modul einsatzbereit — aktiv, Schluessel da, Benutzer freigegeben? */
function ki_bereit(): bool {
    if (!module_enabled('ki')) return false;
    if ((string) (ki_einstellungen()['schluessel'] ?? '') === '') return false;
    return ki_darf();
}
