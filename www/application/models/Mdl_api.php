<?php
class Mdl_api extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    public function get_products()
    {
        $res = $this->db->get_where(PD_INFO, [
            'f_Delete' => 'N',
        ])->result_array();

        return query_array($res);
    }

    public function get_product($id)
    {
        $res = $this->db->get_where(PD_INFO, [
            'f_Delete'    => 'N',
            'f_ProductId' => $id,
            'f_Stock >'   => 0,
        ])->row_array();

        return query_row($res);
    }
} // class Mdl_api
