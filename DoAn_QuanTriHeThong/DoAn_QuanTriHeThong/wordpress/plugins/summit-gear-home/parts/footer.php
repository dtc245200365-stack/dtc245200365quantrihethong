<?php
if (!defined('ABSPATH')) exit;
$privacy = function_exists('get_privacy_policy_url') ? get_privacy_policy_url() : '';
if (!$privacy) $privacy = sg_page_url_by_title('Chính sách bảo mật');
$returns = sg_page_url_by_title('Chính sách đổi trả hàng');
$links = [];
if ($privacy) $links[] = '<a href="' . esc_url($privacy) . '">Chính sách bảo mật</a>';
if ($returns) $links[] = '<a href="' . esc_url($returns) . '">Chính sách đổi trả hàng</a>';
?>
<footer class="sg-footer" id="lien-he">
  <div class="sg-wrap sg-footer__grid">
    <div>
      <div class="sg-logo sg-logo--light">▲ SUMMIT GEAR</div>
      <p class="sg-footer__slogan">Gear up for a greater tomorrow</p>
    </div>
    <div>
      <h4>Về chúng tôi</h4>
      <p>SUMMIT GEAR chuyên cung cấp các sản phẩm đồ leo núi, trekking và dã ngoại chất lượng cao, đáp ứng nhu cầu của người yêu thiên nhiên.</p>
    </div>
    <div>
      <h4>Thông tin liên hệ</h4>
      <p>📍 123 Đường Lâm Sơn, Đà Nẵng<br>📞 <a href="tel:0862968309">0862 968 309</a><br>✉ <a href="https://mail.google.com/mail/?view=cm&to=smg016@gmail.com" target="_blank" rel="noopener">smg016@gmail.com</a></p>
    </div>
    <div>
      <h4>Kết nối với chúng tôi</h4>
      <div class="sg-topbar__social"><a href="#">f</a><a href="#">ig</a><a href="#">yt</a></div>
    </div>
  </div>
  <div class="sg-wrap sg-footer__bottom">
    <span>© <?php echo date('Y'); ?> SUMMIT GEAR. All rights reserved.</span>
    <span class="sg-footer__links"><?php echo implode(' · ', $links); ?></span>
  </div>
</footer>
