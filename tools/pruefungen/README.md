# Prüfungen

Ein Prüflauf ohne Fremdpakete. Jede Datei hier liefert ein Array
`'Beschreibung' => fn() => …`, das bei einem Fehlschlag wirft.

```sh
php tools/pruefen.php                    alle
php tools/pruefen.php suche              nur eine Gruppe
```

## Datenbank

Geprüft wird **nie** gegen ein laufendes Forum, sondern gegen eine eigene
Datenbank:

```sh
FORUM_TEST_DB=forumix_test php tools/pruefen.php
```

## Prüfstand

Manche Prüfungen brauchen eine zweite, vollständige Installation — etwa um zu
sehen, wie sich das Forum verhält, wenn eine Datenbank-Anpassung fehlt. Wo die
liegt, sagt `FORUM_PRUEFSTAND`:

```sh
FORUM_PRUEFSTAND=/pfad/zur/instanz php tools/pruefen.php
```

Ohne die Angabe überspringen sich diese Prüfungen selbst und melden das —
der Lauf bleibt grün, deckt aber weniger ab.

## Fremde Prüfungen

Prüfungen, die außerhalb dieses Repos liegen, laufen mit:

```sh
php tools/pruefen.php --auch=/pfad/zu/pruefungen
```

Sie finden dieses Repo über die Konstante `FORUM_WURZEL`, die der Läufer
setzt.

## Eine neue Prüfung

Erst die Prüfung schreiben, die den Weg nachstellt und **fehlschlägt** — dann
den Fund beheben, dann läuft sie durch. Eine Prüfung, die nie rot war, beweist
nichts.
