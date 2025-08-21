<?php
// Prevent direct file access
if (!defined('ABSPATH')) {
    exit;
}

// Main logic to determine what to render
if (isset($_GET['clubb_id']) && !empty($_GET['clubb_id'])) {
    echo '<h2 class="manager-h2">Add Committee Members</h2>';
    render_add_club_member_form_v2(); // Render the add club member form if club_id is set
} elseif (isset($_GET['user_id']) && !empty($_GET['user_id'])) {
    echo '<h2 class="manager-h2">Edit Committee Members</h2>';
    render_edit_committee_form(); // Render the form if user_id is set
} else {
    echo '<div class="admin-switch">';
echo '<h2 class="manager-h2">Committee</h2>';
echo '<button class="button button-primary my-filters All-button" id="add-member-button">Add Member</button>';
echo '</div>';
    render_club_members_table(); // Render the table otherwise
}

// Function to get the logged-in user's club ID
function get_logged_in_user_club_id() {
    global $wpdb;

    // Check if 'club' and '2ndclubid' parameters exist in the URL
    if (isset($_GET['club']) && !empty($_GET['club']) && isset($_GET['2ndclubid']) && !empty($_GET['2ndclubid'])) {
        $club_name = urldecode(sanitize_text_field($_GET['club']));
        $club_id = intval($_GET['2ndclubid']);

        // Validate the club_name and club_id from the URL
        $valid_club_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT club_id FROM {$wpdb->prefix}clubs WHERE club_name = %s AND club_id = %d",
                $club_name,
                $club_id
            )
        );

        // If valid, return the club_id
        if ($valid_club_id) {
            return intval($valid_club_id);
        }
    }

    // Fallback: Fetch club_id based on the logged-in user's email
    $current_user = wp_get_current_user();
    if (!$current_user->exists()) {
        return false; // Return false if the user is not logged in
    }

    $user_email = $current_user->user_email;

    // Fetch the logged-in user's club information
    $club_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT club_id FROM {$wpdb->prefix}club_members WHERE user_email = %s",
            $user_email
        )
    );

    return $club_id ? intval($club_id) : false; // Return the club_id or false if not found
}



