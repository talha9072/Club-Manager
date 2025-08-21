<?php

function restrict_content_by_club($query) {
    // Ensure this is ONLY for admin queries and prevent frontend execution
    if (!is_admin() || !$query->is_main_query()) {
        return;
    }

    // Ensure it's NOT running in AJAX requests
    if (defined('DOING_AJAX') && DOING_AJAX) {
        return;
    }

    // Ensure it's NOT running in REST API calls
    if (defined('REST_REQUEST') && REST_REQUEST) {
        return;
    }

    // Allowed post types to restrict
    $restricted_post_types = ['ajde_events', 'aw_workflow'];

    // Check if the query is for one of these post types
    $post_type = $query->get('post_type');
    if (!$post_type || !in_array($post_type, $restricted_post_types, true)) {
        return;
    }

    // Get logged-in user's club details (Cache to avoid redundant calls)
    static $club_details_cache = null;
    if ($club_details_cache === null) {
        require_once dirname(__FILE__) . '/helper.php'; // Ensure correct path
        $club_details_cache = get_logged_in_user_club();
    }

    if (!$club_details_cache || !isset($club_details_cache['club_id'])) {
        return; // User is not assigned to a club, do not alter query
    }

    $club_id = intval($club_details_cache['club_id']);

    // Modify the query to filter only items that belong to the user's club
    $meta_query = (array) $query->get('meta_query');

    // Apply club filtering for posts, products, and events
    if (in_array($post_type, ['product', 'post', 'ajde_events'], true)) {
        $meta_query[] = [
            'key'     => '_select_club_id',
            'value'   => $club_id,
            'compare' => '='
        ];
        $query->set('meta_query', $meta_query);
    }

    // Restrict AutomateWoo workflows (`aw_workflow`)
    if ($post_type === 'aw_workflow') {
        global $wpdb;

        // Get workflows linked to the user's club (Avoids unnecessary queries if cache exists)
        static $workflow_cache = null;
        if ($workflow_cache === null) {
            $workflow_cache = $wpdb->get_var($wpdb->prepare(
                "SELECT notifications FROM {$wpdb->prefix}clubs WHERE club_id = %d",
                $club_id
            ));
        }

        if (!empty($workflow_cache)) {
            $workflow_ids = array_map('intval', explode(',', $workflow_cache));
            if (!empty($workflow_ids)) {
                $query->set('post__in', $workflow_ids);
            } else {
                $query->set('post__in', [0]); // Prevents showing all workflows
            }
        } else {
            $query->set('post__in', [0]); // Ensures no workflows are displayed if none exist
        }
    }

    // Debugging: Log filtered queries
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log("✅ Restricting content (Post Type: " . esc_html($post_type) . ") for Club ID: " . esc_html($club_id));
    }
}

add_action('pre_get_posts', 'restrict_content_by_club');


// pages
// Restrict admin pages list by club association via custom field
function restrict_pages_by_club_meta($query) {
    // Only run in admin area, main query, and for 'page' post type
    if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== 'page') {
        return;
    }

    // Skip AJAX and REST requests
    if (defined('DOING_AJAX') && DOING_AJAX) return;
    if (defined('REST_REQUEST') && REST_REQUEST) return;

    // Allow full access to administrators
    if (current_user_can('administrator')) return;

    // Get current user's club_id
    static $club_id = null;
    if ($club_id === null) {
        require_once dirname(__FILE__) . '/helper.php'; // Adjust path if needed
        $club_details = get_logged_in_user_club();
        $club_id = isset($club_details['club_id']) ? intval($club_details['club_id']) : 0;
    }

    if (!$club_id) return; // If no club assigned, don't restrict

    // Modify query to only show pages where meta key _select_club_id matches current club_id
    $meta_query = array(
        array(
            'key'     => '_select_club_id',
            'value'   => $club_id,
            'compare' => '='
        )
    );

    $query->set('meta_query', $meta_query);
}

// Attach the function to WordPress
add_action('pre_get_posts', 'restrict_pages_by_club_meta');



