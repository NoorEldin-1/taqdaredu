-- =====================================================
-- Academy LMS - API Tables Migration
-- Version: 2.0
-- Description: Database tables required for API features
-- =====================================================

-- -----------------------------------------------------
-- Table: api_keys
-- Stores API keys for external integrations
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `api_keys` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) UNSIGNED NOT NULL,
    `name` VARCHAR(100) NOT NULL DEFAULT 'API Key',
    `key` VARCHAR(64) NOT NULL,
    `secret` VARCHAR(128) DEFAULT NULL,
    `permissions` JSON DEFAULT NULL COMMENT 'JSON array of allowed endpoints',
    `rate_limit` INT(11) DEFAULT 100 COMMENT 'Requests per minute',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `last_used_at` INT(11) DEFAULT NULL,
    `expires_at` INT(11) DEFAULT NULL,
    `created_at` INT(11) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_key` (`key`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table: api_logs
-- Logs API requests for monitoring and debugging
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `api_logs` (
    `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `api_key_id` INT(11) UNSIGNED DEFAULT NULL,
    `user_id` INT(11) UNSIGNED DEFAULT NULL,
    `endpoint` VARCHAR(255) NOT NULL,
    `method` VARCHAR(10) NOT NULL,
    `request_body` TEXT DEFAULT NULL,
    `request_headers` TEXT DEFAULT NULL,
    `response_code` INT(11) DEFAULT NULL,
    `response_time` FLOAT DEFAULT NULL COMMENT 'Response time in milliseconds',
    `ip_address` VARCHAR(45) NOT NULL,
    `user_agent` VARCHAR(500) DEFAULT NULL,
    `created_at` INT(11) NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_api_key_id` (`api_key_id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_endpoint` (`endpoint`(191)),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table: notifications
-- Stores user notifications
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) UNSIGNED NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `message` TEXT DEFAULT NULL,
    `type` VARCHAR(50) DEFAULT 'general' COMMENT 'general, course, payment, message, system',
    `reference_id` INT(11) DEFAULT NULL COMMENT 'ID of related entity (course_id, payment_id, etc)',
    `reference_type` VARCHAR(50) DEFAULT NULL COMMENT 'course, payment, user, etc',
    `action_url` VARCHAR(500) DEFAULT NULL,
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    `read_at` INT(11) DEFAULT NULL,
    `created_at` INT(11) NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_is_read` (`is_read`),
    KEY `idx_type` (`type`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table: push_tokens
-- Stores FCM/Push notification tokens
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `push_tokens` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) UNSIGNED NOT NULL,
    `token` VARCHAR(500) NOT NULL,
    `device_type` ENUM('android', 'ios', 'web') NOT NULL DEFAULT 'android',
    `device_id` VARCHAR(255) DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` INT(11) NOT NULL,
    `updated_at` INT(11) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_token` (`token`(255)),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table: webhooks
-- Stores webhook configurations
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `webhooks` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) UNSIGNED NOT NULL,
    `name` VARCHAR(100) NOT NULL DEFAULT 'Webhook',
    `url` VARCHAR(500) NOT NULL,
    `events` JSON DEFAULT NULL COMMENT 'JSON array of subscribed events',
    `secret` VARCHAR(128) NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `last_triggered_at` INT(11) DEFAULT NULL,
    `failure_count` INT(11) NOT NULL DEFAULT 0,
    `created_at` INT(11) NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table: webhook_deliveries
