<?php
//로그인 상태 유지를 위해 세션을 가장 먼저 시작
session_start();

ini_set('display_errors', 1);
error_reporting(E_ALL);
// DB 연결 정보
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "wdb";
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

// 빈 값  검사
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

/*
// DB에 저장된 비밀번호를 $db_pwd 변수에 바인딩
$stmt->bind_result($db_pwd);
$stmt->fetch();
$stmt->close();


// 비밀번호 검증
if ($pwd === $db_pwd) {
    $_SESSION['user_id'] = $uid;
    alert_redirect($uid . "님, 환영합니다!", "dashboard.html");
} else {
    alert_back("비밀번호가 올바르지 않습니다.");
}
*/

$stmt->bind_result($hashed_pwd);
$stmt->fetch();

// 비밀번호 검증
if (password_verify($pwd, $hashed_pwd)) {
    $_SESSION['user_id'] = $uid;
    alert_redirect($uid . "님, 환영합니다!", "dashboard.php");
} else {
    alert_back("비밀번호가 올바르지 않습니다.");
}


$conn->close();
?>
