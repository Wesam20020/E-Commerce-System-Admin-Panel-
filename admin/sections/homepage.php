<?php
require_once __DIR__ . '/../includes/layout.php';
$ctx = admin_boot('homepage');
$pdo = $ctx['pdo'];
$settings = $ctx['siteSettings'];
$currency = $ctx['siteCurrency'];

admin_homepage_default_slots($pdo);

if (is_post_request()) {
    verify_csrf_or_fail($_POST['_csrf'] ?? null);
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'save_hero') {
            $fields = [
                'homepage_eyebrow' => 140,
                'homepage_title_line_1' => 120,
                'homepage_title_line_2' => 120,
                'homepage_subtitle' => 320,
                'homepage_primary_cta_label' => 80,
                'homepage_primary_cta_url' => 500,
                'homepage_secondary_cta_label' => 80,
                'homepage_secondary_cta_url' => 500,
                'homepage_trust_text' => 180,
                'homepage_hero_image' => 500,
            ];
            foreach ($fields as $key => $max) {
                admin_store_setting($pdo, $key, mb_substr(trim((string) ($_POST[$key] ?? '')), 0, $max));
            }
            admin_log_activity($pdo, 'homepage_hero_updated', 'homepage', null, 'Updated homepage hero copy and CTA settings.');
            flash_set('success', 'Homepage hero saved.');
        } elseif ($action === 'save_slots') {
            $slots = $_POST['slots'] ?? [];
            if (!is_array($slots)) {
                $slots = [];
            }
            $stmt = $pdo->prepare('UPDATE homepage_featured_slots SET product_id = :product_id, title_override = :title_override, subtitle_override = :subtitle_override, badge_override = :badge_override, sort_order = :sort_order, is_active = :is_active WHERE id = :id LIMIT 1');
            foreach ($slots as $id => $slot) {
                if (!is_array($slot)) {
                    continue;
                }
                $productId = max(0, (int) ($slot['product_id'] ?? 0));
                $stmt->execute([
                    'id' => (int) $id,
                    'product_id' => $productId > 0 ? $productId : null,
                    'title_override' => mb_substr(trim((string) ($slot['title_override'] ?? '')), 0, 190),
                    'subtitle_override' => mb_substr(trim((string) ($slot['subtitle_override'] ?? '')), 0, 255),
                    'badge_override' => mb_substr(trim((string) ($slot['badge_override'] ?? '')), 0, 120),
                    'sort_order' => (int) ($slot['sort_order'] ?? 0),
                    'is_active' => isset($slot['is_active']) ? 1 : 0,
                ]);
            }
            admin_log_activity($pdo, 'homepage_slots_updated', 'homepage', null, 'Updated homepage featured product slots.');
            flash_set('success', 'Homepage featured slots saved.');
        } elseif ($action === 'add_banner') {
            $stmt = $pdo->prepare('INSERT INTO homepage_banners (title, subtitle, eyebrow, cta_label, cta_url, image_path, sort_order, is_active) VALUES (:title, :subtitle, :eyebrow, :cta_label, :cta_url, :image_path, :sort_order, :is_active)');
            $stmt->execute([
                'title' => mb_substr(trim((string) ($_POST['title'] ?? '')), 0, 190),
                'subtitle' => mb_substr(trim((string) ($_POST['subtitle'] ?? '')), 0, 255),
                'eyebrow' => mb_substr(trim((string) ($_POST['eyebrow'] ?? '')), 0, 120),
                'cta_label' => mb_substr(trim((string) ($_POST['cta_label'] ?? '')), 0, 120),
                'cta_url' => mb_substr(trim((string) ($_POST['cta_url'] ?? '')), 0, 500),
                'image_path' => mb_substr(trim((string) ($_POST['image_path'] ?? '')), 0, 500),
                'sort_order' => (int) ($_POST['sort_order'] ?? 0),
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
            ]);
            $id = (int) $pdo->lastInsertId();
            admin_log_activity($pdo, 'homepage_banner_created', 'homepage_banner', $id, (string) ($_POST['title'] ?? ''));
            flash_set('success', 'Homepage banner created.');
        } elseif ($action === 'update_banner') {
            $id = (int) ($_POST['banner_id'] ?? 0);
            $stmt = $pdo->prepare('UPDATE homepage_banners SET title = :title, subtitle = :subtitle, eyebrow = :eyebrow, cta_label = :cta_label, cta_url = :cta_url, image_path = :image_path, sort_order = :sort_order, is_active = :is_active WHERE id = :id LIMIT 1');
            $stmt->execute([
                'id' => $id,
                'title' => mb_substr(trim((string) ($_POST['title'] ?? '')), 0, 190),
                'subtitle' => mb_substr(trim((string) ($_POST['subtitle'] ?? '')), 0, 255),
                'eyebrow' => mb_substr(trim((string) ($_POST['eyebrow'] ?? '')), 0, 120),
                'cta_label' => mb_substr(trim((string) ($_POST['cta_label'] ?? '')), 0, 120),
                'cta_url' => mb_substr(trim((string) ($_POST['cta_url'] ?? '')), 0, 500),
                'image_path' => mb_substr(trim((string) ($_POST['image_path'] ?? '')), 0, 500),
                'sort_order' => (int) ($_POST['sort_order'] ?? 0),
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
            ]);
            admin_log_activity($pdo, 'homepage_banner_updated', 'homepage_banner', $id, (string) ($_POST['title'] ?? ''));
            flash_set('success', 'Homepage banner updated.');
        } elseif ($action === 'delete_banner') {
            $id = (int) ($_POST['banner_id'] ?? 0);
            $pdo->prepare('DELETE FROM homepage_banners WHERE id = :id LIMIT 1')->execute(['id' => $id]);
            admin_log_activity($pdo, 'homepage_banner_deleted', 'homepage_banner', $id, 'Deleted homepage banner.');
            flash_set('success', 'Homepage banner deleted.');
        }
    } catch (Throwable $e) {
        admin_flash_from_exception($e, 'Homepage settings could not be saved.');
    }
    admin_redirect('homepage');
}

