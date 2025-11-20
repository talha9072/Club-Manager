<?php
// Prevent direct access to the file
defined('ABSPATH') || exit;

// Add AJAX action to handle user search
add_action('wp_ajax_search_users', 'club_manager_search_users');

function club_manager_search_users() {
    if (!isset($_GET['search'])) {
        wp_send_json([]);
        return;
    }

    $search_term = sanitize_text_field($_GET['search']);
    $users = get_users([
        'search'         => '*' . esc_attr($search_term) . '*',
        'search_columns' => ['user_login', 'user_nicename', 'display_name'],
        'number'         => 10
    ]);

    $results = [];
    foreach ($users as $user) {
        $results[] = [
            'id'           => $user->ID,
            'display_name' => $user->display_name,
            'user_login'   => $user->user_login,
            'user_email'   => $user->user_email
        ];
    }
    wp_send_json($results);
}

// Add AJAX action to handle adding club members
add_action('wp_ajax_add_club_member', 'club_manager_add_club_member');

function club_manager_add_club_member() {
    global $wpdb;

    $club_id = intval($_POST['club_id']);
    $club_name = sanitize_text_field($_POST['club_name']);
    $member_name = sanitize_text_field($_POST['member_name']);
    $member_email = sanitize_email($_POST['member_email']);
    $role = sanitize_text_field($_POST['role']);

    if ($club_id && $club_name && $member_name && $member_email && $role) {
        $wpdb->insert(
            "{$wpdb->prefix}club_members",
            [
                'club_id'    => $club_id,
                'club_name'  => $club_name,
                'user_name'  => $member_name,
                'user_email' => $member_email,
                'role'       => $role,
                'created_at' => current_time('mysql')
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s']
        );

        if ($wpdb->insert_id) {
            wp_send_json_success();
        } else {
            wp_send_json_error();
        }
    } else {
        wp_send_json_error();
    }
    wp_die();
}

// Handle AJAX request to fetch members based on selected club
add_action('wp_ajax_fetch_club_members', 'fetch_club_members');

function fetch_club_members() {
    global $wpdb;

    $club_id = isset($_POST['club_id']) ? intval($_POST['club_id']) : 0;
    if (!$club_id) {
        echo '<tr><td colspan="6">' . __('Invalid club ID.', 'club-manager') . '</td></tr>';
        wp_die();
    }

    $members = $wpdb->get_results(
        $wpdb->prepare("SELECT id, club_name, user_name, user_email, role FROM {$wpdb->prefix}club_members WHERE club_id = %d", $club_id)
    );

    if (!empty($members)) {
        foreach ($members as $member) {
            echo '<tr data-member-id="' . esc_attr($member->id) . '">';
            echo '<td style="border: 1px solid #ddd; padding: 8px;">' . esc_html($member->club_name) . '</td>';
            echo '<td style="border: 1px solid #ddd; padding: 8px;">' . esc_html($member->user_name) . '</td>';
            echo '<td style="border: 1px solid #ddd; padding: 8px;">' . esc_html($member->user_email) . '</td>';
            echo '<td style="border: 1px solid #ddd; padding: 8px;">' . esc_html($member->role) . '</td>';
            echo '<td style="border: 1px solid #ddd; padding: 8px;">' . (esc_html($member->role) === 'Club Manager' ? 'Full Access' : 'Limited Access') . '</td>';
            echo '<td style="border: 1px solid #ddd; padding: 8px; text-align: center;"><a href="#" class="delete-member" data-member-id="' . esc_attr($member->id) . '" style="display: inline-block; width: 100%; color: red;">❌</a></td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="6" style="border: 1px solid #ddd; padding: 8px;">' . __('No members found for this club.', 'club-manager') . '</td></tr>';
    }
    wp_die();
}

// Handle AJAX request to delete a club member
add_action('wp_ajax_delete_club_member', 'delete_club_member');

function delete_club_member() {
    global $wpdb;

    $member_id = intval($_POST['member_id']);
    if ($member_id) {
        $deleted = $wpdb->delete("{$wpdb->prefix}club_members", ['id' => $member_id], ['%d']);

        if ($deleted) {
            wp_send_json_success();
        } else {
            wp_send_json_error();
        }
    } else {
        wp_send_json_error();
    }
    wp_die();
}

// Deleting subscription of user in manager dashboard
add_action('wp_ajax_bulk_delete_users', 'handle_bulk_delete_users');

function handle_bulk_delete_users() {
   

    global $wpdb;

    if (!isset($_POST['user_ids'])) {
        wp_send_json_error(['message' => 'No user IDs provided.']);
    }

    $user_ids = explode(',', sanitize_text_field($_POST['user_ids']));
    $user_ids = array_filter(array_map('intval', $user_ids));

    foreach ($user_ids as $user_id) {
        // Get the subscription ID linked to this user
        $subscription_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT p.ID
                 FROM {$wpdb->prefix}posts p
                 INNER JOIN {$wpdb->prefix}postmeta pm 
                    ON p.ID = pm.post_id 
                 WHERE p.post_type = 'shop_subscription' 
                   AND pm.meta_key = '_customer_user' 
                   AND pm.meta_value = %d
                 LIMIT 1",
                $user_id
            )
        );

        if ($subscription_id) {
            // Update post_status directly to 'trash'
            $wpdb->update(
                $wpdb->posts,
                ['post_status' => 'trash'],
                ['ID' => $subscription_id]
            );
            clean_post_cache($subscription_id); // Optional: Clear cache
        }
    }

    wp_send_json_success(['message' => 'Selected subscriptions moved to trash.']);
}


