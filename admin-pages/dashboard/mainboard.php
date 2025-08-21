<?php
// File: /includes/manager-dashboard.php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}


// Function to fetch the second club's details for the logged-in user
function fetch_second_club_details() {
    global $wpdb;

    // Ensure the request is coming via AJAX and the user is logged in
    if (!is_user_logged_in() || !check_ajax_referer('switch_role_nonce', 'nonce', false)) {
        wp_send_json_error(['message' => 'Unauthorized access.']);
    }

    // Get the logged-in user's email
    $current_user = wp_get_current_user();
    $user_email = $current_user->user_email;

    // Fetch the second club's details
    $second_club = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT club_id, club_name 
             FROM wp_club_members 
             WHERE user_email = %s AND role != 'Member'
             LIMIT 1 OFFSET 1", // Offset ensures we fetch the second club
            $user_email
        ),
        ARRAY_A
    );

    // If no second club is found, return an error
    if (empty($second_club)) {
        wp_send_json_error(['message' => 'No additional club found for this user.']);
    }

    // Return the second club's details
    wp_send_json_success([
        '2ndclub' => $second_club['club_name'],
        '2ndclubid' => $second_club['club_id'],
    ]);
}

// Hook the function to handle AJAX requests
add_action('wp_ajax_fetch_second_club', 'fetch_second_club_details');


// Function to check if the logged-in user has specific roles (excluding Member)
function get_current_user_role() {
    global $wpdb;

    // Get current logged-in user data
    $current_user = wp_get_current_user();
    if (!$current_user->exists()) {
        return false;
    }

    // Check for 'bca_admin' capability
    if (in_array('bca_admin', (array)$current_user->roles)) {
        return 'BCA Admin';
    }

    // Query the wp_club_members table to get the user's role
    $user_email = $current_user->user_email;
    $role = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT role FROM wp_club_members WHERE user_email = %s",
            $user_email
        )
    );

    return $role;
}


// Function to get the club name for the logged-in user or from URL parameters
function get_club_name_by_user_email($user_email) {
    global $wpdb;

    // Check if 'club' parameter exists in the URL
    if (isset($_GET['club']) && !empty($_GET['club'])) {
        // Sanitize and decode the club name from the URL
        $club_name = urldecode(sanitize_text_field($_GET['club']));

        // Validate the club name from the database
        $valid_club_name = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT club_name FROM {$wpdb->prefix}clubs WHERE club_name = %s",
                $club_name
            )
        );

        if ($valid_club_name) {
            return $valid_club_name;
        }
    }

    // Fallback: Fetch the club name based on the provided user email
    return $wpdb->get_var(
        $wpdb->prepare(
            "SELECT club_name FROM {$wpdb->prefix}club_members WHERE user_email = %s",
            $user_email
        )
    );
}

function is_club_valuation_enabled() {
    global $wpdb;
    
    // Get the logged-in user's club name
    $current_user = wp_get_current_user();
    $club_name = get_club_name_by_user_email($current_user->user_email);
    
    if (!$club_name) {
        return false;
    }

    // Query the `wp_clubs` table to check for a non-empty `gform_id`
    $gform_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT gform_id FROM {$wpdb->prefix}clubs WHERE club_name = %s",
            $club_name
        )
    );

    // Return true if `gform_id` exists and is not empty
    return !empty($gform_id);
}

