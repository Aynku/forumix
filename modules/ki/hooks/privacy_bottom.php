<?php
/**
 * modules/ki/hooks/privacy_bottom.php – Auffang, wenn der Platzhalter fehlt
 *
 * Der vorgesehene Weg ist {{ki_hinweis}} im Text der Datenschutzerklärung —
 * dort bestimmt der Betreiber die Stelle. Steht er nicht drin, haengt der
 * Absatz hier unten an: lieber an der falschen Stelle als gar nicht.
 *
 * Derselbe Text wie im Platzhalter und durch dieselbe BBCode-Verarbeitung,
 * damit er nicht anders aussieht als der Rest der Seite.
 */
if (!defined('FORUM_RUNNING')) return;

$ki_seite = function_exists('get_page') ? get_page('privacy') : null;

// Steht der Platzhalter im Text, ist der Absatz schon oben — dann hier nichts.
if ($ki_seite && str_contains((string) ($ki_seite['content'] ?? ''), '{{ki_hinweis}}')) return;

// Der Seitentext geht als $kontext mit, damit die Ueberschrift dieselbe
// Nummer bekommt wie ueber den Platzhalter.
$kontext  = (string) ($ki_seite['content'] ?? '');
$ki_platz = include __DIR__ . '/../platzhalter.php';
$ki_text  = (string) ($ki_platz['{{ki_hinweis}}']['text'] ?? '');
if (trim($ki_text) === '') return;

echo '<div style="margin-top:24px">' . bbcode($ki_text) . '</div>';
