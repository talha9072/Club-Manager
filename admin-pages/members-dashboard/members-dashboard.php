<?php
// File: /includes/members-dashboard.php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Function to check if the logged-in user is an Active Subscriber
if (!function_exists('is_active_subscriber')) {
    function is_active_subscriber() {
        $current_user = wp_get_current_user();

        if (!$current_user->exists()) {
            return false;
        }

        // Check if user has subscription ID or is a paying customer
        $has_subscription = get_user_meta($current_user->ID, '_wcs_subscription_ids_cache', true);
        $is_paying_customer = get_user_meta($current_user->ID, 'paying_customer', true);

        // Validate the subscription or paying status
        return !empty($has_subscription) || $is_paying_customer == 1;
    }
}

function get_user_active_subscriptions() {
    global $wpdb;

    // Ensure the WordPress core functions are loaded
    if (!function_exists('is_user_logged_in')) {
        require_once ABSPATH . WPINC . '/pluggable.php';
    }

    // Check if the user is logged in
    if (!is_user_logged_in()) {
        return [];
    }

    // Get the logged-in user ID
    $current_user = wp_get_current_user();
    $current_user_id = $current_user->ID;

    if (!$current_user_id) {
        return [];
    }

    // SQL query to get active user subscriptions with _select_club_name meta
    $sql = $wpdb->prepare("
        SELECT 
            sub.ID AS subscription_id,
            sub.post_status AS subscription_status,
            prod.ID AS product_id,
            prod.post_title AS product_name,
            pm_club.meta_value AS club_name
        FROM wp_posts sub
        INNER JOIN wp_postmeta pm_customer 
            ON sub.ID = pm_customer.post_id 
            AND pm_customer.meta_key = '_customer_user'
        INNER JOIN wp_woocommerce_order_items oi 
            ON sub.ID = oi.order_id
        INNER JOIN wp_woocommerce_order_itemmeta pm_product 
            ON oi.order_item_id = pm_product.order_item_id 
            AND pm_product.meta_key = '_product_id'
        INNER JOIN wp_posts prod 
            ON pm_product.meta_value = prod.ID
        LEFT JOIN wp_postmeta pm_club 
            ON prod.ID = pm_club.post_id 
            AND pm_club.meta_key = '_select_club_name'
        WHERE pm_customer.meta_value = %d
        AND sub.post_type = 'shop_subscription'
        AND sub.post_status = 'wc-active'
    ", $current_user_id);

    // Execute the query
    $results = $wpdb->get_results($sql, ARRAY_A);

    // Prepare and return the simplified result set
    $subscriptions = [];
    if (!empty($results)) {
        foreach ($results as $subscription) {
            $subscriptions[] = [
                'product_name'    => $subscription['product_name'],
                'subscription_id' => $subscription['subscription_id'],
                'product_id'      => $subscription['product_id'],
                'club_name'       => $subscription['club_name'] ?? 'N/A',
            ];
        }
    }

    return $subscriptions;
}


// Function to check if the user can switch roles
function can_user_switch_roles() {
    global $wpdb;
    $current_user = wp_get_current_user();

    if (!$current_user->exists()) {
        return false;
    }

    // Fetch the user's role from the wp_club_members table
    $user_email = $current_user->user_email;
    $user_role = $wpdb->get_var($wpdb->prepare(
        "SELECT role FROM wp_club_members WHERE user_email = %s LIMIT 1",
        $user_email
    ));

    // Check if the user has the bca_admin role from wp_usermeta
    $user_capabilities = get_user_meta($current_user->ID, 'wp_capabilities', true);
    $has_bca_admin = isset($user_capabilities['bca_admin']) && $user_capabilities['bca_admin'] === true;

    // Define allowed roles
    $allowed_roles = ['Club Manager', 'Treasurer', 'Store Manager', 'Media/Social'];

    // Return true if the user has one of the allowed roles or the bca_admin role
    return in_array($user_role, $allowed_roles) || $has_bca_admin;
}

function get_latest_gform_profile_photo_url_for_user($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }

    if (!$user_id) {
        return ''; // Not logged in
    }

    global $wpdb;

    // List of forms and their corresponding profile photo field IDs (in order of priority)
    $form_checks = [
        ['form_id' => 2,  'field_id' => 120],
        ['form_id' => 37, 'field_id' => 88],
        ['form_id' => 22, 'field_id' => 120],
    ];

    foreach ($form_checks as $check) {
        $entry_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}gf_entry
                 WHERE form_id = %d AND created_by = %d
                 ORDER BY date_created DESC LIMIT 1",
                $check['form_id'],
                $user_id
            )
        );

        if (!$entry_id) {
            continue;
        }

        $photo_url = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT meta_value FROM {$wpdb->prefix}gf_entry_meta
                 WHERE entry_id = %d AND meta_key = %s LIMIT 1",
                $entry_id,
                (string) $check['field_id']
            )
        );

        if (!empty($photo_url)) {
            // Convert to full URL if needed
            if (!filter_var($photo_url, FILTER_VALIDATE_URL)) {
                $upload_dir = wp_upload_dir();
                $photo_url = trailingslashit($upload_dir['baseurl']) . ltrim($photo_url, '/');
            }

            return esc_url($photo_url);
        }
    }

    return ''; // No photo found in any form
}


