<?php
/**
 * Handles the admin UI for the WP Update Tracker plugin.
 */

defined('ABSPATH') || exit;

class WP_Update_Tracker_Admin_UI {

    /**
     * Initialize the Admin UI.
     */
    public static function init() {
        add_action('admin_menu', [ __CLASS__, 'add_admin_menu' ]);
        add_action('admin_init', [ __CLASS__, 'register_settings' ]);
        add_action('admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin_assets' ]);
        // Hook into admin_init to handle CSV export.
        add_action('admin_init', [__CLASS__, 'handle_csv_export']);
    }

    /**
     * Enqueue admin styles.
     */
    public static function enqueue_admin_assets() {
      wp_enqueue_style(
          'wp-update-tracker-admin',
          WP_UPDATE_TRACKER_PLUGIN_URL . 'assets/css/admin.css',
          [],
          WP_UPDATE_TRACKER_VERSION
      );
    }

    /**
     * Add a menu item to the WordPress admin.
     */
    public static function add_admin_menu() {
        add_menu_page(
            __('WP Update Tracker', 'wp-update-tracker'), // Page title
            __('Update Tracker', 'wp-update-tracker'),   // Menu title
            'manage_options',                            // Capability
            'wp-update-tracker',                         // Menu slug
            [ __CLASS__, 'render_settings_page' ],       // Callback function
            'dashicons-update',                          // Menu icon
            80                                           // Menu position
        );
  
      add_submenu_page(
          'wp-update-tracker',
          __('Log Viewer', 'wp-update-tracker'),
          __('Log Viewer', 'wp-update-tracker'),
          'manage_options',
          'wp-update-tracker-logs',
          [ __CLASS__, 'render_log_viewer_page' ]
      );
    }

    /**
     * Register settings for the plugin.
     */
    public static function register_settings() {
        register_setting('wp_update_tracker_settings', 'wp_update_tracker_settings');

        add_settings_section(
            'wp_update_tracker_general_settings',
            __('General Settings', 'wp-update-tracker'),
            '__return_false', // No description callback.
            'wp-update-tracker'
        );

        add_settings_field(
            'track_core_updates',
            __('Track Core Updates', 'wp-update-tracker'),
            [ __CLASS__, 'render_toggle_field' ],
            'wp-update-tracker',
            'wp_update_tracker_general_settings',
            [
                'label_for' => 'track_core_updates',
                'option_key' => 'track_core_updates',
                'description' => __('Log updates to WordPress core.', 'wp-update-tracker'),
            ]
        );

        add_settings_field(
            'track_plugin_updates',
            __('Track Plugin Updates', 'wp-update-tracker'),
            [ __CLASS__, 'render_toggle_field' ],
            'wp-update-tracker',
            'wp_update_tracker_general_settings',
            [
                'label_for' => 'track_plugin_updates',
                'option_key' => 'track_plugin_updates',
                'description' => __('Log updates to plugins.', 'wp-update-tracker'),
            ]
        );

        add_settings_field(
            'track_theme_updates',
            __('Track Theme Updates', 'wp-update-tracker'),
            [ __CLASS__, 'render_toggle_field' ],
            'wp-update-tracker',
            'wp_update_tracker_general_settings',
            [
                'label_for' => 'track_theme_updates',
                'option_key' => 'track_theme_updates',
                'description' => __('Log updates to themes.', 'wp-update-tracker'),
            ]
        );
    }

    /**
     * Render the settings page.
     */
    public static function render_settings_page() {
      ?>
      <div class="wrap">
          <h1><?php esc_html_e('WP Update Tracker Settings', 'wp-update-tracker'); ?></h1>
          <p class="description">
              <?php esc_html_e('Configure the tracking options for updates and changes in WordPress. Enable or disable logging for WordPress core, plugins, and themes below.', 'wp-update-tracker'); ?>
          </p>
          <form method="post" action="options.php">
              <?php
              settings_fields('wp_update_tracker_settings');
              do_settings_sections('wp-update-tracker');
              submit_button(__('Save Settings', 'wp-update-tracker'));
              ?>
          </form>
      </div>
      <?php
    }


    /**
     * Render a toggle switch field.
     *
     * @param array $args Field arguments.
     */
    public static function render_toggle_field($args) {
      $options = get_option('wp_update_tracker_settings', []);
      $checked = isset($options[$args['option_key']]) ? 'checked' : '';
      ?>
      <label class="wp-update-tracker-switch">
          <input type="checkbox" id="<?php echo esc_attr($args['label_for']); ?>" name="wp_update_tracker_settings[<?php echo esc_attr($args['option_key']); ?>]" value="1" <?php echo $checked; ?>>
          <span class="wp-update-tracker-slider"></span>
      </label>
      <p class="description"><?php echo esc_html($args['description']); ?></p>
      <?php
    }

    /**
     * Handle CSV export for logs.
     */
    public static function handle_csv_export() {
      if (!isset($_GET['export_csv']) || $_GET['export_csv'] !== '1') {
          return;
      }

      if (!current_user_can('manage_options')) {
          wp_die(__('You do not have permission to access this page.', 'wp-update-tracker'));
      }

      global $wpdb;

      $table_name = $wpdb->prefix . 'update_tracker_logs';
      $event_type = $_GET['event_type'] ?? '';
      $where_clause = '';
      $query_args = [];

      if (!empty($event_type)) {
          $where_clause = 'WHERE event_type = %s';
          $query_args[] = $event_type;
      }

      // Fetch logs based on the filter.
      $logs = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name $where_clause ORDER BY timestamp DESC", $query_args), ARRAY_A);

      // Output CSV headers.
      header('Content-Type: text/csv');
      header('Content-Disposition: attachment; filename="update-tracker-logs.csv"');

      $output = fopen('php://output', 'w');
      fputcsv($output, ['Timestamp', 'Event Type', 'Details']);

      // Write logs to CSV.
      foreach ($logs as $log) {
          fputcsv($output, [$log['timestamp'], $log['event_type'], $log['event_details']]);
      }

      fclose($output);
      exit;
    }

    /**
 * Render the Log Viewer page with event type and date range filters.
 */