add_action('wp_ajax_search_pages', 'club_manager_search_pages');
function club_manager_search_pages() {
    if (!isset($_GET['search'])) {
        wp_send_json([]); // Return an empty array if no search term
        return;
    }

    $search_term = sanitize_text_field($_GET['search']);

    // Query pages matching the search term
    $pages = get_posts([
        'post_type'      => 'page',
        'post_status'    => 'publish',
        's'              => $search_term, // Search term
        'posts_per_page' => 10,           // Limit results
    ]);

    // Prepare results for the dropdown
    $results = [];
    foreach ($pages as $page) {
        $results[] = [
            'id'    => get_permalink($page->ID), // Return the permalink as the ID
            'text'  => $page->post_title,        // Display title for the dropdown
        ];
    }

    wp_send_json($results); // Send JSON response
}


add_action('wp_ajax_get_specific_user_meta', function () {
    $user_id = intval($_GET['user_id']);
    if (!$user_id) {
        wp_send_json_error('Invalid user ID');
    }

    $fields = [
        'billing_first_name', 'billing_last_name', 'billing_email', 'billing_phone',
        'user_second_phone', 'user_idnumber', 'user_msa_number', 
        'user_postal_address_1', 'user_postal_address_2', 'user_postal_address_3',
        'user_postal_address_4', 'user_postal_address_5',
        'user_medaid_company', 'user_medaid_number', 'user_medaid_phone',
        'user_dr_name', 'user_dr_phone', 'user_ice_contact_name', 'user_ice_contact_number',
        'user_bike_1_make', 'user_bike_1_model', 'user_bike_1_year', 'user_bike_1_registration_number',
        'user_howfind', 'user_iagree'
    ];

    $user_meta = [];
    foreach ($fields as $field) {
        $user_meta[$field] = get_user_meta($user_id, $field, true);
    }

    wp_send_json_success(['user_meta' => $user_meta]);
});


add_action('wp_ajax_update_specific_user_meta', function () {
    $user_id = intval($_POST['user_id']);
    if (!$user_id) {
        wp_send_json_error('Invalid user ID');
    }

    $fields = [
        'billing_first_name', 'billing_last_name', 'billing_email', 'billing_phone',
        'user_second_phone', 'user_idnumber', 'user_msa_number', 
        'user_postal_address_1', 'user_postal_address_2', 'user_postal_address_3',
        'user_postal_address_4', 'user_postal_address_5',
        'user_medaid_company', 'user_medaid_number', 'user_medaid_phone',
        'user_dr_name', 'user_dr_phone', 'user_ice_contact_name', 'user_ice_contact_number',
        'user_bike_1_make', 'user_bike_1_model', 'user_bike_1_year', 'user_bike_1_registration_number',
        'user_howfind', 'user_iagree'
    ];

    $updated_fields = [];

    foreach ($fields as $field) {
        if (isset($_POST[$field])) {
            $value = sanitize_text_field($_POST[$field]);
            $existing_value = get_user_meta($user_id, $field, true);

            if ($existing_value !== $value) {
                $updated = update_user_meta($user_id, $field, $value);
                if ($updated) {
                    $updated_fields[$field] = $value;
                }
            }
        }
    }

    if (!empty($updated_fields)) {
        wp_send_json_success(['message' => 'User meta updated successfully.', 'updated_fields' => $updated_fields]);
    } else {
        wp_send_json_error('No fields were updated.');
    }
});







