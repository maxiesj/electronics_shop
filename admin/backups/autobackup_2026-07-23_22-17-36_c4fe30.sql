-- ADONAK Backup
-- Generated: 2026-07-23 22:17:36

SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `brands`;
CREATE TABLE `brands` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `brand_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `brand_name` (`brand_name`)
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=latin1;

INSERT INTO `brands` VALUES ('17', 'LENOVO', '2026-07-11 19:58:44');
INSERT INTO `brands` VALUES ('19', 'TCL', '2026-07-13 13:12:55');
INSERT INTO `brands` VALUES ('20', 'SAMSUNG', '2026-07-13 13:13:08');
INSERT INTO `brands` VALUES ('21', 'TECNO', '2026-07-13 13:14:21');
INSERT INTO `brands` VALUES ('22', 'VIVO', '2026-07-13 13:14:58');
INSERT INTO `brands` VALUES ('23', 'REDME', '2026-07-13 13:15:06');
INSERT INTO `brands` VALUES ('24', 'REALME', '2026-07-13 13:15:15');
INSERT INTO `brands` VALUES ('25', 'MIKA', '2026-07-13 13:15:56');
INSERT INTO `brands` VALUES ('27', 'APPLE', '2026-07-13 17:00:12');
INSERT INTO `brands` VALUES ('28', 'HISENSE', '2026-07-23 10:45:45');
INSERT INTO `brands` VALUES ('29', 'VITRON', '2026-07-23 11:34:12');
INSERT INTO `brands` VALUES ('30', 'ROYAL', '2026-07-23 11:34:18');
INSERT INTO `brands` VALUES ('32', 'ORAIMO', '2026-07-23 11:34:36');
INSERT INTO `brands` VALUES ('33', 'HP', '2026-07-23 16:35:33');
INSERT INTO `brands` VALUES ('34', 'DELL', '2026-07-23 16:35:43');


DROP TABLE IF EXISTS `cart`;
CREATE TABLE `cart` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `quantity` int(11) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `cart_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `cart_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=57 DEFAULT CHARSET=latin1;



DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=latin1;

INSERT INTO `categories` VALUES ('17', 'LAPTOPS', '2026-07-11 19:58:33');
INSERT INTO `categories` VALUES ('18', 'PHONE ACCESSORIES', '2026-07-11 19:59:44');
INSERT INTO `categories` VALUES ('19', 'TVS', '2026-07-13 13:12:46');
INSERT INTO `categories` VALUES ('20', 'SMART PHONES', '2026-07-13 13:14:12');
INSERT INTO `categories` VALUES ('21', 'FRIDGE', '2026-07-13 13:15:49');
INSERT INTO `categories` VALUES ('22', 'SOUND BARS', '2026-07-22 23:25:08');
INSERT INTO `categories` VALUES ('24', 'DESKTOP', '2026-07-23 16:35:25');


DROP TABLE IF EXISTS `customer_wallets`;
CREATE TABLE `customer_wallets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `available_balance` decimal(10,2) NOT NULL DEFAULT '0.00',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `customer_wallets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=latin1;

INSERT INTO `customer_wallets` VALUES ('10', '7', '1516.00', '2026-07-23 16:35:06');
INSERT INTO `customer_wallets` VALUES ('11', '7', '846.00', '2026-07-23 16:35:06');
INSERT INTO `customer_wallets` VALUES ('12', '7', '463.00', '2026-07-23 16:35:06');
INSERT INTO `customer_wallets` VALUES ('13', '11', '344.00', '2026-07-22 23:30:50');
INSERT INTO `customer_wallets` VALUES ('14', '12', '6.00', '2026-07-22 23:37:23');
INSERT INTO `customer_wallets` VALUES ('15', '7', '80.00', '2026-07-23 16:35:06');
INSERT INTO `customer_wallets` VALUES ('16', '7', '40.00', '2026-07-23 16:35:06');


DROP TABLE IF EXISTS `layaway_plans`;
CREATE TABLE `layaway_plans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `deposit_paid` decimal(10,2) NOT NULL,
  `balance_remaining` decimal(10,2) NOT NULL,
  `status` enum('Active','Fully Paid','Cancelled') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `layaway_plans_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `layaway_plans_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;



