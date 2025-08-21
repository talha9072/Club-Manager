<?php
// Ensure to include this within a WordPress environment
defined('ABSPATH') || exit;

// Fetch club ID from the URL
$club_id = isset($_GET['club_id']) ? intval($_GET['club_id']) : 0;
if (!$club_id) {
    echo '<p>' . __('No club ID provided.', 'textdomain') . '</p>';
    return;
}

// Fetch club name from the database
global $wpdb;
$club_name = $wpdb->get_var($wpdb->prepare(
    "SELECT club_name FROM {$wpdb->prefix}clubs WHERE club_id = %d",
    $club_id
));
if (!$club_name) {
    echo '<p>' . __('Club not found.', 'textdomain') . '</p>';
    return;
}

// Fetch all WooCommerce products with the category "Membership"
$args = [
    'post_type' => 'product',
    'posts_per_page' => -1,
    'tax_query' => [
        [
            'taxonomy' => 'product_cat',
            'field' => 'name',
            'terms' => 'Membership',
        ],
    ],
];
$membership_products = get_posts($args);

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_membership_settings'])) {
    if (isset($_POST['product_ids']) && is_array($_POST['product_ids'])) {
        foreach ($_POST['product_ids'] as $product_id) {
            $product_id = intval($product_id);

            // Save club ID and club name as post meta for the product
            update_post_meta($product_id, '_select_club_id', $club_id);
            update_post_meta($product_id, '_select_club_name', $club_name);
        }

        // Redirect to avoid form resubmission
        wp_redirect(add_query_arg(['club_id' => $club_id, 'updated' => true], $_SERVER['REQUEST_URI']));
        exit;
    }
}
?>

<?php if (isset($_GET['updated']) && $_GET['updated'] == true): ?>
    <div class="notice notice-success is-dismissible">
        <p><?php _e('Membership settings saved successfully!', 'textdomain'); ?></p>
    </div>
<?php endif; ?>

<form method="POST" action="">
    <table class="form-table">
        <tr>
            <th scope="row">
                <label for="product_ids">
                    <?php _e('Select Membership Products', 'textdomain'); ?>
                </label>
            </th>
            <td>
                <select id="product_ids" name="product_ids[]" multiple="multiple" style="width: 100%; height: 200px;">
                    <?php foreach ($membership_products as $product): ?>
                        <option value="<?php echo esc_attr($product->ID); ?>">
                            <?php echo esc_html($product->post_title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="description">Select one or more membership products to associate with this club.</p>
            </td>
        </tr>
    </table>

    <input type="hidden" name="club_id" value="<?php echo $club_id; ?>" />
    <input type="hidden" name="save_membership_settings" value="1" />
    <p class="submit">
        <input type="submit" class="button button-primary" value="<?php _e('Save Changes', 'textdomain'); ?>" />
    </p>
</form>
