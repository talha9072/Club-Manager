<?php

/* =========================================================
   HELPER: CHECK IF PRODUCT IS A SUBSCRIPTION (MEMBERSHIP)
========================================================= */
function is_membership_subscription_product($product_id) {
    if (!function_exists('wc_get_product')) return false;

    $product = wc_get_product($product_id);
    if (!$product) return false;

    return $product->is_type(['subscription', 'variable-subscription']);
}

/* =========================================================
   1) ATTACH GRAVITY FORM + HIDE ADD TO CART
========================================================= */
add_action('woocommerce_before_add_to_cart_button', 'attach_gravity_form_to_membership_products');

function attach_gravity_form_to_membership_products() {
    if (!is_product()) {
        return;
    }

    $product_id = get_queried_object_id();

    // ✅ REPLACED: category check → product type check
    if (!is_membership_subscription_product($product_id)) {
        return;
    }

    // Fetch the assigned Gravity Form ID
    $form_id = get_post_meta($product_id, '_select_registration_form', true);
    if (!$form_id) {
        return;
    }

    // Hide Add to Cart button
    echo '<style>.single_add_to_cart_button { display: none !important; }</style>';
    ?>
    <div id="membership-form">
        <?php echo do_shortcode('[gravityform id="' . esc_attr($form_id) . '" title="false" description="false"]'); ?>
    </div>
    <?php
}

/* =========================================================
   2) FORCE person_id IN URL
========================================================= */
add_action('template_redirect', 'force_person_id_in_url_for_membership_products');

function force_person_id_in_url_for_membership_products() {
    if (!class_exists('WooCommerce')) return;
    if (!function_exists('is_product') || !is_product() || !is_user_logged_in()) return;

    global $post;

    // ✅ REPLACED: has_term() → product type check
    if (!is_membership_subscription_product($post->ID)) return;

    if (isset($_GET['person_id'])) return;

    $user_id = get_current_user_id();

    $current_url  = home_url(add_query_arg(null, null));
    $redirect_url = add_query_arg('person_id', $user_id, $current_url);

    wp_safe_redirect($redirect_url);
    exit;
}

/* =========================================================
   3) GRAVITY FORM → ADD PRODUCT TO CART (UNCHANGED LOGIC)
========================================================= */
add_action('gform_after_submission', 'conditionally_add_product_to_cart_after_gform_submission', 10, 2);

function conditionally_add_product_to_cart_after_gform_submission($entry, $form) {

    // ⛔ KEEP THIS EXACTLY AS ORIGINAL (IT WORKS)
    if (is_product()) {

        $product_id = get_queried_object_id();

        // ✅ REPLACED: category logic → product type check
        if (!is_membership_subscription_product($product_id)) {
            return;
        }

        if (class_exists('WC_Cart') && function_exists('WC')) {
            $cart = WC()->cart;

            // Club restriction check
            $can_add = restrict_add_to_cart_for_multiple_clubs(true, $product_id, 1);

            if ($can_add) {

                // Prevent duplicate add
                $product_in_cart = false;
                foreach ($cart->get_cart() as $cart_item) {
                    if ($cart_item['product_id'] == $product_id) {
                        $product_in_cart = true;
                        break;
                    }
                }

                if (!$product_in_cart) {
                    $cart->add_to_cart($product_id);
                }

                // Redirect to basket
                wp_redirect(wc_get_cart_url());
                exit;

            } else {
                wc_add_notice(
                    __('You are not allowed to join multiple clubs.', 'woocommerce'),
                    'error'
                );
            }
        }
    }
}
