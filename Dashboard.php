<?php
session_start();
require_once 'auth.php';
// 1. ตรวจสอบ Login
if (!isset($_SESSION['fullname'])) {
    header("Location: login.php");
    exit();
}

// ✅ แก้ไขสิทธิ์: ให้ทั้ง 'manager' และ 'admin' เข้าได้
if ($_SESSION['role'] !== 'manager' && $_SESSION['role'] !== 'admin') {
    header("Location: Main.php"); // ถ้าเป็น staff ให้ไปหน้าหลัก
    exit();
}

require_once 'db_connect.php'; 
// หรือ $conn = new mysqli("localhost", "root", "", "tjc_db"); $conn->set_charset("utf8");

// --- ตัวกรอง ---
$filter_name = isset($_GET['filter_name']) ? $_GET['filter_name'] : '';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

$where_sql = "WHERE 1=1"; 
if (!empty($filter_name)) { $where_sql .= " AND reporter_name = '$filter_name'"; }
if (!empty($start_date)) { $where_sql .= " AND report_date >= '$start_date'"; }
if (!empty($end_date)) { $where_sql .= " AND report_date <= '$end_date'"; }

// =========================================================
// ส่วนที่ 1: ดึงยอดรวม และ ค่าใช้จ่าย
// =========================================================
$sql_main = "SELECT COUNT(*) as total, SUM(total_expense) as expense FROM reports $where_sql";
$res_main = $conn->query($sql_main);
$main_data = $res_main->fetch_assoc();
$total_reports = $main_data['total'] ?? 0;
$total_expense = $main_data['expense'] ?? 0;

// =========================================================
// ส่วนที่ 2: ดึงยอดแยกตามสถานะ (Dynamic Loop)
// =========================================================
$sql_status = "SELECT job_status, COUNT(*) as count FROM reports $where_sql GROUP BY job_status";
$res_status = $conn->query($sql_status);

$status_stats = [];
while($row = $res_status->fetch_assoc()) {
    $status_stats[$row['job_status']] = $row['count'];
}

// Config สี (ถ้าสถานะตรงชื่อนี้จะใช้สีนี้)
$color_map = [
    'ได้งาน' => ['bg'=>'#d4edda', 'text'=>'#155724', 'border'=>'#2ecc71', 'icon'=>'fa-check-circle'],
    'กำลังติดตาม' => ['bg'=>'#fff3cd', 'text'=>'#856404', 'border'=>'#f1c40f', 'icon'=>'fa-stopwatch'],
    'เข้าเสนอโครงการ' => ['bg'=>'#d1ecf1', 'text'=>'#0c5460', 'border'=>'#17a2b8', 'icon'=>'fa-briefcase'],
    'ไม่ได้งาน' => ['bg'=>'#f8d7da', 'text'=>'#721c24', 'border'=>'#e74c3c', 'icon'=>'fa-times-circle'],
];

// สีสำรอง (สำหรับสถานะใหม่ๆ)
$default_colors = [
    ['bg'=>'#e2e3e5', 'text'=>'#383d41', 'border'=>'#6c757d', 'icon'=>'fa-folder'], 
    ['bg'=>'#d6d8d9', 'text'=>'#1b1e21', 'border'=>'#343a40', 'icon'=>'fa-tag'],
    ['bg'=>'#cce5ff', 'text'=>'#004085', 'border'=>'#007bff', 'icon'=>'fa-star'],
    ['bg'=>'#e0cffc', 'text'=>'#3d0899', 'border'=>'#6f42c1', 'icon'=>'fa-bolt']
];

// =========================================================
// ส่วนที่ 3: ดึงตารางรายงาน
// =========================================================
$sql_list = "SELECT * FROM reports $where_sql ORDER BY report_date DESC, id DESC";
$result_list = $conn->query($sql_list);

$sql_users = "SELECT DISTINCT reporter_name FROM reports ORDER BY reporter_name ASC";
$result_users = $conn->query($sql_users);