add_action('wp_ajax_get_specific_event_meta', function () {
    $event_id = intval($_GET['event_id']);
    if (!$event_id) {
        wp_send_json_error('Invalid event ID');
    }

    $fields = [
        '_event_category', '_event_title', '_event_subtitle', '_event_description',
        '_unix_start_ev', '_unix_end_ev', '_event_organizer', '_event_location',
        '_featured_image'
    ];

    $event_meta = [];
    foreach ($fields as $field) {
        $event_meta[$field] = get_post_meta($event_id, $field, true);
    }

    wp_send_json_success(['event_meta' => $event_meta]);
});







function fetch_user_names_v2() {
    global $wpdb;

    // Get the search term
    $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';

    // Query users matching the search term
    $users = $wpdb->get_results($wpdb->prepare(
        "SELECT ID, display_name, user_email AS email 
         FROM {$wpdb->users} 
         WHERE display_name LIKE %s OR user_login LIKE %s 
         LIMIT 10",
        '%' . $wpdb->esc_like($search) . '%',
        '%' . $wpdb->esc_like($search) . '%'
    ));

    // Prepare the result to send both name and email
    $formatted_users = array_map(function($user) {
        return [
            'id' => $user->ID,
            'name' => $user->display_name, // Only name for display
            'email' => $user->email       // Email remains in the data
        ];
    }, $users);

    // Send the results as JSON
    wp_send_json($formatted_users);
}
add_action('wp_ajax_fetch_user_names_v2', 'fetch_user_names_v2');
add_action('wp_ajax_nopriv_fetch_user_names_v2', 'fetch_user_names_v2'); // Allow non-logged-in users


function save_club_member_v2() {
    global $wpdb;

    // Get POST data
    $club_id = isset($_POST['club_id']) ? intval($_POST['club_id']) : 0;
    $member_name = isset($_POST['member_name']) ? sanitize_text_field($_POST['member_name']) : '';
    $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
    $custom_title = isset($_POST['custom_title']) ? sanitize_text_field($_POST['custom_title']) : '';

    // Validate required fields: either 'title' or 'custom_title' must be provided
    if (!$club_id || !$member_name || !$email || !$user_id || (!$title && !$custom_title)) {
        wp_send_json_error(['message' => __('Missing required fields. Please provide either a Title or a Custom Title.', 'club-manager')]);
        return;
    }

    // Check if the user is already a member of the club
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}club_committee WHERE club_id = %d AND user_id = %d",
        $club_id,
        $user_id
    ));

    if ($exists) {
        wp_send_json_error(['message' => __('This user is already a Committee member of the club.', 'club-manager')]);
        return;
    }

    // Insert data into the database
    $result = $wpdb->insert(
        "{$wpdb->prefix}club_committee",
        [
            'club_id' => $club_id,
            'user_id' => $user_id,
            'name' => $member_name,
            'email' => $email,
            'title' => $title,
            'custom_title' => $custom_title,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ],
        ['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s']
    );

    // Return success or failure response
    if ($result) {
        wp_send_json_success(['message' => __('Member added successfully.', 'club-manager')]);
    } else {
        wp_send_json_error(['message' => __('Failed to add member.', 'club-manager')]);
    }
}
add_action('wp_ajax_save_club_member_v2', 'save_club_member_v2');
add_action('wp_ajax_nopriv_save_club_member_v2', 'save_club_member_v2'); // Allow non-logged-in users



// Register AJAX handler for subscription renewal
add_action('wp_ajax_renew_subscription', 'handle_subscription_renewal');

