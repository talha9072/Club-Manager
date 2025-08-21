<?php


// Function to determine what to render
function render_dashboard() {
    if (isset($_GET['Add']) && $_GET['Add'] === 'true') {
        echo '<h3>Add Post</h3>';
        handle_frontend_post();
        return; // Stop further execution
    }

    if (isset($_GET['post_id']) && !empty($_GET['post_id'])) {
        echo '<h3>Edit Post</h3>';
        render_edit_post_form();
        return; // Stop further execution
    }

    
    // Default: Show posts table
    echo '<div class="admin-switch">';
    echo '<h2 class="manager-h2">Posts</h2>';
    echo '<a href="' . esc_url(admin_url('edit.php')) . '" class="button All-button">Advanced</a>';
    echo '</div>';
        
    posts_table();
}

// Call the function to determine what to render
render_dashboard();



function get_club_categories($selected_category = []) {
    global $wpdb;

    // Get the logged-in user's email
    $user_email = wp_get_current_user()->user_email;
    if (!$user_email) {
        return [];
    }

    // Fetch the club ID for the logged-in user
    $club_data = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT club_id FROM {$wpdb->prefix}club_members WHERE user_email = %s LIMIT 1",
            $user_email
        )
    );

    if (!$club_data) {
        return [];
    }

    $club_id = $club_data->club_id;

    // Fetch categories linked to the club_id
    $categories = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT t.term_id, t.name AS taxonomy_name, tt.parent
            FROM {$wpdb->prefix}terms AS t
            INNER JOIN {$wpdb->prefix}term_taxonomy AS tt ON t.term_id = tt.term_id
            INNER JOIN {$wpdb->prefix}termmeta AS tm ON t.term_id = tm.term_id
            WHERE tt.taxonomy = 'category'
            AND tm.meta_key = 'taxonomy_custom_dropdown'
            AND tm.meta_value = %d",
            $club_id
        )
    );

    if (!$categories) {
        return [];
    }

    // Build category hierarchy
    $hierarchy = [];
    foreach ($categories as $category) {
        $parent_id = intval($category->parent);
        $hierarchy[$parent_id][] = $category;
    }

    // Recursive function to render categories
    function render_category_options($hierarchy, $parent_id = 0, $depth = 0, $selected_category = []) {
        if (!isset($hierarchy[$parent_id])) {
            return;
        }

        foreach ($hierarchy[$parent_id] as $category) {
            $padding = str_repeat('&nbsp;', $depth * 4); // Indentation
            $selected = in_array($category->term_id, (array) $selected_category) ? 'selected' : '';
            echo '<option value="' . esc_attr($category->term_id) . '" ' . $selected . '>' . $padding . esc_html($category->taxonomy_name) . '</option>';
            render_category_options($hierarchy, $category->term_id, $depth + 1, $selected_category);
        }
    }

    return function() use ($hierarchy, $selected_category) {
        render_category_options($hierarchy, 0, 0, $selected_category);
    };
}



