<?php
/*
Plugin Name: OpenKBS AI Plugin
Description: Connect Agentic AI to your WordPress
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
        if ($result !== null) {
            return $result;
        }

        $stored_api_key = get_option('openkbs_api_key');
        $api_key_header = isset($_SERVER['HTTP_X_API_KEY']) ? $_SERVER['HTTP_X_API_KEY'] : '';

        if (empty($api_key_header)) {
            return true;
        }

        if ($api_key_header !== $stored_api_key) {
            return new WP_Error(
                'rest_forbidden',
                'Invalid API key provided.',
                array('status' => 403)
            );
        }

        wp_set_current_user(1);
        return true;
    }

    public function add_admin_menu() {
        add_menu_page(
            'OpenKBS',
            'OpenKBS',
            'manage_options',
            'openkbs-main-menu',
            array($this, 'registration_page'),
            'dashicons-admin-generic',
            6
        );

        add_submenu_page(
            'openkbs-main-menu',
            'OpenKBS AI Settings',
            'Settings',
            'manage_options',
            'openkbs-ai-settings',
            array($this, 'settings_page')
        );
    }

    public function register_settings() {
        register_setting('openkbs_settings', 'openkbs_api_key');
    }

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

    public function registration_page() {
        ?>
        <div class="wrap">
            <h2>OpenKBS Registration</h2>
            <p>To use the OpenKBS AI Plugin, please register your site.</p>
            <a href="https://openkbs.com/install/wordpressv01/" target="_blank" class="button button-primary">Register Now</a>
        </div>
        <?php
    }
}

new OpenKBSAIPlugin();