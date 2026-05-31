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

// Handle Add Employee
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_employee'])) {
    $employee_no = trim($_POST['employee_no']);
    $firstname = trim($_POST['firstname']);
    $lastname = trim($_POST['lastname']);
    $middlename = trim($_POST['middlename']);
    $gender = $_POST['gender'];
    $birthdate = $_POST['birthdate'] ?: null;
    $address = trim($_POST['address']);
    $contact_no = trim($_POST['contact_no']);
    $email = trim($_POST['email']);
    $position = trim($_POST['position']);
    $department = trim($_POST['department']);
    $salary_rate = floatval($_POST['salary_rate']);
    $salary_type = $_POST['salary_type'];
    $hire_date = $_POST['hire_date'];
    
    // Validate required fields
    if (empty($employee_no) || empty($firstname) || empty($lastname) || empty($position) || empty($department) || $salary_rate <= 0 || empty($hire_date)) {
        $error = "❌ Please fill in all required fields!";
    } else {
        // Check if employee number already exists
        $check_query = "SELECT id FROM tblemployees WHERE employee_no = :employee_no";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':employee_no', $employee_no);
        $check_stmt->execute();
        
        if ($check_stmt->rowCount() > 0) {
            $error = "❌ Employee number already exists!";
        } else {
            $hourly_rate = 0;
            $daily_rate = 0;
            if ($salary_type == 'monthly') {
                $daily_rate = $salary_rate / 22;
                $hourly_rate = $daily_rate / 8;
            } elseif ($salary_type == 'daily') {
                $daily_rate = $salary_rate;
                $hourly_rate = $daily_rate / 8;
            } else {
                $hourly_rate = $salary_rate;
                $daily_rate = $hourly_rate * 8;
            }
            
            $query = "INSERT INTO tblemployees (employee_no, firstname, lastname, middlename, gender, birthdate, address, contact_no, email, position, department, salary_rate, salary_type, hourly_rate, daily_rate, hire_date, status) 
                      VALUES (:employee_no, :firstname, :lastname, :middlename, :gender, :birthdate, :address, :contact_no, :email, :position, :department, :salary_rate, :salary_type, :hourly_rate, :daily_rate, :hire_date, 'active')";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':employee_no', $employee_no);
            $stmt->bindParam(':firstname', $firstname);
            $stmt->bindParam(':lastname', $lastname);
            $stmt->bindParam(':middlename', $middlename);
            $stmt->bindParam(':gender', $gender);
            $stmt->bindParam(':birthdate', $birthdate);
            $stmt->bindParam(':address', $address);
            $stmt->bindParam(':contact_no', $contact_no);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':position', $position);
            $stmt->bindParam(':department', $department);
            $stmt->bindParam(':salary_rate', $salary_rate);
            $stmt->bindParam(':salary_type', $salary_type);
            $stmt->bindParam(':hourly_rate', $hourly_rate);
            $stmt->bindParam(':daily_rate', $daily_rate);
            $stmt->bindParam(':hire_date', $hire_date);
            
            if ($stmt->execute()) {
                $success = "✅ Employee added successfully!";
                // Clear POST data to prevent re-submission
                $_POST = array();
            } else {
                $error = "❌ Failed to add employee!";
            }
        }
    }
}

// Handle Delete Employee
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $query = "DELETE FROM tblemployees WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $id);
    if ($stmt->execute()) {
        $success = "✅ Employee deleted successfully!";
    } else {
        $error = "❌ Failed to delete employee!";
    }
}

