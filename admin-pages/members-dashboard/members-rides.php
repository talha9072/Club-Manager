<?php
function get_logged_in_user_id() {
    return is_user_logged_in() ? get_current_user_id() : false;
}

function get_logged_in_user_email() {
    $user = wp_get_current_user();
    return ($user && $user->exists()) ? $user->user_email : false;
}

// Function to get the logged-in user's club_id
function get_user_club_id() {
    global $wpdb;
    $product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : null;
    if ($product_id) {
        $club_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT meta_value 
                 FROM {$wpdb->prefix}postmeta 
                 WHERE post_id = %d 
                 AND meta_key = '_select_club_id'",
                $product_id
            )
        );
        if ($club_id) {
            return [$club_id];
        } else {
            return false;
        }
    }

    // Default behavior: Get the logged-in user's email
    $user_email = get_logged_in_user_email();

    if (!$user_email) {
        return false; // No user is logged in
    }

    // Query to fetch club_id(s) based on email
    $query = "
        SELECT club_id 
        FROM {$wpdb->prefix}club_members 
        WHERE user_email = %s
    ";

    // Execute the query and fetch multiple club IDs (if user belongs to multiple clubs)
    $club_ids = $wpdb->get_col($wpdb->prepare($query, $user_email));

    return !empty($club_ids) ? $club_ids : false;
}
function get_rides_form_ids($club_ids) {
    global $wpdb;

    if (!$club_ids || !is_array($club_ids)) {
        return false; 
    }
    $query = "
        SELECT club_id, rides_form 
        FROM {$wpdb->prefix}clubs 
        WHERE club_id IN (" . implode(',', array_map('intval', $club_ids)) . ")
    ";
    $results = $wpdb->get_results($query, ARRAY_A);

    $rides_forms = [];
    foreach ($results as $row) {
        $form_ids = explode(',', $row['rides_form']);
        foreach ($form_ids as $form_id) {
            $form_id = trim($form_id);
            if (!empty($form_id)) {
                $rides_forms[] = [
                    'club_id' => $row['club_id'],
                    'form_id' => $form_id
                ];
            }
        }
    }

    return !empty($rides_forms) ? $rides_forms : false;
}

function display_rides_form() {
    $club_ids = get_user_club_id(); 
    if (!$club_ids) {
        echo "No club found for the logged-in user.";
        return;
    }
    $rides_forms = get_rides_form_ids($club_ids);
    if (!$rides_forms) {
        echo "No rides form found for the user's club.";
        return;
    }
    if (count($rides_forms) === 1) {
        $form_id = $rides_forms[0]['form_id'];
        echo do_shortcode("[gravityform id='{$form_id}' title='true' description='false' ajax='true']"); 
        return;
    }
    echo '
    <div style="text-align:center; margin-bottom: 15px;">
        <button id="switch-form" onclick="switchForm()"class="All-button" style=" border:none; cursor:pointer; font-size:16px;">Switch Ride Type</button>
    </div>';
    echo '<div id="form-container">';

    foreach ($rides_forms as $index => $form) {
        $form_id = $form['form_id'];
        $club_id = $form['club_id'];
        echo "<div class='rides-form' id='form-$index' style='display: " . ($index === 0 ? 'block' : 'none') . ";'>";
        echo do_shortcode("[gravityform id='{$form_id}' title='true' description='false' ajax='true']");
        echo "</div>";
    }

    echo '</div>';
    echo "<script>
        var currentForm = 0;
        function switchForm() {
            var forms = document.querySelectorAll('.rides-form');
            forms[currentForm].style.display = 'none';
            currentForm = (currentForm + 1) % forms.length;
            forms[currentForm].style.display = 'block';
        }
    </script>";
}

display_rides_form();
?>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const dashboardContent = document.querySelector('.dashboard-content');

        if (!dashboardContent) return;

        const observer = new MutationObserver((mutations, obs) => {
            const titleEl = dashboardContent.querySelector('#gform_wrapper_8 .gform_title');

            if (titleEl && titleEl.innerText.trim() === 'Rides: Vehicle') {
                titleEl.innerText = 'Rides';
                obs.disconnect(); // Stop observing once updated
            }
        });

        observer.observe(dashboardContent, {
            childList: true,
            subtree: true,
        });
    });

    document.addEventListener('DOMContentLoaded', function () {
        const dashboardContent = document.querySelector('.dashboard-content');

        if (!dashboardContent) return;

        const observer = new MutationObserver((mutations, obs) => {
            const titleEl = dashboardContent.querySelector('#gform_wrapper_14 .gform_title');

            if (titleEl && titleEl.innerText.trim() === 'Rides: Bikes') {
                titleEl.innerText = 'Rides';
                obs.disconnect(); // Stop observing once updated
            }
        });

        observer.observe(dashboardContent, {
            childList: true,
            subtree: true,
        });
    });
</script>
