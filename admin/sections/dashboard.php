<?php
require_once __DIR__ . '/../includes/layout.php';
$ctx = admin_boot('index');
$pdo = $ctx['pdo'];
$currency = $ctx['siteCurrency'];
$notificationCounts = admin_notification_counts($pdo, $ctx);

$metrics = [
    'products' => (int) admin_scalar($pdo, 'SELECT COUNT(*) FROM products WHERE is_active = 1'),
    'all_products' => (int) admin_scalar($pdo, 'SELECT COUNT(*) FROM products'),
    'orders' => (int) admin_scalar($pdo, 'SELECT COUNT(*) FROM orders'),
    'revenue' => (float) admin_scalar($pdo, "SELECT COALESCE(SUM(total),0) FROM orders WHERE payment_status = 'paid'"),
    'customers' => (int) admin_scalar($pdo, 'SELECT COUNT(*) FROM users'),
    'low_stock' => (int) admin_scalar($pdo, 'SELECT COUNT(*) FROM products WHERE is_active = 1 AND stock <= 5'),
    'messages' => (int) admin_scalar($pdo, "SELECT COUNT(*) FROM support_messages WHERE status IN ('new','open')"),
    'coupons' => (int) admin_scalar($pdo, 'SELECT COUNT(*) FROM coupons WHERE is_active = 1'),
    'media' => (int) admin_scalar($pdo, 'SELECT COUNT(*) FROM media_assets'),
    'deals' => admin_active_deals_count($pdo),
    'active_shipping' => (int) admin_scalar($pdo, 'SELECT COUNT(*) FROM shipping_methods WHERE is_active = 1'),
    'active_payment' => (int) admin_scalar($pdo, 'SELECT COUNT(*) FROM payment_methods WHERE is_active = 1'),
    'seo_missing' => (int) admin_scalar($pdo, "SELECT COUNT(*) FROM site_pages WHERE is_active = 1 AND ((meta_title IS NULL OR meta_title = '') OR (meta_description IS NULL OR meta_description = ''))"),
    'maintenance' => ($ctx['siteSettings']['maintenance_mode'] ?? '0') === '1' ? 1 : 0,
    'active_notifications' => (int) $notificationCounts['active'],
    'queued_emails' => (int) admin_scalar($pdo, "SELECT COUNT(*) FROM email_outbox WHERE status = 'queued'"),
    'failed_emails' => (int) admin_scalar($pdo, "SELECT COUNT(*) FROM email_outbox WHERE status = 'failed'"),
    'critical_notifications' => (int) $notificationCounts['critical'],
];

$recentOrders = admin_rows($pdo, 'SELECT id, order_number, full_name, email, total, status, payment_status, created_at FROM orders ORDER BY created_at DESC, id DESC LIMIT 6');
$stockRisks = admin_rows($pdo, 'SELECT id, name, slug, stock, brand FROM products WHERE is_active = 1 AND stock <= 5 ORDER BY stock ASC, updated_at DESC LIMIT 6');
$topCategories = admin_rows($pdo, 'SELECT c.name, c.slug, COUNT(p.id) product_count FROM categories c LEFT JOIN products p ON p.category_id = c.id GROUP BY c.id, c.name, c.slug ORDER BY product_count DESC, c.name ASC LIMIT 6');
$recentActivity = admin_rows($pdo, 'SELECT * FROM admin_activity_logs ORDER BY created_at DESC, id DESC LIMIT 6');

$quickActions = [
    ['label' => 'Add product', 'page' => admin_page_url('product_edit'), 'icon' => 'add_box'],
    ['label' => 'Orders', 'page' => admin_page_url('orders'), 'icon' => 'receipt_long'],
    ['label' => 'Inventory', 'page' => admin_page_url('inventory'), 'icon' => 'warehouse'],
    ['label' => 'Support', 'page' => admin_page_url('support'), 'icon' => 'support_agent'],
    ['label' => 'Media', 'page' => admin_page_url('media'), 'icon' => 'photo_library'],
    ['label' => 'Reports', 'page' => admin_page_url('reports'), 'icon' => 'analytics'],
];

admin_header('Dashboard', 'A focused work center for daily store operations.', 'index');
?>
<section class="admin-dashboard-shortcuts" aria-label="Quick actions">
    <article class="admin-card glass-panel admin-quick-card">
        <div class="admin-section-head"><div><p class="admin-eyebrow">Shortcuts</p><h2>Quick actions</h2></div></div>
        <div class="admin-quick-grid">
            <?php foreach ($quickActions as $action): ?>
                <a href="<?= e($action['page']) ?>"><span class="material-symbols-outlined"><?= e($action['icon']) ?></span><strong><?= e($action['label']) ?></strong></a>
            <?php endforeach; ?>
        </div>
    </article>
