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

</head>
<body>
  <header class="site-header">
    <h1>WDB 대시보드</h1>
    <p class="sub">테스트용 샘플 페이지입니다.</p>
  </header>

  <main class="auth-container">
    <section class="panel">
      <p>로그인 성공</p>
      <div style="margin-top:12px;">
        <a href="logout.php">
          <button class="primary" type="button">로그아웃(돌아가기)</button>
        </a>
      </div>
    </section>
  </main>
</body>
</html>


