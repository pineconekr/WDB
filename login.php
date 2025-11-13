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

// 아이디 존재 여부 확인
$stmt = $conn->prepare("SELECT pwd FROM users WHERE uid = ?");
$stmt->bind_param("s", $uid);
$stmt->execute();
$stmt->store_result();

// 아이디 없음
if ($stmt->num_rows === 0) {
    die("존재하지 않는 아이디입니다.");
}

$stmt->bind_result($hashed_pwd);
$stmt->fetch();

// 비밀번호 검증
if (password_verify($pwd, $hashed_pwd)) {
    echo "로그인 성공!<br>";
    echo "<a href='dashboard.html'>대시보드로 이동</a>";
} else {
    echo "비밀번호가 올바르지 않습니다.";
}

$stmt->close();
$conn->close();
?>
