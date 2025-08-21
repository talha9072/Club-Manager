<?php
if (!function_exists('get_logged_in_user_club')) {
    function get_logged_in_user_club() {
        if (!is_user_logged_in()) {
            return null; // Return null if the user is not logged in
        }

        $current_user = wp_get_current_user();
        $user_email = $current_user->user_email;

        // Check if the user has the 'administrator' role
        if (in_array('administrator', $current_user->roles)) {
            return null; // If the user is an admin, return null
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'club_members';

        // Query the database for the user's club details
        $club_details = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT club_id, club_name FROM $table_name WHERE user_email = %s LIMIT 1",
                $user_email
            ), 
            ARRAY_A
        );

        return $club_details ? $club_details : null;
    }
}



