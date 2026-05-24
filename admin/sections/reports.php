<?php
require_once __DIR__ . '/../includes/layout.php';
$ctx = admin_boot('reports');
$pdo = $ctx['pdo'];
$currency = $ctx['siteCurrency'];

$today = new DateTimeImmutable('today');
$defaultFrom = $today->modify('-29 days')->format('Y-m-d');
$defaultTo = $today->format('Y-m-d');

$from = trim((string) ($_GET['from'] ?? $defaultFrom));
$to = trim((string) ($_GET['to'] ?? $defaultTo));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
    $from = $defaultFrom;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    $to = $defaultTo;
}
$fromTime = strtotime($from . ' 00:00:00');
$toTime = strtotime($to . ' 00:00:00');
if (!$fromTime || !$toTime || $fromTime > $toTime) {
    $from = $defaultFrom;
    $to = $defaultTo;
    $fromTime = strtotime($from . ' 00:00:00');
    $toTime = strtotime($to . ' 00:00:00');
}
$toExclusive = date('Y-m-d 00:00:00', strtotime($to . ' +1 day'));
$rangeParams = [
    'from_date' => date('Y-m-d 00:00:00', (int) $fromTime),
    'to_date' => $toExclusive,
];
$whereRange = 'created_at >= :from_date AND created_at < :to_date';
$orderRange = 'o.created_at >= :from_date AND o.created_at < :to_date';

$summary = admin_rows($pdo, "SELECT COUNT(*) AS orders_count, COALESCE(SUM(total),0) AS gross_total, COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total ELSE 0 END),0) AS paid_total, COALESCE(SUM(discount_total),0) AS discount_total, COALESCE(SUM(shipping_total),0) AS shipping_total, COALESCE(AVG(NULLIF(total,0)),0) AS avg_order_value, SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) AS paid_orders, SUM(CASE WHEN status IN ('pending','processing') THEN 1 ELSE 0 END) AS open_orders FROM orders WHERE {$whereRange}", $rangeParams);
$summary = $summary[0] ?? [];
$newCustomers = (int) admin_scalar($pdo, "SELECT COUNT(*) FROM users WHERE {$whereRange}", $rangeParams);
$supportMessages = (int) admin_scalar($pdo, "SELECT COUNT(*) FROM support_messages WHERE {$whereRange}", $rangeParams);
$lowStockCount = (int) admin_scalar($pdo, 'SELECT COUNT(*) FROM products WHERE is_active = 1 AND stock <= 5');

$dailySales = admin_rows($pdo, "SELECT DATE(created_at) AS report_date, COUNT(*) AS orders_count, COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total ELSE 0 END),0) AS paid_total FROM orders WHERE {$whereRange} GROUP BY DATE(created_at) ORDER BY report_date ASC", $rangeParams);
$maxDailyPaid = 1.0;
foreach ($dailySales as $day) {
    $maxDailyPaid = max($maxDailyPaid, (float) ($day['paid_total'] ?? 0));
}