function handle_frontend_post() {
    global $wpdb;

    // Handle form submission logic
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_post'])) {
        // Verify nonce for security
        if (!isset($_POST['post_nonce']) || !wp_verify_nonce($_POST['post_nonce'], 'frontend_post_nonce')) {
            wp_die('Security check failed!');
        }

        // Sanitize and prepare data
        $post_title = sanitize_text_field($_POST['post_title']);
        $post_content = wp_kses_post($_POST['post_content']);
        $post_excerpt = sanitize_text_field($_POST['post_excerpt']);
        $post_category = intval($_POST['post_category']);
        $post_status = isset($_POST['post_status']) && in_array($_POST['post_status'], ['publish', 'draft']) 
            ? sanitize_text_field($_POST['post_status']) 
            : 'draft';

        // Get the logged-in user's email
        $user_email = wp_get_current_user()->user_email;

        if (empty($user_email)) {
            wp_die('No user is logged in.');
        }

        // Fetch the club data for the logged-in user
        $club_data = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT club_name, club_id FROM {$wpdb->prefix}club_members WHERE user_email = %s LIMIT 1",
                $user_email
            )
        );

        if ($club_data) {
            $club_name = $club_data->club_name;
            $selected_club_id = $club_data->club_id;
        } else {
            wp_die('No club data found for the logged-in user.');
        }

        // Check if this is an update to an existing post
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

        // Prepare post data
        $post_data = [
            'post_title'   => $post_title,
            'post_content' => $post_content,
            'post_excerpt' => $post_excerpt,
            'post_status'  => $post_status,
            'post_author'  => get_current_user_id(),
            'post_category' => [$post_category],
        ];

        if ($post_id > 0 && get_post($post_id)) {
            // Update existing post
            $post_data['ID'] = $post_id;
            wp_update_post($post_data);

            echo '<p>Post updated successfully!</p>';
        } else {
            // Insert new post
            $post_id = wp_insert_post($post_data);

            if (is_wp_error($post_id)) {
                wp_die('Error creating post: ' . $post_id->get_error_message());
            } else {
                // Add club data as postmeta
                update_post_meta($post_id, '_select_club_name', $club_name);
                update_post_meta($post_id, '_select_club_id', $selected_club_id);

                // Handle featured image upload
                // Handle featured image selected via media uploader
                if (!empty($_POST['featured_image'])) {
                    $attachment_id = intval($_POST['featured_image']);
                    set_post_thumbnail($post_id, $attachment_id);
                }


                // Redirect to avoid duplicate form submission
                wp_redirect(add_query_arg('post_created', 'success', $_SERVER['REQUEST_URI']));
                exit;
            }
        }
    }

    // Display success or error message
    if (isset($_GET['post_created']) && $_GET['post_created'] === 'success') {
        echo '<p>Post created successfully!</p>';
    }

    $render_categories = get_club_categories(isset($post_category) ? [$post_category] : []);


    // Render the form
    ?>
    <form id="frontend-post-form" method="post" enctype="multipart/form-data">

    
        <div class="event-row">
            <div class="event-label">
                <label for="post-title">Post Title</label>
                <input type="text" id="post-title" class="inps" name="post_title" required value="<?php echo isset($post_title) ? esc_attr($post_title) : ''; ?>" />
            </div>

            <div class="event-label">

                <label for="post-status">Post Status</label>
                <select id="post-status" name="post_status">
                    <option value="draft" <?php selected($post_status, 'draft'); ?>>Draft</option>
                    <option value="publish" <?php selected($post_status, 'publish'); ?>>Publish</option>
                </select>

            </div>


       
        </div>

        <div class="event-row">
            <div class="event-label">
            <label for="post-category">Category</label>
            <select id="post-category" name="post_category">
            <?php if ($render_categories) $render_categories(); ?>
            </select>
            </div>

            <div class="event-label">
            <label for="post-excerpt">Excerpt</label>
            <textarea id="post-excerpt" name="post_excerpt" rows="2"><?php echo isset($post_excerpt) ? esc_textarea($post_excerpt) : ''; ?></textarea>
            </div>


        </div>



        <div class="wysiwyg-wrapper">
        
        <div class="wysiwyg-column1">

                <?php
            wp_editor(isset($post_content) ? $post_content : '', 'post_content', [
                'textarea_name' => 'post_content',
                'textarea_rows' => 10,
                'teeny'         => false,  // Ensure the full editor is loaded
                'media_buttons' => current_user_can('upload_files'), // Show media button only if user can upload
            ]);
            
                ?>

        </div>
       

        <div class="event-label wysiwyg-column2">
            <label for="featured_image">Featured Image</label>
            <input type="hidden" id="featured_image" name="featured_image" value="">
            <div style="width: 100%; height: 210px; border: 1px dashed #ccc; display: flex; align-items: center; justify-content: center; position: relative; background-color: #f9f9f9;">
                <span id="featured_image_placeholder">No featured image selected</span>
                <img id="featured_image_preview" src="" alt="Featured Image Preview" style="display: none; width: 200px; height: 200px; border-radius: 4px;">
                <button type="button" id="choose_featured_image" style="position: absolute; bottom: -40px; background-color: #0073aa; color: white; border: none; padding: 10px 15px; cursor: pointer; border-radius: 4px;">Choose Image</button>
            </div>
        </div>


        </div>

       

        <?php if (isset($post_id) && $post_id > 0): ?>
            <input type="hidden" name="post_id" value="<?php echo esc_attr($post_id); ?>" />
        <?php endif; ?>

        <?php wp_nonce_field('frontend_post_nonce', 'post_nonce'); ?>

        <button type="submit"class="All-button" name="submit_post"><?php echo isset($post_id) && $post_id > 0 ? 'Save' : 'Submit Post'; ?></button>
    </form>
    <?php
    
}




