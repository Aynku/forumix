# Forumix – Änderungs-Historie

Versionierung nach [SemVer](https://semver.org): `MAJOR.MINOR.PATCH`

- **PATCH** (`0.13.6 → 0.13.7`): Bug-Fixes, kleine Verbesserungen, **keine** Migration nötig
- **MINOR** (`0.13.x → 0.14.0`): Neues Feature, **Migration nötig**
- **MAJOR** (`0.x.x → 1.0.0`): Stabile Veröffentlichung / breaking changes

---

## v1.5.2 (2026-08-21)

### Ein Updatepaket prüft, auf welchen Stand es passt

Ein Updatepaket enthält nur die Dateien, die sich seit der davor
veröffentlichten Fassung geändert haben — bei 1.5.1 waren das acht. Bisher
ließ es sich trotzdem auf jeden beliebigen Stand einspielen: Die acht Dateien
landeten auf einem älteren Forum, alles andere blieb alt, und die
Versionsnummer wurde dennoch hochgesetzt. Ein Forum konnte danach behaupten,
auf 1.5.1 zu sein, während die Hälfte seiner Dateien von 1.2.0 stammte.

- Das Paket nennt jetzt die Fassung, für die es gebaut wurde. Passt sie nicht
  zur laufenden, wird es abgewiesen — bevor eine einzige Datei geschrieben
  ist.
- Die Absage nennt beide Nummern und den Weg: Für einen Sprung über mehrere
  Fassungen ist das **Vollpaket** vorgesehen. Es enthält alle Dateien und ist
  von jedem Stand aus richtig, die Datenbank-Anpassungen holen den Rest.
- Bereits heruntergeladene Pakete bleiben gültig. Wo die Angabe fehlt, ändert
  sich nichts.

### Eingespielte Pakete machten die Fassung zur Beta

Wurde ein Paket über den Adminbereich eingespielt, hängte das Forum an die
Versionsnummer das Wort „Beta" an — aus 1.5.1 wurde „1.5.1 Beta", sichtbar im
Adminbereich und im Fußbereich. Der Zusatz stammte aus der Zeit vor 1.0 und
verschwand von selbst nicht wieder. Er entfällt.

---

## v1.5.1 (2026-08-21)

### Neuinstallationen bekommen wieder das ganze Forum

Eine frische Installation legte nur die Tabellen an, die in `install.sql`
stehen — und diese Datei war seit 1.1.0 nicht mehr nachgezogen worden. Ein
frisch aufgesetztes Forum hatte damit weder Gruppenrechte noch Papierkorb noch
Volltextsuche und wies sich trotzdem als aktuelle Fassung aus. Bemerkbar
machte es sich nicht: alle drei Funktionen haben einen Rückfall, das Forum
lief also, es konnte nur weniger.

- Der Installer fährt jetzt dieselben Datenbank-Anpassungen wie ein Update.
  Beide Wege — Browser und Kommandozeile — nehmen denselben Ordner
  `install/migrations/`.
- `install.sql` bleibt bewusst der ältere Stand. Sie ein zweites Mal von Hand
  zu pflegen war die Ursache; alles Neue kommt jetzt aus einer Quelle.
- Nach dem Aufsetzen steht im Adminbereich unter *Forum-Updates* nichts mehr
  offen.

**Bestehende Foren sind nicht betroffen** — dort wurden die Anpassungen beim
Update gefahren. Betroffen war nur das Fenster zwischen Aufsetzen und erstem
Update.

### Zwei Verweise im Adminbereich zeigten ins Leere

Bei den Einladungen und in der Freigabe-Übersicht führte der Hinweis auf die
Admin-Freigabe in die allgemeinen Einstellungen — die Einstellung steht aber
in einem eigenen Bereich. Ein dritter Verweis, in der Forenverwaltung, zeigte
auf einen Bereich, den es gar nicht gibt.

---

## v1.5.0 (2026-08-21)

### Feinere Rechte je Forum und Gruppe

Ein Forum kannte je Gruppe drei Stufen: nichts, lesen, schreiben. „Schreiben"
war dabei alles auf einmal. Für ein Ankündigungsforum, in dem alle antworten
dürfen, aber nur das Team ein Thema eröffnet, gab es keine Einstellung. Ab
dieser Fassung zerfällt die Stufe in fünf Einzelrechte.

- **Themen eröffnen** und **Antworten** sind getrennt. Damit lässt sich ein
  Forum bauen, in das nur das Team schreibt und in dem trotzdem diskutiert
  wird.
- **Dateien anhängen** ist einzeln abschaltbar — auch dort, wo geschrieben
  werden darf.
- **Umfragen anlegen** ebenso.
- **Eigene Beiträge ändern** lässt sich abschalten, wo Beiträge stehenbleiben
  sollen. Für Moderatoren gilt die Sperre nicht.
- Die Häkchen stehen in der Forenverwaltung rechts neben den bisherigen
  Stufen. Sie gelten nur bei „schreiben"; bei „nichts" und „lesen" sind sie
  wirkungslos und werden blass dargestellt.
- Wer in mehreren Gruppen ist, bekommt die Summe der Rechte — aber nur aus
  den Gruppen, die in diesem Forum überhaupt schreiben dürfen.
- Inhaber, Admins und Moderatoren sind wie bisher ausgenommen.

**Migration nötig** (v30). Alle fünf Rechte stehen danach auf „erlaubt": jede
Gruppe darf genau das, was sie vorher durfte. Solange die Migration nicht
gelaufen ist, verhält sich das Forum wie vor dieser Fassung.

### Anpassungen tragen wieder ihren Namen

Im Adminbereich standen die letzten Anpassungen nur mit ihrer Nummer da
(„v29"). Die Titel für v25.6, v25.7, v28, v29 und v30 sind nachgetragen.

---

## v1.4.0 (2026-08-20)

### Suche über einen Volltext-Index

Die Suche verglich bisher mit `LIKE '%wort%'`. Das kann kein Index bedienen:
Die Datenbank liest bei jeder Suche jeden Beitrag, und eine Reihenfolge nach
Treffergüte gibt es dabei nicht — `LIKE` kennt nur „kommt vor“ und „kommt nicht
vor“. Ab dieser Fassung fragt die Suche einen Volltext-Index.

- **Beste Treffer** ist die neue Vorgabe, sobald ein Suchbegriff eingegeben
  wurde. Der Titel zählt dabei doppelt: wonach ein Thema heißt, ist meist
  näher dran als ein Wort irgendwo im Text. Die übrigen Sortierungen bleiben.
- **Jedes Wort muss vorkommen** — im Titel oder im Beitrag, nicht zwingend
  beides an derselben Stelle. Wer nach zwei Wörtern sucht, meint beide.
- **Wortanfänge zählen:** „Hefe" findet auch „Hefeteig".
- **In Anführungszeichen** wird die Wendung genau so gesucht, in dieser
  Reihenfolge.
- Wörter unter drei Zeichen kennt der Index nicht; die Suche nimmt dafür
  weiterhin den alten Weg.

**Migration nötig** (v29). Sie legt zwei Volltext-Indizes an und liest dafür
die Beiträge einmal ganz durch — bei einem großen Forum dauert das spürbar, und
die Tabellen sind währenddessen gesperrt. **Eine ruhige Minute wählen.**

Solange die Migration nicht gelaufen ist, sucht das Forum wie bisher: ohne
Index, ohne Treffergüte, aber vollständig.

---

## v1.3.2 (2026-08-20)

### Ungelesen-Anzeige stimmt wieder

Seit 1.3.0 galt jedes Thema dauerhaft als ungelesen: der blaue Punkt blieb
stehen, auch nach „Alles gelesen". Ursache war ein Ausdruck, der in der
Abfrage selbst gelandet ist statt davor — die Abfrage scheiterte, und ein
leeres `catch` schluckte den Fehler.

- Die Abfrage ist berichtigt, die Anzeige verhält sich wieder wie vor 1.3.0.
- Der Fehler wird jetzt protokolliert, statt verschluckt zu werden. Ein
  Fehler, den niemand sehen kann, kostet mehr als eine Zeile im Protokoll.
- Eine Prüfung im Repo geht den Quelltext mit dem PHP-Zerteiler durch und
  meldet, wenn ein Abfrage-Helfer in einer Zeichenkette steht statt daneben.

### Kleinigkeiten

- Im Papierkorb steht „1 Beitrag" statt „1 Beiträge".

**Hinweis zu älteren Installationen:** Wurde vor 1.3.0 ein Beitrag gelöscht,
der zufällig der letzte eines Themas war, zeigt die Themenliste bei diesem
Thema dauerhaft keinen letzten Beitrag an — das alte Löschen hat den Verweis
stehen lassen. Ab 1.3.0 passiert das nicht mehr. Vorhandene Fälle lassen sich
einmalig geradeziehen:

```sql
UPDATE threads t
   SET t.last_post_id = (SELECT MAX(p.post_id) FROM posts p
                          WHERE p.thread_id = t.thread_id AND p.deleted_at IS NULL)
 WHERE t.last_post_id IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM posts p2 WHERE p2.post_id = t.last_post_id);
```

Keine Migration nötig.

---

## v1.3.1 (2026-08-20)

### Ohne Migration fällt nichts mehr aus

Wird 1.3.0 eingespielt und die Migration erst später ausgeführt, fragte jede
Seite nach Spalten, die es noch nicht gab — die Installation antwortete in
dieser Zeit durchgehend mit Fehler 500.

- Fehlen die Spalten, verhält sich das Forum wie vor 1.3.0: kein Papierkorb,
  aber es läuft. Sobald die Migration durch ist, ist er da.
- Die Prüfung dafür stellt den Fall nach: Spalten weg, Seitenaufrufe, Spalten
  wieder anlegen.

### Kleinigkeiten

- Werkzeuge auf der Kommandozeile erscheinen nicht mehr unter „Wer ist online".
  Bisher hinterließ jeder Aufruf dort einen Gast mit der Adresse `0.0.0.0`.
- Die Knöpfe unter einem Beitrag stehen immer rechts. Bei Beiträgen ohne
  Absenderadresse rutschten sie nach links, seit es solche Beiträge regelmäßig
  gibt, sah die Fußzeile von Beitrag zu Beitrag anders aus.
- „Ab hier abtrennen" fragt den Titel des neuen Themas nach dem Klick ab,
  statt ein Eingabefeld zwischen die Knöpfe zu setzen.

Keine Migration nötig.

---

## v1.3.0 (2026-08-20)

### Papierkorb statt endgültig löschen

Bisher entfernte jedes Löschen den Beitrag samt Anhängen aus der Datenbank. Ein
Fehlgriff in den Mod-Werkzeugen war damit nicht zu heilen. Gelöschtes bleibt
jetzt liegen, bis es jemand zurückholt oder ausdrücklich endgültig entfernt.

- Für Mitglieder und Gäste ist ein gelöschter Beitrag nicht vorhanden — auch
  nicht für den Verfasser, nicht über die Suche, nicht im RSS-Abruf und nicht
  über die Kennung in der Adresszeile.
- Die Moderation sieht ihn weiterhin an seiner Stelle im Thema, ausgegraut und
  mit dem Vermerk, wer ihn wann gelöscht hat. So bleibt beim Wiederherstellen
  erkennbar, worauf sich die folgenden Antworten beziehen.
- Zähler an Forum, Thema und Verfasser gehen beim Löschen herunter und beim
  Wiederherstellen wieder hoch. Ein Beitrag im Papierkorb zählt nirgends mit.
- Wird ein Thema gelöscht, gehen seine Beiträge mit. Beim Wiederherstellen
  kommt genau das zurück, was mit ihm gegangen ist — was vorher einzeln
  weggelegt wurde, bleibt liegen.
- Endgültig löschen bleibt möglich, aber als zweiter Schritt: erst Papierkorb,
  dann entfernen. Dabei verschwinden auch die Anhänge.
- Der Papierkorb steht unter *Moderator-Bereich → Papierkorb*. Er leert sich
  nicht von selbst.

### Themen teilen und zusammenführen

- **Ab hier abtrennen**: an einem Beitrag lässt sich das Thema teilen. Dieser
  und alle folgenden Beiträge wandern in ein neues Thema im selben Forum. Es
  trägt Verfasser und Datum des ersten abgetrennten Beitrags.
- **Zusammenführen**: alle Beiträge eines Themas wandern in ein anderes, das
  leergeräumte verschwindet. Moderationsrecht wird auf beiden Seiten verlangt.
- Die Zähler beider Themen und Foren werden anschließend neu berechnet, nicht
  hoch- und heruntergezählt.
- Am ersten Beitrag lässt sich nichts abtrennen; ein Thema lässt sich nicht mit
  sich selbst zusammenführen.

**Migration nötig** (v28). Sie ergänzt die Spalten für den Papierkorb. Alles
Bestehende gilt als sichtbar — nach dem Update ist nichts verschwunden, was
vorher da war.

---

## v1.2.4 (2026-08-20)

### Keine erfundene Adresse im IP-Protokoll

Ein Beitrag, der nicht aus einem Browser kommt, hat keine Absenderadresse.
`client_ip()` liefert in diesem Fall die Ersatzadresse `0.0.0.0` — und die
stand anschließend im Protokoll, als wäre jemand von dort gekommen.

- Ohne gültige Adresse wird kein Protokolleintrag mehr geschrieben.
- Betrifft Beiträge, die über die Kommandozeile entstehen; am Beitrag selbst
  stand ohnehin keine Adresse.
- Vorhandene Einträge bleiben liegen. Wer sie entfernen will:

```sql
DELETE FROM user_ip_log WHERE ip = '0.0.0.0';
```

Keine Migration nötig.

---

## v1.2.3 (2026-08-20)

### Gelöschte Konten hinterlassen keine Spuren mehr

Beim Löschen wird ein Konto anonymisiert; die Zeile selbst bleibt als Anker
unter den Beiträgen bestehen, die stehen bleiben. Sichtbar war davon bisher
mehr als nötig.

- Das Anmelde-Protokoll wird jetzt mitgelöscht. Bisher blieben die
  IP-Adressen stehen — der Name war ersetzt, die Person darüber weiterhin
  erkennbar. Bei einer abgelehnten Registrierung geschah das längst.
- Im Adminbereich stehen gelöschte Konten nicht mehr in der Benutzerliste.
  Das Einblenden auf Wunsch entfällt; an einem anonymisierten Konto ist nichts
  zu verwalten.
- Das Dashboard zählt sie nicht mehr als Mitglieder und führt sie weder unter
  „Neueste Mitglieder“ noch unter „Letzte Logins“.
- Die Kurve der Registrierungen der letzten 30 Tage lässt sie ebenfalls weg,
  im Dashboard wie in der Statistik.

Protokollzeilen aus früheren Löschungen bleiben liegen; das Update räumt keine
vorhandenen Daten ab. Wer sie entfernen will:

```sql
DELETE l FROM user_ip_log l JOIN users u ON u.user_id = l.user_id
WHERE u.is_deleted = 1;
```

Keine Migration nötig.

---

## v1.2.2 (2026-08-20)

### Auszeichnung für wortwörtlichen Text im Editor

Das Tag steht seit 1.2.0 zur Verfügung, war über die Oberfläche aber nicht
erreichbar. Es hat jetzt einen eigenen Knopf.

- In der Werkzeugleiste neben dem Code-Block, beschriftet mit `‹›`.
- Wirkt auf die Auswahl, wie die übrigen Auszeichnungen.
- Die Vorschau im Editor stellt das Ergebnis bereits dar.

### Abstände um Blöcke

Eine Leerzeile zwischen zwei Absätzen erzeugt einen Zeilenumbruch. Grenzt sie
an einen Code-Block, ein Zitat, eine Linie oder eine Liste, kommt dieser
Umbruch zum Abstand des Blocks hinzu — sichtbar als zwei überzählige Leerzeilen
um jeden Kasten.

- Umbrüche unmittelbar an einem Block werden nicht mehr gesetzt.
- Innerhalb eines Absatzes bleibt die Darstellung unverändert.
- Wortwörtlicher Text im Fließtext ist ausgenommen; Zeilenumbrüche daneben
  bleiben erhalten.
- Betrifft auch vorhandene Beiträge: die Darstellung entsteht bei jedem Aufruf
  neu, der gespeicherte Text bleibt unberührt.

Keine Migration nötig.

---

## v1.2.1 (2026-08-20)

### Gruppenabgleich wieder aus dem Paket genommen

Mit 1.2.0 kamen ein Werkzeug zum Abgleich einer Benutzergruppe mit einer
Adressliste sowie die Migration v27 hinzu. Beides gehört zum Betrieb von
forumix.de und nicht in die ausgelieferte Software: einer Installation ohne
eine solche Liste bleiben davon eine Datei ohne Funktion und eine Spalte ohne
Inhalt. Das Update entfernt beides.

- `bin/gruppenabgleich.php` wird beim Update entfernt.
- Die Migration v27 entfällt. Wurde sie bereits ausgeführt, bleibt die Spalte
  `group_members.woher` bestehen. Sie wird von keiner Funktion mehr gelesen und
  kann entfernt werden.
- Die Nummer v27 wird nicht erneut vergeben; die nächste Migration ist v28.
- Gruppen, Rechte und die Gruppenverwaltung im Adminbereich sind unverändert.

### Aus 1.2.0 unverändert übernommen: wortwörtlicher Text im Fließtext

Neben dem Code-Block steht eine Auszeichnung für einzelne Wörter zur Verfügung
— einen Dateinamen, einen Schalter, ein Tag. Der Inhalt erscheint in
Schreibmaschinenschrift und wird nicht als BBCode ausgewertet; damit lässt sich
über BBCode schreiben, ohne dass er wirkt.

```
Der Schalter [c]--probe[/c] rechnet nur vor.
```

Keine Migration nötig.

---

## v1.2.0 (2026-08-20)

**Eine Gruppe lässt sich aus einer Adressliste pflegen.** Wenn woanders schon
steht, wer dazugehört — eine Mitgliederverwaltung, eine Kundendatei, ein
anderes System —, muss die Gruppe im Forum nicht zusätzlich von Hand geführt
werden. `bin/gruppenabgleich.php` nimmt eine Liste von Adressen entgegen und
bringt die Gruppe damit in Deckung, in beide Richtungen. Gedacht für cron.

- Übernommen werden nur **bestätigte** Adressen. Ohne diese Grenze genügt es,
  sich mit der Adresse eines Mitglieds anzumelden, um in dessen Gruppe zu
  landen — und damit womöglich in ein Forum, das nur diese Gruppe sieht. Ob die
  Anmeldung eine Bestätigung verlangt, hängt an einem Schalter im Adminbereich;
  hier hängt es an nichts. Gesperrte und gelöschte Konten bleiben ebenfalls
  draußen.
- Ausgetragen wird nur, was ein früherer Abgleich selbst eingetragen hat. Wen
  ein Administrator von Hand in die Gruppe gesetzt hat, fasst der Abgleich
  nicht an — auch dann nicht, wenn die Adresse auf der Liste steht.
- Im Adminbereich steht bei diesen Mitgliedschaften „aus Liste“. Wer eine davon
  entfernt, wird darauf hingewiesen, dass sie beim nächsten Abgleich wieder da
  ist.
- `--probe` rechnet vor, ohne zu schreiben. Eine leere Liste an der
  Standardeingabe räumt die Gruppe nicht leer, sondern bricht ab: eine Quelle,
  die gerade nichts liefert, ist kein Grund, eine Gruppe zu räumen.

**Wortwörtliches mitten im Satz.** Neben dem Code-Block gibt es jetzt ein
Inline-Tag für einzelne Wörter — einen Dateinamen, einen Schalter, ein Tag.
Es setzt sie in Schreibmaschinenschrift und nimmt sie aus der Auszeichnung
heraus: was dazwischen steht, wird nicht mehr als BBCode gelesen. Damit lässt
sich endlich über BBCode schreiben, ohne dass es wirkt.

```
Der Schalter [c]--probe[/c] rechnet nur vor.
```

**Migration nötig** (v27). Sie ergänzt in `group_members` eine Spalte für die
Herkunft einer Mitgliedschaft. Alles Bestehende gilt als von Hand eingetragen
und ist damit vor jedem Abgleich sicher. Ohne die Migration verhält sich das
Forum wie bisher.

---

## v1.1.5 (2026-08-20)

**Codezeilen lassen sich einzeln verlinken.** "Schau dir Zeile 14 an" wird zum
Klick: jede Zeile eines Code-Blocks hat eine Sprungmarke, die Nummer davor ist
der Verweis darauf, und die angesprungene Zeile hebt sich ab. Die Marke setzt
sich aus Beitrag und Blocknummer zusammen (`p12c1-3`) und bleibt damit gueltig,
wenn der Beitrag auf eine andere Seite des Themas rutscht. Die Nummer selbst
wandert beim Kopieren weiterhin nicht mit.

**Sprungziele landen nicht mehr hinter der Kopfzeile.** Die laeuft mit, und
damit verschwand jedes Ziel dahinter — das galt auch fuer die
"zum Beitrag"-Verweise aus Zitaten, nur fiel es dort weniger auf. Die Hoehe
der Kopfzeile wird jetzt gemessen (61 px am Rechner, 175 px am Handy, weil sie
sich dort stapelt) und als Sprungreserve gesetzt.

Keine Migration noetig.

---

## v1.1.4 (2026-08-19)

**Geloeschte Konten treten nicht mehr als Mitglieder auf.** Ein anonymisiertes
Konto als "Neuestes Mitglied" zu fuehren ist eine Auskunft ueber niemanden.
Startseite und Fusszeile zaehlen geloeschte Konten nicht mehr mit, und als
neuestes Mitglied werden ausserdem gesperrte uebersprungen. Die Beitraege
bleiben unveraendert stehen — sonst bekaemen Gespraeche Loecher.

Im Adminbereich stehen geloeschte Konten nicht mehr in der Benutzerliste. Ein
Hinweis darueber nennt ihre Zahl und blendet sie auf Klick wieder ein, falls
jemand einer alten Kennung nachgehen will.

Die laufende Nummer hinter "Geloeschter Benutzer" ist jetzt auch in
Mitgliederliste, Wer-ist-online, Dashboard, Auszeichnungen und auf den
Team-Zeilen verschwunden — in 1.1.3 war sie nur an drei Stellen weg.

Und "1 Mitglieder" heisst jetzt "1 Mitglied".

Keine Migration noetig.

---

## v1.1.3 (2026-08-19)

**Ein geloeschtes Konto verlaesst jetzt seine Gruppen.** Bisher blieb die
Mitgliedschaft stehen: in der Mitgliederliste einer Gruppe stand dann
"Geloeschter Benutzer #2", die Zahl daneben zaehlte ihn mit — und weil Rechte
an der Kennung haengen, haette ein Nachfolger sie geerbt. Die Kontoloeschung
raeumt `group_members` jetzt mit ab, so wie sie es mit Forum-Moderator-,
Abo- und Lesezeichen-Eintraegen schon immer getan hat.

Ergaenzend halten Mitgliederliste und Zaehlung geloeschte Konten heraus — fuer
Zeilen, die aus der Zeit davor stammen.

**"Geloeschter Benutzer" ohne laufende Nummer.** Die Loeschung haengt eine
Nummer an, weil der Benutzername eindeutig sein muss. Zu sehen bekam man sie
bisher auch — obwohl der Text vor dem Loeschen ausdruecklich "Geloeschter
Benutzer" verspricht. Beitrag, Profil und Startseite zeigen die Nummer nicht
mehr. Nachahmen laesst sich der Name nicht: `#` ist in Benutzernamen nicht
erlaubt, und "geloeschter benutzer" ist bei der Anmeldung reserviert.

Keine Migration noetig.

---

## v1.1.2 (2026-08-19)

**Kein Knopf, wo er nur zur Absage fuehrt.** "+ Neues Thema" stand in jedem
Forum, auch in denen, in denen man nur lesen darf — Klick, Absage, zurueck.
Dasselbe beim Antwortfeld in der Themenansicht.

Beide erscheinen jetzt nur mit Schreibrecht. Statt des Antwortfeldes steht eine
Zeile, die sagt, woran es liegt: mitlesen ja, schreiben nein. Die Pruefung beim
Abschicken bleibt unveraendert — wer das Formular von Hand nachbaut, kommt
weiterhin nicht durch.

Gefunden beim Durchprobieren mit einem gewoehnlichen Konto auf einer laufenden
Instanz: die Sperre griff, aber der Weg dorthin sah aus wie eine Einladung.

Keine Migration noetig.

---

## v1.1.1 (2026-08-19)

**Zwei Luecken beim Verstecken geschlossen.** Sie fielen auf, als das erste
Forum wirklich auf eine Gruppe eingeschraenkt wurde: der direkte Aufruf gab
schon 404, auf der Startseite stand es aber weiter.

- **Die Startseite** benutzte den zentralen Filter nicht. Sie listete alle
  Foren einer Kategorie und maskierte bei passwortgeschuetzten nur den Titel
  des letzten Beitrags — bei einem Passwortforum ist das Absicht, bei einem
  Gruppenbrett falsch. Eine Kategorie, in der nichts mehr sichtbar ist, wird
  jetzt ausserdem gar nicht mehr gezeichnet: die Ueberschrift allein verriete
  sonst genau das, was das Verstecken verhindern soll.
- **Abo-Mails** hielten den Beitragstext nur bei passwortgeschuetzten Foren
  zurueck. Ein Abo ueberlebt aber auch, dass jemand aus einer Gruppe genommen
  wird — dann trug die Mail den Inhalt weiter ins Postfach. Dafuer gibt es
  jetzt `board_level_for_user()`: dieselbe Rechnung fuer einen bestimmten
  Benutzer statt fuer den angemeldeten Besucher.

Keine Migration noetig.

---

## v1.1.0 (2026-08-19)

**Gruppen und Rechte pro Forum.** Bis hierher gab es drei Stufen — Inhaber,
Moderator, alle anderen — und den Passwortschutz. Wer ein Forum nur einem Teil
der Mitglieder zeigen wollte, hatte nichts Passendes: ein Passwort kann jeder
weitergeben, und es verraet, dass es das Forum gibt.

Jetzt gibt es Gruppen, und je Forum und Gruppe eine Stufe: **nichts, lesen,
schreiben**. Wer in mehreren Gruppen ist, bekommt die hoechste davon. Inhaber,
Admins und Moderatoren sind wie bisher ausgenommen.

- Zwei eingebaute Gruppen, **Gäste** und **Angemeldete**: jeder Besucher faellt
  in genau eine davon. In sie wird niemand eingetragen, das ergibt sich.
- Pro Forum waehlbar: **verstecken** oder **sichtbar mit Schloss**. Verstecken
  heisst wirklich weg — nicht auf der Startseite, nicht in der Suche, nicht im
  RSS, nicht ueber Schlagworte, nicht im Profil eines Mitglieds. Direkter
  Aufruf ergibt dieselbe 404-Seite wie ein Forum, das es nicht gibt.
- Der Grund fuer die Abweisung ist unterscheidbar: ein **Passwort** kann der
  Besucher selbst eingeben, eine fehlende **Gruppe** nicht. Deshalb fuehrt der
  eine Weg zur Entsperrseite und der andere auf 404 — auch von der
  Entsperrseite selbst aus, die sonst allein durch ihr Dasein Auskunft gaebe.
- Gruppen koennen als **Abzeichen** unter dem Rang erscheinen. Ohne Haken
  bleiben sie unsichtbar, was fuer eine interne Gruppe der Sinn der Sache ist.
- Verwaltet wird das unter **Benutzer → Gruppen** und im Forum-Formular.

**Migration noetig** (`v26`). Sie traegt fuer jedes vorhandene Forum
ausdruecklich ein, was heute gilt: Gäste lesen, Angemeldete schreiben. Nach dem
Update verschwindet also nichts, was vorher offen war.

**Code-Bloecke und Zitate neu gebaut.** Der Code-Block hatte weder Kopfzeile
noch Sprachangabe noch Kopieren-Knopf. Jetzt mit alldem, dazu Zeilennummern und
ein Deckel bei 460 Pixel Hoehe.

- **Syntax-Faerbung** ueber CodeMirror, das fuer den Vorlagen-Editor ohnehin
  beiliegt — kein zweites Paket. Geladen wird nur `runmode-standalone` plus die
  gebrauchten Modi, und auch das nur auf Seiten mit einem Code-Block. Die
  Farben kommen aus den vorhandenen Tokens, damit es im hellen Thema genauso
  stimmt wie im dunklen. Sprachangabe per `[code=php]`; ohne Angabe wird nicht
  geraten.
- Behoben: die BBCode-Kette lief auch durch den Code. `[b]` machte fett,
  `@name` wurde zum Verweis, `nl2br()` haengte an jede Zeile ein `<br>`, und
  aus `:)` im Quelltext wurde ein Smiley.
- Behoben: verschachtelte Zitate nahmen den falschen Schluss-Tag — aus
  `[quote=Name;post=41]` wurde die Zeile "Name;post=41 schrieb".

**Themen verschieben** (aus 1.0.1) und ein Fund aus dem laufenden Betrieb:
Beitraege liessen sich nicht loeschen, wenn ein Zaehler auf 0 stand.
`GREATEST(0, spalte - 1)` schuetzt bei einer UNSIGNED-Spalte nicht — die
Subtraktion laeuft ueber, bevor GREATEST drankommt.

---

## v1.0.1 (2026-08-19)

**Themen lassen sich verschieben.** Ein Thema landet nicht immer im richtigen
Forum, und bisher gab es keinen Weg, das zu korrigieren — ausser von Hand in
der Datenbank.

Die Mod-Tools der Themenansicht zeigen jetzt eine Auswahl der uebrigen Foren
und einen Knopf. Passwortgeschuetzte Foren ohne Zugang stehen nicht darin.

- Moderatorenrecht wird auf **beiden** Seiten verlangt. Sonst waere das
  Verschieben ein Weg, ein Thema in ein fremdes Forum zu heben, in dem man
  selbst nichts zu sagen hat.
- Die Zaehler beider Foren werden nachgerechnet statt hoch- und
  runtergezaehlt: Letzteres schreibt jede vorhandene Abweichung dauerhaft
  fest. `board_zaehler_neu()` liegt dafuer in `lib/board_mods.php` und ist
  damit auch pruefbar.
- Der Vorgang steht im Moderationsprotokoll.

Keine Migration noetig, die Datenbankstruktur bleibt unveraendert.

## v1.0.0 (2026-08-17)

**Die erste stabile Freigabe.** Ab hier ist die Versionsnummer ein Versprechen:
Datenbankstruktur, Modul-Schnittstelle und Vorlagenformat liegen fest. Was
daran bricht, heisst 2.0.

Der Funktionsumfang stand schon vorher — 26.000 Zeilen PHP, 47 Tabellen,
Anmeldung mit Zwei-Faktor, Moderation, Sicherungen, Migrationen,
Updatepakete, Themen-Editor, Modulsystem. Was fehlte, war der Nachweis, dass
man sich darauf verlassen kann. Deshalb ging der 1.0 ein Audit voraus.

### Audit

Neun Pruefrichtungen ueber den gesamten Quelltext, 39 gemeldete Funde, jeder
davon anschliessend von drei unabhaengigen Pruefern mit dem Auftrag
angegriffen, ihn zu WIDERLEGEN. 32 haben das ueberstanden und sind behoben,
7 sind gefallen. Der vollstaendige Bericht mit Hergang und Belegstellen liegt
beim Betreiber.

**Schwer:**

- **Sitzungsfixierung beim Angemeldet-bleiben.** Von drei Wegen in eine
  angemeldete Sitzung wechselte einer die Sitzungskennung nicht — ausgerechnet
  der, den jeder Seitenaufruf geht. Eine untergeschobene Sitzung blieb nach der
  automatischen Anmeldung gueltig. Alle drei Wege verwerfen jetzt zusaetzlich
  den CSRF-Token, der ja schon vor der Anmeldung erzeugt wird.
- **Ein Zwei-Faktor-Code galt mehrfach.** Bis zu 90 Sekunden lang war derselbe
  Code beliebig oft einloesbar. Da die Passwort-Ruecksetzung per 2FA allein
  gegen Benutzername und Code herausgegeben wird, reichte EIN abgefangener
  Code fuer Ruecksetzung, Anmeldung und Zwei-Faktor-Schritt — eine
  vollstaendige Kontouebernahme. Codes werden jetzt verbraucht, und eine
  Ruecksetzung ohne Postfachzugriff meldet sich beim Kontoinhaber.
- **Kopfzeilen-Einschleusung ueber den Absendernamen.** Ein Zeilenumbruch im
  Feld haengte eine eigene Kopfzeile an jede ausgehende Mail (Bcc) oder ein
  zweites RCPT TO in den SMTP-Dialog.
- **Die Bremse zaehlte nach der Pruefung.** Zwischen Lesen und Erhoehen kamen
  parallele Anfragen alle durch — jede Begrenzung liess sich umgehen, indem
  man die Versuche gleichzeitig statt nacheinander schickte.
- **Eine erfolgreiche Anmeldung loeschte die Bremse fuer alle Konten derselben
  Adresse.** Mit einem eigenen gueltigen Konto liess sich der Zaehler fuer das
  Durchprobieren aller anderen abraeumen.

**Mittel:** Wartungsmodus ueber die Adresse umgehbar; RSS lieferte Beitraege
aus der Freigabe-Warteschlange und liess sich mit einem `]]>` im Text ganz
zerlegen; Anhaenge ueberlebten das Loeschen ihres Beitrags und blieben
abrufbar; im Paket fehlte der Apache-Schutz fuer Vorlagen- und Cache-Ordner;
Benachrichtigungsmails trugen Beitragstexte auch an Abonnenten ohne Zugang
zum geschuetzten Forum.

**Niedrig:** Reaktionen ohne Zugangspruefung; unfreigegebene Themen ueber ihre
ID, in der Schnellsuche und in Schlagwortlisten sichtbar; Abmelden und
Sprachwahl per Aufruf statt per Formular; SVG als Rollen-Avatar;
Vorlagenname aus einem Parameter im Dateipfad; der E-Mail-Wechsel verriet,
welche Adressen belegt sind; Zwei-Faktor-Geheimnisse im Klartext.

### Weiter behoben

- **Alle Beitraege trugen die Nummer #1.** Die Vorlagen-Engine hielt `loop` in
  einer einzigen Variablen; eine innere Schleife ueberschrieb die aeussere.
  Betraf jede verschachtelte Schleife in jeder Vorlage.
- **Die Werkseinstellung des Themes stand doppelt** und waere auseinander
  gelaufen: „Design zuruecksetzen" haette bald eine andere Palette hergestellt
  als eine Neuinstallation.

### Aufgeraeumt

- **Die alten PHP-Ansichten sind weg.** Alle 35 lagen doppelt vor und wurden
  nie gerendert, mussten aber bei jeder Aenderung mitgepflegt werden.
- **Neu: `tools/pruefen.php`** — ein Prueflauf ohne Fremdpakete mit 36
  Nachweisen. Jeder stellt einen Fund aus dem Audit nach.

### Datenbank

Zwei Migrationen, beide ueber *Wartung → Updates* oder
`php install/migrate.php`:

- `migrate_v25_6` legt `user_2fa.last_window` an (Verbrauchsvermerk fuer
  Codes). **Ohne sie weist die Zwei-Faktor-Anmeldung jeden Code ab.**
- `migrate_v25_7` verbreitert `user_2fa.secret` auf VARCHAR(255) und
  verschluesselt vorhandene Geheimnisse, sofern ein `FORUM_CRYPT_KEY` in der
  config.php steht. Ohne Schluessel bleibt alles im Klartext und
  funktioniert unveraendert.

### Aussehen

Die Oberflaeche ist ueberarbeitet: eine Zeile je Forum statt drei, Zahlen
rechtsbuendig in gleicher Ziffernbreite, Monogramme statt eines Symbols vor
jedem Eintrag, matte Flaechen ohne Verlauf und Schatten, rund 380 Bildzeichen
aus Beschriftungen entfernt. Neu ist der Kopfstil **Eine Zeile** und die
Angabe `ort` im Modul-Manifest, damit ein Menuepunkt nicht gleichzeitig oben
und unten steht.

---

## v0.25.5 (2026-08-17)

### Aufgeraeumt: Oberflaeche im ganzen Forum ueberarbeitet

Die Forenuebersicht traegt eine Zeile je Forum statt drei. Themen und Beitraege
stehen rechtsbuendig in EINER Spalte mit gleicher Ziffernbreite, der letzte
Beitrag bekommt eine eigene Betreffzeile statt drei gleich starker Zeilen, und
die Moderatorenspalte erscheint nur, wenn in der Kategorie ueberhaupt jemand
Moderator ist. Die Kopfzeile der Tabelle faellt weg — „12 Themen" sagt selbst,
was es ist. Vor jedem Forum steht ein Monogramm aus dem Namen statt eines
Klemmbretts, das ueberall gleich aussah.

Die Beitragskarte ist ein Raster: Zeit und Nummer stehen jetzt in der
Textspalte, nicht als grauer Balken ueber der ganzen Karte, die Aktionen
darunter als Pillen. Fuer Gaeste faellt die leere Aktionsleiste weg.

Flaechen sind matt — kein Verlauf hinter dem Logo, keine Schatten unter Karten.
Die Grautoene sind leicht ins Blaue gezogen; ein neutrales Grau wirkt neben dem
Indigo stumpf. Der Akzent wird im dunklen Erscheinungsbild aus dem eingestellten
Wert aufgehellt, statt als zweite Einstellung gepflegt zu werden.

Aus Beschriftungen sind rund 380 Bildzeichen verschwunden — Zitieren, Melden,
Bearbeiten, Anpinnen, Menuepunkte, Ueberschriften. Geblieben sind die, die
allein etwas bedeuten: Themenstatus, Rollenfarben.

### Neu: Kopfstil „Eine Zeile"

Logo, Menue und Bedienung nebeneinander auf einer Hoehe. Der Schriftzug ersetzt
darin das Bildlogo, die Suche wandert in die Navigation. Zur Auswahl in der
Verwaltung neben Standard, Zentriert und Kompakt; unter 900 Pixel Breite faellt
der Kopf in den gewohnten Aufbau zurueck.

Neue Foren starten mit diesem Kopf und der Systemschrift. Bestehende behalten,
was eingestellt ist.

### Module: `ort` im Manifest

Ein Menuepunkt stand bisher gleichzeitig in der Kopfleiste UND in der
Fusszeile. Ein Modul kann jetzt `'ort' => 'menue' | 'fuss' | 'beides'` angeben.
Ohne Angabe bleibt es bei `beides`. Der Kontaktpunkt steht damit dort, wo man
ihn sucht: neben dem Impressum.

### Behoben

- **Alle Beitraege trugen die Nummer #1.** Die Vorlagen-Engine hielt `loop` in
  einer einzigen Variablen; eine innere Schleife legte sie neu an, und danach
  zaehlte die aeussere auf der inneren weiter. Bei leerer innerer Schleife blieb
  der Zaehler stehen. Betraf jede verschachtelte Schleife in jeder Vorlage.
- **Die Werkseinstellung des Themes stand doppelt** — einmal in `bootstrap.php`,
  einmal im Zuruecksetzen der Verwaltung. „Design zuruecksetzen" haette bald
  eine andere Palette hergestellt als eine Neuinstallation. Jetzt eine Quelle:
  `theme_defaults()`.

---

## v0.25.4 (2026-08-16)

### 📝 Wortwahl auf betreuten Installationen

0.25.3 hatte „Betreiber" durch „Anbieter" ersetzt und den Text dabei
verschwurbelt („von der Stelle, die es dir eingerichtet hat"). Der Grund war ein
Denkfehler: „Betreiber" kollidiert im HOSTING-DIENST, wo die Kundentabelle so
heisst — im Forum selbst heisst niemand so, dort gibt es Inhaber, Admins und
Moderatoren.

Jetzt steht ueberall **Betreiber des Dienstes**, und die Abweisungen sagen
aktiv „Diese Installation aktualisiert der Betreiber des Dienstes" statt
„wird aktuell gehalten".

Auch die Wegbeschreibungen zeigen jetzt auf **Wartung → Updates** — der Punkt
ist in 0.25.1 dorthin gewandert, README und Projektseite schickten Leute
weiter an die alte Stelle.

---

## v0.25.3 (2026-08-16)

### 📝 „Betreiber" hiess zwei verschiedene Dinge

Auf betreuten Installationen stand ueber der Update-Seite *„Updates macht der
Betreiber"*. Wer das liest, ist selbst Betreiber seines Forums — die
Ueberschrift sagte ihm also „das machst du", und der Satz darunter das
Gegenteil. Dazu „haelt die Installation aktuell" und „werden dort eingespielt":
zweimal dasselbe, und „dort" zeigte auf nichts.

Jetzt heisst es **Anbieter**, an allen vier Stellen — Ueberschrift, Text, die
beiden abweisenden Meldungen und der Hinweis am festgelegten Mail-Absender.
Das Wort kollidiert mit nichts und sagt, wer gemeint ist.

---

## v0.25.2 (2026-08-16)

### 🛠️ Betreute Installationen: auch die Hinweise passen jetzt

Unter dem Satz „Updates macht der Betreiber" stand weiter der Kasten *Wichtige
Hinweise* — „vor jedem Update ein Backup anlegen", „nur der Owner kann Pakete
einspielen", „bei Fehlern das Backup zurückspielen". Rat fuer eine Handlung,
die es dort gar nicht gibt, zwei Zeilen unter der Ansage, dass sich jemand
anderes kuemmert.

Der Kasten erscheint nur noch, wo man selbst einspielt.

### 🎨 Frische Installationen kommen indigo hoch, nicht violett

`bootstrap.php` nennt `4f46e5` den Standard („Default-Indigo"), `install.sql`
schrieb `7c3aed`. Der Rueckfall im Code wird nie erreicht — der Installer
schreibt die Einstellung immer — also gewann seit jeher der Installer, und
jedes neue Forum war violett. Der Wert stand seit der ersten Bestandsaufnahme
so drin; das war keine Entscheidung.

**Betrifft nur neue Installationen.** Bestehende behalten ihre Farbe.

### 📁 `install/migrations/README.md`

Die Regel fuers Aufraeumen steht jetzt dort, wo jemand steht, wenn er eine
Migration anlegt oder wegwirft: `install.sql` ist der Schnitt, und Wegwerfen
ist eine Zusage („Aufstieg ab X"), keine Aufraeumarbeit. Nachtraeglich pruefen
laesst es sich nicht — `bootstrap.php` zieht `forum_version` beim ersten Aufruf
hoch und ueberschreibt damit die Herkunft.

---

## v0.25.1 (2026-08-16)

### 🛠️ Backups und Updates stehen jetzt unter Wartung

Die Trennung war unscharf: unter *System* standen vier Einstellungsseiten und
zwei Handlungsseiten. Die Regel, die traegt — **System ist, wie die
Installation eingestellt ist; Wartung ist, was man an ihr tut.**

Backups geht mit. Nur *Updates* zu verschieben haette das Paar
auseinandergerissen, und es ist ein Handgriff: Sicherung anlegen, Update
einspielen, notfalls Wartungsmodus.

### 🧹 `migrate_v18_56.php` entfernt

Sie verbreiterte `users.pending_email_token` auf `VARCHAR(255)`. Schon das
aelteste je veroeffentlichte Paket — 0.18.58 — legt die Spalte so an; eine
Installation, der sie fehlen koennte, muesste aelter als 0.18.56 sein, und so
eine wurde nie ausgeliefert.

**`install/migrations/` bleibt** und traegt jetzt eine README mit der Regel
fuer kuenftige Freigaben. Der Ordner ist die einzige Quelle fuer
`available_migrations()`; fehlt er, kommt eine leere Liste zurueck — ohne
Fehler und ohne Warnung.

---

## v0.25.0 (2026-08-16)

### 🛠️ Betreute Installationen: `FORUM_UPDATE_EXTERN`

Steht `define('FORUM_UPDATE_EXTERN', true)` in der `config.php`, laeuft der
Update-Weg im Adminbereich still: keine Pakete einspielen, keine Migrationen
ausloesen. **Ohne die Konstante aendert sich nichts.**

Gedacht fuers Hosting. Wer ein Forum gestellt bekommt, bekommt auch die
Aktualisierung gestellt — und ein Paket-Upload ist per Bauart Codeausfuehrung
auf fremdem Webspace, den sich der Betreiber mit anderen teilt.

Der Riegel sitzt in beiden Handlern, nicht nur in der Ansicht: eine
ausgeblendete Schaltflaeche ist keine Sicherung. Die Seite selbst bleibt und
zeigt Version und Verlauf weiter; statt der Schaltflaechen steht dort, wer sich
kuemmert. Eine Seite, die fehlt, liest sich wie ein Fehler.

### 🔒 Migrationen nur noch vom Owner

Vorher durfte sie jeder Admin ausloesen. Eine Migration aendert das Schema
unter laufendem Betrieb — das ist Sache des Inhabers, nicht jedes Admins, den
er ernannt hat. **Wer bisher als Admin migriert hat, kann das nicht mehr**; der
Owner kann es weiterhin.

### ⌨️ `install/migrate.php` — Migrationen ohne Browser

    php install/migrate.php --status          zeigt, was aussteht
    php install/migrate.php                   faehrt alle ausstehenden
    php install/migrate.php --version=v18_56  faehrt genau eine
    php install/migrate.php --json            Ausgabe fuer Maschinen

Bisher gab es die Anpassungen nur klickweise im Adminbereich. Fuer eine
betreute Installation ist das kein Weg — dort faehrt sie der Betreiber von
aussen mit.

Die Datei bleibt beim Aufraeumen liegen, wie `install/migrations/` auch, und
macht deshalb ueber HTTP hart dicht (`PHP_SAPI !== 'cli'` → 403). Sie verlaesst
sich nicht darauf, dass eine Konstante fehlt.

Bricht eine Migration ab, laeuft der Rest NICHT weiter: die naechste Anpassung
baut in aller Regel auf der vorigen auf, und wer durchlaeuft, macht aus einem
Fehler zwei.

### 📦 Fuer Paketbauer

Ab hier gilt: **jede Datenbankaenderung bekommt eine Migrationsdatei, und die
bleibt im Paket** — mindestens zurueck bis zur aeltesten Version, die noch
irgendwo laeuft. Nicht ueber `update.sql`; das steckt nur im Updatepaket, und
betreute Installationen bekommen das Vollpaket. Fehlt die Datei, liefert
`available_migrations()` eine leere Liste: kein Fehler, keine Warnung, neuer
Code auf alter Tabelle.

---

## v0.24.0 (2026-08-15)

### 📧 Stundengrenze fuer den Mailversand

Steht `FORUM_MAIL_MAX_PER_HOUR` in der `config.php`, verschickt die
Installation hoechstens so viele Mails je Stunde. **Ohne die Konstante aendert
sich nichts** — kein Installer schreibt sie von selbst.

Gedacht fuer Installationen, die jemand anderes betreibt: der Versand laeuft
ueber dessen Server und dessen Ruf. Ein Forum, das ploetzlich tausend
Einladungen verschickt, kostet nicht sich selbst die Zustellbarkeit, sondern
alle anderen auf derselben Maschine.

Gezaehlt wird fuer die ganze Installation, nicht je Empfaenger — sonst waere
die Grenze wirkungslos, man schickt ja an viele verschiedene. Benutzt wird der
Zaehler, den es fuer Anmeldung und Registrierung ohnehin gibt
(`lib/ratelimit.php`), kein zweiter daneben.

Eine abgewiesene Mail landet im Fehlerprotokoll. Stumm verschlucken waere das
Schlimmste: der Betreiber wundert sich sonst, warum die Bestaetigung ausbleibt,
und niemand sucht danach.

**Keine Migration noetig.**

---

## v0.23.0 (2026-08-15)

### 🖥️ Installer laeuft jetzt auch ohne Browser

`php install/cli.php --config=setup.json` setzt ein Forum ohne Formular auf.
Wer SSH hat, spart sich vier Seiten; fuer das kommende Hosting war es
Voraussetzung — dort klickt niemand von Hand.

Die Angaben kommen als **JSON, nicht als Argumente**. Argumente stehen in
`ps` und waeren fuer jeden anderen Benutzer der Maschine lesbar, samt
Datenbank- und Inhaber-Passwort. Ueber eine Datei (0600) oder die
Standardeingabe sind sie es nicht.

Der Kern liegt neu in `install/lib.php`: Voraussetzungen, Schema,
Inhaber-Konto, `config.php`. Browser-Installer und Kommandozeile gehen
durch **denselben** Code. Stuende die Arbeit zweimal nebeneinander, wuerde
eine neue Tabelle irgendwann nur einen der beiden Wege erreichen — und das
faellt erst auf, wenn ein fertig aufgesetztes Forum beim ersten Klick auf die
Nase faellt.

Zwei Warntexte des Browser-Installers sind hier echte Abbrueche: bereits
installiert, und die Datenbank enthaelt schon Forumix-Tabellen (`install.sql`
beginnt mit `DROP TABLE`).

`"cleanup": true` raeumt den Installer nach Erfolg selbst weg.

### 📧 Absender festnageln (fuer Installationen, die jemand anderes betreibt)

Stehen `FORUM_MAIL_FROM` bzw. `FORUM_MAIL_FROM_NAME` in der `config.php`,
gewinnen sie ueber die Einstellung im Adminbereich, und das Feld ist dort
gesperrt. **Ohne die Konstanten aendert sich nichts** — kein Installer
schreibt sie von selbst.

Eine gesperrte Anzeige ist keine Sicherung: das Formular laesst sich auch
ohne Browser abschicken. Der Wert faellt deshalb zusaetzlich im
Speichern-Zweig heraus, bevor irgendetwas gespeichert wird.

Gedacht fuer Faelle, in denen der Versand ueber fremde Reputation laeuft.
Nuetzlich auch, wenn man sein Forum jemandem zur Verwaltung gibt: Absender
raus aus dem Adminbereich, ohne ihm Adminrechte zu beschneiden.

### 🐛 „Ordner install/ loeschen" hat die Migrationen kaputtgemacht

Der Adminbereich holt die Datenbank-Anpassungen spaeterer Updates aus
`install/migrations/`. Fehlt der Ordner, liefert `available_migrations()`
eine leere Liste — **kein Fehler, keine Warnung**, die Update-Seite ist
einfach leer, und keine Anpassung laeuft je.

Der Rat stand im Installer, in der README und auf der Projektseite. Ueberall
korrigiert: alles in `install/` loeschen, **ausser** `migrations/`. Die
Migrationsdateien tragen einen `FORUM_RUNNING`-Riegel, ueber HTTP passiert
also nichts.

Wer den Ordner schon geloescht hat: einfach aus dem Paket zuruecklegen.

### 🔢 Der README-Kopf hinkte ein ganzes MINOR hinterher

Er nannte 0.21.2, waehrend der Code bei 0.22.2 stand — und er ist das Erste,
was jemand auf GitHub liest. `tools/release.sh` prueft ihn jetzt mit, so wie
es `bootstrap.php` schon prueft.

**Keine Migration noetig.**

---

## v0.22.2 (2026-08-15)

### 📧 Die Absenderadresse gilt auch ohne SMTP — und die Vorgabe taugte nicht

Die Felder heissen „SMTP-Absender", werden aber **vor** der Entscheidung
gelesen, ob ueber SMTP oder ueber `mail()` versendet wird. Sie gelten also in
beiden Faellen. Am Feld stand nichts davon.

Schlimmer war die Vorgabe: ohne Eintrag baut das Forum
`noreply@<Host aus der Forum-Adresse>`. Bei einem Forum unter
`www.beispiel.de` wird daraus `noreply@www.beispiel.de` — und SPF und DKIM
haengen fast immer an `beispiel.de` ohne Vorsatz. Die Mail geht dann
**unbeglaubigt** raus: keine Signatur, kein SPF-Treffer, keine
DMARC-Ausrichtung. Solange die eigene DMARC-Regel auf `p=none` steht, faellt
das niemandem auf — beim Hochziehen auf `quarantine` faellt aller Forenversand
durch.

Ein fuehrendes `www.` faellt jetzt weg. Bei anderen Subdomains hilft das
nicht — welcher Teil die eigentliche Domain ist, laesst sich ohne
Suffix-Liste nicht sagen. Deshalb steht es jetzt am Feld: Adresse setzen, und
zwar eine der Domain, fuer die Versand eingerichtet ist.

#### Geänderte Dateien
- bootstrap.php (v0.22.2)
- lib/email.php (Vorgabe ohne www.)
- views/admin/index.php (Hinweis am Absenderfeld)

---

## v0.22.1 (2026-08-15)

### 🔇 Der Hinweis zum Schluessel erschien auch ohne SMTP

Wer ueber `mail()` versendet, hat gar kein SMTP-Passwort — und bekam trotzdem
den Kasten mit der Anleitung, wie man es verschluesselt. Ein Rat, etwas zu
schuetzen, das man nicht hat, ist Laerm.

Der Kasten erscheint jetzt nur, wenn ein SMTP-Passwort gesetzt ist und der
Schluessel fehlt. Der Hinweis am Feld selbst bleibt in beiden Faellen richtig.

#### Geänderte Dateien
- bootstrap.php (v0.22.1)
- admin/index.php (Bedingung fuer den Hinweis)

---

## v0.22.0 (2026-08-15)

### 🔐 Das SMTP-Passwort liegt jetzt verschluesselt in der Datenbank

In 0.21.3 wurde die falsche Zusage aus der Oberflaeche entfernt. Jetzt gibt es,
was dort vorher behauptet wurde.

**Wogegen das hilft und wogegen nicht.** Es schuetzt gegen einen
abhandengekommenen Datenbank-Abzug — eine Sicherung auf einem fremden Rechner,
eine weitergereichte Kopie, eine SQL-Luecke. Es schuetzt **nicht** gegen
jemanden, der den Server hat: der liest die `config.php` und damit den
Schluessel. Genau deshalb steht der Schluessel dort und nicht in der Datenbank
— das vorhandene `forum_secret` waere als Schluessel wertlos, es liegt neben
dem, was es schuetzen soll.

Verfahren ist AES-256-GCM. Das Ergebnis traegt seine eigene Pruefsumme: ein
veraenderter Wert entschluesselt nicht falsch, sondern gar nicht.

**Neue Installationen** bekommen den Schluessel vom Installer, jede einen
eigenen.

**Bestehende Installationen bleiben unangetastet.** Ohne Schluessel arbeitet
alles wie bisher im Klartext — lieber weiterlaufen als jemanden aus seinem
Mailversand aussperren. Wer umstellen will, findet unter *Adminbereich →
E-Mail* die fertige Zeile fuer die `config.php` und speichert das Passwort
danach einmal neu. Auch dann bleiben alte Klartext-Werte lesbar: nur was das
Vorzeichen `enc:v1:` traegt, wird entschluesselt.

Der Hinweis am Feld sagt jetzt in beiden Faellen die Wahrheit — verschluesselt
oder nicht.

#### Neue Dateien
- lib/crypto.php

#### Geänderte Dateien
- bootstrap.php (v0.22.0, laedt lib/crypto.php)
- install/index.php (schreibt `FORUM_CRYPT_KEY` in die config.php)
- admin/index.php (verschluesselt beim Speichern, Vorschlag fuer den Schluessel)
- lib/email.php (entschluesselt beim Versand)
- views/admin/index.php (Hinweis und Anleitung)

---

## v0.21.3 (2026-08-15)

### 🔎 Eine Zusage, die die Software nicht eingehalten hat

Unter dem SMTP-Passwort stand „Wird verschluesselt in der DB gespeichert."
Das stimmte nicht. Es gibt an keiner Stelle eine Verschluesselung — das Passwort
liegt im Klartext in der Tabelle `settings`. Das `base64_encode` in
`lib/email.php` ist die SMTP-Anmeldung, kein Schutz.

Fuer sich genommen ist Klartext hier nicht dramatisch: wer die Datenbank hat,
hat meist ohnehin alles. Aber eine Zusage in der Oberflaeche, die nicht
eingehalten wird, ist schlimmer als gar keine — jemand verlaesst sich darauf
und schiebt einen Datenbank-Abzug weiter, weil „ist ja verschluesselt".

Der Hinweis sagt jetzt, wie es ist, und rät zu einem eigenen Postfach.

Nachrüsten waere kein Einzeiler: `forum_secret` steht selbst in der Datenbank
und taugt deshalb nicht als Schluessel. Ein echter Schluessel muesste in die
`config.php`, und bestehende Installationen haben dort keinen.

#### Geänderte Dateien
- bootstrap.php (v0.21.3)
- views/admin/index.php (Hinweis am SMTP-Passwort)

---

## v0.21.2 (2026-08-15)

### 🏷️ Tags lassen sich nachtraeglich aendern

Tags konnten bisher **nur beim Erstellen** eines Themas vergeben werden. Damit
war ausgerechnet der Tag „Gelöst" sinnlos — den braucht man ja hinterher.

Beim Bearbeiten des **ersten** Beitrags stehen die Tags jetzt zur Auswahl.
Nur dort, weil sie am Thema haengen und nicht am Beitrag; sonst gaebe es in
einem langen Thema fuenf Stellen, die dasselbe setzen. Wird kein Tag
angekreuzt, sind danach auch keine gesetzt — sonst liesse sich ein einmal
vergebener Tag nie wieder loswerden.

### ✍️ Das Eingabefeld war kleiner als die Vorschau darunter

`.editor-textarea` hatte eine feste Hoehe von 140 Pixeln. Die Vorschau
darunter waechst mit dem Inhalt — beim Bearbeiten eines laengeren Beitrags
tippte man also in einem Kaestchen, unter dem dreimal so viel Text stand.

Das Feld richtet sich jetzt nach seinem Inhalt, bis maximal 70 % der
Fensterhoehe, danach scrollt es. Untergrenze fuer den leeren Editor sind 220
statt 140 Pixel. Wer den Ziehgriff selbst benutzt, behaelt seine Hoehe.

#### Geänderte Dateien
- bootstrap.php (v0.21.2)
- pages/edit_post.php (Tags lesen und schreiben)
- views/edit_post.php · views_new/edit_post.tpl.html (Auswahl)
- assets/style.css · assets/forum.js (mitwachsendes Textfeld)

---

## v0.21.1 (2026-08-14)

### 🐛 Auf einer Modulseite leuchtete der falsche Menuepunkt

Wer die FAQ oeffnete, sah „📋 Forum" hervorgehoben. Grund: die Navigation
liest den aktiven Punkt aus `_page`, und das setzt der Router. Eine Modulseite
laeuft aber **nicht** ueber den Router — sie wird direkt aufgerufen. Ohne
`_page` fiel die Ermittlung auf `home` zurueck, und damit galt die
Forenuebersicht als aktiv.

Der Fehler steckt seit dem ersten Modul im Layout; bei genau einem Modul ist
er nur niemandem aufgefallen.

Die Navigation vergleicht jetzt zusaetzlich den Pfad der laufenden Anfrage.
Auf einer Modulseite leuchtet der Punkt des Moduls, und die Forenuebersicht
bleibt dunkel.

#### Geänderte Dateien
- bootstrap.php (v0.21.1)
- views/layout.php (Pfadvergleich statt nur `_page`)

---

## v0.21.0 (2026-08-14)

### ✨ Drei Module und drei neue Einhaengepunkte

#### Kontaktformular

Seite, ueber die auch Nicht-Mitglieder schreiben koennen. Die Nachricht geht
per Mail an die Impressum-Adresse **und** wird in der Datenbank abgelegt. Wenn
der Mailversand klemmt, ist sie trotzdem da — ein Kontaktformular, das still
verliert, ist schlimmer als gar keins. Bremse auf drei Nachrichten je Stunde
und IP, dazu ein Honigtopf gegen Skripte.

#### Als Loesung markieren

Der Fragesteller — oder die Moderation — zeichnet eine Antwort als Loesung
aus. Der Beitrag bekommt einen gruenen Balken, oben im Thema steht ein Kasten
mit Sprung dorthin. Ein Thema hat hoechstens eine Loesung; das erzwingt der
Primaerschluessel, nicht der Code. Der erste Beitrag ist die Frage und laesst
sich nicht selbst markieren.

#### Ankuendigungsband

Band ueber der ganzen Seite fuer Wartungshinweise. Text, Farbe und
Ein-/Ausschalter liegen ohne eigene Tabelle im Konfigurationsfeld des Moduls.
Wer es wegklickt, sieht es nicht wieder — bis der Text sich aendert, denn der
Schluessel im Browser haengt am Text.

### 🪝 Neue Einhaengepunkte

`layout_top` (ueber allem), `thread_top` (ueber dem ersten Beitrag) und
`post_footer` (unter jedem Beitrag). Dafuer nimmt `render_module_hook()` jetzt
einen zweiten Wert: Zusatzvariablen fuer den Haken. Ein Haken mitten in der
Beitragsschleife nuetzt nichts, wenn er nicht weiss, um welchen Beitrag es
geht. `EXTR_SKIP` sorgt dafuer, dass ein Modul `$currentUser` nicht
ueberschreiben kann.

Beide Vorlagenwelten sind versorgt: die alten PHP-Ansichten rufen direkt auf,
fuer die `.tpl.html`-Vorlagen puffert die Seite die Ausgabe und gibt sie als
Zeichenkette weiter — dasselbe Vorgehen, das `home_top` schon benutzt.

### 🧭 Menuepunkte nur fuer die Verwaltung

`'menu' => [..., 'admin' => true]` blendet den Punkt fuer alle anderen aus.
Das Ankuendigungsband braucht das: ein Link auf eine Seite, die ohnehin nur
Admins bedienen duerfen, hat in der Navigation aller nichts zu suchen.

### 🧪 Geprueft wurde daneben, nicht im Betrieb

Auf keiner der beiden laufenden Installationen gab es ein Thema — die
Themenansicht liess sich dort also gar nicht pruefen. Dafuer steht jetzt ein
eigener Pruefstand mit eigener Datenbank und Testinhalten. Beide neuen Haken
sind dort durch die neue Vorlagenwelt hindurch nachgewiesen.

#### Neue Dateien
- modules/kontakt/ (module.php, install.sql, kontakt.php, views/kontakt.php)
- modules/loesung/ (module.php, install.sql, lib.php, markieren.php, hooks/post_footer.php, hooks/thread_top.php)
- modules/banner/ (module.php, banner.php, views/banner.php, hooks/layout_top.php)

#### Geänderte Dateien
- bootstrap.php (v0.21.0)
- lib/modules.php (Kontext fuer Haken, `admin` im Menuefeld)
- views/layout.php (`layout_top`)
- pages/thread.php · views/thread.php · views_new/thread.tpl.html (`thread_top`, `post_footer`)

---

## v0.20.0 (2026-08-14)

### ✨ Neues Modul: Downloads

Dateiablage mit eigener Seite unter `/modules/downloads/downloads.php`.
Admins stellen Dateien ein und gruppieren sie ueber ein freies Kategoriefeld,
lesen und herunterladen darf jeder. Je Eintrag werden die Abrufe gezaehlt.

Die Regeln fuer Dateien sind **dieselben wie bei den Beitrags-Anhaengen** des
Kerns: der Typ wird am Inhalt bestimmt statt am Namen, es gilt dieselbe
Liste erlaubter Typen, und der Name auf der Platte wird erzeugt
(`dl_<zufall>.<endung>`). Der Name des Hochladenden liegt getrennt daneben und
kommt nur beim Ausliefern zum Einsatz.

Ausgeliefert wird ueber `datei.php` statt ueber einen direkten Link ins
Upload-Verzeichnis. Das kostet einen Umweg, bringt aber drei Dinge: die Abrufe
lassen sich zaehlen, der Name auf der Platte bleibt unsichtbar, und wer spaeter
eine Zugangsregel einbauen will, hat genau eine Stelle dafuer. Der Dateiname in
der Kopfzeile wird von Zeilenumbruechen und Anfuehrungszeichen befreit, sonst
liesse sich der Kopfzeilen-Satz von aussen erweitern.

Schlaegt das Speichern in der Datenbank fehl, wird die bereits abgelegte Datei
wieder entfernt — sonst laege sie ohne Eintrag herum und waere weder sichtbar
noch loeschbar.

Standardmaessig ist das Modul **aus**. Adminbereich → Module → einschalten.

#### Neue Dateien
- modules/downloads/module.php
- modules/downloads/install.sql
- modules/downloads/downloads.php
- modules/downloads/datei.php
- modules/downloads/views/downloads.php

---

## v0.19.1 (2026-08-14)

### 🧭 Module melden ihren Menuepunkt selbst an

Beim Aktivieren des neuen Termin-Moduls fiel auf: es gab keinen Link dorthin.
Der Grund stand im Layout — die FAQ war dort **fest verdrahtet**, mit einem
`if (module_enabled('faq'))` um den Link herum. Jedes weitere Modul haette
also eine Zeile im Kern gebraucht, obwohl Module genau das nicht sollen.

Ein Modul traegt seinen Punkt jetzt im Manifest ein:

    'menu' => ['label' => '📅 Termine', 'url' => 'modules/termine/termine.php']

`module_menu_links()` sammelt die Punkte aller **aktiven** Module ein, das
Layout laeuft in Navigation und Fussbereich einmal darueber. Weggelassen heisst
kein Eintrag. Absolute Adressen und `..` werden verworfen — ein Modul soll
keinen fremden Link in die Navigation des Forums haengen koennen.

Die FAQ benutzt denselben Weg wie jedes andere Modul; ihr Sonderfall im Layout
ist weg.

#### Geänderte Dateien
- bootstrap.php (v0.19.1)
- lib/modules.php (`menu` im Manifest, `module_menu_links()`)
- views/layout.php (Sonderfall FAQ raus, Schleife rein)
- modules/faq/module.php · modules/termine/module.php (Menuepunkt)

---

## v0.19.0 (2026-08-14)

### ✨ Neues Modul: Termine

Terminliste mit eigener Seite unter `/modules/termine/termine.php`. Kommende
Termine oben, vergangene darunter ausgegraut. Anlegen, Aendern und Loeschen
duerfen Admins und der Inhaber, lesen darf jeder.

Ueber den Einhaengepunkt `home_top` erscheinen zusaetzlich die naechsten drei
Termine auf der Startseite — und nur dann, wenn wirklich etwas ansteht. Ein
leerer Kasten ist schlechter als gar keiner.

**Am Kern wurde nichts geaendert.** Das Modul kommt mit dem aus, was die
Schnittstelle heute hergibt: eigene Tabelle, eigene Seite, eigene Ansicht, ein
Einhaengepunkt. Eine Migration braucht es nicht — `install.sql` laeuft beim
ersten Aktivieren und ist auf Wiederholung ausgelegt.

Standardmaessig ist das Modul **aus**. Adminbereich → Module → einschalten.

#### Neue Dateien
- modules/termine/module.php
- modules/termine/install.sql
- modules/termine/termine.php
- modules/termine/views/termine.php
- modules/termine/hooks/home_top.php

---

## v0.18.61 (2026-08-14)

### 🔧 Installer verlangt jetzt PHP 8.1

`MIN_PHP` stand auf `8.0.0`, waehrend README und Projektseite 8.1 nannten. Ein
Server mit 8.0 waere also durch die Voraussetzungspruefung gekommen und haette
danach womoeglich am Forum selbst gescheitert — mit einer weit unklareren
Fehlermeldung als der des Installers. Angeglichen.

#### Geänderte Dateien
- bootstrap.php (v0.18.61)
- install/index.php (MIN_PHP)

---

## v0.18.60 (2026-08-14)

### 🧩 install.sql eines Moduls muss wiederholbar sein

`enable_module()` fuehrt zuerst `install.sql` aus und traegt das Modul **danach**
in die Tabelle `modules` ein. Scheitert der zweite Schritt, stehen die Tabellen
des Moduls schon, die Zeile fehlt aber — beim naechsten Versuch laeuft
`install.sql` erneut. Die Saatdaten des FAQ-Moduls haben feste Schluessel und
brachen dann mit "Duplicate entry" ab, mit einer ganz anderen Fehlermeldung als
beim ersten Mal.

Die beiden `INSERT` im FAQ-Modul sind jetzt `INSERT IGNORE`. Fuer eigene Module
gilt dasselbe: `install.sql` muss einen zweiten Durchlauf unbeschadet
ueberstehen.

#### Geänderte Dateien
- bootstrap.php (v0.18.60)
- modules/faq/install.sql (Saatdaten wiederholbar)

---

## v0.18.59 (2026-08-14)

### 🐛 Zwei INSERTs ohne Pflichtspalte

Beide Fehler haben dieselbe Ursache: eine Spalte ist `NOT NULL` und hat keinen
Vorgabewert, das `INSERT` liefert sie aber nicht mit. Auf einem Server mit
`STRICT_TRANS_TABLES` — der Voreinstellung von MySQL und MariaDB — scheitert
das mit Fehler 1364.

#### Module ließen sich nicht aktivieren

`enable_module()` schrieb beim ersten Aktivieren nur `code`, `enabled` und
`version` — `modules.name` fehlte. Die Ausnahme landete in der Fehlermeldung
des Adminbereichs, das Modul blieb aus. Damit war das mitgelieferte
FAQ-Modul in einer frischen Installation **nicht einschaltbar**.

#### Die Beitragshistorie zeichnete nichts auf

`record_post_edit()` schrieb kein `new_content`, obwohl die Spalte
`TEXT NOT NULL` ist — und `TEXT` kann in MySQL keinen Vorgabewert haben. Der
Fehler fiel niemandem auf, weil die Ausnahme mit einem leeren `catch`
verschluckt wurde: Bearbeiten funktionierte, nur die Historie blieb für immer
leer. Der neue Inhalt wird jetzt mitgegeben, und ein fehlgeschlagener Eintrag
landet im Fehlerprotokoll statt im Nichts.

#### Der Rest wurde mitgeprüft

Alle `INSERT`-Anweisungen in `lib/`, `pages/`, `admin/` und `modules/` gegen
die Pflichtspalten aus `install.sql` gestellt — 41 Tabellen. Außer diesen
beiden Stellen war nichts offen.

#### Geänderte Dateien
- bootstrap.php (v0.18.59)
- lib/modules.php (name im INSERT)
- lib/edits.php (new_content im INSERT, Fehler ins Protokoll)
- pages/edit_post.php (neuer Inhalt wird durchgereicht)

---

## v0.18.58 (2026-08-14)

### 🧹 Doppelte Einstellungen entfernt

#### Registrierung und Wartung standen zweimal

Wie zuvor beim SMTP: beide sind in eigene Menüpunkte umgezogen, die alten
Blöcke blieben im großen Einstellungs-Formular stehen. Wer sie dort änderte,
überschrieb still, was er auf der eigenen Seite eingestellt hatte. Beide raus,
die Abschnitte neu durchnummeriert.

Systematisch nachgesehen statt gesucht: alle Formularfelder je Menüpunkt
gegeneinander gestellt. Außer Registrierung und Wartung war nichts doppelt —
gleiche Feldnamen wie `title` oder `position` gehören zu ganz anderen
Formularen.

#### Speichern hätte die Schalter abgeräumt (Bug-Fix)

Das große Formular schickte keinen Sektions-Marker mit. Ohne den nimmt
`save_settings` das alte Verhalten und behandelt die Felder **aller** Sektionen
als zu diesem Formular gehörig — und Ankreuzfelder, die nicht mitkommen,
gelten als abgewählt. Nach dem Entfernen der Blöcke hätte ein Klick auf
„Einstellungen speichern" also Registrierung, E-Mail-Bestätigung und
Wartungsmodus ausgeschaltet. Das Formular trägt jetzt `general`.

#### CAPTCHA ließ sich nicht abschalten (Bug-Fix)

`captcha_enabled` stand in der Liste der Ankreuzfelder, ist im Formular aber
ein Auswahlfeld. Bei Ankreuzfeldern zählt nur, *ob* etwas ankommt — ein
Auswahlfeld sendet aber immer. „❌ Aus" wurde deshalb als „an" gespeichert,
und zwar seit die Einstellung existiert.

#### Karteileichen in der Feldliste

In `$sectionFields['general']` standen noch acht Branding-Felder — Name, Logo,
Slogan, Akzentfarbe, Standard-Theme, beide Fußzeilen-Felder und Custom CSS.
Die liegen längst im Designbereich und werden von `save_design` geschrieben.
Ohne Wirkung, aber irreführend beim Lesen. Raus.

#### Geänderte Dateien
- bootstrap.php (v0.18.58)
- admin/index.php (captcha_enabled aus der Boolean-Liste, Feldliste aufgeräumt)
- views/admin/index.php (zwei Blöcke raus, Sektions-Marker, Nummerierung)

---

## v0.18.57 (2026-08-14)

### 🎨 Standard-Logo, Schriftstärken und doppeltes SMTP

#### Der Punkt im Standard-Logo verrutschte (Bug-Fix)

Der Punkt hinter dem Forennamen stand als eigener Kreis an einer gerechneten
Stelle: `Zeichenzahl × Schriftgröße × 0.55`. Diese Schätzung stimmt bei keinem
Namen genau, also wanderte der Abstand — bei schmalen Namen klaffte eine Lücke,
bei breiten rutschte der Punkt in den Text hinein.

Der Punkt hängt jetzt als `tspan` im Text selbst, damit der Browser den
Abstand misst statt PHP ihn zu raten. Größe und Höhe sind aus den echten
Maßen von Inter 800 abgeleitet (Punkt 0.312 em breit, 0.290 em über der
Grundlinie), sodass er aussieht wie der bisherige Kreis.

Dieselbe Schätzung bestimmte auch die Zeichenfläche — bei einem breiten Namen
war sie zu klein und **der Name wurde rechts abgeschnitten**. Die Breite wird
jetzt je Zeichenklasse geschätzt, mit 12 % Aufschlag. Gegen die Schriftmaße
geprüft, auch mit Namen wie „MMMMMMMMMMMMMM".

#### Logo wurde mit Stärke 700 statt 800 gezeichnet (Bug-Fix)

Inter ist eine variable Schrift und trägt alle Stärken von 100 bis 900 in
einer Datei. In `fonts.css` stand sie viermal mit je einem festen Wert
(400/500/600/700) auf derselben Datei — damit klemmt der Browser die Achse auf
diesen Wert. Das Logo fragt 800 an und bekam 700. Jetzt zwei Deklarationen mit
`font-weight: 100 900`, das sind 46 Zeilen weniger.

#### SMTP stand zweimal im Adminbereich

Beim Umzug in einen eigenen Menüpunkt blieb der alte Block in den
Einstellungen stehen. Er ist raus.

Dabei fiel auf, dass die Test-Mail auf der neuen Seite an
`?action=send_test_email` schickte — **diese Aktion gibt es nicht**, der Knopf
lief ins Leere. Sie benutzt jetzt denselben Weg wie vorher der alte Block:
`send_test_mail` mit Rückmeldung direkt unter dem Feld.

#### Geänderte Dateien
- bootstrap.php (v0.18.57)
- lib/settings.php (Logo-Geometrie)
- assets/fonts.css (Inter als Stärkebereich)
- views/admin/index.php (SMTP-Block entfernt, Test-Mail repariert)

---

## v0.18.56 (2026-08-14)

### 🔒 Sicherheit, Logo und Fußbereich

Sammelversion aus der Vorbereitung zur Veröffentlichung als Forumix.

#### Session Fixation (Sicherheit)

`session.use_strict_mode` wird jetzt vor `session_start()` gesetzt. Ohne das
übernimmt PHP eine Sitzungs-ID, die der Besucher selbst mitbringt — jemand
kann einem Opfer vorab eine ID unterschieben und dessen angemeldete Sitzung
danach mitbenutzen. Die Einstellung steht im Code und nicht in der
Server-Konfiguration, damit jede Installation sie mitbringt.

#### Standard-Logo zeigte den falschen Namen (Bug-Fix)

Die Logo-Vorschau im Adminbereich band `assets/logo_default.svg` als Bild ein
statt das aus dem Forum-Namen erzeugte SVG. In einer frischen Installation
stand dort deshalb ein fremder Forenname, obwohl im Feld darüber der eigene
eingetragen war. Die Vorschau benutzt jetzt `forum_logo_render()` — dieselbe
Funktion wie der Seitenkopf, also sieht man dort auch wirklich das, was die
Besucher sehen.

#### Fußbereich aufgeräumt

Neben dem Forumix-Zeichen stand ein zweiter Link „Quelltext (AGPLv3)". Das
Zeichen selbst ist jetzt der Link und verweist auf forumix.de, wo der
Quelltext liegt; damit ist § 13 der AGPLv3 erfüllt, ohne Kleingedrucktes im
Fuß. Die Einstellung „Quelltext-URL" bleibt und lenkt das Ziel um — wer den
Code ändert, trägt dort sein eigenes Repository ein.

#### Eingabefelder vom Typ url waren ungestylt

`.form-row` zählt die Eingabetypen einzeln auf und `url` fehlte. Das Feld
„Quelltext-URL" stand als schmaler Kasten zwischen zwei vollbreiten Feldern.
`tel`, `search` und `time` gleich mit aufgenommen.

#### Migration

`migrate_v18_56.php` verbreitert `users.pending_email_token` auf
`VARCHAR(255)`. Der Token wird gehasht abgelegt und passte nicht mehr in die
alte Spaltenbreite — die Bestätigung einer E-Mail-Änderung lief dadurch ins
Leere.

#### Geänderte Dateien
- bootstrap.php (v0.18.56, `FORUMIX_URL` statt `FORUMIX_SOURCE_URL`)
- lib/auth.php (use_strict_mode)
- views/layout.php (Fußbereich)
- views/admin/index.php (Logo-Vorschau, Feld „Quelltext-URL")
- admin/index.php (Vorgabe für `source_url` ist jetzt leer)
- assets/style.css (Eingabetypen)
- install/migrations/migrate_v18_56.php

---

## v0.18.55 (2026-05-28)

### 📦 Erstes Update übers neue Paket-System + Versions-Schutz

Das ist das erste Update, das über das in v0.18.54 eingeführte ZIP-Upload-
Feature eingespielt werden kann (statt FTP). Inhaltlich enthält es die
Editor-Textfarben (ursprünglich als v0.18.53 entwickelt) plus einen
wichtigen Schutz im Paket-Installer.

#### Versions-Schutz (Bug-Fix)

`update_record_package()` setzt die forum_version jetzt NUR noch wenn die
Paket-Version höher ist als die aktuelle — und nie unter FORUM_VERSION_CODE.
Damit kann das nachträgliche Einspielen eines älteren Pakets die angezeigte
Version nicht zurückwerfen. (Gefunden beim Testen: ein Paket mit niedrigerer
Versionsnummer hätte den Footer kurzzeitig zurückgesetzt, bis das
Self-Healing in bootstrap.php es wieder hochzog.)

#### Editor-Textfarben (Inhalt aus dem früheren v0.18.53)

- Farb-Button in der Editor-Toolbar mit 12-Farben-Palette + eigenem Color-Picker
- [color=...]-BBCode robuster (Großbuchstaben-Hex, benannte Farben, injection-safe)
- Voller BBCode-Editor in "Über mich" und Signatur

#### Geänderte Dateien
- bootstrap.php (v0.18.55)
- lib/update_package.php (Versions-Schutz)
- lib/functions.php (color-Regex + render_editor)
- lib/profile_fields.php (Editor in "Über mich")
- views/partials/editor.php (Farb-Button)
- views_new/settings.tpl.html (Editor in Signatur)
- assets/forum.js, assets/style.css (Farb-Palette)

## v0.18.54 (2026-05-28)

### 📦 Update-Bereich: ZIP-Upload statt Datei-Migrationen

Der Admin-Update-Bereich kann jetzt Update-Pakete als ZIP entgegennehmen,
prüfen und voll-automatisch einspielen (Dateien kopieren + SQL ausführen).

#### Neuer Workflow

1. Owner lädt Update-ZIP im Admin-Bereich hoch
2. System validiert das Paket VOLLSTÄNDIG bevor etwas geschrieben wird
3. Bei Erfolg: Dateien werden kopiert, update.sql wird ausgeführt
4. Eintrag im Verlauf (wie bei Migrationen), forum_version wird gesetzt

#### Paket-Format

```
update.json    (Pflicht — {"version":"0.18.xx","name":"Beschreibung"})
update.sql     (optional — wird statement-weise ausgeführt)
files/         (alles hierunter wird relativ ins Forum-Root kopiert)
  bootstrap.php
  lib/...
```

#### Sicherheitsschichten

Da dies ein Remote-Code-Execution-Vektor ist, mehrfach abgesichert:
- **Nur Owner** (is_owner) — nicht mal normale Admins
- **CSRF-Pflicht**
- **Whitelist** erlaubter Endungen (php, css, js, sql, html, tpl, json, md,
  txt, Bilder, Fonts) — alles andere bricht ab
- **Zip-Slip-Schutz**: kein `..`, kein absoluter Pfad; Ziel muss im
  Forum-Root bleiben (doppelt geprüft: Entry-Name + realpath nach mkdir)
- **Manifest-Pflicht**: ohne gültiges update.json kein Einspielen
- **PHP wird nur kopiert, nie inkludiert** während des Uploads
- **SQL nur aus genau einer Datei** (update.sql), statement-weise,
  idempotenz-freundlich (Duplicate-column/already-exists wird übersprungen)
- **Größenlimit** 20 MB

Die Validierungs-Logik wurde mit 18 Angriffs- und Positiv-Fällen getestet
(Path-Traversal, absolute Pfade, gefährliche Endungen etc.).

#### Verlauf begrenzt

Der Verlauf zeigt jetzt die letzten 15 Einträge; ältere sind unter
„▾ N ältere Einträge anzeigen" ausklappbar (`<details>`). Die Gesamt-Anzahl
steht in der Überschrift.

#### Detail-Anzeige beim Einspielen

Nach dem Einspielen zeigt eine Ergebnis-Box pro Datei den Status:
- 🆕 **neu** — Datei existierte vorher nicht
- ✏️ **geändert** — bestehende Datei mit anderem Inhalt überschrieben
  (per SHA-256-Vergleich erkannt)
- **= unverändert** — Inhalt identisch, Datei wird übersprungen (kein I/O)

Plus Zusammenfassung („X neu, Y geändert, Z unverändert") und das
SQL-Ergebnis. Das volle Log wird auch im Verlauf-Eintrag gespeichert.

#### Datei-Migrationen bleiben

Der alte Mechanismus (Migrations-Dateien im install/migrations/-Ordner +
Einzeln-Ausführen) funktioniert weiter und zeigt ausstehende Migrationen
weiterhin an. Beide Wege schreiben in denselben Verlauf.

#### Geänderte Dateien
- lib/update_package.php (NEU — Validierung + Installation)
- bootstrap.php (Lib geladen + v0.18.54)
- admin/index.php (upload_update-Handler + updates-View lädt History-Limit)
- views/admin/index.php (Upload-Form + begrenzter/ausklappbarer Verlauf)

## v0.18.53 (2026-05-27)

### 🎨 Textfarben im Editor + Editor in Profil-Feldern

#### 1. Farb-Auswahl im BBCode-Editor

Neuer Farb-Button (buntes "A") in der Editor-Toolbar. Klick öffnet eine
Palette mit 12 vordefinierten Farben + einem nativen Color-Picker für
beliebige eigene Farben.

Auswahl wickelt die Selektion in `[color=#xxxxxx]…[/color]`.

Die 12 Palette-Farben: Rot, Orange, Gelb, Grün, Türkis, Blau, Violett,
Pink, Braun, Grau, Schwarz, Weiß.

#### 2. [color]-BBCode robuster

Das `[color=...]`-Pattern unterstützt jetzt:
- Hex mit Großbuchstaben: `[color=#FF0000]` (vorher nur Kleinbuchstaben)
- 3- und 6-stellige Hex-Codes: `#fff` und `#ffffff`
- Benannte Farben: `[color=red]`, `[color=darkblue]`

Sicherheit: Das Regex erlaubt nur Hex-Codes und reine Buchstaben-Namen —
keine Klammern, Semikola oder Doppelpunkte, die CSS-Injection (z.B.
`url(...)` oder `expression(...)`) ermöglichen würden.

#### 3. Voller Editor in "Über mich" und Signatur

Bisher waren die Profil-Felder "Über mich" und "Signatur" simple
Textareas ohne Formatierungs-Hilfe. Jetzt nutzen beide den vollen
BBCode-Editor mit Toolbar (inkl. der neuen Farb-Auswahl).

Technisch: Neue Helper-Funktion `render_editor()` in functions.php, die
das Editor-Partial als HTML-String zurückgibt. Damit kann der Editor auch
in den .tpl.html-Templates (die kein PHP-include können) genutzt werden —
über den im Template erlaubten Funktionsaufruf
`{{ render_editor(...) | raw }}`.

#### Keine Migration nötig

Reines Code/Frontend-Update. `signature` und `profile_about` sind bereits
TEXT-Spalten und werden bereits durch bbcode() gerendert — die Farben
erscheinen also sofort korrekt.

#### Geänderte Dateien
- lib/functions.php (color-Regex robuster + render_editor-Helper)
- lib/profile_fields.php ("Über mich" nutzt Editor)
- views/partials/editor.php (Farb-Button in Toolbar)
- views_new/settings.tpl.html (Signatur nutzt Editor)
- assets/forum.js (Farb-Palette-Logik)
- assets/style.css (Farb-Palette-Styling)

## v0.18.52 (2026-05-27)

### 🕐 "Zuletzt aktiv" zeigt jetzt echte Aktivität

#### Symptom (User-Fund vom 27.05.)

Steffi hat am 26.05. einen Beitrag geschrieben. In ihrem Profil stand
trotzdem "Zuletzt aktiv 23.05.2026" — also vor 3 Tagen.

#### Ursache

Das Profil zeigte `users.last_login` als "Zuletzt aktiv". Diese Spalte
wird aber NUR beim echten Login mit Username+Passwort aktualisiert. Bei
Remember-Me-Sessions bleibt sie auf dem Datum vom Cookie-Setzen — der
User kann das Forum täglich nutzen, ohne dass `last_login` sich ändert.

Außerdem hat die Inspektion offenbart: Die Spalte `users.last_post_at`
existierte, wurde aber nirgendwo gesetzt — Zombie-Spalte aus früherer
Schema-Migration.

#### Lösung

**Neue Spalte `users.last_activity_at`** (Migration v18_52):
- Wird bei JEDEM authentifizierten Pageview via `track_online()` gesetzt
- Wird beim Posten + Antworten zusätzlich explizit gesetzt
- Initial-Befüllung bei Migration: GREATEST(last_login, last_post_at)

**`users.last_post_at`** wird endlich auch gesetzt — bisher Zombie.

**Profil-Template**: zeigt `last_activity_at` als "Zuletzt aktiv",
Fallback auf `last_login` wenn noch NULL.

**Aktivitäts-Level (v0.18.50)** nutzt jetzt `last_activity_at` statt
`last_login` — sonst hätten Remember-Me-User fälschlich "🟠 Selten"
angezeigt bekommen.

**`users.last_login`** bleibt unverändert was sie ist (echte Logins) —
wichtig für Sicherheits-Auswertungen ("wann hat sich der User wirklich
mit Passwort angemeldet").

#### Performance

`track_online()` macht jetzt einen zusätzlichen UPDATE pro Pageview für
eingeloggte User. Bei kleinen Foren (Aynku: 4 User) absolut egal. Bei
großen Foren könnte das relevant werden — dann wäre Throttling
empfehlenswert (z.B. nur alle 60 Sekunden), aber das ist heute kein
Thema.

#### Migration

`v18_52` legt die Spalte + Index an, befüllt rückwirkend aus
last_login/last_post_at. Idempotent.

## v0.18.51 (2026-05-26)

### 🛡️ Online-Tracking: "Gäste im Adminbereich"-Phantom-Bug

#### Symptom (vom User entdeckt)

Auf `/online.php` tauchten regelmäßig Einträge wie

```
Gast    Adminbereich    Heute, 18:49 Uhr    142.250.32.3
Gast    Adminbereich    Heute, 18:49 Uhr    66.249.73.196
```

auf. Die IP-Adressen waren ausnahmslos Google-Crawler (142.250.x.x,
74.125.x.x, 66.249.x.x — alles AS15169 / Google LLC).

#### Was wirklich passierte

Googlebot und ähnliche Crawler folgten Links und versuchten `/admin/`
aufzurufen. Forum-Auth hat sie korrekt abgelehnt — aber die
`track_online()`-Funktion läuft **bevor** der Zugriff verweigert wird.
Die `location_label()`-Logik prüfte nur ob die URL `/admin/` enthielt,
nicht ob der Zugriff erfolgreich war oder ob der User Admin-Rechte hat.

Resultat: gescheiterte Zugriffsversuche wurden als "Adminbereich"
gelabelt und sahen aus wie wirklich erfolgreiche Admin-Zugriffe.

**Sicherheits-Bewertung**: kein echter Zugriff. Reines Tracking-Phantom.

#### Fix A: Authentifizierung-aware Tracking

In `lib/online.php` `location_label()`:

```php
if (str_contains($uri, '/admin/')) {
    if (is_admin() || is_mod()) {
        return 'Adminbereich';
    }
    return 'Forum';  // Crawler/anonyme Zugriffs-Versuche neutral labeln
}
```

Jetzt taucht "Adminbereich" nur noch in der Liste auf wenn der angezeigte
User wirklich Admin oder Mod ist.

#### Fix B: robots.txt

Crawler sollten sensible Bereiche gar nicht erst aufrufen müssen. Neue
`robots.txt` im Forum-Root:

```
Disallow: /admin/
Disallow: /install/
Disallow: /settings.php
Disallow: /pm.php
Disallow: /notifications.php
Disallow: /bookmarks.php
Disallow: /login.php / /register.php / /reset_password.php / ...
Disallow: /verify_email.php / /verify_email_change.php
Disallow: /unsubscribe.php
Disallow: /search.php / /online.php
Crawl-delay: 2
```

Respektierte Bots (Google, Bing, DuckDuckGo, Anthropic etc.) halten sich
daran. Böse Bots ignorieren robots.txt sowieso — aber die werden durch
Fix A oder die Auth-Schicht abgefangen.

#### Keine Migration, keine Breaking Changes

Reines Code-Update. 2 Dateien geändert.

## v0.18.50 (2026-05-26)

### 📊 Erweiterte Profil-Statistik + Top-Beiträge

#### Was sich ändert

Profil-Seite (`/profile.php?id=...`) bekommt zwei neue Sektionen unter den
bestehenden Stat-Karten:

**📊 Aktivität** — strukturierte Liste mit:
- 💬 Threads erstellt (neu: vorher nur Posts-Count)
- 📝 Beiträge gesamt
- ↑ Reaktionen erhalten
- ↓ Reaktionen vergeben (neu: zeigt wie aktiv jemand mit anderen interagiert)
- 📅 Posts in den letzten 30 Tagen

**🏆 Top-Threads** — die 3 Threads des Users mit den meisten Reaktionen,
sortiert absteigend. Zeigt was die Person Gutes beigetragen hat.

**Profil-Meta erweitert**:
- 🆔 Mitglied-Nummer (#1, #2, ...)
- Aktivitäts-Level-Badge: 🟢 Aktiv / 🟡 Gelegentlich / 🟠 Selten / ⚫ Inaktiv

#### Aktivitäts-Level-Berechnung

```
🟢 Aktiv:         ≥10 Posts in 30 Tagen ODER letzter Login <7 Tage
🟡 Gelegentlich:  ≥1 Post in 30 Tagen ODER Login <30 Tage
🟠 Selten:        Login <90 Tage
⚫ Inaktiv:       sonst
```

Bewusst niedrige Schwellwerte — für kleine Foren sollen auch ein paar
Posts schon als "aktiv" gelten.

#### Technisches

- Neue Lib: `lib/user_stats.php` mit Stat-Helpern
  (`user_threads_count`, `user_given_reactions`, `user_posts_in_last_days`,
  `user_activity_level`, `user_top_threads`)
- `pages/profile.php` lädt die neuen Daten als `extendedStats`-Array
- Template `profile.tpl.html` zeigt sie strukturiert an
- CSS `assets/style.css` ergänzt um `.profile-activity-grid`-Klassen
- Bootstrap lädt die neue Lib mit

#### Keine Migration nötig

Alle Daten existieren bereits in den vorhandenen Tabellen (threads, posts,
reactions). Reines Code-Update.

#### Sichtbarkeit

Stats respektieren `board_filter_sql()` — Posts/Threads aus für den
anfragenden User gesperrten Foren werden nicht mitgezählt.

## v0.18.49 (2026-05-26)

### 🎨 Admin-Menü-Refactor: logischere Gruppierung

Das Admin-Menü wurde nach 8 konkreten UX-Kritikpunkten umgebaut. Items
sitzen jetzt da wo man sie intuitiv sucht.

#### Was sich ändert (Auslöser: "Wartungsmodus liegt unter Settings statt Wartung")

| Vorher | Jetzt |
|---|---|
| Wartungsmodus unter Einstellungen | 🛠️ Wartung → Wartungsmodus (eigener Punkt) |
| SMTP-Block in Einstellungen versteckt | 📧 E-Mail als eigener System-Punkt |
| Registrierungs-Settings in Einstellungen | 📝 Registrierung als eigener System-Punkt |
| Mod-Log unter System | unter 👥 Benutzer (gehört thematisch dahin) |
| IP-Suche + IP-Banns separate Punkte | 🌐 IP-Verwaltung mit Sub-Tabs |
| Design + Templates unter System | 🎨 Darstellung als neue Gruppe |
| "Rechtliches" unter System | 📄 Seiten unter Inhalt |
| Statistik als eigener Punkt | ins Dashboard integriert (Daten werden mitgeladen) |

#### Neue Menü-Struktur

```
📊 Dashboard          ← lädt jetzt Statistik-Daten mit
🚦 Freigabe
🚨 Meldungen

👥 Benutzer
  ├── 👥 Benutzerliste
  ├── 🆕 Reg.-Freigaben
  ├── ✉️ Einladungen
  ├── ⚠️ Warnungen
  ├── 📜 Mod-Log         ← NEU hier
  ├── 🌐 IP-Verwaltung   ← Lookup + Banns mit Sub-Tabs
  └── 🤖 Bot-Verkehr

📁 Inhalt
  ├── 📁 Kategorien
  ├── 📋 Foren
  ├── 🏷️ Tags
  ├── 🏅 Ränge
  ├── 😀 Smileys
  └── 📄 Seiten          ← war "Rechtliches" unter System

🎨 Darstellung         ← NEU
  ├── 🎨 Design
  └── 🖼️ Templates

⚙️ System
  ├── 🔧 Einstellungen   ← entschlackt
  ├── 📧 E-Mail          ← NEU als eigener Punkt
  ├── 📝 Registrierung   ← NEU als eigener Punkt
  ├── 🧩 Module
  ├── 💾 Backups
  └── 🚀 Updates

🛠️ Wartung            ← NEU als eigene Gruppe
  ├── 🛠️ Wartungsmodus  ← NEU eigener Punkt mit klaren Warnhinweisen
  └── 🧹 Tools          ← was bisher "Wartung" hieß
```

#### Sektion-aware Save-Handler

Bisher war `save_settings` ein Mega-Form das ALLE Settings auf einmal
speicherte. Wenn man jetzt einen Sub-Form abschickt (z.B. nur SMTP), darf
das nicht die anderen Settings überschreiben (insb. nicht den Wartungsmodus
deaktivieren, weil seine Checkbox nicht in dem Form ist).

Lösung: `settings_section`-Marker im POST. Wenn gesetzt, werden nur die
zur Sektion gehörenden Felder gespeichert. Ohne Marker (Kompatibilität)
funktioniert das alte Form weiter wie gehabt.

```
'general'          → forum_name, theme, captcha, ...
'email'            → smtp_*
'registration'     → registration_*, email_verification_required, ...
'maintenance_mode' → maintenance_mode, maintenance_message
```

Sub-Forms haben jetzt jeweils einen eigenen "💾 Speichern"-Button, der
nur ihre Sektion betrifft. Redirect geht zur richtigen Sektion zurück.

#### Sub-Tabs in IP-Verwaltung

Statt zwei separater Menüpunkte gibt es jetzt eine Seite mit 🔍 Lookup-Tab
und 🚫 Banns-Tab. Die alte URL `?action=ipbans` bleibt rückwärts-kompatibel
erhalten (eigener Code-Block existiert noch), zeigt aber keinen Menüpunkt
mehr.

#### Wartungsmodus-Seite mit Sicherheits-Hinweisen

Die neue dedizierte Wartungsmodus-Seite enthält:
- Den Toggle für Aktivierung
- Wartungs-Nachricht-Textarea
- **Großen Warnhinweis** mit phpMyAdmin-Notausgang-SQL
- **Roten Status-Block** wenn Modus aktiv ist ("Logge dich nicht aus!")

Lessons Learned aus dem Pre-Test heute (26.05.) sind hier permanent
verankert.

#### Keine Migration nötig

Reines Code-Refactor. Bestehende Settings-Daten unverändert. Funktional
ist alles weiterhin erreichbar — nur an logischeren Stellen.

## v0.18.48 (2026-05-26)

### 🔐 Sicherheits-Update: Passwort-Change + E-Mail-Doppel-Opt-In

Großes Update für den Sicherheits-Tab: zwei Standard-Patterns aus dem Account-
Security-Playbook werden jetzt umgesetzt.

#### 1. Passwort-Änderung — jetzt mit Bestätigung

**Vorher**: Neues Passwort in ein Feld eintragen → speichern → fertig. Wer auch
immer kurz vor dem Bildschirm sitzt während du eingeloggt bist (offene Session),
konnte dein Passwort ändern.

**Jetzt**:
- Aktuelles Passwort muss eingegeben werden (Bestätigung der Identität)
- Neues Passwort muss zweimal eingegeben werden (Tippfehler-Schutz)
- Live-JS-Check ob die Wiederholung übereinstimmt
- Neues Passwort muss != altem sein
- Eigener Action-Handler `change_password` mit strikter Validierung

#### 2. E-Mail-Änderung — jetzt mit Doppel-Opt-In

**Vorher**: E-Mail-Feld überschreiben → speichern → fertig. Bei Tippfehler:
Account verloren (Passwort-Reset geht an die falsche Adresse). Bei Angreifer
mit Session-Zugriff: Account-Übernahme trivial.

**Jetzt**:
- Aktuelle E-Mail wird im Sicherheits-Tab als **read-only** angezeigt
- Klick auf "E-Mail-Adresse ändern" öffnet eigenes Sub-Form
- User gibt neue Adresse + aktuelles Passwort ein
- Server speichert die neue Adresse in `pending_email` (NICHT in `email`!)
- HMAC-signierter Token (24h gültig) wird an die NEUE Adresse verschickt
- Zusätzlich: Hinweis-Mail an die ALTE Adresse mit Security-Awareness
- Erst nach Klick auf den Link wird `pending_email → email` umgezogen
- Während der Pending-Phase: alte Mail bleibt aktiv, User sieht den Status
  im Sicherheits-Tab und kann abbrechen

#### Sicherheits-Vorteile dieses Patterns

| Szenario | Vorher | Jetzt |
|---|---|---|
| Tippfehler in neuer Mail | Account verloren | Nichts passiert (kein Link-Klick) |
| Angreifer mit Session | E-Mail-Übernahme sofort | Braucht Passwort + neue Inbox |
| Social-Engineering | Mail-Änderung unbemerkt | Alte Inbox bekommt Warnung |
| Tippfehler im aktuellen PW | n/a | Frühe Fehlermeldung |

#### Technische Details

**Neue Migration v18_48**: 3 Spalten in `users`:
- `pending_email` VARCHAR(255)
- `pending_email_token` VARCHAR(64)
- `pending_email_expires` DATETIME

**Neue Library `lib/email_change.php`**:
- `email_change_initiate(user_id, new_email)` — startet Flow
- `email_change_verify(token)` — bestätigt und führt Umzug aus
- `email_change_cancel(user_id)` — bricht offene Anfrage ab
- HMAC-Secret eigenständig (Setting `email_change_secret`, auto-generiert)
- Pattern analog zu `unsubscribe_token.php` aus v0.18.44

**Neue Seite `pages/verify_email_change.php`**: Token-Klick-Ziel mit Status-Anzeige

**Neue Action-Handler in `pages/settings.php`**:
- `change_password` — separat vom alten `save_profile`
- `request_email_change` — startet den Doppel-Opt-In
- `cancel_email_change` — bricht offene Anfrage ab

**`save_profile`-Handler entschlackt**: behandelt E-Mail und Passwort nicht
mehr — das macht jetzt nur die dedizierten Handler. Eliminiert auch endgültig
das Risiko des Tab-Save-Bugs aus v0.18.45 (E-Mail-Spalte wird im Profil-Tab
gar nicht mehr angefasst).

#### Migration

**1 neue Migration**: `migrate_v18_48` legt die 3 pending_email-Spalten an.
Idempotent (mehrfaches Ausführen sicher).

## v0.18.47 (2026-05-26)

### 🔧 Wartungsmodus-Seite: Admin-Login-Link funktioniert jetzt

#### Symptom
Auf der "🛠️ Wartungsmodus"-Seite war der "Admin-Login"-Link **relativ**
(`<a href="login.php">`). Das funktionierte nur, wenn man bereits im
Forum-Root-Verzeichnis war. Beim direkten Aufruf von z.B. `/login.php`
auf älteren Forum-Versionen ohne `.htaccess`-Rewriting → Sackgasse.

#### Fix
Beide Links sind jetzt **absolut** mit `FORUM_URL`:

```html
<a href="https://aynku.de/login.php">Admin-Login</a> ·
<a href="https://aynku.de/admin/">Admin-Bereich</a>
```

Zusätzlich neuer "Admin-Bereich"-Link als zweite Option, falls das
Forum-Login-Routing mal hakt.

#### Lessons Learned aus Live-Deploy-Pre-Test
Beim Test des Wartungsmodus auf dem Live-Forum (v0.16.x) wurden zwei
UX-Probleme entdeckt:

1. **Admin muss bereits eingeloggt sein VOR Aktivieren des Wartungsmodus**.
   Sonst ist die Wartungs-Seite eine Sackgasse — der Login-Link kommt
   nicht zur Maske, sondern lädt nur die gleiche Seite neu.
2. **phpMyAdmin ist der zuverlässige Notausgang**: `UPDATE settings
   SET setting_value = '0' WHERE setting_key = 'maintenance_mode'`
   schaltet den Modus sofort aus, ohne Forum-Login.

Diese Lessons sind im überarbeiteten Live-Deploy-Bundle (v0.18.47)
fett im Step-by-Step-Doc dokumentiert.

#### Keine Migration nötig
Reines Code-Update.

## v0.18.46 (2026-05-26)

### 🔔 Settings: Eigener Tab "Benachrichtigungen"

Die E-Mail-Benachrichtigungs-Checkbox war bisher unter "🎨 Darstellung" einsortiert
— thematisch unpassend, weil Benachrichtigungen kein Aussehen sondern Verhalten sind.

#### Was sich ändert
- **Neuer Tab "🔔 Benachrichtigungen"** in der Settings-Navigation
- Checkbox "E-Mail-Benachrichtigungen aktivieren" wandert dorthin
- Erweiterte Erklärung im UI (verweist auf 1-Klick-Abmelde-Link im Mail-Footer)
- Anzeige der aktuell hinterlegten E-Mail-Adresse mit Link zum Sicherheit-Tab
- Tab "🎨 Darstellung" enthält jetzt nur noch Theme-Auswahl

#### Tab-Reihenfolge
1. 👤 Profil
2. 🔐 Sicherheit
3. 💻 Sitzungen
4. 🔔 Benachrichtigungen ← NEU
5. 🎨 Darstellung
6. ⚠️ Konto

#### Save-Handler erweitert
- Neuer Hidden-Marker `notifications_tab` im Benachrichtigungen-Form
- Erkennt tab-spezifisch ob Checkbox-Wert übernommen werden soll
- Konsistent mit dem Tab-aware Save-Pattern aus v0.18.45

#### Unsubscribe-Page aktualisiert
- Links in `views/unsubscribe.php` zeigen nun auf `?tab=notifications`
- Mail-Footer-Link (`lib/notifications.php`) ebenso

#### Keine Migration nötig
Reines UI-Refactoring. Keine Schema-Änderungen.

## v0.18.45 (2026-05-26)

### 🐛 Hotfix: Profil-Speichern crashte mit "Duplicate entry '' for key 'email'"

#### Symptom
Beim Klicken auf "Speichern" im Profil-Tab (oder Darstellung-Tab):
```
Fatal error: PDOException: SQLSTATE[23000]: Integrity constraint violation:
1062 Duplicate entry '' for key 'email' in lib/db.php:50
```

#### Root Cause
Seit v0.18.27 (Profil/Settings-Trennung) gibt es 5 Tabs in der Settings-Seite,
aber alle nutzen denselben Save-Handler mit `action=save_profile`. Der Handler
las JEDES Feld via `post_str()` aus dem POST — auch wenn das aktuell abgesendete
Tab-Form das Feld gar nicht enthält.

Konsequenz: Wenn man den Profil-Tab abschickte (der kein 'email'-Feld hat),
wurde `$email = post_str('email')` zu `''` (leer) → UPDATE setzte die E-Mail
auf leer → Kollision mit anderem User der bereits leere E-Mail hatte (z.B.
"gelöschter Benutzer") → UNIQUE-Constraint-Verletzung.

Analog: Profil-Tab überschrieb das Theme auf 'auto' (Default), Sicherheits-Tab
überschrieb alle Profil-Felder auf leer.

#### Fix
Der Save-Handler ist jetzt **tab-aware**: Jedes Feld wird nur dann aus POST
gelesen wenn ein Tab-spezifisches Schlüssel-Feld im POST steht:

| Tab | Schlüssel-Feld zur Erkennung |
|---|---|
| Profil | `profile_gender` |
| Darstellung | `theme` |
| Sicherheits-Tab | `email` (existiert nur dort) |

Wenn das Schlüssel-Feld nicht im POST ist → bestehende DB-Werte behalten.

#### Betroffene Datei
`pages/settings.php`: Logik in Zeilen 94-115 und 181-238 umgestellt.

#### Keine Migration nötig
Reines Code-Update. Keine Schema-Änderungen.

#### Wichtig fürs Live-Deploy
Wenn du das Live-Forum von v0.16.x updatest, deploye GLEICH v0.18.45 (nicht
v0.18.44 ohne diesen Fix), sonst können bestehende User ihr Profil nicht
mehr speichern.

## v0.18.44 (2026-05-26)

### 📧 E-Mail-Benachrichtigung bei Thread-Antworten

Wenn jemand auf einen abonnierten Thread antwortet, wird der Subscriber jetzt
auch per E-Mail benachrichtigt (zusätzlich zur In-App-Glocke). Voraussetzung:
User hat `notify_email = 1` in den Einstellungen (Default: aus, muss aktiv eingeschaltet werden).

#### Was die Mail enthält
- Hallo + Username
- "X hat in Thread Y geantwortet"
- 200-Zeichen-Vorschau vom Post (HTML-Tags entfernt)
- Großer "→ Zur Antwort"-Button (direkt zur Post-Position)
- Footer mit 1-Klick-Abmelde-Link und Settings-Link

#### 1-Klick-Abmelde-Token (HMAC-signiert)
- Token-Format: `base64url(payload).base64url(hmac_sha256)`
- Payload: `user_id:thread_id:expiry_timestamp`
- HMAC-Secret wird einmalig in der `settings`-Tabelle generiert
  (Setting `unsubscribe_secret`, 64 Hex-Zeichen)
- Stateless – kein DB-Eintrag pro Token nötig
- Gültigkeit: 30 Tage
- Klick auf Link → `/unsubscribe.php?token=...` → Abo gelöscht ohne Login

#### Sicherheit
- Manipulierte Tokens werden via `hash_equals()` (konstante Zeit) abgelehnt
- Abgelaufene Tokens werden serverseitig verworfen
- Token-Verifikation reicht für Abmeldung – kein CSRF-Token nötig
  (User muss nicht eingeloggt sein, der Mail-Empfang ist die Authentifizierung)

#### Use-Case Walkthrough
1. Alice abonniert Thread "Hallo Welt" (oder hat selbst gepostet, dann Auto-Abo)
2. Alice hat in Settings → Darstellung → "E-Mail-Benachrichtigungen" aktiviert
3. Bob antwortet auf den Thread
4. Server: `notify_thread_subscribers()` läuft durch
5. Alice bekommt: In-App-Notification (Glocke) + E-Mail
6. Alice klickt im Mail-Footer "Mit einem Klick abmelden"
7. Browser öffnet `/unsubscribe.php?token=...` → "✅ Abmeldung erfolgreich"

#### Technische Details
- `lib/notifications.php`: `notify_thread_subscribers()` erweitert um Mail-Versand
- `lib/notifications.php`: neue private Funktion `_send_reply_notification_email()`
- `lib/unsubscribe_token.php`: NEU – Token-Erzeugung + -Verifikation
- `pages/unsubscribe.php`: NEU – die 1-Klick-Abmelde-Seite
- `views/unsubscribe.php`: NEU – PHP-View mit 3 Status-Varianten

#### Keine Migration nötig
Das Forum-Setting `unsubscribe_secret` wird beim ersten Bedarf automatisch
in die `settings`-Tabelle geschrieben (via `unsubscribe_secret()`-Funktion).
Idempotent: existiert es schon, wird's wiederverwendet.

## v0.18.43 (2026-05-26)

### 💄 Hotfix: Hässlicher Focus-Rahmen auf Passwort-Toggle weg

Beim Klick auf den 👁-Button bei Passwort-Feldern (Login, Reset, Register,
Settings) erschien ein blauer Outline-Rahmen um das Auge-Icon. Sieht
seltsam aus weil das Auge ja nur ein UI-Element ist, kein Eingabefeld.

#### Fix
Statt `:focus` nutze ich jetzt `:focus-visible`:
- Maus-Klick: kein Rahmen (was der User will)
- Tastatur-Tab: Rahmen bleibt (Barrierefreiheit für Keyboard-Navigation)

`:focus-visible` ist der moderne CSS-Selektor genau für diesen Fall —
unterstützt seit allen halbwegs aktuellen Browsern.

```css
.password-toggle-btn:focus { outline: none; }
.password-toggle-btn:focus-visible { outline: 2px solid var(--color-accent); }
```

## v0.18.42 (2026-05-26)

### 🏷️ Default-Tags + Tag-Nav minimal

#### Default-Tags wiederhergestellt
Im install.sql wurden bisher **keine** Default-Tags angelegt – Tags mussten
manuell im Admin-Panel erstellt werden. Bei einigen Installationen (Test-
Forum, frischer Install) wurden sie nie angelegt, was dazu führte dass
bei der Thread-Erstellung **keine Tags zur Auswahl standen**.

Das wurde jetzt behoben:
- `install.sql` enthält die 6 typischen Forum-Tags als INSERT IGNORE
- `migrate_v18_42.php` legt sie idempotent in bestehenden Installationen an

Die 6 Tags entsprechen den Werten auf dem Live-Forum:

| slug         | name        | description | color   |
|--------------|-------------|:-----------:|---------|
| frage        | Frage       | ❓          | #3b82f6 |
| anleitung    | Anleitung   | 📖          | #10b981 |
| bug          | Bug         | 🐛          | #ef4444 |
| idee         | Idee        | 💡          | #f59e0b |
| ankuendigung | Ankündigung | 📣          | #8b5cf6 |
| geloest      | Gelöst      | ✅          | #22c55e |

#### Idempotenz
INSERT IGNORE auf UNIQUE-Index (slug): bestehende Tags werden nicht
überschrieben. Falls ein Admin Namen oder Farben angepasst hat, bleiben
diese Anpassungen erhalten. Auf dem Live-Forum wo alle 6 bereits da sind
macht die Migration nichts.

#### Tag-Header-Link entfernt (minimaler Approach)
Der prominente „🏷️ Tags"-Eintrag in der Hauptnavigation wurde entfernt.
Tag-Funktionalität bleibt vollständig erhalten:
- Tag-Chips unter Threads bleiben sichtbar + klickbar
- /tag.php?slug=X funktioniert weiterhin (Filter nach Tag)
- /tag.php (ohne slug) zeigt weiterhin die Tag-Übersicht
- Tag-Verwaltung im Admin unverändert

Hintergrund: Tags sind eine Information am Thread, keine eigene Top-Level-
Navigation. Die Übersichts-Seite ist weiterhin erreichbar – nur nicht mehr
prominent in der Haupt-Nav.

## v0.18.41 (2026-05-26)

### 🐛 Hotfix: /tag.php zeigte Fatal Error

Beim Aufruf von /tag.php (Tag-Übersicht) gab es einen Fatal-Error:
`Uncaught TypeError: Unsupported operand types: array | bool`

#### Ursache
In `views_new/tag_index.tpl.html` Zeile 4 stand:
```
{% if tags|length == 0 %}
```

Genau die gleiche Falle wie bei v0.18.34 schon mal: Pipe-Filter (`|length`)
funktionieren NUR in `{{ }}`-Ausgabe-Blöcken, nicht in `{% %}`-Direktiven.
Dort interpretiert die Engine das `|` als bitwise OR (PHP-Operator).

#### Fix
`{% if tags|length == 0 %}` → `{% if count(tags) == 0 %}`

PHP-Funktionen können direkt in Direktiven aufgerufen werden.

#### Audit
Ich habe alle 17 Templates in views_new/ nach diesem Pattern durchgescannt:
- Nur diese eine Stelle war noch betroffen
- Alle anderen sind sauber

Wenn ihr beim Aufruf von /tag.php den Fehler gesehen habt: jetzt behoben.

## v0.18.40 (2026-05-26)

### 👁 Passwort-Toggle für Login + Reset (Symmetrie zu Register)

Damit das Passwort-Auge-Pattern überall konsistent ist:

#### Login (`/login.php`)
- 👁 Toggle am Passwort-Feld (1 Feld)
- Keine Stärke-Anzeige (Login ≠ neues Passwort setzen)

#### Reset (`/reset.php`) – wenn User auf den Forgot-Mail-Link klickt
Wird wie Register behandelt weil hier ein **neues** Passwort gesetzt wird:
- 👁 Toggle an beiden Passwort-Feldern
- 📊 Passwort-Stärke-Anzeige (Live-Balken, 4 Stufen)
- ✓/✗ Match-Hinweis bei Passwort-Wiederholung

#### Forgot (`/forgot.php`) – bewusst NICHT angefasst
Hier gibt es kein verstecktes Passwort-Feld:
- E-Mail-Adresse (sichtbar)
- 2FA-Code (sichtbar, sonst Tippfehler unsichtbar)
- Backup-Code (sichtbar)

Ein Toggle würde keinen Mehrwert bieten.

#### Technisch
- views_new/login.tpl.html: Toggle eingebaut
- views_new/reset.tpl.html: Komplett überarbeitet mit Stärke + Match + Toggle
- views/login.php, views/reset.php: PHP-Fallbacks ebenfalls aktualisiert
- Keine neuen CSS-Klassen nötig – nutzt die in v0.18.36 eingeführten
  (.password-input-wrap, .password-toggle-btn, .password-strength-*, .password-match-hint)

## v0.18.39 (2026-05-26)

### 🐛 Hotfix: Migrations-Verlauf zeigt jetzt korrekte Beschreibungen

Im Admin → System → 🚀 Updates → "Verlauf" wurden bei einigen Einträgen
nur die Roh-Versions-IDs angezeigt (z.B. "v18_31", "v18_36", "v18_38")
statt der schönen beschreibenden Titel.

#### Ursache
Die Migrations-Tabelle speichert die Beschreibung als String mit. Bei
einigen Versionen war das `migration_title()`-Mapping zum Ausführungs-
Zeitpunkt noch nicht im Code – also wurde nur die Roh-ID gespeichert.
Nachträgliches Mapping-Ergänzen half nicht, da die DB-Werte nicht
aktualisiert wurden.

#### Fix
`executed_migrations()` in `lib/migrations.php` zieht jetzt den Titel
**vorrangig aus dem aktuellen Code-Mapping** und nutzt den DB-Namen nur
als Fallback. So aktualisieren sich Titel **rückwirkend** wenn das
Mapping verbessert wird, ohne dass die Migration neu ausgeführt werden
muss (was eh nicht ginge).

Effekt: Die alten Verlauf-Einträge zeigen ab sofort die richtigen
beschreibenden Titel.

## v0.18.38 (2026-05-26)

### 🤖 Bot-Verkehr-Log + Stealth-Bot-Heuristik

Neue DB-Tabelle + Admin-UI um zu sehen welche Bots das Forum besucht haben.
Plus Heuristik für „Stealth-Bots" (Crawler die sich als normaler Browser tarnen).

#### Bot-Visits-Tabelle (`bot_visits`)
Pro (Bot-Name + IP)-Kombination eine Zeile mit aggregierten Stats:
- `visit_count` – wie oft gesehen
- `first_seen` / `last_seen` – Zeitfenster
- `last_url` – auf welcher Seite zuletzt
- `category` – 'search' | 'ai' | 'seo' | 'social' | 'monitoring' |
                'archive' | 'suspect' | 'generic'

#### Stealth-Bot-Heuristik
Wenn `detect_bot()` (User-Agent-Match) nichts findet, prüft die neue
`detect_stealth_bot()`-Funktion:
- Gibt es mehrere parallele Sessions von dieser IP (≥ 3)?
- Alle nicht eingeloggt?
→ Falls ja: Eintrag mit category='suspect' und Icon 🕵️

False-Positives möglich (Firmen-NAT) aber unwahrscheinlich da meistens
mindestens 1 User eingeloggt wäre.

#### Bot-Kategorien
40+ vordefinierte Bots in `detect_bot()` – jeder hat jetzt eine Kategorie:
- 🔍 search: Google, Bing, DuckDuckGo, Yandex, Baidu, Apple, Sogou
- 🤖 ai: GPTBot, ClaudeBot, PerplexityBot, AmazonBot, CCBot, etc.
- 📊 seo: Ahrefs, Semrush, Moz (DotBot/RogerBot), Majestic
- 📱 social: Facebook, Twitter, LinkedIn, WhatsApp, Telegram, Discord, Slack
- 🛡️ monitoring: Pingdom, UptimeRobot, Site24x7
- 📦 archive: Internet Archive (archive.org, ia_archiver)
- 🤖 generic: alle anderen mit 'bot'/'crawler'/'spider' im UA

#### Admin-UI
**Admin → System → 🤖 Bot-Verkehr**

4 Filter-Tabs mit Counter-Badges:
- 🌐 Alle
- 🔍 Suchmaschinen
- 🤖 KI-Bots
- 🕵️ Verdächtige

Pro Eintrag: Bot-Name + Icon, Kategorie, IP, Besucher-Counter, letzter Besuch,
letzte Seite. Bei Verdächtigen wird zusätzlich der vollständige User-Agent
angezeigt. Quick-Actions: 🚫 IP sperren / 🔍 IP-Lookup.

#### Auto-Cleanup
Einträge älter als 90 Tage werden automatisch entfernt (gleiche
Cleanup-Routine wie für user_online, 5% pro Request).

#### Technisch
- DB: `bot_visits` mit UNIQUE-Key auf (bot_name, ip), UPSERT bei jedem Besuch
- `detect_bot()` Return-Format erweitert um `category`-Feld
- `track_online()` ruft `log_bot_visit()` auf wenn nicht eingeloggt
- `bot_log_stats()` / `bot_log_list()` als neue Helper in `lib/online.php`
- Migration v18_38 erstellt die Tabelle idempotent

## v0.18.37 (2026-05-26)

### 📖 Doku-Hotfix: Migrations bekommen ordentliche Doc-Blocks

Bei v0.18.31 und v0.18.36 habe ich die Migration-Files mit minimalen
Doc-Block-Kommentaren angelegt – nur eine Zeile Titel ohne die übliche
Erklärung was und warum.

Alle anderen Migrations (v14, v15, v16, v18_1, v18_24, etc.) haben mehrzeilige
Doc-Blocks die erklären:
- Was die Migration tut
- Warum (Hintergrund/Kontext)
- Welche Spalten/Settings betroffen sind
- Wo der Admin die neue Funktion findet
- Spezielle Hinweise (Defensiv-Code, Voraussetzungen)

Diese Konsistenz ist wichtig: wenn jemand (oder die nächste Iteration von mir)
in 6 Monaten den Code anschaut und sich fragt „was hat diese Migration eigentlich
gemacht und warum gibt es diese komische Spalte" – die Doc-Blocks beantworten
das ohne Git-History-Archäologie.

#### Was wurde verbessert
- migrate_v18_31.php: Vollständige Beschreibung der 7 neuen Spalten, Privacy-Stufen,
  Idempotenz-Hinweis, Verweis auf lib/profile_fields.php und Settings-UI
- migrate_v18_36.php: DSGVO-/TMG-Kontext, Idempotenz, Voraussetzungen
  (Nutzungsbedingungen-Page sollte ausgefüllt sein), Pfad zum Admin-Schalter

#### Was sich nicht ändert
- Keine Funktionalitäts-Änderungen
- Keine DB-Änderungen
- Migrations laufen wie bisher (Doc-Blocks sind reine Kommentare)

## v0.18.36 (2026-05-26)

### 📝 Registrierung UX-Upgrade

Drei UX-Verbesserungen nach Woltlab-Vorbild eingebaut:

#### 👁 Passwort-Anzeige-Toggle
Auge-Button neben beiden Passwort-Feldern. Klick wechselt zwischen
`type=password` und `type=text`. Hilfreich vor allem auf Mobile gegen
Tippfehler. Per `tabindex=-1` aus dem Tab-Flow ausgenommen (User springt
nicht aus Versehen drauf).

#### 📊 Passwort-Stärke-Anzeige (live)
Balken unter dem Passwort-Feld der live aktualisiert wird während der
User tippt. Vier Stufen:
- 🔴 Schwach (≤2 Punkte)
- 🟠 Mittelmäßig (3 Punkte)
- 🟢 Gut (4 Punkte)
- 🟢 Stark (5–6 Punkte)

Punkte werden vergeben für: Länge ≥6 / ≥10 / ≥14, Groß+Klein-Mix,
Zahlen, Sonderzeichen. Reines JavaScript, kein Server-Roundtrip.

Plus: Live-Vergleich der zwei Passwort-Felder mit ✓/✗-Hinweis.

#### 📜 AGB-Checkbox mit Pflicht-Bestätigung
Neue Checkbox „Ich habe die [Nutzungsbedingungen] gelesen und akzeptiere sie."
Verlinkt auf die existierende `/terms.php`-Seite (öffnet in neuem Tab).
Server-seitige Validierung – ohne Häkchen geht's nicht weiter.
DSGVO-/TMG-konform.

#### ⚙️ Neue Admin-Einstellung
**Admin → System → Einstellungen → Registrierung → „Zustimmung zu Nutzungsbedingungen erforderlich"**

Default: aktiviert. Kann ausgeschaltet werden (z.B. für Test-Foren).

### Technische Details
- DB: Neues Setting `registration_terms_required` (Default `'1'`)
- Migration v18_36 fügt das Setting in bestehenden Installationen hinzu
- Template `views_new/register.tpl.html` aktualisiert
- PHP-Fallback `views/register.php` ebenfalls aktualisiert (1:1 Funktionalität)
- CSS: neue Klassen `.password-input-wrap`, `.password-toggle-btn`,
  `.password-strength-*`, `.password-match-hint`, `.terms-row`

### Nicht übernommen (bewusst):
- reCAPTCHA von Google – wir haben besseres eigenes CAPTCHA
- Mehrsprachigkeit – in v0.18.35 aus der UI entfernt
- Inhaltssprachen-Filter – über-engineered für ein Forum

## v0.18.35 (2026-05-26)

### 🧩 Settings als Template + Mehrsprachigkeit aus UI entfernt

#### `views_new/settings.tpl.html`
Symmetrie zum Profil-Template. Alle 5 Tabs (Profil/Sicherheit/Sessions/Darstellung/Konto)
laufen jetzt durch die Template-Engine. PHP-Fallback `views/settings.php` bleibt
als Sicherheitsnetz unverändert.

**Lessons aus heute Morgen angewendet:**
- Filter (`|raw`, `|length`, `|date`, `|default`) nur in `{{ }}`
- In `{% if %}` / `{% set %}` / `{% for %}`: direkt PHP-Funktionen (`count()`, etc.)
- `{{ url('/path') }}` statt `{{ FORUM_URL }}/path`
- `{# Twig-Kommentare #}` werden von der Engine erkannt

**Neue Helper-Funktionen in `lib/profile_fields.php`:**
- `render_profile_field_inputs($profile, $privacy)` – generiert die 6 Profil-Feld-Inputs
  als HTML-String. Notwendig weil die Engine keinen `loop.key` hat und ein
  `foreach ($schema as $key => $def)` im Template nicht abbildbar ist.
- `can_user_delete_self_ok(int $userId): bool` – Wrapper um
  `can_user_delete_self()` das ein Array zurückgibt; im Template-Kontext praktischer.
- `join_codes(array $codes): string` – Backup-Codes für Clipboard escapen.

### 🌍 Mehrsprachigkeit aus UI entfernt (TODO #3)

Die Mehrsprachigkeits-Infrastruktur war halb-fertig: `lang/de.php` und
`lang/en.php` haben je 47 Übersetzungs-Keys, aber 99% des Codes hat deutsche
Strings hardcoded. Englisch-Umschaltung bewirkte praktisch nichts.

#### Was geändert wurde
- 🚫 Sprach-Toggle aus User-Dropdown entfernt
- 🚫 Sprach-Toggle aus Gast-Header-Icons entfernt
- 🚫 Sprach-Auswahl aus Settings „Darstellung"-Tab entfernt
- 🔒 `<html lang>` fest auf `de`
- 🔒 Save-Handler in `settings.php` ignoriert `language`-POST, setzt immer `de`

#### Was bleibt
Die Infrastruktur (`lib/lang.php`, `lang/de.php`, `lang/en.php`, `t()`-Funktion,
`current_language()`) bleibt im Code. Falls Mehrsprachigkeit irgendwann ordentlich
nachgezogen wird (mehrere Tage Arbeit alle Hardcoded-Strings durch `t()` zu ersetzen),
ist das Fundament noch da. Bis dahin: ehrliche, deutsche UI.

## v0.18.34 (2026-05-26)

### 🐛 Hotfix: Profile-Template hatte Pipe-Filter im if-Kontext

Fatal Error nach Upload von v0.18.33:
```
TypeError: Unsupported operand types: array | bool
in cache/templates/profile.php:144
```

#### Ursache
In `views_new/profile.tpl.html` stand:
```twig
{% if ipHistory|length <= 10 %}
```
Der `|length`-Filter funktioniert **nur in `{{ }}`-Output-Ausdrücken**.
In `{% if %}`-Direktiven kompiliert `tpl_compile_expression()` das `|`
als bitweisen OR-Operator – `$ipHistory | $length` ist dann `array | undefined`,
was PHP zur Recht ablehnt.

#### Fix
Innerhalb von `{% %}`-Blöcken nutzen wir jetzt `count()` direkt:
```twig
{% if count(ipHistory) <= 10 %}
{% if count(ipHistory) > 10 %}
```
Innerhalb von `{{ }}` darf `{{ ipHistory|length }}` weiterhin verwendet werden.

#### Lesson für zukünftige Templates
**`| filter` nur in `{{ }}`, nie in `{% if %}` / `{% for %}` / `{% set %}`**.
Im Direktiv-Kontext: direkt PHP-Funktionen aufrufen (`count()`, `strlen()`, etc.).

## v0.18.33 (2026-05-26)

### 🧩 Profil-Seite als Template (endlich!)

Versprochen in v0.18.30, vergessen in v0.18.31, eingelöst in v0.18.33.
`views_new/profile.tpl.html` ist da. Die Template-Engine konnte tatsächlich
schon viel mehr als ich anfangs angenommen hatte – PHP-Funktionsaufrufe
funktionieren direkt im Template (`{{ format_date(profile.last_login) }}`
wird via Pattern-Erkennung in `tpl_compile_expression` korrekt kompiliert).

#### Was im Template steckt
- Banner + Hero (Avatar, Username, Online-Dot, Badges)
- 4-Card Stats-Grid (Beiträge / Reaktionen / Achievements / Punkte)
- Meta-Zeile (Dabei seit · Zuletzt aktiv)
- Action-Buttons (PN senden / Profil bearbeiten)
- Erweiterte Profil-Felder mit Privacy-Filter
- Signatur mit BBCode-Rendering
- Letzte Beiträge mit Empty-State-Fallback
- IP-Verlauf (Mods only, ausklappbar, Lazy-Load über 10 Einträge)

#### Template-Features die genutzt werden
- `{{ funktion(arg) }}` – direkter PHP-Funktionsaufruf
- `{{ x | raw }}` – HTML-Output ohne Escape
- `{{ x | date('d.m.Y') }}` / `{{ x | escape }}` / `{{ x | length }}` / `{{ x | default('–') }}`
- `{% if / elseif / else / endif %}`
- `{% for x in list %}` mit `loop.index`, `loop.index0`, `loop.length`
- `{% set var = expr %}`

#### PHP-Fallback bleibt
`views/profile.php` existiert weiterhin als Sicherheitsnetz. Wenn das
Template fehlt oder bricht, fällt die Engine automatisch auf PHP zurück.
Im Admin-Template-Editor kann das Template später angepasst werden.

#### Hinweis für nächstes Mal
`url('/path')` statt `{{ FORUM_URL }}/path` nutzen – `FORUM_URL` ist eine
PHP-Konstante, nicht als Template-Variable verfügbar.

## v0.18.32 (2026-05-26)

### 🎨 Hotfix: Profil-Felder-Formular hatte kaputtes Styling

In v0.18.31 sahen die neuen Profil-Felder im Settings-Tab nicht zum Forum
passend aus: weiße Default-Inputs ohne Padding/Border, „Sichtbar für"-Dropdown
schwebte unter den Feldern statt daneben, fehlende visuelle Trennung.

#### Ursachen
1. Mein neuer Container hieß `.profile-field-input` — die globalen
   Forum-Input-Styles greifen nur auf `.form-row input[...]`
2. `input[type=date]` war gar nicht in der globalen Selector-Liste
3. Layout-Grid kollabierte ohne klare Spalten

#### Fix
- Container nutzt jetzt `.form-row` als Basis (Standard-Styling greift)
- `input[type=date]` zur globalen Selector-Liste hinzugefügt
- Grid mit `align-items:end` für saubere Bottom-Alignment
- Date-Picker-Icon im Dark-Mode invertiert (sonst weiß auf hellem Hintergrund)
- Privacy-Dropdown sichtbar daneben, nicht darunter

## v0.18.31 (2026-05-26)

### 👤 Erweiterte Profil-Felder mit Privacy-Toggles

6 neue Felder im Profil – jedes mit individuellem Sichtbarkeits-Toggle:

| Feld | Typ |
|---|---|
| 🚻 Geschlecht | Dropdown (Weiblich/Männlich/Divers/Sonstige) |
| 🎂 Geburtstag | Date-Picker |
| 📍 Wohnort | Freitext (100 Zeichen) |
| 💼 Beruf | Freitext (100 Zeichen) |
| 🎯 Interessen | Freitext (500 Zeichen) |
| 📝 Über mich | Textarea mit BBCode-Unterstützung |

### 🔐 Privacy-Toggles pro Feld

Drei Sichtbarkeits-Stufen pro Feld einzeln einstellbar:
- **🌐 Öffentlich** – jeder sieht das Feld (auch nicht-eingeloggte Besucher)
- **🔒 Nur Mitglieder** – nur eingeloggte User sehen das Feld
- **🔐 Nur ich** – nur du selbst (Admins sehen es ebenfalls, für Moderation)

Standard: alles öffentlich.

### 🛠️ Architektur

#### Neue Library `lib/profile_fields.php`
Zentrale Schema-Definition – wenn ein Feld geändert/hinzugefügt wird, nur **eine**
Stelle anfassen. Funktionen:
- `profile_field_schema()` – Definition aller Felder
- `profile_privacy_settings($user)` – Privacy-Map auslesen (JSON deserialisieren)
- `profile_field_is_visible($key, $profileUser, $viewer)` – Sichtbarkeits-Logik
- `profile_visible_fields($profileUser, $viewer)` – komplett gefilterte Liste für Views

#### DB-Schema
7 neue Spalten in `users`:
- `profile_gender` VARCHAR(50)
- `profile_birthday` DATE
- `profile_location` VARCHAR(100)
- `profile_occupation` VARCHAR(100)
- `profile_interests` VARCHAR(500)
- `profile_about` TEXT
- `profile_privacy` TEXT (JSON-Map)

#### Defensiv-Logik
Save-Handler in `settings.php` hat **3-stufigen Fallback**:
1. Mit neuen Spalten speichern
2. Wenn Migration noch nicht gelaufen: ohne neue Spalten
3. Wenn sehr altes Schema: minimaler Fallback
Verhindert das Production-Outage-Szenario von gestern (is_approved).

### 🎨 Anzeige
- Im Settings-Tab „👤 Profil": jedes Feld mit Privacy-Dropdown daneben
- Im Profil: neue Sektion „👤 Über [Username]" als 2-Spalten-Grid
- „Über mich" und „Interessen" spannen die volle Breite (sind lang)
- BBCode wird im „Über mich"-Feld gerendert
- Leere Felder werden nicht angezeigt (kein „Geschlecht: nicht angegeben")
- Mobile: 1-Spalten-Layout

## v0.18.30 (2026-05-26)

### ✨ Profil-Seite aufgehübscht

#### Stats-Grid statt linearer Liste
Statistiken werden jetzt in einem 4-Spalten-Grid angezeigt:
- 📝 Beiträge · 👍 Reaktionen · 🏆 Achievements · ⭐ Punkte
Jede Stat in einer eigenen Card mit Icon + Zahl + Label.
Auf Mobile: 2×2-Grid.
Achievements-Card ist klickbar (Hover-Effekt).

#### Bessere Hierarchie im Hero
- Username + Badge in einer Zeile (visuell verbunden)
- Meta-Infos (Dabei seit / Zuletzt aktiv) als eine kleine Zeile statt mehrerer Stat-Lines
- Action-Buttons sauber gruppiert am Ende

### 🔍 IP-Verlauf umgebaut (nur Mods/Admins)

- **Position**: war zwischen Hero und „Letzte Beiträge" → jetzt ganz am Ende der Seite
- **Ausklappbar**: standardmäßig zugeklappt (außer wenn ≤10 Einträge)
- **Lazy-Load**: bei mehr als 10 Einträgen werden nur die neuesten 10 sofort gezeigt,
  Button „weitere anzeigen" enthüllt den Rest
- **Visuell zurückhaltender**: dezenter Header mit Counter, gestrichelter Trennstrich oben

### 🤔 Was NICHT umgesetzt wurde
- **Templates für Profil/Settings**: zu logiklastig (viele PHP-Funktionsaufrufe wie
  `online_dot`, `rank_badge`, `bbcode`). Würde umfangreichere Engine-Erweiterung
  erfordern → für einen späteren Release wenn wir die Template-Engine ausbauen.
- **Neue Profil-Felder mit Privacy-Toggles**: kommt als v0.18.31 mit DB-Migration.

## v0.18.29 (2026-05-26)

### 🐛 Hotfix: Parse-Error in views/settings.php

In v0.18.28 wurden Backslashes im PHP-Code falsch escaped, was zu einem
Parse-Error führte:
```
syntax error, unexpected token "\" in views/settings.php on line 152
```

Konkret: `implode(\"\\n\", ...)` statt `implode("\n", ...)` —
ein Tool-Artefakt beim Generieren des Codes.

Fix: Backslashes sauber geschrieben.

## v0.18.28 (2026-05-26)

### 🔐 2FA komplett in Settings integriert
Vorher war 2FA über drei verschiedene Stellen erreichbar:
- User-Menü-Dropdown
- Profil-Hero-Button
- Eigene Setup-Seite `/twofactor_setup.php`

Jetzt **eine** klare Stelle: **Settings → Sicherheit**.

#### Was sich ändert
- 🔐 Settings-Tab „Sicherheit" hat jetzt den kompletten 2FA-Setup-Flow integriert:
  QR-Code, Secret-Eingabe, Code-Bestätigung, Backup-Codes-Anzeige
- 📌 Backup-Codes werden nach Aktivierung in einer **hervorgehobenen Box** angezeigt
  mit „Codes kopieren"-Button (Clipboard-API)
- 🔓 Deaktivieren erfordert Passwort-Bestätigung (wie bisher)
- 🚪 `/twofactor_setup.php` ist jetzt ein Redirect auf `/settings.php?tab=security`
  (für alte Bookmarks)
- ❌ Doppelter Link aus User-Dropdown entfernt
- ❌ Doppelter Link aus Profil-Hero entfernt
- 🗑️ `views/twofactor_setup.php` gelöscht (nicht mehr verwendet)

#### Konsistenzgewinn
Alles rund ums Konto an einem Ort: Profil-Felder, E-Mail, Passwort, 2FA, Sessions,
Theme, Account-Löschung. Kein Hin-und-Her zwischen verschiedenen Seiten mehr.

## v0.18.27 (2026-05-26)

### ⚙️ Profil und Einstellungen sauber getrennt

Bisher waren „Mein Profil" und „Einstellungen" technisch dieselbe Seite
(`profile.php?id=X` vs `profile.php?id=X&edit=1`). Die Edit-Felder waren
unten ans öffentliche Profil drangehängt – verwirrend und unprofessionell.

#### Neu
- **`/profile.php`** = reine Anzeige-Seite (read-only)
  - Zeigt Avatar, Banner, Stats, letzte Beiträge
  - User sieht sein eigenes Profil **identisch wie andere User es sehen**
  - Bei eigenem Profil: prominenter Button **„⚙️ Profil bearbeiten"** oben
- **`/settings.php`** = neue Bearbeitungs-Seite
  - Tab-basierte Navigation: 👤 Profil · 🔐 Sicherheit · 💻 Sitzungen · 🎨 Darstellung · ⚠️ Konto
  - Klare Trennung: Avatar/Banner/Bio · E-Mail/Passwort/2FA · Aktive Sessions · Theme/Sprache · Account-Löschung
  - Speichert tab-spezifisch, kommt nach Submit auf den gleichen Tab zurück

#### Kompatibilität
- Alte Links `?id=X&edit=1` werden automatisch auf `/settings.php` umgeleitet
- Header-Nav-Links aktualisiert
- Online-Tracker erkennt „Einstellungen" jetzt als eigene Seite

### Bemerkung
Dieser Umbau ist Vorbereitung für v0.18.28+ – dort kommen erweiterte
Profil-Felder (Geschlecht, Geburtstag, etc.) mit Privacy-Toggles pro Feld
in den 👤 Profil-Tab.

## v0.18.26 (2026-05-25)

### 🐛 Bugfix: Invite-Code wurde nicht erkannt
Zwei Bugs in einem:

#### Bug 1: `db_execute()` falsch interpretiert
Die Helper-Funktion `db_execute()` gibt `lastInsertId()` zurück, was bei
UPDATE-/DELETE-Statements immer 0 ist. `invitation_consume()` hatte:
```php
$affected = db_execute('UPDATE ...');
return $affected > 0;  // immer FALSE
```
Resultat: UPDATE lief erfolgreich durch (Code in DB als „eingelöst" markiert),
aber Return-Wert war `false` → `$skipApproval` blieb `false` → User landete
in der Approval-Queue.

**Fix**: Direkter `PDO::prepare()` + `rowCount()` für UPDATE/DELETE.
Gleicher Bug auch in `invitations_cleanup()` gefixt.

#### Bug 2: SQL-Alias `user` problematisch
Im JOIN für `invitations_list()` wurde der Alias `user` verwendet:
```sql
LEFT JOIN users user ON user.user_id = i.used_by
```
`user` ist in MySQL 8+ teilweise reserviert. Der JOIN konnte fehlschlagen,
der Username erschien als `?`.

**Fix**: Alias umbenannt zu `consumer`.

## v0.18.25 (2026-05-25)

### ✉️ Einladungs-System für Registrierungen
Komplement zum Admin-Approval: Wer einen gültigen Einladungs-Code mitbringt
umgeht die Freigabe-Queue komplett.

#### Workflow
1. Admin → 👥 Benutzer → ✉️ Einladungen → Code erstellen
   - Optional E-Mail-Adresse (bindet Code an Adresse)
   - Optional interne Notiz
   - Gültigkeit (default 7 Tage)
   - Optional: Mail direkt versenden mit Code + Link
2. User klickt auf Link `register.php?invite=XXXX-XXXX` (oder gibt Code manuell ein)
3. Bei gültigem Code: Account wird sofort freigeschaltet (is_approved=1)
4. Code wird als „verbraucht" markiert (one-shot)

#### Sicherheits-Eigenschaften
- **One-shot**: Jeder Code nur einmal verwendbar
- **Ablaufdatum**: Codes werden nach Frist ungültig (default 7 Tage)
- **Widerrufbar**: Admin kann ungenutzte Codes invalidieren
- **E-Mail-Bindung optional**: Codes können an spezifische Adresse gebunden werden
- **Stille Ignorierung ungültiger Codes**: Brute-Force-Schutz – falsche Codes
  führen User in die normale Queue, ohne Auskunft warum
- **Format**: `XXXX-XXXX` mit 8 Zeichen, ohne I/O/0/1/L (Verwechslungsschutz)

#### Admin-Sichtbarkeit
- Counter-Badge in der Nav (Anzahl aktiver Codes)
- Status-Anzeige pro Code: 🟢 aktiv, ✓ eingelöst, ⏰ abgelaufen, 🚫 widerrufen
- Wer hat eingeladen, wann verbraucht, von wem
- Mod-Log dokumentiert alles

### 🛢️ DB-Schema
- Neue Tabelle `invitations` (40 Tabellen total)
- Migration `v18_25`

### 🐛 Bugfix: Defensive Schreibweise in auth.php
`current_user()` und `login_user()` haben einen Fallback wenn die `is_approved`-
Spalte noch nicht existiert (für sicheres Update-Verhalten ohne Production-Outage).
**Lehre aus heute Abend**: Wenn neue Spalten verwendet werden, MUSS Code
defensiv damit umgehen können – sonst stürzt das Forum ab wenn die Files
hochgeladen sind, aber die Migration noch nicht gelaufen ist.

## v0.18.24 (2026-05-25)

### 🆕 Admin-Approval für Registrierungen
Neue Setting im Admin-Bereich: **„Admin-Freigabe für neue Registrierungen erforderlich"**.

Wenn aktiv, müssen neue Accounts vom Admin-Team freigeschaltet werden, bevor
sie sich einloggen können. Ideal für Test-/Privat-Foren oder Anti-Bot-Schutz.

#### Workflow
1. User registriert sich → Account angelegt mit `is_approved = 0`
2. Alle Admins/Owner bekommen E-Mail-Benachrichtigung
3. Im Admin → 👥 Benutzer → 🆕 Reg.-Freigaben (mit Counter-Badge):
   - **✓ Freigeben**: User bekommt „Konto freigeschaltet"-Mail, kann sich einloggen
   - **❌ Ablehnen**: Mit Pflicht-Begründung, User bekommt Begründungs-Mail,
     Account wird gelöscht (Username/E-Mail werden wieder frei)
4. Mod-Log dokumentiert jede Aktion (wer hat wann wen freigegeben/abgelehnt)

#### Edge-Cases
- Kombiniert mit E-Mail-Verifikation: User muss erst E-Mail bestätigen, dann
  wartet er noch auf Admin-Freigabe. Klare Flash-Messages erklären das.
- `current_user()` und `login_user()` prüfen `is_approved` – auch via
  Remember-Me-Token gibt's keinen Bypass.
- Owner/Admins können nicht versehentlich abgelehnt werden.

### 🛢️ DB-Schema
- `users.is_approved` (TINYINT, default 1)
- `users.approval_note` (VARCHAR 500)
- Index `idx_is_approved` (für schnelle Pending-Liste)
- Bestehende User: alle auf `is_approved = 1` (kein Lockout)
- Migration `v18_24` führt die Änderungen durch

## v0.18.23 (2026-05-25)

### 🐛 SMTP: HTML-Mails wurden bei manchen Clients (z.B. GMX) als Text angezeigt

Body-Encoding war auf `quoted-printable` gestellt – das fügt bei langen
Zeilen (Inline-CSS in HTML-Mails!) Soft-Linebreaks ein, die einige Mail-Clients
nicht korrekt interpretieren. Resultat: HTML-Header der Mail wurde als
Plain-Text angezeigt statt gerendert.

### Fix
Umgestellt auf **Base64-Encoding** (RFC 2045):
- Keine Zeilenlängen-Probleme mehr
- Saubere UTF-8-Übertragung
- Von allen Mail-Clients korrekt dekodiert (GMX, Gmail, Outlook, Apple Mail)

## v0.18.22 (2026-05-25)

### 🔧 SMTP: Port 2525 als Fallback hinzugefügt
Wenn Hoster Port 587 outbound blockiert (Anti-Spam-Schutz),
ist Port 2525 oft eine Alternative. Brevo und die meisten anderen
SMTP-Relays bieten beide Ports an. Im Dropdown jetzt verfügbar:

- 🔒 STARTTLS · Port 587 (empfohlen)
- 🔐 SSL/TLS · Port 465
- 🔒 STARTTLS · Port 2525 (falls 587 blockiert)
- ⚠️ Unverschlüsselt · Port 25

## v0.18.21 (2026-05-25)

### 🎨 SMTP-Einstellungen vereinfacht
Port-Feld und Verschlüsselungs-Dropdown waren doppelt gemoppelt – ein
gemeinsames „Verbindung"-Dropdown reicht aus:

- **🔒 STARTTLS · Port 587** (empfohlen für 99% aller Provider)
- **🔐 SSL/TLS · Port 465**
- **⚠️ Unverschlüsselt · Port 25** (unsicher)

Hinter den Kulissen werden `smtp_port` und `smtp_encryption` automatisch
gesetzt – kein Risiko mehr für ungültige Kombinationen wie „Port 587 + SSL".

## v0.18.20 (2026-05-25)

### 🐛 Template-Engine: `not`-Operator gefixt
Bug in der Template-Engine: `{% if not variable %}` produzierte
fehlerhaften PHP-Code (`not $variable` statt `!$variable`).

**Wurzel:** Die Post-Processing-Regex `'/ not /'` verlangte Leerzeichen
vor UND nach `not`. Nach dem Identifier-zu-Variable-Umschreiben stand aber
`not $variable` da – `$` ist kein Wort-Zeichen, also matched die Regex nicht.

**Fix:** Regex auf `'/not\s+/'` umgestellt (Word-Boundary vor `not`,
beliebige Whitespaces danach – egal was folgt).

### 🔁 forgot.tpl.html Workaround weg
Template nutzt jetzt sauber `{% if not sent %}` an einer Stelle die vorher
mit `{% else %}` umgangen wurde.

## v0.18.19 (2026-05-25)

### 🔑 Multi-Faktor-Recovery für Passwort-Vergessen
„Passwort vergessen?" bietet jetzt drei Wiederherstellungs-Methoden:

#### ✉️ E-Mail-Link (Standard, immer verfügbar)
Klassischer Weg wie bisher – Token-Link an gespeicherte E-Mail.

#### 🔐 2FA-Token (wenn User 2FA aktiv hat)
User gibt Benutzername + aktuellen 6-stelligen TOTP-Code ein.
Bei Erfolg: direkter Reset-Link auf der Seite, kein E-Mail-Umweg.
**Stärker als E-Mail** – auch bei kompromittiertem E-Mail-Account funktioniert nichts.

#### 🔑 Backup-Code (wenn User 2FA + Backup-Codes hat)
User gibt Benutzername + unverbrauchten Backup-Code ein.
Code wird nach Verwendung gelöscht (one-shot).
**Fallback wenn 2FA-Gerät verloren** – aber kein doppelter Code-Use möglich.

### Sicherheits-Design
- Tabs zeigen alle drei Methoden **immer** an (kein User-Enumeration über UI)
- Generische Fehlermeldungen bei 2FA/Backup („Benutzername oder Code nicht korrekt")
- Eigene Rate-Limits pro Methode: `forgot_email`, `forgot_2fa`, `forgot_backup`
- CAPTCHA-System (v0.18.16+) greift auch hier
- Backup-Code wird auch verbraucht wenn der danach folgende Reset abbricht (designed)

### Bewusste Design-Entscheidung
**Keine Sicherheitsfragen** – sind in der modernen Security-Welt als schwächer
als Passwörter selbst eingestuft (siehe AUDIT.md). Stattdessen 2FA + Backup-Codes.

## v0.18.18 (2026-05-25)

### 🤖 CAPTCHA für Register + Forgot-Password
Erweiterung des v0.18.16-Systems auf zwei weitere kritische Forms:

- **Registrierung**: Bot-Schwemme bei neuen Accounts wird abgewehrt
- **Passwort-vergessen**: Verhindert E-Mail-Bombing durch Bots

Alle drei Schutz-Schichten greifen jetzt überall:
- Schicht 1 (Honeypot) – bereits vorhanden
- Schicht 2 (Verhaltens-Token + Min-Submit-Zeit) – neu
- Schicht 3 (Mathe-CAPTCHA nach 3 Fehlversuchen) – neu

Damit sind alle drei wichtigsten öffentlichen Forms (Login/Register/Forgot)
geschützt. Counter werden pro Action separat geführt – jemand der bei Login
versagt, sieht trotzdem kein CAPTCHA beim Register (außer er versagt dort auch).

## v0.18.17 (2026-05-25)

### 🎨 CAPTCHA-Feinschliff
- Spinner-Pfeile beim Mathe-CAPTCHA-Input ausgeblendet
  (verhindert auch Brute-Force durch reines Hoch-/Runterklicken)
- Schöneres Styling für die CAPTCHA-Zeile (eigene Box mit Border)

## v0.18.16 (2026-05-25)

### 🤖 Adaptives CAPTCHA-System
Drei-Schichten-Verteidigung gegen Bots, komplett selbst gebaut – kein Google-reCAPTCHA.

#### Schicht 1: Honeypot (bestehend)
Versteckte Form-Felder die Bots typischerweise ausfüllen.

#### Schicht 2: Verhaltens-Token (NEU)
HMAC-signierter Token mit Zeitstempel. Min-Submit-Zeit verhindert Instant-Submits.
Versteckt im Hintergrund. Bots werden geblockt ohne dass User je was sehen.

#### Schicht 3: Mathe-CAPTCHA (NEU)
Erscheint nur bei Verdacht (z.B. nach 3 fehlgeschlagenen Login-Versuchen).
Einfache Mathe-Aufgabe (1-stellig + / − / ×). Lösung in Session, nie im HTML.
Token-basiert (one-shot, 10 Min gültig, max 5 offene pro Session).

### Admin-Settings
Neuer Bereich „🤖 CAPTCHA" in System → Einstellungen:
- An/Aus
- Schwellenwert (nach wie vielen Fehlversuchen erscheint CAPTCHA)
- Min. Submit-Zeit in ms

### DB-Schema
- Neue Tabelle `captcha_attempts` (IP-Hash + Action → Counter, mit Ablauf)
- Migration v18_16

### Integration
Vorerst nur in Login. Register/Forgot-Password folgen in v0.18.17+.
PHP-Fallback-View und Template-View beide aktualisiert.

### Sicherheit
- IP wird gehasht (SHA-256) – keine Klartext-IPs in DB
- Token via HMAC-SHA256 signiert – nicht fälschbar
- Auto-Cleanup abgelaufener Einträge

## v0.18.15 (2026-05-25)

### 🛡️ Security-Audit-Fixes (Phase 1)

Aus dem v0.18.14-Audit zwei Findings sofort gefixt:

#### F1: Session-Cookie mit `secure`-Flag
- Bei HTTPS-Verbindung wird das Session-Cookie nur noch via HTTPS übertragen
- Erkennung über `$_SERVER['HTTPS']` und `X-Forwarded-Proto` (Reverse-Proxy-tauglich)
- Schutz vor Session-Hijack in unsicheren Netzen

#### F2: `session_regenerate_id()` nach Login
- Beim erfolgreichen Login wird die Session-ID erneuert
- Schutz vor Session-Fixation-Angriffen
- Bricht potentielle Angriffe wo Angreifer eine Pre-Login-Session nutzen wollen

### 📋 Audit-Dokument
Neue Datei `AUDIT.md` im Forum-Root – komplette Befundliste mit Bewertung
und Roadmap für weitere Verbesserungen.

## v0.18.14 (2026-05-25)

### ✨ Sechs weitere Templates konvertiert
Template-Editor erhält frisches Futter zum Bearbeiten:

- `profile_deleted.tpl.html` – „Account gelöscht"-Anzeige
- `delete_account_blocked.tpl.html` – Sperr-Hinweis bei Lösch-Versuch
- `tag_index.tpl.html` – Tag-Übersichtsseite
- `forgot.tpl.html` – Passwort-vergessen Formular
- `reset.tpl.html` – Passwort-Reset-Formular
- `board_unlock.tpl.html` – Passwort-Eingabe für geschützte Foren

Damit sind **10 Templates** im Editor verfügbar (vorher 4).
PHP-Fallback bleibt aktiv – kein Risiko bei Problemen.

## v0.18.13 (2026-05-25)

### ✨ Backup-System im Admin
Neuer Bereich **System → 💾 Backups** – Sicherheitsnetz nach DSGVO und für den Notfall.

#### Drei Backup-Typen
- **💾 Nur Datenbank** – kompletter SQL-Dump aller Tabellen mit Daten
- **📁 Nur Dateien** – ZIP aller Forum-Dateien (außer cache/, backup/, config.php)
- **📦 Komplett** – DB-Dump + Dateien in einem ZIP

#### Features
- Optionale Notiz pro Backup (z.B. „vor v0.19 Update")
- Liste vorhandener Backups mit Typ, Größe, Datum
- Direkter Download über Admin (mit Auth-Check)
- Löschen einzelner Backups
- Gesamt-Speicherbedarf im Überblick

#### Sicherheits-Maßnahmen
- `backup/`-Verzeichnis mit `.htaccess` (Deny from all)
- Plus `index.html` als zusätzlicher Schutz
- Download nur über PHP mit Admin-Check (kein direkter Link auf Files)
- Filename-Validierung (kein Path-Traversal)
- `config.php` wird NICHT ins Backup aufgenommen (enthält Passwörter)

#### Voraussetzungen
- DB-Backup: keine zusätzlichen Extensions nötig
- Datei/Komplett-Backup: PHP ZipArchive-Extension (Hinweis im Admin wenn nicht da)

## v0.18.12 (2026-05-25)

### 🐛 Bugfix: Header-Style wurde nicht gespeichert
- `theme_header_style` fehlte in der Whitelist des `save_design`-Handlers
- Die Vorschau funktionierte (Live-JS), aber beim Speichern wurde der Wert ignoriert
- Nach Reload war der Header-Style wieder „Standard"
- Fix: `theme_header_style` zur `$themeKeys`-Whitelist hinzugefügt
- Default-Wert auch in `install.sql` ergänzt (für saubere Neuinstallationen)

## v0.18.11 (2026-05-25)

### 🐛 Bugfix: Header-Style „Zentriert" und „Kompakt"
Die CSS-Selektoren für die Header-Styles referenzierten nicht-existierende
Klassen (`.header-banner-logo`, `.header-banner-text`, `.header-banner-slogan`).
Sie wurden auf die tatsächlich vorhandenen Klassen umgestellt:
- `#forum-logo-banner` (ID-Selektor für Logo)
- `.header-banner .header-inner` (Container)
- `.header-slogan` (Slogan-Anzeige)

Auswirkung:
- „Zentriert" zentriert das Logo jetzt wirklich
- „Kompakt" verkleinert das Logo wieder korrekt (greift jetzt auch bei SVG)
- Beide Styles funktionieren für alle Logo-Typen (PNG, SVG, etc.)

## v0.18.10 (2026-05-25)

### 🔍 Stale-Override-Detection für Template-Editor
- Template-Editor erkennt jetzt wenn das Datei-Original seit dem letzten Save geändert wurde
- Bei veralteten Overrides: gelbes Warn-Banner mit drei Aktionsoptionen
- **Diff-Anzeige**: Line-by-Line was sich am Original geändert hat (LCS-Algorithmus)
- Drei Buttons im Banner:
  - „✓ Mein Override behalten" – akzeptiert den neuen Original-Stand ohne Override zu ändern
  - „🔄 Override neu starten" – löscht Override, beginnt mit neuem Original
  - „🔍 Original-Änderungen anzeigen" – zeigt das Diff
- Template-Liste zeigt zusätzliches Badge „⚠️ Veraltet"

### DB-Schema
- Neue Spalte `template_overrides.original_at_save` (LONGTEXT)
- Migration `v18_10` füllt die Spalte für bestehende Overrides mit aktuellem Datei-Inhalt (Baseline)

### Warum?
Vorher: Wenn ein Update die Original-Datei änderte, blieb der DB-Override stumm aktiv – Admin konnte
neue Features/Bugfixes im Original übersehen. Klassisches WordPress-Theme-Problem.
Jetzt: Bewusste Entscheidung pro Override bei Update-Konflikten.

## v0.18.9 (2026-05-25)

### ✨ Cache-Verwaltung im Admin
- Neue Sektion in **System → 🛠️ Wartung**: „🗑️ Cache leeren"
- Statistik: Anzahl & Größe der Template-Cache-Dateien
- Zwei Buttons:
  - „Template-Cache leeren" (nur compiled Templates)
  - „Alle Caches leeren" (Templates + in-memory)
- Nützlich nach manuellen Template-Änderungen oder bei seltsamen Render-Bugs

## v0.18.8 (2026-05-25)

### ✨ Drei weitere Templates auf Engine umgestellt
- `register.tpl.html` – Registrierungsformular (sehen alle neuen User)
- `verify.tpl.html` – E-Mail-Bestätigungsseite
- `error.tpl.html` – Fehlerseite (Fallback bei Routing-Fehlern)

### Vorteile
- Diese Templates erscheinen jetzt im Admin-Template-Editor (v0.18.7)
- Können live im Browser angepasst werden – ohne FTP, ohne PHP-Risiken
- Original-Dateien bleiben unverändert (Override-System)

### Engine-Verhalten
Bestehendes PHP-Fallback unverändert – Templates ohne `.tpl.html` werden
weiter aus `views/` als PHP geladen. Module bleiben komplett unangetastet.

## v0.18.7 (2026-05-25)

### ✨ Template-Editor im Admin
- Neuer Bereich **System → 🖼️ Templates**
- Liste aller `.tpl.html`-Templates in `views_new/`
- Status pro Template: „📄 Original" oder „✏️ Angepasst"
- Editor mit **CodeMirror 5** (Syntax-Highlighting, Dracula-Theme, Klammern-Match)
- Strg+S zum Speichern
- Original-Anzeige als Read-Only-Vergleich
- „↩️ Auf Original zurücksetzen"-Button löscht den Override

### 🛡️ Architektur: Override-System
- Originale bleiben in den Code-Dateien (`views_new/*.tpl.html`) – Updates aktualisieren sie
- Anpassungen werden in der DB-Tabelle `template_overrides` gespeichert
- Beim Rendern: Override aus DB > Datei aus Code
- Keine Datei-Schreibrechte vom Webserver nötig (sicher)
- Cache wird beim Save automatisch invalidiert

### 🔒 Sicherheit
- PHP-Tags (`<?php`, `<?=`) werden vor dem Speichern abgewiesen
- Nur Templates die als Datei existieren können geändert werden (Whitelist)
- Template-Engine bleibt sandboxed (kein PHP, kein File-Access aus Templates)

### Migration `migrate_v18_7.php`
Legt die `template_overrides`-Tabelle an.

## v0.18.6 (2026-05-25)

### ✨ Editierbare statische Seiten (Pages)
- Neue Tabelle `pages` für Impressum, Datenschutzerklärung, Nutzungsbedingungen
- Komplett im Admin bearbeitbar mit dem BBCode-Editor aus Phase 2
- Platzhalter-System: `{{name}}`, `{{street}}`, `{{city}}`, `{{email}}`, `{{forum_name}}`, `{{forum_url}}`, `{{date}}`, `{{month_year}}` werden beim Rendern automatisch ersetzt
- Admin-Bereich: **System → 📄 Rechtliches** zeigt Kontaktdaten + Page-Liste
- Templates `imprint.php`, `privacy.php`, `terms.php` laden jetzt aus DB (statt hartkodiert)

### 🎨 BBCode-Erweiterungen
- Neue Tags: `[h2]` (große Überschrift), `[h3]` (kleinere Überschrift), `[hr]` (horizontale Linie)
- `[list]` mit `[*]`-Markern für Bullet-Listen (auch nummeriert: `[list=1]`)
- Sub-Pages: `[url=/profile.php]Link[/url]` (interne Links)
- Editor: Optionale Header-Buttons mit `$editorAllowHeadings = true` (für Pages aktiv, in Posts/PMs nicht)

### 🛠️ Sonstiges
- Bugfix: MStV-Bereich im Impressum nur noch mit Namen (Adresse stand redundant darüber)
- Migration `migrate_v18_6.php` legt Tabelle an und spielt Default-Inhalte ein
- `imprint_data()`-Settings aus v0.18.5 bleiben kompatibel

## v0.18.5 (2026-05-25)

### ✨ Rechtliches im Admin konfigurierbar
- Impressum und Datenschutz nutzen jetzt Settings statt hardcoded-Daten
- Neuer Admin-Bereich: **Admin → System → 📄 Rechtliches**
- 4 Felder: Name, Straße, PLZ/Ort, E-Mail
- Wenn Daten leer: Platzhalter werden angezeigt + Admin sieht Warnhinweis auf den Pages
- Helper-Funktion `imprint_data()` in `lib/settings.php` mit Default-Fallback
- Vorteil für künftiges OpenSource: jeder Forenbetreiber füllt seine eigenen Daten ein

## v0.18.4 (2026-05-25)

### ✨ Phase 3: Editor auch in Privaten Nachrichten
- WYSIWYG-Editor (mit Live-Vorschau, Smileys, BBCode) jetzt auch beim Schreiben einer PM
- PM-Anzeige nutzt bereits `bbcode()` → Smileys werden in empfangenen PNs gerendert
- Bugfix nebenbei: Eingegebener Text bleibt erhalten wenn die PM-Form einen Validierungsfehler wirft (vorher ging er verloren)

### Phase 1–3 Komplett
Damit ist die ursprüngliche Smiley- und Editor-Roadmap abgeschlossen.

## v0.18.3 (2026-05-25)

### 🐛 Editor-Verbesserungen nach User-Feedback
- **Doppelte Vorschau-Tabs entfernt** (alte „Schreiben/Vorschau"-Tabs gingen weg, neuer Editor übernimmt komplett)
- **Vorschau ist standardmäßig aktiviert** beim Öffnen eines Editors (kann manuell ausgeblendet werden)
- **Smileys werden jetzt auch in Zitaten gerendert** statt nur als Code angezeigt
- **Alias-Smileys im Admin gekennzeichnet** (z.B. „:-)" wird als „Alias von :)" markiert — gestrichelte Border + abgesenkte Opacity)
- **Auto-Save Status-Indikator** in die neue Toolbar integriert (rechts neben Vorschau-Button)

## v0.18.2 (2026-05-25)

### ✨ Phase 2: WYSIWYG-Editor
- Wiederverwendbarer Editor-Partial (`views/partials/editor.php`)
- Neue Toolbar: B/I/U/S, Quote/Code/Spoiler, Listen (ul/ol), Link, Bild, Smileys, Vorschau
- Live-Vorschau via existierenden `api/preview.php`-Endpoint (debounced 400ms)
- Smiley-Picker als Grid statt Liste (visuell ansprechender)
- Keyboard-Shortcuts: Ctrl+B / Ctrl+I / Ctrl+U
- Sticky-Toolbar bei langen Editoren
- Eingebaut in: `new_thread.php`, `edit_post.php`, `thread.php` (Antwort-Form)
- Bestehende globale Funktionen (`bbWrap` etc.) bleiben kompatibel

### 🛠️ Konsistenz-Check verbessert
- Stripping von PHP-Kommentaren vor SQL-Analyse
- String-Literale werden in WHERE-Klauseln ignoriert (kein `'dismissed'` als „Spalte")
- 5+ False-Positive-Filter ergänzt

## v0.18.1 (2026-05-26)

### ✨ Smiley-Verwaltung im Admin
- Smileys jetzt in eigener DB-Tabelle (statt hartkodiert in `lib/smileys.php`)
- Admin-UI unter „Inhalt → 😀 Smileys": anlegen, bearbeiten, löschen
- Picker-Smileys per Drag & Drop sortierbar (Reihenfolge wird automatisch gespeichert)
- 52 Standard-Smileys (24 davon im Picker) via Migration
- Migration: `migrate_v18_1.php` (für bestehende Foren)

## v0.18.0 (2026-05-26)

### ✨ Neue Template-Engine
- Eigene Twig-inspirierte Template-Engine in `lib/template.php`
- Auto-Escape standardmäßig (kein vergessenes `e()` mehr möglich)
- Endung: `.tpl.html` (für IDE-HTML-Highlighting)
- Compile-Cache in `cache/templates/`
- Mischbetrieb: `views_new/*.tpl.html` haben Vorrang, sonst Fallback auf `views/*.php`
- Demo: Login-View migriert
- Anleitung für Test-Forum-Setup (`TEST_FORUM_SETUP.md`)

## v0.17.7 (2026-05-26)

### 🐛 Self-Healing Versionsanzeige
- Footer zeigte „Version 0.16 Beta" obwohl Code längst weiter
- Ursache: `forum_version` DB-Setting wurde seit Installation nie mehr aktualisiert
- Fix: Konstante `FORUM_VERSION_CODE` in bootstrap.php + Auto-Sync in DB
- Künftig: kein manuelles SQL mehr nötig, Version pflegt sich selbst

## v0.17.6 (2026-05-26)

### 🐛 Hotfix für v0.17.5
- Online-Punkt war bei gelöschten Usern im Thread weiterhin sichtbar
- Ursache: `is_deleted` wurde nicht aus der users-Tabelle in den Thread-Query gejoint
- Fix: COALESCE(u.is_deleted, 0) ergänzt

## v0.17.5 (2026-05-26)

### 🎨 Polish
- Kein Online/Offline-Punkt mehr bei gelöschten Usern (würde nie wieder online sein)
- `online_dot()` akzeptiert jetzt auch User-Array statt nur ID (spart DB-Queries)

## v0.17.4 (2026-05-26)

### 🛡️ Sicherheits-Fix – Founder/Owner-Lock-Out-Schutz
- Founder (User-ID 1) konnte sich theoretisch selbst löschen (Danger-Zone-Button war sichtbar)
- Letzter Owner konnte den Lösch-Prozess starten (Fehler kam erst nach Passwort + LÖSCHEN)
- Neue Helper-Funktion `can_user_delete_self()` zentralisiert die Prüfung
- Im Profil: Danger Zone nur sichtbar wenn Löschung erlaubt
- Auf /delete_account.php: Erklärungs-Seite vor der Form, wenn nicht erlaubt
- Race-Condition-Schutz: Re-Check direkt vor dem Löschen

## v0.17.3 (2026-05-26)

### 🐛 Hotfix für v0.17.1
- email-Spalte ist NOT NULL UNIQUE → konnte nicht auf NULL gesetzt werden
- Fix: SHOW COLUMNS erfasst auch NULL-Eigenschaft. Bei NOT NULL → leerer String.
- Bei UNIQUE-Spalten (email): eindeutiger Platzhalter `deleted_<id>_<time>@deleted.local`

## v0.17.2 (2026-05-26)

### 🐛 Bug-Fix – Dropdown-Clipping in Admin-Benutzerverwaltung
- ⋯-Menü neben Usern wurde unten von der umschließenden Card abgeschnitten
- Fix: `.card:has(.action-dropdown)` setzt `overflow: visible`
- Abgerundete Ecken bleiben für andere Cards erhalten

## v0.17.1 (2026-05-26)

### 🐛 Hotfix für v0.17.0
- `delete_user_account` referenzierte nicht-existente Spalten (`banner`, `bio`, `website`)
- Fix: Defensive Spalten-Erkennung via `SHOW COLUMNS` – setzt nur was wirklich existiert

## v0.17.0 (2026-05-26)

### ✨ Neues Feature – Account-Löschung (DSGVO)
- **Selbst-Löschung:** User können eigenen Account löschen (Passwort + „LÖSCHEN" zur Bestätigung)
- **Admin/Owner-Löschung:** Admins können User-Accounts löschen (Soft-Delete = anonymisiert)
- **Beiträge bleiben** erhalten, persönliche Daten werden komplett entfernt
- Username wird zu „Gelöschter Benutzer #<ID>"
- Avatar, Bio, Signatur, Email, Rollen → alle weg
- Login unmöglich, Remember-Tokens werden gelöscht
- PMs beim Empfänger bleiben sichtbar (mit „Gelöschter Benutzer" als Sender)
- Letzter Owner kann sich nicht löschen (Lock-Out-Schutz)
- Profilseite gelöschter User zeigt minimale Hinweis-Box

### 🔧 Migration
- `migrate_v16_3.php` – Spalten `is_deleted` + `deleted_at` zu `users`-Tabelle

## v0.16.23 (2026-05-25)

### 🎨 „Powered by Forumix" größer und besser lesbar
- Text 11px → 13px
- Logo 16px → 24px (50% größer)
- Farbe von text-faint zu text-muted (etwas kräftiger)

## v0.16.22 (2026-05-25)

### 🎨 Footer-Layout überarbeitet
- Forum-Name nicht mehr doppelt im Footer
- Copyright, Links und Version jetzt in eigenen Zeilen (mittig)
- Versions-Nummer eigene dezente Zeile (kleiner, etwas ausgegraut)
- FAQ-Link ans Ende der Link-Liste verschoben (da Modul-abhängig)
- Powered by Forumix bleibt darunter

## v0.16.21 (2026-05-25)

### 🐛 Bug-Fix – Meldungen-Liste war leer trotz vorhandener Reports
- Ursache: Nicht-existente `r.thread_id`-Spalte in der List-Query → SQL-Error → leere Liste
- COALESCE(p.thread_id, r.thread_id) war Reste von einer nie umgesetzten Funktion
- Fix: Pfad reports → posts → threads geht jetzt nur noch über `p.thread_id`
- Diagnose-Block wieder entfernt

## v0.16.20 (2026-05-25)

### 🔬 Diagnose-Build (temporär)
- Diagnose-Block für Reports-Liste jetzt für alle Mods sichtbar (nicht nur Owner)
- Zeigt zusätzlich was mod_reports_list(null) direkt liefert

## v0.16.19 (2026-05-25)

### 🐛 Hotfix für v0.16.18
- Parse Error „Unmatched '}'" gefixt (str_replace hatte eine überzählige Klammer reingeschmuggelt)
- Mod-Bereich funktioniert wieder

## v0.16.18 (2026-05-25)

### 🐛 Bug-Fix-Versuch
- Reports-Liste war leer obwohl Count > 0 (Daten-Inkonsistenz)
- Ursache vermutet: INNER JOIN mit users.reporter filtert Datensätze raus wenn Reporter gelöscht
- Fix: INNER JOIN → LEFT JOIN
- Diagnose-Block für Owner falls das nicht reicht (zeigt rohe DB-Daten)

## v0.16.17 (2026-05-25)

### 🐛 Bug-Fix – Meldungs-Notification routet richtig
- Klick auf Report-Notification leitete immer zum Admin-Bereich → 403 für Nicht-Admins
- Fix: Admins → /admin/, Mods (global oder Board) → /mod.php
- Auch Board-Mods (ohne globalen is_mod=1) werden jetzt korrekt geroutet

## v0.16.16 (2026-05-25)

### 🐛 Bug-Fix – Schriftart wird endlich übernommen
- Variablen-Namen-Mismatch: `style.css` nutzt `var(--font)`, Layout setzte `--font-family`
- DB-Schrift war zwar gesetzt aber wurde von Komponenten nie verwendet
- Fix: Beide Variablen (`--font` und `--font-family`) werden jetzt aus DB-Setting befüllt

## v0.16.15 (2026-05-25)

### 🐛 Bug-Fix – Design-Vorschau funktioniert wieder
- iframe wurde nach Theme-Änderung nicht neu geladen (Hash statt Query-Parameter)
- Fix: Query-Parameter `?_t=<timestamp>` zwingt iframe zum Neuladen
- Bonus: Preview-Werte werden jetzt auch übernommen wenn der Key nicht im DB-Default ist
- `current_user()['is_admin']` durch sauberes `is_admin()` ersetzt

## v0.16.14 (2026-05-25)

### 🐛 Bug-Fixes – Quelltext-Hygiene
- PHP-Warning „Undefined variable \$accentSoft" gefixt – wird jetzt aus accent-Hex berechnet
- PHP-Warning „Undefined variable \$customCss" gefixt – wird jetzt aus Settings geladen
- Doppeltes `</style>` im Head entfernt (Copy-Paste-Fehler)
- Custom CSS aus Admin → Design → Erweitert wird jetzt tatsächlich ausgespielt

## v0.16.13 (2026-05-25)

### 🐛 Hotfix für v0.16.12
- PHP-Warnings „Undefined variable \$cu" auf PM-Read-Seite gefixt
- View nutzt jetzt `current_user()` direkt statt nicht-definiertes `\$cu`
- „An [Empfänger]"-Anzeige funktioniert wieder korrekt

## v0.16.12 (2026-05-25)

### 🐛 Bug-Fix
- **Sent-Ordner-PMs konnten nicht geöffnet werden** („Nachricht nicht gefunden")
- Read-Query prüfte nur receiver_id, Sender war ausgeschlossen
- Fix: Query erlaubt jetzt receiver_id ODER sender_id (mit jeweils passendem deleted-Flag)
- View zeigt im Sent-Modus „An [Empfänger]" + Lesestatus, blendet Antworten-an-sich-selbst aus

## v0.16.11 (2026-05-25)

### 🐛 Bug-Fix
- **Forum war auf Desktop linksbündig** (eigentliche Ursache endlich gefunden!)
- Im Layout-Template hatte `#app` ein `max-width: var(--max-width)` ohne `margin: 0 auto`
- Bei Bildschirmen breiter als die eingestellte „Maximale Forum-Breite" rutschte alles nach links
- Fix: `margin: 0 auto` + `width: 100%` für `#app` ergänzt

## v0.16.10 (2026-05-25)

### 🐛 Kritischer Bug-Fix
- **Forum linksbündig statt zentriert auf Desktop** (Copy-Paste-Fehler in style.css)
- Ursache: doppelte `width: 100%`-Zeile und überzählige `}` im `#wrapper`-Block
- Folge: CSS-Parser brach, `max-width: 1320px; margin: 0 auto` ging verloren
- Fix: überzählige Zeilen entfernt, Klammer-Balance wiederhergestellt

## v0.16.9 (2026-05-25)

### 🐛 Hotfix
- **Logo war komplett unsichtbar** (Folgefehler aus v0.16.8)
- Ursache: SVG ohne `height`-Attribut rendert in Browsern als 0px
- Fix: SVG bekommt `height="100"` + `preserveAspectRatio`, CSS setzt explizit `height: 100px` (60px mobile)

## v0.16.8 (2026-05-25)

### 🐛 Bug-Fix
- **Aynku-Logo wurde zu groß angezeigt** (Folgefehler aus der Forumix-Logo-Umstellung v0.16.5)
- Ursache: Bei inline-SVG respektiert der Browser die nativen `width`/`height`-Attribute statt CSS
- Fix: Feste Größen aus dem SVG entfernt, nur `viewBox` behalten; `max-width: 280px` als CSS-Sicherheit

## v0.16.7 (2026-05-25)

### 🎨 Logo-Aufstellung
- Oberes Logo zurück zum Aynku-Design (lila Sprechblase, Indigo-Verlauf)
- „Powered by Forumix" bleibt im Footer als dezentes Software-Branding
- Klare Trennung: Community-Marke oben, Software-Brand unten

## v0.16.6 (2026-05-25)

### 🐛 Bug-Fix + ✨ Neu
- **Logo-Lücke gefixt:** „Forum" und „ix" sitzen jetzt direkt aneinander (statt mit Lücke)
  - Stattdessen ein `<text>`-Element mit `<tspan>`-Subgruppen – Browser setzt Buchstaben perfekt
- **„Powered by Forumix" im Footer** – Software-Branding bleibt subtil sichtbar auch wenn ein
  eigenes Logo hochgeladen ist (klassisches Web-Pattern wie Discourse/Flarum/NodeBB)

## v0.16.5 (2026-05-25)

### ✨ Neu – Forumix als Default-Logo
- Neues Standard-Logo: zwei überlappende Sprechblasen + Wordmark „Forumix"
- Tagline: „Deine Community. Deine Software."
- **Theme-adaptiv:** nutzt SVG-`currentColor` – passt sich automatisch an Light/Dark-Mode an
- Blaue Sprechblase + blaues „ix" bleiben immer als Brand-Akzent stehen

### 🔧 Technisch
- Neue Helper-Funktion `forum_logo_render()` – inlined Default-SVGs für currentColor-Support
- Benutzerdefinierte Logos werden weiterhin als `<img>` ausgegeben (unverändert)

## v0.16.4 (2026-05-25)

### 🐛 Bug-Fix
- Interner Hinweis-Block aus der Datenschutzerklärung entfernt – war versehentlich öffentlich sichtbar

## v0.16.3 (2026-05-25)

### ✨ Neu – Rechtliche Seiten
- **Datenschutzerklärung** unter `/privacy.php` – DSGVO-konform aufgebaut
- **Nutzungsbedingungen** unter `/terms.php` – klassische Forum-Regeln
- Beide im gleichen Stil wie das bestehende Impressum
- Footer um „Datenschutz" und „Nutzungsbedingungen" erweitert
- Online-Liste zeigt beide Seiten korrekt an

⚠️ Die Texte sind solide Vorlagen, sollten aber für produktiven Einsatz von einem Fachanwalt geprüft werden.

## v0.16.2 (2026-05-25)

### ✨ Konsistenz
- Passwort-Schutz kann jetzt direkt beim Anlegen eines Forums gesetzt werden (vorher nur beim Bearbeiten)
- Neues „🔒 Passwort-Schutz"-Feld im „➕ Neues Forum"-Formular

## v0.16.1 (2026-05-25)

### 🐛 Bug-Fixes – Owner-Farbe (Lila) an mehreren Stellen
- Owner erscheinen als Forum-Mod jetzt korrekt lila statt orange (Forum-Index)
- Owner erscheinen in „Wer ist online?" jetzt korrekt lila statt weiß
- Owner erscheinen in Admin → Foren → Mods-Spalte jetzt korrekt lila statt rot
- Legende unter „Wer ist online" zeigt jetzt zusätzlich „Inhaber" als Lila

### 🔧 Technisch
- Neue zentrale Funktion `user_role_style()` in `lib/auth.php` – ersetzt 3x kopierten Code
- `board_moderators_map()` und `online_users()` laden jetzt `is_owner` mit

## v0.16.0 (2026-05-25)

### 🚀 MINOR – Migration nötig (migrate_v16.php)

### ✨ Neu – Passwort-geschützte Foren
- Foren können mit einem Passwort geschützt werden (Hash via bcrypt)
- Owner/Admin/Mod und Forum-Mods sind ausgenommen (kein Passwort nötig)
- Andere User geben Passwort einmal pro Session ein → bleiben entsperrt bis zum Logout
- Brute-Force-Schutz: 5 Versuche/15 Min via Rate-Limiting
- Nicht-Berechtigte sehen: Forum + Beschreibung + Statistiken, aber Last-Post-Titel maskiert
- Threads aus geschützten Foren erscheinen NICHT in: Suche, Tags, Profil-Stats, Lesezeichen, RSS

### ✨ Neu – Kategorien bearbeiten
- Im Admin → Kategorien jetzt mit „✏️ Bearbeiten"-Button pro Zeile
- Inline-Form für Titel + Position
- Bisher konnte man nur anlegen + löschen – jetzt komplett verwaltbar

### 🔧 Technische Details
- Neue zentrale Library `lib/board_access.php` mit Helper-Funktionen
- `board_filter_sql()` als zentrales Filter-Snippet in allen Queries
- `board_user_can_access()` für Zugangs-Prüfung
- Neue Seite `pages/board_unlock.php` für die Passwort-Eingabe

## v0.15.5 (2026-05-25)

### ✨ Verbesserung
- **Saubere Team-Trennung**: Owner/Admin/Mod erscheinen nicht mehr doppelt in der Mitglieder-Team-Übersicht
- Die „Forum-Moderatoren"-Sektion zeigt jetzt nur User ohne globale Rolle
- Hierarchie ist konsistent: oben das Team, unten nur die Forum-spezifischen Mods
- Forum-Detailseite (Mods pro Board) und Admin-Verwaltung bleiben unverändert

## v0.15.4 (2026-05-25)

### 🐛 Kritischer Bug-Fix
- **Owner konnten Admin-Bereich nicht mehr aufrufen** (Folgefehler aus v0.15.3)
- `is_admin()` und `is_mod()` berücksichtigen jetzt die korrekte Hierarchie:
  - Owner zählt automatisch als Admin
  - Owner und Admin zählen automatisch als Mod
- Bonus: Report-Notifications gehen jetzt auch an Owner

## v0.15.3 (2026-05-25)

### ✨ Neu – Owner-Verwaltung
- Mehrere Inhaber gleichzeitig möglich – Team-Bereich zeigt alle Owner an
- **Founder-Konzept**: User-ID 1 ist der Forum-Gründer
- Nur der Founder kann Owner ernennen (`👑 Zum Inhaber`) oder entziehen (`⬇️ Owner entziehen`)
- Founder selbst ist hart geschützt (kein Lock-Out möglich)
- Andere Owner zeigen "🛡️ Inhaber (geschützt)" – nur Founder kann sie verändern

### 🐛 Bug-Fixes
- **"Administrator"-Rang bei Owner** behoben: Owner haben jetzt Rang "Inhaber"
- Owner haben kein doppeltes `is_admin`-Flag mehr (Owner > Admin)
- `update_user_rank()` und `recalculate_all_ranks()` berücksichtigen Owner-Status korrekt
- Bei Admin/Owner-Toggle wird der Rang automatisch aktualisiert

### 🔧 Migration nötig (migrate_v15_2.php)
- Räumt bestehende Owner-/Admin-Ränge in der Datenbank auf
- Stellt sicher dass User-ID 1 als Founder/Inhaber gesetzt ist

## v0.15.2 (2026-05-25)

### ✨ Mobile-Verbesserung
- 📱 Alle Tabellen werden auf Mobile (≤700px) automatisch zu lesbaren Karten umgewandelt
- JavaScript injiziert `data-label` aus den Tabellen-Headern, CSS rendert als Card-Layout
- Wirkt überall: Admin-Bereich (Benutzer, Statistiken, Meldungen, ...), Mitglieder, Online-Liste, Lesezeichen, Profil-Stats
- Desktop bleibt unverändert (klassische Tabelle)
- Opt-out per Klasse `forum-table-keep-wide` (für spezielle Fälle → Horizontal-Scroll)

## v0.15.1 (2026-05-25)

### 🐛 Bug-Fixes
- **SMTP-Mails kamen nicht an** ("message has lines too long for transport")
- Body wird jetzt `quoted-printable` encoded → löst sowohl Zeilenlängen-Problem als auch UTF-8-Encoding
- Test-Mail-Erfolgsmeldung ist ehrlicher: zeigt dass nur die Annahme bestätigt ist, nicht die Zustellung

## v0.15.0 (2026-05-25)

### 🚀 MINOR – Migration nötig (migrate_v15.php)

### ✨ Neu – „Angemeldet bleiben" mit Profi-Sicherheit
- Checkbox „🔒 Angemeldet bleiben (30 Tage)" auf der Login-Seite
- Split-Token-Pattern (Selector + Validator) gegen Timing-Attacks
- Constant-Time-Compare, Defense-in-Depth bei verdächtigen Tokens
- Rolling-Renewal alle 7 Tage
- Cookies httponly, samesite=Lax, secure bei HTTPS
- Auto-Token-Widerruf bei Logout, Passwort-Reset, Passwort-Änderung

### 🔐 Aktive Sitzungen im Profil
- Liste aller Geräte mit "Angemeldet bleiben" (Browser + OS + IP + Daten)
- 📍 Markierung "Diese Sitzung"
- 🚪 Einzelne Geräte oder alle anderen Geräte abmelden

### 🔧 Erweiterung am Core
- `lib/remember.php` – komplette Token-Verwaltung
- bootstrap.php: Auto-Login bei vorhandenem Token, periodische Token-GC

## v0.14.5 (2026-05-25)

### ✨ Neu – Settings-Bereich aufgeräumt
- 7 klare Sektionen statt wildem Mix: Allgemein, Registrierung, Beiträge, Moderation, E-Mail, Wartung, Cookie
- Neue Settings: Standardsprache (DE/EN), Bot-Erkennung-Level, Mod-Notifications bei Reports, Cookie-Banner

### 📧 SMTP komplett ausgebaut
- Endlich alle Felder im Admin: Host, Port, User, Passwort, Verschlüsselung (STARTTLS/SSL/keine)
- Echter SMTP-Versand mit AUTH LOGIN (vorher nur PHP-mail())
- Test-Mail-Button im Admin → schickt eine Test-Nachricht, zeigt Erfolg oder klaren Fehler
- Fallback auf PHP-mail() wenn kein SMTP-Host gesetzt

### 🍪 Cookie-Banner
- Aktivierbares Banner unten am Bildschirm
- Anpassbarer Text, einmaliger Hinweis (per localStorage gemerkt)
- Slide-Up-Animation beim ersten Besuch

## v0.14.4 (2026-05-25)

### ✨ Neu – Design-System Etappe 2
- **🖼️ Identität-Tab**: Forum-Name, Slogan, Logo, Akzentfarbe, Standard-Theme (alles aus Settings hierher verschoben)
- **📐 Layout-Tab**: Header-Stil (Standard/Zentriert/Kompakt) + Footer-Text + Footer-Links
- **🎁 Presets-Tab**: 6 vorgefertigte Themes (Original, Discord, Mintgrün, Sonnen-Orange, Cyberpunk, Soft Light) per Klick anwendbar
- **🛠️ Erweitert-Tab**: Custom CSS (aus Settings verschoben)

### 🧹 Aufgeräumt
- Settings-Bereich enthält nur noch funktionale Einstellungen (keine Branding/Footer/CSS-Felder mehr)
- Hinweis-Block in Settings zeigt auf den neuen Design-Bereich
- Density-Preset (Compact/Normal/Comfortable) wirkt jetzt real auf Padding/Abstände
- Body-Klassen `header-*` und `density-*` für CSS-gesteuerte Layouts

### 🐛 Bug-Fixes
- Reset-Button im Design war verschachteltes `<form>` (gleicher Bug-Typ wie in v0.13.5). Reset-Form jetzt außerhalb, Trigger per JS.

## v0.14.3 (2026-05-24)

### 🐛 Bug-Fixes
- Sub-Versionen wie `v14_2` werden jetzt im Updates-Bereich erkannt
- Migrations-Pattern erweitert von `v\d+` auf `v\d+(?:_\d+)?`
- Korrekte Sortierung von Sub-Versionen (v14_2 kommt nach v14)

## v0.14.2 (2026-05-24)

### 🐛 Bug-Fixes / Migration
- **Original-Farben wiederhergestellt**: In v0.14.0 hatten sich Slate-Töne als Defaults eingeschlichen, statt der neutralen Discord/GitHub-Farben aus dem Original-`style.css`
- Migration `v14_2` setzt 19 Theme-Farb-Settings auf die Original-Werte zurück
- Reset-Knopf im Design-Bereich nutzt nun ebenfalls die korrekten Werte
- Bootstrap-Defaults korrigiert für saubere Fallbacks

## v0.14.1 (2026-05-24)

### 🐛 Bug-Fixes
- Doppelter ✅-Haken bei Migration-Erfolgsmeldung entfernt
- Die Flash-Layout-Logik fügt das Symbol automatisch hinzu, also kein zusätzliches Emoji im Meldungstext nötig

## v0.14.0 (2026-05-24)

### 🚀 MINOR – Migration nötig (migrate_v14.php)

### ✨ Neu – Design-Bereich (Etappe 1)
- Neuer Admin-Bereich "🎨 Design" getrennt von funktionalen Einstellungen
- **Farben-Tab**: 19 individuelle Farben (Dark + Light + Status) mit Color-Picker und Hex-Eingabe
- **Schrift-Tab**: 7 Schriftarten + Custom-Option, Schriftgröße 12-16px
- **Form-Tab**: 5 Rundungs-Presets (eckig → pille), Max-Width 980-1600px, Dichte-Preset
- **Live-Vorschau** im Iframe rechts neben den Controls (400ms Debounce)
- "↗ Großansicht" öffnet Vorschau in neuem Tab
- "↺ Auf Standard zurücksetzen" stellt Werks-Defaults wieder her
- Theme-Settings landen als CSS-Variablen direkt im `<head>` jeder Seite

### 🔧 Erweiterung am Core
- `bootstrap.php` mit Theme-Helper-Funktionen (`theme_settings()`, `theme_font_stack()`, `theme_radius_preset()`)
- `views/layout.php` rendert komplette CSS-Variablen-Sets pro Theme
- Google-Fonts dynamisch nachgeladen je nach Auswahl

## v0.13.8 (2026-05-24)

### ✨ Neu – FAQ-Modul
- Aktivierbares FAQ-Modul mit 4 Kategorien (Allgemein, Beiträge, Konto, Moderation)
- 23 vorbefüllte Fragen + Antworten als Startpunkt
- Accordion-UI mit Quick-Jump-Navigation
- Admin-Verwaltung: Fragen anlegen/bearbeiten/löschen/sortieren
- Sichtbarkeits-Flag pro Frage (z.B. für Drafts)
- BBCode-Unterstützung in Antworten
- Link in Footer + Hauptmenü (nur wenn Modul aktiv)

### 🔧 Erweiterung am Core
- `lib/view.php` unterstützt jetzt Modul-Views per `@modulcode/viewname`-Syntax
- Damit können Module saubere Templates haben ohne den Core anzufassen

## v0.13.6 (2026-05-24)

### ✨ Neu
- 🔍 Avatar-Lightbox: Klick auf jedes Avatar zeigt es groß mit Username + Profil-Link
- Funktioniert in Threads, Profilen, Mitglieder-Übersicht und Shoutbox
- Hover-Effekt zeigt Klickbarkeit, ESC + Klick außerhalb zum Schließen

## v0.13.5 (2026-05-24)

### 🐛 Bug-Fixes
- **Kritisch:** Admin → Einstellungen → Speichern funktionierte nicht (verschachtelte `<form>`-Tags)
- Rollen-Avatare-Sektion ist jetzt eigener Block vor dem Settings-Form

## v0.13.4 (2026-05-24)

### 🐛 Bug-Fixes
- Rollen-Avatare im Team-Bereich der Mitgliederseite werden jetzt korrekt angezeigt
- `team_members()` lädt jetzt die Rollen-Flags (`is_owner`, `is_admin`, `is_mod`)
- Bonus: `rank` mit Backticks versehen (MySQL 8+ reserved keyword)

## v0.13.3 (2026-05-24)

### 🐛 Bug-Fixes
- Shoutbox zeigt erste Nachricht jetzt sofort an (ohne Reload nötig)
- Polling-Logik repariert für leere Shoutbox

## v0.13.2 (2026-05-23)

### ✨ Neu
- Avatar und Banner im Profil-Edit jetzt entfernbar (Checkbox „🗑️ entfernen")
- Vorschau-Bilder im Profil-Edit-Formular
- Bei Avatar-Entfernung wird der Rollen-Avatar wieder angezeigt (Owner/Admin/Mod)

## v0.13.1 (2026-05-23)

### ✨ Neu
- 👤 Rollen-Avatare: Owner / Admin / Mod bekommen automatisch ein SVG-Avatar
  wenn sie kein eigenes hochgeladen haben
- 3 SVG-Standard-Bilder (Owner Gold, Admin Blau, Mod Grün)
- Im Admin-Bereich austauschbar via Upload (PNG/JPG/WEBP/SVG, max 2 MB)
- Forum-Mods (kontextuell, pro Forum) bekommen bewusst KEIN Rollen-Avatar
- Bei Rechte-Entzug verschwindet das Rollen-Avatar automatisch

## v0.13.0 (2026-05-23)

### 🚀 MINOR – Migration nötig (migrate_v13.php)

### ✨ Neu – Modul-System
- Tabelle `modules` für aktivierbare Erweiterungen
- Ordnerstruktur `modules/<code>/` mit Manifest, install.sql, hooks/, api.php
- Hook-System: Module können sich an verschiedenen Stellen ins Layout einklinken
- Admin-Verwaltung: Module per Klick aktivieren/deaktivieren
- **Daten bleiben erhalten** wenn Modul deaktiviert wird

### ✨ Erstes Modul – Shoutbox
- Mini-Chat-Box auf der Startseite
- Live-Updates via Polling (alle 10 Sek)
- Rate-Limit: 1 Nachricht / 5 Sek pro User
- 500 Zeichen max, Rollen-Badges, eigene Nachrichten löschen
- Ein-/Ausklappbar mit gemerktem Zustand (localStorage)

### 🧹 Cleanup
- Migrate-Dateien `v2`-`v13` ins Archiv verschoben (`install/migrations/archive/`)
- `UPDATES.md` Anleitung für Update-Workflow
- Erste **Delta-Update**-Infrastruktur etabliert

---

## Frühere Versionen

Für Versionen vor v0.13 siehe Git-Historie bzw. Migration-Dateien im Archiv:

- **v0.12** – Branding-System, Mod-Bereich
- **v0.11** – Notification-Typen, Tastatur-Shortcuts, Dashboard
- **v0.10** – Bot-Erkennung, Notification-Löschen, PM-Lösch
- **v0.9** – Version in DB, Forum-Editor, Logo-Banner
- **v0.8** – Freigabe-System, Mod-Edits
- **v0.7** – Owner-Rolle
- **v0.6** – Forum-spezifische Moderatoren
- **v0.5** – Passwort-Reset, 2FA, Rate-Limit, Gelesen-Marker
- **v0.4** – Meldungen, Warnungen, Edit-Log, Tags
- **v0.3** – Reaktionen, Lesezeichen, Online-Status, Achievements
- **v0.2** – Anhänge, Notifications, Polls, IP-Tracking
- **v0.1** – Initiale Forum-Basis
