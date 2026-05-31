<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$complete_name = $_SESSION['cname'] ?? 'Administrator';
$user_role_id = $_SESSION['roleID'] ?? 1;
$roleNames = [1 => 'ADMIN', 2 => 'HR'];
$user_role = $roleNames[$user_role_id] ?? 'ADMIN';

// Get dashboard stats for notification badge
$stats = [];
$query = "SELECT COUNT(*) as total FROM tblleaves WHERE status = 'pending'";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['pending_leaves'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$query = "SELECT COUNT(*) as total FROM tblloans WHERE status = 'pending'";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['pending_loans'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get date range filter
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'payroll';

// Get payroll summary
$payroll_summary = [];
$query = "SELECT p.*, e.firstname, e.lastname, e.employee_no, e.position, e.department
          FROM tblpayroll p
          JOIN tblemployees e ON p.employee_id = e.id
          WHERE p.period_start BETWEEN :date_from AND :date_to
          ORDER BY p.created_at DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(':date_from', $date_from);
$stmt->bindParam(':date_to', $date_to);
$stmt->execute();
$payroll_summary = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get attendance summary
$attendance_summary = [];
$query = "SELECT a.*, e.firstname, e.lastname, e.employee_no, e.department
          FROM tblattendance a
          JOIN tblemployees e ON a.employee_id = e.id
          WHERE a.date BETWEEN :date_from AND :date_to
          ORDER BY a.date DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(':date_from', $date_from);
$stmt->bindParam(':date_to', $date_to);
$stmt->execute();
$attendance_summary = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get leave summary
$leave_summary = [];
$query = "SELECT l.*, e.firstname, e.lastname, e.employee_no
          FROM tblleaves l
          JOIN tblemployees e ON l.employee_id = e.id
          WHERE l.created_at BETWEEN :date_from AND :date_to
          ORDER BY l.created_at DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(':date_from', $date_from);
$stmt->bindParam(':date_to', $date_to);
$stmt->execute();
$leave_summary = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get loan summary
$loan_summary = [];
$query = "SELECT l.*, e.firstname, e.lastname, e.employee_no
          FROM tblloans l
          JOIN tblemployees e ON l.employee_id = e.id
          WHERE l.created_at BETWEEN :date_from AND :date_to
          ORDER BY l.created_at DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(':date_from', $date_from);
$stmt->bindParam(':date_to', $date_to);
$stmt->execute();
$loan_summary = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals
$total_gross = array_sum(array_column($payroll_summary, 'gross_pay'));
$total_net = array_sum(array_column($payroll_summary, 'net_pay'));
$total_sss = array_sum(array_column($payroll_summary, 'sss_deduction'));
$total_philhealth = array_sum(array_column($payroll_summary, 'philhealth_deduction'));
$total_pagibig = array_sum(array_column($payroll_summary, 'pagibig_deduction'));
$total_tax = array_sum(array_column($payroll_summary, 'withholding_tax'));

$total_present = 0;
$total_absent = 0;
$total_late = 0;
foreach ($attendance_summary as $att) {
    if ($att['status'] == 'present') $total_present++;
    if ($att['status'] == 'absent') $total_absent++;
    if ($att['status'] == 'late') $total_late++;
}

$total_leaves = count($leave_summary);
$pending_leaves = count(array_filter($leave_summary, function($l) { return $l['status'] == 'pending'; }));
$approved_leaves = count(array_filter($leave_summary, function($l) { return $l['status'] == 'approved'; }));

$total_loans = count($loan_summary);
$pending_loans = count(array_filter($loan_summary, function($l) { return $l['status'] == 'pending'; }));
$approved_loans = count(array_filter($loan_summary, function($l) { return $l['status'] == 'approved'; }));
$total_loan_amount = array_sum(array_column($loan_summary, 'amount'));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports | Payroll Pro</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f5f5f5;
            color: #111827;
            overflow-x: hidden;
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        ::-webkit-scrollbar-track {
            background: #e5e7eb;
        }

        ::-webkit-scrollbar-thumb {
            background: #000000;
            border-radius: 3px;
        }

        /* ========== TOP NAVBAR ========== */
        .top-navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 70px;
            background: #ffffff;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 40px;
            z-index: 1000;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .navbar-left {
            display: flex;
            align-items: center;
            gap: 30px;
        }

        .menu-toggle {
            width: 44px;
            height: 44px;
            background: #f5f5f5;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            font-size: 18px;
            cursor: pointer;
            color: #000000;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .menu-toggle:hover {
            background: #000000;
            color: #ffffff;
            transform: scale(1.02);
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logo-icon {
            width: 36px;
            height: 36px;
            background: #000000;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .logo-icon i {
            font-size: 18px;
            color: #ffffff;
        }

        .logo h2 {
            font-size: 20px;
            font-weight: 800;
            color: #000000;
        }

        .logo span {
            font-size: 9px;
            color: #6b7280;
            margin-left: 4px;
        }

        .search-bar {
            display: flex;
            align-items: center;
            background: #f5f5f5;
            border: 1px solid #e5e7eb;
            border-radius: 40px;
            padding: 8px 18px;
            gap: 10px;
            width: 280px;
            transition: all 0.3s;
        }

        .search-bar:focus-within {
            border-color: #000000;
            background: #ffffff;
        }

        .search-bar i {
            color: #6b7280;
            font-size: 14px;
        }

        .search-bar input {
            border: none;
            background: none;
            outline: none;
            width: 100%;
            font-size: 13px;
            color: #000000;
        }

        .navbar-right {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .notification-btn {
            width: 44px;
            height: 44px;
            background: #f5f5f5;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            font-size: 18px;
            cursor: pointer;
            color: #000000;
            transition: all 0.3s;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .notification-btn:hover {
            background: #000000;
            color: #ffffff;
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #000000;
            color: #ffffff;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            padding: 5px 12px;
            border-radius: 40px;
            transition: all 0.3s;
        }

        .user-profile:hover {
            background: #f5f5f5;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: #000000;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            font-weight: 700;
            font-size: 16px;
        }

        .user-info h4 {
            font-size: 14px;
            font-weight: 700;
            color: #000000;
        }

        .user-info p {
            font-size: 11px;
            color: #6b7280;
        }

        /* ========== SIDEBAR ========== */
        .sidebar {
            position: fixed;
            top: 70px;
            left: 0;
            bottom: 0;
            width: 280px;
            background: #ffffff;
            border-right: 1px solid #e5e7eb;
            transform: translateX(-100%);
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 999;
            overflow-y: auto;
        }

        .sidebar.open {
            transform: translateX(0);
        }

        .sidebar-nav {
            padding: 20px;
        }

        .nav-item {
            margin-bottom: 6px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: #4b5563;
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.3s;
            font-size: 14px;
            font-weight: 500;
        }

        .nav-link i {
            width: 20px;
            font-size: 16px;
            color: #9ca3af;
        }

        .nav-link:hover {
            background: #f5f5f5;
            color: #000000;
        }

        .nav-link:hover i {
            color: #000000;
        }

        .nav-link.active {
            background: #000000;
            color: #ffffff;
        }

        .nav-link.active i {
            color: #ffffff;
        }

        .nav-divider {
            height: 1px;
            background: #e5e7eb;
            margin: 16px 0;
        }

        /* ========== MAIN CONTENT ========== */
        .main-content {
            margin-left: 0;
            margin-top: 70px;
            padding: 30px;
            transition: margin-left 0.3s;
        }

        .main-content.sidebar-open {
            margin-left: 280px;
        }

        .sidebar-overlay {
            position: fixed;
            top: 70px;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.3);
            z-index: 998;
            display: none;
        }

        .sidebar-overlay.active {
            display: block;
        }

        /* Page Header */
        .page-header {
            margin-bottom: 30px;
        }

        .page-header h1 {
            font-size: 28px;
            font-weight: 800;
            color: #000000;
        }

        .page-header p {
            color: #6b7280;
            font-size: 14px;
            margin-top: 5px;
        }

        /* Filter Form */
        .filter-form {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 24px;
            padding: 24px;
            margin-bottom: 24px;
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            align-items: flex-end;
        }

        .filter-group {
            flex: 1;
            min-width: 160px;
        }

        .filter-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #374151;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .filter-group input,
        .filter-group select {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            background: #ffffff;
            transition: all 0.3s;
        }

        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: #000000;
            box-shadow: 0 0 0 2px rgba(0,0,0,0.05);
        }

        /* Buttons */
        .btn-primary {
            background: #000000;
            color: #ffffff;
            border: none;
            padding: 10px 20px;
            border-radius: 40px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            font-size: 13px;
            transition: all 0.3s;
        }

        .btn-primary:hover {
            background: #333333;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #f5f5f5;
            color: #000000;
            border: 1px solid #e5e7eb;
            padding: 10px 20px;
            border-radius: 40px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            font-size: 13px;
            transition: all 0.3s;
        }

        .btn-secondary:hover {
            background: #000000;
            color: #ffffff;
            border-color: #000000;
        }

        /* Stats Summary */
        .stats-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-box {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 20px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s;
        }

        .stat-box:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.05);
        }

        .stat-box .value {
            font-size: 28px;
            font-weight: 800;
            color: #000000;
        }

        .stat-box .label {
            font-size: 12px;
            color: #6b7280;
            margin-top: 8px;
        }

        /* Cards */
        .card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 24px;
            overflow: hidden;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .card-header {
            padding: 20px 24px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-header h3 {
            font-size: 16px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
            color: #000000;
        }

        .card-header span {
            font-size: 13px;
            color: #6b7280;
        }

        /* Data Table */
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th,
        .data-table td {
            padding: 14px 20px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }

        .data-table th {
            background: #f5f5f5;
            font-weight: 600;
            color: #6b7280;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .data-table td {
            font-size: 13px;
            color: #374151;
        }

        .table-responsive {
            overflow-x: auto;
        }

        /* Badges */
        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 600;
            display: inline-block;
        }

        .badge-present {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .badge-absent {
            background: #ffebee;
            color: #c62828;
        }

        .badge-late {
            background: #fff3e0;
            color: #ef6c00;
        }

        .badge-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-approved {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .badge-paid {
            background: #e3f2fd;
            color: #1565c0;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .main-content {
                padding: 20px;
            }
            .filter-form {
                flex-direction: column;
            }
            .stats-summary {
                grid-template-columns: 1fr 1fr;
            }
            .data-table th,
            .data-table td {
                padding: 10px 12px;
                font-size: 11px;
            }
            .search-bar {
                display: none;
            }
            .user-info {
                display: none;
            }
            .page-header h1 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <!-- TOP NAVBAR -->
    <div class="top-navbar">
        <div class="navbar-left">
            <button class="menu-toggle" id="menuToggle">
                <i class="fas fa-bars"></i>
            </button>
            <div class="logo">
                <div class="logo-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div>
                    <h2>LUMBRES PAYROLL <span>SYSTEM</span></h2>
                </div>
            </div>
            <div class="search-bar">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Search employees, reports...">
            </div>
        </div>
        <div class="navbar-right">
            <div class="notification-btn" id="notificationBtn">
                <i class="fas fa-bell"></i>
                <?php if(isset($stats['pending_leaves']) && ($stats['pending_leaves'] > 0 || $stats['pending_loans'] > 0)): ?>
                <span class="notification-badge"><?php echo $stats['pending_leaves'] + $stats['pending_loans']; ?></span>
                <?php endif; ?>
            </div>
            <div class="user-profile" id="userProfile">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($complete_name, 0, 1)); ?>
                </div>
                <div class="user-info">
                    <h4><?php echo htmlspecialchars($complete_name); ?></h4>
                    <p><?php echo $user_role; ?></p>
                </div>
                <i class="fas fa-chevron-down" style="font-size: 10px; color: #9ca3af;"></i>
            </div>
        </div>
    </div>

    <!-- SIDEBAR -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-nav">
            <div class="nav-item">
                <a href="dashboard.php" class="nav-link">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="employees.php" class="nav-link">
                    <i class="fas fa-users"></i>
                    <span>Employees</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="attendance.php" class="nav-link">
                    <i class="fas fa-calendar-check"></i>
                    <span>Attendance</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="payroll.php" class="nav-link">
                    <i class="fas fa-money-bill-wave"></i>
                    <span>Payroll</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="payslip.php" class="nav-link">
                    <i class="fas fa-file-invoice"></i>
                    <span>Payslip</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="leave.php" class="nav-link">
                    <i class="fas fa-umbrella-beach"></i>
                    <span>Leave</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="loans.php" class="nav-link">
                    <i class="fas fa-hand-holding-usd"></i>
                    <span>Loans</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="reports.php" class="nav-link active">
                    <i class="fas fa-chart-line"></i>
                    <span>Reports</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="holidays.php" class="nav-link">
                    <i class="fas fa-calendar-day"></i>
                    <span>Holidays</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="settings.php" class="nav-link">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="registration.php" class="nav-link">
                    <i class="fas fa-user-plus"></i>
                    <span>Register User</span>
                </a>
            </div>
            <div class="nav-divider"></div>
            <div class="nav-item">
                <a href="../logout.php" class="nav-link">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
    </div>

    <!-- OVERLAY -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- MAIN CONTENT -->
    <div class="main-content" id="mainContent">
        <div class="page-header">
            <h1>Reports & Analytics</h1>
            <p>Generate and export reports for payroll, attendance, leave, and loans</p>
        </div>

        <!-- Filter Form -->
        <div class="filter-form">
            <div class="filter-group">
                <label>Date From</label>
                <input type="date" id="date_from" value="<?php echo $date_from; ?>">
            </div>
            <div class="filter-group">
                <label>Date To</label>
                <input type="date" id="date_to" value="<?php echo $date_to; ?>">
            </div>
            <div class="filter-group">
                <label>Report Type</label>
                <select id="report_type">
                    <option value="payroll" <?php echo $report_type == 'payroll' ? 'selected' : ''; ?>>Payroll Report</option>
                    <option value="attendance" <?php echo $report_type == 'attendance' ? 'selected' : ''; ?>>Attendance Report</option>
                    <option value="leave" <?php echo $report_type == 'leave' ? 'selected' : ''; ?>>Leave Report</option>
                    <option value="loan" <?php echo $report_type == 'loan' ? 'selected' : ''; ?>>Loan Report</option>
                </select>
            </div>
            <div class="filter-group">
                <button class="btn-primary" onclick="applyFilter()">
                    <i class="fas fa-search"></i> Apply Filter
                </button>
                <button class="btn-secondary" onclick="printReport()">
                    <i class="fas fa-print"></i> Print
                </button>
            </div>
        </div>

        <!-- Stats Summary -->
        <div class="stats-summary">
            <div class="stat-box">
                <div class="value">₱<?php echo number_format($total_gross, 2); ?></div>
                <div class="label">Total Gross Pay</div>
            </div>
            <div class="stat-box">
                <div class="value">₱<?php echo number_format($total_net, 2); ?></div>
                <div class="label">Total Net Pay</div>
            </div>
            <div class="stat-box">
                <div class="value">₱<?php echo number_format($total_sss, 2); ?></div>
                <div class="label">SSS Contributions</div>
            </div>
            <div class="stat-box">
                <div class="value">₱<?php echo number_format($total_philhealth, 2); ?></div>
                <div class="label">PhilHealth</div>
            </div>
            <div class="stat-box">
                <div class="value">₱<?php echo number_format($total_pagibig, 2); ?></div>
                <div class="label">Pag-IBIG</div>
            </div>
            <div class="stat-box">
                <div class="value">₱<?php echo number_format($total_tax, 2); ?></div>
                <div class="label">Withholding Tax</div>
            </div>
        </div>

        <!-- Payroll Report -->
        <?php if($report_type == 'payroll'): ?>
        <div class="card" id="reportContent">
            <div class="card-header">
                <h3><i class="fas fa-file-invoice-dollar"></i> Payroll Summary Report</h3>
                <span><?php echo date('F d, Y', strtotime($date_from)); ?> - <?php echo date('F d, Y', strtotime($date_to)); ?></span>
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Payroll Period</th>
                            <th>Basic Salary</th>
                            <th>Overtime</th>
                            <th>Gross Pay</th>
                            <th>SSS</th>
                            <th>PhilHealth</th>
                            <th>Pag-IBIG</th>
                            <th>Tax</th>
                            <th>Net Pay</th>
                        </thead>
                    <tbody>
                        <?php if(count($payroll_summary) > 0): ?>
                            <?php foreach($payroll_summary as $pay): ?>
                            <tr>
                                <td>
                                    <strong><?php echo $pay['firstname'] . ' ' . $pay['lastname']; ?></strong>
                                    <br><small style="color:#6b7280;"><?php echo $pay['employee_no']; ?></small>
                                29
                                <td><?php echo $pay['payroll_period']; ?>29
                                <td>₱<?php echo number_format($pay['basic_salary'], 2); ?>29
                                <td>₱<?php echo number_format($pay['overtime_pay'], 2); ?>29
                                <td>₱<?php echo number_format($pay['gross_pay'], 2); ?>29
                                <td>₱<?php echo number_format($pay['sss_deduction'], 2); ?>29
                                <td>₱<?php echo number_format($pay['philhealth_deduction'], 2); ?>29
                                <td>₱<?php echo number_format($pay['pagibig_deduction'], 2); ?>29
                                <td>₱<?php echo number_format($pay['withholding_tax'], 2); ?>29
                                <td><strong>₱<?php echo number_format($pay['net_pay'], 2); ?></strong>29
                            用
                            <?php endforeach; ?>
                        <?php else: ?>
                             Bon
                                <td colspan="10" style="text-align: center; padding: 40px; color: #6b7280;">
                                    <i class="fas fa-file-invoice-dollar" style="font-size: 48px; margin-bottom: 10px; opacity: 0.5;"></i><br>
                                    No payroll records found for the selected period.
                                 </td>
                             </tr>
                        <?php endif; ?>
                    </tbody>
                 </div>
        </div>
        <?php endif; ?>

        <!-- Attendance Report -->
        <?php if($report_type == 'attendance'): ?>
        <div class="card" id="reportContent">
            <div class="card-header">
                <h3><i class="fas fa-calendar-check"></i> Attendance Summary Report</h3>
                <span><?php echo date('F d, Y', strtotime($date_from)); ?> - <?php echo date('F d, Y', strtotime($date_to)); ?></span>
            </div>
            <div class="stats-summary" style="margin: 0 24px 24px 24px;">
                <div class="stat-box">
                    <div class="value"><?php echo $total_present; ?></div>
                    <div class="label">Present</div>
                </div>
                <div class="stat-box">
                    <div class="value"><?php echo $total_absent; ?></div>
                    <div class="label">Absent</div>
                </div>
                <div class="stat-box">
                    <div class="value"><?php echo $total_late; ?></div>
                    <div class="label">Late</div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Employee</th>
                            <th>Time In</th>
                            <th>Time Out</th>
                            <th>Hours</th>
                            <th>Status</th>
                        </thead>
                    <tbody>
                        <?php if(count($attendance_summary) > 0): ?>
                            <?php foreach($attendance_summary as $att): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($att['date'])); ?>29
                                <td>
                                    <strong><?php echo $att['firstname'] . ' ' . $att['lastname']; ?></strong>
                                    <br><small style="color:#6b7280;"><?php echo $att['employee_no']; ?></small>
                                29
                                <td><?php echo $att['time_in'] ? date('h:i A', strtotime($att['time_in'])) : '-'; ?>29
                                <td><?php echo $att['time_out'] ? date('h:i A', strtotime($att['time_out'])) : '-'; ?>29
                                <td><?php echo $att['hours_worked'] ? number_format($att['hours_worked'], 2) : '0'; ?>29
                                <td><span class="badge badge-<?php echo $att['status']; ?>"><?php echo strtoupper($att['status']); ?></span>29
                            用
                            <?php endforeach; ?>
                        <?php else: ?>
                             <tr>
                                <td colspan="6" style="text-align: center; padding: 40px; color: #6b7280;">
                                    <i class="fas fa-calendar-check" style="font-size: 48px; margin-bottom: 10px; opacity: 0.5;"></i><br>
                                    No attendance records found for the selected period.
                                 </td>
                             </tr>
                        <?php endif; ?>
                    </tbody>
                 </div>
        </div>
        <?php endif; ?>

        <!-- Leave Report -->
        <?php if($report_type == 'leave'): ?>
        <div class="card" id="reportContent">
            <div class="card-header">
                <h3><i class="fas fa-umbrella-beach"></i> Leave Summary Report</h3>
                <span><?php echo date('F d, Y', strtotime($date_from)); ?> - <?php echo date('F d, Y', strtotime($date_to)); ?></span>
            </div>
            <div class="stats-summary" style="margin: 0 24px 24px 24px;">
                <div class="stat-box">
                    <div class="value"><?php echo $total_leaves; ?></div>
                    <div class="label">Total Leaves</div>
                </div>
                <div class="stat-box">
                    <div class="value"><?php echo $pending_leaves; ?></div>
                    <div class="label">Pending</div>
                </div>
                <div class="stat-box">
                    <div class="value"><?php echo $approved_leaves; ?></div>
                    <div class="label">Approved</div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Leave Type</th>
                            <th>Period</th>
                            <th>Days</th>
                            <th>Reason</th>
                            <th>Status</th>
                        </thead>
                    <tbody>
                        <?php if(count($leave_summary) > 0): ?>
                            <?php foreach($leave_summary as $leave): ?>
                            <tr>
                                <td>
                                    <strong><?php echo $leave['firstname'] . ' ' . $leave['lastname']; ?></strong>
                                    <br><small style="color:#6b7280;"><?php echo $leave['employee_no']; ?></small>
                                29
                                <td><?php echo ucfirst($leave['leave_type']); ?> Leave29
                                <td><?php echo date('M d', strtotime($leave['start_date'])); ?> - <?php echo date('M d', strtotime($leave['end_date'])); ?>29
                                <td><?php echo $leave['days_applied']; ?> days29
                                <td><?php echo $leave['reason']; ?>29
                                <td><span class="badge badge-<?php echo $leave['status']; ?>"><?php echo strtoupper($leave['status']); ?></span>29
                            用
                            <?php endforeach; ?>
                        <?php else: ?>
                             <tr>
                                <td colspan="6" style="text-align: center; padding: 40px; color: #6b7280;">
                                    <i class="fas fa-umbrella-beach" style="font-size: 48px; margin-bottom: 10px; opacity: 0.5;"></i><br>
                                    No leave records found for the selected period.
                                  </tr>
                        <?php endif; ?>
                    </tbody>
                 </div>
        </div>
        <?php endif; ?>

        <!-- Loan Report -->
        <?php if($report_type == 'loan'): ?>
        <div class="card" id="reportContent">
            <div class="card-header">
                <h3><i class="fas fa-hand-holding-usd"></i> Loan Summary Report</h3>
                <span><?php echo date('F d, Y', strtotime($date_from)); ?> - <?php echo date('F d, Y', strtotime($date_to)); ?></span>
            </div>
            <div class="stats-summary" style="margin: 0 24px 24px 24px;">
                <div class="stat-box">
                    <div class="value"><?php echo $total_loans; ?></div>
                    <div class="label">Total Loans</div>
                </div>
                <div class="stat-box">
                    <div class="value">₱<?php echo number_format($total_loan_amount, 2); ?></div>
                    <div class="label">Total Amount</div>
                </div>
                <div class="stat-box">
                    <div class="value"><?php echo $pending_loans; ?></div>
                    <div class="label">Pending</div>
                </div>
                <div class="stat-box">
                    <div class="value"><?php echo $approved_loans; ?></div>
                    <div class="label">Approved</div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Loan Type</th>
                            <th>Amount</th>
                            <th>Terms</th>
                            <th>Monthly</th>
                            <th>Paid</th>
                            <th>Balance</th>
                            <th>Status</th>
                        </thead>
                    <tbody>
                        <?php if(count($loan_summary) > 0): ?>
                            <?php foreach($loan_summary as $loan): ?>
                            <tr>
                                <td>
                                    <strong><?php echo $loan['firstname'] . ' ' . $loan['lastname']; ?></strong>
                                    <br><small style="color:#6b7280;"><?php echo $loan['employee_no']; ?></small>
                                29
                                <td><?php echo ucfirst($loan['loan_type']); ?> Loan29
                                <td>₱<?php echo number_format($loan['amount'], 2); ?>29
                                <td><?php echo $loan['terms']; ?> months29
                                <td>₱<?php echo number_format($loan['monthly_amortization'], 2); ?>29
                                <td>₱<?php echo number_format($loan['total_paid'], 2); ?>29
                                <td>₱<?php echo number_format($loan['balance'], 2); ?>29
                                <td><span class="badge badge-<?php echo $loan['status']; ?>"><?php echo strtoupper($loan['status']); ?></span>29
                            用
                            <?php endforeach; ?>
                        <?php else: ?>
                             <tr>
                                <td colspan="8" style="text-align: center; padding: 40px; color: #6b7280;">
                                    <i class="fas fa-hand-holding-usd" style="font-size: 48px; margin-bottom: 10px; opacity: 0.5;"></i><br>
                                    No loan records found for the selected period.
                                  </tr>
                        <?php endif; ?>
                    </tbody>
                 </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Sidebar Toggle
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const mainContent = document.getElementById('mainContent');

        function toggleSidebar() {
            sidebar.classList.toggle('open');
            sidebarOverlay.classList.toggle('active');
            mainContent.classList.toggle('sidebar-open');
            
            const icon = menuToggle.querySelector('i');
            if (sidebar.classList.contains('open')) {
                icon.classList.remove('fa-bars');
                icon.classList.add('fa-times');
            } else {
                icon.classList.remove('fa-times');
                icon.classList.add('fa-bars');
            }
        }

        menuToggle.addEventListener('click', toggleSidebar);
        sidebarOverlay.addEventListener('click', toggleSidebar);

        // Apply Filter
        function applyFilter() {
            var date_from = document.getElementById('date_from').value;
            var date_to = document.getElementById('date_to').value;
            var report_type = document.getElementById('report_type').value;
            window.location.href = 'reports.php?date_from=' + date_from + '&date_to=' + date_to + '&report_type=' + report_type;
        }

        // Print Report
        function printReport() {
            var printContent = document.getElementById('reportContent').innerHTML;
            var date_from = document.getElementById('date_from').value;
            var date_to = document.getElementById('date_to').value;
            var report_type = document.getElementById('report_type').options[document.getElementById('report_type').selectedIndex].text;
            
            var printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                <head>
                    <title>Payroll Pro - ${report_type}</title>
                    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
                    <style>
                        body { font-family: 'Inter', sans-serif; padding: 40px; background: white; }
                        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #000000; padding-bottom: 20px; }
                        .header h1 { color: #000000; font-size: 24px; }
                        .header p { color: #6b7280; margin-top: 5px; }
                        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                        th, td { padding: 10px; border: 1px solid #e5e7eb; text-align: left; }
                        th { background: #f5f5f5; font-weight: 600; }
                        .footer { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb; color: #6b7280; font-size: 12px; }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <h1>LUMBRES PAYROLL SYSTEM</h1>
                        <p>${report_type}</p>
                        <p>Period: ${new Date(date_from).toLocaleDateString()} - ${new Date(date_to).toLocaleDateString()}</p>
                        <p>Generated on: ${new Date().toLocaleString()}</p>
                    </div>
                    ${printContent}
                    <div class="footer">
                        <p>This is a computer-generated report. No signature required.</p>
                    </div>
                    <script>window.onload = function() { window.print(); setTimeout(() => window.close(), 500); }<\/script>
                </body>
                </html>
            `);
            printWindow.document.close();
        }
    </script>
</body>
</html>