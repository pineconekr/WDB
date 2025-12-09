<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$host = "localhost";
$user = "root";
$pass = "";
$dbname = "team006";


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

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    alert_back("DB 연결에 실패했습니다. 관리자에게 문의하세요. (Error: " . $conn->connect_errno . ")");
}
$conn->set_charset("utf8mb4");

$uid = $_POST['uid'] ?? "";
$pwd = $_POST['pwd'] ?? "";
$pwd_confirm = $_POST['pwd_confirm'] ?? "";

if (empty($uid) || empty($pwd) || empty($pwd_confirm)) {
    alert_back("아이디와 비밀번호, 비밀번호 확인 필드는 필수입니다.");
}

//비밀번호 최소 길이 검사
if (strlen($pwd) < 8) {
    alert_back("비밀번호는 8자 이상이어야 합니다.");
}

// 비밀번호 일치 확인
if ($pwd !== $pwd_confirm) {
    alert_back("비밀번호가 서로 일치하지 않습니다.");
}

// 사용자 중복 확인
$stmt = $conn->prepare("SELECT uid FROM users WHERE uid = ?");
$stmt->bind_param("s", $uid);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    alert_back("이미 존재하는 아이디입니다. 다른 아이디를 사용해주세요.");
}
$stmt->close();

// 비밀번호 암호화
$hashed_pwd = password_hash($pwd, PASSWORD_DEFAULT);

// DB에 회원정보 저장
$stmt = $conn->prepare("INSERT INTO users (uid, pwd) VALUES (?, ?)");
$stmt->bind_param("ss", $uid, $hashed_pwd);

if ($stmt->execute()) {
    alert_redirect("회원가입이 완료되었습니다. 로그인 해주세요.", "auth.html#tab-login");
} else {
    alert_back("회원가입 중 오류가 발생했습니다. (Error: " . $stmt->error . ")");
}

$stmt->close();
$conn->close();
?>
