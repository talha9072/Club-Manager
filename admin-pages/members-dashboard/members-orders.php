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

function get_user_orders($user_id, $filters = [], $pagination = []) {
    global $wpdb;

    $query_conditions = [];
    $query_params = [$user_id];

    // Check for product_id in URL
    $product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : null;

    // Pehle product ka club_id nikaalo agar product_id hai
    $club_id = null;
    if ($product_id) {
        $club_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT meta_value FROM {$wpdb->prefix}postmeta WHERE post_id = %d AND meta_key = '_select_club_id' LIMIT 1",
                $product_id
            )
        );
    }

    // Filter by month
    if (!empty($filters['month'])) {
        $query_conditions[] = "DATE_FORMAT(p.post_date, '%Y-%m') = %s";
        $query_params[] = $filters['month'];
    }

    // Filter by status
    if (!empty($filters['status'])) {
        $query_conditions[] = "p.post_status = %s";
        $query_params[] = $filters['status'];
    }

    // Search by name or order number
    if (!empty($filters['search'])) {
        $search_query = str_replace('#', '', $filters['search']);
        $query_conditions[] = "(p.ID LIKE %s OR meta_first_name.meta_value LIKE %s OR meta_last_name.meta_value LIKE %s)";
        $query_params[] = '%' . $search_query . '%';
        $query_params[] = '%' . $filters['search'] . '%';
        $query_params[] = '%' . $filters['search'] . '%';
    }

    // Product filter replaced by club filter if club_id exists
    $product_join = '';
    if ($club_id) {
        $product_join = "
            INNER JOIN {$wpdb->prefix}woocommerce_order_items oi ON p.ID = oi.order_id
            INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim_product ON oi.order_item_id = oim_product.order_item_id AND oim_product.meta_key = '_product_id'
            INNER JOIN {$wpdb->prefix}postmeta product_club ON product_club.post_id = oim_product.meta_value AND product_club.meta_key = '_select_club_id'
        ";
        $query_conditions[] = "product_club.meta_value = %s";
        $query_params[] = $club_id;
    }

    // Combine conditions
    $query_condition_string = !empty($query_conditions) ? 'AND ' . implode(' AND ', $query_conditions) : '';

    // Pagination
    $limit = $pagination['limit'] ?? 20;
    $offset = $pagination['offset'] ?? 0;

    $query_params[] = $limit;
    $query_params[] = $offset;

    // Main orders query
    $orders = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT DISTINCT p.ID as order_id, 
                    p.post_date as order_date, 
                    p.post_status as status, 
                    meta_total.meta_value as total,
                    meta_first_name.meta_value as first_name,
                    meta_last_name.meta_value as last_name
             FROM {$wpdb->prefix}posts p
             LEFT JOIN {$wpdb->prefix}postmeta meta_total ON p.ID = meta_total.post_id AND meta_total.meta_key = '_order_total'
             LEFT JOIN {$wpdb->prefix}postmeta meta_first_name ON p.ID = meta_first_name.post_id AND meta_first_name.meta_key = '_billing_first_name'
             LEFT JOIN {$wpdb->prefix}postmeta meta_last_name ON p.ID = meta_last_name.post_id AND meta_last_name.meta_key = '_billing_last_name'
             LEFT JOIN {$wpdb->prefix}postmeta meta_user_id ON p.ID = meta_user_id.post_id AND meta_user_id.meta_key = '_customer_user'
             $product_join
             WHERE p.post_type = 'shop_order'
             AND meta_user_id.meta_value = %d
             $query_condition_string
             ORDER BY p.post_date DESC
             LIMIT %d OFFSET %d",
            $query_params
        )
    );

    // Count query for total orders
    $total_orders = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(DISTINCT p.ID)
             FROM {$wpdb->prefix}posts p
             LEFT JOIN {$wpdb->prefix}postmeta meta_user_id ON p.ID = meta_user_id.post_id AND meta_user_id.meta_key = '_customer_user'
             $product_join
             WHERE p.post_type = 'shop_order'
             AND meta_user_id.meta_value = %d
             $query_condition_string",
            array_slice($query_params, 0, count($query_params) - 2)
        )
    );

    return ['orders' => $orders, 'total' => $total_orders];
}



