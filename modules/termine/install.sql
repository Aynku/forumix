-- Laeuft beim ersten Aktivieren des Moduls. Muss einen zweiten Durchlauf
-- unbeschadet ueberstehen: bricht das Aktivieren mittendrin ab, kommt die
-- Datei beim naechsten Versuch erneut dran.
CREATE TABLE IF NOT EXISTS `termine` (
  `termin_id`     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `titel`         VARCHAR(140)  NOT NULL,
  -- TEXT kann keinen Vorgabewert haben. Deshalb NULL erlaubt, statt die
  -- Spalte auf NOT NULL zu setzen und bei jedem INSERT daran zu denken.
  `beschreibung`  TEXT          NULL,
  `beginn`        DATETIME      NOT NULL,
  `ende`          DATETIME      NULL,
  `ort`           VARCHAR(140)  NOT NULL DEFAULT '',
  `created_by`    INT UNSIGNED  NOT NULL DEFAULT 0,
  `created_at`    DATETIME      DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_beginn` (`beginn`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
