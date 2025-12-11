<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// ตั้งค่าฐานข้อมูล (ใช้ TiDB Cloud Credentials + SSL Logic)
$servername = "gateway01.ap-southeast-1.prod.aws.tidbcloud.com";
$username = "2zJFS48pitnR2QG.root"; 
$password = "DF43GROp1tGLs8Gp"; // <<< รหัสผ่านจริงของคุณ
$dbname = "tjc_db";
$port = 4000; // Port สำหรับ TiDB

// 1. สร้างการเชื่อมต่อและเปิดใช้งาน SSL/TLS
$conn = mysqli_init();
if (!$conn) {
    echo json_encode(["status" => "error", "message" => "Connection failed: mysqli_init() error"]);
    exit();
}

// 2. ตั้งค่าไม่ตรวจสอบใบรับรอง (ใช้เลข 25 แทน)
mysqli_options($conn, 25, false);
// 3. เชื่อมต่อโดยบังคับใช้ SSL/TLS
mysqli_real_connect($conn, $servername, $username, $password, $dbname, $port, NULL, MYSQLI_CLIENT_SSL);

// เช็คว่าเชื่อมต่อล้มเหลวหรือไม่
if (mysqli_connect_errno()) {
    echo json_encode(["status" => "error", "message" => "Connection failed: " . mysqli_connect_error()]);
    exit();
}

$conn->set_charset("utf8");

$action = isset($_GET['action']) ? $_GET['action'] : '';

// ==========================================
// 1. LOGIN (เข้าสู่ระบบ + ดึงสิทธิ์ Permission)
// ==========================================
if ($action == 'login') {
    $data = json_decode(file_get_contents("php://input"), true);
    $user = $data['username'];
    $pass = $data['password'];

    // 1. ตรวจสอบ Username/Password
    $sql = "SELECT * FROM users WHERE username = ? AND password = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $user, $pass);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $role = $row['role'];
        
        // 2. ดึงสิทธิ์ (Permission) ว่าเข้าหน้าไหนได้บ้าง
        $allowed_pages = [];
        
        if ($role == 'admin') {
            // Admin เข้าได้ทุกอย่าง (ส่งคำว่า 'ALL' ไปบอกแอป)
            $allowed_pages = ['ALL'];
        } else {
            // Role อื่นๆ ดึงตามจริงจากตาราง permissions
            // ต้อง JOIN 2 ตาราง: permissions (จับคู่สิทธิ์) และ master_pages (ชื่อไฟล์)
            $sql_perm = "SELECT mp.page_name, mp.file_name FROM permissions p 
                          JOIN master_pages mp ON p.page_id = mp.id 
                          WHERE p.role_name = '$role'";
            $res_perm = $conn->query($sql_perm);
            
            while($perm = $res_perm->fetch_assoc()) {
                // เก็บชื่อไฟล์ (เช่น Report.php) ลงใน Array
                $allowed_pages[] = $perm['file_name']; 
            }
        }

        // 3. ส่งข้อมูลกลับไปให้แอป (รวมถึง allowed_pages)
        echo json_encode([
            "status" => "success", 
            "id" => $row['id'],
            "fullname" => $row['fullname'],
            "role" => $role,
            "avatar" => $row['avatar'],
            "allowed_pages" => $allowed_pages 
        ]);
        
    } else {
        echo json_encode(["status" => "fail", "message" => "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง"]);
    }
}

// ==========================================
// 2. GET USERS (ดึงรายชื่อพนักงานใส่ Dropdown) - ✅ ใหม่
// ==========================================
else if ($action == 'get_users') {
    $sql = "SELECT DISTINCT reporter_name FROM reports ORDER BY reporter_name ASC";
    $result = $conn->query($sql);
    $users = [];
    while($row = $result->fetch_assoc()) {
        $users[] = $row['reporter_name'];
    }
    echo json_encode($users);
}

