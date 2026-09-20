-- SaaS usage billing (safe to re-run). Also auto-created by Sk_Saas_Billing_model.

CREATE TABLE IF NOT EXISTS `saas_client_amounts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `vendor_id` INT UNSIGNED NULL DEFAULT NULL COMMENT 'NULL = global default',
  `request_type` VARCHAR(64) NOT NULL DEFAULT 'ai_chat',
  `unit_amount` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
  `currency` VARCHAR(8) NOT NULL DEFAULT 'INR',
  `label` VARCHAR(160) NULL DEFAULT NULL,
  `status` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_vendor_type` (`vendor_id`, `request_type`),
  KEY `idx_type_status` (`request_type`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `saas_ai_requests` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `request_code` VARCHAR(64) NOT NULL,
  `vendor_id` INT UNSIGNED NOT NULL,
  `request_type` VARCHAR(64) NOT NULL DEFAULT 'ai_chat',
  `source` VARCHAR(24) NOT NULL DEFAULT 'api',
  `status` VARCHAR(24) NOT NULL DEFAULT 'ok',
  `units` DECIMAL(12,4) NOT NULL DEFAULT 1.0000,
  `unit_amount` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
  `total_amount` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
  `meta` MEDIUMTEXT NULL,
  `external_id` VARCHAR(128) NULL DEFAULT NULL,
  `billed_at` DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_request_code` (`request_code`),
  UNIQUE KEY `uniq_source_external` (`source`, `external_id`),
  KEY `idx_vendor_created` (`vendor_id`, `created_at`),
  KEY `idx_billed` (`billed_at`),
  KEY `idx_type` (`request_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `saas_daily_bills` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `vendor_id` INT UNSIGNED NOT NULL,
  `bill_date` DATE NOT NULL,
  `request_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `total_amount` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
  `status` VARCHAR(16) NOT NULL DEFAULT 'final',
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_vendor_date` (`vendor_id`, `bill_date`),
  KEY `idx_bill_date` (`bill_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `settings` (`key`, `value`) VALUES
('saas_billing_token', ''),
('saas_default_vendor_id', ''),
('saas_cron_key', '');
