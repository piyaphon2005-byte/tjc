<?php
session_start();

// --- ส่วนการเชื่อมต่อฐานข้อมูล TiDB (แก้ไขแล้ว) ---
$servername = "gateway01.ap-southeast-1.prod.aws.tidbcloud.com";
$username = "2zJFS48pitnR2QG.root";
$password = "DF43GROp1tGLs8Gp"; 
$dbname = "tjc_db";
$port = 4000;

// 1. เริ่มต้น mysqli object
$conn = mysqli_init();
if (!$conn) {
    die("Connection failed: mysqli_init() error");
}

// 2. ตั้งค่า SSL (จำเป็นสำหรับ TiDB)
mysqli_options($conn, MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, true);

// 3. เชื่อมต่อด้วย MYSQLI_CLIENT_SSL
$connected = mysqli_real_connect(
    $conn, 
    $servername, 
    $username, 
    $password, 
    $dbname, 
    $port, 
    NULL, 
    MYSQLI_CLIENT_SSL
);

if (!$connected) {
    // ถ้าเชื่อมต่อไม่ได้ ให้หยุดและแจ้ง error
    die("Database Connection Error: " . mysqli_connect_error());
}

$conn->set_charset("utf8");
// --- จบส่วนการเชื่อมต่อ ---

$error = ''; 

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // SQL Query
    $sql = "SELECT id, fullname, role, avatar FROM users WHERE username = ? AND password = ?";
    
    // เตรียม Statement
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        // เช็คเผื่อ SQL ผิดพลาด
        die("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("ss", $username, $password);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $_SESSION['user_id'] = $row['id'];
        $_SESSION['fullname'] = $row['fullname'];
        $_SESSION['role'] = $row['role'];
        $_SESSION['avatar'] = $row['avatar'];

        // ส่งไปหน้า Main.php
        header("Location: Main.php");
        exit();
    } else {
        $error = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง";
    }

    $stmt->close();
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
