<?php
class Mdl_api extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    public function get_products()
    {
        $a_products = $this->db->get_where(PD_INFO, [
            'f_Delete' => 'N',
        ])->result_array();

        return query_array($a_products);
    }

    public function get_product($v_ProductId)
    {
        $a_product = $this->db->get_where(PD_INFO, [
            'f_Delete'    => 'N',
            'f_ProductId' => $v_ProductId,
            'f_Stock >'   => 0,
        ])->row_array();

        return query_row($a_product);
    }

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
                'v_ExpireAt'      => $v_ExpireAt
            ]
        ];
    }
} // class Mdl_api
