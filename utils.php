<?php

function load_svg($svg_path) {
    $icon_path = plugin_dir_path(__FILE__) . $svg_path;
    if (file_exists($icon_path)) {
        return 'data:image/svg+xml;base64,' . base64_encode(file_get_contents($icon_path));
    }
    return '';
}

function register_openkbs_app() {
    if (isset($_POST['kbId']) && isset($_POST['apiKey']) && isset($_POST['kbTitle']) && isset($_POST['AESKey'])) {
        $kbId = sanitize_text_field($_POST['kbId']);
        $apiKey = sanitize_text_field($_POST['apiKey']);
        $kbTitle = sanitize_text_field($_POST['kbTitle']);
        $AESKey = sanitize_text_field($_POST['AESKey']);
        $wpapiKey = wp_generate_password(20, true, false);
        
        $apps = get_option('openkbs_apps', array());
        $new_app_id = uniqid('app_');
        
        $apps[$new_app_id] = array(
            'kbId' => $kbId,
            'apiKey' => $apiKey,
            'kbTitle' => $kbTitle,
            'AESKey' => $AESKey,
            'wpapiKey' => $wpapiKey
        );

        update_option('openkbs_apps', $apps);
        wp_send_json_success('App registered successfully');
    } else {
        wp_send_json_error('Incomplete data provided');
    }
}

function delete_openkbs_app() {
    if (isset($_POST['app_id'])) {
        $app_id = sanitize_text_field($_POST['app_id']);
        $apps = get_option('openkbs_apps', array());
        
        if (isset($apps[$app_id])) {
            unset($apps[$app_id]);
            update_option('openkbs_apps', $apps);
            wp_send_json_success('App deleted successfully');
        } else {
            wp_send_json_error('App not found');
        }
    } else {
        wp_send_json_error('No app ID provided');
    }
}

function modify_admin_footer_text() {
    return '';
}

function remove_update_footer() {
    return '';
}