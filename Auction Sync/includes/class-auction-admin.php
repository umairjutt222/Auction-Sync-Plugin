<?php
class Auction_Admin {
    public static function init() {
        add_action('init', [__CLASS__, 'register_post_type']);
        add_action('admin_menu', [__CLASS__, 'register_admin_page']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_scripts']);
        add_action('wp_ajax_sync_auction_data', ['Auction_Sync', 'fetch_auction_data']);

        // ✅ Hook the post type update function properly
        add_action('init', [__CLASS__, 'check_and_update_post_type']);
    }

    public static function register_post_type() {
        register_post_type('auction_sync_list', [
            'labels' => [
                'name'          => __('Auction Sync List', 'textdomain'),
                'singular_name' => __('Auction Sync', 'textdomain'),
            ],
            'public'       => true,
            'has_archive'  => true,
            'supports'     => ['title', 'editor', 'thumbnail', 'custom-fields'],
            'menu_icon'    => 'dashicons-hammer',
            'rewrite'      => ['slug' => 'auctions'],
            'show_in_rest' => true,
        ]);
    }

    public static function register_admin_page() {
        add_submenu_page(
            'edit.php?post_type=auction_sync_list',
            __('Sync Auctions', 'textdomain'),
            __('Sync Auctions', 'textdomain'),
            'manage_options',
            'auction-sync',
            [__CLASS__, 'sync_page']
        );
    }

    public static function enqueue_admin_scripts($hook) {
        if ($hook !== 'auction_sync_list_page_auction-sync') {
            return;
        }
        wp_enqueue_script(
            'auction-sync-js',
            plugins_url('assets/admin.js', dirname(__FILE__)),
            ['jquery'],
            null,
            true
        );
    }

    public static function sync_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Sync Auctions', 'textdomain'); ?></h1>
            <button id="sync-auctions" class="button button-primary"><?php _e('Sync Now', 'textdomain'); ?></button>
            <div id="sync-status"></div>
        </div>
        <?php
    }

    // ✅ Correctly Define the Function Inside the Class
    public static function check_and_update_post_type() {
        global $wpdb;

        // Check if any posts still have the old post type
        $existing_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}posts WHERE post_type = 'auction_list'");

        if ($existing_count > 0) {
            // Update all posts from 'auction_list' to 'auction_sync_list'
            $wpdb->query("UPDATE {$wpdb->prefix}posts SET post_type = 'auction_sync_list' WHERE post_type = 'auction_list'");

            // Flush permalinks
            flush_rewrite_rules();
        }
    }
}

// ✅ Ensure the class is properly initialized
Auction_Admin::init();
