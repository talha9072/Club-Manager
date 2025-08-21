<?php
// Fetch existing Stripe values
$stripe_data = $wpdb->get_row($wpdb->prepare(
    "SELECT 
        stripe_live_publishable_key, stripe_live_secret_key, stripe_live_webhook_secret,
        stripe_test_publishable_key, stripe_test_secret_key, stripe_test_webhook_secret,
        stripe_testmode_enabled
     FROM {$wpdb->prefix}clubs WHERE club_id = %d",
    $club_id
), ARRAY_A);

// Handle Stripe form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_stripe_credentials'])) {
    $stripe_update_data = [
        'stripe_live_publishable_key'  => sanitize_text_field($_POST['stripe_live_publishable_key']),
        'stripe_live_secret_key'       => sanitize_text_field($_POST['stripe_live_secret_key']),
        'stripe_live_webhook_secret'   => sanitize_text_field($_POST['stripe_live_webhook_secret']),
        'stripe_test_publishable_key'  => sanitize_text_field($_POST['stripe_test_publishable_key']),
        'stripe_test_secret_key'       => sanitize_text_field($_POST['stripe_test_secret_key']),
        'stripe_test_webhook_secret'   => sanitize_text_field($_POST['stripe_test_webhook_secret']),
        'stripe_testmode_enabled'      => isset($_POST['stripe_testmode_enabled']) ? 1 : 0,
    ];

    $wpdb->update("{$wpdb->prefix}clubs", $stripe_update_data, ['club_id' => $club_id]);
    wp_redirect(add_query_arg(['club_id' => $club_id, 'stripe_updated' => true], $_SERVER['REQUEST_URI']));
    exit;
}
?>

<?php if (isset($_GET['stripe_updated']) && $_GET['stripe_updated'] == true): ?>
    <div class="notice notice-success is-dismissible">
        <p><?php _e('Stripe credentials saved successfully!', 'textdomain'); ?></p>
    </div>
<?php endif; ?>

<form method="POST" action="">
    <h2><?php _e('Stripe Settings', 'textdomain'); ?></h2>
    <table class="form-table">
        <tr>
            <th><label for="stripe_live_publishable_key"><?php _e('Live Publishable Key', 'textdomain'); ?></label></th>
            <td><input type="text" name="stripe_live_publishable_key" id="stripe_live_publishable_key" value="<?php echo esc_attr($stripe_data['stripe_live_publishable_key'] ?? ''); ?>" class="regular-text"></td>
        </tr>
        <tr>
            <th><label for="stripe_live_secret_key"><?php _e('Live Secret Key', 'textdomain'); ?></label></th>
            <td><input type="text" name="stripe_live_secret_key" id="stripe_live_secret_key" value="<?php echo esc_attr($stripe_data['stripe_live_secret_key'] ?? ''); ?>" class="regular-text"></td>
        </tr>
        <tr>
            <th><label for="stripe_live_webhook_secret"><?php _e('Live Webhook Secret', 'textdomain'); ?></label></th>
            <td><input type="text" name="stripe_live_webhook_secret" id="stripe_live_webhook_secret" value="<?php echo esc_attr($stripe_data['stripe_live_webhook_secret'] ?? ''); ?>" class="regular-text"></td>
        </tr>
        <tr>
            <th><label for="stripe_test_publishable_key"><?php _e('Test Publishable Key', 'textdomain'); ?></label></th>
            <td><input type="text" name="stripe_test_publishable_key" id="stripe_test_publishable_key" value="<?php echo esc_attr($stripe_data['stripe_test_publishable_key'] ?? ''); ?>" class="regular-text"></td>
        </tr>
        <tr>
            <th><label for="stripe_test_secret_key"><?php _e('Test Secret Key', 'textdomain'); ?></label></th>
            <td><input type="text" name="stripe_test_secret_key" id="stripe_test_secret_key" value="<?php echo esc_attr($stripe_data['stripe_test_secret_key'] ?? ''); ?>" class="regular-text"></td>
        </tr>
        <tr>
            <th><label for="stripe_test_webhook_secret"><?php _e('Test Webhook Secret', 'textdomain'); ?></label></th>
            <td><input type="text" name="stripe_test_webhook_secret" id="stripe_test_webhook_secret" value="<?php echo esc_attr($stripe_data['stripe_test_webhook_secret'] ?? ''); ?>" class="regular-text"></td>
        </tr>
        <tr>
            <th><label for="stripe_testmode_enabled"><?php _e('Enable Test Mode', 'textdomain'); ?></label></th>
            <td>
                <input type="checkbox" name="stripe_testmode_enabled" id="stripe_testmode_enabled" value="1" <?php checked($stripe_data['stripe_testmode_enabled'] ?? 1, 1); ?>>
                <label for="stripe_testmode_enabled"><?php _e('Check to use test Stripe credentials', 'textdomain'); ?></label>
            </td>
        </tr>
    </table>

    <input type="hidden" name="save_stripe_credentials" value="1" />
    <p class="submit">
        <input type="submit" class="button button-primary" value="<?php _e('Save Stripe Settings', 'textdomain'); ?>" />
    </p>
</form>