$products = admin_product_options($pdo);
$slots = admin_homepage_slots($pdo);
$banners = admin_homepage_banners($pdo);
$media = admin_rows($pdo, 'SELECT id, public_path, original_name, alt_text FROM media_assets ORDER BY created_at DESC, id DESC LIMIT 80');
$activeBannerCount = (int) admin_scalar($pdo, 'SELECT COUNT(*) FROM homepage_banners WHERE is_active = 1');
$slotCount = count($slots);
$selectedSlotCount = 0;
foreach ($slots as $slot) {
    if (!empty($slot['product_id']) && (int) $slot['is_active'] === 1) {
        $selectedSlotCount++;
    }
}

$heroEyebrow = $settings['homepage_eyebrow'] ?? 'NEXT-GEN TECHNOLOGY ARRIVED';
$heroTitle1 = $settings['homepage_title_line_1'] ?? 'The Future';
$heroTitle2 = $settings['homepage_title_line_2'] ?? 'In Your Hands.';
$heroSubtitle = $settings['homepage_subtitle'] ?? 'Experience the pinnacle of mobile innovation with a storefront designed around premium devices, audio, and accessories.';
$primaryLabel = $settings['homepage_primary_cta_label'] ?? 'Pre-order Now';
$primaryUrl = $settings['homepage_primary_cta_url'] ?? 'products.php';
$secondaryLabel = $settings['homepage_secondary_cta_label'] ?? 'Watch Trailer';
$secondaryUrl = $settings['homepage_secondary_cta_url'] ?? 'new-arrivals.php';
$trustText = $settings['homepage_trust_text'] ?? 'Trusted by tech enthusiasts worldwide';
$heroImage = $settings['homepage_hero_image'] ?? '';

admin_header('Homepage Manager', 'Control the storefront hero, featured product slots, and promotional banners without editing code.', 'homepage');
?>
<section class="admin-metrics-grid">
    <?php admin_metric_card('Hero controls', 'Ready', 'web', 'Copy, CTA, and hero media'); ?>
    <?php admin_metric_card('Featured slots', (string) $selectedSlotCount . '/' . (string) $slotCount, 'star', 'Homepage product placements'); ?>
    <?php admin_metric_card('Active banners', (string) $activeBannerCount, 'campaign', 'Promotional content'); ?>
    <?php admin_metric_card('Catalog products', (string) count($products), 'inventory_2', 'Available for selection'); ?>
</section>

