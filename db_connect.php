<?php
// ข้อมูลเชื่อมต่อจาก TiDB Cloud
$servername = "gateway01.ap-southeast-1.prod.aws.tidbcloud.com";
$username = "2zJFS48pitnR2QG.root"; 
$password = "DF43GROp1tGLs8Gp"; // รหัสผ่านจริงของคุณ
$dbname = "tjc_db"; 
$port = 4000; // Port สำหรับ TiDB

// 1. สร้างการเชื่อมต่อและเปิดใช้งาน SSL/TLS (แทนที่ new mysqli())
$conn = mysqli_init();
if (!$conn) {
    die("Connection failed: mysqli_init() error");
}

// 2. ตั้งค่าไม่ตรวจสอบใบรับรอง (เพื่อข้าม CA_PATH)
mysqli_options($conn, 25, false);
// 3. เชื่อมต่อโดยบังคับใช้ SSL/TLS
mysqli_real_connect($conn, $servername, $username, $password, $dbname, $port, NULL, MYSQLI_CLIENT_SSL);

// เช็คว่าเชื่อมต่อล้มเหลวหรือไม่ (ใช้โค้ดที่สะอาดแล้ว)
if (mysqli_connect_errno()) {
    die("เชื่อมต่อล้มเหลว: " . mysqli_connect_error());
}

// ตั้งค่าให้รองรับภาษาไทย
$conn->set_charset("utf8");

?>
