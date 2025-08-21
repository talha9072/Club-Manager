<?php


/**
 * Debugging: Log messages to debug.log
 */
function debug_log($message) {
    error_log(print_r($message, true));
}

/**
 * Check if the logged-in user belongs to a specific role in `wp_club_members`
 */
function get_user_club_role() {
    if (is_user_logged_in()) {
        $user = wp_get_current_user();

        // Ensure the user is NOT an administrator
        if (in_array('administrator', (array) $user->roles)) {
            debug_log("✅ User {$user->user_email} is an Administrator. Skipping Club Role check.");
            return false;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . "club_members";

        // Get user's club role
        $query = $wpdb->prepare("SELECT role FROM $table_name WHERE user_email = %s", $user->user_email);
        $role_result = $wpdb->get_var($query);

        if ($role_result) {
            debug_log("✅ User {$user->user_email} is a {$role_result}.");
            return $role_result;
        } else {
            debug_log("⛔ User {$user->user_email} does not have a Club Role.");
            return false;
        }
    }
    return false;
}

/**
 * Apply capabilities based on the user's club role
 */
function enforce_club_role_capabilities() {
    if ($role = get_user_club_role()) {
        $user = wp_get_current_user();

        // Define capabilities based on role
        $capabilities = ['upload_files']; // All roles get upload capability

        if ($role === "Treasurer") {
            $capabilities = array_merge($capabilities, [
                'edit_shop_orders', 'edit_others_shop_orders', 'delete_shop_orders',
                'publish_shop_orders', 'read_private_shop_orders', 'delete_private_shop_orders',
                'delete_published_shop_orders', 'delete_others_shop_orders', 'edit_private_shop_orders',
                'edit_published_shop_orders'
            ]);
        } elseif ($role === "Media/Social") {
            $capabilities = array_merge($capabilities, [
                'edit_products', 'edit_others_products', 'delete_products', 'publish_products',
                'read_private_products', 'delete_private_products', 'delete_published_products',
                'delete_others_products', 'edit_private_products', 'edit_published_products',

                'edit_eventons', 'edit_others_eventons', 'delete_eventons', 'publish_eventons',
                'read_private_eventons', 'delete_private_eventons', 'delete_published_eventons',
                'delete_others_eventons', 'edit_private_eventons', 'edit_published_eventons',

                'rank_math_404_monitor',
                'rank_math_admin_bar',
                'rank_math_analytics',
                'rank_math_content_ai',
                'rank_math_edit_htaccess',
                'rank_math_general',
                'rank_math_link_builder',
                'rank_math_onpage_advanced',
                'rank_math_onpage_analysis',
                'rank_math_onpage_general',
                'rank_math_onpage_snippet',
                'rank_math_onpage_social',
                'rank_math_redirections',
                'rank_math_role_manager',
                'rank_math_site_analysis',
                'rank_math_sitemap',
                'rank_math_titles',

                'edit_posts', 'edit_others_posts', 'delete_posts', 'publish_posts',
                'read_private_posts', 'read', 'delete_private_posts', 'delete_published_posts',
                'delete_others_posts', 'edit_private_posts', 'edit_published_posts',

                'edit_pages', 'edit_others_pages', 'delete_pages', 'publish_pages',
                'read_private_pages', 'delete_private_pages', 'delete_published_pages',
                'delete_others_pages', 'edit_private_pages', 'edit_published_pages',
            ]);
        } elseif ($role === "Store Manager" || $role === "Club Manager") {
            $capabilities = array_merge($capabilities, [
                'edit_shop_orders', 'edit_others_shop_orders', 'delete_shop_orders',
                'publish_shop_orders', 'read_private_shop_orders', 'delete_private_shop_orders',
                'delete_published_shop_orders', 'delete_others_shop_orders', 'edit_private_shop_orders',
                'edit_published_shop_orders',

                'edit_products', 'edit_others_products', 'delete_products', 'publish_products',
                'read_private_products', 'delete_private_products', 'delete_published_products',
                'delete_others_products', 'edit_private_products', 'edit_published_products',

                'manage_automatewoo', 'view_automatewoo_reports', 'automatewoo_manage_workflows',
                'automatewoo_edit_workflows', 'automatewoo_delete_workflows', 'automatewoo_publish_workflows',
                'automatewoo_read_private_workflows', 'automatewoo_manage_queues', 'automatewoo_view_logs',
                'automatewoo_settings',

                'edit_pages', 'edit_others_pages', 'delete_pages', 'publish_pages',
                'read_private_pages', 'delete_private_pages', 'delete_published_pages',
                'delete_others_pages', 'edit_private_pages', 'edit_published_pages',

                'list_users', 'promote_users', 'create_users', 'delete_users','edit_users', 'remove_users',


                'manage_woocommerce', 'view_woocommerce_reports', 'edit_subscriptions', 'read_private_subscriptions',

                // Added capabilities for Club Manager: Posts and Events
                'edit_posts', 'edit_others_posts', 'delete_posts', 'publish_posts',
                'read_private_posts', 'read', 'delete_private_posts', 'delete_published_posts',
                'delete_others_posts', 'edit_private_posts', 'edit_published_posts',

                'edit_eventons', 'edit_others_eventons', 'delete_eventons', 'publish_eventons',
                'read_private_eventons', 'delete_private_eventons', 'delete_published_eventons',
                'delete_others_eventons', 'edit_private_eventons', 'edit_published_eventons',
                'rank_math_404_monitor',
                'rank_math_admin_bar',
                'rank_math_analytics',
                'rank_math_content_ai',
                'rank_math_edit_htaccess',
                'rank_math_general',
                'rank_math_link_builder',
                'rank_math_onpage_advanced',
                'rank_math_onpage_analysis',
                'rank_math_onpage_general',
                'rank_math_onpage_snippet',
                'rank_math_onpage_social',
                'rank_math_redirections',
                'rank_math_role_manager',
                'rank_math_site_analysis',
                'rank_math_sitemap',
                'rank_math_titles',
                // Shop Coupon Capabilities
                'edit_shop_coupons', 'edit_others_shop_coupons', 'delete_shop_coupons', 'publish_shop_coupons',
                'read_private_shop_coupons', 'delete_private_shop_coupons', 'delete_published_shop_coupons',
                'delete_others_shop_coupons', 'edit_private_shop_coupons', 'edit_published_shop_coupons',
            ]);
        }

        foreach ($capabilities as $cap) {
            $user->add_cap($cap);
        }

        debug_log(" {$role} capabilities applied for {$user->user_email}");
    }
}

add_action('set_current_user', 'enforce_club_role_capabilities');

// Removing rank math from top bar
add_action('admin_bar_menu', function ($wp_admin_bar) {
    if (!current_user_can('administrator')) {
        $wp_admin_bar->remove_node('rank-math'); // Remove main node
    }
}, 999);





/**
 * Restrict Admin Menu for Different Club Roles
 */
function restrict_admin_menu_for_club_roles() {
    global $menu, $submenu;

    if ($role = get_user_club_role()) {
        $user = wp_get_current_user();
        debug_log("🚨 Restricting menu for {$role}: {$user->user_email}");

        // Get current user role
        $user_roles = $user->roles;
        $is_admin = in_array('administrator', $user_roles); // Check if user is an admin

        // **Remove "Event Tickets" menu for non-admins**
        if (!$is_admin) {

            add_action('admin_menu', function () {
                global $submenu;
            
                if (isset($submenu['woocommerce'])) {
                    // Default: Orders and Subscriptions visible
                    $allowed_woo_menus = [
                        'edit.php?post_type=shop_order',  // Orders
                        'edit.php?post_type=shop_subscription' // Subscriptions
                    ];
            
                    // Get the user role
                    $role = get_user_club_role();
            
                    // If Treasurer or Store Manager, only allow "Orders"
                    if ($role === "Treasurer" || $role === "Store Manager") {
                        $allowed_woo_menus = [
                            'edit.php?post_type=shop_order' // Only Orders
                        ];
                    }
            
                    // Remove unwanted WooCommerce submenu items
                    foreach ($submenu['woocommerce'] as $key => $item) {
                        if (!in_array($item[2], $allowed_woo_menus)) {
                            unset($submenu['woocommerce'][$key]);
                        }
                    }
            
                    // Re-index WooCommerce submenu array
                    $submenu['woocommerce'] = array_values($submenu['woocommerce']);
                }
            }, 999);
            

            add_action('admin_menu', function () {
                remove_menu_page('edit.php?post_type=evo-tix'); // Remove top-level Event Tickets menu
                remove_submenu_page('edit.php?post_type=ajde_events', 'edit.php?post_type=evo-tix'); // Remove from Events submenu
            }, 999); // Run late to override any plugins that might add it later

            // Extra security: Hide it with CSS and JavaScript
            add_action('admin_head', function () {
                echo '<style>
                    #menu-posts-evo-tix, 
                    [href="edit.php?post_type=evo-tix"],
                    [href="admin.php?page=event-tickets"] { 
                        display: none !important; 
                    }
                </style>';
                echo '<script>
                    document.addEventListener("DOMContentLoaded", function() { 
                        let etMenu = document.querySelectorAll("[href=\'edit.php?post_type=evo-tix\']");
                        etMenu.forEach(el => el.remove());
                    });
                </script>';
            });
        }

        // Define allowed menu items for each role
        $allowed_menus = [
            "Treasurer" => ['woocommerce'],
            "Media/Social" => ['edit.php', 'edit.php?post_type=product', 'edit.php?post_type=ajde_events', 'edit.php?post_type=page'],
            "Store Manager" => ['woocommerce', 'edit.php?post_type=product', 'edit.php?post_type=shop_order', 'admin.php?page=automatewoo', 'edit.php?post_type=page'],
            "Club Manager" => ['edit.php', 'edit.php?post_type=product', 'edit.php?post_type=ajde_events', 'woocommerce', 'users.php', 'edit.php?post_type=shop_order', 'admin.php?page=automatewoo', 'edit.php?post_type=page', 'edit.php?post_type=shop_subscription']
        ];

        // **Force AutomateWoo menu to be visible for Store Manager & Club Manager**
        if ($role === "Store Manager" || $role === "Club Manager") {
            $automatewoo_menus = [
                'admin.php?page=automatewoo',
                'admin.php?page=automatewoo-settings',
                'admin.php?page=automatewoo-dashboard',
                'edit.php?post_type=aw_workflow',
                'admin.php?page=automatewoo-logs',
                'admin.php?page=automatewoo-queue'
            ];
            $allowed_menus[$role] = array_merge($allowed_menus[$role], $automatewoo_menus);
        }

        // **Filter menu based on allowed items**
        foreach ($menu as $key => $value) {
            if (!in_array($value[2], $allowed_menus[$role] ?? []) && !$is_admin) {
                unset($menu[$key]);
            }
        }

        debug_log("✅ Admin menu restricted for {$role}.");
    }
}
add_action('admin_menu', 'restrict_admin_menu_for_club_roles', 100);


add_action('admin_menu', function () {
    if (current_user_can('administrator')) {
        return;
    }

    $role = get_user_club_role();
    if ($role !== 'Club Manager') {
        return;
    }

    // ✅ Step 1: Add top-level menu with custom slug
    add_menu_page(
        'Coupons',
        'Coupons',
        'edit_shop_coupons',
        'club_marketing_menu',
        function () {
            // Redirect to Coupons
            wp_redirect(admin_url('edit.php?post_type=shop_coupon'));
            exit;
        },
        'dashicons-megaphone',
        55
    );

    // ✅ Step 2: Add ONLY the "Coupons" submenu
    add_submenu_page(
        'club_marketing_menu',
        'Coupons',
        'Coupons',
        'edit_shop_coupons',
        'edit.php?post_type=shop_coupon'
    );
}, 999);

// ✅ Step 3: Remove the automatic duplicate submenu
add_action('admin_head', function () {
    global $submenu;

    if (isset($submenu['club_marketing_menu'])) {
        // Remove the first item (duplicate "Marketing")
        unset($submenu['club_marketing_menu'][0]);

        // Reindex to prevent gaps
        $submenu['club_marketing_menu'] = array_values($submenu['club_marketing_menu']);
    }
});




// remove view link
function remove_view_link_from_user_row_actions($actions, $user_object) {
    // Remove the "View" link for all users
    unset($actions['view']);
    return $actions;
}
add_filter('user_row_actions', 'remove_view_link_from_user_row_actions', 10, 2);


function force_automatewoo_menu() {
    if (current_user_can('manage_automatewoo')) {
        // Create AutomateWoo menu, redirecting it to Workflows
        add_menu_page(
            __('AutomateWoo', 'automatewoo'),
            __('AutomateWoo', 'automatewoo'),
            'manage_automatewoo',
            'edit.php?post_type=aw_workflow', // Redirect AutomateWoo to Workflows
            '',
            'dashicons-analytics',
            56
        );

        // Re-add the missing submenus manually in the correct order
        add_submenu_page(
            'edit.php?post_type=aw_workflow',
            __('Workflows', 'automatewoo'),
            __('Workflows', 'automatewoo'),
            'manage_woocommerce',
            'edit.php?post_type=aw_workflow'
        );

        add_submenu_page(
            'edit.php?post_type=aw_workflow',
            __('Logs', 'automatewoo'),
            __('Logs', 'automatewoo'),
            'manage_woocommerce',
            'automatewoo-logs'
        );

        add_submenu_page(
            'edit.php?post_type=aw_workflow',
            __('Queue', 'automatewoo'),
            __('Queue', 'automatewoo'),
            'manage_woocommerce',
            'automatewoo-queue'
        );
    }
}

// Hook to modify the menu AFTER AutomateWoo registers its submenus
add_action('admin_menu', 'force_automatewoo_menu', 10001);

add_action('admin_menu', function () {
    global $submenu, $current_user;

    // Get current user role
    $user_roles = $current_user->roles;
    $is_admin = in_array('administrator', $user_roles); // Check if user is an admin

    if (isset($submenu['edit.php?post_type=aw_workflow'])) {
        // Define the allowed submenu items in the correct order
        $ordered_submenus = [
            ['Workflows', 'manage_woocommerce', 'edit.php?post_type=aw_workflow'], // First item (default)
            ['Logs', 'manage_woocommerce', 'automatewoo-logs'],
            ['Queue', 'manage_woocommerce', 'automatewoo-queue'],
        ];

        // Remove existing submenus
        unset($submenu['edit.php?post_type=aw_workflow']);

        // Add the reordered submenus
        foreach ($ordered_submenus as $submenu_item) {
            $submenu['edit.php?post_type=aw_workflow'][] = $submenu_item;
        }

        // If the user is not an admin, remove everything else
        if (!$is_admin) {
            // Filter allowed submenu slugs for non-admins
            $allowed_submenus = array_column($ordered_submenus, 2);

            foreach ($submenu['edit.php?post_type=aw_workflow'] as $key => $item) {
                if (!in_array($item[2], $allowed_submenus)) {
                    unset($submenu['edit.php?post_type=aw_workflow'][$key]);
                }
            }

            // Re-index submenu array to prevent empty gaps
            $submenu['edit.php?post_type=aw_workflow'] = array_values($submenu['edit.php?post_type=aw_workflow']);
        }
    }
}, 10002); // Ensure this runs after AutomateWoo registers its menus



// Customize top bar: remove "New", "Comments", "Edit Page", "View Post", and Archive links
function remove_toolbar_items($wp_admin_bar) {
    if (!current_user_can('administrator')) { // Only for non-admins
        $nodes_to_remove = [
            'site-name',    // **Removes Site Name from Admin Bar**
            'new-content',  // "New +" menu
            'comments',     // Comments icon
            'edit',         // "Edit Page"
            'view',         // "View Post/Page"
            'archive',      // Custom "View Posts" archive link
            'customize',    // "Customize" button in admin bar
            'woocommerce-site-visibility-badge' // **Removes WooCommerce "Live" Badge**
        ];

        foreach ($nodes_to_remove as $node) {
            $wp_admin_bar->remove_node($node);
        }
    }
}
add_action('admin_bar_menu', 'remove_toolbar_items', 999);

add_action('admin_bar_init', function () {
    if (!current_user_can('administrator')) {
        add_action('admin_bar_menu', function($wp_admin_bar) {
            $wp_admin_bar->remove_node('breeze-topbar');
        }, 9999);
    }
});

/**
 * Hide WooCommerce Site Visibility Badge with CSS
 */
function hide_woocommerce_badge_css() {
    echo '<style>
        #wp-admin-bar-woocommerce-site-visibility-badge { display: none !important; }
    </style>';
}
add_action('admin_head', 'hide_woocommerce_badge_css');



// dashboard customization
function remove_all_dashboard_notices_and_widgets_for_non_admins() {
    if (!current_user_can('administrator')) { // Only for non-admins
        // Remove all admin notices
        remove_all_actions('admin_notices');
        remove_all_actions('all_admin_notices');

        // Remove all dashboard widgets
        remove_meta_box('dashboard_activity', 'dashboard', 'normal'); // Activity widget
        remove_meta_box('dashboard_quick_press', 'dashboard', 'side'); // Quick Draft widget
        remove_meta_box('dashboard_right_now', 'dashboard', 'normal'); // At a Glance widget
        remove_meta_box('dashboard_primary', 'dashboard', 'side'); // WordPress News widget
        remove_meta_box('dashboard_site_health', 'dashboard', 'normal'); // Site Health widget
        remove_meta_box('woocommerce_dashboard_status', 'dashboard', 'normal'); // WooCommerce widget (if applicable)
        remove_meta_box('dashboard_recent_comments', 'dashboard', 'normal'); // Recent Comments widget
        remove_meta_box('dashboard_plugins', 'dashboard', 'normal'); // Plugins widget
        remove_meta_box('dashboard_recent_drafts', 'dashboard', 'side'); // Recent Drafts
        remove_meta_box('dashboard_incoming_links', 'dashboard', 'normal'); // Incoming Links
        remove_meta_box('dashboard_secondary', 'dashboard', 'side'); // Other WordPress News
        remove_meta_box('yoast_db_widget', 'dashboard', 'normal'); // Yoast SEO widget (if installed)
        remove_meta_box('rank_math_dashboard_widget', 'dashboard', 'normal'); // RankMath SEO widget (if installed)
    }
}
add_action('wp_dashboard_setup', 'remove_all_dashboard_notices_and_widgets_for_non_admins', 999);

function hide_admin_notices_for_non_admins() {
    if (!current_user_can('administrator')) { // Only for non-admins
        echo '<style>.notice, .updated, .error, .is-dismissible, #dashboard-widgets-wrap { display: none !important; }</style>';
    }
}
add_action('admin_head', 'hide_admin_notices_for_non_admins', 999);



function add_manager_dashboard_to_admin_bar($wp_admin_bar) {
    // Check if user has a club role
    $club_role = get_user_club_role();
    
    if ($club_role) {
        $wp_admin_bar->add_node([
            'id'    => 'manager_dashboard',
            'title' => '<span class="manager-dashboard-icon dashicons dashicons-admin-users"></span> <span class="manager-dashboard-text">Manager Dashboard</span>', 
            'href'  => admin_url('/manager-dashboard'),
            'meta'  => [
                'class' => 'manager-dashboard-admin-bar', // Custom class for styling
                'title' => 'Go to Manager Dashboard' // Tooltip text
            ]
        ]);
    }
}
add_action('admin_bar_menu', 'add_manager_dashboard_to_admin_bar', 100);

/**
 * Ensure Dashicons is loaded for all users (including non-admins on the frontend)
 */
function ensure_dashicons_for_all_users() {
    if (!is_admin()) { // Only load it on the frontend
        wp_enqueue_style('dashicons');
    }
}
add_action('wp_enqueue_scripts', 'ensure_dashicons_for_all_users');

/**
 * Add custom styles to ensure Dashicons render properly
 */
function custom_admin_bar_styles() {
    echo '<style>
        /* Ensure Dashicons are properly styled in admin bar */
        #wpadminbar #wp-admin-bar-manager_dashboard .ab-item .manager-dashboard-icon {
            font-family: "dashicons" !important; /* Ensure the correct font */
            font-size: 16px;
            margin-right: 5px;
            display: inline-block;
            vertical-align: middle;
        }
        #wpadminbar #wp-admin-bar-manager_dashboard .ab-item .manager-dashboard-text {
            vertical-align: middle;
        }
    </style>';
}

