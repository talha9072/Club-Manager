<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Check if the club_id is provided
if (!isset($_GET['club_id']) || empty($_GET['club_id'])) {
    echo __('No club ID provided.', 'club-manager');
    exit;
}

global $wpdb;
$club_id = intval($_GET['club_id']);

// Fetch the club details from the database
$club = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}clubs WHERE club_id = %d",
    $club_id
));

// Set default values for club name, URL, and logo
$club_name = $club ? esc_attr($club->club_name) : '';
$club_url = $club ? esc_attr($club->club_url) : '/';
$club_logo = $club ? esc_url($club->club_logo) : '';
$selected_gform_id = $club ? intval($club->gform_id) : null; // Initialize selected_gform_id here

// Fetch EFT details
$eft_details = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}eft_details WHERE club_id = %d",
    $club_id
));

// Set default values for EFT details
$eft_account_name = $eft_details ? esc_attr($eft_details->account_name) : '';
$eft_account_number = $eft_details ? esc_attr($eft_details->account_number) : '';
$eft_bank_name = $eft_details ? esc_attr($eft_details->bank_name) : '';
$eft_branch_code = $eft_details ? esc_attr($eft_details->branch_code) : '';

// Fetch existing payment gateway details
$gateway_details = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}payment_gateways WHERE club_id = %d",
    $club_id
));

// Set default values for payment gateway fields
$yoco_link     = $gateway_details ? esc_attr($gateway_details->yoco_link) : '';

// set default (workflows) assigned to this club
$club_notifications = $club ? $club->notifications : '';
$selected_workflows = $club_notifications ? explode(',', $club_notifications) : [];

// Set default valuation form assigned to this club
$valuation_form_id = $club ? intval($club->gform_id) : null;


// Fetch all available AutomateWoo workflows
$workflow_results = $wpdb->get_results(
    "SELECT ID, post_title 
     FROM {$wpdb->posts} 
     WHERE post_type = 'aw_workflow' AND post_status IN ('publish', 'draft', 'disabled')"
);

$workflows = [];
if ($workflow_results) {
    foreach ($workflow_results as $workflow) {
        $workflows[$workflow->ID] = $workflow->post_title;
    }
}

// Handle Club Details form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_club_details'])) {
    $club_name = sanitize_text_field($_POST['club_name']);
    $club_url = sanitize_text_field($_POST['club_url']);
    $club_logo = esc_url_raw($_POST['club_logo']);
    $template_id = isset($_POST['template_id']) ? intval($_POST['template_id']) : null; // Add template_id

    // Update the club in the database
    $wpdb->update(
        "{$wpdb->prefix}clubs",
        array(
            'club_name'   => $club_name,
            'club_url'    => $club_url,
            'club_logo'   => $club_logo,
            'template_id' => $template_id // Include template_id in update
        ),
        array('club_id' => $club_id),
        array('%s', '%s', '%s', '%d'), // Include %d for template_id
        array('%d')
    );

    echo '<div class="notice notice-success is-dismissible"><p>' . __('Club details updated successfully.', 'club-manager') . '</p></div>';
}


// Handle EFT Details form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_eft_details'])) {
    global $wpdb;

    $eft_account_name = sanitize_text_field($_POST['eft_account_name']);
    $eft_account_number = sanitize_text_field($_POST['eft_account_number']);
    $eft_bank_name = sanitize_text_field($_POST['eft_bank_name']);
    $eft_branch_code = sanitize_text_field($_POST['eft_branch_code']);

    // Ensure club_id is defined
    $club_id = isset($_POST['club_id']) ? intval($_POST['club_id']) : 0;

    // Check if EFT details already exist for the given club_id
    $eft_details = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}eft_details WHERE club_id = %d",
            $club_id
        )
    );

    // Update or insert EFT details in the database
    if ($eft_details) {
        $wpdb->update(
            "{$wpdb->prefix}eft_details",
            array(
                'account_name' => $eft_account_name,
                'account_number' => $eft_account_number,
                'bank_name' => $eft_bank_name,
                'branch_code' => $eft_branch_code
            ),
            array('club_id' => $club_id),
            array('%s', '%s', '%s', '%s'),
            array('%d')
        );
    } else {
        $wpdb->insert(
            "{$wpdb->prefix}eft_details",
            array(
                'club_id' => $club_id,
                'account_name' => $eft_account_name,
                'account_number' => $eft_account_number,
                'bank_name' => $eft_bank_name,
                'branch_code' => $eft_branch_code
            ),
            array('%d', '%s', '%s', '%s', '%s')
        );
    }

    echo '<div class="notice notice-success is-dismissible"><p>' . __('EFT details updated successfully.', 'club-manager') . '</p></div>';
}







// Handle Payment Gateways form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_gateway_details'])) {
    $gateway_type  = sanitize_text_field('yoco'); // Default to 'yoco' since no dropdown is needed
    $merchant_id   = sanitize_text_field($_POST['merchant_id']);
    $merchant_key  = sanitize_text_field($_POST['merchant_key']);
    $api_key       = sanitize_text_field($_POST['api_key']);
    $secret_key    = sanitize_text_field($_POST['secret_key']);
    $yoco_link     = sanitize_text_field($_POST['yoco_link']);

    // Update or insert payment gateway details in the database
    if ($gateway_details) {
        $wpdb->update(
            "{$wpdb->prefix}payment_gateways",
            array(
                'gateway_type' => $gateway_type,
                'merchant_id' => $merchant_id,
                'merchant_key' => $merchant_key,
                'api_key' => $api_key,
                'secret_key' => $secret_key,
                'yoco_link' => $yoco_link
            ),
            array('club_id' => $club_id),
            array('%s', '%s', '%s', '%s', '%s', '%s'),
            array('%d')
        );
    } else {
        $wpdb->insert(
            "{$wpdb->prefix}payment_gateways",
            array(
                'club_id' => $club_id,
                'gateway_type' => $gateway_type,
                'merchant_id' => $merchant_id,
                'merchant_key' => $merchant_key,
                'api_key' => $api_key,
                'secret_key' => $secret_key,
                'yoco_link' => $yoco_link
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s')
        );
    }

    echo '<div class="notice notice-success is-dismissible"><p>' . __('Payment gateway details updated successfully.', 'club-manager') . '</p></div>';
}

