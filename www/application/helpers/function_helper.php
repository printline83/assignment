<?php
defined('BASEPATH') || exit('No direct script access allowed');

// 환경접근함수
if (!function_exists('env')) {
    function env($key, $default = null)
    {
        static $env = null;
  
        if ($env === null) {
            $env = parse_ini_file(FCPATH.'.env');
        }
  
        return isset($env[$key]) ? $env[$key] : $default;
    }
}

// array 출력
if (!function_exists('print_a')) {
    function print_a($p_ary)
    {
        echo '<font color="orangered">';
        if (is_array($p_ary)) {
            echo '<xmp>',print_r($p_ary).'</xmp>';
        } else {
            echo $p_ary;
        }
        echo '</font>';
    }
}

// 암호화/복호화
if (!function_exists('rc4crypt')) {
    function rc4crypt($txt, $mode = 1)
    {
        $key = 'clinetwebsitecryptcodedata';
        $tmp = '';
        if (!$mode) {
            $txt = base64_decode($txt);
        }
        $ctr = 0;
        $cnt = strlen($txt);
        $len = strlen($key);
        for ($i = 0; $i < $cnt; $i++) {
            if ($ctr == $len) {
                $ctr = 0;
            }
            $tmp .= substr($txt, $i, 1) ^ substr($key, $ctr, 1);
            $ctr++;
        }
        $tmp = ($mode) ? base64_encode($tmp) : $tmp;

        return $tmp;
    }
}

if (!function_exists('alert')) {
    function alert($msg = '', $url = '')
    {
        $CI = &get_instance();
        if (!$msg) {
            $msg = '잘못된 접근입니다.(404)';
        }
        echo '<meta http-equiv="content-type" content="text/html; charset='.$CI->config->item('charset').'">';
        echo "<script type='text/javascript'>alert('".$msg."');";
        if ($url) {
            echo "location.replace('".$url."');";
        } else {
            echo 'history.go(-1);';
        }
        echo '</script>';
        exit;
    }
}

// f_ 제거 , 디코딩 row
if (!function_exists('query_row')) {
    function query_row($p_ary)
    {
        $res = null;
        if (is_array($p_ary)) {
            foreach ($p_ary as $k => $v) {
                if (substr($k, -4) == '_enc') {
                    $p       = substr($k, 2, -4);
                    $res[$p] = rc4crypt($v, 0);
                } else {
                    $p       = substr($k, 2);
                    $res[$p] = $v;
                }
            }
        }

        return $res;
    }
}

// f_ 제거 , 디코딩 array
if (!function_exists('query_array')) {
    function query_array($p_ary)
    {
        $a_res = null;
        if (!empty($p_ary) && is_array($p_ary)) {
            for ($i = 0; $i < count($p_ary); $i++) {
                foreach ($p_ary[$i] as $k => $v) {
                    // $res['index'] = $i + 1;
                    if (substr($k, -4) == '_enc') {
                        $p       = substr($k, 2, -4);
                        $res[$p] = rc4crypt($v, 0);
                    } else {
                        $p       = substr($k, 2);
                        $res[$p] = $v;
                    }
                }
                $a_res[$i] = $res;
            }
        }

        return $a_res;
    }
}

// array 출력
if (!function_exists('print_a')) {
    function print_a($p_ary)
    {
        echo '<pre style="color: orangered;">';

        if (empty($p_ary)) {
            echo '데이터 없음';
        } elseif (is_array($p_ary) || is_object($p_ary)) {
            print_r($p_ary);
        } else {
            echo $p_ary;
        }

        echo '</pre>';
    }
}

// 콤마 추가
if (!function_exists('comma')) {
    function comma($number)
    {
        if (!empty($number)) {
            $int = @number_format($number);
        } else {
            $int = 0;
        }

        return $int;
    }
}

// 콤마 제거
if (!function_exists('uncomma')) {
    function uncomma($number)
    {
        if (!empty($number)) {
            if (strpos($number, '.') !== false) {
                $int = $number;
            } else {
                $int = (int)str_replace(',', '', $number);
            }
        } else {
            $int = 0;
        }

        return $int;
    }
}
