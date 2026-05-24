<?php

function fetch_site_settings(PDO $pdo): array
{
    store_ensure_default_settings($pdo);
    $stmt = $pdo->query('SELECT setting_key, setting_value FROM site_settings');
    $settings = [];
    foreach ($stmt as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    return $settings;
}

function store_default_settings(): array
{
    return [
        'site_name' => 'Phonix Türkiye',
        'site_currency' => 'TRY',
        'support_email' => 'support@phonixturkiye.com',
        'support_phone' => '0850 305 20 20',
        'support_chat_label' => 'Weekdays 09:00 - 18:00 (TRT)',
        'footer_tagline' => 'Official phones, trusted warranty, fast delivery across Turkey.',
        'announcement_text' => '',
        'shipping_info_text' => 'Shipping and payment choices are saved directly into each phone order.',
        'maintenance_mode' => '0',
        'maintenance_title' => 'Store maintenance in progress',
        'maintenance_message' => 'We are upgrading the phone marketplace and will be back shortly.',
        'email_notifications_enabled' => '0',
        'email_from_name' => 'Phonix Türkiye',
        'email_from_email' => 'no-reply@phonixturkiye.com',
        'email_admin_alert_email' => 'support@phonixturkiye.com',
        'email_delivery_enabled' => '0',
        'email_delivery_batch_size' => '10',
        'email_cron_token' => '',
        'email_last_run_at' => '',
    ];
}

function store_ensure_default_settings(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    try {
        $stmt = $pdo->prepare('INSERT INTO site_settings (setting_key, setting_value) VALUES (:k, :v) ON DUPLICATE KEY UPDATE setting_key = setting_key');
        foreach (store_default_settings() as $key => $value) {
            $stmt->execute(['k' => $key, 'v' => $value]);
        }
    } catch (Throwable $e) {
        error_log('[Phonix settings defaults] ' . $e->getMessage());
    }
    $done = true;
}

function store_setting(array $settings, string $key, string $default = ''): string
{
    $value = trim((string) ($settings[$key] ?? ''));
    return $value !== '' ? $value : $default;
}

function store_setting_bool(array $settings, string $key, bool $default = false): bool
{
    $value = $settings[$key] ?? null;
    if ($value === null || $value === '') {
        return $default;
    }
    return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
}

function store_email_default_templates(): array
{
    return [
        'order_confirmation' => [
            'name' => 'Order confirmation',
            'subject' => 'Your {{site_name}} order {{order_number}} is confirmed',
            'body' => "Hi {{customer_name}},\n\nThanks for your order {{order_number}}.\n\nOrder total: {{order_total}}\nShipping: {{shipping_method}}\nPayment: {{payment_method}}\n\nWe will update you when the order status changes.\n\n{{site_name}}",
            'description' => 'Queued for the customer after checkout creates an order.',
        ],
        'admin_new_order' => [
            'name' => 'Admin new order alert',
            'subject' => 'New order {{order_number}} — {{order_total}}',
            'body' => "A new order was placed.\n\nOrder: {{order_number}}\nCustomer: {{customer_name}} <{{customer_email}}>\nTotal: {{order_total}}\nShipping: {{shipping_method}}\nPayment: {{payment_method}}\n\nOpen the admin Orders section to process it.",
            'description' => 'Queued for the admin alert email after a new checkout order.',
        ],
        'support_confirmation' => [
            'name' => 'Support request confirmation',
            'subject' => 'We received your support request',
            'body' => "Hi {{customer_name}},\n\nWe received your message: {{support_subject}}.\n\nReference: #{{support_id}}\n\nOur team will review it from the support inbox.\n\n{{site_name}}",
            'description' => 'Queued for customers after they submit the support form.',
        ],
        'admin_new_support' => [
            'name' => 'Admin new support alert',
            'subject' => 'New support message #{{support_id}} — {{support_subject}}',
            'body' => "A new support message was submitted.\n\nFrom: {{customer_name}} <{{customer_email}}>\nOrder: {{order_number}}\nSubject: {{support_subject}}\n\n{{support_excerpt}}\n\nOpen the admin Support section to respond.",
            'description' => 'Queued for the admin alert email after a support form submission.',
        ],
        'order_status_update' => [
            'name' => 'Order status update',
            'subject' => 'Order {{order_number}} is now {{order_status}}',
            'body' => "Hi {{customer_name}},\n\nYour order {{order_number}} status is now: {{order_status}}.\nPayment status: {{payment_status}}\nTracking: {{tracking_number}}\n\n{{site_name}}",
            'description' => 'Queued for customers when admins change order status, payment status, or tracking details.',
        ],
    ];
}

function store_ensure_email_tables(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS email_templates (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, template_key VARCHAR(120) NOT NULL, name VARCHAR(190) NOT NULL, subject VARCHAR(255) NOT NULL, body LONGTEXT NOT NULL, description VARCHAR(255) NULL, is_active TINYINT(1) NOT NULL DEFAULT 1, created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (id), UNIQUE KEY uq_email_templates_key (template_key), KEY idx_email_templates_active (is_active)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS email_outbox (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, template_key VARCHAR(120) NULL, recipient_email VARCHAR(190) NOT NULL, recipient_name VARCHAR(190) NULL, subject VARCHAR(255) NOT NULL, body LONGTEXT NOT NULL, status VARCHAR(40) NOT NULL DEFAULT 'queued', related_type VARCHAR(80) NULL, related_id BIGINT UNSIGNED NULL, error_message TEXT NULL, attempts INT UNSIGNED NOT NULL DEFAULT 0, last_attempt_at DATETIME NULL, queued_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, sent_at DATETIME NULL, updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (id), KEY idx_email_outbox_status (status), KEY idx_email_outbox_queued (queued_at), KEY idx_email_outbox_related (related_type, related_id), KEY idx_email_outbox_recipient (recipient_email), KEY idx_email_outbox_attempts (attempts, last_attempt_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $outboxColumns = [
        'attempts' => 'ALTER TABLE email_outbox ADD COLUMN attempts INT UNSIGNED NOT NULL DEFAULT 0 AFTER error_message',
        'last_attempt_at' => 'ALTER TABLE email_outbox ADD COLUMN last_attempt_at DATETIME NULL AFTER attempts',
    ];
    foreach ($outboxColumns as $column => $statement) {
        if (function_exists('store_table_has_column') && !store_table_has_column($pdo, 'email_outbox', $column)) {
            $pdo->exec($statement);
        }
    }

    $stmt = $pdo->prepare('INSERT INTO email_templates (template_key, name, subject, body, description, is_active) VALUES (:template_key, :name, :subject, :body, :description, 1) ON DUPLICATE KEY UPDATE template_key = VALUES(template_key)');
    foreach (store_email_default_templates() as $key => $template) {
        $stmt->execute([
            'template_key' => $key,
            'name' => $template['name'],
            'subject' => $template['subject'],
            'body' => $template['body'],
            'description' => $template['description'],
        ]);
    }
    $done = true;
}

function store_email_template(PDO $pdo, string $templateKey): ?array
{
    store_ensure_email_tables($pdo);
    $stmt = $pdo->prepare('SELECT * FROM email_templates WHERE template_key = :template_key AND is_active = 1 LIMIT 1');
    $stmt->execute(['template_key' => mb_substr($templateKey, 0, 120)]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function store_email_render(string $text, array $vars): string
{
    $replacements = [];
    foreach ($vars as $key => $value) {
        if (!is_scalar($value) && $value !== null) {
            continue;
        }
        $safeKey = preg_replace('/[^a-z0-9_]+/i', '', (string) $key);
        if ($safeKey === '') {
            continue;
        }
        $replacements['{{' . $safeKey . '}}'] = (string) $value;
    }
    return strtr($text, $replacements);
}

function store_email_notifications_enabled(): bool
{
    $settings = $GLOBALS['siteSettings'] ?? [];
    return store_setting_bool(is_array($settings) ? $settings : [], 'email_notifications_enabled', false);
}

function store_queue_email(PDO $pdo, string $templateKey, string $recipientEmail, string $recipientName, array $vars = [], ?string $relatedType = null, ?int $relatedId = null): ?int
{
    if (!store_email_notifications_enabled()) {
        return null;
    }

    $recipientEmail = mb_substr(trim($recipientEmail), 0, 190);
    if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
        return null;
    }

    try {
        $template = store_email_template($pdo, $templateKey);
        if (!$template) {
            return null;
        }
        $settings = $GLOBALS['siteSettings'] ?? [];
        $siteName = store_setting(is_array($settings) ? $settings : [], 'site_name', 'Phonix');
        $vars = ['site_name' => $siteName] + $vars;
        $subject = mb_substr(store_email_render((string) $template['subject'], $vars), 0, 255);
        $body = store_email_render((string) $template['body'], $vars);
        $stmt = $pdo->prepare('INSERT INTO email_outbox (template_key, recipient_email, recipient_name, subject, body, status, related_type, related_id) VALUES (:template_key, :recipient_email, :recipient_name, :subject, :body, :status, :related_type, :related_id)');
        $stmt->execute([
            'template_key' => mb_substr($templateKey, 0, 120),
            'recipient_email' => $recipientEmail,
            'recipient_name' => mb_substr(trim($recipientName), 0, 190) ?: null,
            'subject' => $subject,
            'body' => $body,
            'status' => 'queued',
            'related_type' => $relatedType ? mb_substr($relatedType, 0, 80) : null,
            'related_id' => $relatedId,
        ]);
        return (int) $pdo->lastInsertId();
    } catch (Throwable $e) {
        error_log('[Phonix email queue] ' . $e->getMessage());
        return null;
    }
}


function store_email_delivery_enabled(): bool
{
    $settings = $GLOBALS['siteSettings'] ?? [];
    return store_setting_bool(is_array($settings) ? $settings : [], 'email_delivery_enabled', false);
}

function store_email_batch_size(): int
{
    $settings = $GLOBALS['siteSettings'] ?? [];
    $size = (int) (is_array($settings) ? ($settings['email_delivery_batch_size'] ?? 10) : 10);
    return max(1, min(50, $size));
}

function store_email_clean_header_value(string $value, int $max = 190): string
{
    $value = trim(preg_replace('/[\r\n]+/', ' ', $value) ?? '');
    return mb_substr($value, 0, $max);
}

function store_email_encoded_subject(string $subject): string
{
    $subject = store_email_clean_header_value($subject, 255);
    if ($subject === '') {
        return '(no subject)';
    }
    return preg_match('/[^\x20-\x7E]/', $subject) ? '=?UTF-8?B?' . base64_encode($subject) . '?=' : $subject;
}

function store_email_sender_settings(array $settings): array
{
    $fromName = store_email_clean_header_value(store_setting($settings, 'email_from_name', store_setting($settings, 'site_name', 'Phonix')), 120);
    $fromEmail = store_email_clean_header_value(store_setting($settings, 'email_from_email', 'no-reply@phonix.local'), 190);
    if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        $fromEmail = 'no-reply@phonix.local';
    }
    return [$fromName ?: 'Phonix', $fromEmail];
}

function store_email_send_via_php_mail(array $message, array $settings): array
{
    $to = store_email_clean_header_value((string) ($message['recipient_email'] ?? ''), 190);
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Recipient email is invalid.'];
    }

    [$fromName, $fromEmail] = store_email_sender_settings($settings);
    $encodedFromName = preg_match('/[^\x20-\x7E]/', $fromName) ? '=?UTF-8?B?' . base64_encode($fromName) . '?=' : $fromName;
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        'From: ' . $encodedFromName . ' <' . $fromEmail . '>',
        'Reply-To: ' . $fromEmail,
        'X-Mailer: Phonix Store Mailer',
    ];

    if (!function_exists('mail')) {
        return ['ok' => false, 'error' => 'PHP mail() is not available on this server.'];
    }

    $subject = store_email_encoded_subject((string) ($message['subject'] ?? ''));
    $body = str_replace(["\r\n", "\r"], "\n", (string) ($message['body'] ?? ''));
    $sent = @mail($to, $subject, $body, implode("\r\n", $headers));
    return $sent ? ['ok' => true, 'error' => null] : ['ok' => false, 'error' => 'mail() returned false. Check hosting mail configuration.'];
}

function store_process_email_queue(PDO $pdo, ?int $limit = null): array
{
    store_ensure_email_tables($pdo);
    $settings = fetch_site_settings($pdo);
    $GLOBALS['siteSettings'] = $settings;

    $result = [
        'processed' => 0,
        'sent' => 0,
        'failed' => 0,
        'skipped' => 0,
        'message' => '',
    ];

    if (!store_setting_bool($settings, 'email_delivery_enabled', false)) {
        $result['message'] = 'Email delivery is disabled.';
        return $result;
    }

    $limit = $limit ?? store_email_batch_size();
    $limit = max(1, min(50, (int) $limit));
    $stmt = $pdo->prepare("SELECT * FROM email_outbox WHERE status = 'queued' ORDER BY queued_at ASC, id ASC LIMIT {$limit}");
    $stmt->execute();
    $messages = $stmt->fetchAll();

    foreach ($messages as $message) {
        $id = (int) ($message['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }
        $lock = $pdo->prepare("UPDATE email_outbox SET status = 'processing', attempts = attempts + 1, last_attempt_at = NOW(), error_message = NULL WHERE id = :id AND status = 'queued' LIMIT 1");
        $lock->execute(['id' => $id]);
        if ($lock->rowCount() !== 1) {
            continue;
        }

        $result['processed']++;
        try {
            $delivery = store_email_send_via_php_mail($message, $settings);
            if ($delivery['ok'] ?? false) {
                $pdo->prepare("UPDATE email_outbox SET status = 'sent', sent_at = NOW(), error_message = NULL WHERE id = :id LIMIT 1")->execute(['id' => $id]);
                $result['sent']++;
            } else {
                $error = mb_substr((string) ($delivery['error'] ?? 'Email delivery failed.'), 0, 2000);
                $pdo->prepare("UPDATE email_outbox SET status = 'failed', sent_at = NULL, error_message = :error WHERE id = :id LIMIT 1")->execute(['error' => $error, 'id' => $id]);
                $result['failed']++;
            }
        } catch (Throwable $e) {
            $error = mb_substr($e->getMessage(), 0, 2000);
            $pdo->prepare("UPDATE email_outbox SET status = 'failed', sent_at = NULL, error_message = :error WHERE id = :id LIMIT 1")->execute(['error' => $error, 'id' => $id]);
            $result['failed']++;
        }
    }

    $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES ('email_last_run_at', NOW()) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")->execute();
    $result['message'] = $result['processed'] > 0 ? 'Email queue processed.' : 'No queued emails were waiting.';
    return $result;
}

function store_requeue_failed_emails(PDO $pdo, int $limit = 50): int
{
    store_ensure_email_tables($pdo);
    $limit = max(1, min(200, $limit));
    $stmt = $pdo->prepare("UPDATE email_outbox SET status = 'queued', error_message = NULL WHERE status = 'failed' ORDER BY updated_at DESC, id DESC LIMIT {$limit}");
    $stmt->execute();
    return $stmt->rowCount();
}

function store_admin_alert_email(): string
{
    $settings = $GLOBALS['siteSettings'] ?? [];
    $settings = is_array($settings) ? $settings : [];
    return store_setting($settings, 'email_admin_alert_email', store_setting($settings, 'support_email', 'support@phonix.com'));
}

function store_queue_order_emails(PDO $pdo, array $order): void
{
    $currency = isset($GLOBALS['siteCurrency']) ? (string) $GLOBALS['siteCurrency'] : 'TRY';
    $vars = [
        'order_number' => $order['order_number'] ?? '',
        'customer_name' => $order['full_name'] ?? 'Customer',
        'customer_email' => $order['email'] ?? '',
        'order_total' => format_price((float) ($order['total'] ?? 0), $currency),
        'shipping_method' => $order['shipping_method_name'] ?? '',
        'payment_method' => $order['payment_method_name'] ?? '',
    ];
    store_queue_email($pdo, 'order_confirmation', (string) ($order['email'] ?? ''), (string) ($order['full_name'] ?? ''), $vars, 'order', isset($order['id']) ? (int) $order['id'] : null);
    store_queue_email($pdo, 'admin_new_order', store_admin_alert_email(), 'Store admin', $vars, 'order', isset($order['id']) ? (int) $order['id'] : null);
}

function store_queue_support_emails(PDO $pdo, array $message): void
{
    $excerpt = trim(preg_replace('/\s+/', ' ', (string) ($message['message'] ?? '')) ?? '');
    $vars = [
        'support_id' => $message['id'] ?? '',
        'support_subject' => $message['subject'] ?? 'Support request',
        'support_excerpt' => mb_substr($excerpt, 0, 240),
        'customer_name' => $message['name'] ?? 'Customer',
        'customer_email' => $message['email'] ?? '',
        'order_number' => $message['order_number'] ?? '—',
    ];
    store_queue_email($pdo, 'support_confirmation', (string) ($message['email'] ?? ''), (string) ($message['name'] ?? ''), $vars, 'support_message', isset($message['id']) ? (int) $message['id'] : null);
    store_queue_email($pdo, 'admin_new_support', store_admin_alert_email(), 'Store admin', $vars, 'support_message', isset($message['id']) ? (int) $message['id'] : null);
}

function store_is_admin_user(PDO $pdo, ?array $user): bool
{
    if (!$user) {
        return false;
    }
    $email = mb_strtolower(trim((string) ($user['email'] ?? '')));
    if ($email === '') {
        return false;
    }
    if ($email === 'admin@phonix.local') {
        return true;
    }
    try {
        $stmt = $pdo->prepare("SELECT id FROM admins WHERE LOWER(email) = :email AND (status IS NULL OR status = '' OR status = 'active') LIMIT 1");
        $stmt->execute(['email' => $email]);
        return (bool) $stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('[Phonix admin bypass check] ' . $e->getMessage());
        return false;
    }
}

function store_request_path(): string
{
    $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    return '/' . ltrim((string) $path, '/');
}

function store_request_is_admin_area(): bool
{
    $path = store_request_path();
    return str_contains($path, '/admin/') || preg_match('#/(admin|admin-login-reset)\.php$#', $path) === 1;
}

function store_request_is_auth_area(): bool
{
    return preg_match('#/(auth|admin-login-reset)\.php$#', store_request_path()) === 1;
}

function store_request_is_api_area(): bool
{
    return str_contains(store_request_path(), '/api/');
}


function store_ensure_public_product_columns(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }

    try {
        if (!store_table_has_column($pdo, 'products', 'product_status')) {
            $pdo->exec("ALTER TABLE products ADD COLUMN product_status VARCHAR(40) NOT NULL DEFAULT 'active' AFTER gallery_json");
        }
        if (!store_table_has_column($pdo, 'products', 'benefits_json')) {
            $pdo->exec("ALTER TABLE products ADD COLUMN benefits_json LONGTEXT NULL AFTER specs_json");
        }
        $pdo->exec("UPDATE products SET product_status = CASE WHEN is_active = 0 THEN 'archived' WHEN stock <= 0 THEN 'out_of_stock' ELSE 'active' END WHERE product_status IS NULL OR product_status = ''");
    } catch (Throwable $e) {
        error_log('[Phonix public product columns] ' . $e->getMessage());
    }

    $done = true;
}

function store_public_product_visibility_sql(string $alias = 'p'): string
{
    $alias = preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $alias) ? $alias : 'p';
    return $alias . ".is_active = 1 AND COALESCE(NULLIF(" . $alias . ".product_status, ''), 'active') IN ('active','out_of_stock')";
}

function store_public_product_purchasable_sql(string $alias = 'p'): string
{
    $alias = preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $alias) ? $alias : 'p';
    return store_public_product_visibility_sql($alias) . " AND COALESCE(NULLIF(" . $alias . ".product_status, ''), 'active') = 'active' AND " . $alias . ".stock > 0";
}

function public_product_status(array $product): string
{
    $status = trim((string) ($product['product_status'] ?? ''));
    if ($status === '') {
        $status = ((int) ($product['is_active'] ?? 1) === 1) ? 'active' : 'archived';
    }
    if ($status === 'active' && (int) ($product['stock'] ?? 0) <= 0) {
        return 'out_of_stock';
    }
    return $status;
}

function public_product_is_visible(array $product): bool
{
    return (int) ($product['is_active'] ?? 0) === 1 && in_array(public_product_status($product), ['active', 'out_of_stock'], true);
}

function public_product_is_purchasable(array $product): bool
{
    return public_product_is_visible($product) && public_product_status($product) === 'active' && (int) ($product['stock'] ?? 0) > 0;
}

function public_product_stock_label(array $product): string
{
    if (public_product_is_purchasable($product)) {
        $stock = (int) ($product['stock'] ?? 0);
        return $stock <= 5 ? 'Only ' . $stock . ' left' : 'In Stock';
    }
    return 'Sold Out';
}

function public_product_badge(array $product, string $fallback = 'Product'): string
{
    $badge = trim((string) ($product['badge'] ?? ''));
    if ($badge !== '') {
        return $badge;
    }
    return public_product_stock_label($product) ?: $fallback;
}

function fetch_categories_with_counts(PDO $pdo): array
{
    store_ensure_public_product_columns($pdo);
    $visibleSql = store_public_product_visibility_sql('p');
    $sql = 'SELECT c.id, c.name, c.slug, COUNT(p.id) AS product_count
            FROM categories c
            LEFT JOIN products p ON p.category_id = c.id AND ' . $visibleSql . '
            GROUP BY c.id, c.name, c.slug
            ORDER BY c.name ASC';
    return $pdo->query($sql)->fetchAll();
}

function fetch_featured_products(PDO $pdo, int $limit = 4): array
{
    store_ensure_public_product_columns($pdo);
    $visibleSql = store_public_product_visibility_sql('p');
    $stmt = $pdo->prepare('SELECT p.*, c.slug AS category_slug, c.name AS category_name
                           FROM products p
                           LEFT JOIN categories c ON c.id = p.category_id
                           WHERE ' . $visibleSql . '
                           ORDER BY p.is_featured DESC, p.created_at DESC, p.id DESC
                           LIMIT :limit');
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function fetch_products(PDO $pdo, array $filters = []): array
{
    store_ensure_public_product_columns($pdo);
    $where = [store_public_product_visibility_sql('p')];
    $params = [];

    if (!empty($filters['category_slug'])) {
        $where[] = 'c.slug = :category_slug';
        $params[':category_slug'] = $filters['category_slug'];
    }

    if (!empty($filters['search'])) {
        $where[] = '(p.name LIKE :search OR p.short_description LIKE :search OR p.description LIKE :search OR p.sku LIKE :search OR p.brand LIKE :search OR c.name LIKE :search)';
        $params[':search'] = '%' . $filters['search'] . '%';
    }

    $orderBy = 'p.is_featured DESC, p.created_at DESC, p.id DESC';
    if (!empty($filters['sort'])) {
        switch ($filters['sort']) {
            case 'price_asc':
                $orderBy = 'p.price ASC, p.id DESC';
                break;
            case 'price_desc':
                $orderBy = 'p.price DESC, p.id DESC';
                break;
            case 'name_asc':
                $orderBy = 'p.name ASC';
                break;
            case 'rating_desc':
                $orderBy = 'p.rating DESC, p.id DESC';
                break;
        }
    }

    $sql = 'SELECT p.*, c.slug AS category_slug, c.name AS category_name
            FROM products p
            LEFT JOIN categories c ON c.id = p.category_id
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY ' . $orderBy;

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    $stmt->execute();
    return $stmt->fetchAll();
}

function fetch_product_by_slug(PDO $pdo, string $slug): ?array
{
    store_ensure_public_product_columns($pdo);
    $visibleSql = store_public_product_visibility_sql('p');
    $stmt = $pdo->prepare('SELECT p.*, c.slug AS category_slug, c.name AS category_name
                           FROM products p
                           LEFT JOIN categories c ON c.id = p.category_id
                           WHERE p.slug = :slug AND ' . $visibleSql . '
                           LIMIT 1');
    $stmt->execute([':slug' => $slug]);
    $product = $stmt->fetch();
    return $product ?: null;
}

function store_ensure_product_variants_table(PDO $pdo): void
{
    static $done = false;
    if ($done) {
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
        CONSTRAINT fk_product_variants_product_public FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $done = true;
}

function fetch_product_variants(PDO $pdo, int $productId): array
{
    store_ensure_product_variants_table($pdo);
    $stmt = $pdo->prepare('SELECT option_type, option_value FROM product_variants WHERE product_id = :product_id ORDER BY id ASC');
    $stmt->execute([':product_id' => $productId]);
    $variants = [];
    foreach ($stmt as $row) {
        $variants[$row['option_type']][] = $row['option_value'];
    }
    return $variants;
}

function fetch_related_products(PDO $pdo, int $productId, ?int $categoryId, int $limit = 4): array
{
    store_ensure_public_product_columns($pdo);
    $visibleSql = store_public_product_visibility_sql('p');

    if ($categoryId) {
        $stmt = $pdo->prepare('SELECT p.*, c.slug AS category_slug, c.name AS category_name
                               FROM products p
                               LEFT JOIN categories c ON c.id = p.category_id
                               WHERE ' . $visibleSql . ' AND p.id <> :product_id AND p.category_id = :category_id
                               ORDER BY p.is_featured DESC, p.created_at DESC, p.id DESC
                               LIMIT :limit');
        $stmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
        $stmt->bindValue(':category_id', $categoryId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        if ($rows) {
            return $rows;
        }
    }

    $stmt = $pdo->prepare('SELECT p.*, c.slug AS category_slug, c.name AS category_name
                           FROM products p
                           LEFT JOIN categories c ON c.id = p.category_id
                           WHERE ' . $visibleSql . ' AND p.id <> :product_id
                           ORDER BY p.is_featured DESC, p.created_at DESC, p.id DESC
                           LIMIT :limit');
    $stmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}
function format_price($amount, string $currency = 'TRY'): string
{
    $amount = (float) $amount;
    $currency = strtoupper($currency);

    if ($currency === 'TRY') {
        return '₺' . number_format($amount, 0, ',', '.');
    }

    $symbol = '$';
    if ($currency === 'EUR') {
        $symbol = '€';
    } elseif ($currency === 'GBP') {
        $symbol = '£';
    }

    return $symbol . number_format($amount, 2);
}

function public_safe_url(?string $url, string $fallback = 'products.php'): string
{
    $url = trim((string) $url);
    if ($url === '') {
        return $fallback;
    }

    $lower = strtolower($url);
    if (str_starts_with($lower, 'javascript:') || str_starts_with($lower, 'data:') || str_starts_with($lower, 'vbscript:')) {
        return $fallback;
    }

    $parts = parse_url($url);
    if ($parts === false) {
        return $fallback;
    }

    if (isset($parts['scheme']) && !in_array(strtolower((string) $parts['scheme']), ['http', 'https'], true)) {
        return $fallback;
    }

    return $url;
}

function public_image_url(?string $path): string
{
    $path = trim((string) $path);
    if ($path === '') {
        return '';
    }

    $lower = strtolower($path);
    if (str_starts_with($lower, 'javascript:') || str_starts_with($lower, 'data:') || str_starts_with($lower, 'vbscript:')) {
        return '';
    }

    return $path;
}

function fetch_live_deal_campaigns(PDO $pdo): array
{
    store_ensure_public_product_columns($pdo);
    $visibleSql = store_public_product_visibility_sql('p');
    try {
        $sql = 'SELECT d.*, c.code AS coupon_code, c.discount_type, c.discount_value, c.min_order_total, c.ends_at AS coupon_ends_at,
                       p.name AS product_name, p.slug AS product_slug, p.price AS product_price, p.compare_price AS product_compare_price,
                       p.image AS product_image, p.brand AS product_brand, p.short_description AS product_short_description
                FROM deal_campaigns d
                LEFT JOIN coupons c ON c.id = d.coupon_id
                    AND c.is_active = 1
                    AND (c.starts_at IS NULL OR c.starts_at <= NOW())
                    AND (c.ends_at IS NULL OR c.ends_at >= NOW())
                    AND (c.max_uses IS NULL OR c.used_count < c.max_uses)
                LEFT JOIN products p ON p.id = d.product_id AND ' . $visibleSql . '
                WHERE d.is_active = 1
                  AND (d.starts_at IS NULL OR d.starts_at <= NOW())
                  AND (d.ends_at IS NULL OR d.ends_at >= NOW())
                  AND (d.product_id IS NULL OR p.id IS NOT NULL)
                ORDER BY d.sort_order ASC, d.id DESC';
        return $pdo->query($sql)->fetchAll();
    } catch (Throwable $e) {
        error_log('[Phonix deals] ' . $e->getMessage());
        return [];
    }
}

function fetch_public_active_coupons(PDO $pdo, int $limit = 12): array
{
    try {
        $stmt = $pdo->prepare("SELECT * FROM coupons
                               WHERE is_active = 1
                                 AND (starts_at IS NULL OR starts_at <= NOW())
                                 AND (ends_at IS NULL OR ends_at >= NOW())
                                 AND (max_uses IS NULL OR used_count < max_uses)
                               ORDER BY created_at DESC, id DESC
                               LIMIT :limit");
        $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        error_log('[Phonix coupons] ' . $e->getMessage());
        return [];
    }
}

function fetch_public_sale_products(PDO $pdo, int $limit = 12): array
{
    store_ensure_public_product_columns($pdo);
    $visibleSql = store_public_product_visibility_sql('p');
    try {
        $stmt = $pdo->prepare('SELECT p.*, c.slug AS category_slug, c.name AS category_name
                               FROM products p
                               LEFT JOIN categories c ON c.id = p.category_id
                               WHERE ' . $visibleSql . '
                                 AND p.compare_price IS NOT NULL
                                 AND p.compare_price > p.price
                               ORDER BY ((p.compare_price - p.price) / NULLIF(p.compare_price, 0)) DESC, p.updated_at DESC, p.id DESC
                               LIMIT :limit');
        $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        error_log('[Phonix sale products] ' . $e->getMessage());
        return [];
    }
}

function public_deal_cta_url(array $deal): string
{
    $fallback = !empty($deal['product_slug']) ? site_url('product', ['slug' => (string) $deal['product_slug']]) : site_url('products');
    return public_safe_url($deal['cta_url'] ?? '', $fallback);
}

function public_deal_image(array $deal): string
{
    $image = public_image_url($deal['image_path'] ?? '');
    if ($image !== '') {
        return $image;
    }
    return public_image_url($deal['product_image'] ?? '');
}

function public_coupon_discount_label(array $coupon, string $currency = 'TRY'): string
{
    $type = (string) ($coupon['discount_type'] ?? 'percent');
    $value = (float) ($coupon['discount_value'] ?? 0);
    if ($type === 'fixed') {
        return format_price($value, $currency) . ' off';
    }
    return rtrim(rtrim(number_format($value, 2), '0'), '.') . '% off';
}


function current_store_context(PDO $pdo): array
{
    $user = function_exists('auth_current_user') ? auth_current_user($pdo) : null;
    $sessionKey = function_exists('auth_guest_session_key') ? auth_guest_session_key() : session_id();

    return [
        'user_id' => $user ? (int) $user['id'] : null,
        'session_key' => (string) $sessionKey,
    ];
}

function get_or_create_cart_id(PDO $pdo, ?int $userId, string $sessionKey): int
{
    if ($userId) {
        $stmt = $pdo->prepare('SELECT id FROM carts WHERE user_id = :user_id ORDER BY id ASC LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        $id = $stmt->fetchColumn();
        if ($id) {
            return (int) $id;
        }

        $stmt = $pdo->prepare('INSERT INTO carts (user_id, session_key) VALUES (:user_id, NULL)');
        $stmt->execute(['user_id' => $userId]);
        return (int) $pdo->lastInsertId();
    }

    $stmt = $pdo->prepare('SELECT id FROM carts WHERE session_key = :session_key AND user_id IS NULL ORDER BY id ASC LIMIT 1');
    $stmt->execute(['session_key' => $sessionKey]);
    $id = $stmt->fetchColumn();
    if ($id) {
        return (int) $id;
    }

    $stmt = $pdo->prepare('INSERT INTO carts (user_id, session_key) VALUES (NULL, :session_key)');
    $stmt->execute(['session_key' => $sessionKey]);
    return (int) $pdo->lastInsertId();
}

function store_claim_guest_state_to_user(PDO $pdo, int $userId, string $sessionKey): void
{
    store_ensure_cart_option_columns($pdo);
    if ($userId <= 0 || $sessionKey === '') {
        return;
    }

    $userCartStmt = $pdo->prepare('SELECT id FROM carts WHERE user_id = :user_id ORDER BY id ASC LIMIT 1');
    $userCartStmt->execute(['user_id' => $userId]);
    $userCartId = $userCartStmt->fetchColumn();

    $guestCartStmt = $pdo->prepare('SELECT id FROM carts WHERE session_key = :session_key AND user_id IS NULL ORDER BY id ASC LIMIT 1');
    $guestCartStmt->execute(['session_key' => $sessionKey]);
    $guestCartId = $guestCartStmt->fetchColumn();

    if ($guestCartId) {
        $guestCartId = (int) $guestCartId;
        if (!$userCartId) {
            $stmt = $pdo->prepare('UPDATE carts SET user_id = :user_id, session_key = NULL WHERE id = :id');
            $stmt->execute(['user_id' => $userId, 'id' => $guestCartId]);
            $userCartId = $guestCartId;
        } elseif ((int) $userCartId !== $guestCartId) {
            $itemsStmt = $pdo->prepare('SELECT product_id, qty, selected_options_json, selected_options_hash FROM cart_items WHERE cart_id = :cart_id');
            $itemsStmt->execute(['cart_id' => $guestCartId]);
            $items = $itemsStmt->fetchAll();
            foreach ($items as $item) {
                $existingStmt = $pdo->prepare('SELECT id, qty FROM cart_items WHERE cart_id = :cart_id AND product_id = :product_id AND selected_options_hash = :selected_options_hash LIMIT 1');
                $existingStmt->execute([
                    'cart_id' => (int) $userCartId,
                    'product_id' => (int) $item['product_id'],
                    'selected_options_hash' => (string) ($item['selected_options_hash'] ?: 'default'),
                ]);
                $existing = $existingStmt->fetch();
                if ($existing) {
                    $updateStmt = $pdo->prepare('UPDATE cart_items SET qty = qty + :qty WHERE id = :id');
                    $updateStmt->execute([
                        'qty' => (int) $item['qty'],
                        'id' => (int) $existing['id'],
                    ]);
                } else {
                    $insertStmt = $pdo->prepare('INSERT INTO cart_items (cart_id, product_id, qty, selected_options_json, selected_options_hash) VALUES (:cart_id, :product_id, :qty, :selected_options_json, :selected_options_hash)');
                    $insertStmt->execute([
                        'cart_id' => (int) $userCartId,
                        'product_id' => (int) $item['product_id'],
                        'qty' => (int) $item['qty'],
                        'selected_options_json' => $item['selected_options_json'] ?: null,
                        'selected_options_hash' => (string) ($item['selected_options_hash'] ?: 'default'),
                    ]);
                }
            }
            $pdo->prepare('DELETE FROM carts WHERE id = :id')->execute(['id' => $guestCartId]);
        }
    }

    $guestWishlistStmt = $pdo->prepare('SELECT product_id FROM wishlist_items WHERE session_key = :session_key AND user_id IS NULL');
    $guestWishlistStmt->execute(['session_key' => $sessionKey]);
    $productIds = $guestWishlistStmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($productIds as $productId) {
        $existsStmt = $pdo->prepare('SELECT id FROM wishlist_items WHERE user_id = :user_id AND product_id = :product_id LIMIT 1');
        $existsStmt->execute([
            'user_id' => $userId,
            'product_id' => (int) $productId,
        ]);
        if (!$existsStmt->fetchColumn()) {
            $insertStmt = $pdo->prepare('INSERT INTO wishlist_items (user_id, session_key, product_id) VALUES (:user_id, NULL, :product_id)');
            $insertStmt->execute([
                'user_id' => $userId,
                'product_id' => (int) $productId,
            ]);
        }
    }
    $pdo->prepare('DELETE FROM wishlist_items WHERE session_key = :session_key AND user_id IS NULL')->execute(['session_key' => $sessionKey]);
}

function fetch_cart_items(PDO $pdo): array
{
    store_ensure_public_product_columns($pdo);
    store_ensure_cart_option_columns($pdo);
    $context = current_store_context($pdo);
    $purchasableSql = store_public_product_purchasable_sql('p');
    if ($context['user_id']) {
        $sql = 'SELECT p.id, p.name, p.slug, p.image, p.price, p.compare_price, p.stock, p.product_status, ci.id AS cart_item_id, ci.qty, ci.selected_options_json, ci.selected_options_hash
                FROM carts c
                INNER JOIN cart_items ci ON ci.cart_id = c.id
                INNER JOIN products p ON p.id = ci.product_id
                WHERE c.user_id = :user_id AND ' . $purchasableSql . '
                ORDER BY ci.updated_at DESC, ci.id DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['user_id' => $context['user_id']]);
    } else {
        $sql = 'SELECT p.id, p.name, p.slug, p.image, p.price, p.compare_price, p.stock, p.product_status, ci.id AS cart_item_id, ci.qty, ci.selected_options_json, ci.selected_options_hash
                FROM carts c
                INNER JOIN cart_items ci ON ci.cart_id = c.id
                INNER JOIN products p ON p.id = ci.product_id
                WHERE c.session_key = :session_key AND c.user_id IS NULL AND ' . $purchasableSql . '
                ORDER BY ci.updated_at DESC, ci.id DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['session_key' => $context['session_key']]);
    }
    $items = [];
    foreach ($stmt->fetchAll() as $row) {
        $qty = max(1, (int) $row['qty']);
        $stock = max(0, (int) $row['stock']);
        if ($stock > 0 && $qty > $stock) {
            $qty = $stock;
        }
        $selectedOptions = store_options_from_json($row['selected_options_json'] ?? null);
        $optionHash = (string) (($row['selected_options_hash'] ?? '') ?: store_options_hash($selectedOptions));
        $items[] = [
            'id' => (int) $row['id'],
            'cart_item_id' => (int) $row['cart_item_id'],
            'name' => $row['name'],
            'slug' => $row['slug'],
            'image' => $row['image'],
            'price' => (float) $row['price'],
            'compare_price' => $row['compare_price'] !== null ? (float) $row['compare_price'] : null,
            'stock' => $stock,
            'product_status' => (string) ($row['product_status'] ?? 'active'),
            'selected_options' => $selectedOptions,
            'selected_options_label' => store_options_label($selectedOptions),
            'selected_options_hash' => $optionHash,
            'qty' => $qty,
            'line_total' => (float) $row['price'] * $qty,
        ];
    }
    return $items;
}
function fetch_wishlist_items(PDO $pdo): array
{
    store_ensure_public_product_columns($pdo);
    $context = current_store_context($pdo);
    $visibleSql = store_public_product_visibility_sql('p');
    if ($context['user_id']) {
        $sql = 'SELECT p.id, p.name, p.slug, p.image, p.price, p.compare_price, p.stock, p.product_status, p.badge, p.short_description
                FROM wishlist_items wi
                INNER JOIN products p ON p.id = wi.product_id
                WHERE wi.user_id = :user_id AND ' . $visibleSql . '
                GROUP BY p.id, p.name, p.slug, p.image, p.price, p.compare_price, p.stock, p.product_status, p.badge, p.short_description
                ORDER BY MAX(wi.created_at) DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['user_id' => $context['user_id']]);
    } else {
        $sql = 'SELECT p.id, p.name, p.slug, p.image, p.price, p.compare_price, p.stock, p.product_status, p.badge, p.short_description
                FROM wishlist_items wi
                INNER JOIN products p ON p.id = wi.product_id
                WHERE wi.session_key = :session_key AND wi.user_id IS NULL AND ' . $visibleSql . '
                GROUP BY p.id, p.name, p.slug, p.image, p.price, p.compare_price, p.stock, p.product_status, p.badge, p.short_description
                ORDER BY MAX(wi.created_at) DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['session_key' => $context['session_key']]);
    }
    return array_map(static function (array $row): array {
        return [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'slug' => $row['slug'],
            'image' => $row['image'],
            'price' => (float) $row['price'],
            'compare_price' => $row['compare_price'] !== null ? (float) $row['compare_price'] : null,
            'stock' => (int) $row['stock'],
            'product_status' => (string) ($row['product_status'] ?? 'active'),
            'badge' => $row['badge'],
            'short_description' => $row['short_description'],
        ];
    }, $stmt->fetchAll());
}
function fetch_wishlist_ids(PDO $pdo): array
{
    return array_values(array_map(static fn(array $item): int => (int) $item['id'], fetch_wishlist_items($pdo)));
}

function cart_summary_from_items(array $items): array
{
    $count = 0;
    $subtotal = 0.0;
    foreach ($items as $item) {
        $count += (int) $item['qty'];
        $subtotal += (float) $item['line_total'];
    }

    return [
        'items' => $items,
        'count' => $count,
        'subtotal' => $subtotal,
    ];
}

function add_product_to_cart(PDO $pdo, int $productId, int $qty = 1, $selectedOptions = []): bool
{
    $qty = max(1, $qty);
    store_ensure_public_product_columns($pdo);
    store_ensure_cart_option_columns($pdo);
    $productStmt = $pdo->prepare('SELECT id, stock, is_active, product_status FROM products WHERE id = :id AND ' . store_public_product_purchasable_sql('products') . ' LIMIT 1');
    $productStmt->execute(['id' => $productId]);
    $product = $productStmt->fetch();
    if (!$product) {
        return false;
    }

    $stock = max(0, (int) $product['stock']);
    if ($stock <= 0) {
        return false;
    }

    $options = store_sanitize_product_options($pdo, $productId, $selectedOptions);
    $optionsHash = store_options_hash($options);
    $optionsJson = store_options_json($options);

    $context = current_store_context($pdo);
    $cartId = get_or_create_cart_id($pdo, $context['user_id'], $context['session_key']);
    $existingStmt = $pdo->prepare('SELECT id, qty FROM cart_items WHERE cart_id = :cart_id AND product_id = :product_id AND selected_options_hash = :selected_options_hash LIMIT 1');
    $existingStmt->execute([
        'cart_id' => $cartId,
        'product_id' => $productId,
        'selected_options_hash' => $optionsHash,
    ]);
    $existing = $existingStmt->fetch();
    if ($existing) {
        $newQty = min($stock, (int) $existing['qty'] + $qty);
        $updateStmt = $pdo->prepare('UPDATE cart_items SET qty = :qty, selected_options_json = :selected_options_json WHERE id = :id');
        $updateStmt->execute([
            'qty' => $newQty,
            'selected_options_json' => $optionsJson,
            'id' => (int) $existing['id'],
        ]);
    } else {
        $insertStmt = $pdo->prepare('INSERT INTO cart_items (cart_id, product_id, qty, selected_options_json, selected_options_hash) VALUES (:cart_id, :product_id, :qty, :selected_options_json, :selected_options_hash)');
        $insertStmt->execute([
            'cart_id' => $cartId,
            'product_id' => $productId,
            'qty' => min($stock, $qty),
            'selected_options_json' => $optionsJson,
            'selected_options_hash' => $optionsHash,
        ]);
    }
    return true;
}

function update_cart_item_qty(PDO $pdo, int $productId, int $qty, $selectedOptions = null, ?string $optionHash = null): bool
{
    $context = current_store_context($pdo);
    store_ensure_public_product_columns($pdo);
    store_ensure_cart_option_columns($pdo);
    $productStmt = $pdo->prepare('SELECT id, stock, is_active, product_status FROM products WHERE id = :id AND ' . store_public_product_purchasable_sql('products') . ' LIMIT 1');
    $productStmt->execute(['id' => $productId]);
    $product = $productStmt->fetch();
    if (!$product) {
        return false;
    }

    if ($context['user_id']) {
        $cartStmt = $pdo->prepare('SELECT id FROM carts WHERE user_id = :user_id ORDER BY id ASC LIMIT 1');
        $cartStmt->execute(['user_id' => $context['user_id']]);
    } else {
        $cartStmt = $pdo->prepare('SELECT id FROM carts WHERE session_key = :session_key AND user_id IS NULL ORDER BY id ASC LIMIT 1');
        $cartStmt->execute(['session_key' => $context['session_key']]);
    }
    $cartId = $cartStmt->fetchColumn();
    if (!$cartId) {
        return false;
    }

    $optionHash = trim((string) ($optionHash ?? ''));
    if ($optionHash === '') {
        $options = $selectedOptions === null ? store_sanitize_product_options($pdo, $productId, []) : store_sanitize_product_options($pdo, $productId, $selectedOptions);
        $optionHash = store_options_hash($options);
    }

    if ($qty <= 0) {
        $deleteStmt = $pdo->prepare('DELETE FROM cart_items WHERE cart_id = :cart_id AND product_id = :product_id AND selected_options_hash = :selected_options_hash');
        $deleteStmt->execute(['cart_id' => (int) $cartId, 'product_id' => $productId, 'selected_options_hash' => $optionHash]);
        return true;
    }

    $stock = max(0, (int) $product['stock']);
    if ($stock <= 0) {
        $deleteStmt = $pdo->prepare('DELETE FROM cart_items WHERE cart_id = :cart_id AND product_id = :product_id AND selected_options_hash = :selected_options_hash');
        $deleteStmt->execute(['cart_id' => (int) $cartId, 'product_id' => $productId, 'selected_options_hash' => $optionHash]);
        return true;
    }

    $qty = min(max(1, $qty), $stock);
    $updateStmt = $pdo->prepare('UPDATE cart_items SET qty = :qty WHERE cart_id = :cart_id AND product_id = :product_id AND selected_options_hash = :selected_options_hash');
    $updateStmt->execute(['qty' => $qty, 'cart_id' => (int) $cartId, 'product_id' => $productId, 'selected_options_hash' => $optionHash]);
    return true;
}

function remove_cart_item(PDO $pdo, int $productId, $selectedOptions = null, ?string $optionHash = null): bool
{
    return update_cart_item_qty($pdo, $productId, 0, $selectedOptions, $optionHash);
}

function toggle_wishlist_item(PDO $pdo, int $productId): string
{
    store_ensure_public_product_columns($pdo);
    $productStmt = $pdo->prepare('SELECT id, is_active, stock, product_status FROM products WHERE id = :id AND ' . store_public_product_visibility_sql('products') . ' LIMIT 1');
    $productStmt->execute(['id' => $productId]);
    $product = $productStmt->fetch();
    if (!$product) {
        return 'missing';
    }

    $context = current_store_context($pdo);
    if ($context['user_id']) {
        $existsStmt = $pdo->prepare('SELECT id FROM wishlist_items WHERE user_id = :user_id AND product_id = :product_id LIMIT 1');
        $existsStmt->execute(['user_id' => $context['user_id'], 'product_id' => $productId]);
        $existingId = $existsStmt->fetchColumn();
        if ($existingId) {
            $pdo->prepare('DELETE FROM wishlist_items WHERE id = :id')->execute(['id' => (int) $existingId]);
            return 'removed';
        }
        $pdo->prepare('INSERT INTO wishlist_items (user_id, session_key, product_id) VALUES (:user_id, NULL, :product_id)')
            ->execute(['user_id' => $context['user_id'], 'product_id' => $productId]);
        return 'added';
    }

    $existsStmt = $pdo->prepare('SELECT id FROM wishlist_items WHERE session_key = :session_key AND user_id IS NULL AND product_id = :product_id LIMIT 1');
    $existsStmt->execute(['session_key' => $context['session_key'], 'product_id' => $productId]);
    $existingId = $existsStmt->fetchColumn();
    if ($existingId) {
        $pdo->prepare('DELETE FROM wishlist_items WHERE id = :id')->execute(['id' => (int) $existingId]);
        return 'removed';
    }
    $pdo->prepare('INSERT INTO wishlist_items (user_id, session_key, product_id) VALUES (NULL, :session_key, :product_id)')
        ->execute(['session_key' => $context['session_key'], 'product_id' => $productId]);
    return 'added';
}

function sync_legacy_store_state(PDO $pdo, array $cart, array $wishlist): void
{
    foreach ($cart as $entry) {
        $productId = (int) ($entry['id'] ?? 0);
        $qty = max(1, (int) ($entry['qty'] ?? 1));
        if ($productId > 0) {
            add_product_to_cart($pdo, $productId, $qty);
        }
    }
    $existingIds = fetch_wishlist_ids($pdo);
    foreach ($wishlist as $productId) {
        $productId = (int) $productId;
        if ($productId > 0 && !in_array($productId, $existingIds, true)) {
            $result = toggle_wishlist_item($pdo, $productId);
            if ($result === 'added') {
                $existingIds[] = $productId;
            }
        }
    }
}

function store_table_has_column(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name');
    $stmt->execute(['table_name' => $table, 'column_name' => $column]);
    return (int) $stmt->fetchColumn() > 0;
}
function store_table_has_index(PDO $pdo, string $table, string $index): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND INDEX_NAME = :index_name');
    $stmt->execute(['table_name' => $table, 'index_name' => $index]);
    return (int) $stmt->fetchColumn() > 0;
}

function store_ensure_cart_option_columns(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }

    if (!store_table_has_column($pdo, 'cart_items', 'selected_options_json')) {
        $pdo->exec('ALTER TABLE cart_items ADD COLUMN selected_options_json TEXT NULL AFTER qty');
    }
    if (!store_table_has_column($pdo, 'cart_items', 'selected_options_hash')) {
        $pdo->exec("ALTER TABLE cart_items ADD COLUMN selected_options_hash CHAR(40) NOT NULL DEFAULT 'default' AFTER selected_options_json");
    }
    if (store_table_has_index($pdo, 'cart_items', 'uq_cart_product')) {
        try {
            $pdo->exec('ALTER TABLE cart_items DROP INDEX uq_cart_product');
        } catch (Throwable $e) {
            error_log('[Phonix cart options] Could not drop old cart unique key: ' . $e->getMessage());
        }
    }
    if (!store_table_has_index($pdo, 'cart_items', 'uq_cart_product_options')) {
        try {
            $pdo->exec('ALTER TABLE cart_items ADD UNIQUE KEY uq_cart_product_options (cart_id, product_id, selected_options_hash)');
        } catch (Throwable $e) {
            error_log('[Phonix cart options] Could not add cart option unique key: ' . $e->getMessage());
        }
    }
    if (!store_table_has_column($pdo, 'order_items', 'selected_options_json')) {
        $pdo->exec('ALTER TABLE order_items ADD COLUMN selected_options_json TEXT NULL AFTER line_total');
    }

    $done = true;
}

function store_options_from_json($json): array
{
    if (!is_string($json) || trim($json) === '') {
        return [];
    }
    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return [];
    }
    $options = [];
    foreach ($decoded as $key => $value) {
        $key = mb_substr(trim((string) $key), 0, 100);
        $value = mb_substr(trim((string) $value), 0, 190);
        if ($key !== '' && $value !== '') {
            $options[$key] = $value;
        }
    }
    return $options;
}

function store_normalize_option_key(string $key): string
{
    $key = mb_strtolower(trim($key));
    $key = preg_replace('/[^\p{L}\p{N}]+/u', '_', $key) ?? $key;
    return trim($key, '_');
}

function store_raw_options_to_array($rawOptions): array
{
    if (is_string($rawOptions)) {
        $decoded = json_decode($rawOptions, true);
        $rawOptions = is_array($decoded) ? $decoded : [];
    }
    if (!is_array($rawOptions)) {
        return [];
    }

    $options = [];
    foreach ($rawOptions as $key => $value) {
        if (is_array($value)) {
            continue;
        }
        $key = mb_substr(trim((string) $key), 0, 100);
        $value = mb_substr(trim((string) $value), 0, 190);
        if ($key !== '' && $value !== '') {
            $options[$key] = $value;
        }
    }
    return $options;
}

function store_sanitize_product_options(PDO $pdo, int $productId, $rawOptions = []): array
{
    $input = store_raw_options_to_array($rawOptions);
    $inputByKey = [];
    foreach ($input as $key => $value) {
        $inputByKey[store_normalize_option_key($key)] = $value;
    }

    try {
        $groups = fetch_product_variants($pdo, $productId);
    } catch (Throwable $e) {
        $groups = [];
    }

    $clean = [];
    foreach ($groups as $type => $values) {
        $type = mb_substr(trim((string) $type), 0, 100);
        if ($type === '' || !is_array($values)) {
            continue;
        }
        $allowed = [];
        foreach ($values as $value) {
            $value = mb_substr(trim((string) $value), 0, 190);
            if ($value !== '' && !in_array($value, $allowed, true)) {
                $allowed[] = $value;
            }
        }
        if ($allowed === []) {
            continue;
        }

        $submitted = $inputByKey[store_normalize_option_key($type)] ?? null;
        $selected = $allowed[0];
        if ($submitted !== null) {
            foreach ($allowed as $allowedValue) {
                if (mb_strtolower($allowedValue) === mb_strtolower(trim((string) $submitted))) {
                    $selected = $allowedValue;
                    break;
                }
            }
        }
        $clean[$type] = $selected;
    }
    return $clean;
}

function store_options_hash(array $options): string
{
    if ($options === []) {
        return 'default';
    }
    return sha1(json_encode($options, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function store_options_json(array $options): ?string
{
    if ($options === []) {
        return null;
    }
    return json_encode($options, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function store_options_label(array $options): string
{
    if ($options === []) {
        return '';
    }
    $parts = [];
    foreach ($options as $key => $value) {
        $parts[] = trim((string) $key) . ': ' . trim((string) $value);
    }
    return implode(' · ', $parts);
}



function store_ensure_support_tables(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }

    if (function_exists('store_ensure_email_tables')) {
        store_ensure_email_tables($pdo);
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS support_faqs (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, question VARCHAR(255) NOT NULL, answer TEXT NOT NULL, category VARCHAR(120) NULL, sort_order INT NOT NULL DEFAULT 0, is_active TINYINT(1) NOT NULL DEFAULT 1, created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (id), KEY idx_support_faqs_active_order (is_active, sort_order)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS support_messages (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, user_id BIGINT UNSIGNED NULL, name VARCHAR(160) NOT NULL, email VARCHAR(190) NOT NULL, phone VARCHAR(80) NULL, order_number VARCHAR(80) NULL, subject VARCHAR(190) NULL, message TEXT NOT NULL, status VARCHAR(40) NOT NULL DEFAULT 'new', source_page VARCHAR(120) NULL, admin_note TEXT NULL, read_at DATETIME NULL, created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (id), KEY idx_support_messages_user (user_id), KEY idx_support_messages_status (status), KEY idx_support_messages_created (created_at), KEY idx_support_messages_order (order_number)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $columns = [
        'user_id' => 'ALTER TABLE support_messages ADD COLUMN user_id BIGINT UNSIGNED NULL AFTER id',
        'phone' => 'ALTER TABLE support_messages ADD COLUMN phone VARCHAR(80) NULL AFTER email',
        'order_number' => 'ALTER TABLE support_messages ADD COLUMN order_number VARCHAR(80) NULL AFTER phone',
        'source_page' => 'ALTER TABLE support_messages ADD COLUMN source_page VARCHAR(120) NULL AFTER status',
        'admin_note' => 'ALTER TABLE support_messages ADD COLUMN admin_note TEXT NULL AFTER source_page',
        'read_at' => 'ALTER TABLE support_messages ADD COLUMN read_at DATETIME NULL AFTER admin_note',
    ];
    foreach ($columns as $column => $statement) {
        if (!store_table_has_column($pdo, 'support_messages', $column)) {
            $pdo->exec($statement);
        }
    }

    store_seed_default_support_faqs($pdo);
    $done = true;
}

function store_seed_default_support_faqs(PDO $pdo): void
{
    $count = (int) $pdo->query('SELECT COUNT(*) FROM support_faqs')->fetchColumn();
    if ($count > 0) {
        return;
    }

    $stmt = $pdo->prepare('INSERT INTO support_faqs (question, answer, category, sort_order, is_active) VALUES (:question, :answer, :category, :sort_order, 1)');
    $defaults = [
        ['How long does shipping take?', 'Standard shipping usually takes 3 to 5 business days. Express shipping usually takes 1 to 2 business days when available.', 'Shipping', 10],
        ['What is your return policy?', 'We offer a 30-day return window for eligible products returned in their original condition with included accessories and packaging.', 'Returns', 20],
        ['How do I claim my warranty?', 'Contact support with your order number, product model, and a short description of the issue. Our team will review the request and guide you through the next step.', 'Warranty', 30],
        ['Can I change my shipping address after ordering?', 'Contact support as soon as possible. Address changes are only possible before the order is packed or shipped.', 'Orders', 40],
    ];
    foreach ($defaults as $faq) {
        $stmt->execute([
            'question' => $faq[0],
            'answer' => $faq[1],
            'category' => $faq[2],
            'sort_order' => $faq[3],
        ]);
    }
}

function fetch_public_support_faqs(PDO $pdo, string $search = ''): array
{
    store_ensure_support_tables($pdo);
    $search = mb_substr(trim($search), 0, 120);
    $params = [];
    $where = ['is_active = 1'];
    if ($search !== '') {
        $where[] = '(question LIKE :q OR answer LIKE :q OR category LIKE :q)';
        $params['q'] = '%' . $search . '%';
    }

    $stmt = $pdo->prepare('SELECT id, question, answer, category, sort_order FROM support_faqs WHERE ' . implode(' AND ', $where) . ' ORDER BY sort_order ASC, id ASC LIMIT 80');
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function store_create_support_message(PDO $pdo, array $payload): int
{
    store_ensure_support_tables($pdo);
    $context = current_store_context($pdo);

    $name = mb_substr(trim((string) ($payload['name'] ?? '')), 0, 160);
    $email = mb_substr(trim((string) ($payload['email'] ?? '')), 0, 190);
    $phone = mb_substr(trim((string) ($payload['phone'] ?? '')), 0, 80) ?: null;
    $orderNumber = mb_substr(trim((string) ($payload['order_number'] ?? '')), 0, 80) ?: null;
    $subject = mb_substr(trim((string) ($payload['subject'] ?? '')), 0, 190) ?: 'Support request';
    $message = mb_substr(trim((string) ($payload['message'] ?? '')), 0, 5000);
    $sourcePage = mb_substr(trim((string) ($payload['source_page'] ?? 'support')), 0, 120) ?: 'support';

    if ($name === '' || $email === '' || $message === '') {
        throw new RuntimeException('Please enter your name, email, and message.');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Please enter a valid email address.');
    }
    if (mb_strlen($message) < 10) {
        throw new RuntimeException('Please write a little more detail so support can help you properly.');
    }

    $stmt = $pdo->prepare('INSERT INTO support_messages (user_id, name, email, phone, order_number, subject, message, status, source_page) VALUES (:user_id, :name, :email, :phone, :order_number, :subject, :message, :status, :source_page)');
    $stmt->execute([
        'user_id' => $context['user_id'],
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'order_number' => $orderNumber,
        'subject' => $subject,
        'message' => $message,
        'status' => 'new',
        'source_page' => $sourcePage,
    ]);

    $messageId = (int) $pdo->lastInsertId();
    store_queue_support_emails($pdo, [
        'id' => $messageId,
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'order_number' => $orderNumber ?: '—',
        'subject' => $subject,
        'message' => $message,
    ]);

    return $messageId;
}


function store_order_status_options(): array
{
    return [
        'pending' => [
            'label' => 'Pending',
            'customer_label' => 'Order received',
            'summary' => 'Your order is in the queue and will be reviewed by the Phonix team.',
            'detail' => 'We have received your order and are checking the selected items and delivery details.',
            'icon' => 'pending_actions',
            'tone' => 'info',
        ],
        'processing' => [
            'label' => 'Processing',
            'customer_label' => 'Preparing order',
            'summary' => 'Your order is being prepared for payment confirmation and delivery.',
            'detail' => 'The team is preparing the products, confirming availability, and arranging the next delivery step.',
            'icon' => 'inventory_2',
            'tone' => 'info',
        ],
        'shipped' => [
            'label' => 'Shipped',
            'customer_label' => 'On the way',
            'summary' => 'Your order has left fulfillment and is moving toward delivery.',
            'detail' => 'The order has been handed to the delivery carrier. Use the tracking details when they are available.',
            'icon' => 'local_shipping',
            'tone' => 'warning',
        ],
        'delivered' => [
            'label' => 'Delivered',
            'customer_label' => 'Delivered',
            'summary' => 'Your order has been marked as delivered.',
            'detail' => 'Delivery is complete. Keep your invoice and warranty details for future reference.',
            'icon' => 'check_circle',
            'tone' => 'good',
        ],
        'cancelled' => [
            'label' => 'Cancelled',
            'customer_label' => 'Cancelled',
            'summary' => 'This order has been cancelled.',
            'detail' => 'The order is no longer active. Contact support if you need help placing a new order.',
            'icon' => 'cancel',
            'tone' => 'danger',
        ],
        'refunded' => [
            'label' => 'Refunded',
            'customer_label' => 'Refunded',
            'summary' => 'A refund has been recorded for this order.',
            'detail' => 'The refund status has been recorded. Your bank or payment provider may need additional processing time.',
            'icon' => 'assignment_return',
            'tone' => 'danger',
        ],
    ];
}

function store_payment_status_options(): array
{
    return [
        'unpaid' => [
            'label' => 'Unpaid',
            'customer_label' => 'Payment pending',
            'summary' => 'Payment is still pending or will be collected according to the selected payment method.',
            'tone' => 'warning',
        ],
        'paid' => [
            'label' => 'Paid',
            'customer_label' => 'Paid',
            'summary' => 'Payment has been confirmed for this order.',
            'tone' => 'good',
        ],
        'failed' => [
            'label' => 'Failed',
            'customer_label' => 'Payment failed',
            'summary' => 'Payment could not be completed. Contact support or place a new order if needed.',
            'tone' => 'danger',
        ],
        'refunded' => [
            'label' => 'Refunded',
            'customer_label' => 'Refunded',
            'summary' => 'Payment has been refunded for this order.',
            'tone' => 'danger',
        ],
    ];
}

function store_order_status_values(): array
{
    return array_keys(store_order_status_options());
}

function store_payment_status_values(): array
{
    return array_keys(store_payment_status_options());
}

function store_order_status_option(?string $status): array
{
    $status = strtolower(trim((string) $status));
    $options = store_order_status_options();
    return $options[$status] ?? $options['pending'];
}

function store_payment_status_option(?string $status): array
{
    $status = strtolower(trim((string) $status));
    $options = store_payment_status_options();
    return $options[$status] ?? $options['unpaid'];
}

function store_order_status_label(?string $status, bool $customer = false): string
{
    $option = store_order_status_option($status);
    return (string) ($customer ? ($option['customer_label'] ?? $option['label']) : $option['label']);
}

function store_payment_status_label(?string $status, bool $customer = false): string
{
    $option = store_payment_status_option($status);
    return (string) ($customer ? ($option['customer_label'] ?? $option['label']) : $option['label']);
}

function store_normalize_order_status(?string $status, string $fallback = 'pending'): string
{
    $status = strtolower(trim((string) $status));
    return in_array($status, store_order_status_values(), true) ? $status : $fallback;
}

function store_normalize_payment_status(?string $status, string $fallback = 'unpaid'): string
{
    $status = strtolower(trim((string) $status));
    return in_array($status, store_payment_status_values(), true) ? $status : $fallback;
}

function store_order_public_steps(?string $currentStatus): array
{
    $currentStatus = store_normalize_order_status($currentStatus);
    $steps = ['pending', 'processing', 'shipped', 'delivered'];
    $currentIndex = array_search($currentStatus, $steps, true);
    if ($currentIndex === false) {
        $currentIndex = $currentStatus === 'refunded' ? count($steps) - 1 : 0;
    }

    $result = [];
    foreach ($steps as $index => $status) {
        $option = store_order_status_option($status);
        $result[] = [
            'status' => $status,
            'label' => (string) ($option['customer_label'] ?? $option['label']),
            'icon' => (string) ($option['icon'] ?? 'radio_button_unchecked'),
            'state' => $index < $currentIndex ? 'done' : ($index === $currentIndex ? 'current' : 'upcoming'),
        ];
    }

    if (in_array($currentStatus, ['cancelled', 'refunded'], true)) {
        $option = store_order_status_option($currentStatus);
        $result[] = [
            'status' => $currentStatus,
            'label' => (string) ($option['customer_label'] ?? $option['label']),
            'icon' => (string) ($option['icon'] ?? 'cancel'),
            'state' => 'current',
        ];
    }

    return $result;
}

function store_ensure_checkout_tables(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }

    if (function_exists('store_ensure_email_tables')) {
        store_ensure_email_tables($pdo);
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS shipping_methods (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, code VARCHAR(80) NOT NULL, name VARCHAR(160) NOT NULL, description TEXT NULL, price DECIMAL(10,2) NOT NULL DEFAULT 0.00, free_over DECIMAL(10,2) NULL, eta_min_days INT UNSIGNED NULL, eta_max_days INT UNSIGNED NULL, region_label VARCHAR(190) NULL, sort_order INT NOT NULL DEFAULT 0, is_active TINYINT(1) NOT NULL DEFAULT 1, created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (id), UNIQUE KEY uq_shipping_methods_code (code), KEY idx_shipping_methods_active_order (is_active, sort_order)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS payment_methods (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, code VARCHAR(80) NOT NULL, name VARCHAR(160) NOT NULL, provider VARCHAR(120) NULL, instructions TEXT NULL, manual_followup TINYINT(1) NOT NULL DEFAULT 0, sort_order INT NOT NULL DEFAULT 0, is_active TINYINT(1) NOT NULL DEFAULT 1, created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (id), UNIQUE KEY uq_payment_methods_code (code), KEY idx_payment_methods_active_order (is_active, sort_order)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS order_status_events (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, order_id BIGINT UNSIGNED NOT NULL, status VARCHAR(60) NULL, payment_status VARCHAR(60) NULL, note TEXT NULL, is_customer_visible TINYINT(1) NOT NULL DEFAULT 1, admin_email VARCHAR(190) NULL, created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), KEY idx_order_events_order_created (order_id, created_at), KEY idx_order_events_customer_visible (order_id, is_customer_visible, created_at), CONSTRAINT fk_order_status_events_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    if (!store_table_has_column($pdo, 'order_status_events', 'is_customer_visible')) {
        $pdo->exec('ALTER TABLE order_status_events ADD COLUMN is_customer_visible TINYINT(1) NOT NULL DEFAULT 1 AFTER note');
    }

    $orderColumns = [
        'shipping_method_id' => 'ALTER TABLE orders ADD COLUMN shipping_method_id BIGINT UNSIGNED NULL AFTER status',
        'shipping_method_code' => 'ALTER TABLE orders ADD COLUMN shipping_method_code VARCHAR(80) NULL AFTER shipping_method_id',
        'shipping_method_name' => 'ALTER TABLE orders ADD COLUMN shipping_method_name VARCHAR(160) NULL AFTER shipping_method_code',
        'shipping_total' => 'ALTER TABLE orders ADD COLUMN shipping_total DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER subtotal',
        'discount_total' => 'ALTER TABLE orders ADD COLUMN discount_total DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER shipping_total',
        'tax_total' => 'ALTER TABLE orders ADD COLUMN tax_total DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER discount_total',
        'payment_method_id' => 'ALTER TABLE orders ADD COLUMN payment_method_id BIGINT UNSIGNED NULL AFTER payment_method',
        'payment_method_code' => 'ALTER TABLE orders ADD COLUMN payment_method_code VARCHAR(80) NULL AFTER payment_method_id',
        'payment_method_name' => 'ALTER TABLE orders ADD COLUMN payment_method_name VARCHAR(160) NULL AFTER payment_method_code',
        'coupon_id' => 'ALTER TABLE orders ADD COLUMN coupon_id BIGINT UNSIGNED NULL AFTER payment_method_name',
        'coupon_code' => 'ALTER TABLE orders ADD COLUMN coupon_code VARCHAR(80) NULL AFTER coupon_id',
        'coupon_discount_type' => 'ALTER TABLE orders ADD COLUMN coupon_discount_type VARCHAR(20) NULL AFTER coupon_code',
        'coupon_discount_value' => 'ALTER TABLE orders ADD COLUMN coupon_discount_value DECIMAL(10,2) NULL AFTER coupon_discount_type',
        'tracking_number' => 'ALTER TABLE orders ADD COLUMN tracking_number VARCHAR(190) NULL AFTER payment_status',
        'tracking_carrier' => 'ALTER TABLE orders ADD COLUMN tracking_carrier VARCHAR(120) NULL AFTER tracking_number',
        'tracking_url' => 'ALTER TABLE orders ADD COLUMN tracking_url VARCHAR(500) NULL AFTER tracking_carrier',
        'internal_notes' => 'ALTER TABLE orders ADD COLUMN internal_notes TEXT NULL AFTER notes',
    ];

    foreach ($orderColumns as $column => $statement) {
        if (!store_table_has_column($pdo, 'orders', $column)) {
            $pdo->exec($statement);
        }
    }

    store_ensure_cart_option_columns($pdo);

    store_seed_default_checkout_methods($pdo);
    $done = true;
}

function store_seed_default_checkout_methods(PDO $pdo): void
{
    $shippingCount = (int) $pdo->query('SELECT COUNT(*) FROM shipping_methods')->fetchColumn();
    if ($shippingCount === 0) {
        $stmt = $pdo->prepare('INSERT INTO shipping_methods (code, name, description, price, free_over, eta_min_days, eta_max_days, region_label, sort_order, is_active) VALUES (:code, :name, :description, :price, :free_over, :eta_min_days, :eta_max_days, :region_label, :sort_order, 1)');
        $defaults = [
            ['standard', 'Standard Shipping', 'Reliable standard delivery for most orders.', 0.00, 50.00, 3, 5, 'Domestic / default regions', 10],
            ['express', 'Express Shipping', 'Priority delivery for faster fulfillment.', 14.99, 120.00, 1, 2, 'Domestic / metro regions', 20],
            ['pickup', 'Store Pickup', 'Customer collects the order from store or warehouse.', 0.00, null, 0, 1, 'Local pickup', 30],
        ];
        foreach ($defaults as $method) {
            $stmt->execute([
                'code' => $method[0],
                'name' => $method[1],
                'description' => $method[2],
                'price' => $method[3],
                'free_over' => $method[4],
                'eta_min_days' => $method[5],
                'eta_max_days' => $method[6],
                'region_label' => $method[7],
                'sort_order' => $method[8],
            ]);
        }
    }

    $paymentCount = (int) $pdo->query('SELECT COUNT(*) FROM payment_methods')->fetchColumn();
    if ($paymentCount === 0) {
        $stmt = $pdo->prepare('INSERT INTO payment_methods (code, name, provider, instructions, manual_followup, sort_order, is_active) VALUES (:code, :name, :provider, :instructions, :manual_followup, :sort_order, 1)');
        $defaults = [
            ['cod', 'Cash on Delivery', 'Manual', 'Customer pays when the order is delivered.', 1, 10],
            ['bank_transfer', 'Bank Transfer', 'Manual', 'Share bank transfer instructions after order confirmation.', 1, 20],
            ['card_placeholder', 'Card Payment', 'Payment gateway placeholder', 'Card gateway is prepared as a placeholder and can be connected later.', 0, 30],
        ];
        foreach ($defaults as $method) {
            $stmt->execute([
                'code' => $method[0],
                'name' => $method[1],
                'provider' => $method[2],
                'instructions' => $method[3],
                'manual_followup' => $method[4],
                'sort_order' => $method[5],
            ]);
        }
    }
}

function fetch_active_shipping_methods(PDO $pdo): array
{
    store_ensure_checkout_tables($pdo);
    $stmt = $pdo->query('SELECT * FROM shipping_methods WHERE is_active = 1 ORDER BY sort_order ASC, id ASC');
    return $stmt->fetchAll();
}

function fetch_active_payment_methods(PDO $pdo): array
{
    store_ensure_checkout_tables($pdo);
    $stmt = $pdo->query('SELECT * FROM payment_methods WHERE is_active = 1 ORDER BY sort_order ASC, id ASC');
    return $stmt->fetchAll();
}

function store_find_active_shipping_method(PDO $pdo, int $id): ?array
{
    store_ensure_checkout_tables($pdo);
    $stmt = $pdo->prepare('SELECT * FROM shipping_methods WHERE id = :id AND is_active = 1 LIMIT 1');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function store_find_active_payment_method(PDO $pdo, int $id): ?array
{
    store_ensure_checkout_tables($pdo);
    $stmt = $pdo->prepare('SELECT * FROM payment_methods WHERE id = :id AND is_active = 1 LIMIT 1');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function store_shipping_cost(array $method, float $subtotal): float
{
    $price = max(0.0, (float) ($method['price'] ?? 0));
    $freeOver = $method['free_over'] !== null && $method['free_over'] !== '' ? (float) $method['free_over'] : null;
    if ($freeOver !== null && $freeOver > 0 && $subtotal >= $freeOver) {
        return 0.0;
    }
    return round($price, 2);
}

function store_normalize_coupon_code(?string $code): string
{
    return mb_substr(mb_strtoupper(trim((string) $code)), 0, 80);
}

function store_coupon_discount_amount(array $coupon, float $subtotal): float
{
    $subtotal = max(0.0, round($subtotal, 2));
    $type = (string) ($coupon['discount_type'] ?? 'percent');
    $value = max(0.0, (float) ($coupon['discount_value'] ?? 0));

    if ($subtotal <= 0 || $value <= 0) {
        return 0.0;
    }

    if ($type === 'fixed') {
        return round(min($subtotal, $value), 2);
    }

    $percent = min(100.0, $value);
    return round(min($subtotal, ($subtotal * $percent) / 100), 2);
}

function store_validate_coupon_row(array $coupon, float $subtotal): void
{
    global $siteCurrency;
    $currency = isset($siteCurrency) ? (string) $siteCurrency : 'TRY';
    $minOrder = (float) ($coupon['min_order_total'] ?? 0);
    if ($minOrder > 0 && $subtotal < $minOrder) {
        throw new RuntimeException('This coupon requires a minimum order of ' . format_price($minOrder, $currency) . '.');
    }

    if (store_coupon_discount_amount($coupon, $subtotal) <= 0) {
        throw new RuntimeException('This coupon does not create a valid discount for the current cart.');
    }
}

function store_find_checkout_coupon(PDO $pdo, string $code, bool $lock = false): ?array
{
    $normalized = store_normalize_coupon_code($code);
    if ($normalized === '') {
        return null;
    }

    $sql = "SELECT * FROM coupons
            WHERE UPPER(code) = :code
              AND is_active = 1
              AND (starts_at IS NULL OR starts_at <= NOW())
              AND (ends_at IS NULL OR ends_at >= NOW())
              AND (max_uses IS NULL OR used_count < max_uses)
            LIMIT 1";
    if ($lock) {
        $sql .= ' FOR UPDATE';
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['code' => $normalized]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function store_preview_checkout_coupon(PDO $pdo, ?string $code, float $subtotal): array
{
    $normalized = store_normalize_coupon_code($code);
    if ($normalized === '') {
        return ['code' => '', 'coupon' => null, 'discount' => 0.0];
    }

    $coupon = store_find_checkout_coupon($pdo, $normalized, false);
    if (!$coupon) {
        throw new RuntimeException('Coupon code is invalid, expired, inactive, or fully used.');
    }

    store_validate_coupon_row($coupon, $subtotal);
    return [
        'code' => (string) $coupon['code'],
        'coupon' => $coupon,
        'discount' => store_coupon_discount_amount($coupon, $subtotal),
    ];
}

function store_order_number(): string
{
    return 'PX-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

function store_cart_id(PDO $pdo): ?int
{
    $context = current_store_context($pdo);
    if ($context['user_id']) {
        $stmt = $pdo->prepare('SELECT id FROM carts WHERE user_id = :user_id ORDER BY id ASC LIMIT 1');
        $stmt->execute(['user_id' => $context['user_id']]);
    } else {
        $stmt = $pdo->prepare('SELECT id FROM carts WHERE session_key = :session_key AND user_id IS NULL ORDER BY id ASC LIMIT 1');
        $stmt->execute(['session_key' => $context['session_key']]);
    }
    $id = $stmt->fetchColumn();
    return $id ? (int) $id : null;
}

function store_create_order_from_checkout(PDO $pdo, array $payload): array
{
    store_ensure_checkout_tables($pdo);
    $items = fetch_cart_items($pdo);
    if ($items === []) {
        throw new RuntimeException('Your cart is empty.');
    }

    $shippingMethod = store_find_active_shipping_method($pdo, (int) ($payload['shipping_method_id'] ?? 0));
    $paymentMethod = store_find_active_payment_method($pdo, (int) ($payload['payment_method_id'] ?? 0));
    if (!$shippingMethod) {
        throw new RuntimeException('Please choose an available shipping method.');
    }
    if (!$paymentMethod) {
        throw new RuntimeException('Please choose an available payment method.');
    }

    $summary = cart_summary_from_items($items);
    $subtotal = round((float) $summary['subtotal'], 2);
    $couponCode = store_normalize_coupon_code($payload['coupon_code'] ?? '');
    $taxTotal = 0.0;
    $context = current_store_context($pdo);

    $fullName = mb_substr(trim((string) ($payload['full_name'] ?? '')), 0, 190);
    $email = mb_substr(trim((string) ($payload['email'] ?? '')), 0, 190);
    $phone = mb_substr(trim((string) ($payload['phone'] ?? '')), 0, 60) ?: null;
    $address = mb_substr(trim((string) ($payload['address_line1'] ?? '')), 0, 255);
    $address2 = mb_substr(trim((string) ($payload['address_line2'] ?? '')), 0, 255) ?: null;
    $city = mb_substr(trim((string) ($payload['city'] ?? '')), 0, 120);
    $country = mb_substr(trim((string) ($payload['country'] ?? '')), 0, 120);
    $notes = mb_substr(trim((string) ($payload['notes'] ?? '')), 0, 3000) ?: null;

    if ($fullName === '' || $email === '' || $address === '' || $city === '' || $country === '') {
        throw new RuntimeException('Please complete your shipping contact and address details.');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Please enter a valid email address.');
    }

    $pdo->beginTransaction();
    try {
        foreach ($items as $item) {
            $stmt = $pdo->prepare('SELECT id, name, price, stock FROM products WHERE id = :id AND ' . store_public_product_purchasable_sql('products') . ' LIMIT 1 FOR UPDATE');
            $stmt->execute(['id' => (int) $item['id']]);
            $product = $stmt->fetch();
            if (!$product || (int) $product['stock'] < (int) $item['qty']) {
                throw new RuntimeException('One of the products is no longer available in the requested quantity.');
            }
        }

        $coupon = null;
        $discountTotal = 0.0;
        if ($couponCode !== '') {
            $coupon = store_find_checkout_coupon($pdo, $couponCode, true);
            if (!$coupon) {
                throw new RuntimeException('Coupon code is invalid, expired, inactive, or fully used.');
            }
            store_validate_coupon_row($coupon, $subtotal);
            $discountTotal = store_coupon_discount_amount($coupon, $subtotal);
        }

        $shippingTotal = store_shipping_cost($shippingMethod, $subtotal);
        $total = round(max(0, $subtotal + $shippingTotal + $taxTotal - $discountTotal), 2);

        do {
            $orderNumber = store_order_number();
            $exists = $pdo->prepare('SELECT id FROM orders WHERE order_number = :order_number LIMIT 1');
            $exists->execute(['order_number' => $orderNumber]);
        } while ($exists->fetchColumn());

        $stmt = $pdo->prepare('INSERT INTO orders (user_id, order_number, full_name, email, phone, address_line1, address_line2, city, country, status, shipping_method_id, shipping_method_code, shipping_method_name, payment_method, payment_method_id, payment_method_code, payment_method_name, coupon_id, coupon_code, coupon_discount_type, coupon_discount_value, payment_status, subtotal, shipping_total, discount_total, tax_total, total, notes) VALUES (:user_id, :order_number, :full_name, :email, :phone, :address_line1, :address_line2, :city, :country, :status, :shipping_method_id, :shipping_method_code, :shipping_method_name, :payment_method, :payment_method_id, :payment_method_code, :payment_method_name, :coupon_id, :coupon_code, :coupon_discount_type, :coupon_discount_value, :payment_status, :subtotal, :shipping_total, :discount_total, :tax_total, :total, :notes)');
        $stmt->execute([
            'user_id' => $context['user_id'],
            'order_number' => $orderNumber,
            'full_name' => $fullName,
            'email' => $email,
            'phone' => $phone,
            'address_line1' => $address,
            'address_line2' => $address2,
            'city' => $city,
            'country' => $country,
            'status' => 'pending',
            'shipping_method_id' => (int) $shippingMethod['id'],
            'shipping_method_code' => (string) $shippingMethod['code'],
            'shipping_method_name' => (string) $shippingMethod['name'],
            'payment_method' => (string) $paymentMethod['code'],
            'payment_method_id' => (int) $paymentMethod['id'],
            'payment_method_code' => (string) $paymentMethod['code'],
            'payment_method_name' => (string) $paymentMethod['name'],
            'coupon_id' => $coupon ? (int) $coupon['id'] : null,
            'coupon_code' => $coupon ? (string) $coupon['code'] : null,
            'coupon_discount_type' => $coupon ? (string) $coupon['discount_type'] : null,
            'coupon_discount_value' => $coupon ? (float) $coupon['discount_value'] : null,
            'payment_status' => 'unpaid',
            'subtotal' => $subtotal,
            'shipping_total' => $shippingTotal,
            'discount_total' => $discountTotal,
            'tax_total' => $taxTotal,
            'total' => $total,
            'notes' => $notes,
        ]);
        $orderId = (int) $pdo->lastInsertId();

        if ($coupon) {
            $pdo->prepare('UPDATE coupons SET used_count = used_count + 1 WHERE id = :id LIMIT 1')->execute(['id' => (int) $coupon['id']]);
        }

        $itemStmt = $pdo->prepare('INSERT INTO order_items (order_id, product_id, product_name, unit_price, qty, line_total, selected_options_json) VALUES (:order_id, :product_id, :product_name, :unit_price, :qty, :line_total, :selected_options_json)');
        $stockStmt = $pdo->prepare('UPDATE products SET stock = GREATEST(stock - :qty, 0) WHERE id = :id LIMIT 1');
        foreach ($items as $item) {
            $itemStmt->execute([
                'order_id' => $orderId,
                'product_id' => (int) $item['id'],
                'product_name' => mb_substr((string) $item['name'], 0, 190),
                'unit_price' => (float) $item['price'],
                'qty' => (int) $item['qty'],
                'line_total' => round((float) $item['line_total'], 2),
                'selected_options_json' => store_options_json($item['selected_options'] ?? []),
            ]);
            $stockStmt->execute(['qty' => (int) $item['qty'], 'id' => (int) $item['id']]);
        }

        $cartId = store_cart_id($pdo);
        if ($cartId) {
            $pdo->prepare('DELETE FROM cart_items WHERE cart_id = :cart_id')->execute(['cart_id' => $cartId]);
        }

        $eventStmt = $pdo->prepare('INSERT INTO order_status_events (order_id, status, payment_status, note, is_customer_visible, admin_email) VALUES (:order_id, :status, :payment_status, :note, 1, NULL)');
        $eventStmt->execute([
            'order_id' => $orderId,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'note' => 'Order placed. Shipping: ' . $shippingMethod['name'] . '. Payment: ' . $paymentMethod['name'] . ($coupon ? '. Coupon: ' . $coupon['code'] . ' (-' . format_price($discountTotal, isset($GLOBALS['siteCurrency']) ? (string) $GLOBALS['siteCurrency'] : 'TRY') . ')' : '') . '.',
        ]);

        $pdo->commit();
        store_queue_order_emails($pdo, [
            'id' => $orderId,
            'order_number' => $orderNumber,
            'full_name' => $fullName,
            'email' => $email,
            'total' => $total,
            'shipping_method_name' => (string) $shippingMethod['name'],
            'payment_method_name' => (string) $paymentMethod['name'],
        ]);
        return ['id' => $orderId, 'order_number' => $orderNumber, 'total' => $total];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
