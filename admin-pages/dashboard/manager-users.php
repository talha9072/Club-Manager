<?php
// File: admin-pages/dashboard/manager-users.php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Check if the user is a Club Manager
function is_club_manager_page_accessible() {
    global $wpdb;

    $current_user = wp_get_current_user();
    if (!$current_user->exists()) {
        return false;
    }

    $user_email = $current_user->user_email;
    $club_manager = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM wp_club_members WHERE user_email = %s AND role = 'Club Manager'",
            $user_email
        )
    );

    return $club_manager > 0;
}

// Fetch the status of a specific subscription ID dynamically
function get_subscription_status($subscription_id) {
    global $wpdb;

    if (!$subscription_id || $subscription_id === 'N/A') {
        return 'N/A';
    }

    $status = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT post_status 
             FROM wp_posts
             WHERE ID = %d",
            $subscription_id
        )
    );

    $status_map = [
        'wc-active' => 'Active',
        'wc-pending' => 'Pending',
        'wc-cancelled' => 'Cancelled',
        'wc-on-hold' => 'On Hold',
        'wc-expired' => 'Expired',
    ];

    return isset($status_map[$status]) ? $status_map[$status] : ucfirst(str_replace('wc-', '', $status));
}

// Fetch the role of a user by user ID
function get_user_role($user_id) {
    global $wpdb;

    if (!$user_id || $user_id === 'N/A') {
        return 'N/A';
    }

    $meta_key = $wpdb->prefix . 'capabilities';
    $role_data = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT meta_value 
             FROM {$wpdb->usermeta}
             WHERE user_id = %d
             AND meta_key = %s",
            $user_id,
            $meta_key
        )
    );

    if (!$role_data) {
        return 'N/A';
    }

    $roles = maybe_unserialize($role_data);
    
    // Extract the role name (first key of the array)
    if (is_array($roles) && !empty($roles)) {
        $role_keys = array_keys($roles);
        return $role_keys[0]; // Return only the first role name
    }

    return 'N/A';
}


function get_club_users_and_subscriptions() {
    global $wpdb;

    $club_id = null;
    $search_query = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';

    // Check if 'club' and '2ndclubid' parameters exist in the URL
    if (isset($_GET['club']) && !empty($_GET['club']) && isset($_GET['2ndclubid']) && !empty($_GET['2ndclubid'])) {
        $club_name = urldecode(sanitize_text_field($_GET['club']));
        $club_id = intval($_GET['2ndclubid']);

        // Validate the club information
        $valid_club = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT club_id FROM {$wpdb->prefix}clubs WHERE club_name = %s AND club_id = %d",
                $club_name,
                $club_id
            )
        );

        if (!$valid_club) {
            return []; // Return empty if the club is invalid
        }
    }

    // Fallback: Get the logged-in user's club details
    if (!$club_id) {
        $current_user = wp_get_current_user();
        if (!$current_user->exists()) {
            return []; // Return empty if the user is not logged in
        }

        $user_email = $current_user->user_email;
        $club_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT club_id FROM {$wpdb->prefix}club_members WHERE user_email = %s AND role = 'Club Manager'",
                $user_email
            )
        );

        if (!$club_id) {
            return []; // Return empty if the user is not associated with a club
        }
    }

    // Fetch all products associated with the club ID
    $products = $wpdb->get_results(
        "SELECT 
            p.ID 
         FROM {$wpdb->prefix}posts p 
         JOIN {$wpdb->prefix}postmeta pm ON p.ID = pm.post_id 
         WHERE p.post_type = 'product' 
           AND pm.meta_key = '_select_club_id' 
           AND pm.meta_value = " . intval($club_id),
        ARRAY_A
    );

    $product_ids = wp_list_pluck($products, 'ID');

    // Get all users with subscriptions linked to these product IDs
    $club_users = [];
    if (!empty($product_ids)) {
        $product_ids_placeholder = implode(',', array_map('intval', $product_ids));

        $filters = [];

        if (!empty($search_query)) {
            $filters[] = $wpdb->prepare(
                "(u.user_email LIKE %s OR u.display_name LIKE %s)",
                '%' . $search_query . '%',
                '%' . $search_query . '%'
            );
        }

        if (isset($_GET['status']) && !empty($_GET['status'])) {
            $status_param = sanitize_text_field($_GET['status']);
    if ($status_param === 'on_hold') {
        $status_param = 'on-hold'; // Convert to proper format used in WooCommerce
    }
    $filters[] = $wpdb->prepare("p.post_status = %s", 'wc-' . $status_param);

            }

            if (isset($_GET['role']) && !empty($_GET['role'])) {
                $filters[] = $wpdb->prepare("um.meta_value LIKE %s", '%' . sanitize_text_field($_GET['role']) . '%');
            }

            if (isset($_GET['month']) && !empty($_GET['month'])) {
                $month_filter = sanitize_text_field($_GET['month']);
                $month_start = date('Y-m-01', strtotime($month_filter));
                $month_end = date('Y-m-t', strtotime($month_filter));
                $filters[] = $wpdb->prepare("pm_end.meta_value BETWEEN %s AND %s", $month_start, $month_end);
            }

            $filter_sql = !empty($filters) ? ' AND ' . implode(' AND ', $filters) : '';

            $subscriptions = $wpdb->get_results(
                "SELECT DISTINCT 
                    p.ID AS subscription_id,
                    pm.meta_value AS user_id,
                    p.post_status AS subscription_status,
                    u.user_email,
                    u.display_name AS user_name,
                    oim.meta_value AS product_id,
                    prod_post.post_title AS product_name,
                    pm_next_payment.meta_value AS next_payment_date,
                    pm_end.meta_value AS schedule_end
                FROM {$wpdb->prefix}posts p
                INNER JOIN {$wpdb->prefix}postmeta pm 
                    ON pm.post_id = p.ID AND pm.meta_key = '_customer_user'
                INNER JOIN {$wpdb->prefix}woocommerce_order_items oi 
                    ON p.ID = oi.order_id
                INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim 
                    ON oi.order_item_id = oim.order_item_id
                INNER JOIN {$wpdb->prefix}users u 
                    ON pm.meta_value = u.ID
                LEFT JOIN {$wpdb->prefix}postmeta pm_next_payment 
                    ON p.ID = pm_next_payment.post_id AND pm_next_payment.meta_key = '_schedule_next_payment'
                LEFT JOIN {$wpdb->prefix}postmeta pm_end 
                    ON p.ID = pm_end.post_id AND pm_end.meta_key = '_schedule_end'
                LEFT JOIN {$wpdb->prefix}posts prod_post 
                    ON prod_post.ID = oim.meta_value
                LEFT JOIN {$wpdb->usermeta} um
                    ON um.user_id = u.ID AND um.meta_key = '{$wpdb->prefix}capabilities'
                WHERE p.post_type = 'shop_subscription'
                AND p.post_status != 'trash'
                AND oim.meta_key = '_product_id'
                AND oim.meta_value IN ($product_ids_placeholder)
                $filter_sql
                ORDER BY p.ID DESC",  
                ARRAY_A
            );

            foreach ($subscriptions as $subscription) {
                $club_users[] = [
                    'user_email' => $subscription['user_email'],
                    'user_id' => $subscription['user_id'],
                    'user_name' => $subscription['user_name'],
                    'role' => get_user_role($subscription['user_id']),
                    'membership' => [
                        [
                            'subscription_id' => $subscription['subscription_id'],
                            'membership_plan' => $subscription['product_name'],
                            'next_payment_date' => $subscription['next_payment_date'] ?? 'N/A',
                            'schedule_end' => $subscription['schedule_end'] ?? 'N/A',
                            'membership_status' => $subscription['subscription_status'],
                        ]
                    ]
                ];
            }
        }

        return $club_users;
    }




