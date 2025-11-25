<?php
// [중요] 브라우저 캐시 방지 헤더 (가장 중요!)
// 이 설정이 있어야 '뒤로 가기'나 '새로고침' 시 옛날 데이터가 아닌 최신 데이터를 가져옵니다.
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

session_start();

// [중요] 지역이 많으면 API 호출 시간이 길어지므로 PHP 실행 시간 제한을 풉니다.
set_time_limit(0);

if (!isset($_SESSION['user_id'])) {
    exit("로그인이 필요합니다.");
}

date_default_timezone_set('Asia/Seoul');
$user_id = $_SESSION['user_id'];

// 1. DB 연결
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "team006";
$conn = new mysqli($host, $user, $pass, $dbname);
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    exit("DB 연결 실패");
}

// 2. 사용자의 저장된 지역 목록 가져오기
$sql = "SELECT id, region_name, region_nx, region_ny FROM user_regions WHERE user_uid = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$regions = [];
while ($row = $result->fetch_assoc()) {
    $regions[] = $row;
}
$stmt->close();
$conn->close();

// 저장된 지역이 없으면 안내 메시지 출력
if (empty($regions)) {
    echo '<div style="padding:20px; text-align:center; color:#666;">
            <p>📉 저장된 관심 지역이 없습니다.</p>
            <p>좌측 메뉴에서 <strong>지역을 2개 이상 추가</strong>해보세요!</p>
          </div>';
    exit;
}

// 3. 날씨 API 호출 및 기온 수집 함수
function getTempForRegion($nx, $ny) {
    $serviceKey = "bbc2f96d627a4f50f836e44d783c2cb40633431aae9315876336c6bd9afd8432";
    $endpoint = "https://apis.data.go.kr/1360000/VilageFcstInfoService_2.0/getVilageFcst";

    // 현재 시간 기준으로 가장 최신 Base_time 계산
    $now = new DateTime('now', new DateTimeZone('Asia/Seoul'));
    $currentTime = $now->format('Hi');
    $baseDate = $now->format('Ymd');
    $baseTime = '2300';

    // 단기예보 API 제공 시간
    $baseTimesMap = [
        '0210' => '0200', '0510' => '0500', '0810' => '0800', '1110' => '1100',
        '1410' => '1400', '1710' => '1700', '2010' => '2000', '2310' => '2300'
    ];

    foreach ($baseTimesMap as $threshold => $base) {
        if ($currentTime >= $threshold) {
            $baseTime = $base;
        }
    }
    if ($currentTime < '0210') {
        $baseDate = (clone $now)->modify('-1 day')->format('Ymd');
    }

    // [중요] 기온 데이터를 확실히 잡기 위해 numOfRows를 60으로 설정
    $params = [
        'ServiceKey' => $serviceKey, 'dataType' => 'JSON',
        'base_date' => $baseDate, 'base_time' => $baseTime,
        'nx' => $nx, 'ny' => $ny,
        'pageNo' => 1, 'numOfRows' => 60 
    ];

    $ch = curl_init($endpoint . '?' . http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5); // 각 요청당 5초 타임아웃
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    $items = $data['response']['body']['items']['item'] ?? [];

    // TMP(1시간 기온) 찾기
    foreach ($items as $item) {
        if ($item['category'] === 'TMP') {
            return (float)$item['fcstValue']; 
        }
    }
    return null; // 기온 데이터 없음
}

// 4. 각 지역별 기온 수집
$ranking_data = [];
foreach ($regions as $region) {
    $temp = getTempForRegion($region['region_nx'], $region['region_ny']);
    
    if ($temp !== null) {
        $ranking_data[] = [
            'name' => $region['region_name'],
            'temp' => $temp,
            'status' => 'ok'
        ];
    } else {
        // API 호출 실패하거나 데이터가 없는 경우
        $ranking_data[] = [
            'name' => $region['region_name'],
            'temp' => -999, // 정렬 시 맨 뒤로 보내기 위함
            'status' => 'error'
        ];
    }
}

// 5. 기온 높은 순으로 정렬 (내림차순)
usort($ranking_data, function($a, $b) {
    return $a['temp'] <=> $b['temp'];
});

// 6. 결과 HTML 출력
?>
<style>
    .ranking-list { list-style: none; padding: 0; margin: 0; }
    .ranking-item {
        display: flex; align-items: center; justify-content: space-between;
        padding: 15px; margin-bottom: 10px;
        background: #fff; border: 1px solid #e0e0e0; border-radius: 12px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.03);
    }
    .rank-badge {
        width: 30px; height: 30px; border-radius: 50%;
        background: #eee; color: #555;
        display: flex; align-items: center; justify-content: center;
        font-weight: bold; margin-right: 12px;
    }
    /* 메달 색상 */
    .rank-1 { background: #FFD700; color: #fff; } 
    .rank-2 { background: #C0C0C0; color: #fff; } 
    .rank-3 { background: #CD7F32; color: #fff; } 
    
    .region-info { flex: 1; font-size: 1.1rem; font-weight: 500; }
    .temp-info { font-size: 1.3rem; font-weight: bold; color: #333; }
    
    /* 온도별 색상 */
    .hot { color: #e74c3c; }
    .cold { color: #3498db; }
    .error-text { font-size: 0.9rem; color: #999; font-weight: normal; }
</style>

<div style="margin-bottom: 15px; font-size: 0.9rem; color: #666;">
    * 저장된 관심 지역 <?php echo count($regions); ?>곳을 비교합니다.
</div>

<ul class="ranking-list">
    <?php foreach ($ranking_data as $index => $data): ?>
        <?php 
            $rank = $index + 1;
            $badgeClass = ($rank <= 3) ? "rank-$rank" : "";
            
            // 데이터 상태에 따른 표시
            if ($data['status'] === 'ok') {
                $tempClass = ($data['temp'] >= 20) ? 'hot' : (($data['temp'] <= 10) ? 'cold' : '');
                $tempText = $data['temp'] . "°C";
            } else {
                $badgeClass = ""; // 에러면 뱃지 색 제거
                $tempClass = "error-text";
                $tempText = "데이터 없음";
            }
        ?>
        <li class="ranking-item">
            <div style="display:flex; align-items:center;">
                <span class="rank-badge <?php echo $badgeClass; ?>"><?php echo $rank; ?></span>
                <span class="region-info"><?php echo htmlspecialchars($data['name']); ?></span>
            </div>
            <span class="temp-info <?php echo $tempClass; ?>">
                <?php echo $tempText; ?>
            </span>
        </li>
    <?php endforeach; ?>
</ul>