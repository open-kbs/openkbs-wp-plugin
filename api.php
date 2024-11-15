<?php

function register_openkbs_app() {
    if (isset($_POST['JWT']) && isset($_POST['kbId']) && isset($_POST['apiKey']) && isset($_POST['kbTitle']) && isset($_POST['AESKey'])) {
        $jwt = sanitize_text_field($_POST['JWT']);
        $kbId = sanitize_text_field($_POST['kbId']);
        $apiKey = sanitize_text_field($_POST['apiKey']);
        $kbTitle = sanitize_text_field($_POST['kbTitle']);
        $AESKey = sanitize_text_field($_POST['AESKey']);
        $wpapiKey = wp_generate_password(20, true, false);
        
        // =========================
        // Security Implementation
        // =========================

        // First level encryption with an in-browser generated AES key
        $encrypted_wpapi_key = encrypt_kb_item($wpapiKey, $AESKey);    
        $encrypted_site_url = encrypt_kb_item(get_site_url(), $AESKey);

        /*
        * Transmit to secret storage for second-level encryption with an asymmetric public key.
        * Only the code execution service can decrypt, as the storage lacks the private key.
        */
        $api_response = store_secret('wpapiKey', $encrypted_wpapi_key, $jwt);
        $url_response = store_secret('wpUrl', $encrypted_site_url, $jwt);
                
        if ($api_response === false || $url_response === false) {
            wp_send_json_error('Failed to create secret');
            return;
        }
        
        // If secret creation was successful, proceed with local storage
        $apps = get_option('openkbs_apps', array());
        if (!is_array($apps)) {
            $apps = array();
        }    
        
        $apps[$kbId] = array(
            'kbId' => $kbId,
            'apiKey' => $apiKey,
            'kbTitle' => $kbTitle,
            'AESKey' => $AESKey,
            'wpapiKey' => $wpapiKey
        );

        update_option('openkbs_apps', $apps);
        wp_send_json_success(array(
            'message' => 'App registered successfully',
            'appId' => $kbId,
            'redirect' => admin_url('admin.php?page=openkbs-app-' . $kbId)
        ));
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