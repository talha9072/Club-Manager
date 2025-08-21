<?php
// Prevent direct file access
if (!defined('ABSPATH')) {
    exit;
}
function fetch_user_valuations() {
    $current_user = wp_get_current_user();
    if (!$current_user->exists()) {
        echo '<p>You must be logged in to view your valuations.</p>';
        return;
    }
    $user_id = intval($current_user->ID);
    global $wpdb;
    $all_valuations = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT e.id AS entry_id, e.date_created,
                    (SELECT meta_value FROM {$wpdb->prefix}gf_entry_meta WHERE entry_id = e.id AND meta_key = '3') AS model,
                    (SELECT meta_value FROM {$wpdb->prefix}gf_entry_meta WHERE entry_id = e.id AND meta_key = '4') AS registration_number,
                    (SELECT meta_value FROM {$wpdb->prefix}gf_entry_meta WHERE entry_id = e.id AND meta_key = '5') AS mileage,
                    (SELECT meta_value FROM {$wpdb->prefix}gf_entry_meta WHERE entry_id = e.id AND meta_key = 'evaluation_pdf') AS evaluation_pdf
             FROM {$wpdb->prefix}gf_entry e
             WHERE e.created_by = %d AND e.form_id = %d",
            $user_id, 25
        )
    );
    $status_map = [
        'Reviewed' => ['Reviewed', '#C6E1C6', '#5B841B'],    
        'Pending Review' => ['Pending Review', '#F8D7DA', '#721C24'], 
    ];
    $total_valuations = count($all_valuations);
    $pending_valuations = count(array_filter($all_valuations, fn($valuation) => !$valuation->evaluation_pdf));
    $reviewed_valuations = count(array_filter($all_valuations, fn($valuation) => $valuation->evaluation_pdf));
    $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
    $date_filter = isset($_GET['date']) ? sanitize_text_field($_GET['date']) : '';
    $valuations = array_filter($all_valuations, function ($valuation) use ($status_filter, $date_filter) {
        $matches_status = true;
        $matches_date = true;

        if ($status_filter === 'pending') {
            $matches_status = !$valuation->evaluation_pdf;
        } elseif ($status_filter === 'reviewed') {
            $matches_status = $valuation->evaluation_pdf;
        }

        if (!empty($date_filter)) {
            $matches_date = date('Y-m', strtotime($valuation->date_created)) === $date_filter;
        }

        return $matches_status && $matches_date;
    });

    // Pagination logic
    global $wp_query;
    $paged = isset($wp_query->query_vars['paged']) ? intval($wp_query->query_vars['paged']) : 1;
    $current_page = $paged > 0 ? $paged : 1;

    $items_per_page = 20;
    $total_pages = ceil(count($valuations) / $items_per_page);
    $valuations = array_slice($valuations, ($current_page - 1) * $items_per_page, $items_per_page);

    // Add-Valuation button
    echo '<div style="margin-bottom: 20px;">';
    echo '<form method="get" action="">';
    foreach ($_GET as $key => $value) {
        if ($key !== 'add-val') {
            echo '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '">';
        }
    }
    echo '<div class="admin-switch">';
    echo '<h2 class="h2-switch">Valuations</h2>';
    echo '<button type="submit"class="All-button" name="add-val" value="true" style=" border: none; cursor: pointer;">Add Valuation</button>';
    echo '</div>';
    echo '</form>';
    echo '</div>';

    // Count filter links
    $base_url = remove_query_arg(['status', 'date', 'paged'], $_SERVER['REQUEST_URI']);
    echo '<div class="status-filters count">';
    echo '<a href="' . esc_url($base_url) . '" class="' . (empty($status_filter) ? 'current' : '') . '">All (' . $total_valuations . ')</a> | ';
    echo '<a href="' . esc_url(add_query_arg('status', 'pending', $base_url)) . '" class="' . ($status_filter === 'pending' ? 'current' : '') . '">Pending (' . $pending_valuations . ')</a> | ';
    echo '<a href="' . esc_url(add_query_arg('status', 'reviewed', $base_url)) . '" class="' . ($status_filter === 'reviewed' ? 'current' : '') . '">Reviewed (' . $reviewed_valuations . ')</a>';
    echo '</div>';

    // Date filter
    echo '<form method="GET" class="bulk-users end-filters">';
    foreach ($_GET as $key => $value) {
        if ($key !== 'date') {
            echo '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '">';
        }
    }
   
    echo '<input type="month" id="date"class="my-inputs" name="date" value="' . esc_attr($date_filter) . '">';
    echo '<button type="submit"class="my-filters All-button">Filter</button>';
    echo '</form>';

    // Check if valuations exist
    if (empty($valuations)) {
        echo '<p>No valuations found for your account.</p>';
        return;
    }

    // Display the valuations in a table
    echo "<table class='managertable' style='border-collapse: collapse; width: 100%;'id='members-valuation-table'>";
    echo '<thead>';
    echo '<tr>';
    echo '<th>Date</th>';
    echo '<th>Model</th>';
    echo '<th>Registration Number</th>';
    echo '<th>Mileage</th>';
    echo '<th>Status</th>';
    echo '<th>Download</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';

    foreach ($valuations as $valuation) {
        $formatted_date = date('j/M/Y', strtotime($valuation->date_created));
        $status = $valuation->evaluation_pdf ? 'Reviewed' : 'Pending Review';
        $status_info = $status_map[$status] ?? ['Unknown', '#E0E0E0', '#777']; 

        $download_link = $valuation->evaluation_pdf
            ? '<a href="' . esc_url($valuation->evaluation_pdf) . '" download>Download PDF</a>'
            : 'N/A';

        echo '<tr>';
        echo '<td  data-label="Date">' . esc_html($formatted_date) . '</td>';
        echo '<td  data-label="Model">' . esc_html($valuation->model) . '</td>';
        echo '<td  data-label="Registration Number">' . esc_html($valuation->registration_number) . '</td>';
        echo '<td  data-label="Milage">' . esc_html($valuation->mileage) . '</td>';
        echo '<td  data-label="Status">
                <span class="badge" style="padding: 5px 10px; border-radius: 3px; display: inline-block;
                    background: ' . esc_attr($status_info[1]) . '; color: ' . esc_attr($status_info[2]) . ';">
                    ' . esc_html($status_info[0]) . '
                </span>
            </td>';

        echo '<td  data-label="Download">' . $download_link . '</td>';
        echo '</tr>';
    }

    echo '</tbody>';
    echo '</table>';

    // Pagination
    if ($total_pages >= 1) {
        echo '<div class="pagination">';
        if ($current_page > 1) {
            echo '<a href="' . esc_url(add_query_arg('paged', $current_page - 1, $base_url)) . '" class="prev">Previous</a>';
        }
        for ($i = 1; $i <= $total_pages; $i++) {
            echo '<a href="' . esc_url(add_query_arg('paged', $i, $base_url)) . '" class="' . ($i === $current_page ? 'current' : '') . '">' . $i . '</a>';
        }
        if ($current_page < $total_pages) {
            echo '<a href="' . esc_url(add_query_arg('paged', $current_page + 1, $base_url)) . '" class="next">Next</a>';
        }
        echo '</div>';
    }
}

function addvaluation() {
    
    echo do_shortcode('[gravityform id="25" title="true" description="true"]');
}

if (isset($_GET['add-val']) && $_GET['add-val'] === 'true') {
    addvaluation();
} else {
   
    fetch_user_valuations();
}
