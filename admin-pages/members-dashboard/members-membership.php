<?php
// File: admin-pages/dashboard/manager-membership.php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Fetch the status of a specific membership ID dynamically
function get_membership_status($membership_id) {
    global $wpdb;

    if (!$membership_id || $membership_id === 'N/A') {
        return 'N/A';
    }

    $status = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT post_status 
             FROM wp_posts
             WHERE ID = %d",
            $membership_id
        )
    );

    $status_map = [
        'wcm-active' => 'Active',
        'wcm-pending' => 'Pending',
        'wcm-paused' => 'Paused',
        'wcm-pending-cancel'  => 'Pending Cancellation',
        'wcm-cancelled' => 'Cancelled',
        'wcm-expired' => 'Expired',
    ];

    return isset($status_map[$status]) ? $status_map[$status] : ucfirst(str_replace('wcm-', '', $status));
}

// Fetch the current user's subscriptions with detailed plan details
function get_logged_in_user_memberships() {
    global $wpdb;

    $current_user = wp_get_current_user();
    if (!$current_user->exists()) {
        return [];
    }

    $user_id = $current_user->ID;

    // ⭐ ORIGINAL SQL — UNTOUCHED (DO NOT MODIFY)
    $memberships = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT 
                s.ID AS subscription_id,
                MAX(u.display_name) AS member_name,  
                MAX(u.user_email) AS email,  
                GROUP_CONCAT(DISTINCT p.post_title ORDER BY p.post_title SEPARATOR ', ') AS membership_plan,
                CASE 
                    WHEN MAX(s.post_status) = 'wc-active' THEN 'Active'
                    WHEN MAX(s.post_status) = 'wc-pending' THEN 'Pending'
                    WHEN MAX(s.post_status) = 'wc-on-hold' THEN 'On Hold'
                    WHEN MAX(s.post_status) = 'wc-pending-cancel' THEN 'Pending Cancellation'
                    WHEN MAX(s.post_status) = 'wc-cancelled' THEN 'Cancelled'
                    WHEN MAX(s.post_status) = 'wc-expired' THEN 'Expired'
                    ELSE 'Unknown'
                END AS subscription_status,
                DATE_FORMAT(MAX(next_payment.meta_value), '%%d/%%m/%%Y') AS next_payment_date,
                DATE_FORMAT(MAX(end_date.meta_value), '%%d/%%m/%%Y') AS subscription_end_date,
                MAX(total.meta_value) AS total_amount,
                MAX(billing.meta_value) AS billing_period
            FROM 
                wp_posts s
            JOIN wp_postmeta pm 
                ON s.ID = pm.post_id AND pm.meta_key = '_customer_user'
            JOIN wp_users u 
                ON u.ID = pm.meta_value
            JOIN wp_woocommerce_order_items oi 
                ON s.ID = oi.order_id
            JOIN wp_woocommerce_order_itemmeta oim 
                ON oi.order_item_id = oim.order_item_id AND oim.meta_key = '_product_id'
            JOIN wp_posts p 
                ON oim.meta_value = p.ID
            LEFT JOIN wp_postmeta next_payment 
                ON s.ID = next_payment.post_id AND next_payment.meta_key = '_schedule_next_payment'
            LEFT JOIN wp_postmeta end_date 
                ON s.ID = end_date.post_id AND end_date.meta_key = '_schedule_end'
            LEFT JOIN wp_postmeta total 
                ON s.ID = total.post_id AND total.meta_key = '_order_total'
            LEFT JOIN wp_postmeta billing 
                ON s.ID = billing.post_id AND billing.meta_key = '_billing_period'
            WHERE 
                s.post_type = 'shop_subscription'
                AND pm.meta_value = %d
            GROUP BY s.ID",
            $user_id
        ),
        ARRAY_A
    );

    // ⭐ SAFE ADD: club_id + club_currency for each subscription
    foreach ($memberships as &$m) {

        $subscription_id = $m['subscription_id'];

        // 1️⃣ Get product_id inside this subscription
        $product_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT oim.meta_value
                 FROM wp_woocommerce_order_items oi
                 JOIN wp_woocommerce_order_itemmeta oim
                     ON oi.order_item_id = oim.order_item_id
                 WHERE oi.order_id = %d
                   AND oim.meta_key = '_product_id'
                 LIMIT 1",
                $subscription_id
            )
        );

        if (!$product_id) {
            $m['currency_icon'] = "R";
            continue;
        }

        // 2️⃣ Get club_id from product
        $club_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT meta_value 
                 FROM wp_postmeta 
                 WHERE post_id = %d 
                   AND meta_key = '_select_club_id'
                 LIMIT 1",
                $product_id
            )
        );

        if (!$club_id) {
            $m['currency_icon'] = "R";
            continue;
        }

        // 3️⃣ Get currency from wp_clubs
        $club_currency = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT club_currency 
                 FROM wp_clubs 
                 WHERE club_id = %d
                 LIMIT 1",
                $club_id
            )
        );

        // 4️⃣ Assign (fallback R)
        $m['currency_icon'] = $club_currency ?: "R";
    }

    return $memberships;
}








