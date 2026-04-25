-- Add missing columns to products table
ALTER TABLE `products` ADD COLUMN IF NOT EXISTS `is_new_arrival` tinyint(1) NOT NULL DEFAULT 0 AFTER `is_featured`;
ALTER TABLE `products` ADD COLUMN IF NOT EXISTS `is_bestseller` tinyint(1) NOT NULL DEFAULT 0 AFTER `is_new_arrival`;
ALTER TABLE `products` ADD COLUMN IF NOT EXISTS `occasion` varchar(100) DEFAULT NULL AFTER `care`;
ALTER TABLE `products` ADD COLUMN IF NOT EXISTS `weight_grams` int(11) DEFAULT NULL AFTER `occasion`;
ALTER TABLE `products` ADD COLUMN IF NOT EXISTS `meta_title` varchar(255) DEFAULT NULL AFTER `weight_grams`;
ALTER TABLE `products` ADD COLUMN IF NOT EXISTS `meta_description` text DEFAULT NULL AFTER `meta_title`;
