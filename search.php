<?php
require __DIR__ . '/includes/bootstrap.php';

$query = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$sort = isset($_GET['sort']) ? trim((string) $_GET['sort']) : '';
$allowedSorts = ['price_asc', 'price_desc', 'rating_desc', 'name_asc'];
if (!in_array($sort, $allowedSorts, true)) {
    $sort = '';
}

$filters = [];
if ($query !== '') {
    $filters['search'] = $query;
}
if ($sort !== '') {
    $filters['sort'] = $sort;
}
$results = $query !== '' ? fetch_products($pdo, $filters) : [];
$featuredResult = $results[0] ?? null;
$remainingResults = $featuredResult ? array_slice($results, 1) : [];

$pageTitle = $siteName . ' | Search';
$pageDescription = 'Search phones and accessories at Phonix Türkiye.';
$currentPage = 'search';
require __DIR__ . '/includes/partials_header.php';
?>
<section class="section">
    <div class="container page-head compact-head">
        <div>
            <div class="breadcrumbs"><a href="<?= e(site_url('home')) ?>">Home</a> / <span>Search</span></div>
            <h1><?= $query === '' ? 'Search phones and accessories' : 'Results for “' . e($query) . '”' ?></h1>
            <p><?= $query === '' ? 'Type a phone model, brand, accessory, or category and browse matching products.' : 'Showing ' . count($results) . ' matching product' . (count($results) === 1 ? '' : 's') . ' from the live database.' ?></p>
        </div>
    </div>
</section>

<section class="section" style="padding-top:0">
    <div class="container">
        <form class="card search-hero" method="get" action="<?= e(site_url('search')) ?>">
            <span class="material-symbols-outlined" aria-hidden="true" style="color:var(--outline);padding-left:12px">search</span>
            <input type="search" name="q" value="<?= e($query) ?>" placeholder="Search products, brands, categories...">
            <select name="sort" aria-label="Sort results" style="max-width:220px">
                <option value=""<?= $sort === '' ? ' selected' : '' ?>>Relevance</option>
                <option value="price_asc"<?= $sort === 'price_asc' ? ' selected' : '' ?>>Price: Low to High</option>
                <option value="price_desc"<?= $sort === 'price_desc' ? ' selected' : '' ?>>Price: High to Low</option>
                <option value="rating_desc"<?= $sort === 'rating_desc' ? ' selected' : '' ?>>Top Rated</option>
                <option value="name_asc"<?= $sort === 'name_asc' ? ' selected' : '' ?>>Name A-Z</option>
            </select>
            <button class="primary-btn" type="submit">Search</button>
        </form>
    </div>
</section>

<section class="section" style="padding-top:0">
    <div class="container">
        <?php if ($query === ''): ?>
            <div class="empty-state card">Enter a phone model, brand, or accessory name to search our products.</div>
        <?php elseif (!$results): ?>
            <div class="empty-state card">No matching products were found.</div>
        <?php else: ?>
            <?php if ($featuredResult): ?>
                <?php $featuredCanBuy = public_product_is_purchasable($featuredResult); ?>
                <article class="card" style="padding:32px;margin-bottom:24px;overflow:hidden">
                    <div class="grid-2" style="align-items:center">
                        <a class="product-image-frame" href="<?= e(site_url('product', ['slug' => $featuredResult['slug']])) ?>" style="min-height:360px">
                            <img src="<?= e($featuredResult['image']) ?>" alt="<?= e($featuredResult['name']) ?>">
                        </a>
                        <div>
                            <span class="badge">Best Match</span>
                            <h2 style="font-size:clamp(34px,5vw,56px);line-height:1.05;letter-spacing:-.04em;margin:18px 0 12px;color:var(--on-surface)"><?= e($featuredResult['name']) ?></h2>
                            <p class="muted"><?= e($featuredResult['short_description'] ?: 'Featured product.') ?></p>
                            <div class="price-row" style="justify-content:flex-start;margin:24px 0">
                                <strong style="font-size:28px;color:var(--primary)"><?= e(format_price($featuredResult['price'], $siteCurrency)) ?></strong>
                                <?php if (!empty($featuredResult['compare_price'])): ?><s><?= e(format_price($featuredResult['compare_price'], $siteCurrency)) ?></s><?php endif; ?>
                            </div>
                            <div style="display:flex;gap:12px;flex-wrap:wrap">
                                <button type="button" class="primary-btn <?= $featuredCanBuy ? 'js-add-to-cart' : 'is-disabled-buy' ?>" data-product-id="<?= (int) $featuredResult['id'] ?>" data-product-name="<?= e($featuredResult['name']) ?>" <?= $featuredCanBuy ? '' : 'disabled aria-disabled="true"' ?>><?= $featuredCanBuy ? 'Add to Cart' : 'Sold Out' ?></button>
                                <a class="outline-btn" href="<?= e(site_url('product', ['slug' => $featuredResult['slug']])) ?>">View Details</a>
                                <button type="button" class="outline-btn js-wishlist-toggle" data-product-id="<?= (int) $featuredResult['id'] ?>" aria-label="Wishlist">♡</button>
                            </div>
                        </div>
                    </div>
                </article>
            <?php endif; ?>

            <?php if ($remainingResults): ?>
                <div class="product-grid product-grid-wide">
                    <?php foreach ($remainingResults as $product): ?>
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
        <?php endif; ?>
    </div>
</section>

<div class="toast js-toast" aria-live="polite"></div>
<?php require __DIR__ . '/includes/partials_footer.php'; ?>
