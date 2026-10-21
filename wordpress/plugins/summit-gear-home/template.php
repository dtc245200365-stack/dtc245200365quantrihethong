<?php
if (!defined('ABSPATH')) exit;

/* ---------- Dữ liệu dùng chung ---------- */
[$home, $about, $shop, $contact, $blog] = sg_links();

$hero = get_theme_mod('hestia_big_title_background');
if (!$hero) $hero = get_the_post_thumbnail_url(get_option('page_on_front'), 'full');
if (!$hero) $hero = sg_asset('hero.jpg');

$brand_img = sg_asset('brand.jpg') ?: $hero;
$quote_img = sg_asset('quote.jpg') ?: $hero;

$cats = [
    ['Leo núi',            'Chinh phục độ cao'],
    ['Cắm trại',           'Tận hưởng thiên nhiên'],
    ['Giày trekking',      'Vững bước mọi địa hình'],
    ['Balo',               'Đồng hành trên mọi hành trình'],
    ['Trang phục outdoor', 'Thoải mái và bền bỉ'],
    ['Phụ kiện',           'Hỗ trợ tối đa cho chuyến đi'],
];
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class('sg-home'); ?>>
<?php wp_body_open(); ?>

<?php include SG_DIR . 'parts/header.php'; ?>

<!-- Hero -->
<section class="sg-hero" style="<?php echo $hero ? 'background-image:url(' . esc_url($hero) . ')' : ''; ?>">
  <div class="sg-wrap">
    <div class="sg-hero__box">
      <span class="sg-eyebrow">SUMMIT GEAR</span>
      <h1>Đồng hành cùng bạn trên mọi cung đường</h1>
      <p>Khám phá thiết bị leo núi, trekking, cắm trại và dã ngoại chất lượng cao dành cho những người yêu thiên nhiên.</p>
      <a class="sg-btn sg-btn--accent" href="<?php echo esc_url($shop); ?>">Khám phá sản phẩm →</a>
    </div>
  </div>
</section>

<!-- Danh mục -->
<section class="sg-section sg-cats">
  <div class="sg-wrap sg-cats__grid">
    <?php foreach ($cats as $c) :
        $term = taxonomy_exists('product_cat') ? get_term_by('name', $c[0], 'product_cat') : false;
        $link = ($term && !is_wp_error($term)) ? get_term_link($term) : $shop;
        $img  = '';
        if ($term) {
            $tid = get_term_meta($term->term_id, 'thumbnail_id', true);
            if ($tid) $img = wp_get_attachment_image_url($tid, 'large');
        }
    ?>
    <a class="sg-cat" href="<?php echo esc_url($link); ?>" style="<?php echo $img ? 'background-image:url(' . esc_url($img) . ')' : ''; ?>">
      <span class="sg-cat__txt"><strong><?php echo esc_html($c[0]); ?></strong><small><?php echo esc_html($c[1]); ?></small></span>
    </a>
    <?php endforeach; ?>
  </div>
</section>

<!-- Sản phẩm tiêu biểu -->
<section class="sg-section">
  <div class="sg-wrap">
    <div class="sg-head">
      <div>
        <h2 class="sg-title">Sản phẩm tiêu biểu</h2>
        <p class="sg-sub">Những sản phẩm được yêu thích và tin dùng bởi cộng đồng yêu trekking.</p>
      </div>
      <a class="sg-more" href="<?php echo esc_url($shop); ?>">Xem tất cả sản phẩm →</a>
    </div>
    <div class="sg-products">
      <?php
      if (post_type_exists('product')) {
          $args = ['post_type' => 'product', 'posts_per_page' => 5, 'post_status' => 'publish',
                   'tax_query' => [['taxonomy' => 'product_visibility', 'field' => 'name', 'terms' => 'featured']]];
          $q = new WP_Query($args);
          if (!$q->have_posts()) { unset($args['tax_query']); $q = new WP_Query($args); }
          while ($q->have_posts()) : $q->the_post();
              $product = wc_get_product(get_the_ID());
              $desc = wp_trim_words(wp_strip_all_tags(get_the_excerpt()), 8, '…');
      ?>
        <article class="sg-card">
          <a class="sg-card__img" href="<?php the_permalink(); ?>"><?php echo get_the_post_thumbnail(get_the_ID(), 'woocommerce_single'); ?></a>
          <div class="sg-card__body">
            <h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
            <?php if ($desc) : ?><p class="sg-card__desc"><?php echo esc_html($desc); ?></p><?php endif; ?>
            <?php if ($product) : ?><p class="sg-card__price"><?php echo wp_kses_post($product->get_price_html()); ?></p><?php endif; ?>
            <a class="sg-btn sg-btn--sm" href="<?php the_permalink(); ?>">Xem chi tiết →</a>
          </div>
        </article>
      <?php endwhile; wp_reset_postdata(); } ?>
    </div>
  </div>
