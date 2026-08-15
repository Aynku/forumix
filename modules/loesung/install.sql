-- Laeuft beim ersten Aktivieren und muss einen zweiten Durchlauf unbeschadet
-- ueberstehen.
--
-- Ein Thema hat hoechstens eine Loesung, deshalb ist thread_id der
-- Primaerschluessel. Eine zweite Markierung ersetzt die erste, statt daneben
-- zu liegen — das erzwingt die Datenbank und nicht der Code.
CREATE TABLE IF NOT EXISTS `loesungen` (
  `thread_id`   INT UNSIGNED NOT NULL PRIMARY KEY,
  `post_id`     INT UNSIGNED NOT NULL,
  `gesetzt_von` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`  DATETIME     DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_post` (`post_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
