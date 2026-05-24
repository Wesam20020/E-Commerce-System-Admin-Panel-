<?php
require __DIR__ . '/includes/bootstrap.php';

$currentPage = 'products';
$categorySlug = isset($_GET['category']) ? trim((string) $_GET['category']) : '';
$sort = isset($_GET['sort']) ? trim((string) $_GET['sort']) : '';
$brand = isset($_GET['brand']) ? trim((string) $_GET['brand']) : '';
$priceRange = isset($_GET['price']) ? trim((string) $_GET['price']) : '';
$inStockOnly = isset($_GET['stock']) && $_GET['stock'] === '1';
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 9;

$baseProducts = fetch_products($pdo, [
    'category_slug' => $categorySlug,
    'sort' => $sort,
]);

$currentCategory = null;
if ($categorySlug !== '') {
    foreach ($topCategories as $category) {
        if ($category['slug'] === $categorySlug) {
            $currentCategory = $category;
            break;
        }
    }
}

$brandOptions = [];
foreach ($baseProducts as $product) {
    $brandKey = trim((string) ($product['brand'] ?? ''));
    if ($brandKey === '') {
        continue;
    }
    if (!isset($brandOptions[$brandKey])) {
        $brandOptions[$brandKey] = 0;
    }
    $brandOptions[$brandKey]++;
}
ksort($brandOptions, SORT_NATURAL | SORT_FLAG_CASE);

$filteredProducts = array_values(array_filter($baseProducts, static function (array $product) use ($brand, $priceRange, $inStockOnly): bool {
    if ($brand !== '' && strcasecmp((string) ($product['brand'] ?? ''), $brand) !== 0) {
        return false;
    }

    $price = (float) ($product['price'] ?? 0);
    if ($priceRange === 'under_15000' && $price >= 15000) {
        return false;
    }
    if ($priceRange === '15000_29999' && ($price < 15000 || $price > 29999.99)) {
        return false;
    }
    if ($priceRange === '30000_plus' && $price < 30000) {
        return false;
    }

    if ($inStockOnly && (int) ($product['stock'] ?? 0) <= 0) {
        return false;
    }

    return true;
}));

