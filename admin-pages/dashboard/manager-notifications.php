<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Fetch the logged-in user's email
function get_logged_in_user_email() {
    $current_user = wp_get_current_user();
    return $current_user->exists() ? $current_user->user_email : false;
}

// Match the email with the clubs and fetch relevant club notifications
function get_club_notifications_by_user() {
    global $wpdb;

    // Check for the 'club' parameter in the URL
    if (isset($_GET['club']) && !empty($_GET['club'])) {
        $club_name = urldecode(sanitize_text_field($_GET['club']));

        // Query the database to fetch club details based on the club name
        $club = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}clubs WHERE club_name = %s",
                $club_name
            )
        );

        // If a club is found, return its notifications
        if ($club && $club->notifications) {
            return [
                'club_name' => $club->club_name,
                'notifications' => explode(',', $club->notifications)
            ];
        }

        // If the club parameter is invalid, return false
        return false;
    }

    // Fallback: Get the logged-in user's email
    $user_email = get_logged_in_user_email();
    if (!$user_email) {
        return false;
    }

    // Query to match the user's email with clubs
    $club = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}club_members cm
            INNER JOIN {$wpdb->prefix}clubs c ON cm.club_id = c.club_id
            WHERE cm.user_email = %s",
            $user_email
        )
    );

    // If a club is found, return its notifications
    if ($club && $club->notifications) {
        return [
            'club_name' => $club->club_name,
            'notifications' => explode(',', $club->notifications)
        ];
    }

    return false;
}


