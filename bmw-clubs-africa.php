<?php
/**
 * Plugin Name: Club Manager
 * Plugin URI: https://yourpluginwebsite.com
 * Description: A streamlined dashboard for managing users, memberships, events, and orders, tailored specifically for club administrators.
 * Version: 1.0.0
 * Author: Web Hosting Guru
 * Author URI: https://bmwclubs.africa
 * License: GPL2
 * Text Domain: club-manager
 */
 
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('CLUB_MANAGER_VERSION', '1.0.0');
define('CLUB_MANAGER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CLUB_MANAGER_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include admin pages file
require_once CLUB_MANAGER_PLUGIN_DIR . 'admin-pages.php';
require_once CLUB_MANAGER_PLUGIN_DIR . 'bmw-ajax.php';

$file_path = plugin_dir_path(__FILE__) . 'admin-pages/payfast-override.php';
if (!file_exists($file_path)) {
    error_log('File not found: ' . $file_path);
    return;
}

require_once $file_path;

$file_path = plugin_dir_path(__FILE__) . 'admin-pages/payfast-itn.php';
if (!file_exists($file_path)) {
    error_log('File not found: ' . $file_path);
    return;
}

require_once $file_path;

$yoco_path = plugin_dir_path(__FILE__) . 'admin-pages/yoco-gateway/yoco-payment-gateway/yoco_wc_payment_gateway.php';

if (!file_exists($yoco_path)) {
    error_log('Yoco plugin file not found: ' . $yoco_path);
    return;
}

require_once $yoco_path;

$file_path = plugin_dir_path(__FILE__) . 'admin-pages/includes/vehicle-meta-handler.php';
if (!file_exists($file_path)) {
    error_log('File not found: ' . $file_path);
    return;
}
require_once $file_path;

$file_path = plugin_dir_path(__FILE__) . 'admin-pages/includes/backhome.php';
if (!file_exists($file_path)) {
    error_log('File not found: ' . $file_path);
    return;
}
require_once $file_path;

$file_path = plugin_dir_path(__FILE__) . 'admin-pages/includes/profile-form.php';
if (!file_exists($file_path)) {
    error_log('File not found: ' . $file_path);
    return;
}
require_once $file_path;

$file_path = plugin_dir_path(__FILE__) . 'admin-pages/includes/registration-form.php';
if (!file_exists($file_path)) {
    error_log('File not found: ' . $file_path);
    return;
}
require_once $file_path;


$file_path = plugin_dir_path(__FILE__) . 'admin-pages/includes/remove-et-builder.php';
if (!file_exists($file_path)) {
    error_log('File not found: ' . $file_path);
    return;
}
require_once $file_path;

$file_path = plugin_dir_path(__FILE__) . 'admin-pages/includes/attach-gform-products.php';
if (!file_exists($file_path)) {
    error_log('File not found: ' . $file_path);
    return;
}
require_once $file_path;


$file_path = plugin_dir_path(__FILE__) . 'admin-pages/includes/gform-7.php';
if (!file_exists($file_path)) {
    error_log('File not found: ' . $file_path);
    return;
}
require_once $file_path;

$file_path = plugin_dir_path(__FILE__) . 'admin-pages/includes/gform-22.php';
if (!file_exists($file_path)) {
    error_log('File not found: ' . $file_path);
    return;
}
require_once $file_path;

$file_path = plugin_dir_path(__FILE__) . 'admin-pages/includes/membership_metabox.php';
if (!file_exists($file_path)) {
    error_log('File not found: ' . $file_path);
    return;
}
require_once $file_path;


$file_path = plugin_dir_path(__FILE__) . 'admin-pages/includes/payment.php';
if (!file_exists($file_path)) {
    error_log('File not found: ' . $file_path);
    return;
}
require_once $file_path;


$file_path = plugin_dir_path(__FILE__) . 'admin-pages/includes/shortcode.php';
if (!file_exists($file_path)) {
    error_log('File not found: ' . $file_path);
    return;
}
require_once $file_path;


$file_path = plugin_dir_path(__FILE__) . 'admin-pages/includes/role-restriction.php';
if (!file_exists($file_path)) {
    error_log('File not found: ' . $file_path);
    return;
}
require_once $file_path;

$file_path = plugin_dir_path(__FILE__) . 'admin-pages/content-restriction/helper.php';
if (!file_exists($file_path)) {
    error_log('File not found: ' . $file_path);
    return;
}
require_once $file_path;

$file_path = plugin_dir_path(__FILE__) . 'admin-pages/content-restriction/product-restriction.php';
if (!file_exists($file_path)) {
    error_log('File not found: ' . $file_path);
    return;
}
require_once $file_path;


$file_path = plugin_dir_path(__FILE__) . 'admin-pages/content-restriction/events-restriction.php';
if (!file_exists($file_path)) {
    error_log('File not found: ' . $file_path);
    return;
}
require_once $file_path;

$file_path = plugin_dir_path(__FILE__) . 'admin-pages/content-restriction/bank-restriction.php';
if (!file_exists($file_path)) {
    error_log('File not found: ' . $file_path);
    return;
}
require_once $file_path;

$file_path = plugin_dir_path(__FILE__) . 'admin-pages/content-restriction/backend-restriction.php';
if (!file_exists($file_path)) {
    error_log('File not found: ' . $file_path);
    return;
}
require_once $file_path;

$file_path = plugin_dir_path(__FILE__) . 'admin-pages/content-restriction/catagory-restrictions.php';
if (!file_exists($file_path)) {
    error_log('File not found: ' . $file_path);
    return;
}
require_once $file_path;


$file_path = plugin_dir_path(__FILE__) . 'admin-pages/content-restriction/lost-password.php';
if (!file_exists($file_path)) {
    error_log('File not found: ' . $file_path);
    return;
}
require_once $file_path;


$file_path = plugin_dir_path(__FILE__) . 'admin-pages/content-restriction/checkout-prepopulate.php';
if (!file_exists($file_path)) {
    error_log('File not found: ' . $file_path);
    return;
}
require_once $file_path;






// Assuming the main plugin file is in the root folder of your plugin
require_once plugin_dir_path(__FILE__) . 'admin-pages/includes/post-permissions.php';
// Assuming the main plugin file is in the root folder of your plugin
require_once plugin_dir_path(__FILE__) . 'admin-pages/includes/display-conditions.php';

// Assuming the main plugin file is in the root folder of your plugin
require_once plugin_dir_path(__FILE__) . 'admin-pages/includes/taxonomies-permissions.php';

// Assuming the main plugin file is in the root folder of your plugin
require_once plugin_dir_path(__FILE__) . 'admin-pages/dashboard/mainboard.php';
// Assuming the main plugin file is in the root folder of your plugin
require_once plugin_dir_path(__FILE__) . 'admin-pages/members-dashboard/members-dashboard.php';



// Stripe plugin
add_action('plugins_loaded', function() {
    if (!class_exists('WC_Gateway_Stripe')) {
        require_once __DIR__ . '/admin-pages/woocommerce-gateway-stripe/woocommerce-gateway-stripe.php';
    }
    if (function_exists('woocommerce_gateway_stripe_init')) {
        woocommerce_gateway_stripe_init(); // Force initialization
    }
});


// Plugin activation hook
register_activation_hook(__FILE__, 'club_manager_activate');

function club_manager_activate() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    // Include the WordPress upgrade file for dbDelta()
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

// Table for Clubs
$table_name = $wpdb->prefix . 'clubs';
$sql = "CREATE TABLE $table_name (
    club_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    club_name VARCHAR(255) NOT NULL,
    club_url VARCHAR(255) NOT NULL,
    club_logo VARCHAR(255) DEFAULT NULL,
    club_status ENUM('active', 'draft', 'trash') NOT NULL DEFAULT 'draft',
    notifications TEXT DEFAULT NULL,
    template_id BIGINT(20) UNSIGNED DEFAULT NULL,
    gform_id BIGINT(20) UNSIGNED DEFAULT NULL,
    event_gform TEXT DEFAULT NULL,  -- Stores multiple event form IDs as CSV
    rides_form TEXT DEFAULT NULL,   -- Stores multiple ride form IDs as CSV
    registration_form TEXT DEFAULT NULL, -- Added to store multiple registration form IDs
    PRIMARY KEY (club_id)
) $charset_collate;";
dbDelta($sql);




    // Table for EFT Details