function handle_subscription_renewal() {
    global $wpdb;
    error_log("Starting subscription renewal process.");

    if (empty($_POST['subscription_id']) || !is_numeric($_POST['subscription_id'])) {
        wp_send_json_error(['message' => 'Invalid Subscription ID']);
    }

    $subscription_id = intval($_POST['subscription_id']);
    $subscription = wcs_get_subscription($subscription_id);

    if (!$subscription) {
        wp_send_json_error(['message' => 'Subscription not found']);
    }

    $status = $subscription->get_status();
    $user_id = $subscription->get_user_id();

    if (!$user_id) {
        wp_send_json_error(['message' => 'User ID not found']);
    }

    try {
        $user_info = get_userdata($user_id);
        $first_name = $user_info ? $user_info->first_name : '';
        $last_name = $user_info ? $user_info->last_name : '';

        // Step 1: Get Order ID Linked to the Expired/Canceled Subscription
        $order_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_parent FROM {$wpdb->posts} WHERE ID = %d AND post_type = 'shop_subscription'",
            $subscription_id
        ));

        if (!$order_id) {
            throw new Exception('No order found for this subscription.');
        }

        // Step 2: Find Membership ID Linked to This Order
        $existing_membership = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_order_id' AND meta_value = %d LIMIT 1",
            $order_id
        ));

        if (!$existing_membership) {
            throw new Exception('No membership found for this subscription.');
        }

        // Step 3: Get Membership Plan ID
        $plan_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_parent FROM {$wpdb->posts} WHERE ID = %d LIMIT 1",
            $existing_membership
        ));

        if (!$plan_id) {
            throw new Exception('Membership plan not found.');
        }

        // Step 4: Create a New Subscription and Order for Renewal
        $items = $subscription->get_items();
        $product_id = null;
        foreach ($items as $item) {
            $product_id = $item->get_product_id();
            break;
        }

        if (!$product_id || !WC_Subscriptions_Product::is_subscription($product_id)) {
            throw new Exception('Invalid subscription product.');
        }

        $start_date = current_time('mysql');
        $end_date_time = new DateTime($start_date);
        $end_date_time->modify('+1 year');
        $end_date = $end_date_time->format('Y-m-d H:i:s');

        $next_payment_date_time = clone $end_date_time;
        $next_payment_date_time->modify('-5 minutes');
        $next_payment = $next_payment_date_time->format('Y-m-d H:i:s');

        $billing_period = WC_Subscriptions_Product::get_period($product_id);
        $billing_interval = WC_Subscriptions_Product::get_interval($product_id);

        $new_subscription = wcs_create_subscription([
            'customer_id' => $user_id,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'billing_period' => $billing_period,
            'billing_interval' => $billing_interval,
            'status' => 'on-hold',
        ]);

        if (is_wp_error($new_subscription)) {
            throw new Exception($new_subscription->get_error_message());
        }

        $product = wc_get_product($product_id);
        $new_subscription->add_product($product, 1);
        $new_subscription->calculate_totals();
        $new_subscription->update_dates([
            'next_payment' => $next_payment,
            'end' => $end_date,
        ]);
        $new_subscription->save();

        $new_order = wcs_create_renewal_order($new_subscription);
        if (is_wp_error($new_order)) {
            throw new Exception($new_order->get_error_message());
        }

        $new_subscription->set_parent_id($new_order->get_id());
        $new_subscription->save();

        // Set billing first and last name in the new order
        update_post_meta($new_order->get_id(), '_billing_first_name', $first_name);
        update_post_meta($new_order->get_id(), '_billing_last_name', $last_name);

        // Step 5: Update the Existing Membership with the New Subscription and Order IDs
        update_post_meta($existing_membership, '_subscription_id', $new_subscription->get_id());
        update_post_meta($existing_membership, '_order_id', $new_order->get_id());

        // Keep the membership status unchanged
        $current_status = $wpdb->get_var($wpdb->prepare(
            "SELECT post_status FROM {$wpdb->prefix}posts WHERE ID = %d",
            $existing_membership
        ));

        wp_send_json_success([
            'message' => 'New subscription created successfully and linked to existing membership.',
            'membership_id' => $existing_membership,
            'new_subscription_id' => $new_subscription->get_id(),
            'new_order_id' => $new_order->get_id(),
            'membership_status' => $current_status
        ]);
    } catch (Exception $e) {
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}











// Register the AJAX handler for unique subscription renewal
add_action('wp_ajax_renew_subscription_unique', 'handle_subscription_renewal_unique');
add_action('wp_ajax_nopriv_renew_subscription_unique', 'handle_subscription_renewal_unique');

