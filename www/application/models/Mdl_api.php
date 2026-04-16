<?php
class Mdl_api extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    // 전체 상품 목록 및 현재 결제 대기 중인 수량 정보 조회
    public function get_products()
    {
        $this->db->select('A.*, (SELECT COUNT(*) FROM '.RS_INFO." B WHERE B.f_ProductId = A.f_ProductId AND B.f_Status = 'ED' AND B.f_ExpiredAt > NOW()) AS f_PendingCount", false);
        $a_products = $this->db->get_where(PD_INFO.' A', [
            'A.f_Delete' => 'N',
        ])->result_array();

        return query_array($a_products);
    }

    // 특정 상품의 상세 정보 및 재고 유무 확인
    public function get_product($v_ProductId)
    {
        $a_product = $this->db->get_where(PD_INFO, [
            'f_Delete'    => 'N',
            'f_ProductId' => $v_ProductId,
            'f_Stock >'   => 0,
        ])->row_array();

        return query_row($a_product);
    }

    // 예약(주문) 생성: 비관적 락을 통한 재고 선차감 및 10분 유효 시간 부여
    public function create_reservation($a_prm)
    {
        $this->db->trans_begin();

        // 1. 중복 요청 방지 및 멱등성 검사 (완료건, 혹은 아직 만료되지 않은 대기건만 체크)
        $this->db->where('f_ProductId', $a_prm['v_ProductId']);
        $this->db->where('f_UserPhone', $a_prm['v_UserPhone']);
        $this->db->group_start();
        $this->db->where('f_Status', 'CF');
        $this->db->or_group_start();
        $this->db->where('f_Status', 'ED');
        $this->db->where('f_ExpiredAt >', date('Y-m-d H:i:s'));
        $this->db->group_end();
        $this->db->group_end();
        $a_exists = $this->db->get(RS_INFO)->row_array();
        
        if (!empty($a_exists)) {
            $this->db->trans_rollback();
            // CF: 완료됨, ED: 결제진행중(대기)
            $msg = $a_exists['f_Status'] == 'CF' ? '이미 완료된 예약이 있습니다.' : '현재 결제 대기 중인 예약이 이미 존재합니다. (10분 후 재시도 가능)';

            return ['status' => false, 'message' => $msg];
        }

        // 2. 상품 정보 조회 (동시성 제어를 위한 비관적 락 설정)
        // 참고: CI3 get_where()로는 FOR UPDATE 구문을 추가하기 어려우므로 동시성 보장을 위해 raw 쿼리 사용
        $sql       = 'SELECT f_Stock, f_Price, f_ProductName FROM '.PD_INFO." WHERE f_ProductId = ? AND f_Delete = 'N' FOR UPDATE";
        $a_product = $this->db->query($sql, [$a_prm['v_ProductId']])->row_array();

        if (empty($a_product)) {
            $this->db->trans_rollback();

            return ['status' => false, 'message' => '존재하지 않거나 삭제된 상품입니다.'];
        }

        // 2-1. 지연 만료 처리 (예약 시점에 과거 만료 건들을 찾아 스윕, FOR UPDATE 락)
        $sweep_sql = 'SELECT f_ReservationId FROM '.RS_INFO." WHERE f_ProductId = ? AND f_Status = 'ED' AND f_ExpiredAt <= NOW() FOR UPDATE";
        $a_expired = $this->db->query($sweep_sql, [$a_prm['v_ProductId']])->result_array();

        if (!empty($a_expired)) {
            $a_expired_ids = array_column($a_expired, 'f_ReservationId');
            
            // 만료 처리 상태 변경
            $this->db->where_in('f_ReservationId', $a_expired_ids);
            $this->db->where('f_Status', 'ED');
            $this->db->set('f_Status', 'EX');
            $this->db->update(RS_INFO);
            
            $recovered_count = $this->db->affected_rows();

            if ($recovered_count > 0) {
                // 이미 PD_INFO에 락이 걸려있으므로 안전하게 재고 반환
                $this->db->where('f_ProductId', $a_prm['v_ProductId']);
                $this->db->set('f_Stock', "f_Stock + {$recovered_count}", false);
                $this->db->update(PD_INFO);
                
                $a_product['f_Stock'] += $recovered_count;
            }
        }

        // 3. 1차 재고 유효성 검사
        if ($a_product['f_Stock'] <= 0) {
            $this->db->trans_rollback();

            return ['status' => false, 'message' => '해당 상품은 모두 품절되었습니다.'];
        }

        // 4. 동시성을 고려한 재고 차감
        // 이미 FOR UPDATE로 락을 획득했으므로 안전함
        $this->db->where('f_ProductId', $a_prm['v_ProductId']);
        $this->db->set('f_Stock', 'f_Stock - 1', false);
        $this->db->update(PD_INFO);

        // 5. 예약 정보 생성
        $v_ExpireAt      = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        $b_inserted      = false;
        $v_ReservationId = '';

        $i_attempts = 0;
        while ($i_attempts < 30) {
            $v_ReservationId = generate_res_id(); // 변경된 스키마에 따라 문자열 PK 직접 생성
            
            // PK 중복 여부 확인
            $a_dup = $this->db->select('f_ReservationId')->get_where(RS_INFO, ['f_ReservationId' => $v_ReservationId])->row_array();
                              
            if (empty($a_dup)) {
                $b_inserted = true;

                break;
            }
            $i_attempts++;
        }

        if (!$b_inserted) {
            $this->db->trans_rollback();

            return ['status' => false, 'message' => '예약번호 갱신에 실패했습니다. 잠시 후 다시 시도해주세요.'];
        }

        $this->db->insert(RS_INFO, [
            'f_ReservationId' => $v_ReservationId,
            'f_ProductId'     => $a_prm['v_ProductId'],
            'f_UserId'        => 'guest', // 비회원 기본값
            'f_UserName'      => $a_prm['v_UserName'],
            'f_UserPhone'     => $a_prm['v_UserPhone'],
            'f_Amount'        => $a_product['f_Price'],
            'f_ProductName'   => $a_product['f_ProductName'],
            'f_Status'        => 'ED',
            'f_CreatedAt'     => date('Y-m-d H:i:s'),
            'f_ExpiredAt'     => $v_ExpireAt
        ]);

        // 6. 최종 검증 및 트랜잭션 커밋
        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();

            return ['status' => false, 'message' => '시스템 오류가 발생하여 예약에 실패했습니다.'];
        }

        $this->db->trans_commit();

        return [
            'status' => true,
            'data'   => [
                'v_ReservationId' => $v_ReservationId,
                'v_ExpireAt'      => $v_ExpireAt,
                'v_Amount'        => $a_product['f_Price'],
                'v_OrderName'     => $a_product['f_ProductName']
            ]
        ];
    }

    // 토스 페이먼츠 결제 승인 확인 및 최종 상태 업데이트 (성공 시 PY_INFO 기록)
    public function confirm_payment($paymentKey, $orderId, $amount)
    {
        $secretKey = 'test_sk_Lex6BJGQOVDnYxAwgvJ8W4w2zNbg';
        $url       = 'https://api.tosspayments.com/v1/payments/confirm';

        $data = [
            'paymentKey' => $paymentKey,
            'orderId'    => $orderId,
            'amount'     => $amount
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Basic '.base64_encode($secretKey.':'),
            'Content-Type: application/json'
        ]);

        $response  = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $resData = json_decode($response, true);

        if ($http_code == 200) {
            $this->db->trans_begin();

            // 예약 상태 원자적 업데이트 (결제 금액 무결성 방어 포함)
            $this->db->where('f_ReservationId', $orderId);
            $this->db->where('f_Status', 'ED');
            $this->db->where('f_Amount', $amount); // 금액 위변조 방지
            $this->db->set('f_Status', 'CF');
            $this->db->update(RS_INFO);

            if ($this->db->affected_rows() == 0) {
                $this->db->trans_rollback();

                // 중복 호출 대응 (Idempotency): 이미 성공 처리된 건이라면 정상 응답을 반환하여 중복 취소를 방지함
                $check = $this->db->select('f_Status')->where('f_ReservationId', $orderId)->get(RS_INFO)->row_array();
                if (!empty($check) && $check['f_Status'] === 'CF') {
                    return ['status' => true, 'data' => $resData];
                }

                // 진짜 만료되었거나 금액 위변조가 발생한 경우에만 취소 프로세스 진행
                $this->cancel_reservation($orderId);
                $this->_cancel_toss_payment($paymentKey, '결제 금액 위변조 감지 또는 예약 시간 만료');

                return ['status' => false, 'message' => '유효하지 않은 예약이거나 이미 만료되었습니다. 결제가 즉시 자동 취소되었습니다.'];
            }

            // t_Payments 결제 이력 저장
            $this->db->insert(PY_INFO, [
                'f_ReservationId'  => $orderId,
                'f_Amount'         => $amount,
                'f_Status'         => 'SS', // 성공 (Success)
                'f_TransactionId'  => $paymentKey, // PG사 승인번호 (Unique 제약조건으로 중복 방지)
                'f_CreatedAt'      => date('Y-m-d H:i:s')
            ]);
            
            if ($this->db->trans_status() === false) {
                $this->db->trans_rollback();

                // DB 삽입 실패 등 시스템 에러 시 기승인된 결제 환불 처리
                $this->_cancel_toss_payment($paymentKey, '내부 서버 시스템 오류로 인한 결제 롤백');

                return ['status' => false, 'message' => '내부 시스템 오류로 결제 승인 처리에 실패하여 자동 환불 처리되었습니다.'];
            }
            
            $this->db->trans_commit();

            return ['status' => true, 'data' => $resData];
        } else {
            return ['status' => false, 'message' => isset($resData['message']) ? $resData['message'] : '결제 승인에 실패했습니다.'];
        }
    }

    // 토스 페이먼츠 API를 통한 실제 승인 취소(환불) 요청 처리
    private function _cancel_toss_payment($paymentKey, $cancelReason)
    {
        $secretKey = 'test_sk_Lex6BJGQOVDnYxAwgvJ8W4w2zNbg';
        $url       = 'https://api.tosspayments.com/v1/payments/'.$paymentKey.'/cancel';

        $data = ['cancelReason' => $cancelReason];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Basic '.base64_encode($secretKey.':'),
            'Content-Type: application/json'
        ]);

        $response  = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // HTTP 200 이면 성공
        return $http_code == 200;
    }

    // 능동적 결제 취소 및 환불 로직
    // 결제 완료된 주문에 대한 전액 환불 및 재고 복구 처리
    public function refund_payment($v_ReservationId)
    {
        // 1. 성공한 결제 내역 조회
        $payment = $this->db->where('f_ReservationId', $v_ReservationId)
                            ->where('f_Status', 'SS')
                            ->get(PY_INFO)->row_array();
        
        if (empty($payment)) {
            return ['status' => false, 'message' => '취소할 수 있는 유효한 결제 내역을 찾을 수 없습니다.'];
        }

        $res_row = $this->db->select('f_ProductId')->where('f_ReservationId', $v_ReservationId)->get(RS_INFO)->row();
        if (empty($res_row)) {
            return ['status' => false, 'message' => '예약 정보를 찾을 수 없습니다.'];
        }
        $v_ProductId = $res_row->f_ProductId;

        $this->db->trans_begin();

        // [공통 로직] 예약 상태 변경 및 재고 복구 시도 (CF -> CX)
        $b_core_success = $this->_core_cancel_and_restore_stock($v_ReservationId, 'CF', 'CX');
        
        if ($b_core_success) {
            // 4. 페이먼트 이력(PY_INFO) 취소 상태로 변경
            $this->db->where('f_PaymentId', $payment['f_PaymentId'])->set('f_Status', 'CX')->update(PY_INFO);

            // 5. 실제 토스망 API 취소 (실패 시 전체 Rollback 처리하여 정합성 유지)
            $toss_cancel = $this->_cancel_toss_payment($payment['f_TransactionId'], '고객 단순 변심 취소');
            if (!$toss_cancel) {
                // 토스 망 에러/기취소 방어
                $this->db->trans_rollback();

                return ['status' => false, 'message' => '토스 PG사 연동 취소에 실패했습니다. (이미 취소된 거래일 수 있습니다.)'];
            }
        } else {
            $this->db->trans_rollback();

            return ['status' => false, 'message' => '이미 취소되었거나 환불이 불가한 예약 상태입니다.'];
        }

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();

            return ['status' => false, 'message' => '시스템 오류로 환불에 실패했습니다.'];
        }

        $this->db->trans_commit();

        return ['status' => true];
    }

    // 결제 대기 중인 예약에 대한 취소(포기) 및 재고 복구 처리
    public function cancel_reservation($v_ReservationId, $v_Status = 'CX')
    {
        $this->db->trans_begin();

        // [공통 로직] 예약 상태 변경 및 재고 복구 시도 (ED -> $v_Status)
        $success = $this->_core_cancel_and_restore_stock($v_ReservationId, 'ED', $v_Status);

        if (!$success) {
            $this->db->trans_rollback();

            return false;
        }

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();

            return false;
        }

        $this->db->trans_commit();

        return true;
    }
 
    // 취소 및 환불 시 공통적으로 사용되는 예약 상태 변경과 재고 환원 핵심 로직
    private function _core_cancel_and_restore_stock($v_ReservationId, $v_RequiredPrevStatus, $v_TargetStatus)
    {
        // 1. 상품ID 및 현재 상태 확인 (락을 걸기 전 가벼운 체크)
        $row = $this->db->select('f_ProductId, f_Status')
                        ->where('f_ReservationId', $v_ReservationId)
                        ->get(RS_INFO)->row_array();
        
        if (empty($row) || $row['f_Status'] !== $v_RequiredPrevStatus) {
            return false;
        }

        $v_ProductId = $row['f_ProductId'];

        // 2. 상품 테이블 락 획득 (데드락 방지를 위해 항상 예약 생성 시와 동일한 순서로 Product 락 선획득)
        $this->db->query('SELECT f_ProductId FROM '.PD_INFO.' WHERE f_ProductId = ? FOR UPDATE', [$v_ProductId]);
        
        // 3. 예약 상태 업데이트 (그 사이에 누군가 처리했을 수 있으므로 다시 한번 상태 조건 체크)
        $this->db->where('f_ReservationId', $v_ReservationId);
        $this->db->where('f_Status', $v_RequiredPrevStatus);
        $this->db->set('f_Status', $v_TargetStatus);
        $this->db->update(RS_INFO);
        
        if ($this->db->affected_rows() > 0) {
            // 4. 제품 재고 +1 즉시 복구
            $this->db->where('f_ProductId', $v_ProductId);
            $this->db->set('f_Stock', 'f_Stock + 1', false);
            $this->db->update(PD_INFO);

            return true;
        }

        return false;
    }

    // 결제창 진입 전 예약의 유효성(상태, 시간) 검사 및 만료 시 자동 취소
    public function check_reservation_validity($v_ReservationId)
    {
        $this->db->select('f_Status, f_ExpiredAt');
        $this->db->where('f_ReservationId', $v_ReservationId);
        $row = $this->db->get(RS_INFO)->row_array();

        if (empty($row)) {
            return false;
        }
        if ($row['f_Status'] !== 'ED') {
            return false;
        }
        
        // 현재 시간이 만료 시간을 지났는지 확인
        if (strtotime($row['f_ExpiredAt']) < time()) {
            // 유효 시간이 넘은 경우 즉각 취소(재고 복구) 로직 실행
            $this->cancel_reservation($v_ReservationId, 'EX');

            return false;
        }

        return true;
    }
} // class Mdl_api