$table_name = $wpdb->prefix . 'eft_details';
$sql = "CREATE TABLE $table_name (
    eft_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    club_id BIGINT(20) UNSIGNED NOT NULL,
    account_name VARCHAR(255) NOT NULL,
    account_number VARCHAR(255) NOT NULL,
    bank_name VARCHAR(255) NOT NULL,
    branch_code VARCHAR(50) NOT NULL,
    iban TEXT NOT NULL,
    PRIMARY KEY (eft_id),
    FOREIGN KEY (club_id) REFERENCES {$wpdb->prefix}clubs(club_id) ON DELETE CASCADE
) $charset_collate;";
dbDelta($sql);


    // Table for Club Roles
    $table_name = $wpdb->prefix . 'club_roles';
    $sql = "CREATE TABLE $table_name (
        role_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        club_id BIGINT(20) UNSIGNED NOT NULL,
        username VARCHAR(255) NOT NULL,
        role_name VARCHAR(255) NOT NULL,
        PRIMARY KEY (role_id),
        FOREIGN KEY (club_id) REFERENCES {$wpdb->prefix}clubs(club_id) ON DELETE CASCADE
    ) $charset_collate;";
    dbDelta($sql);

    // Table for Payment Gateways
    $table_name = $wpdb->prefix . 'payment_gateways';
    $sql = "CREATE TABLE $table_name (
        gateway_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        club_id BIGINT(20) UNSIGNED NOT NULL,
        gateway_type VARCHAR(50) NOT NULL,
        merchant_id VARCHAR(255) DEFAULT NULL,
        merchant_key VARCHAR(255) DEFAULT NULL,
        api_key VARCHAR(255) DEFAULT NULL,
        secret_key VARCHAR(255) DEFAULT NULL,
        yoco_link VARCHAR(255) DEFAULT NULL,
        PRIMARY KEY (gateway_id),
        FOREIGN KEY (club_id) REFERENCES {$wpdb->prefix}clubs(club_id) ON DELETE CASCADE
    ) $charset_collate;";
    dbDelta($sql);

    // Table for Club Members
    $table_name = $wpdb->prefix . 'club_members';
    $sql = "CREATE TABLE $table_name (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        club_id BIGINT(20) UNSIGNED NOT NULL,
        club_name VARCHAR(255) NOT NULL,
        user_name VARCHAR(255) NOT NULL,
        user_email VARCHAR(255) NOT NULL,
        role VARCHAR(255) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        FOREIGN KEY (club_id) REFERENCES {$wpdb->prefix}clubs(club_id) ON DELETE CASCADE
    ) $charset_collate;";
    dbDelta($sql);

    // Table for Club Committee
    $table_name = $wpdb->prefix . 'club_committee';
    $sql = "CREATE TABLE $table_name (
        id INT(11) NOT NULL AUTO_INCREMENT,
        user_id BIGINT(20) UNSIGNED DEFAULT NULL,
        club_id BIGINT(20) UNSIGNED NOT NULL,
        title VARCHAR(255) NOT NULL,
        custom_title VARCHAR(255) DEFAULT NULL,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        FOREIGN KEY (club_id) REFERENCES {$wpdb->prefix}clubs(club_id) ON DELETE CASCADE
    ) $charset_collate;";
    dbDelta($sql);

    // Create the 'evaluations' upload directory
    $upload_dir = wp_upload_dir();
    $evaluation_folder_path = $upload_dir['basedir'] . '/evaluations/';

    if (!file_exists($evaluation_folder_path)) {
        wp_mkdir_p($evaluation_folder_path); // Create directory recursively
    }

    // Optional: Log success for folder creation
    if (!file_exists($evaluation_folder_path)) {
        wp_die('Error: Unable to create "evaluations" folder in uploads directory. Please check folder permissions.');
    }
}




