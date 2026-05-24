<?php
require_once __DIR__ . '/../includes/layout.php';

try {
    $ctx = admin_boot('products');
    $pdo = $ctx['pdo'];

    if (!is_post_request()) {
        admin_json_response(['ok' => false, 'message' => 'POST request required.'], 405);
    }
    verify_csrf_or_fail($_POST['_csrf'] ?? null);

    $action = (string) ($_POST['admin_action'] ?? '');
    $productId = admin_int_input('product_id');
    $product = $productId > 0 ? admin_find_product($pdo, $productId) : null;
    if (!$product && $action !== '') {
        admin_json_response(['ok' => false, 'message' => 'Product was not found.'], 404);
    }

    if ($action === 'product_activity') {
        if (!admin_table_exists($pdo, 'admin_activity_logs')) {
            admin_json_response(['ok' => true, 'items' => []]);
        }
        $logs = admin_rows($pdo, "SELECT action, details, created_at FROM admin_activity_logs WHERE entity_type = 'product' AND entity_id = :id ORDER BY created_at DESC, id DESC LIMIT 8", ['id' => $productId]);
        $items = array_map(static function (array $log): array {
            return [
                'action' => (string) ($log['action'] ?? ''),
                'details' => (string) ($log['details'] ?? ''),
                'created_at' => (string) ($log['created_at'] ?? ''),
            ];
        }, $logs);
        admin_json_response(['ok' => true, 'items' => $items]);
    }

    if ($action === 'product_quick_stock') {
        $newStock = admin_int_input('new_stock');
        $oldStock = (int) ($product['stock'] ?? 0);
        $oldStatus = admin_product_status_value($product['product_status'] ?? null, (int) ($product['is_active'] ?? 0), $oldStock);
        admin_adjust_product_stock($pdo, $productId, $newStock, 'Quick update from published products drawer');
        $pdo->prepare("UPDATE products SET product_status = CASE WHEN product_status IN ('draft','archived') THEN product_status WHEN stock <= 0 THEN 'out_of_stock' ELSE 'active' END, is_active = CASE WHEN product_status IN ('draft','archived') THEN is_active ELSE 1 END WHERE id = :id LIMIT 1")->execute(['id' => $productId]);
        $newStatus = $newStock <= 0 ? 'out_of_stock' : 'active';
        admin_log_activity($pdo, 'product_quick_stock_reviewed', 'product', $productId, (string) ($product['name'] ?? 'Product') . ': stock ' . $oldStock . ' → ' . $newStock . ', status ' . $oldStatus . ' → ' . $newStatus);
        admin_json_response(['ok' => true, 'message' => 'Stock updated.']);
    }

    if ($action === 'product_quick_price') {
        $price = admin_decimal_input('price');
        $discountPercentRaw = trim((string) ($_POST['discount_percent'] ?? ''));
        $discountPercent = $discountPercentRaw === '' ? 0.0 : round((float) str_replace(',', '.', $discountPercentRaw), 2);
        if ($price <= 0) {
            admin_json_response(['ok' => false, 'message' => 'Price must be greater than 0.'], 422);
        }
        if ($discountPercent < 0 || $discountPercent > 95) {
            admin_json_response(['ok' => false, 'message' => 'Discount must be between 0 and 95%.'], 422);
        }
        $comparePrice = null;
        if ($discountPercent > 0 && $price > 0) {
            $comparePrice = round($price / (1 - ($discountPercent / 100)), 2);
        }
        $oldPrice = (float) ($product['price'] ?? 0);
        $oldComparePrice = $product['compare_price'] ?? null;
        $oldDiscountPercent = ($oldComparePrice !== null && (float) $oldComparePrice > $oldPrice && (float) $oldComparePrice > 0) ? round((((float) $oldComparePrice - $oldPrice) / (float) $oldComparePrice) * 100, 2) : 0;
        $pdo->prepare('UPDATE products SET price = :price, compare_price = :compare_price WHERE id = :id LIMIT 1')->execute([
            'price' => $price,
            'compare_price' => $comparePrice,
            'id' => $productId,
        ]);
        admin_log_activity($pdo, 'product_quick_price_updated', 'product', $productId, (string) ($product['name'] ?? 'Product') . ': price ' . number_format($oldPrice, 2) . ' → ' . number_format($price, 2) . ', discount ' . rtrim(rtrim(number_format($oldDiscountPercent, 2, '.', ''), '0'), '.') . '% → ' . rtrim(rtrim(number_format($discountPercent, 2, '.', ''), '0'), '.') . '%');
        admin_json_response(['ok' => true, 'message' => 'Price updated.']);
    }

    if ($action === 'product_toggle_featured') {
        $next = (int) ($product['is_featured'] ?? 0) === 1 ? 0 : 1;
        $pdo->prepare('UPDATE products SET is_featured = :featured WHERE id = :id LIMIT 1')->execute(['featured' => $next, 'id' => $productId]);
        admin_log_activity($pdo, $next ? 'product_featured' : 'product_unfeatured', 'product', $productId, (string) ($product['name'] ?? ''));
        admin_json_response(['ok' => true, 'message' => $next ? 'Product marked featured.' : 'Product removed from featured.']);
    }

    if ($action === 'product_sync_status') {
        $pdo->prepare("UPDATE products SET product_status = CASE WHEN stock <= 0 THEN 'out_of_stock' ELSE 'active' END, is_active = 1 WHERE id = :id LIMIT 1")->execute(['id' => $productId]);
        admin_log_activity($pdo, 'product_stock_status_synced', 'product', $productId, (string) ($product['name'] ?? ''));
        admin_json_response(['ok' => true, 'message' => 'Stock/status logic fixed.']);
    }

    if ($action === 'product_draft') {
        $pdo->prepare("UPDATE products SET product_status = 'draft', is_active = 0 WHERE id = :id LIMIT 1")->execute(['id' => $productId]);
        admin_log_activity($pdo, 'product_moved_to_draft', 'product', $productId, (string) ($product['name'] ?? ''));
        admin_json_response(['ok' => true, 'message' => 'Product moved to draft.']);
    }

    if ($action === 'product_archive') {
        $pdo->prepare("UPDATE products SET product_status = 'archived', is_active = 0 WHERE id = :id LIMIT 1")->execute(['id' => $productId]);
        admin_log_activity($pdo, 'product_archived', 'product', $productId, (string) ($product['name'] ?? ''));
        admin_json_response(['ok' => true, 'message' => 'Product archived.']);
    }

    if ($action === 'product_duplicate') {
        $copyName = mb_substr((string) ($product['name'] ?? 'Product') . ' Copy', 0, 190);
        $copySlug = admin_unique_slug($pdo, 'products', (string) ($product['slug'] ?? $copyName) . '-copy');
        $stmt = $pdo->prepare('INSERT INTO products (category_id, name, slug, sku, brand, badge, product_type, short_description, description, specs_json, price, compare_price, stock, rating, image, gallery_json, product_status, is_active, is_featured) VALUES (:category_id, :name, :slug, :sku, :brand, :badge, :product_type, :short_description, :description, :specs_json, :price, :compare_price, :stock, :rating, :image, :gallery_json, :product_status, :is_active, :is_featured)');
        $stmt->execute([
            'category_id' => $product['category_id'] ?? null,
            'name' => $copyName,
            'slug' => $copySlug,
            'sku' => null,
            'brand' => $product['brand'] ?? null,
            'badge' => $product['badge'] ?? null,
            'product_type' => $product['product_type'] ?? 'general',
            'short_description' => $product['short_description'] ?? null,
            'description' => $product['description'] ?? null,
            'specs_json' => $product['specs_json'] ?? null,
            'price' => $product['price'] ?? 0,
            'compare_price' => $product['compare_price'] ?? null,
            'stock' => $product['stock'] ?? 0,
            'rating' => $product['rating'] ?? 0,
            'image' => $product['image'] ?? null,
            'gallery_json' => $product['gallery_json'] ?? null,
            'product_status' => 'draft',
            'is_active' => 0,
            'is_featured' => 0,
        ]);
        $newId = (int) $pdo->lastInsertId();
        admin_log_activity($pdo, 'product_duplicated_as_draft', 'product', $newId, $copyName);
        admin_json_response(['ok' => true, 'message' => 'Product duplicated as draft.', 'edit_url' => admin_page_url('product_edit', ['id' => $newId])]);
    }

    admin_json_response(['ok' => false, 'message' => 'Unknown product action.'], 400);
} catch (Throwable $e) {
    error_log('[Phonix product api] ' . $e->getMessage());
    $message = function_exists('app_debug') && app_debug() ? $e->getMessage() : 'Product action failed.';
    admin_json_response(['ok' => false, 'message' => $message], 500);
}
