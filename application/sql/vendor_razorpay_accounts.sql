-- Vendor Razorpay account mapping.
-- Created at runtime by sk_razorpay_ensure_vendor_account().
-- linked_account_id stays empty until Razorpay Route onboarding is approved.
-- transfer_allowed stays 0. Do not copy a standalone merchant ID into linked_account_id.

CREATE TABLE IF NOT EXISTS `vendor_razorpay_accounts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `vendor_id` INT UNSIGNED NOT NULL,
  `provider` VARCHAR(24) NOT NULL DEFAULT 'razorpay',
  `linked_account_id` VARCHAR(64) NULL DEFAULT NULL,
  `onboarding_status` VARCHAR(32) NOT NULL DEFAULT 'not_linked',
  `transfer_allowed` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_vendor_provider` (`vendor_id`, `provider`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
