# Datenbank-Anpassungen

Hier liegen die Migrationen: `migrate_v<version>.php`, eine je Änderung am
Schema. Gefahren werden sie im Adminbereich unter *Wartung → Updates* oder ohne Browser
mit `php install/migrate.php`.

**Der Ordner bleibt stehen, auch wenn er leer ist.** Er ist die einzige Quelle
für `available_migrations()`; fehlt er, liefert die Funktion eine leere Liste —
ohne Fehler und ohne Warnung. Der Installer räumt sich nach dem Aufsetzen
selbst weg und lässt diesen Ordner ausdrücklich liegen.

## Regel für neue Freigaben

**Jede Datenbankänderung bekommt eine Datei, und die bleibt im Paket** —
mindestens zurück bis zur ältesten Version, die noch irgendwo läuft. Nicht über
`update.sql`: das steckt nur im Updatepaket, und betreute Installationen
bekommen das Vollpaket.

## Aufräumen

Haben sich Dateien angesammelt, dürfen sie weg — `install.sql` ist der Schnitt.
Was dort im Schema steht, braucht keine Migration mehr, denn jede frische
Installation bekommt es ohnehin.

**Das ist aber keine Aufräumarbeit, sondern eine Zusage:** „Wer von einer
Version älter als X kommt, muss erst auf X." Wer direkt von darunter springt,
findet die entfernten Dateien nicht mehr — `available_migrations()` liefert
dann einfach weniger, ohne Fehler, und der neue Code sitzt auf alten Tabellen.

Erschwerend: `bootstrap.php` zieht `forum_version` beim ersten Aufruf auf die
Code-Version hoch. Sobald der neue Code einmal geladen wurde, ist nicht mehr
feststellbar, woher die Installation kam — eine nachträgliche Prüfung gibt es
also nicht.

Deshalb beim Wegwerfen:

1. Die Untergrenze in die Freigabe-Notiz und auf die Downloadseite schreiben.
2. Beim Hosting greift eine Prüfung: der Dienst kennt die Version einer Instanz,
   bevor er eine Datei anfasst. Wenn hier je wieder Dateien wegfallen, gehört
   dort ein Abbruch für zu alte Instanzen hin (`internal/lauf`).

Wer eine Datei entfernt, muss zeigen können, dass niemand sie mehr braucht.
Für `migrate_v18_56.php` (16.08.2026 entfernt) war das der Fall: sie
verbreiterte `users.pending_email_token` auf `VARCHAR(255)`, und schon das
älteste je veröffentlichte Paket — 0.18.58 — legt die Spalte so an. Eine
Installation, der sie gefehlt hätte, kann es also nicht geben.

Die Dateien sind idempotent zu schreiben: bei einer frischen Installation gibt
es keine Einträge in der Tabelle `migrations`, also gilt dort jede vorhandene
Datei als ausstehend und läuft einmal durch. Sie muss dann feststellen, dass
nichts zu tun ist, statt zu scheitern.