// Filters and pagination setup
$filter_month = isset($_GET['filter_month']) ? sanitize_text_field($_GET['filter_month']) : '';
$filter_status = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : '';
$search_query = isset($_GET['search_query']) ? sanitize_text_field($_GET['search_query']) : '';

global $wp_query;
$paged = isset($wp_query->query_vars['paged']) ? intval($wp_query->query_vars['paged']) : 1;
$current_page = $paged > 0 ? $paged : 1;

$orders_per_page = 20;
$offset = ($current_page - 1) * $orders_per_page;

// Fetch orders
$user_orders = get_user_orders(
    $current_user->ID,
    ['month' => $filter_month, 'status' => $filter_status, 'search' => $search_query],
    ['limit' => $orders_per_page, 'offset' => $offset]
);

$orders = $user_orders['orders'];
$total_pages = ceil($user_orders['total'] / $orders_per_page);

?>

<?php
function render_user_orders($user_orders, $orders, $filter_status, $filter_month, $search_query, $current_page, $total_pages) {
    // Count Filters
    $total_orders = $user_orders['total'];
    $completed_orders = count(array_filter($orders, fn($order) => $order->status === 'wc-completed'));
    $pending_orders = count(array_filter($orders, fn($order) => $order->status === 'wc-pending'));
    $processing_orders = count(array_filter($orders, fn($order) => $order->status === 'wc-processing'));
    $cancelled_orders = count(array_filter($orders, fn($order) => $order->status === 'wc-cancelled'));

    // Render Count Filters
    $base_url = remove_query_arg(['filter_status', 'paged'], $_SERVER['REQUEST_URI']);
    ob_start(); // Start output buffering
    ?>
    <div class="search-heading-con">
    <h2 class="manager-h2">Orders</h2>
    <form method="get" id="order-search-form" class="order-search-form end-filters">
    <div class="input-icon">
    <input type="text" id="search_order_number"placeholder="Search" name="search_query" value="<?php echo esc_attr($search_query); ?>">
        <span class="icon"><i class="fa fa-search"></i></span> <!-- Font Awesome Icon -->
    </div>
    

   

    <!-- Retain section=orders and other parameters -->
    <input type="hidden" name="section" value="orders">
    <input type="hidden" name="subscription_id" value="<?php echo esc_attr($_GET['subscription_id'] ?? ''); ?>">
    <input type="hidden" name="product_id" value="<?php echo esc_attr($_GET['product_id'] ?? ''); ?>">
    <input type="hidden" name="club" value="<?php echo esc_attr($_GET['club'] ?? ''); ?>">

    <button type="submit" class="button padding-button All-button">Search</button>
</form>
    </div>
    <div class="status-filters count">
        <a href="<?php echo esc_url($base_url); ?>" class="<?php echo empty($filter_status) ? 'current' : ''; ?>">All (<?php echo $total_orders; ?>)</a> |
        <a href="<?php echo esc_url(add_query_arg('filter_status', 'wc-completed', $base_url)); ?>" class="<?php echo $filter_status === 'wc-completed' ? 'current' : ''; ?>">Completed (<?php echo $completed_orders; ?>)</a> |
        <a href="<?php echo esc_url(add_query_arg('filter_status', 'wc-pending', $base_url)); ?>" class="<?php echo $filter_status === 'wc-pending' ? 'current' : ''; ?>">Pending (<?php echo $pending_orders; ?>)</a> |
        <a href="<?php echo esc_url(add_query_arg('filter_status', 'wc-processing', $base_url)); ?>" class="<?php echo $filter_status === 'wc-processing' ? 'current' : ''; ?>">Processing (<?php echo $processing_orders; ?>)</a> |
        <a href="<?php echo esc_url(add_query_arg('filter_status', 'wc-cancelled', $base_url)); ?>" class="<?php echo $filter_status === 'wc-cancelled' ? 'current' : ''; ?>">Cancelled (<?php echo $cancelled_orders; ?>)</a>
    </div>

    <div class="order-list">
        
        <!-- Filters -->
        <form method="get" class="filters end-filters" id="filter-form">
    <!-- Month filter -->
    <input type="month" id="filter_month" name="filter_month" value="<?php echo esc_attr($filter_month); ?>">
    
    <!-- Keep 'section' parameter intact and other filters -->
    <input type="hidden" name="section" value="orders">
    <input type="hidden" name="subscription_id" value="<?php echo esc_attr($_GET['subscription_id'] ?? ''); ?>">
    <input type="hidden" name="product_id" value="<?php echo esc_attr($_GET['product_id'] ?? ''); ?>">
    <input type="hidden" name="club" value="<?php echo esc_attr($_GET['club'] ?? ''); ?>">

    <button type="submit" class="my-filters All-button">Apply Filters</button>

    <!-- Clear Filters Button -->
    <a href="<?php echo esc_url(remove_query_arg(['filter_month', 'filter_status', 'search_query', 'paged'])); ?>" class="button padding-a">Clear Filters</a>
    </form>


        <!-- Orders Table -->
        <form method="post" class="bulk-actions">
            <table class="wp-list-table widefat fixed striped managertable" id="members-orders-table">
                <thead>
                    <tr>
                        <th>Order Number</th>
                        <th>Name</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
        <?php if (!empty($orders)) : ?>
            <?php foreach ($orders as $order) : ?>
                <?php
                $order_number = $order->order_id;
                $customer_name = $order->first_name . ' ' . $order->last_name;
                $order_date = date('d/m/Y', strtotime($order->order_date));
                $order_status = ucwords(str_replace('wc-', '', $order->status));
                $order_total = wc_price($order->total);
                ?>
                <tr>
                    
                    <td data-label="Order Number">
                        <a href="<?php echo esc_url(add_query_arg('order_id', $order_number, $base_url)); ?>">
                            #<?php echo esc_html($order_number); ?>
                        </a>
                    </td>

                    <td data-label="Name"><?php echo esc_html($customer_name); ?></td>
                    <td data-label="Date"><?php echo esc_html($order_date); ?></td>
                    <?php 
                    $badge_styles = [
                        'wc-pending'    => 'background: #fff3cd; color: #856404; border: 1px solid #ffeeba; padding: 5px 10px; border-radius: 4px; display: inline-block;',
                        'wc-on-hold'    => 'background: #f8f9fa; color: #6c757d; border: 1px solid #dee2e6; padding: 5px 10px; border-radius: 4px; display: inline-block;',
                        'wc-processing' => 'background: #cce5ff; color: #004085; border: 1px solid #b8daff; padding: 5px 10px; border-radius: 4px; display: inline-block;',
                        'wc-completed'  => 'background: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: 5px 10px; border-radius: 4px; display: inline-block;',
                        'wc-cancelled'  => 'background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 5px 10px; border-radius: 4px; display: inline-block;',
                        'wc-failed'     => 'background: #f5c6cb; color: #721c24; border: 1px solid #f5c6cb; padding: 5px 10px; border-radius: 4px; display: inline-block;',
                        'wc-refunded'   => 'background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; padding: 5px 10px; border-radius: 4px; display: inline-block;',
                    ];

                    $status_key = strtolower(str_replace(' ', '-', $order->status)); // Ensure matching key format
                    $badge_style = isset($badge_styles[$status_key]) ? $badge_styles[$status_key] : '';
                    ?>

                    <td data-label="Status"><span class="badge" style="<?php echo esc_attr($badge_style); ?>"><?php echo esc_html($order_status); ?></span></td>

                    <td data-label="Total"><?php echo esc_html(strip_tags($order_total)); ?></td>
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

        <div class="pagination">
        <?php if ($total_pages >= 1): ?>
            <?php if ($current_page > 1): ?>
                <a href="<?php echo esc_url(add_query_arg('paged', $current_page - 1)); ?>" class="prev">Previous</a>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="<?php echo esc_url(add_query_arg('paged', $i)); ?>" class="<?php echo ($i === $current_page) ? 'current' : ''; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>

            <?php if ($current_page < $total_pages): ?>
                <a href="<?php echo esc_url(add_query_arg('paged', $current_page + 1)); ?>" class="next">Next</a>
            <?php endif; ?>
        <?php endif; ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const selectAllCheckbox = document.getElementById('select-all');
            const orderCheckboxes = document.querySelectorAll('input[name="selected_orders[]"]');

            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', function () {
                    orderCheckboxes.forEach(checkbox => checkbox.checked = this.checked);
                });
            }
        });
    </script>

    <?php
    return ob_get_clean(); // Return the output buffer content
}





