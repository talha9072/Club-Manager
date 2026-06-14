<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
// Check if 'add-product' is present in the URL
if (isset($_GET['add-product']) && $_GET['add-product'] === 'true') {
    // If 'add-product' exists and is true, call the add_woo_product function
    echo '<h3>Add Product</h3>';
    add_woo_product();
} else if (isset($_GET['product_id']) && !empty($_GET['product_id'])) {
    // If 'product_id' exists, call the product_edits function
    product_edits();
} else {
    // If neither 'add-product' nor 'product_id' exist, call the display_club_products function
    echo '<div class="admin-switch">';
    echo '<h2 class="manager-h2">Products</h2>';
    // Add the button below the heading
    echo '<a href="' . esc_url(admin_url('edit.php?post_type=product')) . '" class="button All-button" style="text-decoration: none; border: none;">
       Advanced
   </a>';
    echo '</div>';
    display_club_products();
}

function get_user_club_info() {
    global $wpdb;

    // Get the current user's email
    $current_user = wp_get_current_user();
    if (!$current_user->exists()) {
        return false; // Return false if no user is logged in
    }

    $user_email = $current_user->user_email;

    // Fetch the club info for the logged-in user
    $club_info = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}club_members WHERE user_email = %s LIMIT 1",
            $user_email
        )
    );

    return $club_info ? $club_info : false; // Return the club info or false if not found
}



function get_products_by_club($club_name) {
    global $wpdb;

    $query = "
        SELECT DISTINCT p.ID, p.post_title, 
               wc_price.meta_value AS price, 
               wc_sku.meta_value AS sku, 
               wc_stock.meta_value AS stock_status
        FROM {$wpdb->prefix}posts p
        INNER JOIN {$wpdb->prefix}postmeta pm ON p.ID = pm.post_id
        LEFT JOIN {$wpdb->prefix}postmeta wc_price ON p.ID = wc_price.post_id AND wc_price.meta_key = '_price'
        LEFT JOIN {$wpdb->prefix}postmeta wc_sku ON p.ID = wc_sku.post_id AND wc_sku.meta_key = '_sku'
        LEFT JOIN {$wpdb->prefix}postmeta wc_stock ON p.ID = wc_stock.post_id AND wc_stock.meta_key = '_stock_status'
        
        -- Join categories and group them together per product
        LEFT JOIN {$wpdb->prefix}term_relationships tr ON p.ID = tr.object_id
        LEFT JOIN {$wpdb->prefix}term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
        LEFT JOIN {$wpdb->prefix}terms t ON tt.term_id = t.term_id

        WHERE pm.meta_key = '_select_club_name'
        AND pm.meta_value = %s
        AND p.post_type = 'product'
        AND p.post_parent = 0  
        AND p.post_status != 'trash'

        -- Exclude products with 'Ticket' or 'FreeTicket' category
        AND p.ID NOT IN (
            SELECT tr2.object_id
            FROM {$wpdb->prefix}term_relationships tr2
            INNER JOIN {$wpdb->prefix}term_taxonomy tt2 ON tr2.term_taxonomy_id = tt2.term_taxonomy_id
            INNER JOIN {$wpdb->prefix}terms t2 ON tt2.term_id = t2.term_id
            WHERE t2.name IN ('Ticket', 'FreeTicket')
        )
    ";

    $params = [$club_name];

    // **Filter by Product Type**
    if (!empty($_GET['product_type'])) {
        $query .= " AND EXISTS (
            SELECT 1 FROM {$wpdb->prefix}term_relationships tr3
            JOIN {$wpdb->prefix}term_taxonomy tt3 ON tr3.term_taxonomy_id = tt3.term_taxonomy_id
            JOIN {$wpdb->prefix}terms t3 ON tt3.term_id = t3.term_id
            WHERE tr3.object_id = p.ID AND tt3.taxonomy = 'product_type' AND t3.slug = %s
        )";
        $params[] = sanitize_text_field($_GET['product_type']);
    }

    // **Filter by Stock Status**
    if (!empty($_GET['stock_status'])) {
        $stock_status = sanitize_text_field($_GET['stock_status']);
        if ($stock_status === 'in_stock') {
            $query .= " AND wc_stock.meta_value = 'instock'";
        } elseif ($stock_status === 'out_of_stock') {
            $query .= " AND wc_stock.meta_value = 'outofstock'";
        } elseif ($stock_status === 'low_stock') {
            $query .= " AND wc_stock.meta_value = 'onbackorder'";
        }
    }

    // **Order by Latest First**
    $query .= " ORDER BY p.post_date DESC";

    return $wpdb->get_results($wpdb->prepare($query, $params));
}







function get_product_categories($product_id) {
    global $wpdb;

    $categories = $wpdb->get_col(
        $wpdb->prepare("
            SELECT t.name
            FROM {$wpdb->prefix}terms t
            INNER JOIN {$wpdb->prefix}term_taxonomy tt ON t.term_id = tt.term_id
            INNER JOIN {$wpdb->prefix}term_relationships tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
            WHERE tr.object_id = %d
            AND tt.taxonomy = 'product_cat'
        ", $product_id)
    );

    return implode(', ', (array) $categories);
}

function get_product_type($product_id) {
    static $product_type_cache = [];
    if (isset($product_type_cache[$product_id])) {
        return $product_type_cache[$product_id];
    }

    global $wpdb;

    $product_type = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT t.slug
             FROM {$wpdb->prefix}terms t
             INNER JOIN {$wpdb->prefix}term_taxonomy tt ON t.term_id = tt.term_id
             INNER JOIN {$wpdb->prefix}term_relationships tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
             WHERE tr.object_id = %d AND tt.taxonomy = 'product_type'",
            $product_id
        )
    );

    $product_type_cache[$product_id] = $product_type ? esc_html($product_type) : 'Unknown';
    return $product_type_cache[$product_id];
}



