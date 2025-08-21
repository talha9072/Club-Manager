<?php
// Prevent direct access to this file
if (!defined('ABSPATH')) {
    exit;
}

// Function to create admin menu and submenus
function club_manager_menu() {
    // Add main menu and make "Clubs" the main content page
    add_menu_page(
        __('Club Manager', 'club-manager'), // Page title
        __('Club Manager', 'club-manager'), // Menu title
        'manage_options', // Capability
        'club-manager-clubs', // Use 'club-manager-clubs' as the Menu slug to avoid the extra submenu
        'club_manager_clubs_page', // Function to display page content for Clubs
        'dashicons-groups', // Dashicon for club icon
        6 // Position in the admin menu
    );

    global $wpdb;
    $club_count = $wpdb->get_var("SELECT COUNT(*) FROM wp_clubs");
    
    // Add submenu for "Clubs" (this will effectively be the main page as well)
    add_submenu_page(
        'club-manager-clubs', // Parent slug (main menu)
        __('Clubs', 'club-manager'), // Page title
        __('Clubs <span class="update-plugins count-' . $club_count . '"><span class="plugin-count">' . $club_count . '</span></span>', 'club-manager'), // Submenu title with notification badge
        'manage_options', // Capability
        'club-manager-clubs', // Submenu slug
        'club_manager_clubs_page' // Function to display page content
    );

    // Add submenu for "Add New Club"
    add_submenu_page(
        'club-manager-clubs', // Parent slug (main menu)
        __('Add New Club', 'club-manager'), // Page title
        __('Add New Club', 'club-manager'), // Submenu title
        'manage_options', // Capability
        'club-manager-edit-club', // Submenu slug
        'club_manager_edit_club_page' // Function to display page content
    );

   
}

// Function to include the "Clubs" page or the edit page based on action
function club_manager_clubs_page() {
    // Check if action is 'edit' and a valid club_id is provided
    if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['club_id'])) {
        // Load the edit-all-clubs.php file for editing
        require_once CLUB_MANAGER_PLUGIN_DIR . 'admin-pages/includes/edit-all-clubs.php';
    } else {
        // If no action or different action, load the normal clubs list
        require_once CLUB_MANAGER_PLUGIN_DIR . 'admin-pages/clubs.php';
    }
}

// Function to include the "Add New Club" page
function club_manager_edit_club_page() {
    // Load the "Add New Club" page by default
    require_once CLUB_MANAGER_PLUGIN_DIR . 'admin-pages/edit-clubs.php';
}


// Hook to add the menu in the WordPress dashboard
add_action('admin_menu', 'club_manager_menu');
