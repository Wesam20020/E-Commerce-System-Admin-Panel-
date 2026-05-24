<?php
require __DIR__ . '/includes/bootstrap.php';

$pageTitle = $siteName . ' | Brands';
$pageDescription = 'Browse phone and accessory brands available at Phonix Türkiye.';
$currentPage = 'brands';

store_ensure_public_product_columns($pdo);
$brandVisibleSql = store_public_product_visibility_sql('products');
$stmt = $pdo->query('SELECT brand, COUNT(*) AS product_count, MIN(price) AS min_price, MAX(price) AS max_price
                     FROM products
                     WHERE ' . $brandVisibleSql . ' AND brand IS NOT NULL AND brand <> ""
                     GROUP BY brand
                     ORDER BY product_count DESC, brand ASC');
$brands = $stmt->fetchAll();
$allProducts = fetch_products($pdo);
$productsByBrand = [];
foreach ($allProducts as $product) {
    $brand = trim((string) ($product['brand'] ?? ''));
    if ($brand === '') {
        continue;
    }
    $productsByBrand[$brand][] = $product;
}
$featuredBrands = array_slice($brands, 0, 4);

require __DIR__ . '/includes/partials_header.php';
?>
<section class="section">
    <div class="container page-head compact-head">
        <div>
            <div class="breadcrumbs"><a href="<?= e(site_url('home')) ?>">Home</a> / <span>Brands</span></div>
            <h1>Shop by Brand</h1>
            <p>Discover available phones and accessories from the brands we carry.</p>
        </div>
    </div>
</section>

<?php if ($featuredBrands): ?>
<section class="section" style="padding-top:0">
    <div class="container">
        <div class="section-head">
            <div><div class="small">Curated Selection</div><h2>Featured Partners</h2></div>
            <a class="ghost-btn" href="#all-brands">View All <span class="material-symbols-outlined" style="font-size:16px">arrow_forward</span></a>
        </div>
        <div class="brand-grid">
            <?php foreach ($featuredBrands as $brand): ?>
                <a class="card brand-card" href="<?= e(site_url('search', ['q' => $brand['brand']])) ?>">
                    <div>
                        <div class="brand-mark-text"><?= e($brand['brand']) ?></div>
                        <div class="muted" style="margin-top:10px"><?= (int) $brand['product_count'] ?> products</div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="section" id="all-brands" style="padding-top:0">
    <div class="container">
        <?php if (!$brands): ?>
            <div class="empty-state card">No brands are available right now.</div>
        <?php else: ?>
            <?php foreach ($brands as $brand): ?>
                <?php $brandProducts = array_slice($productsByBrand[$brand['brand']] ?? [], 0, 3); ?>
                <section style="margin-bottom:64px">
                    <div class="section-head">
                        <div>
                            <h2><?= e($brand['brand']) ?></h2>
                            <p><?= (int) $brand['product_count'] ?> product<?= (int) $brand['product_count'] === 1 ? '' : 's' ?> · <?= e(format_price($brand['min_price'], $siteCurrency)) ?> – <?= e(format_price($brand['max_price'], $siteCurrency)) ?></p>
                        </div>
                        <a class="ghost-btn" href="<?= e(site_url('search', ['q' => $brand['brand']])) ?>">Shop <?= e($brand['brand']) ?> <span class="material-symbols-outlined" style="font-size:16px">arrow_forward</span></a>
                    </div>
                    <?php if ($brandProducts): ?>
                        <div class="product-grid product-grid-wide">
                            <?php foreach ($brandProducts as $product): ?>
                                <?php $canBuy = public_product_is_purchasable($product); ?>
                                <article class="product-card">
                                    <div class="product-top">
                                        <span class="badge"><?= e(public_product_badge($product, (string) $brand['brand'])) ?></span>
                                        <button type="button" class="icon-btn js-wishlist-toggle" data-product-id="<?= (int) $product['id'] ?>">♡</button>
                                    </div>
                                    <a href="<?= e(site_url('product', ['slug' => $product['slug']])) ?>" class="product-image-frame">
                                        <img src="<?= e($product['image']) ?>" alt="<?= e($product['name']) ?>">
                                    </a>
                                    <div class="product-meta">
                                        <small><?= e($product['category_name'] ?? 'Products') ?></small>
                                        <div class="product-title"><?= e($product['name']) ?></div>
                                        <small><?= e($product['short_description']) ?></small>
                                    </div>
                                    <div class="price-row">
                                        <strong><?= e(format_price($product['price'], $siteCurrency)) ?></strong>
                                        <button type="button" class="primary-btn <?= $canBuy ? 'js-add-to-cart' : 'is-disabled-buy' ?>" data-product-id="<?= (int) $product['id'] ?>" data-product-name="<?= e($product['name']) ?>" <?= $canBuy ? '' : 'disabled aria-disabled="true"' ?>><?= $canBuy ? 'Add' : 'Sold Out' ?></button>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>
<div class="toast js-toast" aria-live="polite"></div>
<?php require __DIR__ . '/includes/partials_footer.php'; ?>
