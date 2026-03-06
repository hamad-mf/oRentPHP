-- Release: 2026-03-06_vehicle_collation_hotfix
-- Author: AI assistant (backfill)
-- Safe: rerunnable (may rebuild tables)
-- Notes: Align database/table collations to utf8mb4_unicode_ci for vehicle modules.

ALTER DATABASE `u230826074_orentin`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

ALTER TABLE `vehicles`
  CONVERT TO CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

ALTER TABLE `vehicle_images`
  CONVERT TO CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

ALTER TABLE `vehicle_requests`
  CONVERT TO CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
