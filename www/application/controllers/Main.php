<?php

defined('BASEPATH') || exit('No direct script access allowed');

class Main extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        minifier();
        $this->load->model('mdl_main');
    }

    public function _remap($method, $params = [])
    {
        if (!method_exists($this, $method)) {
            alert('잘못된 접근입니다.');
        }
        if ($this->input->is_ajax_request()) {
            $a_post = $this->input->post();
            if (empty($a_post)) {
                echo json_encode(['msg' => '데이터가 없습니다.', 'state' => 'error']);
                exit;
            }
            $this->{"{$method}"}($params, $a_post);
        } else {
            switch ($method) {
                case 'index':
                    $data['a_css']      = '';
                    $data['a_script']   = '';
                    $data['page_title'] = '';
     
                    break;
                    
                default:
                    $data['page_title'] = '';

                    break;
            }

            // Security
            $data['tknm']       = $this->security->get_csrf_token_name();
            $data['tkhs']       = $this->security->get_csrf_hash();
            $this->load->view('inc/header', $data);
            $this->{"{$method}"}($params);
            $this->load->view('inc/footer');
        }
    }

    public function index()
    {
        $this->products();
    }

    public function products()
    {
        $this->load->view('main/main_products');
    }

    public function reserve($params = [])
    {
        $data['product_id'] = isset($params[0]) ? $params[0] : '';
        if (!$data['product_id']) {
            alert('상품 정보가 없습니다.');
        }
        $this->load->view('main/main_reserve', $data);
    }

    public function payment()
    {
        $v_ReservationId = isset($_GET['reservation_id']) ? $_GET['reservation_id'] : '';
        if (!$v_ReservationId) {
            alert('정상적인 접근이 아닙니다.');
        }

        $a_info = $this->mdl_main->get_payment_info($v_ReservationId);
        
        if (empty($a_info)) {
            alert('예약 정보를 찾을 수 없습니다.', '/main/products');
        }

        if ($a_info['Status'] === 'CF') {
            alert('이미 결제가 완료된 예약건입니다.', '/main/products');
        } elseif ($a_info['Status'] === 'EX') {
            alert('결제가 취소되었거나 만료된 예약입니다.', '/main/products');
        } elseif ($a_info['Status'] !== 'ED') {
            alert('현재 결제를 진행할 수 없는 상태입니다.', '/main/products');
        }

        $data['reservation_id'] = $a_info['ReservationId'];
        $data['amount']         = $a_info['Amount'];
        $data['order_name']     = $a_info['ProductName'] ? $a_info['ProductName'] : '상품 예약결제';
        $data['user_name']      = $a_info['UserName'];

        $this->load->view('main/main_payment', $data);
    }

    public function payment_result()
    {
        $this->load->library('session');
        $data['status'] = $this->session->flashdata('payment_status');
        $data['reservation_id'] = $this->session->flashdata('reservation_id');
        $data['message'] = $this->session->flashdata('payment_message');
        
        if (!$data['status']) {
            alert('비정상적인 접근이거나 이미 처리가 완료된 요청입니다.', '/main/products');
        }
        
        $this->load->view('main/main_payment_result', $data);
    }
}
