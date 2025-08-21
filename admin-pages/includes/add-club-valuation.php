<?php
// Ensure to include this within a WordPress environment
defined('ABSPATH') || exit;

// Fetch club ID from the URL
$club_id = isset($_GET['club_id']) ? intval($_GET['club_id']) : 0;
if (!$club_id) {
    echo '<p>' . __('No club ID provided.', 'textdomain') . '</p>';
    return;
}

// Fetch existing form settings for the club if available
global $wpdb;
$selected_gform_id = $wpdb->get_var($wpdb->prepare(
    "SELECT gform_id FROM {$wpdb->prefix}clubs WHERE club_id = %d",
    $club_id
));

// Fetch all Gravity Forms
if (class_exists('GFAPI')) {
    $forms = GFAPI::get_forms();
} else {
    echo '<p>' . __('Gravity Forms plugin is not active.', 'textdomain'); ?></p>
    <?php return;
}

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_forms'])) {
    $selected_gform_id = isset($_POST['gform_id']) ? intval($_POST['gform_id']) : null;

    // Update the gform_id in the wp_clubs table
    $wpdb->update(
        "{$wpdb->prefix}clubs",
        ['gform_id' => $selected_gform_id],
        ['club_id' => $club_id],
        ['%d'],
        ['%d']
    );

    // Redirect to avoid form resubmission
    wp_redirect(add_query_arg(['club_id' => $club_id, 'updated' => true], $_SERVER['REQUEST_URI']));
    exit;
}
?>

<?php if (isset($_GET['updated']) && $_GET['updated'] == true): ?>
    <div class="notice notice-success is-dismissible">
        <p><?php _e('Form settings saved successfully!', 'textdomain'); ?></p>
    </div>
<?php endif; ?>

<form method="POST" action="">
    <table class="form-table">
        <tr>
            <th scope="row">
                <label for="gform_id">
                    <?php _e('Select Gravity Form', 'textdomain'); ?>
                </label>
            </th>
            <td>
                <select id="gform_id" name="gform_id" style="width: 100%;">
                    <option value="" <?php selected($selected_gform_id, null); ?>><?php _e('None', 'textdomain'); ?></option>
                    <?php foreach ($forms as $form): ?>
                        <option value="<?php echo esc_attr($form['id']); ?>" <?php selected($selected_gform_id, $form['id']); ?>>
                            <?php echo esc_html($form['title'] . ' (ID: ' . $form['id'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="description">Select a Gravity Form for the club.</p>
            </td>
        </tr>
    </table>

    <input type="hidden" name="club_id" value="<?php echo $club_id; ?>" />
    <input type="hidden" name="save_forms" value="1" />
    <p class="submit">
        <input type="submit" class="button button-primary" value="<?php _e('Save Changes', 'textdomain'); ?>" />
    </p>
</form>
