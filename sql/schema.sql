-- UniWallet — database schema
-- Import this file in phpMyAdmin (Import tab) or run:
--   mysql -u root -p < sql/schema.sql

CREATE DATABASE IF NOT EXISTS `uniwallet_app`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `uniwallet_app`;

-- Drop in reverse dependency order so re-importing is safe.
DROP TABLE IF EXISTS `savings_goals`;
DROP TABLE IF EXISTS `budgets`;
DROP TABLE IF EXISTS `expenses`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `users`;

-- ---------------------------------------------------------------- users
CREATE TABLE `users` (
  `user_id`       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `full_name`     VARCHAR(100) NOT NULL,
  `email`         VARCHAR(190) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `warn_pct`      TINYINT UNSIGNED NOT NULL DEFAULT 80,   -- budget bars turn amber at this %
  `theme`         VARCHAR(8) NOT NULL DEFAULT 'system',   -- 'light', 'dark' or 'system'
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `uq_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------- categories
-- Every user gets their own copy of the default set at registration,
-- so `user_id` is never NULL. `is_default` just marks the seeded rows.
CREATE TABLE `categories` (
  `category_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED NOT NULL,
  `name`        VARCHAR(60) NOT NULL,
  `icon`        VARCHAR(16) NOT NULL DEFAULT '📦',
  `is_default`  TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`category_id`),
  UNIQUE KEY `uq_cat_user_name` (`user_id`, `name`),
  CONSTRAINT `fk_cat_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------- expenses
-- category_id is nullable: deleting a category leaves its expenses in
-- place as "Uncategorised" rather than destroying spending history.
CREATE TABLE `expenses` (
  `expense_id`  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED NOT NULL,
  `category_id` INT UNSIGNED NULL DEFAULT NULL,
  `amount`      DECIMAL(10,2) NOT NULL,
  `spent_on`    DATE NOT NULL,
  `note`        VARCHAR(255) NULL DEFAULT NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`expense_id`),
  KEY `idx_exp_user_date` (`user_id`, `spent_on`),
  KEY `idx_exp_user_cat` (`user_id`, `category_id`),
  CONSTRAINT `fk_exp_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_exp_cat` FOREIGN KEY (`category_id`)
    REFERENCES `categories` (`category_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------- budgets
-- One table holds both kinds of limit, keyed by month ('YYYY-MM'):
--   category_id IS NULL      -> `total_limit`    = whole-month cap
--   category_id IS NOT NULL  -> `category_limit` = cap for that category
-- MySQL treats NULLs as distinct in UNIQUE keys, so the "one overall row
-- per month" rule is enforced in PHP (see includes/finance.php).
CREATE TABLE `budgets` (
  `budget_id`      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`        INT UNSIGNED NOT NULL,
  `month_year`     CHAR(7) NOT NULL COMMENT 'YYYY-MM',
  `category_id`    INT UNSIGNED NULL DEFAULT NULL,
  `total_limit`    DECIMAL(10,2) NULL DEFAULT NULL,
  `category_limit` DECIMAL(10,2) NULL DEFAULT NULL,
  PRIMARY KEY (`budget_id`),
  KEY `idx_budget_user_month` (`user_id`, `month_year`),
  CONSTRAINT `fk_budget_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_budget_cat` FOREIGN KEY (`category_id`)
    REFERENCES `categories` (`category_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------- savings_goals
CREATE TABLE `savings_goals` (
  `goal_id`       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`       INT UNSIGNED NOT NULL,
  `title`         VARCHAR(120) NOT NULL,
  `target_amount` DECIMAL(10,2) NOT NULL,
  `saved_amount`  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `deadline`      DATE NULL DEFAULT NULL,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`goal_id`),
  KEY `idx_goal_user` (`user_id`),
  CONSTRAINT `fk_goal_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
