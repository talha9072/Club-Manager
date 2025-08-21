<?php


// ✅ **Hides Default WordPress Logo**
function custom_login_logo() {
    echo '
    <style type="text/css">
        .login h1 a {
            display: none !important;
        }
    </style>';
}
add_action('login_enqueue_scripts', 'custom_login_logo');


// ✅ **Custom Styling for Lost Password & Email Confirmation Pages**
function custom_lost_password_background() {
    if ( (isset($_GET['action']) && $_GET['action'] === 'lostpassword') || 
     (isset($_GET['checkemail']) && $_GET['checkemail'] === 'confirm') || 
     (basename($_SERVER['PHP_SELF']) === 'wp-login.php') ) {
        echo '
        <style type="text/css">
            body {
                background-image: linear-gradient(180deg, rgba(38, 38, 38, 0.95) 0%, rgba(38, 38, 38, 0.95) 100%), 
                                  url(/wp-content/uploads/2024/12/BCA-404.jpg) !important;
                background-size: cover !important;
                background-position: center !important;
                background-repeat: no-repeat !important;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
            }
            .wp-login-log-in, #backtoblog a,#nav a {
                color: white !important;
                font-size: 16px !important;
            }
            .notice p {
                font-size: 16px !important;
                font-weight: 500 !important;
            }
            #wp-submit {
                border-radius: 0px !important;
                font-size: 18px;
            }
            #user_login, #language-switcher-locales, .button {
                border-radius: 0px !important;
            }
            .dashicons-translation {
                color: white !important;
            }
                .language-switcher{
                display: none !important;
    }
        </style>';
    }
}
add_action('login_enqueue_scripts', 'custom_lost_password_background');


// ✅ **Modify the "Log in" Link on Lost Password Page to Redirect to `/mrcap`**
function custom_lost_password_login_link($link, $link_text) {
    if ( isset($_GET['action']) && $_GET['action'] === 'lostpassword' ) {
        return '<a href="' . site_url('/mrcap') . '">' . __('Login') . '</a>';
    }
    return $link;
}
add_filter('login_footer', function() {
    echo '<script>
        document.addEventListener("DOMContentLoaded", function() {
            let loginLink = document.querySelector(".login #nav a[href*=wp-login]");
            if (loginLink) {
                loginLink.href = "' . site_url('/login') . '";
                loginLink.innerText = "Login";
            }
        });
    </script>';
});


// ✅ **Modify the "Login Page" Link in Email Confirmation Message (`wp-login.php?checkemail=confirm`) to Redirect to `/mrcap`**
add_action('login_footer', function() {
    echo '<script>
        document.addEventListener("DOMContentLoaded", function() {
            let emailMessageLink = document.querySelector("#login-message p a[href*=wp-login]");
            if (emailMessageLink) {
                emailMessageLink.href = "' . site_url('/login') . '";
            }
        });
    </script>';
});



// profile click redirect
function redirect_user_on_profile_click() {
    if (!is_user_logged_in()) {
        return; // Do nothing if user is not logged in
    }

    $user = wp_get_current_user();
    $user_email = $user->user_email;

    global $wpdb;
    $table_name = $wpdb->prefix . 'club_members';

    $query = $wpdb->prepare("SELECT role FROM $table_name WHERE user_email = %s", $user_email);
    $role = $wpdb->get_var($query);

    $redirect_url = ($role === 'Club Manager') ? '/manager-dashboard' : '/member-dashboard';

    ?>
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            document.querySelectorAll(".mega-mega-menu-profile-icon").forEach(function (element) {
                element.addEventListener("click", function (event) {
                    event.preventDefault();
                    window.location.href = "<?php echo esc_url($redirect_url); ?>";
                });
            });
        });
    </script>
    <?php
}
add_action('wp_footer', 'redirect_user_on_profile_click');



add_filter('woocommerce_new_customer_data', 'set_username_as_email_for_woo_users', 10, 1);

function set_username_as_email_for_woo_users($customer_data) {
    if (!empty($customer_data['user_email'])) {
        $customer_data['user_login'] = sanitize_user($customer_data['user_email'], true);
    }
    return $customer_data;
}
