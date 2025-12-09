<?php
session_start();

// 만약 세션이 없으면 auth.html로 이동
if (!isset($_SESSION['user_id'])) {
  header("Location: auth.html");
  exit;
}

// RecommendClothes.php 파일 로드
require_once 'RecommendClothes.php';

// 기본 시간 설정
date_default_timezone_set('Asia/Seoul');

$user_id = $_SESSION['user_id'];

// (최신 날씨 정보를 항상 가져오도록 설정하기 위해 브라우저가 캐시를 사용하지 않도록 설정)
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

$host = "localhost";
$user = "root";
$pass = "";
$dbname = "team006";
$conn = new mysqli($host, $user, $pass, $dbname);
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
  die("DB 연결 실패: " . $conn->connect_error);
}

$saved_regions = fetchSavedRegions($conn, $user_id);
$requested_region_id = isset($_GET['region_id']) ? (int) $_GET['region_id'] : null;

// 지역 미설정 시 기본 메시지
$main_region_name = "먼저 지역을 설정해주세요.";
$current_weather_info = "먼저 지역을 설정해주세요.";
$current_weather_detail = null;
$google_chart_data_json = 'null';
$profile_region_text = "--";
$active_region_id = null;
$outfit_message = '<blockquote class="outfit-quote">저장된 지역이 없습니다. \'내 정보\'에서 지역을 설정해주세요.</blockquote>';
$wind_chill_data = [
  'applicable' => false,
  'value' => null,
  'message' => '지역을 먼저 설정해주세요.',
  'temp_c' => null,
  'wind_kmh' => null
];


if (!empty($saved_regions)) {
  $main_region = null;
  if ($requested_region_id !== null) {
    foreach ($saved_regions as $region) {
      if ((int) $region['id'] === $requested_region_id) {
        $main_region = $region;
        break;
      }
    }
  }

  if ($main_region === null) {
    $main_region = $saved_regions[0];
  }

  $active_region_id = (int) $main_region['id'];
  $main_region_name = $main_region['region_name'];
  $profile_region_text = $main_region_name;

  $nx = (int) $main_region['region_nx'];
  $ny = (int) $main_region['region_ny'];

  $weatherPayload = fetchWeatherData($nx, $ny);

  $google_chart_data_json = $weatherPayload['chart_json'];
  $current_weather_info = $weatherPayload['current_info'];
  $current_weather_detail = $weatherPayload['current_detail'];

  // 실시간 날씨 데이터 가져오기 (파라미터: nx, ny 좌표)
  $realTimeData = fetchRealTimeWeather($nx, $ny);
  
  // 값 덮어쓰기 (실시간 날씨 데이터가 있으면 실시간으로 한번 더 덮어쓰기)
  if ($realTimeData !== null) {
        $current_weather_detail['temperature'] = $realTimeData['T1H'];
        $current_weather_detail['reh'] = $realTimeData['REH'];
        $current_weather_detail['wsd'] = $realTimeData['WSD'];
        
        $temp = $realTimeData['T1H'];
        $pty = $realTimeData['PTY'];
        $weatherText = '맑음'; 

        if ($pty != '0') {
            $ptyMap = ['1'=>'비', '2'=>'비/눈', '3'=>'눈', '4'=>'소나기', '5'=>'빗방울', '6'=>'빗방울/눈날림', '7'=>'눈날림'];
            $weatherText = $ptyMap[$pty] ?? '강수';
        } else {
             $weatherText = '맑음';
        }
        $current_weather_info = "현재: {$temp}℃ / {$weatherText}";
    }

    if (isset($current_weather_detail['temperature'])) {
        if (function_exists('getClothingRecommendation')) {
            $recText = getClothingRecommendation((float) $current_weather_detail['temperature']);
            $outfit_message = '<blockquote class="outfit-quote">' . htmlspecialchars($recText, ENT_QUOTES, 'UTF-8') . '</blockquote>';
        }
    }

    $wind_chill_data = calculateWindChillData(
        $current_weather_detail['temperature'] ?? null,
        $current_weather_detail['wsd'] ?? null
    );

}

$stnId = $main_region['stnId'];   // ★ 지역별 기상특보용 코드
$weather_warnings_html = fetchWeatherWarningsByRegion($stnId);

$conn->close();


