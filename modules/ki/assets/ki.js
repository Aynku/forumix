/**
 * modules/ki/assets/ki.js – Schreibhilfe im Editor
 *
 * Der Ablauf ist absichtlich umstaendlich: anfordern, ansehen, uebernehmen.
 * Kein Knopf schreibt direkt in den Text. Was das Modell liefert, ist ein
 * Vorschlag, und ein Vorschlag wird gelesen, bevor er gilt.
 */
(function () {
  'use strict';

  var K = window.KI_EINSTELLUNGEN;
  if (!K || !K.befehle || !K.befehle.length) return;

  // ── Wortweiser Vergleich ────────────────────────────────────────────
  //
  // Damit man sieht, WAS sich geaendert hat, nicht nur DASS sich etwas
  // geaendert hat. Beim Glattbuegeln verschwindet sonst leicht ein "nicht",
  // und in einem Forum steht danach das Gegenteil dessen, was gemeint war.

  /** Zerlegt in Woerter samt folgendem Leerraum, damit nichts verlorengeht. */
  function inWoerter(text) {
    return text.match(/\S+\s*/g) || [];
  }

  /**
   * Laengste gemeinsame Folge, klassisch ueber eine Tabelle.
   * Bei sehr langen Texten wird darauf verzichtet — die Tabelle waechst mit
   * dem Produkt beider Laengen, und eine Sanduhr hilft niemandem.
   */
  var DIFF_GRENZE = 1200;

  /**
   * Zwei Wörter sind dasselbe Wort, wenn ihr Kern gleich ist.
   *
   * Der anhängende Leerraum gehört nicht dazu: Bricht das Modell eine Zeile
   * anders um, wäre "der\n" sonst etwas anderes als "der " — und jedes Wort
   * am Zeilenende erschiene als geändert, obwohl es unverändert dasteht.
   */
  function gleich(x, y) {
    return x.trim() === y.trim();
  }

  function vergleiche(alt, neu) {
    var a = inWoerter(alt), b = inWoerter(neu);
    if (a.length > DIFF_GRENZE || b.length > DIFF_GRENZE) return null;

    var n = a.length, m = b.length, i, j;
    var tab = new Array(n + 1);
    for (i = 0; i <= n; i++) tab[i] = new Int32Array(m + 1);

    for (i = n - 1; i >= 0; i--) {
      for (j = m - 1; j >= 0; j--) {
        tab[i][j] = gleich(a[i], b[j])
          ? tab[i + 1][j + 1] + 1
          : Math.max(tab[i + 1][j], tab[i][j + 1]);
      }
    }

    var teile = [];
    i = 0; j = 0;
    while (i < n && j < m) {
      // Bei '=' beide Fassungen mitführen: links soll der alte Umbruch
      // stehen, rechts der neue, und markiert wird trotzdem nichts.
      if (gleich(a[i], b[j]))                  { teile.push({t: '=', w: a[i], w2: b[j]}); i++; j++; }
      else if (tab[i + 1][j] >= tab[i][j + 1]) { teile.push({t: '-', w: a[i]}); i++; }
      else                                     { teile.push({t: '+', w: b[j]}); j++; }
    }
    while (i < n) { teile.push({t: '-', w: a[i]}); i++; }
    while (j < m) { teile.push({t: '+', w: b[j]}); j++; }
    return verfeinern(teile);
  }

  /**
   * Markiert innerhalb eines geänderten Wortes nur den geänderten Teil.
   *
   * Kommt bei einer Rechtschreibkorrektur ein Komma dazu, wäre sonst "sagen"
   * rot und "sagen," grün — der Leser sieht zwei Wörter markiert, die für ihn
   * identisch aussehen, und sucht den Unterschied. Jetzt bleibt "sagen"
   * unmarkiert und nur das Komma ist grün.
   *
   * Nur bei Wörtern, die sich wirklich ähneln: bei "und" und "oder" wäre das
   * gemeinsame "d" ein Zufall und die Hervorhebung Unsinn.
   */
  function verfeinern(teile) {
    var aus = [];
    for (var i = 0; i < teile.length; i++) {
      var weg = teile[i], hin = teile[i + 1];
      if (weg.t === '-' && hin && hin.t === '+') {
        var x = weg.w, y = hin.w;
        var p = 0;
        while (p < x.length && p < y.length && x[p] === y[p]) p++;
        var q = 0;
        while (q < x.length - p && q < y.length - p && x[x.length - 1 - q] === y[y.length - 1 - q]) q++;

        var kurz = Math.min(x.trim().length, y.trim().length);
        if (p + q >= 2 && p + q >= kurz * 0.4) {
          if (p) aus.push({t: '=', w: x.substring(0, p)});
          if (x.length - p - q > 0) aus.push({t: '-', w: x.substring(p, x.length - q)});
          if (y.length - p - q > 0) aus.push({t: '+', w: y.substring(p, y.length - q)});
          if (q) aus.push({t: '=', w: x.substring(x.length - q)});
          i++;
          continue;
        }
      }
      aus.push(weg);
    }
    return aus;
  }

  /** Eine Spalte ohne Hervorhebung — wenn der Text fuer den Vergleich zu lang war. */
  function schlichteSpalte(text) {
    var box = document.createElement('div');
    box.className = 'ki-text';
    box.textContent = text;
    return box;
  }

  /** Baut eine Spalte: 'alt' zeigt Entferntes, 'neu' zeigt Hinzugekommenes. */
  function spalte(teile, seite) {
    var box = document.createElement('div');
    box.className = 'ki-text';

    teile.forEach(function (s) {
      if (s.t === '=') {
        // Links die alte Fassung, rechts die neue — sie unterscheiden sich
        // hoechstens im Leerraum, und der gehoert zur jeweiligen Seite.
        box.appendChild(document.createTextNode(seite === 'neu' && s.w2 ? s.w2 : s.w));
        return;
      }
      if (seite === 'alt' && s.t !== '-') return;
      if (seite === 'neu' && s.t !== '+') return;
      var m = document.createElement('mark');
      m.className = s.t === '-' ? 'ki-weg' : 'ki-neu';
      m.textContent = s.w;
      box.appendChild(m);
    });
    return box;
  }

  // ── Prüfung auf schutzwürdige Daten ─────────────────────────────────
  //
  // Was hier gefunden wird, verlässt den Browser nicht. Der Nutzer sieht die
  // Funde, BEVOR etwas gesendet wird, und entscheidet: abbrechen, schwärzen
  // oder trotzdem senden.
  //
  // Beim Schwärzen geht an Stelle des Fundes eine Marke hinaus, und nach der
  // Antwort steht der echte Wert wieder da. Die Adresse ist damit weder beim
  // Anbieter noch auf dem eigenen Server gewesen — und der Text ist trotzdem
  // vollständig.

  var MUSTER = [
    {art: 'E-Mail-Adresse',   re: /[\w.+-]+@[\w-]+\.[\w.-]{2,}/g},
    // Die letzte Gruppe ist oft kuerzer als vier Zeichen: eine deutsche IBAN
    // hat 22 Stellen, also endet sie auf ein Paar. Ohne den Nachsatz blieben
    // die letzten Ziffern beim Schwaerzen stehen.
    {art: 'IBAN',             re: /\b[A-Z]{2}\d{2}(?:[ ]?[A-Z0-9]{4}){2,7}(?:[ ]?[A-Z0-9]{1,3})?\b/g},
    // Beginnt mit 0 oder +. Ohne diese Bedingung war "PassivePorts 49152 50000"
    // aus einem Codeblock eine Telefonnummer — zehn Ziffern mit Leerzeichen
    // reichten. Deutsche und internationale Nummern fangen so an; eine
    // Portnummer, eine Bestellnummer und ein Zahlenpaar nicht.
    // Das (?<!\d) davor ist noetig, sonst faengt "2019 2020" bei der Null in
    // der Jahreszahl an und ergibt "019 2020".
    {art: 'Telefonnummer',    re: /(?<![\d+])(?:\+\d{1,3}|0)[ /-]?\(?\d{2,5}\)?[ /-]?\d{3,}(?:[ -]?\d{2,})?/g, pruef: telefonPlausibel},
    {art: 'Zugangsschlüssel', re: /\b(?:sk-|pk-|ghp_|gho_|xox[baprs]-|AKIA|AIza)[A-Za-z0-9_-]{10,}\b/g},
    {art: 'Kartennummer',     re: /\b(?:\d[ -]?){12,18}\d\b/g, pruef: luhn},
    {art: 'IP-Adresse',       re: /\b(?:\d{1,3}\.){3}\d{1,3}\b/g, pruef: ipPlausibel}
  ];

  /** Kartennummern haben eine Prüfziffer — ohne sie wäre jede lange Zahl ein Fund. */
  function luhn(s) {
    var z = s.replace(/[^\d]/g, '');
    if (z.length < 13 || z.length > 19) return false;
    var summe = 0, doppelt = false;
    for (var i = z.length - 1; i >= 0; i--) {
      var d = +z[i];
      if (doppelt) { d *= 2; if (d > 9) d -= 9; }
      summe += d;
      doppelt = !doppelt;
    }
    return summe % 10 === 0;
  }

  /** Vier Zahlen mit Punkten sind nicht automatisch eine Adresse. */
  function ipPlausibel(s) {
    return s.split('.').every(function (t) { return +t >= 0 && +t <= 255 && !(t.length > 1 && t[0] === '0'); });
  }

  /** Genug Ziffern, um eine Nummer zu sein — und nicht bloß eine Jahreszahl. */
  function telefonPlausibel(s) {
    var z = s.replace(/[^\d]/g, '');
    return z.length >= 7 && z.length <= 15;
  }

  /**
   * Bereiche, die ohnehin nicht gesendet werden: [code] und [quote].
   *
   * Was dort steht, trennt der Server ab — eine Warnung darüber wäre eine
   * Warnung vor etwas, das nicht passiert. Und genau daran stirbt eine
   * Warnung: die IP-Adresse aus der eigenen Beispielkonfiguration wird beim
   * dritten Mal weggeklickt, und beim vierten Mal auch die echte.
   *
   * Gesucht wird flach mit einem Ausdruck — hier geht es nur darum, welche
   * Stellen ausgespart werden. Das genaue Zählen der Verschachtelung macht
   * der Server (lib/schutz.php), und er entscheidet auch, was hinausgeht.
   */
  function geschuetzteBereiche(text) {
    var re = /\[(code|quote)(=[^\]]*)?\][\s\S]*?\[\/\1\]/gi;
    var bereiche = [], t;
    while ((t = re.exec(text)) !== null) {
      bereiche.push({von: t.index, bis: t.index + t[0].length});
    }
    return bereiche;
  }

  function liegtIn(bereiche, von, bis) {
    return bereiche.some(function (b) { return von >= b.von && bis <= b.bis; });
  }

  /**
   * Sucht die Funde. Überlappungen fallen weg: eine IBAN enthält Ziffernfolgen,
   * die auch als Kartennummer durchgehen könnten.
   */
  function funde(text) {
    var geschuetzt = geschuetzteBereiche(text);
    var alle = [];
    MUSTER.forEach(function (m) {
      m.re.lastIndex = 0;
      var t;
      while ((t = m.re.exec(text)) !== null) {
        var wert = t[0].trim();
        if (m.pruef && !m.pruef(wert)) continue;
        if (liegtIn(geschuetzt, t.index, t.index + t[0].length)) continue;
        alle.push({art: m.art, wert: wert, von: t.index, bis: t.index + t[0].length});
      }
    });
    alle.sort(function (a, b) { return a.von - b.von || (b.bis - b.von) - (a.bis - a.von); });

    var ohneUeberschneidung = [];
    var bisher = -1;
    alle.forEach(function (f) {
      if (f.von >= bisher) { ohneUeberschneidung.push(f); bisher = f.bis; }
    });
    return ohneUeberschneidung;
  }

  /** Zeigt nur Anfang und Ende — der Fund soll erkennbar sein, nicht lesbar. */
  function angedeutet(wert) {
    if (wert.length <= 8) return wert[0] + '…' + wert[wert.length - 1];
    return wert.substring(0, 4) + '…' + wert.substring(wert.length - 3);
  }

  /** Ersetzt die Funde durch Marken. Gibt Text und Rückweg zurück. */
  function schwaerzen(text, liste) {
    var zurueck = [];
    for (var i = liste.length - 1; i >= 0; i--) {
      var f = liste[i];
      zurueck.unshift(f.wert);
      text = text.substring(0, f.von) + '⟦DATEN' + i + '⟧' + text.substring(f.bis);
    }
    return {text: text, werte: zurueck};
  }

  function entschwaerzen(text, werte) {
    werte.forEach(function (w, i) {
      text = text.split('⟦DATEN' + i + '⟧').join(w);
    });
    return text;
  }

  // ── Werkzeug ────────────────────────────────────────────────────────

  function textfeld(editorId) {
    return document.getElementById(editorId);
  }

  /** Markierter Abschnitt, sonst der ganze Inhalt. */
  function holeText(ta) {
    var s = ta.selectionStart, e = ta.selectionEnd;
    if (s !== e) return {text: ta.value.substring(s, e), von: s, bis: e, teil: true};
    return {text: ta.value, von: 0, bis: ta.value.length, teil: false};
  }

  function menueSchliessen() {
    var alle = document.querySelectorAll('.ki-menue');
    for (var i = 0; i < alle.length; i++) alle[i].remove();
  }

  /**
   * Haengt ein Menue an den Seitenkoerper und stellt es an den Knopf.
   *
   * Nicht in den Editor: der hat overflow:hidden fuer seine runden Ecken, und
   * ein Menue mit vier Eintraegen ragt darueber hinaus — die Haelfte waere
   * abgeschnitten. Passt es nach unten nicht mehr auf den Schirm, klappt es
   * nach oben auf.
   */
  function anstellen(menue, knopf) {
    document.body.appendChild(menue);
    var k = knopf.getBoundingClientRect();
    var m = menue.getBoundingClientRect();
    var platzUnten = window.innerHeight - k.bottom;

    var oben = (platzUnten < m.height + 12 && k.top > m.height + 12)
      ? k.top + window.scrollY - m.height - 6
      : k.bottom + window.scrollY + 6;

    // Rechtsbuendig zum Knopf, aber nie ueber den linken Rand hinaus.
    var links = Math.max(8, k.right + window.scrollX - m.width);

    menue.style.top  = Math.round(oben) + 'px';
    menue.style.left = Math.round(links) + 'px';
    menue.style.visibility = 'visible';
  }

  // ── Menue am Knopf ──────────────────────────────────────────────────

  function menueOeffnen(knopf) {
    menueSchliessen();
    var editorId = knopf.getAttribute('data-ki-editor');
    var ta = textfeld(editorId);
    if (!ta) return;

    var menue = document.createElement('div');
    menue.className = 'ki-menue';

    var stueck = holeText(ta);
    var kopf = document.createElement('div');
    kopf.className = 'ki-menue-kopf';
    kopf.textContent = stueck.teil
      ? 'Nimmt nur das Markierte'
      : 'Nimmt den ganzen Beitrag';
    menue.appendChild(kopf);

    K.befehle.forEach(function (b) {
      var e = document.createElement('button');
      e.type = 'button';
      e.className = 'ki-menue-punkt';
      e.innerHTML = '<span class="ki-menue-name"></span><span class="ki-menue-hinweis"></span>';
      e.querySelector('.ki-menue-name').textContent = b.name + (b.marke ? ' *' : '');
      e.querySelector('.ki-menue-hinweis').textContent = b.hinweis;
      e.addEventListener('click', function () {
        menueSchliessen();
        if (b.ziel) spracheFragen(ta, b);
        else starte(ta, b, '');
      });
      menue.appendChild(e);
    });

    var fuss = document.createElement('div');
    fuss.className = 'ki-menue-fuss';
    fuss.textContent = '* am Beitrag steht danach, dass die KI geholfen hat';
    menue.appendChild(fuss);

    anstellen(menue, knopf);

    setTimeout(function () {
      document.addEventListener('click', function zu(ev) {
        if (menue.contains(ev.target) || ev.target === knopf) return;
        menueSchliessen();
        document.removeEventListener('click', zu);
      });
    }, 0);
  }

  function spracheFragen(ta, befehl) {
    var wahl = document.createElement('div');
    wahl.className = 'ki-menue ki-menue-sprachen';
    var kopf = document.createElement('div');
    kopf.className = 'ki-menue-kopf';
    kopf.textContent = 'Wohin übersetzen?';
    wahl.appendChild(kopf);

    Object.keys(K.sprachen).forEach(function (code) {
      var e = document.createElement('button');
      e.type = 'button';
      e.className = 'ki-menue-punkt';
      e.textContent = K.sprachen[code];
      e.addEventListener('click', function () {
        wahl.remove();
        starte(ta, befehl, code);
      });
      wahl.appendChild(e);
    });

    var knopf = document.querySelector('.ki-knopf[data-ki-editor="' + ta.id + '"]');
    if (!knopf) return;
    anstellen(wahl, knopf);

    setTimeout(function () {
      document.addEventListener('click', function zu(ev) {
        if (wahl.contains(ev.target)) return;
        wahl.remove();
        document.removeEventListener('click', zu);
      });
    }, 0);
  }

  // ── Anfrage ─────────────────────────────────────────────────────────

  function starte(ta, befehl, ziel) {
    var stueck = holeText(ta);
    if (!stueck.text.trim()) {
      panelFehler(ta, 'Da ist noch kein Text.');
      return;
    }

    // Erst nachsehen, ob etwas darin steht, das nicht hinausgehen sollte.
    var gefunden = funde(stueck.text);
    if (gefunden.length) {
      panelWarnung(ta, stueck, gefunden, befehl, ziel);
      return;
    }
    senden(ta, stueck, stueck.text, [], befehl, ziel);
  }

  /**
   * Der Halt vor dem Senden. Keine Vorauswahl, kein "wird schon passen":
   * hier entscheidet der Verfasser, und bis er entschieden hat, geht nichts
   * hinaus.
   */
  function panelWarnung(ta, stueck, gefunden, befehl, ziel) {
    var p = panel(ta);
    if (!p) return;
    p.className = 'ki-panel ki-panel-warnung';

    var kopf = document.createElement('div');
    kopf.className = 'ki-kopf';
    var stark = document.createElement('strong');
    stark.textContent = gefunden.length === 1
      ? 'Eine Angabe, die du vielleicht nicht mitschicken willst'
      : gefunden.length + ' Angaben, die du vielleicht nicht mitschicken willst';
    kopf.appendChild(stark);
    p.appendChild(kopf);

    var liste = document.createElement('ul');
    liste.className = 'ki-funde';
    gefunden.forEach(function (f) {
      var li = document.createElement('li');
      var art = document.createElement('span');
      art.className = 'ki-fund-art';
      art.textContent = f.art;
      li.appendChild(art);
      li.appendChild(document.createTextNode(' ' + angedeutet(f.wert)));
      liste.appendChild(li);
    });
    p.appendChild(liste);

    // Ein Satz, Hauptsatz, kein Fachwort. Er muss zwei Fehlschluesse
    // ausraeumen: "wird jetzt veroeffentlicht" und "die Angaben fallen aus
    // dem Text". Beides ist falsch, und beides hat schon jemand gelesen.
    var hinweis = document.createElement('div');
    hinweis.className = 'ki-fund-hinweis';
    hinweis.textContent = 'Sie bleiben in deinem Beitrag stehen. Es geht nur darum, '
      + 'ob die KI sie zu sehen bekommt.';
    p.appendChild(hinweis);

    var leiste = document.createElement('div');
    leiste.className = 'ki-leiste';

    // "Zurückhalten" statt "ohne": ohne klingt nach Verlust, dabei bleibt der
    // Text vollständig. Und kein "senden" — der Knopf steht eine Handbreit
    // ueber "Antworten", und in einem Forum heisst senden "veroeffentlichen".
    leiste.appendChild(knopf('Nicht mitschicken', 'ki-btn ki-btn-haupt', function () {
      var g = schwaerzen(stueck.text, gefunden);
      senden(ta, stueck, g.text, g.werte, befehl, ziel);
    }));

    leiste.appendChild(knopf('Trotzdem mitschicken', 'ki-btn', function () {
      senden(ta, stueck, stueck.text, [], befehl, ziel);
    }));

    leiste.appendChild(knopf('Abbrechen', 'ki-btn ki-btn-still', function () {
      p.remove();
      ta.focus();
    }));

    p.appendChild(leiste);
  }

  function senden(ta, stueck, text, geschwaerzt, befehl, ziel) {
    panelWarten(ta, befehl.name);

    var daten = new FormData();
    daten.append('csrf', K.csrf);
    daten.append('befehl', befehl.code);
    daten.append('ziel', ziel);
    daten.append('text', text);

    fetch(K.url, {method: 'POST', body: daten, credentials: 'same-origin'})
      .then(function (r) { return r.json().catch(function () { return {ok: false, fehler: 'Unverständliche Antwort.'}; }); })
      .then(function (d) {
        if (!d.ok) { panelFehler(ta, d.fehler || 'Es hat nicht geklappt.'); return; }
        if (geschwaerzt.length) {
          d.text = entschwaerzen(d.text, geschwaerzt);
          d.geschwaerzt = geschwaerzt.length;
        }
        panelErgebnis(ta, stueck, d, befehl, ziel);
      })
      .catch(function () {
        panelFehler(ta, 'Die Verbindung ist abgebrochen.');
      });
  }

  // ── Panel ───────────────────────────────────────────────────────────

  function panel(ta) {
    var editor = ta.closest('.editor');
    if (!editor) return null;
    var alt = editor.querySelector('.ki-panel');
    if (alt) alt.remove();
    var p = document.createElement('div');
    p.className = 'ki-panel';
    var leiste = editor.querySelector('.editor-toolbar');
    if (leiste) leiste.insertAdjacentElement('afterend', p);
    else editor.insertBefore(p, editor.firstChild);
    return p;
  }

  function panelWarten(ta, name) {
    var p = panel(ta);
    if (!p) return;
    var box = document.createElement('div');
    box.className = 'ki-warten';
    var punkte = document.createElement('span');
    punkte.className = 'ki-punkte';
    box.appendChild(punkte);
    box.appendChild(document.createTextNode(name + ' läuft …'));
    p.appendChild(box);
  }

  function panelFehler(ta, text) {
    var p = panel(ta);
    if (!p) return;
    p.className = 'ki-panel ki-panel-fehler';
    var box = document.createElement('div');
    box.className = 'ki-fehler';
    box.textContent = text;
    var zu = document.createElement('button');
    zu.type = 'button';
    zu.className = 'ki-btn ki-btn-still';
    zu.textContent = 'Schließen';
    zu.addEventListener('click', function () { p.remove(); });
    box.appendChild(zu);
    p.appendChild(box);
  }

  function panelErgebnis(ta, stueck, d, befehl, ziel) {
    var p = panel(ta);
    if (!p) return;

    var teile = vergleiche(stueck.text, d.text);

    var kopf = document.createElement('div');
    kopf.className = 'ki-kopf';
    kopf.innerHTML = '<strong></strong>';
    kopf.querySelector('strong').textContent = befehl.name;
    if (!teile) {
      var hinweis = document.createElement('span');
      hinweis.className = 'ki-kopf-hinweis';
      hinweis.textContent = 'Der Text ist zu lang, um die Unterschiede zu färben. Bitte selbst vergleichen.';
      kopf.appendChild(hinweis);
    }
    if (d.hinweis) {
      var warn = document.createElement('span');
      warn.className = 'ki-kopf-hinweis';
      warn.textContent = d.hinweis;
      kopf.appendChild(warn);
    }
    if (d.geschwaerzt) {
      var ok = document.createElement('span');
      ok.className = 'ki-kopf-still';
      ok.textContent = d.geschwaerzt === 1
        ? 'Eine Angabe wurde nicht mitgeschickt.'
        : d.geschwaerzt + ' Angaben wurden nicht mitgeschickt.';
      kopf.appendChild(ok);
    }
    p.appendChild(kopf);

    var raster = document.createElement('div');
    raster.className = 'ki-raster';

    var links = document.createElement('div');
    links.className = 'ki-spalte';
    links.innerHTML = '<div class="ki-spalte-kopf">Vorher</div>';
    links.appendChild(teile ? spalte(teile, 'alt') : schlichteSpalte(stueck.text));

    var rechts = document.createElement('div');
    rechts.className = 'ki-spalte ki-spalte-neu';
    rechts.innerHTML = '<div class="ki-spalte-kopf">Vorschlag</div>';
    rechts.appendChild(teile ? spalte(teile, 'neu') : schlichteSpalte(d.text));

    raster.appendChild(links);
    raster.appendChild(rechts);
    p.appendChild(raster);

    var leiste = document.createElement('div');
    leiste.className = 'ki-leiste';

    if (d.kennzeichnet) {
      var marke = document.createElement('span');
      marke.className = 'ki-marke-hinweis';
      marke.textContent = 'Am Beitrag steht danach, dass die KI geholfen hat';
      leiste.appendChild(marke);
    }

    leiste.appendChild(knopf('Übernehmen', 'ki-btn ki-btn-haupt', function () {
      uebernehmen(ta, stueck, d.text);
      merken(ta, d);
      p.remove();
    }));

    leiste.appendChild(knopf('Darunter einfügen', 'ki-btn', function () {
      ta.value = ta.value.replace(/\s*$/, '') + '\n\n' + d.text;
      merken(ta, d);
      p.remove();
      ta.focus();
    }));

    // Mit derselben Zielsprache — sonst uebersetzt "Noch einmal" ins Leere.
    leiste.appendChild(knopf('Noch einmal', 'ki-btn ki-btn-still', function () {
      starte(ta, befehl, ziel || '');
    }));

    leiste.appendChild(knopf('Verwerfen', 'ki-btn ki-btn-still', function () {
      p.remove();
      ta.focus();
    }));

    p.appendChild(leiste);
  }

  function knopf(text, klasse, tu) {
    var b = document.createElement('button');
    b.type = 'button';
    b.className = klasse;
    b.textContent = text;
    b.addEventListener('click', tu);
    return b;
  }

  function uebernehmen(ta, stueck, neu) {
    if (stueck.teil) {
      ta.value = ta.value.substring(0, stueck.von) + neu + ta.value.substring(stueck.bis);
    } else {
      ta.value = neu;
    }
    ta.dispatchEvent(new Event('input', {bubbles: true}));
    ta.focus();
  }

  /**
   * Merkt im Formular, dass hier mitgeschrieben wurde. Der Server glaubt dem
   * Feld nicht blind — er hat den Fingerabdruck des gelieferten Textes noch
   * in der Sitzung.
   */
  function merken(ta, d) {
    if (!d.kennzeichnet) return;
    var form = ta.closest('form');
    if (!form) return;
    var feld = form.querySelector('input[name="ki_befehl"]');
    if (!feld) {
      feld = document.createElement('input');
      feld.type = 'hidden';
      feld.name = 'ki_befehl';
      form.appendChild(feld);
    }
    feld.value = d.befehl;
  }

  // ── Start ───────────────────────────────────────────────────────────

  document.addEventListener('click', function (ev) {
    var k = ev.target.closest ? ev.target.closest('.ki-knopf') : null;
    if (!k) return;
    ev.preventDefault();
    menueOeffnen(k);
  });
})();
