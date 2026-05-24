<?php
require __DIR__ . '/includes/bootstrap.php';

$pageTitle = $siteName . ' | Secure Checkout';
$pageDescription = 'Secure checkout review for your live Phonix cart.';
$currentPage = 'checkout';
$checkoutShippingInfoText = store_setting($siteSettings, 'shipping_info_text', 'Selected shipping and payment are saved directly into the order.');
$cartItems = fetch_cart_items($pdo);
$cart = cart_summary_from_items($cartItems);

$fullName = trim((string) ($currentUser['name'] ?? ''));
$firstName = '';
$lastName = '';
if ($fullName !== '') {
    $parts = preg_split('/\s+/', $fullName, 2);
    $firstName = $parts[0] ?? '';
    $lastName = $parts[1] ?? '';
}
$addressLine = trim((string) ($currentUser['address_line1'] ?? ''));
if ($addressLine === '' && !empty($currentUser['address_line2'])) {
    $addressLine = trim((string) $currentUser['address_line2']);
}
$city = trim((string) ($currentUser['city'] ?? ''));
$country = trim((string) ($currentUser['country'] ?? '')) ?: 'Türkiye';

store_ensure_checkout_tables($pdo);
$shippingMethods = fetch_active_shipping_methods($pdo);
$paymentMethods = fetch_active_payment_methods($pdo);
$checkoutErrors = [];
$checkoutSuccessOrder = null;

$post = is_post_request() ? $_POST : [];
$selectedShippingId = (int) ($post['shipping_method_id'] ?? ($shippingMethods[0]['id'] ?? 0));
$selectedPaymentId = (int) ($post['payment_method_id'] ?? ($paymentMethods[0]['id'] ?? 0));
$selectedShippingMethod = $selectedShippingId > 0 ? store_find_active_shipping_method($pdo, $selectedShippingId) : null;
$selectedPaymentMethod = $selectedPaymentId > 0 ? store_find_active_payment_method($pdo, $selectedPaymentId) : null;

$checkoutFullName = trim((string) ($post['full_name'] ?? $fullName));
$checkoutEmail = trim((string) ($post['email'] ?? ($currentUser['email'] ?? '')));
$checkoutPhone = trim((string) ($post['phone'] ?? ($currentUser['phone'] ?? '')));
$checkoutAddress = trim((string) ($post['address_line1'] ?? $addressLine));
$checkoutAddress2 = trim((string) ($post['address_line2'] ?? ($currentUser['address_line2'] ?? '')));
$checkoutCity = trim((string) ($post['city'] ?? $city));
$checkoutCountry = trim((string) ($post['country'] ?? $country));
$checkoutNotes = trim((string) ($post['notes'] ?? ''));
$couponCode = store_normalize_coupon_code($post['coupon_code'] ?? ($_GET['coupon'] ?? ''));
$couponPreview = null;
$couponNotice = '';

