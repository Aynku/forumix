-- ═══════════════════════════════════════════════════════════════════════
-- Forumix – Komplettes Schema v0.18.1
-- ═══════════════════════════════════════════════════════════════════════
-- Wird vom Installer ausgeführt. Enthält ALLE Schema-Definitionen und
-- Default-Daten konsolidiert aus den historischen Migrationen v0.1 – v0.17.7.
--
-- Nach dem Lauf dieser Datei MUSS keine einzige Migration mehr nachgezogen
-- werden – das Schema ist vollständig.
-- ═══════════════════════════════════════════════════════════════════════

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";

-- ─── KERN: USERS ───────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `users` (
  `user_id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `username`         VARCHAR(50)  NOT NULL UNIQUE,
  `password`         VARCHAR(255) NOT NULL,
  `email`            VARCHAR(255) NOT NULL UNIQUE,
  `avatar`           VARCHAR(255) DEFAULT NULL,
  `signature`        TEXT         DEFAULT NULL,
  `post_count`       INT UNSIGNED DEFAULT 0,
  `rank`             VARCHAR(50)  DEFAULT 'Neuling',
  `is_admin`         TINYINT(1)   DEFAULT 0,
  `is_owner`         TINYINT(1)   DEFAULT 0,
  `is_mod`           TINYINT(1)   DEFAULT 0,
  `is_banned`        TINYINT(1)   DEFAULT 0,
  `is_deleted`       TINYINT(1)   DEFAULT 0,
  `ban_reason`       VARCHAR(255) DEFAULT NULL,
  `deleted_at`       DATETIME     DEFAULT NULL,
  `registration_ip`  VARCHAR(45)  DEFAULT NULL,
  `last_ip`          VARCHAR(45)  DEFAULT NULL,
  `registered_at`    DATETIME     DEFAULT CURRENT_TIMESTAMP,
  `last_login`       DATETIME     DEFAULT NULL,
  `last_post_at`     DATETIME     DEFAULT NULL,
  `language`         VARCHAR(5)   DEFAULT 'de',
  `theme`            VARCHAR(20)  DEFAULT 'auto',
  `notify_email`     TINYINT(1)   DEFAULT 0,
  `profile_banner`   VARCHAR(255) DEFAULT NULL,
  `location`         VARCHAR(100) DEFAULT '',
  `warning_points`   INT UNSIGNED DEFAULT 0,
  `email_verified`   TINYINT(1)   DEFAULT 0,
  `profile_views`    INT UNSIGNED DEFAULT 0,
  `is_approved`      TINYINT(1)   DEFAULT 1,
  `approval_note`    VARCHAR(500) DEFAULT NULL,
  -- Erweiterte Profil-Felder (v0.18.31)
  `profile_gender`     VARCHAR(50)  DEFAULT NULL,
  `profile_birthday`   DATE         DEFAULT NULL,
  `profile_location`   VARCHAR(100) DEFAULT NULL,
  `profile_occupation` VARCHAR(100) DEFAULT NULL,
  `profile_interests`  VARCHAR(500) DEFAULT NULL,
  `profile_about`      TEXT         DEFAULT NULL,
  -- JSON-Map mit Privacy-Toggles pro Feld: {"gender":"public","birthday":"members",...}
  -- Werte: "public" / "members" / "private"
  `profile_privacy`    TEXT         DEFAULT NULL,
  -- Doppel-Opt-In für E-Mail-Änderung (v0.18.48)
  `pending_email`         VARCHAR(255) DEFAULT NULL,
  `pending_email_token`   VARCHAR(255) DEFAULT NULL,
  `pending_email_expires` DATETIME     DEFAULT NULL,
  -- Letzte Aktivität (v0.18.52) — wird bei jedem Pageview + Post aktualisiert
  -- (last_login bleibt für echte Logins, nicht für Heartbeat)
  `last_activity_at`      DATETIME     DEFAULT NULL,
  INDEX `idx_is_deleted` (`is_deleted`),
  INDEX `idx_is_admin`   (`is_admin`),
  INDEX `idx_is_approved` (`is_approved`),
  INDEX `idx_is_mod`     (`is_mod`),
  INDEX `idx_last_activity` (`last_activity_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── KERN: KATEGORIEN, FOREN, THREADS, POSTS ───────────────────────────
CREATE TABLE IF NOT EXISTS `categories` (
  `cat_id`   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `title`    VARCHAR(100) NOT NULL,
  `position` INT UNSIGNED DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `boards` (
  `board_id`                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `cat_id`                    INT UNSIGNED NOT NULL,
  `title`                     VARCHAR(100) NOT NULL,
  `description`               VARCHAR(255) DEFAULT '',
  `password_hash`             VARCHAR(255) NULL,
  `position`                  INT UNSIGNED DEFAULT 0,
  `thread_count`              INT UNSIGNED DEFAULT 0,
  `post_count`                INT UNSIGNED DEFAULT 0,
  `last_post_id`              INT UNSIGNED DEFAULT NULL,
  `require_approval_threads`  TINYINT(1)   DEFAULT 0,
  `require_approval_replies`  TINYINT(1)   DEFAULT 0,
  FOREIGN KEY (`cat_id`) REFERENCES `categories`(`cat_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `threads` (
  `thread_id`    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `board_id`     INT UNSIGNED NOT NULL,
  `user_id`      INT UNSIGNED NOT NULL,
  `title`        VARCHAR(200) NOT NULL,
  `is_sticky`    TINYINT(1)   DEFAULT 0,
  `is_closed`    TINYINT(1)   DEFAULT 0,
  `is_approved`  TINYINT(1)   DEFAULT 1,
  `view_count`   INT UNSIGNED DEFAULT 0,
  `reply_count`  INT UNSIGNED DEFAULT 0,
  `created_at`   DATETIME     DEFAULT CURRENT_TIMESTAMP,
  `last_post_id` INT UNSIGNED DEFAULT NULL,
  INDEX `idx_board_lastpost` (`board_id`, `last_post_id`),
  INDEX `idx_approved` (`is_approved`),
  FOREIGN KEY (`board_id`) REFERENCES `boards`(`board_id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`)  REFERENCES `users`(`user_id`)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `posts` (
  `post_id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `thread_id`        INT UNSIGNED NOT NULL,
  `user_id`          INT UNSIGNED NOT NULL,
  `content`          TEXT         NOT NULL,
  `author_ip`        VARCHAR(45)  DEFAULT NULL,
  `created_at`       DATETIME     DEFAULT CURRENT_TIMESTAMP,
  `edited_at`        DATETIME     DEFAULT NULL,
  `edited_by`        INT UNSIGNED DEFAULT NULL,
  `is_approved`      TINYINT(1)   DEFAULT 1,
  `mod_edited_by`    INT UNSIGNED DEFAULT NULL,
  `mod_edit_reason`  VARCHAR(500) DEFAULT NULL,
  `mod_edited_at`    DATETIME     DEFAULT NULL,
  INDEX `idx_thread_created` (`thread_id`, `created_at`),
  INDEX `idx_user` (`user_id`),
  INDEX `idx_approved` (`is_approved`),
  FOREIGN KEY (`thread_id`) REFERENCES `threads`(`thread_id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`)   REFERENCES `users`(`user_id`)     ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── KERN: PM, MOD-LOG, IP-LOGS ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `private_messages` (
  `pm_id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `sender_id`           INT UNSIGNED NOT NULL,
  `receiver_id`         INT UNSIGNED NOT NULL,
  `subject`             VARCHAR(200) NOT NULL,
  `content`             TEXT         NOT NULL,
  `is_read`             TINYINT(1)   DEFAULT 0,
  `sent_at`             DATETIME     DEFAULT CURRENT_TIMESTAMP,
  `deleted_by_sender`   TINYINT(1)   DEFAULT 0,
  `deleted_by_receiver` TINYINT(1)   DEFAULT 0,
  INDEX `idx_receiver_read` (`receiver_id`, `is_read`),
  INDEX `idx_sender` (`sender_id`),
  FOREIGN KEY (`sender_id`)   REFERENCES `users`(`user_id`) ON DELETE CASCADE,
  FOREIGN KEY (`receiver_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `mod_log` (
  `log_id`      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `mod_id`      INT UNSIGNED NOT NULL,
  `action`      VARCHAR(50)  NOT NULL,
  `target_type` VARCHAR(50)  NOT NULL,
  `target_id`   INT UNSIGNED DEFAULT NULL,
  `reason`      VARCHAR(255) DEFAULT NULL,
  `created_at`  DATETIME     DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_mod` (`mod_id`),
  INDEX `idx_target` (`target_type`, `target_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `user_ip_log` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`    INT UNSIGNED NOT NULL,
  `ip`         VARCHAR(45) NOT NULL,
  `action`     ENUM('register','login','post') NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_user_ip` (`user_id`, `ip`),
  INDEX `idx_ip`      (`ip`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ip_bans` (
  `ban_id`     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `ip`         VARCHAR(45)  NOT NULL,
  `reason`     VARCHAR(255) DEFAULT '',
  `banned_by`  INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME     DEFAULT CURRENT_TIMESTAMP,
  `expires_at` DATETIME     DEFAULT NULL,
  UNIQUE KEY `unique_ip` (`ip`),
  INDEX `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── BOT-VISITS-LOG (v0.18.38) ────────────────────────────
-- Pro (Bot-Name + IP) eine Zeile mit aggregierten Statistiken.
-- Counter zählt wie oft dieser Bot von dieser IP gesehen wurde.
-- 'category': 'search' (Suchmaschinen) | 'ai' (KI-Crawler) |
--             'seo' (Marketing/SEO) | 'social' (Social-Media-Crawler) |
--             'monitoring' (Pingdom etc.) | 'archive' (Internet Archive) |
--             'suspect' (Stealth-Bot-Heuristik) | 'generic' (allgemein)
CREATE TABLE IF NOT EXISTS `bot_visits` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `bot_name`    VARCHAR(100) NOT NULL,
  `bot_icon`    VARCHAR(20)  DEFAULT '🤖',
  `category`    VARCHAR(20)  NOT NULL DEFAULT 'generic',
  `ip`          VARCHAR(45)  NOT NULL,
  `user_agent`  TEXT         DEFAULT NULL,
  `visit_count` INT UNSIGNED DEFAULT 1,
  `first_seen`  DATETIME     DEFAULT CURRENT_TIMESTAMP,
  `last_seen`   DATETIME     DEFAULT CURRENT_TIMESTAMP,
  `last_url`    VARCHAR(255) DEFAULT NULL,
  UNIQUE KEY `unique_bot_ip` (`bot_name`, `ip`),
  INDEX `idx_last_seen` (`last_seen`),
  INDEX `idx_category`  (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── ATTACHMENTS, NOTIFICATIONS, ABOS ──────────────────────────────────
CREATE TABLE IF NOT EXISTS `attachments` (
  `attachment_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `post_id`       INT UNSIGNED NOT NULL,
  `user_id`       INT UNSIGNED NOT NULL,
  `filename`      VARCHAR(255) NOT NULL,
  `original_name` VARCHAR(255) NOT NULL,
  `mime_type`     VARCHAR(100) NOT NULL,
  `size_bytes`    INT UNSIGNED NOT NULL,
  `is_image`      TINYINT(1) DEFAULT 0,
  `created_at`    DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_post` (`post_id`),
  FOREIGN KEY (`post_id`) REFERENCES `posts`(`post_id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `notifications` (
  `notif_id`     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`      INT UNSIGNED NOT NULL,
  `type`         ENUM('reply','mention','pm','warning','report','approved','rejected','mod_edit') NOT NULL,
  `from_user_id` INT UNSIGNED DEFAULT NULL,
  `thread_id`    INT UNSIGNED DEFAULT NULL,
  `post_id`      INT UNSIGNED DEFAULT NULL,
  `pm_id`        INT UNSIGNED DEFAULT NULL,
  `is_read`      TINYINT(1) DEFAULT 0,
  `created_at`   DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_user_read` (`user_id`, `is_read`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `thread_subscriptions` (
  `user_id`    INT UNSIGNED NOT NULL,
  `thread_id`  INT UNSIGNED NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`, `thread_id`),
  FOREIGN KEY (`user_id`)   REFERENCES `users`(`user_id`)     ON DELETE CASCADE,
  FOREIGN KEY (`thread_id`) REFERENCES `threads`(`thread_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── UMFRAGEN ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `polls` (
  `poll_id`      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `thread_id`    INT UNSIGNED NOT NULL UNIQUE,
  `question`     VARCHAR(255) NOT NULL,
  `multi_choice` TINYINT(1) DEFAULT 0,
  `expires_at`   DATETIME DEFAULT NULL,
  `created_at`   DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`thread_id`) REFERENCES `threads`(`thread_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `poll_options` (
  `option_id`   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `poll_id`     INT UNSIGNED NOT NULL,
  `text`        VARCHAR(200) NOT NULL,
  `vote_count`  INT UNSIGNED DEFAULT 0,
  `position`    INT UNSIGNED DEFAULT 0,
  FOREIGN KEY (`poll_id`) REFERENCES `polls`(`poll_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `poll_votes` (
  `poll_id`    INT UNSIGNED NOT NULL,
  `option_id`  INT UNSIGNED NOT NULL,
  `user_id`    INT UNSIGNED NOT NULL,
  `voted_at`   DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`poll_id`, `option_id`, `user_id`),
  FOREIGN KEY (`poll_id`)   REFERENCES `polls`(`poll_id`)         ON DELETE CASCADE,
  FOREIGN KEY (`option_id`) REFERENCES `poll_options`(`option_id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`)   REFERENCES `users`(`user_id`)         ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── REAKTIONEN, LESEZEICHEN, ONLINE, ACHIEVEMENTS ─────────────────────
CREATE TABLE IF NOT EXISTS `reactions` (
  `reaction_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `post_id`     INT UNSIGNED NOT NULL,
  `user_id`     INT UNSIGNED NOT NULL,
  `type`        VARCHAR(20) NOT NULL,
  `created_at`  DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `idx_unique` (`post_id`, `user_id`, `type`),
  INDEX `idx_post` (`post_id`),
  FOREIGN KEY (`post_id`) REFERENCES `posts`(`post_id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `bookmarks` (
  `bookmark_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`     INT UNSIGNED NOT NULL,
  `thread_id`   INT UNSIGNED DEFAULT NULL,
  `post_id`     INT UNSIGNED DEFAULT NULL,
  `note`        VARCHAR(255) DEFAULT '',
  `created_at`  DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_user` (`user_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `user_online` (
  `session_id` VARCHAR(64) NOT NULL PRIMARY KEY,
  `user_id`    INT UNSIGNED DEFAULT NULL,
  `ip`         VARCHAR(45) NOT NULL,
  `last_seen`  DATETIME DEFAULT CURRENT_TIMESTAMP,
  `location`   VARCHAR(255) DEFAULT '',
  `user_agent` TEXT DEFAULT NULL,
  INDEX `idx_user` (`user_id`),
  INDEX `idx_last_seen` (`last_seen`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `achievements` (
  `achievement_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `code`           VARCHAR(50) NOT NULL UNIQUE,
  `title`          VARCHAR(100) NOT NULL,
  `description`    VARCHAR(255) NOT NULL,
  `icon`           VARCHAR(10) DEFAULT '🏆',
  `points`         INT UNSIGNED DEFAULT 10
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `user_achievements` (
  `user_id`        INT UNSIGNED NOT NULL,
  `achievement_id` INT UNSIGNED NOT NULL,
  `earned_at`      DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`, `achievement_id`),
  FOREIGN KEY (`user_id`)        REFERENCES `users`(`user_id`)              ON DELETE CASCADE,
  FOREIGN KEY (`achievement_id`) REFERENCES `achievements`(`achievement_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── MODERATION: REPORTS, WARNUNGEN, EDITS, TAGS ───────────────────────
CREATE TABLE IF NOT EXISTS `reports` (
  `report_id`   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `reporter_id` INT UNSIGNED NOT NULL,
  `post_id`     INT UNSIGNED DEFAULT NULL,
  `reason`      VARCHAR(50)  NOT NULL,
  `comment`     TEXT         DEFAULT NULL,
  `status`      ENUM('open','resolved','dismissed','reviewed','actioned') DEFAULT 'open',
  `reviewed_by` INT UNSIGNED DEFAULT NULL,
  `reviewed_at` DATETIME     DEFAULT NULL,
  `created_at`  DATETIME     DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_status` (`status`),
  INDEX `idx_post`   (`post_id`),
  FOREIGN KEY (`reporter_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `warnings` (
  `warning_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`    INT UNSIGNED NOT NULL,
  `mod_id`     INT UNSIGNED NOT NULL,
  `points`     INT UNSIGNED NOT NULL DEFAULT 1,
  `reason`     VARCHAR(255) NOT NULL,
  `expires_at` DATETIME     DEFAULT NULL,
  `is_active`  TINYINT(1)   DEFAULT 1,
  `created_at` DATETIME     DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_user_active` (`user_id`, `is_active`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
  FOREIGN KEY (`mod_id`)  REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `post_edits` (
  `edit_id`     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `post_id`     INT UNSIGNED NOT NULL,
  `editor_id`   INT UNSIGNED NOT NULL,
  `old_content` TEXT NOT NULL,
  `new_content` TEXT NOT NULL,
  `reason`      VARCHAR(500) DEFAULT '',
  `created_at`  DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_post` (`post_id`),
  FOREIGN KEY (`post_id`) REFERENCES `posts`(`post_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tags` (
  `tag_id`      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `slug`        VARCHAR(50) NOT NULL UNIQUE,
  `name`        VARCHAR(50) NOT NULL,
  `description` VARCHAR(200) DEFAULT '',
  `color`       VARCHAR(20) DEFAULT '#888',
  `usage_count` INT UNSIGNED DEFAULT 0,
  `created_at`  DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `thread_tags` (
  `thread_id` INT UNSIGNED NOT NULL,
  `tag_id`    INT UNSIGNED NOT NULL,
  PRIMARY KEY (`thread_id`, `tag_id`),
  FOREIGN KEY (`thread_id`) REFERENCES `threads`(`thread_id`) ON DELETE CASCADE,
  FOREIGN KEY (`tag_id`)    REFERENCES `tags`(`tag_id`)       ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default-Tags (v0.18.42) – Forum-typische Thread-Kategorisierung
-- INSERT IGNORE: bei existierenden slugs (Unique-Index) wird übersprungen
INSERT IGNORE INTO `tags` (`slug`, `name`, `description`, `color`) VALUES
  ('frage',        'Frage',       '❓', '#3b82f6'),
  ('anleitung',    'Anleitung',   '📖', '#10b981'),
  ('bug',          'Bug',         '🐛', '#ef4444'),
  ('idee',         'Idee',        '💡', '#f59e0b'),
  ('ankuendigung', 'Ankündigung', '📣', '#8b5cf6'),
  ('geloest',      'Gelöst',      '✅', '#22c55e');

-- ─── SICHERHEIT: RESET, VERIFY, RATE-LIMITS, 2FA ───────────────────────
CREATE TABLE IF NOT EXISTS `password_resets` (
  `reset_id`   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`    INT UNSIGNED NOT NULL,
  `token` VARCHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used`       TINYINT(1) DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `idx_token` (`token`),
  INDEX `idx_user` (`user_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `email_verifications` (
  `verify_id`  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`    INT UNSIGNED NOT NULL,
  `token` VARCHAR(64) NOT NULL,
  `email`      VARCHAR(255) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `idx_token` (`token`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `rate_limits` (
  `key_hash`   VARCHAR(64) NOT NULL,
  `action`     VARCHAR(30) NOT NULL,
  `count`      INT UNSIGNED DEFAULT 1,
  `expires_at` DATETIME NOT NULL,
  PRIMARY KEY (`key_hash`, `action`),
  INDEX `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `thread_reads` (
  `user_id`   INT UNSIGNED NOT NULL,
  `thread_id` INT UNSIGNED NOT NULL,
  `read_at`   DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`, `thread_id`),
  FOREIGN KEY (`user_id`)   REFERENCES `users`(`user_id`)     ON DELETE CASCADE,
  FOREIGN KEY (`thread_id`) REFERENCES `threads`(`thread_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `user_2fa` (
  `user_id`       INT UNSIGNED NOT NULL PRIMARY KEY,
  `secret`        VARCHAR(64) NOT NULL,
  `backup_codes`  TEXT DEFAULT NULL,
  `enabled`       TINYINT(1) DEFAULT 0,
  `confirmed_at`  DATETIME DEFAULT NULL,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── FORUM-MODERATOREN ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `board_moderators` (
  `board_id`    INT UNSIGNED NOT NULL,
  `user_id`     INT UNSIGNED NOT NULL,
  `assigned_by` INT UNSIGNED DEFAULT NULL,
  `assigned_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`board_id`, `user_id`),
  INDEX `idx_user` (`user_id`),
  FOREIGN KEY (`board_id`) REFERENCES `boards`(`board_id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`)  REFERENCES `users`(`user_id`)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── REMEMBER-ME-TOKENS ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `remember_tokens` (
  `token_id`       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`        INT UNSIGNED NOT NULL,
  `selector`       VARCHAR(32) NOT NULL,
  `validator_hash` VARCHAR(64) NOT NULL,
  `user_agent`     VARCHAR(255) DEFAULT '',
  `ip_address`     VARCHAR(45) DEFAULT '',
  `created_at`     DATETIME DEFAULT CURRENT_TIMESTAMP,
  `last_used`      DATETIME DEFAULT CURRENT_TIMESTAMP,
  `expires_at`     DATETIME NOT NULL,
  UNIQUE KEY `idx_selector` (`selector`),
  INDEX `idx_user` (`user_id`),
  INDEX `idx_expires` (`expires_at`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── GESPEICHERTE SUCHEN ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `saved_searches` (
  `search_id`  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`    INT UNSIGNED NOT NULL,
  `name`       VARCHAR(100) NOT NULL,
  `params`     TEXT NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_user` (`user_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── MODUL-SYSTEM ──────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `modules` (
  `module_id`    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `code`         VARCHAR(50) NOT NULL UNIQUE,
  `name`         VARCHAR(100) NOT NULL,
  `enabled`      TINYINT(1) DEFAULT 0,
  `version`      VARCHAR(20) DEFAULT '1.0',
  `installed_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `config`       TEXT DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── SMILEYS ───────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `smileys` (
  `smiley_id`    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `code`         VARCHAR(30) NOT NULL UNIQUE,
  `emoji`        VARCHAR(10) NOT NULL,
  `description`  VARCHAR(100) NOT NULL DEFAULT '',
  `is_in_picker` TINYINT(1)  DEFAULT 0,
  `position`     INT UNSIGNED DEFAULT 0,
  INDEX `idx_picker` (`is_in_picker`, `position`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── SETTINGS (KEY-VALUE) ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `settings` (
  `setting_key`   VARCHAR(100) NOT NULL PRIMARY KEY,
  `setting_value` TEXT DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── STATISCHE SEITEN (Impressum, Datenschutz, Nutzungsbedingungen) ────
CREATE TABLE IF NOT EXISTS `pages` (
  `page_id`    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `slug`       VARCHAR(50) NOT NULL UNIQUE,
  `title`      VARCHAR(200) NOT NULL,
  `content`    LONGTEXT NOT NULL,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── TEMPLATE-OVERRIDES (Admin-Anpassungen an .tpl.html-Dateien) ────────
CREATE TABLE IF NOT EXISTS `template_overrides` (
  `template_name`    VARCHAR(120) NOT NULL PRIMARY KEY,
  `content`          LONGTEXT NOT NULL,
  `original_at_save` LONGTEXT NOT NULL,
  `updated_at`       DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by`       INT UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── CAPTCHA-FEHLVERSUCHE (für adaptive CAPTCHA-Anzeige) ────────────────
CREATE TABLE IF NOT EXISTS `captcha_attempts` (
  `ip_hash`    CHAR(64)     NOT NULL,
  `action`     VARCHAR(50)  NOT NULL,
  `attempts`   INT UNSIGNED NOT NULL DEFAULT 0,
  `expires_at` DATETIME     NOT NULL,
  PRIMARY KEY (`ip_hash`, `action`),
  INDEX `idx_captcha_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── EINLADUNGEN ───────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `invitations` (
  `invitation_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `code`          VARCHAR(32)  NOT NULL UNIQUE,
  `email`         VARCHAR(255) DEFAULT NULL,   -- NULL = universal, gesetzt = E-Mail-gebunden
  `note`          VARCHAR(255) DEFAULT NULL,   -- interne Notiz, sichtbar im Admin
  `created_by`    INT UNSIGNED NOT NULL,
  `created_at`    DATETIME     DEFAULT CURRENT_TIMESTAMP,
  `expires_at`    DATETIME     NOT NULL,
  `used_by`       INT UNSIGNED DEFAULT NULL,   -- user_id wenn eingelöst
  `used_at`       DATETIME     DEFAULT NULL,
  `revoked_at`    DATETIME     DEFAULT NULL,
  `mail_sent_at`  DATETIME     DEFAULT NULL,
  INDEX `idx_code`       (`code`),
  INDEX `idx_email`      (`email`),
  INDEX `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ═══════════════════════════════════════════════════════════════════════
-- DEFAULT-DATEN
-- ═══════════════════════════════════════════════════════════════════════

-- ─── Forum-Grundeinstellungen ──────────────────────────────────────────
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES
  ('forum_version',          '1.9.1'),
  ('forum_footer_text',      ''),
  ('forum_footer_links',     ''),
  -- Muss zum Rueckfall in bootstrap.php (forum_accent_color) passen. Der wird
  -- nie erreicht, weil der Installer die Einstellung immer schreibt — stand
  -- hier ein anderer Wert, kam JEDE frische Installation in einer Farbe hoch,
  -- die der Code fuer den Standard nicht haelt. Bis 0.25.1 war das so: Code
  -- sagte Indigo, Installer schrieb Violett.
  ('forum_accent_color',     '4f46e5'),
  ('show_ips_to_mods',       '1'),
  ('warning_ban_threshold',  '10'),
  ('registration_enabled',   '1'),
  ('require_email_verify',   '0');

-- ─── Theme-Tokens ──────────────────────────────────────────────────────
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES
  ('theme_color_bg_body',          '0d0f13'),
  ('theme_color_bg_surface',       '15181e'),
  ('theme_color_bg_surface2',      '1b1f26'),
  ('theme_color_text_base',        'e4e6ea'),
  ('theme_color_text_muted',       '9aa0ab'),
  ('theme_color_text_faint',       '6b7280'),
  ('theme_color_border',           '252a33'),
  ('theme_color_border_strong',    '333a45'),
  ('theme_color_success',          '4ade80'),
  ('theme_color_warning',          'fbbf24'),
  ('theme_color_danger',           'f87171'),
  ('theme_color_bg_body_light',       'f5f6f8'),
  ('theme_color_bg_surface_light',    'ffffff'),
  ('theme_color_bg_surface2_light',   'eef0f4'),
  ('theme_color_text_base_light',     '1a1d23'),
  ('theme_color_text_muted_light',    '585e6b'),
  ('theme_color_text_faint_light',    '8b91a0'),
  ('theme_color_border_light',        'e3e6ec'),
  ('theme_color_border_strong_light', 'ccd1da'),
  ('theme_font_family',     'system'),
  ('theme_font_custom',     ''),
  ('theme_font_size_base',  '14'),
  ('theme_radius_preset',   'medium'),
  ('theme_density',         'normal'),
  ('theme_header_style',    'inline'),
  ('captcha_enabled',       '1'),
  ('captcha_threshold',     '3'),
  ('captcha_min_time',      '1500'),
  ('registration_approval_required', '0'),
  ('registration_terms_required',    '1'),
  ('theme_max_width',       '1200');

-- ─── Standard-Achievements ─────────────────────────────────────────────
INSERT IGNORE INTO `achievements` (`code`, `title`, `description`, `icon`, `points`) VALUES
  ('first_post',       '📝 Erster Beitrag',       'Du hast deinen ersten Beitrag verfasst', '📝', 5),
  ('posts_10',         '✏️ Aktives Mitglied',     '10 Beiträge geschrieben',                '✏️', 10),
  ('posts_50',         '💬 Plaudertasche',        '50 Beiträge geschrieben',                '💬', 25),
  ('posts_100',        '🗣️ Vielschreiber',        '100 Beiträge geschrieben',               '🗣️', 50),
  ('posts_500',        '📚 Forum-Stammgast',      '500 Beiträge geschrieben',               '📚', 100),
  ('posts_1000',       '👑 Forum-Legende',        '1000 Beiträge geschrieben',              '👑', 250),
  ('first_thread',     '🆕 Themen-Eröffner',      'Dein erstes Thema erstellt',             '🆕', 10),
  ('threads_10',       '📋 Themen-Sammler',       '10 Themen erstellt',                     '📋', 30),
  ('first_reaction',   '👍 Gefällt mir!',         'Du hast deine erste Reaktion vergeben',  '👍', 5),
  ('received_10',      '⭐ Beliebt',              '10 Reaktionen erhalten',                  '⭐', 25),
  ('received_50',      '🌟 Sehr beliebt',         '50 Reaktionen erhalten',                  '🌟', 75),
  ('received_100',     '💫 Star',                 '100 Reaktionen erhalten',                 '💫', 150),
  ('bookmarker',       '🔖 Sammler',              '10 Lesezeichen gespeichert',              '🔖', 15),
  ('mention_5',        '📢 Bekannt',              '5x in Posts erwähnt worden',             '📢', 20),
  ('birthday_1y',      '🎂 Ein Jahr dabei',       '1 Jahr Forum-Mitglied',                  '🎂', 50),
  ('birthday_2y',      '🎉 Zwei Jahre dabei',     '2 Jahre Forum-Mitglied',                 '🎉', 100),
  ('avatar_changed',   '🎨 Stylist',              'Ein Avatar hochgeladen',                  '🎨', 5),
  ('signature_set',    '✒️ Signatur-Schöpfer',    'Eine Signatur eingerichtet',              '✒️', 5),
  ('poll_creator',     '📊 Meinungsforscher',     'Eine Umfrage erstellt',                  '📊', 15),
  ('pm_sent',          '📨 Briefträger',          'Erste PN versendet',                     '📨', 5);

-- ─── Standard-Smileys ──────────────────────────────────────────────────
INSERT IGNORE INTO `smileys` (`code`, `emoji`, `description`, `is_in_picker`, `position`) VALUES
  (':)', '🙂', 'Lächeln', 1, 1),
  (':-)', '🙂', 'Lächeln', 0, 0),
  (':D', '😄', 'Grinsen', 1, 2),
  (':-D', '😄', 'Grinsen', 0, 0),
  ('xD', '😆', 'Lachen', 0, 0),
  (':P', '😛', 'Zunge', 1, 4),
  (':-P', '😛', 'Zunge', 0, 0),
  (';)', '😉', 'Zwinkern', 1, 3),
  (';-)', '😉', 'Zwinkern', 0, 0),
  (':(', '🙁', 'Traurig', 1, 5),
  (':-(', '🙁', 'Traurig', 0, 0),
  (':''(', '😢', 'Weinen', 1, 6),
  (':o', '😮', 'Überrascht', 1, 7),
  (':-o', '😮', 'Überrascht', 0, 0),
  (':O', '😲', 'Schock', 0, 0),
  (':*', '😘', 'Kuss', 1, 8),
  (':-*', '😘', 'Kuss', 0, 0),
  ('<3', '❤️', 'Herz', 1, 9),
  ('</3', '💔', 'Gebrochen', 0, 0),
  (':|', '😐', 'Neutral', 0, 0),
  (':-|', '😐', 'Neutral', 0, 0),
  (':/', '😕', 'Skeptisch', 0, 0),
  (':-/', '😕', 'Skeptisch', 0, 0),
  ('B)', '😎', 'Cool', 1, 14),
  ('8)', '😎', 'Cool', 0, 0),
  ('o.O', '😳', 'Verwirrt', 0, 0),
  ('O.o', '😳', 'Verwirrt', 0, 0),
  ('>:(', '😠', 'Wütend', 0, 0),
  ('>:)', '😈', 'Teuflisch', 0, 0),
  (':zzz:', '😴', 'Müde', 0, 0),
  (':rofl:', '🤣', 'ROFL', 1, 11),
  (':lol:', '😂', 'LOL', 1, 10),
  (':love:', '😍', 'Verliebt', 1, 12),
  (':think:', '🤔', 'Denken', 1, 13),
  (':facepalm:', '🤦', 'Facepalm', 1, 15),
  (':shrug:', '🤷', 'Schulterzucken', 1, 16),
  (':wave:', '👋', 'Winken', 1, 24),
  (':thumbsup:', '👍', 'Daumen hoch', 1, 17),
  (':thumbsdown:', '👎', 'Daumen runter', 1, 18),
  (':clap:', '👏', 'Klatschen', 0, 0),
  (':fire:', '🔥', 'Feuer', 1, 19),
  (':100:', '💯', '100', 1, 20),
  (':check:', '✅', 'Häkchen', 0, 0),
  (':x:', '❌', 'Kreuz', 0, 0),
  (':party:', '🥳', 'Party', 1, 21),
  (':beer:', '🍺', 'Bier', 0, 0),
  (':coffee:', '☕', 'Kaffee', 0, 0),
  (':pizza:', '🍕', 'Pizza', 0, 0),
  (':rocket:', '🚀', 'Rakete', 1, 22),
  (':warning:', '⚠️', 'Warnung', 0, 0),
  (':idea:', '💡', 'Idee', 1, 23),
  (':eyes:', '👀', 'Augen', 0, 0);

-- ─── Statische Seiten (Impressum, Datenschutz, Nutzungsbedingungen) ─────
INSERT IGNORE INTO `pages` (`slug`, `title`, `content`) VALUES
  ('imprint', 'Impressum', '[h2]Angaben gemäß § 5 TMG[/h2]
{{name}}
{{street}}
{{city}}

[h2]Kontakt[/h2]
E-Mail: [url=mailto:{{email}}]{{email}}[/url]

[h2]Verantwortlich für den Inhalt nach § 18 Abs. 2 MStV[/h2]
{{name}} (Anschrift wie oben)

[h2]Haftungsausschluss[/h2]

[h3]Haftung für Inhalte[/h3]
Als Diensteanbieter sind wir gemäß § 7 Abs. 1 TMG für eigene Inhalte auf diesen Seiten nach den allgemeinen Gesetzen verantwortlich. Nach §§ 8 bis 10 TMG sind wir als Diensteanbieter jedoch nicht verpflichtet, übermittelte oder gespeicherte fremde Informationen zu überwachen oder nach Umständen zu forschen, die auf eine rechtswidrige Tätigkeit hinweisen.

Verpflichtungen zur Entfernung oder Sperrung der Nutzung von Informationen nach den allgemeinen Gesetzen bleiben hiervon unberührt. Eine diesbezügliche Haftung ist jedoch erst ab dem Zeitpunkt der Kenntnis einer konkreten Rechtsverletzung möglich. Bei Bekanntwerden von entsprechenden Rechtsverletzungen werden wir diese Inhalte umgehend entfernen.

[h3]Haftung für Links[/h3]
Unser Angebot enthält Links zu externen Webseiten Dritter, auf deren Inhalte wir keinen Einfluss haben. Deshalb können wir für diese fremden Inhalte auch keine Gewähr übernehmen. Für die Inhalte der verlinkten Seiten ist stets der jeweilige Anbieter oder Betreiber der Seiten verantwortlich. Die verlinkten Seiten wurden zum Zeitpunkt der Verlinkung auf mögliche Rechtsverstöße überprüft. Rechtswidrige Inhalte waren zum Zeitpunkt der Verlinkung nicht erkennbar.

[h3]Urheberrecht[/h3]
Die durch die Seitenbetreiber erstellten Inhalte und Werke auf diesen Seiten unterliegen dem deutschen Urheberrecht. Die Vervielfältigung, Bearbeitung, Verbreitung und jede Art der Verwertung außerhalb der Grenzen des Urheberrechtes bedürfen der schriftlichen Zustimmung des jeweiligen Autors bzw. Erstellers. Downloads und Kopien dieser Seite sind nur für den privaten, nicht kommerziellen Gebrauch gestattet.

[hr]
Stand: {{month_year}}'),
  ('privacy', 'Datenschutzerklärung', '[h2]1. Verantwortlicher[/h2]
Verantwortlich für die Datenverarbeitung auf dieser Website ist:
{{name}}
{{street}}
{{city}}
E-Mail: [url=mailto:{{email}}]{{email}}[/url]

[h2]2. Erhebung und Speicherung personenbezogener Daten[/h2]

[h3]2.1 Beim Besuch der Website[/h3]
Beim Aufruf unserer Website werden durch den Webserver automatisch Informationen erfasst und in sogenannten Server-Log-Dateien gespeichert. Folgende Daten werden hierbei ohne dein Zutun erhoben:
[list]
[*]IP-Adresse des anfragenden Rechners
[*]Datum und Uhrzeit des Zugriffs
[*]Name und URL der abgerufenen Datei
[*]Website, von der aus der Zugriff erfolgt (Referrer)
[*]verwendeter Browser und ggf. das Betriebssystem deines Rechners
[/list]
Diese Daten dienen der Gewährleistung eines reibungslosen Verbindungsaufbaus, der Komfortabilität der Nutzung, der Auswertung der Systemsicherheit und -stabilität sowie zu administrativen Zwecken. Rechtsgrundlage ist Art. 6 Abs. 1 lit. f DSGVO.

[h3]2.2 Bei Registrierung und Nutzung des Forums[/h3]
Bei der Registrierung werden folgende Pflichtangaben erhoben und gespeichert:
[list]
[*]Benutzername (frei wählbar, kann ein Pseudonym sein)
[*]E-Mail-Adresse (für Account-Bestätigung, Passwort-Reset und ggf. Benachrichtigungen)
[*]Passwort (verschlüsselt gespeichert)
[*]Registrierungs-Datum und -Uhrzeit
[*]IP-Adresse zum Zeitpunkt der Registrierung
[/list]
Optional kannst du weitere Daten hinterlegen (Avatar, Signatur, „Über mich" etc.). Diese sind freiwillig und können jederzeit geändert oder gelöscht werden.

[h2]3. Rechtsgrundlage[/h2]
Die Verarbeitung deiner Daten erfolgt auf Grundlage von Art. 6 Abs. 1 lit. b DSGVO (Vertragserfüllung — Bereitstellung des Forums) sowie auf Grundlage deiner Einwilligung gemäß Art. 6 Abs. 1 lit. a DSGVO (z.B. bei optionalen Profilangaben).

[h2]4. Cookies und lokale Speicherung[/h2]
Das Forum verwendet technisch notwendige Cookies (z.B. für Session und Login). Diese werden ohne deine ausdrückliche Einwilligung verarbeitet, da sie für den Betrieb erforderlich sind (Art. 6 Abs. 1 lit. f DSGVO).

Zusätzlich nutzen wir die lokale Browser-Speicherung (LocalStorage) für Komfort-Funktionen wie das automatische Speichern von Beitragsentwürfen. Diese Daten verbleiben ausschließlich auf deinem Gerät.

[h2]5. Weitergabe von Daten[/h2]
Eine Übertragung deiner persönlichen Daten an Dritte findet nicht statt, außer:
[list]
[*]du hast deine ausdrückliche Einwilligung dazu erteilt
[*]die Weitergabe ist zur Geltendmachung, Ausübung oder Verteidigung von Rechtsansprüchen erforderlich
[*]eine gesetzliche Verpflichtung zur Weitergabe besteht
[/list]

[h2]6. Speicherdauer[/h2]
Wir speichern deine Daten nur so lange, wie es für die Erfüllung der jeweiligen Zwecke erforderlich ist oder gesetzliche Aufbewahrungsfristen bestehen.

Server-Log-Daten werden nach maximal 30 Tagen automatisch gelöscht. Bei Löschung deines Accounts werden alle persönlichen Daten unverzüglich anonymisiert (Beiträge werden einem anonymen System-User zugeordnet, damit Diskussionen für andere User erhalten bleiben).

[h2]7. Deine Rechte[/h2]
Du hast nach DSGVO folgende Rechte:
[list]
[*]Recht auf Auskunft (Art. 15 DSGVO)
[*]Recht auf Berichtigung (Art. 16 DSGVO)
[*]Recht auf Löschung (Art. 17 DSGVO)
[*]Recht auf Einschränkung der Verarbeitung (Art. 18 DSGVO)
[*]Recht auf Datenübertragbarkeit (Art. 20 DSGVO)
[*]Recht auf Widerspruch gegen die Verarbeitung (Art. 21 DSGVO)
[*]Beschwerderecht bei der zuständigen Aufsichtsbehörde (Art. 77 DSGVO)
[/list]
Zur Ausübung dieser Rechte wende dich bitte per E-Mail an die im Impressum genannte Adresse.

[h2]8. Löschung des Accounts[/h2]
Du kannst die Löschung deines Accounts jederzeit per E-Mail an die im Impressum genannte Adresse oder über die Account-Einstellungen beantragen. Nach der Löschung werden deine persönlichen Daten anonymisiert.

[h2]9. Datensicherheit[/h2]
Wir treffen technische und organisatorische Sicherheitsmaßnahmen, um deine Daten gegen zufällige oder vorsätzliche Manipulationen, Verlust, Zerstörung oder gegen den Zugriff unberechtigter Personen zu schützen. Die Übertragung deiner Daten erfolgt verschlüsselt über HTTPS.

[h2]10. Änderungen dieser Datenschutzerklärung[/h2]
Wir behalten uns vor, diese Datenschutzerklärung anzupassen, wenn rechtliche Änderungen oder Änderungen am Forum dies erforderlich machen. Die jeweils aktuelle Version ist hier abrufbar.

[hr]
Stand: {{month_year}}'),
  ('terms', 'Nutzungsbedingungen', '[i]ℹ️ Mit der Registrierung und Nutzung dieses Forums akzeptierst du die nachfolgenden Bedingungen. Wenn du nicht einverstanden bist, nutze das Forum bitte nicht.[/i]

[h2]1. Geltungsbereich[/h2]
Diese Nutzungsbedingungen regeln das Verhältnis zwischen dem Betreiber dieses Forums (siehe Impressum) und den registrierten Nutzer:innen sowie Besucher:innen. Mit der Nutzung des Forums – insbesondere mit der Registrierung – erklärst du dich mit diesen Bedingungen einverstanden.

[h2]2. Registrierung und Account[/h2]
[list]
[*]Die Registrierung ist kostenlos und freiwillig. Es besteht kein Anspruch auf Aufnahme.
[*]Du musst mindestens 16 Jahre alt sein, um dich zu registrieren. Jüngere Personen benötigen die Zustimmung eines Erziehungsberechtigten.
[*]Pro Person ist nur ein Account erlaubt. Mehrfach-Accounts (Sockenpuppen) sind untersagt.
[*]Du bist verpflichtet, wahrheitsgemäße Angaben (insbesondere bei der E-Mail-Adresse) zu machen.
[*]Du bist für die Sicherheit deines Passworts selbst verantwortlich. Gib es nicht weiter.
[*]Bei Verdacht auf unbefugte Account-Nutzung informiere bitte umgehend einen Administrator.
[/list]

[h2]3. Verhaltensregeln (Netiquette)[/h2]
Im Forum gelten die Grundregeln respektvollen Miteinanders. Folgendes ist untersagt:
[list]
[*][b]Beleidigungen, Bedrohungen, Diskriminierung[/b] jeglicher Art (Rassismus, Sexismus, Homophobie, Antisemitismus, religiöse oder politische Hetze etc.)
[*][b]Belästigung, Stalking, Mobbing[/b] einzelner Personen oder Gruppen
[*][b]Spam, Werbung[/b] ohne ausdrückliche Erlaubnis des Betreibers
[*][b]Pornografische, gewaltverherrlichende[/b] oder anderweitig anstößige Inhalte
[*][b]Inhalte, die gegen geltendes Recht[/b] verstoßen (Volksverhetzung, Aufruf zu Straftaten etc.)
[*][b]Urheberrechtsverletzungen[/b] – poste keine fremden Texte, Bilder, Videos ohne Erlaubnis
[*][b]Veröffentlichung personenbezogener Daten[/b] Dritter ohne deren Einwilligung (Namen, Adressen, Fotos, private Nachrichten etc.)
[*]Schreiben in [b]FORTLAUFENDEN GROSSBUCHSTABEN[/b] oder exzessives Verwenden von Sonderzeichen
[/list]

[h2]4. Inhalte und Urheberrecht[/h2]
Für alle von dir veröffentlichten Inhalte (Beiträge, Bilder, Avatare, Signaturen) bist du selbst verantwortlich. Mit der Veröffentlichung versicherst du, dass:
[list]
[*]du der Urheber bist oder die nötigen Rechte zur Veröffentlichung besitzt
[*]die Inhalte nicht gegen Rechte Dritter (Persönlichkeitsrechte, Urheberrechte, etc.) verstoßen
[*]die Inhalte nicht gegen geltendes Recht verstoßen
[/list]
Mit der Veröffentlichung räumst du dem Forumsbetreiber ein einfaches, zeitlich und räumlich unbeschränktes Nutzungsrecht ein, deine Beiträge im Rahmen des Forums anzuzeigen, zu speichern und technisch zu verarbeiten. Du behältst das Urheberrecht an deinen Inhalten.

[h2]5. Moderation und Sanktionen[/h2]
Das Forum-Team (Owner, Administratoren, Moderatoren) behält sich vor, bei Verstößen gegen diese Bedingungen oder die Netiquette:
[list]
[*]Beiträge zu bearbeiten, zu verschieben, zu sperren oder zu löschen
[*]Verwarnungen auszusprechen
[*]Accounts zu sperren (temporär oder dauerhaft)
[*]IP-Adressen zu sperren
[/list]
Bei groben oder wiederholten Verstößen kann eine sofortige Sperrung ohne vorherige Warnung erfolgen. Ein Anspruch auf Begründung oder Rückerstattung besteht nicht (Forum ist kostenlos).

[h2]6. Haftung[/h2]
Der Forumsbetreiber haftet nicht für die Inhalte der Beiträge der Nutzer. Die jeweiligen Verfasser sind für ihre Inhalte selbst verantwortlich. Bei rechtswidrigen Inhalten erfolgt nach Bekanntwerden umgehend eine Entfernung.

Der Betreiber bemüht sich um einen reibungslosen Betrieb, kann aber keine ständige Verfügbarkeit zusichern. Bei Wartung, technischen Problemen oder Angriffen kann das Forum zeitweise nicht erreichbar sein.

[h2]7. Datenschutz[/h2]
Für Details zur Datenverarbeitung siehe die [url=/privacy.php]Datenschutzerklärung[/url].

[h2]8. Änderungen[/h2]
Der Betreiber behält sich vor, diese Nutzungsbedingungen anzupassen. Wesentliche Änderungen werden im Forum bekanntgegeben. Die fortgesetzte Nutzung nach einer Änderung gilt als Zustimmung.

[h2]9. Schlussbestimmungen[/h2]
Sollten einzelne Bestimmungen dieser Nutzungsbedingungen unwirksam sein, bleibt die Wirksamkeit der übrigen Bestimmungen unberührt. Es gilt deutsches Recht.

[hr]
Stand: {{month_year}}');

-- ─── Demo-Kategorien und -Foren ────────────────────────────────────────
INSERT IGNORE INTO `categories` (`cat_id`, `title`, `position`) VALUES
  (1, 'Allgemein', 1),
  (2, 'Support',   2);

INSERT IGNORE INTO `boards` (`board_id`, `cat_id`, `title`, `description`, `position`) VALUES
  (1, 1, 'Ankündigungen',  'Offizielle News',                  1),
  (2, 1, 'Off-Topic',      'Alles, was woanders nicht passt',  2),
  (3, 2, 'Hilfe & Fragen', 'Fragen rund ums Forum',            1),
  (4, 2, 'Fehlerberichte', 'Bugs melden',                      2);