function render_notification_table() {
    global $wp_query;
    $paged = isset($wp_query->query_vars['paged']) ? intval($wp_query->query_vars['paged']) : 1;
    $current_page = $paged > 0 ? $paged : 1;
    $items_per_page = 20; // Number of items per page
    $offset = ($current_page - 1) * $items_per_page;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Handle bulk actions
        $bulk_action = sanitize_text_field($_POST['bulk_action'] ?? '');
        $workflow_ids = (isset($_POST['workflow_ids']) && is_array($_POST['workflow_ids']))
            ? array_map('intval', $_POST['workflow_ids'])
            : []; // Ensure workflow_ids is an array or fallback to an empty array

        if ($bulk_action === 'delete' && !empty($workflow_ids)) {
            foreach ($workflow_ids as $workflow_id) {
                wp_delete_post($workflow_id, true); // Permanently delete post
            }
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Selected workflows deleted successfully.', 'textdomain') . '</p></div>';
        }
    }

    // Fetch notifications
    $club_notifications = get_club_notifications_by_user();
    if (!$club_notifications) {
        echo '<p>' . __('No notifications found for your club.', 'textdomain') . '</p>';
        return;
    }

    global $wpdb;
    $notifications = $club_notifications['notifications'];

    

    // Fetch workflow details with pagination and search
    $placeholders = implode(',', array_fill(0, count($notifications), '%d'));
    $workflow_results = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT p.ID, p.post_title, 
                    pm_type.meta_value AS type,
                    pm_trigger_name.meta_value AS trigger_name
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm_type ON p.ID = pm_type.post_id AND pm_type.meta_key = 'type'
             LEFT JOIN {$wpdb->postmeta} pm_trigger_name ON p.ID = pm_trigger_name.post_id AND pm_trigger_name.meta_key = 'trigger_name'
             WHERE p.ID IN ($placeholders) AND p.post_status = 'publish' $search_clause
             LIMIT %d OFFSET %d",
            array_merge($notifications, [$items_per_page, $offset])
        )
    );

    if (!$workflow_results) {
        echo '<p>' . __('No workflows found.', 'textdomain') . '</p>';
        return;
    }

    // Pagination
    $total_workflows = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*)
             FROM {$wpdb->posts} p
             WHERE p.ID IN ($placeholders) AND p.post_status = 'publish' $search_clause",
            $notifications
        )
    );
    $total_pages = ceil($total_workflows / $items_per_page);

    // Render search and bulk actions
    echo '<form method="post" id="bulk-action-form">
        <div class="bulk-actions end-filters">
            <div class="end-filters">
                <select name="bulk_action">
                    <option value="">' . __('Bulk Actions', 'textdomain') . '</option>
                    <option value="delete">' . __('Delete', 'textdomain') . '</option>
                </select>
                <button type="submit" class="button button-primary my-filters All-button">' . __('Apply Filter', 'textdomain') . '</button>
            </div>
           
        </div>';

    // Render the workflows table
    echo '<table class="wp-list-table widefat fixed striped managertable"id="notification-table">
        <thead>
            <tr>
                <th><input type="checkbox" id="select-all-notification"></th>
                <th>' . __('Notifications Name', 'textdomain') . '</th>
                <th>' . __('Type', 'textdomain') . '</th>
                <th>' . __('Trigger Name', 'textdomain') . '</th>
            </tr>
        </thead>
        <tbody>';

    foreach ($workflow_results as $workflow) {
        $notification_name = $workflow->post_title;
        $type = $workflow->type ? ucfirst(strtolower($workflow->type)) : __('Unknown', 'textdomain');
        $trigger_name = $workflow->trigger_name ? ucwords(str_replace('_', ' ', strtolower($workflow->trigger_name))) : __('Unknown', 'textdomain');

        echo '<tr>
            <td><input type="checkbox" name="workflow_ids[]" value="' . esc_attr($workflow->ID) . '"></td>
            <td data-label="Notification Name"><a href="#" class="notification-link" data-workflow-id="' . esc_attr($workflow->ID) . '">' . esc_html($notification_name) . '</a></td>
            <td data-label="Type">' . esc_html($type) . '</td>
            <td data-label="Trigger Name">' . esc_html($trigger_name) . '</td>
        </tr>';
    }

    echo '</tbody>
    </table>
    </form>';

    // Render pagination
    echo '<div class="pagination" style="margin-top: 20px; text-align: center;">';
    if ($current_page > 1) {
        echo '<a href="' . esc_url(add_query_arg('paged', $current_page - 1)) . '" class="prev-page">' . __('Previous', 'textdomain') . '</a>';
    }
    for ($i = 1; $i <= $total_pages; $i++) {
        echo '<a href="' . esc_url(add_query_arg('paged', $i)) . '" class="' . ($i === $current_page ? 'current' : '') . '">' . $i . '</a>';
    }
    if ($current_page < $total_pages) {
        echo '<a href="' . esc_url(add_query_arg('paged', $current_page + 1)) . '" class="next-page">' . __('Next', 'textdomain') . '</a>';
    }
    echo '</div>';

    echo '<script>
        document.getElementById("select-all-notification").addEventListener("change", function () {
            const checkboxes = document.querySelectorAll("input[name=\'workflow_ids[]\']");
            checkboxes.forEach(checkbox => checkbox.checked = this.checked);
        });

        document.addEventListener("DOMContentLoaded", function () {
            const links = document.querySelectorAll(".notification-link");
            links.forEach(link => {
                link.addEventListener("click", function (e) {
                    e.preventDefault();
                    const workflowId = this.getAttribute("data-workflow-id");
                    const url = new URL(window.location.href);
                    url.searchParams.set("workflow_id", workflowId);
                    window.history.pushState({}, "", url);
                    location.reload();
                });
            });
        });
    </script>';
}









