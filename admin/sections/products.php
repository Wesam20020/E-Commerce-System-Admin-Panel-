<?php
require_once __DIR__ . '/../includes/layout.php';
$ctx = admin_boot('products');
$pdo = $ctx['pdo'];
$currency = $ctx['siteCurrency'];

if (is_post_request()) {
    verify_csrf_or_fail($_POST['_csrf'] ?? null);
    $action = (string) ($_POST['admin_action'] ?? '');
    try {
        if ($action === 'product_bulk_action') {
            $ids = admin_int_array_input('product_ids');
            $bulkAction = (string) ($_POST['bulk_action'] ?? '');
            if (!$ids) {
                throw new RuntimeException('Select at least one published product.');
            }
            [$placeholders, $bulkParams] = admin_sql_placeholders($ids, 'product');
            $inSql = implode(',', $placeholders);

            if ($bulkAction === 'mark_active') {
                $pdo->prepare("UPDATE products SET product_status = CASE WHEN stock <= 0 THEN 'out_of_stock' ELSE 'active' END, is_active = 1 WHERE id IN ({$inSql})")->execute($bulkParams);
            } elseif ($bulkAction === 'mark_draft') {
                $pdo->prepare("UPDATE products SET product_status = 'draft', is_active = 0 WHERE id IN ({$inSql})")->execute($bulkParams);
            } elseif ($bulkAction === 'archive') {
                $pdo->prepare("UPDATE products SET product_status = 'archived', is_active = 0 WHERE id IN ({$inSql})")->execute($bulkParams);
            } elseif ($bulkAction === 'feature') {
                $pdo->prepare("UPDATE products SET is_featured = 1 WHERE id IN ({$inSql})")->execute($bulkParams);
            } elseif ($bulkAction === 'unfeature') {
                $pdo->prepare("UPDATE products SET is_featured = 0 WHERE id IN ({$inSql})")->execute($bulkParams);
            } elseif ($bulkAction === 'sync_stock_status') {
                $pdo->prepare("UPDATE products SET product_status = CASE WHEN stock <= 0 THEN 'out_of_stock' ELSE 'active' END, is_active = 1 WHERE id IN ({$inSql})")->execute($bulkParams);
            } elseif ($bulkAction === 'set_stock') {
                $newStock = max(0, admin_int_input('bulk_stock'));
                foreach ($ids as $productId) {
                    admin_adjust_product_stock($pdo, $productId, $newStock, 'Bulk update from published products');
                }
                $pdo->prepare("UPDATE products SET product_status = CASE WHEN stock <= 0 THEN 'out_of_stock' ELSE 'active' END, is_active = 1 WHERE id IN ({$inSql})")->execute($bulkParams);
            } elseif ($bulkAction === 'set_discount') {
                $discount = max(1, min(95, admin_int_input('bulk_discount_percent')));
                $params = $bulkParams + ['discount_factor' => 1 - ($discount / 100)];
                $pdo->prepare("UPDATE products SET compare_price = ROUND(price / :discount_factor, 2) WHERE price > 0 AND id IN ({$inSql})")->execute($params);
            } elseif ($bulkAction === 'clear_discount') {
                $pdo->prepare("UPDATE products SET compare_price = NULL WHERE id IN ({$inSql})")->execute($bulkParams);
            } elseif ($bulkAction === 'set_category') {
                $categoryId = admin_int_input('bulk_category_id');
                if ($categoryId <= 0) {
                    throw new RuntimeException('Choose a category before applying this action.');
                }
                $params = $bulkParams + ['category_id' => $categoryId];
                $pdo->prepare("UPDATE products SET category_id = :category_id WHERE id IN ({$inSql})")->execute($params);
            } elseif ($bulkAction === 'set_brand') {
                $brand = admin_clean_text('bulk_brand', 150);
                if ($brand === '') {
                    throw new RuntimeException('Write a brand before applying this action.');
                }
                $params = $bulkParams + ['brand' => $brand];
                $pdo->prepare("UPDATE products SET brand = :brand WHERE id IN ({$inSql})")->execute($params);
            } else {
                throw new RuntimeException('Choose a valid bulk product action.');
            }
            admin_log_activity($pdo, 'published_products_bulk_updated', 'product', null, count($ids) . ' published products updated: ' . $bulkAction);
            flash_set('success', count($ids) . ' published products updated.');
        } elseif ($action === 'product_stock_adjust') {
            $id = admin_int_input('product_id');
            $newStock = admin_int_input('new_stock');
            admin_adjust_product_stock($pdo, $id, $newStock, 'Quick update from published products');
            $pdo->prepare("UPDATE products SET product_status = CASE WHEN product_status IN ('draft','archived') THEN product_status WHEN stock <= 0 THEN 'out_of_stock' ELSE 'active' END, is_active = CASE WHEN product_status IN ('draft','archived') THEN is_active ELSE 1 END WHERE id = :id LIMIT 1")->execute(['id' => $id]);
            flash_set('success', 'Stock updated.');
        } else {
            throw new RuntimeException('Unknown product action.');
        }
    } catch (Throwable $e) {
        admin_flash_from_exception($e);
    }
    admin_redirect('products', $_GET);
}

$categories = admin_rows($pdo, 'SELECT id, name, slug FROM categories ORDER BY name ASC');
$brands = admin_rows($pdo, "SELECT DISTINCT brand FROM products WHERE brand IS NOT NULL AND brand <> '' ORDER BY brand ASC");
$productTypes = admin_product_type_options();