if (is_post_request()) {
    try {
        verify_csrf_or_fail($_POST['_csrf'] ?? null);
        if ($cart['items'] === []) {
            throw new RuntimeException('Your cart is empty.');
        }
        if ($shippingMethods === [] || $paymentMethods === []) {
            throw new RuntimeException('Checkout is temporarily unavailable. Please contact support or try again later.');
        }

        $checkoutAction = (string) ($_POST['checkout_action'] ?? 'place_order');
        if ($checkoutAction === 'preview_coupon') {
            if ($couponCode === '') {
                throw new RuntimeException('Enter a coupon code first.');
            }
            $couponPreview = store_preview_checkout_coupon($pdo, $couponCode, (float) $cart['subtotal']);
            $couponNotice = 'Coupon ' . $couponPreview['code'] . ' is ready to apply.';
        } else {
            $checkoutSuccessOrder = store_create_order_from_checkout($pdo, [
                'shipping_method_id' => $selectedShippingId,
                'payment_method_id' => $selectedPaymentId,
                'coupon_code' => $couponCode,
                'full_name' => $checkoutFullName,
                'email' => $checkoutEmail,
                'phone' => $checkoutPhone,
                'address_line1' => $checkoutAddress,
                'address_line2' => $checkoutAddress2,
                'city' => $checkoutCity,
                'country' => $checkoutCountry,
                'notes' => $checkoutNotes,
            ]);
            redirect_to(site_url('checkout', ['status' => 'confirmed', 'order' => $checkoutSuccessOrder['order_number']]));
        }
    } catch (Throwable $e) {
        $checkoutErrors[] = app_debug() ? $e->getMessage() : $e->getMessage();
        $cartItems = fetch_cart_items($pdo);
        $cart = cart_summary_from_items($cartItems);
        $selectedShippingMethod = $selectedShippingId > 0 ? store_find_active_shipping_method($pdo, $selectedShippingId) : null;
        $selectedPaymentMethod = $selectedPaymentId > 0 ? store_find_active_payment_method($pdo, $selectedPaymentId) : null;
        $couponPreview = null;
    }
} elseif ($couponCode !== '' && $cart['items'] !== []) {
    try {
        $couponPreview = store_preview_checkout_coupon($pdo, $couponCode, (float) $cart['subtotal']);
        $couponNotice = 'Coupon ' . $couponPreview['code'] . ' is ready to apply.';
    } catch (Throwable $e) {
        $checkoutErrors[] = $e->getMessage();
        $couponCode = '';
    }
}

$shippingCost = $selectedShippingMethod ? store_shipping_cost($selectedShippingMethod, (float) $cart['subtotal']) : 0.0;
$estimatedTaxes = 0.0;
$discountTotal = $couponPreview ? (float) $couponPreview['discount'] : 0.0;
$total = max(0, $cart['subtotal'] + $shippingCost + $estimatedTaxes - $discountTotal);
$canCheckout = $cart['items'] !== [] && $shippingMethods !== [] && $paymentMethods !== [];
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($pageDescription) ?>">
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
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
                        'on-secondary-container': '#5e656c',
                        'surface': '#f9f9fa',
                        'on-background': '#1a1c1d',
                        'on-secondary-fixed': '#151c22',
                        'on-surface': '#1a1c1d',
                        'error': '#ba1a1a',
                        'on-primary-fixed': '#001d34',
                        'surface-container-low': '#f3f3f4',
                        'on-tertiary-fixed': '#131b2e',
                        'error-container': '#ffdad6',
                        'tertiary-fixed': '#dae2fd',
                        'primary-fixed-dim': '#99cbff',
                        'surface-container-high': '#e8e8e9',
                        'on-tertiary-fixed-variant': '#3f465c',
                        'surface-variant': '#e2e2e3',
                        'primary-fixed': '#cfe5ff',
                        'inverse-on-surface': '#f0f1f2',
                        'surface-container-lowest': '#ffffff',
                        'on-tertiary-container': '#2d3549',
                        'on-secondary-fixed-variant': '#40484e',
                        'surface-tint': '#00629e',
                        'on-primary-fixed-variant': '#004a78',
                        'tertiary-container': '#969db6',
                        'secondary-fixed-dim': '#c0c7cf',
                        'on-error': '#ffffff',
                        'inverse-primary': '#99cbff',
                        'primary-container': '#5ea3e3',
                        'outline': '#717881',
                        'secondary-fixed': '#dce3eb',
                        'secondary': '#585f66',
                        'on-secondary': '#ffffff',
                        'surface-container': '#eeeeef',
                        'surface-container-highest': '#e2e2e3',
                        'surface-dim': '#d9dadb',
                        'on-tertiary': '#ffffff',
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
                        'body-lg': ['18px', { lineHeight: '1.6', fontWeight: '400' }],
                        'label-caps': ['12px', { lineHeight: '1', letterSpacing: '0.1em', fontWeight: '700' }],
                        'h3': ['24px', { lineHeight: '1.3', fontWeight: '600' }],
                        'body-md': ['16px', { lineHeight: '1.6', fontWeight: '400' }],
                        'h1': ['64px', { lineHeight: '1.1', letterSpacing: '-0.02em', fontWeight: '700' }],
                        'h2': ['40px', { lineHeight: '1.2', letterSpacing: '-0.01em', fontWeight: '600' }]
                    }
                }
            }
        }
    </script>
    <style>
        .glass-panel {
            background-color: rgba(255, 255, 255, 0.4);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.6);
            box-shadow: 0 20px 40px rgba(94, 163, 227, 0.08);
        }
        .minimal-input {
            background-color: transparent;
            border: none;
            border-bottom: 1px solid #c0c7d1;
            border-radius: 0;
            padding-left: 0;
            padding-right: 0;
            transition: border-color 0.3s ease;
        }
        .minimal-input:focus {
            outline: none;
            box-shadow: none;
            border-bottom-color: #00629e;
        }
        .toast {
            position: fixed;
            left: 50%;
            bottom: 24px;
            transform: translateX(-50%) translateY(20px);
            background: rgba(26, 28, 29, 0.92);
            color: #fff;
            padding: 12px 18px;
            border-radius: 9999px;
            font-size: 14px;
            opacity: 0;
            pointer-events: none;
            transition: opacity .2s ease, transform .2s ease;
            z-index: 100;
            box-shadow: 0 10px 30px rgba(0,0,0,.18);
        }
        .toast.show {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }
    </style>
