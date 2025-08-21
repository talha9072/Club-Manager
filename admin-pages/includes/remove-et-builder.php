<?php

function remove_et_visual_builder_button() {
    if (!current_user_can('manage_options')) { // Only allow admins to see it
        remove_action('admin_bar_menu', 'et_fb_add_admin_bar_items', 999);
    }
}
add_action('wp_before_admin_bar_render', 'remove_et_visual_builder_button');

function hide_et_visual_builder_and_cta_with_css() {
    if (!current_user_can('manage_options')) { // Hides for non-admins
        echo '<style>
            #wp-admin-bar-et-use-visual-builder { display: none !important; } /* Hide "Enable Visual Builder" */
            #et_pb_fb_cta { display: none !important; } /* Hide "Build On The Front End" button */
        </style>';
    }
}
add_action('admin_head', 'hide_et_visual_builder_and_cta_with_css');
add_action('wp_head', 'hide_et_visual_builder_and_cta_with_css');
