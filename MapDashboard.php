<?php
session_start();
require_once 'auth.php';

// 1. ตรวจสอบสิทธิ์
if (!isset($_SESSION['fullname'])) { 
    header("Location: login.php"); 
    exit(); 
}

// ============================================
// 2. เชื่อมต่อฐานข้อมูล (แก้ไขให้รองรับ TiDB SSL)
// ============================================
$servername = "gateway01.ap-southeast-1.prod.aws.tidbcloud.com";
$username = "2zJFS48pitnR2QG.root";
$password = "DF43GROp1tGLs8Gp"; 
$dbname = "tjc_db";
$port = 4000;

// เริ่มต้น mysqli object
$conn = mysqli_init();
if (!$conn) {
    die("Connection failed: mysqli_init() error");
}

// ตั้งค่า SSL (สำคัญมากสำหรับ TiDB)
mysqli_options($conn, MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, true);

// เชื่อมต่อโดยใช้ Real Connect + SSL Flag
$connected = mysqli_real_connect(
    $conn, 
    $servername, 
    $username, 
    $password, 
    $dbname, 
    $port, 
    NULL, 
    MYSQLI_CLIENT_SSL
);

if (!$connected) {
    die("Connection failed: " . mysqli_connect_error());
}

// ตั้งค่าภาษาไทย
$conn->set_charset("utf8");

// ============================================
// ส่วนที่ 1: ตัวกรอง (Filter)
// ============================================
$filter_name = isset($_GET['filter_name']) ? $_GET['filter_name'] : '';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : ''; 
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

$where_sql = "WHERE r.gps != '' AND r.gps != 'Office'";

if (!empty($filter_name)) { $where_sql .= " AND r.reporter_name = '$filter_name'"; }
if (!empty($start_date)) { $where_sql .= " AND r.report_date >= '$start_date'"; }
if (!empty($end_date)) { $where_sql .= " AND r.report_date <= '$end_date'"; }

$sql_users = "SELECT DISTINCT reporter_name FROM reports ORDER BY reporter_name ASC";
$result_users = $conn->query($sql_users);

// ============================================
// ส่วนที่ 2: จัดการสีสถานะ (Dynamic Color)
// ============================================
$sql_master = "SELECT * FROM master_job_status ORDER BY id ASC";
$res_master = $conn->query($sql_master);

$fixed_colors = [
    'ได้งาน' => '#2ecc71',       
    'กำลังติดตาม' => '#f1c40f', 
    'ไม่ได้งาน' => '#e74c3c',    
    'เข้าเสนอโครงการ' => '#3498db' 
];
$palette = ['#9b59b6', '#e67e22', '#1abc9c', '#34495e', '#7f8c8d', '#c0392b'];
$palette_index = 0;
$status_config = [];

if ($res_master) {
    while($row = $res_master->fetch_assoc()) {
        $name = $row['status_name'];
        if (array_key_exists($name, $fixed_colors)) {
            $status_config[$name] = $fixed_colors[$name];
        } else {
            $status_config[$name] = $palette[$palette_index % count($palette)];
            $palette_index++;
        }
    }
}

// ============================================
// ส่วนที่ 3: ดึงข้อมูล Report (เพิ่ม total_expense)
// ============================================
$sql = "SELECT r.id, r.reporter_name, r.project_name, r.work_result, r.job_status, r.gps, r.report_date, r.total_expense,
               u.avatar 
        FROM reports r 
        LEFT JOIN users u ON r.reporter_name = u.fullname 
        $where_sql 
        ORDER BY r.report_date DESC";

$result = $conn->query($sql);

