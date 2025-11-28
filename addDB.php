<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 1. DB 연결 정보
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "team006";

// 2. DB 연결
// mysqli_report를 사용하여 에러를 예외(Exception)로 받겠다고 설정 (try-catch 사용 위함)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($host, $user, $pass, $dbname);
    $conn->set_charset("utf8mb4");
} catch (mysqli_sql_exception $e) {
    die("DB 연결 실패: " . $e->getMessage());
}

echo "<h1>데이터베이스 구조 변경 (1:N RDBMS)</h1>";

// 3. 쿼리 1: 'users' 테이블 정리 (열 삭제) - try-catch 적용
$sql_alter = "ALTER TABLE users
              DROP COLUMN region_name,
              DROP COLUMN region_nx,
              DROP COLUMN region_ny";

echo "<h3>1. 'users' 테이블 정리 (열 삭제) 시도...</h3>";

try {
    // 쿼리 실행 시도
    $conn->query($sql_alter);
    echo "<p style='color:green;'>✅ 'users' 테이블에서 3개 열(region_name 등) 삭제 성공!</p>";

} catch (mysqli_sql_exception $e) {
    // 오류가 발생했을 때 이곳으로 옵니다.
    
    // 오류 코드가 1091번(Can't DROP... check that it exists)이면 무시하고 진행
    if ($e->getCode() == 1091) {
        echo "<p style='color:orange;'>⚠️ 경고: 삭제하려는 열이 이미 없습니다. (이미 정리된 상태입니다.)</p>";
        echo "<p>➡️ 다음 단계로 계속 진행합니다.</p>";
    } else {
        // 1091번 이외의 다른 오류라면 출력하고 멈춤
        echo "<p style='color:red;'>❌ 오류 발생: " . $e->getMessage() . "</p>";
    }
}

// 4. 'user_regions' 새 테이블 생성 - try-catch 적용
$sql_create = "CREATE TABLE user_regions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_uid VARCHAR(50) NOT NULL,
    region_name VARCHAR(50) NOT NULL,
    region_nx INT NOT NULL,
    region_ny INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    stnId VARCHAR(16) NULL,
    
    FOREIGN KEY (user_uid) REFERENCES users(uid)
        ON DELETE CASCADE
        ON UPDATE CASCADE
)";

echo "<h3>2. 'user_regions' 새 테이블 생성 시도...</h3>";

try {
    $conn->query($sql_create);
    echo "<p style='color:green;'>✅ 'user_regions' 테이블 생성 성공!</p>";

} catch (mysqli_sql_exception $e) {
    // 오류 코드가 1050번(Table already exists)이면 무시
    if ($e->getCode() == 1050) {
        echo "<p style='color:orange;'>⚠️ 경고: 'user_regions' 테이블이 이미 존재합니다.</p>";
    } else {
        echo "<p style='color:red;'>❌ 테이블 생성 오류: " . $e->getMessage() . "</p>";
    }
}

/* ==========================================================
    3) user_regions에 stnId 컬럼이 없다면 ADD COLUMN
   ========================================================== */
echo "<h3>3. 'user_regions'에 stnId 컬럼 추가 확인...</h3>";

try {
    $result = $conn->query("SHOW COLUMNS FROM user_regions LIKE 'stnId'");
    if ($result->num_rows === 0) {
        // 컬럼 없음 → 추가
        $conn->query("ALTER TABLE user_regions ADD COLUMN stnId VARCHAR(16) NULL");
        echo "<p style='color:green;'>✅ stnId 컬럼 추가 완료!</p>";
    } else {
        echo "<p style='color:blue;'>ℹ️ stnId 컬럼 이미 존재함 → 다음 단계</p>";
    }
} catch (mysqli_sql_exception $e) {
    echo "<p style='color:red;'>❌ stnId 컬럼 체크 오류: " . $e->getMessage() . "</p>";
}

/* ==========================================================
    4) stnId 컬럼이 NOT NULL 상태라면 NULL 허용으로 수정
   ========================================================== */
echo "<h3>4. 'stnId' NULL 허용 업데이트...</h3>";

try {
    $conn->query("ALTER TABLE user_regions MODIFY stnId VARCHAR(16) NULL");
    echo "<p style='color:green;'>✅ stnId NULL 허용으로 업데이트 완료!</p>";
} catch (mysqli_sql_exception $e) {
    echo "<p style='color:red;'>❌ stnId NULL 허용 업데이트 오류: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<h2>🎉 구조 변경 작업 완료</h2>";
echo "<p>이제 <a href='dashboard.php'>대시보드</a>로 이동하여 확인하세요.</p>";

$conn->close();
?>