<?php
session_start();

// 로그인 상태가 아니면 auth.html로 강제 이동
if (!isset($_SESSION['user_id'])) {
  header("Location: auth.html");
  exit;
}

$user_id = htmlspecialchars($_SESSION['user_id'], ENT_QUOTES);

// 캐시 방지
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
  <link rel="stylesheet" href="./dashboard.css" />

  <!-- 뒤로 가기 방지 -->
  <script>
    window.addEventListener('pageshow', function(event) {
        if (event.persisted) {
            window.location.reload();
        }
    });
  </script>
</head>
<body>
  <div class="dashboard-container">

    <!-- 왼쪽 사이드바 (Navbar) -->
    <aside class="sidebar">
      <div class="sidebar-header">
        <h2>WDB 날씨</h2>
      </div>
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
        <!-- 로그아웃은 PHP 세션 종료 파일로 이동 -->
        <a href="logout.php" class="nav-item nav-logout">
          <span class="nav-icon">🚪</span>
          <span class="nav-text">로그아웃</span>
        </a>
      </nav>
    </aside>

    <!-- 메인 콘텐츠 영역 -->
    <main class="main-content">

      <!-- 대시보드 페이지 -->
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
          <!-- 현재 날씨 정보 카드 -->
          <section class="weather-card">
            <h2>현재 날씨</h2>
            <div class="weather-info">
              <div class="weather-main">
                <div class="temperature">--°C</div>
                <div class="location">지역을 설정해주세요</div>
              </div>
              <div class="weather-details">
                <div class="detail-item">
                  <span class="detail-label">강수확률</span>
                  <span class="detail-value">--%</span>
                </div>
                <div class="detail-item">
                  <span class="detail-label">습도</span>
                  <span class="detail-value">--%</span>
                </div>
                <div class="detail-item">
                  <span class="detail-label">풍속</span>
                  <span class="detail-value">--m/s</span>
                </div>
              </div>
            </div>
          </section>

          <!-- 옷차림 추천 카드 -->
          <section class="weather-card">
            <h2>오늘의 옷차림</h2>
            <div class="outfit-recommendation">
              <p class="outfit-message clickable" id="outfitMessage">
                지역을 설정하면 맞춤형 옷차림을 추천해드립니다.
              </p>
            </div>
          </section>

          <!-- 기상 알림 카드 -->
          <section class="weather-card">
            <h2>기상 알림</h2>
            <div class="alert-list">
              <p class="no-alert">현재 특별한 기상 알림이 없습니다.</p>
            </div>
          </section>

          <!-- 차트 영역 -->
          <section class="weather-card chart-card">
            <h2>날씨 차트</h2>
            <div id="weather-chart" class="chart-container">
              <p>차트 데이터를 불러오는 중...</p>
            </div>
          </section>
        </div>
      </div>

      <!-- 날씨 랭킹 페이지 -->
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

      <!-- 내 정보 페이지 -->
      <div class="page-content" id="page-profile">
        <header class="content-header">
          <h1>내 정보</h1>
        </header>

        <div class="content-body">
          <!-- 계정 정보 -->
          <section class="weather-card">
            <h2>계정 정보</h2>
            <div class="profile-info">
              <div class="info-item">
                <span class="info-label">아이디</span>
                <span class="info-value" id="profileUid"><?php echo $user_id; ?></span>
              </div>
              <div class="info-item">
                <span class="info-label">설정 지역</span>
                <span class="info-value" id="profileRegion">--</span>
              </div>
            </div>
          </section>

          <!-- 지역 설정 -->
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
    /* ---- 기존 JS 로직 그대로 유지 ---- */

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

    document.getElementById('profileBtn').addEventListener('click', function() {
      switchPage('profile');
      document.querySelectorAll('.nav-item').forEach(nav => nav.classList.remove('active'));
      document.querySelector('.nav-item[data-page="profile"]').classList.add('active');
    });

    document.getElementById('outfitMessage').addEventListener('click', function() {
      switchPage('profile');
      document.querySelectorAll('.nav-item').forEach(nav => nav.classList.remove('active'));
      document.querySelector('.nav-item[data-page="profile"]').classList.add('active');
      setTimeout(() => {
        const regionSection = document.getElementById('regionSettingSection');
        if (regionSection) regionSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }, 100);
    });

    document.getElementById('regionFormProfile').addEventListener('submit', function(e) {
      e.preventDefault();
      const sido = document.getElementById('region-sido-profile').value;
      const sigungu = document.getElementById('region-sigungu-profile').value;
      const dong = document.getElementById('region-dong-profile').value;

      let region = sido;
      if (sigungu) region += ' ' + sigungu;
      if (dong) region += ' ' + dong;

      document.getElementById('profileRegion').textContent = region;

      const locationElement = document.querySelector('.location');
      if (locationElement) locationElement.textContent = region;

      alert('지역이 설정되었습니다.');
    });

    document.querySelectorAll('.nav-item').forEach(item => {
      item.addEventListener('click', function(e) {
        if (this.classList.contains('nav-logout')) return;
        e.preventDefault();
        document.querySelectorAll('.nav-item').forEach(nav => nav.classList.remove('active'));
        this.classList.add('active');
        const page = this.getAttribute('data-page');
        switchPage(page);
      });
    });
  </script>
</body>
</html>
