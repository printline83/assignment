<?php
defined('BASEPATH') || exit('No direct script access allowed');

class Cron extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model(['mdl_cron', 'mdl_api']);
    }

    // 10분 초과된 미결제 예약 일괄 취소 및 재고 자동 복구 (주기: 1~5분 권장)
    public function sweep_expired()
    {
        $result = $this->mdl_cron->process_expired();
        if ($result !== false) {
            echo 'Expired process complete. Count: '.$result."\n";
        } else {
            echo "Expired process failed due to DB error.\n";
        }
    }
}
