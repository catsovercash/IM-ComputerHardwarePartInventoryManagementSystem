-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 09, 2026 at 07:33 AM
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
-- Database: `computerpartsinventorydb`
--

-- --------------------------------------------------------

--
-- Table structure for table `category`
--

CREATE TABLE `category` (
  `CategoryID` int(11) NOT NULL,
  `CategoryName` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `category`
--

INSERT INTO `category` (`CategoryID`, `CategoryName`) VALUES
(1, 'Processor'),
(2, 'Graphics Card'),
(3, 'Motherboard'),
(4, 'Memory (RAM)'),
(5, 'Storage (SSD/HDD)'),
(6, 'Power Supply'),
(7, 'Computer Case');

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `InventoryID` int(11) NOT NULL,
  `PartID` int(11) DEFAULT NULL,
  `QuantityOnHand` int(11) NOT NULL,
  `ReservedQuantity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`InventoryID`, `PartID`, `QuantityOnHand`, `ReservedQuantity`) VALUES
(1, 1, 50, 5),
(2, 2, 40, 2),
(3, 3, 15, 2),
(4, 4, 25, 3),
(5, 5, 30, 0),
(6, 6, 100, 10),
(7, 7, 80, 5),
(8, 8, 67, 1);

-- --------------------------------------------------------

--
-- Table structure for table `manufacturer`
--

CREATE TABLE `manufacturer` (
  `ManufacturerID` int(11) NOT NULL,
  `ManufacturerName` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `manufacturer`
--

INSERT INTO `manufacturer` (`ManufacturerID`, `ManufacturerName`) VALUES
(1, 'Intel'),
(2, 'AMD'),
(3, 'NVIDIA'),
(4, 'ASUS'),
(5, 'MSI'),
(6, 'Corsair'),
(7, 'Samsung');

-- --------------------------------------------------------

--
-- Table structure for table `part`
--

CREATE TABLE `part` (
  `PartID` int(11) NOT NULL,
  `SKU` varchar(255) NOT NULL,
  `PartName` varchar(255) NOT NULL,
  `ModelNumber` varchar(255) DEFAULT NULL,
  `CategoryID` int(11) DEFAULT NULL,
  `ManufacturerID` int(11) DEFAULT NULL,
  `Description` varchar(255) DEFAULT NULL,
  `Price` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `part`
--

INSERT INTO `part` (`PartID`, `SKU`, `PartName`, `ModelNumber`, `CategoryID`, `ManufacturerID`, `Description`, `Price`) VALUES
(1, 'CPU-INT-001', 'Core i7-12700K', 'BX8071512700K', 1, 1, '12th Gen Intel Core i7 Processor', 350.00),
(2, 'CPU-AMD-001', 'Ryzen 7 5800X', '100-100000063WOF', 1, 2, 'AMD Ryzen 7 5800X 8-Core Processor', 300.00),
(3, 'GPU-NVD-001', 'GeForce RTX 4080', 'RTX4080-16G', 2, 3, 'NVIDIA GeForce RTX 4080 16GB GDDR6X', 1200.00),
(4, 'GPU-ASUS-001', 'ROG Strix RTX 3070', 'ROG-STRIX-RTX3070-O8G', 2, 4, 'ASUS ROG Strix NVIDIA GeForce RTX 3070', 650.00),
(5, 'MB-MSI-001', 'MAG B550 TOMAHAWK', 'MAG B550 TOMAHAWK', 3, 5, 'MSI MAG B550 TOMAHAWK Gaming Motherboard', 170.00),
(6, 'RAM-COR-001', 'Vengeance LPX 16GB', 'CMK16GX4M2B3200C16', 4, 6, 'Corsair Vengeance LPX 16GB (2x8GB) DDR4 3200MHz', 60.00),
(7, 'SSD-SAM-001', '970 EVO Plus 1TB', 'MZ-V7S1T0B/AM', 5, 7, 'Samsung 970 EVO Plus SSD 1TB NVMe M.2', 100.00),
(8, 'INT-GEN67-6700AB', 'Intel Core i67-6700K', 'BC676767X', 1, 1, 'Intel Core i67-6700K', 67.00);

-- --------------------------------------------------------

--
-- Table structure for table `stocktransaction`
--

CREATE TABLE `stocktransaction` (
  `TransactionID` int(11) NOT NULL,
  `TransactionType` varchar(255) NOT NULL,
  `Quantity` int(11) NOT NULL,
  `TransactionDate` datetime NOT NULL,
  `Notes` varchar(255) DEFAULT NULL,
  `PartID` int(11) DEFAULT NULL,
  `SupplierID` int(11) DEFAULT NULL,
  `UserID` int(11) DEFAULT NULL,
  `ReferenceNumber` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stocktransaction`
--

INSERT INTO `stocktransaction` (`TransactionID`, `TransactionType`, `Quantity`, `TransactionDate`, `Notes`, `PartID`, `SupplierID`, `UserID`, `ReferenceNumber`) VALUES
(1, 'Receipt', 50, '2026-06-01 10:00:00', 'Initial Stock', 1, 1, 1, 'PO-001'),
(2, 'Receipt', 40, '2026-06-01 11:30:00', 'Initial Stock', 2, 2, 2, 'PO-002'),
(3, 'Receipt', 15, '2026-06-02 09:15:00', 'New GPU Arrival', 3, 3, 1, 'PO-003'),
(4, 'Sale', -2, '2026-06-03 14:20:00', 'Sold to Walk-in Customer', 3, NULL, 4, 'INV-1001'),
(5, 'Receipt', 30, '2026-06-04 16:45:00', 'Restocking Motherboards', 5, 4, 3, 'PO-004'),
(6, 'Adjustment', -1, '2026-06-05 10:00:00', 'Damaged Item found during check', 6, NULL, 5, 'ADJ-001'),
(7, 'Sale', -5, '2026-06-06 13:00:00', 'Corporate Order', 7, NULL, 4, 'INV-1002'),
(8, 'Sale', 5, '2026-06-08 19:17:40', 'Bought by a customer', 1, NULL, 3, 'SOLD-0001'),
(9, 'Sale', -5, '2026-06-08 19:18:23', 'Bouyght', 1, NULL, 3, 'sold-0002'),
(10, 'Sale', -5, '2026-06-08 19:22:01', 'Bought', 1, NULL, 3, '123'),
(11, 'Sale', 5, '2026-06-08 19:22:17', '123', 1, NULL, 3, '123'),
(12, 'Receipt', 67, '2026-06-09 07:04:35', 'Bought from supplier', 8, 6, 3, 'ABC12367');

-- --------------------------------------------------------

--
-- Table structure for table `supplier`
--

CREATE TABLE `supplier` (
  `SupplierID` int(11) NOT NULL,
  `SupplierName` varchar(255) NOT NULL,
  `PhoneNumber` varchar(15) NOT NULL,
  `SupplierEmail` varchar(255) NOT NULL,
  `SupplierAddress` varchar(255) NOT NULL,
  `UserID` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `supplier`
--

INSERT INTO `supplier` (`SupplierID`, `SupplierName`, `PhoneNumber`, `SupplierEmail`, `SupplierAddress`, `UserID`) VALUES
(1, 'TechDistro Inc.', '800-1234', 'sales@techdistro.com', '123 Tech Blvd, Cityville', 1),
(2, 'PC Parts Wholesale', '800-5678', 'contact@pcparts.com', '456 Silicon Ave, Townsville', 2),
(3, 'Global Hardware', '800-9012', 'info@globalhw.com', '789 Circuit Road, Metropolis', 1),
(4, 'Direct Components', '800-3456', 'support@directcomp.com', '321 Motherboard St, Tech City', 3),
(5, 'Elite Systems Supply', '800-7890', 'orders@elitesys.com', '654 Processor Lane, Silicon Valley', 2),
(6, 'Eazy Peazy', '096', '123@gmail.com', '67 Sahur St.', 3);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `UserID` int(11) NOT NULL,
  `Username` varchar(255) NOT NULL,
  `UserPassword` varchar(255) NOT NULL,
  `FullName` varchar(100) DEFAULT NULL,
  `Role` varchar(255) NOT NULL,
  `Email` varchar(255) NOT NULL,
  `IsActive` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`UserID`, `Username`, `UserPassword`, `FullName`, `Role`, `Email`, `IsActive`) VALUES
(1, 'admin_kurt', 'hashedpw123', 'Kurt Paolo Redondo', 'Admin', 'kurt@example.com', 1),
(2, 'admin_earl', 'hashedpw123', 'Earl Amodia', 'Admin', 'earl@example.com', 1),
(3, 'admin_carlo', 'hashedpw123', 'John Carlo Nayan', 'Admin', 'carlo@example.com', 1),
(4, 'staff_juan', 'staffpass1', 'Juan Dela Cruz', 'Staff', 'juan.staff@example.com', 1),
(5, 'staff_maria', 'staffpass2', 'Maria Clara', 'Staff', 'maria.staff@example.com', 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `category`
--
ALTER TABLE `category`
  ADD PRIMARY KEY (`CategoryID`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`InventoryID`),
  ADD KEY `PartID` (`PartID`);

--
-- Indexes for table `manufacturer`
--
ALTER TABLE `manufacturer`
  ADD PRIMARY KEY (`ManufacturerID`);

--
-- Indexes for table `part`
--
ALTER TABLE `part`
  ADD PRIMARY KEY (`PartID`),
  ADD UNIQUE KEY `SKU` (`SKU`),
  ADD KEY `CategoryID` (`CategoryID`),
  ADD KEY `ManufacturerID` (`ManufacturerID`);

--
-- Indexes for table `stocktransaction`
--
ALTER TABLE `stocktransaction`
  ADD PRIMARY KEY (`TransactionID`),
  ADD KEY `PartID` (`PartID`),
  ADD KEY `SupplierID` (`SupplierID`),
  ADD KEY `UserID` (`UserID`);

--
-- Indexes for table `supplier`
--
ALTER TABLE `supplier`
  ADD PRIMARY KEY (`SupplierID`),
  ADD KEY `UserID` (`UserID`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`UserID`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `category`
--
ALTER TABLE `category`
  MODIFY `CategoryID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `InventoryID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `manufacturer`
--
ALTER TABLE `manufacturer`
  MODIFY `ManufacturerID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `part`
--
ALTER TABLE `part`
  MODIFY `PartID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `stocktransaction`
--
ALTER TABLE `stocktransaction`
  MODIFY `TransactionID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `supplier`
--
ALTER TABLE `supplier`
  MODIFY `SupplierID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `UserID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `inventory`
--
ALTER TABLE `inventory`
  ADD CONSTRAINT `inventory_ibfk_1` FOREIGN KEY (`PartID`) REFERENCES `part` (`PartID`);

--
-- Constraints for table `part`
--
ALTER TABLE `part`
  ADD CONSTRAINT `part_ibfk_1` FOREIGN KEY (`CategoryID`) REFERENCES `category` (`CategoryID`),
  ADD CONSTRAINT `part_ibfk_2` FOREIGN KEY (`ManufacturerID`) REFERENCES `manufacturer` (`ManufacturerID`);

--
-- Constraints for table `stocktransaction`
--
ALTER TABLE `stocktransaction`
  ADD CONSTRAINT `stocktransaction_ibfk_1` FOREIGN KEY (`PartID`) REFERENCES `part` (`PartID`),
  ADD CONSTRAINT `stocktransaction_ibfk_2` FOREIGN KEY (`SupplierID`) REFERENCES `supplier` (`SupplierID`),
  ADD CONSTRAINT `stocktransaction_ibfk_3` FOREIGN KEY (`UserID`) REFERENCES `users` (`UserID`);

--
-- Constraints for table `supplier`
--
ALTER TABLE `supplier`
  ADD CONSTRAINT `supplier_ibfk_1` FOREIGN KEY (`UserID`) REFERENCES `users` (`UserID`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
