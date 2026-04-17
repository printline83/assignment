# 시계거래소 실무 과제: 상품 예약 및 결제 시스템

본 프로젝트는 **CodeIgniter 3(CI3)** 기반으로 구축되었으며, 고가 중고 시계 거래의 특성을 반영한 **재고 정합성(동시성 제어)**과 **데이터 스냅샷 설계**에 중점을 두었습니다.

---

## 1. 프로젝트 구조 (CI3 기반)

CI3 환경에 익숙하지 않은 검토자를 위해 주요 로직의 위치를 안내드립니다.

- **Controller**: `application/controllers/Api.php`
  - 주요 비즈니스 로직(예약 생성, 결제 검증, 취소 등) 진입점
- **Model**: `application/models/`
  - DB 트랜잭션, 비관적 락(`FOR UPDATE`), 상태 변경 처리
- **View**: `application/views/`
  - 상품 목록 UI 및 토스페이먼츠 연동(SDK) 페이지
- **Cron**: `application/controllers/Cron.php`
  - CLI 기반의 만료 재고 환원 배치 프로그램

---

## 2. 데이터베이스 스키마 설계 (Database Schema)

### **t_Products (상품 정보)**

- `f_ProductId` (PK): 상품 고유 번호
- `f_Stock`: 실시간 잔여 재고 (재고가 1개인 고가 시계 특성을 고려한 핵심 컬럼)

### **t_Reservations (예약 및 주문 원장)**

- `f_ReservationId` (PK): 고유 문자열 예약번호 (보안 및 식별성 강화)
- **`f_ProductName`, `f_Amount` (Data Snapshot)**: 중고 제품 특성상 관리자가 상품명이나 가격을 수정하더라도, **예약 시점의 구매 조건을 보장**하기 위해 당시 정보를 스냅샷 형태로 저장합니다.
- `f_Status`: 상태 (`ED`:대기, `CF`:완료, `CX`:취소, `EX`:만료)
- `f_ExpiredAt`: 예약 유효 시각 (발동 시점 + 10분)

### **t_Payments (결제 승인 이력)**

- `f_TransactionId` (Unique): 토스 `paymentKey`. **중복 결제 승인 방지(Idempotency)**를 위해 유니크 제약 조건을 적용했습니다.

---

## 3. 핵심 구현 포인트

### ① 비관적 락(Pessimistic Lock)을 통한 재고 보호

재고가 1개뿐인 고가 상품에 수천 명의 동시 요청이 발생하는 경합 상황(Race Condition)을 대비하여, 예약 시점에 `SELECT ... FOR UPDATE`를 사용하여 행 잠금을 수행합니다. 이를 통해 실제 결제 가능 인원을 물리적으로 제한하여 초과 판매(Over-selling)를 원천 차단했습니다.

### ② 데이터 일관성을 위한 스냅샷(Snapshot) 전략

중고 제품 특성상 관리자에 의해 상품명이나 가격이 수시로 변경될 수 있습니다. 마스터 테이블(`t_Products`)의 정보가 변하더라도 예약 당시의 비즈니스 조건을 보장하기 위해, 상품명과 결제 예정 금액을 `t_Reservations` 원장에 독립적으로 기록하는 스냅샷 방식을 채택하여 데이터 정합성을 유지합니다.

### ③ 3중 재고 환원 시스템 (Garbage Collection)

미결제 상태로 재고가 묶여 실사용자가 구매하지 못하는 '허수 예약' 문제를 해결하기 위해 3단계의 방어막을 구축했습니다.

- **즉시 환원**: 사용자의 능동적인 결제 취소나 토스페이먼츠의 실패(failUrl) 응답 시 즉시 재고 복구
- **능동 환원**: 신규 사용자가 예약 시도 시, 10분이 경과한 기존 미결제 데이터를 서브쿼리 레벨에서 감지하여 실시간 재고 부활
- **백그라운드 환원**: `Cron.php`를 활용하여 독립적인 마이크로 트랜잭션 기반의 하드 스위핑(Sweeping) 처리를 통해 서비스 부하를 분산

---

## 4. 설치 및 실행 방법

1. **DB 구성** 루트의 `schema.sql`을 실행하여 테이블 및 테스트용 데이터를 생성합니다.
2. **환경변수 설정** 루트에 `.env` 파일을 생성하고 아래 내용을 입력합니다. (현업 관례에 따라 민감 정보는 분리 관리합니다.)

   ```env
   DB_HOST = localhost
   DB_USER = root
   DB_PASS = password
   DB_NAME = assignment_db

   ```

3. **배치 작업(Cron) 등록**  
   유효시간(10분)이 지난 미결제 데이터를 자동으로 정리하고 재고를 환원하기 위해  
   아래 명령어를 크론탭에 등록하거나 CLI에서 주기적으로 실행합니다.

```bash
# CLI 환경에서 1~5분 주기로 실행 권장
php index.php cron sweep_expired
```
