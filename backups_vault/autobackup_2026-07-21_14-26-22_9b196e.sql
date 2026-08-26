-- ADONAK ELECTRONICS COHESIVE AUTOMATED DATA SAFETY EXTRATION
-- Generated via Automated Scheduler Core: 2026-07-21 14:26:22

SET FOREIGN_KEY_CHECKS=0;



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
) ENGINE=InnoDB DEFAULT CHARSET=latin1;



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


CREATE TABLE `customer_wallets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `available_balance` decimal(10,2) NOT NULL DEFAULT '0.00',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `customer_wallets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=latin1;

INSERT INTO `customer_wallets` VALUES("1","10","500.00","2026-07-18 20:24:41");
INSERT INTO `customer_wallets` VALUES("2","10","460.00","2026-07-18 20:24:41");


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



CREATE TABLE `mock_safaricom_ledger` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `mpesa_code` varchar(20) NOT NULL,
  `sender_phone` varchar(20) NOT NULL,
  `actual_amount` decimal(10,2) NOT NULL,
  `transacted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mpesa_code` (`mpesa_code`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;



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
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=latin1;

INSERT INTO `order_items` VALUES("1","1","24","1","215.52","34.48","250.00");
INSERT INTO `order_items` VALUES("2","1","23","1","577.59","92.41","670.00");
INSERT INTO `order_items` VALUES("3","1","21","2","258.62","41.38","300.00");
INSERT INTO `order_items` VALUES("4","1","18","1","25.86","4.14","30.00");
INSERT INTO `order_items` VALUES("5","1","19","1","310.34","49.66","360.00");
INSERT INTO `order_items` VALUES("6","1","20","1","103.45","16.55","120.00");
INSERT INTO `order_items` VALUES("7","2","24","1","215.52","34.48","250.00");
INSERT INTO `order_items` VALUES("8","2","22","1","396.55","63.45","460.00");
INSERT INTO `order_items` VALUES("9","3","23","1","577.59","92.41","670.00");
INSERT INTO `order_items` VALUES("10","4","22","1","396.55","63.45","460.00");


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
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=latin1;

INSERT INTO `orders` VALUES("1","12","A001276890V","1750.00","280.00","16.00","2030.00","delivered","2026-07-13 15:37:28");
INSERT INTO `orders` VALUES("2","10","A003476452Q","612.07","97.93","16.00","710.00","processing","2026-07-14 19:14:29");
INSERT INTO `orders` VALUES("3","10","A003476452Q","0.00","0.00","16.00","0.00","pending","2026-07-14 19:23:25");
INSERT INTO `orders` VALUES("4","10","A003476452Q","396.55","63.45","16.00","460.00","pending","2026-07-14 19:29:56");


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
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=latin1;

INSERT INTO `payments` VALUES("1","1","M-Pesa","TXN_DD64FD170F","2030.00","completed","2026-07-13 15:37:28");
INSERT INTO `payments` VALUES("2","2","M-Pesa","TXN_DEB168970E","710.00","completed","2026-07-14 19:14:29");
INSERT INTO `payments` VALUES("3","2","Manual Store Credit Refund","REF_1784045873_97dd40","710.00","completed","2026-07-14 19:17:53");
INSERT INTO `payments` VALUES("4","3","Store Credit","WALLET_1784046205","670.00","completed","2026-07-14 19:23:25");
INSERT INTO `payments` VALUES("5","3","M-Pesa","TXN_60CF119E58","0.00","completed","2026-07-14 19:23:25");
INSERT INTO `payments` VALUES("6","4","COD","TXN_0ABB6FB36F","460.00","pending","2026-07-14 19:29:56");
INSERT INTO `payments` VALUES("7","4","Manual Store Credit Refund","REF_1784395481_d18cee","460.00","completed","2026-07-18 20:24:41");


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
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=latin1;

INSERT INTO `products` VALUES("18","18","18","procurement@adonak-distributors.co.ke","SAMSUNG S26 COVER","SUM-S26-10","30.00","33","uploads/prod_1783789547_69935314.jpg",NULL,"2026-07-11 20:05:47");
INSERT INTO `products` VALUES("19","20","20","procurement@adonak-distributors.co.ke","SAMSUNG S26 ULTRA","SA26-U2","360.00","29","uploads/prod_1783938331_c2e1e46f.jpg",NULL,"2026-07-13 13:25:31");
INSERT INTO `products` VALUES("20","21","25","procurement@adonak-distributors.co.ke","MIKA FRIDGE 138L","MF138L","120.00","29","uploads/prod_1783938513_e4e57570.jpg",NULL,"2026-07-13 13:28:33");
INSERT INTO `products` VALUES("21","20","21","procurement@adonak-distributors.co.ke","TECNO SPARK 40","TS40","300.00","98","uploads/prod_1783938612_cfe03e8f.jpg",NULL,"2026-07-13 13:30:12");
INSERT INTO `products` VALUES("22","17","20","procurement@adonak-distributors.co.ke","SAMSUNG LAPTOP","LP-SU34","460.00","198","uploads/prod_1783939079_83bb9146.jpg",NULL,"2026-07-13 13:37:59");
INSERT INTO `products` VALUES("23","17","27","procurement@adonak-distributors.co.ke","MACBOOK LAPTOP","LAP-MAC-001","670.00","43","uploads/prod_1783939157_cc123065.jpg","","2026-07-13 13:39:17");
INSERT INTO `products` VALUES("24","19","19","procurement@adonak-distributors.co.ke","TCL 55\" INCHES","TCL-55\'","250.00","38","uploads/prod_1783942359_2007d6a5.jpg",NULL,"2026-07-13 14:32:39");


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
) ENGINE=InnoDB AUTO_INCREMENT=46 DEFAULT CHARSET=latin1;

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
