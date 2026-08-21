-- modules/ki/install.sql – Tabellen der KI-Schreibhilfe
--
-- Die Kennzeichnung steht in einer eigenen Tabelle und nicht als Spalte an
-- `posts`: ein Modul soll den Kern nicht umbauen. Wer das Modul abschaltet,
-- behaelt seine Beitraege unveraendert; die Kennzeichnung wird dann nur nicht
-- mehr angezeigt.
CREATE TABLE IF NOT EXISTS `ki_kennzeichnung` (
  `post_id`    INT UNSIGNED NOT NULL PRIMARY KEY,
  `befehl`     VARCHAR(30)  NOT NULL,
  `created_at` DATETIME     DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`post_id`) REFERENCES `posts`(`post_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
;

-- Wer wann etwas angefordert hat. Zwei Aufgaben: die Bremse (wie viele
-- Anfragen in der letzten Stunde) und die Frage des Betreibers, wofuer seine
-- Rechnung eigentlich entsteht. Der Text selbst steht hier NICHT — es soll
-- keine zweite Kopie fremder Beitraege geben, die niemand erwartet.
CREATE TABLE IF NOT EXISTS `ki_nutzung` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`    INT UNSIGNED NOT NULL,
  `befehl`     VARCHAR(30)  NOT NULL,
  `zeichen`    INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME     DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_user_zeit` (`user_id`, `created_at`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
;

-- Was noch geprueft werden muss.
--
-- Warum eine Warteschlange und nicht gleich im Speicherweg: Eine Anfrage an
-- das Modell dauert ein bis zwei Sekunden. Die haengen sonst am Absenden des
-- Beitrags — der Verfasser wartet auf eine Pruefung, die ihn nichts angeht,
-- und bei einer Stoerung beim Anbieter wartet er die volle Zeitgrenze.
--
-- Der Text steht auch hier nicht: er liegt schon in `posts`, und eine zweite
-- Kopie waere eine zweite Stelle, die jemand loeschen muesste.
CREATE TABLE IF NOT EXISTS `ki_pruefung` (
  `post_id`    INT UNSIGNED NOT NULL PRIMARY KEY,
  `status`     ENUM('offen','fertig','fehler') NOT NULL DEFAULT 'offen',
  `versuche`   TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `befund`     VARCHAR(30)  DEFAULT NULL,
  `created_at` DATETIME     DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_status` (`status`, `created_at`),
  FOREIGN KEY (`post_id`) REFERENCES `posts`(`post_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
