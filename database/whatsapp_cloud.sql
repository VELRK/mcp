-- WhatsApp Cloud (Meta Graph API) inbox + templates
-- Also created automatically when you open Admin → WhatsApp Inbox / Settings → WhatsApp Cloud.

CREATE TABLE IF NOT EXISTS `wa_cloud_templates` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(512) NOT NULL,
  `language` VARCHAR(16) NOT NULL DEFAULT 'en',
  `category` VARCHAR(32) NOT NULL DEFAULT 'UTILITY',
  `kind` VARCHAR(16) NOT NULL DEFAULT 'text',
  `body_text` TEXT NULL,
  `header_text` VARCHAR(60) NULL,
  `footer_text` VARCHAR(60) NULL,
  `media_url` VARCHAR(512) NULL,
  `media_handle` VARCHAR(255) NULL,
  `meta_id` VARCHAR(64) NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'DRAFT',
  `meta_payload` MEDIUMTEXT NULL,
  `variable_map` TEXT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_name_lang` (`name`(191), `language`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `wa_cloud_campaigns` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(190) NOT NULL,
  `template_id` INT UNSIGNED NOT NULL,
  `status` VARCHAR(24) NOT NULL DEFAULT 'draft',
  `total` INT UNSIGNED NOT NULL DEFAULT 0,
  `queued` INT UNSIGNED NOT NULL DEFAULT 0,
  `sent` INT UNSIGNED NOT NULL DEFAULT 0,
  `delivered` INT UNSIGNED NOT NULL DEFAULT 0,
  `read_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `failed` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  `started_at` DATETIME NULL,
  `finished_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tpl` (`template_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `wa_cloud_campaign_recipients` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `campaign_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NULL,
  `phone` VARCHAR(32) NOT NULL,
  `name` VARCHAR(160) NULL,
  `status` VARCHAR(24) NOT NULL DEFAULT 'queued',
  `wamid` VARCHAR(128) NULL,
  `error_text` VARCHAR(500) NULL,
  `variables_json` TEXT NULL,
  `sent_at` DATETIME NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_camp_status` (`campaign_id`, `status`),
  KEY `idx_wamid` (`wamid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `wa_cloud_conversations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `phone` VARCHAR(32) NOT NULL,
  `name` VARCHAR(160) NULL,
  `last_message` VARCHAR(500) NULL,
  `last_direction` VARCHAR(16) NULL,
  `last_at` DATETIME NULL,
  `unread` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `wa_cloud_messages` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `conversation_id` INT UNSIGNED NOT NULL,
  `wamid` VARCHAR(128) NULL,
  `direction` VARCHAR(8) NOT NULL,
  `type` VARCHAR(16) NOT NULL DEFAULT 'text',
  `body` MEDIUMTEXT NULL,
  `media_url` VARCHAR(512) NULL,
  `media_id` VARCHAR(128) NULL,
  `template_name` VARCHAR(512) NULL,
  `status` VARCHAR(24) NOT NULL DEFAULT 'queued',
  `error_text` VARCHAR(500) NULL,
  `raw_json` MEDIUMTEXT NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_conv` (`conversation_id`, `id`),
  KEY `idx_wamid` (`wamid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Existing installs: helper also adds these automatically on first WhatsApp admin page load.
-- ALTER TABLE `wa_cloud_templates` ADD COLUMN `variable_map` TEXT NULL AFTER `meta_payload`;

INSERT IGNORE INTO `settings` (`key`, `value`) VALUES
('wa_cloud_enabled', '0'),
('wa_cloud_access_token', ''),
('wa_cloud_phone_number_id', ''),
('wa_cloud_waba_id', ''),
('wa_cloud_app_secret', ''),
('wa_cloud_verify_token', '2deal-wa-verify'),
('wa_cloud_api_version', 'v21.0');
