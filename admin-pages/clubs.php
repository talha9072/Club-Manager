<?php
// Prevent direct access to this file
if (!defined('ABSPATH')) {
    exit;
}

// Register the custom post type for clubs
function club_manager_register_club_post_type() {
    register_post_type('club', array(
        'labels' => array(
            'name' => __('Clubs', 'club-manager'),
            'singular_name' => __('Club', 'club-manager')
        ),
        'public' => true,
        'show_ui' => true,
        'supports' => array('title'),
        'capabilities' => array('delete_post' => 'delete_posts') // Enable deleting
    ));
}
add_action('init', 'club_manager_register_club_post_type');

// Display success notice if a club is deleted
if (isset($_GET['deleted']) && $_GET['deleted'] === 'true') {
    echo "<div class='notice notice-success is-dismissible'><p>" . __('Club deleted permanently.', 'club-manager') . "</p></div>";
}

// Handle single delete action
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['club_id'])) {
    global $wpdb;
    $club_id = intval($_GET['club_id']);
    $wpdb->delete("{$wpdb->prefix}clubs", ['club_id' => $club_id]); // Delete the club from the database
    wp_redirect(admin_url('admin.php?page=club-manager-clubs&post_status=trash&deleted=true')); // Redirect after deletion
    exit;
}

// Bulk action handling
if (isset($_POST['bulk_action']) && !empty($_POST['club'])) {
    $bulk_action = sanitize_text_field($_POST['bulk_action']);
    $selected_clubs = array_map('intval', $_POST['club']); // Retrieve selected club IDs and sanitize

    // Validate and perform the bulk action
    if (!empty($selected_clubs)) {
        global $wpdb;
        $club_ids_placeholder = implode(',', array_fill(0, count($selected_clubs), '%d'));

        switch ($bulk_action) {
            case 'trash':
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$wpdb->prefix}clubs SET club_status = 'trash' WHERE club_id IN ($club_ids_placeholder)",
                    $selected_clubs
                ));
                break;

            case 'delete': // Bulk delete action
                foreach ($selected_clubs as $club_id) {
                    $wpdb->delete("{$wpdb->prefix}clubs", ['club_id' => $club_id]); // Delete each club
                }
                wp_redirect(admin_url('admin.php?page=club-manager-clubs&post_status=trash&deleted=true')); // Redirect after deletion
                exit;

            case 'draft':
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$wpdb->prefix}clubs SET club_status = 'draft' WHERE club_id IN ($club_ids_placeholder)",
                    $selected_clubs
                ));
                break;

            case 'active': // New option to mark as active
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$wpdb->prefix}clubs SET club_status = 'active' WHERE club_id IN ($club_ids_placeholder)",
                    $selected_clubs
                ));
                break;

            case 'export_csv': // New action to export as CSV
                // Clean any previous output
                if (ob_get_contents()) {
                    ob_end_clean();
                }

                // Prepare SQL to retrieve club data for selected IDs
                $clubs_data = $wpdb->get_results($wpdb->prepare(
                    "SELECT c.club_name, c.club_url, 
                            COALESCE(GROUP_CONCAT(pg.gateway_type SEPARATOR ', '), 'No payment methods') AS payment_methods,
                            COALESCE(GROUP_CONCAT(m.role SEPARATOR ', '), 'No roles') AS roles
                     FROM {$wpdb->prefix}clubs c
                     LEFT JOIN {$wpdb->prefix}club_members m ON c.club_id = m.club_id
                     LEFT JOIN {$wpdb->prefix}payment_gateways pg ON c.club_id = pg.club_id
                     WHERE c.club_id IN ($club_ids_placeholder)
                     GROUP BY c.club_id",
                    $selected_clubs
                ), ARRAY_A);

                // Generate CSV file for selected clubs
                if (!empty($clubs_data)) {
                    // Set headers to force download as a CSV file
                    header('Content-Type: text/csv; charset=utf-8');
                    header('Content-Disposition: attachment; filename="selected_clubs.csv"');

                    // Open a stream to output the CSV content directly
                    $output = fopen('php://output', 'w');

                    if ($output === false) {
                        die('Error: Unable to open output stream for CSV.');
                    }

                    // Add CSV headers
                    fputcsv($output, ['Club Name', 'Home URL', 'Payment Methods', 'Roles']);

                    // Add rows for each selected club
                    foreach ($clubs_data as $club) {
                        $club_name = isset($club['club_name']) ? $club['club_name'] : 'N/A';
                        $club_url = isset($club['club_url']) ? $club['club_url'] : 'N/A';
                        $payment_methods = isset($club['payment_methods']) ? $club['payment_methods'] : 'N/A';
                        $roles = isset($club['roles']) ? $club['roles'] : 'N/A';

                        fputcsv($output, [
                            $club_name,
                            $club_url,
                            $payment_methods,
                            $roles
                        ]);
                    }

                    fclose($output);
                    exit;
                }
                break;

            default:
                // Do nothing if no valid action
                break;
        }
    }
}

