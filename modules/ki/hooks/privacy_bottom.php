<?php
/**
 * modules/ki/hooks/privacy_bottom.php – Absatz für die Datenschutzerklärung
 *
 * Nur wenn tatsaechlich Daten hinausgehen. Ein Absatz, der eine Weitergabe
 * beschreibt, die nicht stattfindet, ist genauso falsch wie ein fehlender —
 * wer ihn liest, glaubt, seine Beitraege gingen an einen fremden Dienst.
 *
 * Deshalb haengt jeder der beiden Teile an seiner eigenen Einstellung: der
 * Schreibhilfe-Absatz an einem hinterlegten Schluessel, der Moderations-Absatz
 * an der eingeschalteten Vorsortierung.
 */
if (!defined('FORUM_RUNNING')) return;

require_once __DIR__ . '/../lib/einstellungen.php';
require_once __DIR__ . '/../lib/anbieter.php';

$ki_p = ki_einstellungen();
if ((string) ($ki_p['schluessel'] ?? '') === '') return;

$ki_anbieter = KI_ANBIETER[$ki_p['anbieter'] ?? 'anthropic']['name'] ?? 'einem KI-Anbieter';
$ki_mod      = !empty($ki_p['mod_an']);
?>
<h3 style="margin-top:28px">Verarbeitung durch ein KI-System</h3>

<p>
  Dieses Forum setzt für einzelne Funktionen ein Sprachmodell von
  <strong><?= e($ki_anbieter) ?></strong> ein. Dabei werden Beitragstexte an
  diesen Anbieter übermittelt und dort verarbeitet. Es werden keine
  Benutzernamen, E-Mail-Adressen oder IP-Adressen mitgesendet.
</p>

<p>
  <strong>Schreibhilfe.</strong> Wer beim Verfassen eines Beitrags eine der
  Hilfsfunktionen benutzt — etwa Rechtschreibprüfung, Kürzen oder Übersetzen —,
  übermittelt den bearbeiteten Text an den Anbieter. Das geschieht nur auf
  ausdrückliche Anforderung durch einen Klick. Vor dem Senden weist das Forum
  auf erkannte persönliche Angaben hin und bietet an, sie zurückzuhalten.
  Zitate und Quelltext werden nicht übermittelt.
</p>

<?php if ($ki_mod): ?>
<p>
  <strong>Automatische Vorprüfung.</strong> Neue Beiträge werden nach dem
  Absenden automatisch auf Werbung und beleidigende Inhalte geprüft. Dazu wird
  der Beitragstext an den oben genannten Anbieter übermittelt. Der Beitrag wird
  dabei nicht zurückgehalten und nicht automatisch gelöscht; ergibt die Prüfung
  einen Verdacht, entsteht lediglich eine Meldung, der ein Moderator nachgeht.
  Die Entscheidung trifft in jedem Fall ein Mensch.
</p>
<?php endif; ?>

<p>
  Rechtsgrundlage ist Art. 6 Abs. 1 lit. f DSGVO (berechtigtes Interesse an
  einem funktionsfähigen und störungsfreien Forum). Die Verarbeitung durch den
  Anbieter richtet sich nach dessen Datenschutzbestimmungen.
</p>
