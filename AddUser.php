<?php
session_start();
require_once 'auth.php';


// 2. เชื่อมต่อฐานข้อมูล
require_once 'db_connect.php';
$conn->set_charset("utf8");

$message = "";

// 3. บันทึกข้อมูลเมื่อกดปุ่ม
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user = $_POST['username'];
    $pass = $_POST['password'];
    $fname = $_POST['fullname'];
    $role = $_POST['role'];

    // เช็คว่า Username ซ้ำไหม
    $check_sql = "SELECT * FROM users WHERE username = '$user'";
    $check_result = $conn->query($check_sql);

    if ($check_result->num_rows > 0) {
        $message = "<div class='alert error'>❌ ชื่อผู้ใช้นี้มีอยู่ในระบบแล้ว</div>";
    } else {
        // เพิ่มผู้ใช้ใหม่
        $sql = "INSERT INTO users (username, password, fullname, role) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssss", $user, $pass, $fname, $role);

        if ($stmt->execute()) {
            $message = "<div class='alert success'>✅ เพิ่มผู้ใช้เรียบร้อยแล้ว!</div>";
        } else {
            $message = "<div class='alert error'>เกิดข้อผิดพลาด: " . $conn->error . "</div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เพิ่มผู้ใช้งาน - TJC System</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        :root { --primary: #4e54c8; --secondary: #8f94fb; --success: #2ecc71; --danger: #e74c3c; }
        body { font-family: 'Sarabun', sans-serif; background: #f0f2f5; margin: 0; padding: 0; min-height: 100vh; }
        
        .navbar { background: #4e54c8; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; color: white; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .navbar h2 { margin: 0; font-size: 20px; }
        .btn-back { color: white; text-decoration: none; background: rgba(255,255,255,0.2); padding: 8px 15px; border-radius: 20px; transition: 0.3s; font-size: 14px; }
        .btn-back:hover { background: rgba(255,255,255,0.3); }

        .container { max-width: 500px; margin: 50px auto; padding: 20px; }
        
        .card { background: white; padding: 30px; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .card h3 { text-align: center; color: var(--primary); margin-top: 0; margin-bottom: 25px; font-size: 24px; }

        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: bold; margin-bottom: 8px; color: #555; }
        .form-group input, .form-group select { width: 100%; padding: 12px; border: 2px solid #eee; border-radius: 10px; font-family: 'Sarabun'; font-size: 16px; box-sizing: border-box; transition: 0.3s; }
        .form-group input:focus, .form-group select:focus { border-color: var(--secondary); outline: none; }

        .btn-submit { width: 100%; background: linear-gradient(45deg, var(--primary), var(--secondary)); color: white; border: none; padding: 15px; font-size: 18px; font-weight: bold; border-radius: 10px; cursor: pointer; transition: 0.3s; box-shadow: 0 5px 15px rgba(78, 84, 200, 0.3); }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(78, 84, 200, 0.4); }

        .alert { padding: 15px; border-radius: 10px; margin-bottom: 20px; text-align: center; font-weight: bold; }
        .alert.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>
<?php include 'sidebar.php'; ?>
    <div class="navbar">
        <h2><i class="fas fa-user-plus"></i> เพิ่มพนักงานใหม่</h2>
        <a href="Dashboard.php" class="btn-back"><i class="fas fa-arrow-left"></i> กลับ Dashboard</a>
    </div>

    <div class="container">
        <div class="card">
            <h3>กรอกข้อมูลพนักงาน</h3>
            
            <?php echo $message; ?>

            <form method="POST">
                <div class="form-group">
                    <label>👤 ชื่อ-นามสกุล (ที่แสดงในรายงาน)</label>
                    <input type="text" name="fullname" placeholder="เช่น สมชาย ใจดี" required>
                </div>

                <div class="form-group">
                    <label>🔑 ชื่อผู้ใช้ (Username)</label>
                    <input type="text" name="username" placeholder="ภาษาอังกฤษ เช่น somchai" required>
                </div>

                <div class="form-group">
                    <label>🔒 รหัสผ่าน (Password)</label>
                    <input type="text" name="password" placeholder="ตั้งรหัสผ่าน..." required>
                </div>

                <div class="form-group">
                <label>🔰 ระดับสิทธิ์ (Role)</label>
                <select name="role" required>
                    <option value="">-- กรุณาเลือก --</option>
                    <?php
                    // ดึงข้อมูลจากตาราง master_roles
                    $role_sql = "SELECT * FROM master_roles ORDER BY id ASC";
                    $role_query = $conn->query($role_sql);
                    
                    while($r = $role_query->fetch_assoc()) {
                        echo '<option value="'.$r['role_name'].'">'.ucfirst($r['role_name']).'</option>';
                    }
                    ?>
                </select>
            </div>

                <button type="submit" class="btn-submit"><i class="fas fa-save"></i> บันทึกข้อมูล</button>
            </form>
        </div>
    </div>

</body>
</html>