$view = preg_replace('/[^a-z_]+/', '', strtolower((string) ($_GET['view'] ?? 'published'))) ?: 'published';
$allowedViews = ['published', 'available', 'out_of_stock', 'low_stock', 'missing_image', 'needs_review', 'featured', 'discounted', 'stock_mismatch', 'no_sales', 'stale'];
if (!in_array($view, $allowedViews, true)) {
    $view = 'published';
}
$q = trim((string) ($_GET['q'] ?? ''));
$categoryId = (int) ($_GET['category_id'] ?? 0);
$brand = trim((string) ($_GET['brand'] ?? ''));
$type = preg_replace('/[^a-z0-9_]+/', '', strtolower((string) ($_GET['product_type'] ?? ''))) ?: '';
$stockState = preg_replace('/[^a-z_]+/', '', strtolower((string) ($_GET['stock_state'] ?? ''))) ?: '';
$mediaState = preg_replace('/[^a-z_]+/', '', strtolower((string) ($_GET['media_state'] ?? ''))) ?: '';
$dealState = preg_replace('/[^a-z_]+/', '', strtolower((string) ($_GET['deal_state'] ?? ''))) ?: '';
$sort = preg_replace('/[^a-z_]+/', '', strtolower((string) ($_GET['sort'] ?? 'updated_desc'))) ?: 'updated_desc';

$publishedBase = "p.is_active = 1 AND p.product_status IN ('active','out_of_stock')";
$needsReviewSql = admin_product_readiness_sql_condition('p');
$hasSalesTables = admin_table_exists($pdo, 'orders') && admin_table_exists($pdo, 'order_items');
$where = [$publishedBase];
$params = [];

if ($view === 'available') {
    $where[] = "p.product_status = 'active' AND p.stock > 0";
} elseif ($view === 'out_of_stock') {
    $where[] = "(p.product_status = 'out_of_stock' OR p.stock <= 0)";
} elseif ($view === 'low_stock') {
    $where[] = "p.product_status = 'active' AND p.stock BETWEEN 1 AND 5";
} elseif ($view === 'missing_image') {
    $where[] = "(p.image IS NULL OR p.image = '')";
} elseif ($view === 'needs_review') {
    $where[] = $needsReviewSql;
} elseif ($view === 'featured') {
    $where[] = 'p.is_featured = 1';
} elseif ($view === 'discounted') {
    $where[] = 'p.compare_price IS NOT NULL AND p.compare_price > p.price';
} elseif ($view === 'stock_mismatch') {
    $where[] = "((p.product_status = 'active' AND p.stock <= 0) OR (p.product_status = 'out_of_stock' AND p.stock > 0))";
} elseif ($view === 'no_sales') {
    $where[] = $hasSalesTables ? 'NOT EXISTS (SELECT 1 FROM order_items oi WHERE oi.product_id = p.id)' : '1 = 0';
} elseif ($view === 'stale') {
    $where[] = 'p.updated_at < DATE_SUB(NOW(), INTERVAL 30 DAY)';
}

if ($q !== '') {
    $where[] = '(p.name LIKE :q OR p.sku LIKE :q OR p.brand LIKE :q OR p.short_description LIKE :q OR p.slug LIKE :q)';
    $params['q'] = '%' . $q . '%';
}
if ($categoryId > 0) {
    $where[] = 'p.category_id = :category_id';
    $params['category_id'] = $categoryId;
}
if ($brand !== '') {
    $where[] = 'p.brand = :brand';
    $params['brand'] = $brand;
}
if ($type !== '' && array_key_exists($type, $productTypes)) {
    $where[] = 'p.product_type = :product_type';
    $params['product_type'] = $type;
}
if ($stockState === 'in_stock') {
    $where[] = 'p.stock > 5';
} elseif ($stockState === 'low') {
    $where[] = 'p.stock BETWEEN 1 AND 5';
} elseif ($stockState === 'zero') {
    $where[] = 'p.stock <= 0';
}
if ($mediaState === 'has_image') {
    $where[] = "p.image IS NOT NULL AND p.image <> ''";
} elseif ($mediaState === 'missing_image') {
    $where[] = "(p.image IS NULL OR p.image = '')";
}
if ($dealState === 'discounted') {
    $where[] = 'p.compare_price IS NOT NULL AND p.compare_price > p.price';
} elseif ($dealState === 'regular') {
    $where[] = '(p.compare_price IS NULL OR p.compare_price <= p.price)';
}

$salesJoin = $hasSalesTables ? "
LEFT JOIN (
    SELECT oi.product_id, COUNT(DISTINCT oi.order_id) AS orders_count, COALESCE(SUM(oi.qty), 0) AS qty_sold, COALESCE(SUM(oi.line_total), 0) AS revenue, MAX(o.created_at) AS last_order_at
    FROM order_items oi
    INNER JOIN orders o ON o.id = oi.order_id
    WHERE oi.product_id IS NOT NULL
    GROUP BY oi.product_id
) s ON s.product_id = p.id" : '';
$salesSelect = $hasSalesTables ? 'COALESCE(s.orders_count,0) AS orders_count, COALESCE(s.qty_sold,0) AS qty_sold, COALESCE(s.revenue,0) AS revenue, s.last_order_at' : '0 AS orders_count, 0 AS qty_sold, 0 AS revenue, NULL AS last_order_at';

