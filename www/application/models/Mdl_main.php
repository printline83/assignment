<?php
class Mdl_main extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    public function get_payment_info($v_ReservationId)
    {
        $this->db->select('f_ReservationId, f_Amount, f_UserName, f_ProductName, f_Status');
        $this->db->from(RS_INFO);
        $this->db->where('f_ReservationId', $v_ReservationId);
        
        $a_res = $this->db->get()->row_array();
        
        return query_row($a_res);
    }
} // class Mdl_main
