-- Caffe BMS — Core Schema
-- MySQL 8.0 / InnoDB / utf8mb4
--
-- Renamed from the Gym Guardian-derived draft: `gyms` -> `cafes`, `gym_id` -> `cafe_id`
-- everywhere. Dropped `membership_plans` and the gym-membership columns on `users`
-- (trainer_description, trainer_expiry, company_sponsor, membership_expiry, member_code,
-- plan_id, last_payment_method) since `users` in this system is staff-only — customers
-- are not required to have accounts (see README: orders carry customer_name/customer_contact
-- as plain fields).

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------------
-- roles — global staff role definitions (admin, manager, barista, cashier, ...)
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_role_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------------------
-- cafes — multi-tenant root (renamed from `gyms`)
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `cafes`;
CREATE TABLE `cafes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `address` text,
  `contact_phone` varchar(20) DEFAULT NULL,
  `status` enum('active','suspended','trial') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------------------
-- users — staff accounts only (barista, cashier, manager, admin)
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone_number` varchar(20) NOT NULL,
  `password` varchar(255) NOT NULL,
  `profile_pic` varchar(255) DEFAULT NULL,
  `cafe_id` bigint unsigned DEFAULT NULL,
  `role_id` bigint unsigned NOT NULL,
  `status` varchar(20) DEFAULT 'active',
  `last_news_check` datetime DEFAULT '2020-01-01 00:00:00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deletion_scheduled_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `fk_user_cafe` (`cafe_id`),
  KEY `fk_user_role` (`role_id`),
  CONSTRAINT `fk_user_cafe` FOREIGN KEY (`cafe_id`) REFERENCES `cafes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_user_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------------------
-- login_attempts — brute-force tracking, unscoped by tenant on purpose
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `login_attempts`;
CREATE TABLE `login_attempts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `email` varchar(255) NOT NULL,
  `attempt_time` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------------------
-- idempotent_requests — safe request replay for mutating API calls
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `idempotent_requests`;
CREATE TABLE `idempotent_requests` (
  `idempotency_key` varchar(64) NOT NULL,
  `user_id` int NOT NULL,
  `status` enum('processing','completed','failed') NOT NULL DEFAULT 'processing',
  `response_payload` json DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`idempotency_key`),
  KEY `idx_user_key` (`user_id`,`idempotency_key`),
  KEY `idx_cleanup` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------------------
