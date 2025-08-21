<?php
if (!defined('ABSPATH')) {
    exit;
}

// Get club details by ID
function get_club_details($club_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'clubs';
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE club_id = %d", $club_id));
}

// Get club roles by ID
function get_club_roles($club_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'club_roles';
    return $wpdb->get_col($wpdb->prepare("SELECT role_name FROM $table_name WHERE club_id = %d", $club_id));
}

// Get EFT details by club ID
function get_eft_details($club_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'eft_details';
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE club_id = %d", $club_id));
}

// Get payment gateway type by club ID
function get_payment_gateway($club_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'payment_gateways';
    return $wpdb->get_var($wpdb->prepare("SELECT gateway_type FROM $table_name WHERE club_id = %d", $club_id));
}