// Render the Members Dashboard HTML
function render_members_dashboard_html() {
    // Restrict access to Active Subscribers only
    if (!is_user_logged_in()) {
        return '<p>You do not have permission to access this page. Please <a href="/login">log in</a>.</p>';
    }
    if (!is_active_subscriber()) {
        return '<p>You do not have permission to access this page.</p>';
    }

    // Get current logged-in user data
    $current_user = wp_get_current_user();

    // Get the user avatar and name
    $profile_photo = get_latest_gform_profile_photo_url_for_user($current_user->ID);

// Fallback to avatar if no uploaded photo found
if (empty($profile_photo)) {
    $profile_photo = get_avatar_url($current_user->ID, ['size' => 100]);
}

    $user_name = $current_user->display_name;

    // Determine which section to load based on the query parameter
    $current_section = isset($_GET['section']) ? sanitize_text_field($_GET['section']) : 'default';

    ob_start();
    ?>
    <div class="dashboard-wrapper" style="display: flex; font-family: Arial, sans-serif;">
        <!-- Burger Menu -->
        

        <!-- Left Sidebar -->
        <div class="dashboard-sidebar">
            <!-- User Profile Section -->
            <div class="mainprofile">
                <h2 style="text-align: left; margin-bottom: 20px;font-weight:700 !important;">Dashboard</h2>
                <div class="profile-section" style=" margin-bottom: 20px; ">
                    <div class="pp">
                        <img src="<?php echo esc_url($profile_photo); ?>" alt="Profile Image" style="border-radius: 50%; width: 60px; height: 60px;margin-bottom: 0px;">
                    </div>
                    <div class="infouser">
                        <p style="margin: 0; font-weight: bold;"><?php echo esc_html($user_name); ?></p>
                        <p style="margin: 0; font-weight: bold;">Member</p>
                        
                    </div>
                </div>
                <div style="margin-top: 10px;">
                    <!-- Subscription Dropdown -->

                    <?php 
                        $subscriptions = get_user_active_subscriptions(); 
                        if (!empty($subscriptions)) : 
                            // Determine the selected subscription from the URL or default to the first one
                            $selected_subscription_id = $_GET['subscription_id'] ?? $subscriptions[0]['subscription_id'];
                            $selected_product_id = $_GET['product_id'] ?? $subscriptions[0]['product_id'];
                            ?>
             <select class="unique-subscription-dropdown" id="subscriptionDropdown" style="padding:10px;">
    <?php foreach ($subscriptions as $subscription) : ?>
        <option value="<?php echo esc_attr($subscription['subscription_id'] . '|' . $subscription['product_id'] . '|' . htmlspecialchars($subscription['club_name'], ENT_QUOTES, 'UTF-8')); ?>"
            <?php echo ($subscription['subscription_id'] == $selected_subscription_id) ? 'selected' : ''; ?>>
            <?php echo esc_html($subscription['product_name']); ?>
        </option>
    <?php endforeach; ?>
</select>


                        <?php else : ?>
                            <select class="unique-subscription-dropdown" id="subscriptionDropdown" style="padding:10px; border-radius:3px; margin-left:10px;">
                                <option value="">No Active Subscriptions</option>
                            </select>
                        <?php endif; ?>
                        <?php $logout_url = wp_logout_url(home_url()); ?>
                        <button 
                            class="custom-logout" 
                            data-logout-url="<?php echo esc_url($logout_url); ?>" 
                            style="padding:10px 15px; background-color: #0767b1; color: white; border: none; cursor: pointer;">
                            <i class="fas fa-sign-out-alt" style="margin-right: 5px;"></i> Logout
                        </button>


                    <?php if (can_user_switch_roles()) : ?>
                       <button onclick="switchRole()" style="padding:10px 15px; background-color: #0767b1; color: white; border: none;  cursor: pointer;">
                         <i class="fas fa-exchange-alt" style="margin-right: 5px;"></i> Switch Roles
                       </button>
                       

                    <?php endif; ?>
                </div>
            </div>

            <!-- Navigation Menu -->
            <ul class="dashboard-menu" style="list-style: none; padding: 0; margin: 0;">
                <li style="margin: 10px 0;">
                    <a href="?section=profile" class="<?php echo ($current_section === 'profile') ? 'active' : ''; ?>" style="text-decoration: none; color: #000;">
                        <i class="fas fa-user" style="margin-right: 8px;"></i> Profile
                    </a>
                </li>
                <li style="margin: 10px 0;">
                    <a href="?section=orders" class="<?php echo ($current_section === 'orders') ? 'active' : ''; ?>" style="text-decoration: none; color: #000;">
                        <i class="fas fa-shopping-cart" style="margin-right: 8px;"></i> Orders
                    </a>
                </li>
                <li style="margin: 10px 0;">
                    <a href="?section=membership" class="<?php echo ($current_section === 'membership') ? 'active' : ''; ?>" style="text-decoration: none; color: #000;">
                        <i class="fas fa-id-card" style="margin-right: 8px;"></i> Membership
                    </a>
                </li>
                <li style="margin: 10px 0;">
                    <a href="?section=valuation" class="<?php echo ($current_section === 'valuation') ? 'active' : ''; ?>" style="text-decoration: none; color: #000;">
                        <i class="fas fa-balance-scale" style="margin-right: 8px;"></i> Valuations
                    </a>
                </li>
                <li style="margin: 10px 0;">
                    <a href="?section=rides" class="<?php echo ($current_section === 'rides') ? 'active' : ''; ?>" style="text-decoration: none; color: #000;">
                        <i class="fas fa-bicycle" style="margin-right: 8px;"></i> Rides
                    </a>
                </li>
                <li style="margin: 10px 0;">
                    <a href="?section=ecard" class="<?php echo ($current_section === 'ecard') ? 'active' : ''; ?>" style="text-decoration: none; color: #000;">
                        <i class="fas fa-address-card" style="margin-right: 8px;"></i> eCard
                    </a>
                </li>
            </ul>
        </div>

        <!-- Main Content -->
        <div class="dashboard-content">
            <?php
            switch ($current_section) {
                case 'profile':
                    require_once CLUB_MANAGER_PLUGIN_DIR . 'admin-pages/members-dashboard/members-user.php';
                    break;
                case 'orders':
                    require_once CLUB_MANAGER_PLUGIN_DIR . 'admin-pages/members-dashboard/members-orders.php';
                    break;
                case 'membership':
                    require_once CLUB_MANAGER_PLUGIN_DIR . 'admin-pages/members-dashboard/members-membership.php';
                    break;
                case 'valuation':
                    require_once CLUB_MANAGER_PLUGIN_DIR . 'admin-pages/members-dashboard/members-valuation.php';
                    break;
                case 'rides':
                    require_once CLUB_MANAGER_PLUGIN_DIR . 'admin-pages/members-dashboard/members-rides.php';
                    break;
                case 'ecard':
                    require_once CLUB_MANAGER_PLUGIN_DIR . 'admin-pages/members-dashboard/members-ecard.php';
                    break;
                default:
                    echo '<h2 class"manager-h2">Welcome to Members Dashboard</h2>';
                    echo '<p class="default-p">Select an option from the menu to explore your dashboard.</p>';
            }
            ?>
        </div>
    </div>


    <script>
document.addEventListener("click", function (event) {
    const logoutBtn = event.target.closest(".custom-logout");

    if (logoutBtn) {
        console.log("✅ Logout button clicked");

        event.preventDefault();

        const logoutUrl = logoutBtn.getAttribute("data-logout-url");
        if (logoutUrl) {
            console.log("➡️ Redirecting to:", logoutUrl);
            window.location.href = logoutUrl;
        } else {
            console.warn("⚠️ Logout URL not found on the button.");
        }
    }
});

console.log("🧠 Global logout click listener attached.");
</script>

    <script>
        

        document.addEventListener("DOMContentLoaded", function () {
    document.querySelector(".custom-logout")?.addEventListener("click", function (event) {
        event.preventDefault();
        const logoutUrl = this.getAttribute("data-logout-url");
        if (logoutUrl) {
            window.location.href = logoutUrl;
        }
    });
});


        document.addEventListener('DOMContentLoaded', function () {
            const links = document.querySelectorAll('.dashboard-menu a');
            const currentURL = new URL(window.location.href);
            const section = currentURL.searchParams.get('section') || 'default';

            // Highlight the active section
            links.forEach(link => {
                const linkSection = new URL(link.href).searchParams.get('section');
                if (linkSection === section) {
                    link.classList.add('active');
                } else {
                    link.classList.remove('active');
                }
            });

            // Preserve `?section=` in the URL and navigate
            links.forEach(anchor => {
                anchor.addEventListener('click', function (event) {
                    const targetURL = new URL(this.href);
                    event.preventDefault();
                    window.location.href = targetURL.toString();
                });
            });
        });

        function toggleSidebar() {
            const sidebar = document.querySelector('.dashboard-sidebar');
            sidebar.classList.toggle('open');
        }
    </script>
    <script>
        function switchRole() {
      window.location.href = '/manager-dashboard';
     }

    </script>

<script>
(function () {
  let sidebar, originalParent, nextSibling, megaMenu, sidebarClone, insertedLi, headingLi, styleTag;

  // Prevent flicker before anything loads
  if (window.innerWidth < 1100) {
    styleTag = document.createElement('style');
    styleTag.textContent = '.dashboard-sidebar { display: none !important; }';
    document.head.appendChild(styleTag);
  }

  document.addEventListener('DOMContentLoaded', function () {
    sidebar = document.querySelector('.dashboard-sidebar');
    originalParent = sidebar?.parentElement;
    nextSibling = sidebar?.nextElementSibling;
    megaMenu = document.querySelector('.mega-menu');

    if (!sidebar || !originalParent || !megaMenu) {
      console.warn('❌ Sidebar or Mega Menu not found.');
      return;
    }

    function moveSidebarToMegaMenu() {
      if (insertedLi || !sidebar) return;

      // Clone sidebar
      sidebarClone = sidebar.cloneNode(true);
      insertedLi = document.createElement('li');
      insertedLi.className = 'mega-menu-item mega-menu-item-type-custom sidebar-wrapper';
      insertedLi.appendChild(sidebarClone);

      // Site Menu Heading
      headingLi = document.createElement('li');
      headingLi.className = 'mega-menu-item site-menu-heading';
      headingLi.style.fontWeight = 'bold';
      headingLi.style.padding = '10px 15px';
      headingLi.style.fontSize = '14px';
      headingLi.style.textTransform = 'uppercase';
      headingLi.style.color = '#000';
      headingLi.textContent = 'Site Menu';

      // Insert sidebar and heading at the top
      megaMenu.insertBefore(headingLi, megaMenu.firstElementChild);
      megaMenu.insertBefore(insertedLi, headingLi);

      // Remove flicker-hiding CSS
      if (styleTag && styleTag.parentNode) {
        styleTag.remove();
        styleTag = null;
      }

      sidebar.style.display = 'none';
      console.log('📥 Sidebar moved and Site Menu added');
    }

    function restoreSidebar() {
      if (!insertedLi || !sidebar || !originalParent) return;

      insertedLi.remove();
      insertedLi = null;

      if (headingLi) {
        headingLi.remove();
        headingLi = null;
      }

      sidebar.style.display = '';
      originalParent.insertBefore(sidebar, nextSibling || null);

      console.log('📤 Sidebar restored to original position');
    }

    function checkWidthAndMove() {
      if (window.innerWidth < 1100) {
        moveSidebarToMegaMenu();
      } else {
        restoreSidebar();
      }
    }

    window.addEventListener('resize', checkWidthAndMove);
    checkWidthAndMove();
  });
})();
</script>




<script>
    document.addEventListener('DOMContentLoaded', function () {
        const dropdown = document.getElementById('subscriptionDropdown');
        const links = document.querySelectorAll('.dashboard-menu a');

        // Get current URL parameters for subscription_id, product_id, and club
        const urlParams = new URLSearchParams(window.location.search);
        let subscriptionId = urlParams.get('subscription_id') || dropdown.value.split('|')[0];
        let productId = urlParams.get('product_id') || dropdown.value.split('|')[1];
        let clubName = urlParams.get('club') || dropdown.value.split('|')[2];

        // Check if URL parameters are missing on initial load
        if (!urlParams.has('subscription_id') || !urlParams.has('product_id') || !urlParams.has('club')) {
            const initialURL = new URL(window.location.href);
            initialURL.searchParams.set('subscription_id', subscriptionId);
            initialURL.searchParams.set('product_id', productId);
            initialURL.searchParams.set('club', clubName);
            window.location.replace(initialURL.href); // Immediately redirect without history entry
            return;
        }

        // Update the URL parameters when dropdown changes
        dropdown.addEventListener('change', function () {
            const selectedValue = this.value.split('|');
            const newSubscriptionId = selectedValue[0];
            const newProductId = selectedValue[1];
            const newClubName = selectedValue[2];

            // Update URL with selected subscription details
            const currentURL = new URL(window.location.href);
            currentURL.searchParams.set('subscription_id', newSubscriptionId);
            currentURL.searchParams.set('product_id', newProductId);
            currentURL.searchParams.set('club', newClubName);
            window.location.href = currentURL.href;
        });

        // Add subscription_id, product_id, and club to navigation links
        links.forEach(link => {
            link.addEventListener('click', function (event) {
                event.preventDefault();
                const targetURL = new URL(this.href);
                targetURL.searchParams.set('subscription_id', subscriptionId);
                targetURL.searchParams.set('product_id', productId);
                targetURL.searchParams.set('club', clubName);
                window.location.href = targetURL.href;
            });
        });
    });
</script>



    <?php
    return ob_get_clean();
}

// Add Members Dashboard Shortcode
add_shortcode('members_dashboard', 'render_members_dashboard_html');