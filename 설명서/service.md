# 기상청 단기예보 조회서비스(VilageFcstInfoService_2.0) 오픈API 활용가이드 – 전체 상세 요약

본 문서는 기상청 단기예보 조회서비스의 전체 기능, 요청/응답 규격, 예보 요소 코드값, 발표 시각 규칙, 격자 <-> 위경도 변환 알고리즘, OpenAPI 에러 규격 등 **서비스 구현에 필요한 모든 기술 요소를 포괄적으로 정리한 기술 문서**이다.

---

# 1. 서비스 명세

## 1.1 API 개요

### ● API 기본 정보
- **API명(영문)**: `VilageFcstInfoService_2.0`
- **API명(국문)**: 단기예보 조회서비스(2.0)
- **API 설명**
  - 초단기실황(Ncst): 예보 구역에 대한 대표 AWS 관측값 제공
  - 초단기예보(Fcst): 발표 시각 기준 ~6시간 이내 예보 제공
  - 단기예보(VilageFcst): 1시간/3시간 간격의 세분화된 예보 제공
  - 예보버전 조회: 초단기실황/초단기예보/단기예보 각각의 파일 버전 조회 기능 제공

---

### ● 인증 및 통신 방식
- **인증**: ServiceKey (URL Encode 필요)
- **메시지 암호화**: 없음
- **전송 레벨 암호화**: SSL 미사용
- **인터페이스 표준**: `REST (GET)`
- **데이터 포맷**: XML, JSON 지원
- **메시지 교환형태**: Request–Response
- **서비스 URL**  
  `http://apis.data.go.kr/1360000/VilageFcstInfoService_2.0`
- **서비스 버전/배포일**: v1.0 / 2021-07-01
- **데이터 갱신 주기**: 수시(단기예보는 1일 8회)

---

# 1.2 상세 기능 목록
| 번호 | API명 | 상세기능명(영문) | 기능 |
|-----|--------|---------------------|--------|
| 1 | 단기예보 조회서비스 | getUltraSrtNcst | 초단기실황조회 |
| 2 | 단기예보 조회서비스 | getUltraSrtFcst | 초단기예보조회 |
| 3 | 단기예보 조회서비스 | getVilageFcst | 단기예보조회 |
| 4 | 단기예보 조회서비스 | getFcstVersion | 예보버전조회 |

---

# 1.3 상세 기능 내역

---

# 1) 초단기실황조회 (`getUltraSrtNcst`)

## a) 상세 기능 설명
- 발표일자(base_date), 발표시각(base_time), 좌표(nx, ny)를 조건으로  
  자료구분코드(category), 관측 실황(obsrValue) 등을 조회하는 기능.
- 평균 응답시간: 100ms  
- TPS: 30  
- 최대 메시지 크기: 1764 bytes  
- Callback URL:  
  `.../getUltraSrtNcst`

---

## b) 요청 메시지 명세
| 필드 | 설명 | 필수 | 예시 |
|------|--------|------|--------|
| serviceKey | 인증키 | 1 | 발급받은 키 |
| numOfRows | 한 페이지 결과 수 | 1 | 10 |
| pageNo | 페이지 번호 | 1 | 1 |
| dataType | 응답 자료형식 | 0 | XML |
| base_date | 발표일자 YYYYMMDD | 1 | 20210628 |
| base_time | 발표시각 HH00 | 1 | 0600 |
| nx | 예보지점 X좌표 | 1 | 55 |
| ny | 예보지점 Y좌표 | 1 | 127 |

※ **매시각 10분 이후 호출해야 해당 정시 실황 데이터 조회 가능**

---

## c) 응답 메시지 구조
- 공통: `resultCode`, `resultMsg`, `numOfRows`, `pageNo`, `totalCount`
- item 필드:
  - baseDate / baseTime
  - category (자료구분코드: RN1, T1H, UUU, VVV, WSD 등)
  - obsrValue (실황 값)
  - nx / ny

---

