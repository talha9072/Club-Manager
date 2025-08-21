<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Hook to ensure WordPress is fully initialized
add_action('init', 'gform_id_2_meta_handler_init');

function gform_id_2_meta_handler_init() {
    if (is_user_logged_in()) {
        add_filter('gform_pre_render_2', 'pre_populate_gform_2_fields');
    }

    add_action('gform_after_submission_2', 'save_gform_2_fields', 10, 2);
}

/**
 * Pre-populate Gravity Form fields for Form ID: 2
 */
function pre_populate_gform_2_fields($form) {
    global $wpdb;

    // Get person_id from URL
    $person_id = isset($_GET['person_id']) ? intval($_GET['person_id']) : 0;
    if (!$person_id) {
        return $form; // No person_id provided
    }

    // Get user's email based on person_id
    $user_email = $wpdb->get_var(
        $wpdb->prepare("SELECT user_email FROM {$wpdb->prefix}users WHERE ID = %d", $person_id)
    );

    if (!$user_email) {
        return $form; // No email found
    }

    // Get latest entry ID for Form ID 2 matching this user's email
    $entry_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT e.id FROM {$wpdb->prefix}gf_entry e
             WHERE e.form_id = 2 AND e.created_by = %d
             ORDER BY e.date_created DESC LIMIT 1",
            $person_id
        )
    );

    if (!$entry_id) {
        return $form; // No entry found
    }

    // Fetch all meta values for the entry
    $entry_meta = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT meta_key, meta_value FROM {$wpdb->prefix}gf_entry_meta WHERE entry_id = %d",
            $entry_id
        ),
        ARRAY_A
    );

    if (empty($entry_meta)) {
        return $form; // No meta found
    }

    // Map meta values
    $meta_map = wp_list_pluck($entry_meta, 'meta_value', 'meta_key');

    foreach ($form['fields'] as &$field) {
        if (isset($meta_map[$field->id])) {
            $field->defaultValue = $meta_map[$field->id];
        }

        // Handle complex/nested fields (e.g., Address fields)
        if (!empty($field->inputs)) {
            foreach ($field->inputs as &$input) {
                $nested_key = $input['id'];
                if (isset($meta_map[$nested_key])) {
                    $input['defaultValue'] = $meta_map[$nested_key];
                }
            }
        }
    }

    return $form;
}

/**
 * Save Gravity Form submission data
 */
function save_gform_2_fields($entry, $form) {
    global $wpdb;

    // Get person_id from URL
    $person_id = isset($_GET['person_id']) ? intval($_GET['person_id']) : 0;
    if (!$person_id) {
        return;
    }

    // Fetch the email for the specified person_id
    $user_email = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT user_email FROM {$wpdb->prefix}users WHERE ID = %d",
            $person_id
        )
    );

    if (!$user_email) {
        return;
    }

    // Get entry ID from the form submission
    $entry_id = rgar($entry, 'id');
    if (!$entry_id) {
        return;
    }

    // Update the `created_by` field in the main entry table
    $wpdb->update(
        "{$wpdb->prefix}gf_entry",
        [
            'created_by'  => $person_id,
            'ip'          => $_SERVER['REMOTE_ADDR'],
            'source_url'  => esc_url_raw($_SERVER['HTTP_REFERER']),
            'user_agent'  => esc_html($_SERVER['HTTP_USER_AGENT'])
        ],
        ['id' => $entry_id]
    );

    // Iterate through form fields and update entry meta
    foreach ($form['fields'] as $field) {
        $field_value = rgar($entry, $field->id);
        if (!empty($field_value)) {
            $exists = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}gf_entry_meta WHERE entry_id = %d AND meta_key = %s",
                    $entry_id,
                    $field->id
                )
            );

            if ($exists) {
                $wpdb->update(
                    "{$wpdb->prefix}gf_entry_meta",
                    ['meta_value' => $field_value],
                    ['entry_id' => $entry_id, 'meta_key' => $field->id]
                );
            } else {
                $wpdb->insert(
                    "{$wpdb->prefix}gf_entry_meta",
                    [
                        'form_id'    => $form['id'],
                        'entry_id'   => $entry_id,
                        'meta_key'   => $field->id,
                        'meta_value' => $field_value
                    ]
                );
            }
        }
    }
}




