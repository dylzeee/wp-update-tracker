<?php
/**
 * Plugin Name: WP Update Tracker
 * Description: Tracks updates to WordPress core, plugins, themes, and settings changes. Includes notifications and admin UI.
 * Version: 1.0
 * Author: Your Name
 * Text Domain: wp-update-tracker
 * Domain Path: /languages
 */

defined('ABSPATH') || exit;

// Define plugin constants.
define('WP_UPDATE_TRACKER_VERSION', '1.0');
define('WP_UPDATE_TRACKER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WP_UPDATE_TRACKER_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once WP_UPDATE_TRACKER_PLUGIN_DIR . 'includes/class-event-logger.php';
require_once WP_UPDATE_TRACKER_PLUGIN_DIR . 'includes/class-admin-ui.php';
//require_once WP_UPDATE_TRACKER_PLUGIN_DIR . 'includes/class-cron-handler.php';
//require_once WP_UPDATE_TRACKER_PLUGIN_DIR . 'includes/class-notifications.php';

/**
 * Initialize the WP Update Tracker plugin.
 */
class WP_Update_Tracker {

    public function __construct() {
        // Register activation and deactivation hooks.
        register_activation_hook(__FILE__, [ $this, 'on_activation' ]);
        register_deactivation_hook(__FILE__, [ $this, 'on_deactivation' ]);

        // Initialize core functionality.
        add_action('plugins_loaded', [ $this, 'init' ]);
    }

    /**
     * Handles plugin activation tasks.
     */
    public function on_activation() {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        global $wpdb;

        $table_name = $wpdb->prefix . 'update_tracker_logs';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            event_type VARCHAR(50) NOT NULL,
            event_details LONGTEXT NOT NULL,
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY (id)
        ) $charset_collate;";

        dbDelta($sql);

        // Schedule cron job for log pruning.
        if (!wp_next_scheduled('wp_update_tracker_prune_logs')) {
            wp_schedule_event(time(), 'daily', 'wp_update_tracker_prune_logs');
        }

        // Output an activation message to debug log (example of translation use).
        error_log(__('WP Update Tracker activated.', 'wp-update-tracker'));
    }

    /**
     * Handles plugin deactivation tasks.
     */
    public function on_deactivation() {
        // Remove scheduled cron job.
        wp_clear_scheduled_hook('wp_update_tracker_prune_logs');

        // Output a deactivation message to debug log.
        error_log(__('WP Update Tracker deactivated.', 'wp-update-tracker'));
    }

    /**
     * Load plugin text domain and initialize functionality.
     */
    public function init() {
        // Load translations for the plugin.
        load_plugin_textdomain('wp-update-tracker', false, dirname(plugin_basename(__FILE__)) . '/languages');

        // Initialize core functionality.
        WP_Update_Tracker_Event_Logger::init();

        if (is_admin()) {
          WP_Update_Tracker_Admin_UI::init();
        }

       // WP_Update_Tracker_Notifications::init();
       // WP_Update_Tracker_Cron_Handler::init();
    }
}

// Instantiate the plugin.
new WP_Update_Tracker();
