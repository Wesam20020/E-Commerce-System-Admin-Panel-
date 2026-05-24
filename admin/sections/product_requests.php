<?php
require_once __DIR__ . '/../../includes/phone_matcher.php';
phone_finder_ensure_tables($pdo);

function admin_phone_request_find(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT r.*, p.name AS existing_product_name, p.slug AS existing_product_slug, cp.name AS converted_product_name, cp.slug AS converted_product_slug
                           FROM product_sourcing_requests r
                           LEFT JOIN products p ON p.id = r.existing_product_id
                           LEFT JOIN products cp ON cp.id = r.converted_product_id
                           WHERE r.id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function admin_phone_request_category_id(PDO $pdo, string $brand): ?int
{
    $slug = mb_strtolower(trim($brand)) === 'apple' ? 'iphone' : 'android';
    $stmt = $pdo->prepare('SELECT id FROM categories WHERE slug = :slug LIMIT 1');
    $stmt->execute(['slug' => $slug]);
    $id = $stmt->fetchColumn();
    if ($id) {
        return (int) $id;
    }
    $stmt = $pdo->query('SELECT id FROM categories ORDER BY id ASC LIMIT 1');
    $id = $stmt ? $stmt->fetchColumn() : null;
    return $id ? (int) $id : null;
}

function admin_phone_request_unique_slug(PDO $pdo, string $name): string
{
    $base = function_exists('admin_slugify') ? admin_slugify($name) : strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));
    $base = trim($base ?: 'requested-phone', '-');
    $slug = mb_substr($base, 0, 160);
    $i = 2;
    while (true) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM products WHERE slug = :slug');
        $stmt->execute(['slug' => $slug]);
        if ((int) $stmt->fetchColumn() === 0) {
            return $slug;
        }
        $slug = mb_substr($base, 0, 150) . '-' . $i;
        $i++;
    }
}