add_filter( 'gform_pre_render', 'auto_check_checkbox_for_logged_in_users' );
add_filter( 'gform_pre_validation', 'auto_check_checkbox_for_logged_in_users' );
add_filter( 'gform_pre_submission_filter', 'auto_check_checkbox_for_logged_in_users' );
add_filter( 'gform_admin_pre_render', 'auto_check_checkbox_for_logged_in_users' );

function auto_check_checkbox_for_logged_in_users( $form ) {
    if ( is_user_logged_in() ) {
        foreach ( $form['fields'] as &$field ) {
            if ( $field->id == 19 && $field->type == 'checkbox' ) {
                // Pre-check the checkbox option
                foreach ( $field->choices as &$choice ) {
                    $choice['isSelected'] = true;
                }

                // Make it read-only (prevent user from unchecking)
                $field->cssClass .= ' gf_readonly';
            }
        }
    }
    return $form;
}

// Enforce the selection on form submission
add_filter( 'gform_field_validation', 'enforce_checkbox_for_logged_in_users', 10, 4 );
function enforce_checkbox_for_logged_in_users( $result, $value, $form, $field ) {
    if ( is_user_logged_in() && $field->id == 19 && $field->type == 'checkbox' ) {
        if ( empty( $value ) ) {
            $result['is_valid'] = false;
            $result['message'] = 'This field is required for logged-in users.';
        }
    }
    return $result;
}

// Add CSS to visually disable the field
add_action( 'wp_footer', 'add_readonly_checkbox_css' );
function add_readonly_checkbox_css() {
    if ( is_user_logged_in() ) {
        echo '<style>.gf_readonly input[type="checkbox"] { pointer-events: none; opacity: 0.5; }</style>';
    }
}



function repair_gf_created_by_for_form_2() {
    global $wpdb;

    // Only run once every 24 hours
    if (get_transient('gf_repair_last_run')) {
        return;
    }

    $form_id = 2;
    $email_field_id = 7;
    $updated_users = [];

    // Get entries with NULL created_by
    $entries = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}gf_entry 
             WHERE form_id = %d AND created_by IS NULL",
            $form_id
        )
    );

    if (empty($entries)) {
        echo 'No orphaned entries found.<br>';
        set_transient('gf_repair_last_run', true, DAY_IN_SECONDS);
        return;
    }

    foreach ($entries as $entry) {
        $entry_id = $entry->id;

        // Get email from field 7
        $email = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT meta_value FROM {$wpdb->prefix}gf_entry_meta 
                 WHERE entry_id = %d AND meta_key = %d",
                $entry_id, $email_field_id
            )
        );

        if (!$email || !is_email($email)) {
            continue;
        }

        // Get user ID from email
        $user_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->prefix}users 
                 WHERE user_email = %s",
                $email
            )
        );

        if (!$user_id) {
            continue;
        }

        // Update created_by
        $updated = $wpdb->update(
            "{$wpdb->prefix}gf_entry",
            ['created_by' => $user_id],
            ['id' => $entry_id]
        );

        if ($updated !== false) {
            $updated_users[] = $user_id;
        }
    }

    // Output updated user IDs
    if (!empty($updated_users)) {
        echo 'Updated user IDs: ' . implode(', ', array_unique($updated_users)) . '<br>';
    } else {
        echo 'No entries updated.<br>';
    }

    // Set transient to prevent re-run for 24 hours
    set_transient('gf_repair_last_run', true, 3 * HOUR_IN_SECONDS);

}

// Automatically run the function on admin load (or use 'init' if needed sitewide)
add_action('admin_init', 'repair_gf_created_by_for_form_2');
