<?php
/**
 * modules/ki/hooks/thread_top.php – Liegengebliebenes nachholen
 *
 * Der Regelfall ist der Nachlauf direkt nach dem Beitrag. Bleibt dabei etwas
 * liegen — abgebrochene Anfrage, Neustart mitten drin —, faende es sonst nie
 * wieder jemand. Deshalb hier ein Blick auf die Warteschlange, aber nur auf
 * Eintraege, die schon eine Weile warten: alles Frische ist entweder gerade
 * in Arbeit oder eben fertig geworden.
 */
if (!defined('FORUM_RUNNING')) return;
if (!module_enabled('ki')) return;

require_once __DIR__ . '/../lib/moderation.php';

$ki_cfg = ki_einstellungen();
if (empty($ki_cfg['mod_an'])) return;

try {
    $liegt = db_count("SELECT COUNT(*) FROM ki_pruefung
                        WHERE status = 'offen' AND versuche < 3
                          AND created_at < (NOW() - INTERVAL 5 MINUTE)");
} catch (Exception $e) {
    return;
}

// Einer je Seitenaufruf reicht: es geht um Nachzuegler, nicht um Durchsatz.
if ($liegt > 0) ki_pruefung_spaeter(1);