## d) 예시 요청
```
.../getUltraSrtNcst?serviceKey=인증키&numOfRows=10&pageNo=1
&base_date=20210628&base_time=0600&nx=55&ny=127
```

## d) 예시 응답(XML)
```xml
<item>
  <baseDate>20210628</baseDate>
  <baseTime>0600</baseTime>
  <category>RN1</category>
  <nx>55</nx>
  <ny>127</ny>
  <obsrValue>1.1</obsrValue>
</item>
```

---

# 2) 초단기예보조회 (`getUltraSrtFcst`)

## a) 상세 기능 정보
- 발표시각 base_time은 **30분 단위 (HH30)**
- “매시각 45분 이후 호출”해야 정상 조회
- 최대 메시지 크기: 2686 bytes  
- Callback URL:  
  `.../getUltraSrtFcst`

---

## b) 요청 필드
| 필드 | 설명 | 필수 | 예시 |
|------|--------|------|--------|
| base_date | 발표일자 | 1 | 20210628 |
| base_time | 발표시각(30분단위 HH30) | 1 | 0630 |
| nx / ny | 격자 | 1 | 55 / 127 |

다른 필드는 초단기실황과 동일

---

## c) 응답 item 구성
- baseDate / baseTime  
- category (예: LGT, RN1, SKY, T1H 등)
- fcstDate / fcstTime  
- fcstValue (예보값)

---

## d) 예시 응답
```xml
<item>
  <baseDate>20210628</baseDate>
  <baseTime>0630</baseTime>
  <category>LGT</category>
  <fcstDate>20210628</fcstDate>
  <fcstTime>1200</fcstTime>
  <fcstValue>0</fcstValue>
  <nx>55</nx>
  <ny>127</ny>
</item>
```

---

# 3) 단기예보조회 (`getVilageFcst`)

## a) 기능 설명
- 발표일자/시각 + 좌표를 조건으로  
  TMP, TMN, TMX, POP, PCP, SKY, PTY, REH, WSD, VEC 등 **단기예보 전체 요소** 조회
- 최대 메시지: 48,452 bytes  
- 평균 응답시간: 600ms  
- Callback URL:  
  `.../getVilageFcst`

---

## b) 요청 필드
- base_time은 **02, 05, 08, 11, 14, 17, 20, 23시** 중 하나  
- numOfRows 기본값 10(예시 50)

---

## c) 응답 item 구조
- baseDate / baseTime  
- fcstDate / fcstTime  
- category (TMP, POP, SKY, PTY, PCP, TMN, TMX…)  
- fcstValue  
- nx / ny  

### 단기예보의 중요한 규칙
- **02·05·08·11·14시 발표 → 글피(3일 뒤)까지 제공**
- **17·20·23시 발표 → 그글피(4일 뒤)까지 제공**
- 연장 예보 구간은:
  - 시간 간격: **3시간**
  - PCP/SNO/WSD는 **정성 코드값**(약한 비/보통 비/강한 비 등)으로만 제공

---

## d) 예시 응답
```xml
<item>
  <baseDate>20210628</baseDate>
  <baseTime>0500</baseTime>
  <category>TMP</category>
  <fcstDate>20210628</fcstDate>
  <fcstTime>0600</fcstTime>
  <fcstValue>21</fcstValue>
  <nx>55</nx>
  <ny>127</ny>
</item>
```

---

# 4) 예보버전조회 (`getFcstVersion`)

## a) 기능 개요
- 특정 발표시각(base_datetime)에 해당하는  
  초단기실황/초단기예보/단기예보 파일의 **버전(생성 시각)** 조회
- 주요 필드:
  - ftype: ODAM(초단기실황), VSRT(초단기예보), SHRT(단기예보)
  - basedatetime: YYYYMMDDHHMM

---

## b) 응답 item
- filetype  
- version (파일 생성 시간: 예 `20210628092217`)

---

# 2. 참고자료

# 2.1 단기예보 연장 코드값(정성 코드)