$topProducts = admin_rows($pdo, "SELECT oi.product_id, oi.product_name, COALESCE(p.sku, '') AS sku, COALESCE(p.stock, 0) AS stock, COALESCE(p.slug, '') AS slug, SUM(oi.qty) AS qty_sold, COALESCE(SUM(oi.line_total),0) AS sales_total FROM order_items oi INNER JOIN orders o ON o.id = oi.order_id LEFT JOIN products p ON p.id = oi.product_id WHERE {$orderRange} GROUP BY oi.product_id, oi.product_name, p.sku, p.stock, p.slug ORDER BY qty_sold DESC, sales_total DESC LIMIT 10", $rangeParams);
$orderStatuses = admin_rows($pdo, "SELECT status, COUNT(*) AS orders_count, COALESCE(SUM(total),0) AS total_value FROM orders WHERE {$whereRange} GROUP BY status ORDER BY orders_count DESC, status ASC", $rangeParams);
$paymentStatuses = admin_rows($pdo, "SELECT payment_status, COUNT(*) AS orders_count, COALESCE(SUM(total),0) AS total_value FROM orders WHERE {$whereRange} GROUP BY payment_status ORDER BY orders_count DESC, payment_status ASC", $rangeParams);
$shippingBreakdown = admin_rows($pdo, "SELECT COALESCE(NULLIF(shipping_method_name,''), 'Not selected') AS method_name, COUNT(*) AS orders_count, COALESCE(SUM(shipping_total),0) AS shipping_total, COALESCE(SUM(total),0) AS total_value FROM orders WHERE {$whereRange} GROUP BY COALESCE(NULLIF(shipping_method_name,''), 'Not selected') ORDER BY orders_count DESC, method_name ASC LIMIT 8", $rangeParams);
$paymentBreakdown = admin_rows($pdo, "SELECT COALESCE(NULLIF(payment_method_name,''), 'Not selected') AS method_name, COUNT(*) AS orders_count, COALESCE(SUM(total),0) AS total_value FROM orders WHERE {$whereRange} GROUP BY COALESCE(NULLIF(payment_method_name,''), 'Not selected') ORDER BY orders_count DESC, method_name ASC LIMIT 8", $rangeParams);
$lowStockProducts = admin_rows($pdo, 'SELECT id, name, slug, sku, brand, stock, is_active FROM products WHERE is_active = 1 AND stock <= 5 ORDER BY stock ASC, updated_at DESC, id DESC LIMIT 12');
$coupons = admin_rows($pdo, "SELECT c.code, c.discount_type, c.discount_value, c.used_count, c.max_uses, c.is_active, COUNT(o.id) AS period_orders, COALESCE(SUM(o.discount_total),0) AS period_discount FROM coupons c LEFT JOIN orders o ON o.coupon_id = c.id AND {$orderRange} GROUP BY c.id, c.code, c.discount_type, c.discount_value, c.used_count, c.max_uses, c.is_active ORDER BY period_orders DESC, period_discount DESC, c.used_count DESC, c.code ASC LIMIT 10", $rangeParams);

$ordersCount = (int) ($summary['orders_count'] ?? 0);
$paidTotal = (float) ($summary['paid_total'] ?? 0);
$grossTotal = (float) ($summary['gross_total'] ?? 0);
$avgOrder = (float) ($summary['avg_order_value'] ?? 0);
$paidOrders = (int) ($summary['paid_orders'] ?? 0);
$openOrders = (int) ($summary['open_orders'] ?? 0);
$rangeLabel = date('M j, Y', (int) $fromTime) . ' → ' . date('M j, Y', (int) $toTime);

admin_header('Reports', 'Sales, order, product, inventory, shipping, payment, and coupon analytics for the selected period.', 'reports');
?>
<section class="admin-card glass-panel admin-report-toolbar">
    <div>
        <p class="admin-eyebrow">Analytics range</p>
        <h2><?= e($rangeLabel) ?></h2>
        <p>Reports use order creation dates. Paid revenue only includes orders whose payment status is paid.</p>
    </div>
    <form method="get" class="admin-filter-bar admin-report-filter">
        <label class="admin-field"><span>From</span><input type="date" name="from" value="<?= e($from) ?>"></label>
        <label class="admin-field"><span>To</span><input type="date" name="to" value="<?= e($to) ?>"></label>
        <button class="admin-primary-btn" type="submit">Apply range</button>
        <a class="admin-ghost-btn" href="<?= e(admin_page_url('reports')) ?>">Reset</a>
    </form>
</section>

<section class="admin-metrics-grid" aria-label="Report metrics">
    <?php admin_metric_card('Paid revenue', admin_money($paidTotal, $currency), 'payments', $paidOrders . ' paid orders'); ?>
    <?php admin_metric_card('Gross order value', admin_money($grossTotal, $currency), 'shopping_cart', $ordersCount . ' total orders'); ?>
    <?php admin_metric_card('Average order', admin_money($avgOrder, $currency), 'monitoring', 'Across selected range'); ?>
    <?php admin_metric_card('Open orders', (string) $openOrders, 'pending_actions', 'Pending / processing'); ?>
    <?php admin_metric_card('Discounts given', admin_money((float) ($summary['discount_total'] ?? 0), $currency), 'redeem', 'Coupon and manual discounts'); ?>
    <?php admin_metric_card('Shipping collected', admin_money((float) ($summary['shipping_total'] ?? 0), $currency), 'local_shipping', 'Shipping totals'); ?>
    <?php admin_metric_card('New customers', (string) $newCustomers, 'person_add', 'Registered in range'); ?>
    <?php admin_metric_card('Low stock', (string) $lowStockCount, 'warning', 'Active products ≤ 5'); ?>
