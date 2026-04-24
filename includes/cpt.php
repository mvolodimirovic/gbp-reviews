<?php
if ( ! defined('ABSPATH') ) exit;

class EGBP_CPT {

  public static function maybe_register() {
    if ( post_type_exists('reviews') ) return;

    register_post_type('reviews', [
      'labels' => [
        'name'               => __('Reviews', 'velvetylab-gbp-reviews'),
        'singular_name'      => __('Review', 'velvetylab-gbp-reviews'),
        'add_new_item'       => __('Add New Review', 'velvetylab-gbp-reviews'),
        'edit_item'          => __('Edit Review', 'velvetylab-gbp-reviews'),
        'search_items'       => __('Search Reviews', 'velvetylab-gbp-reviews'),
        'not_found'          => __('No reviews found.', 'velvetylab-gbp-reviews'),
        'not_found_in_trash' => __('No reviews found in Trash.', 'velvetylab-gbp-reviews'),
      ],
      'public'             => true,
      'publicly_queryable' => false,
      'has_archive'        => false,
      'show_in_rest'       => false,
      'menu_icon'          => 'dashicons-star-half',
      'supports'           => ['title', 'editor', 'thumbnail'],
      'rewrite'            => false,
    ]);
  }

  public static function disable_gutenberg($use_block_editor, $post_type) {
    if ($post_type === 'reviews') return false;
    return $use_block_editor;
  }
}
