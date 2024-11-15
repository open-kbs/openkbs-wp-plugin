<?php

function load_svg($svg_path) {
    $icon_path = plugin_dir_path(__FILE__) . $svg_path;
    if (file_exists($icon_path)) {
        return 'data:image/svg+xml;base64,' . base64_encode(file_get_contents($icon_path));
    }
    return '';
}

function evpKDF($password, $salt, $keySize, $ivSize) {
    $targetKeySize = $keySize + $ivSize;
    $derivedBytes = '';
    $block = '';
    while (strlen($derivedBytes) < $targetKeySize) {
        $block = md5($block . $password . $salt, true);
        $derivedBytes .= $block;
    }
    $key = substr($derivedBytes, 0, $keySize);
    $iv = substr($derivedBytes, $keySize, $ivSize);
    return array('key' => $key, 'iv' => $iv);
}

function encrypt_kb_item($item, $passphrase) {
    // Ensure passphrase and item are in binary format
    $passphrase = mb_convert_encoding($passphrase, 'UTF-8');
    $item = mb_convert_encoding($item, 'UTF-8');

    // Generate an 8-byte (64-bit) salt
    $salt = openssl_random_pseudo_bytes(8);

    // Derive key and IV using the EVP key derivation function
    $keySize = 32; // 256 bits
    $ivSize = 16;  // 128 bits
    $derived = evpKDF($passphrase, $salt, $keySize, $ivSize);
    $key = $derived['key'];
    $iv = $derived['iv'];
    $encrypted = openssl_encrypt($item, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
    $encryptedData = 'Salted__' . $salt . $encrypted;
    return base64_encode($encryptedData);
}

function create_secret_with_kb_token($secret_name, $secret_value, $token) {
    $response = wp_remote_post('https://kb.openkbs.com/', array(
        'body' => json_encode(array(
            'token' => $token,
            'action' => 'createSecretWithKBToken',
            'secretName' => $secret_name,
            'secretValue' => $secret_value
        )),
        'headers' => array(
            'Content-Type' => 'application/json'
        )
    ));
    
    if (is_wp_error($response)) {
        return false;
    }
    
    $body = wp_remote_retrieve_body($response);
    $result = json_decode($body, true);
    
    return isset($result['success']) && $result['success'] === true;
}

function register_openkbs_app() {
    if (isset($_POST['JWT']) && isset($_POST['kbId']) && isset($_POST['apiKey']) && isset($_POST['kbTitle']) && isset($_POST['AESKey'])) {
        $jwt = sanitize_text_field($_POST['JWT']);
        $kbId = sanitize_text_field($_POST['kbId']);
        $apiKey = sanitize_text_field($_POST['apiKey']);
        $kbTitle = sanitize_text_field($_POST['kbTitle']);
        $AESKey = sanitize_text_field($_POST['AESKey']);
        $wpapiKey = wp_generate_password(20, true, false);
        
        // First, encrypt the wordpress APIKey
        $encrypted_wpapi_key = encrypt_kb_item($wpapiKey, $AESKey);
        
        // Create the secret via openkbs API
        $api_response = create_secret_with_kb_token('wpapiKey', $encrypted_wpapi_key, $jwt);
        
        if ($api_response === false) {
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
            'appId' => $new_app_id,
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

function modify_admin_footer_text() {
    return '';
}

function remove_update_footer() {
    return '';
}