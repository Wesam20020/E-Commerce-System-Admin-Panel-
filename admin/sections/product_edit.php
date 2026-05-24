<?php
require_once __DIR__ . '/../includes/layout.php';
$ctx = admin_boot('product_edit');
$pdo = $ctx['pdo'];
$currency = $ctx['siteCurrency'];

$productId = isset($_GET['id']) && is_numeric($_GET['id']) ? (int) $_GET['id'] : 0;
$product = $productId > 0 ? admin_find_product($pdo, $productId) : null;
if ($productId > 0 && !$product) {
    flash_set('error', 'Product was not found.');
    admin_redirect('products');
}

if (is_post_request()) {
    verify_csrf_or_fail($_POST['_csrf'] ?? null);
    $action = (string) ($_POST['admin_action'] ?? '');
    $saveNext = (string) ($_POST['save_next'] ?? 'stay');
    if (!in_array($saveNext, ['stay', 'products', 'view'], true)) {
        $saveNext = 'stay';
    }
    try {
        if (!in_array($action, ['product_create', 'product_update'], true)) {
            throw new RuntimeException('Unknown product editor action.');
        }
        $isUpdate = $action === 'product_update';
        $id = admin_int_input('product_id');
        if ($isUpdate && $id <= 0) {
            throw new RuntimeException('Product id is required.');
        }
        if ($isUpdate && !admin_find_product($pdo, $id)) {
            throw new RuntimeException('Product was not found.');
        }

        $name = admin_clean_text('name', 190);
        if ($name === '') {
            throw new RuntimeException('Product name is required.');
        }

        $status = admin_product_status_from_post('product_status');
        $type = admin_product_type_from_post('product_type');
        $stock = admin_int_input('stock');
        if ($stock < 0) {
            $stock = 0;
        }
        if ($status === 'out_of_stock') {
            $stock = 0;
        }

        $price = admin_decimal_input('price');
        $discountPercentRaw = trim((string) ($_POST['discount_percent'] ?? ''));
        $discountPercent = $discountPercentRaw === '' ? 0.0 : round((float) str_replace(',', '.', $discountPercentRaw), 2);
        if ($price < 0) {
            throw new RuntimeException('Price cannot be negative.');
        }
        if ($discountPercent < 0 || $discountPercent > 95) {
            throw new RuntimeException('Discount must be between 0 and 95%.');
        }
        $comparePrice = null;
        if ($discountPercent > 0 && $price > 0) {
            $comparePrice = round($price / (1 - ($discountPercent / 100)), 2);
        }

        $image = admin_clean_text('image', 500) ?: null;
        $primaryImagePath = admin_clean_text('primary_image_path', 500);
        if ($primaryImagePath !== '' && $primaryImagePath !== '__typed') {
            $image = $primaryImagePath;
        }

        $galleryPaths = [];
        $existingPaths = $_POST['gallery_existing_paths'] ?? [];
        if (!is_array($existingPaths)) {
            $existingPaths = [];
        }
        foreach ($existingPaths as $path) {
            $path = trim((string) $path);
            if ($path !== '' && mb_strlen($path) <= 500 && !in_array($path, $galleryPaths, true)) {
                $galleryPaths[] = $path;
            }
        }

        $typedPaths = admin_product_gallery_from_post($pdo, 'gallery_paths', 'gallery_media_ids');
        foreach ($typedPaths as $path) {
            if ($path !== '' && !in_array($path, $galleryPaths, true)) {
                $galleryPaths[] = $path;
            }
        }

        $galleryUploadIds = admin_media_upload_many($pdo, $_FILES['gallery_image_uploads'] ?? [], $name, 'Uploaded from product editor gallery.');
        foreach ($galleryUploadIds as $galleryUploadId) {
            $asset = admin_media_find($pdo, (int) $galleryUploadId);
            if ($asset && !empty($asset['public_path']) && !in_array((string) $asset['public_path'], $galleryPaths, true)) {
                $galleryPaths[] = (string) $asset['public_path'];
            }
        }

        $primaryMediaId = admin_int_input('primary_media_id');
        if ($primaryMediaId > 0) {
            $asset = admin_media_find($pdo, $primaryMediaId);
            if (!$asset) {
                throw new RuntimeException('Selected main image was not found in the media library.');
            }
            $image = (string) $asset['public_path'];
        }

        $mainImageUpload = $_FILES['main_image_upload'] ?? [];
        if (($mainImageUpload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $mainUploadId = admin_media_upload($pdo, $mainImageUpload, $name, 'Uploaded as product main image.');
            $asset = admin_media_find($pdo, $mainUploadId);
            if (!$asset || empty($asset['public_path'])) {
                throw new RuntimeException('Uploaded main image could not be attached to this product.');
            }
            $image = (string) $asset['public_path'];
        }

        if ($image && !in_array($image, $galleryPaths, true)) {
            array_unshift($galleryPaths, $image);
        }
        $galleryPaths = array_values(array_unique(array_filter($galleryPaths, static fn($path) => trim((string) $path) !== '')));
        $galleryJson = $galleryPaths ? json_encode($galleryPaths, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;
        $specsJson = admin_product_specs_from_editor_post($type, 'specs_text', 'typed_specs');
        $benefitsJson = admin_product_benefits_from_post();
        $variantRows = admin_product_variants_from_post();

        $payload = [
            'category_id' => admin_int_input('category_id') ?: null,
            'name' => $name,
            'slug' => admin_unique_slug($pdo, 'products', admin_clean_text('slug', 190) ?: $name, $isUpdate ? $id : null),
            'sku' => admin_clean_text('sku', 120) ?: null,
            'brand' => admin_clean_text('brand', 150) ?: null,
            'badge' => admin_clean_text('badge', 120) ?: null,
            'product_type' => $type,
            'short_description' => admin_clean_text('short_description', 1000) ?: null,
            'description' => trim((string) ($_POST['description'] ?? '')) ?: null,
            'specs_json' => $specsJson,
            'benefits_json' => $benefitsJson,
            'price' => $price,
            'compare_price' => $comparePrice,
            'stock' => $stock,
            'rating' => min(5, max(0, admin_decimal_input('rating'))),
            'image' => $image,
            'gallery_json' => $galleryJson,
            'product_status' => $status,
            'is_active' => admin_product_is_live_status($status),
            'is_featured' => admin_bool_from_post('is_featured'),
        ];

        if ($isUpdate) {
            $payload['id'] = $id;
            $stmt = $pdo->prepare('UPDATE products SET category_id=:category_id, name=:name, slug=:slug, sku=:sku, brand=:brand, badge=:badge, product_type=:product_type, short_description=:short_description, description=:description, specs_json=:specs_json, benefits_json=:benefits_json, price=:price, compare_price=:compare_price, stock=:stock, rating=:rating, image=:image, gallery_json=:gallery_json, product_status=:product_status, is_active=:is_active, is_featured=:is_featured WHERE id=:id LIMIT 1');
            $stmt->execute($payload);
            admin_replace_product_variants($pdo, $id, $variantRows);
            admin_log_activity($pdo, 'product_updated', 'product', $id, $name);
            flash_set('success', 'Product saved successfully.');
            if ($saveNext === 'view') {
                redirect_to(admin_root_url('product.php', ['slug' => $payload['slug']]));
            }
            if ($saveNext === 'products') {
                admin_redirect('products');
            }
            admin_redirect('product_edit', ['id' => $id]);
        }

        $stmt = $pdo->prepare('INSERT INTO products (category_id, name, slug, sku, brand, badge, product_type, short_description, description, specs_json, benefits_json, price, compare_price, stock, rating, image, gallery_json, product_status, is_active, is_featured) VALUES (:category_id, :name, :slug, :sku, :brand, :badge, :product_type, :short_description, :description, :specs_json, :benefits_json, :price, :compare_price, :stock, :rating, :image, :gallery_json, :product_status, :is_active, :is_featured)');
        $stmt->execute($payload);
        $newId = (int) $pdo->lastInsertId();
        admin_replace_product_variants($pdo, $newId, $variantRows);
        admin_log_activity($pdo, 'product_created', 'product', $newId, $name);
        flash_set('success', 'Product created successfully.');
        if ($saveNext === 'view') {
            redirect_to(admin_root_url('product.php', ['slug' => $payload['slug']]));
        }
        if ($saveNext === 'products') {
            admin_redirect('products');
        }
        admin_redirect('product_edit', ['id' => $newId]);
    } catch (Throwable $e) {
        admin_flash_from_exception($e);
        admin_redirect('product_edit', $productId > 0 ? ['id' => $productId] : []);
    }
}

$categories = admin_rows($pdo, 'SELECT id, name, slug FROM categories ORDER BY name ASC');
$brands = admin_rows($pdo, "SELECT DISTINCT brand FROM products WHERE brand IS NOT NULL AND brand <> '' ORDER BY brand ASC");
$recentMedia = admin_rows($pdo, 'SELECT id, public_path, original_name, alt_text, width, height FROM media_assets ORDER BY created_at DESC, id DESC LIMIT 36');
$isEdit = (bool) $product;
$gallery = admin_product_gallery_list($product['gallery_json'] ?? null);
if ($isEdit && !empty($product['image']) && !in_array((string) $product['image'], $gallery, true)) {
    array_unshift($gallery, (string) $product['image']);
}
$productType = (string) ($product['product_type'] ?? 'general');
if (!array_key_exists($productType, admin_product_type_options())) {
    $productType = 'general';
}
$statusValue = admin_product_status_value($product['product_status'] ?? null, (int) ($product['is_active'] ?? 1), (int) ($product['stock'] ?? 0));
$specList = admin_product_specs_list($product['specs_json'] ?? null);
$benefitList = admin_product_benefits_list($product['benefits_json'] ?? null);
if (!$benefitList) {
    $benefitList = [''];
}
$specNames = [];
foreach ($specList as $spec) {
    $specNames[mb_strtolower((string) $spec['name'])] = true;
}
foreach (admin_product_spec_template_for($productType) as $templateField) {
    $key = mb_strtolower($templateField);
    if (!isset($specNames[$key])) {
        $specList[] = ['name' => $templateField, 'value' => ''];
        $specNames[$key] = true;
    }
}
if (!$specList) {
    $specList[] = ['name' => '', 'value' => ''];
}
$variantRows = $isEdit ? admin_product_variant_rows($pdo, (int) $product['id']) : [];
if (!$variantRows) {
    $variantRows = [
        ['option_type' => 'storage', 'option_value' => ''],
        ['option_type' => 'color', 'option_value' => ''],
    ];
}
$variantGroups = [];
foreach ($variantRows as $variantRow) {
    $variantType = trim((string) ($variantRow['option_type'] ?? ''));
    $variantValue = trim((string) ($variantRow['option_value'] ?? ''));
    if ($variantType !== '' && $variantValue !== '') {
        $variantGroups[$variantType][] = $variantValue;
    }
}
$specTemplatesJson = json_encode(admin_product_spec_templates(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$viewUrl = $isEdit && !empty($product['slug']) ? admin_root_url('product.php', ['slug' => $product['slug']]) : '';
$price = (float) ($product['price'] ?? 0);
$comparePrice = (float) ($product['compare_price'] ?? 0);
$discountPercent = ($comparePrice > $price && $comparePrice > 0) ? round((($comparePrice - $price) / $comparePrice) * 100, 2) : 0;
$discountDisplay = $discountPercent > 0 ? rtrim(rtrim(number_format($discountPercent, 2, '.', ''), '0'), '.') : '';
$mainImage = (string) ($product['image'] ?? '');
$readyItems = [
    'name' => ['Has product name', trim((string) ($product['name'] ?? '')) !== ''],
    'price' => ['Has valid price', $price > 0],
    'image' => ['Has main image', $mainImage !== ''],
    'description' => ['Has short description', trim((string) ($product['short_description'] ?? '')) !== ''],
    'category' => ['Assigned to category', (int) ($product['category_id'] ?? 0) > 0],
    'specs' => ['Has visible specifications', (bool) array_filter($specList, static fn($spec) => trim((string) ($spec['name'] ?? '')) !== '' && trim((string) ($spec['value'] ?? '')) !== '')],
    'benefits' => ['Has key benefits', (bool) array_filter($benefitList, static fn($benefit) => trim((string) $benefit) !== '')],
    'storefront' => ['Visible storefront status', in_array($statusValue, ['active', 'out_of_stock'], true)],
];
$readyCount = count(array_filter($readyItems, static fn($item) => (bool) $item[1]));
$readyTotal = count($readyItems);
$readyState = $readyCount === $readyTotal ? 'Ready to publish' : ($readyCount >= 4 ? 'Almost ready' : 'Needs work');

admin_header($isEdit ? 'Edit product' : 'New product', $isEdit ? 'Update the fields employees use every day. Secondary controls are tucked away to keep the editor clean.' : 'Create a new product from the essential storefront fields first, then open advanced sections only when needed.', 'products');
?>
<section class="admin-product-edit-hero glass-panel admin-product-edit-hero-compact">
    <div class="admin-product-edit-hero-copy">
        <p class="admin-eyebrow">Focused editor</p>
        <h2><?= $isEdit ? e($product['name']) : 'Create product' ?></h2>
        <p><?= $isEdit ? 'Only daily-use fields stay visible. Advanced controls open when needed.' : 'Start with the essentials. Add advanced details only when the product needs them.' ?></p>
    </div>
    <div class="admin-product-edit-actions">
        <?php if ($viewUrl !== ''): ?><a class="admin-primary-btn" href="<?= e($viewUrl) ?>" target="_blank" rel="noopener"><span class="material-symbols-outlined">visibility</span>View</a><?php endif; ?>
        <a class="admin-ghost-btn" href="<?= e(admin_page_url('products')) ?>"><span class="material-symbols-outlined">arrow_back</span>Products</a>
    </div>
</section>

<?php if ($isEdit): ?>
<section class="admin-editor-status-strip glass-panel" aria-label="Product quick status">
    <span><strong>Status</strong><?= e(admin_product_status_label($statusValue)) ?></span>
    <span><strong>Type</strong><?= e(admin_product_type_label($productType)) ?></span>
    <span><strong>Stock</strong><?= e((string) (int) ($product['stock'] ?? 0)) ?></span>
    <span><strong>Ready</strong><?= e($readyCount . '/' . $readyTotal) ?> · <?= e($readyState) ?></span>
</section>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="admin-product-edit-layout admin-product-editor-pro" data-product-editor data-spec-templates="<?= e((string) $specTemplatesJson) ?>" data-currency="<?= e($currency) ?>">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="admin_action" value="<?= $isEdit ? 'product_update' : 'product_create' ?>">
    <input type="hidden" name="product_id" value="<?= (int) ($product['id'] ?? 0) ?>">

    <div class="admin-product-edit-main">
        <nav class="admin-product-tabs glass-panel" data-product-tabs aria-label="Product editor tabs">
            <button type="button" class="is-active" data-product-tab="overview">Basics <span class="admin-tab-issue-count" data-tab-issue-count="overview" hidden>0</span></button>
            <button type="button" data-product-tab="pricing">Price <span class="admin-tab-issue-count" data-tab-issue-count="pricing" hidden>0</span></button>
            <button type="button" data-product-tab="options">Options <span class="admin-tab-issue-count" data-tab-issue-count="options" hidden>0</span></button>
            <button type="button" data-product-tab="media">Media <span class="admin-tab-issue-count" data-tab-issue-count="media" hidden>0</span></button>
            <button type="button" data-product-tab="descriptions">Content <span class="admin-tab-issue-count" data-tab-issue-count="descriptions" hidden>0</span></button>
            <button type="button" data-product-tab="specs">Specs <span class="admin-tab-issue-count" data-tab-issue-count="specs" hidden>0</span></button>
        </nav>

        <section class="admin-card glass-panel admin-editor-validation" data-product-validation-summary hidden>
            <div>
                <p class="admin-eyebrow">Required fixes</p>
                <strong>Fix these fields before saving.</strong>
            </div>
            <ul data-product-validation-list></ul>
        </section>

        <section class="admin-product-tab-panel is-active" data-product-tab-panel="overview" data-editor-tab="overview">
            <div class="admin-card glass-panel admin-editor-card">
                <div class="admin-section-head"><div><p class="admin-eyebrow">Product identity</p><h2>What the visitor sees first</h2></div></div>
                <div class="admin-form-grid admin-editor-essential-grid">
                    <label class="admin-field admin-field-wide"><span>Name</span><input name="name" value="<?= e($product['name'] ?? '') ?>" required placeholder="Phonix Aero Pro Max" data-preview-name data-field-label="Product name"><small data-field-error="name"></small></label>
                    <label class="admin-field"><span>Product type</span><select name="product_type" data-product-type-select><?php foreach (admin_product_type_options() as $value => $label): ?><option value="<?= e($value) ?>" <?= $productType === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
                    <label class="admin-field"><span>Status</span><select name="product_status" data-preview-status><?php foreach (admin_product_status_options() as $value => $label): ?><option value="<?= e($value) ?>" <?= $statusValue === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
                    <label class="admin-field"><span>Category</span><select name="category_id" data-ready-category><option value="0">No category</option><?php foreach ($categories as $cat): ?><option value="<?= (int) $cat['id'] ?>" <?= (int)($product['category_id'] ?? 0) === (int)$cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option><?php endforeach; ?></select></label>
                    <label class="admin-field"><span>Brand</span><input name="brand" list="brand-options" value="<?= e($product['brand'] ?? '') ?>" data-preview-brand></label><datalist id="brand-options"><?php foreach ($brands as $b): ?><option value="<?= e($b['brand']) ?>"></option><?php endforeach; ?></datalist>
                    <details class="admin-editor-accordion admin-field-wide">
                        <summary><span>Advanced identity</span><small>Slug, SKU, badge, featured state</small></summary>
                        <div class="admin-form-grid admin-editor-accordion-grid">
                            <label class="admin-field"><span>Slug</span><div class="admin-inline-control"><input name="slug" value="<?= e($product['slug'] ?? '') ?>" placeholder="Auto-generated if empty" data-slug-input data-field-label="Slug"><button type="button" class="admin-table-btn" data-product-slug-generate>Generate</button></div><small>Changing the slug changes the product URL.</small><small data-field-error="slug"></small></label>
                            <label class="admin-field"><span>SKU</span><input name="sku" value="<?= e($product['sku'] ?? '') ?>" placeholder="PX-AERO-001"></label>
                            <label class="admin-field"><span>Badge</span><input name="badge" value="<?= e($product['badge'] ?? '') ?>" placeholder="New, Deal, Featured" data-preview-badge></label>
                            <label class="admin-field admin-check-row"><input type="checkbox" name="is_featured" <?= (int)($product['is_featured'] ?? 0) === 1 ? 'checked' : '' ?>><span>Featured product</span></label>
                        </div>
                    </details>
                </div>
            </div>
        </section>

        <section class="admin-product-tab-panel" data-product-tab-panel="pricing" data-editor-tab="pricing" hidden>
            <div class="admin-card glass-panel admin-editor-card">
                <div class="admin-section-head"><div><p class="admin-eyebrow">Purchase block</p><h2>Price, stock, and button state</h2></div></div>
                <div class="admin-form-grid">
                    <label class="admin-field"><span>Price</span><input type="number" step="0.01" min="0" name="price" value="<?= e((string) ($product['price'] ?? '0.00')) ?>" data-preview-price data-field-label="Price"><small data-field-error="price"></small></label>
                    <label class="admin-field"><span>Discount %</span><input type="number" step="0.01" min="0" max="95" name="discount_percent" value="<?= e($discountDisplay) ?>" data-preview-discount-input data-field-label="Discount percentage"><small data-price-warning><?= $discountPercent > 0 ? e($discountDisplay . '% discount will be shown in preview.') : 'Write 0 or leave empty to keep the product at regular price.' ?></small><small data-field-error="discount_percent"></small></label>
                    <label class="admin-field"><span>Stock</span><input type="number" min="0" name="stock" value="<?= e((string) ($product['stock'] ?? '0')) ?>" data-preview-stock data-field-label="Stock"><small data-stock-hint>Stock controls whether the visitor can buy this product.</small><small data-field-error="stock"></small></label>
                    <details class="admin-editor-accordion admin-field-wide">
                        <summary><span>Advanced pricing</span><small>Rating and secondary display values</small></summary>
                        <div class="admin-form-grid admin-editor-accordion-grid">
                            <label class="admin-field"><span>Rating</span><input type="number" min="0" max="5" step="0.1" name="rating" value="<?= e((string) ($product['rating'] ?? '0')) ?>"></label>
                        </div>
                    </details>
                </div>
                <div class="admin-editor-note"><span class="material-symbols-outlined">info</span><p>If stock is zero, keep the status as <strong>Out of stock</strong> to show the product without allowing purchase.</p></div>
                <div class="admin-status-advisor" data-product-status-advisor hidden>
                    <span class="material-symbols-outlined">tips_and_updates</span>
                    <p data-product-status-advisor-text></p>
                    <button type="button" class="admin-table-btn" data-product-status-suggestion></button>
                </div>
            </div>
        </section>

        <section class="admin-product-tab-panel" data-product-tab-panel="options" data-editor-tab="options" hidden>
            <div class="admin-card glass-panel admin-editor-card">
                <div class="admin-section-head">
                    <div>
                        <p class="admin-eyebrow">Visible product options</p>
                        <h2>Storage, color, and selectable choices</h2>
                        <small class="admin-editor-subtitle"><span data-variant-filled-count>0</span> options will appear on the product page as selectors.</small>
                    </div>
                    <div class="admin-page-actions admin-editor-mini-actions">
                        <button type="button" class="admin-primary-btn" data-variant-add-row>Add option</button>
                        <details class="admin-more-menu">
                            <summary><span>More</span><span class="material-symbols-outlined">expand_more</span></summary>
                            <div class="admin-more-panel">
                                <button type="button" data-variant-import>Import pasted</button>
                                <button type="button" data-variant-clean>Clean rows</button>
                                <button type="button" data-variant-preset="storage">Storage set</button>
                                <button type="button" data-variant-preset="color">Color set</button>
                                <button type="button" data-variant-preset="size">Size set</button>
                                <button type="button" data-variant-preset="finish">Finish set</button>
                            </div>
                        </details>
                    </div>
                </div>
                <div class="admin-editor-note"><span class="material-symbols-outlined">tune</span><p>These choices are the option buttons shown on the public product page. Leave this section empty for products that do not need selectable options.</p></div>
                <div class="admin-variants-editor" data-variant-rows>
                    <?php foreach ($variantRows as $variantRow): ?>
                        <div class="admin-variant-row" data-variant-row>
                            <input name="variant_types[]" value="<?= e($variantRow['option_type'] ?? '') ?>" placeholder="Option type, e.g. storage or color" aria-label="Option type" list="variant-type-options">
                            <input name="variant_values[]" value="<?= e($variantRow['option_value'] ?? '') ?>" placeholder="Option value, e.g. 256GB or Midnight Black" aria-label="Option value">
                            <div class="admin-variant-actions"><details class="admin-more-menu admin-row-more"><summary aria-label="Option row actions"><span class="material-symbols-outlined">more_horiz</span></summary><div class="admin-more-panel"><button type="button" data-variant-move="up">Move up</button><button type="button" data-variant-move="down">Move down</button><button type="button" class="danger" data-variant-remove>Remove</button></div></details></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <datalist id="variant-type-options">
                    <option value="storage"></option>
                    <option value="color"></option>
                    <option value="size"></option>
                    <option value="finish"></option>
                    <option value="capacity"></option>
                    <option value="connector"></option>
                </datalist>
                <small class="admin-specs-error" data-field-error="variants"></small>
                <details class="admin-editor-accordion admin-editor-accordion-soft">
                    <summary><span>Paste options in bulk</span><small>Optional import helper</small></summary>
                    <label class="admin-field admin-field-wide admin-legacy-specs"><span>Paste extra options, optional</span><textarea name="variants_text" rows="4" placeholder="storage: 256GB&#10;storage: 512GB&#10;color: Midnight Black&#10;size: Small, Medium, Large"></textarea><small>Useful when copying options from another product. Use Import pasted to convert lines into editable rows before saving.</small><small class="admin-inline-feedback" data-variant-import-feedback></small></label>
                </details>
            </div>
        </section>

        <section class="admin-product-tab-panel" data-product-tab-panel="media" data-editor-tab="media" hidden>
            <div class="admin-card glass-panel admin-editor-card admin-media-studio-card">
                <div class="admin-section-head admin-media-studio-head">
                    <div>
                        <p class="admin-eyebrow">Product media</p>
                        <h2>Image studio</h2>
                        <small class="admin-editor-subtitle">Keep the main image obvious, manage the gallery visually, and open advanced tools only when needed.</small>
                    </div>
                    <div class="admin-media-studio-summary" aria-label="Media summary">
                        <span class="<?= $mainImage !== '' ? 'is-ok' : 'is-warn' ?>"><span class="material-symbols-outlined"><?= $mainImage !== '' ? 'check_circle' : 'error' ?></span><?= $mainImage !== '' ? 'Main image ready' : 'Main image missing' ?></span>
                        <span><span class="material-symbols-outlined">photo_library</span><?= e((string) count($gallery)) ?> gallery images</span>
                        <span><span class="material-symbols-outlined">perm_media</span><?= e((string) count($recentMedia)) ?> library items</span>
                    </div>
                </div>

                <input class="admin-visually-hidden" type="radio" name="primary_image_path" value="__typed" checked aria-hidden="true">

                <div class="admin-media-studio-layout">
                    <section class="admin-media-primary-panel" aria-label="Main product image">
                        <div class="admin-media-panel-head">
                            <div>
                                <span class="admin-picker-title">Main image</span>
                                <small>This is the first image customers see on cards and product pages.</small>
                            </div>
                            <span class="admin-media-state <?= $mainImage !== '' ? 'is-ok' : 'is-missing' ?>"><?= $mainImage !== '' ? 'Selected' : 'Missing' ?></span>
                        </div>

                        <div class="admin-media-primary-preview <?= $mainImage === '' ? 'is-empty' : '' ?>">
                            <?php if ($mainImage !== ''): ?>
                                <span class="admin-media-main-badge"><span class="material-symbols-outlined">star</span>Main image</span>
                                <img src="<?= e(admin_media_public_url($mainImage)) ?>" alt="<?= e($product['name'] ?? 'Product image') ?>">
                                <small class="admin-media-main-filename" title="<?= e($mainImage) ?>"><?= e(basename($mainImage)) ?></small>
                            <?php else: ?>
                                <span class="material-symbols-outlined">image</span>
                                <strong>No main image yet</strong>
                                <small>Upload one or choose from Media Library below.</small>
                            <?php endif; ?>
                        </div>

                        <div class="admin-media-action-stack">
                            <label class="admin-file-drop admin-file-drop-compact">
                                <span class="material-symbols-outlined">upload</span>
                                <strong>Upload main image</strong>
                                <small>Replaces the current main image after saving</small>
                                <input type="file" name="main_image_upload" accept="image/*" data-main-image-upload>
                            </label>
                            <div class="admin-upload-preview admin-upload-preview-compact" data-main-upload-preview hidden></div>
                            <details class="admin-editor-accordion admin-editor-accordion-soft admin-media-path-details">
                                <summary><span>Image path</span><small>Advanced</small></summary>
                                <label class="admin-field">
                                    <span>Main image path</span>
                                    <input name="image" value="<?= e($product['image'] ?? '') ?>" placeholder="assets/uploads/admin_media/..." data-preview-image-input data-field-label="Main image">
                                    <small>Use only when pasting an existing file path manually.</small>
                                    <small data-field-error="image"></small>
                                </label>
                            </details>
                        </div>
                    </section>

                    <section class="admin-media-gallery-panel" aria-label="Product image gallery">
                        <div class="admin-media-panel-head">
                            <div>
                                <span class="admin-picker-title">Gallery</span>
                                <small>Images appear below the main image. The first strong product angles should stay near the top.</small>
                            </div>
                            <label class="admin-file-drop admin-file-drop-inline">
                                <span class="material-symbols-outlined">add_photo_alternate</span>
                                <strong>Add images</strong>
                                <input type="file" name="gallery_image_uploads[]" accept="image/*" multiple data-gallery-upload>
                            </label>
                        </div>

                        <div class="admin-upload-preview-grid admin-upload-preview-grid-compact" data-gallery-upload-preview hidden></div>

                        <?php if ($gallery): ?>
                            <div class="admin-current-gallery admin-current-gallery-studio" data-gallery-list>
                                <?php foreach ($gallery as $index => $path): ?>
                                    <?php $isPrimaryGalleryImage = (!empty($product['image']) && $path === (string) $product['image']); ?>
                                    <article class="admin-gallery-card admin-gallery-card-studio <?= $isPrimaryGalleryImage ? 'is-primary' : '' ?>" data-gallery-card data-image-path="<?= e($path) ?>">
                                        <div class="admin-gallery-thumb-wrap">
                                            <img src="<?= e(admin_media_public_url($path)) ?>" alt="Product gallery image <?= (int) $index + 1 ?>">
                                            <span class="admin-gallery-rank">#<?= (int) $index + 1 ?></span>
                                        </div>
                                        <div class="admin-gallery-card-body">
                                            <input type="hidden" name="gallery_existing_paths[]" value="<?= e($path) ?>">
                                            <div class="admin-gallery-card-topline">
                                                <label class="admin-media-chip"><input type="radio" name="primary_image_path" value="<?= e($path) ?>" <?= $isPrimaryGalleryImage ? 'checked' : '' ?>> Main</label>
                                                <?php if ($isPrimaryGalleryImage): ?><span class="admin-media-current-pill">Current</span><?php endif; ?>
                                            </div>
                                            <strong class="admin-gallery-filename" title="<?= e($path) ?>"><?= e((string) basename($path)) ?></strong>
                                            <small title="<?= e($path) ?>"><?= e($path) ?></small>
                                            <div class="admin-gallery-actions">
                                                <button type="button" class="admin-icon-btn" data-gallery-move="up" aria-label="Move image up"><span class="material-symbols-outlined">keyboard_arrow_up</span></button>
                                                <button type="button" class="admin-icon-btn" data-gallery-move="down" aria-label="Move image down"><span class="material-symbols-outlined">keyboard_arrow_down</span></button>
                                                <button type="button" class="admin-icon-btn danger" data-gallery-remove aria-label="Remove image"><span class="material-symbols-outlined">close</span></button>
                                            </div>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                            <p class="admin-gallery-empty-note" data-gallery-empty hidden>No gallery images selected.</p>
                        <?php else: ?>
                            <p class="admin-gallery-empty-note admin-gallery-empty-studio" data-gallery-empty><span class="material-symbols-outlined">photo_library</span>No gallery images yet. Upload images or pick them from Media Library.</p>
                        <?php endif; ?>
                    </section>
                </div>

                <?php if ($recentMedia): ?>
                    <details class="admin-editor-accordion admin-media-library-accordion admin-media-library-studio admin-media-picker-popover">
                        <summary><span><span class="material-symbols-outlined">perm_media</span>Pick from Media Library</span><small><span data-media-selected-count>0</span> selected</small></summary>
                        <div class="admin-media-picker-shell">
                            <div class="admin-media-picker-titlebar">
                                <div>
                                    <strong>Media Library picker</strong>
                                    <small>Select Main for the primary image or Gallery for extra product angles.</small>
                                </div>
                                <span class="admin-media-picker-close-hint">Click the header again to close</span>
                            </div>
                            <div class="admin-media-library-tools">
                                <label class="admin-media-search"><span class="material-symbols-outlined">search</span><input type="search" placeholder="Search filename, alt text, or path" data-media-filter></label>
                                <label class="admin-media-toggle"><input type="checkbox" data-media-selected-only> Show selected only</label>
                            </div>
                            <div class="admin-media-library-grid admin-media-library-wide admin-media-library-picker" data-media-library-grid>
                            <?php foreach ($recentMedia as $asset): ?>
                                <?php $assetPath = (string) $asset['public_path']; ?>
                                <article class="admin-media-tile admin-media-tile-studio" data-media-path="<?= e($assetPath) ?>" data-media-search-text="<?= e(mb_strtolower((string) ($asset['original_name'] . ' ' . $assetPath . ' ' . ($asset['alt_text'] ?? '')))) ?>">
                                    <img src="<?= e(admin_media_public_url($assetPath)) ?>" alt="<?= e($asset['alt_text'] ?: $asset['original_name']) ?>">
                                    <div class="admin-media-tile-actions">
                                        <label class="admin-media-chip"><input type="checkbox" name="gallery_media_ids[]" value="<?= (int) $asset['id'] ?>" <?= in_array($assetPath, $gallery, true) ? 'checked' : '' ?>> Gallery</label>
                                        <label class="admin-media-chip"><input type="radio" name="primary_media_id" value="<?= (int) $asset['id'] ?>"> Main</label>
                                    </div>
                                    <small title="<?= e($assetPath) ?>"><?= e($asset['original_name']) ?></small>
                                </article>
                            <?php endforeach; ?>
                            </div>
                        </div>
                    </details>
                <?php else: ?>
                    <p class="admin-hint admin-media-empty-library">No media assets yet. Upload images from this editor or open Media Library later.</p>
                <?php endif; ?>

                <details class="admin-editor-accordion admin-editor-accordion-soft admin-media-manual-details">
                    <summary><span>Manual gallery paths</span><small>Rare use</small></summary>
                    <label class="admin-field admin-field-wide">
                        <span>Add gallery paths manually</span>
                        <textarea name="gallery_paths" rows="3" placeholder="One image path per line"></textarea>
                        <small>Use this only when the image file already exists and is not available in Media Library.</small>
                    </label>
                </details>
            </div>
        </section>

        <section class="admin-product-tab-panel" data-product-tab-panel="descriptions" data-editor-tab="descriptions" hidden>
            <div class="admin-card glass-panel admin-editor-card">
                <div class="admin-section-head"><div><p class="admin-eyebrow">Product story</p><h2>Short and full descriptions</h2></div></div>
                <div class="admin-form-grid">
                    <label class="admin-field admin-field-wide"><span>Short description</span><textarea name="short_description" rows="3" maxlength="1000" placeholder="Compact product summary used in cards and product page intro." data-preview-short data-field-label="Short description"><?= e($product['short_description'] ?? '') ?></textarea><small><span data-short-count>0</span>/1000 characters.</small><small data-field-error="short_description"></small></label>
                    <label class="admin-field admin-field-wide"><span>Full description</span><textarea name="description" rows="10" placeholder="Full product description shown on the product page." data-full-description><?= e($product['description'] ?? '') ?></textarea><small><span data-full-count>0</span> characters. Keep it focused on what appears on the product page.</small></label>
                    <div class="admin-field admin-field-wide admin-benefits-block">
                        <div class="admin-section-head admin-section-head-compact">
                            <div>
                                <span>Key benefits</span>
                                <small><span data-benefit-filled-count>0</span> benefits will appear as compact selling points on the product page.</small>
                            </div>
                            <div class="admin-page-actions admin-editor-mini-actions">
                                    <button type="button" class="admin-primary-btn" data-benefit-add-row>Add benefit</button>
                                    <details class="admin-more-menu">
                                        <summary><span>More</span><span class="material-symbols-outlined">expand_more</span></summary>
                                        <div class="admin-more-panel">
                                            <button type="button" data-benefit-import>Import pasted</button>
                                            <button type="button" data-benefit-clean>Clean rows</button>
                                            <button type="button" data-benefit-preset="daily">Add daily set</button>
                                        </div>
                                    </details>
                                </div>
                        </div>
                        <div class="admin-benefits-editor" data-benefit-rows>
                            <?php foreach ($benefitList as $benefit): ?>
                                <div class="admin-benefit-row" data-benefit-row>
                                    <input name="benefits[]" value="<?= e($benefit) ?>" placeholder="Example: Fast charging and all-day battery life" aria-label="Product benefit">
                                    <div class="admin-benefit-actions"><details class="admin-more-menu admin-row-more"><summary aria-label="Benefit row actions"><span class="material-symbols-outlined">more_horiz</span></summary><div class="admin-more-panel"><button type="button" data-benefit-move="up">Move up</button><button type="button" data-benefit-move="down">Move down</button><button type="button" class="danger" data-benefit-remove>Remove</button></div></details></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <details class="admin-editor-accordion admin-editor-accordion-soft">
                            <summary><span>Paste benefits in bulk</span><small>Optional import helper</small></summary>
                            <label class="admin-field admin-field-wide admin-legacy-specs"><span>Paste benefits, optional</span><textarea name="benefits_text" rows="3" placeholder="One benefit per line"></textarea><small>Useful for quick copy-paste from supplier notes. Use Import pasted to turn lines into editable rows before saving.</small><small class="admin-inline-feedback" data-benefit-import-feedback></small></label>
                        </details>
                    </div>
                </div>
            </div>
        </section>

        <section class="admin-product-tab-panel" data-product-tab-panel="specs" data-editor-tab="specs" hidden>
            <div class="admin-card glass-panel admin-editor-card">
                <div class="admin-section-head"><div><p class="admin-eyebrow">Product-page specs</p><h2>Editable specifications</h2><small class="admin-editor-subtitle"><span data-spec-filled-count>0</span> filled specs will appear on the product page in this order.</small></div><div class="admin-page-actions admin-editor-mini-actions"><button type="button" class="admin-primary-btn" data-spec-add-row>Add spec</button><details class="admin-more-menu"><summary><span>More</span><span class="material-symbols-outlined">expand_more</span></summary><div class="admin-more-panel"><button type="button" data-spec-import>Import pasted</button><button type="button" data-spec-clean>Clean rows</button><button type="button" data-spec-preset="shipping">Add shipping set</button><button type="button" data-spec-apply-template>Apply type template</button></div></details></div></div>
                <div class="admin-editor-note"><span class="material-symbols-outlined">tune</span><p>These rows are saved as product specifications and shown on the storefront product page. No raw JSON editing is needed.</p></div>
                <div class="admin-specs-editor" data-spec-rows>
                    <?php foreach ($specList as $spec): ?>
                        <div class="admin-spec-row" data-spec-row>
                            <input name="spec_names[]" value="<?= e($spec['name'] ?? '') ?>" placeholder="Specification name" aria-label="Specification name">
                            <input name="spec_values[]" value="<?= e($spec['value'] ?? '') ?>" placeholder="Specification value" aria-label="Specification value">
                            <div class="admin-spec-actions"><details class="admin-more-menu admin-row-more"><summary aria-label="Spec row actions"><span class="material-symbols-outlined">more_horiz</span></summary><div class="admin-more-panel"><button type="button" data-spec-move="up">Move up</button><button type="button" data-spec-move="down">Move down</button><button type="button" class="danger" data-spec-remove>Remove</button></div></details></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <small class="admin-specs-error" data-field-error="specs"></small>
                <details class="admin-editor-accordion admin-editor-accordion-soft">
                    <summary><span>Paste specs in bulk</span><small>Optional import helper</small></summary>
                    <label class="admin-field admin-field-wide admin-legacy-specs"><span>Paste extra specs, optional</span><textarea name="specs_text" rows="4" placeholder="One extra specification per line: Name: Value"></textarea><small>Useful for bulk pasting. Use Import pasted to convert lines into editable rows before saving.</small><small class="admin-inline-feedback" data-spec-import-feedback></small></label>
                </details>
            </div>
        </section>
    </div>

    <aside class="admin-product-edit-side">
        <section class="admin-card glass-panel admin-editor-card admin-save-card admin-product-save-bar">
            <div><strong data-save-state><?= $isEdit ? 'Ready to save' : 'Ready to create' ?></strong><small data-save-help>Save changes without leaving this editor.</small></div>
            <button class="admin-primary-btn" type="submit" name="save_next" value="stay" data-product-save-button><?= $isEdit ? 'Save changes' : 'Create product' ?></button>
            <details class="admin-more-menu drop-up admin-save-more">
                <summary><span>More</span><span class="material-symbols-outlined">expand_more</span></summary>
                <div class="admin-more-panel">
                    <button type="submit" name="save_next" value="view">Save & view</button>
                    <button type="submit" name="save_next" value="products">Save & back</button>
                    <a href="<?= e(admin_page_url('products')) ?>">Cancel</a>
                </div>
            </details>
        </section>

        <section class="admin-card glass-panel admin-editor-card admin-sticky-editor-side admin-product-live-card">
            <div class="admin-section-head"><div><p class="admin-eyebrow">Preview</p><h2>Product snapshot</h2></div></div>
            <div class="admin-product-preview-card" data-product-preview>
                <div class="admin-product-preview-image"><img src="<?= e($mainImage !== '' ? admin_media_public_url($mainImage) : '') ?>" alt="Product preview" data-preview-img <?= $mainImage === '' ? 'hidden' : '' ?>><span class="material-symbols-outlined" data-preview-img-empty <?= $mainImage !== '' ? 'hidden' : '' ?>>image</span></div>
                <div class="admin-product-preview-body">
                    <span class="admin-product-preview-badge" data-preview-badge-out <?= empty($product['badge']) ? 'hidden' : '' ?>><?= e($product['badge'] ?? '') ?></span>
                    <strong data-preview-title><?= e($product['name'] ?? 'Untitled product') ?></strong>
                    <small data-preview-meta><?= e(($product['brand'] ?? '') ?: admin_product_type_label($productType)) ?></small>
                    <p data-preview-short-out><?= e($product['short_description'] ?? 'Short product description will appear here.') ?></p>
                    <div class="admin-product-preview-price"><span data-preview-price-out><?= e(admin_money($product['price'] ?? 0, $currency)) ?></span><del data-preview-compare-out <?= $comparePrice > $price && $comparePrice > 0 ? '' : 'hidden' ?>><?= e(admin_money($comparePrice, $currency)) ?></del></div>
                    <small class="admin-product-preview-discount" data-preview-discount <?= $discountPercent > 0 ? '' : 'hidden' ?>><?= $discountPercent > 0 ? e($discountDisplay . '% off') : '' ?></small>
                    <div class="admin-product-preview-gallery" data-preview-gallery></div>
                    <div class="admin-product-preview-options" data-preview-options></div>
                    <div class="admin-product-preview-benefits" data-preview-benefits></div>
                    <div class="admin-product-preview-specs" data-preview-specs></div>
                    <button type="button" class="admin-primary-btn" data-preview-button><?= $statusValue === 'out_of_stock' || (int)($product['stock'] ?? 0) <= 0 ? 'Sold out' : 'Add to cart' ?></button>
                </div>
            </div>
        </section>

        <details class="admin-card glass-panel admin-editor-card admin-ready-card admin-editor-side-details" open>
            <summary><span>Readiness</span><strong data-ready-title><?= e($readyState) ?></strong></summary>
            <div class="admin-ready-progress admin-ready-score-<?= (int) $readyCount ?>" data-ready-progress-wrap><span data-ready-progress></span></div>
            <ul class="admin-ready-list" data-ready-list>
                <?php foreach ($readyItems as $key => $item): ?>
                    <li class="<?= $item[1] ? 'is-ok' : 'is-missing' ?>" data-ready-item="<?= e($key) ?>"><span class="material-symbols-outlined"><?= $item[1] ? 'check_circle' : 'radio_button_unchecked' ?></span><?= e($item[0]) ?></li>
                <?php endforeach; ?>
            </ul>
        </details>

        <details class="admin-card glass-panel admin-editor-card admin-final-review-card admin-editor-side-details">
            <summary><span>Final review</span><strong>Before publishing</strong></summary>
            <ul class="admin-final-review-list" data-final-review-list>
                <li><span>Storefront URL</span><strong data-final-review-url><?= $viewUrl !== '' ? e($viewUrl) : 'Generated after save' ?></strong></li>
                <li><span>Visible options</span><strong data-final-review-options><?= e((string) array_sum(array_map('count', $variantGroups))) ?></strong></li>
                <li><span>Gallery images</span><strong data-final-review-gallery><?= e((string) count($gallery)) ?></strong></li>
                <li><span>Benefits</span><strong data-final-review-benefits><?= e((string) count(array_filter($benefitList, static fn($benefit) => trim((string) $benefit) !== ''))) ?></strong></li>
            </ul>
        </details>
    </aside>
</form>
<?php admin_footer(); ?>
