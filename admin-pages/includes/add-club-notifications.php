<?php
// Ensure to include this within a WordPress environment
defined('ABSPATH') || exit;

// Fetch club ID from the URL
$club_id = isset($_GET['club_id']) ? intval($_GET['club_id']) : 0;
if (!$club_id) {
    echo '<p>' . __('No club ID provided.', 'textdomain') . '</p>';
    return;
}

// Fetch existing notification settings for the club if available
global $wpdb;
$selected_workflows = $wpdb->get_var($wpdb->prepare(
    "SELECT workflows FROM {$wpdb->prefix}club_notifications WHERE club_id = %d",
    $club_id
));
$selected_workflows = $selected_workflows ? explode(',', $selected_workflows) : [];

// Fetch all AutomateWoo workflows directly from the database
$workflow_results = $wpdb->get_results(
    "SELECT ID, post_title 
     FROM {$wpdb->posts} 
     WHERE post_type = 'aw_workflow' 
     AND post_status IN ('publish', 'aw-disabled1')"
);

$workflows = [];
if ($workflow_results) {
    foreach ($workflow_results as $workflow) {
        $workflows[$workflow->ID] = $workflow->post_title;
    }
}

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_notifications'])) {
    $selected_workflows = isset($_POST['workflows']) ? array_map('intval', $_POST['workflows']) : [];
    $workflow_ids = implode(',', $selected_workflows);

    // Insert or update the notification settings in the wp_clubs table
    $wpdb->update(
        "{$wpdb->prefix}clubs",
        ['notifications' => $workflow_ids],
        ['club_id' => $club_id]
    );

    // Redirect to avoid form resubmission
    wp_redirect(add_query_arg(['club_id' => $club_id, 'updated' => true], $_SERVER['REQUEST_URI']));
    exit;
}
?>

<?php if (isset($_GET['updated']) && $_GET['updated'] == true): ?>
    <div class="notice notice-success is-dismissible">
        <p><?php _e('Notification settings saved successfully!', 'textdomain'); ?></p>
    </div>
<?php endif; ?>

<form method="POST" action="">
    <table class="form-table">
        <tr>
            <th scope="row">
                <label for="workflows">
                    <?php _e('Select Workflows', 'textdomain'); ?>
                </label>
            </th>
            <td>
                <select id="workflows" name="workflows[]" multiple="multiple" style="width: 100%; height: 200px;">
                    <?php foreach ($workflows as $workflow_id => $workflow_title): ?>
                        <option value="<?php echo esc_attr($workflow_id); ?>" <?php echo in_array($workflow_id, $selected_workflows) ? 'selected' : ''; ?>>
                            <?php echo esc_html($workflow_title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="description">Select one or more workflows for club notifications.</p>
            </td>
        </tr>
    </table>

    <input type="hidden" name="club_id" value="<?php echo $club_id; ?>" />
    <input type="hidden" name="save_notifications" value="1" />
    <p class="submit">
        <input type="submit" class="button button-primary" value="<?php _e('Save Changes', 'textdomain'); ?>" />
    </p>
</form>