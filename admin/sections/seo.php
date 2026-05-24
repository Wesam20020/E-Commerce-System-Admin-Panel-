<?php
require_once __DIR__ . '/../includes/layout.php';
$ctx = admin_boot('seo');
$pdo = $ctx['pdo'];
$settings = $ctx['siteSettings'];
$siteName = $ctx['siteName'];

admin_seed_default_site_pages($pdo);

if (is_post_request()) {
    verify_csrf_or_fail($_POST['_csrf'] ?? null);
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'save_global_seo') {
            $fields = [
                'site_meta_title' => 190,
                'site_meta_description' => 320,
                'site_default_og_image' => 500,
                'site_robots_policy' => 40,
                'site_structured_data_enabled' => 10,
            ];
            foreach ($fields as $key => $max) {
                if ($key === 'site_structured_data_enabled') {
                    admin_store_setting($pdo, $key, isset($_POST[$key]) ? '1' : '0');
                    continue;
                }
                admin_store_setting($pdo, $key, mb_substr(trim((string) ($_POST[$key] ?? '')), 0, $max));
            }
            admin_log_activity($pdo, 'global_seo_updated', 'seo', null, 'Updated global SEO defaults.');
            flash_set('success', 'Global SEO settings were saved.');
            admin_redirect('seo');
        }

        if ($action === 'save_page_seo') {
            $pageId = admin_int_input('page_id');
            $pageKey = preg_replace('/[^a-z0-9_-]+/i', '', (string) ($_POST['page_key'] ?? '')) ?: 'page';
            $pageLabel = admin_clean_text('page_label', 120) ?: ucfirst(str_replace('_', ' ', $pageKey));
            $metaTitle = admin_clean_text('meta_title', 190);
            $metaDescription = admin_clean_text('meta_description', 320);
            $canonicalUrl = admin_clean_text('canonical_url', 500);
            $ogImage = admin_clean_text('og_image', 500);
            $robotsIndex = isset($_POST['robots_index']) ? 1 : 0;
            $isActive = isset($_POST['is_active']) ? 1 : 0;

            if ($pageId > 0) {
                $stmt = $pdo->prepare('UPDATE site_pages SET page_key = :page_key, page_label = :page_label, meta_title = :meta_title, meta_description = :meta_description, canonical_url = :canonical_url, og_image = :og_image, robots_index = :robots_index, is_active = :is_active WHERE id = :id LIMIT 1');
                $stmt->execute([
                    'page_key' => mb_substr($pageKey, 0, 80),
                    'page_label' => $pageLabel,
                    'meta_title' => $metaTitle,
                    'meta_description' => $metaDescription,
                    'canonical_url' => $canonicalUrl,
                    'og_image' => $ogImage,
                    'robots_index' => $robotsIndex,
                    'is_active' => $isActive,
                    'id' => $pageId,
                ]);
                admin_log_activity($pdo, 'page_seo_updated', 'seo_page', $pageId, $pageLabel);
            } else {
                $stmt = $pdo->prepare('INSERT INTO site_pages (page_key, page_label, meta_title, meta_description, canonical_url, og_image, robots_index, is_active) VALUES (:page_key, :page_label, :meta_title, :meta_description, :canonical_url, :og_image, :robots_index, :is_active)');
                $stmt->execute([
                    'page_key' => mb_substr($pageKey, 0, 80),
                    'page_label' => $pageLabel,
                    'meta_title' => $metaTitle,
                    'meta_description' => $metaDescription,
                    'canonical_url' => $canonicalUrl,
                    'og_image' => $ogImage,
                    'robots_index' => $robotsIndex,
                    'is_active' => $isActive,
                ]);
                $pageId = (int) $pdo->lastInsertId();
                admin_log_activity($pdo, 'page_seo_created', 'seo_page', $pageId, $pageLabel);
            }
            flash_set('success', 'Page SEO was saved.');
            admin_redirect('seo');
        }

        if ($action === 'reset_defaults') {
            admin_seed_default_site_pages($pdo);
            admin_log_activity($pdo, 'seo_pages_seeded', 'seo', null, 'Seeded default storefront page records.');
            flash_set('success', 'Default page records were created or refreshed.');
            admin_redirect('seo');
        }
    } catch (Throwable $e) {
        admin_flash_from_exception($e, 'SEO settings could not be saved.');
        admin_redirect('seo');
    }
}

