<?php
/**
 * 기온에 따른 옷차림 추천 함수
 * @param float $temp 현재 기온
 * @return string 추천 옷차림 멘트
 */
function getClothingRecommendation($temp) {
    $comment = "";
    $icon = "";

    // 기온별 옷차림 로직
    if ($temp >= 28) {
        $comment = "🎽한여름 날씨예요! 민소매, 짧은 치마, 반팔, 반바지, 린넨 소재의 시원한 옷을 추천해요.";
    } elseif ($temp >= 23) {
        $comment = "👕약간 더워요. 반팔, 얇은 셔츠, 반바지나 면바지가 좋겠어요.";
    } elseif ($temp >= 20) {
        $comment = "👚활동하기 좋은 날씨! 얇은 가디건이나 블라우스, 긴팔 티셔츠, 면바지, 슬랙스를 추천해요.";
    } elseif ($temp >= 17) {
        $comment = "🧥약간 쌀쌀할 수 있어요. 얇은 니트나 가디건, 맨투맨, 후드, 긴바지를 추천해요.";
    } elseif ($temp >= 12) {
        $comment = "🧥본격적인 환절기네요. 자켓, 스타킹, 청바지, 청자켓, 니트, 도톰한 가디건을 추천해요.";
    } elseif ($temp >= 9) {
        $comment = "🧥꽤 쌀쌀해요. 트렌치코트, 야상, 점퍼, 스타킹, 기모바지 등을 추천해요.";
    } elseif ($temp >= 5) {
        $comment = "🧣추워요! 코트나 가죽자켓을 입고, 히트텍을 입으면 좋아요.";
    } else {
        $comment = "🧤한파 주의! 패딩, 두꺼운 코트, 목도리, 장갑으로 꽁꽁 싸매세요!";
    }

    return $comment;
}
?>