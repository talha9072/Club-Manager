<?php




// zim form latest fetch and autopopulate

/**
 * FORM 48 – Bikes
 * Save separately (no overwrite of other forms)
 * Auto-populate ONLY from Form 48 data
 */

add_action('init', function () {

    if (!is_user_logged_in()) return;

    /**
     * AUTO-POPULATE FORM 48
     * Priority:
     * 1) Latest GF entry (Form 48)
     * 2) form48_* user_meta
     */
    add_filter('gform_pre_render_48', function ($form) {

        $user_id = get_current_user_id();

        // Field ID → dedicated meta key (FORM 48 ONLY)
        $map = [
            // Bike #1
            87  => 'form48_bike_1_make',
            88  => 'form48_bike_1_model',
            89  => 'form48_bike_1_year',
            90  => 'form48_bike_1_reg',
            117 => 'form48_bike_1_vin',

            // Bike #2
            100 => 'form48_bike_2_make',
            101 => 'form48_bike_2_model',
            102 => 'form48_bike_2_year',
            103 => 'form48_bike_2_reg',
            118 => 'form48_bike_2_vin',

            // Bike #3
            108 => 'form48_bike_3_make',
            109 => 'form48_bike_3_model',
            110 => 'form48_bike_3_year',
            111 => 'form48_bike_3_reg',
            119 => 'form48_bike_3_vin',

            // Bike #4
            113 => 'form48_bike_4_make',
            114 => 'form48_bike_4_model',
            115 => 'form48_bike_4_year',
            116 => 'form48_bike_4_reg',
            120 => 'form48_bike_4_vin',
        ];

        // 🔥 Fetch latest GF entry for THIS form & user
        $search_criteria = [
            'field_filters' => [
                [
                    'key'   => 'created_by',
                    'value' => $user_id
                ]
            ]
        ];

        $entries = GFAPI::get_entries(
            48,
            $search_criteria,
            ['key' => 'date_created', 'direction' => 'DESC'],
            ['page_size' => 1]
        );

        $latest_entry = !empty($entries) ? $entries[0] : null;

        foreach ($form['fields'] as &$field) {
            if (!isset($map[$field->id])) continue;

            // 1️⃣ Latest GF entry (Form 48 only)
            if ($latest_entry && isset($latest_entry[$field->id])) {
                $field->defaultValue = $latest_entry[$field->id];
            }
            // 2️⃣ Fallback: form48_* meta
            else {
                $field->defaultValue = get_user_meta($user_id, $map[$field->id], true);
            }
        }

        return $form;
    });

    /**
     * SAVE FORM 48 → DEDICATED META (NO OVERWRITE OF OTHER FORMS)
     */
    add_action('gform_after_submission_48', function ($entry) {

        $user_id = get_current_user_id();
        if (!$user_id) return;

        $map = [
            87  => 'form48_bike_1_make',
            88  => 'form48_bike_1_model',
            89  => 'form48_bike_1_year',
            90  => 'form48_bike_1_reg',
            117 => 'form48_bike_1_vin',

            100 => 'form48_bike_2_make',
            101 => 'form48_bike_2_model',
            102 => 'form48_bike_2_year',
            103 => 'form48_bike_2_reg',
            118 => 'form48_bike_2_vin',

            108 => 'form48_bike_3_make',
            109 => 'form48_bike_3_model',
            110 => 'form48_bike_3_year',
            111 => 'form48_bike_3_reg',
            119 => 'form48_bike_3_vin',

            113 => 'form48_bike_4_make',
            114 => 'form48_bike_4_model',
            115 => 'form48_bike_4_year',
            116 => 'form48_bike_4_reg',
            120 => 'form48_bike_4_vin',
        ];

        foreach ($map as $field_id => $meta_key) {
            update_user_meta(
                $user_id,
                $meta_key,
                sanitize_text_field(rgar($entry, $field_id))
            );
        }

    }, 10, 2);

});










// Gravity Forms ID 8: Only ONE entry per logged-in user (update existing on resubmit)

// Step 1: Pre-populate form with user's existing entry (if any)
add_filter('gform_pre_render_8', 'gf8_populate_user_entry');
add_filter('gform_pre_validation_8', 'gf8_populate_user_entry');
add_filter('gform_pre_submission_filter_8', 'gf8_populate_user_entry');
add_filter('gform_admin_pre_render_8', 'gf8_populate_user_entry'); // Optional: admin mein bhi dikhe

