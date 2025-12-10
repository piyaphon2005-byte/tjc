<?php
session_start();
require_once 'auth.php';
require_once 'db_connect.php';

// ตรวจสอบสิทธิ์ (Admin เท่านั้น)
// ถ้าอยากให้ Role อื่นเข้าได้ด้วย ให้ใช้ canSeeMenu แทนบรรทัดนี้ครับ แต่ปกติหน้านี้ควรเป็น Admin
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'admin') {
    echo "คุณไม่มีสิทธิ์เข้าถึงหน้านี้";
    exit();
}

$message = "";

// --- 1. เพิ่มข้อมูล (Insert) ---
if (isset($_POST['add_activity'])) {
    $activity_name = trim($_POST['activity_name']);
    
    if (!empty($activity_name)) {
        // เช็คก่อนว่ามีชื่อนี้หรือยัง
        $check = $conn->query("SELECT id FROM master_activities WHERE activity_name = '$activity_name'");
        if ($check->num_rows == 0) {
            $stmt = $conn->prepare("INSERT INTO master_activities (activity_name) VALUES (?)");
            $stmt->bind_param("s", $activity_name);
            if ($stmt->execute()) {
                $message = "<div class='alert success'>✅ เพิ่มกิจกรรมเรียบร้อย</div>";
            } else {
                $message = "<div class='alert error'>❌ Error: " . $conn->error . "</div>";
            }
        } else {
            $message = "<div class='alert error'>⚠️ ชื่อกิจกรรมนี้มีอยู่แล้ว</div>";
        }
    } else {
        $message = "<div class='alert error'>⚠️ กรุณากรอกชื่อกิจกรรม</div>";
    }
}

// --- 2. ลบข้อมูล (Delete) ---
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM master_activities WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $message = "<div class='alert success'>🗑️ ลบข้อมูลเรียบร้อย</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการกิจกรรม - TJC System</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: 'Sarabun', sans-serif; background: #f4f6f9; margin: 0; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        h2 { color: #4e54c8; border-bottom: 2px solid #eee; padding-bottom: 15px; }
        .form-box { background: #f8f9fa; padding: 20px; border-radius: 10px; margin-bottom: 20px; display: flex; gap: 10px; align-items: flex-end; }
        .form-group { flex: 1; }
        label { display: block; font-weight: bold; margin-bottom: 5px; }
        input[type="text"] { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; }
        .btn-save { background: #28a745; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; }
        .btn-delete { background: #dc3545; color: white; padding: 5px 10px; text-decoration: none; border-radius: 5px; font-size: 14px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; border-bottom: 1px solid #eee; text-align: left; }
        th { background: #4e54c8; color: white; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        .alert.success { background: #d4edda; color: #155724; }
        .alert.error { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="container">
        <h2>📋 จัดการประเภทกิจกรรม (Activity Manager)</h2>
        <?php echo $message; ?>

        <div class="form-box">
            <form method="post" style="display:flex; gap:10px; width:100%; align-items:flex-end;">
                <div class="form-group">
                    <label>ชื่อกิจกรรมใหม่:</label>
                    <input type="text" name="activity_name" placeholder="เช่น ส่งสินค้า, เก็บเช็ค, เข้าพบลูกค้า" required>
                </div>
                <button type="submit" name="add_activity" class="btn-save"><i class="fas fa-plus"></i> เพิ่ม</button>
            </form>
        </div>

        <table>
            <thead>
                <tr>
                    <th width="10%">ID</th>
                    <th>ชื่อกิจกรรม</th>
                    <th width="15%">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // ดึงข้อมูลจากตาราง master_activities
                $result = $conn->query("SELECT * FROM master_activities ORDER BY id ASC");
                
                if ($result->num_rows > 0) {
                    while($row = $result->fetch_assoc()) {
                        echo "<tr>";
                        echo "<td>{$row['id']}</td>";
                        echo "<td>{$row['activity_name']}</td>";
                        echo "<td>
                                <a href='ActivityManager.php?delete={$row['id']}' 
                                   class='btn-delete' 
                                   onclick=\"return confirm('ยืนยันที่จะลบกิจกรรมนี้?');\">
                                   <i class='fas fa-trash'></i> ลบ
                                </a>
                              </td>";
                        echo "</tr>";
                    }
                } else {
                    echo "<tr><td colspan='3' style='text-align:center'>ยังไม่มีข้อมูลกิจกรรม</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</body>
</html>