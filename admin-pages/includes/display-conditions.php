<?php




// Show a checkout notice if products from different clubs are present only on the checkout page
add_action('wp_footer', function() {
    // Check if the URL contains '/checkout/'
    if (strpos($_SERVER['REQUEST_URI'], '/checkout/') !== false) {
        global $wpdb;

        $cart = WC()->cart->get_cart();
        $club_ids = [];

        // Collect club IDs from cart items
        foreach ($cart as $cart_item) {
            $product_id = $cart_item['product_id'];
            $club_id = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT meta_value FROM wp_postmeta WHERE post_id = %d AND meta_key = '_select_club_id'",
                    $product_id
                )
            );

            if ($club_id) {
                $club_ids[] = $club_id;
            }
        }

        // Check if multiple clubs exist
        if (count(array_unique($club_ids)) > 1) {
            // Inject notice via JavaScript
            ?>
            <script>
                jQuery(function($) {
                    var $noticesWrapper = $('.woocommerce .woocommerce-notices-wrapper');

                    if ($noticesWrapper.length) {
                        var noticeHtml = `
                            <div class="woocommerce-error" style="background-color: #f44336; color: #fff; border-left: 5px solid #d32f2f; padding: 15px; margin-bottom: 20px; font-weight: bold;">
                                🚨 You cannot add products from a different club to cart. Please remove existing items before adding products from another club.
                            </div>`;
                        $noticesWrapper.prepend(noticeHtml);
                        console.log('🚀 DEBUG: Notice manually injected into .woocommerce-notices-wrapper.');
                    } else {
                        console.warn('⚠️ DEBUG: .woocommerce-notices-wrapper not found.');
                    }
                });
            </script>
            <?php
        }
    }
});


// Add this function to your custom plugin
add_action('woocommerce_before_cart', 'show_product_ids_on_cart_or_checkout');
add_action('woocommerce_before_checkout_form', 'show_product_ids_on_cart_or_checkout');
add_action('woocommerce_add_to_cart_validation', 'restrict_add_to_cart_for_multiple_clubs', 10, 3);

function show_product_ids_on_cart_or_checkout() {
    // Check if we are on the cart or checkout page
    if (is_cart() || is_checkout()) {
        // Get cart contents
        $cart = WC()->cart->get_cart();

        if (!empty($cart)) {
            // Initialize an array to hold product IDs
            $product_ids = [];

            // Loop through cart items
            foreach ($cart as $cart_item) {
                $product_ids[] = $cart_item['product_id'];
            }

            

            // Add club name as a URL parameter
            add_club_name_to_url();
        }
    }
}

// Restrict checkout if products from more than one club are in the cart
add_action('woocommerce_check_cart_items', 'restrict_checkout_for_multiple_clubs');

function restrict_checkout_for_multiple_clubs() {
    global $wpdb;

    // Get cart contents
    $cart = WC()->cart->get_cart();

    if (!empty($cart)) {
        $club_ids = [];

        foreach ($cart as $cart_item) {
            $product_id = $cart_item['product_id'];

            // Fetch club information using the provided SQL
            $club_data = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT pm.meta_value AS club_id
                     FROM wp_postmeta pm
                     WHERE pm.post_id = %d AND pm.meta_key = '_select_club_id'",
                    $product_id
                )
            );

            if ($club_data) {
                $club_ids[] = $club_data->club_id;
            }
        }

        // Remove duplicates from club IDs
        $unique_club_ids = array_unique($club_ids);

        // Check if more than one club is present
        if (count($unique_club_ids) > 1) {
            // Clear existing notices to avoid generic WooCommerce messages
            wc_clear_notices();

            // Display an error notice and prevent checkout
            wc_add_notice('You cannot add products from multiple clubs. Please remove items from other clubs to proceed.', 'error');
        }
    }
}

