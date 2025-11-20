<?php
//로그인 상태 유지를 위해 세션을 가장 먼저 시작
session_start();

ini_set('display_errors', 1);
error_reporting(E_ALL);
// DB 연결 정보
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "team006";

// 알림창/이동 함수 (signup.php와 동일)
function alert_back($message) {
    header('Content-Type: text/html; charset=utf-8');
    echo "<script>
        alert('" . htmlspecialchars($message, ENT_QUOTES) . "');
        history.back();
    </script>";
    exit;
}

function alert_redirect($message, $url) {
    header('Content-Type: text/html; charset=utf-8');
    echo "<script>
        alert('" . htmlspecialchars($message, ENT_QUOTES) . "');
        location.href = '" . $url . "';
    </script>";
    exit;
}

// DB 연결
$conn = new mysqli($host, $user, $pass, $dbname);

// 연결 오류 체크
if ($conn->connect_error) {
    alert_back("DB 연결에 실패했습니다. 관리자에게 문의하세요. (Error: " . $conn->connect_errno . ")");
}
$conn->set_charset("utf8mb4");

// POST로 전달된 값 받기
$uid = $_POST['uid'] ?? "";
$pwd = $_POST['pwd'] ?? "";

// 빈 값 검사
if (empty($uid) || empty($pwd)) {
    alert_back("아이디와 비밀번호를 모두 입력해주세요.");
}

// 아이디 존재 여부 확인
$stmt = $conn->prepare("SELECT pwd FROM users WHERE uid = ?");
$stmt->bind_param("s", $uid);
$stmt->execute();
$stmt->store_result();

// 아이디 없음
if ($stmt->num_rows === 0) {
    alert_back("존재하지 않는 아이디입니다.");
}

// DB에 저장된 암호화된 비밀번호를 $hashed_pwd 변수에 바인딩
$stmt->bind_result($hashed_pwd); 
$stmt->fetch();
$stmt->close();

// 비밀번호 검증 (암호화 방식)
// password_verify() 함수가 (입력값, DB의 암호화된 값)을 비교
if (password_verify($pwd, $hashed_pwd)) {
    // 성공 시: 세션에 사용자 ID 기록
    $_SESSION['user_id'] = $uid;
    alert_redirect($uid . "님, 환영합니다!", "dashboard.php");
} else {
    // 실패 시
    alert_back("비밀번호가 올바르지 않습니다.");
}

$conn->close();
?>