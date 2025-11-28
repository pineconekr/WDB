<?php
header('Content-Type: application/json; charset=utf-8');

$host = "localhost";
$user = "root";
$pass = "";
$dbname = "team006";

$conn = new mysqli($host, $user, $pass, $dbname);
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    echo json_encode(['error' => 'DB 연결 실패']);
    exit;
}

$type = isset($_GET['type']) ? $_GET['type'] : '';
$step1 = isset($_GET['step1']) ? $_GET['step1'] : '';
$step2 = isset($_GET['step2']) ? $_GET['step2'] : '';

$data = [];

if ($type === 'step1') {
    // 시/도 목록 조회
    $sql = "SELECT DISTINCT step1 FROM region_dictionary WHERE step1 IS NOT NULL AND step1 != '' ORDER BY step1";
    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) {
        $data[] = $row['step1'];
    }
} elseif ($type === 'step2' && $step1) {
    // 시/군/구 목록 조회
    $stmt = $conn->prepare("SELECT DISTINCT step2 FROM region_dictionary WHERE step1 = ? AND step2 IS NOT NULL AND step2 != '' ORDER BY step2");
    $stmt->bind_param("s", $step1);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $data[] = $row['step2'];
    }
    $stmt->close();
} elseif ($type === 'step3' && $step1 && $step2) {
    // 동/읍/면 목록 조회
    $stmt = $conn->prepare("SELECT DISTINCT step3 FROM region_dictionary WHERE step1 = ? AND step2 = ? AND step3 IS NOT NULL AND step3 != '' ORDER BY step3");
    $stmt->bind_param("ss", $step1, $step2);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $data[] = $row['step3'];
    }
    $stmt->close();
}

echo json_encode($data);

$conn->close();
?>

