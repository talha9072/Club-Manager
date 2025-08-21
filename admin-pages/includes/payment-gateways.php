<?php
// Ensure to include this within a WordPress environment
defined('ABSPATH') || exit;

// Fetch club ID from the URL
$club_id = isset($_GET['club_id']) ? intval($_GET['club_id']) : 0;
if (!$club_id) {
    echo '<p>' . __('No club ID provided.', 'textdomain') . '</p>';
    return;
}

// Fetch existing payment gateway details for the club if available
global $wpdb;
$gateway_details = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}payment_gateways WHERE club_id = %d", $club_id));

// Set default value for Yoco Link
$yoco_link = $gateway_details ? esc_attr($gateway_details->yoco_link) : '';

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_payment_gateway'])) {
    $yoco_link = sanitize_text_field($_POST['yoco_link']);

    // Insert or update the payment gateway details in the database
    if ($gateway_details) {
        // Update existing gateway
        $wpdb->update(
            "{$wpdb->prefix}payment_gateways",
            [
                'yoco_link' => $yoco_link,
                'gateway_type' => 'yoco',
            ],
            ['club_id' => $club_id],
            ['%s', '%s'],
            ['%d']
        );
    } else {
        // Insert new gateway
        $wpdb->insert(
            "{$wpdb->prefix}payment_gateways",
            [
                'club_id'  => $club_id,
                'yoco_link' => $yoco_link,
                'gateway_type' => 'yoco',
            ],
            ['%d', '%s', '%s']
        );
    }

    // Redirect to avoid form resubmission
    wp_redirect(add_query_arg(['club_id' => $club_id, 'updated' => true], $_SERVER['REQUEST_URI']));
    exit;
}

?>
<?php if (isset($_GET['updated']) && $_GET['updated'] == true): ?>
    <div class="notice notice-success is-dismissible">
        <p><?php _e('Payment gateway details saved successfully!', 'textdomain'); ?></p>
    </div>
<?php endif; ?>

<form method="POST" action="">
    <table class="form-table">
        <tr>
            <th scope="row">
                <label for="yoco_link">
                    <?php _e('Yoco Payment Link', 'textdomain'); ?>
                    <span class="woocommerce-help-tip" data-tip="<?php _e('Provide the Yoco payment link.', 'textdomain'); ?>"></span>
                </label>
            </th>
            <td>
                <input type="text" id="yoco_link" name="yoco_link" value="<?php echo $yoco_link; ?>" class="regular-text" required />
            </td>
        </tr>
    </table>

    <input type="hidden" name="club_id" value="<?php echo $club_id; ?>" />
    <input type="hidden" name="save_payment_gateway" value="1" />
    <p class="submit">
        <input type="submit" class="button button-primary" value="<?php _e('Save Changes', 'textdomain'); ?>" />
    </p>
</form>

<script>
    jQuery(document).ready(function($) {
        // Initialize WooCommerce tooltips
        $('span.woocommerce-help-tip').each(function() {
            const tip = $(this).data('tip');
            $(this).tooltip({
                content: tip,
                show: null,
                hide: null
            });
        });
    });
</script>
