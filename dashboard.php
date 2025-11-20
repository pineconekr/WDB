<?php
session_start();

if (!isset($_SESSION['user_id'])) {
  header("Location: auth.html");
  exit;
}

require_once 'RecommendClothes.php';

date_default_timezone_set('Asia/Seoul');

$user_id = $_SESSION['user_id'];

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

$main_region_name = "지역 미설정";
$current_weather_info = "표시할 지역을 먼저 추가해 주세요.";
$current_weather_detail = null;
$google_chart_data_json = 'null';
$profile_region_text = "--";
$active_region_id = null;
$outfit_message = "<span style='color: #e74c3c; font-weight: bold;'>⚠️ 저장된 지역이 없습니다.</span><br>좌측 사이드바에서 지역을 추가해 주세요.";

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

  $weatherPayload = fetchWeatherData((int) $main_region['region_nx'], (int) $main_region['region_ny']);
  $google_chart_data_json = $weatherPayload['chart_json'];
  $current_weather_info = $weatherPayload['current_info'];
  $current_weather_detail = $weatherPayload['current_detail'];

  if (isset($current_weather_detail['temperature'])) {
    if (function_exists('getClothingRecommendation')) {
      $outfit_message = getClothingRecommendation((float) $current_weather_detail['temperature']);
    }
  }

}

$conn->close();

$regions_list_for_form = [
  "서울" => "서울/60/127",
  "부산" => "부산/98/76",
  "대구" => "대구/89/90",
  "인천" => "인천/55/124",
  "광주" => "광주/58/74",
  "대전" => "대전/67/100",
  "울산" => "울산/102/84",
  "경기" => "수원/60/121",
  "강원" => "춘천/73/134",
  "충북" => "청주/69/107",
  "충남" => "홍성/68/100",
  "전북" => "전주/63/89",
  "전남" => "무안/51/67",
  "경북" => "안동/91/106",
  "경남" => "창원/90/77",
  "제주" => "제주/52/38"
];

