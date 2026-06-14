<?php
function bmw_manager_export_orders($selected_orders) {
    global $wpdb;
    while (ob_get_level()) ob_end_clean();

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment;filename=orders_detailed.csv');
    $output = fopen('php://output', 'w');

    fputcsv($output, ['Order Number', 'Customer Name', 'Order Date', 'Order Status', 'Order Total', 'Billing Address', 'Shipping Address', 'Payment Method', 'Shipping Method', 'Items Ordered', 'Customer Notes', 'Order Notes']);

    foreach ($selected_orders as $order_id) {
        $order = wc_get_order($order_id);
        if (!$order) continue;

        $items = [];
        foreach ($order->get_items() as $item) {
            $items[] = "{$item->get_name()} (x{$item->get_quantity()}, Total: R" . intval($item->get_total()) . ")";
        }

        fputcsv($output, [
            $order->get_id(),
            $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            $order->get_date_created() ? $order->get_date_created()->date('d/m/Y') : '',
            ucfirst($order->get_status()),
            $order->get_total(),
            strip_tags($order->get_formatted_billing_address()),
            strip_tags($order->get_formatted_shipping_address()),
            $order->get_payment_method_title(),
            implode(', ', array_map(fn($s) => $s->get_name(), $order->get_shipping_methods())),
            implode('; ', $items),
            $order->get_customer_note(),
            implode('; ', array_map(fn($n) => $n->content, wc_get_order_notes(['order_id' => $order_id])))
        ]);
    }
    fclose($output);
    exit;
}
