-- 2DEAL local seed (MySQL / MariaDB)
-- Keeps: admins, settings, roles, master_* , variant_units, WhatsApp Cloud templates
-- Import: phpMyAdmin → Import, or:
--   mysql -uroot shopkart < database/seed_local.sql

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';

-- Wipe catalog + transactional data (not login / settings)
TRUNCATE TABLE `activity_logs`;
TRUNCATE TABLE `addresses`;
TRUNCATE TABLE `affiliate_clicks`;
TRUNCATE TABLE `affiliate_commission_ledger`;
TRUNCATE TABLE `affiliate_commissions`;
TRUNCATE TABLE `affiliate_kyc_documents`;
TRUNCATE TABLE `affiliate_payouts`;
TRUNCATE TABLE `affiliate_product_requests`;
TRUNCATE TABLE `affiliates`;
TRUNCATE TABLE `banks`;
TRUNCATE TABLE `blogs`;
TRUNCATE TABLE `brands`;
TRUNCATE TABLE `cart`;
TRUNCATE TABLE `categories`;
TRUNCATE TABLE `category_nav_products`;
TRUNCATE TABLE `contact_enquiries`;
TRUNCATE TABLE `customer_royalty`;
TRUNCATE TABLE `customer_royalty_transactions`;
TRUNCATE TABLE `customer_wallet_transactions`;
TRUNCATE TABLE `customer_wallets`;
TRUNCATE TABLE `mega_menu_titles`;
TRUNCATE TABLE `newsletter`;
TRUNCATE TABLE `order_items`;
TRUNCATE TABLE `orders`;
TRUNCATE TABLE `otp_sessions`;
TRUNCATE TABLE `payments`;
TRUNCATE TABLE `product_images`;
TRUNCATE TABLE `product_variants`;
TRUNCATE TABLE `product_videos`;
TRUNCATE TABLE `products`;
TRUNCATE TABLE `promo_codes`;
TRUNCATE TABLE `promo_usage`;
TRUNCATE TABLE `razorpay_payment_links`;
TRUNCATE TABLE `review_media`;
TRUNCATE TABLE `reviews`;
TRUNCATE TABLE `saree_styles`;
TRUNCATE TABLE `sa_audit_logs`;
TRUNCATE TABLE `sa_conversation_events`;
TRUNCATE TABLE `sa_conversations`;
TRUNCATE TABLE `sa_leads`;
TRUNCATE TABLE `sa_processed_webhooks`;
TRUNCATE TABLE `sk_banners`;
TRUNCATE TABLE `sk_testimonials`;
TRUNCATE TABLE `subcategories`;
TRUNCATE TABLE `users`;
TRUNCATE TABLE `vendor_bank_details`;
TRUNCATE TABLE `vendor_commission_history`;
TRUNCATE TABLE `vendor_documents`;
TRUNCATE TABLE `vendor_settlements`;
TRUNCATE TABLE `vendor_stores`;
TRUNCATE TABLE `vendor_wallet_transactions`;
TRUNCATE TABLE `vendor_wallets`;
TRUNCATE TABLE `vendors`;
TRUNCATE TABLE `wa_campaign_recipients`;
TRUNCATE TABLE `wa_campaigns`;
TRUNCATE TABLE `wa_cloud_campaign_recipients`;
TRUNCATE TABLE `wa_cloud_campaigns`;
TRUNCATE TABLE `wa_cloud_conversations`;
TRUNCATE TABLE `wa_cloud_messages`;
TRUNCATE TABLE `wa_conversations`;
TRUNCATE TABLE `wa_messages`;
TRUNCATE TABLE `whatsapp_logs`;
TRUNCATE TABLE `wishlist`;

INSERT INTO `mega_menu_titles` (`id`, `title`, `sort_order`, `created_at`) VALUES
(1, 'Silk collections', 1, NOW()),
(2, 'Occasion', 2, NOW());

