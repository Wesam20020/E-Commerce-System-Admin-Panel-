<?php
require __DIR__ . '/includes/bootstrap.php';

if (file_exists(__DIR__ . '/includes/auth.php')) {
    require_once __DIR__ . '/includes/auth.php';
}

$slug = isset($_GET['slug']) ? trim((string) $_GET['slug']) : '';
$product = $slug !== '' ? fetch_product_by_slug($pdo, $slug) : null;

if (!$product) {
    http_response_code(404);
    $pageTitle = ($siteName ?? 'Phonix') . ' | Product not found';
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body{font-family:Manrope,system-ui,sans-serif;background:#f9f9fa;color:#1a1c1d;display:grid;place-items:center;min-height:100vh;margin:0;padding:24px}
        .card{max-width:680px;width:100%;background:rgba(255,255,255,.75);backdrop-filter:blur(18px);border:1px solid rgba(255,255,255,.8);box-shadow:0 20px 40px rgba(94,163,227,.08);border-radius:32px;padding:40px}
        a{color:#00629e;text-decoration:none;font-weight:700}
    </style>
<link rel="stylesheet" href="<?= e(site_url('assets/css/top_nav.css')) ?>"/>
</head>
<body>
    <div class="card">
        <h1>Product not found</h1>
        <p>The requested product could not be found right now.</p>
        <p><a href="<?= e(site_url('products')) ?>">Back to products</a></p>
    </div>
</body>
</html>
<?php
    exit;
}

$variants = fetch_product_variants($pdo, (int) $product['id']);
$relatedProducts = fetch_related_products($pdo, (int) $product['id'], $product['category_id'] ? (int) $product['category_id'] : null, 4);

$currentUser = function_exists('auth_current_user') ? auth_current_user($pdo) : null;
$csrf = function_exists('csrf_token') ? csrf_token() : '';
$storeEndpoint = file_exists(__DIR__ . '/api/store.php') ? 'api/store.php' : '';

$rating = max(0, min(5, (float) ($product['rating'] ?? 0)));
$ratingLabel = number_format($rating, 1);
$stock = max(0, (int) ($product['stock'] ?? 0));
$canBuy = public_product_is_purchasable($product);
$maxPurchaseQty = $canBuy ? max(1, $stock) : 0;
$stockBadgeLabel = strtoupper(public_product_stock_label($product));
$stockBadgeBg = $canBuy ? '#d1ebd1' : '#ffdad6';
$stockBadgeColor = $canBuy ? '#2e5e2e' : '#93000a';
$productBadge = trim((string) ($product['badge'] ?? ''));

$gallery = [];
$mainImage = trim((string) ($product['image'] ?? ''));
if ($mainImage !== '') {
    $gallery[] = $mainImage;
}
$productGalleryRaw = json_decode((string) ($product['gallery_json'] ?? ''), true);
if (is_array($productGalleryRaw)) {
    foreach ($productGalleryRaw as $imagePath) {
        $imagePath = trim((string) $imagePath);
        if ($imagePath !== '' && !in_array($imagePath, $gallery, true)) {
            $gallery[] = $imagePath;
        }
    }
}
$productSpecs = [];
$productSpecsRaw = json_decode((string) ($product['specs_json'] ?? ''), true);
if (is_array($productSpecsRaw)) {
    foreach ($productSpecsRaw as $spec) {
        if (!is_array($spec)) {
            continue;
        }
        $specName = trim((string) ($spec['name'] ?? ''));
        $specValue = trim((string) ($spec['value'] ?? ''));
        if ($specName !== '' && $specValue !== '') {
            $productSpecs[] = ['name' => $specName, 'value' => $specValue];
        }
    }
}
$productBenefits = [];
$productBenefitsRaw = json_decode((string) ($product['benefits_json'] ?? ''), true);
if (is_array($productBenefitsRaw)) {
    foreach ($productBenefitsRaw as $benefit) {
        $benefitText = trim(is_array($benefit) ? (string) ($benefit['text'] ?? $benefit['benefit'] ?? '') : (string) $benefit);
        if ($benefitText !== '' && !in_array($benefitText, $productBenefits, true)) {
            $productBenefits[] = $benefitText;
        }
    }
}


$variantGroups = [];
foreach ($variants as $type => $values) {
    $type = trim((string) $type);
    if ($type === '' || !is_array($values)) {
        continue;
    }
    $cleanValues = [];
    foreach ($values as $value) {
        $value = trim((string) $value);
        if ($value !== '' && !in_array($value, $cleanValues, true)) {
            $cleanValues[] = $value;
        }
    }
    if ($cleanValues !== []) {
        $variantGroups[$type] = $cleanValues;
    }
}

$pageTitle = $siteName . ' | ' . $product['name'];
$pageDescription = $product['short_description'] ?: 'Product details from Phonix Türkiye.';
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($pageDescription) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <style>
        :root{--on-primary:#ffffff;--tertiary-fixed-dim:#bec6e0;--surface-bright:#f9f9fa;--tertiary:#565e74;--surface:#f9f9fa;--on-background:#1a1c1d;--on-surface:#1a1c1d;--error:#ba1a1a;--surface-container-low:#f3f3f4;--tertiary-fixed:#dae2fd;--primary-fixed-dim:#99cbff;--surface-container-high:#e8e8e9;--surface-variant:#e2e2e3;--primary-fixed:#cfe5ff;--surface-container-lowest:#ffffff;--surface-tint:#00629e;--tertiary-container:#969db6;--secondary-fixed-dim:#c0c7cf;--primary-container:#5ea3e3;--outline:#717881;--secondary-fixed:#dce3eb;--secondary:#585f66;--surface-container:#eeeeef;--surface-container-highest:#e2e2e3;--surface-dim:#d9dadb;--background:#f9f9fa;--outline-variant:#c0c7d1;--on-surface-variant:#414750;--primary:#00629e;--secondary-container:#dce3eb;--on-primary-container:#00385c}
        *{box-sizing:border-box}html,body{margin:0;padding:0}body{background:var(--background);color:var(--on-background);font-family:Manrope,system-ui,sans-serif;min-height:100vh;display:flex;flex-direction:column;overflow-x:hidden}a{text-decoration:none;color:inherit}button,input{font:inherit}
        .material-symbols-outlined{font-variation-settings:'FILL' 0,'wght' 300,'GRAD' 0,'opsz' 24}
        .shell{width:min(1280px,calc(100% - 48px));margin:0 auto}.topbar{position:sticky;top:0;z-index:50;background:rgba(255,255,255,.4);backdrop-filter:blur(20px);border-bottom:1px solid rgba(255,255,255,.2)}
        .topbar-inner{display:flex;justify-content:space-between;align-items:center;padding:24px 0;border-bottom:1px solid rgba(255,255,255,.1);box-shadow:0 20px 40px rgba(94,163,227,.08)}
        .brand{font-size:28px;font-weight:700;letter-spacing:-.04em}.nav{display:flex;gap:32px}.nav a,.top-actions a,.top-actions button{transition:.25s ease}.nav a{color:#475569;font-weight:300;transform:scale(.95)}.nav a:hover,.top-actions a:hover,.top-actions button:hover{opacity:.8}.nav a.active{color:#2563eb;border-bottom:2px solid #3b82f6;padding-bottom:4px;font-weight:500}
        .top-actions{display:flex;align-items:center;gap:18px;color:#3b82f6}.icon-link,.icon-button{display:inline-flex;align-items:center;justify-content:center;background:none;border:0;cursor:pointer;padding:0;position:relative}.count{position:absolute;top:-8px;right:-8px;min-width:18px;height:18px;padding:0 5px;border-radius:999px;background:#00629e;color:#fff;font-size:10px;font-weight:700;display:grid;place-items:center}
        main{flex:1;padding:48px 0 72px}.breadcrumbs{display:flex;flex-wrap:wrap;align-items:center;gap:8px;color:var(--on-surface-variant);font-size:12px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;margin-bottom:24px}.breadcrumbs a:hover{color:var(--primary)}.crumb-icon{font-size:14px}.breadcrumbs .current{color:var(--primary)}
        .hero-grid{display:grid;grid-template-columns:minmax(0,7fr) minmax(360px,5fr);gap:32px;margin-bottom:120px}.gallery{display:flex;flex-direction:column;gap:24px}.glass-panel{background:rgba(255,255,255,.4);backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,.5);box-shadow:0 20px 40px rgba(94,163,227,.08)}
        .main-image{position:relative;border-radius:48px;overflow:hidden;display:flex;align-items:center;justify-content:center;padding:32px;aspect-ratio:1/1}.main-image img{width:80%;height:80%;object-fit:contain;object-position:center;filter:drop-shadow(0 24px 48px rgba(0,0,0,.14));transition:transform .5s ease}.main-image:hover img{transform:scale(1.05)}
        .image-action{position:absolute;top:16px;right:16px;width:40px;height:40px;border-radius:999px;border:0;background:rgba(255,255,255,.55);display:grid;place-items:center;cursor:pointer}.thumbs{display:grid;grid-template-columns:repeat(4,1fr);gap:8px}.thumb{border-radius:16px;overflow:hidden;display:flex;align-items:center;justify-content:center;padding:8px;aspect-ratio:1/1;cursor:pointer;opacity:.72;transition:.25s ease;border:1px solid transparent}.thumb img{width:100%;height:100%;object-fit:contain}.thumb.active{border:2px solid var(--primary);opacity:1}.thumb:hover{opacity:1;border-color:var(--outline-variant)}
        .product-panel{display:flex;flex-direction:column;padding-top:16px}.meta-row{display:flex;flex-wrap:wrap;gap:12px;align-items:center;margin-bottom:8px}.badge{display:inline-flex;align-items:center;padding:6px 12px;border-radius:999px;font-size:12px;font-weight:700;letter-spacing:.1em}.stars{display:inline-flex;align-items:center;gap:1px;color:#d4af37}.stars .material-symbols-outlined{font-size:20px;font-variation-settings:'FILL' 1}.stars .half{font-variation-settings:'FILL' .5}.rating-text{font-size:16px;color:var(--on-surface-variant);margin-left:4px}.title{font-size:40px;line-height:1.2;letter-spacing:-.01em;font-weight:600;margin:0 0 8px}.lead{font-size:18px;line-height:1.6;color:var(--on-surface-variant);margin:0 0 24px}.price-row{display:flex;align-items:flex-end;gap:16px;padding-bottom:32px;border-bottom:1px solid rgba(192,199,209,.3);margin-bottom:32px}.price-main{font-size:64px;line-height:1.1;font-weight:700;letter-spacing:-.02em;color:var(--primary)}.price-compare{font-size:24px;line-height:1.3;color:var(--on-surface-variant);text-decoration:line-through;margin-bottom:10px}
        .selector-stack{display:flex;flex-direction:column;gap:18px;margin-bottom:26px}.option-group{padding:18px;border-radius:24px;background:rgba(255,255,255,.45);border:1px solid rgba(192,199,209,.32)}.selector-title{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:12px;font-size:12px;line-height:1.2;letter-spacing:.09em;font-weight:800;color:var(--on-surface-variant);text-transform:uppercase}.selector-title strong{color:var(--on-surface);font-size:13px;letter-spacing:0;text-transform:none;font-weight:700}.option-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(118px,1fr));gap:10px}.option-btn{min-height:48px;padding:12px 14px;border-radius:16px;border:1px solid var(--outline-variant);background:#fff;color:var(--on-surface);cursor:pointer;transition:.2s ease;font-size:14px;line-height:1.25;font-weight:700;text-align:center}.option-btn:hover{border-color:rgba(0,98,158,.45);transform:translateY(-1px)}.option-btn.active{border:2px solid var(--primary);background:rgba(0,98,158,.07);color:var(--primary);box-shadow:0 10px 20px rgba(0,98,158,.08)}.product-badge{background:rgba(0,98,158,.08);color:var(--primary);border:1px solid rgba(0,98,158,.18)}.stock-note{margin:12px 0 0;color:var(--on-surface-variant);font-size:14px;line-height:1.5}.stock-note strong{color:var(--primary)}.benefit-strip{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin:22px 0 28px}.benefit-chip{display:flex;align-items:flex-start;gap:10px;min-height:58px;padding:14px 15px;border-radius:20px;background:rgba(255,255,255,.52);border:1px solid rgba(192,199,209,.34);color:var(--on-surface);font-size:14px;line-height:1.45;font-weight:700}.benefit-chip .material-symbols-outlined{flex:0 0 auto;width:24px;height:24px;border-radius:999px;display:grid;place-items:center;background:rgba(0,98,158,.1);color:var(--primary);font-size:17px}
        .swatches{display:flex;gap:16px;flex-wrap:wrap}.swatch{width:40px;height:40px;border-radius:999px;padding:4px;border:1px solid transparent;background:none;cursor:pointer;transition:.2s ease}.swatch:hover{border-color:var(--outline-variant)}.swatch.active{border:2px solid var(--primary)}.swatch-inner{width:100%;height:100%;border-radius:999px;display:block}
        .info-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:14px 18px;margin:8px 0 32px}.info-item{padding:16px 18px;border-radius:20px;background:rgba(255,255,255,.55);border:1px solid rgba(255,255,255,.45)}.info-item span{display:block;font-size:12px;font-weight:700;letter-spacing:.1em;color:var(--on-surface-variant);text-transform:uppercase;margin-bottom:6px}.info-item strong{font-size:16px;font-weight:600;color:var(--on-surface)}
        .actions{display:grid;grid-template-columns:auto minmax(180px,1fr) 56px;align-items:center;gap:14px;margin-top:auto;padding-top:8px}.qty-pill{display:flex;align-items:center;justify-content:center;border:1px solid var(--outline-variant);border-radius:999px;height:56px;min-width:148px;background:var(--surface-bright);overflow:hidden}.qty-pill button{width:48px;height:100%;display:grid;place-items:center;border:0;background:none;color:var(--on-surface);cursor:pointer}.qty-pill button:hover:not(:disabled){color:var(--primary);background:rgba(0,98,158,.05)}.qty-pill button:disabled{opacity:.35;cursor:not-allowed}.qty-display{width:42px;text-align:center;font-weight:800;font-size:16px}.primary-action{min-height:56px;border:0;border-radius:999px;background:linear-gradient(90deg,var(--primary),var(--primary-container));color:var(--on-primary);font-weight:800;display:flex;align-items:center;justify-content:center;gap:10px;cursor:pointer;box-shadow:0 20px 40px rgba(94,163,227,.14);transition:opacity .2s ease,transform .2s ease;padding:0 22px}.primary-action:hover:not(:disabled){opacity:.92;transform:translateY(-1px)}.primary-action:disabled,.is-disabled-buy{opacity:.58;cursor:not-allowed}.wishlist-action{width:56px;height:56px;border-radius:999px;border:1px solid var(--outline-variant);background:#fff;color:var(--on-surface);display:grid;place-items:center;cursor:pointer;transition:.2s ease}.wishlist-action:hover,.wishlist-action.active{color:var(--primary);border-color:var(--primary)}
        .description-section{margin-bottom:120px}.description-card{padding:32px;border-radius:32px}.description-card h2,.related-head h2{font-size:40px;line-height:1.2;letter-spacing:-.01em;font-weight:600;margin:0 0 16px}.description-card p{font-size:18px;line-height:1.7;color:var(--on-surface-variant);margin:0}.description-benefits{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin:24px 0 6px}.description-benefit{display:flex;gap:10px;align-items:flex-start;padding:15px;border-radius:20px;background:rgba(255,255,255,.48);border:1px solid rgba(192,199,209,.28);font-size:15px;line-height:1.55;font-weight:700;color:var(--on-surface)}.description-benefit .material-symbols-outlined{font-size:19px;color:var(--primary);margin-top:1px}.spec-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:28px}.spec-item{display:flex;justify-content:space-between;gap:16px;padding:16px;border-radius:20px;background:rgba(255,255,255,.42);border:1px solid rgba(192,199,209,.3)}.spec-item span{color:var(--on-surface-variant);font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.08em}.spec-item strong{text-align:right;color:var(--on-surface);font-size:14px}
        .related-head{display:flex;justify-content:space-between;align-items:end;gap:24px;margin-bottom:32px}.related-head p{margin:0;color:var(--on-surface-variant);font-size:18px}.related-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:24px}.related-card{padding:24px;border-radius:32px;display:flex;flex-direction:column;transition:transform .25s ease}.related-card:hover{transform:translateY(-6px)}.related-top{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px}.related-badge{display:inline-flex;padding:6px 12px;border-radius:999px;background:rgba(0,98,158,.08);color:var(--primary);font-size:11px;font-weight:700;letter-spacing:.1em}.related-wish{border:1px solid var(--outline-variant);background:#fff;border-radius:999px;width:40px;height:40px;display:grid;place-items:center;color:var(--on-surface);cursor:pointer}.related-image{height:240px;border-radius:24px;background:linear-gradient(180deg,transparent,rgba(249,249,250,.5));display:flex;align-items:center;justify-content:center;overflow:hidden;margin-bottom:20px}.related-image img{width:100%;height:100%;object-fit:contain;mix-blend-mode:multiply;transition:transform .5s ease}.related-card:hover .related-image img{transform:scale(1.05)}.related-brand{display:block;font-size:14px;color:var(--on-surface-variant);margin-bottom:4px}.related-title{font-size:24px;line-height:1.3;font-weight:600;margin:0 0 8px}.related-desc{font-size:15px;line-height:1.6;color:var(--on-surface-variant);margin:0 0 20px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}.related-bottom{display:flex;gap:12px;margin-top:auto}.related-price{font-size:20px;font-weight:600}.secondary-link{width:48px;height:48px;border-radius:999px;border:1px solid var(--outline-variant);display:grid;place-items:center;color:var(--on-surface-variant);background:#fff}
        footer{margin-top:auto;width:100%;padding:96px 0 48px;border-top:1px solid #f1f5f9;background:#f8fafc}.footer-grid{display:grid;grid-template-columns:2fr 1fr 1fr;gap:48px;color:#64748b;font-size:14px}.footer-brand{font-size:22px;font-weight:700;color:#0f172a;display:block;margin-bottom:16px}.footer-links{display:flex;flex-direction:column;gap:10px}.footer-links a:hover{color:#3b82f6;transform:translateX(4px)}
        .toast{position:fixed;left:50%;bottom:24px;transform:translate(-50%,20px);background:#111827;color:#fff;padding:12px 16px;border-radius:999px;font-size:14px;font-weight:600;opacity:0;pointer-events:none;transition:.25s ease;z-index:80;box-shadow:0 14px 30px rgba(0,0,0,.18)}.toast.show{opacity:1;transform:translate(-50%,0)}
        @media (max-width:1100px){.hero-grid{grid-template-columns:1fr}.product-panel{padding-top:0}.related-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.nav{display:none}}
        @media (max-width:720px){.spec-grid{grid-template-columns:1fr}.shell{width:min(100%,calc(100% - 28px))}.topbar-inner{padding:18px 0}.brand{font-size:24px}.top-actions{gap:12px}.breadcrumbs{margin-bottom:18px}.main-image{border-radius:32px;padding:18px}.thumbs{grid-template-columns:repeat(4,1fr)}.title{font-size:32px}.price-main{font-size:44px}.option-group{padding:14px;border-radius:20px}.option-grid,.info-grid,.footer-grid,.related-grid{grid-template-columns:1fr}.benefit-strip,.description-benefits{grid-template-columns:1fr}.actions{grid-template-columns:1fr}.qty-pill{width:100%;justify-content:center}.primary-action{width:100%}.wishlist-action{width:100%}.footer-grid{gap:24px}main{padding:28px 0 56px}}
    </style>
<link rel="stylesheet" href="<?= e(site_url('assets/css/top_nav.css')) ?>"/>
</head>
<body data-store-endpoint="<?= e($storeEndpoint) ?>" data-csrf="<?= e($csrf) ?>" data-product-stock="<?= (int) $maxPurchaseQty ?>">
<?php require __DIR__ . '/includes/top_nav.php'; ?>

<main>
    <div class="shell">
        <nav class="breadcrumbs" aria-label="Breadcrumbs">
            <a href="<?= e(site_url('home')) ?>">Home</a>
            <span class="material-symbols-outlined crumb-icon">chevron_right</span>
            <a href="<?= e(site_url('products')) ?>">Products</a>
            <?php if (!empty($product['category_slug'])): ?>
                <span class="material-symbols-outlined crumb-icon">chevron_right</span>
                <a href="<?= e(site_url('products', ['category' => $product['category_slug']])) ?>"><?= e($product['category_name'] ?: 'Products') ?></a>
            <?php endif; ?>
            <span class="material-symbols-outlined crumb-icon">chevron_right</span>
            <span class="current"><?= e($product['name']) ?></span>
        </nav>

        <div class="hero-grid">
            <section class="gallery">
                <div class="glass-panel main-image">
                    <img id="mainProductImage" src="<?= e($gallery[0] ?? $mainImage) ?>" alt="<?= e($product['name']) ?>">
                    <button class="image-action" type="button" aria-label="View larger image"><span class="material-symbols-outlined">fullscreen</span></button>
                </div>
                <div class="thumbs">
                    <?php foreach ($gallery as $index => $image): ?>
                        <button class="glass-panel thumb <?= $index === 0 ? 'active' : '' ?>" type="button" data-thumb="<?= e($image) ?>" aria-label="Preview image <?= $index + 1 ?>">
                            <img src="<?= e($image) ?>" alt="<?= e($product['name']) ?> preview <?= $index + 1 ?>">
                        </button>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="product-panel">
                <div class="meta-row">
                    <?php if ($productBadge !== ''): ?><span class="badge product-badge"><?= e($productBadge) ?></span><?php endif; ?>
                    <span class="badge" style="background:<?= e($stockBadgeBg) ?>;color:<?= e($stockBadgeColor) ?>"><?= e($stockBadgeLabel) ?></span>
                    <div class="stars" aria-label="Rated <?= e($ratingLabel) ?> out of 5">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <?php if ($rating >= $i): ?>
                                <span class="material-symbols-outlined">star</span>
                            <?php elseif ($rating >= ($i - 0.5)): ?>
                                <span class="material-symbols-outlined half">star_half</span>
                            <?php else: ?>
                                <span class="material-symbols-outlined" style="font-variation-settings:'FILL' 0">star</span>
                            <?php endif; ?>
                        <?php endfor; ?>
                        <span class="rating-text"><?= e($ratingLabel) ?> customer rating</span>
                    </div>
                </div>

                <h1 class="title"><?= e($product['name']) ?></h1>
                <p class="lead"><?= e($product['short_description'] ?: 'Clear product details for confident buying.') ?></p>
                <?php if ($productBenefits): ?>
                    <div class="benefit-strip" aria-label="Product benefits">
                        <?php foreach (array_slice($productBenefits, 0, 4) as $benefit): ?>
                            <div class="benefit-chip"><span class="material-symbols-outlined">check_circle</span><span><?= e($benefit) ?></span></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="price-row">
                    <span class="price-main"><?= e(format_price($product['price'], $siteCurrency)) ?></span>
                    <?php if (!empty($product['compare_price'])): ?>
                        <span class="price-compare"><?= e(format_price($product['compare_price'], $siteCurrency)) ?></span>
                    <?php endif; ?>
                </div>

                <?php if ($variantGroups): ?>
                    <div class="selector-stack" aria-label="Product options">
                        <?php foreach ($variantGroups as $type => $values): ?>
                            <?php $defaultValue = $values[0] ?? ''; ?>
                            <div class="option-group" data-option-group-wrap="<?= e($type) ?>">
                                <span class="selector-title"><?= e($type) ?> <strong data-selected-option-label="<?= e($type) ?>"><?= e($defaultValue) ?></strong></span>
                                <div class="option-grid">
                                    <?php foreach ($values as $index => $value): ?>
                                        <button class="option-btn <?= $index === 0 ? 'active' : '' ?>" type="button" data-option-group="<?= e($type) ?>" data-option-value="<?= e($value) ?>"><?= e($value) ?></button>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="info-grid">
                    <div class="info-item"><span>Brand</span><strong><?= e($product['brand'] ?: 'Phonix') ?></strong></div>
                    <div class="info-item"><span>SKU</span><strong><?= e($product['sku'] ?: 'N/A') ?></strong></div>
                    <div class="info-item"><span>Stock</span><strong><?= (int) $stock ?></strong></div>
                    <div class="info-item"><span>Category</span><strong><?= e($product['category_name'] ?: 'Products') ?></strong></div>
                </div>

                <div class="actions">
                    <div class="qty-pill" aria-label="Quantity selector">
                        <button type="button" id="qtyMinus" aria-label="Decrease quantity"><span class="material-symbols-outlined">remove</span></button>
                        <span class="qty-display" id="qtyDisplay">1</span>
                        <button type="button" id="qtyPlus" aria-label="Increase quantity"><span class="material-symbols-outlined">add</span></button>
                    </div>
                    <button class="primary-action <?= $canBuy ? '' : 'is-disabled-buy' ?>" type="button" id="productAddToCart" data-product-id="<?= (int) $product['id'] ?>" data-product-name="<?= e($product['name']) ?>" <?= $canBuy ? '' : 'disabled aria-disabled="true"' ?>>
                        <span class="material-symbols-outlined" style="font-variation-settings:'FILL' 1">shopping_cart</span>
                        <span><?= $canBuy ? 'Add to Cart' : 'Sold Out' ?></span>
                    </button>
                    <button class="wishlist-action" type="button" id="productWishlistToggle" data-product-id="<?= (int) $product['id'] ?>" aria-pressed="false" aria-label="Save to wishlist">
                        <span class="material-symbols-outlined">favorite</span>
                    </button>
                </div>
                <?php if ($canBuy): ?><p class="stock-note" id="stockLimitNote">Maximum <?= (int) $maxPurchaseQty ?> available for this product.</p><?php endif; ?>
            </section>
        </div>

        <section class="description-section">
            <div class="glass-panel description-card">
                <h2>Description</h2>
                <p><?= nl2br(e($product['description'] ?: $product['short_description'])) ?></p>
                <?php if ($productSpecs): ?>
                    <div class="spec-grid">
                        <?php foreach ($productSpecs as $spec): ?>
                            <div class="spec-item"><span><?= e($spec['name']) ?></span><strong><?= e($spec['value']) ?></strong></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <?php if ($relatedProducts): ?>
            <section>
                <div class="related-head">
                    <div>
                        <h2>Related products</h2>
                        <p>More products you may like.</p>
                    </div>
                </div>
                <div class="related-grid">
                    <?php foreach ($relatedProducts as $item): ?>
                        <?php $relatedCanBuy = public_product_is_purchasable($item); ?>
                        <article class="glass-panel related-card">
                            <div class="related-top">
                                <span class="related-badge"><?= e(public_product_badge($item, (string) ($item['brand'] ?: 'Related'))) ?></span>
                                <button class="related-wish js-related-wish" type="button" data-product-id="<?= (int) $item['id'] ?>" aria-label="Save <?= e($item['name']) ?> to wishlist">♡</button>
                            </div>
                            <a class="related-image" href="<?= e(site_url('product', ['slug' => $item['slug']])) ?>">
                                <img src="<?= e($item['image']) ?>" alt="<?= e($item['name']) ?>">
                            </a>
                            <span class="related-brand"><?= e($item['brand'] ?: 'Phonix') ?></span>
                            <h3 class="related-title"><?= e($item['name']) ?></h3>
                            <p class="related-desc"><?= e($item['short_description'] ?: 'Featured product.') ?></p>
                            <div class="related-bottom">
                                <button class="primary-action <?= $relatedCanBuy ? 'js-related-cart' : 'is-disabled-buy' ?>" type="button" data-product-id="<?= (int) $item['id'] ?>" data-product-name="<?= e($item['name']) ?>" <?= $relatedCanBuy ? '' : 'disabled aria-disabled="true"' ?>>
                                    <span><?= $relatedCanBuy ? 'Add' : 'Sold Out' ?></span>
                                </button>
                                <a class="secondary-link" href="<?= e(site_url('product', ['slug' => $item['slug']])) ?>" aria-label="View <?= e($item['name']) ?>">
                                    <span class="material-symbols-outlined">visibility</span>
                                </a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </div>
</main>

<footer>
    <div class="shell footer-grid">
        <div>
            <span class="footer-brand"><?= e($siteName) ?></span>
            <p>© <?= date('Y') ?> <?= e($siteName) ?> Electronics. <?= e(store_setting($siteSettings ?? [], 'footer_tagline', 'Official phones, trusted warranty, fast delivery across Turkey.')) ?></p>
        </div>
        <div class="footer-links">
            <a href="<?= e(site_url('support')) ?>">Support</a>
            <a href="#">Privacy Policy</a>
            <a href="#">Terms of Service</a>
        </div>
        <div class="footer-links">
            <a href="<?= e(site_url('products')) ?>">All products</a>
            <a href="<?= e(site_url('brands')) ?>">Brands</a>
            <a href="<?= e(site_url('checkout')) ?>">Checkout</a>
        </div>
    </div>
</footer>

<div class="toast" id="pageToast" aria-live="polite"></div>

<script>
(function () {
    const body = document.body;
    const endpoint = body.dataset.storeEndpoint || '';
    const csrf = body.dataset.csrf || '';
    const toast = document.getElementById('pageToast');
    const cartCountNodes = Array.from(document.querySelectorAll('.js-cart-count'));
    const wishlistCountNodes = Array.from(document.querySelectorAll('.js-wishlist-count'));
    const mainWishlistBtn = document.getElementById('productWishlistToggle');
    const mainAddBtn = document.getElementById('productAddToCart');
    const qtyDisplay = document.getElementById('qtyDisplay');
    const qtyMinus = document.getElementById('qtyMinus');
    const qtyPlus = document.getElementById('qtyPlus');
    const productId = Number(mainAddBtn?.dataset.productId || 0);
    const maxQty = Math.max(0, Number(body.dataset.productStock || 0));
    let qty = 1;
    let state = { cart: { items: [], count: 0 }, wishlist: { ids: [], count: 0 } };

    function showToast(message) {
        if (!toast) return;
        toast.textContent = message;
        toast.classList.add('show');
        clearTimeout(window.__productToastTimer);
        window.__productToastTimer = setTimeout(() => toast.classList.remove('show'), 1800);
    }

    function getLocalCart() {
        try { return JSON.parse(localStorage.getItem('phonix-cart-db') || '[]'); } catch (e) { return []; }
    }

    function setLocalCart(items) {
        localStorage.setItem('phonix-cart-db', JSON.stringify(items));
    }

    function getLocalWishlist() {
        try { return JSON.parse(localStorage.getItem('phonix-wishlist-db') || '[]'); } catch (e) { return []; }
    }

    function setLocalWishlist(items) {
        localStorage.setItem('phonix-wishlist-db', JSON.stringify(items));
    }

    function syncCounts() {
        const cartCount = Number(state.cart?.count || 0);
        const wishCount = Number(state.wishlist?.count || 0);
        cartCountNodes.forEach(node => node.textContent = String(cartCount));
        wishlistCountNodes.forEach(node => node.textContent = String(wishCount));
    }

    function syncWishlistButtons() {
        const ids = Array.isArray(state.wishlist?.ids) ? state.wishlist.ids.map(Number) : [];
        const active = ids.includes(productId);
        if (mainWishlistBtn) {
            mainWishlistBtn.classList.toggle('active', active);
            mainWishlistBtn.setAttribute('aria-pressed', active ? 'true' : 'false');
            const icon = mainWishlistBtn.querySelector('.material-symbols-outlined');
            if (icon) {
                icon.style.fontVariationSettings = active ? "'FILL' 1" : "'FILL' 0";
            }
        }
        document.querySelectorAll('.js-related-wish').forEach((button) => {
            const id = Number(button.dataset.productId || 0);
            button.textContent = ids.includes(id) ? '♥' : '♡';
        });
    }

    function applyLocalState() {
        const cart = getLocalCart();
        const wishlistIds = getLocalWishlist().map(Number).filter(Boolean);
        state = {
            cart: { items: cart, count: cart.reduce((sum, item) => sum + Number(item.qty || 1), 0) },
            wishlist: { ids: wishlistIds, count: wishlistIds.length }
        };
        syncCounts();
        syncWishlistButtons();
    }

    async function fetchState() {
        const res = await fetch(endpoint, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
        const payload = await res.json();
        if (!res.ok || !payload.ok) throw new Error(payload.message || 'Could not load state.');
        state = payload.state || state;
        syncCounts();
        syncWishlistButtons();
    }

    async function apiPost(action, payload) {
        const res = await fetch(endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-Token': csrf
            },
            body: JSON.stringify(Object.assign({ _csrf: csrf, action }, payload || {}))
        });
        const data = await res.json();
        if (!res.ok || !data.ok) throw new Error(data.message || 'Action failed.');
        state = data.state || state;
        syncCounts();
        syncWishlistButtons();
        return data;
    }

    function setQty(next) {
        const limit = maxQty > 0 ? maxQty : 1;
        qty = Math.max(1, Math.min(limit, Number(next) || 1));
        if (qtyDisplay) qtyDisplay.textContent = String(qty);
        if (qtyMinus) qtyMinus.disabled = qty <= 1;
        if (qtyPlus) qtyPlus.disabled = maxQty > 0 && qty >= maxQty;
    }

    function selectedOptions() {
        const options = {};
        document.querySelectorAll('[data-option-group].active').forEach((button) => {
            const group = String(button.dataset.optionGroup || '').trim();
            const value = String(button.dataset.optionValue || '').trim();
            if (group && value) options[group] = value;
        });
        return options;
    }

    document.querySelectorAll('[data-thumb]').forEach((button) => {
        button.addEventListener('click', () => {
            const target = button.dataset.thumb;
            const main = document.getElementById('mainProductImage');
            if (main && target) main.src = target;
            document.querySelectorAll('[data-thumb]').forEach((item) => item.classList.remove('active'));
            button.classList.add('active');
        });
    });

    document.querySelectorAll('[data-option-group]').forEach((button) => {
        button.addEventListener('click', () => {
            const group = button.dataset.optionGroup || '';
            document.querySelectorAll('[data-option-group]').forEach((item) => {
                if ((item.dataset.optionGroup || '') === group) item.classList.remove('active');
            });
            button.classList.add('active');
            document.querySelectorAll('[data-selected-option-label]').forEach((label) => {
                if ((label.dataset.selectedOptionLabel || '') === group) label.textContent = String(button.dataset.optionValue || '');
            });
        });
    });

    setQty(1);
    qtyMinus?.addEventListener('click', () => setQty(qty - 1));
    qtyPlus?.addEventListener('click', () => {
        if (maxQty > 0 && qty >= maxQty) {
            showToast('Maximum available quantity reached.');
            return;
        }
        setQty(qty + 1);
    });

    mainAddBtn?.addEventListener('click', async () => {
        const name = mainAddBtn.dataset.productName || 'Product';
        try {
            if (endpoint && csrf) {
                const payload = await apiPost('add_cart', { product_id: productId, qty, selected_options: selectedOptions() });
                showToast(payload.message || (name + ' added to cart.'));
            } else {
                const cart = getLocalCart();
                const options = selectedOptions();
                const optionKey = JSON.stringify(options);
                const existing = cart.find((item) => Number(item.id) === productId && JSON.stringify(item.selected_options || {}) === optionKey);
                if (existing) existing.qty = Number(existing.qty || 0) + qty;
                else cart.push({ id: productId, qty, name, selected_options: options });
                setLocalCart(cart);
                applyLocalState();
                showToast(name + ' added to cart.');
            }
        } catch (error) {
            showToast(error.message || 'Could not add this product.');
        }
    });

    mainWishlistBtn?.addEventListener('click', async () => {
        try {
            if (endpoint && csrf) {
                const payload = await apiPost('toggle_wishlist', { product_id: productId });
                showToast(payload.message || 'Wishlist updated.');
            } else {
                const wishlist = getLocalWishlist();
                const index = wishlist.indexOf(productId);
                if (index >= 0) wishlist.splice(index, 1); else wishlist.unshift(productId);
                setLocalWishlist(wishlist);
                applyLocalState();
                showToast(index >= 0 ? 'Removed from wishlist.' : 'Saved to wishlist.');
            }
        } catch (error) {
            showToast(error.message || 'Could not update wishlist.');
        }
    });

    document.querySelectorAll('.js-related-cart').forEach((button) => {
        button.addEventListener('click', async () => {
            const id = Number(button.dataset.productId || 0);
            const name = button.dataset.productName || 'Product';
            if (!id) return;
            try {
                if (endpoint && csrf) {
                    const payload = await apiPost('add_cart', { product_id: id, qty: 1 });
                    showToast(payload.message || (name + ' added to cart.'));
                } else {
                    const cart = getLocalCart();
                    const existing = cart.find((item) => Number(item.id) === id);
                    if (existing) existing.qty = Number(existing.qty || 0) + 1;
                    else cart.push({ id, qty: 1, name });
                    setLocalCart(cart);
                    applyLocalState();
                    showToast(name + ' added to cart.');
                }
            } catch (error) {
                showToast(error.message || 'Could not add this product.');
            }
        });
    });

    document.querySelectorAll('.js-related-wish').forEach((button) => {
        button.addEventListener('click', async () => {
            const id = Number(button.dataset.productId || 0);
            if (!id) return;
            try {
                if (endpoint && csrf) {
                    const payload = await apiPost('toggle_wishlist', { product_id: id });
                    showToast(payload.message || 'Wishlist updated.');
                } else {
                    const wishlist = getLocalWishlist();
                    const index = wishlist.indexOf(id);
                    if (index >= 0) wishlist.splice(index, 1); else wishlist.unshift(id);
                    setLocalWishlist(wishlist);
                    applyLocalState();
                    showToast(index >= 0 ? 'Removed from wishlist.' : 'Saved to wishlist.');
                }
            } catch (error) {
                showToast(error.message || 'Could not update wishlist.');
            }
        });
    });

    if (endpoint && csrf) {
        fetchState().catch(applyLocalState);
    } else {
        applyLocalState();
    }
})();
</script>
</body>
</html>