// Handle Notifications form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_notifications'])) {
    $selected_notifications = isset($_POST['notifications']) ? array_map('intval', $_POST['notifications']) : [];
    $notifications_ids = implode(',', $selected_notifications);

    $wpdb->update(
        "{$wpdb->prefix}clubs",
        ['notifications' => $notifications_ids],
        ['club_id' => $club_id],
        ['%s'],
        ['%d']
    );

    echo '<div class="notice notice-success is-dismissible"><p>' . __('Notifications updated successfully.', 'club-manager') . '</p></div>';
}



// Fetch club ID from the URL
$club_id = isset($_GET['club_id']) ? intval($_GET['club_id']) : 0;
if (!$club_id) {
    echo '<p>' . __('No club ID provided.', 'textdomain') . '</p>';
    return;
}

// Fetch all WooCommerce products with the category "Membership"
$args = [
    'post_type' => 'product',
    'posts_per_page' => -1,
    'tax_query' => [
        [
            'taxonomy' => 'product_cat',
            'field' => 'name',
            'terms' => 'Membership',
        ],
    ],
];
$membership_products = get_posts($args);

// Set default products assigned to this club
$selected_products = [];
if (!empty($membership_products)) {
    foreach ($membership_products as $product) {
        $assigned_club_id = get_post_meta($product->ID, '_select_club_id', true);
        if (!empty($assigned_club_id) && intval($assigned_club_id) === $club_id) {
            $selected_products[] = $product->ID;
        }
    }
}

// Handle Products form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_membership_settings'])) {
    $selected_products = isset($_POST['product_ids']) ? array_map('intval', $_POST['product_ids']) : [];

    // Remove previously assigned products not selected this time
    foreach ($membership_products as $product) {
        $assigned_club_id = get_post_meta($product->ID, '_select_club_id', true);

        if (intval($assigned_club_id) === $club_id && !in_array($product->ID, $selected_products)) {
            delete_post_meta($product->ID, '_select_club_id');
            delete_post_meta($product->ID, '_select_club_name');
        }
    }

    // Assign club ID and club name to the selected products
    foreach ($selected_products as $product_id) {
        update_post_meta($product_id, '_select_club_id', $club_id);
        update_post_meta($product_id, '_select_club_name', $club_name);
    }

    echo '<div class="notice notice-success is-dismissible"><p>' . __('Products updated successfully.', 'club-manager') . '</p></div>';
}



// Fetch all available Gravity Forms
if (class_exists('GFAPI')) {
    $forms = GFAPI::get_forms();
} else {
    $forms = [];
}

// Handle Valuation Form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_valuation_form'])) {
    $valuation_form_id = isset($_POST['valuation_form_id']) ? intval($_POST['valuation_form_id']) : null;

    $wpdb->update(
        "{$wpdb->prefix}clubs",
        ['gform_id' => $valuation_form_id],
        ['club_id' => $club_id],
        ['%d'],
        ['%d']
    );

    echo '<div class="notice notice-success is-dismissible"><p>' . __('Valuation form updated successfully.', 'club-manager') . '</p></div>';
}

// Handle Event Form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_event_forms'])) {
    $event_form_ids = isset($_POST['event_form_ids']) ? array_map('intval', $_POST['event_form_ids']) : [];

    // Save the selected IDs as a comma-separated string
    $imploded_ids = implode(',', $event_form_ids);

    $wpdb->update(
        "{$wpdb->prefix}clubs",
        ['event_gform' => $imploded_ids], // Store as a comma-separated string
        ['club_id' => $club_id],
        ['%s'], // Format as a string
        ['%d']
    );

    echo '<div class="notice notice-success is-dismissible"><p>' . __('Event forms updated successfully.', 'club-manager') . '</p></div>';
}

// Fetch saved event_gform IDs for the multi-select dropdown
$event_gform_ids = $wpdb->get_var($wpdb->prepare(
    "SELECT event_gform FROM {$wpdb->prefix}clubs WHERE club_id = %d",
    $club_id
));

// Convert the comma-separated string back to an array
$selected_event_form_ids = $event_gform_ids ? explode(',', $event_gform_ids) : [];




// rides code
// Fetch saved rides_form IDs for the multi-select dropdown
$rides_form_ids = $wpdb->get_var($wpdb->prepare(
    "SELECT rides_form FROM {$wpdb->prefix}clubs WHERE club_id = %d",
    $club_id
));

// Convert the comma-separated string back to an array
$selected_rides_form_ids = $rides_form_ids ? explode(',', $rides_form_ids) : [];

// Handle Rides Form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_rides_forms'])) {
    $rides_form_ids = isset($_POST['rides_form_ids']) ? array_map('intval', $_POST['rides_form_ids']) : [];

    // Save the selected IDs as a comma-separated string
    $imploded_ids = implode(',', $rides_form_ids);

    $update_result = $wpdb->update(
        "{$wpdb->prefix}clubs",
        ['rides_form' => $imploded_ids], // Store as a comma-separated string
        ['club_id' => $club_id],
        ['%s'], // Format as a string
        ['%d']
    );

    if ($update_result !== false) {
        wp_redirect(add_query_arg(['club_id' => $club_id, 'rides_forms_updated' => '1'], $_SERVER['REQUEST_URI']));
        exit;
    } else {
        echo '<div class="notice notice-error is-dismissible"><p>' . __('Failed to update Rides forms.', 'club-manager') . '</p></div>';
    }
}

