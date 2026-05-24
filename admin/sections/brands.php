<?php
require_once __DIR__ . '/../includes/layout.php';
$ctx = admin_boot('brands');
$pdo = $ctx['pdo'];
$currency = $ctx['siteCurrency'];

if (is_post_request()) {
    verify_csrf_or_fail($_POST['_csrf'] ?? null);
    try {
        $old = admin_clean_text('old_brand', 150);
        $new = admin_clean_text('new_brand', 150);
        if ($old === '' || $new === '') { throw new RuntimeException('Both old and new brand names are required.'); }
        $pdo->prepare('UPDATE products SET brand = :new WHERE brand = :old')->execute(['new' => $new, 'old' => $old]);
        flash_set('success', 'Brand renamed across linked products.');
    } catch (Throwable $e) { admin_flash_from_exception($e); }
    admin_redirect('brands');
}

$brands = admin_rows($pdo, "SELECT brand, COUNT(*) product_count, MIN(price) min_price, MAX(price) max_price, SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) active_count FROM products WHERE brand IS NOT NULL AND brand <> '' GROUP BY brand ORDER BY product_count DESC, brand ASC");
$unbranded = (int) admin_scalar($pdo, "SELECT COUNT(*) FROM products WHERE brand IS NULL OR brand = ''");
admin_header('Brands', 'Manage manufacturer names used by products and brand discovery pages.', 'brands');
?>
<section class="admin-metrics-grid">
    <?php admin_metric_card('Brands', (string) count($brands), 'sell', 'Detected from products'); ?>
    <?php admin_metric_card('Unbranded products', (string) $unbranded, 'label_off', 'Need cleanup'); ?>
</section>
<section class="admin-card glass-panel">
    <div class="admin-section-head"><div><p class="admin-eyebrow">Brand directory</p><h2>All brands</h2></div><a class="admin-text-link" href="<?= e(admin_root_url('brands.php')) ?>">Open storefront brands</a></div>
    <?php if (!$brands): ?><?php admin_empty_state('No brands yet', 'Add brand names to products first.'); ?><?php else: ?>
    <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Brand</th><th>Products</th><th>Active</th><th>Price range</th><th>Rename</th></tr></thead><tbody>
    <?php foreach ($brands as $brand): ?>
        <tr><td><strong><?= e($brand['brand']) ?></strong></td><td><?= (int)$brand['product_count'] ?></td><td><?= (int)$brand['active_count'] ?></td><td><?= e(admin_money($brand['min_price'], $currency)) ?> – <?= e(admin_money($brand['max_price'], $currency)) ?></td><td><form method="post" class="admin-inline-form"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="old_brand" value="<?= e($brand['brand']) ?>"><input name="new_brand" value="<?= e($brand['brand']) ?>"><button type="submit">Rename</button></form></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
</section>
<?php admin_footer(); ?>
