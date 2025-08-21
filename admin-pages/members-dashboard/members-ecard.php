<?php
// File: /includes/members-ecard.php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Function to fetch the club logo based on product ID
function fetch_product_club_logo($product_id = null) {
    global $wpdb;
    if (isset($_GET['product_id']) && is_numeric($_GET['product_id'])) {
        $product_id = intval($_GET['product_id']);
    }
    if (!$product_id) {
        return '';
    }

    $club_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT meta_value 
             FROM {$wpdb->prefix}postmeta 
             WHERE post_id = %d 
             AND meta_key = '_select_club_id' 
             LIMIT 1",
            $product_id
        )
    );

    if (!$club_id) {
        return ''; 
    }

    $club_logo = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT club_logo 
             FROM wp_clubs 
             WHERE club_id = %d 
             LIMIT 1",
            $club_id
        )
    );
    return $club_logo ? esc_url($club_logo) : '';
}

/**
 * Fetches ECard details for the logged-in user from WooCommerce Subscriptions.
 */
function fetch_ecard_details($user_id) {
    global $wpdb;
    // Fetch subscription details with optional URL parameters
    $subscription_id = isset($_GET['subscription_id']) ? intval($_GET['subscription_id']) : null;
    $product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : null;

    if ($subscription_id && $product_id) {
        $subscription = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT DISTINCT 
                    u.ID AS user_id, 
                    u.user_email AS email, 
                    u.display_name AS name,
                    sub.ID AS subscription_id,
                    sub.post_status AS subscription_status,
                    sub.post_date,  
                    pm_start.meta_value AS schedule_start,
                    pm_end.meta_value AS schedule_end,
                    prod.ID AS product_id,
                    prod.post_title AS product_name
                FROM {$wpdb->prefix}users u
                INNER JOIN {$wpdb->prefix}postmeta pm_customer 
                    ON u.ID = pm_customer.meta_value 
                    AND pm_customer.meta_key = '_customer_user'
                INNER JOIN {$wpdb->prefix}posts sub 
                    ON pm_customer.post_id = sub.ID 
                    AND sub.post_type = 'shop_subscription'
                INNER JOIN {$wpdb->prefix}woocommerce_order_items oi 
                    ON sub.ID = oi.order_id
                INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta pm_product 
                    ON oi.order_item_id = pm_product.order_item_id 
                    AND pm_product.meta_key = '_product_id'
                INNER JOIN {$wpdb->prefix}posts prod 
                    ON pm_product.meta_value = prod.ID
                LEFT JOIN {$wpdb->prefix}postmeta pm_start 
                    ON sub.ID = pm_start.post_id 
                    AND pm_start.meta_key = '_schedule_start'
                LEFT JOIN {$wpdb->prefix}postmeta pm_end 
                    ON sub.ID = pm_end.post_id 
                    AND pm_end.meta_key = '_schedule_end'
                WHERE sub.ID = %d
                AND prod.ID = %d
                AND sub.post_status = 'wc-active'
                LIMIT 1",
                $subscription_id,
                $product_id
            ),
            ARRAY_A
        );
    } else {
        // Fallback: fetch the first active subscription for the given user ID
        $subscription = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT DISTINCT 
                    u.ID AS user_id, 
                    u.user_email AS email, 
                    u.display_name AS name,
                    sub.ID AS subscription_id,
                    sub.post_status AS subscription_status,
                    sub.post_date,  
                    pm_start.meta_value AS schedule_start,
                    pm_end.meta_value AS schedule_end,
                    prod.ID AS product_id,
                    prod.post_title AS product_name
                FROM {$wpdb->prefix}users u
                INNER JOIN {$wpdb->prefix}postmeta pm_customer 
                    ON u.ID = pm_customer.meta_value 
                    AND pm_customer.meta_key = '_customer_user'
                INNER JOIN {$wpdb->prefix}posts sub 
                    ON pm_customer.post_id = sub.ID 
                    AND sub.post_type = 'shop_subscription'
                INNER JOIN {$wpdb->prefix}woocommerce_order_items oi 
                    ON sub.ID = oi.order_id
                INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta pm_product 
                    ON oi.order_item_id = pm_product.order_item_id 
                    AND pm_product.meta_key = '_product_id'
                INNER JOIN {$wpdb->prefix}posts prod 
                    ON pm_product.meta_value = prod.ID
                LEFT JOIN {$wpdb->prefix}postmeta pm_start 
                    ON sub.ID = pm_start.post_id 
                    AND pm_start.meta_key = '_schedule_start'
                LEFT JOIN {$wpdb->prefix}postmeta pm_end 
                    ON sub.ID = pm_end.post_id 
                    AND pm_end.meta_key = '_schedule_end'
                WHERE u.ID = %d
                AND sub.post_status = 'wc-active'
                LIMIT 1",
                $user_id
            ),
            ARRAY_A
        );
    }

    $subscription_id = $subscription['subscription_id'] ?? 'N/A';
    $product_id = $subscription['product_id'] ?? 0;
    $club_name = get_post_meta($product_id, '_select_club_name', true) ?: '';
    $club_param = urlencode($club_name);
    $subscription_plan = $subscription['product_name'] ?? 'N/A';
    $expiry_date = $subscription['schedule_end'] ?? 'N/A';
    $first_name = get_user_meta($user_id, 'first_name', true) ?: '';
    $last_name = get_user_meta($user_id, 'last_name', true) ?: '';

   // Step 1: Get product_id from URL
    $product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;

    // Default placeholders
    $ice_contact_name = 'N/A';
    $ice_contact_number = 'N/A';
    $partner_first_name = 'N/A';
    $partner_last_name = 'N/A';

    if ($product_id && is_user_logged_in()) {
        $user_id = get_current_user_id();
        $user_info = get_userdata($user_id);
        $user_email = $user_info ? $user_info->user_email : '';

        if ($user_email) {
            global $wpdb;
            $club_id = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT meta_value FROM {$wpdb->prefix}postmeta
                    WHERE post_id = %d AND meta_key = '_select_club_id'
                    LIMIT 1",
                    $product_id
                )
            );

            if ($club_id) {
                // Step 3: Get registration_form from wp_clubs
                $form_id = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT registration_form FROM wp_clubs WHERE club_id = %d LIMIT 1",
                        $club_id
                    )
                );

                if ($form_id) {
                    // Step 4: Find latest entry by email in field ID 7
                    $latest_entry_id = $wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT e.id
                            FROM {$wpdb->prefix}gf_entry e
                            INNER JOIN {$wpdb->prefix}gf_entry_meta em ON e.id = em.entry_id
                            WHERE e.form_id = %d
                            AND em.meta_key = 7
                            AND em.meta_value = %s
                            ORDER BY e.date_created DESC
                            LIMIT 1",
                            $form_id,
                            $user_email
                        )
                    );

                    if ($latest_entry_id) {
                        // Step 5: Fetch entry meta
                        $entry_meta = $wpdb->get_results(
                            $wpdb->prepare(
                                "SELECT meta_key, meta_value
                                FROM {$wpdb->prefix}gf_entry_meta
                                WHERE entry_id = %d",
                                $latest_entry_id
                            ),
                            ARRAY_A
                        );

                        $meta_data = [];
                        foreach ($entry_meta as $meta) {
                            $meta_data[$meta['meta_key']] = $meta['meta_value'];
                        }

                        // Step 6: Extract ICE and Partner fields
                        $ice_contact_name = $meta_data[84] ?? 'N/A';
                        $ice_contact_number = $meta_data[85] ?? 'N/A';
                        $partner_first_name = $meta_data[4] ?? 'N/A';
                        $partner_last_name = $meta_data[5] ?? 'N/A';
                    }
                }
            }
        }
    }
    
    $club_logo = fetch_product_club_logo($product_id);

    // Generate QR Code URL with subscription_id from URL or default
    $subscription_id_param = isset($_GET['subscription_id']) ? intval($_GET['subscription_id']) : $subscription_id;
    $qr_code_url = home_url("/check-membership/?userID={$user_id}&subscriptionID={$subscription_id_param}&club={$club_param}");



    return [
        'subscription_id'    => $subscription_id,
        'subscription_plan'  => $subscription_plan,
        'expiry_date'        => $expiry_date,
        'full_name'          => trim("$first_name $last_name"),
        'club_logo'          => $club_logo,
        'qr_code_url'        => $qr_code_url,
        'ice_name'           => $ice_contact_name,
        'ice_number'         => $ice_contact_number,
        'partner_first_name' => $partner_first_name,
        'partner_last_name'  => $partner_last_name,
    ];
}