</section>

<section class="admin-card glass-panel">
    <div class="admin-section-head"><div><p class="admin-eyebrow">Sales trend</p><h2>Daily paid revenue</h2></div><a class="admin-text-link" href="<?= e(admin_page_url('orders', ['from' => $from, 'to' => $to])) ?>">Open orders</a></div>
    <?php if (!$dailySales): ?>
        <?php admin_empty_state('No sales activity', 'Paid revenue bars will appear after orders exist in the selected range.'); ?>
    <?php else: ?>
        <div class="admin-report-bars" aria-label="Daily paid revenue bars">
            <?php foreach ($dailySales as $day): ?>
                <?php $barWidth = max(4, min(100, (int) round(((float) $day['paid_total'] / $maxDailyPaid) * 100))); ?>
                <article class="admin-report-bar-row">
                    <div><strong><?= e(date('M j', strtotime((string) $day['report_date']))) ?></strong><small><?= (int) $day['orders_count'] ?> orders</small></div>
                    <progress class="admin-report-progress" max="100" value="<?= (int) $barWidth ?>"><?= (int) $barWidth ?>%</progress>
                    <b><?= e(admin_money((float) $day['paid_total'], $currency)) ?></b>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="admin-two-col admin-report-grid">
    <article class="admin-card glass-panel">
        <div class="admin-section-head"><div><p class="admin-eyebrow">Products</p><h2>Best sellers</h2></div><a class="admin-text-link" href="<?= e(admin_page_url('products')) ?>">Manage products</a></div>
        <?php if (!$topProducts): ?>
            <?php admin_empty_state('No product sales yet', 'The best seller table will fill after checkout orders are created.'); ?>
        <?php else: ?>
            <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Product</th><th>Qty</th><th>Sales</th><th>Stock</th></tr></thead><tbody>
            <?php foreach ($topProducts as $product): ?>
                <tr><td><strong><?= e($product['product_name']) ?></strong><small><?= e($product['sku'] ?: 'No SKU') ?></small></td><td><?= (int) $product['qty_sold'] ?></td><td><?= e(admin_money((float) $product['sales_total'], $currency)) ?></td><td><span class="admin-pill <?= (int) $product['stock'] <= 5 ? 'warning' : 'good' ?>"><?= (int) $product['stock'] ?></span></td></tr>
            <?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
    </article>

    <article class="admin-card glass-panel">
        <div class="admin-section-head"><div><p class="admin-eyebrow">Inventory</p><h2>Low stock products</h2></div><a class="admin-text-link" href="<?= e(admin_page_url('inventory')) ?>">Open inventory</a></div>
        <?php if (!$lowStockProducts): ?>
            <?php admin_empty_state('No low stock products', 'All active products are currently above the low-stock threshold.'); ?>
        <?php else: ?>
            <div class="admin-compact-list">
                <?php foreach ($lowStockProducts as $product): ?>
                    <a href="<?= e(admin_page_url('products', ['edit' => (int) $product['id']])) ?>"><span><?= e($product['name']) ?><small><?= e($product['brand'] ?: ($product['sku'] ?: 'No brand / SKU')) ?></small></span><strong><?= (int) $product['stock'] ?> left</strong></a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </article>
</section>

