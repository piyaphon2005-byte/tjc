<?php
// ต้องมี session_start() มาจากไฟล์หลักก่อนหน้านี้แล้ว

if (!isset($_SESSION['fullname'])) {
    header("Location: login.php");
    exit();
}

$current_role = $_SESSION['role'];
$current_page = basename($_SERVER['PHP_SELF']); // ชื่อไฟล์ปัจจุบัน (เช่น Report.php)

// 1. ถ้าเป็น Admin ให้ผ่านตลอด (God Mode)
if ($current_role == 'admin') {
    return; 
}

// 2. ถ้าไม่ใช่ Admin ต้องเช็คใน Database
require_once 'db_connect.php'; // เรียกใช้ไฟล์เชื่อมต่อ DB

// ดึง ID ของหน้านี้จากตาราง master_pages
$sql_page = "SELECT id FROM master_pages WHERE file_name = '$current_page'";
$res_page = $conn->query($sql_page);

// ถ้าหน้านี้ไม่มีอยู่ในระบบ permission (ไม่ได้ลงทะเบียนไว้) -> ปล่อยผ่าน หรือ บล็อก (แล้วแต่นโยบาย)
// ในที่นี้สมมติว่าถ้าไม่ได้ลงทะเบียนไว้ ให้เข้าได้ปกติ (เช่น login.php, logout.php)
if ($res_page->num_rows == 0) {
    return;
}

$page_row = $res_page->fetch_assoc();
$page_id = $page_row['id'];

// 3. เช็คว่า Role นี้ มีสิทธิ์ใน Page ID นี้ไหม
$sql_check = "SELECT * FROM permissions WHERE role_name = '$current_role' AND page_id = $page_id";
$result_auth = $conn->query($sql_check);

if ($result_auth->num_rows == 0) {
    // ❌ ไม่มีสิทธิ์! ดีดกลับหน้า Main
    echo "<script>alert('คุณไม่มีสิทธิ์เข้าถึงหน้านี้'); window.location.href='Main.php';</script>";
    exit();
}

// ✅ มีสิทธิ์ -> ทำงานต่อได้
?>