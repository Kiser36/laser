CREATE DATABASE IF NOT EXISTS `owere_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `owere_db`;

-- Admin Users Table
CREATE TABLE IF NOT EXISTS `admin_users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `display_name` VARCHAR(100) DEFAULT NULL,
  `avatar` VARCHAR(255) DEFAULT NULL,
  `role` ENUM('owner','admin') NOT NULL DEFAULT 'admin',
  `password_hash` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Admin Activity Log Table
CREATE TABLE IF NOT EXISTS `admin_activity` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `admin_id` INT DEFAULT NULL,
  `username` VARCHAR(50) NOT NULL DEFAULT 'system',
  `action` VARCHAR(50) NOT NULL,
  `details` VARCHAR(255) DEFAULT NULL,
  `ip` VARCHAR(45) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_activity_created` (`created_at`),
  KEY `idx_activity_action` (`action`)
) ENGINE=InnoDB;

-- Partner / Client Credibility Logos Table
CREATE TABLE IF NOT EXISTS `partner_logos` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `org_name` VARCHAR(100) NOT NULL,
  `logo_path` VARCHAR(255) NOT NULL,
  `category` ENUM('corporate', 'ngo', 'compliance') DEFAULT 'corporate',
  `display_order` INT DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Service Booking & Consultation Inquiries Table
CREATE TABLE IF NOT EXISTS `inquiries` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `client_name` VARCHAR(100) NOT NULL,
  `company_name` VARCHAR(100) DEFAULT NULL,
  `email` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(30) NOT NULL,
  `service_requested` VARCHAR(100) NOT NULL,
  `preferred_date` DATE DEFAULT NULL,
  `message` TEXT DEFAULT NULL,
  `status` ENUM('new', 'contacted', 'closed') DEFAULT 'new',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- System Settings Table (WhatsApp & Dynamic Configs)
CREATE TABLE IF NOT EXISTS `system_settings` (
  `setting_key` VARCHAR(50) PRIMARY KEY,
  `setting_value` TEXT NOT NULL
) ENGINE=InnoDB;

-- Seed Default Settings
INSERT INTO `system_settings` (`setting_key`, `setting_value`) VALUES
('whatsapp_number', '+256701700461'),
('whatsapp_welcome_msg', 'Hello Owere & Associates, I would like to inquire about your advisory services.'),
('notification_email', 'info@owereassociates.com');