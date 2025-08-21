<?php
function custom_login_redirect( $redirect_to, $request, $user ) {
    global $wpdb;

    // Check if user is logged in and user object exists
    if ( isset( $user->user_email ) && isset( $user->roles ) && is_array( $user->roles ) ) {
        
        // Prevent redirection for administrators
        if ( in_array( 'administrator', $user->roles ) ) {
            return $redirect_to;
        }

        // Get user email
        $email = $user->user_email;

        // Query the wp_club_members table to check if the email exists
        $query = $wpdb->prepare("SELECT * FROM wp_club_members WHERE user_email = %s", $email);
        $club_member = $wpdb->get_row($query);

        // If email exists in the club members table, redirect to /manager-dashboard
        if ( $club_member ) {
            return home_url( '/manager-dashboard' );
        }

        // If user role is subscriber, redirect to /member-dashboard
        if ( in_array( 'subscriber', $user->roles ) ) {
            return home_url( '/member-dashboard' );
        }

        // If none of the above conditions are met, redirect to home page
        return home_url();
    }

    // Default WordPress login behavior
    return $redirect_to;
}

// Hook into WordPress login redirect
add_filter( 'login_redirect', 'custom_login_redirect', 10, 3 );



// Logout Function
function custom_logout_redirect() {
    global $wpdb;

    if (!function_exists('write_log')) {
        function write_log($log) {
            if (WP_DEBUG === true) {
                error_log(print_r($log, true));
            }
        }
    }

    // Get current user before logout
    $user = wp_get_current_user();
    $email = $user->user_email;

    write_log("🔄 Logout Process Started for: " . $email);

    // **Ensure Proper Logout**
    wp_logout(); // Logs out the user
    wp_clear_auth_cookie(); // Clears authentication cookies
    wp_destroy_current_session(); // Ends the session
    session_destroy(); // Clears PHP session (fixes some lingering login issues)

    // Remove any leftover authentication cookies
    setcookie("wordpress_logged_in", "", time() - 3600, "/");
    setcookie("wordpress_sec", "", time() - 3600, "/");

    if (!empty($email)) {
        // Check if user exists in wp_club_members
        $query = $wpdb->prepare("SELECT club_id FROM wp_club_members WHERE user_email = %s", $email);
        $club_member = $wpdb->get_row($query);

        write_log("🔍 Checking wp_club_members: " . $query);
        write_log("📜 Club Member Data: " . print_r($club_member, true));

        if ($club_member && !empty($club_member->club_id)) {
            $club_id = $club_member->club_id;
            write_log("✅ User found in wp_club_members. Club ID: " . $club_id);

            // Get the club URL from wp_clubs
            $query = $wpdb->prepare("SELECT club_url FROM wp_clubs WHERE club_id = %d", $club_id);
            $club_info = $wpdb->get_row($query);

            write_log("🔍 Checking wp_clubs for Club ID: " . $query);
            write_log("📜 Club Info Data: " . print_r($club_info, true));

            if ($club_info && !empty($club_info->club_url)) {
                write_log("🚀 Redirecting to Club URL: " . $club_info->club_url);
                wp_redirect($club_info->club_url);
                exit();
            } else {
                write_log("⚠️ Club URL Not Found for Club ID: " . $club_id);
            }
        } else {
            write_log("❌ User Not Found in wp_club_members.");
        }
    } else {
        write_log("⚠️ User Email Not Retrieved.");
    }

    // Default logout redirect if no club is found
    write_log("🔁 Redirecting to Home Page");
    wp_redirect(home_url());
    exit();
}

// Modify Logout URL to Use Custom Logout Handler
function modify_logout_url($logout_url, $redirect) {
    return wp_nonce_url(home_url('/?custom_logout=1'), 'custom_logout_action');
}
add_filter('logout_url', 'modify_logout_url', 10, 2);

// Handle Custom Logout Request
function handle_custom_logout() {
    if (isset($_GET['custom_logout']) && wp_verify_nonce($_GET['_wpnonce'], 'custom_logout_action')) {
        custom_logout_redirect();
    }
}
add_action('init', 'handle_custom_logout');



// dashboard logout redirect

function dashboard_logout_redirect_fix() {
    if (!is_user_logged_in()) {
        $restricted_pages = array('manager-dashboard', 'member-dashboard');
        $current_url = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/'); // Get path only, ignoring query params

        foreach ($restricted_pages as $restricted) {
            if (strpos($current_url, $restricted) === 0) { // Check if the URL starts with restricted pages
                wp_redirect(home_url('/login')); // Redirect to login page
                exit;
            }
        }
    }
}
add_action('template_redirect', 'dashboard_logout_redirect_fix');



// Immediately hide WooCommerce Memberships restriction notice with CSS
function hide_wc_membership_notice_css() {
    echo '<style>
        .admin-restricted-content-notice {
            display: none !important;
        }
    </style>';
}
add_action('wp_head', 'hide_wc_membership_notice_css', 1);


function redirect_my_account_to_member_dashboard() {
    if (is_page('my-account') || strpos($_SERVER['REQUEST_URI'], '/my-account/') !== false) {
        wp_redirect(home_url('/member-dashboard/'));
        exit;
    }
}
add_action('template_redirect', 'redirect_my_account_to_member_dashboard');
