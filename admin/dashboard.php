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

// Get dashboard stats
$stats = [];
$query = "SELECT COUNT(*) as total FROM tblemployees WHERE status = 'active'";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['employees'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$today = date('Y-m-d');
$query = "SELECT COUNT(DISTINCT employee_id) as total FROM tblattendance WHERE date = :date AND status = 'present'";
$stmt = $db->prepare($query);
$stmt->bindParam(':date', $today);
$stmt->execute();
$stats['present'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$query = "SELECT COUNT(*) as total FROM tblleaves WHERE status = 'pending'";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['pending_leaves'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$query = "SELECT COUNT(*) as total FROM tblloans WHERE status = 'pending'";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['pending_loans'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$query = "SELECT SUM(net_pay) as total FROM tblpayroll WHERE MONTH(created_at) = MONTH(CURDATE()) AND status = 'processed'";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['payroll_total'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Get upcoming holidays
$upcoming_holidays = [];
$query = "SELECT * FROM tblholidays WHERE holiday_date >= CURDATE() ORDER BY holiday_date ASC LIMIT 5";
$stmt = $db->prepare($query);
$stmt->execute();
$upcoming_holidays = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get top employees (most attendance)
$top_employees = [];
$query = "SELECT e.firstname, e.lastname, e.employee_no, e.position, COUNT(a.id) as attendance_count 
          FROM tblemployees e 
          LEFT JOIN tblattendance a ON e.id = a.employee_id AND MONTH(a.date) = MONTH(CURDATE())
          WHERE e.status = 'active'
          GROUP BY e.id 
          ORDER BY attendance_count DESC LIMIT 5";
$stmt = $db->prepare($query);
$stmt->execute();
$top_employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

$attendance_rate = $stats['employees'] > 0 ? round(($stats['present'] / $stats['employees']) * 100, 1) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Payroll Pro</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #ffffff;
            color: #0a0a0a;
            overflow-x: hidden;
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        ::-webkit-scrollbar-thumb {
            background: #000000;
            border-radius: 3px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #333333;
        }

        /* ========== TOP NAVBAR - GLASS EFFECT ========== */
        .top-navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 80px;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 40px;
            z-index: 1000;
            transition: all 0.3s;
        }

        .navbar-left {
            display: flex;
            align-items: center;
            gap: 30px;
        }

        .menu-toggle {
            width: 48px;
            height: 48px;
            background: #f5f5f5;
            border: none;
            border-radius: 16px;
            font-size: 20px;
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
            transform: scale(1.05);
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo-icon {
            width: 40px;
            height: 40px;
            background: #000000;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .logo-icon i {
            font-size: 22px;
            color: #ffffff;
        }

        .logo h2 {
            font-size: 22px;
            font-weight: 800;
            color: #000000;
            letter-spacing: -0.5px;
        }

        .logo span {
            font-size: 10px;
            color: #666666;
            margin-left: 5px;
        }

        .search-bar {
            display: flex;
            align-items: center;
            background: #f5f5f5;
            border-radius: 40px;
            padding: 10px 20px;
            gap: 12px;
            width: 320px;
            transition: all 0.3s;
        }

        .search-bar:focus-within {
            background: #ffffff;
            box-shadow: 0 0 0 2px #000000, 0 0 0 4px rgba(0,0,0,0.05);
        }

        .search-bar i {
            color: #666666;
            font-size: 16px;
        }

        .search-bar input {
            border: none;
            background: none;
            outline: none;
            width: 100%;
            font-size: 14px;
            color: #000000;
        }

        .search-bar input::placeholder {
            color: #999999;
        }

        .navbar-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .notification-btn {
            width: 48px;
            height: 48px;
            background: #f5f5f5;
            border: none;
            border-radius: 16px;
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
            transform: scale(1.05);
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #000000;
            color: #ffffff;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            padding: 5px 15px;
            border-radius: 40px;
            transition: all 0.3s;
        }

        .user-profile:hover {
            background: #f5f5f5;
        }

        .user-avatar {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, #000000, #333333);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            font-weight: 700;
            font-size: 18px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .user-info h4 {
            font-size: 15px;
            font-weight: 700;
            color: #000000;
        }

        .user-info p {
            font-size: 12px;
            color: #666666;
        }

        /* ========== SIDEBAR (SLIDE) - LUXURY ========== */
        .sidebar {
            position: fixed;
            top: 80px;
            left: 0;
            bottom: 0;
            width: 300px;
            background: #ffffff;
            border-right: 1px solid #f0f0f0;
            transform: translateX(-100%);
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 999;
            overflow-y: auto;
            box-shadow: 2px 0 20px rgba(0, 0, 0, 0.02);
        }

        .sidebar.open {
            transform: translateX(0);
        }

        .sidebar-nav {
            padding: 24px 20px;
        }

        .nav-item {
            margin-bottom: 8px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 18px;
            color: #4a5568;
            text-decoration: none;
            border-radius: 16px;
            transition: all 0.3s;
            font-size: 14px;
            font-weight: 500;
            position: relative;
        }

        .nav-link i {
            width: 22px;
            font-size: 18px;
            color: #9ca3af;
            transition: all 0.3s;
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

        .nav-link.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 3px;
            height: 30px;
            background: #ffffff;
            border-radius: 3px;
        }

        .nav-divider {
            height: 1px;
            background: #f0f0f0;
            margin: 20px 0;
        }

        /* ========== MAIN CONTENT ========== */
        .main-content {
            margin-left: 0;
            margin-top: 80px;
            padding: 40px;
            transition: margin-left 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .main-content.sidebar-open {
            margin-left: 300px;
        }

        /* Overlay for mobile */
        .sidebar-overlay {
            position: fixed;
            top: 80px;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.3);
            z-index: 998;
            display: none;
            backdrop-filter: blur(4px);
        }

        .sidebar-overlay.active {
            display: block;
        }

        /* Welcome Banner - Luxury */
        .welcome-banner {
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            border: 1px solid #f0f0f0;
            border-radius: 32px;
            padding: 40px;
            margin-bottom: 40px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.05);
        }

        .welcome-banner::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -30%;
            width: 80%;
            height: 200%;
            background: radial-gradient(circle, rgba(0,0,0,0.02) 0%, transparent 70%);
            animation: rotate 30s linear infinite;
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .welcome-banner h1 {
            font-size: 32px;
            font-weight: 800;
            color: #000000;
            margin-bottom: 12px;
            letter-spacing: -0.5px;
        }

        .welcome-banner p {
            color: #666666;
            font-size: 15px;
        }

        .date-chip {
            position: absolute;
            top: 40px;
            right: 40px;
            background: #ffffff;
            border: 1px solid #f0f0f0;
            padding: 10px 24px;
            border-radius: 40px;
            font-size: 14px;
            font-weight: 500;
            color: #000000;
            box-shadow: 0 2px 8px rgba(0,0,0,0.02);
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 24px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: #ffffff;
            border: 1px solid #f0f0f0;
            border-radius: 28px;
            padding: 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
        }

        .stat-card::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: #000000;
            transform: scaleX(0);
            transition: transform 0.3s;
        }

        .stat-card:hover::after {
            transform: scaleX(1);
        }

        .stat-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 20px 35px -12px rgba(0, 0, 0, 0.1);
            border-color: #e5e5e5;
        }

        .stat-info h3 {
            font-size: 36px;
            font-weight: 800;
            color: #000000;
            letter-spacing: -1px;
        }

        .stat-info p {
            color: #666666;
            font-size: 13px;
            margin-top: 6px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-icon {
            width: 64px;
            height: 64px;
            background: #f5f5f5;
            border-radius: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: #000000;
            transition: all 0.3s;
        }

        .stat-card:hover .stat-icon {
            background: #000000;
            color: #ffffff;
        }

        /* Row Layout - 2 columns */
        .row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 28px;
            margin-bottom: 40px;
        }

        .card {
            background: #ffffff;
            border: 1px solid #f0f0f0;
            border-radius: 28px;
            overflow: hidden;
            transition: all 0.3s;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
        }

        .card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 35px -12px rgba(0, 0, 0, 0.08);
            border-color: #e5e5e5;
        }

        .card-header {
            padding: 24px 28px;
            border-bottom: 1px solid #f5f5f5;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-header h3 {
            font-size: 18px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #000000;
        }

        .view-all {
            color: #666666;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: color 0.3s;
            padding: 6px 12px;
            border-radius: 20px;
        }

        .view-all:hover {
            color: #000000;
            background: #f5f5f5;
        }

        .card-body {
            padding: 24px 28px;
        }

        /* Progress Section */
        .progress-section {
            text-align: center;
        }

        .progress-ring {
            position: relative;
            width: 160px;
            height: 160px;
            margin: 0 auto 24px;
        }

        .progress-ring canvas {
            width: 100%;
            height: 100%;
        }

        .progress-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 36px;
            font-weight: 800;
            color: #000000;
        }

        /* Employee Table */
        .employee-table {
            width: 100%;
            border-collapse: collapse;
        }

        .employee-table th,
        .employee-table td {
            padding: 14px 0;
            text-align: left;
            border-bottom: 1px solid #f5f5f5;
        }

        .employee-table th {
            font-size: 12px;
            font-weight: 600;
            color: #888888;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .employee-table td {
            color: #333333;
            font-size: 14px;
        }

        .rank-badge {
            width: 32px;
            height: 32px;
            background: #f5f5f5;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 14px;
        }

        .rank-1 { background: #000000; color: #ffffff; }
        .rank-2 { background: #333333; color: #ffffff; }
        .rank-3 { background: #666666; color: #ffffff; }

        /* Holiday Cards */
        .holiday-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .holiday-item {
            display: flex;
            align-items: center;
            gap: 18px;
            padding: 16px;
            background: #fafafa;
            border-radius: 20px;
            transition: all 0.3s;
        }

        .holiday-item:hover {
            background: #f5f5f5;
            transform: translateX(5px);
        }

        .holiday-date {
            text-align: center;
            min-width: 70px;
        }

        .holiday-date .day {
            font-size: 28px;
            font-weight: 800;
            color: #000000;
            line-height: 1;
        }

        .holiday-date .month {
            font-size: 11px;
            color: #888888;
            margin-top: 4px;
        }

        .holiday-info {
            flex: 1;
        }

        .holiday-info h4 {
            font-size: 15px;
            font-weight: 700;
            color: #000000;
            margin-bottom: 4px;
        }

        .holiday-info p {
            font-size: 12px;
            color: #888888;
        }

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }

        .action-btn {
            background: #fafafa;
            padding: 18px;
            border-radius: 20px;
            text-align: center;
            text-decoration: none;
            transition: all 0.3s;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            border: 1px solid #f0f0f0;
        }

        .action-btn i {
            font-size: 28px;
            color: #000000;
        }

        .action-btn span {
            font-size: 13px;
            font-weight: 600;
            color: #333333;
        }

        .action-btn:hover {
            background: #000000;
            transform: translateY(-5px);
            border-color: #000000;
        }

        .action-btn:hover i,
        .action-btn:hover span {
            color: #ffffff;
        }

        /* Footer */
        .footer {
            margin-top: 40px;
            text-align: center;
            padding: 24px;
            color: #888888;
            font-size: 12px;
            border-top: 1px solid #f0f0f0;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .row {
                grid-template-columns: 1fr;
            }
            .quick-actions {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 20px;
            }
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .search-bar {
                display: none;
            }
            .user-info {
                display: none;
            }
            .date-chip {
                display: none;
            }
            .welcome-banner h1 {
                font-size: 24px;
            }
            .quick-actions {
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
                    <h2>LUMBRES PAYROLL<span>SYSTEM </span></h2>
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
                <?php if($stats['pending_leaves'] > 0 || $stats['pending_loans'] > 0): ?>
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
                <i class="fas fa-chevron-down" style="font-size: 12px; color: #999;"></i>
            </div>
        </div>
    </div>

    <!-- SIDEBAR -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-nav">
            <div class="nav-item">
                <a href="dashboard.php" class="nav-link active">
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
    <div class="main-content" id="mainContent">>
        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-info">
                    <h3><?php echo $stats['employees']; ?></h3>
                    <p>Total Employees</p>
                </div>
                <div class="stat-icon"><i class="fas fa-users"></i></div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3><?php echo $stats['present']; ?></h3>
                    <p>Present Today</p>
                </div>
                <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3><?php echo $stats['pending_leaves']; ?></h3>
                    <p>Pending Leaves</p>
                </div>
                <div class="stat-icon"><i class="fas fa-umbrella-beach"></i></div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3><?php echo $stats['pending_loans']; ?></h3>
                    <p>Pending Loans</p>
                </div>
                <div class="stat-icon"><i class="fas fa-hand-holding-usd"></i></div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3>₱<?php echo number_format($stats['payroll_total'], 2); ?></h3>
                    <p>This Month Payroll</p>
                </div>
                <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
            </div>
        </div>

        <!-- Row 1: Attendance Overview + Quick Actions -->
        <div class="row">
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-chart-pie"></i> Attendance Overview</h3>
                    <span class="view-all">Today</span>
                </div>
                <div class="card-body">
                    <div class="progress-section">
                        <div class="progress-ring">
                            <canvas id="attendanceChart" width="160" height="160"></canvas>
                            <div class="progress-text"><?php echo $attendance_rate; ?>%</div>
                        </div>
                        <p style="color: #666666; margin-top: 16px;"><?php echo $stats['present']; ?> out of <?php echo $stats['employees']; ?> employees present</p>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
                </div>
                <div class="card-body">
                    <div class="quick-actions">
                        <a href="attendance.php" class="action-btn">
                            <i class="fas fa-fingerprint"></i>
                            <span>Time In/Out</span>
                        </a>
                        <a href="employees.php?add=1" class="action-btn">
                            <i class="fas fa-user-plus"></i>
                            <span>Add Employee</span>
                        </a>
                        <a href="payroll.php" class="action-btn">
                            <i class="fas fa-calculator"></i>
                            <span>Process Payroll</span>
                        </a>
                        <a href="reports.php" class="action-btn">
                            <i class="fas fa-chart-line"></i>
                            <span>Generate Report</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Row 2: Upcoming Holidays + Top Employees (Magkatabi) -->
        <div class="row">
            <!-- Upcoming Holidays Card -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-calendar-star"></i> Upcoming Holidays</h3>
                    <a href="holidays.php" class="view-all">View All →</a>
                </div>
                <div class="card-body">
                    <div class="holiday-list">
                        <?php if(count($upcoming_holidays) > 0): ?>
                            <?php foreach($upcoming_holidays as $hol): ?>
                            <div class="holiday-item">
                                <div class="holiday-date">
                                    <div class="day"><?php echo date('d', strtotime($hol['holiday_date'])); ?></div>
                                    <div class="month"><?php echo date('M', strtotime($hol['holiday_date'])); ?></div>
                                </div>
                                <div class="holiday-info">
                                    <h4><?php echo htmlspecialchars($hol['holiday_name']); ?></h4>
                                    <p><?php echo ucfirst(str_replace('_', ' ', $hol['holiday_type'])); ?> Holiday</p>
                                </div>
                                <?php if($hol['double_pay']): ?>
                                <span style="background: #000000; padding: 5px 12px; border-radius: 20px; font-size: 10px; color: #ffffff; font-weight: 600;">Double Pay</span>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="text-align: center; padding: 20px; color: #888;">No upcoming holidays</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Top Employees Card -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-trophy"></i> Top Employees (This Month)</h3>
                    <a href="reports.php?report_type=attendance" class="view-all">View All →</a>
                </div>
                <div class="card-body">
                    <table class="employee-table">
                        <thead>
                            <tr><th>Rank</th><th>Employee</th><th>Position</th><th>Attendance</th> </thead>
                        <tbody>
                            <?php foreach($top_employees as $index => $emp): ?>
                            <tr>
                                <td style="width: 50px;">
                                    <span class="rank-badge rank-<?php echo $index + 1; ?>">
                                        <?php echo $index + 1; ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?php echo $emp['firstname'] . ' ' . $emp['lastname']; ?></strong>
                                    <br><small style="color:#888;"><?php echo $emp['employee_no']; ?></small>
                                </td>
                                <td><?php echo $emp['position']; ?></td>
                                <td><?php echo $emp['attendance_count']; ?> days</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>© <?php echo date('Y'); ?> LUMBRES PAYROLL SYSTEM. All rights reserved. | Designed with <i class="fas fa-heart" style="color: #000;"></i> for excellence</p>
        </div>
    </div>

    <script>
        // Attendance Chart
        const ctx = document.getElementById('attendanceChart').getContext('2d');
        const attendanceRate = <?php echo $attendance_rate; ?>;
        const remaining = 100 - attendanceRate;
        
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Present', 'Absent'],
                datasets: [{
                    data: [attendanceRate, remaining],
                    backgroundColor: ['#000000', '#f0f0f0'],
                    borderWidth: 0,
                    cutout: '70%'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        enabled: false
                    }
                }
            }
        });

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

        // Current Date
        function updateDate() {
            const now = new Date();
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            const dateElement = document.getElementById('currentDate');
            if (dateElement) {
                dateElement.innerHTML = now.toLocaleDateString('en-US', options);
            }
        }
        updateDate();

        // Notification Bell Click
        document.getElementById('notificationBtn').addEventListener('click', () => {
            window.location.href = 'reports.php?report_type=leave';
        });
    </script>
</body>
</html>