// Count the clubs by status using SQL
global $wpdb;
$counts = $wpdb->get_results("
    SELECT club_status, COUNT(*) as count 
    FROM {$wpdb->prefix}clubs
    GROUP BY club_status
", OBJECT_K);

// Assign counts to variables for easy access
$count_all = (isset($counts['active']) ? $counts['active']->count : 0) + (isset($counts['draft']) ? $counts['draft']->count : 0);
$count_active = isset($counts['active']) ? $counts['active']->count : 0;
$count_draft = isset($counts['draft']) ? $counts['draft']->count : 0;
$count_bin = isset($counts['trash']) ? $counts['trash']->count : 0;

// Retrieve filter values from GET parameters
$filter_role = isset($_GET['filter_role']) ? sanitize_text_field($_GET['filter_role']) : '';
$filter_payment = isset($_GET['filter_payment']) ? sanitize_text_field($_GET['filter_payment']) : '';
$search_query = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
$status_filter = isset($_GET['post_status']) ? sanitize_text_field($_GET['post_status']) : 'all';
?>

<!-- CSS to improve layout -->
<style>
    .filter-container {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        margin-bottom: 15px;
        position: relative;
        top: 50px;
        
    }
    .filter-container .alignleft, .filter-container .alignright {
        margin-top: 10px;
    }
    .filter-container form{
        margin-left: -30px;
    }
    .filter-container .alignright{
        position: absolute;
        top: -52px;
        right: 60px;
    }

    .filter-container .alignleft {
        display: flex;
        
    }
    .filter-container select, .filter-container input[type="search"] {
        width: 220px;
    }
    #clubs-table th, #clubs-table td {
        white-space: wrap;
    }
    #clubs-table {
        margin-top: 30px;
    }
    
</style>




