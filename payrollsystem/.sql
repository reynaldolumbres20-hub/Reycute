-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 26, 2026 at 11:33 AM
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
-- Database: `payroll_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `employee_id` varchar(20) DEFAULT NULL,
  `employee_name` varchar(100) DEFAULT NULL,
  `department` varchar(50) DEFAULT NULL,
  `position` varchar(50) DEFAULT NULL,
  `shift_type` varchar(20) DEFAULT NULL,
  `hourly_rate` decimal(10,2) DEFAULT 100.00,
  `employee_email` varchar(100) DEFAULT NULL,
  `attendance_date` date DEFAULT NULL,
  `time_in` datetime DEFAULT NULL,
  `time_out` datetime DEFAULT NULL,
  `hours_worked` decimal(5,2) DEFAULT 0.00,
  `regular_hours` decimal(5,2) DEFAULT 0.00,
  `overtime_hours` decimal(5,2) DEFAULT 0.00,
  `overtime_pay` decimal(10,2) DEFAULT 0.00,
  `night_diff_hours` decimal(5,2) DEFAULT 0.00,
  `break_hours` decimal(5,2) DEFAULT 1.00,
  `night_diff_pay` decimal(10,2) DEFAULT 0.00,
  `total_pay` decimal(10,2) DEFAULT 0.00,
  `status` varchar(50) DEFAULT NULL,
  `is_late` int(11) DEFAULT 0,
  `is_undertime` int(11) DEFAULT 0,
  `is_holiday` int(11) DEFAULT 0,
  `is_restday` int(11) DEFAULT 0,
  `email_status` varchar(20) DEFAULT 'PENDING',
  `email_sent_date` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`id`, `employee_id`, `employee_name`, `department`, `position`, `shift_type`, `hourly_rate`, `employee_email`, `attendance_date`, `time_in`, `time_out`, `hours_worked`, `regular_hours`, `overtime_hours`, `overtime_pay`, `night_diff_hours`, `break_hours`, `night_diff_pay`, `total_pay`, `status`, `is_late`, `is_undertime`, `is_holiday`, `is_restday`, `email_status`, `email_sent_date`) VALUES
(5, '22-2404', 'REYNALDO LUMBRES', 'IT DEPARTMENT', 'SOFTWARE DEVELOPER', 'MORNING', 100.00, 'reynaldolumbres20@gmail.com', '2026-03-26', '2026-03-26 08:00:00', '2026-03-26 20:00:00', 11.00, 8.00, 3.00, 375.00, 0.00, 1.00, 0.00, 1175.00, 'PRESENT, OT', 0, 0, 0, 0, 'SENT', '2026-03-26 13:33:55'),
(6, '22-2404', 'REYNALDO LUMBRES', 'IT DEPARTMENT', 'SOFTWARE DEVELOPER', 'MORNING', 100.00, 'reynaldolumbres20@gmail.com', '2026-03-26', '2026-03-26 08:00:00', '2026-03-26 17:00:00', 8.00, 8.00, 0.00, 0.00, 0.00, 1.00, 0.00, 800.00, 'PRESENT', 0, 0, 0, 0, 'SENT', '2026-03-26 13:33:58');

-- --------------------------------------------------------

--
-- Table structure for table `payslip`
--

CREATE TABLE `payslip` (
  `id` int(11) NOT NULL,
  `attendance_id` int(11) DEFAULT NULL,
  `employee_id` varchar(20) DEFAULT NULL,
  `employee_name` varchar(100) DEFAULT NULL,
  `pay_period` varchar(50) DEFAULT NULL,
  `attendance_date` date DEFAULT NULL,
  `hours_worked` decimal(5,2) DEFAULT NULL,
  `regular_hours` decimal(5,2) DEFAULT NULL,
  `overtime_hours` decimal(5,2) DEFAULT NULL,
  `night_diff_hours` decimal(5,2) DEFAULT NULL,
  `hourly_rate` decimal(10,2) DEFAULT NULL,
  `total_pay` decimal(12,2) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `generated_date` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblattendance`
--

