<?php
session_start();
// 로그인 증명서가 없을 때 즉시 로그인 페이지로 쫓아냄
if (!isset($_SESSION['user_id'])) {
  header("Location: auth.html");
  exit;
}
// 증명서가 있다면, 그 안의 아이디를 변수에 저장
$user_id = htmlspecialchars($_SESSION['user_id'], ENT_QUOTES);

// 브라우저가 페이지를 캐시하지 않게 강제
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

// DB에서 모든 선호 지역 목록 가져오기(RDBMS)
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "wdb";
$conn = new mysqli($host, $user, $pass, $dbname);
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die("DB 연결 실패: " . $conn->connect_error);
}

// 차트에 쓸 좌표 SELECT
$stmt = $conn->prepare("SELECT id, region_name, region_nx, region_ny FROM user_regions WHERE user_uid = ?");
$stmt->bind_param("s", $user_id);
$stmt->execute();
$result = $stmt->get_result();

// 선호 지역 목록
$saved_regions = [];
while ($row = $result->fetch_assoc()) {
    $saved_regions[] = $row;
}
$stmt->close();

//날씨 API 호출 및 데이터 가공
$google_chart_data_json = 'null';
$current_weather_info = "표시할 지역을 먼저 추가해 주세요.";
$main_region_name = "지역 미설정";

