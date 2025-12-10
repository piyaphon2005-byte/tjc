<?php
session_start();
require_once 'db_connect.php';
$conn = new mysqli($servername, $username, $password, $dbname);
$conn->set_charset("utf8");

$error = ''; 

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $sql = "SELECT id, fullname, role, avatar FROM users WHERE username = ? AND password = ?";
    
    if ($conn->connect_error) {
        $error = "Database connection failed.";
    } else {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $username, $password);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['fullname'] = $row['fullname'];
            $_SESSION['role'] = $row['role'];
            $_SESSION['avatar'] = $row['avatar'];

            // ✅ แก้ไขตรงนี้: ส่งทุกคนไปหน้า "เมนูหลัก" (Main.php)
            header("Location: Main.php");
            exit();
        } else {
            $error = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง";
        }
    }
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ - TJC System</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        :root { --primary: #4e54c8; --primary-light: #8f94fb; }
        * { box-sizing: border-box; } 

        body {
            font-family: 'Sarabun', sans-serif;
            margin: 0;
            padding: 20px;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: #f4f7f6; 
            color: #333;
        }

        .login-card {
            background: #ffffff;
            border-radius: 15px;
            padding: 40px 30px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
            text-align: center;
            border: 1px solid #e0e0e0;
        }

        .logo-img {
            max-width: 150px;
            height: auto;
            margin-bottom: 20px;
            display: block;
            margin-left: auto;
            margin-right: auto;
        }

        h2 {
            font-size: 24px;
            color: var(--primary);
            margin: 0 0 25px 0;
            font-weight: 800;
        }

        .input-group {
            position: relative;
            margin-bottom: 20px;
            width: 100%;
        }
        .input-group i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #aaa;
            font-size: 18px;
            z-index: 2;
        }
        
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border: 2px solid #eee;
            border-radius: 8px;
            background: #fafafa;
            font-family: 'Sarabun', sans-serif;
            font-size: 16px;
            color: #333;
            transition: 0.3s;
        }
        
        input:focus {
            outline: none;
            border-color: var(--primary);
            background: #fff;
        }

        .btn-login {
            margin-top: 10px;
            background: var(--primary);
            color: white;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            width: 100%;
            transition: background 0.3s;
        }
        .btn-login:hover {
            background: #3b40a3;
        }

        .error-message {
            color: #e74c3c;
            background: #fdeaea;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            border: 1px solid #fadbd8;
        }
    </style>
</head>
<body>

    <div class="login-card">
        <img src="images/LOgoTJC.png" alt="TJC Logo" class="logo-img">
        
        <h2>เข้าสู่ระบบ</h2>

        <?php if (!empty($error)): ?>
            <div class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <div class="input-group">
                <i class="fas fa-user"></i>
                <input type="text" name="username" placeholder="ชื่อผู้ใช้" required autocomplete="off">
            </div>
            
            <div class="input-group">
                <i class="fas fa-key"></i>
                <input type="password" name="password" placeholder="รหัสผ่าน" required>
            </div>
            
            <button type="submit" class="btn-login">
                เข้าใช้งาน
            </button>
        </form>
    </div>

</body>
</html>