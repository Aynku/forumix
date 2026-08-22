<?php if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); } ?>
<?php /** @var array $cfg; @var array $anbieter; @var array $befehle; @var array $gruppen;
        @var array $verbrauch; @var int $gesamt; @var ?string $probe;
        @var bool $crypt_da; @var bool $hat_schluessel */ ?>

<div class="section-heading">✨ KI-Funktionen</div>

<?php if ($probe !== null): ?>
  <div class="flash <?= $probe === '' ? 'flash-success' : 'flash-error' ?>" style="margin-bottom:16px">
    <?= $probe === '' ? 'Verbindung steht — Schlüssel und Modell wurden angenommen.' : e($probe) ?>
  </div>
<?php endif; ?>

<div class="card" style="margin-bottom:16px;padding:14px 16px;font-size:13px;line-height:1.7">
  <strong>Wer zahlt:</strong> der Betreiber dieses Forums. Jeder Klick auf einen
  KI-Knopf ist eine Anfrage an den gewählten Anbieter und erscheint auf dessen
  Rechnung. Deshalb gibt es weiter unten zwei Bremsen — Zeichenzahl je Anfrage
  und Anfragen je Stunde und Person. Wer nichts einträgt, gibt nichts frei:
  ohne Schlüssel bleibt der Knopf im Editor unsichtbar.
</div>

<?php if (!empty($nachgezogen)): ?>
  <div class="flash flash-success" style="margin-bottom:16px">
    Dein API-Schlüssel lag noch im Klartext in der Datenbank und ist jetzt
    verschlüsselt. Falls davon schon eine Sicherung gemacht wurde, steht er dort
    weiter im Klartext — dann erzeuge beim Anbieter besser einen neuen.
  </div>
<?php endif; ?>

<?php if (!$crypt_da): ?>
  <div class="card" style="margin-bottom:16px;padding:14px 16px;
       border-left:3px solid var(--color-warning)">
    <strong>🔐 Erst einen Schlüssel in die config.php</strong>
    <p style="margin:6px 0;font-size:13px;line-height:1.6">
      Ohne ihn liegt dein API-Schlüssel im Klartext in der Datenbank — und damit
      auch in jeder Sicherung. Diese Installation hat noch keinen: Der Installer
      legt ihn erst seit Version 0.23.0 an. Nimm diese Zeile in deine
      <code>config.php</code> auf:
    </p>
    <pre style="white-space:pre-wrap;word-break:break-all;background:var(--bg-body);
         padding:10px;border-radius:6px;margin:0"><code>define('FORUM_CRYPT_KEY', '<?= e($crypt_vorschlag) ?>');</code></pre>
    <p style="margin:8px 0 0;font-size:13px;line-height:1.6">
      Danach <strong>nicht mehr ändern</strong> und beim Umzug mitnehmen — sonst ist
      nicht mehr lesbar, was damit gespeichert wurde. Der Schlüssel hilft gegen einen
      abhandengekommenen Datenbank-Abzug, nicht gegen jemanden, der den Server hat:
      der liest die <code>config.php</code> mit.
    </p>
  </div>
<?php endif; ?>

