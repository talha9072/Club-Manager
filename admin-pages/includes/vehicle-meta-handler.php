<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Hook to ensure WordPress is fully initialized
add_action('init', 'vehicle_meta_handler_init');

function vehicle_meta_handler_init() {
    // Pre-populate Gravity Form fields for bikes (Form ID: 14)
    if (is_user_logged_in()) {
        add_filter('gform_pre_render_14', 'pre_pop_membership_bike');
    }

    function pre_pop_membership_bike($form) {
        $current_user = get_current_user_id();

        // Manually populate bike data for up to 5 bikes
        $bike_1_make = get_user_meta($current_user, "user_bike_1_make", true);
        $bike_1_model = get_user_meta($current_user, "user_bike_1_model", true);
        $bike_1_year = get_user_meta($current_user, "user_bike_1_year", true);
        $bike_1_registration = get_user_meta($current_user, "user_bike_1_registration_number", true);

        $bike_2_make = get_user_meta($current_user, "user_bike_2_make", true);
        $bike_2_model = get_user_meta($current_user, "user_bike_2_model", true);
        $bike_2_year = get_user_meta($current_user, "user_bike_2_year", true);
        $bike_2_registration = get_user_meta($current_user, "user_bike_2_registration_number", true);

        $bike_3_make = get_user_meta($current_user, "user_bike_3_make", true);
        $bike_3_model = get_user_meta($current_user, "user_bike_3_model", true);
        $bike_3_year = get_user_meta($current_user, "user_bike_3_year", true);
        $bike_3_registration = get_user_meta($current_user, "user_bike_3_registration_number", true);

        $bike_4_make = get_user_meta($current_user, "user_bike_4_make", true);
        $bike_4_model = get_user_meta($current_user, "user_bike_4_model", true);
        $bike_4_year = get_user_meta($current_user, "user_bike_4_year", true);
        $bike_4_registration = get_user_meta($current_user, "user_bike_4_registration_number", true);

        $bike_5_make = get_user_meta($current_user, "user_bike_5_make", true);
        $bike_5_model = get_user_meta($current_user, "user_bike_5_model", true);
        $bike_5_year = get_user_meta($current_user, "user_bike_5_year", true);
        $bike_5_registration = get_user_meta($current_user, "user_bike_5_registration_number", true);

        // Map Gravity Form fields to bike meta data
        foreach ($form['fields'] as &$field) {
            if ($field->id == 87) $field->defaultValue = $bike_1_make;
            if ($field->id == 88) $field->defaultValue = $bike_1_model;
            if ($field->id == 89) $field->defaultValue = $bike_1_year;
            if ($field->id == 90) $field->defaultValue = $bike_1_registration;

            if ($field->id == 100) $field->defaultValue = $bike_2_make;
            if ($field->id == 101) $field->defaultValue = $bike_2_model;
            if ($field->id == 102) $field->defaultValue = $bike_2_year;
            if ($field->id == 103) $field->defaultValue = $bike_2_registration;

            if ($field->id == 108) $field->defaultValue = $bike_3_make;
            if ($field->id == 109) $field->defaultValue = $bike_3_model;
            if ($field->id == 110) $field->defaultValue = $bike_3_year;
            if ($field->id == 111) $field->defaultValue = $bike_3_registration;

            if ($field->id == 113) $field->defaultValue = $bike_4_make;
            if ($field->id == 114) $field->defaultValue = $bike_4_model;
            if ($field->id == 115) $field->defaultValue = $bike_4_year;
            if ($field->id == 116) $field->defaultValue = $bike_4_registration;

            if ($field->id == 118) $field->defaultValue = $bike_5_make;
            if ($field->id == 119) $field->defaultValue = $bike_5_model;
            if ($field->id == 120) $field->defaultValue = $bike_5_year;
            if ($field->id == 121) $field->defaultValue = $bike_5_registration;
        }

        return $form;
    }

    // Save bike data from Gravity Form submission (Form ID: 14)
    add_action('gform_after_submission_14', 'bmw_save_bike_input_fields', 10, 2);

    function bmw_save_bike_input_fields($entry, $form) {
        $current_user = get_current_user_id();

        // Save bike data for up to 5 bikes
        update_user_meta($current_user, "user_bike_1_make", rgar($entry, 87));
        update_user_meta($current_user, "user_bike_1_model", rgar($entry, 88));
        update_user_meta($current_user, "user_bike_1_year", rgar($entry, 89));
        update_user_meta($current_user, "user_bike_1_registration_number", rgar($entry, 90));

        update_user_meta($current_user, "user_bike_2_make", rgar($entry, 100));
        update_user_meta($current_user, "user_bike_2_model", rgar($entry, 101));
        update_user_meta($current_user, "user_bike_2_year", rgar($entry, 102));
        update_user_meta($current_user, "user_bike_2_registration_number", rgar($entry, 103));

        update_user_meta($current_user, "user_bike_3_make", rgar($entry, 108));
        update_user_meta($current_user, "user_bike_3_model", rgar($entry, 109));
        update_user_meta($current_user, "user_bike_3_year", rgar($entry, 110));
        update_user_meta($current_user, "user_bike_3_registration_number", rgar($entry, 111));

        update_user_meta($current_user, "user_bike_4_make", rgar($entry, 113));
        update_user_meta($current_user, "user_bike_4_model", rgar($entry, 114));
        update_user_meta($current_user, "user_bike_4_year", rgar($entry, 115));
        update_user_meta($current_user, "user_bike_4_registration_number", rgar($entry, 116));

        update_user_meta($current_user, "user_bike_5_make", rgar($entry, 118));
        update_user_meta($current_user, "user_bike_5_model", rgar($entry, 119));
        update_user_meta($current_user, "user_bike_5_year", rgar($entry, 120));
        update_user_meta($current_user, "user_bike_5_registration_number", rgar($entry, 121));
    }

    // Pre-populate Gravity Form fields for cars (Form ID: 8)
    if (is_user_logged_in()) {
        add_filter('gform_pre_render_8', 'pre_pop_membership_car');
    }

    function pre_pop_membership_car($form) {
        $current_user = get_current_user_id();

        // Manually populate car data for up to 5 cars
        $car_1_make = get_user_meta($current_user, "user_vehicle_1", true);
        $car_1_model = get_user_meta($current_user, "user_vehicle_1_year", true);
        $car_1_year = get_user_meta($current_user, "user_vehicle_1_capa", true);
        $car_1_registration = get_user_meta($current_user, "user_vehicle_1_num_cyl", true);

        $car_2_make = get_user_meta($current_user, "user_vehicle_2", true);
        $car_2_model = get_user_meta($current_user, "user_vehicle_2_year", true);
        $car_2_year = get_user_meta($current_user, "user_vehicle_2_capa", true);
        $car_2_registration = get_user_meta($current_user, "user_vehicle_2_num_cyl", true);

        $car_3_make = get_user_meta($current_user, "user_vehicle_3", true);
        $car_3_model = get_user_meta($current_user, "user_vehicle_3_year", true);
        $car_3_year = get_user_meta($current_user, "user_vehicle_3_capa", true);
        $car_3_registration = get_user_meta($current_user, "user_vehicle_3_num_cyl", true);

        // Map Gravity Form fields to car meta data
        foreach ($form['fields'] as &$field) {
            if ($field->id == 11) $field->defaultValue = $car_1_make;
            if ($field->id == 24) $field->defaultValue = $car_1_model;
            if ($field->id == 25) $field->defaultValue = $car_1_year;
            if ($field->id == 31) $field->defaultValue = $car_1_registration;

            if ($field->id == 34) $field->defaultValue = $car_2_make;
            if ($field->id == 36) $field->defaultValue = $car_2_model;
            if ($field->id == 37) $field->defaultValue = $car_2_year;
            if ($field->id == 39) $field->defaultValue = $car_2_registration;
        }

        return $form;
    }

    // Save car data from Gravity Form submission (Form ID: 8)
    add_action('gform_after_submission_8', 'bmw_save_car_input_fields', 10, 2);

    function bmw_save_car_input_fields($entry, $form) {
        $current_user = get_current_user_id();

        // Save car data for up to 5 cars
        update_user_meta($current_user, "user_vehicle_1", rgar($entry, 11));
        update_user_meta($current_user, "user_vehicle_1_year", rgar($entry, 24));
        update_user_meta($current_user, "user_vehicle_1_capa", rgar($entry, 25));
        update_user_meta($current_user, "user_vehicle_1_num_cyl", rgar($entry, 31));

        update_user_meta($current_user, "user_vehicle_2", rgar($entry, 34));
        update_user_meta($current_user, "user_vehicle_2_year", rgar($entry, 36));
        update_user_meta($current_user, "user_vehicle_2_capa", rgar($entry, 37));
        update_user_meta($current_user, "user_vehicle_2_num_cyl", rgar($entry, 39));
    }
}
