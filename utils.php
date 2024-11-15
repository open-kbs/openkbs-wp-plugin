<?php

function load_svg($svg_path) {
    $icon_path = plugin_dir_path(__FILE__) . $svg_path;
    if (file_exists($icon_path)) {
        return 'data:image/svg+xml;base64,' . base64_encode(file_get_contents($icon_path));
    }
    return '';
}

function enqueue_openkbs_scripts() {
    wp_enqueue_script(
        'openkbs-connection',
        plugins_url('js/openkbs-connection.js', __FILE__),
        array('jquery'),
        '1.0.0',
        true
    );

    wp_localize_script('openkbs-connection', 'openkbsVars', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('openkbs-connection-nonce'),
        'i18n' => array(
            'connectToOpenKBS' => __('Connect to OpenKBS', 'openkbs-ai'),
            'requestingAccess' => __('OpenKBS is requesting access to your WordPress site.', 'openkbs-ai'),
            'knowledgeBase' => __('Knowledge Base:', 'openkbs-ai'),
            'cancel' => __('Cancel', 'openkbs-ai'),
            'approveConnection' => __('Approve', 'openkbs-ai')
        )
    ));
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
    $passphrase = mb_convert_encoding($passphrase, 'UTF-8');
    $item = mb_convert_encoding($item, 'UTF-8');

    $salt = openssl_random_pseudo_bytes(8);

    $keySize = 32;
    $ivSize = 16;
    $derived = evpKDF($passphrase, $salt, $keySize, $ivSize);
    $key = $derived['key'];
    $iv = $derived['iv'];
    $encrypted = openssl_encrypt($item, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
    $encryptedData = 'Salted__' . $salt . $encrypted;
    return base64_encode($encryptedData);
}

function store_secret($secret_name, $secret_value, $token) {
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

function modify_admin_footer_text() {
    return '';
}

function remove_update_footer() {
    return '';
}