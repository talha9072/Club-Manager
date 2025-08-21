<?php
// Ensure to include this within a WordPress environment
defined('ABSPATH') || exit;

// Load necessary WordPress scripts for media uploader and select2
function enqueue_club_scripts() {
    wp_enqueue_media(); // Enqueues WordPress Media Library
    wp_enqueue_script('select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js', ['jquery'], '4.0.13', true);
    wp_enqueue_style('select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css', [], '4.0.13');
    wp_localize_script('select2', 'ajaxurl', admin_url('admin-ajax.php')); // AJAX URL for WordPress
}
add_action('admin_enqueue_scripts', 'enqueue_club_scripts');

// Fetch club details if available (for edit case)
global $wpdb;
$club_id = isset($_GET['club_id']) ? intval($_GET['club_id']) : 0;
$club_details = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}clubs WHERE club_id = %d", $club_id ) );

// Set default values for club name, URL, logo, and template
$club_name = $club_details ? esc_attr( $club_details->club_name ) : '';
$club_url = $club_details ? esc_attr( $club_details->club_url ) : '/';
$club_logo = $club_details ? esc_url( $club_details->club_logo ) : '';
$template_id = $club_details ? intval( $club_details->template_id ) : null;

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_club_details'])) {
    $club_name = sanitize_text_field($_POST['club_name']);
    $club_url = esc_url_raw($_POST['club_url']);
    $club_logo = esc_url_raw($_POST['club_logo']);
    $template_id = isset($_POST['template_id']) ? intval($_POST['template_id']) : null;

    // Insert or update the club details in the database
    if ($club_id > 0) {
        // Update existing club
        $wpdb->update(
            "{$wpdb->prefix}clubs",
            [
                'club_name'   => $club_name,
                'club_url'    => $club_url,
                'club_logo'   => $club_logo,
                'template_id' => $template_id, // Include template_id
            ],
            ['club_id' => $club_id]
        );
    } else {
        // Insert new club
        $wpdb->insert(
            "{$wpdb->prefix}clubs",
            [
                'club_name'   => $club_name,
                'club_url'    => $club_url,
                'club_logo'   => $club_logo,
                'template_id' => $template_id, // Include template_id
            ]
        );
        $club_id = $wpdb->insert_id; // Get the new club ID

        
    }

    // Redirect to avoid resubmitting the form on page reload
    wp_redirect(add_query_arg(['club_id' => $club_id, 'updated' => true], $_SERVER['REQUEST_URI']));
    exit;
}



// AJAX handler for searching pages
add_action('wp_ajax_search_pages', 'search_pages_ajax_handler');
function search_pages_ajax_handler() {
    if (!isset($_GET['search'])) {
        wp_send_json([]); // Return an empty JSON array if no search term
        return;
    }

    $search_term = sanitize_text_field($_GET['search']);

    // Query pages matching the search term
    $pages = get_posts([
        'post_type'      => 'page',
        'post_status'    => 'publish',
        's'              => $search_term, // Search term
        'posts_per_page' => 10,           // Limit results
    ]);

    // Prepare results for the dropdown
    $results = [];
    foreach ($pages as $page) {
        $results[] = [
            'id'    => get_permalink($page->ID),
            'text'  => $page->post_title,
        ];
    }

    wp_send_json($results); // Send JSON response
}
?>

<?php if (isset($_GET['updated']) && $_GET['updated'] == true): ?>
    <div class="notice notice-success is-dismissible">
        <p><?php _e('Club details saved successfully!', 'textdomain'); ?></p>
    </div>
<?php endif; ?>

