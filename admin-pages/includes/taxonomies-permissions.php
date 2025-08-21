<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add custom dropdown field to Attributes, Tags, Categories, and Product Taxonomies
 */

// Add custom dropdown to the Add Form (Categories, Tags, etc.)
function tp_add_dropdown_to_taxonomies($taxonomy) {
    global $wpdb;

    ?>
    <div class="form-field">
        <label for="taxonomy_custom_dropdown">Select Club</label>
        <select name="taxonomy_custom_dropdown" id="taxonomy_custom_dropdown">
            <?php
            // Add the "Global" option
            echo '<option value="global">Global</option>';

            // Fetch all clubs from the wp_clubs table
            $clubs = $wpdb->get_results("SELECT club_id, club_name FROM wp_clubs", ARRAY_A);

            if (!empty($clubs)) {
                // Display all clubs in the dropdown
                foreach ($clubs as $club) {
                    echo '<option value="' . esc_attr($club['club_id']) . '">' . esc_html($club['club_name']) . '</option>';
                }
            } else {
                // No clubs available
                echo '<option value="">No clubs available</option>';
            }
            ?>
        </select>
        <p>Select a club associated with this taxonomy.</p>
    </div>
    <?php
}


// Add custom dropdown to the Edit Form (Categories, Tags, etc.)
function tp_edit_dropdown_for_taxonomies($term) {
    global $wpdb;

    // Get the saved value for the term
    $value = get_term_meta($term->term_id, 'taxonomy_custom_dropdown', true);

    ?>
    <tr class="form-field">
        <th scope="row" valign="top"><label for="taxonomy_custom_dropdown">Select Club</label></th>
        <td>
            <select name="taxonomy_custom_dropdown" id="taxonomy_custom_dropdown">
                <?php
                // Add the "Global" option
                $global_selected = ($value === 'global') ? 'selected="selected"' : '';
                echo '<option value="global" ' . $global_selected . '>Global</option>';

                // Fetch all clubs from the wp_clubs table
                $clubs = $wpdb->get_results("SELECT club_id, club_name FROM wp_clubs", ARRAY_A);

                if (!empty($clubs)) {
                    // Display all clubs in the dropdown
                    foreach ($clubs as $club) {
                        $selected = ($value == $club['club_id']) ? 'selected="selected"' : '';
                        echo '<option value="' . esc_attr($club['club_id']) . '" ' . $selected . '>' . esc_html($club['club_name']) . '</option>';
                    }
                } else {
                    // No clubs available
                    echo '<option value="">No clubs available</option>';
                }
                ?>
            </select>
            <p class="description">Select a club associated with this taxonomy.</p>
        </td>
    </tr>
    <?php
}


// Save custom dropdown value for Taxonomies
function tp_save_dropdown_for_taxonomies($term_id) {
    if (isset($_POST['taxonomy_custom_dropdown'])) {
        // Sanitize and save the selected value (e.g., 'global' or a club ID)
        $value = sanitize_text_field($_POST['taxonomy_custom_dropdown']);
        update_term_meta($term_id, 'taxonomy_custom_dropdown', $value);
    }
}


// Add custom dropdown to Product Attributes
function tp_add_dropdown_to_product_attribute_form() {
    global $wpdb;

    ?>
    <div class="form-field">
        <label for="attribute_custom_dropdown">Select Club</label>
        <select name="attribute_custom_dropdown" id="attribute_custom_dropdown">
            <?php
            // Add the "Global" option
            echo '<option value="global">Global</option>';

            // Fetch all clubs from the wp_clubs table
            $clubs = $wpdb->get_results("SELECT club_id, club_name FROM wp_clubs", ARRAY_A);

            if (!empty($clubs)) {
                // Display all clubs in the dropdown
                foreach ($clubs as $club) {
                    echo '<option value="' . esc_attr($club['club_id']) . '">' . esc_html($club['club_name']) . '</option>';
                }
            } else {
                // No clubs available
                echo '<option value="">No clubs available</option>';
            }
            ?>
        </select>
        <p>Select a club associated with this attribute.</p>
    </div>
    <?php
}
add_action('woocommerce_after_add_attribute_fields', 'tp_add_dropdown_to_product_attribute_form');