function export_all_club_users() {
    global $wpdb;

    while (ob_get_level()) {
        ob_end_clean();
    }

    $current_user = wp_get_current_user();
    $user_email = $current_user->user_email;

    $club_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT club_id FROM {$wpdb->prefix}club_members WHERE user_email = %s LIMIT 1",
            $user_email
        )
    );

    $registration_form_id = $club_id ? $wpdb->get_var(
        $wpdb->prepare(
            "SELECT registration_form FROM {$wpdb->prefix}clubs WHERE club_id = %d LIMIT 1",
            $club_id
        )
    ) : null;

    $club_users = get_club_users_and_subscriptions();

    if (empty($club_users)) {
        wp_die("No users found for export.");
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="club_users_' . date("Y-m-d_H-i") . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');

    $headers = [
        'User ID', 'User Name', 'Email', 'Subscription ID', 
        'Membership Plan', 'Next Payment Date', 'End Date', 
        'Status', 'Role', 
        'Partner Name', 'Partner Surname', 'Form Email', 'Mobile Number', 
        'ID Number', 'ICE Contact Name', 'ICE Contact Number',
        'Street Address', 'Address Line 2', 'City', 'State / Province', 'ZIP / Postal Code'
    ];
    fputcsv($output, $headers);

    foreach ($club_users as $user) {
        $membership = $user['membership'][0] ?? [];
        $user_id = intval($user['user_id']);

        $entry_id = null;
        $email = sanitize_email($user['user_email']);

        if ($registration_form_id && $email) {
            $entry_id = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT e.id
                     FROM {$wpdb->prefix}gf_entry e
                     INNER JOIN {$wpdb->prefix}gf_entry_meta em ON e.id = em.entry_id
                     WHERE e.form_id = %d AND em.meta_key = '7' AND em.meta_value = %s
                     ORDER BY e.date_created DESC
                     LIMIT 1",
                    $registration_form_id,
                    $email
                )
            );
        }

        $get_field = function($entry_id, $field_id) use ($wpdb) {
            if (!$entry_id || !$field_id) return '';
            return $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT meta_value FROM {$wpdb->prefix}gf_entry_meta 
                     WHERE entry_id = %d AND meta_key = %s LIMIT 1",
                    $entry_id,
                    (string) $field_id
                )
            );
        };

        $partner_name     = $get_field($entry_id, '4');
        $partner_surname  = $get_field($entry_id, '5');
        $form_email       = $get_field($entry_id, '7');
        $mobile_number    = $get_field($entry_id, '8');
        $id_number        = $get_field($entry_id, '9');
        $ice_name         = $get_field($entry_id, '84');
        $ice_contact      = $get_field($entry_id, '85');

        // Individual address fields
        $addr1  = $get_field($entry_id, '10.1');
        $addr2  = $get_field($entry_id, '10.2');
        $city   = $get_field($entry_id, '10.3');
        $state  = $get_field($entry_id, '10.4');
        $zip    = $get_field($entry_id, '10.5');

        $user_data = [
            strip_tags(trim($user['user_id'])),
            strip_tags(trim($user['user_name'])),
            strip_tags(trim($user['user_email'])),
            strip_tags(trim($membership['subscription_id'] ?? 'N/A')),
            strip_tags(trim($membership['membership_plan'] ?? 'N/A')),
            !empty($membership['next_payment_date']) ? date('Y-m-d', strtotime($membership['next_payment_date'])) : 'N/A',
            !empty($membership['schedule_end']) ? date('Y-m-d', strtotime($membership['schedule_end'])) : 'N/A',
            ucfirst(str_replace('wc-', '', strip_tags(trim($membership['membership_status'] ?? 'N/A')))),
            strip_tags(trim($user['role'] ?? 'N/A')),
            $partner_name ?: '',
            $partner_surname ?: '',
            $form_email ?: '',
            $mobile_number ?: '',
            $id_number ?: '',
            $ice_name ?: '',
            $ice_contact ?: '',
            $addr1 ?: '',
            $addr2 ?: '',
            $city ?: '',
            $state ?: '',
            $zip ?: ''
        ];

        fputcsv($output, $user_data);
    }

    fclose($output);
    exit;
}









