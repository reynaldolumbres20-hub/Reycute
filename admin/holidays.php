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

// ============================================
// PRE-DEFINED HOLIDAYS (AUTOMATIC)
// ============================================
$year = date('Y');

$automatic_holidays = [
    // Regular Holidays (Double Pay)
    ['name' => 'New Year\'s Day', 'date' => $year . '-01-01', 'type' => 'regular', 'double_pay' => 1],
    ['name' => 'Araw ng Kagitingan', 'date' => $year . '-04-09', 'type' => 'regular', 'double_pay' => 1],
    ['name' => 'Labor Day', 'date' => $year . '-05-01', 'type' => 'regular', 'double_pay' => 1],
    ['name' => 'Independence Day', 'date' => $year . '-06-12', 'type' => 'regular', 'double_pay' => 1],
    ['name' => 'National Heroes Day', 'date' => $year . '-08-31', 'type' => 'regular', 'double_pay' => 1],
    ['name' => 'Bonifacio Day', 'date' => $year . '-11-30', 'type' => 'regular', 'double_pay' => 1],
    ['name' => 'Christmas Day', 'date' => $year . '-12-25', 'type' => 'regular', 'double_pay' => 1],
    ['name' => 'Rizal Day', 'date' => $year . '-12-30', 'type' => 'regular', 'double_pay' => 1],
    
    // Special Non-Working Holidays
    ['name' => 'EDSA People Power Anniversary', 'date' => $year . '-02-25', 'type' => 'special_non_working', 'double_pay' => 0],
    ['name' => 'Ninoy Aquino Day', 'date' => $year . '-08-21', 'type' => 'special_non_working', 'double_pay' => 0],
    ['name' => 'All Saints\' Day', 'date' => $year . '-11-01', 'type' => 'special_non_working', 'double_pay' => 0],
    ['name' => 'Feast of the Immaculate Conception', 'date' => $year . '-12-08', 'type' => 'special_non_working', 'double_pay' => 0],
];

// Insert automatic holidays if not exists
foreach ($automatic_holidays as $hol) {
    $check = "SELECT id FROM tblholidays WHERE holiday_date = :date";
    $check_stmt = $db->prepare($check);
    $check_stmt->bindParam(':date', $hol['date']);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() == 0) {
        $query = "INSERT INTO tblholidays (holiday_name, holiday_date, holiday_type, double_pay) 
                  VALUES (:name, :date, :type, :double_pay)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':name', $hol['name']);
        $stmt->bindParam(':date', $hol['date']);
        $stmt->bindParam(':type', $hol['type']);
        $stmt->bindParam(':double_pay', $hol['double_pay']);
        $stmt->execute();
    }
}

// Handle Add Holiday (Manual)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_holiday'])) {
    $holiday_name = trim($_POST['holiday_name']);
    $holiday_date = $_POST['holiday_date'];
    $holiday_type = $_POST['holiday_type'];
    $double_pay = isset($_POST['double_pay']) ? 1 : 0;
    
    if (empty($holiday_name) || empty($holiday_date)) {
        $error = "❌ Please fill in all required fields!";
    } else {
        $check = "SELECT id FROM tblholidays WHERE holiday_date = :holiday_date";
        $check_stmt = $db->prepare($check);
        $check_stmt->bindParam(':holiday_date', $holiday_date);
        $check_stmt->execute();
        
        if ($check_stmt->rowCount() == 0) {
            $query = "INSERT INTO tblholidays (holiday_name, holiday_date, holiday_type, double_pay) 
                      VALUES (:holiday_name, :holiday_date, :holiday_type, :double_pay)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':holiday_name', $holiday_name);
            $stmt->bindParam(':holiday_date', $holiday_date);
            $stmt->bindParam(':holiday_type', $holiday_type);
            $stmt->bindParam(':double_pay', $double_pay);
            
            if ($stmt->execute()) {
                $success = "✅ Holiday added successfully!";
            } else {
                $error = "❌ Failed to add holiday!";
            }
        } else {
            $error = "⚠️ Holiday already exists for this date!";
        }
    }
}

// Handle Delete Holiday
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $query = "DELETE FROM tblholidays WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $id);
    if ($stmt->execute()) {
        $success = "✅ Holiday deleted successfully!";
    } else {
        $error = "❌ Failed to delete holiday!";
    }
}

