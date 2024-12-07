<?php

require_once plugin_dir_path(__FILE__) . 'openkbs-utils.php';

class OpenKBSMetaPluginAPI {
    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    public function register_routes() {
        register_rest_route('openkbs/v1', '/plugins/list', array(
            'methods' => 'GET',
            'callback' => array($this, 'list_plugins'),
            'permission_callback' => function() {
                return true;
            }
        ));

        register_rest_route('openkbs/v1', '/plugins/activate', array(
            'methods' => 'POST',
            'callback' => array($this, 'activate_plugin_handler'),
            'permission_callback' => function() {
                return true;
            }
        ));

        register_rest_route('openkbs/v1', '/plugins/deactivate', [
            'methods' => 'POST',
            'callback' => [$this, 'deactivate_plugin_handler'],
            'permission_callback' => function() {
                return true;
            }
        ]);
    }

    // Lists all plugins with their status
    public function list_plugins() {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        $all_plugins = get_plugins();
        $active_plugins = get_option('active_plugins');

        foreach($all_plugins as $plugin_path => $plugin) {
            $all_plugins[$plugin_path]['is_active'] = in_array($plugin_path, $active_plugins);
        }

        return new WP_REST_Response($all_plugins, 200);
    }

    public function activate_plugin_handler(WP_REST_Request $request) {
        try {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
            $plugin_path = $request->get_param('plugin_path');

            // Use try-catch to catch any PHP errors/exceptions
            try {
                $result = activate_plugin($plugin_path);
            } catch (Throwable $e) {
                return new WP_REST_Response(['error' => $e->getMessage()], 500);
            }

            if (is_wp_error($result)) {
                return new WP_REST_Response([
                    'status' => 'error',
                    'message' => $result->get_error_message()
                ], 400);
            }

            return new WP_REST_Response(['status' => 'success'], 200);

        } catch (Throwable $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 500);
        }
    }

    public function deactivate_plugin_handler(WP_REST_Request $request) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        $plugin_path = $request->get_param('plugin_path');
        
        deactivate_plugins($plugin_path);
        
        if (is_plugin_active($plugin_path)) {
            return new WP_REST_Response([
                'status' => 'error',
                'message' => 'Failed to deactivate plugin'
            ], 400);
        }
    
        return new WP_REST_Response(['status' => 'success'], 200);
    }
}

// Initialize the API
new OpenKBSMetaPluginAPI();