// Plugin deactivation hook
register_deactivation_hook(__FILE__, 'club_manager_deactivate');
function club_manager_deactivate() {
    // Placeholder for deactivation tasks (e.g., removing scheduled events, temporary data cleanup)
}

// Enqueue the JavaScript file
function club_manager_enqueue_scripts() {
    // Always load in admin
    if (is_admin()) {
        wp_enqueue_script('edit-clubs-js', CLUB_MANAGER_PLUGIN_URL . 'assets/edit-clubs.js', array('jquery', 'wp-mediaelement'), null, true);
        wp_enqueue_media();
        wp_enqueue_style('select2-css', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css');
        wp_enqueue_script('select2-js', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js', array('jquery'), null, true);
        return;
    }

    // Also load on specific frontend pages
    if (is_page(['manager-dashboard', 'member-dashboard'])) {
        wp_enqueue_media();
        wp_enqueue_style('select2-css', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css');
        wp_enqueue_script('select2-js', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js', array('jquery'), null, true);
    }
}
add_action('admin_enqueue_scripts', 'club_manager_enqueue_scripts');
add_action('wp_enqueue_scripts', 'club_manager_enqueue_scripts'); // for frontend




function enqueue_wc_tooltip_scripts() {
    if (!is_admin() || !class_exists('WooCommerce')) {
        return; // Prevent running on frontend or if WooCommerce is not active
    }

    wp_enqueue_style('woocommerce_admin_styles');
    wp_enqueue_script('woocommerce_admin');
}
add_action('admin_enqueue_scripts', 'enqueue_wc_tooltip_scripts');




// Register the shortcode for Manager Dashboard
function register_manager_dashboard_shortcode() {
    add_shortcode('manager_dashboard', 'render_manager_dashboard_html');
}
add_action('init', 'register_manager_dashboard_shortcode');

// Add the Members Dashboard shortcode
function register_members_dashboard_shortcode() {
    add_shortcode('members_dashboard', 'render_members_dashboard_html');
}
add_action('init', 'register_members_dashboard_shortcode');



function enqueue_club_manager_scripts() {
    // Admin area – always load
    if (is_admin()) {
        wp_enqueue_script('jquery');
        wp_enqueue_script('select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js', array('jquery'), '4.0.13', true);
        wp_enqueue_style('select2-css', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css', array(), '4.0.13');
        return;
    }

    // Frontend – load only on two specific pages
    if (is_page(['manager-dashboard', 'member-dashboard'])) {
        wp_enqueue_script('jquery');
        wp_enqueue_script('select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js', array('jquery'), '4.0.13', true);
        wp_enqueue_style('select2-css', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css', array(), '4.0.13');

        wp_localize_script('select2', 'ajax_object', array(
            'ajaxurl' => admin_url('admin-ajax.php')
        ));
    }
}
add_action('wp_enqueue_scripts', 'enqueue_club_manager_scripts');
add_action('admin_enqueue_scripts', 'enqueue_club_manager_scripts');



function include_font_awesome() {
    // Load in wp-admin
    if (is_admin()) {
        wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css', array(), '6.0.0-beta3');
        return;
    }

    // Load only on these two frontend pages
    if (is_page(['manager-dashboard', 'member-dashboard'])) {
        wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css', array(), '6.0.0-beta3');
    }
}
add_action('wp_enqueue_scripts', 'include_font_awesome');
add_action('admin_enqueue_scripts', 'include_font_awesome');



add_action('admin_menu', function () {
    global $menu;
    
    // Loop through all admin menu items
    foreach ($menu as $key => $value) {
        // Check if there are duplicate "Posts" entries
        if ($value[0] === 'Posts' && $value[2] === 'edit.php') {
            // Remove the duplicate entry
            unset($menu[$key]);
            break; // Exit the loop after removing one duplicate
        }
    }
}, 999); // Hook late to ensure menus are already added


add_filter('use_block_editor_for_post', function ($use_block_editor, $post) {
    // Ensure we are working with a valid post object
    if ($post && $post->post_type === 'post') {
        return true; // Enable Block Editor for standard posts
    }
    return false; // Disable Block Editor for all other post types
}, 10, 2);



add_action('wp_footer', function() {
    global $shortcode_tags;
});


function enqueue_media_uploader_script() {
    if (is_admin()) return; // Do not run in wp-admin

    // Only load media uploader on specific frontend pages
    if (is_page(['manager-dashboard', 'member-dashboard'])) {
        wp_enqueue_media();
        wp_enqueue_script('jquery');
    }
}
add_action('wp_enqueue_scripts', 'enqueue_media_uploader_script');