// Restrict adding products from multiple clubs to the cart
function restrict_add_to_cart_for_multiple_clubs($passed, $product_id, $quantity) {
    global $wpdb;

    // Get the club ID of the product being added
    $club_data = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT pm.meta_value AS club_id
             FROM wp_postmeta pm
             WHERE pm.post_id = %d AND pm.meta_key = '_select_club_id'",
            $product_id
        )
    );

    if ($club_data) {
        $new_club_id = $club_data->club_id;

        // Check existing cart contents
        $cart = WC()->cart->get_cart();

        if (!empty($cart)) {
            $existing_club_ids = [];

            foreach ($cart as $cart_item) {
                $existing_product_id = $cart_item['product_id'];

                $existing_club_data = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT pm.meta_value AS club_id
                         FROM wp_postmeta pm
                         WHERE pm.post_id = %d AND pm.meta_key = '_select_club_id'",
                        $existing_product_id
                    )
                );

                if ($existing_club_data) {
                    $existing_club_ids[] = $existing_club_data->club_id;
                }
            }

            // Remove duplicates from existing club IDs
            $unique_existing_club_ids = array_unique($existing_club_ids);

            // Restrict if there's already a product from a different club in the cart
            if (!empty($unique_existing_club_ids) && !in_array($new_club_id, $unique_existing_club_ids)) {
                wc_add_notice('You cannot add products from a different club to the cart. Please remove existing items before adding products from another club.', 'error');
                return false;
            }
        }
    }

    return $passed;
}

// Add club name to URL as a parameter (excluding "Global")
function add_club_name_to_url() {
    global $wpdb;

    // Get current URL
    $current_url = home_url(add_query_arg(null, null));

    // Check if the 'club' parameter is already in the URL
    if (!isset($_GET['club'])) {
        // Get cart contents
        $cart = WC()->cart->get_cart();

        if (!empty($cart)) {
            $club_name = '';

            foreach ($cart as $cart_item) {
                $product_id = $cart_item['product_id'];

                // Fetch club name using the provided SQL
                $club_data = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT pm.meta_value AS club_name
                         FROM wp_postmeta pm
                         WHERE pm.post_id = %d AND pm.meta_key = '_select_club_name'",
                        $product_id
                    )
                );

                if ($club_data) {
                    $club_name = trim($club_data->club_name);
                    
                    // Check if the club name is "Global" (case insensitive)
                    if (strtolower($club_name) !== 'global') {
                        break; // Use the first valid (non-global) club name found
                    }
                }
            }

            if (!empty($club_name) && strtolower($club_name) !== 'global') {
                // Add club name as a URL parameter
                $updated_url = add_query_arg('club', urlencode($club_name), $current_url);

                // Redirect to the updated URL only if it doesn't already include the 'club' parameter
                if ($current_url !== $updated_url) {
                    wp_safe_redirect($updated_url);
                    exit;
                }
            }
        }
    }
}


// Pass club parameter to all WooCommerce pages
add_filter('woocommerce_get_cart_url', 'add_club_param_to_url');
add_filter('woocommerce_get_checkout_url', 'add_club_param_to_url');
add_filter('woocommerce_account_menu_items', 'add_club_param_to_account_urls');
// add_filter('term_link', 'add_club_param_to_category_urls', 10, 3);
// add_filter('post_type_link', 'add_club_param_to_shop_urls', 10, 2);

// Add club param to URLs (excluding "Global")
function add_club_param_to_url($url) {
    if (isset($_GET['club'])) {
        $club_name = trim(sanitize_text_field($_GET['club']));
        
        // Only add if club is NOT "Global"
        if (strtolower($club_name) !== 'global') {
            $url = add_query_arg('club', $club_name, $url);
        }
    }
    return $url;
}

// Add club param to account URLs (excluding "Global")
function add_club_param_to_account_urls($items) {
    if (isset($_GET['club'])) {
        $club_name = trim(sanitize_text_field($_GET['club']));
        
        // Only add if club is NOT "Global"
        if (strtolower($club_name) !== 'global') {
            foreach ($items as $key => $url) {
                $items[$key] = add_query_arg('club', $club_name, $url);
            }
        }
    }
    return $items;
}

// Add club param to category URLs (excluding "Global")
// function add_club_param_to_category_urls($url, $term, $taxonomy) {
//     if (isset($_GET['club'])) {
//         $club_name = trim(sanitize_text_field($_GET['club']));
        
//         // Only add if club is NOT "Global"
//         if (strtolower($club_name) !== 'global') {
//             $url = add_query_arg('club', $club_name, $url);
//         }
//     }
//     return $url;
// }

// Add club param to shop/product URLs (excluding "Global")
// function add_club_param_to_shop_urls($url, $post) {
//     if (isset($_GET['club']) && $post->post_type === 'product') {
//         $club_name = trim(sanitize_text_field($_GET['club']));
        
//         // Only add if club is NOT "Global"
//         if (strtolower($club_name) !== 'global') {
//             $url = add_query_arg('club', $club_name, $url);
//         }
//     }
//     return $url;
// }