function get_filtered_categories_by_club() {
    global $wpdb;
    $current_user = wp_get_current_user();
    
    // Fetch logged-in user's club info
    $user_club_info = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT club_id FROM {$wpdb->prefix}club_members WHERE user_email = %s",
            $current_user->user_email
        )
    );

    if (!$user_club_info) {
        return []; // No club info found, return empty array
    }

    $user_club_id = $user_club_info->club_id;

    // Fetch categories matching the user's club ID
    $categories = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT t.term_id, t.name, tm.meta_value AS club_id
             FROM {$wpdb->prefix}terms t
             JOIN {$wpdb->prefix}term_taxonomy tt ON t.term_id = tt.term_id
             JOIN {$wpdb->prefix}termmeta tm ON t.term_id = tm.term_id
             WHERE tt.taxonomy = 'product_cat'
             AND tm.meta_key = 'taxonomy_custom_dropdown'
             AND tm.meta_value = %d",
            $user_club_id
        )
    );

    return $categories; // Returns the filtered categories as an array
}



function get_variable_product_price_range($product_id) {
    global $wpdb;

    $prices = $wpdb->get_col($wpdb->prepare("
        SELECT DISTINCT pm.meta_value 
        FROM {$wpdb->prefix}postmeta pm
        INNER JOIN {$wpdb->prefix}posts p ON pm.post_id = p.ID
        WHERE p.post_parent = %d
        AND p.post_type = 'product_variation'
        AND pm.meta_key = '_price'
        AND pm.meta_value IS NOT NULL
        AND pm.meta_value != ''
    ", $product_id));

    // Convert all prices to float
    $prices = array_map('floatval', $prices);

    if (empty($prices)) {
        return 'Price not available';
    }

    $min_price = min($prices);
    $max_price = max($prices);

    if ($min_price == $max_price) {
        return 'R' . number_format($min_price, 2, ',', ' ');
    } else {
        return 'R' . number_format($min_price, 2, ',', ' ') . ' – R' . number_format($max_price, 2, ',', ' ');
    }
}

// Function to get filtered product categories with slugs
function get_filtered_category_slugs_by_club() {
    global $wpdb;
    $current_user = wp_get_current_user();

    // Get the user's club ID
    $user_club_info = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT club_id FROM {$wpdb->prefix}club_members WHERE user_email = %s",
            $current_user->user_email
        )
    );

    if (!$user_club_info) {
        return []; // No club info found
    }

    $user_club_id = $user_club_info->club_id;

    // Fetch categories based on club ID with slugs
    $categories = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT t.term_id, t.name, t.slug, tm.meta_value AS club_id
             FROM {$wpdb->prefix}terms t
             JOIN {$wpdb->prefix}term_taxonomy tt ON t.term_id = tt.term_id
             JOIN {$wpdb->prefix}termmeta tm ON t.term_id = tm.term_id
             WHERE tt.taxonomy = 'product_cat'
             AND tm.meta_key = 'taxonomy_custom_dropdown'
             AND tm.meta_value = %d",
            $user_club_id
        )
    );

    return $categories; // Returns an array with term_id, name, and slug
}



