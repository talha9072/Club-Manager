<?php


add_filter('woocommerce_bacs_accounts', function ($accounts) {
    // Get the club parameter from the URL
    $club_param = isset($_GET['club']) ? urldecode(sanitize_text_field($_GET['club'])) : '';

    // If no club is set, return all bank accounts
    if (empty($club_param)) {
        return $accounts;
    }

    // Filter accounts based on the club name
    $filtered_accounts = array_filter($accounts, function ($account) use ($club_param) {
        return strcasecmp($account['account_name'], $club_param) === 0; // Case-insensitive comparison
    });

    // If a matching club is found, return only that account, else return all
    return !empty($filtered_accounts) ? array_values($filtered_accounts) : $accounts;
});

// 🧠 Store `club` param from URL into WC session (only once)
add_action('init', function () {
    if (isset($_GET['club']) && function_exists('WC') && WC()->session) {
        $club_param = sanitize_text_field($_GET['club']);
        WC()->session->set('club_for_bacs', $club_param);
    }
});

add_filter('woocommerce_available_payment_gateways', function ($gateways) {

    if (is_admin()) return $gateways;

    // Only apply on checkout or order-pay pages.
    if (!is_checkout() && !is_wc_endpoint_url('order-pay')) return $gateways;

    if (!function_exists('WC') || !WC()->session || !WC()->cart) return $gateways;

    // Skip filtering ONLY during the actual "place order" AJAX call.
    // WooCommerce also sends payment_method during checkout refresh AJAX
    // (woocommerce_update_order_review) — that must still be filtered for
    // correct display. Only the final woocommerce_checkout action (place order)
    // should bypass filtering to prevent "Invalid payment method" errors.
    if (
        defined('DOING_AJAX') && DOING_AJAX &&
        isset($_POST['action']) && $_POST['action'] === 'woocommerce_checkout'
    ) {
        return $gateways;
    }

    global $wpdb;

    $chosen = WC()->session->get('chosen_payment_method', '');

    /*
     |----------------------------------
     | BACS (Bank Transfer)
     |----------------------------------
     */
    if (isset($gateways['bacs'])) {

        $bacs_accounts = get_option('woocommerce_bacs_accounts');

        if (!is_array($bacs_accounts) || empty($bacs_accounts)) {
            unset($gateways['bacs']);
        } else {

            $club_param = WC()->session->get('club_for_bacs', '');
            $club_param_trimmed = strtolower(trim($club_param));
            $is_global = $club_param_trimmed === '' || $club_param_trimmed === 'global';

            $match_found = false;

            foreach ($bacs_accounts as $account) {
                $account_name = strtolower(trim($account['account_name']));
                if (
                    ($is_global && $account_name === 'global') ||
                    (!$is_global && $account_name === $club_param_trimmed)
                ) {
                    $match_found = true;
                    break;
                }
            }

            if (!$match_found) {
                unset($gateways['bacs']);
            }
        }
    }

    /*
     |----------------------------------
     | CART ANALYSIS (for Yoco Link)
     |----------------------------------
     */
    $cart = WC()->cart->get_cart();
    $club_ids = [];
    $require_global_yoco = false;

    foreach ($cart as $item) {
        if (empty($item['product_id'])) continue;

        $club_id = get_post_meta($item['product_id'], '_select_club_id', true);
        $club_id = is_string($club_id) ? strtolower(trim($club_id)) : $club_id;

        if (empty($club_id) || $club_id === 'global') {
            $require_global_yoco = true;
        } else {
            $club_ids[] = (int) $club_id;
        }
    }

    $missing_yoco = false;

    // Global fallback Yoco
    if ($require_global_yoco) {
        $data = maybe_unserialize(get_option('woocommerce_yoco_accounts'));
        if (!is_array($data) || empty($data[0]['account_name'])) {
            $missing_yoco = true;
        }
    }

    // Club-based Yoco links
    if (!empty($club_ids)) {
        $placeholders = implode(',', array_fill(0, count($club_ids), '%d'));
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT club_id, yoco_link FROM wp_payment_gateways WHERE club_id IN ($placeholders)",
                $club_ids
            )
        );

        $valid = [];
        foreach ($results as $row) {
            if (!empty($row->yoco_link)) {
                $valid[] = (int) $row->club_id;
            }
        }

        foreach ($club_ids as $cid) {
            if (!in_array($cid, $valid, true)) {
                $missing_yoco = true;
                break;
            }
        }
    }

    /*
     |----------------------------------
     | YOCO PAYMENT LINK (yoco_gateway)
     |----------------------------------
     */
    if ($missing_yoco && isset($gateways['yoco_gateway']) && $chosen !== 'yoco_gateway') {
        unset($gateways['yoco_gateway']);
    }

    /*
     |----------------------------------
     | CLUB TOGGLE LOGIC
     |----------------------------------
     */
    $club_id = bca_get_club_id_from_context();

    if ($club_id) {

        $club = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT payfast_enabled, stripe_enabled, yoco_enabled
                 FROM {$wpdb->prefix}clubs
                 WHERE club_id = %d",
                $club_id
            )
        );

        if ($club) {

            // PAYFAST
            if (empty($club->payfast_enabled)) {
                if ($chosen !== 'payfast' && isset($gateways['payfast'])) {
                    unset($gateways['payfast']);
                }
            }

            // STRIPE
            if (empty($club->stripe_enabled)) {
                foreach ($gateways as $key => $gateway) {
                    if (strpos($key, 'stripe') !== false && $chosen !== $key) {
                        unset($gateways[$key]);
                    }
                }
            }

            // NORMAL YOCO (NOT yoco_gateway)
            if (empty($club->yoco_enabled)) {
                foreach ($gateways as $key => $gateway) {
                    if (
                        strpos($key, 'yoco') !== false &&
                        $key !== 'yoco_gateway' &&
                        $chosen !== $key
                    ) {
                        unset($gateways[$key]);
                    }
                }
            }
        }
    }

    return $gateways;

}, 999);