// Call this function on cart, checkout, account, shop, and product category pages
add_action('woocommerce_before_cart', 'add_club_name_to_url');
add_action('woocommerce_before_checkout_form', 'add_club_name_to_url');
add_action('woocommerce_before_account_navigation', 'add_club_name_to_url');
// add_action('woocommerce_before_shop_loop', 'add_club_name_to_url');
// add_action('woocommerce_archive_description', 'add_club_name_to_url');





add_action('template_redirect', 'add_club_param_for_product_categories');

function add_club_param_for_product_categories() {
    // Check if WooCommerce is active
    if (!class_exists('WooCommerce')) {
        return; // Exit if WooCommerce is not active
    }
    
    if (function_exists('is_product_category') && is_product_category()) { // Ensure this logic runs only on product category pages
        global $wpdb;

        // Get the current URL path (e.g., /product-category/mrcap/)
        $current_path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

        // Extract the slug after "product-category/"
        $path_parts = explode('/', $current_path);
        if (isset($path_parts[1])) {
            $category_slug = $path_parts[1]; // This gets "mrcap" or "mrnam"
        } else {
            return; // Exit if the slug is not found
        }

        // Query the database to find the matching club
        $club = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT club_name FROM wp_clubs WHERE club_url LIKE %s",
                '%' . $wpdb->esc_like($category_slug) . '%'
            )
        );

        // If a matching club is found and the club parameter is not already in the URL
        if ($club && !isset($_GET['club'])) {
            $club_name = trim($club->club_name); // Clean up the club name
            
            // Only proceed if the club is NOT "Global"
            if (strtolower($club_name) !== 'global') {
                $club_name_encoded = urlencode($club_name); // Encode the club name for URL safety
                $current_url = home_url(add_query_arg(null, null)); // Get the current full URL
                $updated_url = add_query_arg('club', $club_name_encoded, $current_url);

                // Redirect to the updated URL
                wp_safe_redirect($updated_url);
                exit;
            }
        }
    }
}














add_action('wp_footer', 'filter_product_category_dropdown_with_descendants');

function filter_product_category_dropdown_with_descendants() {
    // Check if WooCommerce is active
    if (!class_exists('WooCommerce')) {
        return; // Exit if WooCommerce is not active
    }
    
    // Check if the current page has the WooCommerce category dropdown
    $is_product_category_page = is_product_category() || is_shop() || is_product();
    
    // If the dropdown is not present and no "club" filter exists, don't run SQL
    if (!$is_product_category_page && !isset($_GET['club'])) {
        return;
    }

    global $wpdb;

    // Get the club name from the URL
    $club_name = isset($_GET['club']) ? sanitize_text_field(urldecode($_GET['club'])) : '';

    // Query to find parent term IDs for categories linked to the given club
    $categories = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT 
                tm.term_id,
                t.name AS category_name,
                tt.count AS product_count
            FROM 
                wp_termmeta AS tm
            INNER JOIN 
                wp_terms AS t ON tm.term_id = t.term_id
            INNER JOIN 
                wp_term_taxonomy AS tt ON t.term_id = tt.term_id
            LEFT JOIN 
                wp_clubs AS c ON tm.meta_value = c.club_id
            WHERE 
                tm.meta_key = 'taxonomy_custom_dropdown'
                AND c.club_name = %s
                AND tt.taxonomy = 'product_cat'",
            $club_name
        )
    );

    // Exit if no categories found (avoid running extra code)
    if (empty($categories)) {
        return;
    }

    // Recursive function to fetch all descendants of a category
    function get_category_descendants($parent_id, $wpdb) {
        static $cache = []; // Cache results to avoid redundant queries
        if (isset($cache[$parent_id])) {
            return $cache[$parent_id];
        }

        $descendants = [];
        $children = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT 
                    t.term_id,
                    t.name AS category_name,
                    tt.count AS product_count
                FROM 
                    wp_terms AS t
                INNER JOIN 
                    wp_term_taxonomy AS tt ON t.term_id = tt.term_id
                WHERE 
                    tt.parent = %d
                    AND tt.taxonomy = 'product_cat'",
                $parent_id
            )
        );

        foreach ($children as $child) {
            $descendants[] = $child;
            $descendants = array_merge($descendants, get_category_descendants($child->term_id, $wpdb));
        }

        $cache[$parent_id] = $descendants;
        return $descendants;
    }

    // Generate the filtered dropdown options with counts
    $options = '';
    $added_ids = []; // Track added category IDs to prevent duplicates

    foreach ($categories as $category) {
        if (!in_array($category->term_id, $added_ids)) {
            $term_link = get_term_link((int)$category->term_id, 'product_cat');
            if (is_wp_error($term_link)) {
                continue; // Skip this category if there's an error
            }
            $term_link = add_query_arg('club', urlencode($club_name), $term_link);
            $options .= sprintf(
                '<option value="%s">%s (%d)</option>',
                esc_url($term_link),
                esc_html($category->category_name),
                intval($category->product_count)
            );
            $added_ids[] = $category->term_id; // Mark this ID as added
        }

        // Fetch all descendants of the current category
        $descendants = get_category_descendants($category->term_id, $wpdb);
        foreach ($descendants as $descendant) {
            if (!in_array($descendant->term_id, $added_ids)) {
                $descendant_link = get_term_link((int)$descendant->term_id, 'product_cat');
                if (is_wp_error($descendant_link)) {
                    continue; // Skip this descendant if there's an error
                }
                $descendant_link = add_query_arg('club', urlencode($club_name), $descendant_link);
                $options .= sprintf(
                    '<option value="%s">− %s (%d)</option>',
                    esc_url($descendant_link),
                    esc_html($descendant->category_name),
                    intval($descendant->product_count)
                );
                $added_ids[] = $descendant->term_id; // Mark this ID as added
            }
        }
    }

    // Inject JavaScript only if WooCommerce category dropdown exists
    echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            var dropdowns = document.querySelectorAll('.wc-block-product-categories__dropdown select');
            if (dropdowns.length > 0) { // Only run if dropdown exists
                dropdowns.forEach(function(dropdown) {
                    dropdown.innerHTML = '<option value=\"false\" hidden>Select a category</option>' + " . json_encode($options) . ";
                });
            }
        });
    </script>";
}