//선호 지역 1개 있을시 API 호출
if (!empty($saved_regions)) {
    
    // (1) 첫 번째 선호 지역을 기본으로 사용
    $main_region = $saved_regions[0];
    $main_region_name = htmlspecialchars($main_region['region_name']);
    $nx = $main_region['region_nx'];
    $ny = $main_region['region_ny'];

    // (2) KMA 단기예보용 'base_time' 자동 계산
    date_default_timezone_set('Asia/Seoul');
    $base_date = date('Ymd');
    $current_time = date('Hi'); // '1330' (오후 1시 30분)
    
    // 단기예보 API 발표 시각 (02:00, 05:00, 08:00, 11:00, 14:00, 17:00, 20:00, 23:00)
    // 각 발표 시간 10분 후부터 조회 가능 (예: 14:10부터 14:00 자료 조회 가능)
    $base_times_map = [
        '0210' => '0200',
        '0510' => '0500',
        '0810' => '0800',
        '1110' => '1100',
        '1410' => '1400',
        '1710' => '1700',
        '2010' => '2000',
        '2310' => '2300'
    ];
    
    $base_time = '2300'; // 기본값 (어제 23시)
    // 현재 시간과 비교하여 가장 최신 발표 시각 찾기
    foreach ($base_times_map as $api_time => $base) {
        if ($current_time >= $api_time) {
            $base_time = $base;
        }
    }
    // 만약 02:10 이전이라면, 어제 23:00 자료를 써야 함
    if ($current_time < '0210') {
        $base_date = date('Ymd', strtotime('-1 day'));
    }

    // (3) KMA API cURL 호출
    $serviceKey = "bbc2f96d627a4f50f836e44d783c2cb40633431aae9315876336c6bd9afd8432"; // 개인 키 입력
    $endpoint = "https://apis.data.go.kr/1360000/VilageFcstInfoService_2.0/getVilageFcst";
    
    $params = [
        'ServiceKey' => $serviceKey,
        'dataType'   => 'JSON',
        'base_date'  => $base_date,
        'base_time'  => $base_time,
        'nx'         => $nx,
        'ny'         => $ny,
        'pageNo'     => 1,
        'numOfRows'  => 300 // 12시간 * 약 12개 항목 = 144개 (넉넉하게 300개)
    ];
    
    $queryString = http_build_query($params);
    $requestUrl = $endpoint . '?' . $queryString;

    $ch = curl_init(); 
    curl_setopt($ch, CURLOPT_URL, $requestUrl); 
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); 
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
    $response = curl_exec($ch); 
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); 
    curl_close($ch); 

    // (4) API 응답 데이터 가공 (가장 중요!)
    if ($httpCode == 200) {
        $jsonData = json_decode($response, true);
        
        if (isset($jsonData['response']['header']['resultCode']) && $jsonData['response']['header']['resultCode'] == '00') {
            $items = $jsonData['response']['body']['items']['item'];
            
            // 1. 데이터를 시간대별로 "피벗(Pivot)" (재정렬)
            $weather_data = [];
            foreach ($items as $item) {
                $time = $item['fcstTime']; // '1800'
                $category = $item['category']; // 'TMP'
                $value = $item['fcstValue']; // '13'
                
                // 원하는 카테고리만 저장 (TMP, POP, REH, SKY, PTY)
                if (in_array($category, ['TMP', 'POP', 'REH', 'WSD', 'SKY', 'PTY'])) {
                    if (!isset($weather_data[$time])) {
                        $weather_data[$time] = []; // (예: $weather_data['1800'] = [])
                    }
                    $weather_data[$time][$category] = $value;
                }
            }
            ksort($weather_data); // 시간순 정렬

            // 2. Google Chart가 요구하는 형식 (배열의 배열)으로 변환
            $chart_rows = [];
            $chart_rows[] = ['시간', '기온(℃)', '강수확률(%)', '습도(%)']; // 헤더 행
            
            $count = 0;
            foreach ($weather_data as $time => $categories) {
                $formatted_time = substr($time, 0, 2) . "시"; // '1800' -> '18시'
                
                // SKY(하늘), PTY(강수)를 조합하여 '현재 날씨' 텍스트 생성 (첫 번째 시간대만)
                if ($count == 0) {
                    $sky = $categories['SKY'] ?? 'N/A';
                    $pty = $categories['PTY'] ?? 'N/A';
                    $weather_text = "맑음"; // 기본값
                    if ($pty != '0') {
                        if ($pty == '1') $weather_text = '비 🌧️';
                        else if ($pty == '2') $weather_text = '비/눈 🌨️';
                        else if ($pty == '3') $weather_text = '눈 ❄️';
                        else if ($pty == '4') $weather_text = '소나기 🌦️';
                    } else {
                        if ($sky == '3') $weather_text = '구름많음 ☁️';
                        else if ($sky == '4') $weather_text = '흐림 🌥️';
                    }
                    $current_weather_info = "현재: " . ($categories['TMP'] ?? '?') . "℃ / $weather_text";
                }

                // 차트에 데이터 행 추가
                $chart_rows[] = [
                    $formatted_time, 
                    (float)($categories['TMP'] ?? null), // 기온
                    (int)($categories['POP'] ?? null), // 강수확률
                    (int)($categories['REH'] ?? null)  // 습도
                ];

                $count++;
                if ($count >= 12) break; // 차트가 너무 길어지지 않게 12시간치만 표시
            }

            // 3. PHP 배열을 JS가 읽을 수 있는 JSON 문자열로 변환
            $google_chart_data_json = json_encode($chart_rows);

        } else {
            // API가 오류를 반환한 경우 (예: DEADLINE_EXCEEDED)
            $error_msg = $jsonData['response']['header']['resultMsg'] ?? 'API 응답 오류';
            $current_weather_info = "날씨 API 오류: " . $error_msg;
        }
    } else {
        // HTTP 통신 자체가 실패한 경우
        $current_weather_info = "날씨 API 호출 실패: HTTP Code $httpCode";
    }
}

