-- Run once on a database created before the dark theme existed.
-- (Fresh installs from schema.sql already have this column.)
USE `uniwallet_app`;
ALTER TABLE `users`
  ADD COLUMN `theme` VARCHAR(8) NOT NULL DEFAULT 'system' AFTER `warn_pct`;
