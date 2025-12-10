<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Global $wpdb instance
global $wpdb;

// Get the current logged-in user
$current_user = wp_get_current_user();
if (!$current_user->exists()) {
    echo '<p>You must be logged in to view this information.</p>';
    return;
}

// Check for 'club' and '2ndclubid' parameters in the URL
$club_id = null;
$club_name = null;

if (isset($_GET['club']) && !empty($_GET['club']) && isset($_GET['2ndclubid']) && !empty($_GET['2ndclubid'])) {
    $club_name = urldecode(sanitize_text_field($_GET['club']));
    $club_id = intval($_GET['2ndclubid']);

    // Validate the club information from the database
    $club_info = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT club_id, club_name 
             FROM {$wpdb->prefix}clubs 
             WHERE club_name = %s AND club_id = %d",
            $club_name,
            $club_id
        )
    );

    if (!$club_info) {
        echo '<p>The provided club details are invalid.</p>';
        return;
    }
} else {
    // Fallback: Get the logged-in user's club details
    $user_email = $current_user->user_email;

    $club_info = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT cm.club_id, cm.club_name 
             FROM {$wpdb->prefix}club_members cm
             WHERE cm.user_email = %s LIMIT 1",
            $user_email
        )
    );

    if (!$club_info) {
        echo '<p>You are not associated with any club.</p>';
        return;
    }

    // Set the club_id and club_name from the user's data
    $club_id = $club_info->club_id;
    $club_name = $club_info->club_name;
}

// Fetch all product IDs associated with the determined club ID
$product_ids = $wpdb->get_col(
    $wpdb->prepare(
        "SELECT DISTINCT p.ID 
         FROM {$wpdb->prefix}posts p
         JOIN {$wpdb->prefix}postmeta pm 
         ON p.ID = pm.post_id
         WHERE p.post_type = 'product'
           AND pm.meta_key = '_select_club_id'
           AND pm.meta_value = %d",
        $club_id
    )
);

if (empty($product_ids)) {
    echo '<p>No products are associated with your club.</p>';
    return;
}

// Proceed with further processing using $product_ids


if (empty($product_ids)) {
    echo '<p>No products found for your club: ' . esc_html($club_info->club_name) . '.</p>';
    return;
}

// Validate product IDs before using implode
$product_ids_sanitized = array_map('intval', $product_ids);
if (empty($product_ids_sanitized)) {
    echo '<p>No products found for your club: ' . esc_html($club_info->club_name) . '.</p>';
    return;
}

global $wpdb;
$current_user = wp_get_current_user();

if (!$current_user->exists()) {
    echo '<p>You must be logged in to view this information.</p>';
    return;
}

// Generate a unique cache table name based on the user's ID or email
$cache_table_name = 'club_users_cache_' . md5($current_user->user_email);

// Check if the temporary table exists in the current session
$check_cache = $wpdb->get_var("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '$cache_table_name'");

