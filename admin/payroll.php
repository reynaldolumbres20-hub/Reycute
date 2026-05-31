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
$user_id = $_SESSION['user_id'];

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

// Function to get SSS Contribution
function getSSSContribution($db, $salary) {
    $query = "SELECT employee_share FROM tblsss_contributions WHERE salary_range_min <= :salary AND salary_range_max >= :salary ORDER BY salary_range_min LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':salary', $salary);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['employee_share'] : 0;
}

function getPhilHealthContribution($db, $salary) {
    $query = "SELECT employee_share FROM tblphilhealth_contributions WHERE salary_range_min <= :salary AND salary_range_max >= :salary ORDER BY salary_range_min LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':salary', $salary);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['employee_share'] : 200;
}

function getPagIBIGContribution($db, $salary) {
    $query = "SELECT employee_share FROM tblpagibig_contributions WHERE salary_range_min <= :salary AND salary_range_max >= :salary ORDER BY salary_range_min LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':salary', $salary);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['employee_share'] : 100;
}

function getWithholdingTax($db, $monthlySalary) {
    $query = "SELECT base_tax, percentage_over FROM tblwithholding_tax WHERE salary_range_min <= :salary AND salary_range_max >= :salary ORDER BY salary_range_min LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':salary', $monthlySalary);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        return $result['base_tax'] + (($monthlySalary - $result['salary_range_min']) * $result['percentage_over']);
    }
    return 0;
}

$employees = [];
$query = "SELECT id, employee_no, firstname, lastname, position, department, salary_rate, daily_rate, hourly_rate FROM tblemployees WHERE status = 'active' ORDER BY firstname ASC";
$stmt = $db->prepare($query);
$stmt->execute();
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

function getAttendanceSummary($db, $employee_id, $period_start, $period_end) {
    $query = "SELECT SUM(hours_worked) as total_hours, COUNT(*) as days_present, 
                     SUM(overtime_hours) as total_overtime, 
                     SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absences,
                     SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as lates
              FROM tblattendance 
              WHERE employee_id = :employee_id AND date BETWEEN :start AND :end";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':employee_id', $employee_id);
    $stmt->bindParam(':start', $period_start);
    $stmt->bindParam(':end', $period_end);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

$payroll_result = null;
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['process_payroll'])) {
    $employee_id = $_POST['employee_id'];
    $period_start = $_POST['period_start'];
    $period_end = $_POST['period_end'];
    $payroll_period = date('F d', strtotime($period_start)) . ' - ' . date('d, Y', strtotime($period_end));
    
    $employee = null;
    foreach ($employees as $emp) {
        if ($emp['id'] == $employee_id) {
            $employee = $emp;
            break;
        }
    }
    
    if ($employee) {
        $attendance = getAttendanceSummary($db, $employee_id, $period_start, $period_end);
        
        $monthly_salary = $employee['salary_rate'];
        $daily_rate = $monthly_salary / 22;
        $hourly_rate = $daily_rate / 8;
        
        $working_days = $attendance['days_present'] ?? 0;
        $overtime_hours = $attendance['total_overtime'] ?? 0;
        $absences = $attendance['absences'] ?? 0;
        
        $basic_salary = $working_days * $daily_rate;
        $overtime_pay = $overtime_hours * ($hourly_rate * 1.25);
        $absence_deduction = $absences * $daily_rate;
        
        $gross_pay = $basic_salary + $overtime_pay;
        
        $sss = getSSSContribution($db, $monthly_salary);
        $philhealth = getPhilHealthContribution($db, $monthly_salary);
        $pagibig = getPagIBIGContribution($db, $monthly_salary);
        $withholding_tax = getWithholdingTax($db, $monthly_salary);
        
        $total_deductions = $sss + $philhealth + $pagibig + $withholding_tax + $absence_deduction;
        $net_pay = $gross_pay - $total_deductions;
        
        $insert = "INSERT INTO tblpayroll (employee_id, payroll_period, period_start, period_end, working_days, total_hours_worked, basic_salary, overtime_pay, gross_pay, sss_deduction, philhealth_deduction, pagibig_deduction, withholding_tax, absence_deduction, total_deductions, net_pay, status, processed_by, processed_date) 
                   VALUES (:employee_id, :payroll_period, :period_start, :period_end, :working_days, :total_hours, :basic_salary, :overtime_pay, :gross_pay, :sss, :philhealth, :pagibig, :tax, :absence_deduction, :total_deductions, :net_pay, 'processed', :user_id, CURDATE())";
        $stmt = $db->prepare($insert);
        $stmt->bindParam(':employee_id', $employee_id);
        $stmt->bindParam(':payroll_period', $payroll_period);
        $stmt->bindParam(':period_start', $period_start);
        $stmt->bindParam(':period_end', $period_end);
        $stmt->bindParam(':working_days', $working_days);
        $stmt->bindParam(':total_hours', $attendance['total_hours']);
        $stmt->bindParam(':basic_salary', $basic_salary);
        $stmt->bindParam(':overtime_pay', $overtime_pay);
        $stmt->bindParam(':gross_pay', $gross_pay);
        $stmt->bindParam(':sss', $sss);
        $stmt->bindParam(':philhealth', $philhealth);
        $stmt->bindParam(':pagibig', $pagibig);
        $stmt->bindParam(':tax', $withholding_tax);
        $stmt->bindParam(':absence_deduction', $absence_deduction);
        $stmt->bindParam(':total_deductions', $total_deductions);
        $stmt->bindParam(':net_pay', $net_pay);
        $stmt->bindParam(':user_id', $user_id);
        
        if ($stmt->execute()) {
            $payroll_result = [
                'employee' => $employee['firstname'] . ' ' . $employee['lastname'],
                'period' => $payroll_period,
                'basic' => $basic_salary,
                'overtime' => $overtime_pay,
                'gross' => $gross_pay,
                'sss' => $sss,
                'philhealth' => $philhealth,
                'pagibig' => $pagibig,
                'tax' => $withholding_tax,
                'absence' => $absence_deduction,
                'deductions' => $total_deductions,
                'net' => $net_pay
            ];
            $success = "✅ Payroll processed successfully!";
        } else {
            $error = "❌ Failed to process payroll!";
        }
    } else {
        $error = "❌ Employee not found!";
    }
}