add_action('template_redirect', function () {
    global $wpdb;

    // Check if the user is logged in
    if (is_user_logged_in()) {
        // Fetch the current user's email
        $current_user = wp_get_current_user();
        $user_email = $current_user->user_email;

        // Query the database to get the club details for the logged-in user
        $club_member = $wpdb->get_row(
            $wpdb->prepare("SELECT club_id FROM wp_club_members WHERE user_email = %s", $user_email)
        );

        if ($club_member && isset($club_member->club_id)) {
            $club_id = $club_member->club_id;

            // Query the wp_clubs table to fetch the club name and related details
            $club = $wpdb->get_row(
                $wpdb->prepare("SELECT club_name FROM wp_clubs WHERE club_id = %d", $club_id)
            );

            if ($club && isset($club->club_name)) {
                $club_name = urlencode($club->club_name);

                // Get the current URL slug
                $current_page_slug = trim($_SERVER['REQUEST_URI'], '/');

                // ✅ Only check for manager-dashboard
                if (strpos($current_page_slug, 'manager-dashboard') !== false) {
                    // Check if the URL already contains the 'club' parameter
                    if (!strpos($_SERVER['REQUEST_URI'], 'club=')) {
                        // Append the 'club' parameter to the URL
                        $redirect_url = add_query_arg('club', $club_name, home_url($current_page_slug));
                        wp_redirect($redirect_url);
                        exit;
                    }
                }
            }
        }
    }
});


// Redirect bca_admin users to the manager-dashboard only once per login
add_action('template_redirect', function () {
    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();

        // Check if the user has the bca_admin role
        if (in_array('bca_admin', $current_user->roles)) {
            // Use WordPress user meta instead of $_SESSION
            $redirected = get_user_meta($current_user->ID, 'bca_admin_redirected', true);

            if (!$redirected) {
                update_user_meta($current_user->ID, 'bca_admin_redirected', true);
                wp_redirect(home_url('/manager-dashboard'));
                exit;
            }
        }
    }
});

// Clear the redirect flag on logout
add_action('wp_logout', function () {
    $user_id = get_current_user_id();
    if ($user_id) {
        delete_user_meta($user_id, 'bca_admin_redirected');
    }
});