// Function to fetch Billing & Shipping details
function get_billing_shipping_details($order_id) {
    global $wpdb;
    
    $results = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT meta_key, meta_value FROM {$wpdb->prefix}postmeta 
             WHERE post_id = %d 
             AND (meta_key LIKE '_billing_%' OR meta_key LIKE '_shipping_%')",
            $order_id
        ),
        OBJECT_K
    );

    $data = [];
    foreach ($results as $key => $meta) {
        $clean_key = ltrim($key, '_'); // Remove only the first underscore
        $data[$clean_key] = $meta->meta_value;
    }
    
    return $data;
}

// Function to display a single order's details
function render_order_overview($order_id) {
    global $wpdb;

    // Fetch order details
    $order = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT p.ID as order_id, 
                    p.post_date as order_date, 
                    p.post_status as status, 
                    meta_total.meta_value as total,
                    meta_payment.meta_value as payment_method
             FROM {$wpdb->prefix}posts p
             LEFT JOIN {$wpdb->prefix}postmeta meta_total ON p.ID = meta_total.post_id AND meta_total.meta_key = '_order_total'
             LEFT JOIN {$wpdb->prefix}postmeta meta_payment ON p.ID = meta_payment.post_id AND meta_payment.meta_key = '_payment_method_title'
             WHERE p.ID = %d",
            $order_id
        )
    );

    if (!$order) {
        echo '<p>Order not found.</p>';
        return;
    }
