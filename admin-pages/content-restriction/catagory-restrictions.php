<?php



// Register the meta key so it's exposed via the REST API.
function register_taxonomy_custom_dropdown_meta() {
    register_term_meta( 'category', 'taxonomy_custom_dropdown', array(
        'show_in_rest' => true,
        'single'       => true,
        'type'         => 'string',
    ) );
}
add_action( 'init', 'register_taxonomy_custom_dropdown_meta' );

// Modify the REST API query for categories based on the logged-in user's club ID.
function filter_categories_by_meta( $args, $request ) {
    // Get the logged-in user.
    $current_user = wp_get_current_user();
    if ( ! $current_user || ! $current_user->ID ) {
        // No user logged in; return all categories.
        return $args;
    }
    
    // For administrators, show all categories.
    if ( current_user_can( 'administrator' ) ) {
        return $args;
    }
    
    global $wpdb;
    // Adjust the table name if your table prefix is different.
    $table_name = $wpdb->prefix . 'club_members';
    $user_email = $current_user->user_email;
    
    // Retrieve the club ID for the logged-in user.
    $club_id = $wpdb->get_var( $wpdb->prepare(
        "SELECT club_id FROM $table_name WHERE user_email = %s",
        $user_email
    ) );
    
    if ( empty( $club_id ) ) {
        // If no club ID is found, do not filter and return all categories.
        return $args;
    }
    
    // Filter: Only return categories whose meta value matches the user's club ID.
    $args['meta_query'] = array(
        array(
            'key'     => 'taxonomy_custom_dropdown',
            'value'   => $club_id,
            'compare' => '='
        )
    );
    
    return $args;
}
add_filter( 'rest_category_query', 'filter_categories_by_meta', 10, 2 );



function filter_admin_product_cat_terms( $args, $taxonomies, $args2 = array() ) {
    // Only target product_cat taxonomy in the admin area.
    if ( is_admin() && in_array( 'product_cat', (array) $taxonomies ) ) {
        $current_user = wp_get_current_user();
        if ( ! $current_user || ! $current_user->ID ) {
            return $args;
        }
        // Allow administrators to see all categories.
        if ( current_user_can( 'administrator' ) ) {
            return $args;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'club_members';
        $user_email = $current_user->user_email;
        
        // Retrieve the club ID for the logged-in user.
        $club_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT club_id FROM $table_name WHERE user_email = %s",
            $user_email
        ) );
        
        if ( ! empty( $club_id ) ) {
            // Add a meta query to filter product categories based on the club ID.
            $args['meta_query'] = array(
                array(
                    'key'     => 'taxonomy_custom_dropdown',
                    'value'   => $club_id,
                    'compare' => '='
                )
            );
        }
    }
    return $args;
}
add_filter( 'get_terms_args', 'filter_admin_product_cat_terms', 10, 3 );



function filter_admin_event_type_terms( $args, $taxonomies, $args2 = array() ) {
    // Only target event_type taxonomy in the admin area.
    if ( is_admin() && in_array( 'event_type', (array) $taxonomies ) ) {
        $current_user = wp_get_current_user();
        if ( ! $current_user || ! $current_user->ID ) {
            return $args;
        }
        
        // Allow administrators to see all event categories.
        if ( current_user_can( 'administrator' ) ) {
            return $args;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'club_members';
        $user_email = $current_user->user_email;
        
        // Retrieve the club ID for the logged-in user.
        $club_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT club_id FROM $table_name WHERE user_email = %s",
            $user_email
        ) );

        if ( ! empty( $club_id ) ) {
            // Add a meta query to filter event categories based on the club ID.
            $args['meta_query'] = array(
                array(
                    'key'     => 'taxonomy_custom_dropdown',
                    'value'   => $club_id,
                    'compare' => '='
                )
            );
        }
    }
    return $args;
}
add_filter( 'get_terms_args', 'filter_admin_event_type_terms', 10, 3 );


// Allow all logged-in users to assign post and product categories.
function allow_all_to_assign_terms( $caps, $cap, $user_id, $args ) {
    if ( in_array( $cap, array( 'assign_product_terms', 'assign_category' ) ) ) {
        // Remove restrictions by returning an empty array.
        $caps = array();
    }
    return $caps;
}
add_filter( 'map_meta_cap', 'allow_all_to_assign_terms', 10, 4 );


function force_save_event_type_terms_for_club_members($post_id, $post, $update) {
    // Only run for ajde_events post type
    if ($post->post_type !== 'ajde_events') {
        return;
    }

    // Security: prevent during autosave, ajax, bulk, etc.
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (defined('DOING_AJAX') && DOING_AJAX) return;
    if (wp_is_post_revision($post_id)) return;

    // Make sure current user has permission to edit this post
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Check if event_type taxonomy is submitted
    if (isset($_POST['tax_input']['event_type'])) {
        $event_types = array_map('intval', (array) $_POST['tax_input']['event_type']);
        wp_set_object_terms($post_id, $event_types, 'event_type');
    }
}
add_action('save_post', 'force_save_event_type_terms_for_club_members', 20, 3);


