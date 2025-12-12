<?php
session_start();
require_once 'auth.php'; // ตรวจสอบการล็อกอิน

// ถ้ายังไม่ได้ล็อกอิน ให้เด้งไปหน้า login
if (!isset($_SESSION['fullname'])) { 
    header("Location: login.php"); 
    exit(); 
}

$message = "";

// 1. เรียกใช้ไฟล์เชื่อมต่อฐานข้อมูล
require_once 'db_connect.php';

// ==========================================
// ✅ ฟังก์ชันอัปโหลดรูป (แก้ไขใหม่ แก้ Permission Denied)
// ==========================================
function uploadReceipt($fileInputName) {
    // ตรวจสอบว่ามีการส่งไฟล์มาจริงไหม
    if (isset($_FILES[$fileInputName]) && $_FILES[$fileInputName]['error'] == 0) {
        
        // 1. ระบุที่อยู่โฟลเดอร์แบบ "เต็ม" (Absolute Path) เพื่อกัน Server หลงทาง
        // __DIR__ จะดึง path จริงของไฟล์นี้ออกมา ไม่ว่าจะอยู่ C:\xampp หรือ /var/www
        $target_dir = __DIR__ . "/uploads/";

        // 2. ถ้ายังไม่มีโฟลเดอร์ ให้สร้างใหม่ (เปิดสิทธิ์ 0777)
        if (!file_exists($target_dir)) { 
            @mkdir($target_dir, 0777, true); 
        }
        
        // 3. 💥 คำสั่งสำคัญ: สั่งปลดล็อกโฟลเดอร์ซ้ำอีกที (เพื่อให้ PHP เขียนไฟล์ได้ชัวร์ๆ บน Linux)
        @chmod($target_dir, 0777);
        
        // ตั้งชื่อไฟล์ใหม่ (กันชื่อซ้ำ)
        $fileExtension = pathinfo($_FILES[$fileInputName]["name"], PATHINFO_EXTENSION);
        $newFileName = "receipt_" . time() . "_" . rand(100, 999) . "." . $fileExtension;
        
        // ระบุปลายทางไฟล์แบบเต็ม
        $target_file = $target_dir . $newFileName;
        
        // 4. ย้ายไฟล์จาก Temp ไปยังโฟลเดอร์จริง
        if (move_uploaded_file($_FILES[$fileInputName]["tmp_name"], $target_file)) {
            return $newFileName; // ส่งชื่อไฟล์กลับไปบันทึกใน DB
        }
    }
    return ""; // ถ้าไม่มีไฟล์ หรืออัปโหลดไม่ผ่าน ให้คืนค่าว่าง
}