function get_latest_gform_profile_photo_url_for_user_manager($user_id = null) {
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

// Render the Manager Dashboard HTML
function render_manager_dashboard_html() {
// Get the user's role
$role = get_current_user_role();

// Restrict access if the role is not allowed
if (!$role || ($role === 'Member' && !in_array('bca_admin', (array)wp_get_current_user()->roles))) {
    return '<p>You do not have permission to access this page.</p>';
}

// Get current logged-in user data
$current_user = wp_get_current_user();

// Get the user avatar
// Get the user avatar and name
$profile_photo = get_latest_gform_profile_photo_url_for_user($current_user->ID);

// Fallback to avatar if no uploaded photo found
if (empty($profile_photo)) {
    $profile_photo = get_avatar_url($current_user->ID, ['size' => 100]);
}
$user_name = $current_user->display_name; // User's display name

// Determine the club name
if (in_array('bca_admin', (array)$current_user->roles)) {
    $club_name = 'BMW Clubs Africa';
} else {
    $club_name = get_club_name_by_user_email($current_user->user_email);
}

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
                        <img src="<?php echo esc_url($profile_photo); ?>" alt="Profile Image" style="border-radius: 50%; width: 60px; height: 60px; margin-bottom: 10px;">
                    </div>
                    <div class="infouser">
                        <p style="margin: 0; font-weight: bold;"><?php echo esc_html($user_name); ?></p>
                        <p style="margin: 5px 0; color: #777; font-size: 14px;"><?php echo esc_html($club_name); ?></p>
                    </div>
                </div>
                <div style="margin-top: 10px;">
                <button onclick="switchRole()" style="padding:10px; margin-right: 5px; background-color: #0767b1; color: white; border: none;  cursor: pointer;">
                    <i class="fas fa-exchange-alt" style="margin-right: 5px;"></i> Switch Role
                </button>
                <?php
$logout_url = wp_logout_url(home_url()); // Redirect to home after logout
?>
<button 
    class="custom-logout" 
    data-logout-url="<?php echo esc_url($logout_url); ?>" 
    style="padding:10px; background-color: #0767b1; color: white; border: none; cursor: pointer;">
    <i class="fas fa-sign-out-alt" style="margin-right: 5px;"></i> Logout
</button>

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




                </div>
            </div>

            <!-- Navigation Menu -->
            <ul class="dashboard-menu" style="list-style: none; padding: 0; margin: 0;">
                <!-- BCA Admin -->
            <?php if ($role === 'BCA Admin') : ?>
                    <li style="margin: 10px 0;">
                        <a href="?section=committees" class="<?php echo ($current_section === 'committees') ? 'active' : ''; ?>" style="text-decoration: none; color: #000;">
                            <i class="fas fa-user-friends" style="margin-right: 8px;"></i> Committee
                        </a>
                    </li>
                <?php endif; ?>

                <?php if ($role === 'Club Manager') : ?>
                    
                    <li style="margin: 10px 0;">
                        <a href="?section=users" class="<?php echo ($current_section === 'users') ? 'active' : ''; ?>" style="text-decoration: none; color: #000;">
                            <i class="fas fa-users" style="margin-right: 8px;"></i> Users
                        </a>
                    </li>
                    
                <?php endif; ?>
                <?php if ($role === 'Club Manager' || $role === 'Treasurer' || $role === 'Store Manager') : ?>
                    <li style="margin: 10px 0;">
                        <a href="?section=orders" class="<?php echo ($current_section === 'orders') ? 'active' : ''; ?>" style="text-decoration: none; color: #000;">
                            <i class="fas fa-shopping-cart" style="margin-right: 8px;"></i> Orders
                        </a>
                    </li>
                <?php endif; ?>
                <?php if ($role === 'Club Manager' || $role === 'Media/Social' || $role === 'Store Manager') : ?>
                    <li style="margin: 10px 0;">
                        <a href="?section=products" class="<?php echo ($current_section === 'products') ? 'active' : ''; ?>" style="text-decoration: none; color: #000;">
                            <i class="fas fa-box-open" style="margin-right: 8px;"></i> Products
                        </a>
                    </li>
                <?php endif; ?>
                <?php if ($role === 'Club Manager' || $role === 'Media/Social') : ?>
                    <li style="margin: 10px 0;">
                        <a href="?section=posts" class="<?php echo ($current_section === 'posts') ? 'active' : ''; ?>" style="text-decoration: none; color: #000;">
                            <i class="fas fa-edit" style="margin-right: 8px;"></i> Posts
                        </a>
                    </li>
                    <li style="margin: 10px 0;">
                        <a href="?section=events" class="<?php echo ($current_section === 'events') ? 'active' : ''; ?>" style="text-decoration: none; color: #000;">
                            <i class="fas fa-calendar-alt" style="margin-right: 8px;"></i> Events
                        </a>
                    </li>
                <?php endif; ?>


                <?php if ($role === 'Club Manager' || $role === 'Media/Social' || $role === 'Store Manager') : ?>
                    <li style="margin: 10px 0; cursor: pointer;" id="manage-pages">
                        <a href="#"id="pages-link" style="text-decoration: none; color: #000;">
                            <i class="fas fa-file-alt" style="margin-right: 8px;"></i> Pages
                        </a>
                    </li>

                    <script>
                        document.addEventListener('DOMContentLoaded', function () {
                            // Handle "Pages" link redirect
                            document.getElementById('manage-pages')?.addEventListener('click', function (event) {
                                event.preventDefault();
                                window.location.href = '<?php echo esc_url(admin_url("edit.php?post_type=page")); ?>';
                            });

                            const currentURL = new URL(window.location.href);
                            const currentSection = currentURL.searchParams.get('section');

                            // Active state logic
                            const navLinks = document.querySelectorAll('.dashboard-menu a');

                            navLinks.forEach(link => {
                                const isPagesLink = link.id === 'pages-link';

                                if (isPagesLink) {
                                    link.classList.remove('active'); // Actively remove
                                    return;
                                }

                                const linkSection = new URL(link.href, window.location.origin).searchParams.get('section');

                                if (linkSection === currentSection) {
                                    link.classList.add('active');
                                } else {
                                    link.classList.remove('active');
                                }
                            });

                            // 🔒 Extra safeguard to remove .active from Pages in case other scripts add it
                            setTimeout(() => {
                                const pagesLink = document.getElementById('pages-link');
                                if (pagesLink) pagesLink.classList.remove('active');
                            }, 0);
                        });
                    </script>
                    
                <?php endif; ?>


                
                <?php if ($role === 'Club Manager') : ?>
                    <li style="margin: 10px 0;">
                        <a href="?section=committee" class="<?php echo ($current_section === 'committee') ? 'active' : ''; ?>" style="text-decoration: none; color: #000;">
                            <i class="fas fa-user-friends" style="margin-right: 8px;"></i> Committee
                        </a>
                    </li>
                    <?php if ($role === 'Club Manager' && is_club_valuation_enabled()) : ?>
    <li style="margin: 10px 0;">
        <a href="?section=valuation" class="<?php echo ($current_section === 'valuation') ? 'active' : ''; ?>" style="text-decoration: none; color: #000;">
            <i class="fas fa-balance-scale" style="margin-right: 8px;"></i> Valuations
        </a>
    </li>
<?php endif; ?>

                    <li style="margin: 10px 0;">
                        <a href="?section=notifications" class="<?php echo ($current_section === 'notifications') ? 'active' : ''; ?>" style="text-decoration: none; color: #000;">
                            <i class="fas fa-bell" style="margin-right: 8px;"></i> Notifications
                        </a>
                    </li>

                    
                <?php endif; ?>

                <!-- click up form -->
                <?php if ($role === 'Club Manager') : ?>
                    <li style="margin: 10px 0;" class="<?php echo (isset($_GET['section']) && $_GET['section'] === 'enquiry') ? 'active' : ''; ?>">
                        <a id="open-enquiry-form"
                        href="https://forms.clickup.com/9015530995/f/8cnw5fk-8075/V75TNMUOKQSHIB21IX"
                        style="text-decoration: none; color: #000;">
                            <i class="fas fa-question-circle" style="margin-right: 8px;"></i> Support
                        </a>
                    </li>
                    <script>
                        document.addEventListener('DOMContentLoaded', function () {
                            const enquiryLink = document.getElementById('open-enquiry-form');
                            if (enquiryLink) {
                                enquiryLink.addEventListener('click', function (e) {
                                    e.preventDefault();
                                    e.stopImmediatePropagation();
                                    window.open(this.href, '_blank');
                                    return false;
                                }, true);
                            }
                        });
                    </script>
                <?php endif; ?>
                
            </ul>
        </div>

        <!-- Main Content Area -->
        <div class="dashboard-content">
            <?php
            switch ($current_section) {
                case 'users':
                    require_once CLUB_MANAGER_PLUGIN_DIR . 'admin-pages/dashboard/manager-users.php';
                    break;
                case 'orders':
                    require_once CLUB_MANAGER_PLUGIN_DIR . 'admin-pages/dashboard/manager-orders.php';
                    break;
                case 'posts':
                    require_once CLUB_MANAGER_PLUGIN_DIR . 'admin-pages/dashboard/manager-newpost.php';
                    break;
                case 'events':
                    require_once CLUB_MANAGER_PLUGIN_DIR . 'admin-pages/dashboard/manager-events.php';
                    break;
                case 'products':
                    require_once CLUB_MANAGER_PLUGIN_DIR . 'admin-pages/dashboard/manager-products.php';
                    break;
                case 'committee':
                    require_once CLUB_MANAGER_PLUGIN_DIR . 'admin-pages/dashboard/manager-committee.php';
                    break;
                case 'valuation':
                    require_once CLUB_MANAGER_PLUGIN_DIR . 'admin-pages/dashboard/manager-valuation.php';
                    break;
                case 'notifications':
                    require_once CLUB_MANAGER_PLUGIN_DIR . 'admin-pages/dashboard/manager-notifications.php';
                    break;
                case 'committees':
                     require_once CLUB_MANAGER_PLUGIN_DIR . 'admin-pages/dashboard/manager-bca.php';
                     break;    
                default:
                    echo '<h2>Welcome to Manager Dashboard</h2>';
                    echo '<p class="default-p">Select an option from the menu to get started.</p>';
            }
            ?>
        </div>
    </div>

    <script>
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

            // Persist `?section=` in the URL and preserve existing parameters
            document.querySelectorAll('a').forEach(anchor => {
                anchor.addEventListener('click', function (event) {
                    const targetURL = new URL(this.href);
                    const existingSection = currentURL.searchParams.get('section');
                    if (!targetURL.searchParams.has('section') && existingSection) {
                        targetURL.searchParams.set('section', existingSection);
                    }
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
    // Select all navigation links inside the list items
    const navLinks = document.querySelectorAll('.dashboard-menu a');

    navLinks.forEach(link => {
        link.addEventListener('click', function (event) {
            event.preventDefault();

            // Get the current URL and the target URL
            const currentURL = window.location.href;
            const targetURL = new URL(this.href);

            // Remove the '/page/X/' part from the current URL path
            const cleanedPath = currentURL.replace(/\/page\/\d+\//, '');

            // Update the target URL's pathname with the cleaned path
            targetURL.pathname = new URL(cleanedPath).pathname;

            // Redirect to the updated URL
            window.location.href = targetURL.toString();
        });
    });
});

</script>


<script>
function switchRole() {
    window.location.href = '/member-dashboard';
}
</script>

    <?php
    return ob_get_clean();
}

// Add the dashboard shortcode
add_shortcode('manager_dashboard', 'render_manager_dashboard_html');
?>