function render_edit_committee_form() {
        if (!isset($_GET['user_id']) || empty($_GET['user_id'])) {
            echo "<p>Invalid or missing user ID.</p>";
            return;
        }

        $user_id = intval($_GET['user_id']); // Get the user ID from the URL

        global $wpdb;

        // Fetch the user's details from wp_users
        $user = $wpdb->get_row(
            $wpdb->prepare("SELECT ID, display_name, user_email FROM {$wpdb->users} WHERE ID = %d", $user_id)
        );

        if (!$user) {
            echo "<p>User not found in the users table.</p>";
            return;
        }

        $email = esc_attr($user->user_email);

        // Fetch the user's club information
        $club_info = $wpdb->get_row(
            $wpdb->prepare("SELECT club_id, title, custom_title FROM {$wpdb->prefix}club_committee WHERE email LIKE %s", '%' . $email . '%')
        );

        if (!$club_info) {
            echo "<p>User not found in the club members table.</p>";
            return;
        }

        $club_id = esc_attr($club_info->club_id);
        $role = esc_attr($club_info->title); // Current role
        $custom_title = esc_attr($club_info->custom_title); // Current custom title

        // List of available roles and custom titles
        $roles = [
            'Chairman',
            'Vice-chairman',
            'Secretary',
            'Treasurer',
            'Events',
            'Training',
            'Social Media',
            'Communications',
            'Member',
            'Clubs Africa'
        ];

        $custom_titles = [
            'President',
            'Rides Captain',
            'Rides',
            'Tours',
            'Marshalls',
            'Social',
            'Media',
            'Social Media Liaison',
            'Podcaster',
            'Taxi Driver',
            'Insurance Guru',
            'Technical Guru',
            'Investment Guru',
            'MC',
            'Storyteller Extraordinaire'
        ];

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_changes'])) {
        $new_display_name = sanitize_text_field($_POST['display_name']);
        $new_email = sanitize_email($_POST['email']);
        $new_role = sanitize_text_field($_POST['role']);
        $new_custom_title = sanitize_text_field($_POST['custom_title']);

        // Ensure at least one of "role" or "custom_title" is provided
        if (empty($new_role) && empty($new_custom_title)) {
            echo "<p>Error: Please select a Title/Role or provide a Custom Title.</p>";
            return;
        }

        // If a role is provided, validate it
        if (!empty($new_role) && !in_array($new_role, $roles)) {
            echo "<p>Error: Invalid role selected.</p>";
            return;
        }

        // Update wp_users for display_name and email
        $wpdb->update(
            "{$wpdb->users}",
            ['display_name' => $new_display_name, 'user_email' => $new_email],
            ['ID' => $user_id],
            ['%s', '%s'],
            ['%d']
        );

        // Update role, custom title, and email in wp_club_committee
        $wpdb->update(
            "{$wpdb->prefix}club_committee",
            ['title' => $new_role, 'custom_title' => $new_custom_title, 'email' => $new_email],
            ['email' => $email],
            ['%s', '%s', '%s'],
            ['%s']
        );

        // Redirect to the same page with a success message
        wp_redirect(add_query_arg(['saved' => 'true'], $_SERVER['REQUEST_URI']));
        exit;
    }

        // Display success message if redirected with a success flag
        if (isset($_GET['saved']) && $_GET['saved'] === 'true') {
            echo "<p style='color: green;'>Changes saved successfully!</p>";

            // JavaScript to remove 'saved=true' from the URL
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    const url = new URL(window.location.href);
                    url.searchParams.delete('saved');
                    window.history.replaceState({}, document.title, url.toString());
                });
            </script>";
        }
            

        // Render the form
        ?>
        <form method="POST" class="edit-committee-form">
            
            <p>
                <label for="display_name">Name:</label><br>
                <input type="text" id="display_name" name="display_name" value="<?php echo esc_attr($user->display_name); ?>" required>
            </p>
            <p>
                <label for="email">Email Address:</label><br>
                <input type="email" id="email" name="email" value="<?php echo esc_attr($email); ?>" required>
            </p>
            <p>
                <label for="role">Title/Role:</label><br>
                <select id="role" name="role" required>
                    <option value=""><?php _e('Select a Role', 'club-manager'); ?></option>
                    <?php foreach ($roles as $available_role): ?>
                        <option value="<?php echo esc_attr($available_role); ?>" <?php selected($role, $available_role); ?>>
                            <?php echo esc_html($available_role); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>
            <p>
                <label for="custom_title">Custom Title:</label><br>
                <input type="text" id="custom_title" name="custom_title" list="custom_title_list" value="<?php echo esc_attr($custom_title); ?>">
                <datalist id="custom_title_list">
                    <?php foreach ($custom_titles as $available_custom_title): ?>
                        <option value="<?php echo esc_attr($available_custom_title); ?>">
                            <?php echo esc_html($available_custom_title); ?>
                        </option>
                    <?php endforeach; ?>
                </datalist>
            </p>
            <p>
            <button type="submit"class="savebtn committeesave" name="save_changes">
                <i class="fa fa-save"></i> Save Changes
            </button>

            <a href="<?php echo esc_url(remove_query_arg('user_id')); ?>" class="cancelbtn">Cancel</a>
            </p>
        </form>

        <script>
        document.addEventListener('DOMContentLoaded', function () {
            const roleField = document.getElementById('role');
            const customTitleField = document.getElementById('custom_title');

            // Disable fields based on selection
            function toggleFields() {
                if (roleField.value) {
                    customTitleField.disabled = true;
                } else {
                    customTitleField.disabled = false;
                }

                if (customTitleField.value) {
                    roleField.disabled = true;
                } else {
                    roleField.disabled = false;
                }
            }

            // Add event listeners
            roleField.addEventListener('change', toggleFields);
            customTitleField.addEventListener('input', toggleFields);

            // Initialize field states
            toggleFields();
        });
        </script>
        <?php
}