INSERT INTO `categories` (`id`, `name`, `slug`, `description`, `image`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Silk Sarees', 'silk-sarees', 'Handwoven silk sarees', 'assets/uploads/categories/categories_69f701aa4d037.jpg', 1, 1, NOW(), NOW()),
(2, 'Cotton Sarees', 'cotton-sarees', 'Everyday cotton weaves', 'images/products/saree-modeling-photography.jpg', 2, 1, NOW(), NOW()),
(3, 'Designer Sarees', 'designer-sarees', 'Contemporary designer drapes', 'images/products/designer-women-saree-model-18--758.jpg', 3, 1, NOW(), NOW()),
(4, 'Wedding Sarees', 'wedding-sarees', 'Bridal and ceremonial silks', 'images/products/pngtree-indian-red-bridal-saree-red-dluhan-green-wedding-full-poses-png-image_14593372.png', 4, 1, NOW(), NOW()),
(5, 'Casual Sarees', 'casual-sarees', 'Light drapes for daily wear', 'images/products/Indian_Woman_Saree-2.webp', 5, 1, NOW(), NOW());

INSERT INTO `subcategories` (`id`, `category_id`, `mega_menu_title_id`, `name`, `slug`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'Kanjivaram', 'kanjivaram', 1, 1, NOW(), NOW()),
(2, 1, 1, 'Banarasi', 'banarasi', 2, 1, NOW(), NOW()),
(3, 4, 2, 'Bridal', 'bridal', 3, 1, NOW(), NOW()),
(4, 2, 2, 'Daily wear', 'daily-wear', 4, 1, NOW(), NOW());

INSERT INTO `brands` (`id`, `name`, `slug`, `status`, `created_at`) VALUES
(1, 'Kanjivaram House', 'kanjivaram-house', 1, NOW()),
(2, 'Banarasi Weavers', 'banarasi-weavers', 1, NOW()),
(3, 'Palam Silks', 'palam-silks', 1, NOW()),
(4, 'Nalli Silks', 'nalli-silks', 1, NOW());

INSERT INTO `products` (
  `id`, `category_id`, `subcategory_id`, `brand_id`, `name`, `slug`, `sku`, `description`, `short_desc`,
  `price`, `sale_price`, `stock`, `min_order_qty`, `thumbnail`, `featured`, `hot_sale`, `special_product`, `nav_featured`,
  `status`, `listing_status`, `fabric`, `saree_type`, `color`, `blouse_included`, `wash_care`, `origin_state`, `created_at`, `updated_at`
) VALUES
(1, 1, 1, 1, 'Kanjivaram Silk Saree - Gold Border', 'kanjivaram-silk-saree-gold-border', '2D-001',
 '<p>Kanjivaram Silk Saree with blouse piece.</p><ul><li>Fabric: Silk</li><li>Colour: Maroon</li></ul>',
 'Kanjivaram Silk Saree - Gold Border', 389.00, 329.00, 40, 1, 'images/products/images (1).jpg', 1, 1, 0, 1, 'active', 'ACTIVE', 'Silk', 'Kanjivaram', 'Maroon', 1, 'Dry clean recommended', 'Tamil Nadu', NOW(), NOW()),
(2, 1, 2, 2, 'Banarasi Silk Saree - Zari Weave', 'banarasi-silk-saree-zari-weave', '2D-002',
 '<p>Banarasi Silk Saree with blouse piece.</p><ul><li>Fabric: Silk</li><li>Colour: Royal Blue</li></ul>',
 'Banarasi Silk Saree - Zari Weave', 359.00, 299.00, 40, 1, 'images/products/blue-banarasi-silk-saree-with-smashing-blouse-piece_3.webp', 1, 0, 1, 1, 'active', 'ACTIVE', 'Silk', 'Banarasi', 'Royal Blue', 1, 'Dry clean recommended', 'Tamil Nadu', NOW(), NOW()),
(3, 1, 1, 3, 'Heritage Crepe Silk Saree', 'heritage-crepe-silk-saree', '2D-003',
 '<p>Heritage Crepe Silk Saree with blouse piece.</p><ul><li>Fabric: Crepe Silk</li><li>Colour: Peach</li></ul>',
 'Heritage Crepe Silk Saree', 249.00, NULL, 40, 1, 'images/products/images (6).jpg', 1, 0, 0, 1, 'active', 'ACTIVE', 'Crepe Silk', 'Crepe', 'Peach', 1, 'Dry clean recommended', 'Tamil Nadu', NOW(), NOW()),
(4, 2, 4, 4, 'Handloom Cotton Saree - Temple Border', 'handloom-cotton-saree-temple-border', '2D-004',
 '<p>Handloom Cotton Saree with blouse piece.</p><ul><li>Fabric: Cotton</li><li>Colour: Ivory</li></ul>',
 'Handloom Cotton Saree - Temple Border', 129.00, 99.00, 40, 1, 'images/products/saree-modeling-photography.jpg', 1, 1, 0, 0, 'active', 'ACTIVE', 'Cotton', 'Handloom', 'Ivory', 1, 'Dry clean recommended', 'Tamil Nadu', NOW(), NOW()),
(5, 2, 4, 4, 'Soft Cotton Casual Saree', 'soft-cotton-casual-saree', '2D-005',
 '<p>Soft Cotton Casual Saree with blouse piece.</p><ul><li>Fabric: Cotton</li><li>Colour: Mint</li></ul>',
 'Soft Cotton Casual Saree', 89.00, NULL, 40, 1, 'images/products/Indian_Woman_Saree-2.webp', 0, 0, 0, 0, 'active', 'ACTIVE', 'Cotton', 'Casual', 'Mint', 1, 'Dry clean recommended', 'Tamil Nadu', NOW(), NOW()),
(6, 3, NULL, 3, 'Designer Georgette Saree', 'designer-georgette-saree', '2D-006',
 '<p>Designer Georgette Saree with blouse piece.</p><ul><li>Fabric: Georgette</li><li>Colour: Emerald</li></ul>',
 'Designer Georgette Saree', 219.00, 179.00, 40, 1, 'images/products/designer-women-saree-model-18--758.jpg', 1, 0, 1, 1, 'active', 'ACTIVE', 'Georgette', 'Designer', 'Emerald', 1, 'Dry clean recommended', 'Tamil Nadu', NOW(), NOW()),
(7, 3, NULL, 1, 'Printed Designer Saree', 'printed-designer-saree', '2D-007',
 '<p>Printed Designer Saree with blouse piece.</p><ul><li>Fabric: Crepe</li><li>Colour: Multi</li></ul>',
 'Printed Designer Saree', 159.00, NULL, 40, 1, 'images/products/images (15).jpg', 0, 0, 0, 0, 'active', 'ACTIVE', 'Crepe', 'Printed', 'Multi', 1, 'Dry clean recommended', 'Tamil Nadu', NOW(), NOW()),
(8, 4, 3, 1, 'Bridal Red Silk Saree', 'bridal-red-silk-saree', '2D-008',
 '<p>Bridal Red Silk Saree with blouse piece.</p><ul><li>Fabric: Silk</li><li>Colour: Red</li></ul>',
 'Bridal Red Silk Saree', 499.00, 449.00, 40, 1, 'images/products/pngtree-indian-red-bridal-saree-red-dluhan-green-wedding-full-poses-png-image_14593372.png', 1, 0, 1, 1, 'active', 'ACTIVE', 'Silk', 'Bridal', 'Red', 1, 'Dry clean recommended', 'Tamil Nadu', NOW(), NOW()),
(9, 4, 3, 2, 'Wedding Tissue Silk Saree', 'wedding-tissue-silk-saree', '2D-009',
 '<p>Wedding Tissue Silk Saree with blouse piece.</p><ul><li>Fabric: Tissue Silk</li><li>Colour: Gold</li></ul>',
 'Wedding Tissue Silk Saree', 429.00, NULL, 40, 1, 'images/products/images (19).jpg', 0, 1, 0, 0, 'active', 'ACTIVE', 'Tissue Silk', 'Wedding', 'Gold', 1, 'Dry clean recommended', 'Tamil Nadu', NOW(), NOW()),
(10, 5, NULL, 4, 'Everyday Casual Saree', 'everyday-casual-saree', '2D-010',
 '<p>Everyday Casual Saree with blouse piece.</p><ul><li>Fabric: Cotton Blend</li><li>Colour: Beige</li></ul>',
 'Everyday Casual Saree', 79.00, 69.00, 40, 1, 'images/products/images (22).jpg', 0, 0, 0, 0, 'active', 'ACTIVE', 'Cotton Blend', 'Casual', 'Beige', 1, 'Dry clean recommended', 'Tamil Nadu', NOW(), NOW()),
(11, 3, NULL, 3, 'Party Wear Sequined Saree', 'party-wear-sequined-saree', '2D-011',
 '<p>Party Wear Sequined Saree with blouse piece.</p><ul><li>Fabric: Georgette</li><li>Colour: Wine</li></ul>',
 'Party Wear Sequined Saree', 269.00, 229.00, 40, 1, 'images/products/new-model-sarees.jpg', 1, 0, 0, 0, 'active', 'ACTIVE', 'Georgette', 'Party', 'Wine', 1, 'Dry clean recommended', 'Tamil Nadu', NOW(), NOW()),
(12, 1, 1, 1, 'Classic Silk Saree - Contrast Pallu', 'classic-silk-saree-contrast-pallu', '2D-012',
 '<p>Classic Silk Saree with blouse piece.</p><ul><li>Fabric: Silk</li><li>Colour: Teal</li></ul>',
 'Classic Silk Saree - Contrast Pallu', 199.00, NULL, 40, 1, 'images/products/images (27).jpg', 0, 0, 0, 0, 'active', 'ACTIVE', 'Silk', 'Silk', 'Teal', 1, 'Dry clean recommended', 'Tamil Nadu', NOW(), NOW());

INSERT INTO `product_images` (`product_id`, `image`, `alt`, `sort_order`) VALUES
(1, 'images/products/images (1).jpg', 'Kanjivaram Silk Saree - Gold Border', 0),
(1, 'images/products/images (2).jpg', 'Kanjivaram Silk Saree - Gold Border', 1),
(1, 'images/products/images (3).jpg', 'Kanjivaram Silk Saree - Gold Border', 2),
(2, 'images/products/blue-banarasi-silk-saree-with-smashing-blouse-piece_3.webp', 'Banarasi Silk Saree - Zari Weave', 0),
(2, 'images/products/images (4).jpg', 'Banarasi Silk Saree - Zari Weave', 1),
(2, 'images/products/images (5).jpg', 'Banarasi Silk Saree - Zari Weave', 2),
(3, 'images/products/images (6).jpg', 'Heritage Crepe Silk Saree', 0),
(3, 'images/products/images (7).jpg', 'Heritage Crepe Silk Saree', 1),
(3, 'images/products/images (8).jpg', 'Heritage Crepe Silk Saree', 2),
(4, 'images/products/saree-modeling-photography.jpg', 'Handloom Cotton Saree - Temple Border', 0),
(4, 'images/products/images (9).jpg', 'Handloom Cotton Saree - Temple Border', 1),
(4, 'images/products/images (10).jpg', 'Handloom Cotton Saree - Temple Border', 2),
(5, 'images/products/Indian_Woman_Saree-2.webp', 'Soft Cotton Casual Saree', 0),
(5, 'images/products/images (11).jpg', 'Soft Cotton Casual Saree', 1),
(5, 'images/products/images (12).jpg', 'Soft Cotton Casual Saree', 2),
(6, 'images/products/designer-women-saree-model-18--758.jpg', 'Designer Georgette Saree', 0),
(6, 'images/products/images (13).jpg', 'Designer Georgette Saree', 1),
(6, 'images/products/images (14).jpg', 'Designer Georgette Saree', 2),
(7, 'images/products/images (15).jpg', 'Printed Designer Saree', 0),
(7, 'images/products/images (16).jpg', 'Printed Designer Saree', 1),
(7, 'images/products/images (17).jpg', 'Printed Designer Saree', 2),
(8, 'images/products/pngtree-indian-red-bridal-saree-red-dluhan-green-wedding-full-poses-png-image_14593372.png', 'Bridal Red Silk Saree', 0),
(8, 'images/products/HD-wallpaper-saree-model-indian-subbu.jpg', 'Bridal Red Silk Saree', 1),
(8, 'images/products/images (18).jpg', 'Bridal Red Silk Saree', 2),
(9, 'images/products/images (19).jpg', 'Wedding Tissue Silk Saree', 0),
(9, 'images/products/images (20).jpg', 'Wedding Tissue Silk Saree', 1),
(9, 'images/products/images (21).jpg', 'Wedding Tissue Silk Saree', 2),
(10, 'images/products/images (22).jpg', 'Everyday Casual Saree', 0),
(10, 'images/products/images (23).jpg', 'Everyday Casual Saree', 1),
(10, 'images/products/images (24).jpg', 'Everyday Casual Saree', 2),
(11, 'images/products/new-model-sarees.jpg', 'Party Wear Sequined Saree', 0),
(11, 'images/products/images (25).jpg', 'Party Wear Sequined Saree', 1),
(11, 'images/products/images (26).jpg', 'Party Wear Sequined Saree', 2),
(12, 'images/products/images (27).jpg', 'Classic Silk Saree - Contrast Pallu', 0),
(12, 'images/products/images (28).jpg', 'Classic Silk Saree - Contrast Pallu', 1),
(12, 'images/products/images (29).jpg', 'Classic Silk Saree - Contrast Pallu', 2);

-- unit_id 6 = Piece (from variant_units)
INSERT INTO `product_variants` (`product_id`, `unit_id`, `label`, `unit_value`, `price`, `sale_price`, `stock`, `sku`, `image`, `is_default`, `sort_order`, `status`) VALUES
(1, 6, '1 pc', 1, 389.00, 329.00, 40, '2D-001-PC', 'images/products/images (1).jpg', 1, 0, 1),
(2, 6, '1 pc', 1, 359.00, 299.00, 40, '2D-002-PC', 'images/products/blue-banarasi-silk-saree-with-smashing-blouse-piece_3.webp', 1, 0, 1),
(3, 6, '1 pc', 1, 249.00, NULL, 40, '2D-003-PC', 'images/products/images (6).jpg', 1, 0, 1),
(4, 6, '1 pc', 1, 129.00, 99.00, 40, '2D-004-PC', 'images/products/saree-modeling-photography.jpg', 1, 0, 1),
(5, 6, '1 pc', 1, 89.00, NULL, 40, '2D-005-PC', 'images/products/Indian_Woman_Saree-2.webp', 1, 0, 1),
(6, 6, '1 pc', 1, 219.00, 179.00, 40, '2D-006-PC', 'images/products/designer-women-saree-model-18--758.jpg', 1, 0, 1),
(7, 6, '1 pc', 1, 159.00, NULL, 40, '2D-007-PC', 'images/products/images (15).jpg', 1, 0, 1),
(8, 6, '1 pc', 1, 499.00, 449.00, 40, '2D-008-PC', 'images/products/pngtree-indian-red-bridal-saree-red-dluhan-green-wedding-full-poses-png-image_14593372.png', 1, 0, 1),
(9, 6, '1 pc', 1, 429.00, NULL, 40, '2D-009-PC', 'images/products/images (19).jpg', 1, 0, 1),
(10, 6, '1 pc', 1, 79.00, 69.00, 40, '2D-010-PC', 'images/products/images (22).jpg', 1, 0, 1),
(11, 6, '1 pc', 1, 269.00, 229.00, 40, '2D-011-PC', 'images/products/new-model-sarees.jpg', 1, 0, 1),
(12, 6, '1 pc', 1, 199.00, NULL, 40, '2D-012-PC', 'images/products/images (27).jpg', 1, 0, 1);

INSERT INTO `category_nav_products` (`category_id`, `product_id`, `sort_order`) VALUES
(1, 1, 0),
(1, 2, 1),
(1, 3, 2),
(3, 6, 3),
(4, 8, 4);

INSERT INTO `sk_banners` (`title`, `subtitle`, `description`, `cta_text`, `cta_link`, `image`, `sort_order`, `status`, `type`, `created_at`, `updated_at`) VALUES
('Find Your Signature Style', 'Silk & cotton drapes for every occasion', 'Silk & cotton drapes for every occasion', 'Shop now', '/shop-default', 'assets/uploads/banners/banners_69f706968530d.jpg', 1, 1, 'hero', NOW(), NOW()),
('Your Ultimate Style Destination', 'New weaves, classic borders', 'New weaves, classic borders', 'Explore', '/shop-default', 'assets/uploads/banners/banners_69f7036350082.jpg', 2, 1, 'hero', NOW(), NOW()),
('Bridal & Wedding Collection', 'Ceremonial silks ready to ship', 'Ceremonial silks ready to ship', 'View bridal', '/shop-default', 'images/products/pngtree-indian-red-bridal-saree-red-dluhan-green-wedding-full-poses-png-image_14593372.png', 3, 1, 'hero', NOW(), NOW()),
('Festival specials', 'Selected silks on offer', 'Selected silks on offer', 'Shop offers', '/shop-default', 'assets/uploads/banners/banners_69ef44f2ef240.jpg', 1, 1, 'offer', NOW(), NOW()),
('Designer silks', 'Contemporary drapes', 'Contemporary drapes', 'Shop designer', '/shop-default', 'images/products/designer-women-saree-model-18--758.jpg', 1, 1, 'collection', NOW(), NOW()),
('Casual cotton', 'Light daily wear', 'Light daily wear', 'Shop cotton', '/shop-default', 'images/products/saree-modeling-photography.jpg', 2, 1, 'collection', NOW(), NOW());

INSERT INTO `sk_testimonials` (`author_name`, `author_title`, `quote`, `rating`, `product_id`, `status`, `sort_order`) VALUES
('Aisha R.', 'Kuala Lumpur', 'Beautiful drape and the gold border looks exactly like the photos.', 5, 1, 1, 0),
('Meera K.', 'Penang', 'Soft cotton, easy to wear for office. Fast packing.', 5, 4, 1, 1),
('Priya S.', 'Johor Bahru', 'Wedding saree quality is excellent. Family loved it.', 5, 8, 1, 2);

SET FOREIGN_KEY_CHECKS = 1;
