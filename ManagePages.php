<?php
session_start();
require_once 'auth.php'; // ตรวจสอบการล็อกอิน

// ตรวจสอบว่าเป็น Admin เท่านั้นถึงเข้าหน้านี้ได้ (เพื่อความปลอดภัย)
if ($_SESSION['role'] !== 'admin') {
    echo "คุณไม่มีสิทธิ์เข้าถึงหน้านี้";
    exit();
}

require_once 'db_connect.php';
$message = "";

// 1. ส่วนการ "เพิ่ม" ข้อมูล (Insert)
if (isset($_POST['add_page'])) {
    $page_name = $_POST['page_name'];
    $file_name = $_POST['file_name'];

    if (!empty($page_name) && !empty($file_name)) {
        $sql = "INSERT INTO master_pages (page_name, file_name) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $page_name, $file_name);
        if ($stmt->execute()) {
            $message = "<div class='alert success'>✅ เพิ่มหน้าเว็บเรียบร้อยแล้ว</div>";
        } else {
            $message = "<div class='alert error'>❌ Error: " . $conn->error . "</div>";
        }
    } else {
        $message = "<div class='alert error'>⚠️ กรุณากรอกข้อมูลให้ครบ</div>";
    }
}

// 2. ส่วนการ "ลบ" ข้อมูล (Delete)
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $sql = "DELETE FROM master_pages WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $message = "<div class='alert success'>🗑️ ลบข้อมูลเรียบร้อยแล้ว</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการรายชื่อหน้าเว็บ - TJC System</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Style เดียวกับหน้าอื่นๆ เพื่อความสวยงาม */
        body { font-family: 'Sarabun', sans-serif; background: #f4f6f9; margin: 0; padding: 20px; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 30px; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        h2 { color: #4e54c8; border-bottom: 2px solid #eee; padding-bottom: 15px; }
        
        .form-box { background: #f8f9fa; padding: 20px; border-radius: 10px; margin-bottom: 30px; border: 1px solid #eee; }
        .form-group { margin-bottom: 15px; }
        label { display: block; font-weight: bold; margin-bottom: 5px; }
        input[type="text"] { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; }
        
        .btn-save { background: #28a745; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-size: 16px; }
        .btn-save:hover { background: #218838; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; border-bottom: 1px solid #eee; text-align: left; }
        th { background: #4e54c8; color: white; }
        tr:hover { background: #f1f1f1; }
        
        .btn-delete { background: #dc3545; color: white; padding: 5px 10px; text-decoration: none; border-radius: 5px; font-size: 14px; }
        .btn-delete:hover { background: #c82333; }
        
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        .alert.success { background: #d4edda; color: #155724; }
        .alert.error { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    
    <?php include 'sidebar.php'; ?>

    <div class="container">
        <h2>🛠️ จัดการรายชื่อหน้าเว็บ (Menu Manager)</h2>
        <?php echo $message; ?>

        <div class="form-box">
            <h3>➕ เพิ่มหน้าใหม่</h3>
            <form method="post">
                <div class="form-group">
                    <label>ชื่อเรียก (ภาษาไทย):</label>
                    <input type="text" name="page_name" placeholder="เช่น: แผนที่ติดตาม, เขียนรายงาน" required>
                </div>
                <div class="form-group">
                    <label>ชื่อไฟล์ (ภาษาอังกฤษ):</label>
                    <input type="text" name="file_name" placeholder="เช่น: MapDashboard.php" required>
                </div>
                <button type="submit" name="add_page" class="btn-save"><i class="fas fa-save"></i> บันทึกข้อมูล</button>
            </form>
        </div>

        <h3>รายการหน้าเว็บในระบบ</h3>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>ชื่อหน้าเว็บ (ไทย)</th>
                    <th>ชื่อไฟล์ (File Name)</th>
                    <th>จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $result = $conn->query("SELECT * FROM master_pages ORDER BY id ASC");
                if ($result->num_rows > 0) {
                    while($row = $result->fetch_assoc()) {
                        echo "<tr>";
                        echo "<td>" . $row['id'] . "</td>";
                        echo "<td>" . $row['page_name'] . "</td>";
                        echo "<td>" . $row['file_name'] . "</td>";
                        echo "<td><a href='ManagePages.php?delete=" . $row['id'] . "' class='btn-delete' onclick=\"return confirm('ยืนยันที่จะลบหน้านี้?');\"><i class='fas fa-trash'></i> ลบ</a></td>";
                        echo "</tr>";
                    }
                } else {
                    echo "<tr><td colspan='4' style='text-align:center;'>ยังไม่มีข้อมูล</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>

</body>
</html>