// Function to render the table of club members with added features and pagination
function render_club_members_table() {
    // Get the logged-in user's club_id
    $club_id = get_logged_in_user_club_id();

    if (!$club_id) {
        echo "<p>You are not associated with any club or not logged in.</p>";
        return;
    }

    global $wpdb, $wp_query;

    // Search and filter logic
    $search_query = '';
    if (!empty($_GET['search'])) {
        $search_query = sanitize_text_field($_GET['search']);
    }

    // Pagination logic using global $wp_query
    $paged = isset($wp_query->query_vars['paged']) ? intval($wp_query->query_vars['paged']) : 1;
    $current_page = $paged > 0 ? $paged : 1;
    $items_per_page = 20; // Number of members per page
    $offset = ($current_page - 1) * $items_per_page;

    // Query to get paginated users in the club committee
    $query = $wpdb->prepare(
        "SELECT u.ID AS user_id, u.display_name, u.user_email, cc.title AS role, cc.custom_title 
         FROM {$wpdb->prefix}club_committee cc
         JOIN {$wpdb->users} u 
         ON u.ID = cc.user_id
         WHERE cc.club_id = %d " .
            ($search_query ? "AND (u.display_name LIKE %s OR u.user_email LIKE %s OR cc.title LIKE %s OR cc.custom_title LIKE %s)" : '') . 
            " LIMIT %d OFFSET %d",
        ...($search_query ? [$club_id, "%$search_query%", "%$search_query%", "%$search_query%", "%$search_query%", $items_per_page, $offset] : [$club_id, $items_per_page, $offset])
    );
    

    $users = $wpdb->get_results($query);

  
    // Fetch total number of members for pagination
    $total_members = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) 
             FROM {$wpdb->prefix}club_committee cc
             JOIN {$wpdb->users} u 
             ON u.ID = cc.user_id
             WHERE cc.club_id = %d " .
                ($search_query ? "AND (u.display_name LIKE %s OR u.user_email LIKE %s OR cc.title LIKE %s)" : ''),
            ...($search_query ? [$club_id, "%$search_query%", "%$search_query%", "%$search_query%"] : [$club_id])
        )
    );

    $total_pages = ceil($total_members / $items_per_page);
    

    // Handle bulk actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['bulk-action']) && !empty($_POST['user_ids'])) {
        $bulk_action = sanitize_text_field($_POST['bulk-action']);
        $selected_ids = array_map('intval', $_POST['user_ids']);

        if ($bulk_action === 'remove') {
            foreach ($selected_ids as $user_id) {
                $wpdb->delete(
                    "{$wpdb->prefix}club_committee",
                    ['user_id' => $user_id, 'club_id' => $club_id],
                    ['%d', '%d']
                );
            }
            wp_redirect(remove_query_arg(['bulk-action', 'user_ids'], $_SERVER['REQUEST_URI']));
            exit;

        } elseif ($bulk_action === 'export') {
            export_members_to_csv($selected_ids);
            exit;
        }
    }

    // Render the table and controls
    ?>
    <form method="get" id="filter-form" class="end-filters">
        <!-- Preserve Section Parameter -->
        <input type="hidden" name="section" value="<?php echo isset($_GET['section']) ? esc_attr($_GET['section']) : 'default'; ?>" />

        <!-- Search Input -->
        <div class="input-icon">
            <input type="text" class="my-inputs" name="search" placeholder="Search" value="<?php echo esc_attr($search_query); ?>" />
            <span class="icon"><i class="fa fa-search"></i></span>
        </div>

        <!-- Search Button -->
        <button type="submit" class="my-filters All-button">Search</button>

        <!-- Clear Filters Button -->
        <button type="button" class="clear-filter" onclick="window.location.href='<?php echo esc_url(add_query_arg('section', isset($_GET['section']) ? esc_attr($_GET['section']) : 'default', basename($_SERVER['PHP_SELF']))); ?>';">Clear Filters</button>
        
    </form>

    <form method="post" id="bulk-form">
        <div class="bulk-users end-filters">
            <select name="bulk-action">
                <option value="">Bulk Actions</option>
                <option value="remove">Remove Members</option>
                <option value="export">Export Members</option>
            </select>
            <button type="submit" class="my-filters All-button">Apply</button>
        </div>
        

        <table style="width: 100%; border-collapse: collapse; "class="managertable" id="committee-table">
            <thead>
                <tr>
                    <th><input type="checkbox" id="select-all3"></th>
                    <th>Name</th>
                    <th>Title</th>
                    <th>Email</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user) : ?>
                    <tr>
                        <td><input type="checkbox" name="user_ids[]" value="<?php echo intval($user->user_id); ?>"></td>
                        <td data-label="Name"><?php echo esc_html($user->display_name); ?></td>
                        <td data-label="Title">
                            <?php
                            // Display custom_title if available, otherwise fallback to role
                            echo esc_html(!empty($user->custom_title) ? $user->custom_title : $user->role);
                            ?>
                        </td>

                        <td data-label="Email"><?php echo esc_html($user->user_email); ?></td>
                        <td data-label="Action">
                            <a href="#" data-user-id="<?php echo esc_attr($user->user_id); ?>" class="action-button">
                                <i class="fa fa-edit"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Pagination Section -->
        <div class="pagination" style="margin-top: 20px; text-align: center;">
            <?php if ($current_page > 1): ?>
                <!-- Previous Button -->
                <a href="<?php echo esc_url(get_pagenum_link($current_page - 1)); ?>" 
                style="margin: 0 5px; padding: 5px 10px; border: 1px solid #ddd; text-decoration: none; color: #333;">
                    Previous
                </a>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $total_pages; $i++) : ?>
                <a href="<?php echo esc_url(get_pagenum_link($i)); ?>" 
                class="<?php echo $i === $current_page ? 'current-page' : ''; ?>" 
                style="margin: 0 5px; padding: 5px 10px; border: 1px solid #ddd; text-decoration: none; 
                        <?php echo $i === $current_page ? 'background-color: #10487B; color: white;' : ''; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>

            <?php if ($current_page < $total_pages): ?>
                <!-- Next Button -->
                <a href="<?php echo esc_url(get_pagenum_link($current_page + 1)); ?>" 
                style="margin: 0 5px; padding: 5px 10px; border: 1px solid #ddd; text-decoration: none; color: #333;">
                    Next
                </a>
            <?php endif; ?>
        </div>
    </form>

    <script>
        // Add "Select All" functionality
        document.getElementById("select-all3").addEventListener("change", function () {
            var checkboxes = document.querySelectorAll('input[name="user_ids[]"]');
            checkboxes.forEach(function (checkbox) {
                checkbox.checked = this.checked;
            }, this);
        });

        // Redirect to Add Member form with club_id in URL
        document.getElementById("add-member-button").addEventListener("click", function (e) {
            e.preventDefault();
            const url = new URL(window.location.href);

            // Preserve existing 'section' parameter if it exists
            const section = url.searchParams.get("section");
            if (!section) {
                console.error("Section parameter is missing!");
                return;
            }

            // Remove other parameters except 'section'
            url.searchParams.forEach((value, key) => {
                if (key !== "section") {
                    url.searchParams.delete(key);
                }
            });

            // Add 'clubb_id' parameter to the URL
            url.searchParams.set("clubb_id", <?php echo json_encode($club_id); ?>);

            // Redirect to the updated URL
            window.location.href = url.toString();
        });
    </script>
    <?php
}



