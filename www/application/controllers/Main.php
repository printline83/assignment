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
        $data['reservation_id'] = isset($_GET['reservation_id']) ? $_GET['reservation_id'] : '';
        if (!$data['reservation_id']) {
            alert('정상적인 접근이 아닙니다.');
        }
        $this->load->view('main/main_payment', $data);
    }
}