<!-- HTML -->
<div class="wrap">
    <h1><?php echo __('Clubs', 'club-manager'); ?> 
        <a href="<?php echo admin_url('admin.php?page=club-manager-edit-club'); ?>" class="button-primary"><?php echo __('Add Club', 'club-manager'); ?></a>
    </h1>

    <!-- Filter Links -->
    <ul class="subsubsub">
        <li><a href="<?php echo add_query_arg('post_status', 'all', admin_url('admin.php?page=club-manager-clubs')); ?>" class="<?php echo ($status_filter === 'all') ? 'current' : ''; ?>"><?php echo __('All', 'club-manager'); ?> <span class="count">(<?php echo esc_html($count_all); ?>)</span></a> | </li>
        <li><a href="<?php echo add_query_arg('post_status', 'active', admin_url('admin.php?page=club-manager-clubs')); ?>" class="<?php echo ($status_filter === 'active') ? 'current' : ''; ?>"><?php echo __('Active', 'club-manager'); ?> <span class="count">(<?php echo esc_html($count_active); ?>)</span></a> | </li>
        <li><a href="<?php echo add_query_arg('post_status', 'draft', admin_url('admin.php?page=club-manager-clubs')); ?>" class="<?php echo ($status_filter === 'draft') ? 'current' : ''; ?>"><?php echo __('Draft', 'club-manager'); ?> <span class="count">(<?php echo esc_html($count_draft); ?>)</span></a> | </li>
        <li><a href="<?php echo add_query_arg('post_status', 'trash', admin_url('admin.php?page=club-manager-clubs')); ?>" class="<?php echo ($status_filter === 'trash') ? 'current' : ''; ?>"><?php echo __('Bin', 'club-manager'); ?> <span class="count">(<?php echo esc_html($count_bin); ?>)</span></a></li>
    </ul>

    <!-- New row for filters and search -->
    <div class="filter-container">
        <!-- Filter Controls -->
        <form method="get">
            <input type="hidden" name="page" value="club-manager-clubs" />
            <input type="hidden" name="post_status" value="<?php echo esc_attr($status_filter); ?>" />

            <div class="alignleft actions">
                <select name="filter_role">
                    <option value=""><?php echo __('Filter by Role', 'club-manager'); ?></option>
                    <option value="Club Manager"<?php selected($filter_role, 'Club Manager'); ?>><?php echo __('Club Manager', 'club-manager'); ?></option>
                    <option value="Treasurer"<?php selected($filter_role, 'Treasurer'); ?>><?php echo __('Treasurer', 'club-manager'); ?></option>
                    <option value="Media/Social"<?php selected($filter_role, 'Media/Social'); ?>><?php echo __('Media/Social', 'club-manager'); ?></option>
                    <option value="Store Manager"<?php selected($filter_role, 'Store Manager'); ?>><?php echo __('Store Manager', 'club-manager'); ?></option>
                </select>

                <select name="filter_payment">
                    <option value=""><?php echo __('Filter by Payment Method', 'club-manager'); ?></option>
                    <option value="EFT"<?php selected($filter_payment, 'EFT'); ?>><?php echo __('EFT', 'club-manager'); ?></option>
                    <option value="Yoco"<?php selected($filter_payment, 'Yoco'); ?>><?php echo __('Yoco', 'club-manager'); ?></option>
                    <option value="PayFast"<?php selected($filter_payment, 'PayFast'); ?>><?php echo __('PayFast', 'club-manager'); ?></option>
                    <option value="Stripe"<?php selected($filter_payment, 'Stripe'); ?>><?php echo __('Stripe', 'club-manager'); ?></option>
                    <option value="Both"<?php selected($filter_payment, 'Both'); ?>><?php echo __('Both', 'club-manager'); ?></option>
                </select>

                <button type="submit" class="button"><?php echo __('Filter', 'club-manager'); ?></button>
            </div>

            <div class="alignright">
                <input type="search" name="s" value="<?php echo esc_attr($search_query); ?>" placeholder="<?php echo __('Search clubs', 'club-manager'); ?>" />
                <button type="submit" class="button"><?php echo __('Search', 'club-manager'); ?></button>
            </div>
        </form>
    </div>

    <!-- Bulk Action Form -->
    <div class="tablenav top">
        <form method="post" action="">
        <div class="alignleft actions bulkactions" style="padding-bottom: 20px;">
                <select name="bulk_action">
                    <option value=""><?php echo __('Bulk Actions', 'club-manager'); ?></option>
                    <option value="trash"><?php echo __('Move to Trash', 'club-manager'); ?></option>
                    <option value="draft"><?php echo __('Mark as Draft', 'club-manager'); ?></option>
                    <option value="active"><?php echo __('Mark as Active', 'club-manager'); ?></option> <!-- New option for marking clubs as active -->
                    <option value="export_csv"><?php echo __('Export as CSV', 'club-manager'); ?></option>

                </select>
                <button type="submit" class="button action"><?php echo __('Apply', 'club-manager'); ?></button>
            </div>

            <!-- Clubs Table -->
            <table class="wp-list-table widefat fixed striped posts" id="clubs-table">
                <thead>
                    <tr>
                        <th class="manage-column column-cb check-column"><input type="checkbox" /></th>
                        <th><?php echo __('Club Name', 'club-manager'); ?></th>
                        <th><?php echo __('Home URL', 'club-manager'); ?></th>
                        <th><?php echo __('Payment Methods', 'club-manager'); ?></th>
                        <th><?php echo __('Membership Products', 'club-manager'); ?></th>

                    </tr>
                </thead>
                <tbody>
    <?php
    // Prepare SQL query to retrieve clubs, roles, and payment methods from the custom tables
    $sql = "
    SELECT 
        c.club_id, 
        c.club_name, 
        c.club_url, 
        c.club_status, 
        COALESCE(
            NULLIF(
                CONCAT(
                    CASE 
                        WHEN EXISTS (
                            SELECT 1 FROM {$wpdb->prefix}payment_gateways 
                            WHERE club_id = c.club_id 
                            AND yoco_link IS NOT NULL 
                            AND yoco_link != ''
                        ) THEN 'Yoco Payment Link' 
                        ELSE ''
                    END,
                    CASE 
                        WHEN e.club_id IS NOT NULL AND EXISTS (
                            SELECT 1 FROM {$wpdb->prefix}payment_gateways 
                            WHERE club_id = c.club_id 
                            AND yoco_link IS NOT NULL 
                            AND yoco_link != ''
                        ) THEN ', EFT'
                        WHEN e.club_id IS NOT NULL THEN 'EFT'
                        ELSE ''
                    END
                ), ''
            ), 'No payment methods'
        ) AS payment_method,
        COALESCE(GROUP_CONCAT(DISTINCT m.role SEPARATOR ', '), 'No roles') AS roles,
        (
            SELECT COUNT(*)
            FROM {$wpdb->prefix}posts p
            INNER JOIN {$wpdb->prefix}postmeta pm1 ON p.ID = pm1.post_id
            INNER JOIN {$wpdb->prefix}postmeta pm2 ON p.ID = pm2.post_id
            WHERE pm1.meta_key = '_select_club_id'
            AND pm2.meta_key = '_select_club_name'
            AND pm1.meta_value = c.club_id
            AND p.post_type = 'product'
            AND p.post_status = 'publish'
            AND EXISTS (
                SELECT 1
                FROM {$wpdb->prefix}term_relationships tr
                INNER JOIN {$wpdb->prefix}term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                INNER JOIN {$wpdb->prefix}terms t ON tt.term_id = t.term_id
                WHERE tr.object_id = p.ID
                AND tt.taxonomy = 'product_cat'
                AND t.slug = 'membership'
            )
        ) AS membership_count
    FROM 
        {$wpdb->prefix}clubs c
    LEFT JOIN 
        {$wpdb->prefix}club_members m ON c.club_id = m.club_id
    LEFT JOIN 
        {$wpdb->prefix}eft_details e ON c.club_id = e.club_id
    WHERE 
        1=1
