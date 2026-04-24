<?php
if ( ! defined('ABSPATH') ) exit;

$post_id  = $args['post_id'] ?? get_the_ID();
$title    = get_the_title($post_id);
$date     = get_the_date('d.m.Y', $post_id);
$content  = apply_filters('the_content', get_post_field('post_content', $post_id));
$rating   = egbp_get_rating($post_id);
$thumb_id = get_post_thumbnail_id($post_id);
?>
<div class="reviews__item swiper-slide">
  <div class="reviews__item__head">
    <?php if ($rating): ?>
      <div class="reviews__item__rating">
        <div class="stars-clip" style="--rating:<?php echo esc_attr($rating); ?>" aria-label="<?php echo esc_attr(sprintf(__('%d out of 5', 'velvetylab-gbp-reviews'), $rating)); ?>" role="img"></div>
        <div class="rating-num"><?php echo esc_html($rating); ?><span>/5</span></div>
      </div>
    <?php endif; ?>
  </div>
  <?php if ($content): ?>
    <div class="reviews__item__text">
      <?php echo wp_kses_post($content); ?>
    </div>
    <button type="button"
            class="reviews__item-btn egbp-read-more"
            hidden
            aria-expanded="false"
            data-more="<?php esc_attr_e('Read more', 'velvetylab-gbp-reviews'); ?>"
            data-less="<?php esc_attr_e('Show less', 'velvetylab-gbp-reviews'); ?>">
      <?php esc_html_e('Read more', 'velvetylab-gbp-reviews'); ?>
    </button>
  <?php endif; ?>
  <div class="reviews__item__info">
    <div class="reviews__item__image">
      <?php echo egbp_author_image((int)$thumb_id, $title); ?>
    </div>
    <span class="reviews__item__name"><?php echo esc_html($title); ?></span>
    <time class="reviews__item__date" datetime="<?php echo esc_attr(get_the_date('Y-m-d', $post_id)); ?>">
      <?php echo esc_html($date); ?>
    </time>
  </div>
</div>
