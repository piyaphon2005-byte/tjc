<?php
session_start();
require_once 'auth.php';
require_once 'db_connect.php'; 

// เช็คสิทธิ์ Admin
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'admin') {
    echo "คุณไม่มีสิทธิ์เข้าถึงหน้านี้";
    exit();
}

$message = "";

// --- ฟังก์ชันเพิ่ม Role ---
if (isset($_POST['add_role'])) {
    $new_role = trim($_POST['role_name']);
    if (!empty($new_role)) {
        // เช็คว่าซ้ำไหม
        $check = $conn->query("SELECT * FROM master_roles WHERE role_name = '$new_role'");
        if ($check->num_rows == 0) {
            $stmt = $conn->prepare("INSERT INTO master_roles (role_name) VALUES (?)");
            $stmt->bind_param("s", $new_role);
            if ($stmt->execute()) {
                $message = "<div class='alert success'>✅ เพิ่มตำแหน่ง '$new_role' เรียบร้อย</div>";
            } else {
                $message = "<div class='alert error'>❌ เกิดข้อผิดพลาด: " . $conn->error . "</div>";
            }
        } else {
            $message = "<div class='alert error'>⚠️ ตำแหน่งนี้มีอยู่แล้ว</div>";
        }
    }
}

// --- ฟังก์ชันลบ Role ---
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $conn->query("DELETE FROM master_roles WHERE id = $id");
    
    // ✅ แก้ไข: ชื่อไฟล์ให้ตรงกับชื่อจริง (ManagerRoles.php)
    header("Location: ManagerRoles.php"); 
    exit();
}

// ดึงข้อมูล Role ทั้งหมด
$roles = $conn->query("SELECT * FROM master_roles ORDER BY id ASC");
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการตำแหน่ง (Roles) - TJC</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: 'Sarabun', sans-serif; background: #f0f2f5; padding: 20px; padding-left: 270px; } /* เว้นที่ให้ Sidebar */
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
        h2 { margin-top: 0; color: #4e54c8; }
        .form-group { display: flex; gap: 10px; margin-bottom: 20px; }
        input[type="text"] { flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
        button { background: #4e54c8; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-weight: bold; }
        button:hover { background: #3b40a3; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 12px; border-bottom: 1px solid #eee; text-align: left; }
        th { background: #f8f9fa; color: #555; }        
        .btn-del { color: #ff4757; text-decoration: none; font-size: 14px; }
        .alert { padding: 10px; margin-bottom: 15px; border-radius: 5px; font-size: 14px; }
        .success { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }
        .btn-back { display: inline-block; margin-bottom: 20px; text-decoration: none; color: #666; }
        @media (max-width: 768px) { body { padding-left: 20px; } }
    </style>
</head>
<body>
<?php include 'sidebar.php'; ?>
    <div class="container">
        <h2>🛠️ จัดการตำแหน่งผู้ใช้งาน</h2>
        <?php echo $message; ?>

        <form method="POST" class="form-group">
            <input type="text" name="role_name" placeholder="ชื่อตำแหน่งใหม่ (เช่น Supervisor, HR)" required>
            <button type="submit" name="add_role"><i class="fas fa-plus"></i> เพิ่ม</button>
        </form>

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>ชื่อตำแหน่ง (Role Name)</th>
                    <th>จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $roles->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $row['id']; ?></td>
                    <td>
                        <span style="background:#eee; padding:3px 8px; border-radius:10px; font-weight:bold;">
                            <?php echo $row['role_name']; ?>
                        </span>
                    </td>
                    <td>
                        <?php if(!in_array(strtolower($row['role_name']), ['admin', 'manager', 'staff'])): ?>
                            <a href="ManagerRoles.php?delete=<?php echo $row['id']; ?>" class="btn-del" onclick="return confirm('ยืนยันการลบ?');"><i class="fas fa-trash"></i> ลบ</a>
                        <?php else: ?>
                            <span style="color:#ccc; font-size:12px;">(Default)</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</body>
</html>