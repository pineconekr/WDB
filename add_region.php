<?php
// 1. 세션 시작 및 로그인 확인
session_start();
if (!isset($_SESSION['user_id'])) {
    // 로그인하지 않은 사용자는 auth.html로 쫓아냄
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
// 'region_data' 값이 없거나 비어있으면 뒤로 돌려보냄
if (!isset($_POST['region_data']) || empty($_POST['region_data'])) {
    alert_back("추가할 지역을 선택해주세요.");
}

$region_data = $_POST['region_data']; // (예: "서울/60/127")

// 3. 데이터 분리 ("서울/60/127" -> "서울", 60, 127)
$parts = explode('/', $region_data);

// 데이터 형식이 잘못되었는지 확인 (3부분으로 나뉘지 않았다면 )
if (count($parts) !== 3) {
    alert_back("전달된 지역 데이터 형식이 올바르지 않습니다.");
}

$region_name = $parts[0];
$region_nx = (int)$parts[1]; // 정수로 변환
$region_ny = (int)$parts[2]; // 정수로 변환

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

//  중복 저장 방지
$stmt = $conn->prepare("SELECT id FROM user_regions WHERE user_uid = ? AND region_name = ?");
$stmt->bind_param("ss", $user_id, $region_name);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    // 이미 DB에 이 지역이 존재함
    alert_back("'" . htmlspecialchars($region_name) . "' 지역은 이미 선호 지역에 추가되어 있습니다.");
}
$stmt->close();

// 6. DB에 새 지역 "추가"
$stmt = $conn->prepare("INSERT INTO user_regions (user_uid, region_name, region_nx, region_ny) VALUES (?, ?, ?, ?)");
// s = string (user_id, region_name), i = integer (nx, ny)
$stmt->bind_param("ssii", $user_id, $region_name, $region_nx, $region_ny);

if ($stmt->execute()) {
    // 추가 성공
    alert_redirect($region_name . " 지역이 추가되었습니다.", "dashboard.php");
} else {
    // 추가 실패
    alert_back("지역 추가 중 오류가 발생했습니다: " . $stmt->error);
}

$stmt->close();
$conn->close();
?>