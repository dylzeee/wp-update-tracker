<?php
/**
 * Handles logging of WordPress core, plugin, theme updates, and settings changes.
 */

defined('ABSPATH') || exit;

class WP_Update_Tracker_Event_Logger {

    /**
     * Initialize hooks for tracking events.
     */
    public static function init() {
        // Hook to track updates.
        add_action('upgrader_process_complete', [ __CLASS__, 'track_updates' ], 10, 2);

        // Hook to track settings changes.
        add_action('update_option', [ __CLASS__, 'track_settings_changes' ], 10, 3);
        // Other hooks
        add_action('wp_version_check', [ __CLASS__, 'capture_core_version' ]);
        add_filter('pre_set_site_transient_update_plugins', [ __CLASS__, 'capture_plugin_versions' ]);
        add_filter('pre_set_site_transient_update_themes', [ __CLASS__, 'capture_theme_versions' ]);

    }

/**
 * Track WordPress core, plugin, and theme updates, including previous versions.
 *
 * @param WP_Upgrader $upgrader The upgrader instance.
 * @param array       $data     Details of the upgrade process.
 */
public static function track_updates($upgrader, $data) {
  global $wpdb;

  $table_name = $wpdb->prefix . 'update_tracker_logs';
  $timestamp = current_time('mysql');

  // Process WordPress Core Updates.
  if ($data['type'] === 'core') {
      $current_version = get_bloginfo('version');
      $previous_version = get_option('pre_update_core_version', 'Unknown'); // Retrieve stored version.

      $details = sprintf(
          __('WordPress core updated from version %s to %s', 'wp-update-tracker'),
          $previous_version,
          $current_version
      );

      $wpdb->insert($table_name, [
          'event_type'    => 'core_update',
          'event_details' => $details,
          'timestamp'     => $timestamp,
      ]);
  }

  // Process Plugin Updates.
  elseif ($data['type'] === 'plugin' && !empty($data['plugins'])) {
      $updated_plugins = [];

      foreach ($data['plugins'] as $plugin_file) {
          $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin_file, false, false);
          $plugin_name = $plugin_data['Name'] ?? $plugin_file;
          $previous_version = get_option("pre_update_plugin_version_{$plugin_file}", 'Unknown');
          $current_version = $plugin_data['Version'] ?? 'Unknown';

          $updated_plugins[] = sprintf('%s (from %s to %s)', $plugin_name, $previous_version, $current_version);

          // Optionally clean up the stored version.
          delete_option("pre_update_plugin_version_{$plugin_file}");
      }

      $details = sprintf(
          __('Plugins updated: %s', 'wp-update-tracker'),
          implode(', ', $updated_plugins)
      );

      $wpdb->insert($table_name, [
          'event_type'    => 'plugin_update',
          'event_details' => $details,
          'timestamp'     => $timestamp,
      ]);
  }

  // Process Theme Updates.
  elseif ($data['type'] === 'theme' && !empty($data['themes'])) {
      $updated_themes = [];

      foreach ($data['themes'] as $theme_slug) {
          $theme = wp_get_theme($theme_slug);
          $previous_version = get_option("pre_update_theme_version_{$theme_slug}", 'Unknown');
          $current_version = $theme->get('Version');

          $updated_themes[] = sprintf('%s (from %s to %s)', $theme->get('Name'), $previous_version, $current_version);

          // Optionally clean up the stored version.
          delete_option("pre_update_theme_version_{$theme_slug}");
      }

      $details = sprintf(
          __('Themes updated: %s', 'wp-update-tracker'),
          implode(', ', $updated_themes)
      );

      $wpdb->insert($table_name, [
          'event_type'    => 'theme_update',
          'event_details' => $details,
          'timestamp'     => $timestamp,
      ]);
  }
}


/**
 * Track WordPress settings changes, restricted to a whitelist of core settings.
 *
 * @param string $option    The name of the updated option.
 * @param mixed  $old_value The old value of the option.
 * @param mixed  $value     The new value of the option.
 */
public static function track_settings_changes($option, $old_value, $value) {
  global $wpdb;

  // Define the whitelist of core settings to track.
  $whitelist = [
      'blogname',        // Site Title
      'blogdescription', // Tagline
      'admin_email',     // Admin Email
      'timezone_string', // Timezone
      'date_format',     // Date Format
      'time_format',     // Time Format
  ];

  // Only proceed if the option is in the whitelist.
  if (!in_array($option, $whitelist, true)) {
      return;
  }

  // Check if the value actually changed.
  if ($old_value !== $value) {
      $table_name = $wpdb->prefix . 'update_tracker_logs';
      $timestamp = current_time('mysql');

      $details = sprintf(
          __('Core setting "%1$s" changed from "%2$s" to "%3$s"', 'wp-update-tracker'),
          $option,
          is_scalar($old_value) ? $old_value : __('non-scalar value', 'wp-update-tracker'),
          is_scalar($value) ? $value : __('non-scalar value', 'wp-update-tracker')
      );

      // Insert the log into the database.
      $wpdb->insert($table_name, [
          'event_type'    => 'settings_change',
          'event_details' => $details,
          'timestamp'     => $timestamp,
      ]);
  }
}

/**
 * Store current WordPress core version before updates.
 */
public static function capture_core_version() {
  update_option('pre_update_core_version', get_bloginfo('version'));
}

/**
* Store current plugin versions before updates.
*
* @param object $transient The update transient.
* @return object The filtered transient.
*/
public static function capture_plugin_versions($transient) {
  foreach ($transient->response as $plugin_file => $plugin_data) {
      $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin_file, false, false);
      update_option("pre_update_plugin_version_{$plugin_file}", $plugin_data['Version'] ?? 'Unknown');
  }
  return $transient;
}

/**
* Store current theme versions before updates.
*
* @param object $transient The update transient.
* @return object The filtered transient.
*/
public static function capture_theme_versions($transient) {
  foreach ($transient->response as $theme_slug => $theme_data) {
      $theme = wp_get_theme($theme_slug);
      update_option("pre_update_theme_version_{$theme_slug}", $theme->get('Version') ?? 'Unknown');
  }
  return $transient;
}


}