-- ledger lookup tables
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `ledger_account_types`;
CREATE TABLE `ledger_account_types` (
  `id` tinyint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS `ledger_transaction_types`;
CREATE TABLE `ledger_transaction_types` (
  `id` tinyint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------------------
-- expenses
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `expense_categories`;
CREATE TABLE `expense_categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `is_recurring` tinyint(1) DEFAULT '0',
  `recurrence_interval` varchar(20) DEFAULT 'none',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS `expenses`;
CREATE TABLE `expenses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cafe_id` bigint unsigned NOT NULL,
  `category_id` bigint unsigned DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `notes` text,
  `expense_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `recorded_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` bigint unsigned DEFAULT NULL,
  `previous_hash` char(64) DEFAULT NULL,
  `signature_hash` char(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_expense_cafe` (`cafe_id`),
  KEY `fk_expense_category` (`category_id`),
  KEY `fk_expense_recorder` (`recorded_by`),
  KEY `fk_expense_deleted_by` (`deleted_by`),
  CONSTRAINT `fk_expense_category` FOREIGN KEY (`category_id`) REFERENCES `expense_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_expense_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_expense_cafe` FOREIGN KEY (`cafe_id`) REFERENCES `cafes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_expense_recorder` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_expense_amount_positive` CHECK ((`amount` >= 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------------------
-- financial_ledger — append-only. Never UPDATE or DELETE rows here.
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `financial_ledger`;
CREATE TABLE `financial_ledger` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cafe_id` bigint unsigned NOT NULL,
  `transaction_type_id` tinyint unsigned NOT NULL,
  `account_type_id` tinyint unsigned NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `reference_table` enum('payments','expenses') NOT NULL,
  `reference_id` bigint unsigned NOT NULL,
  `recorded_by` bigint unsigned NOT NULL,
  `previous_hash` char(64) DEFAULT NULL,
  `signature_hash` char(64) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ledger_reconciliation` (`cafe_id`,`account_type_id`),
  KEY `fk_ledger_type` (`transaction_type_id`),
  KEY `fk_ledger_account` (`account_type_id`),
  KEY `fk_ledger_user` (`recorded_by`),
  CONSTRAINT `fk_ledger_account` FOREIGN KEY (`account_type_id`) REFERENCES `ledger_account_types` (`id`),
  CONSTRAINT `fk_ledger_cafe` FOREIGN KEY (`cafe_id`) REFERENCES `cafes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ledger_type` FOREIGN KEY (`transaction_type_id`) REFERENCES `ledger_transaction_types` (`id`),
  CONSTRAINT `fk_ledger_user` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------------------
-- menu
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `menu_categories`;
CREATE TABLE `menu_categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cafe_id` bigint unsigned NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_menu_cat_cafe` (`cafe_id`),
  CONSTRAINT `fk_menu_cat_cafe` FOREIGN KEY (`cafe_id`) REFERENCES `cafes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS `menu_items`;
CREATE TABLE `menu_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cafe_id` bigint unsigned NOT NULL,
  `category_id` bigint unsigned DEFAULT NULL,
  `name` varchar(200) NOT NULL,
  `description` text,
  `price` decimal(15,2) NOT NULL DEFAULT '0.00',
  `is_available` tinyint(1) DEFAULT '1',
  `image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_menu_item_category` (`category_id`),
  KEY `idx_menu_item_cafe` (`cafe_id`),
  CONSTRAINT `fk_menu_item_category` FOREIGN KEY (`category_id`) REFERENCES `menu_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_menu_item_cafe` FOREIGN KEY (`cafe_id`) REFERENCES `cafes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS `menu_item_images`;
CREATE TABLE `menu_item_images` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `menu_item_id` bigint unsigned NOT NULL,
  `url` varchar(255) NOT NULL,
  `alt_text` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_menu_item_images_item` (`menu_item_id`),
  CONSTRAINT `fk_menu_item_images_item` FOREIGN KEY (`menu_item_id`) REFERENCES `menu_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------------------
-- orders
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `orders`;
CREATE TABLE `orders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cafe_id` bigint unsigned NOT NULL,
  `customer_name` varchar(200) DEFAULT NULL,
  `customer_contact` varchar(100) DEFAULT NULL,
  `status` enum('queued','preparing','ready','completed','cancelled') NOT NULL DEFAULT 'queued',
  `total_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `payment_status` enum('pending','paid','refunded') NOT NULL DEFAULT 'pending',
  `recorded_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_orders_cafe` (`cafe_id`),
  KEY `idx_orders_recorded_by` (`recorded_by`),
  CONSTRAINT `fk_orders_cafe` FOREIGN KEY (`cafe_id`) REFERENCES `cafes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_orders_recorded_by` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS `order_items`;
CREATE TABLE `order_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned NOT NULL,
  `menu_item_id` bigint unsigned NOT NULL,
  `quantity` int NOT NULL DEFAULT '1',
  `unit_price` decimal(15,2) NOT NULL DEFAULT '0.00',
  `total_price` decimal(15,2) NOT NULL DEFAULT '0.00',
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_order_items_order` (`order_id`),
  KEY `idx_order_items_menu_item` (`menu_item_id`),
  CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_order_items_menu_item` FOREIGN KEY (`menu_item_id`) REFERENCES `menu_items` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------------------
-- payments
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cafe_id` bigint unsigned NOT NULL,
  `order_id` bigint unsigned DEFAULT NULL,
  `payer_name` varchar(100) DEFAULT NULL,
  `recorded_by` bigint unsigned NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `method` varchar(50) NOT NULL,
  `transaction_date` datetime DEFAULT CURRENT_TIMESTAMP,
  `status` enum('pending','approved','rejected') DEFAULT 'approved',
  `void_requested` tinyint(1) DEFAULT '0',
  `notes` text,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` bigint unsigned DEFAULT NULL,
  `previous_hash` char(64) DEFAULT NULL,
  `signature_hash` char(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_payment_cafe` (`cafe_id`),
  KEY `fk_payment_order` (`order_id`),
  KEY `fk_payment_recorder` (`recorded_by`),
  KEY `fk_payment_deleted_by` (`deleted_by`),
  CONSTRAINT `fk_payment_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_payment_cafe` FOREIGN KEY (`cafe_id`) REFERENCES `cafes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payment_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_payment_recorder` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_payment_amount_positive` CHECK ((`amount` >= 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------------
-- Seed data: roles
-- ---------------------------------------------------------------------------
INSERT INTO `roles` (`name`, `description`) VALUES
  ('admin', 'Full system access'),
  ('manager', 'Manage menu, pricing, users, and view/export reports'),
  ('cashier', 'Accept payments, reconcile transactions, issue receipts (also waitstaff)'),
  ('barista', 'Receive and prepare orders (also kitchen)');