add_action('woocommerce_thankyou', function ($order_id) {
    if (!$order_id) {
        return;
    }

    $order = wc_get_order($order_id);
    if (!$order) {
        return;
    }

    $club_names = [];

    // Loop through order items to get associated club names
    foreach ($order->get_items() as $item) {
        $product_id = $item->get_product_id();
        $club_name = get_post_meta($product_id, '_select_club_name', true);

        if (!empty($club_name)) {
            $club_names[] = strtolower(trim($club_name)); // Normalize for comparison
        }
    }

    // Remove duplicates
    $club_names = array_unique($club_names);

    // Filter BACS details based on club name (if available)
    add_filter('woocommerce_bacs_accounts', function ($bacs_accounts) use ($club_names) {
        // If no club name is found OR club name is "Global", show only "BMW Clubs Africa" account
        if (empty($club_names) || in_array('global', $club_names)) {
            $filtered_bacs = array_filter($bacs_accounts, function ($account) {
                return strtolower(trim($account['account_name'])) === 'bmw clubs africa';
            });

            return !empty($filtered_bacs) ? array_values($filtered_bacs) : $bacs_accounts;
        }

        // Otherwise, filter BACS accounts based on the club name
        $filtered_bacs = array_filter($bacs_accounts, function ($account) use ($club_names) {
            return in_array(strtolower(trim($account['account_name'])), $club_names);
        });

        return !empty($filtered_bacs) ? array_values($filtered_bacs) : $bacs_accounts;
    });

}, 10, 1);


add_filter('woocommerce_bacs_accounts', function ($bacs_accounts, $order_id) {
    if (!$order_id) {
        return $bacs_accounts;
    }

    $order = wc_get_order($order_id);
    if (!$order) {
        return $bacs_accounts;
    }

    $club_names = [];

    // Loop through order items to get associated club names
    foreach ($order->get_items() as $item) {
        $product_id = $item->get_product_id();
        $club_name = get_post_meta($product_id, '_select_club_name', true);

        if (!empty($club_name)) {
            $club_names[] = strtolower(trim($club_name)); // Normalize for comparison
        }
    }

    // Remove duplicates
    $club_names = array_unique($club_names);

    // If no club name is found OR club name is "Global", show only "BMW Clubs Africa" account
    if (empty($club_names) || in_array('global', $club_names)) {
        $filtered_bacs = array_filter($bacs_accounts, function ($account) {
            return strtolower(trim($account['account_name'])) === 'bmw clubs africa';
        });

        return !empty($filtered_bacs) ? array_values($filtered_bacs) : $bacs_accounts;
    }

    // Otherwise, filter BACS accounts based on the club name
    $filtered_bacs = array_filter($bacs_accounts, function ($account) use ($club_names) {
        return in_array(strtolower(trim($account['account_name'])), $club_names);
    });

    return !empty($filtered_bacs) ? array_values($filtered_bacs) : $bacs_accounts;
}, 10, 2);


add_action('template_redirect', 'redirect_thankyou_with_club_param');
function redirect_thankyou_with_club_param() {
    // Check if we're on the thank you page
    if (!is_wc_endpoint_url('order-received')) {
        return;
    }

    // Only do this once to avoid redirect loops
    if (isset($_GET['club']) || isset($_GET['redirected'])) {
        return;
    }

    // Get order ID from query vars
    $order_id = get_query_var('order-received');
    if (!$order_id) {
        return;
    }

    // Get the order
    $order = wc_get_order($order_id);
    if (!$order) {
        return;
    }

    // Get the first product in the order
    foreach ($order->get_items() as $item) {
        $product_id = $item->get_product_id();
        $club_id = get_post_meta($product_id, '_select_club_name', true);
        if (!empty($club_id)) {
            // Build redirect URL with club param
            $redirect_url = add_query_arg([
                'key' => $_GET['key'] ?? '',
                'club' => $club_id,
                'redirected' => '1'
            ], wc_get_endpoint_url('order-received', $order_id, wc_get_checkout_url()));

            wp_safe_redirect($redirect_url);
            exit;
        }
    }
}




// Restrict adding EventON tickets from multiple clubs to the cart
add_action('wp_ajax_evotx_add_to_cart', 'restrict_eventon_ticket_club_check', 1);
add_action('wp_ajax_nopriv_evotx_add_to_cart', 'restrict_eventon_ticket_club_check', 1);

