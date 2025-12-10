<?php
session_start();
require_once 'auth.php';
require_once 'db_connect.php';

// ตรวจสอบสิทธิ์ (Admin เท่านั้น)
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'admin') {
    echo "คุณไม่มีสิทธิ์เข้าถึงหน้านี้";
    exit();
}

$message = "";

// --- 1. เพิ่มข้อมูล (Insert) ---
if (isset($_POST['add_province'])) {
    $region_name = $_POST['region_name'];
    $name_th = trim($_POST['name_th']);
    
    if (!empty($region_name) && !empty($name_th)) {
        // เช็คว่ามีจังหวัดนี้ในภาคนี้หรือยัง
        $check = $conn->query("SELECT id FROM master_provinces WHERE name_th = '$name_th' AND region_name = '$region_name'");
        if ($check->num_rows == 0) {
            $stmt = $conn->prepare("INSERT INTO master_provinces (region_name, name_th) VALUES (?, ?)");
            $stmt->bind_param("ss", $region_name, $name_th);
            if ($stmt->execute()) {
                $message = "<div class='alert success'>✅ เพิ่มจังหวัด '$name_th' ($region_name) เรียบร้อย</div>";
            } else {
                $message = "<div class='alert error'>❌ Error: " . $conn->error . "</div>";
            }
        } else {
            $message = "<div class='alert error'>⚠️ มีจังหวัดนี้ในระบบแล้ว</div>";
        }
    } else {
        $message = "<div class='alert error'>⚠️ กรุณากรอกข้อมูลให้ครบ</div>";
    }
}

// --- 2. ลบข้อมูล (Delete) ---
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM master_provinces WHERE id = ?");
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
    <title>จัดการจังหวัด - TJC System</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: 'Sarabun', sans-serif; background: #f4f6f9; margin: 0; padding: 20px; }
        /* เว้นที่ให้ Sidebar */
        @media (min-width: 768px) { body { padding-left: 270px; } }
        
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 30px; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        h2 { color: #4e54c8; border-bottom: 2px solid #eee; padding-bottom: 15px; }
        .form-box { background: #f8f9fa; padding: 20px; border-radius: 10px; margin-bottom: 20px; display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap; }
        .form-group { flex: 1; min-width: 200px; }
        label { display: block; font-weight: bold; margin-bottom: 5px; }
        input[type="text"], select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; font-family: 'Sarabun'; }
        .btn-save { background: #28a745; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; min-width: 100px; }
        .btn-delete { background: #dc3545; color: white; padding: 5px 10px; text-decoration: none; border-radius: 5px; font-size: 14px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; border-bottom: 1px solid #eee; text-align: left; }
        th { background: #4e54c8; color: white; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        .alert.success { background: #d4edda; color: #155724; }
        .alert.error { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="container">
        <h2>🗺️ จัดการข้อมูลจังหวัด (Province Manager)</h2>
        <?php echo $message; ?>

        <div class="form-box">
            <form method="post" style="display:flex; gap:10px; width:100%; align-items:flex-end; flex-wrap:wrap;">
                <div class="form-group">
                    <label>เลือกภาค:</label>
                    <select name="region_name" required>
                        <option value="">-- เลือกภาค --</option>
                        <option value="ภาคเหนือ">ภาคเหนือ</option>
                        <option value="ภาคอีสาน">ภาคอีสาน</option>
                        <option value="ภาคกลาง">ภาคกลาง</option>
                        <option value="ภาคใต้">ภาคใต้</option>
                        <option value="ภาคตะวันออก">ภาคตะวันออก</option>
                        <option value="ภาคตะวันตก">ภาคตะวันตก</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>ชื่อจังหวัด:</label>
                    <input type="text" name="name_th" placeholder="เช่น เชียงใหม่, ขอนแก่น" required>
                </div>
                <button type="submit" name="add_province" class="btn-save"><i class="fas fa-plus"></i> เพิ่ม</button>
            </form>
        </div>

        <table>
            <thead>
                <tr>
                    <th width="10%">ID</th>
                    <th width="30%">ภาค (Region)</th>
                    <th>ชื่อจังหวัด</th>
                    <th width="15%">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // ดึงข้อมูล เรียงตามภาคก่อน แล้วค่อยเรียงตามชื่อจังหวัด
                $result = $conn->query("SELECT * FROM master_provinces ORDER BY region_name ASC, name_th ASC");
                
                if ($result->num_rows > 0) {
                    while($row = $result->fetch_assoc()) {
                        echo "<tr>";
                        echo "<td>{$row['id']}</td>";
                        echo "<td><span style='background:#eef; padding:3px 8px; border-radius:5px;'>{$row['region_name']}</span></td>";
                        echo "<td>{$row['name_th']}</td>";
                        echo "<td>
                                <a href='ProvinceManager.php?delete={$row['id']}' 
                                   class='btn-delete' 
                                   onclick=\"return confirm('ยืนยันที่จะลบจังหวัด {$row['name_th']}?');\">
                                   <i class='fas fa-trash'></i> ลบ
                                </a>
                              </td>";
                        echo "</tr>";
                    }
                } else {
                    echo "<tr><td colspan='4' style='text-align:center'>ยังไม่มีข้อมูลจังหวัด</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</body>
</html>