function force_enable_event_type_checkboxes() {
    ?>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            document.querySelectorAll('#taxonomy-event_type input[type="checkbox"]').forEach(checkbox => {
                checkbox.removeAttribute("disabled");
            });
        });
    </script>
    <?php
}
add_action( 'admin_footer', 'force_enable_event_type_checkboxes' );



function remove_tags_taxonomy_for_club_members() {
    if (is_user_logged_in()) {
        global $wpdb;
        $current_user = wp_get_current_user();

        // Check if the user exists in `wp_club_members`
        $table_name = $wpdb->prefix . 'club_members';
        $user_email = $current_user->user_email;
        $user_in_club = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE user_email = %s",
            $user_email
        ));

        // If user is in `wp_club_members` and NOT an admin, remove Tags
        if ($user_in_club && !current_user_can('administrator')) {
            add_action('init', function () {
                unregister_taxonomy_for_object_type('post_tag', 'post'); // Remove Tags from Posts
                unregister_taxonomy_for_object_type('post_tag', 'page'); // Remove Tags from Pages
                unregister_taxonomy_for_object_type('ajde_events_tags', 'ajde_events'); // Remove Tags from Events
                unregister_taxonomy_for_object_type('product_tag', 'product'); // Remove Tags from WooCommerce Products
            }, 100);
        }
    }
}
add_action('init', 'remove_tags_taxonomy_for_club_members', 99);


// hidding event tags 
function remove_event_tags_for_club_members() {
    if (is_user_logged_in()) {
        global $wpdb;
        $current_user = wp_get_current_user();

        // Check if the user exists in `wp_club_members`
        $table_name = $wpdb->prefix . 'club_members';
        $user_email = $current_user->user_email;
        $user_in_club = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE user_email = %s",
            $user_email
        ));

        // If user is in `wp_club_members` and NOT an admin, fully remove Tags from Events
        if ($user_in_club && !current_user_can('administrator')) {

            // 1️⃣ Unregister Tags for Events
            add_action('init', function () {
                unregister_taxonomy_for_object_type('post_tag', 'ajde_events'); // Remove WP default tags
                unregister_taxonomy_for_object_type('ajde_events_tags', 'ajde_events'); // Remove EventON tags
            }, 100);

            // 2️⃣ Prevent Re-Registration of Event Tags
            add_filter('register_taxonomy_args', function ($args, $taxonomy) {
                if ($taxonomy === 'ajde_events_tags' || $taxonomy === 'post_tag') {
                    return []; // Block registration of these taxonomies
                }
                return $args;
            }, 10, 2);

            // 3️⃣ Remove the Meta Box from Edit/Add Event Screens
            add_action('admin_menu', function () {
                remove_meta_box('tagsdiv-ajde_events_tags', 'ajde_events', 'side'); // EventON Tags Box
                remove_meta_box('tagsdiv-post_tag', 'ajde_events', 'side'); // WordPress Default Tags Box
            }, 100);

            // 4️⃣ Redirect if Club Members Access Tags Management
            add_action('admin_init', function () {
                if (isset($_GET['taxonomy']) && $_GET['taxonomy'] === 'ajde_events_tags' && !current_user_can('administrator')) {
                    wp_redirect(admin_url());
                    exit;
                }
            });

            // 5️⃣ Ensure Tags Do Not Appear in the Admin UI
            add_filter('get_editable_roles', function ($roles) {
                global $pagenow;
                if ($pagenow === 'edit-tags.php' && isset($_GET['taxonomy']) && $_GET['taxonomy'] === 'ajde_events_tags') {
                    wp_redirect(admin_url());
                    exit;
                }
                return $roles;
            });

        }
    }
}
add_action('init', 'remove_event_tags_for_club_members', 99);



// hiding event type 2 

function hide_event_type_2_for_club_members() {
    if (is_user_logged_in()) {
        global $wpdb;
        $current_user = wp_get_current_user();

        // Check if the user exists in `wp_club_members`
        $table_name = $wpdb->prefix . 'club_members';
        $user_email = $current_user->user_email;
        $user_in_club = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE user_email = %s",
            $user_email
        ));

        // If user is in `wp_club_members` and NOT an admin, hide "Event Type 2 Categories"
        if ($user_in_club && !current_user_can('administrator')) {
            
            // 1️⃣ Remove "Event Type 2 Categories" meta box from the event edit screen
            add_action('admin_menu', function () {
                remove_meta_box('event_type_2div', 'ajde_events', 'side'); // Remove from Event Edit screen
            }, 100);

            // 2️⃣ Unregister the taxonomy from the Event post type
            add_action('init', function () {
                unregister_taxonomy_for_object_type('event_type_2', 'ajde_events');
            }, 100);

            // 3️⃣ Prevent WordPress from re-registering the taxonomy
            add_filter('register_taxonomy_args', function ($args, $taxonomy) {
                if ($taxonomy === 'event_type_2') {
                    return []; // Block registration of this taxonomy
                }
                return $args;
            }, 10, 2);

            // 4️⃣ Redirect if a club member tries to access the Event Type 2 categories page
            add_action('admin_init', function () {
                if (isset($_GET['taxonomy']) && $_GET['taxonomy'] === 'event_type_2' && !current_user_can('administrator')) {
                    wp_redirect(admin_url());
                    exit;
                }
            });

        }
    }
}
add_action('init', 'hide_event_type_2_for_club_members', 99);