</section>

<section class="admin-metrics-grid admin-metrics-grid-focused" aria-label="Store metrics">
    <?php admin_metric_card('Orders', (string) $metrics['orders'], 'receipt_long', 'All time'); ?>
    <?php admin_metric_card('Paid revenue', admin_money($metrics['revenue'], $currency), 'payments', 'Paid only'); ?>
    <?php admin_metric_card('Products', (string) $metrics['products'], 'inventory_2', $metrics['all_products'] . ' total'); ?>
    <?php admin_metric_card('Support', (string) $metrics['messages'], 'support_agent', 'Open messages'); ?>
    <?php admin_metric_card('Low stock', (string) $metrics['low_stock'], 'warning', 'Stock ≤ 5'); ?>
</section>

<section class="admin-two-col admin-dashboard-main">
    <article class="admin-card glass-panel">
        <div class="admin-section-head"><div><p class="admin-eyebrow">Sales</p><h2>Recent orders</h2></div><a class="admin-text-link" href="<?= e(admin_page_url('orders')) ?>">Manage</a></div>
        <?php if (!$recentOrders): ?>
            <?php admin_empty_state('No orders yet', 'Orders will appear here after checkout activity starts.'); ?>
        <?php else: ?>
            <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Order</th><th>Customer</th><th>Total</th><th>Status</th></tr></thead><tbody>
            <?php foreach ($recentOrders as $order): ?>
                <tr><td><strong><?= e($order['order_number']) ?></strong><small><?= e((string) $order['created_at']) ?></small></td><td><?= e($order['full_name']) ?><small><?= e($order['email']) ?></small></td><td><?= e(admin_money($order['total'], $currency)) ?></td><td><span class="admin-pill <?= e(admin_status_class($order['status'])) ?>"><?= e($order['status']) ?></span></td></tr>
            <?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
    </article>

    <article class="admin-card glass-panel">
        <div class="admin-section-head"><div><p class="admin-eyebrow">Inventory</p><h2>Stock risks</h2></div><a class="admin-text-link" href="<?= e(admin_page_url('inventory')) ?>">Open</a></div>
        <?php if (!$stockRisks): ?>
            <?php admin_empty_state('Inventory looks safe', 'No active products are currently at or below 5 units.'); ?>
        <?php else: ?>
            <div class="admin-compact-list admin-stock-risk-list">
                <?php foreach ($stockRisks as $product): ?>
                    <a href="<?= e(admin_page_url('product_edit', ['id' => (int) $product['id']])) ?>"><span><?= e($product['name']) ?><small><?= e($product['brand'] ?: 'No brand') ?></small></span><strong><?= (int) $product['stock'] ?> left</strong></a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </article>
</section>

<details class="admin-card glass-panel admin-dashboard-more">
    <summary><span>More operational context</span><span class="material-symbols-outlined">expand_more</span></summary>
    <div class="admin-dashboard-more-grid">
        <section>
            <div class="admin-section-head"><div><p class="admin-eyebrow">Catalog</p><h2>Category coverage</h2></div><a class="admin-text-link" href="<?= e(admin_page_url('categories')) ?>">Manage</a></div>
            <div class="admin-compact-list">
                <?php foreach ($topCategories as $category): ?>
                    <a href="<?= e(admin_root_url('products.php', ['category' => $category['slug']])) ?>"><span><?= e($category['name']) ?></span><strong><?= (int) $category['product_count'] ?></strong></a>
                <?php endforeach; ?>
            </div>
        </section>
        <section>
            <div class="admin-section-head"><div><p class="admin-eyebrow">Audit</p><h2>Recent admin activity</h2></div></div>
            <?php if (!$recentActivity): ?>
                <?php admin_empty_state('No admin activity yet', 'Product, stock, and order changes will appear here automatically.'); ?>
            <?php else: ?>
                <div class="admin-timeline admin-timeline-compact">
                    <?php foreach ($recentActivity as $activity): ?>
                        <article>
                            <span class="admin-timeline-dot"></span>
                            <div>
                                <strong><?= e(str_replace('_', ' ', ucfirst((string) $activity['action']))) ?></strong>
                                <?php if (!empty($activity['details'])): ?><p><?= e($activity['details']) ?></p><?php endif; ?>
                                <small><?= e(($activity['admin_email'] ?: 'Admin') . ' · ' . ($activity['entity_type'] ?: 'system') . ($activity['entity_id'] ? ' #' . $activity['entity_id'] : '') . ' · ' . $activity['created_at']) ?></small>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
</details>

<?php admin_footer(); ?>
