<?php
require_once __DIR__ . '/../includes/layout.php';
$ctx = admin_boot('media');
$pdo = $ctx['pdo'];

if (is_post_request()) {
    verify_csrf_or_fail($_POST['_csrf'] ?? null);
    $action = (string) ($_POST['admin_action'] ?? '');
    try {
        if ($action === 'media_upload') {
            admin_media_upload($pdo, $_FILES['asset'] ?? [], admin_clean_text('alt_text', 255), admin_clean_text('caption', 255));
            flash_set('success', 'Image uploaded to the media library.');
        } elseif ($action === 'media_update') {
            $id = admin_int_input('media_id');
            $asset = admin_media_find($pdo, $id);
            if (!$asset) {
                throw new RuntimeException('Media asset was not found.');
            }
            $stmt = $pdo->prepare('UPDATE media_assets SET alt_text = :alt_text, caption = :caption WHERE id = :id LIMIT 1');
            $stmt->execute([
                'alt_text' => admin_clean_text('alt_text', 255),
                'caption' => admin_clean_text('caption', 255),
                'id' => $id,
            ]);
            admin_log_activity($pdo, 'media_updated', 'media', $id, $asset['public_path']);
            flash_set('success', 'Media details updated.');
        } elseif ($action === 'media_delete') {
            $id = admin_int_input('media_id');
            $asset = admin_media_find($pdo, $id);
            if (!$asset) {
                throw new RuntimeException('Media asset was not found.');
            }
            $stmt = $pdo->prepare('DELETE FROM media_assets WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $id]);
            $diskPath = (string) ($asset['disk_path'] ?? '');
            if ($diskPath !== '' && str_starts_with($diskPath, admin_media_upload_base_dir()) && is_file($diskPath)) {
                @unlink($diskPath);
            }
            admin_log_activity($pdo, 'media_deleted', 'media', $id, $asset['public_path']);
            flash_set('success', 'Media asset deleted.');
        } elseif ($action === 'media_set_product_image') {
            $id = admin_int_input('media_id');
            $productId = admin_int_input('product_id');
            $asset = admin_media_find($pdo, $id);
            if (!$asset) {
                throw new RuntimeException('Media asset was not found.');
            }
            $product = admin_find_product($pdo, $productId);
            if (!$product) {
                throw new RuntimeException('Product was not found.');
            }
            $stmt = $pdo->prepare('UPDATE products SET image = :image WHERE id = :id LIMIT 1');
            $stmt->execute(['image' => $asset['public_path'], 'id' => $productId]);
            admin_log_activity($pdo, 'product_image_updated', 'product', $productId, $product['name'] . ' ← ' . $asset['public_path']);
            flash_set('success', 'Product image updated from media library.');
        } else {
            throw new RuntimeException('Unknown media action.');
        }
    } catch (Throwable $e) {
        admin_flash_from_exception($e);
    }
    admin_redirect('media');
}

$q = trim((string) ($_GET['q'] ?? ''));
$type = (string) ($_GET['type'] ?? 'all');
$where = [];
$params = [];
if ($q !== '') {
    $where[] = '(original_name LIKE :q OR alt_text LIKE :q OR caption LIKE :q OR public_path LIKE :q)';
    $params['q'] = '%' . $q . '%';
}
if ($type !== 'all' && in_array($type, ['jpg', 'png', 'webp', 'gif'], true)) {
    $where[] = 'extension = :extension';
    $params['extension'] = $type;
}
$sql = 'SELECT * FROM media_assets';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY created_at DESC, id DESC LIMIT 72';
$assets = admin_rows($pdo, $sql, $params);

$products = admin_rows($pdo, 'SELECT id, name, sku, image FROM products ORDER BY updated_at DESC, id DESC LIMIT 150');
$withoutImages = admin_rows($pdo, "SELECT id, name, sku FROM products WHERE image IS NULL OR image = '' ORDER BY updated_at DESC, id DESC LIMIT 12");
$metrics = [
    'assets' => (int) admin_scalar($pdo, 'SELECT COUNT(*) FROM media_assets'),
    'storage' => (int) admin_scalar($pdo, 'SELECT COALESCE(SUM(file_size),0) FROM media_assets'),
    'unassigned' => (int) admin_scalar($pdo, "SELECT COUNT(*) FROM products WHERE image IS NULL OR image = ''"),
    'webp' => (int) admin_scalar($pdo, "SELECT COUNT(*) FROM media_assets WHERE extension = 'webp'"),
];

admin_header('Media Library', 'Upload, organize, document, and reuse product images without leaving the admin console.', 'media');
?>
<section class="admin-metrics-grid">
    <?php admin_metric_card('Media assets', (string) $metrics['assets'], 'photo_library'); ?>
    <?php admin_metric_card('Storage used', admin_bytes_label($metrics['storage']), 'hard_drive'); ?>
    <?php admin_metric_card('Products without image', (string) $metrics['unassigned'], 'hide_image'); ?>
    <?php admin_metric_card('WEBP images', (string) $metrics['webp'], 'image'); ?>
</section>

<section class="admin-two-col admin-two-col-narrow">
    <article class="admin-card glass-panel">
        <div class="admin-section-head"><div><p class="admin-eyebrow">Upload</p><h2>Add image</h2></div></div>
        <form method="post" enctype="multipart/form-data" class="admin-form-grid admin-form-compact">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="admin_action" value="media_upload">
            <label class="admin-field admin-field-wide"><span>Image file</span><input type="file" name="asset" accept="image/jpeg,image/png,image/webp,image/gif" required></label>
            <label class="admin-field"><span>Alt text</span><input name="alt_text" placeholder="Short accessible description"></label>
            <label class="admin-field"><span>Caption</span><input name="caption" placeholder="Internal or public note"></label>
            <div class="admin-form-actions"><button class="admin-primary-btn" type="submit">Upload image</button></div>
        </form>
        <p class="admin-hint">Accepted: JPG, PNG, WEBP, GIF. Max size: 5 MB. Files are stored under <code>assets/uploads/admin_media/</code>.</p>
    </article>

    <article class="admin-card glass-panel">
        <div class="admin-section-head"><div><p class="admin-eyebrow">Catalog health</p><h2>Products missing images</h2></div><a class="admin-text-link" href="<?= e(admin_page_url('products')) ?>">Open products</a></div>
        <?php if (!$withoutImages): ?>
            <?php admin_empty_state('All products have images', 'Your visible catalog looks visually complete.'); ?>
        <?php else: ?>
            <div class="admin-compact-list">
                <?php foreach ($withoutImages as $product): ?>
                    <a href="<?= e(admin_page_url('products', ['edit' => (int) $product['id']])) ?>"><span><?= e($product['name']) ?><small><?= e($product['sku'] ?: 'No SKU') ?></small></span><strong>#<?= (int) $product['id'] ?></strong></a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </article>
</section>

<section class="admin-card glass-panel">
    <div class="admin-section-head"><div><p class="admin-eyebrow">Library</p><h2>Assets</h2></div></div>
    <form method="get" class="admin-filter-bar">
        <input name="q" value="<?= e($q) ?>" placeholder="Search images, alt text, captions...">
        <select name="type">
            <option value="all" <?= $type === 'all' ? 'selected' : '' ?>>All formats</option>
            <option value="jpg" <?= $type === 'jpg' ? 'selected' : '' ?>>JPG</option>
            <option value="png" <?= $type === 'png' ? 'selected' : '' ?>>PNG</option>
            <option value="webp" <?= $type === 'webp' ? 'selected' : '' ?>>WEBP</option>
            <option value="gif" <?= $type === 'gif' ? 'selected' : '' ?>>GIF</option>
        </select>
        <button class="admin-ghost-btn" type="submit">Filter</button>
    </form>

    <?php if (!$assets): ?>
        <?php admin_empty_state('No media found', 'Upload product images or adjust your filter.'); ?>
    <?php else: ?>
        <div class="admin-media-grid">
            <?php foreach ($assets as $asset): ?>
                <article class="admin-media-card">
                    <div class="admin-media-thumb"><img src="<?= e(admin_media_public_url($asset['public_path'])) ?>" alt="<?= e($asset['alt_text'] ?: $asset['original_name']) ?>"></div>
                    <div class="admin-media-meta">
                        <strong><?= e($asset['original_name']) ?></strong>
                        <small><?= e(strtoupper($asset['extension']) . ' · ' . admin_bytes_label((int) $asset['file_size']) . ' · ' . (int) $asset['width'] . '×' . (int) $asset['height']) ?></small>
                        <code><?= e($asset['public_path']) ?></code>
                    </div>
                    <form method="post" class="admin-media-edit-form">
                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="admin_action" value="media_update">
                        <input type="hidden" name="media_id" value="<?= (int) $asset['id'] ?>">
                        <input name="alt_text" value="<?= e($asset['alt_text'] ?? '') ?>" placeholder="Alt text">
                        <input name="caption" value="<?= e($asset['caption'] ?? '') ?>" placeholder="Caption">
                        <button class="admin-ghost-btn" type="submit">Save</button>
                    </form>
                    <form method="post" class="admin-media-attach-form">
                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="admin_action" value="media_set_product_image">
                        <input type="hidden" name="media_id" value="<?= (int) $asset['id'] ?>">
                        <select name="product_id" required>
                            <option value="">Set as product image...</option>
                            <?php foreach ($products as $product): ?>
                                <option value="<?= (int) $product['id'] ?>"><?= e($product['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit">Apply</button>
                    </form>
                    <form method="post" class="admin-media-delete-form">
                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="admin_action" value="media_delete">
                        <input type="hidden" name="media_id" value="<?= (int) $asset['id'] ?>">
                        <button class="admin-danger-link" type="submit">Delete image</button>
                    </form>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php admin_footer(); ?>
