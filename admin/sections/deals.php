<?php
require_once __DIR__ . '/../includes/layout.php';
$ctx = admin_boot('deals');
$pdo = $ctx['pdo'];
$currency = $ctx['siteCurrency'];

if (is_post_request()) {
    verify_csrf_or_fail($_POST['_csrf'] ?? null);
    $action = (string) ($_POST['admin_action'] ?? '');
    try {
        if ($action === 'deal_create' || $action === 'deal_update') {
            $id = admin_int_input('deal_id');
            $title = admin_clean_text('title', 190);
            if ($title === '') {
                throw new RuntimeException('Deal title is required.');
            }
            $payload = [
                'title' => $title,
                'subtitle' => admin_clean_text('subtitle', 255) ?: null,
                'badge' => admin_clean_text('badge', 120) ?: null,
                'coupon_id' => admin_int_input('coupon_id') ?: null,
                'product_id' => admin_int_input('product_id') ?: null,
                'discount_label' => admin_clean_text('discount_label', 120) ?: null,
                'cta_label' => admin_clean_text('cta_label', 120) ?: null,
                'cta_url' => admin_clean_text('cta_url', 500) ?: null,
                'image_path' => admin_clean_text('image_path', 500) ?: null,
                'starts_at' => admin_datetime_input('starts_at'),
                'ends_at' => admin_datetime_input('ends_at'),
                'sort_order' => (int) ($_POST['sort_order'] ?? 0),
                'is_active' => admin_bool_from_post('is_active'),
            ];
            if ($action === 'deal_create') {
                $stmt = $pdo->prepare('INSERT INTO deal_campaigns (title, subtitle, badge, coupon_id, product_id, discount_label, cta_label, cta_url, image_path, starts_at, ends_at, sort_order, is_active) VALUES (:title, :subtitle, :badge, :coupon_id, :product_id, :discount_label, :cta_label, :cta_url, :image_path, :starts_at, :ends_at, :sort_order, :is_active)');
                $stmt->execute($payload);
                $newId = (int) $pdo->lastInsertId();
                admin_log_activity($pdo, 'deal_created', 'deal', $newId, $title);
                flash_set('success', 'Deal campaign created.');
            } else {
                $payload['id'] = $id;
                $stmt = $pdo->prepare('UPDATE deal_campaigns SET title=:title, subtitle=:subtitle, badge=:badge, coupon_id=:coupon_id, product_id=:product_id, discount_label=:discount_label, cta_label=:cta_label, cta_url=:cta_url, image_path=:image_path, starts_at=:starts_at, ends_at=:ends_at, sort_order=:sort_order, is_active=:is_active WHERE id=:id LIMIT 1');
                $stmt->execute($payload);
                admin_log_activity($pdo, 'deal_updated', 'deal', $id, $title);
                flash_set('success', 'Deal campaign updated.');
            }
        } elseif ($action === 'deal_delete') {
            $id = admin_int_input('deal_id');
            $pdo->prepare('DELETE FROM deal_campaigns WHERE id = :id LIMIT 1')->execute(['id' => $id]);
            admin_log_activity($pdo, 'deal_deleted', 'deal', $id);
            flash_set('success', 'Deal campaign deleted.');
        } elseif ($action === 'deal_toggle') {
            $id = admin_int_input('deal_id');
            $current = (int) admin_scalar($pdo, 'SELECT is_active FROM deal_campaigns WHERE id = ' . $id);
            $next = $current === 1 ? 0 : 1;
            $stmt = $pdo->prepare('UPDATE deal_campaigns SET is_active = :next WHERE id = :id LIMIT 1');
            $stmt->execute(['next' => $next, 'id' => $id]);
            admin_log_activity($pdo, $next ? 'deal_published' : 'deal_hidden', 'deal', $id);
            flash_set('success', $next ? 'Deal published.' : 'Deal hidden.');
        } else {
            throw new RuntimeException('Unknown deal action.');
        }
    } catch (Throwable $e) {
        admin_flash_from_exception($e);
    }
    admin_redirect('deals');
}

$deals = admin_deal_campaigns($pdo);
$coupons = admin_rows($pdo, "SELECT id, code, discount_type, discount_value, is_active FROM coupons ORDER BY is_active DESC, code ASC");
$products = admin_product_options($pdo);

$editDeal = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM deal_campaigns WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => (int) $_GET['edit']]);
    $editDeal = $stmt->fetch() ?: null;
}

