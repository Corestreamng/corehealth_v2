-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 15, 2023 at 09:12 PM
-- Server version: 10.4.25-MariaDB
-- PHP Version: 7.4.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `_corehealth_db_v2`
--

-- --------------------------------------------------------

--
-- Table structure for table `application_status`
--

CREATE TABLE `application_status` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `site_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `site_abbreviation` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `header_text` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `footer_text` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `logo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `favicon` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_address` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_phones` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_emails` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `social_links` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `version` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `debug_mode` tinyint(1) NOT NULL DEFAULT 1,
  `allow_piece_sale` tinyint(1) NOT NULL DEFAULT 1,
  `allow_halve_sale` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `application_status`
--

INSERT INTO `application_status` (`id`, `site_name`, `site_abbreviation`, `header_text`, `footer_text`, `logo`, `favicon`, `contact_address`, `contact_phones`, `contact_emails`, `social_links`, `description`, `version`, `active`, `debug_mode`, `allow_piece_sale`, `allow_halve_sale`, `created_at`, `updated_at`) VALUES
(1, 'Hospital Management System', 'HMS', 'Hospital Management System', 'HMS', NULL, NULL, 'P.M.B 204 Jos Plateau State Nigeria', '070-01010101', NULL, NULL, NULL, 'Ver. 1.0', 1, 0, 0, 0, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `clinics`
--

CREATE TABLE `clinics` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `clinics`
--

INSERT INTO `clinics` (`id`, `name`, `status`, `created_at`, `updated_at`) VALUES
(1, 'GOPD', 1, '2023-03-14 18:22:51', NULL),
(2, 'Dental', 1, '2023-03-13 23:00:00', NULL),
(3, 'Opthalmology', 1, '0000-00-00 00:00:00', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `details`
--

CREATE TABLE `details` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `service_rendered` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `price` int(11) NOT NULL,
  `patient_id` bigint(20) UNSIGNED NOT NULL,
  `has_paid` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `doctor_queues`
--

CREATE TABLE `doctor_queues` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `patient_id` bigint(20) UNSIGNED NOT NULL,
  `clinic_id` bigint(20) UNSIGNED NOT NULL,
  `staff_id` bigint(20) UNSIGNED DEFAULT NULL,
  `receptionist_id` bigint(20) UNSIGNED NOT NULL,
  `request_entry_id` bigint(20) UNSIGNED NOT NULL,
  `status` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `doctor_queues`
--

INSERT INTO `doctor_queues` (`id`, `patient_id`, `clinic_id`, `staff_id`, `receptionist_id`, `request_entry_id`, `status`, `created_at`, `updated_at`) VALUES
(4, 1, 1, 2, 2, 5, 1, '2023-03-15 11:38:19', '2023-03-15 11:38:19'),
(5, 1, 1, 2, 2, 7, 1, '2023-03-15 17:24:32', '2023-03-15 17:24:32');

-- --------------------------------------------------------

--
-- Table structure for table `failed_jobs`
--

CREATE TABLE `failed_jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `hmos`
--

CREATE TABLE `hmos` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `desc` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `discount` double(8,2) NOT NULL DEFAULT 0.00,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `hmos`
--

INSERT INTO `hmos` (`id`, `name`, `desc`, `discount`, `status`, `created_at`, `updated_at`) VALUES
(1, 'PLASCHEMA/United Health', 'PLASCHEMA/United Health', 90.00, 1, '2023-03-06 16:15:51', '2023-03-06 16:15:51'),
(2, 'NHIS/Hygia', 'NHIS/Hygia', 90.00, 1, '2023-03-06 16:15:51', '2023-03-06 16:15:51'),
(3, 'NHIS/Reliance', 'NHIS/Reliance', 90.00, 1, '2023-03-06 19:15:33', '2023-03-06 19:15:33'),
(4, 'NHIS/Axa Mansard up', 'NHIS/Axa Mansard', 10.00, 1, '2023-03-06 19:17:32', '2023-03-06 19:33:17'),
(5, 'NHIS/United', 'NHIS/United', 80.90, 1, '2023-03-06 19:20:01', '2023-03-06 19:20:01');

-- --------------------------------------------------------

--
-- Table structure for table `invoices`
--

