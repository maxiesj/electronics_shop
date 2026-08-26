-- ADONAK Electronics database backup
-- Generated: 2026-08-11 21:32:23

SET FOREIGN_KEY_CHECKS=0;
SET NAMES utf8mb4;

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
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=latin1;


DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `category_name` (`category_name`)
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
  `is_active_toggle` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  CONSTRAINT `customer_wallets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=latin1;

INSERT INTO `customer_wallets` VALUES ('13', '11', '94.00', '2026-07-26 18:14:59', '0');
INSERT INTO `customer_wallets` VALUES ('16', '7', '460.60', '2026-08-06 22:56:16', '0');
INSERT INTO `customer_wallets` VALUES ('17', '12', '570.00', '2026-08-11 12:40:09', '0');
INSERT INTO `customer_wallets` VALUES ('18', '10', '450.00', '2026-07-28 01:24:41', '0');
INSERT INTO `customer_wallets` VALUES ('19', '15', '502750.00', '2026-08-11 13:29:56', '1');

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
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=latin1;

INSERT INTO `layaway_plans` VALUES ('6', '33', '12', '240.00', '72.00', '168.00', 'Active', '2026-07-24 02:16:31');
INSERT INTO `layaway_plans` VALUES ('7', '38', '12', '740.00', '739.00', '1.00', 'Active', '2026-07-24 03:39:57');
INSERT INTO `layaway_plans` VALUES ('8', '39', '12', '240.00', '172.00', '68.00', 'Active', '2026-07-24 13:22:46');
INSERT INTO `layaway_plans` VALUES ('9', '54', '7', '727.60', '363.80', '363.80', 'Active', '2026-07-24 22:06:11');
INSERT INTO `layaway_plans` VALUES ('10', '55', '7', '1840.40', '920.20', '920.20', 'Active', '2026-07-24 22:10:25');
INSERT INTO `layaway_plans` VALUES ('11', '56', '7', '256.80', '256.80', '0.00', '', '2026-07-24 22:15:58');
INSERT INTO `layaway_plans` VALUES ('12', '57', '7', '256.80', '256.80', '0.00', '', '2026-07-24 22:26:53');
INSERT INTO `layaway_plans` VALUES ('13', '58', '7', '1540.80', '1540.80', '0.00', '', '2026-07-24 22:34:04');
INSERT INTO `layaway_plans` VALUES ('14', '59', '7', '321.00', '321.00', '0.00', '', '2026-07-24 22:34:38');
INSERT INTO `layaway_plans` VALUES ('15', '60', '7', '256.80', '256.80', '0.00', '', '2026-07-24 22:45:13');
INSERT INTO `layaway_plans` VALUES ('16', '61', '7', '577.80', '577.80', '0.00', '', '2026-07-24 22:51:04');
INSERT INTO `layaway_plans` VALUES ('17', '62', '7', '321.00', '321.00', '0.00', '', '2026-07-24 22:55:51');
INSERT INTO `layaway_plans` VALUES ('18', '63', '7', '214.00', '0.00', '0.00', '', '2026-07-24 23:04:00');
INSERT INTO `layaway_plans` VALUES ('19', '64', '7', '214.00', '214.00', '0.00', '', '2026-07-24 23:11:41');
INSERT INTO `layaway_plans` VALUES ('20', '66', '12', '300.00', '0.00', '300.00', 'Active', '2026-07-25 18:02:36');
INSERT INTO `layaway_plans` VALUES ('21', '67', '12', '600.00', '300.00', '300.00', 'Active', '2026-07-25 18:21:43');
INSERT INTO `layaway_plans` VALUES ('22', '69', '12', '300.00', '300.00', '0.00', '', '2026-07-25 20:21:03');
INSERT INTO `layaway_plans` VALUES ('23', '70', '12', '10780.00', '5390.00', '5390.00', 'Active', '2026-07-25 20:50:22');
INSERT INTO `layaway_plans` VALUES ('24', '73', '11', '500.00', '250.00', '250.00', 'Active', '2026-07-26 18:14:59');
INSERT INTO `layaway_plans` VALUES ('25', '84', '7', '200.00', '200.00', '0.00', '', '2026-07-27 10:25:14');
INSERT INTO `layaway_plans` VALUES ('26', '89', '10', '300.00', '150.00', '150.00', 'Active', '2026-07-28 01:24:41');
INSERT INTO `layaway_plans` VALUES ('27', '100', '15', '470500.00', '242250.00', '228250.00', 'Active', '2026-08-11 12:23:23');

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
) ENGINE=InnoDB AUTO_INCREMENT=186 DEFAULT CHARSET=latin1;

INSERT INTO `order_items` VALUES ('63', '17', NULL, '4', '603.45', '96.55', '700.00');
INSERT INTO `order_items` VALUES ('64', '18', '52', '1', '206.90', '33.10', '240.00');
INSERT INTO `order_items` VALUES ('65', '18', '53', '1', '172.41', '27.59', '200.00');
INSERT INTO `order_items` VALUES ('66', '18', '54', '1', '258.62', '41.38', '300.00');
INSERT INTO `order_items` VALUES ('67', '19', '52', '1', '206.90', '33.10', '240.00');
INSERT INTO `order_items` VALUES ('68', '19', '54', '2', '258.62', '41.38', '300.00');
INSERT INTO `order_items` VALUES ('69', '20', '53', '1', '172.41', '27.59', '200.00');
INSERT INTO `order_items` VALUES ('70', '21', '54', '1', '258.62', '41.38', '300.00');
INSERT INTO `order_items` VALUES ('71', '22', '54', '1', '258.62', '41.38', '300.00');
INSERT INTO `order_items` VALUES ('72', '23', '53', '1', '172.41', '27.59', '200.00');
INSERT INTO `order_items` VALUES ('73', '24', '53', '1', '172.41', '27.59', '200.00');
INSERT INTO `order_items` VALUES ('74', '25', '53', '1', '172.41', '27.59', '200.00');
INSERT INTO `order_items` VALUES ('75', '26', '53', '1', '172.41', '27.59', '200.00');
INSERT INTO `order_items` VALUES ('76', '27', '54', '1', '0.00', '0.00', '300.00');
INSERT INTO `order_items` VALUES ('77', '28', '53', '1', '0.00', '0.00', '200.00');
INSERT INTO `order_items` VALUES ('78', '29', '54', '1', '0.00', '0.00', '300.00');
INSERT INTO `order_items` VALUES ('79', '30', '53', '2', '0.00', '0.00', '200.00');
INSERT INTO `order_items` VALUES ('80', '31', '54', '1', '0.00', '0.00', '300.00');
INSERT INTO `order_items` VALUES ('81', '32', '52', '1', '0.00', '0.00', '240.00');
INSERT INTO `order_items` VALUES ('82', '33', '52', '1', '0.00', '0.00', '240.00');
INSERT INTO `order_items` VALUES ('83', '34', '54', '1', '0.00', '0.00', '300.00');
INSERT INTO `order_items` VALUES ('84', '35', '54', '1', '0.00', '0.00', '300.00');
INSERT INTO `order_items` VALUES ('85', '36', '52', '1', '0.00', '0.00', '240.00');
INSERT INTO `order_items` VALUES ('86', '36', '53', '1', '0.00', '0.00', '200.00');
INSERT INTO `order_items` VALUES ('87', '37', '52', '1', '0.00', '0.00', '240.00');
INSERT INTO `order_items` VALUES ('88', '37', '53', '1', '0.00', '0.00', '200.00');
INSERT INTO `order_items` VALUES ('89', '37', '54', '1', '0.00', '0.00', '300.00');
INSERT INTO `order_items` VALUES ('90', '38', '52', '1', '0.00', '0.00', '240.00');
INSERT INTO `order_items` VALUES ('91', '38', '53', '1', '0.00', '0.00', '200.00');
INSERT INTO `order_items` VALUES ('92', '38', '54', '1', '0.00', '0.00', '300.00');
INSERT INTO `order_items` VALUES ('93', '39', '52', '1', '0.00', '0.00', '240.00');
INSERT INTO `order_items` VALUES ('94', '40', '52', '1', '0.00', '0.00', '240.00');
INSERT INTO `order_items` VALUES ('95', '40', '53', '1', '0.00', '0.00', '200.00');
INSERT INTO `order_items` VALUES ('96', '40', '54', '2', '0.00', '0.00', '300.00');
INSERT INTO `order_items` VALUES ('97', '41', '54', '1', '0.00', '0.00', '300.00');
INSERT INTO `order_items` VALUES ('98', '42', '53', '4', '0.00', '0.00', '200.00');
INSERT INTO `order_items` VALUES ('99', '42', '54', '1', '0.00', '0.00', '300.00');
INSERT INTO `order_items` VALUES ('100', '43', '54', '1', '0.00', '0.00', '300.00');
INSERT INTO `order_items` VALUES ('101', '44', '53', '1', '0.00', '0.00', '200.00');
INSERT INTO `order_items` VALUES ('102', '44', '54', '1', '0.00', '0.00', '300.00');
INSERT INTO `order_items` VALUES ('103', '45', '54', '1', '0.00', '0.00', '300.00');
INSERT INTO `order_items` VALUES ('104', '46', '53', '1', '0.00', '0.00', '200.00');
INSERT INTO `order_items` VALUES ('105', '47', '54', '1', '0.00', '0.00', '300.00');
INSERT INTO `order_items` VALUES ('106', '48', '52', '1', '0.00', '0.00', '240.00');
INSERT INTO `order_items` VALUES ('107', '49', '52', '1', '0.00', '0.00', '240.00');
INSERT INTO `order_items` VALUES ('108', '50', '52', '1', '0.00', '0.00', '240.00');
INSERT INTO `order_items` VALUES ('109', '51', '52', '1', '0.00', '0.00', '240.00');
INSERT INTO `order_items` VALUES ('110', '52', '52', '1', '0.00', '0.00', '240.00');
INSERT INTO `order_items` VALUES ('111', '53', '52', '2', '0.00', '0.00', '240.00');
INSERT INTO `order_items` VALUES ('112', '53', '53', '1', '0.00', '0.00', '200.00');
INSERT INTO `order_items` VALUES ('113', '54', '52', '2', '0.00', '0.00', '240.00');
INSERT INTO `order_items` VALUES ('114', '54', '53', '1', '0.00', '0.00', '200.00');
INSERT INTO `order_items` VALUES ('115', '55', '52', '3', '0.00', '0.00', '240.00');
INSERT INTO `order_items` VALUES ('116', '55', '53', '5', '0.00', '0.00', '200.00');
INSERT INTO `order_items` VALUES ('117', '56', '52', '1', '0.00', '0.00', '240.00');
INSERT INTO `order_items` VALUES ('118', '57', '52', '1', '0.00', '0.00', '240.00');
INSERT INTO `order_items` VALUES ('119', '58', '52', '1', '0.00', '0.00', '240.00');
INSERT INTO `order_items` VALUES ('120', '58', '53', '3', '0.00', '0.00', '200.00');
INSERT INTO `order_items` VALUES ('121', '58', '54', '2', '0.00', '0.00', '300.00');
INSERT INTO `order_items` VALUES ('122', '59', '54', '1', '0.00', '0.00', '300.00');
INSERT INTO `order_items` VALUES ('123', '60', '52', '1', '0.00', '0.00', '240.00');
INSERT INTO `order_items` VALUES ('124', '61', '52', '1', '0.00', '0.00', '240.00');
INSERT INTO `order_items` VALUES ('125', '61', '54', '1', '0.00', '0.00', '300.00');
INSERT INTO `order_items` VALUES ('126', '62', '54', '1', '0.00', '0.00', '300.00');
INSERT INTO `order_items` VALUES ('127', '63', '53', '1', '0.00', '0.00', '200.00');
INSERT INTO `order_items` VALUES ('128', '64', '53', '1', '0.00', '0.00', '200.00');
INSERT INTO `order_items` VALUES ('129', '65', '53', '1', '186.92', '13.08', '200.00');
INSERT INTO `order_items` VALUES ('130', '66', '54', '1', '280.37', '19.63', '300.00');
INSERT INTO `order_items` VALUES ('131', '67', '54', '2', '280.37', '19.63', '300.00');
INSERT INTO `order_items` VALUES ('132', '68', '53', '1', '186.92', '13.08', '200.00');
INSERT INTO `order_items` VALUES ('133', '69', '54', '1', '280.37', '19.63', '300.00');
INSERT INTO `order_items` VALUES ('134', '70', '52', '22', '224.30', '15.70', '240.00');
INSERT INTO `order_items` VALUES ('135', '70', '53', '8', '186.92', '13.08', '200.00');
INSERT INTO `order_items` VALUES ('136', '70', '54', '13', '280.37', '19.63', '300.00');
INSERT INTO `order_items` VALUES ('137', '71', '53', '10', '186.92', '13.08', '200.00');
INSERT INTO `order_items` VALUES ('138', '71', '54', '11', '280.37', '19.63', '300.00');
INSERT INTO `order_items` VALUES ('139', '72', '53', '1', '186.92', '13.08', '200.00');
INSERT INTO `order_items` VALUES ('140', '73', '53', '1', '186.92', '13.08', '200.00');
INSERT INTO `order_items` VALUES ('141', '73', '54', '1', '280.37', '19.63', '300.00');
INSERT INTO `order_items` VALUES ('142', '74', '54', '1', '280.37', '19.63', '300.00');
INSERT INTO `order_items` VALUES ('143', '75', '54', '1', '280.37', '19.63', '300.00');
INSERT INTO `order_items` VALUES ('144', '76', '53', '1', '186.92', '13.08', '200.00');
INSERT INTO `order_items` VALUES ('145', '77', '54', '2', '280.37', '19.63', '300.00');
INSERT INTO `order_items` VALUES ('146', '78', '53', '1', '186.92', '13.08', '200.00');
INSERT INTO `order_items` VALUES ('147', '79', '54', '1', '280.37', '19.63', '300.00');
INSERT INTO `order_items` VALUES ('148', '80', '54', '1', '300.00', '0.00', '300.00');
INSERT INTO `order_items` VALUES ('149', '81', '54', '1', '300.00', '0.00', '300.00');
INSERT INTO `order_items` VALUES ('150', '82', '54', '1', '300.00', '0.00', '300.00');
INSERT INTO `order_items` VALUES ('151', '83', '52', '1', '240.00', '0.00', '240.00');
INSERT INTO `order_items` VALUES ('152', '84', '53', '1', '200.00', '0.00', '200.00');
INSERT INTO `order_items` VALUES ('153', NULL, '53', '1', '200.00', '0.00', '200.00');
INSERT INTO `order_items` VALUES ('154', NULL, '53', '1', '200.00', '0.00', '200.00');
INSERT INTO `order_items` VALUES ('155', NULL, '54', '1', '300.00', '0.00', '300.00');
INSERT INTO `order_items` VALUES ('156', '85', '54', '1', '300.00', '0.00', '300.00');
INSERT INTO `order_items` VALUES ('157', '86', '52', '10', '240.00', '0.00', '240.00');
INSERT INTO `order_items` VALUES ('158', '86', '53', '6', '200.00', '0.00', '200.00');
INSERT INTO `order_items` VALUES ('159', '86', '54', '4', '300.00', '0.00', '300.00');
INSERT INTO `order_items` VALUES ('160', '87', '54', '11', '300.00', '0.00', '300.00');
INSERT INTO `order_items` VALUES ('161', '88', '52', '15', '240.00', '0.00', '240.00');
INSERT INTO `order_items` VALUES ('162', '88', '53', '7', '200.00', '0.00', '200.00');
INSERT INTO `order_items` VALUES ('163', '88', '54', '41', '300.00', '0.00', '300.00');
INSERT INTO `order_items` VALUES ('164', '89', '54', '1', '300.00', '0.00', '300.00');
INSERT INTO `order_items` VALUES ('165', '90', '54', '1', '300.00', '0.00', '300.00');
INSERT INTO `order_items` VALUES ('166', '91', '55', '1', '1500.00', '0.00', '1500.00');
INSERT INTO `order_items` VALUES ('167', '92', '52', '1', '240.00', '0.00', '240.00');
INSERT INTO `order_items` VALUES ('168', '93', '52', '1', '240.00', '0.00', '240.00');
INSERT INTO `order_items` VALUES ('169', '94', '52', '1', '240.00', '0.00', '240.00');
INSERT INTO `order_items` VALUES ('170', '95', '57', '1', '170000.00', '0.00', '170000.00');
INSERT INTO `order_items` VALUES ('171', '96', '55', '10', '1500.00', '0.00', '1500.00');
INSERT INTO `order_items` VALUES ('172', '97', '55', '39', '1500.00', '0.00', '1500.00');
INSERT INTO `order_items` VALUES ('173', '98', '57', '1', '154545.45', '15454.55', '170000.00');
INSERT INTO `order_items` VALUES ('174', '99', '56', '1', '50000.00', '5000.00', '55000.00');
INSERT INTO `order_items` VALUES ('175', '99', '57', '2', '154545.45', '15454.55', '170000.00');
INSERT INTO `order_items` VALUES ('176', '99', '53', '2', '51818.18', '5181.82', '57000.00');
INSERT INTO `order_items` VALUES ('177', '99', '52', '1', '27272.73', '2727.27', '30000.00');
INSERT INTO `order_items` VALUES ('178', '99', '54', '2', '142727.27', '14272.73', '157000.00');
INSERT INTO `order_items` VALUES ('179', '100', '57', '1', '154545.45', '15454.55', '170000.00');
INSERT INTO `order_items` VALUES ('180', '100', '56', '1', '50000.00', '5000.00', '55000.00');
INSERT INTO `order_items` VALUES ('181', '100', '55', '1', '1363.64', '136.36', '1500.00');
INSERT INTO `order_items` VALUES ('182', '100', '54', '1', '142727.27', '14272.73', '157000.00');
INSERT INTO `order_items` VALUES ('183', '100', '53', '1', '51818.18', '5181.82', '57000.00');
INSERT INTO `order_items` VALUES ('184', '100', '52', '1', '27272.73', '2727.27', '30000.00');
INSERT INTO `order_items` VALUES ('185', '101', '56', '1', '50000.00', '5000.00', '55000.00');

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
  `processed_by` varchar(100) DEFAULT 'System Automated Checkout',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=102 DEFAULT CHARSET=latin1;

INSERT INTO `orders` VALUES ('17', '7', 'A051534789G', '2413.79', '386.21', '16.00', '2800.00', 'delivered', '2026-07-23 18:44:02', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('18', '7', 'A001276890V', '637.93', '102.07', '16.00', '740.00', 'delivered', '2026-07-23 23:35:06', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('19', '7', 'A001276890C', '724.14', '115.86', '16.00', '840.00', 'delivered', '2026-07-24 00:02:23', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('20', '7', 'A001276890C', '172.41', '27.59', '16.00', '200.00', 'delivered', '2026-07-24 00:07:45', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('21', '7', 'A001276890C', '258.62', '41.38', '16.00', '300.00', 'delivered', '2026-07-24 00:27:05', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('22', '7', 'A001276890C', '258.62', '41.38', '16.00', '300.00', 'delivered', '2026-07-24 00:37:13', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('23', '7', 'A001276890C', '172.41', '27.59', '16.00', '200.00', 'delivered', '2026-07-24 00:43:33', 'sarah andrew');
INSERT INTO `orders` VALUES ('24', '7', 'A001276890C', '172.41', '27.59', '16.00', '200.00', 'pending', '2026-07-24 01:07:12', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('25', '7', 'A001276890C', '172.41', '27.59', '16.00', '200.00', 'pending', '2026-07-24 01:07:37', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('26', '7', 'A001276890C', '172.41', '27.59', '16.00', '200.00', 'pending', '2026-07-24 01:15:52', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('27', '7', 'A001276890C', '280.37', '19.63', '7.00', '300.00', 'pending', '2026-07-24 01:39:35', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('28', '7', 'A001276890C', '186.92', '13.08', '7.00', '200.00', 'pending', '2026-07-24 01:40:47', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('29', '7', 'A001276890C', '280.37', '19.63', '7.00', '300.00', 'pending', '2026-07-24 01:49:44', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('30', '7', 'A001276890C', '373.83', '26.17', '7.00', '400.00', 'pending', '2026-07-24 01:58:31', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('31', '7', 'A001276890C', '280.37', '19.63', '7.00', '300.00', 'cancelled', '2026-07-24 02:06:19', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('32', '7', 'A001276890C', '224.30', '15.70', '7.00', '240.00', 'cancelled', '2026-07-24 02:08:23', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('33', '7', 'A001276890C', '224.30', '15.70', '7.00', '240.00', 'cancelled', '2026-07-24 02:16:31', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('34', '7', 'A001276890C', '280.37', '19.63', '7.00', '300.00', 'cancelled', '2026-07-24 02:41:29', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('35', '7', 'A001276890C', '280.37', '19.63', '7.00', '0.00', 'cancelled', '2026-07-24 02:42:45', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('36', '7', 'A001276890C', '411.21', '28.79', '7.00', '440.00', 'pending', '2026-07-24 02:43:19', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('37', '7', 'A001276890C', '691.59', '48.41', '7.00', '740.00', 'pending', '2026-07-24 02:46:51', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('38', '7', 'A001276890C', '691.59', '48.41', '7.00', '740.00', 'pending', '2026-07-24 03:39:57', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('39', '7', 'A001276890C', '224.30', '15.70', '7.00', '240.00', 'cancelled', '2026-07-24 13:22:46', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('40', '7', NULL, '0.00', '0.00', '16.00', '0.00', 'cancelled', '2026-07-24 14:42:00', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('41', '7', NULL, '0.00', '0.00', '16.00', '14.00', 'pending', '2026-07-24 14:44:04', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('42', '7', NULL, '0.00', '0.00', '16.00', '800.00', 'cancelled', '2026-07-24 15:17:20', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('43', '7', NULL, '0.00', '0.00', '16.00', '300.00', 'pending', '2026-07-24 15:18:20', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('44', '7', NULL, '0.00', '0.00', '16.00', '500.00', 'pending', '2026-07-24 15:28:59', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('45', '7', NULL, '0.00', '0.00', '16.00', '300.00', 'pending', '2026-07-24 15:40:47', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('46', '7', NULL, '0.00', '0.00', '16.00', '200.00', 'pending', '2026-07-24 15:53:58', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('47', '7', NULL, '0.00', '0.00', '16.00', '300.00', 'pending', '2026-07-24 15:58:30', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('48', '7', NULL, '0.00', '0.00', '16.00', '240.00', 'pending', '2026-07-24 16:01:58', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('49', '7', NULL, '0.00', '0.00', '16.00', '240.00', 'pending', '2026-07-24 16:04:53', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('50', '7', NULL, '0.00', '0.00', '16.00', '240.00', 'pending', '2026-07-24 16:07:03', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('51', '7', NULL, '0.00', '0.00', '16.00', '240.00', 'pending', '2026-07-24 18:25:25', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('52', '7', NULL, '0.00', '0.00', '16.00', '0.00', 'processing', '2026-07-24 18:26:23', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('53', '7', NULL, '680.00', '47.60', '7.00', '727.60', 'pending', '2026-07-24 21:56:35', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('54', '7', NULL, '680.00', '47.60', '7.00', '727.60', 'pending', '2026-07-24 22:06:11', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('55', '7', NULL, '1720.00', '120.40', '7.00', '1840.40', 'pending', '2026-07-24 22:10:25', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('56', '7', NULL, '240.00', '16.80', '7.00', '256.80', 'processing', '2026-07-24 22:15:58', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('57', '7', NULL, '240.00', '16.80', '7.00', '256.80', 'processing', '2026-07-24 22:26:53', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('58', '7', NULL, '1440.00', '100.80', '7.00', '1540.80', 'pending', '2026-07-24 22:34:04', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('59', '7', NULL, '300.00', '21.00', '7.00', '321.00', 'pending', '2026-07-24 22:34:38', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('60', '7', NULL, '240.00', '16.80', '7.00', '256.80', 'processing', '2026-07-24 22:45:13', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('61', '7', NULL, '540.00', '37.80', '7.00', '577.80', 'pending', '2026-07-24 22:51:04', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('62', '7', NULL, '300.00', '21.00', '7.00', '321.00', 'pending', '2026-07-24 22:55:51', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('63', '7', NULL, '200.00', '14.00', '7.00', '214.00', 'pending', '2026-07-24 23:04:00', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('64', '7', NULL, '200.00', '14.00', '7.00', '214.00', 'processing', '2026-07-24 23:11:41', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('65', '7', 'A001276890C', '186.92', '13.08', '7.00', '200.00', 'paid', '2026-07-25 17:58:30', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('66', '7', 'A001276890C', '280.37', '19.63', '7.00', '300.00', 'pending', '2026-07-25 18:02:36', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('67', '7', 'A001276890C', '560.75', '39.25', '7.00', '600.00', 'pending', '2026-07-25 18:21:43', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('68', '7', 'A001276890C', '186.92', '13.08', '7.00', '200.00', 'delivered', '2026-07-25 20:15:15', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('69', '7', 'A001276890C', '280.37', '19.63', '7.00', '300.00', 'delivered', '2026-07-25 20:21:03', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('70', '7', 'A001276890C', '10074.77', '705.23', '7.00', '10780.00', 'pending', '2026-07-25 20:50:22', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('71', '7', 'A001276890C', '4953.27', '346.73', '7.00', '5300.00', 'delivered', '2026-07-25 21:22:56', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('72', '7', 'A001276890C', '186.92', '13.08', '7.00', '200.00', 'delivered', '2026-07-25 21:26:04', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('73', '11', 'A001276890C', '467.29', '32.71', '7.00', '500.00', 'pending', '2026-07-26 18:14:59', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('74', '11', 'A001276890C', '280.37', '19.63', '7.00', '300.00', 'delivered', '2026-07-26 18:31:34', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('75', '11', 'A001276890C', '280.37', '19.63', '7.00', '300.00', 'delivered', '2026-07-26 20:36:52', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('76', '11', 'A001276890C', '186.92', '13.08', '7.00', '200.00', 'delivered', '2026-07-26 20:56:40', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('77', '11', 'A001276890C', '560.75', '39.25', '7.00', '600.00', 'delivered', '2026-07-26 21:30:16', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('78', '11', 'A001276890C', '186.92', '13.08', '7.00', '200.00', 'delivered', '2026-07-26 21:36:45', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('79', '11', 'A001276890C', '280.37', '19.63', '7.00', '300.00', 'delivered', '2026-07-26 21:41:55', 'sarah andrew');
INSERT INTO `orders` VALUES ('80', '7', 'A051534789G', '300.00', '0.00', '0.00', '300.00', 'delivered', '2026-07-26 22:27:51', 'sarah andrew');
INSERT INTO `orders` VALUES ('81', '7', 'A051534789G', '300.00', '0.00', '0.00', '300.00', 'delivered', '2026-07-26 22:28:51', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('82', '7', 'A051534789G', '300.00', '0.00', '0.00', '300.00', 'delivered', '2026-07-26 23:37:48', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('83', '7', 'A051534789G', '240.00', '0.00', '0.00', '240.00', 'delivered', '2026-07-27 10:24:23', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('84', '7', 'A051534789G', '200.00', '0.00', '0.00', '200.00', 'delivered', '2026-07-27 10:25:14', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('85', '7', 'A051534789G', '300.00', '0.00', '0.00', '300.00', 'pending', '2026-07-27 11:21:46', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('86', '10', 'A003476452Q', '4800.00', '0.00', '0.00', '4800.00', 'pending', '2026-07-27 13:23:35', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('87', '10', 'A003476452Q', '3300.00', '0.00', '0.00', '3300.00', 'pending', '2026-07-27 13:33:02', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('88', '10', 'A003476452Q', '17300.00', '0.00', '0.00', '17300.00', 'delivered', '2026-07-27 23:30:34', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('89', '10', 'A003476452Q', '300.00', '0.00', '0.00', '300.00', 'pending', '2026-07-28 01:24:41', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('90', '10', 'A003476452Q', '300.00', '0.00', '0.00', '300.00', 'delivered', '2026-07-28 01:25:35', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('91', '12', 'A001276890C', '1500.00', '0.00', '0.00', '1500.00', 'delivered', '2026-07-30 10:48:51', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('92', '12', 'A001276890C', '240.00', '0.00', '0.00', '240.00', 'delivered', '2026-08-02 01:33:05', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('93', '12', 'A001276890C', '240.00', '0.00', '0.00', '240.00', 'delivered', '2026-08-02 01:46:51', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('94', '12', 'A001276890C', '240.00', '0.00', '0.00', '240.00', 'cancelled', '2026-08-02 02:09:07', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('95', '7', 'A051534789G', '170000.00', '0.00', '0.00', '170000.00', 'delivered', '2026-08-07 01:39:48', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('96', '7', 'A051534789G', '15000.00', '0.00', '0.00', '15000.00', 'delivered', '2026-08-07 19:47:28', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('97', '7', 'A051534789G', '58500.00', '0.00', '0.00', '58500.00', 'delivered', '2026-08-07 19:49:23', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('98', '7', 'A051534789G', '154545.45', '15454.55', '10.00', '170000.00', 'delivered', '2026-08-07 19:59:31', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('99', '15', 'A001276890C', '775454.55', '77545.45', '10.00', '853000.00', 'delivered', '2026-08-11 02:57:23', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('100', '15', 'A001276890C', '427727.27', '42772.73', '10.00', '470500.00', 'processing', '2026-08-11 12:23:23', 'System Automated Checkout');
INSERT INTO `orders` VALUES ('101', '15', 'A001276890C', '50000.00', '5000.00', '10.00', '55000.00', 'processing', '2026-08-11 13:02:23', 'Sarah Andrew');

DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `transaction_code` varchar(100) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `payment_status` enum('pending','completed','failed','refunded') DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=103 DEFAULT CHARSET=latin1;

INSERT INTO `payments` VALUES ('37', NULL, 'Manual Admin Adjustment', 'MAN_1784813698_579d', '40.00', 'completed', '2026-07-23 16:34:58');
INSERT INTO `payments` VALUES ('38', NULL, 'Manual Admin Adjustment', 'MAN_1784813706_7701', '40.00', 'completed', '2026-07-23 16:35:06');
INSERT INTO `payments` VALUES ('39', '17', 'M-Pesa', 'TXN_B2F64EF269', '2800.00', 'completed', '2026-07-23 18:44:02');
INSERT INTO `payments` VALUES ('40', '18', 'M-Pesa', 'TXN_ABFDD05331', '740.00', 'completed', '2026-07-23 23:35:06');
INSERT INTO `payments` VALUES ('41', '19', 'M-Pesa', 'TXN_9BD48F595A', '840.00', 'completed', '2026-07-24 00:02:23');
INSERT INTO `payments` VALUES ('42', '20', 'M-Pesa', 'TXN_AF3910E83F', '200.00', 'completed', '2026-07-24 00:07:45');
INSERT INTO `payments` VALUES ('43', '21', 'M-Pesa', 'TXN_CFD41D6686', '300.00', 'completed', '2026-07-24 00:27:05');
INSERT INTO `payments` VALUES ('44', '22', 'M-Pesa', 'TXN_7D55D0A121', '300.00', 'completed', '2026-07-24 00:37:13');
INSERT INTO `payments` VALUES ('45', '23', 'M-Pesa', 'TXN_78A99C2AD0', '200.00', 'completed', '2026-07-24 00:43:33');
INSERT INTO `payments` VALUES ('46', '24', 'M-Pesa', 'TXN_F5DC7F548B', '200.00', 'completed', '2026-07-24 01:07:12');
INSERT INTO `payments` VALUES ('47', '25', 'M-Pesa', 'TXN_8730A4E870', '200.00', 'completed', '2026-07-24 01:07:37');
INSERT INTO `payments` VALUES ('48', '26', 'M-Pesa', 'TXN_92B57AA97C', '200.00', 'completed', '2026-07-24 01:15:52');
INSERT INTO `payments` VALUES ('49', '35', 'Manual Store Credit Refund', 'REF_1784852216_7b60a5', '300.00', 'completed', '2026-07-24 03:16:56');
INSERT INTO `payments` VALUES ('50', '66', 'Lipa Pole Pole', 'TXN_6A64D00C5FBD4', '0.00', 'pending', '2026-07-25 18:02:36');
INSERT INTO `payments` VALUES ('51', NULL, 'M-Pesa Deposit', 'TXN_DEP_BF1497', '7800.00', 'completed', '2026-07-25 18:19:14');
INSERT INTO `payments` VALUES ('52', '67', 'Lipa Pole Pole', 'TXN_6A64D487C5C15', '0.00', 'pending', '2026-07-25 18:21:43');
INSERT INTO `payments` VALUES ('53', '67', 'Lipa Pole Pole (Wallet)', 'TXN_POLE_W_7FD611', '300.00', 'completed', '2026-07-25 18:33:43');
INSERT INTO `payments` VALUES ('54', '68', 'M-Pesa', 'TXN_774FFD7BA', '200.00', 'completed', '2026-07-25 20:15:15');
INSERT INTO `payments` VALUES ('55', '69', 'Lipa Pole Pole', 'TXN_6A64F07F0D244', '0.00', 'pending', '2026-07-25 20:21:03');
INSERT INTO `payments` VALUES ('56', '69', 'Lipa Pole Pole (Wallet)', 'TXN_POLE_W_374FC9', '300.00', 'completed', '2026-07-25 20:25:43');
INSERT INTO `payments` VALUES ('57', '70', 'Lipa Pole Pole (Initial Deposit)', '0', '5390.00', 'completed', '2026-07-25 20:50:22');
INSERT INTO `payments` VALUES ('58', '71', 'M-Pesa', '0', '5300.00', 'completed', '2026-07-25 21:22:56');
INSERT INTO `payments` VALUES ('59', '72', 'M-Pesa', '0', '200.00', 'completed', '2026-07-25 21:26:04');
INSERT INTO `payments` VALUES ('60', '73', 'Lipa Pole Pole (Initial Deposit)', '0', '250.00', 'completed', '2026-07-26 18:14:59');
INSERT INTO `payments` VALUES ('61', '74', 'M-Pesa', '0', '300.00', 'completed', '2026-07-26 18:31:34');
INSERT INTO `payments` VALUES ('62', '75', 'M-Pesa', '0', '300.00', 'completed', '2026-07-26 20:36:52');
INSERT INTO `payments` VALUES ('63', '76', 'M-Pesa', '0', '200.00', 'completed', '2026-07-26 20:56:40');
INSERT INTO `payments` VALUES ('64', '77', 'M-Pesa', '0', '600.00', 'completed', '2026-07-26 21:30:16');
INSERT INTO `payments` VALUES ('65', '78', 'M-Pesa', '0', '200.00', 'completed', '2026-07-26 21:36:45');
INSERT INTO `payments` VALUES ('66', '79', 'M-Pesa', '0', '300.00', 'completed', '2026-07-26 21:41:55');
INSERT INTO `payments` VALUES ('67', '80', 'M-Pesa', '0', '300.00', 'completed', '2026-07-26 22:27:51');
INSERT INTO `payments` VALUES ('68', '81', 'M-Pesa', '0', '300.00', 'completed', '2026-07-26 22:28:51');
INSERT INTO `payments` VALUES ('69', '82', 'M-Pesa', '0', '300.00', 'completed', '2026-07-26 23:37:48');
INSERT INTO `payments` VALUES ('70', '83', 'M-Pesa', '0', '240.00', 'completed', '2026-07-27 10:24:23');
INSERT INTO `payments` VALUES ('71', '84', 'Lipa Pole Pole (Initial Deposit)', '0', '100.00', 'completed', '2026-07-27 10:25:14');
INSERT INTO `payments` VALUES ('72', NULL, 'M-Pesa', '0', '200.00', 'completed', '2026-07-27 11:02:27');
INSERT INTO `payments` VALUES ('73', NULL, 'M-Pesa', '0', '500.00', 'completed', '2026-07-27 11:03:16');
INSERT INTO `payments` VALUES ('74', '85', 'M-Pesa', '0', '300.00', 'completed', '2026-07-27 11:21:46');
INSERT INTO `payments` VALUES ('75', '84', 'Lipa Pole Pole (M-Pesa)', 'TXN_POLE_M_508488', '100.00', 'completed', '2026-07-27 11:24:27');
INSERT INTO `payments` VALUES ('76', '58', 'Lipa Pole Pole (Wallet)', 'TXN_POLE_W_525689', '770.40', 'completed', '2026-07-27 11:24:54');
INSERT INTO `payments` VALUES ('77', '86', 'M-Pesa', '0', '4800.00', 'completed', '2026-07-27 13:23:35');
INSERT INTO `payments` VALUES ('78', '87', 'M-Pesa', '0', '3300.00', 'completed', '2026-07-27 13:33:02');
INSERT INTO `payments` VALUES ('79', '88', 'M-Pesa', '0', '17300.00', 'completed', '2026-07-27 23:30:34');
INSERT INTO `payments` VALUES ('80', NULL, 'M-Pesa Deposit', 'TXN_DEP_D84145', '500.00', 'completed', '2026-07-28 00:17:03');
INSERT INTO `payments` VALUES ('81', NULL, 'M-Pesa Deposit', 'TXN_DEP_EB832D', '500.00', 'completed', '2026-07-28 00:18:07');
INSERT INTO `payments` VALUES ('82', NULL, 'M-Pesa Deposit', 'TXN_DEP_EE892F', '200.00', 'completed', '2026-07-28 00:18:18');
INSERT INTO `payments` VALUES ('83', NULL, 'M-Pesa Deposit', 'TXN_DEP_652BC1', '500.00', 'completed', '2026-07-28 00:18:27');
INSERT INTO `payments` VALUES ('84', NULL, 'M-Pesa Deposit', 'TXN_DEP_86978A', '500.00', 'completed', '2026-07-28 01:24:07');
INSERT INTO `payments` VALUES ('85', NULL, 'M-Pesa Deposit', 'TXN_DEP_12D534', '100.00', 'completed', '2026-07-28 01:24:17');
INSERT INTO `payments` VALUES ('86', '89', 'Lipa Pole Pole', '0', '150.00', 'completed', '2026-07-28 01:24:41');
INSERT INTO `payments` VALUES ('87', '90', 'M-Pesa', '0', '300.00', 'completed', '2026-07-28 01:25:35');
INSERT INTO `payments` VALUES ('88', '91', 'M-Pesa', '0', '1500.00', 'completed', '2026-07-30 10:48:51');
INSERT INTO `payments` VALUES ('89', '92', 'Customer Wallet', '0', '240.00', 'completed', '2026-08-02 01:33:05');
INSERT INTO `payments` VALUES ('90', '93', 'M-Pesa', '0', '240.00', 'completed', '2026-08-02 01:46:51');
INSERT INTO `payments` VALUES ('91', '94', 'Customer Wallet', '0', '240.00', 'refunded', '2026-08-02 02:09:07');
INSERT INTO `payments` VALUES ('92', '95', 'M-Pesa', '0', '170000.00', 'completed', '2026-08-07 01:39:48');
INSERT INTO `payments` VALUES ('93', '96', 'M-Pesa', '0', '15000.00', 'completed', '2026-08-07 19:47:28');
INSERT INTO `payments` VALUES ('94', '97', 'M-Pesa', '0', '58500.00', 'completed', '2026-08-07 19:49:23');
INSERT INTO `payments` VALUES ('95', '98', 'M-Pesa', '0', '170000.00', 'completed', '2026-08-07 19:59:31');
INSERT INTO `payments` VALUES ('96', '99', 'M-Pesa', '0', '853000.00', 'completed', '2026-08-11 02:57:23');
INSERT INTO `payments` VALUES ('97', NULL, 'M-Pesa Deposit', 'TXN_DEP_34069A', '1000000.00', 'completed', '2026-08-11 11:57:42');
INSERT INTO `payments` VALUES ('98', NULL, 'M-Pesa Deposit', 'TXN_DEP_680816', '700000.00', 'completed', '2026-08-11 11:58:39');
INSERT INTO `payments` VALUES ('99', NULL, 'M-Pesa Deposit', 'TXN_DEP_U15_96BF8A9A', '800000.00', 'completed', '2026-08-11 12:22:51');
INSERT INTO `payments` VALUES ('100', '100', 'Lipa Pole Pole', 'TXN_6A7AEA0BD8FF2', '235250.00', 'completed', '2026-08-11 12:23:23');
INSERT INTO `payments` VALUES ('101', '101', 'Customer Wallet', 'TXN_6A7AF32F14182', '55000.00', 'completed', '2026-08-11 13:02:23');
INSERT INTO `payments` VALUES ('102', '100', 'Lipa Pole Pole (Wallet)', 'TXN_POLE_W_ED14BB', '7000.00', 'completed', '2026-08-11 13:29:56');

DROP TABLE IF EXISTS `polepole_reminders`;
CREATE TABLE `polepole_reminders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `plan_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `reminder_date` date NOT NULL,
  `reminder_slot` enum('09:00','13:00','18:00') NOT NULL,
  `channel` varchar(20) NOT NULL DEFAULT 'in_app',
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_plan_slot` (`plan_id`,`reminder_date`,`reminder_slot`),
  KEY `user_lookup` (`user_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


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
  UNIQUE KEY `uq_product_reviews_user_product` (`user_id`,`product_id`),
  UNIQUE KEY `unique_customer_product_review` (`user_id`,`product_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `product_reviews_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `product_reviews_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=latin1;

INSERT INTO `product_reviews` VALUES ('3', '7', '54', 'james kimani', '4', 'good product', '1', '2026-07-27 10:33:49');
INSERT INTO `product_reviews` VALUES ('4', '7', '53', 'james kimani', '5', 'good', '1', '2026-07-27 10:41:03');
INSERT INTO `product_reviews` VALUES ('5', '7', '52', 'james kimani', '4', 'great experience', '1', '2026-07-27 10:56:10');
INSERT INTO `product_reviews` VALUES ('8', '10', '52', 'peter otieno', '5', 'goood', '1', '2026-07-28 00:25:34');
INSERT INTO `product_reviews` VALUES ('10', '10', '54', 'peter otieno', '5', 'wow', '1', '2026-07-28 01:35:36');
INSERT INTO `product_reviews` VALUES ('11', '12', '52', 'john allan', '5', 'very good product', '1', '2026-08-02 02:10:12');
INSERT INTO `product_reviews` VALUES ('12', '7', '57', 'james kimani', '5', 'great', '1', '2026-08-10 22:47:34');
INSERT INTO `product_reviews` VALUES ('14', '7', '55', 'james kimani', '5', 'high quality covers from adonak', '1', '2026-08-10 23:09:17');

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
) ENGINE=InnoDB AUTO_INCREMENT=59 DEFAULT CHARSET=latin1;