function fetchSavedRegions($conn, $userId)
{
  $stmt = $conn->prepare("SELECT id, region_name, region_nx, region_ny FROM user_regions WHERE user_uid = ?");
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

function findRegionById($conn, $regionId, $userId)
{
  $stmt = $conn->prepare("SELECT id, region_name, region_nx, region_ny FROM user_regions WHERE id = ? AND user_uid = ?");
  $stmt->bind_param("is", $regionId, $userId);
  $stmt->execute();
  $result = $stmt->get_result();
  $region = $result->fetch_assoc();
  $stmt->close();

  return $region ?: null;
}

function fetchWeatherData($nx, $ny)
{
  $serviceKey = "bbc2f96d627a4f50f836e44d783c2cb40633431aae9315876336c6bd9afd8432";
  $endpoint = "https://apis.data.go.kr/1360000/VilageFcstInfoService_2.0/getVilageFcst";

  list($base_date, $base_time) = resolveBaseDateTime();

  $params = [
    'ServiceKey' => $serviceKey,
    'dataType' => 'JSON',
    'base_date' => $base_date,
    'base_time' => $base_time,
    'nx' => $nx,
    'ny' => $ny,
    'pageNo' => 1,
    'numOfRows' => 300
  ];

  $requestUrl = $endpoint . '?' . http_build_query($params);

  $ch = curl_init($requestUrl);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

  $response = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $curlError = curl_error($ch);
  curl_close($ch);

  if ($response === false) {
    $message = $curlError ? $curlError : '네트워크 오류';
    return [
      'chart_json' => 'null',
      'current_info' => "날씨 API 호출 실패: {$message}",
      'current_detail' => null
    ];
  }

  if ($httpCode !== 200) {
    return [
      'chart_json' => 'null',
      'current_info' => "날씨 API 호출 실패: HTTP Code {$httpCode}",
      'current_detail' => null
    ];
  }

  $jsonData = json_decode($response, true);

  if (!isset($jsonData['response']['header']['resultCode']) || $jsonData['response']['header']['resultCode'] !== '00') {
    $error_msg = $jsonData['response']['header']['resultMsg'] ?? 'API 응답 오류';
    return [
      'chart_json' => 'null',
      'current_info' => "날씨 API 오류: {$error_msg}",
      'current_detail' => null
    ];
  }

  $items = $jsonData['response']['body']['items']['item'] ?? [];
  list($chartRows, $currentInfo, $currentDetails) = transformWeatherItems($items);

  if (empty($chartRows)) {
    return [
      'chart_json' => 'null',
      'current_info' => $currentInfo,
      'current_detail' => $currentDetails
    ];
  }

  $chartJson = json_encode($chartRows, JSON_UNESCAPED_UNICODE);
  if ($chartJson === false) {
    $chartJson = 'null';
  }

  return [
    'chart_json' => $chartJson,
    'current_info' => $currentInfo,
    'current_detail' => $currentDetails
  ];
}

function transformWeatherItems($items)
{
  $weatherData = [];

  foreach ($items as $item) {
    $time = isset($item['fcstTime']) ? $item['fcstTime'] : null;
    $category = isset($item['category']) ? $item['category'] : null;
    $value = isset($item['fcstValue']) ? $item['fcstValue'] : null;

    if ($time === null || $category === null) {
      continue;
    }

    if (!in_array($category, ['TMP', 'POP', 'REH', 'WSD', 'SKY', 'PTY'], true)) {
      continue;
    }

    if (!isset($weatherData[$time])) {
      $weatherData[$time] = [];
    }

    $weatherData[$time][$category] = $value;
  }

  if (empty($weatherData)) {
    return [[], "날씨 데이터가 없습니다.", null];
  }

  ksort($weatherData, SORT_STRING);

  $chartRows = [
    ['시간', '기온(℃)', '강수확률(%)', '습도(%)']
  ];
  $currentInfo = "날씨 데이터가 없습니다.";
  $currentDetails = null;
  $count = 0;

  foreach ($weatherData as $time => $categories) {
    if ($count === 0) {
      $currentInfo = buildCurrentWeatherText($categories);
      $currentDetails = buildCurrentDetail($categories, $time);
    }

    $chartRows[] = [
      substr($time, 0, 2) . '시',
      isset($categories['TMP']) ? (float) $categories['TMP'] : null,
      isset($categories['POP']) ? (int) $categories['POP'] : null,
      isset($categories['REH']) ? (int) $categories['REH'] : null
    ];

    $count++;

    if ($count >= 12) {
      break;
    }
  }

  if ($count === 0) {
    return [[], "날씨 데이터가 없습니다.", null];
  }

  return [$chartRows, $currentInfo, $currentDetails];
}

function buildCurrentWeatherText($categories)
{
  $temp = isset($categories['TMP']) ? $categories['TMP'] : '?';
  $sky = isset($categories['SKY']) ? $categories['SKY'] : null;
  $pty = isset($categories['PTY']) ? $categories['PTY'] : null;
  $weatherText = '맑음';

  if ($pty !== null && $pty !== '0') {
    switch ($pty) {
      case '1':
        $weatherText = '비';
        break;
      case '2':
        $weatherText = '비/눈';
        break;
      case '3':
        $weatherText = '눈';
        break;
      case '4':
        $weatherText = '소나기';
        break;
      default:
        $weatherText = '강수';
    }
  } else {
    if ($sky === '3') {
      $weatherText = '구름많음';
    } elseif ($sky === '4') {
      $weatherText = '흐림';
    }
  }

  return "현재: {$temp}℃ / {$weatherText}";
}

function buildCurrentDetail($categories, $time)
{
  return [
    'time' => $time,
    'temperature' => isset($categories['TMP']) ? (float) $categories['TMP'] : null,
    'pop' => isset($categories['POP']) ? (int) $categories['POP'] : null,
    'reh' => isset($categories['REH']) ? (int) $categories['REH'] : null,
    'wsd' => isset($categories['WSD']) ? (float) $categories['WSD'] : null
  ];
}

function formatWeatherMetric($value, $unit = '', $decimals = null)
{
  if ($value === null || $value === '' || !is_numeric($value)) {
    return $unit ? "--{$unit}" : "--";
  }

  $number = (float) $value;
  if ($decimals !== null) {
    $display = number_format($number, max(0, (int) $decimals), '.', '');
  } else {
    $display = ($number == (int) $number) ? (string) (int) $number : (string) $number;
  }

  return $display . $unit;
}

function resolveBaseDateTime()
{
  $timezone = new DateTimeZone('Asia/Seoul');
  $now = new DateTimeImmutable('now', $timezone);
  $currentTime = $now->format('Hi');
  $baseDate = $now->format('Ymd');
  $baseTime = '2300';

  $baseTimesMap = [
    '0210' => '0200',
    '0510' => '0500',
    '0810' => '0800',
    '1110' => '1100',
    '1410' => '1400',
    '1710' => '1700',
    '2010' => '2000',
    '2310' => '2300'
  ];

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
?>
<!DOCTYPE html>
<html lang="ko">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>WDB 대시보드</title>
  <link rel="stylesheet" href="./dashboard.css" />

  <script>
    window.addEventListener('pageshow', function (event) {
      if (event.persisted) {
        window.location.reload();
      }
    });
  </script>

  <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
  <script type="text/javascript">
    const chartData = <?php echo $google_chart_data_json; ?>;

    google.charts.load('current', { 'packages': ['corechart'] });
    google.charts.setOnLoadCallback(() => drawChart(chartData));

    function drawChart(sourceData) {
      const chartDiv = document.getElementById('weather-chart');
      if (!chartDiv) {
        return;
      }

      if (!Array.isArray(sourceData) || sourceData.length <= 1) {
        chartDiv.innerHTML = "<p>표시할 날씨 데이터가 없습니다. (지역을 추가하거나 API를 확인하세요)</p>";
        return;
      }

      const data = google.visualization.arrayToDataTable(sourceData);

      const chartColors = {
        bg: '#ffffff',
        text: '#333333',
        grid: '#e0e0e0',
        line1: '#e74c3c',
        line2: '#3498db',
        bars: '#95a5a6'
      };

      const options = {
        title: '시간별 상세 예보 (12시간)',
        backgroundColor: chartColors.bg,
        titleTextStyle: { color: chartColors.text },
        legend: {
          position: 'bottom',
          textStyle: { color: chartColors.text }
        },
        hAxis: { textStyle: { color: chartColors.text } },
        vAxes: {
          0: {
            title: '기온(℃) / 습도(%)',
            textStyle: { color: chartColors.text },
            titleTextStyle: { color: chartColors.text }
          },
          1: {
            title: '강수확률(%)',
            textStyle: { color: chartColors.text },
            titleTextStyle: { color: chartColors.text },
            gridlines: { color: 'transparent' },
            minValue: 0,
            maxValue: 100
          }
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
  <div class="dashboard-layout">
    <aside class="sidebar">
      <section class="summary-panel">
        <p class="login-state"><?php echo htmlspecialchars($user_id, ENT_QUOTES, 'UTF-8'); ?>님 환영합니다!</p>

        <div class="digital-clock-widget">
          <div id="clock-time" class="clock-time">--:--</div>
          <div id="clock-date" class="clock-date">--월 --일 (-)</div>
        </div>

        <h2 id="activeRegionTitle"><?php echo htmlspecialchars($main_region_name, ENT_QUOTES, 'UTF-8'); ?></h2>
        <p class="current-info" id="activeRegionInfo">
          <?php echo htmlspecialchars($current_weather_info, ENT_QUOTES, 'UTF-8'); ?>
        </p>
      </section>

      <section class="region-list">
        <h3>나의 선호 지역</h3>
        <?php if (empty($saved_regions)): ?>
          <p class="empty-region">아직 저장된 선호 지역이 없습니다.</p>
        <?php else: ?>
          <ul>
            <?php foreach ($saved_regions as $region): ?>
              <?php
              $regionId = (int) $region['id'];
              $isActive = $active_region_id === $regionId;
              ?>
              <li data-region-id="<?php echo $regionId; ?>">
                <span
                  class="region-name"><?php echo htmlspecialchars($region['region_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                <div class="region-actions">
                  <form class="set-region-form" method="GET">
                    <input type="hidden" name="region_id" value="<?php echo $regionId; ?>">
                    <button type="submit" class="set-region-btn<?php echo $isActive ? ' active' : ''; ?>"
                      aria-label="선택 지역 변경">
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
      </section>

      <form class="region-selector" action="add_region.php" method="POST">
        <label for="region-select"><strong>새 선호 지역 추가:</strong></label>
        <div class="field">
          <select id="region-select" name="region_data" required>
            <option value="">-- 지역 선택 --</option>
            <?php foreach ($regions_list_for_form as $name => $value): ?>
              <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <button class="primary" type="submit">추가하기</button>
      </form>

      <nav class="sidebar-nav">
        <a href="#" class="nav-item active" data-page="dashboard">
          <span class="nav-icon">🏠</span>
          <span class="nav-text">대시보드</span>
        </a>
        <a href="#" class="nav-item" data-page="ranking">
          <span class="nav-icon">📊</span>
          <span class="nav-text">날씨 랭킹</span>
        </a>
        <a href="#" class="nav-item" data-page="profile">
          <span class="nav-icon">👤</span>
          <span class="nav-text">내 정보</span>
        </a>
        <a href="logout.php" class="nav-item nav-logout">
          <span class="nav-icon">🚪</span>
          <span class="nav-text">로그아웃</span>
        </a>
        <div class="external-links">
          <p class="links-title">외부 링크</p>
          <div class="links-grid">
            <a href="https://www.weather.go.kr" target="_blank" class="link-item" title="기상청">
              🏛️ 기상청
            </a>
            <a href="https://www.airkorea.or.kr" target="_blank" class="link-item" title="에어코리아">
              😷 대기질
            </a>
            <a href="https://map.naver.com" target="_blank" class="link-item" title="지도">
              🗺️ 지도
            </a>
          </div>
        </div>
      </nav>
    </aside>

    <main class="main-content">
      <div class="page-content active" id="page-dashboard">
        <header class="content-header">
          <h1>대시보드</h1>
          <div class="header-actions">
            <button class="profile-btn" id="profileBtn" title="내 정보 조회">
              <span class="profile-icon">👤</span>
            </button>
          </div>
        </header>

        <div class="content-body">
          <section class="weather-card">
            <h2>
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
                  <span class="detail-label">강수확률</span>
                  <span class="detail-value"
                    id="currentPop"><?php echo formatWeatherMetric($current_weather_detail['pop'] ?? null, '%', 0); ?></span>
                </div>
                <div class="detail-item">
                  <span class="detail-label">습도</span>
                  <span class="detail-value"
                    id="currentReh"><?php echo formatWeatherMetric($current_weather_detail['reh'] ?? null, '%', 0); ?></span>
                </div>
                <div class="detail-item">
                  <span class="detail-label">풍속</span>
                  <span class="detail-value"
                    id="currentWind"><?php echo formatWeatherMetric($current_weather_detail['wsd'] ?? null, 'm/s', 1); ?></span>
                </div>
              </div>
            </div>
          </section>

          <section class="weather-card">
            <h2>오늘의 옷차림</h2>
            <div class="outfit-recommendation">
              <?php
              echo $outfit_message;
              ?>
            </div>
          </section>


          <!--TODO 기상알림 - 추후에 구현 예정(?) -->
          <!-- <section class="weather-card">
            <h2>기상 알림</h2>
            <div class="alert-list">
              <p class="no-alert">현재 특별한 기상 알림이 없습니다.</p>
            </div>
          </section> -->

          <section class="weather-card chart-card">
            <h2>날씨 차트</h2>
            <div id="weather-chart" class="chart-container">
              <p>차트 데이터를 불러오는 중...</p>
            </div>
          </section>
        </div>
      </div>

      <div class="page-content" id="page-ranking">
        <header class="content-header">
          <h1>날씨 랭킹</h1>
        </header>
        <div class="content-body">
          <section class="weather-card">
            <h2>지역별 기온 랭킹</h2>
            <p>랭킹 데이터를 불러오는 중...</p>
          </section>
        </div>
      </div>

      <div class="page-content" id="page-profile">
        <header class="content-header">
          <h1>내 정보</h1>
        </header>

        <div class="content-body">
          <section class="weather-card">
            <h2>계정 정보</h2>
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
            <h2>지역 설정</h2>
            <div class="region-setting">
              <form id="regionFormProfile">
                <div class="field">
                  <label for="region-sido-profile">시/도</label>
                  <select id="region-sido-profile" name="sido" required>
                    <option value="">선택하세요</option>
                    <option value="서울">서울특별시</option>
                    <option value="부산">부산광역시</option>
                    <option value="대구">대구광역시</option>
                    <option value="인천">인천광역시</option>
                    <option value="광주">광주광역시</option>
                    <option value="대전">대전광역시</option>
                    <option value="울산">울산광역시</option>
                    <option value="세종">세종특별자치시</option>
                    <option value="경기">경기도</option>
                    <option value="강원">강원도</option>
                    <option value="충북">충청북도</option>
                    <option value="충남">충청남도</option>
                    <option value="전북">전라북도</option>
                    <option value="전남">전라남도</option>
                    <option value="경북">경상북도</option>
                    <option value="경남">경상남도</option>
                    <option value="제주">제주특별자치도</option>
                  </select>
                </div>

                <div class="field">
                  <label for="region-sigungu-profile">시/군/구</label>
                  <input type="text" id="region-sigungu-profile" name="sigungu" placeholder="예: 강남구, 수원시" required />
                </div>

                <div class="field">
                  <label for="region-dong-profile">동/읍/면 (선택)</label>
                  <input type="text" id="region-dong-profile" name="dong" placeholder="예: 역삼동" />
                </div>

                <button type="submit" class="primary">지역 저장</button>
              </form>
            </div>
          </section>
        </div>
      </div>
    </main>
  </div>

  <script>
    function switchPage(pageName) {
      document.querySelectorAll('.page-content').forEach(page => {
        page.classList.remove('active');
      });
      const selectedPage = document.getElementById(`page-${pageName}`);
      if (selectedPage) {
        selectedPage.classList.add('active');
        window.scrollTo({ top: 0, behavior: 'smooth' });
      }
    }

    const profileBtn = document.getElementById('profileBtn');
    if (profileBtn) {
      profileBtn.addEventListener('click', function () {
        switchPage('profile');
        document.querySelectorAll('.nav-item').forEach(nav => nav.classList.remove('active'));
        const profileNav = document.querySelector('.nav-item[data-page="profile"]');
        if (profileNav) {
          profileNav.classList.add('active');
        }
      });
    }

    const outfitMessage = document.getElementById('outfitMessage');
    if (outfitMessage) {
      outfitMessage.addEventListener('click', function () {
        switchPage('profile');
        document.querySelectorAll('.nav-item').forEach(nav => nav.classList.remove('active'));
        const profileNav = document.querySelector('.nav-item[data-page="profile"]');
        if (profileNav) {
          profileNav.classList.add('active');
        }
        setTimeout(() => {
          const regionSection = document.getElementById('regionSettingSection');
          if (regionSection) {
            regionSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
          }
        }, 100);
      });
    }

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

    document.querySelectorAll('.nav-item').forEach(item => {
      item.addEventListener('click', function (e) {
        if (this.classList.contains('nav-logout')) {
          return;
        }
        e.preventDefault();
        document.querySelectorAll('.nav-item').forEach(nav => nav.classList.remove('active'));
        this.classList.add('active');
        const page = this.getAttribute('data-page');
        if (page) {
          switchPage(page);
        }
      });
    });

    document.querySelectorAll('.delete-form').forEach(form => {
      form.addEventListener('submit', function (e) {
        const regionName = this.closest('li')?.querySelector('.region-name')?.textContent?.trim() || '해당 지역';
        const confirmed = window.confirm(`${regionName}을(를) 삭제하시겠습니까?\n삭제 후에는 다시 추가해야 합니다.`);
        if (!confirmed) {
          e.preventDefault();
        }
      });
    });
  </script>
</body>

</html>