# Woher die Dateien kommen

`codemirror.min.js`, `codemirror.min.css`, `theme/dracula.min.css` und die Modi
`javascript`, `htmlmixed`, `css`, `xml` stammen aus der CodeMirror-Auslieferung
5.65.16 und werden vom Vorlagen-Editor im Adminbereich benutzt.

Seit 1.1.0 kommen dazu, fuer die Syntax-Faerbung in Beitraegen:

    addon/runmode/runmode-standalone.js
    mode/clike/clike.js
    mode/php/php.js
    mode/sql/sql.js

Diese vier stammen aus dem Debian-Paket `libjs-codemirror` 5.65.20 und liegen
unverkleinert vor. Die Fassungen 5.65.16 und 5.65.20 gehoeren derselben Reihe an;
Modi sind zwischen Patch-Fassungen austauschbar.

`runmode-standalone` bringt eine eigene, kleine CodeMirror-Huelle mit: sie kann
faerben, aber keinen Editor bauen. Deshalb laedt die Leseseite nur diese 12 KB
statt der ganzen Bibliothek — und auch das nur, wenn auf der Seite ueberhaupt
ein Code-Block mit Sprachangabe steht.

Lizenz unveraendert MIT, siehe LICENSE.
