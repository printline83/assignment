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
        $a_post['v_UserPhone'] = uncomma($this->input->post('userPhone'));

        if (!$a_post['v_ProductId'] || !$a_post['v_UserName'] || !$a_post['v_UserPhone']) {
            return $this->_response(400, 'error', '필수 항목이 누락되었습니다.');
        }

        $res = $this->mdl_api->create_reservation($a_post);

        if (!$res['status']) {
            return $this->_response(409, 'error', $res['message']); // 409 Conflict
        }

        return $this->_response(200, 'success', $res['data']);
    }
}
