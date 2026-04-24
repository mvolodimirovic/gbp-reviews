<?php
if ( ! defined('ABSPATH') ) exit;

class EGBP_Importer {

  // ── helpers ────────────────────────────────────────────────

  private static function map_star($enum): ?int {
    if (is_numeric($enum)) return max(1, min(5, (int)$enum));
    $map = ['ONE'=>1,'TWO'=>2,'THREE'=>3,'FOUR'=>4,'FIVE'=>5];
    return $map[strtoupper((string)$enum)] ?? null;
  }

  private static function extract_original(string $comment): string {
    if ($comment === '') return '';
    if (preg_match('~\((Original|Оригінал|Оригинал)\)\s*(.+)$~isu', $comment, $m)) {
      return trim($m[2]);
    }
    if (stripos($comment, 'Translated by Google') !== false) {
      $parts = preg_split("~\R\R+~u", $comment);
      if (!empty($parts)) return trim(end($parts));
    }
    return trim($comment);
  }

  private static function insert_review(string $review_id, string $display, string $comment, string $create, ?int $rating_num, string $photo_url, array &$log): bool {
    // duplicate check
    $q = new WP_Query([
      'post_type'      => 'reviews',
      'fields'         => 'ids',
      'meta_key'       => 'gbp_review_id',
      'meta_value'     => $review_id,
      'posts_per_page' => 1,
      'post_status'    => 'any',
      'no_found_rows'  => true,
    ]);
    if ($q->have_posts()) return false;

    $postarr = [
      'post_type'    => 'reviews',
      'post_status'  => 'publish',
      'post_title'   => (string)$display,
      'post_content' => $comment ? wp_kses_post($comment) : '',
    ];

    if ($create) {
      $ts = is_numeric($create) ? (int)$create : strtotime($create);
      if ($ts) {
        $postarr['post_date']     = get_date_from_gmt(gmdate('Y-m-d H:i:s', $ts));
        $postarr['post_date_gmt'] = gmdate('Y-m-d H:i:s', $ts);
      }
    }

    $post_id = wp_insert_post($postarr);
    if (!$post_id || is_wp_error($post_id)) {
      $log[] = ['insert_error' => is_wp_error($post_id) ? $post_id->get_error_message() : $post_id];
      return false;
    }

    update_post_meta($post_id, 'gbp_review_id', $review_id);

    if ($rating_num !== null) {
      update_post_meta($post_id, 'rating', $rating_num);
    }

    if ($photo_url) {
      self::import_photo($post_id, $photo_url);
    }

    return true;
  }

  private static function import_photo(int $post_id, string $url): bool {
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';
    $attach_id = media_sideload_image($url, $post_id, null, 'id');
    if (is_wp_error($attach_id)) return false;
    return set_post_thumbnail($post_id, $attach_id);
  }

  // ── entry point ─────────────────────────────────────────────

