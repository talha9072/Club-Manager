<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}


if (isset($_GET['event_id']) && !empty($_GET['event_id'])) {
    // If 'event_id' exists in the URL, show the edit event form
    edit_event_details();
} elseif (isset($_GET['add_event']) && $_GET['add_event'] === 'true') {
    // If 'add_event' exists and is set to 'true', show the add event form
    echo '<h2 class="manager-h2">Add Events</h2>';
    render_add_event_form();
} else {
    // Fetch filters from GET parameters
    $filters = [
        'filter_month' => sanitize_text_field($_GET['filter_month'] ?? ''),
        'filter_status' => sanitize_text_field($_GET['filter_status'] ?? ''),
        'search_query' => sanitize_text_field($_GET['search_query'] ?? ''),
        'filter_category' => sanitize_text_field($_GET['filter_category'] ?? ''), // Added category filter
    ];

    // Fetch pagination data
    $pagination = [
        'paged' => max(1, intval($_GET['paged'] ?? 1)), // Ensure valid page number
        'events_per_page' => 20,
    ];

    

    // Fetch events using optimized query
    $events_data = fetch_events_for_club($filters, $pagination);

    // Render the events table with the retrieved data
    render_events_table($user_club_name, $events_data, $filters, $pagination);
}



function update_past_events_to_completed() {
    global $wpdb;

    // Check the last run timestamp
    $last_run = get_option('last_event_update_timestamp');
    $today_unix = strtotime(date('Y-m-d'));

    // Only run if it hasn't run today
    if ($last_run && $last_run >= $today_unix) {
        return; // Already updated today, no need to run again
    }

    // Fetch events that are past their end date and either missing a status or not completed
    $events_to_update = $wpdb->get_results("
        SELECT p.ID, meta_end.meta_value AS end_date, meta_status.meta_value AS status
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} meta_end ON p.ID = meta_end.post_id AND meta_end.meta_key = 'evcal_erow'
        LEFT JOIN {$wpdb->postmeta} meta_status ON p.ID = meta_status.post_id AND meta_status.meta_key = '_status'
        WHERE p.post_type = 'ajde_events'
        AND p.post_status IN ('publish', 'draft', 'pending')
        AND meta_end.meta_value IS NOT NULL
    ");

    if (!empty($events_to_update)) {
        foreach ($events_to_update as $event) {
            $end_date = (int) $event->end_date;
            $status = $event->status;

            // If event is past the end date
            if ($end_date < $today_unix) {
                // If status is missing or not completed, set it to completed
                if (empty($status) || $status !== 'completed') {
                    update_post_meta($event->ID, '_status', 'completed');
                }
            } else {
                // If the event is upcoming and missing status, set to upcoming
                if (empty($status)) {
                    update_post_meta($event->ID, '_status', 'scheduled');
                }
            }
        }
    }

    // Update the last run timestamp to today
    update_option('last_event_update_timestamp', $today_unix);
}



// Run the function
update_past_events_to_completed();


function get_event_categories_for_logged_in_user() {
    global $wpdb, $current_user;

    // Get the current logged-in user's email
    wp_get_current_user();
    $user_email = $current_user->user_email;

    if (empty($user_email)) {
        return ['error' => 'No logged-in user found.'];
    }

    // Fetch the club ID of the logged-in user
    $user_club = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT club_id FROM {$wpdb->prefix}club_members WHERE user_email = %s LIMIT 1",
            $user_email
        ),
        ARRAY_A
    );

    if (!$user_club || empty($user_club['club_id'])) {
        return ['error' => 'User is not associated with any club.'];
    }

    $club_id = $user_club['club_id'];

    // Fetch event categories that match the user's club ID
    $categories = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT t.term_id, t.name, tm.meta_value 
             FROM {$wpdb->terms} t
             INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
             INNER JOIN {$wpdb->termmeta} tm ON t.term_id = tm.term_id
             WHERE tt.taxonomy = 'event_type' 
             AND tm.meta_key = 'taxonomy_custom_dropdown'
             AND tm.meta_value = %d",
            $club_id
        ),
        ARRAY_A
    );

    return !empty($categories) ? $categories : ['error' => 'No event categories found for this club.'];
}



