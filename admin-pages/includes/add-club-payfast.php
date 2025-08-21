<?php
// Fetch existing PayFast values
$payfast_data = $wpdb->get_row($wpdb->prepare(
    "SELECT 
        payfast_merchant_id, payfast_merchant_key, payfast_passphrase,
        sandbox_merchant_id, sandbox_merchant_key, sandbox_passphrase,
        sandbox_enabled
     FROM {$wpdb->prefix}clubs WHERE club_id = %d",
    $club_id
), ARRAY_A);

// Handle PayFast form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_payfast_credentials'])) {
    $payfast_update_data = [
        'payfast_merchant_id'     => sanitize_text_field($_POST['payfast_merchant_id']),
        'payfast_merchant_key'    => sanitize_text_field($_POST['payfast_merchant_key']),
        'payfast_passphrase'      => sanitize_text_field($_POST['payfast_passphrase']),
        'sandbox_merchant_id'     => sanitize_text_field($_POST['sandbox_merchant_id']),
        'sandbox_merchant_key'    => sanitize_text_field($_POST['sandbox_merchant_key']),
        'sandbox_passphrase'      => sanitize_text_field($_POST['sandbox_passphrase']),
        'sandbox_enabled'         => isset($_POST['sandbox_enabled']) ? 1 : 0,
    ];

    $wpdb->update("{$wpdb->prefix}clubs", $payfast_update_data, ['club_id' => $club_id]);
    wp_redirect(add_query_arg(['club_id' => $club_id, 'pf_updated' => true], $_SERVER['REQUEST_URI']));
    exit;
}
?>

<?php if (isset($_GET['pf_updated']) && $_GET['pf_updated'] == true): ?>
    <div class="notice notice-success is-dismissible">
        <p><?php _e('PayFast credentials saved successfully!', 'textdomain'); ?></p>
    </div>
<?php endif; ?>

<form method="POST" action="">
    <h2><?php _e('PayFast Settings', 'textdomain'); ?></h2>
    <table class="form-table">
        <tr>
            <th><label for="payfast_merchant_id"><?php _e('Live Merchant ID', 'textdomain'); ?></label></th>
            <td><input type="text" name="payfast_merchant_id" id="payfast_merchant_id" value="<?php echo esc_attr($payfast_data['payfast_merchant_id'] ?? ''); ?>" class="regular-text"></td>
        </tr>
        <tr>
            <th><label for="payfast_merchant_key"><?php _e('Live Merchant Key', 'textdomain'); ?></label></th>
            <td><input type="text" name="payfast_merchant_key" id="payfast_merchant_key" value="<?php echo esc_attr($payfast_data['payfast_merchant_key'] ?? ''); ?>" class="regular-text"></td>
        </tr>
        <tr>
            <th><label for="payfast_passphrase"><?php _e('Live Passphrase', 'textdomain'); ?></label></th>
            <td><input type="text" name="payfast_passphrase" id="payfast_passphrase" value="<?php echo esc_attr($payfast_data['payfast_passphrase'] ?? ''); ?>" class="regular-text"></td>
        </tr>
        <tr>
            <th><label for="sandbox_merchant_id"><?php _e('Sandbox Merchant ID', 'textdomain'); ?></label></th>
            <td><input type="text" name="sandbox_merchant_id" id="sandbox_merchant_id" value="<?php echo esc_attr($payfast_data['sandbox_merchant_id'] ?? ''); ?>" class="regular-text"></td>
        </tr>
        <tr>
            <th><label for="sandbox_merchant_key"><?php _e('Sandbox Merchant Key', 'textdomain'); ?></label></th>
            <td><input type="text" name="sandbox_merchant_key" id="sandbox_merchant_key" value="<?php echo esc_attr($payfast_data['sandbox_merchant_key'] ?? ''); ?>" class="regular-text"></td>
        </tr>
        <tr>
            <th><label for="sandbox_passphrase"><?php _e('Sandbox Passphrase', 'textdomain'); ?></label></th>
            <td><input type="text" name="sandbox_passphrase" id="sandbox_passphrase" value="<?php echo esc_attr($payfast_data['sandbox_passphrase'] ?? ''); ?>" class="regular-text"></td>
        </tr>
        <tr>
            <th><label for="sandbox_enabled"><?php _e('Enable Sandbox Mode', 'textdomain'); ?></label></th>
            <td>
                <input type="checkbox" name="sandbox_enabled" id="sandbox_enabled" value="1" <?php checked($payfast_data['sandbox_enabled'] ?? 0, 1); ?>>
                <label for="sandbox_enabled"><?php _e('Check to use sandbox.payfast.co.za', 'textdomain'); ?></label>
            </td>
        </tr>
    </table>

    <input type="hidden" name="save_payfast_credentials" value="1" />
    <p class="submit">
        <input type="submit" class="button button-primary" value="<?php _e('Save PayFast Settings', 'textdomain'); ?>" />
    </p>
</form>
