<?php
/*
    Plugin Name: OpenKBS AI Plugin
    Description: Connect AI Agents to your WordPress
    Version: 1.1
    Author: kbMaster
    Text Domain: openkbs-ai
    Domain Path: /languages
*/

require_once plugin_dir_path(__FILE__) . 'src/openkbs-utils.php';
require_once plugin_dir_path(__FILE__) . 'src/openkbs-admin.php';
require_once plugin_dir_path(__FILE__) . 'src/openkbs-api.php';
require_once plugin_dir_path(__FILE__) . 'src/openkbs-filesystem-api.php';
require_once plugin_dir_path(__FILE__) . 'src/openkbs-meta-plugin-api.php';
require_once plugin_dir_path(__FILE__) . 'src/events-woo.php';
require_once plugin_dir_path(__FILE__) . 'src/events-wpcf7.php';

class OpenKBSAIPlugin {
    // Whitelist of API namespaces that can be accessed with HTTP_WP_API_KEY
    private $allowed_api_namespaces = [
        'wp/v2',
        'wc/v3',
        'openkbs/v1'
    ];

    public function __construct() {
        add_action('rest_api_init', array($this, 'register_api_key_authentication'), 15);
        add_action('rest_api_init', array($this, 'register_openkbs_endpoints'));
        add_action('wp_ajax_register_openkbs_app', 'register_openkbs_app');
        add_action('wp_ajax_nopriv_register_openkbs_app', 'register_openkbs_app');
        add_action('wp_ajax_delete_openkbs_app', 'delete_openkbs_app');
        add_action('admin_enqueue_scripts', 'enqueue_openkbs_scripts');
        add_action('admin_enqueue_scripts', 'enqueue_openkbs_polling_scripts');
        add_action('wp_ajax_openkbs_check_callback', 'handle_openkbs_polling');
        add_action('wp_ajax_toggle_filesystem_api', 'handle_filesystem_api_toggle');

        add_filter('admin_footer_text', 'modify_admin_footer_text');
        add_filter('update_footer', 'remove_update_footer', 11);

        // Events
        add_action('init', 'hook_woocommerce_events');
        add_action('init', 'hook_wpcf7_events');
    }

    public function register_api_key_authentication() {
        // Run after default authentication (which is at priority 10)
        add_filter('rest_authentication_errors', array($this, 'validate_api_key'), 90);
    }

    public function register_openkbs_endpoints() {
        register_rest_route('openkbs/v1', '/callback', array(
            'methods' => 'POST',
            'callback' => 'handle_openkbs_callback',
            'permission_callback' => array($this, 'check_openkbs_permission')
        ));
    }

    public function check_openkbs_permission() {
        $api_key_header = isset($_SERVER['HTTP_WP_API_KEY']) ? $_SERVER['HTTP_WP_API_KEY'] : '';
        if (empty($api_key_header) || !$this->validate_api_key_against_db($api_key_header)) {
            return new WP_Error(
                'rest_forbidden',
                'Invalid or missing API key for OpenKBS endpoint.',
                array('status' => 403)
            );
        }

        $this->set_current_user_with_full_access();
        return true;
    }

    public function validate_api_key($result) {
        // If another authentication method has already failed, return that error
        if (is_wp_error($result)) {
            return $result;
        }

        $api_key_header = isset($_SERVER['HTTP_WP_API_KEY']) ? $_SERVER['HTTP_WP_API_KEY'] : '';
        $current_route = $this->get_current_route();
        $is_allowed_namespace = $this->is_allowed_namespace($current_route);

        // If API key is provided and route is in allowed namespaces
        if (!empty($api_key_header) && $is_allowed_namespace) {
            if ($this->validate_api_key_against_db($api_key_header)) {
                $this->set_current_user_with_full_access();
                return null; // Proceed with request
            } else {
                return new WP_Error(
                    'rest_forbidden',
                    'Invalid API key provided.',
                    array('status' => 403)
                );
            }
        }

        // For OpenKBS endpoints, always require API key
        if (strpos($current_route, 'openkbs/v1') === 0) {
            return new WP_Error(
                'rest_forbidden',
                'API key required for OpenKBS endpoints.',
                array('status' => 403)
            );
        }

        // For all other routes, do not interfere
        return null;
    }

    private function is_allowed_namespace($route) {
        foreach ($this->allowed_api_namespaces as $namespace) {
            if (strpos($route, $namespace) === 0) {
                return true;
            }
        }
        return false;
    }

    private function get_current_route() {
        $rest_route = null;

        if (isset($_GET['rest_route'])) {
            $rest_route = $_GET['rest_route'];
        } else {
            $request_uri = $_SERVER['REQUEST_URI'];
            $home_path = parse_url(home_url(), PHP_URL_PATH);
            $request_path = parse_url($request_uri, PHP_URL_PATH);

            if ($home_path !== null) {
                $request_path = preg_replace('#^' . preg_quote($home_path) . '#', '', $request_path);
            }

            if (strpos($request_path, '/wp-json/') === 0) {
                $rest_route = substr($request_path, strlen('/wp-json/'));
            }
        }

        if ($rest_route === null) {
            return '';
        }

        return trim($rest_route, '/');
    }

    private function is_protected_route($route) {
        foreach ($this->allowed_api_namespaces as $namespace) {
            if (strpos($route, $namespace) === 0) {
                return true;
            }
        }
        return false;
    }

    private function validate_api_key_against_db($api_key) {
        $apps = get_option('openkbs_apps', array());
        foreach ($apps as $app) {
            if (hash_equals($app['wpapiKey'], $api_key)) {
                return true;
            }
        }
        return false;
    }

    private function set_current_user_with_full_access() {
        $username = 'openkbs_api_user';
        // Check if the user already exists
        $user = get_user_by('login', $username);

        if (!$user) {
            // Generate secure random email and password
            $random_suffix = wp_generate_password(12, false, false);
            $random_email = $username . '@random' . $random_suffix . '.com';
            $random_password = wp_generate_password(20, true, true);
            // Create the new user
            $user_id = wp_create_user($username, $random_password, $random_email);
            $user = new WP_User($user_id);

            // Assign administrator role
            $user->set_role('administrator');
        }
        // Set the current user to the newly created user
        wp_set_current_user($user->ID);
    }
}

new OpenKBSAIPlugin();

// Hook the admin menu and settings functions directly
add_action('admin_menu', 'add_admin_menu');
add_action('admin_init', 'register_settings');