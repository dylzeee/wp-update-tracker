<?php
// Exit if accessed directly.
defined('ABSPATH') || exit;

// Ensure the uninstall constant is defined.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Remove custom database table.
global $wpdb;
$table_name = $wpdb->prefix . 'update_tracker_logs';
$wpdb->query("DROP TABLE IF EXISTS $table_name");
