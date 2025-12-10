<?php
// db_connect.php
$servername = "localhost";
$username = "root"; // XAMPP ปกติ user คือ root
$password = "";     // XAMPP ปกติ password จะว่างไว้
$dbname = "tjc_db"; // ชื่อฐานข้อมูลที่เราเพิ่งสร้าง

// สร้างการเชื่อมต่อ
$conn = new mysqli($servername, $username, $password, $dbname);

// เช็คว่าเชื่อมได้ไหม
if ($conn->connect_error) {
    die("เชื่อมต่อล้มเหลว: " . $conn->connect_error);
}
// ถ้าเงียบกริบ แปลว่าเชื่อมสำเร็จ (ไม่ต้อง echo อะไรออกมา เพราะเราจะเอาไว้ include)
?>