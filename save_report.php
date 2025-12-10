<?php
// save_report.php

// 1. อนุญาตให้ React (จากคนละ Port) ส่งข้อมูลมาหาได้ (CORS)
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// 2. เรียกไฟล์เชื่อมต่อฐานข้อมูลมาใช้
require_once 'db_connect.php';

// 3. รับข้อมูลดิบ (JSON) ที่ส่งมาจาก React
$json_data = file_get_contents("php://input");
$data = json_decode($json_data, true); // แปลง JSON เป็น Array ของ PHP

// ตรวจสอบว่ามีข้อมูลส่งมาจริงไหม
if (!empty($data)) {
    
    // 4. ดึงค่าจาก Array มาใส่ตัวแปร (ให้ตรงกับที่ส่งมาจาก React)
    $date = $data['date'];
    $name = $data['name'];
    $area = $data['area'];
    $gps = $data['gps'];
    $jobSource = $data['jobSource'];
    $jobValue = $data['jobValue'];
    $expense = $data['expense'];
    $problem = $data['problem'];
    $suggestion = $data['suggestion'];

    // 5. เตรียมคำสั่ง SQL (ใช้ Prepare Statement ป้องกันการแฮก SQL Injection)
    $sql = "INSERT INTO reports (report_date, reporter_name, area, gps, job_source, job_value, expense, problem, suggestion) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    // s = string, d = decimal/double
    $stmt->bind_param("sssssddss", $date, $name, $area, $gps, $jobSource, $jobValue, $expense, $problem, $suggestion);

    // 6. สั่งบันทึกและส่งผลลัพธ์กลับไปหา React
    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "บันทึกข้อมูลเรียบร้อย!"]);
    } else {
        echo json_encode(["status" => "error", "message" => "เกิดข้อผิดพลาด: " . $stmt->error]);
    }

    $stmt->close();
} else {
    echo json_encode(["status" => "error", "message" => "ไม่มีข้อมูลส่งมา"]);
}

$conn->close();
?>