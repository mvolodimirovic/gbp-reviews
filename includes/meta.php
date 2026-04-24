<?php
if ( ! defined('ABSPATH') ) exit;

class EGBP_Meta {

  public static function register(): void {
    register_post_meta('reviews', 'rating', [
      'type'              => 'integer',
      'single'            => true,
      'default'           => 0,
      'sanitize_callback' => function($v) { return max(0, min(5, (int)$v)); },
      'auth_callback'     => function() { return current_user_can('edit_posts'); },
      'show_in_rest'      => false,
    ]);

    register_post_meta('reviews', 'gbp_review_id', [
      'type'          => 'string',
      'single'        => true,
      'default'       => '',
      'sanitize_callback' => 'sanitize_text_field',
      'auth_callback' => function() { return current_user_can('edit_posts'); },
      'show_in_rest'  => false,
    ]);
  }

  public static function add_meta_box(): void {
    add_meta_box(
      'egbp_review_meta',
      __('Review details', 'velvetylab-gbp-reviews'),
      [__CLASS__, 'render_meta_box'],
      'reviews',
      'side',
      'high'
    );
  }

  public static function render_meta_box(\WP_Post $post): void {
    $rating    = (int) get_post_meta($post->ID, 'rating', true);
    $review_id = get_post_meta($post->ID, 'gbp_review_id', true);
    wp_nonce_field('egbp_save_meta', 'egbp_meta_nonce');
    ?>
    <div class="egbp-metabox">
      <p>
        <label for="egbp_rating"><strong><?php esc_html_e('Rating (1–5)', 'velvetylab-gbp-reviews'); ?></strong></label><br>
        <input type="number" id="egbp_rating" name="egbp_rating"
               value="<?php echo esc_attr($rating ?: ''); ?>"
               min="1" max="5" step="1" style="width:60px;margin-top:4px">
      </p>
      <?php if ($review_id): ?>
        <p style="margin-top:8px">
          <strong><?php esc_html_e('Google Review ID', 'velvetylab-gbp-reviews'); ?></strong><br>
          <code style="font-size:11px;word-break:break-all"><?php echo esc_html($review_id); ?></code>
        </p>
      <?php endif; ?>
    </div>
    <?php
  }

  public static function save_meta_box(int $post_id): void {
    if (
      ! isset($_POST['egbp_meta_nonce']) ||
      ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['egbp_meta_nonce'])), 'egbp_save_meta')
    ) return;

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (! current_user_can('edit_post', $post_id)) return;
    if (get_post_type($post_id) !== 'reviews') return;

    if (isset($_POST['egbp_rating'])) {
      $rating = max(0, min(5, (int)$_POST['egbp_rating']));
      update_post_meta($post_id, 'rating', $rating);
    }
  }
}
