<!DOCTYPE html>
<html>
<head>
    <title>날씨 API 테스트</title>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; line-height: 1.6; }
        pre { background-color: #f4f4f4; border: 1px solid #ddd; padding: 10px; }
    </style>
</head>
<body>

    <h1>기상청 단기예보 API 호출 결과</h1>

    <?php
    $serviceKey = "b3c360a413cb1615dcc61e3e22cdcc872dfb50b558ae305f91254c16e5eacffe"; 
    
    $endpoint = "https://apis.data.go.kr/1360000/VilageFcstInfoService_2.0/getVilageFcst";

    $params = [
        'ServiceKey' => $serviceKey,
        'dataType'   => 'JSON',
        'base_date'  => '20251109',
        'base_time'  => '1700',
        'nx'         => 60,
        'ny'         => 127,
        'pageNo'     => 1,
        'numOfRows'  => 100 
    ];

    $queryString = http_build_query($params);
    $requestUrl = $endpoint . '?' . $queryString;

    echo "<h3>1. 요청 URL (참고용):</h3>";
    echo "<p>" . htmlspecialchars($requestUrl) . "</p>";

    $ch = curl_init(); 
    curl_setopt($ch, CURLOPT_URL, $requestUrl); 
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); 
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 

    $response = curl_exec($ch); 
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); 
    curl_close($ch); 

    echo "<h3>2. API 응답 결과 (JSON):</h3>";
    if ($httpCode == 200) {
        $jsonData = json_decode($response, true); 

        echo "<pre>";
        print_r($jsonData);
        echo "</pre>";

    } else {
        echo "<h4>API 호출 실패: HTTP Code " . $httpCode . "</h4>";
        echo "<pre>응답 내용: " . htmlspecialchars($response) . "</pre>";
    }

    ?>

</body>
</html>