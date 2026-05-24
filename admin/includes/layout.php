<?php
require_once __DIR__ . '/common.php';

function admin_nav_items(): array
{
    return [
        'index' => ['label' => 'Dashboard', 'icon' => 'dashboard', 'page' => 'index'],
        'notifications' => ['label' => 'Activity Center', 'icon' => 'notifications_active', 'page' => 'notifications'],
        'system_health' => ['label' => 'System Health', 'icon' => 'health_and_safety', 'page' => 'system_health'],
        'maintenance_tools' => ['label' => 'Maintenance Tools', 'icon' => 'build_circle', 'page' => 'maintenance_tools'],
        'email' => ['label' => 'Email Center', 'icon' => 'mark_email_unread', 'page' => 'email'],
        'homepage' => ['label' => 'Homepage', 'icon' => 'web', 'page' => 'homepage'],
        'deals' => ['label' => 'Deals', 'icon' => 'campaign', 'page' => 'deals'],
        'seo' => ['label' => 'SEO / Pages', 'icon' => 'travel_explore', 'page' => 'seo'],
        'products' => ['label' => 'Products', 'icon' => 'inventory_2', 'page' => 'products'],
        'product_requests' => ['label' => 'Product Requests', 'icon' => 'add_shopping_cart', 'page' => 'product_requests'],
        'inventory' => ['label' => 'Inventory', 'icon' => 'warehouse', 'page' => 'inventory'],
        'media' => ['label' => 'Media Library', 'icon' => 'photo_library', 'page' => 'media'],
        'categories' => ['label' => 'Categories', 'icon' => 'category', 'page' => 'categories'],
        'brands' => ['label' => 'Brands', 'icon' => 'sell', 'page' => 'brands'],
        'orders' => ['label' => 'Orders', 'icon' => 'receipt_long', 'page' => 'orders'],
        'customers' => ['label' => 'Customers', 'icon' => 'group', 'page' => 'customers'],
        'coupons' => ['label' => 'Coupons', 'icon' => 'local_offer', 'page' => 'coupons'],
        'shipping_payments' => ['label' => 'Shipping / Payments', 'icon' => 'local_shipping', 'page' => 'shipping_payments'],
        'support' => ['label' => 'Support / FAQ', 'icon' => 'support_agent', 'page' => 'support'],
        'reports' => ['label' => 'Reports', 'icon' => 'analytics', 'page' => 'reports'],
        'exports' => ['label' => 'Exports', 'icon' => 'download', 'page' => 'exports'],
        'settings' => ['label' => 'Settings', 'icon' => 'settings', 'page' => 'settings'],
        'admin_users' => ['label' => 'Admin Users', 'icon' => 'admin_panel_settings', 'page' => 'admin_users'],
    ];
}

function admin_primary_nav_keys(): array
{
    return ['index', 'products', 'product_requests', 'orders', 'inventory', 'support', 'reports'];
}

function admin_render_side_nav(string $active): void
{
    $active = admin_normalize_section($active);
    $items = admin_nav_items();
    $primaryKeys = admin_primary_nav_keys();
    $primary = [];
    $secondary = [];
    foreach ($items as $key => $item) {
        if (!admin_user_can_section($GLOBALS['pdo'], $key, $GLOBALS['currentUser'] ?? null)) {
            continue;
        }
        if (in_array($key, $primaryKeys, true)) {
            $primary[$key] = $item;
        } else {
            $secondary[$key] = $item;
        }
    }
    $secondaryOpen = array_key_exists($active, $secondary);
    ?>
            <nav class="admin-side-nav" aria-label="Admin navigation">
                <?php foreach ($primary as $key => $item): ?>
                    <a class="<?= $active === $key ? 'is-active' : '' ?>" href="<?= e(admin_page_url($item['page'])) ?>" data-admin-section-link="<?= e($key) ?>">
                        <span class="material-symbols-outlined"><?= e($item['icon']) ?></span>
                        <span><?= e($item['label']) ?></span>
                    </a>
                <?php endforeach; ?>
                <?php if ($secondary): ?>
                    <details class="admin-side-more<?= $secondaryOpen ? ' is-active' : '' ?>" <?= $secondaryOpen ? 'open' : '' ?>>
                        <summary><span class="material-symbols-outlined">apps</span><span>More tools</span><span class="material-symbols-outlined admin-side-more-arrow">expand_more</span></summary>
                        <div>
                            <?php foreach ($secondary as $key => $item): ?>
                                <a class="<?= $active === $key ? 'is-active' : '' ?>" href="<?= e(admin_page_url($item['page'])) ?>" data-admin-section-link="<?= e($key) ?>">
                                    <span class="material-symbols-outlined"><?= e($item['icon']) ?></span>
                                    <span><?= e($item['label']) ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </details>
                <?php endif; ?>
            </nav>
    <?php
}