<form method="post" class="form-card">
  <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">

  <div class="form-row">
    <label>Anbieter</label>
    <select name="anbieter">
      <?php foreach ($anbieter as $code => $a): ?>
        <option value="<?= e($code) ?>" <?= ($cfg['anbieter'] ?? 'anthropic') === $code ? 'selected' : '' ?>>
          <?= e($a['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <div class="form-hint">
      Den Schlüssel gibt es beim jeweiligen Anbieter:
      <?php foreach ($anbieter as $a): ?>
        <?= e($a['name']) ?> → <code><?= e($a['hilfe']) ?></code>&nbsp;&nbsp;
      <?php endforeach; ?>
    </div>
  </div>

  <div class="form-row">
    <label>API-Schlüssel</label>
    <input type="password" name="schluessel" autocomplete="off"
           placeholder="<?= $hat_schluessel ? '•••••••• (hinterlegt — leer lassen, um ihn zu behalten)' : 'noch keiner hinterlegt' ?>">
    <div class="form-hint">
      Wird verschlüsselt gespeichert und danach nie wieder angezeigt.
      <?php if ($hat_schluessel): ?>
        <label style="display:inline-block;margin-left:8px">
          <input type="checkbox" name="schluessel_weg" value="1"> hinterlegten Schlüssel entfernen
        </label>
      <?php endif; ?>
    </div>
  </div>

  <div class="form-row">
    <label>Modell</label>
    <input type="text" name="modell" value="<?= e((string) ($cfg['modell'] ?? '')) ?>"
           placeholder="<?= e($anbieter[$cfg['anbieter'] ?? 'anthropic']['vorgabe'] ?? '') ?>">
    <div class="form-hint">
      Leer lassen für die Vorgabe. Ein kleines, schnelles Modell reicht für
      Rechtschreibung und Kürzen und kostet einen Bruchteil.
    </div>
  </div>

  <div class="section-heading" style="margin-top:24px">Befehle</div>
  <div class="form-hint" style="margin-bottom:10px">
    <strong>Kennzeichnen</strong> heißt: der Beitrag trägt sichtbar den Hinweis, dass
    ein Sprachmodell mitgeschrieben hat. Bei einer reinen Rechtschreibkorrektur ist
    das übertrieben — beim Zusammenfassen nicht, denn dabei entscheidet das Modell,
    was wegfällt.
  </div>

  <table class="forum-table" style="margin-bottom:8px">
    <thead>
      <tr>
        <th>Befehl</th>
        <th style="width:200px">Modell</th>
        <th style="width:90px;text-align:center">Freigegeben</th>
        <th style="width:110px;text-align:center">Kennzeichnen</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($befehle as $code => $b): ?>
        <?php
          $frei    = !in_array($code, (array) ($cfg['gesperrt'] ?? []), true);
          $marke   = ki_kennzeichnet($code, $cfg);
          $eigenes = (string) (($cfg['modelle'] ?? [])[$code] ?? '');
          $urteil  = ki_braucht_urteil($code);
        ?>
        <tr>
          <td>
            <strong><?= e($b['name']) ?></strong>
            <div style="font-size:12px;color:var(--text-muted)"><?= e($b['hinweis']) ?></div>
          </td>
          <td>
            <input type="text" name="modell_je[<?= e($code) ?>]" value="<?= e($eigenes) ?>"
                   placeholder="wie oben" style="width:100%;font-size:12px">
            <?php if ($urteil): ?>
              <div style="font-size:11px;color:var(--color-warning);margin-top:3px">
                entscheidet mit, was wegfällt
              </div>
            <?php endif; ?>
          </td>
          <td style="text-align:center">
            <input type="checkbox" name="frei[<?= e($code) ?>]" value="1" <?= $frei ? 'checked' : '' ?>>
          </td>
          <td style="text-align:center">
            <input type="checkbox" name="marke[<?= e($code) ?>]" value="1" <?= $marke ? 'checked' : '' ?>>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <div class="form-hint" style="margin-bottom:8px">
    <strong>Modell je Befehl:</strong> leer heißt „das oben eingetragene". Sinnvoll ist
    der Unterschied dort, wo <em>entscheidet mit, was wegfällt</em> steht — beim Kürzen
    und Zusammenfassen wählt das Modell aus, was im Beitrag bleibt. Bei Rechtschreibung
    und Übersetzen macht ein größeres Modell messbar keinen Unterschied, kostet aber
    ein Vielfaches.
  </div>

  <div class="section-heading" style="margin-top:24px">Wer darf</div>
  <div class="form-row">
    <?php if (!$gruppen): ?>
      <div class="form-hint">Es gibt noch keine Gruppen. Inhaber und Administratoren dürfen immer.</div>
    <?php else: ?>
      <div style="display:flex;flex-wrap:wrap;gap:14px">
        <?php foreach ($gruppen as $g): ?>
          <label style="display:flex;align-items:center;gap:6px">
            <input type="checkbox" name="gruppen[]" value="<?= (int) $g['group_id'] ?>"
                   <?= in_array((int) $g['group_id'], array_map('intval', (array) ($cfg['gruppen'] ?? [])), true) ? 'checked' : '' ?>>
            <?= e($g['name']) ?>
          </label>
        <?php endforeach; ?>
      </div>
      <div class="form-hint">
        Ohne Haken dürfen nur Inhaber und Administratoren. Das ist Absicht: eine
        Funktion, die Geld kostet, soll nicht versehentlich für alle offen stehen.
      </div>
    <?php endif; ?>
  </div>

  <div class="section-heading" style="margin-top:24px">Bremsen</div>
  <div class="form-row">
    <label>Höchstens Zeichen je Anfrage</label>
    <input type="number" name="max_zeichen" min="500" max="40000"
           value="<?= (int) ($cfg['max_zeichen'] ?? 8000) ?>">
    <div class="form-hint">Längere Texte weist der Server ab, mit dem Hinweis, einen Abschnitt zu markieren.</div>
  </div>
  <div class="form-row">
    <label>Anfragen je Stunde und Person</label>
    <input type="number" name="pro_stunde" min="0" max="500"
           value="<?= (int) ($cfg['pro_stunde'] ?? 30) ?>">
    <div class="form-hint">0 schaltet die Bremse ab. Das ist nur sinnvoll, wenn du der einzige Benutzer bist.</div>
  </div>

  <div class="section-heading" style="margin-top:24px">Beiträge vorsortieren</div>
  <div class="form-hint" style="margin-bottom:10px">
    Neue Beiträge werden nach dem Absenden auf Werbung und Beleidigungen geprüft.
    Der Beitrag bleibt dabei sichtbar und wird nicht zurückgehalten — bei einem
    Verdacht entsteht eine Meldung, der ein Moderator nachgeht. Gelöscht oder
    gesperrt wird nichts automatisch.
  </div>

  <div class="form-row">
    <label>
      <input type="checkbox" name="mod_an" value="1" <?= !empty($cfg['mod_an']) ? 'checked' : '' ?>>
      Vorsortierung einschalten
    </label>
    <div class="form-hint">
      Im Auslieferungszustand aus. Sobald sie läuft, steht am Editor ein Hinweis
      darauf und in der Datenschutzerklärung ein Absatz dazu.
    </div>
  </div>

  <div class="form-row">
    <label>Meldendes Konto</label>
    <input type="text" name="mod_melder" list="ki-melder-vorschlaege" autocomplete="off"
           value="<?= e($melder_name) ?>" placeholder="Benutzername">
    <datalist id="ki-melder-vorschlaege">
      <?php foreach ($melder_wahl as $m): ?>
        <option value="<?= e($m['username']) ?>"></option>
      <?php endforeach; ?>
    </datalist>
    <div class="form-hint">
      Unter diesem Namen erscheinen die Meldungen. Am besten ein Konto, das
      sonst wenig schreibt — dann ist auf einen Blick klar, woher die Meldung
      kommt. Die Vorschläge sind nur eine Hilfe; eintragen lässt sich jedes
      Konto. Ohne Eintrag entsteht keine Meldung.
    </div>
  </div>

  <div class="form-row">
    <label>Nur bis zu dieser Beitragszahl prüfen</label>
    <input type="number" name="mod_bis" min="0" max="10000"
           value="<?= (int) ($cfg['mod_bis'] ?? 20) ?>">
    <div class="form-hint">
      Wer schon länger dabei ist, fängt selten mit Werbung an. 0 prüft jeden
      Beitrag — das kostet ein Vielfaches. Die Moderation selbst wird nie geprüft.
    </div>
  </div>

  <div class="form-row">
    <label>Modell für die Vorprüfung</label>
    <input type="text" name="mod_modell" value="<?= e((string) ($cfg['mod_modell'] ?? '')) ?>"
           placeholder="wie oben">
    <div class="form-hint">
      Hier reicht das kleinste Modell: Die Frage ist „Werbung, Beleidigung oder
      in Ordnung", nicht das Umschreiben eines Textes.
    </div>
  </div>

  <?php if (!empty($mod_offen) || !empty($mod_befunde)): ?>
    <div class="form-hint" style="margin-bottom:14px">
      <?php if (!empty($mod_offen)): ?>
        <strong><?= (int) $mod_offen ?></strong> Beiträge warten noch auf die Prüfung.
      <?php endif; ?>
      <?php if (!empty($mod_befunde)): ?>
        Sieben Tage:
        <?php foreach ($mod_befunde as $b): ?>
          <?= e((string) $b['befund']) ?> <strong><?= (int) $b['anzahl'] ?></strong><?= $b === end($mod_befunde) ? '' : ' ·' ?>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <div class="form-actions" style="margin-top:20px;display:flex;gap:10px;flex-wrap:wrap">
    <button type="submit" class="btn btn-primary">Speichern</button>
    <button type="submit" name="probe" value="1" class="btn">Speichern und Verbindung prüfen</button>
  </div>
</form>

<?php if ($gesamt > 0): ?>
  <div class="section-heading" style="margin-top:28px">Die letzten sieben Tage</div>
  <table class="forum-table">
    <thead>
      <tr><th>Befehl</th><th style="width:110px;text-align:right">Anfragen</th><th style="width:140px;text-align:right">Zeichen gesendet</th></tr>
    </thead>
    <tbody>
      <?php foreach ($verbrauch as $v): ?>
        <tr>
          <td><?= e($befehle[$v['befehl']]['name'] ?? $v['befehl']) ?></td>
          <td style="text-align:right"><?= number_format((int) $v['anzahl'], 0, ',', '.') ?></td>
          <td style="text-align:right"><?= number_format((int) $v['zeichen'], 0, ',', '.') ?></td>
        </tr>
      <?php endforeach; ?>
      <tr>
        <td><strong>Zusammen</strong></td>
        <td style="text-align:right"><strong><?= number_format($gesamt, 0, ',', '.') ?></strong></td>
        <td></td>
      </tr>
    </tbody>
  </table>
  <div class="form-hint" style="margin-top:8px">
    Gezählt werden Anfragen, nicht Kosten — was eine Anfrage kostet, hängt vom
    Modell ab und steht beim Anbieter.
  </div>
<?php endif; ?>
