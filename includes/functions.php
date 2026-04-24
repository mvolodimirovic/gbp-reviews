<?php
if ( ! defined('ABSPATH') ) exit;

function egbp_get_rating(int $post_id): ?int {
  $val = get_post_meta($post_id, 'rating', true);
  return $val !== '' ? (int)$val : null;
}

function egbp_author_bg_color(string $title): string {
  if (function_exists('author_bg_color_light')) {
    return author_bg_color_light($title);
  }
  $h = abs(crc32($title)) % 360;
  return "hsl({$h},45%,85%)";
}

function egbp_title_initials(string $title): string {
  if (function_exists('get_title_initials')) {
    return get_title_initials($title);
  }
  $words = preg_split('/\s+/u', trim($title));
  $out = '';
  foreach (array_slice($words, 0, 2) as $w) {
    $out .= mb_strtoupper(mb_substr($w, 0, 1));
  }
  return $out ?: '?';
}

function egbp_author_image(int $thumb_id, string $title): string {
  if ($thumb_id) {
    if (function_exists('render_optimized_image')) {
      return render_optimized_image($thumb_id, 'thumbnail', '', true, '50vw');
    }
    return wp_get_attachment_image($thumb_id, 'thumbnail');
  }
  $bg       = esc_attr(egbp_author_bg_color($title));
  $initials = esc_html(egbp_title_initials($title));
  return "<span class=\"reviews__item__initials\" style=\"background-color:{$bg}\">{$initials}</span>";
}
