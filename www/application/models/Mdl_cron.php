<?php
class Mdl_cron extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    public function process_expired()
    {
        // 글로벌 락(FOR UPDATE)을 걸면 예약 진입/결제 스레드와 통째로 데드락(Deadlock)이 발생할 수 있습니다.
        // 따라서 단순 조회 후, 각 예약건마다 독립적인 마이크로 트랜잭션(cancel_reservation)을 돌려 안전하게 처리합니다.
        $this->db->select('f_ReservationId');
        $this->db->where('f_Status', 'ED');
        $this->db->where('f_ExpiredAt <=', date('Y-m-d H:i:s'));
        $expired_list = $this->db->get(RS_INFO)->result_array();

        // Mdl_api에 이미 완벽하게 구축된 재고 복구 로직 재사용
        $count = 0;
        foreach ($expired_list as $row) {
            // 내부적으로 독립적인 트랜잭션과 비관적 락(Product 락)을 사용하여 동시성을 완벽히 방어함
            $success = $this->mdl_api->cancel_reservation($row['f_ReservationId'], 'EX');
            if ($success) {
                $count++;
            }
        }

        return $count;
    }
}
