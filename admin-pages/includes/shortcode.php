<?php
/**
 * Fetches subscription details for a given user ID.
 *
 * @param int $user_id The ID of the user whose subscription details are needed.
 * @return array|false Subscription details array or false if not found.
 */
// Function to get user subscription details based on URL parameter or default to first active subscription
function get_user_subscription_details($user_id) {
    global $wpdb;

    // Validate the user ID
    if (!$user_id || !is_numeric($user_id)) {
        error_log('get_user_subscription_details: Invalid user ID.');
        return false;
    }

    // Check for subscriptionID in URL
    $subscription_id = isset($_GET['subscriptionID']) ? intval($_GET['subscriptionID']) : null;

    // Logging for debugging
    error_log("Fetching subscription details for user ID: $user_id" . ($subscription_id ? " with subscription ID: $subscription_id" : ""));

    // SQL query with conditional logic based on subscriptionID presence
    if ($subscription_id) {
        // Fetch subscription using the provided subscriptionID from URL
        $subscription = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT DISTINCT 
                    sub.ID AS subscription_id,
                    sub.post_status AS subscription_status,
                    sub.post_date,  
                    pm_start.meta_value AS schedule_start,
                    DATE_FORMAT(pm_end.meta_value, '%%d/%%m/%%Y') AS expiry_date,
                    prod.ID AS product_id,
                    prod.post_title AS subscription_plan,
                    um_first.meta_value AS first_name,
                    um_last.meta_value AS last_name
                FROM {$wpdb->prefix}users u
                INNER JOIN {$wpdb->prefix}postmeta pm_customer 
                    ON u.ID = pm_customer.meta_value 
                    AND pm_customer.meta_key = '_customer_user'
                INNER JOIN {$wpdb->prefix}posts sub 
                    ON pm_customer.post_id = sub.ID 
                    AND sub.post_type = 'shop_subscription'
                INNER JOIN {$wpdb->prefix}woocommerce_order_items oi 
                    ON sub.ID = oi.order_id
                INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta pm_product 
                    ON oi.order_item_id = pm_product.order_item_id 
                    AND pm_product.meta_key = '_product_id'
                INNER JOIN {$wpdb->prefix}posts prod 
                    ON pm_product.meta_value = prod.ID
                LEFT JOIN {$wpdb->prefix}postmeta pm_start 
                    ON sub.ID = pm_start.post_id 
                    AND pm_start.meta_key = '_schedule_start'
                LEFT JOIN {$wpdb->prefix}postmeta pm_end 
                    ON sub.ID = pm_end.post_id 
                    AND pm_end.meta_key = '_schedule_end'
                LEFT JOIN {$wpdb->prefix}usermeta um_first 
                    ON um_first.user_id = u.ID 
                    AND um_first.meta_key = 'first_name'
                LEFT JOIN {$wpdb->prefix}usermeta um_last 
                    ON um_last.user_id = u.ID 
                    AND um_last.meta_key = 'last_name'
                WHERE sub.ID = %d
                AND sub.post_status = 'wc-active'
                LIMIT 1",
                $subscription_id
            ),
            ARRAY_A
        );
    } else {
        // Fallback: fetch the first active subscription for the given user ID
        $subscription = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT DISTINCT 
                    sub.ID AS subscription_id,
                    sub.post_status AS subscription_status,
                    sub.post_date,  
                    pm_start.meta_value AS schedule_start,
                    DATE_FORMAT(pm_end.meta_value, '%%d/%%m/%%Y') AS expiry_date,
                    prod.ID AS product_id,
                    prod.post_title AS subscription_plan,
                    um_first.meta_value AS first_name,
                    um_last.meta_value AS last_name
                FROM {$wpdb->prefix}users u
                INNER JOIN {$wpdb->prefix}postmeta pm_customer 
                    ON u.ID = pm_customer.meta_value 
                    AND pm_customer.meta_key = '_customer_user'
                INNER JOIN {$wpdb->prefix}posts sub 
                    ON pm_customer.post_id = sub.ID 
                    AND sub.post_type = 'shop_subscription'
                INNER JOIN {$wpdb->prefix}woocommerce_order_items oi 
                    ON sub.ID = oi.order_id
                INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta pm_product 
                    ON oi.order_item_id = pm_product.order_item_id 
                    AND pm_product.meta_key = '_product_id'
                INNER JOIN {$wpdb->prefix}posts prod 
                    ON pm_product.meta_value = prod.ID
                LEFT JOIN {$wpdb->prefix}postmeta pm_start 
                    ON sub.ID = pm_start.post_id 
                    AND pm_start.meta_key = '_schedule_start'
                LEFT JOIN {$wpdb->prefix}postmeta pm_end 
                    ON sub.ID = pm_end.post_id 
                    AND pm_end.meta_key = '_schedule_end'
                LEFT JOIN {$wpdb->prefix}usermeta um_first 
                    ON um_first.user_id = u.ID 
                    AND um_first.meta_key = 'first_name'
                LEFT JOIN {$wpdb->prefix}usermeta um_last 
                    ON um_last.user_id = u.ID 
                    AND um_last.meta_key = 'last_name'
                WHERE u.ID = %d
                AND sub.post_status = 'wc-active'
                LIMIT 1",
                $user_id
            ),
            ARRAY_A
        );
    }

    // Handle empty subscription result
    if (!$subscription) {
        error_log("DEBUG: No subscription found for user ID: $user_id");
        return false;
    }

    $partner_name = 'N/A';