function display_club_products() {
    $club_info = get_user_club_info();

    if (!$club_info) {
        echo 'No club information found for this user.';
        return;
    }

    $club_name = $club_info->club_name;
    $products = get_products_by_club($club_name);

    // Fetch all product categories
    $categories = get_terms([
        'taxonomy' => 'product_cat',
        'hide_empty' => true,
    ]);

    // Calculate stock counts
    $stock_counts = [
        'all' => count($products),
        'instock' => count(array_filter($products, function ($product) {
            return get_post_meta($product->ID, '_stock_status', true) === 'instock';
        })),
        'outofstock' => count(array_filter($products, function ($product) {
            return get_post_meta($product->ID, '_stock_status', true) === 'outofstock';
        })),
    ];


        // Get selected filters from GET request
        $selected_category = isset($_GET['category']) ? sanitize_text_field($_GET['category']) : '';
        $selected_stock_status = isset($_GET['stock_status']) ? sanitize_text_field($_GET['stock_status']) : '';
        $selected_product_type = isset($_GET['product_type']) ? sanitize_text_field($_GET['product_type']) : '';
        // Get search query
    $search_query = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';



            // Filter products by category
            if ($selected_category) {
                $products = array_filter($products, function ($product) use ($selected_category) {
                    $product_categories = wp_get_post_terms($product->ID, 'product_cat', ['fields' => 'slugs']);
                    return in_array($selected_category, $product_categories);
                });
            }

            // Filter products by stock status
            if ($selected_stock_status) {
                $products = array_filter($products, function ($product) use ($selected_stock_status) {
                    $stock_status = get_post_meta($product->ID, '_stock_status', true);
                    return $stock_status === $selected_stock_status;
                });
            }
                    
        // Filter products by category
    if ($selected_category) {
        $products = array_filter($products, function ($product) use ($selected_category) {
            $product_categories = wp_get_post_terms($product->ID, 'product_cat', ['fields' => 'slugs']);
            return in_array($selected_category, $product_categories);
        });
    }


    // Filter products by search query (title-based search)
    if (!empty($search_query)) {
        $products = array_filter($products, function ($product) use ($search_query) {
            return stripos($product->post_title, $search_query) !== false;
        });
    }



          

        // Filter products by product type
        if ($selected_product_type) {
            $products = array_filter($products, function ($product) use ($selected_product_type) {
                return get_product_type($product->ID) === $selected_product_type;
            });
        }


        // Pagination variables
        global $wp_query;
        $paged = isset($wp_query->query_vars['paged']) ? intval($wp_query->query_vars['paged']) : 1;
        $current_page = $paged > 0 ? $paged : 1;

        $items_per_page = 20;
        $total_items = count($products);
        $total_pages = ceil($total_items / $items_per_page);
        $offset = ($current_page - 1) * $items_per_page;

        // Slice the products array for pagination
        $products = array_slice($products, $offset, $items_per_page);


    // Handle bulk actions
    if (!empty($_POST['bulk-action']) && !empty($_POST['product_ids'])) {
        $bulk_action = sanitize_text_field($_POST['bulk-action']);
        $selected_ids = array_map('intval', $_POST['product_ids']);

        if ($bulk_action === 'delete') {
            foreach ($selected_ids as $product_id) {
                // Delete the product
                wp_delete_post($product_id, true);
            }
            echo '<p>Selected products have been deleted successfully.</p>';
        } elseif ($bulk_action === 'export') {
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
            header('Content-Disposition: attachment; filename="products.csv"');
            header('Pragma: no-cache');
            header('Expires: 0');

            // Open output stream
            $output = fopen('php://output', 'w');

            // Add CSV column headers
            fputcsv($output, ['Product Name', 'Categories', 'Price', 'SKU', 'Stock Status']);

            // Fetch and write product details
            foreach ($selected_ids as $product_id) {
                $product = get_post($product_id);

                if ($product && $product->post_type === 'product') {
                    // Fetch product details
                    $price = sanitize_text_field(get_post_meta($product_id, '_price', true));
                    $sku = sanitize_text_field(get_post_meta($product_id, '_sku', true));
                    $stock_status = sanitize_text_field(get_post_meta($product_id, '_stock_status', true));

                    // Fetch product categories as a comma-separated list
                    $categories = get_the_term_list($product_id, 'product_cat', '', ', ', '') ?: 'None';
                    $categories_clean = wp_strip_all_tags($categories);

                    // Write product data to the CSV
                    fputcsv($output, [
                        sanitize_text_field($product->post_title),
                        $categories_clean,
                        $price,
                        $sku,
                        $stock_status
                    ]);
                }
            }

            // Close output stream and terminate script
            fclose($output);
            exit;
        }
    }


        // Render stock count filters
    echo '<div class="stock-status-filters count" style="margin-bottom: 15px;">';
    echo '<a href="' . esc_url(remove_query_arg('stock_status')) . '" class="' . (empty($selected_stock_status) ? 'current' : '') . '" style="margin-right: 10px;">All (' . intval($stock_counts['all']) . ')</a>';
    echo '<a href="' . esc_url(add_query_arg('stock_status', 'instock')) . '" class="' . ($selected_stock_status === 'instock' ? 'current' : '') . '" style="margin-right: 10px;">In Stock (' . intval($stock_counts['instock']) . ')</a>';
    echo '<a href="' . esc_url(add_query_arg('stock_status', 'outofstock')) . '" class="' . ($selected_stock_status === 'outofstock' ? 'current' : '') . '" style="margin-right: 10px;">Out of Stock (' . intval($stock_counts['outofstock']) . ')</a>';
    echo '</div>';

    // Add Product Button
    echo '<button type="button" onclick="addProductToUrl()"class="my-filters All-button add-product-button">Add Product</button>';

        // Filter Form
    echo '<form id="filter-form"class="end-filters" method="get">';

    // Preserve Section Parameter
    echo '<input type="hidden" name="section" value="products">';

    $categories = get_filtered_categories_by_club();

 

    // Stock Status Filter
    echo '<select name="stock_status" id="stock-status">';
    echo '<option value="">Stock Status</option>';
    echo '<option value="instock" ' . selected($selected_stock_status, 'instock', false) . '>In Stock</option>';
    echo '<option value="outofstock" ' . selected($selected_stock_status, 'outofstock', false) . '>Out of Stock</option>';
    echo '</select>';

    

    // Product Type Filter
    echo '<select name="product_type" id="product-type">';
    echo '<option value="">Product Type</option>';
    echo '<option value="simple" ' . selected($selected_product_type, 'simple', false) . '>Simple</option>';
    echo '<option value="subscription" ' . selected($selected_product_type, 'subscription', false) . '>Subscription</option>';
    echo '<option value="variable" ' . selected($selected_product_type, 'variable', false) . '>Variable</option>';
    echo '</select>';


    // Category Filter
    echo '<select name="category" id="category">';
    echo '<option value="">Select Category</option>';
    $categories = get_filtered_category_slugs_by_club();
    foreach ($categories as $category) {
        $selected = ($selected_category === $category->slug) ? 'selected' : '';
        echo '<option value="' . esc_attr($category->slug) . '" ' . $selected . '>' . esc_html($category->name) . '</option>';
    }
    echo '</select>';


    // Search Filter
    echo '<div class="input-icon">';
    echo '<input type="text" name="search" placeholder="Search Product..." value="' . esc_attr($search_query) . '" >';
    echo '<span class="icon"><i class="fa fa-search"></i></span> ';
    echo '</div>';

        // Apply Filters Button
        echo '<button type="submit"class="my-filters All-button">Apply</button>';

        // Clear Filters Button (Preserve Section Parameter)
        echo '<button type="button"class="clear-filter" onclick="window.location.href=\'?section=products\';">Clear Filters</button>';
        echo '</form>';


            if (empty($products)) {
                echo 'No products found for this club.';
                return;
            }

            // Bulk Actions and Products Table
            echo '<form id="bulk-form" method="post">';
            echo '<div class="end-filters">';
            echo '<select name="bulk-action">';
            echo '<option value="">Bulk Actions</option>';
            echo '<option value="delete">Delete</option>';
            echo '<option value="export">Export as CSV</option>';
            echo '</select>';
            echo '<button type="submit" class="my-filters All-button">Apply</button>';
            echo '</div>';

            echo '<table class="wp-list-table widefat fixed striped managertable" id="products-table">';
            echo '<thead><tr>
            <th><input type="checkbox" id="select-all1"></th>
            <th>Product Name</th>
            <th>Categories</th>
            <th>Price</th>
            <th>SKU</th>
            <th>Stock Status</th>
            <th>Product Type</th>
        </tr></thead>';


            foreach ($products as $product) {
                $product_id = $product->ID;
                $categories = get_product_categories($product_id);
                $price = get_post_meta($product_id, '_price', true);
                $subscription_price = get_post_meta($product_id, '_subscription_price', true);
                $subscription_period = get_post_meta($product_id, '_subscription_period', true);
                $subscription_interval = get_post_meta($product_id, '_subscription_period_interval', true);

                        // Determine price to display
                $product_type = get_product_type($product_id); // Get product type
                if ($product_type === 'variable') {
                    // Fetch price range for variable products
                    $display_price = get_variable_product_price_range($product_id);
                } else {
                    // Regular price logic for simple/subscription products
                    $regular_price = get_post_meta($product_id, '_regular_price', true);
                    $sale_price = get_post_meta($product_id, '_sale_price', true);
                    $subscription_price = get_post_meta($product_id, '_subscription_price', true);
                    $subscription_interval = get_post_meta($product_id, '_subscription_period_interval', true);
                    $subscription_period = get_post_meta($product_id, '_subscription_period', true);
                    $sign_up_fee = get_post_meta($product_id, '_subscription_sign_up_fee', true);

                    if (!empty($subscription_price)) {
                        // Subscription product price
                        $display_price = 'R' . number_format((float)$subscription_price, 2, ',', ' ') 
                                    . ' for ' . $subscription_interval . ' ' . $subscription_period;
                        if (!empty($sign_up_fee)) {
                            $display_price .= ' and a R' . number_format((float)$sign_up_fee, 2, ',', ' ') . ' sign-up fee';
                        }
                    } else if (!empty($sale_price)) {
                        // Simple product sale price
                        $display_price = 'R' . number_format((float)$sale_price, 2, ',', ' ');
                    } else if (!empty($regular_price)) {
                        // Simple product regular price
                        $display_price = 'R' . number_format((float)$regular_price, 2, ',', ' ');
                    } else {
                        // If no price is found, use `_price` if available
                        $fallback_price = get_post_meta($product_id, '_price', true);
                        if (!empty($fallback_price)) {
                            $display_price = 'R' . number_format((float)$fallback_price, 2, ',', ' ');
                        } else {
                            // If `_price` is also empty, show "Price not available"
                            $display_price = 'R0,00';
                        }
                    }
                }

                        

                

                $sku = get_post_meta($product_id, '_sku', true);
                $stock_status = get_post_meta($product_id, '_stock_status', true);
                $stock_quantity = get_post_meta($product_id, '_stock', true); // Fetch stock quantity

                // Determine stock display with count if available
                if ($stock_status === 'instock') {
                    $stock_text = "In Stock";
                    $stock_count = (!empty($stock_quantity) && is_numeric($stock_quantity)) ? " ($stock_quantity)" : "";
                    $stock_color = "style='color: green; font-weight: bold;'"; // Green color for in stock
                } elseif ($stock_status === 'outofstock') {
                    $stock_text = "Out Of Stock";
                    $stock_color = "style='color: red; font-weight: bold;'"; // Red color for out of stock
                    $stock_count = "";
                } else {
                    $stock_text = "Unknown";
                    $stock_color = ""; // No color for unknown status
                    $stock_count = "";
                }

                // Final stock status display
                $display_stock_status = "<span $stock_color>$stock_text$stock_count</span>";


                echo '<tr>';
                echo '<td><input type="checkbox" name="product_ids[]" value="' . intval($product_id) . '"></td>';
                echo '<td data-label="Product Name"><a href="' . esc_url(add_query_arg('product_id', $product_id, get_permalink())) . '">' . esc_html($product->post_title) . '</a></td>';
                echo '<td data-label="Categories">' . esc_html($categories) . '</td>';
                echo '<td data-label="Price">' . esc_html($display_price) . '</td>';
                echo '<td data-label="SKU">' . esc_html($sku) . '</td>';
                $product_type = get_product_type($product_id); // Get product type
                echo '<td data-label="Stock Status">' . $display_stock_status . '</td>';
                echo '<td data-label="Product Type">' . esc_html(ucwords(str_replace('-', ' ', $product_type))) . '</td>'; 

                echo '</tr>';
            }
            echo '</tbody>';
            echo '</table>';
            if ($total_pages >= 1) {
                echo '<div class="pagination" style="margin-top: 20px; text-align: center;">';
            
                // Previous Button
                if ($current_page > 1) {
                    echo '<a href="' . esc_url(add_query_arg('paged', $current_page - 1)) . '" 
                    style="margin: 0 5px; padding: 5px 10px; border: 1px solid #ddd; text-decoration: none; color: #333;">Previous</a>';
                }
            
                // Page Numbers
                for ($i = 1; $i <= $total_pages; $i++) {
                    echo '<a href="' . esc_url(add_query_arg('paged', $i)) . '" 
                    class="' . ($i === $current_page ? 'current' : '') . '" 
                    style="margin: 0 5px; padding: 5px 10px; border: 1px solid #ddd; text-decoration: none; ' 
                    . ($i === $current_page ? 'background: #10487B; color: #fff;' : 'color: #333;') . '">' . $i . '</a>';
                }
            
                // Next Button
                if ($current_page < $total_pages) {
                    echo '<a href="' . esc_url(add_query_arg('paged', $current_page + 1)) . '" 
                    style="margin: 0 5px; padding: 5px 10px; border: 1px solid #ddd; text-decoration: none; color: #333;">Next</a>';
                }
            
                echo '</div>';
            }
            

                
            echo '</form>';

            ?>
        <script>
        document.getElementById('select-all1').addEventListener('change', function(e) {
            var checkboxes = document.querySelectorAll('input[name="product_ids[]"]');
            var isChecked = this.checked;
            checkboxes.forEach(function(checkbox) {
                checkbox.checked = isChecked;
            });
        });

        function addProductToUrl() {
            const url = new URL(window.location.href);

            // Preserve the existing 'section' parameter
            const section = url.searchParams.get('section');
            if (!section) {
                console.error('Section parameter is missing!');
                return;
            }

            // Remove other query parameters except 'section'
            url.searchParams.forEach((value, key) => {
                if (key !== 'section') {
                    url.searchParams.delete(key);
                }
            });

            // Add 'add-product=true' to the URL
            url.searchParams.set('add-product', 'true');

            // Update the URL and reload the page
            window.history.pushState({}, '', url);
            location.reload();
        }

        </script>
        <?php
}