CREATE TABLE `tblattendance` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `time_in` time DEFAULT NULL,
  `time_out` time DEFAULT NULL,
  `break_start` time DEFAULT NULL,
  `break_end` time DEFAULT NULL,
  `break_duration` decimal(5,2) DEFAULT 0.00,
  `hours_worked` decimal(5,2) DEFAULT NULL,
  `overtime_hours` decimal(5,2) DEFAULT 0.00,
  `night_diff_hours` decimal(5,2) DEFAULT 0.00,
  `is_holiday` tinyint(4) DEFAULT 0,
  `is_rest_day` tinyint(4) DEFAULT 0,
  `status` enum('present','absent','late','halfday') DEFAULT 'present',
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblcompany_settings`
--

CREATE TABLE `tblcompany_settings` (
  `id` int(11) NOT NULL,
  `company_name` varchar(100) DEFAULT NULL,
  `company_address` text DEFAULT NULL,
  `company_contact` varchar(20) DEFAULT NULL,
  `company_email` varchar(100) DEFAULT NULL,
  `tax_rate` decimal(5,2) DEFAULT 0.00,
  `sss_rate` decimal(5,2) DEFAULT 0.00,
  `philhealth_rate` decimal(5,2) DEFAULT 0.00,
  `pagibig_rate` decimal(5,2) DEFAULT 0.00,
  `night_diff_rate` decimal(5,2) DEFAULT 1.10,
  `overtime_rate` decimal(5,2) DEFAULT 1.25,
  `holiday_rate` decimal(5,2) DEFAULT 2.00,
  `rest_day_rate` decimal(5,2) DEFAULT 1.30,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `tblcompany_settings`
--

INSERT INTO `tblcompany_settings` (`id`, `company_name`, `company_address`, `company_contact`, `company_email`, `tax_rate`, `sss_rate`, `philhealth_rate`, `pagibig_rate`, `night_diff_rate`, `overtime_rate`, `holiday_rate`, `rest_day_rate`, `updated_by`, `updated_at`) VALUES
(1, 'Payroll System Inc.', 'Manila, Philippines', '02-123-4567', 'info@payrollsystem.com', 15.00, 4.50, 3.00, 2.00, 1.10, 1.25, 2.00, 1.30, NULL, '2026-03-26 10:02:20');

-- --------------------------------------------------------

--
-- Table structure for table `tblemployees`
--

CREATE TABLE `tblemployees` (
  `id` int(11) NOT NULL,
  `employee_no` varchar(20) NOT NULL,
  `firstname` varchar(50) NOT NULL,
  `lastname` varchar(50) NOT NULL,
  `middlename` varchar(50) DEFAULT NULL,
  `gender` enum('Male','Female') NOT NULL,
  `birthdate` date DEFAULT NULL,
  `address` text DEFAULT NULL,
  `contact_no` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `position` varchar(50) DEFAULT NULL,
  `department` varchar(50) DEFAULT NULL,
  `salary_rate` decimal(10,2) NOT NULL,
  `salary_type` enum('monthly','daily','hourly') DEFAULT 'monthly',
  `hourly_rate` decimal(10,2) DEFAULT NULL,
  `daily_rate` decimal(10,2) DEFAULT NULL,
  `hire_date` date DEFAULT NULL,
  `status` enum('active','inactive','resigned') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblemployee_excel_imports`
--

