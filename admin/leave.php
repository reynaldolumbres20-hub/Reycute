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

// Get all employees
$employees = [];
$query = "SELECT id, employee_no, firstname, lastname FROM tblemployees WHERE status = 'active' ORDER BY firstname ASC";
$stmt = $db->prepare($query);
$stmt->execute();
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle Add Leave Request
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_leave'])) {
    $employee_id = $_POST['employee_id'];
    $leave_type = $_POST['leave_type'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $reason = $_POST['reason'];
    
    $start = strtotime($start_date);
    $end = strtotime($end_date);
    $days_applied = ceil(($end - $start) / (60 * 60 * 24)) + 1;
    
    $query = "INSERT INTO tblleaves (employee_id, leave_type, start_date, end_date, days_applied, reason, status) 
              VALUES (:employee_id, :leave_type, :start_date, :end_date, :days_applied, :reason, 'pending')";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':employee_id', $employee_id);
    $stmt->bindParam(':leave_type', $leave_type);
    $stmt->bindParam(':start_date', $start_date);
    $stmt->bindParam(':end_date', $end_date);
    $stmt->bindParam(':days_applied', $days_applied);
    $stmt->bindParam(':reason', $reason);
    
    if ($stmt->execute()) {
        $success = "✅ Leave request submitted successfully!";
    } else {
        $error = "❌ Failed to submit leave request!";
    }
}

// Handle Approve/Reject Leave
if (isset($_GET['approve'])) {
    $id = $_GET['approve'];
    $query = "UPDATE tblleaves SET status = 'approved', approved_by = :user_id, approved_date = CURDATE() WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':id', $id);
    if ($stmt->execute()) {
        $success = "✅ Leave approved successfully!";
    } else {
        $error = "❌ Failed to approve leave!";
    }
}

if (isset($_GET['reject'])) {
    $id = $_GET['reject'];
    $query = "UPDATE tblleaves SET status = 'rejected', approved_by = :user_id, approved_date = CURDATE() WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':id', $id);
    if ($stmt->execute()) {
        $success = "✅ Leave rejected successfully!";
    } else {
        $error = "❌ Failed to reject leave!";
    }
}

// Get all leave requests
$leaves = [];
$query = "SELECT l.*, e.firstname, e.lastname, e.employee_no 
          FROM tblleaves l 
          JOIN tblemployees e ON l.employee_id = e.id 
          ORDER BY l.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$leaves = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get leave balances
$balances = [];
$query = "SELECT lb.*, e.firstname, e.lastname, e.employee_no 
          FROM tblleave_balances lb 
          JOIN tblemployees e ON lb.employee_id = e.id 
          WHERE lb.year = YEAR(CURDATE())
          ORDER BY e.firstname ASC";
