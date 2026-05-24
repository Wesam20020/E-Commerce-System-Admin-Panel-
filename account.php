<?php
require __DIR__ . '/includes/bootstrap.php';

$user = auth_require_user($pdo);
store_ensure_checkout_tables($pdo);

if (is_post_request()) {
    verify_csrf_or_fail($_POST['_csrf'] ?? null);
    $action = (string) ($_POST['form_action'] ?? '');

    if ($action === 'profile') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $address1 = trim((string) ($_POST['address_line1'] ?? ''));
        $address2 = trim((string) ($_POST['address_line2'] ?? ''));
        $city = trim((string) ($_POST['city'] ?? ''));
        $country = trim((string) ($_POST['country'] ?? ''));

        if ($name === '' || mb_strlen($name) < 2) {
            flash_set('error', 'Name must be at least 2 characters.');
            redirect_to(site_url('account'));
        }

        $stmt = $pdo->prepare('UPDATE users SET name = :name, phone = :phone, address_line1 = :address1, address_line2 = :address2, city = :city, country = :country WHERE id = :id');
        $stmt->execute([
            'name' => $name,
            'phone' => $phone !== '' ? $phone : null,
            'address1' => $address1 !== '' ? $address1 : null,
            'address2' => $address2 !== '' ? $address2 : null,
            'city' => $city !== '' ? $city : null,
            'country' => $country !== '' ? $country : null,
            'id' => (int) $user['id'],
        ]);

        flash_set('success', 'Your profile has been updated.');
        redirect_to(site_url('account'));
    }

    if ($action === 'password') {
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['new_password_confirmation'] ?? '');

        if (!password_verify($currentPassword, (string) $user['password_hash'])) {
            flash_set('error', 'Your current password is incorrect.');
            redirect_to(site_url('account') . '#security');
        }
        if (mb_strlen($newPassword) < 8 || !preg_match('/[A-Za-z]/', $newPassword) || !preg_match('/\d/', $newPassword)) {
            flash_set('error', 'New password must be at least 8 characters and include letters and numbers.');
            redirect_to(site_url('account') . '#security');
        }
        if ($newPassword !== $confirmPassword) {
            flash_set('error', 'New password confirmation does not match.');
            redirect_to(site_url('account') . '#security');
        }

        $stmt = $pdo->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id');
        $stmt->execute([
            'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
            'id' => (int) $user['id'],
        ]);
        flash_set('success', 'Your password has been changed.');
        redirect_to(site_url('account') . '#security');
    }

    if ($action === 'logout') {
        auth_logout($pdo);
        session_start();
        flash_set('success', 'You have been signed out.');
        redirect_to(site_url('auth', ['mode' => 'signin']));
    }
}

$user = auth_require_user($pdo);
$flashMessages = flash_take_all();

$wishlistStmt = $pdo->prepare('SELECT COUNT(DISTINCT product_id) FROM wishlist_items WHERE user_id = :user_id');
$wishlistStmt->execute(['user_id' => (int) $user['id']]);
$wishlistCount = (int) $wishlistStmt->fetchColumn();

$orderCountStmt = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE user_id = :user_id');
$orderCountStmt->execute(['user_id' => (int) $user['id']]);
$orderCount = (int) $orderCountStmt->fetchColumn();