if (!$check_cache) {
    // Create the temporary table if it does not exist
    $create_table_query = "
    CREATE TEMPORARY TABLE $cache_table_name (
        user_email VARCHAR(255) NOT NULL,
        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    );
    ";
    $wpdb->query($create_table_query);
    echo '';

    // Insert the users into the temporary table
    $insert_query = "
    INSERT INTO $cache_table_name (user_email)
    SELECT DISTINCT u.user_email
    FROM {$wpdb->prefix}users u
    INNER JOIN {$wpdb->prefix}postmeta pm_customer 
        ON u.ID = pm_customer.meta_value AND pm_customer.meta_key = '_customer_user'
    INNER JOIN {$wpdb->prefix}posts sub 
        ON pm_customer.post_id = sub.ID AND sub.post_type = 'shop_subscription'
    INNER JOIN {$wpdb->prefix}woocommerce_order_items sub_oi 
        ON sub.ID = sub_oi.order_id
    INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta sub_oim 
        ON sub_oi.order_item_id = sub_oim.order_item_id
    WHERE sub_oim.meta_key = '_product_id'
    AND sub_oim.meta_value IN (" . implode(",", $product_ids_sanitized) . ")
    ";
    $wpdb->query($insert_query);

} else {
    // Check if data in cache is older than 1 hour
    $cache_time_check = $wpdb->get_var("
        SELECT TIMESTAMPDIFF(HOUR, MAX(last_updated), NOW())
        FROM $cache_table_name
    ");

    if ($cache_time_check > 1) {
        // Refresh cache (insert fresh data)
        $wpdb->query("TRUNCATE TABLE $cache_table_name");

        $insert_query = "
        INSERT INTO $cache_table_name (user_email)
        SELECT DISTINCT u.user_email
        FROM {$wpdb->prefix}users u
        INNER JOIN {$wpdb->prefix}postmeta pm_customer 
            ON u.ID = pm_customer.meta_value AND pm_customer.meta_key = '_customer_user'
        INNER JOIN {$wpdb->prefix}posts sub 
            ON pm_customer.post_id = sub.ID AND sub.post_type = 'shop_subscription'
        INNER JOIN {$wpdb->prefix}woocommerce_order_items sub_oi 
            ON sub.ID = sub_oi.order_id
        INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta sub_oim 
            ON sub_oi.order_item_id = sub_oim.order_item_id
        WHERE sub_oim.meta_key = '_product_id'
        AND sub_oim.meta_value IN (" . implode(",", $product_ids_sanitized) . ")
        ";
        $wpdb->query($insert_query);

        
    } else {
        echo '';
    }
}

// Fetch the club users from the cache table
$club_users = $wpdb->get_col("SELECT user_email FROM $cache_table_name");


if (empty($club_users)) {
    echo '<p>No users found in your club: ' . esc_html($club_info->club_name) . '.</p>';
    return;
}


// Handle Complete Order action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'complete_order') {
    $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;

    if ($order_id) {
        $order = wc_get_order($order_id);

        if ($order && in_array($order->get_status(), ['on-hold', 'pending', 'processing'])) {
            // Update the order status to 'completed'
            $order->update_status('completed', __('Order marked as completed via dashboard.', 'woocommerce'));

            // Add a success message
            echo '<div class="notice notice-success is-dismissible"><p>Order #' . esc_html($order_id) . ' has been marked as completed.</p></div>';
        } else {
            // Add an error message if the order cannot be completed
            echo '<div class="notice notice-error is-dismissible"><p>Order #' . esc_html($order_id) . ' cannot be completed.</p></div>';
        }
    }
}