if ($order->status === 'wc-pending') {
    // Get order key for URL
    $order_key = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT meta_value FROM {$wpdb->prefix}postmeta WHERE post_id = %d AND meta_key = '_order_key' LIMIT 1",
            $order_id
        )
    );

    $payment_url = esc_url( home_url( "/checkout/order-pay/{$order_id}/?pay_for_order=true&key={$order_key}" ) );

    echo '<div class="notice notice-warning" style="
        padding: 12px; 
        margin-bottom: 20px; 
        border: 1px solid #f5c6cb; 
        background-color: #f8d7da; 
        color: #721c24; 
        border-radius: 4px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
    ">
    <i class="fas fa-exclamation-triangle" style="font-size:18px;"></i>
    Payment is pending. Please complete your payment on the <a href="' . $payment_url . '" target="_blank" style="font-weight:700; text-decoration:underline; color: #721c24;">customer payment page</a>.
    </div>';
}
    // Fetch order items
    $items = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT oi.order_item_name AS product_name,
                    om_qty.meta_value AS quantity,
                    om_price.meta_value AS product_price
             FROM {$wpdb->prefix}woocommerce_order_items oi
             LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta om_qty ON oi.order_item_id = om_qty.order_item_id AND om_qty.meta_key = '_qty'
             LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta om_price ON oi.order_item_id = om_price.order_item_id AND om_price.meta_key = '_line_total'
             WHERE oi.order_id = %d",
            $order_id
        )
    );

    // Fetch Billing & Shipping Details
    $billing_shipping = get_billing_shipping_details($order_id);
    ?>

    <div>
        <h2>Order Overview</h2>
        <ul class="row-list">
            <li class="label-order"><label class="user-label">Order Number:</label> #<?php echo esc_html($order->order_id); ?></li>
            <li class="label-order"><label class="user-label">Status:</label> <?php echo esc_html(ucwords(str_replace('wc-', '', $order->status))); ?></li>
            <li class="label-order"><label class="user-label">Payment Method:</label> <?php echo esc_html($order->payment_method); ?></li>
            <li class="label-order"><label class="user-label">Paid On:</label> <?php echo esc_html(date('jS F Y @ g:i A', strtotime($order->order_date))); ?></li>
            <li class="label-order"><label class="user-label">Total Paid:</label> <?php echo wc_price($order->total); ?></li>
        </ul>

        <h3>Items Ordered</h3>
        <ul class="row-list">
            <?php foreach ($items as $item) : ?>
                <li class="row-li">
                    <div class="address"><label class="user-label">Item Name:</label> <?php echo esc_html($item->product_name); ?></div>
                    <div class="address"><label class="user-label">Quantity:</label> <?php echo esc_html($item->quantity); ?></div>
                    <div class="address"><label class="user-label">Price:</label> <?php echo wc_price($item->product_price); ?></div>
                </li>
            <?php endforeach; ?>
        </ul>

        
        <form method="post">
            <div >
                <!-- Billing Details -->
                <div class="billing-address">
                    <h3>Billing Details</h3>
                    <div class="details-row">
                    <div class="address"><label class="user-label">First Name:</label> <input type="text" name="billing_first_name" value="<?php echo esc_attr($billing_shipping['billing_first_name'] ?? ''); ?>"></div>
                    <div class="address"><label class="user-label">Last Name:</label> <input type="text" name="billing_last_name" value="<?php echo esc_attr($billing_shipping['billing_last_name'] ?? ''); ?>"></div>
                    <div class="address"><label class="user-label">Company:</label> <input type="text" name="billing_company" value="<?php echo esc_attr($billing_shipping['billing_company'] ?? ''); ?>"></div>
                    </div>

                    <div class="details-row">
                    <div class="address"><label class="user-label">Address Line 1:</label> <input type="text" name="billing_address_1" value="<?php echo esc_attr($billing_shipping['billing_address_1'] ?? ''); ?>"></div>
                    <div class="address"><label class="user-label">Address Line 2:</label> <input type="text" name="billing_address_2" value="<?php echo esc_attr($billing_shipping['billing_address_2'] ?? ''); ?>"></div>
                    <div class="address"><label class="user-label">City:</label> <input type="text" name="billing_city" value="<?php echo esc_attr($billing_shipping['billing_city'] ?? ''); ?>"></div>
                    </div>

                    <div class="details-row">
                    <div class="address"><label class="user-label">Postcode:</label> <input type="text" name="billing_postcode" value="<?php echo esc_attr($billing_shipping['billing_postcode'] ?? ''); ?>"></div>
                    <div class="address"><label class="user-label">Country:</label> <input type="text" name="billing_country" value="<?php echo esc_attr($billing_shipping['billing_country'] ?? ''); ?>"></div>
                    <div class="address"><label class="user-label">State:</label> <input type="text" name="billing_state" value="<?php echo esc_attr($billing_shipping['billing_state'] ?? ''); ?>"></div>
                    </div>

                    <div class="details-row">
                    <div class="address"><label class="user-label">Email:</label> <input type="email" name="billing_email" value="<?php echo esc_attr($billing_shipping['billing_email'] ?? ''); ?>"></div>
                    <div class="address"><label class="user-label">Phone:</label> <input type="text" name="billing_phone" value="<?php echo esc_attr($billing_shipping['billing_phone'] ?? ''); ?>"></div>
                    </div>

                </div>

                <!-- Shipping Details -->
                <div class="billing-address">
                    <h3>Shipping Details</h3>

                    <div class="details-row">
                    <div class="address"><label class="user-label">First Name:</label> <input type="text" name="shipping_first_name" value="<?php echo esc_attr($billing_shipping['shipping_first_name'] ?? ''); ?>"></div>
                    <div class="address"><label class="user-label">Last Name:</label> <input type="text" name="shipping_last_name" value="<?php echo esc_attr($billing_shipping['shipping_last_name'] ?? ''); ?>"></div>
                    <div class="address"><label class="user-label">Company:</label> <input type="text" name="shipping_company" value="<?php echo esc_attr($billing_shipping['shipping_company'] ?? ''); ?>"></div>
                    </div>

                    <div class="details-row">
                    <div class="address"><label class="user-label">Address Line 1:</label> <input type="text" name="shipping_address_1" value="<?php echo esc_attr($billing_shipping['shipping_address_1'] ?? ''); ?>"></div>
                    <div class="address"><label class="user-label">Address Line 2:</label> <input type="text" name="shipping_address_2" value="<?php echo esc_attr($billing_shipping['shipping_address_2'] ?? ''); ?>"></div>
                    <div class="address"><label class="user-label">City:</label> <input type="text" name="shipping_city" value="<?php echo esc_attr($billing_shipping['shipping_city'] ?? ''); ?>"></div>
                    </div>

                    <div class="details-row">
                    <div class="address"><label class="user-label">Postcode:</label> <input type="text" name="shipping_postcode" value="<?php echo esc_attr($billing_shipping['shipping_postcode'] ?? ''); ?>"></div>
                    <div class="address"><label class="user-label">Country:</label> <input type="text" name="shipping_country" value="<?php echo esc_attr($billing_shipping['shipping_country'] ?? ''); ?>"></div>
                    <div class="address"><label class="user-label">State:</label> <input type="text" name="shipping_state" value="<?php echo esc_attr($billing_shipping['shipping_state'] ?? ''); ?>"></div>
                    </div>
                </div>
            </div>

            <input type="hidden" name="order_id" value="<?php echo esc_attr($order_id); ?>">
            <button type="submit"class="padding-button All-button" name="update_order_details">Update Details</button>
        </form>
    </div>
    <?php
}

