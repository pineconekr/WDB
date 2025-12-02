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
    echo '
        <section class="weather-card ranking-panel ranking-empty-state">
            <h2 class="card-title">지역별 기온 랭킹</h2>
            <p>📉 저장된 관심 지역이 없습니다.</p>
            <p>좌측 메뉴에서 <strong>지역을 2개 이상 추가</strong>해보세요!</p>
        </section>
    ';
    exit;
}

// 3. 단순 기온/날씨 정보 수집용 헬퍼 (초급 개발자 수준으로 단순화)
function fetchRegionSnapshot($nx, $ny) {
    $serviceKey = "bbc2f96d627a4f50f836e44d783c2cb40633431aae9315876336c6bd9afd8432";
    $endpoint = "https://apis.data.go.kr/1360000/VilageFcstInfoService_2.0/getVilageFcst";

    $now = new DateTime('now', new DateTimeZone('Asia/Seoul'));
    $currentTime = $now->format('Hi');
    $baseDate = $now->format('Ymd');
    $baseTime = '2300';

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

    $params = [
        'ServiceKey' => $serviceKey,
        'dataType' => 'JSON',
        'base_date' => $baseDate,
        'base_time' => $baseTime,
        'nx' => $nx,
        'ny' => $ny,
        'pageNo' => 1,
        'numOfRows' => 80
    ];

    $ch = curl_init($endpoint . '?' . http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    $items = $data['response']['body']['items']['item'] ?? [];

    if (empty($items)) {
        return null;
    }

    foreach ($items as $item) {
        if ($item['category'] === 'TMP') {
            return [
                'temp' => (float) $item['fcstValue'],
                'fcstDate' => $item['fcstDate'],
                'fcstTime' => $item['fcstTime']
            ];
        }
    }

    return null;
}

function formatTemp($value) {
    if ($value === null || $value === '' || $value === '--') {
        return '--';
    }
    $precision = abs($value) >= 10 ? 0 : 1;
    return number_format((float) $value, $precision) . "°C";
}

function formatFcstLabel($date, $time) {
    if (!$date || !$time) {
        return date('Y.m.d H시');
    }
    $formatted = DateTime::createFromFormat('Ymd H', $date . ' ' . substr($time, 0, 2));
    return $formatted ? $formatted->format('Y.m.d H시') : ($date . ' ' . substr($time, 0, 2) . '시');
}

function detectTemperatureState($value) {
    if ($value === null || $value === '' || $value === '--') {
        return 'neutral';
    }
    $temp = (float) $value;
    if ($temp >= 25) {
        return 'is-hot';
    }
    if ($temp <= 0) {
        return 'is-cold';
    }
    return 'neutral';
}

// 4. 각 지역별 데이터 수집 (현재 기온과 상태만)
$ranking_data = [];
$referenceDate = null;
$referenceTime = null;

foreach ($regions as $region) {
    $snapshot = fetchRegionSnapshot($region['region_nx'], $region['region_ny']);

    if ($snapshot !== null) {
        if ($referenceDate === null) {
            $referenceDate = $snapshot['fcstDate'];
            $referenceTime = $snapshot['fcstTime'];
        }

        $ranking_data[] = [
            'name' => $region['region_name'],
            'snapshot' => $snapshot,
            'status' => 'ok'
        ];
    } else {
        $ranking_data[] = [
            'name' => $region['region_name'],
            'status' => 'error'
        ];
    }
}

usort($ranking_data, function ($a, $b) {
    $aTemp = ($a['status'] === 'ok' && isset($a['snapshot']['temp'])) ? $a['snapshot']['temp'] : -999;
    $bTemp = ($b['status'] === 'ok' && isset($b['snapshot']['temp'])) ? $b['snapshot']['temp'] : -999;
    return $bTemp <=> $aTemp;
});

$referenceLabel = formatFcstLabel($referenceDate, $referenceTime);
$updatedLabel = date('H:i');

// 5. 결과 HTML 출력
?>
<section class="weather-card ranking-panel">
    <div class="ranking-title-row">
        <div>
            <h2 class="card-title">지역별 기온 랭킹</h2>
            <p class="ranking-meta">
                <?php echo htmlspecialchars($referenceLabel, ENT_QUOTES, 'UTF-8'); ?> 기준 · 저장 지역 <?php echo count($regions); ?>곳 비교
            </p>
        </div>
        <span class="ranking-updated">업데이트 <?php echo htmlspecialchars($updatedLabel, ENT_QUOTES, 'UTF-8'); ?></span>
    </div>

    <ol class="ranking-list" aria-live="polite">
        <?php foreach ($ranking_data as $index => $data): ?>
            <?php
                $rankClasses = [];
                if ($index === 0) {
                    $rankClasses[] = 'is-first';
                }
                if ($index < 3) {
                    $rankClasses[] = 'is-top-three';
                }
                if ($data['status'] !== 'ok') {
                    $rankClasses[] = 'is-error';
                }
                $classAttr = empty($rankClasses) ? '' : ' ' . implode(' ', $rankClasses);
                $snapshot = $data['status'] === 'ok' ? ($data['snapshot'] ?? null) : null;
                $summaryText = $snapshot ? formatFcstLabel($snapshot['fcstDate'], $snapshot['fcstTime']) : null;
                $temperatureState = $snapshot ? detectTemperatureState($snapshot['temp'] ?? null) : 'neutral';
                $temperatureValue = $snapshot ? formatTemp($snapshot['temp'] ?? null) : '--';
            ?>
            <li class="ranking-card<?php echo $classAttr; ?>">
                <div class="ranking-card-header">
                    <div class="rank-index">
                        <span class="rank-number"><?php echo $index + 1; ?></span>
                        <span class="rank-label">위</span>
                    </div>
                    <div class="rank-region">
                        <span class="region-name"><?php echo htmlspecialchars($data['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php if ($summaryText): ?>
                            <span class="region-summary"><?php echo htmlspecialchars($summaryText, ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php else: ?>
                            <span class="region-summary ranking-error-text">기상 데이터를 불러오지 못했습니다.</span>
                        <?php endif; ?>
                    </div>
                    <div class="rank-temperature <?php echo $temperatureState; ?>">
                        <span class="temperature-value"><?php echo $temperatureValue; ?></span>
                        <span class="temperature-label">현재 기온</span>
                    </div>
                </div>
            </li>
        <?php endforeach; ?>
    </ol>
</section>