<?php
// Ensure to include this within a WordPress environment
defined('ABSPATH') || exit;

// Fetch club ID from the URL
$club_id = isset($_GET['club_id']) ? intval($_GET['club_id']) : 0;
if (!$club_id) {
    echo '<p>' . __('No club ID provided.', 'textdomain') . '</p>';
    return;
}

// Fetch existing EFT details for the club if available
global $wpdb;
$eft_details = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}eft_details WHERE club_id = %d", $club_id ) );

// Set default values for EFT details fields
$account_name   = $eft_details ? esc_attr($eft_details->account_name) : '';
$account_number = $eft_details ? esc_attr($eft_details->account_number) : '';
$bank_name      = $eft_details ? esc_attr($eft_details->bank_name) : '';
$branch_code    = $eft_details ? esc_attr($eft_details->branch_code) : '';


// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_eft_details'])) {
    $account_name   = sanitize_text_field($_POST['account_name']);
    $account_number = sanitize_text_field($_POST['account_number']);
    $bank_name      = sanitize_text_field($_POST['bank_name']);
    $branch_code    = sanitize_text_field($_POST['branch_code']);
    

    // Insert or update the EFT details in the database
    if ($eft_details) {
        // Update existing EFT details
        $wpdb->update(
            "{$wpdb->prefix}eft_details",
            [
                'account_name'   => $account_name,
                'account_number' => $account_number,
                'bank_name'      => $bank_name,
                'branch_code'    => $branch_code,
                
            ],
            ['club_id' => $club_id]
        );
    } else {
        // Insert new EFT details
        $wpdb->insert(
            "{$wpdb->prefix}eft_details",
            [
                'club_id'        => $club_id,
                'account_name'   => $account_name,
                'account_number' => $account_number,
                'bank_name'      => $bank_name,
                'branch_code'    => $branch_code,
                
            ]
        );
    }

    // Redirect to avoid form resubmission
    wp_redirect(add_query_arg(['club_id' => $club_id, 'updated' => true], $_SERVER['REQUEST_URI']));
    exit;
}

?>
<?php if (isset($_GET['updated']) && $_GET['updated'] == true): ?>
    <div class="notice notice-success is-dismissible">
        <p><?php _e('EFT details saved successfully!', 'textdomain'); ?></p>
    </div>
<?php endif; ?>

<form method="POST" action="">
    <table class="form-table">
        <tr>
            <th scope="row">
                <label for="account_name">
                    <?php _e('Account Name', 'textdomain'); ?>
                    <span class="woocommerce-help-tip" data-tip="<?php _e('Enter the account holder’s name as it appears on the bank account.', 'textdomain'); ?>"></span>
                </label>
            </th>
            <td>
                <input type="text" id="account_name" name="account_name" value="<?php echo $account_name; ?>" class="regular-text" required />
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="account_number">
                    <?php _e('Account Number', 'textdomain'); ?>
                    <span class="woocommerce-help-tip" data-tip="<?php _e('Provide the account number for the bank account.', 'textdomain'); ?>"></span>
                </label>
            </th>
            <td>
                <input type="text" id="account_number" name="account_number" value="<?php echo $account_number; ?>" class="regular-text" required />
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="bank_name">
                    <?php _e('Bank Name', 'textdomain'); ?>
                    <span class="woocommerce-help-tip" data-tip="<?php _e('Enter the name of the bank where the account is held.', 'textdomain'); ?>"></span>
                </label>
            </th>
            <td>
                <input type="text" id="bank_name" name="bank_name" value="<?php echo $bank_name; ?>" class="regular-text" required />
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="branch_code">
                    <?php _e('Branch Code', 'textdomain'); ?>
                    <span class="woocommerce-help-tip" data-tip="<?php _e('Enter the branch code for the bank account.', 'textdomain'); ?>"></span>
                </label>
            </th>
            <td>
                <input type="text" id="branch_code" name="branch_code" value="<?php echo $branch_code; ?>" class="regular-text" required />
            </td>
        </tr>
        
    </table>

    <input type="hidden" name="club_id" value="<?php echo $club_id; ?>" />
    <input type="hidden" name="save_eft_details" value="1" />
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