$payroll_history = [];
$query = "SELECT p.*, e.firstname, e.lastname, e.employee_no FROM tblpayroll p 
          JOIN tblemployees e ON p.employee_id = e.id 
          ORDER BY p.created_at DESC LIMIT 20";
$stmt = $db->prepare($query);
$stmt->execute();
$payroll_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll | Payroll Pro</title>
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

        /* Badges */
        .badge-success {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }

        /* Payroll Summary */
        .payroll-summary {
            background: #f5f5f5;
            border: 1px solid #e5e7eb;
            border-radius: 20px;
            padding: 24px;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-top: 20px;
        }

        .summary-item {
            background: #ffffff;
            padding: 16px;
            border-radius: 16px;
            text-align: center;
            border: 1px solid #e5e7eb;
            transition: all 0.3s;
        }

        .summary-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }

        .summary-item .label {
            color: #6b7280;
            font-size: 12px;
            margin-bottom: 8px;
        }

        .summary-item .value {
            font-size: 22px;
            font-weight: 800;
            color: #111827;
        }

        .text-success {
            color: #2e7d32;
        }

        .text-danger {
            color: #dc2626;
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
            .summary-grid {
                grid-template-columns: 1fr;
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
                <a href="payroll.php" class="nav-link active">
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
            <h1>Payroll Processing</h1>
            <p>Compute employee salaries and deductions</p>
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

        <!-- Compute Payroll Form -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-calculator"></i> Compute Payroll</h3>
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
                            <label>Payroll Period Start *</label>
                            <input type="date" name="period_start" required>
                        </div>
                        <div class="form-group">
                            <label>Payroll Period End *</label>
                            <input type="date" name="period_end" required>
                        </div>
                        <div class="form-group">
                            <button type="submit" name="process_payroll" class="btn-primary">
                                <i class="fas fa-calculator"></i> Process Payroll
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Payroll Result -->
        <?php if($payroll_result): ?>
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-receipt"></i> Payroll Summary</h3>
            </div>
            <div class="card-body">
                <div class="payroll-summary">
                    <h4 style="margin-bottom: 8px; color: #000000;"><?php echo $payroll_result['employee']; ?></h4>
                    <p style="color: #6b7280; margin-bottom: 20px;"><?php echo $payroll_result['period']; ?></p>
                    <div class="summary-grid">
                        <div class="summary-item">
                            <div class="label">Basic Salary</div>
                            <div class="value">₱<?php echo number_format($payroll_result['basic'], 2); ?></div>
                        </div>
                        <div class="summary-item">
                            <div class="label">Overtime Pay</div>
                            <div class="value">₱<?php echo number_format($payroll_result['overtime'], 2); ?></div>
                        </div>
                        <div class="summary-item">
                            <div class="label">Gross Pay</div>
                            <div class="value text-success">₱<?php echo number_format($payroll_result['gross'], 2); ?></div>
                        </div>
                        <div class="summary-item">
                            <div class="label">SSS</div>
                            <div class="value">₱<?php echo number_format($payroll_result['sss'], 2); ?></div>
                        </div>
                        <div class="summary-item">
                            <div class="label">PhilHealth</div>
                            <div class="value">₱<?php echo number_format($payroll_result['philhealth'], 2); ?></div>
                        </div>
                        <div class="summary-item">
                            <div class="label">Pag-IBIG</div>
                            <div class="value">₱<?php echo number_format($payroll_result['pagibig'], 2); ?></div>
                        </div>
                        <div class="summary-item">
                            <div class="label">Withholding Tax</div>
                            <div class="value">₱<?php echo number_format($payroll_result['tax'], 2); ?></div>
                        </div>
                        <div class="summary-item">
                            <div class="label">Absence Deduction</div>
                            <div class="value text-danger">₱<?php echo number_format($payroll_result['absence'], 2); ?></div>
                        </div>
                        <div class="summary-item">
                            <div class="label">Total Deductions</div>
                            <div class="value text-danger">₱<?php echo number_format($payroll_result['deductions'], 2); ?></div>
                        </div>
                        <div class="summary-item">
                            <div class="label">Net Pay</div>
                            <div class="value text-success" style="font-size: 28px;">₱<?php echo number_format($payroll_result['net'], 2); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Payroll History -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-history"></i> Payroll History</h3>
            </div>
            <div style="overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Period</th>
                            <th>Gross Pay</th>
                            <th>Deductions</th>
                            <th>Net Pay</th>
                            <th>Status</th>
                            <th>Date</th>
                        </thead>
                    <tbody>
                        <?php if(count($payroll_history) > 0): ?>
                            <?php foreach($payroll_history as $ph): ?>
                            <tr>
                                <td>
                                    <strong><?php echo $ph['firstname'] . ' ' . $ph['lastname']; ?></strong>
                                    <br><small style="color:#6b7280;"><?php echo $ph['employee_no']; ?></small>
                                29
                                <td><?php echo $ph['payroll_period']; ?>29
                                <td>₱<?php echo number_format($ph['gross_pay'], 2); ?>29
                                <td>₱<?php echo number_format($ph['total_deductions'], 2); ?>29
                                <td><strong class="text-success">₱<?php echo number_format($ph['net_pay'], 2); ?></strong>29
                                <td><span class="badge-success"><?php echo strtoupper($ph['status']); ?></span>29
                                <td><?php echo date('M d, Y', strtotime($ph['created_at'])); ?>29
                            用
                            <?php endforeach; ?>
                        <?php else: ?>
                             Bon
                                <td colspan="7" style="text-align: center; padding: 40px; color: #6b7280;">
                                    <i class="fas fa-receipt" style="font-size: 48px; margin-bottom: 10px; opacity: 0.5;"></i><br>
                                    No payroll records found. Process a payroll to see records.
                                 </td>
                             </tr>
                        <?php endif; ?>
                    </tbody>
                 </div>
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