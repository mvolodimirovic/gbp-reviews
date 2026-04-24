<?php
if ( ! defined('ABSPATH') ) exit;

/** @var array $args  { title, subtitle, query } */
/** @var WP_Query $query */
$title    = $args['title']    ?? '';
$subtitle = $args['subtitle'] ?? '';
$query    = $args['query']    ?? null;

if (!$query || !$query->have_posts()) return;

$label_attr = $title
  ? 'aria-labelledby="gbp-reviews-title"'
  : 'aria-label="' . esc_attr__('Reviews', 'velvetylab-gbp-reviews') . '"';
?>
<section class="reviews section" <?php echo $label_attr; ?>>
  <div class="wrapper reviews__wrapper container">
    <?php if ($title || $subtitle): ?>
      <div class="reviews__header section-header">
        <?php if ($title): ?>
          <h2 id="gbp-reviews-title" class="reviews__title"><?php echo esc_html($title); ?></h2>
        <?php endif; ?>
        <?php if ($subtitle): ?>
          <p class="reviews__subtitle"><?php echo wp_kses($subtitle, ['br' => []]); ?></p>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <div class="reviews__slider reviews-swiper">
      <div class="swiper-wrapper">
        <?php while ($query->have_posts()): $query->the_post();
          include __DIR__ . '/card.php';
        endwhile;
        wp_reset_postdata(); ?>
      </div>
    </div>

    <div class="reviews-progress"></div>
  </div>
</section>