INSERT INTO `products` VALUES ('52', '21', '25', 'mika.infor@gmail.com', '190L MIKE FRIDGE', 'FREE-01', '30000.00', '13', '1784833532_âœ… HISENSE RD27 (205L) ðŸ“¦ Friji ya kisasa yenyeâ€¦.jpg', 'high quality products, 1 year warrant.', '2026-07-23 22:05:32');
INSERT INTO `products` VALUES ('53', '20', '21', 'tecno@gmail.com', 'TECNO SPARK 20', 'SPAR20', '57000.00', '27', '1784834408_561331541074925554.jpg', 'great technology invested in this device, one year warrant', '2026-07-23 22:20:08');
INSERT INTO `products` VALUES ('54', '20', '20', 'SAMSUNG@GMAIL.COM', 'SUMSANG S26 ULTRA', 'SAMSUNGS26', '157000.00', '4', '1784834661_Samsung Galaxy S26 Ultra Case with Card Holder.jpg', '', '2026-07-23 22:24:21');
INSERT INTO `products` VALUES ('55', '18', '20', 'SAMSUNG@GMAIL.COM', 'SUMSANG S26 ULTRA COVER', 'SU26', '1500.00', '9', '1785328125_43136108927701024 (1).jpg', 'QUALITY COVER FOR ULTIMATE PHONE', '2026-07-29 15:28:45');
INSERT INTO `products` VALUES ('56', '17', '20', 'sumsang@info.ke.co', 'sumsan laptop', 'SAM-LP23', '55000.00', '17', '1785786352_Samsung Crome Laptop! Like new used less than 10 times!.jpg', 'high quality laptops', '2026-08-03 22:45:52');
INSERT INTO `products` VALUES ('57', '20', '22', 'VIVO@GMAIL.COM', 'VIVO X300', 'X300', '170000.00', '41', '1785786987_Vivo X300 Ultra 5G Cell Phone ZEISS Camera Snapdragon 8 Elite Gen5.jpg', '', '2026-08-03 22:56:27');
INSERT INTO `products` VALUES ('58', '20', '22', 'VIVO@GMAIL.COM', 'VIVO X200', 'X200', '160000.00', '20', '1786444629_vivo X200 Pro 5G 6_78_ 200MP 256_ 512GB 1TB Dimensity 9400 Android 15 CN version Opens in a new wind.jpg', 'BEST QUALITY SMART PHONE FROM VIVO INDUSTRIES,\r\nONE YEAR WARRANT.\r\nBEST CAMERA', '2026-08-11 13:37:09');

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


DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_name` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_name` (`role_name`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=latin1;

INSERT INTO `roles` VALUES ('1', 'admin', '2026-08-04 21:38:25');
INSERT INTO `roles` VALUES ('2', 'staff', '2026-08-04 21:38:25');
INSERT INTO `roles` VALUES ('3', 'customer', '2026-08-04 21:38:25');
INSERT INTO `roles` VALUES ('4', 'cashier', '2026-08-04 23:26:30');
INSERT INTO `roles` VALUES ('5', 'auditor', '2026-08-05 00:12:41');
INSERT INTO `roles` VALUES ('6', 'super_admin', '2026-08-05 00:23:08');
INSERT INTO `roles` VALUES ('7', 'supervissoor', '2026-08-05 00:55:27');
INSERT INTO `roles` VALUES ('8', 'cleaner', '2026-08-05 00:59:42');

DROP TABLE IF EXISTS `staff_attendance`;
CREATE TABLE `staff_attendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `staff_name` varchar(100) NOT NULL,
  `clock_in_time` datetime NOT NULL,
  `clock_out_time` datetime DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `shift_status` enum('Active','Completed','Force Closed') NOT NULL DEFAULT 'Active',
  `shift_type` varchar(50) DEFAULT 'regular',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `staff_attendance_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=latin1;

INSERT INTO `staff_attendance` VALUES ('1', '6', 'System Administrator', '2026-07-26 17:43:16', NULL, '::1', 'Force Closed', 'regular');
INSERT INTO `staff_attendance` VALUES ('2', '6', 'System Administrator', '2026-07-26 17:43:40', NULL, '::1', 'Force Closed', 'regular');
INSERT INTO `staff_attendance` VALUES ('3', '6', 'sarah andrew', '2026-08-03 21:58:15', NULL, '::1', 'Force Closed', 'regular');
INSERT INTO `staff_attendance` VALUES ('4', '6', 'sarah andrew', '2026-08-04 21:28:13', NULL, '::1', 'Force Closed', 'regular');
INSERT INTO `staff_attendance` VALUES ('5', '6', 'Sarah Andrew', '2026-08-06 16:50:21', NULL, '::1', 'Force Closed', 'regular');
INSERT INTO `staff_attendance` VALUES ('6', '12', 'john allan', '2026-08-07 01:29:57', NULL, '::1', 'Force Closed', 'regular');
INSERT INTO `staff_attendance` VALUES ('7', '10', 'peter otieno', '2026-08-07 02:18:15', NULL, '::1', 'Force Closed', 'regular');
INSERT INTO `staff_attendance` VALUES ('8', '6', 'Sarah Andrew', '2026-08-07 13:33:58', NULL, '::1', 'Force Closed', 'short_coverage');
INSERT INTO `staff_attendance` VALUES ('9', '6', 'Sarah Andrew', '2026-08-07 13:38:37', NULL, '::1', 'Force Closed', 'short_coverage');
INSERT INTO `staff_attendance` VALUES ('12', '10', 'peter otieno', '2026-08-09 21:01:14', NULL, '::1', 'Force Closed', 'night');
INSERT INTO `staff_attendance` VALUES ('13', '6', 'Sarah Andrew', '2026-08-10 21:24:15', NULL, '::1', 'Force Closed', 'short_coverage');
INSERT INTO `staff_attendance` VALUES ('14', '10', 'peter otieno', '2026-08-11 00:44:25', '2026-08-11 01:00:59', '::1', 'Completed', 'short_coverage');
INSERT INTO `staff_attendance` VALUES ('15', '6', 'Sarah Andrew', '2026-08-11 01:12:57', '2026-08-11 01:17:49', '::1', 'Completed', 'short_coverage');

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
) ENGINE=InnoDB AUTO_INCREMENT=290 DEFAULT CHARSET=latin1;