<link rel="stylesheet" href="<?= e(site_url('assets/css/top_nav.css')) ?>"/>
</head>
<body class="bg-surface-bright text-on-surface font-body-md antialiased min-h-screen flex flex-col selection:bg-primary-container selection:text-on-primary-container" data-store-endpoint="<?= e(site_url('api_store')) ?>" data-csrf="<?= e(csrf_token()) ?>">
<?php require __DIR__ . '/includes/top_nav.php'; ?>

<main class="flex-grow w-full max-w-container-max mx-auto px-6 md:px-12 pb-section-padding">
    <div class="mb-12">
        <h1 class="font-h1 text-h1 text-on-background">Checkout</h1>
        <p class="font-body-lg text-body-lg text-on-surface-variant mt-4 max-w-2xl">Complete your order details below. Your cart here is live and synced with your current session.</p>
        <?php if (!$currentUser): ?>
            <p class="mt-4 inline-flex items-center gap-2 rounded-full bg-white/60 px-4 py-2 text-sm text-on-surface-variant border border-white/60">
                <span class="material-symbols-outlined text-[18px] text-primary">person</span>
                <span>Want faster checkout? <a class="text-primary font-medium" href="<?= e(site_url('auth', ['mode' => 'signin', 'next' => site_url('checkout')])) ?>">Sign in</a> to load your saved details.</span>
            </p>
        <?php endif; ?>
    </div>

    <form method="post" class="grid grid-cols-1 lg:grid-cols-12 gap-gutter">
        <?= csrf_field() ?>
        <div class="lg:col-span-8 flex flex-col gap-12">
            <section class="glass-panel rounded-xl p-8">
                <div class="flex items-center justify-between gap-4 mb-8">
                    <h2 class="font-h3 text-h3 text-on-surface">Review Items</h2>
                    <a href="<?= e(site_url('products')) ?>" class="text-primary font-label-caps text-label-caps inline-flex items-center gap-1">Continue shopping <span class="material-symbols-outlined text-[16px]">arrow_forward</span></a>
                </div>
                <?php if ($cart['items'] === []): ?>
                    <div class="rounded-[24px] bg-white/60 border border-white p-10 text-center">
                        <div class="w-16 h-16 mx-auto rounded-full bg-primary/10 text-primary flex items-center justify-center mb-5">
                            <span class="material-symbols-outlined text-3xl">shopping_cart</span>
                        </div>
                        <h3 class="text-2xl font-semibold text-on-background mb-3">Your cart is empty</h3>
                        <p class="text-on-surface-variant mb-6">Add products from the shop and they will appear here instantly.</p>
                        <a href="<?= e(site_url('products')) ?>" class="inline-flex items-center justify-center rounded-full bg-primary text-on-primary px-8 py-4 font-medium hover:bg-primary/90 transition-all">Browse products</a>
                    </div>
                <?php else: ?>
                    <div class="flex flex-col gap-8" data-cart-page>
                        <?php foreach ($cart['items'] as $item): ?>
                            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-6 pb-8 border-b border-outline-variant/30 last:border-0 last:pb-0" data-cart-row data-qty="<?= (int) $item['qty'] ?>" data-option-hash="<?= e($item['selected_options_hash'] ?? 'default') ?>">
                                <a href="<?= e(site_url('product', ['slug' => $item['slug']])) ?>" class="w-32 h-32 bg-surface-container-lowest rounded-lg p-4 flex items-center justify-center shrink-0">
                                    <img src="<?= e($item['image']) ?>" alt="<?= e($item['name']) ?>" class="w-full h-full object-contain mix-blend-multiply">
                                </a>
                                <div class="flex-grow flex flex-col gap-2">
                                    <h3 class="font-body-lg text-body-lg font-medium text-on-background"><?= e($item['name']) ?></h3>
                                    <?php if (!empty($item['selected_options_label'])): ?>
                                        <p class="font-body-md text-body-md text-on-surface-variant"><?= e($item['selected_options_label']) ?></p>
                                    <?php endif; ?>
                                    <p class="font-body-md text-body-md text-on-surface-variant"><?= e($item['stock']) ?> in stock</p>
                                    <button type="button" class="text-tertiary hover:text-error transition-colors flex items-center gap-1 mt-2 w-fit" data-store-action="remove_cart" data-product-id="<?= (int) $item['id'] ?>" data-option-hash="<?= e($item['selected_options_hash'] ?? 'default') ?>">
                                        <span class="material-symbols-outlined text-sm">delete</span>
                                        <span class="font-label-caps text-label-caps">REMOVE</span>
                                    </button>
                                </div>
                                <div class="flex flex-col items-end gap-4 shrink-0">
                                    <span class="font-h3 text-h3 text-on-background"><?= e(format_price($item['line_total'], $siteCurrency)) ?></span>
                                    <div class="flex items-center gap-4 bg-surface-container-low rounded-full px-4 py-2 border border-outline-variant/50">
                                        <button type="button" class="text-on-surface-variant hover:text-on-surface" data-store-action="update_cart" data-product-id="<?= (int) $item['id'] ?>" data-option-hash="<?= e($item['selected_options_hash'] ?? 'default') ?>" data-delta="-1" aria-label="Decrease quantity"><span class="material-symbols-outlined text-sm">remove</span></button>
                                        <span class="font-body-md text-body-md font-medium w-4 text-center"><?= (int) $item['qty'] ?></span>
                                        <button type="button" class="text-on-surface-variant hover:text-on-surface" data-store-action="update_cart" data-product-id="<?= (int) $item['id'] ?>" data-option-hash="<?= e($item['selected_options_hash'] ?? 'default') ?>" data-delta="1" aria-label="Increase quantity"><span class="material-symbols-outlined text-sm">add</span></button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <?php if ($checkoutErrors): ?>
                <section class="rounded-[28px] border border-error/30 bg-error-container/70 text-on-error-container p-6">
                    <strong class="block mb-2">Checkout needs attention</strong>
                    <?php foreach ($checkoutErrors as $error): ?><p><?= e($error) ?></p><?php endforeach; ?>
                </section>
            <?php endif; ?>

            <?php if (!$canCheckout && $cart['items'] !== []): ?>
                <section class="rounded-[28px] border border-error/30 bg-error-container/70 text-on-error-container p-6">
                    <strong class="block mb-2">Checkout is not ready</strong>
                    <p>Checkout is temporarily unavailable. Please contact support or try again later.</p>
                </section>
            <?php endif; ?>

            <section class="glass-panel rounded-xl p-8">
                <h2 class="font-h3 text-h3 text-on-surface mb-8">Shipping Information</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-10">
                    <div class="flex flex-col gap-2 md:col-span-2">
                        <label class="font-label-caps text-label-caps text-tertiary uppercase" for="checkout_full_name">Full Name</label>
                        <input id="checkout_full_name" name="full_name" class="minimal-input font-body-md text-body-md text-on-background" placeholder="Jane Doe" type="text" value="<?= e($checkoutFullName) ?>" required>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="font-label-caps text-label-caps text-tertiary uppercase" for="checkout_email">Email</label>
                        <input id="checkout_email" name="email" class="minimal-input font-body-md text-body-md text-on-background" placeholder="jane@example.com" type="email" value="<?= e($checkoutEmail) ?>" required>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="font-label-caps text-label-caps text-tertiary uppercase" for="checkout_phone">Phone</label>
                        <input id="checkout_phone" name="phone" class="minimal-input font-body-md text-body-md text-on-background" placeholder="+90 ..." type="text" value="<?= e($checkoutPhone) ?>">
                    </div>
                    <div class="flex flex-col gap-2 md:col-span-2">
                        <label class="font-label-caps text-label-caps text-tertiary uppercase" for="checkout_address">Street Address</label>
                        <input id="checkout_address" name="address_line1" class="minimal-input font-body-md text-body-md text-on-background" placeholder="123 Innovation Drive, Suite 400" type="text" value="<?= e($checkoutAddress) ?>" required>
                    </div>
                    <div class="flex flex-col gap-2 md:col-span-2">
                        <label class="font-label-caps text-label-caps text-tertiary uppercase" for="checkout_address2">Address line 2</label>
                        <input id="checkout_address2" name="address_line2" class="minimal-input font-body-md text-body-md text-on-background" placeholder="Apartment, suite, floor..." type="text" value="<?= e($checkoutAddress2) ?>">
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="font-label-caps text-label-caps text-tertiary uppercase" for="checkout_city">City</label>
                        <input id="checkout_city" name="city" class="minimal-input font-body-md text-body-md text-on-background" placeholder="Istanbul" type="text" value="<?= e($checkoutCity) ?>" required>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="font-label-caps text-label-caps text-tertiary uppercase" for="checkout_country">Country</label>
                        <select id="checkout_country" name="country" class="minimal-input font-body-md text-body-md text-on-background appearance-none" required>
                            <?php foreach (['Türkiye', 'Germany', 'United Kingdom', 'Saudi Arabia', 'United States'] as $option): ?>
                                <option value="<?= e($option) ?>" <?= $checkoutCountry === $option ? 'selected' : '' ?>><?= e($option) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </section>

            <section class="glass-panel rounded-xl p-8">
                <div class="flex items-center justify-between gap-4 mb-8">
                    <h2 class="font-h3 text-h3 text-on-surface">Shipping Method</h2>
                    <span class="text-sm text-on-surface-variant">Calculated on the server when the order is placed.</span>
                </div>
                <?php if ($shippingMethods === []): ?>
                    <div class="rounded-[24px] bg-white/60 border border-white p-8 text-on-surface-variant">No active shipping methods are configured.</div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <?php foreach ($shippingMethods as $method): ?>
                            <?php $methodCost = store_shipping_cost($method, (float) $cart['subtotal']); ?>
                            <label class="rounded-[24px] bg-white/55 border border-white/70 p-5 flex flex-col gap-3 cursor-pointer hover:bg-white/75 transition-all">
                                <span class="flex items-center justify-between gap-3">
                                    <strong class="text-on-background"><?= e($method['name']) ?></strong>
                                    <input type="radio" name="shipping_method_id" value="<?= (int) $method['id'] ?>" <?= (int) $selectedShippingId === (int) $method['id'] ? 'checked' : '' ?> required>
                                </span>
                                <span class="text-primary font-semibold"><?= e(format_price($methodCost, $siteCurrency)) ?></span>
                                <?php if (!empty($method['description'])): ?><span class="text-sm text-on-surface-variant"><?= e($method['description']) ?></span><?php endif; ?>
                                <small class="text-tertiary"><?= e(($method['eta_min_days'] !== null ? (string) $method['eta_min_days'] : '—') . '–' . ($method['eta_max_days'] !== null ? (string) $method['eta_max_days'] : '—') . ' days') ?><?= $method['free_over'] !== null ? e(' · Free over ' . format_price((float) $method['free_over'], $siteCurrency)) : '' ?></small>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <section class="glass-panel rounded-xl p-8">
                    <h2 class="font-h3 text-h3 text-on-surface mb-8">Payment Method</h2>
                    <?php if ($paymentMethods === []): ?>
                        <div class="rounded-[24px] bg-white/60 border border-white p-8 text-on-surface-variant">No active payment methods are configured.</div>
                    <?php else: ?>
                        <div class="flex flex-col gap-4">
                            <?php foreach ($paymentMethods as $method): ?>
                                <label class="rounded-[24px] bg-white/55 border border-white/70 p-5 flex flex-col gap-2 cursor-pointer hover:bg-white/75 transition-all">
                                    <span class="flex items-center justify-between gap-3">
                                        <strong class="text-on-background"><?= e($method['name']) ?></strong>
                                        <input type="radio" name="payment_method_id" value="<?= (int) $method['id'] ?>" <?= (int) $selectedPaymentId === (int) $method['id'] ? 'checked' : '' ?> required>
                                    </span>
                                    <span class="text-sm text-on-surface-variant"><?= e($method['provider'] ?: 'Manual') ?><?= (int) $method['manual_followup'] === 1 ? ' · Manual follow-up' : '' ?></span>
                                    <?php if (!empty($method['instructions'])): ?><small class="text-tertiary"><?= e($method['instructions']) ?></small><?php endif; ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
                <section class="glass-panel rounded-xl p-8">
                    <h2 class="font-h3 text-h3 text-on-surface mb-8">Order Notes</h2>
                    <div class="flex flex-col gap-2 h-full">
                        <label class="font-label-caps text-label-caps text-tertiary uppercase" for="checkout_notes">Special Instructions</label>
                        <textarea id="checkout_notes" name="notes" class="minimal-input font-body-md text-body-md text-on-background resize-none h-full min-h-[140px]" placeholder="Add any special instructions for delivery here..."><?= e($checkoutNotes) ?></textarea>
                    </div>
                </section>
            </div>
        </div>


        <div class="lg:col-span-4 relative">
            <div class="sticky top-8 flex flex-col gap-8">
                <section class="glass-panel rounded-xl p-8">
                    <h2 class="font-h3 text-h3 text-on-surface mb-8">Order Summary</h2>
                    <div class="rounded-[24px] bg-white/55 border border-white/70 p-5 mb-8">
                        <label class="font-label-caps text-label-caps text-tertiary uppercase block mb-3" for="checkout_coupon">Coupon code</label>
                        <div class="flex flex-col sm:flex-row gap-3">
                            <input id="checkout_coupon" name="coupon_code" class="minimal-input font-body-md text-body-md text-on-background flex-1 uppercase" placeholder="WELCOME10" value="<?= e($couponCode) ?>">
                            <button type="submit" name="checkout_action" value="preview_coupon" class="rounded-full border border-outline-variant/60 px-5 py-3 text-sm font-semibold text-on-surface hover:bg-white/70 transition-colors">Apply</button>
                        </div>
                        <?php if ($couponNotice !== ''): ?>
                            <p class="mt-3 text-sm text-primary font-semibold"><?= e($couponNotice) ?> Saving <?= e(format_price($discountTotal, $siteCurrency)) ?>.</p>
                        <?php elseif ($couponCode !== ''): ?>
                            <p class="mt-3 text-sm text-on-surface-variant">This code will be checked before the order is placed.</p>
                        <?php endif; ?>
                    </div>
                    <div class="flex flex-col gap-4 text-on-surface-variant font-body-md text-body-md mb-8">
                        <div class="flex justify-between">
                            <span>Subtotal (<?= (int) $cart['count'] ?> item<?= (int) $cart['count'] === 1 ? '' : 's' ?>)</span>
                            <span class="text-on-background font-medium"><?= e(format_price($cart['subtotal'], $siteCurrency)) ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span>Shipping</span>
                            <span class="text-on-background font-medium"><?= e(format_price($shippingCost, $siteCurrency)) ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span>Discount</span>
                            <span class="text-on-background font-medium">-<?= e(format_price($discountTotal, $siteCurrency)) ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span>Estimated Taxes</span>
                            <span class="text-on-background font-medium"><?= e(format_price($estimatedTaxes, $siteCurrency)) ?></span>
                        </div>
                    </div>
                    <div class="pt-6 border-t border-outline-variant/30 flex justify-between items-end mb-8">
                        <span class="font-body-lg text-body-lg text-on-surface-variant">Total</span>
                        <span class="font-h2 text-h2 text-on-background font-bold tracking-tight"><?= e(format_price($total, $siteCurrency)) ?></span>
                    </div>
                    <button type="submit" name="checkout_action" value="place_order" class="w-full bg-primary text-on-primary font-body-lg text-body-lg font-medium rounded-full py-4 px-8 hover:bg-primary/90 transition-all duration-300 shadow-lg shadow-primary/20 flex items-center justify-center gap-2 group <?= !$canCheckout ? 'opacity-50 cursor-not-allowed' : '' ?>" <?= !$canCheckout ? 'disabled' : '' ?>>
                        <span>Complete Order</span>
                        <span class="material-symbols-outlined group-hover:translate-x-1 transition-transform">arrow_forward</span>
                    </button>
                    <p class="font-label-caps text-label-caps text-tertiary text-center mt-6 flex items-center justify-center gap-1">
                        <span class="material-symbols-outlined text-[14px]">verified_user</span>
                        SSL SECURE ENCRYPTED CHECKOUT
                    </p>
                    <p class="mt-5 text-sm text-center text-on-surface-variant"><?= e($checkoutShippingInfoText) ?></p>
                </section>
            </div>
        </div>
    </form>

    <?php if (isset($_GET['status']) && $_GET['status'] === 'confirmed'): ?>
        <div class="mt-32 pt-24 border-t border-outline-variant/20 flex flex-col items-center justify-center text-center max-w-2xl mx-auto">
            <div class="w-20 h-20 bg-primary-container text-on-primary-container rounded-full flex items-center justify-center mb-8 shadow-[0_20px_40px_rgba(94,163,227,0.2)]">
                <span class="material-symbols-outlined text-4xl" style="font-variation-settings:'FILL' 1;">check_circle</span>
            </div>
            <h2 class="font-h2 text-h2 text-on-background mb-4">Order Confirmed</h2>
            <p class="font-body-lg text-body-lg text-on-surface-variant mb-8">Your order has been received and is now being processed.<?= isset($_GET['order']) ? ' Order #' . e((string) $_GET['order']) : '' ?></p>
            <a href="<?= e(site_url('account')) ?>" class="bg-surface-container-high text-on-surface font-body-md text-body-md rounded-full py-3 px-8 hover:bg-surface-variant transition-colors border border-outline-variant/30">View Order Details</a>
        </div>
    <?php endif; ?>
</main>

<div class="toast js-toast" aria-live="polite"></div>
<script src="assets/js/site.js"></script>
</body>
</html>