$liveCount = admin_active_deals_count($pdo);
$scheduledCount = (int) admin_scalar($pdo, "SELECT COUNT(*) FROM deal_campaigns WHERE is_active = 1 AND starts_at IS NOT NULL AND starts_at > NOW()");
$expiredCount = (int) admin_scalar($pdo, "SELECT COUNT(*) FROM deal_campaigns WHERE is_active = 1 AND ends_at IS NOT NULL AND ends_at < NOW()");
$discountedProducts = (int) admin_scalar($pdo, "SELECT COUNT(*) FROM products WHERE is_active = 1 AND compare_price IS NOT NULL AND compare_price > price");

$defaults = [
    'id' => '',
    'title' => '',
    'subtitle' => '',
    'badge' => '',
    'coupon_id' => '',
    'product_id' => '',
    'discount_label' => '',
    'cta_label' => 'Shop deal',
    'cta_url' => 'deals.php',
    'image_path' => '',
    'starts_at' => '',
    'ends_at' => '',
    'sort_order' => '0',
    'is_active' => 1,
];
$form = array_merge($defaults, $editDeal ?: []);

admin_header('Deals', 'Build promotional campaigns that connect coupons, products, banners, and the public deals page.', 'deals');
?>
<section class="admin-metrics-grid" aria-label="Deals metrics">
    <?php admin_metric_card('Live deals', (string) $liveCount, 'campaign', 'Visible now'); ?>
    <?php admin_metric_card('Scheduled', (string) $scheduledCount, 'event_upcoming', 'Starts later'); ?>
    <?php admin_metric_card('Expired', (string) $expiredCount, 'history', 'Needs review'); ?>
    <?php admin_metric_card('Sale products', (string) $discountedProducts, 'sell', 'compare_price > price'); ?>
</section>

<section class="admin-two-col">
    <article class="admin-card glass-panel">
        <div class="admin-section-head">
            <div>
                <p class="admin-eyebrow"><?= $editDeal ? 'Edit campaign' : 'New campaign' ?></p>
                <h2><?= $editDeal ? e($editDeal['title']) : 'Create a promotion' ?></h2>
            </div>
            <?php if ($editDeal): ?><a class="admin-text-link" href="<?= e(admin_page_url('deals')) ?>">New deal</a><?php endif; ?>
        </div>
        <form method="post" class="admin-form-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="admin_action" value="<?= $editDeal ? 'deal_update' : 'deal_create' ?>">
            <input type="hidden" name="deal_id" value="<?= e((string) ($form['id'] ?? '')) ?>">

            <label class="admin-wide">Title
                <input name="title" value="<?= e((string) $form['title']) ?>" required maxlength="190" placeholder="Summer device deals">
            </label>
            <label class="admin-wide">Subtitle
                <textarea name="subtitle" rows="3" maxlength="255" placeholder="Short campaign copy shown to customers"><?= e((string) $form['subtitle']) ?></textarea>
            </label>
            <label>Badge
                <input name="badge" value="<?= e((string) $form['badge']) ?>" maxlength="120" placeholder="Limited time">
            </label>
            <label>Discount label
                <input name="discount_label" value="<?= e((string) $form['discount_label']) ?>" maxlength="120" placeholder="Up to 20% off">
            </label>
            <label>Sort order
                <input type="number" name="sort_order" value="<?= e((string) $form['sort_order']) ?>">
            </label>
            <label class="admin-check-label">
                <input type="checkbox" name="is_active" value="1" <?= (int) ($form['is_active'] ?? 0) === 1 ? 'checked' : '' ?>>
                Active
            </label>

            <label class="admin-wide">Attach coupon
                <select name="coupon_id">
                    <option value="">No coupon</option>
                    <?php foreach ($coupons as $coupon): ?>
                        <option value="<?= (int) $coupon['id'] ?>" <?= (int) ($form['coupon_id'] ?? 0) === (int) $coupon['id'] ? 'selected' : '' ?>>
                            <?= e($coupon['code']) ?> · <?= e($coupon['discount_type']) ?> <?= e((string) $coupon['discount_value']) ?><?= (int) $coupon['is_active'] === 1 ? '' : ' · hidden' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="admin-wide">Feature product
                <select name="product_id">
                    <option value="">No product</option>
                    <?php foreach ($products as $product): ?>
                        <option value="<?= (int) $product['id'] ?>" <?= (int) ($form['product_id'] ?? 0) === (int) $product['id'] ? 'selected' : '' ?>>
                            <?= e($product['name']) ?> · <?= e($product['brand'] ?: 'No brand') ?> · <?= e(admin_money($product['price'], $currency)) ?><?= (int) $product['is_active'] === 1 ? '' : ' · hidden' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>CTA label
                <input name="cta_label" value="<?= e((string) $form['cta_label']) ?>" maxlength="120">
            </label>
            <label>CTA URL
                <input name="cta_url" value="<?= e((string) $form['cta_url']) ?>" maxlength="500" placeholder="products.php?category=audio">
            </label>
            <label class="admin-wide">Image path
                <input name="image_path" value="<?= e((string) $form['image_path']) ?>" maxlength="500" placeholder="assets/uploads/admin_media/banner.webp">
            </label>
            <label>Starts at
                <input type="datetime-local" name="starts_at" value="<?= e(admin_datetime_local_value($form['starts_at'] ?? '')) ?>">
            </label>
            <label>Ends at
                <input type="datetime-local" name="ends_at" value="<?= e(admin_datetime_local_value($form['ends_at'] ?? '')) ?>">
            </label>

            <div class="admin-form-actions">
                <button class="admin-primary-btn" type="submit"><?= $editDeal ? 'Save deal' : 'Create deal' ?></button>
            </div>
        </form>
    </article>

    <article class="admin-card glass-panel">
        <div class="admin-section-head">
            <div><p class="admin-eyebrow">Preview logic</p><h2>Where deals appear</h2></div>
            <a class="admin-text-link" href="<?= e(admin_root_url('deals.php')) ?>">Open public page</a>
        </div>
        <div class="admin-deals-guide">
            <article>
                <span class="material-symbols-outlined">campaign</span>
                <div><strong>Campaigns</strong><p>Shown on the public deals page when active and inside their date window.</p></div>
            </article>
            <article>
                <span class="material-symbols-outlined">local_offer</span>
                <div><strong>Coupons</strong><p>Attached coupon codes are displayed as copy-ready badges for customers.</p></div>
            </article>
            <article>
                <span class="material-symbols-outlined">sell</span>
                <div><strong>Sale products</strong><p>Products with a compare price higher than the current price appear as live product deals.</p></div>
            </article>
        </div>
    </article>