INSERT INTO `staff_logs` VALUES ('63', '5', 'Maxies John', 'Inventory Update', NULL, 'Procurement Reorder Dispatched: Generated formal inventory replenishment notification for model [TCL SMART TV 55\'] to agent email [TCL@IINFO.KE.CO] asking for +21 turnover items.', '2026-07-23 14:43:55');
INSERT INTO `staff_logs` VALUES ('64', '5', 'Maxies John', 'Staff Login', NULL, 'Secure entry validated via IP: ::1', '2026-07-23 22:02:55');
INSERT INTO `staff_logs` VALUES ('65', '5', 'Maxies John', 'Staff Login', NULL, 'Secure entry validated via IP: ::1', '2026-07-24 22:13:20');
INSERT INTO `staff_logs` VALUES ('66', '5', 'Maxies John', 'Staff Login', NULL, 'Secure entry validated via IP: ::1', '2026-07-25 20:51:24');
INSERT INTO `staff_logs` VALUES ('67', '6', 'sarah andrew', 'Staff Login', NULL, 'Secure staff desk entry validated via IP: ::1', '2026-07-25 22:23:03');
INSERT INTO `staff_logs` VALUES ('68', '6', 'sarah andrew', 'Staff Login', NULL, 'Secure staff desk entry validated via IP: ::1', '2026-07-26 16:55:56');
INSERT INTO `staff_logs` VALUES ('69', '6', 'sarah andrew', 'Staff Login', NULL, 'Secure staff desk entry validated via IP: ::1', '2026-07-26 17:14:24');
INSERT INTO `staff_logs` VALUES ('70', '6', 'sarah andrew', 'Staff Login', NULL, 'Secure staff desk entry validated via IP: ::1', '2026-07-26 17:15:37');
INSERT INTO `staff_logs` VALUES ('71', '6', 'sarah andrew', 'Staff Login', NULL, 'Secure staff desk entry validated via IP: ::1', '2026-07-26 17:17:18');
INSERT INTO `staff_logs` VALUES ('72', '6', 'sarah andrew', 'Staff Login', NULL, 'Secure staff desk entry validated via IP: ::1', '2026-07-26 17:17:53');
INSERT INTO `staff_logs` VALUES ('73', '6', 'sarah andrew', 'Staff Login', NULL, 'Secure staff desk entry validated via IP: ::1', '2026-07-26 17:33:16');
INSERT INTO `staff_logs` VALUES ('74', '6', 'sarah andrew', 'Staff Login', NULL, 'Secure staff desk entry validated via IP: ::1', '2026-07-26 17:48:50');
INSERT INTO `staff_logs` VALUES ('75', '6', 'sarah andrew', 'Staff Login', NULL, 'Secure staff desk entry validated via IP: ::1', '2026-07-26 17:54:45');
INSERT INTO `staff_logs` VALUES ('76', '6', 'sarah andrew', 'Staff Login', NULL, 'Secure staff desk entry validated via IP: ::1', '2026-07-26 21:48:00');
INSERT INTO `staff_logs` VALUES ('77', '6', 'sarah andrew', 'Staff Login', NULL, 'Secure staff desk entry validated via IP: ::1', '2026-07-26 21:49:48');
INSERT INTO `staff_logs` VALUES ('78', '6', 'sarah andrew', 'Staff Login', NULL, 'Secure staff desk entry validated via IP: ::1', '2026-07-26 22:18:42');
INSERT INTO `staff_logs` VALUES ('79', '6', 'sarah andrew', 'Staff Login', NULL, 'Secure staff desk entry validated via IP: ::1', '2026-07-26 22:19:02');
INSERT INTO `staff_logs` VALUES ('80', '6', 'sarah andrew', 'Staff Login', NULL, 'Secure staff desk entry validated via IP: ::1', '2026-07-26 22:20:37');
INSERT INTO `staff_logs` VALUES ('81', '6', 'sarah andrew', 'Staff Login', NULL, 'Secure staff desk entry validated via IP: ::1', '2026-07-26 22:21:12');
INSERT INTO `staff_logs` VALUES ('82', '5', 'Maxies John', 'Staff Login', NULL, 'Secure entry validated via IP: ::1', '2026-07-26 22:23:37');
INSERT INTO `staff_logs` VALUES ('83', '5', 'Maxies John', 'Staff Login', NULL, 'Secure entry validated via IP: ::1', '2026-07-26 22:25:31');
INSERT INTO `staff_logs` VALUES ('84', '6', 'sarah andrew', 'Staff Login', NULL, 'Secure staff desk entry validated via IP: ::1', '2026-07-26 23:43:41');
INSERT INTO `staff_logs` VALUES ('85', '5', 'Maxies John', 'Staff Login', NULL, 'Secure entry validated via IP: ::1', '2026-07-27 10:32:45');
INSERT INTO `staff_logs` VALUES ('86', '5', 'Maxies John', 'Staff Login', NULL, 'Secure entry validated via IP: ::1', '2026-07-27 12:33:55');
INSERT INTO `staff_logs` VALUES ('87', '5', 'Maxies John', 'Staff Login', NULL, 'Secure entry validated via IP: ::1', '2026-07-27 22:54:34');
INSERT INTO `staff_logs` VALUES ('88', '5', 'Maxies John', 'Staff Login', NULL, 'Secure entry validated via IP: ::1', '2026-07-27 23:01:54');
INSERT INTO `staff_logs` VALUES ('89', '5', 'Maxies John', 'Staff Login', NULL, 'Secure entry validated via IP: ::1', '2026-07-29 12:36:29');
INSERT INTO `staff_logs` VALUES ('90', '5', 'Maxies John', '', NULL, 'Secure entry session terminated via IP: ::1', '2026-07-29 16:11:08');
INSERT INTO `staff_logs` VALUES ('91', '5', 'Maxies John', 'Staff Login', NULL, 'Secure entry validated via IP: ::1', '2026-07-30 16:07:15');
INSERT INTO `staff_logs` VALUES ('92', '5', 'Maxies John', '', NULL, 'Secure entry session terminated via IP: ::1', '2026-08-01 12:50:08');
INSERT INTO `staff_logs` VALUES ('93', '5', 'Maxies John', 'Staff Login', NULL, 'Secure entry validated via IP: ::1', '2026-08-01 22:09:48');
INSERT INTO `staff_logs` VALUES ('94', '5', 'Maxies John', '', NULL, 'Secure entry session terminated via IP: ::1', '2026-08-01 22:11:11');
INSERT INTO `staff_logs` VALUES ('95', '5', 'Maxies John', 'Staff Login', NULL, 'Secure entry validated via IP: ::1', '2026-08-01 22:13:27');
INSERT INTO `staff_logs` VALUES ('96', '5', 'Maxies John', '', NULL, 'Secure entry session terminated via IP: ::1', '2026-08-01 22:13:38');
INSERT INTO `staff_logs` VALUES ('97', '5', 'Maxies John', 'Staff Login', NULL, 'Secure entry validated via IP: ::1', '2026-08-01 22:20:05');
INSERT INTO `staff_logs` VALUES ('98', '5', 'Maxies John', 'Staff Login', NULL, 'Secure entry validated via IP: 127.0.0.1', '2026-08-02 02:10:52');
INSERT INTO `staff_logs` VALUES ('99', '5', 'Maxies John', 'Staff Login', NULL, 'MFA Complete: Secure admin entry validated via IP ::1', '2026-08-02 21:21:15');
INSERT INTO `staff_logs` VALUES ('100', '5', 'Maxies John', '', NULL, 'Secure entry session terminated via IP: ::1', '2026-08-02 21:28:31');
INSERT INTO `staff_logs` VALUES ('101', '5', 'Maxies John', 'Staff Login', NULL, 'Secure entry validated via IP: ::1', '2026-08-02 22:50:22');
INSERT INTO `staff_logs` VALUES ('102', '5', 'Maxies John', 'Staff Login', NULL, 'Secure entry validated via IP: ::1', '2026-08-02 22:50:41');
INSERT INTO `staff_logs` VALUES ('103', '5', 'Maxies John', '', NULL, 'Secure entry session terminated via IP: ::1', '2026-08-03 17:16:08');
INSERT INTO `staff_logs` VALUES ('104', '5', 'Maxies John', 'Staff Login', NULL, 'Secure entry validated via IP: ::1', '2026-08-03 17:16:18');
INSERT INTO `staff_logs` VALUES ('105', '5', 'Maxies John', '', NULL, 'Secure entry session terminated via IP: ::1', '2026-08-03 17:34:23');
INSERT INTO `staff_logs` VALUES ('106', '5', 'Maxies John', 'Staff Login', NULL, 'Secure entry validated via IP: ::1', '2026-08-03 17:35:17');
INSERT INTO `staff_logs` VALUES ('107', '5', 'Maxies John', 'Staff Login', NULL, 'Secure entry validated via IP: ::1', '2026-08-03 18:02:28');
INSERT INTO `staff_logs` VALUES ('108', '5', 'Maxies John', 'Staff Login', NULL, 'Secure entry validated via IP: ::1', '2026-08-03 20:03:07');
INSERT INTO `staff_logs` VALUES ('109', '5', 'Maxies John', '', NULL, 'Secure entry session terminated via IP: ::1', '2026-08-03 21:24:43');
INSERT INTO `staff_logs` VALUES ('110', '5', 'Maxies John', 'Staff Login', NULL, 'Secure entry validated via IP: ::1', '2026-08-03 21:24:57');
INSERT INTO `staff_logs` VALUES ('111', '5', 'Maxies John', '', NULL, 'Secure entry session terminated via IP: ::1', '2026-08-03 21:57:46');
INSERT INTO `staff_logs` VALUES ('112', '6', 'sarah andrew', 'Staff Login', NULL, 'Secure staff desk entry validated via IP: ::1', '2026-08-03 21:58:01');
INSERT INTO `staff_logs` VALUES ('113', '6', 'sarah andrew', '', NULL, 'Secure entry session terminated via IP: ::1', '2026-08-03 22:21:58');
INSERT INTO `staff_logs` VALUES ('114', '5', 'Maxies John', 'Staff Login', NULL, 'Secure entry validated via IP: ::1', '2026-08-03 22:22:12');
INSERT INTO `staff_logs` VALUES ('115', '5', 'Maxies John', '', NULL, 'Secure entry session terminated via IP: ::1', '2026-08-03 22:23:01');
INSERT INTO `staff_logs` VALUES ('116', '5', 'Maxies John', 'Staff Login', NULL, 'Secure entry validated via IP: ::1', '2026-08-03 22:41:39');
INSERT INTO `staff_logs` VALUES ('117', '5', 'Maxies John', '', NULL, 'Secure entry session terminated via IP: ::1', '2026-08-03 22:46:11');
INSERT INTO `staff_logs` VALUES ('118', '5', 'Maxies John', 'Staff Login', NULL, 'Secure entry validated via IP: ::1', '2026-08-03 22:51:42');
INSERT INTO `staff_logs` VALUES ('119', '5', 'Maxies John', '', NULL, 'Secure entry session terminated via IP: ::1', '2026-08-03 22:57:32');
INSERT INTO `staff_logs` VALUES ('120', '5', 'Maxies John', 'Staff Login', NULL, 'Secure entry validated via IP: ::1', '2026-08-04 00:16:41');
INSERT INTO `staff_logs` VALUES ('121', '5', 'Maxies John', 'Staff Login', NULL, 'Secure super admin entry validated via IP: ::1', '2026-08-04 01:13:58');
INSERT INTO `staff_logs` VALUES ('122', '5', 'Maxies John', 'Staff Login', NULL, 'Secure super admin entry validated via IP: ::1', '2026-08-04 09:50:35');
INSERT INTO `staff_logs` VALUES ('123', '5', 'Maxies John', 'Staff Login', NULL, 'Secure super admin entry validated via IP: ::1', '2026-08-04 10:16:55');
INSERT INTO `staff_logs` VALUES ('124', '5', 'Maxies John', 'Staff Login', NULL, 'Secure super admin entry validated via IP: ::1', '2026-08-04 12:03:20');
INSERT INTO `staff_logs` VALUES ('125', '5', 'Maxies John', 'Staff Login', NULL, 'Secure super admin entry validated via IP: ::1', '2026-08-04 12:20:29');
INSERT INTO `staff_logs` VALUES ('126', '5', 'Maxies John', 'Staff Login', NULL, 'Secure super admin entry validated via IP: ::1', '2026-08-04 17:27:10');
INSERT INTO `staff_logs` VALUES ('127', '5', 'Maxies John', 'Staff Login', NULL, 'Secure super admin entry validated via IP: ::1', '2026-08-04 21:21:30');
INSERT INTO `staff_logs` VALUES ('128', '5', 'Maxies John', '', NULL, 'Secure entry session terminated via IP: ::1', '2026-08-04 21:27:52');
INSERT INTO `staff_logs` VALUES ('129', '6', 'sarah andrew', 'Staff Login', NULL, 'Secure staff desk entry validated via IP: ::1', '2026-08-04 21:28:06');
INSERT INTO `staff_logs` VALUES ('130', '6', 'sarah andrew', '', NULL, 'Secure entry session terminated via IP: ::1', '2026-08-04 21:30:05');
INSERT INTO `staff_logs` VALUES ('131', '5', 'Maxies John', 'Staff Login', NULL, 'Secure super admin entry validated via IP: ::1', '2026-08-04 21:30:23');
INSERT INTO `staff_logs` VALUES ('132', '5', 'Maxies John', '', NULL, 'Secure entry session terminated via IP: ::1', '2026-08-04 21:30:54');
INSERT INTO `staff_logs` VALUES ('133', '5', 'Maxies John', 'Staff Login', NULL, 'Secure super admin entry validated via IP: ::1', '2026-08-04 21:31:08');
INSERT INTO `staff_logs` VALUES ('134', '5', 'Maxies John', 'Staff Login', NULL, 'Secure admin entry validated via IP: ::1', '2026-08-04 22:33:45');
INSERT INTO `staff_logs` VALUES ('135', '5', 'Maxies John', 'Staff Login', NULL, 'Secure admin entry validated via IP: ::1', '2026-08-04 23:19:07');
INSERT INTO `staff_logs` VALUES ('136', '5', 'Maxies John', 'Staff Login', NULL, 'Secure admin entry validated via IP: ::1', '2026-08-05 00:42:24');
INSERT INTO `staff_logs` VALUES ('137', '5', 'Maxies John', 'Staff Login', NULL, 'Secure admin entry validated via IP: ::1', '2026-08-05 01:50:21');
INSERT INTO `staff_logs` VALUES ('138', '5', 'Maxies John', '', NULL, 'Secure entry session terminated via IP: ::1', '2026-08-05 02:24:11');
INSERT INTO `staff_logs` VALUES ('139', '5', 'Maxies John', 'Staff Login', NULL, 'Secure admin entry validated via IP: ::1', '2026-08-05 15:34:10');
INSERT INTO `staff_logs` VALUES ('140', '5', 'Maxies John', '', NULL, 'Secure entry session terminated via IP: ::1', '2026-08-05 16:16:26');
INSERT INTO `staff_logs` VALUES ('141', '5', 'Maxies John', 'Staff Login', NULL, 'Secure admin entry validated via IP: ::1', '2026-08-06 13:00:04');
INSERT INTO `staff_logs` VALUES ('142', '5', 'Maxies John', 'Staff Login', NULL, 'Secure admin entry validated via IP: ::1', '2026-08-06 13:57:37');
INSERT INTO `staff_logs` VALUES ('143', '5', 'Maxies John', '', NULL, 'Secure entry session terminated via IP: ::1', '2026-08-06 14:10:50');
INSERT INTO `staff_logs` VALUES ('144', '6', 'Sarah Andrew', 'Staff Login', NULL, 'Secure staff desk entry validated via IP: ::1', '2026-08-06 14:11:05');
INSERT INTO `staff_logs` VALUES ('145', '6', 'Sarah Andrew', 'Staff Login', NULL, 'Secure staff desk entry validated via IP: ::1', '2026-08-06 14:20:44');
INSERT INTO `staff_logs` VALUES ('146', '5', 'Maxies John', 'Staff Login', NULL, 'Secure admin entry validated via IP: ::1', '2026-08-06 14:45:08');
INSERT INTO `staff_logs` VALUES ('147', '5', 'Maxies John', 'Staff Login', NULL, 'Secure admin entry validated via IP: ::1', '2026-08-06 15:20:45');
INSERT INTO `staff_logs` VALUES ('148', '5', 'Maxies John', 'Staff Login', NULL, 'Secure admin entry validated via IP: ::1', '2026-08-06 16:46:36');
INSERT INTO `staff_logs` VALUES ('149', '6', 'Sarah Andrew', '', NULL, 'Secure entry session terminated via IP: ::1', '2026-08-06 16:47:30');
INSERT INTO `staff_logs` VALUES ('150', '6', 'Sarah Andrew', 'Staff Login', NULL, 'Secure staff desk entry validated via IP: ::1', '2026-08-06 16:47:45');
INSERT INTO `staff_logs` VALUES ('151', '6', 'Sarah Andrew', '', NULL, 'Secure entry session terminated via IP: ::1', '2026-08-06 16:48:28');
INSERT INTO `staff_logs` VALUES ('152', '6', 'Sarah Andrew', 'Staff Login', NULL, 'Secure staff desk entry validated via IP: ::1', '2026-08-06 16:48:51');
INSERT INTO `staff_logs` VALUES ('153', '14', 'Naomy Andrew', 'Staff Login', NULL, 'Secure admin entry validated via IP: 127.0.0.1', '2026-08-06 17:18:22');
INSERT INTO `staff_logs` VALUES ('154', '5', 'Maxies John', 'Staff Login', NULL, 'Secure admin entry validated via IP: ::1', '2026-08-06 17:52:47');
INSERT INTO `staff_logs` VALUES ('155', '5', 'Maxies John', 'Staff Login', NULL, 'Secure admin entry validated via IP: ::1', '2026-08-06 17:53:28');
INSERT INTO `staff_logs` VALUES ('156', '14', 'Naomy Andrew', 'Staff Login', NULL, 'Secure admin entry validated via IP: 127.0.0.1', '2026-08-06 17:54:34');
INSERT INTO `staff_logs` VALUES ('157', '5', 'Maxies John', 'Staff Login', NULL, 'Secure admin entry validated via IP: ::1', '2026-08-06 17:56:18');
INSERT INTO `staff_logs` VALUES ('158', '5', 'Maxies John', 'Staff Login', NULL, 'Secure admin entry validated via IP: ::1', '2026-08-06 18:10:27');
INSERT INTO `staff_logs` VALUES ('159', '5', 'Maxies John', 'Staff Login', NULL, 'Secure admin entry validated via IP: ::1', '2026-08-06 18:13:13');
INSERT INTO `staff_logs` VALUES ('160', '5', 'Maxies John', 'Staff Login', NULL, 'Secure admin entry validated via IP: ::1', '2026-08-06 18:17:39');
INSERT INTO `staff_logs` VALUES ('161', '5', 'Maxies John', 'Staff Login', NULL, 'Secure admin entry validated via IP: ::1', '2026-08-06 18:17:52');
INSERT INTO `staff_logs` VALUES ('162', '5', 'Maxies John', 'Staff Login', NULL, 'Secure admin entry validated via IP: ::1', '2026-08-06 18:19:55');
INSERT INTO `staff_logs` VALUES ('163', '5', 'Maxies John', 'Staff Login', NULL, 'Secure admin entry validated via IP: ::1', '2026-08-06 18:20:41');
INSERT INTO `staff_logs` VALUES ('164', '5', 'Maxies John', 'Staff Login', NULL, 'Secure admin entry validated via IP: ::1', '2026-08-06 18:21:22');
INSERT INTO `staff_logs` VALUES ('165', '5', 'Maxies John', 'Staff Login', NULL, 'Secure admin entry validated via IP: ::1', '2026-08-06 18:32:04');
INSERT INTO `staff_logs` VALUES ('166', '14', 'Naomy Andrew', 'Staff Login', NULL, 'Secure admin entry validated via IP: ::1', '2026-08-06 18:32:51');
INSERT INTO `staff_logs` VALUES ('167', '14', 'Naomy Andrew', '', NULL, 'Secure entry session terminated via IP: ::1', '2026-08-06 18:34:01');
INSERT INTO `staff_logs` VALUES ('168', '5', 'Maxies John', 'Staff Login', NULL, 'Secure admin entry validated via IP: ::1', '2026-08-06 18:34:23');
INSERT INTO `staff_logs` VALUES ('169', '5', 'Maxies John', 'Staff Login', NULL, 'Secure admin entry validated via IP: ::1', '2026-08-06 18:48:23');
INSERT INTO `staff_logs` VALUES ('170', '5', 'Maxies John', 'Staff Login', NULL, 'Secure admin entry validated via IP: ::1', '2026-08-06 19:00:49');
INSERT INTO `staff_logs` VALUES ('171', '5', 'Maxies John', 'Staff Login', NULL, 'Secure admin entry validated via IP: ::1', '2026-08-06 19:07:35');
INSERT INTO `staff_logs` VALUES ('172', '14', 'Naomy Andrew', 'Staff Login', NULL, 'Secure admin entry validated via IP: ::1', '2026-08-06 19:07:48');
INSERT INTO `staff_logs` VALUES ('173', '5', 'Maxies John', 'Staff Login', NULL, 'Secure admin entry validated via IP: ::1', '2026-08-06 19:47:32');
INSERT INTO `staff_logs` VALUES ('174', '5', 'Maxies John', 'Staff Login', NULL, 'Secure admin entry validated via IP: ::1', '2026-08-06 19:50:54');
INSERT INTO `staff_logs` VALUES ('175', '5', 'Maxies John', 'Staff Login', NULL, 'Secure admin entry validated via IP: ::1', '2026-08-06 19:53:46');
INSERT INTO `staff_logs` VALUES ('176', '5', 'Maxies John', 'Staff Login', NULL, 'Secure admin entry validated via IP: ::1', '2026-08-06 19:55:35');
INSERT INTO `staff_logs` VALUES ('177', '5', 'Maxies John', 'Staff Login', NULL, 'Secure admin entry validated via IP: ::1', '2026-08-06 20:23:09');
INSERT INTO `staff_logs` VALUES ('178', '14', 'Naomy Andrew', 'Staff Login', NULL, 'Secure admin entry validated via IP: ::1', '2026-08-06 20:34:07');
INSERT INTO `staff_logs` VALUES ('179', '14', 'Naomy Andrew', '', NULL, 'Secure entry session terminated via IP: ::1', '2026-08-06 20:57:16');
INSERT INTO `staff_logs` VALUES ('180', '5', 'Maxies John', 'Staff Login', NULL, 'Secure admin entry validated via IP: ::1', '2026-08-06 20:57:29');
INSERT INTO `staff_logs` VALUES ('181', '14', 'Naomy Andrew', '', NULL, 'Secure entry session terminated via IP: ::1', '2026-08-06 20:57:58');
INSERT INTO `staff_logs` VALUES ('182', '14', 'Naomy Andrew', 'Staff Login', NULL, 'Secure admin entry validated via IP: ::1', '2026-08-06 20:58:13');
INSERT INTO `staff_logs` VALUES ('183', '14', 'Naomy Andrew', 'Staff Login', NULL, 'Secure admin entry validated via IP: ::1', '2026-08-06 21:19:37');
INSERT INTO `staff_logs` VALUES ('184', '5', 'Maxies John', 'Staff Login', NULL, 'Secure admin entry validated via IP: ::1', '2026-08-06 21:32:07');
INSERT INTO `staff_logs` VALUES ('185', '5', 'Maxies John', 'Staff Login', NULL, 'Secure admin entry validated via IP: ::1', '2026-08-06 22:52:57');
INSERT INTO `staff_logs` VALUES ('186', '5', 'Maxies John', '', NULL, 'Feedback Moderated: Marked Product Review ID #10 as status tracking token: \'Hidden\'.', '2026-08-06 22:55:00');
INSERT INTO `staff_logs` VALUES ('187', '5', 'Maxies John', '', NULL, 'Feedback Moderated: Marked Product Review ID #10 as status tracking token: \'Approved\'.', '2026-08-06 22:55:06');
INSERT INTO `staff_logs` VALUES ('188', '5', 'Maxies John', '', NULL, 'Order Logistics Modified: Changed Order #94 status tracking index tag to: \'processing\'.', '2026-08-06 22:55:35');
INSERT INTO `staff_logs` VALUES ('189', '5', 'Maxies John', '', NULL, 'Wallet Reconciled: Credited +$5.00 into the customer wallet sheet for operator \'james kimani\' (Wallet ID #16).', '2026-08-06 22:56:16');
INSERT INTO `staff_logs` VALUES ('190', '5', 'Maxies John', 'Staff Login', NULL, 'Secure admin entry validated via IP: ::1', '2026-08-07 00:24:36');
INSERT INTO `staff_logs` VALUES ('191', '14', 'Naomy Andrew', 'Staff Login', NULL, 'Secure admin entry validated via IP: ::1', '2026-08-07 00:31:47');
INSERT INTO `staff_logs` VALUES ('192', '5', 'Maxies John', 'Staff Login', NULL, 'Secure admin entry validated via IP: ::1', '2026-08-07 01:03:22');
INSERT INTO `staff_logs` VALUES ('193', '14', 'Naomy Andrew', 'Staff Login', NULL, 'Secure admin entry validated via IP: ::1', '2026-08-07 01:04:53');
INSERT INTO `staff_logs` VALUES ('194', '5', 'Maxies John', '', NULL, 'Manual Data Snapshot Generated: [autobackup_2026-08-07_00-17-32_6c4ab6.sql] saved to disk store folder.', '2026-08-07 01:17:32');
INSERT INTO `staff_logs` VALUES ('195', '12', 'john allan', 'Staff Login', NULL, 'Secure staff desk entry validated via IP: ::1', '2026-08-07 01:28:34');
INSERT INTO `staff_logs` VALUES ('196', '5', 'Maxies John', 'Staff Login', NULL, 'Secure admin entry validated via IP: 127.0.0.1', '2026-08-07 01:30:26');
INSERT INTO `staff_logs` VALUES ('197', '5', 'Maxies John', '', NULL, 'Staff Profile Modified: Updated account data properties for Employee ID #12 (\'john allan\', Email: maxiesj7@gmail.com, Role ID: #2). Remapped table links to assign 19 clear views.', '2026-08-07 01:31:27');
INSERT INTO `staff_logs` VALUES ('198', '12', 'john allan', '', NULL, 'Secure entry session terminated via IP: ::1', '2026-08-07 01:37:55');
INSERT INTO `staff_logs` VALUES ('199', '10', 'peter otieno', 'Staff Login', NULL, 'Secure staff desk entry validated via IP: ::1', '2026-08-07 01:47:04');
INSERT INTO `staff_logs` VALUES ('200', '10', 'peter otieno', '', NULL, 'Secure entry session terminated via IP: ::1', '2026-08-07 02:18:27');
INSERT INTO `staff_logs` VALUES ('201', '12', 'john allan', 'Staff Login', NULL, 'Secure staff desk entry validated via IP: ::1', '2026-08-07 09:41:17');
INSERT INTO `staff_logs` VALUES ('202', '12', 'john allan', '', NULL, 'Secure entry session terminated via IP: ::1', '2026-08-07 10:10:28');
INSERT INTO `staff_logs` VALUES ('203', '5', 'Maxies John', 'Staff Login', NULL, 'Secure admin entry validated via IP: ::1', '2026-08-07 10:10:50');
INSERT INTO `staff_logs` VALUES ('204', '14', 'Naomy Andrew', 'Staff Login', NULL, 'Secure admin entry validated via IP: ::1', '2026-08-07 10:20:18');
INSERT INTO `staff_logs` VALUES ('205', '14', 'Naomy Andrew', '', NULL, 'Secure entry session terminated via IP: ::1', '2026-08-07 10:20:34');
INSERT INTO `staff_logs` VALUES ('206', '5', 'Maxies John', 'Staff Login', NULL, 'Secure admin entry validated via IP: ::1', '2026-08-07 10:20:45');
INSERT INTO `staff_logs` VALUES ('207', '6', 'Sarah Andrew', 'Staff Login', NULL, 'Secure staff desk entry validated via IP: ::1', '2026-08-07 10:29:58');
INSERT INTO `staff_logs` VALUES ('208', '5', 'Maxies John', 'Staff Login', NULL, 'Secure admin entry validated via IP: ::1', '2026-08-07 11:02:27');
INSERT INTO `staff_logs` VALUES ('209', '5', 'Maxies John', 'Staff Login', NULL, 'Secure admin entry validated via IP: ::1', '2026-08-07 11:35:42');
INSERT INTO `staff_logs` VALUES ('210', '5', 'Maxies John', 'Staff Login', NULL, 'Secure admin entry validated via IP: ::1', '2026-08-07 12:33:27');
INSERT INTO `staff_logs` VALUES ('211', '6', 'Sarah Andrew', 'Staff Login', NULL, 'Secure staff desk entry validated via IP: ::1', '2026-08-07 12:50:22');
INSERT INTO `staff_logs` VALUES ('212', '6', 'Sarah Andrew', 'Staff Login', NULL, 'Secure staff desk entry validated via IP: ::1', '2026-08-07 13:09:45');
INSERT INTO `staff_logs` VALUES ('213', '5', 'Maxies John', 'Staff Login', NULL, 'Secure admin entry validated via IP: ::1', '2026-08-07 13:45:52');
INSERT INTO `staff_logs` VALUES ('214', '12', 'john allan', 'Staff Login', NULL, 'Secure staff desk entry validated via IP: 127.0.0.1', '2026-08-07 14:01:25');
INSERT INTO `staff_logs` VALUES ('215', '5', 'Maxies John', '', NULL, 'Staff Profile Modified: Updated account data properties for Employee ID #12 (\'john allan\', Email: maxiesj7@gmail.com, Role ID: #2). Remapped table links to assign 18 clear views.', '2026-08-07 14:03:51');
INSERT INTO `staff_logs` VALUES ('216', '5', 'Maxies John', '', NULL, 'Staff Profile Modified: Updated account data properties for Employee ID #12 (\'john allan\', Email: maxiesj7@gmail.com, Role ID: #2). Remapped table links to assign 17 clear views.', '2026-08-07 14:05:27');
INSERT INTO `staff_logs` VALUES ('217', '6', 'Sarah Andrew', '', NULL, 'Secure entry session terminated via IP: ::1', '2026-08-07 14:06:23');
INSERT INTO `staff_logs` VALUES ('218', '14', 'Naomy Andrew', 'Staff Login', NULL, 'Secure admin entry validated via IP: ::1', '2026-08-07 14:07:34');
INSERT INTO `staff_logs` VALUES ('219', '14', 'Naomy Andrew', '', NULL, 'Secure entry session terminated via IP: ::1', '2026-08-07 14:09:18');
INSERT INTO `staff_logs` VALUES ('220', '12', 'john allan', '', NULL, 'Secure entry session terminated via IP: 127.0.0.1', '2026-08-07 14:16:29');
INSERT INTO `staff_logs` VALUES ('221', '5', 'Maxies John', 'Staff Login', NULL, 'Secure admin entry validated via IP: ::1', '2026-08-07 18:59:45');
INSERT INTO `staff_logs` VALUES ('222', '6', 'Sarah Andrew', 'Staff Login', NULL, 'Secure staff desk entry validated via IP: ::1', '2026-08-07 19:18:32');
INSERT INTO `staff_logs` VALUES ('223', '5', 'Maxies John', 'Staff Login', NULL, 'Secure admin entry validated via IP: ::1', '2026-08-07 19:35:17');
INSERT INTO `staff_logs` VALUES ('224', '6', 'Sarah Andrew', '', NULL, 'Secure entry session terminated via IP: ::1', '2026-08-07 19:44:08');
INSERT INTO `staff_logs` VALUES ('225', '5', 'Maxies John', 'Inventory Update', NULL, 'Product ID #52 modifications successfully saved. SKU Updated: [FREE-01].', '2026-08-07 19:48:12');
INSERT INTO `staff_logs` VALUES ('226', '5', 'Maxies John', '', NULL, 'Feedback Moderated: Marked Product Review ID #10 as status tracking token: \'Hidden\'.', '2026-08-07 19:48:32');
INSERT INTO `staff_logs` VALUES ('227', '5', 'Maxies John', '', NULL, 'Feedback Moderated: Marked Product Review ID #10 as status tracking token: \'Approved\'.', '2026-08-07 19:48:35');
INSERT INTO `staff_logs` VALUES ('228', '5', 'Maxies John', 'Inventory Update', NULL, 'Procurement Reorder Dispatched: Generated formal inventory replenishment notification for model [SUMSANG S26 ULTRA COVER] to agent email [SAMSUNG@GMAIL.COM] asking for +25 turnover items.', '2026-08-07 19:50:38');
INSERT INTO `staff_logs` VALUES ('229', '5', 'Maxies John', '', NULL, 'Manual Data Snapshot Generated: [autobackup_2026-08-07_18-53-44_c64316.sql] saved to disk store folder.', '2026-08-07 19:53:44');
INSERT INTO `staff_logs` VALUES ('230', '10', 'peter otieno', 'Staff Login', NULL, 'Secure staff desk entry validated via IP: 127.0.0.1', '2026-08-07 19:55:26');
INSERT INTO `staff_logs` VALUES ('231', '5', 'Maxies John', '', NULL, 'Staff Profile Modified: Updated account data properties for Employee ID #10 (\'peter otieno\', Email: otieno@gmail.com, Role ID: #2). Remapped table links to assign 3 clear views.', '2026-08-07 19:56:02');
INSERT INTO `staff_logs` VALUES ('232', '10', 'peter otieno', '', NULL, 'Secure entry session terminated via IP: 127.0.0.1', '2026-08-07 19:56:24');
INSERT INTO `staff_logs` VALUES ('233', '14', 'Naomy Andrew', 'Staff Login', NULL, 'Secure admin entry validated via IP: 127.0.0.1', '2026-08-07 19:56:42');
INSERT INTO `staff_logs` VALUES ('234', '5', 'Maxies John', '', NULL, 'Staff Profile Modified: Updated account data properties for Employee ID #14 (\'Naomy Andrew\', Email: kemmy@gmail.com, Role ID: #1). Remapped table links to assign 0 clear views.', '2026-08-07 19:57:42');
INSERT INTO `staff_logs` VALUES ('235', '5', 'Maxies John', '', NULL, 'Global Tax Matrix Re-calibrated: Modified baseline VAT rate from 0.00% to brand new rate threshold: [10.00%].', '2026-08-07 19:58:57');
INSERT INTO `staff_logs` VALUES ('236', '14', 'Naomy Andrew', '', NULL, 'Secure entry session terminated via IP: 127.0.0.1', '2026-08-07 20:00:47');
INSERT INTO `staff_logs` VALUES ('237', '5', 'Maxies John', 'Staff Login', NULL, 'Secure admin entry validated via IP: ::1', '2026-08-09 17:22:34');
INSERT INTO `staff_logs` VALUES ('238', '5', 'Maxies John', 'Staff Login', NULL, 'Secure admin entry validated via IP: ::1', '2026-08-09 19:31:46');
INSERT INTO `staff_logs` VALUES ('239', '6', 'Sarah Andrew', 'Staff Login', NULL, 'Secure staff desk entry validated via IP: ::1', '2026-08-09 19:42:17');
INSERT INTO `staff_logs` VALUES ('240', '5', 'Maxies John', 'Staff Login', NULL, 'Secure admin entry validated via IP: ::1', '2026-08-09 20:06:31');
INSERT INTO `staff_logs` VALUES ('241', '10', 'peter otieno', 'Staff Login', NULL, 'Secure staff desk entry validated via IP: ::1', '2026-08-09 20:33:47');
INSERT INTO `staff_logs` VALUES ('242', '5', 'Maxies John', 'Staff Login', NULL, 'Secure admin entry validated via IP: ::1', '2026-08-10 21:10:05');
INSERT INTO `staff_logs` VALUES ('243', '5', 'Maxies John', 'Staff Login', NULL, 'Secure admin entry validated via IP: ::1', '2026-08-10 21:27:34');
INSERT INTO `staff_logs` VALUES ('244', '5', 'Maxies John', 'Staff Login', NULL, 'Secure admin entry validated via IP: ::1', '2026-08-10 21:59:06');
INSERT INTO `staff_logs` VALUES ('245', '6', 'Sarah Andrew', '', NULL, 'Secure entry session terminated via IP: ::1', '2026-08-10 22:29:05');
INSERT INTO `staff_logs` VALUES ('246', '5', 'Maxies John', 'Staff Login', NULL, 'Secure admin entry validated via IP: ::1', '2026-08-10 22:48:32');
INSERT INTO `staff_logs` VALUES ('247', '5', 'Maxies John', '', NULL, 'Feedback Moderated: Marked Product Review ID #12 as status tracking token: \'Hidden\'.', '2026-08-10 22:48:45');
INSERT INTO `staff_logs` VALUES ('248', '5', 'Maxies John', '', NULL, 'Feedback Moderated: Marked Product Review ID #12 as status tracking token: \'Approved\'.', '2026-08-10 22:49:05');
INSERT INTO `staff_logs` VALUES ('249', '5', 'Maxies John', 'Staff Login', NULL, 'Secure admin entry validated via IP: ::1', '2026-08-10 23:11:58');
INSERT INTO `staff_logs` VALUES ('250', '5', 'Maxies John', 'Staff Login', NULL, 'Secure admin entry validated via IP: ::1', '2026-08-10 23:40:13');
INSERT INTO `staff_logs` VALUES ('251', '5', 'Maxies John', 'Staff Login', NULL, 'Secure admin entry validated via IP: ::1', '2026-08-11 00:42:01');
INSERT INTO `staff_logs` VALUES ('252', '10', 'peter otieno', 'Staff Login', NULL, 'Secure staff desk entry validated via IP: ::1', '2026-08-11 00:44:14');
INSERT INTO `staff_logs` VALUES ('253', '5', 'Maxies John', 'Staff Login', NULL, 'Secure admin entry validated via IP: ::1', '2026-08-11 01:01:54');
INSERT INTO `staff_logs` VALUES ('254', '10', 'peter otieno', '', NULL, 'Secure entry session terminated via IP: ::1', '2026-08-11 01:12:33');
INSERT INTO `staff_logs` VALUES ('255', '6', 'Sarah Andrew', 'Staff Login', NULL, 'Secure staff desk entry validated via IP: ::1', '2026-08-11 01:12:45');
INSERT INTO `staff_logs` VALUES ('256', '5', 'Maxies John', 'Staff Login', NULL, 'Secure admin entry validated via IP: ::1', '2026-08-11 09:27:04');
INSERT INTO `staff_logs` VALUES ('257', '5', 'Maxies John', 'Staff Login', NULL, 'Secure admin entry validated via IP: ::1', '2026-08-11 10:05:56');
INSERT INTO `staff_logs` VALUES ('258', '5', 'Maxies John', 'Staff Login', NULL, 'Secure admin entry validated via IP: ::1', '2026-08-11 10:09:40');
INSERT INTO `staff_logs` VALUES ('259', '6', 'Sarah Andrew', 'Staff Login', NULL, 'Secure staff desk entry validated via IP: ::1', '2026-08-11 10:10:51');
INSERT INTO `staff_logs` VALUES ('260', '5', 'Maxies John', 'Staff Login', NULL, 'Secure admin entry validated via IP: ::1', '2026-08-11 11:07:41');
INSERT INTO `staff_logs` VALUES ('261', '5', 'Maxies John', 'Staff Login', NULL, 'Secure admin entry validated via IP: ::1', '2026-08-11 11:49:30');
INSERT INTO `staff_logs` VALUES ('262', '6', 'Sarah Andrew', '', NULL, 'Secure entry session terminated via IP: ::1', '2026-08-11 11:55:59');
INSERT INTO `staff_logs` VALUES ('263', '5', 'Maxies John', 'Staff Login', NULL, 'Secure admin entry validated via IP: ::1', '2026-08-11 12:29:20');
INSERT INTO `staff_logs` VALUES ('264', '5', 'Maxies John', '', NULL, 'Order Logistics Modified: Changed Order #100 status to \'processing\'. Settlement verified before delivery.', '2026-08-11 12:31:19');
INSERT INTO `staff_logs` VALUES ('265', '5', 'Maxies John', '', NULL, 'Order Cancelled & Refunded: Order #94 cancelled. KES 240.00 of verified completed payments credited to User ID #12.', '2026-08-11 12:40:09');
INSERT INTO `staff_logs` VALUES ('266', '5', 'Maxies John', '', NULL, 'Order Logistics Modified: Changed Order #92 status to \'pending\'. Settlement verified before delivery.', '2026-08-11 12:40:19');
INSERT INTO `staff_logs` VALUES ('267', '5', 'Maxies John', '', NULL, 'Order Logistics Modified: Changed Order #92 status to \'delivered\'. Settlement verified before delivery.', '2026-08-11 12:40:29');
INSERT INTO `staff_logs` VALUES ('268', '5', 'Maxies John', '', NULL, 'Payment Reconciliation: Order #36 changed from Delivered to Pending because KES 440.00 remains unpaid. Paid KES 0.00 of KES 440.00.', '2026-08-11 12:50:48');
INSERT INTO `staff_logs` VALUES ('269', '5', 'Maxies John', '', NULL, 'Payment Reconciliation: Order #37 changed from Delivered to Pending because KES 740.00 remains unpaid. Paid KES 0.00 of KES 740.00.', '2026-08-11 12:50:48');
INSERT INTO `staff_logs` VALUES ('270', '5', 'Maxies John', '', NULL, 'Payment Reconciliation: Order #38 changed from Delivered to Pending because KES 740.00 remains unpaid. Paid KES 0.00 of KES 740.00.', '2026-08-11 12:50:48');
INSERT INTO `staff_logs` VALUES ('271', '5', 'Maxies John', '', NULL, 'Payment Reconciliation: Order #58 changed from Delivered to Pending because KES 770.40 remains unpaid. Paid KES 770.40 of KES 1,540.80.', '2026-08-11 12:50:48');
INSERT INTO `staff_logs` VALUES ('272', '5', 'Maxies John', '', NULL, 'Payment Reconciliation: Order #59 changed from Delivered to Pending because KES 321.00 remains unpaid. Paid KES 0.00 of KES 321.00.', '2026-08-11 12:50:48');
INSERT INTO `staff_logs` VALUES ('273', '5', 'Maxies John', '', NULL, 'Payment Reconciliation: Order #61 changed from Delivered to Pending because KES 577.80 remains unpaid. Paid KES 0.00 of KES 577.80.', '2026-08-11 12:50:48');
INSERT INTO `staff_logs` VALUES ('274', '5', 'Maxies John', '', NULL, 'Payment Reconciliation: Order #62 changed from Delivered to Pending because KES 321.00 remains unpaid. Paid KES 0.00 of KES 321.00.', '2026-08-11 12:50:48');
INSERT INTO `staff_logs` VALUES ('275', '5', 'Maxies John', '', NULL, 'Payment Reconciliation: Order #70 changed from Delivered to Pending because KES 5,390.00 remains unpaid. Paid KES 5,390.00 of KES 10,780.00.', '2026-08-11 12:50:48');
INSERT INTO `staff_logs` VALUES ('276', '5', 'Maxies John', '', NULL, 'Order Cancelled & Refunded: Order #40 cancelled. KES 0.00 of verified completed payments credited to User ID #7.', '2026-08-11 13:03:35');
INSERT INTO `staff_logs` VALUES ('277', '5', 'Maxies John', '', NULL, 'Order Cancelled & Refunded: Order #42 cancelled. KES 0.00 of verified completed payments credited to User ID #7.', '2026-08-11 13:03:48');
INSERT INTO `staff_logs` VALUES ('278', '5', 'Maxies John', 'Staff Login', NULL, 'Secure admin entry validated via IP: ::1', '2026-08-11 13:32:16');
INSERT INTO `staff_logs` VALUES ('279', '5', 'Maxies John', 'Staff Login', NULL, 'Secure admin entry validated via IP: ::1', '2026-08-11 14:24:46');
INSERT INTO `staff_logs` VALUES ('280', '5', 'Maxies John', 'Staff Login', NULL, 'Secure admin entry validated via IP: ::1', '2026-08-11 14:31:08');
INSERT INTO `staff_logs` VALUES ('281', '6', 'Sarah Andrew', 'Staff Login', NULL, 'Secure staff desk entry validated via IP: ::1', '2026-08-11 14:50:14');
INSERT INTO `staff_logs` VALUES ('282', '14', 'Naomy Andrew', 'Staff Login', NULL, 'Secure admin entry validated via IP: ::1', '2026-08-11 15:39:36');
INSERT INTO `staff_logs` VALUES ('283', '5', 'Maxies John', 'Staff Login', NULL, 'Secure admin entry validated via IP: ::1', '2026-08-11 18:36:14');
INSERT INTO `staff_logs` VALUES ('284', '6', 'Sarah Andrew', 'Staff Login', NULL, 'Secure staff desk entry validated via IP: 127.0.0.1', '2026-08-11 18:38:06');
INSERT INTO `staff_logs` VALUES ('285', '5', 'Maxies John', 'Staff Login', NULL, 'Secure admin entry validated via IP: ::1', '2026-08-11 19:44:32');
INSERT INTO `staff_logs` VALUES ('286', '5', 'Maxies John', '', NULL, 'Staff Profile Modified: Updated account data properties for Employee ID #14 (\'Naomy Andrew\', Email: kemmy@gmail.com, Role ID: #1). Remapped table links to assign 15 clear views.', '2026-08-11 19:46:41');
INSERT INTO `staff_logs` VALUES ('287', '10', 'peter otieno', 'Staff Login', NULL, 'Secure staff desk entry validated via IP: 127.0.0.1', '2026-08-11 19:48:23');
INSERT INTO `staff_logs` VALUES ('288', '5', 'Maxies John', 'Staff Login', NULL, 'Secure admin entry validated via IP: ::1', '2026-08-11 20:47:40');
INSERT INTO `staff_logs` VALUES ('289', '6', 'Sarah Andrew', 'Staff Login', NULL, 'Secure staff desk entry validated via IP: 127.0.0.1', '2026-08-11 20:55:10');

