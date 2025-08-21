<?php
// File: /includes/manager-bca-table.php
if (!defined('ABSPATH')) {
    exit;
}

function render_chairman_table() {
    global $wpdb;

    // Pagination setup
    $items_per_page = 20;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $items_per_page;

    // Fetch chairman details with club names
    $query = $wpdb->prepare(
        "SELECT c.club_name, cm.name, cm.title, cm.email 
         FROM {$wpdb->prefix}club_committee cm
         JOIN {$wpdb->prefix}clubs c ON cm.club_id = c.club_id
         WHERE cm.title = %s
         LIMIT %d OFFSET %d",
        'Chairman', $items_per_page, $offset
    );

    $chairmen = $wpdb->get_results($query);

    // Get total chairman count for pagination
    $total_chairmen = $wpdb->get_var(
        $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}club_committee WHERE title = %s", 'Chairman')
    );
    $total_pages = ceil($total_chairmen / $items_per_page);

    // Handle CSV export
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk-action']) && $_POST['bulk-action'] === 'export') {
        export_all_chairmen_to_csv();
    }

    // Render table
    ?>
    <form method="post" id="bulk-form">
        <input type="hidden" name="bulk-action" value="export">
        <button type="submit" class="button button-primary">Export All to CSV</button>

        <table border="1" cellpadding="10" style="border-collapse: collapse; width: 100%; margin-top: 20px;"class="wp-list-table widefat fixed striped managertable" id="bca-table">
            <thead>
                <tr>
                    <th>Club Name</th>
                    <th>Name</th>
                    <th>Title</th>
                    <th>Email</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($chairmen as $chairman): ?>
                    <tr>
                        
                        <td><?php echo esc_html($chairman->club_name); ?></td>
                        <td><?php echo esc_html($chairman->name); ?></td>
                        <td><?php echo esc_html($chairman->title); ?></td>
                        <td><?php echo esc_html($chairman->email); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <div class="pagination" style="margin-top: 20px; text-align: center;">
            <?php if ($current_page > 1): ?>
                <a href="?paged=<?php echo $current_page - 1; ?>" style="margin: 0 5px; padding: 5px 10px; border: 1px solid #ddd; text-decoration: none;">Previous</a>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?paged=<?php echo $i; ?>" class="<?php echo $i === $current_page ? 'current-page' : ''; ?>" style="margin: 0 5px; padding: 5px 10px; border: 1px solid #ddd; text-decoration: none; <?php echo $i === $current_page ? 'background-color: #10487B; color: white;' : ''; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>

            <?php if ($current_page < $total_pages): ?>
                <a href="?paged=<?php echo $current_page + 1; ?>" style="margin: 0 5px; padding: 5px 10px; border: 1px solid #ddd; text-decoration: none;">Next</a>
            <?php endif; ?>
        </div>
    </form>

    <script>
        document.getElementById('select-all').addEventListener('change', function () {
            document.querySelectorAll('input[name="selected[]"]').forEach(cb => cb.checked = this.checked);
        });
    </script>
    <?php
}

function export_all_chairmen_to_csv() {
    global $wpdb;

    // Ensure no output is sent before headers
    if (headers_sent()) {
        die(__('Headers already sent. Cannot generate CSV.', 'textdomain'));
    }

    // Clear all output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }

    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="all_chairmen.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Open output stream
    $output = fopen('php://output', 'w');

    // Write CSV header row
    fputcsv($output, ['Club Name', 'Name', 'Title', 'Email']);

    // Fetch all chairman records
    $chairmen = $wpdb->get_results(
        "SELECT c.club_name, cm.name, cm.title, cm.email 
         FROM {$wpdb->prefix}club_committee cm
         JOIN {$wpdb->prefix}clubs c ON cm.club_id = c.club_id
         WHERE cm.title = 'Chairman'"
    );

    // Write data rows
    foreach ($chairmen as $chairman) {
        fputcsv($output, [$chairman->club_name, $chairman->name, $chairman->title, $chairman->email]);
    }

    // Close the output stream
    fclose($output);
    exit;
}

render_chairman_table();
