<?php
/**
 * Plugin Name: Summit Gear Home
 * Description: Trang chủ SUMMIT GEAR viết bằng code + header/menu/footer dùng chung cho mọi trang + nút "Xem chi tiết".
 * Version: 2.7
 */
if (!defined('ABSPATH')) exit;
require_once __DIR__ . '/includes/contact-form.php';

define('SG_DIR', plugin_dir_path(__FILE__));
define('SG_URL', plugin_dir_url(__FILE__));

/* Các liên kết dùng chung: [trang chủ, giới thiệu, sản phẩm, liên hệ, trang bài viết] */
function sg_links() {
    $home    = home_url('/');
    $shop    = function_exists('wc_get_page_id') ? get_permalink(wc_get_page_id('shop')) : $home;
    $p_about = get_page_by_path('gioi-thieu');
    $p_ct    = get_page_by_path('lien-he');
    $about   = $p_about ? get_permalink($p_about) : home_url('/#gioi-thieu');
    $contact = $p_ct ? get_permalink($p_ct) : home_url('/#lien-he');
    $blog_id = (int) get_option('page_for_posts');
    $blog    = $blog_id ? get_permalink($blog_id) : home_url('/#kinh-nghiem');
    return [$home, $about, $shop, $contact, $blog];
}

/* Link tới trang ĐÃ XUẤT BẢN theo tiêu đề; trả về '' nếu chưa có hoặc còn là bản nháp */
function sg_page_url_by_title($title) {
    $q = new WP_Query([
        'post_type'      => 'page',
        'post_status'    => 'publish',
        'title'          => $title,
        'posts_per_page' => 1,
        'no_found_rows'  => true,
    ]);
    $url = $q->have_posts() ? get_permalink($q->posts[0]->ID) : '';
    wp_reset_postdata();
    return $url;
}

/* Lấy URL ảnh trong thư mục assets nếu có */
function sg_asset($file) {
    return file_exists(SG_DIR . 'assets/' . $file) ? SG_URL . 'assets/' . $file : '';
}

/* Trang chủ dùng template riêng */
add_filter('template_include', function ($template) {
    if (is_front_page()) {
        return SG_DIR . 'template.php';
    }
    return $template;
}, 99);

/* CSS + font nạp ở mọi trang phía người xem */
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('sg-fonts', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap', [], null);
    wp_enqueue_style('sg-home', SG_URL . 'assets/summit.css', [], filemtime(SG_DIR . 'assets/summit.css'));
});

/* Các trang khác: chèn header + footer của Summit Gear (trang chủ tự có trong template.php) */
add_action('wp_body_open', function () {
    if (is_front_page()) return;
    static $done = false;
    if ($done) return;
    $done = true;
    include SG_DIR . 'parts/header.php';
}, 1);

add_action('wp_footer', function () {
    if (is_front_page()) return;
    include SG_DIR . 'parts/footer.php';
}, 1);

/* Nút "Thêm vào giỏ hàng" -> "Xem chi tiết" */
add_filter('woocommerce_loop_add_to_cart_link', function ($html, $product) {
    return '<a href="' . esc_url(get_permalink($product->get_id())) . '" class="button">Xem chi tiết →</a>';
}, 10, 2);


/* ================= CHẾ ĐỘ CHỈ QUẢNG BÁ (KHÔNG BÁN HÀNG) ================= */

/* Đổi thành false nếu muốn ẩn luôn giá sản phẩm */
define('SG_SHOW_PRICE', false);

/* Không cho mua: mất nút "Thêm vào giỏ hàng" và chặn thêm vào giỏ ở phía máy chủ */
add_filter('woocommerce_is_purchasable', '__return_false');

/* Ẩn giá nếu cần */
if (!SG_SHOW_PRICE) {
    add_filter('woocommerce_get_price_html', '__return_empty_string');
}

/* Trang chi tiết sản phẩm: nút "Liên hệ tư vấn" thay cho nút mua */
add_action('woocommerce_single_product_summary', function () {
    [, , , $contact] = sg_links();
    echo '<p class="sg-consult"><a class="sg-btn" href="' . esc_url($contact) . '">Liên hệ tư vấn →</a></p>';
}, 30);

/* Giỏ hàng / thanh toán không còn dùng: chuyển về trang Sản phẩm */
add_action('template_redirect', function () {
    if (!function_exists('is_cart')) return;
    if (is_cart() || is_checkout()) {
        [, , $shop] = sg_links();
        wp_safe_redirect($shop);
        exit;
    }
});
