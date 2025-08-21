<?php
// File: /includes/user-meta-form.php

if (!defined('ABSPATH')) {
    exit;
}
function append_person_id_and_show_form() {
    global $wpdb;
    if (!is_user_logged_in()) {
        return '<p>You must be logged in to access this functionality.</p>';
    }
    $user_id = get_current_user_id();
    if (!isset($_GET['person_id'])) {
        $current_url = home_url(add_query_arg(['person_id' => $user_id]));
        wp_redirect($current_url);
        exit;
    }
    $registration_form = get_logged_in_user_registration_form();
    if (is_numeric($registration_form) && $registration_form > 0) {
        echo do_shortcode('[gravityform id="' . esc_attr($registration_form) . '" title="true" description="true"]');
    } else {
        echo "<p>No registration form available.</p>";
    }
    // Check if subscription_id and product_id exist in the URL
    $subscription_id = isset($_GET['subscription_id']) ? intval($_GET['subscription_id']) : null;
    $product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : null;

    if ($subscription_id && $product_id) {
        // Fetch subscription details directly based on URL parameters
        $subscription_data = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT 
                    sub.ID AS subscription_id,
                    order_meta.meta_value AS product_id,
                    prod_post.post_title AS subscription_plan
                FROM {$wpdb->posts} sub
                INNER JOIN {$wpdb->prefix}woocommerce_order_items order_items 
                    ON sub.ID = order_items.order_id
                INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta order_meta 
                    ON order_items.order_item_id = order_meta.order_item_id
                INNER JOIN {$wpdb->posts} prod_post 
                    ON prod_post.ID = order_meta.meta_value
                WHERE sub.post_type = 'shop_subscription'
                AND sub.ID = %d
                AND order_meta.meta_key = '_product_id'
                AND order_meta.meta_value = %d
                LIMIT 1",
                $subscription_id,
                $product_id
            ),
            ARRAY_A
        );

        $subscription_details = !empty($subscription_data) ? $subscription_data[0] : null;

        // Fetch next payment and schedule end date if found
        if ($subscription_details) {
            $next_payment_date = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_schedule_next_payment'",
                    $subscription_id
                )
            );

            $schedule_end = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_schedule_end'",
                    $subscription_id
                )
            );

            // Store values in the array
            $subscription_details['next_payment_date'] = $next_payment_date;
            $subscription_details['schedule_end'] = $schedule_end;
        }
    } else {
        // Fallback: Fetch the first subscription for the user
        $subscription_data = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT 
                    sub.ID AS subscription_id,
                    order_meta.meta_value AS product_id,
                    prod_post.post_title AS subscription_plan
                FROM {$wpdb->posts} sub
                INNER JOIN {$wpdb->postmeta} customer_user 
                    ON sub.ID = customer_user.post_id 
                    AND customer_user.meta_key = '_customer_user'
                INNER JOIN {$wpdb->prefix}woocommerce_order_items order_items 
                    ON sub.ID = order_items.order_id
                INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta order_meta 
                    ON order_items.order_item_id = order_meta.order_item_id
                INNER JOIN {$wpdb->posts} prod_post 
                    ON prod_post.ID = order_meta.meta_value
                WHERE sub.post_type = 'shop_subscription'
                AND customer_user.meta_value = %d
                AND order_meta.meta_key = '_product_id'
                LIMIT 1",
                $user_id
            ),
            ARRAY_A
        );

        $subscription_details = !empty($subscription_data) ? $subscription_data[0] : null;

        // Fetch next payment and schedule end date separately
        if ($subscription_details) {
            $subscription_id = $subscription_details['subscription_id'];

            $next_payment_date = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_schedule_next_payment'",
                    $subscription_id
                )
            );

            $schedule_end = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_schedule_end'",
                    $subscription_id
                )
            );

            // Store values in the array
            $subscription_details['next_payment_date'] = $next_payment_date;
            $subscription_details['schedule_end'] = $schedule_end;
        }
    }

        
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_password'])) {
        $user_id = get_current_user_id();
        $new_password = sanitize_text_field($_POST['new_password']);

        if (!empty($new_password)) {
            // Get the 'club' value from URL (if any)
            $club_name = isset($_GET['club']) ? trim($_GET['club']) : '';

            // Default redirect to homepage
            $redirect_url = home_url('/');

            if (!empty($club_name)) {
                global $wpdb;

                // Prepare for safe comparison (case-insensitive, remove pluses and decode if needed)
                $club_name_clean = str_replace('+', ' ', $club_name);
                $club_name_clean = urldecode($club_name_clean);

                // Fetch club_url from wp_clubs
                $club_url = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT club_url FROM wp_clubs WHERE TRIM(LOWER(club_name)) = TRIM(LOWER(%s)) LIMIT 1",
                        $club_name_clean
                    )
                );

                // If found and not empty, set as redirect URL
                if (!empty($club_url)) {
                    $redirect_url = $club_url;
                }
            }

            wp_set_password($new_password, $user_id);
            wp_logout(); // Destroy session

            // Hard redirect (WordPress will not intercept this)
            wp_redirect($redirect_url);
            exit;
        } else {
            echo '<p style="color:red;"> Please enter a valid password.</p>';
        }
    }

    ?>

    <?php
    $next_payment_formatted = !empty($subscription_details['next_payment_date']) 
        ? date('Y-m-d', strtotime($subscription_details['next_payment_date'])) 
        : '';

    $schedule_end_formatted = !empty($subscription_details['schedule_end']) 
        ? date('Y-m-d', strtotime($subscription_details['schedule_end'])) 
        : '';
    ?>

    <form id="edit-user-subscription-form" class="edit-user-form" method="post" style="margin-top:30px">
        <!-- Subscription Details -->
        <h2 style="font-weight: bold; font-size: 28px; color: #262626; margin-bottom:25px">Membership Details</h2>
        <?php if ($subscription_details) : ?>
            <div class="form-row membership-row">
                <div class="form-group">
                    <label class="user-label" style="padding-bottom:0px !important">Current Membership</label>
                    <h6><?php echo esc_html($subscription_details['subscription_plan'] ?? 'N/A'); ?></h6>
                </div>
                
            </div>
            
            <div class="form-row membership-row">
                
                <div class="form-group">
                    <label class="user-label">Next Payment Date</label>
                    <input type="date" name="next_payment_date" value="<?php echo esc_attr($next_payment_formatted); ?>" readonly>
                </div>
                <div class="form-group">
                <label class="user-label">End Date</label>
                <input type="date" name="schedule_end" value="<?php echo esc_attr($schedule_end_formatted); ?>" readonly>
                </div>

            </div>
        <?php else : ?>
            <p>No Membership details available.</p>
        <?php endif; ?>

        
    </form>

    <!-- Password Update Form -->
    <form method="post" style="margin-top: 40px;">
        <h2 style="font-weight: bold; font-size: 28px; margin-bottom:10px; color: #262626;">Update Password</h2>
        <div class="form-group" style="position: relative; max-width: 400px;">
            <label class="user-label" for="new_password">Set New Password</label>
            <input type="password" name="new_password" id="new_password" required
                style="padding: 8px; width: 100%; max-width: 400px;" onkeyup="isGood(this.value)">
            <span id="togglePassword" style="position: absolute; right: 12px; top: 42px; cursor: pointer; font-size: 25px; color: #666;">&#128065;</span>
            <div style="width: 100%; margin-top: 8px;">
                <div id="password-bar-container" style="background: #e5e5e5; border-radius: 3px; height: 8px; width: 100%; overflow: hidden;">
                    <div id="password-bar" style="height: 100%; width: 0%; background: #e74c3c; border-radius: 3px; transition: width 0.3s, background 0.3s;"></div>
                </div>
                <small class="help-block" id="password-text" style="display:block; margin-top:6px;"></small>
            </div>
            <!-- Note about logout -->
            <div style="margin-top: 14px;">
                <div style="
                    background: #f5fafc;
                    border-left: 4px solid #1e7bb7;
                    color: #23639e;
                    padding: 10px 15px;
                    border-radius: 5px;
                    font-size: 15px;
                    margin-top: 10px;
                    max-width: 400px;
                    font-family: 'BMWTypeNext-Regular', sans-serif !important;
                    ">
                    <strong>Note:</strong> For your security, you will be automatically logged out after updating your password. Please log in again using your new password.
                </div>
            </div>
        </div>
        <div style="margin-top: 12px;">
            <ul style="padding-left: 20px; font-size: 14px; margin-bottom: 0;">
                <li id="ucase" style="color: #e5e5e5;">Uppercase Letters</li>
                <li id="lcase" style="color: #e5e5e5;">Lowercase Letters</li>
                <li id="num" style="color: #e5e5e5;">Numbers</li>
                <li id="spchar" style="color: #e5e5e5;">Special Character</li>
            </ul>
        </div>
        <button type="submit" id="password-submit" class="btn btn-primary" style="margin-top: 15px;">UPDATE PASSWORD</button>
    </form>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const passwordInput = document.getElementById('new_password');
            const togglePassword = document.getElementById('togglePassword');
            const passwordBar = document.getElementById('password-bar');
            const passwordText = document.getElementById('password-text');
            const submitBtn = document.getElementById('password-submit');
            const ucase = document.getElementById('ucase');
            const lcase = document.getElementById('lcase');
            const num = document.getElementById('num');
            const spchar = document.getElementById('spchar');

            // Eye icon toggle
            togglePassword.addEventListener('click', function () {
                const type = passwordInput.type === 'password' ? 'text' : 'password';
                passwordInput.type = type;
                this.style.color = type === 'text' ? '#0767b1' : '#666';
            });

            // Initial submit button state
            submitBtn.disabled = true;
            submitBtn.style.opacity = 0.6;
            submitBtn.style.cursor = 'not-allowed';

            window.isGood = function(password) {
                let passed = 0;
                const green = '#37a500';
                const grey = '#888';

                // Check each requirement and set color on list
                if (/[A-Z]/.test(password)) {
                    ucase.style.color = green;
                    passed++;
                } else {
                    ucase.style.color = grey;
                }

                if (/[a-z]/.test(password)) {
                    lcase.style.color = green;
                    passed++;
                } else {
                    lcase.style.color = grey;
                }

                if (/[0-9]/.test(password)) {
                    num.style.color = green;
                    passed++;
                } else {
                    num.style.color = grey;
                }

                if (/[$@$!%*#?&]/.test(password)) {
                    spchar.style.color = green;
                    passed++;
                } else {
                    spchar.style.color = grey;
                }

                // Display bar and status
                let strength = "";
                let width = "0%";
                let color = "#c0392b"; // dark red

                if (password.length === 0) {
                    passwordBar.style.width = width;
                    passwordBar.style.background = color;
                    passwordText.innerHTML = "";
                    submitBtn.disabled = true;
                    submitBtn.style.opacity = 0.6;
                    submitBtn.style.cursor = 'not-allowed';
                    return;
                }

                switch (passed) {
                    case 0:
                    case 1:
                    case 2:
                        strength = "<span style='color:#c0392b;'>Weak</span>";
                        width = "40%";
                        color = "#c0392b";
                        break;
                    case 3:
                        strength = "<span style='color:#b9770e;'>Medium</span>";
                        width = "70%";
                        color = "#b9770e";
                        break;
                    case 4:
                        strength = "<span style='color:#186a3b;'>Strong</span>";
                        width = "100%";
                        color = "#37a500";
                        break;
                }

                passwordBar.style.width = width;
                passwordBar.style.background = color;
                passwordText.innerHTML = "Strength: " + strength;

                // Enable submit ONLY when all 4 rules met and at least 8 chars
                if (passed === 4 && password.length >= 8) {
                    submitBtn.disabled = false;
                    submitBtn.style.opacity = 1;
                    submitBtn.style.cursor = '';
                } else {
                    submitBtn.disabled = true;
                    submitBtn.style.opacity = 0.6;
                    submitBtn.style.cursor = 'not-allowed';
                }
            };
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const dashboardContent = document.querySelector('.dashboard-content');

            if (!dashboardContent) return;

            const observer = new MutationObserver((mutations, obs) => {
                const titleEl = dashboardContent.querySelector('#gform_wrapper_2 .gform_title');

                if (titleEl && titleEl.innerText.trim() === 'Membership Registration - Global') {
                    titleEl.innerText = 'Profile';
                    obs.disconnect(); // Stop observing once updated
                }
            });

            observer.observe(dashboardContent, {
                childList: true,
                subtree: true,
            });
        });
    </script>


        <?php
}

