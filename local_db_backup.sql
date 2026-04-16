-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 16, 2026 at 10:53 AM
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
-- Database: `orent`
--

-- --------------------------------------------------------

--
-- Table structure for table `attendance_breaks`
--

CREATE TABLE `attendance_breaks` (
  `id` int(11) NOT NULL,
  `attendance_id` int(11) NOT NULL,
  `break_start` datetime NOT NULL,
  `break_end` datetime DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `start_lat` decimal(10,7) DEFAULT NULL,
  `start_lng` decimal(10,7) DEFAULT NULL,
  `start_address` varchar(500) DEFAULT NULL,
  `end_lat` decimal(10,7) DEFAULT NULL,
  `end_lng` decimal(10,7) DEFAULT NULL,
  `end_address` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance_breaks`
--

INSERT INTO `attendance_breaks` (`id`, `attendance_id`, `break_start`, `break_end`, `reason`, `start_lat`, `start_lng`, `start_address`, `end_lat`, `end_lng`, `end_address`, `created_at`) VALUES
(6, 14, '2026-04-10 13:00:00', '2026-04-10 14:00:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-15 16:09:00'),
(7, 15, '2026-04-11 13:00:00', '2026-04-11 14:00:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-15 16:09:00'),
(8, 16, '2026-03-16 12:00:00', '2026-03-16 13:00:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-15 16:15:38'),
(9, 17, '2026-03-17 12:00:00', '2026-03-17 13:00:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-15 16:15:38'),
(10, 18, '2026-03-18 12:00:00', '2026-03-18 13:00:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-15 16:15:38'),
(11, 19, '2026-03-19 12:00:00', '2026-03-19 13:00:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-15 16:15:38'),
(12, 20, '2026-03-20 12:00:00', '2026-03-20 13:00:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-15 16:15:38'),
(13, 21, '2026-03-21 12:00:00', '2026-03-21 13:00:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-15 16:15:38'),
(14, 22, '2026-03-22 12:00:00', '2026-03-22 13:00:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-15 16:15:38');

-- --------------------------------------------------------

--
-- Table structure for table `bank_accounts`
--

CREATE TABLE `bank_accounts` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `account_number` varchar(50) DEFAULT NULL,
  `balance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bank_accounts`
--

INSERT INTO `bank_accounts` (`id`, `name`, `bank_name`, `account_number`, `balance`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Bank Account', NULL, NULL, 1401.40, 1, '2026-03-05 05:02:13', '2026-04-12 07:22:25'),
(2, 'test', 't1', '122', -9000.00, 1, '2026-03-06 08:14:56', '2026-03-22 10:42:37');

-- --------------------------------------------------------

--
-- Table structure for table `challans`
--

CREATE TABLE `challans` (
  `id` int(11) NOT NULL,
  `vehicle_id` int(11) DEFAULT NULL,
  `client_id` int(11) DEFAULT NULL,
  `challan_no` varchar(100) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `issue_date` date DEFAULT NULL,
  `status` enum('pending','paid') NOT NULL DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `clients`
--

CREATE TABLE `clients` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `alternative_number` varchar(30) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `rating` tinyint(4) DEFAULT NULL,
  `rating_review` text DEFAULT NULL,
  `is_blacklisted` tinyint(1) NOT NULL DEFAULT 0,
  `blacklist_reason` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `proof_file` varchar(500) DEFAULT NULL,
  `photo` varchar(500) DEFAULT NULL,
  `voucher_balance` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `clients`
--

INSERT INTO `clients` (`id`, `name`, `email`, `phone`, `alternative_number`, `address`, `rating`, `rating_review`, `is_blacklisted`, `blacklist_reason`, `notes`, `proof_file`, `photo`, `voucher_balance`, `created_at`, `updated_at`) VALUES
(1, 'hamad', 'hamadmf100@gmail.com', '6235646799', NULL, 'Neha M F Madathiparambil house\r\nC/O Shaina V A Valiyaveetil house', 3, '', 0, NULL, '', NULL, NULL, 317129.45, '2026-03-05 05:05:31', '2026-04-12 07:22:25'),
(2, 'zaman', 'zaman@gmail.com', '6435627845', NULL, 'asas', 3, '', 0, NULL, '', NULL, NULL, 5550.56, '2026-03-05 05:06:52', '2026-03-16 17:38:10'),
(3, 'test2', NULL, '+971501111001', '00000000', '', NULL, NULL, 0, NULL, 'Converted from lead #2', NULL, NULL, 0.00, '2026-03-05 06:31:12', '2026-03-07 18:37:10'),
(5, 'alt', 'alt@gmaill.com', '6435627845', '3434343434', 'sdsd', 2, '', 0, NULL, 'sdsd', NULL, NULL, 156554.89, '2026-03-07 18:36:43', '2026-03-23 19:18:00'),
(6, 'sdsdsd', 'sdsdsd@gmail.com', '5656232345', NULL, 'dsf', 3, NULL, 0, NULL, 'df', NULL, NULL, 899.55, '2026-03-07 18:38:20', '2026-03-07 19:14:00'),
(7, 'withproof', 'withproof@gmail.com', '1212121212', '1212121212', 'sdsd', 3, '', 0, NULL, 'sd', 'uploads/clients/proof_69b138512fe36.png', NULL, 762.00, '2026-03-11 09:39:29', '2026-03-17 04:39:46'),
(8, 'alby', 'multiproof@gmail.com', '5467934567', '1212121212', 'sdsd', 4, '', 0, NULL, 'sd', NULL, 'uploads/clients/client_photo_69c66e460e827.jpeg', 15499.31, '2026-03-11 10:25:42', '2026-03-27 11:47:18'),
(9, 'vlcsnap-2024-09-20-15h40m39s687.png', 'multiproofs2@gmail.com', '1212121212', '3434343434', 'sdfdf', 2, '', 0, NULL, 'df', NULL, NULL, 1367.28, '2026-03-11 10:26:56', '2026-03-23 18:31:22'),
(10, 'vlcsnap-2024-09-20-15h40m39s687.png', 'test2@gmail.com', '3434343434', '2323232323', 'dfdf', 2, '', 0, NULL, '', NULL, NULL, 23673.48, '2026-03-11 10:28:26', '2026-03-28 05:47:59'),
(11, 'test5', 'test555@gmail.com', '1212121212', '3434343434', 'address', 2, '', 0, NULL, 'dfdf', NULL, NULL, 30.00, '2026-03-11 10:31:26', '2026-03-21 14:43:43'),
(12, 'req', 'req@gmail.com', '1212121212', NULL, 'sdsd', NULL, NULL, 0, NULL, '', NULL, NULL, 0.00, '2026-03-13 17:10:26', '2026-03-13 17:10:26'),
(13, 'hamad', 'hamadmf@gmail.com', '6235646792', NULL, 'sdsd', 4, '', 0, NULL, 'sd', NULL, NULL, 0.76, '2026-03-13 17:15:23', '2026-03-16 17:41:25'),
(14, 'hamad', 'hamadmf500@gmail.com', '6235646791', NULL, 'madathipparambil house', 2, '', 0, NULL, 'Converted from lead #10', NULL, NULL, 1700.00, '2026-03-13 17:29:07', '2026-04-03 07:23:06'),
(15, 'added test', 'adedtest@gmail.com', '6723459933', '1212121212', 'sdfdf', 2, '', 0, NULL, 'dfdf', NULL, NULL, 555989.43, '2026-03-18 11:03:56', '2026-04-09 07:38:55'),
(16, 'confirm test', 'confirmtest@gmail.com', '4578342367', NULL, 'sdfdsf', 2, '', 0, NULL, 'df', NULL, NULL, 12103.18, '2026-03-19 08:44:48', '2026-03-24 04:36:57'),
(17, 'crop proof', 'crop@gmail.com', '1673456932', NULL, 'df', NULL, NULL, 0, NULL, 'df', NULL, 'uploads/clients/client_photo_69bfd60a932f7.jpeg', 0.00, '2026-03-22 11:34:53', '2026-03-22 11:44:10'),
(470, 'Client A 69c66ba087345', 'test1_69c66ba087272@example.com', '555900169c66ba087345', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, 0.00, '2026-03-27 11:36:00', '2026-03-27 11:36:00'),
(471, 'Client B 69c66ba0876b5', 'test2_69c66ba08727c@example.com', '555900269c66ba0876b5', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, 0.00, '2026-03-27 11:36:00', '2026-03-27 11:36:00'),
(472, 'Client A 69c66ba087d08', 'test1_69c66ba087283@example.com', '555910169c66ba087d08', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, 0.00, '2026-03-27 11:36:00', '2026-03-27 11:36:00'),
(473, 'Client B 69c66ba088165', 'test2_69c66ba087284@example.com', '555910269c66ba088165', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, 0.00, '2026-03-27 11:36:00', '2026-03-27 11:36:00'),
(474, 'Client A 69c66ba088753', 'test1_69c66ba087286@example.com', '555920169c66ba088753', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, 0.00, '2026-03-27 11:36:00', '2026-03-27 11:36:00'),
(475, 'Client A 69c66ba088cc5', 'testA@example.com_69c66ba088cc5', '5559301', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, 0.00, '2026-03-27 11:36:00', '2026-03-27 11:36:00'),
(476, 'Client B 69c66ba088fa2', 'testB@example.com_69c66ba088fa2', '5559302', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, 0.00, '2026-03-27 11:36:00', '2026-03-27 11:36:00'),
(477, 'Client A 69c66ba08955f', 'testA@example.com_69c66ba08955f', '5559401', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, 0.00, '2026-03-27 11:36:00', '2026-03-27 11:36:00'),
(478, 'Client B 69c66ba08983a', 'testB@example.com_69c66ba08983a', '5559402', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, 0.00, '2026-03-27 11:36:00', '2026-03-27 11:36:00'),
(479, 'Client A 69c66ba089e15', 'testA@example.com_69c66ba089e15', '5559501', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, 0.00, '2026-03-27 11:36:00', '2026-03-27 11:36:00'),
(480, 'Test Client 69c66ba08a2e1', 'testchange_69c66ba08a2e1@example.com', '555960169c66ba08a2e1', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, 'uploads/clients/old_photo.jpg', 0.00, '2026-03-27 11:36:00', '2026-03-27 11:36:00'),
(481, 'Test Client 69c66baac896c', 'test_69c66baac896c@example.com', '55569c66baac896c', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, 0.00, '2026-03-27 11:36:10', '2026-03-27 11:36:10'),
(482, 'Test Client 1 69c66baac9b57', 'test1_69c66baac9b57@example.com', '555169c66baac9b57', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, 0.00, '2026-03-27 11:36:10', '2026-03-27 11:36:10'),
(483, 'Test Client 2 69c66baac9f92', 'test2_69c66baac9f92@example.com', '555269c66baac9f92', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, 0.00, '2026-03-27 11:36:10', '2026-03-27 11:36:10'),
(484, 'Test Client 3 69c66baaca326', 'test3_69c66baaca326@example.com', '555369c66baaca326', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, 0.00, '2026-03-27 11:36:10', '2026-03-27 11:36:10'),
(485, 'Verify Test Client 1 69c66bb130fd6', 'verifytest1_69c66bb130fd6@example.com', '555900169c66bb130fd6', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, 0.00, '2026-03-27 11:36:17', '2026-03-27 11:36:17'),
(486, 'Verify Test Client 2 69c66bb131693', 'verifytest2_69c66bb131693@example.com', '555900269c66bb131693', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, 0.00, '2026-03-27 11:36:17', '2026-03-27 11:36:17'),
(487, 'Client With Photo 69c66bb1324d8', 'withphoto_69c66bb1324d8@example.com', '55580069c66bb1324d8', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, 'uploads/clients/test_photo_69c66bb13243d.jpg', 0.00, '2026-03-27 11:36:17', '2026-03-27 11:36:17'),
(488, 'john', 'jevih81860@ostahie.com', '1234764567', NULL, 'Neha M F Madathiparambil house\r\nC/O Shaina V A Valiyaveetil house', NULL, NULL, 0, NULL, '', NULL, 'uploads/clients/client_photo_69c66df9cdece.jpeg', 0.00, '2026-03-27 11:39:34', '2026-03-27 11:46:01'),
(490, 'Test Client 69d4b5a4e67c0', NULL, '1234567890', NULL, 'Test Address', NULL, NULL, 0, NULL, NULL, NULL, NULL, 0.00, '2026-04-07 07:43:32', '2026-04-07 07:43:32'),
(491, 'Test Client 69d4b5c2366ea', NULL, '1234567890', NULL, 'Test Address', NULL, NULL, 0, NULL, NULL, NULL, NULL, 0.00, '2026-04-07 07:44:02', '2026-04-07 07:44:02'),
(492, 'Preservation Test Client 69d4b679b2ba8', NULL, '9876543210', NULL, 'Preservation Test Address', NULL, NULL, 0, NULL, NULL, NULL, NULL, 0.00, '2026-04-07 07:47:05', '2026-04-07 07:47:05'),
(493, 'Test Client 69d4b6c91c6ba', NULL, '1234567890', NULL, 'Test Address', NULL, NULL, 0, NULL, NULL, NULL, NULL, 0.00, '2026-04-07 07:48:25', '2026-04-07 07:48:25');

-- --------------------------------------------------------

--
-- Table structure for table `client_proofs`
--

CREATE TABLE `client_proofs` (
  `id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `type` varchar(50) DEFAULT NULL,
  `file_path` varchar(500) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `client_proofs`
--

INSERT INTO `client_proofs` (`id`, `client_id`, `title`, `type`, `file_path`, `created_at`) VALUES
(1, 8, 'Image20241031091554.png', 'png', 'uploads/clients/proof_69b14326b85528.74345536.png', '2026-03-11 10:25:42'),
(2, 8, 'vlcsnap-2024-09-30-15h25m00s139.png', 'png', 'uploads/clients/proof_69b14326bb5e07.08658004.png', '2026-03-11 10:25:42'),
(3, 8, 'vlcsnap-2024-09-20-15h40m39s687.png', 'png', 'uploads/clients/proof_69b14326bbca25.54088254.png', '2026-03-11 10:25:42'),
(4, 8, 'vlcsnap-2024-09-16-16h03m27s309 - Copy.png', 'png', 'uploads/clients/proof_69b14326bcaa95.17983065.png', '2026-03-11 10:25:42'),
(5, 8, 'Picture1.png', 'png', 'uploads/clients/proof_69b14326bd02e5.12927638.png', '2026-03-11 10:25:42'),
(6, 9, 'vlcsnap-2024-09-16-16h03m27s309 - Copy.png', 'png', 'uploads/clients/proof_69b143708f8a71.45682752.png', '2026-03-11 10:26:56'),
(7, 9, 'vlcsnap-2024-09-16-16h03m27s309.png', 'png', 'uploads/clients/proof_69b14370907966.33737640.png', '2026-03-11 10:26:56'),
(8, 9, 'vlcsnap-2024-09-20-15h40m37s347 - Copy.png', 'png', 'uploads/clients/proof_69b1437090ead1.53954362.png', '2026-03-11 10:26:56'),
(9, 9, 'vlcsnap-2024-09-20-15h40m37s347.png', 'png', 'uploads/clients/proof_69b143709160c1.59590406.png', '2026-03-11 10:26:56'),
(10, 9, 'vlcsnap-2024-09-20-15h40m39s687.png', 'png', 'uploads/clients/proof_69b1437091bfc9.65790017.png', '2026-03-11 10:26:56'),
(11, 10, 'vlcsnap-2024-09-16-16h03m27s309 - Copy.png', 'png', 'uploads/clients/proof_69b143cad54eb8.26274202.png', '2026-03-11 10:28:26'),
(12, 10, 'vlcsnap-2024-09-16-16h03m27s309.png', 'png', 'uploads/clients/proof_69b143cad59ef2.14394118.png', '2026-03-11 10:28:26'),
(13, 10, 'vlcsnap-2024-09-20-15h40m37s347 - Copy.png', 'png', 'uploads/clients/proof_69b143cad5f7f4.15849199.png', '2026-03-11 10:28:26'),
(14, 10, 'vlcsnap-2024-09-20-15h40m37s347.png', 'png', 'uploads/clients/proof_69b143cad64377.09583396.png', '2026-03-11 10:28:26'),
(15, 10, 'vlcsnap-2024-09-20-15h40m39s687.png', 'png', 'uploads/clients/proof_69b143cad6f572.18720900.png', '2026-03-11 10:28:26'),
(16, 11, 'vlcsnap-2024-09-16-16h03m27s309 - Copy.png', 'png', 'uploads/clients/proof_69b1447e0f2872.70850138.png', '2026-03-11 10:31:26'),
(17, 11, 'vlcsnap-2024-09-16-16h03m27s309.png', 'png', 'uploads/clients/proof_69b1447e0f99d3.63984018.png', '2026-03-11 10:31:26'),
(18, 11, 'vlcsnap-2024-09-20-15h40m37s347 - Copy.png', 'png', 'uploads/clients/proof_69b1447e1040c4.10443651.png', '2026-03-11 10:31:26'),
(19, 11, 'vlcsnap-2024-09-20-15h40m37s347.png', 'png', 'uploads/clients/proof_69b1447e1091f5.03665051.png', '2026-03-11 10:31:26'),
(20, 11, 'vlcsnap-2024-09-20-15h40m39s687.png', 'png', 'uploads/clients/proof_69b1447e10d906.60412561.png', '2026-03-11 10:31:26'),
(21, 12, 'Picture1.png', 'png', 'uploads/clients/proof_69b445020e6805.34111346.png', '2026-03-13 17:10:26'),
(22, 13, 'Picture1.png', 'png', 'uploads/clients/proof_69b4462b2e40b1.37228190.png', '2026-03-13 17:15:23'),
(23, 14, 'Picture1.png', 'png', 'uploads/clients/proof_69b44963c7fa92.26651381.png', '2026-03-13 17:29:07'),
(24, 15, 'Image20241031091554.png', 'png', 'uploads/clients/proof_69ba869cc8df34.50119792.png', '2026-03-18 11:03:56'),
(25, 16, 'Image20241031091554.png', 'png', 'uploads/clients/proof_69bbb781006a65.87150386.png', '2026-03-19 08:44:49'),
(26, 17, 'Image20241031091554.png', 'png', 'uploads/clients/proof_69bfd3ddb9a621.35272721.png', '2026-03-22 11:34:53'),
(27, 17, 'Image20241031091554.png', 'jpeg', 'uploads/clients/proof_69bfd60a92c079.07941505.jpeg', '2026-03-22 11:44:10'),
(28, 488, 'Image20241031091554.png', 'jpeg', 'uploads/clients/proof_69c66c76562360.49020539.jpeg', '2026-03-27 11:39:34');

-- --------------------------------------------------------

--
-- Table structure for table `client_reviews`
--

CREATE TABLE `client_reviews` (
  `id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `reservation_id` int(11) NOT NULL,
  `rating` tinyint(4) NOT NULL,
  `review` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `client_reviews`
--

INSERT INTO `client_reviews` (`id`, `client_id`, `reservation_id`, `rating`, `review`, `created_by`, `created_at`) VALUES
(1, 1, 15, 3, '1st review test', NULL, '2026-03-08 21:43:08'),
(2, 1, 16, 3, '2nd review', NULL, '2026-03-08 21:46:41'),
(3, 1, 17, 5, 'reviewd by hamad adminnnn', 1, '2026-03-08 22:30:57'),
(4, 1, 5, 5, NULL, 1, '2026-03-10 00:51:25'),
(5, 1, 4, 3, NULL, 1, '2026-03-10 00:52:43'),
(6, 1, 18, 3, NULL, 1, '2026-03-10 00:54:50'),
(7, 1, 19, 3, NULL, 1, '2026-03-10 01:08:55'),
(8, 5, 22, 3, NULL, 1, '2026-03-11 12:55:35'),
(9, 5, 25, 3, 'sad', 1, '2026-03-11 14:18:53'),
(10, 8, 26, 4, NULL, 1, '2026-03-12 11:22:53'),
(11, 1, 24, 3, NULL, 1, '2026-03-12 20:39:22'),
(12, 9, 28, 2, 'enter your review here', 1, '2026-03-13 16:18:20'),
(13, 2, 33, 3, NULL, 1, '2026-03-16 23:08:10'),
(14, 13, 32, 4, NULL, 1, '2026-03-16 23:11:25'),
(15, 7, 35, 3, NULL, 1, '2026-03-17 10:09:46'),
(16, 14, 38, 2, NULL, 1, '2026-03-18 01:15:23'),
(17, 16, 41, 2, NULL, 1, '2026-03-21 20:12:11'),
(18, 11, 39, 2, NULL, 1, '2026-03-21 20:13:43'),
(19, 5, 34, 3, NULL, 1, '2026-03-21 20:14:27'),
(20, 5, 47, 2, NULL, 1, '2026-03-23 18:19:46'),
(21, 5, 48, 2, NULL, 1, '2026-03-23 23:39:08'),
(22, 5, 49, 2, NULL, 1, '2026-03-23 23:47:59'),
(23, 9, 50, 2, NULL, 1, '2026-03-24 00:01:22'),
(24, 5, 46, 2, NULL, 1, '2026-03-24 00:48:00'),
(25, 16, 51, 2, NULL, 1, '2026-03-24 10:06:57'),
(26, 15, 52, 2, NULL, 1, '2026-03-24 18:16:22'),
(27, 8, 53, 4, NULL, 1, '2026-03-24 19:05:16'),
(28, 1, 45, 3, NULL, 1, '2026-03-24 19:43:46'),
(29, 15, 54, 2, NULL, 1, '2026-03-25 16:45:32'),
(30, 15, 44, 2, NULL, 1, '2026-03-28 11:14:48'),
(31, 10, 31, 2, NULL, 1, '2026-03-28 11:17:59'),
(32, 14, 55, 2, NULL, 1, '2026-04-03 12:53:06'),
(33, 15, 68, 2, NULL, 1, '2026-04-09 10:38:07'),
(34, 15, 56, 2, NULL, 2, '2026-04-09 13:08:55'),
(35, 1, 73, 3, NULL, 1, '2026-04-12 12:52:25');

-- --------------------------------------------------------

--
-- Table structure for table `client_voucher_transactions`
--

CREATE TABLE `client_voucher_transactions` (
  `id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `reservation_id` int(11) DEFAULT NULL,
  `type` enum('credit','debit') NOT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `note` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `client_voucher_transactions`
--

INSERT INTO `client_voucher_transactions` (`id`, `client_id`, `reservation_id`, `type`, `amount`, `note`, `created_at`) VALUES
(1, 1, 1, 'credit', 4.76, 'Early return credit for reservation #1', '2026-03-06 08:18:32'),
(2, 1, 3, 'credit', 462.24, 'Early return credit for reservation #3', '2026-03-06 08:47:08'),
(3, 2, 7, 'credit', 698.26, 'Early return credit for reservation #7', '2026-03-07 18:52:07'),
(4, 1, 8, 'credit', 1899.61, 'Early return credit for reservation #8', '2026-03-07 19:00:12'),
(5, 6, 9, 'credit', 899.55, 'Early return credit for reservation #9', '2026-03-07 19:14:00'),
(6, 1, 10, 'credit', 600.00, 'Early return credit for reservation #10', '2026-03-07 19:19:43'),
(7, 1, 11, 'credit', 499.57, 'Early return credit for reservation #11', '2026-03-07 19:39:30'),
(8, 5, 12, 'credit', 800.00, 'Early return credit for reservation #12', '2026-03-07 19:55:32'),
(9, 1, 6, 'credit', 1172.33, 'Early return credit for reservation #6', '2026-03-07 20:12:15'),
(10, 1, 14, 'credit', 800.00, 'Early return credit for reservation #14', '2026-03-07 20:27:53'),
(11, 1, 13, 'credit', 553.54, 'Early return credit for reservation #13', '2026-03-07 20:30:04'),
(12, 1, 15, 'credit', 30.00, 'Early return credit for reservation #15', '2026-03-08 16:13:08'),
(13, 1, 16, 'credit', 1443.00, 'Early return credit for reservation #16', '2026-03-08 16:16:41'),
(14, 1, 17, 'credit', 28637.61, 'Early return credit for reservation #17', '2026-03-08 17:00:57'),
(15, 1, 4, 'credit', 2672.22, 'Early return credit for reservation #4', '2026-03-09 19:22:43'),
(16, 1, 18, 'credit', 72.52, 'Early return credit for reservation #18', '2026-03-09 19:24:50'),
(17, 1, 19, 'credit', 999.61, 'Early return credit for reservation #19', '2026-03-09 19:38:55'),
(18, 5, 22, 'credit', 18.00, 'Early return credit for reservation #22', '2026-03-11 07:25:35'),
(19, 5, 25, 'credit', 5999.31, 'Early return credit for reservation #25', '2026-03-11 08:48:53'),
(20, 8, 26, 'credit', 5999.31, 'Early return credit for reservation #26', '2026-03-12 05:52:53'),
(21, 1, 24, 'credit', 9569.47, 'Early return credit for reservation #24', '2026-03-12 15:09:22'),
(22, 9, 28, 'credit', 1225.29, 'Early return credit for reservation #28', '2026-03-13 10:48:20'),
(23, 2, 33, 'credit', 4852.30, 'Early return credit for reservation #33', '2026-03-16 17:38:10'),
(24, 13, 32, 'credit', 0.76, 'Early return credit for reservation #32', '2026-03-16 17:41:25'),
(25, 7, 35, 'credit', 762.00, 'Early return credit for reservation #35', '2026-03-17 04:39:46'),
(26, 16, 41, 'credit', 3.53, 'Early return credit for reservation #41', '2026-03-21 14:42:11'),
(27, 11, 39, 'credit', 30.00, 'Early return credit for reservation #39', '2026-03-21 14:43:43'),
(28, 5, 34, 'credit', 59976.33, 'Early return credit for reservation #34', '2026-03-21 14:44:27'),
(30, 5, 47, 'credit', 63100.00, 'Early return credit for reservation #47', '2026-03-23 12:49:46'),
(31, 5, 48, 'credit', 263.95, 'Early return credit for reservation #48', '2026-03-23 18:09:08'),
(32, 5, 49, 'credit', 50.00, 'Early return credit for reservation #49', '2026-03-23 18:17:59'),
(33, 9, 50, 'credit', 141.99, 'Early return credit for reservation #50', '2026-03-23 18:31:22'),
(34, 5, 46, 'credit', 26347.30, 'Early return credit for reservation #46', '2026-03-23 19:18:00'),
(35, 16, 51, 'credit', 12099.65, 'Early return credit for reservation #51', '2026-03-24 04:36:57'),
(36, 15, 52, 'credit', 9699.29, 'Early return credit for reservation #52', '2026-03-24 12:46:22'),
(37, 8, 53, 'credit', 9500.00, 'Early return credit for reservation #53', '2026-03-24 13:35:16'),
(38, 1, 45, 'credit', 264664.36, 'Early return credit for reservation #45', '2026-03-24 14:13:46'),
(39, 15, 54, 'credit', 519287.83, 'Early return credit for reservation #54', '2026-03-25 11:15:32'),
(40, 15, 44, 'credit', 26001.42, 'Early return credit for reservation #44', '2026-03-28 05:44:48'),
(41, 10, 31, 'credit', 23673.48, 'Early return credit for reservation #31', '2026-03-28 05:47:59'),
(42, 14, 55, 'credit', 1700.00, 'Early return credit for reservation #55', '2026-04-03 07:23:06'),
(43, 15, 68, 'credit', 350.00, 'Excess advance from reservation #68 vehicle change', '2026-04-08 06:57:30'),
(44, 15, 68, 'credit', 595.41, 'Early return credit for reservation #68', '2026-04-09 05:08:07'),
(45, 15, 56, 'credit', 55.48, 'Early return credit for reservation #56', '2026-04-09 07:38:55'),
(46, 1, 73, 'credit', 3048.61, 'Early return credit for reservation #73', '2026-04-12 07:22:25');

-- --------------------------------------------------------

--
-- Table structure for table `credit_payment_allocations`
--

CREATE TABLE `credit_payment_allocations` (
  `id` int(11) NOT NULL,
  `credit_income_entry_id` int(11) NOT NULL COMMENT 'FK to ledger_entries.id (income entry being paid)',
  `credit_payment_entry_id` int(11) NOT NULL COMMENT 'FK to ledger_entries.id (payment entry)',
  `allocated_amount` decimal(12,2) NOT NULL COMMENT 'Amount allocated from payment to income entry',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `credit_payment_allocations`
--

INSERT INTO `credit_payment_allocations` (`id`, `credit_income_entry_id`, `credit_payment_entry_id`, `allocated_amount`, `created_at`) VALUES
(1, 9, 68, 700.00, '2026-04-03 09:42:36'),
(2, 11, 68, 600.00, '2026-04-03 09:42:36'),
(3, 11, 70, 50.00, '2026-04-03 09:42:36'),
(4, 24, 70, 300.00, '2026-04-03 09:42:36'),
(5, 28, 70, 50.00, '2026-04-03 09:42:36'),
(6, 28, 91, 100.00, '2026-04-03 09:42:36'),
(7, 28, 128, 100.00, '2026-04-03 09:42:36'),
(8, 28, 130, 100.00, '2026-04-03 09:42:36'),
(9, 28, 132, 100.00, '2026-04-03 09:42:36'),
(10, 31, 134, 100.00, '2026-04-03 09:42:36'),
(11, 31, 136, 100.00, '2026-04-03 09:42:36'),
(12, 31, 138, 100.00, '2026-04-03 09:42:36'),
(13, 35, 140, 200.00, '2026-04-03 09:42:36'),
(14, 38, 140, 200.00, '2026-04-03 09:42:36'),
(15, 38, 142, 100.00, '2026-04-03 09:42:36'),
(16, 41, 144, 100.00, '2026-04-03 09:42:36'),
(17, 41, 199, 200.00, '2026-04-03 09:42:36'),
(18, 41, 205, 100.00, '2026-04-03 09:42:36'),
(19, 77, 205, 100.00, '2026-04-03 09:42:36'),
(32, 216, 305, 1000.00, '2026-04-03 09:50:05'),
(33, 213, 307, 1000.00, '2026-04-03 10:05:53');

-- --------------------------------------------------------

--
-- Table structure for table `damage_costs`
--

CREATE TABLE `damage_costs` (
  `id` int(11) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `cost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `documents`
--

CREATE TABLE `documents` (
  `id` int(11) NOT NULL,
  `vehicle_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `type` varchar(50) DEFAULT NULL,
  `file_path` varchar(500) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `cancellation_reason` text DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancellation_by` int(11) DEFAULT NULL,
  `refund_amount` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `documents`
--

INSERT INTO `documents` (`id`, `vehicle_id`, `title`, `type`, `file_path`, `created_at`, `cancellation_reason`, `cancelled_at`, `cancellation_by`, `refund_amount`) VALUES
(1, 5, 'activity_icon.webp', 'webp', 'uploads/documents/doc_69ac7f693b70f0.11961359.webp', '2026-03-07 19:41:29', NULL, NULL, NULL, NULL),
(2, 5, 'Insurance - activity_icon.webp', 'webp', 'uploads/documents/doc_69ac7f693c1ba9.97279485.webp', '2026-03-07 19:41:29', NULL, NULL, NULL, NULL),
(3, 5, 'Pollution - Image20241031091554.png', 'png', 'uploads/documents/doc_69ac7f693c8286.05774072.png', '2026-03-07 19:41:29', NULL, NULL, NULL, NULL),
(4, 6, 'Insurance - activity_icon.webp', 'webp', 'uploads/documents/doc_69ac84401638a0.77588531.webp', '2026-03-07 20:02:08', NULL, NULL, NULL, NULL),
(5, 1, 'activity_icon.webp', 'webp', 'uploads/documents/doc_69b3b7359b9681.54356108.webp', '2026-03-13 07:05:25', NULL, NULL, NULL, NULL),
(6, 1, 'Image20241031091554.png', 'png', 'uploads/documents/doc_69b3b7359c39e4.86133730.png', '2026-03-13 07:05:25', NULL, NULL, NULL, NULL),
(7, 1, 'Insurance - Image20241031091554.png', 'png', 'uploads/documents/doc_69b3b7359caa56.89792838.png', '2026-03-13 07:05:25', NULL, NULL, NULL, NULL),
(8, 1, 'Pollution - activity_icon.webp', 'webp', 'uploads/documents/doc_69b3b7359e0876.12741805.webp', '2026-03-13 07:05:25', NULL, NULL, NULL, NULL),
(9, 6, 'Pollution - Image20241031091554.png', 'png', 'uploads/documents/doc_69b4f724ba2068.10483978.png', '2026-03-14 05:50:28', NULL, NULL, NULL, NULL),
(10, 6, 'Pollution - Image20241031091554.png', 'png', 'uploads/documents/doc_69b4f76ba17019.59348638.png', '2026-03-14 05:51:39', NULL, NULL, NULL, NULL),
(11, 8, 'Insurance - Image20241031091554.png', 'png', 'uploads/documents/doc_69c2cb20eee1b2.68768004.png', '2026-03-24 17:34:24', NULL, NULL, NULL, NULL),
(12, 8, 'Pollution - activity_icon.webp', 'webp', 'uploads/documents/doc_69c2cb20efe906.06814365.webp', '2026-03-24 17:34:24', NULL, NULL, NULL, NULL),
(13, 33, 'activity_icon.webp', 'webp', 'uploads/documents/doc_69db4af92d69c2.26670361.webp', '2026-04-12 07:34:17', NULL, NULL, NULL, NULL),
(15, 33, 'Pollution - activity_icon.webp', 'webp', 'uploads/documents/doc_69db4af92ec746.35244946.webp', '2026-04-12 07:34:17', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `emi_investments`
--

CREATE TABLE `emi_investments` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `lender` varchar(255) DEFAULT NULL,
  `total_cost` decimal(12,2) NOT NULL DEFAULT 0.00,
  `down_payment` decimal(12,2) NOT NULL DEFAULT 0.00,
  `loan_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `emi_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `tenure_months` int(11) NOT NULL DEFAULT 1,
  `start_date` date NOT NULL,
  `notes` text DEFAULT NULL,
  `down_payment_account_id` int(11) DEFAULT NULL,
  `down_payment_ledger_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `emi_investments`
--

INSERT INTO `emi_investments` (`id`, `title`, `lender`, `total_cost`, `down_payment`, `loan_amount`, `emi_amount`, `tenure_months`, `start_date`, `notes`, `down_payment_account_id`, `down_payment_ledger_id`, `created_at`) VALUES
(1, 'test', 'hdfc', 10000.00, 1000.00, 9000.00, 1000.00, 9, '2026-03-05', NULL, 1, 4, '2026-03-05 15:44:37'),
(2, 'Test BMW EMI', 'HDFC Bank', 50000.00, 10000.00, 40000.00, 5000.00, 8, '2026-03-16', 'test', 1, 160, '2026-03-16 15:13:28'),
(3, 'doubling check', 'hdfc', 10000.00, 1000.00, 9000.00, 1000.00, 9, '2026-03-18', NULL, 1, 188, '2026-03-18 18:24:33'),
(4, 'test', 'hdfc', 100000.00, 10000.00, 90000.00, 10000.00, 9, '2026-03-23', NULL, 1, 283, '2026-03-25 11:37:34');

-- --------------------------------------------------------

--
-- Table structure for table `emi_schedules`
--

CREATE TABLE `emi_schedules` (
  `id` int(11) NOT NULL,
  `investment_id` int(11) NOT NULL,
  `installment_no` int(11) NOT NULL,
  `due_date` date NOT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('pending','paid') NOT NULL DEFAULT 'pending',
  `paid_date` date DEFAULT NULL,
  `bank_account_id` int(11) DEFAULT NULL,
  `ledger_entry_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `emi_schedules`
--

INSERT INTO `emi_schedules` (`id`, `investment_id`, `installment_no`, `due_date`, `amount`, `status`, `paid_date`, `bank_account_id`, `ledger_entry_id`, `notes`) VALUES
(1, 1, 1, '2026-03-05', 1000.00, 'paid', '2026-03-05', 1, 5, NULL),
(2, 1, 2, '2026-04-05', 1000.00, 'pending', NULL, NULL, NULL, NULL),
(3, 1, 3, '2026-05-05', 1000.00, 'pending', NULL, NULL, NULL, NULL),
(4, 1, 4, '2026-06-05', 1000.00, 'pending', NULL, NULL, NULL, NULL),
(5, 1, 5, '2026-07-05', 1000.00, 'pending', NULL, NULL, NULL, NULL),
(6, 1, 6, '2026-08-05', 1000.00, 'pending', NULL, NULL, NULL, NULL),
(7, 1, 7, '2026-09-05', 1000.00, 'pending', NULL, NULL, NULL, NULL),
(8, 1, 8, '2026-10-05', 1000.00, 'pending', NULL, NULL, NULL, NULL),
(9, 1, 9, '2026-11-05', 1000.00, 'pending', NULL, NULL, NULL, NULL),
(10, 2, 1, '2026-03-17', 5000.00, 'pending', NULL, NULL, NULL, NULL),
(11, 2, 2, '2026-04-16', 5000.00, 'pending', NULL, NULL, NULL, NULL),
(12, 2, 3, '2026-05-16', 5000.00, 'pending', NULL, NULL, NULL, NULL),
(13, 2, 4, '2026-06-16', 5000.00, 'pending', NULL, NULL, NULL, NULL),
(14, 2, 5, '2026-07-16', 5000.00, 'pending', NULL, NULL, NULL, NULL),
(15, 2, 6, '2026-08-16', 5000.00, 'pending', NULL, NULL, NULL, NULL),
(16, 2, 7, '2026-09-16', 5000.00, 'pending', NULL, NULL, NULL, NULL),
(17, 2, 8, '2026-10-16', 5000.00, 'pending', NULL, NULL, NULL, NULL),
(18, 3, 1, '2026-03-18', 1000.00, 'pending', NULL, NULL, NULL, NULL),
(19, 3, 2, '2026-04-18', 1000.00, 'pending', NULL, NULL, NULL, NULL),
(20, 3, 3, '2026-05-18', 1000.00, 'pending', NULL, NULL, NULL, NULL),
(21, 3, 4, '2026-06-18', 1000.00, 'pending', NULL, NULL, NULL, NULL),
(22, 3, 5, '2026-07-18', 1000.00, 'pending', NULL, NULL, NULL, NULL),
(23, 3, 6, '2026-08-18', 1000.00, 'pending', NULL, NULL, NULL, NULL),
(24, 3, 7, '2026-09-18', 1000.00, 'pending', NULL, NULL, NULL, NULL),
(25, 3, 8, '2026-10-18', 1000.00, 'pending', NULL, NULL, NULL, NULL),
(26, 3, 9, '2026-11-18', 1000.00, 'pending', NULL, NULL, NULL, NULL),
(27, 4, 1, '2026-03-23', 10000.00, 'pending', NULL, NULL, NULL, NULL),
(28, 4, 2, '2026-04-23', 10000.00, 'pending', NULL, NULL, NULL, NULL),
(29, 4, 3, '2026-05-23', 10000.00, 'pending', NULL, NULL, NULL, NULL),
(30, 4, 4, '2026-06-23', 10000.00, 'pending', NULL, NULL, NULL, NULL),
(31, 4, 5, '2026-07-23', 10000.00, 'pending', NULL, NULL, NULL, NULL),
(32, 4, 6, '2026-08-23', 10000.00, 'pending', NULL, NULL, NULL, NULL),
(33, 4, 7, '2026-09-23', 10000.00, 'pending', NULL, NULL, NULL, NULL),
(34, 4, 8, '2026-10-23', 10000.00, 'pending', NULL, NULL, NULL, NULL),
(35, 4, 9, '2026-11-23', 10000.00, 'pending', NULL, NULL, NULL, NULL),
(36, 1, 99, '2026-03-25', 5000.00, 'pending', NULL, NULL, NULL, NULL),
(37, 1, 99, '2026-03-25', 5000.00, 'pending', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `category` varchar(100) DEFAULT NULL,
  `expense_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `gps_daily_checks`
--

CREATE TABLE `gps_daily_checks` (
  `id` int(11) NOT NULL,
  `reservation_id` int(11) NOT NULL,
  `vehicle_id` int(11) NOT NULL,
  `check_date` date NOT NULL,
  `check_slot` tinyint(4) NOT NULL,
  `tracking_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_location` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `gps_daily_checks`
--

INSERT INTO `gps_daily_checks` (`id`, `reservation_id`, `vehicle_id`, `check_date`, `check_slot`, `tracking_active`, `last_location`, `notes`, `updated_by`, `updated_at`, `created_at`) VALUES
(1, 27, 2, '2026-03-16', 1, 1, '1', NULL, 1, '2026-03-16 17:46:24', '2026-03-16 05:11:48'),
(3, 27, 2, '2026-03-16', 2, 1, '1', NULL, 1, '2026-03-16 17:46:24', '2026-03-16 05:12:22'),
(6, 27, 2, '2026-03-16', 3, 0, '1', 'sdsd', 1, '2026-03-16 17:46:24', '2026-03-16 05:12:32'),
(10, 30, 1, '2026-03-16', 1, 1, 'thrissur', NULL, 1, '2026-03-16 17:46:24', '2026-03-16 09:32:53'),
(15, 30, 1, '2026-03-16', 2, 1, 'thrissur', NULL, 1, '2026-03-16 17:46:24', '2026-03-16 09:33:04'),
(21, 30, 1, '2026-03-16', 3, 1, 'thrissur', NULL, 1, '2026-03-16 17:46:24', '2026-03-16 09:33:17'),
(31, 32, 5, '2026-03-16', 1, 1, 'eranakulam', NULL, 1, '2026-03-16 15:26:23', '2026-03-16 12:13:05'),
(39, 32, 5, '2026-03-16', 2, 1, 'eranakulam', NULL, 1, '2026-03-16 15:29:45', '2026-03-16 12:13:32'),
(48, 32, 5, '2026-03-16', 3, 0, 'eranakulam', 'no', 1, '2026-03-16 15:29:45', '2026-03-16 12:13:50'),
(118, 33, 7, '2026-03-16', 1, 1, 'kochi', NULL, 1, '2026-03-16 15:29:45', '2026-03-16 15:25:56'),
(129, 33, 7, '2026-03-16', 2, 1, 'kochi', NULL, 1, '2026-03-16 15:29:45', '2026-03-16 15:26:05'),
(141, 33, 7, '2026-03-16', 3, 1, 'kochi', NULL, 1, '2026-03-16 15:29:45', '2026-03-16 15:26:23'),
(162, 34, 7, '2026-03-16', 1, 1, 'palakkad', NULL, 1, '2026-03-16 17:46:24', '2026-03-16 17:46:18'),
(170, 34, 7, '2026-03-17', 1, 1, 'palakkad', NULL, 1, '2026-03-17 04:26:08', '2026-03-17 04:21:20'),
(172, 34, 7, '2026-03-17', 2, 1, 'palakkad', NULL, 1, '2026-03-17 04:26:08', '2026-03-17 04:21:39'),
(177, 30, 1, '2026-04-14', 1, 0, 'thrissur', 'now in kozhikode', 1, '2026-04-14 10:06:06', '2026-04-14 10:06:06');

-- --------------------------------------------------------

--
-- Table structure for table `gps_tracking`
--

CREATE TABLE `gps_tracking` (
  `id` int(11) NOT NULL,
  `reservation_id` int(11) DEFAULT NULL,
  `vehicle_id` int(11) NOT NULL,
  `tracker_id` varchar(100) DEFAULT NULL,
  `last_location` varchar(255) DEFAULT NULL,
  `tracking_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_seen` timestamp NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `cancellation_reason` text DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancellation_by` int(11) DEFAULT NULL,
  `refund_amount` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `gps_tracking`
--

INSERT INTO `gps_tracking` (`id`, `reservation_id`, `vehicle_id`, `tracker_id`, `last_location`, `tracking_active`, `last_seen`, `notes`, `updated_by`, `updated_at`, `cancellation_reason`, `cancelled_at`, `cancellation_by`, `refund_amount`) VALUES
(1, 3, 2, NULL, 'thrissur kodungallur', 1, '2026-03-06 08:45:55', NULL, 1, '2026-03-06 08:45:55', NULL, NULL, NULL, NULL),
(2, 3, 2, NULL, 'thrissur kodungallur', 0, '2026-03-06 08:46:32', 'as', 1, '2026-03-06 08:46:32', NULL, NULL, NULL, NULL),
(3, 4, 2, NULL, NULL, 1, '2026-03-07 05:45:54', 'Initial delivery', 1, '2026-03-07 05:45:54', NULL, NULL, NULL, NULL),
(4, 5, 1, NULL, NULL, 1, '2026-03-07 05:46:27', 'Initial delivery', 1, '2026-03-07 05:46:27', NULL, NULL, NULL, NULL),
(5, 6, 3, NULL, NULL, 1, '2026-03-07 18:02:55', 'Initial delivery', 1, '2026-03-07 18:02:55', NULL, NULL, NULL, NULL),
(6, 7, 4, NULL, NULL, 1, '2026-03-07 18:29:39', 'Initial delivery', 1, '2026-03-07 18:29:39', NULL, NULL, NULL, NULL),
(7, 8, 4, NULL, 'thrissur', 1, '2026-03-07 18:57:32', 'Initial delivery', 1, '2026-03-07 18:57:32', NULL, NULL, NULL, NULL),
(8, 9, 4, NULL, 'thrissur', 1, '2026-03-07 19:12:13', 'Initial delivery', 1, '2026-03-07 19:12:13', NULL, NULL, NULL, NULL),
(9, 10, 4, NULL, '23', 1, '2026-03-07 19:18:48', 'Initial delivery', 1, '2026-03-07 19:18:48', NULL, NULL, NULL, NULL),
(10, 11, 4, NULL, 'sds', 1, '2026-03-07 19:37:51', 'Initial delivery', 1, '2026-03-07 19:37:51', NULL, NULL, NULL, NULL),
(11, 12, 4, NULL, 'sdsd', 1, '2026-03-07 19:53:49', 'Initial delivery', 1, '2026-03-07 19:53:49', NULL, NULL, NULL, NULL),
(12, 13, 6, NULL, 'dfdfe', 1, '2026-03-07 20:13:20', 'Initial delivery', 1, '2026-03-07 20:13:20', NULL, NULL, NULL, NULL),
(13, 14, 2, NULL, 'ssd', 1, '2026-03-07 20:26:39', 'Initial delivery', 1, '2026-03-07 20:26:39', NULL, NULL, NULL, NULL),
(14, 15, 5, NULL, 'sdsd', 1, '2026-03-08 16:10:56', 'Initial delivery', 1, '2026-03-08 16:10:56', NULL, NULL, NULL, NULL),
(15, 15, 5, NULL, 'delivered to thrissur', 1, '2026-03-08 16:12:06', 'delivered to thrissur', 1, '2026-03-08 16:12:06', NULL, NULL, NULL, NULL),
(16, 16, 6, NULL, 'sdsd', 1, '2026-03-08 16:16:04', 'Initial delivery', 1, '2026-03-08 16:16:04', NULL, NULL, NULL, NULL),
(17, 17, 6, NULL, 'sd', 1, '2026-03-08 17:00:07', 'Initial delivery', 1, '2026-03-08 17:00:07', NULL, NULL, NULL, NULL),
(18, 5, 1, NULL, NULL, 0, '2026-03-09 09:21:53', 'Initial delivery', 1, '2026-03-09 09:21:53', NULL, NULL, NULL, NULL),
(19, 18, 5, NULL, 'thrissur', 1, '2026-03-09 19:23:35', 'Initial delivery', 1, '2026-03-09 19:23:35', NULL, NULL, NULL, NULL),
(20, 19, 2, NULL, 'dfdf', 1, '2026-03-09 19:37:12', 'Initial delivery', 1, '2026-03-09 19:37:12', NULL, NULL, NULL, NULL),
(21, 21, 2, NULL, 'rt', 1, '2026-03-11 05:31:01', 'Initial delivery', 1, '2026-03-11 05:31:01', NULL, NULL, NULL, NULL),
(22, 22, 1, NULL, 'sd', 1, '2026-03-11 07:19:52', 'Initial delivery', 1, '2026-03-11 07:19:52', NULL, NULL, NULL, NULL),
(23, 25, 3, NULL, 'df', 1, '2026-03-11 08:48:06', 'Initial delivery', 1, '2026-03-11 08:48:06', NULL, NULL, NULL, NULL),
(24, 24, 2, NULL, 'dfd', 1, '2026-03-12 05:34:22', 'Initial delivery', 1, '2026-03-12 05:34:22', NULL, NULL, NULL, NULL),
(25, 26, 3, NULL, 'dfd', 1, '2026-03-12 05:52:00', 'Initial delivery', 1, '2026-03-12 05:52:00', NULL, NULL, NULL, NULL),
(26, 27, 2, NULL, 'sdf', 1, '2026-03-13 08:50:28', 'Initial delivery', 1, '2026-03-13 08:50:28', NULL, NULL, NULL, NULL),
(27, 28, 3, NULL, 'gfh', 1, '2026-03-13 09:32:09', 'Initial delivery', 1, '2026-03-13 09:32:09', NULL, NULL, NULL, NULL),
(28, 27, 2, NULL, '1', 1, '2026-03-16 05:11:48', NULL, 1, '2026-03-16 05:11:48', NULL, NULL, NULL, NULL),
(29, 27, 2, NULL, '1', 1, '2026-03-16 05:12:22', NULL, 1, '2026-03-16 05:12:22', NULL, NULL, NULL, NULL),
(30, 27, 2, NULL, '1', 1, '2026-03-16 05:12:22', NULL, 1, '2026-03-16 05:12:22', NULL, NULL, NULL, NULL),
(31, 27, 2, NULL, '1', 1, '2026-03-16 05:12:32', NULL, 1, '2026-03-16 05:12:32', NULL, NULL, NULL, NULL),
(32, 27, 2, NULL, '1', 1, '2026-03-16 05:12:32', NULL, 1, '2026-03-16 05:12:32', NULL, NULL, NULL, NULL),
(33, 27, 2, NULL, '1', 0, '2026-03-16 05:12:32', 'sdsd', 1, '2026-03-16 05:12:32', NULL, NULL, NULL, NULL),
(34, 29, 2, NULL, 'dfdf', 1, '2026-03-16 09:30:28', 'Initial delivery', 1, '2026-03-16 09:30:28', NULL, NULL, NULL, NULL),
(35, 27, 2, NULL, '1', 1, '2026-03-16 09:30:53', NULL, 1, '2026-03-16 09:30:53', NULL, NULL, NULL, NULL),
(36, 27, 2, NULL, '1', 1, '2026-03-16 09:30:53', NULL, 1, '2026-03-16 09:30:53', NULL, NULL, NULL, NULL),
(37, 27, 2, NULL, '1', 0, '2026-03-16 09:30:53', 'sdsd', 1, '2026-03-16 09:30:53', NULL, NULL, NULL, NULL),
(38, 30, 1, NULL, 'thrissur', 1, '2026-03-16 09:32:14', 'Initial delivery', 1, '2026-03-16 09:32:14', NULL, NULL, NULL, NULL),
(39, 30, 1, NULL, 'thrissur', 1, '2026-03-16 09:32:53', NULL, 1, '2026-03-16 09:32:53', NULL, NULL, NULL, NULL),
(40, 27, 2, NULL, '1', 1, '2026-03-16 09:32:53', NULL, 1, '2026-03-16 09:32:53', NULL, NULL, NULL, NULL),
(41, 27, 2, NULL, '1', 1, '2026-03-16 09:32:53', NULL, 1, '2026-03-16 09:32:53', NULL, NULL, NULL, NULL),
(42, 27, 2, NULL, '1', 0, '2026-03-16 09:32:53', 'sdsd', 1, '2026-03-16 09:32:53', NULL, NULL, NULL, NULL),
(43, 30, 1, NULL, 'thrissur', 1, '2026-03-16 09:33:04', NULL, 1, '2026-03-16 09:33:04', NULL, NULL, NULL, NULL),
(44, 30, 1, NULL, 'thrissur', 1, '2026-03-16 09:33:04', NULL, 1, '2026-03-16 09:33:04', NULL, NULL, NULL, NULL),
(45, 27, 2, NULL, '1', 1, '2026-03-16 09:33:04', NULL, 1, '2026-03-16 09:33:04', NULL, NULL, NULL, NULL),
(46, 27, 2, NULL, '1', 1, '2026-03-16 09:33:04', NULL, 1, '2026-03-16 09:33:04', NULL, NULL, NULL, NULL),
(47, 27, 2, NULL, '1', 0, '2026-03-16 09:33:04', 'sdsd', 1, '2026-03-16 09:33:04', NULL, NULL, NULL, NULL),
(48, 30, 1, NULL, 'thrissur', 1, '2026-03-16 09:33:17', NULL, 1, '2026-03-16 09:33:17', NULL, NULL, NULL, NULL),
(49, 30, 1, NULL, 'thrissur', 1, '2026-03-16 09:33:17', NULL, 1, '2026-03-16 09:33:17', NULL, NULL, NULL, NULL),
(50, 30, 1, NULL, 'thrissur', 1, '2026-03-16 09:33:17', NULL, 1, '2026-03-16 09:33:17', NULL, NULL, NULL, NULL),
(51, 27, 2, NULL, '1', 1, '2026-03-16 09:33:17', NULL, 1, '2026-03-16 09:33:17', NULL, NULL, NULL, NULL),
(52, 27, 2, NULL, '1', 1, '2026-03-16 09:33:17', NULL, 1, '2026-03-16 09:33:17', NULL, NULL, NULL, NULL),
(53, 27, 2, NULL, '1', 0, '2026-03-16 09:33:17', 'sdsd', 1, '2026-03-16 09:33:17', NULL, NULL, NULL, NULL),
(54, 30, 1, NULL, 'thrissur', 1, '2026-03-16 10:08:03', NULL, 1, '2026-03-16 10:08:03', NULL, NULL, NULL, NULL),
(55, 30, 1, NULL, 'thrissur', 1, '2026-03-16 10:08:03', NULL, 1, '2026-03-16 10:08:03', NULL, NULL, NULL, NULL),
(56, 30, 1, NULL, 'thrissur', 1, '2026-03-16 10:08:03', NULL, 1, '2026-03-16 10:08:03', NULL, NULL, NULL, NULL),
(57, 27, 2, NULL, '1', 1, '2026-03-16 10:08:03', NULL, 1, '2026-03-16 10:08:03', NULL, NULL, NULL, NULL),
(58, 27, 2, NULL, '1', 1, '2026-03-16 10:08:03', NULL, 1, '2026-03-16 10:08:03', NULL, NULL, NULL, NULL),
(59, 27, 2, NULL, '1', 0, '2026-03-16 10:08:03', 'sdsd', 1, '2026-03-16 10:08:03', NULL, NULL, NULL, NULL),
(60, 31, 3, NULL, 'dfdf', 1, '2026-03-16 11:43:05', 'Initial delivery', 1, '2026-03-16 11:43:05', NULL, NULL, NULL, NULL),
(61, 32, 5, NULL, 'eranakulam', 1, '2026-03-16 12:04:11', 'Initial delivery', 1, '2026-03-16 12:04:11', NULL, NULL, NULL, NULL),
(62, 32, 5, NULL, 'eranakulam', 1, '2026-03-16 12:13:05', NULL, 1, '2026-03-16 12:13:05', NULL, NULL, NULL, NULL),
(63, 30, 1, NULL, 'thrissur', 1, '2026-03-16 12:13:05', NULL, 1, '2026-03-16 12:13:05', NULL, NULL, NULL, NULL),
(64, 30, 1, NULL, 'thrissur', 1, '2026-03-16 12:13:05', NULL, 1, '2026-03-16 12:13:05', NULL, NULL, NULL, NULL),
(65, 30, 1, NULL, 'thrissur', 1, '2026-03-16 12:13:05', NULL, 1, '2026-03-16 12:13:05', NULL, NULL, NULL, NULL),
(66, 27, 2, NULL, '1', 1, '2026-03-16 12:13:05', NULL, 1, '2026-03-16 12:13:05', NULL, NULL, NULL, NULL),
(67, 27, 2, NULL, '1', 1, '2026-03-16 12:13:05', NULL, 1, '2026-03-16 12:13:05', NULL, NULL, NULL, NULL),
(68, 27, 2, NULL, '1', 0, '2026-03-16 12:13:05', 'sdsd', 1, '2026-03-16 12:13:05', NULL, NULL, NULL, NULL),
(69, 32, 5, NULL, 'eranakulam', 1, '2026-03-16 12:13:32', NULL, 1, '2026-03-16 12:13:32', NULL, NULL, NULL, NULL),
(70, 32, 5, NULL, 'eranakulam', 1, '2026-03-16 12:13:32', NULL, 1, '2026-03-16 12:13:32', NULL, NULL, NULL, NULL),
(71, 30, 1, NULL, 'thrissur', 1, '2026-03-16 12:13:32', NULL, 1, '2026-03-16 12:13:32', NULL, NULL, NULL, NULL),
(72, 30, 1, NULL, 'thrissur', 1, '2026-03-16 12:13:32', NULL, 1, '2026-03-16 12:13:32', NULL, NULL, NULL, NULL),
(73, 30, 1, NULL, 'thrissur', 1, '2026-03-16 12:13:32', NULL, 1, '2026-03-16 12:13:32', NULL, NULL, NULL, NULL),
(74, 27, 2, NULL, '1', 1, '2026-03-16 12:13:32', NULL, 1, '2026-03-16 12:13:32', NULL, NULL, NULL, NULL),
(75, 27, 2, NULL, '1', 1, '2026-03-16 12:13:32', NULL, 1, '2026-03-16 12:13:32', NULL, NULL, NULL, NULL),
(76, 27, 2, NULL, '1', 0, '2026-03-16 12:13:32', 'sdsd', 1, '2026-03-16 12:13:32', NULL, NULL, NULL, NULL),
(77, 32, 5, NULL, 'eranakulam', 1, '2026-03-16 12:13:50', NULL, 1, '2026-03-16 12:13:50', NULL, NULL, NULL, NULL),
(78, 32, 5, NULL, 'eranakulam', 1, '2026-03-16 12:13:50', NULL, 1, '2026-03-16 12:13:50', NULL, NULL, NULL, NULL),
(79, 32, 5, NULL, 'eranakulam', 0, '2026-03-16 12:13:50', 'no', 1, '2026-03-16 12:13:50', NULL, NULL, NULL, NULL),
(80, 30, 1, NULL, 'thrissur', 1, '2026-03-16 12:13:50', NULL, 1, '2026-03-16 12:13:50', NULL, NULL, NULL, NULL),
(81, 30, 1, NULL, 'thrissur', 1, '2026-03-16 12:13:50', NULL, 1, '2026-03-16 12:13:50', NULL, NULL, NULL, NULL),
(82, 30, 1, NULL, 'thrissur', 1, '2026-03-16 12:13:50', NULL, 1, '2026-03-16 12:13:50', NULL, NULL, NULL, NULL),
(83, 27, 2, NULL, '1', 1, '2026-03-16 12:13:50', NULL, 1, '2026-03-16 12:13:50', NULL, NULL, NULL, NULL),
(84, 27, 2, NULL, '1', 1, '2026-03-16 12:13:50', NULL, 1, '2026-03-16 12:13:50', NULL, NULL, NULL, NULL),
(85, 27, 2, NULL, '1', 0, '2026-03-16 12:13:50', 'sdsd', 1, '2026-03-16 12:13:50', NULL, NULL, NULL, NULL),
(86, 32, 5, NULL, 'eranakulam', 1, '2026-03-16 12:14:22', NULL, 1, '2026-03-16 12:14:22', NULL, NULL, NULL, NULL),
(87, 32, 5, NULL, 'eranakulam', 1, '2026-03-16 12:14:22', NULL, 1, '2026-03-16 12:14:22', NULL, NULL, NULL, NULL),
(88, 32, 5, NULL, 'eranakulam', 0, '2026-03-16 12:14:22', 'no', 1, '2026-03-16 12:14:22', NULL, NULL, NULL, NULL),
(89, 30, 1, NULL, 'thrissur', 1, '2026-03-16 12:14:22', NULL, 1, '2026-03-16 12:14:22', NULL, NULL, NULL, NULL),
(90, 30, 1, NULL, 'thrissur', 1, '2026-03-16 12:14:22', NULL, 1, '2026-03-16 12:14:22', NULL, NULL, NULL, NULL),
(91, 30, 1, NULL, 'thrissur', 1, '2026-03-16 12:14:22', NULL, 1, '2026-03-16 12:14:22', NULL, NULL, NULL, NULL),
(92, 27, 2, NULL, '1', 1, '2026-03-16 12:14:22', NULL, 1, '2026-03-16 12:14:22', NULL, NULL, NULL, NULL),
(93, 27, 2, NULL, '1', 1, '2026-03-16 12:14:22', NULL, 1, '2026-03-16 12:14:22', NULL, NULL, NULL, NULL),
(94, 27, 2, NULL, '1', 0, '2026-03-16 12:14:22', 'sdsd', 1, '2026-03-16 12:14:22', NULL, NULL, NULL, NULL),
(95, 32, 5, NULL, 'eranakulam', 1, '2026-03-16 12:14:33', NULL, 1, '2026-03-16 12:14:33', NULL, NULL, NULL, NULL),
(96, 32, 5, NULL, 'eranakulam', 1, '2026-03-16 12:14:33', NULL, 1, '2026-03-16 12:14:33', NULL, NULL, NULL, NULL),
(97, 32, 5, NULL, 'eranakulam', 0, '2026-03-16 12:14:33', 'no', 1, '2026-03-16 12:14:33', NULL, NULL, NULL, NULL),
(98, 30, 1, NULL, 'thrissur', 1, '2026-03-16 12:14:33', NULL, 1, '2026-03-16 12:14:33', NULL, NULL, NULL, NULL),
(99, 30, 1, NULL, 'thrissur', 1, '2026-03-16 12:14:33', NULL, 1, '2026-03-16 12:14:33', NULL, NULL, NULL, NULL),
(100, 30, 1, NULL, 'thrissur', 1, '2026-03-16 12:14:33', NULL, 1, '2026-03-16 12:14:33', NULL, NULL, NULL, NULL),
(101, 27, 2, NULL, '1', 1, '2026-03-16 12:14:33', NULL, 1, '2026-03-16 12:14:33', NULL, NULL, NULL, NULL),
(102, 27, 2, NULL, '1', 1, '2026-03-16 12:14:33', NULL, 1, '2026-03-16 12:14:33', NULL, NULL, NULL, NULL),
(103, 27, 2, NULL, '1', 0, '2026-03-16 12:14:33', 'sdsd', 1, '2026-03-16 12:14:33', NULL, NULL, NULL, NULL),
(104, 32, 5, NULL, 'eranakulam', 1, '2026-03-16 12:18:49', NULL, 1, '2026-03-16 12:18:49', NULL, NULL, NULL, NULL),
(105, 32, 5, NULL, 'eranakulam', 1, '2026-03-16 12:18:49', NULL, 1, '2026-03-16 12:18:49', NULL, NULL, NULL, NULL),
(106, 32, 5, NULL, 'eranakulam', 0, '2026-03-16 12:18:49', 'no', 1, '2026-03-16 12:18:49', NULL, NULL, NULL, NULL),
(107, 30, 1, NULL, 'thrissur', 1, '2026-03-16 12:18:49', NULL, 1, '2026-03-16 12:18:49', NULL, NULL, NULL, NULL),
(108, 30, 1, NULL, 'thrissur', 1, '2026-03-16 12:18:49', NULL, 1, '2026-03-16 12:18:49', NULL, NULL, NULL, NULL),
(109, 30, 1, NULL, 'thrissur', 1, '2026-03-16 12:18:49', NULL, 1, '2026-03-16 12:18:49', NULL, NULL, NULL, NULL),
(110, 27, 2, NULL, '1', 1, '2026-03-16 12:18:49', NULL, 1, '2026-03-16 12:18:49', NULL, NULL, NULL, NULL),
(111, 27, 2, NULL, '1', 1, '2026-03-16 12:18:49', NULL, 1, '2026-03-16 12:18:49', NULL, NULL, NULL, NULL),
(112, 27, 2, NULL, '1', 0, '2026-03-16 12:18:49', 'sdsd', 1, '2026-03-16 12:18:49', NULL, NULL, NULL, NULL),
(113, 32, 5, NULL, 'eranakulam', 1, '2026-03-16 12:20:34', NULL, 1, '2026-03-16 12:20:34', NULL, NULL, NULL, NULL),
(114, 32, 5, NULL, 'eranakulam', 1, '2026-03-16 12:20:34', NULL, 1, '2026-03-16 12:20:34', NULL, NULL, NULL, NULL),
(115, 32, 5, NULL, 'eranakulam', 0, '2026-03-16 12:20:34', 'no', 1, '2026-03-16 12:20:34', NULL, NULL, NULL, NULL),
(116, 30, 1, NULL, 'thrissur', 1, '2026-03-16 12:20:34', NULL, 1, '2026-03-16 12:20:34', NULL, NULL, NULL, NULL),
(117, 30, 1, NULL, 'thrissur', 1, '2026-03-16 12:20:34', NULL, 1, '2026-03-16 12:20:34', NULL, NULL, NULL, NULL),
(118, 30, 1, NULL, 'thrissur', 1, '2026-03-16 12:20:34', NULL, 1, '2026-03-16 12:20:34', NULL, NULL, NULL, NULL),
(119, 27, 2, NULL, '1', 1, '2026-03-16 12:20:34', NULL, 1, '2026-03-16 12:20:34', NULL, NULL, NULL, NULL),
(120, 27, 2, NULL, '1', 1, '2026-03-16 12:20:34', NULL, 1, '2026-03-16 12:20:34', NULL, NULL, NULL, NULL),
(121, 27, 2, NULL, '1', 0, '2026-03-16 12:20:34', 'sdsd', 1, '2026-03-16 12:20:34', NULL, NULL, NULL, NULL),
(122, 32, 5, NULL, 'eranakulam', 1, '2026-03-16 12:26:13', NULL, 1, '2026-03-16 12:26:13', NULL, NULL, NULL, NULL),
(123, 32, 5, NULL, 'eranakulam', 1, '2026-03-16 12:26:13', NULL, 1, '2026-03-16 12:26:13', NULL, NULL, NULL, NULL),
(124, 32, 5, NULL, 'eranakulam', 0, '2026-03-16 12:26:13', 'no', 1, '2026-03-16 12:26:13', NULL, NULL, NULL, NULL),
(125, 30, 1, NULL, 'thrissur', 1, '2026-03-16 12:26:13', NULL, 1, '2026-03-16 12:26:13', NULL, NULL, NULL, NULL),
(126, 30, 1, NULL, 'thrissur', 1, '2026-03-16 12:26:13', NULL, 1, '2026-03-16 12:26:13', NULL, NULL, NULL, NULL),
(127, 30, 1, NULL, 'thrissur', 1, '2026-03-16 12:26:13', NULL, 1, '2026-03-16 12:26:13', NULL, NULL, NULL, NULL),
(128, 27, 2, NULL, '1', 1, '2026-03-16 12:26:13', NULL, 1, '2026-03-16 12:26:13', NULL, NULL, NULL, NULL),
(129, 27, 2, NULL, '1', 1, '2026-03-16 12:26:13', NULL, 1, '2026-03-16 12:26:13', NULL, NULL, NULL, NULL),
(130, 27, 2, NULL, '1', 0, '2026-03-16 12:26:13', 'sdsd', 1, '2026-03-16 12:26:13', NULL, NULL, NULL, NULL),
(131, 32, 5, NULL, 'eranakulam', 1, '2026-03-16 12:28:42', NULL, 1, '2026-03-16 12:28:42', NULL, NULL, NULL, NULL),
(132, 32, 5, NULL, 'eranakulam', 1, '2026-03-16 12:28:42', NULL, 1, '2026-03-16 12:28:42', NULL, NULL, NULL, NULL),
(133, 32, 5, NULL, 'eranakulam', 0, '2026-03-16 12:28:42', 'no', 1, '2026-03-16 12:28:42', NULL, NULL, NULL, NULL),
(134, 30, 1, NULL, 'thrissur', 1, '2026-03-16 12:28:42', NULL, 1, '2026-03-16 12:28:42', NULL, NULL, NULL, NULL),
(135, 30, 1, NULL, 'thrissur', 1, '2026-03-16 12:28:42', NULL, 1, '2026-03-16 12:28:42', NULL, NULL, NULL, NULL),
(136, 30, 1, NULL, 'thrissur', 1, '2026-03-16 12:28:42', NULL, 1, '2026-03-16 12:28:42', NULL, NULL, NULL, NULL),
(137, 27, 2, NULL, '1', 1, '2026-03-16 12:28:42', NULL, 1, '2026-03-16 12:28:42', NULL, NULL, NULL, NULL),
(138, 27, 2, NULL, '1', 1, '2026-03-16 12:28:42', NULL, 1, '2026-03-16 12:28:42', NULL, NULL, NULL, NULL),
(139, 27, 2, NULL, '1', 0, '2026-03-16 12:28:42', 'sdsd', 1, '2026-03-16 12:28:42', NULL, NULL, NULL, NULL),
(140, 32, 5, NULL, 'eranakulam', 1, '2026-03-16 12:31:13', NULL, 1, '2026-03-16 12:31:13', NULL, NULL, NULL, NULL),
(141, 32, 5, NULL, 'eranakulam', 1, '2026-03-16 12:31:13', NULL, 1, '2026-03-16 12:31:13', NULL, NULL, NULL, NULL),
(142, 32, 5, NULL, 'eranakulam', 0, '2026-03-16 12:31:13', 'no', 1, '2026-03-16 12:31:13', NULL, NULL, NULL, NULL),
(143, 30, 1, NULL, 'thrissur', 1, '2026-03-16 12:31:13', NULL, 1, '2026-03-16 12:31:13', NULL, NULL, NULL, NULL),
(144, 30, 1, NULL, 'thrissur', 1, '2026-03-16 12:31:13', NULL, 1, '2026-03-16 12:31:13', NULL, NULL, NULL, NULL),
(145, 30, 1, NULL, 'thrissur', 1, '2026-03-16 12:31:13', NULL, 1, '2026-03-16 12:31:13', NULL, NULL, NULL, NULL),
(146, 27, 2, NULL, '1', 1, '2026-03-16 12:31:13', NULL, 1, '2026-03-16 12:31:13', NULL, NULL, NULL, NULL),
(147, 27, 2, NULL, '1', 1, '2026-03-16 12:31:13', NULL, 1, '2026-03-16 12:31:13', NULL, NULL, NULL, NULL),
(148, 27, 2, NULL, '1', 0, '2026-03-16 12:31:13', 'sdsd', 1, '2026-03-16 12:31:13', NULL, NULL, NULL, NULL),
(149, 33, 7, NULL, 'kochi', 1, '2026-03-16 15:25:31', 'Initial delivery', 1, '2026-03-16 15:25:31', NULL, NULL, NULL, NULL),
(150, 33, 7, NULL, 'kochi', 1, '2026-03-16 15:25:56', NULL, 1, '2026-03-16 15:25:56', NULL, NULL, NULL, NULL),
(151, 32, 5, NULL, 'eranakulam', 1, '2026-03-16 15:25:56', NULL, 1, '2026-03-16 15:25:56', NULL, NULL, NULL, NULL),
(152, 32, 5, NULL, 'eranakulam', 1, '2026-03-16 15:25:56', NULL, 1, '2026-03-16 15:25:56', NULL, NULL, NULL, NULL),
(153, 32, 5, NULL, 'eranakulam', 0, '2026-03-16 15:25:56', 'no', 1, '2026-03-16 15:25:56', NULL, NULL, NULL, NULL),
(154, 30, 1, NULL, 'thrissur', 1, '2026-03-16 15:25:56', NULL, 1, '2026-03-16 15:25:56', NULL, NULL, NULL, NULL),
(155, 30, 1, NULL, 'thrissur', 1, '2026-03-16 15:25:56', NULL, 1, '2026-03-16 15:25:56', NULL, NULL, NULL, NULL),
(156, 30, 1, NULL, 'thrissur', 1, '2026-03-16 15:25:56', NULL, 1, '2026-03-16 15:25:56', NULL, NULL, NULL, NULL),
(157, 27, 2, NULL, '1', 1, '2026-03-16 15:25:56', NULL, 1, '2026-03-16 15:25:56', NULL, NULL, NULL, NULL),
(158, 27, 2, NULL, '1', 1, '2026-03-16 15:25:56', NULL, 1, '2026-03-16 15:25:56', NULL, NULL, NULL, NULL),
(159, 27, 2, NULL, '1', 0, '2026-03-16 15:25:56', 'sdsd', 1, '2026-03-16 15:25:56', NULL, NULL, NULL, NULL),
(160, 33, 7, NULL, 'kochi', 1, '2026-03-16 15:26:05', NULL, 1, '2026-03-16 15:26:05', NULL, NULL, NULL, NULL),
(161, 33, 7, NULL, 'kochi', 1, '2026-03-16 15:26:05', NULL, 1, '2026-03-16 15:26:05', NULL, NULL, NULL, NULL),
(162, 32, 5, NULL, 'eranakulam', 1, '2026-03-16 15:26:05', NULL, 1, '2026-03-16 15:26:05', NULL, NULL, NULL, NULL),
(163, 32, 5, NULL, 'eranakulam', 1, '2026-03-16 15:26:05', NULL, 1, '2026-03-16 15:26:05', NULL, NULL, NULL, NULL),
(164, 32, 5, NULL, 'eranakulam', 0, '2026-03-16 15:26:05', 'no', 1, '2026-03-16 15:26:05', NULL, NULL, NULL, NULL),
(165, 30, 1, NULL, 'thrissur', 1, '2026-03-16 15:26:05', NULL, 1, '2026-03-16 15:26:05', NULL, NULL, NULL, NULL),
(166, 30, 1, NULL, 'thrissur', 1, '2026-03-16 15:26:05', NULL, 1, '2026-03-16 15:26:05', NULL, NULL, NULL, NULL),
(167, 30, 1, NULL, 'thrissur', 1, '2026-03-16 15:26:05', NULL, 1, '2026-03-16 15:26:05', NULL, NULL, NULL, NULL),
(168, 27, 2, NULL, '1', 1, '2026-03-16 15:26:05', NULL, 1, '2026-03-16 15:26:05', NULL, NULL, NULL, NULL),
(169, 27, 2, NULL, '1', 1, '2026-03-16 15:26:05', NULL, 1, '2026-03-16 15:26:05', NULL, NULL, NULL, NULL),
(170, 27, 2, NULL, '1', 0, '2026-03-16 15:26:05', 'sdsd', 1, '2026-03-16 15:26:05', NULL, NULL, NULL, NULL),
(171, 33, 7, NULL, 'kochi', 1, '2026-03-16 15:26:23', NULL, 1, '2026-03-16 15:26:23', NULL, NULL, NULL, NULL),
(172, 33, 7, NULL, 'kochi', 1, '2026-03-16 15:26:23', NULL, 1, '2026-03-16 15:26:23', NULL, NULL, NULL, NULL),
(173, 33, 7, NULL, 'kochi', 1, '2026-03-16 15:26:23', NULL, 1, '2026-03-16 15:26:23', NULL, NULL, NULL, NULL),
(174, 32, 5, NULL, 'eranakulam', 1, '2026-03-16 15:26:23', NULL, 1, '2026-03-16 15:26:23', NULL, NULL, NULL, NULL),
(175, 32, 5, NULL, 'eranakulam', 1, '2026-03-16 15:26:23', NULL, 1, '2026-03-16 15:26:23', NULL, NULL, NULL, NULL),
(176, 32, 5, NULL, 'eranakulam', 0, '2026-03-16 15:26:23', 'no', 1, '2026-03-16 15:26:23', NULL, NULL, NULL, NULL),
(177, 30, 1, NULL, 'thrissur', 1, '2026-03-16 15:26:23', NULL, 1, '2026-03-16 15:26:23', NULL, NULL, NULL, NULL),
(178, 30, 1, NULL, 'thrissur', 1, '2026-03-16 15:26:23', NULL, 1, '2026-03-16 15:26:23', NULL, NULL, NULL, NULL),
(179, 30, 1, NULL, 'thrissur', 1, '2026-03-16 15:26:23', NULL, 1, '2026-03-16 15:26:23', NULL, NULL, NULL, NULL),
(180, 27, 2, NULL, '1', 1, '2026-03-16 15:26:23', NULL, 1, '2026-03-16 15:26:23', NULL, NULL, NULL, NULL),
(181, 27, 2, NULL, '1', 1, '2026-03-16 15:26:23', NULL, 1, '2026-03-16 15:26:23', NULL, NULL, NULL, NULL),
(182, 27, 2, NULL, '1', 0, '2026-03-16 15:26:23', 'sdsd', 1, '2026-03-16 15:26:23', NULL, NULL, NULL, NULL),
(183, 33, 7, NULL, 'kochi', 1, '2026-03-16 15:29:45', NULL, 1, '2026-03-16 15:29:45', NULL, NULL, NULL, NULL),
(184, 33, 7, NULL, 'kochi', 1, '2026-03-16 15:29:45', NULL, 1, '2026-03-16 15:29:45', NULL, NULL, NULL, NULL),
(185, 33, 7, NULL, 'kochi', 1, '2026-03-16 15:29:45', NULL, 1, '2026-03-16 15:29:45', NULL, NULL, NULL, NULL),
(186, 32, 5, NULL, 'eranakulam', 1, '2026-03-16 15:29:45', NULL, 1, '2026-03-16 15:29:45', NULL, NULL, NULL, NULL),
(187, 32, 5, NULL, 'eranakulam', 0, '2026-03-16 15:29:45', 'no', 1, '2026-03-16 15:29:45', NULL, NULL, NULL, NULL),
(188, 30, 1, NULL, 'thrissur', 1, '2026-03-16 15:29:45', NULL, 1, '2026-03-16 15:29:45', NULL, NULL, NULL, NULL),
(189, 30, 1, NULL, 'thrissur', 1, '2026-03-16 15:29:45', NULL, 1, '2026-03-16 15:29:45', NULL, NULL, NULL, NULL),
(190, 30, 1, NULL, 'thrissur', 1, '2026-03-16 15:29:45', NULL, 1, '2026-03-16 15:29:45', NULL, NULL, NULL, NULL),
(191, 27, 2, NULL, '1', 1, '2026-03-16 15:29:45', NULL, 1, '2026-03-16 15:29:45', NULL, NULL, NULL, NULL),
(192, 27, 2, NULL, '1', 1, '2026-03-16 15:29:45', NULL, 1, '2026-03-16 15:29:45', NULL, NULL, NULL, NULL),
(193, 27, 2, NULL, '1', 0, '2026-03-16 15:29:45', 'sdsd', 1, '2026-03-16 15:29:45', NULL, NULL, NULL, NULL),
(194, 34, 7, NULL, 'palakkad', 1, '2026-03-16 17:44:20', 'Initial delivery', 1, '2026-03-16 17:44:20', NULL, NULL, NULL, NULL),
(195, 34, 7, NULL, 'palakkad', 1, '2026-03-16 17:46:18', NULL, 1, '2026-03-16 17:46:18', NULL, NULL, NULL, NULL),
(196, 34, 7, NULL, 'palakkad', 1, '2026-03-16 17:46:24', NULL, 1, '2026-03-16 17:46:24', NULL, NULL, NULL, NULL),
(197, 30, 1, NULL, 'thrissur', 1, '2026-03-16 17:46:24', NULL, 1, '2026-03-16 17:46:24', NULL, NULL, NULL, NULL),
(198, 30, 1, NULL, 'thrissur', 1, '2026-03-16 17:46:24', NULL, 1, '2026-03-16 17:46:24', NULL, NULL, NULL, NULL),
(199, 30, 1, NULL, 'thrissur', 1, '2026-03-16 17:46:24', NULL, 1, '2026-03-16 17:46:24', NULL, NULL, NULL, NULL),
(200, 27, 2, NULL, '1', 1, '2026-03-16 17:46:24', NULL, 1, '2026-03-16 17:46:24', NULL, NULL, NULL, NULL),
(201, 27, 2, NULL, '1', 1, '2026-03-16 17:46:24', NULL, 1, '2026-03-16 17:46:24', NULL, NULL, NULL, NULL),
(202, 27, 2, NULL, '1', 0, '2026-03-16 17:46:24', 'sdsd', 1, '2026-03-16 17:46:24', NULL, NULL, NULL, NULL),
(203, 34, 7, NULL, 'palakkad', 1, '2026-03-17 04:21:20', NULL, 1, '2026-03-17 04:21:20', NULL, NULL, NULL, NULL),
(204, 34, 7, NULL, 'palakkad', 1, '2026-03-17 04:21:39', NULL, 1, '2026-03-17 04:21:39', NULL, NULL, NULL, NULL),
(205, 34, 7, NULL, 'palakkad', 1, '2026-03-17 04:21:39', NULL, 1, '2026-03-17 04:21:39', NULL, NULL, NULL, NULL),
(206, 34, 7, NULL, 'palakkad', 1, '2026-03-17 04:21:46', NULL, 1, '2026-03-17 04:21:46', NULL, NULL, NULL, NULL),
(207, 34, 7, NULL, 'palakkad', 1, '2026-03-17 04:21:46', NULL, 1, '2026-03-17 04:21:46', NULL, NULL, NULL, NULL),
(208, 34, 7, NULL, 'palakkad', 1, '2026-03-17 04:26:08', NULL, 1, '2026-03-17 04:26:08', NULL, NULL, NULL, NULL),
(209, 34, 7, NULL, 'palakkad', 1, '2026-03-17 04:26:08', NULL, 1, '2026-03-17 04:26:08', NULL, NULL, NULL, NULL),
(210, 35, 5, NULL, 'sdsd', 1, '2026-03-17 04:38:58', 'Initial delivery', 1, '2026-03-17 04:38:58', NULL, NULL, NULL, NULL),
(211, 38, 8, NULL, 'thrissur', 1, '2026-03-17 19:38:54', 'Initial delivery', 1, '2026-03-17 19:38:54', NULL, NULL, NULL, NULL),
(212, 39, 9, NULL, 'dfd', 1, '2026-03-19 07:59:20', 'Initial delivery', 1, '2026-03-19 07:59:20', NULL, NULL, NULL, NULL),
(213, 41, 10, NULL, 'dfdf', 1, '2026-03-19 08:52:44', 'Initial delivery', 1, '2026-03-19 08:52:44', NULL, NULL, NULL, NULL),
(214, 44, 8, NULL, 'fdf', 1, '2026-03-22 06:21:23', 'Initial delivery', 1, '2026-03-22 06:21:23', NULL, NULL, NULL, NULL),
(215, 45, 7, NULL, 'dfdf', 1, '2026-03-22 06:29:58', 'Initial delivery', 1, '2026-03-22 06:29:58', NULL, NULL, NULL, NULL),
(216, 46, 5, NULL, 'sdsdf', 1, '2026-03-22 06:41:39', 'Initial delivery', 1, '2026-03-22 06:41:39', NULL, NULL, NULL, NULL),
(217, 47, 8, NULL, 'df', 1, '2026-03-22 06:47:23', 'Initial delivery', 1, '2026-03-22 06:47:23', NULL, NULL, NULL, NULL),
(218, 48, 9, NULL, 'thrissur', 1, '2026-03-23 18:04:45', 'Initial delivery', 1, '2026-03-23 18:04:45', NULL, NULL, NULL, NULL),
(219, 49, 9, NULL, 'thrissur', 1, '2026-03-23 18:13:28', 'Initial delivery', 1, '2026-03-23 18:13:28', NULL, NULL, NULL, NULL),
(220, 50, 9, NULL, 'thrissur', 1, '2026-03-23 18:21:00', 'Initial delivery', 1, '2026-03-23 18:21:00', NULL, NULL, NULL, NULL),
(221, 51, 5, NULL, 'thrissur', 1, '2026-03-24 04:35:58', 'Initial delivery', 1, '2026-03-24 04:35:58', NULL, NULL, NULL, NULL),
(222, 52, 5, NULL, 'dfdf', 1, '2026-03-24 12:38:16', 'Initial delivery', 1, '2026-03-24 12:38:16', NULL, NULL, NULL, NULL),
(223, 53, 5, NULL, 'Thrissur', 1, '2026-03-24 13:34:03', 'Initial delivery', 1, '2026-03-24 13:34:03', NULL, NULL, NULL, NULL),
(224, 54, 11, NULL, 'tth', 1, '2026-03-25 11:07:47', 'Initial delivery', 1, '2026-03-25 11:07:47', NULL, NULL, NULL, NULL),
(225, 55, 8, NULL, 'thrissur', 1, '2026-04-03 07:22:02', 'Initial delivery', 1, '2026-04-03 07:22:02', NULL, NULL, NULL, NULL),
(226, 56, 8, NULL, 'palakkad', 1, '2026-04-07 09:44:43', 'Initial delivery', 1, '2026-04-07 09:44:43', NULL, NULL, NULL, NULL),
(227, 68, 31, NULL, 'thrissur', 1, '2026-04-08 07:38:06', 'Initial delivery', 1, '2026-04-08 07:38:06', NULL, NULL, NULL, NULL),
(228, 73, 2, NULL, 'thrissur', 1, '2026-04-12 07:20:05', 'Initial delivery', 1, '2026-04-12 07:20:05', NULL, NULL, NULL, NULL),
(229, 30, 1, NULL, 'thrissur', 0, '2026-04-14 10:06:06', 'now in kozhikode', 1, '2026-04-14 10:06:06', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `hope_daily_predictions`
--

CREATE TABLE `hope_daily_predictions` (
  `id` int(11) NOT NULL,
  `target_date` date NOT NULL,
  `label` varchar(255) NOT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `hope_daily_predictions`
--

INSERT INTO `hope_daily_predictions` (`id`, `target_date`, `label`, `amount`, `created_by`, `created_at`, `updated_at`) VALUES
(1, '2026-03-01', 'tesla resv', 1000.00, 1, '2026-03-18 11:12:57', '2026-03-18 11:14:59'),
(4, '2026-03-23', 'dfdf', 1000.00, 1, '2026-03-18 12:30:44', '2026-03-26 20:53:09'),
(6, '2026-03-20', 'test', 1000.00, 1, '2026-03-18 12:43:09', '2026-03-26 20:53:09'),
(8, '2026-03-24', 'bmw return tommorw', 1000.00, 1, '2026-03-26 20:53:09', '2026-03-26 20:53:09');

-- --------------------------------------------------------

--
-- Table structure for table `hope_daily_targets`
--

CREATE TABLE `hope_daily_targets` (
  `id` int(11) NOT NULL,
  `target_date` date NOT NULL,
  `target_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `hope_daily_targets`
--

INSERT INTO `hope_daily_targets` (`id`, `target_date`, `target_amount`, `created_by`, `created_at`, `updated_at`) VALUES
(1, '2026-03-01', 60000.00, 1, '2026-03-17 21:44:52', '2026-03-18 11:14:59');

-- --------------------------------------------------------

--
-- Table structure for table `inspection_photos`
--

CREATE TABLE `inspection_photos` (
  `id` int(11) NOT NULL,
  `inspection_id` int(11) NOT NULL,
  `view_name` varchar(50) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inspection_photos`
--

INSERT INTO `inspection_photos` (`id`, `inspection_id`, `view_name`, `file_path`, `created_at`) VALUES
(1, 12, 'front', 'uploads/inspections/insp_12_front_1772909527.webp', '2026-03-07 18:52:07'),
(2, 12, 'back', 'uploads/inspections/insp_12_back_1772909527.webp', '2026-03-07 18:52:07'),
(3, 12, 'left', 'uploads/inspections/insp_12_left_1772909527.webp', '2026-03-07 18:52:07'),
(4, 12, 'right', 'uploads/inspections/insp_12_right_1772909527.webp', '2026-03-07 18:52:07'),
(5, 12, 'interior', 'uploads/inspections/insp_12_interior_1772909527.webp', '2026-03-07 18:52:07'),
(6, 12, 'odometer', 'uploads/inspections/insp_12_odometer_1772909527.webp', '2026-03-07 18:52:07'),
(7, 12, 'with_customer', 'uploads/inspections/insp_12_with_customer_1772909527.webp', '2026-03-07 18:52:07'),
(8, 13, 'front', 'uploads/inspections/insp_13_front_1772909852.webp', '2026-03-07 18:57:32'),
(9, 13, 'back', 'uploads/inspections/insp_13_back_1772909852.webp', '2026-03-07 18:57:32'),
(10, 13, 'left', 'uploads/inspections/insp_13_left_1772909852.webp', '2026-03-07 18:57:32'),
(11, 13, 'right', 'uploads/inspections/insp_13_right_1772909852.webp', '2026-03-07 18:57:32'),
(12, 13, 'interior', 'uploads/inspections/insp_13_interior_1772909852.webp', '2026-03-07 18:57:32'),
(13, 13, 'odometer', 'uploads/inspections/insp_13_odometer_1772909852.webp', '2026-03-07 18:57:32'),
(14, 13, 'with_customer', 'uploads/inspections/insp_13_with_customer_1772909852.webp', '2026-03-07 18:57:32'),
(15, 14, 'front', 'uploads/inspections/insp_14_front_1772910012.webp', '2026-03-07 19:00:12'),
(16, 14, 'back', 'uploads/inspections/insp_14_back_1772910012.webp', '2026-03-07 19:00:12'),
(17, 14, 'left', 'uploads/inspections/insp_14_left_1772910012.webp', '2026-03-07 19:00:12'),
(18, 14, 'right', 'uploads/inspections/insp_14_right_1772910012.webp', '2026-03-07 19:00:12'),
(19, 14, 'interior', 'uploads/inspections/insp_14_interior_1772910012.webp', '2026-03-07 19:00:12'),
(20, 14, 'odometer', 'uploads/inspections/insp_14_odometer_1772910012.webp', '2026-03-07 19:00:12'),
(21, 14, 'with_customer', 'uploads/inspections/insp_14_with_customer_1772910012.webp', '2026-03-07 19:00:12'),
(22, 15, 'front', 'uploads/inspections/insp_15_front_1772910733.webp', '2026-03-07 19:12:13'),
(23, 15, 'back', 'uploads/inspections/insp_15_back_1772910733.webp', '2026-03-07 19:12:13'),
(24, 15, 'left', 'uploads/inspections/insp_15_left_1772910733.webp', '2026-03-07 19:12:13'),
(25, 15, 'right', 'uploads/inspections/insp_15_right_1772910733.webp', '2026-03-07 19:12:13'),
(26, 15, 'interior', 'uploads/inspections/insp_15_interior_1772910733.webp', '2026-03-07 19:12:13'),
(27, 15, 'odometer', 'uploads/inspections/insp_15_odometer_1772910733.webp', '2026-03-07 19:12:13'),
(28, 15, 'with_customer', 'uploads/inspections/insp_15_with_customer_1772910733.webp', '2026-03-07 19:12:13'),
(29, 16, 'front', 'uploads/inspections/insp_16_front_1772910840.webp', '2026-03-07 19:14:00'),
(30, 16, 'back', 'uploads/inspections/insp_16_back_1772910840.webp', '2026-03-07 19:14:00'),
(31, 16, 'left', 'uploads/inspections/insp_16_left_1772910840.webp', '2026-03-07 19:14:00'),
(32, 16, 'right', 'uploads/inspections/insp_16_right_1772910840.webp', '2026-03-07 19:14:00'),
(33, 16, 'interior', 'uploads/inspections/insp_16_interior_1772910840.webp', '2026-03-07 19:14:00'),
(34, 16, 'odometer', 'uploads/inspections/insp_16_odometer_1772910840.webp', '2026-03-07 19:14:00'),
(35, 16, 'with_customer', 'uploads/inspections/insp_16_with_customer_1772910840.webp', '2026-03-07 19:14:00'),
(36, 17, 'front', 'uploads/inspections/insp_17_front_1772911128.webp', '2026-03-07 19:18:48'),
(37, 17, 'back', 'uploads/inspections/insp_17_back_1772911128.webp', '2026-03-07 19:18:48'),
(38, 17, 'left', 'uploads/inspections/insp_17_left_1772911128.webp', '2026-03-07 19:18:48'),
(39, 17, 'right', 'uploads/inspections/insp_17_right_1772911128.webp', '2026-03-07 19:18:48'),
(40, 17, 'interior', 'uploads/inspections/insp_17_interior_1772911128.webp', '2026-03-07 19:18:48'),
(41, 17, 'odometer', 'uploads/inspections/insp_17_odometer_1772911128.webp', '2026-03-07 19:18:48'),
(42, 17, 'with_customer', 'uploads/inspections/insp_17_with_customer_1772911128.webp', '2026-03-07 19:18:48'),
(43, 18, 'front', 'uploads/inspections/insp_18_front_1772911183.webp', '2026-03-07 19:19:43'),
(44, 18, 'back', 'uploads/inspections/insp_18_back_1772911183.webp', '2026-03-07 19:19:43'),
(45, 18, 'left', 'uploads/inspections/insp_18_left_1772911183.webp', '2026-03-07 19:19:43'),
(46, 18, 'right', 'uploads/inspections/insp_18_right_1772911183.webp', '2026-03-07 19:19:43'),
(47, 18, 'interior', 'uploads/inspections/insp_18_interior_1772911183.webp', '2026-03-07 19:19:43'),
(48, 18, 'odometer', 'uploads/inspections/insp_18_odometer_1772911183.webp', '2026-03-07 19:19:43'),
(49, 18, 'with_customer', 'uploads/inspections/insp_18_with_customer_1772911183.webp', '2026-03-07 19:19:43'),
(50, 19, 'front', 'uploads/inspections/insp_19_front_1772912271.webp', '2026-03-07 19:37:51'),
(51, 19, 'back', 'uploads/inspections/insp_19_back_1772912271.webp', '2026-03-07 19:37:51'),
(52, 19, 'left', 'uploads/inspections/insp_19_left_1772912271.webp', '2026-03-07 19:37:51'),
(53, 19, 'right', 'uploads/inspections/insp_19_right_1772912271.webp', '2026-03-07 19:37:51'),
(54, 19, 'interior', 'uploads/inspections/insp_19_interior_1772912271.webp', '2026-03-07 19:37:51'),
(55, 19, 'odometer', 'uploads/inspections/insp_19_odometer_1772912271.webp', '2026-03-07 19:37:51'),
(56, 19, 'with_customer', 'uploads/inspections/insp_19_with_customer_1772912271.webp', '2026-03-07 19:37:51'),
(57, 20, 'front', 'uploads/inspections/insp_20_front_1772912370.webp', '2026-03-07 19:39:30'),
(58, 20, 'back', 'uploads/inspections/insp_20_back_1772912370.webp', '2026-03-07 19:39:30'),
(59, 20, 'left', 'uploads/inspections/insp_20_left_1772912370.webp', '2026-03-07 19:39:30'),
(60, 20, 'right', 'uploads/inspections/insp_20_right_1772912370.webp', '2026-03-07 19:39:30'),
(61, 20, 'interior', 'uploads/inspections/insp_20_interior_1772912370.webp', '2026-03-07 19:39:30'),
(62, 20, 'odometer', 'uploads/inspections/insp_20_odometer_1772912370.webp', '2026-03-07 19:39:30'),
(63, 20, 'with_customer', 'uploads/inspections/insp_20_with_customer_1772912370.webp', '2026-03-07 19:39:30'),
(64, 21, 'front', 'uploads/inspections/insp_21_front_1772913229.webp', '2026-03-07 19:53:49'),
(65, 21, 'back', 'uploads/inspections/insp_21_back_1772913229.webp', '2026-03-07 19:53:49'),
(66, 21, 'left', 'uploads/inspections/insp_21_left_1772913229.webp', '2026-03-07 19:53:49'),
(67, 21, 'right', 'uploads/inspections/insp_21_right_1772913229.webp', '2026-03-07 19:53:49'),
(68, 21, 'interior', 'uploads/inspections/insp_21_interior_1772913229.webp', '2026-03-07 19:53:49'),
(69, 21, 'odometer', 'uploads/inspections/insp_21_odometer_1772913229.webp', '2026-03-07 19:53:49'),
(70, 21, 'with_customer', 'uploads/inspections/insp_21_with_customer_1772913229.webp', '2026-03-07 19:53:49'),
(71, 22, 'front', 'uploads/inspections/insp_22_front_1772913332.webp', '2026-03-07 19:55:32'),
(72, 22, 'back', 'uploads/inspections/insp_22_back_1772913332.webp', '2026-03-07 19:55:32'),
(73, 22, 'left', 'uploads/inspections/insp_22_left_1772913332.webp', '2026-03-07 19:55:32'),
(74, 22, 'right', 'uploads/inspections/insp_22_right_1772913332.webp', '2026-03-07 19:55:32'),
(75, 22, 'interior', 'uploads/inspections/insp_22_interior_1772913332.webp', '2026-03-07 19:55:32'),
(76, 22, 'odometer', 'uploads/inspections/insp_22_odometer_1772913332.webp', '2026-03-07 19:55:32'),
(77, 22, 'with_customer', 'uploads/inspections/insp_22_with_customer_1772913332.webp', '2026-03-07 19:55:32'),
(78, 23, 'front', 'uploads/inspections/insp_23_front_1772914335.webp', '2026-03-07 20:12:15'),
(79, 23, 'back', 'uploads/inspections/insp_23_back_1772914335.webp', '2026-03-07 20:12:15'),
(80, 23, 'left', 'uploads/inspections/insp_23_left_1772914335.webp', '2026-03-07 20:12:15'),
(81, 23, 'right', 'uploads/inspections/insp_23_right_1772914335.webp', '2026-03-07 20:12:15'),
(82, 23, 'interior', 'uploads/inspections/insp_23_interior_1772914335.webp', '2026-03-07 20:12:15'),
(83, 23, 'odometer', 'uploads/inspections/insp_23_odometer_1772914335.webp', '2026-03-07 20:12:15'),
(84, 23, 'with_customer', 'uploads/inspections/insp_23_with_customer_1772914335.webp', '2026-03-07 20:12:15'),
(85, 24, 'front', 'uploads/inspections/insp_24_front_1772914400.webp', '2026-03-07 20:13:20'),
(86, 24, 'back', 'uploads/inspections/insp_24_back_1772914400.webp', '2026-03-07 20:13:20'),
(87, 24, 'left', 'uploads/inspections/insp_24_left_1772914400.webp', '2026-03-07 20:13:20'),
(88, 24, 'right', 'uploads/inspections/insp_24_right_1772914400.webp', '2026-03-07 20:13:20'),
(89, 24, 'interior', 'uploads/inspections/insp_24_interior_1772914400.webp', '2026-03-07 20:13:20'),
(90, 24, 'odometer', 'uploads/inspections/insp_24_odometer_1772914400.webp', '2026-03-07 20:13:20'),
(91, 24, 'with_customer', 'uploads/inspections/insp_24_with_customer_1772914400.webp', '2026-03-07 20:13:20'),
(92, 25, 'front', 'uploads/inspections/insp_25_front_1772915199.webp', '2026-03-07 20:26:39'),
(93, 25, 'back', 'uploads/inspections/insp_25_back_1772915199.webp', '2026-03-07 20:26:39'),
(94, 25, 'left', 'uploads/inspections/insp_25_left_1772915199.webp', '2026-03-07 20:26:39'),
(95, 25, 'right', 'uploads/inspections/insp_25_right_1772915199.webp', '2026-03-07 20:26:39'),
(96, 25, 'interior', 'uploads/inspections/insp_25_interior_1772915199.webp', '2026-03-07 20:26:39'),
(97, 25, 'odometer', 'uploads/inspections/insp_25_odometer_1772915199.webp', '2026-03-07 20:26:39'),
(98, 25, 'with_customer', 'uploads/inspections/insp_25_with_customer_1772915199.webp', '2026-03-07 20:26:39'),
(99, 26, 'front', 'uploads/inspections/insp_26_front_1772915273.webp', '2026-03-07 20:27:53'),
(100, 26, 'back', 'uploads/inspections/insp_26_back_1772915273.webp', '2026-03-07 20:27:53'),
(101, 26, 'left', 'uploads/inspections/insp_26_left_1772915273.webp', '2026-03-07 20:27:53'),
(102, 26, 'right', 'uploads/inspections/insp_26_right_1772915273.webp', '2026-03-07 20:27:53'),
(103, 26, 'interior', 'uploads/inspections/insp_26_interior_1772915273.webp', '2026-03-07 20:27:53'),
(104, 26, 'odometer', 'uploads/inspections/insp_26_odometer_1772915273.webp', '2026-03-07 20:27:53'),
(105, 26, 'with_customer', 'uploads/inspections/insp_26_with_customer_1772915273.webp', '2026-03-07 20:27:53'),
(106, 27, 'front', 'uploads/inspections/insp_27_front_1772915404.webp', '2026-03-07 20:30:04'),
(107, 27, 'back', 'uploads/inspections/insp_27_back_1772915404.webp', '2026-03-07 20:30:04'),
(108, 27, 'left', 'uploads/inspections/insp_27_left_1772915404.webp', '2026-03-07 20:30:04'),
(109, 27, 'right', 'uploads/inspections/insp_27_right_1772915404.webp', '2026-03-07 20:30:04'),
(110, 27, 'interior', 'uploads/inspections/insp_27_interior_1772915404.webp', '2026-03-07 20:30:04'),
(111, 27, 'odometer', 'uploads/inspections/insp_27_odometer_1772915404.webp', '2026-03-07 20:30:04'),
(112, 27, 'with_customer', 'uploads/inspections/insp_27_with_customer_1772915404.webp', '2026-03-07 20:30:04'),
(113, 28, 'front', 'uploads/inspections/insp_28_front_1772986256.webp', '2026-03-08 16:10:56'),
(114, 28, 'back', 'uploads/inspections/insp_28_back_1772986256.webp', '2026-03-08 16:10:56'),
(115, 28, 'left', 'uploads/inspections/insp_28_left_1772986256.webp', '2026-03-08 16:10:56'),
(116, 28, 'right', 'uploads/inspections/insp_28_right_1772986256.webp', '2026-03-08 16:10:56'),
(117, 28, 'interior', 'uploads/inspections/insp_28_interior_1772986256.webp', '2026-03-08 16:10:56'),
(118, 28, 'odometer', 'uploads/inspections/insp_28_odometer_1772986256.webp', '2026-03-08 16:10:56'),
(119, 28, 'with_customer', 'uploads/inspections/insp_28_with_customer_1772986256.webp', '2026-03-08 16:10:56'),
(120, 29, 'front', 'uploads/inspections/insp_29_front_1772986388.webp', '2026-03-08 16:13:08'),
(121, 29, 'back', 'uploads/inspections/insp_29_back_1772986388.webp', '2026-03-08 16:13:08'),
(122, 29, 'left', 'uploads/inspections/insp_29_left_1772986388.webp', '2026-03-08 16:13:08'),
(123, 29, 'right', 'uploads/inspections/insp_29_right_1772986388.webp', '2026-03-08 16:13:08'),
(124, 29, 'interior', 'uploads/inspections/insp_29_interior_1772986388.webp', '2026-03-08 16:13:08'),
(125, 29, 'odometer', 'uploads/inspections/insp_29_odometer_1772986388.webp', '2026-03-08 16:13:08'),
(126, 29, 'with_customer', 'uploads/inspections/insp_29_with_customer_1772986388.webp', '2026-03-08 16:13:08'),
(127, 30, 'front', 'uploads/inspections/insp_30_front_1772986564.webp', '2026-03-08 16:16:04'),
(128, 30, 'back', 'uploads/inspections/insp_30_back_1772986564.webp', '2026-03-08 16:16:04'),
(129, 30, 'left', 'uploads/inspections/insp_30_left_1772986564.webp', '2026-03-08 16:16:04'),
(130, 30, 'right', 'uploads/inspections/insp_30_right_1772986564.webp', '2026-03-08 16:16:04'),
(131, 30, 'interior', 'uploads/inspections/insp_30_interior_1772986564.webp', '2026-03-08 16:16:04'),
(132, 30, 'odometer', 'uploads/inspections/insp_30_odometer_1772986564.webp', '2026-03-08 16:16:04'),
(133, 30, 'with_customer', 'uploads/inspections/insp_30_with_customer_1772986564.webp', '2026-03-08 16:16:04'),
(134, 31, 'front', 'uploads/inspections/insp_31_front_1772986601.webp', '2026-03-08 16:16:41'),
(135, 31, 'back', 'uploads/inspections/insp_31_back_1772986601.webp', '2026-03-08 16:16:41'),
(136, 31, 'left', 'uploads/inspections/insp_31_left_1772986601.webp', '2026-03-08 16:16:41'),
(137, 31, 'right', 'uploads/inspections/insp_31_right_1772986601.webp', '2026-03-08 16:16:41'),
(138, 31, 'interior', 'uploads/inspections/insp_31_interior_1772986601.webp', '2026-03-08 16:16:41'),
(139, 31, 'odometer', 'uploads/inspections/insp_31_odometer_1772986601.webp', '2026-03-08 16:16:41'),
(140, 31, 'with_customer', 'uploads/inspections/insp_31_with_customer_1772986601.webp', '2026-03-08 16:16:41'),
(141, 32, 'front', 'uploads/inspections/insp_32_front_1772989207.webp', '2026-03-08 17:00:07'),
(142, 32, 'back', 'uploads/inspections/insp_32_back_1772989207.webp', '2026-03-08 17:00:07'),
(143, 32, 'left', 'uploads/inspections/insp_32_left_1772989207.webp', '2026-03-08 17:00:07'),
(144, 32, 'right', 'uploads/inspections/insp_32_right_1772989207.webp', '2026-03-08 17:00:07'),
(145, 32, 'interior', 'uploads/inspections/insp_32_interior_1772989207.webp', '2026-03-08 17:00:07'),
(146, 32, 'odometer', 'uploads/inspections/insp_32_odometer_1772989207.webp', '2026-03-08 17:00:07'),
(147, 32, 'with_customer', 'uploads/inspections/insp_32_with_customer_1772989207.webp', '2026-03-08 17:00:07'),
(148, 33, 'front', 'uploads/inspections/insp_33_front_1772989257.webp', '2026-03-08 17:00:57'),
(149, 33, 'back', 'uploads/inspections/insp_33_back_1772989257.webp', '2026-03-08 17:00:57'),
(150, 33, 'left', 'uploads/inspections/insp_33_left_1772989257.webp', '2026-03-08 17:00:57'),
(151, 33, 'right', 'uploads/inspections/insp_33_right_1772989257.webp', '2026-03-08 17:00:57'),
(152, 33, 'interior', 'uploads/inspections/insp_33_interior_1772989257.webp', '2026-03-08 17:00:57'),
(153, 33, 'odometer', 'uploads/inspections/insp_33_odometer_1772989257.webp', '2026-03-08 17:00:57'),
(154, 33, 'with_customer', 'uploads/inspections/insp_33_with_customer_1772989257.webp', '2026-03-08 17:00:57'),
(155, 34, 'front', 'uploads/inspections/insp_34_front_1773084085.webp', '2026-03-09 19:21:25'),
(156, 34, 'back', 'uploads/inspections/insp_34_back_1773084085.webp', '2026-03-09 19:21:25'),
(157, 34, 'left', 'uploads/inspections/insp_34_left_1773084085.webp', '2026-03-09 19:21:25'),
(158, 34, 'right', 'uploads/inspections/insp_34_right_1773084085.webp', '2026-03-09 19:21:25'),
(159, 34, 'interior', 'uploads/inspections/insp_34_interior_1773084085.webp', '2026-03-09 19:21:25'),
(160, 34, 'odometer', 'uploads/inspections/insp_34_odometer_1773084085.webp', '2026-03-09 19:21:25'),
(161, 34, 'with_customer', 'uploads/inspections/insp_34_with_customer_1773084085.webp', '2026-03-09 19:21:25'),
(162, 35, 'front', 'uploads/inspections/insp_35_front_1773084163.webp', '2026-03-09 19:22:43'),
(163, 35, 'back', 'uploads/inspections/insp_35_back_1773084163.webp', '2026-03-09 19:22:43'),
(164, 35, 'left', 'uploads/inspections/insp_35_left_1773084163.webp', '2026-03-09 19:22:43'),
(165, 35, 'right', 'uploads/inspections/insp_35_right_1773084163.webp', '2026-03-09 19:22:43'),
(166, 35, 'interior', 'uploads/inspections/insp_35_interior_1773084163.webp', '2026-03-09 19:22:43'),
(167, 35, 'odometer', 'uploads/inspections/insp_35_odometer_1773084163.webp', '2026-03-09 19:22:43'),
(168, 35, 'with_customer', 'uploads/inspections/insp_35_with_customer_1773084163.webp', '2026-03-09 19:22:43'),
(169, 36, 'front', 'uploads/inspections/insp_36_front_1773084215.webp', '2026-03-09 19:23:35'),
(170, 36, 'back', 'uploads/inspections/insp_36_back_1773084215.webp', '2026-03-09 19:23:35'),
(171, 36, 'left', 'uploads/inspections/insp_36_left_1773084215.webp', '2026-03-09 19:23:35'),
(172, 36, 'right', 'uploads/inspections/insp_36_right_1773084215.webp', '2026-03-09 19:23:35'),
(173, 36, 'interior', 'uploads/inspections/insp_36_interior_1773084215.webp', '2026-03-09 19:23:35'),
(174, 36, 'odometer', 'uploads/inspections/insp_36_odometer_1773084215.webp', '2026-03-09 19:23:35'),
(175, 36, 'with_customer', 'uploads/inspections/insp_36_with_customer_1773084215.webp', '2026-03-09 19:23:35'),
(176, 37, 'front', 'uploads/inspections/insp_37_front_1773084290.webp', '2026-03-09 19:24:50'),
(177, 37, 'back', 'uploads/inspections/insp_37_back_1773084290.webp', '2026-03-09 19:24:50'),
(178, 37, 'left', 'uploads/inspections/insp_37_left_1773084290.webp', '2026-03-09 19:24:50'),
(179, 37, 'right', 'uploads/inspections/insp_37_right_1773084290.webp', '2026-03-09 19:24:50'),
(180, 37, 'interior', 'uploads/inspections/insp_37_interior_1773084290.webp', '2026-03-09 19:24:50'),
(181, 37, 'odometer', 'uploads/inspections/insp_37_odometer_1773084290.webp', '2026-03-09 19:24:50'),
(182, 37, 'with_customer', 'uploads/inspections/insp_37_with_customer_1773084290.webp', '2026-03-09 19:24:50'),
(183, 38, 'front', 'uploads/inspections/insp_38_front_1773085032.webp', '2026-03-09 19:37:12'),
(184, 38, 'back', 'uploads/inspections/insp_38_back_1773085032.webp', '2026-03-09 19:37:12'),
(185, 38, 'left', 'uploads/inspections/insp_38_left_1773085032.webp', '2026-03-09 19:37:12'),
(186, 38, 'right', 'uploads/inspections/insp_38_right_1773085032.webp', '2026-03-09 19:37:12'),
(187, 38, 'interior', 'uploads/inspections/insp_38_interior_1773085032.webp', '2026-03-09 19:37:12'),
(188, 38, 'odometer', 'uploads/inspections/insp_38_odometer_1773085032.webp', '2026-03-09 19:37:12'),
(189, 38, 'with_customer', 'uploads/inspections/insp_38_with_customer_1773085032.webp', '2026-03-09 19:37:12'),
(190, 39, 'front', 'uploads/inspections/insp_39_front_1773085135.webp', '2026-03-09 19:38:55'),
(191, 39, 'back', 'uploads/inspections/insp_39_back_1773085135.webp', '2026-03-09 19:38:55'),
(192, 39, 'left', 'uploads/inspections/insp_39_left_1773085135.webp', '2026-03-09 19:38:55'),
(193, 39, 'right', 'uploads/inspections/insp_39_right_1773085135.webp', '2026-03-09 19:38:55'),
(194, 39, 'interior', 'uploads/inspections/insp_39_interior_1773085135.webp', '2026-03-09 19:38:55'),
(195, 39, 'odometer', 'uploads/inspections/insp_39_odometer_1773085135.webp', '2026-03-09 19:38:55'),
(196, 39, 'with_customer', 'uploads/inspections/insp_39_with_customer_1773085135.webp', '2026-03-09 19:38:55'),
(197, 40, 'front', 'uploads/inspections/insp_40_front_1773207061.webp', '2026-03-11 05:31:01'),
(198, 40, 'back', 'uploads/inspections/insp_40_back_1773207061.webp', '2026-03-11 05:31:01'),
(199, 40, 'left', 'uploads/inspections/insp_40_left_1773207061.webp', '2026-03-11 05:31:01'),
(200, 40, 'right', 'uploads/inspections/insp_40_right_1773207061.webp', '2026-03-11 05:31:01'),
(201, 40, 'interior', 'uploads/inspections/insp_40_interior_1773207061.webp', '2026-03-11 05:31:01'),
(202, 40, 'odometer', 'uploads/inspections/insp_40_odometer_1773207061.webp', '2026-03-11 05:31:01'),
(203, 40, 'with_customer', 'uploads/inspections/insp_40_with_customer_1773207061.webp', '2026-03-11 05:31:01'),
(204, 41, 'front', 'uploads/inspections/insp_41_front_1773213592.webp', '2026-03-11 07:19:52'),
(205, 41, 'back', 'uploads/inspections/insp_41_back_1773213592.webp', '2026-03-11 07:19:52'),
(206, 41, 'left', 'uploads/inspections/insp_41_left_1773213592.webp', '2026-03-11 07:19:52'),
(207, 41, 'right', 'uploads/inspections/insp_41_right_1773213592.webp', '2026-03-11 07:19:52'),
(208, 41, 'interior', 'uploads/inspections/insp_41_interior_1773213592.webp', '2026-03-11 07:19:52'),
(209, 41, 'odometer', 'uploads/inspections/insp_41_odometer_1773213592.webp', '2026-03-11 07:19:52'),
(210, 41, 'with_customer', 'uploads/inspections/insp_41_with_customer_1773213592.webp', '2026-03-11 07:19:52'),
(211, 42, 'front', 'uploads/inspections/insp_42_front_1773213935.webp', '2026-03-11 07:25:35'),
(212, 42, 'back', 'uploads/inspections/insp_42_back_1773213935.webp', '2026-03-11 07:25:35'),
(213, 42, 'left', 'uploads/inspections/insp_42_left_1773213935.webp', '2026-03-11 07:25:35'),
(214, 42, 'right', 'uploads/inspections/insp_42_right_1773213935.webp', '2026-03-11 07:25:35'),
(215, 42, 'interior', 'uploads/inspections/insp_42_interior_1773213935.webp', '2026-03-11 07:25:35'),
(216, 42, 'odometer', 'uploads/inspections/insp_42_odometer_1773213935.webp', '2026-03-11 07:25:35'),
(217, 43, 'front', 'uploads/inspections/insp_43_front_1773218886.webp', '2026-03-11 08:48:06'),
(218, 43, 'back', 'uploads/inspections/insp_43_back_1773218886.webp', '2026-03-11 08:48:06'),
(219, 43, 'left', 'uploads/inspections/insp_43_left_1773218886.webp', '2026-03-11 08:48:06'),
(220, 43, 'right', 'uploads/inspections/insp_43_right_1773218886.webp', '2026-03-11 08:48:06'),
(221, 43, 'interior', 'uploads/inspections/insp_43_interior_1773218886.webp', '2026-03-11 08:48:06'),
(222, 43, 'odometer', 'uploads/inspections/insp_43_odometer_1773218886.webp', '2026-03-11 08:48:06'),
(223, 43, 'with_customer', 'uploads/inspections/insp_43_with_customer_1773218886.webp', '2026-03-11 08:48:06'),
(224, 44, 'front', 'uploads/inspections/insp_44_front_1773218933.webp', '2026-03-11 08:48:53'),
(225, 44, 'back', 'uploads/inspections/insp_44_back_1773218933.webp', '2026-03-11 08:48:53'),
(226, 44, 'left', 'uploads/inspections/insp_44_left_1773218933.webp', '2026-03-11 08:48:53'),
(227, 44, 'right', 'uploads/inspections/insp_44_right_1773218933.webp', '2026-03-11 08:48:53'),
(228, 44, 'interior', 'uploads/inspections/insp_44_interior_1773218933.webp', '2026-03-11 08:48:53'),
(229, 44, 'odometer', 'uploads/inspections/insp_44_odometer_1773218933.webp', '2026-03-11 08:48:53'),
(230, 45, 'front', 'uploads/inspections/insp_45_front_1773293662.webp', '2026-03-12 05:34:22'),
(231, 45, 'back', 'uploads/inspections/insp_45_back_1773293662.webp', '2026-03-12 05:34:22'),
(232, 45, 'left', 'uploads/inspections/insp_45_left_1773293662.webp', '2026-03-12 05:34:22'),
(233, 45, 'right', 'uploads/inspections/insp_45_right_1773293662.webp', '2026-03-12 05:34:22'),
(234, 45, 'interior', 'uploads/inspections/insp_45_interior_1773293662.webp', '2026-03-12 05:34:22'),
(235, 45, 'odometer', 'uploads/inspections/insp_45_odometer_1773293662.webp', '2026-03-12 05:34:22'),
(236, 45, 'with_customer', 'uploads/inspections/insp_45_with_customer_1773293662.webp', '2026-03-12 05:34:22'),
(237, 46, 'front', 'uploads/inspections/insp_46_front_1773294720.webp', '2026-03-12 05:52:00'),
(238, 46, 'back', 'uploads/inspections/insp_46_back_1773294720.webp', '2026-03-12 05:52:00'),
(239, 46, 'left', 'uploads/inspections/insp_46_left_1773294720.webp', '2026-03-12 05:52:00'),
(240, 46, 'right', 'uploads/inspections/insp_46_right_1773294720.webp', '2026-03-12 05:52:00'),
(241, 46, 'interior', 'uploads/inspections/insp_46_interior_1773294720.webp', '2026-03-12 05:52:00'),
(242, 46, 'odometer', 'uploads/inspections/insp_46_odometer_1773294720.webp', '2026-03-12 05:52:00'),
(243, 46, 'with_customer', 'uploads/inspections/insp_46_with_customer_1773294720.webp', '2026-03-12 05:52:00'),
(244, 47, 'front', 'uploads/inspections/insp_47_front_1773294773.webp', '2026-03-12 05:52:53'),
(245, 47, 'back', 'uploads/inspections/insp_47_back_1773294773.webp', '2026-03-12 05:52:53'),
(246, 47, 'left', 'uploads/inspections/insp_47_left_1773294773.webp', '2026-03-12 05:52:53'),
(247, 47, 'right', 'uploads/inspections/insp_47_right_1773294773.webp', '2026-03-12 05:52:53'),
(248, 47, 'interior', 'uploads/inspections/insp_47_interior_1773294773.webp', '2026-03-12 05:52:53'),
(249, 47, 'odometer', 'uploads/inspections/insp_47_odometer_1773294773.webp', '2026-03-12 05:52:53'),
(250, 48, 'front', 'uploads/inspections/insp_48_front_1773328162.webp', '2026-03-12 15:09:22'),
(251, 48, 'back', 'uploads/inspections/insp_48_back_1773328162.webp', '2026-03-12 15:09:22'),
(252, 48, 'left', 'uploads/inspections/insp_48_left_1773328162.webp', '2026-03-12 15:09:22'),
(253, 48, 'right', 'uploads/inspections/insp_48_right_1773328162.webp', '2026-03-12 15:09:22'),
(254, 48, 'interior', 'uploads/inspections/insp_48_interior_1773328162.webp', '2026-03-12 15:09:22'),
(255, 48, 'odometer', 'uploads/inspections/insp_48_odometer_1773328162.webp', '2026-03-12 15:09:22'),
(256, 49, 'front', 'uploads/inspections/insp_49_front_1773391828.webp', '2026-03-13 08:50:28'),
(257, 49, 'back', 'uploads/inspections/insp_49_back_1773391828.webp', '2026-03-13 08:50:28'),
(258, 49, 'left', 'uploads/inspections/insp_49_left_1773391828.webp', '2026-03-13 08:50:28'),
(259, 49, 'right', 'uploads/inspections/insp_49_right_1773391828.webp', '2026-03-13 08:50:28'),
(260, 49, 'interior', 'uploads/inspections/insp_49_interior_1773391828.webp', '2026-03-13 08:50:28'),
(261, 49, 'odometer', 'uploads/inspections/insp_49_odometer_1773391828.webp', '2026-03-13 08:50:28'),
(262, 49, 'with_customer', 'uploads/inspections/insp_49_with_customer_1773391828.webp', '2026-03-13 08:50:28'),
(263, 50, 'front', 'uploads/inspections/insp_50_front_1773394329.webp', '2026-03-13 09:32:09'),
(264, 50, 'back', 'uploads/inspections/insp_50_back_1773394329.webp', '2026-03-13 09:32:09'),
(265, 50, 'left', 'uploads/inspections/insp_50_left_1773394329.webp', '2026-03-13 09:32:09'),
(266, 50, 'right', 'uploads/inspections/insp_50_right_1773394329.webp', '2026-03-13 09:32:09'),
(267, 50, 'interior', 'uploads/inspections/insp_50_interior_1773394329.webp', '2026-03-13 09:32:09'),
(268, 50, 'odometer', 'uploads/inspections/insp_50_odometer_1773394329.webp', '2026-03-13 09:32:09'),
(269, 50, 'with_customer', 'uploads/inspections/insp_50_with_customer_1773394329.webp', '2026-03-13 09:32:09'),
(270, 51, 'front', 'uploads/inspections/insp_51_front_1773398900.webp', '2026-03-13 10:48:20'),
(271, 51, 'back', 'uploads/inspections/insp_51_back_1773398900.webp', '2026-03-13 10:48:20'),
(272, 51, 'left', 'uploads/inspections/insp_51_left_1773398900.webp', '2026-03-13 10:48:20'),
(273, 51, 'right', 'uploads/inspections/insp_51_right_1773398900.webp', '2026-03-13 10:48:20'),
(274, 51, 'interior', 'uploads/inspections/insp_51_interior_1773398900.webp', '2026-03-13 10:48:20'),
(275, 51, 'odometer', 'uploads/inspections/insp_51_odometer_1773398900.webp', '2026-03-13 10:48:20'),
(276, 52, 'front', 'uploads/inspections/insp_52_front_1773653428.webp', '2026-03-16 09:30:28'),
(277, 52, 'back', 'uploads/inspections/insp_52_back_1773653428.webp', '2026-03-16 09:30:28'),
(278, 52, 'left', 'uploads/inspections/insp_52_left_1773653428.webp', '2026-03-16 09:30:28'),
(279, 52, 'right', 'uploads/inspections/insp_52_right_1773653428.webp', '2026-03-16 09:30:28'),
(280, 52, 'interior', 'uploads/inspections/insp_52_interior_1773653428.webp', '2026-03-16 09:30:28'),
(281, 52, 'odometer', 'uploads/inspections/insp_52_odometer_1773653428.webp', '2026-03-16 09:30:28'),
(282, 52, 'with_customer', 'uploads/inspections/insp_52_with_customer_1773653428.webp', '2026-03-16 09:30:28'),
(283, 53, 'front', 'uploads/inspections/insp_53_front_1773653534.webp', '2026-03-16 09:32:14'),
(284, 53, 'back', 'uploads/inspections/insp_53_back_1773653534.webp', '2026-03-16 09:32:14'),
(285, 53, 'left', 'uploads/inspections/insp_53_left_1773653534.webp', '2026-03-16 09:32:14'),
(286, 53, 'right', 'uploads/inspections/insp_53_right_1773653534.webp', '2026-03-16 09:32:14'),
(287, 53, 'interior', 'uploads/inspections/insp_53_interior_1773653534.webp', '2026-03-16 09:32:14'),
(288, 53, 'odometer', 'uploads/inspections/insp_53_odometer_1773653534.webp', '2026-03-16 09:32:14'),
(289, 53, 'with_customer', 'uploads/inspections/insp_53_with_customer_1773653534.webp', '2026-03-16 09:32:14'),
(290, 54, 'front', 'uploads/inspections/insp_54_front_1773661385.webp', '2026-03-16 11:43:05'),
(291, 54, 'back', 'uploads/inspections/insp_54_back_1773661385.webp', '2026-03-16 11:43:05'),
(292, 54, 'left', 'uploads/inspections/insp_54_left_1773661385.webp', '2026-03-16 11:43:05'),
(293, 54, 'right', 'uploads/inspections/insp_54_right_1773661385.webp', '2026-03-16 11:43:05'),
(294, 54, 'interior', 'uploads/inspections/insp_54_interior_1773661385.webp', '2026-03-16 11:43:05'),
(295, 54, 'odometer', 'uploads/inspections/insp_54_odometer_1773661385.webp', '2026-03-16 11:43:05'),
(296, 54, 'with_customer', 'uploads/inspections/insp_54_with_customer_1773661385.webp', '2026-03-16 11:43:05'),
(297, 55, 'front', 'uploads/inspections/insp_55_front_1773662651.webp', '2026-03-16 12:04:11'),
(298, 55, 'back', 'uploads/inspections/insp_55_back_1773662651.webp', '2026-03-16 12:04:11'),
(299, 55, 'left', 'uploads/inspections/insp_55_left_1773662651.webp', '2026-03-16 12:04:11'),
(300, 55, 'right', 'uploads/inspections/insp_55_right_1773662651.webp', '2026-03-16 12:04:11'),
(301, 55, 'interior', 'uploads/inspections/insp_55_interior_1773662651.webp', '2026-03-16 12:04:11'),
(302, 55, 'odometer', 'uploads/inspections/insp_55_odometer_1773662651.webp', '2026-03-16 12:04:11'),
(303, 55, 'with_customer', 'uploads/inspections/insp_55_with_customer_1773662651.webp', '2026-03-16 12:04:11'),
(304, 56, 'front', 'uploads/inspections/insp_56_front_1773674731.webp', '2026-03-16 15:25:31'),
(305, 56, 'back', 'uploads/inspections/insp_56_back_1773674731.webp', '2026-03-16 15:25:31'),
(306, 56, 'left', 'uploads/inspections/insp_56_left_1773674731.webp', '2026-03-16 15:25:31'),
(307, 56, 'right', 'uploads/inspections/insp_56_right_1773674731.webp', '2026-03-16 15:25:31'),
(308, 56, 'interior', 'uploads/inspections/insp_56_interior_1773674731.webp', '2026-03-16 15:25:31'),
(309, 56, 'odometer', 'uploads/inspections/insp_56_odometer_1773674731.webp', '2026-03-16 15:25:31'),
(310, 56, 'with_customer', 'uploads/inspections/insp_56_with_customer_1773674731.webp', '2026-03-16 15:25:31'),
(311, 57, 'front', 'uploads/inspections/insp_57_front_1773682690.webp', '2026-03-16 17:38:10'),
(312, 57, 'back', 'uploads/inspections/insp_57_back_1773682690.webp', '2026-03-16 17:38:10'),
(313, 57, 'left', 'uploads/inspections/insp_57_left_1773682690.webp', '2026-03-16 17:38:10'),
(314, 57, 'right', 'uploads/inspections/insp_57_right_1773682690.webp', '2026-03-16 17:38:10'),
(315, 57, 'interior', 'uploads/inspections/insp_57_interior_1773682690.webp', '2026-03-16 17:38:10'),
(316, 57, 'odometer', 'uploads/inspections/insp_57_odometer_1773682690.webp', '2026-03-16 17:38:10'),
(317, 58, 'front', 'uploads/inspections/insp_58_front_1773682885.webp', '2026-03-16 17:41:25'),
(318, 58, 'back', 'uploads/inspections/insp_58_back_1773682885.webp', '2026-03-16 17:41:25'),
(319, 58, 'left', 'uploads/inspections/insp_58_left_1773682885.webp', '2026-03-16 17:41:25'),
(320, 58, 'right', 'uploads/inspections/insp_58_right_1773682885.webp', '2026-03-16 17:41:25'),
(321, 58, 'interior', 'uploads/inspections/insp_58_interior_1773682885.webp', '2026-03-16 17:41:25'),
(322, 58, 'odometer', 'uploads/inspections/insp_58_odometer_1773682885.webp', '2026-03-16 17:41:25'),
(323, 59, 'front', 'uploads/inspections/insp_59_front_1773683060.webp', '2026-03-16 17:44:20'),
(324, 59, 'back', 'uploads/inspections/insp_59_back_1773683060.webp', '2026-03-16 17:44:20'),
(325, 59, 'left', 'uploads/inspections/insp_59_left_1773683060.webp', '2026-03-16 17:44:20'),
(326, 59, 'right', 'uploads/inspections/insp_59_right_1773683060.webp', '2026-03-16 17:44:20'),
(327, 59, 'interior', 'uploads/inspections/insp_59_interior_1773683060.webp', '2026-03-16 17:44:20'),
(328, 59, 'odometer', 'uploads/inspections/insp_59_odometer_1773683060.webp', '2026-03-16 17:44:20'),
(329, 59, 'with_customer', 'uploads/inspections/insp_59_with_customer_1773683060.webp', '2026-03-16 17:44:20'),
(330, 60, 'front', 'uploads/inspections/insp_60_front_1773722338.webp', '2026-03-17 04:38:58'),
(331, 60, 'back', 'uploads/inspections/insp_60_back_1773722338.webp', '2026-03-17 04:38:58'),
(332, 60, 'left', 'uploads/inspections/insp_60_left_1773722338.webp', '2026-03-17 04:38:58'),
(333, 60, 'right', 'uploads/inspections/insp_60_right_1773722338.webp', '2026-03-17 04:38:58'),
(334, 60, 'interior', 'uploads/inspections/insp_60_interior_1773722338.webp', '2026-03-17 04:38:58'),
(335, 60, 'odometer', 'uploads/inspections/insp_60_odometer_1773722338.webp', '2026-03-17 04:38:58'),
(336, 60, 'with_customer', 'uploads/inspections/insp_60_with_customer_1773722338.webp', '2026-03-17 04:38:58'),
(337, 61, 'front', 'uploads/inspections/insp_61_front_1773722386.webp', '2026-03-17 04:39:46'),
(338, 61, 'back', 'uploads/inspections/insp_61_back_1773722386.webp', '2026-03-17 04:39:46'),
(339, 61, 'left', 'uploads/inspections/insp_61_left_1773722386.webp', '2026-03-17 04:39:46'),
(340, 61, 'right', 'uploads/inspections/insp_61_right_1773722386.webp', '2026-03-17 04:39:46'),
(341, 61, 'interior', 'uploads/inspections/insp_61_interior_1773722386.webp', '2026-03-17 04:39:46'),
(342, 61, 'odometer', 'uploads/inspections/insp_61_odometer_1773722386.webp', '2026-03-17 04:39:46'),
(343, 62, 'front', 'uploads/inspections/insp_62_front_1773776334.webp', '2026-03-17 19:38:54'),
(344, 62, 'back', 'uploads/inspections/insp_62_back_1773776334.webp', '2026-03-17 19:38:54'),
(345, 62, 'left', 'uploads/inspections/insp_62_left_1773776334.webp', '2026-03-17 19:38:54'),
(346, 62, 'right', 'uploads/inspections/insp_62_right_1773776334.webp', '2026-03-17 19:38:54'),
(347, 62, 'interior', 'uploads/inspections/insp_62_interior_1773776334.webp', '2026-03-17 19:38:54'),
(348, 62, 'odometer', 'uploads/inspections/insp_62_odometer_1773776334.webp', '2026-03-17 19:38:54'),
(349, 62, 'with_customer', 'uploads/inspections/insp_62_with_customer_1773776334.webp', '2026-03-17 19:38:54'),
(350, 63, 'front', 'uploads/inspections/insp_63_front_1773776723.webp', '2026-03-17 19:45:23'),
(351, 63, 'back', 'uploads/inspections/insp_63_back_1773776723.webp', '2026-03-17 19:45:23'),
(352, 63, 'left', 'uploads/inspections/insp_63_left_1773776723.webp', '2026-03-17 19:45:23'),
(353, 63, 'right', 'uploads/inspections/insp_63_right_1773776723.webp', '2026-03-17 19:45:23'),
(354, 63, 'interior', 'uploads/inspections/insp_63_interior_1773776723.webp', '2026-03-17 19:45:23'),
(355, 63, 'odometer', 'uploads/inspections/insp_63_odometer_1773776723.webp', '2026-03-17 19:45:23'),
(356, 64, 'front', 'uploads/inspections/insp_64_front_1773907159.webp', '2026-03-19 07:59:20'),
(357, 64, 'back', 'uploads/inspections/insp_64_back_1773907160.webp', '2026-03-19 07:59:20'),
(358, 64, 'left', 'uploads/inspections/insp_64_left_1773907160.webp', '2026-03-19 07:59:20'),
(359, 64, 'right', 'uploads/inspections/insp_64_right_1773907160.webp', '2026-03-19 07:59:20'),
(360, 64, 'interior', 'uploads/inspections/insp_64_interior_1773907160.webp', '2026-03-19 07:59:20'),
(361, 64, 'odometer', 'uploads/inspections/insp_64_odometer_1773907160.webp', '2026-03-19 07:59:20'),
(362, 64, 'with_customer', 'uploads/inspections/insp_64_with_customer_1773907160.webp', '2026-03-19 07:59:20'),
(363, 65, 'front', 'uploads/inspections/insp_65_front_1773910364.webp', '2026-03-19 08:52:44'),
(364, 65, 'back', 'uploads/inspections/insp_65_back_1773910364.webp', '2026-03-19 08:52:44'),
(365, 65, 'left', 'uploads/inspections/insp_65_left_1773910364.webp', '2026-03-19 08:52:44'),
(366, 65, 'right', 'uploads/inspections/insp_65_right_1773910364.webp', '2026-03-19 08:52:44'),
(367, 65, 'interior', 'uploads/inspections/insp_65_interior_1773910364.webp', '2026-03-19 08:52:44'),
(368, 65, 'odometer', 'uploads/inspections/insp_65_odometer_1773910364.webp', '2026-03-19 08:52:44'),
(369, 65, 'with_customer', 'uploads/inspections/insp_65_with_customer_1773910364.webp', '2026-03-19 08:52:44'),
(370, 66, 'front', 'uploads/inspections/insp_66_front_1774104130.webp', '2026-03-21 14:42:10'),
(371, 66, 'back', 'uploads/inspections/insp_66_back_1774104130.webp', '2026-03-21 14:42:10'),
(372, 66, 'left', 'uploads/inspections/insp_66_left_1774104130.webp', '2026-03-21 14:42:10'),
(373, 66, 'right', 'uploads/inspections/insp_66_right_1774104130.webp', '2026-03-21 14:42:11'),
(374, 66, 'interior', 'uploads/inspections/insp_66_interior_1774104131.webp', '2026-03-21 14:42:11'),
(375, 66, 'odometer', 'uploads/inspections/insp_66_odometer_1774104131.webp', '2026-03-21 14:42:11'),
(376, 67, 'front', 'uploads/inspections/insp_67_front_1774104223.webp', '2026-03-21 14:43:43'),
(377, 67, 'back', 'uploads/inspections/insp_67_back_1774104223.webp', '2026-03-21 14:43:43'),
(378, 67, 'left', 'uploads/inspections/insp_67_left_1774104223.webp', '2026-03-21 14:43:43'),
(379, 67, 'right', 'uploads/inspections/insp_67_right_1774104223.webp', '2026-03-21 14:43:43'),
(380, 67, 'interior', 'uploads/inspections/insp_67_interior_1774104223.webp', '2026-03-21 14:43:43'),
(381, 67, 'odometer', 'uploads/inspections/insp_67_odometer_1774104223.webp', '2026-03-21 14:43:43'),
(382, 68, 'front', 'uploads/inspections/insp_68_front_1774104267.webp', '2026-03-21 14:44:27'),
(383, 68, 'back', 'uploads/inspections/insp_68_back_1774104267.webp', '2026-03-21 14:44:27'),
(384, 68, 'left', 'uploads/inspections/insp_68_left_1774104267.webp', '2026-03-21 14:44:27'),
(385, 68, 'right', 'uploads/inspections/insp_68_right_1774104267.webp', '2026-03-21 14:44:27'),
(386, 68, 'interior', 'uploads/inspections/insp_68_interior_1774104267.webp', '2026-03-21 14:44:27'),
(387, 68, 'odometer', 'uploads/inspections/insp_68_odometer_1774104267.webp', '2026-03-21 14:44:27'),
(388, 69, 'front', 'uploads/inspections/insp_69_front_1774160483.webp', '2026-03-22 06:21:23'),
(389, 69, 'back', 'uploads/inspections/insp_69_back_1774160483.webp', '2026-03-22 06:21:23'),
(390, 69, 'left', 'uploads/inspections/insp_69_left_1774160483.webp', '2026-03-22 06:21:23'),
(391, 69, 'right', 'uploads/inspections/insp_69_right_1774160483.webp', '2026-03-22 06:21:23'),
(392, 69, 'interior', 'uploads/inspections/insp_69_interior_1774160483.webp', '2026-03-22 06:21:23'),
(393, 69, 'odometer', 'uploads/inspections/insp_69_odometer_1774160483.webp', '2026-03-22 06:21:23'),
(394, 69, 'with_customer', 'uploads/inspections/insp_69_with_customer_1774160483.webp', '2026-03-22 06:21:23'),
(395, 70, 'front', 'uploads/inspections/insp_70_front_1774160998.webp', '2026-03-22 06:29:58'),
(396, 70, 'back', 'uploads/inspections/insp_70_back_1774160998.webp', '2026-03-22 06:29:58'),
(397, 70, 'left', 'uploads/inspections/insp_70_left_1774160998.webp', '2026-03-22 06:29:58'),
(398, 70, 'right', 'uploads/inspections/insp_70_right_1774160998.webp', '2026-03-22 06:29:58'),
(399, 70, 'interior', 'uploads/inspections/insp_70_interior_1774160998.webp', '2026-03-22 06:29:58'),
(400, 70, 'odometer', 'uploads/inspections/insp_70_odometer_1774160998.webp', '2026-03-22 06:29:58'),
(401, 70, 'with_customer', 'uploads/inspections/insp_70_with_customer_1774160998.webp', '2026-03-22 06:29:58'),
(402, 71, 'front', 'uploads/inspections/insp_71_front_1774161699.webp', '2026-03-22 06:41:39'),
(403, 71, 'back', 'uploads/inspections/insp_71_back_1774161699.webp', '2026-03-22 06:41:39'),
(404, 71, 'left', 'uploads/inspections/insp_71_left_1774161699.webp', '2026-03-22 06:41:39'),
(405, 71, 'right', 'uploads/inspections/insp_71_right_1774161699.webp', '2026-03-22 06:41:39'),
(406, 71, 'interior', 'uploads/inspections/insp_71_interior_1774161699.webp', '2026-03-22 06:41:39'),
(407, 71, 'odometer', 'uploads/inspections/insp_71_odometer_1774161699.webp', '2026-03-22 06:41:39'),
(408, 71, 'with_customer', 'uploads/inspections/insp_71_with_customer_1774161699.webp', '2026-03-22 06:41:39'),
(409, 72, 'front', 'uploads/inspections/insp_72_front_1774162043.webp', '2026-03-22 06:47:23'),
(410, 72, 'back', 'uploads/inspections/insp_72_back_1774162043.webp', '2026-03-22 06:47:23'),
(411, 72, 'left', 'uploads/inspections/insp_72_left_1774162043.webp', '2026-03-22 06:47:23'),
(412, 72, 'right', 'uploads/inspections/insp_72_right_1774162043.webp', '2026-03-22 06:47:23'),
(413, 72, 'interior', 'uploads/inspections/insp_72_interior_1774162043.webp', '2026-03-22 06:47:23'),
(414, 72, 'odometer', 'uploads/inspections/insp_72_odometer_1774162043.webp', '2026-03-22 06:47:23'),
(415, 72, 'with_customer', 'uploads/inspections/insp_72_with_customer_1774162043.webp', '2026-03-22 06:47:23'),
(422, 74, 'front', 'uploads/inspections/insp_74_front_1774270186.png', '2026-03-23 12:49:46'),
(423, 74, 'back', 'uploads/inspections/insp_74_back_1774270186.png', '2026-03-23 12:49:46'),
(424, 74, 'left', 'uploads/inspections/insp_74_left_1774270186.png', '2026-03-23 12:49:46'),
(425, 74, 'right', 'uploads/inspections/insp_74_right_1774270186.png', '2026-03-23 12:49:46'),
(426, 74, 'interior', 'uploads/inspections/insp_74_interior_1774270186.png', '2026-03-23 12:49:46'),
(427, 74, 'odometer', 'uploads/inspections/insp_74_odometer_1774270186.png', '2026-03-23 12:49:46'),
(428, 75, 'front', 'uploads/inspections/insp_75_front_1774289085.webp', '2026-03-23 18:04:45'),
(429, 75, 'back', 'uploads/inspections/insp_75_back_1774289085.webp', '2026-03-23 18:04:45'),
(430, 75, 'left', 'uploads/inspections/insp_75_left_1774289085.webp', '2026-03-23 18:04:45'),
(431, 75, 'right', 'uploads/inspections/insp_75_right_1774289085.webp', '2026-03-23 18:04:45'),
(432, 75, 'interior', 'uploads/inspections/insp_75_interior_1774289085.webp', '2026-03-23 18:04:45'),
(433, 75, 'odometer', 'uploads/inspections/insp_75_odometer_1774289085.webp', '2026-03-23 18:04:45'),
(434, 75, 'with_customer', 'uploads/inspections/insp_75_with_customer_1774289085.webp', '2026-03-23 18:04:45'),
(435, 76, 'front', 'uploads/inspections/insp_76_front_1774289348.webp', '2026-03-23 18:09:08'),
(436, 76, 'back', 'uploads/inspections/insp_76_back_1774289348.webp', '2026-03-23 18:09:08'),
(437, 76, 'left', 'uploads/inspections/insp_76_left_1774289348.webp', '2026-03-23 18:09:08'),
(438, 76, 'right', 'uploads/inspections/insp_76_right_1774289348.webp', '2026-03-23 18:09:08'),
(439, 76, 'interior', 'uploads/inspections/insp_76_interior_1774289348.webp', '2026-03-23 18:09:08'),
(440, 76, 'odometer', 'uploads/inspections/insp_76_odometer_1774289348.webp', '2026-03-23 18:09:08'),
(441, 77, 'front', 'uploads/inspections/insp_77_front_1774289608.webp', '2026-03-23 18:13:28'),
(442, 77, 'back', 'uploads/inspections/insp_77_back_1774289608.webp', '2026-03-23 18:13:28'),
(443, 77, 'left', 'uploads/inspections/insp_77_left_1774289608.webp', '2026-03-23 18:13:28'),
(444, 77, 'right', 'uploads/inspections/insp_77_right_1774289608.webp', '2026-03-23 18:13:28'),
(445, 77, 'interior', 'uploads/inspections/insp_77_interior_1774289608.webp', '2026-03-23 18:13:28'),
(446, 77, 'odometer', 'uploads/inspections/insp_77_odometer_1774289608.webp', '2026-03-23 18:13:28'),
(447, 77, 'with_customer', 'uploads/inspections/insp_77_with_customer_1774289608.webp', '2026-03-23 18:13:28'),
(448, 78, 'front', 'uploads/inspections/insp_78_front_1774289879.webp', '2026-03-23 18:17:59'),
(449, 78, 'back', 'uploads/inspections/insp_78_back_1774289879.webp', '2026-03-23 18:17:59'),
(450, 78, 'left', 'uploads/inspections/insp_78_left_1774289879.webp', '2026-03-23 18:17:59'),
(451, 78, 'right', 'uploads/inspections/insp_78_right_1774289879.webp', '2026-03-23 18:17:59'),
(452, 78, 'interior', 'uploads/inspections/insp_78_interior_1774289879.webp', '2026-03-23 18:17:59'),
(453, 78, 'odometer', 'uploads/inspections/insp_78_odometer_1774289879.webp', '2026-03-23 18:17:59'),
(454, 79, 'front', 'uploads/inspections/insp_79_front_1774290060.webp', '2026-03-23 18:21:00'),
(455, 79, 'back', 'uploads/inspections/insp_79_back_1774290060.webp', '2026-03-23 18:21:00'),
(456, 79, 'left', 'uploads/inspections/insp_79_left_1774290060.webp', '2026-03-23 18:21:00'),
(457, 79, 'right', 'uploads/inspections/insp_79_right_1774290060.webp', '2026-03-23 18:21:00'),
(458, 79, 'interior', 'uploads/inspections/insp_79_interior_1774290060.webp', '2026-03-23 18:21:00'),
(459, 79, 'odometer', 'uploads/inspections/insp_79_odometer_1774290060.webp', '2026-03-23 18:21:00'),
(460, 79, 'with_customer', 'uploads/inspections/insp_79_with_customer_1774290060.webp', '2026-03-23 18:21:00'),
(461, 80, 'front', 'uploads/inspections/insp_80_front_1774290682.webp', '2026-03-23 18:31:22'),
(462, 80, 'back', 'uploads/inspections/insp_80_back_1774290682.webp', '2026-03-23 18:31:22'),
(463, 80, 'left', 'uploads/inspections/insp_80_left_1774290682.webp', '2026-03-23 18:31:22'),
(464, 80, 'right', 'uploads/inspections/insp_80_right_1774290682.webp', '2026-03-23 18:31:22'),
(465, 80, 'interior', 'uploads/inspections/insp_80_interior_1774290682.webp', '2026-03-23 18:31:22'),
(466, 80, 'odometer', 'uploads/inspections/insp_80_odometer_1774290682.webp', '2026-03-23 18:31:22'),
(467, 81, 'front', 'uploads/inspections/insp_81_front_1774293480.webp', '2026-03-23 19:18:00'),
(468, 81, 'back', 'uploads/inspections/insp_81_back_1774293480.webp', '2026-03-23 19:18:00'),
(469, 81, 'left', 'uploads/inspections/insp_81_left_1774293480.webp', '2026-03-23 19:18:00'),
(470, 81, 'right', 'uploads/inspections/insp_81_right_1774293480.webp', '2026-03-23 19:18:00'),
(471, 81, 'interior', 'uploads/inspections/insp_81_interior_1774293480.webp', '2026-03-23 19:18:00'),
(472, 81, 'odometer', 'uploads/inspections/insp_81_odometer_1774293480.webp', '2026-03-23 19:18:00'),
(473, 82, 'front', 'uploads/inspections/insp_82_front_1774326958.webp', '2026-03-24 04:35:58'),
(474, 82, 'back', 'uploads/inspections/insp_82_back_1774326958.webp', '2026-03-24 04:35:58'),
(475, 82, 'left', 'uploads/inspections/insp_82_left_1774326958.webp', '2026-03-24 04:35:58'),
(476, 82, 'right', 'uploads/inspections/insp_82_right_1774326958.webp', '2026-03-24 04:35:58'),
(477, 82, 'interior', 'uploads/inspections/insp_82_interior_1774326958.webp', '2026-03-24 04:35:58'),
(478, 82, 'odometer', 'uploads/inspections/insp_82_odometer_1774326958.webp', '2026-03-24 04:35:58'),
(479, 82, 'with_customer', 'uploads/inspections/insp_82_with_customer_1774326958.webp', '2026-03-24 04:35:58'),
(480, 83, 'front', 'uploads/inspections/insp_83_front_1774327017.webp', '2026-03-24 04:36:57'),
(481, 83, 'back', 'uploads/inspections/insp_83_back_1774327017.webp', '2026-03-24 04:36:57'),
(482, 83, 'left', 'uploads/inspections/insp_83_left_1774327017.webp', '2026-03-24 04:36:57'),
(483, 83, 'right', 'uploads/inspections/insp_83_right_1774327017.webp', '2026-03-24 04:36:57'),
(484, 83, 'interior', 'uploads/inspections/insp_83_interior_1774327017.webp', '2026-03-24 04:36:57'),
(485, 83, 'odometer', 'uploads/inspections/insp_83_odometer_1774327017.webp', '2026-03-24 04:36:57'),
(486, 84, 'front', 'uploads/inspections/insp_84_front_1774355896.webp', '2026-03-24 12:38:16'),
(487, 84, 'back', 'uploads/inspections/insp_84_back_1774355896.webp', '2026-03-24 12:38:16'),
(488, 84, 'left', 'uploads/inspections/insp_84_left_1774355896.webp', '2026-03-24 12:38:16'),
(489, 84, 'right', 'uploads/inspections/insp_84_right_1774355896.webp', '2026-03-24 12:38:16'),
(490, 84, 'interior', 'uploads/inspections/insp_84_interior_1774355896.webp', '2026-03-24 12:38:16'),
(491, 84, 'odometer', 'uploads/inspections/insp_84_odometer_1774355896.webp', '2026-03-24 12:38:16'),
(492, 84, 'with_customer', 'uploads/inspections/insp_84_with_customer_1774355896.webp', '2026-03-24 12:38:16'),
(493, 85, 'front', 'uploads/inspections/insp_85_front_1774356382.webp', '2026-03-24 12:46:22'),
(494, 85, 'back', 'uploads/inspections/insp_85_back_1774356382.webp', '2026-03-24 12:46:22'),
(495, 85, 'left', 'uploads/inspections/insp_85_left_1774356382.webp', '2026-03-24 12:46:22'),
(496, 85, 'right', 'uploads/inspections/insp_85_right_1774356382.webp', '2026-03-24 12:46:22'),
(497, 85, 'interior', 'uploads/inspections/insp_85_interior_1774356382.webp', '2026-03-24 12:46:22'),
(498, 85, 'odometer', 'uploads/inspections/insp_85_odometer_1774356382.webp', '2026-03-24 12:46:22'),
(499, 86, 'front', 'uploads/inspections/insp_86_front_1774359243.webp', '2026-03-24 13:34:03'),
(500, 86, 'back', 'uploads/inspections/insp_86_back_1774359243.webp', '2026-03-24 13:34:03'),
(501, 86, 'left', 'uploads/inspections/insp_86_left_1774359243.webp', '2026-03-24 13:34:03'),
(502, 86, 'right', 'uploads/inspections/insp_86_right_1774359243.webp', '2026-03-24 13:34:03'),
(503, 86, 'interior', 'uploads/inspections/insp_86_interior_1774359243.webp', '2026-03-24 13:34:03'),
(504, 86, 'odometer', 'uploads/inspections/insp_86_odometer_1774359243.webp', '2026-03-24 13:34:03'),
(505, 86, 'with_customer', 'uploads/inspections/insp_86_with_customer_1774359243.webp', '2026-03-24 13:34:03'),
(506, 87, 'front', 'uploads/inspections/insp_87_front_1774359316.webp', '2026-03-24 13:35:16'),
(507, 87, 'back', 'uploads/inspections/insp_87_back_1774359316.webp', '2026-03-24 13:35:16'),
(508, 87, 'left', 'uploads/inspections/insp_87_left_1774359316.webp', '2026-03-24 13:35:16'),
(509, 87, 'right', 'uploads/inspections/insp_87_right_1774359316.webp', '2026-03-24 13:35:16'),
(510, 87, 'interior', 'uploads/inspections/insp_87_interior_1774359316.webp', '2026-03-24 13:35:16'),
(511, 87, 'odometer', 'uploads/inspections/insp_87_odometer_1774359316.webp', '2026-03-24 13:35:16'),
(512, 88, 'front', 'uploads/inspections/insp_88_front_1774361626.webp', '2026-03-24 14:13:46'),
(513, 88, 'back', 'uploads/inspections/insp_88_back_1774361626.webp', '2026-03-24 14:13:46'),
(514, 88, 'left', 'uploads/inspections/insp_88_left_1774361626.webp', '2026-03-24 14:13:46'),
(515, 88, 'right', 'uploads/inspections/insp_88_right_1774361626.webp', '2026-03-24 14:13:46'),
(516, 88, 'odometer', 'uploads/inspections/insp_88_odometer_1774361626.webp', '2026-03-24 14:13:46'),
(517, 88, 'interior_1', 'uploads/inspections/insp_88_interior_1_1774361626.png', '2026-03-24 14:13:46'),
(518, 88, 'interior_2', 'uploads/inspections/insp_88_interior_2_1774361626.png', '2026-03-24 14:13:46'),
(519, 88, 'interior_3', 'uploads/inspections/insp_88_interior_3_1774361626.png', '2026-03-24 14:13:46'),
(520, 88, 'interior_4', 'uploads/inspections/insp_88_interior_4_1774361626.png', '2026-03-24 14:13:46'),
(521, 88, 'interior_5', 'uploads/inspections/insp_88_interior_5_1774361626.png', '2026-03-24 14:13:46');
INSERT INTO `inspection_photos` (`id`, `inspection_id`, `view_name`, `file_path`, `created_at`) VALUES
(522, 89, 'front', 'uploads/inspections/insp_89_front_1774436867.webp', '2026-03-25 11:07:47'),
(523, 89, 'back', 'uploads/inspections/insp_89_back_1774436867.webp', '2026-03-25 11:07:47'),
(524, 89, 'left', 'uploads/inspections/insp_89_left_1774436867.webp', '2026-03-25 11:07:47'),
(525, 89, 'right', 'uploads/inspections/insp_89_right_1774436867.webp', '2026-03-25 11:07:47'),
(526, 89, 'odometer', 'uploads/inspections/insp_89_odometer_1774436867.webp', '2026-03-25 11:07:47'),
(527, 89, 'with_customer', 'uploads/inspections/insp_89_with_customer_1774436867.webp', '2026-03-25 11:07:47'),
(528, 89, 'interior_1', 'uploads/inspections/insp_89_interior_1_1774436867.webp', '2026-03-25 11:07:47'),
(529, 90, 'front', 'uploads/inspections/insp_90_front_1774437332.webp', '2026-03-25 11:15:32'),
(530, 90, 'back', 'uploads/inspections/insp_90_back_1774437332.webp', '2026-03-25 11:15:32'),
(531, 90, 'left', 'uploads/inspections/insp_90_left_1774437332.webp', '2026-03-25 11:15:32'),
(532, 90, 'right', 'uploads/inspections/insp_90_right_1774437332.webp', '2026-03-25 11:15:32'),
(533, 90, 'odometer', 'uploads/inspections/insp_90_odometer_1774437332.webp', '2026-03-25 11:15:32'),
(534, 90, 'interior_1', 'uploads/inspections/insp_90_interior_1_1774437332.png', '2026-03-25 11:15:32'),
(535, 90, 'interior_2', 'uploads/inspections/insp_90_interior_2_1774437332.png', '2026-03-25 11:15:32'),
(536, 91, 'front', 'uploads/inspections/insp_91_front_1774676688.webp', '2026-03-28 05:44:48'),
(537, 91, 'back', 'uploads/inspections/insp_91_back_1774676688.webp', '2026-03-28 05:44:48'),
(538, 91, 'left', 'uploads/inspections/insp_91_left_1774676688.webp', '2026-03-28 05:44:48'),
(539, 91, 'right', 'uploads/inspections/insp_91_right_1774676688.webp', '2026-03-28 05:44:48'),
(540, 91, 'odometer', 'uploads/inspections/insp_91_odometer_1774676688.webp', '2026-03-28 05:44:48'),
(541, 91, 'interior_1', 'uploads/inspections/insp_91_interior_1_1774676688.webp', '2026-03-28 05:44:48'),
(542, 92, 'front', 'uploads/inspections/insp_92_front_1774676879.webp', '2026-03-28 05:47:59'),
(543, 92, 'back', 'uploads/inspections/insp_92_back_1774676879.webp', '2026-03-28 05:47:59'),
(544, 92, 'left', 'uploads/inspections/insp_92_left_1774676879.webp', '2026-03-28 05:47:59'),
(545, 92, 'right', 'uploads/inspections/insp_92_right_1774676879.webp', '2026-03-28 05:47:59'),
(546, 92, 'odometer', 'uploads/inspections/insp_92_odometer_1774676879.webp', '2026-03-28 05:47:59'),
(547, 92, 'interior_1', 'uploads/inspections/insp_92_interior_1_1774676879.webp', '2026-03-28 05:47:59'),
(548, 93, 'front', 'uploads/inspections/insp_93_front_1775200922.webp', '2026-04-03 07:22:02'),
(549, 93, 'back', 'uploads/inspections/insp_93_back_1775200922.webp', '2026-04-03 07:22:02'),
(550, 93, 'left', 'uploads/inspections/insp_93_left_1775200922.webp', '2026-04-03 07:22:02'),
(551, 93, 'right', 'uploads/inspections/insp_93_right_1775200922.webp', '2026-04-03 07:22:02'),
(552, 93, 'odometer', 'uploads/inspections/insp_93_odometer_1775200922.webp', '2026-04-03 07:22:02'),
(553, 93, 'with_customer', 'uploads/inspections/insp_93_with_customer_1775200922.webp', '2026-04-03 07:22:02'),
(554, 93, 'interior_1', 'uploads/inspections/insp_93_interior_1_1775200922.webp', '2026-04-03 07:22:02'),
(555, 94, 'front', 'uploads/inspections/insp_94_front_1775200986.webp', '2026-04-03 07:23:06'),
(556, 94, 'back', 'uploads/inspections/insp_94_back_1775200986.webp', '2026-04-03 07:23:06'),
(557, 94, 'left', 'uploads/inspections/insp_94_left_1775200986.webp', '2026-04-03 07:23:06'),
(558, 94, 'right', 'uploads/inspections/insp_94_right_1775200986.webp', '2026-04-03 07:23:06'),
(559, 94, 'odometer', 'uploads/inspections/insp_94_odometer_1775200986.webp', '2026-04-03 07:23:06'),
(560, 94, 'interior_1', 'uploads/inspections/insp_94_interior_1_1775200986.webp', '2026-04-03 07:23:06'),
(561, 95, 'front', 'uploads/inspections/insp_95_front_1775555083.webp', '2026-04-07 09:44:43'),
(562, 95, 'back', 'uploads/inspections/insp_95_back_1775555083.webp', '2026-04-07 09:44:43'),
(563, 95, 'left', 'uploads/inspections/insp_95_left_1775555083.png', '2026-04-07 09:44:43'),
(564, 95, 'right', 'uploads/inspections/insp_95_right_1775555083.png', '2026-04-07 09:44:43'),
(565, 95, 'odometer', 'uploads/inspections/insp_95_odometer_1775555083.png', '2026-04-07 09:44:43'),
(566, 95, 'with_customer', 'uploads/inspections/insp_95_with_customer_1775555083.png', '2026-04-07 09:44:43'),
(567, 95, 'interior_1', 'uploads/inspections/insp_95_interior_1_1775555083.png', '2026-04-07 09:44:43'),
(568, 95, 'interior_2', 'uploads/inspections/insp_95_interior_2_1775555083.jpg', '2026-04-07 09:44:43'),
(569, 96, 'front', 'uploads/inspections/insp_96_front_1775633886.webp', '2026-04-08 07:38:06'),
(570, 96, 'back', 'uploads/inspections/insp_96_back_1775633886.webp', '2026-04-08 07:38:06'),
(571, 96, 'left', 'uploads/inspections/insp_96_left_1775633886.webp', '2026-04-08 07:38:06'),
(572, 96, 'right', 'uploads/inspections/insp_96_right_1775633886.webp', '2026-04-08 07:38:06'),
(573, 96, 'odometer', 'uploads/inspections/insp_96_odometer_1775633886.webp', '2026-04-08 07:38:06'),
(574, 96, 'with_customer', 'uploads/inspections/insp_96_with_customer_1775633886.webp', '2026-04-08 07:38:06'),
(575, 96, 'interior_1', 'uploads/inspections/insp_96_interior_1_1775633886.webp', '2026-04-08 07:38:06'),
(576, 97, 'front', 'uploads/inspections/insp_97_front_1775711287.webp', '2026-04-09 05:08:07'),
(577, 97, 'back', 'uploads/inspections/insp_97_back_1775711287.webp', '2026-04-09 05:08:07'),
(578, 97, 'left', 'uploads/inspections/insp_97_left_1775711287.webp', '2026-04-09 05:08:07'),
(579, 97, 'right', 'uploads/inspections/insp_97_right_1775711287.webp', '2026-04-09 05:08:07'),
(580, 97, 'odometer', 'uploads/inspections/insp_97_odometer_1775711287.webp', '2026-04-09 05:08:07'),
(581, 97, 'interior_1', 'uploads/inspections/insp_97_interior_1_1775711287.webp', '2026-04-09 05:08:07'),
(582, 98, 'front', 'uploads/inspections/insp_98_front_1775720335.webp', '2026-04-09 07:38:55'),
(583, 98, 'back', 'uploads/inspections/insp_98_back_1775720335.webp', '2026-04-09 07:38:55'),
(584, 98, 'left', 'uploads/inspections/insp_98_left_1775720335.webp', '2026-04-09 07:38:55'),
(585, 98, 'right', 'uploads/inspections/insp_98_right_1775720335.webp', '2026-04-09 07:38:55'),
(586, 98, 'odometer', 'uploads/inspections/insp_98_odometer_1775720335.webp', '2026-04-09 07:38:55'),
(587, 98, 'interior_1', 'uploads/inspections/insp_98_interior_1_1775720335.webp', '2026-04-09 07:38:55'),
(588, 99, 'front', 'uploads/inspections/insp_99_front_1775978405.webp', '2026-04-12 07:20:05'),
(589, 99, 'back', 'uploads/inspections/insp_99_back_1775978405.webp', '2026-04-12 07:20:05'),
(590, 99, 'left', 'uploads/inspections/insp_99_left_1775978405.webp', '2026-04-12 07:20:05'),
(591, 99, 'right', 'uploads/inspections/insp_99_right_1775978405.webp', '2026-04-12 07:20:05'),
(592, 99, 'odometer', 'uploads/inspections/insp_99_odometer_1775978405.webp', '2026-04-12 07:20:05'),
(593, 99, 'with_customer', 'uploads/inspections/insp_99_with_customer_1775978405.webp', '2026-04-12 07:20:05'),
(594, 99, 'interior_1', 'uploads/inspections/insp_99_interior_1_1775978405.webp', '2026-04-12 07:20:05'),
(595, 100, 'front', 'uploads/inspections/insp_100_front_1775978545.webp', '2026-04-12 07:22:25'),
(596, 100, 'back', 'uploads/inspections/insp_100_back_1775978545.webp', '2026-04-12 07:22:25'),
(597, 100, 'left', 'uploads/inspections/insp_100_left_1775978545.webp', '2026-04-12 07:22:25'),
(598, 100, 'right', 'uploads/inspections/insp_100_right_1775978545.webp', '2026-04-12 07:22:25'),
(599, 100, 'odometer', 'uploads/inspections/insp_100_odometer_1775978545.webp', '2026-04-12 07:22:25'),
(600, 100, 'interior_1', 'uploads/inspections/insp_100_interior_1_1775978545.webp', '2026-04-12 07:22:25');

-- --------------------------------------------------------

--
-- Table structure for table `leads`
--

CREATE TABLE `leads` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `alternative_number` varchar(30) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `source` varchar(100) DEFAULT NULL,
  `inquiry_type` varchar(100) DEFAULT NULL,
  `vehicle_interest` varchar(255) DEFAULT NULL,
  `status` enum('new','contacted','interested','future','closed_won','closed_lost') NOT NULL DEFAULT 'new',
  `assigned_to` varchar(255) DEFAULT NULL,
  `assigned_staff_id` int(11) DEFAULT NULL,
  `converted_client_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `lost_reason` text DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leads`
--

INSERT INTO `leads` (`id`, `name`, `phone`, `alternative_number`, `email`, `source`, `inquiry_type`, `vehicle_interest`, `status`, `assigned_to`, `assigned_staff_id`, `converted_client_id`, `notes`, `lost_reason`, `closed_at`, `created_at`, `updated_at`) VALUES
(1, 'test', '6435627845', NULL, NULL, 'phone', 'daily', 'SUV', 'closed_won', 'Admin', 1, 2, NULL, NULL, '2026-03-05 12:00:08', '2026-03-05 06:29:55', '2026-03-05 06:30:11'),
(2, 'test2', '+971501111001', NULL, NULL, 'phone', 'daily', NULL, 'closed_won', 'Admin', 1, 3, NULL, NULL, '2026-03-05 12:01:08', '2026-03-05 06:30:53', '2026-03-05 06:31:12'),
(3, 'hamad', '6435627845', NULL, 'hamadmf100@gmail.com', 'phone', 'daily', 'SUV', 'closed_lost', 'Admin', 1, NULL, NULL, 'Auto-closed after 4 follow-ups', NULL, '2026-03-06 13:50:22', '2026-03-06 14:08:35'),
(5, 'test', '8129706305', NULL, 'test@gmail.com', 'phone', 'daily', NULL, 'new', 'Admin', 1, NULL, NULL, NULL, NULL, '2026-03-06 14:15:56', '2026-03-06 15:53:25'),
(6, 'added', '1212121212', '3434343434', 'sdds@gmail.com', 'phone', 'daily', 'sddf', 'closed_lost', 'Admin', 1, NULL, NULL, 'manual', NULL, '2026-03-07 18:37:37', '2026-03-17 06:32:29'),
(7, 'sd', '3434343434', NULL, 'sds@gmail.com', 'phone', 'daily', '11', 'closed_lost', 'Admin', 1, NULL, NULL, 'Auto-closed after 3 follow-ups', NULL, '2026-03-07 18:38:01', '2026-03-17 04:54:45'),
(8, 'today8', '1212121212', NULL, 'today@gmail.com', 'phone', 'daily', 'SUV', 'closed_won', 'Admin', 1, NULL, NULL, NULL, '2026-03-13 22:46:35', '2026-03-07 19:22:20', '2026-03-13 17:16:35'),
(9, 'hamad', '6235646799', '3434343434', 'hamadmf45@gmail.com', 'phone', 'weekly', 'df', 'closed_lost', 'Admin', 1, NULL, NULL, 'Auto-closed after 4 follow-ups', NULL, '2026-03-13 10:36:29', '2026-03-13 10:37:42'),
(10, 'hamad', '6235646792', NULL, 'hamadmf100@gmail.com', 'phone', 'daily', 'SUV', 'closed_won', 'Admin', 1, 14, NULL, NULL, '2026-03-13 22:58:32', '2026-03-13 17:28:22', '2026-03-13 17:29:07'),
(11, 'wed', '1122334455', NULL, 'wed@gmail.com', 'phone', 'wedding_rental', 'SUV', 'new', 'Admin', 1, NULL, NULL, NULL, NULL, '2026-03-17 09:51:09', '2026-03-17 09:51:09'),
(12, 'testing time', '5673456783', NULL, 'testingtime@gmail.com', 'phone', 'daily', 'SUV', 'new', 'Admin', 1, NULL, NULL, NULL, NULL, '2026-03-26 04:34:02', '2026-03-26 04:34:02'),
(13, 'testin interested', '2345237744', '2345237744', 'testinginterested@gmail.com', 'phone', 'daily', 'SUV', 'interested', 'Admin', 1, NULL, 'sdsd', NULL, NULL, '2026-04-09 05:43:04', '2026-04-09 05:43:04');

-- --------------------------------------------------------

--
-- Table structure for table `lead_activities`
--

CREATE TABLE `lead_activities` (
  `id` int(11) NOT NULL,
  `lead_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `type` varchar(50) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lead_activities`
--

INSERT INTO `lead_activities` (`id`, `lead_id`, `user_id`, `type`, `note`, `created_at`) VALUES
(1, 1, NULL, NULL, 'Lead created with status: new.', '2026-03-05 06:29:55'),
(2, 1, NULL, NULL, 'Follow-up scheduled: Call on 05 Mar 2026, 10:00 AM.', '2026-03-05 06:30:00'),
(3, 1, NULL, NULL, 'Follow-up marked as done.', '2026-03-05 06:30:01'),
(4, 1, NULL, NULL, 'Status updated to: contacted.', '2026-03-05 06:30:04'),
(5, 1, NULL, NULL, 'Status updated to: interested.', '2026-03-05 06:30:06'),
(6, 1, NULL, NULL, 'Status updated to: closed won.', '2026-03-05 06:30:08'),
(7, 1, NULL, NULL, 'Lead linked to existing client #2.', '2026-03-05 06:30:11'),
(8, 2, NULL, NULL, 'Lead created with status: new.', '2026-03-05 06:30:53'),
(9, 2, NULL, NULL, 'Follow-up scheduled: Call on 05 Mar 2026, 10:00 AM.', '2026-03-05 06:30:56'),
(10, 2, NULL, NULL, 'Follow-up marked as done.', '2026-03-05 06:30:57'),
(11, 2, NULL, NULL, 'Status updated to: contacted.', '2026-03-05 06:31:02'),
(12, 2, NULL, NULL, 'Status updated to: interested.', '2026-03-05 06:31:04'),
(13, 2, NULL, NULL, 'Status updated to: future.', '2026-03-05 06:31:06'),
(14, 2, NULL, NULL, 'Status updated to: closed won.', '2026-03-05 06:31:08'),
(15, 2, NULL, NULL, 'Lead converted to client #3.', '2026-03-05 06:31:12'),
(16, 3, NULL, NULL, 'Lead created with status: new.', '2026-03-06 13:50:22'),
(17, 3, NULL, NULL, 'Follow-up scheduled: Call on 06 Mar 2026, 08:00 PM.', '2026-03-06 14:03:03'),
(18, 3, NULL, NULL, 'Follow-up marked as done.', '2026-03-06 14:07:46'),
(19, 3, NULL, NULL, 'Follow-up scheduled: Call on 06 Mar 2026, 08:30 PM.', '2026-03-06 14:08:06'),
(20, 3, NULL, NULL, 'Follow-up marked as done.', '2026-03-06 14:08:09'),
(21, 3, NULL, NULL, 'Follow-up scheduled: Call on 06 Mar 2026, 09:00 PM.', '2026-03-06 14:08:17'),
(22, 3, NULL, NULL, 'Follow-up marked as done.', '2026-03-06 14:08:20'),
(23, 3, NULL, NULL, 'Follow-up scheduled: Call on 06 Mar 2026, 10:00 PM.', '2026-03-06 14:08:26'),
(24, 3, NULL, NULL, 'Status updated to: contacted.', '2026-03-06 14:08:32'),
(25, 3, NULL, NULL, 'Follow-up marked as done.', '2026-03-06 14:08:35'),
(26, 3, NULL, NULL, 'Lead auto-closed to Lost after 4 follow-ups.', '2026-03-06 14:08:35'),
(38, 5, NULL, NULL, 'Lead created with status: new.', '2026-03-06 14:15:56'),
(39, 5, NULL, NULL, 'Follow-up scheduled: Call on 06 Mar 2026, 08:00 PM.', '2026-03-06 14:16:03'),
(40, 5, NULL, NULL, 'Follow-up scheduled: Call on 06 Mar 2026, 08:30 PM.', '2026-03-06 14:16:18'),
(41, 5, NULL, NULL, 'Follow-up scheduled: Call on 06 Mar 2026, 08:45 PM.', '2026-03-06 14:16:27'),
(42, 5, NULL, NULL, 'Follow-up scheduled: Call on 06 Mar 2026, 09:00 PM.', '2026-03-06 14:16:35'),
(43, 5, NULL, NULL, 'Follow-up marked as done.', '2026-03-06 14:16:38'),
(44, 5, NULL, NULL, 'Follow-up marked as done.', '2026-03-06 14:16:40'),
(45, 5, NULL, NULL, 'Follow-up marked as done.', '2026-03-06 14:16:42'),
(46, 5, NULL, NULL, 'Follow-up marked as done.', '2026-03-06 14:16:43'),
(47, 5, NULL, NULL, 'Follow-up scheduled: Call on 06 Mar 2026, 10:00 PM.', '2026-03-06 14:20:46'),
(48, 5, NULL, NULL, 'Follow-up marked as done.', '2026-03-06 14:20:50'),
(49, 5, NULL, NULL, 'Lead details updated.', '2026-03-06 15:53:25'),
(50, 6, NULL, NULL, 'Lead created with status: new.', '2026-03-07 18:37:37'),
(51, 7, NULL, NULL, 'Lead created with status: new.', '2026-03-07 18:38:01'),
(52, 8, NULL, NULL, 'Lead created with status: new.', '2026-03-07 19:22:20'),
(53, 8, NULL, NULL, 'Follow-up scheduled: Call on 14 Mar 2026, 10:00 AM.', '2026-03-13 10:35:44'),
(54, 8, NULL, NULL, 'Follow-up marked as done.', '2026-03-13 10:35:46'),
(55, 9, NULL, NULL, 'Lead created with status: new.', '2026-03-13 10:36:29'),
(56, 9, NULL, NULL, 'Follow-up scheduled: Call on 14 Mar 2026, 09:00 AM.', '2026-03-13 10:37:10'),
(57, 9, NULL, NULL, 'Follow-up marked as done.', '2026-03-13 10:37:13'),
(58, 9, NULL, NULL, 'Follow-up scheduled: Call on 14 Mar 2026, 09:30 AM.', '2026-03-13 10:37:22'),
(59, 9, NULL, NULL, 'Follow-up marked as done.', '2026-03-13 10:37:24'),
(60, 9, NULL, NULL, 'Follow-up scheduled: Call on 14 Mar 2026, 10:00 AM.', '2026-03-13 10:37:29'),
(61, 9, NULL, NULL, 'Follow-up marked as done.', '2026-03-13 10:37:32'),
(62, 9, NULL, NULL, 'Follow-up scheduled: Call on 14 Mar 2026, 10:30 AM.', '2026-03-13 10:37:40'),
(63, 9, NULL, NULL, 'Follow-up marked as done.', '2026-03-13 10:37:42'),
(64, 9, NULL, NULL, 'Lead auto-closed to Lost after 4 follow-ups.', '2026-03-13 10:37:42'),
(65, 8, NULL, NULL, 'Status updated to: contacted.', '2026-03-13 17:16:32'),
(66, 8, NULL, NULL, 'Status updated to: interested.', '2026-03-13 17:16:33'),
(67, 8, NULL, NULL, 'Status updated to: closed won.', '2026-03-13 17:16:35'),
(68, 10, NULL, NULL, 'Lead created with status: new.', '2026-03-13 17:28:22'),
(69, 10, NULL, NULL, 'Status updated to: contacted.', '2026-03-13 17:28:28'),
(70, 10, NULL, NULL, 'Status updated to: interested.', '2026-03-13 17:28:29'),
(71, 10, NULL, NULL, 'Status updated to: closed won.', '2026-03-13 17:28:32'),
(72, 10, NULL, NULL, 'Lead converted to client #14.', '2026-03-13 17:29:07'),
(73, 7, NULL, NULL, 'Follow-up scheduled: Call on 18 Mar 2026, 10:00 AM.', '2026-03-17 04:53:55'),
(74, 7, NULL, NULL, 'Follow-up marked as done.', '2026-03-17 04:54:00'),
(75, 7, NULL, NULL, 'Follow-up scheduled: Call on 18 Mar 2026, 11:00 AM.', '2026-03-17 04:54:08'),
(76, 7, NULL, NULL, 'Follow-up marked as done.', '2026-03-17 04:54:11'),
(77, 7, NULL, NULL, 'Follow-up scheduled: Call on 18 Mar 2026, 11:15 AM.', '2026-03-17 04:54:43'),
(78, 7, NULL, NULL, 'Follow-up marked as done.', '2026-03-17 04:54:45'),
(79, 7, NULL, NULL, 'Lead auto-closed to Lost after 3 follow-ups.', '2026-03-17 04:54:45'),
(80, 6, NULL, NULL, 'Status updated to: closed lost. Lost reason: manual', '2026-03-17 06:32:29'),
(81, 11, NULL, NULL, 'Lead created with status: new.', '2026-03-17 09:51:09'),
(82, 11, NULL, NULL, 'Follow-up scheduled: Call on 17 Mar 2026, 07:00 PM.', '2026-03-17 10:26:09'),
(83, 11, NULL, NULL, 'Follow-up scheduled: Call on 18 Mar 2026, 10:00 AM.', '2026-03-17 10:43:27'),
(84, 12, NULL, NULL, 'Lead created with status: new.', '2026-03-26 04:34:02'),
(85, 13, NULL, NULL, 'Lead created with status: interested.', '2026-04-09 05:43:04'),
(86, 11, NULL, NULL, 'Follow-up marked as done.', '2026-04-10 10:04:22'),
(87, 11, NULL, NULL, 'Follow-up marked as done.', '2026-04-10 10:04:31');

-- --------------------------------------------------------

--
-- Table structure for table `lead_followups`
--

CREATE TABLE `lead_followups` (
  `id` int(11) NOT NULL,
  `lead_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `type` varchar(50) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `scheduled_at` datetime DEFAULT NULL,
  `is_done` tinyint(1) NOT NULL DEFAULT 0,
  `done_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lead_followups`
--

INSERT INTO `lead_followups` (`id`, `lead_id`, `user_id`, `type`, `note`, `notes`, `scheduled_at`, `is_done`, `done_at`, `created_at`) VALUES
(1, 1, NULL, 'call', NULL, NULL, '2026-03-05 10:00:00', 1, NULL, '2026-03-05 06:30:00'),
(2, 2, NULL, 'call', NULL, NULL, '2026-03-05 10:00:00', 1, NULL, '2026-03-05 06:30:56'),
(3, 3, NULL, 'call', NULL, 'sdsd', '2026-03-06 20:00:00', 1, '2026-03-06 19:37:46', '2026-03-06 14:03:03'),
(4, 3, NULL, 'call', NULL, NULL, '2026-03-06 20:30:00', 1, '2026-03-06 19:38:09', '2026-03-06 14:08:06'),
(5, 3, NULL, 'call', NULL, NULL, '2026-03-06 21:00:00', 1, '2026-03-06 19:38:20', '2026-03-06 14:08:17'),
(6, 3, NULL, 'call', NULL, NULL, '2026-03-06 22:00:00', 1, '2026-03-06 19:38:35', '2026-03-06 14:08:26'),
(12, 5, NULL, 'call', NULL, NULL, '2026-03-06 20:00:00', 1, '2026-03-06 19:46:38', '2026-03-06 14:16:03'),
(13, 5, NULL, 'call', NULL, NULL, '2026-03-06 20:30:00', 1, '2026-03-06 19:46:40', '2026-03-06 14:16:18'),
(14, 5, NULL, 'call', NULL, NULL, '2026-03-06 20:45:00', 1, '2026-03-06 19:46:42', '2026-03-06 14:16:27'),
(15, 5, NULL, 'call', NULL, NULL, '2026-03-06 21:00:00', 1, '2026-03-06 19:46:43', '2026-03-06 14:16:35'),
(16, 5, NULL, 'call', NULL, NULL, '2026-03-06 22:00:00', 1, '2026-03-06 19:50:50', '2026-03-06 14:20:46'),
(17, 8, NULL, 'call', NULL, NULL, '2026-03-14 10:00:00', 1, '2026-03-13 16:05:46', '2026-03-13 10:35:44'),
(18, 9, NULL, 'call', NULL, NULL, '2026-03-14 09:00:00', 1, '2026-03-13 16:07:13', '2026-03-13 10:37:10'),
(19, 9, NULL, 'call', NULL, NULL, '2026-03-14 09:30:00', 1, '2026-03-13 16:07:24', '2026-03-13 10:37:22'),
(20, 9, NULL, 'call', NULL, NULL, '2026-03-14 10:00:00', 1, '2026-03-13 16:07:32', '2026-03-13 10:37:29'),
(21, 9, NULL, 'call', NULL, NULL, '2026-03-14 10:30:00', 1, '2026-03-13 16:07:42', '2026-03-13 10:37:40'),
(22, 7, NULL, 'call', NULL, NULL, '2026-03-18 10:00:00', 1, '2026-03-17 10:24:00', '2026-03-17 04:53:55'),
(23, 7, NULL, 'call', NULL, NULL, '2026-03-18 11:00:00', 1, '2026-03-17 10:24:11', '2026-03-17 04:54:08'),
(24, 7, NULL, 'call', NULL, NULL, '2026-03-18 11:15:00', 1, '2026-03-17 10:24:45', '2026-03-17 04:54:43'),
(25, 11, NULL, 'call', NULL, NULL, '2026-03-17 19:00:00', 1, '2026-04-10 15:34:22', '2026-03-17 10:26:09'),
(26, 11, NULL, 'call', NULL, NULL, '2026-03-18 10:00:00', 1, '2026-04-10 15:34:31', '2026-03-17 10:43:27');

-- --------------------------------------------------------

--
-- Table structure for table `ledger_entries`
--

CREATE TABLE `ledger_entries` (
  `id` int(11) NOT NULL,
  `txn_type` enum('income','expense','adjustment') NOT NULL DEFAULT 'income',
  `category` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `payment_mode` varchar(20) DEFAULT NULL,
  `bank_account_id` int(11) DEFAULT NULL,
  `source_type` varchar(50) NOT NULL DEFAULT 'manual',
  `source_id` int(11) DEFAULT NULL,
  `source_event` varchar(50) DEFAULT NULL,
  `is_legacy_payment` tinyint(1) DEFAULT 0,
  `idempotency_key` varchar(120) DEFAULT NULL,
  `posted_at` datetime DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `voided_at` datetime DEFAULT NULL,
  `voided_by` int(11) DEFAULT NULL,
  `void_reason` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ledger_entries`
--

INSERT INTO `ledger_entries` (`id`, `txn_type`, `category`, `description`, `amount`, `payment_mode`, `bank_account_id`, `source_type`, `source_id`, `source_event`, `is_legacy_payment`, `idempotency_key`, `posted_at`, `created_by`, `created_at`, `voided_at`, `voided_by`, `void_reason`) VALUES
(1, 'income', 'Reservation Delivery', 'Reservation #2  ” Delivery payment', 1000.00, 'account', 1, 'reservation', 2, 'delivery', 0, 'reservation:delivery:2', '2026-03-05 10:38:47', 1, '2026-03-05 05:08:47', NULL, NULL, NULL),
(2, 'income', 'Reservation Delivery', 'Reservation #1  ” Delivery payment', 6.00, 'account', 1, 'reservation', 1, 'delivery', 0, 'reservation:delivery:1', '2026-03-05 10:39:10', 1, '2026-03-05 05:09:10', NULL, NULL, NULL),
(3, 'income', 'test', '', 4000.00, 'account', 1, 'manual', NULL, 'manual', 0, NULL, '2026-03-05 21:14:05', 1, '2026-03-05 15:44:05', NULL, NULL, NULL),
(4, 'expense', 'Investment Down Payment', 'Down payment for: test', 1000.00, 'account', 1, 'emi_investment', 1, 'down_payment', 0, 'inv_dp_1', '2026-03-05 21:14:37', 1, '2026-03-05 15:44:37', NULL, NULL, NULL),
(5, 'expense', 'EMI Payment', 'EMI #1 for: test', 1000.00, 'account', 1, 'emi_schedule', 1, 'emi_paid', 0, 'emi_pay_1', '2026-03-05 00:00:00', 1, '2026-03-05 15:45:02', NULL, NULL, NULL),
(6, 'income', 'test', '', 4.00, 'account', 1, 'manual', NULL, 'manual', 0, NULL, '2026-03-06 10:07:26', 1, '2026-03-06 04:37:26', NULL, NULL, NULL),
(7, 'income', 'test', '', 90.00, 'account', 1, 'manual', NULL, 'manual', 0, NULL, '2026-03-06 10:19:41', 1, '2026-03-06 04:49:41', NULL, NULL, NULL),
(8, 'expense', 'Manual Expense', '', 100.00, 'account', 1, 'manual', NULL, 'manual', 0, NULL, '2026-03-06 10:20:05', 1, '2026-03-06 04:50:05', NULL, NULL, NULL),
(9, 'income', 'Reservation Return', 'Reservation #2  ” Return payment', 700.00, 'credit', NULL, 'reservation', 2, 'return', 0, 'reservation:return:2', '2026-03-06 13:47:24', 1, '2026-03-06 08:17:24', NULL, NULL, NULL),
(10, 'income', 'Reservation Return', 'Reservation #1  ” Return payment', 5000.00, 'cash', NULL, 'reservation', 1, 'return', 0, 'reservation:return:1', '2026-03-06 13:48:32', 1, '2026-03-06 08:18:32', NULL, NULL, NULL),
(11, 'income', 'Reservation Delivery', 'Reservation #3  ” Delivery payment', 650.00, 'credit', NULL, 'reservation', 3, 'delivery', 0, 'reservation:delivery:3', '2026-03-06 14:15:22', 1, '2026-03-06 08:45:22', NULL, NULL, NULL),
(19, 'income', 'Reservation Delivery', 'Reservation #4  ” Delivery payment', 3000.00, 'cash', NULL, 'reservation', 4, 'delivery', 0, 'reservation:delivery:4', '2026-03-07 11:15:54', 1, '2026-03-07 05:45:54', NULL, NULL, NULL),
(20, 'income', 'Reservation Delivery', 'Reservation #5  ” Delivery payment', 281.00, 'cash', NULL, 'reservation', 5, 'delivery', 0, 'reservation:delivery:5', '2026-03-07 11:16:27', 1, '2026-03-07 05:46:27', NULL, NULL, NULL),
(21, 'expense', 'Vehicle Expense', 'bmw m4 - Service: sdsd', 5678.00, 'account', 1, 'vehicle_expense', 2, 'Service', 0, NULL, '2026-03-07 23:20:02', 1, '2026-03-07 17:50:02', NULL, NULL, NULL),
(22, 'income', 'Reservation Delivery', 'Reservation #6  ” Delivery payment', 1650.00, 'cash', NULL, 'reservation', 6, 'delivery', 0, 'reservation:delivery:6', '2026-03-07 23:32:55', 1, '2026-03-07 18:02:55', NULL, NULL, NULL),
(23, 'income', 'Reservation Delivery', 'Reservation #7 - Delivery payment (Cash)', 300.00, 'cash', NULL, 'reservation', 7, 'delivery', 0, 'reservation:delivery:7:cash', '2026-03-07 23:59:39', 1, '2026-03-07 18:29:39', NULL, NULL, NULL),
(24, 'income', 'Reservation Delivery', 'Reservation #7 - Delivery payment (Credit)', 300.00, 'credit', NULL, 'reservation', 7, 'delivery', 0, 'reservation:delivery:7:credit', '2026-03-07 23:59:39', 1, '2026-03-07 18:29:39', NULL, NULL, NULL),
(25, 'income', 'Reservation Delivery', 'Reservation #7 - Delivery payment (Account)', 100.00, 'account', 1, 'reservation', 7, 'delivery', 0, 'reservation:delivery:7:account', '2026-03-07 23:59:39', 1, '2026-03-07 18:29:39', NULL, NULL, NULL),
(26, 'income', 'Reservation Return', 'Reservation #7  ” Return payment', 1000.00, 'cash', NULL, 'reservation', 7, 'return', 0, 'reservation:return:7', '2026-03-08 00:22:07', 1, '2026-03-07 18:52:07', NULL, NULL, NULL),
(27, 'income', 'Reservation Delivery', 'Reservation #8 - Delivery payment (Cash)', 450.00, 'cash', NULL, 'reservation', 8, 'delivery', 0, 'reservation:delivery:8:cash', '2026-03-08 00:27:32', 1, '2026-03-07 18:57:32', NULL, NULL, NULL),
(28, 'income', 'Reservation Delivery', 'Reservation #8 - Delivery payment (Credit)', 450.00, 'credit', NULL, 'reservation', 8, 'delivery', 0, 'reservation:delivery:8:credit', '2026-03-08 00:27:32', 1, '2026-03-07 18:57:32', NULL, NULL, NULL),
(29, 'income', 'Reservation Delivery', 'Reservation #8 - Delivery payment (Account)', 1000.00, 'account', 1, 'reservation', 8, 'delivery', 0, 'reservation:delivery:8:account', '2026-03-08 00:27:32', 1, '2026-03-07 18:57:32', NULL, NULL, NULL),
(30, 'income', 'Reservation Delivery', 'Reservation #9 - Delivery payment (Cash)', 300.00, 'cash', NULL, 'reservation', 9, 'delivery', 0, 'reservation:delivery:9:cash', '2026-03-08 00:42:13', 1, '2026-03-07 19:12:13', NULL, NULL, NULL),
(31, 'income', 'Reservation Delivery', 'Reservation #9 - Delivery payment (Credit)', 300.00, 'credit', NULL, 'reservation', 9, 'delivery', 0, 'reservation:delivery:9:credit', '2026-03-08 00:42:13', 1, '2026-03-07 19:12:13', NULL, NULL, NULL),
(32, 'income', 'Reservation Delivery', 'Reservation #9 - Delivery payment (Account)', 300.00, 'account', 1, 'reservation', 9, 'delivery', 0, 'reservation:delivery:9:account', '2026-03-08 00:42:13', 1, '2026-03-07 19:12:13', NULL, NULL, NULL),
(33, 'income', 'Reservation Return', 'Reservation #9  ” Return payment', 600.00, 'cash', NULL, 'reservation', 9, 'return', 0, 'reservation:return:9', '2026-03-08 00:44:00', 1, '2026-03-07 19:14:00', NULL, NULL, NULL),
(34, 'income', 'Reservation Delivery', 'Reservation #10 - Delivery payment (Cash)', 200.00, 'cash', NULL, 'reservation', 10, 'delivery', 0, 'reservation:delivery:10:cash', '2026-03-08 00:48:48', 1, '2026-03-07 19:18:48', NULL, NULL, NULL),
(35, 'income', 'Reservation Delivery', 'Reservation #10 - Delivery payment (Credit)', 200.00, 'credit', NULL, 'reservation', 10, 'delivery', 0, 'reservation:delivery:10:credit', '2026-03-08 00:48:48', 1, '2026-03-07 19:18:48', NULL, NULL, NULL),
(36, 'income', 'Reservation Delivery', 'Reservation #10 - Delivery payment (Account)', 200.00, 'account', 1, 'reservation', 10, 'delivery', 0, 'reservation:delivery:10:account', '2026-03-08 00:48:48', 1, '2026-03-07 19:18:48', NULL, NULL, NULL),
(37, 'income', 'Reservation Delivery', 'Reservation #11 - Delivery payment (Cash)', 100.00, 'cash', NULL, 'reservation', 11, 'delivery', 0, 'reservation:delivery:11:cash', '2026-03-08 01:07:51', 1, '2026-03-07 19:37:51', NULL, NULL, NULL),
(38, 'income', 'Reservation Delivery', 'Reservation #11 - Delivery payment (Credit)', 300.00, 'credit', NULL, 'reservation', 11, 'delivery', 0, 'reservation:delivery:11:credit', '2026-03-08 01:07:51', 1, '2026-03-07 19:37:51', NULL, NULL, NULL),
(39, 'income', 'Reservation Delivery', 'Reservation #11 - Delivery payment (Account)', 100.00, 'account', 1, 'reservation', 11, 'delivery', 0, 'reservation:delivery:11:account', '2026-03-08 01:07:51', 1, '2026-03-07 19:37:51', NULL, NULL, NULL),
(40, 'income', 'Reservation Return', 'Reservation #11 - Return payment (Cash)', 500.00, 'cash', NULL, 'reservation', 11, 'return', 0, 'reservation:return:11:cash', '2026-03-08 01:09:30', 1, '2026-03-07 19:39:30', NULL, NULL, NULL),
(41, 'income', 'Reservation Return', 'Reservation #11 - Return payment (Credit)', 400.00, 'credit', NULL, 'reservation', 11, 'return', 0, 'reservation:return:11:credit', '2026-03-08 01:09:30', 1, '2026-03-07 19:39:30', NULL, NULL, NULL),
(42, 'income', 'Reservation Return', 'Reservation #11 - Return payment (Account)', 100.00, 'account', 1, 'reservation', 11, 'return', 0, 'reservation:return:11:account', '2026-03-08 01:09:30', 1, '2026-03-07 19:39:30', NULL, NULL, NULL),
(43, 'income', 'Reservation Delivery', 'Reservation #13  ” Delivery payment', 555.00, 'cash', NULL, 'reservation', 13, 'delivery', 0, 'reservation:delivery:13', '2026-03-08 01:43:20', 1, '2026-03-07 20:13:20', NULL, NULL, NULL),
(44, 'income', 'Reservation Delivery', 'Reservation #14  ” Delivery payment', 800.00, 'cash', NULL, 'reservation', 14, 'delivery', 0, 'reservation:delivery:14', '2026-03-08 01:56:39', 1, '2026-03-07 20:26:39', NULL, NULL, NULL),
(45, 'income', 'Security Deposit Collected', 'Reservation #14 - Security deposit collected', 120.00, 'account', 1, 'reservation', 14, 'security_deposit_in', 0, 'reservation:security_deposit_in:14', '2026-03-08 01:56:39', 1, '2026-03-07 20:26:39', NULL, NULL, NULL),
(46, 'expense', 'Security Deposit Returned', 'Reservation #14 - Security deposit returned', 120.00, 'account', 1, 'reservation', 14, 'security_deposit_out', 0, 'reservation:security_deposit_out:14', '2026-03-08 01:57:53', 1, '2026-03-07 20:27:53', NULL, NULL, NULL),
(47, 'income', 'Reservation Delivery', 'Reservation #15  ” Delivery payment', 30.00, 'cash', NULL, 'reservation', 15, 'delivery', 0, 'reservation:delivery:15', '2026-03-08 21:40:56', 1, '2026-03-08 16:10:56', NULL, NULL, NULL),
(48, 'income', 'Security Deposit Collected', 'Reservation #15 - Security deposit collected', 4.50, 'account', 1, 'reservation', 15, 'security_deposit_in', 0, 'reservation:security_deposit_in:15', '2026-03-08 21:40:56', 1, '2026-03-08 16:10:56', NULL, NULL, NULL),
(49, 'expense', 'Security Deposit Returned', 'Reservation #15 - Security deposit returned', 4.50, 'account', 1, 'reservation', 15, 'security_deposit_out', 0, 'reservation:security_deposit_out:15', '2026-03-08 21:43:08', 1, '2026-03-08 16:13:08', NULL, NULL, NULL),
(50, 'income', 'Reservation Delivery', 'Reservation #16  ” Delivery payment', 1443.00, 'cash', NULL, 'reservation', 16, 'delivery', 0, 'reservation:delivery:16', '2026-03-08 21:46:04', 1, '2026-03-08 16:16:04', NULL, NULL, NULL),
(51, 'income', 'Security Deposit Collected', 'Reservation #16 - Security deposit collected', 216.45, 'account', 1, 'reservation', 16, 'security_deposit_in', 0, 'reservation:security_deposit_in:16', '2026-03-08 21:46:04', 1, '2026-03-08 16:16:04', NULL, NULL, NULL),
(52, 'expense', 'Security Deposit Returned', 'Reservation #16 - Security deposit returned', 216.45, 'account', 1, 'reservation', 16, 'security_deposit_out', 0, 'reservation:security_deposit_out:16', '2026-03-08 21:46:41', 1, '2026-03-08 16:16:41', NULL, NULL, NULL),
(53, 'income', 'Reservation Delivery', 'Reservation #17  ” Delivery payment', 28638.00, 'cash', NULL, 'reservation', 17, 'delivery', 0, 'reservation:delivery:17', '2026-03-08 22:30:07', 1, '2026-03-08 17:00:07', NULL, NULL, NULL),
(54, 'income', 'Security Deposit Collected', 'Reservation #17 - Security deposit collected', 4295.70, 'account', 1, 'reservation', 17, 'security_deposit_in', 0, 'reservation:security_deposit_in:17', '2026-03-08 22:30:07', 1, '2026-03-08 17:00:07', NULL, NULL, NULL),
(55, 'expense', 'Security Deposit Returned', 'Reservation #17 - Security deposit returned', 4295.70, 'account', 1, 'reservation', 17, 'security_deposit_out', 0, 'reservation:security_deposit_out:17', '2026-03-08 22:30:57', 1, '2026-03-08 17:00:57', NULL, NULL, NULL),
(61, 'expense', 'Vehicle Expense', 'sdsd S-Class S 350d - Fuel: ds', 12.00, 'cash', NULL, 'vehicle_expense', 6, 'Fuel', 0, NULL, '2026-03-09 13:53:24', 1, '2026-03-09 08:23:24', NULL, NULL, NULL),
(62, 'expense', 'Manual Expense', '', 12.00, 'cash', NULL, 'manual', NULL, 'manual', 0, NULL, '2026-03-09 14:13:44', 1, '2026-03-09 08:43:44', NULL, NULL, NULL),
(63, 'expense', 'Manual Expense', '', 567.00, 'cash', NULL, 'manual', NULL, 'manual', 0, NULL, '2026-03-09 14:23:59', 1, '2026-03-09 08:53:59', NULL, NULL, NULL),
(64, 'expense', 'Garage Cleaning', '', 12.00, 'account', 1, 'manual', NULL, 'manual', 0, NULL, '2026-03-09 14:42:39', 1, '2026-03-09 09:12:39', NULL, NULL, NULL),
(65, 'expense', 'Fuel', '', 1000.00, 'cash', NULL, 'manual', NULL, 'manual', 0, NULL, '2026-03-09 14:49:21', 1, '2026-03-09 09:19:21', NULL, NULL, NULL),
(68, 'expense', 'Credit Payment Settled', 'Credit payment settled via Cash - testing credit to cash', 1300.00, 'credit', NULL, 'manual', NULL, 'credit_payment_settlement', 1, NULL, '2026-03-10 00:06:01', 1, '2026-03-09 18:36:01', NULL, NULL, NULL),
(69, 'income', 'Credit Payment Received', 'Payment received against credit via Cash - testing credit to cash', 1300.00, 'cash', NULL, 'manual', NULL, 'credit_payment_received', 0, NULL, '2026-03-10 00:06:01', 1, '2026-03-09 18:36:01', NULL, NULL, NULL),
(70, 'expense', 'Credit Payment Settled', 'Credit payment settled via Bank', 400.00, 'credit', NULL, 'manual', NULL, 'credit_payment_settlement', 1, NULL, '2026-03-10 00:31:44', 1, '2026-03-09 19:01:44', NULL, NULL, NULL),
(71, 'income', 'Credit Payment Received', 'Payment received against credit via Bank', 400.00, 'account', 1, 'manual', NULL, 'credit_payment_received', 0, NULL, '2026-03-10 00:31:44', 1, '2026-03-09 19:01:44', NULL, NULL, NULL),
(72, 'income', 'Reservation Return', 'Reservation #5  ” Return payment', 2009.00, 'account', 1, 'reservation', 5, 'return', 0, 'reservation:return:5', '2026-03-10 00:51:25', 1, '2026-03-09 19:21:25', NULL, NULL, NULL),
(73, 'income', 'Reservation Return', 'Reservation #4  ” Return payment', 1500.00, 'account', 2, 'reservation', 4, 'return', 0, 'reservation:return:4', '2026-03-10 00:52:43', 1, '2026-03-09 19:22:43', NULL, NULL, NULL),
(74, 'income', 'Reservation Delivery', 'Reservation #18  ” Delivery payment', 73.00, 'cash', NULL, 'reservation', 18, 'delivery', 0, 'reservation:delivery:18', '2026-03-10 00:53:35', 1, '2026-03-09 19:23:35', NULL, NULL, NULL),
(75, 'income', 'Security Deposit Collected', 'Reservation #18 - Security deposit collected', 10.95, 'account', 1, 'reservation', 18, 'security_deposit_in', 0, 'reservation:security_deposit_in:18', '2026-03-10 00:53:35', 1, '2026-03-09 19:23:35', NULL, NULL, NULL),
(76, 'income', 'Reservation Return', 'Reservation #18 - Return payment (Cash)', 500.00, 'cash', NULL, 'reservation', 18, 'return', 0, 'reservation:return:18:cash', '2026-03-10 00:54:50', 1, '2026-03-09 19:24:50', NULL, NULL, NULL),
(77, 'income', 'Reservation Return', 'Reservation #18 - Return payment (Credit)', 500.00, 'credit', NULL, 'reservation', 18, 'return', 0, 'reservation:return:18:credit', '2026-03-10 00:54:50', 1, '2026-03-09 19:24:50', NULL, NULL, NULL),
(78, 'income', 'Reservation Return', 'Reservation #18 - Return payment (Account)', 500.00, 'account', 1, 'reservation', 18, 'return', 0, 'reservation:return:18:account', '2026-03-10 00:54:50', 1, '2026-03-09 19:24:50', NULL, NULL, NULL),
(79, 'expense', 'Security Deposit Returned', 'Reservation #18 - Security deposit returned', 10.95, 'account', 1, 'reservation', 18, 'security_deposit_out', 0, 'reservation:security_deposit_out:18', '2026-03-10 00:54:50', 1, '2026-03-09 19:24:50', NULL, NULL, NULL),
(80, 'income', 'Reservation Delivery', 'Reservation #19  ” Delivery payment', 1150.00, 'cash', NULL, 'reservation', 19, 'delivery', 0, 'reservation:delivery:19', '2026-03-10 01:07:12', 1, '2026-03-09 19:37:12', NULL, NULL, NULL),
(81, 'income', 'Security Deposit Collected', 'Reservation #19 - Security deposit collected', 172.50, 'account', 1, 'reservation', 19, 'security_deposit_in', 0, 'reservation:security_deposit_in:19', '2026-03-10 01:07:12', 1, '2026-03-09 19:37:12', NULL, NULL, NULL),
(82, 'income', 'Reservation Return', 'Reservation #19 - Return payment (Cash)', 100.00, 'cash', NULL, 'reservation', 19, 'return', 0, 'reservation:return:19:cash', '2026-03-10 01:08:55', 1, '2026-03-09 19:38:55', NULL, NULL, NULL),
(83, 'income', 'Reservation Return', 'Reservation #19 - Return payment (Credit)', 100.00, 'credit', NULL, 'reservation', 19, 'return', 0, 'reservation:return:19:credit', '2026-03-10 01:08:55', 1, '2026-03-09 19:38:55', NULL, NULL, NULL),
(84, 'income', 'Reservation Return', 'Reservation #19 - Return payment (Account)', 100.00, 'account', 1, 'reservation', 19, 'return', 0, 'reservation:return:19:account', '2026-03-10 01:08:55', 1, '2026-03-09 19:38:55', NULL, NULL, NULL),
(85, 'expense', 'Security Deposit Returned', 'Reservation #19 - Security deposit returned', 172.50, 'account', 1, 'reservation', 19, 'security_deposit_out', 0, 'reservation:security_deposit_out:19', '2026-03-10 01:08:55', 1, '2026-03-09 19:38:55', NULL, NULL, NULL),
(87, 'income', 'Reservation Advance', 'Reservation #21 - Advance payment', 100.00, 'account', 2, 'reservation', 21, 'advance', 0, 'reservation:advance:21', '2026-03-11 10:58:00', 1, '2026-03-11 05:28:00', NULL, NULL, NULL),
(88, 'income', 'Reservation Delivery', 'Reservation #21 - Delivery payment', 500.00, 'cash', NULL, 'reservation', 21, 'delivery', 0, 'reservation:delivery:21', '2026-03-11 11:01:01', 1, '2026-03-11 05:31:01', NULL, NULL, NULL),
(89, 'income', 'Security Deposit Collected', 'Reservation #21 - Security deposit collected', 97.50, 'account', 1, 'reservation', 21, 'security_deposit_in', 0, 'reservation:security_deposit_in:21', '2026-03-11 11:01:01', 1, '2026-03-11 05:31:01', NULL, NULL, NULL),
(90, 'expense', 'Reservation Cancellation Refund', 'Refund — Reservation #21 cancelled. Reason: sdsd', 500.00, 'cash', NULL, 'reservation', 21, 'cancellation', 0, NULL, '2026-03-11 11:01:55', 1, '2026-03-11 05:31:55', NULL, NULL, NULL),
(91, 'expense', 'Credit Payment Settled', 'Credit payment settled via Bank', 100.00, 'credit', NULL, 'manual', NULL, 'credit_payment_settlement', 1, NULL, '2026-03-11 12:16:13', 1, '2026-03-11 06:46:13', NULL, NULL, NULL),
(92, 'income', 'Credit Payment Received', 'Payment received against credit via Bank', 100.00, 'account', 2, 'manual', NULL, 'credit_payment_received', 0, NULL, '2026-03-11 12:16:13', 1, '2026-03-11 06:46:13', NULL, NULL, NULL),
(93, 'income', 'Reservation Delivery', 'Reservation #22 - Delivery payment', 168.00, 'cash', NULL, 'reservation', 22, 'delivery', 0, 'reservation:delivery:22', '2026-03-11 12:49:52', 1, '2026-03-11 07:19:52', NULL, NULL, NULL),
(94, 'income', 'Security Deposit Collected', 'Reservation #22 - Security deposit collected', 25.20, 'account', 1, 'reservation', 22, 'security_deposit_in', 0, 'reservation:security_deposit_in:22', '2026-03-11 12:49:52', 1, '2026-03-11 07:19:52', NULL, NULL, NULL),
(95, 'income', 'Reservation Return', 'Reservation #22 - Return payment', 200.00, 'cash', NULL, 'reservation', 22, 'return', 0, 'reservation:return:22', '2026-03-11 12:55:35', 1, '2026-03-11 07:25:35', NULL, NULL, NULL),
(96, 'expense', 'Security Deposit Returned', 'Reservation #22 - Security deposit returned', 25.20, 'account', 1, 'reservation', 22, 'security_deposit_out', 0, 'reservation:security_deposit_out:22', '2026-03-11 12:55:35', 1, '2026-03-11 07:25:35', NULL, NULL, NULL),
(97, 'income', 'Reservation Advance', 'Reservation #25 - Advance payment', 2000.00, 'account', 2, 'reservation', 25, 'advance', 0, 'reservation:advance:25', '2026-03-11 14:16:37', 1, '2026-03-11 08:46:37', NULL, NULL, NULL),
(98, 'income', 'Reservation Delivery', 'Reservation #25 - Delivery payment', 4150.00, 'cash', NULL, 'reservation', 25, 'delivery', 0, 'reservation:delivery:25', '2026-03-11 14:18:06', 1, '2026-03-11 08:48:06', NULL, NULL, NULL),
(99, 'income', 'Security Deposit Collected', 'Reservation #25 - Security deposit collected', 622.50, 'account', 1, 'reservation', 25, 'security_deposit_in', 0, 'reservation:security_deposit_in:25', '2026-03-11 14:18:06', 1, '2026-03-11 08:48:06', NULL, NULL, NULL),
(100, 'income', 'Reservation Return', 'Reservation #25 - Return payment', 200.00, 'cash', NULL, 'reservation', 25, 'return', 0, 'reservation:return:25', '2026-03-11 14:18:53', 1, '2026-03-11 08:48:53', NULL, NULL, NULL),
(101, 'expense', 'Security Deposit Returned', 'Reservation #25 - Security deposit returned', 622.50, 'account', 1, 'reservation', 25, 'security_deposit_out', 0, 'reservation:security_deposit_out:25', '2026-03-11 14:18:53', 1, '2026-03-11 08:48:53', NULL, NULL, NULL),
(102, 'expense', 'Transfer Out', 'Cash transfer to Bank Account', 51097.00, 'cash', NULL, 'transfer', NULL, 'transfer_out', 0, NULL, '2026-03-11 15:07:15', 1, '2026-03-11 09:37:15', NULL, NULL, NULL),
(103, 'income', 'Transfer In', 'Cash transfer to Bank Account', 51097.00, 'account', 1, 'transfer', NULL, 'transfer_in', 0, NULL, '2026-03-11 15:07:15', 1, '2026-03-11 09:37:15', NULL, NULL, NULL),
(104, 'expense', 'Office Expense', '', 900.00, 'credit', NULL, 'manual', NULL, 'manual', 0, NULL, '2026-03-12 10:56:30', 1, '2026-03-12 05:26:30', NULL, NULL, NULL),
(105, 'income', 'Reservation Delivery', 'Reservation #24 - Delivery payment', 10850.00, 'cash', NULL, 'reservation', 24, 'delivery', 0, 'reservation:delivery:24', '2026-03-12 11:04:22', 1, '2026-03-12 05:34:22', NULL, NULL, NULL),
(106, 'income', 'Security Deposit Collected', 'Reservation #24 - Security deposit collected', 1477.50, 'account', 1, 'reservation', 24, 'security_deposit_in', 0, 'reservation:security_deposit_in:24', '2026-03-12 11:04:22', 1, '2026-03-12 05:34:22', NULL, NULL, NULL),
(107, 'expense', 'Utilities', '', 850.00, 'cash', NULL, 'manual', NULL, 'manual', 0, NULL, '2026-03-12 11:04:50', 1, '2026-03-12 05:34:50', NULL, NULL, NULL),
(108, 'income', 'Reservation Advance', 'Reservation #26 - Advance payment', 2000.00, 'cash', NULL, 'reservation', 26, 'advance', 0, 'reservation:advance:26', '2026-03-12 11:20:21', 1, '2026-03-12 05:50:21', NULL, NULL, NULL),
(109, 'income', 'Reservation Delivery (Prepaid)', 'Reservation #26 - Delivery (Prepaid) payment', 150.00, 'cash', NULL, 'reservation', 26, 'delivery_prepaid', 0, 'reservation:delivery_prepaid:26', '2026-03-12 11:20:21', 1, '2026-03-12 05:50:21', NULL, NULL, NULL),
(110, 'income', 'Reservation Delivery', 'Reservation #26 - Delivery payment', 4500.00, 'cash', NULL, 'reservation', 26, 'delivery', 0, 'reservation:delivery:26', '2026-03-12 11:22:00', 1, '2026-03-12 05:52:00', NULL, NULL, NULL),
(111, 'income', 'Security Deposit Collected', 'Reservation #26 - Security deposit collected', 600.00, 'account', 1, 'reservation', 26, 'security_deposit_in', 0, 'reservation:security_deposit_in:26', '2026-03-12 11:22:00', 1, '2026-03-12 05:52:00', NULL, NULL, NULL),
(112, 'income', 'Reservation Return', 'Reservation #26 - Return payment', 200.00, 'cash', NULL, 'reservation', 26, 'return', 0, 'reservation:return:26', '2026-03-12 11:22:53', 1, '2026-03-12 05:52:53', NULL, NULL, NULL),
(113, 'expense', 'Security Deposit Returned', 'Reservation #26 - Security deposit returned', 600.00, 'account', 1, 'reservation', 26, 'security_deposit_out', 0, 'reservation:security_deposit_out:26', '2026-03-12 11:22:53', 1, '2026-03-12 05:52:53', NULL, NULL, NULL),
(115, 'income', 'for void', '', 1200.00, 'cash', NULL, 'manual', NULL, 'manual', 0, NULL, '2026-03-12 20:16:55', 1, '2026-03-12 14:46:55', '2026-03-12 20:22:44', 1, 'mistake'),
(116, 'income', 'Reservation Return', 'Reservation #24 - Return payment', 200.00, 'cash', NULL, 'reservation', 24, 'return', 0, 'reservation:return:24', '2026-03-12 20:39:22', 1, '2026-03-12 15:09:22', '2026-03-12 20:41:49', 1, 'testing'),
(117, 'expense', 'Security Deposit Returned', 'Reservation #24 - Security deposit returned', 1477.50, 'account', 1, 'reservation', 24, 'security_deposit_out', 0, 'reservation:security_deposit_out:24', '2026-03-12 20:39:22', 1, '2026-03-12 15:09:22', NULL, NULL, NULL),
(118, 'income', 'Reservation Advance', 'Reservation #27 - Advance payment', 50.00, 'cash', NULL, 'reservation', 27, 'advance', 0, 'reservation:advance:27', '2026-03-13 14:19:23', 1, '2026-03-13 08:49:23', NULL, NULL, NULL),
(119, 'income', 'Reservation Delivery (Prepaid)', 'Reservation #27 - Delivery (Prepaid) payment', 150.00, 'cash', NULL, 'reservation', 27, 'delivery_prepaid', 0, 'reservation:delivery_prepaid:27', '2026-03-13 14:19:23', 1, '2026-03-13 08:49:23', NULL, NULL, NULL),
(120, 'income', 'Reservation Delivery', 'Reservation #27 - Delivery payment', 250.00, 'cash', NULL, 'reservation', 27, 'delivery', 0, 'reservation:delivery:27', '2026-03-13 14:20:28', 1, '2026-03-13 08:50:28', NULL, NULL, NULL),
(121, 'income', 'Security Deposit Collected', 'Reservation #27 - Security deposit collected', 22.50, 'account', 1, 'reservation', 27, 'security_deposit_in', 0, 'reservation:security_deposit_in:27', '2026-03-13 14:20:28', 1, '2026-03-13 08:50:28', NULL, NULL, NULL),
(122, 'income', 'Reservation Extension', 'Reservation #27 - Extension payment', 300.00, 'cash', NULL, 'reservation', 27, 'extension', 0, 'reservation:extension:1', '2026-03-13 14:54:19', 1, '2026-03-13 09:24:19', NULL, NULL, NULL),
(123, 'income', 'Reservation Advance', 'Reservation #28 - Advance payment', 200.00, 'account', 1, 'reservation', 28, 'advance', 0, 'reservation:advance:28', '2026-03-13 15:01:38', 1, '2026-03-13 09:31:38', NULL, NULL, NULL),
(124, 'income', 'Reservation Delivery (Prepaid)', 'Reservation #28 - Delivery (Prepaid) payment', 150.00, 'account', 1, 'reservation', 28, 'delivery_prepaid', 0, 'reservation:delivery_prepaid:28', '2026-03-13 15:01:38', 1, '2026-03-13 09:31:38', NULL, NULL, NULL),
(125, 'income', 'Reservation Delivery', 'Reservation #28 - Delivery payment', 200.00, 'cash', NULL, 'reservation', 28, 'delivery', 0, 'reservation:delivery:28', '2026-03-13 15:02:09', 1, '2026-03-13 09:32:09', NULL, NULL, NULL),
(126, 'income', 'Security Deposit Collected', 'Reservation #28 - Security deposit collected', 30.00, 'account', 1, 'reservation', 28, 'security_deposit_in', 0, 'reservation:security_deposit_in:28', '2026-03-13 15:02:09', 1, '2026-03-13 09:32:09', NULL, NULL, NULL),
(127, 'income', 'Reservation Extension', 'Reservation #28 - Extension payment', 1400.00, 'account', 1, 'reservation', 28, 'extension', 0, 'reservation:extension:2', '2026-03-13 15:04:51', 1, '2026-03-13 09:34:51', NULL, NULL, NULL),
(128, 'expense', 'Credit Payment Settled', 'Credit payment settled via Bank', 100.00, 'credit', NULL, 'manual', NULL, 'credit_payment_settlement', 1, NULL, '2026-03-13 15:47:11', 1, '2026-03-13 10:17:11', NULL, NULL, NULL),
(129, 'income', 'Credit Payment Received', 'Payment received against credit via Bank', 100.00, 'account', 1, 'manual', NULL, 'credit_payment_received', 0, NULL, '2026-03-13 15:47:11', 1, '2026-03-13 10:17:11', NULL, NULL, NULL),
(130, 'expense', 'Credit Payment Settled', 'Credit payment settled via Bank', 100.00, 'credit', NULL, 'manual', NULL, 'credit_payment_settlement', 1, NULL, '2026-03-13 15:47:16', 1, '2026-03-13 10:17:16', NULL, NULL, NULL),
(131, 'income', 'Credit Payment Received', 'Payment received against credit via Bank', 100.00, 'account', 1, 'manual', NULL, 'credit_payment_received', 0, NULL, '2026-03-13 15:47:16', 1, '2026-03-13 10:17:16', NULL, NULL, NULL),
(132, 'expense', 'Credit Payment Settled', 'Credit payment settled via Bank', 100.00, 'credit', NULL, 'manual', NULL, 'credit_payment_settlement', 1, NULL, '2026-03-13 15:47:23', 1, '2026-03-13 10:17:23', NULL, NULL, NULL),
(133, 'income', 'Credit Payment Received', 'Payment received against credit via Bank', 100.00, 'account', 1, 'manual', NULL, 'credit_payment_received', 0, NULL, '2026-03-13 15:47:23', 1, '2026-03-13 10:17:23', NULL, NULL, NULL),
(134, 'expense', 'Credit Payment Settled', 'Credit payment settled via Bank', 100.00, 'credit', NULL, 'manual', NULL, 'credit_payment_settlement', 1, NULL, '2026-03-13 15:47:33', 1, '2026-03-13 10:17:33', NULL, NULL, NULL),
(135, 'income', 'Credit Payment Received', 'Payment received against credit via Bank', 100.00, 'account', 1, 'manual', NULL, 'credit_payment_received', 0, NULL, '2026-03-13 15:47:33', 1, '2026-03-13 10:17:33', NULL, NULL, NULL),
(136, 'expense', 'Credit Payment Settled', 'Credit payment settled via Bank', 100.00, 'credit', NULL, 'manual', NULL, 'credit_payment_settlement', 1, NULL, '2026-03-13 15:47:39', 1, '2026-03-13 10:17:39', NULL, NULL, NULL),
(137, 'income', 'Credit Payment Received', 'Payment received against credit via Bank', 100.00, 'account', 1, 'manual', NULL, 'credit_payment_received', 0, NULL, '2026-03-13 15:47:39', 1, '2026-03-13 10:17:39', NULL, NULL, NULL),
(138, 'expense', 'Credit Payment Settled', 'Credit payment settled via Bank', 100.00, 'credit', NULL, 'manual', NULL, 'credit_payment_settlement', 1, NULL, '2026-03-13 15:47:46', 1, '2026-03-13 10:17:46', NULL, NULL, NULL),
(139, 'income', 'Credit Payment Received', 'Payment received against credit via Bank', 100.00, 'account', 1, 'manual', NULL, 'credit_payment_received', 0, NULL, '2026-03-13 15:47:46', 1, '2026-03-13 10:17:46', NULL, NULL, NULL),
(140, 'expense', 'Credit Payment Settled', 'Credit payment settled via Cash', 400.00, 'credit', NULL, 'manual', NULL, 'credit_payment_settlement', 1, NULL, '2026-03-13 15:47:49', 1, '2026-03-13 10:17:49', NULL, NULL, NULL),
(141, 'income', 'Credit Payment Received', 'Payment received against credit via Cash', 400.00, 'cash', NULL, 'manual', NULL, 'credit_payment_received', 0, NULL, '2026-03-13 15:47:49', 1, '2026-03-13 10:17:49', NULL, NULL, NULL),
(142, 'expense', 'Credit Payment Settled', 'Credit payment settled via Cash', 100.00, 'credit', NULL, 'manual', NULL, 'credit_payment_settlement', 1, NULL, '2026-03-13 15:47:52', 1, '2026-03-13 10:17:52', NULL, NULL, NULL),
(143, 'income', 'Credit Payment Received', 'Payment received against credit via Cash', 100.00, 'cash', NULL, 'manual', NULL, 'credit_payment_received', 0, NULL, '2026-03-13 15:47:52', 1, '2026-03-13 10:17:52', NULL, NULL, NULL),
(144, 'expense', 'Credit Payment Settled', 'Credit payment settled via Cash', 100.00, 'credit', NULL, 'manual', NULL, 'credit_payment_settlement', 1, NULL, '2026-03-13 15:47:55', 1, '2026-03-13 10:17:55', NULL, NULL, NULL),
(145, 'income', 'Credit Payment Received', 'Payment received against credit via Cash', 100.00, 'cash', NULL, 'manual', NULL, 'credit_payment_received', 0, NULL, '2026-03-13 15:47:55', 1, '2026-03-13 10:17:55', NULL, NULL, NULL),
(146, 'income', 'Reservation Return', 'Reservation #28 - Return payment', 200.00, 'cash', NULL, 'reservation', 28, 'return', 0, 'reservation:return:28', '2026-03-13 16:18:20', 1, '2026-03-13 10:48:20', NULL, NULL, NULL),
(147, 'expense', 'Security Deposit Returned', 'Reservation #28 - Security deposit returned', 30.00, 'account', 1, 'reservation', 28, 'security_deposit_out', 0, 'reservation:security_deposit_out:28', '2026-03-13 16:18:20', 1, '2026-03-13 10:48:20', NULL, NULL, NULL),
(148, 'income', 'Reservation Delivery (Prepaid)', 'Reservation #29 - Delivery (Prepaid) payment', 150.00, 'account', 1, 'reservation', 29, 'delivery_prepaid', 0, 'reservation:delivery_prepaid:29', '2026-03-16 14:59:48', 1, '2026-03-16 09:29:48', NULL, NULL, NULL),
(149, 'income', 'Reservation Delivery', 'Reservation #29 - Delivery payment', 3600.00, 'cash', NULL, 'reservation', 29, 'delivery', 0, 'reservation:delivery:29', '2026-03-16 15:00:28', 1, '2026-03-16 09:30:28', NULL, NULL, NULL),
(150, 'income', 'Security Deposit Collected', 'Reservation #29 - Security deposit collected', 540.00, 'account', 1, 'reservation', 29, 'security_deposit_in', 0, 'reservation:security_deposit_in:29', '2026-03-16 15:00:28', 1, '2026-03-16 09:30:28', NULL, NULL, NULL),
(151, 'income', 'Reservation Delivery (Prepaid)', 'Reservation #30 - Delivery (Prepaid) payment', 150.00, 'cash', NULL, 'reservation', 30, 'delivery_prepaid', 0, 'reservation:delivery_prepaid:30', '2026-03-16 15:01:37', 1, '2026-03-16 09:31:37', NULL, NULL, NULL),
(152, 'income', 'Reservation Delivery', 'Reservation #30 - Delivery payment', 36.00, 'cash', NULL, 'reservation', 30, 'delivery', 0, 'reservation:delivery:30', '2026-03-16 15:02:14', 1, '2026-03-16 09:32:14', NULL, NULL, NULL),
(153, 'income', 'Security Deposit Collected', 'Reservation #30 - Security deposit collected', 5.40, 'account', 1, 'reservation', 30, 'security_deposit_in', 0, 'reservation:security_deposit_in:30', '2026-03-16 15:02:14', 1, '2026-03-16 09:32:14', NULL, NULL, NULL),
(154, 'income', 'Reservation Delivery (Prepaid)', 'Reservation #31 - Delivery (Prepaid) payment', 150.00, 'cash', NULL, 'reservation', 31, 'delivery_prepaid', 0, 'reservation:delivery_prepaid:31', '2026-03-16 17:12:33', 1, '2026-03-16 11:42:33', NULL, NULL, NULL),
(155, 'income', 'Reservation Delivery', 'Reservation #31 - Delivery payment', 36000.00, 'cash', NULL, 'reservation', 31, 'delivery', 0, 'reservation:delivery:31', '2026-03-16 17:13:05', 1, '2026-03-16 11:43:05', NULL, NULL, NULL),
(156, 'income', 'Security Deposit Collected', 'Reservation #31 - Security deposit collected', 5400.00, 'account', 1, 'reservation', 31, 'security_deposit_in', 0, 'reservation:security_deposit_in:31', '2026-03-16 17:13:05', 1, '2026-03-16 11:43:05', NULL, NULL, NULL),
(157, 'income', 'Reservation Delivery (Prepaid)', 'Reservation #32 - Delivery (Prepaid) payment', 150.00, 'cash', NULL, 'reservation', 32, 'delivery_prepaid', 0, 'reservation:delivery_prepaid:32', '2026-03-16 17:33:35', 1, '2026-03-16 12:03:35', NULL, NULL, NULL),
(158, 'income', 'Reservation Delivery', 'Reservation #32 - Delivery payment', 1.00, 'cash', NULL, 'reservation', 32, 'delivery', 0, 'reservation:delivery:32', '2026-03-16 17:34:11', 1, '2026-03-16 12:04:11', NULL, NULL, NULL),
(159, 'income', 'Security Deposit Collected', 'Reservation #32 - Security deposit collected', 0.15, 'account', 1, 'reservation', 32, 'security_deposit_in', 0, 'reservation:security_deposit_in:32', '2026-03-16 17:34:11', 1, '2026-03-16 12:04:11', NULL, NULL, NULL),
(160, 'expense', 'Investment Down Payment', 'Down payment for: Test BMW EMI', 10000.00, 'account', 1, 'emi_investment', 2, 'down_payment', 0, 'inv_dp_2', '2026-03-16 20:43:28', 1, '2026-03-16 15:13:28', NULL, NULL, NULL),
(161, 'income', 'Reservation Delivery (Prepaid)', 'Reservation #33 - Delivery (Prepaid) payment', 150.00, 'cash', NULL, 'reservation', 33, 'delivery_prepaid', 0, 'reservation:delivery_prepaid:33', '2026-03-16 20:53:46', 1, '2026-03-16 15:23:46', NULL, NULL, NULL),
(162, 'income', 'Reservation Delivery', 'Reservation #33 - Delivery payment', 5000.00, 'cash', NULL, 'reservation', 33, 'delivery', 0, 'reservation:delivery:33', '2026-03-16 20:55:31', 1, '2026-03-16 15:25:31', NULL, NULL, NULL),
(163, 'income', 'Security Deposit Collected', 'Reservation #33 - Security deposit collected', 750.00, 'account', 1, 'reservation', 33, 'security_deposit_in', 0, 'reservation:security_deposit_in:33', '2026-03-16 20:55:31', 1, '2026-03-16 15:25:31', NULL, NULL, NULL),
(164, 'income', 'Reservation Return', 'Reservation #33 - Return payment', 200.00, 'cash', NULL, 'reservation', 33, 'return', 0, 'reservation:return:33', '2026-03-16 23:08:10', 1, '2026-03-16 17:38:10', NULL, NULL, NULL),
(165, 'expense', 'Security Deposit Returned', 'Reservation #33 - Security deposit returned', 750.00, 'account', 1, 'reservation', 33, 'security_deposit_out', 0, 'reservation:security_deposit_out:33', '2026-03-16 23:08:10', 1, '2026-03-16 17:38:10', NULL, NULL, NULL),
(166, 'income', 'Reservation Return', 'Reservation #32 - Return payment', 200.00, 'cash', NULL, 'reservation', 32, 'return', 0, 'reservation:return:32', '2026-03-16 23:11:25', 1, '2026-03-16 17:41:25', NULL, NULL, NULL),
(167, 'expense', 'Security Deposit Returned', 'Reservation #32 - Security deposit returned', 0.15, 'account', 1, 'reservation', 32, 'security_deposit_out', 0, 'reservation:security_deposit_out:32', '2026-03-16 23:11:25', 1, '2026-03-16 17:41:25', NULL, NULL, NULL),
(168, 'income', 'Reservation Delivery (Prepaid)', 'Reservation #34 - Delivery (Prepaid) payment', 150.00, 'cash', NULL, 'reservation', 34, 'delivery_prepaid', 0, 'reservation:delivery_prepaid:34', '2026-03-16 23:12:47', 1, '2026-03-16 17:42:47', NULL, NULL, NULL),
(169, 'income', 'Reservation Delivery', 'Reservation #34 - Delivery payment', 58000.00, 'cash', NULL, 'reservation', 34, 'delivery', 0, 'reservation:delivery:34', '2026-03-16 23:14:20', 1, '2026-03-16 17:44:20', NULL, NULL, NULL),
(170, 'income', 'Security Deposit Collected', 'Reservation #34 - Security deposit collected', 8700.00, 'account', 1, 'reservation', 34, 'security_deposit_in', 0, 'reservation:security_deposit_in:34', '2026-03-16 23:14:20', 1, '2026-03-16 17:44:20', NULL, NULL, NULL),
(171, 'income', 'Reservation Extension', 'Reservation #34 - Extension payment', 7000.00, 'cash', NULL, 'reservation', 34, 'extension', 0, 'reservation:extension:3', '2026-03-17 09:59:28', 1, '2026-03-17 04:29:28', NULL, NULL, NULL),
(172, 'income', 'Reservation Delivery (Prepaid)', 'Reservation #35 - Delivery (Prepaid) payment', 150.00, 'cash', NULL, 'reservation', 35, 'delivery_prepaid', 0, 'reservation:delivery_prepaid:35', '2026-03-17 10:08:13', 1, '2026-03-17 04:38:13', NULL, NULL, NULL),
(173, 'income', 'Reservation Delivery', 'Reservation #35 - Delivery payment', 762.00, 'cash', NULL, 'reservation', 35, 'delivery', 0, 'reservation:delivery:35', '2026-03-17 10:08:58', 1, '2026-03-17 04:38:58', '2026-03-17 10:12:52', 1, 'mistaken'),
(174, 'income', 'Security Deposit Collected', 'Reservation #35 - Security deposit collected', 114.30, 'account', 1, 'reservation', 35, 'security_deposit_in', 0, 'reservation:security_deposit_in:35', '2026-03-17 10:08:58', 1, '2026-03-17 04:38:58', NULL, NULL, NULL),
(175, 'income', 'Reservation Return', 'Reservation #35 - Return payment', 200.00, 'cash', NULL, 'reservation', 35, 'return', 0, 'reservation:return:35', '2026-03-17 10:09:46', 1, '2026-03-17 04:39:46', '2026-03-17 10:17:23', 1, 'mistakenly entered'),
(176, 'expense', 'Security Deposit Returned', 'Reservation #35 - Security deposit returned', 114.30, 'account', 1, 'reservation', 35, 'security_deposit_out', 0, 'reservation:security_deposit_out:35', '2026-03-17 10:09:46', 1, '2026-03-17 04:39:46', NULL, NULL, NULL),
(179, 'income', 'Reservation Advance', 'Reservation #36 - Advance payment', 100.00, 'cash', NULL, 'reservation', 36, 'advance', 0, 'reservation:advance:36', '2026-03-17 21:53:30', 1, '2026-03-17 16:23:30', NULL, NULL, NULL),
(180, 'income', 'Reservation Delivery (Prepaid)', 'Reservation #36 - Delivery (Prepaid) payment', 150.00, 'cash', NULL, 'reservation', 36, 'delivery_prepaid', 0, 'reservation:delivery_prepaid:36', '2026-03-17 21:53:30', 1, '2026-03-17 16:23:30', NULL, NULL, NULL),
(181, 'income', 'Reservation Advance', 'Reservation #37 - Advance payment', 50.00, 'cash', NULL, 'reservation', 37, 'advance', 0, 'reservation:advance:37', '2026-03-18 00:42:29', 1, '2026-03-17 19:12:29', NULL, NULL, NULL),
(182, 'income', 'Reservation Delivery (Prepaid)', 'Reservation #37 - Delivery (Prepaid) payment', 150.00, 'cash', NULL, 'reservation', 37, 'delivery_prepaid', 0, 'reservation:delivery_prepaid:37', '2026-03-18 00:42:29', 1, '2026-03-17 19:12:29', NULL, NULL, NULL),
(183, 'income', 'Reservation Delivery (Prepaid)', 'Reservation #38 - Delivery (Prepaid) payment', 60.00, 'cash', NULL, 'reservation', 38, 'delivery_prepaid', 0, 'reservation:delivery_prepaid:38', '2026-03-18 01:03:59', 1, '2026-03-17 19:33:59', NULL, NULL, NULL),
(184, 'income', 'Reservation Advance', 'Reservation #38 - Advance payment', 40.00, 'cash', NULL, 'reservation', 38, 'advance', 0, 'reservation:advance:38', '2026-03-18 01:05:15', 1, '2026-03-17 19:35:15', NULL, NULL, NULL),
(185, 'income', 'Reservation Delivery', 'Reservation #38 - Delivery payment', 185.00, 'cash', NULL, 'reservation', 38, 'delivery', 0, 'reservation:delivery:38', '2026-03-18 01:08:54', 1, '2026-03-17 19:38:54', NULL, NULL, NULL),
(186, 'income', 'Reservation Return', 'Reservation #38 - Return payment', 55.00, 'cash', NULL, 'reservation', 38, 'return', 0, 'reservation:return:38', '2026-03-18 01:15:23', 1, '2026-03-17 19:45:23', NULL, NULL, NULL),
(187, 'expense', 'Salary', 'Salary - staff1 (March 2026)', 10350.00, 'account', 1, 'payroll', 34, 'salary_payment', 0, NULL, '2026-03-18 20:46:31', 1, '2026-03-18 15:16:31', NULL, NULL, NULL),
(188, 'expense', 'Investment Down Payment', 'Down payment for: doubling check', 1000.00, 'account', 1, 'emi_investment', 3, 'down_payment', 0, 'inv_dp_3', '2026-03-18 23:54:33', 1, '2026-03-18 18:24:33', NULL, NULL, NULL),
(189, 'income', 'Reservation Delivery (Prepaid)', 'Reservation #39 - Delivery (Prepaid) payment', 150.00, 'cash', NULL, 'reservation', 39, 'delivery_prepaid', 0, 'reservation:delivery_prepaid:39', '2026-03-19 13:28:25', 1, '2026-03-19 07:58:25', NULL, NULL, NULL),
(190, 'income', 'Reservation Delivery', 'Reservation #39 - Delivery payment', 30.00, 'cash', NULL, 'reservation', 39, 'delivery', 0, 'reservation:delivery:39', '2026-03-19 13:29:20', 1, '2026-03-19 07:59:20', NULL, NULL, NULL),
(191, 'income', 'Security Deposit Collected', 'Reservation #39 - Security deposit collected', 4.50, 'account', 1, 'reservation', 39, 'security_deposit_in', 0, 'reservation:security_deposit_in:39', '2026-03-19 13:29:20', 1, '2026-03-19 07:59:20', NULL, NULL, NULL),
(192, 'income', 'Reservation Delivery (Prepaid)', 'Reservation #40 - Delivery (Prepaid) payment', 150.00, 'cash', NULL, 'reservation', 40, 'delivery_prepaid', 0, 'reservation:delivery_prepaid:40', '2026-03-19 13:33:07', 1, '2026-03-19 08:03:07', NULL, NULL, NULL),
(193, 'income', 'Reservation Delivery (Prepaid)', 'Reservation #41 - Delivery (Prepaid) payment', 150.00, 'account', 1, 'reservation', 41, 'delivery_prepaid', 0, 'reservation:delivery_prepaid:41', '2026-03-19 14:22:18', 1, '2026-03-19 08:52:18', NULL, NULL, NULL),
(194, 'income', 'Reservation Delivery', 'Reservation #41 - Delivery payment', 6.00, 'cash', NULL, 'reservation', 41, 'delivery', 0, 'reservation:delivery:41', '2026-03-19 14:22:44', 1, '2026-03-19 08:52:44', NULL, NULL, NULL),
(195, 'income', 'Security Deposit Collected', 'Reservation #41 - Security deposit collected', 0.90, 'account', 1, 'reservation', 41, 'security_deposit_in', 0, 'reservation:security_deposit_in:41', '2026-03-19 14:22:44', 1, '2026-03-19 08:52:44', NULL, NULL, NULL),
(196, 'income', 'Reservation Delivery (Prepaid)', 'Reservation #42 - Delivery (Prepaid) payment', 150.00, 'cash', NULL, 'reservation', 42, 'delivery_prepaid', 0, 'reservation:delivery_prepaid:42', '2026-03-19 14:23:19', 1, '2026-03-19 08:53:19', NULL, NULL, NULL),
(197, 'income', 'Reservation Return', 'Reservation #41 - Return payment', 200.00, 'credit', NULL, 'reservation', 41, 'return', 0, 'reservation:return:41', '2026-03-21 20:12:11', 1, '2026-03-21 14:42:11', NULL, NULL, NULL),
(198, 'expense', 'Security Deposit Returned', 'Reservation #41 - Security deposit returned', 0.90, 'account', 1, 'reservation', 41, 'security_deposit_out', 0, 'reservation:security_deposit_out:41', '2026-03-21 20:12:11', 1, '2026-03-21 14:42:11', NULL, NULL, NULL),
(199, 'expense', 'Credit Payment Settled', 'Credit payment settled via Bank - closed', 200.00, 'credit', NULL, 'manual', NULL, 'credit_payment_settlement', 1, NULL, '2026-03-21 20:12:41', 1, '2026-03-21 14:42:41', NULL, NULL, NULL),
(200, 'income', 'Credit Payment Received', 'Payment received against credit via Bank - closed', 200.00, 'account', 1, 'manual', NULL, 'credit_payment_received', 0, NULL, '2026-03-21 20:12:41', 1, '2026-03-21 14:42:41', NULL, NULL, NULL),
(201, 'income', 'Reservation Return', 'Reservation #39 - Return payment', 200.00, 'cash', NULL, 'reservation', 39, 'return', 0, 'reservation:return:39', '2026-03-21 20:13:43', 1, '2026-03-21 14:43:43', NULL, NULL, NULL),
(202, 'expense', 'Security Deposit Returned', 'Reservation #39 - Security deposit returned', 4.50, 'account', 1, 'reservation', 39, 'security_deposit_out', 0, 'reservation:security_deposit_out:39', '2026-03-21 20:13:43', 1, '2026-03-21 14:43:43', NULL, NULL, NULL),
(203, 'income', 'Reservation Return', 'Reservation #34 - Return payment', 200.00, 'credit', NULL, 'reservation', 34, 'return', 0, 'reservation:return:34', '2026-03-21 20:14:27', 1, '2026-03-21 14:44:27', NULL, NULL, NULL),
(204, 'expense', 'Security Deposit Returned', 'Reservation #34 - Security deposit returned', 8700.00, 'account', 1, 'reservation', 34, 'security_deposit_out', 0, 'reservation:security_deposit_out:34', '2026-03-21 20:14:27', 1, '2026-03-21 14:44:27', NULL, NULL, NULL),
(205, 'expense', 'Credit Payment Settled', 'Credit payment settled via Bank', 200.00, 'credit', NULL, 'manual', NULL, 'credit_payment_settlement', 1, NULL, '2026-03-21 20:17:45', 1, '2026-03-21 14:47:45', NULL, NULL, NULL),
(206, 'income', 'Credit Payment Received', 'Payment received against credit via Bank', 200.00, 'account', 1, 'manual', NULL, 'credit_payment_received', 0, NULL, '2026-03-21 20:17:45', 1, '2026-03-21 14:47:45', NULL, NULL, NULL),
(207, 'income', 'Reservation Delivery (Prepaid)', 'Reservation #43 - Delivery (Prepaid) payment', 150.00, 'cash', NULL, 'reservation', 43, 'delivery_prepaid', 0, 'reservation:delivery_prepaid:43', '2026-03-21 22:07:43', 1, '2026-03-21 16:37:43', NULL, NULL, NULL),
(208, 'income', 'Reservation Delivery', 'Reservation #44 - Delivery payment', 27000.00, 'credit', NULL, 'reservation', 44, 'delivery', 0, 'reservation:delivery:44', '2026-03-22 11:51:23', 1, '2026-03-22 06:21:23', NULL, NULL, NULL),
(209, 'income', 'Security Deposit Collected', 'Reservation #44 - Security deposit collected', 4050.00, 'account', 1, 'reservation', 44, 'security_deposit_in', 0, 'reservation:security_deposit_in:44', '2026-03-22 11:51:23', 1, '2026-03-22 06:21:23', NULL, NULL, NULL),
(210, 'income', 'Reservation Delivery', 'Reservation #45 - Delivery payment', 266000.00, 'cash', NULL, 'reservation', 45, 'delivery', 0, 'reservation:delivery:45', '2026-03-22 11:59:58', 1, '2026-03-22 06:29:58', NULL, NULL, NULL),
(211, 'income', 'Security Deposit Collected', 'Reservation #45 - Security deposit collected', 39900.00, 'account', 1, 'reservation', 45, 'security_deposit_in', 0, 'reservation:security_deposit_in:45', '2026-03-22 11:59:58', 1, '2026-03-22 06:29:58', NULL, NULL, NULL),
(212, 'income', 'Reservation Delivery', 'Reservation #46 - Delivery payment', 26500.00, 'cash', NULL, 'reservation', 46, 'delivery', 0, 'reservation:delivery:46', '2026-03-22 12:11:39', 1, '2026-03-22 06:41:39', NULL, NULL, NULL),
(213, 'income', 'Reservation Payment', 'Reservation #46 - Delivery_charge payment', 1000.00, 'credit', NULL, 'reservation', 46, 'delivery_charge', 0, 'reservation:delivery_charge:46', '2026-03-22 12:11:39', 1, '2026-03-22 06:41:39', NULL, NULL, NULL),
(214, 'income', 'Security Deposit Collected', 'Reservation #46 - Security deposit collected', 4125.00, 'account', 1, 'reservation', 46, 'security_deposit_in', 0, 'reservation:security_deposit_in:46', '2026-03-22 12:11:39', 1, '2026-03-22 06:41:39', NULL, NULL, NULL),
(215, 'income', 'Reservation Delivery', 'Reservation #47 - Delivery payment', 63100.00, 'cash', NULL, 'reservation', 47, 'delivery', 0, 'reservation:delivery:47', '2026-03-22 12:17:23', 1, '2026-03-22 06:47:23', NULL, NULL, NULL),
(216, 'income', 'Reservation Delivery Charge', 'Reservation #47 - Delivery charge payment', 1000.00, 'credit', NULL, 'reservation', 47, 'delivery_charge', 0, 'reservation:delivery_charge:47', '2026-03-22 12:17:23', 1, '2026-03-22 06:47:23', NULL, NULL, NULL),
(217, 'income', 'Security Deposit Collected', 'Reservation #47 - Security deposit collected', 9615.00, 'account', 1, 'reservation', 47, 'security_deposit_in', 0, 'reservation:security_deposit_in:47', '2026-03-22 12:17:23', 1, '2026-03-22 06:47:23', NULL, NULL, NULL),
(218, 'expense', 'Traffic Challan', 'Challan Paid - Thar Roxx - over speed (Due: 20 Mar 2026)', 700.00, 'account', 2, 'challan_payment', 2, 'paid', 0, 'challan_2_paid_1774176157', '2026-03-22 16:12:37', 1, '2026-03-22 10:42:37', NULL, NULL, NULL),
(219, 'income', 'Reservation Return', 'Reservation #47 - Return payment', 1200.00, 'cash', NULL, 'reservation', 47, 'return', 0, 'reservation:return:47', '2026-03-23 18:19:46', 1, '2026-03-23 12:49:46', NULL, NULL, NULL),
(220, 'expense', 'Security Deposit', 'Reservation #47 - Deposit deducted (damage/charges)', 1200.00, 'account', 1, 'reservation', 47, 'security_deposit_deducted', 0, 'reservation:security_deposit_deducted:47', '2026-03-23 18:19:46', 1, '2026-03-23 12:49:46', NULL, NULL, NULL),
(221, 'income', 'Damage Charges', 'Reservation #47 - Damage charges from deposit', 1200.00, 'account', 1, 'reservation', 47, 'damage_from_deposit', 0, 'reservation:damage_from_deposit:47', '2026-03-23 18:19:46', 1, '2026-03-23 12:49:46', NULL, NULL, NULL),
(222, 'expense', 'Security Deposit Returned', 'Reservation #47 - Security deposit returned', 8415.00, 'account', 1, 'reservation', 47, 'security_deposit_out', 0, 'reservation:security_deposit_out:47', '2026-03-23 18:19:46', 1, '2026-03-23 12:49:46', NULL, NULL, NULL),
(223, 'income', 'Reservation Delivery', 'Reservation #48 - Delivery payment', 264.00, 'cash', NULL, 'reservation', 48, 'delivery', 0, 'reservation:delivery:48', '2026-03-23 23:34:45', 1, '2026-03-23 18:04:45', NULL, NULL, NULL),
(224, 'income', 'Reservation Delivery Charge', 'Reservation #48 - Delivery charge payment', 150.00, 'cash', NULL, 'reservation', 48, 'delivery_charge', 0, 'reservation:delivery_charge:48', '2026-03-23 23:34:45', 1, '2026-03-23 18:04:45', NULL, NULL, NULL),
(225, 'income', 'Security Deposit Collected', 'Reservation #48 - Security deposit collected', 62.10, 'account', 1, 'reservation', 48, 'security_deposit_in', 0, 'reservation:security_deposit_in:48', '2026-03-23 23:34:45', 1, '2026-03-23 18:04:45', NULL, NULL, NULL),
(226, 'income', 'Reservation Return', 'Reservation #48 - Return payment', 200.00, 'cash', NULL, 'reservation', 48, 'return', 0, 'reservation:return:48', '2026-03-23 23:39:08', 1, '2026-03-23 18:09:08', NULL, NULL, NULL),
(227, 'expense', 'Security Deposit Returned', 'Reservation #48 - Security deposit returned', 62.10, 'account', 1, 'reservation', 48, 'security_deposit_out', 0, 'reservation:security_deposit_out:48', '2026-03-23 23:39:08', 1, '2026-03-23 18:09:08', NULL, NULL, NULL),
(228, 'income', 'Reservation Delivery', 'Reservation #49 - Delivery payment', 50.00, 'cash', NULL, 'reservation', 49, 'delivery', 0, 'reservation:delivery:49', '2026-03-23 23:43:28', 1, '2026-03-23 18:13:28', NULL, NULL, NULL),
(229, 'income', 'Reservation Delivery Charge', 'Reservation #49 - Delivery charge payment', 150.00, 'cash', NULL, 'reservation', 49, 'delivery_charge', 0, 'reservation:delivery_charge:49', '2026-03-23 23:43:28', 1, '2026-03-23 18:13:28', NULL, NULL, NULL);
INSERT INTO `ledger_entries` (`id`, `txn_type`, `category`, `description`, `amount`, `payment_mode`, `bank_account_id`, `source_type`, `source_id`, `source_event`, `is_legacy_payment`, `idempotency_key`, `posted_at`, `created_by`, `created_at`, `voided_at`, `voided_by`, `void_reason`) VALUES
(230, 'income', 'Security Deposit Collected', 'Reservation #49 - Security deposit collected', 30.00, 'account', 1, 'reservation', 49, 'security_deposit_in', 0, 'reservation:security_deposit_in:49', '2026-03-23 23:43:28', 1, '2026-03-23 18:13:28', NULL, NULL, NULL),
(231, 'income', 'Reservation Return', 'Reservation #49 - Return payment', 200.00, 'cash', NULL, 'reservation', 49, 'return', 0, 'reservation:return:49', '2026-03-23 23:47:59', 1, '2026-03-23 18:17:59', NULL, NULL, NULL),
(232, 'expense', 'Security Deposit', 'Reservation #49 - Deposit deducted (damage/charges)', 20.00, 'account', 1, 'reservation', 49, 'security_deposit_deducted', 0, 'reservation:security_deposit_deducted:49', '2026-03-23 23:47:59', 1, '2026-03-23 18:17:59', NULL, NULL, NULL),
(233, 'income', 'Damage Charges', 'Reservation #49 - Damage charges from deposit', 20.00, 'account', 1, 'reservation', 49, 'damage_from_deposit', 0, 'reservation:damage_from_deposit:49', '2026-03-23 23:47:59', 1, '2026-03-23 18:17:59', NULL, NULL, NULL),
(234, 'expense', 'Security Deposit Returned', 'Reservation #49 - Security deposit returned', 10.00, 'account', 1, 'reservation', 49, 'security_deposit_out', 0, 'reservation:security_deposit_out:49', '2026-03-23 23:47:59', 1, '2026-03-23 18:17:59', NULL, NULL, NULL),
(235, 'income', 'Reservation Delivery', 'Reservation #50 - Delivery payment', 142.00, 'cash', NULL, 'reservation', 50, 'delivery', 0, 'reservation:delivery:50', '2026-03-23 23:51:00', 1, '2026-03-23 18:21:00', NULL, NULL, NULL),
(236, 'income', 'Reservation Delivery Charge', 'Reservation #50 - Delivery charge payment', 150.00, 'cash', NULL, 'reservation', 50, 'delivery_charge', 0, 'reservation:delivery_charge:50', '2026-03-23 23:51:00', 1, '2026-03-23 18:21:00', NULL, NULL, NULL),
(237, 'income', 'Security Deposit Collected', 'Reservation #50 - Security deposit collected', 43.80, 'account', 1, 'reservation', 50, 'security_deposit_in', 0, 'reservation:security_deposit_in:50', '2026-03-23 23:51:00', 1, '2026-03-23 18:21:00', NULL, NULL, NULL),
(238, 'income', 'Reservation Return', 'Reservation #50 - Return payment', 200.00, 'cash', NULL, 'reservation', 50, 'return', 0, 'reservation:return:50', '2026-03-24 00:01:22', 1, '2026-03-23 18:31:22', NULL, NULL, NULL),
(239, 'expense', 'Security Deposit', 'Reservation #50 - Deposit held: testing', 43.80, 'account', 1, 'reservation', 50, 'security_deposit_held', 0, 'reservation:security_deposit_held:50', '2026-03-24 00:01:22', 1, '2026-03-23 18:31:22', NULL, NULL, NULL),
(240, 'income', 'Damage Charges', 'Reservation #50 — Held deposit converted to income', 43.80, 'account', 1, 'reservation', 50, 'damage_from_deposit', 0, 'reservation:held_converted_income:50', '2026-03-24 00:08:43', 1, '2026-03-23 18:38:43', NULL, NULL, NULL),
(241, 'expense', 'Security Deposit Returned', 'Reservation #50 - Security deposit returned', 43.80, 'account', 1, 'reservation', 50, 'security_deposit_out', 0, 'reservation:security_deposit_out:50', '2026-03-24 00:18:41', 1, '2026-03-23 18:48:41', NULL, NULL, NULL),
(242, 'income', 'Reservation Return', 'Reservation #46 - Return payment', 1200.00, 'cash', NULL, 'reservation', 46, 'return', 0, 'reservation:return:46', '2026-03-24 00:48:00', 1, '2026-03-23 19:18:00', NULL, NULL, NULL),
(243, 'expense', 'Security Deposit', 'Reservation #46 - Deposit held: testing', 1000.00, 'account', 1, 'reservation', 46, 'security_deposit_held', 0, 'reservation:security_deposit_held:46', '2026-03-24 00:48:00', 1, '2026-03-23 19:18:00', NULL, NULL, NULL),
(244, 'expense', 'Security Deposit Returned', 'Reservation #46 - Security deposit returned', 3125.00, 'account', 1, 'reservation', 46, 'security_deposit_out', 0, 'reservation:security_deposit_out:46', '2026-03-24 00:48:00', 1, '2026-03-23 19:18:00', NULL, NULL, NULL),
(245, 'expense', 'Security Deposit', 'Reservation #46 — Held deposit converted to income', 1000.00, 'account', 1, 'reservation', 46, 'security_deposit_deducted', 0, 'reservation:held_converted_deducted:46', '2026-03-24 00:48:26', 1, '2026-03-23 19:18:26', NULL, NULL, NULL),
(246, 'income', 'Damage Charges', 'Reservation #46 — Held deposit converted to income', 1000.00, 'account', 1, 'reservation', 46, 'damage_from_deposit', 0, 'reservation:held_converted_income:46', '2026-03-24 00:48:26', 1, '2026-03-23 19:18:26', NULL, NULL, NULL),
(247, 'income', 'Reservation Delivery', 'Reservation #51 - Delivery payment', 12100.00, 'cash', NULL, 'reservation', 51, 'delivery', 0, 'reservation:delivery:51', '2026-03-24 10:05:58', 1, '2026-03-24 04:35:58', NULL, NULL, NULL),
(248, 'income', 'Reservation Delivery Charge', 'Reservation #51 - Delivery charge payment', 150.00, 'cash', NULL, 'reservation', 51, 'delivery_charge', 0, 'reservation:delivery_charge:51', '2026-03-24 10:05:58', 1, '2026-03-24 04:35:58', NULL, NULL, NULL),
(249, 'income', 'Security Deposit Collected', 'Reservation #51 - Security deposit collected', 1837.50, 'account', 1, 'reservation', 51, 'security_deposit_in', 0, 'reservation:security_deposit_in:51', '2026-03-24 10:05:58', 1, '2026-03-24 04:35:58', NULL, NULL, NULL),
(250, 'income', 'Reservation Return', 'Reservation #51 - Return payment', 1000.00, 'cash', NULL, 'reservation', 51, 'return', 0, 'reservation:return:51', '2026-03-24 10:06:57', 1, '2026-03-24 04:36:57', NULL, NULL, NULL),
(251, 'expense', 'Security Deposit', 'Reservation #51 - Deposit deducted (damage/charges)', 200.00, 'account', 1, 'reservation', 51, 'security_deposit_deducted', 0, 'reservation:security_deposit_deducted:51', '2026-03-24 10:06:57', 1, '2026-03-24 04:36:57', NULL, NULL, NULL),
(252, 'income', 'Damage Charges', 'Reservation #51 - Damage charges from deposit', 200.00, 'account', 1, 'reservation', 51, 'damage_from_deposit', 0, 'reservation:damage_from_deposit:51', '2026-03-24 10:06:57', 1, '2026-03-24 04:36:57', NULL, NULL, NULL),
(253, 'expense', 'Security Deposit', 'Reservation #51 - Deposit held: testing', 1000.00, 'account', 1, 'reservation', 51, 'security_deposit_held', 0, 'reservation:security_deposit_held:51', '2026-03-24 10:06:57', 1, '2026-03-24 04:36:57', NULL, NULL, NULL),
(254, 'expense', 'Security Deposit Returned', 'Reservation #51 - Security deposit returned', 637.50, 'account', 1, 'reservation', 51, 'security_deposit_out', 0, 'reservation:security_deposit_out:51', '2026-03-24 10:06:57', 1, '2026-03-24 04:36:57', NULL, NULL, NULL),
(255, 'income', 'Reservation Extension', 'Reservation #45 - Extension payment', 2000.00, 'cash', NULL, 'reservation', 45, 'extension', 0, 'reservation:extension:4', '2026-03-24 10:32:45', 1, '2026-03-24 05:02:45', NULL, NULL, NULL),
(256, 'expense', 'Security Deposit', 'Reservation #51 — Held deposit converted to income', 1000.00, 'account', 1, 'reservation', 51, 'security_deposit_deducted', 0, 'reservation:held_converted_deducted:51', '2026-03-24 11:12:22', 1, '2026-03-24 05:42:22', NULL, NULL, NULL),
(257, 'income', 'Damage Charges', 'Reservation #51 — Held deposit converted to income', 1000.00, 'account', 1, 'reservation', 51, 'damage_from_deposit', 0, 'reservation:held_converted_income:51', '2026-03-24 11:12:22', 1, '2026-03-24 05:42:22', NULL, NULL, NULL),
(258, 'income', 'Reservation Delivery', 'Reservation #52 - Delivery payment', 9300.00, 'cash', NULL, 'reservation', 52, 'delivery', 0, 'reservation:delivery:52', '2026-03-24 18:08:16', 1, '2026-03-24 12:38:16', NULL, NULL, NULL),
(259, 'income', 'Reservation Delivery Charge', 'Reservation #52 - Delivery charge payment', 150.00, 'cash', NULL, 'reservation', 52, 'delivery_charge', 0, 'reservation:delivery_charge:52', '2026-03-24 18:08:16', 1, '2026-03-24 12:38:16', NULL, NULL, NULL),
(260, 'income', 'Security Deposit Collected', 'Reservation #52 - Security deposit collected', 1417.50, 'account', 1, 'reservation', 52, 'security_deposit_in', 0, 'reservation:security_deposit_in:52', '2026-03-24 18:08:16', 1, '2026-03-24 12:38:16', NULL, NULL, NULL),
(261, 'expense', 'Security Deposit', 'Reservation #52 - Extension #5 paid from security deposit', 200.00, 'account', 1, 'reservation', 52, 'extension_from_deposit', 0, 'reservation:extension_from_deposit:5', '2026-03-24 18:10:02', 1, '2026-03-24 12:40:02', NULL, NULL, NULL),
(262, 'income', 'Reservation Extension', 'Reservation #52 - Extension payment (from deposit)', 200.00, 'account', 1, 'reservation', 52, 'extension', 0, 'reservation:extension:5', '2026-03-24 18:10:02', 1, '2026-03-24 12:40:02', NULL, NULL, NULL),
(263, 'expense', 'Security Deposit', 'Reservation #52 - Extension #6 paid from security deposit', 200.00, 'account', 1, 'reservation', 52, 'extension_from_deposit', 0, 'reservation:extension_from_deposit:6', '2026-03-24 18:13:36', 1, '2026-03-24 12:43:36', NULL, NULL, NULL),
(264, 'income', 'Reservation Extension', 'Reservation #52 - Extension payment (deposit portion)', 200.00, 'account', 1, 'reservation', 52, 'extension', 0, 'reservation:extension:deposit:6', '2026-03-24 18:13:36', 1, '2026-03-24 12:43:36', NULL, NULL, NULL),
(265, 'income', 'Reservation Return', 'Reservation #52 - Return payment', 200.00, 'cash', NULL, 'reservation', 52, 'return', 0, 'reservation:return:52', '2026-03-24 18:16:22', 1, '2026-03-24 12:46:22', NULL, NULL, NULL),
(266, 'expense', 'Security Deposit Returned', 'Reservation #52 - Security deposit returned', 1017.50, 'account', 1, 'reservation', 52, 'security_deposit_out', 0, 'reservation:security_deposit_out:52', '2026-03-24 18:16:22', 1, '2026-03-24 12:46:22', NULL, NULL, NULL),
(267, 'income', 'Reservation Delivery', 'Reservation #53 - Delivery payment', 9500.00, 'cash', NULL, 'reservation', 53, 'delivery', 0, 'reservation:delivery:53', '2026-03-24 19:04:03', 1, '2026-03-24 13:34:03', NULL, NULL, NULL),
(268, 'income', 'Reservation Delivery Charge', 'Reservation #53 - Delivery charge payment', 150.00, 'cash', NULL, 'reservation', 53, 'delivery_charge', 0, 'reservation:delivery_charge:53', '2026-03-24 19:04:03', 1, '2026-03-24 13:34:03', NULL, NULL, NULL),
(269, 'income', 'Security Deposit Collected', 'Reservation #53 - Security deposit collected', 1447.50, 'account', 1, 'reservation', 53, 'security_deposit_in', 0, 'reservation:security_deposit_in:53', '2026-03-24 19:04:03', 1, '2026-03-24 13:34:03', NULL, NULL, NULL),
(270, 'income', 'Reservation Return', 'Reservation #53 - Return payment', 200.00, 'cash', NULL, 'reservation', 53, 'return', 0, 'reservation:return:53', '2026-03-24 19:05:16', 1, '2026-03-24 13:35:16', NULL, NULL, NULL),
(271, 'expense', 'Security Deposit', 'Reservation #53 - Deposit held: test', 1000.00, 'account', 1, 'reservation', 53, 'security_deposit_held', 0, 'reservation:security_deposit_held:53', '2026-03-24 19:05:16', 1, '2026-03-24 13:35:16', NULL, NULL, NULL),
(272, 'expense', 'Security Deposit Returned', 'Reservation #53 - Security deposit returned', 447.50, 'account', 1, 'reservation', 53, 'security_deposit_out', 0, 'reservation:security_deposit_out:53', '2026-03-24 19:05:16', 1, '2026-03-24 13:35:16', NULL, NULL, NULL),
(273, 'expense', 'Vehicle Expense', 'new new - Service: 1', 111.00, 'cash', NULL, 'vehicle_expense', 8, 'Service', 0, NULL, '2026-03-24 19:14:35', 1, '2026-03-24 13:44:35', NULL, NULL, NULL),
(274, 'expense', 'Vehicle Expense', 'new new - Spare Parts: break pad [KM: 100]', 1000.00, 'cash', NULL, 'vehicle_expense', 8, 'Spare Parts', 0, NULL, '2026-03-24 19:20:26', 1, '2026-03-24 13:50:26', NULL, NULL, NULL),
(275, 'income', 'Reservation Return', 'Reservation #45 - Return payment', 200.00, 'cash', NULL, 'reservation', 45, 'return', 0, 'reservation:return:45', '2026-03-24 19:43:46', 1, '2026-03-24 14:13:46', NULL, NULL, NULL),
(276, 'expense', 'Security Deposit Returned', 'Reservation #45 - Security deposit returned', 39900.00, 'account', 1, 'reservation', 45, 'security_deposit_out', 0, 'reservation:security_deposit_out:45', '2026-03-24 19:43:46', 1, '2026-03-24 14:13:46', NULL, NULL, NULL),
(277, 'income', 'Reservation Extension', 'Reservation #44 - Extension payment', 100.00, 'account', 1, 'reservation', 44, 'extension', 0, 'reservation:extension:7', '2026-03-25 15:29:01', 1, '2026-03-25 09:59:01', NULL, NULL, NULL),
(278, 'income', 'Reservation Delivery', 'Reservation #54 - Delivery payment', 745000.00, 'cash', NULL, 'reservation', 54, 'delivery', 0, 'reservation:delivery:54', '2026-03-25 16:37:47', 1, '2026-03-25 11:07:47', NULL, NULL, NULL),
(279, 'income', 'Reservation Delivery Charge', 'Reservation #54 - Delivery charge payment', 150.00, 'cash', NULL, 'reservation', 54, 'delivery_charge', 0, 'reservation:delivery_charge:54', '2026-03-25 16:37:47', 1, '2026-03-25 11:07:47', NULL, NULL, NULL),
(280, 'income', 'Security Deposit Collected', 'Reservation #54 - Security deposit collected', 111772.50, 'account', 1, 'reservation', 54, 'security_deposit_in', 0, 'reservation:security_deposit_in:54', '2026-03-25 16:37:47', 1, '2026-03-25 11:07:47', NULL, NULL, NULL),
(281, 'income', 'Reservation Return', 'Reservation #54 - Return payment', 200.00, 'cash', NULL, 'reservation', 54, 'return', 0, 'reservation:return:54', '2026-03-25 16:45:32', 1, '2026-03-25 11:15:32', NULL, NULL, NULL),
(282, 'expense', 'Security Deposit Returned', 'Reservation #54 - Security deposit returned', 111772.50, 'account', 1, 'reservation', 54, 'security_deposit_out', 0, 'reservation:security_deposit_out:54', '2026-03-25 16:45:32', 1, '2026-03-25 11:15:32', NULL, NULL, NULL),
(283, 'expense', 'Investment Down Payment', 'Down payment for: test', 10000.00, 'account', 1, 'emi_investment', 4, 'down_payment', 0, 'inv_dp_4', '2026-03-25 17:07:34', 1, '2026-03-25 11:37:34', NULL, NULL, NULL),
(284, 'expense', 'Staff Advance', 'Staff Advance - staff1 (pre-payroll)', 1000.00, 'account', 1, 'payroll_advance', 9, 'advance_payment', 0, NULL, '2026-03-26 10:45:57', 1, '2026-03-26 05:15:57', NULL, NULL, NULL),
(285, 'expense', 'Staff Advance', 'Staff Advance - staff1 (pre-payroll)', 500.00, 'account', 1, 'payroll_advance', 10, 'advance_payment', 0, NULL, '2026-03-26 10:54:07', 1, '2026-03-26 05:24:07', NULL, NULL, NULL),
(286, 'expense', 'Staff Advance', 'Staff Advance - staff1 (pre-payroll)', 100.00, 'account', 1, 'payroll_advance', 114, 'advance_payment', 0, NULL, '2026-03-26 15:11:02', 1, '2026-03-26 09:41:02', NULL, NULL, NULL),
(287, 'expense', 'Traffic Challan', 'Challan Paid - bmw m4 - over speed (Due: 05 May 2026)', 5000.00, 'account', 1, 'challan_payment', 4, 'paid', 0, 'challan_4_paid_1774602908', '2026-03-27 14:45:08', 1, '2026-03-27 09:15:08', NULL, NULL, NULL),
(288, 'income', 'Reservation Return', 'Reservation #44 - Return payment', 211.00, 'cash', NULL, 'reservation', 44, 'return', 0, 'reservation:return:44', '2026-03-28 11:14:48', 1, '2026-03-28 05:44:48', NULL, NULL, NULL),
(289, 'expense', 'Security Deposit Returned', 'Reservation #44 - Security deposit returned', 4050.00, 'account', 1, 'reservation', 44, 'security_deposit_out', 0, 'reservation:security_deposit_out:44', '2026-03-28 11:14:48', 1, '2026-03-28 05:44:48', NULL, NULL, NULL),
(290, 'income', 'Reservation Return', 'Reservation #31 - Return payment', 200.00, 'cash', NULL, 'reservation', 31, 'return', 0, 'reservation:return:31', '2026-03-28 11:17:59', 1, '2026-03-28 05:47:59', NULL, NULL, NULL),
(291, 'expense', 'Security Deposit Returned', 'Reservation #31 - Security deposit returned', 5400.00, 'account', 1, 'reservation', 31, 'security_deposit_out', 0, 'reservation:security_deposit_out:31', '2026-03-28 11:17:59', 1, '2026-03-28 05:47:59', NULL, NULL, NULL),
(292, 'expense', 'Transfer Out', 'Cash to Bank test', 50000.00, 'cash', NULL, 'transfer', NULL, 'transfer_out', 0, NULL, '2026-04-02 23:57:34', 1, '2026-04-02 18:27:34', NULL, NULL, NULL),
(293, 'income', 'Transfer In', 'Cash to Bank test', 50000.00, 'account', 1, 'transfer', NULL, 'transfer_in', 0, NULL, '2026-04-02 23:57:34', 1, '2026-04-02 18:27:34', NULL, NULL, NULL),
(294, 'expense', 'Transfer Out', 'Cash to bank test transfer', 10000.00, 'cash', NULL, 'transfer', NULL, 'transfer_out', 0, NULL, '2026-04-02 00:01:17', 1, '2026-04-02 18:31:17', NULL, NULL, NULL),
(295, 'income', 'Transfer In', 'Cash to bank test transfer', 10000.00, 'account', 1, 'transfer', NULL, 'transfer_in', 0, NULL, '2026-04-02 00:01:17', 1, '2026-04-02 18:31:17', NULL, NULL, NULL),
(296, 'expense', 'Transfer Out', 'Adding funds for bank to cash', 15000.00, 'cash', NULL, 'transfer', NULL, 'transfer_out', 0, NULL, '2026-04-03 00:02:53', 1, '2026-04-02 18:32:53', NULL, NULL, NULL),
(297, 'income', 'Transfer In', 'Adding funds for bank to cash', 15000.00, 'account', 1, 'transfer', NULL, 'transfer_in', 0, NULL, '2026-04-03 00:02:53', 1, '2026-04-02 18:32:53', NULL, NULL, NULL),
(298, 'expense', 'Transfer Out', 'Bank transfer from Bank Account to Cash', 1500.00, 'account', 1, 'transfer', NULL, 'transfer_out', 0, NULL, '2026-04-03 00:05:30', 1, '2026-04-02 18:35:30', NULL, NULL, NULL),
(299, 'income', 'Transfer In', 'Bank transfer from Bank Account to Cash', 1500.00, 'cash', NULL, 'transfer', NULL, 'transfer_in', 0, NULL, '2026-04-03 00:05:30', 1, '2026-04-02 18:35:30', NULL, NULL, NULL),
(300, 'income', 'Reservation Delivery', 'Reservation #55 - Delivery payment', 1700.00, 'cash', NULL, 'reservation', 55, 'delivery', 0, 'reservation:delivery:55', '2026-04-03 12:52:02', 1, '2026-04-03 07:22:02', NULL, NULL, NULL),
(301, 'income', 'Reservation Delivery Charge', 'Reservation #55 - Delivery charge payment', 150.00, 'cash', NULL, 'reservation', 55, 'delivery_charge', 0, 'reservation:delivery_charge:55', '2026-04-03 12:52:02', 1, '2026-04-03 07:22:02', NULL, NULL, NULL),
(302, 'income', 'Security Deposit Collected', 'Reservation #55 - Security deposit collected', 277.50, 'account', 1, 'reservation', 55, 'security_deposit_in', 0, 'reservation:security_deposit_in:55', '2026-04-03 12:52:02', 1, '2026-04-03 07:22:02', NULL, NULL, NULL),
(303, 'income', 'Reservation Return', 'Reservation #55 - Return payment', 200.00, 'cash', NULL, 'reservation', 55, 'return', 0, 'reservation:return:55', '2026-04-03 12:53:06', 1, '2026-04-03 07:23:06', NULL, NULL, NULL),
(304, 'expense', 'Security Deposit Returned', 'Reservation #55 - Security deposit returned', 277.50, 'account', 1, 'reservation', 55, 'security_deposit_out', 0, 'reservation:security_deposit_out:55', '2026-04-03 12:53:06', 1, '2026-04-03 07:23:06', NULL, NULL, NULL),
(305, 'expense', 'Credit Payment Settled', 'Credit payment settled via Cash', 1000.00, 'credit', NULL, 'manual', NULL, 'credit_payment_settlement', 0, NULL, '2026-04-03 13:07:45', 1, '2026-04-03 07:37:45', NULL, NULL, NULL),
(306, 'income', 'Credit Payment Received', 'Payment received against credit via Cash', 1000.00, 'cash', NULL, 'manual', NULL, 'credit_payment_received', 0, NULL, '2026-04-03 13:07:45', 1, '2026-04-03 07:37:45', NULL, NULL, NULL),
(307, 'expense', 'Credit Payment Settled', 'Credit payment settled via Cash', 1000.00, 'credit', NULL, 'manual', NULL, 'credit_payment_settlement', 0, NULL, '2026-04-03 15:35:53', 1, '2026-04-03 10:05:53', NULL, NULL, NULL),
(308, 'income', 'Credit Payment Received', 'Payment received against credit via Cash', 1000.00, 'cash', NULL, 'manual', NULL, 'credit_payment_received', 0, NULL, '2026-04-03 15:35:53', 1, '2026-04-03 10:05:53', NULL, NULL, NULL),
(309, 'expense', 'Vehicle Expense', 'Mercedes-Benz S-Class S 350d - Service [KM: 3000]', 5000.00, 'cash', NULL, 'vehicle_expense', 1, 'Service', 0, NULL, '2026-04-04 12:57:14', 1, '2026-04-04 07:27:14', NULL, NULL, NULL),
(310, 'expense', 'Vehicle Expense', 'Mercedes-Benz S-Class S 350d - Service [KM: 1500]', 10000.00, 'cash', NULL, 'vehicle_expense', 1, 'Service', 0, NULL, '2026-04-07 13:01:16', 1, '2026-04-07 07:31:16', NULL, NULL, NULL),
(313, 'expense', 'Maintenance', 'Oil change and filter replacement', 300.00, 'cash', NULL, 'vehicle_expense', 15, NULL, 0, NULL, '2026-03-20 10:00:00', 1, '2026-04-07 07:43:32', NULL, NULL, NULL),
(314, 'expense', 'Service', 'Tire rotation', 200.00, 'cash', NULL, 'vehicle_expense', 15, NULL, 0, NULL, '2026-03-25 14:30:00', 1, '2026-04-07 07:43:32', NULL, NULL, NULL),
(315, 'expense', 'Damage Charge', 'Reservation #57 - Damage charge', 200.00, 'cash', NULL, 'reservation', 57, 'damage', 0, NULL, '2026-03-18 09:00:00', 1, '2026-04-07 07:43:32', NULL, NULL, NULL),
(316, 'expense', 'Repair', 'Brake pad replacement', 500.00, 'cash', NULL, 'vehicle_expense', 16, NULL, 0, NULL, '2026-03-22 11:00:00', 1, '2026-04-07 07:43:32', NULL, NULL, NULL),
(317, 'expense', 'Maintenance', 'Engine tune-up', 300.00, 'cash', NULL, 'vehicle_expense', 16, NULL, 0, NULL, '2026-03-28 15:00:00', 1, '2026-04-07 07:43:32', NULL, NULL, NULL),
(318, 'expense', 'Maintenance', 'Battery replacement', 400.00, 'cash', NULL, 'vehicle_expense', 17, NULL, 0, NULL, '2026-03-19 10:00:00', 1, '2026-04-07 07:43:32', NULL, NULL, NULL),
(319, 'expense', 'Service', 'AC gas refill', 150.00, 'cash', NULL, 'vehicle_expense', 17, NULL, 0, NULL, '2026-03-26 14:00:00', 1, '2026-04-07 07:43:32', NULL, NULL, NULL),
(320, 'expense', 'Maintenance', 'Inside period expense', 250.00, 'cash', NULL, 'vehicle_expense', 15, NULL, 0, NULL, '2026-03-20 10:00:00', 1, '2026-04-07 07:43:32', NULL, NULL, NULL),
(321, 'expense', 'Maintenance', 'Outside period expense', 350.00, 'cash', NULL, 'vehicle_expense', 15, NULL, 0, NULL, '2026-02-10 10:00:00', 1, '2026-04-07 07:43:32', NULL, NULL, NULL),
(322, 'expense', 'Maintenance', 'Oil change and filter replacement', 300.00, 'cash', NULL, 'vehicle_expense', 18, NULL, 0, NULL, '2026-03-20 10:00:00', 1, '2026-04-07 07:44:02', NULL, NULL, NULL),
(323, 'expense', 'Service', 'Tire rotation', 200.00, 'cash', NULL, 'vehicle_expense', 18, NULL, 0, NULL, '2026-03-25 14:30:00', 1, '2026-04-07 07:44:02', NULL, NULL, NULL),
(324, 'expense', 'Damage Charge', 'Reservation #58 - Damage charge', 200.00, 'cash', NULL, 'reservation', 58, 'damage', 0, NULL, '2026-03-18 09:00:00', 1, '2026-04-07 07:44:02', NULL, NULL, NULL),
(325, 'expense', 'Repair', 'Brake pad replacement', 500.00, 'cash', NULL, 'vehicle_expense', 19, NULL, 0, NULL, '2026-03-22 11:00:00', 1, '2026-04-07 07:44:02', NULL, NULL, NULL),
(326, 'expense', 'Maintenance', 'Engine tune-up', 300.00, 'cash', NULL, 'vehicle_expense', 19, NULL, 0, NULL, '2026-03-28 15:00:00', 1, '2026-04-07 07:44:02', NULL, NULL, NULL),
(327, 'expense', 'Maintenance', 'Battery replacement', 400.00, 'cash', NULL, 'vehicle_expense', 20, NULL, 0, NULL, '2026-03-19 10:00:00', 1, '2026-04-07 07:44:02', NULL, NULL, NULL),
(328, 'expense', 'Service', 'AC gas refill', 150.00, 'cash', NULL, 'vehicle_expense', 20, NULL, 0, NULL, '2026-03-26 14:00:00', 1, '2026-04-07 07:44:02', NULL, NULL, NULL),
(329, 'expense', 'Maintenance', 'Inside period expense', 250.00, 'cash', NULL, 'vehicle_expense', 21, NULL, 0, NULL, '2026-03-20 10:00:00', 1, '2026-04-07 07:44:02', NULL, NULL, NULL),
(330, 'expense', 'Maintenance', 'Outside period expense', 350.00, 'cash', NULL, 'vehicle_expense', 21, NULL, 0, NULL, '2026-02-10 10:00:00', 1, '2026-04-07 07:44:02', NULL, NULL, NULL),
(331, 'expense', 'Damage Charge', 'Reservation #59 - Damage Charge', 350.00, 'cash', NULL, 'reservation', 59, 'damage', 0, NULL, '2026-03-20 10:00:00', 1, '2026-04-07 07:47:05', NULL, NULL, NULL),
(332, 'expense', 'Damage Charge', 'Reservation #60 - Damage Charge', 150.00, 'cash', NULL, 'reservation', 60, 'damage', 0, NULL, '2026-03-25 14:00:00', 1, '2026-04-07 07:47:05', NULL, NULL, NULL),
(333, 'expense', 'Damage Charge', 'Reservation #61 - Damage Charge', 200.00, 'cash', NULL, 'reservation', 61, 'damage', 0, NULL, '2026-03-20 10:00:00', 1, '2026-04-07 07:47:05', NULL, NULL, NULL),
(334, 'expense', 'Security Deposit Return', 'Reservation #62 - Security Deposit Return', 500.00, 'cash', NULL, 'reservation', 62, 'damage', 0, NULL, '2026-03-22 11:00:00', 1, '2026-04-07 07:47:05', NULL, NULL, NULL),
(335, 'expense', 'Damage Charge', 'Reservation #63 - Damage Charge', 300.00, 'cash', NULL, 'reservation', 63, 'damage', 0, NULL, '2026-03-20 10:00:00', 1, '2026-04-07 07:47:05', NULL, NULL, NULL),
(336, 'expense', 'Damage Charge', 'Reservation #64 - Damage Charge', 400.00, 'cash', NULL, 'reservation', 64, 'damage', 0, NULL, '2026-02-10 10:00:00', 1, '2026-04-07 07:47:05', NULL, NULL, NULL),
(337, 'income', 'Rental Income', 'Reservation #65 - Rental payment', 800.00, 'cash', NULL, 'reservation', 65, 'delivery', 0, NULL, '2026-03-20 10:00:00', 1, '2026-04-07 07:47:05', NULL, NULL, NULL),
(338, 'income', 'Rental Income', 'Reservation #66 - Rental payment', 600.00, 'cash', NULL, 'reservation', 66, 'delivery', 0, NULL, '2026-03-25 14:00:00', 1, '2026-04-07 07:47:05', NULL, NULL, NULL),
(339, 'expense', 'Maintenance', 'Oil change and filter replacement', 300.00, 'cash', NULL, 'vehicle_expense', 27, NULL, 0, NULL, '2026-03-20 10:00:00', 1, '2026-04-07 07:48:25', NULL, NULL, NULL),
(340, 'expense', 'Service', 'Tire rotation', 200.00, 'cash', NULL, 'vehicle_expense', 27, NULL, 0, NULL, '2026-03-25 14:30:00', 1, '2026-04-07 07:48:25', NULL, NULL, NULL),
(341, 'expense', 'Damage Charge', 'Reservation #67 - Damage charge', 200.00, 'cash', NULL, 'reservation', 67, 'damage', 0, NULL, '2026-03-18 09:00:00', 1, '2026-04-07 07:48:25', NULL, NULL, NULL),
(342, 'expense', 'Repair', 'Brake pad replacement', 500.00, 'cash', NULL, 'vehicle_expense', 28, NULL, 0, NULL, '2026-03-22 11:00:00', 1, '2026-04-07 07:48:25', NULL, NULL, NULL),
(343, 'expense', 'Maintenance', 'Engine tune-up', 300.00, 'cash', NULL, 'vehicle_expense', 28, NULL, 0, NULL, '2026-03-28 15:00:00', 1, '2026-04-07 07:48:25', NULL, NULL, NULL),
(344, 'expense', 'Maintenance', 'Battery replacement', 400.00, 'cash', NULL, 'vehicle_expense', 29, NULL, 0, NULL, '2026-03-19 10:00:00', 1, '2026-04-07 07:48:25', NULL, NULL, NULL),
(345, 'expense', 'Service', 'AC gas refill', 150.00, 'cash', NULL, 'vehicle_expense', 29, NULL, 0, NULL, '2026-03-26 14:00:00', 1, '2026-04-07 07:48:25', NULL, NULL, NULL),
(346, 'expense', 'Maintenance', 'Inside period expense', 250.00, 'cash', NULL, 'vehicle_expense', 30, NULL, 0, NULL, '2026-03-20 10:00:00', 1, '2026-04-07 07:48:25', NULL, NULL, NULL),
(347, 'expense', 'Maintenance', 'Outside period expense', 350.00, 'cash', NULL, 'vehicle_expense', 30, NULL, 0, NULL, '2026-02-10 10:00:00', 1, '2026-04-07 07:48:25', NULL, NULL, NULL),
(348, 'income', 'Reservation Delivery', 'Reservation #56 - Delivery payment', 400.00, 'cash', NULL, 'reservation', 56, 'delivery', 0, 'reservation:delivery:56', '2026-04-07 15:14:43', 1, '2026-04-07 09:44:43', NULL, NULL, NULL),
(349, 'income', 'Reservation Delivery Charge', 'Reservation #56 - Delivery charge payment', 150.00, 'cash', NULL, 'reservation', 56, 'delivery_charge', 0, 'reservation:delivery_charge:56', '2026-04-07 15:14:43', 1, '2026-04-07 09:44:43', NULL, NULL, NULL),
(350, 'income', 'Security Deposit Collected', 'Reservation #56 - Security deposit collected', 82.50, 'account', 1, 'reservation', 56, 'security_deposit_in', 0, 'reservation:security_deposit_in:56', '2026-04-07 15:14:43', 1, '2026-04-07 09:44:43', NULL, NULL, NULL),
(351, 'income', 'Reservation Advance', 'Reservation #68 - Advance payment', 150.00, 'cash', NULL, 'reservation', 68, 'advance', 0, 'reservation:advance:68', '2026-04-08 11:13:33', 1, '2026-04-08 05:43:33', NULL, NULL, NULL),
(352, 'income', 'Reservation Delivery Charge', 'Reservation #68 - Delivery charge payment', 150.00, 'cash', NULL, 'reservation', 68, 'delivery_charge', 0, 'reservation:delivery_charge:68', '2026-04-08 13:08:06', 1, '2026-04-08 07:38:06', NULL, NULL, NULL),
(353, 'income', 'Security Deposit Collected', 'Reservation #68 - Security deposit collected', 22.50, 'account', 1, 'reservation', 68, 'security_deposit_in', 0, 'reservation:security_deposit_in:68', '2026-04-08 13:08:06', 1, '2026-04-08 07:38:06', NULL, NULL, NULL),
(354, 'income', 'Reservation Extension', 'Reservation #68 - Extension payment', 250.00, 'cash', NULL, 'reservation', 68, 'extension', 0, 'reservation:extension:8', '2026-04-08 15:22:45', 1, '2026-04-08 09:52:45', NULL, NULL, NULL),
(355, 'income', 'Reservation Extension', 'Reservation #68 - Extension payment', 250.00, 'cash', NULL, 'reservation', 68, 'extension', 0, 'reservation:extension:9', '2026-04-08 15:29:26', 1, '2026-04-08 09:59:26', NULL, NULL, NULL),
(356, 'income', 'Reservation Return', 'Reservation #68 - Return payment', 180062.00, 'cash', NULL, 'reservation', 68, 'return', 0, 'reservation:return:68', '2026-04-09 10:38:07', 1, '2026-04-09 05:08:07', NULL, NULL, NULL),
(357, 'expense', 'Security Deposit Returned', 'Reservation #68 - Security deposit returned', 22.50, 'account', 1, 'reservation', 68, 'security_deposit_out', 0, 'reservation:security_deposit_out:68', '2026-04-09 10:38:07', 1, '2026-04-09 05:08:07', NULL, NULL, NULL),
(358, 'income', 'Reservation Advance', 'Reservation #71 - Advance payment', 2000.00, 'cash', NULL, 'reservation', 71, 'advance', 0, 'reservation:advance:71', '2026-04-09 11:43:15', 1, '2026-04-09 06:13:15', NULL, NULL, NULL),
(359, 'expense', 'Reservation Cancellation Refund', 'Refund — Reservation #71 cancelled. Reason: testing', 2000.00, 'cash', NULL, 'reservation', 71, 'cancellation', 0, NULL, '2026-04-09 11:46:25', 1, '2026-04-09 06:16:25', NULL, NULL, NULL),
(360, 'income', 'Reservation Return', 'Reservation #56 - Return payment', 150.00, 'cash', NULL, 'reservation', 56, 'return', 0, 'reservation:return:56', '2026-04-09 13:08:55', 2, '2026-04-09 07:38:55', NULL, NULL, NULL),
(361, 'expense', 'Security Deposit Returned', 'Reservation #56 - Security deposit returned', 82.50, 'account', 1, 'reservation', 56, 'security_deposit_out', 0, 'reservation:security_deposit_out:56', '2026-04-09 13:08:55', 2, '2026-04-09 07:38:55', NULL, NULL, NULL),
(362, 'income', 'Reservation Delivery', 'Reservation #73 - Delivery payment', 5000.00, 'cash', NULL, 'reservation', 73, 'delivery', 0, 'reservation:delivery:73', '2026-04-12 12:50:05', 1, '2026-04-12 07:20:05', NULL, NULL, NULL),
(363, 'income', 'Reservation Delivery Charge', 'Reservation #73 - Delivery charge payment', 150.00, 'cash', NULL, 'reservation', 73, 'delivery_charge', 0, 'reservation:delivery_charge:73', '2026-04-12 12:50:05', 1, '2026-04-12 07:20:05', NULL, NULL, NULL),
(364, 'income', 'Security Deposit Collected', 'Reservation #73 - Security deposit collected', 772.50, 'account', 1, 'reservation', 73, 'security_deposit_in', 0, 'reservation:security_deposit_in:73', '2026-04-12 12:50:05', 1, '2026-04-12 07:20:05', NULL, NULL, NULL),
(365, 'income', 'Reservation Return', 'Reservation #73 - Return payment', 200.00, 'cash', NULL, 'reservation', 73, 'return', 0, 'reservation:return:73', '2026-04-12 12:52:25', 1, '2026-04-12 07:22:25', NULL, NULL, NULL),
(366, 'expense', 'Security Deposit Returned', 'Reservation #73 - Security deposit returned', 772.50, 'account', 1, 'reservation', 73, 'security_deposit_out', 0, 'reservation:security_deposit_out:73', '2026-04-12 12:52:25', 1, '2026-04-12 07:22:25', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `monthly_targets`
--

CREATE TABLE `monthly_targets` (
  `id` int(11) NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `target_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `monthly_targets`
--

INSERT INTO `monthly_targets` (`id`, `period_start`, `period_end`, `target_amount`, `notes`, `created_by`, `created_at`, `updated_at`) VALUES
(1, '2026-03-15', '2026-04-14', 1000000.00, NULL, 1, '2026-03-17 05:06:21', '2026-03-17 05:06:21');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `type` enum('due_today','due_soon','overdue','info','emi_due') NOT NULL DEFAULT 'info',
  `message` text NOT NULL,
  `reservation_id` int(11) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `related_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `type`, `message`, `reservation_id`, `is_read`, `related_id`, `created_at`) VALUES
(457, 'overdue', '⚠️ Overdue! withproof\'s bmw m4 was due 13 day(s) ago.', 27, 0, NULL, '2026-03-28 18:04:49'),
(458, 'overdue', '⚠️ Overdue! withproof\'s bmw m4 was due 14 day(s) ago.', 27, 0, NULL, '2026-03-29 08:54:12'),
(459, 'overdue', '⚠️ Overdue! withproof\'s bmw m4 was due 17 day(s) ago.', 27, 0, NULL, '2026-04-01 04:38:34'),
(460, 'overdue', '⚠️ Overdue! withproof\'s bmw m4 was due 18 day(s) ago.', 27, 0, NULL, '2026-04-02 08:30:31'),
(461, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 2 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-02 18:31:17'),
(462, 'overdue', '⚠️ Overdue! withproof\'s bmw m4 was due 19 day(s) ago.', 27, 0, NULL, '2026-04-02 18:31:17'),
(463, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 2 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-02 18:32:53'),
(464, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 2 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-02 18:35:30'),
(465, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 2 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-03 07:20:07'),
(466, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 2 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-03 07:20:21'),
(467, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 2 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-03 07:20:26'),
(468, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 2 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-03 07:20:57'),
(469, 'info', '📋 New reservation: hamad - new new', 55, 0, NULL, '2026-04-03 07:20:57'),
(470, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 2 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-03 07:20:57'),
(471, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 2 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-03 07:21:02'),
(472, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 2 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-03 07:22:02'),
(473, 'info', '🚗 Vehicle delivered: hamad - new new', 55, 0, NULL, '2026-04-03 07:22:02'),
(474, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 2 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-03 07:22:02'),
(475, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 2 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-03 07:22:11'),
(476, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 2 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-03 07:23:06'),
(477, 'info', '✅ Vehicle returned: hamad - new new', 55, 0, NULL, '2026-04-03 07:23:06'),
(478, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 2 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-03 07:23:09'),
(479, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 2 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-03 07:37:14'),
(480, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 2 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-03 07:37:33'),
(481, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 2 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-03 07:37:45'),
(482, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 2 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-03 07:38:08'),
(483, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 2 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-03 07:38:38'),
(484, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 2 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-03 07:39:29'),
(485, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 2 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-03 07:39:52'),
(486, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 2 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-03 09:28:55'),
(487, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 2 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-03 09:29:00'),
(488, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 2 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-03 09:29:02'),
(489, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 2 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-03 09:29:10'),
(490, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 2 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-03 09:29:22'),
(491, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 2 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-03 09:42:44'),
(492, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 2 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-03 09:42:57'),
(493, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 2 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-03 09:43:04'),
(494, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 2 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-03 09:43:06'),
(495, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 2 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-03 09:43:36'),
(496, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 2 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-03 09:43:58'),
(497, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 2 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-03 09:44:13'),
(498, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 2 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-03 09:44:16'),
(499, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 2 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-03 09:44:37'),
(500, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 2 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-03 09:44:38'),
(501, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 2 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-03 09:47:33'),
(502, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 2 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-03 09:50:37'),
(503, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 2 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-03 09:50:50'),
(504, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 2 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-03 10:05:39'),
(505, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 2 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-03 10:05:41'),
(506, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 2 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-03 10:05:53'),
(507, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 1 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-04 04:42:58'),
(508, 'overdue', '⚠️ Overdue! withproof\'s bmw m4 was due 20 day(s) ago.', 27, 0, NULL, '2026-04-04 04:42:58'),
(509, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 1 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-04 04:53:15'),
(510, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 1 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-04 05:38:57'),
(511, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 1 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-04 05:38:58'),
(512, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 1 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-04 05:39:04'),
(513, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 1 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-04 05:39:08'),
(514, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 1 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-04 05:39:20'),
(515, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 1 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-04 05:39:22'),
(516, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 1 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-04 05:39:25'),
(517, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 1 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-04 05:39:26'),
(518, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 1 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-04 05:39:45'),
(519, 'info', '📋 New reservation: added test - new new', 56, 0, NULL, '2026-04-04 05:39:45'),
(520, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 1 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-04 05:39:46'),
(521, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 1 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-04 05:39:47'),
(522, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 1 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-04 05:39:55'),
(523, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 1 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-04 05:39:57'),
(524, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 1 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-04 05:40:27'),
(525, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 1 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-04 05:40:53'),
(526, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 1 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-04 05:40:55'),
(527, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 1 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-04 05:41:01'),
(528, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 1 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-04 05:41:03'),
(529, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 1 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-04 05:46:09'),
(530, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 1 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-04 06:20:16'),
(531, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 1 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-04 06:46:42'),
(532, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 1 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-04 06:46:58'),
(533, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 1 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-04 06:47:12'),
(534, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 1 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-04 06:47:14'),
(535, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 1 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-04 06:51:38'),
(536, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 1 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-04 06:51:40'),
(537, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 1 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-04 06:53:11'),
(538, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 1 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-04 06:53:13'),
(539, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 1 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-04 06:53:15'),
(540, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 1 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-04 06:53:16'),
(541, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 1 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-04 06:53:18'),
(542, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 1 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-04 06:53:21'),
(543, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 1 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-04 06:53:25'),
(544, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 1 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-04 06:53:38'),
(545, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 1 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-04 07:15:44'),
(546, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 1 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-04 07:15:47'),
(547, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 1 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-04 07:21:22'),
(548, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 1 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-04 07:25:55'),
(549, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 1 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-04 07:25:57'),
(550, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 1 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-04 07:26:31'),
(551, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 1 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-04 07:26:48'),
(552, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 1 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-04 07:26:50'),
(553, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 1 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-04 07:27:14'),
(554, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 1 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-04 07:27:18'),
(555, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 1 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-04 07:27:20'),
(556, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 1 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-04 07:27:27'),
(557, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 1 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-04 07:27:29'),
(558, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 1 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-04 07:28:20'),
(559, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 1 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-04 07:28:23'),
(560, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 1 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-04 07:28:24'),
(561, 'emi_due', '⏰ EMI Due Soon: test (hdfc) - EMI #2 of $1,000.00 due in 1 day(s) (2026-04-05).', NULL, 0, NULL, '2026-04-04 07:28:35'),
(562, 'overdue', '⚠️ Overdue! withproof\'s bmw m4 was due 23 day(s) ago.', 27, 0, NULL, '2026-04-07 06:41:57'),
(563, 'info', '🚗 Vehicle delivered: added test - new new', 56, 0, NULL, '2026-04-07 09:44:43'),
(564, 'overdue', '⚠️ Overdue! withproof\'s bmw m4 was due 24 day(s) ago.', 27, 0, NULL, '2026-04-08 05:26:50'),
(565, 'due_soon', '🟡 Due Soon: added test\'s new new is due in 2 day(s).', 56, 0, NULL, '2026-04-08 05:26:50'),
(566, 'info', '📋 New reservation: added test - Thar Roxx', 68, 0, NULL, '2026-04-08 05:43:33'),
(567, 'info', '🚗 Vehicle delivered: added test - Test Car Budget', 68, 0, NULL, '2026-04-08 07:38:06'),
(568, 'info', '📋 New reservation: added test - added added', 69, 0, NULL, '2026-04-08 10:27:13'),
(569, 'info', '📋 New reservation: added test - bmw m4', 70, 0, NULL, '2026-04-08 11:39:17'),
(570, 'overdue', '⚠️ Overdue! withproof\'s bmw m4 was due 25 day(s) ago.', 27, 0, NULL, '2026-04-09 04:39:01'),
(571, 'due_soon', '🟡 Due Soon: added test\'s new new is due in 1 day(s).', 56, 0, NULL, '2026-04-09 04:39:01'),
(572, 'info', '✅ Vehicle returned: added test - Test Car Budget', 68, 0, NULL, '2026-04-09 05:08:07'),
(573, 'info', '📋 New reservation: john - ferrari f', 71, 0, NULL, '2026-04-09 06:13:15'),
(574, 'info', '✅ Vehicle returned: added test - new new', 56, 0, NULL, '2026-04-09 07:38:55'),
(575, 'info', '📋 New reservation: req - ferrari f', 72, 0, NULL, '2026-04-09 08:16:10'),
(576, 'overdue', '⚠️ Overdue! withproof\'s bmw m4 was due 26 day(s) ago.', 27, 0, NULL, '2026-04-10 08:57:33'),
(577, 'overdue', '⚠️ Overdue! withproof\'s bmw m4 was due 28 day(s) ago.', 27, 0, NULL, '2026-04-12 07:01:22'),
(578, 'info', '🚗 Vehicle delivered: hamad - bmw m4', 73, 0, NULL, '2026-04-12 07:20:05'),
(579, 'info', '✅ Vehicle returned: hamad - bmw m4', 73, 0, NULL, '2026-04-12 07:22:25'),
(580, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 2 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-14 05:01:27'),
(581, 'overdue', '⚠️ Overdue! withproof\'s bmw m4 was due 30 day(s) ago.', 27, 0, NULL, '2026-04-14 05:01:27'),
(582, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 2 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-14 05:01:29'),
(583, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 2 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-14 05:01:30'),
(584, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 2 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-14 05:01:44'),
(585, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 2 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-14 10:02:11'),
(586, 'info', 'GPS check pending: withproof\'s bmw m4 (Reservation #27) — 0/3 completed today.', 27, 0, NULL, '2026-04-14 10:02:16'),
(587, 'info', 'GPS check pending: hamad\'s bmw m4 (Reservation #29) — 0/3 completed today.', 29, 0, NULL, '2026-04-14 10:02:16'),
(588, 'info', 'GPS check pending: hamad\'s Mercedes-Benz S-Class S 350d (Reservation #30) — 0/3 completed today.', 30, 0, NULL, '2026-04-14 10:02:16'),
(589, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 2 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-14 10:02:16'),
(590, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 2 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-14 10:05:44'),
(591, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 2 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-14 10:06:07'),
(592, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 1 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-15 05:13:05'),
(593, 'overdue', '⚠️ Overdue! withproof\'s bmw m4 was due 31 day(s) ago.', 27, 0, NULL, '2026-04-15 05:13:05'),
(594, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 1 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-15 05:13:09'),
(595, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 1 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-15 05:13:15'),
(596, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 1 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-15 05:13:23'),
(597, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 1 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-15 05:19:33'),
(598, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 1 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-15 05:54:47'),
(599, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 1 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-15 05:55:00'),
(600, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 1 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-15 05:55:18'),
(601, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 1 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-15 05:55:30'),
(602, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 1 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-15 05:55:31'),
(603, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 1 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-15 05:55:38'),
(604, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 1 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-15 05:55:39'),
(605, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 1 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-15 05:55:40'),
(606, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 1 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-15 05:55:43'),
(607, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 1 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-15 05:55:45'),
(608, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 1 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-15 05:56:12'),
(609, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 1 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-15 05:56:26'),
(610, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 1 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-15 05:57:47'),
(611, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 1 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-15 05:58:06'),
(612, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 1 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-15 05:58:20'),
(613, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 1 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-15 05:58:42'),
(614, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 1 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-15 05:58:51'),
(615, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 1 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-15 06:00:28'),
(616, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 1 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-15 06:00:37'),
(617, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 1 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-15 06:00:38'),
(618, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 1 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-15 06:00:41'),
(619, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 1 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-15 06:01:17'),
(620, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 1 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-15 06:01:19'),
(621, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 1 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-15 06:01:41'),
(622, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 1 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-15 06:02:30'),
(623, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 1 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-15 06:02:51'),
(624, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 1 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-15 06:04:34'),
(625, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 1 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-15 06:07:29'),
(626, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 1 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-15 14:56:33'),
(627, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 1 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-15 14:56:36'),
(628, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 1 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-15 14:56:44'),
(629, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 1 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-15 14:56:54'),
(630, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 1 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-15 15:55:42'),
(631, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 1 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-15 15:55:46'),
(632, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 1 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-15 15:56:47'),
(633, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 1 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-15 15:56:57'),
(634, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 1 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-15 16:01:18'),
(635, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 1 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-15 16:01:39'),
(636, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 1 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-15 16:01:41'),
(637, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 1 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-15 16:01:43'),
(638, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 1 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-15 16:02:22'),
(639, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 1 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-15 16:09:15'),
(640, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 1 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-15 16:09:21'),
(641, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 1 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-15 16:12:58'),
(642, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 1 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-15 16:13:03'),
(643, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 1 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-15 16:15:41'),
(644, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 1 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-15 16:15:45'),
(645, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 1 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-15 16:15:51'),
(646, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 1 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-15 16:15:56'),
(647, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 1 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-15 16:16:09'),
(648, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 1 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-15 16:32:00'),
(649, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 1 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-15 16:32:05'),
(650, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 1 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-15 16:32:12'),
(651, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 1 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-15 16:32:50'),
(652, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 1 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-15 16:32:55'),
(653, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 1 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-15 16:33:01'),
(654, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 1 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-15 16:34:20'),
(655, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 1 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-15 16:34:27'),
(656, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 1 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-15 16:44:58'),
(657, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 1 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-15 16:45:00'),
(658, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 1 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-15 16:45:05'),
(659, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 1 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-15 16:47:02'),
(660, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 1 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-15 16:47:06'),
(661, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 1 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-15 16:48:17'),
(662, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 1 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-15 16:59:41'),
(663, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 1 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-15 16:59:46'),
(664, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 1 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-15 17:00:19'),
(665, 'emi_due', '⏰ EMI Due Soon: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 due in 1 day(s) (2026-04-16).', NULL, 0, NULL, '2026-04-15 17:03:57'),
(666, 'emi_due', '💰 EMI Due Today: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 is due today!', NULL, 0, NULL, '2026-04-16 04:38:45'),
(667, 'emi_due', '⏰ EMI Due Soon: doubling check (hdfc) - EMI #2 of $1,000.00 due in 2 day(s) (2026-04-18).', NULL, 0, NULL, '2026-04-16 04:38:45'),
(668, 'overdue', '⚠️ Overdue! withproof\'s bmw m4 was due 32 day(s) ago.', 27, 0, NULL, '2026-04-16 04:38:45'),
(669, 'emi_due', '💰 EMI Due Today: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 is due today!', NULL, 0, NULL, '2026-04-16 04:38:54'),
(670, 'emi_due', '⏰ EMI Due Soon: doubling check (hdfc) - EMI #2 of $1,000.00 due in 2 day(s) (2026-04-18).', NULL, 0, NULL, '2026-04-16 04:38:54'),
(671, 'emi_due', '💰 EMI Due Today: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 is due today!', NULL, 0, NULL, '2026-04-16 04:39:03'),
(672, 'emi_due', '⏰ EMI Due Soon: doubling check (hdfc) - EMI #2 of $1,000.00 due in 2 day(s) (2026-04-18).', NULL, 0, NULL, '2026-04-16 04:39:03'),
(673, 'emi_due', '💰 EMI Due Today: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 is due today!', NULL, 0, NULL, '2026-04-16 04:39:05'),
(674, 'emi_due', '⏰ EMI Due Soon: doubling check (hdfc) - EMI #2 of $1,000.00 due in 2 day(s) (2026-04-18).', NULL, 0, NULL, '2026-04-16 04:39:05'),
(675, 'emi_due', '💰 EMI Due Today: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 is due today!', NULL, 0, NULL, '2026-04-16 04:39:10'),
(676, 'emi_due', '⏰ EMI Due Soon: doubling check (hdfc) - EMI #2 of $1,000.00 due in 2 day(s) (2026-04-18).', NULL, 0, NULL, '2026-04-16 04:39:10'),
(677, 'emi_due', '💰 EMI Due Today: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 is due today!', NULL, 0, NULL, '2026-04-16 04:39:13'),
(678, 'emi_due', '⏰ EMI Due Soon: doubling check (hdfc) - EMI #2 of $1,000.00 due in 2 day(s) (2026-04-18).', NULL, 0, NULL, '2026-04-16 04:39:13'),
(679, 'emi_due', '💰 EMI Due Today: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 is due today!', NULL, 0, NULL, '2026-04-16 04:39:19'),
(680, 'emi_due', '⏰ EMI Due Soon: doubling check (hdfc) - EMI #2 of $1,000.00 due in 2 day(s) (2026-04-18).', NULL, 0, NULL, '2026-04-16 04:39:19'),
(681, 'emi_due', '💰 EMI Due Today: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 is due today!', NULL, 0, NULL, '2026-04-16 04:40:30'),
(682, 'emi_due', '⏰ EMI Due Soon: doubling check (hdfc) - EMI #2 of $1,000.00 due in 2 day(s) (2026-04-18).', NULL, 0, NULL, '2026-04-16 04:40:30'),
(683, 'emi_due', '💰 EMI Due Today: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 is due today!', NULL, 0, NULL, '2026-04-16 04:45:16'),
(684, 'emi_due', '⏰ EMI Due Soon: doubling check (hdfc) - EMI #2 of $1,000.00 due in 2 day(s) (2026-04-18).', NULL, 0, NULL, '2026-04-16 04:45:16'),
(685, 'emi_due', '💰 EMI Due Today: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 is due today!', NULL, 0, NULL, '2026-04-16 04:45:33'),
(686, 'emi_due', '⏰ EMI Due Soon: doubling check (hdfc) - EMI #2 of $1,000.00 due in 2 day(s) (2026-04-18).', NULL, 0, NULL, '2026-04-16 04:45:33'),
(687, 'emi_due', '💰 EMI Due Today: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 is due today!', NULL, 0, NULL, '2026-04-16 05:11:58'),
(688, 'emi_due', '⏰ EMI Due Soon: doubling check (hdfc) - EMI #2 of $1,000.00 due in 2 day(s) (2026-04-18).', NULL, 0, NULL, '2026-04-16 05:11:58'),
(689, 'emi_due', '💰 EMI Due Today: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 is due today!', NULL, 0, NULL, '2026-04-16 05:12:00'),
(690, 'emi_due', '⏰ EMI Due Soon: doubling check (hdfc) - EMI #2 of $1,000.00 due in 2 day(s) (2026-04-18).', NULL, 0, NULL, '2026-04-16 05:12:00'),
(691, 'emi_due', '💰 EMI Due Today: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 is due today!', NULL, 0, NULL, '2026-04-16 05:12:05'),
(692, 'emi_due', '⏰ EMI Due Soon: doubling check (hdfc) - EMI #2 of $1,000.00 due in 2 day(s) (2026-04-18).', NULL, 0, NULL, '2026-04-16 05:12:05'),
(693, 'emi_due', '💰 EMI Due Today: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 is due today!', NULL, 0, NULL, '2026-04-16 05:12:06'),
(694, 'emi_due', '⏰ EMI Due Soon: doubling check (hdfc) - EMI #2 of $1,000.00 due in 2 day(s) (2026-04-18).', NULL, 0, NULL, '2026-04-16 05:12:06'),
(695, 'emi_due', '💰 EMI Due Today: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 is due today!', NULL, 0, NULL, '2026-04-16 05:12:10'),
(696, 'emi_due', '⏰ EMI Due Soon: doubling check (hdfc) - EMI #2 of $1,000.00 due in 2 day(s) (2026-04-18).', NULL, 0, NULL, '2026-04-16 05:12:10'),
(697, 'emi_due', '💰 EMI Due Today: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 is due today!', NULL, 0, NULL, '2026-04-16 05:12:17'),
(698, 'emi_due', '⏰ EMI Due Soon: doubling check (hdfc) - EMI #2 of $1,000.00 due in 2 day(s) (2026-04-18).', NULL, 0, NULL, '2026-04-16 05:12:17'),
(699, 'emi_due', '💰 EMI Due Today: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 is due today!', NULL, 0, NULL, '2026-04-16 05:12:20'),
(700, 'emi_due', '⏰ EMI Due Soon: doubling check (hdfc) - EMI #2 of $1,000.00 due in 2 day(s) (2026-04-18).', NULL, 0, NULL, '2026-04-16 05:12:20'),
(701, 'emi_due', '💰 EMI Due Today: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 is due today!', NULL, 0, NULL, '2026-04-16 05:12:30'),
(702, 'emi_due', '⏰ EMI Due Soon: doubling check (hdfc) - EMI #2 of $1,000.00 due in 2 day(s) (2026-04-18).', NULL, 0, NULL, '2026-04-16 05:12:30'),
(703, 'emi_due', '💰 EMI Due Today: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 is due today!', NULL, 0, NULL, '2026-04-16 05:12:52'),
(704, 'emi_due', '⏰ EMI Due Soon: doubling check (hdfc) - EMI #2 of $1,000.00 due in 2 day(s) (2026-04-18).', NULL, 0, NULL, '2026-04-16 05:12:52'),
(705, 'emi_due', '💰 EMI Due Today: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 is due today!', NULL, 0, NULL, '2026-04-16 05:13:03'),
(706, 'emi_due', '⏰ EMI Due Soon: doubling check (hdfc) - EMI #2 of $1,000.00 due in 2 day(s) (2026-04-18).', NULL, 0, NULL, '2026-04-16 05:13:03'),
(707, 'emi_due', '💰 EMI Due Today: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 is due today!', NULL, 0, NULL, '2026-04-16 05:14:48'),
(708, 'emi_due', '⏰ EMI Due Soon: doubling check (hdfc) - EMI #2 of $1,000.00 due in 2 day(s) (2026-04-18).', NULL, 0, NULL, '2026-04-16 05:14:48'),
(709, 'emi_due', '💰 EMI Due Today: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 is due today!', NULL, 0, NULL, '2026-04-16 05:14:53'),
(710, 'emi_due', '⏰ EMI Due Soon: doubling check (hdfc) - EMI #2 of $1,000.00 due in 2 day(s) (2026-04-18).', NULL, 0, NULL, '2026-04-16 05:14:53'),
(711, 'emi_due', '💰 EMI Due Today: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 is due today!', NULL, 0, NULL, '2026-04-16 05:25:40'),
(712, 'emi_due', '⏰ EMI Due Soon: doubling check (hdfc) - EMI #2 of $1,000.00 due in 2 day(s) (2026-04-18).', NULL, 0, NULL, '2026-04-16 05:25:40'),
(713, 'emi_due', '💰 EMI Due Today: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 is due today!', NULL, 0, NULL, '2026-04-16 05:25:45'),
(714, 'emi_due', '⏰ EMI Due Soon: doubling check (hdfc) - EMI #2 of $1,000.00 due in 2 day(s) (2026-04-18).', NULL, 0, NULL, '2026-04-16 05:25:45'),
(715, 'emi_due', '💰 EMI Due Today: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 is due today!', NULL, 0, NULL, '2026-04-16 05:26:00'),
(716, 'emi_due', '⏰ EMI Due Soon: doubling check (hdfc) - EMI #2 of $1,000.00 due in 2 day(s) (2026-04-18).', NULL, 0, NULL, '2026-04-16 05:26:00'),
(717, 'emi_due', '💰 EMI Due Today: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 is due today!', NULL, 0, NULL, '2026-04-16 05:26:06'),
(718, 'emi_due', '⏰ EMI Due Soon: doubling check (hdfc) - EMI #2 of $1,000.00 due in 2 day(s) (2026-04-18).', NULL, 0, NULL, '2026-04-16 05:26:06'),
(719, 'emi_due', '💰 EMI Due Today: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 is due today!', NULL, 0, NULL, '2026-04-16 05:26:10'),
(720, 'emi_due', '⏰ EMI Due Soon: doubling check (hdfc) - EMI #2 of $1,000.00 due in 2 day(s) (2026-04-18).', NULL, 0, NULL, '2026-04-16 05:26:10'),
(721, 'emi_due', '💰 EMI Due Today: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 is due today!', NULL, 0, NULL, '2026-04-16 05:41:36'),
(722, 'emi_due', '⏰ EMI Due Soon: doubling check (hdfc) - EMI #2 of $1,000.00 due in 2 day(s) (2026-04-18).', NULL, 0, NULL, '2026-04-16 05:41:36'),
(723, 'emi_due', '💰 EMI Due Today: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 is due today!', NULL, 0, NULL, '2026-04-16 06:03:01'),
(724, 'emi_due', '⏰ EMI Due Soon: doubling check (hdfc) - EMI #2 of $1,000.00 due in 2 day(s) (2026-04-18).', NULL, 0, NULL, '2026-04-16 06:03:01'),
(725, 'emi_due', '💰 EMI Due Today: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 is due today!', NULL, 0, NULL, '2026-04-16 06:03:08'),
(726, 'emi_due', '⏰ EMI Due Soon: doubling check (hdfc) - EMI #2 of $1,000.00 due in 2 day(s) (2026-04-18).', NULL, 0, NULL, '2026-04-16 06:03:08'),
(727, 'emi_due', '💰 EMI Due Today: Test BMW EMI (HDFC Bank) - EMI #2 of $5,000.00 is due today!', NULL, 0, NULL, '2026-04-16 06:03:31'),
(728, 'emi_due', '⏰ EMI Due Soon: doubling check (hdfc) - EMI #2 of $1,000.00 due in 2 day(s) (2026-04-18).', NULL, 0, NULL, '2026-04-16 06:03:31');

-- --------------------------------------------------------

--
-- Table structure for table `papers`
--

CREATE TABLE `papers` (
  `id` int(11) NOT NULL,
  `vehicle_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `cancellation_reason` text DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancellation_by` int(11) DEFAULT NULL,
  `refund_amount` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payroll`
--

CREATE TABLE `payroll` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `month` tinyint(4) NOT NULL,
  `year` smallint(6) NOT NULL,
  `basic_salary` decimal(10,2) NOT NULL DEFAULT 0.00,
  `hours_worked` decimal(10,4) DEFAULT NULL COMMENT 'Hours worked for hourly staff (NULL for fixed salary staff)',
  `incentive` decimal(10,2) NOT NULL DEFAULT 0.00,
  `overtime_pay` decimal(10,2) NOT NULL DEFAULT 0.00,
  `allowances` decimal(10,2) NOT NULL DEFAULT 0.00,
  `deductions` decimal(10,2) NOT NULL DEFAULT 0.00,
  `advance_deducted` decimal(10,2) DEFAULT NULL,
  `net_salary` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payable_salary` decimal(10,2) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('Pending','Paid') NOT NULL DEFAULT 'Pending',
  `payment_date` datetime DEFAULT NULL,
  `paid_from_account_id` int(11) DEFAULT NULL,
  `ledger_entry_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payroll_advances`
--

CREATE TABLE `payroll_advances` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `payroll_id` int(11) DEFAULT NULL,
  `month` tinyint(4) NOT NULL,
  `year` smallint(6) NOT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `remaining_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('pending','partially_recovered','recovered') NOT NULL DEFAULT 'pending',
  `note` varchar(255) DEFAULT NULL,
  `given_at` datetime NOT NULL,
  `recovered_at` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `bank_account_id` int(11) DEFAULT NULL,
  `ledger_entry_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payroll_advances`
--

INSERT INTO `payroll_advances` (`id`, `user_id`, `payroll_id`, `month`, `year`, `amount`, `remaining_amount`, `status`, `note`, `given_at`, `recovered_at`, `created_by`, `bank_account_id`, `ledger_entry_id`, `created_at`) VALUES
(9, 2, NULL, 3, 2026, 1000.00, 1000.00, 'pending', NULL, '2026-03-26 10:45:57', NULL, 1, 1, 284, '2026-03-26 05:15:57'),
(10, 2, NULL, 1, 2026, 500.00, 500.00, 'pending', NULL, '2026-03-26 10:54:07', NULL, 1, 1, 285, '2026-03-26 05:24:07'),
(114, 2, NULL, 3, 2026, 100.00, 100.00, 'pending', NULL, '2026-03-26 15:11:02', NULL, 1, 1, 286, '2026-03-26 09:41:02');

-- --------------------------------------------------------

--
-- Table structure for table `remember_tokens`
--

CREATE TABLE `remember_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `selector` varchar(32) NOT NULL,
  `validator_hash` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reservations`
--

CREATE TABLE `reservations` (
  `id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `vehicle_id` int(11) NOT NULL,
  `rental_type` varchar(50) NOT NULL DEFAULT 'daily',
  `status` enum('pending','confirmed','active','completed','cancelled') NOT NULL DEFAULT 'confirmed',
  `start_date` datetime NOT NULL,
  `end_date` datetime NOT NULL,
  `actual_end_date` datetime DEFAULT NULL,
  `total_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `delivery_charge` decimal(10,2) NOT NULL DEFAULT 0.00,
  `delivery_manual_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `delivery_discount_type` enum('percent','amount') DEFAULT NULL,
  `delivery_discount_value` decimal(10,2) NOT NULL DEFAULT 0.00,
  `delivery_payment_method` enum('cash','account','credit') DEFAULT NULL,
  `delivery_paid_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `delivery_deposit` decimal(10,2) NOT NULL DEFAULT 0.00,
  `deposit_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `deposit_returned` decimal(10,2) NOT NULL DEFAULT 0.00,
  `deposit_deducted` decimal(10,2) NOT NULL DEFAULT 0.00,
  `deposit_held` decimal(10,2) NOT NULL DEFAULT 0.00,
  `deposit_used_for_extension` decimal(10,2) NOT NULL DEFAULT 0.00,
  `deposit_held_at` datetime DEFAULT NULL,
  `deposit_hold_reason` text DEFAULT NULL,
  `overdue_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `chellan_amount` decimal(10,2) DEFAULT 0.00,
  `km_limit` int(11) DEFAULT NULL,
  `extra_km_price` decimal(10,2) DEFAULT NULL,
  `km_driven` int(11) DEFAULT NULL,
  `km_overage_charge` decimal(10,2) DEFAULT 0.00,
  `damage_charge` decimal(10,2) DEFAULT 0.00,
  `additional_charge` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount_type` enum('percent','amount') DEFAULT NULL,
  `discount_value` decimal(10,2) DEFAULT 0.00,
  `voucher_applied` decimal(10,2) NOT NULL DEFAULT 0.00,
  `return_voucher_applied` decimal(10,2) NOT NULL DEFAULT 0.00,
  `return_payment_method` enum('cash','account','credit') DEFAULT NULL,
  `return_paid_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `client_satisfied` enum('yes','no') DEFAULT NULL,
  `client_comment` varchar(255) DEFAULT NULL,
  `early_return_credit` decimal(10,2) NOT NULL DEFAULT 0.00,
  `voucher_credit_issued` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `cancellation_reason` text DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancellation_by` int(11) DEFAULT NULL,
  `refund_amount` decimal(10,2) DEFAULT NULL,
  `delivery_location` varchar(255) DEFAULT NULL,
  `delivered_at` datetime DEFAULT NULL,
  `advance_paid` decimal(10,2) NOT NULL DEFAULT 0.00,
  `advance_payment_method` enum('cash','account','credit') DEFAULT NULL,
  `advance_bank_account_id` int(11) DEFAULT NULL,
  `note` varchar(1000) DEFAULT NULL,
  `delivery_charge_prepaid` decimal(10,2) NOT NULL DEFAULT 0.00,
  `delivery_prepaid_payment_method` enum('cash','account','credit') DEFAULT NULL,
  `delivery_prepaid_bank_account_id` int(11) DEFAULT NULL,
  `extension_paid_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `deposit_held_resolved_at` datetime DEFAULT NULL,
  `deposit_held_action` varchar(20) DEFAULT NULL,
  `booking_discount_type` varchar(10) DEFAULT NULL COMMENT 'percent or amount',
  `booking_discount_value` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'raw input value',
  `return_location` varchar(255) DEFAULT NULL COMMENT 'Optional return location for reference',
  `additional_note` text DEFAULT NULL COMMENT 'Optional general note (max 1000 chars in UI)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reservations`
--

INSERT INTO `reservations` (`id`, `client_id`, `vehicle_id`, `rental_type`, `status`, `start_date`, `end_date`, `actual_end_date`, `total_price`, `delivery_charge`, `delivery_manual_amount`, `delivery_discount_type`, `delivery_discount_value`, `delivery_payment_method`, `delivery_paid_amount`, `delivery_deposit`, `deposit_amount`, `deposit_returned`, `deposit_deducted`, `deposit_held`, `deposit_used_for_extension`, `deposit_held_at`, `deposit_hold_reason`, `overdue_amount`, `chellan_amount`, `km_limit`, `extra_km_price`, `km_driven`, `km_overage_charge`, `damage_charge`, `additional_charge`, `discount_type`, `discount_value`, `voucher_applied`, `return_voucher_applied`, `return_payment_method`, `return_paid_amount`, `client_satisfied`, `client_comment`, `early_return_credit`, `voucher_credit_issued`, `created_at`, `updated_at`, `cancellation_reason`, `cancelled_at`, `cancellation_by`, `refund_amount`, `delivery_location`, `delivered_at`, `advance_paid`, `advance_payment_method`, `advance_bank_account_id`, `note`, `delivery_charge_prepaid`, `delivery_prepaid_payment_method`, `delivery_prepaid_bank_account_id`, `extension_paid_amount`, `deposit_held_resolved_at`, `deposit_held_action`, `booking_discount_type`, `booking_discount_value`, `return_location`, `additional_note`) VALUES
(1, 1, 1, 'daily', 'completed', '2026-03-05 16:05:00', '2026-03-10 01:00:00', '2026-03-06 13:45:00', 6.00, 0.00, 0.00, NULL, 0.00, 'account', 6.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, NULL, 0.00, 0.00, 100, 50.00, 200, 5000.00, 0.00, 0.00, NULL, 0.00, 0.00, 0.00, 'cash', 5000.00, NULL, NULL, 4.76, 4.76, '2026-03-05 05:06:17', '2026-03-06 08:18:32', NULL, NULL, NULL, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL),
(2, 2, 2, 'daily', 'completed', '2026-03-06 07:00:00', '2026-03-15 07:00:00', '2026-03-15 13:45:00', 1000.00, 0.00, 0.00, NULL, 0.00, 'account', 1000.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, NULL, 100.00, 0.00, 100, 50.00, 100, 0.00, 500.00, 100.00, NULL, 0.00, 0.00, 0.00, 'credit', 700.00, NULL, NULL, 0.00, 0.00, '2026-03-05 05:08:20', '2026-03-06 08:17:24', NULL, NULL, NULL, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL),
(3, 1, 2, 'daily', 'completed', '2026-03-06 07:00:00', '2026-03-10 07:00:00', '2026-03-06 14:15:00', 500.00, 150.00, 0.00, NULL, 0.00, 'credit', 650.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, NULL, 0.00, 0.00, 100, 50.00, 1, 0.00, 0.00, 0.00, NULL, 0.00, 0.00, 0.00, NULL, 0.00, NULL, NULL, 462.24, 462.24, '2026-03-06 08:44:41', '2026-03-06 08:47:08', NULL, NULL, NULL, NULL, 'thrissur', NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL),
(4, 1, 2, '30day', 'completed', '2026-03-06 18:10:00', '2026-04-05 18:10:00', '2026-03-10 00:50:00', 3000.00, 0.00, 0.00, NULL, 0.00, 'cash', 3000.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, NULL, 0.00, 0.00, NULL, NULL, 0, 0.00, 0.00, 2000.00, 'amount', 500.00, 0.00, 0.00, 'account', 1500.00, NULL, NULL, 2672.22, 2672.22, '2026-03-06 12:44:36', '2026-03-09 19:22:43', NULL, NULL, NULL, NULL, NULL, '2026-03-07 11:15:54', 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL),
(5, 1, 1, 'daily', 'completed', '2026-02-07 11:15:00', '2026-03-01 01:00:00', '2026-03-10 00:45:00', 281.00, 0.00, 0.00, NULL, 0.00, 'cash', 281.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, NULL, 10.00, 0.00, 1, 1.00, 1000, 999.00, 0.00, 2000.00, 'amount', 1000.00, 0.00, 0.00, 'account', 2009.00, NULL, NULL, 0.00, 0.00, '2026-03-07 05:46:18', '2026-03-09 19:21:25', NULL, NULL, NULL, NULL, NULL, '2026-03-07 11:16:27', 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL),
(6, 1, 3, 'daily', 'completed', '2026-03-07 23:25:00', '2026-03-12 01:00:00', '2026-03-08 01:40:00', 1200.00, 0.00, 450.00, NULL, 0.00, 'cash', 1650.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, NULL, 0.00, 0.00, 100, 12.00, NULL, 0.00, 0.00, 0.00, NULL, 0.00, 0.00, 0.00, NULL, 0.00, NULL, NULL, 1172.33, 1172.33, '2026-03-07 17:59:58', '2026-03-07 20:12:15', NULL, NULL, NULL, NULL, NULL, '2026-03-07 23:32:55', 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL),
(7, 2, 4, '7day', 'completed', '2026-03-07 23:55:00', '2026-03-14 23:55:00', '2026-03-08 00:20:00', 700.00, 0.00, 0.00, NULL, 0.00, '', 700.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, NULL, 0.00, 0.00, 12, 12.00, NULL, 0.00, 0.00, 1000.00, NULL, 0.00, 0.00, 0.00, 'cash', 1000.00, NULL, NULL, 698.26, 698.26, '2026-03-07 18:28:12', '2026-03-07 18:52:07', NULL, NULL, NULL, NULL, NULL, '2026-03-07 23:59:39', 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL),
(8, 1, 4, 'daily', 'completed', '2026-03-08 00:20:00', '2026-03-25 01:00:00', '2026-03-08 00:25:00', 1900.00, 0.00, 0.00, 'percent', 0.00, '', 1900.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, NULL, 0.00, 0.00, 122, 12.00, NULL, 0.00, 0.00, 0.00, NULL, 0.00, 0.00, 0.00, NULL, 0.00, NULL, NULL, 1899.61, 1899.61, '2026-03-07 18:54:54', '2026-03-07 19:00:12', NULL, NULL, NULL, NULL, 'thrissur', '2026-03-08 00:27:32', 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL),
(9, 6, 4, 'daily', 'completed', '2026-03-08 00:35:00', '2026-03-15 01:00:00', '2026-03-08 00:40:00', 900.00, 0.00, 0.00, NULL, 0.00, '', 900.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, NULL, 0.00, 0.00, 12, 12.00, NULL, 0.00, 0.00, 600.00, NULL, 0.00, 0.00, 0.00, 'cash', 600.00, NULL, NULL, 899.55, 899.55, '2026-03-07 19:07:41', '2026-03-07 19:14:00', NULL, NULL, NULL, NULL, 'thrissur', '2026-03-08 00:42:13', 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL),
(10, 1, 4, 'daily', 'completed', '2026-03-08 00:45:00', '2026-03-12 01:00:00', '2026-03-08 00:45:00', 600.00, 0.00, 0.00, NULL, 0.00, '', 600.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, NULL, 0.00, 0.00, 1, 11.00, NULL, 0.00, 0.00, 0.00, NULL, 0.00, 0.00, 0.00, NULL, 0.00, NULL, NULL, 600.00, 600.00, '2026-03-07 19:17:47', '2026-03-07 19:19:43', NULL, NULL, NULL, NULL, '23', '2026-03-08 00:48:48', 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL),
(11, 1, 4, 'daily', 'completed', '2026-03-08 01:00:00', '2026-03-12 01:00:00', '2026-03-08 01:05:00', 500.00, 0.00, 0.00, NULL, 0.00, '', 500.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, NULL, 0.00, 0.00, 12, 12.00, NULL, 0.00, 0.00, 1000.00, NULL, 0.00, 0.00, 0.00, '', 1000.00, NULL, NULL, 499.57, 499.57, '2026-03-07 19:34:51', '2026-03-07 19:39:30', NULL, NULL, NULL, NULL, 'sds', '2026-03-08 01:07:51', 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL),
(12, 5, 4, 'daily', 'completed', '2026-03-08 01:20:00', '2026-03-15 01:00:00', '2026-03-08 01:20:00', 800.00, 0.00, 0.00, 'amount', 800.00, NULL, 0.00, 0.00, 1000.00, 1000.00, 0.00, 0.00, 0.00, NULL, NULL, 0.00, 0.00, 12, 1.00, 12, 0.00, 0.00, 0.00, NULL, 0.00, 0.00, 0.00, NULL, 0.00, NULL, NULL, 800.00, 800.00, '2026-03-07 19:52:34', '2026-03-07 19:55:32', NULL, NULL, NULL, NULL, 'sdsd', '2026-03-08 01:23:49', 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL),
(13, 1, 6, 'daily', 'completed', '2026-03-08 01:40:00', '2026-03-12 01:00:00', '2026-03-08 01:55:00', 555.00, 0.00, 0.00, NULL, 0.00, 'cash', 555.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, NULL, 0.00, 0.00, 12, 12.00, NULL, 0.00, 0.00, 0.00, NULL, 0.00, 0.00, 0.00, NULL, 0.00, NULL, NULL, 553.54, 553.54, '2026-03-07 20:12:43', '2026-03-07 20:30:04', NULL, NULL, NULL, NULL, 'dfdfe', '2026-03-08 01:43:20', 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL),
(14, 1, 2, 'daily', 'completed', '2026-04-08 01:50:00', '2026-04-15 01:00:00', '2026-03-08 01:55:00', 800.00, 0.00, 0.00, NULL, 0.00, 'cash', 800.00, 0.00, 120.00, 120.00, 0.00, 0.00, 0.00, NULL, NULL, 0.00, 0.00, 12, 12.00, NULL, 0.00, 0.00, 0.00, NULL, 0.00, 0.00, 0.00, NULL, 0.00, NULL, NULL, 800.00, 800.00, '2026-03-07 20:25:05', '2026-03-07 20:27:53', NULL, NULL, NULL, NULL, 'ssd', '2026-03-08 01:56:39', 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL),
(15, 1, 5, '30day', 'completed', '2026-03-08 21:35:00', '2026-04-07 21:35:00', '2026-03-08 21:40:00', 30.00, 0.00, 0.00, NULL, 0.00, 'cash', 30.00, 0.00, 4.50, 4.50, 0.00, 0.00, 0.00, NULL, NULL, 0.00, 0.00, 1, 1.00, NULL, 0.00, 0.00, 0.00, NULL, 0.00, 0.00, 0.00, NULL, 0.00, NULL, NULL, 30.00, 30.00, '2026-03-08 16:10:10', '2026-03-08 16:13:08', NULL, NULL, NULL, NULL, 'sdsd', '2026-03-08 21:40:56', 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL),
(16, 1, 6, 'daily', 'completed', '2026-03-08 21:45:00', '2026-03-20 01:00:00', '2026-03-08 21:45:00', 1443.00, 0.00, 0.00, NULL, 0.00, 'cash', 1443.00, 0.00, 216.45, 216.45, 0.00, 0.00, 0.00, NULL, NULL, 0.00, 0.00, 12, 12.00, NULL, 0.00, 0.00, 0.00, NULL, 0.00, 0.00, 0.00, NULL, 0.00, NULL, NULL, 1443.00, 1443.00, '2026-03-08 16:15:25', '2026-03-08 16:16:41', NULL, NULL, NULL, NULL, 'sdsd', '2026-03-08 21:46:04', 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL),
(17, 1, 6, 'daily', 'completed', '2026-03-08 22:25:00', '2026-11-20 01:00:00', '2026-03-08 22:30:00', 28638.00, 0.00, 0.00, NULL, 0.00, 'cash', 28638.00, 0.00, 4295.70, 4295.70, 0.00, 0.00, 0.00, NULL, NULL, 0.00, 0.00, 12, 12.00, NULL, 0.00, 0.00, 0.00, NULL, 0.00, 0.00, 0.00, NULL, 0.00, NULL, NULL, 28637.61, 28637.61, '2026-03-08 16:59:31', '2026-03-08 17:00:57', NULL, NULL, NULL, NULL, 'sd', '2026-03-08 22:30:07', 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL),
(18, 1, 5, 'daily', 'completed', '2026-03-09 13:35:00', '2026-05-20 01:00:00', '2026-03-10 00:50:00', 73.00, 0.00, 0.00, NULL, 0.00, 'cash', 73.00, 0.00, 10.95, 10.95, 0.00, 0.00, 0.00, NULL, NULL, 0.00, 1000.00, 12, 12.00, NULL, 0.00, 0.00, 1000.00, 'amount', 500.00, 0.00, 0.00, NULL, 1500.00, NULL, NULL, 72.52, 72.52, '2026-03-09 08:09:29', '2026-03-09 19:24:50', NULL, NULL, NULL, NULL, 'thrissur', '2026-03-10 00:53:35', 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL),
(19, 1, 2, 'daily', 'completed', '2026-03-10 01:00:00', '2026-03-19 01:00:00', '2026-03-10 01:05:00', 1000.00, 150.00, 0.00, NULL, 0.00, 'cash', 1150.00, 0.00, 172.50, 172.50, 0.00, 0.00, 0.00, NULL, NULL, 0.00, 0.00, 12, 12.00, 1, 0.00, 100.00, 200.00, NULL, 0.00, 0.00, 0.00, NULL, 300.00, NULL, NULL, 999.61, 999.61, '2026-03-09 19:35:35', '2026-03-09 19:38:55', NULL, NULL, NULL, NULL, 'dfdf', '2026-03-10 01:07:12', 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL),
(20, 1, 4, 'daily', 'confirmed', '2026-03-10 20:25:00', '2026-03-20 01:00:00', NULL, 1100.00, 0.00, 0.00, NULL, 0.00, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, NULL, 0.00, 0.00, NULL, NULL, NULL, 0.00, 0.00, 0.00, NULL, 0.00, 0.00, 0.00, NULL, 0.00, NULL, NULL, 0.00, 0.00, '2026-03-10 15:03:45', '2026-03-10 15:03:45', NULL, NULL, NULL, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL),
(21, 1, 2, 'daily', 'cancelled', '2026-03-11 10:45:00', '2026-03-16 01:00:00', NULL, 600.00, 0.00, 0.00, NULL, 0.00, 'cash', 500.00, 0.00, 97.50, 0.00, 0.00, 0.00, 0.00, NULL, NULL, 0.00, 0.00, 1, 1.00, NULL, 0.00, 0.00, 0.00, NULL, 0.00, 0.00, 0.00, NULL, 0.00, NULL, NULL, 0.00, 0.00, '2026-03-11 05:19:10', '2026-03-11 05:31:55', 'sdsd', '2026-03-11 11:01:55', 1, 500.00, 'rt', '2026-03-11 11:01:01', 100.00, 'account', 2, NULL, 0.00, NULL, NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL),
(22, 5, 1, 'daily', 'completed', '2026-03-11 12:45:00', '2026-03-28 01:00:00', '2026-03-11 12:50:00', 18.00, 150.00, 0.00, NULL, 0.00, 'cash', 168.00, 0.00, 25.20, 25.20, 0.00, 0.00, 0.00, NULL, NULL, 0.00, 0.00, 1, 12.00, 1, 0.00, 0.00, 200.00, NULL, 0.00, 0.00, 0.00, 'cash', 200.00, NULL, NULL, 18.00, 18.00, '2026-03-11 07:19:01', '2026-03-11 07:25:35', NULL, NULL, NULL, NULL, 'sd', '2026-03-11 12:49:52', 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL),
(23, 5, 6, 'daily', 'confirmed', '2026-03-11 13:40:00', '2026-05-12 01:00:00', NULL, 6993.00, 0.00, 0.00, NULL, 0.00, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, NULL, 0.00, 0.00, NULL, NULL, NULL, 0.00, 0.00, 0.00, NULL, 0.00, 0.00, 0.00, NULL, 0.00, NULL, NULL, 0.00, 0.00, '2026-03-11 08:14:31', '2026-03-11 08:14:31', NULL, NULL, NULL, NULL, NULL, NULL, 0.00, NULL, NULL, 'testing note', 0.00, NULL, NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL),
(24, 1, 2, 'daily', 'completed', '2026-03-11 13:45:00', '2026-06-15 01:00:00', '2026-03-12 20:35:00', 9700.00, 150.00, 1000.00, NULL, 0.00, 'cash', 10850.00, 0.00, 1477.50, 1477.50, 0.00, 0.00, 0.00, NULL, NULL, 0.00, 0.00, 1, 1.00, 1, 0.00, 0.00, 200.00, NULL, 0.00, 0.00, 0.00, 'cash', 200.00, NULL, NULL, 9569.47, 9569.47, '2026-03-11 08:16:36', '2026-03-12 15:09:22', NULL, NULL, NULL, NULL, 'dfd', '2026-03-12 11:04:22', 0.00, NULL, NULL, 'sdsd', 0.00, NULL, NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL),
(25, 5, 3, '30day', 'completed', '2026-03-11 14:10:00', '2026-04-10 14:10:00', '2026-03-11 14:15:00', 6000.00, 150.00, 0.00, NULL, 0.00, 'cash', 4150.00, 0.00, 622.50, 622.50, 0.00, 0.00, 0.00, NULL, NULL, 0.00, 0.00, 12, 12.00, 1, 0.00, 0.00, 200.00, NULL, 0.00, 0.00, 0.00, 'cash', 200.00, NULL, NULL, 5999.31, 5999.31, '2026-03-11 08:46:37', '2026-03-11 08:48:53', NULL, NULL, NULL, NULL, 'df', '2026-03-11 14:18:06', 2000.00, 'account', 2, 'test advance poy', 0.00, NULL, NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL),
(26, 8, 3, '30day', 'completed', '2026-03-12 11:15:00', '2026-04-11 11:15:00', '2026-03-12 11:20:00', 6000.00, 1000.00, 0.00, 'amount', 500.00, 'cash', 4500.00, 0.00, 600.00, 600.00, 0.00, 0.00, 0.00, NULL, NULL, 0.00, 0.00, 1, 1.00, NULL, 0.00, 0.00, 200.00, NULL, 0.00, 0.00, 0.00, 'cash', 200.00, NULL, NULL, 5999.31, 5999.31, '2026-03-12 05:50:21', '2026-03-12 05:52:53', NULL, NULL, NULL, NULL, 'dfd', '2026-03-12 11:22:00', 2000.00, 'cash', NULL, NULL, 150.00, 'cash', NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL),
(27, 7, 2, 'daily', 'active', '2026-03-10 10:00:00', '2026-03-15 10:00:00', NULL, 500.00, 0.00, 100.00, NULL, 0.00, 'cash', 250.00, 0.00, 22.50, 0.00, 0.00, 0.00, 0.00, NULL, NULL, 0.00, 0.00, 1, 1.00, NULL, 0.00, 0.00, 0.00, NULL, 0.00, 0.00, 0.00, NULL, 0.00, NULL, NULL, 0.00, 0.00, '2026-03-13 08:49:23', '2026-03-13 09:24:19', NULL, NULL, NULL, NULL, 'sdf', '2026-03-13 14:20:28', 50.00, 'cash', NULL, NULL, 150.00, 'cash', NULL, 300.00, NULL, NULL, NULL, 0.00, NULL, NULL),
(28, 9, 3, 'daily', 'completed', '2026-03-10 10:00:00', '2026-03-20 15:04:51', '2026-03-13 16:15:00', 1800.00, 0.00, 0.00, NULL, 0.00, 'cash', 200.00, 0.00, 30.00, 30.00, 0.00, 0.00, 0.00, NULL, NULL, 0.00, 0.00, 1, 1.00, NULL, 0.00, 0.00, 200.00, NULL, 0.00, 0.00, 0.00, 'cash', 200.00, NULL, NULL, 1225.29, 1225.29, '2026-03-13 09:31:38', '2026-03-13 10:48:20', NULL, NULL, NULL, NULL, 'gfh', '2026-03-13 15:02:09', 200.00, 'account', 1, NULL, 150.00, 'account', 1, 1400.00, NULL, NULL, NULL, 0.00, NULL, NULL),
(29, 1, 2, 'daily', 'active', '2026-03-16 14:55:00', '2026-04-20 01:00:00', NULL, 3600.00, 0.00, 0.00, NULL, 0.00, 'cash', 3600.00, 0.00, 540.00, 0.00, 0.00, 0.00, 0.00, NULL, NULL, 0.00, 0.00, 1, 1.00, NULL, 0.00, 0.00, 0.00, NULL, 0.00, 0.00, 0.00, NULL, 0.00, NULL, NULL, 0.00, 0.00, '2026-03-16 09:29:48', '2026-03-16 09:30:28', NULL, NULL, NULL, NULL, 'dfdf', '2026-03-16 15:00:28', 0.00, NULL, NULL, NULL, 150.00, 'account', 1, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL),
(30, 1, 1, 'daily', 'active', '2026-03-16 15:00:00', '2026-04-20 01:00:00', NULL, 36.00, 0.00, 0.00, NULL, 0.00, 'cash', 36.00, 0.00, 5.40, 0.00, 0.00, 0.00, 0.00, NULL, NULL, 0.00, 0.00, 1, 1.00, NULL, 0.00, 0.00, 0.00, NULL, 0.00, 0.00, 0.00, NULL, 0.00, NULL, NULL, 0.00, 0.00, '2026-03-16 09:31:37', '2026-04-09 10:44:24', NULL, NULL, NULL, NULL, 'thrissur', '2026-03-16 15:02:14', 0.00, NULL, NULL, NULL, 150.00, 'cash', NULL, 0.00, NULL, NULL, NULL, 0.00, 'eranakulam', 'additional notes'),
(31, 10, 3, 'daily', 'completed', '2026-03-16 17:10:00', '2026-04-20 01:00:00', '2026-03-28 11:15:00', 36000.00, 0.00, 0.00, NULL, 0.00, 'cash', 36000.00, 0.00, 5400.00, 5400.00, 0.00, 0.00, 0.00, NULL, NULL, 0.00, 0.00, 1, 1.00, NULL, 0.00, 0.00, 200.00, NULL, 0.00, 0.00, 0.00, 'cash', 200.00, 'no', NULL, 23673.48, 23673.48, '2026-03-16 11:42:33', '2026-03-28 05:47:59', NULL, NULL, NULL, NULL, 'dfdf', '2026-03-16 17:13:05', 0.00, NULL, NULL, NULL, 150.00, 'cash', NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL),
(32, 13, 5, '1day', 'completed', '2026-03-16 17:25:00', '2026-03-17 17:25:00', '2026-03-16 23:05:00', 1.00, 0.00, 0.00, NULL, 0.00, 'cash', 1.00, 0.00, 0.15, 0.15, 0.00, 0.00, 0.00, NULL, NULL, 0.00, 0.00, 1, 1.00, NULL, 0.00, 0.00, 200.00, NULL, 0.00, 0.00, 0.00, 'cash', 200.00, NULL, NULL, 0.76, 0.76, '2026-03-16 12:03:35', '2026-03-16 17:41:25', NULL, NULL, NULL, NULL, 'eranakulam', '2026-03-16 17:34:11', 0.00, NULL, NULL, NULL, 150.00, 'cash', NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL),
(33, 2, 7, 'daily', 'completed', '2026-03-16 20:50:00', '2026-03-20 01:00:00', '2026-03-16 23:05:00', 5000.00, 0.00, 0.00, NULL, 0.00, 'cash', 5000.00, 0.00, 750.00, 750.00, 0.00, 0.00, 0.00, NULL, NULL, 0.00, 0.00, 1, 1.00, NULL, 0.00, 0.00, 200.00, NULL, 0.00, 0.00, 0.00, 'cash', 200.00, NULL, NULL, 4852.30, 4852.30, '2026-03-16 15:23:46', '2026-03-16 17:38:10', NULL, NULL, NULL, NULL, 'kochi', '2026-03-16 20:55:31', 0.00, NULL, NULL, NULL, 150.00, 'cash', NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL),
(34, 5, 7, 'daily', 'completed', '2026-03-16 23:10:00', '2026-05-19 01:00:00', '2026-03-21 20:10:00', 65000.00, 0.00, 0.00, NULL, 0.00, 'cash', 58000.00, 0.00, 8700.00, 8700.00, 0.00, 0.00, 0.00, NULL, NULL, 0.00, 0.00, 1, 1.00, NULL, 0.00, 0.00, 200.00, NULL, 0.00, 0.00, 0.00, 'credit', 200.00, NULL, NULL, 59976.33, 59976.33, '2026-03-16 17:42:47', '2026-03-21 14:44:27', NULL, NULL, NULL, NULL, 'palakkad', '2026-03-16 23:14:20', 0.00, NULL, NULL, NULL, 150.00, 'cash', NULL, 7000.00, NULL, NULL, NULL, 0.00, NULL, NULL),
(35, 7, 5, 'daily', 'completed', '2026-03-17 10:05:00', '2028-04-16 10:05:00', '2026-03-17 10:05:00', 762.00, 0.00, 0.00, NULL, 0.00, 'cash', 762.00, 0.00, 114.30, 114.30, 0.00, 0.00, 0.00, NULL, NULL, 0.00, 0.00, 1, 1.00, NULL, 0.00, 0.00, 200.00, NULL, 0.00, 0.00, 0.00, 'cash', 200.00, NULL, NULL, 762.00, 762.00, '2026-03-17 04:38:13', '2026-03-17 04:39:46', NULL, NULL, NULL, NULL, 'sdsd', '2026-03-17 10:08:58', 0.00, NULL, NULL, NULL, 150.00, 'cash', NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL),
(36, 1, 4, 'daily', 'confirmed', '2026-03-20 21:50:00', '2026-03-22 01:00:00', NULL, 200.00, 0.00, 0.00, NULL, 0.00, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, NULL, 0.00, 0.00, NULL, NULL, NULL, 0.00, 0.00, 0.00, NULL, 0.00, 0.00, 0.00, NULL, 0.00, NULL, NULL, 0.00, 0.00, '2026-03-17 16:23:30', '2026-03-17 16:23:30', NULL, NULL, NULL, NULL, NULL, NULL, 100.00, 'cash', NULL, NULL, 150.00, 'cash', NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL),
(37, 14, 5, 'daily', 'confirmed', '2026-03-20 10:00:00', '2026-03-22 10:00:00', NULL, 200.00, 0.00, 0.00, NULL, 0.00, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, NULL, 0.00, 0.00, NULL, NULL, NULL, 0.00, 0.00, 0.00, NULL, 0.00, 0.00, 0.00, NULL, 0.00, NULL, NULL, 0.00, 0.00, '2026-03-17 19:12:29', '2026-03-17 19:12:29', NULL, NULL, NULL, NULL, NULL, NULL, 50.00, 'cash', NULL, NULL, 150.00, 'cash', NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL),
(38, 14, 8, 'daily', 'completed', '2026-03-19 00:00:00', '2026-03-21 00:00:00', '2026-03-21 00:00:00', 200.00, 20.00, 10.00, 'amount', 5.00, 'cash', 185.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, NULL, 0.00, 0.00, 1, 1.00, 11, 10.00, 20.00, 30.00, 'amount', 5.00, 0.00, 0.00, 'cash', 55.00, NULL, NULL, 0.00, 0.00, '2026-03-17 19:33:59', '2026-03-17 19:45:23', NULL, NULL, NULL, NULL, 'thrissur', '2026-03-18 01:08:54', 40.00, 'cash', NULL, NULL, 60.00, 'cash', NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL),
(39, 11, 9, 'daily', 'completed', '2026-04-01 07:00:00', '2026-05-01 07:00:00', '2026-03-21 20:10:00', 30.00, 0.00, 0.00, NULL, 0.00, 'cash', 30.00, 0.00, 4.50, 4.50, 0.00, 0.00, 0.00, NULL, NULL, 0.00, 0.00, 1, 1.00, NULL, 0.00, 0.00, 200.00, NULL, 0.00, 0.00, 0.00, 'cash', 200.00, NULL, NULL, 30.00, 30.00, '2026-03-19 07:58:25', '2026-03-21 14:43:43', NULL, NULL, NULL, NULL, 'dfd', '2026-03-19 13:29:20', 0.00, NULL, NULL, NULL, 150.00, 'cash', NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL),
(41, 16, 10, 'daily', 'completed', '2026-03-19 14:20:00', '2026-03-25 01:00:00', '2026-03-21 20:10:00', 6.00, 0.00, 0.00, NULL, 0.00, 'cash', 6.00, 0.00, 0.90, 0.90, 0.00, 0.00, 0.00, NULL, NULL, 0.00, 0.00, 11, 11.00, NULL, 0.00, 0.00, 200.00, NULL, 0.00, 0.00, 0.00, 'credit', 200.00, NULL, NULL, 3.53, 3.53, '2026-03-19 08:52:18', '2026-03-21 14:42:11', NULL, NULL, NULL, NULL, 'dfdf', '2026-03-19 14:22:44', 0.00, NULL, NULL, NULL, 150.00, 'account', 1, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL),
(43, 5, 10, 'daily', 'confirmed', '2026-03-21 22:05:00', '2026-06-06 01:00:00', NULL, 77.00, 0.00, 0.00, NULL, 0.00, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, NULL, 0.00, 0.00, NULL, NULL, NULL, 0.00, 0.00, 0.00, NULL, 0.00, 0.00, 0.00, NULL, 0.00, NULL, NULL, 0.00, 0.00, '2026-03-21 16:37:43', '2026-03-27 17:04:04', NULL, NULL, NULL, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, 150.00, 'cash', NULL, 0.00, NULL, NULL, 'amount', 50.00, NULL, NULL),
(44, 15, 8, 'daily', 'completed', '2026-03-22 11:45:00', '2026-12-13 01:00:00', '2026-03-28 11:10:00', 26600.00, 500.00, 0.00, NULL, 0.00, 'credit', 27000.00, 0.00, 4050.00, 4050.00, 0.00, 0.00, 0.00, NULL, NULL, 0.00, 0.00, 1, 1.00, 12, 11.00, 0.00, 200.00, NULL, 0.00, 0.00, 0.00, 'cash', 211.00, 'yes', 'nice', 26001.42, 26001.42, '2026-03-22 06:18:52', '2026-03-28 05:44:48', NULL, NULL, NULL, NULL, 'fdf', '2026-03-22 11:51:23', 0.00, NULL, NULL, NULL, 0.00, 'credit', NULL, 100.00, NULL, NULL, NULL, 0.00, NULL, NULL),
(45, 1, 7, 'daily', 'completed', '2026-03-22 11:55:00', '2026-12-13 01:00:00', '2026-03-24 19:40:00', 267000.00, 1000.00, 0.00, NULL, 0.00, 'cash', 266000.00, 0.00, 39900.00, 39900.00, 0.00, 0.00, 0.00, NULL, NULL, 0.00, 0.00, 1, 1.00, NULL, 0.00, 0.00, 200.00, NULL, 0.00, 0.00, 0.00, 'cash', 200.00, NULL, NULL, 264664.36, 264664.36, '2026-03-22 06:29:06', '2026-03-24 14:13:46', NULL, NULL, NULL, NULL, 'dfdf', '2026-03-22 11:59:58', 0.00, NULL, NULL, NULL, 0.00, 'credit', NULL, 2000.00, NULL, NULL, NULL, 0.00, NULL, NULL),
(46, 5, 5, 'daily', 'completed', '2026-03-22 12:10:00', '2026-12-12 01:00:00', '2026-03-24 00:45:00', 26500.00, 1000.00, 0.00, NULL, 0.00, 'cash', 26500.00, 0.00, 4125.00, 3125.00, 1000.00, 0.00, 0.00, NULL, NULL, 0.00, 0.00, 1, 1.00, NULL, 0.00, 1000.00, 200.00, NULL, 0.00, 0.00, 0.00, 'cash', 1200.00, NULL, NULL, 26347.30, 26347.30, '2026-03-22 06:40:50', '2026-03-23 19:18:26', NULL, NULL, NULL, NULL, 'sdsdf', '2026-03-22 12:11:39', 0.00, NULL, NULL, NULL, 0.00, 'credit', NULL, 0.00, '2026-03-24 00:48:26', 'converted', NULL, 0.00, NULL, NULL),
(47, 5, 8, 'daily', 'completed', '2027-03-22 12:15:00', '2028-12-12 01:00:00', '2026-03-23 17:45:00', 63100.00, 1000.00, 0.00, NULL, 0.00, 'cash', 63100.00, 0.00, 9615.00, 8415.00, 1200.00, 0.00, 0.00, NULL, NULL, 0.00, 0.00, 1, 1.00, NULL, 0.00, 1000.00, 200.00, NULL, 0.00, 0.00, 0.00, 'cash', 1200.00, NULL, NULL, 63100.00, 63100.00, '2026-03-22 06:46:28', '2026-03-23 12:49:46', NULL, NULL, NULL, NULL, 'df', '2026-03-22 12:17:23', 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL),
(48, 5, 9, 'daily', 'completed', '2026-03-23 22:20:00', '2026-12-12 01:00:00', '2026-03-23 23:35:00', 264.00, 150.00, 0.00, NULL, 0.00, 'cash', 264.00, 0.00, 62.10, 62.10, 0.00, 0.00, 0.00, NULL, NULL, 0.00, 0.00, 1, 1.00, NULL, 0.00, 0.00, 200.00, NULL, 0.00, 0.00, 0.00, 'cash', 200.00, NULL, NULL, 263.95, 263.95, '2026-03-23 16:54:50', '2026-03-23 18:09:08', NULL, NULL, NULL, NULL, 'thrissur', '2026-03-23 23:34:45', 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL),
(49, 5, 9, 'daily', 'completed', '2026-03-23 23:40:00', '2026-05-12 01:00:00', '2026-03-23 23:45:00', 50.00, 150.00, 0.00, NULL, 0.00, 'cash', 50.00, 0.00, 30.00, 10.00, 20.00, 0.00, 0.00, NULL, NULL, 0.00, 0.00, 1, 1.00, NULL, 0.00, 20.00, 200.00, NULL, 0.00, 0.00, 0.00, 'cash', 200.00, NULL, NULL, 50.00, 50.00, '2026-03-23 18:12:32', '2026-03-23 18:17:59', NULL, NULL, NULL, NULL, 'thrissur', '2026-03-23 23:43:28', 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL),
(50, 9, 9, 'daily', 'completed', '2026-03-23 23:45:00', '2026-08-12 01:00:00', '2026-03-23 23:55:00', 142.00, 150.00, 0.00, NULL, 0.00, 'cash', 142.00, 0.00, 43.80, 43.80, 0.00, 0.00, 0.00, NULL, NULL, 0.00, 0.00, 1, 1.00, NULL, 0.00, 0.00, 200.00, NULL, 0.00, 0.00, 0.00, 'cash', 200.00, NULL, NULL, 141.99, 141.99, '2026-03-23 18:20:07', '2026-03-23 18:48:41', NULL, NULL, NULL, NULL, 'thrissur', '2026-03-23 23:51:00', 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, 0.00, '2026-03-24 00:18:41', 'released', NULL, 0.00, NULL, NULL),
(51, 16, 5, 'daily', 'completed', '2026-03-24 10:00:00', '2026-07-23 01:00:00', '2026-03-24 10:05:00', 12100.00, 150.00, 0.00, NULL, 0.00, 'cash', 12100.00, 0.00, 1837.50, 637.50, 1200.00, 0.00, 0.00, NULL, NULL, 0.00, 0.00, 1, 1.00, 1, 0.00, 1000.00, 200.00, NULL, 0.00, 0.00, 0.00, 'cash', 1000.00, NULL, NULL, 12099.65, 12099.65, '2026-03-24 04:35:03', '2026-03-24 05:42:22', NULL, NULL, NULL, NULL, 'thrissur', '2026-03-24 10:05:58', 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, 0.00, '2026-03-24 11:12:22', 'converted', NULL, 0.00, NULL, NULL),
(52, 15, 5, 'daily', 'completed', '2026-03-24 18:05:00', '2026-06-27 01:00:00', '2026-03-24 18:15:00', 9700.00, 150.00, 0.00, NULL, 0.00, 'cash', 9300.00, 0.00, 1417.50, 1017.50, 0.00, 0.00, 400.00, NULL, NULL, 0.00, 0.00, 1, 1.00, NULL, 0.00, 0.00, 200.00, NULL, 0.00, 0.00, 0.00, 'cash', 200.00, NULL, NULL, 9699.29, 9699.29, '2026-03-24 12:37:16', '2026-03-24 12:46:22', NULL, NULL, NULL, NULL, 'dfdf', '2026-03-24 18:08:16', 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, 400.00, NULL, NULL, NULL, 0.00, NULL, NULL),
(53, 8, 5, 'daily', 'completed', '2026-03-24 19:00:00', '2026-06-27 01:00:00', '2026-03-24 19:00:00', 9500.00, 150.00, 0.00, NULL, 0.00, 'cash', 9500.00, 0.00, 1447.50, 447.50, 0.00, 1000.00, 0.00, '2026-03-24 19:00:00', 'test', 0.00, 0.00, 1, 1.00, NULL, 0.00, 0.00, 200.00, NULL, 0.00, 0.00, 0.00, 'cash', 200.00, NULL, NULL, 9500.00, 9500.00, '2026-03-24 13:33:28', '2026-03-24 13:35:16', NULL, NULL, NULL, NULL, 'Thrissur', '2026-03-24 19:04:03', 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL),
(54, 15, 11, 'daily', 'completed', '2026-03-21 09:00:00', '2026-04-04 10:00:00', '2026-03-25 16:40:00', 750000.00, 150.00, 0.00, NULL, 0.00, 'cash', 745000.00, 0.00, 111772.50, 111772.50, 0.00, 0.00, 0.00, NULL, NULL, 0.00, 0.00, 1, 1.00, NULL, 0.00, 0.00, 200.00, NULL, 0.00, 0.00, 0.00, 'cash', 200.00, NULL, NULL, 519287.83, 519287.83, '2026-03-24 15:08:56', '2026-03-25 11:15:32', NULL, NULL, NULL, NULL, 'tth', '2026-03-25 16:37:47', 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, 0.00, NULL, NULL, 'amount', 5000.00, NULL, NULL),
(55, 14, 8, 'daily', 'completed', '2026-04-03 12:50:00', '2026-04-20 01:00:00', '2026-04-03 12:50:00', 1700.00, 150.00, 0.00, NULL, 0.00, 'cash', 1700.00, 0.00, 277.50, 277.50, 0.00, 0.00, 0.00, NULL, NULL, 0.00, 0.00, 100, 50.00, 30, 0.00, 0.00, 200.00, NULL, 0.00, 0.00, 0.00, 'cash', 200.00, 'yes', 'nice', 1700.00, 1700.00, '2026-04-03 07:20:57', '2026-04-03 07:23:06', NULL, NULL, NULL, NULL, 'thrissur', '2026-04-03 12:52:02', 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL),
(56, 15, 8, 'daily', 'completed', '2026-04-06 11:05:00', '2026-04-10 01:00:00', '2026-04-09 13:05:00', 400.00, 150.00, 0.00, NULL, 0.00, 'cash', 400.00, 0.00, 82.50, 82.50, 0.00, 0.00, 0.00, NULL, NULL, 0.00, 0.00, 12, 12.00, 5, 0.00, 0.00, 200.00, 'amount', 50.00, 0.00, 0.00, 'cash', 150.00, 'yes', NULL, 55.48, 55.48, '2026-04-04 05:39:45', '2026-04-09 07:38:55', NULL, NULL, NULL, NULL, 'palakkad', '2026-04-07 15:14:43', 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL),
(57, 490, 16, 'daily', 'completed', '2026-03-18 09:00:00', '2026-03-23 09:00:00', NULL, 500.00, 0.00, 0.00, NULL, 0.00, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, NULL, 0.00, 0.00, NULL, NULL, NULL, 0.00, 0.00, 0.00, NULL, 0.00, 0.00, 0.00, NULL, 0.00, NULL, NULL, 0.00, 0.00, '2026-04-07 07:43:32', '2026-04-07 07:43:32', NULL, NULL, NULL, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL),
(58, 491, 19, 'daily', 'completed', '2026-03-18 09:00:00', '2026-03-23 09:00:00', NULL, 500.00, 0.00, 0.00, NULL, 0.00, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, NULL, 0.00, 0.00, NULL, NULL, NULL, 0.00, 0.00, 0.00, NULL, 0.00, 0.00, 0.00, NULL, 0.00, NULL, NULL, 0.00, 0.00, '2026-04-07 07:44:02', '2026-04-07 07:44:02', NULL, NULL, NULL, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL),
(59, 492, 22, 'daily', 'completed', '2026-03-20 10:00:00', '2026-03-23 10:00:00', NULL, 1000.00, 0.00, 0.00, NULL, 0.00, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, NULL, 0.00, 0.00, NULL, NULL, NULL, 0.00, 0.00, 0.00, NULL, 0.00, 0.00, 0.00, NULL, 0.00, NULL, NULL, 0.00, 0.00, '2026-04-07 07:47:05', '2026-04-07 07:47:05', NULL, NULL, NULL, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL),
(60, 492, 22, 'daily', 'completed', '2026-03-25 14:00:00', '2026-03-28 14:00:00', NULL, 1000.00, 0.00, 0.00, NULL, 0.00, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, NULL, 0.00, 0.00, NULL, NULL, NULL, 0.00, 0.00, 0.00, NULL, 0.00, 0.00, 0.00, NULL, 0.00, NULL, NULL, 0.00, 0.00, '2026-04-07 07:47:05', '2026-04-07 07:47:05', NULL, NULL, NULL, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL),
(61, 492, 24, 'daily', 'completed', '2026-03-20 10:00:00', '2026-03-23 10:00:00', NULL, 1000.00, 0.00, 0.00, NULL, 0.00, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, NULL, 0.00, 0.00, NULL, NULL, NULL, 0.00, 0.00, 0.00, NULL, 0.00, 0.00, 0.00, NULL, 0.00, NULL, NULL, 0.00, 0.00, '2026-04-07 07:47:05', '2026-04-07 07:47:05', NULL, NULL, NULL, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL),
(62, 492, 24, 'daily', 'completed', '2026-03-22 11:00:00', '2026-03-25 11:00:00', NULL, 1000.00, 0.00, 0.00, NULL, 0.00, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, NULL, 0.00, 0.00, NULL, NULL, NULL, 0.00, 0.00, 0.00, NULL, 0.00, 0.00, 0.00, NULL, 0.00, NULL, NULL, 0.00, 0.00, '2026-04-07 07:47:05', '2026-04-07 07:47:05', NULL, NULL, NULL, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL),
(63, 492, 25, 'daily', 'completed', '2026-03-20 10:00:00', '2026-03-23 10:00:00', NULL, 1000.00, 0.00, 0.00, NULL, 0.00, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, NULL, 0.00, 0.00, NULL, NULL, NULL, 0.00, 0.00, 0.00, NULL, 0.00, 0.00, 0.00, NULL, 0.00, NULL, NULL, 0.00, 0.00, '2026-04-07 07:47:05', '2026-04-07 07:47:05', NULL, NULL, NULL, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL),
(64, 492, 25, 'daily', 'completed', '2026-02-10 10:00:00', '2026-02-13 10:00:00', NULL, 1000.00, 0.00, 0.00, NULL, 0.00, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, NULL, 0.00, 0.00, NULL, NULL, NULL, 0.00, 0.00, 0.00, NULL, 0.00, 0.00, 0.00, NULL, 0.00, NULL, NULL, 0.00, 0.00, '2026-04-07 07:47:05', '2026-04-07 07:47:05', NULL, NULL, NULL, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL),
(65, 492, 26, 'daily', 'completed', '2026-03-20 10:00:00', '2026-03-23 10:00:00', NULL, 1000.00, 0.00, 0.00, NULL, 0.00, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, NULL, 0.00, 0.00, NULL, NULL, NULL, 0.00, 0.00, 0.00, NULL, 0.00, 0.00, 0.00, NULL, 0.00, NULL, NULL, 0.00, 0.00, '2026-04-07 07:47:05', '2026-04-07 07:47:05', NULL, NULL, NULL, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL),
(66, 492, 26, 'daily', 'completed', '2026-03-25 14:00:00', '2026-03-28 14:00:00', NULL, 1000.00, 0.00, 0.00, NULL, 0.00, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, NULL, 0.00, 0.00, NULL, NULL, NULL, 0.00, 0.00, 0.00, NULL, 0.00, 0.00, 0.00, NULL, 0.00, NULL, NULL, 0.00, 0.00, '2026-04-07 07:47:05', '2026-04-07 07:47:05', NULL, NULL, NULL, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL),
(67, 493, 28, 'daily', 'completed', '2026-03-18 09:00:00', '2026-03-23 09:00:00', NULL, 500.00, 0.00, 0.00, NULL, 0.00, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, NULL, 0.00, 0.00, NULL, NULL, NULL, 0.00, 0.00, 0.00, NULL, 0.00, 0.00, 0.00, NULL, 0.00, NULL, NULL, 0.00, 0.00, '2026-04-07 07:48:25', '2026-04-07 07:48:25', NULL, NULL, NULL, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL),
(68, 15, 31, 'daily', 'completed', '2026-04-08 11:10:00', '2026-04-20 01:00:00', '2026-04-09 10:30:00', 650.00, 150.00, 0.00, NULL, 0.00, NULL, 0.00, 0.00, 22.50, 22.50, 0.00, 0.00, 0.00, NULL, NULL, 0.00, 150.00, 12, 12.00, 14988, 179712.00, 0.00, 200.00, NULL, 0.00, 0.00, 0.00, 'cash', 180062.00, 'yes', NULL, 595.41, 595.41, '2026-04-08 05:43:33', '2026-04-09 05:08:07', NULL, NULL, NULL, NULL, 'thrissur', '2026-04-08 13:08:06', 150.00, 'cash', NULL, NULL, 0.00, NULL, NULL, 500.00, NULL, NULL, NULL, 0.00, NULL, NULL),
(69, 15, 4, 'daily', 'confirmed', '2026-04-08 15:55:00', '2026-04-10 01:00:00', NULL, 200.00, 0.00, 0.00, NULL, 0.00, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, NULL, 0.00, 0.00, NULL, NULL, NULL, 0.00, 0.00, 0.00, NULL, 0.00, 0.00, 0.00, NULL, 0.00, NULL, NULL, 0.00, 0.00, '2026-04-08 10:27:13', '2026-04-08 10:27:13', NULL, NULL, NULL, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL),
(70, 15, 2, 'daily', 'confirmed', '2026-07-08 17:05:00', '2026-08-20 01:00:00', NULL, 4300.00, 0.00, 0.00, NULL, 0.00, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, NULL, 0.00, 0.00, NULL, NULL, NULL, 0.00, 0.00, 0.00, NULL, 0.00, 0.00, 0.00, NULL, 0.00, NULL, NULL, 0.00, 0.00, '2026-04-08 11:39:17', '2026-04-08 11:39:17', NULL, NULL, NULL, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL),
(71, 488, 11, 'daily', 'cancelled', '2026-04-10 11:40:00', '2026-04-19 01:00:00', NULL, 450000.00, 0.00, 0.00, NULL, 0.00, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, NULL, 0.00, 0.00, NULL, NULL, NULL, 0.00, 0.00, 0.00, NULL, 0.00, 0.00, 0.00, NULL, 0.00, NULL, NULL, 0.00, 0.00, '2026-04-09 06:13:15', '2026-04-09 06:16:25', 'testing', '2026-04-09 11:46:25', 1, 2000.00, NULL, NULL, 2000.00, 'cash', NULL, NULL, 0.00, NULL, NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL),
(72, 12, 11, 'daily', 'confirmed', '2026-04-09 13:45:00', '2026-04-20 01:00:00', NULL, 550000.00, 0.00, 0.00, NULL, 0.00, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, NULL, 0.00, 0.00, NULL, NULL, NULL, 0.00, 0.00, 0.00, NULL, 0.00, 0.00, 0.00, NULL, 0.00, NULL, NULL, 0.00, 0.00, '2026-04-09 08:16:10', '2026-04-09 08:16:10', NULL, NULL, NULL, NULL, NULL, NULL, 0.00, NULL, NULL, 'testing reservation note', 0.00, NULL, NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL),
(73, 1, 2, 'daily', 'completed', '2026-04-10 14:00:00', '2026-04-15 14:00:00', '2026-04-12 12:50:00', 5000.00, 150.00, 0.00, NULL, 0.00, 'cash', 5000.00, 0.00, 772.50, 772.50, 0.00, 0.00, 0.00, NULL, NULL, 0.00, 0.00, 12, 12.00, 12, 0.00, 0.00, 200.00, NULL, 0.00, 0.00, 0.00, 'cash', 200.00, 'yes', NULL, 3048.61, 3048.61, '2026-04-10 09:09:10', '2026-04-12 07:22:25', NULL, NULL, NULL, NULL, 'thrissur', '2026-04-12 12:50:05', 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL),
(74, 2, 4, 'daily', 'confirmed', '2026-04-11 10:00:00', '2026-04-18 10:00:00', NULL, 4500.00, 0.00, 0.00, NULL, 0.00, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, NULL, 0.00, 0.00, NULL, NULL, NULL, 0.00, 0.00, 0.00, NULL, 0.00, 0.00, 0.00, NULL, 0.00, NULL, NULL, 0.00, 0.00, '2026-04-10 09:09:10', '2026-04-10 09:09:10', NULL, NULL, NULL, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL),
(75, 3, 5, 'daily', 'confirmed', '2026-04-12 15:00:00', '2026-04-19 15:00:00', NULL, 3800.00, 0.00, 0.00, NULL, 0.00, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, NULL, 0.00, 0.00, NULL, NULL, NULL, 0.00, 0.00, 0.00, NULL, 0.00, 0.00, 0.00, NULL, 0.00, NULL, NULL, 0.00, 0.00, '2026-04-10 09:09:10', '2026-04-10 09:09:10', NULL, NULL, NULL, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL),
(76, 5, 7, 'daily', 'confirmed', '2026-04-13 09:00:00', '2026-04-20 09:00:00', NULL, 6500.00, 0.00, 0.00, NULL, 0.00, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, NULL, 0.00, 0.00, NULL, NULL, NULL, 0.00, 0.00, 0.00, NULL, 0.00, 0.00, 0.00, NULL, 0.00, NULL, NULL, 0.00, 0.00, '2026-04-10 09:09:10', '2026-04-10 09:09:10', NULL, NULL, NULL, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL),
(77, 6, 5, 'daily', 'confirmed', '2026-04-14 11:00:00', '2026-04-21 11:00:00', NULL, 4200.00, 0.00, 0.00, NULL, 0.00, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, NULL, 0.00, 0.00, NULL, NULL, NULL, 0.00, 0.00, 0.00, NULL, 0.00, 0.00, 0.00, NULL, 0.00, NULL, NULL, 0.00, 0.00, '2026-04-10 09:09:10', '2026-04-10 09:09:10', NULL, NULL, NULL, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL),
(78, 7, 2, 'daily', 'confirmed', '2026-04-17 13:00:00', '2026-04-24 13:00:00', NULL, 5500.00, 0.00, 0.00, NULL, 0.00, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, NULL, 0.00, 0.00, NULL, NULL, NULL, 0.00, 0.00, 0.00, NULL, 0.00, 0.00, 0.00, NULL, 0.00, NULL, NULL, 0.00, 0.00, '2026-04-10 09:09:10', '2026-04-10 09:09:10', NULL, NULL, NULL, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL),
(79, 8, 8, 'daily', 'confirmed', '2026-04-20 16:00:00', '2026-04-27 16:00:00', NULL, 3900.00, 0.00, 0.00, NULL, 0.00, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, NULL, 0.00, 0.00, NULL, NULL, NULL, 0.00, 0.00, 0.00, NULL, 0.00, 0.00, 0.00, NULL, 0.00, NULL, NULL, 0.00, 0.00, '2026-04-10 09:09:10', '2026-04-10 09:09:10', NULL, NULL, NULL, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, 0.00, NULL, NULL, NULL, 0.00, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `reservation_extensions`
--

CREATE TABLE `reservation_extensions` (
  `id` int(11) NOT NULL,
  `reservation_id` int(11) NOT NULL,
  `old_end_date` datetime NOT NULL,
  `base_start_date` datetime NOT NULL,
  `new_end_date` datetime NOT NULL,
  `rental_type` enum('daily','1day','7day','15day','30day','monthly') NOT NULL DEFAULT 'daily',
  `days` int(11) NOT NULL,
  `rate_per_day` decimal(10,2) NOT NULL DEFAULT 0.00,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `paid_from_deposit` decimal(10,2) NOT NULL DEFAULT 0.00,
  `paid_cash` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_method` enum('cash','account','credit') DEFAULT NULL,
  `bank_account_id` int(11) DEFAULT NULL,
  `payment_source_type` enum('cash','credit','account','deposit','split') DEFAULT NULL,
  `ledger_entry_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reservation_extensions`
--

INSERT INTO `reservation_extensions` (`id`, `reservation_id`, `old_end_date`, `base_start_date`, `new_end_date`, `rental_type`, `days`, `rate_per_day`, `amount`, `paid_from_deposit`, `paid_cash`, `payment_method`, `bank_account_id`, `payment_source_type`, `ledger_entry_id`, `created_by`, `created_at`) VALUES
(1, 27, '2026-03-11 10:00:00', '2026-03-13 14:54:19', '2026-03-15 10:00:00', 'daily', 3, 100.00, 300.00, 0.00, 0.00, 'cash', NULL, NULL, 122, 1, '2026-03-13 09:24:19'),
(2, 28, '2026-03-11 10:00:00', '2026-03-13 15:04:51', '2026-03-20 15:04:51', '7day', 7, 200.00, 1400.00, 0.00, 0.00, 'account', 1, NULL, 127, 1, '2026-03-13 09:34:51'),
(3, 34, '2026-05-12 01:00:00', '2026-05-12 01:00:00', '2026-05-19 01:00:00', '7day', 7, 1000.00, 7000.00, 0.00, 0.00, 'cash', NULL, NULL, 171, 1, '2026-03-17 04:29:28'),
(4, 45, '2026-12-12 01:00:00', '2026-12-12 01:00:00', '2026-12-13 01:00:00', 'daily', 2, 1000.00, 2000.00, 0.00, 0.00, 'cash', NULL, NULL, 255, 1, '2026-03-24 05:02:45'),
(5, 52, '2026-06-25 01:00:00', '2026-06-25 01:00:00', '2026-06-26 01:00:00', 'daily', 2, 100.00, 200.00, 200.00, 0.00, 'account', NULL, 'deposit', NULL, 1, '2026-03-24 12:40:02'),
(6, 52, '2026-06-26 01:00:00', '2026-06-26 01:00:00', '2026-06-27 01:00:00', 'daily', 2, 100.00, 200.00, 200.00, 0.00, 'cash', NULL, 'split', NULL, 1, '2026-03-24 12:43:36'),
(7, 44, '2026-12-12 01:00:00', '2026-12-12 01:00:00', '2026-12-13 01:00:00', '1day', 1, 100.00, 100.00, 0.00, 100.00, 'account', 1, 'account', 277, 1, '2026-03-25 09:59:01'),
(8, 68, '2026-04-11 01:00:00', '2026-04-11 01:00:00', '2026-04-15 01:00:00', 'daily', 5, 50.00, 250.00, 0.00, 250.00, 'cash', NULL, 'cash', 354, 1, '2026-04-08 09:52:45'),
(9, 68, '2026-04-15 01:00:00', '2026-04-15 01:00:00', '2026-04-20 01:00:00', 'daily', 5, 50.00, 250.00, 0.00, 250.00, 'cash', NULL, 'cash', 355, 1, '2026-04-08 09:59:26');

-- --------------------------------------------------------

--
-- Table structure for table `reservation_scratch_photos`
--

CREATE TABLE `reservation_scratch_photos` (
  `id` int(11) NOT NULL,
  `reservation_id` int(11) NOT NULL,
  `event_type` enum('delivery','return') NOT NULL,
  `slot_index` tinyint(4) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `reservation_scratch_photos`
--

INSERT INTO `reservation_scratch_photos` (`id`, `reservation_id`, `event_type`, `slot_index`, `file_path`, `created_at`) VALUES
(1, 54, 'delivery', 1, 'uploads/scratch_photos/scratch_54_delivery_1_1774436867.png', '2026-03-25 16:37:47'),
(2, 54, 'delivery', 2, 'uploads/scratch_photos/scratch_54_delivery_2_1774436867.png', '2026-03-25 16:37:47'),
(3, 54, 'delivery', 3, 'uploads/scratch_photos/scratch_54_delivery_3_1774436867.png', '2026-03-25 16:37:47'),
(4, 54, 'delivery', 4, 'uploads/scratch_photos/scratch_54_delivery_4_1774436867.png', '2026-03-25 16:37:47'),
(5, 54, 'return', 1, 'uploads/scratch_photos/scratch_54_return_1_1774437332.png', '2026-03-25 16:45:32'),
(6, 54, 'return', 2, 'uploads/scratch_photos/scratch_54_return_2_1774437332.png', '2026-03-25 16:45:32'),
(7, 55, 'delivery', 1, 'uploads/scratch_photos/scratch_55_delivery_1_1775200922.webp', '2026-04-03 12:52:02'),
(8, 55, 'return', 1, 'uploads/scratch_photos/scratch_55_return_1_1775200986.webp', '2026-04-03 12:53:06'),
(9, 56, 'delivery', 1, 'uploads/scratch_photos/scratch_56_delivery_1_1775555083.png', '2026-04-07 15:14:43'),
(10, 56, 'delivery', 2, 'uploads/scratch_photos/scratch_56_delivery_2_1775555083.webp', '2026-04-07 15:14:43'),
(11, 68, 'delivery', 1, 'uploads/scratch_photos/scratch_68_delivery_1_1775633886.webp', '2026-04-08 13:08:06'),
(12, 68, 'return', 1, 'uploads/scratch_photos/scratch_68_return_1_1775711287.webp', '2026-04-09 10:38:07'),
(13, 56, 'return', 1, 'uploads/scratch_photos/scratch_56_return_1_1775720335.webp', '2026-04-09 13:08:55');

-- --------------------------------------------------------

--
-- Table structure for table `staff`
--

CREATE TABLE `staff` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `role` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `salary` decimal(10,2) DEFAULT NULL,
  `salary_type` enum('fixed','hourly') NOT NULL DEFAULT 'fixed',
  `hourly_rate` decimal(10,2) DEFAULT NULL,
  `joined_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `id_proof_path` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `enable_admin_dashboard` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff`
--

INSERT INTO `staff` (`id`, `name`, `role`, `phone`, `email`, `salary`, `salary_type`, `hourly_rate`, `joined_date`, `notes`, `id_proof_path`, `created_at`, `updated_at`, `enable_admin_dashboard`) VALUES
(1, 'staff1', 'staff', '6235646799', 'staff1@gmail.com', NULL, 'hourly', 500.00, NULL, 'ass', 'uploads/staff_docs/proof_1772810726_d1a14187.jpg', '2026-03-06 15:25:26', '2026-04-15 16:02:22', 0),
(2, 'staff2', 'staff', '2323232323', 'staff2@gmail.com', 10000.00, 'fixed', NULL, '2025-12-10', 'sd', 'uploads/staff_docs/proof_1772810773_9cd9714d.jpg', '2026-03-06 15:26:13', '2026-04-09 09:53:12', 1),
(3, 'admin2', 'Admin', '+971501111001', 'admin2@gmail.com', 30000.00, 'fixed', NULL, '2026-01-01', NULL, NULL, '2026-03-09 07:30:09', '2026-03-09 07:30:09', 0),
(4, 'staff3', 'staff', '6435627845', 'staff3@gmail.com', 10000.00, 'fixed', NULL, '2025-02-10', 'sd', NULL, '2026-03-09 09:28:12', '2026-03-09 09:28:12', 0),
(6, 'Test Staff 69c4ecf97a21a', 'Test Role', '1234567890', NULL, 5000.00, 'fixed', NULL, '2026-03-26', NULL, NULL, '2026-03-26 08:23:21', '2026-03-26 08:23:21', 0),
(7, 'Test Staff 69c4edc31646b', 'Test Role', '1234567890', NULL, 5000.00, 'fixed', NULL, '2026-03-26', NULL, NULL, '2026-03-26 08:26:43', '2026-03-26 08:26:43', 0),
(8, 'Test Staff 69c4f30047695', 'Test Role', '1234567890', NULL, 5000.00, 'fixed', NULL, '2026-03-26', NULL, NULL, '2026-03-26 08:49:04', '2026-03-26 08:49:04', 0),
(9, 'Test Staff 69c4f444e4f7f', 'Test Role', '1234567890', NULL, 5000.00, 'fixed', NULL, '2026-03-26', NULL, NULL, '2026-03-26 08:54:28', '2026-03-26 08:54:28', 0),
(10, 'Test Staff 69c4f4efd9588', 'Test Role', '1234567890', NULL, 5000.00, 'fixed', NULL, '2026-03-26', NULL, NULL, '2026-03-26 08:57:19', '2026-03-26 08:57:19', 0),
(11, 'Test Staff 69c4f66787d48', 'Test Role', '1234567890', NULL, 5000.00, 'fixed', NULL, '2026-03-26', NULL, NULL, '2026-03-26 09:03:35', '2026-03-26 09:03:35', 0),
(12, 'Test Staff 69c4f92b5dd8b', 'Test Role', '1234567890', NULL, 5000.00, 'fixed', NULL, '2026-03-26', NULL, NULL, '2026-03-26 09:15:23', '2026-03-26 09:15:23', 0);

-- --------------------------------------------------------

--
-- Table structure for table `staff_activity_log`
--

CREATE TABLE `staff_activity_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff_activity_log`
--

INSERT INTO `staff_activity_log` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `created_at`) VALUES
(1, 1, 'delivery', 'reservation', 2, 'Delivered reservation #2 — zaman → bmw m4 (kl05tm). Collected: $1,000.00.', '2026-03-05 05:08:47'),
(2, 1, 'delivery', 'reservation', 1, 'Delivered reservation #1 — hamad → Mercedes-Benz S-Class S 350d (kl05tR). Collected: $6.00.', '2026-03-05 05:09:10'),
(3, 1, 'created_lead', 'lead', 1, 'Created lead \"test\" (6435627845) with status: new.', '2026-03-05 06:29:55'),
(4, 1, 'scheduled_followup', 'lead', 1, 'Scheduled Call follow-up for lead #1 on 05 Mar 2026, 10:00 AM.', '2026-03-05 06:30:00'),
(5, 1, 'completed_followup', 'lead', 1, 'Marked follow-up #1 as done for lead #1.', '2026-03-05 06:30:01'),
(6, 1, 'updated_lead_status', 'lead', 1, 'Updated lead #1 status from new to contacted.', '2026-03-05 06:30:04'),
(7, 1, 'updated_lead_status', 'lead', 1, 'Updated lead #1 status from contacted to interested.', '2026-03-05 06:30:06'),
(8, 1, 'updated_lead_status', 'lead', 1, 'Updated lead #1 status from interested to closed won.', '2026-03-05 06:30:08'),
(9, 1, 'converted_lead', 'lead', 1, 'Linked lead #1 (test) to existing client #2.', '2026-03-05 06:30:11'),
(10, 1, 'created_lead', 'lead', 2, 'Created lead \"test2\" (+971501111001) with status: new.', '2026-03-05 06:30:53'),
(11, 1, 'scheduled_followup', 'lead', 2, 'Scheduled Call follow-up for lead #2 on 05 Mar 2026, 10:00 AM.', '2026-03-05 06:30:56'),
(12, 1, 'completed_followup', 'lead', 2, 'Marked follow-up #2 as done for lead #2.', '2026-03-05 06:30:57'),
(13, 1, 'updated_lead_status', 'lead', 2, 'Updated lead #2 status from new to contacted.', '2026-03-05 06:31:02'),
(14, 1, 'updated_lead_status', 'lead', 2, 'Updated lead #2 status from contacted to interested.', '2026-03-05 06:31:04'),
(15, 1, 'updated_lead_status', 'lead', 2, 'Updated lead #2 status from interested to future.', '2026-03-05 06:31:06'),
(16, 1, 'updated_lead_status', 'lead', 2, 'Updated lead #2 status from future to closed won.', '2026-03-05 06:31:08'),
(17, 1, 'converted_lead', 'lead', 2, 'Converted lead #2 (test2) to new client #3.', '2026-03-05 06:31:12'),
(18, 1, 'return', 'reservation', 2, 'Returned reservation #2 — zaman → bmw m4. Due at return: $700.00.', '2026-03-06 08:17:24'),
(19, 1, 'return', 'reservation', 1, 'Returned reservation #1 — hamad → Mercedes-Benz S-Class S 350d. Due at return: $5,000.00.', '2026-03-06 08:18:32'),
(20, 1, 'delivery', 'reservation', 3, 'Delivered reservation #3 — hamad → bmw m4 (kl05tm). Collected: $650.00.', '2026-03-06 08:45:22'),
(21, 1, 'gps_update', 'reservation', 3, 'Updated GPS for reservation #3 (bmw m4 - kl05tm). At Location: Yes, location: thrissur kodungallur, reason: n/a.', '2026-03-06 08:45:55'),
(22, 1, 'gps_update', 'reservation', 3, 'Updated GPS for reservation #3 (bmw m4 - kl05tm). At Location: No, location: thrissur kodungallur, reason: as.', '2026-03-06 08:46:32'),
(23, 1, 'return', 'reservation', 3, 'Returned reservation #3 — hamad → bmw m4. Due at return: $0.00.', '2026-03-06 08:47:08'),
(24, 1, 'created_lead', 'lead', 3, 'Created lead \"hamad\" (6435627845) with status: new.', '2026-03-06 13:50:22'),
(25, 1, 'scheduled_followup', 'lead', 3, 'Scheduled Call follow-up for lead #3 on 06 Mar 2026, 08:00 PM.', '2026-03-06 14:03:03'),
(26, 1, 'completed_followup', 'lead', 3, 'Marked follow-up #3 as done for lead #3.', '2026-03-06 14:07:46'),
(27, 1, 'scheduled_followup', 'lead', 3, 'Scheduled Call follow-up for lead #3 on 06 Mar 2026, 08:30 PM.', '2026-03-06 14:08:06'),
(28, 1, 'completed_followup', 'lead', 3, 'Marked follow-up #4 as done for lead #3.', '2026-03-06 14:08:09'),
(29, 1, 'scheduled_followup', 'lead', 3, 'Scheduled Call follow-up for lead #3 on 06 Mar 2026, 09:00 PM.', '2026-03-06 14:08:17'),
(30, 1, 'completed_followup', 'lead', 3, 'Marked follow-up #5 as done for lead #3.', '2026-03-06 14:08:20'),
(31, 1, 'scheduled_followup', 'lead', 3, 'Scheduled Call follow-up for lead #3 on 06 Mar 2026, 10:00 PM.', '2026-03-06 14:08:26'),
(32, 1, 'updated_lead_status', 'lead', 3, 'Updated lead #3 status from new to contacted.', '2026-03-06 14:08:32'),
(33, 1, 'completed_followup', 'lead', 3, 'Marked follow-up #6 as done for lead #3.', '2026-03-06 14:08:35'),
(34, 1, 'auto_closed_lost', 'lead', 3, 'Lead #3 auto-closed to Lost after 4 follow-ups.', '2026-03-06 14:08:35'),
(35, 1, 'created_lead', 'lead', 4, 'Created lead \"ijas\" (6435627845) with status: new.', '2026-03-06 14:11:24'),
(36, 1, 'scheduled_followup', 'lead', 4, 'Scheduled Call follow-up for lead #4 on 06 Mar 2026, 08:00 PM.', '2026-03-06 14:11:35'),
(37, 1, 'scheduled_followup', 'lead', 4, 'Scheduled Call follow-up for lead #4 on 06 Mar 2026, 10:00 PM.', '2026-03-06 14:11:47'),
(38, 1, 'scheduled_followup', 'lead', 4, 'Scheduled Call follow-up for lead #4 on 06 Mar 2026, 08:30 PM.', '2026-03-06 14:11:55'),
(39, 1, 'scheduled_followup', 'lead', 4, 'Scheduled Call follow-up for lead #4 on 06 Mar 2026, 09:00 PM.', '2026-03-06 14:12:02'),
(40, 1, 'completed_followup', 'lead', 4, 'Marked follow-up #8 as done for lead #4.', '2026-03-06 14:12:05'),
(41, 1, 'completed_followup', 'lead', 4, 'Marked follow-up #7 as done for lead #4.', '2026-03-06 14:12:06'),
(42, 1, 'completed_followup', 'lead', 4, 'Marked follow-up #9 as done for lead #4.', '2026-03-06 14:12:07'),
(43, 1, 'completed_followup', 'lead', 4, 'Marked follow-up #10 as done for lead #4.', '2026-03-06 14:12:09'),
(44, 1, 'scheduled_followup', 'lead', 4, 'Scheduled Call follow-up for lead #4 on 06 Mar 2026, 10:00 PM.', '2026-03-06 14:12:20'),
(45, 1, 'completed_followup', 'lead', 4, 'Marked follow-up #11 as done for lead #4.', '2026-03-06 14:12:35'),
(46, 1, 'deleted_lead', 'lead', 4, 'Deleted lead #4 (ijas).', '2026-03-06 14:13:01'),
(47, 1, 'created_lead', 'lead', 5, 'Created lead \"test\" (+971501111001) with status: new.', '2026-03-06 14:15:56'),
(48, 1, 'scheduled_followup', 'lead', 5, 'Scheduled Call follow-up for lead #5 on 06 Mar 2026, 08:00 PM.', '2026-03-06 14:16:03'),
(49, 1, 'scheduled_followup', 'lead', 5, 'Scheduled Call follow-up for lead #5 on 06 Mar 2026, 08:30 PM.', '2026-03-06 14:16:18'),
(50, 1, 'scheduled_followup', 'lead', 5, 'Scheduled Call follow-up for lead #5 on 06 Mar 2026, 08:45 PM.', '2026-03-06 14:16:27'),
(51, 1, 'scheduled_followup', 'lead', 5, 'Scheduled Call follow-up for lead #5 on 06 Mar 2026, 09:00 PM.', '2026-03-06 14:16:35'),
(52, 1, 'completed_followup', 'lead', 5, 'Marked follow-up #12 as done for lead #5.', '2026-03-06 14:16:38'),
(53, 1, 'completed_followup', 'lead', 5, 'Marked follow-up #13 as done for lead #5.', '2026-03-06 14:16:40'),
(54, 1, 'completed_followup', 'lead', 5, 'Marked follow-up #14 as done for lead #5.', '2026-03-06 14:16:42'),
(55, 1, 'completed_followup', 'lead', 5, 'Marked follow-up #15 as done for lead #5.', '2026-03-06 14:16:43'),
(56, 1, 'scheduled_followup', 'lead', 5, 'Scheduled Call follow-up for lead #5 on 06 Mar 2026, 10:00 PM.', '2026-03-06 14:20:46'),
(57, 1, 'completed_followup', 'lead', 5, 'Marked follow-up #16 as done for lead #5.', '2026-03-06 14:20:50'),
(58, 1, 'updated_lead', 'lead', 5, 'Updated lead #5 details.', '2026-03-06 15:53:25'),
(59, 1, 'delivery', 'reservation', 4, 'Delivered reservation #4 — hamad → bmw m4 (kl05tm). Collected: $3,000.00.', '2026-03-07 05:45:54'),
(60, 1, 'delivery', 'reservation', 5, 'Delivered reservation #5 — hamad → Mercedes-Benz S-Class S 350d (kl05tR). Collected: $281.00.', '2026-03-07 05:46:27'),
(61, 1, 'delivery', 'reservation', 6, 'Delivered reservation #6 — hamad → Thar Roxx (ABC-1234). Collected: $1,650.00.', '2026-03-07 18:02:55'),
(62, 1, 'delivery', 'reservation', 7, 'Delivered reservation #7 — zaman → added added (dfd2). Collected: $700.00.', '2026-03-07 18:29:39'),
(63, 1, 'created_lead', 'lead', 6, 'Created lead \"added\" (1212121212) with status: new.', '2026-03-07 18:37:37'),
(64, 1, 'created_lead', 'lead', 7, 'Created lead \"sd\" (3434343434) with status: new.', '2026-03-07 18:38:01'),
(65, 1, 'return', 'reservation', 7, 'Returned reservation #7 — zaman → added added. Due at return: $1,000.00.', '2026-03-07 18:52:07'),
(66, 1, 'delivery', 'reservation', 8, 'Delivered reservation #8 — hamad → added added (dfd2). Collected: $1,900.00.', '2026-03-07 18:57:32'),
(67, 1, 'return', 'reservation', 8, 'Returned reservation #8 — hamad → added added. Due at return: $0.00.', '2026-03-07 19:00:12'),
(68, 1, 'delivery', 'reservation', 9, 'Delivered reservation #9 — sdsdsd → added added (dfd2). Collected: $900.00.', '2026-03-07 19:12:13'),
(69, 1, 'return', 'reservation', 9, 'Returned reservation #9 — sdsdsd → added added. Due at return: $600.00.', '2026-03-07 19:14:00'),
(70, 1, 'delivery', 'reservation', 10, 'Delivered reservation #10 — hamad → added added (dfd2). Collected: $600.00.', '2026-03-07 19:18:48'),
(71, 1, 'return', 'reservation', 10, 'Returned reservation #10 — hamad → added added. Due at return: $0.00.', '2026-03-07 19:19:43'),
(72, 1, 'created_lead', 'lead', 8, 'Created lead \"today8\" (1212121212) with status: new.', '2026-03-07 19:22:20'),
(73, 1, 'delivery', 'reservation', 11, 'Delivered reservation #11 — hamad → added added (dfd2). Collected: $500.00.', '2026-03-07 19:37:51'),
(74, 1, 'return', 'reservation', 11, 'Returned reservation #11 — hamad → added added. Due at return: $1,000.00.', '2026-03-07 19:39:30'),
(75, 1, 'delivery', 'reservation', 12, 'Delivered reservation #12 — alt → added added (dfd2). Collected: $0.00.', '2026-03-07 19:53:49'),
(76, 1, 'return', 'reservation', 12, 'Returned reservation #12 — alt → added added. Due at return: $0.00.', '2026-03-07 19:55:32'),
(77, 1, 'return', 'reservation', 6, 'Returned reservation #6 — hamad → Thar Roxx. Due at return: $0.00.', '2026-03-07 20:12:15'),
(78, 1, 'delivery', 'reservation', 13, 'Delivered reservation #13 — hamad → sdsd S-Class S 350d (sds3). Collected: $555.00.', '2026-03-07 20:13:20'),
(79, 1, 'delivery', 'reservation', 14, 'Delivered reservation #14 — hamad → bmw m4 (kl05tm). Collected: $800.00.', '2026-03-07 20:26:39'),
(80, 1, 'return', 'reservation', 14, 'Returned reservation #14 — hamad → bmw m4. Due at return: $0.00.', '2026-03-07 20:27:53'),
(81, 1, 'return', 'reservation', 13, 'Returned reservation #13 — hamad → sdsd S-Class S 350d. Due at return: $0.00.', '2026-03-07 20:30:04'),
(82, 1, 'delivery', 'reservation', 15, 'Delivered reservation #15 — hamad → test Roxx (sddfdf3). Collected: $30.00.', '2026-03-08 16:10:56'),
(83, 1, 'gps_update', 'reservation', 15, 'Updated GPS for reservation #15 (test Roxx - sddfdf3). At Location: Yes, location: delivered to thrissur, reason: delivered to thrissur.', '2026-03-08 16:12:06'),
(84, 1, 'return', 'reservation', 15, 'Returned reservation #15 — hamad → test Roxx. Due at return: $0.00.', '2026-03-08 16:13:08'),
(85, 1, 'delivery', 'reservation', 16, 'Delivered reservation #16 — hamad → sdsd S-Class S 350d (sds3). Collected: $1,443.00.', '2026-03-08 16:16:04'),
(86, 1, 'return', 'reservation', 16, 'Returned reservation #16 — hamad → sdsd S-Class S 350d. Due at return: $0.00.', '2026-03-08 16:16:41'),
(87, 1, 'delivery', 'reservation', 17, 'Delivered reservation #17 — hamad → sdsd S-Class S 350d (sds3). Collected: $28,638.00.', '2026-03-08 17:00:07'),
(88, 1, 'return', 'reservation', 17, 'Returned reservation #17 — hamad → sdsd S-Class S 350d. Due at return: $0.00.', '2026-03-08 17:00:57'),
(89, 1, 'gps_update', 'reservation', 5, 'Updated GPS for reservation #5 (Mercedes-Benz S-Class S 350d - kl05tR). At Location: No, location: Not set, reason: Initial delivery.', '2026-03-09 09:21:53'),
(90, 1, 'return', 'reservation', 5, 'Returned reservation #5 — hamad → Mercedes-Benz S-Class S 350d. Due at return: $2,009.00.', '2026-03-09 19:21:25'),
(91, 1, 'return', 'reservation', 4, 'Returned reservation #4 — hamad → bmw m4. Due at return: $1,500.00.', '2026-03-09 19:22:43'),
(92, 1, 'delivery', 'reservation', 18, 'Delivered reservation #18 — hamad → test Roxx (sddfdf3). Collected: $73.00.', '2026-03-09 19:23:35'),
(93, 1, 'return', 'reservation', 18, 'Returned reservation #18 — hamad → test Roxx. Due at return: $1,500.00.', '2026-03-09 19:24:50'),
(94, 1, 'delivery', 'reservation', 19, 'Delivered reservation #19 — hamad → bmw m4 (kl05tm). Collected: $1,150.00.', '2026-03-09 19:37:12'),
(95, 1, 'return', 'reservation', 19, 'Returned reservation #19 — hamad → bmw m4. Due at return: $300.00.', '2026-03-09 19:38:55'),
(96, 1, 'delivery', 'reservation', 21, 'Delivered reservation #21 — hamad → bmw m4 (kl05tm). Collected: $500.00.', '2026-03-11 05:31:01'),
(97, 1, 'cancellation', 'reservation', 21, 'Cancelled reservation #21 — hamad  bmw m4 (kl05tm). Refund: $500. Reason: sdsd.', '2026-03-11 05:31:55'),
(98, 1, 'delivery', 'reservation', 22, 'Delivered reservation #22 — alt → Mercedes-Benz S-Class S 350d (kl05tR). Collected: $168.00.', '2026-03-11 07:19:52'),
(99, 1, 'return', 'reservation', 22, 'Returned reservation #22 — alt → Mercedes-Benz S-Class S 350d. Due at return: $200.00.', '2026-03-11 07:25:35'),
(100, 1, 'delivery', 'reservation', 25, 'Delivered reservation #25 — alt → Thar Roxx (ABC-1234). Collected: $4,150.00.', '2026-03-11 08:48:06'),
(101, 1, 'return', 'reservation', 25, 'Returned reservation #25 — alt → Thar Roxx. Due at return: $200.00.', '2026-03-11 08:48:53'),
(102, 1, 'delivery', 'reservation', 24, 'Delivered reservation #24 — hamad → bmw m4 (kl05tm). Collected: $10,850.00.', '2026-03-12 05:34:22'),
(103, 1, 'delivery', 'reservation', 26, 'Delivered reservation #26 — multiproof → Thar Roxx (ABC-1234). Collected: $4,500.00.', '2026-03-12 05:52:00'),
(104, 1, 'return', 'reservation', 26, 'Returned reservation #26 — multiproof → Thar Roxx. Due at return: $200.00.', '2026-03-12 05:52:53'),
(105, 1, 'return', 'reservation', 24, 'Returned reservation #24 — hamad → bmw m4. Due at return: $200.00.', '2026-03-12 15:09:22'),
(106, 1, 'delivery', 'reservation', 27, 'Delivered reservation #27 — withproof → bmw m4 (kl05tm). Collected: $250.00.', '2026-03-13 08:50:28'),
(107, 1, 'delivery', 'reservation', 28, 'Delivered reservation #28 — vlcsnap-2024-09-20-15h40m39s687.png → Thar Roxx (ABC-1234). Collected: $200.00.', '2026-03-13 09:32:09'),
(108, 1, 'scheduled_followup', 'lead', 8, 'Scheduled Call follow-up for lead #8 on 14 Mar 2026, 10:00 AM.', '2026-03-13 10:35:44'),
(109, 1, 'completed_followup', 'lead', 8, 'Marked follow-up #17 as done for lead #8.', '2026-03-13 10:35:46'),
(110, 1, 'created_lead', 'lead', 9, 'Created lead \"hamad\" (6235646799) with status: new.', '2026-03-13 10:36:29'),
(111, 1, 'scheduled_followup', 'lead', 9, 'Scheduled Call follow-up for lead #9 on 14 Mar 2026, 09:00 AM.', '2026-03-13 10:37:10'),
(112, 1, 'completed_followup', 'lead', 9, 'Marked follow-up #18 as done for lead #9.', '2026-03-13 10:37:13'),
(113, 1, 'scheduled_followup', 'lead', 9, 'Scheduled Call follow-up for lead #9 on 14 Mar 2026, 09:30 AM.', '2026-03-13 10:37:22'),
(114, 1, 'completed_followup', 'lead', 9, 'Marked follow-up #19 as done for lead #9.', '2026-03-13 10:37:24'),
(115, 1, 'scheduled_followup', 'lead', 9, 'Scheduled Call follow-up for lead #9 on 14 Mar 2026, 10:00 AM.', '2026-03-13 10:37:29'),
(116, 1, 'completed_followup', 'lead', 9, 'Marked follow-up #20 as done for lead #9.', '2026-03-13 10:37:32'),
(117, 1, 'scheduled_followup', 'lead', 9, 'Scheduled Call follow-up for lead #9 on 14 Mar 2026, 10:30 AM.', '2026-03-13 10:37:40'),
(118, 1, 'completed_followup', 'lead', 9, 'Marked follow-up #21 as done for lead #9.', '2026-03-13 10:37:42'),
(119, 1, 'auto_closed_lost', 'lead', 9, 'Lead #9 auto-closed to Lost after 4 follow-ups.', '2026-03-13 10:37:42'),
(120, 1, 'return', 'reservation', 28, 'Returned reservation #28 — vlcsnap-2024-09-20-15h40m39s687.png → Thar Roxx. Due at return: $200.00.', '2026-03-13 10:48:20'),
(121, 1, 'updated_lead_status', 'lead', 8, 'Updated lead #8 status from new to contacted.', '2026-03-13 17:16:32'),
(122, 1, 'updated_lead_status', 'lead', 8, 'Updated lead #8 status from contacted to interested.', '2026-03-13 17:16:33'),
(123, 1, 'updated_lead_status', 'lead', 8, 'Updated lead #8 status from interested to closed won.', '2026-03-13 17:16:35'),
(124, 1, 'created_lead', 'lead', 10, 'Created lead \"hamad\" (6235646792) with status: new.', '2026-03-13 17:28:22'),
(125, 1, 'updated_lead_status', 'lead', 10, 'Updated lead #10 status from new to contacted.', '2026-03-13 17:28:28'),
(126, 1, 'updated_lead_status', 'lead', 10, 'Updated lead #10 status from contacted to interested.', '2026-03-13 17:28:29'),
(127, 1, 'updated_lead_status', 'lead', 10, 'Updated lead #10 status from interested to closed won.', '2026-03-13 17:28:32'),
(128, 1, 'converted_lead', 'lead', 10, 'Converted lead #10 (hamad) to new client #14.', '2026-03-13 17:29:07'),
(129, 1, 'delivery', 'reservation', 29, 'Delivered reservation #29 — hamad → bmw m4 (kl05tm). Collected: $3,600.00.', '2026-03-16 09:30:28'),
(130, 1, 'delivery', 'reservation', 30, 'Delivered reservation #30 — hamad → Mercedes-Benz S-Class S 350d (kl05tR). Collected: $36.00.', '2026-03-16 09:32:14'),
(131, 1, 'delivery', 'reservation', 31, 'Delivered reservation #31 — vlcsnap-2024-09-20-15h40m39s687.png → Thar Roxx (ABC-1234). Collected: $36,000.00.', '2026-03-16 11:43:05'),
(132, 1, 'delivery', 'reservation', 32, 'Delivered reservation #32 — hamad → test Roxx (sddfdf3). Collected: $1.00.', '2026-03-16 12:04:11'),
(133, 1, 'delivery', 'reservation', 33, 'Delivered reservation #33 — zaman → tesla v1 (dfd). Collected: $5,000.00.', '2026-03-16 15:25:31'),
(134, 1, 'return', 'reservation', 33, 'Returned reservation #33 — zaman → tesla v1. Due at return: $200.00.', '2026-03-16 17:38:10'),
(135, 1, 'return', 'reservation', 32, 'Returned reservation #32 — hamad → test Roxx. Due at return: $200.00.', '2026-03-16 17:41:25'),
(136, 1, 'delivery', 'reservation', 34, 'Delivered reservation #34 — alt → tesla v1 (dfd). Collected: $58,000.00.', '2026-03-16 17:44:20'),
(137, 1, 'delivery', 'reservation', 35, 'Delivered reservation #35 — withproof → test Roxx (sddfdf3). Collected: $762.00.', '2026-03-17 04:38:58'),
(138, 1, 'return', 'reservation', 35, 'Returned reservation #35 — withproof → test Roxx. Due at return: $200.00.', '2026-03-17 04:39:46'),
(139, 1, 'scheduled_followup', 'lead', 7, 'Scheduled Call follow-up for lead #7 on 18 Mar 2026, 10:00 AM.', '2026-03-17 04:53:55'),
(140, 1, 'completed_followup', 'lead', 7, 'Marked follow-up #22 as done for lead #7.', '2026-03-17 04:54:00'),
(141, 1, 'scheduled_followup', 'lead', 7, 'Scheduled Call follow-up for lead #7 on 18 Mar 2026, 11:00 AM.', '2026-03-17 04:54:08'),
(142, 1, 'completed_followup', 'lead', 7, 'Marked follow-up #23 as done for lead #7.', '2026-03-17 04:54:11'),
(143, 1, 'scheduled_followup', 'lead', 7, 'Scheduled Call follow-up for lead #7 on 18 Mar 2026, 11:15 AM.', '2026-03-17 04:54:43'),
(144, 1, 'completed_followup', 'lead', 7, 'Marked follow-up #24 as done for lead #7.', '2026-03-17 04:54:45'),
(145, 1, 'auto_closed_lost', 'lead', 7, 'Lead #7 auto-closed to Lost after 3 follow-ups.', '2026-03-17 04:54:45'),
(146, 1, 'updated_lead_status', 'lead', 6, 'Updated lead #6 status from new to closed lost. Lost reason: manual.', '2026-03-17 06:32:29'),
(147, 1, 'created_lead', 'lead', 11, 'Created lead \"wed\" (1122334455) with status: new.', '2026-03-17 09:51:09'),
(148, 1, 'scheduled_followup', 'lead', 11, 'Scheduled Call follow-up for lead #11 on 17 Mar 2026, 07:00 PM.', '2026-03-17 10:26:09'),
(149, 1, 'scheduled_followup', 'lead', 11, 'Scheduled Call follow-up for lead #11 on 18 Mar 2026, 10:00 AM.', '2026-03-17 10:43:27'),
(150, 1, 'delivery', 'reservation', 38, 'Delivered reservation #38 — hamad → new new (sdsdw). Collected: $185.00.', '2026-03-17 19:38:54'),
(151, 1, 'return', 'reservation', 38, 'Returned reservation #38 — hamad → new new. Due at return: $55.00.', '2026-03-17 19:45:23'),
(152, 1, 'delivery', 'reservation', 39, 'Delivered reservation #39 — test5 → to test the bug t555 (dfdf34). Collected: $30.00.', '2026-03-19 07:59:20'),
(153, 1, 'delivery', 'reservation', 41, 'Delivered reservation #41 — confirm test → confirm test confirm test (dfdf5). Collected: $6.00.', '2026-03-19 08:52:44'),
(154, 1, 'return', 'reservation', 41, 'Returned reservation #41 — confirm test → confirm test confirm test. Due at return: $200.00.', '2026-03-21 14:42:11'),
(155, 1, 'return', 'reservation', 39, 'Returned reservation #39 — test5 → to test the bug t555. Due at return: $200.00.', '2026-03-21 14:43:43'),
(156, 1, 'return', 'reservation', 34, 'Returned reservation #34 — alt → tesla v1. Due at return: $200.00.', '2026-03-21 14:44:27'),
(157, 1, 'delivery', 'reservation', 44, 'Delivered reservation #44 — added test → new new (sdsdw). Collected: $27,000.00.', '2026-03-22 06:21:23'),
(158, 1, 'delivery', 'reservation', 45, 'Delivered reservation #45 — hamad → tesla v1 (dfd). Collected: $266,000.00.', '2026-03-22 06:29:58'),
(159, 1, 'delivery', 'reservation', 46, 'Delivered reservation #46 — alt → test Roxx (sddfdf3). Collected: $26,500.00.', '2026-03-22 06:41:39'),
(160, 1, 'delivery', 'reservation', 47, 'Delivered reservation #47 — alt → new new (sdsdw). Collected: $63,100.00.', '2026-03-22 06:47:23'),
(161, 1, 'return', 'reservation', 47, 'Returned reservation #47 — alt → new new. Due at return: $1,200.00.', '2026-03-23 12:49:46'),
(162, 1, 'delivery', 'reservation', 48, 'Delivered reservation #48 — alt → to test the bug t555 (dfdf34). Collected: $264.00.', '2026-03-23 18:04:45'),
(163, 1, 'return', 'reservation', 48, 'Returned reservation #48 — alt → to test the bug t555. Due at return: $200.00.', '2026-03-23 18:09:08'),
(164, 1, 'delivery', 'reservation', 49, 'Delivered reservation #49 — alt → to test the bug t555 (dfdf34). Collected: $50.00.', '2026-03-23 18:13:28'),
(165, 1, 'return', 'reservation', 49, 'Returned reservation #49 — alt → to test the bug t555. Due at return: $200.00.', '2026-03-23 18:17:59'),
(166, 1, 'delivery', 'reservation', 50, 'Delivered reservation #50 — vlcsnap-2024-09-20-15h40m39s687.png → to test the bug t555 (dfdf34). Collected: $142.00.', '2026-03-23 18:21:00'),
(167, 1, 'return', 'reservation', 50, 'Returned reservation #50 — vlcsnap-2024-09-20-15h40m39s687.png → to test the bug t555. Due at return: $200.00.', '2026-03-23 18:31:22'),
(168, 1, 'deposit_released', 'reservation', 50, 'Held deposit of $43.8 released to client for reservation #50 (vlcsnap-2024-09-20-15h40m39s687.png).', '2026-03-23 18:48:41'),
(169, 1, 'return', 'reservation', 46, 'Returned reservation #46 — alt → test Roxx. Due at return: $1,200.00.', '2026-03-23 19:18:00'),
(170, 1, 'deposit_converted', 'reservation', 46, 'Held deposit of $1000 converted to income for reservation #46 (alt).', '2026-03-23 19:18:26'),
(171, 1, 'delivery', 'reservation', 51, 'Delivered reservation #51 — confirm test → test Roxx (sddfdf3). Collected: $12,100.00.', '2026-03-24 04:35:58'),
(172, 1, 'return', 'reservation', 51, 'Returned reservation #51 — confirm test → test Roxx. Due at return: $1,000.00.', '2026-03-24 04:36:57'),
(173, 1, 'deposit_converted', 'reservation', 51, 'Held deposit of $1000 converted to income for reservation #51 (confirm test).', '2026-03-24 05:42:22'),
(174, 1, 'delivery', 'reservation', 52, 'Delivered reservation #52 — added test → test Roxx (sddfdf3). Collected: $9,300.00.', '2026-03-24 12:38:16'),
(175, 1, 'return', 'reservation', 52, 'Returned reservation #52 — added test → test Roxx. Due at return: $200.00.', '2026-03-24 12:46:22'),
(176, 1, 'delivery', 'reservation', 53, 'Delivered reservation #53 — multiproof → test Roxx (sddfdf3). Collected: $9,500.00.', '2026-03-24 13:34:03'),
(177, 1, 'return', 'reservation', 53, 'Returned reservation #53 — multiproof → test Roxx. Due at return: $200.00.', '2026-03-24 13:35:16'),
(178, 1, 'return', 'reservation', 45, 'Returned reservation #45 — hamad → tesla v1. Due at return: $200.00.', '2026-03-24 14:13:46'),
(179, 1, 'delivery', 'reservation', 54, 'Delivered reservation #54 — added test → ferrari f (fdfdf). Collected: $745,000.00.', '2026-03-25 11:07:47'),
(180, 1, 'return', 'reservation', 54, 'Returned reservation #54 — added test → ferrari f. Due at return: $200.00.', '2026-03-25 11:15:32'),
(181, 1, 'created_lead', 'lead', 12, 'Created lead \"testing time\" (5673456783) with status: new.', '2026-03-26 04:34:02'),
(182, 1, 'return', 'reservation', 44, 'Returned reservation #44 — added test → new new. Due at return: $211.00.', '2026-03-28 05:44:48'),
(183, 1, 'return', 'reservation', 31, 'Returned reservation #31 — vlcsnap-2024-09-20-15h40m39s687.png → Thar Roxx. Due at return: $200.00.', '2026-03-28 05:47:59'),
(184, 1, 'create_reservation', 'reservation', 55, 'Created reservation #55 for hamad (new new) — $1700', '2026-04-03 07:20:57'),
(185, 1, 'delivery', 'reservation', 55, 'Delivered reservation #55 — hamad → new new (sdsdw). Collected: $1,700.00.', '2026-04-03 07:22:02'),
(186, 1, 'return', 'reservation', 55, 'Returned reservation #55 — hamad → new new. Due at return: $200.00.', '2026-04-03 07:23:06'),
(187, 1, 'update_settings', 'settings', NULL, 'Updated general settings', '2026-04-04 05:39:20'),
(188, 1, 'create_reservation', 'reservation', 56, 'Created reservation #56 for added test (new new) — $400', '2026-04-04 05:39:45'),
(189, 1, 'update_settings', 'settings', NULL, 'Updated general settings', '2026-04-04 05:40:53'),
(190, 1, 'delivery', 'reservation', 56, 'Delivered reservation #56 — added test → new new (sdsdw). Collected: $400.00.', '2026-04-07 09:44:43'),
(191, 1, 'update_settings', 'settings', NULL, 'Updated expense categories', '2026-04-07 16:59:03'),
(192, 1, 'edit_vehicle', 'vehicle', 4, 'Edited vehicle added added (dfd2)', '2026-04-08 05:27:50'),
(193, 1, 'create_reservation', 'reservation', 68, 'Created reservation #68 for added test (Thar Roxx) — $3000', '2026-04-08 05:43:33'),
(194, 1, 'create_vehicle', 'vehicle', 31, 'Added vehicle Test Car Budget (TEST-123)', '2026-04-08 05:47:05'),
(195, 1, 'edit_reservation', 'reservation', 68, 'Edited reservation #68 — $150', '2026-04-08 06:57:30'),
(196, 1, 'delivery', 'reservation', 68, 'Delivered reservation #68 — added test → Test Car Budget (TEST-123). Collected: $0.00.', '2026-04-08 07:38:06'),
(197, 1, 'extend_reservation', 'reservation', 68, 'Extended reservation #68 by 5 days to 2026-04-15 01:00:00 — $250 (cash)', '2026-04-08 09:52:45'),
(198, 1, 'extend_reservation', 'reservation', 68, 'Extended reservation #68 by 5 days to 2026-04-20 01:00:00 — $250 (cash)', '2026-04-08 09:59:26'),
(199, 1, 'create_vehicle', 'vehicle', 32, 'Added vehicle swift 1 (dsfd44)', '2026-04-08 10:01:45'),
(200, 1, 'create_reservation', 'reservation', 69, 'Created reservation #69 for added test (added added) — $200', '2026-04-08 10:27:13'),
(201, 1, 'create_reservation', 'reservation', 70, 'Created reservation #70 for added test (bmw m4) — $4300', '2026-04-08 11:39:17'),
(202, 1, 'return', 'reservation', 68, 'Returned reservation #68 — added test → Test Car Budget. Due at return: $180,062.00.', '2026-04-09 05:08:07'),
(203, 1, 'created_lead', 'lead', 13, 'Created lead \"testin interested\" (2345237744) with status: interested.', '2026-04-09 05:43:04'),
(204, 1, 'create_reservation', 'reservation', 71, 'Created reservation #71 for john (ferrari f) — $450000', '2026-04-09 06:13:15'),
(205, 1, 'cancellation', 'reservation', 71, 'Cancelled reservation #71 — john  ferrari f (fdfdf). Refund: $2000. Reason: testing.', '2026-04-09 06:16:25'),
(206, 2, 'return', 'reservation', 56, 'Returned reservation #56 — added test → new new. Due at return: $150.00.', '2026-04-09 07:38:55'),
(207, 1, 'create_reservation', 'reservation', 72, 'Created reservation #72 for req (ferrari f) — $550000', '2026-04-09 08:16:10'),
(208, 1, 'completed_followup', 'lead', 11, 'Marked follow-up #25 as done for lead #11.', '2026-04-10 10:04:22'),
(209, 1, 'completed_followup', 'lead', 11, 'Marked follow-up #26 as done for lead #11.', '2026-04-10 10:04:31'),
(210, 1, 'delivery', 'reservation', 73, 'Delivered reservation #73 — hamad → bmw m4 (kl05tm). Collected: $5,000.00.', '2026-04-12 07:20:05'),
(211, 1, 'return', 'reservation', 73, 'Returned reservation #73 — hamad → bmw m4. Due at return: $200.00.', '2026-04-12 07:22:25'),
(212, 1, 'create_vehicle', 'vehicle', 33, 'Added vehicle test veh test veh (dfd3324)', '2026-04-12 07:34:17'),
(213, 1, 'edit_vehicle', 'vehicle', 33, 'Edited vehicle test veh test veh (dfd3324)', '2026-04-12 07:34:48');

-- --------------------------------------------------------

--
-- Table structure for table `staff_attendance`
--

CREATE TABLE `staff_attendance` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `punch_in` datetime DEFAULT NULL,
  `pin_warning` tinyint(1) NOT NULL DEFAULT 0,
  `late_reason` text DEFAULT NULL,
  `punch_in_lat` decimal(10,7) DEFAULT NULL,
  `punch_in_lng` decimal(10,7) DEFAULT NULL,
  `punch_in_address` varchar(500) DEFAULT NULL,
  `punch_out` datetime DEFAULT NULL,
  `pout_warning` tinyint(1) NOT NULL DEFAULT 0,
  `early_punchout_reason` text DEFAULT NULL,
  `punch_out_lat` decimal(10,7) DEFAULT NULL,
  `punch_out_lng` decimal(10,7) DEFAULT NULL,
  `punch_out_address` varchar(500) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `admin_note` varchar(500) DEFAULT NULL,
  `is_manual_punch` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff_attendance`
--

INSERT INTO `staff_attendance` (`id`, `user_id`, `date`, `punch_in`, `pin_warning`, `late_reason`, `punch_in_lat`, `punch_in_lng`, `punch_in_address`, `punch_out`, `pout_warning`, `early_punchout_reason`, `punch_out_lat`, `punch_out_lng`, `punch_out_address`, `notes`, `admin_note`, `is_manual_punch`) VALUES
(5, 2, '2026-03-15', '2026-03-15 08:00:00', 0, NULL, NULL, NULL, NULL, '2026-03-15 22:00:00', 0, NULL, NULL, NULL, NULL, NULL, NULL, 0),
(14, 2, '2026-04-10', '2026-04-10 09:00:00', 0, NULL, NULL, NULL, NULL, '2026-04-10 17:00:00', 0, NULL, NULL, NULL, NULL, NULL, NULL, 0),
(15, 2, '2026-04-11', '2026-04-11 08:30:00', 0, NULL, NULL, NULL, NULL, '2026-04-11 18:00:00', 0, NULL, NULL, NULL, NULL, NULL, NULL, 0),
(16, 2, '2026-03-16', '2026-03-16 09:00:00', 0, NULL, NULL, NULL, NULL, '2026-03-16 17:00:00', 0, NULL, NULL, NULL, NULL, NULL, NULL, 0),
(17, 2, '2026-03-17', '2026-03-17 09:00:00', 0, NULL, NULL, NULL, NULL, '2026-03-17 17:00:00', 0, NULL, NULL, NULL, NULL, NULL, NULL, 0),
(18, 2, '2026-03-18', '2026-03-18 09:00:00', 0, NULL, NULL, NULL, NULL, '2026-03-18 17:00:00', 0, NULL, NULL, NULL, NULL, NULL, NULL, 0),
(19, 2, '2026-03-19', '2026-03-19 09:00:00', 0, NULL, NULL, NULL, NULL, '2026-03-19 17:00:00', 0, NULL, NULL, NULL, NULL, NULL, NULL, 0),
(20, 2, '2026-03-20', '2026-03-20 09:00:00', 0, NULL, NULL, NULL, NULL, '2026-03-20 17:00:00', 0, NULL, NULL, NULL, NULL, NULL, NULL, 0),
(21, 2, '2026-03-21', '2026-03-21 09:00:00', 0, NULL, NULL, NULL, NULL, '2026-03-21 17:00:00', 0, NULL, NULL, NULL, NULL, NULL, NULL, 0),
(22, 2, '2026-03-22', '2026-03-22 09:00:00', 0, NULL, NULL, NULL, NULL, '2026-03-22 17:00:00', 0, NULL, NULL, NULL, NULL, NULL, NULL, 0),
(23, 2, '2026-04-16', '2026-04-16 10:55:59', 1, NULL, 10.1925440, 76.1729970, 'Azhikode, Thrissur, Kerala, India', NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `staff_incentives`
--

CREATE TABLE `staff_incentives` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `month` int(11) NOT NULL CHECK (`month` between 1 and 12),
  `year` int(11) NOT NULL CHECK (`year` between 2000 and 2100),
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `note` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `staff_incentives`
--

INSERT INTO `staff_incentives` (`id`, `user_id`, `month`, `year`, `amount`, `note`, `created_at`, `created_by`) VALUES
(1, 2, 2, 2025, 2000.00, 'nice perfomence', '2026-03-11 12:30:48', 1),
(2, 2, 2, 2025, 500.00, 'nice perfomence2', '2026-03-11 12:31:09', 1),
(3, 2, 2, 2026, 3000.00, NULL, '2026-03-17 20:17:40', 1),
(4, 2, 3, 2026, 5000.00, NULL, '2026-03-26 15:09:01', 1);

-- --------------------------------------------------------

--
-- Table structure for table `staff_permissions`
--

CREATE TABLE `staff_permissions` (
  `user_id` int(11) NOT NULL,
  `permission` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff_permissions`
--

INSERT INTO `staff_permissions` (`user_id`, `permission`) VALUES
(2, 'add_leads'),
(2, 'add_reservations'),
(2, 'add_vehicles'),
(2, 'do_delivery'),
(2, 'do_return'),
(2, 'manage_clients'),
(2, 'view_all_vehicles'),
(2, 'view_finances'),
(2, 'view_vehicle_availability'),
(2, 'view_vehicle_requests'),
(3, 'add_leads'),
(3, 'add_reservations'),
(3, 'add_vehicles'),
(3, 'do_delivery'),
(3, 'do_return'),
(3, 'manage_clients'),
(3, 'manage_staff'),
(3, 'view_all_vehicles'),
(3, 'view_finances'),
(5, 'add_leads'),
(5, 'add_reservations'),
(5, 'add_vehicles'),
(5, 'do_delivery'),
(5, 'do_return'),
(5, 'manage_clients'),
(5, 'manage_staff'),
(5, 'view_finances');

-- --------------------------------------------------------

--
-- Table structure for table `staff_tasks`
--

CREATE TABLE `staff_tasks` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `assigned_to` int(11) NOT NULL,
  `assigned_by` int(11) NOT NULL,
  `status` enum('pending','completed') NOT NULL DEFAULT 'pending',
  `completion_note` text DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `key` varchar(100) NOT NULL,
  `value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`key`, `value`, `updated_at`) VALUES
('att_overtime_rate_per_hour', '100', '2026-03-18 14:43:54'),
('att_overtime_threshold_hours', '12', '2026-03-18 14:43:54'),
('att_punchin_end', '10:30 PM', '2026-03-26 16:58:05'),
('att_punchin_start', '10:00 PM', '2026-03-26 16:58:05'),
('att_punchout_end', '05:30 AM', '2026-03-26 16:58:05'),
('att_punchout_start', '05:00 AM', '2026-03-26 16:58:05'),
('auto_close_lost_after_followups', '3', '2026-03-17 04:54:26'),
('company_name', 'Orentincars', '2026-03-05 05:02:13'),
('credit_prioritize_income', '1', '2026-03-26 17:30:26'),
('daily_target', '50000', '2026-03-05 14:50:07'),
('delivery_charge_default', '150', '2026-03-09 19:36:14'),
('delivery_incentive_per_delivery', '0', '2026-03-05 05:02:13'),
('deposit_percentage', '15', '2026-03-07 20:25:43'),
('expense_categories', '[\"Garage Cleaning\",\"Fuel\",\"Rent\",\"Salary\",\"Maintenance\",\"Utilities\",\"Office Expense\",\"Marketing\",\"Miscellaneous\",\"test\"]', '2026-04-07 16:59:03'),
('held_deposit_alert_days', '1', '2026-03-24 04:34:35'),
('held_deposit_test_mode', '0', '2026-03-26 16:41:20'),
('late_return_rate_per_hour', '120', '2026-03-13 11:47:29'),
('lead_incentive_per_lead', '0', '2026-03-05 05:02:13'),
('lead_sources', '[{\"value\":\"walk_in\",\"label\":\"Walk-in\"},{\"value\":\"phone\",\"label\":\"Phone Call\"},{\"value\":\"whatsapp\",\"label\":\"WhatsApp\"},{\"value\":\"instagram\",\"label\":\"Instagram\"},{\"value\":\"referral\",\"label\":\"Referral\"},{\"value\":\"website\",\"label\":\"Website\"},{\"value\":\"other\",\"label\":\"Other\"}]', '2026-03-05 05:03:36'),
('mobile_bottom_nav_keys', '[\"dashboard\",\"accounts\",\"clients\",\"gps\",\"settings\"]', '2026-03-09 18:55:19'),
('notify_due_soon', '1', '2026-03-14 06:36:03'),
('notify_due_today', '1', '2026-03-14 06:36:03'),
('notify_emi_due', '1', '2026-03-16 15:15:35'),
('notify_gps_pending', '1', '2026-03-26 16:49:17'),
('notify_overdue', '1', '2026-03-14 06:36:03'),
('notify_res_cancelled', '0', '2026-03-14 06:36:03'),
('notify_res_created', '1', '2026-03-14 06:36:03'),
('notify_res_delivered', '1', '2026-03-14 06:36:03'),
('notify_res_returned', '1', '2026-03-14 06:36:03'),
('per_page', '15', '2026-03-06 09:01:17'),
('pipeline_pagination_enabled', '1', '2026-03-05 05:03:36'),
('return_pickup_charge_default', '200', '2026-03-09 19:36:14'),
('security_deposit_bank_account_id', '1', '2026-03-07 20:24:20'),
('upcoming_delivery_alert_days', '3', '2026-04-04 05:39:20');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','staff') NOT NULL DEFAULT 'staff',
  `staff_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_online` tinyint(1) DEFAULT 0 COMMENT 'Whether user is currently logged in',
  `last_login_at` datetime DEFAULT NULL COMMENT 'Timestamp of last login',
  `last_logout_at` datetime DEFAULT NULL COMMENT 'Timestamp of last logout',
  `session_id` varchar(255) DEFAULT NULL COMMENT 'Current PHP session ID for tracking'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `username`, `password_hash`, `role`, `staff_id`, `is_active`, `created_at`, `is_online`, `last_login_at`, `last_logout_at`, `session_id`) VALUES
(1, 'Admin', 'admin', '$2y$10$fD/xMKFUGHUlAeW0R9seXeOamoHNmCk.IdnOm1PmckjwP5uABlCnK', 'admin', NULL, 1, '2026-03-05 05:02:13', 1, '2026-04-16 10:56:06', '2026-04-16 10:55:43', '4qg8rhddipvr76gptmm1dtgege'),
(2, 'staff1', 'staff1', '$2y$10$zxp8T7EZcE0JY3o8w.r4PemiY0/2fUG8kE2mlyjRyNfWCQNF.Tesm', 'staff', 1, 1, '2026-03-06 15:25:26', 0, '2026-04-16 10:55:45', '2026-04-16 10:56:03', NULL),
(3, 'staff2', 'staff2', '$2y$10$cdeLHxhiLNbdOHmd2IGyQetyYA8hfCpDviYGl.nN.Z7kDi3iENyHK', 'staff', 2, 1, '2026-03-06 15:26:13', 0, NULL, NULL, NULL),
(4, 'admin2', 'admin2', '$2y$10$KPSPr/XjZMrcB.fQwHaB4.q59mIl78RVNPI/BR8LTq/eo0AesUteK', 'admin', 3, 1, '2026-03-09 07:30:09', 0, NULL, NULL, NULL),
(5, 'staff3', 'staff3', '$2y$10$RL3XeJaA1X3bL.w9ZFHZJuTjDoSe3szuPJS1jOFCY7KdW7f/yJ5I.', 'staff', 4, 1, '2026-03-09 09:28:12', 0, NULL, NULL, NULL),
(6, 'Test User 69c4ecf97aef7', 'testuser_69c4ecf97aeff', '$2y$10$h7sWjWw74eVDVZKLHg37r.i1FFDFAzPlBwPhd2/9itrcpc.rSIF4u', 'staff', 6, 1, '2026-03-26 08:23:21', 0, NULL, NULL, NULL),
(7, 'Test User 69c4edc317854', 'testuser_69c4edc31785e', '$2y$10$xGHZz1ZBSXk6nF/tv2PH.O7zQ5NZ6yVpPBAeZP2I23q4HzvUNKC8y', 'staff', 7, 1, '2026-03-26 08:26:43', 0, NULL, NULL, NULL),
(8, 'Test User 69c4f30048781', 'testuser_69c4f30048788', '$2y$10$DOg7MkYWz9KyeA1D0xVY3.wx0bTqQLAk8Ql2vGQ3YJhoqyizOP5xa', 'staff', 8, 1, '2026-03-26 08:49:04', 0, NULL, NULL, NULL),
(9, 'Test User 69c4f444e6466', 'testuser_69c4f444e6470', '$2y$10$G0oXJo.k7x4hIQi//aayGuJ4I.YWrfbgplhZReKnwQD2CK/.2yxrq', 'staff', 9, 1, '2026-03-26 08:54:28', 0, NULL, NULL, NULL),
(10, 'Test User 69c4f4efda4ae', 'testuser_69c4f4efda4b7', '$2y$10$OpsFbJkfsOgFigcSePr1VOBJtfRPFHa3kExpYAMFqiQ39wZB7rX9G', 'staff', 10, 1, '2026-03-26 08:57:19', 0, NULL, NULL, NULL),
(11, 'Test User 69c4f6678a733', 'testuser_69c4f6678a738', '$2y$10$uq13UU4yymLl.Ywwf3.G4ug97ds.23shxycq6pnRcU6U6o14KpVQW', 'staff', 11, 1, '2026-03-26 09:03:35', 0, NULL, NULL, NULL),
(12, 'Test User 69c4f92b60221', 'testuser_69c4f92b6022f', '$2y$10$O8uwZlle/3Tna5Zh58XIaOkFykR5LnBE8UZO1htIEFaedDLnvGM1G', 'staff', 12, 1, '2026-03-26 09:15:23', 0, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `vehicles`
--

CREATE TABLE `vehicles` (
  `id` int(11) NOT NULL,
  `brand` varchar(255) NOT NULL,
  `model` varchar(255) NOT NULL,
  `year` int(11) NOT NULL,
  `license_plate` varchar(50) NOT NULL,
  `color` varchar(50) DEFAULT NULL,
  `vin` varchar(50) DEFAULT NULL,
  `status` enum('available','rented','maintenance','sold') NOT NULL DEFAULT 'available',
  `sold_at` datetime DEFAULT NULL,
  `maintenance_started_at` datetime DEFAULT NULL,
  `maintenance_expected_return` date DEFAULT NULL,
  `maintenance_workshop_name` varchar(255) DEFAULT NULL,
  `insurance_type` varchar(30) DEFAULT NULL,
  `insurance_expiry_date` date DEFAULT NULL,
  `pollution_expiry_date` date DEFAULT NULL,
  `condition_notes` text DEFAULT NULL,
  `daily_rate` decimal(10,2) NOT NULL DEFAULT 0.00,
  `monthly_rate` decimal(10,2) DEFAULT NULL,
  `rate_1day` decimal(10,2) DEFAULT NULL,
  `rate_7day` decimal(10,2) DEFAULT NULL,
  `rate_15day` decimal(10,2) DEFAULT NULL,
  `rate_30day` decimal(10,2) DEFAULT NULL,
  `image_url` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `parts_due_notes` text DEFAULT NULL,
  `second_key_location` varchar(255) DEFAULT NULL,
  `original_documents_location` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vehicles`
--

INSERT INTO `vehicles` (`id`, `brand`, `model`, `year`, `license_plate`, `color`, `vin`, `status`, `sold_at`, `maintenance_started_at`, `maintenance_expected_return`, `maintenance_workshop_name`, `insurance_type`, `insurance_expiry_date`, `pollution_expiry_date`, `condition_notes`, `daily_rate`, `monthly_rate`, `rate_1day`, `rate_7day`, `rate_15day`, `rate_30day`, `image_url`, `created_at`, `updated_at`, `parts_due_notes`, `second_key_location`, `original_documents_location`) VALUES
(1, 'Mercedes-Benz', 'S-Class S 350d', 2025, 'kl05tR', 'Black', '5656', 'rented', NULL, NULL, NULL, NULL, NULL, '2027-03-02', NULL, NULL, 1.00, NULL, NULL, NULL, NULL, NULL, '', '2026-03-05 05:05:18', '2026-03-16 09:32:14', NULL, NULL, NULL),
(2, 'bmw', 'm4', 2020, 'kl05tm', 'red', '2HGFG3B55CH123456', 'rented', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'dfdf', 100.00, 3000.00, 100.00, 700.00, 1500.00, 3000.00, '', '2026-03-05 05:07:23', '2026-04-12 07:20:05', 'ssd', NULL, NULL),
(3, 'Thar', 'Roxx', 2023, 'ABC-1234', 'red', '2HGFG3B55CH123456', 'available', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'good', 1000.00, 30000.00, 1000.00, 7000.00, 15000.00, 30000.00, '', '2026-03-05 05:12:44', '2026-03-19 07:20:21', NULL, NULL, NULL),
(4, 'added', 'added', 2020, 'dfd2', 'Blue', '', 'available', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100.00, NULL, NULL, NULL, NULL, NULL, '', '2026-03-07 18:27:51', '2026-04-08 05:27:50', NULL, NULL, NULL),
(5, 'test', 'Roxx', 2020, 'sddfdf3', 'red', '5656', 'available', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100.00, NULL, NULL, NULL, NULL, NULL, '', '2026-03-07 19:41:29', '2026-03-24 13:35:16', NULL, NULL, NULL),
(6, 'sdsd', 'S-Class S 350d', 2024, 'sds3', 'sd', '2HGFG3B55CH123456', 'available', NULL, NULL, NULL, NULL, 'third class', '2026-03-08', '2026-03-20', NULL, 111.00, NULL, NULL, NULL, NULL, NULL, '', '2026-03-07 20:02:08', '2026-03-14 06:19:27', NULL, 'dfdf', 'dfd'),
(7, 'tesla', 'v1', 2025, 'dfd', '', 'KM8J33A42NU123456', 'available', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1000.00, NULL, NULL, NULL, NULL, NULL, '', '2026-03-16 15:23:15', '2026-03-24 14:13:46', NULL, NULL, NULL),
(8, 'new', 'new', 2026, 'sdsdw', 'Black', 'ssd', 'available', '2026-03-29 15:06:12', NULL, NULL, NULL, 'first class', '2026-03-15', '2026-03-17', NULL, 100.00, NULL, NULL, NULL, NULL, NULL, '', '2026-03-17 19:32:32', '2026-04-09 07:38:55', NULL, NULL, NULL),
(9, 'to test the bug', 't555', 2020, 'dfdf34', 'Silver', 'sdfdf', 'available', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1.00, NULL, NULL, NULL, NULL, NULL, '', '2026-03-19 07:56:53', '2026-03-23 18:31:22', NULL, NULL, NULL),
(10, 'confirm test', 'confirm test', 2020, 'dfdf5', 'd', 'dfdf345', 'sold', '2026-03-24 20:25:55', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1.00, NULL, NULL, NULL, NULL, NULL, '', '2026-03-19 08:44:15', '2026-03-24 14:55:55', NULL, NULL, NULL),
(11, 'ferrari', 'f', 2025, 'fdfdf', 'red', 'KM8J33A42NU123456', 'available', '2026-03-27 22:28:11', NULL, NULL, NULL, 'first class', NULL, NULL, NULL, 50000.00, 200000.00, 50000.00, 350000.00, 600000.00, 200000.00, '', '2026-03-24 15:07:15', '2026-04-09 06:16:25', NULL, NULL, NULL),
(15, 'TestBrand1', 'Model1', 0, 'TEST69d4b5a4e6d24', NULL, NULL, 'available', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-07 07:43:32', '2026-04-07 07:43:32', NULL, NULL, NULL),
(16, 'TestBrand2', 'Model2', 0, 'TEST69d4b5a4e70b8', NULL, NULL, 'available', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-07 07:43:32', '2026-04-07 07:43:32', NULL, NULL, NULL),
(17, 'TestBrand3', 'Model3', 0, 'TEST69d4b5a4e75e9', NULL, NULL, 'available', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-07 07:43:32', '2026-04-07 07:43:32', NULL, NULL, NULL),
(18, 'TestBrand1', 'Model1', 0, 'TEST69d4b5c236d46', NULL, NULL, 'available', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-07 07:44:02', '2026-04-07 07:44:02', NULL, NULL, NULL),
(19, 'TestBrand2', 'Model2', 0, 'TEST69d4b5c237178', NULL, NULL, 'available', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-07 07:44:02', '2026-04-07 07:44:02', NULL, NULL, NULL),
(20, 'TestBrand3', 'Model3', 0, 'TEST69d4b5c237540', NULL, NULL, 'available', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-07 07:44:02', '2026-04-07 07:44:02', NULL, NULL, NULL),
(21, 'TestBrandDate', 'ModelDate', 0, 'TESTDATE69d4b5c23c463', NULL, NULL, 'available', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-07 07:44:02', '2026-04-07 07:44:02', NULL, NULL, NULL),
(22, 'PreserveBrand1', 'Model1', 0, 'PRES69d4b679b3ba4', NULL, NULL, 'available', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-07 07:47:05', '2026-04-07 07:47:05', NULL, NULL, NULL),
(23, 'PreserveBrand2', 'Model2', 0, 'PRES69d4b679b40e5', NULL, NULL, 'available', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-07 07:47:05', '2026-04-07 07:47:05', NULL, NULL, NULL),
(24, 'PreserveBrand3', 'Model3', 0, 'PRES69d4b679b4508', NULL, NULL, 'available', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-07 07:47:05', '2026-04-07 07:47:05', NULL, NULL, NULL),
(25, 'PreserveBrand4', 'Model4', 0, 'PRES69d4b679b5036', NULL, NULL, 'available', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-07 07:47:05', '2026-04-07 07:47:05', NULL, NULL, NULL),
(26, 'PreserveBrand5', 'Model5', 0, 'PRES69d4b679b5578', NULL, NULL, 'available', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-07 07:47:05', '2026-04-07 07:47:05', NULL, NULL, NULL),
(27, 'TestBrand1', 'Model1', 0, 'TEST69d4b6c91ceed', NULL, NULL, 'available', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-07 07:48:25', '2026-04-07 07:48:25', NULL, NULL, NULL),
(28, 'TestBrand2', 'Model2', 0, 'TEST69d4b6c91d4c2', NULL, NULL, 'available', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-07 07:48:25', '2026-04-07 07:48:25', NULL, NULL, NULL),
(29, 'TestBrand3', 'Model3', 0, 'TEST69d4b6c91e0f4', NULL, NULL, 'available', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-07 07:48:25', '2026-04-07 07:48:25', NULL, NULL, NULL),
(30, 'TestBrandDate', 'ModelDate', 0, 'TESTDATE69d4b6c924d5c', NULL, NULL, 'available', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-07 07:48:25', '2026-04-07 07:48:25', NULL, NULL, NULL),
(31, 'Test Car', 'Budget', 2024, 'TEST-123', 'Silver', 'dfdfdf', 'available', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 50.00, 1500.00, 50.00, 350.00, 750.00, 1500.00, '', '2026-04-08 05:47:05', '2026-04-09 05:08:07', NULL, NULL, NULL),
(32, 'swift', '1', 2020, 'dsfd44', 'Blue', 'df3434', 'available', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1000.00, 30000.00, 1000.00, 7000.00, 15000.00, 30000.00, '', '2026-04-08 10:01:45', '2026-04-08 10:01:45', NULL, NULL, NULL),
(33, 'test veh', 'test veh', 2012, 'dfd3324', 'Silver', 'sd3243', 'available', NULL, NULL, NULL, NULL, 'third class', '2026-12-12', '2026-12-12', NULL, 100.00, 3000.00, 100.00, 700.00, 1500.00, 3000.00, '', '2026-04-12 07:34:17', '2026-04-12 07:34:17', NULL, 'dfdf', 'sd');

-- --------------------------------------------------------

--
-- Table structure for table `vehicle_challans`
--

CREATE TABLE `vehicle_challans` (
  `id` int(11) NOT NULL,
  `vehicle_id` int(11) NOT NULL,
  `client_id` int(11) DEFAULT NULL,
  `reservation_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `due_date` date DEFAULT NULL,
  `status` enum('pending','paid') NOT NULL DEFAULT 'pending',
  `paid_by` enum('company','customer') DEFAULT NULL,
  `paid_date` date DEFAULT NULL,
  `payment_mode` varchar(20) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vehicle_challans`
--

INSERT INTO `vehicle_challans` (`id`, `vehicle_id`, `client_id`, `reservation_id`, `title`, `amount`, `due_date`, `status`, `paid_by`, `paid_date`, `payment_mode`, `notes`, `created_at`, `updated_at`) VALUES
(1, 2, NULL, NULL, 'over speed', 1000.00, '2026-03-27', 'paid', NULL, NULL, NULL, NULL, '2026-03-22 10:14:16', '2026-03-22 10:14:38'),
(2, 3, NULL, NULL, 'over speed', 700.00, '2026-03-20', 'paid', NULL, NULL, NULL, NULL, '2026-03-22 10:42:13', '2026-03-22 10:42:37'),
(3, 7, 3, NULL, 'test', 2000.00, '2026-12-12', 'paid', 'customer', '2026-03-22', NULL, 'sdfdf\nCustomer paid on 22 Mar 2026', '2026-03-22 11:04:45', '2026-03-22 11:04:57'),
(4, 2, NULL, NULL, 'over speed', 5000.00, '2026-05-05', 'paid', 'company', '2026-03-27', 'account', NULL, '2026-03-27 09:14:33', '2026-03-27 09:15:08'),
(5, 4, NULL, NULL, 'sds', 1.00, '2026-04-20', 'pending', NULL, NULL, NULL, 'sdsd', '2026-04-09 04:51:27', '2026-04-09 04:51:27'),
(6, 1, NULL, NULL, 'sdsd', 1.00, '2026-04-20', 'pending', NULL, NULL, NULL, 'sdsd', '2026-04-09 04:52:17', '2026-04-09 04:52:17'),
(7, 31, 15, 68, 'Traffic Challan - Reservation #68', 150.00, '2026-04-20', 'pending', NULL, NULL, NULL, 'Created during vehicle return', '2026-04-09 05:08:07', '2026-04-09 05:08:07');

-- --------------------------------------------------------

--
-- Table structure for table `vehicle_images`
--

CREATE TABLE `vehicle_images` (
  `id` int(11) NOT NULL,
  `vehicle_id` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `cancellation_reason` text DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancellation_by` int(11) DEFAULT NULL,
  `refund_amount` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vehicle_images`
--

INSERT INTO `vehicle_images` (`id`, `vehicle_id`, `file_path`, `sort_order`, `created_at`, `cancellation_reason`, `cancelled_at`, `cancellation_by`, `refund_amount`) VALUES
(1, 1, 'uploads/vehicles/veh_1_69aae3facbd96.jpg', 0, '2026-03-06 19:56:02', NULL, NULL, NULL, NULL),
(2, 5, 'uploads/vehicles/veh_5_69ac7f6939c71.png', 0, '2026-03-08 01:11:29', NULL, NULL, NULL, NULL),
(3, 31, 'uploads/vehicles/veh_31_69d5ebd938180.webp', 0, '2026-04-08 11:17:05', NULL, NULL, NULL, NULL),
(4, 32, 'uploads/vehicles/veh_32_69d62789cd2a3.webp', 0, '2026-04-08 15:31:45', NULL, NULL, NULL, NULL),
(5, 33, 'uploads/vehicles/veh_33_69db4af92cc12.webp', 0, '2026-04-12 13:04:17', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `vehicle_inspections`
--

CREATE TABLE `vehicle_inspections` (
  `id` int(11) NOT NULL,
  `reservation_id` int(11) NOT NULL,
  `type` enum('delivery','return') NOT NULL,
  `fuel_level` int(11) NOT NULL DEFAULT 100,
  `mileage` int(11) NOT NULL DEFAULT 0,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vehicle_inspections`
--

INSERT INTO `vehicle_inspections` (`id`, `reservation_id`, `type`, `fuel_level`, `mileage`, `notes`, `created_at`) VALUES
(1, 2, 'delivery', 100, 12, '', '2026-03-05 05:08:47'),
(2, 1, 'delivery', 100, 12, '', '2026-03-05 05:09:10'),
(3, 2, 'return', 100, 12, '', '2026-03-06 08:17:24'),
(4, 1, 'return', 100, 12, '', '2026-03-06 08:18:32'),
(5, 3, 'delivery', 100, 12, '', '2026-03-06 08:45:22'),
(6, 3, 'return', 100, 12, '', '2026-03-06 08:47:08'),
(7, 4, 'delivery', 100, 12, '', '2026-03-07 05:39:40'),
(8, 4, 'delivery', 100, 12, '', '2026-03-07 05:45:54'),
(9, 5, 'delivery', 100, 12, '', '2026-03-07 05:46:27'),
(10, 6, 'delivery', 100, 12, '', '2026-03-07 18:02:55'),
(11, 7, 'delivery', 100, 12, '', '2026-03-07 18:29:39'),
(12, 7, 'return', 100, 12, '', '2026-03-07 18:52:07'),
(13, 8, 'delivery', 100, 12, '', '2026-03-07 18:57:32'),
(14, 8, 'return', 100, 12, '', '2026-03-07 19:00:12'),
(15, 9, 'delivery', 100, 12, '', '2026-03-07 19:12:13'),
(16, 9, 'return', 100, 12, '', '2026-03-07 19:14:00'),
(17, 10, 'delivery', 100, 12, '', '2026-03-07 19:18:48'),
(18, 10, 'return', 100, 12, '', '2026-03-07 19:19:43'),
(19, 11, 'delivery', 100, 12, '', '2026-03-07 19:37:51'),
(20, 11, 'return', 100, 12, '', '2026-03-07 19:39:30'),
(21, 12, 'delivery', 100, 12, 'sd', '2026-03-07 19:53:49'),
(22, 12, 'return', 100, 12, '', '2026-03-07 19:55:32'),
(23, 6, 'return', 100, 12, '', '2026-03-07 20:12:15'),
(24, 13, 'delivery', 100, 12, '', '2026-03-07 20:13:20'),
(25, 14, 'delivery', 100, 12, '', '2026-03-07 20:26:39'),
(26, 14, 'return', 100, 12, '', '2026-03-07 20:27:53'),
(27, 13, 'return', 100, 12, '', '2026-03-07 20:30:04'),
(28, 15, 'delivery', 100, 12, '', '2026-03-08 16:10:56'),
(29, 15, 'return', 76, 12, '', '2026-03-08 16:13:08'),
(30, 16, 'delivery', 100, 12, '', '2026-03-08 16:16:04'),
(31, 16, 'return', 100, 12, '', '2026-03-08 16:16:41'),
(32, 17, 'delivery', 100, 12, '', '2026-03-08 17:00:07'),
(33, 17, 'return', 100, 12, '', '2026-03-08 17:00:57'),
(34, 5, 'return', 100, 12, '', '2026-03-09 19:21:25'),
(35, 4, 'return', 100, 12, '', '2026-03-09 19:22:43'),
(36, 18, 'delivery', 100, 12, '', '2026-03-09 19:23:35'),
(37, 18, 'return', 100, 12, '', '2026-03-09 19:24:50'),
(38, 19, 'delivery', 100, 12, '', '2026-03-09 19:37:12'),
(39, 19, 'return', 100, 12, '', '2026-03-09 19:38:55'),
(40, 21, 'delivery', 100, 12, '', '2026-03-11 05:31:01'),
(41, 22, 'delivery', 100, 12, '', '2026-03-11 07:19:52'),
(42, 22, 'return', 100, 12, '', '2026-03-11 07:25:35'),
(43, 25, 'delivery', 100, 12, '', '2026-03-11 08:48:06'),
(44, 25, 'return', 100, 12, '', '2026-03-11 08:48:53'),
(45, 24, 'delivery', 100, 12, '', '2026-03-12 05:34:22'),
(46, 26, 'delivery', 100, 12, '', '2026-03-12 05:52:00'),
(47, 26, 'return', 100, 21, '', '2026-03-12 05:52:53'),
(48, 24, 'return', 100, 12, '', '2026-03-12 15:09:22'),
(49, 27, 'delivery', 100, 12, '', '2026-03-13 08:50:28'),
(50, 28, 'delivery', 100, 12, '', '2026-03-13 09:32:09'),
(51, 28, 'return', 100, 12, '', '2026-03-13 10:48:20'),
(52, 29, 'delivery', 100, 12, '', '2026-03-16 09:30:28'),
(53, 30, 'delivery', 100, 12, '', '2026-03-16 09:32:14'),
(54, 31, 'delivery', 100, 12, '', '2026-03-16 11:43:05'),
(55, 32, 'delivery', 100, 12, '', '2026-03-16 12:04:11'),
(56, 33, 'delivery', 100, 12, '', '2026-03-16 15:25:31'),
(57, 33, 'return', 100, 12, '', '2026-03-16 17:38:10'),
(58, 32, 'return', 100, 12, '', '2026-03-16 17:41:25'),
(59, 34, 'delivery', 100, 12, '', '2026-03-16 17:44:20'),
(60, 35, 'delivery', 100, 12, '', '2026-03-17 04:38:58'),
(61, 35, 'return', 100, 12, '', '2026-03-17 04:39:46'),
(62, 38, 'delivery', 100, 12, '', '2026-03-17 19:38:54'),
(63, 38, 'return', 100, 12, '', '2026-03-17 19:45:23'),
(64, 39, 'delivery', 100, 12, '', '2026-03-19 07:59:19'),
(65, 41, 'delivery', 100, 12, '', '2026-03-19 08:52:44'),
(66, 41, 'return', 100, 12, '', '2026-03-21 14:42:10'),
(67, 39, 'return', 100, 12, '', '2026-03-21 14:43:43'),
(68, 34, 'return', 100, 12, '', '2026-03-21 14:44:27'),
(69, 44, 'delivery', 100, 12, '', '2026-03-22 06:21:23'),
(70, 45, 'delivery', 100, 12, '', '2026-03-22 06:29:58'),
(71, 46, 'delivery', 100, 12, '', '2026-03-22 06:41:39'),
(72, 47, 'delivery', 100, 12, '', '2026-03-22 06:47:23'),
(74, 47, 'return', 100, 12, 'df', '2026-03-23 12:49:46'),
(75, 48, 'delivery', 100, 12, '', '2026-03-23 18:04:45'),
(76, 48, 'return', 100, 12, '', '2026-03-23 18:09:08'),
(77, 49, 'delivery', 100, 12, '', '2026-03-23 18:13:28'),
(78, 49, 'return', 100, 12, '', '2026-03-23 18:17:59'),
(79, 50, 'delivery', 100, 12, '', '2026-03-23 18:21:00'),
(80, 50, 'return', 100, 12, '', '2026-03-23 18:31:22'),
(81, 46, 'return', 100, 12, '', '2026-03-23 19:18:00'),
(82, 51, 'delivery', 100, 12, '', '2026-03-24 04:35:58'),
(83, 51, 'return', 100, 12, '', '2026-03-24 04:36:57'),
(84, 52, 'delivery', 100, 12, '', '2026-03-24 12:38:16'),
(85, 52, 'return', 100, 12, '', '2026-03-24 12:46:22'),
(86, 53, 'delivery', 100, 12, '', '2026-03-24 13:34:03'),
(87, 53, 'return', 100, 12, '', '2026-03-24 13:35:16'),
(88, 45, 'return', 100, 12, '', '2026-03-24 14:13:46'),
(89, 54, 'delivery', 100, 12, '', '2026-03-25 11:07:47'),
(90, 54, 'return', 100, 12, '', '2026-03-25 11:15:32'),
(91, 44, 'return', 100, 12, '', '2026-03-28 05:44:48'),
(92, 31, 'return', 100, 12, '', '2026-03-28 05:47:59'),
(93, 55, 'delivery', 100, 15, '', '2026-04-03 07:22:02'),
(94, 55, 'return', 100, 45, '', '2026-04-03 07:23:06'),
(95, 56, 'delivery', 87, 15000, 'testing note', '2026-04-07 09:44:43'),
(96, 68, 'delivery', 100, 12, '', '2026-04-08 07:38:06'),
(97, 68, 'return', 100, 15000, '', '2026-04-09 05:08:07'),
(98, 56, 'return', 100, 15000, '', '2026-04-09 07:38:55'),
(99, 73, 'delivery', 60, 13, 'test', '2026-04-12 07:20:05'),
(100, 73, 'return', 20, 12, '', '2026-04-12 07:22:25');

-- --------------------------------------------------------

--
-- Table structure for table `vehicle_job_cards`
--

CREATE TABLE `vehicle_job_cards` (
  `id` int(11) NOT NULL,
  `vehicle_id` int(11) NOT NULL,
  `inspection_date` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vehicle_job_cards`
--

INSERT INTO `vehicle_job_cards` (`id`, `vehicle_id`, `inspection_date`, `created_by`, `created_at`) VALUES
(1, 2, '2026-03-29 17:21:39', 1, '2026-03-29 11:51:39'),
(2, 2, '2026-03-29 17:22:01', 1, '2026-03-29 11:52:01'),
(7, 2, '2026-04-01 11:03:47', 1, '2026-04-01 05:33:47'),
(8, 4, '2026-04-01 11:04:08', 1, '2026-04-01 05:34:08'),
(9, 4, '2026-04-01 11:05:50', 1, '2026-04-01 05:35:50'),
(10, 2, '2026-04-01 11:06:54', 1, '2026-04-01 05:36:54'),
(11, 2, '2026-04-01 11:07:05', 1, '2026-04-01 05:37:05'),
(12, 4, '2026-04-01 11:07:11', 1, '2026-04-01 05:37:11'),
(13, 2, '2026-04-01 11:07:47', 1, '2026-04-01 05:37:47'),
(14, 22, '2026-04-10 16:27:57', 1, '2026-04-10 10:57:57'),
(15, 2, '2026-04-10 16:33:34', 1, '2026-04-10 11:03:34');

-- --------------------------------------------------------

--
-- Table structure for table `vehicle_job_card_custom_points`
--

CREATE TABLE `vehicle_job_card_custom_points` (
  `id` int(11) NOT NULL,
  `job_card_id` int(11) NOT NULL,
  `point_number` int(11) NOT NULL,
  `note` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `vehicle_job_card_custom_points`
--

INSERT INTO `vehicle_job_card_custom_points` (`id`, `job_card_id`, `point_number`, `note`, `created_at`) VALUES
(1, 15, 38, 'custom point test', '2026-04-10 16:33:34');

-- --------------------------------------------------------

--
-- Table structure for table `vehicle_job_card_items`
--

CREATE TABLE `vehicle_job_card_items` (
  `id` int(11) NOT NULL,
  `job_card_id` int(11) NOT NULL,
  `item_number` int(11) NOT NULL COMMENT 'Serial number 1-37',
  `item_name` varchar(100) NOT NULL,
  `check_value` varchar(100) DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vehicle_job_card_items`
--

INSERT INTO `vehicle_job_card_items` (`id`, `job_card_id`, `item_number`, `item_name`, `check_value`, `note`) VALUES
(1, 1, 1, 'Car Number', '1', 'verified'),
(2, 1, 2, 'Kilometer', '0', NULL),
(3, 1, 3, 'Scratches', '0', NULL),
(4, 1, 4, 'Service Kilometer Checkin', '0', NULL),
(5, 1, 5, 'Alignment Kilometer Checkin', '0', NULL),
(6, 1, 6, 'Tyre Condition', '0', NULL),
(7, 1, 7, 'Tyre Pressure', '0', NULL),
(8, 1, 8, 'Engine Oil', '0', NULL),
(9, 1, 9, 'Air Filter', '0', NULL),
(10, 1, 10, 'Coolant', '0', NULL),
(11, 1, 11, 'Brake Fluid', '0', NULL),
(12, 1, 12, 'Fuel Filter', '0', NULL),
(13, 1, 13, 'Washer Fluid', '0', NULL),
(14, 1, 14, 'Electric Checking', '0', NULL),
(15, 1, 15, 'Brake Pads', '0', NULL),
(16, 1, 16, 'Hand Brake', '0', NULL),
(17, 1, 17, 'Head Lights', '0', NULL),
(18, 1, 18, 'Indicators', '0', NULL),
(19, 1, 19, 'Seat Belts', '0', NULL),
(20, 1, 20, 'Wipers', '0', NULL),
(21, 1, 21, 'Battery Terminal', '0', NULL),
(22, 1, 22, 'Battery Water', '0', NULL),
(23, 1, 23, 'AC', '0', NULL),
(24, 1, 24, 'AC filter', '0', NULL),
(25, 1, 25, 'Music System', '0', NULL),
(26, 1, 26, 'Lights', '0', NULL),
(27, 1, 27, 'Stepni Tyre', '0', NULL),
(28, 1, 28, 'Jacky', '0', NULL),
(29, 1, 29, 'Interior Cleaning', '0', NULL),
(30, 1, 30, 'Washing', '0', NULL),
(31, 1, 31, 'Car Small Checking', '0', NULL),
(32, 1, 32, 'Seat Condition and Cleaning', '0', NULL),
(33, 1, 33, 'Tyre Polishing', '0', NULL),
(34, 1, 34, 'Papers Checking', '0', NULL),
(35, 1, 35, 'Fine Checking', '0', NULL),
(36, 1, 36, 'Complaints', '0', NULL),
(37, 1, 37, 'Final Check Up and Note', '0', NULL),
(38, 2, 1, 'Car Number', '1', 'verified'),
(39, 2, 2, 'Kilometer', '0', NULL),
(40, 2, 3, 'Scratches', '0', NULL),
(41, 2, 4, 'Service Kilometer Checkin', '0', NULL),
(42, 2, 5, 'Alignment Kilometer Checkin', '0', NULL),
(43, 2, 6, 'Tyre Condition', '0', NULL),
(44, 2, 7, 'Tyre Pressure', '0', NULL),
(45, 2, 8, 'Engine Oil', '0', NULL),
(46, 2, 9, 'Air Filter', '0', NULL),
(47, 2, 10, 'Coolant', '0', NULL),
(48, 2, 11, 'Brake Fluid', '0', NULL),
(49, 2, 12, 'Fuel Filter', '0', NULL),
(50, 2, 13, 'Washer Fluid', '0', NULL),
(51, 2, 14, 'Electric Checking', '0', NULL),
(52, 2, 15, 'Brake Pads', '0', NULL),
(53, 2, 16, 'Hand Brake', '0', NULL),
(54, 2, 17, 'Head Lights', '0', NULL),
(55, 2, 18, 'Indicators', '0', NULL),
(56, 2, 19, 'Seat Belts', '0', NULL),
(57, 2, 20, 'Wipers', '0', NULL),
(58, 2, 21, 'Battery Terminal', '0', NULL),
(59, 2, 22, 'Battery Water', '0', NULL),
(60, 2, 23, 'AC', '0', NULL),
(61, 2, 24, 'AC filter', '0', NULL),
(62, 2, 25, 'Music System', '0', NULL),
(63, 2, 26, 'Lights', '0', NULL),
(64, 2, 27, 'Stepni Tyre', '0', NULL),
(65, 2, 28, 'Jacky', '0', NULL),
(66, 2, 29, 'Interior Cleaning', '0', NULL),
(67, 2, 30, 'Washing', '0', NULL),
(68, 2, 31, 'Car Small Checking', '0', NULL),
(69, 2, 32, 'Seat Condition and Cleaning', '0', NULL),
(70, 2, 33, 'Tyre Polishing', '0', NULL),
(71, 2, 34, 'Papers Checking', '0', NULL),
(72, 2, 35, 'Fine Checking', '0', NULL),
(73, 2, 36, 'Complaints', '0', NULL),
(74, 2, 37, 'Final Check Up and Note', '0', NULL),
(75, 7, 1, 'Car Number', NULL, 'verified'),
(76, 7, 2, 'Kilometer', NULL, NULL),
(77, 7, 3, 'Scratches', NULL, NULL),
(78, 7, 4, 'Service Kilometer Checkin', NULL, NULL),
(79, 7, 5, 'Alignment Kilometer Checkin', NULL, NULL),
(80, 7, 6, 'Tyre Condition', NULL, NULL),
(81, 7, 7, 'Tyre Pressure', NULL, NULL),
(82, 7, 8, 'Engine Oil', NULL, NULL),
(83, 7, 9, 'Air Filter', NULL, NULL),
(84, 7, 10, 'Coolant', NULL, NULL),
(85, 7, 11, 'Brake Fluid', NULL, NULL),
(86, 7, 12, 'Fuel Filter', NULL, NULL),
(87, 7, 13, 'Washer Fluid', NULL, NULL),
(88, 7, 14, 'Electric Checking', NULL, NULL),
(89, 7, 15, 'Brake Pads', NULL, NULL),
(90, 7, 16, 'Hand Brake', NULL, NULL),
(91, 7, 17, 'Head Lights', '0', NULL),
(92, 7, 18, 'Indicators', '0', NULL),
(93, 7, 19, 'Seat Belts', '0', NULL),
(94, 7, 20, 'Wipers', '0', NULL),
(95, 7, 21, 'Battery Terminal', '0', NULL),
(96, 7, 22, 'Battery Water', '0', NULL),
(97, 7, 23, 'AC', '0', NULL),
(98, 7, 24, 'AC filter', '0', NULL),
(99, 7, 25, 'Music System', '0', NULL),
(100, 7, 26, 'Lights', '0', NULL),
(101, 7, 27, 'Stepni Tyre', '0', NULL),
(102, 7, 28, 'Jacky', '0', NULL),
(103, 7, 29, 'Interior Cleaning', '0', NULL),
(104, 7, 30, 'Washing', '0', NULL),
(105, 7, 31, 'Car Small Checking', '0', NULL),
(106, 7, 32, 'Seat Condition and Cleaning', '0', NULL),
(107, 7, 33, 'Tyre Polishing', '0', NULL),
(108, 7, 34, 'Papers Checking', '0', NULL),
(109, 7, 35, 'Fine Checking', '0', NULL),
(110, 7, 36, 'Complaints', '0', NULL),
(111, 7, 37, 'Final Check Up and Note', '0', NULL),
(112, 8, 1, 'Car Number', 'erewr', 'verified'),
(113, 8, 2, 'Kilometer', NULL, NULL),
(114, 8, 3, 'Scratches', NULL, NULL),
(115, 8, 4, 'Service Kilometer Checkin', NULL, NULL),
(116, 8, 5, 'Alignment Kilometer Checkin', NULL, NULL),
(117, 8, 6, 'Tyre Condition', NULL, NULL),
(118, 8, 7, 'Tyre Pressure', NULL, NULL),
(119, 8, 8, 'Engine Oil', NULL, NULL),
(120, 8, 9, 'Air Filter', NULL, NULL),
(121, 8, 10, 'Coolant', NULL, NULL),
(122, 8, 11, 'Brake Fluid', NULL, NULL),
(123, 8, 12, 'Fuel Filter', NULL, NULL),
(124, 8, 13, 'Washer Fluid', NULL, NULL),
(125, 8, 14, 'Electric Checking', NULL, NULL),
(126, 8, 15, 'Brake Pads', NULL, NULL),
(127, 8, 16, 'Hand Brake', NULL, NULL),
(128, 8, 17, 'Head Lights', NULL, NULL),
(129, 8, 18, 'Indicators', NULL, NULL),
(130, 8, 19, 'Seat Belts', NULL, NULL),
(131, 8, 20, 'Wipers', NULL, NULL),
(132, 8, 21, 'Battery Terminal', NULL, NULL),
(133, 8, 22, 'Battery Water', NULL, NULL),
(134, 8, 23, 'AC', NULL, NULL),
(135, 8, 24, 'AC filter', NULL, NULL),
(136, 8, 25, 'Music System', NULL, NULL),
(137, 8, 26, 'Lights', NULL, NULL),
(138, 8, 27, 'Stepni Tyre', NULL, NULL),
(139, 8, 28, 'Jacky', NULL, NULL),
(140, 8, 29, 'Interior Cleaning', NULL, NULL),
(141, 8, 30, 'Washing', NULL, NULL),
(142, 8, 31, 'Car Small Checking', NULL, NULL),
(143, 8, 32, 'Seat Condition and Cleaning', NULL, NULL),
(144, 8, 33, 'Tyre Polishing', NULL, NULL),
(145, 8, 34, 'Papers Checking', NULL, NULL),
(146, 8, 35, 'Fine Checking', NULL, NULL),
(147, 8, 36, 'Complaints', NULL, NULL),
(148, 8, 37, 'Final Check Up and Note', NULL, NULL),
(149, 9, 1, 'Car Number', 'erewr', 'verified'),
(150, 9, 2, 'Kilometer', 'sd', 'sd'),
(151, 9, 3, 'Scratches', NULL, NULL),
(152, 9, 4, 'Service Kilometer Checkin', NULL, NULL),
(153, 9, 5, 'Alignment Kilometer Checkin', NULL, NULL),
(154, 9, 6, 'Tyre Condition', NULL, NULL),
(155, 9, 7, 'Tyre Pressure', NULL, NULL),
(156, 9, 8, 'Engine Oil', NULL, NULL),
(157, 9, 9, 'Air Filter', NULL, NULL),
(158, 9, 10, 'Coolant', NULL, NULL),
(159, 9, 11, 'Brake Fluid', NULL, NULL),
(160, 9, 12, 'Fuel Filter', NULL, NULL),
(161, 9, 13, 'Washer Fluid', NULL, NULL),
(162, 9, 14, 'Electric Checking', NULL, NULL),
(163, 9, 15, 'Brake Pads', NULL, NULL),
(164, 9, 16, 'Hand Brake', NULL, NULL),
(165, 9, 17, 'Head Lights', NULL, NULL),
(166, 9, 18, 'Indicators', NULL, NULL),
(167, 9, 19, 'Seat Belts', NULL, NULL),
(168, 9, 20, 'Wipers', NULL, NULL),
(169, 9, 21, 'Battery Terminal', NULL, NULL),
(170, 9, 22, 'Battery Water', NULL, NULL),
(171, 9, 23, 'AC', NULL, NULL),
(172, 9, 24, 'AC filter', NULL, NULL),
(173, 9, 25, 'Music System', NULL, NULL),
(174, 9, 26, 'Lights', NULL, NULL),
(175, 9, 27, 'Stepni Tyre', NULL, NULL),
(176, 9, 28, 'Jacky', NULL, NULL),
(177, 9, 29, 'Interior Cleaning', NULL, NULL),
(178, 9, 30, 'Washing', NULL, NULL),
(179, 9, 31, 'Car Small Checking', NULL, NULL),
(180, 9, 32, 'Seat Condition and Cleaning', NULL, NULL),
(181, 9, 33, 'Tyre Polishing', NULL, NULL),
(182, 9, 34, 'Papers Checking', NULL, NULL),
(183, 9, 35, 'Fine Checking', NULL, NULL),
(184, 9, 36, 'Complaints', NULL, NULL),
(185, 9, 37, 'Final Check Up and Note', NULL, NULL),
(186, 10, 1, 'Car Number', '1234', 'verified'),
(187, 10, 2, 'Kilometer', NULL, NULL),
(188, 10, 3, 'Scratches', NULL, NULL),
(189, 10, 4, 'Service Kilometer Checkin', NULL, NULL),
(190, 10, 5, 'Alignment Kilometer Checkin', NULL, NULL),
(191, 10, 6, 'Tyre Condition', NULL, NULL),
(192, 10, 7, 'Tyre Pressure', NULL, NULL),
(193, 10, 8, 'Engine Oil', NULL, NULL),
(194, 10, 9, 'Air Filter', NULL, NULL),
(195, 10, 10, 'Coolant', NULL, NULL),
(196, 10, 11, 'Brake Fluid', NULL, NULL),
(197, 10, 12, 'Fuel Filter', NULL, NULL),
(198, 10, 13, 'Washer Fluid', NULL, NULL),
(199, 10, 14, 'Electric Checking', NULL, NULL),
(200, 10, 15, 'Brake Pads', NULL, NULL),
(201, 10, 16, 'Hand Brake', NULL, NULL),
(202, 10, 17, 'Head Lights', '0', NULL),
(203, 10, 18, 'Indicators', '0', NULL),
(204, 10, 19, 'Seat Belts', '0', NULL),
(205, 10, 20, 'Wipers', '0', NULL),
(206, 10, 21, 'Battery Terminal', '0', NULL),
(207, 10, 22, 'Battery Water', '0', NULL),
(208, 10, 23, 'AC', '0', NULL),
(209, 10, 24, 'AC filter', '0', NULL),
(210, 10, 25, 'Music System', '0', NULL),
(211, 10, 26, 'Lights', '0', NULL),
(212, 10, 27, 'Stepni Tyre', '0', NULL),
(213, 10, 28, 'Jacky', '0', NULL),
(214, 10, 29, 'Interior Cleaning', '0', NULL),
(215, 10, 30, 'Washing', '0', NULL),
(216, 10, 31, 'Car Small Checking', '0', NULL),
(217, 10, 32, 'Seat Condition and Cleaning', '0', NULL),
(218, 10, 33, 'Tyre Polishing', '0', NULL),
(219, 10, 34, 'Papers Checking', '0', NULL),
(220, 10, 35, 'Fine Checking', '0', NULL),
(221, 10, 36, 'Complaints', '0', NULL),
(222, 10, 37, 'Final Check Up and Note', '0', NULL),
(223, 11, 1, 'Car Number', '1234', 'verified'),
(224, 11, 2, 'Kilometer', '12', 'ok'),
(225, 11, 3, 'Scratches', NULL, NULL),
(226, 11, 4, 'Service Kilometer Checkin', NULL, NULL),
(227, 11, 5, 'Alignment Kilometer Checkin', NULL, NULL),
(228, 11, 6, 'Tyre Condition', NULL, NULL),
(229, 11, 7, 'Tyre Pressure', NULL, NULL),
(230, 11, 8, 'Engine Oil', NULL, NULL),
(231, 11, 9, 'Air Filter', NULL, NULL),
(232, 11, 10, 'Coolant', NULL, NULL),
(233, 11, 11, 'Brake Fluid', NULL, NULL),
(234, 11, 12, 'Fuel Filter', NULL, NULL),
(235, 11, 13, 'Washer Fluid', NULL, NULL),
(236, 11, 14, 'Electric Checking', NULL, NULL),
(237, 11, 15, 'Brake Pads', NULL, NULL),
(238, 11, 16, 'Hand Brake', NULL, NULL),
(239, 11, 17, 'Head Lights', '0', NULL),
(240, 11, 18, 'Indicators', '0', NULL),
(241, 11, 19, 'Seat Belts', '0', NULL),
(242, 11, 20, 'Wipers', '0', NULL),
(243, 11, 21, 'Battery Terminal', '0', NULL),
(244, 11, 22, 'Battery Water', '0', NULL),
(245, 11, 23, 'AC', '0', NULL),
(246, 11, 24, 'AC filter', '0', NULL),
(247, 11, 25, 'Music System', '0', NULL),
(248, 11, 26, 'Lights', '0', NULL),
(249, 11, 27, 'Stepni Tyre', '0', NULL),
(250, 11, 28, 'Jacky', '0', NULL),
(251, 11, 29, 'Interior Cleaning', '0', NULL),
(252, 11, 30, 'Washing', '0', NULL),
(253, 11, 31, 'Car Small Checking', '0', NULL),
(254, 11, 32, 'Seat Condition and Cleaning', '0', NULL),
(255, 11, 33, 'Tyre Polishing', '0', NULL),
(256, 11, 34, 'Papers Checking', '0', NULL),
(257, 11, 35, 'Fine Checking', '0', NULL),
(258, 11, 36, 'Complaints', '0', NULL),
(259, 11, 37, 'Final Check Up and Note', '0', NULL),
(260, 12, 1, 'Car Number', 'erewr', 'verified'),
(261, 12, 2, 'Kilometer', 'sd', 'sd'),
(262, 12, 3, 'Scratches', 'sdsd', NULL),
(263, 12, 4, 'Service Kilometer Checkin', NULL, NULL),
(264, 12, 5, 'Alignment Kilometer Checkin', NULL, NULL),
(265, 12, 6, 'Tyre Condition', NULL, NULL),
(266, 12, 7, 'Tyre Pressure', NULL, NULL),
(267, 12, 8, 'Engine Oil', NULL, NULL),
(268, 12, 9, 'Air Filter', NULL, NULL),
(269, 12, 10, 'Coolant', NULL, NULL),
(270, 12, 11, 'Brake Fluid', NULL, NULL),
(271, 12, 12, 'Fuel Filter', NULL, NULL),
(272, 12, 13, 'Washer Fluid', NULL, NULL),
(273, 12, 14, 'Electric Checking', NULL, NULL),
(274, 12, 15, 'Brake Pads', NULL, NULL),
(275, 12, 16, 'Hand Brake', NULL, NULL),
(276, 12, 17, 'Head Lights', NULL, NULL),
(277, 12, 18, 'Indicators', NULL, NULL),
(278, 12, 19, 'Seat Belts', NULL, NULL),
(279, 12, 20, 'Wipers', NULL, NULL),
(280, 12, 21, 'Battery Terminal', NULL, NULL),
(281, 12, 22, 'Battery Water', NULL, NULL),
(282, 12, 23, 'AC', NULL, NULL),
(283, 12, 24, 'AC filter', NULL, NULL),
(284, 12, 25, 'Music System', NULL, NULL),
(285, 12, 26, 'Lights', NULL, NULL),
(286, 12, 27, 'Stepni Tyre', NULL, NULL),
(287, 12, 28, 'Jacky', NULL, NULL),
(288, 12, 29, 'Interior Cleaning', NULL, NULL),
(289, 12, 30, 'Washing', NULL, NULL),
(290, 12, 31, 'Car Small Checking', NULL, NULL),
(291, 12, 32, 'Seat Condition and Cleaning', NULL, NULL),
(292, 12, 33, 'Tyre Polishing', NULL, NULL),
(293, 12, 34, 'Papers Checking', NULL, NULL),
(294, 12, 35, 'Fine Checking', NULL, NULL),
(295, 12, 36, 'Complaints', NULL, NULL),
(296, 12, 37, 'Final Check Up and Note', NULL, NULL),
(297, 13, 1, 'Car Number', '1234', 'verified'),
(298, 13, 2, 'Kilometer', '12', 'ok'),
(299, 13, 3, 'Scratches', '2', 'bumper'),
(300, 13, 4, 'Service Kilometer Checkin', NULL, NULL),
(301, 13, 5, 'Alignment Kilometer Checkin', NULL, NULL),
(302, 13, 6, 'Tyre Condition', NULL, NULL),
(303, 13, 7, 'Tyre Pressure', NULL, NULL),
(304, 13, 8, 'Engine Oil', NULL, NULL),
(305, 13, 9, 'Air Filter', NULL, NULL),
(306, 13, 10, 'Coolant', NULL, NULL),
(307, 13, 11, 'Brake Fluid', NULL, NULL),
(308, 13, 12, 'Fuel Filter', NULL, NULL),
(309, 13, 13, 'Washer Fluid', NULL, NULL),
(310, 13, 14, 'Electric Checking', NULL, NULL),
(311, 13, 15, 'Brake Pads', NULL, NULL),
(312, 13, 16, 'Hand Brake', NULL, NULL),
(313, 13, 17, 'Head Lights', '0', NULL),
(314, 13, 18, 'Indicators', '0', NULL),
(315, 13, 19, 'Seat Belts', '0', NULL),
(316, 13, 20, 'Wipers', '0', NULL),
(317, 13, 21, 'Battery Terminal', '0', NULL),
(318, 13, 22, 'Battery Water', '0', NULL),
(319, 13, 23, 'AC', '0', NULL),
(320, 13, 24, 'AC filter', '0', NULL),
(321, 13, 25, 'Music System', '0', NULL),
(322, 13, 26, 'Lights', '0', NULL),
(323, 13, 27, 'Stepni Tyre', '0', NULL),
(324, 13, 28, 'Jacky', '0', NULL),
(325, 13, 29, 'Interior Cleaning', '0', NULL),
(326, 13, 30, 'Washing', '0', NULL),
(327, 13, 31, 'Car Small Checking', '0', NULL),
(328, 13, 32, 'Seat Condition and Cleaning', '0', NULL),
(329, 13, 33, 'Tyre Polishing', '0', NULL),
(330, 13, 34, 'Papers Checking', '0', NULL),
(331, 13, 35, 'Fine Checking', '0', NULL),
(332, 13, 36, 'Complaints', '0', NULL),
(333, 13, 37, 'Final Check Up and Note', '0', NULL),
(334, 14, 1, 'Car Number', '2323', 'wddws'),
(335, 14, 2, 'Kilometer', NULL, NULL),
(336, 14, 3, 'Scratches', NULL, NULL),
(337, 14, 4, 'Service Kilometer Checkin', NULL, NULL),
(338, 14, 5, 'Alignment Kilometer Checkin', NULL, NULL),
(339, 14, 6, 'Tyre Condition', NULL, NULL),
(340, 14, 7, 'Tyre Pressure', NULL, NULL),
(341, 14, 8, 'Engine Oil', NULL, NULL),
(342, 14, 9, 'Air Filter', NULL, NULL),
(343, 14, 10, 'Coolant', NULL, NULL),
(344, 14, 11, 'Brake Fluid', NULL, NULL),
(345, 14, 12, 'Fuel Filter', NULL, NULL),
(346, 14, 13, 'Washer Fluid', NULL, NULL),
(347, 14, 14, 'Electric Checking', NULL, NULL),
(348, 14, 15, 'Brake Pads', NULL, NULL),
(349, 14, 16, 'Hand Brake', NULL, NULL),
(350, 14, 17, 'Head Lights', NULL, NULL),
(351, 14, 18, 'Indicators', NULL, NULL),
(352, 14, 19, 'Seat Belts', NULL, NULL),
(353, 14, 20, 'Wipers', NULL, NULL),
(354, 14, 21, 'Battery Terminal', NULL, NULL),
(355, 14, 22, 'Battery Water', NULL, NULL),
(356, 14, 23, 'AC', NULL, NULL),
(357, 14, 24, 'AC filter', NULL, NULL),
(358, 14, 25, 'Music System', NULL, NULL),
(359, 14, 26, 'Lights', NULL, NULL),
(360, 14, 27, 'Stepni Tyre', NULL, NULL),
(361, 14, 28, 'Jacky', NULL, NULL),
(362, 14, 29, 'Interior Cleaning', NULL, NULL),
(363, 14, 30, 'Washing', NULL, NULL),
(364, 14, 31, 'Car Small Checking', NULL, NULL),
(365, 14, 32, 'Seat Condition and Cleaning', NULL, NULL),
(366, 14, 33, 'Tyre Polishing', NULL, NULL),
(367, 14, 34, 'Papers Checking', NULL, NULL),
(368, 14, 35, 'Fine Checking', NULL, NULL),
(369, 14, 36, 'Complaints', NULL, NULL),
(370, 14, 37, 'Final Check Up and Note', NULL, NULL),
(371, 15, 1, 'Car Number', '1234', 'verified'),
(372, 15, 2, 'Kilometer', '12', 'ok'),
(373, 15, 3, 'Scratches', '2', 'bumper'),
(374, 15, 4, 'Service Kilometer Checkin', NULL, NULL),
(375, 15, 5, 'Alignment Kilometer Checkin', NULL, NULL),
(376, 15, 6, 'Tyre Condition', NULL, NULL),
(377, 15, 7, 'Tyre Pressure', NULL, NULL),
(378, 15, 8, 'Engine Oil', NULL, NULL),
(379, 15, 9, 'Air Filter', NULL, NULL),
(380, 15, 10, 'Coolant', NULL, NULL),
(381, 15, 11, 'Brake Fluid', NULL, NULL),
(382, 15, 12, 'Fuel Filter', NULL, NULL),
(383, 15, 13, 'Washer Fluid', NULL, NULL),
(384, 15, 14, 'Electric Checking', NULL, NULL),
(385, 15, 15, 'Brake Pads', NULL, NULL),
(386, 15, 16, 'Hand Brake', NULL, NULL),
(387, 15, 17, 'Head Lights', '0', NULL),
(388, 15, 18, 'Indicators', '0', NULL),
(389, 15, 19, 'Seat Belts', '0', NULL),
(390, 15, 20, 'Wipers', '0', NULL),
(391, 15, 21, 'Battery Terminal', '0', NULL),
(392, 15, 22, 'Battery Water', '0', NULL),
(393, 15, 23, 'AC', '0', NULL),
(394, 15, 24, 'AC filter', '0', NULL),
(395, 15, 25, 'Music System', '0', NULL),
(396, 15, 26, 'Lights', '0', NULL),
(397, 15, 27, 'Stepni Tyre', '0', NULL),
(398, 15, 28, 'Jacky', '0', NULL),
(399, 15, 29, 'Interior Cleaning', '0', NULL),
(400, 15, 30, 'Washing', '0', NULL),
(401, 15, 31, 'Car Small Checking', '0', NULL),
(402, 15, 32, 'Seat Condition and Cleaning', '0', NULL),
(403, 15, 33, 'Tyre Polishing', '0', NULL),
(404, 15, 34, 'Papers Checking', '0', NULL),
(405, 15, 35, 'Fine Checking', '0', NULL),
(406, 15, 36, 'Complaints', '0', NULL),
(407, 15, 37, 'Final Check Up and Note', '0', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `vehicle_monthly_targets`
--

CREATE TABLE `vehicle_monthly_targets` (
  `id` int(11) NOT NULL,
  `vehicle_id` int(11) NOT NULL,
  `period_start` date NOT NULL COMMENT '15th of the month',
  `period_end` date NOT NULL COMMENT '14th of next month',
  `target_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Monthly income targets per vehicle';

--
-- Dumping data for table `vehicle_monthly_targets`
--

INSERT INTO `vehicle_monthly_targets` (`id`, `vehicle_id`, `period_start`, `period_end`, `target_amount`, `notes`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 4, '2026-03-15', '2026-04-14', 600000.00, '', 1, '2026-03-27 19:39:24', '2026-03-27 19:50:37'),
(2, 2, '2026-03-15', '2026-04-14', 500000.00, NULL, 1, '2026-03-27 19:39:24', '2026-03-27 19:50:08'),
(3, 11, '2026-03-15', '2026-04-14', 500000.00, NULL, 1, '2026-03-27 19:39:24', '2026-03-27 19:50:08'),
(4, 1, '2026-03-15', '2026-04-14', 500000.00, NULL, 1, '2026-03-27 19:39:24', '2026-03-27 19:50:08'),
(5, 8, '2026-03-15', '2026-04-14', 500000.00, NULL, 1, '2026-03-27 19:39:24', '2026-03-27 19:50:08'),
(6, 6, '2026-03-15', '2026-04-14', 500000.00, NULL, 1, '2026-03-27 19:39:24', '2026-03-27 19:50:08'),
(7, 7, '2026-03-15', '2026-04-14', 500000.00, NULL, 1, '2026-03-27 19:39:24', '2026-03-27 19:50:08'),
(8, 5, '2026-03-15', '2026-04-14', 500000.00, NULL, 1, '2026-03-27 19:39:24', '2026-03-27 19:50:08'),
(9, 3, '2026-03-15', '2026-04-14', 500000.00, NULL, 1, '2026-03-27 19:39:24', '2026-03-27 19:50:08'),
(10, 9, '2026-03-15', '2026-04-14', 500000.00, NULL, 1, '2026-03-27 19:39:24', '2026-03-27 19:50:08');

-- --------------------------------------------------------

--
-- Table structure for table `vehicle_permanent_scratches`
--

CREATE TABLE `vehicle_permanent_scratches` (
  `id` int(11) NOT NULL,
  `vehicle_id` int(11) NOT NULL,
  `description` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `vehicle_permanent_scratches`
--

INSERT INTO `vehicle_permanent_scratches` (`id`, `vehicle_id`, `description`, `file_path`, `created_at`, `created_by`) VALUES
(3, 2, 'test', 'uploads/permanent_scratches/permanent_2_1_1775648308.png', '2026-04-08 17:08:28', 1);

-- --------------------------------------------------------

--
-- Table structure for table `vehicle_requests`
--

CREATE TABLE `vehicle_requests` (
  `id` int(11) NOT NULL,
  `client_id` int(11) DEFAULT NULL,
  `client_name_free` varchar(120) DEFAULT NULL,
  `vehicle_brand` varchar(80) NOT NULL,
  `vehicle_model` varchar(80) NOT NULL,
  `people_count` int(11) NOT NULL DEFAULT 1,
  `notes` text DEFAULT NULL,
  `status` enum('pending','contacted','acquired','cancelled') NOT NULL DEFAULT 'pending',
  `requested_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attendance_breaks`
--
ALTER TABLE `attendance_breaks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `attendance_id` (`attendance_id`);

--
-- Indexes for table `bank_accounts`
--
ALTER TABLE `bank_accounts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `challans`
--
ALTER TABLE `challans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vehicle_id` (`vehicle_id`),
  ADD KEY `client_id` (`client_id`);

--
-- Indexes for table `clients`
--
ALTER TABLE `clients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `client_proofs`
--
ALTER TABLE `client_proofs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_client_proofs_client` (`client_id`);

--
-- Indexes for table `client_reviews`
--
ALTER TABLE `client_reviews`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_reservation_review` (`reservation_id`),
  ADD KEY `client_id` (`client_id`);

--
-- Indexes for table `client_voucher_transactions`
--
ALTER TABLE `client_voucher_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_client_created` (`client_id`,`created_at`),
  ADD KEY `reservation_id` (`reservation_id`);

--
-- Indexes for table `credit_payment_allocations`
--
ALTER TABLE `credit_payment_allocations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_income_entry` (`credit_income_entry_id`),
  ADD KEY `idx_payment_entry` (`credit_payment_entry_id`);

--
-- Indexes for table `damage_costs`
--
ALTER TABLE `damage_costs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vehicle_id` (`vehicle_id`);

--
-- Indexes for table `emi_investments`
--
ALTER TABLE `emi_investments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `emi_schedules`
--
ALTER TABLE `emi_schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `investment_id` (`investment_id`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `gps_daily_checks`
--
ALTER TABLE `gps_daily_checks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_gps_daily_slot` (`reservation_id`,`check_date`,`check_slot`),
  ADD KEY `idx_gps_daily_vehicle` (`vehicle_id`),
  ADD KEY `idx_gps_daily_date` (`check_date`),
  ADD KEY `idx_gps_daily_reservation` (`reservation_id`);

--
-- Indexes for table `gps_tracking`
--
ALTER TABLE `gps_tracking`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_gps_reservation_id` (`reservation_id`),
  ADD KEY `idx_gps_vehicle_id` (`vehicle_id`),
  ADD KEY `idx_gps_tracking_active` (`tracking_active`);

--
-- Indexes for table `hope_daily_predictions`
--
ALTER TABLE `hope_daily_predictions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_target_date` (`target_date`);

--
-- Indexes for table `hope_daily_targets`
--
ALTER TABLE `hope_daily_targets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_target_date` (`target_date`),
  ADD KEY `idx_target_date` (`target_date`);

--
-- Indexes for table `inspection_photos`
--
ALTER TABLE `inspection_photos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `inspection_id` (`inspection_id`);

--
-- Indexes for table `leads`
--
ALTER TABLE `leads`
  ADD PRIMARY KEY (`id`),
  ADD KEY `assigned_staff_id` (`assigned_staff_id`);

--
-- Indexes for table `lead_activities`
--
ALTER TABLE `lead_activities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `lead_id` (`lead_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `lead_followups`
--
ALTER TABLE `lead_followups`
  ADD PRIMARY KEY (`id`),
  ADD KEY `lead_id` (`lead_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `ledger_entries`
--
ALTER TABLE `ledger_entries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_idempotency` (`idempotency_key`),
  ADD KEY `idx_txn_type` (`txn_type`),
  ADD KEY `idx_posted_at` (`posted_at`),
  ADD KEY `idx_bank_account` (`bank_account_id`),
  ADD KEY `idx_source` (`source_type`,`source_id`);

--
-- Indexes for table `monthly_targets`
--
ALTER TABLE `monthly_targets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `period_start` (`period_start`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `papers`
--
ALTER TABLE `papers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vehicle_id` (`vehicle_id`);

--
-- Indexes for table `payroll`
--
ALTER TABLE `payroll`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `paid_from` (`paid_from_account_id`);

--
-- Indexes for table `payroll_advances`
--
ALTER TABLE `payroll_advances`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pa_user_status` (`user_id`,`status`),
  ADD KEY `idx_pa_period` (`month`,`year`),
  ADD KEY `idx_pa_payroll` (`payroll_id`),
  ADD KEY `idx_pa_bank` (`bank_account_id`);

--
-- Indexes for table `remember_tokens`
--
ALTER TABLE `remember_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_selector` (`selector`),
  ADD KEY `idx_expires_at` (`expires_at`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indexes for table `reservations`
--
ALTER TABLE `reservations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `client_id` (`client_id`),
  ADD KEY `vehicle_id` (`vehicle_id`);

--
-- Indexes for table `reservation_extensions`
--
ALTER TABLE `reservation_extensions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_reservation` (`reservation_id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `fk_res_extension_bank` (`bank_account_id`);

--
-- Indexes for table `reservation_scratch_photos`
--
ALTER TABLE `reservation_scratch_photos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_rsp_reservation` (`reservation_id`);

--
-- Indexes for table `staff`
--
ALTER TABLE `staff`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `staff_activity_log`
--
ALTER TABLE `staff_activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_created` (`user_id`,`created_at`);

--
-- Indexes for table `staff_attendance`
--
ALTER TABLE `staff_attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_date` (`user_id`,`date`);

--
-- Indexes for table `staff_incentives`
--
ALTER TABLE `staff_incentives`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_month_year` (`user_id`,`month`,`year`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `staff_permissions`
--
ALTER TABLE `staff_permissions`
  ADD PRIMARY KEY (`user_id`,`permission`);

--
-- Indexes for table `staff_tasks`
--
ALTER TABLE `staff_tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `assigned_to` (`assigned_to`),
  ADD KEY `assigned_by` (`assigned_by`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `staff_id` (`staff_id`),
  ADD KEY `idx_is_online` (`is_online`);

--
-- Indexes for table `vehicles`
--
ALTER TABLE `vehicles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `license_plate` (`license_plate`);

--
-- Indexes for table `vehicle_challans`
--
ALTER TABLE `vehicle_challans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vehicle_id` (`vehicle_id`),
  ADD KEY `fk_vehicle_challans_reservation` (`reservation_id`);

--
-- Indexes for table `vehicle_images`
--
ALTER TABLE `vehicle_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vehicle_id` (`vehicle_id`);

--
-- Indexes for table `vehicle_inspections`
--
ALTER TABLE `vehicle_inspections`
  ADD PRIMARY KEY (`id`),
  ADD KEY `reservation_id` (`reservation_id`);

--
-- Indexes for table `vehicle_job_cards`
--
ALTER TABLE `vehicle_job_cards`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vehicle_id` (`vehicle_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `vehicle_job_card_custom_points`
--
ALTER TABLE `vehicle_job_card_custom_points`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_job_card_id` (`job_card_id`);

--
-- Indexes for table `vehicle_job_card_items`
--
ALTER TABLE `vehicle_job_card_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_job_card` (`job_card_id`);

--
-- Indexes for table `vehicle_monthly_targets`
--
ALTER TABLE `vehicle_monthly_targets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_vehicle_period` (`vehicle_id`,`period_start`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_period` (`period_start`,`period_end`),
  ADD KEY `idx_vehicle` (`vehicle_id`);

--
-- Indexes for table `vehicle_permanent_scratches`
--
ALTER TABLE `vehicle_permanent_scratches`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_vps_vehicle` (`vehicle_id`);

--
-- Indexes for table `vehicle_requests`
--
ALTER TABLE `vehicle_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `client_id` (`client_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attendance_breaks`
--
ALTER TABLE `attendance_breaks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `bank_accounts`
--
ALTER TABLE `bank_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `challans`
--
ALTER TABLE `challans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `clients`
--
ALTER TABLE `clients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=494;

--
-- AUTO_INCREMENT for table `client_proofs`
--
ALTER TABLE `client_proofs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `client_reviews`
--
ALTER TABLE `client_reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `client_voucher_transactions`
--
ALTER TABLE `client_voucher_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `credit_payment_allocations`
--
ALTER TABLE `credit_payment_allocations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `damage_costs`
--
ALTER TABLE `damage_costs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `documents`
--
ALTER TABLE `documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `emi_investments`
--
ALTER TABLE `emi_investments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `emi_schedules`
--
ALTER TABLE `emi_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `gps_daily_checks`
--
ALTER TABLE `gps_daily_checks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=178;

--
-- AUTO_INCREMENT for table `gps_tracking`
--
ALTER TABLE `gps_tracking`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=230;

--
-- AUTO_INCREMENT for table `hope_daily_predictions`
--
ALTER TABLE `hope_daily_predictions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `hope_daily_targets`
--
ALTER TABLE `hope_daily_targets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `inspection_photos`
--
ALTER TABLE `inspection_photos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=601;

--
-- AUTO_INCREMENT for table `leads`
--
ALTER TABLE `leads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `lead_activities`
--
ALTER TABLE `lead_activities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=88;

--
-- AUTO_INCREMENT for table `lead_followups`
--
ALTER TABLE `lead_followups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `ledger_entries`
--
ALTER TABLE `ledger_entries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=367;

--
-- AUTO_INCREMENT for table `monthly_targets`
--
ALTER TABLE `monthly_targets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=729;

--
-- AUTO_INCREMENT for table `papers`
--
ALTER TABLE `papers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payroll`
--
ALTER TABLE `payroll`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `payroll_advances`
--
ALTER TABLE `payroll_advances`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=115;

--
-- AUTO_INCREMENT for table `remember_tokens`
--
ALTER TABLE `remember_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=110;

--
-- AUTO_INCREMENT for table `reservations`
--
ALTER TABLE `reservations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=80;

--
-- AUTO_INCREMENT for table `reservation_extensions`
--
ALTER TABLE `reservation_extensions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `reservation_scratch_photos`
--
ALTER TABLE `reservation_scratch_photos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `staff`
--
ALTER TABLE `staff`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `staff_activity_log`
--
ALTER TABLE `staff_activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=214;

--
-- AUTO_INCREMENT for table `staff_attendance`
--
ALTER TABLE `staff_attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `staff_incentives`
--
ALTER TABLE `staff_incentives`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `staff_tasks`
--
ALTER TABLE `staff_tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10002;

--
-- AUTO_INCREMENT for table `vehicles`
--
ALTER TABLE `vehicles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `vehicle_challans`
--
ALTER TABLE `vehicle_challans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `vehicle_images`
--
ALTER TABLE `vehicle_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `vehicle_inspections`
--
ALTER TABLE `vehicle_inspections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=101;

--
-- AUTO_INCREMENT for table `vehicle_job_cards`
--
ALTER TABLE `vehicle_job_cards`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `vehicle_job_card_custom_points`
--
ALTER TABLE `vehicle_job_card_custom_points`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `vehicle_job_card_items`
--
ALTER TABLE `vehicle_job_card_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=408;

--
-- AUTO_INCREMENT for table `vehicle_monthly_targets`
--
ALTER TABLE `vehicle_monthly_targets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `vehicle_permanent_scratches`
--
ALTER TABLE `vehicle_permanent_scratches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `vehicle_requests`
--
ALTER TABLE `vehicle_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance_breaks`
--
ALTER TABLE `attendance_breaks`
  ADD CONSTRAINT `attendance_breaks_ibfk_1` FOREIGN KEY (`attendance_id`) REFERENCES `staff_attendance` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `challans`
--
ALTER TABLE `challans`
  ADD CONSTRAINT `challans_ibfk_1` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `challans_ibfk_2` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `client_proofs`
--
ALTER TABLE `client_proofs`
  ADD CONSTRAINT `fk_client_proofs_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `client_reviews`
--
ALTER TABLE `client_reviews`
  ADD CONSTRAINT `client_reviews_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `client_reviews_ibfk_2` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `client_voucher_transactions`
--
ALTER TABLE `client_voucher_transactions`
  ADD CONSTRAINT `client_voucher_transactions_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `client_voucher_transactions_ibfk_2` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `credit_payment_allocations`
--
ALTER TABLE `credit_payment_allocations`
  ADD CONSTRAINT `credit_payment_allocations_ibfk_1` FOREIGN KEY (`credit_income_entry_id`) REFERENCES `ledger_entries` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `credit_payment_allocations_ibfk_2` FOREIGN KEY (`credit_payment_entry_id`) REFERENCES `ledger_entries` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `documents`
--
ALTER TABLE `documents`
  ADD CONSTRAINT `documents_ibfk_1` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `emi_schedules`
--
ALTER TABLE `emi_schedules`
  ADD CONSTRAINT `emi_schedules_ibfk_1` FOREIGN KEY (`investment_id`) REFERENCES `emi_investments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `gps_tracking`
--
ALTER TABLE `gps_tracking`
  ADD CONSTRAINT `gps_tracking_ibfk_1` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `inspection_photos`
--
ALTER TABLE `inspection_photos`
  ADD CONSTRAINT `inspection_photos_ibfk_1` FOREIGN KEY (`inspection_id`) REFERENCES `vehicle_inspections` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `leads`
--
ALTER TABLE `leads`
  ADD CONSTRAINT `leads_ibfk_1` FOREIGN KEY (`assigned_staff_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `lead_activities`
--
ALTER TABLE `lead_activities`
  ADD CONSTRAINT `lead_activities_ibfk_1` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `lead_activities_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `lead_followups`
--
ALTER TABLE `lead_followups`
  ADD CONSTRAINT `lead_followups_ibfk_1` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `lead_followups_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `ledger_entries`
--
ALTER TABLE `ledger_entries`
  ADD CONSTRAINT `ledger_entries_ibfk_1` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `papers`
--
ALTER TABLE `papers`
  ADD CONSTRAINT `papers_ibfk_1` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payroll_advances`
--
ALTER TABLE `payroll_advances`
  ADD CONSTRAINT `fk_pa_bank` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_pa_payroll` FOREIGN KEY (`payroll_id`) REFERENCES `payroll` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_pa_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `remember_tokens`
--
ALTER TABLE `remember_tokens`
  ADD CONSTRAINT `remember_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reservations`
--
ALTER TABLE `reservations`
  ADD CONSTRAINT `reservations_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reservations_ibfk_2` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reservation_extensions`
--
ALTER TABLE `reservation_extensions`
  ADD CONSTRAINT `fk_res_extension_bank` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_res_extension_reservation` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reservation_scratch_photos`
--
ALTER TABLE `reservation_scratch_photos`
  ADD CONSTRAINT `fk_rsp_reservation` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `staff_attendance`
--
ALTER TABLE `staff_attendance`
  ADD CONSTRAINT `staff_attendance_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `staff_tasks`
--
ALTER TABLE `staff_tasks`
  ADD CONSTRAINT `staff_tasks_ibfk_1` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `staff_tasks_ibfk_2` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `vehicle_challans`
--
ALTER TABLE `vehicle_challans`
  ADD CONSTRAINT `fk_vehicle_challans_reservation` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `vehicle_challans_ibfk_1` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `vehicle_images`
--
ALTER TABLE `vehicle_images`
  ADD CONSTRAINT `vehicle_images_ibfk_1` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `vehicle_inspections`
--
ALTER TABLE `vehicle_inspections`
  ADD CONSTRAINT `vehicle_inspections_ibfk_1` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `vehicle_job_cards`
--
ALTER TABLE `vehicle_job_cards`
  ADD CONSTRAINT `vehicle_job_cards_ibfk_1` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `vehicle_job_cards_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `vehicle_job_card_custom_points`
--
ALTER TABLE `vehicle_job_card_custom_points`
  ADD CONSTRAINT `vehicle_job_card_custom_points_ibfk_1` FOREIGN KEY (`job_card_id`) REFERENCES `vehicle_job_cards` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `vehicle_job_card_items`
--
ALTER TABLE `vehicle_job_card_items`
  ADD CONSTRAINT `vehicle_job_card_items_ibfk_1` FOREIGN KEY (`job_card_id`) REFERENCES `vehicle_job_cards` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `vehicle_monthly_targets`
--
ALTER TABLE `vehicle_monthly_targets`
  ADD CONSTRAINT `vehicle_monthly_targets_ibfk_1` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `vehicle_monthly_targets_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `staff` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `vehicle_permanent_scratches`
--
ALTER TABLE `vehicle_permanent_scratches`
  ADD CONSTRAINT `fk_vps_vehicle` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `vehicle_requests`
--
ALTER TABLE `vehicle_requests`
  ADD CONSTRAINT `vehicle_requests_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