## ● 강수량(PCP)
| 코드 | 의미 | 기준 |
|------|--------|---------|
| 1 | 약한 비 | < 3mm/h |
| 2 | 보통 비 | 3~15mm/h |
| 3 | 강한 비 | ≥15mm/h |

## ● 적설(SNO)
| 코드 | 의미 | 기준 |
|------|--------|---------|
| 1 | 보통 눈 | <1cm/h |
| 2 | 많은 눈 | ≥1cm/h |

## ● 풍속(WSD)
| 코드 | 의미 | 기준 |
|------|--------|---------|
| 1 | 약한 바람 | ≥4m/s |
| 2 | 약간 강 | 4~9m/s |
| 3 | 강한 바람 | ≥9m/s |

---

# 2.2 예보 요소 코드값 전체

## ● 단기예보 요소
- POP(강수확률, %), PTY(강수형태 코드),
- PCP(1시간 강수량), REH(습도)
- SNO(신적설), SKY(하늘상태 코드)
- TMP(1시간 기온), TMN(일 최저기온), TMX(일 최고기온)
- UUU(동서 성분), VVV(남북 성분)
- WAV(파고), VEC(풍향), WSD(풍속)

## ● 초단기실황 요소
- T1H, RN1, UUU, VVV, REH, PTY, VEC, WSD

## ● 초단기예보 요소
- T1H, RN1, SKY, UUU, VVV, REH, PTY, LGT(낙뢰), VEC, WSD

---

# 2.3 요소별 범주 규칙

## ● SKY
- 1: 맑음  
- 3: 구름많음  
- 4: 흐림

## ● PTY
- 초단기: 없음(0), 비(1), 비/눈(2), 눈(3), 빗방울(5), 빗방울눈날림(6), 눈날림(7)
- 단기: 없음(0), 비(1), 비/눈(2), 눈(3), 소나기(4)

---

# 2.4 강수량(RN1, PCP) 범주

- 1mm 미만 → `"1mm 미만"`
- 1.0~29.9mm → `"X.X mm"`
- 30~50mm → `"30.0~50.0mm"`
- 50mm 이상 → `"50.0mm 이상"`
- -, null, 0 → "강수없음"

---

# 2.5 신적설(SNO) 범주
- 0.5cm 미만 → `"0.5cm 미만"`
- 0.5~4.9cm → `"X.Xcm"`
- ≥5cm → `"5.0cm 이상"`
- -, null, 0 → "적설없음"

---

# 2.6 풍향 성분 규칙
- UUU: 동(+), 서(-)
- VVV: 북(+), 남(-)

---

# 2.7 해상 마스킹 규칙
- 해상 지역에는 **기온군, 강수확률, 강수량/적설, 습도 제공 안 함**  
- Missing 값으로 마스킹 처리됨

---

# 2.8 단기예보 발표 / 제공 시각

## ● 발표시각(Base_time)
- 02, 05, 08, 11, 14, 17, 20, 23시 (1일 8회)
- API 제공 가능 시각: 발표시각 +10분  
  예) 02:10, 05:10 …

---

# 2.9 초단기실황 생성 및 제공 시각

- 매 정시 생성  
- base_time = HH00  
- 제공 가능 시각: HH:10  
- 예) 13시 자료 → 13:10 이후 호출

---

# 2.10 초단기예보 생성 시각 및 예보기간

- 매시 30분 생성 (HH30)
- 제공: HH45 이후
- 6시간 예보 제공  
  예: 00시 발표 → 0~6시 구간 예보

---

# 2.11 최고/최저기온 저장 규칙
- 발표 시각별로 “오늘/내일/모레/글피/그글피”의 Tmin/Tmax 저장 여부가 표로 정의
- 02시 발표는 가장 많은 날의 Tmin/Tmax를 포함  
- 17·20·23시 발표는 **오늘 포함 + 그글피까지** 포함되는 구조

---

# 2.12 하늘상태 전운량 규칙
| 하늘상태 | 전운량 |
|----------|---------|
| 맑음 | 0~5 |
| 구름많음 | 6~8 |
| 흐림 | 9~10 |

---

