<?php
session_start();
require_once 'db_connect.php'; // เชื่อมต่อฐานข้อมูลเพื่อดึงสิทธิ์

// 1. ตรวจสอบ Login
if (!isset($_SESSION['fullname'])) {
    header("Location: login.php");
    exit();
}

$fullname = $_SESSION['fullname'];
$role = $_SESSION['role']; 

// ตั้งชื่อตำแหน่ง
if ($role == 'admin') $role_name = 'ผู้ดูแลระบบ (Admin)';
else if ($role == 'manager') $role_name = 'ผู้บริหาร (Manager)';
else $role_name = 'พนักงานทั่วไป (Staff)';

// ✅ ดึงรายชื่อหน้าที่เข้าได้ มาเก็บไว้ใน Array
$allowed_pages = [];
if ($role == 'admin') {
    // Admin ให้ผ่านหมด (God Mode)
    $is_admin = true;
} else {
    $is_admin = false;
    $sql_perm = "SELECT mp.file_name FROM permissions p 
                 JOIN master_pages mp ON p.page_id = mp.id 
                 WHERE p.role_name = '$role'";
    $res_perm = $conn->query($sql_perm);
    while($row = $res_perm->fetch_assoc()) {
        $allowed_pages[] = $row['file_name'];
    }
}