// Get all holidays
$holidays = [];
$query = "SELECT * FROM tblholidays ORDER BY holiday_date ASC";
$stmt = $db->prepare($query);
$stmt->execute();
$holidays = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get upcoming holidays (next 30 days)
$upcoming = [];
$today = date('Y-m-d');
$next_month = date('Y-m-d', strtotime('+30 days'));
$query = "SELECT * FROM tblholidays WHERE holiday_date >= :today AND holiday_date <= :next_month ORDER BY holiday_date ASC";
$stmt = $db->prepare($query);
$stmt->bindParam(':today', $today);
$stmt->bindParam(':next_month', $next_month);
$stmt->execute();
$upcoming = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Holidays | Payroll Pro</title>
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
        .btn-add {
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

        .btn-add:hover {
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
            padding: 18px 24px;
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

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            background: #ffffff;
            transition: all 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #000000;
            box-shadow: 0 0 0 2px rgba(0,0,0,0.05);
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 5px;
        }

        .checkbox-group input {
            width: 18px;
            height: 18px;
            margin: 0;
            cursor: pointer;
        }

        .checkbox-group label {
            margin: 0;
            cursor: pointer;
            color: #374151;
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
        .badge-regular {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }

        .badge-special {
            background: #e3f2fd;
            color: #1565c0;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }

        .badge-working {
            background: #fff3e0;
            color: #ef6c00;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }

        .double-pay-badge {
            background: #fef3c7;
            color: #92400e;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }

        .btn-delete {
            padding: 6px 14px;
            border-radius: 12px;
            text-decoration: none;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #f5f5f5;
            color: #dc2626;
            border: 1px solid #e5e7eb;
            transition: all 0.3s;
        }

        .btn-delete:hover {
            background: #dc2626;
            color: #ffffff;
            border-color: #dc2626;
        }

        /* Upcoming Grid */
        .upcoming-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 16px;
        }

        .upcoming-card {
            background: #f5f5f5;
            border: 1px solid #e5e7eb;
            border-radius: 20px;
            padding: 16px;
            display: flex;
            align-items: center;
            gap: 16px;
            transition: all 0.3s;
        }

        .upcoming-card:hover {
            transform: translateX(5px);
            background: #ffffff;
        }

        .upcoming-date {
            text-align: center;
            min-width: 70px;
            padding: 8px;
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid #e5e7eb;
        }

        .upcoming-date .day {
            font-size: 28px;
            font-weight: 800;
            color: #000000;
            line-height: 1;
        }

        .upcoming-date .month {
            font-size: 11px;
            color: #6b7280;
            margin-top: 4px;
        }

        .upcoming-info {
            flex: 1;
        }

        .upcoming-info h4 {
            font-size: 15px;
            font-weight: 700;
            color: #000000;
            margin-bottom: 6px;
        }

        .upcoming-info p {
            font-size: 12px;
            color: #6b7280;
            margin-bottom: 5px;
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
            .upcoming-grid {
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
                <a href="holidays.php" class="nav-link active">
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
            <h1>Holiday Management</h1>
            <p>Manage regular and special holidays for payroll computation</p>
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

        <!-- Upcoming Holidays Section -->
        <?php if(count($upcoming) > 0): ?>
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-calendar-star"></i> Upcoming Holidays (Next 30 Days)</h3>
            </div>
            <div class="card-body">
                <div class="upcoming-grid">
                    <?php foreach($upcoming as $hol): ?>
                    <div class="upcoming-card">
                        <div class="upcoming-date">
                            <div class="day"><?php echo date('d', strtotime($hol['holiday_date'])); ?></div>
                            <div class="month"><?php echo date('M', strtotime($hol['holiday_date'])); ?></div>
                        </div>
                        <div class="upcoming-info">
                            <h4><?php echo htmlspecialchars($hol['holiday_name']); ?></h4>
                            <p><?php echo ucfirst(str_replace('_', ' ', $hol['holiday_type'])); ?> Holiday</p>
                            <?php if($hol['double_pay']): ?>
                                <span class="double-pay-badge"><i class="fas fa-money-bill-wave"></i> Double Pay</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Add Holiday Form -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-plus-circle"></i> Add New Holiday</h3>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Holiday Name *</label>
                            <input type="text" name="holiday_name" placeholder="e.g., Special Non-Working Holiday" required>
                        </div>
                        <div class="form-group">
                            <label>Holiday Date *</label>
                            <input type="date" name="holiday_date" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Holiday Type *</label>
                            <select name="holiday_type" required>
                                <option value="regular">Regular Holiday (Double Pay)</option>
                                <option value="special_non_working">Special Non-Working Holiday</option>
                                <option value="special_working">Special Working Holiday</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <div class="checkbox-group">
                                <input type="checkbox" name="double_pay" id="double_pay" value="1">
                                <label for="double_pay">Double Pay (For Regular Holidays only)</label>
                            </div>
                        </div>
                    </div>
                    <button type="submit" name="add_holiday" class="btn-add" style="margin-top: 10px;">
                        <i class="fas fa-save"></i> Add Holiday
                    </button>
                </form>
            </div>
        </div>

        <!-- Holiday List -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-list"></i> Holiday Calendar (<?php echo date('Y'); ?>)</h3>
            </div>
            <div style="overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Holiday Name</th>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Double Pay</th>
                            <th>Actions</th>
                        </thead>
                    <tbody>
                        <?php if(count($holidays) > 0): ?>
                            <?php foreach($holidays as $hol): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($hol['holiday_name']); ?></strong>29
                                <td><?php echo date('F d, Y', strtotime($hol['holiday_date'])); ?>29
                                <td>
                                    <?php if($hol['holiday_type'] == 'regular'): ?>
                                        <span class="badge-regular">Regular Holiday</span>
                                    <?php elseif($hol['holiday_type'] == 'special_non_working'): ?>
                                        <span class="badge-special">Special Non-Working</span>
                                    <?php else: ?>
                                        <span class="badge-working">Special Working</span>
                                    <?php endif; ?>
                                29
                                <td>
                                    <?php if($hol['double_pay']): ?>
                                        <span class="double-pay-badge"><i class="fas fa-money-bill-wave"></i> Double Pay</span>
                                    <?php else: ?>
                                        <span style="color: #9ca3af;">-</span>
                                    <?php endif; ?>
                                29
                                <td>
                                    <a href="?delete=<?php echo $hol['id']; ?>" class="btn-delete" onclick="return confirm('Delete this holiday?')">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
                                29
                            用
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 40px; color: #6b7280;">
                                    <i class="fas fa-calendar-day" style="font-size: 48px; margin-bottom: 10px; opacity: 0.5;"></i><br>
                                    No holidays found. Add a holiday using the form above.
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