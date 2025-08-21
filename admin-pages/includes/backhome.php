<?php



add_action('wp_footer', 'redirect_user_based_on_club');
function redirect_user_based_on_club() {
    global $wpdb;

    // Case 3: If the user is not logged in, redirect to home.
    if (!is_user_logged_in()) {
        ?>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const button = document.getElementById('back-home');
                if (button) {
                    button.addEventListener('click', function (e) {
                        e.preventDefault();
                        window.location.href = "/";
                    });
                }
            });
        </script>
        <?php
        return;
    }

    $user = wp_get_current_user();
    $user_email = $user->user_email;

    // Case 1: Check if the user is a club member.
    $club_member = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT club_id FROM wp_club_members WHERE user_email = %s",
            $user_email
        )
    );

    if ($club_member) {
        $club_id = $club_member->club_id;

        // Fetch the club URL for the club ID.
        $club = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT club_url FROM wp_clubs WHERE club_id = %d",
                $club_id
            )
        );

        if ($club && !empty($club->club_url)) {
            $redirect_url = esc_url($club->club_url);
            ?>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    const button = document.getElementById('back-home');
                    if (button) {
                        button.addEventListener('click', function (e) {
                            e.preventDefault();
                            console.log('Redirecting to: <?php echo $redirect_url; ?>');
                            window.location.href = "<?php echo $redirect_url; ?>";
                        });
                    }
                });
            </script>
            <?php
            return;
        }
    }

    // Case 2: If no club member is found, check WooCommerce membership.
    $user_id = get_current_user_id();

    // Get the product ID associated with the user's membership.
    $product_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT pm_product.meta_value 
            FROM wp_posts p
            LEFT JOIN wp_postmeta pm_product 
            ON p.ID = pm_product.post_id AND pm_product.meta_key = '_product_id'
            WHERE p.post_type = 'wc_user_membership'
            AND p.post_author = %d
            LIMIT 1",
            $user_id
        )
    );

    if ($product_id) {
        // Get the club URL based on the product's _select_club_id meta value.
        $club_url = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT c.club_url 
                FROM wp_clubs c
                WHERE c.club_id = (
                    SELECT pm.meta_value
                    FROM wp_postmeta pm
                    WHERE pm.post_id = %d
                    AND pm.meta_key = '_select_club_id'
                    LIMIT 1
                )",
                $product_id
            )
        );

        if ($club_url) {
            $redirect_url = esc_url($club_url);
            ?>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    const button = document.getElementById('back-home');
                    if (button) {
                        button.addEventListener('click', function (e) {
                            e.preventDefault();
                            console.log('Redirecting to: <?php echo $redirect_url; ?>');
                            window.location.href = "<?php echo $redirect_url; ?>";
                        });
                    }
                });
            </script>
            <?php
            return;
        }
    }

    // Case 3: If no club or membership is found, redirect to home.
    ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const button = document.getElementById('back-home');
            if (button) {
                button.addEventListener('click', function (e) {
                    e.preventDefault();
                    console.log('Redirecting to home.');
                    window.location.href = "/";
                });
            }
        });
    </script>
    <?php
}
