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

// Get all employees
$employees = [];
$query = "SELECT id, employee_no, firstname, lastname FROM tblemployees WHERE status = 'active' ORDER BY firstname ASC";
$stmt = $db->prepare($query);
$stmt->execute();
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle Time In
if (isset($_POST['time_in'])) {
    $employee_id = $_POST['employee_id'];
    $date = $_POST['attendance_date'];
    $time_in = $_POST['time_in_manual'];
    $time_in_24hr = date('H:i:s', strtotime($time_in));
    
    $check = "SELECT id FROM tblattendance WHERE employee_id = :employee_id AND date = :date";
    $check_stmt = $db->prepare($check);
    $check_stmt->bindParam(':employee_id', $employee_id);
    $check_stmt->bindParam(':date', $date);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() == 0) {
        $query = "INSERT INTO tblattendance (employee_id, date, time_in, status) VALUES (:employee_id, :date, :time_in, 'present')";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':employee_id', $employee_id);
        $stmt->bindParam(':date', $date);
        $stmt->bindParam(':time_in', $time_in_24hr);
        if ($stmt->execute()) {
            $success = "✅ Time In recorded successfully on $date at " . date('h:i A', strtotime($time_in));
        } else {
            $error = "❌ Failed to record Time In!";
        }
    } else {
        $error = "⚠️ Attendance already recorded for $date!";
    }
}

// Handle Time Out
if (isset($_POST['time_out'])) {
    $attendance_id = $_POST['attendance_id'];
    $time_out = $_POST['time_out_manual'];
    $time_out_24hr = date('H:i:s', strtotime($time_out));
    $break_start = $_POST['break_start'];
    $break_end = $_POST['break_end'];
    
    $get = "SELECT time_in FROM tblattendance WHERE id = :id";
    $get_stmt = $db->prepare($get);
    $get_stmt->bindParam(':id', $attendance_id);
    $get_stmt->execute();
    $att = $get_stmt->fetch(PDO::FETCH_ASSOC);
    $time_in = $att['time_in'];
    
    $break_duration = 0;
    if ($break_start && $break_end) {
        $break_start_time = strtotime($break_start);
        $break_end_time = strtotime($break_end);
        $break_duration = ($break_end_time - $break_start_time) / 3600;
    }
    
    $time_in_sec = strtotime($time_in);
    $time_out_sec = strtotime($time_out_24hr);
    $total_hours = ($time_out_sec - $time_in_sec) / 3600;
    $hours_worked = $total_hours - $break_duration;
    
    $late_threshold = strtotime('08:00:00');
    $status = 'present';
    if ($time_in_sec > $late_threshold) {
        $status = 'late';
    }
    
    $overtime_hours = 0;
    if ($hours_worked > 8) {
        $overtime_hours = $hours_worked - 8;
    }
    
    $query = "UPDATE tblattendance SET time_out = :time_out, break_start = :break_start, break_end = :break_end, 
              break_duration = :break_duration, hours_worked = :hours_worked, overtime_hours = :overtime_hours, status = :status 
              WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':time_out', $time_out_24hr);
    $stmt->bindParam(':break_start', $break_start);
    $stmt->bindParam(':break_end', $break_end);
    $stmt->bindParam(':break_duration', $break_duration);
    $stmt->bindParam(':hours_worked', $hours_worked);
    $stmt->bindParam(':overtime_hours', $overtime_hours);
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':id', $attendance_id);
    
    if ($stmt->execute()) {
        $success = "✅ Time Out recorded! Hours worked: " . number_format($hours_worked, 2);
    } else {
        $error = "❌ Failed to record Time Out!";
    }
}

// Get attendance records
$attendance = [];
$query = "SELECT a.*, e.firstname, e.lastname, e.employee_no 
          FROM tblattendance a 
          JOIN tblemployees e ON a.employee_id = e.id 
          ORDER BY a.date DESC, a.id DESC LIMIT 50";