// Product restrcition
// Restrict products by club in the admin panel
function restrict_products_by_club($query) {
    // Ensure this runs only in the WooCommerce admin product query
    if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== 'product') {
        return;
    }

    // Prevent execution in AJAX and REST API requests
    if (defined('DOING_AJAX') && DOING_AJAX) {
        return;
    }
    if (defined('REST_REQUEST') && REST_REQUEST) {
        return;
    }

    // Get logged-in user's club details (Cache to avoid redundant calls)
    static $club_details_cache = null;
    if ($club_details_cache === null) {
        require_once dirname(__FILE__) . '/helper.php'; // Ensure correct path
        $club_details_cache = get_logged_in_user_club();
    }

    if (!$club_details_cache || !isset($club_details_cache['club_id'])) {
        return; // User is not assigned to a club, do not alter query
    }

    global $wpdb;
    $club_id = intval($club_details_cache['club_id']);

    // Fetch correct product IDs that belong to the user's club (Cache result)
    static $product_ids_cache = null;
    if ($product_ids_cache === null) {
        $product_ids_cache = $wpdb->get_col($wpdb->prepare("
            SELECT DISTINCT p.ID
            FROM {$wpdb->prefix}posts p
            INNER JOIN {$wpdb->prefix}postmeta pm ON p.ID = pm.post_id
                AND pm.meta_key = '_select_club_id'
                AND pm.meta_value = %d
            WHERE p.post_type = 'product'
            AND p.post_parent = 0
            AND p.post_status != 'trash' 
            AND NOT EXISTS (
                SELECT 1 FROM {$wpdb->prefix}term_relationships tr
                INNER JOIN {$wpdb->prefix}term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                INNER JOIN {$wpdb->prefix}terms t ON tt.term_id = t.term_id
                WHERE tr.object_id = p.ID
                AND t.slug IN ('ticket', 'freeticket')
            )
            ORDER BY p.post_date DESC;
        ", $club_id));
    }

    if (!empty($product_ids_cache)) {
        $query->set('post__in', $product_ids_cache);
        $query->set('meta_query', []);
        $query->set('tax_query', []);
    } else {
        $query->set('post__in', [0]); // Prevent showing all products
    }

    // Debugging: Log filtered queries
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log("✅ Restricting products for Club ID: " . esc_html($club_id) . " - Products: " . implode(',', $product_ids_cache));
    }
}

// Ensure it runs only in admin WooCommerce product queries
add_action('pre_get_posts', 'restrict_products_by_club');




// post restrictions
// Restrict posts by club in the admin panel
function restrict_posts_by_club($query) {
    // Ensure this runs only in the admin panel and post type is 'post'
    if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== 'post') {
        return;
    }

    // Prevent execution in AJAX and REST API requests
    if (defined('DOING_AJAX') && DOING_AJAX) {
        return;
    }
    if (defined('REST_REQUEST') && REST_REQUEST) {
        return;
    }

    // Get logged-in user's club details (Cache to avoid redundant calls)
    static $club_details_cache = null;
    if ($club_details_cache === null) {
        require_once dirname(__FILE__) . '/helper.php'; // Ensure correct path
        $club_details_cache = get_logged_in_user_club();
    }

    if (!$club_details_cache || !isset($club_details_cache['club_id'])) {
        return; // User is not assigned to a club, do not alter query
    }

    global $wpdb;
    $club_id = intval($club_details_cache['club_id']);

    // Fetch all post IDs that match the user's club ID (Ensuring 29 results)
    static $post_ids_cache = null;
    if ($post_ids_cache === null) {
        $post_ids_cache = $wpdb->get_col($wpdb->prepare("
            SELECT DISTINCT p.ID
            FROM {$wpdb->prefix}posts p
            LEFT JOIN {$wpdb->prefix}postmeta pm ON p.ID = pm.post_id
            WHERE pm.meta_key = '_select_club_id'
            AND pm.meta_value = %d
            AND p.post_type = 'post'
            AND p.post_status != 'trash'
            ORDER BY p.post_date DESC;
        ", $club_id));
    }

    if (!empty($post_ids_cache)) {
        $query->set('post__in', $post_ids_cache);
        $query->set('meta_query', []); // Reset any meta query
        $query->set('tax_query', []); // Reset any taxonomy query
    } else {
        $query->set('post__in', [0]); // Prevent showing all posts if no match found
    }

    // Debugging: Log filtered queries
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log("✅ Restricting posts for Club ID: " . esc_html($club_id) . " - Posts: " . count($post_ids_cache));
    }
}