// Step 1: Get product ID and user email
$product_id = intval($subscription['product_id'] ?? 0);
$user_email = $wpdb->get_var(
    $wpdb->prepare(
        "SELECT user_email FROM {$wpdb->prefix}users WHERE ID = %d LIMIT 1",
        $user_id
    )
);

// Step 2: Get club_id from product postmeta
if ($product_id && $user_email) {
    $club_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT meta_value FROM {$wpdb->prefix}postmeta
             WHERE post_id = %d AND meta_key = '_select_club_id' LIMIT 1",
            $product_id
        )
    );

    // Step 3: Get registration_form from wp_clubs
    if ($club_id) {
        $form_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT registration_form FROM {$wpdb->prefix}clubs
                 WHERE club_id = %d LIMIT 1",
                $club_id
            )
        );

        // Step 4: Fetch latest entry by user email (field ID 7)
        if ($form_id) {
            $entry_id = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT e.id
                     FROM {$wpdb->prefix}gf_entry e
                     INNER JOIN {$wpdb->prefix}gf_entry_meta em ON e.id = em.entry_id
                     WHERE e.form_id = %d
                       AND em.meta_key = 7
                       AND em.meta_value = %s
                     ORDER BY e.date_created DESC
                     LIMIT 1",
                    $form_id,
                    $user_email
                )
            );

            if ($entry_id) {
                // Step 5: Get all entry meta for that form entry
                $entry_meta = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT meta_key, meta_value
                         FROM {$wpdb->prefix}gf_entry_meta
                         WHERE entry_id = %d",
                        $entry_id
                    ),
                    ARRAY_A
                );

                $meta_data = [];
                foreach ($entry_meta as $meta) {
                    $meta_data[$meta['meta_key']] = $meta['meta_value'];
                }

                $partner_first = $meta_data[4] ?? '';
                $partner_last  = $meta_data[5] ?? '';
                $partner_name  = trim($partner_first . ' ' . $partner_last);
                if ($partner_name === '') {
                    $partner_name = 'N/A';
                }
            }
        }
    }
}


    // Return subscription details
    return [
        'full_name'         => trim(($subscription['first_name'] ?? 'N/A') . ' ' . ($subscription['last_name'] ?? '')),
        'subscription_id'   => $subscription['subscription_id'] ?? 'N/A',
        'subscription_plan' => $subscription['subscription_plan'] ?? 'N/A',
        'expiry_date'       => $subscription['expiry_date'] ?? 'N/A',
        'partner_name'      => $partner_name,
    ];
}


/**
 * Shortcode to display subscription details, automatically fetching userID from URL.
 * Usage: [bmw_check_membership]
 */
function bmw_check_membership_shortcode($atts) {
    // Try to fetch userID from URL (?userID=2301), otherwise use shortcode attribute
    $user_id = isset($_GET['userID']) ? intval($_GET['userID']) : 0;

    if (!$user_id) {
        return '<p style="color: red; text-align: center;">Missing or invalid user ID in URL.</p>';
    }

    // Fetch subscription details
    $subscription_data = get_user_subscription_details($user_id);

    if (!$subscription_data) {
        return '<p style="color: red; text-align: center;">Membership details not found.</p>';
    }

    // Generate output HTML
    ob_start();
    ?>
    <h2 style="display: block; font-size: 24px; font-weight: bold;">Check Membership</h2>
    <div style="max-width: 500px; margin: 0 auto; padding: 20px; border: 2px dashed #000; font-family: Arial, sans-serif;">
        <h2 style="display: block; margin: 0 0 15px; font-size: 22px; font-weight: bold;">Membership Details</h2>
        <p style="margin: 5px 0;"><strong>Name:</strong> <?php echo esc_html($subscription_data['full_name']); ?></p>
        <p style="margin: 5px 0;"><strong>Partner Name:</strong> <?php echo esc_html($subscription_data['partner_name']); ?></p>
        <p style="margin: 5px 0;"><strong>Membership:</strong> <?php echo esc_html($subscription_data['subscription_plan']); ?></p>
        <p style="margin: 5px 0;"><strong>Membership Number: </strong> <?php echo esc_html($subscription_data['subscription_id']); ?></p>
        <p style="margin: 5px 0;"><strong>Expires:</strong> <?php echo esc_html($subscription_data['expiry_date']); ?></p>
        
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('bmw_check_membership', 'bmw_check_membership_shortcode');
