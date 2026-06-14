<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// 1. Load Logic & Data Handler
include_once plugin_dir_path(__FILE__) . 'manager-order/logic.php';
?>

<div class="order-list">
    <?php
    // 2. Load UI Components
    include_once plugin_dir_path(__FILE__) . 'manager-order/header.php';
    include_once plugin_dir_path(__FILE__) . 'manager-order/filters.php';
    include_once plugin_dir_path(__FILE__) . 'manager-order/table.php';
    include_once plugin_dir_path(__FILE__) . 'manager-order/pagination.php';
    ?>
</div>

<script>
    // Select/Deselect All Checkboxes
    document.addEventListener('change', function (e) {
        if (e.target.id === 'select-all') {
            const checkboxes = document.querySelectorAll('input[name="selected_orders[]"]');
            checkboxes.forEach(checkbox => checkbox.checked = e.target.checked);
        }
    });

    // Mobile table responsiveness
    document.addEventListener('DOMContentLoaded', function () {
        if (window.innerWidth <= 1334) {
            document.querySelectorAll('#orders-table tr').forEach(function(row) {
                const actionCell = row.querySelector('td[data-label="Action"]');
                if (actionCell && actionCell.innerText.trim() === '') {
                    actionCell.style.display = 'none';
                }
            });
        }
    });
</script>

