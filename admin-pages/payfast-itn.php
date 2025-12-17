<?php
/**
 * PayFast ITN Handler – FINAL (ADMIN-LIKE, GUARANTEED)
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', function () {

    if (empty($_POST['m_payment_id'])) {
        return;
    }

    $order_id       = intval($_POST['m_payment_id']);
    $payment_status = $_POST['payment_status'] ?? '';
    $pf_txn_id      = $_POST['pf_payment_id'] ?? '';

    // Log
    file_put_contents(
        WP_CONTENT_DIR . '/payfast-itn.log',
        sprintf(
            "[%s] ORDER_ID=%d | STATUS=%s | PF_TXN=%s | RAW=%s\n",
            date('Y-m-d H:i:s'),
            $order_id,
            $payment_status,
            $pf_txn_id,
            json_encode($_POST)
        ),
        FILE_APPEND
    );

    $order = wc_get_order($order_id);
    if (!$order) {
        exit;
    }

    if ($order->get_type() !== 'shop_order') {
        exit;
    }

    // -----------------------------
    // 🔥 REAL FIX HERE
    // -----------------------------
    if ($payment_status === 'COMPLETE') {

        // ALWAYS mark payment complete (even if already paid)
        $order->payment_complete($pf_txn_id);

        // ALWAYS force COMPLETED (admin behaviour)
        $order->set_status(
            'completed',
            'PayFast ITN: Payment COMPLETE (forced, admin-like)'
        );

        // Save explicitly
        $order->save();
    }

    if (in_array($payment_status, ['FAILED', 'CANCELLED'], true)) {
        $order->set_status(
            'failed',
            'PayFast ITN: Payment ' . $payment_status
        );
        $order->save();
    }

    echo 'OK';
    exit;
});
