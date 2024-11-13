<?php
/*
Plugin Name: OpenKBS AI Plugin
Description: Connect Agentic AI to your WordPress
Version: 1.1
Author: kbMaster
*/

require_once plugin_dir_path(__FILE__) . 'utils.php';

class OpenKBSAIPlugin {
    public function __construct() {
        add_action('rest_api_init', array($this, 'register_api_key_authentication'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_ajax_store_openkbs_kbId', 'store_openkbs_kbId');
        add_action('wp_ajax_nopriv_store_openkbs_kbId', 'store_openkbs_kbId');    
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

        // Example: http://localhost:3080/wp-json/wp/v2/pages/ -H 'X-API-Key: secret_key'
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
            array($this, 'home_page'),
            load_svg('assets/icon.svg'),
            6
        );

        add_submenu_page(
            'openkbs-main-menu',
            'Home',
            'Home',
            'manage_options',
            'openkbs-main-menu',
            array($this, 'home_page')
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
        register_setting('openkbs_settings', 'openkbs_kbId');
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
                            <input type="password" name="openkbs_api_key" 
                                   value="<?php echo esc_attr(get_option('openkbs_api_key')); ?>" 
                                   class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">KB ID</th>
                        <td>
                            <input type="text" name="openkbs_kbId" 
                                   value="<?php echo esc_attr(get_option('openkbs_kbId')); ?>" 
                                   class="regular-text">
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public function home_page() {
        $is_localhost = explode(':', $_SERVER['HTTP_HOST'])[0] === 'localhost';

        $home_url = $is_localhost 
            ? 'http://localhost:3002/wordpress-ai-plugin-blueprints/' 
            : 'https://openkbs.com/wordpress-ai-plugin-blueprints/';

        $kbId = get_option('openkbs_kbId', false);

        if ($kbId) {
            $home_url = $is_localhost 
                ? 'http://' . $kbId . '.apps.localhost:3002'
                : 'https://' . $kbId . '.apps.openkbs.com';
        }

        ?>
        <div class="wrap" style="margin: 0; padding: 0; margin-left: -20px; margin-bottom: -66px;">
            <iframe id="openkbs-iframe" src="<?php echo esc_url($home_url); ?>" width="100%" style="border: none;"></iframe>
        </div>
        <script type="text/javascript">
            document.addEventListener('DOMContentLoaded', function() {
                var iframe = document.getElementById('openkbs-iframe');
                function resizeIframe() {
                    var wpBarHeight = 38;
                    iframe.style.height = (window.innerHeight - wpBarHeight) + 'px';
                }
                window.addEventListener('resize', resizeIframe);
                resizeIframe();

                window.addEventListener('message', function(event) {    
                    if (!event.data || !event.data.type || event.data.type.indexOf('openkbs') !== 0 || !event.data.kbId) {
                        return;
                    }

                    var type = event.data.type
                    var kbId = event.data.kbId
                
                    if (!new RegExp('^https?://' + kbId + '\\.apps\\.(openkbs\\.com|localhost:\\d+)$').test(event.origin)) {
                        return;
                    }

                    if (type === 'openkbsKBLoggedIn') {                    
                        var xhr = new XMLHttpRequest();
                        xhr.open('POST', '<?php echo admin_url('admin-ajax.php'); ?>', true);
                        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                        xhr.onreadystatechange = function() {
                            if (xhr.readyState === 4 && xhr.status === 200) {
                                console.log('KB stored successfully');
                            }
                        };
                        xhr.send('action=store_openkbs_kbId&kbId=' + encodeURIComponent(kbId));
                    }
                });
            });
        </script>
        <?php
    }
}

new OpenKBSAIPlugin();