// ==========================================
// 3. GET DASHBOARD STATS (Dynamic Status Version) - ✅ แก้ไข
// ==========================================
else if ($action == 'get_dashboard_stats') {
    
    // รับค่าตัวกรอง (เช็ค isset เพื่อกัน Error)
    $filter_name = isset($_GET['filter_name']) ? $_GET['filter_name'] : '';
    $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
    $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

    // เงื่อนไขพื้นฐาน
    $where = "WHERE 1=1";
    
    // กรองชื่อ (เฉพาะถ้ามีค่าส่งมา และไม่ใช่ค่าว่าง)
    if (!empty($filter_name) && $filter_name != 'undefined') { 
        $where .= " AND reporter_name = '$filter_name'"; 
    }
    
    // กรองวันที่
    if (!empty($start_date) && $start_date != 'undefined') { $where .= " AND DATE(report_date) >= '$start_date'"; }
    if (!empty($end_date) && $end_date != 'undefined') { $where .= " AND DATE(report_date) <= '$end_date'"; }

    // 1. หาภาพรวม
    $sql_summary = "SELECT COUNT(*) as total, SUM(total_expense) as expense FROM reports $where";
    $res_summary = $conn->query($sql_summary);
    $summary = $res_summary->fetch_assoc();

    // 2. หาจำนวนแยกตามสถานะ (แก้ไขให้รองรับกรณีไม่มีข้อมูล)
    $sql_group = "SELECT job_status, COUNT(*) as count FROM reports $where GROUP BY job_status";
    $res_group = $conn->query($sql_group);
    
    $breakdown = [];
    if ($res_group) {
        while($row = $res_group->fetch_assoc()) {
            // ถ้า job_status ใน db เป็นค่าว่าง ให้ตั้งชื่อว่า "ไม่ระบุ"
            $status_name = !empty($row['job_status']) ? $row['job_status'] : 'ไม่ระบุสถานะ';
            $breakdown[] = [
                'status' => $status_name,
                'count' => intval($row['count'])
            ];
        }
    }
    
    // 3. ดึงรายการล่าสุด
    $sql_recent = "SELECT * FROM reports $where ORDER BY report_date DESC, id DESC LIMIT 20";
    $res_recent = $conn->query($sql_recent);
    $recent = [];
    if ($res_recent) {
        while($row = $res_recent->fetch_assoc()) {
            $recent[] = $row;
        }
    }

    echo json_encode([
        "summary" => [
            "total" => $summary['total'] ? intval($summary['total']) : 0,
            "expense" => $summary['expense'] ? floatval($summary['expense']) : 0    
        ],
        "breakdown" => $breakdown, 
        "recent" => $recent
    ]);
}

// ==========================================
// 4. SUBMIT REPORT (บันทึกรายงาน - แก้ไขแล้ว ✅)
// ==========================================
else if ($action == 'submit_report') {
    
    // 1. รับค่าแบบ Safe Mode
    $report_date = isset($_POST['report_date']) ? $_POST['report_date'] : date('Y-m-d H:i:s');
    $reporter_name = isset($_POST['reporter_name']) ? $_POST['reporter_name'] : '';
    $work_type = isset($_POST['work_type']) ? $_POST['work_type'] : '';
    
    if ($work_type == 'company') {
        $area = "เข้าบริษัท (สำนักงาน)"; $province = "กรุงเทพมหานคร"; 
        $gps = "Office"; $gps_address = "สำนักงานใหญ่";
    } else {
        $area = isset($_POST['area_zone']) ? $_POST['area_zone'] : ''; 
        $province = isset($_POST['province']) ? $_POST['province'] : ''; 
        $gps = isset($_POST['gps']) ? $_POST['gps'] : ''; 
        $gps_address = isset($_POST['gps_address']) ? $_POST['gps_address'] : '';
    }

    $work_result = isset($_POST['work_result']) ? $_POST['work_result'] : ''; 
    $customer_type = isset($_POST['customer_type']) ? $_POST['customer_type'] : 'ลูกค้าเก่า';
    $project_name = isset($_POST['project_name']) ? $_POST['project_name'] : ''; 
    $additional_notes = isset($_POST['additional_notes']) ? $_POST['additional_notes'] : '';
    
    $job_status = isset($_POST['job_status']) ? $_POST['job_status'] : '';
    $next_appointment = (!empty($_POST['next_appointment']) && $_POST['next_appointment'] != 'null') ? $_POST['next_appointment'] : NULL;
    
    $activity_type = isset($_POST['activity_type']) ? $_POST['activity_type'] : '';
    $activity_detail = isset($_POST['activity_detail']) ? $_POST['activity_detail'] : '';

    // รับค่าตัวเลข
    $fuel = isset($_POST['fuel_cost']) ? floatval($_POST['fuel_cost']) : 0.00;
    $acc = isset($_POST['accommodation_cost']) ? floatval($_POST['accommodation_cost']) : 0.00;
    $other = isset($_POST['other_cost']) ? floatval($_POST['other_cost']) : 0.00;
    $total = $fuel + $acc + $other;
    
    // รับค่าข้อความ
    $other_cost_detail = isset($_POST['other_cost_detail']) ? $_POST['other_cost_detail'] : '';
    $problem = isset($_POST['problem']) ? $_POST['problem'] : '';
    $suggestion = isset($_POST['suggestion']) ? $_POST['suggestion'] : '';

    // ฟังก์ชันอัปโหลดรูป
    function uploadImg($fileKey) {
        if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] == 0) {
            $target_dir = "uploads/";
            if (!file_exists($target_dir)) { mkdir($target_dir, 0777, true); } 
            $target = $target_dir . "app_" . time() . "_" . rand(100,999) . ".jpg";
            if (move_uploaded_file($_FILES[$fileKey]['tmp_name'], $target)) {
                return basename($target);
            }
        }
        return "";
    }

    $fuel_receipt = uploadImg('fuel_image');
    $acc_receipt = uploadImg('acc_image');
    $other_receipt = uploadImg('other_image');

    // SQL Insert
    $sql = "INSERT INTO reports (
        report_date, reporter_name, area, province, gps, gps_address, 
        work_result, customer_type, project_name, additional_notes, job_status, next_appointment, activity_type, activity_detail,
        fuel_cost, fuel_receipt, accommodation_cost, accommodation_receipt, 
        other_cost, other_receipt, other_cost_detail, total_expense, 
        problem, suggestion
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    // เช็คว่า Prepare ผ่านไหม
    if ($stmt = $conn->prepare($sql)) {
        // ✅ แก้ไข Type: s=String, d=Double
        // other_cost_detail (ตำแหน่ง 21) -> s
        // total_expense (ตำแหน่ง 22) -> d
        $stmt->bind_param("ssssssssssssssdsdsdssdds", 
            $report_date, $reporter_name, $area, $province, $gps, $gps_address, 
            $work_result, $customer_type, $project_name, $additional_notes, $job_status, $next_appointment, $activity_type, $activity_detail,
            $fuel, $fuel_receipt, $acc, $acc_receipt, 
            $other, $other_receipt, $other_cost_detail, $total, 
            $problem, $suggestion
        );

        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "บันทึกข้อมูลเรียบร้อย"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Execute Failed: " . $stmt->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(["status" => "error", "message" => "Prepare Failed: " . $conn->error]);
    }
}