$pages = admin_site_pages($pdo);
$media = admin_rows($pdo, 'SELECT id, public_path, alt_text, original_name FROM media_assets ORDER BY created_at DESC, id DESC LIMIT 10');
$activePages = (int) admin_scalar($pdo, 'SELECT COUNT(*) FROM site_pages WHERE is_active = 1');
$indexedPages = (int) admin_scalar($pdo, 'SELECT COUNT(*) FROM site_pages WHERE robots_index = 1 AND is_active = 1');
$missingTitles = (int) admin_scalar($pdo, "SELECT COUNT(*) FROM site_pages WHERE is_active = 1 AND (meta_title IS NULL OR meta_title = '')");
$missingDescriptions = (int) admin_scalar($pdo, "SELECT COUNT(*) FROM site_pages WHERE is_active = 1 AND (meta_description IS NULL OR meta_description = '')");

$globalTitle = $settings['site_meta_title'] ?? ($siteName . ' | Future in Your Hands');
$globalDescription = $settings['site_meta_description'] ?? 'Experience premium phones, audio, wearables, and accessories from Phonix.';
$globalOg = $settings['site_default_og_image'] ?? '';
$robotsPolicy = $settings['site_robots_policy'] ?? 'index,follow';
$structuredEnabled = ($settings['site_structured_data_enabled'] ?? '1') === '1';

admin_header('SEO / Pages', 'Control public page metadata, default Open Graph assets, and basic indexing flags from the admin console.', 'seo');
?>
<section class="admin-metrics-grid">
    <?php admin_metric_card('Active pages', (string) $activePages, 'web', 'Public page records'); ?>
    <?php admin_metric_card('Indexable pages', (string) $indexedPages, 'travel_explore', 'robots index enabled'); ?>
    <?php admin_metric_card('Missing titles', (string) $missingTitles, 'title', 'Should be 0'); ?>
    <?php admin_metric_card('Missing descriptions', (string) $missingDescriptions, 'notes', 'Should be 0'); ?>
</section>

<section class="admin-grid admin-two-col-wide">
    <article class="admin-card glass-panel">
        <div class="admin-section-head"><div><p class="admin-eyebrow">Global defaults</p><h2>Default SEO settings</h2></div></div>
        <form method="post" class="admin-form-grid">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="save_global_seo">
            <label class="admin-field admin-field-wide"><span>Default meta title</span><input name="site_meta_title" maxlength="190" value="<?= e($globalTitle) ?>"></label>
            <label class="admin-field admin-field-wide"><span>Default meta description</span><textarea name="site_meta_description" rows="3" maxlength="320"><?= e($globalDescription) ?></textarea></label>
            <label class="admin-field admin-field-wide"><span>Default OG image path</span><input name="site_default_og_image" maxlength="500" value="<?= e($globalOg) ?>" placeholder="assets/uploads/admin_media/2026/04/image.webp"></label>
            <label class="admin-field"><span>Robots default</span><select name="site_robots_policy"><option value="index,follow" <?= $robotsPolicy === 'index,follow' ? 'selected' : '' ?>>index, follow</option><option value="noindex,nofollow" <?= $robotsPolicy === 'noindex,nofollow' ? 'selected' : '' ?>>noindex, nofollow</option></select></label>
            <div class="admin-check-row"><label><input type="checkbox" name="site_structured_data_enabled" <?= $structuredEnabled ? 'checked' : '' ?>> Enable structured data where available</label></div>
            <?php if ($media): ?><div class="admin-field admin-field-wide"><span>Recent media paths</span><div class="admin-chip-cloud"><?php foreach ($media as $asset): ?><code><?= e((string) $asset['public_path']) ?></code><?php endforeach; ?></div></div><?php endif; ?>
            <div class="admin-form-actions"><button class="admin-primary-btn" type="submit">Save defaults</button></div>
        </form>
    </article>

    <aside class="admin-card glass-panel">
        <div class="admin-section-head"><div><p class="admin-eyebrow">Preview</p><h2>Search snippet</h2></div></div>
        <div class="admin-seo-preview">
            <span><?= e($globalTitle) ?></span>
            <strong><?= e($globalTitle) ?></strong>
            <p><?= e($globalDescription) ?></p>
        </div>
        <form method="post" class="admin-form-actions admin-form-actions-left">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="reset_defaults">
            <button class="admin-ghost-btn" type="submit">Refresh default pages</button>
        </form>
    </aside>
