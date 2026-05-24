<?php
require __DIR__ . '/includes/bootstrap.php';

function homepage_setting(array $settings, string $key, string $default = ''): string {
    $value = trim((string)($settings[$key] ?? ''));
    return $value !== '' ? $value : $default;
}

function fetch_homepage_slots_public(PDO $pdo): array {
    try {
        store_ensure_public_product_columns($pdo);
        $visibleSql = store_public_product_visibility_sql('p');
        $rows = $pdo->query('SELECT s.slot_key, s.title_override, s.subtitle_override, s.badge_override, s.sort_order, s.is_active, p.*
                             FROM homepage_featured_slots s
                             LEFT JOIN products p ON p.id = s.product_id AND ' . $visibleSql . '
                             WHERE s.is_active = 1
                             ORDER BY s.sort_order ASC, s.id ASC')->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
    $slots = [];
    foreach ($rows as $row) {
        $slots[(string)$row['slot_key']] = $row;
    }
    return $slots;
}
function fetch_homepage_banners_public(PDO $pdo): array {
    try {
        return $pdo->query("SELECT * FROM homepage_banners WHERE is_active = 1 ORDER BY sort_order ASC, id DESC LIMIT 3")->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function fetch_site_page_public(PDO $pdo, string $pageKey): ?array {
    try {
        $stmt = $pdo->prepare('SELECT * FROM site_pages WHERE page_key = :page_key AND is_active = 1 LIMIT 1');
        $stmt->execute(['page_key' => $pageKey]);
        $page = $stmt->fetch();
        return $page ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function homepage_slot_product(array $slots, string $key, ?array $fallback): ?array {
    $slot = $slots[$key] ?? null;
    return !empty($slot['id']) ? $slot : $fallback;
}

function homepage_slot_text(array $slots, string $key, string $field, string $fallback): string {
    $slot = $slots[$key] ?? [];
    $value = trim((string)($slot[$field] ?? ''));
    return $value !== '' ? $value : $fallback;
}

function homepage_slot_public_text(array $slots, string $key, string $field, string $fallback): string {
    $value = homepage_slot_text($slots, $key, $field, '');
    $legacyPlaceholders = [
        'Hero product',
        'Audio spotlight',
        'Accessories spotlight',
        'Main hero product on the homepage',
        'Secondary audio product card',
        'Secondary watch/wearable product card',
        'Flagship',
        'Immersive Audio',
        'Accessories',
    ];
    if ($value === '' || in_array($value, $legacyPlaceholders, true)) {
        return $fallback;
    }
    return $value;
}

function homepage_product_image(?array $product, string $fallback): string {
    $image = trim((string)($product['image'] ?? ''));
    return $image !== '' ? $image : $fallback;
}

function homepage_public_url(string $value, string $fallback): string {
    $value = trim($value);
    return $value !== '' ? $value : $fallback;
}

$homepageSlots = fetch_homepage_slots_public($pdo);
$homepageBanners = fetch_homepage_banners_public($pdo);
$homepageBanners = array_values(array_filter($homepageBanners, static function (array $banner): bool {
    $haystack = strtolower(trim(implode(' ', [
        (string)($banner['eyebrow'] ?? ''),
        (string)($banner['title'] ?? ''),
        (string)($banner['subtitle'] ?? ''),
        (string)($banner['cta_label'] ?? ''),
    ])));
    foreach ([
        'trade in your old phone',
        'old phone',
        'smarter upgrade path',
        'device assessment',
        'bundle deals',
        'student-ready phone bundles',
        'student ready phone bundles',
        'shop accessories',
    ] as $blockedPromo) {
        if ($blockedPromo !== '' && str_contains($haystack, $blockedPromo)) {
            return false;
        }
    }
    return true;
}));
$homePageSeo = fetch_site_page_public($pdo, 'home');

$fallbackHeroProduct = fetch_product_by_slug($pdo, 'samsung-galaxy-s24-ultra') ?: fetch_product_by_slug($pdo, 'apple-iphone-15-pro-max');
$fallbackAudioProduct = fetch_product_by_slug($pdo, 'apple-airpods-pro-usb-c') ?: fetch_product_by_slug($pdo, 'samsung-25w-usb-c-charger');
$fallbackWatchProduct = fetch_product_by_slug($pdo, 'apple-iphone-15') ?: fetch_product_by_slug($pdo, 'galaxy-watch-6-classic');
$heroProduct = homepage_slot_product($homepageSlots, 'hero', $fallbackHeroProduct);
$audioProduct = homepage_slot_product($homepageSlots, 'audio', $fallbackAudioProduct);
$watchProduct = homepage_slot_product($homepageSlots, 'watch', $fallbackWatchProduct);
$featuredProducts = array_values(array_filter([$heroProduct, $audioProduct, $watchProduct]));

$pageTitle = trim((string)($homePageSeo['meta_title'] ?? '')) ?: homepage_setting($siteSettings, 'site_meta_title', $siteName . ' | Phones, iPhone, Android & Accessories');
$pageDescription = trim((string)($homePageSeo['meta_description'] ?? '')) ?: homepage_setting($siteSettings, 'site_meta_description', homepage_setting($siteSettings, 'homepage_subtitle', 'Shop original smartphones and phone accessories in Turkey with clear warranty support, secure checkout, and fast delivery.'));
$pageOgImage = trim((string)($homePageSeo['og_image'] ?? '')) ?: homepage_setting($siteSettings, 'site_default_og_image', '');
$pageRobots = ((int)($homePageSeo['robots_index'] ?? 1) === 1) ? homepage_setting($siteSettings, 'site_robots_policy', 'index,follow') : 'noindex,nofollow';

$heroEyebrow = homepage_setting($siteSettings, 'homepage_eyebrow', 'TURKEY SMARTPHONE MARKETPLACE');
$heroTitle1 = homepage_setting($siteSettings, 'homepage_title_line_1', 'Phones for Turkey.');
$heroTitle2 = homepage_setting($siteSettings, 'homepage_title_line_2', 'Ready to ship.');
$heroSubtitle = homepage_setting($siteSettings, 'homepage_subtitle', 'Shop iPhone, Samsung, Xiaomi, Honor, vivo, and essential accessories with clear warranty status, secure checkout, installment-friendly prices, and fast delivery across Turkey.');
$heroPrimaryLabel = homepage_setting($siteSettings, 'homepage_primary_cta_label', 'Shop Phones');
$heroPrimaryUrl = homepage_public_url(homepage_setting($siteSettings, 'homepage_primary_cta_url', ''), $heroProduct ? site_url('product', ['slug' => (string) $heroProduct['slug']]) : site_url('products'));
$heroSecondaryLabel = homepage_setting($siteSettings, 'homepage_secondary_cta_label', 'View Deals');
$heroSecondaryUrl = homepage_public_url(homepage_setting($siteSettings, 'homepage_secondary_cta_url', ''), site_url('new_arrivals'));
$heroTrustText = homepage_setting($siteSettings, 'homepage_trust_text', 'Trusted by phone buyers across Turkey');
$heroFallbackImage = 'https://lh3.googleusercontent.com/aida-public/AB6AXuC1PEt4RC7UUqPI2WbpJyruOIrB72oAU0yL7574mpOCVFkj35y_yBV5TypQpRaa88OPFhjIXdfUEawObQiVpBwN2MsK8tHdtL7WZWjnVnzyMAYkRi1fmZBZUKII8nH80HBtBX52VBRk6Dj355wELT58Qcj5v08GFCTGjXrte8vkE7_o55RmkX81FZ6WxqlsLkTEb8BCs4EwVUjvVgjUpnyhzf2F3qrPiWOsiHxKW5HNH-g-f11YeYWJFVKzwHacH1EZBAXyayyDtcA';
$heroImageSetting = homepage_setting($siteSettings, 'homepage_hero_image', '');
$heroImage = $heroImageSetting !== '' ? $heroImageSetting : homepage_product_image($heroProduct, 'assets/images/samsung-galaxy-s24-ultra.svg');

$cartSummary = cart_summary_from_items(fetch_cart_items($pdo));
$wishlistIds = fetch_wishlist_ids($pdo);
$accountUrl = $currentUser ? site_url('account') : site_url('auth', ['mode' => 'signin']);
function product_link(?array $product): string {
    return $product ? site_url('product', ['slug' => (string) $product['slug']]) : site_url('products');
}

function category_link(string $slug): string {
    return site_url('products', ['category' => $slug]);
}

function product_badge_text(?array $product, string $fallback): string {
    $badge = trim((string)($product['badge'] ?? ''));
    return $badge !== '' ? $badge : $fallback;
}

function product_desc(?array $product, string $fallback): string {
    $desc = trim((string)($product['short_description'] ?? ''));
    return $desc !== '' ? $desc : $fallback;
}

function product_price_text(?array $product, string $currency): string {
    return $product ? format_price($product['price'], $currency) : format_price(0, 'TRY');
}

function product_id(?array $product): int {
    return (int)($product['id'] ?? 0);
}

function product_name(?array $product, string $fallback): string {
    $name = trim((string)($product['name'] ?? ''));
    return $name !== '' ? $name : $fallback;
}

function product_can_buy(?array $product): bool {
    return $product ? public_product_is_purchasable($product) : false;
}

function product_rating_count(?array $product, int $fallback): int {
    $rating = (float)($product['rating'] ?? 0);
    if ($rating >= 4.9) return 128;
    if ($rating >= 4.8) return 85;
    if ($rating >= 4.7) return 342;
    return $fallback;
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title><?= e($pageTitle) ?></title>
<meta name="description" content="<?= e($pageDescription) ?>"/>
<meta name="robots" content="<?= e($pageRobots) ?>"/>
<?php if ($pageOgImage !== ""): ?><meta property="og:image" content="<?= e($pageOgImage) ?>"/><?php endif; ?>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@300;400;500;600;700;800&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<style>
@keyframes float {
0%, 100% { transform: translateY(0px); }
50% { transform: translateY(-20px); }
}
@keyframes float-slow {
0%, 100% { transform: translateY(0px) rotate(0deg); }
50% { transform: translateY(-10px) rotate(1deg); }
}
@keyframes gradient-xy {
0%, 100% { background-position: 0% 50%; }
50% { background-position: 100% 50%; }
}
@keyframes pulse-slow {
0%, 100% { opacity: 0.4; transform: scale(1); }
50% { opacity: 0.7; transform: scale(1.1); }
}
.animate-float { animation: float 6s ease-in-out infinite; }
.animate-float-slow { animation: float-slow 8s ease-in-out infinite; }
.animate-gradient-mesh {
background-size: 200% 200%;
animation: gradient-xy 15s ease infinite;
}
.animate-pulse-slow { animation: pulse-slow 10s ease-in-out infinite; }
.glass-hero {
background: rgba(255, 255, 255, 0.4);
backdrop-filter: blur(24px);
-webkit-backdrop-filter: blur(24px);
border: 1px solid rgba(255, 255, 255, 0.7);
}
.glass-footer {
background: rgba(255, 255, 255, 0.6);
backdrop-filter: blur(32px);
-webkit-backdrop-filter: blur(32px);
border-top: 1px solid rgba(255, 255, 255, 0.8);
}
.hover-underline-animation {
display: inline-block;
position: relative;
}
.hover-underline-animation::after {
content: '';
position: absolute;
width: 100%;
transform: scaleX(0);
height: 2px;
bottom: -2px;
left: 0;
background-color: currentColor;
transform-origin: bottom right;
transition: transform 0.4s cubic-bezier(0.86, 0, 0.07, 1);
}
.hover-underline-animation:hover::after {
transform: scaleX(1);
transform-origin: bottom left;
}
.toast{position:fixed;left:50%;bottom:24px;transform:translateX(-50%) translateY(20px);opacity:0;transition:.25s ease;z-index:200;background:#111827;color:#fff;padding:12px 18px;border-radius:9999px;box-shadow:0 10px 30px rgba(0,0,0,.2);font-size:14px}
.toast.show{opacity:1;transform:translateX(-50%) translateY(0)}
</style>
<script id="tailwind-config">
tailwind.config = {
darkMode: "class",
theme: {
extend: {
"colors": {
"on-primary": "#ffffff",
"tertiary-fixed-dim": "#bec6e0",
"surface-bright": "#f9f9fa",
"tertiary": "#565e74",
"on-error-container": "#93000a",
"on-secondary-container": "#5e656c",
"surface": "#f9f9fa",
"on-background": "#1a1c1d",
"on-secondary-fixed": "#151c22",
"on-surface": "#1a1c1d",
"error": "#ba1a1a",
"on-primary-fixed": "#001d34",
"surface-container-low": "#f3f3f4",
"on-tertiary-fixed": "#131b2e",
"error-container": "#ffdad6",
"tertiary-fixed": "#dae2fd",
"primary-fixed-dim": "#99cbff",
"surface-container-high": "#e8e8e9",
"on-tertiary-fixed-variant": "#3f465c",
"surface-variant": "#e2e2e3",
"primary-fixed": "#cfe5ff",
"inverse-on-surface": "#f0f1f2",
"surface-container-lowest": "#ffffff",
"on-tertiary-container": "#2d3549",
"on-secondary-fixed-variant": "#40484e",
"surface-tint": "#00629e",
"on-primary-fixed-variant": "#004a78",
"tertiary-container": "#969db6",
"secondary-fixed-dim": "#c0c7cf",
"on-error": "#ffffff",
"inverse-primary": "#99cbff",
"primary-container": "#5ea3e3",
"outline": "#717881",
"secondary-fixed": "#dce3eb",
"secondary": "#585f66",
"on-secondary": "#ffffff",
"surface-container": "#eeeeef",
"surface-container-highest": "#e2e2e3",
"surface-dim": "#d9dadb",
"on-tertiary": "#ffffff",
"background": "#f9f9fa",
"outline-variant": "#c0c7d1",
"on-surface-variant": "#414750",
"inverse-surface": "#2f3132",
"primary": "#00629e",
"secondary-container": "#dce3eb",
"on-primary-container": "#00385c"
},
"borderRadius": {
"DEFAULT": "1rem",
"lg": "2rem",
"xl": "3rem",
"full": "9999px"
},
"spacing": {
"section-padding": "120px",
"element-gap": "24px",
"unit": "8px",
"gutter": "32px",
"container-max": "1280px"
},
"fontFamily": {
"body-lg": ["Manrope"],
"label-caps": ["Manrope"],
"h3": ["Manrope"],
"body-md": ["Manrope"],
"h1": ["Manrope"],
"h2": ["Manrope"]
},
"fontSize": {
"body-lg": ["18px", {"lineHeight": "1.6", "fontWeight": "400"}],
"label-caps": ["12px", {"lineHeight": "1", "letterSpacing": "0.1em", "fontWeight": "700"}],
"h3": ["24px", {"lineHeight": "1.3", "fontWeight": "600"}],
"body-md": ["16px", {"lineHeight": "1.6", "fontWeight": "400"}],
"h1": ["72px", {"lineHeight": "1.05", "letterSpacing": "-0.04em", "fontWeight": "800"}],
"h2": ["40px", {"lineHeight": "1.2", "letterSpacing": "-0.01em", "fontWeight": "600"}]
}
},
},
}
</script>
<link rel="stylesheet" href="<?= e(site_url('assets/css/top_nav.css')) ?>"/>
<link rel="stylesheet" href="<?= e(site_url('assets/css/home_cosmic.css')) ?>"/>
</head>
<body class="home-cosmic-body bg-background text-on-background font-body-md antialiased selection:bg-primary-container selection:text-on-primary-container overflow-x-hidden" data-store-endpoint="<?= e(site_url('api_store')) ?>" data-csrf="<?= e(csrf_token()) ?>">
<?php $topNavSpacer = false; require __DIR__ . '/includes/top_nav.php'; unset($topNavSpacer); ?>
<main class="relative">
<section class="home-cosmic-hero" data-home-cosmos>
<canvas class="home-cosmic-canvas" data-home-cosmic-canvas aria-hidden="true"></canvas>
<div class="home-cosmic-aurora" aria-hidden="true"></div>
<div class="home-cosmic-noise" aria-hidden="true"></div>
<div class="home-cosmic-shell">
<div class="home-cosmic-copy" data-home-reveal>
<div class="home-cosmic-kicker"><span></span><?= e($heroEyebrow) ?></div>
<h1 class="home-cosmic-title"><span><?= e($heroTitle1) ?></span><strong><?= e($heroTitle2) ?></strong></h1>
<p class="home-cosmic-subtitle"><?= e($heroSubtitle) ?></p>
<div class="home-cosmic-actions">
<a class="home-cosmic-btn home-cosmic-btn-primary" href="<?= e($heroPrimaryUrl) ?>"><span><?= e($heroPrimaryLabel) ?></span><span class="material-symbols-outlined">arrow_forward</span></a>
<a class="home-cosmic-btn home-cosmic-btn-ghost" href="<?= e(site_url('phone-finder.php')) ?>"><span class="material-symbols-outlined">auto_awesome</span><span>Find My Phone</span></a>
<a class="home-cosmic-orbit-link" href="<?= e($heroSecondaryUrl) ?>"><?= e($heroSecondaryLabel) ?></a>
</div>
<div class="home-cosmic-metrics" aria-label="Store highlights">
<div><b>5G</b><span>ready phones</span></div>
<div><b>TR</b><span>local delivery</span></div>
<div><b>Smart</b><span>guided picks</span></div>
</div>
</div>
<div class="home-orbit-stage" data-home-reveal>
<div class="home-orbit-radar" aria-hidden="true">
<span class="home-orbit-dot home-orbit-dot-a"></span>
<span class="home-orbit-dot home-orbit-dot-b"></span>
<span class="home-orbit-dot home-orbit-dot-c"></span>
</div>
<div class="home-orbit-brands" aria-hidden="true">
<span class="home-brand-orbit brand-orbit-a"><span class="home-brand-chip home-brand-chip-apple">Apple</span></span>
<span class="home-brand-orbit brand-orbit-b"><span class="home-brand-chip home-brand-chip-samsung">Samsung</span></span>
<span class="home-brand-orbit brand-orbit-c"><span class="home-brand-chip home-brand-chip-xiaomi">mi</span></span>
<span class="home-brand-orbit brand-orbit-d"><span class="home-brand-chip home-brand-chip-honor">HONOR</span></span>
<span class="home-brand-orbit brand-orbit-e"><span class="home-brand-chip home-brand-chip-oppo">OPPO</span></span>
<span class="home-brand-orbit brand-orbit-f"><span class="home-brand-chip home-brand-chip-vivo">vivo</span></span>
<span class="home-brand-orbit brand-orbit-g"><span class="home-brand-chip home-brand-chip-huawei">Huawei</span></span>
</div>
<div class="home-orbit-label home-orbit-label-top">CAMERA</div>
<div class="home-orbit-label home-orbit-label-right">WARRANTY</div>
<div class="home-orbit-label home-orbit-label-left">BATTERY</div>
<a class="home-cosmic-relic" href="<?= e(product_link($heroProduct)) ?>" aria-label="Explore featured orbit">
<span class="home-relic-ring home-relic-ring-a" aria-hidden="true"></span>
<span class="home-relic-ring home-relic-ring-b" aria-hidden="true"></span>
<span class="home-relic-ring home-relic-ring-c" aria-hidden="true"></span>
<span class="home-relic-core" aria-hidden="true">
<span class="home-relic-glow"></span>
<span class="home-relic-symbol material-symbols-outlined">auto_awesome</span>
<span class="home-relic-pulse home-relic-pulse-a"></span>
<span class="home-relic-pulse home-relic-pulse-b"></span>
<span class="home-relic-pulse home-relic-pulse-c"></span>
</span>
<span class="home-relic-comet home-relic-comet-a" aria-hidden="true"></span>
<span class="home-relic-comet home-relic-comet-b" aria-hidden="true"></span>
</a>
<div class="home-floating-card home-floating-card-price">
<span>Current orbit</span>
<strong><?= e(product_price_text($heroProduct, $siteCurrency)) ?></strong>
</div>
<div class="home-floating-card home-floating-card-ai">
<span class="material-symbols-outlined">psychology</span>
<div><strong>Phone Match</strong><small>choose specs, ask availability</small></div>
</div>
<div class="home-floating-card home-floating-card-stock">
<span class="material-symbols-outlined">verified</span>
<div><strong><?= $heroProduct && product_can_buy($heroProduct) ? 'Available' : 'Ask us' ?></strong><small>clear buying status</small></div>
</div>
</div>
</div>
<div class="home-cosmic-scroll" aria-hidden="true"><span></span><em>Explore the orbit</em></div>
</section>
<section class="home-need-section max-w-container-max mx-auto px-gutter mb-section-padding pt-24">
<div class="text-center mb-16"><h2 class="font-h2 text-h2 text-on-surface mb-4">Shop by Phone Need</h2><p class="font-body-md text-on-surface-variant max-w-2xl mx-auto">Choose by platform, brand, or essential accessories for the Turkish market.</p></div>
<div class="grid grid-cols-2 md:grid-cols-4 gap-8">
<a class="group relative bg-white/70 backdrop-blur-md rounded-3xl p-8 shadow-sm border border-white hover:shadow-2xl transition-all duration-500 overflow-hidden text-center block hover:-translate-y-2" href="<?= e(site_url('products')) ?>"><div class="absolute inset-0 bg-gradient-to-b from-primary-fixed/0 to-primary-fixed/20 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div><div class="w-20 h-20 mx-auto bg-primary-fixed/50 rounded-2xl flex items-center justify-center mb-6 group-hover:scale-110 group-hover:rotate-6 transition-transform duration-500"><span class="material-symbols-outlined text-4xl text-primary">smartphone</span></div><h3 class="font-h3 text-xl text-on-surface mb-2">All Phones</h3><span class="text-sm text-primary font-bold opacity-0 group-hover:opacity-100 transition-opacity duration-300">Shop Now →</span></a>
<a class="group relative bg-white/70 backdrop-blur-md rounded-3xl p-8 shadow-sm border border-white hover:shadow-2xl transition-all duration-500 overflow-hidden text-center block hover:-translate-y-2" href="<?= e(category_link('iphone')) ?>"><div class="absolute inset-0 bg-gradient-to-b from-tertiary-fixed/0 to-tertiary-fixed/20 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div><div class="w-20 h-20 mx-auto bg-tertiary-fixed/50 rounded-2xl flex items-center justify-center mb-6 group-hover:scale-110 group-hover:-rotate-6 transition-transform duration-500"><span class="material-symbols-outlined text-4xl text-tertiary">phone_iphone</span></div><h3 class="font-h3 text-xl text-on-surface mb-2">iPhone</h3><span class="text-sm text-tertiary font-bold opacity-0 group-hover:opacity-100 transition-opacity duration-300">Shop Now →</span></a>
<a class="group relative bg-white/70 backdrop-blur-md rounded-3xl p-8 shadow-sm border border-white hover:shadow-2xl transition-all duration-500 overflow-hidden text-center block hover:-translate-y-2" href="<?= e(category_link('android')) ?>"><div class="absolute inset-0 bg-gradient-to-b from-primary-fixed/0 to-primary-fixed/20 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div><div class="w-20 h-20 mx-auto bg-primary-fixed/50 rounded-2xl flex items-center justify-center mb-6 group-hover:scale-110 group-hover:rotate-6 transition-transform duration-500"><span class="material-symbols-outlined text-4xl text-primary">android</span></div><h3 class="font-h3 text-xl text-on-surface mb-2">Android Phones</h3><span class="text-sm text-primary font-bold opacity-0 group-hover:opacity-100 transition-opacity duration-300">Shop Now →</span></a>
<a class="group relative bg-white/70 backdrop-blur-md rounded-3xl p-8 shadow-sm border border-white hover:shadow-2xl transition-all duration-500 overflow-hidden text-center block hover:-translate-y-2" href="<?= e(category_link('accessories')) ?>"><div class="absolute inset-0 bg-gradient-to-b from-tertiary-fixed/0 to-tertiary-fixed/20 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div><div class="w-20 h-20 mx-auto bg-tertiary-fixed/50 rounded-2xl flex items-center justify-center mb-6 group-hover:scale-110 group-hover:-rotate-6 transition-transform duration-500"><span class="material-symbols-outlined text-4xl text-tertiary">cable</span></div><h3 class="font-h3 text-xl text-on-surface mb-2">Accessories</h3><span class="text-sm text-tertiary font-bold opacity-0 group-hover:opacity-100 transition-opacity duration-300">Shop Now →</span></a>
</div>
</section>
<?php if ($homepageBanners): ?>
<section class="home-banner-section max-w-container-max mx-auto px-gutter mb-section-padding space-y-8">
<?php foreach ($homepageBanners as $banner):
    $bannerImage = trim((string)($banner['image_path'] ?? ''));
    $bannerImage = $bannerImage !== '' ? $bannerImage : 'https://images.unsplash.com/photo-1611186871348-b1ce696e52c9?auto=format&amp;fit=crop&amp;w=2000&amp;q=80';
    $bannerUrl = homepage_public_url((string)($banner['cta_url'] ?? ''), site_url('deals'));
    $bannerCta = trim((string)($banner['cta_label'] ?? '')) ?: 'Learn More';
?>
<div class="relative rounded-[3rem] overflow-hidden bg-slate-950 shadow-2xl group">
<div class="absolute inset-0 bg-cover bg-center opacity-40 mix-blend-overlay group-hover:scale-105 transition-transform duration-1000" style="background-image:url('<?= e($bannerImage) ?>')"></div>
<div class="absolute inset-0 bg-gradient-to-r from-slate-950 via-slate-900/80 to-transparent"></div>
<div class="relative z-10 p-16 md:p-24 md:w-2/3"><span class="px-5 py-2 rounded-full bg-white/10 text-white font-label-caps text-xs border border-white/20 backdrop-blur-xl mb-8 inline-block tracking-widest"><?= e((string)($banner['eyebrow'] ?: 'FEATURED')) ?></span><h2 class="font-h1 text-5xl md:text-6xl text-white mb-6 leading-tight"><?= e((string)$banner['title']) ?></h2><p class="font-body-lg text-slate-300 mb-10 max-w-lg"><?= e((string)($banner['subtitle'] ?? '')) ?></p><a class="px-10 py-5 bg-white text-slate-950 rounded-full font-bold shadow-xl hover:bg-slate-100 transition-all duration-300 transform hover:-translate-y-1 active:scale-95 inline-block" href="<?= e($bannerUrl) ?>"><?= e($bannerCta) ?></a></div>
</div>
<?php endforeach; ?>
</section>
<?php endif; ?>
<section class="home-orbit-programs-section max-w-container-max mx-auto px-gutter mb-section-padding" data-home-reveal>
<div class="home-orbit-programs__head">
<span>BUYING SHORTCUTS</span>
<h2>Shortcuts that actually help buyers decide.</h2>
<p>Focused actions only: match the right phone, then verify warranty and buying details before checkout.</p>
</div>
<div class="home-orbit-programs__grid home-orbit-programs__grid--compact">
<a class="home-orbit-program" href="<?= e(site_url('phone-finder.php')) ?>">
<span class="material-symbols-outlined">auto_awesome</span>
<div><small>Personal help</small><h3>Find My Phone</h3><p>Tell us what you need and our team can help check a suitable phone for you.</p></div>
<strong>01</strong>
</a>
<a class="home-orbit-program" href="<?= e(site_url('support')) ?>">
<span class="material-symbols-outlined">verified_user</span>
<div><small>Trust layer</small><h3>Warranty clarity</h3><p>Keep buying decisions centered on warranty, storage, color, stock, and support status.</p></div>
<strong>02</strong>
</a>
</div>
</section>
<section class="home-featured-section max-w-container-max mx-auto px-gutter mb-section-padding">
<div class="flex flex-col md:flex-row justify-between items-end mb-16 gap-4"><div><span class="font-label-caps text-label-caps text-primary tracking-[0.2em] block mb-4">PHONE MARKET PICKS</span><h2 class="font-h2 text-5xl text-on-surface">Featured Phones & Accessories</h2></div><a class="font-body-md text-lg text-primary hover:text-primary-container transition-colors flex items-center gap-2 group font-bold" href="<?= e(site_url('products')) ?>">View All Phones <span class="material-symbols-outlined group-hover:translate-x-1 transition-transform">arrow_forward</span></a></div>
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-10">
<?php $cardProducts = [
    ['slot' => 'hero', 'product' => $heroProduct, 'image' => 'assets/images/samsung-galaxy-s24-ultra.svg', 'default_badge' => 'FLAGSHIP', 'badge_class' => 'bg-primary text-white', 'rating_count' => 987],
    ['slot' => 'audio', 'product' => $audioProduct, 'image' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuDipjC8vyJxFkj4014kZkkd3arkDDUcn8IstG0nmiHrMRAZBkJjnAdlUvpQ6Mxsb0LjmHryy-xYRNCRRuH4xMhK1TPXbFVHgYEW4pUzKzIn4JDZd17u_29GovEkAWjCOrIofbgyoYt_a-2KYD8PzL-rAT-cDQAgIwNAHLIDEgIy7AbhW5S0fWe9NG1nwzMFizceitLFttzbEakgPmI7y0Mzcgc2X2_Uz0lc1JZGiBd_Rz4OBVkk_lnYNLFnSA6kjKZVpgjnBAw9YLQ', 'default_badge' => '', 'badge_class' => 'bg-primary text-white', 'rating_count' => 85],
    ['slot' => 'watch', 'product' => $watchProduct, 'image' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuC12UrDNLyoJRk3Dygh-1axdUglURx1l3x3W0psIDEJyCKJoXDeJZBpzu-U19aZnntqBjf_9kXDhMeofq7fnI_0faHz61wTnC17YSpZmj6_ZnPm2fe1V9Wxyuk6dU58Eq9HPbEn9MPagsLGUr8DWTeVbK4lwwF_5jGqdhdVdMiSoLLOb6jlKxyHJkGZ3CGRgXa9oET7lStvZixA6EAtNfsemhNS4spTuORFXdfWCl7YOWHcnv0gSO8HJC8kfaEwYoaUyb_8ipu5BGI', 'default_badge' => 'BEST SELLER', 'badge_class' => 'bg-tertiary text-white', 'rating_count' => 342],
]; ?>
<?php foreach ($cardProducts as $index => $entry):
    $product = $entry['product'];
    if (!$product) {
        continue;
    }
    $slotKey = (string) $entry['slot'];
    $cardTitle = homepage_slot_public_text($homepageSlots, $slotKey, 'title_override', product_name($product, 'Product'));
    $cardDescription = homepage_slot_public_text($homepageSlots, $slotKey, 'subtitle_override', product_desc($product, 'Warranty-backed phone or accessory selected for everyday use in Turkey.'));
    $cardBadge = homepage_slot_public_text($homepageSlots, $slotKey, 'badge_override', product_badge_text($product, (string) $entry['default_badge']));
    $cardImage = homepage_product_image($product, (string) $entry['image']);
    $cardCanBuy = product_can_buy($product);
?>
<div class="group bg-white rounded-3xl p-8 shadow-sm border border-surface-variant/30 hover:shadow-2xl transition-all duration-500 flex flex-col items-center text-center relative cursor-pointer hover:-translate-y-2">
<?php if ($cardBadge !== ''): ?><div class="absolute top-8 left-8 z-20 flex flex-col gap-2"><span class="px-4 py-1.5 <?= e($entry['badge_class']) ?> rounded-full font-label-caps text-[10px] shadow-sm tracking-widest font-bold"><?= e($cardBadge) ?></span></div><?php endif; ?>
<button class="js-wishlist-toggle absolute top-8 right-8 w-12 h-12 rounded-full bg-surface-container/50 backdrop-blur-md flex items-center justify-center text-on-surface-variant hover:text-error hover:bg-error-container transition-all z-20 shadow-sm group-hover:scale-110" data-product-id="<?= product_id($product) ?>" aria-pressed="<?= in_array(product_id($product), $wishlistIds, true) ? 'true' : 'false' ?>"><span class="material-symbols-outlined text-2xl">favorite</span></button>
<a class="w-full h-80 mb-8 flex justify-center items-center relative rounded-2xl overflow-hidden bg-surface-bright/50" href="<?= e(product_link($product)) ?>"><img alt="<?= e($cardTitle) ?>" class="h-full w-full object-cover transition-transform duration-700 group-hover:scale-110" src="<?= e($cardImage) ?>"/></a>
<div class="w-full text-left">
<div class="flex items-center gap-1 mb-3 text-amber-500">
<span class="material-symbols-outlined text-sm" style="font-variation-settings: 'FILL' 1;">star</span>
<span class="material-symbols-outlined text-sm" style="font-variation-settings: 'FILL' 1;">star</span>
<span class="material-symbols-outlined text-sm" style="font-variation-settings: 'FILL' 1;">star</span>
<span class="material-symbols-outlined text-sm" style="font-variation-settings: 'FILL' 1;">star</span>
<span class="material-symbols-outlined text-sm" style="font-variation-settings: 'FILL' <?= ((float)$product['rating'] >= 4.9 || (float)$product['rating'] >= 5) ? '1' : '0' ?>;"><?= ((float)$product['rating'] >= 4.9) ? 'star' : 'star_half' ?></span>
<span class="text-xs text-on-surface-variant ml-2 font-medium">(<?= product_rating_count($product, $entry['rating_count']) ?>)</span>
</div>
<div class="flex justify-between items-start mb-3"><h3 class="font-h3 text-2xl font-bold text-on-surface group-hover:text-primary transition-colors"><?= e($cardTitle) ?></h3><span class="font-bold text-xl text-on-surface"><?= e(product_price_text($product, $siteCurrency)) ?></span></div>
<p class="font-body-md text-on-surface-variant mb-8 line-clamp-2"><?= e($cardDescription) ?></p>
<button class="<?= $cardCanBuy ? 'js-add-to-cart' : '' ?> w-full py-4 rounded-full bg-primary text-white font-bold hover:bg-primary-container hover:text-on-primary-container transition-all duration-300 shadow-md active:scale-95 <?= $cardCanBuy ? '' : 'opacity-60 cursor-not-allowed' ?>" data-product-id="<?= product_id($product) ?>" data-product-name="<?= e(product_name($product, 'Product')) ?>" <?= $cardCanBuy ? '' : 'disabled aria-disabled="true"' ?>><?= $cardCanBuy ? 'Add to Cart' : 'Sold Out' ?></button>
</div>
</div>
<?php endforeach; ?>
</div>
</section>
<section class="home-why-buy-section bg-surface-bright py-section-padding mb-section-padding border-y border-surface-variant/30">
  <div class="max-w-container-max mx-auto px-gutter">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-24 items-center">
      <div class="space-y-8">
        <span class="font-label-caps text-label-caps text-primary tracking-[0.2em] block">WHY BUY FROM PHONIX TÜRKİYE</span>
        <h2 class="font-h2 text-5xl text-on-surface leading-tight">Phone shopping without confusion.</h2>
        <p class="font-body-lg text-on-surface-variant leading-relaxed text-lg">Compare iPhone and Android options, check warranty status, add the right accessory, and complete checkout with delivery details saved clearly into your order.</p>
        <ul class="space-y-8 mb-12">
          <li class="flex items-start gap-6 group">
            <div class="w-14 h-14 rounded-2xl bg-primary-fixed flex items-center justify-center text-primary shrink-0 group-hover:scale-110 transition-transform duration-300"><span class="material-symbols-outlined text-3xl">sync</span></div>
            <div><h4 class="font-bold text-xl text-on-surface mb-2">Clear Warranty</h4><p class="text-on-surface-variant">Product pages highlight warranty status, storage, color, and key specifications before checkout.</p></div>
          </li>
          <li class="flex items-start gap-6 group">
            <div class="w-14 h-14 rounded-2xl bg-tertiary-fixed flex items-center justify-center text-tertiary shrink-0 group-hover:scale-110 transition-transform duration-300"><span class="material-symbols-outlined text-3xl">bluetooth_audio</span></div>
            <div><h4 class="font-bold text-xl text-on-surface mb-2">Turkey Delivery</h4><p class="text-on-surface-variant">Fast shipping options, local support details, and practical order tracking for phone buyers in Turkey.</p></div>
          </li>
        </ul>
        <a class="text-primary font-bold text-lg hover:underline inline-flex items-center gap-2 group" href="<?= e(site_url('support')) ?>">Visit Support <span class="material-symbols-outlined transition-transform duration-300 group-hover:translate-x-1">arrow_forward</span></a>
      </div>
      <div class="home-why-visual" aria-label="Phonix guided phone shopping experience">
        <div class="home-why-visual__mesh" aria-hidden="true"></div>
        <div class="home-why-visual__orbit home-why-visual__orbit--outer" aria-hidden="true"></div>
        <div class="home-why-visual__orbit home-why-visual__orbit--inner" aria-hidden="true"></div>
        <div class="home-why-visual__comet home-why-visual__comet--a" aria-hidden="true"></div>
        <div class="home-why-visual__comet home-why-visual__comet--b" aria-hidden="true"></div>
        <div class="home-why-visual__core">
          <span class="material-symbols-outlined">travel_explore</span>
          <strong>Clear phone choices</strong>
          <em>Compare • Warranty • Checkout</em>
        </div>
        <div class="home-why-visual__signal home-why-visual__signal--top">
          <span class="material-symbols-outlined">verified</span>
          <b>Warranty</b>
          <small>visible before checkout</small>
        </div>
        <div class="home-why-visual__signal home-why-visual__signal--left">
          <span class="material-symbols-outlined">phone_iphone</span>
          <b>iPhone / Android</b>
          <small>side-by-side clarity</small>
        </div>
        <div class="home-why-visual__signal home-why-visual__signal--right">
          <span class="material-symbols-outlined">local_shipping</span>
          <b>Turkey delivery</b>
          <small>shipping details saved</small>
        </div>
        <div class="home-why-visual__dock">
          <span>Storage</span>
          <span>Color</span>
          <span>Stock</span>
          <span>Accessory</span>
        </div>
      </div>
    </div>
  </div>
</section>
<section class="home-proof-section max-w-container-max mx-auto px-gutter mb-section-padding"><div class="text-center mb-16"><h2 class="font-h2 text-4xl text-on-surface mb-4">Why choose <?= e($siteName) ?>?</h2></div><div class="grid grid-cols-1 md:grid-cols-3 gap-10"><div class="bg-white rounded-[2.5rem] p-10 shadow-sm border border-surface-variant/30 flex flex-col items-center text-center relative overflow-hidden group hover:shadow-xl transition-all duration-500"><div class="absolute inset-0 bg-gradient-to-br from-primary-fixed/30 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 -z-10"></div><div class="w-20 h-20 rounded-3xl bg-gradient-to-br from-primary to-surface-tint flex items-center justify-center mb-8 text-white shadow-lg transform group-hover:rotate-6 transition-transform duration-300"><span class="material-symbols-outlined text-4xl">local_shipping</span></div><h3 class="font-h3 text-2xl text-on-surface mb-4 font-bold">Fast Turkey Delivery</h3><p class="font-body-md text-on-surface-variant">Ship phone orders across Turkey with clear delivery options, live tracking, and fast fulfillment from configured shipping methods.</p></div><div class="bg-white rounded-[2.5rem] p-10 shadow-sm border border-surface-variant/30 flex flex-col items-center text-center relative overflow-hidden group hover:shadow-xl transition-all duration-500"><div class="absolute inset-0 bg-gradient-to-br from-tertiary-fixed/30 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 -z-10"></div><div class="w-20 h-20 rounded-3xl bg-gradient-to-br from-tertiary to-slate-600 flex items-center justify-center mb-8 text-white shadow-lg transform group-hover:-rotate-6 transition-transform duration-300"><span class="material-symbols-outlined text-4xl">verified</span></div><h3 class="font-h3 text-2xl text-on-surface mb-4 font-bold">Warranty Clarity</h3><p class="font-body-md text-on-surface-variant">Each phone card is built around practical buying details: warranty status, storage, color, stock, and trusted brand information.</p></div><div class="bg-white rounded-[2.5rem] p-10 shadow-sm border border-surface-variant/30 flex flex-col items-center text-center relative overflow-hidden group hover:shadow-xl transition-all duration-500"><div class="absolute inset-0 bg-gradient-to-br from-primary-fixed-dim/30 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 -z-10"></div><div class="w-20 h-20 rounded-3xl bg-gradient-to-br from-blue-500 to-cyan-600 flex items-center justify-center mb-8 text-white shadow-lg transform group-hover:rotate-6 transition-transform duration-300"><span class="material-symbols-outlined text-4xl">support_agent</span></div><h3 class="font-h3 text-2xl text-on-surface mb-4 font-bold">Local Support</h3><p class="font-body-md text-on-surface-variant">Customer service, order status, returns, and phone-buying assistance stay visible from support, account, and checkout pages.</p></div></div></section>
</main>
<footer class="relative overflow-hidden pt-32 pb-16"><div class="absolute inset-0 bg-gradient-to-br from-white via-primary-fixed/20 to-tertiary-fixed/30 animate-gradient-mesh -z-20"></div><div class="absolute top-[-10%] left-[-10%] w-[500px] h-[500px] bg-primary-fixed/20 rounded-full blur-[100px] animate-pulse-slow"></div><div class="absolute bottom-[-10%] right-[-10%] w-[600px] h-[600px] bg-tertiary-fixed/20 rounded-full blur-[120px] animate-pulse-slow" style="animation-delay: -3s;"></div><div class="max-w-7xl mx-auto px-gutter relative z-10"><div class="glass-hero rounded-[3rem] p-12 md:p-20 shadow-[0_40px_100px_rgba(0,98,158,0.05)] mb-24 group"><div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center"><div class="space-y-6"><div class="w-16 h-16 bg-primary/10 rounded-2xl flex items-center justify-center animate-float-slow"><span class="material-symbols-outlined text-3xl text-primary">mail</span></div><h2 class="font-h2 text-4xl md:text-5xl text-on-surface font-bold leading-tight">Get phone deals first</h2><p class="font-body-lg text-on-surface-variant opacity-80">Subscribe for new phone arrivals, accessory bundles, and campaign updates in Turkey.</p></div><div class="relative"><form class="flex flex-col sm:flex-row gap-4" action="#" method="get"><input class="flex-1 px-8 py-5 rounded-full border-white bg-white/60 backdrop-blur-md shadow-inner focus:ring-4 focus:ring-primary/20 text-on-surface placeholder-slate-400 text-lg transition-all" placeholder="Enter your email" type="email"/><button class="px-10 py-5 bg-primary text-white rounded-full font-bold text-lg shadow-xl hover:bg-primary/90 hover:scale-105 transition-all duration-300 transform active:scale-95" type="submit">Subscribe</button></form><p class="text-xs text-on-surface-variant mt-4 ml-6 opacity-60">Your privacy is our priority. Unsubscribe anytime.</p></div></div></div><div class="grid grid-cols-1 md:grid-cols-4 gap-16 mb-24"><div class="col-span-1 space-y-8"><a class="text-4xl font-extrabold text-slate-900 font-['Manrope'] tracking-tighter hover:opacity-80 transition-opacity" href="<?= e(site_url('home')) ?>"><?= e($siteName) ?></a><p class="font-['Manrope'] text-on-surface-variant leading-relaxed text-lg max-w-xs"><?= e(store_setting($siteSettings ?? [], 'footer_tagline', 'Official phones, trusted warranty, fast delivery across Turkey.')) ?> Built for smartphone shopping in Turkey with clear product details and practical support.</p><div class="flex space-x-4"><a class="w-12 h-12 rounded-2xl bg-white/40 backdrop-blur-md border border-white/60 flex items-center justify-center text-on-surface-variant hover:bg-primary hover:text-white hover:scale-110 hover:-rotate-6 transition-all duration-300 shadow-sm" href="<?= e(site_url('brands')) ?>"><span class="material-symbols-outlined">language</span></a><a class="w-12 h-12 rounded-2xl bg-white/40 backdrop-blur-md border border-white/60 flex items-center justify-center text-on-surface-variant hover:bg-primary hover:text-white hover:scale-110 hover:rotate-6 transition-all duration-300 shadow-sm" href="<?= e(site_url('deals')) ?>"><span class="material-symbols-outlined">share</span></a><a class="w-12 h-12 rounded-2xl bg-white/40 backdrop-blur-md border border-white/60 flex items-center justify-center text-on-surface-variant hover:bg-primary hover:text-white hover:scale-110 hover:-rotate-6 transition-all duration-300 shadow-sm" href="<?= e(site_url('support')) ?>"><span class="material-symbols-outlined">verified</span></a></div></div><div class="col-span-1 md:col-span-3 grid grid-cols-2 md:grid-cols-3 gap-12"><div class="space-y-8"><h4 class="font-bold text-on-surface text-xl">Products</h4><ul class="flex flex-col space-y-4"><li><a class="text-on-surface-variant hover:text-primary transition-colors text-lg hover-underline-animation" href="<?= e(site_url('products')) ?>">All Phones</a></li><li><a class="text-on-surface-variant hover:text-primary transition-colors text-lg hover-underline-animation" href="<?= e(category_link('iphone')) ?>">iPhone</a></li><li><a class="text-on-surface-variant hover:text-primary transition-colors text-lg hover-underline-animation" href="<?= e(category_link('android')) ?>">Android Phones</a></li><li><a class="text-on-surface-variant hover:text-primary transition-colors text-lg hover-underline-animation" href="<?= e(category_link('accessories')) ?>">Accessories</a></li></ul></div><div class="space-y-8"><h4 class="font-bold text-on-surface text-xl">Support</h4><ul class="flex flex-col space-y-4"><li><a class="text-on-surface-variant hover:text-primary transition-colors text-lg hover-underline-animation" href="<?= e(site_url('support')) ?>">Help Center</a></li><li><a class="text-on-surface-variant hover:text-primary transition-colors text-lg hover-underline-animation" href="<?= e(site_url('account')) ?>">Order Status</a></li><li><a class="text-on-surface-variant hover:text-primary transition-colors text-lg hover-underline-animation" href="<?= e(site_url('support')) ?>">Returns</a></li><li><a class="text-on-surface-variant hover:text-primary transition-colors text-lg hover-underline-animation" href="<?= e(site_url('brands')) ?>">Store Locator</a></li></ul></div><div class="space-y-8"><h4 class="font-bold text-on-surface text-xl">Company</h4><ul class="flex flex-col space-y-4"><li><a class="text-on-surface-variant hover:text-primary transition-colors text-lg hover-underline-animation" href="<?= e(site_url('brands')) ?>">About Us</a></li><li><a class="text-on-surface-variant hover:text-primary transition-colors text-lg hover-underline-animation" href="<?= e(site_url('support')) ?>">Careers</a></li><li><a class="text-on-surface-variant hover:text-primary transition-colors text-lg hover-underline-animation" href="<?= e(site_url('support')) ?>">Privacy Policy</a></li><li><a class="text-on-surface-variant hover:text-primary transition-colors text-lg hover-underline-animation" href="<?= e(site_url('deals')) ?>">Sustainability</a></li></ul></div></div></div><div class="pt-10 border-t border-slate-900/5 flex flex-col md:flex-row justify-between items-center gap-8"><p class="text-on-surface-variant text-base">© <?= date('Y') ?> <?= e($siteName) ?> Electronics. <?= e(store_setting($siteSettings ?? [], 'footer_tagline', 'All rights reserved.')) ?></p><div class="flex items-center gap-8 text-base text-on-surface-variant"><button class="flex items-center gap-2 hover:text-primary transition-colors group" type="button"><span class="material-symbols-outlined text-xl group-hover:scale-110 transition-transform">public</span><span>Türkiye</span></button><span class="w-1.5 h-1.5 rounded-full bg-primary/30"></span><button class="hover:text-primary transition-colors hover-underline-animation" type="button">English</button><span class="w-1.5 h-1.5 rounded-full bg-primary/30"></span><button class="hover:text-primary transition-colors hover-underline-animation" type="button">Sitemap</button></div></div></div></footer>
<div class="toast js-toast" aria-live="polite"></div>
<script src="assets/js/site.js"></script>
<script src="<?= e(site_url('assets/js/home_cosmic.js')) ?>" defer></script>
</body>
</html>
