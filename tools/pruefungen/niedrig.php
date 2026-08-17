<?php
/**
 * Die niedrigen Audit-Funde vom 17.08.2026.
 */

if (!defined('FORUM_PRUEFLAUF')) { http_response_code(403); die('Forbidden'); }

$wurzel = dirname(__DIR__, 2);
$lies = fn(string $d) => file_get_contents($wurzel . '/' . $d);

return [
    'Reaktionen pruefen den Forumzugang' => function () use ($lies) {
        $q = $lies('api/react.php');
        if (!str_contains($q, 'board_user_can_access')) {
            throw new RuntimeException(
                'Die Schnittstelle nimmt jede post_id an — damit sind Reaktionen in '
                . 'passwortgeschuetzten Foren moeglich, ohne das Passwort zu kennen');
        }
        $prueft = strpos($q, 'board_user_can_access');
        $handelt = strpos($q, 'toggle_reaction(');
        if ($prueft > $handelt) throw new RuntimeException('Geprueft wird erst nach dem Schreiben');
    },

    'Reaktionen achten auf geschlossene Themen' => function () use ($lies) {
        if (!str_contains($lies('api/react.php'), 'is_closed')) {
            throw new RuntimeException('Ein geschlossenes Thema laesst sich weiter bereagieren');
        }
    },

    'Unfreigegebene Themen sind nicht ueber ihre ID erreichbar' => function () use ($lies) {
        $q = $lies('pages/thread.php');
        $ab = strpos($q, "\$thread = ");
        $rumpf = substr($q, $ab, 2500);
        if (!str_contains($rumpf, "is_approved")) {
            throw new RuntimeException('thread.php prueft die Freigabe nicht');
        }
    },

    'Schnellsuche und Schlagworte zeigen nur Freigegebenes' => function () use ($lies) {
        foreach (['api/quicksearch.php', 'lib/tags.php'] as $datei) {
            if (!str_contains($lies($datei), 'is_approved')) {
                throw new RuntimeException("$datei listet Themen aus der Warteschlange");
            }
        }
    },

    'Abmelden geht nur per POST mit Token' => function () use ($lies) {
        $q = $lies('pages/logout.php');
        if (!str_contains($q, "REQUEST_METHOD'] === 'POST'") || !str_contains($q, 'verify_csrf')) {
            throw new RuntimeException(
                'Abmelden laeuft ohne Methode- und Tokenpruefung — ein Bild auf einer '
                . 'fremden Seite meldet den Besucher ab und loescht seine Marke');
        }
    },

    'Sprachwahl aendert das Konto nicht mehr per Aufruf' => function () use ($lies) {
        $q = $lies('bootstrap.php');
        $ab = strpos($q, "// Sprache via ?lang=xx setzen");
        $rumpf = substr($q, $ab, 900);
        if (str_contains($rumpf, 'UPDATE users SET language')) {
            throw new RuntimeException(
                'Ein GET mit ?lang= schreibt weiterhin in die Benutzertabelle — '
                . 'jede fremde Seite kann die Sprache fremder Konten umstellen');
        }
    },

    'Rollen-Avatar nimmt kein SVG mehr an' => function () use ($lies) {
        $q = $lies('admin/index.php');
        $ab = strpos($q, "upload_role_avatar");
        $rumpf = substr($q, $ab, 1500);
        if (str_contains($rumpf, 'image/svg+xml')) {
            throw new RuntimeException(
                'SVG ist wieder erlaubt — eine skriptfaehige Datei unter fester Adresse '
                . 'in der eigenen Herkunft');
        }
    },

    'Vorlagenname aus dem Parameter wird geprueft' => function () use ($lies, $wurzel) {
        require_once $wurzel . '/lib/templates.php';
        if (!function_exists('template_name_gueltig')) {
            throw new RuntimeException('template_name_gueltig() fehlt');
        }
        foreach (['../../config', '..%2f..%2fconfig', 'nicht/vorhanden', ''] as $boese) {
            if (template_name_gueltig($boese)) {
                throw new RuntimeException("'$boese' wird als Vorlagenname akzeptiert");
            }
        }
        if (!template_name_gueltig('index')) {
            throw new RuntimeException('ein gueltiger Name wird abgewiesen');
        }
    },

    'E-Mail-Wechsel verraet keine vorhandenen Adressen' => function () use ($lies) {
        $q = $lies('lib/email_change.php');
        if (str_contains($q, "'Diese E-Mail-Adresse ist bereits in Verwendung.'")) {
            throw new RuntimeException(
                'Die Antwort unterscheidet belegte von freien Adressen — damit laesst '
                . 'sich abfragen, wer hier ein Konto hat');
        }
    },

    'Zwei-Faktor-Geheimnis wird verschluesselt abgelegt' => function () use ($lies) {
        if (!str_contains($lies('pages/settings.php'), 'forum_encrypt($secret)')) {
            throw new RuntimeException('Das Geheimnis geht im Klartext in die Datenbank');
        }
        if (!str_contains($lies('lib/twofactor.php'), "forum_decrypt((string) \$row['secret'])")) {
            throw new RuntimeException('Beim Lesen wird nicht entschluesselt');
        }
    },

    'Spalte fuer das verschluesselte Geheimnis ist breit genug' => function () use ($lies) {
        $q = $lies('install/migrations/migrate_v25_7.php');
        if (!str_contains($q, 'VARCHAR(255)')) {
            throw new RuntimeException(
                'user_2fa.secret bleibt VARCHAR(64) — der verschluesselte Wert passt '
                . 'nicht hinein, das Einrichten von 2FA endet in "Data too long"');
        }
    },
];