<form method="POST" action="" enctype="multipart/form-data">
    <p>Lorem ipsum dolor, sit amet consectetur adipisicing elit. Debitis sapiente iusto, delectus officia blanditiis error est neque eligendi porro libero.</p>
    <table class="form-table">
        <tr>
            <th scope="row">
                <label for="club_name">
                    <?php _e('Club Name', 'textdomain'); ?>
                    <span class="woocommerce-help-tip" data-tip="<?php _e('Enter the name of the club. For example, BMW Car Club Gauteng.', 'textdomain'); ?>"></span>
                </label>
            </th>
            <td>
                <input type="text" id="club_name" name="club_name" value="<?php echo $club_name; ?>" class="regular-text" required />
            </td>
        </tr>
        <tr>
    <th scope="row">
        <label for="club_url">
            <?php _e('Home URL', 'textdomain'); ?>
            <span class="woocommerce-help-tip" data-tip="<?php _e('Search and select a page for the club’s home URL.', 'textdomain'); ?>"></span>
        </label>
    </th>
    <td>
        <select id="club_url" name="club_url" class="regular-text" required>
            <option value=""><?php _e('Search and select a page', 'textdomain'); ?></option>
            <?php if ($club_url): ?>
                <option value="<?php echo esc_url($club_url); ?>" selected><?php echo esc_url($club_url); ?></option>
            <?php endif; ?>
        </select>
        <p class="description"><?php _e('Start typing to search for pages.', 'textdomain'); ?></p>
    </td>
</tr>




        <tr>
            <th scope="row">
                <label for="club_logo">
                    <?php _e('Logo', 'textdomain'); ?>
                    <span class="woocommerce-help-tip" data-tip="<?php _e('Upload or select a logo image for the club.', 'textdomain'); ?>"></span>
                </label>
            </th>
            <td>
                <div class="club-logo-wrapper">
                    <img id="club-logo-preview" src="<?php echo $club_logo; ?>" style="max-width: 150px; <?php echo !$club_logo ? 'display:none;' : ''; ?>" />
                    <input type="hidden" id="club_logo" name="club_logo" value="<?php echo $club_logo; ?>" />
                    <button type="button" class="button upload-image"><?php _e('Upload/Add Image', 'textdomain'); ?></button>
                    <button type="button" class="button remove-image" style="<?php echo !$club_logo ? 'display:none;' : ''; ?>"><?php _e('Remove Image', 'textdomain'); ?></button>
                </div>
            </td>
        </tr>
    </table>

    <input type="hidden" name="club_id" value="<?php echo $club_id; ?>" />
    <input type="hidden" name="save_club_details" value="1" />
    <p class="submit">
        <input type="submit" class="button button-primary" value="<?php _e('Save Changes', 'textdomain'); ?>" />
    </p>
</form>

<script>
    jQuery(document).ready(function($) {
        // Initialize Select2 for searchable dropdown
        $('#club_url').select2({
            ajax: {
                url: ajaxurl, // WordPress AJAX URL
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return {
                        action: 'search_pages', // AJAX action
                        search: params.term    // Search term
                    };
                },
                processResults: function(data) {
                    return {
                        results: data
                    };
                },
                cache: true
            },
            minimumInputLength: 2, // Require at least 2 characters to search
            placeholder: '<?php _e('Search for a page...', 'textdomain'); ?>',
            allowClear: true
        });

        // Media Uploader for Club Logo
        var mediaUploader;
        $('.upload-image').click(function(e) {
            e.preventDefault();
            if (mediaUploader) {
                mediaUploader.open();
                return;
            }
            mediaUploader = wp.media.frames.file_frame = wp.media({
                title: '<?php _e("Choose Image", "textdomain"); ?>',
                button: {
                    text: '<?php _e("Choose Image", "textdomain"); ?>'
                }, multiple: false
            });
            mediaUploader.on('select', function() {
                var attachment = mediaUploader.state().get('selection').first().toJSON();
                $('#club_logo').val(attachment.url);
                $('#club-logo-preview').attr('src', attachment.url).show();
                $('.remove-image').show();
            });
            mediaUploader.open();
        });

        $('.remove-image').click(function(e) {
            e.preventDefault();
            $('#club_logo').val('');
            $('#club-logo-preview').hide();
            $(this).hide();
        });
    });

    jQuery(document).ready(function($) {
    // Initialize Select2 for searchable dropdown
    $('#club_url').select2({
        ajax: {
            url: ajaxurl, // WordPress AJAX URL
            dataType: 'json',
            delay: 250,
            data: function(params) {
                return {
                    action: 'search_pages', // AJAX action
                    search: params.term    // Search term
                };
            },
            processResults: function(data) {
                return {
                    results: data // Return the data for Select2
                };
            },
            cache: true
        },
        minimumInputLength: 2, // Require at least 2 characters to search
        placeholder: '<?php _e('Search for a page...', 'textdomain'); ?>',
        allowClear: true // Allow clearing the selection
    });
});

</script>
