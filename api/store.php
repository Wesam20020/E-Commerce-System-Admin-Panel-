<?php
require __DIR__ . '/../includes/bootstrap.php';

function current_store_state(PDO $pdo, string $currency): array
{
    $currentUser = auth_current_user($pdo);
    $cartItems = fetch_cart_items($pdo);
    $wishlistItems = fetch_wishlist_items($pdo);
    $cart = cart_summary_from_items($cartItems);

    return [
        'cart' => [
            'items' => array_map(static function (array $item) use ($currency): array {
                $item['price_formatted'] = format_price($item['price'], $currency);
                $item['line_total_formatted'] = format_price($item['line_total'], $currency);
                $item['selected_options'] = $item['selected_options'] ?? [];
                $item['selected_options_label'] = $item['selected_options_label'] ?? '';
                $item['selected_options_hash'] = $item['selected_options_hash'] ?? 'default';
                return $item;
            }, $cart['items']),
            'count' => $cart['count'],
            'subtotal' => $cart['subtotal'],
            'subtotal_formatted' => format_price($cart['subtotal'], $currency),
        ],
        'wishlist' => [
            'items' => array_map(static function (array $item) use ($currency): array {
                $item['price_formatted'] = format_price($item['price'], $currency);
                return $item;
            }, $wishlistItems),
            'ids' => array_values(array_map(static fn(array $item): int => (int) $item['id'], $wishlistItems)),
            'count' => count($wishlistItems),
        ],
        'auth' => [
            'logged_in' => $currentUser !== null,
        ],
    ];
}

if (request_method() === 'GET') {
    json_response(['ok' => true, 'state' => current_store_state($pdo, $siteCurrency)]);
}

if (!is_post_request()) {
    json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

$input = request_input();
verify_csrf_or_fail($input['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null));
$action = (string) ($input['action'] ?? '');
$message = 'Store updated.';

switch ($action) {
    case 'add_cart':
        $productId = (int) ($input['product_id'] ?? 0);
        $qty = max(1, (int) ($input['qty'] ?? 1));
        $selectedOptions = $input['selected_options'] ?? ($input['options'] ?? []);
        if ($productId <= 0 || !add_product_to_cart($pdo, $productId, $qty, $selectedOptions)) {
            json_response(['ok' => false, 'message' => 'Could not add this item to the cart.'], 422);
        }
        $message = 'Item added to cart.';
        break;

    case 'update_cart':
        $productId = (int) ($input['product_id'] ?? 0);
        $qty = (int) ($input['qty'] ?? 1);
        $optionHash = isset($input['option_hash']) ? (string) $input['option_hash'] : null;
        $selectedOptions = $input['selected_options'] ?? null;
        if ($productId <= 0 || !update_cart_item_qty($pdo, $productId, $qty, $selectedOptions, $optionHash)) {
            json_response(['ok' => false, 'message' => 'Could not update the cart item.'], 422);
        }
        $message = $qty <= 0 ? 'Item removed from cart.' : 'Cart updated.';
        break;

    case 'remove_cart':
        $productId = (int) ($input['product_id'] ?? 0);
        $optionHash = isset($input['option_hash']) ? (string) $input['option_hash'] : null;
        $selectedOptions = $input['selected_options'] ?? null;
        if ($productId <= 0 || !remove_cart_item($pdo, $productId, $selectedOptions, $optionHash)) {
            json_response(['ok' => false, 'message' => 'Could not remove the item from the cart.'], 422);
        }
        $message = 'Item removed from cart.';
        break;

    case 'toggle_wishlist':
        $productId = (int) ($input['product_id'] ?? 0);
        if ($productId <= 0) {
            json_response(['ok' => false, 'message' => 'Invalid wishlist item.'], 422);
        }
        $result = toggle_wishlist_item($pdo, $productId);
        if ($result === 'missing') {
            json_response(['ok' => false, 'message' => 'This product is no longer available.'], 404);
        }
        $message = $result === 'added' ? 'Saved to wishlist.' : 'Removed from wishlist.';
        break;

    case 'move_wishlist_to_cart':
        $productId = (int) ($input['product_id'] ?? 0);
        if ($productId <= 0 || !add_product_to_cart($pdo, $productId, 1)) {
            json_response(['ok' => false, 'message' => 'Could not move this item to the cart.'], 422);
        }
        $result = toggle_wishlist_item($pdo, $productId);
        if ($result === 'added') {
            toggle_wishlist_item($pdo, $productId);
        }
        $message = 'Moved to cart.';
        break;

    case 'sync_client_state':
        $cart = is_array($input['cart'] ?? null) ? $input['cart'] : [];
        $wishlist = is_array($input['wishlist'] ?? null) ? $input['wishlist'] : [];
        sync_legacy_store_state($pdo, $cart, $wishlist);
        $message = 'Saved your existing cart and wishlist.';
        break;

    default:
        json_response(['ok' => false, 'message' => 'Unknown store action.'], 422);
}

json_response([
    'ok' => true,
    'message' => $message,
    'state' => current_store_state($pdo, $siteCurrency),
]);