append_person_id_and_show_form();

function get_logged_in_user_registration_form() {
    if (!is_user_logged_in()) {
        return 'User not logged in';
    }

    global $wpdb;
    $user_id = get_current_user_id();
    $product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : null;
    if ($product_id) {
        $result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT pm_club.meta_value AS club_id
                 FROM {$wpdb->postmeta} pm_club
                 WHERE pm_club.post_id = %d
                   AND pm_club.meta_key = '_select_club_id'",
                $product_id
            ),
            ARRAY_A
        );

        if (!$result || empty($result['club_id'])) {
            return 'No associated club found for the provided product ID';
        }

    } else {
        $result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT 
                    order_meta.meta_value AS product_id,
                    pm_club.meta_value AS club_id
                 FROM {$wpdb->posts} sub
                 INNER JOIN {$wpdb->postmeta} postmeta_customer 
                    ON sub.ID = postmeta_customer.post_id 
                    AND postmeta_customer.meta_key = '_customer_user'
                 INNER JOIN {$wpdb->prefix}woocommerce_order_items order_items 
                    ON sub.ID = order_items.order_id
                 INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta order_meta 
                    ON order_items.order_item_id = order_meta.order_item_id
                 LEFT JOIN {$wpdb->postmeta} pm_club 
                    ON order_meta.meta_value = pm_club.post_id 
                    AND pm_club.meta_key = '_select_club_id'
                 WHERE sub.post_type = 'shop_subscription'
                   AND postmeta_customer.meta_value = %d
                   AND order_meta.meta_key = '_product_id'
                 LIMIT 1",
                $user_id
            ),
            ARRAY_A
        );

        // If no product or club found, return an error
        if (!$result || empty($result['club_id'])) {
            return 'No associated product or club found';
        }
    }
    $club_id = $result['club_id'];
    $registration_form = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT registration_form 
             FROM wp_clubs 
             WHERE club_id = %d",
            $club_id
        )
    );
    return $registration_form ? $registration_form : 'No registration form found';
}




