<?php
/**
 * Plugin Name: VelvetyLab GBP Reviews
 * Description: Import Google Business Profile reviews into CPT "reviews" + Update Reviews button on the admin sub-page.
 * Version:     1.0.0
 * Author:      VelvetyLab
 * Text Domain: velvetylab-gbp-reviews
 */

if ( ! defined('ABSPATH') ) exit;

define('EGBP_VER',          '1.0.0');
define('EGBP_SLUG',         'velvetylab-gbp-reviews');
define('EGBP_OPT_KEY',      'egbp_options');
define('EGBP_CAP',          'edit_posts');
define('EGBP_DIR',          plugin_dir_path(__FILE__));
define('EGBP_URL',          plugin_dir_url(__FILE__));
define('EGBP_TEMPLATE_DIR', EGBP_DIR . 'templates');

require_once EGBP_DIR . 'includes/functions.php';
require_once EGBP_DIR . 'includes/cpt.php';
require_once EGBP_DIR . 'includes/meta.php';
require_once EGBP_DIR . 'includes/admin.php';
require_once EGBP_DIR . 'includes/importer.php';
require_once EGBP_DIR . 'includes/shortcode.php';

register_activation_hook(__FILE__, function(){
  $defaults = array(
    'client_id'     => '',
    'client_secret' => '',
    'refresh_token' => '',
    'account_id'    => '',
    'location_id'   => '',
    'min_rating'         => '',
    'order_by'           => 'updateTime desc',
    'shortcode_title'    => '',
    'shortcode_subtitle' => '',
  );
  $current = get_option(EGBP_OPT_KEY, array());
  update_option(EGBP_OPT_KEY, array_merge($defaults, (array)$current), false);
});

add_action('init',       ['EGBP_CPT',  'maybe_register'], 20);
add_action('init',       ['EGBP_Meta', 'register']);
add_action('add_meta_boxes', ['EGBP_Meta', 'add_meta_box']);
add_action('save_post',  ['EGBP_Meta', 'save_meta_box']);
add_filter('use_block_editor_for_post_type', ['EGBP_CPT', 'disable_gutenberg'], 10, 2);

add_action('admin_menu',                       ['EGBP_Admin','register_menu']);
add_action('admin_post_egbp_save_settings',    ['EGBP_Admin','handle_save']);
add_action('admin_enqueue_scripts',            ['EGBP_Admin','enqueue']);

add_action('wp_ajax_egbp_update_reviews', ['EGBP_Importer','ajax_import']);

add_action('init', ['EGBP_Shortcode', 'register']);
