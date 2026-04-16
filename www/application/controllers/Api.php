<?php

defined('BASEPATH') || exit('No direct script access allowed');

class Api extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('mdl_api');
    }

    public function _remap($method, $params = [])
    {
        $a_allowed_methods = [
            'products' => 'GET',
            'reserve'  => 'POST',
            'payment'  => 'POST'
        ];
    
        if (!method_exists($this, $method)) {
            return $this->_response(404, 'error', 'Endpoint Not Found');
        }
    
        $current_method = $_SERVER['REQUEST_METHOD'];
        if (isset($a_allowed_methods[$method]) && $a_allowed_methods[$method] !== $current_method) {
            return $this->_response(405, 'error', 'Method Not Allowed');
        }
    
        return call_user_func_array([$this, $method], $params);
    }
    
    // API 공통 응답 처리
    private function _response($code, $status, $data_or_msg)
    {
        $response = ['status' => $status];
        if ($status === 'success') {
            $response['data'] = $data_or_msg;
        } else {
            $response['message'] = $data_or_msg;
        }

        return $this->output
            ->set_status_header($code)
            ->set_content_type('application/json')
            ->set_output(json_encode($response));
    }

    // 상품 목록
    public function products()
    {
        $a_products = $this->mdl_api->get_products();

        return $this->_response(200, 'success', $a_products);
    }

    // 상품 정보
    public function product($v_ProductId)
    {
        $a_product = $this->mdl_api->get_product($v_ProductId);

        if (empty($a_product)) {
            return $this->_response(404, 'error', '상품을 찾을 수 없습니다.');
        }

        return $this->_response(200, 'success', $a_product);
    }

    // 예약 처리
    public function reserve()
    {
        $a_post['v_ProductId'] = $this->input->post('productId');
        $a_post['v_UserName']  = $this->input->post('userName');
        $a_post['v_UserPhone'] = $this->input->post('userPhone');

        if (!$a_post['v_ProductId'] || !$a_post['v_UserName'] || !$a_post['v_UserPhone']) {
            return $this->_response(400, 'error', '필수 항목이 누락되었습니다.');
        }

        $res = $this->mdl_api->create_reservation($a_post);

        if (!$res['status']) {
            return $this->_response(409, 'error', $res['message']); // 409 Conflict
        }

        return $this->_response(200, 'success', $res['data']);
    }

    // 결제창 진입 직전 유효성 검사
    public function check_validity()
    {
        $reservation_id = $this->input->post('reservation_id');
        if (!$reservation_id) {
            return $this->_response(400, 'error', '예약 번호가 없습니다.');
        }

        $isValid = $this->mdl_api->check_reservation_validity($reservation_id);
        if ($isValid) {
            return $this->_response(200, 'success', []);
        } else {
            return $this->_response(400, 'error', '결제 유효 시간이 이미 초과되었거나 결제할 수 없는 상태입니다.');
        }
    }

    // 결제 성공 콜백 (Toss Redirect)
    public function payment_success()
    {
        $this->load->library('session');
        $paymentKey = $this->input->get('paymentKey');
        $orderId    = $this->input->get('orderId');
        $amount     = $this->input->get('amount');

        if (!$paymentKey || !$orderId || !$amount) {
            $this->session->set_flashdata('payment_status', 'fail');
            $this->session->set_flashdata('payment_message', '잘못된 결제 접근입니다.');
            header('Location: /main/payment_result');
            exit;
        }

        $res = $this->mdl_api->confirm_payment($paymentKey, $orderId, $amount);

        if ($res['status']) {
            $this->session->set_flashdata('payment_status', 'success');
            $this->session->set_flashdata('reservation_id', $orderId);
        } else {
            $this->session->set_flashdata('payment_status', 'fail');
            $this->session->set_flashdata('payment_message', $res['message']);
        }
        header('Location: /main/payment_result');
        exit;
    }

    // 결제 실패 콜백 (Toss Redirect)
    public function payment_fail()
    {
        $this->load->library('session');
        $msg     = $this->input->get('message');
        $orderId = $this->input->get('orderId');

        if (!$msg) {
            $msg = '사용자 취소 또는 결제 에러가 발생했습니다.';
        }
        
        // 하드 에러의 경우 즉시 재고 복구를 위해 예약을 취소합니다.
        if ($orderId) {
            $this->mdl_api->cancel_reservation($orderId);
        }

        $this->session->set_flashdata('payment_status', 'fail');
        $this->session->set_flashdata('payment_message', htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'));
        header('Location: /main/payment_result');
        exit;
    }

    // 결제 포기 API (프론트에서 직접 호출)
    public function cancel_payment()
    {
        $reservation_id = $this->input->post('reservation_id');
        if ($reservation_id) {
            $this->mdl_api->cancel_reservation($reservation_id);
        }

        return $this->_response(200, 'success', []);
    }
}
