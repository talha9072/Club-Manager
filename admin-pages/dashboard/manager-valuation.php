<?php
// Prevent direct file access
if (!defined('ABSPATH')) {
    exit;
}

// Main logic to determine what to render
if (isset($_GET['club_id']) && !empty($_GET['club_id'])) {
    echo '<h2 class="manager-h2">Edit Valuation </h2>';
    render_add_member_form_v2(); // Render the add club member form if club_id is set
} else {
    echo '<h2 class="manager-h2">Valuations</h2>';
    render_members_table_v2(); // Render the table otherwise
}

// Function to get logged-in user's club information
function fetch_current_user_club_info() {
    global $wpdb;

    // Check if 'club' and '2ndclubid' parameters exist in the URL
    if (isset($_GET['club']) && !empty($_GET['club']) && isset($_GET['2ndclubid']) && !empty($_GET['2ndclubid'])) {
        $club_name = urldecode(sanitize_text_field($_GET['club']));
        $club_id = intval($_GET['2ndclubid']);

        // Fetch club information based on the 'club' name and '2ndclubid'
        $club_info = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}clubs WHERE club_name = %s AND club_id = %d",
                $club_name,
                $club_id
            )
        );

        return $club_info ? $club_info : false;
    }

    // Fallback: Fetch club information for the logged-in user
    $current_user = wp_get_current_user();
    if (!$current_user->exists()) {
        return false;
    }

    $user_email = $current_user->user_email;

    // Fetch the logged-in user's club information
    $club_info = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}club_members cm
             INNER JOIN {$wpdb->prefix}clubs c ON cm.club_id = c.club_id
             WHERE cm.user_email = %s",
            $user_email
        )
    );

    return $club_info ? $club_info : false;
}


// Function to handle file uploads
function handle_pdf_upload($entry_id) {
    // Check for the uploaded file
    if (!isset($_FILES['evaluation_pdf']) || $_FILES['evaluation_pdf']['error'] !== UPLOAD_ERR_OK) {
        return false; // File not uploaded properly
    }

    $uploaded_file = $_FILES['evaluation_pdf'];
    $upload_dir    = wp_upload_dir();
    $upload_path   = $upload_dir['basedir'] . '/evaluations/';
    $upload_url    = $upload_dir['baseurl'] . '/evaluations/';

    // Create the directory if it doesn't exist
    if (!file_exists($upload_path)) {
        wp_mkdir_p($upload_path);
    }

    // Unique file name
    $file_name = 'evaluation_' . $entry_id . '_' . time() . '.pdf';
    $file_path = $upload_path . $file_name;

    // Move the uploaded file
    if (move_uploaded_file($uploaded_file['tmp_name'], $file_path)) {
        global $wpdb;

        // Delete existing meta record if any
        $wpdb->delete(
            "{$wpdb->prefix}gf_entry_meta",
            [
                'entry_id' => $entry_id,
                'meta_key' => 'evaluation_pdf',
            ],
            ['%d', '%s']
        );

        // Insert new file meta
        $wpdb->insert(
            "{$wpdb->prefix}gf_entry_meta",
            [
                'entry_id'   => $entry_id,
                'meta_key'   => 'evaluation_pdf',
                'meta_value' => $upload_url . $file_name,
            ],
            ['%d', '%s', '%s']
        );

        return true;
    }

    return false;
}

// Function to handle file removal
function handle_pdf_removal($entry_id) {
    global $wpdb;

    // Get the current file path
    $pdf_path = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT meta_value FROM {$wpdb->prefix}gf_entry_meta WHERE entry_id = %d AND meta_key = 'evaluation_pdf'",
            $entry_id
        )
    );

    if ($pdf_path) {
        // Remove the file from the server
        $file_path = str_replace(wp_upload_dir()['baseurl'], wp_upload_dir()['basedir'], $pdf_path);
        if (file_exists($file_path)) {
            unlink($file_path);
        }

        // Remove the database entry
        $wpdb->delete(
            "{$wpdb->prefix}gf_entry_meta",
            [
                'entry_id' => $entry_id,
                'meta_key' => 'evaluation_pdf'
            ],
            ['%d', '%s']
        );

        return true;
    }

    return false;
}

