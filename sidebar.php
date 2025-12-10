<?php
if (session_status() == PHP_SESSION_NONE) { session_start(); }

// 1. ตรวจสอบ Role และดึงข้อมูลสิทธิ์จาก DB
$role = $_SESSION['role'] ?? 'staff';
$fullname = $_SESSION['fullname'] ?? 'Guest';
$current_page = basename($_SERVER['PHP_SELF']); 

// ต้องเชื่อมต่อฐานข้อมูล (ต้องแน่ใจว่ามีไฟล์ db_connect.php อยู่แล้ว)
require_once 'db_connect.php'; 

// 2. ดึงรายการหน้าที่ได้รับอนุญาตทั้งหมดสำหรับ Role นี้
$allowed_pages = [];
if ($role != 'admin') { // Admin ไม่ต้อง Query ทุกครั้ง เพราะมีสิทธิ์ทั้งหมด
    $sql_perm = "SELECT mp.file_name FROM permissions p 
                 JOIN master_pages mp ON p.page_id = mp.id 
                 WHERE p.role_name = '$role'";
    $res_perm = $conn->query($sql_perm);
    while($row = $res_perm->fetch_assoc()) {
        $allowed_pages[] = $row['file_name'];
    }
}

// 3. ฟังก์ชันเช็คสิทธิ์ (ใช้แทน if ($role == 'manager') เดิม)
function canSeeMenu($file) {
    global $role, $allowed_pages;
    if ($role == 'admin') return true; // Admin เห็นทุกปุ่ม
    return in_array($file, $allowed_pages);
}

// 4. ฟังก์ชันสำหรับทำ Active Class
function isActive($target_pages, $current_page) {
    if (!is_array($target_pages)) $target_pages = [$target_pages];
    return in_array($current_page, $target_pages) ? 'active' : '';
}

// 5. ฟังก์ชันสำหรับ Avatar
function getAvatar() {
    if(isset($_SESSION['avatar']) && !empty($_SESSION['avatar']) && file_exists('uploads/profiles/'.$_SESSION['avatar'])) {
        return 'uploads/profiles/'.$_SESSION['avatar'];
    }
    return 'https://ui-avatars.com/api/?name='.urlencode($_SESSION['fullname']).'&background=random&color=fff';
}
?>

