<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 1. DB 연결 정보
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "team006";

// 2. DB 연결
$conn = new mysqli($host, $user, $pass, $dbname);

// 연결 오류 체크
if ($conn->connect_error) {
    die("DB 연결 실패 (team006 데이터베이스가 존재하지 않는 것 같습니다. setup_db.php를 먼저 실행했나요?): " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

echo "<h1>데이터베이스 구조 변경 (1:N RDBMS)</h1>";

// 3. 쿼리 1: 'users' 테이블 정리 (열 삭제)
$sql_alter = "ALTER TABLE users
              DROP COLUMN region_name,
              DROP COLUMN region_nx,
              DROP COLUMN region_ny";

echo "<h3>1. 'users' 테이블 정리 (열 삭제) 시도...</h3>";

// if ($conn->query($sql_alter) === TRUE) {
//     echo "<p style='color:green;'>✅ 'users' 테이블에서 3개 열(region_name 등) 삭제 성공!</p>";
// } else {
//     // [중요] 1091 오류는 "이미 없다"는 뜻이므로, 오류가 아닙니다.
//     if ($conn->errno == 1091) {
//         echo "<p style='color:orange;'> 경고: 'users' 테이블에 해당 열이 이미 없습니다. (오류 메시지: " . htmlspecialchars($conn->error) . ")</p>";
//         echo "<p> 이미 정리되었으므로, 다음 단계로 정상 진행합니다.</p>";
//     } else {
//         // 그 외 다른 심각한 오류
//         echo "<p style='color:red;'> 'users' 테이블 정리 실패: " . htmlspecialchars($conn->error) . "</p>";
//     }
// }

// 'user_regions' 새 테이블 생성
$sql_create = "CREATE TABLE user_regions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_uid VARCHAR(50) NOT NULL,
    region_name VARCHAR(50) NOT NULL,
    region_nx INT NOT NULL,
    region_ny INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_uid) REFERENCES users(uid)
        ON DELETE CASCADE
        ON UPDATE CASCADE
)";

echo "<h3>2. 'user_regions' 새 테이블 생성 시도...</h3>";
if ($conn->query($sql_create) === TRUE) {
    echo "<p style='color:green;'>✅ 'user_regions' 테이블 생성 성공!</p>";
} else {
    // 1050 오류는 "테이블이 이미 존재한다"는 뜻
    if ($conn->errno == 1050) {
        echo "<p style='color:orange;'> 경고: 'user_regions' 테이블이 이미 존재합니다. (오류 메시지: " . htmlspecialchars($conn->error) . ")</p>";
    } else {
        echo "<p style='color:red;'> 'user_regions' 테이블 생성 실패: " . htmlspecialchars($conn->error) . "</p>";
    }
}

echo "<hr>";
echo "<h2> 구조 변경이 완료되었습니다.</h2>";
echo "<p>이제 <a href='dashboard.php'>대시보드</a>로 이동하여 1:N RDBMS 기능을 테스트하세요.</p>";
echo "<p style='color:red; font-weight:bold;'>[중요] 이 'update_db_v2.php' 파일은 보안을 위해 지금 바로 삭제해주세요!</p>";

$conn->close();
?>