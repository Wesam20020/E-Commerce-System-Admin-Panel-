<?php
require_once __DIR__ . '/helpers.php';

send_security_headers();

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? null) === '443');

    session_set_cookie_params([
        'httponly' => true,
        'secure' => $https,
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/store_queries.php';

$siteSettings = fetch_site_settings($pdo);
$siteName = store_setting($siteSettings, 'site_name', 'Phonix');
$siteCurrency = store_setting($siteSettings, 'site_currency', 'TRY');
$topCategories = fetch_categories_with_counts($pdo);
$currentUser = auth_current_user($pdo);
if ($currentUser) {
    store_claim_guest_state_to_user($pdo, (int) $currentUser['id'], auth_guest_session_key());
}

$maintenanceMode = store_setting_bool($siteSettings, 'maintenance_mode', false);
$maintenanceBypass = store_request_is_admin_area() || store_request_is_auth_area() || store_is_admin_user($pdo, $currentUser);
if ($maintenanceMode && !$maintenanceBypass) {
    if (store_request_is_api_area()) {
        json_response(['ok' => false, 'message' => 'Store maintenance is currently active.'], 503);
    }
    http_response_code(503);
    header('Retry-After: 1800');
    require __DIR__ . '/maintenance_page.php';
    exit;
}

$flashMessages = flash_take_all();
$oldInput = old_input_take_all();
