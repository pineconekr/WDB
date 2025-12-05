<?php
/**
 * 기온(체감온도 반영)에 따른 옷차림 추천 함수 (dashboard.php 로직 100% 일치 버전)
 * @param float $temp 현재 기온
 * @return string 추천 옷차림 멘트
 */
function getClothingRecommendation($temp) {
    global $current_weather_detail;
    $wsd = isset($current_weather_detail['wsd']) ? (float)$current_weather_detail['wsd'] : 0;
    $windKmh = $wsd * 3.6; 
    $sensoryTemp = $temp;
    $isWindChillApplied = false;

    if ($temp <= 10 && $windKmh >= 4.8) {
        $powWind = pow($windKmh, 0.16);
        $sensoryTemp = 13.12 + 0.6215 * $temp - 11.37 * $powWind + 0.3965 * $temp * $powWind;
        $sensoryTemp = round($sensoryTemp, 1);
        $isWindChillApplied = true;
    }

    $comment = "";
    $accessory = "";

    if ($temp >= 35) {
        $comment = "🔥폭염 경보! 에어컨 없인 위험해요. 민소매, 린넨 등 최대한 시원하고 얇은 옷 필수!";
    } elseif ($sensoryTemp >= 30) {
        $comment = "☀️진짜 여름 날씨! 통기성 좋은 반팔, 반바지, 짧은 치마를 입으세요.";
    } elseif ($sensoryTemp >= 27) {
        $comment = "🎽꽤 더워요. 반팔 티셔츠나 얇은 셔츠, 반바지가 딱 좋아요.";
    } elseif ($sensoryTemp >= 24) {
        $comment = "👕습도가 느껴지는 더위네요. 얇은 반팔이나 셔츠에 면바지를 추천해요.";
    } elseif ($sensoryTemp >= 21) {
        $comment = "👚활동하기 가장 좋은 날씨! 긴팔 티셔츠, 얇은 셔츠, 슬랙스로 멋내기 좋아요.";
    } elseif ($sensoryTemp >= 19) {
        $comment = "🧥그늘은 서늘해요. 얇은 니트나 맨투맨을 입고, 가벼운 가디건을 챙기세요.";
    } elseif ($sensoryTemp >= 17) {
        $comment = "🧶쌀쌀함이 느껴져요. 도톰한 가디건이나 후드티, 맨투맨을 입으세요.";
    } elseif ($sensoryTemp >= 14) {
        $comment = "🧥겉옷이 필수예요. 자켓, 청자켓, 야상, 셔츠에 니트를 레이어드하세요.";
    } elseif ($sensoryTemp >= 12) {
        $comment = "🧥바람이 차가워요. 트렌치코트, 간절기 점퍼, 스타킹을 챙기세요.";
    } elseif ($sensoryTemp >= 9) {
        $comment = "🍂초겨울 느낌! 코트나 가죽자켓, 도톰한 점퍼를 꺼내 입으세요.";
    } elseif ($sensoryTemp >= 6) {
        $comment = "🧣이제 춥네요. 히트텍을 입고, 코트나 얇은 패딩조끼를 겹쳐 입으세요.";
    } elseif ($sensoryTemp >= 2) {
        $comment = "❄️겨울입니다. 두꺼운 코트나 패딩을 입고 목도리를 하세요.";
    } elseif ($sensoryTemp >= -4) {
        $comment = "🧤입김이 나와요! 숏패딩이나 두꺼운 파카, 장갑이 필요해요.";
    } elseif ($sensoryTemp >= -9) {
        $comment = "🥶강력한 추위! 롱패딩으로 무장하고 귀마개, 목도리로 꽁꽁 싸매세요.";
    } else {
        $comment = "⛔외출 자제 추천! 내복+패딩+방한용품으로 생존 패션이 필요해요!";
    }

    return $comment;
}
?>