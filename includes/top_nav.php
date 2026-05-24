<?php
$siteName = $siteName ?? 'Phonix Türkiye';
$currentPage = $currentPage ?? '';
$activeCategory = trim((string) ($_GET['category'] ?? ($activeCategory ?? '')));
$accountUrl = !empty($currentUser) ? site_url('account') : site_url('auth');

$headerCartCount = isset($cartSummary['count']) ? (int) $cartSummary['count'] : 0;
if ($headerCartCount === 0 && isset($pdo) && function_exists('fetch_cart_items') && function_exists('cart_summary_from_items')) {
    try {
        $headerCartSummary = cart_summary_from_items(fetch_cart_items($pdo));
        $headerCartCount = (int) ($headerCartSummary['count'] ?? 0);
    } catch (Throwable $e) {
        $headerCartCount = 0;
    }
}

$topNavSpacer = $topNavSpacer ?? true;
$navCategories = [
    'iphone' => 'iPhone',
    'android' => 'Android',
    'accessories' => 'Accessories',
    'wearables' => 'Wearables',
];
$announcementText = trim((string) (($siteSettings ?? [])['announcement_text'] ?? ''));
$hiddenAnnouncementTexts = [
    'Türkiye-wide shipping, official warranty options, and secure checkout for every phone order.',
];
if (in_array($announcementText, $hiddenAnnouncementTexts, true)) {
    $announcementText = '';
}
?>
<?php if ($announcementText !== ''): ?>
    <div class="phonix-announcement" role="status">
        <span class="material-symbols-outlined" aria-hidden="true">campaign</span>
        <span><?= e($announcementText) ?></span>
    </div>
<?php endif; ?>
<nav class="phonix-topnav<?= $announcementText !== '' ? ' has-announcement' : '' ?>" aria-label="Primary navigation">
    <div class="phonix-topnav__inner">
        <a class="phonix-topnav__brand" href="<?= e(site_url('home')) ?>" aria-label="<?= e($siteName) ?> home">
            <?= e($siteName) ?>
        </a>

        <div class="phonix-topnav__links" aria-label="Product categories">
            <a class="phonix-topnav__link<?= $currentPage === 'products' && $activeCategory === '' ? ' is-active' : '' ?>" href="<?= e(site_url('products')) ?>">
                All Phones
            </a>
            <?php foreach ($navCategories as $slug => $label): ?>
                <a class="phonix-topnav__link<?= $activeCategory === $slug ? ' is-active' : '' ?>" href="<?= e(site_url('products', ['category' => $slug])) ?>">
                    <?= e($label) ?>
                </a>
            <?php endforeach; ?>
            <a class="phonix-topnav__link<?= $currentPage === 'deals' ? ' is-active' : '' ?>" href="<?= e(site_url('deals')) ?>">
                Deals
            </a>
            <a class="phonix-topnav__link<?= $currentPage === 'phone_finder' ? ' is-active' : '' ?>" href="<?= e(site_url('phone_finder')) ?>">
                Find My Phone
            </a>
        </div>

        <div class="phonix-topnav__actions">
            <a class="phonix-topnav__icon phonix-topnav__deal-icon<?= $currentPage === 'deals' ? ' is-active' : '' ?>" aria-label="Deals" href="<?= e(site_url('deals')) ?>">
                <span class="material-symbols-outlined" aria-hidden="true">local_offer</span>
            </a>
            <a class="phonix-topnav__icon" aria-label="Search" href="<?= e(site_url('search')) ?>">
                <span class="material-symbols-outlined" aria-hidden="true">search</span>
            </a>
            <a class="phonix-topnav__icon" aria-label="Wishlist" href="<?= e(site_url('wishlist')) ?>">
                <span class="material-symbols-outlined" aria-hidden="true">favorite</span>
            </a>
            <a class="phonix-topnav__icon phonix-topnav__cart" aria-label="Shopping Cart" href="<?= e(site_url('checkout')) ?>">
                <span class="material-symbols-outlined" aria-hidden="true">shopping_cart</span>
                <span class="js-cart-count phonix-topnav__count"><?= $headerCartCount ?></span>
            </a>
            <a class="phonix-topnav__icon" aria-label="Account" href="<?= e($accountUrl) ?>">
                <span class="material-symbols-outlined" aria-hidden="true">person</span>
            </a>
        </div>
    </div>
</nav>
<?php if ($topNavSpacer): ?>
    <div class="phonix-topnav-spacer<?= $announcementText !== '' ? ' has-announcement' : '' ?>" aria-hidden="true"></div>
<?php endif; ?>
