<?php
require __DIR__ . '/includes/bootstrap.php';

$pageTitle = ($siteName ?? 'Phonix Türkiye') . ' | Phone Deals';
$pageDescription = 'Live phone campaigns, accessory bundles, coupon codes, and limited-time offers for shoppers in Turkey.';
$currentPage = 'deals';
$pageStyles = ['assets/css/deals.css'];

$deals = fetch_live_deal_campaigns($pdo);
$coupons = fetch_public_active_coupons($pdo, 12);
$saleProducts = fetch_public_sale_products($pdo, 12);
$liveCampaignCount = count($deals);
$liveCouponCount = count($coupons);
$saleProductCount = count($saleProducts);

function public_deal_date_label(?string $date): string
{
    if (!$date) {
        return '';
    }
    $time = strtotime($date);
    return $time ? date('M j, Y', $time) : '';
}

function public_product_savings_label(array $product): string
{
    $price = (float) ($product['price'] ?? 0);
    $compare = (float) ($product['compare_price'] ?? 0);
    if ($compare <= $price || $compare <= 0) {
        return '';
    }
    $percent = (int) round((($compare - $price) / $compare) * 100);
    return $percent > 0 ? $percent . '% off' : '';
}

require __DIR__ . '/includes/partials_header.php';
?>

<section class="section deals-hero-section">
    <div class="container">
        <div class="deals-hero glass-panel">
            <p class="eyebrow">Limited-time offers</p>
            <h1>Live Phone Deals</h1>
            <p>Smartphone campaigns, accessory bundles, and coupon codes available for shoppers in Turkey.</p>
            <div class="deals-hero-stats" aria-label="Deals summary">
                <span><strong><?= (int) $liveCampaignCount ?></strong> live campaigns</span>
                <span><strong><?= (int) $liveCouponCount ?></strong> active codes</span>
                <span><strong><?= (int) $saleProductCount ?></strong> sale products</span>
            </div>
            <div class="deals-hero-actions">
                <a class="btn btn-primary" href="#campaigns">Browse campaigns</a>
                <a class="btn btn-secondary" href="<?= e(site_url('products')) ?>">Shop all products</a>
            </div>
        </div>
    </div>
</section>