function render_members_table_v2() {
    global $wpdb, $current_user;

   // Handle POST actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $entry_id = isset($_POST['entry_id']) ? intval($_POST['entry_id']) : 0;

        if (isset($_POST['upload_pdf']) && $entry_id) {
            handle_pdf_upload($entry_id);
        } elseif (isset($_POST['remove_pdf']) && $entry_id) {
            handle_pdf_removal($entry_id);
        }
    }

    // Fetch user club info
    $club_info = fetch_current_user_club_info();
    if (!$club_info) {
        echo "<p>You are not associated with any club or not logged in.</p>";
        return;
    }

    $club_id = intval($club_info->club_id);
    $logged_in_user_email = $club_info->user_email;

    // Fetch product IDs for the logged-in user's club_id
    $product_ids = $wpdb->get_col($wpdb->prepare("
        SELECT DISTINCT pm.post_id
        FROM {$wpdb->prefix}postmeta pm
        JOIN {$wpdb->prefix}posts p ON pm.post_id = p.ID
        WHERE p.post_type = 'product'
        AND pm.meta_key = '_select_club_id'
        AND pm.meta_value = %d
    ", $club_id));

    if (empty($product_ids)) {
        echo "<p>No products are associated with this club.</p>";
        return;
    }

    // Fetch users with WooCommerce memberships linked to these product IDs
    // Fetch users with WooCommerce subscriptions linked to these product IDs
// Generate a unique cache key for the logged-in user and club
$user_id = get_current_user_id();
$cache_key = "club_users_{$club_id}_{$user_id}";
$cached_user_ids = get_transient($cache_key);

if ($cached_user_ids !== false) {
    $user_ids = $cached_user_ids;
} else {
    // Define a temporary table name unique to the logged-in user
    $temp_table = "{$wpdb->prefix}temp_club_users_{$user_id}";

    // Drop the temp table if it exists (clearing previous session)
    $wpdb->query("DROP TEMPORARY TABLE IF EXISTS $temp_table");

    // Create a new temporary table
    $wpdb->query("
        CREATE TEMPORARY TABLE $temp_table (
            user_id BIGINT(20) UNSIGNED PRIMARY KEY
        )
    ");

    // Insert into temporary table
    $wpdb->query("
        INSERT INTO $temp_table (user_id)
        SELECT DISTINCT u.ID
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
        AND sub_oim.meta_value IN (" . implode(",", array_map('intval', $product_ids)) . ")
    ");

    // Retrieve user IDs from the temporary table
    $user_ids = $wpdb->get_col("SELECT user_id FROM $temp_table");

    // Store user IDs in cache for 1 hour
    set_transient($cache_key, $user_ids, 60 * 60);
}

    if (empty($user_ids)) {
        echo "<p>No users are associated with this club's memberships.</p>";
        return;
    }

    // Fetch Gravity Form ID for the club
    $gform_id = $wpdb->get_var($wpdb->prepare("
        SELECT gform_id FROM {$wpdb->prefix}clubs WHERE club_id = %d
    ", $club_id));

    if (!$gform_id) {
        echo "<p>No Gravity Form ID associated with this club.</p>";
        return;
    }

    // Query Parameters
    $search_query = sanitize_text_field($_GET['search'] ?? '');
    $month_filter = intval($_GET['month'] ?? 0);
    $status_filter = sanitize_text_field($_GET['status'] ?? '');
    $current_page = max(1, get_query_var('paged', 1));
    $items_per_page = 20;
    $offset = ($current_page - 1) * $items_per_page;

    // WHERE conditions
    $where_clauses = [
        "u.ID IN (" . implode(',', array_map('intval', $user_ids)) . ")",
        $wpdb->prepare("e.form_id = %d", $gform_id)
    ];

    if ($search_query) {
        $where_clauses[] = $wpdb->prepare("(u.display_name LIKE %s OR u.user_email LIKE %s)", "%$search_query%", "%$search_query%");
    }

    if ($month_filter) {
        $where_clauses[] = $wpdb->prepare("MONTH(e.date_created) = %d", $month_filter);
    }

    if ($status_filter === 'reviewed') {
        $where_clauses[] = "(SELECT meta_value FROM {$wpdb->prefix}gf_entry_meta WHERE entry_id = e.id AND meta_key = 'evaluation_pdf') IS NOT NULL";
    } elseif ($status_filter === 'pending') {
        $where_clauses[] = "(SELECT meta_value FROM {$wpdb->prefix}gf_entry_meta WHERE entry_id = e.id AND meta_key = 'evaluation_pdf') IS NULL";
    }

    $where_sql = implode(' AND ', $where_clauses);

    // Main Query - Order by date_created DESC to show latest first
    $query = "
    SELECT SQL_CALC_FOUND_ROWS u.ID AS user_id, u.display_name, DATE(e.date_created) AS date_created, e.id AS entry_id,
        (SELECT meta_value FROM {$wpdb->prefix}gf_entry_meta WHERE entry_id = e.id AND meta_key = 'evaluation_pdf') AS pdf_path
    FROM {$wpdb->prefix}gf_entry e
    JOIN {$wpdb->users} u ON e.created_by = u.ID
    WHERE $where_sql
    ORDER BY e.date_created DESC
    LIMIT %d OFFSET %d
    ";

    $users = $wpdb->get_results($wpdb->prepare($query, $items_per_page, $offset));

    // Get total count of entries for pagination
    $total_users = $wpdb->get_var("SELECT FOUND_ROWS()");
    $total_pages = ceil($total_users / $items_per_page);

    // Fetch counts for all, pending review, and reviewed
    $counts = [
        'all' => $wpdb->get_var("
            SELECT COUNT(*)
            FROM {$wpdb->prefix}gf_entry e
            JOIN {$wpdb->users} u ON e.created_by = u.ID
            WHERE u.ID IN (" . implode(',', array_map('intval', $user_ids)) . ")
            AND e.form_id = $gform_id
        "),
        'pending' => $wpdb->get_var("
            SELECT COUNT(*)
            FROM {$wpdb->prefix}gf_entry e
            JOIN {$wpdb->users} u ON e.created_by = u.ID
            WHERE u.ID IN (" . implode(',', array_map('intval', $user_ids)) . ")
            AND e.form_id = $gform_id
            AND (SELECT meta_value FROM {$wpdb->prefix}gf_entry_meta WHERE entry_id = e.id AND meta_key = 'evaluation_pdf') IS NULL
        "),
        'reviewed' => $wpdb->get_var("
            SELECT COUNT(*)
            FROM {$wpdb->prefix}gf_entry e
            JOIN {$wpdb->users} u ON e.created_by = u.ID
            WHERE u.ID IN (" . implode(',', array_map('intval', $user_ids)) . ")
            AND e.form_id = $gform_id
            AND (SELECT meta_value FROM {$wpdb->prefix}gf_entry_meta WHERE entry_id = e.id AND meta_key = 'evaluation_pdf') IS NOT NULL
        ")
    ];


            // Render Filters
            ?>
        <div class="status-filters count">
        <a href="<?php echo esc_url(remove_query_arg('status')); ?>" class="<?php echo empty($status_filter) ? 'current' : ''; ?>">
            All (<?php echo intval($counts['all']); ?>)
        </a> |
        <a href="<?php echo esc_url(add_query_arg('status', 'pending')); ?>" class="<?php echo $status_filter === 'pending' ? 'current' : ''; ?>">
            Pending Review (<?php echo intval($counts['pending']); ?>)
        </a> |
        <a href="<?php echo esc_url(add_query_arg('status', 'reviewed')); ?>" class="<?php echo $status_filter === 'reviewed' ? 'current' : ''; ?>">
            Reviewed (<?php echo intval($counts['reviewed']); ?>)
        </a>
    </div>

    <form method="get" id="filter-form-v2"class="end-filters">
        <!-- Preserve Section Parameter -->
        <input type="hidden" name="section" value="<?php echo isset($_GET['section']) ? esc_attr($_GET['section']) : 'default'; ?>">

        <!-- Search Field -->
        <div class="input-icon">
            <input type="text" class="my-inputs" name="search" placeholder="Search by name" value="<?php echo esc_attr($search_query); ?>">
            <span class="icon"><i class="fa fa-search"></i></span> <!-- Font Awesome Icon -->
        </div>

        <!-- Month Dropdown -->
        <select name="month">
            <option value="">Select Month</option>
            <?php for ($i = 1; $i <= 12; $i++): ?>
                <option value="<?php echo $i; ?>" <?php selected($month_filter, $i); ?>>
                    <?php echo date('F', mktime(0, 0, 0, $i, 1)); ?>
                </option>
            <?php endfor; ?>
        </select>

        <!-- Status Dropdown -->
        <select name="status">
            <option value="">Select Status</option>
            <option value="reviewed" <?php selected($status_filter, 'reviewed'); ?>>Reviewed</option>
            <option value="pending" <?php selected($status_filter, 'pending'); ?>>Pending Review</option>
        </select>

        <!-- Filter Button -->
        <button type="submit" class="my-filters All-button">Filter</button>

        <!-- Clear Filters Button -->
        <a href="<?php echo esc_url(add_query_arg('section', isset($_GET['section']) ? esc_attr($_GET['section']) : 'default', remove_query_arg(['search', 'month', 'status', 'paged']))); ?>" class="button clear-filter">Clear Filters</a>
    </form>

    <!-- Render Table -->
    <table  class="managertable" id="valuations-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Date Created</th>
                <!-- <th>User ID</th> -->
                <th>Status</th>
                <th>Evaluation</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($users)): ?>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td data-label="Name"><?php echo esc_html($user->display_name); ?></td>
                        <td data-label="Date Created"><?php echo date('d/m/Y', strtotime($user->date_created)); ?></td>
                        <!-- <td data-label="User ID"><?php echo intval($user->user_id); ?></td> -->
                        <td data-label="Status">
                            <?php 
                                $status_label = $user->pdf_path ? 'Reviewed' : 'Pending Review';
                                $status_colors = $user->pdf_path 
                                    ? ['#C6E1C6', '#5B841B'] // Green for "Reviewed"
                                    : ['#F8D7DA', '#721C24']; // Light Red for "Pending Review"
                            ?>
                            <span class="badge" style="padding: 5px 10px; border-radius: 3px; display: inline-block;
                                background: <?php echo $status_colors[0]; ?>; color: <?php echo $status_colors[1]; ?>;">
                                <?php echo esc_html($status_label); ?>
                            </span>
                        </td>

                        <td data-label="Evaluation">
                            <?php if ($user->pdf_path): ?>
                                <form method="post" style="display:inline;" enctype="multipart/form-data">
                                    <input type="hidden" name="entry_id" value="<?php echo intval($user->entry_id); ?>">
                                    <button type="submit" name="remove_pdf"class="action-item">Remove PDF</button>
                                </form>
                                <a href="<?php echo esc_url($user->pdf_path); ?>" download>Download PDF</a>
                            <?php else: ?>
                                <form method="post" enctype="multipart/form-data" style="display:inline;">
                                    <input type="hidden" name="entry_id" value="<?php echo intval($user->entry_id); ?>">
                                    <input type="file" name="evaluation_pdf" accept="application/pdf" required>
                                    <button type="submit" name="upload_pdf"class="action-item">Upload</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="5">No entries found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <div class="pagination" style="text-align: center; display: flex; flex-wrap: wrap; justify-content: center; gap: 5px;">
        <?php if ($current_page > 1): ?>
            <!-- Previous Button -->
            <a href="<?php echo esc_url(add_query_arg(['paged' => $current_page - 1, 'search' => $search_query, 'month' => $month_filter, 'status' => $status_filter])); ?>" class="prev" 
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
            <a href="<?php echo esc_url(add_query_arg(['paged' => 1, 'search' => $search_query, 'month' => $month_filter, 'status' => $status_filter])); ?>" 
            style="padding: 5px 10px; border: 1px solid #ddd; text-decoration: none; color: #333;">
                1
            </a>
            <?php if ($start_page > 2): ?>
                <span style="padding: 5px;">...</span>
            <?php endif; ?>
        <?php endif; ?>

        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
            <a href="<?php echo esc_url(add_query_arg(['paged' => $i, 'search' => $search_query, 'month' => $month_filter, 'status' => $status_filter])); ?>" 
            class="<?php echo $i === $current_page ? 'current' : ''; ?>" 
            style="padding: 5px 10px; border: 1px solid #ddd; text-decoration: none; <?php echo $i === $current_page ? 'background: #10487B; color: #fff;' : 'color: #333;'; ?>">
                <?php echo $i; ?>
            </a>
        <?php endfor; ?>

        <?php if ($end_page < $total_pages): ?>
            <?php if ($end_page < $total_pages - 1): ?>
                <span style="padding: 5px;">...</span>
            <?php endif; ?>
            <a href="<?php echo esc_url(add_query_arg(['paged' => $total_pages, 'search' => $search_query, 'month' => $month_filter, 'status' => $status_filter])); ?>" 
            style="padding: 5px 10px; border: 1px solid #ddd; text-decoration: none; color: #333;">
                <?php echo $total_pages; ?>
            </a>
        <?php endif; ?>

        <?php if ($current_page < $total_pages): ?>
            <!-- Next Button -->
            <a href="<?php echo esc_url(add_query_arg(['paged' => $current_page + 1, 'search' => $search_query, 'month' => $month_filter, 'status' => $status_filter])); ?>" class="next" 
            style="padding: 5px 10px; border: 1px solid #ddd; text-decoration: none; color: #333;">
                Next
            </a>
        <?php endif; ?>
    </div>

    <?php
}

