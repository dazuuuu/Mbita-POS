-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Jul 16, 2026 at 10:06 PM
-- Server version: 10.11.18-MariaDB-cll-lve
-- PHP Version: 8.4.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `dazutech_baddies_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `customer_name` varchar(120) DEFAULT NULL,
  `customer_phone` varchar(30) DEFAULT NULL,
  `customer_email` varchar(255) DEFAULT NULL,
  `assigned_user_id` int(11) NOT NULL,
  `assistant_user_id` int(11) DEFAULT NULL,
  `department` enum('barber','salon') NOT NULL DEFAULT 'barber',
  `scheduled_at` datetime NOT NULL,
  `duration_minutes` int(11) NOT NULL DEFAULT 30,
  `status` enum('scheduled','checked_in','cancelled','no_show') NOT NULL DEFAULT 'scheduled',
  `visit_id` int(11) DEFAULT NULL,
  `booked_by_user_id` int(11) NOT NULL,
  `total_estimate` decimal(12,2) NOT NULL DEFAULT 0.00,
  `receipt_number` varchar(32) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `receipt_sent_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`id`, `tenant_id`, `branch_id`, `customer_id`, `customer_name`, `customer_phone`, `customer_email`, `assigned_user_id`, `assistant_user_id`, `department`, `scheduled_at`, `duration_minutes`, `status`, `visit_id`, `booked_by_user_id`, `total_estimate`, `receipt_number`, `notes`, `receipt_sent_at`, `created_at`) VALUES
(2, 5, 5, 2, 'Victor Karanja', '0768457485', 'njugunavickie7@gmail.com', 31, NULL, 'salon', '2026-07-06 10:00:00', 30, 'scheduled', NULL, 30, 250.00, 'APT-000002', NULL, '2026-07-06 17:00:01', '2026-07-06 17:00:00'),
(3, 6, 6, 3, 'Jordy', '0795003396', 'victormlewa446@gmail.com', 24, NULL, 'salon', '2026-07-22 08:00:00', 30, 'scheduled', NULL, 27, 3500.00, 'APT-000003', NULL, '2026-07-08 21:04:43', '2026-07-08 21:04:42'),
(4, 6, 6, 3, 'Jordy', '0795003396', 'victormlewa446@gmail.com', 29, NULL, 'salon', '2026-07-08 10:00:00', 30, 'checked_in', 5, 27, 5500.00, 'APT-000004', 'Please come with clean blow dried hair', '2026-07-08 21:22:19', '2026-07-08 21:22:19');

-- --------------------------------------------------------

--
-- Table structure for table `appointment_services`
--