public static function render_log_viewer_page() {
  global $wpdb;

  $table_name = $wpdb->prefix . 'update_tracker_logs';
  $current_page = max(1, intval($_GET['paged'] ?? 1));
  $per_page = 10;
  $offset = ($current_page - 1) * $per_page;

  // Get filter inputs.
  $event_type = $_GET['event_type'] ?? '';
  $start_date = $_GET['start_date'] ?? '';
  $end_date = $_GET['end_date'] ?? '';

  // Build WHERE clause dynamically.
  $where_clauses = [];
  $query_args = [];

  // Event Type Filter.
  if (!empty($event_type)) {
      $where_clauses[] = 'event_type = %s';
      $query_args[] = $event_type;
  }

  // Date Range Filters.
  if (!empty($start_date)) {
      $where_clauses[] = 'timestamp >= %s';
      $query_args[] = $start_date . ' 00:00:00';
  }
  if (!empty($end_date)) {
      $where_clauses[] = 'timestamp <= %s';
      $query_args[] = $end_date . ' 23:59:59';
  }

  // Combine WHERE clause.
  $where_clause = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

  // Fetch logs with filtering and pagination.
  $query = "SELECT * FROM $table_name $where_clause ORDER BY timestamp DESC LIMIT %d OFFSET %d";
  $query_args[] = $per_page;
  $query_args[] = $offset;
  $logs = $wpdb->get_results($wpdb->prepare($query, $query_args));

  // Count total logs for pagination.
  $count_query = "SELECT COUNT(*) FROM $table_name $where_clause";
  $total_logs = $wpdb->get_var($wpdb->prepare($count_query, array_slice($query_args, 0, count($query_args) - 2)));
  $total_pages = ceil($total_logs / $per_page);

  ?>
  <div class="wrap">
      <h1><?php esc_html_e('Log Viewer', 'wp-update-tracker'); ?></h1>

      <!-- Filter Form -->
      <form method="get" action="">
          <input type="hidden" name="page" value="wp-update-tracker-logs">

          <!-- Event Type Filter -->
          <label for="event_type"><?php esc_html_e('Filter by Event Type:', 'wp-update-tracker'); ?></label>
          <select id="event_type" name="event_type">
              <option value=""><?php esc_html_e('All Events', 'wp-update-tracker'); ?></option>
              <option value="core_update" <?php selected($event_type, 'core_update'); ?>><?php esc_html_e('Core Updates', 'wp-update-tracker'); ?></option>
              <option value="plugin_update" <?php selected($event_type, 'plugin_update'); ?>><?php esc_html_e('Plugin Updates', 'wp-update-tracker'); ?></option>
              <option value="theme_update" <?php selected($event_type, 'theme_update'); ?>><?php esc_html_e('Theme Updates', 'wp-update-tracker'); ?></option>
              <option value="settings_change" <?php selected($event_type, 'settings_change'); ?>><?php esc_html_e('Settings Changes', 'wp-update-tracker'); ?></option>
          </select>

          <!-- Date Range Filters -->
          <label for="start_date"><?php esc_html_e('Start Date:', 'wp-update-tracker'); ?></label>
          <input type="date" id="start_date" name="start_date" value="<?php echo esc_attr($start_date); ?>">

          <label for="end_date"><?php esc_html_e('End Date:', 'wp-update-tracker'); ?></label>
          <input type="date" id="end_date" name="end_date" value="<?php echo esc_attr($end_date); ?>">

          <!-- Submit and Export Buttons -->
          <button type="submit" class="button"><?php esc_html_e('Filter', 'wp-update-tracker'); ?></button>
          <button type="submit" name="export_csv" value="1" class="button button-secondary">
              <?php esc_html_e('Export as CSV', 'wp-update-tracker'); ?>
          </button>
      </form>

      <!-- Log Table -->
      <table class="widefat striped">
          <thead>
              <tr>
                  <th><?php esc_html_e('Timestamp', 'wp-update-tracker'); ?></th>
                  <th><?php esc_html_e('Event Type', 'wp-update-tracker'); ?></th>
                  <th><?php esc_html_e('Details', 'wp-update-tracker'); ?></th>
              </tr>
          </thead>
          <tbody>
              <?php if ($logs): ?>
                  <?php foreach ($logs as $log): ?>
                      <tr>
                          <td><?php echo esc_html($log->timestamp); ?></td>
                          <td><?php echo esc_html($log->event_type); ?></td>
                          <td><?php echo esc_html($log->event_details); ?></td>
                      </tr>
                  <?php endforeach; ?>
              <?php else: ?>
                  <tr>
                      <td colspan="3"><?php esc_html_e('No logs found.', 'wp-update-tracker'); ?></td>
                  </tr>
              <?php endif; ?>
          </tbody>
      </table>

      <!-- Pagination -->
      <div class="tablenav bottom">
          <div class="tablenav-pages">
              <?php
              $base_url = add_query_arg([
                  'paged' => '%#%',
                  'event_type' => $event_type,
                  'start_date' => $start_date,
                  'end_date' => $end_date,
              ], menu_page_url('wp-update-tracker-logs', false));

              $pagination_links = paginate_links([
                  'base'      => $base_url,
                  'format'    => '',
                  'current'   => $current_page,
                  'total'     => $total_pages,
                  'prev_text' => __('« Previous', 'wp-update-tracker'),
                  'next_text' => __('Next »', 'wp-update-tracker'),
                  'type'      => 'array',
              ]);

              if ($pagination_links) {
                  echo '<ul class="wp-update-tracker-pagination">';
                  foreach ($pagination_links as $link) {
                      echo '<li>' . $link . '</li>';
                  }
                  echo '</ul>';
              }
              ?>
          </div>
      </div>
  </div>
  <?php
}






}