/**
 * Generates a downloadable PDF of the ECard using jsPDF and html2canvas.
 */
function render_download_pdf_script() {
    ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('download-ecard').addEventListener('click', function() {
            const eCardElement = document.getElementById('ecard-container');
            const originalTransform = eCardElement.style.transform || '';
            const originalMarginLeft = eCardElement.style.marginLeft || '';
            const originalScale = eCardElement.style.scale || '';

            eCardElement.style.transform = 'none';       
            eCardElement.style.marginLeft = '0px';       
            eCardElement.style.scale = '1';               
            eCardElement.style.width = '600px';         
            eCardElement.style.minWidth = '600px';        
            eCardElement.style.maxWidth = '600px';

            html2canvas(eCardElement, {
                scale: 3, 
                useCORS: true,
                allowTaint: false,
                logging: false
            }).then(canvas => {
                const imgData = canvas.toDataURL('image/png');
                const pdf = new jspdf.jsPDF('p', 'mm', 'a4');

                const pdfWidth = pdf.internal.pageSize.getWidth();
                const pdfHeight = (canvas.height * pdfWidth) / canvas.width;

                pdf.addImage(imgData, 'PNG', 0, 0, pdfWidth, pdfHeight, '', 'FAST');
                pdf.save('membership_ecard.pdf');

                eCardElement.style.transform = originalTransform;
                eCardElement.style.marginLeft = originalMarginLeft;
                eCardElement.style.scale = originalScale;
            });
        });
    });
    </script>
    <?php
}