// Get all employees
$employees = [];
$query = "SELECT * FROM tblemployees ORDER BY id DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employees | Payroll Pro</title>
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
            padding: 12px 24px;
            border-radius: 40px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            font-size: 14px;
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

        .badge-inactive {
            background: #ffebee;
            color: #c62828;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }

        .badge-resigned {
            background: #fff3e0;
            color: #ef6c00;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }

        /* Action Buttons */
        .btn-edit, .btn-delete {
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

        .btn-edit {
            background: #f5f5f5;
            color: #000000;
            border: 1px solid #e5e7eb;
        }

        .btn-edit:hover {
            background: #000000;
            color: #ffffff;
            border-color: #000000;
        }

        .btn-delete {
            background: #f5f5f5;
            color: #dc2626;
            border: 1px solid #e5e7eb;
        }

        .btn-delete:hover {
            background: #dc2626;
            color: #ffffff;
            border-color: #dc2626;
        }

        /* Modal */
        .modal {
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

        .modal-content {
            background: #ffffff;
            border-radius: 28px;
            width: 90%;
            max-width: 700px;
            max-height: 85vh;
            overflow-y: auto;
            border: 1px solid #e5e7eb;
        }

        .modal-header {
            padding: 24px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            color: #000000;
            font-size: 20px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-body {
            padding: 24px;
        }

        /* Form Elements */
        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: #6b7280;
            font-size: 12px;
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
            font-size: 13px;
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
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }

        .btn-submit {
            background: #000000;
            color: #ffffff;
            border: none;
            padding: 12px;
            border-radius: 40px;
            cursor: pointer;
            width: 100%;
            font-weight: 600;
            margin-top: 8px;
            font-size: 14px;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-submit:hover {
            background: #333333;
            transform: translateY(-2px);
        }

        .close {
            font-size: 28px;
            cursor: pointer;
            color: #9ca3af;
            transition: all 0.3s;
        }

        .close:hover {
            color: #000000;
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
                <a href="employees.php" class="nav-link active">
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
    <div class="main-content" id="mainContent">
        <div class="page-header">
            <h1>Employee Management</h1>
            <p>Manage employee records, personal information, and employment details</p>
        </div>

        <?php if(isset($success) && $success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <?php if(isset($error) && $error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-users"></i> Employee Directory</h3>
                <button class="btn-add" onclick="openModal()">
                    <i class="fas fa-plus"></i> Add New Employee
                </button>
            </div>
            <div style="overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Employee No.</th>
                            <th>Name</th>
                            <th>Position</th>
                            <th>Department</th>
                            <th>Salary</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </thead>
                    <tbody>
                        <?php if(count($employees) > 0): ?>
                            <?php foreach($employees as $emp): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($emp['employee_no']); ?></strong></td>
                                <td>
                                    <?php echo htmlspecialchars($emp['firstname'] . ' ' . $emp['lastname']); ?>
                                    <?php if(!empty($emp['middlename'])): ?>
                                    <br><small style="color:#6b7280;"><?php echo htmlspecialchars($emp['middlename']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($emp['position']); ?></td>
                                <td><?php echo htmlspecialchars($emp['department']); ?></td>
                                <td>₱<?php echo number_format($emp['salary_rate'], 2); ?></td>
                                <td>
                                    <?php if($emp['status'] == 'active'): ?>
                                        <span class="badge-active">ACTIVE</span>
                                    <?php elseif($emp['status'] == 'inactive'): ?>
                                        <span class="badge-inactive">INACTIVE</span>
                                    <?php else: ?>
                                        <span class="badge-resigned">RESIGNED</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="#" class="btn-edit" onclick="editEmployee(<?php echo $emp['id']; ?>)">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <a href="?delete=<?php echo $emp['id']; ?>" class="btn-delete" onclick="return confirm('Delete this employee?')">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 40px; color: #6b7280;">
                                    <i class="fas fa-users" style="font-size: 48px; margin-bottom: 10px; opacity: 0.5;"></i><br>
                                    No employees found. Click "Add New Employee" to get started.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add Employee Modal -->
    <div id="employeeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-user-plus"></i> Add New Employee</h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <form method="POST" class="modal-body" id="employeeForm">
                <div class="form-row">
                    <div class="form-group">
                        <label>Employee No.*</label>
                        <input type="text" name="employee_no" placeholder="e.g., EMP-2024-001" required value="<?php echo isset($_POST['employee_no']) ? htmlspecialchars($_POST['employee_no']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>First Name*</label>
                        <input type="text" name="firstname" placeholder="First name" required value="<?php echo isset($_POST['firstname']) ? htmlspecialchars($_POST['firstname']) : ''; ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Last Name*</label>
                        <input type="text" name="lastname" placeholder="Last name" required value="<?php echo isset($_POST['lastname']) ? htmlspecialchars($_POST['lastname']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>Middle Name</label>
                        <input type="text" name="middlename" placeholder="Middle name (optional)" value="<?php echo isset($_POST['middlename']) ? htmlspecialchars($_POST['middlename']) : ''; ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Gender*</label>
                        <select name="gender" required>
                            <option value="Male" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'Male') ? 'selected' : ''; ?>>Male</option>
                            <option value="Female" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'Female') ? 'selected' : ''; ?>>Female</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Birthdate</label>
                        <input type="date" name="birthdate" value="<?php echo isset($_POST['birthdate']) ? $_POST['birthdate'] : ''; ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label>Address</label>
                    <textarea name="address" rows="2" placeholder="Complete address"><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Contact No.</label>
                        <input type="text" name="contact_no" placeholder="e.g., 09123456789" value="<?php echo isset($_POST['contact_no']) ? htmlspecialchars($_POST['contact_no']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" placeholder="employee@company.com" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Position*</label>
                        <input type="text" name="position" placeholder="Job title" required value="<?php echo isset($_POST['position']) ? htmlspecialchars($_POST['position']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>Department*</label>
                        <input type="text" name="department" placeholder="Department" required value="<?php echo isset($_POST['department']) ? htmlspecialchars($_POST['department']) : ''; ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Salary Rate*</label>
                        <input type="number" step="0.01" name="salary_rate" placeholder="0.00" required value="<?php echo isset($_POST['salary_rate']) ? $_POST['salary_rate'] : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>Salary Type*</label>
                        <select name="salary_type" required>
                            <option value="monthly" <?php echo (isset($_POST['salary_type']) && $_POST['salary_type'] == 'monthly') ? 'selected' : ''; ?>>Monthly</option>
                            <option value="daily" <?php echo (isset($_POST['salary_type']) && $_POST['salary_type'] == 'daily') ? 'selected' : ''; ?>>Daily</option>
                            <option value="hourly" <?php echo (isset($_POST['salary_type']) && $_POST['salary_type'] == 'hourly') ? 'selected' : ''; ?>>Hourly</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Hire Date*</label>
                    <input type="date" name="hire_date" required value="<?php echo isset($_POST['hire_date']) ? $_POST['hire_date'] : date('Y-m-d'); ?>">
                </div>
                <button type="submit" name="add_employee" class="btn-submit">
                    <i class="fas fa-save"></i> Save Employee
                </button>
            </form>
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

        function openModal() { 
            document.getElementById('employeeModal').style.display = 'flex'; 
        }
        
        function closeModal() { 
            document.getElementById('employeeModal').style.display = 'none'; 
        }
        
        function editEmployee(id) {
            alert('Edit function coming soon! Employee ID: ' + id);
        }
        
        window.onclick = function(event) { 
            if (event.target.classList.contains('modal')) closeModal(); 
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