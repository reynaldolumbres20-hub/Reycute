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

// Get current settings
$settings = [];
$query = "SELECT * FROM tblcompany_settings ORDER BY id DESC LIMIT 1";
$stmt = $db->prepare($query);
$stmt->execute();
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$settings) {
    $insert = "INSERT INTO tblcompany_settings (company_name, company_address, company_contact, company_email, tax_rate, sss_rate, philhealth_rate, pagibig_rate, night_diff_rate, overtime_rate, holiday_rate, rest_day_rate) 
               VALUES ('Payroll System Inc.', 'Manila, Philippines', '02-123-4567', 'info@payrollsystem.com', 15.00, 4.50, 3.00, 2.00, 1.10, 1.25, 2.00, 1.30)";
    $db->exec($insert);
    
    $query = "SELECT * FROM tblcompany_settings ORDER BY id DESC LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Handle Update Settings
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_settings'])) {
    $company_name = $_POST['company_name'];
    $company_address = $_POST['company_address'];
    $company_contact = $_POST['company_contact'];
    $company_email = $_POST['company_email'];
    $tax_rate = $_POST['tax_rate'];
    $sss_rate = $_POST['sss_rate'];
    $philhealth_rate = $_POST['philhealth_rate'];
    $pagibig_rate = $_POST['pagibig_rate'];
    $night_diff_rate = $_POST['night_diff_rate'];
    $overtime_rate = $_POST['overtime_rate'];
    $holiday_rate = $_POST['holiday_rate'];
    $rest_day_rate = $_POST['rest_day_rate'];
    
    $query = "UPDATE tblcompany_settings SET 
              company_name = :company_name,
              company_address = :company_address,
              company_contact = :company_contact,
              company_email = :company_email,
              tax_rate = :tax_rate,
              sss_rate = :sss_rate,
              philhealth_rate = :philhealth_rate,
              pagibig_rate = :pagibig_rate,
              night_diff_rate = :night_diff_rate,
              overtime_rate = :overtime_rate,
              holiday_rate = :holiday_rate,
              rest_day_rate = :rest_day_rate,
              updated_by = :user_id,
              updated_at = NOW()
              WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':company_name', $company_name);
    $stmt->bindParam(':company_address', $company_address);
    $stmt->bindParam(':company_contact', $company_contact);
    $stmt->bindParam(':company_email', $company_email);
    $stmt->bindParam(':tax_rate', $tax_rate);
    $stmt->bindParam(':sss_rate', $sss_rate);
    $stmt->bindParam(':philhealth_rate', $philhealth_rate);
    $stmt->bindParam(':pagibig_rate', $pagibig_rate);
    $stmt->bindParam(':night_diff_rate', $night_diff_rate);
    $stmt->bindParam(':overtime_rate', $overtime_rate);
    $stmt->bindParam(':holiday_rate', $holiday_rate);
    $stmt->bindParam(':rest_day_rate', $rest_day_rate);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':id', $settings['id']);
    
    if ($stmt->execute()) {
        $success = "✅ Settings updated successfully!";
        $query = "SELECT * FROM tblcompany_settings ORDER BY id DESC LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $error = "❌ Failed to update settings!";
    }
}

// Handle Add User
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_user'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $cname = trim($_POST['cname']);
    $roleID = $_POST['roleID'];
    $email = trim($_POST['email']);
    $contact_no = trim($_POST['contact_no']);
    
    if (empty($username) || empty($password) || empty($cname)) {
        $user_error = "❌ Please fill in all required fields!";
    } else {
        $check = "SELECT id FROM tblusers WHERE username = :username";
        $check_stmt = $db->prepare($check);
        $check_stmt->bindParam(':username', $username);
        $check_stmt->execute();
        
        if ($check_stmt->rowCount() > 0) {
            $user_error = "⚠️ Username already exists!";
        } else {
            $query = "INSERT INTO tblusers (username, password, cname, roleID, email, contact_no, isActive) 
                      VALUES (:username, :password, :cname, :roleID, :email, :contact_no, 1)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':password', $password);
            $stmt->bindParam(':cname', $cname);
            $stmt->bindParam(':roleID', $roleID);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':contact_no', $contact_no);
            
            if ($stmt->execute()) {
                $user_success = "✅ User added successfully!";
                $_POST = array();
            } else {
                $user_error = "❌ Failed to add user!";
            }
        }
    }
}