// Function to render the subscription table for the logged-in user
function render_logged_in_user_membership_table($memberships) {
    if (empty($memberships)) {
        echo '<p>No subscriptions found for your account.</p>';
        return;
    }

    // Pagination Logic
    global $wp_query;
    $paged = isset($wp_query->query_vars['paged']) ? intval($wp_query->query_vars['paged']) : 1;
    $current_page = $paged > 0 ? $paged : 1;

    $items_per_page = 60; // Number of subscriptions per page
    $total_pages = ceil(count($memberships) / $items_per_page);

    // Slice the memberships array for the current page
    $memberships_to_display = array_slice($memberships, ($current_page - 1) * $items_per_page, $items_per_page);

    // WooCommerce Subscription Status Colors
    $status_map = [
        'Active' => ['Active', '#C6E1C6', '#5B841B'],   // Green
        'Pending' => ['Pending', '#F8D7DA', '#721C24'],  // Light Red
        'On Hold' => ['On Hold', '#FCE58B', '#946C00'],  // Yellow
        'Cancelled' => ['Cancelled', '#E0E0E0', '#777'], // Gray
        'Pending Cancellation' => ['Pending Cancellation', '#FFE8A1', '#856404'], // Amber
        'Expired' => ['Expired', '#FFA07A', '#D9534F'],  // Light Orange-Red
    ];

    // Render Table
    ?>
    <table style="width: 100%; border-collapse: collapse;"class="wp-list-table widefat fixed striped managertable"id="membership-table">
        <h2 class="manager-h2">Membership</h2>
        <thead>
            <tr>
                <th style="padding: 10px; text-align: left;">User</th>
                <th style="padding: 10px; text-align: left;">Membership Plan</th>
                <th style="padding: 10px; text-align: left;">Total</th>
                <th style="padding: 10px; text-align: left;">Next Payment</th>
                <th style="padding: 10px; text-align: left;">End Date</th>
                <th style="padding: 10px; text-align: left;">Status</th>
                <th style="padding: 10px; text-align: left;">Actions</th>
                

            </tr>
        </thead>
        <tbody>
            <?php foreach ($memberships_to_display as $membership) : 
                // Get the formatted status
                $raw_status = $membership['subscription_status'] ?? 'Unknown';
                $status_info = $status_map[$raw_status] ?? ['Unknown', '#FFA07A', '#D9534F']; // Default WooCommerce "error" styling

                // Format total with billing period
                $formatted_total = ($membership['currency_icon'] ?? 'R') . number_format((float) ($membership['total_amount'] ?? 0), 2);
                if (!empty($membership['billing_period'])) {
                    $formatted_total .= ' / ' . esc_html($membership['billing_period']);
                }

                ?>
                <tr style="border-bottom: 1px solid #ddd;">
                    <td style="padding: 10px;" data-label="User">
                        <strong><?php echo esc_html($membership['member_name']); ?></strong><br>
                        <span style="color: #666;"><?php echo esc_html($membership['email']); ?></span>
                    </td >
                    <td style="padding: 10px;"data-label="Membership Plan">
                        <strong>#<?php echo esc_html($membership['subscription_id']); ?></strong><br>
                        <span style="color: #666;"><?php echo esc_html($membership['membership_plan'] ?? 'N/A'); ?></span>
                    </td>
                    <td style="padding: 10px;"data-label="Total">
                        <?php echo esc_html($formatted_total); ?>
                    </td>
                    <td style="padding: 10px;" data-label="Next Payment">
                        <?php echo esc_html($membership['next_payment_date'] ?? 'N/A'); ?>
                    </td>
                    <td style="padding: 10px;" data-label="End Date">
                        <?php echo esc_html($membership['subscription_end_date'] ?? 'N/A'); ?>
                    </td>
                    <td  style="padding: 10px;" data-label="Status">
                        <span class="badge" style="padding: 5px 10px; border-radius: 3px; display: inline-block;
                            background: <?php echo $status_info[1]; ?>; color: <?php echo $status_info[2]; ?>;">
                            <?php echo esc_html($status_info[0]); ?>
                        </span>
                    </td>
                 <td style="padding: 10px;" data-label="Actions">
    <div style="display:flex; flex-wrap:wrap; gap:8px;">

        <?php
        global $wpdb;

        $subscription_id = (int) $membership['subscription_id'];
        $current_status  = strtolower(trim($membership['subscription_status'] ?? ''));

        /* ==================================================
         * PAY BUTTON (ONLY for ON HOLD + pending renewal)
         * ================================================== */
        if ($current_status === 'on hold') {

            $pending_renewal_order_id = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT p.ID
                     FROM {$wpdb->posts} p
                     INNER JOIN {$wpdb->postmeta} pm
                        ON p.ID = pm.post_id
                     WHERE p.post_type = 'shop_order'
                       AND p.post_status = 'wc-pending'
                       AND pm.meta_key = '_subscription_renewal'
                       AND pm.meta_value = %d
                     ORDER BY p.post_date DESC
                     LIMIT 1",
                    $subscription_id
                )
            );

            if ($pending_renewal_order_id) {
                $order = wc_get_order($pending_renewal_order_id);
                if ($order) {
                    ?>
                    <a
                        href="<?php echo esc_url($order->get_checkout_payment_url()); ?>"
                        class="action-item"
                        style="padding:6px 12px;background:#28a745;color:#fff;border-radius:3px;text-decoration:none;">
                        Pay
                    </a>
                    <?php
                }
            }
        }
        ?>

        <?php
        /* ==========================
         * RENEW BUTTON (UNCHANGED)
         * ========================== */
        $allowed_statuses = ['active', 'expired', 'cancelled'];

        $payment_method = strtolower((string) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT meta_value
                 FROM {$wpdb->postmeta}
                 WHERE post_id = %d
                   AND meta_key = '_payment_method'
                 LIMIT 1",
                $subscription_id
            )
        ));

        $is_blocked_gateway = (
            strpos($payment_method, 'payfast') !== false ||
            strpos($payment_method, 'stripe') !== false
        );

        if (
            in_array($current_status, $allowed_statuses, true)
            && !$is_blocked_gateway
        ):
        ?>
            <button
                class="renew-subscription-btn action-item"
                data-subscription-id="<?php echo esc_attr($subscription_id); ?>"
                style="padding:6px 12px;background:#007bff;color:#fff;border:none;border-radius:3px;cursor:pointer;">
                Renew
            </button>
        <?php endif; ?>

        <?php
        /* ==========================
         * CANCEL / UNCANCEL (UNCHANGED)
         * ========================== */
        if ($current_status === 'active'):
        ?>
            <button
                class="cancel-subscription-btn action-item"
                data-subscription-id="<?php echo esc_attr($subscription_id); ?>"
                style="padding:6px 12px;background:#dc3545;color:#fff;border:none;border-radius:3px;cursor:pointer;">
                Cancel
            </button>

        <?php elseif ($current_status === 'pending cancellation'): ?>
            <button
                class="uncancel-subscription-btn action-item"
                data-subscription-id="<?php echo esc_attr($subscription_id); ?>"
                style="padding:6px 12px;background:#28a745;color:#fff;border:none;border-radius:3px;cursor:pointer;">
                Uncancel
            </button>
        <?php endif; ?>

    </div>
