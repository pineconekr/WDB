<?php
// DB 연결 정보
$host = "localhost";
$user = "root";
$pass = "DB비밀번호";
$dbname = "wdb";

// DB 연결
$conn = new mysqli($host, $user, $pass, $dbname);

// 연결 오류 체크
if ($conn->connect_error) {
    die("DB 연결 실패: " . $conn->connect_error);
}

// POST로 전달된 값 받기
$uid = $_POST['uid'] ?? "";
$pwd = $_POST['pwd'] ?? "";
$pwd_confirm = $_POST['pwd_confirm'] ?? "";

// 비밀번호 일치 확인
if ($pwd !== $pwd_confirm) {
    die("비밀번호가 서로 일치하지 않습니다.");
}

// 사용자 중복 확인
$stmt = $conn->prepare("SELECT uid FROM users WHERE uid = ?");
$stmt->bind_param("s", $uid);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    die("이미 존재하는 아이디입니다.");
}
$stmt->close();

// 비밀번호 암호화 (bcrypt)
$hashed_pwd = password_hash($pwd, PASSWORD_BCRYPT);

// DB에 회원정보 저장
$stmt = $conn->prepare("INSERT INTO users (uid, pwd) VALUES (?, ?)");
$stmt->bind_param("ss", $uid, $hashed_pwd);

if ($stmt->execute()) {
    echo "회원가입이 정상적으로 완료되었습니다.<br>";
    echo "<a href='auth.html'>로그인 하러 가기</a>";
} else {
    echo "회원가입 중 오류가 발생했습니다.";
}

$stmt->close();
$conn->close();
?>
