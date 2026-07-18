-- Middo full schema + seeded test data
-- Generated for phpMyAdmin import.
--
-- Import steps:
--   1. In phpMyAdmin, create (or select) your target database
--   2. Open the Import tab and choose this file
--   3. Format: SQL — then Go
--
-- Note: This dump DROPs existing tables with the same names before recreating them.
-- Seeded login password for all demo users: 12345678
-- Admin mobile: 01310123451


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `areas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `areas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `city_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `areas_city_id_foreign` (`city_id`),
  CONSTRAINT `areas_city_id_foreign` FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `areas` WRITE;
/*!40000 ALTER TABLE `areas` DISABLE KEYS */;
INSERT INTO `areas` (`id`, `name`, `city_id`, `created_at`, `updated_at`) VALUES (1,'Mirpur',1,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(2,'Gulshan',1,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(3,'Banani',1,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(4,'Baridhara',1,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(5,'Cantonment',1,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(6,'Chittagong sadar',2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(7,'Bayezid',2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(8,'Halishahar',2,'2026-07-18 10:05:17','2026-07-18 10:05:17');
/*!40000 ALTER TABLE `areas` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` bigint(20) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` bigint(20) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `cache_locks` WRITE;
/*!40000 ALTER TABLE `cache_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache_locks` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `cash_handover_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cash_handover_orders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `cash_handover_id` bigint(20) unsigned NOT NULL,
  `order_id` bigint(20) unsigned NOT NULL,
  `amount` int(10) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cash_handover_orders_order_id_unique` (`order_id`),
  KEY `cash_handover_orders_cash_handover_id_index` (`cash_handover_id`),
  CONSTRAINT `cash_handover_orders_cash_handover_id_foreign` FOREIGN KEY (`cash_handover_id`) REFERENCES `cash_handovers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cash_handover_orders_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `cash_handover_orders` WRITE;