</section>

<section class="admin-card glass-panel">
    <div class="admin-section-head"><div><p class="admin-eyebrow">Public pages</p><h2>Page metadata</h2></div></div>
    <div class="admin-table-wrap"><table class="admin-table admin-seo-table"><thead><tr><th>Page</th><th>Metadata</th><th>Robots</th><th>Action</th></tr></thead><tbody>
        <?php foreach ($pages as $page): ?>
            <tr>
                <td><strong><?= e((string) $page['page_label']) ?></strong><small><?= e((string) $page['page_key']) ?> · <?= e((string) ($page['canonical_url'] ?: 'No canonical')) ?></small></td>
                <td><span class="admin-seo-title"><?= e((string) ($page['meta_title'] ?: 'No custom title')) ?></span><small><?= e((string) ($page['meta_description'] ?: 'No custom description')) ?></small></td>
                <td><span class="admin-pill <?= (int) $page['robots_index'] === 1 ? 'good' : 'danger' ?>"><?= (int) $page['robots_index'] === 1 ? 'index' : 'noindex' ?></span><span class="admin-pill <?= (int) $page['is_active'] === 1 ? 'info' : 'danger' ?>"><?= (int) $page['is_active'] === 1 ? 'active' : 'hidden' ?></span></td>
                <td><a class="admin-text-link" href="#seo-page-<?= (int) $page['id'] ?>">Edit</a></td>
            </tr>
        <?php endforeach; ?>
    </tbody></table></div>
</section>

<section class="admin-grid admin-two-col-wide">
    <?php foreach ($pages as $page): ?>
        <article class="admin-card glass-panel" id="seo-page-<?= (int) $page['id'] ?>">
            <div class="admin-section-head"><div><p class="admin-eyebrow">Page SEO</p><h2><?= e((string) $page['page_label']) ?></h2></div></div>
            <form method="post" class="admin-form-grid admin-form-compact">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="save_page_seo">
                <input type="hidden" name="page_id" value="<?= (int) $page['id'] ?>">
                <label class="admin-field"><span>Page key</span><input name="page_key" value="<?= e((string) $page['page_key']) ?>"></label>
                <label class="admin-field"><span>Page label</span><input name="page_label" value="<?= e((string) $page['page_label']) ?>"></label>
                <label class="admin-field admin-field-wide"><span>Meta title</span><input name="meta_title" maxlength="190" value="<?= e((string) ($page['meta_title'] ?? '')) ?>"></label>
                <label class="admin-field admin-field-wide"><span>Meta description</span><textarea name="meta_description" rows="3" maxlength="320"><?= e((string) ($page['meta_description'] ?? '')) ?></textarea></label>
                <label class="admin-field admin-field-wide"><span>Canonical URL / path</span><input name="canonical_url" maxlength="500" value="<?= e((string) ($page['canonical_url'] ?? '')) ?>"></label>
                <label class="admin-field admin-field-wide"><span>OG image path</span><input name="og_image" maxlength="500" value="<?= e((string) ($page['og_image'] ?? '')) ?>"></label>
                <div class="admin-check-row"><label><input type="checkbox" name="robots_index" <?= (int) $page['robots_index'] === 1 ? 'checked' : '' ?>> Robots index</label><label><input type="checkbox" name="is_active" <?= (int) $page['is_active'] === 1 ? 'checked' : '' ?>> Active</label></div>
                <div class="admin-form-actions"><button class="admin-primary-btn" type="submit">Save page</button></div>
            </form>
        </article>
    <?php endforeach; ?>
</section>

<?php admin_footer(); ?>
