<?php
if (!defined('ABSPATH')) exit;

global $wpdb;
$current_user = wp_get_current_user();

// 1. Identify Club
$club_id = null;
$club_name = null;

if (isset($_GET['club'], $_GET['2ndclubid']) && !empty($_GET['club']) && !empty($_GET['2ndclubid'])) {
    $club_name = urldecode(sanitize_text_field($_GET['club']));
    $club_id = intval($_GET['2ndclubid']);
    $club_info = $wpdb->get_row($wpdb->prepare("SELECT club_id, club_name FROM {$wpdb->prefix}clubs WHERE club_name = %s AND club_id = %d", $club_name, $club_id));
    if (!$club_info) return;
} else {
    $club_info = $wpdb->get_row($wpdb->prepare("SELECT cm.club_id, cm.club_name FROM {$wpdb->prefix}club_members cm WHERE cm.user_email = %s LIMIT 1", $current_user->user_email));
    if (!$club_info) return;
    $club_id = $club_info->club_id;
    $club_name = $club_info->club_name;
}

// 2. Fetch Club Product IDs
$product_ids_sanitized = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT post_id FROM {$wpdb->prefix}postmeta WHERE meta_key = '_select_club_id' AND meta_value = %d", $club_id));
$product_ids_sanitized = array_map('intval', $product_ids_sanitized);
if (empty($product_ids_sanitized)) return;

// 3. Handle POST Actions (Complete / Bulk)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'complete_order') {
        $order_id = absint($_POST['order_id'] ?? 0);
        $order = wc_get_order($order_id);
        if ($order && in_array($order->get_status(), ['on-hold', 'pending', 'processing'])) {
            $order->update_status('completed', __('Order marked as completed via dashboard.', 'woocommerce'));
            echo '<div class="notice notice-success is-dismissible"><p>Order #' . $order_id . ' completed.</p></div>';
        }
    }

    if (isset($_POST['bulk_action']) && !empty($_POST['selected_orders'])) {
        $bulk_action = sanitize_text_field($_POST['bulk_action']);
        $selected_orders = array_map('absint', $_POST['selected_orders']);
        if ($bulk_action === 'delete') {
            foreach ($selected_orders as $o_id) wp_delete_post($o_id, true);
            echo '<div class="notice notice-success is-dismissible"><p>Orders deleted.</p></div>';
        } elseif ($bulk_action === 'export') {
            include_once plugin_dir_path(__FILE__) . 'export-handler.php';
            bmw_manager_export_orders($selected_orders);
            exit;
        }
    }
}

// 4. Setup Filters & Pagination
$filter_month = sanitize_text_field($_GET['filter_month'] ?? '');
$filter_status = sanitize_text_field($_GET['filter_status'] ?? '');
$search_query = sanitize_text_field($_GET['search_query'] ?? '');
$current_page = max(1, get_query_var('paged') ? absint(get_query_var('paged')) : 1);
$orders_per_page = 20;
$offset = ($current_page - 1) * $orders_per_page;

$product_placeholders = implode(',', array_fill(0, count($product_ids_sanitized), '%d'));
$query_conditions = [];
$query_params = $product_ids_sanitized;

if ($filter_month) { $query_conditions[] = "MONTH(p.post_date) = %d"; $query_params[] = (int)$filter_month; }
if ($filter_status) { $query_conditions[] = "p.post_status = %s"; $query_params[] = $filter_status; }
if ($search_query) {
    $query_conditions[] = "(meta_f.meta_value LIKE %s OR meta_l.meta_value LIKE %s)";
    $query_params[] = '%' . $search_query . '%'; $query_params[] = '%' . $search_query . '%';
}
$condition_sql = !empty($query_conditions) ? 'AND ' . implode(' AND ', $query_conditions) : '';

// 5. Main Optimized Query
$sql = "
SELECT DISTINCT p.ID as order_id, p.post_date, p.post_status as status, 
       meta_t.meta_value as total, meta_f.meta_value as first_name, meta_l.meta_value as last_name,
       u.display_name as fallback_name, c.club_currency
FROM {$wpdb->prefix}posts p
LEFT JOIN {$wpdb->prefix}postmeta meta_t ON p.ID = meta_t.post_id AND meta_t.meta_key = '_order_total'
LEFT JOIN {$wpdb->prefix}postmeta meta_f ON p.ID = meta_f.post_id AND meta_f.meta_key = '_billing_first_name'
LEFT JOIN {$wpdb->prefix}postmeta meta_l ON p.ID = meta_l.post_id AND meta_l.meta_key = '_billing_last_name'
LEFT JOIN {$wpdb->prefix}postmeta pm_cust ON p.ID = pm_cust.post_id AND pm_cust.meta_key = '_customer_user'
LEFT JOIN {$wpdb->prefix}users u ON pm_cust.meta_value = u.ID
INNER JOIN {$wpdb->prefix}woocommerce_order_items oi ON p.ID = oi.order_id
INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id
LEFT JOIN {$wpdb->prefix}postmeta pm_prod ON oim.meta_value = pm_prod.post_id AND pm_prod.meta_key = '_select_club_id'
LEFT JOIN {$wpdb->prefix}clubs c ON pm_prod.meta_value = c.club_id
WHERE p.post_type = 'shop_order' AND p.post_status != 'trash'
AND oim.meta_key = '_product_id' AND oim.meta_value IN ($product_placeholders)
$condition_sql ORDER BY p.post_date DESC LIMIT %d OFFSET %d";

$final_params = array_merge($query_params, [$orders_per_page, $offset]);
$orders = $wpdb->get_results($wpdb->prepare($sql, $final_params));

// 6. Counts (Unified)
$count_base = "FROM {$wpdb->prefix}posts p 
               INNER JOIN {$wpdb->prefix}woocommerce_order_items oi ON p.ID = oi.order_id 
               INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id 
               WHERE p.post_type = 'shop_order' AND oim.meta_key = '_product_id' AND oim.meta_value IN ($product_placeholders)";

$order_counts_raw = $wpdb->get_row($wpdb->prepare("SELECT COUNT(DISTINCT p.ID) as all_o,
    SUM(CASE WHEN p.post_status = 'wc-completed' THEN 1 ELSE 0 END) as comp,
    SUM(CASE WHEN p.post_status = 'wc-pending' THEN 1 ELSE 0 END) as pend,
    SUM(CASE WHEN p.post_status = 'wc-processing' THEN 1 ELSE 0 END) as proc,
    SUM(CASE WHEN p.post_status = 'wc-on-hold' THEN 1 ELSE 0 END) as hold,
    SUM(CASE WHEN p.post_status = 'wc-cancelled' THEN 1 ELSE 0 END) as canc $count_base", $product_ids_sanitized));

$filter_count_sql = "SELECT COUNT(DISTINCT p.ID) $count_base $condition_sql";
$total_orders = $wpdb->get_var($wpdb->prepare($filter_count_sql, $query_params));
$total_pages = ceil($total_orders / $orders_per_page);