function posts_table() {
    global $wpdb;

    // Get the logged-in user's email
    $user_email = wp_get_current_user()->user_email;
    if (empty($user_email)) {
        echo 'No user is logged in.';
        return;
    }

    // Fetch club ID for the logged-in user
    $club_data = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT club_id FROM {$wpdb->prefix}club_members WHERE user_email = %s LIMIT 1",
            $user_email
        )
    );

    if (!$club_data) {
        echo 'No club found for the logged-in user.';
        return;
    }

    $club_id = $club_data->club_id;

    


    // Fetch counts for post statuses
    $post_counts = [
        'all' => $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(p.ID)
                FROM {$wpdb->prefix}postmeta pm
                JOIN {$wpdb->prefix}posts p ON pm.post_id = p.ID
                WHERE pm.meta_key = '_select_club_id' AND pm.meta_value = %d
                AND p.post_type = 'post'",
                $club_id
            )
        ),
        'publish' => $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(p.ID)
                FROM {$wpdb->prefix}postmeta pm
                JOIN {$wpdb->prefix}posts p ON pm.post_id = p.ID
                WHERE pm.meta_key = '_select_club_id' AND pm.meta_value = %d
                AND p.post_status = 'publish' AND p.post_type = 'post'",
                $club_id
            )
        ),
        'draft' => $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(p.ID)
                FROM {$wpdb->prefix}postmeta pm
                JOIN {$wpdb->prefix}posts p ON pm.post_id = p.ID
                WHERE pm.meta_key = '_select_club_id' AND pm.meta_value = %d
                AND p.post_status = 'draft' AND p.post_type = 'post'",
                $club_id
            )
        ),
        'pending' => $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(p.ID)
                FROM {$wpdb->prefix}postmeta pm
                JOIN {$wpdb->prefix}posts p ON pm.post_id = p.ID
                WHERE pm.meta_key = '_select_club_id' AND pm.meta_value = %d
                AND p.post_status = 'pending' AND p.post_type = 'post'",
                $club_id
            )
        ),
        
    ];


        // Pagination setup
        $current_page = get_query_var('paged') ? get_query_var('paged') : 1;


        $posts_per_page = 20;
        $offset = ($current_page - 1) * $posts_per_page;

        
    // Handle bulk actions
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk-action']) && isset($_POST['post_ids'])) {
            $action = sanitize_text_field($_POST['bulk-action']);
            $post_ids = array_map('intval', $_POST['post_ids']);

            if ($action === 'delete') {
                foreach ($post_ids as $post_id) {
                    wp_delete_post($post_id, true); // Force delete the post
                }
                echo '<p>Selected posts have been deleted successfully.</p>';
            } elseif ($action === 'export') {
                global $wpdb;

                // Ensure no output is sent before headers
                if (headers_sent()) {
                    die(__('Headers already sent. Cannot generate CSV.', 'textdomain'));
                }

                // Clear all output buffers
                while (ob_get_level()) {
                    ob_end_clean();
                }

                // Set headers for CSV download
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="posts_export.csv"');
                header('Pragma: no-cache');
                header('Expires: 0');

                // Open output stream
                $output = fopen('php://output', 'w');

                // Write CSV header row
                fputcsv($output, ['ID', 'Title', 'Date', 'Status', 'Categories']);

                // Fetch and write post data
                foreach ($post_ids as $post_id) {
                    $post = get_post($post_id);

                    if ($post) {
                        // Fetch post categories
                        $categories = get_the_category($post_id);
                        $category_names = !empty($categories) ? implode(', ', wp_list_pluck($categories, 'name')) : 'None';

                        // Write post data to the CSV
                        fputcsv($output, [
                            $post->ID,
                            sanitize_text_field($post->post_title),
                            sanitize_text_field($post->post_date),
                            sanitize_text_field($post->post_status),
                            sanitize_text_field($category_names)
                        ]);
                    }
                }

                // Close output stream and terminate script
                fclose($output);
                exit;
            }
        }


            // Initialize filters
            $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
            $category_filter = isset($_GET['category']) ? sanitize_text_field($_GET['category']) : '';
            $search_term = isset($_GET['search_term']) ? sanitize_text_field($_GET['search_term']) : '';
            $month_filter = isset($_GET['month_filter']) ? sanitize_text_field($_GET['month_filter']) : '';

            // Build query with filters
            $query = "SELECT SQL_CALC_FOUND_ROWS p.ID, p.post_title, p.post_date, p.post_status, 
                GROUP_CONCAT(DISTINCT t.name SEPARATOR ', ') AS categories
          FROM {$wpdb->prefix}postmeta pm 
          JOIN {$wpdb->prefix}posts p ON pm.post_id = p.ID 
          LEFT JOIN {$wpdb->prefix}term_relationships tr ON p.ID = tr.object_id 
          LEFT JOIN {$wpdb->prefix}term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id 
          LEFT JOIN {$wpdb->prefix}terms t ON tt.term_id = t.term_id 
          WHERE pm.meta_key = '_select_club_id' AND pm.meta_value = %d 
          AND p.post_status IN ('publish', 'draft', 'pending')
          AND p.post_type = 'post' ";



            $params = [$club_id];

            if (!empty($status_filter)) {
                $query .= " AND p.post_status = %s ";
                $params[] = $status_filter;
            }

            if (!empty($category_filter)) {
                // Filter by term_id instead of name
                $query .= " AND t.term_id = %d ";
                $params[] = intval($category_filter);
            }

            if (!empty($search_term)) {
                $query .= " AND p.post_title LIKE %s ";
                $params[] = '%' . $search_term . '%';
            }

            if (!empty($month_filter)) {
                $query .= " AND MONTH(p.post_date) = %d ";
                $params[] = intval($month_filter);
            }
            

    // Correct calculation of offset
    $offset = ($current_page - 1) * $posts_per_page;

    // Finalize query with pagination
    $query .= " GROUP BY p.ID ORDER BY p.post_date DESC LIMIT %d OFFSET %d";
    $params[] = (int) $posts_per_page;
    $params[] = (int) $offset;

    // Log pagination details before executing query
    error_log("Current Page: " . $current_page);
    error_log("Offset: " . $offset);

    // Prepare and execute the query
    $posts = $wpdb->get_results($wpdb->prepare($query, ...$params));

    // Get total count of filtered posts for pagination
    $total_posts_query = "
        SELECT COUNT(DISTINCT p.ID) 
        FROM {$wpdb->prefix}posts p 
        JOIN {$wpdb->prefix}postmeta pm ON pm.post_id = p.ID 
        WHERE pm.meta_key = '_select_club_id' 
        AND pm.meta_value = %d 
        AND p.post_status IN ('publish', 'draft', 'pending') 
        AND p.post_type = 'post'";

    $total_posts = $wpdb->get_var($wpdb->prepare($total_posts_query, $club_id));

    // Calculate total pages
    $total_pages = ceil($total_posts / $posts_per_page);

    // Log final query after preparing it
    error_log("SQL Query: " . $wpdb->prepare($query, ...$params));


            // Render count filters
            $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';

            echo '<div class="status-filters count">';
            echo '<a href="' . esc_url(remove_query_arg('status')) . '" class="' . (empty($status_filter) ? 'current' : '') . '">All (' . intval($post_counts['all']) . ')</a> | ';
            echo '<a href="' . esc_url(add_query_arg('status', 'publish')) . '" class="' . ($status_filter === 'publish' ? 'current' : '') . '">Published (' . intval($post_counts['publish']) . ')</a> | ';
            echo '<a href="' . esc_url(add_query_arg('status', 'draft')) . '" class="' . ($status_filter === 'draft' ? 'current' : '') . '">Draft (' . intval($post_counts['draft']) . ')</a> | ';
            echo '<a href="' . esc_url(add_query_arg('status', 'pending')) . '" class="' . ($status_filter === 'pending' ? 'current' : '') . '">Pending (' . intval($post_counts['pending']) . ')</a> ';
            echo '</div>';


            // Render filters
            echo '<form method="get" class="end-filters">';
            echo '<input type="hidden" name="section" value="posts">';
            echo '
                <select id="status" name="status">
                    <option value="">Status</option>
                    <option value="publish" ' . selected($status_filter, 'publish', false) . '>Published</option>
                    <option value="draft" ' . selected($status_filter, 'draft', false) . '>Draft</option>
                    
                </select>';
            
                echo '
                <select id="category" name="category">
                    <option value="">Category</option>';
                    
            // Get the logged-in user's email
            $user_email = wp_get_current_user()->user_email;
            
            if (!empty($user_email)) {
                // Fetch the club ID for the logged-in user
                $club_data = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT club_id FROM {$wpdb->prefix}club_members WHERE user_email = %s LIMIT 1",
                        $user_email
                    )
                );
            
                if ($club_data) {
                    $club_id = $club_data->club_id;
            
                    // Fetch categories linked to the club_id
                    $categories = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT t.term_id, t.name AS taxonomy_name
                            FROM {$wpdb->prefix}terms AS t
                            INNER JOIN {$wpdb->prefix}term_taxonomy AS tt ON t.term_id = tt.term_id
                            INNER JOIN {$wpdb->prefix}termmeta AS tm ON t.term_id = tm.term_id
                            WHERE tt.taxonomy = 'category'
                            AND tm.meta_key = 'taxonomy_custom_dropdown'
                            AND tm.meta_value = %d",
                            $club_id
                        )
                    );
            
                    // Display categories in the dropdown
                    if (!empty($categories)) {
                        // Create a parent-child hierarchy
                        $hierarchy = [];
                        foreach ($categories as $category) {
                            $parent_id = intval(get_term_field('parent', $category->term_id, 'category'));
                            if (!isset($hierarchy[$parent_id])) {
                                $hierarchy[$parent_id] = [];
                            }
                            $hierarchy[$parent_id][] = $category;
                        }
                    
                        // Recursive function to render categories
                        function render_category_options($categories, $hierarchy, $parent_id = 0, $depth = 0, $selected_category = '') {
                            if (isset($hierarchy[$parent_id])) {
                                foreach ($hierarchy[$parent_id] as $category) {
                                    $padding = str_repeat('&nbsp;', $depth * 4); // Indentation
                                    echo '<option value="' . esc_attr($category->term_id) . '" ' . selected($selected_category, $category->term_id, false) . '>' . $padding . esc_html($category->taxonomy_name) . '</option>';
                                    render_category_options($categories, $hierarchy, $category->term_id, $depth + 1, $selected_category);
                                }
                            }
                        }
                    
                        // Render the hierarchy
                        render_category_options($categories, $hierarchy, 0, 0, $category_filter);
                    }
                    
                }
            }
            echo '</select>';
            
            
            echo '
                <input type="text" id="search_term" class="my-inputs" name="search_term" value="' . esc_attr($search_term) . '" placeholder="Search posts...">';
            
                echo '
                <select id="month_filter" name="month_filter" class="my-inputs">
                    <option value="">Select Month</option>';
                $months = [
                    '01' => 'January',
                    '02' => 'February',
                    '03' => 'March',
                    '04' => 'April',
                    '05' => 'May',
                    '06' => 'June',
                    '07' => 'July',
                    '08' => 'August',
                    '09' => 'September',
                    '10' => 'October',
                    '11' => 'November',
                    '12' => 'December',
                ];
                foreach ($months as $key => $value) {
                    echo '<option value="' . esc_attr($key) . '" ' . selected($key, $month_filter, false) . '>' . esc_html($value) . '</option>';
                }
                echo '</select>';
                
            
            echo '<button type="submit" class="filter-btn my-filters All-button">Filter</button>
                <a href="' . esc_url(remove_query_arg(['status', 'category', 'month_filter', 'search_term', 'paged'], add_query_arg('section', 'posts'))) . '"class="clear-filter">Clear Filters</a>';

            echo '</form>';
            

            // Render bulk actions and posts table
            echo '<form method="post">';
            echo '<div class="bulk-actions bulk-users end-filters">';
            echo '<select name="bulk-action">
                    <option value="">Bulk Actions</option>
                    <option value="delete">Delete</option>
                    <option value="export">Export as CSV</option>
                </select>';
            echo '<button type="submit" class="my-filters All-button">Apply Action</button>';
            echo '</div>';

            // Add Post Button
            echo '<button id="add-post-button"class="my-filters All-button" style=" border: none; cursor: pointer;">Add Post</button>';
            echo '<script>
            document.getElementById("add-post-button").addEventListener("click", function() {
                const url = new URL(window.location.href);
                url.searchParams.set("Add", "true");
                window.history.pushState({}, "", url);
                location.reload();
            });
            </script>';

            echo '<table class="managertable"id="posts-table">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="select-all-post"></th>
                            <th>Title</th>
                            <th>Categories</th>
                            <th>Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>';
            if (!empty($posts)) {
                foreach ($posts as $post) {
                    $status_styles = [
                        'publish' => 'background: #d4edda; color: #155724; border: 1px solid #c3e6cb;',
                        'draft' => 'background: #fff3cd; color: #856404; border: 1px solid #ffeeba;',
                        'pending' => 'background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;',
                       
                    ];
                    $badge_style = $status_styles[$post->post_status] ?? 'background: #e2e3e5; color: #818182; border: 1px solid #d6d8db;';
                    echo '<tr>
                    <td><input type="checkbox" name="post_ids[]" value="' . intval($post->ID) . '" /></td>
                    <td data-label="Title"><a href="?post_id=' . intval($post->ID) . '" style="color: #10487B;">' . esc_html($post->post_title) . '</a></td>
                    <td data-label="Catagories">' . esc_html($post->categories) . '</td>
                    <td data-label="Date">' . esc_html(date('d/m/Y', strtotime($post->post_date))) . '</td>

                    <td data-label="Status"><span class="badge" style="padding: 5px 10px; border-radius: 3px; ' . esc_attr($badge_style) . '">' . ($post->post_status === 'publish' ? 'Published' : ucfirst($post->post_status)) . '</span></td>

                </tr>';
            
                }
            } else {
                echo '<tr><td colspan="5">No posts found.</td></tr>';
            }
            echo '</tbody></table>';
            echo '</form>';

            // Render pagination
        if ($total_pages >= 1) {
            echo '<div class="pagination" style="text-align: center;">';

            // Previous Button
            if ($current_page > 1) {
                $prev_url = esc_url(add_query_arg(['paged' => max(1, $current_page - 1)]));
                $next_url = esc_url(add_query_arg(['paged' => min($total_pages, $current_page + 1)]));
                

                echo '<a href="' . esc_url($prev_url) . '" class="prev" style="margin-right: 5px;">Previous</a>';
            }

            $start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : '';
            $end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : '';

            // Page Links
            for ($i = 1; $i <= $total_pages; $i++) {
                $class = $i === $current_page ? 'current' : '';
                $url = add_query_arg([
                    'paged' => $i,
                    'status' => $status_filter,
                    'category' => $category_filter,
                    'search_term' => $search_term,
                    'start_date' => $start_date,
                    'end_date' => $end_date
                ]);
                echo '<a href="' . esc_url($url) . '" class="' . esc_attr($class) . '" style="margin-right: 5px;">' . esc_html($i) . '</a>';
            }

            // Next Button
            if ($current_page < $total_pages) {
                $next_url = add_query_arg([
                    'paged' => $current_page + 1,
                    'status' => $status_filter,
                    'category' => $category_filter,
                    'search_term' => $search_term,
                    'start_date' => $start_date,
                    'end_date' => $end_date
                ]);
                echo '<a href="' . esc_url($next_url) . '" class="next" style="margin-left: 5px;">Next</a>';
            }

            echo '</div>';
        }


            // JavaScript for "Select All" functionality
            echo '<script>
            document.getElementById("select-all-post").addEventListener("change", function() {
                const checkboxes = document.querySelectorAll("input[name=\'post_ids[]\']");
                checkboxes.forEach(cb => cb.checked = this.checked);
            });
            </script>';
}



