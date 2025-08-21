<?php

// Attach the correct Gravity Form to Membership Products & Hide "Add to Cart" button if present
add_action('woocommerce_before_add_to_cart_button', 'attach_gravity_form_to_membership_products');
function attach_gravity_form_to_membership_products() {
    if (!is_product()) {
        return;
    }

    global $wpdb;

    $product_id = get_queried_object_id();

    // Check if the product is in the "Membership" category
    $is_membership_product = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}term_relationships tr
        INNER JOIN {$wpdb->prefix}term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
        INNER JOIN {$wpdb->prefix}terms t ON tt.term_id = t.term_id
        WHERE tr.object_id = %d AND tt.taxonomy = 'product_cat' 
        AND (t.name = 'Membership' OR t.slug LIKE 'membership%%')", 
        $product_id
    ));

    if (!$is_membership_product) {
        return;
    }

    // Fetch the assigned Gravity Form ID
    $form_id = get_post_meta($product_id, '_select_registration_form', true);

    if (!$form_id) {
        return; // No Gravity Form assigned, so don't show anything
    }

    // Hide the "Add to Cart" button if a Gravity Form is attached
    echo '<style>.single_add_to_cart_button { display: none !important; }</style>';

    ?>
    <!-- Display the Gravity Form -->
    <div id="membership-form">
        <?php echo do_shortcode('[gravityform id="' . esc_attr($form_id) . '" title="false" description="false"]'); ?>
    </div>
    <?php
}

add_action('template_redirect', 'force_person_id_in_url_for_membership_products');
function force_person_id_in_url_for_membership_products() {
    if (!class_exists('WooCommerce')) return;
    if (!function_exists('is_product') || !is_product() || !is_user_logged_in()) return;

    global $post;

    // Check if this product belongs to 'membership' category
    if (!has_term('membership', 'product_cat', $post->ID)) return;

    // Already has person_id param? No redirect needed.
    if (isset($_GET['person_id'])) return;

    $user_id = get_current_user_id();

    // Build the current full URL and append person_id
    $current_url = home_url(add_query_arg(null, null));
    $redirect_url = add_query_arg('person_id', $user_id, $current_url);

    // Redirect with person_id param
    wp_safe_redirect($redirect_url);
    exit;
}


// Ensure product is added only if it's allowed by cart restrictions
add_action('gform_after_submission', 'conditionally_add_product_to_cart_after_gform_submission', 10, 2);

function conditionally_add_product_to_cart_after_gform_submission($entry, $form) {
    if (is_product()) {
        $product_id = get_queried_object_id();

        // Ensure WooCommerce cart is loaded
        if (class_exists('WC_Cart') && function_exists('WC')) {
            $cart = WC()->cart;

            // Run the club restriction check before adding to cart
            $can_add = restrict_add_to_cart_for_multiple_clubs(true, $product_id, 1);

            if ($can_add) {
                // Check if product is already in the cart
                $product_in_cart = false;
                foreach ($cart->get_cart() as $cart_item) {
                    if ($cart_item['product_id'] == $product_id) {
                        $product_in_cart = true;
                        break;
                    }
                }

                // Add the product to the cart if it's not already added
                if (!$product_in_cart) {
                    $cart->add_to_cart($product_id);
                }

                // Redirect to basket after submission
                wp_redirect(wc_get_cart_url());
                exit;
            } else {
                // Prevent Duplicate Notice
                $notices = WC()->session->get('wc_notices', []);
                

                // Check if the same error is already in WooCommerce notices
                if (!isset($notices['error']) || !in_array($error_message, $notices['error'])) {
                    wc_add_notice($error_message, 'error');
                }
            }
        }
    }
}