CREATE TABLE `invoices` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '2012_12_31_115242_create_user_categories_table', 1),
(2, '2014_10_12_000000_create_users_table', 1),
(3, '2014_10_12_100000_create_password_resets_table', 1),
(4, '2019_08_19_000000_create_failed_jobs_table', 1),
(5, '2019_12_14_000001_create_personal_access_tokens_table', 1),
(6, '2022_12_14_131217_create_permission_tables', 1),
(7, '2022_12_21_131945_create_patients_table', 1),
(8, '2022_12_24_071959_create_services_table', 1),
(9, '2022_12_24_072106_create_invoices_table', 1),
(10, '2022_12_25_092034_create_specializations_table', 1),
(12, '2022_12_25_14641_create_clinics_table', 1),
(14, '2022_12_27_052633_create_details_table', 1),
(15, '2022_12_25_121754_create_staff_table', 2),
(16, '2023_03_06_141358_create_hmos_table', 3),
(19, '2023_03_07_095827_create_product_categories_table', 4),
(20, '2023_03_07_101114_create_service_categories_table', 4),
(21, '2023_03_07_101741_create_services_table', 4),
(22, '2023_03_07_101825_create_products_table', 4),
(23, '2023_03_07_195515_create_stocks_table', 4),
(24, '2023_03_07_200143_create_prices_table', 4),
(25, '2023_03_08_092249_create_promotions_table', 4),
(26, '2023_03_08_092403_create_store_stocks_table', 4),
(27, '2023_03_08_102444_create_service_prices_table', 4),
(30, '2023_03_08_134159_create_application_status_table', 5),
(32, '2023_03_08_104809_create_stores_table', 6),
(33, '2022_12_26_114446_create_doctor_queues_table', 7),
(34, '2023_03_08_151753_create_stock_orders_table', 8),
(35, '2023_03_09_024120_create_sales_table', 8),
(36, '2023_03_09_025436_create_product_or_service_requests_table', 8),
(37, '2023_03_09_111456_create_stock_invoices_table', 8),
(38, '2023_03_09_111606_create_suppliers_table', 8);

-- --------------------------------------------------------

--
-- Table structure for table `model_has_permissions`
--