$cartCountStmt = $pdo->prepare('SELECT COALESCE(SUM(ci.qty), 0)
    FROM carts c
    INNER JOIN cart_items ci ON ci.cart_id = c.id
    WHERE c.user_id = :user_id');
$cartCountStmt->execute(['user_id' => (int) $user['id']]);
$cartCount = (int) $cartCountStmt->fetchColumn();


$isAdminAccount = store_is_admin_user($pdo, $user);
$adminDashboardMetrics = [];
$adminRecentOrders = [];
$adminStockRisks = [];
$adminQuickLinks = [];

if ($isAdminAccount) {
    $adminDashboardMetrics = [
        'orders' => (int) phonix_account_scalar($pdo, 'SELECT COUNT(*) FROM orders'),
        'revenue' => (float) phonix_account_scalar($pdo, "SELECT COALESCE(SUM(total), 0) FROM orders WHERE payment_status = 'paid'"),
        'products' => (int) phonix_account_scalar($pdo, 'SELECT COUNT(*) FROM products WHERE is_active = 1'),
        'support' => (int) phonix_account_scalar($pdo, "SELECT COUNT(*) FROM support_messages WHERE status IN ('new', 'open')"),
        'low_stock' => (int) phonix_account_scalar($pdo, 'SELECT COUNT(*) FROM products WHERE is_active = 1 AND stock <= 5'),
    ];

    $adminRecentOrders = phonix_account_rows($pdo, 'SELECT order_number, full_name, total, status, payment_status, created_at FROM orders ORDER BY created_at DESC, id DESC LIMIT 4');
    $adminStockRisks = phonix_account_rows($pdo, 'SELECT id, name, brand, stock FROM products WHERE is_active = 1 AND stock <= 5 ORDER BY stock ASC, updated_at DESC LIMIT 4');
    $adminQuickLinks = [
        ['label' => 'Admin home', 'href' => 'admin/index.php', 'icon' => 'dashboard'],
        ['label' => 'Products', 'href' => 'admin/index.php?section=products', 'icon' => 'inventory_2'],
        ['label' => 'Orders', 'href' => 'admin/index.php?section=orders', 'icon' => 'receipt_long'],
        ['label' => 'Support', 'href' => 'admin/index.php?section=support', 'icon' => 'support_agent'],
        ['label' => 'Inventory', 'href' => 'admin/index.php?section=inventory', 'icon' => 'warehouse'],
        ['label' => 'Reports', 'href' => 'admin/index.php?section=reports', 'icon' => 'analytics'],
    ];
}

$recentOrdersStmt = $pdo->prepare("SELECT
        o.id,
        o.order_number,
        o.status,
        o.payment_status,
        o.total,
        o.subtotal,
        o.shipping_total,
        o.discount_total,
        o.tax_total,
        o.created_at,
        o.shipping_method_name,
        o.payment_method_name,
        o.tracking_number,
        o.tracking_carrier,
        o.tracking_url,
        oi.product_name,
        p.image AS product_image
    FROM orders o
    LEFT JOIN order_items oi ON oi.id = (
        SELECT i.id FROM order_items i WHERE i.order_id = o.id ORDER BY i.id ASC LIMIT 1
    )
    LEFT JOIN products p ON p.id = oi.product_id
    WHERE o.user_id = :user_id
    ORDER BY o.created_at DESC, o.id DESC
    LIMIT 5");
$recentOrdersStmt->execute(['user_id' => (int) $user['id']]);
$recentOrders = $recentOrdersStmt->fetchAll();
$recentOrderItems = [];
$recentOrderEvents = [];
foreach ($recentOrders as $order) {
    $orderId = (int) ($order['id'] ?? 0);
    if ($orderId <= 0) {
        continue;
    }
    $itemsStmt = $pdo->prepare('SELECT product_name, qty, unit_price, line_total, selected_options_json FROM order_items WHERE order_id = :order_id ORDER BY id ASC LIMIT 8');
    $itemsStmt->execute(['order_id' => $orderId]);
    $recentOrderItems[$orderId] = $itemsStmt->fetchAll();

    $eventsStmt = $pdo->prepare('SELECT status, payment_status, note, created_at FROM order_status_events WHERE order_id = :order_id AND is_customer_visible = 1 ORDER BY created_at DESC, id DESC LIMIT 6');
    $eventsStmt->execute(['order_id' => $orderId]);
    $recentOrderEvents[$orderId] = $eventsStmt->fetchAll();
}

$displayName = trim((string) ($user['name'] ?? 'Customer'));
$firstName = $displayName !== '' ? explode(' ', $displayName)[0] : 'Customer';
$addressSummary = trim(implode(', ', array_filter([
    (string) ($user['address_line1'] ?? ''),
    (string) ($user['city'] ?? ''),
    (string) ($user['country'] ?? ''),
])));


function phonix_account_scalar(PDO $pdo, string $sql, $fallback = 0)
{
    try {
        $value = $pdo->query($sql)->fetchColumn();
        return $value === false ? $fallback : $value;
    } catch (Throwable $e) {
        error_log('[Phonix account admin metric] ' . $e->getMessage());
        return $fallback;
    }
}

function phonix_account_rows(PDO $pdo, string $sql): array
{
    try {
        $stmt = $pdo->query($sql);
        return $stmt ? $stmt->fetchAll() : [];
    } catch (Throwable $e) {
        error_log('[Phonix account admin rows] ' . $e->getMessage());
        return [];
    }
}

function phonix_account_status_badge(string $status): array
{
    $option = store_order_status_option($status);
    return [
        mb_strtoupper((string) ($option['customer_label'] ?? $option['label'])),
        'account-status-' . (string) ($option['tone'] ?? 'info'),
        (string) ($option['icon'] ?? 'schedule'),
    ];
}

function phonix_account_tracking_href(?string $url): string
{
    $url = trim((string) $url);
    if ($url === '') {
        return '';
    }
    return filter_var($url, FILTER_VALIDATE_URL) ? mb_substr($url, 0, 500) : '';
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title><?= e($siteName) ?> - Account</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@300;400;500;600;700;800&amp;display=swap" rel="stylesheet"/>
    <script>
      tailwind.config = {
        darkMode: 'class',
        theme: {
          extend: {
            colors: {
              'on-primary': '#ffffff',
              'tertiary-fixed-dim': '#bec6e0',
              'surface-bright': '#f9f9fa',
              'tertiary': '#565e74',
              'on-error-container': '#93000a',
              'surface': '#f9f9fa',
              'on-background': '#1a1c1d',
              'on-surface': '#1a1c1d',
              'error': '#ba1a1a',
              'surface-container-low': '#f3f3f4',
              'error-container': '#ffdad6',
              'tertiary-fixed': '#dae2fd',
              'primary-fixed-dim': '#99cbff',
              'surface-container-high': '#e8e8e9',
              'surface-variant': '#e2e2e3',
              'primary-fixed': '#cfe5ff',
              'surface-container-lowest': '#ffffff',
              'surface-tint': '#00629e',
              'tertiary-container': '#969db6',
              'secondary-fixed-dim': '#c0c7cf',
              'inverse-primary': '#99cbff',
              'primary-container': '#5ea3e3',
              'outline': '#717881',
              'secondary-fixed': '#dce3eb',
              'secondary': '#585f66',
              'surface-container': '#eeeeef',
              'surface-container-highest': '#e2e2e3',
              'surface-dim': '#d9dadb',
              'background': '#f9f9fa',
              'outline-variant': '#c0c7d1',
              'on-surface-variant': '#414750',
              'inverse-surface': '#2f3132',
              'primary': '#00629e',
              'secondary-container': '#dce3eb',
              'on-primary-container': '#00385c'
            },
            borderRadius: {
              DEFAULT: '1rem',
              lg: '2rem',
              xl: '3rem',
              full: '9999px'
            },
            spacing: {
              'section-padding': '120px',
              'element-gap': '24px',
              'unit': '8px',
              'gutter': '32px',
              'container-max': '1280px'
            },
            fontFamily: {
              'body-lg': ['Manrope'],
              'label-caps': ['Manrope'],
              'h3': ['Manrope'],
              'body-md': ['Manrope'],
              'h1': ['Manrope'],
              'h2': ['Manrope']
            },
            fontSize: {
              'body-lg': ['18px', {lineHeight: '1.6', fontWeight: '400'}],
              'label-caps': ['12px', {lineHeight: '1', letterSpacing: '0.1em', fontWeight: '700'}],
              'h3': ['24px', {lineHeight: '1.3', fontWeight: '600'}],
              'body-md': ['16px', {lineHeight: '1.6', fontWeight: '400'}],
              'h1': ['64px', {lineHeight: '1.1', letterSpacing: '-0.02em', fontWeight: '700'}],
              'h2': ['40px', {lineHeight: '1.2', letterSpacing: '-0.01em', fontWeight: '600'}]
            }
          }
        }
      }
    </script>
    <style>
      body { background:#f9f9fa; color:#1a1c1d; }
      .material-symbols-outlined { font-variation-settings:'FILL' 0,'wght' 300,'GRAD' 0,'opsz' 24; }
      .glass-card { background:rgba(255,255,255,.58); backdrop-filter:blur(18px); border:1px solid rgba(255,255,255,.7); box-shadow:0 20px 40px rgba(94,163,227,.06); }
      .form-input { width:100%; border:none; border-bottom:1px solid #c0c7d1; background:transparent; padding:.875rem 0; color:#1a1c1d; transition:border-color .2s ease; }
      .form-input:focus { outline:none; border-color:#00629e; box-shadow:none; }

      .account-status-badge { display:inline-flex; align-items:center; gap:.35rem; border-radius:999px; padding:.32rem .7rem; font-size:10px; font-weight:800; letter-spacing:.08em; text-transform:uppercase; }
      .account-status-good { background:rgba(232,245,233,.95); color:#2e7d32; }
      .account-status-info { background:rgba(227,242,253,.95); color:#1565c0; }
      .account-status-warning { background:rgba(255,248,225,.95); color:#f57f17; }
      .account-status-danger { background:rgba(255,218,214,.95); color:#b3261e; }
      .order-detail-panel { border-radius:1.35rem; border:1px solid rgba(192,199,209,.58); background:rgba(255,255,255,.64); }
      .order-detail-panel summary { cursor:pointer; list-style:none; }
      .order-detail-panel summary::-webkit-details-marker { display:none; }
      .order-step-dot { width:2.25rem; height:2.25rem; border-radius:999px; display:flex; align-items:center; justify-content:center; border:1px solid rgba(113,120,129,.25); background:rgba(249,249,250,.95); color:#717881; }
      .order-step-dot.done, .order-step-dot.current { background:#00629e; color:#fff; border-color:#00629e; }
      .order-timeline-item { position:relative; padding-left:1.5rem; }
      .order-timeline-item:before { content:""; position:absolute; left:.35rem; top:.4rem; width:.55rem; height:.55rem; border-radius:999px; background:#00629e; }
      .account-admin-panel { background:linear-gradient(135deg, rgba(9,22,40,.94), rgba(0,98,158,.88)); color:#fff; border:1px solid rgba(255,255,255,.16); box-shadow:0 24px 70px rgba(0,40,80,.18); overflow:hidden; }
      .account-admin-panel:before { content:""; position:absolute; inset:auto -8rem -8rem auto; width:18rem; height:18rem; border-radius:999px; background:rgba(153,203,255,.24); filter:blur(20px); }
      .account-admin-eyebrow { display:inline-flex; align-items:center; gap:.45rem; border-radius:999px; padding:.35rem .75rem; background:rgba(255,255,255,.12); color:#dceeff; font-size:11px; font-weight:800; letter-spacing:.1em; text-transform:uppercase; }
      .account-admin-metric { border-radius:1.35rem; background:rgba(255,255,255,.11); border:1px solid rgba(255,255,255,.16); padding:1rem; min-height:7rem; }
      .account-admin-metric strong { display:block; font-size:1.45rem; line-height:1.15; color:#fff; margin:.35rem 0; }
      .account-admin-metric span { color:rgba(255,255,255,.72); font-size:.78rem; font-weight:700; letter-spacing:.08em; text-transform:uppercase; }
      .account-admin-action { display:flex; align-items:center; justify-content:center; gap:.5rem; border-radius:999px; padding:.72rem .95rem; background:rgba(255,255,255,.13); color:#fff; font-size:.78rem; font-weight:800; letter-spacing:.06em; text-transform:uppercase; border:1px solid rgba(255,255,255,.14); transition:background .2s ease, transform .2s ease; }
      .account-admin-action:hover { background:rgba(255,255,255,.22); transform:translateY(-1px); }
      .account-admin-list { border-radius:1.35rem; background:rgba(255,255,255,.1); border:1px solid rgba(255,255,255,.14); padding:.75rem; }
      .account-admin-row { display:flex; align-items:center; justify-content:space-between; gap:1rem; padding:.82rem .9rem; border-radius:1rem; color:#fff; }
      .account-admin-row + .account-admin-row { border-top:1px solid rgba(255,255,255,.1); }
      .account-admin-row small { display:block; color:rgba(255,255,255,.68); margin-top:.2rem; }
      .account-admin-pill { display:inline-flex; align-items:center; justify-content:center; border-radius:999px; padding:.25rem .58rem; background:rgba(255,255,255,.14); color:#dceeff; font-size:.65rem; font-weight:800; letter-spacing:.08em; text-transform:uppercase; }
      .flash-card { border-radius:1.25rem; padding:1rem 1.125rem; backdrop-filter:blur(12px); border:1px solid rgba(255,255,255,.7); }
      .flash-success { background:rgba(232,245,233,.85); color:#2e7d32; }
      .flash-error { background:rgba(255,218,214,.88); color:#93000a; }
    </style>
<link rel="stylesheet" href="<?= e(site_url('assets/css/top_nav.css')) ?>"/>
</head>
<body class="min-h-screen flex flex-col font-body-md text-body-md">
<?php require __DIR__ . '/includes/top_nav.php'; ?>

<main class="flex-grow max-w-[1280px] mx-auto w-full px-6 md:px-8 py-14 md:py-20 space-y-10">
    <section class="flex flex-col md:flex-row items-start md:items-end justify-between gap-6">
        <div>
            <h1 class="font-h1 text-[42px] md:text-h1 leading-none text-on-background mb-2">Welcome back, <?= e($firstName) ?>.</h1>
            <p class="font-body-lg text-body-lg text-on-surface-variant">Manage your orders, profile, and preferences.</p>
        </div>
        <div class="flex items-center gap-3 flex-wrap">
            <?php if ($isAdminAccount): ?>
                <a class="bg-on-background text-white px-8 py-3 rounded-full font-label-caps text-label-caps hover:opacity-90 transition-opacity shadow-[0_10px_20px_rgba(26,28,29,0.14)] inline-flex items-center gap-2" href="admin/index.php"><span class="material-symbols-outlined text-[18px]">admin_panel_settings</span> ADMIN DASHBOARD</a>
            <?php endif; ?>
            <a class="bg-gradient-to-r from-primary-container to-primary-fixed text-on-primary-container px-8 py-3 rounded-full font-label-caps text-label-caps hover:opacity-90 transition-opacity shadow-[0_10px_20px_rgba(94,163,227,0.2)]" href="<?= e(site_url('new_arrivals')) ?>">SHOP NEW ARRIVALS</a>
        </div>
    </section>

    <?php if ($flashMessages !== []): ?>
        <div class="space-y-3">
            <?php foreach ($flashMessages as $flash): ?>
                <div class="flash-card <?= $flash['type'] === 'success' ? 'flash-success' : 'flash-error' ?>"><?= e($flash['message']) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        <aside class="lg:col-span-3 space-y-2">
            <nav class="glass-card rounded-xl p-6 flex flex-col gap-2">
                <a class="flex items-center gap-4 p-4 rounded-lg bg-[rgba(0,98,158,0.06)] text-primary font-medium transition-colors" href="<?= e(site_url('account')) ?>">
                    <span class="material-symbols-outlined" style="font-variation-settings:'FILL' 1;">dashboard</span>
                    Dashboard Overview
                </a>
                <a class="flex items-center gap-4 p-4 rounded-lg text-on-surface-variant hover:bg-surface-variant/50 transition-colors" href="#orders">
                    <span class="material-symbols-outlined">inventory_2</span>
                    Order History
                </a>
                <a class="flex items-center gap-4 p-4 rounded-lg text-on-surface-variant hover:bg-surface-variant/50 transition-colors" href="#settings">
                    <span class="material-symbols-outlined">person</span>
                    Profile Settings
                </a>
                <a class="flex items-center gap-4 p-4 rounded-lg text-on-surface-variant hover:bg-surface-variant/50 transition-colors" href="#settings">
                    <span class="material-symbols-outlined">location_on</span>
                    Address Book
                </a>
                <a class="flex items-center gap-4 p-4 rounded-lg text-on-surface-variant hover:bg-surface-variant/50 transition-colors" href="<?= e(site_url('checkout')) ?>">
                    <span class="material-symbols-outlined">credit_card</span>
                    Payment & Checkout
                </a>
                <a class="flex items-center gap-4 p-4 rounded-lg text-on-surface-variant hover:bg-surface-variant/50 transition-colors" href="<?= e(site_url('wishlist')) ?>">
                    <span class="material-symbols-outlined">favorite</span>
                    Wishlist (<?= $wishlistCount ?>)
                </a>
                <?php if ($isAdminAccount): ?>
                    <a class="flex items-center gap-4 p-4 rounded-lg bg-on-background text-white hover:opacity-90 transition-opacity" href="#admin-dashboard">
                        <span class="material-symbols-outlined">admin_panel_settings</span>
                        Admin Dashboard
                    </a>
                <?php endif; ?>
                <div class="h-px bg-outline-variant/30 my-4"></div>
                <form method="post" action="<?= e(site_url('account')) ?>">
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="form_action" value="logout">
                    <button class="w-full flex items-center gap-4 p-4 rounded-lg text-error hover:bg-error-container/30 transition-colors text-left" type="submit">
                        <span class="material-symbols-outlined">logout</span>
                        Sign Out
                    </button>
                </form>
            </nav>
        </aside>

        <div class="lg:col-span-9 space-y-8">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-white/60 backdrop-blur-2xl rounded-[32px] p-8 shadow-[0_20px_40px_rgba(94,163,227,0.06)] border border-white relative overflow-hidden group">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-primary-fixed-dim/20 rounded-full blur-3xl -mr-10 -mt-10 transition-transform group-hover:scale-150 duration-700"></div>
                    <div class="flex items-start justify-between relative z-10 gap-4">
                        <div>
                            <h3 class="font-h3 text-h3 text-on-background mb-1">Profile</h3>
                            <p class="font-body-md text-body-md text-on-surface-variant mb-6"><?= e($displayName) ?></p>
                            <div class="space-y-3">
                                <div class="flex items-center gap-3 text-on-surface-variant break-all">
                                    <span class="material-symbols-outlined text-[20px]">mail</span>
                                    <span class="text-sm"><?= e((string) $user['email']) ?></span>
                                </div>
                                <div class="flex items-center gap-3 text-on-surface-variant">
                                    <span class="material-symbols-outlined text-[20px]">phone</span>
                                    <span class="text-sm"><?= e((string) ($user['phone'] ?: 'Add your phone number')) ?></span>
                                </div>
                                <div class="flex items-center gap-3 text-on-surface-variant">
                                    <span class="material-symbols-outlined text-[20px]">favorite</span>
                                    <span class="text-sm"><?= $wishlistCount ?> wishlist item<?= $wishlistCount === 1 ? '' : 's' ?></span>
                                </div>
                            </div>
                        </div>
                        <a class="text-primary hover:bg-primary-container/10 p-2 rounded-full transition-colors" href="#settings" aria-label="Edit profile">
                            <span class="material-symbols-outlined">edit</span>
                        </a>
                    </div>
                </div>

                <div class="bg-white/60 backdrop-blur-2xl rounded-[32px] p-8 shadow-[0_20px_40px_rgba(94,163,227,0.06)] border border-white relative overflow-hidden group">
                    <div class="absolute bottom-0 right-0 w-40 h-40 bg-secondary-fixed-dim/20 rounded-full blur-3xl -mr-10 -mb-10 transition-transform group-hover:scale-150 duration-700"></div>
                    <div class="flex items-start justify-between relative z-10 gap-4">
                        <div>
                            <div class="flex items-center gap-3 mb-1 flex-wrap">
                                <h3 class="font-h3 text-h3 text-on-background">Default Address</h3>
                                <span class="bg-surface-container-high text-on-surface px-2 py-1 rounded-full font-label-caps text-[10px]">HOME</span>
                            </div>
                            <p class="font-body-md text-body-md text-on-surface-variant mb-4">Shipping destination</p>
                            <div class="text-sm text-on-surface-variant leading-relaxed">
                                <?php if ($addressSummary !== ''): ?>
                                    <?= e((string) ($user['address_line1'] ?? '')) ?><br/>
                                    <?php if (!empty($user['address_line2'])): ?><?= e((string) $user['address_line2']) ?><br/><?php endif; ?>
                                    <?= e((string) ($user['city'] ?? '')) ?><?= !empty($user['city']) && !empty($user['country']) ? ', ' : '' ?><?= e((string) ($user['country'] ?? '')) ?>
                                <?php else: ?>
                                    Add your address to speed up checkout and order tracking.
                                <?php endif; ?>
                            </div>
                        </div>
                        <a class="text-primary hover:bg-primary-container/10 p-2 rounded-full transition-colors" href="#settings" aria-label="Edit address">
                            <span class="material-symbols-outlined">edit</span>
                        </a>
                    </div>
                </div>
            </div>

            <?php if ($isAdminAccount): ?>
                <section id="admin-dashboard" class="account-admin-panel rounded-[32px] p-8 md:p-10 relative">
                    <div class="relative z-10 space-y-8">
                        <div class="flex flex-col xl:flex-row xl:items-end justify-between gap-6">
                            <div>
                                <span class="account-admin-eyebrow"><span class="material-symbols-outlined text-[16px]">admin_panel_settings</span> Admin workspace</span>
                                <h2 class="font-h2 text-[34px] md:text-h2 leading-tight mt-4 mb-2">Admin Dashboard</h2>
                                <p class="text-white/72 max-w-2xl">Manage store operations directly from this account page, or open the full admin panel when you need deeper controls.</p>
                            </div>
                            <a class="inline-flex items-center justify-center gap-2 rounded-full bg-white text-on-background px-6 py-3 font-label-caps text-label-caps hover:opacity-90 transition-opacity" href="admin/index.php">
                                <span class="material-symbols-outlined text-[18px]">open_in_new</span> OPEN FULL ADMIN PANEL
                            </a>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-4">
                            <div class="account-admin-metric"><span>Orders</span><strong><?= e((string) ($adminDashboardMetrics['orders'] ?? 0)) ?></strong><small class="text-white/60">All store orders</small></div>
                            <div class="account-admin-metric"><span>Paid revenue</span><strong><?= e(format_price((float) ($adminDashboardMetrics['revenue'] ?? 0), $siteCurrency)) ?></strong><small class="text-white/60">Paid orders only</small></div>
                            <div class="account-admin-metric"><span>Products</span><strong><?= e((string) ($adminDashboardMetrics['products'] ?? 0)) ?></strong><small class="text-white/60">Active catalog</small></div>
                            <div class="account-admin-metric"><span>Support</span><strong><?= e((string) ($adminDashboardMetrics['support'] ?? 0)) ?></strong><small class="text-white/60">Open messages</small></div>
                            <div class="account-admin-metric"><span>Low stock</span><strong><?= e((string) ($adminDashboardMetrics['low_stock'] ?? 0)) ?></strong><small class="text-white/60">Stock ≤ 5</small></div>
                        </div>

                        <div class="grid grid-cols-1 xl:grid-cols-2 gap-5">
                            <div class="account-admin-list">
                                <div class="flex items-center justify-between gap-4 px-3 pb-3">
                                    <strong class="text-white">Recent orders</strong>
                                    <a class="text-sm font-bold text-white/72 hover:text-white" href="admin/index.php?section=orders">Manage</a>
                                </div>
                                <?php if ($adminRecentOrders === []): ?>
                                    <p class="text-white/65 px-3 pb-3 text-sm">No orders yet.</p>
                                <?php else: ?>
                                    <?php foreach ($adminRecentOrders as $adminOrder): ?>
                                        <div class="account-admin-row">
                                            <span><strong><?= e((string) $adminOrder['order_number']) ?></strong><small><?= e((string) $adminOrder['full_name']) ?> · <?= e(date('M d, Y', strtotime((string) $adminOrder['created_at']))) ?></small></span>
                                            <span class="text-right"><strong><?= e(format_price((float) $adminOrder['total'], $siteCurrency)) ?></strong><small><?= e(store_order_status_label((string) $adminOrder['status'], true)) ?></small></span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                            <div class="account-admin-list">
                                <div class="flex items-center justify-between gap-4 px-3 pb-3">
                                    <strong class="text-white">Stock risks</strong>
                                    <a class="text-sm font-bold text-white/72 hover:text-white" href="admin/index.php?section=inventory">Open</a>
                                </div>
                                <?php if ($adminStockRisks === []): ?>
                                    <p class="text-white/65 px-3 pb-3 text-sm">No active products are currently at or below 5 units.</p>
                                <?php else: ?>
                                    <?php foreach ($adminStockRisks as $adminProduct): ?>
                                        <a class="account-admin-row" href="admin/index.php?section=product_edit&amp;id=<?= (int) $adminProduct['id'] ?>">
                                            <span><strong><?= e((string) $adminProduct['name']) ?></strong><small><?= e((string) ($adminProduct['brand'] ?: 'No brand')) ?></small></span>
                                            <span class="account-admin-pill"><?= (int) $adminProduct['stock'] ?> left</span>
                                        </a>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3">
                            <?php foreach ($adminQuickLinks as $adminLink): ?>
                                <a class="account-admin-action" href="<?= e($adminLink['href']) ?>"><span class="material-symbols-outlined text-[18px]"><?= e($adminLink['icon']) ?></span><?= e($adminLink['label']) ?></a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>
            <?php endif; ?>

            <section id="orders" class="bg-white/40 backdrop-blur-xl rounded-[32px] p-8 shadow-[0_20px_40px_rgba(94,163,227,0.05)] border border-white">
                <div class="flex items-center justify-between mb-8 gap-4 flex-wrap">
                    <div>
                        <h2 class="font-h2 text-h2 text-on-background">Recent Orders</h2>
                        <p class="text-sm text-on-surface-variant mt-1"><?= $orderCount ?> total order<?= $orderCount === 1 ? '' : 's' ?></p>
                    </div>
                    <a class="font-label-caps text-label-caps text-primary hover:text-primary-container transition-colors flex items-center gap-1" href="<?= e(site_url('checkout')) ?>">
                        GO TO CHECKOUT <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
                    </a>
                </div>

                <?php if ($recentOrders === []): ?>
                    <div class="rounded-[24px] bg-surface-bright/70 border border-white p-8 text-on-surface-variant">
                        You have no orders yet. Start exploring phones and accessories, and your next purchase will appear here.
                    </div>
                <?php else: ?>
                    <div class="space-y-6">
                        <?php foreach ($recentOrders as $order): ?>
                            <?php
                                $orderId = (int) ($order['id'] ?? 0);
                                [$badgeText, $badgeClass, $badgeIcon] = phonix_account_status_badge((string) ($order['status'] ?? ''));
                                $statusOption = store_order_status_option((string) ($order['status'] ?? 'pending'));
                                $paymentOption = store_payment_status_option((string) ($order['payment_status'] ?? 'unpaid'));
                                $trackingHref = phonix_account_tracking_href($order['tracking_url'] ?? null);
                                $orderItemsForCard = $recentOrderItems[$orderId] ?? [];
                                $orderEventsForCard = $recentOrderEvents[$orderId] ?? [];
                                $shippingLabel = trim((string) ($order['shipping_method_name'] ?? '')) ?: 'Delivery method not set yet';
                                $paymentLabel = trim((string) ($order['payment_method_name'] ?? '')) ?: 'Payment method not set yet';
                            ?>
                            <article class="group p-6 rounded-2xl bg-surface-bright/50 hover:bg-white transition-all duration-300 border border-transparent hover:border-white/50 shadow-sm hover:shadow-[0_10px_30px_rgba(94,163,227,0.08)]">
                                <div class="flex flex-col sm:flex-row items-center gap-6">
                                    <div class="w-24 h-24 rounded-xl bg-surface-container flex-shrink-0 overflow-hidden relative">
                                        <?php if (!empty($order['product_image'])): ?>
                                            <img alt="<?= e((string) ($order['product_name'] ?? 'Ordered product')) ?>" class="w-full h-full object-cover mix-blend-multiply" src="<?= e((string) $order['product_image']) ?>"/>
                                        <?php else: ?>
                                            <div class="w-full h-full flex items-center justify-center text-primary bg-primary-fixed/40">
                                                <span class="material-symbols-outlined text-[34px]">inventory_2</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-grow flex flex-col sm:flex-row justify-between w-full gap-4">
                                        <div>
                                            <div class="flex items-center gap-3 mb-2 flex-wrap">
                                                <span class="font-label-caps text-label-caps text-on-surface-variant">ORDER #<?= e((string) $order['order_number']) ?></span>
                                                <span class="account-status-badge <?= e($badgeClass) ?>">
                                                    <span class="material-symbols-outlined text-[12px]"><?= e($badgeIcon) ?></span> <?= e($badgeText) ?>
                                                </span>
                                            </div>
                                            <h4 class="font-h3 text-body-lg text-on-background mb-1"><?= e((string) ($order['product_name'] ?: 'Order')) ?></h4>
                                            <p class="font-body-md text-sm text-on-surface-variant"><?= e((string) ($statusOption['summary'] ?? 'Your order status is being updated.')) ?></p>
                                            <p class="font-body-md text-xs text-on-surface-variant mt-1">Placed on <?= e(date('M d, Y', strtotime((string) $order['created_at']))) ?> · <?= e(store_payment_status_label((string) ($order['payment_status'] ?? 'unpaid'), true)) ?></p>
                                        </div>
                                        <div class="flex flex-row sm:flex-col items-center sm:items-end justify-between sm:justify-center gap-4">
                                            <span class="font-h3 text-body-lg text-on-background"><?= e(format_price((float) $order['total'], $siteCurrency)) ?></span>
                                            <a class="px-4 py-2 rounded-full border border-outline-variant text-on-surface-variant hover:bg-surface-variant transition-colors font-label-caps text-[10px]" href="<?= e(site_url('products')) ?>">
                                                <?= in_array(strtolower((string) $order['status']), ['delivered'], true) ? 'BUY AGAIN' : 'SHOP MORE' ?>
                                            </a>
                                        </div>
                                    </div>
                                </div>

                                <details class="order-detail-panel mt-5 p-5">
                                    <summary class="flex items-center justify-between gap-4 text-primary font-label-caps text-label-caps">
                                        <span class="flex items-center gap-2"><span class="material-symbols-outlined text-[18px]">manage_search</span> View order status details</span>
                                        <span class="material-symbols-outlined text-[18px]">expand_more</span>
                                    </summary>
                                    <div class="pt-5 space-y-6">
                                        <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                                            <?php foreach (store_order_public_steps((string) ($order['status'] ?? 'pending')) as $step): ?>
                                                <div class="flex items-center gap-3 rounded-2xl bg-white/60 border border-outline-variant/30 p-3">
                                                    <span class="order-step-dot <?= e($step['state']) ?>"><span class="material-symbols-outlined text-[18px]"><?= e($step['icon']) ?></span></span>
                                                    <div><strong class="text-sm text-on-surface"><?= e($step['label']) ?></strong><p class="text-xs text-on-surface-variant"><?= e(ucfirst((string) $step['state'])) ?></p></div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>

                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                                            <div class="rounded-2xl bg-white/55 border border-white p-4"><strong class="block text-on-surface mb-1">Delivery</strong><span class="text-on-surface-variant"><?= e($shippingLabel) ?></span></div>
                                            <div class="rounded-2xl bg-white/55 border border-white p-4"><strong class="block text-on-surface mb-1">Tracking</strong><span class="text-on-surface-variant"><?= e(($order['tracking_carrier'] ?? '') ? 'Carrier: ' . $order['tracking_carrier'] : 'Carrier not assigned yet') ?><?= ($order['tracking_number'] ?? '') ? ' · ' . e('Number: ' . $order['tracking_number']) : '' ?></span><?php if ($trackingHref !== ''): ?><a class="block mt-2 text-primary font-medium" href="<?= e($trackingHref) ?>" target="_blank" rel="noopener">Open tracking link</a><?php endif; ?></div>
                                            <div class="rounded-2xl bg-white/55 border border-white p-4"><strong class="block text-on-surface mb-1">Payment</strong><span class="text-on-surface-variant"><?= e($paymentLabel) ?> · <?= e((string) ($paymentOption['summary'] ?? store_payment_status_label((string) ($order['payment_status'] ?? 'unpaid'), true))) ?></span></div>
                                        </div>

                                        <?php if ($orderItemsForCard !== []): ?>
                                            <div>
                                                <strong class="block text-on-surface mb-3">Items in this order</strong>
                                                <div class="space-y-2">
                                                    <?php foreach ($orderItemsForCard as $item): ?>
                                                        <?php $itemOptionsLabel = store_options_label(store_options_from_json($item['selected_options_json'] ?? null)); ?>
                                                        <div class="flex items-center justify-between gap-4 rounded-2xl bg-white/55 border border-white px-4 py-3 text-sm">
                                                            <span><strong class="text-on-surface"><?= e((string) $item['product_name']) ?></strong><?= $itemOptionsLabel !== '' ? '<small class="block text-on-surface-variant">' . e($itemOptionsLabel) . '</small>' : '' ?></span>
                                                            <span class="text-on-surface-variant">×<?= (int) $item['qty'] ?> · <?= e(format_price((float) $item['line_total'], $siteCurrency)) ?></span>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                        <div>
                                            <strong class="block text-on-surface mb-3">Status timeline</strong>
                                            <?php if ($orderEventsForCard === []): ?>
                                                <p class="text-sm text-on-surface-variant">No public status updates yet. The latest status above is always current.</p>
                                            <?php else: ?>
                                                <div class="space-y-3">
                                                    <?php foreach ($orderEventsForCard as $event): ?>
                                                        <div class="order-timeline-item text-sm">
                                                            <strong class="block text-on-surface"><?= e(store_order_status_label((string) ($event['status'] ?? ''), true)) ?> · <?= e(store_payment_status_label((string) ($event['payment_status'] ?? ''), true)) ?></strong>
                                                            <?php if (!empty($event['note'])): ?><p class="text-on-surface-variant mt-1"><?= nl2br(e((string) $event['note'])) ?></p><?php endif; ?>
                                                            <small class="text-on-surface-variant"><?= e(date('M d, Y H:i', strtotime((string) $event['created_at']))) ?></small>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </details>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <div id="settings" class="grid grid-cols-1 xl:grid-cols-2 gap-6">
                <section class="glass-card rounded-[32px] p-8 md:p-10">
                    <div class="mb-8">
                        <h2 class="font-h3 text-h3 text-on-background mb-2">Profile & Address</h2>
                        <p class="text-on-surface-variant">Update your contact information and default delivery details.</p>
                    </div>
                    <form class="space-y-6" method="post" action="<?= e(site_url('account')) ?>">
                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="form_action" value="profile">

                        <div>
                            <label class="text-label-caps font-label-caps text-on-surface-variant block mb-2" for="name">FULL NAME</label>
                            <input class="form-input" id="name" name="name" type="text" required value="<?= e((string) ($user['name'] ?? '')) ?>">
                        </div>
                        <div>
                            <label class="text-label-caps font-label-caps text-on-surface-variant block mb-2" for="email">EMAIL ADDRESS</label>
                            <input class="form-input opacity-70 cursor-not-allowed" id="email" type="email" disabled value="<?= e((string) ($user['email'] ?? '')) ?>">
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                            <div>
                                <label class="text-label-caps font-label-caps text-on-surface-variant block mb-2" for="phone">PHONE</label>
                                <input class="form-input" id="phone" name="phone" type="text" value="<?= e((string) ($user['phone'] ?? '')) ?>">
                            </div>
                            <div>
                                <label class="text-label-caps font-label-caps text-on-surface-variant block mb-2" for="country">COUNTRY</label>
                                <input class="form-input" id="country" name="country" type="text" value="<?= e((string) ($user['country'] ?? '')) ?>">
                            </div>
                        </div>
                        <div>
                            <label class="text-label-caps font-label-caps text-on-surface-variant block mb-2" for="address_line1">ADDRESS LINE 1</label>
                            <input class="form-input" id="address_line1" name="address_line1" type="text" value="<?= e((string) ($user['address_line1'] ?? '')) ?>">
                        </div>
                        <div>
                            <label class="text-label-caps font-label-caps text-on-surface-variant block mb-2" for="address_line2">ADDRESS LINE 2</label>
                            <input class="form-input" id="address_line2" name="address_line2" type="text" value="<?= e((string) ($user['address_line2'] ?? '')) ?>">
                        </div>
                        <div>
                            <label class="text-label-caps font-label-caps text-on-surface-variant block mb-2" for="city">CITY</label>
                            <input class="form-input" id="city" name="city" type="text" value="<?= e((string) ($user['city'] ?? '')) ?>">
                        </div>
                        <button class="w-full rounded-full bg-primary text-on-primary py-4 text-body-md font-medium hover:opacity-90 transition-opacity flex justify-center items-center gap-2" type="submit">
                            Save Profile
                            <span class="material-symbols-outlined text-[20px]">arrow_forward</span>
                        </button>
                    </form>
                </section>

                <section id="security" class="glass-card rounded-[32px] p-8 md:p-10 relative overflow-hidden">
                    <div class="absolute -right-20 -top-20 w-64 h-64 bg-primary/5 rounded-full blur-3xl"></div>
                    <div class="relative z-10">
                        <div class="mb-8">
                            <h2 class="font-h3 text-h3 text-on-background mb-2">Security</h2>
                            <p class="text-on-surface-variant">Change your password and keep this account secure.</p>
                        </div>
                        <div class="space-y-6 mb-10">
                            <div class="flex gap-4 items-start">
                                <div class="w-10 h-10 rounded-full bg-primary/10 text-primary flex items-center justify-center shrink-0">
                                    <span class="material-symbols-outlined text-[20px]">shield</span>
                                </div>
                                <div>
                                    <h3 class="font-medium text-on-surface mb-1">Real account access</h3>
                                    <p class="text-on-surface-variant text-sm">Your account is connected to live profile, wishlist, and order data.</p>
                                </div>
                            </div>
                            <div class="flex gap-4 items-start">
                                <div class="w-10 h-10 rounded-full bg-primary/10 text-primary flex items-center justify-center shrink-0">
                                    <span class="material-symbols-outlined text-[20px]">sync</span>
                                </div>
                                <div>
                                    <h3 class="font-medium text-on-surface mb-1">Synced experience</h3>
                                    <p class="text-on-surface-variant text-sm">Your wishlist and cart stay tied to this account across sessions.</p>
                                </div>
                            </div>
                            <div class="flex gap-4 items-start">
                                <div class="w-10 h-10 rounded-full bg-primary/10 text-primary flex items-center justify-center shrink-0">
                                    <span class="material-symbols-outlined text-[20px]">history</span>
                                </div>
                                <div>
                                    <h3 class="font-medium text-on-surface mb-1">Order history</h3>
                                    <p class="text-on-surface-variant text-sm">You currently have <?= $orderCount ?> order<?= $orderCount === 1 ? '' : 's' ?> saved on this account.</p>
                                </div>
                            </div>
                        </div>
                        <form class="space-y-6" method="post" action="<?= e(site_url('account')) ?>">
                            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="form_action" value="password">
                            <div>
                                <label class="text-label-caps font-label-caps text-on-surface-variant block mb-2" for="current_password">CURRENT PASSWORD</label>
                                <input class="form-input" id="current_password" name="current_password" type="password" autocomplete="current-password" required>
                            </div>
                            <div>
                                <label class="text-label-caps font-label-caps text-on-surface-variant block mb-2" for="new_password">NEW PASSWORD</label>
                                <input class="form-input" id="new_password" name="new_password" type="password" autocomplete="new-password" required>
                            </div>
                            <div>
                                <label class="text-label-caps font-label-caps text-on-surface-variant block mb-2" for="new_password_confirmation">CONFIRM PASSWORD</label>
                                <input class="form-input" id="new_password_confirmation" name="new_password_confirmation" type="password" autocomplete="new-password" required>
                            </div>
                            <button class="w-full rounded-full border border-primary text-primary py-4 text-body-md font-medium hover:bg-primary/5 transition-colors flex justify-center items-center gap-2" type="submit">
                                Update Password
                            </button>
                        </form>
                    </div>
                </section>
            </div>
        </div>
    </div>
</main>

<footer class="w-full pt-16 pb-12 bg-slate-50 border-t border-slate-100">
    <div class="max-w-7xl mx-auto px-6 md:px-12 grid grid-cols-1 md:grid-cols-4 gap-12">
        <div>
            <a class="text-xl font-bold text-slate-900 mb-4 block hover:translate-x-1 transition-transform duration-200 hover:opacity-70" href="<?= e(site_url('home')) ?>"><?= e($siteName) ?></a>
            <p class="text-sm text-slate-500">© <?= date('Y') ?> <?= e($siteName) ?> Electronics. <?= e(store_setting($siteSettings ?? [], 'footer_tagline', 'Official phones, trusted warranty, fast delivery across Turkey.')) ?></p>
        </div>
        <div class="flex flex-col gap-3">
            <a class="text-sm text-slate-500 hover:text-blue-500 transition-colors hover:translate-x-1 duration-200 block w-fit" href="<?= e(site_url('support')) ?>">Support</a>
            <a class="text-sm text-slate-500 hover:text-blue-500 transition-colors hover:translate-x-1 duration-200 block w-fit" href="<?= e(site_url('wishlist')) ?>">Wishlist</a>
            <a class="text-sm text-slate-500 hover:text-blue-500 transition-colors hover:translate-x-1 duration-200 block w-fit" href="<?= e(site_url('checkout')) ?>">Checkout</a>
            <a class="text-sm text-slate-500 hover:text-blue-500 transition-colors hover:translate-x-1 duration-200 block w-fit" href="<?= e(site_url('new_arrivals')) ?>">New Arrivals</a>
            <a class="text-sm text-slate-500 hover:text-blue-500 transition-colors hover:translate-x-1 duration-200 block w-fit" href="<?= e(site_url('brands')) ?>">Brands</a>
        </div>
        <div class="md:col-span-2"></div>
    </div>
</footer>
</body>
</html>
