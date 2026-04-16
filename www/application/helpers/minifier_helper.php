<?php

defined('BASEPATH') || exit('No direct script access allowed');

function minifier()
{
    require APPPATH.'third_party/minifier/lessc.inc.php';
    $minify = new lessc();

    // Less
    $minify->compileFile(FCPATH.'www/assets/css/style.less', FCPATH.'www/assets/css/style.css', FCPATH.'www/assets/css/style.min.css');

    // js
    $minify->new_jsMinify(FCPATH.'www/assets/js/script.js', FCPATH.'www/assets/js/script.min.js');
}
