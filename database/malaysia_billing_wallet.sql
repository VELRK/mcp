-- Malaysia billing, affiliate invite, wallet points (run once)
SET NAMES utf8mb4;

-- Affiliate invite / force password setup
ALTER TABLE `affiliates`
  ADD COLUMN IF NOT EXISTS `must_set_password` TINYINT(1) NOT NULL DEFAULT 0 AFTER `password`,
  ADD COLUMN IF NOT EXISTS `invite_token` VARCHAR(64) NULL DEFAULT NULL AFTER `must_set_password`,
  ADD COLUMN IF NOT EXISTS `invite_expires` DATETIME NULL DEFAULT NULL AFTER `invite_token`;

-- Address: optional company + type (shipping/billing)
ALTER TABLE `addresses`
  ADD COLUMN IF NOT EXISTS `company_name` VARCHAR(150) NULL DEFAULT NULL AFTER `full_name`,
  ADD COLUMN IF NOT EXISTS `address_type` VARCHAR(20) NOT NULL DEFAULT 'shipping' AFTER `label`;

-- Order billing fields
ALTER TABLE `orders`
  ADD COLUMN IF NOT EXISTS `billing_name` VARCHAR(150) NULL DEFAULT NULL AFTER `shipping_country`,
  ADD COLUMN IF NOT EXISTS `billing_company` VARCHAR(150) NULL DEFAULT NULL AFTER `billing_name`,
  ADD COLUMN IF NOT EXISTS `billing_phone` VARCHAR(30) NULL DEFAULT NULL AFTER `billing_company`,
  ADD COLUMN IF NOT EXISTS `billing_line1` VARCHAR(255) NULL DEFAULT NULL AFTER `billing_phone`,
  ADD COLUMN IF NOT EXISTS `billing_line2` VARCHAR(255) NULL DEFAULT NULL AFTER `billing_line1`,
  ADD COLUMN IF NOT EXISTS `billing_city` VARCHAR(100) NULL DEFAULT NULL AFTER `billing_line2`,
  ADD COLUMN IF NOT EXISTS `billing_state` VARCHAR(100) NULL DEFAULT NULL AFTER `billing_city`,
  ADD COLUMN IF NOT EXISTS `billing_pincode` VARCHAR(20) NULL DEFAULT NULL AFTER `billing_state`,
  ADD COLUMN IF NOT EXISTS `billing_country` VARCHAR(80) NULL DEFAULT 'Malaysia' AFTER `billing_pincode`;

-- Settings defaults (ignore duplicates via app upsert)
INSERT IGNORE INTO `settings` (`key`, `value`) VALUES
('currency_symbol', 'RM'),
('currency_code', 'MYR'),
('default_country', 'Malaysia'),
('payment_gateway', 'toyyibpay'),
('toyyibpay_secret_key', ''),
('toyyibpay_category_code', ''),
('toyyibpay_sandbox', '1');