function admin_logout_form(string $class = 'admin-logout-form', string $label = 'Sign out'): void
{
    ?>
    <form class="<?= e($class) ?>" method="post" action="<?= e(admin_root_url('auth.php')) ?>">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="form_action" value="logout">
        <button class="admin-logout-btn" type="submit"><span class="material-symbols-outlined">logout</span><span><?= e($label) ?></span></button>
    </form>
    <?php
}

function admin_header_quick_actions(): void
{
    ?>
                <div class="admin-page-actions admin-page-actions-compact">
                    <a class="admin-ghost-btn" href="<?= e(admin_root_url('index.php')) ?>"><span class="material-symbols-outlined">storefront</span><span>Storefront</span></a>
                    <a class="admin-primary-btn" href="<?= e(admin_page_url('product_edit')) ?>"><span class="material-symbols-outlined">add_box</span><span>Add product</span></a>
                    <button class="admin-ghost-btn admin-density-toggle" type="button" data-admin-compact-toggle aria-pressed="false"><span class="material-symbols-outlined">density_medium</span><span>Compact</span></button>
                    <?php admin_logout_form('admin-logout-form admin-header-logout', 'Sign out'); ?>
                </div>
    <?php
}
function admin_render_flash_stack(): void
{
    global $flashMessages;
    if (empty($flashMessages)) {
        return;
    }
    ?>
    <div class="admin-flash-stack">
        <?php foreach ($flashMessages as $message): ?>
            <div class="admin-flash <?= e((string) ($message['type'] ?? 'info')) ?>"><?= e((string) ($message['message'] ?? '')) ?></div>
        <?php endforeach; ?>
    </div>
    <?php
}

function admin_header(string $title, string $subtitle, string $active = 'index'): void
{
    global $siteName, $currentUser;
    $displayName = trim((string) ($currentUser['name'] ?? 'Admin')) ?: 'Admin';
    if (admin_is_ajax_section()) {
        ?>
        <div class="admin-section-fragment" data-admin-active="<?= e(admin_normalize_section($active)) ?>" data-admin-title="<?= e($title) ?>">
            <header class="admin-page-header glass-panel">
                <div>
                    <p class="admin-eyebrow">Admin Console</p>
                    <h1><?= e($title) ?></h1>
                    <p><?= e($subtitle) ?></p>
                </div>
                <?php admin_header_quick_actions(); ?>
            </header>
            <?php admin_render_flash_stack(); ?>
        <?php
        return;
    }
    ?><!doctype html>
<html lang="en" class="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?> | <?= e($siteName) ?> Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(admin_asset_url('assets/css/admin.css')) ?>">
</head>
<body class="admin-body">
<section class="admin-console admin-console-split" data-admin-version="<?= e(admin_build_version()) ?>" data-admin-version-url="<?= e(admin_ajax_url('index', ['admin_asset_check' => '1'])) ?>">
    <div class="admin-mobile-bar glass-panel">
        <button type="button" class="admin-menu-toggle" data-admin-menu-toggle aria-controls="adminSidebar" aria-expanded="false">
            <span class="material-symbols-outlined">menu</span><span>Menu</span>
        </button>
        <div class="admin-mobile-brand"><strong>Phonix Admin</strong><span><?= e($displayName) ?></span></div>
        <a class="admin-mobile-storefront" href="<?= e(admin_root_url('index.php')) ?>"><span class="material-symbols-outlined">storefront</span><span>Store</span></a>
        <?php admin_logout_form('admin-logout-form admin-mobile-logout', 'Logout'); ?>
    </div>
    <div class="admin-mobile-overlay" data-admin-menu-close hidden></div>
    <div class="admin-shell">
        <aside class="admin-sidebar-panel glass-panel" id="adminSidebar">
            <button type="button" class="admin-sidebar-close" data-admin-menu-close aria-label="Close admin menu"><span class="material-symbols-outlined">close</span></button>
            <div class="admin-sidebar-head">
                <div class="admin-sidebar-logo"><span class="material-symbols-outlined">bolt</span></div>
                <div><strong>Phonix Admin</strong><span><?= e($displayName) ?></span></div>
            </div>
            <?php admin_render_side_nav($active); ?>
            <div class="admin-sidebar-footer"><?php admin_logout_form('admin-logout-form admin-sidebar-logout', 'Sign out'); ?></div>
        </aside>
        <main class="admin-main-panel">
            <header class="admin-page-header glass-panel">
                <div>
                    <p class="admin-eyebrow">Admin Console</p>
                    <h1><?= e($title) ?></h1>
                    <p><?= e($subtitle) ?></p>
                </div>
                <?php admin_header_quick_actions(); ?>
            </header>
            <?php admin_render_flash_stack(); ?>
<?php
}

