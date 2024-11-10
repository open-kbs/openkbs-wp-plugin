<?php
/*
Plugin Name: OpenKBS AI Plugin
Description: Enables API key authentication for WordPress REST API
Version: 1.0
Author: kbMaster
*/

class OpenKBSAIPlugin {
    public function __construct() {
        add_action('rest_api_init', array($this, 'register_api_key_authentication'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function register_api_key_authentication() {
        add_filter('rest_authentication_errors', array($this, 'validate_api_key'));
    }

    public function validate_api_key($result) {
        // If another authentication method is being used, don't interrupt it
        if ($result !== null) {
            return $result;
        }

        // Get the API key from the settings
        $stored_api_key = get_option('openkbs_api_key');

        // Get the API key from the request header
        $api_key_header = isset($_SERVER['HTTP_X_API_KEY']) ? $_SERVER['HTTP_X_API_KEY'] : '';

        // If no API key is provided in the header
        if (empty($api_key_header)) {
            return true; // Allow the request to continue for normal WordPress authentication
        }

        // Validate the API key
        if ($api_key_header !== $stored_api_key) {
            return new WP_Error(
                'rest_forbidden',
                'Invalid API key provided.',
                array('status' => 403)
            );
        }

        // API key is valid, set current user to admin
        wp_set_current_user(1); // Assumes admin is user ID 1
        return true;
    }

    // Add admin menu page
    public function add_admin_menu() {
        add_options_page(
            'OpenKBS AI Settings',
            'OpenKBS AI',
            'manage_options',
            'openkbs-ai-settings',
            array($this, 'settings_page')
        );
    }

    // Register settings
    public function register_settings() {
        register_setting('openkbs_settings', 'openkbs_api_key');
    }

    // Settings page HTML
    public function settings_page() {
        ?>
        <div class="wrap">
            <h2>OpenKBS AI Settings</h2>
            <form method="post" action="options.php">
                <?php
                settings_fields('openkbs_settings');
                do_settings_sections('openkbs_settings');
                ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">API Key</th>
                        <td>
                            <input type="text" name="openkbs_api_key" 
                                   value="<?php echo esc_attr(get_option('openkbs_api_key')); ?>" 
                                   class="regular-text">
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}

// Initialize the plugin
new OpenKBSAIPlugin();