  public static function ajax_import(): void {
    if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'forbidden'], 403);
    check_ajax_referer('egbp_update');

    $opts   = get_option(EGBP_OPT_KEY, []);
    $source = $opts['source'] ?? 'gbp_v4';

    if ($source === 'places_api') {
      self::run_places_import($opts);
    } else {
      self::run_gbp_import($opts);
    }
  }

  // ── Google Business Profile API v4 ──────────────────────────

  private static function run_gbp_import(array $opts): void {
    $client_id     = $opts['client_id']     ?? '';
    $client_secret = $opts['client_secret'] ?? '';
    $refresh_token = $opts['refresh_token'] ?? '';
    $account_id    = preg_replace('~\D~', '', $opts['account_id']  ?? '');
    $location_id   = preg_replace('~\D~', '', $opts['location_id'] ?? '');
    $min_rating    = isset($opts['min_rating']) && $opts['min_rating'] !== '' ? (int)$opts['min_rating'] : null;
    $order_by      = $opts['order_by'] ?? 'updateTime desc';

    if (!$client_id || !$client_secret || !$refresh_token || !$account_id || !$location_id) {
      wp_send_json_error(['message' => 'missing_options'], 400);
    }

    // get access token
    $token_resp = wp_remote_post('https://oauth2.googleapis.com/token', [
      'timeout' => 20,
      'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
      'body'    => [
        'grant_type'    => 'refresh_token',
        'refresh_token' => $refresh_token,
        'client_id'     => $client_id,
        'client_secret' => $client_secret,
      ],
    ]);
    if (is_wp_error($token_resp)) wp_send_json_error(['message' => 'token_error', 'error' => $token_resp->get_error_message()], 500);

    $token_body = json_decode(wp_remote_retrieve_body($token_resp), true);
    if (empty($token_body['access_token'])) wp_send_json_error(['message' => 'token_empty', 'raw' => $token_body], 500);

    $access_token = $token_body['access_token'];
    $base = sprintf('https://mybusiness.googleapis.com/v4/accounts/%s/locations/%s/reviews',
      rawurlencode($account_id), rawurlencode($location_id));

    $created = 0; $skipped = 0; $pages = 0; $fetched = 0; $log = [];
    $pageToken = null;

    do {
      $pages++;
      $q = ['pageSize' => 50, 'orderBy' => $order_by];
      if ($pageToken) $q['pageToken'] = $pageToken;

      $resp = wp_remote_get(add_query_arg($q, $base), [
        'timeout' => 30,
        'headers' => ['Authorization' => 'Bearer ' . $access_token],
      ]);
      if (is_wp_error($resp)) { $log[] = ['page' => $pages, 'error' => $resp->get_error_message()]; break; }

      $code     = wp_remote_retrieve_response_code($resp);
      $raw_body = wp_remote_retrieve_body($resp);
      $data     = json_decode($raw_body, true);
      if ((int)$code !== 200) { $log[] = ['page' => $pages, 'http' => $code, 'body' => $data, 'raw' => substr($raw_body, 0, 500)]; break; }

      $items = is_array($data['reviews'] ?? null) ? $data['reviews'] : [];
      foreach ($items as $r) {
        $review_id = '';
        if (!empty($r['name']) && preg_match('~reviews/([^/]+)$~', $r['name'], $m)) $review_id = $m[1];
        if (!$review_id) { $log[] = ['skip' => 'no_review_id']; $skipped++; continue; }

        $rating_num = self::map_star($r['starRating'] ?? null);
        if ($min_rating && $rating_num !== null && $rating_num < $min_rating) { $skipped++; continue; }

        $display   = $r['reviewer']['displayName'] ?? __('Google User', 'velvetylab-gbp-reviews');
        $photo_url = $r['reviewer']['profilePhotoUrl'] ?? '';
        $comment   = self::extract_original($r['comment'] ?? '');
        $create    = $r['createTime'] ?? ($r['updateTime'] ?? '');

        if (self::insert_review($review_id, $display, $comment, $create, $rating_num, $photo_url, $log)) {
          $created++;
        } else {
          $skipped++;
        }
      }

      $fetched   += count($items);
      $pageToken  = $data['nextPageToken'] ?? null;
    } while ($pageToken);

    wp_send_json_success(['pages' => $pages, 'fetched' => $fetched, 'created' => $created, 'skipped' => $skipped, 'log' => $log]);
  }

  // ── Google Places API (New) ──────────────────────────────────

  private static function run_places_import(array $opts): void {
    $api_key    = trim($opts['places_api_key'] ?? '');
    $place_id   = trim($opts['place_id']       ?? '');
    $min_rating = isset($opts['min_rating']) && $opts['min_rating'] !== '' ? (int)$opts['min_rating'] : null;

    if (!$api_key || !$place_id) {
      wp_send_json_error(['message' => 'missing_places_options'], 400);
    }

    $url = sprintf('https://places.googleapis.com/v1/places/%s', rawurlencode($place_id));

    $resp = wp_remote_get($url, [
      'timeout' => 30,
      'headers' => [
        'X-Goog-Api-Key'    => $api_key,
        'X-Goog-FieldMask'  => 'reviews',
      ],
    ]);

    if (is_wp_error($resp)) wp_send_json_error(['message' => 'request_error', 'error' => $resp->get_error_message()], 500);

    $code     = wp_remote_retrieve_response_code($resp);
    $raw_body = wp_remote_retrieve_body($resp);
    $data     = json_decode($raw_body, true);

    if ((int)$code !== 200) {
      wp_send_json_error(['message' => 'api_error', 'http' => $code, 'body' => $data, 'raw' => substr($raw_body, 0, 500)], 500);
    }

    $items   = is_array($data['reviews'] ?? null) ? $data['reviews'] : [];
    $created = 0; $skipped = 0; $log = [];

    foreach ($items as $r) {
      $review_id = '';
      if (!empty($r['name']) && preg_match('~reviews/([^/]+)$~', $r['name'], $m)) $review_id = $m[1];
      if (!$review_id) { $log[] = ['skip' => 'no_review_id']; $skipped++; continue; }

      $rating_num = isset($r['rating']) ? max(1, min(5, (int)$r['rating'])) : null;
      if ($min_rating && $rating_num !== null && $rating_num < $min_rating) { $skipped++; continue; }

      $display   = $r['authorAttribution']['displayName'] ?? __('Google User', 'velvetylab-gbp-reviews');
      $photo_url = $r['authorAttribution']['photoUri']    ?? '';
      $comment   = !empty($r['originalText']['text']) ? trim($r['originalText']['text']) : trim($r['text']['text'] ?? '');
      $create    = $r['publishTime'] ?? '';

      if (self::insert_review($review_id, $display, $comment, $create, $rating_num, $photo_url, $log)) {
        $created++;
      } else {
        $skipped++;
      }
    }

    wp_send_json_success(['pages' => 1, 'fetched' => count($items), 'created' => $created, 'skipped' => $skipped, 'log' => $log]);
  }
}