// Handle Bulk Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action']) && !empty($_POST['selected_orders'])) {
    $bulk_action = sanitize_text_field($_POST['bulk_action']);
    $selected_orders = array_map('absint', $_POST['selected_orders']);

    if ($bulk_action === 'delete') {
        foreach ($selected_orders as $order_id) {
            $order = wc_get_order($order_id);

            if ($order) {
                // Delete the order permanently
                wp_delete_post($order_id, true);
            }
        }
        echo '<div class="notice notice-success is-dismissible"><p>Selected orders have been deleted.</p></div>';
    } elseif ($bulk_action === 'export') {
        // Clear output buffer to avoid extra output in the CSV file
        while (ob_get_level()) {
            ob_end_clean();
        }
    
        // Prepare headers for CSV export
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment;filename=orders_detailed.csv');
        $output = fopen('php://output', 'w');
    
        // Add CSV headers
        $headers = [
            'Order Number', 'Customer Name', 'Order Date', 'Order Status', 'Order Total',
            'Billing Address', 'Shipping Address', 'Payment Method', 'Shipping Method',
            'Items Ordered', 'Customer Notes', 'Order Notes'
        ];
        fputcsv($output, $headers);
    
        foreach ($selected_orders as $order_id) {
            $order = wc_get_order($order_id);
            if ($order) {
                // Gather order details
                $order_number = $order->get_id();
                $customer_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
                $order_date = $order->get_date_created() ? $order->get_date_created()->date('d/m/Y') : '';
                $order_status = ucfirst($order->get_status());
    
                // 1) Find first product ID inside this order
                $product_id = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT meta_value 
                        FROM {$wpdb->prefix}woocommerce_order_itemmeta oim
                        INNER JOIN {$wpdb->prefix}woocommerce_order_items oi
                            ON oi.order_item_id = oim.order_item_id
                        WHERE oi.order_id = %d
                        AND oim.meta_key = '_product_id'
                        LIMIT 1",
                        $order_id
                    )
                );

                // 2) Find club currency from product
                $club_currency = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT c.club_currency
                        FROM {$wpdb->prefix}postmeta pm
                        LEFT JOIN {$wpdb->prefix}clubs c 
                            ON c.club_id = pm.meta_value
                        WHERE pm.post_id = %d
                        AND pm.meta_key = '_select_club_id'
                        LIMIT 1",
                        $product_id
                    )
                );

                // 3) Fallback if empty
                if (!$club_currency) {
                    $club_currency = 'R';
                }

                // 4) Final amount for CSV
                $order_total_raw = $order->get_total();
                $order_total = $club_currency . number_format((float)$order_total_raw, 2);
    
                // Billing and shipping addresses
                $billing_address = strip_tags($order->get_formatted_billing_address());
                $shipping_address = strip_tags($order->get_formatted_shipping_address());
    
                // Payment and shipping methods
                $payment_method = $order->get_payment_method_title();
                $shipping_method = implode(', ', array_map(fn($item) => $item->get_name(), $order->get_shipping_methods()));
    
                // Items ordered
                $items_ordered = [];
                foreach ($order->get_items() as $item) {
                    $product_name = $item->get_name();
                    $quantity = $item->get_quantity();
    
                    // Format the subtotal and total for each item
                    $subtotal_raw = $item->get_subtotal();
                    $subtotal = 'R' . intval($subtotal_raw);
    
                    $total_raw = $item->get_total();
                    $total = 'R' . intval($total_raw);
    
                    $items_ordered[] = "$product_name (x$quantity, Subtotal: $subtotal, Total: $total)";
                }
                $items_ordered = implode('; ', $items_ordered);
    
                // Customer notes and order notes
                $customer_note = $order->get_customer_note();
                $order_notes = implode('; ', array_map(fn($note) => $note->content, wc_get_order_notes(['order_id' => $order_id])));
    
                // Add row to CSV
                fputcsv($output, [
                    $order_number, $customer_name, $order_date, $order_status, $order_total,
                    $billing_address, $shipping_address, $payment_method, $shipping_method,
                    $items_ordered, $customer_note, $order_notes
                ]);
            }
        }
    
        fclose($output);
        exit;
    }
    
}

// Handle filters
$filter_month = isset($_GET['filter_month']) ? sanitize_text_field($_GET['filter_month']) : '';
$filter_status = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : '';
$search_query = isset($_GET['search_query']) ? sanitize_text_field($_GET['search_query']) : '';

// Pagination setup
$current_page = get_query_var('paged') ? absint(get_query_var('paged')) : 1;
$orders_per_page = 20;
$offset = ($current_page - 1) * $orders_per_page;

// Debugging log for current page (optional)
error_log("Current page: " . $current_page);

// Query WooCommerce orders for users in the same club
if (empty($club_users)) {
    echo '<p>No users found in your club.</p>';
    return;
}
// Prepare placeholders for product IDs (for IN clause)
$product_placeholders = implode(',', array_fill(0, count($product_ids_sanitized), '%d'));

$query_conditions = [];
$query_params = [];

// Filter by month
if (!empty($filter_month)) {
    $query_conditions[] = "MONTH(p.post_date) = %d";
    $query_params[] = intval($filter_month);
}

// Filter by status
if (!empty($filter_status)) {
    $query_conditions[] = "p.post_status = %s";
    $query_params[] = $filter_status;
}

// Search by name (billing first or last)
if (!empty($search_query)) {
    $query_conditions[] = "(meta_first_name.meta_value LIKE %s OR meta_last_name.meta_value LIKE %s)";
    $query_params[] = '%' . $search_query . '%';
    $query_params[] = '%' . $search_query . '%';
}

// Combine conditions string
$query_condition_string = !empty($query_conditions) ? 'AND ' . implode(' AND ', $query_conditions) : '';

// Add pagination params
$query_params[] = $orders_per_page;
$query_params[] = $offset;

// SQL to fetch orders with club products
$sql = "
SELECT DISTINCT p.ID as order_id,
       p.post_date as order_date,
       p.post_status as status,
       meta_total.meta_value as total,
       meta_first_name.meta_value as first_name,
       meta_last_name.meta_value as last_name
