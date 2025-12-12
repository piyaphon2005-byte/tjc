<?php
session_start();
// 1. ตรวจสอบ Login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// เปิด Error Reporting เพื่อดูปัญหา (ถ้ามี)
error_reporting(E_ALL);
ini_set('display_errors', 1);

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role']; // ใช้ role จาก session

// ============================================
// 2. เชื่อมต่อฐานข้อมูล (แบบ TiDB Cloud SSL)
// ============================================
$servername = "gateway01.ap-southeast-1.prod.aws.tidbcloud.com";
$username_db = "2zJFS48pitnR2QG.root"; 
$password_db = "DF43GROp1tGLs8Gp"; 
$dbname = "tjc_db";
$port = 4000;

$conn = mysqli_init();
mysqli_options($conn, 25, false); // MYSQLI_OPT_SSL_VERIFY_SERVER_CERT
// เชื่อมต่อ
if (!mysqli_real_connect($conn, $servername, $username_db, $password_db, $dbname, $port, NULL, MYSQLI_CLIENT_SSL)) {
    die("Connection failed: " . mysqli_connect_error());
}
$conn->set_charset("utf8");

$message = "";

// 3. จัดการอัปโหลดรูปภาพ
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['profile_img'])) {
    $file = $_FILES['profile_img'];
    
    // เช็ค Error จากการอัปโหลด
    if ($file['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            
            // ✅ แก้ไขส่วนนี้: ใช้ Path เต็ม และ ปลดล็อก Permission
            $target_dir = __DIR__ . "/uploads/profiles/";
            
            // ถ้าไม่มีโฟลเดอร์ ให้สร้างใหม่
            if (!file_exists($target_dir)) { 
                @mkdir($target_dir, 0777, true); 
            }
            
            // 💥 สั่งปลดล็อกโฟลเดอร์ซ้ำ (สำคัญมากสำหรับ Render)
            @chmod($target_dir, 0777);
            
            // ตั้งชื่อไฟล์ใหม่กันซ้ำ (user_id_timestamp.jpg)
            $new_name = "user_" . $user_id . "_" . time() . "." . $ext;
            $target_file = $target_dir . $new_name;
            
            if (move_uploaded_file($file['tmp_name'], $target_file)) {
                // อัปเดตชื่อไฟล์ลงฐานข้อมูล
                $sql_update = "UPDATE users SET avatar = ? WHERE id = ?";
                $stmt = $conn->prepare($sql_update);
                $stmt->bind_param("si", $new_name, $user_id);
                
                if ($stmt->execute()) {
                    // ✅ อัปเดต Session ให้แสดงผลรูปใหม่ทันที
                    $_SESSION['avatar'] = $new_name; 
                    $message = "<div class='alert success'>✅ อัปเดตโปรไฟล์เรียบร้อยแล้ว!</div>";
                    
                    // บังคับ Refresh หน้าเว็บเพื่อเคลียร์ Cache รูปเก่า
                    echo "<meta http-equiv='refresh' content='1'>"; 
                } else {
                    $message = "<div class='alert error'>❌ บันทึกฐานข้อมูลล้มเหลว: " . $stmt->error . "</div>";
                }
                $stmt->close();
            } else {
                $message = "<div class='alert error'>❌ ย้ายไฟล์ไม่สำเร็จ (ตรวจสอบ Permission โฟลเดอร์ uploads)</div>";
            }
        } else {
            $message = "<div class='alert error'>⚠️ อนุญาตเฉพาะไฟล์ JPG, PNG, GIF เท่านั้น</div>";
        }
    } else {
        // กรณีไม่ได้เลือกไฟล์ หรือไฟล์มีปัญหา
        if ($file['error'] != 4) { // error 4 คือไม่ได้เลือกไฟล์ (ไม่ต้องแจ้งเตือน)
            $message = "<div class='alert error'>❌ เกิดข้อผิดพลาดในการอัปโหลด (Code: " . $file['error'] . ")</div>";
        }
    }
}

// 4. ดึงข้อมูลล่าสุดของผู้ใช้มาแสดง
$sql_user = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($sql_user);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result_user = $stmt->get_result();
$user_data = $result_user->fetch_assoc();

