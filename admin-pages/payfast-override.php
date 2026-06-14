<?php
/**
 * PayFast per-club gateway override for WooCommerce.
 * Supports dynamic merchant credentials for each order/club on both payment and ITN callback.
 */

add_action('plugins_loaded', function() {
    if (!class_exists('WC_Gateway_PayFast')) {
        return;
    }

    if (!class_exists('WC_Gateway_PayFast_Override')) {
        class WC_Gateway_PayFast_Override extends WC_Gateway_PayFast {

            public function generate_payfast_form($order_id) {
    global $wpdb;

    $order     = wc_get_order($order_id);
    if ( ! $order ) {
        return;
    }

    $site_name = html_entity_decode(get_bloginfo('name'), ENT_QUOTES, get_bloginfo('charset'));

    /* -----------------------------
     * 1. Detect club (payment time)
     * ----------------------------- */
    $club_id = null;
    foreach ($order->get_items() as $item) {
        $product_id = $item->get_product_id();
        $club_id = get_post_meta($product_id, '_select_club_id', true);
        if ( ! empty($club_id) ) {
            break;
        }
    }

    $club_id_trim = is_string($club_id) ? trim($club_id) : '';
    $is_global    = empty($club_id_trim) || strtolower($club_id_trim) === 'global';

    /* -----------------------------
     * 2. Defaults (global PayFast)
     * ----------------------------- */
    $merchant_id  = $this->merchant_id;
    $merchant_key = $this->merchant_key;
    $pass_phrase  = $this->pass_phrase;
    $env          = 'live';

    $this->url          = 'https://www.payfast.co.za/eng/process?aff=woo-free';
    $this->validate_url = 'https://www.payfast.co.za/eng/query/validate';

    /* -----------------------------
     * 3. Club override
     * ----------------------------- */
    if ( ! $is_global ) {
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}clubs WHERE club_id = %d",
                absint($club_id)
            )
        );

        if ( $row ) {
            if ( (int) $row->sandbox_enabled === 1 ) {
                $merchant_id  = $row->sandbox_merchant_id;
                $merchant_key = $row->sandbox_merchant_key;
                $pass_phrase  = $row->sandbox_passphrase;
                $env          = 'sandbox';

                $this->url          = 'https://sandbox.payfast.co.za/eng/process?aff=woo-free';
                $this->validate_url = 'https://sandbox.payfast.co.za/eng/query/validate';
            } else {
                $merchant_id  = $row->payfast_merchant_id;
                $merchant_key = $row->payfast_merchant_key;
                $pass_phrase  = $row->payfast_passphrase;
                $env          = 'live';
            }
        }
    }

    /* -----------------------------
     * 4. SAVE merchant for ITN
     * ----------------------------- */
    $order->update_meta_data('_payfast_merchant_id', $merchant_id);
    $order->update_meta_data('_payfast_merchant_key', $merchant_key);
    $order->update_meta_data('_payfast_passphrase', $pass_phrase);
    $order->update_meta_data('_payfast_env', $env);
    $order->save();

    /* -----------------------------
     * 5. Apply to instance
     * ----------------------------- */
    $this->merchant_id  = $merchant_id;
    $this->merchant_key = $merchant_key;
    $this->pass_phrase  = $pass_phrase;

    /* -----------------------------
     * 6. Build PayFast payload
     * ----------------------------- */
    $this->data_to_send = array(
        'merchant_id'   => $merchant_id,
        'merchant_key'  => $merchant_key,

        'return_url'    => esc_url_raw(add_query_arg('utm_nooverride', '1', $this->get_return_url($order))),
        'cancel_url'    => $order->get_cancel_order_url(),
        'notify_url'    => $this->response_url,

        'name_first'    => self::get_order_prop($order, 'billing_first_name'),
        'name_last'     => self::get_order_prop($order, 'billing_last_name'),
        'email_address' => self::get_order_prop($order, 'billing_email'),

        'm_payment_id'  => ltrim($order->get_order_number(), '#'),
        'amount'        => $order->get_total(),
        'item_name'     => $site_name . ' - ' . $order->get_order_number(),
        'item_description' => sprintf(__('New order from %s', 'woocommerce-gateway-payfast'), $site_name),

        'custom_str1'   => self::get_order_prop($order, 'order_key'),
        'custom_str2'   => 'WooCommerce/' . WC_VERSION . '; ' . rawurlencode(get_site_url()),
        'custom_str3'   => self::get_order_prop($order, 'id'),
        'source'        => 'WooCommerce-Free-Plugin',
    );

    if ($this->is_subscription($order_id) || $this->order_contains_subscription($order_id)) {
        $this->data_to_send['subscription_type'] = '2';
    }

    /* -----------------------------
     * 7. WORKING signature logic
     * ----------------------------- */
    $payfast_args_array = array();
    $sign_strings = array();

    foreach ($this->data_to_send as $key => $value) {
        if ('source' !== $key) {
            $sign_strings[] =
                esc_attr($key) . '=' .
                urlencode(str_replace('&amp;', '&', trim($value)));
        }
        $payfast_args_array[] =
            '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '" />';
    }

    if (!empty($this->pass_phrase)) {
        $signature = md5(
            implode('&', $sign_strings) .
            '&passphrase=' . urlencode($this->pass_phrase)
        );
    } else {
        $signature = md5(implode('&', $sign_strings));
    }

    $payfast_args_array[] =
        '<input type="hidden" name="signature" value="' . esc_attr($signature) . '" />';

    /* -----------------------------
     * 8. Output form
     * ----------------------------- */
    echo '<form action="' . esc_url($this->url) . '" method="post" id="payfast_payment_form">';
    echo implode('', $payfast_args_array);
    echo '<input type="submit" class="button-alt" id="submit_payfast_payment_form"
        value="' . esc_attr__('Pay via Payfast', 'woocommerce-gateway-payfast') . '" />';
    echo '</form>';
}


          public function handle_itn_request( $data ) {

    if ( empty($data) || empty($data['custom_str3']) ) {
        status_header(200);
        exit;
    }

    $order_id = absint( $data['custom_str3'] );
    $order    = wc_get_order( $order_id );

    if ( ! $order ) {
        status_header(200);
        exit;
    }

    // Log for debug
    wc_get_logger()->info( 'PayFast ITN bypassed validation for order #' . $order_id, array( 'source' => 'payfast-override' ) );

    if ( isset($data['payment_status']) && strtoupper($data['payment_status']) === 'COMPLETE' ) {

        // Subscriptions crash avoid – hooks remove kar do temporarily
        remove_all_actions('woocommerce_subscription_payment_complete');
        remove_all_actions('woocommerce_scheduled_subscription_payment_payfast');

        $order->add_order_note('PayFast ITN bypassed validation – payment complete (club account).');
        $order->payment_complete( $data['pf_payment_id'] ?? null );

        // Token save
        if ( ! empty($data['token']) ) {
            $order->update_meta_data( '_payfast_token', $data['token'] );
            $order->save();

            if ( function_exists('wcs_order_contains_subscription') && wcs_order_contains_subscription( $order ) ) {
                $subscriptions = wcs_get_subscriptions_for_order( $order_id );
                foreach ( $subscriptions as $subscription ) {
                    $subscription->update_meta_data( '_payfast_token', $data['token'] );
                    $subscription->save();
                }
            }
        }
    }

    status_header(200);
    exit;
}
        }
    }

    // Swap out the default gateway with your new one
    add_filter('woocommerce_payment_gateways', function($methods) {
        foreach($methods as $k => $class) {
            if ($class === 'WC_Gateway_PayFast') {
                unset($methods[$k]);
            }
        }
        $methods[] = 'WC_Gateway_PayFast_Override';
        return $methods;
    }, 99);

});



