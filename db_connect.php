<?php
// db_connect.php
$servername = "gateway01.ap-southeast-1.prod.aws.tidbcloud.com";
$username = "2zJFS48pitnR2QG.root"; 
$password = "DF43GROp1tGLs8Gp"; // <<< เปลี่ยนตรงนี้เป็นรหัสผ่านจริง
$dbname = "tjc_db"; 

// สร้างการเชื่อมต่อ (ต้องใส่ $port เป็นตัวที่ 5)
$conn = new mysqli($servername, $username, $password, $dbname,);

// เช็คว่าเชื่อมได้ไหม
if ($conn->connect_error) {
    die("เชื่อมต่อล้มเหลว: " . $conn->connect_error);
}
// ถ้าเงียบกริบ แปลว่าเชื่อมสำเร็จ (ไม่ต้อง echo อะไรออกมา เพราะเราจะเอาไว้ include)
?>