// Display success message if updated
if (isset($_GET['rides_forms_updated']) && $_GET['rides_forms_updated'] == '1') {
    echo '<div class="notice notice-success is-dismissible"><p>' . __('Rides forms updated successfully.', 'club-manager') . '</p></div>';
}


// payfast
// Handle PayFast form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_payfast_settings'])) {
    $payfast_fields = [
        'payfast_merchant_id'   => sanitize_text_field($_POST['payfast_merchant_id']),
        'payfast_merchant_key'  => sanitize_text_field($_POST['payfast_merchant_key']),
        'payfast_passphrase'    => sanitize_text_field($_POST['payfast_passphrase']),
        'sandbox_merchant_id'   => sanitize_text_field($_POST['sandbox_merchant_id']),
        'sandbox_merchant_key'  => sanitize_text_field($_POST['sandbox_merchant_key']),
        'sandbox_passphrase'    => sanitize_text_field($_POST['sandbox_passphrase']),
        'sandbox_enabled'       => isset($_POST['sandbox_enabled']) ? 1 : 0,
    ];

    $update_result = $wpdb->update(
        "{$wpdb->prefix}clubs",
        $payfast_fields,
        ['club_id' => $club_id],
        ['%s','%s','%s','%s','%s','%s','%d'],
        ['%d']
    );

    if ($update_result !== false) {
        wp_redirect(add_query_arg(['club_id' => $club_id, 'payfast_updated' => '1'], $_SERVER['REQUEST_URI']));
        exit;
    } else {
        echo '<div class="notice notice-error is-dismissible"><p>' . __('Failed to update PayFast settings.', 'club-manager') . '</p></div>';
    }
}

// Show success message
if (isset($_GET['payfast_updated']) && $_GET['payfast_updated'] == '1') {
    echo '<div class="notice notice-success is-dismissible"><p>' . __('PayFast settings updated successfully.', 'club-manager') . '</p></div>';
}


// Stripe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_stripe_settings'])) {
    $stripe_fields = [
        'stripe_live_publishable_key'   => sanitize_text_field($_POST['stripe_live_publishable_key']),
        'stripe_live_secret_key'        => sanitize_text_field($_POST['stripe_live_secret_key']),
        'stripe_live_webhook_secret'    => sanitize_text_field($_POST['stripe_live_webhook_secret']),
        'stripe_test_publishable_key'   => sanitize_text_field($_POST['stripe_test_publishable_key']),
        'stripe_test_secret_key'        => sanitize_text_field($_POST['stripe_test_secret_key']),
        'stripe_test_webhook_secret'    => sanitize_text_field($_POST['stripe_test_webhook_secret']),
        'stripe_testmode_enabled'       => isset($_POST['stripe_testmode_enabled']) ? 1 : 0,
    ];

    $update_result = $wpdb->update(
        "{$wpdb->prefix}clubs",
        $stripe_fields,
        ['club_id' => $club_id],
        ['%s','%s','%s','%s','%s','%s','%d'],
        ['%d']
    );

    if ($update_result !== false) {
        wp_redirect(add_query_arg(['club_id' => $club_id, 'stripe_updated' => '1'], $_SERVER['REQUEST_URI']));
        exit;
    } else {
        echo '<div class="notice notice-error is-dismissible"><p>' . __('Failed to update Stripe settings.', 'club-manager'); '</p></div>';
    }
}

if (isset($_GET['stripe_updated']) && $_GET['stripe_updated'] == '1') {
    echo '<div class="notice notice-success is-dismissible"><p>' . __('Stripe settings updated successfully.', 'club-manager') . '</p></div>';
}


// yoco payment gateway
// Yoco
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_yoco_settings'])) {
    $yoco_fields = [
        'yoco_live_secret_key'     => sanitize_text_field($_POST['yoco_live_secret_key']),
        'yoco_test_secret_key'     => sanitize_text_field($_POST['yoco_test_secret_key']),
        'yoco_test_mode_enabled'   => isset($_POST['yoco_test_mode_enabled']) ? 1 : 0,
    ];

    $update_result = $wpdb->update(
        "{$wpdb->prefix}clubs",
        $yoco_fields,
        ['club_id' => $club_id],
        ['%s','%s','%d'],
        ['%d']
    );

    if ($update_result !== false) {
        wp_redirect(add_query_arg(['club_id' => $club_id, 'yoco_updated' => '1'], $_SERVER['REQUEST_URI']));
        exit;
    } else {
        echo '<div class="notice notice-error is-dismissible"><p>' . __('Failed to update Yoco settings.', 'club-manager') . '</p></div>';
    }
}

if (isset($_GET['yoco_updated']) && $_GET['yoco_updated'] == '1') {
    echo '<div class="notice notice-success is-dismissible"><p>' . __('Yoco settings updated successfully.', 'club-manager') . '</p></div>';
}


// Fetch saved registration_form IDs for the multi-select dropdown
$registration_form_ids = $wpdb->get_var($wpdb->prepare(
    "SELECT registration_form FROM {$wpdb->prefix}clubs WHERE club_id = %d",
    $club_id
));

// Convert the comma-separated string back to an array
$selected_registration_form_ids = $registration_form_ids ? explode(',', $registration_form_ids) : [];