// Load styles on both backend and frontend
add_action('admin_head', 'custom_admin_bar_styles');
add_action('wp_head', 'custom_admin_bar_styles'); // Ensure it works on the frontend


function custom_hide_admin_bar() {
    if ( is_admin() ) {
        return; // Always show in admin area
    }

    // Get current user
    $user = wp_get_current_user();

    // If user is admin, do nothing
    if ( in_array('administrator', (array) $user->roles) ) {
        return;
    }

    // Get user's email
    $user_email = $user->user_email;

    // Check if the user exists in wp_club_members table
    global $wpdb;
    $table_name = $wpdb->prefix . 'club_members';
    $member = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_name WHERE user_email = %s", $user_email));

    if ( $member ) {
        // User is in the wp_club_members table, allow admin bar in admin area but not frontend
        if ( !is_admin() ) {
            add_filter('show_admin_bar', '__return_false');
        }
    } else {
        // Hide for everyone else
        add_filter('show_admin_bar', '__return_false');
    }
}

add_action('init', 'custom_hide_admin_bar');





add_action('wp_footer', 'redirect_all_links_to_login_on_thankyou_page');

function redirect_all_links_to_login_on_thankyou_page() {
    // Make sure WooCommerce function exists
    if (!function_exists('is_wc_endpoint_url')) {
        return;
    }

    if (is_wc_endpoint_url('order-received')) : ?>
        <script>
        jQuery(document).ready(function($) {
            // Redirect all WooCommerce links EXCEPT ones with the yoco-button class
            $('.woocommerce a:not(.yoco-button), .woocommerce-order a:not(.yoco-button), .woocommerce-order-overview a:not(.yoco-button), .woocommerce-order-details a:not(.yoco-button), .woocommerce-MyAccount-subscriptions a:not(.yoco-button)').each(function() {
                $(this).attr('href', '/member-dashboard');
                $(this).attr('target', '_self');
            });
        });
        </script>
    <?php endif;
}