$orderBy = match ($sort) {
    'name_asc' => 'p.name ASC, p.id DESC',
    'price_desc' => 'p.price DESC, p.id DESC',
    'price_asc' => 'p.price ASC, p.id DESC',
    'stock_desc' => 'p.stock DESC, p.id DESC',
    'stock_asc' => 'p.stock ASC, p.id DESC',
    'sales_desc' => $hasSalesTables ? 'COALESCE(s.qty_sold,0) DESC, COALESCE(s.revenue,0) DESC, p.updated_at DESC' : 'p.updated_at DESC, p.id DESC',
    'readiness_asc' => "CASE WHEN {$needsReviewSql} THEN 0 ELSE 1 END ASC, p.updated_at DESC, p.id DESC",
    default => 'p.updated_at DESC, p.id DESC',
};

$sql = "SELECT p.*, c.name AS category_name, {$salesSelect}
FROM products p
LEFT JOIN categories c ON c.id = p.category_id
{$salesJoin}
WHERE " . implode(' AND ', $where) . "
ORDER BY {$orderBy}
LIMIT 120";
$products = admin_rows($pdo, $sql, $params);

$metricBase = "is_active = 1 AND product_status IN ('active','out_of_stock')";
$metricNeedsReview = admin_product_readiness_sql_condition('');
$catalogMetrics = [
    'published' => (int) admin_scalar($pdo, "SELECT COUNT(*) FROM products WHERE {$metricBase}"),
    'available' => (int) admin_scalar($pdo, "SELECT COUNT(*) FROM products WHERE {$metricBase} AND product_status = 'active' AND stock > 0"),
    'out' => (int) admin_scalar($pdo, "SELECT COUNT(*) FROM products WHERE {$metricBase} AND (product_status = 'out_of_stock' OR stock <= 0)"),
    'low' => (int) admin_scalar($pdo, "SELECT COUNT(*) FROM products WHERE {$metricBase} AND product_status = 'active' AND stock BETWEEN 1 AND 5"),
    'missing_image' => (int) admin_scalar($pdo, "SELECT COUNT(*) FROM products WHERE {$metricBase} AND (image IS NULL OR image = '')"),
    'needs_review' => (int) admin_scalar($pdo, "SELECT COUNT(*) FROM products WHERE {$metricBase} AND {$metricNeedsReview}"),
    'featured' => (int) admin_scalar($pdo, "SELECT COUNT(*) FROM products WHERE {$metricBase} AND is_featured = 1"),
    'discounted' => (int) admin_scalar($pdo, "SELECT COUNT(*) FROM products WHERE {$metricBase} AND compare_price IS NOT NULL AND compare_price > price"),
    'stock_mismatch' => (int) admin_scalar($pdo, "SELECT COUNT(*) FROM products WHERE {$metricBase} AND ((product_status = 'active' AND stock <= 0) OR (product_status = 'out_of_stock' AND stock > 0))"),
    'no_sales' => $hasSalesTables ? (int) admin_scalar($pdo, "SELECT COUNT(*) FROM products p WHERE p.is_active = 1 AND p.product_status IN ('active','out_of_stock') AND NOT EXISTS (SELECT 1 FROM order_items oi WHERE oi.product_id = p.id)") : 0,
    'stale' => (int) admin_scalar($pdo, "SELECT COUNT(*) FROM products WHERE {$metricBase} AND updated_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"),
];
$catalogMetrics['ready_percent'] = $catalogMetrics['published'] > 0 ? (int) round((($catalogMetrics['published'] - $catalogMetrics['needs_review']) / max(1, $catalogMetrics['published'])) * 100) : 0;
$activeFilterCount = 0;
foreach ([$q, $brand, $type, $stockState, $mediaState, $dealState] as $filterValue) {
    if ((string) $filterValue !== '') { $activeFilterCount++; }
}
if ($categoryId > 0) { $activeFilterCount++; }

$queryForView = function (string $nextView) use ($q, $categoryId, $brand, $type, $stockState, $mediaState, $dealState, $sort): array {
    $query = ['view' => $nextView];
    if ($q !== '') { $query['q'] = $q; }
    if ($categoryId > 0) { $query['category_id'] = $categoryId; }
    if ($brand !== '') { $query['brand'] = $brand; }
    if ($type !== '') { $query['product_type'] = $type; }
    if ($stockState !== '') { $query['stock_state'] = $stockState; }
    if ($mediaState !== '') { $query['media_state'] = $mediaState; }
    if ($dealState !== '') { $query['deal_state'] = $dealState; }
    if ($sort !== '') { $query['sort'] = $sort; }
    return $query;
};

$exportQuery = $queryForView($view);
$exportQuery['type'] = 'published_products';
$exportQuery['_csrf'] = csrf_token();
$exportCurrentUrl = admin_root_url('admin/export.php', $exportQuery);
$exportBaseUrl = admin_root_url('admin/export.php', ['type' => 'published_products', '_csrf' => csrf_token()]);
$healthCards = [
    ['label' => 'Ready ratio', 'value' => $catalogMetrics['ready_percent'] . '%', 'note' => $catalogMetrics['needs_review'] . ' need review', 'icon' => 'verified', 'class' => $catalogMetrics['ready_percent'] >= 80 ? 'good' : 'warning', 'view' => 'needs_review'],
    ['label' => 'Stock mismatches', 'value' => (string) $catalogMetrics['stock_mismatch'], 'note' => 'Status does not match stock', 'icon' => 'sync_problem', 'class' => $catalogMetrics['stock_mismatch'] > 0 ? 'danger' : 'good', 'view' => 'stock_mismatch'],
    ['label' => 'No sales yet', 'value' => (string) $catalogMetrics['no_sales'], 'note' => $hasSalesTables ? 'Published but no orders' : 'Sales tables unavailable', 'icon' => 'trending_flat', 'class' => $catalogMetrics['no_sales'] > 0 ? 'neutral' : 'good', 'view' => 'no_sales'],
    ['label' => 'Stale products', 'value' => (string) $catalogMetrics['stale'], 'note' => 'No update for 30+ days', 'icon' => 'history', 'class' => $catalogMetrics['stale'] > 0 ? 'warning' : 'good', 'view' => 'stale'],
];