// ==========================================
// 5. GET HISTORY (ประวัติส่วนตัว + กรองวันที่) ✅ แก้ไขใหม่
// ==========================================
else if ($action == 'get_history') {
    $reporter_name = $_GET['reporter_name'];
    $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
    $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

    // สร้างเงื่อนไข SQL
    $where = "WHERE reporter_name = '$reporter_name'";
    if (!empty($start_date)) { $where .= " AND report_date >= '$start_date'"; }
    if (!empty($end_date)) { $where .= " AND report_date <= '$end_date'"; }
    
    // ดึง KPI 4 ตัว (ให้เหมือน Dashboard ผู้บริหาร)
    $sql_summary = "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN job_status='ได้งาน' THEN 1 ELSE 0 END) as won,
        SUM(CASE WHEN job_status='กำลังติดตาม' THEN 1 ELSE 0 END) as follow,
        SUM(total_expense) as expense
        FROM reports $where";
        
    $result_sum = $conn->query($sql_summary);
    $data = ($result_sum && $result_sum->num_rows > 0) 
        ? $result_sum->fetch_assoc() 
        : ["total" => 0, "won" => 0, "follow" => 0, "expense" => 0];

    // ดึงรายการ
    $sql_list = "SELECT * FROM reports $where ORDER BY report_date DESC, id DESC LIMIT 50";
    $res_list = $conn->query($sql_list);
    
    $history = [];
    if ($res_list) {
        while($row = $res_list->fetch_assoc()) {
            $history[] = $row;
        }
    }

    echo json_encode(["summary" => $data, "history" => $history]);
}

// ==========================================
// 6. GET MAP DATA (Final Ultimate Fix)
// ==========================================
else if ($action == 'get_map_data') {
    $filter_name = isset($_GET['filter_name']) ? $_GET['filter_name'] : '';
    $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
    $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

    // เงื่อนไขพื้นฐาน
    $where = "WHERE r.gps != 'Office' AND r.gps != '' AND r.gps IS NOT NULL";
    
    // 1. กรองชื่อ
    if (!empty($filter_name)) { 
        $where .= " AND r.reporter_name = '$filter_name'"; 
    }
    
    // 2. กรองวันที่ (ใช้ DATE() เพื่อตัดเวลาทิ้ง 100%)
    if (!empty($start_date)) { 
        $where .= " AND DATE(r.report_date) >= '$start_date'"; 
    }
    if (!empty($end_date)) { 
        $where .= " AND DATE(r.report_date) <= '$end_date'"; 
    }

    $sql = "SELECT r.*, u.avatar, u.role 
             FROM reports r 
             LEFT JOIN users u ON r.reporter_name = u.fullname 
             $where 
             ORDER BY r.report_date DESC";
             
    $result = $conn->query($sql);
    
    $locations = [];
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $coords = explode(',', $row['gps']);
            if(count($coords) == 2) {
                $position = ($row['role'] == 'manager') ? 'ผู้บริหาร' : 'พนักงานขาย';
                
                // แปลงวันที่เป็น d/m/Y (ปี ค.ศ.) ส่งไปให้แอปจัดการต่อ
                $date_display = date('d/m/Y', strtotime($row['report_date']));

                $locations[] = [
                    'id' => $row['id'], // ✅ ต้องมี ID เพื่อใช้เป็น Key
                    'name' => $row['reporter_name'],
                    'lat' => floatval(trim($coords[0])),
                    'lng' => floatval(trim($coords[1])),
                    'client' => $row['work_result'], 
                    'project' => $row['project_name'], 
                    'status' => $row['job_status'],
                    'date' => $date_display,
                    'expense' => $row['total_expense'] ? intval($row['total_expense']) : 0, // ค่าใช้จ่าย
                    'avatar' => $row['avatar'],
                    'position' => $position 
                ];
            }
        }
    }
    echo json_encode($locations);
}