DROP TABLE IF EXISTS `staff_permissions`;
CREATE TABLE `staff_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `target_view` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_perm` (`user_id`,`target_view`)
) ENGINE=InnoDB AUTO_INCREMENT=226 DEFAULT CHARSET=utf8mb4;

INSERT INTO `staff_permissions` VALUES ('135', '5', 'add_product.php');
INSERT INTO `staff_permissions` VALUES ('140', '5', 'dashboard_overview.php');
INSERT INTO `staff_permissions` VALUES ('138', '5', 'db_backup.php');
INSERT INTO `staff_permissions` VALUES ('148', '5', 'etims_pdf_report.php');
INSERT INTO `staff_permissions` VALUES ('143', '5', 'invoice_archiver.php');
INSERT INTO `staff_permissions` VALUES ('149', '5', 'layaway_defaulters.php');
INSERT INTO `staff_permissions` VALUES ('144', '5', 'low_stock_dispatcher.php');
INSERT INTO `staff_permissions` VALUES ('151', '5', 'low_stock_monitor.php');
INSERT INTO `staff_permissions` VALUES ('136', '5', 'manage_categories.php');
INSERT INTO `staff_permissions` VALUES ('137', '5', 'manage_customers.php');
INSERT INTO `staff_permissions` VALUES ('146', '5', 'manage_orders.php');
INSERT INTO `staff_permissions` VALUES ('150', '5', 'manage_reviews.php');
INSERT INTO `staff_permissions` VALUES ('147', '5', 'manage_staff.php');
INSERT INTO `staff_permissions` VALUES ('139', '5', 'manage_wallets.php');
INSERT INTO `staff_permissions` VALUES ('145', '5', 'mpesa_checker.php');
INSERT INTO `staff_permissions` VALUES ('141', '5', 'sales_analytics.php');
INSERT INTO `staff_permissions` VALUES ('142', '5', 'tax_settings.php');
INSERT INTO `staff_permissions` VALUES ('152', '5', 'warehouse.php');
INSERT INTO `staff_permissions` VALUES ('153', '5', 'workspace_tracker');
INSERT INTO `staff_permissions` VALUES ('118', '6', 'layaway_defaulters.php');
INSERT INTO `staff_permissions` VALUES ('120', '6', 'low_stock_monitor.php');
INSERT INTO `staff_permissions` VALUES ('117', '6', 'manage_orders.php');
INSERT INTO `staff_permissions` VALUES ('119', '6', 'manage_reviews.php');
INSERT INTO `staff_permissions` VALUES ('116', '6', 'mpesa_checker.php');
INSERT INTO `staff_permissions` VALUES ('209', '10', 'low_stock_monitor.php');
INSERT INTO `staff_permissions` VALUES ('208', '10', 'manage_customers.php');
INSERT INTO `staff_permissions` VALUES ('210', '10', 'warehouse.php');
INSERT INTO `staff_permissions` VALUES ('43', '11', 'add_product.php');
INSERT INTO `staff_permissions` VALUES ('46', '11', 'manage_orders.php');
INSERT INTO `staff_permissions` VALUES ('45', '11', 'sales_analytics.php');
INSERT INTO `staff_permissions` VALUES ('47', '11', 'warehouse.php');
INSERT INTO `staff_permissions` VALUES ('191', '12', 'add_product.php');
INSERT INTO `staff_permissions` VALUES ('196', '12', 'dashboard_overview.php');
INSERT INTO `staff_permissions` VALUES ('194', '12', 'db_backup.php');
INSERT INTO `staff_permissions` VALUES ('203', '12', 'etims_pdf_report.php');
INSERT INTO `staff_permissions` VALUES ('199', '12', 'invoice_archiver.php');
INSERT INTO `staff_permissions` VALUES ('204', '12', 'layaway_defaulters.php');
INSERT INTO `staff_permissions` VALUES ('200', '12', 'low_stock_dispatcher.php');
INSERT INTO `staff_permissions` VALUES ('192', '12', 'manage_categories.php');
INSERT INTO `staff_permissions` VALUES ('193', '12', 'manage_customers.php');
INSERT INTO `staff_permissions` VALUES ('201', '12', 'manage_orders.php');
INSERT INTO `staff_permissions` VALUES ('205', '12', 'manage_reviews.php');
INSERT INTO `staff_permissions` VALUES ('202', '12', 'manage_staff.php');
INSERT INTO `staff_permissions` VALUES ('195', '12', 'manage_wallets.php');
INSERT INTO `staff_permissions` VALUES ('197', '12', 'sales_analytics.php');
INSERT INTO `staff_permissions` VALUES ('198', '12', 'tax_settings.php');
INSERT INTO `staff_permissions` VALUES ('206', '12', 'warehouse.php');
INSERT INTO `staff_permissions` VALUES ('207', '12', 'workspace_tracker');
INSERT INTO `staff_permissions` VALUES ('211', '14', 'add_product.php');
INSERT INTO `staff_permissions` VALUES ('214', '14', 'dashboard_overview.php');
INSERT INTO `staff_permissions` VALUES ('221', '14', 'etims_pdf_report.php');
INSERT INTO `staff_permissions` VALUES ('216', '14', 'invoice_archiver.php');
INSERT INTO `staff_permissions` VALUES ('222', '14', 'layaway_defaulters.php');
INSERT INTO `staff_permissions` VALUES ('217', '14', 'low_stock_dispatcher.php');
INSERT INTO `staff_permissions` VALUES ('224', '14', 'low_stock_monitor.php');
INSERT INTO `staff_permissions` VALUES ('212', '14', 'manage_categories.php');
INSERT INTO `staff_permissions` VALUES ('213', '14', 'manage_customers.php');
INSERT INTO `staff_permissions` VALUES ('219', '14', 'manage_orders.php');
INSERT INTO `staff_permissions` VALUES ('223', '14', 'manage_reviews.php');
INSERT INTO `staff_permissions` VALUES ('220', '14', 'manage_staff.php');
INSERT INTO `staff_permissions` VALUES ('218', '14', 'mpesa_checker.php');
INSERT INTO `staff_permissions` VALUES ('215', '14', 'sales_analytics.php');
INSERT INTO `staff_permissions` VALUES ('225', '14', 'warehouse.php');

