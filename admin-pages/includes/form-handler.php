<?php
if (!defined('ABSPATH')) {
    exit;
}

function handle_club_form_submission() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        global $wpdb;

        $club_id = isset($_GET['club_id']) ? intval($_GET['club_id']) : 0;
        $club_name = sanitize_text_field($_POST['club_name']);
        $club_url = sanitize_text_field($_POST['club_url']);
        $club_logo = isset($_POST['club_logo']) ? esc_url_raw($_POST['club_logo']) : ''; // Save as a URL
        $club_roles = isset($_POST['club_roles']) ? array_map('sanitize_text_field', $_POST['club_roles']) : [];
        $eft_account_name = sanitize_text_field($_POST['eft_account_name']);
        $eft_account_number = sanitize_text_field($_POST['eft_account_number']);
        $eft_bank_name = sanitize_text_field($_POST['eft_bank_name']);
        $eft_branch_code = sanitize_text_field($_POST['eft_branch_code']);
        
        // Payment gateway fields
        $payment_gateway = sanitize_text_field($_POST['payment_gateway']);
        $payfast_merchant_id = isset($_POST['payfast_merchant_id']) ? sanitize_text_field($_POST['payfast_merchant_id']) : '';
        $payfast_merchant_key = isset($_POST['payfast_merchant_key']) ? sanitize_text_field($_POST['payfast_merchant_key']) : '';
        $stripe_api_key = isset($_POST['stripe_api_key']) ? sanitize_text_field($_POST['stripe_api_key']) : '';
        $stripe_secret_key = isset($_POST['stripe_secret_key']) ? sanitize_text_field($_POST['stripe_secret_key']) : '';
        $yoco_link = isset($_POST['yoco_link']) ? sanitize_text_field($_POST['yoco_link']) : '';

        // Save or update club details
        $new_club_id = $club_id ? $club_id : save_or_update_club(0, $club_name, $club_url, $club_logo);
        $club_id = $club_id ?: $new_club_id;

        save_or_update_club($club_id, $club_name, $club_url, $club_logo);
        save_or_update_roles($club_id, $club_roles);
        save_or_update_eft_details($club_id, $eft_account_name, $eft_account_number, $eft_bank_name, $eft_branch_code);
        
        // Save or update payment gateway details based on the selected gateway
        save_or_update_payment_gateway($club_id, $payment_gateway, $payfast_merchant_id, $payfast_merchant_key, $stripe_api_key, $stripe_secret_key, $yoco_link);

        wp_redirect(admin_url('admin.php?page=club-manager-clubs'));
        exit;
    }
}


// Function to save or update payment gateway details
function save_or_update_payment_gateway($club_id, $gateway_type, $payfast_merchant_id, $payfast_merchant_key, $stripe_api_key, $stripe_secret_key, $yoco_link) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'payment_gateways';

    // Prepare data based on gateway type
    $data = array(
        'club_id' => $club_id,
        'gateway_type' => $gateway_type,
        'merchant_id' => '',
        'merchant_key' => '',
        'api_key' => '',
        'secret_key' => '',
        'yoco_link' => ''
    );

    switch ($gateway_type) {
        case 'payfast':
            $data['merchant_id'] = $payfast_merchant_id;
            $data['merchant_key'] = $payfast_merchant_key;
            break;
        case 'stripe':
            $data['api_key'] = $stripe_api_key;
            $data['secret_key'] = $stripe_secret_key;
            break;
        case 'yoco':
            $data['yoco_link'] = $yoco_link;
            break;
    }

    // Replace into payment_gateways table to save or update the entry
    $wpdb->replace(
        $table_name,
        $data,
        array('%d', '%s', '%s', '%s', '%s', '%s', '%s')
    );
}