";




    // Apply status filter if necessary
    if ($status_filter === 'active') {
        $sql .= " AND c.club_status = 'active'";
    } elseif ($status_filter === 'draft') {
        $sql .= " AND c.club_status = 'draft'";
    } elseif ($status_filter === 'all') {
        $sql .= " AND c.club_status IN ('active', 'draft')";
    } elseif ($status_filter === 'trash') {
        $sql .= " AND c.club_status = 'trash'";
    }

    // Add conditions based on filters
    if (!empty($filter_role)) {
        $sql .= $wpdb->prepare(" AND m.role = %s", $filter_role);
    }
    if ($filter_payment !== '') {
        $sql .= $wpdb->prepare(" AND pg.gateway_type = %s", $filter_payment);
    }
    if (!empty($search_query)) {
        $sql .= $wpdb->prepare(" AND c.club_name LIKE %s", '%' . $wpdb->esc_like($search_query) . '%');
    }

    // Group by club to handle aggregate roles
    $sql .= " GROUP BY c.club_id";

    // Execute the SQL query
    $clubs = $wpdb->get_results($sql);

    // Output the results in the table
    foreach ($clubs as $club) {
        $edit_link = admin_url('admin.php?page=club-manager-clubs&action=edit&club_id=' . $club->club_id);
        $status_label = ($club->club_status === 'draft') ? ' — Draft' : '';
        echo "<tr>
                <th scope='row' class='check-column'><input type='checkbox' name='club[]' value='{$club->club_id}' /></th>
                <td>
                    <strong>
                        <a class='row-title' href='{$edit_link}'>" . esc_html($club->club_name) . "</a>{$status_label}
                    </strong>
                    <div class='row-actions'>
                        <span class='edit'>
                            <a href='{$edit_link}'>" . __('Edit', 'club-manager') . "</a>
                        </span>";
        if ($status_filter === 'trash') {
            $delete_link = admin_url('admin.php?page=club-manager-clubs&action=delete&club_id=' . $club->club_id);
            $confirm_message = __('Are you sure you want to delete this club permanently?', 'club-manager');
            $delete_label = __('Delete Permanently', 'club-manager');
            echo "<span class='delete'>
                    <a href='{$delete_link}' onclick=\"return confirm('{$confirm_message}');\">{$delete_label}</a>
                  </span>";
        }
        echo "</div>
                </td>
                <td>{$club->club_url}</td>
                <td>{$club->payment_method}</td>
                <td>{$club->membership_count}</td> <!-- New column added -->
            </tr>";
    }
    
    
    
    ?>
                </tbody>
            </table>
        </form>
    </div>
</div>




