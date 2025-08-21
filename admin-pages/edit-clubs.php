<?php
// Ensure to include this within a WooCommerce or WordPress environment
defined('ABSPATH') || exit;

// Enqueue WooCommerce or WordPress styles for tabs
function enqueue_global_styles_for_club_manager() {
    // Check if we are on the 'edit-clubs.php' page
    if (is_page('edit-clubs')) {
        // Enqueue WooCommerce and WordPress styles that include the default tab design
        wp_enqueue_style('woocommerce-general');
        wp_enqueue_style('woocommerce-layout');
        wp_enqueue_style('woocommerce-smallscreen');
        // Enqueue WordPress admin styles if needed
        wp_enqueue_style('wp-admin');
    }
}
add_action('wp_enqueue_scripts', 'enqueue_global_styles_for_club_manager');

// Check if 'club_id' is present in the URL
$club_id = isset($_GET['club_id']) ? intval($_GET['club_id']) : 0;

// Add WooCommerce-style tabs for the club management system
function club_manager_woocommerce_tabs($club_id) {
    ?>
    <div class="wrap">
        <h2 class="nav-tab-wrapper">
            <a href="#club-details" class="nav-tab nav-tab-active"><?php _e('Club Details', 'textdomain'); ?></a>
            
            <?php if ($club_id): // Only show these tabs if club_id is present ?>
                <a href="#eft-details" class="nav-tab"><?php _e('EFT Details', 'textdomain'); ?></a>
                <a href="#payment-gateways" class="nav-tab"><?php _e('Payment Gateways', 'textdomain'); ?></a>
                <a href="#add-club-members" class="nav-tab"><?php _e('Club Managers', 'textdomain'); ?></a>
                <a href="#add-club-notifications" class="nav-tab"><?php _e('Notifications', 'textdomain'); ?></a>
                <a href="#add-club-valuation" class="nav-tab"><?php _e('Valuation Form', 'textdomain'); ?></a>
                <a href="#add-club-event" class="nav-tab"><?php _e('Event Forms', 'textdomain'); ?></a>
                <a href="#add-club-product" class="nav-tab"><?php _e('Memebership Products', 'textdomain'); ?></a>
                <a href="#add-club-rides" class="nav-tab"><?php _e('Rides Forms', 'textdomain'); ?></a>
                <a href="#add-club-registration" class="nav-tab"><?php _e('Registration Forms', 'textdomain'); ?></a>
                
            <?php endif; ?>
        </h2>

        <div id="club-details" class="tab-content" style="display: block;">
            <?php include 'includes/club-details.php'; ?>
        </div>

        <?php if ($club_id): // Only show these content sections if club_id is present ?>
            <div id="eft-details" class="tab-content" style="display: none;">
                <?php include 'includes/eft-details.php'; ?>
            </div>
            <div id="payment-gateways" class="tab-content" style="display: none;">
                <h3><?php _e('Payment Gateways', 'textdomain'); ?></h3>
                <ul class="sub-tab-nav" style="display: flex; gap: 20px; margin-bottom: 20px; list-style:none;">
                    <li><a href="#" class="sub-tab-link sub-tab-active" data-target="yoco-gateway"><?php _e('Yoco & Other', 'textdomain'); ?></a></li>
                    <li><a href="#" class="sub-tab-link" data-target="payfast-gateway"><?php _e('PayFast', 'textdomain'); ?></a></li>
                    <li><a href="#" class="sub-tab-link" data-target="stripe-gateway"><?php _e('Stripe', 'textdomain'); ?></a></li>
                    <li><a href="#" class="sub-tab-link" data-target="yocomain-gateway"><?php _e('Stripe', 'textdomain'); ?></a></li>
                </ul>
                <div class="sub-tab-content" id="yoco-gateway" style="display: block;">
                    <?php include 'includes/payment-gateways.php'; ?>
                </div>
                <div class="sub-tab-content" id="payfast-gateway" style="display: none;">
                    <?php include 'includes/add-club-payfast.php'; ?>
                </div>
                <div class="sub-tab-content" id="stripe-gateway" style="display: none;">
                    <?php include 'includes/add-club-stripe.php'; ?>
                </div>
                <div class="sub-tab-content" id="yocomain-gateway" style="display: none;">
                    <?php include 'includes/add-club-yoco.php'; ?>
                </div>
            </div>

            <div id="add-club-members" class="tab-content" style="display: none;">
                <?php include 'includes/add-club-members.php'; ?>
            </div>
            <div id="add-club-notifications" class="tab-content" style="display: none;">
                <?php include 'includes/add-club-notifications.php'; ?>
            </div>
            <div id="add-club-valuation" class="tab-content" style="display: none;">
                <?php include 'includes/add-club-valuation.php'; ?>
            </div>
            <div id="add-club-event" class="tab-content" style="display: none;">
                <?php include 'includes/add-club-event.php'; ?>
            </div>
            <div id="add-club-product" class="tab-content" style="display: none;">
                <?php include 'includes/add-club-product.php'; ?>
            </div>
            <div id="add-club-rides" class="tab-content" style="display: none;">
                <?php include 'includes/add-club-rides.php'; ?>
            </div>
            <div id="add-club-registration" class="tab-content" style="display: none;">
                <?php include 'includes/add-club-registration.php'; ?>
            </div>
            
        <?php endif; ?>
    </div>

    <style>
        .tab-content {
            padding: 20px;
            margin-top: 10px;
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            let tabs = document.querySelectorAll('.nav-tab');
            let contents = document.querySelectorAll('.tab-content');

            tabs.forEach((tab, index) => {
                tab.addEventListener('click', function(e) {
                    e.preventDefault();

                    // Remove active class from all tabs and hide all contents
                    tabs.forEach(t => t.classList.remove('nav-tab-active'));
                    contents.forEach(c => c.style.display = 'none');

                    // Add active class to clicked tab and show corresponding content
                    this.classList.add('nav-tab-active');
                    document.querySelector(this.getAttribute('href')).style.display = 'block';
                });
            });
        });
    </script>

    <script>
document.addEventListener('DOMContentLoaded', function () {
    // Sub-tabs in Payment Gateways
    const subTabLinks = document.querySelectorAll('.sub-tab-link');
    const subTabContents = document.querySelectorAll('.sub-tab-content');

    // Define styles
    const activeStyles = "color:#0073aa;";
    const inactiveStyles = "color:black;";

    function updateTabStyles() {
        subTabLinks.forEach(link => {
            if (link.classList.contains('sub-tab-active')) {
                link.style.cssText = activeStyles;
            } else {
                link.style.cssText = inactiveStyles;
            }
        });
    }

    subTabLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            subTabLinks.forEach(l => l.classList.remove('sub-tab-active'));
            subTabContents.forEach(c => c.style.display = 'none');
            this.classList.add('sub-tab-active');
            document.getElementById(this.dataset.target).style.display = 'block';
            updateTabStyles();
        });
    });

    // Initial style update
    updateTabStyles();
});
</script>

    <?php
}

// Call the function to display the WooCommerce tabs
club_manager_woocommerce_tabs($club_id);