/*!40000 ALTER TABLE `cash_handover_orders` DISABLE KEYS */;
/*!40000 ALTER TABLE `cash_handover_orders` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `cash_handovers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cash_handovers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `rider_id` bigint(20) unsigned NOT NULL,
  `amount` int(10) unsigned NOT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'pending',
  `accepted_by` bigint(20) unsigned DEFAULT NULL,
  `accepted_at` timestamp NULL DEFAULT NULL,
  `notes` varchar(500) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cash_handovers_accepted_by_foreign` (`accepted_by`),
  KEY `cash_handovers_rider_id_status_index` (`rider_id`,`status`),
  KEY `cash_handovers_status_index` (`status`),
  CONSTRAINT `cash_handovers_accepted_by_foreign` FOREIGN KEY (`accepted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cash_handovers_rider_id_foreign` FOREIGN KEY (`rider_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `cash_handovers` WRITE;
/*!40000 ALTER TABLE `cash_handovers` DISABLE KEYS */;
/*!40000 ALTER TABLE `cash_handovers` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `cities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cities` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `cities` WRITE;
/*!40000 ALTER TABLE `cities` DISABLE KEYS */;
INSERT INTO `cities` (`id`, `name`, `created_at`, `updated_at`) VALUES (1,'Dhaka','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(2,'Chittagong','2026-07-18 10:05:17','2026-07-18 10:05:17');
/*!40000 ALTER TABLE `cities` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `contact_forms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `contact_forms` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `company` varchar(255) DEFAULT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `contact_forms` WRITE;
/*!40000 ALTER TABLE `contact_forms` DISABLE KEYS */;
/*!40000 ALTER TABLE `contact_forms` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `device_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `device_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `token` varchar(512) NOT NULL,
  `platform` varchar(32) NOT NULL DEFAULT 'android',
  `device_name` varchar(120) DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `device_tokens_token_unique` (`token`),
  KEY `device_tokens_user_id_platform_index` (`user_id`,`platform`),
  CONSTRAINT `device_tokens_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `device_tokens` WRITE;
/*!40000 ALTER TABLE `device_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `device_tokens` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` varchar(255) NOT NULL,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`),
  KEY `failed_jobs_connection_queue_failed_at_index` (`connection`,`queue`,`failed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `job_batches` WRITE;
/*!40000 ALTER TABLE `job_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_batches` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` smallint(5) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `meal_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `meal_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `summary` text DEFAULT NULL,
  `thumbnail` varchar(255) DEFAULT NULL,
  `recipe_ingredient_cost` int(11) NOT NULL DEFAULT 0,
  `other_costs` int(11) NOT NULL DEFAULT 0,
  `total_cost` int(11) NOT NULL DEFAULT 0,
  `note` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `meal_items` WRITE;
/*!40000 ALTER TABLE `meal_items` DISABLE KEYS */;
INSERT INTO `meal_items` (`id`, `name`, `summary`, `thumbnail`, `recipe_ingredient_cost`, `other_costs`, `total_cost`, `note`, `created_at`, `updated_at`) VALUES (1,'Rice','Steamed basmati rice.',NULL,19,5,24,'Seeded test meal item','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(2,'Daal','Yellow lentil dal.',NULL,24,8,32,'Seeded test meal item','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(3,'Potato Mash','Spiced mashed potatoes.',NULL,18,6,24,'Seeded test meal item','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(4,'Salad','Fresh kachumber salad.',NULL,14,3,17,'Seeded test meal item','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(5,'Chicken Curry','Rich chicken curry.',NULL,72,15,87,'Seeded test meal item','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(6,'Beef Curry','Slow-cooked beef curry.',NULL,89,18,107,'Seeded test meal item','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(7,'Mixed Veg','Seasonal mixed vegetable curry.',NULL,25,7,32,'Seeded test meal item','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(8,'Fish Curry','Bengali-style fish curry.',NULL,78,12,90,'Seeded test meal item','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(9,'Raita','Yogurt raita.',NULL,15,4,19,'Seeded test meal item','2026-07-18 10:05:17','2026-07-18 10:05:17');
/*!40000 ALTER TABLE `meal_items` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `meal_package_days`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `meal_package_days` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `meal_package_id` bigint(20) unsigned NOT NULL,
  `delivery_date` date NOT NULL,
  `menu_item_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `meal_package_days_meal_package_id_delivery_date_unique` (`meal_package_id`,`delivery_date`),
  KEY `meal_package_days_menu_item_id_foreign` (`menu_item_id`),
  CONSTRAINT `meal_package_days_meal_package_id_foreign` FOREIGN KEY (`meal_package_id`) REFERENCES `meal_packages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `meal_package_days_menu_item_id_foreign` FOREIGN KEY (`menu_item_id`) REFERENCES `menu_items` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=121 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `meal_package_days` WRITE;
/*!40000 ALTER TABLE `meal_package_days` DISABLE KEYS */;
INSERT INTO `meal_package_days` (`id`, `meal_package_id`, `delivery_date`, `menu_item_id`, `created_at`, `updated_at`) VALUES (1,1,'2026-07-19',1,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(2,1,'2026-07-20',2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(3,1,'2026-07-21',3,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(4,1,'2026-07-22',4,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(5,1,'2026-07-23',5,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(6,1,'2026-07-24',1,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(7,1,'2026-07-25',2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(8,1,'2026-07-26',3,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(9,1,'2026-07-27',4,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(10,1,'2026-07-28',5,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(11,1,'2026-07-29',1,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(12,1,'2026-07-30',2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(13,1,'2026-07-31',3,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(14,1,'2026-08-01',4,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(15,1,'2026-08-02',5,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(16,1,'2026-08-03',1,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(17,1,'2026-08-04',2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(18,1,'2026-08-05',3,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(19,1,'2026-08-06',4,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(20,1,'2026-08-07',5,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(21,1,'2026-08-08',1,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(22,1,'2026-08-09',2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(23,1,'2026-08-10',3,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(24,1,'2026-08-11',4,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(25,1,'2026-08-12',5,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(26,1,'2026-08-13',1,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(27,1,'2026-08-14',2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(28,1,'2026-08-15',3,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(29,1,'2026-08-16',4,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(30,1,'2026-08-17',5,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(31,2,'2026-07-19',1,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(32,2,'2026-07-20',2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(33,2,'2026-07-21',3,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(34,2,'2026-07-22',4,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(35,2,'2026-07-23',5,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(36,2,'2026-07-24',1,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(37,2,'2026-07-25',2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(38,2,'2026-07-26',3,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(39,2,'2026-07-27',4,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(40,2,'2026-07-28',5,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(41,2,'2026-07-29',1,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(42,2,'2026-07-30',2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(43,2,'2026-07-31',3,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(44,2,'2026-08-01',4,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(45,2,'2026-08-02',5,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(46,2,'2026-08-03',1,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(47,2,'2026-08-04',2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(48,2,'2026-08-05',3,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(49,2,'2026-08-06',4,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(50,2,'2026-08-07',5,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(51,2,'2026-08-08',1,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(52,2,'2026-08-09',2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(53,2,'2026-08-10',3,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(54,2,'2026-08-11',4,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(55,2,'2026-08-12',5,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(56,2,'2026-08-13',1,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(57,2,'2026-08-14',2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(58,2,'2026-08-15',3,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(59,2,'2026-08-16',4,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(60,2,'2026-08-17',5,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(61,3,'2026-07-19',1,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(62,3,'2026-07-20',2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(63,3,'2026-07-21',3,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(64,3,'2026-07-22',4,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(65,3,'2026-07-23',5,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(66,3,'2026-07-24',1,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(67,3,'2026-07-25',2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(68,3,'2026-07-26',3,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(69,3,'2026-07-27',4,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(70,3,'2026-07-28',5,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(71,3,'2026-07-29',1,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(72,3,'2026-07-30',2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(73,3,'2026-07-31',3,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(74,3,'2026-08-01',4,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(75,3,'2026-08-02',5,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(76,3,'2026-08-03',1,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(77,3,'2026-08-04',2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(78,3,'2026-08-05',3,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(79,3,'2026-08-06',4,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(80,3,'2026-08-07',5,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(81,3,'2026-08-08',1,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(82,3,'2026-08-09',2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(83,3,'2026-08-10',3,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(84,3,'2026-08-11',4,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(85,3,'2026-08-12',5,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(86,3,'2026-08-13',1,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(87,3,'2026-08-14',2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(88,3,'2026-08-15',3,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(89,3,'2026-08-16',4,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(90,3,'2026-08-17',5,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(91,4,'2026-07-19',1,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(92,4,'2026-07-20',2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(93,4,'2026-07-21',3,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(94,4,'2026-07-22',5,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(95,4,'2026-07-23',1,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(96,4,'2026-07-24',2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(97,4,'2026-07-25',3,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(98,4,'2026-07-26',5,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(99,4,'2026-07-27',1,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(100,4,'2026-07-28',2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(101,4,'2026-07-29',3,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(102,4,'2026-07-30',5,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(103,4,'2026-07-31',1,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(104,4,'2026-08-01',2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(105,4,'2026-08-02',3,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(106,4,'2026-08-03',5,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(107,4,'2026-08-04',1,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(108,4,'2026-08-05',2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(109,4,'2026-08-06',3,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(110,4,'2026-08-07',5,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(111,4,'2026-08-08',1,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(112,4,'2026-08-09',2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(113,4,'2026-08-10',3,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(114,4,'2026-08-11',5,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(115,4,'2026-08-12',1,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(116,4,'2026-08-13',2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(117,4,'2026-08-14',3,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(118,4,'2026-08-15',5,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(119,4,'2026-08-16',1,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(120,4,'2026-08-17',2,'2026-07-18 10:05:17','2026-07-18 10:05:17');
/*!40000 ALTER TABLE `meal_package_days` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `meal_packages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `meal_packages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `summary` text DEFAULT NULL,
  `price_per_day` int(10) unsigned NOT NULL,
  `diet_tag` varchar(255) NOT NULL DEFAULT 'classic',
  `duration_days` smallint(5) unsigned NOT NULL DEFAULT 30,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `thumbnail` varchar(255) DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'draft',
  `display_order` int(11) NOT NULL DEFAULT 0,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `meal_packages_created_by_foreign` (`created_by`),
  KEY `meal_packages_updated_by_foreign` (`updated_by`),
  CONSTRAINT `meal_packages_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `meal_packages_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `meal_packages` WRITE;
/*!40000 ALTER TABLE `meal_packages` DISABLE KEYS */;
INSERT INTO `meal_packages` (`id`, `name`, `summary`, `price_per_day`, `diet_tag`, `duration_days`, `start_date`, `end_date`, `thumbnail`, `status`, `display_order`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES (1,'৳79 / day · Classic','Affordable 30-day office lunch plan with a rotating Middo thali each weekday.',79,'classic',30,'2026-07-19','2026-08-17','img/menu/menu-1.jpg','published',1,1,1,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(2,'৳150 / day · Standard','Balanced month-long package — fuller portions and a wider daily rotation.',150,'classic',30,'2026-07-19','2026-08-17','img/menu/menu-1.jpg','published',2,1,1,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(3,'৳200 / day · Premium','Premium 30-day plan featuring Middo’s richer thalis for executive teams.',200,'protein',30,'2026-07-19','2026-08-17','img/menu/menu-1.jpg','published',3,1,1,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(4,'Vegetarian · 30 days','Vegetarian-focused month package. Ideal when the office wants meat-free lunches.',120,'vegetarian',30,'2026-07-19','2026-08-17','img/menu/menu-1.jpg','published',4,1,1,'2026-07-18 10:05:17','2026-07-18 10:05:17');
/*!40000 ALTER TABLE `meal_packages` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `menu_item_meal_item`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `menu_item_meal_item` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `menu_item_id` bigint(20) unsigned NOT NULL,
  `meal_item_id` bigint(20) unsigned NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `menu_item_meal_item_menu_item_id_meal_item_id_unique` (`menu_item_id`,`meal_item_id`),
  KEY `menu_item_meal_item_meal_item_id_foreign` (`meal_item_id`),
  CONSTRAINT `menu_item_meal_item_meal_item_id_foreign` FOREIGN KEY (`meal_item_id`) REFERENCES `meal_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `menu_item_meal_item_menu_item_id_foreign` FOREIGN KEY (`menu_item_id`) REFERENCES `menu_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `menu_item_meal_item` WRITE;
/*!40000 ALTER TABLE `menu_item_meal_item` DISABLE KEYS */;
INSERT INTO `menu_item_meal_item` (`id`, `menu_item_id`, `meal_item_id`, `sort_order`, `created_at`, `updated_at`) VALUES (1,1,1,1,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(2,1,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(3,1,3,3,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(4,1,4,4,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(5,1,7,5,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(6,2,1,1,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(7,2,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(8,2,3,3,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(9,2,4,4,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(10,2,7,5,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(11,2,9,6,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(12,3,1,1,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(13,3,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(14,3,3,3,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(15,3,4,4,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(16,3,6,5,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(17,3,7,6,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(18,3,9,7,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(19,4,1,1,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(20,4,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(21,4,3,3,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(22,4,4,4,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(23,4,5,5,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(24,5,1,1,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(25,5,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(26,5,3,3,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(27,5,4,4,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(28,5,8,5,'2026-07-18 10:05:17','2026-07-18 10:05:17');
/*!40000 ALTER TABLE `menu_item_meal_item` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `menu_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `menu_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `summary` text DEFAULT NULL,
  `price` int(11) NOT NULL DEFAULT 0,
  `thumbnail` varchar(255) DEFAULT NULL,
  `kitchen_commission` int(11) NOT NULL DEFAULT 0,
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `is_homepage` tinyint(1) NOT NULL DEFAULT 0,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `meals_cost` int(11) NOT NULL DEFAULT 0,
  `other_cost` int(11) NOT NULL DEFAULT 0,
  `note` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `menu_items` WRITE;
/*!40000 ALTER TABLE `menu_items` DISABLE KEYS */;
INSERT INTO `menu_items` (`id`, `name`, `summary`, `price`, `thumbnail`, `kitchen_commission`, `is_featured`, `is_homepage`, `display_order`, `meals_cost`, `other_cost`, `note`, `created_at`, `updated_at`) VALUES (1,'Vegetable Khichdi Thali','Comforting khichdi with mutton curry, mashed vegetables, and fresh kachumber salad.',350,'img/menu/menu-1.jpg',35,1,1,1,129,20,'Seeded menu packaging / misc costs','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(2,'Traditional Vegetarian Thali','Basmati rice with yellow dal, potato sabzi, mixed vegetable curry, and green bean stir-fry.',320,'img/menu/menu-2.jpg',32,1,1,2,148,20,'Seeded menu packaging / misc costs','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(3,'Royal Indian Thali','A grand platter with meat curry, dal tadka, mixed vegetable curries, and spiced potatoes.',450,'img/menu/menu-3.jpg',45,1,1,3,255,20,'Seeded menu packaging / misc costs','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(4,'Chicken Curry Thali','Steamed rice with rich chicken curry, mixed sabzi, yellow dal, and spiced potatoes.',420,'img/menu/menu-4.jpg',42,1,0,4,184,20,'Seeded menu packaging / misc costs','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(5,'Bengali Fish Thali','Fish curry with steamed rice, mixed vegetables, yellow dal, and spiced mashed potatoes.',400,'img/menu/menu-5.jpg',40,1,0,5,187,20,'Seeded menu packaging / misc costs','2026-07-18 10:05:17','2026-07-18 10:05:17');
/*!40000 ALTER TABLE `menu_items` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `middo_box_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `middo_box_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint(20) unsigned DEFAULT NULL,
  `middo_box_id` bigint(20) unsigned NOT NULL,
  `custody_status` enum('warehouse','assigned_at_kitchen','in_transit','with_customer','collected_by_rider','returned_and_washed') NOT NULL DEFAULT 'warehouse',
  `log_action` enum('dispatched_to_kitchen','received_at_kitchen','picked_by_delivery_from_kitchen','delivered_to_corporate','picked_from_corporate_by_delivery','returned_to_kitchen','returned_to_warehouse','registered_at_warehouse') NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `middo_box_logs_order_id_custody_status_index` (`order_id`,`custody_status`),
  KEY `middo_box_logs_middo_box_id_index` (`middo_box_id`),
  KEY `middo_box_logs_log_action_index` (`log_action`),
  CONSTRAINT `middo_box_logs_middo_box_id_foreign` FOREIGN KEY (`middo_box_id`) REFERENCES `middo_boxes` (`id`),
  CONSTRAINT `middo_box_logs_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=73 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `middo_box_logs` WRITE;
/*!40000 ALTER TABLE `middo_box_logs` DISABLE KEYS */;
INSERT INTO `middo_box_logs` (`id`, `order_id`, `middo_box_id`, `custody_status`, `log_action`, `created_at`, `updated_at`) VALUES (1,NULL,1,'warehouse','registered_at_warehouse','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(2,NULL,2,'warehouse','registered_at_warehouse','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(3,NULL,3,'warehouse','registered_at_warehouse','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(4,NULL,4,'warehouse','registered_at_warehouse','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(5,NULL,5,'warehouse','registered_at_warehouse','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(6,NULL,6,'warehouse','registered_at_warehouse','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(7,NULL,7,'warehouse','registered_at_warehouse','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(8,NULL,8,'warehouse','registered_at_warehouse','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(9,NULL,9,'warehouse','registered_at_warehouse','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(10,NULL,10,'warehouse','registered_at_warehouse','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(11,NULL,11,'warehouse','registered_at_warehouse','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(12,NULL,12,'warehouse','registered_at_warehouse','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(13,NULL,13,'warehouse','registered_at_warehouse','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(14,NULL,14,'warehouse','registered_at_warehouse','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(15,NULL,15,'warehouse','registered_at_warehouse','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(16,NULL,16,'warehouse','registered_at_warehouse','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(17,NULL,17,'warehouse','registered_at_warehouse','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(18,NULL,18,'warehouse','registered_at_warehouse','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(19,NULL,19,'warehouse','registered_at_warehouse','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(20,NULL,20,'warehouse','registered_at_warehouse','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(21,NULL,21,'assigned_at_kitchen','received_at_kitchen','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(22,NULL,22,'assigned_at_kitchen','received_at_kitchen','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(23,NULL,23,'assigned_at_kitchen','received_at_kitchen','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(24,NULL,24,'assigned_at_kitchen','received_at_kitchen','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(25,NULL,25,'assigned_at_kitchen','received_at_kitchen','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(26,NULL,26,'assigned_at_kitchen','received_at_kitchen','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(27,NULL,27,'assigned_at_kitchen','received_at_kitchen','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(28,NULL,28,'assigned_at_kitchen','received_at_kitchen','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(29,NULL,29,'assigned_at_kitchen','received_at_kitchen','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(30,NULL,30,'assigned_at_kitchen','received_at_kitchen','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(31,NULL,31,'assigned_at_kitchen','received_at_kitchen','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(32,NULL,32,'assigned_at_kitchen','received_at_kitchen','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(33,NULL,33,'in_transit','dispatched_to_kitchen','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(34,NULL,33,'in_transit','dispatched_to_kitchen','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(35,NULL,34,'in_transit','dispatched_to_kitchen','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(36,NULL,34,'in_transit','dispatched_to_kitchen','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(37,NULL,35,'in_transit','dispatched_to_kitchen','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(38,NULL,35,'in_transit','dispatched_to_kitchen','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(39,NULL,36,'in_transit','dispatched_to_kitchen','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(40,NULL,36,'in_transit','dispatched_to_kitchen','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(41,NULL,37,'assigned_at_kitchen','received_at_kitchen','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(42,NULL,38,'assigned_at_kitchen','received_at_kitchen','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(43,NULL,39,'in_transit','dispatched_to_kitchen','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(44,NULL,39,'in_transit','dispatched_to_kitchen','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(45,NULL,40,'in_transit','dispatched_to_kitchen','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(46,NULL,40,'in_transit','dispatched_to_kitchen','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(47,NULL,41,'assigned_at_kitchen','received_at_kitchen','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(48,NULL,42,'assigned_at_kitchen','received_at_kitchen','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(49,NULL,43,'assigned_at_kitchen','received_at_kitchen','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(50,NULL,44,'assigned_at_kitchen','received_at_kitchen','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(51,NULL,45,'assigned_at_kitchen','received_at_kitchen','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(52,NULL,46,'assigned_at_kitchen','received_at_kitchen','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(53,NULL,47,'warehouse','registered_at_warehouse','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(54,286,47,'in_transit','picked_by_delivery_from_kitchen','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(55,NULL,48,'warehouse','registered_at_warehouse','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(56,286,48,'in_transit','picked_by_delivery_from_kitchen','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(57,NULL,49,'warehouse','registered_at_warehouse','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(58,287,49,'in_transit','picked_by_delivery_from_kitchen','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(59,NULL,50,'warehouse','registered_at_warehouse','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(60,287,50,'in_transit','picked_by_delivery_from_kitchen','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(61,NULL,51,'warehouse','registered_at_warehouse','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(62,288,51,'with_customer','delivered_to_corporate','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(63,NULL,52,'warehouse','registered_at_warehouse','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(64,288,52,'with_customer','delivered_to_corporate','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(65,NULL,53,'warehouse','registered_at_warehouse','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(66,289,53,'with_customer','delivered_to_corporate','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(67,NULL,54,'warehouse','registered_at_warehouse','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(68,289,54,'with_customer','delivered_to_corporate','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(69,NULL,55,'warehouse','registered_at_warehouse','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(70,290,55,'with_customer','delivered_to_corporate','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(71,NULL,56,'warehouse','registered_at_warehouse','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(72,290,56,'with_customer','delivered_to_corporate','2026-07-18 10:05:17','2026-07-18 10:05:17');
/*!40000 ALTER TABLE `middo_box_logs` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `middo_boxes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `middo_boxes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `qr_code_id` varchar(255) NOT NULL,
  `box_model_type` varchar(255) NOT NULL DEFAULT 'standard_insulated',
  `held_by_user_id` bigint(20) unsigned DEFAULT NULL,
  `kitchen_id` bigint(20) unsigned DEFAULT NULL,
  `asset_status` enum('at_middo_warehouse','active','maintenance','damaged','lost','retired') NOT NULL DEFAULT 'at_middo_warehouse',
  `ready_for_pickup` tinyint(1) NOT NULL DEFAULT 0,
  `ready_for_pickup_at` timestamp NULL DEFAULT NULL,
  `total_uses_count` int(11) NOT NULL DEFAULT 0,
  `last_scanned_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `middo_boxes_qr_code_id_unique` (`qr_code_id`),
  KEY `middo_boxes_qr_code_id_index` (`qr_code_id`),
  KEY `middo_boxes_held_by_user_id_index` (`held_by_user_id`),
  KEY `middo_boxes_kitchen_id_index` (`kitchen_id`),
  CONSTRAINT `middo_boxes_held_by_user_id_foreign` FOREIGN KEY (`held_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `middo_boxes_kitchen_id_foreign` FOREIGN KEY (`kitchen_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=57 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `middo_boxes` WRITE;
/*!40000 ALTER TABLE `middo_boxes` DISABLE KEYS */;
INSERT INTO `middo_boxes` (`id`, `qr_code_id`, `box_model_type`, `held_by_user_id`, `kitchen_id`, `asset_status`, `ready_for_pickup`, `ready_for_pickup_at`, `total_uses_count`, `last_scanned_at`, `created_at`, `updated_at`) VALUES (1,'MB-000001','standard_insulated',NULL,NULL,'at_middo_warehouse',0,NULL,0,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(2,'MB-000002','standard_insulated',NULL,NULL,'at_middo_warehouse',0,NULL,0,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(3,'MB-000003','standard_insulated',NULL,NULL,'at_middo_warehouse',0,NULL,0,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(4,'MB-000004','standard_insulated',NULL,NULL,'at_middo_warehouse',0,NULL,0,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(5,'MB-000005','standard_insulated',NULL,NULL,'at_middo_warehouse',0,NULL,0,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(6,'MB-000006','standard_insulated',NULL,NULL,'at_middo_warehouse',0,NULL,0,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(7,'MB-000007','standard_insulated',NULL,NULL,'at_middo_warehouse',0,NULL,0,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(8,'MB-000008','standard_insulated',NULL,NULL,'at_middo_warehouse',0,NULL,0,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(9,'MB-000009','standard_insulated',NULL,NULL,'at_middo_warehouse',0,NULL,0,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(10,'MB-000010','standard_insulated',NULL,NULL,'at_middo_warehouse',0,NULL,0,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(11,'MB-000011','standard_insulated',NULL,NULL,'at_middo_warehouse',0,NULL,0,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(12,'MB-000012','standard_insulated',NULL,NULL,'at_middo_warehouse',0,NULL,0,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(13,'MB-000013','standard_insulated',NULL,NULL,'at_middo_warehouse',0,NULL,0,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(14,'MB-000014','standard_insulated',NULL,NULL,'at_middo_warehouse',0,NULL,0,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(15,'MB-000015','standard_insulated',NULL,NULL,'at_middo_warehouse',0,NULL,0,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(16,'MB-000016','standard_insulated',NULL,NULL,'at_middo_warehouse',0,NULL,0,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(17,'MB-000017','standard_insulated',NULL,NULL,'at_middo_warehouse',0,NULL,0,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(18,'MB-000018','standard_insulated',NULL,NULL,'at_middo_warehouse',0,NULL,0,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(19,'MB-000019','standard_insulated',NULL,NULL,'at_middo_warehouse',0,NULL,0,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(20,'MB-000020','standard_insulated',NULL,NULL,'at_middo_warehouse',0,NULL,0,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(21,'MB-KD00001','standard_insulated',3,3,'active',0,NULL,0,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(22,'MB-KD00002','standard_insulated',3,3,'active',0,NULL,0,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(23,'MB-KD00003','standard_insulated',3,3,'active',0,NULL,0,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(24,'MB-KD00004','standard_insulated',3,3,'active',0,NULL,0,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(25,'MB-KD00005','standard_insulated',3,3,'active',0,NULL,0,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(26,'MB-KD00006','standard_insulated',3,3,'active',0,NULL,0,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(27,'MB-KD00007','standard_insulated',3,3,'active',0,NULL,0,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(28,'MB-KD00008','standard_insulated',3,3,'active',0,NULL,0,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(29,'MB-KD00009','standard_insulated',3,3,'active',0,NULL,0,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(30,'MB-KD00010','standard_insulated',3,3,'active',0,NULL,0,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(31,'MB-KD00011','standard_insulated',3,3,'active',0,NULL,0,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(32,'MB-KD00012','standard_insulated',3,3,'active',0,NULL,0,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(33,'MB-KD00013','standard_insulated',15,3,'active',0,NULL,0,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(34,'MB-KD00014','standard_insulated',15,3,'active',0,NULL,0,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(35,'MB-KD00015','standard_insulated',15,3,'active',0,NULL,0,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(36,'MB-KD00016','standard_insulated',15,3,'active',0,NULL,0,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(37,'MB-KD00017','standard_insulated',4,4,'active',0,NULL,0,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(38,'MB-KD00018','standard_insulated',4,4,'active',0,NULL,0,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(39,'MB-KD00019','standard_insulated',16,4,'active',0,NULL,0,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(40,'MB-KD00020','standard_insulated',16,4,'active',0,NULL,0,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(41,'MB-KD00021','standard_insulated',3,3,'active',0,NULL,0,'2026-07-18 10:05:17','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(42,'MB-KD00022','standard_insulated',3,3,'active',0,NULL,0,'2026-07-18 10:05:17','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(43,'MB-KD00023','standard_insulated',3,3,'active',0,NULL,0,'2026-07-18 10:05:17','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(44,'MB-KD00024','standard_insulated',3,3,'active',0,NULL,0,'2026-07-18 10:05:17','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(45,'MB-KD00025','standard_insulated',3,3,'active',0,NULL,0,'2026-07-18 10:05:17','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(46,'MB-KD00026','standard_insulated',3,3,'active',0,NULL,0,'2026-07-18 10:05:17','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(47,'MB-KD00027','standard_insulated',15,NULL,'active',0,NULL,1,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(48,'MB-KD00028','standard_insulated',15,NULL,'active',0,NULL,1,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(49,'MB-KD00029','standard_insulated',15,NULL,'active',0,NULL,1,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(50,'MB-KD00030','standard_insulated',15,NULL,'active',0,NULL,1,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(51,'MB-KD00031','standard_insulated',2,NULL,'active',0,NULL,1,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(52,'MB-KD00032','standard_insulated',2,NULL,'active',0,NULL,1,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(53,'MB-KD00033','standard_insulated',2,NULL,'active',0,NULL,1,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(54,'MB-KD00034','standard_insulated',2,NULL,'active',0,NULL,1,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(55,'MB-KD00035','standard_insulated',2,NULL,'active',0,NULL,1,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(56,'MB-KD00036','standard_insulated',2,NULL,'active',0,NULL,1,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17');
/*!40000 ALTER TABLE `middo_boxes` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `middo_cash_ledger`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `middo_cash_ledger` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `amount` int(11) NOT NULL,
  `balance_after` int(11) NOT NULL,
  `entry_type` varchar(64) NOT NULL,
  `reference_type` varchar(64) DEFAULT NULL,
  `reference_id` bigint(20) unsigned DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `middo_cash_ledger_created_by_foreign` (`created_by`),
  KEY `middo_cash_ledger_reference_type_reference_id_index` (`reference_type`,`reference_id`),
  KEY `middo_cash_ledger_entry_type_index` (`entry_type`),
  CONSTRAINT `middo_cash_ledger_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `middo_cash_ledger` WRITE;
/*!40000 ALTER TABLE `middo_cash_ledger` DISABLE KEYS */;
/*!40000 ALTER TABLE `middo_cash_ledger` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1,'0001_01_01_000001_create_cache_table',1),
(2,'0001_01_01_000002_create_jobs_table',1),
(3,'2026_06_12_094913_create_cities_table',1),
(4,'2026_06_12_094914_create_areas_table',1),
(5,'2026_06_12_094923_create_rbac_tables',1),
(6,'2026_06_12_094923_create_users_table',1),
(7,'2026_06_16_102822_create_contact_form_table',1),
(8,'2026_06_18_194937_create_navs_table',1),
(9,'2026_06_20_134949_create_menu_items_table',1),
(10,'2026_06_23_000000_add_kitchen_commission_to_menu_items_table',1),
(11,'2026_06_25_185945_create_orders_table',1),
(12,'2026_06_25_191212_create_middo_boxes_table',1),
(13,'2026_06_25_191559_create_middo_box_logs_table',1),
(14,'2026_07_07_131047_create_order_logs_table',1),
(15,'2026_07_07_135136_create_order_complaints_table',1),
(16,'2026_07_07_151115_create_meal_order_groups_table',1),
(17,'2026_07_07_153034_restructure_order_groups_tables',1),
(18,'2026_07_07_173500_add_registered_at_warehouse_to_middo_box_logs',1),
(19,'2026_07_14_110000_add_cost_fields_to_menu_items_table',1),
(20,'2026_07_14_110100_create_meal_items_and_recipes_tables',1),
(21,'2026_07_15_080000_add_kitchen_dashboard_navs',1),
(22,'2026_07_15_081500_add_kitchen_id_to_middo_boxes_table',1),
(23,'2026_07_15_081600_add_kitchen_middo_boxes_navs',1),
(24,'2026_07_15_083000_add_delivery_dispatch_fields',1),
(25,'2026_07_15_083100_add_delivery_dashboard_navs',1),
(26,'2026_07_15_084000_expand_order_status_for_delivery',1),
(27,'2026_07_15_084100_add_delivery_delivered_orders_nav',1),
(28,'2026_07_16_193711_create_personal_access_tokens_table',1),
(29,'2026_07_17_071500_create_device_tokens_table',1),
(30,'2026_07_17_180000_add_packed_to_order_status',1),
(31,'2026_07_17_180100_add_area_id_to_orders_and_groups',1),
(32,'2026_07_17_180200_create_cash_handover_and_ledger_tables',1),
(33,'2026_07_17_180300_add_cash_handover_navs',1),
(34,'2026_07_17_190000_add_prepayment_fields_to_orders_table',1),
(35,'2026_07_17_194000_add_prepaid_and_cash_collected_to_orders',1),
(36,'2026_07_17_200000_add_company_name_to_users_table',1),
(37,'2026_07_17_200100_create_site_pages_table',1),
(38,'2026_07_17_210000_add_wallet_transactions_and_box_pickup_flag',1),
(39,'2026_07_18_100000_create_meal_packages_tables',1),
(40,'2026_07_18_100100_add_meal_packages_navs',1);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `navs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `navs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `route_name` varchar(255) DEFAULT NULL,
  `icon` varchar(255) DEFAULT NULL,
  `parent_id` bigint(20) unsigned DEFAULT NULL,
  `order` int(11) NOT NULL DEFAULT 0,
  `role_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `navs_parent_id_foreign` (`parent_id`),
  KEY `navs_role_id_foreign` (`role_id`),
  CONSTRAINT `navs_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `navs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `navs_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=47 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `navs` WRITE;
/*!40000 ALTER TABLE `navs` DISABLE KEYS */;
INSERT INTO `navs` (`id`, `title`, `route_name`, `icon`, `parent_id`, `order`, `role_id`, `created_at`, `updated_at`) VALUES (1,'Dashboard','admin.dashboard',NULL,NULL,1,1,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(2,'Kitchens',NULL,'👨‍🍳',NULL,2,1,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(3,'Active Kitchens','admin.kitchens.active',NULL,2,1,1,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(4,'Onboarding','admin.kitchens.onboarding',NULL,2,2,1,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(5,'Menu',NULL,'🍽️',NULL,3,1,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(6,'Menu items','admin.menu.index',NULL,5,1,1,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(7,'Meal items','admin.meal-items.index',NULL,5,2,1,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(8,'Packages','admin.packages.index',NULL,5,3,1,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(9,'Orders',NULL,'📦',NULL,4,1,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(10,'Active orders','admin.orders.active',NULL,9,1,1,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(11,'Order History','admin.orders.history',NULL,9,2,1,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(12,'Search Order','admin.orders.search',NULL,9,3,1,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(13,'Users',NULL,'👥',NULL,5,1,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(14,'Admins','admin.users.admin',NULL,13,1,1,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(15,'Operations','admin.users.operation',NULL,13,2,1,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(16,'Kitchens','admin.users.kitchen',NULL,13,3,1,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(17,'Corporates','admin.users.corporate',NULL,13,4,1,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(18,'Delivery','admin.users.delivery',NULL,13,5,1,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(19,'System Navs','admin.navrole.index','⚙️',NULL,6,1,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(20,'Dashboard','operation.dashboard',NULL,NULL,1,5,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(21,'Kitchens','operation.kitchens.index','👨‍🍳',NULL,2,5,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(22,'Menu',NULL,'🍽️',NULL,3,5,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(23,'Menu items','operation.menu.index',NULL,22,1,5,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(24,'Meal items','operation.meal-items.index',NULL,22,2,5,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(25,'Packages','operation.packages.index',NULL,22,3,5,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(26,'Orders',NULL,'📦',NULL,4,5,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(27,'Active orders','operation.orders.active',NULL,26,1,5,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(28,'Order History','operation.orders.history',NULL,26,2,5,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(29,'Search Order','operation.orders.search',NULL,26,3,5,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(30,'Middo Boxes','operation.middo-boxes.index','📦',NULL,5,5,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(31,'Dashboard','corporates.dashboard',NULL,NULL,1,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(32,'Packages','corporates.packages.index',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(33,'Scheduled Orders','corporates.orders.scheduled',NULL,NULL,3,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(34,'Order History','corporates.orders.history',NULL,NULL,4,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(35,'Dashboard','kitchen.dashboard',NULL,NULL,1,3,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(36,'Middo order groups','kitchen.order-groups.middo',NULL,NULL,2,3,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(37,'My active orders','kitchen.orders.active',NULL,NULL,3,3,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(38,'My orders this month','kitchen.orders.this-month',NULL,NULL,4,3,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(39,'Last 3 months','kitchen.orders.last-three-months',NULL,NULL,5,3,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(40,'Middo boxes',NULL,'📦',NULL,6,3,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(41,'Boxes at kitchen','kitchen.middo-boxes.at-kitchen',NULL,40,1,3,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(42,'Incoming','kitchen.middo-boxes.incoming',NULL,40,2,3,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(43,'Dashboard','delivery.dashboard',NULL,NULL,1,4,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(44,'Kitchen dispatches','delivery.kitchen-dispatches',NULL,NULL,2,4,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(45,'Middo boxes pending run','delivery.middo-boxes.pending-run',NULL,NULL,3,4,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(46,'Delivered orders','delivery.orders.delivered',NULL,NULL,4,4,'2026-07-18 10:05:17','2026-07-18 10:05:17');
/*!40000 ALTER TABLE `navs` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `order_complaints`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_complaints` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint(20) unsigned NOT NULL,
  `parent_id` bigint(20) unsigned DEFAULT NULL,
  `is_reply` tinyint(1) NOT NULL DEFAULT 0,
  `category` enum('delivery','food_quality','payment','other') DEFAULT NULL,
  `message` text NOT NULL,
  `attachment` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `order_complaints_parent_id_foreign` (`parent_id`),
  KEY `order_complaints_created_by_foreign` (`created_by`),
  KEY `order_complaints_updated_by_foreign` (`updated_by`),
  KEY `order_complaints_order_id_parent_id_index` (`order_id`,`parent_id`),
  KEY `order_complaints_is_reply_index` (`is_reply`),
  CONSTRAINT `order_complaints_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `order_complaints_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `order_complaints_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `order_complaints` (`id`) ON DELETE CASCADE,
  CONSTRAINT `order_complaints_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `order_complaints` WRITE;
/*!40000 ALTER TABLE `order_complaints` DISABLE KEYS */;
INSERT INTO `order_complaints` (`id`, `order_id`, `parent_id`, `is_reply`, `category`, `message`, `attachment`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES (1,1,NULL,0,'food_quality','Two meals arrived cold and the rice portion was smaller than usual. Please look into this.',NULL,2,2,'2026-07-15 14:20:00','2026-07-15 14:20:00'),
(2,1,1,1,NULL,'Sorry about that. We have flagged this with the kitchen and added a ৳200 credit to your account.',NULL,1,1,'2026-07-16 10:15:00','2026-07-16 10:15:00'),
(3,1,1,1,NULL,'Please confirm if the credit reflects on your dashboard. We will monitor the next delivery closely.',NULL,1,1,'2026-07-17 16:40:00','2026-07-17 16:40:00'),
(4,261,NULL,0,'payment','Refund for this cancelled order has not appeared in my balance yet. Order was cancelled yesterday.',NULL,2,2,'2026-07-18 08:05:17','2026-07-18 08:05:17'),
(5,11,NULL,0,'delivery','Can you confirm the delivery window for today? Our team lunch starts at 12:15 PM sharp.',NULL,2,2,'2026-07-18 13:05:17','2026-07-18 13:05:17'),
(6,11,5,1,NULL,'Your order is scheduled for the 12:00 PM window. The rider is expected between 11:30 AM and 11:45 AM.',NULL,1,1,'2026-07-18 14:05:17','2026-07-18 14:05:17');
/*!40000 ALTER TABLE `order_complaints` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `order_group_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_group_orders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_group_id` bigint(20) unsigned NOT NULL,
  `order_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_group_orders_order_id_unique` (`order_id`),
  KEY `order_group_orders_order_group_id_foreign` (`order_group_id`),
  CONSTRAINT `order_group_orders_order_group_id_foreign` FOREIGN KEY (`order_group_id`) REFERENCES `order_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `order_group_orders_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `order_group_orders` WRITE;
/*!40000 ALTER TABLE `order_group_orders` DISABLE KEYS */;
INSERT INTO `order_group_orders` (`id`, `order_group_id`, `order_id`, `created_at`, `updated_at`) VALUES (1,1,269,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(2,2,270,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(3,3,271,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(4,4,272,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(5,5,273,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(6,6,274,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(7,7,275,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(8,8,276,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(9,9,277,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(10,10,278,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(11,11,279,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(12,12,280,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(13,13,281,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(14,14,282,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(15,15,283,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(16,16,284,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(17,17,285,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(18,18,286,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(19,19,287,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(20,20,288,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(21,21,289,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(22,22,290,'2026-07-18 10:05:17','2026-07-18 10:05:17');
/*!40000 ALTER TABLE `order_group_orders` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `order_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_groups` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `menu_id` bigint(20) unsigned NOT NULL,
  `area_id` bigint(20) unsigned DEFAULT NULL,
  `delivery_date` date NOT NULL,
  `kitchen_id` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `order_groups_menu_id_foreign` (`menu_id`),
  KEY `order_groups_kitchen_id_foreign` (`kitchen_id`),
  KEY `order_groups_created_by_foreign` (`created_by`),
  KEY `order_groups_updated_by_foreign` (`updated_by`),
  KEY `order_groups_delivery_date_menu_id_index` (`delivery_date`,`menu_id`),
  KEY `order_groups_name_index` (`name`),
  KEY `order_groups_area_id_foreign` (`area_id`),
  CONSTRAINT `order_groups_area_id_foreign` FOREIGN KEY (`area_id`) REFERENCES `areas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `order_groups_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `order_groups_kitchen_id_foreign` FOREIGN KEY (`kitchen_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `order_groups_menu_id_foreign` FOREIGN KEY (`menu_id`) REFERENCES `menu_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `order_groups_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `order_groups` WRITE;
/*!40000 ALTER TABLE `order_groups` DISABLE KEYS */;
INSERT INTO `order_groups` (`id`, `name`, `menu_id`, `area_id`, `delivery_date`, `kitchen_id`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES (1,'GRP-SEED-001',1,NULL,'2026-07-18',NULL,18,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(2,'GRP-SEED-002',2,NULL,'2026-07-18',NULL,18,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(3,'GRP-SEED-003',1,NULL,'2026-07-19',NULL,18,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(4,'GRP-SEED-004',3,NULL,'2026-07-19',NULL,18,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(5,'GRP-SEED-005',2,NULL,'2026-07-20',NULL,18,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(6,'GRP-SEED-006',1,NULL,'2026-07-20',NULL,18,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(7,'GRP-SEED-007',1,NULL,'2026-07-18',3,18,3,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(8,'GRP-SEED-008',2,NULL,'2026-07-18',3,18,3,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(9,'GRP-SEED-009',1,NULL,'2026-07-19',3,18,3,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(10,'GRP-SEED-010',3,NULL,'2026-07-19',3,18,3,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(11,'GRP-SEED-011',2,NULL,'2026-07-18',4,18,4,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(12,'GRP-SEED-012',1,NULL,'2026-07-11',3,18,3,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(13,'GRP-SEED-013',2,NULL,'2026-07-04',3,18,3,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(14,'GRP-SEED-014',3,NULL,'2026-06-23',3,18,3,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(15,'GRP-SEED-015',1,NULL,'2026-07-18',3,18,3,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(16,'GRP-SEED-016',1,NULL,'2026-07-18',3,18,3,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(17,'GRP-SEED-017',1,NULL,'2026-07-18',3,18,3,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(18,'GRP-SEED-018',2,NULL,'2026-07-18',3,18,15,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(19,'GRP-SEED-019',2,NULL,'2026-07-18',3,18,15,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(20,'GRP-SEED-020',1,NULL,'2026-07-11',3,18,15,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(21,'GRP-SEED-021',1,NULL,'2026-07-11',3,18,15,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(22,'GRP-SEED-022',3,NULL,'2026-07-04',3,18,15,'2026-07-18 10:05:17','2026-07-18 10:05:17');
/*!40000 ALTER TABLE `order_groups` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `order_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint(20) unsigned DEFAULT NULL,
  `event` varchar(50) NOT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `performed_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `order_logs_performed_by_foreign` (`performed_by`),
  KEY `order_logs_order_id_created_at_index` (`order_id`,`created_at`),
  KEY `order_logs_event_index` (`event`),
  CONSTRAINT `order_logs_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `order_logs_performed_by_foreign` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=624 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `order_logs` WRITE;
/*!40000 ALTER TABLE `order_logs` DISABLE KEYS */;
INSERT INTO `order_logs` (`id`, `order_id`, `event`, `metadata`, `performed_by`, `created_at`, `updated_at`) VALUES (1,1,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":1,\"quantity\":5,\"delivery_date\":\"2026-06-20\",\"delivery_time\":\"11:30 AM\",\"total_amount\":1750,\"address\":\"Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-06-13 10:15:00','2026-06-13 10:15:00'),
(2,1,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-06-13 10:35:00','2026-06-13 10:35:00'),
(3,1,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-06-19 17:30:00','2026-06-19 17:30:00'),
(4,1,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"processing\",\"to\":\"delivered\"}}}',2,'2026-06-20 11:30:00','2026-06-20 11:30:00'),
(5,2,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":2,\"quantity\":4,\"delivery_date\":\"2026-06-23\",\"delivery_time\":\"12:00 PM\",\"total_amount\":1280,\"address\":\"Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-06-16 10:15:00','2026-06-16 10:15:00'),
(6,2,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-06-16 10:35:00','2026-06-16 10:35:00'),
(7,2,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-06-22 17:30:00','2026-06-22 17:30:00'),
(8,2,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"processing\",\"to\":\"delivered\"}}}',2,'2026-06-23 12:00:00','2026-06-23 12:00:00'),
(9,3,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":3,\"quantity\":3,\"delivery_date\":\"2026-06-27\",\"delivery_time\":\"12:30 PM\",\"total_amount\":1350,\"address\":\"Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-06-20 10:15:00','2026-06-20 10:15:00'),
(10,3,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-06-20 10:35:00','2026-06-20 10:35:00'),
(11,3,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-06-26 17:30:00','2026-06-26 17:30:00'),
(12,3,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"processing\",\"to\":\"delivered\"}}}',2,'2026-06-27 12:30:00','2026-06-27 12:30:00'),
(13,4,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":4,\"quantity\":5,\"delivery_date\":\"2026-06-30\",\"delivery_time\":\"11:30 AM\",\"total_amount\":2100,\"address\":\"Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-06-23 10:15:00','2026-06-23 10:15:00'),
(14,4,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-06-23 10:35:00','2026-06-23 10:35:00'),
(15,4,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-06-29 17:30:00','2026-06-29 17:30:00'),
(16,4,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"processing\",\"to\":\"delivered\"}}}',2,'2026-06-30 11:30:00','2026-06-30 11:30:00'),
(17,5,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":5,\"quantity\":2,\"delivery_date\":\"2026-07-04\",\"delivery_time\":\"12:00 PM\",\"total_amount\":800,\"address\":\"Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-06-27 10:15:00','2026-06-27 10:15:00'),
(18,5,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-06-27 10:35:00','2026-06-27 10:35:00'),
(19,5,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-03 17:30:00','2026-07-03 17:30:00'),
(20,5,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"processing\",\"to\":\"delivered\"}}}',2,'2026-07-04 12:00:00','2026-07-04 12:00:00'),
(21,6,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":1,\"quantity\":4,\"delivery_date\":\"2026-07-08\",\"delivery_time\":\"12:30 PM\",\"total_amount\":1400,\"address\":\"Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-01 10:15:00','2026-07-01 10:15:00'),
(22,6,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-01 10:35:00','2026-07-01 10:35:00'),
(23,6,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-07 17:30:00','2026-07-07 17:30:00'),
(24,6,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"processing\",\"to\":\"delivered\"}}}',2,'2026-07-08 12:30:00','2026-07-08 12:30:00'),
(25,7,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":2,\"quantity\":5,\"delivery_date\":\"2026-07-11\",\"delivery_time\":\"11:30 AM\",\"total_amount\":1600,\"address\":\"Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-04 10:15:00','2026-07-04 10:15:00'),
(26,7,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-04 10:35:00','2026-07-04 10:35:00'),
(27,7,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-10 17:30:00','2026-07-10 17:30:00'),
(28,7,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"processing\",\"to\":\"delivered\"}}}',2,'2026-07-11 11:30:00','2026-07-11 11:30:00'),
(29,8,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":3,\"quantity\":3,\"delivery_date\":\"2026-07-14\",\"delivery_time\":\"12:00 PM\",\"total_amount\":1350,\"address\":\"Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-08 10:15:00','2026-07-08 10:15:00'),
(30,8,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-08 10:35:00','2026-07-08 10:35:00'),
(31,8,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-13 17:30:00','2026-07-13 17:30:00'),
(32,8,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"processing\",\"to\":\"delivered\"}}}',2,'2026-07-14 12:00:00','2026-07-14 12:00:00'),
(33,9,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":4,\"quantity\":4,\"delivery_date\":\"2026-07-16\",\"delivery_time\":\"12:30 PM\",\"total_amount\":1680,\"address\":\"Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-12 10:15:00','2026-07-12 10:15:00'),
(34,9,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-12 10:35:00','2026-07-12 10:35:00'),
(35,9,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-15 17:30:00','2026-07-15 17:30:00'),
(36,9,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"processing\",\"to\":\"delivered\"}}}',2,'2026-07-16 12:30:00','2026-07-16 12:30:00'),
(37,10,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":5,\"quantity\":2,\"delivery_date\":\"2026-07-17\",\"delivery_time\":\"11:30 AM\",\"total_amount\":800,\"address\":\"Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-14 10:15:00','2026-07-14 10:15:00'),
(38,10,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"cancelled\"}}}',2,'2026-07-16 18:00:00','2026-07-16 18:00:00'),
(39,10,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"paid\",\"to\":\"returned\"}}}',2,'2026-07-16 18:45:00','2026-07-16 18:45:00'),
(40,11,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":3,\"quantity\":3,\"delivery_date\":\"2026-07-18\",\"delivery_time\":\"11:30 AM\",\"total_amount\":1350,\"address\":\"Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-16 10:15:00','2026-07-16 10:15:00'),
(41,11,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-16 10:35:00','2026-07-16 10:35:00'),
(42,11,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-17 17:30:00','2026-07-17 17:30:00'),
(43,12,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":4,\"quantity\":5,\"delivery_date\":\"2026-07-19\",\"delivery_time\":\"12:00 PM\",\"total_amount\":2100,\"address\":\"Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-17 10:15:00','2026-07-17 10:15:00'),
(44,13,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":5,\"quantity\":4,\"delivery_date\":\"2026-07-20\",\"delivery_time\":\"12:30 PM\",\"total_amount\":1600,\"address\":\"Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-18 10:15:00','2026-07-18 10:15:00'),
(45,14,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":1,\"quantity\":2,\"delivery_date\":\"2026-07-22\",\"delivery_time\":\"11:30 AM\",\"total_amount\":700,\"address\":\"Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-20 10:15:00','2026-07-20 10:15:00'),
(46,15,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":2,\"quantity\":5,\"delivery_date\":\"2026-07-23\",\"delivery_time\":\"12:00 PM\",\"total_amount\":1600,\"address\":\"Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-21 10:15:00','2026-07-21 10:15:00'),
(47,16,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":3,\"quantity\":3,\"delivery_date\":\"2026-07-25\",\"delivery_time\":\"12:30 PM\",\"total_amount\":1350,\"address\":\"Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-23 10:15:00','2026-07-23 10:15:00'),
(48,17,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":4,\"quantity\":4,\"delivery_date\":\"2026-07-28\",\"delivery_time\":\"11:30 AM\",\"total_amount\":1680,\"address\":\"Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-26 10:15:00','2026-07-26 10:15:00'),
(49,18,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":5,\"quantity\":1,\"delivery_date\":\"2026-07-30\",\"delivery_time\":\"12:00 PM\",\"total_amount\":400,\"address\":\"Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-28 10:15:00','2026-07-28 10:15:00'),
(50,19,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":3,\"quantity\":5,\"delivery_date\":\"2026-07-19\",\"delivery_time\":\"12:30 PM\",\"total_amount\":2250,\"address\":\"Plot 12, Block C, Banani, Dhaka 1213\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-17 10:15:00','2026-07-17 10:15:00'),
(51,19,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-18 17:30:00','2026-07-18 17:30:00'),
(52,20,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":3,\"quantity\":1,\"delivery_date\":\"2026-07-21\",\"delivery_time\":\"11:30 AM\",\"total_amount\":450,\"address\":\"Plot 12, Block C, Banani, Dhaka 1213\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-19 10:15:00','2026-07-19 10:15:00'),
(53,20,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-20 17:30:00','2026-07-20 17:30:00'),
(54,21,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":1,\"quantity\":5,\"delivery_date\":\"2026-07-19\",\"delivery_time\":\"11:30 AM\",\"total_amount\":1750,\"address\":\"Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-17 10:15:00','2026-07-17 10:15:00'),
(55,22,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":5,\"quantity\":1,\"delivery_date\":\"2026-07-19\",\"delivery_time\":\"12:00 PM\",\"total_amount\":400,\"address\":\"Plot 12, Block C, Banani, Dhaka 1213\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-17 10:15:00','2026-07-17 10:15:00'),
(56,23,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":1,\"quantity\":1,\"delivery_date\":\"2026-07-21\",\"delivery_time\":\"12:30 PM\",\"total_amount\":350,\"address\":\"Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-19 10:15:00','2026-07-19 10:15:00'),
(57,24,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":1,\"quantity\":5,\"delivery_date\":\"2026-07-19\",\"delivery_time\":\"12:00 PM\",\"total_amount\":1750,\"address\":\"Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-17 10:15:00','2026-07-17 10:15:00'),
(58,25,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":3,\"quantity\":4,\"delivery_date\":\"2026-07-18\",\"delivery_time\":\"11:30 AM\",\"total_amount\":1800,\"address\":\"Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-16 10:15:00','2026-07-16 10:15:00'),
(59,25,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-16 10:35:00','2026-07-16 10:35:00'),
(60,26,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":2,\"quantity\":1,\"delivery_date\":\"2026-07-19\",\"delivery_time\":\"12:30 PM\",\"total_amount\":320,\"address\":\"88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-17 10:15:00','2026-07-17 10:15:00'),
(61,27,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":5,\"quantity\":5,\"delivery_date\":\"2026-07-21\",\"delivery_time\":\"11:30 AM\",\"total_amount\":2000,\"address\":\"88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-19 10:15:00','2026-07-19 10:15:00'),
(62,27,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-19 10:35:00','2026-07-19 10:35:00'),
(63,28,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":1,\"quantity\":3,\"delivery_date\":\"2026-07-20\",\"delivery_time\":\"11:30 AM\",\"total_amount\":1050,\"address\":\"Navana Tower, Gulshan-1, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-18 10:15:00','2026-07-18 10:15:00'),
(64,28,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-18 10:35:00','2026-07-18 10:35:00'),
(65,29,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":1,\"quantity\":1,\"delivery_date\":\"2026-07-18\",\"delivery_time\":\"12:00 PM\",\"total_amount\":350,\"address\":\"Navana Tower, Gulshan-1, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-16 10:15:00','2026-07-16 10:15:00'),
(66,30,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":2,\"quantity\":5,\"delivery_date\":\"2026-07-18\",\"delivery_time\":\"12:30 PM\",\"total_amount\":1600,\"address\":\"Plot 12, Block C, Banani, Dhaka 1213\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-16 10:15:00','2026-07-16 10:15:00'),
(67,30,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-16 10:35:00','2026-07-16 10:35:00'),
(68,31,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":1,\"quantity\":1,\"delivery_date\":\"2026-07-18\",\"delivery_time\":\"12:00 PM\",\"total_amount\":350,\"address\":\"Navana Tower, Gulshan-1, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-16 10:15:00','2026-07-16 10:15:00'),
(69,31,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-17 17:30:00','2026-07-17 17:30:00'),
(70,32,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":5,\"quantity\":1,\"delivery_date\":\"2026-07-18\",\"delivery_time\":\"12:30 PM\",\"total_amount\":400,\"address\":\"Navana Tower, Gulshan-1, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-16 10:15:00','2026-07-16 10:15:00'),
(71,33,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":1,\"quantity\":4,\"delivery_date\":\"2026-07-20\",\"delivery_time\":\"12:00 PM\",\"total_amount\":1400,\"address\":\"Navana Tower, Gulshan-1, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-18 10:15:00','2026-07-18 10:15:00'),
(72,33,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-18 10:35:00','2026-07-18 10:35:00'),
(73,34,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":1,\"quantity\":2,\"delivery_date\":\"2026-07-19\",\"delivery_time\":\"11:30 AM\",\"total_amount\":700,\"address\":\"Plot 12, Block C, Banani, Dhaka 1213\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-17 10:15:00','2026-07-17 10:15:00'),
(74,34,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-17 10:35:00','2026-07-17 10:35:00'),
(75,35,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":4,\"quantity\":2,\"delivery_date\":\"2026-07-18\",\"delivery_time\":\"11:30 AM\",\"total_amount\":840,\"address\":\"Plot 12, Block C, Banani, Dhaka 1213\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-16 10:15:00','2026-07-16 10:15:00'),
(76,35,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-17 17:30:00','2026-07-17 17:30:00'),
(77,36,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":5,\"quantity\":1,\"delivery_date\":\"2026-07-18\",\"delivery_time\":\"11:30 AM\",\"total_amount\":400,\"address\":\"Plot 12, Block C, Banani, Dhaka 1213\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-16 10:15:00','2026-07-16 10:15:00'),
(78,36,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-17 17:30:00','2026-07-17 17:30:00'),
(79,37,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":1,\"quantity\":3,\"delivery_date\":\"2026-07-19\",\"delivery_time\":\"12:00 PM\",\"total_amount\":1050,\"address\":\"Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-17 10:15:00','2026-07-17 10:15:00'),
(80,38,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":2,\"quantity\":3,\"delivery_date\":\"2026-07-21\",\"delivery_time\":\"12:00 PM\",\"total_amount\":960,\"address\":\"Navana Tower, Gulshan-1, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-19 10:15:00','2026-07-19 10:15:00'),
(81,38,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-19 10:35:00','2026-07-19 10:35:00'),
(82,38,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-20 17:30:00','2026-07-20 17:30:00'),
(83,39,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":1,\"quantity\":2,\"delivery_date\":\"2026-07-21\",\"delivery_time\":\"12:00 PM\",\"total_amount\":700,\"address\":\"88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-19 10:15:00','2026-07-19 10:15:00'),
(84,39,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-19 10:35:00','2026-07-19 10:35:00'),
(85,39,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-20 17:30:00','2026-07-20 17:30:00'),
(86,40,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":3,\"quantity\":3,\"delivery_date\":\"2026-07-18\",\"delivery_time\":\"11:30 AM\",\"total_amount\":1350,\"address\":\"Plot 12, Block C, Banani, Dhaka 1213\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-16 10:15:00','2026-07-16 10:15:00'),
(87,41,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":1,\"quantity\":1,\"delivery_date\":\"2026-07-20\",\"delivery_time\":\"12:00 PM\",\"total_amount\":350,\"address\":\"Plot 12, Block C, Banani, Dhaka 1213\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-18 10:15:00','2026-07-18 10:15:00'),
(88,42,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":3,\"quantity\":4,\"delivery_date\":\"2026-07-19\",\"delivery_time\":\"12:30 PM\",\"total_amount\":1800,\"address\":\"Plot 12, Block C, Banani, Dhaka 1213\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-17 10:15:00','2026-07-17 10:15:00'),
(89,42,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-18 17:30:00','2026-07-18 17:30:00'),
(90,43,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":5,\"quantity\":5,\"delivery_date\":\"2026-07-19\",\"delivery_time\":\"11:30 AM\",\"total_amount\":2000,\"address\":\"Plot 12, Block C, Banani, Dhaka 1213\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-17 10:15:00','2026-07-17 10:15:00'),
(91,44,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":1,\"quantity\":4,\"delivery_date\":\"2026-07-20\",\"delivery_time\":\"11:30 AM\",\"total_amount\":1400,\"address\":\"Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-18 10:15:00','2026-07-18 10:15:00'),
(92,45,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":1,\"quantity\":4,\"delivery_date\":\"2026-07-18\",\"delivery_time\":\"12:30 PM\",\"total_amount\":1400,\"address\":\"88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-16 10:15:00','2026-07-16 10:15:00'),
(93,45,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-16 10:35:00','2026-07-16 10:35:00'),
(94,45,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-17 17:30:00','2026-07-17 17:30:00'),
(95,46,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":5,\"quantity\":5,\"delivery_date\":\"2026-07-21\",\"delivery_time\":\"12:00 PM\",\"total_amount\":2000,\"address\":\"88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-19 10:15:00','2026-07-19 10:15:00'),
(96,46,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-19 10:35:00','2026-07-19 10:35:00'),
(97,46,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-20 17:30:00','2026-07-20 17:30:00'),
(98,47,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":4,\"quantity\":4,\"delivery_date\":\"2026-07-18\",\"delivery_time\":\"12:00 PM\",\"total_amount\":1680,\"address\":\"Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-16 10:15:00','2026-07-16 10:15:00'),
(99,48,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":4,\"quantity\":4,\"delivery_date\":\"2026-07-18\",\"delivery_time\":\"11:30 AM\",\"total_amount\":1680,\"address\":\"88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-16 10:15:00','2026-07-16 10:15:00'),
(100,48,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-16 10:35:00','2026-07-16 10:35:00'),
(101,48,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-17 17:30:00','2026-07-17 17:30:00'),
(102,49,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":1,\"quantity\":1,\"delivery_date\":\"2026-07-20\",\"delivery_time\":\"12:00 PM\",\"total_amount\":350,\"address\":\"Navana Tower, Gulshan-1, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-18 10:15:00','2026-07-18 10:15:00'),
(103,50,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":3,\"quantity\":5,\"delivery_date\":\"2026-07-21\",\"delivery_time\":\"11:30 AM\",\"total_amount\":2250,\"address\":\"Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-19 10:15:00','2026-07-19 10:15:00'),
(104,50,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-19 10:35:00','2026-07-19 10:35:00'),
(105,51,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":5,\"quantity\":5,\"delivery_date\":\"2026-07-19\",\"delivery_time\":\"12:00 PM\",\"total_amount\":2000,\"address\":\"Plot 12, Block C, Banani, Dhaka 1213\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-17 10:15:00','2026-07-17 10:15:00'),
(106,52,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":2,\"quantity\":3,\"delivery_date\":\"2026-07-18\",\"delivery_time\":\"12:30 PM\",\"total_amount\":960,\"address\":\"Plot 12, Block C, Banani, Dhaka 1213\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-16 10:15:00','2026-07-16 10:15:00'),
(107,52,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-16 10:35:00','2026-07-16 10:35:00'),
(108,53,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":5,\"quantity\":4,\"delivery_date\":\"2026-07-21\",\"delivery_time\":\"12:30 PM\",\"total_amount\":1600,\"address\":\"Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-19 10:15:00','2026-07-19 10:15:00'),
(109,53,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-19 10:35:00','2026-07-19 10:35:00'),
(110,54,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":4,\"quantity\":3,\"delivery_date\":\"2026-07-21\",\"delivery_time\":\"11:30 AM\",\"total_amount\":1260,\"address\":\"Navana Tower, Gulshan-1, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-19 10:15:00','2026-07-19 10:15:00'),
(111,54,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-20 17:30:00','2026-07-20 17:30:00'),
(112,55,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":4,\"quantity\":5,\"delivery_date\":\"2026-07-19\",\"delivery_time\":\"12:30 PM\",\"total_amount\":2100,\"address\":\"88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-17 10:15:00','2026-07-17 10:15:00'),
(113,56,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":1,\"quantity\":5,\"delivery_date\":\"2026-07-21\",\"delivery_time\":\"12:30 PM\",\"total_amount\":1750,\"address\":\"88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-19 10:15:00','2026-07-19 10:15:00'),
(114,57,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":4,\"quantity\":1,\"delivery_date\":\"2026-07-21\",\"delivery_time\":\"11:30 AM\",\"total_amount\":420,\"address\":\"Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-19 10:15:00','2026-07-19 10:15:00'),
(115,57,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-19 10:35:00','2026-07-19 10:35:00'),
(116,58,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":3,\"quantity\":1,\"delivery_date\":\"2026-07-18\",\"delivery_time\":\"11:30 AM\",\"total_amount\":450,\"address\":\"88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-16 10:15:00','2026-07-16 10:15:00'),
(117,59,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":2,\"quantity\":4,\"delivery_date\":\"2026-07-20\",\"delivery_time\":\"11:30 AM\",\"total_amount\":1280,\"address\":\"Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-18 10:15:00','2026-07-18 10:15:00'),
(118,60,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":5,\"quantity\":5,\"delivery_date\":\"2026-07-19\",\"delivery_time\":\"12:30 PM\",\"total_amount\":2000,\"address\":\"Navana Tower, Gulshan-1, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-17 10:15:00','2026-07-17 10:15:00'),
(119,61,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":2,\"quantity\":5,\"delivery_date\":\"2026-07-19\",\"delivery_time\":\"12:00 PM\",\"total_amount\":1600,\"address\":\"Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-17 10:15:00','2026-07-17 10:15:00'),
(120,62,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":2,\"quantity\":2,\"delivery_date\":\"2026-07-18\",\"delivery_time\":\"12:30 PM\",\"total_amount\":640,\"address\":\"Navana Tower, Gulshan-1, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-16 10:15:00','2026-07-16 10:15:00'),
(121,62,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-16 10:35:00','2026-07-16 10:35:00'),
(122,63,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":2,\"quantity\":5,\"delivery_date\":\"2026-07-18\",\"delivery_time\":\"12:30 PM\",\"total_amount\":1600,\"address\":\"Plot 12, Block C, Banani, Dhaka 1213\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-16 10:15:00','2026-07-16 10:15:00'),
(123,64,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":1,\"quantity\":1,\"delivery_date\":\"2026-07-20\",\"delivery_time\":\"12:30 PM\",\"total_amount\":350,\"address\":\"Plot 12, Block C, Banani, Dhaka 1213\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-18 10:15:00','2026-07-18 10:15:00'),
(124,64,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-18 10:35:00','2026-07-18 10:35:00'),
(125,64,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-19 17:30:00','2026-07-19 17:30:00'),
(126,65,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":3,\"quantity\":2,\"delivery_date\":\"2026-07-19\",\"delivery_time\":\"12:00 PM\",\"total_amount\":900,\"address\":\"Plot 12, Block C, Banani, Dhaka 1213\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-17 10:15:00','2026-07-17 10:15:00'),
(127,66,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":2,\"quantity\":1,\"delivery_date\":\"2026-07-20\",\"delivery_time\":\"12:30 PM\",\"total_amount\":320,\"address\":\"Navana Tower, Gulshan-1, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-18 10:15:00','2026-07-18 10:15:00'),
(128,66,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-18 10:35:00','2026-07-18 10:35:00'),
(129,67,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":5,\"quantity\":3,\"delivery_date\":\"2026-07-18\",\"delivery_time\":\"12:30 PM\",\"total_amount\":1200,\"address\":\"88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-16 10:15:00','2026-07-16 10:15:00'),
(130,67,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-16 10:35:00','2026-07-16 10:35:00'),
(131,68,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":4,\"quantity\":5,\"delivery_date\":\"2026-07-19\",\"delivery_time\":\"11:30 AM\",\"total_amount\":2100,\"address\":\"Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-17 10:15:00','2026-07-17 10:15:00'),
(132,68,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-17 10:35:00','2026-07-17 10:35:00'),
(133,69,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":2,\"quantity\":5,\"delivery_date\":\"2026-07-18\",\"delivery_time\":\"12:00 PM\",\"total_amount\":1600,\"address\":\"Plot 12, Block C, Banani, Dhaka 1213\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-16 10:15:00','2026-07-16 10:15:00'),
(134,69,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-16 10:35:00','2026-07-16 10:35:00'),
(135,70,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":2,\"quantity\":5,\"delivery_date\":\"2026-07-19\",\"delivery_time\":\"12:30 PM\",\"total_amount\":1600,\"address\":\"Plot 12, Block C, Banani, Dhaka 1213\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-17 10:15:00','2026-07-17 10:15:00'),
(136,70,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-17 10:35:00','2026-07-17 10:35:00'),
(137,70,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-18 17:30:00','2026-07-18 17:30:00'),
(138,71,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":2,\"quantity\":5,\"delivery_date\":\"2026-07-21\",\"delivery_time\":\"12:00 PM\",\"total_amount\":1600,\"address\":\"88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-19 10:15:00','2026-07-19 10:15:00'),
(139,71,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-19 10:35:00','2026-07-19 10:35:00'),
(140,72,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":2,\"quantity\":1,\"delivery_date\":\"2026-07-18\",\"delivery_time\":\"11:30 AM\",\"total_amount\":320,\"address\":\"Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-16 10:15:00','2026-07-16 10:15:00'),
(141,73,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":2,\"quantity\":2,\"delivery_date\":\"2026-07-19\",\"delivery_time\":\"12:00 PM\",\"total_amount\":640,\"address\":\"Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-17 10:15:00','2026-07-17 10:15:00'),
(142,74,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":4,\"quantity\":3,\"delivery_date\":\"2026-07-18\",\"delivery_time\":\"12:00 PM\",\"total_amount\":1260,\"address\":\"88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-16 10:15:00','2026-07-16 10:15:00'),
(143,74,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-16 10:35:00','2026-07-16 10:35:00'),
(144,75,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":1,\"quantity\":3,\"delivery_date\":\"2026-07-18\",\"delivery_time\":\"12:00 PM\",\"total_amount\":1050,\"address\":\"Plot 12, Block C, Banani, Dhaka 1213\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-16 10:15:00','2026-07-16 10:15:00'),
(145,75,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-16 10:35:00','2026-07-16 10:35:00'),
(146,75,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-17 17:30:00','2026-07-17 17:30:00'),
(147,76,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":1,\"quantity\":3,\"delivery_date\":\"2026-07-21\",\"delivery_time\":\"12:30 PM\",\"total_amount\":1050,\"address\":\"88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-19 10:15:00','2026-07-19 10:15:00'),
(148,76,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-19 10:35:00','2026-07-19 10:35:00'),
(149,77,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":4,\"quantity\":1,\"delivery_date\":\"2026-07-20\",\"delivery_time\":\"12:30 PM\",\"total_amount\":420,\"address\":\"Plot 12, Block C, Banani, Dhaka 1213\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-18 10:15:00','2026-07-18 10:15:00'),
(150,77,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-18 10:35:00','2026-07-18 10:35:00'),
(151,78,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":4,\"quantity\":3,\"delivery_date\":\"2026-07-20\",\"delivery_time\":\"12:30 PM\",\"total_amount\":1260,\"address\":\"Navana Tower, Gulshan-1, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-18 10:15:00','2026-07-18 10:15:00'),
(152,79,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":4,\"quantity\":2,\"delivery_date\":\"2026-07-20\",\"delivery_time\":\"11:30 AM\",\"total_amount\":840,\"address\":\"Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-18 10:15:00','2026-07-18 10:15:00'),
(153,79,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-19 17:30:00','2026-07-19 17:30:00'),
(154,80,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":4,\"quantity\":1,\"delivery_date\":\"2026-07-18\",\"delivery_time\":\"12:00 PM\",\"total_amount\":420,\"address\":\"Navana Tower, Gulshan-1, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-16 10:15:00','2026-07-16 10:15:00'),
(155,80,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-17 17:30:00','2026-07-17 17:30:00'),
(156,81,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":1,\"quantity\":3,\"delivery_date\":\"2026-07-19\",\"delivery_time\":\"11:30 AM\",\"total_amount\":1050,\"address\":\"88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-17 10:15:00','2026-07-17 10:15:00'),
(157,81,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-17 10:35:00','2026-07-17 10:35:00'),
(158,82,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":2,\"quantity\":4,\"delivery_date\":\"2026-07-21\",\"delivery_time\":\"12:30 PM\",\"total_amount\":1280,\"address\":\"Navana Tower, Gulshan-1, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-19 10:15:00','2026-07-19 10:15:00'),
(159,82,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-19 10:35:00','2026-07-19 10:35:00'),
(160,83,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":5,\"quantity\":2,\"delivery_date\":\"2026-07-20\",\"delivery_time\":\"12:30 PM\",\"total_amount\":800,\"address\":\"Navana Tower, Gulshan-1, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-18 10:15:00','2026-07-18 10:15:00'),
(161,83,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-18 10:35:00','2026-07-18 10:35:00'),
(162,83,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-19 17:30:00','2026-07-19 17:30:00'),
(163,84,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":4,\"quantity\":4,\"delivery_date\":\"2026-07-18\",\"delivery_time\":\"11:30 AM\",\"total_amount\":1680,\"address\":\"Navana Tower, Gulshan-1, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-16 10:15:00','2026-07-16 10:15:00'),
(164,84,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-16 10:35:00','2026-07-16 10:35:00'),
(165,84,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-17 17:30:00','2026-07-17 17:30:00'),
(166,85,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":5,\"quantity\":3,\"delivery_date\":\"2026-07-19\",\"delivery_time\":\"12:30 PM\",\"total_amount\":1200,\"address\":\"Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-17 10:15:00','2026-07-17 10:15:00'),
(167,85,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-17 10:35:00','2026-07-17 10:35:00'),
(168,86,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":2,\"quantity\":3,\"delivery_date\":\"2026-07-18\",\"delivery_time\":\"11:30 AM\",\"total_amount\":960,\"address\":\"Navana Tower, Gulshan-1, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-16 10:15:00','2026-07-16 10:15:00'),
(169,86,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-17 17:30:00','2026-07-17 17:30:00'),
(170,87,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":2,\"quantity\":1,\"delivery_date\":\"2026-07-19\",\"delivery_time\":\"12:30 PM\",\"total_amount\":320,\"address\":\"Plot 12, Block C, Banani, Dhaka 1213\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-17 10:15:00','2026-07-17 10:15:00'),
(171,87,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-17 10:35:00','2026-07-17 10:35:00'),
(172,88,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":1,\"quantity\":4,\"delivery_date\":\"2026-07-19\",\"delivery_time\":\"11:30 AM\",\"total_amount\":1400,\"address\":\"Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-17 10:15:00','2026-07-17 10:15:00'),
(173,89,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":3,\"quantity\":3,\"delivery_date\":\"2026-07-20\",\"delivery_time\":\"11:30 AM\",\"total_amount\":1350,\"address\":\"Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-18 10:15:00','2026-07-18 10:15:00'),
(174,89,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-19 17:30:00','2026-07-19 17:30:00'),
(175,90,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":4,\"quantity\":3,\"delivery_date\":\"2026-07-18\",\"delivery_time\":\"12:00 PM\",\"total_amount\":1260,\"address\":\"Plot 12, Block C, Banani, Dhaka 1213\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-16 10:15:00','2026-07-16 10:15:00'),
(176,90,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-16 10:35:00','2026-07-16 10:35:00'),
(177,91,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":4,\"quantity\":1,\"delivery_date\":\"2026-07-19\",\"delivery_time\":\"12:00 PM\",\"total_amount\":420,\"address\":\"Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-17 10:15:00','2026-07-17 10:15:00'),
(178,91,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-17 10:35:00','2026-07-17 10:35:00'),
(179,92,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":5,\"quantity\":5,\"delivery_date\":\"2026-07-18\",\"delivery_time\":\"11:30 AM\",\"total_amount\":2000,\"address\":\"88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-16 10:15:00','2026-07-16 10:15:00'),
(180,93,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":2,\"quantity\":1,\"delivery_date\":\"2026-07-18\",\"delivery_time\":\"12:30 PM\",\"total_amount\":320,\"address\":\"Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-16 10:15:00','2026-07-16 10:15:00'),
(181,93,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-16 10:35:00','2026-07-16 10:35:00'),
(182,94,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":5,\"quantity\":5,\"delivery_date\":\"2026-07-21\",\"delivery_time\":\"12:30 PM\",\"total_amount\":2000,\"address\":\"Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-19 10:15:00','2026-07-19 10:15:00'),
(183,95,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":5,\"quantity\":5,\"delivery_date\":\"2026-07-18\",\"delivery_time\":\"11:30 AM\",\"total_amount\":2000,\"address\":\"88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-16 10:15:00','2026-07-16 10:15:00'),
(184,96,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":5,\"quantity\":1,\"delivery_date\":\"2026-07-20\",\"delivery_time\":\"12:30 PM\",\"total_amount\":400,\"address\":\"Plot 12, Block C, Banani, Dhaka 1213\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-18 10:15:00','2026-07-18 10:15:00'),
(185,97,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":5,\"quantity\":3,\"delivery_date\":\"2026-07-20\",\"delivery_time\":\"12:30 PM\",\"total_amount\":1200,\"address\":\"Plot 12, Block C, Banani, Dhaka 1213\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-18 10:15:00','2026-07-18 10:15:00'),
(186,97,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-18 10:35:00','2026-07-18 10:35:00'),
(187,98,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":3,\"quantity\":1,\"delivery_date\":\"2026-07-21\",\"delivery_time\":\"12:00 PM\",\"total_amount\":450,\"address\":\"88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-19 10:15:00','2026-07-19 10:15:00'),
(188,99,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":5,\"quantity\":5,\"delivery_date\":\"2026-07-20\",\"delivery_time\":\"12:30 PM\",\"total_amount\":2000,\"address\":\"Plot 12, Block C, Banani, Dhaka 1213\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-18 10:15:00','2026-07-18 10:15:00'),
(189,99,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-18 10:35:00','2026-07-18 10:35:00'),
(190,100,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":3,\"quantity\":5,\"delivery_date\":\"2026-07-20\",\"delivery_time\":\"12:00 PM\",\"total_amount\":2250,\"address\":\"88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-18 10:15:00','2026-07-18 10:15:00'),
(191,100,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-18 10:35:00','2026-07-18 10:35:00'),
(192,101,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":5,\"quantity\":2,\"delivery_date\":\"2026-07-18\",\"delivery_time\":\"12:30 PM\",\"total_amount\":800,\"address\":\"Navana Tower, Gulshan-1, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-16 10:15:00','2026-07-16 10:15:00'),
(193,102,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":4,\"quantity\":4,\"delivery_date\":\"2026-07-18\",\"delivery_time\":\"11:30 AM\",\"total_amount\":1680,\"address\":\"88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-16 10:15:00','2026-07-16 10:15:00'),
(194,103,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":1,\"quantity\":2,\"delivery_date\":\"2026-07-19\",\"delivery_time\":\"12:30 PM\",\"total_amount\":700,\"address\":\"Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-17 10:15:00','2026-07-17 10:15:00'),
(195,104,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":2,\"quantity\":2,\"delivery_date\":\"2026-07-21\",\"delivery_time\":\"12:30 PM\",\"total_amount\":640,\"address\":\"Navana Tower, Gulshan-1, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-19 10:15:00','2026-07-19 10:15:00'),
(196,105,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":5,\"quantity\":2,\"delivery_date\":\"2026-07-19\",\"delivery_time\":\"12:30 PM\",\"total_amount\":800,\"address\":\"Plot 12, Block C, Banani, Dhaka 1213\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-17 10:15:00','2026-07-17 10:15:00'),
(197,106,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":3,\"quantity\":5,\"delivery_date\":\"2026-07-19\",\"delivery_time\":\"12:30 PM\",\"total_amount\":2250,\"address\":\"Navana Tower, Gulshan-1, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-17 10:15:00','2026-07-17 10:15:00'),
(198,107,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":2,\"quantity\":4,\"delivery_date\":\"2026-07-21\",\"delivery_time\":\"12:00 PM\",\"total_amount\":1280,\"address\":\"Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-19 10:15:00','2026-07-19 10:15:00'),
(199,107,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-19 10:35:00','2026-07-19 10:35:00'),
(200,108,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":2,\"quantity\":5,\"delivery_date\":\"2026-07-19\",\"delivery_time\":\"11:30 AM\",\"total_amount\":1600,\"address\":\"Plot 12, Block C, Banani, Dhaka 1213\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-17 10:15:00','2026-07-17 10:15:00'),
(201,109,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":5,\"quantity\":4,\"delivery_date\":\"2026-07-19\",\"delivery_time\":\"12:30 PM\",\"total_amount\":1600,\"address\":\"Plot 12, Block C, Banani, Dhaka 1213\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-17 10:15:00','2026-07-17 10:15:00'),
(202,109,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-17 10:35:00','2026-07-17 10:35:00'),
(203,109,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-18 17:30:00','2026-07-18 17:30:00'),
(204,110,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":4,\"quantity\":3,\"delivery_date\":\"2026-07-21\",\"delivery_time\":\"12:00 PM\",\"total_amount\":1260,\"address\":\"Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-19 10:15:00','2026-07-19 10:15:00'),
(205,110,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-19 10:35:00','2026-07-19 10:35:00'),
(206,110,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-20 17:30:00','2026-07-20 17:30:00'),
(207,111,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":1,\"quantity\":5,\"delivery_date\":\"2026-07-21\",\"delivery_time\":\"12:30 PM\",\"total_amount\":1750,\"address\":\"Navana Tower, Gulshan-1, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-19 10:15:00','2026-07-19 10:15:00'),
(208,112,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":3,\"quantity\":4,\"delivery_date\":\"2026-07-20\",\"delivery_time\":\"12:30 PM\",\"total_amount\":1800,\"address\":\"Navana Tower, Gulshan-1, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-18 10:15:00','2026-07-18 10:15:00'),
(209,112,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-18 10:35:00','2026-07-18 10:35:00'),
(210,113,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":3,\"quantity\":2,\"delivery_date\":\"2026-07-19\",\"delivery_time\":\"12:30 PM\",\"total_amount\":900,\"address\":\"Navana Tower, Gulshan-1, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-17 10:15:00','2026-07-17 10:15:00'),
(211,113,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-17 10:35:00','2026-07-17 10:35:00'),
(212,114,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":2,\"quantity\":2,\"delivery_date\":\"2026-07-20\",\"delivery_time\":\"12:00 PM\",\"total_amount\":640,\"address\":\"Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-18 10:15:00','2026-07-18 10:15:00'),
(213,114,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-18 10:35:00','2026-07-18 10:35:00'),
(214,114,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-19 17:30:00','2026-07-19 17:30:00'),
(215,115,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":2,\"quantity\":1,\"delivery_date\":\"2026-07-19\",\"delivery_time\":\"11:30 AM\",\"total_amount\":320,\"address\":\"Plot 12, Block C, Banani, Dhaka 1213\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-17 10:15:00','2026-07-17 10:15:00'),
(216,115,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-18 17:30:00','2026-07-18 17:30:00'),
(217,116,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":2,\"quantity\":3,\"delivery_date\":\"2026-07-20\",\"delivery_time\":\"12:30 PM\",\"total_amount\":960,\"address\":\"Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-18 10:15:00','2026-07-18 10:15:00'),
(218,116,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-18 10:35:00','2026-07-18 10:35:00'),
(219,117,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":1,\"quantity\":2,\"delivery_date\":\"2026-07-21\",\"delivery_time\":\"12:30 PM\",\"total_amount\":700,\"address\":\"88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-19 10:15:00','2026-07-19 10:15:00'),
(220,118,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":3,\"quantity\":1,\"delivery_date\":\"2026-07-20\",\"delivery_time\":\"12:30 PM\",\"total_amount\":450,\"address\":\"Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-18 10:15:00','2026-07-18 10:15:00'),
(221,119,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":2,\"quantity\":2,\"delivery_date\":\"2026-07-20\",\"delivery_time\":\"12:30 PM\",\"total_amount\":640,\"address\":\"Plot 12, Block C, Banani, Dhaka 1213\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-18 10:15:00','2026-07-18 10:15:00'),
(222,119,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-18 10:35:00','2026-07-18 10:35:00'),
(223,120,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":5,\"quantity\":1,\"delivery_date\":\"2026-07-19\",\"delivery_time\":\"12:00 PM\",\"total_amount\":400,\"address\":\"88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-17 10:15:00','2026-07-17 10:15:00'),
(224,120,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-18 17:30:00','2026-07-18 17:30:00'),
(225,121,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":2,\"quantity\":2,\"delivery_date\":\"2026-07-19\",\"delivery_time\":\"12:00 PM\",\"total_amount\":640,\"address\":\"Navana Tower, Gulshan-1, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-17 10:15:00','2026-07-17 10:15:00'),
(226,121,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-17 10:35:00','2026-07-17 10:35:00'),
(227,121,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-18 17:30:00','2026-07-18 17:30:00'),
(228,122,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":5,\"quantity\":2,\"delivery_date\":\"2026-07-20\",\"delivery_time\":\"12:30 PM\",\"total_amount\":800,\"address\":\"Plot 12, Block C, Banani, Dhaka 1213\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-18 10:15:00','2026-07-18 10:15:00'),
(229,122,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-18 10:35:00','2026-07-18 10:35:00'),
(230,122,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-19 17:30:00','2026-07-19 17:30:00'),
(231,123,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":5,\"quantity\":1,\"delivery_date\":\"2026-07-21\",\"delivery_time\":\"12:30 PM\",\"total_amount\":400,\"address\":\"Plot 12, Block C, Banani, Dhaka 1213\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-19 10:15:00','2026-07-19 10:15:00'),
(232,124,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":2,\"quantity\":5,\"delivery_date\":\"2026-07-20\",\"delivery_time\":\"11:30 AM\",\"total_amount\":1600,\"address\":\"88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-18 10:15:00','2026-07-18 10:15:00'),
(233,124,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-18 10:35:00','2026-07-18 10:35:00'),
(234,125,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":5,\"quantity\":2,\"delivery_date\":\"2026-07-21\",\"delivery_time\":\"12:00 PM\",\"total_amount\":800,\"address\":\"Plot 12, Block C, Banani, Dhaka 1213\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-19 10:15:00','2026-07-19 10:15:00'),
(235,125,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-19 10:35:00','2026-07-19 10:35:00'),
(236,126,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":3,\"quantity\":5,\"delivery_date\":\"2026-07-21\",\"delivery_time\":\"12:00 PM\",\"total_amount\":2250,\"address\":\"88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-19 10:15:00','2026-07-19 10:15:00'),
(237,126,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-19 10:35:00','2026-07-19 10:35:00'),
(238,127,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":1,\"quantity\":5,\"delivery_date\":\"2026-07-21\",\"delivery_time\":\"11:30 AM\",\"total_amount\":1750,\"address\":\"88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-19 10:15:00','2026-07-19 10:15:00'),
(239,127,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-19 10:35:00','2026-07-19 10:35:00'),
(240,128,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":3,\"quantity\":2,\"delivery_date\":\"2026-07-19\",\"delivery_time\":\"11:30 AM\",\"total_amount\":900,\"address\":\"88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-17 10:15:00','2026-07-17 10:15:00'),
(241,129,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":5,\"quantity\":2,\"delivery_date\":\"2026-07-20\",\"delivery_time\":\"11:30 AM\",\"total_amount\":800,\"address\":\"Plot 12, Block C, Banani, Dhaka 1213\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-18 10:15:00','2026-07-18 10:15:00'),
(242,129,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-18 10:35:00','2026-07-18 10:35:00'),
(243,130,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":2,\"quantity\":1,\"delivery_date\":\"2026-07-20\",\"delivery_time\":\"12:00 PM\",\"total_amount\":320,\"address\":\"88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-18 10:15:00','2026-07-18 10:15:00'),
(244,130,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-18 10:35:00','2026-07-18 10:35:00'),
(245,131,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":2,\"quantity\":1,\"delivery_date\":\"2026-07-19\",\"delivery_time\":\"12:00 PM\",\"total_amount\":320,\"address\":\"Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-17 10:15:00','2026-07-17 10:15:00'),
(246,132,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":5,\"quantity\":4,\"delivery_date\":\"2026-07-19\",\"delivery_time\":\"12:00 PM\",\"total_amount\":1600,\"address\":\"Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-17 10:15:00','2026-07-17 10:15:00'),
(247,133,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":1,\"quantity\":4,\"delivery_date\":\"2026-07-21\",\"delivery_time\":\"11:30 AM\",\"total_amount\":1400,\"address\":\"88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-19 10:15:00','2026-07-19 10:15:00'),
(248,133,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-20 17:30:00','2026-07-20 17:30:00'),
(249,134,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":4,\"quantity\":5,\"delivery_date\":\"2026-07-21\",\"delivery_time\":\"12:30 PM\",\"total_amount\":2100,\"address\":\"Plot 12, Block C, Banani, Dhaka 1213\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-19 10:15:00','2026-07-19 10:15:00'),
(250,135,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":4,\"quantity\":3,\"delivery_date\":\"2026-07-18\",\"delivery_time\":\"12:00 PM\",\"total_amount\":1260,\"address\":\"Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-16 10:15:00','2026-07-16 10:15:00'),
(251,136,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":4,\"quantity\":1,\"delivery_date\":\"2026-07-20\",\"delivery_time\":\"11:30 AM\",\"total_amount\":420,\"address\":\"Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-18 10:15:00','2026-07-18 10:15:00'),
(252,137,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":1,\"quantity\":3,\"delivery_date\":\"2026-07-18\",\"delivery_time\":\"11:30 AM\",\"total_amount\":1050,\"address\":\"Plot 12, Block C, Banani, Dhaka 1213\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-16 10:15:00','2026-07-16 10:15:00'),
(253,137,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-17 17:30:00','2026-07-17 17:30:00'),
(254,138,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":1,\"quantity\":1,\"delivery_date\":\"2026-07-21\",\"delivery_time\":\"12:30 PM\",\"total_amount\":350,\"address\":\"Plot 12, Block C, Banani, Dhaka 1213\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-19 10:15:00','2026-07-19 10:15:00'),
(255,138,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-19 10:35:00','2026-07-19 10:35:00'),
(256,139,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":1,\"quantity\":1,\"delivery_date\":\"2026-07-18\",\"delivery_time\":\"12:30 PM\",\"total_amount\":350,\"address\":\"88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-16 10:15:00','2026-07-16 10:15:00'),
(257,139,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-16 10:35:00','2026-07-16 10:35:00'),
(258,140,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":4,\"quantity\":5,\"delivery_date\":\"2026-07-20\",\"delivery_time\":\"11:30 AM\",\"total_amount\":2100,\"address\":\"Navana Tower, Gulshan-1, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-18 10:15:00','2026-07-18 10:15:00'),
(259,141,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":1,\"quantity\":1,\"delivery_date\":\"2026-07-19\",\"delivery_time\":\"12:30 PM\",\"total_amount\":350,\"address\":\"Plot 12, Block C, Banani, Dhaka 1213\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-17 10:15:00','2026-07-17 10:15:00'),
(260,141,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-18 17:30:00','2026-07-18 17:30:00'),
(261,142,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":2,\"quantity\":5,\"delivery_date\":\"2026-07-18\",\"delivery_time\":\"11:30 AM\",\"total_amount\":1600,\"address\":\"88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-16 10:15:00','2026-07-16 10:15:00'),
(262,142,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-16 10:35:00','2026-07-16 10:35:00'),
(263,143,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":1,\"quantity\":3,\"delivery_date\":\"2026-07-21\",\"delivery_time\":\"12:30 PM\",\"total_amount\":1050,\"address\":\"88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-19 10:15:00','2026-07-19 10:15:00'),
(264,144,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":2,\"quantity\":1,\"delivery_date\":\"2026-07-20\",\"delivery_time\":\"11:30 AM\",\"total_amount\":320,\"address\":\"Navana Tower, Gulshan-1, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-18 10:15:00','2026-07-18 10:15:00'),
(265,145,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":2,\"quantity\":5,\"delivery_date\":\"2026-07-20\",\"delivery_time\":\"12:00 PM\",\"total_amount\":1600,\"address\":\"Plot 12, Block C, Banani, Dhaka 1213\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-18 10:15:00','2026-07-18 10:15:00'),
(266,146,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":3,\"quantity\":2,\"delivery_date\":\"2026-07-19\",\"delivery_time\":\"12:00 PM\",\"total_amount\":900,\"address\":\"88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-17 10:15:00','2026-07-17 10:15:00'),
(267,147,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":1,\"quantity\":3,\"delivery_date\":\"2026-07-19\",\"delivery_time\":\"12:00 PM\",\"total_amount\":1050,\"address\":\"Navana Tower, Gulshan-1, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-17 10:15:00','2026-07-17 10:15:00'),
(268,148,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":2,\"quantity\":4,\"delivery_date\":\"2026-07-21\",\"delivery_time\":\"12:30 PM\",\"total_amount\":1280,\"address\":\"Navana Tower, Gulshan-1, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-19 10:15:00','2026-07-19 10:15:00'),
(269,149,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":2,\"quantity\":4,\"delivery_date\":\"2026-07-21\",\"delivery_time\":\"12:30 PM\",\"total_amount\":1280,\"address\":\"88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-19 10:15:00','2026-07-19 10:15:00'),
(270,150,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":3,\"quantity\":5,\"delivery_date\":\"2026-07-18\",\"delivery_time\":\"12:00 PM\",\"total_amount\":2250,\"address\":\"Navana Tower, Gulshan-1, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-16 10:15:00','2026-07-16 10:15:00'),
(271,150,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-16 10:35:00','2026-07-16 10:35:00'),
(272,151,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":1,\"quantity\":4,\"delivery_date\":\"2026-07-20\",\"delivery_time\":\"11:30 AM\",\"total_amount\":1400,\"address\":\"88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-18 10:15:00','2026-07-18 10:15:00'),
(273,151,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-18 10:35:00','2026-07-18 10:35:00'),
(274,152,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":1,\"quantity\":4,\"delivery_date\":\"2026-07-21\",\"delivery_time\":\"11:30 AM\",\"total_amount\":1400,\"address\":\"Plot 12, Block C, Banani, Dhaka 1213\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-19 10:15:00','2026-07-19 10:15:00'),
(275,152,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-19 10:35:00','2026-07-19 10:35:00'),
(276,153,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":1,\"quantity\":4,\"delivery_date\":\"2026-07-21\",\"delivery_time\":\"12:00 PM\",\"total_amount\":1400,\"address\":\"88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-19 10:15:00','2026-07-19 10:15:00'),
(277,153,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-19 10:35:00','2026-07-19 10:35:00'),
(278,154,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":3,\"quantity\":5,\"delivery_date\":\"2026-07-21\",\"delivery_time\":\"12:30 PM\",\"total_amount\":2250,\"address\":\"Navana Tower, Gulshan-1, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-19 10:15:00','2026-07-19 10:15:00'),
(279,155,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":3,\"quantity\":1,\"delivery_date\":\"2026-07-20\",\"delivery_time\":\"12:30 PM\",\"total_amount\":450,\"address\":\"Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-18 10:15:00','2026-07-18 10:15:00'),
(280,156,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":4,\"quantity\":4,\"delivery_date\":\"2026-07-19\",\"delivery_time\":\"12:00 PM\",\"total_amount\":1680,\"address\":\"88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-17 10:15:00','2026-07-17 10:15:00'),
(281,156,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-17 10:35:00','2026-07-17 10:35:00'),
(282,157,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":4,\"quantity\":5,\"delivery_date\":\"2026-07-20\",\"delivery_time\":\"12:30 PM\",\"total_amount\":2100,\"address\":\"Navana Tower, Gulshan-1, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-18 10:15:00','2026-07-18 10:15:00'),
(283,157,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-18 10:35:00','2026-07-18 10:35:00'),
(284,158,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":1,\"quantity\":2,\"delivery_date\":\"2026-07-18\",\"delivery_time\":\"12:30 PM\",\"total_amount\":700,\"address\":\"88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-16 10:15:00','2026-07-16 10:15:00'),
(285,158,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-16 10:35:00','2026-07-16 10:35:00'),
(286,159,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":3,\"quantity\":2,\"delivery_date\":\"2026-07-19\",\"delivery_time\":\"12:30 PM\",\"total_amount\":900,\"address\":\"Navana Tower, Gulshan-1, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-17 10:15:00','2026-07-17 10:15:00'),
(287,159,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-17 10:35:00','2026-07-17 10:35:00'),
(288,159,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-18 17:30:00','2026-07-18 17:30:00'),
(289,160,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":1,\"quantity\":5,\"delivery_date\":\"2026-07-18\",\"delivery_time\":\"12:00 PM\",\"total_amount\":1750,\"address\":\"Plot 12, Block C, Banani, Dhaka 1213\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-16 10:15:00','2026-07-16 10:15:00'),
(290,161,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":3,\"quantity\":5,\"delivery_date\":\"2026-07-21\",\"delivery_time\":\"12:00 PM\",\"total_amount\":2250,\"address\":\"Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-19 10:15:00','2026-07-19 10:15:00'),
(291,161,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-19 10:35:00','2026-07-19 10:35:00'),
(292,161,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-20 17:30:00','2026-07-20 17:30:00'),
(293,162,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":1,\"quantity\":3,\"delivery_date\":\"2026-07-18\",\"delivery_time\":\"11:30 AM\",\"total_amount\":1050,\"address\":\"88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-16 10:15:00','2026-07-16 10:15:00'),
(294,162,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-16 10:35:00','2026-07-16 10:35:00'),
(295,163,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":1,\"quantity\":4,\"delivery_date\":\"2026-07-19\",\"delivery_time\":\"11:30 AM\",\"total_amount\":1400,\"address\":\"Plot 12, Block C, Banani, Dhaka 1213\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-17 10:15:00','2026-07-17 10:15:00'),
(296,164,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":1,\"quantity\":3,\"delivery_date\":\"2026-07-21\",\"delivery_time\":\"12:00 PM\",\"total_amount\":1050,\"address\":\"Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-19 10:15:00','2026-07-19 10:15:00'),
(297,164,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-19 10:35:00','2026-07-19 10:35:00'),
(298,165,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":4,\"quantity\":4,\"delivery_date\":\"2026-07-18\",\"delivery_time\":\"11:30 AM\",\"total_amount\":1680,\"address\":\"Navana Tower, Gulshan-1, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-16 10:15:00','2026-07-16 10:15:00'),
(299,165,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-16 10:35:00','2026-07-16 10:35:00'),
(300,166,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":3,\"quantity\":1,\"delivery_date\":\"2026-07-18\",\"delivery_time\":\"12:00 PM\",\"total_amount\":450,\"address\":\"88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-16 10:15:00','2026-07-16 10:15:00'),
(301,167,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":3,\"quantity\":3,\"delivery_date\":\"2026-07-21\",\"delivery_time\":\"12:30 PM\",\"total_amount\":1350,\"address\":\"Navana Tower, Gulshan-1, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-19 10:15:00','2026-07-19 10:15:00'),
(302,167,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-20 17:30:00','2026-07-20 17:30:00'),
(303,168,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":5,\"quantity\":4,\"delivery_date\":\"2026-07-18\",\"delivery_time\":\"12:00 PM\",\"total_amount\":1600,\"address\":\"Navana Tower, Gulshan-1, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-16 10:15:00','2026-07-16 10:15:00'),
(304,168,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-16 10:35:00','2026-07-16 10:35:00'),
(305,168,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-17 17:30:00','2026-07-17 17:30:00'),
(306,169,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":3,\"quantity\":4,\"delivery_date\":\"2026-07-19\",\"delivery_time\":\"12:00 PM\",\"total_amount\":1800,\"address\":\"Navana Tower, Gulshan-1, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-17 10:15:00','2026-07-17 10:15:00'),
(307,170,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":1,\"quantity\":1,\"delivery_date\":\"2026-07-21\",\"delivery_time\":\"12:00 PM\",\"total_amount\":350,\"address\":\"88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-19 10:15:00','2026-07-19 10:15:00'),
(308,171,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":3,\"quantity\":2,\"delivery_date\":\"2026-07-21\",\"delivery_time\":\"12:30 PM\",\"total_amount\":900,\"address\":\"Navana Tower, Gulshan-1, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-19 10:15:00','2026-07-19 10:15:00'),
(309,171,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-19 10:35:00','2026-07-19 10:35:00'),
(310,171,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-20 17:30:00','2026-07-20 17:30:00'),
(311,172,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":2,\"quantity\":5,\"delivery_date\":\"2026-07-20\",\"delivery_time\":\"12:30 PM\",\"total_amount\":1600,\"address\":\"88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-18 10:15:00','2026-07-18 10:15:00'),
(312,173,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":5,\"quantity\":4,\"delivery_date\":\"2026-07-19\",\"delivery_time\":\"12:00 PM\",\"total_amount\":1600,\"address\":\"88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-17 10:15:00','2026-07-17 10:15:00'),
(313,173,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-18 17:30:00','2026-07-18 17:30:00'),
(314,174,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":5,\"quantity\":2,\"delivery_date\":\"2026-07-19\",\"delivery_time\":\"11:30 AM\",\"total_amount\":800,\"address\":\"Navana Tower, Gulshan-1, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-17 10:15:00','2026-07-17 10:15:00'),
(315,175,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":5,\"quantity\":3,\"delivery_date\":\"2026-07-21\",\"delivery_time\":\"11:30 AM\",\"total_amount\":1200,\"address\":\"Plot 12, Block C, Banani, Dhaka 1213\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-19 10:15:00','2026-07-19 10:15:00'),
(316,175,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-20 17:30:00','2026-07-20 17:30:00'),
(317,176,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":3,\"quantity\":2,\"delivery_date\":\"2026-07-18\",\"delivery_time\":\"11:30 AM\",\"total_amount\":900,\"address\":\"Navana Tower, Gulshan-1, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-16 10:15:00','2026-07-16 10:15:00'),
(318,176,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-16 10:35:00','2026-07-16 10:35:00'),
(319,177,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":4,\"quantity\":3,\"delivery_date\":\"2026-07-19\",\"delivery_time\":\"12:30 PM\",\"total_amount\":1260,\"address\":\"Plot 12, Block C, Banani, Dhaka 1213\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-17 10:15:00','2026-07-17 10:15:00'),
(320,177,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-17 10:35:00','2026-07-17 10:35:00'),
(321,178,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":4,\"quantity\":5,\"delivery_date\":\"2026-07-18\",\"delivery_time\":\"11:30 AM\",\"total_amount\":2100,\"address\":\"Navana Tower, Gulshan-1, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-16 10:15:00','2026-07-16 10:15:00'),
(322,178,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-16 10:35:00','2026-07-16 10:35:00'),
(323,179,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":1,\"quantity\":5,\"delivery_date\":\"2026-07-18\",\"delivery_time\":\"12:00 PM\",\"total_amount\":1750,\"address\":\"Navana Tower, Gulshan-1, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-16 10:15:00','2026-07-16 10:15:00'),
(324,179,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-17 17:30:00','2026-07-17 17:30:00'),
(325,180,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":5,\"quantity\":2,\"delivery_date\":\"2026-07-18\",\"delivery_time\":\"12:30 PM\",\"total_amount\":800,\"address\":\"Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-16 10:15:00','2026-07-16 10:15:00'),
(326,181,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":1,\"quantity\":3,\"delivery_date\":\"2026-07-19\",\"delivery_time\":\"12:30 PM\",\"total_amount\":1050,\"address\":\"Plot 12, Block C, Banani, Dhaka 1213\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-17 10:15:00','2026-07-17 10:15:00'),
(327,182,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":1,\"quantity\":3,\"delivery_date\":\"2026-07-18\",\"delivery_time\":\"12:30 PM\",\"total_amount\":1050,\"address\":\"Navana Tower, Gulshan-1, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-16 10:15:00','2026-07-16 10:15:00'),
(328,183,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":4,\"quantity\":4,\"delivery_date\":\"2026-07-18\",\"delivery_time\":\"12:00 PM\",\"total_amount\":1680,\"address\":\"Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-16 10:15:00','2026-07-16 10:15:00'),
(329,183,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-17 17:30:00','2026-07-17 17:30:00'),
(330,184,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":5,\"quantity\":5,\"delivery_date\":\"2026-07-19\",\"delivery_time\":\"11:30 AM\",\"total_amount\":2000,\"address\":\"Navana Tower, Gulshan-1, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-17 10:15:00','2026-07-17 10:15:00'),
(331,184,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-17 10:35:00','2026-07-17 10:35:00'),
(332,184,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-18 17:30:00','2026-07-18 17:30:00'),
(333,185,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":1,\"quantity\":1,\"delivery_date\":\"2026-07-18\",\"delivery_time\":\"12:00 PM\",\"total_amount\":350,\"address\":\"Plot 12, Block C, Banani, Dhaka 1213\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-16 10:15:00','2026-07-16 10:15:00'),
(334,186,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":1,\"quantity\":5,\"delivery_date\":\"2026-07-19\",\"delivery_time\":\"12:00 PM\",\"total_amount\":1750,\"address\":\"Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-17 10:15:00','2026-07-17 10:15:00'),
(335,187,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":3,\"quantity\":4,\"delivery_date\":\"2026-07-20\",\"delivery_time\":\"11:30 AM\",\"total_amount\":1800,\"address\":\"Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-18 10:15:00','2026-07-18 10:15:00'),
(336,187,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-18 10:35:00','2026-07-18 10:35:00'),
(337,188,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":1,\"quantity\":2,\"delivery_date\":\"2026-07-20\",\"delivery_time\":\"11:30 AM\",\"total_amount\":700,\"address\":\"Plot 12, Block C, Banani, Dhaka 1213\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-18 10:15:00','2026-07-18 10:15:00'),
(338,188,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-18 10:35:00','2026-07-18 10:35:00'),
(339,189,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":2,\"quantity\":1,\"delivery_date\":\"2026-07-18\",\"delivery_time\":\"12:00 PM\",\"total_amount\":320,\"address\":\"Navana Tower, Gulshan-1, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-16 10:15:00','2026-07-16 10:15:00'),
(340,189,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-16 10:35:00','2026-07-16 10:35:00'),
(341,189,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-17 17:30:00','2026-07-17 17:30:00'),
(342,190,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":5,\"quantity\":5,\"delivery_date\":\"2026-07-21\",\"delivery_time\":\"12:00 PM\",\"total_amount\":2000,\"address\":\"Navana Tower, Gulshan-1, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-19 10:15:00','2026-07-19 10:15:00'),
(343,190,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-19 10:35:00','2026-07-19 10:35:00'),
(344,191,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":2,\"quantity\":4,\"delivery_date\":\"2026-07-18\",\"delivery_time\":\"12:30 PM\",\"total_amount\":1280,\"address\":\"88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-16 10:15:00','2026-07-16 10:15:00'),
(345,192,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":5,\"quantity\":3,\"delivery_date\":\"2026-07-20\",\"delivery_time\":\"11:30 AM\",\"total_amount\":1200,\"address\":\"88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-18 10:15:00','2026-07-18 10:15:00'),
(346,193,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":1,\"quantity\":5,\"delivery_date\":\"2026-07-18\",\"delivery_time\":\"12:30 PM\",\"total_amount\":1750,\"address\":\"Navana Tower, Gulshan-1, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-16 10:15:00','2026-07-16 10:15:00'),
(347,193,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-16 10:35:00','2026-07-16 10:35:00'),
(348,194,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":3,\"quantity\":5,\"delivery_date\":\"2026-07-21\",\"delivery_time\":\"11:30 AM\",\"total_amount\":2250,\"address\":\"Plot 12, Block C, Banani, Dhaka 1213\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-19 10:15:00','2026-07-19 10:15:00'),
(349,195,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":1,\"quantity\":2,\"delivery_date\":\"2026-07-19\",\"delivery_time\":\"11:30 AM\",\"total_amount\":700,\"address\":\"Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-17 10:15:00','2026-07-17 10:15:00'),
(350,195,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-17 10:35:00','2026-07-17 10:35:00'),
(351,195,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-18 17:30:00','2026-07-18 17:30:00'),
(352,196,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":4,\"quantity\":5,\"delivery_date\":\"2026-07-20\",\"delivery_time\":\"12:30 PM\",\"total_amount\":2100,\"address\":\"88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-18 10:15:00','2026-07-18 10:15:00'),
(353,197,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":3,\"quantity\":2,\"delivery_date\":\"2026-07-19\",\"delivery_time\":\"12:30 PM\",\"total_amount\":900,\"address\":\"Navana Tower, Gulshan-1, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-17 10:15:00','2026-07-17 10:15:00'),
(354,198,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":4,\"quantity\":4,\"delivery_date\":\"2026-07-18\",\"delivery_time\":\"12:30 PM\",\"total_amount\":1680,\"address\":\"Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-16 10:15:00','2026-07-16 10:15:00'),
(355,198,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-16 10:35:00','2026-07-16 10:35:00'),
(356,198,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-17 17:30:00','2026-07-17 17:30:00'),
(357,199,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":3,\"quantity\":2,\"delivery_date\":\"2026-07-18\",\"delivery_time\":\"12:30 PM\",\"total_amount\":900,\"address\":\"Navana Tower, Gulshan-1, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-16 10:15:00','2026-07-16 10:15:00'),
(358,200,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":1,\"quantity\":4,\"delivery_date\":\"2026-07-20\",\"delivery_time\":\"12:30 PM\",\"total_amount\":1400,\"address\":\"88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-18 10:15:00','2026-07-18 10:15:00'),
(359,200,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-18 10:35:00','2026-07-18 10:35:00'),
(360,201,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":1,\"quantity\":1,\"delivery_date\":\"2026-07-20\",\"delivery_time\":\"12:30 PM\",\"total_amount\":350,\"address\":\"Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-18 10:15:00','2026-07-18 10:15:00'),
(361,202,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":4,\"quantity\":4,\"delivery_date\":\"2026-07-19\",\"delivery_time\":\"11:30 AM\",\"total_amount\":1680,\"address\":\"Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-17 10:15:00','2026-07-17 10:15:00'),
(362,203,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":4,\"quantity\":3,\"delivery_date\":\"2026-07-21\",\"delivery_time\":\"12:30 PM\",\"total_amount\":1260,\"address\":\"Navana Tower, Gulshan-1, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-19 10:15:00','2026-07-19 10:15:00'),
(363,203,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-19 10:35:00','2026-07-19 10:35:00'),
(364,204,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":5,\"quantity\":2,\"delivery_date\":\"2026-07-20\",\"delivery_time\":\"11:30 AM\",\"total_amount\":800,\"address\":\"Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-18 10:15:00','2026-07-18 10:15:00'),
(365,204,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-19 17:30:00','2026-07-19 17:30:00'),
(366,205,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":3,\"quantity\":3,\"delivery_date\":\"2026-07-21\",\"delivery_time\":\"12:30 PM\",\"total_amount\":1350,\"address\":\"Navana Tower, Gulshan-1, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-19 10:15:00','2026-07-19 10:15:00'),
(367,205,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-19 10:35:00','2026-07-19 10:35:00'),
(368,206,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":5,\"quantity\":1,\"delivery_date\":\"2026-07-21\",\"delivery_time\":\"11:30 AM\",\"total_amount\":400,\"address\":\"Plot 12, Block C, Banani, Dhaka 1213\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-19 10:15:00','2026-07-19 10:15:00'),
(369,206,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-19 10:35:00','2026-07-19 10:35:00'),
(370,207,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":1,\"quantity\":1,\"delivery_date\":\"2026-07-19\",\"delivery_time\":\"12:00 PM\",\"total_amount\":350,\"address\":\"Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-17 10:15:00','2026-07-17 10:15:00'),
(371,207,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-18 17:30:00','2026-07-18 17:30:00'),
(372,208,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":4,\"quantity\":4,\"delivery_date\":\"2026-07-21\",\"delivery_time\":\"12:00 PM\",\"total_amount\":1680,\"address\":\"Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-19 10:15:00','2026-07-19 10:15:00'),
(373,208,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-19 10:35:00','2026-07-19 10:35:00'),
(374,209,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":4,\"quantity\":2,\"delivery_date\":\"2026-07-21\",\"delivery_time\":\"12:30 PM\",\"total_amount\":840,\"address\":\"88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-19 10:15:00','2026-07-19 10:15:00'),
(375,209,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-19 10:35:00','2026-07-19 10:35:00'),
(376,210,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":3,\"quantity\":1,\"delivery_date\":\"2026-07-21\",\"delivery_time\":\"11:30 AM\",\"total_amount\":450,\"address\":\"Plot 12, Block C, Banani, Dhaka 1213\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-19 10:15:00','2026-07-19 10:15:00'),
(377,210,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-19 10:35:00','2026-07-19 10:35:00'),
(378,210,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-20 17:30:00','2026-07-20 17:30:00'),
(379,211,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":2,\"quantity\":4,\"delivery_date\":\"2026-07-20\",\"delivery_time\":\"12:00 PM\",\"total_amount\":1280,\"address\":\"Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-18 10:15:00','2026-07-18 10:15:00'),
(380,211,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-18 10:35:00','2026-07-18 10:35:00'),
(381,212,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":3,\"quantity\":2,\"delivery_date\":\"2026-07-19\",\"delivery_time\":\"11:30 AM\",\"total_amount\":900,\"address\":\"Navana Tower, Gulshan-1, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-17 10:15:00','2026-07-17 10:15:00'),
(382,212,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-17 10:35:00','2026-07-17 10:35:00'),
(383,213,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":4,\"quantity\":5,\"delivery_date\":\"2026-07-20\",\"delivery_time\":\"11:30 AM\",\"total_amount\":2100,\"address\":\"Navana Tower, Gulshan-1, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-18 10:15:00','2026-07-18 10:15:00'),
(384,214,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":1,\"quantity\":4,\"delivery_date\":\"2026-07-18\",\"delivery_time\":\"11:30 AM\",\"total_amount\":1400,\"address\":\"Plot 12, Block C, Banani, Dhaka 1213\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-16 10:15:00','2026-07-16 10:15:00'),
(385,215,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":3,\"quantity\":1,\"delivery_date\":\"2026-07-19\",\"delivery_time\":\"12:00 PM\",\"total_amount\":450,\"address\":\"Plot 12, Block C, Banani, Dhaka 1213\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-17 10:15:00','2026-07-17 10:15:00'),
(386,215,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-18 17:30:00','2026-07-18 17:30:00'),
(387,216,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":2,\"quantity\":4,\"delivery_date\":\"2026-07-18\",\"delivery_time\":\"12:00 PM\",\"total_amount\":1280,\"address\":\"Plot 12, Block C, Banani, Dhaka 1213\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-16 10:15:00','2026-07-16 10:15:00'),
(388,217,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":5,\"quantity\":3,\"delivery_date\":\"2026-07-18\",\"delivery_time\":\"11:30 AM\",\"total_amount\":1200,\"address\":\"Navana Tower, Gulshan-1, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-16 10:15:00','2026-07-16 10:15:00'),
(389,218,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":1,\"quantity\":5,\"delivery_date\":\"2026-07-21\",\"delivery_time\":\"11:30 AM\",\"total_amount\":1750,\"address\":\"88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-19 10:15:00','2026-07-19 10:15:00'),
(390,219,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":1,\"quantity\":1,\"delivery_date\":\"2026-07-12\",\"delivery_time\":\"12:00 PM\",\"total_amount\":350,\"address\":\"Plot 12, Block C, Banani, Dhaka 1213\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-05 10:15:00','2026-07-05 10:15:00'),
(391,219,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-05 10:35:00','2026-07-05 10:35:00'),
(392,219,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-11 17:30:00','2026-07-11 17:30:00'),
(393,219,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"processing\",\"to\":\"delivered\"}}}',2,'2026-07-12 12:00:00','2026-07-12 12:00:00'),
(394,220,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":4,\"quantity\":1,\"delivery_date\":\"2026-07-11\",\"delivery_time\":\"11:30 AM\",\"total_amount\":420,\"address\":\"88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-04 10:15:00','2026-07-04 10:15:00'),
(395,220,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-04 10:35:00','2026-07-04 10:35:00'),
(396,220,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-10 17:30:00','2026-07-10 17:30:00'),
(397,220,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"processing\",\"to\":\"delivered\"}}}',2,'2026-07-11 11:30:00','2026-07-11 11:30:00'),
(398,221,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":3,\"quantity\":1,\"delivery_date\":\"2026-07-14\",\"delivery_time\":\"12:30 PM\",\"total_amount\":450,\"address\":\"Plot 12, Block C, Banani, Dhaka 1213\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-08 10:15:00','2026-07-08 10:15:00'),
(399,221,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-08 10:35:00','2026-07-08 10:35:00'),
(400,221,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"cancelled\"}}}',2,'2026-07-13 18:00:00','2026-07-13 18:00:00'),
(401,222,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":2,\"quantity\":3,\"delivery_date\":\"2026-07-17\",\"delivery_time\":\"12:30 PM\",\"total_amount\":960,\"address\":\"Plot 12, Block C, Banani, Dhaka 1213\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-14 10:15:00','2026-07-14 10:15:00'),
(402,222,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-14 10:35:00','2026-07-14 10:35:00'),
(403,222,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-16 17:30:00','2026-07-16 17:30:00'),
(404,222,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"processing\",\"to\":\"delivered\"}}}',2,'2026-07-17 12:30:00','2026-07-17 12:30:00'),
(405,223,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":3,\"quantity\":2,\"delivery_date\":\"2026-07-13\",\"delivery_time\":\"11:30 AM\",\"total_amount\":900,\"address\":\"Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-06 10:15:00','2026-07-06 10:15:00'),
(406,223,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-06 10:35:00','2026-07-06 10:35:00'),
(407,223,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-12 17:30:00','2026-07-12 17:30:00'),
(408,223,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"processing\",\"to\":\"delivered\"}}}',2,'2026-07-13 11:30:00','2026-07-13 11:30:00'),
(409,224,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":2,\"quantity\":2,\"delivery_date\":\"2026-07-17\",\"delivery_time\":\"11:30 AM\",\"total_amount\":640,\"address\":\"Plot 12, Block C, Banani, Dhaka 1213\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-14 10:15:00','2026-07-14 10:15:00'),
(410,224,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-14 10:35:00','2026-07-14 10:35:00'),
(411,224,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-16 17:30:00','2026-07-16 17:30:00'),
(412,224,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"processing\",\"to\":\"delivered\"}}}',2,'2026-07-17 11:30:00','2026-07-17 11:30:00'),
(413,225,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":4,\"quantity\":2,\"delivery_date\":\"2026-07-12\",\"delivery_time\":\"12:00 PM\",\"total_amount\":840,\"address\":\"88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-05 10:15:00','2026-07-05 10:15:00'),
(414,225,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-05 10:35:00','2026-07-05 10:35:00'),
(415,225,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-11 17:30:00','2026-07-11 17:30:00'),
(416,225,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"processing\",\"to\":\"delivered\"}}}',2,'2026-07-12 12:00:00','2026-07-12 12:00:00'),
(417,226,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":2,\"quantity\":4,\"delivery_date\":\"2026-07-13\",\"delivery_time\":\"12:00 PM\",\"total_amount\":1280,\"address\":\"Plot 12, Block C, Banani, Dhaka 1213\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-06 10:15:00','2026-07-06 10:15:00'),
(418,226,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-12 17:30:00','2026-07-12 17:30:00'),
(419,226,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"processing\",\"to\":\"delivered\"}}}',2,'2026-07-13 12:00:00','2026-07-13 12:00:00'),
(420,227,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":5,\"quantity\":2,\"delivery_date\":\"2026-07-11\",\"delivery_time\":\"12:30 PM\",\"total_amount\":800,\"address\":\"Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-04 10:15:00','2026-07-04 10:15:00'),
(421,227,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-04 10:35:00','2026-07-04 10:35:00'),
(422,227,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-10 17:30:00','2026-07-10 17:30:00'),
(423,227,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"processing\",\"to\":\"delivered\"}}}',2,'2026-07-11 12:30:00','2026-07-11 12:30:00'),
(424,228,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":4,\"quantity\":4,\"delivery_date\":\"2026-07-15\",\"delivery_time\":\"12:00 PM\",\"total_amount\":1680,\"address\":\"88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-10 10:15:00','2026-07-10 10:15:00'),
(425,228,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-10 10:35:00','2026-07-10 10:35:00'),
(426,228,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-14 17:30:00','2026-07-14 17:30:00'),
(427,228,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"processing\",\"to\":\"delivered\"}}}',2,'2026-07-15 12:00:00','2026-07-15 12:00:00'),
(428,229,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":5,\"quantity\":3,\"delivery_date\":\"2026-07-17\",\"delivery_time\":\"12:30 PM\",\"total_amount\":1200,\"address\":\"Navana Tower, Gulshan-1, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-14 10:15:00','2026-07-14 10:15:00'),
(429,229,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-14 10:35:00','2026-07-14 10:35:00'),
(430,229,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-16 17:30:00','2026-07-16 17:30:00'),
(431,229,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"processing\",\"to\":\"delivered\"}}}',2,'2026-07-17 12:30:00','2026-07-17 12:30:00'),
(432,230,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":2,\"quantity\":4,\"delivery_date\":\"2026-07-11\",\"delivery_time\":\"12:00 PM\",\"total_amount\":1280,\"address\":\"Plot 12, Block C, Banani, Dhaka 1213\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-04 10:15:00','2026-07-04 10:15:00'),
(433,230,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-04 10:35:00','2026-07-04 10:35:00'),
(434,230,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-10 17:30:00','2026-07-10 17:30:00'),
(435,230,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"processing\",\"to\":\"delivered\"}}}',2,'2026-07-11 12:00:00','2026-07-11 12:00:00'),
(436,231,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":3,\"quantity\":2,\"delivery_date\":\"2026-07-13\",\"delivery_time\":\"12:30 PM\",\"total_amount\":900,\"address\":\"Plot 12, Block C, Banani, Dhaka 1213\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-06 10:15:00','2026-07-06 10:15:00'),
(437,231,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-06 10:35:00','2026-07-06 10:35:00'),
(438,231,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-12 17:30:00','2026-07-12 17:30:00'),
(439,231,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"processing\",\"to\":\"delivered\"}}}',2,'2026-07-13 12:30:00','2026-07-13 12:30:00'),
(440,232,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":2,\"quantity\":3,\"delivery_date\":\"2026-07-13\",\"delivery_time\":\"12:00 PM\",\"total_amount\":960,\"address\":\"88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-06 10:15:00','2026-07-06 10:15:00'),
(441,232,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-06 10:35:00','2026-07-06 10:35:00'),
(442,232,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-12 17:30:00','2026-07-12 17:30:00'),
(443,232,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"processing\",\"to\":\"delivered\"}}}',2,'2026-07-13 12:00:00','2026-07-13 12:00:00'),
(444,233,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":3,\"quantity\":3,\"delivery_date\":\"2026-07-15\",\"delivery_time\":\"12:30 PM\",\"total_amount\":1350,\"address\":\"Navana Tower, Gulshan-1, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-10 10:15:00','2026-07-10 10:15:00'),
(445,233,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-10 10:35:00','2026-07-10 10:35:00'),
(446,233,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-14 17:30:00','2026-07-14 17:30:00'),
(447,233,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"processing\",\"to\":\"delivered\"}}}',2,'2026-07-15 12:30:00','2026-07-15 12:30:00'),
(448,234,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":2,\"quantity\":3,\"delivery_date\":\"2026-07-17\",\"delivery_time\":\"11:30 AM\",\"total_amount\":960,\"address\":\"Navana Tower, Gulshan-1, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-14 10:15:00','2026-07-14 10:15:00'),
(449,234,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-14 10:35:00','2026-07-14 10:35:00'),
(450,234,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-16 17:30:00','2026-07-16 17:30:00'),
(451,234,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"processing\",\"to\":\"delivered\"}}}',2,'2026-07-17 11:30:00','2026-07-17 11:30:00'),
(452,235,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":4,\"quantity\":2,\"delivery_date\":\"2026-07-11\",\"delivery_time\":\"12:30 PM\",\"total_amount\":840,\"address\":\"Plot 12, Block C, Banani, Dhaka 1213\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-04 10:15:00','2026-07-04 10:15:00'),
(453,235,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-04 10:35:00','2026-07-04 10:35:00'),
(454,235,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-10 17:30:00','2026-07-10 17:30:00'),
(455,235,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"processing\",\"to\":\"delivered\"}}}',2,'2026-07-11 12:30:00','2026-07-11 12:30:00'),
(456,236,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":1,\"quantity\":4,\"delivery_date\":\"2026-07-13\",\"delivery_time\":\"12:00 PM\",\"total_amount\":1400,\"address\":\"Navana Tower, Gulshan-1, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-06 10:15:00','2026-07-06 10:15:00'),
(457,236,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-06 10:35:00','2026-07-06 10:35:00'),
(458,236,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-12 17:30:00','2026-07-12 17:30:00'),
(459,236,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"processing\",\"to\":\"delivered\"}}}',2,'2026-07-13 12:00:00','2026-07-13 12:00:00'),
(460,237,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":5,\"quantity\":1,\"delivery_date\":\"2026-07-13\",\"delivery_time\":\"12:00 PM\",\"total_amount\":400,\"address\":\"88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-06 10:15:00','2026-07-06 10:15:00'),
(461,237,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-06 10:35:00','2026-07-06 10:35:00'),
(462,237,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-12 17:30:00','2026-07-12 17:30:00'),
(463,237,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"processing\",\"to\":\"delivered\"}}}',2,'2026-07-13 12:00:00','2026-07-13 12:00:00'),
(464,238,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":4,\"quantity\":2,\"delivery_date\":\"2026-07-16\",\"delivery_time\":\"12:00 PM\",\"total_amount\":840,\"address\":\"88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-12 10:15:00','2026-07-12 10:15:00'),
(465,238,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-12 10:35:00','2026-07-12 10:35:00'),
(466,238,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-15 17:30:00','2026-07-15 17:30:00'),
(467,238,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"processing\",\"to\":\"delivered\"}}}',2,'2026-07-16 12:00:00','2026-07-16 12:00:00'),
(468,239,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":1,\"quantity\":5,\"delivery_date\":\"2026-07-16\",\"delivery_time\":\"12:00 PM\",\"total_amount\":1750,\"address\":\"Plot 12, Block C, Banani, Dhaka 1213\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-12 10:15:00','2026-07-12 10:15:00'),
(469,239,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-12 10:35:00','2026-07-12 10:35:00'),
(470,239,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-15 17:30:00','2026-07-15 17:30:00'),
(471,239,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"processing\",\"to\":\"delivered\"}}}',2,'2026-07-16 12:00:00','2026-07-16 12:00:00'),
(472,240,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":4,\"quantity\":2,\"delivery_date\":\"2026-07-14\",\"delivery_time\":\"11:30 AM\",\"total_amount\":840,\"address\":\"Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-08 10:15:00','2026-07-08 10:15:00'),
(473,240,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-08 10:35:00','2026-07-08 10:35:00'),
(474,240,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-13 17:30:00','2026-07-13 17:30:00'),
(475,240,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"processing\",\"to\":\"delivered\"}}}',2,'2026-07-14 11:30:00','2026-07-14 11:30:00'),
(476,241,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":2,\"quantity\":1,\"delivery_date\":\"2026-07-15\",\"delivery_time\":\"12:30 PM\",\"total_amount\":320,\"address\":\"Navana Tower, Gulshan-1, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-10 10:15:00','2026-07-10 10:15:00'),
(477,241,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-10 10:35:00','2026-07-10 10:35:00'),
(478,241,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-14 17:30:00','2026-07-14 17:30:00'),
(479,241,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"processing\",\"to\":\"delivered\"}}}',2,'2026-07-15 12:30:00','2026-07-15 12:30:00'),
(480,242,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":2,\"quantity\":5,\"delivery_date\":\"2026-07-15\",\"delivery_time\":\"11:30 AM\",\"total_amount\":1600,\"address\":\"Navana Tower, Gulshan-1, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-10 10:15:00','2026-07-10 10:15:00'),
(481,242,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-10 10:35:00','2026-07-10 10:35:00'),
(482,242,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-14 17:30:00','2026-07-14 17:30:00'),
(483,242,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"processing\",\"to\":\"delivered\"}}}',2,'2026-07-15 11:30:00','2026-07-15 11:30:00'),
(484,243,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":2,\"quantity\":5,\"delivery_date\":\"2026-07-16\",\"delivery_time\":\"12:00 PM\",\"total_amount\":1600,\"address\":\"Plot 12, Block C, Banani, Dhaka 1213\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-12 10:15:00','2026-07-12 10:15:00'),
(485,243,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-12 10:35:00','2026-07-12 10:35:00'),
(486,243,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-15 17:30:00','2026-07-15 17:30:00'),
(487,243,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"processing\",\"to\":\"delivered\"}}}',2,'2026-07-16 12:00:00','2026-07-16 12:00:00'),
(488,244,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":2,\"quantity\":5,\"delivery_date\":\"2026-07-17\",\"delivery_time\":\"12:30 PM\",\"total_amount\":1600,\"address\":\"Navana Tower, Gulshan-1, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-14 10:15:00','2026-07-14 10:15:00'),
(489,244,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-14 10:35:00','2026-07-14 10:35:00'),
(490,244,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-16 17:30:00','2026-07-16 17:30:00'),
(491,244,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"processing\",\"to\":\"delivered\"}}}',2,'2026-07-17 12:30:00','2026-07-17 12:30:00'),
(492,245,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":4,\"quantity\":1,\"delivery_date\":\"2026-07-14\",\"delivery_time\":\"12:00 PM\",\"total_amount\":420,\"address\":\"Navana Tower, Gulshan-1, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-08 10:15:00','2026-07-08 10:15:00'),
(493,245,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-08 10:35:00','2026-07-08 10:35:00'),
(494,245,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"cancelled\"}}}',2,'2026-07-13 18:00:00','2026-07-13 18:00:00'),
(495,246,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":5,\"quantity\":4,\"delivery_date\":\"2026-07-12\",\"delivery_time\":\"12:00 PM\",\"total_amount\":1600,\"address\":\"Plot 12, Block C, Banani, Dhaka 1213\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-05 10:15:00','2026-07-05 10:15:00'),
(496,246,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-05 10:35:00','2026-07-05 10:35:00'),
(497,246,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-11 17:30:00','2026-07-11 17:30:00'),
(498,246,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"processing\",\"to\":\"delivered\"}}}',2,'2026-07-12 12:00:00','2026-07-12 12:00:00'),
(499,247,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":3,\"quantity\":4,\"delivery_date\":\"2026-07-17\",\"delivery_time\":\"12:00 PM\",\"total_amount\":1800,\"address\":\"Plot 12, Block C, Banani, Dhaka 1213\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-14 10:15:00','2026-07-14 10:15:00'),
(500,247,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-14 10:35:00','2026-07-14 10:35:00'),
(501,247,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-16 17:30:00','2026-07-16 17:30:00'),
(502,247,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"processing\",\"to\":\"delivered\"}}}',2,'2026-07-17 12:00:00','2026-07-17 12:00:00'),
(503,248,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":1,\"quantity\":1,\"delivery_date\":\"2026-07-12\",\"delivery_time\":\"12:30 PM\",\"total_amount\":350,\"address\":\"88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-05 10:15:00','2026-07-05 10:15:00'),
(504,248,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-05 10:35:00','2026-07-05 10:35:00'),
(505,248,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-11 17:30:00','2026-07-11 17:30:00'),
(506,248,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"processing\",\"to\":\"delivered\"}}}',2,'2026-07-12 12:30:00','2026-07-12 12:30:00'),
(507,249,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":4,\"quantity\":4,\"delivery_date\":\"2026-07-14\",\"delivery_time\":\"12:30 PM\",\"total_amount\":1680,\"address\":\"88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-08 10:15:00','2026-07-08 10:15:00'),
(508,249,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-08 10:35:00','2026-07-08 10:35:00'),
(509,249,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"cancelled\"}}}',2,'2026-07-13 18:00:00','2026-07-13 18:00:00'),
(510,250,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":5,\"quantity\":3,\"delivery_date\":\"2026-07-17\",\"delivery_time\":\"12:00 PM\",\"total_amount\":1200,\"address\":\"Navana Tower, Gulshan-1, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-14 10:15:00','2026-07-14 10:15:00'),
(511,250,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"cancelled\"}}}',2,'2026-07-16 18:00:00','2026-07-16 18:00:00'),
(512,250,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"paid\",\"to\":\"returned\"}}}',2,'2026-07-16 18:45:00','2026-07-16 18:45:00'),
(513,251,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":4,\"quantity\":1,\"delivery_date\":\"2026-07-12\",\"delivery_time\":\"12:00 PM\",\"total_amount\":420,\"address\":\"Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-05 10:15:00','2026-07-05 10:15:00'),
(514,251,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-11 17:30:00','2026-07-11 17:30:00'),
(515,251,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"processing\",\"to\":\"delivered\"}}}',2,'2026-07-12 12:00:00','2026-07-12 12:00:00'),
(516,252,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":5,\"quantity\":3,\"delivery_date\":\"2026-07-12\",\"delivery_time\":\"12:30 PM\",\"total_amount\":1200,\"address\":\"Navana Tower, Gulshan-1, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-05 10:15:00','2026-07-05 10:15:00'),
(517,252,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-05 10:35:00','2026-07-05 10:35:00'),
(518,252,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-11 17:30:00','2026-07-11 17:30:00'),
(519,252,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"processing\",\"to\":\"delivered\"}}}',2,'2026-07-12 12:30:00','2026-07-12 12:30:00'),
(520,253,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":5,\"quantity\":4,\"delivery_date\":\"2026-07-15\",\"delivery_time\":\"12:30 PM\",\"total_amount\":1600,\"address\":\"Navana Tower, Gulshan-1, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-10 10:15:00','2026-07-10 10:15:00'),
(521,253,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-10 10:35:00','2026-07-10 10:35:00'),
(522,253,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"cancelled\"}}}',2,'2026-07-14 18:00:00','2026-07-14 18:00:00'),
(523,254,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":2,\"quantity\":1,\"delivery_date\":\"2026-07-13\",\"delivery_time\":\"11:30 AM\",\"total_amount\":320,\"address\":\"Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-06 10:15:00','2026-07-06 10:15:00'),
(524,254,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-06 10:35:00','2026-07-06 10:35:00'),
(525,254,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"cancelled\"}}}',2,'2026-07-12 18:00:00','2026-07-12 18:00:00'),
(526,255,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":1,\"quantity\":1,\"delivery_date\":\"2026-07-13\",\"delivery_time\":\"12:00 PM\",\"total_amount\":350,\"address\":\"88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-06 10:15:00','2026-07-06 10:15:00'),
(527,255,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-06 10:35:00','2026-07-06 10:35:00'),
(528,255,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-12 17:30:00','2026-07-12 17:30:00'),
(529,255,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"processing\",\"to\":\"delivered\"}}}',2,'2026-07-13 12:00:00','2026-07-13 12:00:00'),
(530,256,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":1,\"quantity\":3,\"delivery_date\":\"2026-07-12\",\"delivery_time\":\"12:30 PM\",\"total_amount\":1050,\"address\":\"88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-05 10:15:00','2026-07-05 10:15:00'),
(531,256,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-05 10:35:00','2026-07-05 10:35:00'),
(532,256,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-11 17:30:00','2026-07-11 17:30:00'),
(533,256,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"processing\",\"to\":\"delivered\"}}}',2,'2026-07-12 12:30:00','2026-07-12 12:30:00'),
(534,257,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":4,\"quantity\":4,\"delivery_date\":\"2026-07-17\",\"delivery_time\":\"12:00 PM\",\"total_amount\":1680,\"address\":\"Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-14 10:15:00','2026-07-14 10:15:00'),
(535,257,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-14 10:35:00','2026-07-14 10:35:00'),
(536,257,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-16 17:30:00','2026-07-16 17:30:00'),
(537,257,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"processing\",\"to\":\"delivered\"}}}',2,'2026-07-17 12:00:00','2026-07-17 12:00:00'),
(538,258,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":1,\"quantity\":5,\"delivery_date\":\"2026-07-16\",\"delivery_time\":\"12:30 PM\",\"total_amount\":1750,\"address\":\"Plot 12, Block C, Banani, Dhaka 1213\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-12 10:15:00','2026-07-12 10:15:00'),
(539,258,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-15 17:30:00','2026-07-15 17:30:00'),
(540,258,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"processing\",\"to\":\"delivered\"}}}',2,'2026-07-16 12:30:00','2026-07-16 12:30:00'),
(541,259,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":5,\"quantity\":3,\"delivery_date\":\"2026-07-12\",\"delivery_time\":\"11:30 AM\",\"total_amount\":1200,\"address\":\"Navana Tower, Gulshan-1, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-05 10:15:00','2026-07-05 10:15:00'),
(542,259,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-05 10:35:00','2026-07-05 10:35:00'),
(543,259,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-11 17:30:00','2026-07-11 17:30:00'),
(544,259,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"processing\",\"to\":\"delivered\"}}}',2,'2026-07-12 11:30:00','2026-07-12 11:30:00'),
(545,260,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":5,\"quantity\":5,\"delivery_date\":\"2026-07-12\",\"delivery_time\":\"11:30 AM\",\"total_amount\":2000,\"address\":\"88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-05 10:15:00','2026-07-05 10:15:00'),
(546,260,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-05 10:35:00','2026-07-05 10:35:00'),
(547,260,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-11 17:30:00','2026-07-11 17:30:00'),
(548,260,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"processing\",\"to\":\"delivered\"}}}',2,'2026-07-12 11:30:00','2026-07-12 11:30:00'),
(549,261,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":4,\"quantity\":3,\"delivery_date\":\"2026-07-13\",\"delivery_time\":\"11:30 AM\",\"total_amount\":1260,\"address\":\"Navana Tower, Gulshan-1, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-06 10:15:00','2026-07-06 10:15:00'),
(550,261,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-06 10:35:00','2026-07-06 10:35:00'),
(551,261,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"cancelled\"}}}',2,'2026-07-12 18:00:00','2026-07-12 18:00:00'),
(552,262,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":1,\"quantity\":5,\"delivery_date\":\"2026-07-17\",\"delivery_time\":\"12:00 PM\",\"total_amount\":1750,\"address\":\"88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-14 10:15:00','2026-07-14 10:15:00'),
(553,262,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-14 10:35:00','2026-07-14 10:35:00'),
(554,262,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-16 17:30:00','2026-07-16 17:30:00'),
(555,262,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"processing\",\"to\":\"delivered\"}}}',2,'2026-07-17 12:00:00','2026-07-17 12:00:00'),
(556,263,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":1,\"quantity\":4,\"delivery_date\":\"2026-07-17\",\"delivery_time\":\"12:00 PM\",\"total_amount\":1400,\"address\":\"Navana Tower, Gulshan-1, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-14 10:15:00','2026-07-14 10:15:00'),
(557,263,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-14 10:35:00','2026-07-14 10:35:00'),
(558,263,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-16 17:30:00','2026-07-16 17:30:00'),
(559,263,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"processing\",\"to\":\"delivered\"}}}',2,'2026-07-17 12:00:00','2026-07-17 12:00:00'),
(560,264,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":2,\"quantity\":2,\"delivery_date\":\"2026-07-16\",\"delivery_time\":\"11:30 AM\",\"total_amount\":640,\"address\":\"Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-12 10:15:00','2026-07-12 10:15:00'),
(561,264,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-12 10:35:00','2026-07-12 10:35:00'),
(562,264,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"cancelled\"}}}',2,'2026-07-15 18:00:00','2026-07-15 18:00:00'),
(563,265,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":5,\"quantity\":5,\"delivery_date\":\"2026-07-16\",\"delivery_time\":\"11:30 AM\",\"total_amount\":2000,\"address\":\"Navana Tower, Gulshan-1, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-12 10:15:00','2026-07-12 10:15:00'),
(564,265,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-12 10:35:00','2026-07-12 10:35:00'),
(565,265,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-15 17:30:00','2026-07-15 17:30:00'),
(566,265,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"processing\",\"to\":\"delivered\"}}}',2,'2026-07-16 11:30:00','2026-07-16 11:30:00'),
(567,266,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":3,\"quantity\":4,\"delivery_date\":\"2026-07-11\",\"delivery_time\":\"11:30 AM\",\"total_amount\":1800,\"address\":\"Plot 12, Block C, Banani, Dhaka 1213\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-04 10:15:00','2026-07-04 10:15:00'),
(568,266,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-04 10:35:00','2026-07-04 10:35:00'),
(569,266,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-10 17:30:00','2026-07-10 17:30:00'),
(570,266,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"processing\",\"to\":\"delivered\"}}}',2,'2026-07-11 11:30:00','2026-07-11 11:30:00'),
(571,267,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":3,\"quantity\":1,\"delivery_date\":\"2026-07-14\",\"delivery_time\":\"12:30 PM\",\"total_amount\":450,\"address\":\"Navana Tower, Gulshan-1, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-08 10:15:00','2026-07-08 10:15:00'),
(572,267,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-08 10:35:00','2026-07-08 10:35:00'),
(573,267,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"cancelled\"}}}',2,'2026-07-13 18:00:00','2026-07-13 18:00:00'),
(574,268,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":5,\"quantity\":3,\"delivery_date\":\"2026-07-13\",\"delivery_time\":\"12:30 PM\",\"total_amount\":1200,\"address\":\"88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":2,\"updated_by\":2}}',2,'2026-07-06 10:15:00','2026-07-06 10:15:00'),
(575,268,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-06 10:35:00','2026-07-06 10:35:00'),
(576,268,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-12 17:30:00','2026-07-12 17:30:00'),
(577,268,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"processing\",\"to\":\"delivered\"}}}',2,'2026-07-13 12:30:00','2026-07-13 12:30:00'),
(578,269,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":1,\"quantity\":2,\"delivery_date\":\"2026-07-18\",\"delivery_time\":\"12:00 PM\",\"total_amount\":700,\"address\":\"Corp HQ, Gulshan Avenue, Dhaka\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":18,\"updated_by\":null}}',2,'2026-07-16 10:15:00','2026-07-16 10:15:00'),
(579,269,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-16 10:35:00','2026-07-16 10:35:00'),
(580,270,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":2,\"quantity\":1,\"delivery_date\":\"2026-07-18\",\"delivery_time\":\"12:00 PM\",\"total_amount\":320,\"address\":\"Corp HQ, Gulshan Avenue, Dhaka\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":18,\"updated_by\":null}}',2,'2026-07-16 10:15:00','2026-07-16 10:15:00'),
(581,270,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-16 10:35:00','2026-07-16 10:35:00'),
(582,271,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":1,\"quantity\":2,\"delivery_date\":\"2026-07-19\",\"delivery_time\":\"12:00 PM\",\"total_amount\":700,\"address\":\"Corp HQ, Gulshan Avenue, Dhaka\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":18,\"updated_by\":null}}',2,'2026-07-17 10:15:00','2026-07-17 10:15:00'),
(583,271,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-17 10:35:00','2026-07-17 10:35:00'),
(584,272,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":3,\"quantity\":3,\"delivery_date\":\"2026-07-19\",\"delivery_time\":\"12:00 PM\",\"total_amount\":1350,\"address\":\"Corp HQ, Gulshan Avenue, Dhaka\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":18,\"updated_by\":null}}',2,'2026-07-17 10:15:00','2026-07-17 10:15:00'),
(585,272,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-17 10:35:00','2026-07-17 10:35:00'),
(586,273,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":2,\"quantity\":1,\"delivery_date\":\"2026-07-20\",\"delivery_time\":\"12:00 PM\",\"total_amount\":320,\"address\":\"Corp HQ, Gulshan Avenue, Dhaka\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":18,\"updated_by\":null}}',2,'2026-07-18 10:15:00','2026-07-18 10:15:00'),
(587,273,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-18 10:35:00','2026-07-18 10:35:00'),
(588,274,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":1,\"quantity\":2,\"delivery_date\":\"2026-07-20\",\"delivery_time\":\"12:00 PM\",\"total_amount\":700,\"address\":\"Corp HQ, Gulshan Avenue, Dhaka\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":18,\"updated_by\":null}}',2,'2026-07-18 10:15:00','2026-07-18 10:15:00'),
(589,274,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-18 10:35:00','2026-07-18 10:35:00'),
(590,275,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":1,\"quantity\":2,\"delivery_date\":\"2026-07-18\",\"delivery_time\":\"12:00 PM\",\"total_amount\":700,\"address\":\"Corp HQ, Gulshan Avenue, Dhaka\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":18,\"updated_by\":3}}',2,'2026-07-16 10:15:00','2026-07-16 10:15:00'),
(591,275,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-16 10:35:00','2026-07-16 10:35:00'),
(592,276,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":2,\"quantity\":1,\"delivery_date\":\"2026-07-18\",\"delivery_time\":\"12:00 PM\",\"total_amount\":320,\"address\":\"Corp HQ, Gulshan Avenue, Dhaka\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":18,\"updated_by\":3}}',2,'2026-07-16 10:15:00','2026-07-16 10:15:00'),
(593,276,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-16 10:35:00','2026-07-16 10:35:00'),
(594,277,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":1,\"quantity\":2,\"delivery_date\":\"2026-07-19\",\"delivery_time\":\"12:00 PM\",\"total_amount\":700,\"address\":\"Corp HQ, Gulshan Avenue, Dhaka\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":18,\"updated_by\":3}}',2,'2026-07-17 10:15:00','2026-07-17 10:15:00'),
(595,277,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-17 10:35:00','2026-07-17 10:35:00'),
(596,278,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":3,\"quantity\":2,\"delivery_date\":\"2026-07-19\",\"delivery_time\":\"12:00 PM\",\"total_amount\":900,\"address\":\"Corp HQ, Gulshan Avenue, Dhaka\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":18,\"updated_by\":3}}',2,'2026-07-17 10:15:00','2026-07-17 10:15:00'),
(597,278,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-17 10:35:00','2026-07-17 10:35:00'),
(598,279,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":2,\"quantity\":2,\"delivery_date\":\"2026-07-18\",\"delivery_time\":\"12:00 PM\",\"total_amount\":640,\"address\":\"Corp HQ, Gulshan Avenue, Dhaka\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":18,\"updated_by\":4}}',2,'2026-07-16 10:15:00','2026-07-16 10:15:00'),
(599,279,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-07-16 10:35:00','2026-07-16 10:35:00'),
(600,280,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":1,\"quantity\":2,\"delivery_date\":\"2026-07-11\",\"delivery_time\":\"12:00 PM\",\"total_amount\":700,\"address\":\"Corp HQ, Gulshan Avenue, Dhaka\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":18,\"updated_by\":3}}',2,'2026-07-04 10:15:00','2026-07-04 10:15:00'),
(601,280,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-10 17:30:00','2026-07-10 17:30:00'),
(602,280,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"processing\",\"to\":\"delivered\"}}}',2,'2026-07-11 12:00:00','2026-07-11 12:00:00'),
(603,281,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":2,\"quantity\":2,\"delivery_date\":\"2026-07-04\",\"delivery_time\":\"12:00 PM\",\"total_amount\":640,\"address\":\"Corp HQ, Gulshan Avenue, Dhaka\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":18,\"updated_by\":3}}',2,'2026-06-27 10:15:00','2026-06-27 10:15:00'),
(604,281,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-03 17:30:00','2026-07-03 17:30:00'),
(605,281,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"processing\",\"to\":\"delivered\"}}}',2,'2026-07-04 12:00:00','2026-07-04 12:00:00'),
(606,282,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":3,\"quantity\":2,\"delivery_date\":\"2026-06-23\",\"delivery_time\":\"12:00 PM\",\"total_amount\":900,\"address\":\"Corp HQ, Gulshan Avenue, Dhaka\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":18,\"updated_by\":3}}',2,'2026-06-16 10:15:00','2026-06-16 10:15:00'),
(607,282,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-06-16 10:35:00','2026-06-16 10:35:00'),
(608,283,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":1,\"quantity\":2,\"delivery_date\":\"2026-07-18\",\"delivery_time\":\"12:00 PM\",\"total_amount\":700,\"address\":\"Corp HQ, Gulshan Avenue, Dhaka\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":18,\"updated_by\":3}}',2,'2026-07-16 10:15:00','2026-07-16 10:15:00'),
(609,283,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-17 17:30:00','2026-07-17 17:30:00'),
(610,284,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":1,\"quantity\":2,\"delivery_date\":\"2026-07-18\",\"delivery_time\":\"12:00 PM\",\"total_amount\":700,\"address\":\"Corp HQ, Gulshan Avenue, Dhaka\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":18,\"updated_by\":3}}',2,'2026-07-16 10:15:00','2026-07-16 10:15:00'),
(611,284,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-17 17:30:00','2026-07-17 17:30:00'),
(612,285,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":1,\"quantity\":2,\"delivery_date\":\"2026-07-18\",\"delivery_time\":\"12:00 PM\",\"total_amount\":700,\"address\":\"Corp HQ, Gulshan Avenue, Dhaka\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":18,\"updated_by\":3}}',2,'2026-07-16 10:15:00','2026-07-16 10:15:00'),
(613,285,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-17 17:30:00','2026-07-17 17:30:00'),
(614,286,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":2,\"quantity\":2,\"delivery_date\":\"2026-07-18\",\"delivery_time\":\"12:00 PM\",\"total_amount\":640,\"address\":\"Corp HQ, Gulshan Avenue, Dhaka\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":18,\"updated_by\":15}}',2,'2026-07-16 10:15:00','2026-07-16 10:15:00'),
(615,287,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":2,\"quantity\":2,\"delivery_date\":\"2026-07-18\",\"delivery_time\":\"12:00 PM\",\"total_amount\":640,\"address\":\"Corp HQ, Gulshan Avenue, Dhaka\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":18,\"updated_by\":15}}',2,'2026-07-16 10:15:00','2026-07-16 10:15:00'),
(616,288,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":1,\"quantity\":2,\"delivery_date\":\"2026-07-11\",\"delivery_time\":\"12:00 PM\",\"total_amount\":700,\"address\":\"Corp HQ, Gulshan Avenue, Dhaka\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":18,\"updated_by\":15}}',2,'2026-07-04 10:15:00','2026-07-04 10:15:00'),
(617,288,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-10 17:30:00','2026-07-10 17:30:00'),
(618,288,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"processing\",\"to\":\"delivered\"}}}',2,'2026-07-11 12:00:00','2026-07-11 12:00:00'),
(619,289,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":1,\"quantity\":2,\"delivery_date\":\"2026-07-11\",\"delivery_time\":\"12:00 PM\",\"total_amount\":700,\"address\":\"Corp HQ, Gulshan Avenue, Dhaka\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":18,\"updated_by\":15}}',2,'2026-07-04 10:15:00','2026-07-04 10:15:00'),
(620,289,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"pending\",\"to\":\"processing\"}}}',2,'2026-07-10 17:30:00','2026-07-10 17:30:00'),
(621,289,'order_status_changed','{\"changes\":{\"order_status\":{\"from\":\"processing\",\"to\":\"delivered\"}}}',2,'2026-07-11 12:00:00','2026-07-11 12:00:00'),
(622,290,'created','{\"snapshot\":{\"user_id\":2,\"menu_item_id\":3,\"quantity\":2,\"delivery_date\":\"2026-07-04\",\"delivery_time\":\"12:00 PM\",\"total_amount\":900,\"address\":\"Corp HQ, Gulshan Avenue, Dhaka\",\"order_status\":\"pending\",\"payment_status\":\"pending\",\"created_by\":18,\"updated_by\":15}}',2,'2026-06-27 10:15:00','2026-06-27 10:15:00'),
(623,290,'payment_status_changed','{\"changes\":{\"payment_status\":{\"from\":\"pending\",\"to\":\"paid\"}}}',2,'2026-06-27 10:35:00','2026-06-27 10:35:00');
/*!40000 ALTER TABLE `order_logs` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `order_middo_boxes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_middo_boxes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint(20) unsigned NOT NULL,
  `middo_box_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_middo_boxes_order_id_middo_box_id_unique` (`order_id`,`middo_box_id`),
  KEY `order_middo_boxes_middo_box_id_index` (`middo_box_id`),
  CONSTRAINT `order_middo_boxes_middo_box_id_foreign` FOREIGN KEY (`middo_box_id`) REFERENCES `middo_boxes` (`id`),
  CONSTRAINT `order_middo_boxes_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `order_middo_boxes` WRITE;
/*!40000 ALTER TABLE `order_middo_boxes` DISABLE KEYS */;
INSERT INTO `order_middo_boxes` (`id`, `order_id`, `middo_box_id`, `created_at`, `updated_at`) VALUES (1,283,41,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(2,283,42,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(3,284,43,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(4,284,44,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(5,285,45,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(6,285,46,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(7,286,47,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(8,286,48,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(9,287,49,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(10,287,50,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(11,288,51,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(12,288,52,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(13,289,53,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(14,289,54,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(15,290,55,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(16,290,56,'2026-07-18 10:05:17','2026-07-18 10:05:17');
/*!40000 ALTER TABLE `order_middo_boxes` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `orders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `menu_item_id` bigint(20) unsigned NOT NULL,
  `package_subscription_id` bigint(20) unsigned DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `delivery_date` date NOT NULL,
  `delivery_time` varchar(20) NOT NULL,
  `total_amount` int(11) NOT NULL,
  `amount_paid` int(10) unsigned NOT NULL DEFAULT 0,
  `prepaid_amount` int(10) unsigned NOT NULL DEFAULT 0,
  `cash_collected` int(10) unsigned NOT NULL DEFAULT 0,
  `address` text NOT NULL,
  `receiver_name` varchar(255) DEFAULT NULL,
  `receiver_mobile` varchar(20) DEFAULT NULL,
  `area_id` bigint(20) unsigned DEFAULT NULL,
  `order_status` enum('pending','processing','packed','on_the_way_to_delivery','delivered','delivered_and_paid','cancelled','others') NOT NULL DEFAULT 'pending',
  `payment_status` enum('pending','paid','returned','failed','others') NOT NULL DEFAULT 'pending',
  `dispatched_at` timestamp NULL DEFAULT NULL,
  `delivery_rider_id` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `orders_menu_item_id_foreign` (`menu_item_id`),
  KEY `orders_created_by_foreign` (`created_by`),
  KEY `orders_updated_by_foreign` (`updated_by`),
  KEY `orders_user_id_delivery_date_index` (`user_id`,`delivery_date`),
  KEY `orders_delivery_rider_id_foreign` (`delivery_rider_id`),
  KEY `orders_area_id_foreign` (`area_id`),
  KEY `orders_package_subscription_id_foreign` (`package_subscription_id`),
  CONSTRAINT `orders_area_id_foreign` FOREIGN KEY (`area_id`) REFERENCES `areas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `orders_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `orders_delivery_rider_id_foreign` FOREIGN KEY (`delivery_rider_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `orders_menu_item_id_foreign` FOREIGN KEY (`menu_item_id`) REFERENCES `menu_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `orders_package_subscription_id_foreign` FOREIGN KEY (`package_subscription_id`) REFERENCES `package_subscriptions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `orders_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `orders_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=291 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `orders` WRITE;
/*!40000 ALTER TABLE `orders` DISABLE KEYS */;
INSERT INTO `orders` (`id`, `user_id`, `menu_item_id`, `package_subscription_id`, `quantity`, `delivery_date`, `delivery_time`, `total_amount`, `amount_paid`, `prepaid_amount`, `cash_collected`, `address`, `receiver_name`, `receiver_mobile`, `area_id`, `order_status`, `payment_status`, `dispatched_at`, `delivery_rider_id`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES (1,2,1,NULL,5,'2026-06-20','11:30 AM',1750,1750,1750,0,'Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212','Nabila Rahman','01310123452',NULL,'delivered','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(2,2,2,NULL,4,'2026-06-23','12:00 PM',1280,1280,1280,0,'Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212','Nabila Rahman','01310123452',NULL,'delivered','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(3,2,3,NULL,3,'2026-06-27','12:30 PM',1350,1350,1350,0,'Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212','Nabila Rahman','01310123452',NULL,'delivered','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(4,2,4,NULL,5,'2026-06-30','11:30 AM',2100,2100,2100,0,'Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212','Nabila Rahman','01310123452',NULL,'delivered','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(5,2,5,NULL,2,'2026-07-04','12:00 PM',800,800,800,0,'Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212','Nabila Rahman','01310123452',NULL,'delivered','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(6,2,1,NULL,4,'2026-07-08','12:30 PM',1400,1400,1400,0,'Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212','Nabila Rahman','01310123452',NULL,'delivered','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(7,2,2,NULL,5,'2026-07-11','11:30 AM',1600,1600,1600,0,'Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212','Nabila Rahman','01310123452',NULL,'delivered','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(8,2,3,NULL,3,'2026-07-14','12:00 PM',1350,1350,1350,0,'Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212','Nabila Rahman','01310123452',NULL,'delivered','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(9,2,4,NULL,4,'2026-07-16','12:30 PM',1680,1680,1680,0,'Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212','Nabila Rahman','01310123452',NULL,'delivered','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(10,2,5,NULL,2,'2026-07-17','11:30 AM',800,0,0,0,'Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212','Nabila Rahman','01310123452',NULL,'cancelled','returned',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(11,2,3,NULL,3,'2026-07-18','11:30 AM',1350,1350,1350,0,'Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212','Nabila Rahman','01310123452',NULL,'processing','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(12,2,4,NULL,5,'2026-07-19','12:00 PM',2100,0,0,0,'Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(13,2,5,NULL,4,'2026-07-20','12:30 PM',1600,0,0,0,'Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(14,2,1,NULL,2,'2026-07-22','11:30 AM',700,0,0,0,'Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(15,2,2,NULL,5,'2026-07-23','12:00 PM',1600,0,0,0,'Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(16,2,3,NULL,3,'2026-07-25','12:30 PM',1350,0,0,0,'Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(17,2,4,NULL,4,'2026-07-28','11:30 AM',1680,0,0,0,'Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(18,2,5,NULL,1,'2026-07-30','12:00 PM',400,0,0,0,'Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(19,2,3,NULL,5,'2026-07-19','12:30 PM',2250,0,0,0,'Plot 12, Block C, Banani, Dhaka 1213','Nabila Rahman','01310123452',NULL,'processing','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(20,2,3,NULL,1,'2026-07-21','11:30 AM',450,0,0,0,'Plot 12, Block C, Banani, Dhaka 1213','Nabila Rahman','01310123452',NULL,'processing','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(21,2,1,NULL,5,'2026-07-19','11:30 AM',1750,0,0,0,'Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(22,2,5,NULL,1,'2026-07-19','12:00 PM',400,0,0,0,'Plot 12, Block C, Banani, Dhaka 1213','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(23,2,1,NULL,1,'2026-07-21','12:30 PM',350,0,0,0,'Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(24,2,1,NULL,5,'2026-07-19','12:00 PM',1750,0,0,0,'Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(25,2,3,NULL,4,'2026-07-18','11:30 AM',1800,1800,1800,0,'Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(26,2,2,NULL,1,'2026-07-19','12:30 PM',320,0,0,0,'88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(27,2,5,NULL,5,'2026-07-21','11:30 AM',2000,2000,2000,0,'88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(28,2,1,NULL,3,'2026-07-20','11:30 AM',1050,1050,1050,0,'Navana Tower, Gulshan-1, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(29,2,1,NULL,1,'2026-07-18','12:00 PM',350,0,0,0,'Navana Tower, Gulshan-1, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(30,2,2,NULL,5,'2026-07-18','12:30 PM',1600,1600,1600,0,'Plot 12, Block C, Banani, Dhaka 1213','Nabila Rahman','01310123452',NULL,'pending','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(31,2,1,NULL,1,'2026-07-18','12:00 PM',350,0,0,0,'Navana Tower, Gulshan-1, Dhaka 1212','Nabila Rahman','01310123452',NULL,'processing','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(32,2,5,NULL,1,'2026-07-18','12:30 PM',400,0,0,0,'Navana Tower, Gulshan-1, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(33,2,1,NULL,4,'2026-07-20','12:00 PM',1400,1400,1400,0,'Navana Tower, Gulshan-1, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(34,2,1,NULL,2,'2026-07-19','11:30 AM',700,700,700,0,'Plot 12, Block C, Banani, Dhaka 1213','Nabila Rahman','01310123452',NULL,'pending','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(35,2,4,NULL,2,'2026-07-18','11:30 AM',840,0,0,0,'Plot 12, Block C, Banani, Dhaka 1213','Nabila Rahman','01310123452',NULL,'processing','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(36,2,5,NULL,1,'2026-07-18','11:30 AM',400,0,0,0,'Plot 12, Block C, Banani, Dhaka 1213','Nabila Rahman','01310123452',NULL,'processing','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(37,2,1,NULL,3,'2026-07-19','12:00 PM',1050,0,0,0,'Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(38,2,2,NULL,3,'2026-07-21','12:00 PM',960,960,960,0,'Navana Tower, Gulshan-1, Dhaka 1212','Nabila Rahman','01310123452',NULL,'processing','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(39,2,1,NULL,2,'2026-07-21','12:00 PM',700,700,700,0,'88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212','Nabila Rahman','01310123452',NULL,'processing','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(40,2,3,NULL,3,'2026-07-18','11:30 AM',1350,0,0,0,'Plot 12, Block C, Banani, Dhaka 1213','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(41,2,1,NULL,1,'2026-07-20','12:00 PM',350,0,0,0,'Plot 12, Block C, Banani, Dhaka 1213','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(42,2,3,NULL,4,'2026-07-19','12:30 PM',1800,0,0,0,'Plot 12, Block C, Banani, Dhaka 1213','Nabila Rahman','01310123452',NULL,'processing','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(43,2,5,NULL,5,'2026-07-19','11:30 AM',2000,0,0,0,'Plot 12, Block C, Banani, Dhaka 1213','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(44,2,1,NULL,4,'2026-07-20','11:30 AM',1400,0,0,0,'Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(45,2,1,NULL,4,'2026-07-18','12:30 PM',1400,1400,1400,0,'88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212','Nabila Rahman','01310123452',NULL,'processing','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(46,2,5,NULL,5,'2026-07-21','12:00 PM',2000,2000,2000,0,'88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212','Nabila Rahman','01310123452',NULL,'processing','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(47,2,4,NULL,4,'2026-07-18','12:00 PM',1680,0,0,0,'Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(48,2,4,NULL,4,'2026-07-18','11:30 AM',1680,1680,1680,0,'88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212','Nabila Rahman','01310123452',NULL,'processing','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(49,2,1,NULL,1,'2026-07-20','12:00 PM',350,0,0,0,'Navana Tower, Gulshan-1, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(50,2,3,NULL,5,'2026-07-21','11:30 AM',2250,2250,2250,0,'Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(51,2,5,NULL,5,'2026-07-19','12:00 PM',2000,0,0,0,'Plot 12, Block C, Banani, Dhaka 1213','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(52,2,2,NULL,3,'2026-07-18','12:30 PM',960,960,960,0,'Plot 12, Block C, Banani, Dhaka 1213','Nabila Rahman','01310123452',NULL,'pending','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(53,2,5,NULL,4,'2026-07-21','12:30 PM',1600,1600,1600,0,'Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(54,2,4,NULL,3,'2026-07-21','11:30 AM',1260,0,0,0,'Navana Tower, Gulshan-1, Dhaka 1212','Nabila Rahman','01310123452',NULL,'processing','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(55,2,4,NULL,5,'2026-07-19','12:30 PM',2100,0,0,0,'88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(56,2,1,NULL,5,'2026-07-21','12:30 PM',1750,0,0,0,'88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(57,2,4,NULL,1,'2026-07-21','11:30 AM',420,420,420,0,'Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(58,2,3,NULL,1,'2026-07-18','11:30 AM',450,0,0,0,'88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(59,2,2,NULL,4,'2026-07-20','11:30 AM',1280,0,0,0,'Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(60,2,5,NULL,5,'2026-07-19','12:30 PM',2000,0,0,0,'Navana Tower, Gulshan-1, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(61,2,2,NULL,5,'2026-07-19','12:00 PM',1600,0,0,0,'Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(62,2,2,NULL,2,'2026-07-18','12:30 PM',640,640,640,0,'Navana Tower, Gulshan-1, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(63,2,2,NULL,5,'2026-07-18','12:30 PM',1600,0,0,0,'Plot 12, Block C, Banani, Dhaka 1213','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(64,2,1,NULL,1,'2026-07-20','12:30 PM',350,350,350,0,'Plot 12, Block C, Banani, Dhaka 1213','Nabila Rahman','01310123452',NULL,'processing','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(65,2,3,NULL,2,'2026-07-19','12:00 PM',900,0,0,0,'Plot 12, Block C, Banani, Dhaka 1213','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(66,2,2,NULL,1,'2026-07-20','12:30 PM',320,320,320,0,'Navana Tower, Gulshan-1, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(67,2,5,NULL,3,'2026-07-18','12:30 PM',1200,1200,1200,0,'88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(68,2,4,NULL,5,'2026-07-19','11:30 AM',2100,2100,2100,0,'Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(69,2,2,NULL,5,'2026-07-18','12:00 PM',1600,1600,1600,0,'Plot 12, Block C, Banani, Dhaka 1213','Nabila Rahman','01310123452',NULL,'pending','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(70,2,2,NULL,5,'2026-07-19','12:30 PM',1600,1600,1600,0,'Plot 12, Block C, Banani, Dhaka 1213','Nabila Rahman','01310123452',NULL,'processing','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(71,2,2,NULL,5,'2026-07-21','12:00 PM',1600,1600,1600,0,'88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(72,2,2,NULL,1,'2026-07-18','11:30 AM',320,0,0,0,'Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(73,2,2,NULL,2,'2026-07-19','12:00 PM',640,0,0,0,'Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(74,2,4,NULL,3,'2026-07-18','12:00 PM',1260,1260,1260,0,'88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(75,2,1,NULL,3,'2026-07-18','12:00 PM',1050,1050,1050,0,'Plot 12, Block C, Banani, Dhaka 1213','Nabila Rahman','01310123452',NULL,'processing','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(76,2,1,NULL,3,'2026-07-21','12:30 PM',1050,1050,1050,0,'88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(77,2,4,NULL,1,'2026-07-20','12:30 PM',420,420,420,0,'Plot 12, Block C, Banani, Dhaka 1213','Nabila Rahman','01310123452',NULL,'pending','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(78,2,4,NULL,3,'2026-07-20','12:30 PM',1260,0,0,0,'Navana Tower, Gulshan-1, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(79,2,4,NULL,2,'2026-07-20','11:30 AM',840,0,0,0,'Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212','Nabila Rahman','01310123452',NULL,'processing','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(80,2,4,NULL,1,'2026-07-18','12:00 PM',420,0,0,0,'Navana Tower, Gulshan-1, Dhaka 1212','Nabila Rahman','01310123452',NULL,'processing','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(81,2,1,NULL,3,'2026-07-19','11:30 AM',1050,1050,1050,0,'88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(82,2,2,NULL,4,'2026-07-21','12:30 PM',1280,1280,1280,0,'Navana Tower, Gulshan-1, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(83,2,5,NULL,2,'2026-07-20','12:30 PM',800,800,800,0,'Navana Tower, Gulshan-1, Dhaka 1212','Nabila Rahman','01310123452',NULL,'processing','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(84,2,4,NULL,4,'2026-07-18','11:30 AM',1680,1680,1680,0,'Navana Tower, Gulshan-1, Dhaka 1212','Nabila Rahman','01310123452',NULL,'processing','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(85,2,5,NULL,3,'2026-07-19','12:30 PM',1200,1200,1200,0,'Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(86,2,2,NULL,3,'2026-07-18','11:30 AM',960,0,0,0,'Navana Tower, Gulshan-1, Dhaka 1212','Nabila Rahman','01310123452',NULL,'processing','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(87,2,2,NULL,1,'2026-07-19','12:30 PM',320,320,320,0,'Plot 12, Block C, Banani, Dhaka 1213','Nabila Rahman','01310123452',NULL,'pending','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(88,2,1,NULL,4,'2026-07-19','11:30 AM',1400,0,0,0,'Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(89,2,3,NULL,3,'2026-07-20','11:30 AM',1350,0,0,0,'Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212','Nabila Rahman','01310123452',NULL,'processing','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(90,2,4,NULL,3,'2026-07-18','12:00 PM',1260,1260,1260,0,'Plot 12, Block C, Banani, Dhaka 1213','Nabila Rahman','01310123452',NULL,'pending','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(91,2,4,NULL,1,'2026-07-19','12:00 PM',420,420,420,0,'Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(92,2,5,NULL,5,'2026-07-18','11:30 AM',2000,0,0,0,'88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(93,2,2,NULL,1,'2026-07-18','12:30 PM',320,320,320,0,'Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(94,2,5,NULL,5,'2026-07-21','12:30 PM',2000,0,0,0,'Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(95,2,5,NULL,5,'2026-07-18','11:30 AM',2000,0,0,0,'88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(96,2,5,NULL,1,'2026-07-20','12:30 PM',400,0,0,0,'Plot 12, Block C, Banani, Dhaka 1213','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(97,2,5,NULL,3,'2026-07-20','12:30 PM',1200,1200,1200,0,'Plot 12, Block C, Banani, Dhaka 1213','Nabila Rahman','01310123452',NULL,'pending','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(98,2,3,NULL,1,'2026-07-21','12:00 PM',450,0,0,0,'88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(99,2,5,NULL,5,'2026-07-20','12:30 PM',2000,2000,2000,0,'Plot 12, Block C, Banani, Dhaka 1213','Nabila Rahman','01310123452',NULL,'pending','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(100,2,3,NULL,5,'2026-07-20','12:00 PM',2250,2250,2250,0,'88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(101,2,5,NULL,2,'2026-07-18','12:30 PM',800,0,0,0,'Navana Tower, Gulshan-1, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(102,2,4,NULL,4,'2026-07-18','11:30 AM',1680,0,0,0,'88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(103,2,1,NULL,2,'2026-07-19','12:30 PM',700,0,0,0,'Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(104,2,2,NULL,2,'2026-07-21','12:30 PM',640,0,0,0,'Navana Tower, Gulshan-1, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(105,2,5,NULL,2,'2026-07-19','12:30 PM',800,0,0,0,'Plot 12, Block C, Banani, Dhaka 1213','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(106,2,3,NULL,5,'2026-07-19','12:30 PM',2250,0,0,0,'Navana Tower, Gulshan-1, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(107,2,2,NULL,4,'2026-07-21','12:00 PM',1280,1280,1280,0,'Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(108,2,2,NULL,5,'2026-07-19','11:30 AM',1600,0,0,0,'Plot 12, Block C, Banani, Dhaka 1213','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(109,2,5,NULL,4,'2026-07-19','12:30 PM',1600,1600,1600,0,'Plot 12, Block C, Banani, Dhaka 1213','Nabila Rahman','01310123452',NULL,'processing','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(110,2,4,NULL,3,'2026-07-21','12:00 PM',1260,1260,1260,0,'Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212','Nabila Rahman','01310123452',NULL,'processing','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(111,2,1,NULL,5,'2026-07-21','12:30 PM',1750,0,0,0,'Navana Tower, Gulshan-1, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(112,2,3,NULL,4,'2026-07-20','12:30 PM',1800,1800,1800,0,'Navana Tower, Gulshan-1, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(113,2,3,NULL,2,'2026-07-19','12:30 PM',900,900,900,0,'Navana Tower, Gulshan-1, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(114,2,2,NULL,2,'2026-07-20','12:00 PM',640,640,640,0,'Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212','Nabila Rahman','01310123452',NULL,'processing','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(115,2,2,NULL,1,'2026-07-19','11:30 AM',320,0,0,0,'Plot 12, Block C, Banani, Dhaka 1213','Nabila Rahman','01310123452',NULL,'processing','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(116,2,2,NULL,3,'2026-07-20','12:30 PM',960,960,960,0,'Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(117,2,1,NULL,2,'2026-07-21','12:30 PM',700,0,0,0,'88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(118,2,3,NULL,1,'2026-07-20','12:30 PM',450,0,0,0,'Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(119,2,2,NULL,2,'2026-07-20','12:30 PM',640,640,640,0,'Plot 12, Block C, Banani, Dhaka 1213','Nabila Rahman','01310123452',NULL,'pending','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(120,2,5,NULL,1,'2026-07-19','12:00 PM',400,0,0,0,'88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212','Nabila Rahman','01310123452',NULL,'processing','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(121,2,2,NULL,2,'2026-07-19','12:00 PM',640,640,640,0,'Navana Tower, Gulshan-1, Dhaka 1212','Nabila Rahman','01310123452',NULL,'processing','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(122,2,5,NULL,2,'2026-07-20','12:30 PM',800,800,800,0,'Plot 12, Block C, Banani, Dhaka 1213','Nabila Rahman','01310123452',NULL,'processing','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(123,2,5,NULL,1,'2026-07-21','12:30 PM',400,0,0,0,'Plot 12, Block C, Banani, Dhaka 1213','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(124,2,2,NULL,5,'2026-07-20','11:30 AM',1600,1600,1600,0,'88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(125,2,5,NULL,2,'2026-07-21','12:00 PM',800,800,800,0,'Plot 12, Block C, Banani, Dhaka 1213','Nabila Rahman','01310123452',NULL,'pending','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(126,2,3,NULL,5,'2026-07-21','12:00 PM',2250,2250,2250,0,'88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(127,2,1,NULL,5,'2026-07-21','11:30 AM',1750,1750,1750,0,'88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(128,2,3,NULL,2,'2026-07-19','11:30 AM',900,0,0,0,'88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(129,2,5,NULL,2,'2026-07-20','11:30 AM',800,800,800,0,'Plot 12, Block C, Banani, Dhaka 1213','Nabila Rahman','01310123452',NULL,'pending','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(130,2,2,NULL,1,'2026-07-20','12:00 PM',320,320,320,0,'88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(131,2,2,NULL,1,'2026-07-19','12:00 PM',320,0,0,0,'Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(132,2,5,NULL,4,'2026-07-19','12:00 PM',1600,0,0,0,'Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(133,2,1,NULL,4,'2026-07-21','11:30 AM',1400,0,0,0,'88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212','Nabila Rahman','01310123452',NULL,'processing','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(134,2,4,NULL,5,'2026-07-21','12:30 PM',2100,0,0,0,'Plot 12, Block C, Banani, Dhaka 1213','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(135,2,4,NULL,3,'2026-07-18','12:00 PM',1260,0,0,0,'Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(136,2,4,NULL,1,'2026-07-20','11:30 AM',420,0,0,0,'Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(137,2,1,NULL,3,'2026-07-18','11:30 AM',1050,0,0,0,'Plot 12, Block C, Banani, Dhaka 1213','Nabila Rahman','01310123452',NULL,'processing','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(138,2,1,NULL,1,'2026-07-21','12:30 PM',350,350,350,0,'Plot 12, Block C, Banani, Dhaka 1213','Nabila Rahman','01310123452',NULL,'pending','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(139,2,1,NULL,1,'2026-07-18','12:30 PM',350,350,350,0,'88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(140,2,4,NULL,5,'2026-07-20','11:30 AM',2100,0,0,0,'Navana Tower, Gulshan-1, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(141,2,1,NULL,1,'2026-07-19','12:30 PM',350,0,0,0,'Plot 12, Block C, Banani, Dhaka 1213','Nabila Rahman','01310123452',NULL,'processing','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(142,2,2,NULL,5,'2026-07-18','11:30 AM',1600,1600,1600,0,'88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(143,2,1,NULL,3,'2026-07-21','12:30 PM',1050,0,0,0,'88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(144,2,2,NULL,1,'2026-07-20','11:30 AM',320,0,0,0,'Navana Tower, Gulshan-1, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(145,2,2,NULL,5,'2026-07-20','12:00 PM',1600,0,0,0,'Plot 12, Block C, Banani, Dhaka 1213','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(146,2,3,NULL,2,'2026-07-19','12:00 PM',900,0,0,0,'88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(147,2,1,NULL,3,'2026-07-19','12:00 PM',1050,0,0,0,'Navana Tower, Gulshan-1, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(148,2,2,NULL,4,'2026-07-21','12:30 PM',1280,0,0,0,'Navana Tower, Gulshan-1, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(149,2,2,NULL,4,'2026-07-21','12:30 PM',1280,0,0,0,'88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(150,2,3,NULL,5,'2026-07-18','12:00 PM',2250,2250,2250,0,'Navana Tower, Gulshan-1, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(151,2,1,NULL,4,'2026-07-20','11:30 AM',1400,1400,1400,0,'88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(152,2,1,NULL,4,'2026-07-21','11:30 AM',1400,1400,1400,0,'Plot 12, Block C, Banani, Dhaka 1213','Nabila Rahman','01310123452',NULL,'pending','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(153,2,1,NULL,4,'2026-07-21','12:00 PM',1400,1400,1400,0,'88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(154,2,3,NULL,5,'2026-07-21','12:30 PM',2250,0,0,0,'Navana Tower, Gulshan-1, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(155,2,3,NULL,1,'2026-07-20','12:30 PM',450,0,0,0,'Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(156,2,4,NULL,4,'2026-07-19','12:00 PM',1680,1680,1680,0,'88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(157,2,4,NULL,5,'2026-07-20','12:30 PM',2100,2100,2100,0,'Navana Tower, Gulshan-1, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(158,2,1,NULL,2,'2026-07-18','12:30 PM',700,700,700,0,'88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(159,2,3,NULL,2,'2026-07-19','12:30 PM',900,900,900,0,'Navana Tower, Gulshan-1, Dhaka 1212','Nabila Rahman','01310123452',NULL,'processing','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(160,2,1,NULL,5,'2026-07-18','12:00 PM',1750,0,0,0,'Plot 12, Block C, Banani, Dhaka 1213','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(161,2,3,NULL,5,'2026-07-21','12:00 PM',2250,2250,2250,0,'Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212','Nabila Rahman','01310123452',NULL,'processing','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(162,2,1,NULL,3,'2026-07-18','11:30 AM',1050,1050,1050,0,'88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(163,2,1,NULL,4,'2026-07-19','11:30 AM',1400,0,0,0,'Plot 12, Block C, Banani, Dhaka 1213','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(164,2,1,NULL,3,'2026-07-21','12:00 PM',1050,1050,1050,0,'Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(165,2,4,NULL,4,'2026-07-18','11:30 AM',1680,1680,1680,0,'Navana Tower, Gulshan-1, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(166,2,3,NULL,1,'2026-07-18','12:00 PM',450,0,0,0,'88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(167,2,3,NULL,3,'2026-07-21','12:30 PM',1350,0,0,0,'Navana Tower, Gulshan-1, Dhaka 1212','Nabila Rahman','01310123452',NULL,'processing','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(168,2,5,NULL,4,'2026-07-18','12:00 PM',1600,1600,1600,0,'Navana Tower, Gulshan-1, Dhaka 1212','Nabila Rahman','01310123452',NULL,'processing','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(169,2,3,NULL,4,'2026-07-19','12:00 PM',1800,0,0,0,'Navana Tower, Gulshan-1, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(170,2,1,NULL,1,'2026-07-21','12:00 PM',350,0,0,0,'88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(171,2,3,NULL,2,'2026-07-21','12:30 PM',900,900,900,0,'Navana Tower, Gulshan-1, Dhaka 1212','Nabila Rahman','01310123452',NULL,'processing','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(172,2,2,NULL,5,'2026-07-20','12:30 PM',1600,0,0,0,'88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(173,2,5,NULL,4,'2026-07-19','12:00 PM',1600,0,0,0,'88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212','Nabila Rahman','01310123452',NULL,'processing','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(174,2,5,NULL,2,'2026-07-19','11:30 AM',800,0,0,0,'Navana Tower, Gulshan-1, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(175,2,5,NULL,3,'2026-07-21','11:30 AM',1200,0,0,0,'Plot 12, Block C, Banani, Dhaka 1213','Nabila Rahman','01310123452',NULL,'processing','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(176,2,3,NULL,2,'2026-07-18','11:30 AM',900,900,900,0,'Navana Tower, Gulshan-1, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(177,2,4,NULL,3,'2026-07-19','12:30 PM',1260,1260,1260,0,'Plot 12, Block C, Banani, Dhaka 1213','Nabila Rahman','01310123452',NULL,'pending','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(178,2,4,NULL,5,'2026-07-18','11:30 AM',2100,2100,2100,0,'Navana Tower, Gulshan-1, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(179,2,1,NULL,5,'2026-07-18','12:00 PM',1750,0,0,0,'Navana Tower, Gulshan-1, Dhaka 1212','Nabila Rahman','01310123452',NULL,'processing','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(180,2,5,NULL,2,'2026-07-18','12:30 PM',800,0,0,0,'Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(181,2,1,NULL,3,'2026-07-19','12:30 PM',1050,0,0,0,'Plot 12, Block C, Banani, Dhaka 1213','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(182,2,1,NULL,3,'2026-07-18','12:30 PM',1050,0,0,0,'Navana Tower, Gulshan-1, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(183,2,4,NULL,4,'2026-07-18','12:00 PM',1680,0,0,0,'Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212','Nabila Rahman','01310123452',NULL,'processing','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(184,2,5,NULL,5,'2026-07-19','11:30 AM',2000,2000,2000,0,'Navana Tower, Gulshan-1, Dhaka 1212','Nabila Rahman','01310123452',NULL,'processing','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(185,2,1,NULL,1,'2026-07-18','12:00 PM',350,0,0,0,'Plot 12, Block C, Banani, Dhaka 1213','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(186,2,1,NULL,5,'2026-07-19','12:00 PM',1750,0,0,0,'Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(187,2,3,NULL,4,'2026-07-20','11:30 AM',1800,1800,1800,0,'Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(188,2,1,NULL,2,'2026-07-20','11:30 AM',700,700,700,0,'Plot 12, Block C, Banani, Dhaka 1213','Nabila Rahman','01310123452',NULL,'pending','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(189,2,2,NULL,1,'2026-07-18','12:00 PM',320,320,320,0,'Navana Tower, Gulshan-1, Dhaka 1212','Nabila Rahman','01310123452',NULL,'processing','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(190,2,5,NULL,5,'2026-07-21','12:00 PM',2000,2000,2000,0,'Navana Tower, Gulshan-1, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(191,2,2,NULL,4,'2026-07-18','12:30 PM',1280,0,0,0,'88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(192,2,5,NULL,3,'2026-07-20','11:30 AM',1200,0,0,0,'88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(193,2,1,NULL,5,'2026-07-18','12:30 PM',1750,1750,1750,0,'Navana Tower, Gulshan-1, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(194,2,3,NULL,5,'2026-07-21','11:30 AM',2250,0,0,0,'Plot 12, Block C, Banani, Dhaka 1213','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(195,2,1,NULL,2,'2026-07-19','11:30 AM',700,700,700,0,'Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212','Nabila Rahman','01310123452',NULL,'processing','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(196,2,4,NULL,5,'2026-07-20','12:30 PM',2100,0,0,0,'88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(197,2,3,NULL,2,'2026-07-19','12:30 PM',900,0,0,0,'Navana Tower, Gulshan-1, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(198,2,4,NULL,4,'2026-07-18','12:30 PM',1680,1680,1680,0,'Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212','Nabila Rahman','01310123452',NULL,'processing','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(199,2,3,NULL,2,'2026-07-18','12:30 PM',900,0,0,0,'Navana Tower, Gulshan-1, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(200,2,1,NULL,4,'2026-07-20','12:30 PM',1400,1400,1400,0,'88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(201,2,1,NULL,1,'2026-07-20','12:30 PM',350,0,0,0,'Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(202,2,4,NULL,4,'2026-07-19','11:30 AM',1680,0,0,0,'Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(203,2,4,NULL,3,'2026-07-21','12:30 PM',1260,1260,1260,0,'Navana Tower, Gulshan-1, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(204,2,5,NULL,2,'2026-07-20','11:30 AM',800,0,0,0,'Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212','Nabila Rahman','01310123452',NULL,'processing','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(205,2,3,NULL,3,'2026-07-21','12:30 PM',1350,1350,1350,0,'Navana Tower, Gulshan-1, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(206,2,5,NULL,1,'2026-07-21','11:30 AM',400,400,400,0,'Plot 12, Block C, Banani, Dhaka 1213','Nabila Rahman','01310123452',NULL,'pending','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(207,2,1,NULL,1,'2026-07-19','12:00 PM',350,0,0,0,'Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212','Nabila Rahman','01310123452',NULL,'processing','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(208,2,4,NULL,4,'2026-07-21','12:00 PM',1680,1680,1680,0,'Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(209,2,4,NULL,2,'2026-07-21','12:30 PM',840,840,840,0,'88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(210,2,3,NULL,1,'2026-07-21','11:30 AM',450,450,450,0,'Plot 12, Block C, Banani, Dhaka 1213','Nabila Rahman','01310123452',NULL,'processing','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(211,2,2,NULL,4,'2026-07-20','12:00 PM',1280,1280,1280,0,'Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(212,2,3,NULL,2,'2026-07-19','11:30 AM',900,900,900,0,'Navana Tower, Gulshan-1, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(213,2,4,NULL,5,'2026-07-20','11:30 AM',2100,0,0,0,'Navana Tower, Gulshan-1, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(214,2,1,NULL,4,'2026-07-18','11:30 AM',1400,0,0,0,'Plot 12, Block C, Banani, Dhaka 1213','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(215,2,3,NULL,1,'2026-07-19','12:00 PM',450,0,0,0,'Plot 12, Block C, Banani, Dhaka 1213','Nabila Rahman','01310123452',NULL,'processing','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(216,2,2,NULL,4,'2026-07-18','12:00 PM',1280,0,0,0,'Plot 12, Block C, Banani, Dhaka 1213','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(217,2,5,NULL,3,'2026-07-18','11:30 AM',1200,0,0,0,'Navana Tower, Gulshan-1, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(218,2,1,NULL,5,'2026-07-21','11:30 AM',1750,0,0,0,'88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212','Nabila Rahman','01310123452',NULL,'pending','pending',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(219,2,1,NULL,1,'2026-07-12','12:00 PM',350,350,350,350,'Plot 12, Block C, Banani, Dhaka 1213','Nabila Rahman','01310123452',NULL,'delivered','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(220,2,4,NULL,1,'2026-07-11','11:30 AM',420,420,420,420,'88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212','Nabila Rahman','01310123452',NULL,'delivered','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(221,2,3,NULL,1,'2026-07-14','12:30 PM',450,450,450,0,'Plot 12, Block C, Banani, Dhaka 1213','Nabila Rahman','01310123452',NULL,'cancelled','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(222,2,2,NULL,3,'2026-07-17','12:30 PM',960,960,960,960,'Plot 12, Block C, Banani, Dhaka 1213','Nabila Rahman','01310123452',NULL,'delivered','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(223,2,3,NULL,2,'2026-07-13','11:30 AM',900,900,900,900,'Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212','Nabila Rahman','01310123452',NULL,'delivered','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(224,2,2,NULL,2,'2026-07-17','11:30 AM',640,640,640,640,'Plot 12, Block C, Banani, Dhaka 1213','Nabila Rahman','01310123452',NULL,'delivered','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(225,2,4,NULL,2,'2026-07-12','12:00 PM',840,840,840,840,'88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212','Nabila Rahman','01310123452',NULL,'delivered','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(226,2,2,NULL,4,'2026-07-13','12:00 PM',1280,0,0,0,'Plot 12, Block C, Banani, Dhaka 1213','Nabila Rahman','01310123452',NULL,'delivered','returned',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(227,2,5,NULL,2,'2026-07-11','12:30 PM',800,800,800,800,'Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212','Nabila Rahman','01310123452',NULL,'delivered','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(228,2,4,NULL,4,'2026-07-15','12:00 PM',1680,1680,1680,1680,'88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212','Nabila Rahman','01310123452',NULL,'delivered','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(229,2,5,NULL,3,'2026-07-17','12:30 PM',1200,1200,1200,1200,'Navana Tower, Gulshan-1, Dhaka 1212','Nabila Rahman','01310123452',NULL,'delivered','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(230,2,2,NULL,4,'2026-07-11','12:00 PM',1280,1280,1280,1280,'Plot 12, Block C, Banani, Dhaka 1213','Nabila Rahman','01310123452',NULL,'delivered','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(231,2,3,NULL,2,'2026-07-13','12:30 PM',900,900,900,900,'Plot 12, Block C, Banani, Dhaka 1213','Nabila Rahman','01310123452',NULL,'delivered','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(232,2,2,NULL,3,'2026-07-13','12:00 PM',960,960,960,960,'88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212','Nabila Rahman','01310123452',NULL,'delivered','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(233,2,3,NULL,3,'2026-07-15','12:30 PM',1350,1350,1350,1350,'Navana Tower, Gulshan-1, Dhaka 1212','Nabila Rahman','01310123452',NULL,'delivered','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(234,2,2,NULL,3,'2026-07-17','11:30 AM',960,960,960,960,'Navana Tower, Gulshan-1, Dhaka 1212','Nabila Rahman','01310123452',NULL,'delivered','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(235,2,4,NULL,2,'2026-07-11','12:30 PM',840,840,840,840,'Plot 12, Block C, Banani, Dhaka 1213','Nabila Rahman','01310123452',NULL,'delivered','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(236,2,1,NULL,4,'2026-07-13','12:00 PM',1400,1400,1400,1400,'Navana Tower, Gulshan-1, Dhaka 1212','Nabila Rahman','01310123452',NULL,'delivered','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(237,2,5,NULL,1,'2026-07-13','12:00 PM',400,400,400,400,'88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212','Nabila Rahman','01310123452',NULL,'delivered','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(238,2,4,NULL,2,'2026-07-16','12:00 PM',840,840,840,840,'88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212','Nabila Rahman','01310123452',NULL,'delivered','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(239,2,1,NULL,5,'2026-07-16','12:00 PM',1750,1750,1750,1750,'Plot 12, Block C, Banani, Dhaka 1213','Nabila Rahman','01310123452',NULL,'delivered','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(240,2,4,NULL,2,'2026-07-14','11:30 AM',840,840,840,840,'Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212','Nabila Rahman','01310123452',NULL,'delivered','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(241,2,2,NULL,1,'2026-07-15','12:30 PM',320,320,320,320,'Navana Tower, Gulshan-1, Dhaka 1212','Nabila Rahman','01310123452',NULL,'delivered','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(242,2,2,NULL,5,'2026-07-15','11:30 AM',1600,1600,1600,1600,'Navana Tower, Gulshan-1, Dhaka 1212','Nabila Rahman','01310123452',NULL,'delivered','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(243,2,2,NULL,5,'2026-07-16','12:00 PM',1600,1600,1600,1600,'Plot 12, Block C, Banani, Dhaka 1213','Nabila Rahman','01310123452',NULL,'delivered','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(244,2,2,NULL,5,'2026-07-17','12:30 PM',1600,1600,1600,1600,'Navana Tower, Gulshan-1, Dhaka 1212','Nabila Rahman','01310123452',NULL,'delivered','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(245,2,4,NULL,1,'2026-07-14','12:00 PM',420,420,420,0,'Navana Tower, Gulshan-1, Dhaka 1212','Nabila Rahman','01310123452',NULL,'cancelled','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(246,2,5,NULL,4,'2026-07-12','12:00 PM',1600,1600,1600,1600,'Plot 12, Block C, Banani, Dhaka 1213','Nabila Rahman','01310123452',NULL,'delivered','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(247,2,3,NULL,4,'2026-07-17','12:00 PM',1800,1800,1800,1800,'Plot 12, Block C, Banani, Dhaka 1213','Nabila Rahman','01310123452',NULL,'delivered','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(248,2,1,NULL,1,'2026-07-12','12:30 PM',350,350,350,350,'88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212','Nabila Rahman','01310123452',NULL,'delivered','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(249,2,4,NULL,4,'2026-07-14','12:30 PM',1680,1680,1680,0,'88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212','Nabila Rahman','01310123452',NULL,'cancelled','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(250,2,5,NULL,3,'2026-07-17','12:00 PM',1200,0,0,0,'Navana Tower, Gulshan-1, Dhaka 1212','Nabila Rahman','01310123452',NULL,'cancelled','returned',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(251,2,4,NULL,1,'2026-07-12','12:00 PM',420,0,0,0,'Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212','Nabila Rahman','01310123452',NULL,'delivered','returned',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(252,2,5,NULL,3,'2026-07-12','12:30 PM',1200,1200,1200,1200,'Navana Tower, Gulshan-1, Dhaka 1212','Nabila Rahman','01310123452',NULL,'delivered','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(253,2,5,NULL,4,'2026-07-15','12:30 PM',1600,1600,1600,0,'Navana Tower, Gulshan-1, Dhaka 1212','Nabila Rahman','01310123452',NULL,'cancelled','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(254,2,2,NULL,1,'2026-07-13','11:30 AM',320,320,320,0,'Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212','Nabila Rahman','01310123452',NULL,'cancelled','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(255,2,1,NULL,1,'2026-07-13','12:00 PM',350,350,350,350,'88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212','Nabila Rahman','01310123452',NULL,'delivered','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(256,2,1,NULL,3,'2026-07-12','12:30 PM',1050,1050,1050,1050,'88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212','Nabila Rahman','01310123452',NULL,'delivered','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(257,2,4,NULL,4,'2026-07-17','12:00 PM',1680,1680,1680,1680,'Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212','Nabila Rahman','01310123452',NULL,'delivered','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(258,2,1,NULL,5,'2026-07-16','12:30 PM',1750,0,0,0,'Plot 12, Block C, Banani, Dhaka 1213','Nabila Rahman','01310123452',NULL,'delivered','returned',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(259,2,5,NULL,3,'2026-07-12','11:30 AM',1200,1200,1200,1200,'Navana Tower, Gulshan-1, Dhaka 1212','Nabila Rahman','01310123452',NULL,'delivered','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(260,2,5,NULL,5,'2026-07-12','11:30 AM',2000,2000,2000,2000,'88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212','Nabila Rahman','01310123452',NULL,'delivered','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(261,2,4,NULL,3,'2026-07-13','11:30 AM',1260,1260,1260,0,'Navana Tower, Gulshan-1, Dhaka 1212','Nabila Rahman','01310123452',NULL,'cancelled','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(262,2,1,NULL,5,'2026-07-17','12:00 PM',1750,1750,1750,1750,'88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212','Nabila Rahman','01310123452',NULL,'delivered','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(263,2,1,NULL,4,'2026-07-17','12:00 PM',1400,1400,1400,1400,'Navana Tower, Gulshan-1, Dhaka 1212','Nabila Rahman','01310123452',NULL,'delivered','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(264,2,2,NULL,2,'2026-07-16','11:30 AM',640,640,640,0,'Level 8, Gulshan Tower, Gulshan-2, Dhaka 1212','Nabila Rahman','01310123452',NULL,'cancelled','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(265,2,5,NULL,5,'2026-07-16','11:30 AM',2000,2000,2000,2000,'Navana Tower, Gulshan-1, Dhaka 1212','Nabila Rahman','01310123452',NULL,'delivered','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(266,2,3,NULL,4,'2026-07-11','11:30 AM',1800,1800,1800,1800,'Plot 12, Block C, Banani, Dhaka 1213','Nabila Rahman','01310123452',NULL,'delivered','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(267,2,3,NULL,1,'2026-07-14','12:30 PM',450,450,450,0,'Navana Tower, Gulshan-1, Dhaka 1212','Nabila Rahman','01310123452',NULL,'cancelled','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(268,2,5,NULL,3,'2026-07-13','12:30 PM',1200,1200,1200,1200,'88 Bir Uttam AK Khandakar Road, Mohakhali, Dhaka 1212','Nabila Rahman','01310123452',NULL,'delivered','paid',NULL,NULL,2,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(269,2,1,NULL,2,'2026-07-18','12:00 PM',700,700,700,0,'Corp HQ, Gulshan Avenue, Dhaka','Nabila Rahman','01310123452',NULL,'pending','paid',NULL,NULL,18,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(270,2,2,NULL,1,'2026-07-18','12:00 PM',320,320,320,0,'Corp HQ, Gulshan Avenue, Dhaka','Nabila Rahman','01310123452',NULL,'pending','paid',NULL,NULL,18,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(271,2,1,NULL,2,'2026-07-19','12:00 PM',700,700,700,0,'Corp HQ, Gulshan Avenue, Dhaka','Nabila Rahman','01310123452',NULL,'pending','paid',NULL,NULL,18,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(272,2,3,NULL,3,'2026-07-19','12:00 PM',1350,1350,1350,0,'Corp HQ, Gulshan Avenue, Dhaka','Nabila Rahman','01310123452',NULL,'pending','paid',NULL,NULL,18,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(273,2,2,NULL,1,'2026-07-20','12:00 PM',320,320,320,0,'Corp HQ, Gulshan Avenue, Dhaka','Nabila Rahman','01310123452',NULL,'pending','paid',NULL,NULL,18,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(274,2,1,NULL,2,'2026-07-20','12:00 PM',700,700,700,0,'Corp HQ, Gulshan Avenue, Dhaka','Nabila Rahman','01310123452',NULL,'pending','paid',NULL,NULL,18,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(275,2,1,NULL,2,'2026-07-18','12:00 PM',700,700,700,0,'Corp HQ, Gulshan Avenue, Dhaka','Nabila Rahman','01310123452',NULL,'pending','paid',NULL,NULL,18,3,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(276,2,2,NULL,1,'2026-07-18','12:00 PM',320,320,320,0,'Corp HQ, Gulshan Avenue, Dhaka','Nabila Rahman','01310123452',NULL,'pending','paid',NULL,NULL,18,3,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(277,2,1,NULL,2,'2026-07-19','12:00 PM',700,700,700,0,'Corp HQ, Gulshan Avenue, Dhaka','Nabila Rahman','01310123452',NULL,'pending','paid',NULL,NULL,18,3,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(278,2,3,NULL,2,'2026-07-19','12:00 PM',900,900,900,0,'Corp HQ, Gulshan Avenue, Dhaka','Nabila Rahman','01310123452',NULL,'pending','paid',NULL,NULL,18,3,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(279,2,2,NULL,2,'2026-07-18','12:00 PM',640,640,640,0,'Corp HQ, Gulshan Avenue, Dhaka','Nabila Rahman','01310123452',NULL,'pending','paid',NULL,NULL,18,4,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(280,2,1,NULL,2,'2026-07-11','12:00 PM',700,0,0,0,'Corp HQ, Gulshan Avenue, Dhaka','Nabila Rahman','01310123452',NULL,'delivered','pending',NULL,NULL,18,3,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(281,2,2,NULL,2,'2026-07-04','12:00 PM',640,0,0,0,'Corp HQ, Gulshan Avenue, Dhaka','Nabila Rahman','01310123452',NULL,'delivered','pending',NULL,NULL,18,3,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(282,2,3,NULL,2,'2026-06-23','12:00 PM',900,900,900,0,'Corp HQ, Gulshan Avenue, Dhaka','Nabila Rahman','01310123452',NULL,'delivered_and_paid','paid',NULL,NULL,18,3,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(283,2,1,NULL,2,'2026-07-18','12:00 PM',700,0,0,0,'Corp HQ, Gulshan Avenue, Dhaka','Nabila Rahman','01310123452',NULL,'processing','pending','2026-07-18 09:35:17',NULL,18,3,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(284,2,1,NULL,2,'2026-07-18','12:00 PM',700,0,0,0,'Corp HQ, Gulshan Avenue, Dhaka','Nabila Rahman','01310123452',NULL,'processing','pending','2026-07-18 09:40:17',NULL,18,3,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(285,2,1,NULL,2,'2026-07-18','12:00 PM',700,0,0,0,'Corp HQ, Gulshan Avenue, Dhaka','Nabila Rahman','01310123452',NULL,'processing','pending','2026-07-18 09:45:17',NULL,18,3,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(286,2,2,NULL,2,'2026-07-18','12:00 PM',640,0,0,0,'Corp HQ, Gulshan Avenue, Dhaka','Nabila Rahman','01310123452',NULL,'on_the_way_to_delivery','pending','2026-07-18 08:05:17',15,18,15,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(287,2,2,NULL,2,'2026-07-18','12:00 PM',640,0,0,0,'Corp HQ, Gulshan Avenue, Dhaka','Nabila Rahman','01310123452',NULL,'on_the_way_to_delivery','pending','2026-07-18 08:05:17',15,18,15,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(288,2,1,NULL,2,'2026-07-11','12:00 PM',700,0,0,0,'Corp HQ, Gulshan Avenue, Dhaka','Nabila Rahman','01310123452',NULL,'delivered','pending','2026-07-11 11:05:17',15,18,15,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(289,2,1,NULL,2,'2026-07-11','12:00 PM',700,0,0,0,'Corp HQ, Gulshan Avenue, Dhaka','Nabila Rahman','01310123452',NULL,'delivered','pending','2026-07-11 11:05:17',15,18,15,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(290,2,3,NULL,2,'2026-07-04','12:00 PM',900,900,900,0,'Corp HQ, Gulshan Avenue, Dhaka','Nabila Rahman','01310123452',NULL,'delivered_and_paid','paid','2026-07-04 11:05:17',15,18,15,'2026-07-18 10:05:17','2026-07-18 10:05:17');
/*!40000 ALTER TABLE `orders` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `package_subscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `package_subscriptions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `meal_package_id` bigint(20) unsigned NOT NULL,
  `quantity` int(10) unsigned NOT NULL DEFAULT 1,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `omitted_weekdays` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`omitted_weekdays`)),
  `billable_days` int(10) unsigned NOT NULL DEFAULT 0,
  `price_per_day` int(10) unsigned NOT NULL,
  `total_amount` int(10) unsigned NOT NULL,
  `amount_paid` int(10) unsigned NOT NULL DEFAULT 0,
  `payment_status` varchar(255) NOT NULL DEFAULT 'pending',
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `delivery_time` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `receiver_name` varchar(255) DEFAULT NULL,
  `receiver_mobile` varchar(255) DEFAULT NULL,
  `area_id` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `package_subscriptions_user_id_foreign` (`user_id`),
  KEY `package_subscriptions_meal_package_id_foreign` (`meal_package_id`),
  KEY `package_subscriptions_area_id_foreign` (`area_id`),
  KEY `package_subscriptions_created_by_foreign` (`created_by`),
  CONSTRAINT `package_subscriptions_area_id_foreign` FOREIGN KEY (`area_id`) REFERENCES `areas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `package_subscriptions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `package_subscriptions_meal_package_id_foreign` FOREIGN KEY (`meal_package_id`) REFERENCES `meal_packages` (`id`),
  CONSTRAINT `package_subscriptions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `package_subscriptions` WRITE;
/*!40000 ALTER TABLE `package_subscriptions` DISABLE KEYS */;
/*!40000 ALTER TABLE `package_subscriptions` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `mobile` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`mobile`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `permission_role`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `permission_role` (
  `role_id` bigint(20) unsigned NOT NULL,
  `permission_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `permission_role_permission_id_foreign` (`permission_id`),
  CONSTRAINT `permission_role_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `permission_role_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `permission_role` WRITE;
/*!40000 ALTER TABLE `permission_role` DISABLE KEYS */;
INSERT INTO `permission_role` (`role_id`, `permission_id`) VALUES (1,1),
(1,2),
(1,3),
(3,1),
(3,2),
(4,2),
(5,2);
/*!40000 ALTER TABLE `permission_role` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_unique` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
INSERT INTO `permissions` (`id`, `name`, `created_at`, `updated_at`) VALUES (1,'edit-menu','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(2,'accept-order','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(3,'view-analytics','2026-07-18 10:05:17','2026-07-18 10:05:17');
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint(20) unsigned NOT NULL,
  `name` text NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  KEY `personal_access_tokens_expires_at_index` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `personal_access_tokens` WRITE;
/*!40000 ALTER TABLE `personal_access_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `personal_access_tokens` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `recipe_ingredients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `recipe_ingredients` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `recipe_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `quantity` decimal(10,3) NOT NULL DEFAULT 0.000,
  `unit` varchar(255) DEFAULT NULL,
  `cost` int(11) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `recipe_ingredients_recipe_id_foreign` (`recipe_id`),
  CONSTRAINT `recipe_ingredients_recipe_id_foreign` FOREIGN KEY (`recipe_id`) REFERENCES `recipes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `recipe_ingredients` WRITE;
/*!40000 ALTER TABLE `recipe_ingredients` DISABLE KEYS */;
INSERT INTO `recipe_ingredients` (`id`, `recipe_id`, `name`, `quantity`, `unit`, `cost`, `sort_order`, `created_at`, `updated_at`) VALUES (1,1,'Basmati rice',0.150,'kg',18,1,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(2,1,'Salt',2.000,'g',1,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(3,2,'Masoor dal',0.080,'kg',14,1,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(4,2,'Onion',0.050,'kg',4,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(5,2,'Spices',1.000,'pcs',6,3,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(6,3,'Potato',0.200,'kg',10,1,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(7,3,'Butter',0.020,'kg',8,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(8,4,'Cucumber',0.050,'kg',5,1,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(9,4,'Tomato',0.050,'kg',6,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(10,4,'Onion',0.030,'kg',3,3,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(11,5,'Chicken',0.180,'kg',55,1,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(12,5,'Onion',0.080,'kg',5,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(13,5,'Oil & spices',1.000,'pcs',12,3,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(14,6,'Beef',0.180,'kg',70,1,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(15,6,'Onion',0.080,'kg',5,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(16,6,'Oil & spices',1.000,'pcs',14,3,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(17,7,'Mixed vegetables',0.150,'kg',20,1,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(18,7,'Spices',1.000,'pcs',5,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(19,8,'Fish',0.160,'kg',60,1,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(20,8,'Mustard oil',0.020,'L',10,2,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(21,8,'Spices',1.000,'pcs',8,3,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(22,9,'Yogurt',0.100,'kg',12,1,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(23,9,'Cucumber',0.030,'kg',3,2,'2026-07-18 10:05:17','2026-07-18 10:05:17');
/*!40000 ALTER TABLE `recipe_ingredients` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `recipe_photos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `recipe_photos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `recipe_id` bigint(20) unsigned NOT NULL,
  `path` varchar(255) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `recipe_photos_recipe_id_foreign` (`recipe_id`),
  CONSTRAINT `recipe_photos_recipe_id_foreign` FOREIGN KEY (`recipe_id`) REFERENCES `recipes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `recipe_photos` WRITE;
/*!40000 ALTER TABLE `recipe_photos` DISABLE KEYS */;
/*!40000 ALTER TABLE `recipe_photos` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `recipes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `recipes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `meal_item_id` bigint(20) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `instructions` text DEFAULT NULL,
  `training_video_url` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `recipes_meal_item_id_is_active_index` (`meal_item_id`,`is_active`),
  CONSTRAINT `recipes_meal_item_id_foreign` FOREIGN KEY (`meal_item_id`) REFERENCES `meal_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `recipes` WRITE;
/*!40000 ALTER TABLE `recipes` DISABLE KEYS */;
INSERT INTO `recipes` (`id`, `meal_item_id`, `title`, `instructions`, `training_video_url`, `is_active`, `created_at`, `updated_at`) VALUES (1,1,'Standard Rice Recipe','Prepare Rice using the listed ingredients. Follow standard kitchen SOP.','https://www.youtube.com/watch?v=dQw4w9WgXcQ',1,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(2,2,'Standard Daal Recipe','Prepare Daal using the listed ingredients. Follow standard kitchen SOP.','https://www.youtube.com/watch?v=dQw4w9WgXcQ',1,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(3,3,'Standard Potato Mash Recipe','Prepare Potato Mash using the listed ingredients. Follow standard kitchen SOP.','https://www.youtube.com/watch?v=dQw4w9WgXcQ',1,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(4,4,'Standard Salad Recipe','Prepare Salad using the listed ingredients. Follow standard kitchen SOP.','https://www.youtube.com/watch?v=dQw4w9WgXcQ',1,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(5,5,'Standard Chicken Curry Recipe','Prepare Chicken Curry using the listed ingredients. Follow standard kitchen SOP.','https://www.youtube.com/watch?v=dQw4w9WgXcQ',1,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(6,6,'Standard Beef Curry Recipe','Prepare Beef Curry using the listed ingredients. Follow standard kitchen SOP.','https://www.youtube.com/watch?v=dQw4w9WgXcQ',1,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(7,7,'Standard Mixed Veg Recipe','Prepare Mixed Veg using the listed ingredients. Follow standard kitchen SOP.','https://www.youtube.com/watch?v=dQw4w9WgXcQ',1,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(8,8,'Standard Fish Curry Recipe','Prepare Fish Curry using the listed ingredients. Follow standard kitchen SOP.','https://www.youtube.com/watch?v=dQw4w9WgXcQ',1,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(9,9,'Standard Raita Recipe','Prepare Raita using the listed ingredients. Follow standard kitchen SOP.','https://www.youtube.com/watch?v=dQw4w9WgXcQ',1,'2026-07-18 10:05:17','2026-07-18 10:05:17');
/*!40000 ALTER TABLE `recipes` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_unique` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` (`id`, `name`, `created_at`, `updated_at`) VALUES (1,'admin','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(2,'corporate','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(3,'kitchen','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(4,'delivery','2026-07-18 10:05:17','2026-07-18 10:05:17'),
(5,'operation','2026-07-18 10:05:17','2026-07-18 10:05:17');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `site_pages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `site_pages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `body` longtext NOT NULL,
  `is_published` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `site_pages_slug_unique` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `site_pages` WRITE;
/*!40000 ALTER TABLE `site_pages` DISABLE KEYS */;
INSERT INTO `site_pages` (`id`, `slug`, `title`, `body`, `is_published`, `created_at`, `updated_at`) VALUES (1,'privacy','Privacy Policy','<p>Last updated: 17 July 2026</p>\n\n<p>Middo (“we”, “us”) provides corporate meal ordering and delivery services in Bangladesh. This Privacy Policy explains how we collect, use, and protect information when you use the Middo website, corporate portal, and mobile apps.</p>\n\n<h2>1. Information we collect</h2>\n<ul>\n    <li><strong>Account details</strong> — buyer name, company name, mobile number, email, delivery address, city, and area.</li>\n    <li><strong>Order activity</strong> — menus selected, quantities, delivery dates/times, payment and Middo Balance activity, and support messages.</li>\n    <li><strong>Device data</strong> — push notification tokens and basic device identifiers when you use the corporate mobile app.</li>\n    <li><strong>Operational logs</strong> — order status history and Middo Box custody events needed to fulfill deliveries.</li>\n</ul>\n\n<h2>2. How we use information</h2>\n<ul>\n    <li>Authenticate your account and communicate about orders, OTP verification, and support.</li>\n    <li>Schedule, prepare, deliver, and track meals and Middo Boxes.</li>\n    <li>Process Middo Balance top-ups and order prepayments through our payment partners or temporary checkout.</li>\n    <li>Improve service reliability, prevent fraud, and meet legal obligations.</li>\n</ul>\n\n<h2>3. Sharing</h2>\n<p>We share only what is required with kitchens, riders, and operations staff fulfilling your orders, and with payment or SMS providers acting on our instructions. We do not sell personal data.</p>\n\n<h2>4. Retention &amp; security</h2>\n<p>We retain account and order records for as long as needed to provide the service and meet accounting or legal requirements. Access is limited to authorized Middo roles and protected with industry-standard safeguards.</p>\n\n<h2>5. Your choices</h2>\n<p>You may update profile details from the corporate portal or app. For account closure, balance questions, or privacy requests, contact Middo support via the Contact page or in-app support on an order.</p>\n\n<h2>6. Contact</h2>\n<p>Questions about this policy: use the Middo Contact form or email the address published on our Contact page.</p>',1,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(2,'terms','Terms & Conditions','<p>Last updated: 17 July 2026</p>\n\n<p>These Terms &amp; Conditions govern use of Middo’s corporate meal platform (website, portal, and mobile apps). By creating an account or placing an order, you agree to these terms.</p>\n\n<h2>1. Who may use Middo</h2>\n<p>Corporate accounts are for office buyers ordering meals for workplace delivery. Kitchen and delivery accounts are issued separately by Middo operations. You must provide accurate buyer and delivery details and keep your login credentials secure.</p>\n\n<h2>2. Orders &amp; scheduling</h2>\n<ul>\n    <li>Orders may be placed for eligible menu items and delivery windows shown in the product.</li>\n    <li>While an order remains <strong>pending</strong> (before kitchen dispatch), you may edit quantities or cancel it from Scheduled / Active Orders.</li>\n    <li>After kitchen acceptance or dispatch, changes may no longer be available; contact support for help.</li>\n</ul>\n\n<h2>3. Pricing, Middo Balance &amp; payments</h2>\n<ul>\n    <li>Menu prices are shown in Bangladeshi Taka (৳).</li>\n    <li>Middo Balance can be topped up through the payment checkout presented in the product (including our temporary pseudo gateway during development).</li>\n    <li>Some orders require prepayment (for example when the receiver differs from the account holder, or when active-order limits apply). Unpaid required prepayment blocks scheduling.</li>\n    <li>Unused Middo Balance remains on the account for future orders unless Middo support agrees otherwise.</li>\n</ul>\n\n<h2>4. Delivery &amp; Middo Boxes</h2>\n<p>Meals are delivered in Middo Boxes. Empty boxes remaining at your office stay in your custody until a Middo rider collects them on a subsequent delivery or pickup run. Please keep boxes accessible and undamaged.</p>\n\n<h2>5. Acceptable use</h2>\n<p>Do not misuse the platform, attempt unauthorized access, or submit abusive content in support threads. Middo may suspend accounts that violate these terms or applicable law.</p>\n\n<h2>6. Limitation of liability</h2>\n<p>Middo works with partner kitchens and riders to fulfill orders. To the extent permitted by law, Middo is not liable for indirect or consequential losses. Nothing in these terms limits rights you cannot waive under Bangladesh law.</p>\n\n<h2>7. Changes</h2>\n<p>We may update these terms and will revise the “Last updated” date above. Continued use after changes means you accept the updated terms.</p>\n\n<h2>8. Contact</h2>\n<p>For questions about these terms, use the Middo Contact page.</p>',1,'2026-07-18 10:05:17','2026-07-18 10:05:17');
/*!40000 ALTER TABLE `site_pages` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `first_name` varchar(255) NOT NULL,
  `last_name` varchar(255) NOT NULL,
  `company_name` varchar(255) DEFAULT NULL,
  `full_name` varchar(255) GENERATED ALWAYS AS (concat(`first_name`,' ',`last_name`)) VIRTUAL,
  `balance` int(11) NOT NULL DEFAULT 0,
  `email` varchar(255) DEFAULT NULL,
  `mobile` varchar(20) NOT NULL,
  `alt_mobile` varchar(20) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `address` text DEFAULT NULL,
  `area` varchar(255) DEFAULT NULL,
  `city` varchar(255) DEFAULT NULL,
  `role_id` bigint(20) unsigned DEFAULT NULL,
  `status` enum('inactive','active','pending') NOT NULL DEFAULT 'inactive',
  `is_mobile_verified` tinyint(1) NOT NULL DEFAULT 0,
  `city_id` bigint(20) unsigned DEFAULT NULL,
  `area_id` bigint(20) unsigned DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_mobile_unique` (`mobile`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_role_id_foreign` (`role_id`),
  KEY `users_city_id_foreign` (`city_id`),
  KEY `users_area_id_foreign` (`area_id`),
  CONSTRAINT `users_area_id_foreign` FOREIGN KEY (`area_id`) REFERENCES `areas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `users_city_id_foreign` FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`) ON DELETE SET NULL,
  CONSTRAINT `users_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` (`id`, `first_name`, `last_name`, `company_name`, `full_name`, `balance`, `email`, `mobile`, `alt_mobile`, `password`, `address`, `area`, `city`, `role_id`, `status`, `is_mobile_verified`, `city_id`, `area_id`, `remember_token`, `created_at`, `updated_at`) VALUES (1,'Admin User','Admin',NULL,'Admin User Admin',0,'admin@middo.com','01310123451',NULL,'$2y$12$pfPu91UWF4a8CSb.fYrgyO8P7C6eWKauko2E4BUUuWE6/QL7YQO5G',NULL,NULL,NULL,1,'active',1,NULL,NULL,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(2,'Nabila','Rahman','Middo Demo Corp','Nabila Rahman',50000,'corporate@middo.com','01310123452',NULL,'$2y$12$pfPu91UWF4a8CSb.fYrgyO8P7C6eWKauko2E4BUUuWE6/QL7YQO5G','House 12, Road 5, Gulshan',NULL,NULL,2,'active',1,1,2,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(3,'Gulshan','Kitchen',NULL,'Gulshan Kitchen',0,'kitchen@middo.com','01310123453',NULL,'$2y$12$pfPu91UWF4a8CSb.fYrgyO8P7C6eWKauko2E4BUUuWE6/QL7YQO5G','Road 45, Gulshan Ave',NULL,NULL,3,'active',1,1,2,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(4,'Banani','Kitchen',NULL,'Banani Kitchen',0,'kitchen2@middo.com','01310123456',NULL,'$2y$12$pfPu91UWF4a8CSb.fYrgyO8P7C6eWKauko2E4BUUuWE6/QL7YQO5G','Block C, Banani',NULL,NULL,3,'active',1,1,3,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(5,'Mohakhali','Kitchen',NULL,'Mohakhali Kitchen',0,'kitchen3@middo.com','01310123457',NULL,'$2y$12$pfPu91UWF4a8CSb.fYrgyO8P7C6eWKauko2E4BUUuWE6/QL7YQO5G','Mohakhali DOHS',NULL,NULL,3,'active',1,1,2,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(6,'Baridhara','Kitchen',NULL,'Baridhara Kitchen',0,'kitchen4@middo.com','01310123458',NULL,'$2y$12$pfPu91UWF4a8CSb.fYrgyO8P7C6eWKauko2E4BUUuWE6/QL7YQO5G','Baridhara Diplomatic Zone',NULL,NULL,3,'active',1,1,4,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(7,'Dhanmondi','Kitchen',NULL,'Dhanmondi Kitchen',0,'kitchen5@middo.com','01310123459',NULL,'$2y$12$pfPu91UWF4a8CSb.fYrgyO8P7C6eWKauko2E4BUUuWE6/QL7YQO5G','Road 27, Dhanmondi',NULL,NULL,3,'active',1,1,1,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(8,'Uttara','Kitchen',NULL,'Uttara Kitchen',0,'pending.kitchen1@middo.com','01310123501',NULL,'$2y$12$pfPu91UWF4a8CSb.fYrgyO8P7C6eWKauko2E4BUUuWE6/QL7YQO5G','Sector 7, Uttara',NULL,NULL,3,'pending',0,1,1,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(9,'Mirpur','Kitchen',NULL,'Mirpur Kitchen',0,'pending.kitchen2@middo.com','01310123502',NULL,'$2y$12$pfPu91UWF4a8CSb.fYrgyO8P7C6eWKauko2E4BUUuWE6/QL7YQO5G','Mirpur 10 Roundabout',NULL,NULL,3,'pending',0,1,1,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(10,'Motijheel','Kitchen',NULL,'Motijheel Kitchen',0,'pending.kitchen3@middo.com','01310123503',NULL,'$2y$12$pfPu91UWF4a8CSb.fYrgyO8P7C6eWKauko2E4BUUuWE6/QL7YQO5G','Dilkhusha, Motijheel',NULL,NULL,3,'pending',0,1,2,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(11,'Bashundhara','Kitchen',NULL,'Bashundhara Kitchen',0,'pending.kitchen4@middo.com','01310123504',NULL,'$2y$12$pfPu91UWF4a8CSb.fYrgyO8P7C6eWKauko2E4BUUuWE6/QL7YQO5G','Block B, Bashundhara R/A',NULL,NULL,3,'pending',0,1,4,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(12,'Wari','Kitchen',NULL,'Wari Kitchen',0,'pending.kitchen5@middo.com','01310123505',NULL,'$2y$12$pfPu91UWF4a8CSb.fYrgyO8P7C6eWKauko2E4BUUuWE6/QL7YQO5G','Rankin Street, Wari',NULL,NULL,3,'pending',0,1,1,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(13,'Tejgaon','Kitchen',NULL,'Tejgaon Kitchen',0,'pending.kitchen6@middo.com','01310123506',NULL,'$2y$12$pfPu91UWF4a8CSb.fYrgyO8P7C6eWKauko2E4BUUuWE6/QL7YQO5G','Industrial Area, Tejgaon',NULL,NULL,3,'pending',0,1,3,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(14,'Farmgate','Kitchen',NULL,'Farmgate Kitchen',0,'pending.kitchen7@middo.com','01310123507',NULL,'$2y$12$pfPu91UWF4a8CSb.fYrgyO8P7C6eWKauko2E4BUUuWE6/QL7YQO5G','Green Road, Farmgate',NULL,NULL,3,'pending',0,1,3,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(15,'Rahim','Uddin',NULL,'Rahim Uddin',0,'delivery@middo.com','01310123454',NULL,'$2y$12$pfPu91UWF4a8CSb.fYrgyO8P7C6eWKauko2E4BUUuWE6/QL7YQO5G',NULL,NULL,NULL,4,'active',1,NULL,NULL,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(16,'Karim','Ahmed',NULL,'Karim Ahmed',0,'delivery2@middo.com','01310123460',NULL,'$2y$12$pfPu91UWF4a8CSb.fYrgyO8P7C6eWKauko2E4BUUuWE6/QL7YQO5G',NULL,NULL,NULL,4,'active',1,NULL,NULL,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(17,'Jamal','Hossain',NULL,'Jamal Hossain',0,'delivery3@middo.com','01310123461',NULL,'$2y$12$pfPu91UWF4a8CSb.fYrgyO8P7C6eWKauko2E4BUUuWE6/QL7YQO5G',NULL,NULL,NULL,4,'active',1,NULL,NULL,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17'),
(18,'Operation User','Operation',NULL,'Operation User Operation',0,'operations@middo.com','01310123455',NULL,'$2y$12$pfPu91UWF4a8CSb.fYrgyO8P7C6eWKauko2E4BUUuWE6/QL7YQO5G',NULL,NULL,NULL,5,'active',1,NULL,NULL,NULL,'2026-07-18 10:05:17','2026-07-18 10:05:17');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `wallet_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `wallet_transactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `type` varchar(32) NOT NULL,
  `amount` int(11) NOT NULL,
  `balance_after` int(11) NOT NULL,
  `reference_type` varchar(255) DEFAULT NULL,
  `reference_id` bigint(20) unsigned DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `gateway_token` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `wallet_transactions_user_id_created_at_index` (`user_id`,`created_at`),
  KEY `wallet_transactions_gateway_token_index` (`gateway_token`),
  CONSTRAINT `wallet_transactions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `wallet_transactions` WRITE;
/*!40000 ALTER TABLE `wallet_transactions` DISABLE KEYS */;
/*!40000 ALTER TABLE `wallet_transactions` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