function export_members_to_csv($selected_ids) {
    global $wpdb;
    // Clear all buffers and disable further output
    while (ob_get_level()) {
        ob_end_clean();
    }

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="club_committee_members.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Name', 'Email', 'Role', 'Title', 'Custom Title']); // Updated headers

    foreach ($selected_ids as $user_id) {
        $user = get_userdata($user_id);

        // Fetch title and custom_title from the club_committee table
        $committee_info = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT title, custom_title FROM {$wpdb->prefix}club_committee WHERE user_id = %d",
                $user_id
            )
        );

        $title = $committee_info->title ?? 'N/A';
        $custom_title = $committee_info->custom_title ?? 'N/A';

        fputcsv($output, [
            $user->display_name,
            $user->user_email,
            $user->roles[0] ?? 'N/A',
            $title,
            $custom_title
        ]);
    }

    fclose($output);
    exit;
}









function render_add_club_member_form_v2() {
    // Get the club ID from the URL
    $club_id = isset($_GET['clubb_id']) ? intval($_GET['clubb_id']) : 0;

    // Fetch the club name using the club_id
    global $wpdb;
    $club_name = $wpdb->get_var($wpdb->prepare("SELECT club_name FROM {$wpdb->prefix}clubs WHERE club_id = %d", $club_id));

    if (!$club_name) {
        echo '<p>' . __('Club not found.', 'club-manager') . '</p>';
        return;
    }

    // Output the form
    ?>
    <div class="wrap">
       
    <form id="add-club-member-form-v2" method="post">
    <div class="committee-wrapper">
        <div class="committee-row">
            <label for="member-name-v2"><?php _e('Select Member', 'club-manager'); ?></label>
            <select id="member-name-v2" name="member-name" class="regular-text select2">
                <option value=""><?php _e('Search and select a member', 'club-manager'); ?></option>
            </select>
        </div>
        
        <div class="committee-row">
            <label for="member-email-v2"><?php _e('Member Email', 'club-manager'); ?></label>
            <input type="email" id="member-email-v2" name="member-email" class="regular-text" readonly />
        </div>
        
        <div class="committee-row" style="display: none;">
            <label for="user-id-v2"><?php _e('User ID', 'club-manager'); ?></label>
            <input type="text" id="user-id-v2" name="user-id" class="regular-text" readonly />
        </div>
        
        <div class="committee-row">
            <label for="title-v2"><?php _e('Title', 'club-manager'); ?></label>
            <select id="title-v2" name="title" class="regular-text">
                <option value=""><?php _e('Select a title', 'club-manager'); ?></option>
                <option value="Chairman">Chairman</option>
                <option value="Vice-chairman">Vice-chairman</option>
                <option value="Secretary">Secretary</option>
                <option value="Treasurer">Treasurer</option>
                <option value="Events">Events</option>
                <option value="Training">Training</option>
                <option value="Social Media">Social Media</option>
                <option value="Communications">Communications</option>
                <option value="Member">Member</option>
                <option value="Clubs Africa">Clubs Africa</option>
            </select>
        </div>
        
        <div class="committee-row">
            <label for="custom-title-v2"><?php _e('Custom Title', 'club-manager'); ?></label>
            <input type="text" id="custom-title-v2" name="custom-title" list="custom-title-list" class="regular-text" placeholder="<?php _e('Select or type a custom title', 'club-manager'); ?>" />
            <datalist id="custom-title-list">
                <option value="President">President</option>
                <option value="Rides Captain">Rides Captain</option>
                <option value="Rides">Rides</option>
                <option value="Tours">Tours</option>
                <option value="Marshalls">Marshalls</option>
                <option value="Social">Social</option>
                <option value="Media">Media</option>
                <option value="Social Media Liaison">Social Media Liaison</option>
                <option value="Podcaster">Podcaster</option>
                <option value="Taxi Driver">Taxi Driver</option>
                <option value="Insurance Guru">Insurance Guru</option>
                <option value="Technical Guru">Technical Guru</option>
                <option value="Investment Guru">Investment Guru</option>
                <option value="MC">MC</option>
                <option value="Storyteller Extraordinaire">Storyteller Extraordinaire</option>
            </datalist>
        </div>
    </div>
    
    <button type="submit" class="button button-primary padding-button"><?php _e('Add Member', 'club-manager'); ?></button>
    <button type="button" id="cancel-button-v2" class="button button-secondary padding-button"><?php _e('Cancel', 'club-manager'); ?></button>
</form>
    </div>

    <script type="text/javascript">
    jQuery(document).ready(function ($) {
        // Initialize Select2
        $('#member-name-v2').select2({
            ajax: {
                url: ajax_object.ajaxurl, // Use localized ajaxurl
                type: 'GET',
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return {
                        action: 'fetch_user_names_v2', // Custom action name
                        search: params.term // Search term
                    };
                },
                processResults: function (data) {
                    if (!data) {
                        return { results: [] };
                    }
                    return {
                        results: data.map(function (user) {
                            return {
                                id: user.id, // Use 'id' from JSON response
                                text: user.name, // Display name only
                                email: user.email // Add email for use in selection
                            };
                        })
                    };
                },
                cache: true
            },
            placeholder: "<?php _e('Search and select a member', 'club-manager'); ?>",
            minimumInputLength: 2
        });

        // Populate email and user ID fields on select
        $('#member-name-v2').on('select2:select', function (e) {
            const selectedData = e.params.data;
            $('#member-email-v2').val(selectedData.email); // Populate email field
            $('#user-id-v2').val(selectedData.id); // Populate user ID field
        });

        // Handle form submission
        $('#add-club-member-form-v2').on('submit', function (e) {
            e.preventDefault();

            // Collect form data
            const memberName = $('#member-name-v2 option:selected').text();
            const email = $('#member-email-v2').val();
            const userId = $('#user-id-v2').val();
            const title = $('#title-v2').val();
            const customTitle = $('#custom-title-v2').val();
            const clubId = <?php echo $club_id; ?>;

            // Validate required fields
            if (!memberName || !email || !userId || (!title && !customTitle)) {
                alert('<?php _e('All fields are required.', 'club-manager'); ?>');
                return;
            }

            // Send AJAX request to save data
            $.ajax({
                url: ajax_object.ajaxurl,
                type: 'POST',
                data: {
                    action: 'save_club_member_v2',
                    club_id: clubId,
                    member_name: memberName,
                    email: email,
                    user_id: userId,
                    title: title,
                    custom_title: customTitle
                },
                success: function (response) {
                    if (response.success) {
                        alert(response.data.message || '<?php _e('Member added successfully!', 'club-manager'); ?>');
                        location.reload();
                    } else {
                        alert(response.data.message || '<?php _e('Failed to add the member.', 'club-manager'); ?>');
                    }
                },
                error: function (xhr) {
                    alert('<?php _e('Failed to connect to the server.', 'club-manager'); ?>');
                    console.error(xhr.responseText);
                }
            });
        });

        // Disable the other dropdown when a value is selected
        $('#title-v2, #custom-title-v2').on('change', function () {
            const titleValue = $('#title-v2').val();
            const customTitleValue = $('#custom-title-v2').val();

            if (titleValue) {
                $('#custom-title-v2').prop('disabled', true); // Disable Custom Title
            } else {
                $('#custom-title-v2').prop('disabled', false); // Enable Custom Title
            }

            if (customTitleValue) {
                $('#title-v2').prop('disabled', true); // Disable Title
            } else {
                $('#title-v2').prop('disabled', false); // Enable Title
            }
        });

        // Cancel button functionality
        $('#cancel-button-v2').on('click', function () {
            const url = new URL(window.location.href);
            url.search = ''; // Clear all query parameters
            window.location.href = url.href; // Refresh the page without parameters
        });
    });
    </script>

    <?php
}







?>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const actionButtons = document.querySelectorAll('.action-button');

        actionButtons.forEach(button => {
            button.addEventListener('click', function (event) {
                event.preventDefault();

                const userId = this.getAttribute('data-user-id');
                const currentUrl = new URL(window.location);

                // Check if `2ndclubid` is in the URL, prioritize it as `club_id`
                let clubId = currentUrl.searchParams.get('2ndclubid');
                if (!clubId) {
                    // Fallback to the `club_id` parameter
                    clubId = currentUrl.searchParams.get('club_id');
                }

                // Update the URL with the `user_id` and `club_id` parameters
                if (clubId) {
                    currentUrl.searchParams.set('club_id', clubId); // Always set `club_id` from either `2ndclubid` or fallback
                }
                currentUrl.searchParams.set('user_id', userId);

                // Push the new URL to the browser history and reload
                window.history.pushState({}, '', currentUrl);
                location.reload(); // Ensure the form is rendered after URL change
            });
        });
    });
</script>

