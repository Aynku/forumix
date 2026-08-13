CREATE TABLE IF NOT EXISTS `faq_categories` (
  `cat_id`     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `title`      VARCHAR(80) NOT NULL,
  `icon`       VARCHAR(10) DEFAULT '📂',
  `position`   INT UNSIGNED DEFAULT 0,
  INDEX `idx_position` (`position`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `faq_items` (
  `faq_id`      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `cat_id`      INT UNSIGNED NOT NULL,
  `question`    VARCHAR(255) NOT NULL,
  `answer`      TEXT NOT NULL,
  `position`    INT UNSIGNED DEFAULT 0,
  `visible`     TINYINT(1) DEFAULT 1,
  `created_at`  DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_cat`      (`cat_id`),
  INDEX `idx_position` (`position`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `faq_categories` (`cat_id`, `title`, `icon`, `position`) VALUES
(1, 'Allgemein',  '🏠', 10),
(2, 'Beiträge',   '💬', 20),
(3, 'Konto',      '👤', 30),
(4, 'Moderation', '🛡️', 40);

INSERT INTO `faq_items` (`cat_id`, `question`, `answer`, `position`) VALUES
(1, 'Was ist dieses Forum?', 'Dieses Forum ist eine Community-Plattform, in der sich Mitglieder austauschen, Fragen stellen und diskutieren können. Du findest verschiedene Bereiche zu unterschiedlichen Themen – wähl einfach einen aus, der dich interessiert, und schau dich um!', 10),
(1, 'Muss ich mich registrieren um mitzulesen?', 'Nein, das Mitlesen ist für alle frei möglich. Zum [b]Schreiben von Beiträgen[/b] oder zum Reagieren brauchst du allerdings ein eigenes Konto. Die Registrierung dauert nur einen Moment.', 20),
(1, 'Kostet die Nutzung etwas?', 'Nein, das Forum ist komplett kostenlos. Keine Premium-Mitgliedschaften, keine versteckten Kosten, keine Werbung.', 30),
(1, 'Wie finde ich bestimmte Themen?', 'Oben im Forum gibt es eine [b]Suchleiste[/b], in der du nach Stichworten suchen kannst. Über die erweiterte Suche kannst du auch nach Autor, Datum oder Forum eingrenzen.', 40),
(1, 'Was sind Tags?', 'Tags sind Schlagworte, die einem Thema zugeordnet werden – z.B. "frage", "anleitung", "lösung". Über die Tag-Seite findest du alle Themen zu einem bestimmten Thema gebündelt.', 50),

(2, 'Wie schreibe ich einen neuen Beitrag?', 'Klick in einem Forum auf [b]"+ Neues Thema"[/b], gib einen Titel und deinen Text ein, und schick es ab. In bestehenden Themen findest du unten ein Antwort-Feld.', 10),
(2, 'Was ist BBCode?', 'BBCode ist eine einfache Formatierungs-Sprache. Du kannst zum Beispiel:[br][br][b]Fett:[/b] [b]Text[/b] mit [b][b]Text[/b][/b][br][b]Kursiv:[/b] [i]Text[/i] mit [b][i]Text[/i][/b][br][b]Link:[/b] [url=https://example.com]Beispiel[/url] mit [b][url=...]Text[/url][/b][br][b]Zitat:[/b] [quote]Zitat[/quote] mit [b][quote]Zitat[/quote][/b][br][br]Im Editor gibt es Knöpfe, die das für dich übernehmen.', 20),
(2, 'Kann ich Bilder oder Dateien anhängen?', 'Ja! Im Editor findest du eine [b]Anhang-Funktion[/b]. Erlaubt sind die gängigen Bildformate (JPG/PNG/GIF/WebP) und einige Datei-Typen. Schau einfach mal rein.', 30),
(2, 'Was sind Reaktionen?', 'Statt einer Antwort kannst du Beiträge mit Emoji-Reaktionen versehen (👍 ❤️ 😂 etc.). So zeigst du Zustimmung oder Freude, ohne dass jedes Mal ein neuer Beitrag entsteht.', 40),
(2, 'Wie lange kann ich meine Beiträge bearbeiten?', 'Du kannst eigene Beiträge nach dem Posten noch bearbeiten oder löschen. Wie lange genau – das hängt von den Forum-Einstellungen ab. Moderatoren sehen wenn ein Beitrag nachträglich verändert wurde.', 50),
(2, 'Warum ist mein Beitrag noch nicht sichtbar?', 'In manchen Foren werden neue Beiträge erst von einem Moderator [b]freigegeben[/b], bevor sie für alle sichtbar werden. Das ist eine Maßnahme gegen Spam. Hab Geduld – ein Mod sieht das in der Regel zeitnah.', 60),

(3, 'Wie ändere ich mein Passwort?', 'Klick oben rechts auf dein Avatar → [b]"⚙️ Einstellungen"[/b]. Dort kannst du dein Passwort und andere Profil-Daten anpassen.', 10),
(3, 'Ich habe mein Passwort vergessen!', 'Auf der Login-Seite gibt es einen Link [b]"Passwort vergessen?"[/b]. Gib dort deine E-Mail-Adresse ein – du bekommst dann eine E-Mail mit einem Link zum Zurücksetzen.', 20),
(3, 'Wie lade ich einen Avatar hoch?', 'Klick oben rechts auf dein Avatar → [b]"⚙️ Einstellungen"[/b] → bei "Avatar" eine Datei auswählen und speichern. Erlaubt sind JPG/PNG/GIF/WebP, max. 512 KB.', 30),
(3, 'Was ist Zwei-Faktor-Authentifizierung (2FA)?', '2FA ist ein zusätzlicher Sicherheits-Schutz für dein Konto. Beim Login gibst du nicht nur dein Passwort ein, sondern auch einen sechsstelligen Code aus einer App auf deinem Handy (z.B. Google Authenticator, Authy, …). So kann selbst jemand der dein Passwort kennt nicht auf dein Konto zugreifen.', 40),
(3, 'Was sind Achievements?', 'Achievements sind kleine Auszeichnungen, die du automatisch bekommst – z.B. für deinen ersten Beitrag, für 100 Posts, für 30 Tage Streak. Im Dashboard siehst du, welche du schon freigeschaltet hast.', 50),
(3, 'Was bedeutet "Tage Streak"?', 'Die Streak zählt, an wie vielen aufeinanderfolgenden Tagen du aktiv warst (z.B. einen Beitrag geschrieben hast). Schaffst du einen Tag nicht, fängt sie wieder bei 0 an. Im Dashboard wird sie als 🔥-Counter angezeigt.', 60),
(3, 'Kann ich mein Konto löschen?', 'Ja, wende dich dazu bitte direkt an einen Administrator. Aus rechtlichen Gründen können wir Konten manuell löschen, damit auch alle deine Beiträge ordentlich anonymisiert werden.', 70),

(4, 'Wer sind die Moderatoren?', 'Die Moderatoren sind ausgewählte Community-Mitglieder, die sich um die Einhaltung der Regeln kümmern. Du erkennst sie an einem [b]🛡️ Mod-[/b] oder [b]A Admin-Badge[/b] neben ihrem Namen. Die komplette Liste findest du auf der Mitglieder-Seite.', 10),
(4, 'Wie melde ich einen unangemessenen Beitrag?', 'Bei jedem Beitrag gibt es ein "..."-Menü (oder ein 🚨-Symbol). Klicke dort auf "Melden" und gib einen Grund an. Ein Moderator schaut sich das dann an und entscheidet was zu tun ist.', 20),
(4, 'Was passiert wenn ich eine Verwarnung bekomme?', 'Eine Verwarnung ist eine Hinweis auf einen Regelverstoß. Sie wird in deinem Profil hinterlegt. Bei mehreren Verwarnungen können weitere Maßnahmen folgen – im äußersten Fall ein Bann. Aber keine Sorge: in 99% der Fälle reicht eine Erinnerung und alles ist wieder gut.', 30),
(4, 'Was sind die Forum-Regeln?', 'Sei respektvoll. Bleib beim Thema. Kein Spam, keine Beleidigungen, keine illegalen Inhalte. Das sind die Grundregeln – ergänzende Regeln stehen in den Ankündigungen oder erfährst du von der Moderation.', 40),
(4, 'Ich glaube ich wurde zu Unrecht moderiert', 'Bei Unstimmigkeiten wende dich bitte direkt per [b]Privatnachricht[/b] an den verantwortlichen Moderator oder einen Admin. Wir hören uns deinen Standpunkt gerne an.', 50);
