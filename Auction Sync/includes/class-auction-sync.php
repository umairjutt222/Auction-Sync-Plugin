<?php
class Auction_Sync {
    const API_URL = 'https://bidnow.auctionaz.com/api/feed?active=false';
    const PER_PAGE = 50;

    public static function fetch_auction_data() {
        // Prevent multiple concurrent syncs
        if (defined('AUCTION_SYNC_RUNNING')) {
            wp_send_json_error(['error' => 'Sync already in progress']);
            return;
        }
        define('AUCTION_SYNC_RUNNING', true);

        $created = 0;
        $updated = 0;
        $page = 1;
        $all_data = [];

        while (true) {
            $api_url = self::API_URL . "&per_page=" . self::PER_PAGE . "&page=" . $page;

            $response = wp_remote_get($api_url, [
                'timeout' => 60,
                'redirection' => 5,
                'blocking' => true,
                'headers' => [
                    'Accept' => 'application/json',
                ],
            ]);

            if (is_wp_error($response)) {
                error_log("API Error: " . $response->get_error_message());
                wp_send_json_error(['error' => $response->get_error_message()]);
                return;
            }

            $data = json_decode(wp_remote_retrieve_body($response), true);
            $all_data[] = $data;

            // Stop if no results
            if (!isset($data['results']) || empty($data['results'])) {
                break;
            }

            foreach ($data['results'] as $auction) {
                if (!isset($auction['id'])) continue;

                // ✅ FIXED: Checking the correct meta key `_auction_auction_id`
                $existing_post_id = self::get_post_id_by_meta('_auction_auction_id', $auction['id']);
                if ($existing_post_id) {
                    self::update_auction_post($existing_post_id, $auction);
                    $updated++;
                } else {
                    self::insert_auction_post($auction);
                    $created++;
                }
            }
            $page++;
        }

        // Output readable response with auction data
        $formatted_data = json_encode($all_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        wp_send_json_success([
            'message' => "Sync completed: $created auctions created, $updated updated.",
            'data' => $formatted_data
        ]);
    }


    private static function insert_auction_post($auction) {
        $post_data = self::prepare_post_data($auction);
        $post_id = wp_insert_post($post_data);

        if ($post_id) {
            self::store_custom_fields($post_id, $auction);
            error_log("Auction created: " . $post_data['post_title']);
        } else {
            error_log("Failed to create post for Auction ID: {$auction['id']}");
        }
    }

    private static function update_auction_post($post_id, $auction) {
        $post_data = self::prepare_post_data($auction);
        $post_data['ID'] = $post_id;
        wp_update_post($post_data);
        self::store_custom_fields($post_id, $auction);
        error_log("Auction updated: " . get_the_title($post_id));
    }

    private static function prepare_post_data($auction) {
        $description = $auction['description'] ?? '';
        $formatted_description = $auction['formatted_simple_description'] ?? '';

        $content = $description;
        if ($formatted_description) {
            $content .= "\n\n" . $formatted_description;
        }

        return [
            'post_title'   => sanitize_text_field($auction['name'] ?? 'Untitled Auction'),
            'post_content' => wp_kses_post($content),
            'post_status'  => 'publish',
            'post_type'    => 'auction_sync_list',
        ];
    }

    private static function store_custom_fields($post_id, $auction) {
        $fields = [
            'auction_id' => $auction['id'] ?? '',
            'items_count' => $auction['items_count'] ?? 0,
            'third_party_bidding_url' => $auction['third_party_bidding_url'] ?? '',
            'name' => $auction['name'] ?? '',
            'status' => $auction['status'] ?? '',
            'scheduled_end_time' => $auction['scheduled_end_time'] ?? '',
            'starts_at' => $auction['starts_at'] ?? '',
            'hide_dates' => $auction['hide_dates'] ?? false,
            'timezone' => $auction['timezone'] ?? '',
            'advance_to_live' => $auction['advance_to_live'] ?? false,
            'tag_line' => $auction['tag_line'] ?? '',
            'location' => $auction['location'] ?? '',
            'description' => $auction['description'] ?? '',
            'simple_description' => $auction['simple_description'] ?? '',
            'formatted_simple_description' => $auction['formatted_simple_description'] ?? '',
            'company_id' => $auction['company_id'] ?? '',
            'broadcast' => $auction['broadcast'] ?? false,
            'published' => $auction['published'] ?? false,
            'online_only' => $auction['online_only'] ?? false,
            'offline_only' => $auction['offline_only'] ?? false,
        ];

        foreach ($fields as $key => $value) {
            update_post_meta($post_id, '_auction_' . sanitize_key($key), $value);
        }

        if (!empty($auction['featured_images'])) {
            $images = array_map(function ($image) {
                return $image['large_url'];
            }, $auction['featured_images']);
            update_post_meta($post_id, '_auction_featured_images', $images);
        }

        error_log("Stored custom fields for Post ID $post_id: " . print_r(get_post_meta($post_id), true));
    }


    private static function get_post_id_by_meta($meta_key, $meta_value) {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = %s AND meta_value = %s",
            $meta_key, $meta_value
        ));
    }
}