function admin_phone_request_specs_from_ai(array $ai, array $preferences): string
{
    $rows = [];
    foreach ((array) ($ai['key_specs'] ?? []) as $line) {
        $line = phone_finder_text($line, 160);
        if ($line === '') {
            continue;
        }
        $parts = preg_split('/\s*[:=-]\s*/u', $line, 2);
        if ($parts && count($parts) === 2) {
            $rows[] = ['name' => phone_finder_text($parts[0], 80), 'value' => phone_finder_text($parts[1], 160)];
        } else {
            $rows[] = ['name' => 'Spec', 'value' => $line];
        }
    }
    if (!empty($ai['estimated_price_range_try'])) {
        $rows[] = ['name' => 'Estimated market range', 'value' => phone_finder_text($ai['estimated_price_range_try'], 120)];
    }
    if (!empty($preferences['storage_min'])) {
        $rows[] = ['name' => 'Requested storage', 'value' => (int) $preferences['storage_min'] . 'GB minimum'];
    }
    if (!$rows) {
        $rows[] = ['name' => 'Source', 'value' => 'Created from phone finder request'];
    }
    return json_encode(array_slice($rows, 0, 12), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}


function admin_phone_request_image_url(array $ai): string
{
    $url = '';
    if (function_exists('phone_finder_candidate_display_image_url')) {
        $url = phone_finder_candidate_display_image_url($ai);
    }
    if ($url === '' && isset($ai['request_image']) && is_array($ai['request_image']) && !empty($ai['request_image']['url'])) {
        $url = (string) $ai['request_image']['url'];
    }
    if ($url === '') {
        $url = (string) ($ai['image_url'] ?? '');
    }

    $url = str_replace('\\', '/', trim($url));
    if (str_starts_with($url, 'uploads/product_requests/')) {
        $url = 'assets/uploads/product_requests/' . basename($url);
    }
    if ($url === '' || $url === 'uploads/product_requests/' || $url === 'assets/uploads/product_requests/') {
        $url = 'assets/images/phone-ai-placeholder.svg';
    }
    if (preg_match('#^(assets|uploads)/#', $url) && function_exists('admin_root_url')) {
        return admin_root_url($url);
    }
    return $url;
}

function admin_phone_request_convert_to_product(PDO $pdo, array $request): int
{
    if (!empty($request['converted_product_id'])) {
        return (int) $request['converted_product_id'];
    }
    $ai = phone_finder_decode_json_field($request['ai_result_json'] ?? '');
    $preferences = phone_finder_decode_json_field($request['preferences_json'] ?? '');
    $brand = phone_finder_text($request['requested_brand'] ?? ($ai['brand'] ?? ''), 120);
    $model = phone_finder_text($request['requested_model'] ?? ($ai['model'] ?? ''), 190);
    $variant = phone_finder_text($request['requested_variant'] ?? ($ai['variant'] ?? ''), 190);
    $name = trim(implode(' ', array_filter([$brand, $model, $variant])));
    if ($name === '') {
        throw new RuntimeException('The request does not contain a valid phone name.');
    }

    $categoryId = admin_phone_request_category_id($pdo, $brand);
    $slug = admin_phone_request_unique_slug($pdo, $name);
    $sku = 'REQ-' . str_pad((string) ((int) $request['id']), 6, '0', STR_PAD_LEFT);
    $why = phone_finder_text($ai['why_it_matches'] ?? '', 700);
    $note = phone_finder_text($ai['sourcing_note'] ?? '', 300);
    $short = $why !== '' ? $why : 'Draft phone created from a customer sourcing request.';
    $description = trim($short . ($note !== '' ? "\n\nSourcing note: " . $note : '') . "\n\nCreated from Phone Finder request #" . (int) $request['id'] . '.');
    $specsJson = admin_phone_request_specs_from_ai($ai, $preferences);
    $requestImage = admin_phone_request_image_url($ai);

    $stmt = $pdo->prepare('INSERT INTO products (category_id, name, slug, sku, brand, badge, product_type, short_description, description, specs_json, benefits_json, price, compare_price, stock, rating, image, gallery_json, product_status, is_active, is_featured)
                           VALUES (:category_id, :name, :slug, :sku, :brand, :badge, :product_type, :short_description, :description, :specs_json, :benefits_json, :price, :compare_price, :stock, :rating, :image, :gallery_json, :product_status, :is_active, :is_featured)');
    $stmt->execute([
        'category_id' => $categoryId,
        'name' => mb_substr($name, 0, 190),
        'slug' => $slug,
        'sku' => mb_substr($sku, 0, 120),
        'brand' => $brand ?: null,
        'badge' => 'Requested',
        'product_type' => 'smartphone',
        'short_description' => $short,
        'description' => $description,
        'specs_json' => $specsJson,
        'benefits_json' => json_encode(['Customer demand already registered', 'Review supplier availability before publishing', 'Add official price, stock, warranty, and images'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'price' => 0,
        'compare_price' => null,
        'stock' => 0,
        'rating' => 0,
        'image' => $requestImage,
        'gallery_json' => $requestImage !== '' ? json_encode([$requestImage], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        'product_status' => 'draft',
        'is_active' => 0,
        'is_featured' => 0,
    ]);
    $productId = (int) $pdo->lastInsertId();
    $pdo->prepare("UPDATE product_sourcing_requests SET status = 'converted_to_product', converted_product_id = :product_id WHERE id = :id LIMIT 1")->execute(['product_id' => $productId, 'id' => (int) $request['id']]);
    admin_log_activity($pdo, 'phone_request_converted', 'product_sourcing_request', (int) $request['id'], 'Converted sourcing request #' . (int) $request['id'] . ' to product draft #' . $productId);
    return $productId;
}

if (is_post_request()) {
    $input = request_input();
    verify_csrf_or_fail($input['_csrf'] ?? null);
    $action = phone_finder_text($input['action'] ?? '', 60);
    $id = (int) ($input['request_id'] ?? 0);
    try {
        $request = admin_phone_request_find($pdo, $id);
        if (!$request) {
            throw new RuntimeException('Sourcing request not found.');
        }
        if ($action === 'update_status') {
            $status = phone_finder_text($input['status'] ?? 'new', 40);
            $statuses = phone_finder_allowed_statuses();
            if (!array_key_exists($status, $statuses)) {
                throw new RuntimeException('Invalid request status.');
            }
            if ($status === 'rejected') {
                if (!empty($request['converted_product_id'])) {
                    throw new RuntimeException('Converted requests cannot be rejected here. Edit or delete the draft product instead.');
                }
                $ai = phone_finder_decode_json_field($request['ai_result_json'] ?? '');
                phone_finder_delete_request_image($ai);
                $pdo->prepare('DELETE FROM product_sourcing_requests WHERE id = :id LIMIT 1')->execute(['id' => $id]);
                admin_log_activity($pdo, 'phone_request_rejected_deleted', 'product_sourcing_request', $id, 'Rejected and deleted sourcing request #' . $id);
                flash_set('success', 'Request rejected and removed from the dashboard.');
                admin_redirect('product_requests', $_GET);
            }
            $notes = phone_finder_text($input['admin_notes'] ?? ($request['admin_notes'] ?? ''), 2000);
            $pdo->prepare('UPDATE product_sourcing_requests SET status = :status, admin_notes = :admin_notes WHERE id = :id LIMIT 1')->execute([
                'status' => $status,
                'admin_notes' => $notes ?: null,
                'id' => $id,
            ]);
            admin_log_activity($pdo, 'phone_request_status_updated', 'product_sourcing_request', $id, 'Updated sourcing request #' . $id . ' to ' . $status);
            flash_set('success', 'Request updated.');
        } elseif ($action === 'convert_to_product') {
            $productId = admin_phone_request_convert_to_product($pdo, $request);
            flash_set('success', 'Product draft created from request #' . $id . '.');
            admin_redirect('product_edit', ['id' => $productId]);
        } else {
            throw new RuntimeException('Unknown request action.');
        }
    } catch (Throwable $e) {
        admin_flash_from_exception($e, 'The sourcing request could not be updated.');
    }
    admin_redirect('product_requests', $_GET);
}

$statusFilter = phone_finder_text($_GET['status'] ?? '', 40);
$q = trim((string) ($_GET['q'] ?? ''));
$allowedStatuses = phone_finder_allowed_statuses();
$where = ['1=1'];
$params = [];
if ($statusFilter !== '' && array_key_exists($statusFilter, $allowedStatuses)) {
    $where[] = 'r.status = :status';
    $params['status'] = $statusFilter;
}
if ($q !== '') {
    $where[] = '(r.requested_model LIKE :q OR r.requested_brand LIKE :q OR r.customer_name LIKE :q OR r.customer_email LIKE :q OR r.customer_phone LIKE :q)';
    $params['q'] = '%' . $q . '%';
}

$requests = admin_rows($pdo, 'SELECT r.*, p.name AS existing_product_name, p.slug AS existing_product_slug, cp.name AS converted_product_name, cp.slug AS converted_product_slug
                               FROM product_sourcing_requests r
                               LEFT JOIN products p ON p.id = r.existing_product_id
                               LEFT JOIN products cp ON cp.id = r.converted_product_id
                               WHERE ' . implode(' AND ', $where) . '
                               ORDER BY FIELD(r.status, \'new\', \'reviewing\', \'contacted_supplier\', \'available\', \'converted_to_product\', \'rejected\'), r.created_at DESC
                               LIMIT 150', $params);
$metrics = [
    'new' => (int) admin_scalar($pdo, "SELECT COUNT(*) FROM product_sourcing_requests WHERE status = 'new'"),
    'reviewing' => (int) admin_scalar($pdo, "SELECT COUNT(*) FROM product_sourcing_requests WHERE status IN ('reviewing','contacted_supplier')"),
    'available' => (int) admin_scalar($pdo, "SELECT COUNT(*) FROM product_sourcing_requests WHERE status = 'available'"),
    'converted' => (int) admin_scalar($pdo, "SELECT COUNT(*) FROM product_sourcing_requests WHERE status = 'converted_to_product'"),
];

admin_header('Product Requests', 'Review phone sourcing requests and convert strong demand into product drafts.', 'product_requests');
?>
<section class="admin-section product-requests-admin">
    <div class="admin-metrics-grid">
        <?php admin_metric_card('New requests', (string) $metrics['new'], 'new_releases', 'Needs product review'); ?>
        <?php admin_metric_card('In review', (string) $metrics['reviewing'], 'manage_search', 'Reviewing or supplier contacted'); ?>
        <?php admin_metric_card('Marked available', (string) $metrics['available'], 'inventory', 'Ready for product creation'); ?>
        <?php admin_metric_card('Converted', (string) $metrics['converted'], 'task_alt', 'Draft product created'); ?>
    </div>

    <section class="admin-card glass-panel">
        <div class="admin-card-head">
            <div>
                <h2>Phone sourcing inbox</h2>
                <p>Every selected phone request appears here so staff can verify supplier availability, price, stock, and warranty before publishing.</p>
            </div>
        </div>

        <form class="admin-filter-bar product-requests-filter" method="get">
            <input type="hidden" name="section" value="product_requests">
            <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search model, customer, phone...">
            <select name="status">
                <option value="">All statuses</option>
                <?php foreach ($allowedStatuses as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $statusFilter === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="admin-primary-btn" type="submit">Filter</button>
            <a class="admin-table-btn" href="<?= e(admin_page_url('product_requests')) ?>">Reset</a>
        </form>

        <?php if (!$requests): ?>
            <?php admin_empty_state('No sourcing requests yet', 'When customers request a suggested phone, it will appear in this inbox.'); ?>
        <?php else: ?>
            <div class="admin-table-wrap product-requests-table-wrap">
                <table class="admin-table product-requests-table">
                    <thead>
                        <tr>
                            <th>Request</th>
                            <th>Customer</th>
                            <th>Preferences</th>
                            <th>Sourcing note</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $request): ?>
                            <?php
                            $preferences = phone_finder_decode_json_field($request['preferences_json'] ?? '');
                            $ai = phone_finder_decode_json_field($request['ai_result_json'] ?? '');
                            $status = (string) ($request['status'] ?? 'new');
                            $phoneName = trim(implode(' ', array_filter([(string) ($request['requested_brand'] ?? ''), (string) ($request['requested_model'] ?? ''), (string) ($request['requested_variant'] ?? '')])));
                            $requestImage = admin_phone_request_image_url($ai);
                            $contactBits = array_filter([(string) ($request['customer_name'] ?? ''), (string) ($request['customer_email'] ?? ''), (string) ($request['customer_phone'] ?? '')]);
                            $budgetText = 'Any budget';
                            if (!empty($preferences['budget_max'])) {
                                $budgetText = (!empty($preferences['budget_min']) ? format_price((float) $preferences['budget_min'], $siteCurrency) . ' - ' : 'Up to ') . format_price((float) $preferences['budget_max'], $siteCurrency);
                            }
                            ?>
                            <tr>
                                <td>
                                    <div class="product-request-phone-cell">
                                        <div class="product-request-image">
                                            <?php if ($requestImage !== ''): ?>
                                                <img src="<?= e($requestImage) ?>" alt="<?= e($phoneName ?: 'Requested phone') ?>" loading="lazy" referrerpolicy="no-referrer">
                                            <?php else: ?>
                                                <span class="material-symbols-outlined" aria-hidden="true">smartphone</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="product-request-phone-meta">
                                            <strong><?= e($phoneName ?: 'Requested phone') ?></strong>
                                            <small>#<?= (int) $request['id'] ?> · <?= e(date('M j, Y H:i', strtotime((string) $request['created_at']))) ?></small>
                                            <?php if (!empty($ai['image_search_query'])): ?><small>Image query: <?= e((string) $ai['image_search_query']) ?></small><?php endif; ?>
                                            <?php if (!empty($request['converted_product_id'])): ?>
                                                <small>Draft: <a href="<?= e(admin_page_url('product_edit', ['id' => (int) $request['converted_product_id']])) ?>"><?= e($request['converted_product_name'] ?: ('Product #' . (int) $request['converted_product_id'])) ?></a></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($contactBits): ?>
                                        <strong><?= e((string) ($request['customer_name'] ?: 'Customer')) ?></strong>
                                        <?php if (!empty($request['customer_email'])): ?><small><?= e((string) $request['customer_email']) ?></small><?php endif; ?>
                                        <?php if (!empty($request['customer_phone'])): ?><small><?= e((string) $request['customer_phone']) ?></small><?php endif; ?>
                                    <?php else: ?>
                                        <span class="admin-muted">Guest / no contact</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="product-request-chips">
                                        <span><?= e($budgetText) ?></span>
                                        <span><?= e(strtoupper((string) ($preferences['os'] ?? 'any'))) ?></span>
                                        <span><?= e((string) ($preferences['use_case'] ?? 'daily')) ?></span>
                                        <span><?= (int) ($preferences['storage_min'] ?? 128) ?>GB+</span>
                                        <?php if (!empty($preferences['need_5g'])): ?><span>5G</span><?php endif; ?>
                                    </div>
                                    <?php if (!empty($preferences['notes'])): ?><small><?= e((string) $preferences['notes']) ?></small><?php endif; ?>
                                </td>
                                <td>
                                    <p class="product-request-note"><?= e((string) ($ai['why_it_matches'] ?? $ai['sourcing_note'] ?? 'No sourcing note stored.')) ?></p>
                                    <?php if (!empty($ai['estimated_price_range_try'])): ?><small>Est. <?= e((string) $ai['estimated_price_range_try']) ?></small><?php endif; ?>
                                </td>
                                <td><span class="admin-status <?= e(phone_finder_status_class($status)) ?>"><?= e(phone_finder_status_label($status)) ?></span></td>
                                <td>
                                    <form method="post" class="product-request-action-form">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>">
                                        <input type="hidden" name="action" value="update_status">
                                        <select name="status" aria-label="Update request status">
                                            <?php foreach ($allowedStatuses as $value => $label): ?>
                                                <option value="<?= e($value) ?>" <?= $status === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <textarea name="admin_notes" rows="2" placeholder="Internal note"><?= e((string) ($request['admin_notes'] ?? '')) ?></textarea>
                                        <button class="admin-table-btn" type="submit">Save</button>
                                    </form>
                                    <form method="post" class="product-request-convert-form" data-admin-confirm="Create a draft product from this request?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>">
                                        <input type="hidden" name="action" value="convert_to_product">
                                        <button class="admin-primary-btn" type="submit" <?= !empty($request['converted_product_id']) ? 'disabled' : '' ?>>Convert to draft</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</section>
<?php admin_footer(); ?>
