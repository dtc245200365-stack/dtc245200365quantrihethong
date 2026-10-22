<?php
if (!defined('ABSPATH')) exit;

/* ===== FORM LIÊN HỆ: lưu tin nhắn vào CSDL, xem ở menu "Tin nhắn liên hệ" ===== */

add_action('init', function () {
    register_post_type('sg_message', [
        'labels' => ['name' => 'Tin nhắn liên hệ', 'singular_name' => 'Tin nhắn', 'menu_name' => 'Tin nhắn liên hệ'],
        'public' => false,
        'show_ui' => true,
        'menu_icon' => 'dashicons-email-alt',
        'supports' => ['title'],
        'capabilities' => ['create_posts' => 'do_not_allow'],
        'map_meta_cap' => true,
    ]);
});

/* Shortcode: [sg_contact_form] */
add_shortcode('sg_contact_form', function () {
    $status = isset($_GET['sg_status']) ? sanitize_key($_GET['sg_status']) : '';
    ob_start();
    echo '<div id="sg-contact">';
    if ($status === 'ok') {
        echo '<div class="sg-form__notice sg-form__notice--ok">Cảm ơn bạn! SUMMIT GEAR đã nhận được lời nhắn và sẽ phản hồi sớm.</div>';
    } elseif ($status === 'invalid') {
        echo '<div class="sg-form__notice sg-form__notice--err">Vui lòng nhập đủ họ tên, email hợp lệ và nội dung.</div>';
    } elseif ($status === 'wait') {
        echo '<div class="sg-form__notice sg-form__notice--err">Bạn gửi hơi nhanh, vui lòng thử lại sau ít giây.</div>';
    }
    ?>
    <form class="sg-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
      <input type="hidden" name="action" value="sg_contact">
      <?php wp_nonce_field('sg_contact', 'sg_nonce'); ?>
      <p style="position:absolute;left:-9999px" aria-hidden="true">
        <label>Để trống <input type="text" name="sg_website" tabindex="-1" autocomplete="off"></label>
      </p>
      <p><label for="sg_name">Họ và tên *</label>
        <input id="sg_name" type="text" name="sg_name" maxlength="100" required></p>
      <p><label for="sg_email">Email *</label>
        <input id="sg_email" type="email" name="sg_email" maxlength="100" required></p>
      <p><label for="sg_phone">Số điện thoại</label>
        <input id="sg_phone" type="tel" name="sg_phone" maxlength="20"></p>
      <p><label for="sg_msg">Nội dung *</label>
        <textarea id="sg_msg" name="sg_msg" rows="5" maxlength="2000" required></textarea></p>
      <p><button type="submit" class="sg-form__btn">Gửi lời nhắn</button></p>
    </form>
    <?php
    echo '</div>';
    return ob_get_clean();
});

function sg_contact_handle() {
    $back = wp_get_referer() ?: home_url('/');
    $back = remove_query_arg('sg_status', $back);
    $go = function ($s) use ($back) {
        wp_safe_redirect(add_query_arg('sg_status', $s, $back) . '#sg-contact');
        exit;
    };

    if (!isset($_POST['sg_nonce']) || !wp_verify_nonce($_POST['sg_nonce'], 'sg_contact')) $go('invalid');
    if (!empty($_POST['sg_website'])) $go('ok'); /* bot điền ô ẩn: im lặng bỏ qua */

    $ip  = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    $key = 'sg_rl_' . md5($ip);
    if (get_transient($key)) $go('wait');

    $name  = sanitize_text_field(wp_unslash($_POST['sg_name'] ?? ''));
    $email = sanitize_email(wp_unslash($_POST['sg_email'] ?? ''));
    $phone = sanitize_text_field(wp_unslash($_POST['sg_phone'] ?? ''));
    $msg   = sanitize_textarea_field(wp_unslash($_POST['sg_msg'] ?? ''));
    if ($name === '' || $msg === '' || !is_email($email)) $go('invalid');

    $id = wp_insert_post([
        'post_type'   => 'sg_message',
        'post_status' => 'publish',
        'post_title'  => mb_substr($name . ' – ' . $msg, 0, 60),
        'post_content'=> $msg,
    ]);
    if ($id && !is_wp_error($id)) {
        update_post_meta($id, 'sg_name', $name);
        update_post_meta($id, 'sg_email', $email);
        update_post_meta($id, 'sg_phone', $phone);
        set_transient($key, 1, 30);
        /* Báo qua email cho admin; ở localhost sẽ không gửi được nhưng tin nhắn vẫn nằm trong CSDL */
        @wp_mail(get_option('admin_email'), 'Liên hệ mới từ ' . $name,
            "Họ tên: $name\nEmail: $email\nĐiện thoại: $phone\n\n$msg", ['Reply-To: ' . $email]);
        $go('ok');
    }
    $go('invalid');
}
add_action('admin_post_nopriv_sg_contact', 'sg_contact_handle');
add_action('admin_post_sg_contact', 'sg_contact_handle');

/* Trang quản trị: cột và hộp thông tin */
add_filter('manage_sg_message_posts_columns', function () {
    return ['cb' => '<input type="checkbox">', 'title' => 'Tin nhắn', 'sg_from' => 'Người gửi', 'sg_phone' => 'Điện thoại', 'date' => 'Ngày gửi'];
});
add_action('manage_sg_message_posts_custom_column', function ($col, $id) {
    if ($col === 'sg_from') echo esc_html(get_post_meta($id, 'sg_name', true)) . '<br>' . esc_html(get_post_meta($id, 'sg_email', true));
    if ($col === 'sg_phone') echo esc_html(get_post_meta($id, 'sg_phone', true));
}, 10, 2);
add_action('add_meta_boxes', function () {
    add_meta_box('sg_msg_box', 'Nội dung liên hệ', function ($post) {
        echo '<p><strong>Họ tên:</strong> ' . esc_html(get_post_meta($post->ID, 'sg_name', true)) . '</p>';
        echo '<p><strong>Email:</strong> ' . esc_html(get_post_meta($post->ID, 'sg_email', true)) . '</p>';
        echo '<p><strong>Điện thoại:</strong> ' . esc_html(get_post_meta($post->ID, 'sg_phone', true)) . '</p>';
        echo '<p><strong>Nội dung:</strong></p><div style="white-space:pre-wrap">' . esc_html($post->post_content) . '</div>';
    }, 'sg_message', 'normal', 'high');
});