// Ensure it runs only in admin post queries
add_action('pre_get_posts', 'restrict_posts_by_club');



// orders restrcitions


add_action('pre_get_posts', 'restrict_orders_by_club_in_backend');

function restrict_orders_by_club_in_backend($query) {
    global $wpdb;

    // Only modify in admin main query for shop_order
    if (
        !is_admin() ||
        !$query->is_main_query() ||
        empty($query->query_vars['post_type']) ||
        $query->query_vars['post_type'] !== 'shop_order'
    ) {
        return;
    }

    // Get current user
    $current_user = wp_get_current_user();
    if (!$current_user->exists()) {
        return;
    }

    // If user is admin, do not restrict
    if (in_array('administrator', (array) $current_user->roles)) {
        return;
    }

    $user_email = $current_user->user_email;

    // Get user's club ID
    $club_id = $wpdb->get_var($wpdb->prepare(
        "SELECT club_id FROM {$wpdb->prefix}club_members WHERE user_email = %s LIMIT 1",
        $user_email
    ));

    if (!$club_id) {
        $query->set('post__in', [0]);
        return;
    }

    // Get all product IDs linked to club
    $product_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT p.ID FROM {$wpdb->prefix}posts p
         INNER JOIN {$wpdb->prefix}postmeta pm ON p.ID = pm.post_id
         WHERE p.post_type = 'product' AND pm.meta_key = '_select_club_id' AND pm.meta_value = %d",
        $club_id
    ));

    if (empty($product_ids)) {
        $query->set('post__in', [0]);
        return;
    }

    // Sanitize product IDs and prepare placeholders
    $product_ids_int = array_map('intval', $product_ids);
    $placeholders = implode(',', array_fill(0, count($product_ids_int), '%d'));

    // Fetch order IDs where those products exist in order items
    $order_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT oi.order_id
         FROM {$wpdb->prefix}woocommerce_order_items oi
         INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id
         WHERE oim.meta_key = '_product_id' AND oim.meta_value IN ($placeholders)",
        ...$product_ids_int
    ));

    if (empty($order_ids)) {
        $query->set('post__in', [0]);
        return;
    }

    // Limit to those orders
    $query->set('post__in', $order_ids);
}







// subscription restrction

add_action('pre_get_posts', 'restrict_subscriptions_by_club');

function restrict_subscriptions_by_club($query) {
    global $wpdb;

    // Ensure the query is for WooCommerce Subscriptions in the admin
    if (!is_admin() || !$query->is_main_query() || empty($query->query_vars['post_type']) || $query->query_vars['post_type'] !== 'shop_subscription') {
        return;
    }

    // Get the logged-in user
    $current_user = wp_get_current_user();
    if (!$current_user->exists()) {
        return;
    }
    
    // Allow administrators to see all subscriptions
    if (in_array('administrator', (array) $current_user->roles)) {
        return;
    }
    

    $user_email = $current_user->user_email;

    // Fetch the logged-in user's club ID
    $club_id = $wpdb->get_var($wpdb->prepare(
        "SELECT club_id FROM {$wpdb->prefix}club_members WHERE user_email = %s AND role = 'Club Manager'",
        $user_email
    ));

    if (!$club_id) {
        return; // Exit if no club is found
    }

    // Get all product IDs linked to the club
    $product_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT p.ID FROM {$wpdb->prefix}posts p
        INNER JOIN {$wpdb->prefix}postmeta pm ON p.ID = pm.post_id
        WHERE p.post_type = 'product'
        AND pm.meta_key = '_select_club_id'
        AND pm.meta_value = %d",
        $club_id
    ));

    if (empty($product_ids)) {
        $query->set('post__in', [0]); // No subscriptions found
        return;
    }

    // Get all subscription IDs linked to these products
    $subscription_ids = $wpdb->get_col(
        "SELECT DISTINCT sub.ID FROM {$wpdb->prefix}posts sub
        INNER JOIN {$wpdb->prefix}woocommerce_order_items oi ON sub.ID = oi.order_id
        INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id
        WHERE sub.post_type = 'shop_subscription'
        AND oim.meta_key = '_product_id'
        AND oim.meta_value IN (" . implode(',', array_map('intval', $product_ids)) . ")"
    );

    if (empty($subscription_ids)) {
        $query->set('post__in', [0]); // No subscriptions found
        return;
    }

    // Restrict the subscriptions query to the filtered subscription IDs
    $query->set('post__in', $subscription_ids);
}




