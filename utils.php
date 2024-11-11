<?php

function load_svg($svg_path) {
    $icon_path = plugin_dir_path(__FILE__) . $svg_path;
    if (file_exists($icon_path)) {
        return 'data:image/svg+xml;base64,' . base64_encode(file_get_contents($icon_path));
    }
    return '';
}

function store_openkbs_kbId() {
    if (isset($_POST['kbId'])) {
        $kbId = sanitize_text_field($_POST['kbId']);
        update_option('openkbs_kbId', $kbId);
        wp_send_json_success('kbId stored');
    } else {
        wp_send_json_error('No kbId provided');
    }
}

function modify_admin_footer_text() {
    return '';
}

function remove_update_footer() {
    return '';
}