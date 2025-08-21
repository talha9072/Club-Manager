<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Sync BACS bank details from `wp_eft_details` to `woocommerce_bacs_accounts` without overwriting manually added accounts.
 */
function sync_bacs_bank_details_once() {
    global $wpdb;

    // Fetch all existing WooCommerce BACS accounts
    $existing_accounts = get_option('woocommerce_bacs_accounts', []);

    // Fetch all bank details from `wp_eft_details`
    $results = $wpdb->get_results(
        "SELECT account_name, account_number, bank_name, branch_code FROM wp_eft_details",
        ARRAY_A
    );

    if (empty($results)) {
        error_log("🚨 No bank details found in `wp_eft_details`.");
        return; // Exit if no bank details are found
    }

    $new_accounts = [];

    foreach ($results as $row) {
        // Check if this bank account already exists
        $exists = false;
        foreach ($existing_accounts as $existing) {
            if (
                $existing['account_name'] === $row['account_name'] &&
                $existing['account_number'] === $row['account_number']
            ) {
                $exists = true;
                break;
            }
        }

        // If it does not exist, add it to the new accounts array
        if (! $exists) {
            $new_accounts[] = [
                'account_name'   => sanitize_text_field($row['account_name']),
                'account_number' => sanitize_text_field($row['account_number']),
                'bank_name'      => sanitize_text_field($row['bank_name']),
                'sort_code'      => sanitize_text_field($row['branch_code']),
                'iban'           => '',
                'bic'            => '',
            ];
        }
    }

    // If there are new accounts, merge them with existing accounts
    if (! empty($new_accounts)) {
        $updated_accounts = array_merge($existing_accounts, $new_accounts);
        update_option('woocommerce_bacs_accounts', $updated_accounts);
        error_log("✅ Successfully synced new BACS accounts: " . print_r($new_accounts, true));
    } else {
        error_log("ℹ️ No new BACS accounts to add.");
    }
}

/**
 * Run sync only once after plugin activation
 */
function run_sync_bacs_bank_details_once() {
    if (! get_option('bacs_sync_completed', false)) {
        sync_bacs_bank_details_once();
        update_option('bacs_sync_completed', true);
    }
}
register_activation_hook(__FILE__, 'run_sync_bacs_bank_details_once');
