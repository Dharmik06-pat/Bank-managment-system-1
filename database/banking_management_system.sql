-- =====================================================================
--  BANKING MANAGEMENT SYSTEM  -  MySQL Database Schema
--  ------------------------------------------------------------------
--  How to import (phpMyAdmin):
--    1. Start Apache + MySQL in the XAMPP Control Panel
--    2. Open http://localhost/phpmyadmin
--    3. Click the "Import" tab  ->  "Choose File"
--    4. Select this file and press "Go"
--
--  This script creates the database itself, so you do NOT need to
--  create "banking_management_system" manually beforehand.
-- =====================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- Database
-- ---------------------------------------------------------------------
CREATE DATABASE IF NOT EXISTS `banking_management_system`
    DEFAULT CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `banking_management_system`;

-- Drop in dependency order so the script can be re-imported safely.
DROP TABLE IF EXISTS `transactions`;
DROP TABLE IF EXISTS `accounts`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `admins`;

-- ---------------------------------------------------------------------
-- Table: users  (bank customers)
-- ---------------------------------------------------------------------
CREATE TABLE `users` (
  `id`            INT(11)      NOT NULL AUTO_INCREMENT,
  `full_name`     VARCHAR(120) NOT NULL,
  `email`         VARCHAR(150) NOT NULL,
  `mobile`        VARCHAR(15)  NOT NULL,
  `date_of_birth` DATE         NOT NULL,
  `gender`        ENUM('Male','Female','Other') NOT NULL DEFAULT 'Male',
  `address`       VARCHAR(255) NOT NULL,
  `city`          VARCHAR(80)  NOT NULL,
  `state`         VARCHAR(80)  NOT NULL,
  `pin_code`      VARCHAR(10)  NOT NULL,
  `username`      VARCHAR(60)  NOT NULL,
  `password`      VARCHAR(255) NOT NULL COMMENT 'bcrypt hash from password_hash()',
  `status`        ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
  `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email`    (`email`),
  UNIQUE KEY `uq_users_username` (`username`),
  KEY `idx_users_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: accounts  (one customer may hold several bank accounts)
-- ---------------------------------------------------------------------
CREATE TABLE `accounts` (
  `id`             INT(11)     NOT NULL AUTO_INCREMENT,
  `user_id`        INT(11)     NOT NULL,
  `account_number` VARCHAR(20) NOT NULL,
  `account_type`   ENUM('Savings Account','Current Account') NOT NULL DEFAULT 'Savings Account',
  `balance`        DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `account_status` ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
  `created_at`     TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_accounts_number` (`account_number`),
  KEY `idx_accounts_user`   (`user_id`),
  KEY `idx_accounts_status` (`account_status`),
  CONSTRAINT `fk_accounts_user`
      FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
      ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: transactions  (append-only ledger - never edited or deleted)
-- ---------------------------------------------------------------------
CREATE TABLE `transactions` (
  `id`                     INT(11)     NOT NULL AUTO_INCREMENT,
  `transaction_id`         VARCHAR(30) NOT NULL COMMENT 'Human readable reference e.g. TXN20260903ABC123',
  `account_id`             INT(11)     NOT NULL,
  `related_account_number` VARCHAR(20) DEFAULT NULL COMMENT 'Other party for transfers, NULL otherwise',
  `transaction_type`       ENUM('Deposit','Withdrawal','Transfer Sent','Transfer Received') NOT NULL,
  `amount`                 DECIMAL(15,2) NOT NULL,
  `description`            VARCHAR(255) DEFAULT NULL,
  `status`                 ENUM('Success','Pending','Failed') NOT NULL DEFAULT 'Success',
  `created_at`             TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tx_reference` (`transaction_id`),
  KEY `idx_tx_account` (`account_id`),
  KEY `idx_tx_type`    (`transaction_type`),
  KEY `idx_tx_created` (`created_at`),
  CONSTRAINT `fk_tx_account`
      FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`)
      ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: admins
-- ---------------------------------------------------------------------
CREATE TABLE `admins` (
  `id`         INT(11)      NOT NULL AUTO_INCREMENT,
  `username`   VARCHAR(60)  NOT NULL,
  `password`   VARCHAR(255) NOT NULL COMMENT 'bcrypt hash from password_hash()',
  `full_name`  VARCHAR(120) NOT NULL,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admins_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Default administrator
--   Username : admin
--   Password : admin123
--
-- The value below is a real bcrypt hash produced the same way
-- password_hash('admin123', PASSWORD_DEFAULT) produces one, so
-- password_verify('admin123', $hash) returns true.
--
-- If you ever need to regenerate it, open in your browser:
--   http://localhost/banking-management-system/database/generate_hash.php
-- ---------------------------------------------------------------------
INSERT INTO `admins` (`username`, `password`, `full_name`) VALUES
('admin', '$2y$10$7f0GXKSL2/dsWRfhrmWMq.gFOl9.5MsluLaTqMuR42mf0KXb80Aq6', 'System Administrator');

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
--  End of schema
-- =====================================================================
