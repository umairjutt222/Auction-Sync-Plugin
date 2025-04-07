<?php
/**
 * Plugin Name: Auction Sync
 * Plugin URI:  https://yourwebsite.com/
 * Description: Sync auctions from an external API and store them as WordPress posts.
 * Version:     1.0
 * Author:      Gifts of GLobe
 * License:     GPL2
 */

if (!defined('ABSPATH')) {
    exit;
}

// Include required files
require_once plugin_dir_path(__FILE__) . 'includes/class-auction-sync.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-auction-admin.php';

// Initialize classes
// Auction_Sync::init();
Auction_Admin::init();
