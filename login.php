<?php
session_start();
require_once 'config/database.php';

if (isset($_SESSION['user_id'])) {
    switch($_SESSION['roleID']) {
        case 1: header("Location: admin/dashboard.php"); break;
        case 2: header("Location: admin/dashboard.php"); break;
        default: header("Location: admin/dashboard.php");
    }
    exit();
}

$database = new Database();
$db = $database->getConnection();
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    try {
        $query = "SELECT id, username, cname, roleID, isActive FROM tblusers 
                  WHERE username = :username AND password = :password AND isActive = 1";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':password', $password);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            $role_names = [1 => 'ADMIN', 2 => 'HR'];
            
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['cname'] = $user['cname'];
            $_SESSION['roleID'] = $user['roleID'];
            $_SESSION['role_name'] = $role_names[$user['roleID']];
            
            $log_query = "INSERT INTO tbluser_logs (username, user_action) VALUES (:username, 'LOGIN')";
            $log_stmt = $db->prepare($log_query);
            $log_stmt->bindParam(':username', $username);
            $log_stmt->execute();
            
            header("Location: admin/dashboard.php");
            exit();
        } else {
            $error = "Invalid username or password!";
            $log_query = "INSERT INTO tbluser_logs (username, user_action) VALUES (:username, 'FAILED LOGIN')";
            $log_stmt = $db->prepare($log_query);
            $log_stmt->bindParam(':username', $username);
            $log_stmt->execute();
        }
    } catch (Exception $e) {
        $error = "Login failed. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll Pro | Login</title>
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
            background: #ffffff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .login-wrapper {
            width: 100%;
            max-width: 480px;
            position: relative;
            z-index: 10;
            padding: 20px;
        }

        .login-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 32px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            transition: transform 0.3s ease;
        }

        .login-card:hover {
            transform: translateY(-5px);
        }

        .login-brand {
            background: #000000;
            padding: 40px 32px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .logo-icon {
            width: 70px;
            height: 70px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            position: relative;
            z-index: 1;
            border: 1px solid rgba(255,255,255,0.2);
        }

        .logo-icon i {
            font-size: 32px;
            color: white;
        }

        .login-brand h1 {
            color: white;
            font-size: 28px;
            font-weight: 800;
            letter-spacing: -0.5px;
            margin-bottom: 8px;
            position: relative;
            z-index: 1;
        }

        .login-brand p {
            color: rgba(255, 255, 255, 0.7);
            font-size: 14px;
            font-weight: 500;
            position: relative;
            z-index: 1;
        }

        .login-form {
            padding: 40px 32px;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 16px;
            margin-bottom: 24px;
            font-size: 13px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            background: #fef2f2;
            color: #dc2626;
            border-left: 3px solid #dc2626;
        }

        .input-group {
            margin-bottom: 24px;
        }

        .input-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 13px;
            font-weight: 600;
            color: #374151;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 18px;
        }

        .input-wrapper input {
            width: 100%;
            padding: 14px 16px 14px 48px;
            border: 2px solid #e5e7eb;
            border-radius: 20px;
            font-size: 15px;
            font-family: 'Inter', sans-serif;
            transition: all 0.3s;
            background: #ffffff;
        }

        .input-wrapper input:focus {
            outline: none;
            border-color: #000000;
            box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.05);
        }

        .toggle-password {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            cursor: pointer;
            font-size: 18px;
        }

        .toggle-password:hover {
            color: #000000;
        }

        .btn-login {
            width: 100%;
            padding: 14px;
            background: #000000;
            color: white;
            border: none;
            border-radius: 20px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 8px;
        }

        .btn-login:hover {
            background: #1a1a1a;
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.2);
        }

        .footer-text {
            text-align: center;
            margin-top: 28px;
            padding-top: 24px;
            border-top: 1px solid #e5e7eb;
            font-size: 11px;
            color: #9ca3af;
        }

        @media (max-width: 480px) {
            .login-form {
                padding: 32px 24px;
            }
            .login-brand {
                padding: 32px 24px;
            }
            .login-brand h1 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-card">
            <div class="login-brand">
                <div class="logo-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <h1>Payroll Pro</h1>
                <p>Enterprise Payroll Management System</p>
            </div>
            
            <div class="login-form">
                <?php if ($error): ?>
                    <div class="alert">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="input-group">
                        <label>Username</label>
                        <div class="input-wrapper">
                            <i class="fas fa-user input-icon"></i>
                            <input type="text" name="username" placeholder="Enter your username" required>
                        </div>
                    </div>
                    
                    <div class="input-group">
                        <label>Password</label>
                        <div class="input-wrapper">
                            <i class="fas fa-lock input-icon"></i>
                            <input type="password" name="password" id="password" placeholder="Enter your password" required>
                            <i class="fas fa-eye toggle-password" onclick="togglePassword()"></i>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-login">
                        <i class="fas fa-arrow-right-to-bracket"></i> Sign In
                    </button>
                </form>
                
                <div class="footer-text">
                    <i class="fas fa-shield-alt"></i> Secured Access | Authorized Personnel Only
                </div>
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            const password = document.getElementById('password');
            const icon = event.currentTarget;
            
            if (password.type === 'password') {
                password.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                password.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>