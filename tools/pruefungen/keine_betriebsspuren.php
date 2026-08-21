<?php
/**
 * Im Produkt-Repo steht nichts, was nur den Betrieb einer bestimmten
 * Installation angeht.
 *
 * Anlass: Bis zum 21.08.2026 lagen die Betriebswerkzeuge von forumix.de unter
 * `tools/` in diesem Repo. Sie waren per `export-ignore` aus dem
 * Auslieferungspaket ausgeschlossen — aus dem Repository aber nicht. Das ist
 * die halbe Grenze: Ein Paket schuetzt sie, ein oeffentliches Repository nicht.
 * Wer es geklont haette, haette gesehen, wie hier ausgerollt wird, auf welchen
 * Pfaden, und wie das Forum mit der Hosting-Verwaltung zusammenhaengt.
 *
 * Diese Pruefung zieht die Grenze jetzt an der einzigen Stelle, an der sie
 * haelt: an dem, was hier liegt. Was nur bei einem bestimmten Betreiber laeuft,
 * gehoert in dessen eigenes Repo.
 *
 * Braucht keine Datenbank und keinen Pruefstand: gelesen wird der Quelltext.
 */

if (!defined('FORUM_PRUEFLAUF')) { http_response_code(403); die('Forbidden'); }

$wurzel = defined('FORUM_WURZEL') ? FORUM_WURZEL : dirname(__DIR__, 2);

return [
    'Kein fremder Betrieb im Produkt-Repo' => function () use ($wurzel) {
        // Spuren, die immer auf eine bestimmte Installation zeigen.
        $spuren = [
            '/var/www/'        => 'ein Pfad auf einem bestimmten Server',
            'forumixctl'       => 'das Werkzeug der Hosting-Verwaltung',
            '/usr/local/lib/'  => 'ein Ablageort auf einem bestimmten Server',
            '/etc/cron.d/'     => 'ein Eintrag auf einem bestimmten Server',
            '/root/'           => 'ein Pfad im Heimatverzeichnis des Betreibers',
        ];

        // Drei Ausnahmen, mit Grund:
        //   CHANGELOG.md   ist Geschichte und nennt Wege, die es einmal gab.
        //   README.md      darf auf das eigene Forum als Beispiel verweisen.
        //   diese Datei    traegt die Muster selbst — sonst meldet sie sich
        //                  selbst, und zwar erst nach dem Einchecken, weil
        //                  git ls-files nur Eingechecktes kennt.
        $ausgenommen = [
            'CHANGELOG.md',
            'README.md',
            'tools/pruefungen/' . basename(__FILE__),
        ];

        exec('cd ' . escapeshellarg($wurzel) . ' && git ls-files 2>/dev/null', $dateien, $rc);
        if ($rc !== 0 || !$dateien) throw new RuntimeException('git ls-files liefert nichts');

        $funde = [];
        foreach ($dateien as $rel) {
            if (in_array($rel, $ausgenommen, true)) continue;
            $pfad = $wurzel . '/' . $rel;
            if (!is_file($pfad) || filesize($pfad) > 400 * 1024) continue;

            // Binaerdateien ueberspringen.
            $inhalt = (string) file_get_contents($pfad);
            if (str_contains(substr($inhalt, 0, 2048), "\0")) continue;

            foreach ($spuren as $muster => $was) {
                if (!str_contains($inhalt, $muster)) continue;
                foreach (explode("\n", $inhalt) as $nr => $zeile) {
                    if (str_contains($zeile, $muster)) {
                        $funde[] = $rel . ':' . ($nr + 1) . ' — ' . $was
                                 . ' (' . trim(mb_substr($zeile, 0, 70)) . ')';
                        break;
                    }
                }
            }
        }

        if ($funde) {
            throw new RuntimeException(
                count($funde) . ' Betriebsspur(en) im Produkt-Repo: '
                . implode(' | ', array_slice($funde, 0, 5))
                . (count($funde) > 5 ? ' …' : '')
            );
        }
    },
];