if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Render the workflow edit form
function workflow_edit() {
    if (!isset($_GET['workflow_id'])) {
        echo '<p>' . __('No workflow selected.', 'textdomain') . '</p>';
        return;
    }

    $workflow_id = intval($_GET['workflow_id']);
    global $wpdb;

    // Fetch workflow details
    $workflow = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT p.ID, p.post_title, 
                    (SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = p.ID AND meta_key = 'actions') AS actions,
                    (SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = p.ID AND meta_key = 'workflow_options') AS workflow_options,
                    (SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = p.ID AND meta_key = 'trigger_options') AS trigger_options,
                    (SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = p.ID AND meta_key = 'trigger_name') AS trigger_name
             FROM {$wpdb->posts} p
             WHERE p.ID = %d",
            $workflow_id
        )
    );

    if (!$workflow) {
        echo '<p>' . __('Workflow not found.', 'textdomain') . '</p>';
        return;
    }

    $actions = !empty($workflow->actions) ? unserialize($workflow->actions) : [];
    $workflow_options = !empty($workflow->workflow_options) ? unserialize($workflow->workflow_options) : [];
    $trigger_options = !empty($workflow->trigger_options) ? unserialize($workflow->trigger_options) : [];

    $workflow_name = $workflow->post_title;
    $timing = $workflow_options['when_to_run'] ?? 'immediately';
    $trigger_name = $workflow->trigger_name 
        ? ucwords(str_replace('_', ' ', strtolower($workflow->trigger_name))) 
        : __('Unknown', 'textdomain');
    $days_before_renewal = ($workflow->trigger_name === 'subscription_before_renewal') 
        ? ($trigger_options['days_before_renewal'] ?? '') 
        : '';
    $email_content = $actions[1]['email_content'] ?? '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_workflow'])) {
        // Save changes to the workflow
        $new_workflow_name = sanitize_text_field($_POST['workflow_name']);
        $new_email_content = isset($_POST['email_content']) ? wp_kses_post($_POST['email_content']) : $email_content;
        $new_days_before_renewal = isset($_POST['days_before_renewal']) ? intval($_POST['days_before_renewal']) : $days_before_renewal;

        $actions[1]['email_content'] = $new_email_content;

        if ($workflow->trigger_name === 'subscription_before_renewal') {
            $trigger_options['days_before_renewal'] = $new_days_before_renewal;
        }

        // Update workflow name
        $wpdb->update(
            $wpdb->posts,
            ['post_title' => $new_workflow_name],
            ['ID' => $workflow_id],
            ['%s'],
            ['%d']
        );

        // Update workflow actions
        $wpdb->update(
            $wpdb->postmeta,
            ['meta_value' => serialize($actions)],
            ['post_id' => $workflow_id, 'meta_key' => 'actions'],
            ['%s'],
            ['%d', '%s']
        );

        // Update trigger options
        $wpdb->update(
            $wpdb->postmeta,
            ['meta_value' => serialize($trigger_options)],
            ['post_id' => $workflow_id, 'meta_key' => 'trigger_options'],
            ['%s'],
            ['%d', '%s']
        );

        // Reload the updated workflow data
        $workflow = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT p.ID, p.post_title, 
                        (SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = p.ID AND meta_key = 'actions') AS actions,
                        (SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = p.ID AND meta_key = 'workflow_options') AS workflow_options,
                        (SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = p.ID AND meta_key = 'trigger_options') AS trigger_options,
                        (SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = p.ID AND meta_key = 'trigger_name') AS trigger_name
                 FROM {$wpdb->posts} p
                 WHERE p.ID = %d",
                $workflow_id
            )
        );

        $actions = !empty($workflow->actions) ? unserialize($workflow->actions) : [];
        $workflow_options = !empty($workflow->workflow_options) ? unserialize($workflow->workflow_options) : [];
        $trigger_options = !empty($workflow->trigger_options) ? unserialize($workflow->trigger_options) : [];
        $workflow_name = $workflow->post_title;
        $timing = $workflow_options['when_to_run'] ?? 'immediately';
        $trigger_name = $workflow->trigger_name 
            ? ucwords(str_replace('_', ' ', strtolower($workflow->trigger_name))) 
            : __('Unknown', 'textdomain');
        $days_before_renewal = ($workflow->trigger_name === 'subscription_before_renewal') 
            ? ($trigger_options['days_before_renewal'] ?? '') 
            : '';
        $email_content = $actions[1]['email_content'] ?? '';

        echo '<div class="notice notice-success is-dismissible"><p>' . __('Workflow updated successfully.', 'textdomain') . '</p></div>';
    }

    echo '<form method="post">
    <div class="notification">
    
        <div class="event-row">
            <div class="event-label">
                <label for="workflow_name">' . __('Workflow Name', 'textdomain') . '</label>
                <div>
                    <input type="text" id="workflow_name" name="workflow_name" class="regular-text" value="' . esc_attr($workflow_name) . '" />
                </div>
            </div>
            
            <div class="event-label">
                <label for="trigger_name">' . __('Trigger Name', 'textdomain') . '</label>
                <div>
                    <input type="text" id="trigger_name" class="regular-text" value="' . esc_html($trigger_name) . '" disabled />
                </div>
            </div>
            
            <div class="event-label">
                <label for="timing">' . __('Timing', 'textdomain') . '</label>
                <div>
                    <input type="text" id="timing" class="regular-text" value="' . esc_html($timing) . '" readonly />
                </div>
            </div>
        </div>';

        if ($workflow->trigger_name === 'subscription_before_renewal') {
            echo '<div class="event-label">
                    <label for="days_before_renewal">' . __('Days Before Renewal', 'textdomain') . '</label>
                    <div>
                        <input type="number" id="days_before_renewal" name="days_before_renewal" class="regular-text" value="' . esc_attr($days_before_renewal) . '" />
                    </div>
                </div>';
        }

        echo '<label>' . __('', 'textdomain') . '</label>';
        echo '<div class="wysiwyg-column1 wysiwig-margin">';
        wp_editor($email_content, 'email_content', [
            'textarea_name' => 'email_content',
            'textarea_rows' => 10,
        ]);
        echo '</div>';
        
    echo '</div>        
        <p class="submit">
            <input type="submit" name="save_workflow" class="button button-primary savebtn All-button" value="' . __('Save Changes', 'textdomain') . '">
            <button type="button" id="cancel-button" class="button cancelbtn">' . __('Cancel', 'textdomain') . '</button>
        </p>
    </form>';


    echo '<script>
        document.getElementById("cancel-button").addEventListener("click", function () {
            const url = new URL(window.location.href);
            url.searchParams.delete("workflow_id");
            window.history.pushState({}, "", url);
            location.reload();
        });
    </script>';
}




