<?php

// Hook into EventON before rendering event content
add_action('eventon_before_main_content', function() {
    if (!is_singular('ajde_events')) {
        return;
    }

    // Get the current event post ID
    $event_id = get_the_ID();
    
    // Debugging: Log event ID
    error_log("✅ EventON Event ID: " . ($event_id ? $event_id : 'Not Found'));

    // Get the featured image URL
    $featured_image_url = get_the_post_thumbnail_url($event_id, 'full');

    // Debugging: Log featured image URL
    error_log("✅ Featured Image URL: " . ($featured_image_url ? $featured_image_url : 'No Featured Image'));

    // Fallback image URL if no featured image is found
    $default_image_url = 'https://stage.bmwclubs.africa/wp-content/uploads/2025/01/bca-hero-1.jpg';

    // Ensure a valid image URL
    $image_url = !empty($featured_image_url) ? $featured_image_url : $default_image_url;

    // Debugging: Log final image URL
    error_log("✅ Final Image URL: " . $image_url);

    // Output the background-styled div before event content
    echo '<div class="event-featured-container" style="
            width: 100%; 
            padding-top: 150px;
            padding-bottom:100px; 
            background-blend-mode: multiply; 
            background-color: initial;
            background-image: url(' . esc_url($image_url) . '), linear-gradient(90deg, #161616 0%, rgba(38, 38, 38, 0) 100%);
            background-size: cover;
            background-position: center;
            
          ">
          </div>';
}, 5);



// below wala

// Hook into wp_footer to inject JavaScript after the page loads
add_action('wp_footer', function() {
    if (!is_singular('ajde_events')) {
        return;
    }

    ?>
    <style>
        /* Ensure the background color is applied */
        .event-spacing-container-below {
            width: 100%;
            padding-top: 60px;
            padding-bottom: 140px;
            background-color: #f2f2f2 !important; /* Apply important here */
        }
    </style>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // Find the `.container` element
            let containerElement = document.querySelector('.container');

            if (containerElement) {
                // Create the new spacing div
                let spacingDiv = document.createElement("div");
                spacingDiv.className = "event-spacing-container-below"; // Only set class
                
                // Insert it right after `.container`
                containerElement.parentNode.insertBefore(spacingDiv, containerElement.nextSibling);
            }
        });
    </script>
    <?php
}, 20);


// Hook into wp_head to add custom CSS for ajde_events pages only
add_action('wp_head', function() {
    if (!is_singular('ajde_events')) {
        return;
    }

    ?>
    <style>
        /* Ensure container is 90% width on larger screens */
        .container {
            width: 87%;
            max-width: 1080px;
            margin: auto;
            position: relative;
            padding-bottom: 58px;
        }
        .evo_page_body{
            max-width: 100% !important;
        }

        /* Make container 100% width on mobile devices */
        @media screen and (max-width: 768px) {
            .container {
                width: 90%;
            }
        }
    </style>
    <?php
}, 10);












// event catagory pages
// Hook into wp_head to add custom CSS for event-type archive pages
add_action('wp_head', function() {
    if (is_tax('event_type')) { // Check if it's an EventON event-type archive page
        ?>
        <style>
            /* Ensure container is 90% width on larger screens */
            .container {
                width: 90%;
                max-width: 1080px;
                margin: auto;
                position: relative;
                padding-bottom: 58px;
            }
            .evotax_term_card{
                display:block !important;
                margin:0px !important;
            }
            .evotax_term_card .evo_card_wrapper{
                margin:0px !important;
                display:block !important
            }

            /* Make container 100% width on mobile devices */
            @media screen and (max-width: 768px) {
                .container {
                    width: 90%;
                }
            }
        </style>
        <?php
    }
});