// Get all users
$users = [];
$query = "SELECT id, username, cname, roleID, email, contact_no, isActive, created_at FROM tblusers ORDER BY id DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle Delete User
if (isset($_GET['delete_user'])) {
    $id = $_GET['delete_user'];
    if ($id != $user_id) {
        $query = "DELETE FROM tblusers WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $id);
        if ($stmt->execute()) {
            $user_success = "✅ User deleted successfully!";
        } else {
            $user_error = "❌ Failed to delete user!";
        }
    } else {
        $user_error = "⚠️ You cannot delete your own account!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings | Payroll Pro</title>
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
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-group input,
        .form-group select,
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

        .form-group input:focus,
        .form-group select:focus,
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

        .form-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
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
        .badge-active {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }

        /* Action Buttons */
        .btn-delete {
            background: #f5f5f5;
            color: #dc2626;
            padding: 6px 14px;
            border-radius: 12px;
            text-decoration: none;
            font-size: 12px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid #e5e7eb;
            transition: all 0.3s;
        }

        .btn-delete:hover {
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

        /* Tabs */
        .nav-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 24px;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 10px;
        }

        .nav-tab {
            padding: 10px 24px;
            background: none;
            border: none;
            cursor: pointer;
            font-weight: 600;
            color: #6b7280;
            border-radius: 40px;
            transition: all 0.3s;
            font-size: 14px;
        }

        .nav-tab:hover {
            background: #f5f5f5;
            color: #000000;
        }

        .nav-tab.active {
            background: #000000;
            color: #ffffff;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .main-content {
                padding: 20px;
            }
            .form-row, .form-grid {
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
            .nav-tabs {
                flex-wrap: wrap;
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
                <a href="holidays.php" class="nav-link">
                    <i class="fas fa-calendar-day"></i>
                    <span>Holidays</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="settings.php" class="nav-link active">
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
            <h1>System Settings</h1>
            <p>Configure company information, contribution rates, and user management</p>
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
        
        <?php if(isset($user_success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $user_success; ?>
            </div>
        <?php endif; ?>
        
        <?php if(isset($user_error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $user_error; ?>
            </div>
        <?php endif; ?>

        <!-- Tabs -->
        <div class="nav-tabs">
            <button class="nav-tab active" onclick="showTab('company')">Company Settings</button>
            <button class="nav-tab" onclick="showTab('rates')">Rates & Contributions</button>
            <button class="nav-tab" onclick="showTab('users')">User Management</button>
        </div>

        <!-- Company Settings Tab -->
        <div id="tab-company" class="tab-content active">
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-building"></i> Company Information</h3>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Company Name *</label>
                                <input type="text" name="company_name" value="<?php echo htmlspecialchars($settings['company_name']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Company Address *</label>
                                <input type="text" name="company_address" value="<?php echo htmlspecialchars($settings['company_address']); ?>" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Contact Number *</label>
                                <input type="text" name="company_contact" value="<?php echo htmlspecialchars($settings['company_contact']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Email Address *</label>
                                <input type="email" name="company_email" value="<?php echo htmlspecialchars($settings['company_email']); ?>" required>
                            </div>
                        </div>
                        <button type="submit" name="update_settings" class="btn-primary">
                            <i class="fas fa-save"></i> Save Settings
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Rates & Contributions Tab -->
        <div id="tab-rates" class="tab-content">
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-percent"></i> Contribution Rates</h3>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Tax Rate (%)</label>
                                <input type="number" step="0.01" name="tax_rate" value="<?php echo $settings['tax_rate']; ?>">
                            </div>
                            <div class="form-group">
                                <label>SSS Rate (%)</label>
                                <input type="number" step="0.01" name="sss_rate" value="<?php echo $settings['sss_rate']; ?>">
                            </div>
                            <div class="form-group">
                                <label>PhilHealth Rate (%)</label>
                                <input type="number" step="0.01" name="philhealth_rate" value="<?php echo $settings['philhealth_rate']; ?>">
                            </div>
                            <div class="form-group">
                                <label>Pag-IBIG Rate (%)</label>
                                <input type="number" step="0.01" name="pagibig_rate" value="<?php echo $settings['pagibig_rate']; ?>">
                            </div>
                        </div>
                        <div class="card-header" style="margin-top: 20px; padding: 0 0 20px 0;">
                            <h3><i class="fas fa-clock"></i> Differential Rates</h3>
                        </div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Night Differential Rate (x)</label>
                                <input type="number" step="0.01" name="night_diff_rate" value="<?php echo $settings['night_diff_rate']; ?>">
                            </div>
                            <div class="form-group">
                                <label>Overtime Rate (x)</label>
                                <input type="number" step="0.01" name="overtime_rate" value="<?php echo $settings['overtime_rate']; ?>">
                            </div>
                            <div class="form-group">
                                <label>Holiday Rate (x)</label>
                                <input type="number" step="0.01" name="holiday_rate" value="<?php echo $settings['holiday_rate']; ?>">
                            </div>
                            <div class="form-group">
                                <label>Rest Day Rate (x)</label>
                                <input type="number" step="0.01" name="rest_day_rate" value="<?php echo $settings['rest_day_rate']; ?>">
                            </div>
                        </div>
                        <button type="submit" name="update_settings" class="btn-primary">
                            <i class="fas fa-save"></i> Save Rates
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- User Management Tab -->
        <div id="tab-users" class="tab-content">
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-user-plus"></i> Add New User</h3>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Username *</label>
                                <input type="text" name="username" placeholder="Enter username" required>
                            </div>
                            <div class="form-group">
                                <label>Password *</label>
                                <input type="password" name="password" placeholder="Enter password" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Full Name *</label>
                                <input type="text" name="cname" placeholder="Enter full name" required>
                            </div>
                            <div class="form-group">
                                <label>Role *</label>
                                <select name="roleID" required>
                                    <option value="1">Administrator</option>
                                    <option value="2">HR Staff</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" name="email" placeholder="Enter email">
                            </div>
                            <div class="form-group">
                                <label>Contact Number</label>
                                <input type="text" name="contact_no" placeholder="Enter contact number">
                            </div>
                        </div>
                        <button type="submit" name="add_user" class="btn-primary">
                            <i class="fas fa-user-plus"></i> Add User
                        </button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-users"></i> System Users</h3>
                </div>
                <div style="overflow-x: auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Full Name</th>
                                <th>Role</th>
                                <th>Email</th>
                                <th>Contact</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </thead>
                        <tbody>
                            <?php if(count($users) > 0): ?>
                                <?php foreach($users as $user): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($user['cname']); ?></td>
                                    <td><?php echo $user['roleID'] == 1 ? 'Administrator' : 'HR Staff'; ?></td>
                                    <td><?php echo $user['email'] ?: '-'; ?></td>
                                    <td><?php echo $user['contact_no'] ?: '-'; ?></td>
                                    <td><span class="badge-active">Active</span></td>
                                    <td>
                                        <?php if($user['id'] != $user_id): ?>
                                            <a href="?delete_user=<?php echo $user['id']; ?>" class="btn-delete" onclick="return confirm('Delete this user?')">
                                                <i class="fas fa-trash"></i> Delete
                                            </a>
                                        <?php else: ?>
                                            <span style="color: #9ca3af; font-size: 12px;">Current User</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 40px; color: #6b7280;">
                                        <i class="fas fa-users" style="font-size: 48px; margin-bottom: 10px; opacity: 0.5;"></i><br>
                                        No users found.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
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

        // Tab Navigation
        function showTab(tab) {
            document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.nav-tab').forEach(btn => btn.classList.remove('active'));
            
            document.getElementById('tab-' + tab).classList.add('active');
            event.target.classList.add('active');
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