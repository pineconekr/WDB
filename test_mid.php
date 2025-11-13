<?php

$serviceKey = urlencode("b3c360a413cb1615dcc61e3e22cdcc872dfb50b558ae305f91254c16e5eacffe"); 
// 또는 공공데이터포털의 Encoding Key 그대로 넣기 (추가 인코딩 금지)

$ch = curl_init();
$url = 'http://apis.data.go.kr/1360000/MidFcstInfoService/getMidFcst';

/* 요청 파라미터 구성 */
$queryParams  = '?' . urlencode('ServiceKey') . '=' . $serviceKey; 
$queryParams .= '&' . urlencode('pageNo') . '=' . urlencode('1');
$queryParams .= '&' . urlencode('numOfRows') . '=' . urlencode('10');
$queryParams .= '&' . urlencode('dataType') . '=' . urlencode('XML');
$queryParams .= '&' . urlencode('stnId') . '=' . urlencode('108');
$queryParams .= '&' . urlencode('tmFc') . '=' . urlencode(date('Ymd') . '0600'); 
// 또는 특정 시간 사용: '202501110600'

/* CURL 옵션 */
curl_setopt($ch, CURLOPT_URL, $url . $queryParams);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, false);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');

/* 실행 */
$response = curl_exec($ch);
$err      = curl_error($ch);
curl_close($ch);

/* 결과 출력 */
if ($response === false) {
    echo "CURL ERROR: " . $err;
} else {
    header('Content-Type: text/xml; charset=utf-8');
    echo $response;
}

?>
