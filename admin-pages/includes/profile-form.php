<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

add_action('init', 'populate_and_save_gform7');

function populate_and_save_gform7() {
    // Add prepopulation logic for Gravity Form ID 7
    if (is_user_logged_in()) {
        add_filter('gform_pre_render_7', 'pre_populate_gform7_fields');
        add_filter('gform_pre_validation_7', 'pre_populate_gform7_fields');
        add_filter('gform_admin_pre_render_7', 'pre_populate_gform7_fields');
    }

    function pre_populate_gform7_fields($form) {
        $current_user_id = get_current_user_id();

        // Retrieve entries created by the current user for Form ID 7
        $search_criteria = [
            'field_filters' => [
                ['key' => 'created_by', 'operator' => 'is', 'value' => $current_user_id],
            ]
        ];
        $entries = GFAPI::get_entries(7, $search_criteria);

        // If no entries exist, fallback to user meta
        if (empty($entries)) {
            error_log("No entries found for user $current_user_id in Form ID 7.");
            return $form;
        }

        $entry = $entries[0]; // Use the most recent entry
        error_log("Entry Found: " . print_r($entry, true)); // Debug the fetched entry

        foreach ($form['fields'] as &$field) {
            // Prepopulate single fields
            if (isset($entry[$field->id])) {
                $field->defaultValue = $entry[$field->id];
                error_log("Field ID: {$field->id}, Default Value Set: {$field->defaultValue}");
            }

            // Prepopulate multi-input fields
            if (!empty($field->inputs)) {
                foreach ($field->inputs as &$input) {
                    $input_id = (string) $input['id'];
                    if (isset($entry[$input_id])) {
                        $input['defaultValue'] = $entry[$input_id];
                        error_log("Multi-input Field ID: $input_id, Default Value Set: {$input['defaultValue']}");
                    }
                }
            }
        }

        return $form;
    }

    // Save Gravity Form submission data to user meta
    add_action('gform_after_submission_7', 'save_gform7_submission', 10, 2);

    function save_gform7_submission($entry, $form) {
        $current_user_id = get_current_user_id();

        $meta_keys = [
            2  => 'first_name',
            3  => 'last_name',
            7  => 'user_email',
            8  => 'mobile_number',
            9  => 'id_number',
            72 => 'msa_license_number',
            '10.1' => 'street_address',
            '10.2' => 'address_line_2',
            '10.3' => 'city',
            '10.4' => 'state',
            '10.5' => 'zip_code',
            '10.6' => 'country',
            '76.1' => 'postal_street_address',
            '76.2' => 'postal_address_line_2',
            '76.3' => 'postal_city',
            '76.4' => 'postal_province',
            '76.5' => 'postal_zip_code',
            '76.6' => 'postal_country',
        ];

        foreach ($meta_keys as $field_id => $meta_key) {
            $value = rgar($entry, $field_id);
            if (!empty($value)) {
                update_user_meta($current_user_id, $meta_key, sanitize_text_field($value));
                error_log("Saved Field ID: $field_id to Meta Key: $meta_key with Value: $value");
            }
        }
    }
}
