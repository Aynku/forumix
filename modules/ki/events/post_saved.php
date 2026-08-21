<?php
/**
 * modules/ki/events/post_saved.php – Kennzeichnung setzen
 *
 * Laeuft, nachdem ein Beitrag gespeichert wurde. Hier wird nur festgehalten,
 * ob das Modell daran mitgeschrieben hat; am Beitrag selbst aendert sich
 * nichts.
 *
 * Zwei Wege fuehren zur Kennzeichnung:
 *  1. Das versteckte Feld aus dem Editor — der normale Fall.
 *  2. Der Fingerabdruck aus der Sitzung — fuer den Fall, dass jemand das Feld
 *     entfernt hat. Steht der gelieferte Text unveraendert im Beitrag, gilt
 *     er als uebernommen.
 *
 * Verfuegbar aus module_event(): $post_id, $thread_id, $user_id, $content,
 * $freigegeben, $neues_thema.
 */

if (!defined('FORUM_RUNNING')) return;
if (!isset($post_id, $content)) return;

require_once __DIR__ . '/../lib/einstellungen.php';
require_once __DIR__ . '/../lib/befehle.php';

$befehl = '';

// Weg 1: was der Editor gemeldet hat.
$gemeldet = (string) ($_POST['ki_befehl'] ?? '');
if ($gemeldet !== '' && isset(ki_befehle()[$gemeldet])) {
    $befehl = $gemeldet;
}

// Weg 2: der Fingerabdruck. Er ersetzt Weg 1 nicht, er faengt ihn auf.
if ($befehl === '') {
    $marken = $_SESSION['ki_marken'] ?? [];
    if (is_array($marken)) {
        $abdruck = hash('sha256', trim((string) $content));
        if (isset($marken[$abdruck])) {
            $befehl = (string) $marken[$abdruck];
        }
    }
}

if ($befehl === '') return;

try {
    db_execute('INSERT IGNORE INTO ki_kennzeichnung (post_id, befehl) VALUES (?, ?)',
        [(int) $post_id, $befehl]);
} catch (Exception $e) {
    error_log('[ki] Kennzeichnung nicht gespeichert: ' . $e->getMessage());
}
