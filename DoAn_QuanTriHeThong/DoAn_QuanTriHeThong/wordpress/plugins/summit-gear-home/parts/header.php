<?php
if (!defined('ABSPATH')) exit;
[$home, $about, $shop, $contact, $blog] = sg_links();
$is_shop = function_exists('is_woocommerce') && is_woocommerce();
?>
<div class="sg-topbar">
  <div class="sg-wrap sg-topbar__in">
    <div class="sg-topbar__info">
      <span>📍 123 Đường Lâm Sơn, Đà Nẵng</span>
      <span>📞 <a href="tel:0862968309">0862 968 309</a></span>
      <span>✉ <a href="https://mail.google.com/mail/?view=cm&to=smg016@gmail.com" target="_blank" rel="noopener">smg016@gmail.com</a></span>
    </div>
    <div class="sg-topbar__right">
   
      <span class="sg-topbar__social"><a href="#">f</a><a href="#">ig</a><a href="#">yt</a></span>
    </div>
  </div>
</div>
<header class="sg-header">
  <div class="sg-wrap sg-header__in">
    <a class="sg-logo" href="<?php echo esc_url($home); ?>">▲ SUMMIT GEAR</a>
    <nav class="sg-nav">
      <a class="<?php echo is_front_page() ? 'is-active' : ''; ?>" href="<?php echo esc_url($home); ?>">Trang chủ</a>
      <a class="<?php echo is_page('gioi-thieu') ? 'is-active' : ''; ?>" href="<?php echo esc_url($about); ?>">Giới thiệu</a>
      <a class="<?php echo $is_shop ? 'is-active' : ''; ?>" href="<?php echo esc_url($shop); ?>">Sản phẩm</a>
      <a href="<?php echo esc_url(home_url('/#thuong-hieu')); ?>">Thương hiệu</a>
      <a class="<?php echo (is_home() || is_singular('post') || is_category()) ? 'is-active' : ''; ?>" href="<?php echo esc_url($blog); ?>">Kinh nghiệm trekking</a>
      <a class="<?php echo is_page('lien-he') ? 'is-active' : ''; ?>" href="<?php echo esc_url($contact); ?>">Liên hệ</a>
    </nav>
  </div>
</header>