$stmt = $db->prepare($query);
$stmt->execute();
$balances = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Management | Payroll Pro</title>
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
        .form-group input,
        .form-group textarea {
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
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #000000;
            box-shadow: 0 0 0 2px rgba(0,0,0,0.05);
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        /* Balance Grid */
        .balance-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 16px;
        }

        .balance-card {
            background: #f5f5f5;
            border: 1px solid #e5e7eb;
            border-radius: 20px;
            padding: 16px;
            transition: all 0.3s;
        }

        .balance-card:hover {
            background: #ffffff;
            transform: translateY(-2px);
        }

        .balance-card h4 {
            font-size: 15px;
            font-weight: 700;
            color: #000000;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 1px solid #e5e7eb;
        }

        .balance-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 13px;
        }

        .balance-item span {
            color: #6b7280;
        }

        .balance-item strong {
            color: #000000;
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
        .badge-pending {
            background: #fef3c7;
            color: #92400e;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }

        .badge-approved {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }

        .badge-rejected {
            background: #ffebee;
            color: #c62828;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }

        /* Action Buttons */
        .btn-approve, .btn-reject {
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

        .btn-approve {
            background: #f5f5f5;
            color: #2e7d32;
            border: 1px solid #e5e7eb;
        }

        .btn-approve:hover {
            background: #2e7d32;
            color: #ffffff;
            border-color: #2e7d32;
        }

        .btn-reject {
            background: #f5f5f5;
            color: #dc2626;
            border: 1px solid #e5e7eb;
        }

        .btn-reject:hover {
            background: #dc2626;
            color: #ffffff;
            border-color: #dc2626;
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
            .balance-grid {
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
                <a href="leave.php" class="nav-link active">
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
            <h1>Leave Management</h1>
            <p>Manage employee leave requests and balances</p>
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

        <!-- Request Leave Form -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-plus-circle"></i> Request Leave</h3>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Employee *</label>
                            <select name="employee_id" required>
                                <option value="">-- Select Employee --</option>
                                <?php foreach($employees as $emp): ?>
                                <option value="<?php echo $emp['id']; ?>"><?php echo $emp['employee_no'] . ' - ' . $emp['firstname'] . ' ' . $emp['lastname']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Leave Type *</label>
                            <select name="leave_type" required>
                                <option value="sick">Sick Leave</option>
                                <option value="vacation">Vacation Leave</option>
                                <option value="emergency">Emergency Leave</option>
                                <option value="maternity">Maternity Leave</option>
                                <option value="paternity">Paternity Leave</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Start Date *</label>
                            <input type="date" name="start_date" required>
                        </div>
                        <div class="form-group">
                            <label>End Date *</label>
                            <input type="date" name="end_date" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Reason</label>
                        <textarea name="reason" rows="3" placeholder="Please provide reason for leave..."></textarea>
                    </div>
                    <button type="submit" name="add_leave" class="btn-primary">
                        <i class="fas fa-paper-plane"></i> Submit Request
                    </button>
                </form>
            </div>
        </div>

        <!-- Leave Balances -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-chart-simple"></i> Leave Balances (<?php echo date('Y'); ?>)</h3>
            </div>
            <div class="card-body">
                <div class="balance-grid">
                    <?php if(count($balances) > 0): ?>
                        <?php foreach($balances as $bal): ?>
                        <div class="balance-card">
                            <h4><?php echo $bal['firstname'] . ' ' . $bal['lastname']; ?></h4>
                            <div class="balance-item">
                                <span>Sick Leave:</span>
                                <strong><?php echo $bal['sick_leave_balance']; ?> days</strong>
                            </div>
                            <div class="balance-item">
                                <span>Vacation Leave:</span>
                                <strong><?php echo $bal['vacation_leave_balance']; ?> days</strong>
                            </div>
                            <div class="balance-item">
                                <span>Emergency Leave:</span>
                                <strong><?php echo $bal['emergency_leave_balance']; ?> days</strong>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="text-align: center; padding: 40px; color: #6b7280;">No leave balances found.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Leave Requests Table -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-list"></i> Leave Requests</h3>
            </div>
            <div style="overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Type</th>
                            <th>Period</th>
                            <th>Days</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </thead>
                    <tbody>
                        <?php if(count($leaves) > 0): ?>
                            <?php foreach($leaves as $leave): ?>
                            <tr>
                                <td>
                                    <strong><?php echo $leave['firstname'] . ' ' . $leave['lastname']; ?></strong>
                                    <br><small style="color:#6b7280;"><?php echo $leave['employee_no']; ?></small>
                                 </td>
                                 <td><?php echo ucfirst($leave['leave_type']); ?> Leave</td>
                                 <td><?php echo date('M d', strtotime($leave['start_date'])); ?> - <?php echo date('M d', strtotime($leave['end_date'])); ?></td>
                                 <td><?php echo $leave['days_applied']; ?> days</td>
                                 <td><?php echo $leave['reason']; ?></td>
                                 <td>
                                    <?php if($leave['status'] == 'pending'): ?>
                                        <span class="badge-pending">PENDING</span>
                                    <?php elseif($leave['status'] == 'approved'): ?>
                                        <span class="badge-approved">APPROVED</span>
                                    <?php else: ?>
                                        <span class="badge-rejected">REJECTED</span>
                                    <?php endif; ?>
                                 </td>
                                 <td>
                                    <?php if($leave['status'] == 'pending'): ?>
                                        <a href="?approve=<?php echo $leave['id']; ?>" class="btn-approve" onclick="return confirm('Approve this leave request?')">
                                            <i class="fas fa-check"></i> Approve
                                        </a>
                                        <a href="?reject=<?php echo $leave['id']; ?>" class="btn-reject" onclick="return confirm('Reject this leave request?')">
                                            <i class="fas fa-times"></i> Reject
                                        </a>
                                    <?php endif; ?>
                                 </td>
                             </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                             <tr>
                                <td colspan="7" style="text-align: center; padding: 40px; color: #6b7280;">
                                    <i class="fas fa-umbrella-beach" style="font-size: 48px; margin-bottom: 10px; opacity: 0.5;"></i><br>
                                    No leave requests found.
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