function handle_subscription_renewal_unique() {
    global $wpdb;
    error_log('AJAX Request Triggered');

    if (!isset($_POST['_ajax_nonce']) || !wp_verify_nonce($_POST['_ajax_nonce'], 'renew_subscription_nonce')) {
        wp_send_json_error(['message' => 'Invalid nonce.']);
        die();
    }

    if (empty($_POST['subscription_id']) || !is_numeric($_POST['subscription_id'])) {
        wp_send_json_error(['message' => 'Subscription ID not provided.']);
        die();
    }

    if (!class_exists('WC_Subscriptions')) {
        wp_send_json_error(['message' => 'WooCommerce Subscriptions plugin not active.']);
        die();
    }

    $subscription_id = intval($_POST['subscription_id']);
    $subscription = wcs_get_subscription($subscription_id);

    if (!$subscription) {
        wp_send_json_error(['message' => 'Subscription not found.']);
        die();
    }

    $status = $subscription->get_status();
    $user_id = $subscription->get_user_id();

    if (!$user_id) {
        wp_send_json_error(['message' => 'User ID not found']);
    }

    $user_info = get_userdata($user_id);
    $first_name = $user_info ? $user_info->first_name : '';
    $last_name = $user_info ? $user_info->last_name : '';

    try {
        // Step 1: Get the order ID linked to the expired/canceled subscription
        $order_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_parent FROM {$wpdb->posts} WHERE ID = %d AND post_type = 'shop_subscription'",
            $subscription_id
        ));

        if (!$order_id) {
            throw new Exception('No order found for this subscription.');
        }

        // Step 2: Find the membership ID linked to this order
        $existing_membership = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_order_id' AND meta_value = %d LIMIT 1",
            $order_id
        ));

        if (!$existing_membership) {
            throw new Exception('No membership found for this order.');
        }

        // Step 3: Get the membership plan ID
        $plan_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_parent FROM {$wpdb->posts} WHERE ID = %d LIMIT 1",
            $existing_membership
        ));

        if (!$plan_id) {
            throw new Exception('Membership plan not found.');
        }

        if ($status === 'active' || $status === 'on-hold') {
            $current_end_date = $subscription->get_date('end');
            if (!$current_end_date || $current_end_date == '0') {
                throw new Exception('Invalid subscription end date.');
            }

            $end_date_time = new DateTime($current_end_date);
            $end_date_time->modify('+1 year');
            $new_end_date = $end_date_time->format('Y-m-d H:i:s');

            $next_payment_date_time = clone $end_date_time;
            $next_payment_date_time->modify('-5 minutes');
            $new_next_payment = $next_payment_date_time->format('Y-m-d H:i:s');

            $subscription->update_dates(['end' => $new_end_date, 'next_payment' => $new_next_payment]);
            $subscription->save();

            $renewal_order = wcs_create_renewal_order($subscription);
            if (is_wp_error($renewal_order)) {
                throw new Exception($renewal_order->get_error_message());
            }

            update_post_meta($renewal_order->get_id(), '_billing_first_name', $first_name);
            update_post_meta($renewal_order->get_id(), '_billing_last_name', $last_name);

            // Step 4: Update the existing membership with the new subscription and order IDs
            update_post_meta($existing_membership, '_subscription_id', $subscription->get_id());
            update_post_meta($existing_membership, '_order_id', $renewal_order->get_id());

            wp_send_json_success([
                'message' => 'Subscription renewed successfully and linked to existing membership.',
                'membership_id' => $existing_membership,
                'new_subscription_id' => $subscription->get_id(),
                'new_order_id' => $renewal_order->get_id(),
            ]);
        } else {
            $items = $subscription->get_items();
            $product_id = null;
            foreach ($items as $item) {
                $product_id = $item->get_product_id();
                break;
            }

            if (!$product_id || !WC_Subscriptions_Product::is_subscription($product_id)) {
                throw new Exception('Invalid subscription product.');
            }

            $start_date = current_time('mysql');
            $end_date_time = new DateTime($start_date);
            $end_date_time->modify('+1 year');
            $end_date = $end_date_time->format('Y-m-d H:i:s');

            $next_payment_date_time = clone $end_date_time;
            $next_payment_date_time->modify('-5 minutes');
            $next_payment = $next_payment_date_time->format('Y-m-d H:i:s');

            $billing_period = WC_Subscriptions_Product::get_period($product_id);
            $billing_interval = WC_Subscriptions_Product::get_interval($product_id);

            $new_subscription = wcs_create_subscription([
                'customer_id' => $user_id,
                'start_date' => $start_date,
                'end_date' => $end_date,
                'billing_period' => $billing_period,
                'billing_interval' => $billing_interval,
                'status' => 'on-hold',
            ]);

            if (is_wp_error($new_subscription)) {
                throw new Exception($new_subscription->get_error_message());
            }

            $product = wc_get_product($product_id);
            $new_subscription->add_product($product, 1);
            $new_subscription->calculate_totals();
            $new_subscription->update_dates(['next_payment' => $next_payment, 'end' => $end_date]);
            $new_subscription->save();

            $new_order = wcs_create_renewal_order($new_subscription);
            if (is_wp_error($new_order)) {
                throw new Exception($new_order->get_error_message());
            }

            update_post_meta($new_order->get_id(), '_billing_first_name', $first_name);
            update_post_meta($new_order->get_id(), '_billing_last_name', $last_name);

            // Step 5: Update the existing membership with the new subscription and order IDs
            update_post_meta($existing_membership, '_subscription_id', $new_subscription->get_id());
            update_post_meta($existing_membership, '_order_id', $new_order->get_id());

            wp_send_json_success([
                'message' => 'New subscription created successfully and linked to existing membership.',
                'membership_id' => $existing_membership,
                'new_subscription_id' => $new_subscription->get_id(),
                'new_order_id' => $new_order->get_id(),
            ]);
        }
    } catch (Exception $e) {
        wp_send_json_error(['message' => $e->getMessage()]);
    }

    die();
}


