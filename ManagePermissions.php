<?php
session_start();
require_once 'auth.php';
// 1. ตรวจสอบสิทธิ์ (เฉพาะ Admin สูงสุดเท่านั้นที่เข้าหน้านี้ได้)
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: Main.php");
    exit();
}

require_once 'db_connect.php'; // หรือ $conn = new mysqli(...)

$message = "";

// 2. บันทึกข้อมูลเมื่อกด Save
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // ล้างสิทธิ์เก่าออกทั้งหมดก่อน (วิธีที่ง่ายที่สุด)
    $conn->query("TRUNCATE TABLE permissions");
    
    // วนลูปบันทึกสิทธิ์ใหม่ที่ถูกติ๊ก
    if (isset($_POST['perms'])) {
        $stmt = $conn->prepare("INSERT INTO permissions (role_name, page_id) VALUES (?, ?)");
        
        foreach ($_POST['perms'] as $role => $pages) {
            foreach ($pages as $page_id) {
                $stmt->bind_param("si", $role, $page_id);
                $stmt->execute();
            }
        }
        $message = "<div class='alert success'>✅ บันทึกสิทธิ์เรียบร้อยแล้ว!</div>";
    } else {
        $message = "<div class='alert error'>⚠️ คุณไม่ได้เลือกสิทธิ์ใดๆ เลย</div>";
    }
}

// 3. ดึงข้อมูลมาแสดง
$roles = $conn->query("SELECT * FROM master_roles ORDER BY id ASC");
$pages = $conn->query("SELECT * FROM master_pages ORDER BY id ASC");

// ดึงสิทธิ์ปัจจุบันมาใส่ Array เพื่อเช็คว่าอันไหนต้องติ๊กถูก
$current_perms = [];
$res = $conn->query("SELECT * FROM permissions");
while($row = $res->fetch_assoc()) {
    $current_perms[$row['role_name']][] = $row['page_id'];
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>กำหนดสิทธิ์การใช้งาน - TJC</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: 'Sarabun', sans-serif; background: #f0f2f5; padding: 20px; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 30px; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        h2 { margin: 0; color: #4e54c8; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 12px; border: 1px solid #eee; text-align: center; }
        th { background: #4e54c8; color: white; position: sticky; top: 0; }
        th:first-child { text-align: left; min-width: 200px; background: #3b40a3; z-index: 10; }
        tr:nth-child(even) { background: #f9f9f9; }
        
        /* Checkbox Custom */
        input[type="checkbox"] { transform: scale(1.5); cursor: pointer; accent-color: #2ecc71; }
        
        .btn-save { background: #2ecc71; color: white; border: none; padding: 12px 30px; border-radius: 50px; font-size: 16px; font-weight: bold; cursor: pointer; position: fixed; bottom: 30px; right: 30px; box-shadow: 0 5px 15px rgba(0,0,0,0.2); transition: 0.3s; }
        .btn-save:hover { transform: scale(1.1); }
        .btn-back { text-decoration: none; color: #666; background: #eee; padding: 8px 15px; border-radius: 20px; }
        
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 10px; text-align: center; }
        .success { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
<?php include 'sidebar.php'; ?>
    <div class="container">
        <div class="header">
            <h2>🔑 กำหนดสิทธิ์การเข้าถึง (Permissions)</h2>
            <a href="Main.php" class="btn-back"><i class="fas fa-home"></i> กลับหน้าหลัก</a>
        </div>

        <?php echo $message; ?>

        <form method="POST">
            <div style="overflow-x:auto;">
                <table>
                    <thead>
                        <tr>
                            <th>หน้าเว็บ / เมนู</th>
                            <?php 
                            // วนลูปสร้างหัวตารางตาม Role
                            $roles_array = []; 
                            while($r = $roles->fetch_assoc()) { 
                                $roles_array[] = $r['role_name'];
                                echo "<th>".ucfirst($r['role_name'])."</th>"; 
                            } 
                            ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($p = $pages->fetch_assoc()): ?>
                        <tr>
                            <td style="text-align: left;">
                                <strong><?php echo $p['page_name']; ?></strong><br>
                                <small style="color:#888;"><?php echo $p['file_name']; ?></small>
                            </td>
                            
                            <?php foreach($roles_array as $r_name): ?>
                                <td>
                                    <?php 
                                    // เช็คว่าเคยบันทึกสิทธิ์นี้ไว้ไหม
                                    $checked = '';
                                    if(isset($current_perms[$r_name]) && in_array($p['id'], $current_perms[$r_name])) {
                                        $checked = 'checked';
                                    }
                                    
                                    // ป้องกันไม่ให้ติ๊กออก Admin ในหน้า Permission เอง (เดี๋ยวล็อกตัวเอง)
                                    $disabled = ($r_name == 'admin' && $p['file_name'] == 'ManagePermissions.php') ? 'disabled checked' : '';
                                    // Hack: ถ้า disabled จะไม่ส่งค่า POST ต้องใส่ hidden input ไว้
                                    if($disabled) echo "<input type='hidden' name='perms[$r_name][]' value='".$p['id']."'>";
                                    ?>
                                    
                                    <input type="checkbox" name="perms[<?php echo $r_name; ?>][]" value="<?php echo $p['id']; ?>" <?php echo $checked; ?> <?php echo $disabled; ?>>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            
            <button type="submit" class="btn-save"><i class="fas fa-save"></i> บันทึกการเปลี่ยนแปลง</button>
        </form>
    </div>

</body>
</html>