// Function to render the Club Users table
function render_club_users_table($club_users, $all_club_users) {
  
   
    if (isset($_GET['export_all']) && $_GET['export_all'] == '1') {
        export_all_club_users();
        exit; // Stop further execution
    }
    
    
    $status_counts = [
        'all' => count($all_club_users),
        'active' => 0,
        'pending' => 0,
        'cancelled' => 0,
        'on_hold' => 0,
        'expired' => 0,
    ];
    
    foreach ($all_club_users as $user) {
        $status = strtolower(trim($user['membership'][0]['membership_status'] ?? ''));
    
        if (strpos($status, 'wc-') === 0) {
            $status = substr($status, 3);
        }
    
        if ($status === 'on hold' || $status === 'on-hold') {
            $status = 'on_hold';
        }
    
        if (isset($status_counts[$status])) {
            $status_counts[$status]++;
        }
    }
    
    
    // Get selected status from URL
    $selected_status = isset($_GET['status']) ? strtolower(sanitize_text_field($_GET['status'])) : '';
    
    // Filter users based on the selected status
    if (!empty($selected_status) && $selected_status !== 'all') {
        $club_users = array_filter($club_users, function ($user) use ($selected_status) {
            $user_status = strtolower(trim($user['membership'][0]['membership_status'] ?? ''));
    
            // Remove 'wc-' prefix if it exists
            if (strpos($user_status, 'wc-') === 0) {
                $user_status = substr($user_status, 3);
            }
    
            // Fix 'on-hold' variations ('wc-on-hold' and 'on hold' should map to 'on_hold')
            if ($user_status === 'on hold' || $user_status === 'on-hold') {
                $user_status = 'on_hold';
            }
    
            return $user_status === $selected_status;
        });
    }

     // Define pagination variables
     $current_page = max(1, intval(get_query_var('paged', 1))); // Current page (default to 1 if not set)
     $items_per_page = 10; // Number of items per page
     $total_items = count($club_users); // Total number of users
     $total_pages = ceil($total_items / $items_per_page); // Total pages
     $offset = ($current_page - 1) * $items_per_page; // Offset for array slicing
 
    ?>
    
    <!-- Status Filters -->
    <div class="status-filters count" style="margin-bottom: 15px;">
        <a href="<?php echo esc_url(remove_query_arg('status')); ?>" 
           class="<?php echo empty($selected_status) ? 'current' : ''; ?>" 
           style="margin-right: 10px;">
            All (<?php echo intval($status_counts['all']); ?>)
        </a> |
    
        <a href="<?php echo esc_url(add_query_arg(['status' => 'active', 'paged' => 1])); ?>" 
           class="<?php echo $selected_status === 'active' ? 'current' : ''; ?>" 
           style="margin-right: 10px;">
            Active (<?php echo intval($status_counts['active']); ?>)
        </a> |
    
        <a href="<?php echo esc_url(add_query_arg('status', 'pending')); ?>" 
           class="<?php echo $selected_status === 'pending' ? 'current' : ''; ?>" 
           style="margin-right: 10px;">
            Pending (<?php echo intval($status_counts['pending']); ?>)
        </a> |
    
        <a href="<?php echo esc_url(add_query_arg('status', 'cancelled')); ?>" 
           class="<?php echo $selected_status === 'cancelled' ? 'current' : ''; ?>" 
           style="margin-right: 10px;">
            Cancelled (<?php echo intval($status_counts['cancelled']); ?>)
        </a> |
    
        <a href="<?php echo esc_url(add_query_arg('status', 'on_hold')); ?>" 
           class="<?php echo $selected_status === 'on_hold' ? 'current' : ''; ?>" 
           style="margin-right: 10px;">
            On Hold (<?php echo intval($status_counts['on_hold']); ?>)
        </a> |
    
        <a href="<?php echo esc_url(add_query_arg('status', 'expired')); ?>" 
           class="<?php echo $selected_status === 'expired' ? 'current' : ''; ?>" 
           style="margin-right: 10px;">
            Expired (<?php echo intval($status_counts['expired']); ?>)
        </a>
    </div>
    
        
        <!-- Filters Section -->
        <div class="filter-section end-filters">
        
        <form id="filters-form" class="user-filters end-filters" method="get">
    <!-- Preserve existing params -->
    <input type="hidden" name="section" value="<?php echo esc_attr($_GET['section'] ?? ''); ?>">
    <input type="hidden" name="club" value="<?php echo esc_attr($_GET['club'] ?? ''); ?>">

    <select id="filter-status" name="status">
        <option value="">Status</option>
        <option value="active">Active</option>
        <option value="pending">Pending</option>
        <option value="cancelled">Cancelled</option>
        <option value="on_hold">On Hold</option>
        <option value="expired">Expired</option>
    </select>

    <select id="filter-role" name="role">
        <option value="">Role</option>
        <option value="member">Member</option>
        <option value="customer">Customer</option>
    </select>

    <input type="month" id="filter-month" name="month">

    <button type="submit" id="apply-filters" class="All-button"
        style="background: #10487B; color: white; border: none; cursor: pointer;">
        Apply Filters
    </button>
    <a href="<?php echo esc_url(add_query_arg(['club' => rawurldecode($_GET['club'] ?? ''), 'section' => $_GET['section'] ?? ''], strtok($_SERVER['REQUEST_URI'], '?'))); ?>" class="clear-filter">
    Clear Filters
    </a>

    </form>



                <div class="user-search end-filters">
                
                <div class="input-icon">
                <input type="text" id="search-users" placeholder="Search by name or email" 
        value="<?php echo isset($_GET['search']) ? esc_attr($_GET['search']) : ''; ?>">

        <span class="icon"><i class="fa fa-search"></i></span> <!-- Font Awesome Icon -->
        </div>
        <button id="search-button"class="All-button" style="background: #10487B; color: white; border: none;  cursor: pointer;">
            Search
        </button>


            </div>

            
        </div>

        <div class="bulk-actions bulk-users end-filters" style="margin-bottom: 20px;">
        <select id="bulk-action-select">
            <option value="">Bulk Actions</option>
            <option value="delete">Delete</option>
            <option value="export">Export Selected as CSV</option>
        </select>
        <button id="apply-bulk-action"class="All-button" style=" background: #10487B; color: white; border: none;  cursor: pointer;">Apply Action</button>

        <!-- Export All Button -->
        <a href="<?php echo esc_url(add_query_arg('export_all', '1')); ?>" 
        style=" border: none;  text-decoration: none;" class="All-button">
            Export All Users
        </a>
        </div>


        <!-- Table Section -->
        <table style="width: 100%; border-collapse: collapse; "id="user-table" class="managertable">
            <thead>
                <tr style=" border-bottom: 1px solid #ddd;"class="table-head">
                    <th style="padding: 10px;"><input type="checkbox" id="select-all"></th>
                    
                    <th style="padding: 10px; text-align: left;">User</th>
                    <th style="padding: 10px; text-align: left;">Subscription ID</th>
                    <th style="padding: 10px; text-align: left;">Next Payment</th>
                    <th style="padding: 10px; text-align: left;">End Date</th>
                    <th style="padding: 10px; text-align: left;">Status</th>
                    <th style="padding: 10px; text-align: left;">Role</th>
                    <th style="padding: 10px; text-align: left;">Actions</th>
                </tr>
            </thead>
            <tbody id="users-table-body">
                <?php
            // Slice the club users array for pagination
            $club_users = array_slice($club_users, $offset, $items_per_page);
            ?>
                    <?php foreach ($club_users as $user) : ?>
                    
                        <tr style="border-bottom: 1px solid #ddd;">
                            <td style="padding: 10px;" data-label="Select">
                                <input type="checkbox" class="select-user" value="<?php echo esc_attr($user['user_id']); ?>">
                            </td>
                            
                            <td style="padding: 10px;" data-label="User">
                                <a href="<?php echo esc_url(add_query_arg([
                                    'person_id' => $user['user_id'],
                                    'section' => 'users',
                                    
                                ])); ?>" 
                                class="user-link" 
                                data-user-id="<?php echo esc_attr($user['user_id']); ?>" 
                                data-user-name="<?php echo esc_attr($user['user_name']); ?>" 
                                data-user-email="<?php echo esc_attr($user['user_email']); ?>" 
                                data-user-role="<?php echo esc_attr($user['role']); ?>" 
                                style="color: #10487B;">
                                    <strong><?php echo esc_html($user['user_name']); ?></strong>
                                </a><br>
                                <span style="color: #777;"><?php echo esc_html($user['user_email']); ?></span>
                            </td>

                            <td style="padding: 10px;" data-label="Subscription ID">
                                <div>
                                <strong>
                                    <a href="<?php echo esc_url(admin_url('post.php?post=' . $user['membership'][0]['subscription_id'] . '&action=edit')); ?>" 
                                    style="text-decoration: none; color: #10487B;">
                                        <?php echo esc_html($user['membership'][0]['subscription_id']); ?>
                                    </a>
                                </strong><br>
                                    <span style="color: #555;">
                                        <?php 
                                            echo isset($user['membership'][0]['membership_plan']) && !empty($user['membership'][0]['membership_plan']) 
                                                ? esc_html($user['membership'][0]['membership_plan']) 
                                                : 'N/A'; 
                                        ?>
                                    </span>
                                </div>
                            </td>
                            <td style="padding: 10px;" data-label="Next Payment">
                                <?php 
                                    echo !empty($user['membership'][0]['next_payment_date']) 
                                        ? esc_html(date('d-M-Y', strtotime($user['membership'][0]['next_payment_date']))) 
                                        : 'N/A'; 
                                ?>
                            </td>
                            <td style="padding: 10px;" data-label="Schedule End"><?php echo esc_html(date('d-M-Y', strtotime($user['membership'][0]['schedule_end']))); ?></td>
                            <td style="padding: 10px;" data-label="Status">
            <?php 
                $status = strtolower(trim($user['membership'][0]['membership_status'] ?? '')); // Convert to lowercase & trim whitespace

                // Remove 'wc-' prefix if it exists (WooCommerce subscription statuses)
                if (strpos($status, 'wc-') === 0) {
                    $status = substr($status, 3); // Remove 'wc-' prefix
                }

                // Normalize variations (on-hold, on hold)
                if (in_array($status, ['on-hold', 'on hold'], true)) {
                    $status = 'on hold'; 
                }

                // Map the status correctly
                $status_map = [
                    'active'    => 'Active',
                    'pending'   => 'Pending',
                    'on hold'   => 'On Hold',  // Fixed issue here
                    'cancelled' => 'Cancelled',
                    'expired'   => 'Expired'
                ];

                $formatted_status = $status_map[$status] ?? ucfirst(str_replace('_', ' ', $status)); // Default formatting

                $badgeColor = ''; 

                // Determine badge color based on status
                switch ($status) {
                    case 'active':
                        $badgeColor = 'background: #d4edda; color: #155724; border: 1px solid #c3e6cb;';
                        break;
                    case 'pending':
                        $badgeColor = 'background: #f8f9fa; color: #6c757d; border: 1px solid #dee2e6;';
                        break;
                    case 'on hold': // Fixed "on-hold" issue
                        $badgeColor = 'background: #fff3cd; color: #856404; border: 1px solid #ffeeba;';
                        break;
                    case 'cancelled':
                        $badgeColor = 'background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;';
                        break;
                    case 'expired':
                        $badgeColor = 'background: #e2e3e5; color: #818182; border: 1px solid #d6d8db;';
                        break;
                    default:
                        $badgeColor = 'background: #e2e3e5; color: #818182; border: 1px solid #d6d8db;';
                        $formatted_status = ucfirst($status); // Default to readable format
                        break;
                }
            ?>
            <span class="badge" style="display: inline-block; padding: 5px 10px; border-radius: 3px; font-size: 12px; <?php echo esc_attr($badgeColor); ?>">
                <?php echo esc_html($formatted_status); ?>
            </span>
        </td>

                        <td style="padding: 10px;" data-label="Role"><?php echo esc_html($user['role'] ?? 'N/A'); ?></td>
                        <td style="padding: 10px;" data-label="Actions">
                        
                            <?php
        // Get and format status clearly before the button
        $status = strtolower(trim($user['membership'][0]['membership_status'] ?? '')); 

        if (strpos($status, 'wc-') === 0) {
            $status = substr($status, 3);
        }

        if (in_array($status, ['active', 'expired', 'cancelled'], true)):
    ?>
    <button 
        class="renew-subscription-btn action-item" 
        data-subscription-id="<?php echo esc_attr($user['membership'][0]['subscription_id']); ?>">
        Renew
    </button>
    <?php endif; ?>

                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ($total_pages > 1): ?>
        <div class="pagination" style="margin-top: 20px; text-align: center; display: flex; flex-wrap: wrap; justify-content: center; gap: 5px;">
            <?php if ($current_page > 1): ?>
                <!-- Previous Button -->
                <a href="<?php echo esc_url(add_query_arg('paged', $current_page - 1)); ?>" 
                style="padding: 5px 10px; border: 1px solid #ddd; text-decoration: none; color: #333;">
                    Previous
                </a>
            <?php endif; ?>

            <?php
            // Pagination logic for displaying limited page numbers
            $max_pages_to_show = 5; // Adjust this value for the number of page links to show
            $start_page = max(1, $current_page - floor($max_pages_to_show / 2));
            $end_page = min($total_pages, $start_page + $max_pages_to_show - 1);

            if ($start_page > 1): ?>
                <a href="<?php echo esc_url(add_query_arg('paged', 1)); ?>" 
                style="padding: 5px 10px; border: 1px solid #ddd; text-decoration: none; color: #333;">
                    1
                </a>
                <?php if ($start_page > 2): ?>
                    <span style="padding: 5px;">...</span>
                <?php endif; ?>
            <?php endif; ?>

            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                <a href="<?php echo esc_url(add_query_arg('paged', $i)); ?>" 
                class="<?php echo ($i == $current_page) ? 'current' : ''; ?>" 
                style="padding: 5px 10px; border: 1px solid #ddd; text-decoration: none; <?php echo ($i == $current_page) ? 'background: #10487B; color: #fff;' : 'color: #333;'; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>

            <?php if ($end_page < $total_pages): ?>
                <?php if ($end_page < $total_pages - 1): ?>
                    <span style="padding: 5px;">...</span>
                <?php endif; ?>
                <a href="<?php echo esc_url(add_query_arg('paged', $total_pages)); ?>" 
                style="padding: 5px 10px; border: 1px solid #ddd; text-decoration: none; color: #333;">
                    <?php echo $total_pages; ?>
                </a>
            <?php endif; ?>

            <?php if ($current_page < $total_pages): ?>
                <!-- Next Button -->
                <a href="<?php echo esc_url(add_query_arg('paged', $current_page + 1)); ?>" 
                style="padding: 5px 10px; border: 1px solid #ddd; text-decoration: none; color: #333;">
                    Next
                </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>




        <script>
            document.addEventListener('DOMContentLoaded', function () {
                // Add click event to user links
                const userLinks = document.querySelectorAll('.user-link');

                userLinks.forEach(link => {
                    link.addEventListener('click', function (e) {
                        e.preventDefault(); // Prevent the default link behavior

                        // Get the user ID from the clicked link
                        const userId = this.getAttribute('data-user-id');

                        // Redirect to the same URL as the fa-edit icon
                        if (userId) {
                            const newUrl = `?page=manager-users&person_id=${userId}`;
                            window.location.href = newUrl;
                        }
                    });
                });
            });
        </script>

        <script>
        document.addEventListener('DOMContentLoaded', function () {
        document.getElementById('search-button').addEventListener('click', function () {
            let searchQuery = document.getElementById('search-users').value.trim();
            let newUrl = new URL(window.location.href);
            
            if (searchQuery) {
                newUrl.searchParams.set('search', searchQuery);
            } else {
                newUrl.searchParams.delete('search');
            }

            window.location.href = newUrl.toString();
        });

        document.getElementById('search-users').addEventListener('keydown', function(event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                document.getElementById('search-button').click();
            }
        });
    });

    </script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('#users-table-body tr').forEach(row => {
            const roleCell = row.querySelector('td:nth-child(8)'); // Role column
            if (roleCell) {
                const roleText = roleCell.textContent.trim().toLowerCase(); // Normalize text
                if (roleText === 'subscriber') {
                    roleCell.textContent = 'Member'; // Update display text
                } else if (roleText === 'customer') {
                    roleCell.textContent = 'Customer'; // Update display text
                }
            }
        });
    });



        document.addEventListener('DOMContentLoaded', function () {
            // Renew subscription functionality
            document.querySelectorAll('.renew-subscription-btn').forEach(button => {
                button.addEventListener('click', function () {
                    const subscriptionId = this.getAttribute('data-subscription-id');
                    if (!subscriptionId) {
                        alert('Invalid Subscription ID');
                        return;
                    }
                    if (confirm('Are you sure you want to renew this subscription?')) {
                        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: new URLSearchParams({
                                action: 'renew_subscription',
                                subscription_id: subscriptionId,
                            })
                        })
                        .then(response => response.json())
                        .then(result => {
                            if (result.success) {
                                alert('Subscription renewed successfully!');
                                location.reload();
                            } else {
                                alert(result.message || 'Failed to renew subscription.');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('An unexpected error occurred. Please try again.');
                        });
                    }
                });
            });

            // Apply filters
            document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('filters-form');
    const params = new URLSearchParams(window.location.search);

    // Pre-fill filters based on current URL params
    document.getElementById('filter-status').value = params.get('status') || '';
    document.getElementById('filter-role').value = params.get('role') || '';
    document.getElementById('filter-month').value = params.get('month') || '';

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        const formData = new FormData(form);
        const url = new URL(window.location.href);

        // Preserve existing params
        const existingParams = ['section', 'club', '2ndclubid'];
        existingParams.forEach(param => {
            const val = params.get(param);
            if (val) url.searchParams.set(param, val);
        });

        // Update filters
        ['status', 'role', 'month'].forEach(param => {
            const val = formData.get(param);
            if (val) {
                url.searchParams.set(param, val);
            } else {
                url.searchParams.delete(param);
            }
        });

        url.searchParams.set('paged', 1);

        window.location.href = url.toString();
    });
    });



            // Handle bulk actions
            document.getElementById('apply-bulk-action').addEventListener('click', function () {
                const action = document.getElementById('bulk-action-select').value;
                const selectedUsers = Array.from(document.querySelectorAll('.select-user:checked')).map(input => input.value);

                if (!action || selectedUsers.length === 0) {
                    alert('Please select a valid action and at least one user.');
                    return;
                }

                if (action === 'delete') {
                    if (confirm('Are you sure you want to delete selected users?')) {
                        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: new URLSearchParams({
                                action: 'bulk_delete_users',
                                user_ids: selectedUsers.join(',')
                            })
                        })
                        .then(response => response.json())
                        .then(result => {
                            if (result.success) {
                                alert('Selected users deleted successfully.');
                                location.reload();
                            } else {
                                alert(result.message || 'Failed to delete users.');
                            }
                        })
                        .catch(error => console.error('Error:', error));
                    }
                } else if (action === 'export') {
        // Initialize the CSV content with headers
        let csvContent = "data:text/csv;charset=utf-8,";
        csvContent += "User ID,User Name,Email,Subscription ID,Start Date,End Date,Status,Role\n";

        // Process selected users
        selectedUsers.forEach(userId => {
            const row = document.querySelector(`#users-table-body tr td input[value="${userId}"]`).closest('tr');
            const rowData = Array.from(row.querySelectorAll('td'))
                .slice(1, -1) // Exclude the first (checkbox) and last (actions) columns
                .map(td => td.textContent.trim().replace(/,/g, '')); // Remove commas from cell data
            csvContent += rowData.join(',') + '\n';
        });

        // Encode the CSV content
        const encodedUri = encodeURI(csvContent);

        // Create a temporary link for download
        const link = document.createElement('a');
        link.setAttribute('href', encodedUri);
        link.setAttribute('download', 'club_users.csv');

        // Append the link to the DOM, trigger the download, and remove the link
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

        });

        // Select all functionality
        document.getElementById('select-all').addEventListener('change', function () {
            const isChecked = this.checked;
            document.querySelectorAll('.select-user').forEach(checkbox => {
                checkbox.checked = isChecked;
            });
        });
    });
    </script>
    <?php
}