<section class="admin-two-col admin-report-grid">
    <article class="admin-card glass-panel">
        <div class="admin-section-head"><div><p class="admin-eyebrow">Orders</p><h2>Status breakdown</h2></div></div>
        <?php if (!$orderStatuses): ?>
            <?php admin_empty_state('No orders in range', 'Order status counts will appear here.'); ?>
        <?php else: ?>
            <div class="admin-report-stats">
                <?php foreach ($orderStatuses as $status): ?>
                    <div><span><b><?= e(ucfirst((string) $status['status'])) ?></b><small><?= e(admin_money((float) $status['total_value'], $currency)) ?></small></span><strong><?= (int) $status['orders_count'] ?></strong></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </article>

    <article class="admin-card glass-panel">
        <div class="admin-section-head"><div><p class="admin-eyebrow">Payments</p><h2>Payment status</h2></div></div>
        <?php if (!$paymentStatuses): ?>
            <?php admin_empty_state('No payments in range', 'Payment status counts will appear here.'); ?>
        <?php else: ?>
            <div class="admin-report-stats">
                <?php foreach ($paymentStatuses as $status): ?>
                    <div><span><b><?= e(ucfirst((string) $status['payment_status'])) ?></b><small><?= e(admin_money((float) $status['total_value'], $currency)) ?></small></span><strong><?= (int) $status['orders_count'] ?></strong></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </article>
</section>

<section class="admin-two-col admin-report-grid">
    <article class="admin-card glass-panel">
        <div class="admin-section-head"><div><p class="admin-eyebrow">Fulfillment</p><h2>Shipping methods</h2></div><a class="admin-text-link" href="<?= e(admin_page_url('shipping_payments')) ?>">Configure</a></div>
        <?php if (!$shippingBreakdown): ?>
            <?php admin_empty_state('No shipping data', 'Shipping method usage will appear once checkout orders exist.'); ?>
        <?php else: ?>
            <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Method</th><th>Orders</th><th>Shipping</th><th>Total</th></tr></thead><tbody>
            <?php foreach ($shippingBreakdown as $row): ?>
                <tr><td><strong><?= e($row['method_name']) ?></strong></td><td><?= (int) $row['orders_count'] ?></td><td><?= e(admin_money((float) $row['shipping_total'], $currency)) ?></td><td><?= e(admin_money((float) $row['total_value'], $currency)) ?></td></tr>
            <?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
    </article>

    <article class="admin-card glass-panel">
        <div class="admin-section-head"><div><p class="admin-eyebrow">Checkout</p><h2>Payment methods</h2></div><a class="admin-text-link" href="<?= e(admin_page_url('shipping_payments')) ?>">Configure</a></div>
        <?php if (!$paymentBreakdown): ?>
            <?php admin_empty_state('No payment method data', 'Payment method usage will appear once checkout orders exist.'); ?>
        <?php else: ?>
            <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Method</th><th>Orders</th><th>Total</th></tr></thead><tbody>
            <?php foreach ($paymentBreakdown as $row): ?>
                <tr><td><strong><?= e($row['method_name']) ?></strong></td><td><?= (int) $row['orders_count'] ?></td><td><?= e(admin_money((float) $row['total_value'], $currency)) ?></td></tr>
            <?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
    </article>
</section>

<section class="admin-card glass-panel">
    <div class="admin-section-head"><div><p class="admin-eyebrow">Promotions</p><h2>Coupon performance</h2></div><a class="admin-text-link" href="<?= e(admin_page_url('coupons')) ?>">Manage coupons</a></div>
    <?php if (!$coupons): ?>
        <?php admin_empty_state('No coupons yet', 'Coupon performance will appear after you create coupons and orders use them.'); ?>
    <?php else: ?>
        <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Coupon</th><th>Discount</th><th>Period orders</th><th>Period discount</th><th>All-time uses</th><th>Status</th></tr></thead><tbody>
        <?php foreach ($coupons as $coupon): ?>
            <tr><td><strong><?= e($coupon['code']) ?></strong></td><td><?= e($coupon['discount_type'] === 'fixed' ? admin_money((float) $coupon['discount_value'], $currency) : ((float) $coupon['discount_value']) . '%') ?></td><td><?= (int) $coupon['period_orders'] ?></td><td><?= e(admin_money((float) $coupon['period_discount'], $currency)) ?></td><td><?= (int) $coupon['used_count'] ?><?= $coupon['max_uses'] ? ' / ' . (int) $coupon['max_uses'] : '' ?></td><td><span class="admin-pill <?= (int) $coupon['is_active'] === 1 ? 'good' : 'danger' ?>"><?= (int) $coupon['is_active'] === 1 ? 'Active' : 'Hidden' ?></span></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
</section>
<?php admin_footer(); ?>
