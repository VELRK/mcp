CREATE TABLE IF NOT EXISTS `sk_testimonials` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `author_name`    VARCHAR(120) NOT NULL,
  `author_title`   VARCHAR(120) DEFAULT NULL COMMENT 'e.g. Verified Buyer',
  `quote`          TEXT NOT NULL,
  `rating`         TINYINT(1) NOT NULL DEFAULT 5,
  `product_id`     INT UNSIGNED DEFAULT NULL,
  `status`         TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=active 0=hidden',
  `sort_order`     SMALLINT(5) NOT NULL DEFAULT 0,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `sk_testimonials` (`author_name`, `author_title`, `quote`, `rating`, `status`, `sort_order`)
SELECT * FROM (
  SELECT 'Silk House' AS author_name, 'Saree boutique · Coimbatore' AS author_title, 'After-hours WhatsApp now sends the saree, the pay link and the invoice. We only pack paid orders.' AS quote, 5 AS rating, 1 AS status, 1 AS sort_order
  UNION ALL SELECT 'City Care Clinic', 'Clinic · Chennai', 'Patients book slots on WhatsApp. Reminders go out automatically. The front desk is not the bottleneck anymore.', 5, 1, 2
  UNION ALL SELECT 'Urban Store', 'D2C store · Bengaluru', 'Instagram comments and WhatsApp chats land in one inbox. Catalogue, payment and CRM update from the same conversation.', 5, 1, 3
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM `sk_testimonials` LIMIT 1);
