<?php

// Hook into WordPress to create the meta box
add_action('add_meta_boxes', 'add_select_club_meta_box');

// Hook into WordPress to save the custom field data
add_action('save_post', 'save_select_club_data');

// Function to create the meta box
function add_select_club_meta_box() {
    $screens = [
        'post',               // Posts               
        'product',            // WooCommerce Products
        'ajde_events',  
        'page',      // EventOn Events (Custom Post Type)
    ];

    foreach ($screens as $screen) {
        add_meta_box(
            'select_club_meta_box', // Unique ID of the meta box
            'Select Club',          // Title of the meta box
            'select_club_meta_box_callback', // Callback function to render the meta box
            $screen,                // The screen on which the box should appear
            'side',                 // Context where the box should appear ('side', 'normal', 'advanced')
            'high'                  // Priority of the meta box
        );
    }
}

function select_club_meta_box_callback($post) {
    global $wpdb, $current_user;
    wp_get_current_user(); // Ensure we have the current user

    // Check if the user is an administrator
    $is_admin = in_array('administrator', $current_user->roles);

    // Retrieve user's club from the wp_club_members table
    $user_club = $wpdb->get_row($wpdb->prepare(
        "SELECT club_id, club_name FROM wp_club_members WHERE user_email = %s LIMIT 1",
        $current_user->user_email
    ), ARRAY_A);

    // Retrieve saved club ID if it exists
    $selected_club_id = get_post_meta($post->ID, '_select_club_id', true);

    echo '<label for="select_club">Select Club:</label>';
    echo '<select name="select_club" id="select_club">';

    if ($is_admin || empty($user_club)) {
        // Add "Global" option
        $global_selected = (empty($selected_club_id) || $selected_club_id === 'Global') ? 'selected="selected"' : '';
        echo '<option value="Global" ' . $global_selected . '>Global</option>';

        // Admins and users without a club should see all available clubs
        $clubs = $wpdb->get_results("SELECT club_id, club_name FROM wp_clubs", ARRAY_A);

        if (!empty($clubs)) {
            foreach ($clubs as $club) {
                $selected = ($club['club_id'] == $selected_club_id) ? 'selected="selected"' : '';
                echo '<option value="' . esc_attr($club['club_id']) . '" ' . $selected . '>' . esc_html($club['club_name']) . '</option>';
            }
        } else {
            echo '<option value="">No clubs available</option>';
        }
    } else {
        // Regular users only see their assigned club
        $selected = ($user_club['club_id'] == $selected_club_id) ? 'selected="selected"' : '';
        echo '<option value="' . esc_attr($user_club['club_id']) . '" ' . $selected . '>' . esc_html($user_club['club_name']) . '</option>';
    }

    echo '</select>';

    // Add a nonce field for security
    wp_nonce_field('save_select_club_nonce', 'select_club_nonce');
}




// Function to save the custom field data when the post/page is saved
function save_select_club_data($post_id) {
    // Check for nonce security
    if (!isset($_POST['select_club_nonce']) || !wp_verify_nonce($_POST['select_club_nonce'], 'save_select_club_nonce')) {
        return $post_id;
    }

    // Check if auto-saving
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return $post_id;
    }

    // Check user permissions
    $post_type = get_post_type($post_id);
    if (in_array($post_type, ['post', 'page', 'product', 'ajde_events'])) {
        if (!current_user_can('edit_post', $post_id)) {
            return $post_id;
        }
    }

    global $wpdb;

    // Validate and sanitize the input value
    if (isset($_POST['select_club'])) {
        $selected_club_id = sanitize_text_field($_POST['select_club']);

        // Handle the "Global" option separately
        if ($selected_club_id === 'Global') {
            update_post_meta($post_id, '_select_club_id', 'Global');
            update_post_meta($post_id, '_select_club_name', 'Global');
        } else {
            // Fetch the club name based on the club ID
            $club_name = $wpdb->get_var($wpdb->prepare("SELECT club_name FROM wp_clubs WHERE club_id = %d", $selected_club_id));

            // Save the club ID and name
            if ($club_name) {
                update_post_meta($post_id, '_select_club_id', $selected_club_id);
                update_post_meta($post_id, '_select_club_name', $club_name);
            }
        }
    }
}


// Unregister and re-register the EventOn post type to modify the slug
add_action('init', 'override_post_product_eventon_types', 20);

