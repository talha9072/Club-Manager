<?php
if (!defined('ABSPATH')) {
    exit;
}


// Check if club exists by name and URL
function club_exists($club_name, $club_url) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'clubs';

    $club = $wpdb->get_row($wpdb->prepare(
        "SELECT club_id FROM $table_name WHERE club_name = %s AND club_url = %s",
        $club_name, $club_url
    ));

    return $club ? $club->club_id : false;
}

// Save or update club details
function save_or_update_club($club_id, $club_name, $club_url, $club_logo) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'clubs';

    // Check if a club with the same name and URL already exists
    $existing_club_id = club_exists($club_name, $club_url);

    if ($club_id || $existing_club_id) {
        // Update the existing club if an ID is provided or if a duplicate exists
        $wpdb->update(
            $table_name,
            array(
                'club_name' => $club_name,
                'club_url' => $club_url,
                'club_logo' => $club_logo // Store as a URL (string)
            ),
            array('club_id' => $club_id ?: $existing_club_id),
            array('%s', '%s', '%s'), // Changed to %s for string
            array('%d')
        );
        return $club_id ?: $existing_club_id;
    } else {
        // Insert new club if it doesn't already exist
        $wpdb->insert(
            $table_name,
            array(
                'club_name' => $club_name,
                'club_url' => $club_url,
                'club_logo' => $club_logo // Store as a URL (string)
            ),
            array('%s', '%s', '%s') // Changed to %s for string
        );
        return $wpdb->insert_id;
    }
}

// Save or update EFT details
function save_or_update_eft_details($club_id, $account_name, $account_number, $bank_name, $branch_code) {
    global $wpdb;
    $wpdb->replace(
        $wpdb->prefix . 'eft_details',
        array(
            'club_id' => $club_id,
            'account_name' => $account_name,
            'account_number' => $account_number,
            'bank_name' => $bank_name,
            'branch_code' => $branch_code
        ),
        array('%d', '%s', '%s', '%s', '%s')
    );
}

// Save or update club roles
function save_or_update_roles($club_id, $club_roles) {
    global $wpdb;
    $wpdb->delete($wpdb->prefix . 'club_roles', array('club_id' => $club_id), array('%d'));

    foreach ($club_roles as $role) {
        if (!empty($role)) { // Ensure only non-empty roles are saved
            $wpdb->insert(
                $wpdb->prefix . 'club_roles',
                array(
                    'club_id' => $club_id,
                    'role_name' => $role
                ),
                array('%d', '%s')
            );
        }
    }
}