</td>



                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
// 🔒 Generate nonce ONCE (stable)
$renew_nonce = wp_create_nonce('renew_subscription_nonce');
?>

<script>
jQuery(document).ready(function ($) {

    $('.renew-subscription-btn').on('click', function (e) {
        e.preventDefault();

        const $btn = $(this);
        const subscriptionId = $btn.data('subscription-id');
        const nonce = '<?php echo esc_js($renew_nonce); ?>';

        if (!subscriptionId) {
            alert('Invalid subscription ID.');
            return;
        }

        if (!confirm('Are you sure you want to renew this subscription?')) {
            return;
        }

        $btn.prop('disabled', true).text('Processing...');

        $.ajax({
            type: 'POST',
            url: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
            dataType: 'json',
            data: {
                action: 'renew_subscription_unique',
                subscription_id: subscriptionId,
                _ajax_nonce: nonce
            },

            success: function (response) {
                console.log('[Renew] Response:', response);

                /* ===============================
                 * SUCCESS
                 * =============================== */
                if (response.success) {

                    const data = response.data || {};
                    const clubId = data.club_id ? parseInt(data.club_id, 10) : null;

                    /* --------------------------------
                     * EXPIRED / CANCELLED FLOW
                     * -------------------------------- */
                    if (data.message === 'Subscription expired/cancelled.' && clubId) {

                        alert('Your subscription has expired or was cancelled.\nRedirecting you to buy a new subscription...');

                        const clubRedirectMap = {
                            4: '/mrcap/membership-registration-mrcap/',
                            5: '/ccgtn/membership-registration-ccgtn/',
                            7: '/mrcen/membership-registration-mrcen/',
                            8: '/mrkzn/membership-registration-mrkzn/',
                            9: '/mreca/membership-registration-mreca/',
                            9: '/mreca/membership-registration-mreca/',
                            10:'/mrgdn/membership-registration-mrgdn/',
                            11:'/mrinl/membership-registration-mrinl/',
                            12:'/mrnam/membership-registration-mrnam/',
                            13:'/mrlwv/membership-registration-mrlwv/',
                            14:'/mrwrd/membership-registration-mrwrd/',
                            15:'/mcpta/membership-registration-mcpta/',
                            16:'/cccpt/membership-registration-cccpt/',
                            18:'/mrzim/membership-registration-mrzim/',

                            // add more mappings later
                        };

                        if (clubRedirectMap[clubId]) {
                            window.location.href = clubRedirectMap[clubId];
                        } else {
                            alert('No registration page found for this club.');
                            $btn.prop('disabled', false).text('Renew');
                        }

                        return;
                    }

                    /* --------------------------------
                     * ACTIVE / ON-HOLD FLOW
                     * -------------------------------- */
                    alert('Pending renewal order created. Redirecting to payment...');
                    window.location.href = data.pay_url || window.location.reload();
                    return;
                }

                /* ===============================
                 * ERROR
                 * =============================== */
                alert('Renewal failed: ' + (response.data?.message || 'Unknown error'));
                $btn.prop('disabled', false).text('Renew');
            },

            error: function (xhr) {
                console.error('[Renew] AJAX failed:', xhr.responseText);
                alert('AJAX request failed.');
                $btn.prop('disabled', false).text('Renew');
            }
        });
    });

});
</script>



