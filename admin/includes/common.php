<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

function admin_root_url(string $path, array $query = []): string
{
    $path = '../' . ltrim($path, '/');
    if ($query !== []) {
        $path .= '?' . http_build_query($query);
    }
    return $path;
}


function admin_is_ajax_section(): bool
{
    return defined('ADMIN_AJAX_SECTION') && ADMIN_AJAX_SECTION === true;
}

function admin_allowed_sections(): array
{
    return ['index', 'notifications', 'system_health', 'maintenance_tools', 'email', 'homepage', 'deals', 'seo', 'products', 'product_edit', 'product_requests', 'inventory', 'media', 'categories', 'brands', 'orders', 'customers', 'coupons', 'shipping_payments', 'support', 'reports', 'exports', 'settings', 'admin_users'];
}

function admin_normalize_section(string $section): string
{
    $section = preg_replace('/[^a-z0-9_-]+/i', '', $section) ?: 'index';
    return in_array($section, admin_allowed_sections(), true) ? $section : 'index';
}

function admin_section_file(string $section): string
{
    $section = admin_normalize_section($section);
    if ($section === 'index') {
        return __DIR__ . '/../sections/dashboard.php';
    }
    return __DIR__ . '/../sections/' . $section . '.php';
}

function admin_page_url(string $page = 'index', array $query = []): string
{
    $section = admin_normalize_section($page);
    $params = $query;
    if ($section !== 'index') {
        $params = ['section' => $section] + $params;
    }
    $path = 'index.php';
    if ($params !== []) {
        $path .= '?' . http_build_query($params);
    }
    return $path;
}

function admin_ajax_url(string $section = 'index', array $query = []): string
{
    $section = admin_normalize_section($section);
    $params = ['section' => $section] + $query;
    return 'ajax.php?' . http_build_query($params);
}

function admin_redirect(string $page = 'index', array $query = [], ?string $anchor = null): void
{
    $url = admin_page_url($page, $query);
    if ($anchor) {
        $url .= '#' . rawurlencode($anchor);
    }
    if (admin_is_ajax_section()) {
        admin_json_response(['ok' => true, 'section' => admin_normalize_section($page), 'url' => $url]);
    }
    redirect_to($url);
}

function admin_asset_url(string $path): string
{
    $relative = ltrim($path, '/');
    $absolute = __DIR__ . '/../../' . $relative;
    $version = is_file($absolute) ? (string) filemtime($absolute) : (string) time();
    return admin_root_url($relative) . '?v=' . rawurlencode($version);
}

function admin_build_version(): string
{
    $paths = [
        __DIR__ . '/../index.php',
        __DIR__ . '/../ajax.php',
        __DIR__ . '/../api/products.php',
        __DIR__ . '/../sections/dashboard.php',
        __DIR__ . '/../sections/notifications.php',
        __DIR__ . '/../sections/system_health.php',
        __DIR__ . '/../system_health.php',
        __DIR__ . '/../sections/maintenance_tools.php',
        __DIR__ . '/../maintenance_tools.php',
        __DIR__ . '/../backup.php',
        __DIR__ . '/../sections/email.php',
        __DIR__ . '/../email.php',
        __DIR__ . '/../email_worker.php',
        __DIR__ . '/../sections/homepage.php',
        __DIR__ . '/../sections/deals.php',
        __DIR__ . '/../sections/seo.php',
        __DIR__ . '/../sections/products.php',
        __DIR__ . '/../sections/product_requests.php',
        __DIR__ . '/../product_requests.php',
        __DIR__ . '/../sections/inventory.php',
        __DIR__ . '/../sections/media.php',
        __DIR__ . '/../sections/categories.php',
        __DIR__ . '/../sections/brands.php',
        __DIR__ . '/../sections/orders.php',
        __DIR__ . '/../order_invoice.php',
        __DIR__ . '/../sections/customers.php',
        __DIR__ . '/../sections/coupons.php',
        __DIR__ . '/../sections/shipping_payments.php',
        __DIR__ . '/../sections/support.php',
        __DIR__ . '/../sections/reports.php',
        __DIR__ . '/../sections/exports.php',
        __DIR__ . '/../export.php',
        __DIR__ . '/../exports.php',
        __DIR__ . '/../notifications.php',
        __DIR__ . '/../sections/settings.php',
        __DIR__ . '/../sections/admin_users.php',
        __FILE__,
        __DIR__ . '/layout.php',
        __DIR__ . '/../../assets/css/admin.css',
        __DIR__ . '/../../assets/js/admin.js',
        __DIR__ . '/../../assets/js/admin_invoice.js',
        __DIR__ . '/../../assets/css/top_nav.css',
        __DIR__ . '/../../assets/css/style.css',
        __DIR__ . '/../../assets/js/site.js',
        __DIR__ . '/../../deals.php',
        __DIR__ . '/../../includes/store_queries.php',
        __DIR__ . '/../../checkout.php',
        __DIR__ . '/../../support.php',
        __DIR__ . '/../../assets/css/support.css',
    ];
    $max = 0;
    foreach ($paths as $path) {
        if (is_file($path)) {
            $max = max($max, (int) filemtime($path));
        }
    }
    return (string) max($max, 1);
}

function admin_json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    }
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function admin_handle_asset_check(): void
{
    if (isset($_GET['admin_asset_check'])) {
        admin_json_response(['ok' => true, 'version' => admin_build_version()]);
    }
}

function admin_scalar(PDO $pdo, string $sql, array $params = [], $default = 0)
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $value = $stmt->fetchColumn();
        return $value === false || $value === null ? $default : $value;
    } catch (Throwable $e) {
        error_log('[Phonix admin scalar] ' . $e->getMessage());
        return $default;
    }
}

function admin_rows(PDO $pdo, string $sql, array $params = []): array
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        error_log('[Phonix admin rows] ' . $e->getMessage());
        return [];
    }
}

function admin_money($amount, string $currency): string
{
    return format_price((float) $amount, $currency);
}

function admin_status_class(?string $status): string
{
    $status = strtolower((string) $status);
    return match ($status) {
        'paid', 'delivered', 'active', 'completed', 'resolved' => 'good',
        'processing', 'shipped', 'pending', 'open', 'new' => 'info',
        'unpaid', 'cancelled', 'refunded', 'inactive', 'hidden', 'failed', 'archived' => 'danger',
        default => 'neutral',
    };
}

function admin_roles(): array
{
    return [
        'owner' => 'Owner',
        'manager' => 'Manager',
        'products' => 'Products',
        'orders' => 'Orders',
        'support' => 'Support',
    ];
}

function admin_role_label(?string $role): string
{
    $role = strtolower((string) $role);
    return admin_roles()[$role] ?? 'Manager';
}

function admin_role_from_post(string $key = 'role'): string
{
    $role = preg_replace('/[^a-z_]+/', '', strtolower((string) ($_POST[$key] ?? 'manager'))) ?: 'manager';
    return array_key_exists($role, admin_roles()) ? $role : 'manager';
}

function admin_status_options(): array
{
    return [
        'active' => 'Active',
        'suspended' => 'Suspended',
    ];
}

function admin_status_from_post(string $key = 'status'): string
{
    $status = preg_replace('/[^a-z_]+/', '', strtolower((string) ($_POST[$key] ?? 'active'))) ?: 'active';
    return array_key_exists($status, admin_status_options()) ? $status : 'active';
}

function admin_current_record(PDO $pdo, ?array $user = null): ?array
{
    global $currentUser;
    $user = $user ?: $currentUser;
    if (!$user) {
        return null;
    }
    $email = mb_strtolower(trim((string) ($user['email'] ?? '')));
    if ($email === '') {
        return null;
    }
    try {
        $stmt = $pdo->prepare('SELECT * FROM admins WHERE LOWER(email) = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $admin = $stmt->fetch();
        if ($admin) {
            return $admin;
        }
        if ($email === 'admin@phonix.local') {
            return [
                'id' => 0,
                'name' => $user['name'] ?? 'Phonix Admin',
                'email' => $email,
                'role' => 'owner',
                'status' => 'active',
            ];
        }
    } catch (Throwable $e) {
        error_log('[Phonix admin record] ' . $e->getMessage());
    }
    return null;
}

function admin_current_role(PDO $pdo, ?array $user = null): string
{
    $admin = admin_current_record($pdo, $user);
    return strtolower((string) ($admin['role'] ?? 'manager')) ?: 'manager';
}

function admin_is_owner(PDO $pdo, ?array $user = null): bool
{
    return admin_current_role($pdo, $user) === 'owner';
}

function admin_can_access(PDO $pdo, ?array $user): bool
{
    if (!$user) {
        return false;
    }
    $email = mb_strtolower(trim((string) ($user['email'] ?? '')));
    if ($email === 'admin@phonix.local') {
        return true;
    }
    try {
        $stmt = $pdo->prepare("SELECT id FROM admins WHERE LOWER(email) = :email AND status = 'active' LIMIT 1");
        $stmt->execute(['email' => $email]);
        return (bool) $stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('[Phonix admin access] ' . $e->getMessage());
        return false;
    }
}

function admin_section_permissions(): array
{
    return [
        'index' => ['owner', 'manager', 'products', 'orders', 'support'],
        'notifications' => ['owner', 'manager', 'products', 'orders', 'support'],
        'system_health' => ['owner', 'manager'],
        'maintenance_tools' => ['owner'],
        'email' => ['owner', 'manager', 'orders', 'support'],
        'homepage' => ['owner', 'manager', 'products'],
        'deals' => ['owner', 'manager', 'products'],
        'seo' => ['owner', 'manager', 'products'],
        'products' => ['owner', 'manager', 'products'],
        'product_edit' => ['owner', 'manager', 'products'],
        'product_requests' => ['owner', 'manager', 'products', 'support'],
        'inventory' => ['owner', 'manager', 'products'],
        'media' => ['owner', 'manager', 'products'],
        'categories' => ['owner', 'manager', 'products'],
        'brands' => ['owner', 'manager', 'products'],
        'orders' => ['owner', 'manager', 'orders'],
        'customers' => ['owner', 'manager', 'orders', 'support'],
        'coupons' => ['owner', 'manager', 'products', 'orders'],
        'shipping_payments' => ['owner', 'manager', 'orders'],
        'support' => ['owner', 'manager', 'support'],
        'reports' => ['owner', 'manager'],
        'exports' => ['owner', 'manager'],
        'settings' => ['owner', 'manager'],
        'admin_users' => ['owner'],
    ];
}

function admin_user_can_section(PDO $pdo, string $section, ?array $user = null): bool
{
    $section = admin_normalize_section($section);
    $role = admin_current_role($pdo, $user);
    $allowed = admin_section_permissions()[$section] ?? ['owner'];
    return in_array($role, $allowed, true);
}

function admin_require_access(PDO $pdo, ?array $user): array
{
    if (admin_can_access($pdo, $user)) {
        try {
            $email = mb_strtolower(trim((string) ($user['email'] ?? '')));
            if ($email !== '') {
                $pdo->prepare('UPDATE admins SET last_seen_at = NOW() WHERE LOWER(email) = :email LIMIT 1')->execute(['email' => $email]);
            }
        } catch (Throwable $e) {
            error_log('[Phonix admin heartbeat] ' . $e->getMessage());
        }
        return $user ?: [];
    }
    flash_set('error', 'Please sign in with an administrator account.');
    redirect_to(admin_root_url('auth.php', ['mode' => 'signin', 'next' => 'admin/index.php']));
}

function admin_require_section_access(PDO $pdo, string $section): void
{
    global $currentUser;
    if (admin_user_can_section($pdo, $section, $currentUser)) {
        return;
    }
    http_response_code(403);
    if (admin_is_ajax_section()) {
        ?>
        <section class="admin-card glass-panel"><div class="admin-empty-state"><span class="material-symbols-outlined">lock</span><strong>Access denied</strong><p>Your admin role is not allowed to open this section.</p></div></section>
        <?php
        exit;
    }
    flash_set('error', 'Your admin role is not allowed to open this section.');
    redirect_to(admin_page_url('index'));
}

function admin_slugify(string $value): string
{
    $value = trim(mb_strtolower($value));
    $value = preg_replace('/[^a-z0-9]+/i', '-', $value) ?? '';
    $value = trim($value, '-');
    if ($value === '') {
        $value = 'item-' . bin2hex(random_bytes(3));
    }
    return substr($value, 0, 180);
}

function admin_unique_slug(PDO $pdo, string $table, string $base, ?int $ignoreId = null): string
{
    if (!in_array($table, ['products', 'categories'], true)) {
        throw new InvalidArgumentException('Invalid slug table.');
    }
    $slug = admin_slugify($base);
    $candidate = $slug;
    $i = 2;
    while (true) {
        $sql = "SELECT id FROM {$table} WHERE slug = :slug";
        $params = ['slug' => $candidate];
        if ($ignoreId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $ignoreId;
        }
        $sql .= ' LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        if (!$stmt->fetchColumn()) {
            return $candidate;
        }
        $candidate = substr($slug, 0, 170) . '-' . $i;
        $i++;
    }
}