DROP TABLE IF EXISTS `system_permissions`;
CREATE TABLE `system_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `permission_key` varchar(100) NOT NULL,
  `display_name` varchar(150) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permission_key` (`permission_key`),
  UNIQUE KEY `permission_key_2` (`permission_key`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4;

INSERT INTO `system_permissions` VALUES ('1', 'add_product.php', 'Add New Product');
INSERT INTO `system_permissions` VALUES ('2', 'warehouse.php', 'Warehouse Stock');
INSERT INTO `system_permissions` VALUES ('3', 'manage_orders.php', 'Manage Sales Orders');
INSERT INTO `system_permissions` VALUES ('4', 'sales_analytics.php', 'Financial Analytics');
INSERT INTO `system_permissions` VALUES ('6', 'layaway_defaulters.php', 'Pole Pole');
INSERT INTO `system_permissions` VALUES ('7', 'low_stock_monitor.php', 'Stock Alarms');
INSERT INTO `system_permissions` VALUES ('8', 'mpesa_checker.php', 'M-pesa Check');
INSERT INTO `system_permissions` VALUES ('9', 'manage_customers.php', 'Clients');
INSERT INTO `system_permissions` VALUES ('10', 'manage_reviews.php', 'Reviews');
INSERT INTO `system_permissions` VALUES ('11', 'dashboard_overview.php', 'Dashboard Overview');
INSERT INTO `system_permissions` VALUES ('12', 'low_stock_dispatcher.php', 'Low Stock Dispatcher');
INSERT INTO `system_permissions` VALUES ('13', 'manage_wallets.php', 'Customer Wallets');
INSERT INTO `system_permissions` VALUES ('14', 'manage_categories.php', 'Categories & Brands');
INSERT INTO `system_permissions` VALUES ('15', 'invoice_archiver.php', 'Invoice PDF Archiver');
INSERT INTO `system_permissions` VALUES ('16', 'etims_pdf_report.php', 'Monthly Reports');
INSERT INTO `system_permissions` VALUES ('17', 'db_backup.php', 'Core Data Backup');
INSERT INTO `system_permissions` VALUES ('18', 'workspace_tracker', 'Workspace Tracker');
INSERT INTO `system_permissions` VALUES ('19', 'manage_staff.php', 'Manage Staff Network');
INSERT INTO `system_permissions` VALUES ('20', 'tax_settings.php', 'Global Tax Settings');

DROP TABLE IF EXISTS `system_settings`;
CREATE TABLE `system_settings` (
  `setting_key` varchar(50) NOT NULL,
  `setting_value` varchar(255) NOT NULL,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

INSERT INTO `system_settings` VALUES ('tax_rate', '10.00');
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
INSERT INTO `system_settings` VALUES ('tax_rate_archived_1784841065', '8.00');
INSERT INTO `system_settings` VALUES ('tax_rate_archived_1784841249', '0.00');
INSERT INTO `system_settings` VALUES ('tax_rate_archived_1784841314', '1.00');
INSERT INTO `system_settings` VALUES ('tax_rate_archived_1784841939', '10.00');
INSERT INTO `system_settings` VALUES ('tax_rate_archived_1784841958', '0.00');
INSERT INTO `system_settings` VALUES ('tax_rate_archived_1784842514', '5.00');
INSERT INTO `system_settings` VALUES ('tax_rate_archived_1785094027', '7.00');
INSERT INTO `system_settings` VALUES ('tax_rate_archived_1786121937', '0.00');

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fullname` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) NOT NULL,
  `password` varchar(255) DEFAULT NULL,
  `role_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `shipping_phone` varchar(20) DEFAULT NULL,
  `shipping_address` text,
  `kra_pin` varchar(20) DEFAULT NULL,
  `reset_token_hash` varchar(64) DEFAULT NULL,
  `reset_token_expires_at` datetime DEFAULT NULL,
  `account_status` varchar(20) DEFAULT 'active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `phone` (`phone`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `reset_token_hash` (`reset_token_hash`),
  KEY `fk_user_role` (`role_id`),
  CONSTRAINT `fk_user_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=latin1;

INSERT INTO `users` VALUES ('5', 'Maxies John', 'maxiesj6@gmail.com', '790900055', '$2y$10$OBuB1I5Zmz.s1ROQ7sRzieNEYs8T6AdbI3r2.cPBnjY9N5eyzhWTm', '6', '2026-07-07 23:30:00', NULL, NULL, NULL, 'dc4f8a078d2963759cc0fd6be63b655297de9e1d0d3c64b74c4503c57babe85f', '2026-08-02 22:36:23', 'active');
INSERT INTO `users` VALUES ('6', 'Sarah Andrew', 'sarah@gmail.com', '721212121', '$2y$10$Dq.Hulx.escj8qJ5QB.JierkONozZc2xYKJahu7ne8u.zvmKBlRqG', '2', '2026-07-08 18:03:32', NULL, NULL, NULL, NULL, NULL, 'active');
INSERT INTO `users` VALUES ('7', 'james kimani', 'james@gmail.com', '797898989', '$2y$10$yEOF6V67dBTT7yHZNFGDo.xH1zpYLQBTdxgjm8qPZJWSsx5OEB7qu', '3', '2026-07-08 19:03:43', '797898989', 'JERU, ACTION VILLA PLAZA, 1ST FLOOR, HOUSE NUMBER 103', 'A051534789G', NULL, NULL, 'purged');
INSERT INTO `users` VALUES ('10', 'peter otieno', 'otieno@gmail.com', '740235465', '$2y$10$BT60EpqyQWcwiuKxhfmegeIi9R/XXIYeGL5VUT5BY0zyh4U3xQtSO', '2', '2026-07-11 19:52:59', '0740235465', 'ACTION VILLA FLATS, JERU 1ST FLOOR ROOM 301', 'A003476452Q', NULL, NULL, 'active');
INSERT INTO `users` VALUES ('11', 'james allan', 'allan@gmail.com', '789898989', '$2y$10$zTO2Q4RPu6Kuv1g9cizXIuD8kzSTzZkM7cSd0qtsKwuQ/e6OMAQLa', '2', '2026-07-11 19:54:08', NULL, NULL, NULL, NULL, NULL, 'active');
INSERT INTO `users` VALUES ('12', 'john allan', 'maxiesj7@gmail.com', '790090055', '$2y$10$GWzIqk8jO2.DYwGstuzbv.yEmjpTUvzpSAJtRkrgEIQuvHvB8O/aC', '2', '2026-07-11 19:54:47', '790090055', 'JERUSALEM, ACTION VILLA,, 2ND FLOOR HOUSE NUMBER 306', 'A001276890C', 'f187561bcb97863789451ab2e08068b77f8d552ea71e3a709ab314c07c7869e1', '2026-07-12 20:38:48', 'active');
INSERT INTO `users` VALUES ('14', 'Naomy Andrew', 'kemmy@gmail.com', '', '$2y$10$6zTSx0m4hW0NyWkz/TLSz.PsXlLg/yijX63jY7qNW.J8oED2dOJoK', '1', '2026-08-06 17:16:04', NULL, NULL, NULL, NULL, NULL, 'active');
INSERT INTO `users` VALUES ('15', 'JOHN ANDREW', 'john@gmail.com', '0701010101', '$2y$10$/f4L5.LvlFyyNauEyXGA3.7EKn8zrWixIrqhhJ/3XTFKvWBxJO2am', '3', '2026-08-11 02:32:54', '0701010101', 'DAIMA TOWERS, ALONG UGANDA ROAD, 8 FLOOR NOTHERN WING, ELDORET', NULL, NULL, NULL, 'active');

SET FOREIGN_KEY_CHECKS=1;