$totalProducts = count($filteredProducts);
$totalPages = max(1, (int) ceil($totalProducts / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;
$products = array_slice($filteredProducts, $offset, $perPage);

$pageTitle = $siteName . ' | ' . ($currentCategory['name'] ?? 'Products');
$pageDescription = 'Browse iPhone, Android phones, wearables, and accessories with Turkey-focused warranty, stock, and delivery details.';

$navCategories = array_slice($topCategories, 0, 5);

function build_products_url(array $overrides = []): string
{
    $params = array_filter([
        'category' => $_GET['category'] ?? '',
        'sort' => $_GET['sort'] ?? '',
        'brand' => $_GET['brand'] ?? '',
        'price' => $_GET['price'] ?? '',
        'stock' => $_GET['stock'] ?? '',
        'page' => $_GET['page'] ?? '',
    ], static fn($value) => $value !== '' && $value !== null);

    foreach ($overrides as $key => $value) {
        if ($value === null || $value === '') {
            unset($params[$key]);
        } else {
            $params[$key] = $value;
        }
    }

    return site_url('products', $params);
}
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($pageDescription) ?>">
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
          darkMode: 'class',
          theme: {
            extend: {
              colors: {
                'on-primary': '#ffffff',
                'tertiary-fixed-dim': '#bec6e0',
                'surface-bright': '#f9f9fa',
                'tertiary': '#565e74',
                'on-error-container': '#93000a',
                'on-secondary-container': '#5e656c',
                'surface': '#f9f9fa',
                'on-background': '#1a1c1d',
                'on-secondary-fixed': '#151c22',
                'on-surface': '#1a1c1d',
                'error': '#ba1a1a',
                'on-primary-fixed': '#001d34',
                'surface-container-low': '#f3f3f4',
                'on-tertiary-fixed': '#131b2e',
                'error-container': '#ffdad6',
                'tertiary-fixed': '#dae2fd',
                'primary-fixed-dim': '#99cbff',
                'surface-container-high': '#e8e8e9',
                'on-tertiary-fixed-variant': '#3f465c',
                'surface-variant': '#e2e2e3',
                'primary-fixed': '#cfe5ff',
                'inverse-on-surface': '#f0f1f2',
                'surface-container-lowest': '#ffffff',
                'on-tertiary-container': '#2d3549',
                'on-secondary-fixed-variant': '#40484e',
                'surface-tint': '#00629e',
                'on-primary-fixed-variant': '#004a78',
                'tertiary-container': '#969db6',
                'secondary-fixed-dim': '#c0c7cf',
                'on-error': '#ffffff',
                'inverse-primary': '#99cbff',
                'primary-container': '#5ea3e3',
                'outline': '#717881',
                'secondary-fixed': '#dce3eb',
                'secondary': '#585f66',
                'on-secondary': '#ffffff',
                'surface-container': '#eeeeef',
                'surface-container-highest': '#e2e2e3',
                'surface-dim': '#d9dadb',
                'on-tertiary': '#ffffff',
                'background': '#f9f9fa',
                'outline-variant': '#c0c7d1',
                'on-surface-variant': '#414750',
                'inverse-surface': '#2f3132',
                'primary': '#00629e',
                'secondary-container': '#dce3eb',
                'on-primary-container': '#00385c'
              },
              borderRadius: {
                DEFAULT: '1rem',
                lg: '2rem',
                xl: '3rem',
                full: '9999px'
              },
              spacing: {
                'section-padding': '120px',
                'element-gap': '24px',
                'unit': '8px',
                'gutter': '32px',
                'container-max': '1280px'
              },
              fontFamily: {
                'body-lg': ['Manrope'],
                'label-caps': ['Manrope'],
                'h3': ['Manrope'],
                'body-md': ['Manrope'],
                'h1': ['Manrope'],
                'h2': ['Manrope']
              },
              fontSize: {
                'body-lg': ['18px', { lineHeight: '1.6', fontWeight: '400' }],
                'label-caps': ['12px', { lineHeight: '1', letterSpacing: '0.1em', fontWeight: '700' }],
                'h3': ['24px', { lineHeight: '1.3', fontWeight: '600' }],
                'body-md': ['16px', { lineHeight: '1.6', fontWeight: '400' }],
                'h1': ['64px', { lineHeight: '1.1', letterSpacing: '-0.02em', fontWeight: '700' }],
                'h2': ['40px', { lineHeight: '1.2', letterSpacing: '-0.01em', fontWeight: '600' }]
              }
            }
          }
        }
    </script>
    <style>
        body { background-color: #f9f9fa; color: #1a1c1d; }
        .glass-card {
            background: rgba(255, 255, 255, 0.4);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            box-shadow: 0 20px 40px rgba(94, 163, 227, 0.08);
        }
        .filter-chip-active {
            border-color: #00629e;
            background: rgba(0, 98, 158, 0.06);
            color: #00629e;
        }
        .toast {
            position: fixed;
            left: 50%;
            bottom: 24px;
            transform: translateX(-50%) translateY(12px);
            padding: 12px 18px;
            border-radius: 9999px;
            background: rgba(26, 28, 29, 0.92);
            color: white;
            font-size: 14px;
            opacity: 0;
            pointer-events: none;
            transition: all 0.25s ease;
            z-index: 100;
        }
        .toast.show {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }
    </style>
<link rel="stylesheet" href="<?= e(site_url('assets/css/top_nav.css')) ?>"/>
</head>
<body class="antialiased min-h-screen flex flex-col font-body-md text-body-md overflow-x-hidden" data-store-endpoint="<?= e(site_url('api_store')) ?>" data-csrf="<?= e(csrf_token()) ?>">
<?php require __DIR__ . '/includes/top_nav.php'; ?>

<main class="flex-grow max-w-[1280px] mx-auto w-full px-6 md:px-[32px] py-[48px] md:py-[80px]">
    <header class="mb-[64px] flex flex-col md:flex-row justify-between items-start md:items-end gap-[24px]">
        <div>
            <div class="text-sm text-on-surface-variant mb-4">
                <a href="<?= e(site_url('home')) ?>" class="hover:text-primary">Home</a>
                <span class="mx-2">/</span>
                <span><?= e($currentCategory['name'] ?? 'All Products') ?></span>
            </div>
            <h1 class="font-h1 text-[clamp(2.6rem,6vw,4rem)] leading-[1.05] text-on-surface mb-[8px]"><?= e($currentCategory['name'] ?? 'Phones & Accessories') ?></h1>
            <p class="font-body-lg text-body-lg text-on-surface-variant max-w-2xl">Browse iPhone, Android phones, wearables, and accessories with Turkey-focused warranty, stock, and delivery details.</p>
        </div>

        <form method="get" class="relative w-full md:w-72">
            <?php if ($categorySlug !== ''): ?><input type="hidden" name="category" value="<?= e($categorySlug) ?>"><?php endif; ?>
            <?php if ($brand !== ''): ?><input type="hidden" name="brand" value="<?= e($brand) ?>"><?php endif; ?>
            <?php if ($priceRange !== ''): ?><input type="hidden" name="price" value="<?= e($priceRange) ?>"><?php endif; ?>
            <?php if ($inStockOnly): ?><input type="hidden" name="stock" value="1"><?php endif; ?>
            <select name="sort" data-auto-submit="change" class="w-full appearance-none bg-surface-bright border border-outline-variant text-on-surface font-body-md text-body-md rounded-lg py-3 pl-4 pr-10 focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary transition-colors cursor-pointer">
                <option value="" <?= $sort === '' ? 'selected' : '' ?>>Sort by: Featured</option>
                <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price: Low to High</option>
                <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: High to Low</option>
                <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>Name: A-Z</option>
                <option value="rating_desc" <?= $sort === 'rating_desc' ? 'selected' : '' ?>>Top Rated</option>
            </select>
            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-on-surface-variant">
                <span class="material-symbols-outlined text-[20px]">expand_more</span>
            </div>
        </form>
    </header>

    <div class="flex flex-col lg:flex-row gap-[48px]">
        <aside class="w-full lg:w-64 flex-shrink-0 flex flex-col gap-[32px]">
            <div>
                <h3 class="font-label-caps text-label-caps text-on-surface mb-[16px] uppercase">Categories</h3>
                <div class="flex flex-wrap gap-[8px]">
                    <a class="px-4 py-2 rounded-full border font-body-md text-[14px] transition-colors <?= $categorySlug === '' ? 'filter-chip-active' : 'border-outline-variant text-on-surface-variant hover:bg-surface-variant' ?>" href="<?= e(build_products_url(['category' => null, 'page' => null])) ?>">All</a>
                    <?php foreach ($topCategories as $category): ?>
                        <a class="px-4 py-2 rounded-full border font-body-md text-[14px] transition-colors <?= $categorySlug === $category['slug'] ? 'filter-chip-active' : 'border-outline-variant text-on-surface-variant hover:bg-surface-variant' ?>" href="<?= e(build_products_url(['category' => $category['slug'], 'page' => null])) ?>"><?= e($category['name']) ?></a>
                    <?php endforeach; ?>
                </div>
            </div>

            <hr class="border-outline-variant/30">

            <div>
                <h3 class="font-label-caps text-label-caps text-on-surface mb-[16px] uppercase">Brand</h3>
                <div class="flex flex-col gap-[12px]">
                    <a class="flex items-center justify-between gap-[12px] cursor-pointer group <?= $brand === '' ? 'text-primary font-medium' : 'text-on-surface-variant' ?>" href="<?= e(build_products_url(['brand' => null, 'page' => null])) ?>">
                        <span>All Brands</span>
                        <span class="text-xs"><?= count($baseProducts) ?></span>
                    </a>
                    <?php foreach ($brandOptions as $brandName => $brandCount): ?>
                        <a class="flex items-center justify-between gap-[12px] cursor-pointer group <?= strcasecmp($brand, $brandName) === 0 ? 'text-primary font-medium' : 'text-on-surface-variant hover:text-on-surface' ?> transition-colors" href="<?= e(build_products_url(['brand' => $brandName, 'page' => null])) ?>">
                            <span><?= e($brandName) ?></span>
                            <span class="text-xs"><?= (int) $brandCount ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <hr class="border-outline-variant/30">

            <div>
                <h3 class="font-label-caps text-label-caps text-on-surface mb-[16px] uppercase">Price Range</h3>
                <div class="flex flex-col gap-[12px]">
                    <?php
                    $priceOptions = [
                        '' => 'Any Price',
                        'under_15000' => 'Under ' . format_price(15000, $siteCurrency),
                        '15000_29999' => format_price(15000, $siteCurrency) . ' - ' . format_price(29999, $siteCurrency),
                        '30000_plus' => format_price(30000, $siteCurrency) . '+',
                    ];
                    foreach ($priceOptions as $value => $label):
                    ?>
                        <a class="<?= $priceRange === $value ? 'text-primary font-medium' : 'text-on-surface-variant hover:text-on-surface' ?> transition-colors" href="<?= e(build_products_url(['price' => $value ?: null, 'page' => null])) ?>"><?= e($label) ?></a>
                    <?php endforeach; ?>
                </div>
            </div>

            <hr class="border-outline-variant/30">

            <div>
                <a class="flex items-center justify-between cursor-pointer group <?= $inStockOnly ? 'text-primary font-medium' : 'text-on-surface-variant' ?>" href="<?= e(build_products_url(['stock' => $inStockOnly ? null : '1', 'page' => null])) ?>">
                    <span class="font-label-caps text-label-caps uppercase">In Stock Only</span>
                    <span class="material-symbols-outlined text-[20px]"><?= $inStockOnly ? 'toggle_on' : 'toggle_off' ?></span>
                </a>
            </div>
        </aside>

        <div class="flex-grow">
            <div class="flex items-center justify-between mb-8 gap-4 flex-wrap">
                <div class="text-on-surface-variant text-sm"><?= $totalProducts ?> product<?= $totalProducts === 1 ? '' : 's' ?> found</div>
                <?php if ($brand !== '' || $priceRange !== '' || $inStockOnly): ?>
                    <a href="<?= e(build_products_url(['brand' => null, 'price' => null, 'stock' => null, 'page' => null])) ?>" class="text-primary text-sm font-medium hover:underline">Clear filters</a>
                <?php endif; ?>
            </div>

            <?php if (!$products): ?>
                <div class="glass-card rounded-[32px] p-10 text-center">
                    <h3 class="text-2xl font-semibold text-on-surface mb-3">No products found</h3>
                    <p class="text-on-surface-variant mb-6">Try changing the category, brand, or price range to see more results.</p>
                    <a href="<?= e(site_url('products', $categorySlug !== '' ? ['category' => $categorySlug] : [])) ?>" class="inline-flex items-center justify-center px-6 py-3 rounded-full bg-primary text-white font-medium">Reset selection</a>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-[32px]">
                    <?php foreach ($products as $product): ?>
                        <?php $canBuy = public_product_is_purchasable($product); ?>
                        <article class="glass-card rounded-[32px] p-[24px] flex flex-col group transition-transform duration-300 hover:-translate-y-2 relative overflow-hidden">
                            <div class="absolute top-6 left-6 z-10 flex flex-wrap gap-2">
                                <span class="bg-primary-container text-on-primary-container px-3 py-1 rounded-full font-label-caps text-[10px] uppercase tracking-wider"><?= e(public_product_badge($product)) ?></span>
                            </div>
                            <button type="button" class="absolute top-6 right-6 z-10 w-10 h-10 rounded-full border border-white/60 bg-white/60 backdrop-blur-md flex items-center justify-center text-on-surface-variant hover:text-error transition-colors js-wishlist-toggle" data-product-id="<?= (int) $product['id'] ?>" aria-label="Wishlist">♡</button>

                            <a href="<?= e(site_url('product', ['slug' => $product['slug']])) ?>" class="h-64 mb-[24px] relative flex items-center justify-center bg-gradient-to-b from-transparent to-surface-bright/50 rounded-[24px] overflow-hidden">
                                <img src="<?= e($product['image']) ?>" alt="<?= e($product['name']) ?>" class="w-full h-full object-contain mix-blend-multiply group-hover:scale-105 transition-transform duration-500">
                            </a>

                            <div class="flex flex-col flex-grow">
                                <span class="font-body-md text-[14px] text-on-surface-variant mb-[4px]"><?= e($product['brand'] ?: ($product['category_name'] ?? 'Products')) ?></span>
                                <h3 class="font-h3 text-h3 text-on-surface mb-[8px] line-clamp-1"><?= e($product['name']) ?></h3>
                                <p class="text-sm text-on-surface-variant mb-5 line-clamp-2"><?= e($product['short_description']) ?></p>
                                <div class="flex items-center gap-[12px] mb-[24px] flex-wrap">
                                    <span class="font-h3 text-[20px] text-on-surface font-semibold"><?= e(format_price($product['price'], $siteCurrency)) ?></span>
                                    <?php if (!empty($product['compare_price'])): ?>
                                        <span class="font-body-md text-[16px] text-on-surface-variant line-through"><?= e(format_price($product['compare_price'], $siteCurrency)) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="mt-auto flex gap-[12px]">
                                    <button type="button" class="flex-grow bg-gradient-to-r from-primary to-primary-container text-on-primary font-body-md text-body-md py-3 rounded-full hover:shadow-lg transition-all duration-300 <?= $canBuy ? 'js-add-to-cart' : 'opacity-60 cursor-not-allowed' ?>" data-product-id="<?= (int) $product['id'] ?>" data-product-name="<?= e($product['name']) ?>" <?= $canBuy ? '' : 'disabled aria-disabled="true"' ?>><?= $canBuy ? 'Add to Cart' : 'Sold Out' ?></button>
                                    <a href="<?= e(site_url('product', ['slug' => $product['slug']])) ?>" class="w-12 h-12 rounded-full border border-outline-variant flex items-center justify-center text-on-surface-variant hover:bg-surface-variant transition-colors" aria-label="View product">
                                        <span class="material-symbols-outlined">visibility</span>
                                    </a>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <?php if ($totalPages > 1): ?>
                    <div class="mt-[64px] flex justify-center items-center gap-[8px] flex-wrap">
                        <a class="w-10 h-10 rounded-full border border-outline-variant flex items-center justify-center text-on-surface-variant hover:bg-surface-variant transition-colors <?= $page <= 1 ? 'pointer-events-none opacity-50' : '' ?>" href="<?= e(build_products_url(['page' => max(1, $page - 1)])) ?>"><span class="material-symbols-outlined">chevron_left</span></a>
                        <?php
                        $windowStart = max(1, $page - 2);
                        $windowEnd = min($totalPages, $page + 2);
                        if ($windowStart > 1): ?>
                            <a class="w-10 h-10 rounded-full hover:bg-surface-variant text-on-surface-variant font-body-md transition-colors flex items-center justify-center" href="<?= e(build_products_url(['page' => 1])) ?>">1</a>
                            <?php if ($windowStart > 2): ?><span class="text-on-surface-variant">...</span><?php endif; ?>
                        <?php endif; ?>
                        <?php for ($i = $windowStart; $i <= $windowEnd; $i++): ?>
                            <a class="w-10 h-10 rounded-full <?= $i === $page ? 'bg-primary/10 text-primary font-semibold' : 'hover:bg-surface-variant text-on-surface-variant' ?> font-body-md transition-colors flex items-center justify-center" href="<?= e(build_products_url(['page' => $i])) ?>"><?= $i ?></a>
                        <?php endfor; ?>
                        <?php if ($windowEnd < $totalPages): ?>
                            <?php if ($windowEnd < $totalPages - 1): ?><span class="text-on-surface-variant">...</span><?php endif; ?>
                            <a class="w-10 h-10 rounded-full hover:bg-surface-variant text-on-surface-variant font-body-md transition-colors flex items-center justify-center" href="<?= e(build_products_url(['page' => $totalPages])) ?>"><?= $totalPages ?></a>
                        <?php endif; ?>
                        <a class="w-10 h-10 rounded-full border border-outline-variant flex items-center justify-center text-on-surface-variant hover:bg-surface-variant transition-colors <?= $page >= $totalPages ? 'pointer-events-none opacity-50' : '' ?>" href="<?= e(build_products_url(['page' => min($totalPages, $page + 1)])) ?>"><span class="material-symbols-outlined">chevron_right</span></a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</main>

<footer class="w-full pt-24 pb-12 border-t border-slate-100 bg-slate-50 font-['Manrope'] text-sm text-slate-500 mt-auto">
    <div class="max-w-7xl mx-auto px-6 md:px-12 grid grid-cols-1 md:grid-cols-4 gap-12">
        <div class="col-span-1 md:col-span-2">
            <span class="text-xl font-bold text-slate-900 mb-4 block"><?= e($siteName) ?></span>
            <p>© <?= date('Y') ?> <?= e($siteName) ?>. <?= e(store_setting($siteSettings ?? [], 'footer_tagline', 'Official phones, trusted warranty, fast delivery across Turkey.')) ?></p>
        </div>
        <div class="col-span-1">
            <ul class="space-y-3">
                <li><a class="text-slate-500 hover:text-blue-500 transition-colors inline-block" href="<?= e(site_url('support')) ?>">Support</a></li>
                <li><a class="text-slate-500 hover:text-blue-500 transition-colors inline-block" href="<?= e(site_url('brands')) ?>">Brands</a></li>
                <li><a class="text-slate-500 hover:text-blue-500 transition-colors inline-block" href="<?= e(site_url('deals')) ?>">Deals</a></li>
            </ul>
        </div>
        <div class="col-span-1">
            <ul class="space-y-3">
                <li><a class="text-slate-500 hover:text-blue-500 transition-colors inline-block" href="<?= e(site_url('new_arrivals')) ?>">New Arrivals</a></li>
                <li><a class="text-slate-500 hover:text-blue-500 transition-colors inline-block" href="<?= e(site_url('checkout')) ?>">Checkout</a></li>
            </ul>
        </div>
    </div>
</footer>

<div class="toast js-toast" aria-live="polite"></div>
<script src="assets/js/site.js"></script>
</body>
</html>
