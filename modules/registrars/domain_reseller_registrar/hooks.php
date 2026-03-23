<?php

add_hook('AdminAreaHeaderOutput', 1, function($vars) {

    $dir = __DIR__;
    $base_path = $_SERVER['DOCUMENT_ROOT'];
    $relative_dir = str_replace($base_path, '', $dir);
    $css = $relative_dir . '/css/style.css';

    return '<link rel="stylesheet" href="'.$css.'" />';

});