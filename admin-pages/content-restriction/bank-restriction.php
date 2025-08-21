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

add_filter('woocommerce_available_payment_gateways', function ($available_gateways) {
    if (!isset($available_gateways['bacs'])) return $available_gateways;

    $bacs_accounts = get_option('woocommerce_bacs_accounts');
    if (!is_array($bacs_accounts) || empty($bacs_accounts)) {
        unset($available_gateways['bacs']);
        return $available_gateways;
    }

    // ✅ Pull from session instead of $_GET
    $club_param = '';
    if (function_exists('WC') && WC()->session) {
        $club_param = WC()->session->get('club_for_bacs', '');
    }

    $club_param_trimmed = strtolower(trim($club_param));
    $is_global = $club_param_trimmed === '' || $club_param_trimmed === 'global';

    $match_found = false;
    foreach ($bacs_accounts as $account) {
        $account_name = strtolower(trim($account['account_name']));
        if (($is_global && $account_name === 'global') || (!$is_global && $account_name === $club_param_trimmed)) {
            $match_found = true;
            break;
        }
    }

    if (!$match_found) {
        unset($available_gateways['bacs']);
    }

    return $available_gateways;
});



add_filter('woocommerce_available_payment_gateways', function ($available_gateways) {
    global $wpdb;

    if (!function_exists('WC') || !WC()->cart) return $available_gateways;

    $cart = WC()->cart->get_cart();
    if (!$cart) return $available_gateways;

    $club_ids = [];
    $require_global_yoco = false;

    foreach ($cart as $item) {
        if (empty($item['product_id'])) continue;

        $product_id = $item['product_id'];
        $club_id = get_post_meta($product_id, '_select_club_id', true);
        $club_id = is_string($club_id) ? strtolower(trim($club_id)) : $club_id;

        if (empty($club_id) || $club_id === 'global') {
            $require_global_yoco = true;
        } else {
            $club_ids[] = (int) $club_id;
        }
    }

    $missing_yoco = false;

    // Check fallback Yoco config for global products
    if ($require_global_yoco) {
        $serialized_data = get_option('woocommerce_yoco_accounts');
        $has_default_yoco = false;

        if ($serialized_data) {
            $unserialized = maybe_unserialize($serialized_data);
            if (is_array($unserialized) && !empty($unserialized[0]['account_name'])) {
                $has_default_yoco = true;
            }
        }

        if (!$has_default_yoco) {
            $missing_yoco = true;
        }
    }

    // Check wp_payment_gateways for Yoco links for each club
    if (!empty($club_ids)) {
        $placeholders = implode(',', array_fill(0, count($club_ids), '%d'));
        $sql = "SELECT club_id, yoco_link FROM wp_payment_gateways WHERE club_id IN ($placeholders)";
        $results = $wpdb->get_results($wpdb->prepare($sql, $club_ids));

        $clubs_with_yoco = [];
        foreach ($results as $row) {
            if (!empty($row->yoco_link)) {
                $clubs_with_yoco[] = (int) $row->club_id;
            }
        }

        foreach ($club_ids as $cid) {
            if (!in_array($cid, $clubs_with_yoco, true)) {
                $missing_yoco = true;
                break;
            }
        }
    }

    // Remove only the specific custom Yoco gateway by ID
    if ($missing_yoco && isset($available_gateways['yoco_gateway'])) {
        unset($available_gateways['yoco_gateway']);
    }

    return $available_gateways;
}, 10, 1);






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
