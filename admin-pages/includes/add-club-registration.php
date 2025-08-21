<?php
defined('ABSPATH') || exit;

global $wpdb;

// Fetch club ID from the URL
$club_id = isset($_GET['club_id']) ? intval($_GET['club_id']) : 0;
if (!$club_id) {
    echo '<p>' . __('No club ID provided.', 'textdomain') . '</p>';
    return;
}

// Fetch existing registration_form for the club
$registration_gform_ids = $wpdb->get_var($wpdb->prepare(
    "SELECT registration_form FROM {$wpdb->prefix}clubs WHERE club_id = %d",
    $club_id
));

// Convert stored IDs to an array
$selected_gform_ids = $registration_gform_ids ? explode(',', $registration_gform_ids) : [];

// Fetch all Gravity Forms
$gravity_forms = $wpdb->get_results(
    "SELECT id, title FROM {$wpdb->prefix}gf_form WHERE is_active = 1 AND is_trash = 0"
);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_registration_gform'])) {
    $selected_gform_ids = isset($_POST['registration_gform_ids']) ? array_map('intval', $_POST['registration_gform_ids']) : [];

    // Update registration_form column in wp_clubs (store IDs as comma-separated string)
    $wpdb->update(
        "{$wpdb->prefix}clubs",
        ['registration_form' => implode(',', $selected_gform_ids)],
        ['club_id' => $club_id],
        ['%s'],
        ['%d']
    );

    // Redirect to avoid form resubmission
    wp_redirect(add_query_arg(['club_id' => $club_id, 'updated' => true], $_SERVER['REQUEST_URI']));
    exit;
}
?>

<?php if (isset($_GET['updated']) && $_GET['updated'] == true): ?>
    <div class="notice notice-success is-dismissible">
        <p><?php _e('Registration form IDs saved successfully!', 'textdomain'); ?></p>
    </div>
<?php endif; ?>

<form method="POST" action="">
    <table class="form-table">
        <!-- Dropdown to Select Gravity Forms -->
        <tr>
            <th scope="row">
                <label for="registration_gform_ids"><?php _e('Select Gravity Forms for Registration', 'textdomain'); ?></label>
            </th>
            <td>
                <select id="registration_gform_ids" name="registration_gform_ids[]" multiple style="width: 100%; height: 200px;">
                    <?php foreach ($gravity_forms as $form): ?>
                        <option value="<?php echo esc_attr($form->id); ?>" <?php echo in_array($form->id, $selected_gform_ids) ? 'selected' : ''; ?>>
                            <?php echo esc_html($form->title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="description">&nbsp;<?php _e('Hold down the Ctrl (Windows) or Command (Mac) button to select multiple forms.', 'textdomain'); ?></p>
            </td>
        </tr>
    </table>

    <input type="hidden" name="save_registration_gform" value="1" />
    <p class="submit">
        <input type="submit" class="button button-primary" value="<?php _e('Save Changes', 'textdomain'); ?>" />
    </p>
</form>