function fetchSavedRegions($conn, $userId) {
    $stmt = $conn->prepare("SELECT id, region_name, region_nx, region_ny, stnId FROM user_regions WHERE user_uid = ?");
    $stmt->bind_param("s", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $regions = [];
    while ($row = $result->fetch_assoc()) {
        $regions[] = $row;
    }
    $stmt->close();
    return $regions;
}

function findRegionById($conn, $regionId, $userId) {
    $stmt = $conn->prepare("SELECT id, region_name, region_nx, region_ny FROM user_regions WHERE id = ? AND user_uid = ?");
    $stmt->bind_param("is", $regionId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $region = $result->fetch_assoc();
    $stmt->close();
    return $region ?: null;
}

function fetchRealTimeWeather($nx, $ny) {
    $serviceKey = "bbc2f96d627a4f50f836e44d783c2cb40633431aae9315876336c6bd9afd8432";
    $endpoint = "https://apis.data.go.kr/1360000/VilageFcstInfoService_2.0/getUltraSrtNcst";

    $now = new DateTime('now', new DateTimeZone('Asia/Seoul'));
    // 매시각 10분 이후 호출 
    if ((int)$now->format('i') < 10) {
        $now->modify('-1 hour');
    }
    $base_date = $now->format('Ymd');
    $base_time = $now->format('H') . '00';

    $params = [
        'ServiceKey' => $serviceKey, 'dataType' => 'JSON',
        'base_date' => $base_date, 'base_time' => $base_time,
        'nx' => $nx, 'ny' => $ny,
        'pageNo' => 1, 'numOfRows' => 10
    ];

    $ch = curl_init($endpoint . '?' . http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    if (!isset($data['response']['header']['resultCode']) || $data['response']['header']['resultCode'] !== '00') {
        return null;
    }

    $items = $data['response']['body']['items']['item'] ?? [];
    $result = [];
    foreach ($items as $item) {
        $result[$item['category']] = $item['obsrValue'];
    }
    return $result;
}

function fetchWeatherData($nx, $ny) {
    $serviceKey = "bbc2f96d627a4f50f836e44d783c2cb40633431aae9315876336c6bd9afd8432";
    $endpoint = "https://apis.data.go.kr/1360000/VilageFcstInfoService_2.0/getVilageFcst";

    list($base_date, $base_time) = resolveBaseDateTime();

    $params = [
        'ServiceKey' => $serviceKey, 'dataType' => 'JSON',
        'base_date' => $base_date, 'base_time' => $base_time,
        'nx' => $nx, 'ny' => $ny,
        'pageNo' => 1, 'numOfRows' => 300
    ];

    $ch = curl_init($endpoint . '?' . http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    curl_close($ch);

    if ($response === false) {
        return ['chart_json' => 'null', 'current_info' => "API 호출 실패", 'current_detail' => null];
    }

    $jsonData = json_decode($response, true);
    if (!isset($jsonData['response']['header']['resultCode']) || $jsonData['response']['header']['resultCode'] !== '00') {
        $msg = $jsonData['response']['header']['resultMsg'] ?? 'Error';
        return ['chart_json' => 'null', 'current_info' => "API 오류: $msg", 'current_detail' => null];
    }

    $items = $jsonData['response']['body']['items']['item'] ?? [];
    
    list($chartRows, $currentInfo, $currentDetails) = transformWeatherItems($items);

    $chartJson = json_encode($chartRows, JSON_UNESCAPED_UNICODE);
    if ($chartJson === false) $chartJson = 'null';

    return [
        'chart_json' => $chartJson,
        'current_info' => $currentInfo,
        'current_detail' => $currentDetails
    ];
}

function transformWeatherItems($items) {
    $weatherData = [];
    $currentHourStr = date('H') . '00';

    foreach ($items as $item) {
        if ((int)$item['fcstTime'] < (int)$currentHourStr && $item['fcstDate'] == date('Ymd')) continue;
        if ($item['fcstDate'] < date('Ymd')) continue;

        $cat = $item['category'];
        if (in_array($cat, ['TMP', 'POP', 'REH', 'WSD', 'SKY', 'PTY'])) {
            $weatherData[$item['fcstTime']][$cat] = $item['fcstValue'];
        }
    }
    ksort($weatherData);

    if (empty($weatherData)) {
        return [[], "데이터 없음", null];
    }

    $chartRows = [['시간', '기온(℃)', '강수확률(%)', '습도(%)']];
    $currentInfo = "";
    $currentDetails = null;
    $count = 0;

    foreach ($weatherData as $time => $cats) {
        $tmp = isset($cats['TMP']) ? (float)$cats['TMP'] : 0;
        $pop = isset($cats['POP']) ? (int)$cats['POP'] : 0;
        $reh = isset($cats['REH']) ? (int)$cats['REH'] : 0;

        if ($count === 0) {
            $currentInfo = buildCurrentWeatherText($cats);
            $currentDetails = buildCurrentDetail($cats, $time);
        }

        $chartRows[] = [
            substr($time, 0, 2), // [수정] '시' 글자 제거 (숫자만)
            $tmp,
            $pop,
            $reh
        ];
        if (++$count >= 24) break;
    }
    return [$chartRows, $currentInfo, $currentDetails];
}

function buildCurrentWeatherText($categories) {
    $temp = $categories['TMP'] ?? '?';
    $sky = $categories['SKY'] ?? '1';
    $pty = $categories['PTY'] ?? '0';
    $wText = '맑음';
    if ($pty != '0') {
        $ptyMap = ['1'=>'비', '2'=>'비/눈', '3'=>'눈', '4'=>'소나기'];
        $wText = $ptyMap[$pty] ?? '강수';
    } else {
        if ($sky == '3') $wText = '구름많음'; elseif ($sky == '4') $wText = '흐림';
    }
    return "예보: {$temp}℃ / {$wText}";
}

function buildCurrentDetail($categories, $time) {
    return [
        'time' => $time,
        'temperature' => isset($categories['TMP']) ? (float)$categories['TMP'] : null,
        'pop' => isset($categories['POP']) ? (int)$categories['POP'] : null,
        'reh' => isset($categories['REH']) ? (int)$categories['REH'] : null,
        'wsd' => isset($categories['WSD']) ? (float)$categories['WSD'] : null
    ];
}

function calculateWindChillData($tempC, $windSpeedMs) {
    $temp = is_numeric($tempC) ? (float)$tempC : null;
    $windMs = is_numeric($windSpeedMs) ? (float)$windSpeedMs : null;
    $windKmh = $windMs !== null ? $windMs * 3.6 : null;

    if ($temp === null || $windMs === null) {
        return [
            'applicable' => false,
            'value' => null,
            'message' => '체감추위 계산에 필요한 기온/풍속 데이터를 불러오지 못했습니다.',
            'temp_c' => $temp,
            'wind_kmh' => $windKmh
        ];
    }

    $windKmhRounded = round($windKmh, 1);

    if ($temp > 10 || $windKmh < 4.8) {
        $reasons = [];
        if ($temp > 10) $reasons[] = '기온이 10℃ 초과';
        if ($windKmh < 4.8) $reasons[] = '풍속이 4.8 km/h 미만';
        $reasonText = empty($reasons) ? '조건 미충족' : implode(', ', $reasons);

        return [
            'applicable' => false,
            'value' => null,
            'message' => "{$reasonText}. 기온 ≤ 10℃ · 풍속 ≥ 4.8 km/h일 때만 공식이 적용됩니다.",
            'temp_c' => $temp,
            'wind_kmh' => $windKmhRounded
        ];
    }

    $windPower = pow($windKmh, 0.16);
    $wc = 13.12 + 0.6215 * $temp - 11.37 * $windPower + 0.3965 * $temp * $windPower;

    return [
        'applicable' => true,
        'value' => round($wc, 1),
        'message' => '국제 표준식으로 계산된 체감추위입니다.',
        'temp_c' => $temp,
        'wind_kmh' => $windKmhRounded
    ];
}

function formatWeatherMetric($val, $unit, $dec=0) {
    if (!is_numeric($val)) return "--";
    return number_format((float)$val, $dec) . $unit;
}

function resolveBaseDateTime() {
    $timezone = new DateTimeZone('Asia/Seoul');
    $now = new DateTimeImmutable('now', $timezone);
    $currentTime = $now->format('Hi');
    $baseDate = $now->format('Ymd');
    $baseTime = '2300';

    $baseTimesMap = ['0210'=>'0200', 
                      '0510'=>'0500', 
                      '0810'=>'0800', 
                      '1110'=>'1100', 
                      '1410'=>'1400', 
                      '1710'=>'1700', 
                      '2010'=>'2000', 
                      '2310'=>'2300'];

    foreach ($baseTimesMap as $threshold => $base) {
        if ($currentTime >= $threshold) {
            $baseTime = $base;
        }
    }
    if ($currentTime < '0210') {
        $baseDate = $now->modify('-1 day')->format('Ymd');
    }
    return [$baseDate, $baseTime];
}

function fetchWeatherWarningsByRegion($stnId) {
    $serviceKey = "36123b4603a13e885bebb2f5b9ee40654bdeb918a36ff63f00060d57a98fcfb6";

    $toTmFc = date("Ymd");
    $fromTmFc = date("Ymd", strtotime("-5 days"));

    $url = "http://apis.data.go.kr/1360000/WthrWrnInfoService/getWthrWrnMsg";
    $url .= "?serviceKey={$serviceKey}";
    $url .= "&numOfRows=50&pageNo=1";
    $url .= "&dataType=XML";
    $url .= "&stnId={$stnId}";
    $url .= "&fromTmFc={$fromTmFc}";
    $url .= "&toTmFc={$toTmFc}";

    $response = file_get_contents($url);
    if ($response === FALSE) return "<p>⚠️ 기상특보 API 요청 실패</p>";

    $xml = simplexml_load_string($response);
    if (!$xml) return "<p>⚠️ 기상특보 XML 파싱 실패</p>";

    if (!isset($xml->body->items->item)) {
        return "<p>📭 현재 발효 중인 기상특보가 없습니다.</p>";
    }

    $items = $xml->body->items->item;
    $html = "";

    // 첫 번째 특보
    $first = $items[0];
    $html .= "
        <div class='warning-item'>
            <strong>📢 {$first->t1}</strong><br>
            발표시각: " . formatTmFc($first->t5) . "<br>
            내용: " . nl2br($first->t2) . "<br>
            해제예고: " . nl2br($first->t4) . "<br>
            세부 위치: " . nl2br($first->t6) . "<br>
            예비특보: " . nl2br($first->t7) . "
        </div>
    ";

    // 숨김 영역
    $html .= "<div id='warning-more' style='display:none; margin-top:10px;'>";

    for ($i = 1; $i < count($items); $i++) {
        $item = $items[$i];

        $html .= "
            <div class='warning-item'>
                <strong>📢 {$item->t1}</strong><br>
                발표시각: " . formatTmFc($item->t5) . "<br>
                내용: " . nl2br($item->t2) . "<br>
                해제예고: " . nl2br($item->t4) . "<br>
                세부 위치: " . nl2br($item->t6) . "<br>
                예비특보: " . nl2br($item->t7) . "
                <hr>
            </div>
        ";
    }

    $html .= "</div>";

    // 토글 버튼
    $html .= "
        <button id='warning-toggle' 
                style='margin-top:10px; background:none; border:none; color:#007BFF; cursor:pointer;'>
            ▼ 더보기
        </button>

        <script>
            const btn = document.getElementById('warning-toggle');
            const box = document.getElementById('warning-more');
            let open = false;

            btn.addEventListener('click', () => {
                open = !open;
                box.style.display = open ? 'block' : 'none';
                btn.textContent = open ? '▲ 접기' : '▼ 더보기';
            });
        </script>
    ";

    return $html;
}



function formatTmFc($tmFc) {
    // 원본 예: 202501231400
    $year = substr($tmFc, 0, 4);
    $month = substr($tmFc, 4, 2);
    $day = substr($tmFc, 6, 2);
    $hour = substr($tmFc, 8, 2);
    $min = substr($tmFc, 10, 2);

    return "{$year}-{$month}-{$day} / {$hour}:{$min}";
}

?>


<!DOCTYPE html>
<html lang="ko">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>WDB 대시보드</title>

  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />

  <!-- 기본 스타일 -->
  <link rel="stylesheet" href="./dashboard.css?v=<?php echo time(); ?>" />
  
  <!-- 다크모드 초기화 -->
  <script>
    (function() {
        const savedTheme = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-theme', savedTheme);
    })();
    window.addEventListener('pageshow', function(event) {
        if (event.persisted) window.location.reload();
    });
  </script>

  <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
  <script type="text/javascript">
    const chartData = <?php echo $google_chart_data_json; ?>;

    google.charts.load('current', { 'packages': ['corechart'] });
    google.charts.setOnLoadCallback(() => drawChart(chartData));

    function drawChart(sourceData) {
      const chartDiv = document.getElementById('weather-chart');
      if (!chartDiv) return;

      if (!Array.isArray(sourceData) || sourceData.length <= 1) {
        chartDiv.innerHTML = "<p style='color:var(--text-secondary);'>표시할 날씨 데이터가 없습니다.</p>";
        return;
      }

      const doc = document.documentElement;
      const isDarkMode = doc.getAttribute('data-theme') === 'dark';

      const chartColors = isDarkMode ? {
          bg: '#000000',    
          text: '#ffffff',  
          grid: '#333333',  
          line1: '#e74c3c', 
          line2: '#ffffff', // [핵심] 습도 점선: 흰색
          bars: '#95a5a6'
      } : {
          bg: '#ffffff',
          text: '#333333',
          grid: '#e0e0e0',
          line1: '#e74c3c',
          line2: '#3498db',
          bars: '#95a5a6'
      };

      const data = google.visualization.arrayToDataTable(sourceData);
      const options = {
        title: '시간별 상세 예보 (24시간)',
        backgroundColor: chartColors.bg,
        titleTextStyle: { color: chartColors.text },
        legend: { position: 'bottom', textStyle: { color: chartColors.text } },
        hAxis: { textStyle: { color: chartColors.text } },
        vAxes: {
          0: { title: '기온(℃) / 습도(%)', textStyle: { color: chartColors.text }, titleTextStyle: { color: chartColors.text } },
          1: { title: '강수확률(%)', textStyle: { color: chartColors.text }, titleTextStyle: { color: chartColors.text }, gridlines: { color: 'transparent' }, minValue: 0, maxValue: 100 }
        },
        seriesType: 'line',
        series: {
          0: { type: 'line', color: chartColors.line1, targetAxisIndex: 0 },
          1: { type: 'bars', color: chartColors.bars, targetAxisIndex: 1 },
          2: { type: 'line', color: chartColors.line2, targetAxisIndex: 0, lineDashStyle: [4, 4] }
        },
        chartArea: { width: '80%', height: '70%' },
        gridlines: { color: chartColors.grid }
      };

      const chart = new google.visualization.ComboChart(chartDiv);
      chart.draw(data, options);
    }

    function updateDigitalClock() {
      const now = new Date();

      const hours = String(now.getHours()).padStart(2, '0');
      const minutes = String(now.getMinutes()).padStart(2, '0');
      const timeString = `${hours}:${minutes}`;

      const month = now.getMonth() + 1;
      const date = now.getDate();
      const days = ['일', '월', '화', '수', '목', '금', '토'];
      const dayName = days[now.getDay()];
      const dateString = `${month}월 ${date}일 (${dayName})`;

      // DOM 업데이트
      const timeEl = document.getElementById('clock-time');
      const dateEl = document.getElementById('clock-date');

      if (timeEl) timeEl.textContent = timeString;
      if (dateEl) dateEl.textContent = dateString;
    }

    // 페이지 로드 시 즉시 실행 및 1초마다 갱신
    document.addEventListener('DOMContentLoaded', () => {
      updateDigitalClock();
      setInterval(updateDigitalClock, 1000);
    });
  </script>
</head>

<body>
  <!-- 상단 고정 네비게이션 바 -->
  <nav class="top-navbar">
    <div class="top-navbar-container">
      <div class="top-nav-left">
        <div class="top-nav-brand">
          <a href="#" id="top-nav-home" class="top-nav-logo" title="홈으로">
            <i class="fas fa-home"></i>
            <span class="logo-text">WDB</span>
          </a>
        </div>
        <div class="top-nav-regions">
          <span class="top-nav-label">선호 지역</span>
          <?php if (empty($saved_regions)): ?>
            <span class="top-nav-region-text">지역 없음</span>
          <?php else: ?>
            <select id="top-nav-region-select" class="top-nav-region-select" aria-label="선호 지역을 선택하세요">
              <?php foreach ($saved_regions as $region): ?>
                <?php
                $regionId = (int) $region['id'];
                $isActive = $active_region_id === $regionId;
                ?>
                <option value="<?php echo $regionId; ?>" <?php echo $isActive ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($region['region_name'], ENT_QUOTES, 'UTF-8'); ?>
                </option>
              <?php endforeach; ?>
            </select>
          <?php endif; ?>
        </div>
      </div>
      
      <div class="top-nav-center">
      </div>
      
      <div class="top-nav-right">
        <div class="top-nav-quick">
          <a href="#" class="top-nav-item" data-page="profile" title="내 정보">
            <i class="fas fa-user"></i>
            <span class="nav-text">내 정보</span>
          </a>
          <a href="#" class="top-nav-item" data-page="ranking" title="날씨 랭킹">
            <i class="fas fa-chart-line"></i>
            <span class="nav-text">날씨 랭킹</span>
          </a>
        </div>
        <div class="top-nav-controls">
          <div class="top-nav-external-links">
            <div class="top-nav-links-dropdown">
              <button class="top-nav-links-btn" title="외부 링크">
                <i class="fas fa-external-link-alt"></i>
                <span class="nav-text">외부링크</span>
              </button>
              <div class="top-nav-links-menu">
                <a href="https://www.weather.go.kr" target="_blank" class="top-nav-link-item" title="기상청">
                  <i class="fas fa-cloud-sun"></i>
                  <span>기상청</span>
                </a>
                <a href="https://www.airkorea.or.kr" target="_blank" class="top-nav-link-item" title="에어코리아">
                  <i class="fas fa-wind"></i>
                  <span>대기질</span>
                </a>
                <a href="https://map.naver.com" target="_blank" class="top-nav-link-item" title="지도">
                  <i class="fas fa-map-marked-alt"></i>
                  <span>지도</span>
                </a>
              </div>
            </div>
          </div>
          <button id="theme-toggle-btn" class="theme-toggle-btn" title="테마 변경">
            <i id="theme-icon" class="fas fa-moon"></i>
          </button>
          <button class="top-nav-circle-btn" id="profileBtn" title="내 정보 조회">
            <i class="fas fa-user-circle"></i>
          </button>
          <a href="logout.php" class="top-nav-item top-nav-logout" title="로그아웃">
            <i class="fas fa-sign-out-alt"></i>
            <span class="nav-text">로그아웃</span>
          </a>
        </div>
      </div>
    </div>
  </nav>

  <div class="dashboard-layout">
    <aside class="sidebar">
      <section class="sidebar-card sidebar-profile simple-card">
        <p class="sidebar-label">안녕하세요</p>
        <p class="sidebar-user"><?php echo htmlspecialchars($user_id, ENT_QUOTES, 'UTF-8'); ?>님</p>
        <div class="sidebar-clock">
          <span id="clock-time" class="clock-time">--:--</span>
          <span id="clock-date" class="clock-date">--월 --일 (-)</span>
        </div>
      </section>

      <section class="sidebar-card sidebar-region simple-card">
        <p class="sidebar-label">현재 지역</p>
        <p class="sidebar-region-name"><?php echo htmlspecialchars($main_region_name, ENT_QUOTES, 'UTF-8'); ?></p>
        <p class="sidebar-region-info"><?php echo htmlspecialchars($current_weather_info, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php if (!empty($saved_regions)): ?>
          <button type="button" class="sidebar-cta" id="sidebarManageRegions">지역 관리</button>
        <?php endif; ?>
      </section>

      <section class="sidebar-card sidebar-windchill simple-card" aria-live="polite">
        <p class="sidebar-label">WIND CHILL (체감추위)</p>
        <div class="windchill-value-row">
          <?php if ($wind_chill_data['applicable'] && $wind_chill_data['value'] !== null): ?>
            <span class="windchill-value"><?php echo number_format((float)$wind_chill_data['value'], 1); ?></span>
            <span class="windchill-unit">°C</span>
          <!-- // 만약 풍속이 4.8 km/h 미만이면 계산 불가 -->
            <?php else: ?>
            <span class="windchill-placeholder">계산 불가</span>
          <?php endif; ?>
        </div>
        <p class="windchill-mini">
          현재 온도: <?php echo formatWeatherMetric($wind_chill_data['temp_c'], '°C', 1); ?>
        </p>
        <p class="windchill-mini">
          현재 풍속: <?php echo formatWeatherMetric($wind_chill_data['wind_kmh'], ' km/h', 1); ?>
        </p>
      </section>
    </aside>

    <main class="main-content">
      <div class="page-content active" id="page-dashboard">
        <header class="content-header">
          <h1>대시보드</h1>
        </header>

        <div class="content-body">
          <section class="weather-card">
            <h2 class="card-title">
              <?php echo htmlspecialchars(($active_region_id !== null) ? $main_region_name . ' 현재 날씨' : '지역 미설정', ENT_QUOTES, 'UTF-8'); ?>
            </h2>
            <div class="weather-info">
              <div class="weather-main">
                <div class="temperature" id="currentTemperature">
                  <?php echo formatWeatherMetric($current_weather_detail['temperature'] ?? null, '°C', 0); ?>
                </div>
                <div class="location" id="currentLocation">
                  <?php
                  $locationText = ($active_region_id !== null) ? $main_region_name : '지역을 설정해주세요';
                  echo htmlspecialchars($locationText, ENT_QUOTES, 'UTF-8');
                  ?>
                </div>
              </div>
              <div class="weather-details">
                <div class="detail-item">
                  <i class="fa-solid fa-droplet" style="color: #00518fff;"></i>
                  <span class="detail-label">강수확률</span>
                  <span class="detail-value"
                    id="currentPop"><?php echo formatWeatherMetric($current_weather_detail['pop'] ?? null, '%', 0); ?></span>
                </div>
                <div class="detail-item">
                  <i class="fa-solid fa-percent" style="color: #00518fff;"></i>
                  <span class="detail-label">습도</span>
                  <span class="detail-value"
                    id="currentReh"><?php echo formatWeatherMetric($current_weather_detail['reh'] ?? null, '%', 0); ?></span>
                </div>
                <div class="detail-item">
                  <i class="fa-solid fa-wind" style="color: #00518fff;"></i>
                  <span class="detail-label">풍속</span>
                  <span class="detail-value"
                    id="currentWind"><?php echo formatWeatherMetric($current_weather_detail['wsd'] ?? null, 'm/s', 1); ?></span>
                </div>
              </div>
            </div>
          </section>

          <section class="weather-card chart-card">
            <h2 class="card-title">날씨 차트</h2>
            <div id="weather-chart" class="chart-container">
              <p>차트 데이터를 불러오는 중...</p>
            </div>
          </section>

          <section class="weather-card">
            <h2 class="card-title">기상특보</h2>
            <div class="weather-warning">
              <?php
              echo $weather_warnings_html;
              ?>
            </div>
          </section>

          <section class="weather-card">
            <h2 class="card-title outfit-title">오늘의 옷차림</h2>
            <?php
            echo $outfit_message;
            ?>
          </section>
        </div>
      </div>

      <div class="page-content" id="page-ranking">
        <header class="content-header">
          <h1>날씨 랭킹</h1>
        </header>
        <div class="content-body">
          <section class="weather-card ranking-panel">
            <div class="ranking-title-row">
              <div>
                <h2 class="card-title">지역별 기온 랭킹</h2>
                <p class="ranking-meta">랭킹 데이터를 준비하고 있습니다...</p>
              </div>
            </div>
          </section>
        </div>
      </div>

      <div class="page-content" id="page-profile">
        <header class="content-header">
          <h1>내 정보</h1>
        </header>

        <div class="content-body">
          <section class="weather-card">
            <h2 class="card-title">계정 정보</h2>
            <div class="profile-info">
              <div class="info-item">
                <span class="info-label">아이디</span>
                <span class="info-value"
                  id="profileUid"><?php echo htmlspecialchars($user_id, ENT_QUOTES, 'UTF-8'); ?></span>
              </div>
              <div class="info-item">
                <span class="info-label">설정 지역</span>
                <span class="info-value"
                  id="profileRegion"><?php echo htmlspecialchars($profile_region_text, ENT_QUOTES, 'UTF-8'); ?></span>
              </div>
            </div>
          </section>

          <section class="weather-card" id="regionSettingSection">
            <h2 class="card-title">선호지역 추가 및 관리</h2>
            
            <div class="region-management">
              <h3>나의 선호 지역</h3>
              <?php if (empty($saved_regions)): ?>
                <p class="empty-region">아직 저장된 선호 지역이 없습니다.</p>
              <?php else: ?>
                <ul class="region-list-profile">
                  <?php foreach ($saved_regions as $region): ?>
                    <?php
                    $regionId = (int) $region['id'];
                    $isActive = $active_region_id === $regionId;
                    ?>
                    <li data-region-id="<?php echo $regionId; ?>" class="<?php echo $isActive ? 'active' : ''; ?>">
                      <span class="region-name"><?php echo htmlspecialchars($region['region_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                      <div class="region-actions">
                        <form class="set-region-form" method="GET">
                          <input type="hidden" name="region_id" value="<?php echo $regionId; ?>">
                          <button type="submit" class="set-region-btn<?php echo $isActive ? ' active' : ''; ?>" aria-label="선택 지역 변경">
                            보기
                          </button>
                        </form>
                        <form class="delete-form" action="delete_region.php" method="POST">
                          <input type="hidden" name="region_id" value="<?php echo $regionId; ?>">
                          <button type="submit" class="delete-btn">삭제</button>
                        </form>
                      </div>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
            </div>

            <div class="region-setting">
              <h3>새 선호 지역 추가</h3>
              <form class="region-selector" action="add_region.php" method="POST">
                <div class="field">
                  <label for="region-step1-profile">시/도</label>
                  <select id="region-step1-profile" name="step1" required>
                    <option value="">시/도 선택</option>
                  </select>
                </div>
                
                <div class="field">
                  <label for="region-step2-profile">시/군/구</label>
                  <select id="region-step2-profile" name="step2" required disabled>
                    <option value="">시/군/구 선택</option>
                  </select>
                </div>
                
                <div class="field">
                  <label for="region-step3-profile">동/읍/면</label>
                  <select id="region-step3-profile" name="step3" required disabled>
                    <option value="">동/읍/면 선택</option>
                  </select>
                </div>

                <button class="primary" type="submit">추가하기</button>
              </form>
            </div>
          </section>
        </div>
      </div>
    </main>
  </div>

  <script>
    const themeToggleBtn = document.getElementById('theme-toggle-btn');
    const themeIcon = document.getElementById('theme-icon');

    const currentTheme = localStorage.getItem('theme') || 'light';
    const themeIconElement = document.getElementById('theme-icon');
    if (themeIconElement) {
      if (currentTheme === 'dark') {
        themeIconElement.className = 'fas fa-sun';
      } else {
        themeIconElement.className = 'fas fa-moon';
      }
    }

    if (themeToggleBtn) {
        themeToggleBtn.addEventListener('click', () => {
            const doc = document.documentElement;
            const isDark = doc.getAttribute('data-theme') === 'dark';
            const themeIconElement = document.getElementById('theme-icon');
            
            if (isDark) {
                doc.setAttribute('data-theme', 'light');
                localStorage.setItem('theme', 'light');
                if (themeIconElement) themeIconElement.className = 'fas fa-moon';
            } else {
                doc.setAttribute('data-theme', 'dark');
                localStorage.setItem('theme', 'dark');
                if (themeIconElement) themeIconElement.className = 'fas fa-sun';
            }
            if (typeof drawChart === 'function' && typeof chartData !== 'undefined') {
                drawChart(chartData);
            }
        });
    }

    // 페이지 전환 함수
    function switchPage(pageName) {
      document.querySelectorAll('.page-content').forEach(page => page.classList.remove('active'));
      const selectedPage = document.getElementById(`page-${pageName}`);
      if (selectedPage) {
        selectedPage.classList.add('active');
        window.scrollTo({ top: 0, behavior: 'smooth' });
      }
    }

    // 상단 네비게이션 활성화 함수
    function setActiveTopNav(pageName) {
      document.querySelectorAll('.top-nav-item').forEach(item => item.classList.remove('active'));
      if (pageName) {
        document.querySelector(`.top-nav-item[data-page="${pageName}"]`)?.classList.add('active');
      }
    }

    const profileBtn = document.getElementById('profileBtn');
    if (profileBtn) {
      profileBtn.addEventListener('click', function () {
        switchPage('profile');
        setActiveTopNav('profile');
      });
    }

    const sidebarManageBtn = document.getElementById('sidebarManageRegions');
    if (sidebarManageBtn) {
      sidebarManageBtn.addEventListener('click', function () {
        switchPage('profile');
        setActiveTopNav('profile');
        setTimeout(() => {
          document.getElementById('regionSettingSection')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 100);
      });
    }


    // 상단 네비게이션 홈 버튼
    const topNavHome = document.getElementById('top-nav-home');
    if (topNavHome) {
        topNavHome.addEventListener('click', function(e) {
            e.preventDefault();
            switchPage('dashboard');
            setActiveTopNav(null);
        });
    }

    // 상단 네비게이션 지역 선택 변경
    const topNavRegionSelect = document.getElementById('top-nav-region-select');
    if (topNavRegionSelect) {
        topNavRegionSelect.addEventListener('change', function() {
            const regionId = this.value;
            if (regionId) {
                window.location.href = '?region_id=' + regionId;
            }
        });
    }

    // 상단 네비게이션 외부 링크 드롭다운
    const topNavLinksBtn = document.querySelector('.top-nav-links-btn');
    const topNavLinksMenu = document.querySelector('.top-nav-links-menu');
    if (topNavLinksBtn && topNavLinksMenu) {
        topNavLinksBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            topNavLinksMenu.classList.toggle('show');
        });
        document.addEventListener('click', function(e) {
            if (!topNavLinksBtn.contains(e.target) && !topNavLinksMenu.contains(e.target)) {
                topNavLinksMenu.classList.remove('show');
            }
        });
    }

    // 상단 네비게이션 아이템 클릭 이벤트
    document.querySelectorAll('.top-nav-item').forEach(item => {
        item.addEventListener('click', function(e) {
            if (this.classList.contains('top-nav-logout')) {
                return;
            }
            e.preventDefault();
            const page = this.getAttribute('data-page');
            setActiveTopNav(page);
            if (page) {
                switchPage(page);
                if (page === 'ranking') {
                    const rankingContainer = document.querySelector('#page-ranking .content-body');
                    rankingContainer.innerHTML = `
                        <section class="weather-card ranking-panel">
                            <div class="ranking-title-row">
                                <div>
                                    <h2 class="card-title">지역별 기온 랭킹</h2>
                                    <p class="ranking-meta">🌤️ 최신 랭킹을 불러오는 중입니다...</p>
                                </div>
                            </div>
                        </section>
                    `;

                    fetch('weather_ranking.php?t=' + new Date().getTime())
                        .then(response => response.text())
                        .then(html => {
                            rankingContainer.innerHTML = html;
                        })
                        .catch(() => {
                            rankingContainer.innerHTML = `
                                <section class="weather-card ranking-panel">
                                    <div class="ranking-title-row">
                                        <div>
                                            <h2 class="card-title">지역별 기온 랭킹</h2>
                                            <p class="ranking-error-text">데이터 로드 실패. 잠시 후 다시 시도해 주세요.</p>
                                        </div>
                                    </div>
                                </section>
                            `;
                        });
                }
            }
        });
    });

    const regionFormProfile = document.getElementById('regionFormProfile');
    if (regionFormProfile) {
      regionFormProfile.addEventListener('submit', function (e) {
        e.preventDefault();
        const sido = document.getElementById('region-sido-profile').value;
        const sigungu = document.getElementById('region-sigungu-profile').value;
        const dong = document.getElementById('region-dong-profile').value;

        let region = '';
        if (sido) region = sido;
        if (sigungu) region += (region ? ' ' : '') + sigungu;
        if (dong) region += (region ? ' ' : '') + dong;

        const profileRegion = document.getElementById('profileRegion');
        if (profileRegion) {
          profileRegion.textContent = region || '--';
        }

        const locationElement = document.getElementById('currentLocation');
        if (locationElement) {
          locationElement.textContent = region || '지역을 설정해주세요';
        }

        alert('지역이 설정되었습니다.');
      });
    }

    document.querySelectorAll('.delete-form').forEach(form => {
      form.addEventListener('submit', function (e) {
        const regionName = this.closest('li')?.querySelector('.region-name')?.textContent?.trim() || '해당 지역';
        const confirmed = window.confirm(`${regionName}을(를) 삭제하시겠습니까?\n삭제 후에는 다시 추가해야 합니다.`);
        if (!confirmed) {
          e.preventDefault();
        }
      });
    });

    // 3단계 드롭다운 로직 (프로필 페이지)
    document.addEventListener('DOMContentLoaded', () => {
      initRegionDropdowns('region-step1-profile', 'region-step2-profile', 'region-step3-profile');
    });

    function initRegionDropdowns(id1, id2, id3) {
      const step1 = document.getElementById(id1);
      const step2 = document.getElementById(id2);
      const step3 = document.getElementById(id3);

      if (!step1 || !step2 || !step3) return;

      // 1. 시/도 목록 로드
      fetch('get_regions.php?type=step1')
        .then(res => res.json())
        .then(data => {
          data.forEach(val => {
            const opt = document.createElement('option');
            opt.value = val;
            opt.textContent = val;
            step1.appendChild(opt);
          });
        })
        .catch(err => console.error('Error loading step1:', err));

      // 2. 시/도 변경 시 시/군/구 로드
      step1.addEventListener('change', function() {
        const val1 = this.value;
        
        // 하위 초기화
        step2.innerHTML = '<option value="">시/군/구 선택</option>';
        step2.disabled = true;
        step3.innerHTML = '<option value="">동/읍/면 선택</option>';
        step3.disabled = true;

        if (!val1) return;

        fetch(`get_regions.php?type=step2&step1=${encodeURIComponent(val1)}`)
          .then(res => res.json())
          .then(data => {
            data.forEach(val => {
              const opt = document.createElement('option');
              opt.value = val;
              opt.textContent = val;
              step2.appendChild(opt);
            });
            step2.disabled = false;
          })
          .catch(err => console.error('Error loading step2:', err));
      });

      // 3. 시/군/구 변경 시 동/읍/면 로드
      step2.addEventListener('change', function() {
        const val1 = step1.value;
        const val2 = this.value;

        // 하위 초기화
        step3.innerHTML = '<option value="">동/읍/면 선택</option>';
        step3.disabled = true;

        if (!val1 || !val2) return;

        fetch(`get_regions.php?type=step3&step1=${encodeURIComponent(val1)}&step2=${encodeURIComponent(val2)}`)
          .then(res => res.json())
          .then(data => {
            data.forEach(val => {
              const opt = document.createElement('option');
              opt.value = val;
              opt.textContent = val;
              step3.appendChild(opt);
            });
            step3.disabled = false;
          })
          .catch(err => console.error('Error loading step3:', err));
      });
    }
  </script>
</body>

</html>
