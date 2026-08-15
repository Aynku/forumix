-- Laeuft beim ersten Aktivieren und muss einen zweiten Durchlauf unbeschadet
-- ueberstehen.
--
-- Die Nachrichten landen hier UND gehen per Mail raus. Wenn der Mailversand
-- klemmt — falsche Zugangsdaten, Empfaenger voll, Postfach kaputt — ist die
-- Nachricht trotzdem da. Ein Kontaktformular, das still verliert, ist
-- schlimmer als gar keins.
CREATE TABLE IF NOT EXISTS `kontakt_nachrichten` (
  `nachricht_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `absender`     VARCHAR(80)   NOT NULL,
  `email`        VARCHAR(190)  NOT NULL,
  `betreff`      VARCHAR(140)  NOT NULL,
  `nachricht`    TEXT          NULL,
  `user_id`      INT UNSIGNED  NOT NULL DEFAULT 0,
  `ip`           VARCHAR(45)   NOT NULL DEFAULT '',
  `versandt`     TINYINT(1)    NOT NULL DEFAULT 0,
  `gelesen`      TINYINT(1)    NOT NULL DEFAULT 0,
  `created_at`   DATETIME      DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_erstellt` (`created_at`),
  INDEX `idx_gelesen`  (`gelesen`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
