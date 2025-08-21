<?php
function fetch_and_add_yoco_links() {
    global $wpdb;

    // Retrieve Yoco links
    $yoco_links = $wpdb->get_results("SELECT club_id, yoco_link FROM wp_payment_gateways WHERE gateway_type = 'yoco'");

    if (!empty($yoco_links)) {
        $yoco_accounts_data = [];

        foreach ($yoco_links as $link) {
            $club_id = $link->club_id;
            $club_name_result = $wpdb->get_row($wpdb->prepare("SELECT club_name FROM wp_clubs WHERE club_id = %d", $club_id));

            if ($club_name_result) {
                $club_name = $club_name_result->club_name;

                // Prepare the entry for Yoco accounts data
                $yoco_accounts_data[] = [
                    'account_name' => $link->yoco_link,
                    'account_number' => $club_name // Using club name here
                ];
            }
        }

        // Delete existing data to ensure only the latest entries are saved
        delete_option('woocommerce_yoco_accounts');

        // Save the new Yoco accounts data
        update_option('woocommerce_yoco_accounts', $yoco_accounts_data);
    }
}

// Execute the function to update Yoco links
fetch_and_add_yoco_links();
