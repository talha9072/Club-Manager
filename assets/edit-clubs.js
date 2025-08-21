document.querySelector('.upload_logo_button').addEventListener('click', function() {
    var frame = wp.media({
        title: 'Upload Club logo',
        button: { text: 'Use This logo' },
        multiple: false
    });

    frame.on('select', function() {
        var attachment = frame.state().get('selection').first().toJSON();
        document.getElementById('club_logo').value = attachment.url; // Save the URL of the selected image

        // Display the selected image in the preview section
        var previewContainer = document.getElementById('club_logo_preview');
        previewContainer.innerHTML = '<img src="' + attachment.url + '" alt="Club Logo" style="max-width: 100px; max-height: 100px;">';
        
        alert('Image uploaded successfully');
    });

    frame.open();
});

document.getElementById('club_logo_file').addEventListener('change', function(event) {
    // Since WordPress media uploader is used, no action is needed here.
    // This listener remains for compatibility with existing structure but is not active.
});

document.querySelector('.remove_logo_button').addEventListener('click', function() {
    document.getElementById('club_logo').value = ''; // Clear the stored image URL
    document.getElementById('club_logo_preview').innerHTML = ''; // Remove the image preview
    alert('Image removed');
});

jQuery(document).ready(function($) {
    $('.sub-tab-content').hide();
    $('#club-details').show();

    $('.sub-tab').on('click', function(e) {
        e.preventDefault();
        $('.sub-tab').removeClass('sub-tab-active');
        $(this).addClass('sub-tab-active');
        $('.sub-tab-content').hide();
        var target = $(this).attr('href');
        $(target).show();
    });

    $('.upload_logo_button').on('click', function(e) {
        e.preventDefault();
        var frame = wp.media({
            title: 'Upload Club logo',
            button: { text: 'Use This logo' },
            multiple: false
        });
        
        frame.on('select', function() {
            var attachment = frame.state().get('selection').first().toJSON();
            $('#club_logo').val(attachment.url); // Save the URL instead of ID

            // Display the selected image in the preview section
            $('#club_logo_preview').html('<img src="' + attachment.url + '" alt="Club Logo" style="max-width: 100px; max-height: 100px;">');
            
            alert('Image uploaded successfully');
        });

        frame.open();
    });

    $('.remove_logo_button').on('click', function(e) {
        e.preventDefault();
        $('#club_logo').val(''); // Clear the stored image URL
        $('#club_logo_preview').html(''); // Remove the image preview
        alert('Image removed');
    });

    // Payment Gateway Selection Logic
    $('#payment_gateway').on('change', function() {
        var selectedGateway = $(this).val();
        $('#payfast_fields, #stripe_fields, #yoco_fields').hide(); // Hide all
        if (selectedGateway === 'payfast') {
            $('#payfast_fields').show();
        } else if (selectedGateway === 'stripe') {
            $('#stripe_fields').show();
        } else if (selectedGateway === 'yoco') {
            $('#yoco_fields').show();
        }
    });

    $('.wc-enhanced-select').select2();
});


    document.addEventListener('DOMContentLoaded', function() {
        // Get the club URL input field
        var clubUrlInput = document.getElementById('club_url');

        // Add an event listener to detect changes in the input field
        clubUrlInput.addEventListener('input', function() {
            var value = clubUrlInput.value;

            // Ensure the value starts with "/"
            if (value.charAt(0) !== '/') {
                value = '/' + value;
            }

            // Replace spaces with hyphens
            value = value.replace(/\s+/g, '-');

            // Update the input field with the modified value
            clubUrlInput.value = value;
        });
    });