// ==========================================
// 7. UPDATE PROFILE (อัปเดตรูปโปรไฟล์ - แบบตรวจสอบละเอียด)
// ==========================================
else if ($action == 'update_profile') {
    
    // 1. รับค่า Username (ตัวระบุว่าจะอัปเดตใคร)
    $username = isset($_POST['username']) ? $_POST['username'] : '';

    if (empty($username)) {
        echo json_encode(["status" => "error", "message" => "ไม่พบชื่อผู้ใช้ (Username is empty)"]);
        exit();
    }

    // 2. ตรวจสอบว่ามีการส่งไฟล์มาชื่อ 'avatar' หรือไม่
    if (!isset($_FILES['avatar'])) {
        echo json_encode(["status" => "error", "message" => "ไม่พบไฟล์รูปภาพ (กรุณาเช็คว่าแอปส่ง key ชื่อ 'avatar' มาหรือไม่)"]);
        exit();
    }

    $file = $_FILES['avatar'];

    // 3. ตรวจสอบ Error จากการอัปโหลดของ PHP
    if ($file['error'] !== 0) {
        echo json_encode(["status" => "error", "message" => "Upload Error Code: " . $file['error']]);
        exit();
    }

    // 4. จัดการโฟลเดอร์
    $target_dir = "uploads/profiles/";
    if (!file_exists($target_dir)) {
        // พยายามสร้างโฟลเดอร์ ถ้าสร้างไม่ได้ให้แจ้งเตือน
        if (!mkdir($target_dir, 0777, true)) {
            echo json_encode(["status" => "error", "message" => "ไม่สามารถสร้างโฟลเดอร์ uploads/profiles/ ได้ (Permission Denied)"]);
            exit();
        }
    }

    // 5. ย้ายไฟล์
    $filename = "user_" . time() . "_" . rand(100,999) . ".jpg";
    $target_path = $target_dir . $filename;

    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        
        // 6. อัปเดตลงฐานข้อมูล
        $sql = "UPDATE users SET avatar = ? WHERE username = ?";
        
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("ss", $filename, $username);
            
            if ($stmt->execute()) {
                // เช็คว่ามีแถวถูกกระทบจริงไหม (ถ้า username ไม่ตรงกับใครเลย rows_affected จะเป็น 0)
                if ($stmt->affected_rows > 0) {
                    echo json_encode([
                        "status" => "success", 
                        "message" => "อัปเดตรูปโปรไฟล์สำเร็จ", 
                        "avatar" => $filename,
                        "url" => $filename // ส่งชื่อไฟล์กลับไปให้แอปแสดงผล
                    ]);
                } else {
                    echo json_encode(["status" => "warning", "message" => "อัปโหลดรูปแล้ว แต่ไม่พบ Username นี้ในระบบ หรือ รูปซ้ำกับของเดิม"]);
                }
            } else {
                echo json_encode(["status" => "error", "message" => "SQL Error: " . $stmt->error]);
            }
            $stmt->close();
        } else {
            echo json_encode(["status" => "error", "message" => "Prepare Failed: " . $conn->error]);
        }

    } else {
        echo json_encode(["status" => "error", "message" => "ย้ายไฟล์ไม่สำเร็จ (Check Folder Permissions)"]);
    }
}

// ==========================================
// 8. GET USER PROFILE (ดึงข้อมูลล่าสุด) ✅ เพิ่มใหม่
// ==========================================
else if ($action == 'get_user_profile') {
    $username = $_GET['username'];
    $sql = "SELECT fullname, role, avatar FROM users WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode($row);
    } else {
        echo json_encode(["status" => "error"]);
    }
}

$conn->close();
?>
