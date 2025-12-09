<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: auth.html");
    exit;
}
$user_id = $_SESSION['user_id'];

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

// 폼 데이터 받기
// 'region_id' 값이 없으면 뒤로 돌려보냄
if (!isset($_POST['region_id']) || empty($_POST['region_id'])) {
    alert_back("삭제할 지역 ID가 없습니다.");
}

$region_id = (int)$_POST['region_id'];

$host = "localhost";
$user = "root";
$pass = "";
$dbname = "team006";
$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    alert_back("DB 연결에 실패했습니다: " . $conn->connect_errno);
}
$conn->set_charset("utf8mb4");

// DB에서 해당 지역 "삭제"
// 1. `id`가 일치하고,
// 2. `user_uid`가 현재 로그인한 사용자의 ID와 일치하는 경우에만 삭제
// (다른 사용자의 지역을 삭제하는 것을 방지하기 위함.)
$stmt = $conn->prepare("DELETE FROM user_regions WHERE id = ? AND user_uid = ?");
$stmt->bind_param("is", $region_id, $user_id);

if ($stmt->execute()) {
    // 삭제 성공
    alert_redirect("선호 지역이 삭제되었습니다.", "dashboard.php");
} else {
    // 삭제 실패
    alert_back("지역 삭제 중 오류가 발생했습니다: " . $stmt->error);
}

$stmt->close();
$conn->close();
?>