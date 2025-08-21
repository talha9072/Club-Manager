<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Hook to ensure WordPress is fully initialized
add_action('init', 'gform_id_22_meta_handler_init');

function gform_id_22_meta_handler_init() {
    global $wpdb;

    // Pre-populate Gravity Form fields for Form ID: 22
    if (is_user_logged_in()) {
        add_filter('gform_pre_render_22', 'pre_populate_gform_22_fields');
    }

    function pre_populate_gform_22_fields($form) {
        global $wpdb;

        // Get person_id (user ID) from URL parameter
        $person_id = isset($_GET['person_id']) ? intval($_GET['person_id']) : 0;
        if (!$person_id) {
            return $form; // Return form unmodified if no person_id
        }

        // Fetch the user's email based on person_id (user ID)
        $user_email = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT user_email FROM {$wpdb->prefix}users WHERE ID = %d",
                $person_id
            )
        );

        if (!$user_email) {
            return $form; // Return form unmodified if no email found
        }

        // Get the latest entry ID for this email and Form ID: 22
        $entry_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT e.id FROM {$wpdb->prefix}gf_entry e
                 JOIN {$wpdb->prefix}gf_entry_meta em ON e.id = em.entry_id
                 WHERE em.meta_value = %s AND e.form_id = 22
                 ORDER BY e.date_created DESC LIMIT 1",
                $user_email
            )
        );

        if (!$entry_id) {
            return $form; // Return form unmodified if no entry found
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
            return $form; // Return form unmodified if no meta found
        }

        // Map meta values to form fields
        $meta_map = wp_list_pluck($entry_meta, 'meta_value', 'meta_key');

        foreach ($form['fields'] as &$field) {
            // Handle normal fields
            if (isset($meta_map[$field->id])) {
                $field->defaultValue = $meta_map[$field->id];
            }

            // Handle complex/nested fields (e.g., Address fields with .1, .2, etc.)
            if ($field->type === 'address' && !empty($field->inputs)) {
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

    // Save Gravity Form submission data to existing entry meta
    add_action('gform_after_submission_22', 'save_gform_22_fields', 10, 2);

    function save_gform_22_fields($entry, $form) {
        global $wpdb;

        // Get person_id (user ID) from URL parameter
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
                'created_by' => $person_id,
                'ip' => $_SERVER['REMOTE_ADDR'], // Update IP to reflect submission source
                'source_url' => esc_url_raw($_SERVER['HTTP_REFERER']), // Update Embed URL
                'user_agent' => esc_html($_SERVER['HTTP_USER_AGENT']) // Update User Agent
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
                            'form_id' => $form['id'],
                            'entry_id' => $entry_id,
                            'meta_key' => $field->id,
                            'meta_value' => $field_value
                        ]
                    );
                }
            }
        }
    }
}


// Hook to ensure WordPress is fully initialized
add_action('init', 'gform_id_37_meta_handler_init');

function gform_id_37_meta_handler_init() {
    global $wpdb;

    // Pre-populate Gravity Form fields for Form ID: 37
    if (is_user_logged_in()) {
        add_filter('gform_pre_render_37', 'pre_populate_gform_37_fields');
    }

    function pre_populate_gform_37_fields($form) {
        global $wpdb;

        // Get person_id (user ID) from URL parameter
        $person_id = isset($_GET['person_id']) ? intval($_GET['person_id']) : 0;
        if (!$person_id) {
            return $form; // Return form unmodified if no person_id
        }

        // Fetch the user's email based on person_id (user ID)
        $user_email = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT user_email FROM {$wpdb->prefix}users WHERE ID = %d",
                $person_id
            )
        );

        if (!$user_email) {
            return $form; // Return form unmodified if no email found
        }

        // Get the latest entry ID for this email and Form ID: 37
        $entry_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT e.id FROM {$wpdb->prefix}gf_entry e
                 JOIN {$wpdb->prefix}gf_entry_meta em ON e.id = em.entry_id
                 WHERE em.meta_value = %s AND e.form_id = 37
                 ORDER BY e.date_created DESC LIMIT 1",
                $user_email
            )
        );

        if (!$entry_id) {
            return $form; // Return form unmodified if no entry found
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
            return $form; // Return form unmodified if no meta found
        }

        // Map meta values to form fields
        $meta_map = wp_list_pluck($entry_meta, 'meta_value', 'meta_key');

        foreach ($form['fields'] as &$field) {
            // Handle normal fields
            if (isset($meta_map[$field->id])) {
                $field->defaultValue = $meta_map[$field->id];
            }

            // Handle complex/nested fields (e.g., Address fields with .1, .2, etc.)
            if ($field->type === 'address' && !empty($field->inputs)) {
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

    // Save Gravity Form submission data to existing entry meta
    add_action('gform_after_submission_37', 'save_gform_37_fields', 10, 2);

    function save_gform_37_fields($entry, $form) {
        global $wpdb;

        // Get person_id (user ID) from URL parameter
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
                'created_by' => $person_id,
                'ip' => $_SERVER['REMOTE_ADDR'], // Update IP to reflect submission source
                'source_url' => esc_url_raw($_SERVER['HTTP_REFERER']), // Update Embed URL
                'user_agent' => esc_html($_SERVER['HTTP_USER_AGENT']) // Update User Agent
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
                            'form_id' => $form['id'],
                            'entry_id' => $entry_id,
                            'meta_key' => $field->id,
                            'meta_value' => $field_value
                        ]
                    );
                }
            }
        }
    }
}