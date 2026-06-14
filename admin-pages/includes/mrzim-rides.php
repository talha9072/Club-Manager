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





/**
 * Gravity Forms ID 48 - One entry per logged-in user
 * Keeps only the most recent submission
 * Automatically deletes older entries from the same user
 *
 * Put this code in your theme's functions.php or (better) in a custom plugin
 */

add_action('gform_after_submission_48', 'gf48_keep_only_latest_entry', 10, 2);

function gf48_keep_only_latest_entry($entry, $form) {
    // Safety checks
    if ($form['id'] != 48) {
        return;
    }

    if (!is_user_logged_in()) {
        return; // anonymous users = normal behavior (multiple entries allowed)
    }

    $user_id = get_current_user_id();

    // Find all previous entries by this user
    $search_criteria = [
        'field_filters' => [
            [
                'key'   => 'created_by',
                'value' => $user_id,
            ],
        ],
    ];

    // Get all entries (except the one we just created)
    $entries = GFAPI::get_entries($form['id'], $search_criteria);

    foreach ($entries as $old_entry) {
        // Don't delete the entry we just submitted
        if ($old_entry['id'] !== $entry['id']) {
            GFAPI::delete_entry($old_entry['id']);
        }
    }
}

// ================================================
// Optional: Pre-populate form with latest entry
// ================================================

add_filter('gform_pre_render_48',    'gf48_pre_populate_latest_entry');
add_filter('gform_pre_validation_48', 'gf48_pre_populate_latest_entry');
add_filter('gform_pre_submission_filter_48', 'gf48_pre_populate_latest_entry');
// add_filter('gform_admin_pre_render_48', 'gf48_pre_populate_latest_entry'); // uncomment if needed in admin

function gf48_pre_populate_latest_entry($form) {
    if ($form['id'] != 48) {
        return $form;
    }

    if (!is_user_logged_in()) {
        return $form;
    }

    $user_id = get_current_user_id();

    $search_criteria = [
        'field_filters' => [
            [
                'key'   => 'created_by',
                'value' => $user_id,
            ],
        ],
    ];

    $sorting = ['key' => 'id', 'direction' => 'DESC'];
    $paging  = ['offset' => 0, 'page_size' => 1];

    $entries = GFAPI::get_entries($form['id'], $search_criteria, $sorting, $paging);

    if (empty($entries)) {
        return $form;
    }

    $latest_entry = $entries[0];

    foreach ($form['fields'] as &$field) {
        $field_id = $field->id;

        if (isset($latest_entry[$field_id])) {
            $value = $latest_entry[$field_id];

            // Better handling for different field types
            switch ($field->get_input_type()) {
                case 'checkbox':
                case 'list':
                    $field->defaultValue = maybe_unserialize($value);
                    break;

                case 'multiselect':
                    $field->defaultValue = $value ? explode(',', $value) : '';
                    break;

                case 'number':
                    $field->defaultValue = (string) $value;
                    break;

                default:
                    $field->defaultValue = $value;
            }
        }
    }

    return $form;
}






/**
 * Gravity Forms ID 48 – FINAL VIN VALIDATION
 * - Prevents duplicate VINs within the same form submission
 * - Global check: prevents VIN already used by ANY other entry
 *   (except the current user's own latest entry)
 *
 * VIN fields: 117, 118, 119, 120
 */


/* ======================================================
 * BACKEND – GLOBAL VIN CHECK (ignores user's own latest entry)
 * ====================================================== */

add_action('wp_ajax_gf48_check_vin_global', 'gf48_check_vin_global');
add_action('wp_ajax_nopriv_gf48_check_vin_global', 'gf48_check_vin_global');