// Determine what to render based on URL
if (isset($_GET['workflow_id']) && !empty($_GET['workflow_id'])) {
    echo '<h2 class="manager-h2">Edit Notification Workflows</h2>';
    workflow_edit();
} else {
   // Add the button below the heading
   
    echo '<div class="admin-switch">';
    echo '<h2 class="manager-h2">Notifications</h2>';
    echo '<a href="' . esc_url(admin_url('edit.php?post_type=aw_workflow')) . '" class="button All-button">
    Advanced
    </a>';
    echo '</div>';
    
    
    render_notification_table();
}

?>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const searchInput = document.getElementById("notification-search");
    const tableRows = document.querySelectorAll(".wp-list-table tbody tr");

    // Add an event listener to filter rows based on search input
    searchInput.addEventListener("input", function () {
        const searchQuery = searchInput.value.toLowerCase();

        tableRows.forEach((row) => {
            const notificationName = row.querySelector("[data-label='Notification Name']").innerText.toLowerCase();
            const messagePreview = row.querySelector("[data-label='Message Preview']").innerText.toLowerCase();

            // Check if the search query matches either the notification name or message preview
            if (notificationName.includes(searchQuery) || messagePreview.includes(searchQuery)) {
                row.style.display = ""; // Show row
            } else {
                row.style.display = "none"; // Hide row
            }
        });
    });
});

</script>