// ==========================================
// ส่วนบันทึกข้อมูลเมื่อกด Submit
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. รับค่าข้อมูลทั่วไป
    $report_date = $_POST['report_date'];
    $reporter_name = $_SESSION['fullname']; 
    $work_type = $_POST['work_type']; 
    
    if ($work_type == 'company') {
        $area = "เข้าบริษัท (สำนักงาน)"; 
        $province = "กรุงเทพมหานคร"; 
        $gps = "Office"; 
        $gps_address = "สำนักงานใหญ่";
    } else {
        $area = isset($_POST['area_zone']) ? $_POST['area_zone'] : 'ไม่ได้ระบุโซน';
        $province = isset($_POST['province']) ? $_POST['province'] : '';
        $gps = isset($_POST['gps']) ? $_POST['gps'] : '';
        $gps_address = isset($_POST['gps_address']) ? $_POST['gps_address'] : '';
    }

    // 2. ข้อมูลงาน
    $work_result = $_POST['work_result'];
    $customer_type = isset($_POST['customer_type']) ? $_POST['customer_type'] : '';
    $project_name = isset($_POST['project_name']) ? $_POST['project_name'] : '';
    $additional_notes = isset($_POST['additional_notes']) ? $_POST['additional_notes'] : ''; 
    
    $job_status = $_POST['job_status']; 
    $next_appointment = !empty($_POST['next_appointment']) ? $_POST['next_appointment'] : NULL;
    
    $activity_type = isset($_POST['activity_type']) ? $_POST['activity_type'] : ''; 
    $activity_detail = isset($_POST['activity_detail']) ? $_POST['activity_detail'] : '';

    // 3. ข้อมูลเงิน
    $fuel_cost = !empty($_POST['fuel_cost']) ? floatval($_POST['fuel_cost']) : 0;
    $accommodation_cost = !empty($_POST['accommodation_cost']) ? floatval($_POST['accommodation_cost']) : 0;
    $other_cost = !empty($_POST['other_cost']) ? floatval($_POST['other_cost']) : 0;
    $other_cost_detail = isset($_POST['other_cost_detail']) ? $_POST['other_cost_detail'] : '';
    
    // เรียกฟังก์ชันอัปโหลด (ที่แก้แล้วด้านบน)
    $fuel_receipt = uploadReceipt('fuel_receipt_file');
    $accommodation_receipt = uploadReceipt('accommodation_receipt_file');
    $other_receipt = uploadReceipt('other_receipt_file');

    $total_expense = $fuel_cost + $accommodation_cost + $other_cost;
    
    $problem = isset($_POST['problem']) ? $_POST['problem'] : '';
    $suggestion = isset($_POST['suggestion']) ? $_POST['suggestion'] : '';

    // เตรียม SQL
    $sql = "INSERT INTO reports (
                report_date, reporter_name, area, province, gps, gps_address, 
                work_result, customer_type, project_name, additional_notes, job_status, next_appointment, activity_type, activity_detail,
                fuel_cost, fuel_receipt, accommodation_cost, accommodation_receipt, 
                other_cost, other_receipt, other_cost_detail, total_expense, 
                problem, suggestion
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("ssssssssssssssdsdsdssdss", 
            $report_date, $reporter_name, $area, $province, $gps, $gps_address, 
            $work_result, $customer_type, $project_name, $additional_notes, $job_status, $next_appointment, $activity_type, $activity_detail,
            $fuel_cost, $fuel_receipt, $accommodation_cost, $accommodation_receipt, 
            $other_cost, $other_receipt, $other_cost_detail, $total_expense, 
            $problem, $suggestion
        );

        if ($stmt->execute()) { 
            // บันทึกสำเร็จ -> ไปหน้าประวัติ
            header("Location: StaffHistory.php");
            exit();
        } else { 
            $message = "<div class='alert error'>❌ Error: " . $stmt->error . "</div>"; 
        }
        $stmt->close();
    } else {
        $message = "<div class='alert error'>❌ Prepare Error: " . $conn->error . "</div>";
    }
    
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายงานฝ่ายการตลาด TJC</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        /* CSS เดิม */
        :root { --primary-color: #4e54c8; --secondary-color: #8f94fb; --card-bg: rgba(255, 255, 255, 0.95); --shadow: 0 10px 30px rgba(0,0,0,0.1); }
        body { font-family: 'Sarabun', sans-serif; background: linear-gradient(-45deg, #ee7752, #e73c7e, #23a6d5, #23d5ab); background-size: 400% 400%; animation: gradientBG 15s ease infinite; margin: 0; padding: 20px; min-height: 100vh; color: #333; }
        @keyframes gradientBG { 0% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } 100% { background-position: 0% 50%; } }
        .container { width: 100%; max-width: 1200px; margin: 0 auto; background: var(--card-bg); padding: 40px; border-radius: 20px; box-shadow: var(--shadow); backdrop-filter: blur(10px); }
        .header-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; border-bottom: 2px solid #f0f0f0; padding-bottom: 20px; }
        h2 { margin: 0; color: var(--primary-color); font-size: 28px; font-weight: 700; }
        .row { display: flex; gap: 20px; margin-bottom: 20px; flex-wrap: wrap; }
        .col { flex: 1; min-width: 280px; } .col-full { width: 100%; }
        label { display: block; margin-bottom: 8px; font-weight: 600; color: #444; }
        input, select, textarea { width: 100%; padding: 14px; border: 2px solid #eee; border-radius: 12px; font-family: 'Sarabun', sans-serif; font-size: 16px; transition: all 0.3s ease; box-sizing: border-box; background: #fafafa; }
        input:focus, select:focus, textarea:focus { border-color: var(--secondary-color); background: #fff; box-shadow: 0 0 0 4px rgba(143, 148, 251, 0.2); outline: none; }
        input[readonly] { background: #e9ecef; cursor: not-allowed; }
        .work-type-selector { display: flex; gap: 15px; }
        .work-type-label { flex: 1; padding: 20px; border: 2px solid #eee; border-radius: 15px; cursor: pointer; text-align: center; font-weight: bold; color: #777; transition: 0.3s; background: white; }
        .work-type-label:hover { border-color: var(--secondary-color); }
        .work-type-selector input:checked + .work-type-label { border-color: var(--primary-color); color: var(--primary-color); background: #f0f3ff; box-shadow: 0 5px 15px rgba(78, 84, 200, 0.2); transform: translateY(-2px); }
        .work-type-label i { font-size: 24px; margin-bottom: 5px; display: block; }
        .btn-gps { background: linear-gradient(45deg, #11998e, #38ef7d); color: white; border: none; padding: 0 25px; border-radius: 10px; font-weight: bold; cursor: pointer; transition: 0.3s; white-space: nowrap; }
        .btn-gps:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(56, 239, 125, 0.4); }
        .btn-submit { width: 100%; background: linear-gradient(45deg, #4e54c8, #8f94fb); color: white; border: none; padding: 18px; font-size: 20px; border-radius: 12px; font-weight: bold; cursor: pointer; transition: 0.3s; box-shadow: 0 10px 20px rgba(78, 84, 200, 0.3); margin-top: 30px; }
        .btn-submit:hover { transform: translateY(-3px); box-shadow: 0 15px 30px rgba(78, 84, 200, 0.4); }
        .expense-box { background: #fff; border: 2px dashed #dce0e6; padding: 25px; border-radius: 15px; transition: 0.3s; }
        .expense-box:hover { border-color: var(--secondary-color); }
        .expense-option { background: #f8f9fa; padding: 15px; border-radius: 12px; margin-bottom: 15px; transition: 0.3s; }
        .expense-option:hover { background: #fff; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .expense-input-group { display: none; margin-top: 15px; animation: fadeIn 0.4s ease; padding-left: 10px; border-left: 3px solid var(--secondary-color); }
        .checkbox-label { display: flex; align-items: center; gap: 12px; font-size: 18px; cursor: pointer; color: #333; }
        .checkbox-label input[type="checkbox"] { width: 24px; height: 24px; accent-color: var(--primary-color); cursor: pointer; }
        input[type="file"] { background: white; padding: 8px; font-size: 14px; border: 1px dashed #ccc; }
        .total-display { text-align: right; font-size: 24px; font-weight: 800; color: var(--primary-color); margin-top: 20px; padding-top: 20px; border-top: 2px solid #eee; }
        .alert { padding: 20px; border-radius: 12px; margin-bottom: 30px; font-weight: bold; text-align: center; animation: fadeIn 0.5s ease; }
        .alert.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .map-link { font-size: 14px; text-decoration: none; color: #007bff; margin-left: 10px; display: none; margin-top:5px; }
        .map-link:hover { text-decoration: underline; }
        .section-header { font-size: 18px; font-weight: bold; color: var(--primary-color); margin-bottom: 15px; border-left: 5px solid var(--secondary-color); padding-left: 10px; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        @media (max-width: 768px) { .container { padding: 20px; } .header-bar { flex-direction: column; text-align: center; gap: 15px; } .row { flex-direction: column; } }
    </style>
</head>
<body>
<?php include 'sidebar.php'; ?>
<div class="container">
    <div class="header-bar">
        <h2><i class="fas fa-chart-line"></i> รายงานฝ่ายการตลาด TJC</h2>
        <div class="user-info">
            <span><i class="fas fa-user-circle"></i> สวัสดี, <strong><?php echo $_SESSION['fullname']; ?></strong></span>
        </div>
    </div>
    <?php echo $message; ?>

    <form method="post" action="Report.php" enctype="multipart/form-data" onsubmit="return validateForm()">
        <div class="row">
            <div class="col"><label>📅 วันที่</label><input type="date" name="report_date" value="<?php echo date('Y-m-d'); ?>" required></div>
            <div class="col"><label>👤 ผู้รายงาน</label><input type="text" name="reporter_name" value="<?php echo $_SESSION['fullname']; ?>" readonly style="background:#e9ecef;"></div>
        </div>

        <div class="form-group" style="margin-bottom: 25px;">
            <label>📍 สถานที่ทำงาน</label>
            <div class="work-type-selector">
                <label><input type="radio" name="work_type" value="company" onclick="toggleWorkMode('company')" checked><div class="work-type-label"><i class="fas fa-building"></i> เข้าบริษัท</div></label>
                <label><input type="radio" name="work_type" value="outside" onclick="toggleWorkMode('outside')"><div class="work-type-label"><i class="fas fa-car-side"></i> นอกสถานที่</div></label>
            </div>
        </div>

        <div id="outsideOptions" style="display:none; animation: fadeIn 0.5s ease;">
            <div class="row" style="background: #f0f3ff; padding: 20px; border-radius: 15px;">
                <div class="col">
                    <label>เลือกภาค/โซน:</label>
                    <select name="area_zone" id="areaSelect" onchange="updateProvinces()">
                        <option value="">-- กรุณาเลือกภาค --</option>
                        <option value="อุบลราชธานี">เฉพาะ จ.อุบลราชธานี</option>
                        <option value="ภาคอีสาน">ภาคอีสาน</option>
                        <option value="ภาคเหนือ">ภาคเหนือ</option>
                        <option value="ภาคกลาง">ภาคกลาง</option>
                        <option value="ภาคใต้">ภาคใต้</option>
                    </select>
                </div>
                <div class="col">
                    <label>จังหวัด:</label>
                    <select name="province" id="provinceSelect"><option value="">-- รอเลือกภาค --</option></select>
                </div>
            </div>
            
            <div class="row" style="background: #f0f3ff; padding: 20px; border-radius: 15px; margin-top: 10px;">
                <div class="col-full" style="width:100%">
                    <label>📌 พิกัด GPS</label>
                    <div style="display:flex; gap:10px; align-items:center; margin-bottom:10px;">
                        <input type="text" id="gpsInput" name="gps" placeholder="รอจับพิกัด..." readonly style="background: white;">
                        <button type="button" class="btn-gps" onclick="getLocation()"><i class="fas fa-location-arrow"></i> จับพิกัด</button>
                    </div>
                    <a id="googleMapLink" href="#" target="_blank" class="map-link">🌐 ดูตำแหน่งใน Google Maps</a>
                    <label style="margin-top:10px;">🏠 ที่อยู่ (พิมพ์แก้ไขได้):</label>
                    <input type="text" id="addressInput" name="gps_address" placeholder="ที่อยู่จะขึ้นอัตโนมัติ หรือพิมพ์เองก็ได้...">
                </div>
            </div>
        </div>

        <hr style="border: 0; border-top: 1px solid #eee; margin: 30px 0;">

        <div class="section-header">📝 ผลปฏิบัติงาน (รายละเอียดงานที่ทำ)</div>
        
        <div class="row">
            <div class="col-full">
                <label>💼 ชื่อโครงการ</label>
                <input type="text" name="project_name" placeholder="ระบุชื่อโครงการ..." >
            </div>
        </div>

        <div class="row">
            <div class="col">
                <label>🏢 ชื่อหน่วยงาน / ลูกค้าที่ติดต่อ</label>
                <input type="text" name="work_result" placeholder="ระบุชื่อหน่วยงาน เช่น โรงเรียนนารีนุกูล..." required>
            </div>
            <div class="col">
                <label>👥 ประเภทลูกค้า</label>
                <div class="work-type-selector">
                    <label>
                        <input type="radio" name="customer_type" value="ลูกค้าเก่า" checked>
                        <div class="work-type-label"><i class="fas fa-user-check"></i> ลูกค้าเก่า</div>
                    </label>
                    <label>
                        <input type="radio" name="customer_type" value="ลูกค้าใหม่">
                        <div class="work-type-label"><i class="fas fa-user-plus"></i> ลูกค้าใหม่</div>
                    </label>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-full">
                <label>📄 บันทึกเพิ่มเติม</label>
                <textarea name="additional_notes" rows="3" placeholder="รายละเอียดอื่นๆ (ถ้ามี)..."></textarea>
            </div>
        </div>

        <div class="row">
            <div class="col" id="activitySection">
                <label>📋 กิจกรรมที่ทำ</label>
                <select name="activity_type" id="activitySelect" onchange="toggleActivityDetail()">
                    <option value="">-- เลือกกิจกรรม --</option>
                </select>
                <input type="text" name="activity_detail" id="activityDetail" placeholder="โปรดระบุกิจกรรม..." style="display:none; margin-top:10px;">
            </div>
            <div class="col">
                <label>📊 สถานะงาน</label>
                <select name="job_status" id="jobStatusSelect" required>
                    <option value="">-- เลือกสถานะ --</option>
                </select>
            </div>
            <div class="col">
                <label>📅 นัดหมายครั้งถัดไป</label>
                <input type="date" name="next_appointment">
            </div>
        </div>

        <div class="form-group" style="margin-top: 20px;">
            <label>💸 การเบิกค่าใช้จ่าย</label>
            <div class="expense-box">
                <div class="row">
                    <div class="col"><div class="expense-option"><label class="checkbox-label"><input type="checkbox" onclick="toggleExpense('fuel_group', 'fuel_input')"> ⛽ ค่าน้ำมัน</label><div id="fuel_group" class="expense-input-group"><input type="number" step="0.01" id="fuel_input" name="fuel_cost" class="calc-expense" placeholder="บาท"><div style="margin-top:10px;"><small>📸 ใบเสร็จ:</small><input type="file" name="fuel_receipt_file" accept="image/*"></div></div></div></div>
                    <div class="col"><div class="expense-option"><label class="checkbox-label"><input type="checkbox" onclick="toggleExpense('hotel_group', 'hotel_input')"> 🏨 ค่าที่พัก</label><div id="hotel_group" class="expense-input-group"><input type="number" step="0.01" id="hotel_input" name="accommodation_cost" class="calc-expense" placeholder="บาท"><div style="margin-top:10px;"><small>📸 ใบเสร็จ:</small><input type="file" name="accommodation_receipt_file" accept="image/*"></div></div></div></div>
                    <div class="col"><div class="expense-option"><label class="checkbox-label"><input type="checkbox" onclick="toggleExpense('other_group', 'other_input')"> 🧩 อื่นๆ</label><div id="other_group" class="expense-input-group"><input type="text" name="other_cost_detail" placeholder="ระบุรายการ..." style="margin-bottom:10px;"><input type="number" step="0.01" id="other_input" name="other_cost" class="calc-expense" placeholder="บาท"><div style="margin-top:10px;"><small>📸 ใบเสร็จ:</small><input type="file" name="other_receipt_file" accept="image/*"></div></div></div></div>
                </div>
                <div class="total-display">รวม: <span id="totalExpenseDisplay">0.00</span> บาท</div>
            </div>
        </div>

        <hr style="border: 0; border-top: 1px solid #eee; margin: 30px 0;">

        <div class="row">
            <div class="col"><label>⚠️ ปัญหาที่พบ</label><textarea name="problem" rows="3"></textarea></div>
            <div class="col"><label>💡 ข้อเสนอแนะ</label><textarea name="suggestion" rows="3"></textarea></div>
        </div>
        <button type="submit" class="btn-submit"><i class="fas fa-paper-plane"></i> ส่งรายงาน</button>
    </form>
</div>

<script>
    async function updateProvinces() {
        const areaSelect = document.getElementById("areaSelect");
        const provinceSelect = document.getElementById("provinceSelect");
        const selectedArea = areaSelect.value;

        provinceSelect.innerHTML = '';

        if (selectedArea === 'อุบลราชธานี') {
            let option = document.createElement("option");
            option.value = 'อุบลราชธานี';
            option.text = 'อุบลราชธานี';
            option.selected = true; 
            provinceSelect.appendChild(option);
            return; 
        }

        provinceSelect.innerHTML = '<option value="">กำลังโหลด...</option>';
        
        if (selectedArea) {
            try {
                // แก้ให้เรียก path ให้ถูก (เผื่ออยู่คนละ folder)
                const response = await fetch('api_data.php?action=get_provinces&region=' + selectedArea);
                const provinces = await response.json();
                
                provinceSelect.innerHTML = '<option value="">-- เลือกจังหวัด --</option>';
                provinces.forEach(p => {
                    let option = document.createElement("option"); 
                    option.value = p; 
                    option.text = p; 
                    provinceSelect.appendChild(option);
                });
            } catch (err) { 
                provinceSelect.innerHTML = '<option value="">โหลดข้อมูลล้มเหลว</option>'; 
            }
        } else { 
            provinceSelect.innerHTML = '<option value="">-- รอเลือกภาค --</option>'; 
        }
    }

    async function loadJobStatus() {
        const select = document.getElementById("jobStatusSelect");
        try {
            const response = await fetch('api_data.php?action=get_job_status');
            const data = await response.json();
            data.forEach(item => {
                let option = document.createElement("option"); option.value = item; option.text = item; select.appendChild(option);
            });
        } catch (err) { console.error("Error loading statuses"); }
    }

    async function loadActivityTypes() {
        const select = document.getElementById("activitySelect");
        try {
            const response = await fetch('api_data.php?action=get_activities');
            const data = await response.json();
            data.forEach(item => {
                let option = document.createElement("option"); option.value = item; option.text = item; select.appendChild(option);
            });
        } catch (err) { console.error("Error loading activities"); }
    }
    
    function toggleActivityDetail() {
        var select = document.getElementById("activitySelect");
        var input = document.getElementById("activityDetail");
        if (select.value === "อื่นๆ") {
            input.style.display = "block";
            input.required = true;
        } else {
            input.style.display = "none";
            input.required = false;
            input.value = "";
        }
    }

    window.addEventListener('DOMContentLoaded', (event) => {
        loadJobStatus();
        loadActivityTypes();
        var radios = document.getElementsByName('work_type');
        for (var i = 0; i < radios.length; i++) { if (radios[i].checked) { toggleWorkMode(radios[i].value); break; } }
    });

    const expenseInputs = document.querySelectorAll('.calc-expense');
    const totalDisplay = document.getElementById('totalExpenseDisplay');
    expenseInputs.forEach(input => { input.addEventListener('input', calculateTotal); });
    function calculateTotal() {
        let total = 0;
        expenseInputs.forEach(input => { let val = parseFloat(input.value); if (!isNaN(val)) total += val; });
        totalDisplay.innerText = total.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,'); 
    }
    function toggleExpense(groupId, inputId) {
        var group = document.getElementById(groupId);
        var input = document.getElementById(inputId);
        if (group.style.display === "none" || group.style.display === "") { group.style.display = "block"; input.focus(); } 
        else { group.style.display = "none"; input.value = ""; calculateTotal(); }
    }
    
    function toggleWorkMode(mode) {
        var outsideDiv = document.getElementById("outsideOptions");
        var activitySection = document.getElementById("activitySection"); 

        if (mode === 'outside') { 
            outsideDiv.style.display = "block"; 
            if(activitySection) activitySection.style.display = "none"; 
        } else { 
            outsideDiv.style.display = "none"; 
            if(activitySection) activitySection.style.display = "block"; 
            document.getElementById("gpsInput").value = ""; 
            document.getElementById("addressInput").value = "";
        }
    }

    function validateForm() {
        var workType = document.querySelector('input[name="work_type"]:checked').value;
        if (workType === 'outside') { 
            if(document.getElementById("areaSelect").value === "") { alert("⚠️ กรุณาเลือกภาคและจังหวัดครับ"); return false; }
            if(document.getElementById("addressInput").value === "") { alert("⚠️ กรุณาระบุที่อยู่ หรือกด 'จับพิกัด' ครับ"); return false; }
        }
        return true;
    }

    function getLocation() {
        if (navigator.geolocation) {
            var gpsInput = document.getElementById("gpsInput");
            var addrInput = document.getElementById("addressInput");
            gpsInput.value = "กำลังค้นหาดาวเทียม...";
            addrInput.value = "กำลังประมวลผล...";
            var options = { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 };
            navigator.geolocation.getCurrentPosition(showPosition, showError, options);
        } else { alert("Browser ไม่รองรับ GPS"); }
    }

    async function showPosition(position) {
        var lat = position.coords.latitude;
        var lng = position.coords.longitude;
        var accuracy = position.coords.accuracy;
        document.getElementById("gpsInput").value = lat.toFixed(6) + ", " + lng.toFixed(6);
        
        var mapLink = document.getElementById("googleMapLink");
        mapLink.href = `https://www.google.com/maps?q=${lat},${lng}`;
        mapLink.style.display = "inline-block";
        mapLink.innerHTML = `🌐 ดูพิกัดใน Google Maps (คลาดเคลื่อน ±${Math.round(accuracy)} ม.)`;

        try {
            const response = await fetch(`https://api.bigdatacloud.net/data/reverse-geocode-client?latitude=${lat}&longitude=${lng}&localityLanguage=th`);
            const data = await response.json();
            let address = "";
            if (data.principalSubdivision) address += "จ." + data.principalSubdivision;
            if (data.locality) address += " อ." + data.locality;
            else if (data.city) address += " " + data.city;
            document.getElementById("addressInput").value = address;
        } catch (error) { document.getElementById("addressInput").value = "จับพิกัดแล้ว (พิมพ์ระบุที่อยู่เองได้เลย)"; }
    }

    function showError(error) {
        alert("❌ ไม่สามารถจับพิกัดได้ (พิมพ์ที่อยู่เองได้เลยครับ)");
        document.getElementById("gpsInput").value = "ระบุไม่ได้";
        document.getElementById("addressInput").value = "";
        document.getElementById("addressInput").focus();
    }
</script>

</body>
</html>