// DB 연결 종료
$conn->close();
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>WDB 대시보드</title>
  <link rel="stylesheet" href="./auth.css" />

  <!--뒤로 가기 캐시 강제 해결 -->
  <script>
    window.addEventListener('pageshow', function(event) {
        if (event.persisted) {
            window.location.reload();
        }
    });
  </script>

  <style>
        .region-selector {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #dadce0;
        }
        .region-selector p { font-size: 1rem; margin-bottom: 10px; }
        .region-selector p strong { color: #1a73e8; }
        .region-selector select {
            width: 100%;
            padding: 10px;
            box-sizing: border-box; 
            border: 1px solid #dadce0; 
            border-radius: 4px;
            background-color: #ffffff; 
            color: #202124; 
            font-size: 1rem;
        }
        
        .region-list {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #dadce0; 
        }
        .region-list h3 { margin-top: 0; }
        .region-list ul { list-style: none; padding: 0; }
        .region-list li {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            border: 1px solid #dadce0; 
            border-radius: 4px;
            margin-bottom: 5px;
        }
        .region-list .delete-form { display: inline; margin: 0; }
        .region-list .delete-btn {
            background-color: #e74c3c;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.8rem;
            cursor: pointer;
        }
        
        .weather-chart-container {
            margin-bottom: 20px;
        }
        .weather-chart-container h2 {
            margin-top: 0;
            margin-bottom: 5px;
            font-size: 1.5rem;
        }
        .weather-chart-container .current-info {
            font-size: 1.1rem;
            color: #5f6368;
            margin-bottom: 10px;
        }
        #weather-chart {
            width: 100%;
            height: 300px;
        }
    </style>

    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>

    <script type="text/javascript">
      google.charts.load('current', {'packages':['corechart']});
      google.charts.setOnLoadCallback(drawChart);

      function drawChart() {
        const chartData = <?php echo $google_chart_data_json; ?>;
        const chartDiv = document.getElementById('weather-chart');
        
        if (!chartData) {
            chartDiv.innerHTML = "<p>표시할 날씨 데이터가 없습니다. (지역을 추가하거나 API를 확인하세요)</p>";
            return; 
        }

        const data = google.visualization.arrayToDataTable(chartData);

        const chartColors = {
            bg: '#ffffff',     // 패널 배경
            text: '#333333',     // 기본 텍스트
            grid: '#e0e0e0',     // 눈금선
            line1: '#e74c3c', // 기온 (빨강)
            line2: '#3498db', // 습도 (파랑)
            bars: '#95a5a6'   // 강수확률 (회색)
        };

        // 차트 옵션 설정
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

        // [유지] 차트 그리기
        const chart = new google.visualization.ComboChart(chartDiv);
        chart.draw(data, options);
      }
    </script>

</head>
<body>
  <header class="site-header">
    <h1>WDB 대시보드</h1>
    <p class="sub">테스트용 샘플 페이지입니다.</p>
  </header>

  <main class="auth-container">
    <section class="panel">
      <p>로그인 성공</p>
      <div class="weather-chart-container">
          <h2><?php echo $main_region_name; ?></h2>
          <p class="current-info"><?php echo $current_weather_info; ?></p>
          <div id="weather-chart"></div>
      </div>

      <div class="region-list">
          <h3>나의 선호 지역</h3>
          <ul>
              <?php if (empty($saved_regions)): ?>
                  <p>아직 저장된 선호 지역이 없습니다.</p>
              <?php else: ?>
                  <?php foreach ($saved_regions as $region): ?>
                      <li>
                          <span><?php echo htmlspecialchars($region['region_name']); ?></span>
                          <form class="delete-form" action="delete_region.php" method="POST">
                              <input type="hidden" name="region_id" value="<?php echo $region['id']; ?>">
                              <button type="submit" class="delete-btn">삭제</button>
                          </form>
                      </li>
                  <?php endforeach; ?>
              <?php endif; ?>
          </ul>
      </div>

      <form class="region-selector" action="add_region.php" method="POST">
          <label for="region-select"><strong>새 선호 지역 추가:</strong></label>
          <div class="field" style="margin-top: 5px;">
              <select id="region-select" name="region_data">
                  <option value="">-- 지역 선택 --</option>
                  <?php
                  // PHP 배열을 기반으로 드롭다운 옵션 자동 생성
                  $regions_list_for_form = [
                      "서울" => "서울/60/127", "부산" => "부산/98/76", "대구" => "대구/89/90",
                      "인천" => "인천/55/124", "광주" => "광주/58/74", "대전" => "대전/67/100",
                      "울산" => "울산/102/84", "경기" => "수원/60/121", "강원" => "춘천/73/134",
                      "충북" => "청주/69/107", "충남" => "홍성/68/100", "전북" => "전주/63/89",
                      "전남" => "무안/51/67", "경북" => "안동/91/106", "경남" => "창원/90/77",
                      "제주" => "제주/52/38"
                  ];
                  foreach ($regions_list_for_form as $name => $value) {
                      echo "<option value=\"$value\">$name</option>";
                  }
                  ?>
              </select>
          </div>
          <button class="primary" type="submit" style="margin-top: 10px;">추가하기</button>
      </form>
      
      <div style="margin-top:12px;">
        <a href="logout.php">
          <button class="primary" type="button">로그아웃(돌아가기)</button>
        </a>
      </div>
    </section>
  </main>
</body>
</html>