$stmt = $db->prepare($query);
$stmt->execute();
$attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get pending time out records
$pending_timeout = [];
$query = "SELECT a.*, e.firstname, e.lastname, e.employee_no 
          FROM tblattendance a 
          JOIN tblemployees e ON a.employee_id = e.id 
          WHERE a.time_out IS NULL
          ORDER BY a.date DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$pending_timeout = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance | Payroll Pro</title>
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

        /* Buttons */
        .btn-primary {
            background: #000000;
            color: #ffffff;
            border: none;
            padding: 10px 24px;
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

        .btn-success {
            background: #000000;
        }

        /* Form Elements */
        .form-group {
            margin-bottom: 0;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            font-size: 12px;
            color: #6b7280;
        }

        .form-group select,
        .form-group input {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            font-family: 'Inter', sans-serif;
            font-size: 13px;
            background: #ffffff;
            transition: all 0.3s;
        }

        .form-group select:focus,
        .form-group input:focus {
            outline: none;
            border-color: #000000;
            box-shadow: 0 0 0 2px rgba(0,0,0,0.05);
        }

        /* Flex Row */
        .flex-row {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: flex-end;
        }

        .flex-grow {
            flex: 1;
        }

        /* Timeout Card */
        .timeout-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 16px;
            transition: all 0.3s;
        }

        .timeout-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }

        .timeout-header {
            margin-bottom: 15px;
            padding-bottom: 12px;
            border-bottom: 1px solid #e5e7eb;
        }

        .timeout-header strong {
            font-size: 16px;
            color: #000000;
        }

        .timeout-header small {
            font-size: 12px;
            color: #6b7280;
        }

        .timeout-fields {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: flex-end;
        }

        .timeout-field {
            flex: 1;
            min-width: 120px;
        }

        .timeout-field label {
            display: block;
            margin-bottom: 5px;
            font-size: 11px;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
        }

        .timeout-field input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            font-size: 13px;
            background: #f5f5f5;
        }

        .timeout-field input:focus {
            outline: none;
            border-color: #000000;
            background: #ffffff;
        }

        /* Data Table */
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th,
        .data-table td {
            padding: 14px 16px;
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

        /* Hint Text */
        .hint-text {
            font-size: 10px;
            color: #9ca3af;
            margin-top: 4px;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .flex-row, .timeout-fields {
                flex-direction: column;
                align-items: stretch;
            }
            .flex-grow, .timeout-field {
                width: 100%;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 20px;
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
                <a href="attendance.php" class="nav-link active">
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
            <h1>Attendance Management</h1>
            <p>Record and manage employee attendance (Manual Date & Time Input)</p>
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

        <!-- Time In Section -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-sign-in-alt"></i> Time In</h3>
            </div>
            <div class="card-body">
                <form method="POST" class="flex-row">
                    <div class="flex-grow">
                        <div class="form-group">
                            <label>Select Employee</label>
                            <select name="employee_id" required>
                                <option value="">-- Select Employee --</option>
                                <?php foreach($employees as $emp): ?>
                                <option value="<?php echo $emp['id']; ?>"><?php echo $emp['employee_no'] . ' - ' . $emp['firstname'] . ' ' . $emp['lastname']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div>
                        <div class="form-group">
                            <label>Date</label>
                            <input type="date" name="attendance_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    <div>
                        <div class="form-group">
                            <label>Time In</label>
                            <input type="time" name="time_in_manual" value="<?php echo date('H:i'); ?>" required>
                        </div>
                    </div>
                    <div>
                        <button type="submit" name="time_in" class="btn-primary">
                            <i class="fas fa-fingerprint"></i> Time In
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Time Out Section -->
        <?php if(count($pending_timeout) > 0): ?>
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-sign-out-alt"></i> Time Out (With Break)</h3>
            </div>
            <div class="card-body">
                <?php foreach($pending_timeout as $pt): ?>
                <div class="timeout-card">
                    <div class="timeout-header">
                        <strong><?php echo $pt['firstname'] . ' ' . $pt['lastname']; ?></strong>
                        <br>
                        <small><?php echo $pt['employee_no']; ?> | Date: <?php echo date('M d, Y', strtotime($pt['date'])); ?> | Time In: <?php echo date('h:i A', strtotime($pt['time_in'])); ?></small>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="attendance_id" value="<?php echo $pt['id']; ?>">
                        <div class="timeout-fields">
                            <div class="timeout-field">
                                <label>Break Start</label>
                                <input type="time" name="break_start" value="12:00">
                            </div>
                            <div class="timeout-field">
                                <label>Break End</label>
                                <input type="time" name="break_end" value="13:00">
                            </div>
                            <div class="timeout-field">
                                <label>Time Out</label>
                                <input type="time" name="time_out_manual" value="<?php echo date('H:i'); ?>" required>
                                <div class="hint-text">e.g., 17:00, 18:30, 20:00</div>
                            </div>
                            <div class="timeout-field" style="flex: 0.5;">
                                <button type="submit" name="time_out" class="btn-primary btn-success" style="width: 100%;">
                                    <i class="fas fa-sign-out-alt"></i> Time Out
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Attendance Records -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-list"></i> Attendance Records</h3>
            </div>
            <div style="overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Employee</th>
                            <th>Time In</th>
                            <th>Break</th>
                            <th>Time Out</th>
                            <th>Hours</th>
                            <th>OT</th>
                            <th>Status</th>
                        </thead>
                    <tbody>
                        <?php if(count($attendance) > 0): ?>
                            <?php foreach($attendance as $att): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($att['date'])); ?>29
                                <td>
                                    <strong><?php echo $att['firstname'] . ' ' . $att['lastname']; ?></strong>
                                    <br><small style="color:#6b7280;"><?php echo $att['employee_no']; ?></small>
                                29
                                <td><?php echo $att['time_in'] ? date('h:i A', strtotime($att['time_in'])) : '-'; ?>29
                                <td><?php echo $att['break_duration'] ? number_format($att['break_duration'], 2) . ' hrs' : '-'; ?>29
                                <td><?php echo $att['time_out'] ? date('h:i A', strtotime($att['time_out'])) : '-'; ?>29
                                <td><?php echo $att['hours_worked'] ? number_format($att['hours_worked'], 2) : '0'; ?>29
                                <td><?php echo $att['overtime_hours'] ? number_format($att['overtime_hours'], 2) : '0'; ?>29
                                <td><span class="badge badge-<?php echo $att['status']; ?>"><?php echo strtoupper($att['status']); ?></span>29
                            用
                            <?php endforeach; ?>
                        <?php else: ?>
                             Bon
                                <td colspan="8" style="text-align: center; padding: 40px; color: #6b7280;">
                                    <i class="fas fa-calendar-check" style="font-size: 48px; margin-bottom: 10px; opacity: 0.5;"></i><br>
                                    No attendance records found.
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