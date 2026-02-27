-- ============================================================
--  oRent — QUICK FIX: Missing Tables + Admin User
--  Run in phpMyAdmin → SQL tab
--  Simple, no PREPARE/EXECUTE — works on all MySQL versions
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Create users table
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','staff') NOT NULL DEFAULT 'staff',
  `staff_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `idx_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Create staff_permissions table
CREATE TABLE IF NOT EXISTS `staff_permissions` (
  `user_id` int(11) NOT NULL,
  `permission` varchar(100) NOT NULL,
  PRIMARY KEY (`user_id`,`permission`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Create staff_activity_log table
CREATE TABLE IF NOT EXISTS `staff_activity_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_created` (`user_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Insert admin user (password: admin123)
INSERT IGNORE INTO `users` (`name`, `username`, `password_hash`, `role`, `is_active`)
VALUES (
  'Administrator',
  'admin',
  '$2y$10$4aOCumWeRMklHt5c2th7yeO9Rekj5044F0834eB9Gf6wRdf3KARxm',
  'admin',
  1
);

-- 5. Add assigned_staff_id to leads if missing
ALTER TABLE `leads` ADD COLUMN IF NOT EXISTS `assigned_staff_id` int(11) DEFAULT NULL;
ALTER TABLE `leads` ADD COLUMN IF NOT EXISTS `lost_reason` text DEFAULT NULL;

-- 6. Add missing reservation columns
ALTER TABLE `reservations` ADD COLUMN IF NOT EXISTS `deposit_amount` decimal(10,2) NOT NULL DEFAULT 0.00;
ALTER TABLE `reservations` ADD COLUMN IF NOT EXISTS `deposit_returned` decimal(10,2) NOT NULL DEFAULT 0.00;
ALTER TABLE `reservations` ADD COLUMN IF NOT EXISTS `delivery_charge` decimal(10,2) NOT NULL DEFAULT 0.00;
ALTER TABLE `reservations` ADD COLUMN IF NOT EXISTS `additional_charge` decimal(10,2) NOT NULL DEFAULT 0.00;
ALTER TABLE `reservations` ADD COLUMN IF NOT EXISTS `voucher_applied` decimal(10,2) NOT NULL DEFAULT 0.00;
ALTER TABLE `reservations` ADD COLUMN IF NOT EXISTS `early_return_credit` decimal(10,2) NOT NULL DEFAULT 0.00;
ALTER TABLE `reservations` ADD COLUMN IF NOT EXISTS `voucher_credit_issued` decimal(10,2) NOT NULL DEFAULT 0.00;
ALTER TABLE `reservations` ADD COLUMN IF NOT EXISTS `return_voucher_applied` decimal(10,2) NOT NULL DEFAULT 0.00;
ALTER TABLE `reservations` ADD COLUMN IF NOT EXISTS `delivery_payment_method` enum('cash','account','credit') DEFAULT NULL;
ALTER TABLE `reservations` ADD COLUMN IF NOT EXISTS `delivery_paid_amount` decimal(10,2) NOT NULL DEFAULT 0.00;
ALTER TABLE `reservations` ADD COLUMN IF NOT EXISTS `return_payment_method` enum('cash','account','credit') DEFAULT NULL;
ALTER TABLE `reservations` ADD COLUMN IF NOT EXISTS `return_paid_amount` decimal(10,2) NOT NULL DEFAULT 0.00;
ALTER TABLE `reservations` ADD COLUMN IF NOT EXISTS `km_limit` int(11) DEFAULT NULL;
ALTER TABLE `reservations` ADD COLUMN IF NOT EXISTS `extra_km_price` decimal(10,2) DEFAULT NULL;
ALTER TABLE `reservations` ADD COLUMN IF NOT EXISTS `km_driven` int(11) DEFAULT NULL;
ALTER TABLE `reservations` ADD COLUMN IF NOT EXISTS `km_overage_charge` decimal(10,2) NOT NULL DEFAULT 0.00;
ALTER TABLE `reservations` ADD COLUMN IF NOT EXISTS `damage_charge` decimal(10,2) NOT NULL DEFAULT 0.00;
ALTER TABLE `reservations` ADD COLUMN IF NOT EXISTS `discount_type` enum('percent','amount') DEFAULT NULL;
ALTER TABLE `reservations` ADD COLUMN IF NOT EXISTS `discount_value` decimal(10,2) NOT NULL DEFAULT 0.00;

-- 7. Add missing vehicle columns
ALTER TABLE `vehicles` ADD COLUMN IF NOT EXISTS `monthly_rate` decimal(10,2) DEFAULT NULL;
ALTER TABLE `vehicles` ADD COLUMN IF NOT EXISTS `rate_1day` decimal(10,2) DEFAULT NULL;
ALTER TABLE `vehicles` ADD COLUMN IF NOT EXISTS `rate_7day` decimal(10,2) DEFAULT NULL;
ALTER TABLE `vehicles` ADD COLUMN IF NOT EXISTS `rate_15day` decimal(10,2) DEFAULT NULL;
ALTER TABLE `vehicles` ADD COLUMN IF NOT EXISTS `rate_30day` decimal(10,2) DEFAULT NULL;
ALTER TABLE `vehicles` ADD COLUMN IF NOT EXISTS `image_url` varchar(500) DEFAULT NULL;
ALTER TABLE `vehicles` ADD COLUMN IF NOT EXISTS `vin` varchar(50) DEFAULT NULL;

-- 8. Add proof_file to clients
ALTER TABLE `clients` ADD COLUMN IF NOT EXISTS `proof_file` varchar(500) DEFAULT NULL;

-- 9. Vouchers table
CREATE TABLE IF NOT EXISTS `vouchers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `reservation_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `used` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- DONE! Login with: admin / admin123
-- ============================================================