// ✅ ฟังก์ชันเช็คสิทธิ์ก่อนแสดงปุ่ม
function canShow($file) {
    global $is_admin, $allowed_pages;
    if ($is_admin) return true; // Admin เห็นหมด
    return in_array($file, $allowed_pages);
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เมนูหลัก - TJC System</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root { --primary: #4e54c8; --secondary: #8f94fb; --bg-body: #f4f6f9; }
        body { font-family: 'Sarabun', sans-serif; background-color: var(--bg-body); margin: 0; padding: 0; min-height: 100vh; display: flex; flex-direction: column; }
        /* Navbar */
        .navbar { background: white; height: 70px; padding: 0 40px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.05); position: sticky; top: 0; z-index: 100; }
        .logo h2 { margin: 0; color: var(--primary); font-size: 24px; font-weight: 800; display: flex; align-items: center; gap: 10px; }
        .user-profile { display: flex; align-items: center; gap: 15px; }
        .user-info { text-align: right; line-height: 1.2; }
        .user-info strong { display: block; color: #333; font-size: 15px; }
        .user-info span { font-size: 12px; color: #777; background: #eee; padding: 2px 8px; border-radius: 10px; }
        .btn-logout { background: #ffe5e5; color: #d63031; padding: 8px 15px; border-radius: 8px; text-decoration: none; font-weight: bold; font-size: 14px; transition: 0.3s; }
        .btn-logout:hover { background: #d63031; color: white; }
        /* Content */
        .container { max-width: 1100px; margin: 40px auto; padding: 0 20px; flex: 1; }
        .welcome-text { margin-bottom: 30px; }
        .welcome-text h1 { margin: 0; font-size: 28px; color: #2d3436; }
        .welcome-text p { margin: 5px 0 0; color: #636e72; font-size: 16px; }
        /* Grid */
        .menu-section { margin-bottom: 40px; }
        .section-header { font-size: 14px; color: #888; font-weight: bold; text-transform: uppercase; margin-bottom: 15px; border-left: 4px solid var(--primary); padding-left: 10px; }
        .menu-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 25px; }
        /* Card */
        .menu-card { background: white; border-radius: 16px; padding: 25px; display: flex; align-items: center; gap: 20px; text-decoration: none; color: #333; border: 1px solid white; box-shadow: 0 4px 20px rgba(0,0,0,0.03); transition: all 0.3s ease; position: relative; overflow: hidden; }
        .menu-card:hover { transform: translateY(-5px); box-shadow: 0 10px 30px rgba(78, 84, 200, 0.15); border-color: rgba(78, 84, 200, 0.2); }
        .icon-box { width: 65px; height: 65px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 28px; flex-shrink: 0; transition: 0.3s; }
        .menu-card:hover .icon-box { transform: scale(1.1) rotate(-5deg); }
        .menu-content { flex: 1; }
        .menu-content h3 { margin: 0 0 5px 0; font-size: 18px; font-weight: 700; color: #2d3436; }
        .menu-content p { margin: 0; font-size: 13px; color: #636e72; line-height: 1.5; }
        .arrow-icon { color: #ddd; font-size: 18px; transition: 0.3s; }
        .menu-card:hover .arrow-icon { color: var(--primary); transform: translateX(5px); }
        /* Colors */
        .c-blue { background: #e3f2fd; color: #1565c0; }
        .c-green { background: #e8f5e9; color: #2e7d32; }
        .c-orange { background: #fff3e0; color: #e65100; }
        .c-purple { background: #f3e5f5; color: #7b1fa2; }
        .c-teal { background: #e0f2f1; color: #00695c; }
        .c-red { background: #ffebee; color: #c62828; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #aaa; margin-top: auto; }
    </style>
</head>
<body>
<?php include 'sidebar.php'; ?>
    <nav class="navbar">
        <div class="logo"><h2><i class="fas fa-cubes"></i> TJC System</h2></div>
        <div class="user-profile">
            <div class="user-info"><strong><?php echo $fullname; ?></strong><span><?php echo $role_name; ?></span></div>
            <a href="logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> ออก</a>
        </div>
    </nav>

    <div class="container">
        <div class="welcome-text">
            <h1>สวัสดีครับ, คุณ<?php echo $fullname; ?> 👋</h1>
            <p>ยินดีต้อนรับเข้าสู่ระบบบริหารจัดการ เลือกเมนูที่ต้องการใช้งาน</p>
        </div>

        <div class="menu-section">
            <div class="section-header">เมนูหลัก (Main Menu)</div>
            <div class="menu-grid">
                
                <?php if (canShow('Dashboard.php')): ?>
                <a href="Dashboard.php" class="menu-card">
                    <div class="icon-box c-blue"><i class="fas fa-chart-line"></i></div>
                    <div class="menu-content"><h3>Dashboard ผู้บริหาร</h3><p>ดูภาพรวม สถิติ กราฟ และสรุปผลการดำเนินงาน</p></div>
                    <i class="fas fa-chevron-right arrow-icon"></i>
                </a>
                <?php endif; ?>

                <?php if (canShow('AddUser.php')): ?>
                <a href="AddUser.php" class="menu-card">
                    <div class="icon-box c-orange"><i class="fas fa-user-plus"></i></div>
                    <div class="menu-content"><h3>จัดการพนักงาน</h3><p>เพิ่มบัญชีผู้ใช้ใหม่ กำหนดสิทธิ์การเข้าใช้งาน</p></div>
                    <i class="fas fa-chevron-right arrow-icon"></i>
                </a>
                <?php endif; ?>

                <?php if (canShow('MapDashboard.php')): ?>
                <a href="MapDashboard.php" class="menu-card">
                    <div class="icon-box c-teal"><i class="fas fa-map-marked-alt"></i></div>
                    <div class="menu-content"><h3>แผนที่ติดตามงาน</h3><p>ดูพิกัดสถานที่ปฏิบัติงานของทีมงานบนแผนที่จริง</p></div>
                    <i class="fas fa-chevron-right arrow-icon"></i>
                </a>
                <?php endif; ?>

                <?php if (canShow('Report.php')): ?>
                <a href="Report.php" class="menu-card">
                    <div class="icon-box c-green"><i class="fas fa-edit"></i></div>
                    <div class="menu-content"><h3>เขียนรายงาน</h3><p>บันทึกการปฏิบัติงานประจำวัน ส่งผลงานเข้าสู่ระบบ</p></div>
                    <i class="fas fa-chevron-right arrow-icon"></i>
                </a>
                <?php endif; ?>

                <?php if (canShow('StaffHistory.php')): ?>
                <a href="StaffHistory.php" class="menu-card">
                    <div class="icon-box c-blue"><i class="fas fa-history"></i></div>
                    <div class="menu-content"><h3>ประวัติงานของฉัน</h3><p>ตรวจสอบสถานะรายงานย้อนหลังและประวัติการเบิกจ่าย</p></div>
                    <i class="fas fa-chevron-right arrow-icon"></i>
                </a>
                <?php endif; ?>

                <?php if (canShow('ManageRoles.php')): ?>
                <a href="ManageRoles.php" class="menu-card">
                    <div class="icon-box c-red"><i class="fas fa-shield-alt"></i></div>
                    <div class="menu-content"><h3>จัดการตำแหน่ง (Roles)</h3><p>เพิ่มหรือลบชื่อตำแหน่งงานในระบบ</p></div>
                    <i class="fas fa-chevron-right arrow-icon"></i>
                </a>
                <?php endif; ?>

                <?php if (canShow('ManagePermissions.php')): ?>
                <a href="ManagePermissions.php" class="menu-card">
                    <div class="icon-box c-red"><i class="fas fa-key"></i></div>
                    <div class="menu-content"><h3>กำหนดสิทธิ์ (Permissions)</h3><p>ตั้งค่าการเข้าถึงเมนูต่างๆ ของแต่ละตำแหน่ง</p></div>
                    <i class="fas fa-chevron-right arrow-icon"></i>
                </a>
                <?php endif; ?>

            </div>
        </div>

        <div class="menu-section">
            <div class="section-header">การตั้งค่าส่วนตัว (Settings)</div>
            <div class="menu-grid">
                <a href="Profile.php" class="menu-card">
                    <div class="icon-box c-purple"><i class="fas fa-user-cog"></i></div>
                    <div class="menu-content"><h3>ตั้งค่าโปรไฟล์</h3><p>แก้ไขข้อมูลส่วนตัว เปลี่ยนรูปประจำตัว (Avatar)</p></div>
                    <i class="fas fa-chevron-right arrow-icon"></i>
                </a>
            </div>
        </div>

    </div>
    <div class="footer">© <?php echo date('Y'); ?> TJC System. All Rights Reserved.</div>
</body>
</html>