add_action('init', function() {
    global $wp_rewrite;
    $wp_rewrite->pagination_base = 'page';
    $wp_rewrite->flush_rules(false); // Flush rewrite rules to recognize pagination
});



// ==============================
// AJAX handler: Export Event Orders to CSV
// ==============================
function my_event_csv_ajax() {
    global $wpdb;

    $e_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
    if (!$e_id) {
        wp_die('Invalid Event ID');
    }

    $pid = get_post_meta($e_id, 'tx_woocommerce_product_id', true);
    if (empty($pid)) {
        wp_die('No product linked to this event.');
    }

    $event = get_post($e_id);
    if (!$event) {
        wp_die('Event not found.');
    }

    $event_title = sanitize_title($event->post_title);

    $headers = [
        'Name','Email','Company','Address','Phone',
        'Ticket IDs','Quantity','Ticket Type',
        'Event Time','Order Status','Ordered Date'
    ];

    // Core query (similar to plugin)
    $query = $wpdb->prepare("
        SELECT 
            o.ID AS order_id,
            o.post_date AS ordered_date,
            REPLACE(o.post_status,'wc-','') AS order_status,
            oi.order_item_id,

            (SELECT meta_value FROM {$wpdb->postmeta} 
             WHERE post_id = o.ID AND meta_key = '_billing_first_name' LIMIT 1) AS First_Name,
            (SELECT meta_value FROM {$wpdb->postmeta} 
             WHERE post_id = o.ID AND meta_key = '_billing_last_name' LIMIT 1) AS Last_Name,
            (SELECT meta_value FROM {$wpdb->postmeta} 
             WHERE post_id = o.ID AND meta_key = '_billing_email' LIMIT 1) AS Email,
            (SELECT meta_value FROM {$wpdb->postmeta} 
             WHERE post_id = o.ID AND meta_key = '_billing_company' LIMIT 1) AS Company,
            (SELECT meta_value FROM {$wpdb->postmeta} 
             WHERE post_id = o.ID AND meta_key = '_billing_address_1' LIMIT 1) AS Address,
            (SELECT meta_value FROM {$wpdb->postmeta} 
             WHERE post_id = o.ID AND meta_key = '_billing_phone' LIMIT 1) AS Phone,

            (SELECT meta_value FROM {$wpdb->prefix}woocommerce_order_itemmeta 
             WHERE order_item_id = oi.order_item_id AND meta_key = '_qty' LIMIT 1) AS Quantity,

            (SELECT meta_value FROM {$wpdb->prefix}woocommerce_order_itemmeta 
             WHERE order_item_id = oi.order_item_id AND meta_key = 'Event-Time' LIMIT 1) AS Event_Time,

            -- Ticket Type (variation attribute if exists, else Normal)
            COALESCE(
              (SELECT meta_value 
                 FROM {$wpdb->prefix}woocommerce_order_itemmeta 
                WHERE order_item_id = oi.order_item_id 
                  AND meta_key LIKE 'attribute_%%'
                LIMIT 1),
              'Normal'
            ) AS Ticket_Type

        FROM {$wpdb->posts} o
        JOIN {$wpdb->prefix}woocommerce_order_items oi 
            ON o.ID = oi.order_id
        JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim 
            ON oi.order_item_id = oim.order_item_id 
            AND oim.meta_key = '_product_id'
        WHERE o.post_type = 'shop_order'
          AND oim.meta_value = %d
        ORDER BY o.post_date DESC
    ", $pid);

    $rows = $wpdb->get_results($query);

    while (ob_get_level()) {
        ob_end_clean();
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $event_title . '_' . date('d-m-Y') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    fputcsv($out, $headers);

    foreach ($rows as $r) {
        $name = trim(($r->First_Name ?: '') . ' ' . ($r->Last_Name ?: ''));
        $qty  = intval($r->Quantity ?: 1);

        // Expand one row per ticket
        for ($i = 0; $i < $qty; $i++) {
            $ticket_id = $r->order_item_id . '-' . $r->order_id . '-' . $pid . 'T' . $i;

            fputcsv($out, [
                $name,
                $r->Email,
                $r->Company,
                $r->Address,
                $r->Phone,
                $ticket_id,
                1, // always 1 per ticket row
                $r->Ticket_Type,
                $r->Event_Time,
                $r->order_status,
                $r->ordered_date,
            ]);
        }
    }

    fclose($out);
    wp_die();
}
add_action('wp_ajax_my_event_csv', 'my_event_csv_ajax');
add_action('wp_ajax_nopriv_my_event_csv', 'my_event_csv_ajax');



add_action('wp_head', function () {

    if (is_admin()) return;

    global $wpdb;

    $rows = $wpdb->get_results("
        SELECT 
            p.ID as product_id,
            IFNULL(c.club_currency, 'R') as club_currency
        FROM wp_posts p
        LEFT JOIN wp_postmeta pm 
            ON pm.post_id = p.ID AND pm.meta_key = '_select_club_id'
        LEFT JOIN wp_clubs c 
            ON c.club_id = pm.meta_value
        WHERE p.post_type = 'product' AND p.post_status = 'publish'
    ");

    $map = [];
    foreach ($rows as $r) {
        $map[(int)$r->product_id] = $r->club_currency ?: 'R';
    }
?>
<script>
window.productCurrencyMap = <?php echo json_encode($map); ?>;
console.log("🟢 MAP READY:", window.productCurrencyMap);
</script>
<?php
});

add_action('wp_head', function () {
    if (is_admin()) return;
?>
<script>
document.addEventListener("DOMContentLoaded", function () {

    console.log("⚡ GLOBAL INIT");

    const map = window.productCurrencyMap || {};

    function updatePrices() {
        const items = document.querySelectorAll("li.product");

        console.log("📦 Found products:", items.length);

        items.forEach(li => {

            if (li.dataset.currencyUpdated === "1") return;

            const idClass = [...li.classList].find(c => c.startsWith("post-"));
            if (!idClass) return;

            const pid = idClass.replace("post-", "");
            const currency = map[pid];

            if (!currency) return;

            // Try every possible price selector
            let priceContainers = li.querySelectorAll(".price, .amount, bdi, span");

            priceContainers.forEach(el => {
                let html = el.innerHTML;

                // Replace standalone R
                html = html.replace(/R(?=\d)/g, currency);

                // Replace <span>R</span>
                html = html.replace(/<span[^>]*>R<\/span>/g, `<span>${currency}</span>`);

                // Replace currency symbol inside <bdi>
                html = html.replace(/<bdi>R/g, `<bdi>${currency}`);

                el.innerHTML = html;
            });

            li.dataset.currencyUpdated = "1";

            console.log(`🔄 Updated product ${pid} → ${currency}`);
        });
    }

    updatePrices();

    const observer = new MutationObserver(() => updatePrices());
    observer.observe(document.body, { childList: true, subtree: true });

    console.log("👀 Observer Active");
});
</script>
<?php
});





?>
