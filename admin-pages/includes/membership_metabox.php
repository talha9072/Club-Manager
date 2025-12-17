<?php
// Add meta box for Subscription products only
add_action('add_meta_boxes', 'add_gravity_form_meta_box');

// Save selected Gravity Form
add_action('save_post', 'save_gravity_form_meta_data');

/**
 * Add Gravity Form meta box only for Subscription products
 */
function add_gravity_form_meta_box() {
    add_meta_box(
        'select_gravity_form_meta_box',
        'Select Registration Form',
        'gravity_form_meta_box_callback',
        'product',
        'side',
        'high'
    );
}

/**
 * Check if product is a subscription type
 */
function is_subscription_product($post_id) {
    $product_types = wp_get_post_terms($post_id, 'product_type', ['fields' => 'names']);

    return in_array('subscription', $product_types) || in_array('variable-subscription', $product_types);
}

/**
 * Render meta box
 */
function gravity_form_meta_box_callback($post) {

    // Show only for subscription products
    if (!is_subscription_product($post->ID)) {
        echo '<p>This option is only available for Subscription products.</p>';
        return;
    }

    // Get saved Gravity Form ID
    $selected_form_id = get_post_meta($post->ID, '_select_registration_form', true);

    // Fetch Gravity Forms
    if (class_exists('GFAPI')) {
        $forms = GFAPI::get_forms();
    } else {
        $forms = [];
    }

    echo '<label for="select_gravity_form">Select Registration Form:</label>';
    echo '<select name="select_gravity_form" id="select_gravity_form">';
    echo '<option value="">Select a Form</option>';

    if (!empty($forms)) {
        foreach ($forms as $form) {
            $selected = selected($form['id'], $selected_form_id, false);
            echo '<option value="' . esc_attr($form['id']) . '" ' . $selected . '>' . esc_html($form['title']) . '</option>';
        }
    } else {
        echo '<option value="">No Forms Available</option>';
    }

    echo '</select>';

    // Security nonce
    wp_nonce_field('save_gravity_form_nonce', 'gravity_form_nonce');
}

/**
 * Save Gravity Form meta
 */
function save_gravity_form_meta_data($post_id) {

    // Security checks
    if (!isset($_POST['gravity_form_nonce']) || !wp_verify_nonce($_POST['gravity_form_nonce'], 'save_gravity_form_nonce')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Save only for subscription products
    if (!is_subscription_product($post_id)) {
        return;
    }

    // Save form ID
    if (isset($_POST['select_gravity_form'])) {
        $selected_form_id = sanitize_text_field($_POST['select_gravity_form']);

        if (!empty($selected_form_id)) {
            update_post_meta($post_id, '_select_registration_form', $selected_form_id);
        } else {
            delete_post_meta($post_id, '_select_registration_form');
        }
    }
}
