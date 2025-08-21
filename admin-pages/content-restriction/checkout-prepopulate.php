<?php
/**
 * Gravity Forms to WooCommerce Auto-Fill (Per User Session)
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

add_action('wp_footer', function() {
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function ($) {
        console.log("✅ Script Loaded - Listening for Input Changes");

        // Generate Unique Session ID for Non-Logged-In Users
        function getSessionID() {
            let sessionID = getCookie('gform_session_id');
            if (!sessionID) {
                sessionID = 'session_' + Math.random().toString(36).substring(2, 15);
                document.cookie = `gform_session_id=${sessionID}; path=/; max-age=3600`;
                console.log(`🔑 New Session Created: ${sessionID}`);
            }
            return sessionID;
        }

        // Function to Set Cookie with Unique Session ID
        function setCookie(name, value) {
            let sessionID = getSessionID();
            document.cookie = `${name}_${sessionID}=${encodeURIComponent(value)}; path=/; max-age=3600`;
            console.log(`🍪 Cookie Set: ${name}_${sessionID} = ${value}`);
        }

        // Function to Get Cookie by Name
        function getCookie(name) {
            let match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
            return match ? decodeURIComponent(match[2]) : null;
        }

        // Function to Delete Old Cookies When New Form is Submitted
        function deleteOldCookies() {
            let sessionID = getSessionID();
            let cookiesToDelete = [
                `gform_first_name_${sessionID}`, `gform_last_name_${sessionID}`, `gform_email_${sessionID}`, `gform_phone_${sessionID}`,
                `gform_address1_${sessionID}`, `gform_address2_${sessionID}`, `gform_city_${sessionID}`, `gform_state_${sessionID}`, `gform_zip_${sessionID}`
            ];
            cookiesToDelete.forEach(cookie => {
                document.cookie = cookie + '=; path=/; expires=Thu, 01 Jan 1970 00:00:01 UTC;';
                console.log(`🗑️ Old Cookie Deleted: ${cookie}`);
            });
        }

        // Capture Changes in Gravity Form Fields & Store in Cookies
        function captureFormData() {
            console.log("🎯 Capturing Gravity Form Data");

            let firstName = $('input[name="input_2"]').val() || '';
            let lastName = $('input[name="input_3"]').val() || '';
            let email = $('input[name="input_7"]').val() || '';
            let phone = $('input[name="input_8"]').val() || '';
            let address1 = $('input[name="input_10\\.1"]').val() || '';
            let address2 = $('input[name="input_10\\.2"]').val() || '';
            let city = $('input[name="input_10\\.3"]').val() || '';
            let state = $('input[name="input_10\\.4"]').val() || '';
            let zip = $('input[name="input_10\\.5"]').val() || '';

            // Delete Old Cookies Before Saving New One
            deleteOldCookies();

            setCookie('gform_first_name', firstName);
            setCookie('gform_last_name', lastName);
            setCookie('gform_email', email);
            setCookie('gform_phone', phone);
            setCookie('gform_address1', address1);
            setCookie('gform_address2', address2);
            setCookie('gform_city', city);
            setCookie('gform_state', state);
            setCookie('gform_zip', zip);
        }

        // Listen for Input Changes on the Gravity Form
        $('body').on('keyup change', 'input[name="input_2"], input[name="input_3"], input[name="input_7"], input[name="input_8"], input[name="input_10\\.1"], input[name="input_10\\.2"], input[name="input_10\\.3"], input[name="input_10\\.4"], input[name="input_10\\.5"]', function() {
            captureFormData();
        });

        // Pre-Fill WooCommerce Checkout Fields
        function prefillCheckoutFields() {
            console.log("🛒 Checking WooCommerce Checkout Autofill...");

            let sessionID = getSessionID();
            let fields = {
                "billing_first_name": getCookie(`gform_first_name_${sessionID}`),
                "billing_last_name": getCookie(`gform_last_name_${sessionID}`),
                "billing_email": getCookie(`gform_email_${sessionID}`),
                "billing_phone": getCookie(`gform_phone_${sessionID}`),
                "billing_address_1": getCookie(`gform_address1_${sessionID}`),
                "billing_address_2": getCookie(`gform_address2_${sessionID}`),
                "billing_city": getCookie(`gform_city_${sessionID}`),
                "billing_state": getCookie(`gform_state_${sessionID}`),
                "billing_postcode": getCookie(`gform_zip_${sessionID}`),
            };

            console.log("🍪 Retrieved Cookies for Checkout:", fields);

            if (fields["billing_first_name"]) $('input#billing_first_name').val(fields["billing_first_name"]);
            if (fields["billing_last_name"]) $('input#billing_last_name').val(fields["billing_last_name"]);
            if (fields["billing_email"]) $('input#billing_email').val(fields["billing_email"]);
            if (fields["billing_phone"]) $('input#billing_phone').val(fields["billing_phone"]);
            if (fields["billing_address_1"]) $('input#billing_address_1').val(fields["billing_address_1"]);
            if (fields["billing_address_2"]) $('input#billing_address_2').val(fields["billing_address_2"]);
            if (fields["billing_city"]) $('input#billing_city').val(fields["billing_city"]);
            if (fields["billing_state"]) $('input#billing_state').val(fields["billing_state"]);
            if (fields["billing_postcode"]) $('input#billing_postcode').val(fields["billing_postcode"]);
        }

        // Clear Cookies After Checkout Completion
        if (window.location.href.includes("order-received")) {
            console.log("✅ Order Completed - Clearing Cookies...");
            deleteOldCookies();
            
        }

        // Run Checkout Autofill When on WooCommerce Checkout Page
        if (window.location.href.includes("checkout")) {
            prefillCheckoutFields();
        }
    });
    </script>
    <?php
});
