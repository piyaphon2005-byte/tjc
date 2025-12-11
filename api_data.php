<?php
// เพิ่ม Headers เพื่อป้องกันปัญหา CORS (เวลาเรียกจากเว็บจะได้ไม่ติด Block)
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// --- แก้ไข: เปลี่ยนจาก Localhost เป็น TiDB Cloud ---
$servername = "gateway01.ap-southeast-1.prod.aws.tidbcloud.com";
$username = "2zJFS48pitnR2QG.root"; 
$password = "DF43GROp1tGLs8Gp"; // รหัสผ่านของคุณ
$dbname = "tjc_db";
$port = 4000;

// 1. สร้างการเชื่อมต่อแบบ SSL
$conn = mysqli_init();
if (!$conn) {
    echo json_encode(["status" => "error", "message" => "Connection init failed"]);
    exit();
}

// 2. ตั้งค่า SSL (เหมือนใน api_mobile.php)
mysqli_options($conn, 25, false); // MYSQLI_OPT_SSL_VERIFY_SERVER_CERT
mysqli_real_connect($conn, $servername, $username, $password, $dbname, $port, NULL, MYSQLI_CLIENT_SSL);

if (mysqli_connect_errno()) {
    echo json_encode(["status" => "error", "message" => "Connect failed: " . mysqli_connect_error()]);
    exit();
}

$conn->set_charset("utf8");
// ---------------------------------------------------

$action = isset($_GET['action']) ? $_GET['action'] : '';

// 1. ดึงจังหวัด (ตามภาค)
if ($action == 'get_provinces') {
    $region = isset($_GET['region']) ? $_GET['region'] : '';
    
    // ถ้าไม่ส่งภาคมา หรือส่งมาเป็นค่าว่าง ให้ดึงทั้งหมด หรือจัดการตามสมควร
    if(empty($region)) {
         $sql = "SELECT name_th FROM master_provinces ORDER BY name_th ASC";
         $stmt = $conn->prepare($sql);
    } else {
         $sql = "SELECT name_th FROM master_provinces WHERE region_name = ? ORDER BY name_th ASC";
         $stmt = $conn->prepare($sql);
         $stmt->bind_param("s", $region);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $data = [];
    while($row = $result->fetch_assoc()) { $data[] = $row['name_th']; }
    echo json_encode($data);
}

// 2. ดึงสถานะงาน (NEW)
else if ($action == 'get_job_status') {
    // เช็คก่อนว่า Table นี้มีอยู่จริงไหม
    $sql = "SELECT status_name FROM master_job_status ORDER BY id ASC";
    $result = $conn->query($sql);
    
    $data = [];
    if ($result) {
        while($row = $result->fetch_assoc()) { $data[] = $row['status_name']; }
    } else {
        // กรณีไม่มี Table ให้ส่งค่า Default กลับไปก่อน กันแอปพัง
        $data = ["รอติดต่อ", "สนใจ", "ติดตามต่อ", "ได้งาน", "ไม่ได้งาน"];
    }
    echo json_encode($data);
}

// 3. ดึงกิจกรรม (NEW)
else if ($action == 'get_activities') {
    $sql = "SELECT activity_name FROM master_activities ORDER BY id ASC";
    $result = $conn->query($sql);
    
    $data = [];
    if ($result) {
        while($row = $result->fetch_assoc()) { $data[] = $row['activity_name']; }
    } else {
        // ค่า Default
        $data = ["เข้าพบลูกค้า", "โทรศัพท์", "ประชุมออนไลน์", "Survey หน้างาน"];
    }
    echo json_encode($data);
}

$conn->close();
?>