// Handle Registration Form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_registration_forms'])) {
    $registration_form_ids = isset($_POST['registration_form_ids']) ? array_map('intval', $_POST['registration_form_ids']) : [];

    // Save the selected IDs as a comma-separated string
    $imploded_ids = implode(',', $registration_form_ids);

    $update_result = $wpdb->update(
        "{$wpdb->prefix}clubs",
        ['registration_form' => $imploded_ids], // Store as a comma-separated string
        ['club_id' => $club_id],
        ['%s'], // Format as a string
        ['%d']
    );

    if ($update_result !== false) {
        wp_redirect(add_query_arg(['club_id' => $club_id, 'registration_forms_updated' => '1'], $_SERVER['REQUEST_URI']));
        exit;
    } else {
        echo '<div class="notice notice-error is-dismissible"><p>' . __('Failed to update Registration forms.', 'club-manager') . '</p></div>';
    }
}

// Display success message if updated
if (isset($_GET['registration_forms_updated']) && $_GET['registration_forms_updated'] == '1') {
    echo '<div class="notice notice-success is-dismissible"><p>' . __('Registration forms updated successfully.', 'club-manager') . '</p></div>';
}



// Redirect to avoid form resubmission on page reload
if (!empty($_POST)) {
    wp_redirect(add_query_arg(['club_id' => $club_id, 'updated' => true], $_SERVER['REQUEST_URI']));
    exit;
}
?>


