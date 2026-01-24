-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 25, 2025 at 01:54 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.29

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `task_management`
--

-- --------------------------------------------------------

--
-- Table structure for table `leave_sheet_sync`
--

CREATE TABLE `leave_sheet_sync` (
  `id` int(11) NOT NULL,
  `sheet_id` varchar(255) NOT NULL,
  `rows_count` varchar(255) DEFAULT NULL,
  `actuals_count` varchar(255) DEFAULT NULL,
  `last_rows_count` varchar(255) DEFAULT NULL,
  `last_actuals_count` varchar(255) DEFAULT NULL,
  `last_synced` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leave_sheet_sync`
--

INSERT INTO `leave_sheet_sync` (`id`, `sheet_id`, `rows_count`, `actuals_count`, `last_rows_count`, `last_actuals_count`, `last_synced`) VALUES
(1, '1uLjlLs1Nd1eumtP3XjCWa0BEa4yPqev1ato5kGr5UY0', '1027', '160.00', '1024', '45,999,674.87', '2025-10-22 08:16:11');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `leave_sheet_sync`
--
ALTER TABLE `leave_sheet_sync`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sheet_id` (`sheet_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `leave_sheet_sync`
--
ALTER TABLE `leave_sheet_sync`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