function override_post_product_eventon_types() {
    // Override default WordPress post type
    register_post_type('post', array(
        'labels' => array(
            'name'                  => 'Posts',
            'singular_name'         => 'Post',
            'menu_name'             => 'Posts',
            'add_new'               => 'Add New Post',
            'add_new_item'          => 'Add New Post',
            'edit_item'             => 'Edit Post',
            'new_item'              => 'New Post',
            'view_item'             => 'View Post',
            'search_items'          => 'Search Posts',
            'not_found'             => 'No Posts Found',
            'not_found_in_trash'    => 'No Posts Found in Trash',
        ),
        'public'                => true,
        'show_ui'               => true,
        'capability_type'       => 'post',
        'map_meta_cap'          => true,
        'publicly_queryable'    => true,
        'rewrite'               => array(
            'slug' => '%club%/posts', // Modify slug for normal posts
        ),
        'query_var'             => true,
        'show_in_rest'          => true,
        'supports'              => array('title', 'author', 'editor', 'custom-fields', 'thumbnail', 'page-attributes', 'comments'),
        'has_archive'           => true,
    ));

    // Override WooCommerce product post type
    if (class_exists('WooCommerce')) {
        register_post_type('product', array(
            'labels' => array(
                'name'                  => 'Products',
                'singular_name'         => 'Product',
                'menu_name'             => 'Products',
                'add_new'               => 'Add New Product',
                'add_new_item'          => 'Add New Product',
                'edit_item'             => 'Edit Product',
                'new_item'              => 'New Product',
                'view_item'             => 'View Product',
                'search_items'          => 'Search Products',
                'not_found'             => 'No Products Found',
                'not_found_in_trash'    => 'No Products Found in Trash',
            ),
            'public'                => true,
            'show_ui'               => true,
            'capability_type'       => 'product',
            'map_meta_cap'          => true,
            'publicly_queryable'    => true,
            'rewrite'               => array(
                'slug' => '%club%/products', // Modify slug for products
            ),
            'query_var'             => true,
            'show_in_rest'          => true,
            'supports'              => array('title', 'editor', 'custom-fields', 'thumbnail', 'page-attributes', 'comments'),
            'has_archive'           => true,
        ));
    }

    // Override EventOn events post type
    $evOpt = get_option('eventon_options'); // Adjust the option key if different
    $event_slug = (!empty($evOpt['evo_event_slug'])) ? $evOpt['evo_event_slug'] : 'events';

    register_post_type('ajde_events', array(
        'labels' => array(
            'name'                  => 'Events',
            'singular_name'         => 'Event',
            'menu_name'             => 'Events',
            'add_new'               => 'Add New Event',
            'add_new_item'          => 'Add New Event',
            'edit_item'             => 'Edit Event',
            'new_item'              => 'New Event',
            'view_item'             => 'View Event',
            'search_items'          => 'Search Events',
            'not_found'             => 'No Events Found',
            'not_found_in_trash'    => 'No Events Found in Trash',
        ),
        'public'                => true,
        'show_ui'               => true,
        'capability_type'       => 'eventon',
        'map_meta_cap'          => true,
        'publicly_queryable'    => true,
        'rewrite'               => array(
            'slug' => '%club%/' . $event_slug, // Modify slug for events
        ),
        'query_var'             => true,
        'show_in_rest'          => true,
        'supports'              => array('title', 'author', 'editor', 'custom-fields', 'thumbnail', 'page-attributes', 'comments'),
        'has_archive'           => true,
    ));
}

// Dynamically replace %club% in permalinks for posts, products, and events
add_filter('post_type_link', 'add_club_to_permalink', 10, 2);

function add_club_to_permalink($post_link, $post) {
    if (in_array($post->post_type, ['post', 'product', 'ajde_events'])) {
        // Retrieve the club name from post meta
        $club_name = get_post_meta($post->ID, '_select_club_name', true);

        if (!empty($club_name)) {
            $club_slug = sanitize_title($club_name);
            return str_replace('%club%', $club_slug, $post_link);
        } else {
            return str_replace('%club%', 'Global', $post_link); // Default if no club is set
        }
    }
    return $post_link;
}

// Add custom rewrite rules for the new URL structure for posts, products, and events
add_action('init', 'add_rewrite_rules_for_custom_types', 20);

function add_rewrite_rules_for_custom_types() {
    // Rewrite rules for Posts
    add_rewrite_rule(
        '^([^/]+)/posts/([^/]+)/?$',
        'index.php?post_type=post&name=$matches[2]',
        'top'
    );

    // Rewrite rules for Products
    add_rewrite_rule(
        '^([^/]+)/products/([^/]+)/?$',
        'index.php?post_type=product&name=$matches[2]',
        'top'
    );

    // Rewrite rules for Events
    add_rewrite_rule(
        '^([^/]+)/events/([^/]+)/?$',
        'index.php?post_type=ajde_events&name=$matches[2]',
        'top'
    );

    // Flush rewrite rules (only run this once)
    flush_rewrite_rules();
}
