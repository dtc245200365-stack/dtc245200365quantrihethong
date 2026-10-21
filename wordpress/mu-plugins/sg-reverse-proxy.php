<?php
/**
 * Plugin Name: SG Reverse Proxy
 * Description: WordPress chạy sau Nginx (HTTPS). Nhận biết HTTPS qua X-Forwarded-Proto và lấy địa chỉ site từ biến môi trường PUBLIC_URL.
 */
if (!defined('ABSPATH')) exit;

if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
    $_SERVER['HTTPS'] = 'on';
}

$sg_public = getenv('PUBLIC_URL');
if ($sg_public) {
    $sg_public = rtrim($sg_public, '/');
    add_filter('pre_option_home',    function () use ($sg_public) { return $sg_public; }, 99);
    add_filter('pre_option_siteurl', function () use ($sg_public) { return $sg_public; }, 99);
}