CREATE TABLE `model_has_permissions` (
  `permission_id` bigint(20) UNSIGNED NOT NULL,
  `model_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `model_has_permissions`
--

INSERT INTO `model_has_permissions` (`permission_id`, `model_type`, `model_id`) VALUES
(1, 'App\\Models\\User', 4),
(1, 'App\\Models\\User', 12);

-- --------------------------------------------------------

--
-- Table structure for table `model_has_roles`
--

CREATE TABLE `model_has_roles` (
  `role_id` bigint(20) UNSIGNED NOT NULL,
  `model_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `model_has_roles`
--

INSERT INTO `model_has_roles` (`role_id`, `model_type`, `model_id`) VALUES
(1, 'App\\Models\\User', 4),
(1, 'App\\Models\\User', 12);

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `patients`
--

CREATE TABLE `patients` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `file_no` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `insurance_scheme` bigint(20) UNSIGNED DEFAULT NULL,
  `hmo_id` bigint(20) UNSIGNED DEFAULT NULL,
  `hmo_no` bigint(20) UNSIGNED DEFAULT NULL,
  `gender` enum('Male','Female','Others') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dob` timestamp NULL DEFAULT NULL,
  `blood_group` enum('A+','A-','B+','B-','AB+','AB-','O+','O-','Others') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `genotype` enum('AA','AS','AC','SS','SC','Others') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `disability` int(11) NOT NULL DEFAULT 0,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nationality` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ethnicity` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `misc` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `patients`
--

INSERT INTO `patients` (`id`, `user_id`, `file_no`, `insurance_scheme`, `hmo_id`, `hmo_no`, `gender`, `dob`, `blood_group`, `genotype`, `disability`, `address`, `nationality`, `ethnicity`, `misc`, `created_at`, `updated_at`) VALUES
(1, 2, '6464645', 1, NULL, NULL, 'Female', '2023-01-03 23:00:00', 'AB+', 'AS', 1, 'Elwazir Street,bosso\r\nVcm 105 Elwazir Estate', 'nig', 'Minna', NULL, '2023-01-05 08:11:45', '2023-01-05 08:11:45'),
(2, 17, '6464645', 2, NULL, NULL, 'Male', '2023-03-27 23:00:00', 'B+', 'AS', 0, NULL, NULL, NULL, NULL, '2023-03-06 12:42:41', '2023-03-06 12:42:41'),
(3, 18, '6573', NULL, 2, 4554666644, 'Male', '2021-03-13 23:00:00', 'A+', 'AA', 0, 'Dankankani village, Furaka district, Bauchi ring road', 'Nigerian', 'Youruba', 'Epilleptic', '2023-03-06 15:24:43', '2023-03-06 16:46:58');

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `permissions`
--

INSERT INTO `permissions` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES
(1, 'can-add-staff', 'web', '2023-01-05 08:36:59', '2023-01-05 08:36:59'),
(2, 'can-manage-product-categories', 'web', '2023-03-08 10:25:10', '2023-03-08 10:25:10'),
(3, 'can-manage-products', 'web', '2023-03-08 10:28:51', '2023-03-08 10:28:51'),
(4, 'can-manage-service-categories', 'web', '2023-03-08 10:29:18', '2023-03-08 10:29:18'),
(5, 'can-manage-services', 'web', '2023-03-08 10:29:51', '2023-03-08 10:29:51'),
(6, 'can-manage-roles', 'web', '2023-03-08 10:30:14', '2023-03-08 10:30:14'),
(7, 'can-manage-permissions', 'web', '2023-03-08 10:30:40', '2023-03-08 10:30:40'),
(8, 'can-manage-store', 'web', '2023-03-09 01:03:14', '2023-03-09 01:03:14');

-- --------------------------------------------------------

--
-- Table structure for table `personal_access_tokens`
--

CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tokenable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `prices`
--

CREATE TABLE `prices` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `pr_buy_price` int(11) NOT NULL DEFAULT 0,
  `initial_sale_price` int(11) NOT NULL DEFAULT 0,
  `initial_sale_date` date DEFAULT NULL,
  `current_sale_price` double(8,2) NOT NULL DEFAULT 0.00,
  `half_price` int(11) NOT NULL DEFAULT 0,
  `pieces_price` int(11) NOT NULL DEFAULT 0,
  `pieces_max_discount` int(11) NOT NULL DEFAULT 0,
  `current_sale_date` date DEFAULT NULL,
  `max_discount` int(11) NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `prices`
--

INSERT INTO `prices` (`id`, `product_id`, `pr_buy_price`, `initial_sale_price`, `initial_sale_date`, `current_sale_price`, `half_price`, `pieces_price`, `pieces_max_discount`, `current_sale_date`, `max_discount`, `status`, `created_at`, `updated_at`) VALUES
(1, 2, 100, 189, '2023-03-09', 189.00, 0, 0, 0, '2023-03-09', 0, 1, '2023-03-09 13:40:02', '2023-03-09 13:51:27'),
(2, 3, 100, 300, '2023-03-09', 300.00, 0, 0, 0, '2023-03-09', 10, 1, '2023-03-09 13:54:33', '2023-03-09 13:54:33'),
(3, 4, 100, 120, '2023-03-09', 120.00, 0, 0, 0, '2023-03-09', 0, 1, '2023-03-09 14:03:10', '2023-03-09 14:03:47');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `category_id` bigint(20) UNSIGNED NOT NULL,
  `product_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reorder_alert` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `has_have` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `has_piece` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `howmany_to` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `current_quantity` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `stock_assign` tinyint(1) NOT NULL DEFAULT 0,
  `price_assign` tinyint(1) NOT NULL DEFAULT 0,
  `promotion` int(11) NOT NULL DEFAULT 0,
  `1` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `user_id`, `category_id`, `product_name`, `product_code`, `reorder_alert`, `has_have`, `has_piece`, `howmany_to`, `current_quantity`, `status`, `stock_assign`, `price_assign`, `promotion`, `1`, `created_at`, `updated_at`) VALUES
(2, 12, 1, 'Diclofenac', 'DIaC', '4999', '0', '0', '0', '100', 1, 1, 1, 0, 0, '2023-03-08 13:40:35', '2023-03-09 13:40:02'),
(3, 12, 2, 'Parecetamol', 'PCM', '12', '0', '0', '0', '388', 1, 1, 1, 0, 0, '2023-03-08 13:44:38', '2023-03-09 13:54:33'),
(4, 12, 1, 'metrodonizadole', 'MTR', '10', '0', '0', '0', '20', 1, 1, 1, 0, 0, '2023-03-09 14:01:46', '2023-03-09 14:03:10');

-- --------------------------------------------------------

--
-- Table structure for table `product_categories`
--

CREATE TABLE `product_categories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `category_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category_description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_categories`
--

INSERT INTO `product_categories` (`id`, `category_name`, `category_code`, `category_description`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Tabletsuuus', 'TB', 'test', 1, '2023-03-08 10:17:32', '2023-03-08 11:27:14'),
(2, 'Tablets', 'TB', 'test', 1, '2023-03-08 10:18:30', '2023-03-08 10:18:30');

-- --------------------------------------------------------

--
-- Table structure for table `product_or_service_requests`
--

CREATE TABLE `product_or_service_requests` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `invoice_id` bigint(20) UNSIGNED DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `staff_user_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED DEFAULT NULL,
  `service_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_or_service_requests`
--

INSERT INTO `product_or_service_requests` (`id`, `invoice_id`, `user_id`, `staff_user_id`, `product_id`, `service_id`, `created_at`, `updated_at`) VALUES
(1, NULL, 2, 12, NULL, 1, '2023-03-15 11:03:15', '2023-03-15 11:03:15'),
(2, NULL, 2, 12, NULL, 1, '2023-03-15 11:26:19', '2023-03-15 11:26:19'),
(3, NULL, 2, 12, NULL, 1, '2023-03-15 11:32:51', '2023-03-15 11:32:51'),
(4, NULL, 2, 12, NULL, 1, '2023-03-15 11:36:24', '2023-03-15 11:36:24'),
(5, NULL, 2, 12, NULL, 1, '2023-03-15 11:38:19', '2023-03-15 11:38:19'),
(6, NULL, 2, 12, NULL, 1, '2023-03-15 17:17:38', '2023-03-15 17:17:38'),
(7, NULL, 2, 12, NULL, 1, '2023-03-15 17:24:32', '2023-03-15 17:24:32'),
(8, NULL, 2, 12, NULL, 1, '2023-03-15 17:28:42', '2023-03-15 17:28:42');

-- --------------------------------------------------------

--
-- Table structure for table `promotions`
--

CREATE TABLE `promotions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `promotion_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity_to_buy` int(11) NOT NULL DEFAULT 0,
  `quantity_to_give` int(11) NOT NULL DEFAULT 0,
  `promotion_total_quantity` int(11) NOT NULL DEFAULT 0,
  `start_date` timestamp NULL DEFAULT NULL,
  `end_date` timestamp NULL DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `current_qt` int(11) NOT NULL DEFAULT 0,
  `give_qt` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES
(1, 'ADMIN', 'web', '2023-01-05 08:37:43', '2023-01-05 08:37:43'),
(2, 'RECEPTIONIST', 'web', '2023-03-06 16:49:53', '2023-03-06 16:49:53'),
(3, 'DOCTOR', 'web', '2023-03-06 16:50:33', '2023-03-06 16:50:33'),
(4, 'PATIENT', 'web', '2023-03-06 16:52:48', '2023-03-06 16:52:48'),
(5, 'PHARMACIST', 'web', '2023-03-06 16:54:25', '2023-03-06 16:55:09'),
(6, 'LAB SCIENTIST', 'web', '2023-03-06 16:56:15', '2023-03-06 16:56:15'),
(7, 'AUDIT', 'web', '2023-03-06 16:56:52', '2023-03-06 16:56:52'),
(8, 'ACCOUNTS', 'web', '2023-03-06 16:57:18', '2023-03-06 16:57:18'),
(9, 'NURSE', 'web', '2023-03-06 16:57:56', '2023-03-06 16:57:56'),
(10, 'RADIOLOGIST', 'web', '2023-03-06 16:58:45', '2023-03-06 16:58:45'),
(11, 'STORE', 'web', '2023-03-06 16:59:06', '2023-03-06 16:59:06');

-- --------------------------------------------------------

--
-- Table structure for table `role_has_permissions`
--

CREATE TABLE `role_has_permissions` (
  `permission_id` bigint(20) UNSIGNED NOT NULL,
  `role_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `role_has_permissions`
--

INSERT INTO `role_has_permissions` (`permission_id`, `role_id`) VALUES
(1, 1),
(1, 2),
(1, 3),
(1, 4),
(1, 5),
(1, 6),
(1, 7),
(1, 8),
(1, 9),
(1, 10),
(2, 1),
(2, 11),
(3, 1),
(3, 11),
(4, 1),
(4, 11),
(5, 1),
(5, 11),
(6, 1),
(7, 1),
(8, 1),
(8, 11);

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_or_service_requests_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED DEFAULT NULL,
  `service_id` bigint(20) UNSIGNED DEFAULT NULL,
  `budget_year_id` bigint(20) UNSIGNED DEFAULT NULL,
  `serial_no` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantity_buy` int(11) NOT NULL,
  `sale_price` double(8,2) NOT NULL,
  `pieces_quantity` int(11) DEFAULT NULL,
  `pieces_sales_price` int(11) DEFAULT NULL,
  `total_amount` int(11) NOT NULL,
  `store_id` bigint(20) UNSIGNED NOT NULL,
  `promo_qt` int(11) DEFAULT NULL,
  `gain` double(8,2) NOT NULL,
  `loss` double(8,2) NOT NULL,
  `sale_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `supply` int(11) NOT NULL,
  `supply_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `category_id` bigint(20) UNSIGNED NOT NULL,
  `service_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `service_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `price_assign` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`id`, `user_id`, `category_id`, `service_name`, `service_code`, `status`, `price_assign`, `created_at`, `updated_at`) VALUES
(1, 12, 1, 'Gynecology  First Visi', 'OBGYNF', 1, 1, '2023-03-11 16:17:01', '2023-03-14 09:35:56');

-- --------------------------------------------------------

--
-- Table structure for table `service_categories`
--

CREATE TABLE `service_categories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `category_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category_description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `service_categories`
--

INSERT INTO `service_categories` (`id`, `category_name`, `category_code`, `category_description`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Consultation', 'CONS', 'Consultations', 1, '2023-03-11 11:27:03', '2023-03-11 11:32:52');

-- --------------------------------------------------------

--
-- Table structure for table `service_prices`
--

CREATE TABLE `service_prices` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `service_id` bigint(20) UNSIGNED NOT NULL,
  `cost_price` int(11) NOT NULL DEFAULT 0,
  `sale_price` int(11) NOT NULL DEFAULT 0,
  `max_discount` int(11) NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `service_prices`
--

INSERT INTO `service_prices` (`id`, `service_id`, `cost_price`, `sale_price`, `max_discount`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 1999, 7770, 888, 1, '2023-03-14 09:35:55', '2023-03-14 09:46:16');

-- --------------------------------------------------------

--
-- Table structure for table `specializations`
--

CREATE TABLE `specializations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `specializations`
--

INSERT INTO `specializations` (`id`, `name`, `status`, `created_at`, `updated_at`) VALUES
(1, 'General Physician', 1, NULL, NULL),
(2, 'Gynacologist', 1, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `staff`
--

CREATE TABLE `staff` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `specialization_id` bigint(20) UNSIGNED DEFAULT NULL,
  `clinic_id` bigint(20) UNSIGNED DEFAULT NULL,
  `gender` enum('Male','Female','Others') COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_of_birth` timestamp NULL DEFAULT NULL,
  `home_address` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `consultation_fee` double(8,2) NOT NULL DEFAULT 0.00,
  `status` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `staff`
--

INSERT INTO `staff` (`id`, `user_id`, `specialization_id`, `clinic_id`, `gender`, `date_of_birth`, `home_address`, `phone_number`, `consultation_fee`, `status`, `created_at`, `updated_at`) VALUES
(1, 4, 1, NULL, 'Male', NULL, 'Elwazir Street,bosso\r\nVcm 105 Elwazir Estate', '08188223228', 497.00, 1, '2023-01-05 13:18:51', '2023-03-05 22:31:02'),
(2, 12, 1, 1, 'Others', NULL, NULL, '08188223244', 8888.00, 1, '2023-03-05 21:06:45', '2023-03-14 17:34:40');

-- --------------------------------------------------------

--
-- Table structure for table `stocks`
--

CREATE TABLE `stocks` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `initial_quantity` int(11) NOT NULL DEFAULT 0,
  `order_quantity` int(11) NOT NULL DEFAULT 0,
  `current_quantity` int(11) NOT NULL DEFAULT 0,
  `quantity_sale` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `stocks`
--

INSERT INTO `stocks` (`id`, `product_id`, `initial_quantity`, `order_quantity`, `current_quantity`, `quantity_sale`, `created_at`, `updated_at`) VALUES
(1, 2, 0, 100, 100, 0, '2023-03-08 13:40:35', '2023-03-09 10:46:57'),
(2, 3, 0, 388, 388, 0, '2023-03-08 13:44:38', '2023-03-09 13:53:24'),
(3, 4, 0, 20, 20, 0, '2023-03-09 14:01:46', '2023-03-09 14:02:37');

-- --------------------------------------------------------

--
-- Table structure for table `stock_invoices`
--

CREATE TABLE `stock_invoices` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `invoice_no` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `supplier_id` bigint(20) UNSIGNED NOT NULL,
  `invoice_date` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `number_of_products` int(11) NOT NULL,
  `total_amount` int(11) NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stock_orders`
--

CREATE TABLE `stock_orders` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `invoice_id` bigint(20) UNSIGNED DEFAULT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `order_quantity` int(11) NOT NULL,
  `total_amount` double(8,2) NOT NULL,
  `store_id` bigint(20) UNSIGNED NOT NULL,
  `stock_date` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `stock_orders`
--

INSERT INTO `stock_orders` (`id`, `invoice_id`, `product_id`, `order_quantity`, `total_amount`, `store_id`, `stock_date`, `created_at`, `updated_at`) VALUES
(1, 22, 2, 100, 2000.00, 1, NULL, '2023-03-09 10:46:57', '2023-03-09 10:46:57'),
(2, 22, 3, 388, 5000.00, 1, NULL, '2023-03-09 13:53:24', '2023-03-09 13:53:24'),
(3, 22, 4, 20, 1000.00, 2, NULL, '2023-03-09 14:02:37', '2023-03-09 14:02:37');

-- --------------------------------------------------------

--
-- Table structure for table `stores`
--

CREATE TABLE `stores` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `store_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `location` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `stores`
--

INSERT INTO `stores` (`id`, `store_name`, `location`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Central store', 'Main Building', 1, '2023-03-09 01:19:07', '2023-03-09 01:37:05'),
(2, 'Pharmacy', 'Phamarcy dept.', 1, '2023-03-09 10:57:40', '2023-03-09 10:58:05');

-- --------------------------------------------------------

--
-- Table structure for table `store_stocks`
--

CREATE TABLE `store_stocks` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `store_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `initial_quantity` int(11) NOT NULL DEFAULT 0,
  `quantity_sale` int(11) NOT NULL DEFAULT 0,
  `order_quantity` int(11) NOT NULL DEFAULT 0,
  `current_quantity` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `store_stocks`
--

INSERT INTO `store_stocks` (`id`, `store_id`, `product_id`, `initial_quantity`, `quantity_sale`, `order_quantity`, `current_quantity`, `created_at`, `updated_at`) VALUES
(1, 1, 2, 170, 0, 70, 160, '2023-03-09 10:46:57', '2023-03-09 11:03:06'),
(2, 2, 2, 0, 0, 0, 10, '2023-03-09 11:03:06', '2023-03-09 11:03:06'),
(3, 1, 3, 0, 0, 388, 388, '2023-03-09 13:53:24', '2023-03-09 13:53:24'),
(4, 2, 4, 0, 0, 20, 20, '2023-03-09 14:02:37', '2023-03-09 14:02:37');

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `company_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `last_payment` double(8,2) DEFAULT NULL,
  `last_payment_date` timestamp NULL DEFAULT NULL,
  `last_buy_date` timestamp NULL DEFAULT NULL,
  `last_buy_amount` double(8,2) DEFAULT NULL,
  `credit_b4` double(8,2) DEFAULT NULL,
  `credit` double(8,2) DEFAULT NULL,
  `deposit_b4` double(8,2) DEFAULT NULL,
  `deposit` double(8,2) DEFAULT NULL,
  `total_deposite` double(8,2) DEFAULT NULL,
  `date_line` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `is_admin` int(11) NOT NULL DEFAULT 20,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `filename` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `old_records` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `surname` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `firstname` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `othername` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `assignRole` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `assignPermission` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` int(11) NOT NULL DEFAULT 1,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `is_admin`, `email`, `filename`, `old_records`, `surname`, `firstname`, `othername`, `assignRole`, `assignPermission`, `email_verified_at`, `password`, `status`, `remember_token`, `created_at`, `updated_at`) VALUES
(2, 19, 'walshak.apollos@hms.com', 'avatar.png', NULL, 'Apollos', 'Walshak', ' ', '0', '0', NULL, '$2y$10$Fcs.on7MNR/0AXrCd/E9aOXutLtfpDPT7UNdzSk4WYc1gwf5xYE5G', 1, NULL, '2023-01-05 08:11:45', '2023-01-05 08:11:45'),
(12, 20, 'walshak1999@gmail.com', '1678062009-00000493.jpg', '1678060093-00000386.jpg', 'mwolji', 'hhg', ' ', '1', '1', NULL, '$2y$10$odozjADaTjXFv9.0VTdvy.n49WNMAky0sz2/U1QP8YDvMqQCVa4vW', 1, NULL, '2023-03-05 21:06:45', '2023-03-14 17:34:40'),
(13, 20, 'walshak91999@gmail.com', 'avatar.png', NULL, 'Apollos', 'Walshak', ' ', '0', '0', NULL, '$2y$10$vW8KrO4NXyWWy1Nm6txoBOgaR9mF/UB4O6EZeNLyfNf1HzScGBbaS', 1, NULL, '2023-03-05 23:23:39', '2023-03-05 23:23:39'),
(14, 20, 'walshak19kk99@gmail.com', 'avatar.png', NULL, 'Apollos', 'Walshak', ' ', '0', '0', NULL, '$2y$10$Lw7XW5ZIXqnco3Q7YJK2r.KTUH/bkOsKXDrfZNIDGaZyfr8pZJjJW', 1, NULL, '2023-03-05 23:29:04', '2023-03-05 23:29:04'),
(17, 19, 'lloo.kkk@hms.com', '1678110155-00000386.jpg', '1678110161-00000427.jpg', 'kkk', 'lloo', 'lllff', '0', '0', NULL, '$2y$10$H5fly3kZR3zUzU6EQzoWr.Q9DrgNxUHV86aFubDLm3uMqBGaNjzKC', 1, NULL, '2023-03-06 12:42:41', '2023-03-06 12:42:41'),
(18, 19, 'abdulfatah.kunle@hms.com', '1678119873-00000418.jpg', '1678119883-trace-cursive-letters-a-z-uppercase.pdf', 'Kunle', 'Abdulfatah', ' ', '0', '0', NULL, '$2y$10$j1zlIzakIJGSWFPlrLDoh.Zz8wrwQStxFMsyTD/bYhk4PhrAGrOHe', 1, NULL, '2023-03-06 15:24:43', '2023-03-06 16:46:58');

-- --------------------------------------------------------

--
-- Table structure for table `user_categories`
--

CREATE TABLE `user_categories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_categories`
--

INSERT INTO `user_categories` (`id`, `name`, `status`, `created_at`, `updated_at`) VALUES
(19, 'patient', 1, NULL, NULL),
(20, 'Receptionist', 1, NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `application_status`
--
ALTER TABLE `application_status`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `clinics`
--
ALTER TABLE `clinics`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `details`
--
ALTER TABLE `details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `details_patient_id_foreign` (`patient_id`);

--
-- Indexes for table `doctor_queues`
--
ALTER TABLE `doctor_queues`
  ADD PRIMARY KEY (`id`),
  ADD KEY `doctor_queues_patient_id_foreign` (`patient_id`),
  ADD KEY `doctor_queues_clinic_id_foreign` (`clinic_id`),
  ADD KEY `doctor_queues_staff_id_foreign` (`staff_id`),
  ADD KEY `doctor_queues_receptionist_id_foreign` (`receptionist_id`);

--
-- Indexes for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`);

--
-- Indexes for table `hmos`
--
ALTER TABLE `hmos`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `model_has_permissions`
--
ALTER TABLE `model_has_permissions`
  ADD PRIMARY KEY (`permission_id`,`model_id`,`model_type`),
  ADD KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`);

--
-- Indexes for table `model_has_roles`
--
ALTER TABLE `model_has_roles`
  ADD PRIMARY KEY (`role_id`,`model_id`,`model_type`),
  ADD KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD KEY `password_resets_email_index` (`email`);

--
-- Indexes for table `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `permissions_name_guard_name_unique` (`name`,`guard_name`);

--
-- Indexes for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  ADD KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`);

--
-- Indexes for table `prices`
--
ALTER TABLE `prices`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `product_categories`
--
ALTER TABLE `product_categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `product_or_service_requests`
--
ALTER TABLE `product_or_service_requests`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `promotions`
--
ALTER TABLE `promotions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `roles_name_guard_name_unique` (`name`,`guard_name`);

--
-- Indexes for table `role_has_permissions`
--
ALTER TABLE `role_has_permissions`
  ADD PRIMARY KEY (`permission_id`,`role_id`),
  ADD KEY `role_has_permissions_role_id_foreign` (`role_id`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `service_categories`
--
ALTER TABLE `service_categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `service_prices`
--
ALTER TABLE `service_prices`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `specializations`
--
ALTER TABLE `specializations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `staff`
--
ALTER TABLE `staff`
  ADD PRIMARY KEY (`id`),
  ADD KEY `staff_specialization_id_foreign` (`specialization_id`);

--
-- Indexes for table `stocks`
--
ALTER TABLE `stocks`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `stock_invoices`
--
ALTER TABLE `stock_invoices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `stock_invoices_invoice_no_unique` (`invoice_no`);

--
-- Indexes for table `stock_orders`
--
ALTER TABLE `stock_orders`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `stores`
--
ALTER TABLE `stores`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `store_stocks`
--
ALTER TABLE `store_stocks`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_email_unique` (`email`);

--
-- Indexes for table `user_categories`
--
ALTER TABLE `user_categories`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `application_status`
--
ALTER TABLE `application_status`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `clinics`
--
ALTER TABLE `clinics`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `details`
--
ALTER TABLE `details`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `doctor_queues`
--
ALTER TABLE `doctor_queues`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `hmos`
--
ALTER TABLE `hmos`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `invoices`
--
ALTER TABLE `invoices`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `prices`
--
ALTER TABLE `prices`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `product_categories`
--
ALTER TABLE `product_categories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `product_or_service_requests`
--
ALTER TABLE `product_or_service_requests`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `promotions`
--
ALTER TABLE `promotions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `service_categories`
--
ALTER TABLE `service_categories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `service_prices`
--
ALTER TABLE `service_prices`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `specializations`
--
ALTER TABLE `specializations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `staff`
--
ALTER TABLE `staff`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `stocks`
--
ALTER TABLE `stocks`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `stock_invoices`
--
ALTER TABLE `stock_invoices`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stock_orders`
--
ALTER TABLE `stock_orders`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `stores`
--
ALTER TABLE `stores`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `store_stocks`
--
ALTER TABLE `store_stocks`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `user_categories`
--
ALTER TABLE `user_categories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `details`
--
ALTER TABLE `details`
  ADD CONSTRAINT `details_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`);

--
-- Constraints for table `doctor_queues`
--
ALTER TABLE `doctor_queues`
  ADD CONSTRAINT `doctor_queues_clinic_id_foreign` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`),
  ADD CONSTRAINT `doctor_queues_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  ADD CONSTRAINT `doctor_queues_receptionist_id_foreign` FOREIGN KEY (`receptionist_id`) REFERENCES `staff` (`id`),
  ADD CONSTRAINT `doctor_queues_staff_id_foreign` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`);

--
-- Constraints for table `model_has_permissions`
--
ALTER TABLE `model_has_permissions`
  ADD CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `model_has_roles`
--
ALTER TABLE `model_has_roles`
  ADD CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `role_has_permissions`
--
ALTER TABLE `role_has_permissions`
  ADD CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `staff`
--
ALTER TABLE `staff`
  ADD CONSTRAINT `staff_specialization_id_foreign` FOREIGN KEY (`specialization_id`) REFERENCES `specializations` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