<div class="wrap">
    <h1><?php echo __('Edit Club', 'club-manager'); ?></h1>

    <!-- Tabs -->
    <h2 class="nav-tab-wrapper">
        <a href="#tab-club-details" class="nav-tab nav-tab-active"><?php _e('Club Details', 'club-manager'); ?></a>
        <a href="#tab-eft-details" class="nav-tab"><?php _e('EFT Details', 'club-manager'); ?></a>
        <a href="#tab-payment-gateways" class="nav-tab"><?php _e('Payment Gateways', 'club-manager'); ?></a>
        <a href="#tab-members-details" class="nav-tab"><?php _e('Club Managers', 'club-manager'); ?></a>
        <a href="#tab-notifications" class="nav-tab"><?php _e('Notifications', 'club-manager'); ?></a>
        <a href="#tab-valuation" class="nav-tab"><?php _e('Valuation Form', 'club-manager'); ?></a>
        <a href="#tab-event" class="nav-tab"><?php _e('Event Forms', 'club-manager'); ?></a>
        <a href="#tab-product" class="nav-tab"><?php _e('Membership Product', 'club-manager'); ?></a>
        <a href="#tab-rides" class="nav-tab"><?php _e('Rides Forms', 'club-manager'); ?></a>
        <a href="#tab-registration" class="nav-tab"><?php _e('Registration Forms', 'club-manager'); ?></a>
        



    </h2>

    <!-- Club Details Tab -->
    <div id="tab-club-details" class="tab-content" style="display: block;">
    <h3>Club Details</h3>
    <form method="post">
        <table class="form-table">
            <tr>
                <th><label for="club_name"><?php _e('Club Name', 'club-manager'); ?></label></th>
                <td><input type="text" name="club_name" id="club_name" value="<?php echo esc_attr($club_name); ?>" class="regular-text" required></td>
            </tr>
            <tr>
                <th><label for="club_url"><?php _e('Club URL', 'club-manager'); ?></label></th>
                <td>
                    <input type="text" name="club_url" id="club_url" value="<?php echo esc_url($club_url); ?>" class="regular-text">
                    <p class="description"><?php echo __('URL should start with a slash (/) and use hyphens for spaces.', 'club-manager'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="club_logo"><?php _e('Logo', 'club-manager'); ?></label></th>
                <td>
                    <div class="club-logo-wrapper">
                        <img id="club-logo-preview" src="<?php echo $club_logo; ?>" style="max-width: 150px; <?php echo !$club_logo ? 'display:none;' : ''; ?>" />
                        <input type="hidden" id="club_logo" name="club_logo" value="<?php echo $club_logo; ?>" />
                        <button type="button" class="button upload-image"><?php echo __('Change Image', 'club-manager'); ?></button>
                        <button type="button" class="button remove-image" style="<?php echo !$club_logo ? 'display:none;' : ''; ?>"><?php echo __('Remove Image', 'club-manager'); ?></button>
                    </div>
                </td>
            </tr>
            
        </table>
        <p class="submit">
            <input type="submit" name="save_club_details" id="save_club_details" class="button button-primary" value="<?php _e('Save Changes', 'club-manager'); ?>">
        </p>
    </form>
</div>


 <!-- EFT Details Tab -->
<div id="tab-eft-details" class="tab-content" style="display: none;">
    <h3>EFT Details</h3>
    <form method="post">
        <table class="form-table">
            <tr>
                <th><label for="eft_account_name"><?php _e('Account Name', 'club-manager'); ?></label></th>
                <td><input type="text" name="eft_account_name" id="eft_account_name" value="<?php echo isset($eft_account_name) ? esc_attr($eft_account_name) : ''; ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="eft_account_number"><?php _e('Account Number', 'club-manager'); ?></label></th>
                <td><input type="text" name="eft_account_number" id="eft_account_number" value="<?php echo isset($eft_account_number) ? esc_attr($eft_account_number) : ''; ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="eft_bank_name"><?php _e('Bank Name', 'club-manager'); ?></label></th>
                <td><input type="text" name="eft_bank_name" id="eft_bank_name" value="<?php echo isset($eft_bank_name) ? esc_attr($eft_bank_name) : ''; ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="eft_branch_code"><?php _e('Branch Code', 'club-manager'); ?></label></th>
                <td><input type="text" name="eft_branch_code" id="eft_branch_code" value="<?php echo isset($eft_branch_code) ? esc_attr($eft_branch_code) : ''; ?>" class="regular-text"></td>
            </tr>
        </table>
        <p class="submit">
            <input type="hidden" name="club_id" value="<?php echo esc_attr($club_id); ?>">
            <input type="submit" name="save_eft_details" id="save_eft_details" class="button button-primary" value="<?php _e('Save Changes', 'club-manager'); ?>">
        </p>
    </form>
</div>


 <!-- Payment Gateways Tab with Sub-Tabs -->
<div id="tab-payment-gateways" class="tab-content" style="display: none;">
    
    <!-- Sub-navigation -->
    <ul class="sub-tab-nav" style="display: flex; gap: 20px; margin-bottom: 20px;">
        <li><a href="#" class="sub-tab-link sub-tab-active" data-target="yoco-gateway"><?php _e('Yoco & Other |', 'club-manager'); ?></a></li>
        <li><a href="#" class="sub-tab-link" data-target="payfast-gateway"><?php _e('PayFast', 'club-manager'); ?></a></li>
        <li><a href="#" class="sub-tab-link" data-target="stripe-gateway"><?php _e('Stripe', 'club-manager'); ?></a></li>
        <li><a href="#" class="sub-tab-link" data-target="yocomain-gateway"><?php _e('Yoco Gateway', 'club-manager'); ?></a></li>
    </ul>

    <!-- Yoco & Other Payment Gateway Form -->
    <div class="sub-tab-content" id="yoco-gateway" style="display: block;">
        <form method="post">
            <table class="form-table">
                <tr>
                    <th><label for="yoco_link"><?php _e('Yoco Link', 'club-manager'); ?></label></th>
                    <td><input type="text" name="yoco_link" id="yoco_link" value="<?php echo $yoco_link; ?>" class="regular-text" ></td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" name="save_gateway_details" id="save_gateway_details" class="button button-primary" value="<?php _e('Save Changes', 'club-manager'); ?>">
            </p>
        </form>
    </div>

    <!-- PayFast Payment Gateway Form -->
    <div class="sub-tab-content" id="payfast-gateway" style="display: none;">
        <form method="post">
            <table class="form-table">
                <tr>
                    <th><label for="payfast_merchant_id"><?php _e('Live Merchant ID', 'club-manager'); ?></label></th>
                    <td><input type="text" name="payfast_merchant_id" id="payfast_merchant_id" value="<?php echo esc_attr($club->payfast_merchant_id ?? ''); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="payfast_merchant_key"><?php _e('Live Merchant Key', 'club-manager'); ?></label></th>
                    <td><input type="text" name="payfast_merchant_key" id="payfast_merchant_key" value="<?php echo esc_attr($club->payfast_merchant_key ?? ''); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="payfast_passphrase"><?php _e('Live Passphrase', 'club-manager'); ?></label></th>
                    <td><input type="text" name="payfast_passphrase" id="payfast_passphrase" value="<?php echo esc_attr($club->payfast_passphrase ?? ''); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="sandbox_merchant_id"><?php _e('Sandbox Merchant ID', 'club-manager'); ?></label></th>
                    <td><input type="text" name="sandbox_merchant_id" id="sandbox_merchant_id" value="<?php echo esc_attr($club->sandbox_merchant_id ?? ''); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="sandbox_merchant_key"><?php _e('Sandbox Merchant Key', 'club-manager'); ?></label></th>
                    <td><input type="text" name="sandbox_merchant_key" id="sandbox_merchant_key" value="<?php echo esc_attr($club->sandbox_merchant_key ?? ''); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="sandbox_passphrase"><?php _e('Sandbox Passphrase', 'club-manager'); ?></label></th>
                    <td><input type="text" name="sandbox_passphrase" id="sandbox_passphrase" value="<?php echo esc_attr($club->sandbox_passphrase ?? ''); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="sandbox_enabled"><?php _e('Enable Sandbox Mode', 'club-manager'); ?></label></th>
                    <td>
                        <input type="checkbox" name="sandbox_enabled" id="sandbox_enabled" value="1" <?php checked($club->sandbox_enabled ?? 0, 1); ?>>
                        <label for="sandbox_enabled"><?php _e('Check this to enable PayFast sandbox mode for this club.', 'club-manager'); ?></label>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" name="save_payfast_settings" class="button button-primary" value="<?php _e('Save Changes', 'club-manager'); ?>">
            </p>
        </form>
    </div>
    <!-- Stripe Payment Gateway Form -->
<div class="sub-tab-content" id="stripe-gateway" style="display: none;">
    <form method="post">
        <table class="form-table">
            <tr>
                <th><label for="stripe_live_publishable_key"><?php _e('Live Publishable Key', 'club-manager'); ?></label></th>
                <td><input type="text" name="stripe_live_publishable_key" id="stripe_live_publishable_key" value="<?php echo esc_attr($club->stripe_live_publishable_key ?? ''); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="stripe_live_secret_key"><?php _e('Live Secret Key', 'club-manager'); ?></label></th>
                <td><input type="text" name="stripe_live_secret_key" id="stripe_live_secret_key" value="<?php echo esc_attr($club->stripe_live_secret_key ?? ''); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="stripe_live_webhook_secret"><?php _e('Live Webhook Secret', 'club-manager'); ?></label></th>
                <td><input type="text" name="stripe_live_webhook_secret" id="stripe_live_webhook_secret" value="<?php echo esc_attr($club->stripe_live_webhook_secret ?? ''); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="stripe_test_publishable_key"><?php _e('Test Publishable Key', 'club-manager'); ?></label></th>
                <td><input type="text" name="stripe_test_publishable_key" id="stripe_test_publishable_key" value="<?php echo esc_attr($club->stripe_test_publishable_key ?? ''); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="stripe_test_secret_key"><?php _e('Test Secret Key', 'club-manager'); ?></label></th>
                <td><input type="text" name="stripe_test_secret_key" id="stripe_test_secret_key" value="<?php echo esc_attr($club->stripe_test_secret_key ?? ''); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="stripe_test_webhook_secret"><?php _e('Test Webhook Secret', 'club-manager'); ?></label></th>
                <td><input type="text" name="stripe_test_webhook_secret" id="stripe_test_webhook_secret" value="<?php echo esc_attr($club->stripe_test_webhook_secret ?? ''); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="stripe_testmode_enabled"><?php _e('Enable Test Mode', 'club-manager'); ?></label></th>
                <td>
                    <input type="checkbox" name="stripe_testmode_enabled" id="stripe_testmode_enabled" value="1" <?php checked($club->stripe_testmode_enabled ?? 0, 1); ?>>
                    <label for="stripe_testmode_enabled"><?php _e('Check this to use test Stripe keys for this club.', 'club-manager'); ?></label>
                </td>
            </tr>
        </table>
        <p class="submit">
            <input type="submit" name="save_stripe_settings" class="button button-primary" value="<?php _e('Save Changes', 'club-manager'); ?>">
        </p>
    </form>
</div>

<!-- Yoco Gateway Form -->
<div class="sub-tab-content" id="yocomain-gateway" style="display: none;">
    <form method="post">
        <table class="form-table">
            <tr>
                <th><label for="yoco_live_secret_key"><?php _e('Live Secret Key', 'club-manager'); ?></label></th>
                <td><input type="text" name="yoco_live_secret_key" id="yoco_live_secret_key" value="<?php echo esc_attr($club->yoco_live_secret_key ?? ''); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="yoco_test_secret_key"><?php _e('Test Secret Key', 'club-manager'); ?></label></th>
                <td><input type="text" name="yoco_test_secret_key" id="yoco_test_secret_key" value="<?php echo esc_attr($club->yoco_test_secret_key ?? ''); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="yoco_test_mode_enabled"><?php _e('Enable Test Mode', 'club-manager'); ?></label></th>
                <td>
                    <input type="checkbox" name="yoco_test_mode_enabled" id="yoco_test_mode_enabled" value="1" <?php checked($club->yoco_test_mode_enabled ?? 0, 1); ?>>
                    <label for="yoco_test_mode_enabled"><?php _e('Check this to use test keys for this club.', 'club-manager'); ?></label>
                </td>
            </tr>
        </table>
        <p class="submit">
            <input type="submit" name="save_yoco_settings" class="button button-primary" value="<?php _e('Save Changes', 'club-manager'); ?>">
        </p>
    </form>
</div>


</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Sub-tabs in Payment Gateways
    const subTabLinks = document.querySelectorAll('.sub-tab-link');
    const subTabContents = document.querySelectorAll('.sub-tab-content');

    // Define styles
    const activeStyles = "color:#0073aa; text-decoration: none;";
    const inactiveStyles = "color:black; text-decoration: none;";

    function updateTabStyles() {
        subTabLinks.forEach(link => {
            if (link.classList.contains('sub-tab-active')) {
                link.style.cssText = activeStyles;
            } else {
                link.style.cssText = inactiveStyles;
            }
        });
    }

    subTabLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            subTabLinks.forEach(l => l.classList.remove('sub-tab-active'));
            subTabContents.forEach(c => c.style.display = 'none');
            this.classList.add('sub-tab-active');
            document.getElementById(this.dataset.target).style.display = 'block';
            updateTabStyles();
        });
    });

    // Initial style update
    updateTabStyles();
});
</script>




    <!-- Members Details Tab -->
    <div id="tab-members-details" class="tab-content" style="display: none;">
        <div>
            <?php require_once CLUB_MANAGER_PLUGIN_DIR . 'admin-pages/includes/add-club-members.php'; ?>
        </div>
    </div>
    <!-- Notification tab -->
    <div id="tab-notifications" class="tab-content" style="display: none;">
    <h3><?php _e('Notifications', 'club-manager'); ?></h3>
    <form method="post">
        <table class="form-table">
            <tr>
                <th><label for="notifications"><?php _e('Manage Notifications', 'club-manager'); ?></label></th>
                <td>
                    <select id="notifications" name="notifications[]" multiple="multiple" style="width: 100%; height: 200px;">
                        <?php
                        if (empty($workflows)) {
                            echo '<option disabled>' . __('No workflows found.', 'club-manager') . '</option>';
                        } else {
                            foreach ($workflows as $workflow_id => $workflow_title) {
                                $selected = in_array($workflow_id, $selected_workflows) ? 'selected' : '';
                                echo '<option value="' . esc_attr($workflow_id) . '" ' . $selected . '>' . esc_html($workflow_title) . '</option>';
                            }
                        }
                        ?>
                    </select>
                    <p class="description"><?php _e('Select workflows to manage notifications.', 'club-manager'); ?></p>
                </td>
            </tr>
        </table>
        <p class="submit">
            <input type="submit" name="save_notifications" class="button button-primary" value="<?php _e('Save Changes', 'club-manager'); ?>">
        </p>
    </form>
