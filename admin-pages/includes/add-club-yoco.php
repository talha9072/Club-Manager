<?php
// Fetch existing Yoco values
$yoco_data = $wpdb->get_row($wpdb->prepare(
    "SELECT 
        yoco_live_secret_key,
        yoco_test_secret_key,
        yoco_test_mode_enabled
     FROM {$wpdb->prefix}clubs WHERE club_id = %d",
    $club_id
), ARRAY_A);

// Handle Yoco form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_yoco_credentials'])) {
    $yoco_update_data = [
        'yoco_live_secret_key'     => sanitize_text_field($_POST['yoco_live_secret_key']),
        'yoco_test_secret_key'     => sanitize_text_field($_POST['yoco_test_secret_key']),
        'yoco_test_mode_enabled'   => isset($_POST['yoco_test_mode_enabled']) ? 1 : 0,
    ];

    $wpdb->update("{$wpdb->prefix}clubs", $yoco_update_data, ['club_id' => $club_id]);
    wp_redirect(add_query_arg(['club_id' => $club_id, 'yoco_updated' => true], $_SERVER['REQUEST_URI']));
    exit;
}
?>

<?php if (isset($_GET['yoco_updated']) && $_GET['yoco_updated'] == true): ?>
    <div class="notice notice-success is-dismissible">
        <p><?php _e('Yoco credentials saved successfully!', 'textdomain'); ?></p>
    </div>
<?php endif; ?>

<form method="POST" action="">
    <h2><?php _e('Yoco Settings', 'textdomain'); ?></h2>
    <table class="form-table">
        <tr>
            <th><label for="yoco_live_secret_key"><?php _e('Live Secret Key', 'textdomain'); ?></label></th>
            <td><input type="text" name="yoco_live_secret_key" id="yoco_live_secret_key" value="<?php echo esc_attr($yoco_data['yoco_live_secret_key'] ?? ''); ?>" class="regular-text"></td>
        </tr>
        <tr>
            <th><label for="yoco_test_secret_key"><?php _e('Test Secret Key', 'textdomain'); ?></label></th>
            <td><input type="text" name="yoco_test_secret_key" id="yoco_test_secret_key" value="<?php echo esc_attr($yoco_data['yoco_test_secret_key'] ?? ''); ?>" class="regular-text"></td>
        </tr>
        <tr>
            <th><label for="yoco_test_mode_enabled"><?php _e('Enable Test Mode', 'textdomain'); ?></label></th>
            <td>
                <input type="checkbox" name="yoco_test_mode_enabled" id="yoco_test_mode_enabled" value="1" <?php checked($yoco_data['yoco_test_mode_enabled'] ?? 1, 1); ?>>
                <label for="yoco_test_mode_enabled"><?php _e('Check to use test Yoco credentials', 'textdomain'); ?></label>
            </td>
        </tr>
    </table>

    <input type="hidden" name="save_yoco_credentials" value="1" />
    <p class="submit">
        <input type="submit" class="button button-primary" value="<?php _e('Save Yoco Settings', 'textdomain'); ?>" />
    </p>
</form>