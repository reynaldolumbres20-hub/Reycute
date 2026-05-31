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

// Get all employees for dropdown
$employees = [];
$query = "SELECT id, employee_no, firstname, lastname FROM tblemployees WHERE status = 'active' ORDER BY firstname ASC";
$stmt = $db->prepare($query);
$stmt->execute();
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get payroll periods for dropdown
$payroll_periods = [];
$query = "SELECT DISTINCT payroll_period, period_start, period_end, id FROM tblpayroll WHERE status = 'processed' ORDER BY period_start DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$payroll_periods = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle Generate Payslip
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['generate_payslip'])) {
    $employee_id = $_POST['employee_id'];
    $payroll_id = $_POST['payroll_id'];
    $payslip_number = 'PS-' . date('Ymd') . '-' . $employee_id . '-' . rand(100, 999);
    
    // Check if payslip already exists
    $check = "SELECT id FROM tblpayslip WHERE employee_id = :employee_id AND payroll_id = :payroll_id";
    $check_stmt = $db->prepare($check);
    $check_stmt->bindParam(':employee_id', $employee_id);
    $check_stmt->bindParam(':payroll_id', $payroll_id);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() == 0) {
        $query = "INSERT INTO tblpayslip (employee_id, payroll_id, payslip_number, generated_date) 
                  VALUES (:employee_id, :payroll_id, :payslip_number, CURDATE())";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':employee_id', $employee_id);
        $stmt->bindParam(':payroll_id', $payroll_id);
        $stmt->bindParam(':payslip_number', $payslip_number);
        
        if ($stmt->execute()) {
            $success = "✅ Payslip generated successfully!";
        } else {
            $error = "❌ Failed to generate payslip!";
        }
    } else {
        $error = "⚠️ Payslip already exists for this payroll period!";
    }
}

