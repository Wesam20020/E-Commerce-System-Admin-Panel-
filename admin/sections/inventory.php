<?php
require_once __DIR__ . '/../includes/layout.php';
$ctx = admin_boot('inventory');
$pdo = $ctx['pdo'];

if (is_post_request()) {
    verify_csrf_or_fail($_POST['_csrf'] ?? null);
    $action = (string) ($_POST['admin_action'] ?? '');
    try {
        if ($action === 'stock_adjust') {
            $productId = admin_int_input('product_id');
            $newStock = admin_int_input('new_stock');
            $reason = admin_clean_text('reason', 160) ?: 'Manual inventory adjustment';
            admin_adjust_product_stock($pdo, $productId, $newStock, $reason);
            flash_set('success', 'Inventory updated.');
        } else {
            throw new RuntimeException('Unknown inventory action.');
        }
    } catch (Throwable $e) {
        admin_flash_from_exception($e);
    }
    admin_redirect('inventory');
}

$q = trim((string) ($_GET['q'] ?? ''));
$risk = (string) ($_GET['risk'] ?? 'all');

$where = [];
$params = [];
if ($q !== '') {
    $where[] = '(p.name LIKE :q OR p.sku LIKE :q OR p.brand LIKE :q)';
    $params['q'] = '%' . $q . '%';
}
if ($risk === 'out') {
    $where[] = 'p.stock <= 0';
} elseif ($risk === 'low') {
    $where[] = 'p.stock > 0 AND p.stock <= 5';
} elseif ($risk === 'safe') {
    $where[] = 'p.stock > 5';
}

$sql = 'SELECT p.id, p.name, p.slug, p.sku, p.brand, p.stock, p.is_active, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY p.stock ASC, p.updated_at DESC, p.id DESC LIMIT 120';
$products = admin_rows($pdo, $sql, $params);

$metrics = [
    'out' => (int) admin_scalar($pdo, 'SELECT COUNT(*) FROM products WHERE stock <= 0'),
    'low' => (int) admin_scalar($pdo, 'SELECT COUNT(*) FROM products WHERE stock > 0 AND stock <= 5'),
    'safe' => (int) admin_scalar($pdo, 'SELECT COUNT(*) FROM products WHERE stock > 5'),
    'units' => (int) admin_scalar($pdo, 'SELECT COALESCE(SUM(stock),0) FROM products'),
];

$movements = admin_rows($pdo, 'SELECT m.*, p.name AS product_name, p.sku FROM inventory_movements m INNER JOIN products p ON p.id = m.product_id ORDER BY m.created_at DESC, m.id DESC LIMIT 20');

admin_header('Inventory', 'Control stock levels, spot risk, and keep a clear movement history without leaving the AJAX console.', 'inventory');
?>
<section class="admin-metrics-grid">
    <?php admin_metric_card('Out of stock', (string) $metrics['out'], 'remove_shopping_cart', 'Stock ≤ 0'); ?>
    <?php admin_metric_card('Low stock', (string) $metrics['low'], 'warning', '1–5 units'); ?>
    <?php admin_metric_card('Safe stock', (string) $metrics['safe'], 'verified', 'More than 5'); ?>
    <?php admin_metric_card('Total units', (string) $metrics['units'], 'warehouse', 'Across catalog'); ?>
</section>

<section class="admin-two-col admin-two-col-wide">
    <article class="admin-card glass-panel">
        <div class="admin-section-head">
            <div><p class="admin-eyebrow">Stock control</p><h2>Inventory table</h2></div>
        </div>
        <form method="get" class="admin-filter-bar inventory-filter">
            <input name="q" value="<?= e($q) ?>" placeholder="Search product, SKU, brand...">
            <select name="risk">
                <option value="all" <?= $risk === 'all' ? 'selected' : '' ?>>All stock</option>
                <option value="out" <?= $risk === 'out' ? 'selected' : '' ?>>Out of stock</option>
                <option value="low" <?= $risk === 'low' ? 'selected' : '' ?>>Low stock</option>
                <option value="safe" <?= $risk === 'safe' ? 'selected' : '' ?>>Safe stock</option>
            </select>
            <button class="admin-ghost-btn" type="submit">Filter</button>
        </form>

        <?php if (!$products): ?>
            <?php admin_empty_state('No inventory rows', 'Products matching your current filters will appear here.'); ?>
        <?php else: ?>
            <div class="admin-table-wrap">
                <table class="admin-table admin-inventory-table">
                    <thead><tr><th>Product</th><th>Current</th><th>Risk</th><th>Set stock</th></tr></thead>
                    <tbody>
                    <?php foreach ($products as $product): ?>
                        <?php
                        $stock = (int) $product['stock'];
                        $riskClass = $stock <= 0 ? 'danger' : ($stock <= 5 ? 'info' : 'good');
                        $riskLabel = $stock <= 0 ? 'Out' : ($stock <= 5 ? 'Low' : 'Safe');
                        ?>
                        <tr>
                            <td>
                                <strong><?= e($product['name']) ?></strong>
                                <small><?= e(($product['sku'] ?: $product['slug']) . ' · ' . ($product['brand'] ?: 'No brand') . ' · ' . ($product['category_name'] ?: 'No category')) ?></small>
                            </td>
                            <td><strong><?= $stock ?></strong></td>
                            <td><span class="admin-pill <?= e($riskClass) ?>"><?= e($riskLabel) ?></span></td>
                            <td>
                                <form method="post" class="admin-stock-inline-form">
                                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="admin_action" value="stock_adjust">
                                    <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                                    <input type="number" name="new_stock" min="0" value="<?= $stock ?>" aria-label="New stock for <?= e($product['name']) ?>">
                                    <input name="reason" value="Manual adjustment" aria-label="Reason">
                                    <button type="submit">Save</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </article>

    <aside class="admin-card glass-panel">
        <div class="admin-section-head"><div><p class="admin-eyebrow">Audit</p><h2>Recent movements</h2></div></div>
        <?php if (!$movements): ?>
            <?php admin_empty_state('No stock history yet', 'Manual stock adjustments will be listed here.'); ?>
        <?php else: ?>
            <div class="admin-timeline">
                <?php foreach ($movements as $move): ?>
                    <article>
                        <span class="admin-timeline-dot"></span>
                        <div>
                            <strong><?= e($move['product_name']) ?></strong>
                            <p><?= (int) $move['previous_stock'] ?> → <?= (int) $move['new_stock'] ?> <span class="<?= (int) $move['delta'] < 0 ? 'admin-negative' : 'admin-positive' ?>"><?= (int) $move['delta'] >= 0 ? '+' : '' ?><?= (int) $move['delta'] ?></span></p>
                            <small><?= e(($move['reason'] ?: 'Adjustment') . ' · ' . ($move['admin_email'] ?: 'Admin') . ' · ' . $move['created_at']) ?></small>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </aside>
</section>
<?php admin_footer(); ?>