function product_edits() {
    if (!isset($_GET['product_id']) || empty($_GET['product_id'])) {
        echo 'Product ID not provided.';
        return;
    }

    global $wpdb;
    $product_id = intval($_GET['product_id']);

    // Fetch existing product details
    $categories = wp_get_object_terms($product_id, 'product_cat', ['fields' => 'names']);

    $product_title = get_post_field('post_title', $product_id);
    $short_description = get_post_field('post_excerpt', $product_id);
    $long_description = get_post_field('post_content', $product_id);

    $regular_price = get_post_meta($product_id, '_regular_price', true);
    $sale_price = get_post_meta($product_id, '_sale_price', true);
    $effective_price = get_post_meta($product_id, '_price', true);

    $subscription_price = get_post_meta($product_id, '_subscription_price', true);
    $subscription_period = get_post_meta($product_id, '_subscription_period', true);
    $subscription_interval = get_post_meta($product_id, '_subscription_period_interval', true);
    $subscription_sign_up_fee = get_post_meta($product_id, '_subscription_sign_up_fee', true);

    $sku = get_post_meta($product_id, '_sku', true);

    $stock_quantity = get_post_meta($product_id, '_stock', true);
    $product_type = get_product_type($product_id); // Get the product type (simple, variable, etc.)
    $is_variable_product = ($product_type === 'variable'); // Check if it's a variable product


    $featured_image_id = get_post_meta($product_id, '_thumbnail_id', true);
    $featured_image_url = $featured_image_id ? wp_get_attachment_url($featured_image_id) : '';

    $gallery_image_ids = get_post_meta($product_id, '_product_image_gallery', true);
    $gallery_image_urls = [];
    if ($gallery_image_ids) {
        $gallery_image_ids_array = explode(',', $gallery_image_ids);
        foreach ($gallery_image_ids_array as $id) {
            $gallery_image_urls[$id] = wp_get_attachment_url($id);
        }
    }

    $is_membership_product = in_array('Membership', $categories, true);

    // Fetch filtered categories for the logged-in user's club
    $filtered_categories = get_filtered_categories_by_club();

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $updated_title = sanitize_text_field($_POST['product_title']);
        $updated_short_description = sanitize_text_field($_POST['short_description']);
        $updated_regular_price = sanitize_text_field($_POST['regular_price']);
        $updated_sale_price = sanitize_text_field($_POST['sale_price']);
        $updated_sku = sanitize_text_field($_POST['sku']);
        $updated_stock_quantity = isset($_POST['stock_quantity']) ? intval($_POST['stock_quantity']) : null;
        $updated_subscription_price = sanitize_text_field($_POST['subscription_price']);
        $updated_subscription_period = sanitize_text_field($_POST['subscription_period']);
        $updated_subscription_interval = sanitize_text_field($_POST['subscription_interval']);
        $updated_subscription_sign_up_fee = sanitize_text_field($_POST['subscription_sign_up_fee']);
        $updated_long_description = wp_kses_post($_POST['long_description']);
        $updated_category_ids = isset($_POST['categories']) ? array_map('intval', $_POST['categories']) : [];

        // Update product details in the database
        wp_update_post([
            'ID' => $product_id,
            'post_title' => $updated_title,
            'post_excerpt' => $updated_short_description,
            'post_content' => $updated_long_description,
        ]);

        update_post_meta($product_id, '_regular_price', $updated_regular_price);
        update_post_meta($product_id, '_sale_price', $updated_sale_price);
        update_post_meta($product_id, '_sku', $updated_sku);
        if ($is_variable_product && $updated_stock_quantity !== null) {
            update_post_meta($product_id, '_stock', $updated_stock_quantity);
        }        
        update_post_meta($product_id, '_subscription_price', $updated_subscription_price);
        update_post_meta($product_id, '_subscription_period', $updated_subscription_period);
        update_post_meta($product_id, '_subscription_interval', $updated_subscription_interval);
        update_post_meta($product_id, '_subscription_sign_up_fee', $updated_subscription_sign_up_fee);

        // Set the `_price` to match `_sale_price` or `_regular_price`
        $updated_effective_price = !empty($updated_sale_price) ? $updated_sale_price : $updated_regular_price;
        update_post_meta($product_id, '_price', $updated_effective_price);

        // Update categories (prevent changing "Membership" category if it's a membership product)
        if (!$is_membership_product) {
            wp_set_object_terms($product_id, $updated_category_ids, 'product_cat');
        }

        // Update featured image
        if (!empty($_POST['featured_image_id'])) {
            update_post_meta($product_id, '_thumbnail_id', intval($_POST['featured_image_id']));
        }

        // Update gallery images (only if not a membership product)
        if (!$is_membership_product && !empty($_POST['gallery_image_ids'])) {
            update_post_meta($product_id, '_product_image_gallery', sanitize_text_field($_POST['gallery_image_ids']));
        }

        echo '<p>Product updated successfully!</p>';

        // Remove the parameter from the URL
        echo '<script>
            const url = new URL(window.location.href);
            url.searchParams.delete("product_id");
            window.location.href = url.toString();
        </script>';
        return;
    }

    // Enqueue WordPress Media Library
    wp_enqueue_media();

    // Display the form
    ?>
    <form method="post">
        <?php if ($is_membership_product): ?>
            <h2>Membership Product</h2>
        <?php endif; ?>

       <div class="event-row">
        <div class="event-label">
                <label>Product Title:</label>
                <input type="text" name="product_title" value="<?php echo esc_attr($product_title); ?>"><br>
            </div>

            <div class="event-label">
                <label>Short Description:</label>
                <textarea name="short_description"><?php echo esc_textarea($short_description); ?></textarea><br>
            </div>
       </div>

         <?php if (!$is_variable_product): ?>
         <div class="event-row">
            <div class="event-label">
                <label>Regular Price:</label>
                <input type="text" name="regular_price" value="<?php echo esc_attr($regular_price); ?>"><br>
            </div>

            <div class="event-label">
                <label>Sale Price:</label>
                <input type="text" name="sale_price" value="<?php echo esc_attr($sale_price); ?>"><br>
            </div>
            
            <div class="event-label">
            <label>SKU:</label>
            <input type="text" name="sku" value="<?php echo esc_attr($sku); ?>"><br>
        </div>

         </div>
        <?php endif; ?>

        

        <?php if ($is_variable_product): ?>
        <div class="event-label">
            <label>Stock Quantity:</label>
            <input type="number" name="stock_quantity" value="<?php echo esc_attr($stock_quantity); ?>" min="0"><br>
        </div>
        <?php endif; ?>


        <?php if (!empty($subscription_price)): ?>
            <div class="event-row">
                <div class="event-label">
                    <label>Subscription Price:</label>
                    <input type="text" name="subscription_price" value="<?php echo esc_attr($subscription_price); ?>"><br>
                </div>

                <div class="event-label">
                    <label>Subscription Interval:</label>
                    <input type="text" name="subscription_interval" value="<?php echo esc_attr($subscription_interval); ?>"><br>
                </div>

                <div class="event-label">
                    <label>Subscription Period:</label>
                    <input type="text" name="subscription_period" value="<?php echo esc_attr($subscription_period); ?>"><br>
                </div>

                <div class="event-label">
                    <label>Sign-Up Fee:</label>
                    <input type="text" name="subscription_sign_up_fee" value="<?php echo esc_attr($subscription_sign_up_fee); ?>"><br>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!$is_membership_product): ?>
            <div class="event-label">
                <label>Categories:</label>
                <select name="categories[]" multiple>
                    <?php if (!empty($filtered_categories)): ?>
                        <?php foreach ($filtered_categories as $category): ?>
                            <option value="<?php echo esc_attr($category->term_id); ?>" <?php echo in_array($category->name, $categories, true) ? 'selected' : ''; ?>>
                                <?php echo esc_html($category->name); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <option value="">No categories available</option>
                    <?php endif; ?>
                </select><br>
            </div>
        <?php endif; ?>

        
     <div class="wysiwyg-wrapper">
        <div class="wysiwyg-column1">
            <?php
                wp_editor(
                    $long_description,
                    'long_description',
                    [
                        'textarea_name' => 'long_description',
                        'media_buttons' => true,
                        'textarea_rows' => 10,
                    ]
                );
                ?><br>
        </div>

            <div class="event-label wysiwyg-column2 dotted-border">
                <label>Featured Image:</label>
                <input type="hidden" name="featured_image_id" id="featured_image_id" value="<?php echo esc_attr($featured_image_id); ?>">
                <div id="featured_image_preview">
                    <?php if ($featured_image_url): ?>
                        <img src="<?php echo esc_url($featured_image_url); ?>" style="width:100px;height:auto;">
                    <?php endif; ?>
                </div>
                <button type="button" id="choose_featured_image">Select Featured Image</button><br>
            </div>
     </div>

        <?php if (!$is_membership_product): ?>
           <div class="event-label">
                <label>Gallery Images:</label>
                    <input type="hidden" name="gallery_image_ids" id="gallery_image_ids" value="<?php echo esc_attr(implode(',', array_keys($gallery_image_urls))); ?>">
                    <div id="gallery_images_preview">
                        <?php foreach ($gallery_image_urls as $id => $url): ?>
                            <div class="gallery-image" data-id="<?php echo $id; ?>">
                                <img src="<?php echo esc_url($url); ?>" style="width:100px;height:auto;margin-right:10px;">
                                <button type="button" class="remove-gallery-image" data-id="<?php echo $id; ?>">Remove</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" id="choose_gallery_images">Select Gallery Images</button><br>
           </div>
        <?php endif; ?>

        <button type="submit">Save Changes</button>
        <button type="button" id="cancel_button">Cancel</button>
    </form>

    <script>
        // Featured Image Selector
        document.getElementById('choose_featured_image').addEventListener('click', function () {
            var frame = wp.media({
                title: 'Select Featured Image',
                button: { text: 'Use this image' },
                multiple: false
            });

            frame.on('select', function () {
                var attachment = frame.state().get('selection').first().toJSON();
                document.getElementById('featured_image_id').value = attachment.id;
                document.getElementById('featured_image_preview').innerHTML = '<img src="' + attachment.url + '" style="width:100px;height:auto;">';
            });

            frame.open();
        });

        // Cancel Button
        document.getElementById('cancel_button').addEventListener('click', function () {
            const url = new URL(window.location.href);
            url.searchParams.delete('product_id');
            window.location.href = url.toString();
        });

        // Gallery Image Selector
        document.getElementById('choose_gallery_images').addEventListener('click', function () {
            var frame = wp.media({
                title: 'Select Gallery Images',
                button: { text: 'Add to Gallery' },
                multiple: true
            });

            frame.on('select', function () {
                var selection = frame.state().get('selection');
                var ids = [];
                var previewContainer = document.getElementById('gallery_images_preview');
                previewContainer.innerHTML = '';

                selection.each(function (attachment) {
                    attachment = attachment.toJSON();
                    ids.push(attachment.id);
                    previewContainer.innerHTML += `
                        <div class="gallery-image" data-id="${attachment.id}">
                            <img src="${attachment.url}" style="width:100px;height:auto;margin-right:10px;">
                            <button type="button" class="remove-gallery-image" data-id="${attachment.id}">Remove</button>
                        </div>
                    `;
                });

                document.getElementById('gallery_image_ids').value = ids.join(',');
            });

            frame.open();
        });

        // Remove Gallery Image (delegated)
        document.getElementById('gallery_images_preview').addEventListener('click', function (e) {
            if (e.target.classList.contains('remove-gallery-image')) {
                const idToRemove = e.target.getAttribute('data-id');
                const container = e.target.closest('.gallery-image');
                container.remove();

                const hiddenInput = document.getElementById('gallery_image_ids');
                const ids = hiddenInput.value.split(',').filter(id => id !== idToRemove);
                hiddenInput.value = ids.join(',');
            }
        });