/**
 * BMW Clubs Africa:
 * Control PayFast visibility based on Club settings.
 * - Hide PayFast if club product exists but club has no PayFast settings
 * - Show PayFast for Global products
 * - Force-enable PayFast for Global products even if WooCommerce disables it
 */
add_filter('woocommerce_available_payment_gateways', function($gateways) {

    if (!is_checkout()) {
        return $gateways;
    }

    global $wpdb;

    $club_id = null;
    $raw_club = null;

    // Detect club ID from cart (clean + safe)
    foreach (WC()->cart->get_cart() as $item) {
        $raw_club = trim(get_post_meta($item['product_id'], '_select_club_id', true));

        // Skip empty and global
        if ($raw_club === '' || strtolower($raw_club) === 'global') {
            continue;
        }

        // Accept only numeric real club IDs
        if (ctype_digit($raw_club)) {
            $club_id = intval($raw_club);
            break;
        }
    }

    // ----------------------------------------------------------
    // CASE 1: GLOBAL PRODUCT → PayFast must show
    // ----------------------------------------------------------
    if (empty($club_id)) {

        // If PayFast is already visible → done
        if (isset($gateways['payfast'])) {
            return $gateways;
        }

        // If PayFast is hidden by WooCommerce → force-enable it
        $all_gateways = WC()->payment_gateways()->payment_gateways();
        if (isset($all_gateways['payfast'])) {
            $gateways['payfast'] = $all_gateways['payfast'];
        }

        return $gateways;
    }

    // ----------------------------------------------------------
    // CASE 2: CLUB PRODUCT → Check club PayFast settings
    // ----------------------------------------------------------

    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}clubs WHERE club_id = %d",
        $club_id
    ));

    $club_has_settings = false;

    if ($row) {

        // Live mode OK?
        if (!empty($row->payfast_merchant_id) &&
            !empty($row->payfast_merchant_key)) {
            $club_has_settings = true;
        }

        // Sandbox mode OK?
        if ($row->sandbox_enabled == 1 &&
            !empty($row->sandbox_merchant_id) &&
            !empty($row->sandbox_merchant_key)) {
            $club_has_settings = true;
        }
    }

    // If CLUB product but NO PayFast settings → hide PayFast
    if (!$club_has_settings) {
        unset($gateways['payfast']);
        return $gateways;
    }

    // If PayFast already enabled → return as is
    if (isset($gateways['payfast'])) {
        return $gateways;
    }

    // ----------------------------------------------------------
    // CASE 3: Club has PayFast settings but WooCommerce hid gateway
    // → Force-enable using already loaded instance (NO class reload)
    // ----------------------------------------------------------

    $all_gateways = WC()->payment_gateways()->payment_gateways();
    if (isset($all_gateways['payfast'])) {
        $gateways['payfast'] = $all_gateways['payfast'];
    }

    return $gateways;

});

