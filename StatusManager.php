<?php
session_start();
require_once 'auth.php'; // ตรวจสอบการล็อกอิน

// เชื่อมต่อฐานข้อมูล
require_once 'db_connect.php';
$conn = new mysqli($servername, $username, $password, $dbname);
$conn->set_charset("utf8");

$status_message = "";

// --- 1. จัดการเพิ่มสถานะใหม่ (POST) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['new_status'])) {
    $new_status = trim($_POST['new_status']);
    
    if (!empty($new_status)) {
        $sql_insert = "INSERT INTO master_job_status (status_name) VALUES (?)";
        $stmt = $conn->prepare($sql_insert);
        $stmt->bind_param("s", $new_status);
        
        if ($stmt->execute()) {
            $status_message = "<div class='alert success'>✅ เพิ่มสถานะ '$new_status' เรียบร้อยแล้ว!</div>";
        } else {
            $status_message = "<div class='alert error'>❌ เพิ่มสถานะไม่สำเร็จ: " . $conn->error . "</div>";
        }
        $stmt->close();
    }
}

// --- 2. จัดการลบสถานะ (GET) ---
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    
    // ป้องกันการลบสถานะหลัก (Won/Follow/Lost) ควรมีการเช็ค ID แต่เพื่อความง่ายจะลบเลย
    $sql_delete = "DELETE FROM master_job_status WHERE id = ?";
    $stmt = $conn->prepare($sql_delete);
    $stmt->bind_param("i", $delete_id);
    
    if ($stmt->execute()) {
        $status_message = "<div class='alert success'>🗑️ ลบสถานะ ID $delete_id เรียบร้อยแล้ว!</div>";
    } else {
        $status_message = "<div class='alert error'>❌ ลบไม่สำเร็จ: " . $conn->error . "</div>";
    }
    $stmt->close();
    header("Location: StatusManager.php"); // Redirect เพื่อล้าง query string
    exit();
}


// --- 3. ดึงข้อมูลสถานะปัจจุบัน ---
$sql_select = "SELECT id, status_name FROM master_job_status ORDER BY id ASC";
$result_select = $conn->query($sql_select);

$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการสถานะงาน</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: 'Sarabun', sans-serif; background: #f0f2f5; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        h3 { color: #4e54c8; border-bottom: 2px solid #eee; padding-bottom: 10px; margin-bottom: 20px; }
        
        input[type="text"] { padding: 10px; border: 1px solid #ddd; border-radius: 8px; width: 70%; margin-right: 10px; font-family: Sarabun; }
        .btn-add { background: #2ecc71; color: white; padding: 10px 15px; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; }
        .btn-delete { color: #ff4757; text-decoration: none; margin-left: 10px; }
        
        .status-list { list-style: none; padding: 0; }
        .status-list li { 
            display: flex; justify-content: space-between; align-items: center; 
            padding: 12px; border-bottom: 1px solid #f0f0f0; 
            font-size: 16px; 
        }
        .alert { padding: 10px; margin-bottom: 15px; border-radius: 8px; font-weight: bold; }
        .alert.success { background: #d4edda; color: #155724; }
        .alert.error { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
<?php include 'sidebar.php'; ?>
<div class="container">
    <a href="Dashboard.php" style="float:right; color:#4e54c8; text-decoration:none;"><i class="fas fa-arrow-left"></i> กลับ Dashboard</a>
    <h3><i class="fas fa-cogs"></i> จัดการสถานะงาน (Master Status)</h3>

    <?php echo $status_message; ?>

    <h4>+ เพิ่มสถานะใหม่</h4>
    <form method="POST">
        <input type="text" name="new_status" placeholder="เช่น: เข้าเสนอ, รอยืนยัน" required>
        <button type="submit" class="btn-add"><i class="fas fa-plus"></i> เพิ่ม</button>
    </form>
    <hr>

    <h4>รายการสถานะปัจจุบัน</h4>
    <ul class="status-list">
        <?php if ($result_select->num_rows > 0): ?>
            <?php while($row = $result_select->fetch_assoc()): ?>
                <li>
                    <span>#<?php echo $row['id']; ?> | <?php echo htmlspecialchars($row['status_name']); ?></span>
                    <a href="StatusManager.php?delete_id=<?php echo $row['id']; ?>" 
                       onclick="return confirm('คุณแน่ใจไหมที่จะลบสถานะ: <?php echo htmlspecialchars($row['status_name']); ?>?')" 
                       class="btn-delete">
                        <i class="fas fa-trash"></i> ลบ
                    </a>
                </li>
            <?php endwhile; ?>
        <?php else: ?>
            <li>ไม่พบสถานะงาน (กรุณาเพิ่ม)</li>
        <?php endif; ?>
    </ul>

</div>

</body>
</html>