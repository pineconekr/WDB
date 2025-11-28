<?php
// 1. 세션 시작 및 로그인 확인
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: auth.html");
    exit;
}
$user_id = $_SESSION['user_id'];

// 알림창/이동 함수 (공용)
function alert_redirect($message, $url) {
    header('Content-Type: text/html; charset=utf-8');
    echo "<script>
        alert('" . htmlspecialchars($message, ENT_QUOTES) . "');
        location.href = '" . $url . "';
    </script>";
    exit;
}
function alert_back($message) {
    header('Content-Type: text/html; charset=utf-8');
    echo "<script>
        alert('" . htmlspecialchars($message, ENT_QUOTES) . "');
        history.back();
    </script>";
    exit;
}

// 2. 폼 데이터 받기
if (!isset($_POST['step1']) || empty($_POST['step1'])) {
    alert_back("시/도를 선택해주세요.");
}
if (!isset($_POST['step2']) || empty($_POST['step2'])) {
    alert_back("시/군/구를 선택해주세요.");
}
if (!isset($_POST['step3']) || empty($_POST['step3'])) {
    alert_back("동/읍/면을 선택해주세요.");
}

$step1 = $_POST['step1'];
$step2 = $_POST['step2'];
$step3 = $_POST['step3'];

// 3. 지역명 생성
$region_name = trim("$step1 $step2 $step3");

// 4. DB 연결
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "team006";
$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    alert_back("DB 연결에 실패했습니다: " . $conn->connect_errno);
}
$conn->set_charset("utf8mb4");

// 5. 중복 저장 방지
$stmt = $conn->prepare("SELECT id FROM user_regions WHERE user_uid = ? AND region_name = ?");
$stmt->bind_param("ss", $user_id, $region_name);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    $stmt->close();
    alert_back("'" . htmlspecialchars($region_name) . "' 지역은 이미 선호 지역에 추가되어 있습니다.");
}
$stmt->close();

// 6. grid_x, grid_y 조회 (region_dictionary)
$stmt = $conn->prepare("SELECT grid_x, grid_y FROM region_dictionary WHERE step1 = ? AND step2 = ? AND step3 = ? LIMIT 1");
$stmt->bind_param("sss", $step1, $step2, $step3);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

if (!$row) {
    alert_back("선택한 지역의 날씨 좌표 정보를 찾을 수 없습니다.");
}

$region_nx = (int)$row['grid_x'];
$region_ny = (int)$row['grid_y'];

// 7. DB에 새 지역 "추가"
$stmt = $conn->prepare("INSERT INTO user_regions (user_uid, region_name, region_nx, region_ny) VALUES (?, ?, ?, ?)");
$stmt->bind_param("ssii", $user_id, $region_name, $region_nx, $region_ny);

if ($stmt->execute()) {
    alert_redirect($region_name . " 지역이 추가되었습니다.", "dashboard.php");
} else {
    alert_back("지역 추가 중 오류가 발생했습니다: " . $stmt->error);
}

$stmt->close();
$conn->close();
?>
