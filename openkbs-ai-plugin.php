<?php
/*
Plugin Name: OpenKBS AI Plugin
Description: Connect AI Agents to your WordPress
Version: 1.1
Author: kbMaster
Text Domain: openkbs-ai
Domain Path: /languages
*/

require_once plugin_dir_path(__FILE__) . 'utils.php';
require_once plugin_dir_path(__FILE__) . 'admin.php';
require_once plugin_dir_path(__FILE__) . 'api.php';

class OpenKBSAIPlugin {
    public function __construct() {
        add_action('rest_api_init', array($this, 'register_api_key_authentication'));
        add_action('wp_ajax_register_openkbs_app', 'register_openkbs_app');
        add_action('wp_ajax_nopriv_register_openkbs_app', 'register_openkbs_app');
        add_action('wp_ajax_delete_openkbs_app', 'delete_openkbs_app');
        add_action('admin_enqueue_scripts', 'enqueue_openkbs_scripts');
        add_filter('admin_footer_text', 'modify_admin_footer_text');
        add_filter('update_footer', 'remove_update_footer', 11);
    }

    public function register_api_key_authentication() {
        add_filter('rest_authentication_errors', array($this, 'validate_api_key'));
    }

    public function validate_api_key($result) {
        if ($result !== null) {
            return $result;
        }

        $api_key_header = isset($_SERVER['HTTP_X_API_KEY']) ? $_SERVER['HTTP_X_API_KEY'] : '';
        if (empty($api_key_header)) {
            return true;
        }

        $apps = get_option('openkbs_apps', array());
        $valid_key = false;
        foreach ($apps as $app) {
            if ($api_key_header === $app['wpapiKey']) {
                $valid_key = true;
                break;
            }
        }

        if (!$valid_key) {
            return new WP_Error(
                'rest_forbidden',
                'Invalid API key provided.',
                array('status' => 403)
            );
        }

        wp_set_current_user(1);
        return true;
    }
}

new OpenKBSAIPlugin();

// Hook the admin menu and settings functions directly
add_action('admin_menu', 'add_admin_menu');
add_action('admin_init', 'register_settings');