/**
 * Renders the ECard HTML
 */
function render_user_ecard() {
    if (!is_user_logged_in()) {
        return '<p>You must be logged in to view your ECard.</p>';
    }

    $user_id = get_current_user_id();
    $ecard_details = fetch_ecard_details($user_id);
    ?>
    <h2 style="font-weight: bold; font-size: 28px; color: #262626; margin-bottom: 20px;">eCard</h2>
    <div id="ecard-container" style="border: 8px solid #c8c8c8; padding: 30px 50px; max-width: 600px;min-width: 600px; overflow-x: auto;   margin-bottom: 60px; background-color: #fff;">
        <div class="ecard-left">
            <!-- Left Side: Club Logo -->
            <div class="ecard-logo">
                <img src="<?php echo esc_url($ecard_details['club_logo']); ?>" alt="Club Logo" style="width: 200px; height: auto;">
            </div>

            <!-- Subscription & ICE Details -->
            <div class="ecard-details">
                <p ><?php echo esc_html($ecard_details['subscription_plan']); ?></p>
                <p ><span>Exp:</span> 
                    <?php 
                        echo esc_html(date("d/m/Y", strtotime($ecard_details['expiry_date']))); 
                    ?>
                </p>

                <p >
                    <span>ICE:</span> 
                    <?php echo esc_html($ecard_details['ice_name']); ?> 
                    <?php echo esc_html($ecard_details['ice_number']); ?>
                </p>
            </div>
        </div>

        <!-- Right Side: User Info -->
        <div class="ecard-right">
            <p class="ecard-name">
            <strong><?php echo esc_html($ecard_details['full_name']); ?></strong>
            </p>
            <p class="ecard-mem">Mem#:<?php echo esc_html($ecard_details['subscription_id']); ?></p>
            <p class="ecard-partner">
                <span>Partner:</span> 
                <?php echo esc_html($ecard_details['partner_first_name'] . ' ' . $ecard_details['partner_last_name']); ?>
            </p>

            <!-- QR Code -->
            <div class="ecard-img-div">
                <img 
                    src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=<?php echo urlencode($ecard_details['qr_code_url']); ?>" 
                    alt="QR Code" 
                    crossorigin="anonymous"
                >
            </div>
        </div>
    </div>

    <!-- Download Button -->
    <div style=" margin-top: 50px;">
        <h2 style="font-weight: bold; font-size: 28px; color: #262626; margin-bottom: 10px;">Print Your eCard Details</h2>
    <button id="download-ecard"class="All-button" style=" border: none; cursor: pointer;">
    <i class="fas fa-download" style="margin-right: 5px;"></i> Download PDF
    </button>
    </div>
    
    <?php render_download_pdf_script(); ?>
    <?php
}

// Display the ECard immediately when this file is loaded
render_user_ecard();