// Empty Cart Function - Set "Return to shop" button based on ?club param

add_action('wp_footer', 'custom_redirect_for_empty_cart_button');
add_filter('woocommerce_return_to_shop_redirect', 'custom_override_return_to_shop_redirect');

function custom_get_club_redirect_url() {
    $club_param = isset($_GET['club']) ? trim(urldecode($_GET['club'])) : '';
    $redirect_url = home_url('/'); // Default fallback

    if (!empty($club_param)) {
        global $wpdb;
        $table = $wpdb->prefix . 'clubs';

        // Case-insensitive exact match
        $club = $wpdb->get_row($wpdb->prepare(
            "SELECT club_url FROM $table WHERE LOWER(club_name) = LOWER(%s) LIMIT 1",
            $club_param
        ));

        if ($club && !empty($club->club_url)) {
            $redirect_url = esc_url($club->club_url);
        }
    }

    return $redirect_url;
}

// Apply the override using WooCommerce's filter
function custom_override_return_to_shop_redirect($default_url) {
    // Only on empty cart
    if (is_cart() && WC()->cart->is_empty()) {
        return custom_get_club_redirect_url();
    }
    return $default_url;
}

// JS Fallback for themes that hardcode the URL
function custom_redirect_for_empty_cart_button() {
    // Ensure WooCommerce is active
    if (!function_exists('is_cart') || !function_exists('WC')) {
        return;
    }

    // Exit early if not on the cart page or if the cart is not empty
    if (!is_cart() || WC()->cart->get_cart_contents_count() > 0) {
        return;
    }

    $redirect_url = custom_get_club_redirect_url();
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const returnBtn = document.querySelector('.woocommerce .return-to-shop a.wc-backward');
        if (returnBtn) {
            returnBtn.setAttribute('href', <?php echo json_encode($redirect_url); ?>);
        }
    });
    </script>
    <?php
}




// hiding bubble numbers
add_action('admin_head', 'hide_shop_order_count_css_for_non_admins');
function hide_shop_order_count_css_for_non_admins() {
    if (current_user_can('administrator')) {
        return;
    }
    ?>
    <style>
        /* Hide the WooCommerce Orders count bubble for non-admins */
        a[href="edit.php?post_type=shop_order"] .awaiting-mod {
            display: none !important;
        }
    </style>
    <?php
}


// hiding user roles for non-admin in user-edit.php/profile
function hide_members_roles_section_for_non_admins() {
    if (!current_user_can('administrator')) {
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Hide the Roles heading
            const headings = document.querySelectorAll('h2');
            headings.forEach(h => {
                if (h.textContent.trim() === 'Roles') {
                    h.style.display = 'none';
                }
            });

            // Hide the table row that contains members_user_roles[]
            const inputs = document.querySelectorAll('input[name="members_user_roles[]"]');
            if (inputs.length > 0) {
                const tr = inputs[0].closest('tr');
                if (tr) {
                    tr.style.display = 'none';
                }
            }
        });
        </script>
        <?php
    }
}
add_action('admin_footer', 'hide_members_roles_section_for_non_admins');
