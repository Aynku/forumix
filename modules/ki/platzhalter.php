<?php
/**
 * modules/ki/platzhalter.php – Absatz für die Rechtstexte
 *
 * Als BBCode, nicht als HTML: Rechtstexte sind bearbeitbare Seiten und werden
 * durch bbcode() gerendert. HTML dazwischen bekaeme andere Abstaende als der
 * Text darum — es faellt sofort auf, dass da etwas Fremdes steht.
 *
 * Was hier steht, haengt an den Einstellungen: Ohne Schluessel geht nichts
 * hinaus, ohne eingeschaltete Vorsortierung wird nichts geprueft. Ein Absatz,
 * der eine Weitergabe beschreibt, die nicht stattfindet, ist genauso falsch
 * wie ein fehlender.
 */
if (!defined('FORUM_RUNNING')) return [];

require_once __DIR__ . '/lib/einstellungen.php';
require_once __DIR__ . '/lib/anbieter.php';

$cfg = ki_einstellungen();

if ((string) ($cfg['schluessel'] ?? '') === '') {
    // Nichts eingerichtet: der Platzhalter verschwindet spurlos aus dem Text.
    return ['{{ki_hinweis}}' => ['text' => '', 'hilfe' => 'KI-Hinweis (derzeit nichts eingerichtet)']];
}

$anbieter = KI_ANBIETER[$cfg['anbieter'] ?? 'anthropic']['name'] ?? 'einem KI-Anbieter';

/**
 * Die Ueberschrift setzt die Nummerierung des Dokuments fort.
 *
 * Ein fettes Wort zwischen nummerierten Ueberschriften faellt auf wie ein
 * Fremdkoerper — der Absatz soll aussehen, als haette ihn derselbe Mensch
 * geschrieben wie den Rest. Die naechste Nummer steht im Text: die hoechste
 * gefundene plus eins. Findet sich keine, bleibt die Ueberschrift ohne Nummer.
 *
 * $kontext kommt aus module_platzhalter() und ist der Seitentext.
 */
$nummer = '';
if (isset($kontext) && is_string($kontext)
    && preg_match_all('/\[h[23]\]\s*(\d+)\./', $kontext, $treffer)) {
    $nummer = (max(array_map('intval', $treffer[1])) + 1) . '. ';
}

$text = "[h2]" . $nummer . "Verarbeitung durch ein KI-System[/h2]\n\n"
      . "Dieses Forum setzt für einzelne Funktionen ein Sprachmodell von "
      . $anbieter . " ein. Dabei werden Beitragstexte an diesen Anbieter "
      . "übermittelt und dort verarbeitet. Benutzernamen, E-Mail-Adressen und "
      . "IP-Adressen werden nicht mitgesendet.\n\n"
      . "[b]Schreibhilfe.[/b] Wer beim Verfassen eines Beitrags eine der "
      . "Hilfsfunktionen benutzt — etwa Rechtschreibprüfung, Kürzen oder "
      . "Übersetzen —, übermittelt den bearbeiteten Text an den Anbieter. Das "
      . "geschieht nur auf ausdrückliche Anforderung durch einen Klick. Vor dem "
      . "Senden weist das Forum auf erkannte persönliche Angaben hin und bietet "
      . "an, sie nicht mitzuschicken. Zitate und Quelltext werden nicht "
      . "übermittelt.\n\n";

if (!empty($cfg['mod_an'])) {
    $text .= "[b]Automatische Vorprüfung.[/b] Neue Beiträge werden nach dem "
           . "Absenden automatisch auf Werbung und beleidigende Inhalte geprüft. "
           . "Dazu wird der Beitragstext an den oben genannten Anbieter "
           . "übermittelt. Der Beitrag bleibt dabei sichtbar und wird nicht "
           . "automatisch gelöscht. Ergibt die Prüfung einen Verdacht, entsteht "
           . "eine Meldung, der ein Moderator nachgeht. Die Entscheidung trifft "
           . "in jedem Fall ein Mensch.\n\n";
}

$text .= "Rechtsgrundlage ist Art. 6 Abs. 1 lit. f DSGVO (berechtigtes Interesse "
       . "an einem funktionsfähigen und störungsfreien Forum). Die Verarbeitung "
       . "durch den Anbieter richtet sich nach dessen Datenschutzbestimmungen.";

return ['{{ki_hinweis}}' => [
    'text'  => $text,
    'hilfe' => 'Absatz zur KI-Verarbeitung (Datenschutzerklärung)',
]];