# 2.13 풍향 구간별 방향 문자열
| 각도 | 방향 |
|------|--------|
| 0–45 | N-NE |
| 45–90 | NE-E |
| 90–135 | E-SE |
| 135–180 | SE-S |
| 180–225 | S-SW |
| 225–270 | SW-W |
| 270–315 | W-NW |
| 315–360 | NW-N |

---

# 2.14 풍향 16방위 변환식

```
변환값 = floor( (풍향값 + 22.5*0.5) / 22.5 )
```

| 값 | 16방위 |
|----|---------|
| 0 | N |
| 1 | NNE |
| 2 | NE |
| 3 | ENE |
| 4 | E |
| 5 | ESE |
| 6 | SE |
| 7 | SSE |
| 8 | S |
| 9 | SSW |
| 10 | SW |
| 11 | WSW |
| 12 | W |
| 13 | WNW |
| 14 | NW |
| 15 | NNW |
| 16 | N |

예: 풍향 339° → NNW

---

# 2.15 Lambert 정각 원추도 기반 위경도 <-> 격자 변환

## ● C 프로그램 제공

### 컴파일
```
cc 소스파일명 -lm
```

### 실행 - 격자→위경도
```
a.out 1 X Y
```

### 실행 - 위경도→격자
```
a.out 0 경도 위도
```

### 코드 핵심 구성
- lamc_parameter 구조체  
  (지구반경, 격자간격, 표준위도1/2, 기준 경위도, 기준 좌표)
- map_conv(): 변환 방향(code=0/1)에 따라 lamcproj() 호출
- lamcproj(): Lambert Conformal Conic 투영 구현  
  - sn, sf, ro 계산  
  - 경위도 ↔ 지도 좌표 변환  
- NX=149, NY=253 격자 정의

---

# 2.16 OpenAPI 에러 코드 목록

| 코드 | 메시지 | 의미 |
|------|----------------------------|----------------------------|
| 00 | NORMAL_SERVICE | 정상 |
| 01 | APPLICATION_ERROR | 어플리케이션 오류 |
| 02 | DB_ERROR | 데이터베이스 오류 |
| 03 | NODATA_ERROR | 데이터 없음 |
| 04 | HTTP_ERROR | HTTP 오류 |
| 05 | SERVICETIME_OUT | 서비스 연결 실패 |
| 10 | INVALID_REQUEST_PARAMETER_ERROR | 잘못된 요청 파라미터 |
| 11 | NO_MANDATORY_REQUEST_PARAMETERS_ERROR | 필수 요청 파라미터 없음 |
| 12 | NO_OPENAPI_SERVICE_ERROR | 서비스 없음/폐기 |
| 20 | SERVICE_ACCESS_DENIED_ERROR | 접근 거부 |
| 21 | TEMPORARILY_DISABLE_THE_SERVICEKEY_ERROR | 일시적으로 사용 불가 |
| 22 | LIMITED_NUMBER_OF_SERVICE_REQUESTS_EXCEEDS_ERROR | 요청 제한 초과 |
| 30 | SERVICE_KEY_IS_NOT_REGISTERED_ERROR | 등록되지 않은 키 |
| 31 | DEADLINE_HAS_EXPIRED_ERROR | 키 만료 |
| 32 | UNREGISTERED_IP_ERROR | 등록되지 않은 IP |
| 33 | UNSIGNED_CALL_ERROR | 서명되지 않은 호출 |
| 99 | UNKNOWN_ERROR | 기타 오류 |

---

# 전체 결론

본 문서는 기상청 ‘단기예보 조회서비스(2.0)’의  
- 호출 규격(파라미터/응답 구조)  
- 예보 요소 코드 체계  
- 시간 규칙(발표/제공/예보기간)  
- 좌표 변환 알고리즘  
- 에러 규격  

까지 **API 구현에 필요한 모든 정보를 완전하게 제공하는 기술 명세서**이며,  
초단기실황·초단기예보·단기예보·예보버전 조회까지 전 기능을 위한 전체 구조를 포함한다.