function admin_bool_from_post(string $key): int
{
    return isset($_POST[$key]) ? 1 : 0;
}

function admin_clean_text(string $key, int $max = 255): string
{
    return mb_substr(trim((string) ($_POST[$key] ?? '')), 0, $max);
}

function admin_decimal_input(string $key): float
{
    $raw = str_replace(',', '.', trim((string) ($_POST[$key] ?? '0')));
    return max(0, round((float) $raw, 2));
}

function admin_int_input(string $key): int
{
    return max(0, (int) ($_POST[$key] ?? 0));
}

function admin_int_array_input(string $key): array
{
    $values = $_POST[$key] ?? [];
    if (!is_array($values)) {
        $values = [$values];
    }
    $ids = [];
    foreach ($values as $value) {
        $id = (int) $value;
        if ($id > 0) {
            $ids[$id] = $id;
        }
    }
    return array_values($ids);
}

function admin_sql_placeholders(array $values, string $prefix = 'id'): array
{
    $placeholders = [];
    $params = [];
    $index = 0;
    foreach ($values as $value) {
        $key = ':' . $prefix . $index++;
        $placeholders[] = $key;
        $params[substr($key, 1)] = $value;
    }
    return [$placeholders, $params];
}

function admin_find_product(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM products WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function admin_ensure_console_tables(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS admins (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, name VARCHAR(150) NOT NULL, email VARCHAR(190) NOT NULL, password_hash VARCHAR(255) NOT NULL, role VARCHAR(40) NOT NULL DEFAULT 'manager', status VARCHAR(30) NOT NULL DEFAULT 'active', permissions_json LONGTEXT NULL, last_seen_at DATETIME NULL, created_by VARCHAR(190) NULL, created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (id), UNIQUE KEY uq_admins_email (email), KEY idx_admins_role_status (role, status), KEY idx_admins_status (status)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $adminColumns = [
        'role' => "ALTER TABLE admins ADD COLUMN role VARCHAR(40) NOT NULL DEFAULT 'manager' AFTER password_hash",
        'status' => "ALTER TABLE admins ADD COLUMN status VARCHAR(30) NOT NULL DEFAULT 'active' AFTER role",
        'permissions_json' => "ALTER TABLE admins ADD COLUMN permissions_json LONGTEXT NULL AFTER status",
        'last_seen_at' => "ALTER TABLE admins ADD COLUMN last_seen_at DATETIME NULL AFTER permissions_json",
        'created_by' => "ALTER TABLE admins ADD COLUMN created_by VARCHAR(190) NULL AFTER last_seen_at",
    ];
    foreach ($adminColumns as $column => $sql) {
        if (!admin_table_has_column($pdo, 'admins', $column)) {
            $pdo->exec($sql);
        }
    }
    $pdo->exec("UPDATE admins SET role = 'owner' WHERE LOWER(email) = 'admin@phonix.local' AND (role IS NULL OR role = '' OR role = 'manager')");
    $pdo->exec("UPDATE admins SET status = 'active' WHERE status IS NULL OR status = ''");
    try {
        global $currentUser;
        $currentEmail = mb_strtolower(trim((string) ($currentUser['email'] ?? '')));
        if ($currentEmail === 'admin@phonix.local') {
            $currentName = trim((string) ($currentUser['name'] ?? 'Phonix Admin')) ?: 'Phonix Admin';
            $currentHash = (string) ($currentUser['password_hash'] ?? '');
            if ($currentHash === '') {
                $currentHash = password_hash(bin2hex(random_bytes(12)), PASSWORD_DEFAULT);
            }
            $stmt = $pdo->prepare("INSERT INTO admins (name, email, password_hash, role, status, created_by) VALUES (:name, :email, :password_hash, 'owner', 'active', 'system') ON DUPLICATE KEY UPDATE role = 'owner', status = 'active'");
            $stmt->execute(['name' => $currentName, 'email' => $currentEmail, 'password_hash' => $currentHash]);
        }
    } catch (Throwable $e) {
        error_log('[Phonix admin owner seed] ' . $e->getMessage());
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS coupons (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, code VARCHAR(80) NOT NULL, description VARCHAR(255) NULL, discount_type VARCHAR(20) NOT NULL DEFAULT 'percent', discount_value DECIMAL(10,2) NOT NULL DEFAULT 0.00, min_order_total DECIMAL(10,2) NOT NULL DEFAULT 0.00, starts_at DATETIME NULL, ends_at DATETIME NULL, max_uses INT UNSIGNED NULL, used_count INT UNSIGNED NOT NULL DEFAULT 0, is_active TINYINT(1) NOT NULL DEFAULT 1, created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (id), UNIQUE KEY uq_coupons_code (code), KEY idx_coupons_active (is_active), KEY idx_coupons_dates (starts_at, ends_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    if (function_exists('store_ensure_support_tables')) {
        store_ensure_support_tables($pdo);
    }
    if (function_exists('store_ensure_email_tables')) {
        store_ensure_email_tables($pdo);
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_activity_logs (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, admin_email VARCHAR(190) NULL, action VARCHAR(120) NOT NULL, entity_type VARCHAR(80) NULL, entity_id BIGINT UNSIGNED NULL, details TEXT NULL, created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), KEY idx_admin_activity_created (created_at), KEY idx_admin_activity_entity (entity_type, entity_id), KEY idx_admin_activity_action (action)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_maintenance_runs (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, admin_email VARCHAR(190) NULL, action VARCHAR(120) NOT NULL, affected_rows INT NOT NULL DEFAULT 0, details TEXT NULL, created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), KEY idx_admin_maintenance_created (created_at), KEY idx_admin_maintenance_action (action), KEY idx_admin_maintenance_admin (admin_email)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS inventory_movements (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, product_id BIGINT UNSIGNED NOT NULL, previous_stock INT NOT NULL DEFAULT 0, new_stock INT NOT NULL DEFAULT 0, delta INT NOT NULL DEFAULT 0, reason VARCHAR(160) NULL, admin_email VARCHAR(190) NULL, created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), KEY idx_inventory_product_created (product_id, created_at), KEY idx_inventory_created (created_at), CONSTRAINT fk_inventory_movements_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS order_status_events (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, order_id BIGINT UNSIGNED NOT NULL, status VARCHAR(60) NULL, payment_status VARCHAR(60) NULL, note TEXT NULL, is_customer_visible TINYINT(1) NOT NULL DEFAULT 1, admin_email VARCHAR(190) NULL, created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), KEY idx_order_events_order_created (order_id, created_at), KEY idx_order_events_customer_visible (order_id, is_customer_visible, created_at), CONSTRAINT fk_order_status_events_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    if (!admin_table_has_column($pdo, 'order_status_events', 'is_customer_visible')) {
        $pdo->exec('ALTER TABLE order_status_events ADD COLUMN is_customer_visible TINYINT(1) NOT NULL DEFAULT 1 AFTER note');
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS media_assets (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_path VARCHAR(500) NOT NULL, disk_path VARCHAR(500) NOT NULL, original_name VARCHAR(255) NOT NULL, mime_type VARCHAR(120) NOT NULL, extension VARCHAR(20) NOT NULL, file_size INT UNSIGNED NOT NULL DEFAULT 0, width INT UNSIGNED NULL, height INT UNSIGNED NULL, alt_text VARCHAR(255) NULL, caption VARCHAR(255) NULL, uploaded_by VARCHAR(190) NULL, created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (id), KEY idx_media_created (created_at), KEY idx_media_mime (mime_type), KEY idx_media_original_name (original_name)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS homepage_banners (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, title VARCHAR(190) NOT NULL, subtitle VARCHAR(255) NULL, eyebrow VARCHAR(120) NULL, cta_label VARCHAR(120) NULL, cta_url VARCHAR(500) NULL, image_path VARCHAR(500) NULL, sort_order INT NOT NULL DEFAULT 0, is_active TINYINT(1) NOT NULL DEFAULT 1, created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (id), KEY idx_homepage_banners_active_order (is_active, sort_order)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS site_pages (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, page_key VARCHAR(80) NOT NULL, page_label VARCHAR(120) NOT NULL, meta_title VARCHAR(190) NULL, meta_description VARCHAR(320) NULL, canonical_url VARCHAR(500) NULL, og_image VARCHAR(500) NULL, robots_index TINYINT(1) NOT NULL DEFAULT 1, is_active TINYINT(1) NOT NULL DEFAULT 1, created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (id), UNIQUE KEY uq_site_pages_key (page_key), KEY idx_site_pages_active (is_active), KEY idx_site_pages_robots (robots_index)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");


    $pdo->exec("CREATE TABLE IF NOT EXISTS deal_campaigns (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, title VARCHAR(190) NOT NULL, subtitle VARCHAR(255) NULL, badge VARCHAR(120) NULL, coupon_id BIGINT UNSIGNED NULL, product_id BIGINT UNSIGNED NULL, discount_label VARCHAR(120) NULL, cta_label VARCHAR(120) NULL, cta_url VARCHAR(500) NULL, image_path VARCHAR(500) NULL, starts_at DATETIME NULL, ends_at DATETIME NULL, sort_order INT NOT NULL DEFAULT 0, is_active TINYINT(1) NOT NULL DEFAULT 1, created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (id), KEY idx_deal_campaigns_active_dates (is_active, starts_at, ends_at), KEY idx_deal_campaigns_order (sort_order, id), KEY idx_deal_campaigns_coupon (coupon_id), KEY idx_deal_campaigns_product (product_id), CONSTRAINT fk_deal_campaign_coupon FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE SET NULL, CONSTRAINT fk_deal_campaign_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS homepage_featured_slots (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, slot_key VARCHAR(80) NOT NULL, product_id BIGINT UNSIGNED NULL, title_override VARCHAR(190) NULL, subtitle_override VARCHAR(255) NULL, badge_override VARCHAR(120) NULL, sort_order INT NOT NULL DEFAULT 0, is_active TINYINT(1) NOT NULL DEFAULT 1, created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (id), UNIQUE KEY uq_homepage_featured_slot (slot_key), KEY idx_homepage_featured_order (is_active, sort_order), KEY idx_homepage_featured_product (product_id), CONSTRAINT fk_homepage_featured_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    if (function_exists('store_ensure_checkout_tables')) {
        store_ensure_checkout_tables($pdo);
    }
    $done = true;
}

function admin_current_email(): ?string
{
    global $currentUser;
    $email = trim((string) ($currentUser['email'] ?? ''));
    return $email !== '' ? mb_substr($email, 0, 190) : null;
}

function admin_log_activity(PDO $pdo, string $action, ?string $entityType = null, ?int $entityId = null, ?string $details = null): void
{
    try {
        $stmt = $pdo->prepare('INSERT INTO admin_activity_logs (admin_email, action, entity_type, entity_id, details) VALUES (:admin_email, :action, :entity_type, :entity_id, :details)');
        $stmt->execute([
            'admin_email' => admin_current_email(),
            'action' => mb_substr($action, 0, 120),
            'entity_type' => $entityType ? mb_substr($entityType, 0, 80) : null,
            'entity_id' => $entityId,
            'details' => $details ? mb_substr($details, 0, 2000) : null,
        ]);
    } catch (Throwable $e) {
        error_log('[Phonix admin activity] ' . $e->getMessage());
    }
}

function admin_record_order_event(PDO $pdo, int $orderId, ?string $status, ?string $paymentStatus, ?string $note = null, bool $customerVisible = true): void
{
    try {
        $stmt = $pdo->prepare('INSERT INTO order_status_events (order_id, status, payment_status, note, is_customer_visible, admin_email) VALUES (:order_id, :status, :payment_status, :note, :is_customer_visible, :admin_email)');
        $stmt->execute([
            'order_id' => $orderId,
            'status' => $status !== null ? store_normalize_order_status($status) : null,
            'payment_status' => $paymentStatus !== null ? store_normalize_payment_status($paymentStatus) : null,
            'note' => $note ? mb_substr($note, 0, 2000) : null,
            'is_customer_visible' => $customerVisible ? 1 : 0,
            'admin_email' => admin_current_email(),
        ]);
    } catch (Throwable $e) {
        error_log('[Phonix order event] ' . $e->getMessage());
    }
}

function admin_adjust_product_stock(PDO $pdo, int $productId, int $newStock, string $reason = 'Manual adjustment'): void
{
    $newStock = max(0, $newStock);
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT id, name, stock FROM products WHERE id = :id LIMIT 1 FOR UPDATE');
        $stmt->execute(['id' => $productId]);
        $product = $stmt->fetch();
        if (!$product) {
            throw new RuntimeException('Product was not found.');
        }
        $previous = (int) $product['stock'];
        $delta = $newStock - $previous;
        $pdo->prepare('UPDATE products SET stock = :stock WHERE id = :id LIMIT 1')->execute(['stock' => $newStock, 'id' => $productId]);
        $move = $pdo->prepare('INSERT INTO inventory_movements (product_id, previous_stock, new_stock, delta, reason, admin_email) VALUES (:product_id, :previous_stock, :new_stock, :delta, :reason, :admin_email)');
        $move->execute([
            'product_id' => $productId,
            'previous_stock' => $previous,
            'new_stock' => $newStock,
            'delta' => $delta,
            'reason' => mb_substr($reason, 0, 160),
            'admin_email' => admin_current_email(),
        ]);
        $pdo->commit();
        admin_log_activity($pdo, 'stock_adjusted', 'product', $productId, $product['name'] . ': ' . $previous . ' → ' . $newStock . ' (' . $reason . ')');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function admin_media_upload_base_dir(): string
{
    return __DIR__ . '/../../assets/uploads/admin_media';
}

function admin_media_public_base_path(): string
{
    return 'assets/uploads/admin_media';
}

function admin_media_public_url(string $path): string
{
    $path = ltrim($path, '/');
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    return admin_root_url($path);
}

function admin_media_upload(PDO $pdo, array $file, string $altText = '', string $caption = ''): int
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Please choose a valid image file.');
    }
    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > 5 * 1024 * 1024) {
        throw new RuntimeException('Image size must be between 1 byte and 5 MB.');
    }
    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        throw new RuntimeException('Upload could not be verified.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file($tmp);
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Only JPG, PNG, WEBP, and GIF images are allowed.');
    }
    $dimensions = @getimagesize($tmp);
    if (!$dimensions) {
        throw new RuntimeException('The uploaded file is not a readable image.');
    }

    $subdir = date('Y/m');
    $diskDir = admin_media_upload_base_dir() . '/' . $subdir;
    if (!is_dir($diskDir) && !mkdir($diskDir, 0755, true) && !is_dir($diskDir)) {
        throw new RuntimeException('Could not create media upload directory.');
    }

    $extension = $allowed[$mime];
    $filename = date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
    $diskPath = $diskDir . '/' . $filename;
    if (!move_uploaded_file($tmp, $diskPath)) {
        throw new RuntimeException('Could not move uploaded image.');
    }
    @chmod($diskPath, 0644);

    $publicPath = admin_media_public_base_path() . '/' . $subdir . '/' . $filename;
    $stmt = $pdo->prepare('INSERT INTO media_assets (public_path, disk_path, original_name, mime_type, extension, file_size, width, height, alt_text, caption, uploaded_by) VALUES (:public_path, :disk_path, :original_name, :mime_type, :extension, :file_size, :width, :height, :alt_text, :caption, :uploaded_by)');
    $stmt->execute([
        'public_path' => $publicPath,
        'disk_path' => $diskPath,
        'original_name' => mb_substr((string) ($file['name'] ?? 'image.' . $extension), 0, 255),
        'mime_type' => $mime,
        'extension' => $extension,
        'file_size' => $size,
        'width' => (int) ($dimensions[0] ?? 0),
        'height' => (int) ($dimensions[1] ?? 0),
        'alt_text' => mb_substr($altText, 0, 255),
        'caption' => mb_substr($caption, 0, 255),
        'uploaded_by' => admin_current_email(),
    ]);
    $id = (int) $pdo->lastInsertId();
    admin_log_activity($pdo, 'media_uploaded', 'media', $id, $publicPath);
    return $id;
}

function admin_media_upload_many(PDO $pdo, array $files, string $altText = '', string $caption = ''): array
{
    $uploadedIds = [];
    $names = $files['name'] ?? [];
    if (!is_array($names)) {
        return $uploadedIds;
    }

    $count = count($names);
    for ($index = 0; $index < $count; $index++) {
        $error = (int) ($files['error'][$index] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $single = [
            'name' => $files['name'][$index] ?? '',
            'type' => $files['type'][$index] ?? '',
            'tmp_name' => $files['tmp_name'][$index] ?? '',
            'error' => $error,
            'size' => $files['size'][$index] ?? 0,
        ];
        $uploadedIds[] = admin_media_upload($pdo, $single, $altText, $caption);
    }

    return $uploadedIds;
}

function admin_media_find(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM media_assets WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function admin_bytes_label(int $bytes): string
{
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 2) . ' MB';
    }
    if ($bytes >= 1024) {
        return round($bytes / 1024, 1) . ' KB';
    }
    return $bytes . ' B';
}

function admin_coupon_code(string $key = 'code'): string
{
    $code = strtoupper(trim((string) ($_POST[$key] ?? '')));
    $code = preg_replace('/[^A-Z0-9_-]+/', '', $code) ?? '';
    return mb_substr($code, 0, 80);
}

function admin_nullable_datetime_input(string $key): ?string
{
    $raw = trim((string) ($_POST[$key] ?? ''));
    if ($raw === '') {
        return null;
    }
    $raw = str_replace('T', ' ', $raw);
    $time = strtotime($raw);
    return $time ? date('Y-m-d H:i:s', $time) : null;
}

function admin_datetime_value($value): string
{
    if (!$value) {
        return '';
    }
    $time = strtotime((string) $value);
    return $time ? date('Y-m-d\TH:i', $time) : '';
}

function admin_setting(PDO $pdo, string $key, string $default = ''): string
{
    try {
        $stmt = $pdo->prepare('SELECT setting_value FROM site_settings WHERE setting_key = :k LIMIT 1');
        $stmt->execute(['k' => $key]);
        $value = $stmt->fetchColumn();
        return $value === false || $value === null ? $default : (string) $value;
    } catch (Throwable $e) {
        error_log('[Phonix admin setting] ' . $e->getMessage());
        return $default;
    }
}

function admin_product_options(PDO $pdo): array
{
    return admin_rows($pdo, 'SELECT id, name, slug, brand, price, stock, is_active FROM products ORDER BY is_active DESC, name ASC, id DESC');
}

function admin_homepage_default_slots(PDO $pdo): void
{
    $defaults = [
        ['hero', null, null, null, null, 10],
        ['audio', null, null, null, null, 20],
        ['watch', null, null, null, null, 30],
    ];
    $stmt = $pdo->prepare('INSERT INTO homepage_featured_slots (slot_key, product_id, title_override, subtitle_override, badge_override, sort_order, is_active) VALUES (:slot_key, :product_id, :title_override, :subtitle_override, :badge_override, :sort_order, 1) ON DUPLICATE KEY UPDATE slot_key = slot_key');
    foreach ($defaults as $slot) {
        $stmt->execute([
            'slot_key' => $slot[0],
            'product_id' => $slot[1],
            'title_override' => $slot[2],
            'subtitle_override' => $slot[3],
            'badge_override' => $slot[4],
            'sort_order' => $slot[5],
        ]);
    }

    $pdo->exec("UPDATE homepage_featured_slots SET title_override = NULL WHERE title_override IN ('Hero product', 'Audio spotlight', 'Wearables spotlight')");
    $pdo->exec("UPDATE homepage_featured_slots SET subtitle_override = NULL WHERE subtitle_override IN ('Main hero product on the homepage', 'Secondary audio product card', 'Secondary watch/wearable product card')");
    $pdo->exec("UPDATE homepage_featured_slots SET badge_override = NULL WHERE badge_override IN ('Flagship', 'Immersive Audio', 'Wearables')");
}


function admin_homepage_slots(PDO $pdo): array
{
    admin_homepage_default_slots($pdo);
    return admin_rows($pdo, 'SELECT s.*, p.name AS product_name, p.slug AS product_slug, p.price AS product_price, p.image AS product_image, p.brand AS product_brand FROM homepage_featured_slots s LEFT JOIN products p ON p.id = s.product_id ORDER BY s.sort_order ASC, s.id ASC');
}

function admin_homepage_banners(PDO $pdo): array
{
    return admin_rows($pdo, 'SELECT * FROM homepage_banners ORDER BY is_active DESC, sort_order ASC, id DESC');
}

function admin_deal_campaigns(PDO $pdo): array
{
    return admin_rows($pdo, "SELECT d.*, c.code AS coupon_code, c.discount_type AS coupon_discount_type, c.discount_value AS coupon_discount_value, p.name AS product_name, p.slug AS product_slug, p.price AS product_price, p.compare_price AS product_compare_price, p.image AS product_image FROM deal_campaigns d LEFT JOIN coupons c ON c.id = d.coupon_id LEFT JOIN products p ON p.id = d.product_id ORDER BY d.is_active DESC, d.sort_order ASC, d.id DESC");
}

function admin_active_deals_count(PDO $pdo): int
{
    return (int) admin_scalar($pdo, "SELECT COUNT(*) FROM deal_campaigns WHERE is_active = 1 AND (starts_at IS NULL OR starts_at <= NOW()) AND (ends_at IS NULL OR ends_at >= NOW())");
}


function admin_notification_key(string $source, int $value = 0, string $scope = ''): string
{
    $raw = $source . ':' . $value . ':' . $scope;
    return mb_substr(preg_replace('/[^a-z0-9:_-]+/i', '-', $raw) ?: $source, 0, 190);
}

function admin_notification_dismissed_keys(PDO $pdo, ?string $email = null): array
{
    $email = mb_strtolower(trim((string) ($email ?: admin_current_email())));
    if ($email === '') {
        return [];
    }
    $rows = admin_rows($pdo, 'SELECT notification_key FROM admin_notification_dismissals WHERE LOWER(admin_email) = :email', ['email' => $email]);
    $keys = [];
    foreach ($rows as $row) {
        $keys[(string) $row['notification_key']] = true;
    }
    return $keys;
}

function admin_dismiss_notification(PDO $pdo, string $notificationKey): void
{
    $email = mb_strtolower(trim((string) admin_current_email()));
    $notificationKey = mb_substr(preg_replace('/[^a-z0-9:_-]+/i', '-', trim($notificationKey)) ?: '', 0, 190);
    if ($email === '' || $notificationKey === '') {
        return;
    }
    $stmt = $pdo->prepare('INSERT IGNORE INTO admin_notification_dismissals (admin_email, notification_key) VALUES (:email, :notification_key)');
    $stmt->execute(['email' => $email, 'notification_key' => $notificationKey]);
}

function admin_restore_notification(PDO $pdo, string $notificationKey): void
{
    $email = mb_strtolower(trim((string) admin_current_email()));
    $notificationKey = mb_substr(preg_replace('/[^a-z0-9:_-]+/i', '-', trim($notificationKey)) ?: '', 0, 190);
    if ($email === '' || $notificationKey === '') {
        return;
    }
    $stmt = $pdo->prepare('DELETE FROM admin_notification_dismissals WHERE LOWER(admin_email) = :email AND notification_key = :notification_key');
    $stmt->execute(['email' => $email, 'notification_key' => $notificationKey]);
}

function admin_system_notifications(PDO $pdo, array $ctx = [], bool $includeDismissed = false): array
{
    global $currentUser;
    $siteSettings = $ctx['siteSettings'] ?? [];
    $items = [];
    $add = static function (string $source, int $value, string $severity, string $icon, string $title, string $body, string $section, array $query = [], string $scope = '') use (&$items): void {
        if ($value <= 0 && $severity !== 'info') {
            return;
        }
        $items[] = [
            'key' => admin_notification_key($source, $value, $scope),
            'source' => $source,
            'value' => $value,
            'severity' => $severity,
            'icon' => $icon,
            'title' => $title,
            'body' => $body,
            'section' => $section,
            'url' => admin_page_url($section, $query),
        ];
    };

    $pendingOrders = (int) admin_scalar($pdo, "SELECT COUNT(*) FROM orders WHERE status IN ('pending','processing')", [], 0);
    $unpaidOrders = (int) admin_scalar($pdo, "SELECT COUNT(*) FROM orders WHERE payment_status IN ('unpaid','pending','failed')", [], 0);
    $newSupport = (int) admin_scalar($pdo, "SELECT COUNT(*) FROM support_messages WHERE status = 'new'", [], 0);
    $openSupport = (int) admin_scalar($pdo, "SELECT COUNT(*) FROM support_messages WHERE status = 'open'", [], 0);
    $lowStock = (int) admin_scalar($pdo, "SELECT COUNT(*) FROM products WHERE is_active = 1 AND product_status IN ('active','out_of_stock') AND stock <= 5", [], 0);
    $outOfStock = (int) admin_scalar($pdo, "SELECT COUNT(*) FROM products WHERE is_active = 1 AND product_status = 'out_of_stock'", [], 0);
    $productsWithoutImage = (int) admin_scalar($pdo, "SELECT COUNT(*) FROM products WHERE is_active = 1 AND product_status IN ('active','out_of_stock') AND (image IS NULL OR image = '')", [], 0);
    $activeShipping = (int) admin_scalar($pdo, 'SELECT COUNT(*) FROM shipping_methods WHERE is_active = 1', [], 0);
    $activePayments = (int) admin_scalar($pdo, 'SELECT COUNT(*) FROM payment_methods WHERE is_active = 1', [], 0);
    $expiredDeals = (int) admin_scalar($pdo, "SELECT COUNT(*) FROM deal_campaigns WHERE is_active = 1 AND ends_at IS NOT NULL AND ends_at < NOW()", [], 0);
    $scheduledDeals = (int) admin_scalar($pdo, "SELECT COUNT(*) FROM deal_campaigns WHERE is_active = 1 AND starts_at IS NOT NULL AND starts_at > NOW()", [], 0);
    $seoGaps = (int) admin_scalar($pdo, "SELECT COUNT(*) FROM site_pages WHERE is_active = 1 AND ((meta_title IS NULL OR meta_title = '') OR (meta_description IS NULL OR meta_description = ''))", [], 0);
    $maintenance = ((string) ($siteSettings['maintenance_mode'] ?? '0')) === '1' ? 1 : 0;
    $inactiveAdmins = (int) admin_scalar($pdo, "SELECT COUNT(*) FROM admins WHERE status = 'suspended'", [], 0);
    $failedEmails = (int) admin_scalar($pdo, "SELECT COUNT(*) FROM email_outbox WHERE status = 'failed'", [], 0);

    $add('orders-pending', $pendingOrders, 'warning', 'pending_actions', 'Orders need fulfillment', $pendingOrders . ' order(s) are still pending or processing.', 'orders', ['status' => 'pending']);
    $add('orders-unpaid', $unpaidOrders, 'danger', 'payments', 'Payments need review', $unpaidOrders . ' order(s) have unpaid, pending, or failed payment status.', 'orders', ['payment' => 'unpaid']);
    $add('support-new', $newSupport, 'danger', 'mark_email_unread', 'New support messages', $newSupport . ' message(s) have not been opened yet.', 'support', ['status' => 'new']);
    $add('support-open', $openSupport, 'warning', 'support_agent', 'Open support conversations', $openSupport . ' support conversation(s) are still open.', 'support', ['status' => 'open']);
    $add('stock-low', $lowStock, 'warning', 'inventory', 'Low stock risk', $lowStock . ' active product(s) are at or below 5 units.', 'inventory', ['risk' => 'low']);
    $add('stock-empty', $outOfStock, 'danger', 'production_quantity_limits', 'Out-of-stock products', $outOfStock . ' product(s) are currently marked out of stock.', 'products', ['status' => 'out_of_stock']);
    $add('catalog-no-image', $productsWithoutImage, 'info', 'image_not_supported', 'Products missing images', $productsWithoutImage . ' active product(s) do not have a main image.', 'products');
    $add('shipping-missing', $activeShipping === 0 ? 1 : 0, 'danger', 'local_shipping', 'No active shipping methods', 'Checkout cannot work correctly until at least one shipping method is active.', 'shipping_payments');
    $add('payment-missing', $activePayments === 0 ? 1 : 0, 'danger', 'credit_card_off', 'No active payment methods', 'Checkout cannot work correctly until at least one payment method is active.', 'shipping_payments');
    $add('deals-expired', $expiredDeals, 'warning', 'event_busy', 'Expired active deals', $expiredDeals . ' active campaign(s) already ended and should be hidden or renewed.', 'deals');
    $add('deals-scheduled', $scheduledDeals, 'info', 'event_upcoming', 'Scheduled deals waiting', $scheduledDeals . ' campaign(s) are scheduled for later.', 'deals');
    $add('seo-gaps', $seoGaps, 'warning', 'travel_explore', 'SEO fields incomplete', $seoGaps . ' active page(s) are missing meta title or description.', 'seo');
    $add('maintenance-on', $maintenance, 'danger', 'construction', 'Maintenance mode is enabled', 'Visitors currently see the maintenance page while admins can still work.', 'settings');
    $add('email-failed', $failedEmails, 'warning', 'outgoing_mail', 'Failed email deliveries', $failedEmails . ' email(s) failed and can be requeued from Email Center.', 'email', ['status' => 'failed']);
    $add('admins-suspended', $inactiveAdmins, 'info', 'admin_panel_settings', 'Suspended admin accounts', $inactiveAdmins . ' admin account(s) are suspended.', 'admin_users');

    $dismissed = admin_notification_dismissed_keys($pdo);
    $visible = [];
    foreach ($items as $item) {
        if (!admin_user_can_section($pdo, (string) $item['section'], $currentUser ?? null)) {
            continue;
        }
        $item['dismissed'] = isset($dismissed[$item['key']]);
        if (!$includeDismissed && $item['dismissed']) {
            continue;
        }
        $visible[] = $item;
    }

    usort($visible, static function (array $a, array $b): int {
        $rank = ['danger' => 0, 'warning' => 1, 'info' => 2, 'good' => 3];
        return ($rank[$a['severity']] ?? 9) <=> ($rank[$b['severity']] ?? 9) ?: strcmp($a['title'], $b['title']);
    });
    return $visible;
}

function admin_notification_counts(PDO $pdo, array $ctx = []): array
{
    $all = admin_system_notifications($pdo, $ctx, true);
    $active = 0;
    $critical = 0;
    $dismissed = 0;
    foreach ($all as $item) {
        if (!empty($item['dismissed'])) {
            $dismissed++;
            continue;
        }
        $active++;
        if (($item['severity'] ?? '') === 'danger') {
            $critical++;
        }
    }
    return ['active' => $active, 'critical' => $critical, 'dismissed' => $dismissed, 'total' => count($all)];
}

function admin_datetime_input(string $key): ?string
{
    $raw = trim((string) ($_POST[$key] ?? ''));
    if ($raw === '') {
        return null;
    }
    $time = strtotime($raw);
    return $time ? date('Y-m-d H:i:s', $time) : null;
}

function admin_deal_status_label(array $deal): string
{
    if ((int) ($deal['is_active'] ?? 0) !== 1) {
        return 'Hidden';
    }
    $now = time();
    $starts = !empty($deal['starts_at']) ? strtotime((string) $deal['starts_at']) : null;
    $ends = !empty($deal['ends_at']) ? strtotime((string) $deal['ends_at']) : null;
    if ($starts && $starts > $now) {
        return 'Scheduled';
    }
    if ($ends && $ends < $now) {
        return 'Expired';
    }
    return 'Live';
}

function admin_deal_status_class(array $deal): string
{
    $label = admin_deal_status_label($deal);
    if ($label === 'Live') {
        return 'success';
    }
    if ($label === 'Scheduled') {
        return 'warning';
    }
    if ($label === 'Expired') {
        return 'danger';
    }
    return 'muted';
}


function admin_seed_default_site_pages(PDO $pdo): void
{
    $defaults = [
        ['home', 'Homepage', 'index.php'],
        ['products', 'Products Listing', 'products.php'],
        ['search', 'Search Results', 'search.php'],
        ['brands', 'Brands', 'brands.php'],
        ['support', 'Support Center', 'support.php'],
        ['deals', 'Deals', 'deals.php'],
        ['account', 'Account', 'account.php'],
        ['checkout', 'Checkout', 'checkout.php'],
    ];
    $stmt = $pdo->prepare('INSERT INTO site_pages (page_key, page_label, canonical_url, robots_index, is_active) VALUES (:page_key, :page_label, :canonical_url, 1, 1) ON DUPLICATE KEY UPDATE page_label = VALUES(page_label)');
    foreach ($defaults as $page) {
        $stmt->execute([
            'page_key' => $page[0],
            'page_label' => $page[1],
            'canonical_url' => $page[2],
        ]);
    }
}

function admin_site_pages(PDO $pdo): array
{
    admin_seed_default_site_pages($pdo);
    return admin_rows($pdo, 'SELECT * FROM site_pages ORDER BY FIELD(page_key, \'home\', \'products\', \'search\', \'brands\', \'support\', \'deals\', \'account\', \'checkout\'), page_label ASC, id ASC');
}

function admin_site_page_find(PDO $pdo, string $pageKey): ?array
{
    admin_seed_default_site_pages($pdo);
    $stmt = $pdo->prepare('SELECT * FROM site_pages WHERE page_key = :page_key LIMIT 1');
    $stmt->execute(['page_key' => mb_substr($pageKey, 0, 80)]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function admin_store_setting(PDO $pdo, string $key, string $value): void
{
    $stmt = $pdo->prepare('INSERT INTO site_settings (setting_key, setting_value) VALUES (:k, :v) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP');
    $stmt->execute(['k' => $key, 'v' => $value]);
}


function admin_datetime_local_value($value): string
{
    if (!$value) {
        return '';
    }
    $time = strtotime((string) $value);
    return $time ? date('Y-m-d\TH:i', $time) : '';
}
function admin_flash_from_exception(Throwable $e, string $fallback = 'The requested admin action could not be completed.'): void
{
    error_log('[Phonix admin action] ' . $e->getMessage());
    flash_set('error', app_debug() ? $e->getMessage() : $fallback);
}

function admin_boot(string $active): array
{
    global $pdo, $currentUser, $siteSettings, $siteName, $siteCurrency, $flashMessages;
    admin_handle_asset_check();
    admin_ensure_console_tables($pdo);
    admin_ensure_product_catalog_columns($pdo);
    require_once __DIR__ . '/../../includes/phone_matcher.php';
    phone_finder_ensure_tables($pdo);
    $adminUser = admin_require_access($pdo, $currentUser);
    admin_require_section_access($pdo, $active);
    return [
        'pdo' => $pdo,
        'currentUser' => $currentUser,
        'adminUser' => $adminUser,
        'siteSettings' => $siteSettings,
        'siteName' => $siteName,
        'siteCurrency' => $siteCurrency,
        'flashMessages' => $flashMessages,
        'active' => $active,
    ];
}


function admin_table_exists(PDO $pdo, string $table): bool
{
    static $cache = [];
    $key = strtolower($table);
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table');
        $stmt->execute(['table' => $table]);
        $cache[$key] = ((int) $stmt->fetchColumn()) > 0;
        return $cache[$key];
    } catch (Throwable $e) {
        error_log('[Phonix admin table check] ' . $e->getMessage());
        return false;
    }
}

function admin_health_severity_rank(string $severity): int
{
    return match ($severity) {
        'critical' => 0,
        'danger' => 1,
        'warning' => 2,
        'info' => 3,
        'good' => 4,
        default => 9,
    };
}

function admin_system_health_checks(PDO $pdo, array $ctx = []): array
{
    $settings = $ctx['siteSettings'] ?? ($GLOBALS['siteSettings'] ?? []);
    $checks = [];
    $add = static function (string $group, string $title, string $body, string $severity, string $icon, string $section = 'system_health', array $query = [], ?string $value = null) use (&$checks): void {
        $checks[] = [
            'group' => $group,
            'title' => $title,
            'body' => $body,
            'severity' => $severity,
            'icon' => $icon,
            'section' => admin_normalize_section($section),
            'url' => admin_page_url($section, $query),
            'value' => $value,
        ];
    };

    $requiredTables = [
        'users', 'admins', 'products', 'categories', 'brands', 'orders', 'order_items',
        'cart_items', 'wishlist_items', 'coupons', 'shipping_methods', 'payment_methods',
        'support_faqs', 'support_messages', 'site_settings', 'site_pages', 'media_assets',
        'homepage_banners', 'homepage_featured_slots', 'deal_campaigns', 'email_templates',
        'email_outbox', 'admin_activity_logs', 'admin_notification_dismissals', 'admin_maintenance_runs',
        'inventory_movements', 'order_status_events',
    ];
    $missingTables = [];
    foreach ($requiredTables as $table) {
        if (!admin_table_exists($pdo, $table)) {
            $missingTables[] = $table;
        }
    }
    if ($missingTables) {
        $add('Database', 'Missing required database tables', 'Missing: ' . implode(', ', $missingTables) . '. Run the latest schema or open the relevant admin sections to trigger auto-migrations.', 'critical', 'database_off', 'settings', [], (string) count($missingTables));
    } else {
        $add('Database', 'Required database tables are present', 'Core store, checkout, admin, email, support, and catalog tables were found.', 'good', 'database', 'settings', [], (string) count($requiredTables));
    }

    $requiredColumns = [
        'products' => ['sku', 'slug', 'category_id', 'brand_id', 'price', 'compare_price', 'stock', 'image', 'gallery_json', 'product_status', 'product_type', 'specs_json', 'is_featured', 'is_active'],
        'orders' => ['order_number', 'user_id', 'full_name', 'email', 'subtotal', 'shipping_total', 'discount_total', 'tax_total', 'total', 'status', 'payment_status', 'shipping_method_id', 'shipping_method_name', 'payment_method_id', 'payment_method_name', 'coupon_code', 'tracking_number', 'tracking_carrier', 'tracking_url', 'internal_notes'],
        'admins' => ['role', 'status', 'permissions_json', 'last_seen_at', 'created_by'],
        'email_outbox' => ['status', 'attempts', 'last_attempt_at', 'error_message'],
        'site_settings' => ['setting_key', 'setting_value'],
    ];
    $missingColumns = [];
    foreach ($requiredColumns as $table => $columns) {
        if (!admin_table_exists($pdo, $table)) {
            continue;
        }
        foreach ($columns as $column) {
            if (!admin_table_has_column($pdo, $table, $column)) {
                $missingColumns[] = $table . '.' . $column;
            }
        }
    }
    if ($missingColumns) {
        $add('Database', 'Missing required columns', 'Missing columns: ' . implode(', ', $missingColumns) . '.', 'critical', 'view_column', 'settings', [], (string) count($missingColumns));
    } else {
        $add('Database', 'Required columns are present', 'Admin, checkout, product, order, and email columns look compatible with the current build.', 'good', 'fact_check', 'settings');
    }

    $ownerCount = (int) admin_scalar($pdo, "SELECT COUNT(*) FROM admins WHERE role = 'owner' AND status = 'active'", [], 0);
    $add('Access', $ownerCount > 0 ? 'Active owner account exists' : 'No active owner account', $ownerCount > 0 ? 'At least one active owner can manage critical admin settings.' : 'Create or restore an owner account before relying on staff roles.', $ownerCount > 0 ? 'good' : 'critical', $ownerCount > 0 ? 'admin_panel_settings' : 'gpp_bad', 'admin_users', [], (string) $ownerCount);

    $debugOn = function_exists('app_debug') && app_debug();
    $setupAllowed = function_exists('app_flag') && app_flag('allow_setup', false);
    $appSecretDefault = function_exists('env_value') && env_value('PHONIX_APP_SECRET', 'change-this-in-production') === 'change-this-in-production';
    $add('Security', $debugOn ? 'Debug mode is enabled' : 'Debug mode is disabled', $debugOn ? 'PHONIX_APP_DEBUG should be disabled in production.' : 'Production visitors will not receive raw debug details.', $debugOn ? 'danger' : 'good', $debugOn ? 'bug_report' : 'verified_user', 'settings');
    $add('Security', $setupAllowed ? 'Setup endpoint is enabled' : 'Setup endpoint is disabled', $setupAllowed ? 'PHONIX_ALLOW_SETUP is true. Disable it after initial deployment.' : 'The installer is not available unless explicitly enabled by environment.', $setupAllowed ? 'danger' : 'good', $setupAllowed ? 'warning' : 'lock', 'settings');
    $add('Security', $appSecretDefault ? 'Default app secret is still in use' : 'Custom app secret detected', $appSecretDefault ? 'Set PHONIX_APP_SECRET to a strong random value so CSRF/session-derived secrets are not predictable.' : 'PHONIX_APP_SECRET is not using the bundled fallback value.', $appSecretDefault ? 'warning' : 'good', $appSecretDefault ? 'key_off' : 'key', 'settings');

    $activeShipping = (int) admin_scalar($pdo, 'SELECT COUNT(*) FROM shipping_methods WHERE is_active = 1', [], 0);
    $activePayments = (int) admin_scalar($pdo, 'SELECT COUNT(*) FROM payment_methods WHERE is_active = 1', [], 0);
    $activeProducts = (int) admin_scalar($pdo, "SELECT COUNT(*) FROM products WHERE is_active = 1 AND product_status = 'active'", [], 0);
    $add('Checkout', $activeShipping > 0 ? 'Active shipping methods are available' : 'No active shipping methods', $activeShipping > 0 ? 'Checkout can offer shipping choices.' : 'Customers cannot complete checkout reliably without at least one active shipping method.', $activeShipping > 0 ? 'good' : 'critical', 'local_shipping', 'shipping_payments', [], (string) $activeShipping);
    $add('Checkout', $activePayments > 0 ? 'Active payment methods are available' : 'No active payment methods', $activePayments > 0 ? 'Checkout can offer payment choices.' : 'Customers cannot complete checkout reliably without at least one active payment method.', $activePayments > 0 ? 'good' : 'critical', 'payments', 'shipping_payments', [], (string) $activePayments);
    $add('Checkout', $activeProducts > 0 ? 'Purchasable products exist' : 'No active purchasable products', $activeProducts > 0 ? 'The storefront has products that can be sold.' : 'Add or activate at least one product before launch.', $activeProducts > 0 ? 'good' : 'warning', 'shopping_cart', 'products', [], (string) $activeProducts);

    $draftActive = (int) admin_scalar($pdo, "SELECT COUNT(*) FROM products WHERE is_active = 1 AND product_status IN ('draft','archived')", [], 0);
    $activeHidden = (int) admin_scalar($pdo, "SELECT COUNT(*) FROM products WHERE is_active = 0 AND product_status = 'active'", [], 0);
    $missingImages = (int) admin_scalar($pdo, "SELECT COUNT(*) FROM products WHERE is_active = 1 AND product_status IN ('active','out_of_stock') AND (image IS NULL OR image = '')", [], 0);
    $lowStock = (int) admin_scalar($pdo, "SELECT COUNT(*) FROM products WHERE is_active = 1 AND product_status IN ('active','out_of_stock') AND stock <= 5", [], 0);
    $outOfStock = (int) admin_scalar($pdo, "SELECT COUNT(*) FROM products WHERE is_active = 1 AND product_status = 'out_of_stock'", [], 0);
    $add('Catalog', $draftActive === 0 ? 'Product visibility flags are consistent' : 'Product visibility mismatch', $draftActive === 0 ? 'No draft/archived products are still marked active.' : $draftActive . ' draft or archived product(s) are still marked is_active=1.', $draftActive === 0 ? 'good' : 'warning', 'visibility', 'products', [], (string) $draftActive);
    if ($activeHidden > 0) {
        $add('Catalog', 'Hidden active products', $activeHidden . ' product(s) have active status but is_active=0. Review if this is intentional.', 'info', 'visibility_off', 'products', ['status' => 'hidden'], (string) $activeHidden);
    }
    $add('Catalog', $missingImages === 0 ? 'Product images look complete' : 'Products missing main images', $missingImages === 0 ? 'Active storefront products have main images.' : $missingImages . ' active product(s) need main images.', $missingImages === 0 ? 'good' : 'warning', 'image', 'products', [], (string) $missingImages);
    if ($lowStock > 0) {
        $add('Inventory', 'Low-stock products detected', $lowStock . ' product(s) are at or below 5 units.', 'warning', 'inventory', 'inventory', ['risk' => 'low'], (string) $lowStock);
    } else {
        $add('Inventory', 'No low-stock risk detected', 'No active products are currently at or below 5 units.', 'good', 'inventory_2', 'inventory');
    }
    if ($outOfStock > 0) {
        $add('Inventory', 'Out-of-stock products detected', $outOfStock . ' product(s) are visible as out of stock.', 'info', 'production_quantity_limits', 'products', ['status' => 'out_of_stock'], (string) $outOfStock);
    }

    $seoGaps = (int) admin_scalar($pdo, "SELECT COUNT(*) FROM site_pages WHERE is_active = 1 AND ((meta_title IS NULL OR meta_title = '') OR (meta_description IS NULL OR meta_description = ''))", [], 0);
    $expiredDeals = (int) admin_scalar($pdo, "SELECT COUNT(*) FROM deal_campaigns WHERE is_active = 1 AND ends_at IS NOT NULL AND ends_at < NOW()", [], 0);
    $slotsWithoutProducts = (int) admin_scalar($pdo, "SELECT COUNT(*) FROM homepage_featured_slots WHERE is_active = 1 AND product_id IS NULL", [], 0);
    $activeBanners = (int) admin_scalar($pdo, 'SELECT COUNT(*) FROM homepage_banners WHERE is_active = 1', [], 0);
    $add('Storefront', $seoGaps === 0 ? 'SEO metadata is complete' : 'SEO metadata gaps', $seoGaps === 0 ? 'All active pages have title and description metadata.' : $seoGaps . ' active page(s) are missing SEO title or description.', $seoGaps === 0 ? 'good' : 'warning', 'travel_explore', 'seo', [], (string) $seoGaps);
    $add('Storefront', $expiredDeals === 0 ? 'No expired active deals' : 'Expired deals still active', $expiredDeals === 0 ? 'Active deal campaigns are within their date windows.' : $expiredDeals . ' active deal campaign(s) have ended.', $expiredDeals === 0 ? 'good' : 'warning', 'event_busy', 'deals', [], (string) $expiredDeals);
    if ($slotsWithoutProducts > 0) {
        $add('Storefront', 'Homepage slots without products', $slotsWithoutProducts . ' active homepage featured slot(s) do not point to a product.', 'info', 'web_asset_off', 'homepage', [], (string) $slotsWithoutProducts);
    }
    if ($activeBanners === 0) {
        $add('Storefront', 'No active homepage banners', 'Homepage can still use fallbacks, but active banners improve merchandising.', 'info', 'web', 'homepage', [], '0');
    }

    $maintenanceMode = ((string) ($settings['maintenance_mode'] ?? '0')) === '1';
    $supportEmail = trim((string) ($settings['support_email'] ?? ''));
    $currency = trim((string) ($settings['site_currency'] ?? ''));
    $add('Settings', $maintenanceMode ? 'Maintenance mode is enabled' : 'Maintenance mode is off', $maintenanceMode ? 'Visitors see the maintenance page while admins can continue working.' : 'The public storefront is available to visitors.', $maintenanceMode ? 'warning' : 'good', 'construction', 'settings');
    $add('Settings', filter_var($supportEmail, FILTER_VALIDATE_EMAIL) ? 'Support email is valid' : 'Support email needs review', filter_var($supportEmail, FILTER_VALIDATE_EMAIL) ? 'Support and footer contact details look usable.' : 'Set a valid support email in Settings for customer communication.', filter_var($supportEmail, FILTER_VALIDATE_EMAIL) ? 'good' : 'warning', 'alternate_email', 'settings');
    $add('Settings', preg_match('/^[A-Z]{3}$/', $currency) ? 'Currency code looks valid' : 'Currency code needs review', preg_match('/^[A-Z]{3}$/', $currency) ? 'Currency uses a three-letter ISO-style code.' : 'Use a three-letter code such as USD, TRY, EUR, or SAR.', preg_match('/^[A-Z]{3}$/', $currency) ? 'good' : 'warning', 'attach_money', 'settings');

    $emailDelivery = ((string) ($settings['email_delivery_enabled'] ?? '0')) === '1';
    $fromEmail = trim((string) ($settings['email_from_email'] ?? ''));
    $queuedEmails = (int) admin_scalar($pdo, "SELECT COUNT(*) FROM email_outbox WHERE status = 'queued'", [], 0);
    $failedEmails = (int) admin_scalar($pdo, "SELECT COUNT(*) FROM email_outbox WHERE status = 'failed'", [], 0);
    $staleQueued = (int) admin_scalar($pdo, "SELECT COUNT(*) FROM email_outbox WHERE status = 'queued' AND created_at < DATE_SUB(NOW(), INTERVAL 1 DAY)", [], 0);
    if ($emailDelivery && !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        $add('Email', 'Email delivery enabled without valid From email', 'Set a valid From email before processing the queue.', 'danger', 'outgoing_mail', 'email');
    } else {
        $add('Email', $emailDelivery ? 'Email delivery is enabled' : 'Email delivery is disabled', $emailDelivery ? 'The worker can process queued messages.' : 'Messages can still queue, but they will not be sent until delivery is enabled.', $emailDelivery ? 'good' : 'info', 'mark_email_read', 'email');
    }
    if ($failedEmails > 0) {
        $add('Email', 'Failed email deliveries', $failedEmails . ' email(s) failed and may need requeueing or hosting mail configuration.', 'warning', 'error', 'email', ['status' => 'failed'], (string) $failedEmails);
    }
    if ($staleQueued > 0) {
        $add('Email', 'Stale queued emails', $staleQueued . ' queued email(s) are older than 24 hours.', 'warning', 'schedule_send', 'email', ['status' => 'queued'], (string) $staleQueued);
    } elseif ($queuedEmails > 0) {
        $add('Email', 'Queued emails waiting', $queuedEmails . ' email(s) are queued for delivery.', 'info', 'mail', 'email', ['status' => 'queued'], (string) $queuedEmails);
    }

    $newSupport = (int) admin_scalar($pdo, "SELECT COUNT(*) FROM support_messages WHERE status = 'new'", [], 0);
    $openSupport = (int) admin_scalar($pdo, "SELECT COUNT(*) FROM support_messages WHERE status = 'open'", [], 0);
    if ($newSupport + $openSupport > 0) {
        $add('Support', 'Support backlog exists', ($newSupport + $openSupport) . ' message(s) are new or open.', $newSupport > 0 ? 'warning' : 'info', 'support_agent', 'support', [], (string) ($newSupport + $openSupport));
    } else {
        $add('Support', 'Support inbox is clear', 'No new or open support conversations need action.', 'good', 'support_agent', 'support');
    }

    $lastBackup = admin_last_maintenance_run($pdo, 'database_backup');
    $backupAgeDays = $lastBackup && !empty($lastBackup['created_at']) ? floor((time() - strtotime((string) $lastBackup['created_at'])) / 86400) : null;
    if ($backupAgeDays === null) {
        $add('Maintenance', 'No admin database backup recorded', 'Download a database backup after major changes so you have a known restore point.', 'warning', 'backup', 'maintenance_tools');
    } elseif ($backupAgeDays > 14) {
        $add('Maintenance', 'Database backup is older than 14 days', 'Last recorded backup was ' . $backupAgeDays . ' day(s) ago.', 'info', 'backup', 'maintenance_tools', [], (string) $backupAgeDays);
    } else {
        $add('Maintenance', 'Recent database backup recorded', 'Last recorded backup was ' . $backupAgeDays . ' day(s) ago.', 'good', 'backup', 'maintenance_tools', [], (string) $backupAgeDays);
    }

    usort($checks, static function (array $a, array $b): int {
        $rank = admin_health_severity_rank((string) $a['severity']) <=> admin_health_severity_rank((string) $b['severity']);
        if ($rank !== 0) {
            return $rank;
        }
        return strcmp((string) $a['group'], (string) $b['group']) ?: strcmp((string) $a['title'], (string) $b['title']);
    });

    return $checks;
}

function admin_system_health_summary(array $checks): array
{
    $summary = ['critical' => 0, 'danger' => 0, 'warning' => 0, 'info' => 0, 'good' => 0, 'total' => count($checks), 'score' => 100];
    foreach ($checks as $check) {
        $severity = (string) ($check['severity'] ?? 'info');
        if (!array_key_exists($severity, $summary)) {
            $severity = 'info';
        }
        $summary[$severity]++;
    }
    $penalty = ($summary['critical'] * 25) + ($summary['danger'] * 15) + ($summary['warning'] * 7) + ($summary['info'] * 2);
    $summary['score'] = max(0, min(100, 100 - $penalty));
    return $summary;
}

function admin_database_name(PDO $pdo): string
{
    try {
        return (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    } catch (Throwable $e) {
        error_log('[Phonix admin database name] ' . $e->getMessage());
        return '';
    }
}

function admin_database_tables(PDO $pdo): array
{
    try {
        $stmt = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
        $tables = [];
        foreach ($stmt->fetchAll(PDO::FETCH_NUM) as $row) {
            $name = (string) ($row[0] ?? '');
            if ($name !== '' && preg_match('/^[A-Za-z0-9_]+$/', $name)) {
                $tables[] = $name;
            }
        }
        sort($tables, SORT_NATURAL | SORT_FLAG_CASE);
        return $tables;
    } catch (Throwable $e) {
        error_log('[Phonix admin database tables] ' . $e->getMessage());
        return [];
    }
}

function admin_database_size_bytes(PDO $pdo): int
{
    try {
        $stmt = $pdo->query('SELECT COALESCE(SUM(data_length + index_length), 0) FROM information_schema.TABLES WHERE table_schema = DATABASE()');
        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('[Phonix admin database size] ' . $e->getMessage());
        return 0;
    }
}

function admin_last_maintenance_run(PDO $pdo, ?string $action = null): ?array
{
    try {
        if ($action) {
            $stmt = $pdo->prepare('SELECT * FROM admin_maintenance_runs WHERE action = :action ORDER BY created_at DESC, id DESC LIMIT 1');
            $stmt->execute(['action' => $action]);
        } else {
            $stmt = $pdo->query('SELECT * FROM admin_maintenance_runs ORDER BY created_at DESC, id DESC LIMIT 1');
        }
        $row = $stmt->fetch();
        return $row ?: null;
    } catch (Throwable $e) {
        error_log('[Phonix admin maintenance last run] ' . $e->getMessage());
        return null;
    }
}

function admin_recent_maintenance_runs(PDO $pdo, int $limit = 10): array
{
    $limit = max(1, min(50, $limit));
    return admin_rows($pdo, 'SELECT * FROM admin_maintenance_runs ORDER BY created_at DESC, id DESC LIMIT ' . $limit);
}

function admin_log_maintenance_run(PDO $pdo, string $action, int $affectedRows = 0, ?string $details = null): void
{
    try {
        $stmt = $pdo->prepare('INSERT INTO admin_maintenance_runs (admin_email, action, affected_rows, details) VALUES (:admin_email, :action, :affected_rows, :details)');
        $stmt->execute([
            'admin_email' => admin_current_email(),
            'action' => mb_substr($action, 0, 120),
            'affected_rows' => max(0, $affectedRows),
            'details' => $details ? mb_substr($details, 0, 2000) : null,
        ]);
    } catch (Throwable $e) {
        error_log('[Phonix admin maintenance log] ' . $e->getMessage());
    }
}

function admin_maintenance_candidates(PDO $pdo): array
{
    return [
        'sent_email_90' => [
            'title' => 'Sent/skipped emails older than 90 days',
            'count' => (int) admin_scalar($pdo, "SELECT COUNT(*) FROM email_outbox WHERE status IN ('sent','skipped') AND created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)", [], 0),
            'action' => 'cleanup_sent_email_90',
            'desc' => 'Keeps current queue and failed emails intact while trimming old delivery history.',
        ],
        'failed_email_180' => [
            'title' => 'Failed emails older than 180 days',
            'count' => (int) admin_scalar($pdo, "SELECT COUNT(*) FROM email_outbox WHERE status = 'failed' AND created_at < DATE_SUB(NOW(), INTERVAL 180 DAY)", [], 0),
            'action' => 'cleanup_failed_email_180',
            'desc' => 'Removes very old failed messages after they are no longer useful for debugging.',
        ],
        'activity_180' => [
            'title' => 'Admin activity logs older than 180 days',
            'count' => (int) admin_scalar($pdo, 'SELECT COUNT(*) FROM admin_activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 180 DAY)', [], 0),
            'action' => 'cleanup_activity_180',
            'desc' => 'Preserves recent audit history and trims old operational noise.',
        ],
        'dismissals_180' => [
            'title' => 'Dismissed notification records older than 180 days',
            'count' => (int) admin_scalar($pdo, 'SELECT COUNT(*) FROM admin_notification_dismissals WHERE dismissed_at < DATE_SUB(NOW(), INTERVAL 180 DAY)', [], 0),
            'action' => 'cleanup_dismissals_180',
            'desc' => 'Lets stale dismissed alerts resurface if the underlying condition still matters.',
        ],
        'maintenance_365' => [
            'title' => 'Maintenance run records older than 365 days',
            'count' => (int) admin_scalar($pdo, 'SELECT COUNT(*) FROM admin_maintenance_runs WHERE created_at < DATE_SUB(NOW(), INTERVAL 365 DAY)', [], 0),
            'action' => 'cleanup_maintenance_365',
            'desc' => 'Keeps one year of maintenance history available in the panel.',
        ],
    ];
}

function admin_run_maintenance_action(PDO $pdo, string $action): array
{
    $definitions = [
        'cleanup_sent_email_90' => [
            'sql' => "DELETE FROM email_outbox WHERE status IN ('sent','skipped') AND created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)",
            'label' => 'Cleaned sent/skipped emails older than 90 days',
        ],
        'cleanup_failed_email_180' => [
            'sql' => "DELETE FROM email_outbox WHERE status = 'failed' AND created_at < DATE_SUB(NOW(), INTERVAL 180 DAY)",
            'label' => 'Cleaned failed emails older than 180 days',
        ],
        'cleanup_activity_180' => [
            'sql' => 'DELETE FROM admin_activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 180 DAY)',
            'label' => 'Cleaned admin activity logs older than 180 days',
        ],
        'cleanup_dismissals_180' => [
            'sql' => 'DELETE FROM admin_notification_dismissals WHERE dismissed_at < DATE_SUB(NOW(), INTERVAL 180 DAY)',
            'label' => 'Cleaned dismissed notification records older than 180 days',
        ],
        'cleanup_maintenance_365' => [
            'sql' => 'DELETE FROM admin_maintenance_runs WHERE created_at < DATE_SUB(NOW(), INTERVAL 365 DAY)',
            'label' => 'Cleaned maintenance run records older than 365 days',
        ],
    ];
    if (!isset($definitions[$action])) {
        throw new RuntimeException('Unknown maintenance action.');
    }
    $stmt = $pdo->prepare($definitions[$action]['sql']);
    $stmt->execute();
    $affected = $stmt->rowCount();
    admin_log_maintenance_run($pdo, $action, $affected, $definitions[$action]['label']);
    admin_log_activity($pdo, 'maintenance_action_run', 'maintenance', null, $definitions[$action]['label'] . ' · ' . $affected . ' row(s)');
    return ['affected' => $affected, 'label' => $definitions[$action]['label']];
}

function admin_sql_dump_quote_identifier(string $identifier): string
{
    return '`' . str_replace('`', '``', $identifier) . '`';
}

function admin_sql_dump_value($value): string
{
    if ($value === null) {
        return 'NULL';
    }
    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }
    return "'" . str_replace(["\\", "'", "\0"], ["\\\\", "\\'", ""], (string) $value) . "'";
}

function admin_stream_database_backup(PDO $pdo): void
{
    $dbName = admin_database_name($pdo);
    $tables = admin_database_tables($pdo);
    $filename = 'phonix-db-backup-' . date('Ymd-His') . '.sql';

    if (!headers_sent()) {
        header('Content-Type: application/sql; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    }

    echo "-- Phonix database backup\n";
    echo "-- Database: " . $dbName . "\n";
    echo "-- Generated at: " . date('c') . "\n\n";
    echo "SET NAMES utf8mb4;\n";
    echo "SET FOREIGN_KEY_CHECKS=0;\n\n";

    foreach ($tables as $table) {
        $quotedTable = admin_sql_dump_quote_identifier($table);
        echo "-- --------------------------------------------------------\n";
        echo "-- Table structure for {$quotedTable}\n\n";
        echo "DROP TABLE IF EXISTS {$quotedTable};\n";
        $createStmt = $pdo->query('SHOW CREATE TABLE ' . $quotedTable);
        $create = $createStmt ? $createStmt->fetch(PDO::FETCH_ASSOC) : null;
        $createSql = $create['Create Table'] ?? '';
        if ($createSql !== '') {
            echo $createSql . ";\n\n";
        }

        echo "-- Data for {$quotedTable}\n";
        $rows = $pdo->query('SELECT * FROM ' . $quotedTable, PDO::FETCH_ASSOC);
        if ($rows) {
            foreach ($rows as $row) {
                $columns = array_map('admin_sql_dump_quote_identifier', array_keys($row));
                $values = array_map('admin_sql_dump_value', array_values($row));
                echo 'INSERT INTO ' . $quotedTable . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ");\n";
            }
        }
        echo "\n";
        if (function_exists('flush')) {
            @flush();
        }
    }

    echo "SET FOREIGN_KEY_CHECKS=1;\n";
}

function admin_table_has_column(PDO $pdo, string $table, string $column): bool
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column');
        $stmt->execute(['table' => $table, 'column' => $column]);
        $cache[$key] = ((int) $stmt->fetchColumn()) > 0;
        return $cache[$key];
    } catch (Throwable $e) {
        error_log('[Phonix admin column check] ' . $e->getMessage());
        return false;
    }
}


function admin_ensure_product_catalog_columns(PDO $pdo): void
{
    $columns = [
        'product_type' => "ALTER TABLE products ADD COLUMN product_type VARCHAR(60) NOT NULL DEFAULT 'general' AFTER badge",
        'gallery_json' => "ALTER TABLE products ADD COLUMN gallery_json LONGTEXT NULL AFTER image",
        'product_status' => "ALTER TABLE products ADD COLUMN product_status VARCHAR(40) NOT NULL DEFAULT 'active' AFTER gallery_json",
        'specs_json' => "ALTER TABLE products ADD COLUMN specs_json LONGTEXT NULL AFTER description",
        'benefits_json' => "ALTER TABLE products ADD COLUMN benefits_json LONGTEXT NULL AFTER specs_json",
    ];
    foreach ($columns as $column => $sql) {
        if (!admin_table_has_column($pdo, 'products', $column)) {
            $pdo->exec($sql);
        }
    }
    admin_ensure_product_variants_table($pdo);
    $pdo->exec("UPDATE products SET product_status = CASE WHEN is_active = 0 THEN 'archived' WHEN stock <= 0 THEN 'out_of_stock' ELSE 'active' END WHERE product_status IS NULL OR product_status = ''");
}

function admin_ensure_product_variants_table(PDO $pdo): void
{
    if (admin_table_exists($pdo, 'product_variants')) {
        return;
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS product_variants (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        product_id BIGINT UNSIGNED NOT NULL,
        option_type VARCHAR(100) NOT NULL,
        option_value VARCHAR(190) NOT NULL,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_product_variants_product (product_id),
        CONSTRAINT fk_product_variants_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function admin_product_variant_rows(PDO $pdo, int $productId): array
{
    if ($productId <= 0) {
        return [];
    }
    admin_ensure_product_variants_table($pdo);
    $stmt = $pdo->prepare('SELECT id, option_type, option_value FROM product_variants WHERE product_id = :product_id ORDER BY id ASC');
    $stmt->execute(['product_id' => $productId]);
    return $stmt->fetchAll() ?: [];
}

function admin_product_variant_groups(PDO $pdo, int $productId): array
{
    $groups = [];
    foreach (admin_product_variant_rows($pdo, $productId) as $row) {
        $type = trim((string) ($row['option_type'] ?? ''));
        $value = trim((string) ($row['option_value'] ?? ''));
        if ($type === '' || $value === '') {
            continue;
        }
        $groups[$type][] = $value;
    }
    return $groups;
}

function admin_product_variants_from_post(string $typeKey = 'variant_types', string $valueKey = 'variant_values', string $bulkKey = 'variants_text'): array
{
    $rows = [];
    $seen = [];

    $types = $_POST[$typeKey] ?? [];
    $values = $_POST[$valueKey] ?? [];
    if (is_array($types) && is_array($values)) {
        $count = max(count($types), count($values));
        for ($i = 0; $i < $count; $i++) {
            $type = mb_substr(trim((string) ($types[$i] ?? '')), 0, 100);
            $value = mb_substr(trim((string) ($values[$i] ?? '')), 0, 190);
            if ($type === '' || $value === '') {
                continue;
            }
            $key = mb_strtolower($type . "|" . $value);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $rows[] = ['option_type' => $type, 'option_value' => $value];
        }
    }

    $raw = str_replace(["
", ','], ["", "
"], trim((string) ($_POST[$bulkKey] ?? '')));
    if ($raw !== '') {
        foreach (preg_split('/\R/u', $raw) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = preg_split('/\s*(?:\||:|=)\s*/u', $line, 2);
            if (!$parts || count($parts) < 2) {
                continue;
            }
            $type = mb_substr(trim((string) $parts[0]), 0, 100);
            $value = mb_substr(trim((string) $parts[1]), 0, 190);
            if ($type === '' || $value === '') {
                continue;
            }
            $key = mb_strtolower($type . "|" . $value);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $rows[] = ['option_type' => $type, 'option_value' => $value];
        }
    }

    return $rows;
}

function admin_replace_product_variants(PDO $pdo, int $productId, array $rows): void
{
    if ($productId <= 0) {
        return;
    }
    admin_ensure_product_variants_table($pdo);
    $pdo->prepare('DELETE FROM product_variants WHERE product_id = :product_id')->execute(['product_id' => $productId]);
    if (!$rows) {
        return;
    }
    $stmt = $pdo->prepare('INSERT INTO product_variants (product_id, option_type, option_value) VALUES (:product_id, :option_type, :option_value)');
    foreach ($rows as $row) {
        $type = trim((string) ($row['option_type'] ?? ''));
        $value = trim((string) ($row['option_value'] ?? ''));
        if ($type === '' || $value === '') {
            continue;
        }
        $stmt->execute([
            'product_id' => $productId,
            'option_type' => mb_substr($type, 0, 100),
            'option_value' => mb_substr($value, 0, 190),
        ]);
    }
}

function admin_product_status_options(): array
{
    return [
        'active' => 'Active',
        'draft' => 'Draft',
        'out_of_stock' => 'Out of stock',
        'archived' => 'Archived',
    ];
}

function admin_product_status_from_post(string $key = 'product_status'): string
{
    $status = preg_replace('/[^a-z_]+/', '', strtolower((string) ($_POST[$key] ?? 'active'))) ?: 'active';
    return array_key_exists($status, admin_product_status_options()) ? $status : 'active';
}

function admin_product_is_live_status(string $status): int
{
    return in_array($status, ['active', 'out_of_stock'], true) ? 1 : 0;
}

function admin_product_status_value($status, int $isActive, int $stock): string
{
    $status = (string) ($status ?: '');
    if (array_key_exists($status, admin_product_status_options())) {
        return $status;
    }
    if ($isActive !== 1) {
        return 'archived';
    }
    return $stock <= 0 ? 'out_of_stock' : 'active';
}

function admin_product_status_label(string $status): string
{
    return admin_product_status_options()[$status] ?? 'Active';
}

function admin_product_status_class(string $status): string
{
    return match ($status) {
        'active' => 'good',
        'draft' => 'info',
        'out_of_stock' => 'warning',
        'archived' => 'danger',
        default => 'neutral',
    };
}

function admin_product_type_options(): array
{
    return [
        'general' => 'General product',
        'smartphone' => 'Smartphone',
        'laptop' => 'Laptop / Computer',
        'tablet' => 'Tablet',
        'audio' => 'Audio',
        'wearable' => 'Wearable',
        'accessory' => 'Accessory',
    ];
}

function admin_product_type_from_post(string $key = 'product_type'): string
{
    $type = preg_replace('/[^a-z0-9_]+/', '', strtolower((string) ($_POST[$key] ?? 'general'))) ?: 'general';
    return array_key_exists($type, admin_product_type_options()) ? $type : 'general';
}

function admin_product_type_label(?string $type): string
{
    $type = strtolower((string) ($type ?: 'general'));
    return admin_product_type_options()[$type] ?? 'General product';
}

function admin_product_spec_templates(): array
{
    return [
        'general' => [
            'Material', 'Dimensions', 'Weight', 'Color', 'Warranty', 'Compatibility',
        ],
        'smartphone' => [
            'Display', 'Chipset', 'RAM', 'Storage', 'Rear camera', 'Front camera', 'Battery', 'Charging', 'Operating system', 'Connectivity',
        ],
        'laptop' => [
            'Processor', 'Graphics', 'RAM', 'Storage', 'Display', 'Battery', 'Ports', 'Operating system', 'Weight', 'Keyboard',
        ],
        'tablet' => [
            'Display', 'Processor', 'RAM', 'Storage', 'Battery', 'Camera', 'Pen support', 'Connectivity', 'Operating system', 'Weight',
        ],
        'audio' => [
            'Driver', 'Noise cancellation', 'Battery life', 'Charging case', 'Microphones', 'Bluetooth', 'Water resistance', 'Codec support', 'Weight',
        ],
        'wearable' => [
            'Display', 'Case size', 'Battery life', 'Sensors', 'Water resistance', 'Connectivity', 'Compatibility', 'Strap', 'Weight',
        ],
        'accessory' => [
            'Compatibility', 'Material', 'Power output', 'Cable length', 'Connector', 'Color', 'Warranty', 'Dimensions', 'Weight',
        ],
    ];
}

function admin_product_spec_template_for(string $type): array
{
    $templates = admin_product_spec_templates();
    return $templates[$type] ?? $templates['general'];
}

function admin_product_specs_map($value): array
{
    $map = [];
    foreach (admin_product_specs_list($value) as $spec) {
        $name = trim((string) ($spec['name'] ?? ''));
        if ($name !== '') {
            $map[mb_strtolower($name)] = (string) ($spec['value'] ?? '');
        }
    }
    return $map;
}

function admin_product_spec_existing_value(array $specMap, string $label): string
{
    return (string) ($specMap[mb_strtolower($label)] ?? '');
}

function admin_product_specs_from_editor_post(string $productType = 'general', string $textKey = 'specs_text', string $typedKey = 'typed_specs'): ?string
{
    $specs = [];
    $seen = [];

    $names = $_POST['spec_names'] ?? [];
    $values = $_POST['spec_values'] ?? [];
    if (is_array($names) && is_array($values)) {
        $count = max(count($names), count($values));
        for ($i = 0; $i < $count; $i++) {
            $name = trim((string) ($names[$i] ?? ''));
            $value = trim((string) ($values[$i] ?? ''));
            if ($name === '' || $value === '') {
                continue;
            }
            $key = mb_strtolower($name);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $specs[] = ['name' => mb_substr($name, 0, 120), 'value' => mb_substr($value, 0, 500)];
        }
    }

    $typedRoot = $_POST[$typedKey] ?? [];
    $typed = is_array($typedRoot) ? ($typedRoot[$productType] ?? []) : [];
    if (is_array($typed)) {
        foreach ($typed as $name => $value) {
            $name = trim((string) $name);
            $value = trim((string) $value);
            if ($name === '' || $value === '') {
                continue;
            }
            $key = mb_strtolower($name);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $specs[] = ['name' => mb_substr($name, 0, 120), 'value' => mb_substr($value, 0, 500)];
        }
    }

    $raw = trim((string) ($_POST[$textKey] ?? ''));
    if ($raw !== '') {
        $parsedJson = false;
        $first = substr($raw, 0, 1);
        if ($first === '[' || $first === '{') {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $parsedJson = true;
                $rows = array_is_list($decoded) ? $decoded : array_map(static fn($key, $val) => ['name' => $key, 'value' => $val], array_keys($decoded), $decoded);
                foreach ($rows as $item) {
                    if (!is_array($item)) {
                        continue;
                    }
                    $name = trim((string) ($item['name'] ?? $item['label'] ?? $item['key'] ?? ''));
                    $value = trim(is_scalar($item['value'] ?? null) ? (string) ($item['value'] ?? '') : json_encode($item['value'] ?? '', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                    if ($name === '' || $value === '') {
                        continue;
                    }
                    $key = mb_strtolower($name);
                    if (isset($seen[$key])) {
                        continue;
                    }
                    $seen[$key] = true;
                    $specs[] = ['name' => mb_substr($name, 0, 120), 'value' => mb_substr($value, 0, 500)];
                }
            }
        }
        if (!$parsedJson) {
            foreach (preg_split('/\R/u', $raw) ?: [] as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                $parts = preg_split('/\s*(?:\||:|=)\s*/u', $line, 2);
                if (!$parts || count($parts) < 2) {
                    continue;
                }
                $name = trim((string) $parts[0]);
                $value = trim((string) $parts[1]);
                if ($name === '' || $value === '') {
                    continue;
                }
                $key = mb_strtolower($name);
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $specs[] = ['name' => mb_substr($name, 0, 120), 'value' => mb_substr($value, 0, 500)];
            }
        }
    }

    return $specs ? json_encode($specs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;
}

function admin_product_gallery_list($value): array
{
    if (!$value) {
        return [];
    }
    $decoded = json_decode((string) $value, true);
    if (!is_array($decoded)) {
        return [];
    }
    $paths = [];
    foreach ($decoded as $item) {
        $path = trim((string) $item);
        if ($path !== '' && mb_strlen($path) <= 500 && !in_array($path, $paths, true)) {
            $paths[] = $path;
        }
    }
    return $paths;
}

function admin_product_gallery_from_post(PDO $pdo, string $textKey = 'gallery_paths', string $mediaKey = 'gallery_media_ids'): array
{
    $paths = [];
    $raw = str_replace([",", "\r"], ["\n", ""], (string) ($_POST[$textKey] ?? ''));
    foreach (explode("\n", $raw) as $line) {
        $path = trim($line);
        if ($path !== '' && mb_strlen($path) <= 500 && !in_array($path, $paths, true)) {
            $paths[] = $path;
        }
    }
    $ids = $_POST[$mediaKey] ?? [];
    if (!is_array($ids)) {
        $ids = [];
    }
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn($id) => $id > 0)));
    if ($ids) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare('SELECT id, public_path FROM media_assets WHERE id IN (' . $placeholders . ')');
        $stmt->execute($ids);
        $found = [];
        foreach ($stmt->fetchAll() as $row) {
            $found[(int) $row['id']] = (string) $row['public_path'];
        }
        foreach ($ids as $id) {
            $path = trim((string) ($found[$id] ?? ''));
            if ($path !== '' && !in_array($path, $paths, true)) {
                $paths[] = $path;
            }
        }
    }
    return $paths;
}

function admin_product_specs_from_post(string $key = 'specs_text'): ?string
{
    $raw = trim((string) ($_POST[$key] ?? ''));
    if ($raw === '') {
        return null;
    }
    $specs = [];
    $first = substr($raw, 0, 1);
    if ($first === '[' || $first === '{') {
        $decoded = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            if (array_is_list($decoded)) {
                foreach ($decoded as $item) {
                    if (!is_array($item)) {
                        continue;
                    }
                    $name = trim((string) ($item['name'] ?? $item['label'] ?? $item['key'] ?? ''));
                    $value = trim((string) ($item['value'] ?? $item['text'] ?? ''));
                    if ($name !== '' && $value !== '') {
                        $specs[] = ['name' => mb_substr($name, 0, 120), 'value' => mb_substr($value, 0, 500)];
                    }
                }
            } else {
                foreach ($decoded as $name => $value) {
                    $name = trim((string) $name);
                    $value = trim(is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                    if ($name !== '' && $value !== '') {
                        $specs[] = ['name' => mb_substr($name, 0, 120), 'value' => mb_substr($value, 0, 500)];
                    }
                }
            }
        }
    }
    if (!$specs) {
        foreach (preg_split('/\R/u', $raw) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = preg_split('/\s*(?:\||:|=)\s*/u', $line, 2);
            if (!$parts || count($parts) < 2) {
                continue;
            }
            $name = trim((string) $parts[0]);
            $value = trim((string) $parts[1]);
            if ($name !== '' && $value !== '') {
                $specs[] = ['name' => mb_substr($name, 0, 120), 'value' => mb_substr($value, 0, 500)];
            }
        }
    }
    return $specs ? json_encode($specs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;
}

function admin_product_specs_list($value): array
{
    if (!$value) {
        return [];
    }
    $decoded = json_decode((string) $value, true);
    if (!is_array($decoded)) {
        return [];
    }
    $specs = [];
    foreach ($decoded as $item) {
        if (!is_array($item)) {
            continue;
        }
        $name = trim((string) ($item['name'] ?? ''));
        $val = trim((string) ($item['value'] ?? ''));
        if ($name !== '' && $val !== '') {
            $specs[] = ['name' => $name, 'value' => $val];
        }
    }
    return $specs;
}

function admin_product_specs_text($value): string
{
    $lines = [];
    foreach (admin_product_specs_list($value) as $spec) {
        $lines[] = $spec['name'] . ': ' . $spec['value'];
    }
    return implode("\n", $lines);
}

function admin_product_benefits_list($value): array
{
    if (!$value) {
        return [];
    }
    $decoded = json_decode((string) $value, true);
    if (!is_array($decoded)) {
        return [];
    }
    $benefits = [];
    foreach ($decoded as $item) {
        $text = trim(is_array($item) ? (string) ($item['text'] ?? $item['benefit'] ?? '') : (string) $item);
        if ($text !== '' && !in_array($text, $benefits, true)) {
            $benefits[] = mb_substr($text, 0, 220);
        }
    }
    return $benefits;
}

function admin_product_benefits_from_post(string $rowKey = 'benefits', string $bulkKey = 'benefits_text'): ?string
{
    $benefits = [];
    $seen = [];
    $rows = $_POST[$rowKey] ?? [];
    if (is_array($rows)) {
        foreach ($rows as $row) {
            $text = trim((string) $row);
            if ($text === '') {
                continue;
            }
            $text = preg_replace('/^[-•*\\s]+/u', '', $text) ?: $text;
            $key = mb_strtolower($text);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $benefits[] = mb_substr($text, 0, 220);
        }
    }

    $raw = trim((string) ($_POST[$bulkKey] ?? ''));
    if ($raw !== '') {
        foreach (preg_split('/\\R/u', $raw) ?: [] as $line) {
            $text = trim((string) $line);
            $text = preg_replace('/^[-•*\\s]+/u', '', $text) ?: $text;
            if ($text === '') {
                continue;
            }
            $key = mb_strtolower($text);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $benefits[] = mb_substr($text, 0, 220);
        }
    }

    return $benefits ? json_encode($benefits, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;
}


function admin_product_readiness(array $product): array
{
    $status = admin_product_status_value($product['product_status'] ?? null, (int) ($product['is_active'] ?? 0), (int) ($product['stock'] ?? 0));
    $price = (float) ($product['price'] ?? 0);
    $stock = (int) ($product['stock'] ?? 0);
    $specs = admin_product_specs_list($product['specs_json'] ?? null);
    $benefits = admin_product_benefits_list($product['benefits_json'] ?? null);
    $benefitsFilled = (bool) array_filter($benefits, static fn($benefit) => trim((string) $benefit) !== '');
    $specsFilled = (bool) array_filter($specs, static function ($spec): bool {
        return trim((string) ($spec['name'] ?? '')) !== '' && trim((string) ($spec['value'] ?? '')) !== '';
    });

    $items = [
        'name' => ['label' => 'Product name', 'ok' => trim((string) ($product['name'] ?? '')) !== '', 'issue' => 'Missing product name'],
        'price' => ['label' => 'Valid price', 'ok' => $price > 0, 'issue' => 'Missing or invalid price'],
        'image' => ['label' => 'Main image', 'ok' => trim((string) ($product['image'] ?? '')) !== '', 'issue' => 'Missing main image'],
        'description' => ['label' => 'Short description', 'ok' => trim((string) ($product['short_description'] ?? '')) !== '', 'issue' => 'Missing short description'],
        'category' => ['label' => 'Category', 'ok' => (int) ($product['category_id'] ?? 0) > 0, 'issue' => 'No category assigned'],
        'specs' => ['label' => 'Visible specs', 'ok' => $specsFilled, 'issue' => 'No visible specifications'],
        'benefits' => ['label' => 'Key benefits', 'ok' => $benefitsFilled, 'issue' => 'No key benefits'],
        'storefront' => ['label' => 'Storefront state', 'ok' => in_array($status, ['active', 'out_of_stock'], true), 'issue' => 'Not visible on storefront'],
    ];

    if ($status === 'active' && $stock <= 0) {
        $items['stock_logic'] = ['label' => 'Stock/status consistency', 'ok' => false, 'issue' => 'Active but stock is 0'];
    } elseif ($status === 'out_of_stock' && $stock > 0) {
        $items['stock_logic'] = ['label' => 'Stock/status consistency', 'ok' => false, 'issue' => 'Out of stock but stock is above 0'];
    } else {
        $items['stock_logic'] = ['label' => 'Stock/status consistency', 'ok' => true, 'issue' => 'Stock/status mismatch'];
    }

    $issues = [];
    $okCount = 0;
    foreach ($items as $key => $item) {
        if (!empty($item['ok'])) {
            $okCount++;
        } else {
            $issues[] = ['key' => $key, 'label' => $item['issue']];
        }
    }
    $total = count($items);
    $score = $total > 0 ? (int) round(($okCount / $total) * 100) : 0;
    $state = $okCount === $total ? 'Ready' : ($okCount >= max(1, $total - 2) ? 'Needs review' : 'Incomplete');
    $class = $okCount === $total ? 'good' : ($okCount >= max(1, $total - 2) ? 'warning' : 'danger');

    return [
        'items' => $items,
        'issues' => $issues,
        'count' => $okCount,
        'total' => $total,
        'score' => $score,
        'state' => $state,
        'class' => $class,
    ];
}

function admin_product_readiness_sql_condition(string $alias = ''): string
{
    $prefix = $alias !== '' ? rtrim($alias, '.') . '.' : '';
    return "({$prefix}name IS NULL OR {$prefix}name = '' OR {$prefix}price <= 0 OR {$prefix}image IS NULL OR {$prefix}image = '' OR {$prefix}short_description IS NULL OR {$prefix}short_description = '' OR {$prefix}category_id IS NULL OR {$prefix}specs_json IS NULL OR {$prefix}specs_json = '' OR ({$prefix}product_status = 'active' AND {$prefix}stock <= 0) OR ({$prefix}product_status = 'out_of_stock' AND {$prefix}stock > 0))";
}

function admin_product_performance_label(int $ordersCount, int $qtySold, int $stock): array
{
    if ($ordersCount <= 0) {
        return ['No sales yet', 'neutral'];
    }
    if ($stock > 0 && $stock <= 5 && $qtySold >= 3) {
        return ['Low stock but selling', 'warning'];
    }
    if ($qtySold >= 10) {
        return ['Bestseller', 'good'];
    }
    return ['Selling', 'info'];
}

function admin_product_action_plan(array $product, ?array $readiness = null): array
{
    $readiness = $readiness ?: admin_product_readiness($product);
    $stock = (int) ($product['stock'] ?? 0);
    $status = admin_product_status_value($product['product_status'] ?? null, (int) ($product['is_active'] ?? 0), $stock);
    $ordersCount = (int) ($product['orders_count'] ?? 0);
    $qtySold = (int) ($product['qty_sold'] ?? 0);
    $updatedAt = trim((string) ($product['updated_at'] ?? ''));

    foreach ($readiness['issues'] ?? [] as $issue) {
        $key = (string) ($issue['key'] ?? '');
        if ($key === 'image') {
            return ['priority' => 'high', 'class' => 'danger', 'title' => 'Add a main image', 'detail' => 'This published product is visible without a customer-facing image.', 'action' => 'Open editor media section'];
        }
        if ($key === 'price') {
            return ['priority' => 'high', 'class' => 'danger', 'title' => 'Fix the selling price', 'detail' => 'Published products need a valid price before customers can trust the listing.', 'action' => 'Open quick price'];
        }
        if ($key === 'stock_logic') {
            return ['priority' => 'high', 'class' => 'warning', 'title' => 'Sync stock and status', 'detail' => 'The visible status does not match the current stock level.', 'action' => 'Run Fix stock/status'];
        }
        if ($key === 'description') {
            return ['priority' => 'medium', 'class' => 'warning', 'title' => 'Write a short description', 'detail' => 'The product card needs a concise explanation before it feels complete.', 'action' => 'Open full editor'];
        }
        if ($key === 'category') {
            return ['priority' => 'medium', 'class' => 'warning', 'title' => 'Assign a category', 'detail' => 'Category improves navigation, filtering, and storefront consistency.', 'action' => 'Open full editor'];
        }
        if ($key === 'specs') {
            return ['priority' => 'medium', 'class' => 'warning', 'title' => 'Add visible specs', 'detail' => 'Specifications make the product page clearer and more comparable.', 'action' => 'Open full editor'];
        }
    }

    if ($stock > 0 && $stock <= 5 && $qtySold >= 3) {
        return ['priority' => 'medium', 'class' => 'warning', 'title' => 'Restock soon', 'detail' => 'This product is selling and stock is close to zero.', 'action' => 'Open quick stock'];
    }
    if ($ordersCount <= 0) {
        return ['priority' => 'low', 'class' => 'neutral', 'title' => 'Review merchandising', 'detail' => 'Published product has no recorded sales yet.', 'action' => 'Check price, image, and placement'];
    }
    if ($updatedAt !== '' && strtotime($updatedAt) !== false && strtotime($updatedAt) < strtotime('-30 days')) {
        return ['priority' => 'low', 'class' => 'neutral', 'title' => 'Refresh product details', 'detail' => 'This published listing has not been updated for 30+ days.', 'action' => 'Review content and pricing'];
    }
    if ($status === 'active') {
        return ['priority' => 'ok', 'class' => 'good', 'title' => 'Healthy listing', 'detail' => 'No urgent operational action is required.', 'action' => 'Monitor performance'];
    }

    return ['priority' => 'low', 'class' => 'neutral', 'title' => 'Monitor listing', 'detail' => 'Keep an eye on this product while it remains visible.', 'action' => 'Review later'];
}