// featured image
// Hook into EventON event-type taxonomy pages to display a featured background
add_action('eventon_before_main_content', function() {
    if (!is_tax('event_type')) {
        return;
    }

    // Get the current event-type term ID
    $term = get_queried_object();
    $term_id = $term->term_id ?? 0;

    // Debugging: Log event type ID
    error_log("✅ EventON Event Type ID: " . ($term_id ? $term_id : 'Not Found'));

    // Get the term's featured image (if using a custom field)
    $featured_image_url = get_term_meta($term_id, 'event_type_image', true);

    // Debugging: Log featured image URL
    error_log("✅ Featured Image URL: " . ($featured_image_url ? $featured_image_url : 'No Featured Image'));

    // Fallback image URL if no featured image is found
    $default_image_url = 'https://stage.bmwclubs.africa/wp-content/uploads/2025/01/bca-hero-1.jpg';

    // Ensure a valid image URL
    $image_url = !empty($featured_image_url) ? $featured_image_url : $default_image_url;

    // Debugging: Log final image URL
    error_log("✅ Final Image URL: " . $image_url);

    // Output the background-styled div before event content
    echo '<div class="event-featured-container" style="
            width: 100%; 
            padding-top: 150px;
            padding-bottom: 100px; 
            background-blend-mode: multiply; 
            background-color: initial;
            background-image: url(' . esc_url($image_url) . '), linear-gradient(90deg, #161616 0%, rgba(38, 38, 38, 0) 100%);
            background-size: cover;
            background-position: center;
          ">
          </div>';
}, 5);



// Hook into wp_footer to inject JavaScript for adding spacing div on event-type pages
add_action('wp_footer', function() {
    if (!is_tax('event_type')) {
        return;
    }

    ?>
    <style>
        /* Ensure the background color is applied */
        .event-spacing-container-below {
            width: 100%;
            padding-top: 60px;
            padding-bottom: 140px;
            background-color: #f2f2f2 !important; /* Apply important here */
        }
    </style>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // Find the `.container` element
            let containerElement = document.querySelector('.container');

            if (containerElement) {
                // Create the new spacing div
                let spacingDiv = document.createElement("div");
                spacingDiv.className = "event-spacing-container-below"; // Only set class
                
                // Insert it right after `.container`
                containerElement.parentNode.insertBefore(spacingDiv, containerElement.nextSibling);
            }
        });
    </script>
    <?php
}, 20);



// Hook to add custom CSS and JS for Gravity Forms within EventON globally
add_action('wp_footer', 'inject_global_eventon_gravity_forms_fix');

function inject_global_eventon_gravity_forms_fix() {
    ?>
    <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function () {
            // Ensure Gravity Forms within EventON are visible
            const forms = document.querySelectorAll('.eventon_desc_in .gform_wrapper');
            forms.forEach(function (form) {
                form.style.display = 'block';
                form.style.visibility = 'visible';
                form.style.opacity = '1';
            });

            // Add notice and hide titles for forms with event-specific messages
            forms.forEach(function (form) {
                const formId = form.id.replace('gform_wrapper_', '');
                const notice = document.createElement('div');
                notice.className = 'event-form-notice';
                notice.style.backgroundColor = '#0767b1';
                notice.style.color = '#fff';
                notice.style.padding = '20px';
                notice.style.fontSize = '22px';
                notice.style.fontWeight = 'bold';
                notice.style.marginBottom = '20px';
                notice.style.textAlign = 'center';
                notice.textContent = '⚠️ IMPORTANT: Please submit the form below before adding this event to the cart.';

                // Inject the notice above the form if not already present
                if (!form.previousElementSibling || !form.previousElementSibling.classList.contains('event-form-notice')) {
                    form.parentNode.insertBefore(notice, form);
                }

                // Hide the Gravity Form title
                const formTitle = form.querySelector('.gform_title');
                if (formTitle) {
                    formTitle.style.display = 'none';
                }
            });
        });
    </script>

    <style>
        /* Ensure Gravity Forms are visible globally within EventON */
        .eventon_desc_in .gform_wrapper {
            display: block !important;
            visibility: visible !important;
            opacity: 1 !important;
        }

        /* Hide Gravity Form titles globally within EventON */
        .eventon_desc_in .gform_heading .gform_title {
            display: none !important;
        }
    </style>
    <?php
}



// Hook to add custom CSS for Gravity Forms visibility within EventON
add_action('wp_head', 'add_eventon_gravity_forms_visibility');

function add_eventon_gravity_forms_visibility() {
    ?>
    <style>
        /* Force display of hidden Gravity Forms within EventON */
        .eventon_desc_in .gform_wrapper {
            display: block !important;
            visibility: visible !important;
            opacity: 1 !important;
        }
    </style>
    <?php
}

?>