add_action('pre_user_query', 'restrict_users_by_club_subscription');

function restrict_users_by_club_subscription($query) {
    global $wpdb;

    // Ensure this is executed only in the admin Users list
    if (!is_admin()) {
        return;
    }

    // Get the logged-in user
    $current_user = wp_get_current_user();
    if (!$current_user->exists()) {
        return;
    }

    $user_email = $current_user->user_email;
    error_log("🔍 Logged-in user: " . $user_email);

    // Fetch the logged-in user's club ID
    $club_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT club_id FROM {$wpdb->prefix}club_members 
             WHERE user_email = %s AND role = 'Club Manager' LIMIT 1",
            $user_email
        )
    );

    if (!$club_id) {
        return;
    }

    // Get all product IDs linked to the club
    $product_ids = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT DISTINCT p.ID 
             FROM {$wpdb->prefix}posts p
             JOIN {$wpdb->prefix}postmeta pm ON p.ID = pm.post_id
             WHERE p.post_type = 'product'
               AND pm.meta_key = '_select_club_id'
               AND pm.meta_value = %d",
            $club_id
        )
    );

    if (empty($product_ids)) {
        return;
    }
    error_log("✅ Found " . count($product_ids) . " products linked to the club.");

    // Get all user IDs who have an active subscription to these products
    $placeholders = implode(',', array_fill(0, count($product_ids), '%d'));
    $query_str = "
        SELECT DISTINCT pm.meta_value AS user_id
        FROM {$wpdb->prefix}postmeta pm
        INNER JOIN {$wpdb->prefix}posts sub ON pm.post_id = sub.ID
        INNER JOIN {$wpdb->prefix}woocommerce_order_items oi ON sub.ID = oi.order_id
        INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id
        WHERE sub.post_type = 'shop_subscription'
        AND pm.meta_key = '_customer_user'
        AND oim.meta_key = '_product_id'
        AND oim.meta_value IN ($placeholders)
    ";

    $subscribed_user_ids = $wpdb->get_col($wpdb->prepare($query_str, ...array_map('intval', $product_ids)));

    if (empty($subscribed_user_ids)) {
        return;
    }

    $filtered_user_ids = array_map('intval', array_filter($subscribed_user_ids));

    // Modify the user query to restrict users
    $ids_string = implode(',', $filtered_user_ids);
    $query->query_where .= " AND {$wpdb->users}.ID IN ($ids_string)";
}




add_action('admin_head', function () {
    global $wpdb, $current_user;

    // Get the current user
    wp_get_current_user();
    $user_email = $current_user->user_email;

    // Allow admins to see everything
    if (current_user_can('administrator')) {
        return;
    }

    // Check if the user is listed in `wp_club_members`
    $is_club_user = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}club_members WHERE user_email = %s",
            $user_email
        )
    );

    // If user is in club members table, hide filters, bulk actions, and search
    if ($is_club_user) {
        echo '<style>
            .wp-filter, 
            .subsubsub, 
            .tablenav.top, 
            .tablenav .actions,
            .search-box {
                display: none !important;
            }
        </style>';
    }
});





// removing custom fields
function remove_custom_fields_and_post_attributes_for_club_members() {
    if (!current_user_can('administrator')) { // Only for non-admins
        $post_types = ['post', 'page', 'ajde_events', 'product']; // Define post types

        foreach ($post_types as $post_type) {
            remove_meta_box('postcustom', $post_type, 'normal'); // Remove "Custom Fields"
            remove_meta_box('pageparentdiv', $post_type, 'side'); // Remove "Post Attributes"
            remove_meta_box('et_settings_meta_box', $post_type, 'side'); // Remove Divi Page Settings
        }
    }
}
add_action('add_meta_boxes', 'remove_custom_fields_and_post_attributes_for_club_members', 999);
