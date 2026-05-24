<?php
require_once __DIR__ . '/../includes/layout.php';
$ctx = admin_boot('exports');
$pdo = $ctx['pdo'];
$currency = $ctx['siteCurrency'];

$counts = [
    'orders' => (int) admin_scalar($pdo, 'SELECT COUNT(*) FROM orders'),
    'products' => (int) admin_scalar($pdo, 'SELECT COUNT(*) FROM products'),
    'customers' => (int) admin_scalar($pdo, 'SELECT COUNT(*) FROM users'),
    'support' => (int) admin_scalar($pdo, 'SELECT COUNT(*) FROM support_messages'),
    'low_stock' => (int) admin_scalar($pdo, "SELECT COUNT(*) FROM products WHERE product_status <> 'archived' AND stock <= 5"),
    'coupons' => (int) admin_scalar($pdo, 'SELECT COUNT(*) FROM coupons'),
];

$csrf = csrf_token();
$exportTypes = [
    'orders' => ['title' => 'Orders ledger', 'icon' => 'receipt_long', 'count' => $counts['orders'], 'desc' => 'Order number, customer, fulfillment, payment, totals, coupon, and tracking.'],
    'products' => ['title' => 'Product catalog', 'icon' => 'inventory_2', 'count' => $counts['products'], 'desc' => 'SKU, slug, category, brand, pricing, stock, status, featured flag, and image.'],
    'customers' => ['title' => 'Customers', 'icon' => 'group', 'count' => $counts['customers'], 'desc' => 'Customer profile, contact fields, address, and signup date.'],
    'support' => ['title' => 'Support messages', 'icon' => 'support_agent', 'count' => $counts['support'], 'desc' => 'Inbox messages, status, order reference, source page, and internal note.'],
    'low_stock' => ['title' => 'Low stock products', 'icon' => 'warning', 'count' => $counts['low_stock'], 'desc' => 'Products at or below 5 units, useful for purchasing and restock planning.'],
    'coupons' => ['title' => 'Coupons', 'icon' => 'local_offer', 'count' => $counts['coupons'], 'desc' => 'Coupon rules, date windows, usage limits, and current usage.'],
];

admin_header('Exports', 'Download clean CSV snapshots for operations, accounting, support, and catalog review.', 'exports');
?>
<section class="admin-metrics-grid" aria-label="Export metrics">
    <?php admin_metric_card('Orders', (string) $counts['orders'], 'receipt_long', 'CSV-ready'); ?>
    <?php admin_metric_card('Products', (string) $counts['products'], 'inventory_2', 'Catalog snapshot'); ?>
    <?php admin_metric_card('Customers', (string) $counts['customers'], 'group', 'User records'); ?>
    <?php admin_metric_card('Support', (string) $counts['support'], 'support_agent', 'Inbox export'); ?>
</section>

<section class="admin-card glass-panel">
    <div class="admin-section-head">
        <div><p class="admin-eyebrow">CSV export center</p><h2>Download operational data</h2></div>
        <span class="admin-pill info">Secure admin-only downloads</span>
    </div>
    <div class="admin-export-grid">
        <?php foreach ($exportTypes as $type => $export): ?>
            <article class="admin-export-card">
                <span class="material-symbols-outlined"><?= e($export['icon']) ?></span>
                <div>
                    <strong><?= e($export['title']) ?></strong>
                    <p><?= e($export['desc']) ?></p>
                    <small><?= number_format((int) $export['count']) ?> rows available</small>
                </div>
                <a class="admin-primary-btn" href="<?= e('export.php?type=' . rawurlencode($type) . '&_csrf=' . rawurlencode($csrf)) ?>" download>Download CSV</a>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<section class="admin-card glass-panel">
    <div class="admin-section-head"><div><p class="admin-eyebrow">Recommended workflow</p><h2>When to export</h2></div></div>
    <div class="admin-action-list">
        <a href="<?= e(admin_page_url('reports')) ?>"><span>Review analytics first</span><strong>Reports</strong></a>
        <a href="<?= e('export.php?type=orders&_csrf=' . rawurlencode($csrf)) ?>"><span>Send order totals to accounting</span><strong>Orders CSV</strong></a>
        <a href="<?= e('export.php?type=low_stock&_csrf=' . rawurlencode($csrf)) ?>"><span>Prepare purchasing list</span><strong>Low stock CSV</strong></a>
        <a href="<?= e('export.php?type=support&_csrf=' . rawurlencode($csrf)) ?>"><span>Audit support backlog</span><strong>Support CSV</strong></a>
    </div>
</section>
<?php admin_footer(); ?>