// Edit custom dropdown for Product Attributes
function tp_edit_dropdown_in_product_attribute_form($attribute) {
    global $wpdb;

    // Get the saved value for the attribute
    $value = get_option("attribute_custom_dropdown_{$attribute->attribute_id}", '');

    ?>
    <tr class="form-field">
        <th scope="row" valign="top"><label for="attribute_custom_dropdown">Select Club</label></th>
        <td>
            <select name="attribute_custom_dropdown" id="attribute_custom_dropdown">
                <?php
                // Add the "Global" option
                $global_selected = ($value === 'global') ? 'selected="selected"' : '';
                echo '<option value="global" ' . $global_selected . '>Global</option>';

                // Fetch all clubs from the wp_clubs table
                $clubs = $wpdb->get_results("SELECT club_id, club_name FROM wp_clubs", ARRAY_A);

                if (!empty($clubs)) {
                    // Display all clubs in the dropdown
                    foreach ($clubs as $club) {
                        $selected = ($value == $club['club_id']) ? 'selected="selected"' : '';
                        echo '<option value="' . esc_attr($club['club_id']) . '" ' . $selected . '>' . esc_html($club['club_name']) . '</option>';
                    }
                } else {
                    // No clubs available
                    echo '<option value="">No clubs available</option>';
                }
                ?>
            </select>
            <p class="description">Select a club associated with this attribute.</p>
        </td>
    </tr>
    <?php
}
add_action('woocommerce_after_edit_attribute_fields', 'tp_edit_dropdown_in_product_attribute_form');

// Save dropdown for Product Attributes
function tp_save_product_attribute_dropdown($attribute_id) {
    if (isset($_POST['attribute_custom_dropdown'])) {
        // Sanitize and save the selected dropdown value (e.g., 'global' or a club ID)
        $value = sanitize_text_field($_POST['attribute_custom_dropdown']);
        update_option("attribute_custom_dropdown_{$attribute_id}", $value);
    }
}
add_action('woocommerce_attribute_added', 'tp_save_product_attribute_dropdown');
add_action('woocommerce_attribute_updated', 'tp_save_product_attribute_dropdown');


// Hooks for taxonomies
$taxonomies = ['post_tag', 'category', 'product_tag', 'product_cat', 'event_type'];
foreach ($taxonomies as $taxonomy) {
    add_action("{$taxonomy}_add_form_fields", 'tp_add_dropdown_to_taxonomies');
    add_action("{$taxonomy}_edit_form_fields", 'tp_edit_dropdown_for_taxonomies');
    add_action("created_{$taxonomy}", 'tp_save_dropdown_for_taxonomies');
    add_action("edited_{$taxonomy}", 'tp_save_dropdown_for_taxonomies');
}

// Add custom columns for Taxonomies
function tp_add_custom_column_for_taxonomies($columns) {
    $columns['custom_club'] = 'Club';
    return $columns;
}

function tp_display_custom_column_for_taxonomies($content, $column_name, $term_id) {
    if ($column_name === 'custom_club') {
        // Get the saved value for the custom dropdown
        $value = get_term_meta($term_id, 'taxonomy_custom_dropdown', true);

        if ($value) {
            if ($value === 'global') {
                // If the value is "global," display "Global"
                $content = 'Global';
            } else {
                // Otherwise, fetch the club name from the database
                global $wpdb;
                $club_name = $wpdb->get_var($wpdb->prepare("SELECT club_name FROM wp_clubs WHERE club_id = %d", $value));
                $content = esc_html($club_name ? $club_name : '—');
            }
        } else {
            // If no value is set, display a dash
            $content = '—';
        }
    }
    return $content;
}

add_filter('manage_edit-post_tag_columns', 'tp_add_custom_column_for_taxonomies');
add_filter('manage_edit-category_columns', 'tp_add_custom_column_for_taxonomies');
add_filter('manage_edit-product_tag_columns', 'tp_add_custom_column_for_taxonomies');
add_filter('manage_edit-product_cat_columns', 'tp_add_custom_column_for_taxonomies');
add_filter('manage_edit-event_type_columns', 'tp_add_custom_column_for_taxonomies');

add_filter('manage_post_tag_custom_column', 'tp_display_custom_column_for_taxonomies', 10, 3);
add_filter('manage_category_custom_column', 'tp_display_custom_column_for_taxonomies', 10, 3);
add_filter('manage_product_tag_custom_column', 'tp_display_custom_column_for_taxonomies', 10, 3);
add_filter('manage_product_cat_custom_column', 'tp_display_custom_column_for_taxonomies', 10, 3);

add_filter('manage_event_type_custom_column', 'tp_display_custom_column_for_taxonomies', 10, 3);