</div>

 <!-- Valuation tab -->
 <div id="tab-valuation" class="tab-content" style="display: none;">
    <h3><?php _e('Valuation Form', 'club-manager'); ?></h3>
    <form method="post">
        <table class="form-table">
            <tr>
                <th><label for="valuation_form_id"><?php _e('Select Valuation Form', 'club-manager'); ?></label></th>
                <td>
                <select id="valuation_form_id" name="valuation_form_id" style="width: 100%; ">
    <option value="" <?php selected($selected_gform_id, null); ?>><?php _e('None', 'club-manager'); ?></option>
    <?php
    if (empty($forms)) {
        echo '<option disabled>' . __('No forms found.', 'club-manager') . '</option>';
    } else {
        foreach ($forms as $form) {
            $selected = $selected_gform_id == $form['id'] ? 'selected' : '';
            echo '<option value="' . esc_attr($form['id']) . '" ' . $selected . '>' . esc_html($form['title'] . ' (ID: ' . $form['id'] . ')') . '</option>';
        }
    }
    ?>
</select>

                    <p class="description"><?php _e('Select a Gravity Form to use as the valuation form.', 'club-manager'); ?></p>
                </td>
            </tr>
        </table>
        <p class="submit">
            <input type="submit" name="save_valuation_form" class="button button-primary" value="<?php _e('Save Changes', 'club-manager'); ?>">
        </p>
    </form>
