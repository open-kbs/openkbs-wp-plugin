<?php

function add_admin_menu() {
    add_menu_page(
        'OpenKBS',
        'OpenKBS',
        'manage_options',
        'openkbs-main-menu',
        'blueprints_page',
        load_svg('assets/icon.svg'),
        6
    );

    // Add Blueprints page
    add_submenu_page(
        'openkbs-main-menu',
        'Blueprints',
        'AI Blueprints',
        'manage_options',
        'openkbs-main-menu',
        'blueprints_page'
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
            'render_app_page'
        );
    }

    // Add Settings page at the bottom
    add_submenu_page(
        'openkbs-main-menu',
        'OpenKBS Settings',
        'Settings',
        'manage_options',
        'openkbs-settings',
        'settings_page'
    );
}

function blueprints_page() {
    $is_localhost = explode(':', $_SERVER['HTTP_HOST'])[0] === 'localhost';
    $blueprints_url = $is_localhost 
        ? 'http://localhost:3002/wordpress-ai-plugin-blueprints/' 
        : 'https://openkbs.com/wordpress-ai-plugin-blueprints/';
    
    render_iframe($blueprints_url);
}

function render_app_page() {
    $current_page = $_GET['page'];
    $app_id = str_replace('openkbs-app-', '', $current_page);
    $apps = get_option('openkbs_apps', array());
    
    if (isset($apps[$app_id])) {
        $is_localhost = explode(':', $_SERVER['HTTP_HOST'])[0] === 'localhost';
        $app_url = $is_localhost 
            ? 'http://' . $apps[$app_id]['kbId'] . '.apps.localhost:3002'
            : 'https://' . $apps[$app_id]['kbId'] . '.apps.openkbs.com';
        
        render_iframe($app_url);
    }
}

function render_iframe($url) {
    ?>
    <div class="wrap" style="margin: 0; padding: 0; margin-left: -20px; margin-bottom: -66px;">
        <iframe id="openkbs-iframe" src="<?php echo esc_url($url); ?>" width="100%" style="border: none;"></iframe>
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

function settings_page() {
    $apps = get_option('openkbs_apps', array());
    ?>
    <div class="wrap">
        <h2>OpenKBS Apps Settings</h2>
        <form method="post" action="options.php">
            <?php settings_fields('openkbs_settings'); ?>
            
            <?php foreach ($apps as $app_id => $app): ?>
            <div class="app-settings" style="margin-bottom: 30px; padding: 20px; background: #fff; border: 1px solid #ccc;">
                <h3><a href="https://<?php echo $app_id; ?>.apps.openkbs.com" target="_blank" ><?php echo esc_attr($app['kbTitle']); ?></a></h3>
                <img src="https://file.openkbs.com/kb-image/<?php echo $app_id; ?>.png" width="128"  />    
                <table class="form-table">
                    <tr>
                        <th scope="row">Menu Title</th>
                        <td>
                            <input type="text" name="openkbs_apps[<?php echo $app_id; ?>][kbTitle]" 
                                   value="<?php echo esc_attr($app['kbTitle']); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">OpenKBS API Key</th>
                        <td>
                            <input type="password" name="openkbs_apps[<?php echo $app_id; ?>][apiKey]" 
                                   value="<?php echo esc_attr($app['apiKey']); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">AES Key</th>
                        <td>
                            <input type="password" name="openkbs_apps[<?php echo $app_id; ?>][AESKey]" 
                                   value="<?php echo esc_attr($app['AESKey']); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">WP API Key</th>
                        <td>
                            <input type="password" name="openkbs_apps[<?php echo $app_id; ?>][wpapiKey]" 
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

function register_settings() {
    register_setting('openkbs_settings', 'openkbs_apps');
}