<section class="admin-grid admin-two-col-wide">
    <article class="admin-card glass-panel">
        <div class="admin-section-head"><div><p class="admin-eyebrow">Hero section</p><h2>Main storefront message</h2></div></div>
        <form method="post" class="admin-form-grid">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="save_hero">
            <label class="admin-field"><span>Eyebrow</span><input name="homepage_eyebrow" value="<?= e($heroEyebrow) ?>"></label>
            <label class="admin-field"><span>First title line</span><input name="homepage_title_line_1" value="<?= e($heroTitle1) ?>"></label>
            <label class="admin-field"><span>Gradient title line</span><input name="homepage_title_line_2" value="<?= e($heroTitle2) ?>"></label>
            <label class="admin-field"><span>Trust text</span><input name="homepage_trust_text" value="<?= e($trustText) ?>"></label>
            <label class="admin-field admin-field-wide"><span>Subtitle</span><textarea name="homepage_subtitle" rows="3"><?= e($heroSubtitle) ?></textarea></label>
            <label class="admin-field"><span>Primary CTA label</span><input name="homepage_primary_cta_label" value="<?= e($primaryLabel) ?>"></label>
            <label class="admin-field"><span>Primary CTA URL</span><input name="homepage_primary_cta_url" value="<?= e($primaryUrl) ?>"></label>
            <label class="admin-field"><span>Secondary CTA label</span><input name="homepage_secondary_cta_label" value="<?= e($secondaryLabel) ?>"></label>
            <label class="admin-field"><span>Secondary CTA URL</span><input name="homepage_secondary_cta_url" value="<?= e($secondaryUrl) ?>"></label>
            <label class="admin-field admin-field-wide"><span>Hero image path</span><input name="homepage_hero_image" value="<?= e($heroImage) ?>" placeholder="assets/uploads/admin_media/2026/04/image.webp"></label>
            <?php if ($media): ?>
                <div class="admin-field admin-field-wide"><span>Recent media quick copy</span><div class="admin-chip-cloud"><?php foreach (array_slice($media, 0, 8) as $asset): ?><code><?= e((string) $asset['public_path']) ?></code><?php endforeach; ?></div></div>
            <?php endif; ?>
            <div class="admin-form-actions"><button class="admin-primary-btn" type="submit">Save hero</button></div>
        </form>
    </article>

    <aside class="admin-card glass-panel admin-home-preview-card">
        <div class="admin-section-head"><div><p class="admin-eyebrow">Preview</p><h2>Hero preview</h2></div></div>
        <div class="admin-home-preview">
            <span><?= e($heroEyebrow) ?></span>
            <h3><?= e($heroTitle1) ?><br><em><?= e($heroTitle2) ?></em></h3>
            <p><?= e($heroSubtitle) ?></p>
            <div><a><?= e($primaryLabel) ?></a><a><?= e($secondaryLabel) ?></a></div>
            <small><?= e($trustText) ?></small>
        </div>
    </aside>
</section>

<section class="admin-card glass-panel">
    <div class="admin-section-head"><div><p class="admin-eyebrow">Featured products</p><h2>Homepage product slots</h2></div></div>
    <form method="post" class="admin-slot-list">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="save_slots">
        <?php foreach ($slots as $slot): ?>
            <article class="admin-slot-card">
                <div class="admin-slot-card-head">
                    <div><strong><?= e(ucfirst((string) $slot['slot_key'])) ?></strong><span><?= $slot['product_name'] ? e((string) $slot['product_name']) : 'No product selected' ?></span></div>
                    <label><input type="checkbox" name="slots[<?= (int) $slot['id'] ?>][is_active]" <?= (int) $slot['is_active'] === 1 ? 'checked' : '' ?>> Active</label>
                </div>
                <div class="admin-form-grid admin-form-compact">
                    <label class="admin-field"><span>Product</span><select name="slots[<?= (int) $slot['id'] ?>][product_id]"><option value="0">No product</option><?php foreach ($products as $product): ?><option value="<?= (int) $product['id'] ?>" <?= (int) ($slot['product_id'] ?? 0) === (int) $product['id'] ? 'selected' : '' ?>><?= e((string) $product['name']) ?><?= (int) $product['is_active'] !== 1 ? ' — hidden' : '' ?></option><?php endforeach; ?></select></label>
                    <label class="admin-field"><span>Sort order</span><input type="number" name="slots[<?= (int) $slot['id'] ?>][sort_order]" value="<?= (int) $slot['sort_order'] ?>"></label>
                    <label class="admin-field"><span>Badge override</span><input name="slots[<?= (int) $slot['id'] ?>][badge_override]" value="<?= e((string) ($slot['badge_override'] ?? '')) ?>"></label>
                    <label class="admin-field"><span>Title override</span><input name="slots[<?= (int) $slot['id'] ?>][title_override]" value="<?= e((string) ($slot['title_override'] ?? '')) ?>"></label>
                    <label class="admin-field admin-field-wide"><span>Subtitle override</span><input name="slots[<?= (int) $slot['id'] ?>][subtitle_override]" value="<?= e((string) ($slot['subtitle_override'] ?? '')) ?>"></label>
                </div>
            </article>
        <?php endforeach; ?>
        <div class="admin-form-actions"><button class="admin-primary-btn" type="submit">Save featured slots</button></div>
    </form>