function fetch_events_for_club($filters = [], $pagination = []) {
    global $wpdb, $wp_query;

    $user_id = get_current_user_id();
    if (!$user_id) return ['events' => [], 'total_events' => 0, 'total_pages' => 0, 'current_page' => 1];

    $temp_table = "{$wpdb->prefix}temp_events_{$user_id}";

    // Check if the temporary table exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$temp_table}'");

    if (!$table_exists) {
        // Create a temporary table to store results
        $wpdb->query("CREATE TEMPORARY TABLE {$temp_table} (
            event_id BIGINT PRIMARY KEY, 
            event_name TEXT, 
            start_date BIGINT, 
            end_date BIGINT, 
            status TEXT, 
            tickets TEXT, 
            category_name TEXT, 
            organizer_name TEXT, 
            location_name TEXT
        )");

        // Get club name from URL or fallback to logged-in user's club
        $user_club_name = isset($_GET['club']) ? sanitize_text_field($_GET['club']) : '';

        if (empty($user_club_name)) {
            $user_email = wp_get_current_user()->user_email;
            if (empty($user_email)) {
                error_log("No user logged in.");
                return ['events' => [], 'total_events' => 0, 'total_pages' => 0, 'current_page' => 1];
            }

            $club_data = $wpdb->get_var(
                $wpdb->prepare("SELECT club_name FROM {$wpdb->prefix}club_members WHERE user_email = %s LIMIT 1", $user_email)
            );

            if (!$club_data) {
                error_log("No club found for user: $user_email");
                return ['events' => [], 'total_events' => 0, 'total_pages' => 0, 'current_page' => 1];
            }

            $user_club_name = $club_data;
        }

        // Extract filters
        $filter_month = $filters['filter_month'] ?? '';
        $filter_status = isset($_GET['count_filter']) ? sanitize_text_field($_GET['count_filter']) : ($filters['filter_status'] ?? '');
        $search_query = $filters['search_query'] ?? '';
        $filter_category = $filters['filter_category'] ?? '';

        // Prepare query conditions
        $query_conditions = "WHERE pm.meta_key = '_select_club_name' AND pm.meta_value = %s AND p.post_type = 'ajde_events'";
        $query_params = [$user_club_name];

        if (!empty($filter_month)) {
            $query_conditions .= " AND DATE_FORMAT(FROM_UNIXTIME(meta_start.meta_value), '%Y-%m') = %s";
            $query_params[] = $filter_month;
        }

        if (!empty($filter_status)) {
            $query_conditions .= " AND meta_status.meta_value = %s";
            $query_params[] = $filter_status;
        }

        if (!empty($search_query)) {
            $query_conditions .= " AND p.post_title LIKE %s";
            $query_params[] = '%' . $search_query . '%';
        }

        if (!empty($filter_category)) {
            $query_conditions .= " AND tt_cat.taxonomy = 'event_type' AND t_cat.term_id = %d";
            $query_params[] = $filter_category;
        }

        // Insert filtered results into the temporary table
        $query = $wpdb->prepare(
            "INSERT INTO {$temp_table} 
                SELECT p.ID AS event_id, p.post_title AS event_name, 
                    COALESCE(MAX(meta_start.meta_value), 0) AS start_date, 
                    COALESCE(MAX(meta_end.meta_value), 0) AS end_date,
                    COALESCE(MAX(meta_status.meta_value), 'N/A') AS status,
                    COALESCE(MAX(tix.meta_value), 'No') AS tickets,
                    GROUP_CONCAT(DISTINCT t_cat.name ORDER BY t_cat.name ASC SEPARATOR ', ') AS category_name,
                    GROUP_CONCAT(DISTINCT t_org.name ORDER BY t_org.name ASC SEPARATOR ', ') AS organizer_name,
                    GROUP_CONCAT(DISTINCT t_loc.name ORDER BY t_loc.name ASC SEPARATOR ', ') AS location_name
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                LEFT JOIN {$wpdb->postmeta} meta_start ON p.ID = meta_start.post_id AND meta_start.meta_key = 'evcal_srow'
                LEFT JOIN {$wpdb->postmeta} meta_end ON p.ID = meta_end.post_id AND meta_end.meta_key = 'evcal_erow'
                LEFT JOIN {$wpdb->postmeta} meta_status ON p.ID = meta_status.post_id AND meta_status.meta_key = '_status'
                LEFT JOIN {$wpdb->postmeta} tix ON p.ID = tix.post_id AND tix.meta_key = 'evotx_tix'
                LEFT JOIN {$wpdb->term_relationships} tr_cat ON p.ID = tr_cat.object_id
                LEFT JOIN {$wpdb->term_taxonomy} tt_cat ON tr_cat.term_taxonomy_id = tt_cat.term_taxonomy_id AND tt_cat.taxonomy = 'event_type'
                LEFT JOIN {$wpdb->terms} t_cat ON tt_cat.term_id = t_cat.term_id
                LEFT JOIN {$wpdb->term_relationships} tr_org ON p.ID = tr_org.object_id
                LEFT JOIN {$wpdb->term_taxonomy} tt_org ON tr_org.term_taxonomy_id = tt_org.term_taxonomy_id AND tt_org.taxonomy = 'event_organizer'
                LEFT JOIN {$wpdb->terms} t_org ON tt_org.term_id = t_org.term_id
                LEFT JOIN {$wpdb->term_relationships} tr_loc ON p.ID = tr_loc.object_id
                LEFT JOIN {$wpdb->term_taxonomy} tt_loc ON tr_loc.term_taxonomy_id = tt_loc.term_taxonomy_id AND tt_loc.taxonomy = 'event_location'
                LEFT JOIN {$wpdb->terms} t_loc ON tt_loc.term_id = t_loc.term_id
                $query_conditions
                GROUP BY p.ID",
            $query_params
        );

        $wpdb->query($query);
    }

    // Fetch paginated events from the temporary table
    $current_page = max(1, get_query_var('paged') ? get_query_var('paged') : (isset($_GET['paged']) ? intval($_GET['paged']) : 1));
    $events_per_page = max(10, intval($pagination['events_per_page'] ?? 20));
    $offset = ($current_page - 1) * $events_per_page;

    $events = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$temp_table} ORDER BY start_date DESC LIMIT %d OFFSET %d", $events_per_page, $offset));

    // Count total events
    $total_events = $wpdb->get_var("SELECT COUNT(*) FROM {$temp_table}");

    return [
        'events' => $events ?: [],
        'total_events' => (int) $total_events,
        'total_pages' => max(1, ceil($total_events / $events_per_page)),
        'current_page' => $current_page
    ];
}





function render_pagination($current_page, $total_pages) {
    if ($total_pages <= 1) return;

    echo '<div class="pagination" style="margin-top: 20px; text-align: center;">';

    // Previous Button
    if ($current_page > 1) {
        echo '<a href="' . esc_url(add_query_arg('paged', $current_page - 1)) . '" 
              style="margin: 0 5px; padding: 5px 10px; border: 1px solid #ddd; text-decoration: none; color: #333;">
              Previous
              </a>';
    }

    // Page Numbers
    for ($i = 1; $i <= $total_pages; $i++) {
        echo '<a href="' . esc_url(add_query_arg('paged', $i)) . '" 
              class="' . ($i == $current_page ? 'current' : '') . '" 
              style="margin: 0 5px; padding: 5px 10px; border: 1px solid #ddd; text-decoration: none; 
              ' . ($i == $current_page ? 'background: #10487B; color: #fff;' : 'color: #333;') . '">
              ' . $i . '
              </a>';
    }

    // Next Button
    if ($current_page < $total_pages) {
        echo '<a href="' . esc_url(add_query_arg('paged', $current_page + 1)) . '" 
              style="margin: 0 5px; padding: 5px 10px; border: 1px solid #ddd; text-decoration: none; color: #333;">
              Next
              </a>';
    }

    echo '</div>';
}

function get_event_count($status) {
    global $wpdb;
    $user_id = get_current_user_id();
    $temp_table = "{$wpdb->prefix}temp_events_{$user_id}";

    if ($status === 'all') {
        return $wpdb->get_var("SELECT COUNT(*) FROM {$temp_table}");
    } else {
        return $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$temp_table} WHERE status = %s", $status));
    }
}


// Function to render events table

