<?php
// ข้อมูลเชื่อมต่อจาก TiDB Cloud
$servername = "gateway01.ap-southeast-1.prod.aws.tidbcloud.com";
$username = "2zJFS48pitnR2QG.root";
$password = "DF43GROp1tGLs8Gp"; // รหัสผ่านจริง
$dbname = "tjc_db";
$port = 4000;

// 1. เริ่มต้น mysqli object
$conn = mysqli_init();
if (!$conn) {
    die("Connection failed: mysqli_init() error");
}

// 2. ตั้งค่า Timeout และการตรวจสอบ SSL (สำคัญมากสำหรับ TiDB Serverless)
// บรรทัดนี้เทียบเท่ากับ mysqli_options($conn, 25, false); คือไม่ต้องเช็คใบรับรอง CA อย่างเคร่งครัด
mysqli_options($conn, MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, true); 
// หมายเหตุ: TiDB แนะนำให้ใช้ CA path แต่ถ้าจะเอาเร็วก่อน ให้ตั้งค่าเป็น false หรือใช้ flag SSL เฉยๆ ก็มักจะผ่าน

// 3. เชื่อมต่อโดยบังคับใช้ SSL/TLS (MYSQLI_CLIENT_SSL)
$connected = mysqli_real_connect(
    $conn, 
    $servername, 
    $username, 
    $password, 
    $dbname, 
    $port, 
    NULL, 
    MYSQLI_CLIENT_SSL // <-- จุดสำคัญที่แก้ Error นี้
);

// 4. เช็คว่าเชื่อมต่อสำเร็จไหม
if (!$connected) {
    // แสดง Error อย่างละเอียดหากเชื่อมต่อไม่ได้
    die("Connect Error (" . mysqli_connect_errno() . ") " . mysqli_connect_error());
}

// 5. ตั้งค่าภาษาไทย
$conn->set_charset("utf8");

echo "เชื่อมต่อฐานข้อมูล TiDB สำเร็จ!";
?>
