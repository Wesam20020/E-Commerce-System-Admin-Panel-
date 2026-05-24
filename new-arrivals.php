<?php
require __DIR__ . '/includes/bootstrap.php';

$pageTitle = $siteName . ' | New Arrivals';
$pageDescription = 'Recently added phones and accessories available from Phonix Türkiye.';
$currentPage = 'new_arrivals';

$products = fetch_products($pdo);
$latestProducts = array_slice($products, 0, 8);

require __DIR__ . '/includes/partials_header.php';
?>
<section class="section">
    <div class="container page-head compact-head">
        <div>
            <div class="breadcrumbs"><a href="<?= e(site_url('home')) ?>">Home</a> / <span>New arrivals</span></div>
            <h1>New arrivals</h1>
            <p>Explore the newest smartphones and accessories recently added to Phonix Türkiye.</p>
        </div>
    </div>
</section>

<section class="section">
    <div class="container product-grid product-grid-wide">
        <?php foreach ($latestProducts as $product): ?>
            <?php $canBuy = public_product_is_purchasable($product); ?>
            <article class="product-card">
                <div class="product-top">
                    <span class="badge"><?= e(public_product_badge($product, 'New')) ?></span>
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
                    <button type="button" class="primary-btn <?= $canBuy ? 'js-add-to-cart' : 'is-disabled-buy' ?>"
                            data-product-id="<?= (int) $product['id'] ?>"
                            data-product-name="<?= e($product['name']) ?>" <?= $canBuy ? '' : 'disabled aria-disabled="true"' ?>><?= $canBuy ? 'Add' : 'Sold Out' ?></button>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<div class="toast js-toast" aria-live="polite"></div>
<?php require __DIR__ . '/includes/partials_footer.php'; ?>
