<?php

header('Content-Type: application/json; charset=utf-8');

$servername = "localhost"; $username = "root"; $password = ""; $dbname = "tjc_db";

$conn = new mysqli($servername, $username, $password, $dbname);

$conn->set_charset("utf8");



$action = isset($_GET['action']) ? $_GET['action'] : '';



// 1. ดึงจังหวัด (ตามภาค)

if ($action == 'get_provinces') {

    $region = isset($_GET['region']) ? $_GET['region'] : '';

    $sql = "SELECT name_th FROM master_provinces WHERE region_name = ? ORDER BY name_th ASC";

    $stmt = $conn->prepare($sql);

    $stmt->bind_param("s", $region);

    $stmt->execute();

    $result = $stmt->get_result();

    $data = [];

    while($row = $result->fetch_assoc()) { $data[] = $row['name_th']; }

    echo json_encode($data);

}



// 2. ดึงสถานะงาน (NEW)

else if ($action == 'get_job_status') {

    $sql = "SELECT status_name FROM master_job_status ORDER BY id ASC";

    $result = $conn->query($sql);

    $data = [];

    while($row = $result->fetch_assoc()) { $data[] = $row['status_name']; }

    echo json_encode($data);

}



// 3. ดึงกิจกรรม (NEW)

else if ($action == 'get_activities') {

    $sql = "SELECT activity_name FROM master_activities ORDER BY id ASC";

    $result = $conn->query($sql);

    $data = [];

    while($row = $result->fetch_assoc()) { $data[] = $row['activity_name']; }

    echo json_encode($data);

}



$conn->close();

?>