function restrict_eventon_ticket_club_check() {
    global $wpdb;

    // Get EventON ticket product ID from AJAX
    $event_data = isset($_POST['event_data']) ? $_POST['event_data'] : [];
    $product_id = isset($event_data['wcid']) ? intval($event_data['wcid']) : 0;

    if (!$product_id) {
        wp_send_json_error('Invalid product ID.', 400);
    }

    // Support for variations: use parent product for club check
    $product = wc_get_product($product_id);
    $club_check_id = $product->is_type('variation') ? $product->get_parent_id() : $product_id;

    // Get current product's club ID
    $club_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_select_club_id'",
            $club_check_id
        )
    );

    if (!$club_id) {
        wp_send_json_error('Club ID not found for this product.', 400);
    }

    // Check all items in cart for existing clubs
    $cart = WC()->cart->get_cart();
    $existing_club_ids = [];

    foreach ($cart as $item) {
        $cart_product = wc_get_product($item['product_id']);
        $cart_check_id = $cart_product->is_type('variation') ? $cart_product->get_parent_id() : $cart_product->get_id();

        $existing_club = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_select_club_id'",
                $cart_check_id
            )
        );

        if ($existing_club) {
            $existing_club_ids[] = $existing_club;
        }
    }

    $unique_existing_clubs = array_unique($existing_club_ids);

    // ❌ Block if cart has product from a different club
    if (!empty($unique_existing_clubs) && !in_array($club_id, $unique_existing_clubs)) {
        wp_send_json([
            'status' => 'error',
            'msg' => '❌ You cannot add a ticket from a different club. Please empty your cart first.',
        ]);
    }

    //  Let EventON handle the rest if allowed
    // Return nothing so their original handler continues
}






add_shortcode('club_menu_dynamic', function () {
    if (is_admin()) return '';

    global $wpdb;

    $slug = '';
    $force_query_param = false;
    $post_id = get_queried_object_id();

    //  Detect special pages where ?club= is prioritized
    $is_special_page = (
        is_page('member-dashboard') ||
        is_page('manager-dashboard') ||
        in_array((int)$post_id, [1102132, 1102420], true) ||
        is_product_category() ||
        is_cart() ||
        is_checkout() ||
        is_order_received_page()
    );

    if ($is_special_page) {
        $force_query_param = true;
    }

    //  Step 1: Use _select_club_name if not forcing query param
    if (!$force_query_param && is_singular(['post', 'product', 'ajde_events', 'page'])) {
        $club_name = get_post_meta($post_id, '_select_club_name', true);

        if (!empty($club_name) && strtolower($club_name) !== 'global') {
            $club = $wpdb->get_row(
                $wpdb->prepare("SELECT club_url FROM wp_clubs WHERE club_name = %s", $club_name)
            );

            if ($club && !empty($club->club_url)) {
                $parsed = parse_url($club->club_url);
                $club_path = trim($parsed['path'] ?? '', '/');
                if (!empty($club_path)) {
                    $slug = strtok($club_path, '/');
                }
            }
        } elseif (strtolower($club_name) === 'global') {
            $slug = 'global';
        }
    }

   //  Step 2: Use ?club= only if forced or meta failed
if ($force_query_param || (empty($slug) && isset($_GET['club']) && !empty($_GET['club']))) {
    $club_name = isset($_GET['club']) ? urldecode($_GET['club']) : '';

    if (!empty($club_name)) {
        $club = $wpdb->get_row(
            $wpdb->prepare("SELECT club_url FROM wp_clubs WHERE club_name = %s", $club_name)
        );

        if ($club && !empty($club->club_url)) {
            $parsed = parse_url($club->club_url);
            $club_path = trim($parsed['path'] ?? '', '/');
            if (!empty($club_path)) {
                $slug = strtok($club_path, '/');
            }
        }
    }
}

    //  Step 3: If no slug yet, fallback to path (e.g. /mrcap/)
    if (empty($slug)) {
        $slug = strtok(trim($_SERVER['REQUEST_URI'], '/'), '/');
    }

    //  Step 4: Slug to menu map
    $club_slug_map = [
        'mrcap' => '[maxmegamenu location=max_mega_menu_1]',
        'mrcen' => '[maxmegamenu location=max_mega_menu_2]',
        'mrkzn' => '[maxmegamenu location=max_mega_menu_3]',
        'mreca' => '[maxmegamenu location=max_mega_menu_4]',
        'mrgdn' => '[maxmegamenu location=max_mega_menu_5]',
        'mrinl' => '[maxmegamenu location=max_mega_menu_6]',
        'mrnam' => '[maxmegamenu location=max_mega_menu_7]',
        'mrlwv' => '[maxmegamenu location=max_mega_menu_8]',
        'mrwrd' => '[maxmegamenu location=max_mega_menu_9]',
        'mcpta' => '[maxmegamenu location=max_mega_menu_10]',
        'cccpt' => '[maxmegamenu location=max_mega_menu_11]',
        'ccgtn' => '[maxmegamenu location=max_mega_menu_12]',
    ];

    //  Final output
    $shortcode = $club_slug_map[$slug] ?? '[maxmegamenu location=primary-menu]';

    

    return do_shortcode($shortcode);
});






