<?php
if ( ! defined('ABSPATH') ) exit;

class EGBP_Shortcode {

  private static $enqueued = false;

  public static function register(): void {
    add_shortcode('gbp_reviews', [__CLASS__, 'render']);
  }

  public static function render($atts): string {
    $opts = get_option(EGBP_OPT_KEY, []);
    $atts = shortcode_atts([
      'title'    => $opts['shortcode_title']    ?? '',
      'subtitle' => $opts['shortcode_subtitle'] ?? '',
      'limit'    => 10,
      'order'    => 'DESC',
    ], $atts, 'gbp_reviews');

    $query = new WP_Query([
      'post_type'              => 'reviews',
      'post_status'            => 'publish',
      'orderby'                => 'date',
      'order'                  => strtoupper($atts['order']) === 'ASC' ? 'ASC' : 'DESC',
      'posts_per_page'         => min(200, max(1, (int)$atts['limit'])),
      'no_found_rows'          => true,
      'update_post_meta_cache' => false,
      'update_post_term_cache' => false,
    ]);

    if (!$query->have_posts()) return '';

    self::maybe_enqueue();

    $args = [
      'title'    => $atts['title'],
      'subtitle' => $atts['subtitle'],
      'query'    => $query,
    ];

    ob_start();
    include EGBP_TEMPLATE_DIR . '/reviews.php';
    return ob_get_clean();
  }

  private static function maybe_enqueue(): void {
    if (self::$enqueued) return;
    self::$enqueued = true;

    wp_enqueue_style('egbp-reviews', EGBP_URL . 'assets/css/reviews.min.css', [], EGBP_VER);
    wp_style_add_data('egbp-reviews', 'media', 'print');
    add_filter('style_loader_tag', [__CLASS__, 'defer_style'], 10, 2);

    wp_enqueue_script('egbp-reviews', EGBP_URL . 'assets/js/reviews.js', [], EGBP_VER, [
      'in_footer' => true,
      'strategy'  => 'defer',
    ]);
  }

  public static function defer_style(string $tag, string $handle): string {
    if ($handle !== 'egbp-reviews') return $tag;
    return str_replace("media='print'", "media='print' onload=\"this.media='all'\"", $tag);
  }
}
