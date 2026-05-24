<?php
require __DIR__ . '/includes/bootstrap.php';

$pageTitle = $siteName . ' | Wishlist';
$pageDescription = 'Saved phones, accessories, and wishlist overview.';
$currentPage = 'wishlist';
$wishlistItems = fetch_wishlist_items($pdo);
$featured = fetch_featured_products($pdo, 4);
require __DIR__ . '/includes/partials_header.php';
?>
<section class="section">
    <div class="container page-head compact-head">
        <div>
            <div class="breadcrumbs"><a href="<?= e(site_url('home')) ?>">Home</a> / <span>Wishlist</span></div>
            <h1>Wishlist</h1>
            <p>Your saved phones and accessories are stored against the live session or your real account, and they follow you after sign-in.</p>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <?php if ($wishlistItems === []): ?>
            <div class="empty-state card">Your wishlist is empty right now. Save products you like, then come back here to move them into the cart.</div>
        <?php else: ?>
            <div class="store-list" data-wishlist-page>
                <?php foreach ($wishlistItems as $item): ?>
                    <?php $canBuy = public_product_is_purchasable($item); ?>
                    <article class="card store-item">
                        <a href="<?= e(site_url('product', ['slug' => $item['slug']])) ?>" class="store-item-media">
                            <img src="<?= e($item['image']) ?>" alt="<?= e($item['name']) ?>">
                        </a>
                        <div class="store-item-copy">
                            <div class="store-item-top">
                                <div>
                                    <div class="product-title"><?= e($item['name']) ?></div>
                                    <small><?= e($item['short_description']) ?></small>
                                </div>
                                <span class="badge"><?= e(public_product_badge($item, 'Wishlist')) ?></span>
                            </div>
                            <div class="store-item-bottom">
                                <strong><?= e(format_price($item['price'], $siteCurrency)) ?></strong>
                                <div class="store-item-actions">
                                    <button type="button" class="primary-btn <?= $canBuy ? '' : 'is-disabled-buy' ?>" data-store-action="move_wishlist_to_cart" data-product-id="<?= (int) $item['id'] ?>" <?= $canBuy ? '' : 'disabled aria-disabled="true"' ?>><?= $canBuy ? 'Move to cart' : 'Sold Out' ?></button>
                                    <button type="button" class="outline-btn js-wishlist-toggle" data-product-id="<?= (int) $item['id'] ?>" data-label-mode="text" aria-pressed="true">Saved to wishlist</button>
                                </div>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-head"><div><h2>You may also like</h2></div></div>
        <div class="product-grid">
            <?php foreach ($featured as $product): ?>
                <?php $canBuy = public_product_is_purchasable($product); ?>
                <article class="product-card">
                    <div class="product-top">
                        <span class="badge"><?= e(public_product_badge($product, (string) ($product['brand'] ?? 'Product'))) ?></span>
                        <button type="button" class="icon-btn js-wishlist-toggle" data-product-id="<?= (int) $product['id'] ?>">♡</button>
                    </div>
                    <a href="<?= e(site_url('product', ['slug' => $product['slug']])) ?>" class="product-image-frame">
                        <img src="<?= e($product['image']) ?>" alt="<?= e($product['name']) ?>">
                    </a>
                    <div class="product-meta">
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
    </div>
</section>
<div class="toast js-toast" aria-live="polite"></div>
<?php require __DIR__ . '/includes/partials_footer.php'; ?>
