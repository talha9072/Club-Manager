<?php
// Hook into WordPress to create the meta box only for Membership products
add_action('add_meta_boxes', 'add_gravity_form_meta_box');

// Hook into WordPress to save the selected Gravity Form
add_action('save_post', 'save_gravity_form_meta_data');

/**
 * Function to add the Gravity Form meta box for Membership products only
 */
function add_gravity_form_meta_box() {
    add_meta_box(
        'select_gravity_form_meta_box', // Unique ID of the meta box
        'Select Registration Form',      // Title of the meta box
        'gravity_form_meta_box_callback', // Callback function
        'product',                       // Post type: WooCommerce Product
        'side',                           // Placement: Side panel
        'high'                            // Priority
    );
}

/**
 * Callback function to render the meta box
 */
function gravity_form_meta_box_callback($post) {
    // Get saved Gravity Form ID
    $selected_form_id = get_post_meta($post->ID, '_select_registration_form', true);

    // Get the product categories
    $product_categories = wp_get_post_terms($post->ID, 'product_cat', array('fields' => 'names'));

    // Check if the product belongs to the "Membership" category
    if (!in_array('Membership', $product_categories)) {
        echo '<p>This option is only available for Membership products.</p>';
        return;
    }

    // Fetch available Gravity Forms
    if (class_exists('GFAPI')) {
        $forms = GFAPI::get_forms();
    } else {
        $forms = [];
    }

    echo '<label for="select_gravity_form">Select Registration Form:</label>';
    echo '<select name="select_gravity_form" id="select_gravity_form">';
    
    // Default "Select" option
    echo '<option value="">Select a Form</option>';

    // Populate dropdown with Gravity Forms
    if (!empty($forms)) {
        foreach ($forms as $form) {
            $selected = ($form['id'] == $selected_form_id) ? 'selected="selected"' : '';
            echo '<option value="' . esc_attr($form['id']) . '" ' . $selected . '>' . esc_html($form['title']) . '</option>';
        }
    } else {
        echo '<option value="">No Forms Available</option>';
    }

    echo '</select>';

    // Add a nonce field for security
    wp_nonce_field('save_gravity_form_nonce', 'gravity_form_nonce');
}

/**
 * Function to save the selected Gravity Form ID as post meta
 */
function save_gravity_form_meta_data($post_id) {
    // Check for nonce security
    if (!isset($_POST['gravity_form_nonce']) || !wp_verify_nonce($_POST['gravity_form_nonce'], 'save_gravity_form_nonce')) {
        return $post_id;
    }

    // Check for auto-save
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return $post_id;
    }

    // Check user permissions
    if (!current_user_can('edit_post', $post_id)) {
        return $post_id;
    }

    // Get the product categories
    $product_categories = wp_get_post_terms($post_id, 'product_cat', array('fields' => 'names'));

    // Only save if the product is in the "Membership" category
    if (!in_array('Membership', $product_categories)) {
        return;
    }

    // Validate and sanitize the input
    if (isset($_POST['select_gravity_form'])) {
        $selected_form_id = sanitize_text_field($_POST['select_gravity_form']);

        // Ensure a valid form is selected before saving
        if (!empty($selected_form_id)) {
            update_post_meta($post_id, '_select_registration_form', $selected_form_id);
        }
    }
}
