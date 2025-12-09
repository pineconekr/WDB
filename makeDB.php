<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

//DB 연결
$host = "localhost";
$user = "root";
$pass = "";

//DB 서버에 연결 
$conn = new mysqli($host, $user, $pass);

//연결 오류 체크
if ($conn->connect_error) {
    die("DB 서버 연결 실패: " . $conn->connect_error);
}

//한글 깨짐 방지
$conn->set_charset("utf8mb4");

echo "<h1>데이터베이스 설정 시작...</h1>";

//'team006' 데이터베이스 생성
$sql_create_db = "CREATE DATABASE team006
                  DEFAULT CHARACTER SET utf8mb4
                  COLLATE utf8mb4_unicode_ci";

if ($conn->query($sql_create_db) === TRUE) {
    echo "<p> 'team006' 데이터베이스 생성 성공 </p>";
} else {
    echo "<p> 'team006' 데이터베이스 생성 실패 (또는 이미 존재함): " . $conn->error . "</p>";
}

//'team006'데이터베이스 선택
$conn->select_db("team006");

//'users' 테이블 생성
$sql_create_table = "CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uid VARCHAR(50) NOT NULL UNIQUE,
    pwd VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql_create_table) === TRUE) {
    echo "<p> 'users' 테이블 생성 성공</p>";
} else {
    echo "<p> 'users' 테이블 생성 실패 (또는 이미 존재함): " . $conn->error . "</p>";
}

echo "<hr>";
echo "<h2> 모든 설정이 완료되었습니다.</h2>";
echo "<p><a href='auth.html'>회원가입 페이지</a>로 이동하세요.</p>";

$conn->close();

?>