// Get all payslips with details
$payslips = [];
$query = "SELECT ps.*, e.firstname, e.lastname, e.employee_no, e.position, e.department,
          pay.payroll_period, pay.period_start, pay.period_end, pay.basic_salary, 
          pay.overtime_pay, pay.night_diff_pay, pay.holiday_pay, pay.allowances,
          pay.gross_pay, pay.sss_deduction, pay.philhealth_deduction, 
          pay.pagibig_deduction, pay.withholding_tax, pay.loan_deduction,
          pay.absence_deduction, pay.total_deductions, pay.net_pay
          FROM tblpayslip ps
          JOIN tblemployees e ON ps.employee_id = e.id
          JOIN tblpayroll pay ON ps.payroll_id = pay.id
          ORDER BY ps.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$payslips = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payslip | Payroll Pro</title>
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

        /* Buttons */
        .btn-primary {
            background: #000000;
            color: #ffffff;
            border: none;
            padding: 12px 24px;
            border-radius: 40px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s;
        }

        .btn-primary:hover {
            background: #333333;
            transform: translateY(-2px);
        }

        /* Cards */
        .card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 24px;
            overflow: hidden;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            transition: all 0.3s;
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -12px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            padding: 20px 24px;
            border-bottom: 1px solid #e5e7eb;
        }

        .card-header h3 {
            font-size: 16px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
            color: #000000;
        }

        .card-body {
            padding: 24px;
        }

        /* Form Elements */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #374151;
            font-size: 13px;
        }

        .form-group select,
        .form-group input {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            background: #ffffff;
            transition: all 0.3s;
        }

        .form-group select:focus,
        .form-group input:focus {
            outline: none;
            border-color: #000000;
            box-shadow: 0 0 0 2px rgba(0,0,0,0.05);
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
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

        /* Action Buttons */
        .btn-view, .btn-print {
            padding: 6px 14px;
            border-radius: 12px;
            text-decoration: none;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin: 0 3px;
            font-weight: 500;
            transition: all 0.3s;
        }

        .btn-view {
            background: #f5f5f5;
            color: #000000;
            border: 1px solid #e5e7eb;
        }

        .btn-view:hover {
            background: #000000;
            color: #ffffff;
            border-color: #000000;
        }

        .btn-print {
            background: #f5f5f5;
            color: #000000;
            border: 1px solid #e5e7eb;
        }

        .btn-print:hover {
            background: #000000;
            color: #ffffff;
            border-color: #000000;
        }

        /* Alerts */
        .alert {
            padding: 14px 18px;
            border-radius: 16px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
        }

        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
            border-left: 3px solid #2e7d32;
        }

        .alert-error {
            background: #ffebee;
            color: #c62828;
            border-left: 3px solid #c62828;
        }

        /* Payslip Modal */
        .payslip-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 3000;
            justify-content: center;
            align-items: center;
        }

        .payslip-content {
            background: #ffffff;
            border-radius: 28px;
            width: 90%;
            max-width: 500px;
            max-height: 85vh;
            overflow-y: auto;
            border: 1px solid #e5e7eb;
        }

        .payslip-header {
            background: #000000;
            padding: 20px;
            text-align: center;
            color: white;
            border-radius: 28px 28px 0 0;
        }

        .payslip-header h3 {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .payslip-header p {
            font-size: 12px;
            opacity: 0.8;
        }

        .payslip-body {
            padding: 24px;
        }

        .payslip-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e5e7eb;
            font-size: 13px;
        }

        .payslip-total {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 16px;
            margin-top: 15px;
        }

        .text-success {
            color: #2e7d32;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .main-content {
                padding: 20px;
            }
            .form-row {
                grid-template-columns: 1fr;
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
                <a href="payslip.php" class="nav-link active">
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
                <a href="reports.php" class="nav-link">
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
            <h1>Payslip Management</h1>
            <p>Generate and manage employee payslips</p>
        </div>

        <?php if(isset($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <?php if(isset($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <!-- Generate Payslip Form -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-plus-circle"></i> Generate Payslip</h3>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Select Employee *</label>
                            <select name="employee_id" required>
                                <option value="">-- Select Employee --</option>
                                <?php foreach($employees as $emp): ?>
                                <option value="<?php echo $emp['id']; ?>"><?php echo $emp['employee_no'] . ' - ' . $emp['firstname'] . ' ' . $emp['lastname']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Select Payroll Period *</label>
                            <select name="payroll_id" required>
                                <option value="">-- Select Period --</option>
                                <?php foreach($payroll_periods as $pp): ?>
                                <option value="<?php echo $pp['id']; ?>"><?php echo $pp['payroll_period']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <button type="submit" name="generate_payslip" class="btn-primary">
                        <i class="fas fa-file-invoice"></i> Generate Payslip
                    </button>
                </form>
            </div>
        </div>

        <!-- Payslip List -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-list"></i> Generated Payslips</h3>
            </div>
            <div style="overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Payslip #</th>
                            <th>Employee</th>
                            <th>Payroll Period</th>
                            <th>Gross Pay</th>
                            <th>Net Pay</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </thead>
                    <tbody>
                        <?php if(count($payslips) > 0): ?>
                            <?php foreach($payslips as $ps): ?>
                            <tr>
                                <td><strong><?php echo $ps['payslip_number']; ?></strong>29
                                <td>
                                    <?php echo $ps['firstname'] . ' ' . $ps['lastname']; ?>
                                    <br><small style="color:#6b7280;"><?php echo $ps['employee_no']; ?></small>
                                29
                                <td><?php echo $ps['payroll_period']; ?>29
                                <td>₱<?php echo number_format($ps['gross_pay'], 2); ?>29
                                <td><strong class="text-success">₱<?php echo number_format($ps['net_pay'], 2); ?></strong>29
                                <td><?php echo date('M d, Y', strtotime($ps['generated_date'])); ?>29
                                <td>
                                    <button class="btn-view" onclick="viewPayslip(<?php echo $ps['id']; ?>)">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                    <button class="btn-print" onclick="printPayslip(<?php echo $ps['id']; ?>)">
                                        <i class="fas fa-print"></i> Print
                                    </button>
                                29
                            用
                            <?php endforeach; ?>
                        <?php else: ?>
                             Bon
                                <td colspan="7" style="text-align: center; padding: 40px; color: #6b7280;">
                                    <i class="fas fa-file-invoice" style="font-size: 48px; margin-bottom: 10px; opacity: 0.5;"></i><br>
                                    No payslips generated yet. Generate a payslip to see records.
                                  </td>
                              </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Payslip Modal -->
    <div id="payslipModal" class="payslip-modal">
        <div class="payslip-content">
            <div class="payslip-header">
                <h3><i class="fas fa-file-invoice"></i> Employee Payslip</h3>
                <p id="payslipCompany">LUMBRES PAYROLL SYSTEM</p>
            </div>
            <div class="payslip-body" id="payslipBody"></div>
        </div>
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

        // View Payslip
        function viewPayslip(id) {
            var payslips = <?php echo json_encode($payslips); ?>;
            var ps = payslips.find(p => p.id == id);
            if (ps) {
                var html = `
                    <div style="text-align:center; margin-bottom:20px;">
                        <h4 style="color:#000000;">${ps.firstname} ${ps.lastname}</h4>
                        <p style="color:#6b7280;">${ps.position} | ${ps.department}</p>
                        <p style="color:#6b7280;">Employee No: ${ps.employee_no}</p>
                    </div>
                    <div class="payslip-item"><span>Payroll Period:</span><span>${ps.payroll_period}</span></div>
                    <div class="payslip-item"><span>Payslip Number:</span><span>${ps.payslip_number}</span></div>
                    <div class="payslip-item"><span>Basic Salary:</span><span>₱${Number(ps.basic_salary).toLocaleString()}</span></div>
                    <div class="payslip-item"><span>Overtime Pay:</span><span>₱${Number(ps.overtime_pay).toLocaleString()}</span></div>
                    <div class="payslip-item"><span>Allowances:</span><span>₱${Number(ps.allowances).toLocaleString()}</span></div>
                    <div class="payslip-item"><span style="font-weight:700;">Gross Pay:</span><span style="font-weight:700;">₱${Number(ps.gross_pay).toLocaleString()}</span></div>
                    <div style="margin-top:15px;"></div>
                    <div class="payslip-item"><span>SSS Deduction:</span><span>₱${Number(ps.sss_deduction).toLocaleString()}</span></div>
                    <div class="payslip-item"><span>PhilHealth:</span><span>₱${Number(ps.philhealth_deduction).toLocaleString()}</span></div>
                    <div class="payslip-item"><span>Pag-IBIG:</span><span>₱${Number(ps.pagibig_deduction).toLocaleString()}</span></div>
                    <div class="payslip-item"><span>Withholding Tax:</span><span>₱${Number(ps.withholding_tax).toLocaleString()}</span></div>
                    <div class="payslip-item"><span>Loan Deduction:</span><span>₱${Number(ps.loan_deduction).toLocaleString()}</span></div>
                    <div class="payslip-item"><span>Absence Deduction:</span><span>₱${Number(ps.absence_deduction).toLocaleString()}</span></div>
                    <div class="payslip-total">
                        <div class="payslip-item"><span style="font-weight:800;">Total Deductions:</span><span style="font-weight:800;">₱${Number(ps.total_deductions).toLocaleString()}</span></div>
                        <div class="payslip-item"><span style="font-weight:800;color:#2e7d32;">NET PAY:</span><span style="font-weight:800;color:#2e7d32;font-size:20px;">₱${Number(ps.net_pay).toLocaleString()}</span></div>
                    </div>
                `;
                document.getElementById('payslipBody').innerHTML = html;
                document.getElementById('payslipModal').style.display = 'flex';
            }
        }

        // Print Payslip
        function printPayslip(id) {
            var payslips = <?php echo json_encode($payslips); ?>;
            var ps = payslips.find(p => p.id == id);
            if (ps) {
                var printWindow = window.open('', '_blank');
                printWindow.document.write(`
                    <html>
                    <head>
                        <title>Payslip - ${ps.firstname} ${ps.lastname}</title>
                        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
                        <style>
                            body { font-family: 'Inter', sans-serif; padding: 40px; max-width: 500px; margin: 0 auto; background: white; }
                            .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #000000; padding-bottom: 20px; }
                            .header h2 { color: #000000; }
                            .item { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #e5e7eb; }
                            .total { background: #f5f5f5; padding: 15px; margin-top: 15px; border-radius: 12px; }
                            .net-pay { font-size: 24px; color: #2e7d32; font-weight: 800; }
                            .text-center { text-align: center; }
                            .text-muted { color: #6b7280; }
                        </style>
                    </head>
                    <body>
                        <div class="header">
                            <h2>LUMBRES PAYROLL SYSTEM</h2>
                            <p class="text-muted">Employee Payslip</p>
                        </div>
                        <div class="text-center" style="margin-bottom:20px;">
                            <h3>${ps.firstname} ${ps.lastname}</h3>
                            <p class="text-muted">${ps.position} | ${ps.department}</p>
                            <p class="text-muted">Employee No: ${ps.employee_no}</p>
                        </div>
                        <div class="item"><span>Payroll Period:</span><span>${ps.payroll_period}</span></div>
                        <div class="item"><span>Payslip Number:</span><span>${ps.payslip_number}</span></div>
                        <div class="item"><span>Basic Salary:</span><span>₱${Number(ps.basic_salary).toLocaleString()}</span></div>
                        <div class="item"><span>Overtime Pay:</span><span>₱${Number(ps.overtime_pay).toLocaleString()}</span></div>
                        <div class="item"><span>Gross Pay:</span><span>₱${Number(ps.gross_pay).toLocaleString()}</span></div>
                        <div class="item"><span>SSS:</span><span>₱${Number(ps.sss_deduction).toLocaleString()}</span></div>
                        <div class="item"><span>PhilHealth:</span><span>₱${Number(ps.philhealth_deduction).toLocaleString()}</span></div>
                        <div class="item"><span>Pag-IBIG:</span><span>₱${Number(ps.pagibig_deduction).toLocaleString()}</span></div>
                        <div class="item"><span>Withholding Tax:</span><span>₱${Number(ps.withholding_tax).toLocaleString()}</span></div>
                        <div class="total">
                            <div class="item"><strong>Total Deductions:</strong><strong>₱${Number(ps.total_deductions).toLocaleString()}</strong></div>
                            <div class="item"><strong>NET PAY:</strong><span class="net-pay">₱${Number(ps.net_pay).toLocaleString()}</span></div>
                        </div>
                        <div class="text-center" style="margin-top:30px; color:#6b7280;">
                            <p>This is a computer-generated payslip. No signature required.</p>
                            <p>Generated on: ${new Date().toLocaleString()}</p>
                        </div>
                        <script>window.onload = function() { window.print(); setTimeout(() => window.close(), 500); }<\/script>
                    </body>
                    </html>
                `);
                printWindow.document.close();
            }
        }

        // Close modal on click outside
        window.onclick = function(event) {
            if (event.target.classList.contains('payslip-modal')) {
                document.getElementById('payslipModal').style.display = 'none';
            }
        }

        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.transition = 'opacity 0.5s';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                }, 4000);
            });
        }, 1000);
    </script>
</body>
</html>