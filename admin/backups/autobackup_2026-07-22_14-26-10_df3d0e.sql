-- ADONAK ELECTRONICS CORE INTEGRITY LEDGER BACKUP
-- GENERATED TIMESTAMP MAP: 2026-07-22 14:26:10

SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `brands`;
CREATE TABLE `brands` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `brand_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=latin1;

INSERT INTO `brands` VALUES("17","LENOVO","2026-07-11 19:58:44");
INSERT INTO `brands` VALUES("18","SAMSUNG","2026-07-11 19:58:59");
INSERT INTO `brands` VALUES("19","TCL","2026-07-13 13:12:55");
INSERT INTO `brands` VALUES("20","SAMSUNG","2026-07-13 13:13:08");
INSERT INTO `brands` VALUES("21","TECNO","2026-07-13 13:14:21");
INSERT INTO `brands` VALUES("22","VIVO","2026-07-13 13:14:58");
INSERT INTO `brands` VALUES("23","REDME","2026-07-13 13:15:06");
INSERT INTO `brands` VALUES("24","REALME","2026-07-13 13:15:15");
INSERT INTO `brands` VALUES("25","MIKA","2026-07-13 13:15:56");
INSERT INTO `brands` VALUES("27","APPLE","2026-07-13 17:00:12");


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
) ENGINE=InnoDB AUTO_INCREMENT=48 DEFAULT CHARSET=latin1;

INSERT INTO `cart` VALUES("47","7","39","1","2026-07-22 14:07:07");


DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=latin1;

INSERT INTO `categories` VALUES("17","LAPTOPS","2026-07-11 19:58:33");
INSERT INTO `categories` VALUES("18","PHONE ACCESSORIES","2026-07-11 19:59:44");
INSERT INTO `categories` VALUES("19","TVS","2026-07-13 13:12:46");
INSERT INTO `categories` VALUES("20","SMART PHONES","2026-07-13 13:14:12");
INSERT INTO `categories` VALUES("21","FRIDGE","2026-07-13 13:15:49");


DROP TABLE IF EXISTS `customer_wallets`;
CREATE TABLE `customer_wallets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `available_balance` decimal(10,2) NOT NULL DEFAULT '0.00',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `customer_wallets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=latin1;

INSERT INTO `customer_wallets` VALUES("10","7","1053.00","2026-07-22 12:06:36");
INSERT INTO `customer_wallets` VALUES("11","7","383.00","2026-07-22 12:06:36");


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
) ENGINE=InnoDB AUTO_INCREMENT=55 DEFAULT CHARSET=latin1;

INSERT INTO `order_items` VALUES("51","12","33","1","577.59","92.41","670.00");
INSERT INTO `order_items` VALUES("52","12","34","1","577.59","92.41","670.00");
INSERT INTO `order_items` VALUES("53","13","33","1","577.59","92.41","670.00");
INSERT INTO `order_items` VALUES("54","15","40","1","57.76","9.24","67.00");


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
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=latin1;

INSERT INTO `orders` VALUES("12","7","A051534789G","1155.17","184.83","16.00","0.00","cancelled","2026-07-22 11:59:22");
INSERT INTO `orders` VALUES("13","7","A051534789G","0.00","0.00","16.00","0.00","pending","2026-07-22 12:00:14");
INSERT INTO `orders` VALUES("14","7",NULL,"0.00","0.00","16.00","0.00","cancelled","2026-07-22 12:03:18");
INSERT INTO `orders` VALUES("15","7","A051534789G","0.00","0.00","16.00","0.00","pending","2026-07-22 12:06:36");


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
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=latin1;

INSERT INTO `payments` VALUES("25","12","M-Pesa","TXN_41338327D9","1340.00","completed","2026-07-22 11:59:22");
INSERT INTO `payments` VALUES("26","12","Manual Store Credit Refund","REF_1784710786_8c7edd","1340.00","completed","2026-07-22 11:59:46");
INSERT INTO `payments` VALUES("27","13","Store Credit","WALLET_1784710814","670.00","completed","2026-07-22 12:00:14");
INSERT INTO `payments` VALUES("28","13","M-Pesa","TXN_B16CBD80AA","0.00","completed","2026-07-22 12:00:14");
INSERT INTO `payments` VALUES("29","14","M-Pesa","TXN_TEST12345","450.00","completed","2026-07-22 12:03:18");
INSERT INTO `payments` VALUES("30","14","Manual Store Credit Refund","REF_1784711168_0ab229","450.00","completed","2026-07-22 12:06:08");
INSERT INTO `payments` VALUES("31","15","Store Credit","WALLET_1784711196","67.00","completed","2026-07-22 12:06:36");
INSERT INTO `payments` VALUES("32","15","M-Pesa","TXN_FF80534FAA","0.00","completed","2026-07-22 12:06:36");


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
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=latin1;

INSERT INTO `products` VALUES("28","20","21","procurement@adonak-distributors.co.ke","CAMON 30","CAM30","600.00","8","prod_1784646347_83dbad.jpg",NULL,"2026-07-21 18:05:47");
INSERT INTO `products` VALUES("29","20","21","procurement@adonak-distributors.co.ke","TECNO SPARK 40","spc40","70.00","83","prod_1784661636_4da7c7.jpg",NULL,"2026-07-21 22:20:36");
INSERT INTO `products` VALUES("30","20","21","procurement@adonak-distributors.co.ke","TECNO SPARK 40","spc40","70.00","82","prod_1784661636_3d655b.jpg",NULL,"2026-07-21 22:20:36");
INSERT INTO `products` VALUES("31","19","19","procurement@adonak-distributors.co.ke","tcl 43","hise","700.00","13","prod_1784662270_9105c4.jpg",NULL,"2026-07-21 22:31:10");
INSERT INTO `products` VALUES("33","21","27","procurement@adonak-distributors.co.ke","TECNO SPARK 20","KH2-12","670.00","14","prod_1784662518_b78cca.jpg",NULL,"2026-07-21 22:35:18");
INSERT INTO `products` VALUES("34","21","27","procurement@adonak-distributors.co.ke","TECNO SPARK 20","KH2-12","670.00","16","prod_1784662518_3b8c65.jpg",NULL,"2026-07-21 22:35:18");
INSERT INTO `products` VALUES("35","21","21","procurement@adonak-distributors.co.ke","TECNO SPARK 40","CAM30","56.00","61","prod_1784662774_b119a4.jpg",NULL,"2026-07-21 22:39:34");
INSERT INTO `products` VALUES("37","20","21","procurement@adonak-distributors.co.ke","TECNO SPARK 20","KH2-12","120.00","8","prod_1784663090_66038c.jpg",NULL,"2026-07-21 22:44:50");
INSERT INTO `products` VALUES("38","18","19","procurement@adonak-distributors.co.ke","TECNO SPARK 20","KH2-12","6.00","11","prod_1784663212_fd0180.jpg",NULL,"2026-07-21 22:46:52");
INSERT INTO `products` VALUES("39","21","25","procurement@adonak-distributors.co.ke","TECNO SPARK 20","KH2-12","12345.00","123448","prod_1784663452_a84867.jpg",NULL,"2026-07-21 22:50:52");
INSERT INTO `products` VALUES("40","20","17","procurement@adonak-distributors.co.ke","TECNO SPARK 20","KH2-12","67.00","41","prod_1784663900_8deb17.jpg","","2026-07-21 22:58:20");
INSERT INTO `products` VALUES("41","20","21","procurement@adonak-distributors.co.ke","tecno","tc","899.00","124","prod_1784711450_d7ce34.jpg",NULL,"2026-07-22 12:10:50");
INSERT INTO `products` VALUES("42","20","21","procurement@adonak-distributors.co.ke","tecno","tc","899.00","124","prod_1784711450_897940.jpg",NULL,"2026-07-22 12:10:50");
INSERT INTO `products` VALUES("43","21","25","procurement@adonak-distributors.co.ke","190L MIKE FRIDGE","FREE-01","780.00","20","prod_1784711665_962ea1.jpg",NULL,"2026-07-22 12:14:25");
INSERT INTO `products` VALUES("44","21","25","procurement@adonak-distributors.co.ke","190L MIKE FRIDGE","FREE-01","780.00","0","prod_1784711665_f6ac9b.jpg","","2026-07-22 12:14:25");


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
) ENGINE=InnoDB AUTO_INCREMENT=58 DEFAULT CHARSET=latin1;

INSERT INTO `staff_logs` VALUES("25","5","Maxies John","Staff Login",NULL,"Secure entry validated via IP: ::1","2026-07-11 19:46:16");
INSERT INTO `staff_logs` VALUES("26","5","Maxies John","Staff Login",NULL,"Secure entry validated via IP: ::1","2026-07-12 18:53:32");
INSERT INTO `staff_logs` VALUES("27","5","Maxies John","Staff Login",NULL,"Secure entry validated via IP: ::1","2026-07-12 22:43:20");
INSERT INTO `staff_logs` VALUES("28","5","Maxies John","Staff Login",NULL,"Secure entry validated via IP: ::1","2026-07-12 22:51:11");
INSERT INTO `staff_logs` VALUES("29","5","Maxies John","Inventory Update",NULL,"Automated Storage Recovery Sweep: Backed up database to backups_vault folder. File name: autobackup_2026-07-12_21-51-18_7fcf33.sql (11.53 KB). Retained files verified healthy.","2026-07-12 22:51:18");
INSERT INTO `staff_logs` VALUES("30","5","Maxies John","Inventory Update",NULL,"Automated Storage Recovery Sweep: Backed up database to backups_vault folder. File name: autobackup_2026-07-12_22-55-04_0090ea.sql (11.81 KB). Retained files verified healthy.","2026-07-12 22:55:04");
INSERT INTO `staff_logs` VALUES("31","5","Maxies John","Staff Login",NULL,"Secure entry validated via IP: 127.0.0.1","2026-07-13 12:59:09");
INSERT INTO `staff_logs` VALUES("32","5","Maxies John","Staff Login",NULL,"Secure entry validated via IP: ::1","2026-07-13 22:07:42");
INSERT INTO `staff_logs` VALUES("33","6","sarah andrew","Staff Login",NULL,"Secure staff desk entry validated via IP: ::1","2026-07-13 22:10:28");
INSERT INTO `staff_logs` VALUES("34","5","Maxies John","Staff Login",NULL,"Secure entry validated via IP: ::1","2026-07-14 19:15:53");
INSERT INTO `staff_logs` VALUES("35","6","sarah andrew","Staff Login",NULL,"Secure staff desk entry validated via IP: ::1","2026-07-14 19:35:28");
INSERT INTO `staff_logs` VALUES("36","5","Maxies John","Staff Login",NULL,"Secure entry validated via IP: ::1","2026-07-16 17:43:04");
INSERT INTO `staff_logs` VALUES("37","5","Maxies John","Staff Login",NULL,"Secure entry validated via IP: ::1","2026-07-18 20:14:16");
INSERT INTO `staff_logs` VALUES("38","5","Maxies John","Staff Login",NULL,"Secure entry validated via IP: ::1","2026-07-18 20:43:11");
INSERT INTO `staff_logs` VALUES("39","5","Maxies John","Staff Login",NULL,"Secure entry validated via IP: ::1","2026-07-18 21:50:52");
INSERT INTO `staff_logs` VALUES("40","5","Maxies John","Staff Login",NULL,"Secure entry validated via IP: ::1","2026-07-18 22:25:23");
INSERT INTO `staff_logs` VALUES("41","5","Maxies John","Staff Login",NULL,"Secure entry validated via IP: 127.0.0.1","2026-07-20 11:43:57");
INSERT INTO `staff_logs` VALUES("42","5","Maxies John","Staff Login",NULL,"Secure entry validated via IP: ::1","2026-07-20 12:05:26");
INSERT INTO `staff_logs` VALUES("43","5","Maxies John","Staff Login",NULL,"Secure entry validated via IP: ::1","2026-07-20 12:08:17");
INSERT INTO `staff_logs` VALUES("44","5","Maxies John","Staff Login",NULL,"Secure entry validated via IP: ::1","2026-07-20 12:08:36");
INSERT INTO `staff_logs` VALUES("45","5","Maxies John","Staff Login",NULL,"Secure entry validated via IP: ::1","2026-07-20 21:29:17");
INSERT INTO `staff_logs` VALUES("46","5","Maxies John","Inventory Update",NULL,"Automated Storage Recovery Sweep: Backed up database to backups_vault folder. File name: autobackup_2026-07-21_14-26-22_9b196e.sql (18.77 KB). Retained files verified healthy.","2026-07-21 14:26:22");
INSERT INTO `staff_logs` VALUES("47","5","Maxies John","Inventory Update",NULL,"Automated Storage Recovery Sweep: Backed up database to backups_vault folder. File name: autobackup_2026-07-21_14-26-28_1d2717.sql (19.05 KB). Retained files verified healthy.","2026-07-21 14:26:28");
INSERT INTO `staff_logs` VALUES("48","5","Maxies John","",NULL,"Automated Snapshot Log Generated: [autobackup_2026-07-21_15-07-09_92135cea.sql] saved to backups_vault.","2026-07-21 15:07:09");
INSERT INTO `staff_logs` VALUES("49","5","Maxies John","",NULL,"Automated Snapshot Log Generated: [autobackup_2026-07-21_15-11-41_ebc7b6ea.sql] saved to backups_vault.","2026-07-21 15:11:41");
INSERT INTO `staff_logs` VALUES("50","5","Maxies John","Staff Login",NULL,"Secure entry validated via IP: ::1","2026-07-21 15:37:12");
INSERT INTO `staff_logs` VALUES("51","5","Maxies John","Staff Login",NULL,"Secure entry validated via IP: ::1","2026-07-21 18:23:23");
INSERT INTO `staff_logs` VALUES("52","5","Maxies John","Staff Login",NULL,"Secure entry validated via IP: ::1","2026-07-21 22:17:58");
INSERT INTO `staff_logs` VALUES("53","5","Maxies John","Staff Login",NULL,"Secure entry validated via IP: ::1","2026-07-21 23:16:14");
INSERT INTO `staff_logs` VALUES("54","5","Maxies John","Staff Login",NULL,"Secure entry validated via IP: ::1","2026-07-21 23:19:33");
INSERT INTO `staff_logs` VALUES("55","5","Maxies John","Staff Login",NULL,"Secure entry validated via IP: ::1","2026-07-22 01:35:30");
INSERT INTO `staff_logs` VALUES("56","5","Maxies John","Staff Login",NULL,"Secure entry validated via IP: ::1","2026-07-22 09:23:22");
INSERT INTO `staff_logs` VALUES("57","5","Maxies John","Staff Login",NULL,"Secure entry validated via IP: ::1","2026-07-22 11:43:01");


DROP TABLE IF EXISTS `system_settings`;
CREATE TABLE `system_settings` (
  `setting_key` varchar(50) NOT NULL,
  `setting_value` varchar(255) NOT NULL,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

INSERT INTO `system_settings` VALUES("tax_rate","17.00");
INSERT INTO `system_settings` VALUES("tax_rate_archived_1784630812","16.00");
INSERT INTO `system_settings` VALUES("tax_rate_archived_1784630842","16.00");
INSERT INTO `system_settings` VALUES("tax_rate_archived_1784630860","12.00");
INSERT INTO `system_settings` VALUES("tax_rate_archived_1784631006","14.00");
INSERT INTO `system_settings` VALUES("tax_rate_archived_1784631313","13.00");


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

INSERT INTO `users` VALUES("5","Maxies John","maxiesj6@gmail.com","790900055","$2y$10$OBuB1I5Zmz.s1ROQ7sRzieNEYs8T6AdbI3r2.cPBnjY9N5eyzhWTm","admin","2026-07-07 23:30:00",NULL,NULL,NULL,NULL,NULL);
INSERT INTO `users` VALUES("6","sarah andrew","sarah@gmail.com","721212121","$2y$10$82HskpfaQR5JWSOjWXmJ1OrTWGoT9kVPMXPGNti7.jLKmke2dVSOq","staff","2026-07-08 18:03:32",NULL,NULL,NULL,NULL,NULL);
INSERT INTO `users` VALUES("7","james kimani","james@gmail.com","797898989","$2y$10$yEOF6V67dBTT7yHZNFGDo.xH1zpYLQBTdxgjm8qPZJWSsx5OEB7qu","customer","2026-07-08 19:03:43","0712121212","JERU, ACTION VILLA PLAZA, 1ST FLOOR, HOUSE NUMBER 103","A051534789G",NULL,NULL);
INSERT INTO `users` VALUES("9","john andrew","andrew@gmail.com","723434565","$2y$10$.5KurCitXjMMuS.GDf6cWuZkNPClXXS1rTR6nYu7szbOxb8NCdE8S","customer","2026-07-11 19:52:26",NULL,NULL,NULL,NULL,NULL);
INSERT INTO `users` VALUES("10","peter otieno","otieno@gmail.com","740235465","$2y$10$BT60EpqyQWcwiuKxhfmegeIi9R/XXIYeGL5VUT5BY0zyh4U3xQtSO","customer","2026-07-11 19:52:59","0740235465","ACTION VILLA FLATS, JERU 1ST FLOOR ROOM 301","A003476452Q",NULL,NULL);
INSERT INTO `users` VALUES("11","james allan","allan@gmail.com","789898989","$2y$10$zTO2Q4RPu6Kuv1g9cizXIuD8kzSTzZkM7cSd0qtsKwuQ/e6OMAQLa","customer","2026-07-11 19:54:08",NULL,NULL,NULL,NULL,NULL);
INSERT INTO `users` VALUES("12","john allan","maxiesj7@gmail.com","787898989","$2y$10$GWzIqk8jO2.DYwGstuzbv.yEmjpTUvzpSAJtRkrgEIQuvHvB8O/aC","customer","2026-07-11 19:54:47","0789890990","JERUSALEM, ACTION VILLA,, 2ND FLOOR HOUSE NUMBER 306","A001276890V","f187561bcb97863789451ab2e08068b77f8d552ea71e3a709ab314c07c7869e1","2026-07-12 20:38:48");


SET FOREIGN_KEY_CHECKS=1;
