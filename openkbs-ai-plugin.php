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

class OpenKBSAIPlugin {
    public function __construct() {
        add_action('rest_api_init', array($this, 'register_api_key_authentication'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
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

    public function add_admin_menu() {
        add_menu_page(
            'OpenKBS',
            'OpenKBS',
            'manage_options',
            'openkbs-main-menu',
            array($this, 'blueprints_page'),
            load_svg('assets/icon.svg'),
            6
        );

        // Add Blueprints page
        add_submenu_page(
            'openkbs-main-menu',
            'Blueprints',
            'Blueprints',
            'manage_options',
            'openkbs-main-menu',
            array($this, 'blueprints_page')
        );

        // Add registered apps as submenu items
        $apps = get_option('openkbs_apps', array());
        foreach ($apps as $app_id => $app) {
            add_submenu_page(
                'openkbs-main-menu',
                $app['kbTitle'],
                $app['kbTitle'],
                'manage_options',
                'openkbs-app-' . $app_id,
                array($this, 'render_app_page')
            );
        }

        // Add Settings page at the bottom
        add_submenu_page(
            'openkbs-main-menu',
            'OpenKBS Settings',
            'Settings',
            'manage_options',
            'openkbs-settings',
            array($this, 'settings_page')
        );
    }

    public function blueprints_page() {
        $is_localhost = explode(':', $_SERVER['HTTP_HOST'])[0] === 'localhost';
        $blueprints_url = $is_localhost 
            ? 'http://localhost:3002/wordpress-ai-plugin-blueprints/' 
            : 'https://openkbs.com/wordpress-ai-plugin-blueprints/';
        
        $this->render_iframe($blueprints_url);
    }

    public function render_app_page() {
        $current_page = $_GET['page'];
        $app_id = str_replace('openkbs-app-', '', $current_page);
        $apps = get_option('openkbs_apps', array());
        
        if (isset($apps[$app_id])) {
            $is_localhost = explode(':', $_SERVER['HTTP_HOST'])[0] === 'localhost';
            $app_url = $is_localhost 
                ? 'http://' . $apps[$app_id]['kbId'] . '.apps.localhost:3002'
                : 'https://' . $apps[$app_id]['kbId'] . '.apps.openkbs.com';
            
            $this->render_iframe($app_url);
        }
    }

    private function render_iframe($url) {
        ?>
        <div class="wrap" style="margin: 0; padding: 0; margin-left: -20px; margin-bottom: -66px;">
            <iframe id="openkbs-iframe" src="<?php echo esc_url($url); ?>" width="100%" style="border: none;"></iframe>
        </div>
        <script type="text/javascript">
            document.addEventListener('DOMContentLoaded', function() {
                // Helper function to escape HTML
                function escapeHtml(unsafe) {
                    return unsafe
                        .replace(/&/g, "&amp;")
                        .replace(/</g, "&lt;")
                        .replace(/>/g, "&gt;")
                        .replace(/"/g, "&quot;")
                        .replace(/'/g, "&#039;");
                }

                function showWordPressConfirmation(kbTitle) {
                    return new Promise((resolve) => {
                        // Create modal wrapper
                        const modal = document.createElement('div');
                        modal.className = 'openkbs-modal-wrapper';
                        
                        // Create modal content
                        modal.innerHTML = `
                            <div class="openkbs-modal">
                                <div class="openkbs-modal-header">
                                    <h2>${openkbsVars.i18n.connectToOpenKBS}</h2>
                                </div>
                                <div class="openkbs-modal-content">
                                    <p>${openkbsVars.i18n.requestingAccess}</p>
                                    <p>${openkbsVars.i18n.knowledgeBase} <strong>${escapeHtml(kbTitle)}</strong></p>
                                </div>
                                <div class="openkbs-modal-footer">
                                    <button class="button button-secondary cancel-button">
                                        ${openkbsVars.i18n.cancel}
                                    </button>
                                    <button class="button button-primary approve-button">
                                        ${openkbsVars.i18n.approveConnection}
                                    </button>
                                </div>
                            </div>
                        `;

                        // Rest of the code remains the same...
                        const styles = document.createElement('style');
                        styles.textContent = `
                            .openkbs-modal-wrapper {
                                position: fixed;
                                top: 0;
                                left: 0;
                                right: 0;
                                bottom: 0;
                                background: rgba(0,0,0,0.7);
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                z-index: 159000;
                            }
                            
                            .openkbs-modal {
                                background: #ffffff;
                                border-radius: 3px;
                                box-shadow: 0 3px 6px rgba(0,0,0,0.3);
                                width: 500px;
                                max-width: 90%;
                                padding: 0;
                            }
                            
                            .openkbs-modal-header {
                                padding: 15px 20px;
                                border-bottom: 1px solid #ddd;
                            }
                            
                            .openkbs-modal-header h2 {
                                margin: 0;
                                font-size: 1.3em;
                                line-height: 1.5;
                            }
                            
                            .openkbs-modal-content {
                                padding: 20px;
                            }
                            
                            .openkbs-modal-footer {
                                padding: 15px 20px;
                                border-top: 1px solid #ddd;
                                text-align: right;
                            }
                            
                            .openkbs-modal-footer button {
                                margin-left: 10px;
                            }
                        `;

                        document.head.appendChild(styles);
                        document.body.appendChild(modal);

                        // Handle button clicks
                        modal.querySelector('.cancel-button').addEventListener('click', () => {
                            document.body.removeChild(modal);
                            resolve(false);
                        });

                        modal.querySelector('.approve-button').addEventListener('click', () => {
                            document.body.removeChild(modal);
                            resolve(true);
                        });
                    });
                }

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

                    var type = event.data.type;
                    var kbId = event.data.kbId;
                    var apiKey = event.data.apiKey;
                    var kbTitle = event.data.kbTitle;
                    var AESKey = event.data.AESKey;
                    var JWT = event.data.JWT;

                    if (!new RegExp('^https?://' + kbId + '\\.apps\\.(openkbs\\.com|localhost:\\d+)$').test(event.origin)) {
                        return;
                    }

                    if (type === 'openkbsKBInstalled') {
                        // Show confirmation dialog first
                        showWordPressConfirmation(kbTitle).then(confirmed => {
                            if (confirmed) {
                                var xhr = new XMLHttpRequest();
                                xhr.open('POST', ajaxurl, true);
                                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                                xhr.onreadystatechange = function() {
                                    if (xhr.readyState === 4 && xhr.status === 200) {
                                        var response = JSON.parse(xhr.responseText);
                                        if (response.success) {
                                            window.location.href = response.data.redirect;
                                        } else {
                                            console.error('Registration failed:', response.data);
                                        }
                                    }
                                };
                                xhr.send('action=register_openkbs_app&kbId=' + encodeURIComponent(kbId) +
                                        '&apiKey=' + encodeURIComponent(apiKey) +
                                        '&JWT=' + encodeURIComponent(JWT) +
                                        '&kbTitle=' + encodeURIComponent(kbTitle) +
                                        '&AESKey=' + encodeURIComponent(AESKey));
                            }
                        });
                    }
                });
            });
        </script>
        <?php
    }

    public function settings_page() {
        $apps = get_option('openkbs_apps', array());
        ?>
        <div class="wrap">
            <h2>OpenKBS Apps Settings</h2>
            <form method="post" action="options.php">
                <?php settings_fields('openkbs_settings'); ?>
                
                <?php foreach ($apps as $app_id => $app): ?>
                <div class="app-settings" style="margin-bottom: 30px; padding: 20px; background: #fff; border: 1px solid #ccc;">
                    <h3><?php echo esc_html($app['kbTitle']); ?></h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row">KB ID</th>
                            <td>
                                <input type="text" name="openkbs_apps[<?php echo $app_id; ?>][kbId]" 
                                       value="<?php echo esc_attr($app['kbId']); ?>" class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">OpenKBS API Key</th>
                            <td>
                                <input type="text" name="openkbs_apps[<?php echo $app_id; ?>][apiKey]" 
                                       value="<?php echo esc_attr($app['apiKey']); ?>" class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">KB Title</th>
                            <td>
                                <input type="text" name="openkbs_apps[<?php echo $app_id; ?>][kbTitle]" 
                                       value="<?php echo esc_attr($app['kbTitle']); ?>" class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">AES Key</th>
                            <td>
                                <input type="text" name="openkbs_apps[<?php echo $app_id; ?>][AESKey]" 
                                       value="<?php echo esc_attr($app['AESKey']); ?>" class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">WP API Key</th>
                            <td>
                                <input type="text" name="openkbs_apps[<?php echo $app_id; ?>][wpapiKey]" 
                                       value="<?php echo esc_attr($app['wpapiKey']); ?>" class="regular-text" readonly>
                            </td>
                        </tr>
                    </table>
                    <p>
                        <button type="button" class="button delete-app" data-app-id="<?php echo esc_attr($app_id); ?>">
                            Delete App
                        </button>
                    </p>
                </div>
                <?php endforeach; ?>

                <?php submit_button('Save All Apps'); ?>
            </form>
        </div>
        <script>
            jQuery(document).ready(function($) {
                $('.delete-app').click(function() {
                    if (confirm('Are you sure you want to delete this app?')) {
                        var appId = $(this).data('app-id');
                        $.post(ajaxurl, {
                            action: 'delete_openkbs_app',
                            app_id: appId
                        }, function(response) {
                            if (response.success) {
                                window.location.reload();
                            }
                        });
                    }
                });
            });
        </script>
        <?php
    }

    public function register_settings() {
        register_setting('openkbs_settings', 'openkbs_apps');
    }
}

new OpenKBSAIPlugin();