</section>

<section class="admin-card glass-panel">
    <div class="admin-section-head">
        <div><p class="admin-eyebrow">Campaigns</p><h2>Deal campaigns</h2></div>
    </div>
    <?php if (!$deals): ?>
        <?php admin_empty_state('No deals yet', 'Create your first deal campaign and it will be available on deals.php.'); ?>
    <?php else: ?>
        <div class="admin-deals-list">
            <?php foreach ($deals as $deal): ?>
                <article class="admin-deal-row">
                    <div class="admin-deal-main">
                        <span class="admin-pill <?= e(admin_deal_status_class($deal)) ?>"><?= e(admin_deal_status_label($deal)) ?></span>
                        <?php if (!empty($deal['badge'])): ?><span class="admin-pill"><?= e($deal['badge']) ?></span><?php endif; ?>
                        <h3><?= e($deal['title']) ?></h3>
                        <?php if (!empty($deal['subtitle'])): ?><p><?= e($deal['subtitle']) ?></p><?php endif; ?>
                        <small>
                            <?= e('Sort ' . (int) $deal['sort_order']) ?>
                            <?= !empty($deal['starts_at']) ? e(' · starts ' . $deal['starts_at']) : '' ?>
                            <?= !empty($deal['ends_at']) ? e(' · ends ' . $deal['ends_at']) : '' ?>
                        </small>
                        <div class="admin-chip-cloud">
                            <?php if (!empty($deal['coupon_code'])): ?><code>Coupon: <?= e($deal['coupon_code']) ?></code><?php endif; ?>
                            <?php if (!empty($deal['product_name'])): ?><code>Product: <?= e($deal['product_name']) ?></code><?php endif; ?>
                            <?php if (!empty($deal['discount_label'])): ?><code><?= e($deal['discount_label']) ?></code><?php endif; ?>
                        </div>
                    </div>
                    <div class="admin-row-actions">
                        <a href="<?= e(admin_page_url('deals', ['edit' => (int) $deal['id']])) ?>">Edit</a>
                        <details class="admin-more-menu">
                            <summary><span>More</span><span class="material-symbols-outlined">expand_more</span></summary>
                            <div class="admin-more-panel">
                                <form method="post" class="admin-inline-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="admin_action" value="deal_toggle">
                                    <input type="hidden" name="deal_id" value="<?= (int) $deal['id'] ?>">
                                    <button type="submit"><?= (int) $deal['is_active'] === 1 ? 'Hide' : 'Publish' ?></button>
                                </form>
                                <form method="post" class="admin-inline-form" data-admin-confirm="Delete this deal campaign?">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="admin_action" value="deal_delete">
                                    <input type="hidden" name="deal_id" value="<?= (int) $deal['id'] ?>">
                                    <button class="danger" type="submit">Delete</button>
                                </form>
                            </div>
                        </details>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php admin_footer(); ?>
