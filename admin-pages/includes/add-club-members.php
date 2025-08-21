<?php
// Prevent direct access to this file
if (!defined('ABSPATH')) {
    exit;
}

// Get the club ID from the URL
$club_id = isset($_GET['club_id']) ? intval($_GET['club_id']) : 0;

// Fetch the club name using the club_id
global $wpdb;
$club_name = $wpdb->get_var($wpdb->prepare("SELECT club_name FROM {$wpdb->prefix}clubs WHERE club_id = %d", $club_id));

if (!$club_name) {
    echo '<p>' . __('Club not found.', 'club-manager') . '</p>';
    return;
}
?>

<div class="wrap">
    <h3>Club Managers</h3>

    <form id="add-club-member-form" method="post">
        <table class="form-table">
            <!-- Searchable member dropdown -->
            <tr>
                <th scope="row">
                    <label for="member-name"><?php _e('Select manager', 'club-manager'); ?></label>
                </th>
                <td>
                    <select id="member-name" name="member-name" class="regular-text select2">
                        <option value=""><?php _e('Search and select a manager', 'club-manager'); ?></option>
                    </select>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="member-email"><?php _e('Member Email', 'club-manager'); ?></label>
                </th>
                <td>
                    <input type="email" id="member-email" name="member-email" class="regular-text" placeholder="<?php _e('Email will be automatically populated', 'club-manager'); ?>" readonly />
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="role"><?php _e('Select Role', 'club-manager'); ?></label>
                </th>
                <td>
                    <select id="role" name="role">
                    <option value=""><?php _e('Select a Role', 'club-manager'); ?></option>
<option value="Club Manager"><?php _e('Club Manager', 'club-manager'); ?></option>
<option value="Media/Social"><?php _e('Media/Social', 'club-manager'); ?></option>
<option value="Treasurer"><?php _e('Treasurer', 'club-manager'); ?></option>
<option value="Store Manager"><?php _e('Store Manager', 'club-manager'); ?></option>

                    </select>
                </td>
            </tr>
        </table>
        <button type="submit" class="button button-primary"><?php _e('Add manager', 'club-manager'); ?></button>
    </form>

    <!-- Dynamic table for showing members based on club -->
    <h2><?php _e('Club manager', 'club-manager'); ?></h2>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php _e('Club Name', 'club-manager'); ?></th>
                <th><?php _e('Name', 'club-manager'); ?></th>
                <th><?php _e('Email', 'club-manager'); ?></th>
                <th><?php _e('Role', 'club-manager'); ?></th>
                <th><?php _e('Permissions', 'club-manager'); ?></th>
            </tr>
        </thead>
        <tbody id="club-members-list">
            <tr><td colspan="5"><?php _e('Loading members...', 'club-manager'); ?></td></tr>
        </tbody>
    </table>
</div>

<!-- Include jQuery and Select2 -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Initialize Select2 for member name search and select
    $('#member-name').select2({
        ajax: {
            url: ajaxurl, // Use WordPress AJAX URL
            dataType: 'json',
            delay: 250,
            data: function(params) {
                return {
                    action: 'search_users', // The AJAX action we will handle in PHP
                    search: params.term // The search term
                };
            },
            processResults: function(data) {
                return {
                    results: $.map(data, function(user) {
                        return {
                            id: user.id,
                            text: user.display_name + ' (' + user.user_login + ')',
                            email: user.user_email
                        };
                    })
                };
            },
            cache: true
        },
        placeholder: "<?php _e('Search and select a manager', 'club-manager'); ?>",
        minimumInputLength: 2,
    });

    // Automatically populate email field when a member is selected
    var selectedUserName = ''; // Track the selected user name
    $('#member-name').on('select2:select', function(e) {
        var email = e.params.data.email;
        selectedUserName = e.params.data.text.split(' (')[0]; // Extract the user's name
        $('#member-email').val(email);
    });

    // Handle form submission with AJAX
    $('#add-club-member-form').on('submit', function(e) {
        e.preventDefault();

        // Collect form data
        var member_id = $('#member-name').val();
        var member_email = $('#member-email').val();
        var role = $('#role').val();

        // Send the user name, not just the ID
        var member_name = selectedUserName;

        // AJAX request to add the club member to the database
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'add_club_member',
                club_id: <?php echo $club_id; ?>, // Send the club_id from the URL
                club_name: '<?php echo $club_name; ?>',
                member_name: member_name, // Send the actual user name
                member_email: member_email,
                role: role,
            },
            success: function(response) {
                if (response.success) {
    alert('User added to ' + '<?php echo $club_name; ?>');
    // Optionally, you can clear the form fields here
    setTimeout(function() {
        location.reload();
    }, 300);
} else {
    alert('Failed to add the manager.');
}
            }
        });
    });

    // Handle dynamic table based on club selection
    function fetchClubMembers() {
        // AJAX request to fetch members based on the club_id
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'fetch_club_members',
                club_id: <?php echo $club_id; ?>, // Pass the club_id from the URL
            },
            success: function(response) {
                $('#club-members-list').html(response); // Update the table with the fetched data
            }
        });
    }

    // Fetch members when the page loads
    fetchClubMembers();
});
</script>


<script>
jQuery(document).ready(function($) {
    // Handle the delete member action
    $(document).on('click', '.delete-member', function(e) {
        e.preventDefault();

        var memberId = $(this).data('member-id');

        if (confirm('Are you sure you want to delete this manager?')) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'delete_club_member',
                    member_id: memberId
                },
                success: function(response) {
                    if (response.success) {
                        $('tr[data-member-id="' + memberId + '"]').remove(); // Remove the row from the table
                    } else {
                        alert('Failed to delete the manager.');
                    }
                }
            });
        }
    });
});
</script>
