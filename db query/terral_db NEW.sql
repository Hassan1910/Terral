-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 24, 2025 at 03:34 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `terral_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `description`, `image`, `created_at`, `updated_at`) VALUES
(9, 'test', 'test', 'api/uploads/categories/category_67ec22d83b6d8.jpeg', '2025-04-01 16:31:04', '2025-04-01 17:31:04'),
(10, 'test2', 'test', 'api/uploads/categories/category_67ec22ff106b7.jpeg', '2025-04-01 16:31:43', '2025-04-01 17:31:43'),
(11, 'test3', 'test3', 'api/uploads/categories/category_67ec23214df7f.jpeg', '2025-04-01 16:32:17', '2025-04-01 17:32:17'),
(12, 'test4', 'test4', 'api/uploads/categories/category_67ec23403a5f0.jpeg', '2025-04-01 16:32:48', '2025-04-01 17:32:48');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `order_number` varchar(50) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `status` enum('pending','processing','shipped','delivered','canceled') NOT NULL DEFAULT 'pending',
  `payment_status` enum('pending','processing','completed','failed','refunded') NOT NULL DEFAULT 'pending',
  `payment_method` varchar(50) DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `shipping_cost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `tax` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_id` varchar(100) DEFAULT NULL,
  `shipping_address` varchar(255) NOT NULL,
  `shipping_city` varchar(100) NOT NULL,
  `shipping_state` varchar(100) DEFAULT NULL,
  `shipping_postal_code` varchar(20) DEFAULT NULL,
  `shipping_country` varchar(100) NOT NULL,
  `shipping_phone` varchar(20) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `customization_color` varchar(50) DEFAULT NULL,
  `customization_size` varchar(50) DEFAULT NULL,
  `customization_image` varchar(255) DEFAULT NULL,
  `customization_text` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `transaction_id` varchar(255) DEFAULT NULL,
  `status` enum('pending','completed','failed','refunded') NOT NULL DEFAULT 'pending',
  `payment_date` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `order_id`, `amount`, `payment_method`, `transaction_id`, `status`, `payment_date`, `created_at`, `updated_at`) VALUES
(5, 6, 36.00, 'mpesa', 'MAN20250606153653940', '', '2025-06-06 12:36:53', '2025-06-06 13:05:09', '2025-06-06 13:36:53'),
(6, 7, 391.76, 'mpesa', NULL, 'pending', NULL, '2025-06-10 11:02:06', '2025-06-10 11:02:06'),
(7, 9, 433.52, 'mpesa', NULL, 'pending', NULL, '2025-07-17 08:13:25', '2025-07-17 08:13:25'),
(8, 10, 388.28, 'mpesa', NULL, 'pending', NULL, '2025-07-22 07:41:22', '2025-07-22 07:41:22'),
(9, 11, 426.56, 'mpesa', NULL, 'pending', NULL, '2025-07-22 07:46:48', '2025-07-22 07:46:48'),
(10, 12, 389.44, 'mpesa', NULL, 'pending', NULL, '2025-07-24 09:05:14', '2025-07-24 09:05:14'),
(11, 13, 391.76, 'mpesa', NULL, 'pending', NULL, '2025-07-24 09:12:07', '2025-07-24 09:12:07'),
(12, 15, 389.44, 'mpesa', NULL, 'pending', NULL, '2025-07-24 09:49:56', '2025-07-24 09:49:56'),
(13, 16, 389.44, 'mpesa', NULL, 'pending', NULL, '2025-07-24 11:30:36', '2025-07-24 11:30:36'),
(14, 17, 388.28, 'mpesa', NULL, 'completed', NULL, '2025-07-24 12:10:59', '2025-07-24 12:50:26'),
(15, 18, 389.44, 'mpesa', NULL, 'pending', NULL, '2025-07-24 12:13:03', '2025-07-24 12:13:03'),
(16, 19, 389.44, 'mpesa', NULL, 'completed', NULL, '2025-07-24 12:24:31', '2025-07-24 12:42:00');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  `image` varchar(255) DEFAULT NULL,
  `is_customizable` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('active','inactive','out_of_stock') NOT NULL DEFAULT 'active',
  `sku` varchar(100) DEFAULT NULL,
  `weight` decimal(10,2) DEFAULT NULL,
  `dimensions` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `description`, `category_id`, `price`, `stock`, `image`, `is_customizable`, `status`, `sku`, `weight`, `dimensions`, `created_at`, `updated_at`) VALUES
(13, 'test1', 'test1', 9, 1.00, 23, NULL, 1, 'active', '', NULL, '', '2025-04-01 17:33:30', '2025-04-01 17:33:30'),
(14, 'test2', 'test2', 10, 33.00, 39, 'img_67ec23b358303_1743528883.jpg', 1, 'active', 'test', 456.00, 'test', '2025-04-01 17:34:43', '2025-07-24 12:10:59'),
(15, 'test4', 'test', 12, 36.00, 28, 'img_67ec2401d6331_1743528961.jpg', 1, 'active', 'er', 463.00, 'ece4rt', '2025-04-01 17:36:01', '2025-07-24 09:12:07'),
(16, 'test5', 'test', 12, 34.00, 58, 'img_67ec2439c22c9_1743529017.jpg', 1, 'active', '45tf', 87.00, '43tv2', '2025-04-01 17:36:57', '2025-07-24 12:24:31');

-- --------------------------------------------------------

--
-- Table structure for table `product_categories`
--

CREATE TABLE `product_categories` (
  `product_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` enum('text','textarea','image','boolean','number','select','color','json') NOT NULL DEFAULT 'text',
  `setting_label` varchar(255) NOT NULL,
  `setting_description` text DEFAULT NULL,
  `setting_group` varchar(50) NOT NULL DEFAULT 'general',
  `setting_options` text DEFAULT NULL COMMENT 'JSON array of options for select type',
  `is_public` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `setting_label`, `setting_description`, `setting_group`, `setting_options`, `is_public`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 'site_name', 'Terral Online Production System', 'text', 'Site Name', 'Name of your website', 'general', '', 1, 1, '2025-04-01 05:09:22', '2025-04-01 05:09:22'),
(2, 'site_description', 'Customize and order printed/branded products online', 'textarea', 'Site Description', 'Short description about your website', 'general', '', 1, 2, '2025-04-01 05:09:22', '2025-04-01 05:09:22'),
(3, 'admin_email', 'admin@terral.com', 'text', 'Admin Email', 'Email address for admin notifications', 'general', '', 0, 3, '2025-04-01 05:09:22', '2025-04-01 05:09:22'),
(4, 'contact_email', 'contact@terral.com', 'text', 'Contact Email', 'Email address displayed on the contact page', 'general', '', 1, 4, '2025-04-01 05:09:22', '2025-04-01 05:09:22'),
(5, 'contact_phone', '+254700000000', 'text', 'Contact Phone', 'Phone number displayed on the website', 'general', '', 1, 5, '2025-04-01 05:09:22', '2025-04-01 05:09:22'),
(6, 'contact_address', '123 Business Street, Nairobi, Kenya', 'textarea', 'Contact Address', 'Physical address displayed on the website', 'general', '', 1, 6, '2025-04-01 05:09:22', '2025-04-01 05:35:21'),
(7, 'logo', 'assets/img/logo.png', 'image', 'Site Logo', 'Main logo of your website', 'appearance', '', 1, 1, '2025-04-01 05:09:22', '2025-04-01 05:09:22'),
(8, 'primary_color', '#3498db', 'color', 'Primary Color', 'Main color of your website theme', 'appearance', '', 1, 3, '2025-04-01 05:09:22', '2025-04-01 05:09:22'),
(9, 'secondary_color', '#2ecc71', 'color', 'Secondary Color', 'Secondary color of your website theme', 'appearance', '', 1, 4, '2025-04-01 05:09:22', '2025-04-01 05:09:22'),
(10, 'footer_text', '© 2023 Terral Online Production System. All rights reserved.', 'textarea', 'Footer Text', 'Text displayed in the footer section', 'footer', '', 1, 1, '2025-04-01 05:09:22', '2025-04-01 05:35:21'),
(11, 'display_payment_icons', '1', 'boolean', 'Display Payment Icons', 'Show payment method icons in the footer', 'footer', '', 1, 2, '2025-04-01 05:09:22', '2025-04-01 05:09:22'),
(12, 'facebook_url', 'https://facebook.com/terral', 'text', 'Facebook URL', 'Link to your Facebook page', 'social', '', 1, 1, '2025-04-01 05:09:22', '2025-04-01 05:09:22'),
(13, 'twitter_url', 'https://twitter.com/terral', 'text', 'Twitter URL', 'Link to your Twitter profile', 'social', '', 1, 2, '2025-04-01 05:09:22', '2025-04-01 05:09:22'),
(14, 'instagram_url', 'https://instagram.com/terral', 'text', 'Instagram URL', 'Link to your Instagram profile', 'social', '', 1, 3, '2025-04-01 05:09:22', '2025-04-01 05:09:22'),
(15, 'smtp_host', 'smtp.example.com', 'text', 'SMTP Host', 'SMTP server for sending emails', 'email', '', 0, 1, '2025-04-01 05:09:22', '2025-04-01 05:09:22'),
(16, 'smtp_port', '587', 'number', 'SMTP Port', 'Port for the SMTP server', 'email', '', 0, 2, '2025-04-01 05:09:22', '2025-04-01 05:09:22'),
(17, 'smtp_username', 'your_username', 'text', 'SMTP Username', 'Username for SMTP authentication', 'email', '', 0, 3, '2025-04-01 05:09:22', '2025-04-01 05:09:22'),
(18, 'smtp_password', 'your_password', 'text', 'SMTP Password', 'Password for SMTP authentication', 'email', '', 0, 4, '2025-04-01 05:09:22', '2025-04-01 05:09:22'),
(19, 'smtp_encryption', 'tls', 'select', 'SMTP Encryption', 'Encryption type for SMTP', 'email', '[{\"value\":\"none\",\"label\":\"None\"},{\"value\":\"ssl\",\"label\":\"SSL\"},{\"value\":\"tls\",\"label\":\"TLS\"}]', 0, 5, '2025-04-01 05:09:22', '2025-04-01 05:09:22'),
(20, 'email_from_name', 'Terral Support', 'text', 'From Name', 'Name to use in the From field', 'email', '', 0, 6, '2025-04-01 05:09:22', '2025-04-01 05:09:22'),
(21, 'email_from_address', 'support@terral.com', 'text', 'From Email', 'Email address to use in the From field', 'email', '', 0, 7, '2025-04-01 05:09:22', '2025-04-01 05:09:22'),
(22, 'enable_email_notifications', '1', 'boolean', 'Enable Email Notifications', 'Send email notifications for orders and account activities', 'email', '', 0, 8, '2025-04-01 05:09:22', '2025-04-01 05:09:22'),
(23, 'payment_methods', '[{&quot;id&quot;:&quot;mpesa&quot;,&quot;name&quot;:&quot;M-Pesa&quot;,&quot;enabled&quot;:true},{&quot;id&quot;:&quot;bank_transfer&quot;,&quot;name&quot;:&quot;Bank Transfer&quot;,&quot;enabled&quot;:true},{&quot;id&quot;:&quot;cash_on_delivery&quot;,&quot;name&quot;:&quot;Cash on Delivery&quot;,&quot;enabled&quot;:true}]', 'json', 'Payment Methods', 'Configure available payment methods and their status', 'payment', '', 1, 10, '2025-04-01 05:09:22', '2025-04-01 17:55:24'),
(24, 'mpesa_business_shortcode', '174379', 'text', 'M-Pesa Business Shortcode', 'Your M-Pesa business shortcode', 'payment', '', 0, 20, '2025-04-01 05:09:22', '2025-04-01 17:51:02'),
(25, 'mpesa_passkey', 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919', 'text', 'M-Pesa Passkey', 'Your M-Pesa API passkey', 'payment', '', 0, 30, '2025-04-01 05:09:22', '2025-04-01 17:51:03'),
(26, 'mpesa_consumer_key', 'YOUR_CONSUMER_KEY', 'text', 'M-Pesa Consumer Key', 'Your M-Pesa API consumer key', 'payment', '', 0, 40, '2025-04-01 05:09:22', '2025-04-01 17:51:03'),
(27, 'mpesa_consumer_secret', 'YOUR_CONSUMER_SECRET', 'text', 'M-Pesa Consumer Secret', 'Your M-Pesa API consumer secret', 'payment', '', 0, 50, '2025-04-01 05:09:22', '2025-04-01 17:51:03'),
(28, 'currency_code', 'KES', 'text', 'Default Currency Code', 'Default currency code (e.g., KES, USD)', 'payment', '', 1, 60, '2025-04-01 05:09:22', '2025-04-01 17:51:03'),
(29, 'currency_symbol', 'KSh', 'text', 'Currency Symbol', 'Currency symbol to display (e.g., KSh, $)', 'payment', '', 1, 70, '2025-04-01 05:09:22', '2025-04-01 17:51:03'),
(30, 'min_order_amount', '500', 'number', 'Minimum Order Amount', 'Minimum amount required for checkout', 'payment', '', 0, 8, '2025-04-01 05:09:22', '2025-04-01 05:09:22'),
(31, 'bank_account_name', 'Terral Ltd', 'text', 'Bank Account Name', 'Name on the bank account', 'payment', '', 0, 9, '2025-04-01 05:09:22', '2025-04-01 05:09:22'),
(32, 'bank_account_number', '1234567890', 'text', 'Bank Account Number', 'Bank account number for transfers', 'payment', '', 0, 10, '2025-04-01 05:09:22', '2025-04-01 05:09:22'),
(33, 'bank_name', 'Example Bank', 'text', 'Bank Name', 'Name of the bank', 'payment', '', 0, 11, '2025-04-01 05:09:22', '2025-04-01 05:09:22'),
(34, 'bank_branch', 'Main Branch', 'text', 'Bank Branch', 'Branch name', 'payment', '', 0, 12, '2025-04-01 05:09:22', '2025-04-01 05:09:22'),
(35, 'store_name', 'Terral Store', 'text', 'Store Name', 'Name of your store', 'store_info', '', 1, 1, '2025-04-01 05:09:22', '2025-04-01 05:09:22'),
(36, 'store_tagline', 'Custom Printing & Branding', 'text', 'Store Tagline', 'Short description or slogan', 'store_info', '', 1, 2, '2025-04-01 05:09:22', '2025-04-01 05:35:21'),
(37, 'store_phone', '+254700000000', 'text', 'Store Phone', 'Primary contact number', 'store_info', '', 1, 3, '2025-04-01 05:09:22', '2025-04-01 05:09:22'),
(38, 'store_email', 'info@terral.com', 'text', 'Store Email', 'Primary contact email', 'store_info', '', 1, 4, '2025-04-01 05:09:22', '2025-04-01 05:09:22'),
(39, 'store_address', '123 Business Street', 'text', 'Street Address', 'Street address', 'store_info', '', 1, 5, '2025-04-01 05:09:22', '2025-04-01 05:09:22'),
(40, 'store_city', 'Nairobi', 'text', 'City', 'City', 'store_info', '', 1, 6, '2025-04-01 05:09:22', '2025-04-01 05:09:22'),
(41, 'store_state', 'Nairobi County', 'text', 'State/County', 'State or county', 'store_info', '', 1, 7, '2025-04-01 05:09:22', '2025-04-01 05:09:22'),
(42, 'store_zip', '00100', 'text', 'Postal Code', 'Postal or ZIP code', 'store_info', '', 1, 8, '2025-04-01 05:09:22', '2025-04-01 05:09:22'),
(43, 'store_country', 'Kenya', 'text', 'Country', 'Country', 'store_info', '', 1, 9, '2025-04-01 05:09:22', '2025-04-01 05:09:22'),
(44, 'business_hours', 'Mon-Fri: 9am-5pm, Sat: 10am-2pm, Sun: Closed', 'text', 'Business Hours', 'Your business operating hours', 'store_info', '', 1, 10, '2025-04-01 05:09:22', '2025-04-01 05:09:22'),
(45, 'google_maps_url', 'https://maps.google.com/?q=nairobi', 'text', 'Google Maps URL', 'Link to your location on Google Maps', 'store_info', '', 1, 11, '2025-04-01 05:09:22', '2025-04-01 05:09:22'),
(46, 'store_description', 'Terral is a leading provider of custom printed and branded products in Kenya.', 'textarea', 'Store Description', 'Detailed description of your store', 'store_info', '', 1, 12, '2025-04-01 05:09:22', '2025-04-01 05:09:22'),
(47, 'minimum_order_amount', '500', 'number', 'Minimum Order Amount', 'Minimum amount required for checkout', 'payment', NULL, 1, 80, '2025-04-01 17:51:03', '2025-04-01 17:51:03');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','customer') NOT NULL DEFAULT 'customer',
  `phone` varchar(20) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` enum('active','inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `first_name`, `last_name`, `email`, `password`, `role`, `phone`, `address`, `city`, `state`, `postal_code`, `country`, `created_at`, `updated_at`, `status`) VALUES
(1, 'Admin', 'User', 'admin@terral.com', '$2y$10$Eq7OC.ShNeAzJ9lyP.Q3rOtD7F4LMKQOYvjzllRHiQuqZ0WK1g6rS', 'admin', NULL, NULL, NULL, NULL, NULL, NULL, '2025-03-31 16:47:34', '2025-06-06 13:34:20', 'active'),
(6, 'hassan', 'adan', 'adanhassan1910@gmail.com', '$2y$10$qSbfoLz.vhka9Ut2hAjiAuKnWaDNT4ADeQEK5Gw9JVgy0/xeB5kjm', 'customer', '0734567765', 'home12', 'saku', NULL, '60500', 'Kenya', '2025-07-17 09:57:13', '2025-07-17 10:57:13', 'active');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD KEY `idx_name` (`name`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_payment_status` (`payment_status`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_order_id` (`order_id`),
  ADD KEY `idx_product_id` (`product_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_order_id` (`order_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_transaction_id` (`transaction_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_name` (`name`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_stock` (`stock`),
  ADD KEY `idx_price` (`price`),
  ADD KEY `idx_category_id` (`category_id`);

--
-- Indexes for table `product_categories`
--
ALTER TABLE `product_categories`
  ADD PRIMARY KEY (`product_id`,`category_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`),
  ADD KEY `idx_setting_key` (`setting_key`),
  ADD KEY `idx_setting_group` (`setting_group`),
  ADD KEY `idx_is_public` (`is_public`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_role` (`role`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `fk_product_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `product_categories`
--
ALTER TABLE `product_categories`
  ADD CONSTRAINT `product_categories_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_categories_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