function render_edit_user_form() {
    global $wpdb;

    // Get user_id from URL
    if (!isset($_GET['person_id']) || !is_numeric($_GET['person_id'])) {
        echo "<p>Invalid user ID.</p>";
        return;
    }

    $user_id = intval($_GET['person_id']); // Sanitize user ID from URL

    

    // Correct SQL query with WordPress table prefixes
    $query = $wpdb->prepare(
        "SELECT 
            sub.ID AS subscription_id,
            order_meta.meta_value AS product_id,
            prod_post.post_title AS membership_plan,
            next_payment.meta_value AS next_payment_date,
            end_date.meta_value AS schedule_end
         FROM {$wpdb->posts} sub
         INNER JOIN {$wpdb->postmeta} customer_user 
             ON sub.ID = customer_user.post_id 
             AND customer_user.meta_key = '_customer_user'
         INNER JOIN {$wpdb->prefix}woocommerce_order_items order_items 
             ON sub.ID = order_items.order_id
         INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta order_meta 
             ON order_items.order_item_id = order_meta.order_item_id
         INNER JOIN {$wpdb->posts} prod_post 
             ON prod_post.ID = order_meta.meta_value
         LEFT JOIN {$wpdb->postmeta} next_payment 
             ON sub.ID = next_payment.post_id 
             AND next_payment.meta_key = '_schedule_next_payment'
         LEFT JOIN {$wpdb->postmeta} end_date 
             ON sub.ID = end_date.post_id 
             AND end_date.meta_key = '_schedule_end'
         WHERE sub.post_type = 'shop_subscription'
           AND customer_user.meta_value = %d
           AND order_meta.meta_key = '_product_id'
         LIMIT 1",
        $user_id
    );

    // Execute the query
    $subscription_data = $wpdb->get_results($query, ARRAY_A);

    // Store first subscription data (if available)
    $subscription_details = !empty($subscription_data) ? $subscription_data[0] : null;

    // Format next payment date (Handle "0" or empty cases)
    $formatted_next_payment = (!empty($subscription_details['next_payment_date']) && $subscription_details['next_payment_date'] !== '0')
        ? date('Y-m-d', strtotime($subscription_details['next_payment_date']))
        : '';

    // Format schedule end date
    $formatted_schedule_end = (!empty($subscription_details['schedule_end']))
        ? date('Y-m-d', strtotime($subscription_details['schedule_end']))
        : '';

    // Include Gravity Form Shortcode
    // Dynamically load Gravity Form based on logged-in user's club ID
   $current_user = wp_get_current_user();

    // Get the user's club_id from wp_club_members
    $club_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT club_id FROM {$wpdb->prefix}club_members WHERE user_email = %s LIMIT 1",
            $current_user->user_email
        )
    );

    // Get the registration_form ID from wp_clubs
    $registration_form_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT registration_form FROM {$wpdb->prefix}clubs WHERE club_id = %d",
            $club_id
        )
    );

    // If a form ID was found, render it
    if (!empty($registration_form_id)) {
        echo do_shortcode('[gravityform id="' . intval($registration_form_id) . '" title="true" description="true"]');
    } else {
        // fallback default form if no registration_form set
        echo do_shortcode('[gravityform id="2" title="true" description="true"]');
    }

    ?>

    <form id="edit-user-meta-form" class="edit-user-form" method="post">
    <h2 style="font-weight: bold; font-size: 28px; color: #262626; margin-bottom:10px">Membership Details</h2>
        <?php if ($subscription_details) : ?>
            <div class="form-row membership-row">
                <div class="form-group">
                    <label class="user-label">Current Membership</label>
                    <p><?php echo esc_html($subscription_details['membership_plan'] ?? 'N/A'); ?></p>
                </div>
                <div class="form-group">
                    <label class="user-label">Expiry Date</label>
                    <p><?php echo esc_attr($formatted_schedule_end); ?></p>
                </div>
            </div>
            <h3 style="font-weight: bold; font-size:18px">Schedule</h3>
            <div class="form-row membership-row">
                <div class="form-group">
                    <label class="user-label">Next Payment Date</label>
                    <input type="date" name="next_payment_date" value="<?php echo esc_attr($formatted_next_payment); ?>">
                </div>
                <div class="form-group">
                    <label class="user-label">Expiry Date</label>
                    <input type="date" name="schedule_end" value="<?php echo esc_attr($formatted_schedule_end); ?>">
                </div>
            </div>
        <?php else : ?>
            <p>No subscription details available.</p>
        <?php endif; ?>

        <div class="form-actions">
            <button type="submit" name="save_user_meta" class="btn btn-primary All-button">Save</button>
            <button type="button" class="btn btn-secondary All-button" onclick="history.back();">Cancel</button>
        </div>
    </form>

   

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        

        const dashboardContent = document.querySelector('.dashboard-content');
        if (!dashboardContent) {
            
            return;
        }

        const observer = new MutationObserver((mutations, obs) => {
            

            const titleEl = dashboardContent.querySelector('.manager-users-wrapper #gform_wrapper_2 .gform_title');
            if (titleEl) {
                
                titleEl.innerText = 'Profile';
                obs.disconnect();
            }
        });

        observer.observe(dashboardContent, {
            childList: true,
            subtree: true,
        });
    });
    </script>


    <?php
    // Process form submission (Update subscription details)
    if (isset($_POST['save_user_meta'])) {
        $next_payment_date = sanitize_text_field($_POST['next_payment_date'] ?? '');
        $schedule_end = sanitize_text_field($_POST['schedule_end'] ?? '');
    
        // Convert dates to 'Y-m-d H:i:s' format
        $next_payment_date = (!empty($next_payment_date)) ? date('Y-m-d H:i:s', strtotime($next_payment_date)) : '';
        $schedule_end = (!empty($schedule_end)) ? date('Y-m-d H:i:s', strtotime($schedule_end)) : '';
    
        // Ensure subscription exists before updating
        if ($subscription_details && !empty($subscription_details['subscription_id'])) {
            $subscription_id = $subscription_details['subscription_id'];
    
            // Update next payment date if provided
            if (!empty($next_payment_date)) {
                $wpdb->update(
                    $wpdb->postmeta,
                    ['meta_value' => $next_payment_date],
                    [
                        'post_id' => $subscription_id,
                        'meta_key' => '_schedule_next_payment',
                    ]
                );
            }
    
            // Update schedule end date if provided
            if (!empty($schedule_end)) {
                $wpdb->update(
                    $wpdb->postmeta,
                    ['meta_value' => $schedule_end],
                    [
                        'post_id' => $subscription_id,
                        'meta_key' => '_schedule_end',
                    ]
                );
            }
    
            // Redirect to avoid form resubmission
            wp_redirect(add_query_arg(['updated' => 'true'], $_SERVER['REQUEST_URI']));
            exit;
        }
    }
    
}







