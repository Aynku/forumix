-- Laeuft beim ersten Aktivieren und muss einen zweiten Durchlauf unbeschadet
-- ueberstehen.
CREATE TABLE IF NOT EXISTS `downloads` (
  `download_id`   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `titel`         VARCHAR(140)  NOT NULL,
  -- TEXT kann keinen Vorgabewert haben, deshalb NULL statt NOT NULL.
  `beschreibung`  TEXT          NULL,
  `kategorie`     VARCHAR(80)   NOT NULL DEFAULT '',
  -- Der Name auf der Platte wird erzeugt. Der Name des Hochladenden landet
  -- getrennt daneben und wird nur beim Ausliefern verwendet.
  `dateiname`     VARCHAR(120)  NOT NULL,
  `originalname`  VARCHAR(255)  NOT NULL DEFAULT '',
  `mime`          VARCHAR(100)  NOT NULL DEFAULT '',
  `groesse`       INT UNSIGNED  NOT NULL DEFAULT 0,
  `klicks`        INT UNSIGNED  NOT NULL DEFAULT 0,
  `created_by`    INT UNSIGNED  NOT NULL DEFAULT 0,
  `created_at`    DATETIME      DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_kategorie` (`kategorie`),
  INDEX `idx_erstellt`  (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