function render_events_table($user_club_name, $events_data, $filters = [], $pagination = []) {
    // Extract pagination data
    $current_page = $events_data['current_page'] ?? 1;
    $total_pages = $events_data['total_pages'] ?? 1;

    // Events data
    $events = $events_data['events'];

    ?>
    <div class="event-list">
        <!-- Advanced Button -->
       <div class="admin-switch">
       <h2 class="manager-h2">Events</h2>
        <a href="<?php echo esc_url(admin_url('edit.php?post_type=ajde_events')); ?>" 
           class="button All-button">
           Advanced
        </a>
       </div>

        
        <!-- Count Filter Buttons -->
    <div class="event-count-filters count" style="margin-bottom: 15px;">
        <a href="<?php echo esc_url(remove_query_arg(['count_filter'], add_query_arg('section', 'events'))); ?>" 
        class="button <?php echo empty($_GET['count_filter']) ? 'active' : ''; ?>"
        style="margin-right: 10px;">All (<?php echo esc_html(get_event_count('all')); ?>)</a>
        |
        <a href="<?php echo esc_url(add_query_arg('count_filter', 'scheduled')); ?>" 
        class="button <?php echo ($_GET['count_filter'] ?? '') === 'scheduled' ? 'active' : ''; ?>"
        style="margin-right: 10px;">Scheduled (<?php echo esc_html(get_event_count('scheduled')); ?>)</a>
        |
        <a href="<?php echo esc_url(add_query_arg('count_filter', 'completed')); ?>" 
        class="button <?php echo ($_GET['count_filter'] ?? '') === 'completed' ? 'active' : ''; ?>">
        Completed (<?php echo esc_html(get_event_count('completed')); ?>)
        </a>
    </div>


        <button onclick="window.location.href='?section=events&add_event=true'" 
                style="border: none; cursor: pointer;"class="All-button add-event-button">
            Add Event
        </button>

        <!-- Filters -->
        <form method="get" class="filters end-filters">
            <input type="hidden" name="section" value="events">
            
            <input type="month" id="filter_month" name="filter_month" value="<?php echo esc_attr($filters['filter_month'] ?? ''); ?>">

            <select id="filter_status" name="filter_status">
                <option value="">Status</option>
                <option value="scheduled" <?php selected($filters['filter_status'] ?? '', 'scheduled'); ?>>Scheduled</option>
                <option value="completed" <?php selected($filters['filter_status'] ?? '', 'completed'); ?>>Completed</option>
            </select>

            <?php 
                // Fetch event categories
                $event_categories = get_event_categories_for_logged_in_user();
            ?>
            <select id="filter_category" name="filter_category">
                <option value="">All Categories</option>
                <?php if (!isset($event_categories['error'])) : ?>
                    <?php foreach ($event_categories as $category) : ?>
                        <option value="<?php echo esc_attr($category['term_id']); ?>" <?php selected($filters['filter_category'] ?? '', $category['term_id']); ?>>
                            <?php echo esc_html($category['name']); ?>
                        </option>
                    <?php endforeach; ?>
                <?php else : ?>
                    <option value="" disabled><?php echo esc_html($event_categories['error']); ?></option>
                <?php endif; ?>
            </select>

            <input type="text" id="search_query" name="search_query" value="<?php echo esc_attr($filters['search_query'] ?? ''); ?>" placeholder="Search by event name">

            <button type="submit" class="my-filters All-button">Apply Filters</button>
            <a href="<?php echo esc_url(remove_query_arg(['filter_month', 'filter_status', 'search_query', 'paged'], add_query_arg('section', 'events'))); ?>" class="button clear-filter">Clear Filters</a>
        </form>

        <!-- Bulk Actions -->
        <form method="post" class="bulk-actions" id="bulk-actions-form">
            <div class="bulk-users end-filters">
                <select name="bulk_action">
                    <option value="">Bulk Actions</option>
                    <option value="delete">Delete</option>
                    <option value="export">Export as CSV</option>
                </select>
                <button type="submit" class="my-filters All-button">Apply</button>
            </div>

            <table class="wp-list-table widefat fixed striped managertable" id="events-table">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="select-all" onclick="toggleAllCheckboxes(this)"></th>
                        <th>Event Name</th>
                        <th>Location</th>
                        <th>Organizer</th>
                        <th>Category</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Tickets</th>
                        <th>Status</th>
                        <th>Download</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($events)) : ?>
                        <?php foreach ($events as $event) : ?>
                            <tr>
                                <td><input type="checkbox" name="selected_events[]" value="<?php echo esc_attr($event->event_id); ?>" class="event-checkbox"></td>
                                <td data-label="Event Name"><a href="?event_id=<?php echo esc_attr($event->event_id); ?>"><?php echo esc_html($event->event_name); ?></a></td>
                                <td data-label="Location"><?php echo esc_html($event->location_name ?: 'N/A'); ?></td>
                                <td data-label="Organizer"><?php echo esc_html($event->organizer_name ?: 'N/A'); ?></td>
                                <td data-label="Category"><?php echo esc_html($event->category_name ?: 'N/A'); ?></td>
                                <td data-label="Start Date"><?php echo esc_html(date('d/m/Y', $event->start_date)); ?></td>
                                <td data-label="End Date"><?php echo esc_html(date('d/m/Y', $event->end_date)); ?></td>
                                <td data-label="Tickets"><?php echo (strtolower($event->tickets) === 'no') ? 'No' : '<i class="fa fa-check-circle" style="color: #10487B; font-size: 28px;"></i>'; ?></td>
                                <?php
                                // Define the status map for badges
                                $status_map = [
                                    'scheduled' => ['Scheduled', '#C6E1C6', '#5B841B'],   // Green
                                    'completed' => ['Completed', '#F8D7DA', '#721C24'],   // Light Red
                                    'canceled'  => ['Canceled', '#E0E0E0', '#777'],       // Gray
                                    'pending'   => ['Pending', '#FCE58B', '#946C00'],     // Yellow
                                ];
                                ?>

                                <td data-label="Status">
                                    <?php 
                                        $status = strtolower($event->status); // Ensure lowercase for matching
                                        $badge = $status_map[$status] ?? ['Unknown', '#E0E0E0', '#000']; // Default to gray if not found
                                    ?>
                                    <span class="badge" style="
                                        display: inline-block; 
                                        padding: 5px 10px; 
                                        
                                        
                                        color: <?php echo $badge[2]; ?>; 
                                        background-color: <?php echo $badge[1]; ?>;">
                                        <?php echo esc_html($badge[0]); ?>
                                    </span>
                                </td>

                                <td data-label="Download">
                                    <?php if (strtolower($event->tickets) !== 'no') : ?>
                                        <a href="#" class="download-csv" data-event-id="<?php echo esc_attr($event->event_id); ?>">
                                            <i class="fa fa-download" style="color: #10487B; font-size: 24px;"></i>
                                        </a>
                                    <?php else : ?>
                                        NA
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr><td colspan="10">No events found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </form>

        <!-- Pagination -->
        <?php render_pagination($current_page, $total_pages); ?>

    </div>

    <script>
        function toggleAllCheckboxes(selectAllCheckbox) {
            document.querySelectorAll('.event-checkbox').forEach(function(checkbox) {
                checkbox.checked = selectAllCheckbox.checked;
            });
        }

        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.download-csv').forEach(function(link) {
                link.addEventListener('click', function(event) {
                    event.preventDefault();
                    event.stopImmediatePropagation();
                    const eventId = this.getAttribute('data-event-id');
                    const downloadUrl = `/wp-admin/admin-ajax.php?action=the_ajax_evotx_a3&e_id=${eventId}&pid=${eventId}`;
                    const tempLink = document.createElement('a');
                    tempLink.href = downloadUrl;
                    tempLink.setAttribute('download', `event_${eventId}_attendees.csv`);
                    document.body.appendChild(tempLink);
                    tempLink.click();
                    document.body.removeChild(tempLink);
                });
            });
        });
    </script>
    <?php
}