function add_yoco_pay_now_button_to_order_received($order_id) {
    if (!$order_id) return;

    global $wpdb;

    // Get the order details
    $order = wc_get_order($order_id);
    if (!$order) return;

    // Get the selected payment method
    $payment_method = $order->get_payment_method_title();

    // Show button only if the payment method is Yoco
    if (stripos($payment_method, 'Yoco') === false) {
        return;
    }

    // Get order data
    $order_total = $order->get_total(); // Total amount
    $customer_first_name = $order->get_billing_first_name();
    $customer_last_name = $order->get_billing_last_name();
    $customer_email = $order->get_billing_email();

    // Get the first product in the order
    $items = $order->get_items();
    $product_id = null;

    foreach ($items as $item) {
        $product_id = $item->get_product_id();
        break; // Get only the first product
    }

    if (!$product_id) {
        echo "<p><strong>Debug:</strong> No product found in order.</p>";
        return;
    }

    // Get the _select_club_id meta value for the product
    $club_id = $wpdb->get_var($wpdb->prepare("
        SELECT meta_value 
        FROM {$wpdb->postmeta} 
        WHERE post_id = %d AND meta_key = '_select_club_id'
    ", $product_id));

    $yoco_link = null; // Default to null

    if ($club_id && strtolower($club_id) !== 'global') {
        // Get the Yoco payment link for the specific club_id
        $yoco_link = $wpdb->get_var($wpdb->prepare("
            SELECT yoco_link FROM wp_payment_gateways 
            WHERE club_id = %d AND gateway_type = 'yoco'
        ", $club_id));
    }

    // Fallback: Fetch default Yoco link from WooCommerce settings if no specific club link is found or if club_id is 'global'
    if (empty($yoco_link)) {
        $serialized_data = $wpdb->get_var("SELECT option_value FROM wp_options WHERE option_name = 'woocommerce_yoco_accounts'");

        if ($serialized_data) {
            $unserialized_data = maybe_unserialize($serialized_data);
            if (is_array($unserialized_data) && isset($unserialized_data[0]['account_name'])) {
                $yoco_link = $unserialized_data[0]['account_name'];
            }
        }
    }

    // If no valid Yoco link is found, do not display the button
    if (empty($yoco_link)) {
        return;
    }

    // Generate Yoco Payment Link with order details
    $yoco_payment_url = $yoco_link . "?" . http_build_query([
        'amount' => $order_total,
        'reference' => "Order #$order_id",
        'firstName' => $customer_first_name,
        'lastName' => $customer_last_name,
        'email' => $customer_email
    ]);

    // Output the Pay Now button inside a container to place it above "woocommerce-order-details"
    echo '<script>
        document.addEventListener("DOMContentLoaded", function() {
            var payNowButtonContainer = document.createElement("div");
            payNowButtonContainer.innerHTML = `<div class="yoco-div">
                <a href="' . esc_url($yoco_payment_url) . '" class="button alt yoco-button" style="background: #0073aa; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none; font-size: 16px;">
                    Pay Now
                </a>
            </div>`;
            
            var orderDetailsSection = document.querySelector(".woocommerce-order-details");
            if (orderDetailsSection) {
                orderDetailsSection.parentNode.insertBefore(payNowButtonContainer, orderDetailsSection);
            }
        });
    </script>';
}

add_action('woocommerce_thankyou', 'add_yoco_pay_now_button_to_order_received', 20);