$queueNoSalesCondition = $hasSalesTables ? ' OR COALESCE(s.orders_count,0) = 0' : '';
$actionQueueSql = "SELECT p.*, c.name AS category_name, {$salesSelect}
FROM products p
LEFT JOIN categories c ON c.id = p.category_id
{$salesJoin}
WHERE {$publishedBase}
  AND ({$needsReviewSql} OR (p.stock BETWEEN 1 AND 5) OR p.updated_at < DATE_SUB(NOW(), INTERVAL 30 DAY){$queueNoSalesCondition})
ORDER BY
  CASE
    WHEN (p.image IS NULL OR p.image = '') THEN 1
    WHEN p.price <= 0 THEN 2
    WHEN ((p.product_status = 'active' AND p.stock <= 0) OR (p.product_status = 'out_of_stock' AND p.stock > 0)) THEN 3
    WHEN p.short_description IS NULL OR p.short_description = '' THEN 4
    WHEN p.category_id IS NULL THEN 5
    WHEN p.stock BETWEEN 1 AND 5 THEN 6
    ELSE 9
  END ASC,
  p.updated_at ASC,
  p.id DESC
LIMIT 4";
$actionQueueProducts = admin_rows($pdo, $actionQueueSql);

$views = [
    'published' => ['label' => 'Published', 'count' => $catalogMetrics['published'], 'icon' => 'storefront'],
    'available' => ['label' => 'Available', 'count' => $catalogMetrics['available'], 'icon' => 'shopping_cart_checkout'],
    'low_stock' => ['label' => 'Low stock', 'count' => $catalogMetrics['low'], 'icon' => 'warning'],
    'out_of_stock' => ['label' => 'Out of stock', 'count' => $catalogMetrics['out'], 'icon' => 'remove_shopping_cart'],
    'needs_review' => ['label' => 'Needs review', 'count' => $catalogMetrics['needs_review'], 'icon' => 'fact_check'],
    'missing_image' => ['label' => 'Missing image', 'count' => $catalogMetrics['missing_image'], 'icon' => 'image_not_supported'],
    'featured' => ['label' => 'Featured', 'count' => $catalogMetrics['featured'], 'icon' => 'stars'],
    'discounted' => ['label' => 'Discounted', 'count' => $catalogMetrics['discounted'], 'icon' => 'sell'],
    'stock_mismatch' => ['label' => 'Stock mismatch', 'count' => $catalogMetrics['stock_mismatch'], 'icon' => 'sync_problem'],
    'no_sales' => ['label' => 'No sales', 'count' => $catalogMetrics['no_sales'], 'icon' => 'trending_flat'],
    'stale' => ['label' => 'Stale', 'count' => $catalogMetrics['stale'], 'icon' => 'history'],
];
$coreViewKeys = ['published', 'available', 'low_stock', 'out_of_stock', 'needs_review'];
$secondaryViewKeys = ['missing_image', 'featured', 'discounted', 'stock_mismatch', 'no_sales', 'stale'];