CREATE TABLE `tblemployee_excel_imports` (
  `id` int(11) NOT NULL,
  `filename` varchar(255) DEFAULT NULL,
  `imported_by` int(11) DEFAULT NULL,
  `total_records` int(11) DEFAULT NULL,
  `successful_records` int(11) DEFAULT NULL,
  `failed_records` int(11) DEFAULT NULL,
  `error_log` text DEFAULT NULL,
  `import_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblholidays`
--

CREATE TABLE `tblholidays` (
  `id` int(11) NOT NULL,
  `holiday_name` varchar(100) NOT NULL,
  `holiday_date` date NOT NULL,
  `holiday_type` enum('regular','special_non_working','special_working') NOT NULL,
  `double_pay` tinyint(4) DEFAULT 0,
  `is_active` tinyint(4) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblleaves`
--

CREATE TABLE `tblleaves` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `leave_type` enum('sick','vacation','emergency','maternity','paternity') NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `days_applied` int(11) NOT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `approved_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblleave_balances`
--

CREATE TABLE `tblleave_balances` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `year` int(11) NOT NULL,
  `sick_leave_balance` int(11) DEFAULT 15,
  `vacation_leave_balance` int(11) DEFAULT 15,
  `emergency_leave_balance` int(11) DEFAULT 5,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblloans`
--

CREATE TABLE `tblloans` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `loan_type` enum('salary','emergency','calamity') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `terms` int(11) NOT NULL,
  `monthly_amortization` decimal(10,2) DEFAULT NULL,
  `total_paid` decimal(10,2) DEFAULT 0.00,
  `balance` decimal(10,2) DEFAULT NULL,
  `status` enum('pending','approved','paid','rejected') DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `date_approved` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblnight_differential_settings`
--

CREATE TABLE `tblnight_differential_settings` (
  `id` int(11) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `differential_rate` decimal(5,2) NOT NULL,
  `is_active` tinyint(4) DEFAULT 1,
  `effective_year` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `tblnight_differential_settings`
--

INSERT INTO `tblnight_differential_settings` (`id`, `start_time`, `end_time`, `differential_rate`, `is_active`, `effective_year`, `created_at`) VALUES
(1, '22:00:00', '06:00:00', 1.10, 1, 2026, '2026-03-26 10:02:20');

-- --------------------------------------------------------

--
-- Table structure for table `tblovertime_settings`
--

CREATE TABLE `tblovertime_settings` (
  `id` int(11) NOT NULL,
  `overtime_type` enum('regular','holiday','rest_day') NOT NULL,
  `rate_multiplier` decimal(5,2) NOT NULL,
  `is_active` tinyint(4) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `tblovertime_settings`
--

INSERT INTO `tblovertime_settings` (`id`, `overtime_type`, `rate_multiplier`, `is_active`, `created_at`) VALUES
(1, 'regular', 1.25, 1, '2026-03-26 10:02:20'),
(2, 'holiday', 2.00, 1, '2026-03-26 10:02:20'),
(3, 'rest_day', 1.30, 1, '2026-03-26 10:02:20');

-- --------------------------------------------------------

--
-- Table structure for table `tblpagibig_contributions`
--

CREATE TABLE `tblpagibig_contributions` (
  `id` int(11) NOT NULL,
  `salary_range_min` decimal(10,2) DEFAULT NULL,
  `salary_range_max` decimal(10,2) DEFAULT NULL,
  `employee_share` decimal(10,2) DEFAULT NULL,
  `employer_share` decimal(10,2) DEFAULT NULL,
  `effective_year` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblpayroll`
--

CREATE TABLE `tblpayroll` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `payroll_period` varchar(50) NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `working_days` int(11) DEFAULT 0,
  `total_hours_worked` decimal(10,2) DEFAULT 0.00,
  `regular_hours` decimal(10,2) DEFAULT 0.00,
  `overtime_hours` decimal(10,2) DEFAULT 0.00,
  `night_diff_hours` decimal(10,2) DEFAULT 0.00,
  `holiday_hours` decimal(10,2) DEFAULT 0.00,
  `basic_salary` decimal(10,2) DEFAULT NULL,
  `overtime_pay` decimal(10,2) DEFAULT 0.00,
  `night_diff_pay` decimal(10,2) DEFAULT 0.00,
  `holiday_pay` decimal(10,2) DEFAULT 0.00,
  `allowances` decimal(10,2) DEFAULT 0.00,
  `gross_pay` decimal(10,2) DEFAULT NULL,
  `sss_deduction` decimal(10,2) DEFAULT 0.00,
  `philhealth_deduction` decimal(10,2) DEFAULT 0.00,
  `pagibig_deduction` decimal(10,2) DEFAULT 0.00,
  `withholding_tax` decimal(10,2) DEFAULT 0.00,
  `loan_deduction` decimal(10,2) DEFAULT 0.00,
  `absence_deduction` decimal(10,2) DEFAULT 0.00,
  `total_deductions` decimal(10,2) DEFAULT NULL,
  `net_pay` decimal(10,2) DEFAULT NULL,
  `status` enum('draft','processed','paid') DEFAULT 'draft',
  `processed_by` int(11) DEFAULT NULL,
  `processed_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblpayslip`
--

CREATE TABLE `tblpayslip` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `payroll_id` int(11) NOT NULL,
  `payslip_number` varchar(50) NOT NULL,
  `generated_date` date DEFAULT NULL,
  `downloaded` tinyint(4) DEFAULT 0,
  `emailed` tinyint(4) DEFAULT 0,
  `pdf_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblphilhealth_contributions`
--

CREATE TABLE `tblphilhealth_contributions` (
  `id` int(11) NOT NULL,
  `salary_range_min` decimal(10,2) DEFAULT NULL,
  `salary_range_max` decimal(10,2) DEFAULT NULL,
  `monthly_premium` decimal(10,2) DEFAULT NULL,
  `employee_share` decimal(10,2) DEFAULT NULL,
  `employer_share` decimal(10,2) DEFAULT NULL,
  `effective_year` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblsss_contributions`
--

CREATE TABLE `tblsss_contributions` (
  `id` int(11) NOT NULL,
  `salary_range_min` decimal(10,2) DEFAULT NULL,
  `salary_range_max` decimal(10,2) DEFAULT NULL,
  `employee_share` decimal(10,2) DEFAULT NULL,
  `employer_share` decimal(10,2) DEFAULT NULL,
  `monthly_contribution` decimal(10,2) DEFAULT NULL,
  `effective_year` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblusers`
--

CREATE TABLE `tblusers` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `cname` varchar(100) NOT NULL,
  `roleID` int(11) NOT NULL COMMENT '1=ADMIN, 2=HR',
  `isActive` tinyint(4) DEFAULT 1,
  `email` varchar(100) DEFAULT NULL,
  `contact_no` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `tblusers`
--

INSERT INTO `tblusers` (`id`, `username`, `password`, `cname`, `roleID`, `isActive`, `email`, `contact_no`, `created_at`) VALUES
(1, 'lumbres', '123456', 'REYNALDO LUMBRES', 1, 1, 'reynaldolumbres20@gmail.com', '09287003273', '2026-03-26 10:31:50');

-- --------------------------------------------------------

--
-- Table structure for table `tbluser_logs`
--

CREATE TABLE `tbluser_logs` (
  `id` int(11) NOT NULL,
  `username` varchar(50) DEFAULT NULL,
  `user_action` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `tbluser_logs`
--

INSERT INTO `tbluser_logs` (`id`, `username`, `user_action`, `created_at`) VALUES
(1, 'admin', 'FAILED LOGIN', '2026-03-26 10:30:17'),
(2, 'admin', 'FAILED LOGIN', '2026-03-26 10:30:29'),
(3, 'lumbres', 'FAILED LOGIN', '2026-03-26 10:32:00'),
(4, 'lumbres', 'FAILED LOGIN', '2026-03-26 10:32:52');

-- --------------------------------------------------------

--
-- Table structure for table `tblwithholding_tax`
--

CREATE TABLE `tblwithholding_tax` (
  `id` int(11) NOT NULL,
  `salary_range_min` decimal(10,2) DEFAULT NULL,
  `salary_range_max` decimal(10,2) DEFAULT NULL,
  `base_tax` decimal(10,2) DEFAULT NULL,
  `percentage_over` decimal(5,2) DEFAULT NULL,
  `effective_year` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `payslip`
--
ALTER TABLE `payslip`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tblattendance`
--
ALTER TABLE `tblattendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_attendance` (`employee_id`,`date`);

--
-- Indexes for table `tblcompany_settings`
--
ALTER TABLE `tblcompany_settings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `updated_by` (`updated_by`);

--
-- Indexes for table `tblemployees`
--
ALTER TABLE `tblemployees`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `employee_no` (`employee_no`);

--
-- Indexes for table `tblemployee_excel_imports`
--
ALTER TABLE `tblemployee_excel_imports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `imported_by` (`imported_by`);

--
-- Indexes for table `tblholidays`
--
ALTER TABLE `tblholidays`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_holiday` (`holiday_date`);

--
-- Indexes for table `tblleaves`
--
ALTER TABLE `tblleaves`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `approved_by` (`approved_by`);

--
-- Indexes for table `tblleave_balances`
--
ALTER TABLE `tblleave_balances`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_balance` (`employee_id`,`year`);

--
-- Indexes for table `tblloans`
--
ALTER TABLE `tblloans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `approved_by` (`approved_by`);

--
-- Indexes for table `tblnight_differential_settings`
--
ALTER TABLE `tblnight_differential_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tblovertime_settings`
--
ALTER TABLE `tblovertime_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tblpagibig_contributions`
--
ALTER TABLE `tblpagibig_contributions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tblpayroll`
--
ALTER TABLE `tblpayroll`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `processed_by` (`processed_by`);

--
-- Indexes for table `tblpayslip`
--
ALTER TABLE `tblpayslip`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `payslip_number` (`payslip_number`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `payroll_id` (`payroll_id`);

--
-- Indexes for table `tblphilhealth_contributions`
--
ALTER TABLE `tblphilhealth_contributions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tblsss_contributions`
--
ALTER TABLE `tblsss_contributions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tblusers`
--
ALTER TABLE `tblusers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `tbluser_logs`
--
ALTER TABLE `tbluser_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tblwithholding_tax`
--
ALTER TABLE `tblwithholding_tax`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `payslip`
--
ALTER TABLE `payslip`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tblattendance`
--
ALTER TABLE `tblattendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tblcompany_settings`
--
ALTER TABLE `tblcompany_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tblemployees`
--
ALTER TABLE `tblemployees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tblemployee_excel_imports`
--
ALTER TABLE `tblemployee_excel_imports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tblholidays`
--
ALTER TABLE `tblholidays`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tblleaves`
--
ALTER TABLE `tblleaves`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tblleave_balances`
--
ALTER TABLE `tblleave_balances`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tblloans`
--
ALTER TABLE `tblloans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tblnight_differential_settings`
--
ALTER TABLE `tblnight_differential_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tblovertime_settings`
--
ALTER TABLE `tblovertime_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `tblpagibig_contributions`
--
ALTER TABLE `tblpagibig_contributions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tblpayroll`
--
ALTER TABLE `tblpayroll`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tblpayslip`
--
ALTER TABLE `tblpayslip`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tblphilhealth_contributions`
--
ALTER TABLE `tblphilhealth_contributions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tblsss_contributions`
--
ALTER TABLE `tblsss_contributions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tblusers`
--
ALTER TABLE `tblusers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tbluser_logs`
--
ALTER TABLE `tbluser_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `tblwithholding_tax`
--
ALTER TABLE `tblwithholding_tax`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `tblattendance`
--
ALTER TABLE `tblattendance`
  ADD CONSTRAINT `tblattendance_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `tblemployees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tblcompany_settings`
--
ALTER TABLE `tblcompany_settings`
  ADD CONSTRAINT `tblcompany_settings_ibfk_1` FOREIGN KEY (`updated_by`) REFERENCES `tblusers` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `tblemployee_excel_imports`
--
ALTER TABLE `tblemployee_excel_imports`
  ADD CONSTRAINT `tblemployee_excel_imports_ibfk_1` FOREIGN KEY (`imported_by`) REFERENCES `tblusers` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `tblleaves`
--
ALTER TABLE `tblleaves`
  ADD CONSTRAINT `tblleaves_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `tblemployees` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tblleaves_ibfk_2` FOREIGN KEY (`approved_by`) REFERENCES `tblusers` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `tblleave_balances`
--
ALTER TABLE `tblleave_balances`
  ADD CONSTRAINT `tblleave_balances_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `tblemployees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tblloans`
--
ALTER TABLE `tblloans`
  ADD CONSTRAINT `tblloans_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `tblemployees` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tblloans_ibfk_2` FOREIGN KEY (`approved_by`) REFERENCES `tblusers` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `tblpayroll`
--
ALTER TABLE `tblpayroll`
  ADD CONSTRAINT `tblpayroll_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `tblemployees` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tblpayroll_ibfk_2` FOREIGN KEY (`processed_by`) REFERENCES `tblusers` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `tblpayslip`
--
ALTER TABLE `tblpayslip`
  ADD CONSTRAINT `tblpayslip_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `tblemployees` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tblpayslip_ibfk_2` FOREIGN KEY (`payroll_id`) REFERENCES `tblpayroll` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