function admin_footer(): void
{
    if (admin_is_ajax_section()) {
        ?>
        </div>
        <?php
        return;
    }
    ?>
        </main>
    </div>
</section>
<script src="<?= e(admin_asset_url('assets/js/admin.js')) ?>" defer></script>
</body>
</html>
<?php
}

function admin_shell(string $initialSection = 'index'): void
{
    global $siteName, $currentUser;
    $initialSection = admin_normalize_section($initialSection);
    $displayName = trim((string) ($currentUser['name'] ?? 'Admin')) ?: 'Admin';
    ?><!doctype html>
<html lang="en" class="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Console | <?= e($siteName) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(admin_asset_url('assets/css/admin.css')) ?>">
</head>
<body class="admin-body">
<section class="admin-console admin-console-split admin-console-ajax" data-admin-version="<?= e(admin_build_version()) ?>" data-admin-version-url="<?= e(admin_ajax_url('index', ['admin_asset_check' => '1'])) ?>" data-admin-section-url="ajax.php" data-admin-initial-section="<?= e($initialSection) ?>">
    <div class="admin-mobile-bar glass-panel">
        <button type="button" class="admin-menu-toggle" data-admin-menu-toggle aria-controls="adminSidebar" aria-expanded="false">
            <span class="material-symbols-outlined">menu</span><span>Menu</span>
        </button>
        <div class="admin-mobile-brand"><strong>Phonix Admin</strong><span><?= e($displayName) ?></span></div>
        <a class="admin-mobile-storefront" href="<?= e(admin_root_url('index.php')) ?>"><span class="material-symbols-outlined">storefront</span><span>Store</span></a>
        <?php admin_logout_form('admin-logout-form admin-mobile-logout', 'Logout'); ?>
    </div>
    <div class="admin-mobile-overlay" data-admin-menu-close hidden></div>
    <div class="admin-shell">
        <aside class="admin-sidebar-panel glass-panel" id="adminSidebar">
            <button type="button" class="admin-sidebar-close" data-admin-menu-close aria-label="Close admin menu"><span class="material-symbols-outlined">close</span></button>
            <div class="admin-sidebar-head">
                <div class="admin-sidebar-logo"><span class="material-symbols-outlined">bolt</span></div>
                <div><strong>Phonix Admin</strong><span><?= e($displayName) ?></span></div>
            </div>
            <?php admin_render_side_nav($initialSection); ?>
            <div class="admin-sidebar-footer"><?php admin_logout_form('admin-logout-form admin-sidebar-logout', 'Sign out'); ?></div>
        </aside>
        <main class="admin-main-panel" id="adminContent" data-admin-content aria-live="polite">
            <div class="admin-loading-card glass-panel"><span class="material-symbols-outlined">progress_activity</span><strong>Loading admin section...</strong></div>
        </main>
    </div>
</section>
<script src="<?= e(admin_asset_url('assets/js/admin.js')) ?>" defer></script>
</body>
</html>
<?php
}

function admin_metric_card(string $label, string $value, string $icon, string $note = ''): void
{
    ?>
    <article class="admin-metric-card glass-panel">
        <span class="material-symbols-outlined"><?= e($icon) ?></span>
        <div><p><?= e($label) ?></p><strong><?= e($value) ?></strong><?php if ($note !== ''): ?><small><?= e($note) ?></small><?php endif; ?></div>
    </article>
    <?php
}

function admin_empty_state(string $title, string $body): void
{
    ?>
    <div class="admin-empty-state">
        <span class="material-symbols-outlined">inbox</span>
        <strong><?= e($title) ?></strong>
        <p><?= e($body) ?></p>
    </div>
    <?php
}