CREATE TABLE `appointment_services` (
  `id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `item_name` varchar(120) NOT NULL,
  `audience` enum('adult','kid') NOT NULL DEFAULT 'adult',
  `charged_amount` decimal(12,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointment_services`
--

INSERT INTO `appointment_services` (`id`, `appointment_id`, `service_id`, `item_name`, `audience`, `charged_amount`) VALUES
(3, 2, 20, 'Sample', 'adult', 250.00),
(4, 3, 13, 'Leave out sew in', 'adult', 3500.00),
(5, 4, 14, 'Closure sew in', 'adult', 5500.00);

-- --------------------------------------------------------

--
-- Table structure for table `blogs`
--

CREATE TABLE `blogs` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `excerpt` text DEFAULT NULL,
  `content` longtext DEFAULT NULL,
  `featured_image` varchar(255) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `author_id` int(11) NOT NULL,
  `status` enum('draft','published','archived') DEFAULT 'draft',
  `view_count` int(11) DEFAULT 0,
  `is_featured` tinyint(1) DEFAULT 0,
  `meta_title` varchar(255) DEFAULT NULL,
  `meta_description` text DEFAULT NULL,
  `meta_keywords` varchar(255) DEFAULT NULL,
  `published_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blog_categories`
--

CREATE TABLE `blog_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `color` varchar(20) DEFAULT '#667eea',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blog_faqs`
--

CREATE TABLE `blog_faqs` (
  `id` int(11) NOT NULL,
  `blog_id` int(11) NOT NULL,
  `question` varchar(300) NOT NULL,
  `answer` text NOT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blog_sections`
--

CREATE TABLE `blog_sections` (
  `id` int(11) NOT NULL,
  `blog_id` int(11) NOT NULL,
  `section_type` enum('text_only','text_image_left','text_image_right','image_gallery','video','youtube','code_block','quote') DEFAULT 'text_only',
  `title` varchar(255) DEFAULT NULL,
  `content` text DEFAULT NULL,
  `media_url` varchar(500) DEFAULT NULL,
  `media_type` enum('image','video','youtube') DEFAULT 'image',
  `video_id` varchar(100) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blog_tags`
--

CREATE TABLE `blog_tags` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `slug` varchar(50) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blog_tag_relations`
--

CREATE TABLE `blog_tag_relations` (
  `blog_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `branches`
--

CREATE TABLE `branches` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `title` varchar(120) NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `branches`
--

INSERT INTO `branches` (`id`, `tenant_id`, `title`, `location`, `is_active`, `created_at`, `updated_at`) VALUES
(5, 5, 'Kari Beauty Stuidio', 'Marurui Road', 1, '2026-07-06 12:16:30', '2026-07-06 12:16:30'),
(6, 6, 'Rari beauty', 'Maruirui road', 1, '2026-07-06 13:57:42', '2026-07-06 13:57:42');

-- --------------------------------------------------------

--
-- Table structure for table `cart_items`
--

CREATE TABLE `cart_items` (
  `id` int(11) NOT NULL,
  `cart_session_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `price` decimal(10,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cart_sessions`
--

CREATE TABLE `cart_sessions` (
  `id` int(11) NOT NULL,
  `session_id` varchar(255) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `name` varchar(120) NOT NULL,
  `status` enum('active','draft') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `tenant_id`, `name`, `status`, `created_at`, `updated_at`) VALUES
(8, 6, 'Hair care products', 'active', '2026-07-06 14:38:21', '2026-07-06 14:38:21'),
(9, 5, 'Goodies', 'active', '2026-07-16 20:21:05', '2026-07-16 20:21:05');

-- --------------------------------------------------------

--
-- Table structure for table `commission_payouts`
--

CREATE TABLE `commission_payouts` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `agent_user_id` int(11) NOT NULL,
  `amount_paid` decimal(12,2) NOT NULL DEFAULT 0.00,
  `sales_count` int(11) NOT NULL DEFAULT 0,
  `paid_by` int(11) NOT NULL,
  `paid_at` datetime NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `commission_payouts`
--

INSERT INTO `commission_payouts` (`id`, `tenant_id`, `agent_user_id`, `amount_paid`, `sales_count`, `paid_by`, `paid_at`, `notes`) VALUES
(1, 2, 17, 40.00, 1, 9, '2026-07-04 15:18:12', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `commission_sales`
--

CREATE TABLE `commission_sales` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `receipt_number` varchar(32) DEFAULT NULL,
  `agent_user_id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `customer_name` varchar(120) DEFAULT NULL,
  `customer_phone` varchar(30) DEFAULT NULL,
  `item_type` enum('service','product') NOT NULL,
  `service_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `item_name` varchar(160) NOT NULL,
  `quantity` decimal(12,2) NOT NULL DEFAULT 1.00,
  `standard_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `charged_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `payment_method` enum('cash','mpesa','credit') NOT NULL DEFAULT 'cash',
  `is_credit` tinyint(1) NOT NULL DEFAULT 0,
  `expense_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `base_commission` decimal(12,2) NOT NULL DEFAULT 0.00,
  `overage_commission` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_commission` decimal(12,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `payout_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `visit_id` int(11) DEFAULT NULL,
  `recorded_by_user_id` int(11) DEFAULT NULL,
  `payment_status` enum('pending','paid') NOT NULL DEFAULT 'paid',
  `assistant_user_id` int(11) DEFAULT NULL,
  `audience` enum('adult','kid') NOT NULL DEFAULT 'adult'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `commission_sales`
--

INSERT INTO `commission_sales` (`id`, `tenant_id`, `receipt_number`, `agent_user_id`, `branch_id`, `customer_id`, `customer_name`, `customer_phone`, `item_type`, `service_id`, `product_id`, `item_name`, `quantity`, `standard_price`, `charged_amount`, `payment_method`, `is_credit`, `expense_total`, `base_commission`, `overage_commission`, `total_commission`, `notes`, `payout_id`, `created_at`, `visit_id`, `recorded_by_user_id`, `payment_status`, `assistant_user_id`, `audience`) VALUES
(6, 6, 'CRS-000006', 24, 6, 3, 'Jordy', '0795003396', 'service', 13, NULL, 'Leave out sew in (Adult)', 1.00, 3500.00, 3500.00, 'cash', 0, 1543.00, 1400.00, 0.00, 1400.00, NULL, NULL, '2026-07-06 14:13:46', 3, 27, 'paid', NULL, 'adult'),
(7, 5, 'CRS-000007', 31, 5, 4, 'Victor Karanja', '0792248332', 'service', 20, NULL, 'Sample (Adult)', 1.00, 250.00, 250.00, 'cash', 0, 0.00, 50.00, 0.00, 50.00, NULL, NULL, '2026-07-06 16:55:16', 4, 30, 'pending', NULL, 'adult'),
(8, 6, 'CRS-000008', 29, 6, 3, 'Jordy', '0795003396', 'service', 14, NULL, 'Closure sew in (Adult)', 1.00, 5500.00, 5500.00, 'cash', 0, 0.00, 1400.00, 0.00, 1400.00, NULL, NULL, '2026-07-08 21:22:54', 5, 27, 'paid', NULL, 'adult');

-- --------------------------------------------------------

--
-- Table structure for table `commission_sale_expenses`
--

CREATE TABLE `commission_sale_expenses` (
  `id` int(11) NOT NULL,
  `commission_sale_id` int(11) NOT NULL,
  `expense_name` varchar(160) NOT NULL,
  `cost` decimal(12,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `commission_sale_expenses`
--

INSERT INTO `commission_sale_expenses` (`id`, `commission_sale_id`, `expense_name`, `cost`) VALUES
(1, 2, 'General', 100.00),
(2, 6, 'Commission', 1543.00);

-- --------------------------------------------------------

--
-- Table structure for table `commission_visits`
--

CREATE TABLE `commission_visits` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `reception_user_id` int(11) NOT NULL,
  `assigned_user_id` int(11) NOT NULL,
  `assistant_user_id` int(11) DEFAULT NULL,
  `customer_name` varchar(120) DEFAULT NULL,
  `customer_phone` varchar(30) DEFAULT NULL,
  `payment_status` enum('pending','paid') NOT NULL DEFAULT 'pending',
  `payment_method` enum('cash','mpesa','credit') DEFAULT NULL,
  `total_charged` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_expenses` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_commission` decimal(12,2) NOT NULL DEFAULT 0.00,
  `receipt_number` varchar(32) DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `paid_by_user_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `commission_visits`
--

INSERT INTO `commission_visits` (`id`, `tenant_id`, `branch_id`, `reception_user_id`, `assigned_user_id`, `assistant_user_id`, `customer_name`, `customer_phone`, `payment_status`, `payment_method`, `total_charged`, `total_expenses`, `total_commission`, `receipt_number`, `paid_at`, `paid_by_user_id`, `notes`, `created_at`) VALUES
(3, 6, 6, 27, 24, NULL, 'Jordy', '0795003396', 'paid', 'cash', 3500.00, 1543.00, 1400.00, 'VST-000003', '2026-07-06 19:21:14', 27, 'Location\r\nUkifika mountain mall thika road, next to it there is a road. The 2nd building on your left utaona Magunas supermarket. Directly opposite utaona shops first floor at Rari beauty. Incase upotee just call me, kindly avoid bringing extra people to your appointment\r\n\r\nPut Magunas Roasters on Uber\r\n\r\nNote: there is a ksh.500 late fee policy so please be on time.\r\n\r\nThank you. See you💕', '2026-07-06 14:13:46'),
(4, 5, 5, 30, 31, NULL, 'Victor Karanja', '0792248332', 'pending', NULL, 250.00, 0.00, 50.00, 'VST-000004', NULL, NULL, NULL, '2026-07-06 16:55:16'),
(5, 6, 6, 27, 29, NULL, 'Jordy', '0795003396', 'paid', 'cash', 5500.00, 0.00, 1400.00, 'VST-000005', '2026-07-08 21:24:19', 27, 'Appointment APT-000004', '2026-07-08 21:22:54');

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `name` varchar(120) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `credit_balance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `tenant_id`, `name`, `phone`, `email`, `credit_balance`, `notes`, `created_at`, `updated_at`) VALUES
(1, 2, 'Dazu', '08909090', NULL, 0.00, NULL, '2026-07-04 15:16:17', '2026-07-04 15:16:17'),
(2, 5, 'Victor Karanja', '0768457485', 'victorkaranjaofficial@gmail.com', 0.00, NULL, '2026-07-06 09:16:48', '2026-07-06 09:16:48'),
(3, 6, 'Jordy', '0795003396', 'victormlewa446@gmail.com', 0.00, NULL, '2026-07-06 14:13:46', '2026-07-08 21:04:42'),
(4, 5, 'Victor Karanja', '0792248332', NULL, 0.00, NULL, '2026-07-06 16:55:16', '2026-07-06 16:55:16');

-- --------------------------------------------------------

--
-- Table structure for table `enquiries`
--

CREATE TABLE `enquiries` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `service` varchar(100) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `status` enum('new','read','contacted','closed') DEFAULT 'new',
  `priority` enum('low','medium','high') DEFAULT 'medium',
  `notes` text DEFAULT NULL,
  `contacted_at` timestamp NULL DEFAULT NULL,
  `closed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `enquiry_replies`
--

CREATE TABLE `enquiry_replies` (
  `id` int(11) NOT NULL,
  `enquiry_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `reply` text NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `gallery`
--

CREATE TABLE `gallery` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `media_type` enum('image','video') DEFAULT 'image',
  `file_path` varchar(500) NOT NULL,
  `thumbnail_path` varchar(500) DEFAULT NULL,
  `video_url` varchar(500) DEFAULT NULL,
  `video_embed_code` text DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `tags` varchar(255) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_featured` tinyint(1) DEFAULT 0,
  `status` enum('active','inactive') DEFAULT 'active',
  `view_count` int(11) DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `gallery_categories`
--

CREATE TABLE `gallery_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `hero_slides`
--

CREATE TABLE `hero_slides` (
  `id` int(10) UNSIGNED NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `caption` varchar(255) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `invoices`
--

CREATE TABLE `invoices` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `staff_id` int(11) DEFAULT NULL,
  `invoice_number` varchar(20) DEFAULT NULL,
  `customer_name` varchar(150) DEFAULT NULL,
  `customer_phone` varchar(30) DEFAULT NULL,
  `customer_email` varchar(150) DEFAULT NULL,
  `total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status` enum('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `payment_status` enum('unpaid','paid') NOT NULL DEFAULT 'unpaid',
  `payment_method` varchar(10) DEFAULT NULL,
  `mpesa_channel` varchar(10) DEFAULT NULL,
  `sale_id` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `invoice_items`
--

CREATE TABLE `invoice_items` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `product_name` varchar(200) NOT NULL,
  `unit` varchar(30) DEFAULT NULL,
  `unit_price` decimal(12,2) NOT NULL,
  `quantity` decimal(12,2) NOT NULL,
  `line_total` decimal(12,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `attempt_time` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `login_attempts`
--

INSERT INTO `login_attempts` (`id`, `email`, `ip_address`, `attempt_time`) VALUES
(1, 'vickiekaran254@gmail.com', '::1', '2026-06-20 18:13:54'),
(2, 'vickiekaran254@gmail.com', '::1', '2026-06-20 18:19:27'),
(3, 'dazuai01@gmail.com', '::1', '2026-06-21 00:54:05'),
(4, 'vickiekaran254@gmail.com', '::1', '2026-06-21 20:43:33'),
(5, 'vickiekaran254@gmail.com', '154.159.252.1', '2026-06-23 11:08:32'),
(6, 'vickiekaran254@gmail.com', '154.159.252.1', '2026-06-23 11:08:38'),
(7, 'vickiekaran254@gmail.com', '154.159.252.1', '2026-06-23 11:08:54'),
(8, 'vickiekaran254@gmail.com', '154.159.252.1', '2026-06-23 11:09:00'),
(9, 'vickiekaran254@gmail.com', '154.159.252.1', '2026-06-23 11:13:15'),
(10, 'njugunavickie7@gmail.com', '154.159.252.1', '2026-06-23 11:13:34'),
(11, 'vickiekaran254@gmail.com', '154.159.252.1', '2026-06-23 11:20:27'),
(12, 'vickiekaran254@gmail.com', '154.159.252.1', '2026-06-23 11:20:33'),
(13, 'lucsela@gmail.com', '154.159.252.1', '2026-06-23 12:15:11'),
(14, 'lucsela@gmail.com', '102.213.179.43', '2026-06-23 13:08:48'),
(15, 'lucsela@gmail.com', '102.213.179.43', '2026-06-23 13:08:52'),
(16, 'Lagrics123@gmail.com', '102.213.179.43', '2026-06-23 13:09:39'),
(17, 'lucsela@gmail.com', '102.213.179.43', '2026-06-23 13:17:05'),
(18, 'lucsela@gmail.com', '102.213.179.43', '2026-06-23 13:17:20'),
(19, 'lucsela@gmail.com', '102.213.179.43', '2026-06-23 13:17:45'),
(20, 'lucsela@gmail.com', '102.213.179.43', '2026-06-23 13:35:24'),
(21, 'Lagrics123@gmail.com', '102.213.179.43', '2026-06-26 12:01:55'),
(22, 'Lagrics123@gmail.com', '102.213.179.43', '2026-06-26 12:02:12'),
(23, 'lucsela@gmail.com', '102.213.179.43', '2026-06-26 12:12:38'),
(24, 'dazuai01@gmail.com', '197.254.8.98', '2026-06-30 07:15:07'),
(25, 'dazuai01@gmail.com', '197.254.8.98', '2026-06-30 07:15:17'),
(26, 'dazuai01@gmail.com', '197.254.8.98', '2026-06-30 07:15:28'),
(27, 'dazuai01@gmail.com', '197.254.8.98', '2026-06-30 07:15:41'),
(28, 'dazuai01@gmail.com', '197.254.8.98', '2026-06-30 07:41:05'),
(29, 'dazuai01@gmail.com', '197.254.8.98', '2026-06-30 08:55:51'),
(30, 'dazuai01@gmail.com', '197.254.8.98', '2026-06-30 08:55:59'),
(31, 'dazuai01@gmail.com', '197.254.8.98', '2026-06-30 08:56:21'),
(32, 'dazuai01@gmail.com', '197.254.8.98', '2026-06-30 08:56:40'),
(33, 'Lagrics123@gmail.com', '102.213.179.43', '2026-06-30 15:30:31'),
(34, 'Lagrics123@gmail.com', '102.213.179.43', '2026-06-30 15:30:42'),
(35, 'vickiekaran254@gmail.com', '::1', '2026-07-01 17:23:37'),
(36, 'admin', '::1', '2026-07-04 10:46:10'),
(37, 'karanjav494@gmail.com', '::1', '2026-07-04 10:46:19'),
(38, 'dazuai01@gmail.com', '::1', '2026-07-06 08:59:34'),
(39, 'Victormlewa446@gmail.com', '197.237.161.175', '2026-07-06 11:15:32'),
(40, 'Victormlewa446@gmail.com', '197.237.161.175', '2026-07-06 11:15:46'),
(41, 'Victormlewa446@gmail.com', '197.237.161.175', '2026-07-06 11:17:00'),
(42, 'Victormlewa446@gmail.com', '197.237.161.175', '2026-07-06 11:17:12'),
(43, 'karanjav494@gmail.com', '197.237.161.175', '2026-07-06 12:44:51'),
(44, 'Patkamau', '197.237.161.175', '2026-07-06 13:05:27'),
(45, 'NAYjaeMwta', '197.237.161.175', '2026-07-06 14:11:25'),
(46, 'glambyessie@gmail.com', '102.206.115.176', '2026-07-06 14:48:53'),
(47, 'glambyessie@gmail.com', '102.206.115.176', '2026-07-06 14:49:05'),
(48, 'glambyessie@gmail.com', '102.206.115.176', '2026-07-06 14:49:18'),
(49, '123456789glambyessie@gmail.com', '102.206.115.176', '2026-07-06 14:49:26'),
(50, 'glambyessie@gmail.com', '102.206.115.176', '2026-07-06 14:49:41'),
(51, 'Victormlewa446@gmail.com', '197.237.161.175', '2026-07-06 16:02:44'),
(52, 'Victormlewa446@gmail.com', '197.237.161.175', '2026-07-06 16:03:15'),
(53, 'Victormlewa97@gmail.com', '197.237.161.175', '2026-07-06 16:06:48'),
(54, 'Victormlewa97@gmail.com', '197.237.161.175', '2026-07-06 16:06:57'),
(55, 'Victormlewa97@gmail.com', '197.237.161.175', '2026-07-06 16:10:46'),
(56, 'Victormlewa97@gmail.com', '197.237.161.175', '2026-07-06 16:11:07'),
(57, 'imanirespect@gmail.com', '197.237.161.175', '2026-07-06 18:52:43'),
(58, 'Victormlewa97@gmail.com', '102.0.20.164', '2026-07-08 05:24:49'),
(59, '', '102.0.20.164', '2026-07-08 05:25:08'),
(60, 'Victormlewa97@gmail.com', '102.0.20.164', '2026-07-08 05:25:24'),
(61, 'Victormlewa97@gmail.com', '102.0.20.164', '2026-07-08 05:25:27'),
(62, 'Victormlewa446@gmail.com', '102.0.20.164', '2026-07-08 17:50:46'),
(63, 'Victormlewa446@gmail.com', '102.0.20.164', '2026-07-09 18:35:23'),
(64, 'Victormlewa97@gmail.com', '102.0.20.164', '2026-07-14 10:53:00');

-- --------------------------------------------------------

--
-- Table structure for table `login_otps`
--

CREATE TABLE `login_otps` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `tenant_id` int(11) DEFAULT NULL,
  `code_hash` varchar(255) NOT NULL,
  `purpose` varchar(32) NOT NULL DEFAULT 'login_2fa',
  `attempts` tinyint(4) NOT NULL DEFAULT 0,
  `max_attempts` tinyint(4) NOT NULL DEFAULT 5,
  `expires_at` datetime NOT NULL,
  `consumed_at` datetime DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `login_otps`
--

INSERT INTO `login_otps` (`id`, `user_id`, `tenant_id`, `code_hash`, `purpose`, `attempts`, `max_attempts`, `expires_at`, `consumed_at`, `ip`, `created_at`) VALUES
(1, 2, 2, '$2y$10$/izm8xNQ.sRUm7bSXuoyX.pX.IZnJxf29Pi/yL3/JJpmH7TW68.oO', 'login_2fa', 1, 5, '2026-06-20 14:49:20', '2026-06-20 17:40:00', '::1', '2026-06-20 17:39:20'),
(2, 2, 2, '$2y$10$t78.vPDUmGEZzL9Zl9VqJ.agAzlzE27L9XFzRPjQSyu2TSO1sissO', 'login_2fa', 1, 5, '2026-06-20 18:14:03', '2026-06-20 21:04:24', '::1', '2026-06-20 21:04:03'),
(3, 2, 2, '$2y$10$xH2jABi79Wwpj7hzgRFFxeMhjkg85tFdzr1aSrnvcBvTOS0f/LJ0e', 'login_2fa', 1, 5, '2026-06-21 00:20:06', '2026-06-21 03:10:45', '::1', '2026-06-21 03:10:07'),
(4, 5, 2, '$2y$10$OJPe17ichLQsxGOx5cuKMuFqQE8fFyKSzP3B0NCis7MLwrkdrY8Yu', 'login_2fa', 1, 5, '2026-06-21 00:51:16', '2026-06-21 03:42:00', '::1', '2026-06-21 03:41:16'),
(5, 5, 2, '$2y$10$7.7FAk9qu.qbZ2x.ijb2e.SI4jlZtVPBnOTvs36/7yKvOYK77LYRe', 'login_2fa', 1, 5, '2026-06-21 05:17:25', '2026-06-21 08:07:58', '::1', '2026-06-21 08:07:25'),
(6, 2, 2, '$2y$10$gWmQh2jeOpYaQWJ2g6uf9ecrsXUeIHIVCQPcn.ixkX18E2ON1qIMm', 'login_2fa', 1, 5, '2026-06-21 05:34:08', '2026-06-21 08:25:04', '::1', '2026-06-21 08:24:08'),
(7, 2, 2, '$2y$10$XCq/q4f6vT9RV83.IhikMuNkRB7UtocMMBAC4rkvfdgxVGA8P7b8K', 'login_2fa', 1, 5, '2026-06-21 18:02:43', '2026-06-21 20:53:22', '::1', '2026-06-21 20:52:43'),
(8, 5, 2, '$2y$10$BT2jj..1iaOPoj6D.VjbveWsAnGDZGxTRY.agvwZ6aEF60wNT66lW', 'login_2fa', 1, 5, '2026-06-21 18:53:30', '2026-06-21 21:44:11', '::1', '2026-06-21 21:43:30'),
(9, 2, 2, '$2y$10$V7wTX4XX2gMu.LUwlWjHrO0oOu66da83ru8DDdX4hSmLiI76ngp5e', 'login_2fa', 1, 5, '2026-06-21 22:57:11', '2026-06-22 00:02:08', '127.0.0.1', '2026-06-21 23:47:11'),
(10, 2, 2, '$2y$10$ROGCDUUOHFHr5/.iBWErfO7X2E2lrIuAiPgWUmPl2HaRDbyirSAJG', 'password_reset', 1, 5, '2026-06-23 13:39:53', '2026-06-23 16:30:55', '102.213.179.43', '2026-06-23 16:29:53'),
(11, 7, 2, '$2y$10$r9z7IVAbKkFoeWv/dcb4Xef17lZ36A3/SztFRnbN4dItaSzN6EM1G', 'password_reset', 1, 5, '2026-06-23 13:43:25', '2026-06-23 16:34:56', '102.213.179.43', '2026-06-23 16:33:25'),
(12, 9, 2, '$2y$10$3XsxE6CFE8/MBzzZE/tJRuYzKAr4PurtNBegf0oZuVmTH9eMv/Mh6', 'password_reset', 1, 5, '2026-06-24 10:29:21', '2026-06-24 13:20:21', '197.254.8.98', '2026-06-24 13:19:21'),
(13, 10, 2, '$2y$10$SYxDN4htLC1Mf1KG0O8x4OMzcXa7EbcpVqhBX5DeluWGTCHKOQDc6', 'password_reset', 1, 5, '2026-06-30 09:06:55', '2026-06-30 11:57:40', '197.254.8.98', '2026-06-30 11:56:56'),
(14, 8, 2, '$2y$10$VmJZHPFcrxbBMYcf6cbWv.piWCl8rVi0cWxXpxboCzTh6Snp1lSu.', 'password_reset', 1, 5, '2026-06-30 15:40:56', '2026-06-30 18:32:13', '102.213.179.43', '2026-06-30 18:30:56'),
(15, 27, 6, '$2y$10$1JhJPwPPD.TWQ629qlHt.OxwrmYQo/0kWL/0aIn6QHeOJ2bsybSeC', 'password_reset', 1, 5, '2026-07-06 11:12:04', '2026-07-06 14:02:48', '197.237.161.175', '2026-07-06 14:02:05'),
(16, 23, 6, '$2y$10$y8yZJ8qjmrw6SM5vI8WlW.i09bQ65pC.fS0hoSZHSbWS7/CAKvdrO', 'password_reset', 1, 5, '2026-07-06 11:26:00', '2026-07-06 14:16:31', '197.237.161.175', '2026-07-06 14:16:00'),
(17, 24, 6, '$2y$10$nFLv5/0QQOlgpszwAE54IeHKN8URSU.xnfQWNKNz.E6q1JEjEZQYK', 'password_reset', 1, 5, '2026-07-06 13:14:35', '2026-07-06 16:05:10', '197.237.161.175', '2026-07-06 16:04:35'),
(18, 26, 6, '$2y$10$mHFNIMBmEaG7ZRB4X/UpDOQcdx.ZDy0MX6Li5n5i6XO06rPD/ayzG', 'password_reset', 1, 5, '2026-07-06 13:50:36', '2026-07-06 16:42:36', '197.237.161.175', '2026-07-06 16:40:36'),
(19, 27, 6, '$2y$10$vbWSKUlfurUPlwhXFXz0yuGt5oL.pGpu5B0q7TuR3Az/p66/QURPa', 'password_reset', 1, 5, '2026-07-06 16:21:46', '2026-07-06 19:12:52', '197.237.161.175', '2026-07-06 19:11:46'),
(20, 27, 6, '$2y$10$gdqaWPbVj52gXlS79LrHy.xFgOpczwL2WTIm47wKiv6Br6/QfPj2W', 'password_reset', 0, 5, '2026-07-08 05:35:43', '2026-07-08 11:49:35', '102.0.20.164', '2026-07-08 08:25:43'),
(21, 27, 6, '$2y$10$enFs4e7aKD4oKy3zNky5FOQcX4E3ddjzkYHs/8iaORiSCGDPBjCa6', 'password_reset', 2, 5, '2026-07-08 08:59:35', '2026-07-14 13:53:53', '197.237.161.175', '2026-07-08 11:49:35'),
(22, 27, 6, '$2y$10$BlGq7N4JmAeZrcjtRsgJQOsBs4VvAsn7fXXNK.WUQEvyhverzupVy', 'password_reset', 0, 5, '2026-07-14 11:03:53', NULL, '102.0.20.164', '2026-07-14 13:53:53');

-- --------------------------------------------------------

--
-- Table structure for table `page_headers`
--

CREATE TABLE `page_headers` (
  `id` int(10) UNSIGNED NOT NULL,
  `page_key` varchar(60) NOT NULL,
  `title` varchar(150) DEFAULT NULL,
  `subtitle` varchar(255) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `subcategory_id` int(11) DEFAULT NULL,
  `name` varchar(160) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantity` decimal(12,2) NOT NULL DEFAULT 0.00,
  `unit` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'piece',
  `buying_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `selling_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `commission_type` enum('percent','fixed') NOT NULL DEFAULT 'percent',
  `commission_value` decimal(12,2) NOT NULL DEFAULT 0.00,
  `credit_allowed` tinyint(1) NOT NULL DEFAULT 0,
  `wholesale_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `retail_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `colors` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `sizes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `image_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `low_stock_threshold` int(11) NOT NULL DEFAULT 10,
  `low_stock_notified_at` datetime DEFAULT NULL,
  `status` enum('active','draft') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_categories`
--

CREATE TABLE `product_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `image_path` varchar(500) DEFAULT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

CREATE TABLE `projects` (
  `id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `small_title` varchar(100) NOT NULL,
  `major_title` varchar(200) NOT NULL,
  `project_slug` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `cover_image` varchar(255) DEFAULT NULL,
  `status` enum('draft','published','archived') DEFAULT 'draft',
  `view_count` int(11) DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `project_categories`
--

CREATE TABLE `project_categories` (
  `id` int(11) NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `category_slug` varchar(100) NOT NULL,
  `category_description` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `project_gallery`
--

CREATE TABLE `project_gallery` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `image_title` varchar(100) DEFAULT NULL,
  `image_description` text DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `project_tags`
--

CREATE TABLE `project_tags` (
  `id` int(11) NOT NULL,
  `tag_name` varchar(50) NOT NULL,
  `tag_slug` varchar(50) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `project_tag_relations`
--

CREATE TABLE `project_tag_relations` (
  `project_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `project_videos`
--

CREATE TABLE `project_videos` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `video_title` varchar(200) DEFAULT NULL,
  `video_url` varchar(500) NOT NULL,
  `video_embed_code` text DEFAULT NULL,
  `video_type` enum('youtube','vimeo','local','other') DEFAULT 'youtube',
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL,
  `scope` enum('platform','tenant') NOT NULL DEFAULT 'tenant',
  `capabilities` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `role_name`, `scope`, `capabilities`, `created_at`) VALUES
(1, 'superadmin', 'platform', '[\"*\"]', '2026-06-17 16:29:32'),
(2, 'admin', 'tenant', '[\"inventory.view\", \"inventory.edit\", \"stock.enter\", \"sales.record\", \"sales.view\", \"customers.manage\", \"catalogue.send\", \"reports.view\", \"staff.manage\", \"settings.manage\", \"billing.manage\"]', '2026-06-17 16:29:32'),
(3, 'user', 'tenant', '[\"inventory.view\", \"sales.record\", \"sales.view\"]', '2026-06-17 16:29:32'),
(4, 'platform_admin', 'platform', '[\"*\"]', '2026-06-20 05:10:12'),
(5, 'tenant_owner', 'tenant', '[\"inventory.view\", \"inventory.edit\", \"stock.enter\", \"sales.record\", \"sales.view\", \"customers.manage\", \"catalogue.send\", \"reports.view\", \"branches.manage\", \"staff.manage\", \"settings.manage\", \"billing.manage\", \"services.manage\", \"sales_agent.manage\", \"commission.pay\", \"reception.manage\"]', '2026-06-20 05:10:14'),
(6, 'staff', 'tenant', '[\"inventory.view\", \"sales.record\", \"sales.view\"]', '2026-06-20 05:10:16'),
(7, 'sales_agent', 'tenant', '[\"commission.view\"]', '2026-07-03 21:30:30'),
(9, 'reception', 'tenant', '[\"reception.visit\", \"reception.collect\", \"commission.view\"]', '2026-07-04 10:33:45');

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `staff_id` int(11) NOT NULL,
  `sale_type` enum('retail','wholesale') NOT NULL DEFAULT 'retail',
  `receipt_number` varchar(32) NOT NULL,
  `payment_method` enum('cash','mpesa','split') NOT NULL DEFAULT 'cash',
  `mpesa_channel` varchar(10) DEFAULT NULL,
  `total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `subtotal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `amount_given` decimal(12,2) DEFAULT NULL,
  `change_given` decimal(12,2) DEFAULT NULL,
  `cash_amount` decimal(12,2) DEFAULT NULL,
  `mpesa_amount` decimal(12,2) DEFAULT NULL,
  `customer_name` varchar(120) DEFAULT NULL,
  `customer_phone` varchar(30) DEFAULT NULL,
  `customer_email` varchar(255) DEFAULT NULL,
  `status` enum('completed','voided') NOT NULL DEFAULT 'completed',
  `payment_status` enum('pending','paid','failed') NOT NULL DEFAULT 'paid',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sale_items`
--

CREATE TABLE `sale_items` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `product_name` varchar(160) NOT NULL,
  `unit` varchar(20) NOT NULL DEFAULT 'piece',
  `unit_price` decimal(12,2) NOT NULL,
  `price_type` enum('retail','wholesale') NOT NULL DEFAULT 'retail',
  `unit_cost` decimal(12,2) NOT NULL DEFAULT 0.00,
  `quantity` decimal(12,2) NOT NULL,
  `line_total` decimal(12,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `saved_for_later`
--

CREATE TABLE `saved_for_later` (
  `id` int(11) NOT NULL,
  `cart_session_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `schema_migrations`
--

CREATE TABLE `schema_migrations` (
  `id` int(11) NOT NULL,
  `filename` varchar(120) NOT NULL,
  `applied_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `schema_migrations`
--

INSERT INTO `schema_migrations` (`id`, `filename`, `applied_at`) VALUES
(1, '001_create_users_table.sql', '2026-07-04 00:36:30'),
(2, '002_create_projects_tables.sql', '2026-07-04 00:36:30'),
(3, '003_create_services_tables.sql', '2026-07-04 00:36:30'),
(4, '004_create_blogs_tables.sql', '2026-07-04 00:36:30'),
(5, '005_create_settings_tables.sql', '2026-07-04 00:36:30'),
(6, '006_create_gallery_tables.sql', '2026-07-04 00:36:30'),
(7, '007_create_products_tables.sql', '2026-07-04 00:36:30'),
(8, '009_create_store_tables.sql', '2026-07-04 00:36:31'),
(9, '010_fix_store_orders_schema.sql', '2026-07-04 00:36:31'),
(10, '011_create_enquiries_table.sql', '2026-07-04 00:36:31'),
(11, '012_create_testimonials_table.sql', '2026-07-04 00:36:31'),
(12, '013_create_tenancy_tables.sql', '2026-07-04 00:36:31'),
(13, '016_create_login_otps.sql', '2026-07-04 00:36:31'),
(14, '017_pricing_and_test_plan.sql', '2026-07-04 00:36:31'),
(15, '018_create_subscription_stk.sql', '2026-07-04 00:36:31'),
(16, '020_create_inventory.sql', '2026-07-04 00:36:31'),
(17, '021_product_category_optional.sql', '2026-07-04 00:36:31'),
(18, '022_create_sales.sql', '2026-07-04 00:36:31'),
(19, '008_create_cart_tables.sql', '2026-07-04 01:06:13'),
(20, '026_service_kids_pricing.sql', '2026-07-04 14:10:01');

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `slug` varchar(200) NOT NULL,
  `short_description` text DEFAULT NULL,
  `cover_image` varchar(255) DEFAULT NULL,
  `status` enum('draft','published','archived') DEFAULT 'draft',
  `view_count` int(11) DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `service_benefits`
--

CREATE TABLE `service_benefits` (
  `id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `benefit_title` varchar(200) NOT NULL,
  `benefit_description` text DEFAULT NULL,
  `icon_class` varchar(100) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `service_expenses`
--

CREATE TABLE `service_expenses` (
  `id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `name` varchar(160) NOT NULL,
  `cost` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `service_expenses`
--

INSERT INTO `service_expenses` (`id`, `service_id`, `name`, `cost`, `created_at`) VALUES
(1, 6, 'General', 100.00, '2026-07-04 15:05:34'),
(2, 7, 'General', 50.00, '2026-07-04 15:06:31'),
(3, 9, 'General', 100.00, '2026-07-06 09:00:07'),
(4, 10, 'General', 50.00, '2026-07-06 09:03:18'),
(11, 14, 'Closure', 1100.00, '2026-07-06 14:48:28'),
(12, 14, 'Bundles', 757.00, '2026-07-06 14:48:28'),
(13, 15, 'Bundles', 757.00, '2026-07-06 14:48:49'),
(14, 16, 'Frontal', 1500.00, '2026-07-06 14:49:40'),
(15, 16, 'Bundles', 757.00, '2026-07-06 14:49:40'),
(16, 13, 'Bundles', 757.00, '2026-07-06 14:53:11'),
(17, 21, 'Bottle', 50.00, '2026-07-16 20:22:29');

-- --------------------------------------------------------

--
-- Table structure for table `service_faqs`
--

CREATE TABLE `service_faqs` (
  `id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `question` varchar(300) NOT NULL,
  `answer` text NOT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `service_gallery`
--

CREATE TABLE `service_gallery` (
  `id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `image_title` varchar(100) DEFAULT NULL,
  `image_description` text DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `service_sections`
--

CREATE TABLE `service_sections` (
  `id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `section_type` enum('text_only','text_image_left','text_image_right','image_gallery','video') DEFAULT 'text_only',
  `title` varchar(200) DEFAULT NULL,
  `content` text DEFAULT NULL,
  `media_url` varchar(500) DEFAULT NULL,
  `media_type` enum('image','video','youtube','vimeo') DEFAULT 'image',
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `site_settings`
--

CREATE TABLE `site_settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `site_settings`
--

INSERT INTO `site_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES
('logo_alt', 'Ismano', '2026-06-17 16:32:07'),
('logo_path', NULL, '2026-06-17 16:32:07'),
('site_name', 'Ismano', '2026-06-17 16:32:07');

-- --------------------------------------------------------

--
-- Table structure for table `store_cart`
--

CREATE TABLE `store_cart` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `price` decimal(10,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `saved_for_later` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `store_categories`
--

CREATE TABLE `store_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `image_path` varchar(500) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `store_categories`
--

INSERT INTO `store_categories` (`id`, `name`, `slug`, `description`, `image_path`, `sort_order`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Electronics', 'electronics', 'Electronic devices and gadgets', NULL, 1, 1, '2026-06-17 16:33:22', '2026-06-17 16:33:22'),
(2, 'Clothing', 'clothing', 'Fashion and apparel', NULL, 2, 1, '2026-06-17 16:33:22', '2026-06-17 16:33:22'),
(3, 'Books', 'books', 'Books and publications', NULL, 3, 1, '2026-06-17 16:33:22', '2026-06-17 16:33:22'),
(4, 'Home & Living', 'home-living', 'Home decor and living essentials', NULL, 4, 1, '2026-06-17 16:33:22', '2026-06-17 16:33:22'),
(5, 'Sports', 'sports', 'Sports equipment and gear', NULL, 5, 1, '2026-06-17 16:33:22', '2026-06-17 16:33:22');

-- --------------------------------------------------------

--
-- Table structure for table `store_orders`
--

CREATE TABLE `store_orders` (
  `id` int(11) NOT NULL,
  `order_number` varchar(32) NOT NULL,
  `user_id` int(11) NOT NULL,
  `customer_name` varchar(150) NOT NULL,
  `contact_phone` varchar(20) NOT NULL,
  `fulfillment_method` enum('walkin','delivery') NOT NULL DEFAULT 'walkin',
  `pickup_location` varchar(255) DEFAULT NULL,
  `delivery_notes` varchar(500) DEFAULT NULL,
  `currency` varchar(8) NOT NULL DEFAULT 'KES',
  `subtotal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `payment_status` enum('pending','paid','failed','cancelled') NOT NULL DEFAULT 'pending',
  `payment_method` varchar(20) NOT NULL DEFAULT 'mpesa',
  `mpesa_merchant_request_id` varchar(64) DEFAULT NULL,
  `mpesa_checkout_request_id` varchar(64) DEFAULT NULL,
  `mpesa_receipt` varchar(32) DEFAULT NULL,
  `mpesa_phone` varchar(20) DEFAULT NULL,
  `mpesa_payer_name` varchar(150) DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `store_orders_backup`
--

CREATE TABLE `store_orders_backup` (
  `id` int(11) NOT NULL,
  `order_number` varchar(50) NOT NULL,
  `user_id` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `status` enum('pending','processing','completed','cancelled') DEFAULT 'pending',
  `payment_status` enum('pending','paid','failed') DEFAULT 'pending',
  `shipping_address` text DEFAULT NULL,
  `billing_address` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `store_order_items`
--

CREATE TABLE `store_order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `parcel_id` varchar(32) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `product_name` varchar(255) NOT NULL,
  `unit_price` decimal(12,2) NOT NULL,
  `quantity` int(11) NOT NULL,
  `line_total` decimal(12,2) NOT NULL,
  `fulfillment_status` enum('processing','ready_for_pickup','out_for_delivery','picked_up','delivered','arrived','cancelled') NOT NULL DEFAULT 'processing',
  `fulfilled_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `store_order_items_backup`
--

CREATE TABLE `store_order_items_backup` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `store_products`
--

CREATE TABLE `store_products` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `compare_price` decimal(10,2) DEFAULT NULL,
  `sku` varchar(100) DEFAULT NULL,
  `stock_quantity` int(11) DEFAULT 0,
  `category_id` int(11) DEFAULT NULL,
  `featured_image` varchar(500) DEFAULT NULL,
  `gallery_images` text DEFAULT NULL,
  `status` enum('active','inactive','draft') DEFAULT 'draft',
  `is_featured` tinyint(1) DEFAULT 0,
  `meta_title` varchar(255) DEFAULT NULL,
  `meta_description` text DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `view_count` int(11) DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `store_saved_for_later`
--

CREATE TABLE `store_saved_for_later` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `subcategories`
--

CREATE TABLE `subcategories` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `name` varchar(120) NOT NULL,
  `status` enum('active','draft') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `subcategories`
--

INSERT INTO `subcategories` (`id`, `tenant_id`, `category_id`, `name`, `status`, `created_at`, `updated_at`) VALUES
(9, 5, 9, 'gums', 'active', '2026-07-16 20:21:13', '2026-07-16 20:21:13');

-- --------------------------------------------------------

--
-- Table structure for table `subscriptions`
--

CREATE TABLE `subscriptions` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `plan_id` int(11) NOT NULL,
  `billing_interval` enum('weekly','biweekly','monthly') NOT NULL DEFAULT 'monthly',
  `amount` decimal(10,2) NOT NULL,
  `status` enum('trialing','active','past_due','suspended','cancelled') NOT NULL DEFAULT 'trialing',
  `current_period_start` datetime DEFAULT NULL,
  `current_period_end` datetime DEFAULT NULL,
  `grace_until` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `subscription_plans`
--

CREATE TABLE `subscription_plans` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `price_weekly` decimal(10,2) DEFAULT NULL,
  `price_biweekly` decimal(10,2) DEFAULT NULL,
  `price_monthly` decimal(10,2) DEFAULT NULL,
  `max_staff` int(11) DEFAULT NULL,
  `max_products` int(11) DEFAULT NULL,
  `features` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`features`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_public` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subscription_plans`
--

INSERT INTO `subscription_plans` (`id`, `name`, `description`, `price_weekly`, `price_biweekly`, `price_monthly`, `max_staff`, `max_products`, `features`, `is_active`, `is_public`, `created_at`) VALUES
(1, 'Test (2 weeks)', 'Temporary test plan — remove before launch', NULL, 10.00, NULL, NULL, NULL, NULL, 1, 0, '2026-07-04 00:36:31');

-- --------------------------------------------------------

--
-- Table structure for table `subscription_stk`
--

CREATE TABLE `subscription_stk` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `subscription_id` int(11) DEFAULT NULL,
  `plan_id` int(11) NOT NULL,
  `billing_interval` enum('weekly','biweekly','monthly') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `phone` varchar(15) NOT NULL,
  `checkout_request_id` varchar(64) DEFAULT NULL,
  `merchant_request_id` varchar(64) DEFAULT NULL,
  `status` enum('pending','success','failed','cancelled') NOT NULL DEFAULT 'pending',
  `result_code` int(11) DEFAULT NULL,
  `result_desc` varchar(191) DEFAULT NULL,
  `mpesa_receipt` varchar(32) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tenants`
--

CREATE TABLE `tenants` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `slug` varchar(150) NOT NULL,
  `owner_user_id` int(11) DEFAULT NULL,
  `status` enum('active','suspended','cancelled') NOT NULL DEFAULT 'active',
  `logo_path` varchar(255) DEFAULT NULL,
  `currency` varchar(8) NOT NULL DEFAULT 'KES',
  `phone` varchar(30) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `map_url` varchar(500) DEFAULT NULL,
  `kra_pin` varchar(30) DEFAULT NULL,
  `receipt_footer` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `credits_enabled` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tenants`
--

INSERT INTO `tenants` (`id`, `name`, `slug`, `owner_user_id`, `status`, `logo_path`, `currency`, `phone`, `email`, `website`, `address`, `location`, `map_url`, `kra_pin`, `receipt_footer`, `created_at`, `updated_at`, `credits_enabled`) VALUES
(5, 'Baddie Szn', 'dazu-kinyozi-upararara', 19, 'active', '/public/uploads/branding/tenant_5_0641b899.png', 'KES', '', NULL, NULL, '', '', NULL, '', '', '2026-07-06 08:47:21', '2026-07-06 12:47:11', 0),
(6, 'Baddies szn', 'baddies-szn', 23, 'active', NULL, 'KES', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-06 13:27:23', '2026-07-06 13:27:23', 0);

-- --------------------------------------------------------

--
-- Table structure for table `tenant_services`
--

CREATE TABLE `tenant_services` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `name` varchar(160) NOT NULL,
  `description` text DEFAULT NULL,
  `charge_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `charge_amount_kid` decimal(12,2) DEFAULT NULL,
  `commission_type` enum('percent','fixed') NOT NULL DEFAULT 'percent',
  `commission_value` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status` enum('active','draft') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `department` enum('barber','salon','both') NOT NULL DEFAULT 'both'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tenant_services`
--

INSERT INTO `tenant_services` (`id`, `tenant_id`, `name`, `description`, `charge_amount`, `charge_amount_kid`, `commission_type`, `commission_value`, `status`, `created_at`, `updated_at`, `department`) VALUES
(11, 5, 'Shaving', 'Standard shave', 500.00, 300.00, 'percent', 40.00, 'active', '2026-07-06 12:47:38', '2026-07-06 12:47:38', 'barber'),
(12, 6, 'Shaving', 'Standard shave', 500.00, 300.00, 'percent', 40.00, 'active', '2026-07-06 13:46:40', '2026-07-06 13:46:40', 'barber'),
(13, 6, 'Leave out sew in', NULL, 3500.00, NULL, 'fixed', 1200.00, 'active', '2026-07-06 14:00:25', '2026-07-06 14:30:14', 'salon'),
(14, 6, 'Closure sew in', NULL, 5500.00, NULL, 'fixed', 1400.00, 'active', '2026-07-06 14:21:22', '2026-07-06 14:21:22', 'salon'),
(15, 6, 'Flip Over sew in', NULL, 3500.00, NULL, 'fixed', 1200.00, 'active', '2026-07-06 14:21:51', '2026-07-06 14:21:51', 'salon'),
(16, 6, 'Frontal ponytail', NULL, 6500.00, NULL, 'fixed', 2000.00, 'active', '2026-07-06 14:22:16', '2026-07-06 14:22:16', 'salon'),
(17, 6, 'Frontal updo', NULL, 8000.00, NULL, 'fixed', 2500.00, 'active', '2026-07-06 14:22:57', '2026-07-06 14:25:28', 'salon'),
(18, 6, 'Wig installation', NULL, 3000.00, NULL, 'fixed', 1200.00, 'active', '2026-07-06 14:24:22', '2026-07-06 14:24:22', 'salon'),
(19, 6, 'Leave out sew in ( own bundles)', NULL, 3000.00, NULL, 'fixed', 1200.00, 'active', '2026-07-06 14:25:08', '2026-07-06 14:25:08', 'salon'),
(20, 5, 'Sample', NULL, 250.00, 200.00, 'percent', 20.00, 'active', '2026-07-06 16:54:21', '2026-07-06 16:54:21', 'salon'),
(21, 5, 'Smoothies', 'sijbicobuqivd', 300.00, NULL, 'percent', 20.00, 'active', '2026-07-16 20:22:29', '2026-07-16 20:22:29', 'salon');

-- --------------------------------------------------------

--
-- Table structure for table `testimonials`
--

CREATE TABLE `testimonials` (
  `id` int(11) NOT NULL,
  `customer_name` varchar(100) NOT NULL,
  `customer_email` varchar(100) DEFAULT NULL,
  `customer_phone` varchar(20) DEFAULT NULL,
  `customer_initial` varchar(5) DEFAULT NULL,
  `rating` int(11) DEFAULT 5,
  `testimonial_text` text NOT NULL,
  `service_tag` varchar(100) DEFAULT NULL,
  `role` varchar(100) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `is_featured` tinyint(1) DEFAULT 0,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `approved_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `must_reset_password` tinyint(1) NOT NULL DEFAULT 0,
  `role_id` int(11) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `email_verified` tinyint(1) DEFAULT 0,
  `activation_token` varchar(64) DEFAULT NULL,
  `activation_expires` datetime DEFAULT NULL,
  `activated_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `staff_category` enum('barber','salon','cleaner','reception','general') DEFAULT NULL,
  `supervisor_user_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `tenant_id`, `branch_id`, `username`, `email`, `password_hash`, `must_reset_password`, `role_id`, `is_active`, `email_verified`, `activation_token`, `activation_expires`, `activated_at`, `created_at`, `updated_at`, `staff_category`, `supervisor_user_id`) VALUES
(19, 5, NULL, 'karanjav494', 'karanjav494@gmail.com', '$2y$10$iCKfunSHHTRutOuObSm13.1Io66Qolaxcijx86tIPJhS2R7ItaSI2', 0, 5, 1, 1, NULL, NULL, NULL, '2026-07-06 05:47:21', '2026-07-06 05:47:21', NULL, NULL),
(23, 6, NULL, 'victormlewa446', 'victormlewa446@gmail.com', '$2y$10$zt02g3XCC3R2epqI5APequV66y6Nb9P4inqg9qNPFwSrl8Ka7cSvS', 0, 5, 1, 1, NULL, NULL, NULL, '2026-07-06 10:27:23', '2026-07-06 11:16:31', NULL, NULL),
(24, 6, 6, 'Muthoni', 'patkamau62@gmail.com', '$2y$10$q8FLY8TV.tnB/Erm4bNF8ejkQOi0O6sHhQhWxO...dnsrGQCMV0ba', 0, 7, 1, 1, NULL, NULL, NULL, '2026-07-06 10:52:07', '2026-07-06 13:05:11', 'salon', NULL),
(25, 6, 6, 'Mary', 'wanguimary972@gmail.com', '$2y$10$J8oheIsnRDXkTsZfjXgU1eQkGHFX9mk6Oq9629ZcVMlYpZNZm4OsW', 0, 7, 1, 1, NULL, NULL, NULL, '2026-07-06 10:53:38', '2026-07-06 14:12:28', 'salon', NULL),
(26, 6, 6, 'Shiko', 'imanirespect@gmail.com', '$2y$10$h6B2Y3NMA.CKQC07Vgtq/uTynjJN4foihEo0XtpdVF9T.wmR4t9ji', 0, 7, 1, 1, NULL, NULL, NULL, '2026-07-06 10:54:39', '2026-07-06 13:42:36', 'salon', NULL),
(27, 6, 6, 'Jordy', 'victormlewa97@gmail.com', '$2y$10$8hRMYK8sE6fJQPFlCQtaWexyxmekvIIWy8ahd1/RIBOuinZsD5fQW', 0, 9, 1, 1, NULL, NULL, NULL, '2026-07-06 10:58:57', '2026-07-06 16:12:52', 'reception', NULL),
(28, 6, 6, 'Essie', 'glambyessie1@gmail.com', '$2y$10$6yh1XRABooo0oxUAe5RIPe2FC0GPE2AxqSyzc6k4EQ790ZSiUXKle', 0, 7, 1, 1, NULL, NULL, NULL, '2026-07-06 11:33:56', '2026-07-06 14:48:31', 'salon', NULL),
(29, 6, 6, 'Karari', 'dw6098028@gmail.com', '$2y$10$GfoyXWliVM.IQI1kt42CCOS4/plcaeVCGTjjCN6kp8s6Oyoydv2K6', 1, 7, 1, 1, NULL, NULL, NULL, '2026-07-06 11:35:25', '2026-07-06 11:35:25', 'salon', NULL),
(30, 5, 5, 'Sample', 'njugunavickie7@gmail.com', '$2y$10$g442prHwRrPFbPBHYXlro.HbmcpQUbYAp7oFAbu9.5B2GIlRJ3WPi', 0, 9, 1, 1, NULL, NULL, NULL, '2026-07-06 12:45:44', '2026-07-06 12:48:55', 'reception', NULL),
(31, 5, 5, 'victorkaranjaofficial', 'victorkaranjaofficial@gmail.com', '$2y$10$eRwvMoWjsqXa8LAG.w.02.VcfRHQZVFR1QSD23Pb.xK2r6Ew92fWO', 1, 7, 1, 1, NULL, NULL, NULL, '2026-07-06 13:52:34', '2026-07-06 13:52:34', 'salon', NULL),
(33, 5, NULL, 'pawatams', 'pawatams@gmail.com', '$2y$10$rtWLA1CeYtNNGf5Pu/gqWe3sFZ5CBjLZU6M3cgCJciWU9EdSsAZiO', 1, 6, 1, 1, NULL, NULL, NULL, '2026-07-16 17:25:05', '2026-07-16 17:25:05', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_permissions`
--

CREATE TABLE `user_permissions` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `capability` varchar(64) NOT NULL,
  `effect` enum('grant','revoke') NOT NULL DEFAULT 'grant',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_profiles`
--

CREATE TABLE `user_profiles` (
  `user_id` int(11) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_appt_tenant_scheduled` (`tenant_id`,`scheduled_at`),
  ADD KEY `idx_appt_assigned` (`assigned_user_id`,`scheduled_at`),
  ADD KEY `idx_appt_status` (`tenant_id`,`status`);

--
-- Indexes for table `appointment_services`
--
ALTER TABLE `appointment_services`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_appt_svc` (`appointment_id`);

--
-- Indexes for table `blogs`
--
ALTER TABLE `blogs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_slug` (`slug`),
  ADD KEY `idx_author` (`author_id`),
  ADD KEY `idx_category` (`category_id`),
  ADD KEY `idx_published` (`published_at`),
  ADD KEY `idx_featured` (`is_featured`);

--
-- Indexes for table `blog_categories`
--
ALTER TABLE `blog_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_slug` (`slug`);

--
-- Indexes for table `blog_faqs`
--
ALTER TABLE `blog_faqs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_blog` (`blog_id`);

--
-- Indexes for table `blog_sections`
--
ALTER TABLE `blog_sections`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_blog` (`blog_id`),
  ADD KEY `idx_sort` (`sort_order`);

--
-- Indexes for table `blog_tags`
--
ALTER TABLE `blog_tags`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `blog_tag_relations`
--
ALTER TABLE `blog_tag_relations`
  ADD PRIMARY KEY (`blog_id`,`tag_id`),
  ADD KEY `tag_id` (`tag_id`);

--
-- Indexes for table `branches`
--
ALTER TABLE `branches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_branch_tenant_title` (`tenant_id`,`title`),
  ADD KEY `idx_branch_tenant` (`tenant_id`);

--
-- Indexes for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_cart_product` (`cart_session_id`,`product_id`),
  ADD KEY `idx_cart` (`cart_session_id`),
  ADD KEY `idx_product` (`product_id`);

--
-- Indexes for table `cart_sessions`
--
ALTER TABLE `cart_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `session_id` (`session_id`),
  ADD KEY `idx_session` (`session_id`),
  ADD KEY `idx_user` (`user_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_cat_tenant_name` (`tenant_id`,`name`),
  ADD KEY `idx_cat_tenant` (`tenant_id`);

--
-- Indexes for table `commission_payouts`
--
ALTER TABLE `commission_payouts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cp_tenant` (`tenant_id`),
  ADD KEY `idx_cp_agent` (`agent_user_id`);

--
-- Indexes for table `commission_sales`
--
ALTER TABLE `commission_sales`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_cs_receipt` (`tenant_id`,`receipt_number`),
  ADD KEY `idx_cs_tenant` (`tenant_id`),
  ADD KEY `idx_cs_agent` (`agent_user_id`),
  ADD KEY `idx_cs_payout` (`payout_id`),
  ADD KEY `idx_cs_created` (`created_at`);

--
-- Indexes for table `commission_sale_expenses`
--
ALTER TABLE `commission_sale_expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cse_sale` (`commission_sale_id`);

--
-- Indexes for table `commission_visits`
--
ALTER TABLE `commission_visits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cv_tenant` (`tenant_id`),
  ADD KEY `idx_cv_branch` (`branch_id`),
  ADD KEY `idx_cv_status` (`payment_status`),
  ADD KEY `idx_cv_assigned` (`assigned_user_id`),
  ADD KEY `idx_cv_created` (`created_at`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cust_tenant` (`tenant_id`),
  ADD KEY `idx_cust_phone` (`tenant_id`,`phone`);

--
-- Indexes for table `enquiries`
--
ALTER TABLE `enquiries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_created` (`created_at`);
ALTER TABLE `enquiries` ADD FULLTEXT KEY `idx_search` (`name`,`email`,`message`);

--
-- Indexes for table `enquiry_replies`
--
ALTER TABLE `enquiry_replies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_id` (`admin_id`),
  ADD KEY `idx_enquiry` (`enquiry_id`);

--
-- Indexes for table `gallery`
--
ALTER TABLE `gallery`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_media_type` (`media_type`),
  ADD KEY `idx_sort` (`sort_order`);

--
-- Indexes for table `gallery_categories`
--
ALTER TABLE `gallery_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `hero_slides`
--
ALTER TABLE `hero_slides`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_hero_active_order` (`is_active`,`sort_order`);

--
-- Indexes for table `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_inv_tenant_status` (`tenant_id`,`status`),
  ADD KEY `idx_inv_sale` (`sale_id`);

--
-- Indexes for table `invoice_items`
--
ALTER TABLE `invoice_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_invitem_invoice` (`invoice_id`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email_time` (`email`,`attempt_time`);

--
-- Indexes for table `login_otps`
--
ALTER TABLE `login_otps`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_otp_user_purpose` (`user_id`,`purpose`),
  ADD KEY `idx_otp_expires` (`expires_at`);

--
-- Indexes for table `page_headers`
--
ALTER TABLE `page_headers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `page_key` (`page_key`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_prod_tenant` (`tenant_id`),
  ADD KEY `idx_prod_cat` (`category_id`),
  ADD KEY `idx_prod_subcat` (`subcategory_id`),
  ADD KEY `idx_prod_status` (`status`),
  ADD KEY `idx_prod_lowstock` (`tenant_id`,`quantity`);

--
-- Indexes for table `product_categories`
--
ALTER TABLE `product_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `parent_id` (`parent_id`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `idx_slug` (`slug`);

--
-- Indexes for table `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `project_slug` (`project_slug`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_category` (`category_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_slug` (`project_slug`);

--
-- Indexes for table `project_categories`
--
ALTER TABLE `project_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `category_slug` (`category_slug`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_slug` (`category_slug`);

--
-- Indexes for table `project_gallery`
--
ALTER TABLE `project_gallery`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_project` (`project_id`),
  ADD KEY `idx_sort` (`sort_order`);

--
-- Indexes for table `project_tags`
--
ALTER TABLE `project_tags`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tag_name` (`tag_name`),
  ADD UNIQUE KEY `tag_slug` (`tag_slug`);

--
-- Indexes for table `project_tag_relations`
--
ALTER TABLE `project_tag_relations`
  ADD PRIMARY KEY (`project_id`,`tag_id`),
  ADD KEY `tag_id` (`tag_id`);

--
-- Indexes for table `project_videos`
--
ALTER TABLE `project_videos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_project` (`project_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `role_name` (`role_name`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_sale_receipt` (`tenant_id`,`receipt_number`),
  ADD KEY `idx_sale_tenant` (`tenant_id`),
  ADD KEY `idx_sale_staff` (`staff_id`),
  ADD KEY `idx_sale_branch` (`branch_id`),
  ADD KEY `idx_sale_created` (`tenant_id`,`created_at`);

--
-- Indexes for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_item_sale` (`sale_id`),
  ADD KEY `idx_item_tenant` (`tenant_id`);

--
-- Indexes for table `saved_for_later`
--
ALTER TABLE `saved_for_later`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_saved` (`cart_session_id`,`product_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `schema_migrations`
--
ALTER TABLE `schema_migrations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `filename` (`filename`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_slug` (`slug`),
  ADD KEY `idx_created_by` (`created_by`);

--
-- Indexes for table `service_benefits`
--
ALTER TABLE `service_benefits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_service` (`service_id`);

--
-- Indexes for table `service_expenses`
--
ALTER TABLE `service_expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_se_service` (`service_id`);

--
-- Indexes for table `service_faqs`
--
ALTER TABLE `service_faqs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_service` (`service_id`);

--
-- Indexes for table `service_gallery`
--
ALTER TABLE `service_gallery`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_service` (`service_id`),
  ADD KEY `idx_sort` (`sort_order`);

--
-- Indexes for table `service_sections`
--
ALTER TABLE `service_sections`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_service` (`service_id`),
  ADD KEY `idx_sort` (`sort_order`);

--
-- Indexes for table `site_settings`
--
ALTER TABLE `site_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `store_cart`
--
ALTER TABLE `store_cart`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_product` (`user_id`,`product_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `idx_user` (`user_id`);

--
-- Indexes for table `store_categories`
--
ALTER TABLE `store_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `idx_slug` (`slug`);

--
-- Indexes for table `store_orders`
--
ALTER TABLE `store_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_number` (`order_number`),
  ADD KEY `idx_orders_user` (`user_id`),
  ADD KEY `idx_orders_pay` (`payment_status`),
  ADD KEY `idx_orders_checkout` (`mpesa_checkout_request_id`);

--
-- Indexes for table `store_orders_backup`
--
ALTER TABLE `store_orders_backup`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_number` (`order_number`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_order_number` (`order_number`);

--
-- Indexes for table `store_order_items`
--
ALTER TABLE `store_order_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `parcel_id` (`parcel_id`),
  ADD KEY `idx_items_order` (`order_id`),
  ADD KEY `idx_items_parcel` (`parcel_id`),
  ADD KEY `idx_items_status` (`fulfillment_status`);

--
-- Indexes for table `store_order_items_backup`
--
ALTER TABLE `store_order_items_backup`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `idx_order` (`order_id`);

--
-- Indexes for table `store_products`
--
ALTER TABLE `store_products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_category` (`category_id`),
  ADD KEY `idx_price` (`price`);
ALTER TABLE `store_products` ADD FULLTEXT KEY `idx_search` (`name`,`description`);

--
-- Indexes for table `store_saved_for_later`
--
ALTER TABLE `store_saved_for_later`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_product_saved` (`user_id`,`product_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `idx_user_saved` (`user_id`);

--
-- Indexes for table `subcategories`
--
ALTER TABLE `subcategories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_subcat_tenant_cat_name` (`tenant_id`,`category_id`,`name`),
  ADD KEY `idx_subcat_tenant` (`tenant_id`),
  ADD KEY `idx_subcat_cat` (`category_id`);

--
-- Indexes for table `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sub_tenant` (`tenant_id`),
  ADD KEY `idx_sub_status` (`status`),
  ADD KEY `idx_sub_period_end` (`current_period_end`);

--
-- Indexes for table `subscription_plans`
--
ALTER TABLE `subscription_plans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_plan_active` (`is_active`);

--
-- Indexes for table `subscription_stk`
--
ALTER TABLE `subscription_stk`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_stk_checkout` (`checkout_request_id`),
  ADD KEY `idx_stk_tenant` (`tenant_id`),
  ADD KEY `idx_stk_status` (`status`);

--
-- Indexes for table `tenants`
--
ALTER TABLE `tenants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_tenant_slug` (`slug`),
  ADD KEY `idx_tenant_status` (`status`);

--
-- Indexes for table `tenant_services`
--
ALTER TABLE `tenant_services`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_ts_tenant_name` (`tenant_id`,`name`),
  ADD KEY `idx_ts_tenant` (`tenant_id`);

--
-- Indexes for table `testimonials`
--
ALTER TABLE `testimonials`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_rating` (`rating`),
  ADD KEY `idx_featured` (`is_featured`),
  ADD KEY `idx_sort` (`sort_order`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `uq_users_tenant_email` (`tenant_id`,`email`),
  ADD KEY `role_id` (`role_id`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_users_tenant` (`tenant_id`),
  ADD KEY `idx_users_activation` (`activation_token`),
  ADD KEY `idx_users_branch` (`branch_id`);

--
-- Indexes for table `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_user_cap` (`user_id`,`capability`),
  ADD KEY `idx_perm_tenant` (`tenant_id`);

--
-- Indexes for table `user_profiles`
--
ALTER TABLE `user_profiles`
  ADD PRIMARY KEY (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `appointment_services`
--
ALTER TABLE `appointment_services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `blogs`
--
ALTER TABLE `blogs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `blog_categories`
--
ALTER TABLE `blog_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `blog_faqs`
--
ALTER TABLE `blog_faqs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `blog_sections`
--
ALTER TABLE `blog_sections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `blog_tags`
--
ALTER TABLE `blog_tags`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `branches`
--
ALTER TABLE `branches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `cart_items`
--
ALTER TABLE `cart_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cart_sessions`
--
ALTER TABLE `cart_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `commission_payouts`
--
ALTER TABLE `commission_payouts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `commission_sales`
--
ALTER TABLE `commission_sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `commission_sale_expenses`
--
ALTER TABLE `commission_sale_expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `commission_visits`
--
ALTER TABLE `commission_visits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `enquiries`
--
ALTER TABLE `enquiries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `enquiry_replies`
--
ALTER TABLE `enquiry_replies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `gallery`
--
ALTER TABLE `gallery`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `gallery_categories`
--
ALTER TABLE `gallery_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `hero_slides`
--
ALTER TABLE `hero_slides`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `invoices`
--
ALTER TABLE `invoices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `invoice_items`
--
ALTER TABLE `invoice_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=65;

--
-- AUTO_INCREMENT for table `login_otps`
--
ALTER TABLE `login_otps`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `page_headers`
--
ALTER TABLE `page_headers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `product_categories`
--
ALTER TABLE `product_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `project_categories`
--
ALTER TABLE `project_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `project_gallery`
--
ALTER TABLE `project_gallery`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `project_tags`
--
ALTER TABLE `project_tags`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `project_videos`
--
ALTER TABLE `project_videos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `sale_items`
--
ALTER TABLE `sale_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `saved_for_later`
--
ALTER TABLE `saved_for_later`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `schema_migrations`
--
ALTER TABLE `schema_migrations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `service_benefits`
--
ALTER TABLE `service_benefits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `service_expenses`
--
ALTER TABLE `service_expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `service_faqs`
--
ALTER TABLE `service_faqs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `service_gallery`
--
ALTER TABLE `service_gallery`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `service_sections`
--
ALTER TABLE `service_sections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `store_cart`
--
ALTER TABLE `store_cart`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `store_categories`
--
ALTER TABLE `store_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `store_orders`
--
ALTER TABLE `store_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `store_orders_backup`
--
ALTER TABLE `store_orders_backup`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `store_order_items`
--
ALTER TABLE `store_order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `store_order_items_backup`
--
ALTER TABLE `store_order_items_backup`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `store_products`
--
ALTER TABLE `store_products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `store_saved_for_later`
--
ALTER TABLE `store_saved_for_later`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `subcategories`
--
ALTER TABLE `subcategories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `subscriptions`
--
ALTER TABLE `subscriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `subscription_plans`
--
ALTER TABLE `subscription_plans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `subscription_stk`
--
ALTER TABLE `subscription_stk`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tenants`
--
ALTER TABLE `tenants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `tenant_services`
--
ALTER TABLE `tenant_services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `testimonials`
--
ALTER TABLE `testimonials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `user_permissions`
--
ALTER TABLE `user_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `blogs`
--
ALTER TABLE `blogs`
  ADD CONSTRAINT `blogs_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `blog_categories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `blogs_ibfk_2` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `blog_categories`
--
ALTER TABLE `blog_categories`
  ADD CONSTRAINT `blog_categories_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `blog_faqs`
--
ALTER TABLE `blog_faqs`
  ADD CONSTRAINT `blog_faqs_ibfk_1` FOREIGN KEY (`blog_id`) REFERENCES `blogs` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `blog_sections`
--
ALTER TABLE `blog_sections`
  ADD CONSTRAINT `blog_sections_ibfk_1` FOREIGN KEY (`blog_id`) REFERENCES `blogs` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `blog_tag_relations`
--
ALTER TABLE `blog_tag_relations`
  ADD CONSTRAINT `blog_tag_relations_ibfk_1` FOREIGN KEY (`blog_id`) REFERENCES `blogs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `blog_tag_relations_ibfk_2` FOREIGN KEY (`tag_id`) REFERENCES `blog_tags` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD CONSTRAINT `cart_items_ibfk_1` FOREIGN KEY (`cart_session_id`) REFERENCES `cart_sessions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cart_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `cart_sessions`
--
ALTER TABLE `cart_sessions`
  ADD CONSTRAINT `cart_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `enquiry_replies`
--
ALTER TABLE `enquiry_replies`
  ADD CONSTRAINT `enquiry_replies_ibfk_1` FOREIGN KEY (`enquiry_id`) REFERENCES `enquiries` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `enquiry_replies_ibfk_2` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `gallery`
--
ALTER TABLE `gallery`
  ADD CONSTRAINT `gallery_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `product_categories`
--
ALTER TABLE `product_categories`
  ADD CONSTRAINT `product_categories_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `product_categories` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `projects`
--
ALTER TABLE `projects`
  ADD CONSTRAINT `projects_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `project_categories` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `projects_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `project_categories`
--
ALTER TABLE `project_categories`
  ADD CONSTRAINT `project_categories_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `project_gallery`
--
ALTER TABLE `project_gallery`
  ADD CONSTRAINT `project_gallery_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `project_tag_relations`
--
ALTER TABLE `project_tag_relations`
  ADD CONSTRAINT `project_tag_relations_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `project_tag_relations_ibfk_2` FOREIGN KEY (`tag_id`) REFERENCES `project_tags` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `project_videos`
--
ALTER TABLE `project_videos`
  ADD CONSTRAINT `project_videos_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `saved_for_later`
--
ALTER TABLE `saved_for_later`
  ADD CONSTRAINT `saved_for_later_ibfk_1` FOREIGN KEY (`cart_session_id`) REFERENCES `cart_sessions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `saved_for_later_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `services`
--
ALTER TABLE `services`
  ADD CONSTRAINT `services_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `service_benefits`
--
ALTER TABLE `service_benefits`
  ADD CONSTRAINT `service_benefits_ibfk_1` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `service_faqs`
--
ALTER TABLE `service_faqs`
  ADD CONSTRAINT `service_faqs_ibfk_1` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `service_gallery`
--
ALTER TABLE `service_gallery`
  ADD CONSTRAINT `service_gallery_ibfk_1` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `service_sections`
--
ALTER TABLE `service_sections`
  ADD CONSTRAINT `service_sections_ibfk_1` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `store_cart`
--
ALTER TABLE `store_cart`
  ADD CONSTRAINT `store_cart_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `store_cart_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `store_products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `store_orders`
--
ALTER TABLE `store_orders`
  ADD CONSTRAINT `fk_orders_user_v2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `store_orders_backup`
--
ALTER TABLE `store_orders_backup`
  ADD CONSTRAINT `store_orders_backup_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `store_order_items`
--
ALTER TABLE `store_order_items`
  ADD CONSTRAINT `fk_items_order_v2` FOREIGN KEY (`order_id`) REFERENCES `store_orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `store_order_items_backup`
--
ALTER TABLE `store_order_items_backup`
  ADD CONSTRAINT `store_order_items_backup_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `store_orders_backup` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `store_order_items_backup_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `store_products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `store_products`
--
ALTER TABLE `store_products`
  ADD CONSTRAINT `store_products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `store_categories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `store_products_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `store_saved_for_later`
--
ALTER TABLE `store_saved_for_later`
  ADD CONSTRAINT `store_saved_for_later_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `store_saved_for_later_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `store_products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);

--
-- Constraints for table `user_profiles`
--
ALTER TABLE `user_profiles`
  ADD CONSTRAINT `user_profiles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