// หารูปโปรไฟล์ (ถ้าไม่มีใช้รูป Default)
// ใช้ time() ต่อท้ายเพื่อป้องกัน Browser จำรูปเก่า (Cache)
$avatar_path = "uploads/profiles/" . $user_data['avatar'];
// เช็คไฟล์จริงโดยใช้ path เต็ม เพื่อความชัวร์
if (!empty($user_data['avatar']) && file_exists(__DIR__ . "/" . $avatar_path)) {
    $avatar_url = $avatar_path . "?t=" . time(); 
} else {
    $avatar_url = "https://via.placeholder.com/150?text=USER";
}

?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตั้งค่าโปรไฟล์ - TJC</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        :root { --primary: #4e54c8; --secondary: #8f94fb; }
        body { font-family: 'Sarabun', sans-serif; background: #f0f2f5; margin: 0; padding: 0; min-height: 100vh; display: flex; justify-content: center; align-items: center; }
        
        .profile-card {
            background: white;
            width: 100%;
            max-width: 450px;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            text-align: center;
            position: relative;
            margin-top: 60px; 
        }
        
        @media (min-width: 768px) {
            body { padding-left: 250px; } 
            .profile-card { margin-top: 0; }
        }

        .back-btn {
            position: absolute;
            top: 20px;
            left: 20px;
            color: #666;
            text-decoration: none;
            font-size: 14px;
            background: #f8f9fa;
            padding: 8px 15px;
            border-radius: 20px;
            transition: 0.3s;
        }
        .back-btn:hover { background: #e2e6ea; }

        h2 { margin: 0 0 5px 0; color: var(--primary); }
        p.role { color: #888; font-size: 14px; margin-bottom: 25px; }

        .avatar-wrapper {
            position: relative;
            width: 150px;
            height: 150px;
            margin: 0 auto 20px;
        }
        .avatar-img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid #fff;
            box-shadow: 0 5px 15px rgba(0,0,0,0.15);
        }
        .camera-icon {
            position: absolute;
            bottom: 5px;
            right: 5px;
            background: var(--primary);
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            cursor: pointer;
            border: 3px solid white;
            transition: 0.3s;
        }
        .camera-icon:hover { transform: scale(1.1); background: var(--secondary); }

        input[type="file"] { display: none; }
        
        .info-group { text-align: left; margin-bottom: 15px; }
        .info-group label { display: block; font-weight: bold; font-size: 14px; color: #555; margin-bottom: 5px; }
        .info-group input { 
            width: 100%; 
            padding: 12px; 
            border: 1px solid #ddd; 
            border-radius: 10px; 
            background: #f9f9f9; 
            color: #555;
            font-family: 'Sarabun';
            box-sizing: border-box;
        }

        .btn-save {
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 50px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            box-shadow: 0 5px 15px rgba(78, 84, 200, 0.3);
            transition: 0.3s;
            margin-top: 10px;
            width: 100%;
        }
        .btn-save:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(78, 84, 200, 0.4); }

        .alert { padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>
    
    <?php include 'sidebar.php'; ?>
    
    <div class="profile-card">
        <a href="<?php echo ($role == 'manager') ? 'Dashboard.php' : 'StaffHistory.php'; ?>" class="back-btn">
            <i class="fas fa-arrow-left"></i> กลับ
        </a>

        <?php echo $message; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="avatar-wrapper">
                <img src="<?php echo $avatar_url; ?>" id="preview" class="avatar-img">
                <label for="fileInput" class="camera-icon">
                    <i class="fas fa-camera"></i>
                </label>
                <input type="file" name="profile_img" id="fileInput" accept="image/*" onchange="previewImage(event)">
            </div>

            <h2><?php echo htmlspecialchars($user_data['fullname']); ?></h2>
            <p class="role"><?php echo strtoupper($user_data['role']); ?></p>

            <div class="info-group">
                <label>ชื่อผู้ใช้ (Username)</label>
                <input type="text" value="<?php echo htmlspecialchars($user_data['username']); ?>" readonly>
            </div>

            <div class="info-group">
                <label>ชื่อ-นามสกุล</label>
                <input type="text" value="<?php echo htmlspecialchars($user_data['fullname']); ?>" readonly>
            </div>

            <button type="submit" class="btn-save">บันทึกรูปโปรไฟล์</button>
        </form>
    </div>

    <script>
        function previewImage(event) {
            var reader = new FileReader();
            reader.onload = function(){
                var output = document.getElementById('preview');
                output.src = reader.result;
            };
            if(event.target.files[0]) {
                reader.readAsDataURL(event.target.files[0]);
            }
        }
    </script>

</body>
</html>