</section>

<section class="admin-grid admin-two-col-wide">
    <article class="admin-card glass-panel">
        <div class="admin-section-head"><div><p class="admin-eyebrow">Promotions</p><h2>Add homepage banner</h2></div></div>
        <form method="post" class="admin-form-grid">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="add_banner">
            <label class="admin-field"><span>Eyebrow</span><input name="eyebrow" placeholder="LIMITED TIME OFFER"></label>
            <label class="admin-field"><span>Title</span><input name="title" required placeholder="Trade in your old device"></label>
            <label class="admin-field admin-field-wide"><span>Subtitle</span><textarea name="subtitle" rows="3"></textarea></label>
            <label class="admin-field"><span>CTA label</span><input name="cta_label" placeholder="Calculate Value"></label>
            <label class="admin-field"><span>CTA URL</span><input name="cta_url" placeholder="deals.php"></label>
            <label class="admin-field"><span>Image path</span><input name="image_path" placeholder="assets/uploads/admin_media/..."></label>
            <label class="admin-field"><span>Sort order</span><input type="number" name="sort_order" value="10"></label>
            <div class="admin-check-row"><label><input type="checkbox" name="is_active" checked> Active</label></div>
            <div class="admin-form-actions"><button class="admin-primary-btn" type="submit">Add banner</button></div>
        </form>
    </article>

    <article class="admin-card glass-panel">
        <div class="admin-section-head"><div><p class="admin-eyebrow">Banner list</p><h2>Current banners</h2></div></div>
        <?php if (!$banners): ?>
            <?php admin_empty_state('No banners yet', 'Create a promotional banner to control homepage campaigns from admin.'); ?>
        <?php else: ?>
            <div class="admin-banner-list">
                <?php foreach ($banners as $banner): ?>
                    <form method="post" class="admin-banner-edit-card">
                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="banner_id" value="<?= (int) $banner['id'] ?>">
                        <input type="hidden" name="action" value="update_banner">
                        <div class="admin-banner-row-head"><strong><?= e((string) $banner['title']) ?></strong><label><input type="checkbox" name="is_active" <?= (int) $banner['is_active'] === 1 ? 'checked' : '' ?>> Active</label></div>
                        <div class="admin-form-grid admin-form-compact">
                            <label class="admin-field"><span>Eyebrow</span><input name="eyebrow" value="<?= e((string) ($banner['eyebrow'] ?? '')) ?>"></label>
                            <label class="admin-field"><span>Title</span><input name="title" value="<?= e((string) $banner['title']) ?>"></label>
                            <label class="admin-field admin-field-wide"><span>Subtitle</span><input name="subtitle" value="<?= e((string) ($banner['subtitle'] ?? '')) ?>"></label>
                            <label class="admin-field"><span>CTA label</span><input name="cta_label" value="<?= e((string) ($banner['cta_label'] ?? '')) ?>"></label>
                            <label class="admin-field"><span>CTA URL</span><input name="cta_url" value="<?= e((string) ($banner['cta_url'] ?? '')) ?>"></label>
                            <label class="admin-field"><span>Image path</span><input name="image_path" value="<?= e((string) ($banner['image_path'] ?? '')) ?>"></label>
                            <label class="admin-field"><span>Sort order</span><input type="number" name="sort_order" value="<?= (int) $banner['sort_order'] ?>"></label>
                        </div>
                        <div class="admin-form-actions"><button class="admin-primary-btn" type="submit">Save</button><button class="admin-danger-link" type="submit" name="action" value="delete_banner">Delete</button></div>
                    </form>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </article>
</section>
<?php admin_footer(); ?>