-- Logs webhook delivery attempts
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `webhook_deliveries` (
    `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `webhook_id` INT(11) UNSIGNED NOT NULL,
    `event` VARCHAR(100) NOT NULL,
    `request_body` TEXT NOT NULL,
    `response_code` INT(11) DEFAULT NULL,
    `response_body` TEXT DEFAULT NULL,
    `is_success` TINYINT(1) NOT NULL DEFAULT 0,
    `error` TEXT DEFAULT NULL,
    `duration_ms` INT(11) DEFAULT NULL,
    `created_at` INT(11) NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_webhook_id` (`webhook_id`),
    KEY `idx_is_success` (`is_success`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table: api_rate_limits
-- Tracks API rate limiting
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `api_rate_limits` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `key` VARCHAR(100) NOT NULL COMMENT 'user_id or API key or IP',
    `endpoint` VARCHAR(255) DEFAULT NULL,
    `requests` INT(11) NOT NULL DEFAULT 0,
    `window_start` INT(11) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_key_endpoint` (`key`, `endpoint`(191)),
    KEY `idx_window_start` (`window_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table: instructor_applications
-- Stores instructor application requests
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `instructor_applications` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) UNSIGNED NOT NULL,
    `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    `document` VARCHAR(255) DEFAULT NULL,
    `reason` TEXT DEFAULT NULL COMMENT 'Reason for rejection if rejected',
    `admin_notes` TEXT DEFAULT NULL,
    `reviewed_by` INT(11) UNSIGNED DEFAULT NULL,
    `reviewed_at` INT(11) DEFAULT NULL,
    `created_at` INT(11) NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table: certificates
-- Stores issued certificates
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `certificates` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) UNSIGNED NOT NULL,
    `course_id` INT(11) UNSIGNED NOT NULL,
    `shareable_code` VARCHAR(50) NOT NULL,
    `certificate_data` JSON DEFAULT NULL,
    `issued_at` INT(11) NOT NULL,
    `created_at` INT(11) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_user_course` (`user_id`, `course_id`),
    UNIQUE KEY `unique_code` (`shareable_code`),
    KEY `idx_course_id` (`course_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Add notification_preferences column to users table
-- -----------------------------------------------------
ALTER TABLE `users` 
ADD COLUMN IF NOT EXISTS `notification_preferences` JSON DEFAULT NULL 
COMMENT 'JSON object storing notification preferences';

-- -----------------------------------------------------
-- Add instructor_revenue column to payment table if not exists
-- -----------------------------------------------------
ALTER TABLE `payment` 
ADD COLUMN IF NOT EXISTS `instructor_revenue` DECIMAL(10,2) DEFAULT 0 
AFTER `amount`;

-- -----------------------------------------------------
-- Create indexes for better performance
-- -----------------------------------------------------

-- Index for message table if not exists
ALTER TABLE `message` 
ADD INDEX IF NOT EXISTS `idx_thread_code` (`thread_code`),
ADD INDEX IF NOT EXISTS `idx_sender` (`sender`),
ADD INDEX IF NOT EXISTS `idx_receiver` (`receiver`),
ADD INDEX IF NOT EXISTS `idx_read_status` (`read_status`);

-- Index for enrol table if not exists
ALTER TABLE `enrol` 
ADD INDEX IF NOT EXISTS `idx_user_id` (`user_id`),
ADD INDEX IF NOT EXISTS `idx_course_id` (`course_id`);

-- Index for payment table if not exists
ALTER TABLE `payment` 
ADD INDEX IF NOT EXISTS `idx_user_id` (`user_id`),
ADD INDEX IF NOT EXISTS `idx_course_id` (`course_id`),
ADD INDEX IF NOT EXISTS `idx_date_added` (`date_added`);

-- Index for course table if not exists
ALTER TABLE `course` 
ADD INDEX IF NOT EXISTS `idx_user_id` (`user_id`),
ADD INDEX IF NOT EXISTS `idx_category_id` (`category_id`),
ADD INDEX IF NOT EXISTS `idx_status` (`status`);

-- Index for lesson table if not exists
ALTER TABLE `lesson` 
ADD INDEX IF NOT EXISTS `idx_course_id` (`course_id`),
ADD INDEX IF NOT EXISTS `idx_section_id` (`section_id`);

-- =====================================================
-- Sample data for testing
-- =====================================================

-- Insert sample API key for testing (optional - remove in production)
-- INSERT INTO `api_keys` (`user_id`, `name`, `key`, `secret`, `permissions`, `rate_limit`, `is_active`, `created_at`)
-- VALUES (1, 'Test API Key', 'test_key_12345', 'test_secret_12345', '["*"]', 100, 1, UNIX_TIMESTAMP());

-- =====================================================
-- Additional Tables for Payment API
-- =====================================================

-- -----------------------------------------------------
-- Table: pending_payments
-- Stores pending payment transactions
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `pending_payments` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) UNSIGNED NOT NULL,
    `course_id` INT(11) UNSIGNED NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `original_amount` DECIMAL(10,2) DEFAULT NULL,
    `discount` DECIMAL(10,2) DEFAULT 0,
    `coupon_code` VARCHAR(50) DEFAULT NULL,
    `payment_method` VARCHAR(50) NOT NULL,
    `payment_token` VARCHAR(64) NOT NULL,
    `transaction_id` VARCHAR(255) DEFAULT NULL,
    `status` ENUM('pending', 'completed', 'failed', 'cancelled') NOT NULL DEFAULT 'pending',
    `created_at` INT(11) NOT NULL,
    `completed_at` INT(11) DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_token` (`payment_token`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table: pending_checkouts
-- Stores pending cart checkouts
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `pending_checkouts` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) UNSIGNED NOT NULL,
    `course_ids` JSON NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `payment_method` VARCHAR(50) DEFAULT NULL,
    `checkout_token` VARCHAR(64) NOT NULL,
    `status` ENUM('pending', 'completed', 'failed') NOT NULL DEFAULT 'pending',
    `created_at` INT(11) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_token` (`checkout_token`),
    KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table: pending_enrol
-- Stores pending offline enrollments
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `pending_enrol` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) UNSIGNED NOT NULL,
    `course_id` INT(11) UNSIGNED NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `payment_type` VARCHAR(50) NOT NULL,
    `document` VARCHAR(255) DEFAULT NULL,
    `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    `admin_notes` TEXT DEFAULT NULL,
    `created_at` INT(11) NOT NULL,
    `updated_at` INT(11) DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table: lesson_progress
-- Tracks lesson completion
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `lesson_progress` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) UNSIGNED NOT NULL,
    `course_id` INT(11) UNSIGNED NOT NULL,
    `lesson_id` INT(11) UNSIGNED NOT NULL,
    `completed_at` INT(11) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_user_lesson` (`user_id`, `lesson_id`),
    KEY `idx_course_id` (`course_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Alter: coupons
-- Adds optional single-course restriction. NULL = store-wide (unchanged
-- behavior for existing coupons); set = only valid when that course is
-- in the cart, and the discount applies to that course's price only.
-- -----------------------------------------------------
ALTER TABLE `coupons`
    ADD COLUMN IF NOT EXISTS `course_id` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `discount_percentage`;

-- =====================================================
-- End of migration
-- =====================================================