</div>
<!-- Event Tab -->
<div id="tab-event" class="tab-content" style="display: none;">
    <h3><?php _e('Event Forms', 'club-manager'); ?></h3>
    <form method="post">
        <table class="form-table">
            <tr>
                <th><label for="event_form_ids"><?php _e('Select Event Forms', 'club-manager'); ?></label></th>
                <td>
                    <select id="event_form_ids" name="event_form_ids[]" multiple style="width: 100%; height: 200px;">
                        <?php
                        if (empty($forms)) {
                            echo '<option disabled>' . __('No forms found.', 'club-manager') . '</option>';
                        } else {
                            foreach ($forms as $form) {
                                $selected = in_array($form['id'], $selected_event_form_ids) ? 'selected' : '';
                                echo '<option value="' . esc_attr($form['id']) . '" ' . $selected . '>' . esc_html($form['title'] . ' (ID: ' . $form['id'] . ')') . '</option>';
                            }
                        }
                        ?>
                    </select>
                    <p class="description"><?php _e('Select one or more Gravity Forms to use as event forms. Hold down Ctrl (Windows) or Command (Mac) to select multiple.', 'club-manager'); ?></p>
                </td>
            </tr>
        </table>
        <p class="submit">
            <input type="submit" name="save_event_forms" class="button button-primary" value="<?php _e('Save Changes', 'club-manager'); ?>">
        </p>
    </form>
</div>


<!-- Product tab -->
<div id="tab-product" class="tab-content" style="display: none;">
    <h3><?php _e('Manage Membership Products', 'club-manager'); ?></h3>
    <form method="post">
        <table class="form-table">
            <tr>
                <th><label for="product_ids"><?php _e('Select Membership Products', 'club-manager'); ?></label></th>
                <td>
                    <select id="product_ids" name="product_ids[]" multiple="multiple" style="width: 100%; height: 200px;">
                        <?php
                        if (empty($membership_products)) {
                            echo '<option disabled>' . __('No membership products found.', 'club-manager') . '</option>';
                        } else {
                            foreach ($membership_products as $product) {
                                // Check if the product is already assigned to another club
                                $assigned_club_id = get_post_meta($product->ID, '_select_club_id', true);
                                $selected = in_array($product->ID, $selected_products) ? 'selected' : '';

                                // Show the product only if it's unassigned or assigned to the current club
                                if (empty($assigned_club_id) || intval($assigned_club_id) === $club_id) {
                                    echo '<option value="' . esc_attr($product->ID) . '" ' . $selected . '>' . esc_html($product->post_title) . '</option>';
                                }
                            }
                        }
                        ?>
                    </select>
                    <p class="description"><?php _e('Select one or more membership products to associate with this club.', 'club-manager'); ?></p>
                </td>
            </tr>
        </table>
        <p class="submit">
            <input type="submit" name="save_membership_settings" class="button button-primary" value="<?php _e('Save Changes', 'club-manager'); ?>">
        </p>
    </form>
</div>


<!-- Rides Forms Tab -->
<div id="tab-rides" class="tab-content" style="display: none;">
    <h3><?php _e('Rides Forms', 'club-manager'); ?></h3>
    <form method="post">
        <table class="form-table">
            <tr>
                <th><label for="rides_form_ids"><?php _e('Select Rides Forms', 'club-manager'); ?></label></th>
                <td>
                    <select id="rides_form_ids" name="rides_form_ids[]" multiple style="width: 100%; height: 200px;">
                        <?php
                        if (empty($forms)) {
                            echo '<option disabled>' . __('No forms found.', 'club-manager') . '</option>';
                        } else {
                            foreach ($forms as $form) {
                                $selected = in_array($form['id'], $selected_rides_form_ids) ? 'selected' : '';
                                echo '<option value="' . esc_attr($form['id']) . '" ' . $selected . '>' . esc_html($form['title'] . ' (ID: ' . $form['id'] . ')') . '</option>';
                            }
                        }
                        ?>
                    </select>
                    <p class="description"><?php _e('Select one or more Gravity Forms to use as rides forms. Hold down Ctrl (Windows) or Command (Mac) to select multiple.', 'club-manager'); ?></p>
                </td>
            </tr>
        </table>
        <p class="submit">
            <input type="submit" name="save_rides_forms" class="button button-primary" value="<?php _e('Save Changes', 'club-manager'); ?>">
        </p>
    </form>
</div>


<!-- Registration Forms Tab -->
<div id="tab-registration" class="tab-content" style="display: none;">
    <h3><?php _e('Registration Forms', 'club-manager'); ?></h3>
    <form method="post">
        <table class="form-table">
            <tr>
                <th><label for="registration_form_ids"><?php _e('Select Registration Forms', 'club-manager'); ?></label></th>
                <td>
                    <select id="registration_form_ids" name="registration_form_ids[]" multiple style="width: 100%; height: 200px;">
                        <?php
                        if (empty($forms)) {
                            echo '<option disabled>' . __('No forms found.', 'club-manager') . '</option>';
                        } else {
                            foreach ($forms as $form) {
                                $selected = in_array($form['id'], $selected_registration_form_ids) ? 'selected' : '';
                                echo '<option value="' . esc_attr($form['id']) . '" ' . $selected . '>' . esc_html($form['title'] . ' (ID: ' . $form['id'] . ')') . '</option>';
                            }
                        }
                        ?>
                    </select>
                    <p class="description"><?php _e('Select one or more Gravity Forms to use as registration forms. Hold down Ctrl (Windows) or Command (Mac) to select multiple.', 'club-manager'); ?></p>
                </td>
            </tr>
        </table>
        <p class="submit">
            <input type="submit" name="save_registration_forms" class="button button-primary" value="<?php _e('Save Changes', 'club-manager'); ?>">
        </p>
    </form>