<section class="section" id="campaigns">
    <div class="container">
        <div class="section-head">
            <div>
                <p class="eyebrow">Promotions</p>
                <h2>Featured campaigns</h2>
            </div>
            <a class="section-link" href="<?= e(site_url('products')) ?>">View all products</a>
        </div>
        <?php if (!$deals): ?>
            <div class="empty-state glass-panel">No live campaigns right now. Check back soon.</div>
        <?php else: ?>
            <div class="deals-grid">
                <?php foreach ($deals as $deal): ?>
                    <?php
                    $ctaUrl = public_deal_cta_url($deal);
                    $image = public_deal_image($deal);
                    $productUrl = !empty($deal['product_slug']) ? site_url('product', ['slug' => (string) $deal['product_slug']]) : '';
                    $endsLabel = public_deal_date_label($deal['ends_at'] ?? null);
                    $couponEndsLabel = public_deal_date_label($deal['coupon_ends_at'] ?? null);
                    ?>
                    <article class="deal-card glass-panel">
                        <div class="deal-card__media">
                            <?php if ($image !== ''): ?>
                                <img src="<?= e($image) ?>" alt="<?= e((string) $deal['title']) ?>" loading="lazy">
                            <?php else: ?>
                                <div class="deal-card__placeholder" aria-hidden="true">
                                    <span class="material-symbols-outlined">local_offer</span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="deal-card__body">
                            <div class="deal-card__meta">
                                <span><?= e($deal['badge'] ?: 'Deal') ?></span>
                                <?php if ($endsLabel !== ''): ?><small>Ends <?= e($endsLabel) ?></small><?php endif; ?>
                            </div>
                            <h3><?= e((string) $deal['title']) ?></h3>
                            <?php if (!empty($deal['subtitle'])): ?><p><?= e((string) $deal['subtitle']) ?></p><?php endif; ?>
                            <div class="deal-card__chips">
                                <?php if (!empty($deal['discount_label'])): ?><strong><?= e((string) $deal['discount_label']) ?></strong><?php endif; ?>
                                <?php if (!empty($deal['coupon_code'])): ?><code><?= e((string) $deal['coupon_code']) ?></code><?php endif; ?>
                            </div>
                            <?php if (!empty($deal['product_name'])): ?>
                                <a class="deal-product-mini" href="<?= e($productUrl) ?>">
                                    <span><?= e((string) ($deal['product_brand'] ?: 'Featured product')) ?></span>
                                    <strong><?= e((string) $deal['product_name']) ?></strong>
                                    <em><?= e(format_price((float) $deal['product_price'], $siteCurrency)) ?><?= !empty($deal['product_compare_price']) ? ' · was ' . e(format_price((float) $deal['product_compare_price'], $siteCurrency)) : '' ?></em>
                                </a>
                            <?php endif; ?>
                            <?php if (!empty($deal['coupon_code']) && $couponEndsLabel !== ''): ?>
                                <small class="deal-note">Coupon active until <?= e($couponEndsLabel) ?>.</small>
                            <?php endif; ?>
                            <a class="btn btn-primary" href="<?= e($ctaUrl) ?>"><?= e($deal['cta_label'] ?: 'Shop deal') ?></a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-head">
            <div>
                <p class="eyebrow">Coupons</p>
                <h2>Available codes</h2>
            </div>
            <a class="section-link" href="<?= e(site_url('checkout')) ?>">Go to checkout</a>
        </div>
        <?php if (!$coupons): ?>
            <div class="empty-state glass-panel">No active coupon codes.</div>
        <?php else: ?>
            <div class="coupon-grid">
                <?php foreach ($coupons as $coupon): ?>
                    <?php $couponEnds = public_deal_date_label($coupon['ends_at'] ?? null); ?>
                    <article class="coupon-card glass-panel">
                        <small><?= e(public_coupon_discount_label($coupon, $siteCurrency)) ?></small>
                        <strong><?= e((string) $coupon['code']) ?></strong>
                        <p><?= e($coupon['description'] ?: 'Apply this code during checkout while it is active.') ?></p>
                        <div class="coupon-card__meta">
                            <?php if ((float) ($coupon['min_order_total'] ?? 0) > 0): ?>
                                <span>Min. order <?= e(format_price((float) $coupon['min_order_total'], $siteCurrency)) ?></span>
                            <?php endif; ?>
                            <?php if ($couponEnds !== ''): ?><span>Ends <?= e($couponEnds) ?></span><?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-head">
            <div>
                <p class="eyebrow">Product deals</p>
                <h2>Sale products</h2>
            </div>
            <a class="section-link" href="<?= e(site_url('products', ['sort' => 'price_asc'])) ?>">Shop sale products</a>
        </div>
        <?php if (!$saleProducts): ?>
            <div class="empty-state glass-panel">No sale products with compare prices yet.</div>
        <?php else: ?>
            <div class="product-grid">
                <?php foreach ($saleProducts as $product): ?>
                    <?php $saving = public_product_savings_label($product); ?>
                    <article class="product-card glass-panel">
                        <a href="<?= e(site_url('product', ['slug' => (string) $product['slug']])) ?>">
                            <?php if (!empty($product['image'])): ?><img src="<?= e((string) $product['image']) ?>" alt="<?= e((string) $product['name']) ?>" loading="lazy"><?php endif; ?>
                            <small><?= e($product['brand'] ?: $product['category_name'] ?: 'Phonix') ?></small>
                            <h3><?= e((string) $product['name']) ?></h3>
                            <?php if ($saving !== ''): ?><span class="sale-badge"><?= e($saving) ?></span><?php endif; ?>
                            <p><?= e((string) ($product['short_description'] ?: '')) ?></p>
                            <div class="price-line"><strong><?= e(format_price((float) $product['price'], $siteCurrency)) ?></strong><span><?= e(format_price((float) $product['compare_price'], $siteCurrency)) ?></span></div>
                        </a>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/includes/partials_footer.php'; ?>
