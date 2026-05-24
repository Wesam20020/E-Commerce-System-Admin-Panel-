<?php
require_once __DIR__ . '/includes/layout.php';

$ctx = admin_boot('exports');
$pdo = $ctx['pdo'];
verify_csrf_or_fail($_GET['_csrf'] ?? null);

$type = preg_replace('/[^a-z0-9_]+/i', '', (string) ($_GET['type'] ?? 'orders')) ?: 'orders';

if ($type === 'published_products') {
    $productTypes = admin_product_type_options();
    $allowedViews = ['published', 'available', 'out_of_stock', 'low_stock', 'missing_image', 'needs_review', 'featured', 'discounted', 'stock_mismatch', 'no_sales', 'stale'];
    $view = preg_replace('/[^a-z_]+/', '', strtolower((string) ($_GET['view'] ?? 'published'))) ?: 'published';
    if (!in_array($view, $allowedViews, true)) {
        $view = 'published';
    }

    $q = trim((string) ($_GET['q'] ?? ''));
    $categoryId = (int) ($_GET['category_id'] ?? 0);
    $brand = trim((string) ($_GET['brand'] ?? ''));
    $productType = preg_replace('/[^a-z0-9_]+/', '', strtolower((string) ($_GET['product_type'] ?? ''))) ?: '';
    $stockState = preg_replace('/[^a-z_]+/', '', strtolower((string) ($_GET['stock_state'] ?? ''))) ?: '';
    $mediaState = preg_replace('/[^a-z_]+/', '', strtolower((string) ($_GET['media_state'] ?? ''))) ?: '';
    $dealState = preg_replace('/[^a-z_]+/', '', strtolower((string) ($_GET['deal_state'] ?? ''))) ?: '';
    $sort = preg_replace('/[^a-z_]+/', '', strtolower((string) ($_GET['sort'] ?? 'updated_desc'))) ?: 'updated_desc';

    $where = ["p.is_active = 1 AND p.product_status IN ('active','out_of_stock')"];
    $params = [];
    $needsReviewSql = admin_product_readiness_sql_condition('p');
    $hasSalesTables = admin_table_exists($pdo, 'orders') && admin_table_exists($pdo, 'order_items');

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
    if ($productType !== '' && array_key_exists($productType, $productTypes)) {
        $where[] = 'p.product_type = :product_type';
        $params['product_type'] = $productType;
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

    $ids = [];
    foreach (explode(',', (string) ($_GET['ids'] ?? '')) as $rawId) {
        $id = (int) $rawId;
        if ($id > 0) {
            $ids[$id] = $id;
        }
    }
    if ($ids) {
        [$idPlaceholders, $idParams] = admin_sql_placeholders(array_values($ids), 'export_product');
        $where[] = 'p.id IN (' . implode(',', $idPlaceholders) . ')';
        $params += $idParams;
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

    $sql = "SELECT p.*, c.name AS category, {$salesSelect}
FROM products p
LEFT JOIN categories c ON c.id = p.category_id
{$salesJoin}
WHERE " . implode(' AND ', $where) . "
ORDER BY {$orderBy}";

    $headers = ['id', 'sku', 'name', 'slug', 'category', 'brand', 'product_type', 'price', 'compare_price', 'discount_percent', 'stock', 'storefront_status', 'readiness_score', 'readiness_state', 'readiness_issues', 'orders_count', 'qty_sold', 'revenue', 'last_order_at', 'image', 'updated_at'];
    $filename = 'phonix-published-products-' . date('Ymd-His') . '.csv';

    if (!headers_sent()) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    }

    $out = fopen('php://output', 'wb');
    if ($out === false) {
        exit;
    }
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $headers);

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $readiness = admin_product_readiness($row);
        $issueLabels = array_map(static fn ($issue) => (string) ($issue['label'] ?? ''), $readiness['issues'] ?? []);
        $comparePrice = (float) ($row['compare_price'] ?? 0);
        $price = (float) ($row['price'] ?? 0);
        $discountPercent = ($comparePrice > $price && $comparePrice > 0) ? (int) round((($comparePrice - $price) / $comparePrice) * 100) : 0;
        $status = admin_product_status_value($row['product_status'] ?? null, (int) ($row['is_active'] ?? 0), (int) ($row['stock'] ?? 0));

        fputcsv($out, [
            $row['id'] ?? '',
            $row['sku'] ?? '',
            $row['name'] ?? '',
            $row['slug'] ?? '',
            $row['category'] ?? '',
            $row['brand'] ?? '',
            $row['product_type'] ?? '',
            $row['price'] ?? '',
            $row['compare_price'] ?? '',
            $discountPercent,
            $row['stock'] ?? '',
            $status,
            ($readiness['count'] ?? 0) . '/' . ($readiness['total'] ?? 0),
            $readiness['state'] ?? '',
            implode(' | ', array_filter($issueLabels)),
            $row['orders_count'] ?? 0,
            $row['qty_sold'] ?? 0,
            $row['revenue'] ?? 0,
            $row['last_order_at'] ?? '',
            $row['image'] ?? '',
            $row['updated_at'] ?? '',
        ]);
    }
    fclose($out);
    exit;
}

