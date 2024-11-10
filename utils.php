<?php

function load_svg($svg_path) {
    $icon_path = plugin_dir_path(__FILE__) . $svg_path;
    if (file_exists($icon_path)) {
        return 'data:image/svg+xml;base64,' . base64_encode(file_get_contents($icon_path));
    }
}