// Main logic to handle page rendering
if (!is_club_manager_page_accessible()) {
    echo '<p>You do not have permission to access this page.</p>';
    exit;
}

if (isset($_GET['person_id']) && is_numeric($_GET['person_id'])) {
    $person_id = intval($_GET['person_id']);
    ?>
    <div class="manager-users-wrapper" style="font-family: Arial, sans-serif;">
        
        <?php render_edit_user_form($person_id); ?>
    </div>
    <?php
   
} else {
    // Step 1: Get full unfiltered users (for status counts)
$original_status = $_GET['status'] ?? null;
unset($_GET['status']); // temporarily remove status filter
$all_club_users = get_club_users_and_subscriptions();

// Step 2: Now get the filtered users for display
if ($original_status !== null) {
    $_GET['status'] = $original_status;
}
$filtered_club_users = get_club_users_and_subscriptions();

    ?>
    <div class="manager-users-wrapper" style="font-family: Arial, sans-serif; ">
   <div class="admin-switch">
   <h2> Users</h2>
   <button onclick="window.location.href='<?php echo admin_url('users.php'); ?>'" style=" color: white;  border: none; cursor: pointer;"class="All-button">
     Advanced
   </button>
        
   </div>

        <div id="users-table-section">
            <?php render_club_users_table($filtered_club_users, $all_club_users); ?>
        </div>
    </div>
    <?php
}