admin_header('Published Products', 'Manage the products currently visible on the storefront.', 'products');
?>
<section class="published-products-center" data-published-products data-api-url="<?= e(admin_root_url('admin/api/products.php')) ?>" data-csrf="<?= e(csrf_token()) ?>">
    <section class="published-products-hero glass-panel">
        <div>
            <p class="admin-eyebrow">Published Products</p>
            <h2>Storefront products</h2>
            <p>Keep the visible catalog clean: stock, price, image, and readiness.</p>
        </div>
        <div class="published-products-hero-actions">
            <a class="admin-primary-btn" href="<?= e(admin_page_url('product_edit')) ?>"><span class="material-symbols-outlined">add</span> New product</a>
            <details class="admin-more-menu published-toolbar-menu">
                <summary><span class="material-symbols-outlined">more_horiz</span><span>More</span></summary>
                <div class="admin-more-panel">
                    <a href="<?= e(admin_page_url('inventory')) ?>"><span class="material-symbols-outlined">inventory_2</span> Inventory</a>
                    <a href="<?= e($exportCurrentUrl) ?>" download><span class="material-symbols-outlined">download</span> Export current view</a>
                    <button type="button" data-published-refresh><span class="material-symbols-outlined">refresh</span> Refresh data</button>
                </div>
            </details>
        </div>
    </section>

    <section class="published-status-strip glass-panel" aria-label="Published product summary">
        <a href="<?= e(admin_page_url('products', $queryForView('published'))) ?>" class="published-status-chip <?= $view === 'published' ? 'is-active' : '' ?>"><span>Published</span><strong><?= (int) $catalogMetrics['published'] ?></strong></a>
        <a href="<?= e(admin_page_url('products', $queryForView('available'))) ?>" class="published-status-chip <?= $view === 'available' ? 'is-active' : '' ?>"><span>Available</span><strong><?= (int) $catalogMetrics['available'] ?></strong></a>
        <a href="<?= e(admin_page_url('products', $queryForView('low_stock'))) ?>" class="published-status-chip <?= $view === 'low_stock' ? 'is-active' : '' ?>"><span>Low stock</span><strong><?= (int) $catalogMetrics['low'] ?></strong></a>
        <a href="<?= e(admin_page_url('products', $queryForView('needs_review'))) ?>" class="published-status-chip <?= $view === 'needs_review' ? 'is-active' : '' ?>"><span>Needs review</span><strong><?= (int) $catalogMetrics['needs_review'] ?></strong></a>
    </section>

    <details class="published-action-queue glass-panel" aria-label="Published product action queue">
        <summary class="published-collapsed-head">
            <span class="material-symbols-outlined">priority_high</span>
            <div><strong>Fix first</strong><small><?= count($actionQueueProducts) ?> highest-impact product fixes</small></div>
            <em>Open</em>
        </summary>
        <?php if (!$actionQueueProducts): ?>
            <p class="published-muted-note">No urgent published-product action is currently detected.</p>
        <?php else: ?>
            <div class="published-action-list">
                <?php foreach ($actionQueueProducts as $queueProduct): ?>
                    <?php
                    $queueReadiness = admin_product_readiness($queueProduct);
                    $queuePlan = admin_product_action_plan($queueProduct, $queueReadiness);
                    $queueImageUrl = trim((string) ($queueProduct['image'] ?? '')) !== '' ? admin_media_public_url((string) $queueProduct['image']) : '';
                    ?>
                    <article class="published-action-card <?= e($queuePlan['class']) ?>">
                        <div class="published-action-thumb">
                            <?php if ($queueImageUrl !== ''): ?><img src="<?= e($queueImageUrl) ?>" alt="<?= e($queueProduct['name']) ?>"><?php else: ?><span class="material-symbols-outlined">image_not_supported</span><?php endif; ?>
                        </div>
                        <div>
                            <strong><?= e($queueProduct['name']) ?></strong>
                            <small><?= e($queuePlan['title']) ?> · <?= e($queuePlan['priority']) ?> priority</small>
                        </div>
                        <a class="admin-table-btn" href="<?= e(admin_page_url('product_edit', ['id' => (int) $queueProduct['id']])) ?>"><?= e($queuePlan['action']) ?></a>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </details>

    <nav class="published-products-tabs glass-panel" aria-label="Published product filters">
        <?php foreach ($coreViewKeys as $key): ?>
            <?php $item = $views[$key]; ?>
            <a class="<?= $view === $key ? 'is-active' : '' ?>" href="<?= e(admin_page_url('products', $queryForView($key))) ?>">
                <span class="material-symbols-outlined"><?= e($item['icon']) ?></span>
                <strong><?= e($item['label']) ?></strong>
                <em><?= (int) $item['count'] ?></em>
            </a>
        <?php endforeach; ?>
        <details class="published-more-views">
            <summary><span class="material-symbols-outlined">tune</span><strong>More views</strong></summary>
            <div>
                <?php foreach ($secondaryViewKeys as $key): ?>
                    <?php $item = $views[$key]; ?>
                    <a class="<?= $view === $key ? 'is-active' : '' ?>" href="<?= e(admin_page_url('products', $queryForView($key))) ?>">
                        <span class="material-symbols-outlined"><?= e($item['icon']) ?></span>
                        <strong><?= e($item['label']) ?></strong>
                        <em><?= (int) $item['count'] ?></em>
                    </a>
                <?php endforeach; ?>
            </div>
        </details>
    </nav>

    <section class="published-products-filters glass-panel">
        <form method="get" action="<?= e(admin_page_url('products')) ?>" class="published-filter-grid" data-published-filter-form>
            <input type="hidden" name="section" value="products">
            <input type="hidden" name="view" value="<?= e($view) ?>">
            <label class="admin-field published-search-field"><span>Search</span><input name="q" value="<?= e($q) ?>" placeholder="Name, SKU, slug, brand..."></label>
            <label class="admin-field"><span>Category</span><select name="category_id"><option value="0">All categories</option><?php foreach ($categories as $cat): ?><option value="<?= (int) $cat['id'] ?>" <?= $categoryId === (int) $cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option><?php endforeach; ?></select></label>
            <label class="admin-field"><span>Stock</span><select name="stock_state"><option value="">Any stock</option><option value="in_stock" <?= $stockState === 'in_stock' ? 'selected' : '' ?>>Healthy stock</option><option value="low" <?= $stockState === 'low' ? 'selected' : '' ?>>Low stock</option><option value="zero" <?= $stockState === 'zero' ? 'selected' : '' ?>>Zero stock</option></select></label>
            <label class="admin-field"><span>Sort by</span><select name="sort"><option value="updated_desc" <?= $sort === 'updated_desc' ? 'selected' : '' ?>>Last updated</option><option value="readiness_asc" <?= $sort === 'readiness_asc' ? 'selected' : '' ?>>Needs review first</option><option value="sales_desc" <?= $sort === 'sales_desc' ? 'selected' : '' ?>>Best selling</option><option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>Name A-Z</option><option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price high-low</option><option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price low-high</option><option value="stock_asc" <?= $sort === 'stock_asc' ? 'selected' : '' ?>>Lowest stock</option><option value="stock_desc" <?= $sort === 'stock_desc' ? 'selected' : '' ?>>Highest stock</option></select></label>
            <div class="published-filter-actions">
                <button class="admin-primary-btn" type="submit">Apply</button>
                <a class="admin-table-btn" href="<?= e(admin_page_url('products')) ?>">Reset</a>
            </div>
            <details class="published-advanced-filters published-filter-popover">
                <summary><span class="material-symbols-outlined">tune</span> Filters <em><?= (int) $activeFilterCount ?></em></summary>
                <div>
                    <label class="admin-field"><span>Brand</span><select name="brand"><option value="">All brands</option><?php foreach ($brands as $b): ?><option value="<?= e($b['brand']) ?>" <?= $brand === $b['brand'] ? 'selected' : '' ?>><?= e($b['brand']) ?></option><?php endforeach; ?></select></label>
                    <label class="admin-field"><span>Product type</span><select name="product_type"><option value="">All types</option><?php foreach ($productTypes as $value => $label): ?><option value="<?= e($value) ?>" <?= $type === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
                    <label class="admin-field"><span>Media</span><select name="media_state"><option value="">Any media</option><option value="has_image" <?= $mediaState === 'has_image' ? 'selected' : '' ?>>Has main image</option><option value="missing_image" <?= $mediaState === 'missing_image' ? 'selected' : '' ?>>Missing image</option></select></label>
                    <label class="admin-field"><span>Deal</span><select name="deal_state"><option value="">Any price</option><option value="discounted" <?= $dealState === 'discounted' ? 'selected' : '' ?>>Discounted</option><option value="regular" <?= $dealState === 'regular' ? 'selected' : '' ?>>Regular price</option></select></label>
                </div>
            </details>
        </form>
    </section>

    <section class="published-products-table-card glass-panel">
        <div class="admin-section-head published-table-head">
            <div><p class="admin-eyebrow">Published products table</p><h2><?= count($products) ?> products shown</h2><small>Limited to 120 results to keep the operations page fast.</small></div>
            <div class="published-table-tools">
                <button class="admin-table-btn published-density-btn" type="button" data-published-density-toggle><span class="material-symbols-outlined">density_medium</span> Compact</button>
                <details class="admin-more-menu published-toolbar-menu">
                    <summary><span class="material-symbols-outlined">more_horiz</span><span>More</span></summary>
                    <div class="admin-more-panel">
                        <a href="<?= e($exportCurrentUrl) ?>" download><span class="material-symbols-outlined">download</span> Export filtered CSV</a>
                    </div>
                </details>
            </div>
        </div>

        <?php if (!$products): ?>
            <?php admin_empty_state('No published products found', 'Adjust filters, publish a product, or create a new product from the editor.'); ?>
        <?php else: ?>
            <form method="post" id="publishedBulkForm" class="published-products-selectionbar" data-published-bulk-form hidden>
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="admin_action" value="product_bulk_action">
                <div class="published-selection-count"><strong data-published-selected-count>0 selected</strong><small>Bulk actions only apply to selected rows.</small></div>
                <div class="published-selection-actions">
                    <button class="admin-table-btn" type="submit" name="bulk_action" value="mark_active">Mark active</button>
                    <button class="admin-table-btn" type="button" data-published-clear-selection>Clear</button>
                    <details class="admin-more-menu admin-more-menu-wide">
                        <summary><span>More</span><span class="material-symbols-outlined">expand_more</span></summary>
                        <div class="admin-more-panel">
                            <button type="button" data-published-export-selected data-export-base="<?= e($exportBaseUrl) ?>">Export selected</button>
                            <button type="submit" name="bulk_action" value="feature">Feature</button>
                            <button type="submit" name="bulk_action" value="unfeature">Unfeature</button>
                            <button type="submit" name="bulk_action" value="sync_stock_status">Fix stock/status</button>
                            <button type="submit" name="bulk_action" value="mark_draft">Move to draft</button>
                            <button type="submit" name="bulk_action" value="clear_discount">Clear discount</button>
                            <label class="published-inline-input published-inline-stock"><input name="bulk_stock" type="number" min="0" placeholder="Set stock"><button type="submit" name="bulk_action" value="set_stock">Apply stock</button></label>
                            <label class="published-inline-select"><select name="bulk_category_id"><option value="0">Set category...</option><?php foreach ($categories as $cat): ?><option value="<?= (int) $cat['id'] ?>"><?= e($cat['name']) ?></option><?php endforeach; ?></select><button type="submit" name="bulk_action" value="set_category">Apply category</button></label>
                            <label class="published-inline-input published-inline-discount"><input name="bulk_discount_percent" type="number" min="1" max="95" placeholder="Discount %"><button type="submit" name="bulk_action" value="set_discount">Set discount</button></label>
                            <label class="published-inline-input"><input name="bulk_brand" placeholder="Set brand"><button type="submit" name="bulk_action" value="set_brand">Apply brand</button></label>
                            <button class="danger" type="submit" name="bulk_action" value="archive">Archive selected</button>
                        </div>
                    </details>
                </div>
            </form>

            <div class="admin-table-wrap published-products-table-wrap">
                <table class="admin-table published-products-table">
                    <thead>
                        <tr>
                            <th><input type="checkbox" data-published-select-all aria-label="Select all published products"></th>
                            <th>Product</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Readiness</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($products as $product): ?>
                        <?php
                        $rowStatus = admin_product_status_value($product['product_status'] ?? null, (int) $product['is_active'], (int) $product['stock']);
                        $readiness = admin_product_readiness($product);
                        $issuesJson = json_encode($readiness['issues'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]';
                        $checklistJson = json_encode($readiness['items'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
                        $imagePath = (string) ($product['image'] ?? '');
                        $imageUrl = $imagePath !== '' ? admin_media_public_url($imagePath) : '';
                        $productUrl = admin_root_url('product.php', ['slug' => (string) $product['slug']]);
                        $ordersCount = (int) ($product['orders_count'] ?? 0);
                        $qtySold = (int) ($product['qty_sold'] ?? 0);
                        $revenue = (float) ($product['revenue'] ?? 0);
                        [$performanceLabel, $performanceClass] = admin_product_performance_label($ordersCount, $qtySold, (int) $product['stock']);
                        $actionPlan = admin_product_action_plan($product, $readiness);
                        $discountPercent = ((float) ($product['compare_price'] ?? 0) > (float) $product['price'] && (float) ($product['compare_price'] ?? 0) > 0) ? (int) round((((float)$product['compare_price'] - (float)$product['price']) / (float)$product['compare_price']) * 100) : 0;
                        ?>
                        <tr
                            data-published-product-row
                            data-product-id="<?= (int) $product['id'] ?>"
                            data-product-name="<?= e($product['name']) ?>"
                            data-product-sku="<?= e($product['sku'] ?: $product['slug']) ?>"
                            data-product-slug="<?= e($product['slug']) ?>"
                            data-product-image-url="<?= e($imageUrl) ?>"
                            data-product-image-path="<?= e($imagePath) ?>"
                            data-product-category="<?= e($product['category_name'] ?: 'No category') ?>"
                            data-product-brand="<?= e($product['brand'] ?: 'No brand') ?>"
                            data-product-type="<?= e(admin_product_type_label($product['product_type'] ?? 'general')) ?>"
                            data-product-price="<?= e((string) $product['price']) ?>"
                            data-product-compare="<?= e((string) ($product['compare_price'] ?? '')) ?>"
                            data-product-stock="<?= (int) $product['stock'] ?>"
                            data-product-status="<?= e($rowStatus) ?>"
                            data-product-status-label="<?= e(admin_product_status_label($rowStatus)) ?>"
                            data-product-featured="<?= (int) $product['is_featured'] ?>"
                            data-product-readiness="<?= (int) $readiness['count'] ?>/<?= (int) $readiness['total'] ?>"
                            data-product-ready-state="<?= e($readiness['state']) ?>"
                            data-product-issues="<?= e($issuesJson) ?>"
                            data-product-checklist="<?= e($checklistJson) ?>"
                            data-product-action-title="<?= e($actionPlan['title']) ?>"
                            data-product-action-detail="<?= e($actionPlan['detail']) ?>"
                            data-product-action-label="<?= e($actionPlan['action']) ?>"
                            data-product-action-class="<?= e($actionPlan['class']) ?>"
                            data-product-action-priority="<?= e($actionPlan['priority']) ?>"
                            data-product-url="<?= e($productUrl) ?>"
                            data-product-orders="<?= $ordersCount ?>"
                            data-product-qty="<?= $qtySold ?>"
                            data-product-revenue="<?= e(admin_money($revenue, $currency)) ?>"
                            data-product-last-order="<?= e((string) ($product['last_order_at'] ?? '')) ?>"
                            data-product-short="<?= e(mb_substr((string) ($product['short_description'] ?? ''), 0, 240)) ?>"
                        >
                            <td data-label="Select"><input type="checkbox" name="product_ids[]" value="<?= (int) $product['id'] ?>" form="publishedBulkForm" data-published-row-check aria-label="Select <?= e($product['name']) ?>"></td>
                            <td data-label="Product">
                                <div class="published-product-cell">
                                    <?php if ($imageUrl !== ''): ?><img src="<?= e($imageUrl) ?>" alt="<?= e($product['name']) ?>"><?php else: ?><span class="published-product-no-image material-symbols-outlined">image_not_supported</span><?php endif; ?>
                                    <div>
                                        <strong><?= e($product['name']) ?></strong>
                                        <small><?= e($product['sku'] ?: $product['slug']) ?></small>
                                        <span><?= e($product['category_name'] ?: 'No category') ?> · <?= e($product['brand'] ?: admin_product_type_label($product['product_type'] ?? 'general')) ?></span>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Price"><strong><?= e(admin_money($product['price'], $currency)) ?></strong><?php if (!empty($product['compare_price']) && (float)$product['compare_price'] > (float)$product['price']): ?><small><del><?= e(admin_money($product['compare_price'], $currency)) ?></del> · <?= $discountPercent ?>% off</small><?php else: ?><small>Regular price</small><?php endif; ?></td>
                            <td data-label="Stock"><span class="published-stock <?= (int)$product['stock'] <= 0 ? 'is-zero' : ((int)$product['stock'] <= 5 ? 'is-low' : 'is-good') ?>"><?= (int) $product['stock'] ?></span><small><?= (int)$product['stock'] <= 0 ? 'Cannot buy' : ((int)$product['stock'] <= 5 ? 'Low stock' : 'Healthy') ?></small></td>
                            <td data-label="Readiness"><span class="admin-pill <?= e($readiness['class']) ?>"><?= (int) $readiness['count'] ?>/<?= (int) $readiness['total'] ?> <?= e($readiness['state']) ?></span><?php if ($readiness['issues']): ?><small><?= e($readiness['issues'][0]['label']) ?><?= count($readiness['issues']) > 1 ? ' +' . (count($readiness['issues']) - 1) : '' ?></small><?php else: ?><small>Ready for customers</small><?php endif; ?></td>
                            <td data-label="Status"><span class="admin-pill <?= e(admin_product_status_class($rowStatus)) ?>"><?= e(admin_product_status_label($rowStatus)) ?></span><?php if ((int)$product['is_featured'] === 1): ?><small class="published-featured-note"><span class="material-symbols-outlined">stars</span> Featured</small><?php endif; ?></td>
                            <td data-label="Actions">
                                <div class="published-row-actions">
                                    <button class="admin-table-btn published-manage-btn" type="button" data-product-preview-open><span class="material-symbols-outlined">tune</span> Manage</button>
                                    <a class="admin-table-btn published-icon-btn" href="<?= e(admin_page_url('product_edit', ['id' => (int)$product['id']])) ?>" aria-label="Edit full product"><span class="material-symbols-outlined">edit</span></a>
                                    <details class="published-row-menu">
                                        <summary aria-label="More actions"><span class="material-symbols-outlined">more_horiz</span></summary>
                                        <div>
                                            <a href="<?= e($productUrl) ?>" target="_blank" rel="noopener"><span class="material-symbols-outlined">open_in_new</span> View storefront</a>
                                            <button type="button" data-published-copy-link><span class="material-symbols-outlined">content_copy</span> Copy link</button>
                                            <button type="button" data-published-product-action="toggle_featured"><span class="material-symbols-outlined">stars</span> <?= (int)$product['is_featured'] === 1 ? 'Remove featured' : 'Mark featured' ?></button>
                                            <button type="button" data-published-product-action="sync_status"><span class="material-symbols-outlined">sync_problem</span> Fix stock/status</button>
                                            <button type="button" data-published-product-action="draft"><span class="material-symbols-outlined">draft</span> Move to draft</button>
                                            <button type="button" data-published-product-action="duplicate"><span class="material-symbols-outlined">content_copy</span> Duplicate as draft</button>
                                            <button type="button" class="danger" data-published-product-action="archive"><span class="material-symbols-outlined">archive</span> Archive</button>
                                        </div>
                                    </details>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <aside class="published-product-drawer" data-product-drawer hidden aria-hidden="true">
        <div class="published-product-drawer-panel glass-panel" role="dialog" aria-modal="true" aria-label="Published product preview">
            <button type="button" class="published-drawer-close" data-product-drawer-close aria-label="Close product preview"><span class="material-symbols-outlined">close</span></button>
            <div class="published-drawer-media"><img src="" alt="" data-drawer-image hidden><span class="material-symbols-outlined" data-drawer-image-empty>image_not_supported</span></div>
            <div class="published-drawer-body">
                <p class="admin-eyebrow">Storefront preview</p>
                <h3 data-drawer-name>Product</h3>
                <p data-drawer-short>No short description.</p>
                <div class="published-drawer-badges"><span class="admin-pill neutral" data-drawer-status>Status</span><span class="admin-pill neutral" data-drawer-readiness>Readiness</span><span class="admin-pill info" data-drawer-featured hidden>Featured</span></div>
                <section class="published-drawer-action-plan" data-drawer-action-plan>
                    <strong data-drawer-action-title>Recommended action</strong>
                    <p data-drawer-action-detail>Open this product to review its storefront readiness.</p>
                    <small data-drawer-action-label>Review later</small>
                </section>
                <dl class="published-drawer-facts">
                    <div><dt>SKU / slug</dt><dd data-drawer-sku>—</dd></div>
                    <div><dt>Category</dt><dd data-drawer-category>—</dd></div>
                    <div><dt>Brand / type</dt><dd data-drawer-brand>—</dd></div>
                    <div><dt>Sales</dt><dd data-drawer-sales>—</dd></div>
                </dl>
                <section class="published-drawer-issues"><strong>Readiness issues</strong><ul data-drawer-issues></ul></section>
                <details class="published-drawer-details">
                    <summary>Full readiness checklist</summary>
                    <section class="published-drawer-checklist"><ul data-drawer-checklist></ul></section>
                </details>
                <details class="published-drawer-quick-edit" open>
                    <summary><span class="material-symbols-outlined">bolt</span> Quick edit</summary>
                    <div class="published-drawer-quick-grid">
                        <form class="published-drawer-form" data-drawer-stock-form>
                            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="admin_action" value="product_quick_stock">
                            <input type="hidden" name="product_id" value="">
                            <label class="admin-field"><span>Stock</span><input type="number" min="0" name="new_stock" value="0" data-drawer-stock-input></label>
                            <p class="published-change-preview" data-drawer-stock-preview>Stock change preview will appear here.</p>
                            <button class="admin-primary-btn" type="submit"><span class="material-symbols-outlined">save</span> Save stock</button>
                        </form>
                        <form class="published-drawer-form" data-drawer-price-form>
                            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="admin_action" value="product_quick_price">
                            <input type="hidden" name="product_id" value="">
                            <label class="admin-field"><span>Price</span><input type="number" min="0" step="0.01" name="price" value="0" data-drawer-price-input></label>
                            <label class="admin-field"><span>Discount %</span><input type="number" min="0" max="95" step="0.01" name="discount_percent" value="" data-drawer-discount-input></label>
                            <p class="published-change-preview" data-drawer-price-preview>Price change preview will appear here.</p>
                            <button class="admin-primary-btn" type="submit"><span class="material-symbols-outlined">save</span> Save price</button>
                        </form>
                    </div>
                </details>
                <div class="published-drawer-actions">
                    <a class="admin-primary-btn" data-drawer-edit href="#"><span class="material-symbols-outlined">edit</span> Edit full product</a>
                    <a class="admin-ghost-btn" data-drawer-view href="#" target="_blank" rel="noopener"><span class="material-symbols-outlined">open_in_new</span> View</a>
                    <details class="admin-more-menu">
                        <summary><span>More</span><span class="material-symbols-outlined">expand_more</span></summary>
                        <div class="admin-more-panel">
                            <button type="button" data-drawer-copy-link>Copy link</button>
                            <button type="button" data-drawer-action="toggle_featured">Toggle featured</button>
                            <button type="button" data-drawer-action="sync_status">Fix stock/status</button>
                            <button type="button" data-drawer-action="draft">Move to draft</button>
                            <button type="button" class="danger" data-drawer-action="archive">Archive</button>
                        </div>
                    </details>
                </div>
            </div>
        </div>
    </aside>
</section>
<?php admin_footer(); ?>