FROM {$wpdb->prefix}posts p
LEFT JOIN {$wpdb->prefix}postmeta meta_total ON p.ID = meta_total.post_id AND meta_total.meta_key = '_order_total'
LEFT JOIN {$wpdb->prefix}postmeta meta_first_name ON p.ID = meta_first_name.post_id AND meta_first_name.meta_key = '_billing_first_name'
LEFT JOIN {$wpdb->prefix}postmeta meta_last_name ON p.ID = meta_last_name.post_id AND meta_last_name.meta_key = '_billing_last_name'
INNER JOIN {$wpdb->prefix}woocommerce_order_items oi ON p.ID = oi.order_id
INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id
WHERE p.post_type = 'shop_order'
AND p.post_status != 'trash'
AND oim.meta_key = '_product_id'
AND oim.meta_value IN ($product_placeholders)
$query_condition_string
ORDER BY p.post_date DESC
LIMIT %d OFFSET %d
";

// Combine product IDs + filter params
$final_query_params = array_merge($product_ids_sanitized, $query_params);

$orders = $wpdb->get_results(
    $wpdb->prepare($sql, $final_query_params)
);


// Fetch order counts (no filters) — count orders containing club products
$count_sql = "
SELECT
    COUNT(DISTINCT p.ID) as all_orders,
    SUM(CASE WHEN p.post_status = 'wc-completed' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN p.post_status = 'wc-pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN p.post_status = 'wc-processing' THEN 1 ELSE 0 END) as processing,
    SUM(CASE WHEN p.post_status = 'wc-on-hold' THEN 1 ELSE 0 END) as on_hold,
    SUM(CASE WHEN p.post_status = 'wc-cancelled' THEN 1 ELSE 0 END) as cancelled
FROM {$wpdb->prefix}posts p
INNER JOIN {$wpdb->prefix}woocommerce_order_items oi ON p.ID = oi.order_id
INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id
WHERE p.post_type = 'shop_order'
AND oim.meta_key = '_product_id'
AND oim.meta_value IN ($product_placeholders)
";

$order_counts_query = $wpdb->get_row(
    $wpdb->prepare($count_sql, $product_ids_sanitized)
);

$order_counts = [
    'all'        => intval($order_counts_query->all_orders ?? 0),
    'completed'  => intval($order_counts_query->completed ?? 0),
    'pending'    => intval($order_counts_query->pending ?? 0),
    'processing' => intval($order_counts_query->processing ?? 0),
    'on_hold'    => intval($order_counts_query->on_hold ?? 0),
    'cancelled'  => intval($order_counts_query->cancelled ?? 0),
];

// Count total orders for pagination (with filters)
$count_filtered_sql = "
SELECT COUNT(DISTINCT p.ID)
FROM {$wpdb->prefix}posts p
INNER JOIN {$wpdb->prefix}woocommerce_order_items oi ON p.ID = oi.order_id
INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id
LEFT JOIN {$wpdb->prefix}postmeta meta_first_name ON p.ID = meta_first_name.post_id AND meta_first_name.meta_key = '_billing_first_name'
LEFT JOIN {$wpdb->prefix}postmeta meta_last_name ON p.ID = meta_last_name.post_id AND meta_last_name.meta_key = '_billing_last_name'
WHERE p.post_type = 'shop_order'
AND oim.meta_key = '_product_id'
AND oim.meta_value IN ($product_placeholders)
$query_condition_string
";

$total_orders = $wpdb->get_var(
    $wpdb->prepare($count_filtered_sql, array_merge($product_ids_sanitized, $query_params))
);

$total_pages = ceil($total_orders / $orders_per_page);




?>

<div class="order-list">
    <div class="admin-switch">
    <h2 class="manager-h2">Orders</h2>
<a href="<?php echo esc_url(admin_url('edit.php?post_type=shop_order')); ?>" class="button All-button" style="text-decoration: none;  border: none;">
Advanced
</a>
    </div>


<!-- Display the count filters -->
<div class="status-filters count">
    <a href="<?php echo esc_url(remove_query_arg('filter_status')); ?>" class="<?php echo empty($filter_status) ? 'current' : ''; ?>">
        All (<?php echo intval($order_counts['all']); ?>)
    </a> |
    <a href="<?php echo esc_url(add_query_arg('filter_status', 'wc-completed')); ?>" class="<?php echo $filter_status === 'wc-completed' ? 'current' : ''; ?>">
        Completed (<?php echo intval($order_counts['completed']); ?>)
    </a> |
    <a href="<?php echo esc_url(add_query_arg('filter_status', 'wc-pending')); ?>" class="<?php echo $filter_status === 'wc-pending' ? 'current' : ''; ?>">
        Pending (<?php echo intval($order_counts['pending']); ?>)
    </a> |
    <a href="<?php echo esc_url(add_query_arg('filter_status', 'wc-processing')); ?>" class="<?php echo $filter_status === 'wc-processing' ? 'current' : ''; ?>">
        Processing (<?php echo intval($order_counts['processing']); ?>)
    </a> |
    <a href="<?php echo esc_url(add_query_arg('filter_status', 'wc-on-hold')); ?>" class="<?php echo $filter_status === 'wc-on-hold' ? 'current' : ''; ?>">
        On Hold (<?php echo intval($order_counts['on_hold']); ?>)
    </a> |
    <a href="<?php echo esc_url(add_query_arg('filter_status', 'wc-cancelled')); ?>" class="<?php echo $filter_status === 'wc-cancelled' ? 'current' : ''; ?>">
        Cancelled (<?php echo intval($order_counts['cancelled']); ?>)
    </a>
</div>

<!-- Filters -->
<form method="get" class="filters end-filters">
    <input type="hidden" name="section" value="<?php echo esc_attr($_GET['section'] ?? 'orders'); ?>">

    <!-- Month Filter -->
    <select id="filter_month" name="filter_month">
    <option value="" <?php selected($filter_month, ''); ?>>All Dates</option>
    <option value="01" <?php selected($filter_month, '01'); ?>>January</option>
    <option value="02" <?php selected($filter_month, '02'); ?>>February</option>
    <option value="03" <?php selected($filter_month, '03'); ?>>March</option>
    <option value="04" <?php selected($filter_month, '04'); ?>>April</option>
    <option value="05" <?php selected($filter_month, '05'); ?>>May</option>
    <option value="06" <?php selected($filter_month, '06'); ?>>June</option>
    <option value="07" <?php selected($filter_month, '07'); ?>>July</option>
    <option value="08" <?php selected($filter_month, '08'); ?>>August</option>
    <option value="09" <?php selected($filter_month, '09'); ?>>September</option>
    <option value="10" <?php selected($filter_month, '10'); ?>>October</option>
    <option value="11" <?php selected($filter_month, '11'); ?>>November</option>
    <option value="12" <?php selected($filter_month, '12'); ?>>December</option>
</select>


    <!-- Status Dropdown -->
    <select id="filter_status" name="filter_status">
        <option value="">Status</option>
        <option value="wc-completed" <?php selected($filter_status, 'wc-completed'); ?>>Completed</option>
        <option value="wc-pending" <?php selected($filter_status, 'wc-pending'); ?>>Pending</option>
        <option value="wc-processing" <?php selected($filter_status, 'wc-processing'); ?>>Processing</option>
        <option value="wc-cancelled" <?php selected($filter_status, 'wc-cancelled'); ?>>Cancelled</option>
    </select>

    <!-- Search Field -->
    <div class="input-icon">
        <input type="text" id="search_query" name="search_query" value="<?php echo esc_attr($search_query); ?>" placeholder="Search by name...">
        <span class="icon"><i class="fa fa-search"></i></span> <!-- Font Awesome Icon -->
    </div>

    <!-- Apply Filters -->
    <button type="submit" class="my-filters All-button">Apply Filters</button>

    <!-- Clear Filters -->
    <a href="<?php echo esc_url(remove_query_arg(['filter_month', 'filter_status', 'search_query', 'paged'])); ?>&section=<?php echo esc_attr($_GET['section'] ?? 'orders'); ?>" class="button clear-filter">Clear Filters</a>
</form>

<!-- Bulk Actions -->
<form method="post" class="bulk-actions">
    <div class="bulk-users end-filters">
        <select name="bulk_action">
            <option value="">Bulk Actions</option>
            <option value="export">Export as CSV</option>
        </select>
        <button type="submit" class="my-filters All-button">Apply</button>
    </div>

    <!-- Orders Table -->
    <table class="wp-list-table widefat fixed striped order-table managertable" id="orders-table">
        <thead>
            <tr>
                <th><input type="checkbox" id="select-all"></th>
                <th>Order Number</th>
                <th>Name</th>
                <th>Date</th>
                <th>Status</th>
                <th>Total</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($orders)) : ?>
                <?php foreach ($orders as $order) : ?>
                    <?php
                    $order_number = $order->order_id;
                    // Combine billing names
                    $customer_name = trim($order->first_name . ' ' . $order->last_name);

                    // Fallback to display name if billing name is empty
                    if (empty($customer_name)) {
                        $user_id = get_post_meta($order_number, '_customer_user', true);

                        if ($user_id) {
                            $user_info = get_userdata($user_id);
                            if ($user_info && !empty($user_info->display_name)) {
                                $customer_name = $user_info->display_name;
                            }
                        }
                    }

                    // Final fallback
                    if (empty($customer_name)) {
                        $customer_name = 'Unknown';
                    }

                    $order_date = date('d/m/Y', strtotime($order->order_date));
                    $order_status_raw = $order->status; // Raw status for logic
                    $order_status = ucwords(str_replace('wc-', '', $order_status_raw)); // Cleaned status for display
                    // Fetch first product ID inside this order
                    $product_id = $wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT meta_value 
                            FROM {$wpdb->prefix}woocommerce_order_itemmeta oim
                            INNER JOIN {$wpdb->prefix}woocommerce_order_items oi
                                ON oi.order_item_id = oim.order_item_id
                            WHERE oi.order_id = %d
                            AND meta_key = '_product_id'
                            LIMIT 1",
                            $order_number
                        )
                    );

                    // Fetch club currency for that product
                    $club_currency = $wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT c.club_currency
                            FROM {$wpdb->prefix}postmeta pm
                            LEFT JOIN {$wpdb->prefix}clubs c ON c.club_id = pm.meta_value
                            WHERE pm.post_id = %d
                            AND pm.meta_key = '_select_club_id'
                            LIMIT 1",
                            $product_id
                        )
                    );

                    if (!$club_currency) {
                        $club_currency = 'R'; // fallback
                    }
                    $order_total = $club_currency . number_format((float)$order->total, 2);

                    // Badge styles for different order statuses
                    $badge_styles = [
                        'wc-pending'    => 'background: #fff3cd; color: #856404; border: 1px solid #ffeeba;',
                        'wc-on-hold'    => 'background: #f8f9fa; color: #6c757d; border: 1px solid #dee2e6;',
                        'wc-processing' => 'background: #cce5ff; color: #004085; border: 1px solid #b8daff;',
                        'wc-completed'  => 'background: #d4edda; color: #155724; border: 1px solid #c3e6cb;',
                        'wc-cancelled'  => 'background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;',
                        'wc-failed'     => 'background: #f5c6cb; color: #721c24; border: 1px solid #f5c6cb;',
                        'wc-refunded'   => 'background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb;',
                    ];

                    // Assign a badge style based on the status
                    $badge_style = $badge_styles[$order_status_raw] ?? 'background: #e2e3e5; color: #818182; border: 1px solid #d6d8db;';
                    ?>
                    <tr>
                        <td><input type="checkbox" name="selected_orders[]" value="<?php echo esc_attr($order_number); ?>"></td>
                        <td data-label="Order Number">
                        <a href="<?php echo esc_url(admin_url('post.php?post=' . $order_number . '&action=edit')); ?>" target="_blank">
                            #<?php echo esc_html($order_number); ?>
                        </a>
                    </td>

                        <td data-label="Name"><?php echo $customer_name; ?></td>
                        <td data-label="Date"><?php echo esc_html($order_date); ?></td>
                        <td data-label="Status">
                            <!-- Display the order status with a badge -->
                            <span class="badge" style="display: inline-block; padding: 5px 10px; border-radius: 3px; font-size: 12px; <?php echo esc_attr($badge_style); ?>">
                                <?php echo esc_html($order_status); ?>
                            </span>
                        </td>
                        <td data-label="Total"><?php echo esc_html(strip_tags($order_total)); ?></td>
                        <td data-label="Action">
                            <!-- Show the complete button for specific statuses -->
                            <?php if (in_array($order_status_raw, ['wc-on-hold', 'wc-pending', 'wc-processing'])) : ?>
                                <form method="post">
                                    <input type="hidden" name="action" value="complete_order">
                                    <input type="hidden" name="order_id" value="<?php echo esc_attr($order_number); ?>">
                                    <button type="submit" class="button complete-order-button action-item">Complete</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr>
                    <td colspan="7">No orders found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</form>
