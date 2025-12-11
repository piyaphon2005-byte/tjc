<?php
session_start();
// ตรวจสอบ Login
if (!isset($_SESSION['fullname'])) {
    header("Location: login.php");
    exit();
}

$current_user = $_SESSION['fullname']; 

require_once 'db_connect.php';
$conn->set_charset("utf8"); // สำคัญ: แก้ภาษาต่างดาว

if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

// --- 1. ดึงข้อมูลสรุปส่วนตัว ---
$sql_summary = "SELECT 
    COUNT(*) as my_total_reports, 
    SUM(total_expense) as my_total_expenses,
    SUM(CASE WHEN job_status = 'ได้งาน' THEN 1 ELSE 0 END) as my_won
    FROM reports WHERE reporter_name = '$current_user'";

$result_summary = $conn->query($sql_summary);
$summary = $result_summary->fetch_assoc();

// --- 2. ดึงรายการรายงานของฉัน ---
// ✅ เพิ่ม project_name และ additional_notes เข้ามาด้วย
$sql_list = "SELECT * FROM reports WHERE reporter_name = '$current_user' ORDER BY report_date DESC, id DESC";
$result_list = $conn->query($sql_list);

// Helper function for JS strings
function js_safe($str) {
    return addslashes(str_replace(array("\r\n", "\r", "\n"), '\n', $str));
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ประวัติการทำงาน - <?php echo $current_user; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        /* Theme & Layout */
        :root { --primary: #4e54c8; --secondary: #8f94fb; --card-bg: rgba(255, 255, 255, 0.95); }
        body { font-family: 'Sarabun', sans-serif; background: linear-gradient(-45deg, #ee7752, #e73c7e, #23a6d5, #23d5ab); background-size: 400% 400%; animation: gradientBG 15s ease infinite; margin: 0; padding: 0; min-height: 100vh; color: #333; }
        @keyframes gradientBG { 0% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } 100% { background-position: 0% 50%; } }
        
        .navbar { background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(10px); padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 20px rgba(0,0,0,0.05); position: sticky; top: 0; z-index: 1000; }
        .navbar h2 { margin: 0; font-size: 20px; color: var(--primary); font-weight: 800; }
        .nav-links a { color: #555; text-decoration: none; margin-left: 15px; padding: 8px 15px; border-radius: 50px; font-weight: bold; transition: 0.3s; }
        .nav-links a:hover { background: #f0f3ff; color: var(--primary); }
        .nav-links .btn-logout { background: #ffe5e5; color: #ff4757; }

        .container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }

        /* Mini Stats */
        .stats-grid { display: flex; gap: 20px; margin-bottom: 20px; flex-wrap: wrap; }
        .stat-card { flex: 1; background: var(--card-bg); padding: 20px; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); text-align: center; min-width: 150px; }
        .stat-card h3 { margin: 0; font-size: 14px; color: #888; }
        .stat-card p { margin: 5px 0 0; font-size: 24px; font-weight: 800; color: var(--primary); }

        /* Table */
        .table-container { background: var(--card-bg); border-radius: 20px; padding: 25px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 1000px; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid #eee; vertical-align: top; }
        th { background: #f8f9fa; color: #555; font-weight: 800; white-space: nowrap; border-radius: 5px; }
        tr:hover { background: #f1f2f6; }

        .badge { padding: 4px 8px; border-radius: 15px; font-size: 11px; font-weight: bold; display: inline-block; margin-bottom: 4px; }
        .badge-company { background: #e3f2fd; color: #2196f3; }
        .badge-outside { background: #e8f5e9; color: #4caf50; }
        
        .status-won { color: #155724; background-color: #d4edda; padding: 4px 8px; border-radius: 12px; font-size: 11px; font-weight: bold; }
        .status-follow { color: #856404; background-color: #fff3cd; padding: 4px 8px; border-radius: 12px; font-size: 11px; font-weight: bold; }
        .status-lost { color: #721c24; background-color: #f8d7da; padding: 4px 8px; border-radius: 12px; font-size: 11px; font-weight: bold; }

        .btn-view { padding: 4px 8px; background: var(--primary); color: white; border-radius: 5px; text-decoration: none; font-size: 11px; display: inline-block; margin: 2px; }
        .btn-detail { background: none; border: none; color: var(--primary); cursor: pointer; font-size: 18px; transition: 0.2s; }
        .btn-detail:hover { transform: scale(1.1); color: var(--secondary); }
        
        .no-data { text-align: center; padding: 30px; color: #999; font-style: italic; }

        /* Modal Styles */
        .modal { display: none; position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4); }
        .modal-content { background-color: #fefefe; margin: 10% auto; padding: 25px; border-radius: 15px; width: 80%; max-width: 600px; box-shadow: 0 5px 30px rgba(0,0,0,0.2); animation: slideDown 0.3s; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .modal-header h3 { margin: 0; color: var(--primary); }
        .close { color: #aaa; font-size: 28px; font-weight: bold; cursor: pointer; }
        .close:hover { color: #000; }
        .modal-body p { margin-bottom: 10px; line-height: 1.6; }
        .modal-body strong { color: #555; display: inline-block; width: 120px; }
        
        @keyframes slideDown { from { transform: translateY(-50px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        @media (max-width: 768px) { .navbar { flex-direction: column; gap: 10px; } .modal-content { width: 90%; margin: 20% auto; } }
    </style>
</head>
<body>
<?php include 'sidebar.php'; ?>
    <div class="navbar">
        <h2><i class="fas fa-user-clock"></i> ประวัติการส่งรายงาน</h2>
        <div class="nav-links">
            <span>👤 <?php echo $current_user; ?></span>
            <a href="Report.php" style="background:#e8f5e9; color:#2ecc71;"><i class="fas fa-plus-circle"></i> เขียนรายงานใหม่</a>
            <a href="logout.php" class="btn-logout">ออก</a>
        </div>
    </div>

    <div class="container">
        
        <div class="stats-grid">
            <div class="stat-card">
                <h3>📝 ส่งแล้วทั้งหมด</h3>
                <p><?php echo number_format($summary['my_total_reports']); ?> ฉบับ</p>
            </div>
            <div class="stat-card">
                <h3>✅ ปิดการขายได้</h3>
                <p style="color:#2ecc71;"><?php echo number_format($summary['my_won']); ?> งาน</p>
            </div>
            <div class="stat-card">
                <h3>💸 เบิกค่าใช้จ่ายรวม</h3>
                <p style="color:#ff7675;"><?php echo number_format($summary['my_total_expenses']); ?> บ.</p>
            </div>
        </div>

        <div class="table-container">
            <h3 style="margin-top:0; margin-bottom:20px; color:#4e54c8;">📋 รายการย้อนหลังของคุณ</h3>
            
            <table>
                <thead>
                    <tr>
                        <th width="12%">วันที่</th>
                        <th width="15%">สถานที่</th>
                        <th width="15%">โครงการ</th> <th width="20%">ลูกค้า/หน่วยงาน</th>
                        <th width="15%">กิจกรรม/สถานะ</th>
                        <th width="12%">เบิกจ่าย</th>
                        <th width="8%">หลักฐาน</th>
                        <th width="8%" style="text-align:center;">รายละเอียด</th> </tr>
                </thead>
                <tbody>
                    <?php if ($result_list->num_rows > 0): ?>
                        <?php while($row = $result_list->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <strong><?php echo date('d/m/Y', strtotime($row['report_date'])); ?></strong>
                                    <div style="font-size:12px; color:#888;">
                                        <?php if($row['gps'] == 'Office') echo '<span class="badge badge-company">🏢 ออฟฟิศ</span>'; else echo '<span class="badge badge-outside">🚗 ข้างนอก</span>'; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php 
                                        if($row['gps'] == 'Office') {
                                            echo "สำนักงานใหญ่";
                                        } else {
                                            if(!empty($row['province'])) echo "<strong>จ.".$row['province']."</strong><br>";
                                            if(!empty($row['gps_address'])) echo "<small style='color:#666;'>".$row['gps_address']."</small>";
                                            else echo "<small style='color:#ccc;'>ไม่ระบุพิกัด</small>";
                                        }
                                    ?>
                                </td>
                                <td style="color:#4e54c8; font-weight:bold; font-size:14px;">
                                    <?php echo !empty($row['project_name']) ? $row['project_name'] : '-'; ?>
                                </td>
                                <td>
                                    <div style="font-weight:bold; color:#333;"><?php echo $row['work_result']; ?></div>
                                    <small style="color:#666;">(<?php echo $row['customer_type']; ?>)</small>
                                </td>
                                <td>
                                    <div style="margin-bottom:5px;"><?php echo $row['activity_type']; ?></div>
                                    <?php 
                                        $statusClass = '';
                                        if($row['job_status'] == 'ได้งาน') $statusClass = 'status-won';
                                        else if($row['job_status'] == 'กำลังติดตาม') $statusClass = 'status-follow';
                                        else $statusClass = 'status-lost';
                                        echo "<span class='$statusClass'>".$row['job_status']."</span>";
                                        
                                        if(!empty($row['next_appointment']) && $row['next_appointment'] != '0000-00-00') {
                                            echo '<div style="margin-top:5px; font-size:11px; color:#e67e22"><i class="far fa-calendar"></i> นัดต่อ: '.date('d/m', strtotime($row['next_appointment'])).'</div>';
                                        }
                                    ?>
                                </td>
                                <td>
                                    <?php if($row['total_expense'] > 0): ?>
                                        <strong style="color:#ff7675;"><?php echo number_format($row['total_expense']); ?></strong>
                                    <?php else: ?>
                                        <span style="color:#ccc;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                        $hasImg = false;
                                        if(!empty($row['fuel_receipt'])) { echo '<a href="uploads/'.$row['fuel_receipt'].'" target="_blank" class="btn-view">⛽</a> '; $hasImg=true; }
                                        if(!empty($row['accommodation_receipt'])) { echo '<a href="uploads/'.$row['accommodation_receipt'].'" target="_blank" class="btn-view">🏨</a> '; $hasImg=true; }
                                        if(!empty($row['other_receipt'])) { echo '<a href="uploads/'.$row['other_receipt'].'" target="_blank" class="btn-view">🧩</a>'; $hasImg=true; }
                                        if(!$hasImg) echo "-";
                                    ?>
                                </td>
                                <td style="text-align:center;">
                                    <button onclick="openModal(
                                        '<?php echo js_safe($row['project_name']); ?>',
                                        '<?php echo js_safe($row['work_result']); ?>',
                                        '<?php echo js_safe($row['problem']); ?>',
                                        '<?php echo js_safe($row['suggestion']); ?>',
                                        '<?php echo js_safe($row['additional_notes']); ?>',
                                        '<?php echo number_format($row['fuel_cost']); ?>',
                                        '<?php echo number_format($row['accommodation_cost']); ?>',
                                        '<?php echo number_format($row['other_cost']); ?>'
                                    )" class="btn-detail" title="ดูรายละเอียดเพิ่มเติม">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="8" class="no-data">คุณยังไม่เคยส่งรายงานเข้าระบบครับ</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="detailModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-file-alt"></i> รายละเอียดเพิ่มเติม</h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <p><strong>📂 โครงการ:</strong> <span id="m-project" style="color:#4e54c8; font-weight:bold;"></span></p>
                <p><strong>🏢 ลูกค้า:</strong> <span id="m-client"></span></p>
                <hr style="border:0; border-top:1px dashed #ddd; margin:15px 0;">
                
                <p><strong>⚠️ ปัญหาที่พบ:</strong> <br><span id="m-problem" style="color:#e74c3c;"></span></p>
                <p><strong>💡 ข้อเสนอแนะ:</strong> <br><span id="m-suggestion" style="color:#3498db;"></span></p>
                <p><strong>📝 บันทึกเพิ่มเติม:</strong> <br><span id="m-notes"></span></p>
                
                <hr style="border:0; border-top:1px dashed #ddd; margin:15px 0;">
                <h4 style="margin:0 0 10px 0; color:#555;">💸 รายละเอียดค่าใช้จ่าย</h4>
                <p>⛽ น้ำมัน: <span id="m-fuel"></span> บาท</p>
                <p>🏨 ที่พัก: <span id="m-acc"></span> บาท</p>
                <p>🧩 อื่นๆ: <span id="m-other"></span> บาท</p>
            </div>
        </div>
    </div>

    <script>
        // Modal Logic
        function openModal(project, client, problem, suggestion, notes, fuel, acc, other) {
            document.getElementById('m-project').innerText = project || '-';
            document.getElementById('m-client').innerText = client || '-';
            
            // แปลง \n เป็น <br> เพื่อแสดงผล
            document.getElementById('m-problem').innerHTML = (problem || '-').replace(/\\n/g, '<br>');
            document.getElementById('m-suggestion').innerHTML = (suggestion || '-').replace(/\\n/g, '<br>');
            document.getElementById('m-notes').innerHTML = (notes || '-').replace(/\\n/g, '<br>');
            
            document.getElementById('m-fuel').innerText = fuel;
            document.getElementById('m-acc').innerText = acc;
            document.getElementById('m-other').innerText = other;
            
            document.getElementById('detailModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('detailModal').style.display = 'none';
        }

        // ปิดเมื่อคลิกพื้นหลัง
        window.onclick = function(event) {
            var modal = document.getElementById('detailModal');
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }
    </script>

</body>
</html>
<?php $conn->close(); ?>