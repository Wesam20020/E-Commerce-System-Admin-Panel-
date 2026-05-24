<?php
function env_value(string $key, $default = null)
{
    $value = getenv($key);
    return $value === false || $value === '' ? $default : $value;
}

function env_bool(string $key, bool $default = false): bool
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        return $default;
    }
    return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
}

function app_config(): array
{
    static $config;
    if ($config === null) {
        $config = require __DIR__ . '/../config.php';
    }
    return $config;
}

function app_env(): string
{
    return (string) (app_config()['app_env'] ?? 'production');
}

function app_debug(): bool
{
    return (bool) (app_config()['app_debug'] ?? false);
}

function app_flag(string $key, bool $default = false): bool
{
    return (bool) (app_config()[$key] ?? $default);
}

function send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }

    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Permitted-Cross-Domain-Policies: none');
    header('Origin-Agent-Cluster: ?1');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
    header_remove('X-Powered-By');
}

function request_expects_json(): bool
{
    $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
    $requestedWith = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
    $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));

    return str_contains($accept, 'application/json')
        || $requestedWith === 'xmlhttprequest'
        || str_contains($contentType, 'application/json');
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function site_url(string $route, array $query = []): string
{
    $routes = [
        'home' => 'index.php',
        'products' => 'products.php',
        'product' => 'product.php',
        'search' => 'search.php',
        'phone_finder' => 'phone-finder.php',
        'deals' => 'deals.php',
        'brands' => 'brands.php',
        'support' => 'support.php',
        'wishlist' => 'wishlist.php',
        'account' => 'account.php',
        'auth' => 'auth.php',
        'checkout' => 'checkout.php',
        'admin' => 'admin.php',
        'new_arrivals' => 'new-arrivals.php',
        'api_store' => 'api/store.php',
        'api_phone_finder' => 'api/phone-finder.php',
    ];

    $path = $routes[$route] ?? $route;
    if ($query === []) {
        return $path;
    }

    return $path . '?' . http_build_query($query);
}

function redirect_to(string $url, int $status = 302): void
{
    header('Location: ' . $url, true, $status);
    exit;
}

function request_method(): string
{
    return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
}

function is_post_request(): bool
{
    return request_method() === 'POST';
}

function base_path(): string
{
    static $path;
    if ($path === null) {
        $path = dirname(__DIR__);
    }
    return $path;
}

function app_secret(): string
{
    $secret = env_value('PHONIX_APP_SECRET', 'change-this-in-production');
    return hash('sha256', $secret . '|' . __FILE__);
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf_token']) || !is_string($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf_or_fail(?string $token): void
{
    $valid = is_string($token)
        && isset($_SESSION['_csrf_token'])
        && is_string($_SESSION['_csrf_token'])
        && hash_equals($_SESSION['_csrf_token'], $token);

    if ($valid) {
        return;
    }

    if (request_expects_json()) {
        json_response([
            'ok' => false,
            'message' => 'Invalid session token. Please refresh and try again.',
        ], 419);
    }

    http_response_code(419);
    exit('Invalid session token. Please refresh and try again.');
}

function flash_set(string $type, string $message): void
{
    $_SESSION['_flash'][] = [
        'type' => $type,
        'message' => $message,
    ];
}

function flash_take_all(): array
{
    $messages = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return is_array($messages) ? $messages : [];
}

function old_input(string $key, string $default = ''): string
{
    if (!isset($_SESSION['_old']) || !is_array($_SESSION['_old'])) {
        return $default;
    }
    return (string) ($_SESSION['_old'][$key] ?? $default);
}

function set_old_input(array $data): void
{
    $_SESSION['_old'] = $data;
}

function clear_old_input(): void
{
    unset($_SESSION['_old']);
}

function old_input_take_all(): array
{
    $old = $_SESSION['_old'] ?? [];
    unset($_SESSION['_old']);
    return is_array($old) ? $old : [];
}

function safe_internal_redirect_target(?string $url, string $fallback): string
{
    $target = trim((string) $url);
    if ($target === '') {
        return $fallback;
    }

    if (preg_match('#^(?:[a-z][a-z0-9+.-]*:)?//#i', $target)) {
        return $fallback;
    }

    if (preg_match('/[\r\n]/', $target)) {
        return $fallback;
    }

    if (str_starts_with($target, '/')) {
        return $fallback;
    }

    if (!preg_match('#^[A-Za-z0-9_./?&=%#:+,-]+$#', $target)) {
        return $fallback;
    }

    return $target;
}

function request_input(): array
{
    static $input;
    if ($input !== null) {
        return $input;
    }

    $input = $_POST;
    $contentType = (string) ($_SERVER['CONTENT_TYPE'] ?? '');
    if ($input === [] && stripos($contentType, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $decoded = json_decode((string) $raw, true);
        if (is_array($decoded)) {
            $input = $decoded;
        }
    }

    return is_array($input) ? $input : [];
}

function json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