</section>

<!-- Giới thiệu + Kinh nghiệm -->
<section class="sg-section sg-alt">
  <div class="sg-wrap sg-duo">
    <div class="sg-brand" id="gioi-thieu">
      <div class="sg-brand__txt">
        <span class="sg-eyebrow sg-eyebrow--dark">Tiêu chuẩn &amp; chất lượng</span>
        <h2 class="sg-brand__name">SUMMIT GEAR</h2>
        <p class="sg-brand__lead">Không chỉ là trang bị, mà còn là người bạn đồng hành.</p>
        <p>Chúng tôi mang đến những sản phẩm outdoor chất lượng cao, được tuyển chọn kỹ lưỡng từ các thương hiệu uy tín, giúp bạn an tâm khám phá và chinh phục mọi hành trình.</p>
        <a class="sg-btn sg-btn--sm" href="<?php echo esc_url($about); ?>">Tìm hiểu thêm →</a>
      </div>
      <div class="sg-brand__img" style="<?php echo $brand_img ? 'background-image:url(' . esc_url($brand_img) . ')' : ''; ?>"></div>
    </div>

    <div class="sg-blog" id="kinh-nghiem">
      <div class="sg-head sg-head--sm">
        <h2 class="sg-title sg-title--sm">Kinh nghiệm trekking</h2>
        <a class="sg-more" href="<?php echo esc_url($blog); ?>">Xem tất cả →</a>
      </div>
      <p class="sg-sub">Những bài viết hữu ích giúp bạn chuẩn bị tốt hơn cho hành trình của mình.</p>
      <div class="sg-posts">
        <?php
        $pq = new WP_Query(['post_type' => 'post', 'posts_per_page' => 3, 'post_status' => 'publish', 'ignore_sticky_posts' => true]);
        if ($pq->have_posts()) : while ($pq->have_posts()) : $pq->the_post(); ?>
          <a class="sg-post" href="<?php the_permalink(); ?>">
            <span class="sg-post__img"><?php if (has_post_thumbnail()) the_post_thumbnail('medium_large'); ?></span>
            <span class="sg-post__body">
              <strong><?php the_title(); ?></strong>
              <small><?php echo esc_html(get_the_date('j') . ' Tháng ' . get_the_date('n') . ', ' . get_the_date('Y')); ?></small>
            </span>
          </a>
        <?php endwhile; wp_reset_postdata(); else : ?>
          <p class="sg-sub">Chưa có bài viết nào. Hãy thêm bài ở mục Bài viết.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<!-- Câu trích dẫn -->
<section class="sg-quote" id="thuong-hieu" style="<?php echo $quote_img ? 'background-image:url(' . esc_url($quote_img) . ')' : ''; ?>">
  <div class="sg-quote__in">
    <p>“Cùng nhau, chúng ta khám phá những giới hạn mới.”</p>
    <span>— Cộng đồng SUMMIT GEAR —</span>
  </div>
</section>

<?php include SG_DIR . 'parts/footer.php'; ?>

<?php wp_footer(); ?>
</body>
</html>
