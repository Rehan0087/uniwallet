-- Run once on a database created before the Profile & settings page existed.
-- (Fresh installs from schema.sql already have this column.)
USE `uniwallet`;
ALTER TABLE `users`
  ADD COLUMN `warn_pct` TINYINT UNSIGNED NOT NULL DEFAULT 80 AFTER `password_hash`;