// Handle form submission to update Billing & Shipping details
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_order_details'])) {
    $order_id = intval($_POST['order_id']);

    foreach ($_POST as $key => $value) {
        if (!empty($value) && (strpos($key, 'billing_') === 0 || strpos($key, 'shipping_') === 0)) {
            update_post_meta($order_id, "_$key", sanitize_text_field($value));
        }
    }
    echo "<p>Order details updated successfully.</p>";
}


$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : null;

if ($order_id) {
    render_order_overview($order_id);
} else {
    echo render_user_orders($user_orders, $orders, $filter_status, $filter_month, $search_query, $current_page, $total_pages);
}




?>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Select all checkbox functionality
        const selectAllCheckbox = document.getElementById('select-all');
        const orderCheckboxes = document.querySelectorAll('input[name="selected_orders[]"]');

        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function () {
                orderCheckboxes.forEach(checkbox => checkbox.checked = this.checked);
            });
        }

        // Ensure bulk action requires at least one selected order
        const bulkActionForm = document.querySelector('.bulk-actions');
        if (bulkActionForm) {
            bulkActionForm.addEventListener('submit', function (event) {
                const selectedOrders = Array.from(orderCheckboxes).filter(checkbox => checkbox.checked);
                if (selectedOrders.length === 0) {
                    event.preventDefault();
                    alert('Please select at least one order to perform a bulk action.');
                }
            });
        }

        // Success or error message dismissal
        const notices = document.querySelectorAll('.notice.is-dismissible');
        notices.forEach(notice => {
            setTimeout(() => {
                notice.style.display = 'none';
            }, 5000); // Auto-dismiss after 5 seconds
        });
    });
</script>