function render_edit_post_form() {
    global $wpdb;

    // Get the post ID from the URL
    $post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;

    if (!$post_id) {
        echo '<p>Invalid post ID.</p>';
        return;
    }

    // Fetch the post
    $post = get_post($post_id);

    if (!$post) {
        echo '<p>Post not found.</p>';
        return;
    }

    // Get logged-in user's email
    $user_email = wp_get_current_user()->user_email;

    if (empty($user_email)) {
        echo '<p>No user is logged in.</p>';
        return;
    }

    $selected_categories = wp_get_post_categories($post_id);
    $render_categories = get_club_categories($selected_categories);


    // Fetch the featured image
    $featured_image = get_the_post_thumbnail_url($post_id, 'full');

   // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_post_nonce']) && wp_verify_nonce($_POST['edit_post_nonce'], 'edit_post')) {
        $post_status = isset($_POST['post_status']) && in_array($_POST['post_status'], ['publish', 'draft'])
            ? sanitize_text_field($_POST['post_status'])
            : 'draft';

        $post_data = [
            'ID'           => $post_id,
            'post_title'   => sanitize_text_field($_POST['post_title']),
            'post_excerpt' => sanitize_text_field($_POST['post_excerpt']),
            'post_content' => wp_kses_post($_POST['post_content']),
            'post_status'  => $post_status,
        ];

        // Update the post
        wp_update_post($post_data);

        // Update categories
        $new_categories = isset($_POST['categories']) ? array_map('intval', $_POST['categories']) : [];
        wp_set_post_categories($post_id, $new_categories);

        // Update the featured image
        if (!empty($_POST['featured_image'])) {
            $attachment_id = intval($_POST['featured_image']);
            set_post_thumbnail($post_id, $attachment_id);
        }

        // Redirect to refresh the page after updating the post
        wp_redirect(add_query_arg(['post_updated' => 'success'], $_SERVER['REQUEST_URI']));
        exit;
    }

    // Display success message after page refresh
    if (isset($_GET['post_updated']) && $_GET['post_updated'] === 'success') {
        echo '<p class="success-message">Post updated successfully!</p>';
    }


    // Output the form
    ?>
    <form method="post" id="edit-post-form">
        <?php wp_nonce_field('edit_post', 'edit_post_nonce'); ?>


        <div class="event-row">
            <div class="event-label">
                <label for="post_title">Post Title</label>
                <input type="text" id="post_title" name="post_title" value="<?php echo esc_attr($post->post_title); ?>" required>
            </div>
            <div class="event-label">
                <label for="post_status">Post Status</label>
                <select id="post_status" name="post_status">
                    <option value="publish" <?php echo $post->post_status === 'publish' ? 'selected' : ''; ?>>Publish</option>
                    <option value="draft" <?php echo $post->post_status === 'draft' ? 'selected' : ''; ?>>Save Draft</option>
                </select>
            </div>
        </div>

        <div class="event-row">
           

            <div class="event-label">
                <label for="categories">Categories</label>
                <select id="categories" name="categories[]" multiple>
                <?php if ($render_categories) $render_categories(); ?>

                </select>
            </div>

            <div class="event-label">
                <label for="post_excerpt">Post Excerpt</label>
                <textarea id="post_excerpt" name="post_excerpt" rows="3"><?php echo esc_textarea($post->post_excerpt); ?></textarea>
            </div>
        </div>

        <div class="wysiwyg-wrapper">
            <div class="wysiwyg-column1">
                
                <?php
                wp_editor(
                    $post->post_content,
                    'post_content',
                    [
                        'textarea_name' => 'post_content',
                        'textarea_rows' => 10,
                        'media_buttons' => true, // Enable media button for adding images, gallery, etc.

                    ]
                );
                ?>
            </div>

            <div class="event-label wysiwyg-column2">
                <label for="featured_image">Featured Image</label>
                <input type="hidden" id="featured_image" name="featured_image" value="<?php echo get_post_thumbnail_id($post_id); ?>">
                <div style="width: 100%; height: 210px; border: 1px dashed #ccc; display: flex; align-items: center; justify-content: center; position: relative; background-color: #f9f9f9;">
                    <?php if ($featured_image): ?>
                        <img id="featured_image_preview" src="<?php echo esc_url($featured_image); ?>" alt="Featured Image Preview" style="width: 200px; height: 200px; border-radius: 4px;">
                    <?php else: ?>
                        <span id="featured_image_placeholder">No featured image selected</span>
                    <?php endif; ?>
                    <button type="button" id="choose_featured_image" style="position: absolute; bottom: -40px; background-color: #0073aa; color: white; border: none; padding: 10px 15px; cursor: pointer; border-radius: 4px;">Change Image</button>
                </div>
            </div>
        </div>

       

        <div class="form-actions">
            <button type="submit" class="save-btn All-button">Save Changes</button>
            <button type="button" class="cancel-btn" id="cancel_button">Cancel</button>
        </div>
    </form>

    <script>
        jQuery(document).ready(function($) {
            var mediaUploader;

            $('#choose_featured_image').on('click', function(e) {
                e.preventDefault();

                // If the uploader object has already been created, reopen it.
                if (mediaUploader) {
                    mediaUploader.open();
                    return;
                }

                // Create the media uploader.
                mediaUploader = wp.media({
                    title: 'Choose Featured Image',
                    button: {
                        text: 'Set Featured Image'
                    },
                    multiple: false
                });

                // When an image is selected, run a callback.
                mediaUploader.on('select', function() {
                    var attachment = mediaUploader.state().get('selection').first().toJSON();
                    $('#featured_image').val(attachment.id); // Set the hidden input value
                    $('#featured_image_preview').attr('src', attachment.url); // Update the preview image
                    $('#featured_image_placeholder').hide(); // Hide placeholder text if present
                });

                // Open the uploader dialog.
                mediaUploader.open();
            });
        });
    </script>
    <?php
}


?>



<script>
jQuery(document).ready(function($) {
    var mediaUploader;

    $('#choose_featured_image').on('click', function(e) {
        e.preventDefault();

        if (mediaUploader) {
            mediaUploader.open();
            return;
        }

        mediaUploader = wp.media({
            title: 'Choose Featured Image',
            button: {
                text: 'Set Featured Image'
            },
            multiple: false
        });

        mediaUploader.on('select', function() {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            $('#featured_image').val(attachment.id);
            $('#featured_image_preview').attr('src', attachment.url).show();
            $('#featured_image_placeholder').hide();
        });

        mediaUploader.open();
    });
});
</script>