</script>

    <?php
}





function add_woo_product() {
    // Enqueue WordPress Media Library
    wp_enqueue_media();

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $product_title = sanitize_text_field($_POST['product_title']);
        $short_description = sanitize_text_field($_POST['short_description']);
        $long_description = wp_kses_post($_POST['long_description']);
        $sku = sanitize_text_field($_POST['sku']);

        $category_id = isset($_POST['category']) ? intval($_POST['category']) : 0;
        $featured_image_id = intval($_POST['featured_image_id']);
        $gallery_image_ids = isset($_POST['gallery_image_ids']) ? sanitize_text_field($_POST['gallery_image_ids']) : '';
        $regular_price = isset($_POST['regular_price']) ? sanitize_text_field($_POST['regular_price']) : '';
        $sale_price = isset($_POST['sale_price']) ? sanitize_text_field($_POST['sale_price']) : '';
        $stock_quantity = isset($_POST['stock_quantity']) && $_POST['stock_quantity'] !== '' ? intval($_POST['stock_quantity']) : 1;

        // Get logged-in user's club info
        $club_info = get_user_club_info1();
        $selected_club_id = $club_info['club_id'] ?? null;
        $club_name = $club_info['club_name'] ?? null;

        // Create the product
        $product_id = wp_insert_post([
            'post_title' => $product_title,
            'post_content' => $long_description,
            'post_excerpt' => $short_description,
            'post_status' => 'publish',
            'post_type' => 'product',
        ]);

        if ($product_id) {
            // Update meta fields
            update_post_meta($product_id, '_sku', $sku);

            // Force all products to always be in stock
            update_post_meta($product_id, '_stock_status', 'instock');
            update_post_meta($product_id, '_manage_stock', 'yes');
            update_post_meta($product_id, '_stock', $stock_quantity);

            // Set product category
            if ($category_id) {
                wp_set_object_terms($product_id, [$category_id], 'product_cat');
            }

            // Set featured image
            if (!empty($featured_image_id)) {
                update_post_meta($product_id, '_thumbnail_id', $featured_image_id);
            }

            // Set gallery images
            if (!empty($gallery_image_ids)) {
                update_post_meta($product_id, '_product_image_gallery', $gallery_image_ids);
            }

            // Add club info meta fields
            if ($club_name) {
                update_post_meta($product_id, '_select_club_id', $selected_club_id);
                update_post_meta($product_id, '_select_club_name', $club_name);
            }

            // Handle pricing
            update_post_meta($product_id, '_regular_price', $regular_price);
            update_post_meta($product_id, '_sale_price', $sale_price);
            $price = !empty($sale_price) ? $sale_price : $regular_price;
            update_post_meta($product_id, '_price', $price);

            // Force WooCommerce to refresh stock data
            wc_update_product_stock_status($product_id, 'instock');
            wc_delete_product_transients($product_id);

            echo '<p>Product added successfully!</p>';
        } else {
            echo '<p>Failed to add product. Please try again.</p>';
        }
    }

    global $wpdb;

    // Fetch all product categories
    $categories = get_terms([
        'taxonomy' => 'product_cat',
        'hide_empty' => false, // Include empty categories
    ]);
    ?>

    <form method="post">
        <div class="event-row">
            <div class="event-label">
                <label>Product Title:</label>
                <input type="text" name="product_title" required><br>
            </div>

            <div class="event-label">
                <label>Short Description:</label>
                <textarea name="short_description"></textarea><br>
            </div>

            <div class="event-label">
                <label>SKU:</label>
                <input type="text" name="sku" required><br>

            </div>
        </div>

        <div class="event-row">
            <div class="event-label">
                <label>Regular Price:</label>
                <input type="text" name="regular_price"><br>
            </div>

            <div class="event-label">
                <label>Sale Price:</label>
                <input type="text" name="sale_price"><br>
            </div>

            <div class="event-label">
                <label>Stock Quantity:</label>
                <input type="number" name="stock_quantity" min="0" required><br>
            </div>
        </div>

        <div class="event-label">
            <label>Categories:</label>
            <select name="category">
                <option value="">Select a category</option>
                <?php 
                $filtered_categories = get_filtered_categories_by_club();
                if (!empty($filtered_categories)) {
                    foreach ($filtered_categories as $category): ?>
                        <option value="<?php echo esc_attr($category->term_id); ?>">
                            <?php echo esc_html($category->name); ?>
                        </option>
                    <?php endforeach;
                } else {
                    echo '<option value="">No categories available for your club</option>';
                }
                ?>
            </select><br>
        </div>

        <div class="wysiwyg-wrapper">
            <div class="event-label">
                <label>Long Description:</label>
                <div class="wysiwyg-column1">
                <?php
                wp_editor('', 'long_description', [
                    'textarea_name' => 'long_description',
                    'media_buttons' => true,
                    'textarea_rows' => 10,
                ]);
                ?></div><br>
            </div>

            <div class="event-label dotted-border wysiwyg-column2">
                <label>Featured Image:</label>
                <input type="hidden" name="featured_image_id" id="featured_image_id">
                <div id="featured_image_preview"></div>
                <button type="button" id="choose_featured_image">Select Featured Image</button><br>
            </div>
        </div>
       

        <div class="event-label">
            <label>Gallery Images:</label>
            <input type="hidden" name="gallery_image_ids" id="gallery_image_ids">
            <div id="gallery_images_preview"></div>
            <button type="button" id="choose_gallery_images">Select Gallery Images</button><br>
        </div>

        <button type="submit">Add Product</button>
    </form>

    <script>
       

        // Featured Image Selector
        document.getElementById('choose_featured_image').addEventListener('click', function () {
            var frame = wp.media({ title: 'Select Featured Image', button: { text: 'Use this image' }, multiple: false });

            frame.on('select', function () {
                var attachment = frame.state().get('selection').first().toJSON();
                document.getElementById('featured_image_id').value = attachment.id;
                document.getElementById('featured_image_preview').innerHTML = '<img src="' + attachment.url + '" style="width:100px;height:auto;">';
            });

            frame.open();
        });

        // Gallery Images Selector
        document.getElementById('choose_gallery_images').addEventListener('click', function () {
            var frame = wp.media({ title: 'Select Gallery Images', button: { text: 'Use these images' }, multiple: true });

            frame.on('select', function () {
                var selection = frame.state().get('selection').toJSON();
                var ids = selection.map(function (item) { return item.id; }).join(',');
                document.getElementById('gallery_image_ids').value = ids;
                var preview = selection.map(function (item) {
                    return '<img src="' + item.url + '" style="width:100px;height:auto;margin-right:10px;">';
                }).join('');
                document.getElementById('gallery_images_preview').innerHTML = preview;
            });

            frame.open();
        });
    </script>

    <?php
}




function get_user_club_info1() {
    global $wpdb;
    $current_user = wp_get_current_user();
    $user_email = $current_user->user_email;

    // Query to fetch club info for the logged-in user
    $club_info = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT club_id, club_name FROM wp_club_members WHERE user_email = %s",
            $user_email
        ),
        ARRAY_A
    );

    return $club_info;
}
?>