// Main logic
$current_user = wp_get_current_user();
$user_email = $current_user->user_email;

global $wpdb;
$user_club = $wpdb->get_row(
    $wpdb->prepare("SELECT club_id, club_name FROM wp_club_members WHERE user_email = %s", $user_email),
    ARRAY_A
);


if ($user_club) {
    // Process bulk actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
        $bulk_action = sanitize_text_field($_POST['bulk_action']);
        $selected_events = isset($_POST['selected_events']) ? array_map('intval', $_POST['selected_events']) : [];

        if (!empty($selected_events)) {
            if ($bulk_action === 'delete') {
                // Handle bulk delete
                foreach ($selected_events as $event_id) {
                    wp_delete_post($event_id, true);
                }
                echo '<div class="notice notice-success"><p>Selected events deleted successfully.</p></div>';
            } elseif ($bulk_action === 'export') {
                // Handle export as CSV
                export_events_to_csv($selected_events);
                exit;
            }
        } else {
            echo '<div class="notice notice-error"><p>No events selected for the bulk action.</p></div>';
        }
    }

    
} else {
    echo 'User is not associated with any club.';
}

// Function to export selected events to CSV
function export_events_to_csv($event_ids) {
    if (empty($event_ids)) {
        return;
    }

    global $wpdb;

    // Prepare placeholders for the IN clause
    $placeholders = implode(',', array_fill(0, count($event_ids), '%d'));

    // Fetch the events with additional details
    $events = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT p.ID AS event_id, p.post_title AS event_name, 
                    meta_start.meta_value AS start_date, 
                    meta_end.meta_value AS end_date,
                    meta_status.meta_value AS status,
                    tix.meta_value AS tickets
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} meta_start ON p.ID = meta_start.post_id AND meta_start.meta_key = 'evcal_srow'
             LEFT JOIN {$wpdb->postmeta} meta_end ON p.ID = meta_end.post_id AND meta_end.meta_key = 'evcal_erow'
             LEFT JOIN {$wpdb->postmeta} meta_status ON p.ID = meta_status.post_id AND meta_status.meta_key = '_status'
             LEFT JOIN {$wpdb->postmeta} tix ON p.ID = tix.post_id AND tix.meta_key = 'evotx_tix'
             WHERE p.ID IN ($placeholders) AND p.post_type = 'ajde_events' AND p.post_status = 'publish'",
            $event_ids
        )
    );

    // Step 1: Kill all previous output and buffers
    while (ob_get_level()) {
        ob_end_clean();
    }

    // Step 2: Disable WordPress hooks
    remove_all_actions('shutdown');
    remove_all_actions('wp_footer');
    remove_all_actions('wp_head');

    // Step 3: Set CSV headers
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="events_export.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Step 4: Open CSV output
    $output = fopen('php://output', 'w');
    if (!$output) {
        exit('Unable to open output stream.');
    }

    // Step 5: Write CSV headers
    fputcsv($output, [
        'Event ID', 
        'Event Name', 
        'Category', 
        'Organizer', 
        'Location', 
        'Start Date', 
        'End Date', 
        'Status', 
        'Tickets'
    ]);

    // Step 6: Fetch and add additional event details (Organizer, Location, Category)
    foreach ($events as $event) {
        // Fetch event categories
        $event_categories = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT t.name 
                 FROM {$wpdb->terms} t
                 INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
                 INNER JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
                 WHERE tr.object_id = %d AND tt.taxonomy = 'event_type'",
                $event->event_id
            )
        );

        $category_name = !empty($event_categories) ? implode(', ', wp_list_pluck($event_categories, 'name')) : 'N/A';

        // Fetch event organizer
        $event_organizer = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT t.name 
                 FROM {$wpdb->terms} t
                 INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
                 INNER JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
                 WHERE tr.object_id = %d AND tt.taxonomy = 'event_organizer'
                 LIMIT 1",
                $event->event_id
            )
        );

        $organizer_name = $event_organizer ?? 'N/A';

        // Fetch event location
        $event_location = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT t.name 
                 FROM {$wpdb->terms} t
                 INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
                 INNER JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
                 WHERE tr.object_id = %d AND tt.taxonomy = 'event_location'
                 LIMIT 1",
                $event->event_id
            )
        );

        $location_name = $event_location ?? 'N/A';

        // Format dates
        $start_date = $event->start_date ? date('Y-m-d H:i:s', intval($event->start_date)) : 'N/A';
        $end_date = $event->end_date ? date('Y-m-d H:i:s', intval($event->end_date)) : 'N/A';

        // Write row to CSV
        fputcsv($output, [
            $event->event_id,
            sanitize_text_field($event->event_name),
            sanitize_text_field($category_name),
            sanitize_text_field($organizer_name),
            sanitize_text_field($location_name),
            $start_date,
            $end_date,
            sanitize_text_field($event->status ?? 'N/A'),
            ($event->tickets && strtolower($event->tickets) !== 'no') ? 'Yes' : 'No'
        ]);
    }

    // Step 7: Close the output and exit
    fclose($output);
    exit;
}


function get_event_form_id_by_club() {
    global $wpdb, $current_user;

    // Get the logged-in user's details
    wp_get_current_user();
    $user_email = $current_user->user_email;

    if (empty($user_email)) {
        return [];
    }

    // Fetch the user's club ID
    $user_club = $wpdb->get_row(
        $wpdb->prepare("SELECT club_id FROM {$wpdb->prefix}club_members WHERE user_email = %s", $user_email),
        ARRAY_A
    );

    if (!$user_club || empty($user_club['club_id'])) {
        return [];
    }

    $club_id = intval($user_club['club_id']);

    // Fetch the event form IDs from wp_clubs
    $event_form_ids = $wpdb->get_var(
        $wpdb->prepare("SELECT event_gform FROM {$wpdb->prefix}clubs WHERE club_id = %d", $club_id)
    );

    // Return multiple form IDs as an array
    return !empty($event_form_ids) ? explode(',', $event_form_ids) : [];
}



