-- CITY CARE HOSPITAL: COMPLETE FRESH INSTALL
-- Import ONCE into a NEW, EMPTY citycare_hospital database selected in phpMyAdmin.
-- Never run over an existing project database. No DROP DATABASE is used.
-- Original SQL sources are retained separately.
-- Explicit root@localhost DEFINER clauses omitted for importer portability.

-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Oct 23, 2026 at 12:23 PM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.0.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `citycare_hospital`
--

DELIMITER $$
--
-- Procedures
--
CREATE PROCEDURE `sp_doctors_by_department` ()   BEGIN
 SELECT dep.department_name,COUNT(d.doctor_id) AS doctor_count
 FROM department dep LEFT JOIN doctor d ON d.department_id=dep.department_id
 GROUP BY dep.department_id,dep.department_name ORDER BY dep.department_name;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `admission`
--

CREATE TABLE `admission` (
  `admission_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `admission_date` datetime NOT NULL DEFAULT current_timestamp(),
  `discharge_date` datetime DEFAULT NULL,
  `status` enum('Admitted','Discharged') NOT NULL DEFAULT 'Admitted',
  `notes` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admission`
--

INSERT INTO `admission` (`admission_id`, `patient_id`, `room_id`, `admission_date`, `discharge_date`, `status`, `notes`) VALUES
(1, 2, 1, '2026-10-20 15:47:36', '2026-10-22 15:47:36', 'Discharged', 'Observation'),
(2, 4, 3, '2026-10-21 15:47:36', NULL, 'Admitted', 'Orthopedic observation'),
(3, 5, 5, '2026-10-22 15:47:36', NULL, 'Admitted', 'Monitoring'),
(4, 8, 7, '2026-10-18 15:47:36', '2026-10-19 15:47:36', 'Discharged', 'Emergency observation'),
(5, 1, 2, '2026-10-16 15:47:36', '2026-10-17 15:47:36', 'Discharged', 'Short stay'),
(6, 3, 4, '2026-10-19 15:47:36', '2026-10-20 15:47:36', 'Discharged', 'Observation'),
(7, 6, 8, '2026-10-21 15:47:36', '2026-10-22 15:47:36', 'Discharged', 'Pediatric observation'),
(8, 7, 6, '2026-10-22 15:47:36', NULL, 'Admitted', 'ICU monitoring');

--
-- Triggers `admission`
--
DELIMITER $$
CREATE TRIGGER `trg_admission_room_insert` AFTER INSERT ON `admission` FOR EACH ROW BEGIN
 IF NEW.status='Admitted' THEN UPDATE room SET status='Occupied' WHERE room_id=NEW.room_id; END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_admission_room_update` AFTER UPDATE ON `admission` FOR EACH ROW BEGIN
 IF NEW.status='Discharged' AND OLD.status<>'Discharged' THEN
  UPDATE room SET status='Available' WHERE room_id=NEW.room_id AND NOT EXISTS
  (SELECT 1 FROM admission a WHERE a.room_id=NEW.room_id AND a.status='Admitted' AND a.admission_id<>NEW.admission_id);
 END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `appointment`
--

CREATE TABLE `appointment` (
  `appointment_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `status` enum('Booked','Completed','Cancelled','No-show') NOT NULL DEFAULT 'Booked',
  `notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `appointment`
--

INSERT INTO `appointment` (`appointment_id`, `patient_id`, `doctor_id`, `appointment_date`, `appointment_time`, `status`, `notes`, `created_at`) VALUES
(1, 1, 1, '2026-10-23', '09:00:00', 'Booked', 'Routine consultation', '2026-10-23 09:47:36'),
(2, 2, 3, '2026-10-23', '09:30:00', 'Booked', 'Headache consultation', '2026-10-23 09:47:36'),
(3, 3, 5, '2026-10-24', '10:00:00', 'Booked', 'Child wellness', '2026-10-23 09:47:36'),
(4, 4, 4, '2026-10-24', '11:00:00', 'Booked', 'Knee pain', '2026-10-23 09:47:36'),
(5, 5, 1, '2026-10-25', '12:00:00', 'Booked', 'Follow-up', '2026-10-23 09:47:36'),
(6, 6, 5, '2026-10-25', '12:30:00', 'Cancelled', 'Rescheduled', '2026-10-23 09:47:36'),
(7, 7, 7, '2026-10-26', '14:00:00', 'Booked', 'Test review', '2026-10-23 09:47:36'),
(8, 8, 6, '2026-10-26', '15:00:00', 'Completed', 'Urgent assessment', '2026-10-23 09:47:36');

--
-- Triggers `appointment`
--
DELIMITER $$
CREATE TRIGGER `trg_appointment_no_conflict_insert` BEFORE INSERT ON `appointment` FOR EACH ROW BEGIN
 IF NEW.status <> 'Cancelled' AND EXISTS(
  SELECT 1 FROM appointment a WHERE a.doctor_id=NEW.doctor_id
  AND a.appointment_date=NEW.appointment_date AND a.appointment_time=NEW.appointment_time
  AND a.status <> 'Cancelled'
 ) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Doctor already has an appointment at this date and time';
 END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_appointment_no_conflict_update` BEFORE UPDATE ON `appointment` FOR EACH ROW BEGIN
 IF NEW.status <> 'Cancelled' AND EXISTS(
  SELECT 1 FROM appointment a WHERE a.doctor_id=NEW.doctor_id
  AND a.appointment_date=NEW.appointment_date AND a.appointment_time=NEW.appointment_time
  AND a.appointment_id<>NEW.appointment_id AND a.status <> 'Cancelled'
 ) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Doctor already has an appointment at this date and time';
 END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `bill`
--

CREATE TABLE `bill` (
  `bill_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `admission_id` int(11) DEFAULT NULL,
  `bill_date` date NOT NULL DEFAULT curdate(),
  `description` varchar(255) NOT NULL,
  `amount` decimal(10,2) NOT NULL CHECK (`amount` >= 0),
  `payment_status` enum('Unpaid','Paid') NOT NULL DEFAULT 'Unpaid',
  `paid_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `bill`
--

INSERT INTO `bill` (`bill_id`, `patient_id`, `admission_id`, `bill_date`, `description`, `amount`, `payment_status`, `paid_at`) VALUES
(1, 2, 1, '2026-10-23', 'Observation and room charges', 3500.00, 'Paid', '2026-10-23 15:47:36'),
(2, 4, 2, '2026-10-23', 'Orthopedic admission deposit', 5000.00, 'Unpaid', NULL),
(3, 5, 3, '2026-10-23', 'ICU and monitoring charges', 12000.00, 'Unpaid', NULL),
(4, 8, 4, '2026-10-23', 'Emergency care', 4200.00, 'Paid', '2026-10-23 15:47:36'),
(5, 1, 5, '2026-10-23', 'General room charges', 1800.00, 'Paid', '2026-10-23 15:47:36'),
(6, 3, 6, '2026-10-23', 'Observation charges', 2500.00, 'Unpaid', NULL),
(7, 6, 7, '2026-10-23', 'Pediatric care', 2200.00, 'Paid', '2026-10-23 15:47:36'),
(8, 7, 8, '2026-10-23', 'ICU charges', 8000.00, 'Unpaid', NULL);

--
-- Triggers `bill`
--
DELIMITER $$
CREATE TRIGGER `trg_bill_paid_insert` BEFORE INSERT ON `bill` FOR EACH ROW BEGIN
 IF NEW.payment_status='Paid' AND NEW.paid_at IS NULL THEN SET NEW.paid_at=NOW(); END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_bill_paid_update` BEFORE UPDATE ON `bill` FOR EACH ROW BEGIN
 IF NEW.payment_status='Paid' AND OLD.payment_status<>'Paid' AND NEW.paid_at IS NULL THEN SET NEW.paid_at=NOW(); END IF;
 IF NEW.payment_status='Unpaid' THEN SET NEW.paid_at=NULL; END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `department`
--

CREATE TABLE `department` (
  `department_id` int(11) NOT NULL,
  `department_name` varchar(80) NOT NULL,
  `description` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `department`
--

INSERT INTO `department` (`department_id`, `department_name`, `description`) VALUES
(1, 'Cardiology', 'Heart and cardiovascular care'),
(2, 'Neurology', 'Brain and nervous system care'),
(3, 'Orthopedics', 'Bones and joints'),
(4, 'Pediatrics', 'Child healthcare'),
(5, 'Emergency Care', 'Urgent treatment'),
(6, 'Laboratory', 'Diagnostic laboratory');

-- --------------------------------------------------------

--
-- Table structure for table `doctor`
--

CREATE TABLE `doctor` (
  `doctor_id` int(11) NOT NULL,
  `department_id` int(11) NOT NULL,
  `doctor_name` varchar(100) NOT NULL,
  `specialization` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(120) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `doctor`
--

INSERT INTO `doctor` (`doctor_id`, `department_id`, `doctor_name`, `specialization`, `phone`, `email`) VALUES
(1, 1, 'Dr. Farhana Rahman', 'Cardiologist', '01710000001', 'farhana@citycare.example'),
(2, 1, 'Dr. Mahmud Hasan', 'Interventional Cardiology', '01710000002', 'mahmud@citycare.example'),
(3, 2, 'Dr. Nusrat Jahan', 'Neurologist', '01710000003', 'nusrat@citycare.example'),
(4, 3, 'Dr. Tanvir Ahmed', 'Orthopedic Surgeon', '01710000004', 'tanvir@citycare.example'),
(5, 4, 'Dr. Samia Akter', 'Pediatrician', '01710000005', 'samia@citycare.example'),
(6, 5, 'Dr. Imran Hossain', 'Emergency Medicine', '01710000006', 'imran@citycare.example'),
(7, 6, 'Dr. Sabiha Islam', 'Pathologist', '01710000007', 'sabiha@citycare.example'),
(8, 2, 'Dr. Rezaul Karim', 'Clinical Neurology', '01710000008', 'rezaul@citycare.example');

-- --------------------------------------------------------

--
-- Table structure for table `patient`
--

CREATE TABLE `patient` (
  `patient_id` int(11) NOT NULL,
  `patient_name` varchar(100) NOT NULL,
  `gender` enum('Male','Female','Other') NOT NULL,
  `date_of_birth` date DEFAULT NULL,
  `phone` varchar(20) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `patient`
--

INSERT INTO `patient` (`patient_id`, `patient_name`, `gender`, `date_of_birth`, `phone`, `address`, `created_at`) VALUES
(1, 'Ayesha Begum', 'Female', '1998-04-12', '01810000001', 'Mirpur, Dhaka', '2026-10-23 09:47:36'),
(2, 'Rahim Uddin', 'Male', '1985-09-22', '01810000002', 'Mohammadpur, Dhaka', '2026-10-23 09:47:36'),
(3, 'Nusrat Sultana', 'Female', '2002-01-15', '01810000003', 'Uttara, Dhaka', '2026-10-23 09:47:36'),
(4, 'Sakib Hossain', 'Male', '1992-06-30', '01810000004', 'Dhanmondi, Dhaka', '2026-10-23 09:47:36'),
(5, 'Fatema Khatun', 'Female', '1978-12-08', '01810000005', 'Badda, Dhaka', '2026-10-23 09:47:36'),
(6, 'Mehedi Hasan', 'Male', '2015-03-11', '01810000006', 'Jatrabari, Dhaka', '2026-10-23 09:47:36'),
(7, 'Jannatul Ferdous', 'Female', '1990-08-27', '01810000007', 'Banani, Dhaka', '2026-10-23 09:47:36'),
(8, 'Abdul Karim', 'Male', '1969-11-02', '01810000008', 'Khilgaon, Dhaka', '2026-10-23 09:47:36');

-- --------------------------------------------------------

--
-- Table structure for table `room`
--

CREATE TABLE `room` (
  `room_id` int(11) NOT NULL,
  `room_number` varchar(20) NOT NULL,
  `room_type` enum('General','Cabin','ICU','Emergency') NOT NULL,
  `daily_rate` decimal(10,2) NOT NULL DEFAULT 0.00 CHECK (`daily_rate` >= 0),
  `status` enum('Available','Occupied','Maintenance') NOT NULL DEFAULT 'Available'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `room`
--

INSERT INTO `room` (`room_id`, `room_number`, `room_type`, `daily_rate`, `status`) VALUES
(1, 'G-101', 'General', 1500.00, 'Available'),
(2, 'G-102', 'General', 1500.00, 'Available'),
(3, 'C-201', 'Cabin', 4000.00, 'Occupied'),
(4, 'C-202', 'Cabin', 4500.00, 'Available'),
(5, 'ICU-01', 'ICU', 8000.00, 'Occupied'),
(6, 'ICU-02', 'ICU', 8000.00, 'Occupied'),
(7, 'ER-01', 'Emergency', 2500.00, 'Available'),
(8, 'G-103', 'General', 1800.00, 'Available');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` char(64) NOT NULL,
  `role` enum('Admin','Receptionist') NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `password_hash`, `role`, `full_name`, `active`) VALUES
(1, 'admin', '240be518fabd2724ddb6f04eeb1da5967448d7e831c08c8fa822809f74c720a9', 'Admin', 'System Administrator', 1),
(2, 'reception', '5145dba3b6bda2d610d2c5c435a1c2481eefd3146b6a7e004ad73f794386e031', 'Receptionist', 'Reception Desk', 1);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_appointment_details`
-- (See below for the actual view)
--
CREATE TABLE `vw_appointment_details` (
`appointment_id` int(11)
,`patient_name` varchar(100)
,`doctor_name` varchar(100)
,`department_name` varchar(80)
,`appointment_date` date
,`appointment_time` time
,`status` enum('Booked','Completed','Cancelled','No-show')
);

-- --------------------------------------------------------

--
-- Structure for view `vw_appointment_details`
--
DROP TABLE IF EXISTS `vw_appointment_details`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `vw_appointment_details`  AS SELECT `a`.`appointment_id` AS `appointment_id`, `p`.`patient_name` AS `patient_name`, `d`.`doctor_name` AS `doctor_name`, `dep`.`department_name` AS `department_name`, `a`.`appointment_date` AS `appointment_date`, `a`.`appointment_time` AS `appointment_time`, `a`.`status` AS `status` FROM (((`appointment` `a` join `patient` `p` on(`p`.`patient_id` = `a`.`patient_id`)) join `doctor` `d` on(`d`.`doctor_id` = `a`.`doctor_id`)) join `department` `dep` on(`dep`.`department_id` = `d`.`department_id`)) ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admission`
--
ALTER TABLE `admission`
  ADD PRIMARY KEY (`admission_id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `idx_room_status` (`room_id`,`status`);

--
-- Indexes for table `appointment`
--
ALTER TABLE `appointment`
  ADD PRIMARY KEY (`appointment_id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `idx_doctor_slot` (`doctor_id`,`appointment_date`,`appointment_time`);

--
-- Indexes for table `bill`
--
ALTER TABLE `bill`
  ADD PRIMARY KEY (`bill_id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `admission_id` (`admission_id`);

--
-- Indexes for table `department`
--
ALTER TABLE `department`
  ADD PRIMARY KEY (`department_id`),
  ADD UNIQUE KEY `department_name` (`department_name`);

--
-- Indexes for table `doctor`
--
ALTER TABLE `doctor`
  ADD PRIMARY KEY (`doctor_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `department_id` (`department_id`);

--
-- Indexes for table `patient`
--
ALTER TABLE `patient`
  ADD PRIMARY KEY (`patient_id`);

--
-- Indexes for table `room`
--
ALTER TABLE `room`
  ADD PRIMARY KEY (`room_id`),
  ADD UNIQUE KEY `room_number` (`room_number`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admission`
--
ALTER TABLE `admission`
  MODIFY `admission_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `appointment`
--
ALTER TABLE `appointment`
  MODIFY `appointment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `bill`
--
ALTER TABLE `bill`
  MODIFY `bill_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `department`
--
ALTER TABLE `department`
  MODIFY `department_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `doctor`
--
ALTER TABLE `doctor`
  MODIFY `doctor_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `patient`
--
ALTER TABLE `patient`
  MODIFY `patient_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `room`
--
ALTER TABLE `room`
  MODIFY `room_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admission`
--
ALTER TABLE `admission`
  ADD CONSTRAINT `admission_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patient` (`patient_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `admission_ibfk_2` FOREIGN KEY (`room_id`) REFERENCES `room` (`room_id`) ON UPDATE CASCADE;

--
-- Constraints for table `appointment`
--
ALTER TABLE `appointment`
  ADD CONSTRAINT `appointment_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patient` (`patient_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `appointment_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `doctor` (`doctor_id`) ON UPDATE CASCADE;

--
-- Constraints for table `bill`
--
ALTER TABLE `bill`
  ADD CONSTRAINT `bill_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patient` (`patient_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `bill_ibfk_2` FOREIGN KEY (`admission_id`) REFERENCES `admission` (`admission_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `doctor`
--
ALTER TABLE `doctor`
  ADD CONSTRAINT `doctor_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `department` (`department_id`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;


-- SECTION 2: PORTAL TABLES
SET NAMES utf8mb4;
-- Run this ONCE in phpMyAdmin after selecting citycare_hospital.
-- Does not drop or modify your existing eight core tables.
CREATE TABLE IF NOT EXISTS portal_accounts (
  account_id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(80) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('Patient','Doctor','Admin','LabStaff') NOT NULL,
  patient_id INT NULL UNIQUE,
  doctor_id INT NULL UNIQUE,
  full_name VARCHAR(120) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_portal_patient FOREIGN KEY (patient_id) REFERENCES patient(patient_id) ON DELETE CASCADE,
  CONSTRAINT fk_portal_doctor FOREIGN KEY (doctor_id) REFERENCES doctor(doctor_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS prescriptions (
  prescription_id INT AUTO_INCREMENT PRIMARY KEY,
  patient_id INT NOT NULL,
  doctor_id INT NOT NULL,
  appointment_id INT NULL,
  created_by_account_id INT NOT NULL,
  diagnosis VARCHAR(255) NOT NULL,
  clinical_notes TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_prescription_patient FOREIGN KEY (patient_id) REFERENCES patient(patient_id),
  CONSTRAINT fk_prescription_doctor FOREIGN KEY (doctor_id) REFERENCES doctor(doctor_id),
  CONSTRAINT fk_prescription_appointment FOREIGN KEY (appointment_id) REFERENCES appointment(appointment_id) ON DELETE SET NULL,
  CONSTRAINT fk_prescription_account FOREIGN KEY (created_by_account_id) REFERENCES portal_accounts(account_id),
  INDEX idx_prescription_patient (patient_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS prescription_items (
  item_id INT AUTO_INCREMENT PRIMARY KEY,
  prescription_id INT NOT NULL,
  medicine_name VARCHAR(160) NOT NULL,
  dose VARCHAR(100) NULL,
  frequency VARCHAR(100) NULL,
  duration VARCHAR(100) NULL,
  instructions VARCHAR(255) NULL,
  CONSTRAINT fk_item_prescription FOREIGN KEY (prescription_id) REFERENCES prescriptions(prescription_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lab_reports (
  report_id INT AUTO_INCREMENT PRIMARY KEY,
  patient_id INT NOT NULL,
  uploaded_by_account_id INT NOT NULL,
  test_name VARCHAR(160) NOT NULL,
  report_date DATE NOT NULL,
  notes TEXT NULL,
  stored_filename VARCHAR(100) NOT NULL UNIQUE,
  original_filename VARCHAR(255) NOT NULL,
  mime_type VARCHAR(80) NOT NULL,
  file_size INT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_report_patient FOREIGN KEY (patient_id) REFERENCES patient(patient_id),
  CONSTRAINT fk_report_uploader FOREIGN KEY (uploaded_by_account_id) REFERENCES portal_accounts(account_id),
  INDEX idx_report_patient (patient_id, report_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS portal_audit_log (
  audit_id BIGINT AUTO_INCREMENT PRIMARY KEY,
  account_id INT NOT NULL,
  action_name VARCHAR(100) NOT NULL,
  record_id INT NULL,
  ip_address VARCHAR(45) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_audit_account FOREIGN KEY (account_id) REFERENCES portal_accounts(account_id),
  INDEX idx_audit_account_date (account_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- SECTION 3: BILLING TABLES AND LEGACY BILL TRANSFER
-- Run ONCE in phpMyAdmin after selecting citycare_hospital.
-- Creates itemized billing and payment history without altering existing tables.
-- Existing rows from the legacy bill table are copied as read-only-style legacy invoices.

CREATE TABLE IF NOT EXISTS billing_invoices (
  invoice_id INT AUTO_INCREMENT PRIMARY KEY,
  invoice_number VARCHAR(40) NOT NULL UNIQUE,
  patient_id INT NOT NULL,
  appointment_id INT NULL,
  invoice_date DATE NOT NULL,
  due_date DATE NULL,
  subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  tax_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  amount_paid DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  payment_status ENUM('Unpaid','Partially Paid','Paid','Cancelled') NOT NULL DEFAULT 'Unpaid',
  notes TEXT NULL,
  created_by_account_id INT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_billing_invoice_patient FOREIGN KEY (patient_id) REFERENCES patient(patient_id),
  CONSTRAINT fk_billing_invoice_appointment FOREIGN KEY (appointment_id) REFERENCES appointment(appointment_id) ON DELETE SET NULL,
  CONSTRAINT fk_billing_invoice_creator FOREIGN KEY (created_by_account_id) REFERENCES portal_accounts(account_id) ON DELETE SET NULL,
  INDEX idx_billing_patient_date (patient_id, invoice_date),
  INDEX idx_billing_status_date (payment_status, invoice_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS billing_invoice_items (
  item_id INT AUTO_INCREMENT PRIMARY KEY,
  invoice_id INT NOT NULL,
  description VARCHAR(200) NOT NULL,
  quantity DECIMAL(10,2) NOT NULL DEFAULT 1.00,
  unit_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  line_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  CONSTRAINT fk_billing_item_invoice FOREIGN KEY (invoice_id) REFERENCES billing_invoices(invoice_id) ON DELETE CASCADE,
  INDEX idx_billing_items_invoice (invoice_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS billing_payments (
  payment_id INT AUTO_INCREMENT PRIMARY KEY,
  invoice_id INT NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  payment_method ENUM('Cash','bKash','Nagad','Card','Bank Transfer','Other') NOT NULL DEFAULT 'Cash',
  transaction_reference VARCHAR(120) NULL,
  paid_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  received_by_account_id INT NULL,
  notes VARCHAR(255) NULL,
  CONSTRAINT fk_billing_payment_invoice FOREIGN KEY (invoice_id) REFERENCES billing_invoices(invoice_id) ON DELETE CASCADE,
  CONSTRAINT fk_billing_payment_receiver FOREIGN KEY (received_by_account_id) REFERENCES portal_accounts(account_id) ON DELETE SET NULL,
  INDEX idx_billing_payments_invoice (invoice_id, paid_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bring forward the existing simple bill records once, preserving their original IDs in invoice numbers.
INSERT IGNORE INTO billing_invoices
(invoice_number, patient_id, appointment_id, invoice_date, subtotal, discount_amount, tax_percent, tax_amount, total_amount, amount_paid, payment_status, notes, created_by_account_id)
SELECT CONCAT('LEGACY-', b.bill_id), b.patient_id, NULL, b.bill_date, b.amount, 0, 0, 0, b.amount,
       CASE WHEN b.payment_status='Paid' THEN b.amount ELSE 0 END,
       b.payment_status, CONCAT('Migrated from existing bill record #', b.bill_id), NULL
FROM bill b;

INSERT INTO billing_invoice_items (invoice_id, description, quantity, unit_price, line_total)
SELECT i.invoice_id, b.description, 1, b.amount, b.amount
FROM bill b
JOIN billing_invoices i ON i.invoice_number=CONCAT('LEGACY-', b.bill_id)
LEFT JOIN billing_invoice_items li ON li.invoice_id=i.invoice_id
WHERE li.item_id IS NULL;

INSERT INTO billing_payments (invoice_id, amount, payment_method, paid_at, received_by_account_id, notes)
SELECT i.invoice_id, b.amount, 'Other', COALESCE(b.paid_at, CONCAT(b.bill_date, ' 00:00:00')), NULL,
       'Legacy payment record; original payment method was not recorded'
FROM bill b
JOIN billing_invoices i ON i.invoice_number=CONCAT('LEGACY-', b.bill_id)
LEFT JOIN billing_payments p ON p.invoice_id=i.invoice_id
WHERE b.payment_status='Paid' AND p.payment_id IS NULL;