$exports = [
    'orders' => [
        'filename' => 'phonix-orders',
        'headers' => ['order_number', 'created_at', 'customer_name', 'email', 'phone', 'city', 'country', 'status', 'payment_status', 'shipping_method', 'payment_method', 'coupon_code', 'subtotal', 'shipping_total', 'discount_total', 'tax_total', 'total', 'tracking_carrier', 'tracking_number'],
        'sql' => 'SELECT order_number, created_at, full_name AS customer_name, email, phone, city, country, status, payment_status, shipping_method_name AS shipping_method, payment_method_name AS payment_method, coupon_code, subtotal, shipping_total, discount_total, tax_total, total, tracking_carrier, tracking_number FROM orders ORDER BY created_at DESC, id DESC',
    ],
    'products' => [
        'filename' => 'phonix-products',
        'headers' => ['id', 'sku', 'name', 'slug', 'category', 'brand', 'price', 'compare_price', 'stock', 'product_status', 'is_active', 'is_featured', 'image', 'updated_at'],
        'sql' => 'SELECT p.id, p.sku, p.name, p.slug, c.name AS category, p.brand, p.price, p.compare_price, p.stock, p.product_status, p.is_active, p.is_featured, p.image, p.updated_at FROM products p LEFT JOIN categories c ON c.id = p.category_id ORDER BY p.updated_at DESC, p.id DESC',
    ],
    'published_products_snapshot' => [
        'filename' => 'phonix-published-products-snapshot',
        'headers' => ['id', 'sku', 'name', 'slug', 'category', 'brand', 'product_type', 'price', 'compare_price', 'stock', 'product_status', 'is_active', 'is_featured', 'image', 'updated_at'],
        'sql' => "SELECT p.id, p.sku, p.name, p.slug, c.name AS category, p.brand, p.product_type, p.price, p.compare_price, p.stock, p.product_status, p.is_active, p.is_featured, p.image, p.updated_at FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.is_active = 1 AND p.product_status IN ('active','out_of_stock') ORDER BY p.updated_at DESC, p.id DESC",
    ],
    'customers' => [
        'filename' => 'phonix-customers',
        'headers' => ['id', 'name', 'email', 'phone', 'city', 'country', 'created_at', 'orders_count', 'total_spent'],
        'sql' => 'SELECT u.id, u.name, u.email, u.phone, u.city, u.country, u.created_at, COUNT(o.id) AS orders_count, COALESCE(SUM(CASE WHEN o.payment_status = \'paid\' THEN o.total ELSE 0 END), 0) AS total_spent FROM users u LEFT JOIN orders o ON o.user_id = u.id GROUP BY u.id, u.name, u.email, u.phone, u.city, u.country, u.created_at ORDER BY u.created_at DESC, u.id DESC',
    ],
    'support' => [
        'filename' => 'phonix-support-messages',
        'headers' => ['id', 'created_at', 'status', 'name', 'email', 'phone', 'order_number', 'subject', 'message', 'source_page', 'admin_note'],
        'sql' => 'SELECT id, created_at, status, name, email, phone, order_number, subject, message, source_page, admin_note FROM support_messages ORDER BY created_at DESC, id DESC',
    ],
    'low_stock' => [
        'filename' => 'phonix-low-stock',
        'headers' => ['id', 'sku', 'name', 'category', 'brand', 'stock', 'product_status', 'price', 'updated_at'],
        'sql' => "SELECT p.id, p.sku, p.name, c.name AS category, p.brand, p.stock, p.product_status, p.price, p.updated_at FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.product_status <> 'archived' AND p.stock <= 5 ORDER BY p.stock ASC, p.updated_at DESC",
    ],
    'coupons' => [
        'filename' => 'phonix-coupons',
        'headers' => ['code', 'description', 'discount_type', 'discount_value', 'min_order_total', 'starts_at', 'ends_at', 'max_uses', 'used_count', 'is_active', 'updated_at'],
        'sql' => 'SELECT code, description, discount_type, discount_value, min_order_total, starts_at, ends_at, max_uses, used_count, is_active, updated_at FROM coupons ORDER BY updated_at DESC, id DESC',
    ],
];

if (!isset($exports[$type])) {
    admin_json_response(['ok' => false, 'message' => 'Unknown export type.'], 404);
}

$export = $exports[$type];
$filename = $export['filename'] . '-' . date('Ymd-His') . '.csv';

if (!headers_sent()) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
}

$out = fopen('php://output', 'wb');
if ($out === false) {
    exit;
}
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, $export['headers']);
$stmt = $pdo->prepare($export['sql']);
$stmt->execute();
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $line = [];
    foreach ($export['headers'] as $header) {
        $line[] = $row[$header] ?? '';
    }
    fputcsv($out, $line);
}
fclose($out);
exit;