function gf8_populate_user_entry($form) {
    if (!is_user_logged_in()) {
        return $form;
    }

    $user_id = get_current_user_id();

    // Find existing entry by this user for form 8
    $search_criteria = array(
        'field_filters' => array(
            array(
                'key'   => 'created_by',
                'value' => $user_id,
            ),
        ),
    );

    $sorting = array('key' => 'id', 'direction' => 'DESC'); // Latest entry
    $paging  = array('offset' => 0, 'page_size' => 1);

    $entries = GFAPI::get_entries($form['id'], $search_criteria, $sorting, $paging);

    if (!empty($entries)) {
        $entry = $entries[0];

        // Loop through all fields and set default value from existing entry
        foreach ($form['fields'] as &$field) {
            $field_id = $field->id;
            if (isset($entry[$field_id])) {
                $value = $entry[$field_id];

                if (is_array($value)) {
                    $field->defaultValue = maybe_unserialize($value);
                } else {
                    $field->defaultValue = $value;
                }
            }
        }
    }

    return $form;
}

// Step 2: After submission - if user already has an entry, update it instead of keeping new one
add_action('gform_after_submission_8', 'gf8_update_instead_of_create_new', 10, 2);

function gf8_update_instead_of_create_new($entry, $form) {
    if (!is_user_logged_in()) {
        return; // Anonymous users ko normal new entry banne do
    }

    $user_id      = get_current_user_id();
    $new_entry_id = $entry['id'];

    // Find all entries by this user
    $search_criteria = array(
        'field_filters' => array(
            array(
                'key'   => 'created_by',
                'value' => $user_id,
            ),
        ),
    );

    $entries = GFAPI::get_entries($form['id'], $search_criteria);

    if (count($entries) > 1) {
        // New entry created → delete it and update the existing one
        GFAPI::delete_entry($new_entry_id);

        // Get the remaining (previous) entry
        $remaining_entries = GFAPI::get_entries($form['id'], $search_criteria);
        if (!empty($remaining_entries)) {
            $existing_entry = $remaining_entries[0];

            // Update with new submitted values
            foreach ($form['fields'] as $field) {
                $field_id   = (string) $field->id;
                $input_name = 'input_' . str_replace('.', '_', $field_id);

                if (isset($_POST[$input_name])) {
                    $existing_entry[$field_id] = $_POST[$input_name];
                }
            }

            $existing_entry['date_updated'] = current_time('mysql');
            $existing_entry['created_by']   = $user_id;

            GFAPI::update_entry($existing_entry);
        }
    }
    // First time → only one entry → nothing to do
}






/**
 * FORM 8 – FINAL VIN VALIDATION
 * - Same-form duplicate VINs (simple)
 * - Global VIN check (ignore user's latest entry)
 * VIN fields: 28, 41, 51, 62, 70
 */

/* ======================================================
 * BACKEND – GLOBAL VIN CHECK (IGNORE USER LATEST ENTRY)
 * ====================================================== */

add_action('wp_ajax_gf8_check_vin_global', 'gf8_check_vin_global');
add_action('wp_ajax_nopriv_gf8_check_vin_global', 'gf8_check_vin_global');

function gf8_check_vin_global() {

    if (!isset($_POST['vin'])) {
        wp_send_json_error();
    }

    $vin = strtoupper(trim(sanitize_text_field($_POST['vin'])));
    if ($vin === '') {
        wp_send_json_success(['exists' => false]);
    }

    if (!class_exists('GFAPI')) {
        wp_send_json_error();
    }

    $current_user_id = get_current_user_id();

    /* 🔑 Find current user's LATEST entry for form 8 */
    $latest_entry_id = 0;
    if ($current_user_id) {
        $entries = GFAPI::get_entries(
            8,
            [
                'field_filters' => [
                    [
                        'key'   => 'created_by',
                        'value' => $current_user_id
                    ]
                ]
            ],
            ['key' => 'date_created', 'direction' => 'DESC'],
            ['page_size' => 1]
        );

        if (!empty($entries)) {
            $latest_entry_id = (int) $entries[0]['id'];
        }
    }

    // VIN FIELD IDS ONLY
    $vin_fields = [28, 41, 51, 62, 70];

    foreach ($vin_fields as $field_id) {

        $entries = GFAPI::get_entries(8, [
            'field_filters' => [
                [
                    'key'   => (string) $field_id,
                    'value' => $vin, // EXACT MATCH
                ]
            ]
        ]);

        foreach ($entries as $entry) {

            $stored_vin = strtoupper(trim((string) rgar($entry, (string) $field_id)));

            // 🟢 Ignore ONLY user's latest entry
            if (
                $current_user_id &&
                (int) $entry['created_by'] === (int) $current_user_id &&
                (int) $entry['id'] === $latest_entry_id
            ) {
                continue;
            }

            // 🔴 Any other match = duplicate
            if ($stored_vin === $vin) {
                wp_send_json_success([
                    'exists' => true,
                    'vin'    => $vin,
                ]);
            }
        }
    }

    wp_send_json_success(['exists' => false]);
}


