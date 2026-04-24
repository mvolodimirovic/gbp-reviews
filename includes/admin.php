<?php
if ( ! defined('ABSPATH') ) exit;

class EGBP_Admin {

  private static $hook = '';

  public static function register_menu() {
    $parent_slug = 'edit.php?post_type=reviews';
    self::$hook = add_submenu_page(
      $parent_slug,
      __('Import Reviews from Google', 'velvetylab-gbp-reviews'),
      __('Import Reviews from Google', 'velvetylab-gbp-reviews'),
      EGBP_CAP,
      EGBP_SLUG,
      [__CLASS__, 'render_page'],
      30
    );

    add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue']);
  }

  public static function handle_save(): void {
    if ( ! current_user_can(EGBP_CAP) ) wp_die(__('Insufficient permissions.', 'velvetylab-gbp-reviews'));
    check_admin_referer('egbp_save_settings');

    $raw  = isset($_POST[EGBP_OPT_KEY]) && is_array($_POST[EGBP_OPT_KEY]) ? $_POST[EGBP_OPT_KEY] : [];
    $opts = self::sanitize_options($raw);
    update_option(EGBP_OPT_KEY, $opts, false);

    $page_url = add_query_arg([
      'post_type' => 'reviews',
      'page'      => EGBP_SLUG,
      'saved'     => '1',
    ], admin_url('edit.php'));

    wp_safe_redirect($page_url);
    exit;
  }

  private static function sanitize_options(array $opts): array {
    $out = [];

    $allowed_sources  = ['gbp_v4', 'places_api'];
    $out['source']    = in_array($opts['source'] ?? 'gbp_v4', $allowed_sources, true)
                        ? $opts['source'] : 'gbp_v4';

    $out['client_id']     = sanitize_text_field($opts['client_id']     ?? '');
    $out['client_secret'] = sanitize_text_field($opts['client_secret'] ?? '');
    $out['refresh_token'] = sanitize_text_field($opts['refresh_token'] ?? '');
    $out['account_id']    = preg_replace('~\D~', '', $opts['account_id']  ?? '');
    $out['location_id']   = preg_replace('~\D~', '', $opts['location_id'] ?? '');
    $order_by             = sanitize_text_field($opts['order_by'] ?? 'updateTime desc');
    $out['order_by']      = in_array($order_by, ['updateTime desc','rating','rating desc'], true)
                            ? $order_by : 'updateTime desc';

    $out['places_api_key'] = sanitize_text_field($opts['places_api_key'] ?? '');
    $out['place_id']       = sanitize_text_field($opts['place_id']       ?? '');

    $out['min_rating'] = isset($opts['min_rating']) && $opts['min_rating'] !== ''
                         ? max(1, min(5, (int)$opts['min_rating'])) : '';

    $out['shortcode_title']    = sanitize_text_field($opts['shortcode_title']        ?? '');
    $out['shortcode_subtitle'] = sanitize_textarea_field($opts['shortcode_subtitle'] ?? '');

    return $out;
  }

  public static function enqueue($hook) {
    if ($hook !== self::$hook) return;

    $ver = defined('EGBP_VER') ? EGBP_VER : (string) time();
    wp_enqueue_style('egbp-admin',  plugins_url('../assets/admin.css', __FILE__), [], $ver);
    wp_enqueue_script('egbp-admin', plugins_url('../assets/admin.js',  __FILE__), [], $ver, true);

    wp_localize_script('egbp-admin', 'EGBP', [
      'ajax'  => admin_url('admin-ajax.php'),
      'nonce' => wp_create_nonce('egbp_update'),
      'i18n'  => [
        'updating' => __('Updating reviews…',  'velvetylab-gbp-reviews'),
        'done'     => __('Done',               'velvetylab-gbp-reviews'),
        'error'    => __('An error occurred',  'velvetylab-gbp-reviews'),
        'seeLog'   => __('See the log below.', 'velvetylab-gbp-reviews'),
      ],
    ]);
  }

