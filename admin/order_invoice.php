<?php
require_once __DIR__ . '/includes/common.php';
$ctx = admin_boot('orders');
$pdo = $ctx['pdo'];
$currency = $ctx['siteCurrency'];
$siteName = $ctx['siteName'];
store_ensure_checkout_tables($pdo);

$orderId = isset($_GET['order_id']) && is_numeric($_GET['order_id']) ? (int) $_GET['order_id'] : 0;
if ($orderId <= 0) {
    http_response_code(404);
    exit('Order not found.');
}

$stmt = $pdo->prepare('SELECT * FROM orders WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $orderId]);
$order = $stmt->fetch();
if (!$order) {
    http_response_code(404);
    exit('Order not found.');
}

$items = admin_rows($pdo, 'SELECT * FROM order_items WHERE order_id = :order_id ORDER BY id ASC', ['order_id' => $orderId]);
$invoiceNumber = 'INV-' . preg_replace('/[^A-Z0-9-]+/i', '', (string) $order['order_number']);
$paymentMethod = trim((string) ($order['payment_method_name'] ?? '')) ?: (trim((string) ($order['payment_method'] ?? '')) ?: 'Not specified');
$shippingMethod = trim((string) ($order['shipping_method_name'] ?? '')) ?: 'Not specified';
?><!doctype html>
<html lang="en" class="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($invoiceNumber) ?> | <?= e($siteName) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(admin_asset_url('assets/css/admin.css')) ?>">
</head>
<body class="admin-body admin-invoice-body" data-admin-print-page>
<main class="admin-invoice-page glass-panel">
    <header class="admin-invoice-toolbar">
        <a class="admin-ghost-btn" href="<?= e(admin_page_url('orders', ['view' => $orderId])) ?>">Back to order</a>
        <button class="admin-primary-btn" type="button" data-admin-print>Print invoice</button>
    </header>

    <section class="admin-invoice-sheet">
        <div class="admin-invoice-head">
            <div>
                <p class="admin-eyebrow">Invoice</p>
                <h1><?= e($invoiceNumber) ?></h1>
                <p><?= e($siteName) ?> · <?= e((string) date('Y-m-d')) ?></p>
            </div>
            <div class="admin-invoice-status">
                <span class="admin-pill <?= e(admin_status_class($order['status'])) ?>"><?= e(ucfirst((string) $order['status'])) ?></span>
                <span class="admin-pill <?= e(admin_status_class($order['payment_status'])) ?>"><?= e(ucfirst((string) $order['payment_status'])) ?></span>
            </div>
        </div>

        <div class="admin-invoice-meta">
            <article>
                <strong>Bill to</strong>
                <p><?= e($order['full_name']) ?></p>
                <p><?= e($order['email']) ?><?= $order['phone'] ? ' · ' . e($order['phone']) : '' ?></p>
            </article>
            <article>
                <strong>Ship to</strong>
                <p><?= e($order['address_line1']) ?><?= $order['address_line2'] ? ' · ' . e($order['address_line2']) : '' ?></p>
                <p><?= e($order['city'] . ', ' . $order['country']) ?></p>
            </article>
            <article>
                <strong>Order</strong>
                <p><?= e($order['order_number']) ?></p>
                <p><?= e((string) $order['created_at']) ?></p>
            </article>
        </div>

        <div class="admin-invoice-methods">
            <div><span>Shipping method</span><strong><?= e($shippingMethod) ?></strong></div>
            <div><span>Payment method</span><strong><?= e($paymentMethod) ?></strong></div>
            <div><span>Coupon</span><strong><?= e(!empty($order['coupon_code']) ? $order['coupon_code'] : 'Not used') ?></strong></div>
            <div><span>Tracking</span><strong><?= e(trim((string) ($order['tracking_number'] ?? '')) ?: 'Not added') ?></strong></div>
        </div>

        <div class="admin-table-wrap admin-invoice-table-wrap">
            <table class="admin-table admin-invoice-table">
                <thead><tr><th>Item</th><th>Qty</th><th>Unit price</th><th>Line total</th></tr></thead>
                <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <?php $itemOptionsLabel = store_options_label(store_options_from_json($item['selected_options_json'] ?? null)); ?>
                        <td><strong><?= e($item['product_name']) ?></strong><small><?= $item['product_id'] ? '#' . (int) $item['product_id'] : 'Deleted product' ?><?= $itemOptionsLabel !== '' ? ' · ' . e($itemOptionsLabel) : '' ?></small></td>
                        <td><?= (int) $item['qty'] ?></td>
                        <td><?= e(admin_money((float) $item['unit_price'], $currency)) ?></td>
                        <td><?= e(admin_money((float) $item['line_total'], $currency)) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="admin-invoice-summary">
            <div><span>Subtotal</span><strong><?= e(admin_money((float) $order['subtotal'], $currency)) ?></strong></div>
            <div><span>Shipping</span><strong><?= e(admin_money((float) ($order['shipping_total'] ?? 0), $currency)) ?></strong></div>
            <div><span>Discount<?= !empty($order['coupon_code']) ? ' · ' . e($order['coupon_code']) : '' ?></span><strong>-<?= e(admin_money((float) ($order['discount_total'] ?? 0), $currency)) ?></strong></div>
            <div><span>Tax</span><strong><?= e(admin_money((float) ($order['tax_total'] ?? 0), $currency)) ?></strong></div>
            <div class="admin-invoice-total"><span>Total</span><strong><?= e(admin_money((float) $order['total'], $currency)) ?></strong></div>
        </div>

        <?php if (!empty($order['notes'])): ?>
            <section class="admin-invoice-notes"><strong>Customer note</strong><p><?= nl2br(e($order['notes'])) ?></p></section>
        <?php endif; ?>
    </section>
</main>
<script src="<?= e(admin_asset_url('assets/js/admin_invoice.js')) ?>" defer></script>
</body>
</html>
