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
                $site_name = html_entity_decode(get_bloginfo('name'), ENT_QUOTES, get_bloginfo('charset'));

                $club_id = null;
                foreach ($order->get_items() as $item) {
                    $product_id = $item->get_product_id();
                    $club_id = get_post_meta($product_id, '_select_club_id', true);
                    if ($club_id !== '') break;
                }
                $club_id_trim = trim($club_id);
                $is_global = empty($club_id_trim) || strtolower($club_id_trim) === 'global';

                // Defaults
                $merchant_id  = $this->merchant_id;
                $merchant_key = $this->merchant_key;
                $pass_phrase  = $this->pass_phrase;
                $url          = 'https://www.payfast.co.za/eng/process?aff=woo-free';
                $validate_url = 'https://www.payfast.co.za/eng/query/validate';

                if (!$is_global) {
                    $row = $wpdb->get_row($wpdb->prepare(
                        "SELECT * FROM {$wpdb->prefix}clubs WHERE club_id = %d", $club_id
                    ));
                    if ($row) {
                        if ($row->sandbox_enabled == 1) {
                            $merchant_id  = $row->sandbox_merchant_id;
                            $merchant_key = $row->sandbox_merchant_key;
                            $pass_phrase  = $row->sandbox_passphrase;
                            $url          = 'https://sandbox.payfast.co.za/eng/process?aff=woo-free';
                            $validate_url = 'https://sandbox.payfast.co.za/eng/query/validate'; // FIX
                        } else {
                            $merchant_id  = $row->payfast_merchant_id;
                            $merchant_key = $row->payfast_merchant_key;
                            $pass_phrase  = $row->payfast_passphrase;
                            $url          = 'https://www.payfast.co.za/eng/process?aff=woo-free';
                            $validate_url = 'https://www.payfast.co.za/eng/query/validate';  // FIX
                        }
                    }
                } else {
                    // If global/empty, optionally use sandbox if enabled
                    $sandbox_enabled = $this->settings['sandbox'] ?? 'no';
                    if ($sandbox_enabled === 'yes') {
                        $url = 'https://sandbox.payfast.co.za/eng/process?aff=woo-free';
                        $validate_url = 'https://sandbox.payfast.co.za/eng/query/validate';
                    }
                }

                // Apply to instance for ITN
                $this->merchant_id  = $merchant_id;
                $this->merchant_key = $merchant_key;
                $this->pass_phrase  = $pass_phrase;
                $this->url          = $url;
                $this->validate_url = $validate_url; // FIX

                // ...[rest is unchanged]
                $this->data_to_send = array(
                    'merchant_id'      => $merchant_id,
                    'merchant_key'     => $merchant_key,
                    'return_url'       => esc_url_raw(add_query_arg('utm_nooverride', '1', $this->get_return_url($order))),
                    'cancel_url'       => $order->get_cancel_order_url(),
                    'notify_url'       => $this->response_url,

                    'name_first'       => self::get_order_prop($order, 'billing_first_name'),
                    'name_last'        => self::get_order_prop($order, 'billing_last_name'),
                    'email_address'    => self::get_order_prop($order, 'billing_email'),

                    'm_payment_id'     => ltrim($order->get_order_number(), _x('#', 'hash before order number', 'woocommerce-gateway-payfast')),
                    'amount'           => $order->get_total(),
                    'item_name'        => $site_name . ' - ' . $order->get_order_number(),
                    'item_description' => sprintf(esc_html__('New order from %s', 'woocommerce-gateway-payfast'), $site_name),

                    'custom_str1'      => self::get_order_prop($order, 'order_key'),
                    'custom_str2'      => 'WooCommerce/' . WC_VERSION . '; ' . rawurlencode(get_site_url()),
                    'custom_str3'      => self::get_order_prop($order, 'id'),
                    'source'           => 'WooCommerce-Free-Plugin',
                );

                // Subscription logic...
                if (isset($_GET['change_pay_method'])) {
                    $subscription_id = absint(wp_unslash($_GET['change_pay_method']));
                    if ($this->is_subscription($subscription_id) && $order_id === $subscription_id && floatval(0) === floatval($order->get_total())) {
                        $this->data_to_send['custom_str4'] = 'change_pay_method';
                    }
                }
                if ($this->is_subscription($order_id) || $this->order_contains_subscription($order_id)) {
                    $this->data_to_send['subscription_type'] = '2';
                }
                if (function_exists('wcs_order_contains_renewal') && wcs_order_contains_renewal($order)) {
                    $subscriptions = wcs_get_subscriptions_for_renewal_order($order_id);
                    $current = reset($subscriptions);
                    if (count($subscriptions) > 0 && ($this->_has_renewal_flag($current) || $this->id !== $current->get_payment_method())) {
                        $this->data_to_send['subscription_type'] = '2';
                    }
                }

                // [form output below is unchanged...]
                $this->data_to_send = apply_filters('woocommerce_gateway_payfast_payment_data_to_send', $this->data_to_send, $order_id);

                $payfast_args_array = array();
                $sign_strings = array();
                foreach ($this->data_to_send as $key => $value) {
                    if ('source' !== $key) {
                        $sign_strings[] = esc_attr($key) . '=' . urlencode(str_replace('&amp;', '&', trim($value)));
                    }
                    $payfast_args_array[] = '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '" />';
                }
                if (!empty($this->pass_phrase)) {
                    $payfast_args_array[] = '<input type="hidden" name="signature" value="' . md5(implode('&', $sign_strings) . '&passphrase=' . urlencode($this->pass_phrase)) . '" />';
                } else {
                    $payfast_args_array[] = '<input type="hidden" name="signature" value="' . md5(implode('&', $sign_strings)) . '" />';
                }

                echo '<form action="' . esc_url($this->url) . '" method="post" id="payfast_payment_form">';
                echo implode('', $payfast_args_array);
                echo '
                    <input type="submit" class="button-alt" id="submit_payfast_payment_form"
                        value="' . esc_attr__('Pay via Payfast', 'woocommerce-gateway-payfast') . '" />
                    <a class="button cancel" href="' . esc_url($order->get_cancel_order_url()) . '">'
                    . esc_html__('Cancel order &amp; restore cart', 'woocommerce-gateway-payfast') .
                    '</a>
                    <script type="text/javascript">
                        jQuery(function(){
                            if (
                                typeof PerformanceNavigationTiming !== "undefined" &&
                                typeof window.performance !== "undefined" &&
                                typeof performance.getEntriesByType === "function"
                            ) {
                                var isBackForward = false;
                                var entries = performance.getEntriesByType("navigation");
                                entries.forEach((entry) => {
                                    if (entry.type === "back_forward") {
                                        isBackForward = true;
                                    }
                                });
                                if (isBackForward) {
                                    jQuery("body").unblock();
                                    return;
                                }
                            }
                            jQuery("body").block({
                                message: "' . esc_html__('Thank you for your order. We are now redirecting you to Payfast to make payment.', 'woocommerce-gateway-payfast') . '",
                                overlayCSS: { background: "#fff", opacity: 0.6 },
                                css: {
                                    padding: 20, textAlign: "center", color: "#555",
                                    border: "3px solid #aaa", backgroundColor:"#fff", cursor: "wait"
                                }
                            });
                            jQuery("#submit_payfast_payment_form").click();
                        });
                    </script>
                </form>';
            }

            public function handle_itn_request($data) {
                global $wpdb;
                $order_id = absint($data['custom_str3']);
                $order    = wc_get_order($order_id);
                $club_id = null;
                if ($order) {
                    foreach ($order->get_items() as $item) {
                        $product_id = $item->get_product_id();
                        $club_id = get_post_meta($product_id, '_select_club_id', true);
                        if ($club_id !== '') break;
                    }
                }
                $club_id_trim = trim($club_id);
                $is_global = empty($club_id_trim) || strtolower($club_id_trim) === 'global';

                // Set credentials and validation URL
                if (!$is_global) {
                    $row = $wpdb->get_row($wpdb->prepare(
                        "SELECT * FROM {$wpdb->prefix}clubs WHERE club_id = %d", $club_id
                    ));
                    if ($row) {
                        if ($row->sandbox_enabled == 1) {
                            $this->merchant_id  = $row->sandbox_merchant_id;
                            $this->merchant_key = $row->sandbox_merchant_key;
                            $this->pass_phrase  = $row->sandbox_passphrase;
                            $this->validate_url = 'https://sandbox.payfast.co.za/eng/query/validate';
                        } else {
                            $this->merchant_id  = $row->payfast_merchant_id;
                            $this->merchant_key = $row->payfast_merchant_key;
                            $this->pass_phrase  = $row->payfast_passphrase;
                            $this->validate_url = 'https://www.payfast.co.za/eng/query/validate';
                        }
                    }
                } else {
                    $this->merchant_id  = $this->get_option('merchant_id');
                    $this->merchant_key = $this->get_option('merchant_key');
                    $this->pass_phrase  = $this->get_option('pass_phrase');
                    $sandbox_enabled = $this->settings['sandbox'] ?? 'no';
                    if ($sandbox_enabled === 'yes') {
                        $this->validate_url = 'https://sandbox.payfast.co.za/eng/query/validate';
                    } else {
                        $this->validate_url = 'https://www.payfast.co.za/eng/query/validate';
                    }
                }

                // Now proceed with the parent ITN handler
                parent::handle_itn_request($data);
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