  public static function render_page() {
    if ( ! current_user_can(EGBP_CAP) ) wp_die(__('Insufficient permissions.', 'velvetylab-gbp-reviews'));
    $opts   = get_option(EGBP_OPT_KEY, []);
    $source = $opts['source'] ?? 'gbp_v4';
    $key    = EGBP_OPT_KEY;

    if ( ! empty($_GET['saved']) ) {
      echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings saved.', 'velvetylab-gbp-reviews') . '</p></div>';
    }
    ?>
    <div class="wrap egbp-wrap">
      <div class="egbp-head">
        <h1 class="egbp-title"><?php _e('GBP Reviews', 'velvetylab-gbp-reviews'); ?></h1>
        <div class="egbp-sub"><?php _e('Import Google Business Profile reviews and configure the front-end display.', 'velvetylab-gbp-reviews'); ?></div>
      </div>

      <div class="egbp-tabs-nav" role="tablist">
        <button class="egbp-tab is-active" role="tab" aria-selected="true"  aria-controls="egbp-panel-connection" data-tab="connection"><?php _e('Google Connection', 'velvetylab-gbp-reviews'); ?></button>
        <button class="egbp-tab"           role="tab" aria-selected="false" aria-controls="egbp-panel-display"    data-tab="display"><?php _e('Display Settings', 'velvetylab-gbp-reviews'); ?></button>
      </div>

      <div class="egbp-grid">
        <div class="egbp-card">
          <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="egbp-form">
            <input type="hidden" name="action" value="egbp_save_settings" />
            <?php wp_nonce_field('egbp_save_settings'); ?>

            <!-- ── Tab: Google Connection ───────────────────── -->
            <div id="egbp-panel-connection" class="egbp-panel is-active" role="tabpanel">

              <div class="field">
                <label for="egbp_source"><?php esc_html_e('API Source', 'velvetylab-gbp-reviews'); ?></label>
                <select id="egbp_source" name="<?php echo esc_attr($key); ?>[source]">
                  <option value="gbp_v4"     <?php selected($source, 'gbp_v4');     ?>><?php esc_html_e('Google Business Profile API v4 (OAuth)', 'velvetylab-gbp-reviews'); ?></option>
                  <option value="places_api" <?php selected($source, 'places_api'); ?>><?php esc_html_e('Google Places API (API Key, max 5 reviews)', 'velvetylab-gbp-reviews'); ?></option>
                </select>
              </div>

              <div id="egbp-fields-gbp">
                <div class="field">
                  <label for="egbp_client_id">Client ID</label>
                  <input type="text" id="egbp_client_id" name="<?php echo esc_attr($key); ?>[client_id]" value="<?php echo esc_attr($opts['client_id'] ?? ''); ?>" />
                </div>
                <div class="field">
                  <label for="egbp_client_secret">Client Secret</label>
                  <input type="text" id="egbp_client_secret" name="<?php echo esc_attr($key); ?>[client_secret]" value="<?php echo esc_attr($opts['client_secret'] ?? ''); ?>" />
                </div>
                <div class="field">
                  <label for="egbp_refresh_token">Refresh Token</label>
                  <input type="text" id="egbp_refresh_token" name="<?php echo esc_attr($key); ?>[refresh_token]" value="<?php echo esc_attr($opts['refresh_token'] ?? ''); ?>" />
                </div>
                <div class="field">
                  <label for="egbp_account_id">Account ID</label>
                  <input type="text" id="egbp_account_id" name="<?php echo esc_attr($key); ?>[account_id]" value="<?php echo esc_attr($opts['account_id'] ?? ''); ?>" />
                </div>
                <div class="field">
                  <label for="egbp_location_id">Location ID</label>
                  <input type="text" id="egbp_location_id" name="<?php echo esc_attr($key); ?>[location_id]" value="<?php echo esc_attr($opts['location_id'] ?? ''); ?>" />
                </div>
                <div class="field">
                  <label for="egbp_order_by"><?php esc_html_e('Sort order', 'velvetylab-gbp-reviews'); ?></label>
                  <select id="egbp_order_by" name="<?php echo esc_attr($key); ?>[order_by]">
                    <?php
                    $order_by = $opts['order_by'] ?? 'updateTime desc';
                    foreach (['updateTime desc' => 'updateTime desc', 'rating' => 'rating', 'rating desc' => 'rating desc'] as $val => $lbl) {
                      printf('<option value="%s" %s>%s</option>', esc_attr($val), selected($order_by, $val, false), esc_html($lbl));
                    }
                    ?>
                  </select>
                </div>
              </div>

              <div id="egbp-fields-places">
                <div class="field">
                  <label for="egbp_places_api_key"><?php esc_html_e('API Key', 'velvetylab-gbp-reviews'); ?></label>
                  <input type="text" id="egbp_places_api_key" name="<?php echo esc_attr($key); ?>[places_api_key]" value="<?php echo esc_attr($opts['places_api_key'] ?? ''); ?>" />
                </div>
                <div class="field">
                  <label for="egbp_place_id"><?php esc_html_e('Place ID', 'velvetylab-gbp-reviews'); ?></label>
                  <input type="text" id="egbp_place_id" name="<?php echo esc_attr($key); ?>[place_id]" value="<?php echo esc_attr($opts['place_id'] ?? ''); ?>" placeholder="ChIJ..." />
                </div>
              </div>

              <div class="field">
                <label for="egbp_min_rating"><?php esc_html_e('Minimum rating (1–5)', 'velvetylab-gbp-reviews'); ?></label>
                <input type="number" min="1" max="5" id="egbp_min_rating" name="<?php echo esc_attr($key); ?>[min_rating]" value="<?php echo esc_attr($opts['min_rating'] ?? ''); ?>" />
              </div>

              <div class="egbp-actions">
                <?php submit_button(__('Save Settings', 'velvetylab-gbp-reviews'), 'secondary', 'submit', false); ?>
                <button type="button" class="button button-primary egbp-btn primary" id="egbp-update-btn"><?php esc_html_e('Update Reviews', 'velvetylab-gbp-reviews'); ?></button>
                <span id="egbp-status" class="egbp-status" aria-live="polite"></span>
              </div>

              <pre class="egbp-log" id="egbp-log" aria-live="polite"></pre>
            </div>

            <!-- ── Tab: Display Settings ────────────────────── -->
            <div id="egbp-panel-display" class="egbp-panel" role="tabpanel">

              <div class="field">
                <label for="egbp_shortcode_title"><?php esc_html_e('Section title', 'velvetylab-gbp-reviews'); ?></label>
                <input type="text" id="egbp_shortcode_title" name="<?php echo esc_attr($key); ?>[shortcode_title]" value="<?php echo esc_attr($opts['shortcode_title'] ?? ''); ?>" placeholder="<?php esc_attr_e('e.g. Our Reviews', 'velvetylab-gbp-reviews'); ?>" />
              </div>
              <div class="field">
                <label for="egbp_shortcode_subtitle"><?php esc_html_e('Section subtitle', 'velvetylab-gbp-reviews'); ?></label>
                <textarea id="egbp_shortcode_subtitle" name="<?php echo esc_attr($key); ?>[shortcode_subtitle]" rows="3" placeholder="<?php esc_attr_e('e.g. What our clients say', 'velvetylab-gbp-reviews'); ?>"><?php echo esc_textarea($opts['shortcode_subtitle'] ?? ''); ?></textarea>
              </div>
              <div class="egbp-note"><?php esc_html_e('These values are used as defaults when [gbp_reviews] is placed without title/subtitle attributes. You can always override them per-shortcode: [gbp_reviews title="Custom title"].', 'velvetylab-gbp-reviews'); ?></div>

              <div class="egbp-actions">
                <?php submit_button(__('Save Settings', 'velvetylab-gbp-reviews'), 'secondary', 'submit', false); ?>
              </div>
            </div>

          </form>
        </div>

        <div class="egbp-card egbp-help-card" id="egbp-help-connection">
          <p style="margin:0 0 8px;color:var(--egbp-muted)"><?php esc_html_e('Tips:', 'velvetylab-gbp-reviews'); ?></p>
          <ul style="margin:0 0 0 16px;line-height:1.6" id="egbp-tips-gbp">
            <li><?php esc_html_e('Fill in Client ID / Secret / Refresh Token to access the Google Business Profile API v4.', 'velvetylab-gbp-reviews'); ?></li>
            <li><?php esc_html_e('Account ID and Location ID must be numeric.', 'velvetylab-gbp-reviews'); ?></li>
            <li><?php esc_html_e('This source requires approved API access from Google.', 'velvetylab-gbp-reviews'); ?></li>
          </ul>
          <ul style="margin:0 0 0 16px;line-height:1.6" id="egbp-tips-places">
            <li><?php esc_html_e('Get a free API Key in Google Cloud Console — enable "Places API (New)".', 'velvetylab-gbp-reviews'); ?></li>
            <li><?php esc_html_e('Place ID looks like ChIJN1t_tDeuEmsRUsoyG83frY4. Find it at: developers.google.com/maps/documentation/places/web-service/place-id', 'velvetylab-gbp-reviews'); ?></li>
            <li><?php esc_html_e('Limited to 5 most recent reviews per request.', 'velvetylab-gbp-reviews'); ?></li>
          </ul>
        </div>

        <div class="egbp-card egbp-help-card" id="egbp-help-display" style="display:none">
          <p style="margin:0 0 8px;color:var(--egbp-muted)"><?php esc_html_e('Shortcode usage:', 'velvetylab-gbp-reviews'); ?></p>
          <ul style="margin:0 0 0 16px;line-height:1.6">
            <li><?php esc_html_e('Place [gbp_reviews] anywhere on a page.', 'velvetylab-gbp-reviews'); ?></li>
            <li><?php esc_html_e('Available attributes: title, subtitle, limit, order.', 'velvetylab-gbp-reviews'); ?></li>
            <li><code>[gbp_reviews limit="6" order="ASC"]</code></li>
          </ul>
        </div>
      </div>
    </div>
    <?php
  }
}