function js_safe($str) { return addslashes(str_replace(array("\r\n", "\r", "\n"), '\n', $str)); }
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard ผู้บริหาร - TJC</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        :root { --primary: #4e54c8; }
        body { font-family: 'Sarabun', sans-serif; background: #f0f2f5; margin: 0; padding: 0; min-height: 100vh; color: #333; }
        
        .navbar { background: #4e54c8; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 20px rgba(0,0,0,0.1); position: sticky; top: 0; z-index: 1000; }
        .navbar h2 { margin: 0; font-size: 24px; color: white; font-weight: 800; }
        .nav-links a { color: #eee; text-decoration: none; margin-left: 15px; padding: 8px 15px; border-radius: 50px; font-weight: bold; transition: 0.3s; }
        .nav-links a:hover { background: rgba(255,255,255,0.2); color: white; }
        .nav-links .btn-logout { background: #ff4757; color: white; }
        
        .container { max-width: 1400px; margin: 30px auto; padding: 0 20px; }

        .filter-box { background: white; padding: 20px; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); margin-bottom: 25px; display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end; }
        .filter-group { flex: 1; min-width: 180px; }
        .filter-group label { display: block; font-weight: bold; margin-bottom: 5px; color: #666; }
        .filter-group input, .filter-group select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; font-family: 'Sarabun'; }
        .btn-filter { background: var(--primary); color: white; border: none; padding: 10px 25px; border-radius: 8px; cursor: pointer; font-weight: bold; height: 42px; }
        .btn-reset { background: #95a5a6; color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: bold; display: flex; align-items: center; height: 42px; box-sizing: border-box; }

        .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .kpi-card { background: white; padding: 20px; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); border-left: 5px solid #ccc; position: relative; overflow: hidden; transition: transform 0.2s; }
        .kpi-card:hover { transform: translateY(-5px); }
        .kpi-info h3 { margin: 0; font-size: 14px; color: #666; font-weight: bold; }
        .kpi-info p { margin: 5px 0 0; font-size: 28px; font-weight: 800; color: #333; }
        .kpi-icon { position: absolute; right: 20px; bottom: 20px; font-size: 40px; opacity: 0.2; }

        .table-container { background: white; border-radius: 15px; padding: 25px; box-shadow: 0 5px 20px rgba(0,0,0,0.05); overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 1200px; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid #eee; vertical-align: top; }
        th { background: #f8f9fa; color: #555; font-weight: 800; }
        tr:hover { background: #f9f9f9; }
        .status-badge { padding: 5px 10px; border-radius: 20px; font-size: 12px; font-weight: bold; display: inline-block; color: white; }
        .btn-view { padding: 5px 10px; background: var(--primary); color: white; border-radius: 5px; text-decoration: none; font-size: 12px; margin-right: 3px; }
        
        .modal { display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
        .modal-content { background: white; margin: 10% auto; padding: 25px; border-radius: 15px; width: 60%; max-width: 600px; position: relative; }
        .close { position: absolute; right: 20px; top: 15px; font-size: 24px; cursor: pointer; color: #aaa; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>    
    <div class="navbar">
        <h2><i class="fas fa-chart-line"></i> TJC Dashboard</h2>
        <div class="nav-links">
            <a href="Main.php" style="background:rgba(255,255,255,0.2);"><i class="fas fa-home"></i> หน้าหลัก</a>
            <span>👤 <?php echo $_SESSION['fullname']; ?> (<?php echo ucfirst($_SESSION['role']); ?>)</span>
            <a href="logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> ออก</a>
        </div>
    </div>

    <div class="container">
        
        <form method="GET" class="filter-box">
            <div class="filter-group">
                <label>พนักงาน</label>
                <select name="filter_name">
                    <option value="">-- ทั้งหมด --</option>
                    <?php while($u = $result_users->fetch_assoc()) { 
                        $sel = ($filter_name == $u['reporter_name']) ? 'selected' : '';
                        echo "<option value='{$u['reporter_name']}' $sel>{$u['reporter_name']}</option>"; 
                    } ?>
                </select>
            </div>
            <div class="filter-group"><label>วันที่เริ่ม</label><input type="date" name="start_date" value="<?php echo $start_date; ?>"></div>
            <div class="filter-group"><label>ถึงวันที่</label><input type="date" name="end_date" value="<?php echo $end_date; ?>"></div>
            <button type="submit" class="btn-filter"><i class="fas fa-search"></i> ค้นหา</button>
            <a href="Dashboard.php" class="btn-reset"><i class="fas fa-sync-alt"></i> รีเซ็ต</a>
        </form>

        <div class="kpi-grid">
            <div class="kpi-card" style="border-left-color: #4e54c8;">
                <div class="kpi-info"><h3>📝 รายงานทั้งหมด</h3><p style="color:#4e54c8;"><?php echo number_format($total_reports); ?> ฉบับ</p></div>
                <i class="fas fa-file-alt kpi-icon" style="color:#4e54c8;"></i>
            </div>

            <?php 
                $i = 0;
                foreach($status_stats as $status_name => $count) {
                    if(isset($color_map[$status_name])) {
                        $c = $color_map[$status_name];
                    } else {
                        $c = $default_colors[$i % count($default_colors)];
                        $i++;
                    }
            ?>
                <div class="kpi-card" style="border-left-color: <?php echo $c['border']; ?>;">
                    <div class="kpi-info">
                        <h3><?php echo $status_name; ?></h3>
                        <p style="color: <?php echo $c['border']; ?>;"><?php echo number_format($count); ?> งาน</p>
                    </div>
                    <i class="fas <?php echo $c['icon']; ?> kpi-icon" style="color: <?php echo $c['border']; ?>;"></i>
                </div>
            <?php } ?>

            <div class="kpi-card" style="border-left-color: #e74c3c;">
                <div class="kpi-info"><h3>💸 ค่าใช้จ่ายรวม</h3><p style="color:#e74c3c;"><?php echo number_format($total_expense); ?> บ.</p></div>
                <i class="fas fa-wallet kpi-icon" style="color:#e74c3c;"></i>
            </div>
        </div>

        <div class="table-container">
            <h3>📋 รายละเอียดการปฏิบัติงาน</h3>
            <input type="text" id="searchInput" onkeyup="searchTable()" placeholder="🔍 ค้นหาในตาราง..." style="padding:10px; width:250px; border:1px solid #ddd; border-radius:5px; margin-bottom:15px;">
            
            <table id="reportTable">
                <thead>
                    <tr>
                        <th>วันที่</th><th>พนักงาน</th><th>โครงการ</th><th>ลูกค้า/งาน</th><th>สถานะ</th><th>ค่าใช้จ่าย</th><th>หลักฐาน</th><th>ดูเพิ่ม</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result_list->num_rows > 0): while($row = $result_list->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo date('d/m/Y', strtotime($row['report_date'])); ?></td>
                            <td>
                                <b><?php echo $row['reporter_name']; ?></b><br>
                                <small style="color:#888;"><?php echo ($row['gps']=='Office') ? '🏢 ออฟฟิศ' : '🚗 นอกสถานที่'; ?></small>
                            </td>
                            <td style="color:var(--primary); font-weight:bold;"><?php echo $row['project_name']; ?></td>
                            <td><?php echo $row['work_result']; ?></td>
                            <td>
                                <?php 
                                    $s = $row['job_status'];
                                    $bg_color = isset($color_map[$s]) ? $color_map[$s]['border'] : '#95a5a6';
                                    echo "<span class='status-badge' style='background:$bg_color'>$s</span>";
                                ?>
                            </td>
                            <td style="color:#e74c3c; font-weight:bold;"><?php echo ($row['total_expense']>0) ? number_format($row['total_expense']) : '-'; ?></td>
                            <td>
                                <?php if($row['fuel_receipt']) echo "<a href='uploads/{$row['fuel_receipt']}' target='_blank' class='btn-view'>⛽</a>"; ?>
                                <?php if($row['accommodation_receipt']) echo "<a href='uploads/{$row['accommodation_receipt']}' target='_blank' class='btn-view'>🏨</a>"; ?>
                                <?php if($row['other_receipt']) echo "<a href='uploads/{$row['other_receipt']}' target='_blank' class='btn-view'>🧩</a>"; ?>
                            </td>
                            <td>
                                <button onclick="showModal(
                                    '<?php echo js_safe($row['work_result']); ?>',
                                    '<?php echo js_safe($row['project_name']); ?>',
                                    '<?php echo js_safe($row['problem']); ?>',
                                    '<?php echo js_safe($row['suggestion']); ?>',
                                    '<?php echo js_safe($row['additional_notes']); ?>'
                                )" style="border:none; background:none; cursor:pointer; font-size:18px; color:#4e54c8;"><i class="fas fa-eye"></i></button>
                            </td>
                        </tr>
                    <?php endwhile; else: ?>
                        <tr><td colspan="8" style="text-align:center; padding:20px; color:#999;">ไม่พบข้อมูล</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="detailModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="document.getElementById('detailModal').style.display='none'">&times;</span>
            <h3 style="margin-top:0; color:#4e54c8;">📄 รายละเอียดเพิ่มเติม</h3>
            <p><b>🏢 หน่วยงาน:</b> <span id="m-client"></span></p>
            <p><b>📂 โครงการ:</b> <span id="m-project"></span></p>
            <hr style="border:0; border-top:1px dashed #ddd; margin:10px 0;">
            <p style="color:#e74c3c;"><b>⚠️ ปัญหา:</b> <span id="m-problem"></span></p>
            <p style="color:#3498db;"><b>💡 ข้อเสนอแนะ:</b> <span id="m-suggestion"></span></p>
            <p><b>📝 บันทึก:</b> <br><span id="m-notes"></span></p>
        </div>
    </div>

    <script>
        function showModal(client, project, problem, suggestion, notes) {
            document.getElementById('m-client').innerText = client || '-';
            document.getElementById('m-project').innerText = project || '-';
            document.getElementById('m-problem').innerText = problem || '-';
            document.getElementById('m-suggestion').innerText = suggestion || '-';
            document.getElementById('m-notes').innerHTML = (notes || '-').replace(/\\n/g, '<br>');
            document.getElementById('detailModal').style.display = 'block';
        }
        window.onclick = function(e) { if(e.target == document.getElementById('detailModal')) { document.getElementById('detailModal').style.display = 'none'; } }
        function searchTable() {
            var input = document.getElementById("searchInput");
            var filter = input.value.toUpperCase();
            var table = document.getElementById("reportTable");
            var tr = table.getElementsByTagName("tr");
            for (var i = 1; i < tr.length; i++) {
                var txtValue = tr[i].textContent || tr[i].innerText;
                if (txtValue.toUpperCase().indexOf(filter) > -1) { tr[i].style.display = ""; } 
                else { tr[i].style.display = "none"; }
            }
        }
    </script>
</body>
</html>
<?php $conn->close(); ?>