<!-- Pagination -->
<div class="pagination" style="text-align: center; display: flex; flex-wrap: wrap; justify-content: center; gap: 5px;">
    <?php if ($current_page > 1): ?>
        <!-- Previous Button -->
        <a href="<?php echo esc_url(add_query_arg([
            'paged' => $current_page - 1, 
            'filter_month' => $filter_month, 
            'filter_status' => $filter_status, 
            'search_query' => $search_query
        ])); ?>" 
        class="prev" 
        style="padding: 5px 10px; border: 1px solid #ddd; text-decoration: none; color: #333;">
            Previous
        </a>
    <?php endif; ?>

    <?php
    // Display a limited number of page numbers with ellipses
    $max_pages_to_show = 5; // Adjust this to control the range of visible page numbers
    $start_page = max(1, $current_page - floor($max_pages_to_show / 2));
    $end_page = min($total_pages, $start_page + $max_pages_to_show - 1);

    if ($start_page > 1): ?>
        <!-- First Page Link -->
        <a href="<?php echo esc_url(add_query_arg([
            'paged' => 1, 
            'filter_month' => $filter_month, 
            'filter_status' => $filter_status, 
            'search_query' => $search_query
        ])); ?>" 
        style="padding: 5px 10px; border: 1px solid #ddd; text-decoration: none; color: #333;">
            1
        </a>
        <?php if ($start_page > 2): ?>
            <!-- Ellipsis -->
            <span style="padding: 5px;">...</span>
        <?php endif; ?>
    <?php endif; ?>

    <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
        <!-- Page Numbers -->
        <a href="<?php echo esc_url(add_query_arg([
            'paged' => $i, 
            'filter_month' => $filter_month, 
            'filter_status' => $filter_status, 
            'search_query' => $search_query
        ])); ?>" 
        class="<?php echo ((int)$current_page === (int)$i) ? 'current' : ''; ?>" 
        style="padding: 5px 10px; border: 1px solid #ddd; text-decoration: none; <?php echo ((int)$current_page === (int)$i) ? 'background: #10487B; color: #fff;' : 'color: #333;'; ?>">
            <?php echo $i; ?>
        </a>
    <?php endfor; ?>

    <?php if ($end_page < $total_pages): ?>
        <?php if ($end_page < $total_pages - 1): ?>
            <!-- Ellipsis -->
            <span style="padding: 5px;">...</span>
        <?php endif; ?>
        <!-- Last Page Link -->
        <a href="<?php echo esc_url(add_query_arg([
            'paged' => $total_pages, 
            'filter_month' => $filter_month, 
            'filter_status' => $filter_status, 
            'search_query' => $search_query
        ])); ?>" 
        style="padding: 5px 10px; border: 1px solid #ddd; text-decoration: none; color: #333;">
            <?php echo $total_pages; ?>
        </a>
    <?php endif; ?>

    <?php if ($current_page < $total_pages): ?>
        <!-- Next Button -->
        <a href="<?php echo esc_url(add_query_arg([
            'paged' => $current_page + 1, 
            'filter_month' => $filter_month, 
            'filter_status' => $filter_status, 
            'search_query' => $search_query
        ])); ?>" 
        class="next" 
        style="padding: 5px 10px; border: 1px solid #ddd; text-decoration: none; color: #333;">
            Next
        </a>
    <?php endif; ?>
</div>


<script>
    // Select/Deselect All Checkboxes
    document.getElementById('select-all').addEventListener('change', function () {
        const checkboxes = document.querySelectorAll('input[name="selected_orders[]"]');
        checkboxes.forEach(checkbox => checkbox.checked = this.checked);
    });
</script>


<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.innerWidth <= 1334) {
        document.querySelectorAll('#orders-table tr').forEach(function(row) {
            const actionCell = row.querySelector('td[data-label="Action"]');
            if (actionCell && actionCell.innerText.trim() === '') {
                actionCell.style.display = 'none';
            }
        });
    }
});
</script>