$locations = [];
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $coords = explode(',', $row['gps']);
        if (count($coords) == 2) {
            $row['lat'] = trim($coords[0]);
            $row['lng'] = trim($coords[1]);
            
            // Format ค่าใช้จ่าย
            $row['expense_fmt'] = number_format($row['total_expense']);

            // จัดการรูปโปรไฟล์
            if (!empty($row['avatar']) && file_exists('uploads/profiles/' . $row['avatar'])) {
                $row['avatar_url'] = 'uploads/profiles/' . $row['avatar'];
            } else {
                $row['avatar_url'] = 'https://ui-avatars.com/api/?name='.urlencode($row['reporter_name']).'&background=random&color=fff'; 
            }
            
            $locations[] = $row;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แผนที่ติดตามงาน - TJC</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        body { font-family: 'Sarabun', sans-serif; margin: 0; padding: 0; display: flex; flex-direction: column; height: 100vh; }
        
        .navbar { background: #4e54c8; color: white; padding: 10px 20px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.2); z-index: 1000; }
        .navbar h2 { margin: 0; font-size: 18px; }
        .btn-back { background: rgba(255,255,255,0.2); color: white; text-decoration: none; padding: 6px 12px; border-radius: 20px; font-size: 14px; transition: 0.3s; }
        .btn-back:hover { background: rgba(255,255,255,0.4); }

        .filter-bar { background: #f8f9fa; padding: 10px 20px; border-bottom: 1px solid #ddd; display: flex; gap: 10px; align-items: center; flex-wrap: wrap; z-index: 999; }
        .filter-bar select, .filter-bar input { padding: 8px; border: 1px solid #ccc; border-radius: 5px; font-family: 'Sarabun'; font-size: 14px; }
        .btn-search { background: #4e54c8; color: white; border: none; padding: 8px 15px; border-radius: 5px; cursor: pointer; font-weight: bold; }
        .btn-reset { background: #95a5a6; color: white; text-decoration: none; padding: 8px 15px; border-radius: 5px; font-size: 14px; }

        #map { flex: 1; width: 100%; z-index: 1; }

        .legend { background: white; padding: 15px; position: absolute; bottom: 30px; right: 20px; z-index: 999; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.2); font-size: 14px; min-width: 160px; }
        .legend h4 { margin: 0 0 10px 0; font-size: 15px; color: #333; border-bottom: 2px solid #f0f0f0; padding-bottom: 8px; }
        .legend-item { display: flex; align-items: center; margin-bottom: 8px; }
        .color-dot { width: 12px; height: 12px; border-radius: 50%; margin-right: 10px; display: inline-block; border: 1px solid rgba(0,0,0,0.1); }
        
        /* ✅ ปรับแต่ง Popup ให้สวยงาม */
        .popup-content { font-family: 'Sarabun', sans-serif; min-width: 220px; }
        .popup-header { text-align: center; margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px dashed #eee; }
        .popup-avatar { width: 60px; height: 60px; border-radius: 50%; object-fit: cover; border: 3px solid white; box-shadow: 0 3px 6px rgba(0,0,0,0.2); }
        .popup-row { display: flex; margin-bottom: 5px; font-size: 13px; color: #333; }
        .popup-icon { width: 20px; text-align: center; margin-right: 5px; }
        .popup-label { font-weight: bold; margin-right: 5px; color: #666; }
        .popup-value { flex: 1; font-weight: 500; }
        .badge-status { padding: 4px 10px; border-radius: 20px; color: white; font-size: 12px; font-weight: bold; display: inline-block; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }

        /* Marker Styles */
        .avatar-pin { position: relative; transition: transform 0.2s; }
        .avatar-pin:hover { transform: scale(1.2); z-index: 9999 !important; }
        .avatar-img { width: 45px; height: 45px; border-radius: 50%; object-fit: cover; box-shadow: 0 3px 8px rgba(0,0,0,0.4); background: white; }
        .pin-tip { position: absolute; bottom: -5px; left: 50%; transform: translateX(-50%); width: 0; height: 0; border-left: 6px solid transparent; border-right: 6px solid transparent; border-top: 8px solid white; }
    </style>
</head>
<body>
<?php include 'sidebar.php'; ?>
    <div class="navbar">
        <h2><i class="fas fa-map-marked-alt"></i> แผนที่ติดตามงาน</h2>
    </div>

    <div class="filter-bar">
        <form method="GET" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap; width:100%;">
            <select name="filter_name">
                <option value="">-- พนักงานทั้งหมด --</option>
                <?php 
                if ($result_users && $result_users->num_rows > 0) {
                    while($user = $result_users->fetch_assoc()) {
                        $selected = ($filter_name == $user['reporter_name']) ? 'selected' : '';
                        echo "<option value='".$user['reporter_name']."' $selected>".$user['reporter_name']."</option>";
                    }
                }
                ?>
            </select>
            <input type="date" name="start_date" value="<?php echo $start_date; ?>" placeholder="วันที่เริ่ม">
            <input type="date" name="end_date" value="<?php echo $end_date; ?>" placeholder="ถึงวันที่">
            <button type="submit" class="btn-search"><i class="fas fa-search"></i> ค้นหา</button>
            <a href="MapDashboard.php" class="btn-reset"><i class="fas fa-sync-alt"></i> รีเซ็ต</a>
        </form>
    </div>

    <div id="map"></div>

    <div class="legend">
        <h4>📌 สถานะงาน</h4>
        <?php foreach ($status_config as $status => $color): ?>
            <div class="legend-item">
                <span class="color-dot" style="background-color: <?php echo $color; ?>;"></span>
                <?php echo $status; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        const locations = <?php echo json_encode($locations); ?>;
        const statusColors = <?php echo json_encode($status_config); ?>;
        
        var map = L.map('map').setView([13.7563, 100.5018], 6);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        function createAvatarIcon(imgUrl, color) {
            return L.divIcon({
                className: 'avatar-pin',
                html: `
                    <div style="position:relative;">
                        <img src="${imgUrl}" class="avatar-img" style="border: 3px solid ${color};">
                        <div class="pin-tip" style="border-top-color: ${color};"></div>
                    </div>
                `,
                iconSize: [50, 50],
                iconAnchor: [25, 55],
                popupAnchor: [0, -50]
            });
        }

        var bounds = [];
        locations.forEach(loc => {
            var color = statusColors[loc.job_status] || '#95a5a6';
            var marker = L.marker([loc.lat, loc.lng], { 
                icon: createAvatarIcon(loc.avatar_url, color) 
            }).addTo(map);
            
            // ✅ Popup HTML Updated: แสดงข้อมูลครบ 6 อย่าง
            var popupHtml = `
                <div class="popup-content">
                    <div class="popup-header">
                        <img src="${loc.avatar_url}" class="popup-avatar" style="border-color:${color}">
                        <div style="margin-top:5px; font-weight:bold; font-size:16px;">${loc.reporter_name}</div>
                    </div>
                    
                    <div class="popup-row">
                        <span class="popup-icon">📂</span> 
                        <span class="popup-label">โครงการ:</span> 
                        <span class="popup-value" style="color:#4e54c8;">${loc.project_name ? loc.project_name : '-'}</span>
                    </div>
                    
                    <div class="popup-row">
                        <span class="popup-icon">🏢</span> 
                        <span class="popup-label">หน่วยงาน:</span> 
                        <span class="popup-value">${loc.work_result}</span>
                    </div>

                    <div class="popup-row">
                        <span class="popup-icon">📅</span> 
                        <span class="popup-label">วันที่:</span> 
                        <span class="popup-value">${new Date(loc.report_date).toLocaleDateString('th-TH')}</span>
                    </div>

                    <div class="popup-row">
                        <span class="popup-icon">💸</span> 
                        <span class="popup-label">ค่าใช้จ่าย:</span> 
                        <span class="popup-value" style="color:#e74c3c;">${loc.expense_fmt} บ.</span>
                    </div>

                    <div style="text-align:center; margin-top:10px;">
                        <span class="badge-status" style="background:${color}">${loc.job_status}</span>
                    </div>
                </div>
            `;
            
            marker.bindPopup(popupHtml);
            bounds.push([loc.lat, loc.lng]);
        });

        if (bounds.length > 0) {
            map.fitBounds(bounds, { padding: [50, 50] });
        }
    </script>
</body>
</html>