<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    :root {
        --sidebar-width: 250px;
        --sidebar-bg: linear-gradient(180deg, #4e54c8, #8f94fb);
        --text-color: #ffffff;
    }

    /* จัด Layout หน้าเว็บ: ดันเนื้อหาไปขวา */
    body {
        font-family: 'Sarabun', sans-serif;
        margin: 0;
        background-color: #f4f6f9;
        padding-left: var(--sidebar-width); 
        transition: padding-left 0.3s;
    }

    /* Sidebar Container */
    .sidebar {
        height: 100%;
        width: var(--sidebar-width);
        position: fixed;
        z-index: 1000;
        top: 0; left: 0;
        background: var(--sidebar-bg);
        color: white;
        overflow-x: hidden;
        transition: 0.3s;
        box-shadow: 4px 0 10px rgba(0,0,0,0.1);
        display: flex;
        flex-direction: column;
    }

    /* Logo Section */
    .sidebar-brand {
        padding: 25px 20px;
        font-size: 20px;
        font-weight: 800;
        display: flex;
        align-items: center;
        gap: 10px;
        border-bottom: 1px solid rgba(255,255,255,0.15);
    }
    /* Menu List */
    .sidebar-menu {
        list-style: none;
        padding: 10px 0;
        margin: 0;
        flex: 1;
        overflow-y: auto;
    }
    .sidebar-menu a {
        display: flex; align-items: center; padding: 14px 25px; color: rgba(255,255,255,0.8);
        text-decoration: none; font-size: 15px; transition: 0.2s; border-left: 4px solid transparent;
    }
    .sidebar-menu a:hover {
        background: rgba(255,255,255,0.1); color: white;
    }
    .sidebar-menu a.active {
        background: rgba(255,255,255,0.2); color: white; border-left-color: #fff; font-weight: bold;
    }
    .sidebar-menu i { width: 30px; font-size: 18px; text-align: center; }

    /* Profile Section (Bottom) */
    .sidebar-footer {
        padding: 20px; background: rgba(0,0,0,0.2); display: flex; align-items: center; gap: 12px;
    }
    .user-avatar {
        width: 40px; height: 40px; border-radius: 50%; background: white; object-fit: cover; border: 2px solid rgba(255,255,255,0.5);
    }
    .user-info { line-height: 1.2; font-size: 13px; }
    .user-info strong { display: block; }
    .btn-logout-icon { margin-left: auto; color: #ffcccc; cursor: pointer; transition: 0.3s; }

    /* Responsive (มือถือ) */
    .mobile-nav { display: none; }
    @media (max-width: 768px) {
        body { padding-left: 0; }
        .sidebar { left: -100%; }
        .sidebar.show { left: 0; }
        .mobile-nav {
            display: flex; background: #4e54c8; color: white; padding: 15px; position: sticky; top: 0; z-index: 999;
            align-items: center; justify-content: space-between; box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .menu-toggle { font-size: 24px; cursor: pointer; margin-right: 15px; }
        .overlay {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5); z-index: 998;
        }
        .overlay.show { display: block; }
    }
</style>

<div class="mobile-nav">
    <div style="display:flex; gap:15px; align-items:center;">
        <i class="fas fa-bars menu-toggle" onclick="toggleSidebar()"></i>
        <span style="font-weight:bold; font-size:18px;">TJC System</span>
    </div>
    <div class="user-avatar" style="width:30px; height:30px; background-image:url('<?php echo getAvatar(); ?>'); background-size:cover;"></div>
</div>

<div class="sidebar" id="mySidebar">
    <div class="sidebar-brand">
        <i class="fas fa-cubes"></i> TJC System
    </div>

    <ul class="sidebar-menu">
        <?php if (canSeeMenu('Main.php')): ?>
        <li>
            <a href="Main.php" class="<?php echo isActive('Main.php', $current_page); ?>">
                <i class="fas fa-home"></i> <span>หน้าหลัก</span>
            </a>
        </li>
        <?php endif; ?>

        <?php if (canSeeMenu('Dashboard.php')): ?>
            <li>
                <a href="Dashboard.php" class="<?php echo isActive('Dashboard.php', $current_page); ?>">
                    <i class="fas fa-chart-pie"></i> <span>แดชบอร์ด</span>
                </a>
            </li>
        <?php endif; ?>

        <?php if (canSeeMenu('MapDashboard.php')): ?>
            <li>
                <a href="MapDashboard.php" class="<?php echo isActive('MapDashboard.php', $current_page); ?>">
                    <i class="fas fa-map-marked-alt"></i> <span>แผนที่ติดตาม</span>
                </a>
            </li>
        <?php endif; ?>
        
        <?php if (canSeeMenu('Report.php')): ?>
            <li>
                <a href="Report.php" class="<?php echo isActive('Report.php', $current_page); ?>">
                    <i class="fas fa-edit"></i> <span>เขียนรายงาน</span>
                </a>
            </li>
        <?php endif; ?>
        
        <?php if (canSeeMenu('StaffHistory.php')): ?>
            <li>
                <a href="StaffHistory.php" class="<?php echo isActive('StaffHistory.php', $current_page); ?>">
                    <i class="fas fa-history"></i> <span>ประวัติงาน</span>
                </a>
            </li>
        <?php endif; ?>

        <?php if (canSeeMenu('AddUser.php')): ?>
            <li>
                <a href="AddUser.php" class="<?php echo isActive('AddUser.php', $current_page); ?>">
                    <i class="fas fa-users-cog"></i> <span>จัดการพนักงาน</span>
                </a>
            </li>
        <?php endif; ?>


       <?php if (canSeeMenu('ManagerRoles.php') || canSeeMenu('ManagePermissions.php')): ?>
            <li style="margin-top:10px; padding-left:25px; font-size:12px; opacity:0.7; font-weight:bold;">ADMIN ZONE</li>
        <?php endif; ?>

        <?php if (canSeeMenu('ManagerRoles.php')): ?>
            <li>
                <a href="ManagerRoles.php" class="<?php echo isActive('ManagerRoles.php', $current_page); ?>">
                    <i class="fas fa-shield-alt"></i> <span>จัดการตำแหน่ง</span>
                </a>
            </li>
        <?php endif; ?>

        <?php if (canSeeMenu('ProvinceManager.php')): ?>
        <li>
            <a href="ProvinceManager.php" class="<?php echo isActive('ProvinceManager.php', $current_page); ?>">
                <i class="fas fa-map"></i> <span>จัดการจังหวัด</span>
            </a>
        </li>
        <?php endif; ?>

        <?php if (canSeeMenu('ManagePermissions.php')): ?>
            <li>
                <a href="ManagePermissions.php" class="<?php echo isActive('ManagePermissions.php', $current_page); ?>">
                    <i class="fas fa-key"></i> <span>กำหนดสิทธิ์</span>
                </a>
            </li>
        <?php endif; ?>

        <?php if (canSeeMenu('ManagePages.php')): ?>
        <li>
            <a href="ManagePages.php" class="<?php echo isActive('ManagePages.php', $current_page); ?>">
                <i class="fas fa-sitemap"></i> <span>จัดการหน้าเว็บ</span>
            </a>
        </li>
        <?php endif; ?>

        <?php if (canSeeMenu('ActivityManager.php')): ?>
        <li>
            <a href="ActivityManager.php" class="<?php echo isActive('ActivityManager.php', $current_page); ?>">
                <i class="fas fa-clipboard-list"></i> <span>จัดการกิจกรรม</span>
            </a>
        </li>
        <?php endif; ?>

        <?php if (canSeeMenu('StatusManager.php')): ?>
        <li>
            <a href="StatusManager.php" class="<?php echo isActive('StatusManager.php', $current_page); ?>">
                <i class="fas fa-tasks"></i> <span>จัดการสถานะ</span>
            </a>
        </li>
        
        <?php endif; ?>
        
        <li>
            <a href="Profile.php" class="<?php echo isActive('Profile.php', $current_page); ?>">
                <i class="fas fa-user-circle"></i> <span>โปรไฟล์ส่วนตัว</span>
            </a>
        </li>
    </ul>
    

    <div class="sidebar-footer">
        <img src="<?php echo getAvatar(); ?>" class="user-avatar">
        <div class="user-info">
            <strong><?php echo mb_strimwidth($fullname, 0, 15, '..'); ?></strong>
            <span style="opacity:0.8;"><?php echo ucfirst($role); ?></span>
        </div>
        <a href="logout.php" class="btn-logout-icon" title="ออกจากระบบ"><i class="fas fa-sign-out-alt"></i></a>
    </div>
</div>

<div class="overlay" id="overlay" onclick="toggleSidebar()"></div>

<script>
    function toggleSidebar() {
        document.getElementById('mySidebar').classList.toggle('show');
        document.getElementById('overlay').classList.toggle('show');
    }
</script>