// Function to edit event details
// Function to edit event details
function edit_event_details() {
    global $wpdb;

    // Get the event_id from the URL
    $event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;

    if (!$event_id) {
        echo '<p>No event selected or invalid event ID.</p>';
        return;
    }

    // Fetch event details from the database
    $event = get_post($event_id);
    if (!$event || $event->post_type !== 'ajde_events') {
        echo '<p>Invalid event.</p>';
        return;
    }

    // Get metadata and taxonomy terms for the event
    $event_meta = get_post_meta($event_id);
    $event_status = get_post_status($event_id);
    
    // Fetch all available event forms
        $event_form_id = get_event_form_id_by_club();

        // Get the currently selected form ID
        $current_event_form_id = get_post_meta($event_id, '_event_form_id', true);




    // Fetch event categories
    $event_categories = get_event_categories_for_logged_in_user();

    // Fetch selected category IDs
    $event_selected_terms = wp_get_object_terms($event_id, 'event_type', ['fields' => 'ids']);
    $event_selected = !empty($event_selected_terms) ? array_map('intval', $event_selected_terms) : [];

    // Fetch all existing organizers
    $event_organizers = get_terms([
        'taxonomy'   => 'event_organizer',
        'hide_empty' => false,
    ]);
    // Fetch all existing locations
        $event_locations = get_terms([
            'taxonomy'   => 'event_location',
            'hide_empty' => false,
        ]);

        // Fetch selected location(s)
        $event_selected_locations = wp_get_object_terms($event_id, 'event_location', ['fields' => 'ids']);


    // Fetch selected organizer(s)
    $event_selected_organizers = wp_get_object_terms($event_id, 'event_organizer', ['fields' => 'ids']);

    // Fetch event location
    $event_location = wp_get_object_terms($event_id, 'event_location', ['fields' => 'names']);
    $event_location = !empty($event_location) ? $event_location[0] : '';

    $event_title = $event->post_title;
    $event_subtitle = $event_meta['evcal_subtitle'][0] ?? '';
    // Fetch and update Gravity Forms shortcode with the correct form ID
    $event_content = $event->post_content;

    // Check if the Gravity Forms shortcode exists and update its ID
    if ($current_event_form_id) {
        // Check if the shortcode exists and update its ID
        if (preg_match('/\[gravityform\s+id="\d+"\s+title="true"\]/', $event_content)) {
            $event_content = preg_replace('/\[gravityform\s+id="\d+"\s+title="true"\]/', '[gravityform id="' . $current_event_form_id . '" title="true"]', $event_content);
        } else {
            // Shortcode not found, append the correct form ID shortcode
            $event_content .= "\n\n[gravityform id=\"$current_event_form_id\" title=\"true\"]";
        }
    }


        // Retrieve the current featured image
        $current_image_id = get_post_thumbnail_id($event_id);
        $current_image_url = $current_image_id ? wp_get_attachment_url($current_image_id) : '';

        // Fetch event start and end dates
        $event_start_date = isset($event_meta['evcal_srow'][0]) ? date('Y-m-d', $event_meta['evcal_srow'][0]) : '';
        $event_end_date = isset($event_meta['evcal_erow'][0]) ? date('Y-m-d', $event_meta['evcal_erow'][0]) : '';

        // Fetch event start and end times
        $event_start_time = isset($event_meta['evcal_srow'][0]) ? date('H:i', $event_meta['evcal_srow'][0]) : '';
        $event_end_time = isset($event_meta['evcal_erow'][0]) ? date('H:i', $event_meta['evcal_erow'][0]) : '';


        // Handle form submission
    // Handle form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_event'])) {
            // Sanitize and validate input
            $new_event_category = isset($_POST['event_category']) ? intval($_POST['event_category']) : 0;
            $new_event_title = sanitize_text_field($_POST['event_title']);
            $new_event_subtitle = sanitize_text_field($_POST['event_subtitle']);
            $new_event_content = wp_kses_post($_POST['event_content']);
            $new_event_start_date = sanitize_text_field($_POST['event_start_date']);
            $new_event_start_time = sanitize_text_field($_POST['event_start_time']);
            $new_event_end_date = sanitize_text_field($_POST['event_end_date']);
            $new_event_end_time = sanitize_text_field($_POST['event_end_time']);

            $new_event_organizer = isset($_POST['event_organizer']) ? intval($_POST['event_organizer']) : 0;
            $new_event_location = isset($_POST['event_location']) ? intval($_POST['event_location']) : 0;

        // Sanitize status input
            $new_event_status = isset($_POST['event_status']) ? sanitize_text_field($_POST['event_status']) : 'publish';

        // Insert or update Gravity Forms shortcode in content
        // Capture the selected form ID
    $new_event_form_id = isset($_POST['event_form_id'])
        ? sanitize_text_field( $_POST['event_form_id'] )
        : '';

    // 1) Strip out any existing Gravity Forms shortcode
    $new_event_content = preg_replace( '/\[gravityform[^\]]*\]/', '', $new_event_content );

    // 2) If they picked a form, append it and save the meta
    if ( $new_event_form_id !== '' ) {
        $new_event_content .= "\n\n[gravityform id=\"$new_event_form_id\" title=\"true\"]";
        update_post_meta( $event_id, '_event_form_id', $new_event_form_id );

    // 3) Otherwise (they left it blank), delete the meta so nothing remains
    } else {
        delete_post_meta( $event_id, '_event_form_id' );
    }

    // 4) Update the post with the new content
    wp_update_post( [
        'ID'           => $event_id,
        'post_title'   => $new_event_title,
        'post_content' => trim( $new_event_content ),
        'post_status'  => $new_event_status,
    ] );



        // Update meta fields
        update_post_meta($event_id, 'evcal_subtitle', $new_event_subtitle);
        // Combine date and time into Unix timestamps
        $start_datetime = strtotime("$new_event_start_date $new_event_start_time");
        $end_datetime = strtotime("$new_event_end_date $new_event_end_time");

        // Update meta fields with combined date & time
        update_post_meta($event_id, 'evcal_srow', $start_datetime);
        update_post_meta($event_id, 'evcal_erow', $end_datetime);

        // Save the selected event form
        $new_event_form_id = isset($_POST['event_form_id']) ? sanitize_text_field($_POST['event_form_id']) : '';
        update_post_meta($event_id, '_event_form_id', $new_event_form_id);

        // Update or set the featured image
        $new_image_id = isset($_POST['event_featured_image']) ? intval($_POST['event_featured_image']) : 0;
        if ($new_image_id) {
            set_post_thumbnail($event_id, $new_image_id);
        } else {
            // Remove the featured image if the field is empty
            delete_post_thumbnail($event_id);
        }


        // Update taxonomies
        if ($new_event_category > 0) {
            wp_set_object_terms($event_id, [$new_event_category], 'event_type', false);
        }
        if ($new_event_organizer > 0) {
            wp_set_object_terms($event_id, [$new_event_organizer], 'event_organizer', false);
        }
        if ($new_event_location > 0) {
            wp_set_object_terms($event_id, [$new_event_location], 'event_location', false);
        }

        echo '<p>Event updated successfully!</p>';
        // Retain all query parameters while adding event_id and updated
        wp_redirect(add_query_arg([
            'event_id' => $event_id,
            'updated'  => 'true',
            'section'  => isset($_GET['section']) ? $_GET['section'] : 'events',
            'club'     => isset($_GET['club']) ? $_GET['club'] : '',
            'success'  => '1' // <-- Add this parameter
        ], $_SERVER['REQUEST_URI']));
        exit;

        exit;
    }


    // Render the form
    ?>
    <form method="post">
        <h2 class="manager-h2">Edit Event: <?php echo esc_html($event_title); ?></h2>

            <div class="event-row">

                <div class="event-label">
                        <label for="event_category">Category:</label>
                        <select id="event_category" name="event_category" required>
                            <option value="">Select Category</option>
                            <?php if (!isset($event_categories['error'])) : ?>
                                <?php foreach ($event_categories as $category) : ?>
                                    <option value="<?php echo esc_attr($category['term_id']); ?>" 
                                        <?php selected(in_array($category['term_id'], $event_selected)); ?>>
                                        <?php echo esc_html($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <option value="" disabled><?php echo esc_html($event_categories['error']); ?></option>
                            <?php endif; ?>
                        </select>
                    </div>

                

                <div class="event-label">
                    <label for="event_title">Title:</label>
                    <input type="text" id="event_title" name="event_title" value="<?php echo esc_attr($event_title); ?>" required>
                </div>


                
                <div class="event-label event-label-full-width">
                    <label for="event_subtitle">Subtitle:</label>
                    <input type="text" id="event_subtitle" name="event_subtitle" value="<?php echo esc_attr($event_subtitle); ?>">
                </div>

                <div class="event-grid-container">

                    <div class="wysiwyg-event-label">
                        <?php
                        $event_form_ids = get_event_form_id_by_club();
                        if (!empty($event_form_ids)) :
                        ?>
                            <div class="event-label">
                                <label for="event_form_id">Event Form:</label>
                                <select id="event_form_id" name="event_form_id">
                                    <option value="">Select an Event Form</option>
                                    <?php
                                    foreach ($event_form_ids as $form_id) {
                                        // Fetch the form title from the Gravity Forms table
                                        $form_title = $wpdb->get_var($wpdb->prepare("SELECT title FROM {$wpdb->prefix}gf_form WHERE id = %d", $form_id));
                                        ?>
                                        <option value="<?php echo esc_attr($form_id); ?>" 
                                            <?php selected($current_event_form_id, $form_id); ?>>
                                            <?php echo esc_html($form_title ? $form_title : 'Form ID: ' . $form_id); ?>
                                        </option>
                                        <?php
                                    }
                                    ?>
                                </select>
                            </div>
                        <?php endif; ?>
                    </div>

                    

               
                </div>

           


            </div>



        
      <div class="wysiwyg-wrapper">

      <div class="wysiwyg-column1">
        <?php
        wp_editor($event_content, 'event_content', [
            'textarea_name' => 'event_content',
            'textarea_rows' => 10,
        ]);
        ?>
        </div>

        <div class="event-label dotted-border wysiwyg-column2">

            <!-- Featured Image -->
            <label for="event_featured_image">Featured Image:</label>
            <input type="hidden" id="event_featured_image" name="event_featured_image" value="<?php echo esc_attr($current_image_id); ?>">

            <!-- Preview existing image if present -->
            <?php if ($current_image_url) : ?>
                <img id="event_image_preview" src="<?php echo esc_url($current_image_url); ?>" style="max-width: 300px; margin-top: 10px;">
            <?php else : ?>
                <img id="event_image_preview" src="" style="max-width: 300px; display: none; margin-top: 10px;">
            <?php endif; ?>

            <!-- Button to upload or change image -->
            <button type="button" id="upload_image_button" class="button">Select or Change Featured Image</button>

        </div>

      </div>

        
       <div class="event-row">


        <div class="event-label">
            <label for="event_start_date">Event Start Date:</label>
            <input type="date" id="event_start_date" name="event_start_date" value="<?php echo esc_attr($event_start_date); ?>" required>
            </div>

            <div class="event-label">
            <label for="event_start_time">Event Start Time:</label>
            <input type="time" id="event_start_time" name="event_start_time" value="<?php echo esc_attr($event_start_time); ?>" required>
            </div>

            <div class="event-label">
            <label for="event_end_date">Event End Date:</label>
            <input type="date" id="event_end_date" name="event_end_date" value="<?php echo esc_attr($event_end_date); ?>" required>

            </div>

            <div class="event-label">
            <label for="event_end_time">Event End Time:</label>
            <input type="time" id="event_end_time" name="event_end_time" value="<?php echo esc_attr($event_end_time); ?>" required>
            </div>

       </div>


        <div class="event-row">

            <div class="event-label">
                <label for="event_location">Location:</label>
                <select id="event_location" name="event_location">
                    <option value="">Select a Location</option>
                    <?php foreach ($event_locations as $location) : ?>
                        <option value="<?php echo esc_attr($location->term_id); ?>" 
                            <?php selected(in_array($location->term_id, $event_selected_locations)); ?>>
                            <?php echo esc_html($location->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="event-label">

                <label for="event_organizer">Organizer:</label>
                    <select id="event_organizer" name="event_organizer">
                        <option value="">Select an Organizer</option>
                        <?php foreach ($event_organizers as $organizer) : ?>
                            <option value="<?php echo esc_attr($organizer->term_id); ?>" 
                                <?php selected(in_array($organizer->term_id, $event_selected_organizers)); ?>>
                                <?php echo esc_html($organizer->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

            </div>

        </div>
    
       


        <div class="event-row">

        <div class="event-label">
            <label for="event_tickets">Tickets:</label>
            <select id="event_tickets" name="event_tickets">
                <option value="yes" <?php selected($event_meta['evotx_tix'][0], 'yes'); ?>>Yes</option>
                <option value="no" <?php selected($event_meta['evotx_tix'][0], 'no'); ?>>No</option>
            </select>
        </div>
       
       <div class="event-label">

        <label for="event_status">Event Status:</label>
        <select id="event_status" name="event_status">
            <option value="publish" <?php selected($event_status, 'publish'); ?>>Published</option>
            <option value="draft" <?php selected($event_status, 'draft'); ?>>Draft</option>
            <option value="pending" <?php selected($event_status, 'pending'); ?>>Pending Review</option>
        </select>

       </div>

        </div>



        <button type="submit" name="edit_event">Save Changes</button>
        <a href="<?php echo esc_url(remove_query_arg('event_id')); ?>" class="button cancel-button">Cancel</a>
        <?php if (isset($_GET['success']) && $_GET['success'] === '1'): ?>
    <div class="notice notice-success" style="background-color: #dff0d8; color: #3c763d; padding: 15px; border: 1px solid #d6e9c6; margin-bottom: 20px;">
        🎉 Event updated successfully!
    </div>
    <?php endif; ?>

    </form>
    <script>
        jQuery(document).ready(function($) {
            var mediaUploader;

            $('#upload_image_button').click(function(e) {
                e.preventDefault();

                // If the uploader object has already been created, reopen it
                if (mediaUploader) {
                    mediaUploader.open();
                    return;
                }

                // Create a new media uploader instance
                mediaUploader = wp.media.frames.file_frame = wp.media({
                    title: 'Choose or Change Featured Image',
                    button: {
                        text: 'Use this image'
                    },
                    multiple: false
                });

                // Handle image selection
                mediaUploader.on('select', function() {
                    var attachment = mediaUploader.state().get('selection').first().toJSON();
                    $('#event_featured_image').val(attachment.id);
                    $('#event_image_preview').attr('src', attachment.url).show();
                });

                mediaUploader.open();
            });
        });
    </script>

    <script>
        jQuery(document).ready(function($) {
            $('form').submit(function(e) {
                var startDate = $('#event_start_date').val();
                var startTime = $('#event_start_time').val();
                var endDate = $('#event_end_date').val();
                var endTime = $('#event_end_time').val();
                var featuredImage = $('#event_featured_image').val();

                console.log('Featured Image ID:', featuredImage); // 🔍 Log to debug

                // Check if any date/time fields are empty
                if (!startDate || !startTime || !endDate || !endTime) {
                    alert('Please fill in all start and end date/time fields.');
                    e.preventDefault();
                    return false;
                }

                // ✅ Check if featured image is missing
                if (!featuredImage || featuredImage === "0") {
                    alert('Please select a featured image before submitting the form.');
                    e.preventDefault();
                    return false;
                }

                // Compare datetimes
                var startDateTime = new Date(startDate + 'T' + startTime);
                var endDateTime = new Date(endDate + 'T' + endTime);

                if (endDateTime < startDateTime) {
                    alert('The event end date/time cannot be before the start date/time.');
                    e.preventDefault();
                    return false;
                }

                return true;
            });
        });
    </script>




    <?php
}




function render_add_event_form() {
    global $wpdb;

    // Get current user and their club details.
    $current_user = wp_get_current_user();
    $user_email = $current_user->user_email;

    $user_club = $wpdb->get_row(
        $wpdb->prepare("SELECT club_id, club_name FROM {$wpdb->prefix}club_members WHERE user_email = %s", $user_email),
        ARRAY_A
    );

    if (!$user_club) {
        echo '<div class="error">You are not associated with any club. Event creation is not allowed.</div>';
        return;
    }

    $club_id = $user_club['club_id'];
    $club_name = $user_club['club_name'];

    // Fetch event categories for this club
    $event_categories = get_event_categories_for_logged_in_user();
    $event_forms = get_event_form_id_by_club();


    // Fetch all existing organizers and locations
    $organizers = $wpdb->get_results("
        SELECT t.term_id, t.name 
        FROM {$wpdb->terms} t
        INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
        WHERE tt.taxonomy = 'event_organizer'
    ");

    $locations = $wpdb->get_results("
        SELECT t.term_id, t.name 
        FROM {$wpdb->terms} t
        INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
        WHERE tt.taxonomy = 'event_location'
    ");

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_event_nonce']) && wp_verify_nonce($_POST['add_event_nonce'], 'add_new_event')) {
        // Sanitize and validate form inputs
        $event_data = [
            'title'       => sanitize_text_field($_POST['event_title']),
            'subtitle'    => sanitize_text_field($_POST['event_subtitle']),
            'content'     => wp_kses_post($_POST['event_content']),
            
            'start_date'  => sanitize_text_field($_POST['event_start_date']),
            'start_time'  => sanitize_text_field($_POST['event_start_time']),
            'end_date'    => sanitize_text_field($_POST['event_end_date']),
            'end_time'    => sanitize_text_field($_POST['event_end_time']),

            'category'    => intval($_POST['event_category']), // Only allow one category selection
        ];

        $event_data['event_form'] = sanitize_text_field($_POST['event_form']);


        // Handle organizer: check if a new one is provided
        if (!empty($_POST['new_event_organizer'])) {
            $new_organizer_name = sanitize_text_field($_POST['new_event_organizer']);
            $new_organizer_id = wp_insert_term($new_organizer_name, 'event_organizer');
            if (!is_wp_error($new_organizer_id)) {
                $event_data['organizer'] = $new_organizer_id['term_id'];
            }
        } else {
            $event_data['organizer'] = intval($_POST['event_organizer']);
        }

        // Handle location: check if a new one is provided
        if (!empty($_POST['new_event_location'])) {
            $new_location_name = sanitize_text_field($_POST['new_event_location']);
            $new_location_id = wp_insert_term($new_location_name, 'event_location');
            if (!is_wp_error($new_location_id)) {
                $event_data['location'] = $new_location_id['term_id'];
            }
        } else {
            $event_data['location'] = intval($_POST['event_location']);
        }

        // Generate the Gravity Forms shortcode dynamically
        $gravity_form_shortcode = !empty($event_data['event_form']) ? '[gravityform id="' . esc_attr($event_data['event_form']) . '" title="true"]' : '';

        // Insert new event post with the form shortcode appended to content
        $event_id = wp_insert_post([
            'post_title'   => $event_data['title'],
            'post_content' => $event_data['content'] . "\n\n" . $gravity_form_shortcode, // Append shortcode to content
            'post_status'  => 'publish',
            'post_type'    => 'ajde_events',
        ]);

        // Save selected featured image from Media Library
        if (!empty($_POST['event_featured_image'])) {
            $attachment_id = intval($_POST['event_featured_image']);
            set_post_thumbnail($event_id, $attachment_id);
        }

        if (is_wp_error($event_id)) {
            echo '<div class="error">Error creating event: ' . esc_html($event_id->get_error_message()) . '</div>';
        } else {
            // Store metadata for club association
            update_post_meta($event_id, '_select_club_id', $club_id);
            update_post_meta($event_id, '_select_club_name', $club_name);
            update_post_meta($event_id, '_event_form_id', $event_data['event_form']);
            update_post_meta($event_id, '_status', 'scheduled');

            // Save the event subtitle
            update_post_meta($event_id, 'evcal_subtitle', $event_data['subtitle']);



            // Combine date and time into Unix timestamps
            $start_datetime = strtotime($event_data['start_date'] . ' ' . $event_data['start_time']);
            $end_datetime = strtotime($event_data['end_date'] . ' ' . $event_data['end_time']);

            // Store start and end timestamps
            update_post_meta($event_id, 'evcal_srow', $start_datetime);
            update_post_meta($event_id, 'evcal_erow', $end_datetime);


            

            // Assign taxonomies
            if (!empty($event_data['category'])) {
                wp_set_object_terms($event_id, $event_data['category'], 'event_type');
            }
            if (!empty($event_data['organizer'])) {
                wp_set_object_terms($event_id, $event_data['organizer'], 'event_organizer');
            }
            if (!empty($event_data['location'])) {
                wp_set_object_terms($event_id, $event_data['location'], 'event_location');
            }

            // Debugging output
            echo '<div class="success">Event created successfully! Event Title: ' . esc_html($event_data['title']) . '</div>';
            
        }
    }

    // Display the form
    ?>
    <form method="post" action="">
        <?php wp_nonce_field('add_new_event', 'add_event_nonce'); ?>

        <div class="event-row">

            <div class="event-label">
                <label for="event_category">Event Category:</label>
                <select id="event_category" name="event_category" required>
                    <option value="">Select a Category</option>
                    <?php foreach ($event_categories as $category) : ?>
                        <option value="<?php echo esc_attr($category['term_id']); ?>">
                            <?php echo esc_html($category['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="event-label">
                <label for="event_title">Event Title:</label>
                <input type="text" id="event_title" name="event_title" required>
            </div>

            <div class="event-label">
                <label for="event_subtitle">Event Subtitle:</label>
                <input type="text" id="event_subtitle" name="event_subtitle">
            </div>

            <div class="event-label">

            <?php if (!empty($event_forms)) : ?>
                <label for="event_form">Event Form:</label>
                <select id="event_form" name="event_form">
                    <option value="">Select an Event Form</option>
                    <?php 
                    foreach ($event_forms as $form_id) : 
                        // Fetch the form title from the Gravity Forms table
                        $form_title = $wpdb->get_var($wpdb->prepare("SELECT title FROM {$wpdb->prefix}gf_form WHERE id = %d", $form_id));
                    ?>
                        <option value="<?php echo esc_attr($form_id); ?>">
                            <?php echo esc_html($form_title ? $form_title : 'Form ID: ' . $form_id); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>


            </div>

        </div>

        <div class="event-row wysiwyg-wrapper">
            
        <div class="wysiwyg-column1">
            <label for="event_content">Event Description:</label>
            <?php
            wp_editor('', 'event_content', [
                'textarea_name' => 'event_content',
                'textarea_rows' => 10,
            ]);
            ?>
        </div>



            <div class="event-label dotted-border wysiwyg-column2">
                <label for="event_featured_image">Featured Image:</label>
                <input type="hidden" id="event_featured_image" name="event_featured_image">
                <button type="button" id="upload_image_button" class="button">Select Featured Image</button>
                <img id="event_image_preview" src="" style="max-width: 300px;  margin-top: 10px;">
            </div>
        </div>


        

        <div class="event-row">
            
            <div class="event-label">
                <label for="event_start_date">Start Date:</label>
                <input type="date" id="event_start_date" name="event_start_date" required>
            </div>

            <div class="event-label">
                <label for="event_start_time">Start Time:</label>
                <input type="time" id="event_start_time" name="event_start_time" required>
            </div>

            <div class="event-label">
                <label for="event_end_date">End Date:</label>
                <input type="date" id="event_end_date" name="event_end_date" required>
            </div>

            <div class="event-label">
                <label for="event_end_time">End Time:</label>
                <input type="time" id="event_end_time" name="event_end_time" required>
            </div>

        </div>

        

        <div class="event-row">

           <div class="event-label">

            <label for="event_location">Location:</label>
                <select id="event_location" name="event_location">
                    <option value="">Select a Location</option>
                    <?php foreach ($locations as $location): ?>
                        <option value="<?php echo esc_attr($location->term_id); ?>">
                            <?php echo esc_html($location->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

           </div>
            

           <div class="event-label">

            <label for="event_organizer">Organizer:</label>
                <select id="event_organizer" name="event_organizer">
                    <option value="">Select an Organizer</option>
                    <?php foreach ($organizers as $organizer): ?>
                        <option value="<?php echo esc_attr($organizer->term_id); ?>">
                            <?php echo esc_html($organizer->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

           </div>
        
        </div>

        


                

        <button type="submit">Create Event</button>
    </form>
    <script>
        jQuery(document).ready(function($) {
            var mediaUploader;

            $('#upload_image_button').click(function(e) {
                e.preventDefault();
                
                // Ensure the media uploader is available
                if (typeof wp === 'undefined' || !wp.media) {
                    alert('Media uploader not available. Please make sure WordPress media scripts are loaded.');
                    return;
                }

                // If media uploader instance already exists, reopen it
                if (mediaUploader) {
                    mediaUploader.open();
                    return;
                }

                // Create new media uploader instance
                mediaUploader = wp.media.frames.file_frame = wp.media({
                    title: 'Select or Upload Featured Image',
                    button: {
                        text: 'Use this image'
                    },
                    multiple: false
                });

                // Handle image selection
                mediaUploader.on('select', function() {
                    var attachment = mediaUploader.state().get('selection').first().toJSON();
                    $('#event_featured_image').val(attachment.id);
                    $('#event_image_preview').attr('src', attachment.url).show();
                });

                mediaUploader.open();
            });
        });
    </script>

    <script>
        jQuery(document).ready(function($) {
            // Validate event dates and featured image before submitting the form
            $('form').submit(function(e) {
                var startDate = $('#event_start_date').val();
                var startTime = $('#event_start_time').val();
                var endDate = $('#event_end_date').val();
                var endTime = $('#event_end_time').val();
                var featuredImageId = $('#event_featured_image').val();

                // Check if any of the date/time fields are empty
                if (!startDate || !startTime || !endDate || !endTime) {
                    alert('Please fill in all date and time fields.');
                    e.preventDefault();
                    return false;
                }

                // Check if featured image is selected
                if (!featuredImageId) {
                    alert('Please select a featured image before submitting.');
                    e.preventDefault();
                    return false;
                }

                // Convert to Date objects for comparison
                var startDateTime = new Date(startDate + 'T' + startTime);
                var endDateTime = new Date(endDate + 'T' + endTime);

                // Validate that the end time is not before the start time
                if (endDateTime < startDateTime) {
                    alert('The end time cannot be before the start time. Please correct the dates.');
                    e.preventDefault();
                    return false;
                }

                return true;
            });
        });
    </script>



    <?php
}