/* ======================================================
 * FRONTEND – MASTER SCRIPT
 * ====================================================== */

add_action('wp_enqueue_scripts', function () {

    if (is_admin()) return;

    wp_register_script('gf8-vin-final', false, ['jquery'], '1.0', true);
    wp_enqueue_script('gf8-vin-final');

    wp_add_inline_script('gf8-vin-final', "
(function($){

    const vinFieldIds = [28,41,51,62,70];
    let vinBlocked = false;

    function normalize(v){
        return v.trim().toUpperCase();
    }

    function getForm(input){
        return input.closest('form');
    }

    function getSubmitBtn(form){
        return form.querySelector('.gform_button');
    }

    function disableSubmit(form){
        const btn = getSubmitBtn(form);
        if (btn) {
            btn.disabled = true;
            btn.style.opacity = '0.6';
        }
    }

    function enableSubmit(form){
        const btn = getSubmitBtn(form);
        if (btn) {
            btn.disabled = false;
            btn.style.opacity = '';
        }
    }

    function clearErrors(form){
        vinBlocked = false;
        form.querySelector('#vin-error-global')?.remove();
        form.querySelector('#vin-error-local')?.remove();
        form.querySelectorAll('.vin-error').forEach(i => i.classList.remove('vin-error'));
    }

    function showLocalError(form, vin){
        if (form.querySelector('#vin-error-local')) return;

        const div = document.createElement('div');
        div.id = 'vin-error-local';
        div.style.color = '#dc3232';
        div.style.fontSize = '13px';
        div.style.marginBottom = '10px';
        div.innerText =
            'Duplicate VIN detected in this form: ' + vin;
        form.querySelector('.gform_footer')?.prepend(div);
    }

    function showGlobalError(form, vin){
        if (form.querySelector('#vin-error-global')) return;

        const div = document.createElement('div');
        div.id = 'vin-error-global';
        div.style.color = '#dc3232';
        div.style.fontSize = '13px';
        div.style.marginBottom = '10px';
        div.innerText =
            'VIN ' + vin + ' is already used by another member.';
        form.querySelector('.gform_footer')?.prepend(div);
    }

    function validateForm(form){

        clearErrors(form);

        const inputs = vinFieldIds
            .map(id => form.querySelector('input[name=\"input_' + id + '\"]'))
            .filter(Boolean);

        const seen = {};

        /* ===============================
         * 1️⃣ SAME-FORM DUPLICATE LOGIC
         * =============================== */
        for (const input of inputs) {

            const vin = normalize(input.value);
            if (!vin) continue;

            if (seen[vin]) {
                vinBlocked = true;
                input.classList.add('vin-error');
                seen[vin].classList.add('vin-error');

                showLocalError(form, vin);
                disableSubmit(form);
                return;
            }

            seen[vin] = input;
        }

        /* ===============================
         * 2️⃣ GLOBAL DUPLICATE LOGIC
         * =============================== */
        inputs.forEach(input => {

            const vin = normalize(input.value);
            if (!vin) return;

            $.post('" . admin_url('admin-ajax.php') . "', {
                action: 'gf8_check_vin_global',
                vin: vin
            }, function(resp){

                if (resp.success && resp.data.exists) {

                    vinBlocked = true;
                    input.classList.add('vin-error');

                    showGlobalError(form, vin);
                    disableSubmit(form);

                } else if (!vinBlocked) {
                    enableSubmit(form);
                }
            });
        });
    }

    // Live typing
    $(document).on('input', 'input[name^=\"input_\"]', function(){
        const form = getForm(this);
        if (form) validateForm(form);
    });

    // Page load
    $(document).ready(function(){
        document.querySelectorAll('.gform_wrapper form').forEach(form => {
            validateForm(form);
        });
    });

    // GF ajax render
    document.addEventListener('gform_post_render', function(){
        document.querySelectorAll('.gform_wrapper form').forEach(form => {
            validateForm(form);
        });
    });

    // Hard submit block
    document.addEventListener('submit', function(e){
        if (vinBlocked) {
            e.preventDefault();
            e.stopImmediatePropagation();
        }
    }, true);

})(jQuery);
    ");

    wp_add_inline_style('wp-block-library', '
        .vin-error {
            border: 2px solid #dc3232 !important;
        }
    ');
});