function gf48_check_vin_global() {
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

    // Find current user's LATEST entry (to be ignored)
    $latest_entry_id = 0;
    if ($current_user_id) {
        $entries = GFAPI::get_entries(
            48,
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

    // VIN FIELD IDS
    $vin_fields = [117, 118, 119, 120];

    foreach ($vin_fields as $field_id) {
        $entries = GFAPI::get_entries(48, [
            'field_filters' => [
                [
                    'key'   => (string) $field_id,
                    'value' => $vin,          // exact match
                ]
            ]
        ]);

        foreach ($entries as $entry) {
            $stored_vin = strtoupper(trim((string) rgar($entry, (string) $field_id)));

            // Skip only if it's the current user's latest entry
            if (
                $current_user_id &&
                (int) $entry['created_by'] === (int) $current_user_id &&
                (int) $entry['id'] === $latest_entry_id
            ) {
                continue;
            }

            // Any other match → duplicate found
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
 * FRONTEND – Real-time validation + submit protection
 * ====================================================== */

add_action('wp_enqueue_scripts', function () {

    // Only load on frontend
    if (is_admin()) {
        return;
    }

    wp_register_script('gf48-vin-validation', false, ['jquery'], '1.1', true);
    wp_enqueue_script('gf48-vin-validation');

    wp_add_inline_script('gf48-vin-validation', "
(function($){

    const vinFieldIds = [117,118,119,120];
    let vinIsInvalid = false;

    function normalize(vin) {
        return (vin || '').trim().toUpperCase();
    }

    function getForm(el) {
        return el.closest('form');
    }

    function getSubmitButton(form) {
        return form.querySelector('.gform_button, .gform_next_button, .gform_prev_button');
    }

    function disableSubmit(form) {
        const btn = getSubmitButton(form);
        if (btn) {
            btn.disabled = true;
            btn.style.opacity = '0.6';
            btn.style.cursor = 'not-allowed';
        }
    }

    function enableSubmit(form) {
        const btn = getSubmitButton(form);
        if (btn) {
            btn.disabled = false;
            btn.style.opacity = '';
            btn.style.cursor = '';
        }
    }

    function clearErrors(form) {
        vinIsInvalid = false;
        form.querySelector('#vin-error-global')?.remove();
        form.querySelector('#vin-error-local')?.remove();
        form.querySelectorAll('.vin-error').forEach(el => el.classList.remove('vin-error'));
    }

    function showLocalError(form, vin) {
        if (form.querySelector('#vin-error-local')) return;

        const div = document.createElement('div');
        div.id = 'vin-error-local';
        div.style.cssText = 'color:#dc3232; font-size:13px; margin:0 0 10px; padding:8px; background:#fff5f5; border:1px solid #ffb3b3; border-radius:4px;';
        div.textContent = 'Duplicate VIN detected in this form: ' + vin;
        form.querySelector('.gform_footer')?.prepend(div);
    }

    function showGlobalError(form, vin) {
        if (form.querySelector('#vin-error-global')) return;

        const div = document.createElement('div');
        div.id = 'vin-error-global';
        div.style.cssText = 'color:#dc3232; font-size:13px; margin:0 0 10px; padding:8px; background:#fff5f5; border:1px solid #ffb3b3; border-radius:4px;';
        div.textContent = 'VIN ' + vin + ' is already used by another member.';
        form.querySelector('.gform_footer')?.prepend(div);
    }

    function validateVINs(form) {
        clearErrors(form);

        const inputs = vinFieldIds
            .map(id => form.querySelector('input[name=\"input_' + id + '\"]'))
            .filter(Boolean);

        const seen = {};

        // 1. Check duplicates INSIDE this form
        for (const input of inputs) {
            const vin = normalize(input.value);
            if (!vin) continue;

            if (seen[vin]) {
                vinIsInvalid = true;
                input.classList.add('vin-error');
                seen[vin].classList.add('vin-error');
                showLocalError(form, vin);
                disableSubmit(form);
                return;
            }
            seen[vin] = input;
        }

        // 2. Check global duplicates (via AJAX)
        let pendingChecks = 0;

        inputs.forEach(input => {
            const vin = normalize(input.value);
            if (!vin) return;

            pendingChecks++;

            $.post('" . admin_url('admin-ajax.php') . "', {
                action: 'gf48_check_vin_global',
                vin: vin
            }, function(response) {
                pendingChecks--;

                if (response.success && response.data.exists) {
                    vinIsInvalid = true;
                    input.classList.add('vin-error');
                    showGlobalError(form, vin);
                    disableSubmit(form);
                }

                // Only re-enable if no errors left
                if (pendingChecks === 0 && !vinIsInvalid) {
                    enableSubmit(form);
                }
            });
        });

        // If no global checks needed, enable immediately
        if (pendingChecks === 0 && !vinIsInvalid) {
            enableSubmit(form);
        }
    }

    // Events
    $(document).on('input change', 'input[name^=\"input_\"]', function() {
        const form = getForm(this);
        if (form && form.id.includes('gform_48')) {
            validateVINs(form);
        }
    });

    // Initial check + after ajax load
    $(document).on('gform_post_render', function(event, formId) {
        if (formId == 48) {
            document.querySelectorAll('#gform_wrapper_48 form').forEach(form => {
                validateVINs(form);
            });
        }
    });

    // Block submit if invalid
    document.addEventListener('submit', function(e) {
        if (vinIsInvalid && e.target.id.includes('gform_48')) {
            e.preventDefault();
            e.stopImmediatePropagation();
            alert('Please fix the duplicate VIN errors before submitting.');
        }
    }, true);

})(jQuery);
    ");

    // Error styling
    wp_add_inline_style('wp-block-library', '
        .vin-error {
            border: 2px solid #dc3232 !important;
            background-color: #fff5f5 !important;
        }
        .gform_wrapper .vin-error:focus {
            outline: 2px solid #dc3232;
        }
    ');
});