<?php $cancel_nonce = wp_create_nonce('cancel_subscription_nonce'); ?>

<script>
jQuery(document).ready(function ($) {

    /* =====================
     * CANCEL
     * ===================== */
    $('.cancel-subscription-btn').on('click', function (e) {
        e.preventDefault();

        if (!confirm('Are you sure you want to cancel this subscription?')) return;

        const btn = $(this);
        btn.prop('disabled', true).text('Cancelling...');

        $.post('<?php echo admin_url('admin-ajax.php'); ?>', {
            action: 'cancel_subscription_custom',
            subscription_id: btn.data('subscription-id'),
            _ajax_nonce: '<?php echo esc_js($cancel_nonce); ?>'
        }, function (res) {
            if (res.success) {
                alert('Subscription cancelled');
                location.reload();
            } else {
                alert(res.data?.message || 'Failed');
                btn.prop('disabled', false).text('Cancel');
            }
        });
    });

    /* =====================
     * UNCANCEL
     * ===================== */
    $('.uncancel-subscription-btn').on('click', function (e) {
        e.preventDefault();

        if (!confirm('Do you want to reactivate this subscription?')) return;

        const btn = $(this);
        btn.prop('disabled', true).text('Reactivating...');

        $.post('<?php echo admin_url('admin-ajax.php'); ?>', {
            action: 'uncancel_subscription_custom',
            subscription_id: btn.data('subscription-id'),
            _ajax_nonce: '<?php echo esc_js($cancel_nonce); ?>'
        }, function (res) {
            if (res.success) {
                alert('Subscription reactivated');
                location.reload();
            } else {
                alert(res.data?.message || 'Failed');
                btn.prop('disabled', false).text('Uncancel');
            }
        });
    });

});
</script>

    <?php
}








// Fetch and render the logged-in user's memberships
$logged_in_user_memberships = get_logged_in_user_memberships();
render_logged_in_user_membership_table($logged_in_user_memberships);
?>