DROP TABLE IF EXISTS `mock_safaricom_ledger`;
CREATE TABLE `mock_safaricom_ledger` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `mpesa_code` varchar(20) NOT NULL,
  `sender_phone` varchar(20) NOT NULL,
  `actual_amount` decimal(10,2) NOT NULL,
  `transacted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mpesa_code` (`mpesa_code`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;



DROP TABLE IF EXISTS `mpesa_reconciliations`;
CREATE TABLE `mpesa_reconciliations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_code` varchar(20) NOT NULL,
  `customer_name` varchar(100) NOT NULL,
  `claimed_cash` decimal(10,2) NOT NULL,
  `submitted_mpesa_code` varchar(15) NOT NULL,
  `ledger_state` enum('Pending','Verified','Mismatched','Failed') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `submitted_mpesa_code` (`submitted_mpesa_code`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;



DROP TABLE IF EXISTS `order_items`;
CREATE TABLE `order_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `net_price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `vat_price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `price` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `fk_order_items_product` (`product_id`),
  CONSTRAINT `fk_order_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
  CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=64 DEFAULT CHARSET=latin1;

INSERT INTO `order_items` VALUES ('63', '17', NULL, '4', '603.45', '96.55', '700.00');


DROP TABLE IF EXISTS `orders`;
CREATE TABLE `orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `kra_pin` varchar(20) DEFAULT NULL,
  `net_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `vat_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `applied_tax_rate` decimal(5,2) NOT NULL DEFAULT '16.00',
  `total_amount` decimal(10,2) NOT NULL,
  `order_status` enum('pending','paid','processing','delivered','cancelled') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=latin1;

INSERT INTO `orders` VALUES ('17', '7', 'A051534789G', '2413.79', '386.21', '16.00', '2800.00', 'delivered', '2026-07-23 18:44:02');


DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `transaction_code` varchar(100) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `payment_status` enum('pending','completed','failed') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=latin1;

INSERT INTO `payments` VALUES ('37', NULL, 'Manual Admin Adjustment', 'MAN_1784813698_579d', '40.00', 'completed', '2026-07-23 16:34:58');
INSERT INTO `payments` VALUES ('38', NULL, 'Manual Admin Adjustment', 'MAN_1784813706_7701', '40.00', 'completed', '2026-07-23 16:35:06');
INSERT INTO `payments` VALUES ('39', '17', 'M-Pesa', 'TXN_B2F64EF269', '2800.00', 'completed', '2026-07-23 18:44:02');


DROP TABLE IF EXISTS `product_reviews`;
CREATE TABLE `product_reviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `customer_name` varchar(100) NOT NULL,
  `star_rating` int(11) NOT NULL,
  `review_comment` text NOT NULL,
  `is_approved` int(11) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `product_reviews_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `product_reviews_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;



DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) NOT NULL,
  `brand_id` int(11) NOT NULL,
  `supplier_email` varchar(100) NOT NULL DEFAULT 'procurement@adonak-distributors.co.ke',
  `product_name` varchar(255) NOT NULL,
  `sku` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `stock_quantity` int(11) NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `description` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `category_id` (`category_id`),
  KEY `brand_id` (`brand_id`),
  CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`),
  CONSTRAINT `products_ibfk_2` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=55 DEFAULT CHARSET=latin1;

INSERT INTO `products` VALUES ('52', '21', '25', 'mika.infor@gmail.com', '190L MIKE FRIDGE', 'FREE-01', '240.00', '20', '1784833532_✅ HISENSE RD27 (205L) 📦 Friji ya kisasa yenye….jpg', 'high quality products, 1 year warrant.', '2026-07-23 22:05:32');
INSERT INTO `products` VALUES ('53', '20', '21', 'tecno@gmail.com', 'TECNO SPARK 20', 'SPAR20', '200.00', '11', '1784834408_561331541074925554.jpg', 'great technology invested in this device, one year warrant', '2026-07-23 22:20:08');
INSERT INTO `products` VALUES ('54', '20', '20', 'SAMSUNG@GMAIL.COM', 'SUMSANG S26 ULTRA', 'SAMSUNGS26', '300.00', '10', '1784834661_Samsung Galaxy S26 Ultra Case with Card Holder.jpg', '', '2026-07-23 22:24:21');


DROP TABLE IF EXISTS `refund_logs`;
CREATE TABLE `refund_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `payment_id` int(11) NOT NULL,
  `amount_processed` decimal(10,2) NOT NULL,
  `resolution_type` enum('M-Pesa Reversal','Converted to Credit') NOT NULL,
  `reversal_reference` varchar(50) DEFAULT NULL,
  `processed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  CONSTRAINT `refund_logs_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;



DROP TABLE IF EXISTS `staff_attendance`;
CREATE TABLE `staff_attendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `staff_name` varchar(100) NOT NULL,
  `clock_in_time` datetime NOT NULL,
  `clock_out_time` datetime DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `shift_status` enum('Active','Completed','Force Closed') NOT NULL DEFAULT 'Active',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `staff_attendance_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;



DROP TABLE IF EXISTS `staff_logs`;
CREATE TABLE `staff_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `staff_name` varchar(100) NOT NULL,
  `action_type` enum('Staff Login','Inventory Update','Product Deletion') NOT NULL,
  `product_target` varchar(255) DEFAULT NULL,
  `action_details` text NOT NULL,
  `logged_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `staff_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=65 DEFAULT CHARSET=latin1;

INSERT INTO `staff_logs` VALUES ('63', '5', 'Maxies John', 'Inventory Update', NULL, 'Procurement Reorder Dispatched: Generated formal inventory replenishment notification for model [TCL SMART TV 55\'] to agent email [TCL@IINFO.KE.CO] asking for +21 turnover items.', '2026-07-23 14:43:55');
INSERT INTO `staff_logs` VALUES ('64', '5', 'Maxies John', 'Staff Login', NULL, 'Secure entry validated via IP: ::1', '2026-07-23 22:02:55');


DROP TABLE IF EXISTS `system_settings`;
CREATE TABLE `system_settings` (
  `setting_key` varchar(50) NOT NULL,
  `setting_value` varchar(255) NOT NULL,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

INSERT INTO `system_settings` VALUES ('tax_rate', '8.00');
INSERT INTO `system_settings` VALUES ('tax_rate_archived_1784630812', '16.00');
INSERT INTO `system_settings` VALUES ('tax_rate_archived_1784630842', '16.00');
INSERT INTO `system_settings` VALUES ('tax_rate_archived_1784630860', '12.00');
INSERT INTO `system_settings` VALUES ('tax_rate_archived_1784631006', '14.00');
INSERT INTO `system_settings` VALUES ('tax_rate_archived_1784631313', '13.00');
INSERT INTO `system_settings` VALUES ('tax_rate_archived_1784721904', '17.00');
INSERT INTO `system_settings` VALUES ('tax_rate_archived_1784721944', '18.00');
INSERT INTO `system_settings` VALUES ('tax_rate_archived_1784721961', '19.00');
INSERT INTO `system_settings` VALUES ('tax_rate_archived_1784721980', '10.00');
INSERT INTO `system_settings` VALUES ('tax_rate_archived_1784722646', '8.00');
INSERT INTO `system_settings` VALUES ('tax_rate_archived_1784722696', '5.00');
INSERT INTO `system_settings` VALUES ('tax_rate_archived_1784723057', '4.00');
INSERT INTO `system_settings` VALUES ('tax_rate_archived_1784723150', '16.00');
INSERT INTO `system_settings` VALUES ('tax_rate_archived_1784723488', '0.00');
INSERT INTO `system_settings` VALUES ('tax_rate_archived_1784723544', '20.00');
INSERT INTO `system_settings` VALUES ('tax_rate_archived_1784723859', '90.00');
INSERT INTO `system_settings` VALUES ('tax_rate_archived_1784723891', '21.00');
INSERT INTO `system_settings` VALUES ('tax_rate_archived_1784724015', '22.00');
INSERT INTO `system_settings` VALUES ('tax_rate_archived_1784724106', '5.00');
INSERT INTO `system_settings` VALUES ('tax_rate_archived_1784725106', '6.00');
INSERT INTO `system_settings` VALUES ('tax_rate_archived_1784725582', '7.00');


DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fullname` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` int(20) NOT NULL,
  `password` varchar(255) DEFAULT NULL,
  `role` enum('admin','staff','customer') DEFAULT 'customer',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `shipping_phone` varchar(20) DEFAULT NULL,
  `shipping_address` text,
  `kra_pin` varchar(20) DEFAULT NULL,
  `reset_token_hash` varchar(64) DEFAULT NULL,
  `reset_token_expires_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phone` (`phone`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `reset_token_hash` (`reset_token_hash`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=latin1;

INSERT INTO `users` VALUES ('5', 'Maxies John', 'maxiesj6@gmail.com', '790900055', '$2y$10$OBuB1I5Zmz.s1ROQ7sRzieNEYs8T6AdbI3r2.cPBnjY9N5eyzhWTm', 'admin', '2026-07-07 23:30:00', NULL, NULL, NULL, NULL, NULL);
INSERT INTO `users` VALUES ('6', 'sarah andrew', 'sarah@gmail.com', '721212121', '$2y$10$82HskpfaQR5JWSOjWXmJ1OrTWGoT9kVPMXPGNti7.jLKmke2dVSOq', 'staff', '2026-07-08 18:03:32', NULL, NULL, NULL, NULL, NULL);
INSERT INTO `users` VALUES ('7', 'james kimani', 'james@gmail.com', '797898989', '$2y$10$yEOF6V67dBTT7yHZNFGDo.xH1zpYLQBTdxgjm8qPZJWSsx5OEB7qu', 'customer', '2026-07-08 19:03:43', '0712121212', 'JERU, ACTION VILLA PLAZA, 1ST FLOOR, HOUSE NUMBER 103', 'A051534789G', NULL, NULL);
INSERT INTO `users` VALUES ('10', 'peter otieno', 'otieno@gmail.com', '740235465', '$2y$10$BT60EpqyQWcwiuKxhfmegeIi9R/XXIYeGL5VUT5BY0zyh4U3xQtSO', 'customer', '2026-07-11 19:52:59', '0740235465', 'ACTION VILLA FLATS, JERU 1ST FLOOR ROOM 301', 'A003476452Q', NULL, NULL);
INSERT INTO `users` VALUES ('11', 'james allan', 'allan@gmail.com', '789898989', '$2y$10$zTO2Q4RPu6Kuv1g9cizXIuD8kzSTzZkM7cSd0qtsKwuQ/e6OMAQLa', 'customer', '2026-07-11 19:54:08', NULL, NULL, NULL, NULL, NULL);
INSERT INTO `users` VALUES ('12', 'john allan', 'maxiesj7@gmail.com', '787898989', '$2y$10$GWzIqk8jO2.DYwGstuzbv.yEmjpTUvzpSAJtRkrgEIQuvHvB8O/aC', 'customer', '2026-07-11 19:54:47', '0789890990', 'JERUSALEM, ACTION VILLA,, 2ND FLOOR HOUSE NUMBER 306', 'A001276890V', 'f187561bcb97863789451ab2e08068b77f8d552ea71e3a709ab314c07c7869e1', '2026-07-12 20:38:48');


SET FOREIGN_KEY_CHECKS=1;
