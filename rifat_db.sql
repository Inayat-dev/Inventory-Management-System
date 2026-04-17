-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 24, 2026 at 11:17 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `rifat_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `material_perchases`
--

CREATE TABLE `material_perchases` (
  `id` int(11) NOT NULL,
  `material_id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `purchase_date` date NOT NULL,
  `quantity` int(11) NOT NULL,
  `rate` int(11) NOT NULL,
  `total_amount` int(11) NOT NULL,
  `payment_status` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `material_perchases`
--

INSERT INTO `material_perchases` (`id`, `material_id`, `supplier_id`, `purchase_date`, `quantity`, `rate`, `total_amount`, `payment_status`, `created_at`) VALUES
(1, 1, 1, '2025-12-24', 100, 3, 300, 1, '2026-01-31 04:14:10');

-- --------------------------------------------------------

--
-- Table structure for table `production_entries`
--

CREATE TABLE `production_entries` (
  `id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `new_stock` int(11) NOT NULL,
  `Available` int(11) NOT NULL,
  `aggregate_used` int(11) NOT NULL,
  `sand_used` int(11) NOT NULL,
  `cement_used` int(11) NOT NULL,
  `red_sand_used` int(11) NOT NULL,
  `status` varchar(20) NOT NULL,
  `entry_date` date NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `production_entries`
--

INSERT INTO `production_entries` (`id`, `item_id`, `new_stock`, `Available`, `aggregate_used`, `sand_used`, `cement_used`, `red_sand_used`, `status`, `entry_date`) VALUES
(6, 2, 120, 340, 12, 23, 5, 0, 'Available stock', '2026-02-19'),
(7, 1, 1200, 1499, 0, 0, 0, 100, 'Available stock', '2026-02-19');

-- --------------------------------------------------------

--
-- Table structure for table `production_item`
--

CREATE TABLE `production_item` (
  `id` int(11) NOT NULL,
  `name` varchar(20) NOT NULL,
  `min_stock` int(11) NOT NULL,
  `current_stock` int(11) NOT NULL,
  `price_per_unit` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `production_item`
--

INSERT INTO `production_item` (`id`, `name`, `min_stock`, `current_stock`, `price_per_unit`) VALUES
(1, 'Burnt Clay Bricks', 250, 1499, 3),
(2, 'Cement Blocks', 120, 340, 12);

-- --------------------------------------------------------

--
-- Table structure for table `raw_material`
--

CREATE TABLE `raw_material` (
  `id` int(11) NOT NULL,
  `material_name` enum('cement','sand','Aggregate','red sand') NOT NULL,
  `unit` enum('bag','kg','ton','') NOT NULL,
  `current_stock` int(100) NOT NULL,
  `min_stock` int(100) NOT NULL,
  `created_at` date NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `raw_material`
--

INSERT INTO `raw_material` (`id`, `material_name`, `unit`, `current_stock`, `min_stock`, `created_at`) VALUES
(1, 'cement', 'bag', 215, 50, '2026-01-30'),
(2, 'sand', 'ton', 177, 20, '2026-01-30'),
(3, 'Aggregate', 'ton', 138, 40, '2026-01-30'),
(4, 'red sand', 'ton', 140, 50, '2026-01-30');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `role_name` varchar(30) NOT NULL,
  `roles` text NOT NULL,
  `permissions` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `role_name`, `roles`, `permissions`) VALUES
(1, 'super admin', 'admin', '[\"dashboard\", \"production_management\", \"raw_material_management\", \"sales_management\", \"suppliers\", \"reports\", \"settings\"]'),
(2, 'product manager', 'product', '[\"dashboard\", \"production_management\"]');

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `id` int(11) NOT NULL,
  `customer` varchar(100) NOT NULL,
  `product_id` int(11) NOT NULL,
  `qty` int(11) NOT NULL,
  `amount` int(11) NOT NULL,
  `status` enum('paid','pending') NOT NULL,
  `date` date NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sales`
--

INSERT INTO `sales` (`id`, `customer`, `product_id`, `qty`, `amount`, `status`, `date`) VALUES
(1, 'soyambhai', 1, 120, 600, 'paid', '2026-02-20'),
(2, 'jemilbhai', 2, 150, 3000, 'paid', '2026-02-20'),
(3, 'vipulbhai', 2, 10, 200, 'paid', '2026-02-20'),
(4, 'jatin', 2, 1, 5, 'pending', '2026-02-20'),
(5, 'jatin', 2, 100, 100000, 'pending', '2026-02-20');

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL,
  `name` varchar(30) NOT NULL,
  `phone` text NOT NULL,
  `address` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`id`, `name`, `phone`, `address`, `created_at`) VALUES
(1, 'jaydip controction', '8140401738', 'khodvadri', '2026-01-31 02:29:18'),
(4, 'ambuja cement', '6356857545', 'gariyadhar', '2026-01-31 03:08:18');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(30) NOT NULL,
  `email` text NOT NULL,
  `password` varchar(18) NOT NULL,
  `role_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `role_id`) VALUES
(1, 'altaf', 'altafnaya55@gmail.com', 'altaf@55', 1),
(2, 'inayat', 'inayat@gmail.com', 'inayat', 2);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `material_perchases`
--
ALTER TABLE `material_perchases`
  ADD PRIMARY KEY (`id`),
  ADD KEY `con` (`material_id`),
  ADD KEY `supplier_id` (`supplier_id`);

--
-- Indexes for table `production_entries`
--
ALTER TABLE `production_entries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_production_item` (`item_id`);

--
-- Indexes for table `production_item`
--
ALTER TABLE `production_item`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `raw_material`
--
ALTER TABLE `raw_material`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_sales_product` (`product_id`);

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
  ADD KEY `roll_conn` (`role_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `material_perchases`
--
ALTER TABLE `material_perchases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `production_entries`
--
ALTER TABLE `production_entries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `production_item`
--
ALTER TABLE `production_item`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `raw_material`
--
ALTER TABLE `raw_material`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `material_perchases`
--
ALTER TABLE `material_perchases`
  ADD CONSTRAINT `con` FOREIGN KEY (`material_id`) REFERENCES `raw_material` (`id`),
  ADD CONSTRAINT `material_perchases_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`);

--
-- Constraints for table `production_entries`
--
ALTER TABLE `production_entries`
  ADD CONSTRAINT `fk_production_item` FOREIGN KEY (`item_id`) REFERENCES `production_item` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `sales`
--
ALTER TABLE `sales`
  ADD CONSTRAINT `fk_sales_product` FOREIGN KEY (`product_id`) REFERENCES `production_item` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `roll_conn` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
