<?php if (!defined('ABSPATH')) exit; ?>

<form method="post" class="bulk-actions">
    <div class="bulk-users end-filters">
        <select name="bulk_action">
            <option value="">Bulk Actions</option>
            <option value="export">Export as CSV</option>
            <option value="delete">Delete Permanently</option>
        </select>
        <button type="submit" class="my-filters All-button">Apply</button>
    </div>

    <table class="wp-list-table widefat fixed striped order-table managertable" id="orders-table">
        <thead>
            <tr>
                <th><input type="checkbox" id="select-all"></th>
                <th>Order Number</th>
                <th>Name</th>
                <th>Date</th>
                <th>Status</th>
                <th>Total</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($orders)) : ?>
                <?php foreach ($orders as $order_data) : 
                    $order_num = $order_data->order_id;
                    $name = trim($order_data->first_name . ' ' . $order_data->last_name);
                    if (empty($name)) $name = $order_data->fallback_name ?: 'Unknown';
                    
                    $date = date('d/m/Y', strtotime($order_data->post_date));
                    $status_raw = $order_data->status;
                    $status_label = ucwords(str_replace('wc-', '', $status_raw));
                    $currency = $order_data->club_currency ?: 'R';
                    $total_formatted = $currency . number_format((float)$order_data->total, 2);

                    $badge_styles = [
                        'wc-pending'    => 'background: #fff3cd; color: #856404; border: 1px solid #ffeeba;',
                        'wc-on-hold'    => 'background: #f8f9fa; color: #6c757d; border: 1px solid #dee2e6;',
                        'wc-processing' => 'background: #cce5ff; color: #004085; border: 1px solid #b8daff;',
                        'wc-completed'  => 'background: #d4edda; color: #155724; border: 1px solid #c3e6cb;',
                        'wc-cancelled'  => 'background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;',
                        'wc-failed'     => 'background: #f5c6cb; color: #721c24; border: 1px solid #f5c6cb;',
                    ];
                    $badge_style = $badge_styles[$status_raw] ?? 'background: #e2e3e5; color: #818182; border: 1px solid #d6d8db;';
                ?>
                    <tr>
                        <td><input type="checkbox" name="selected_orders[]" value="<?php echo esc_attr($order_num); ?>"></td>
                        <td data-label="Order Number">
                            <a href="<?php echo esc_url(admin_url('post.php?post=' . $order_num . '&action=edit')); ?>" target="_blank">
                                #<?php echo esc_html($order_num); ?>
                            </a>
                        </td>
                        <td data-label="Name"><?php echo esc_html($name); ?></td>
                        <td data-label="Date"><?php echo esc_html($date); ?></td>
                        <td data-label="Status">
                            <span class="badge" style="display: inline-block; padding: 5px 10px; border-radius: 3px; font-size: 12px; <?php echo $badge_style; ?>">
                                <?php echo esc_html($status_label); ?>
                            </span>
                        </td>
                        <td data-label="Total"><?php echo esc_html($total_formatted); ?></td>
                        <td data-label="Action">
                            <?php if (in_array($status_raw, ['wc-on-hold', 'wc-pending', 'wc-processing'])) : ?>
                                <button type="submit" name="action" value="complete_order" onclick="this.form.order_id.value='<?php echo $order_num; ?>'" class="button complete-order-button">Complete</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <input type="hidden" name="order_id" value="">
            <?php else : ?>
                <tr><td colspan="7">No orders found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</form>