</div>

<!-- PayFast Settings Tab -->
<div id="tab-payfast" class="tab-content" style="display: none;">
    <h3><?php _e('PayFast Settings', 'club-manager'); ?></h3>
    <form method="post">
        <table class="form-table">
            <tr>
                <th><label for="payfast_merchant_id"><?php _e('Live Merchant ID', 'club-manager'); ?></label></th>
                <td><input type="text" name="payfast_merchant_id" id="payfast_merchant_id" value="<?php echo esc_attr($club->payfast_merchant_id ?? ''); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="payfast_merchant_key"><?php _e('Live Merchant Key', 'club-manager'); ?></label></th>
                <td><input type="text" name="payfast_merchant_key" id="payfast_merchant_key" value="<?php echo esc_attr($club->payfast_merchant_key ?? ''); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="payfast_passphrase"><?php _e('Live Passphrase', 'club-manager'); ?></label></th>
                <td><input type="text" name="payfast_passphrase" id="payfast_passphrase" value="<?php echo esc_attr($club->payfast_passphrase ?? ''); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="sandbox_merchant_id"><?php _e('Sandbox Merchant ID', 'club-manager'); ?></label></th>
                <td><input type="text" name="sandbox_merchant_id" id="sandbox_merchant_id" value="<?php echo esc_attr($club->sandbox_merchant_id ?? ''); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="sandbox_merchant_key"><?php _e('Sandbox Merchant Key', 'club-manager'); ?></label></th>
                <td><input type="text" name="sandbox_merchant_key" id="sandbox_merchant_key" value="<?php echo esc_attr($club->sandbox_merchant_key ?? ''); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="sandbox_passphrase"><?php _e('Sandbox Passphrase', 'club-manager'); ?></label></th>
                <td><input type="text" name="sandbox_passphrase" id="sandbox_passphrase" value="<?php echo esc_attr($club->sandbox_passphrase ?? ''); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="sandbox_enabled"><?php _e('Enable Sandbox Mode', 'club-manager'); ?></label></th>
                <td>
                    <input type="checkbox" name="sandbox_enabled" id="sandbox_enabled" value="1" <?php checked($club->sandbox_enabled ?? 0, 1); ?>>
                    <label for="sandbox_enabled"><?php _e('Check this to enable PayFast sandbox mode for this club.', 'club-manager'); ?></label>
                </td>
            </tr>
        </table>
        <p class="submit">
            <input type="submit" name="save_payfast_settings" class="button button-primary" value="<?php _e('Save Changes', 'club-manager'); ?>">
        </p>
    </form>
</div>


</div>


<script>
    document.addEventListener('DOMContentLoaded', function () {
        const tabs = document.querySelectorAll('.nav-tab');
        const contents = document.querySelectorAll('.tab-content');

        tabs.forEach((tab, i) => {
            tab.addEventListener('click', function (e) {
                e.preventDefault();
                tabs.forEach(tab => tab.classList.remove('nav-tab-active'));
                contents.forEach(content => (content.style.display = "none"));

                this.classList.add('nav-tab-active');
                contents[i].style.display = "block";
            });
        });

        // Gateway-specific fields toggle
        function toggleGatewayFields(gateway) {
            document.getElementById('yoco_link_row').style.display = gateway === 'yoco' ? 'table-row' : 'none';
            document.getElementById('merchant_id_row').style.display = gateway === 'payfast' ? 'table-row' : 'none';
            document.getElementById('merchant_key_row').style.display = gateway === 'payfast' ? 'table-row' : 'none';
            document.getElementById('api_key_row').style.display = gateway === 'stripe' ? 'table-row' : 'none';
            document.getElementById('secret_key_row').style.display = gateway === 'stripe' ? 'table-row' : 'none';
        }

        const gatewaySelect = document.getElementById('gateway_type');
        gatewaySelect && gatewaySelect.addEventListener('change', function () {
            toggleGatewayFields(this.value);
        });

        toggleGatewayFields(gatewaySelect ? gatewaySelect.value : '');
    });
</script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const uploadButton = document.querySelector('.upload-image');
        const removeButton = document.querySelector('.remove-image');
        const logoInput = document.getElementById('club_logo');
        const logoPreview = document.getElementById('club-logo-preview');

        // Open WordPress media uploader
        uploadButton.addEventListener('click', function (e) {
            e.preventDefault();
            const frame = wp.media({
                title: '<?php echo __('Select Logo', 'club-manager'); ?>',
                button: { text: '<?php echo __('Use This Logo', 'club-manager'); ?>' },
                multiple: false
            });

            frame.on('select', function () {
                const attachment = frame.state().get('selection').first().toJSON();
                logoInput.value = attachment.url;
                logoPreview.src = attachment.url;
                logoPreview.style.display = 'block';
                removeButton.style.display = 'inline-block';
            });

            frame.open();
        });

        // Remove logo
        removeButton.addEventListener('click', function (e) {
            e.preventDefault();
            logoInput.value = '';
            logoPreview.style.display = 'none';
            removeButton.style.display = 'none';
        });
    });
</script>
