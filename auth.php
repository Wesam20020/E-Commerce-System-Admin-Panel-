<?php
require __DIR__ . '/includes/bootstrap.php';

$mode = $_GET['mode'] ?? 'signin';
if (!in_array($mode, ['signin', 'register'], true)) {
    $mode = 'signin';
}

if ($currentUser && is_post_request() && (($_POST['form_action'] ?? '') === 'logout')) {
    verify_csrf_or_fail($_POST['_csrf'] ?? null);
    auth_logout($pdo);
    session_start();
    flash_set('success', 'You have been signed out.');
    redirect_to(site_url('auth', ['mode' => 'signin']));
}

function auth_post_login_destination(PDO $pdo, array $user): string
{
    if (function_exists('store_is_admin_user') && store_is_admin_user($pdo, $user)) {
        return 'admin/index.php';
    }

    return site_url('account');
}

if ($currentUser) {
    flash_set('success', 'You are already signed in.');
    redirect_to(auth_post_login_destination($pdo, $currentUser));
}

if (is_post_request()) {
    verify_csrf_or_fail($_POST['_csrf'] ?? null);
    $formAction = (string) ($_POST['form_action'] ?? '');

    if ($formAction === 'register') {
        $payload = [
            'name' => trim((string) ($_POST['name'] ?? '')),
            'email' => trim((string) ($_POST['email'] ?? '')),
        ];
        set_old_input($payload + ['mode' => 'register']);
        $errors = auth_validate_registration($pdo, $_POST);
        if ($errors !== []) {
            foreach ($errors as $error) {
                flash_set('error', $error);
            }
            redirect_to(site_url('auth', ['mode' => 'register', 'next' => (string) ($_GET['next'] ?? '')]));
        }

        try {
            $user = auth_register_user($pdo, $_POST);
            auth_login($pdo, $user, !empty($_POST['remember']));
            clear_old_input();
            flash_set('success', 'Your account has been created successfully.');
            redirect_to(auth_post_login_destination($pdo, $user));
        } catch (Throwable $e) {
            error_log('[Phonix] Registration failed: ' . $e->getMessage());
            flash_set('error', 'We could not create your account right now. Please try again.');
            redirect_to(site_url('auth', ['mode' => 'register', 'next' => (string) ($_GET['next'] ?? '')]));
        }
    }

    if ($formAction === 'signin') {
        $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        set_old_input(['email' => $email, 'mode' => 'signin']);

        $user = auth_find_user_by_email($pdo, $email);
        if (!$user || !password_verify($password, (string) $user['password_hash'])) {
            flash_set('error', 'Invalid email or password.');
            redirect_to(site_url('auth', ['mode' => 'signin', 'next' => (string) ($_GET['next'] ?? '')]));
        }

        auth_login($pdo, $user, !empty($_POST['remember']));
        clear_old_input();
        flash_set('success', 'Welcome back, ' . $user['name'] . '.');
        redirect_to(auth_post_login_destination($pdo, $user));
    }
}

$isRegister = $mode === 'register';
$pageTitle = $siteName . ' | ' . ($isRegister ? 'Create Account' : 'Sign In');
$pageDescription = 'Phonix customer authentication.';
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($pageDescription) ?>"/>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@300;400;500;600;700;800&amp;display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "on-primary": "#ffffff",
                        "tertiary-fixed-dim": "#bec6e0",
                        "surface-bright": "#f9f9fa",
                        "tertiary": "#565e74",
                        "on-error-container": "#93000a",
                        "surface": "#f9f9fa",
                        "on-background": "#1a1c1d",
                        "on-surface": "#1a1c1d",
                        "error": "#ba1a1a",
                        "surface-container-low": "#f3f3f4",
                        "error-container": "#ffdad6",
                        "tertiary-fixed": "#dae2fd",
                        "primary-fixed-dim": "#99cbff",
                        "surface-variant": "#e2e2e3",
                        "primary-fixed": "#cfe5ff",
                        "surface-container-lowest": "#ffffff",
                        "surface-tint": "#00629e",
                        "outline": "#717881",
                        "secondary": "#585f66",
                        "surface-container": "#eeeeef",
                        "background": "#f9f9fa",
                        "outline-variant": "#c0c7d1",
                        "on-surface-variant": "#414750",
                        "primary": "#00629e",
                        "primary-container": "#5ea3e3",
                        "on-primary-container": "#00385c"
                    },
                    borderRadius: {
                        "DEFAULT": "1rem",
                        "lg": "2rem",
                        "xl": "3rem",
                        "full": "9999px"
                    },
                    spacing: {
                        "section-padding": "120px",
                        "element-gap": "24px",
                        "unit": "8px",
                        "gutter": "32px",
                        "container-max": "1280px"
                    },
                    fontFamily: {
                        "body-lg": ["Manrope"],
                        "label-caps": ["Manrope"],
                        "h3": ["Manrope"],
                        "body-md": ["Manrope"],
                        "h1": ["Manrope"],
                        "h2": ["Manrope"]
                    },
                    fontSize: {
                        "body-lg": ["18px", { lineHeight: "1.6", fontWeight: "400" }],
                        "label-caps": ["12px", { lineHeight: "1", letterSpacing: "0.1em", fontWeight: "700" }],
                        "h3": ["24px", { lineHeight: "1.3", fontWeight: "600" }],
                        "body-md": ["16px", { lineHeight: "1.6", fontWeight: "400" }],
                        "h1": ["64px", { lineHeight: "1.1", letterSpacing: "-0.02em", fontWeight: "700" }],
                        "h2": ["40px", { lineHeight: "1.2", letterSpacing: "-0.01em", fontWeight: "600" }]
                    }
                }
            }
        }
    </script>
    <style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 300, 'GRAD' 0, 'opsz' 24;
        }
        .glass-panel {
            background: rgba(255, 255, 255, 0.4);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            box-shadow: 0 20px 40px rgba(94, 163, 227, 0.08);
        }
        .ambient-glow {
            position: absolute;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(153, 203, 255, 0.2) 0%, rgba(249, 249, 250, 0) 70%);
            border-radius: 50%;
            z-index: -1;
            pointer-events: none;
        }
    </style>
<link rel="stylesheet" href="<?= e(site_url('assets/css/top_nav.css')) ?>"/>
</head>
<body class="bg-background text-on-background min-h-screen flex flex-col relative overflow-x-hidden">
    <div class="ambient-glow top-0 left-0 -translate-x-1/2 -translate-y-1/2"></div>
    <div class="ambient-glow bottom-0 right-0 translate-x-1/2 translate-y-1/2"></div>

    <?php require __DIR__ . '/includes/top_nav.php'; ?>

    <main class="flex-grow flex items-center justify-center p-gutter min-h-screen">
        <div class="max-w-[1000px] w-full grid grid-cols-1 md:grid-cols-2 gap-element-gap relative z-10">
            <div class="glass-panel rounded-xl p-12 flex flex-col justify-center h-full">
                <div class="mb-12">
                    <a class="text-h3 font-h3 text-primary block mb-8" href="<?= e(site_url('home')) ?>"><?= e($siteName) ?></a>
                    <h1 class="text-h2 font-h2 text-on-surface mb-4"><?= $isRegister ? 'Create Account' : 'Welcome Back' ?></h1>
                    <p class="text-body-md font-body-md text-on-surface-variant">
                        <?= $isRegister
                            ? 'Create your Phonix account to sync your cart, wishlist, and future orders across devices.'
                            : 'Sign in to continue exploring phones, accessories, and deals.' ?>
                    </p>
                </div>

                <?php if ($flashMessages !== []): ?>
                    <div class="space-y-3 mb-8">
                        <?php foreach ($flashMessages as $flash): ?>
                            <div class="rounded-2xl px-4 py-3 text-sm <?= $flash['type'] === 'error' ? 'bg-error-container text-on-error-container' : 'bg-primary/10 text-primary' ?>">
                                <?= e($flash['message']) ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($isRegister): ?>
                    <form class="space-y-6" method="post" action="<?= e(site_url('auth', ['mode' => 'register', 'next' => (string) ($_GET['next'] ?? '')])) ?>">
                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="form_action" value="register">
                        <div>
                            <label class="text-label-caps font-label-caps text-on-surface-variant block mb-2" for="name">FULL NAME</label>
                            <input class="w-full border-b border-outline-variant focus:border-primary px-0 py-3 text-body-md font-body-md text-on-surface focus:ring-0 transition-colors bg-transparent placeholder:text-outline" id="name" name="name" value="<?= e($oldInput['name'] ?? '') ?>" placeholder="Your full name" type="text" autocomplete="name" required/>
                        </div>
                        <div>
                            <label class="text-label-caps font-label-caps text-on-surface-variant block mb-2" for="email">EMAIL ADDRESS</label>
                            <input class="w-full border-b border-outline-variant focus:border-primary px-0 py-3 text-body-md font-body-md text-on-surface focus:ring-0 transition-colors bg-transparent placeholder:text-outline" id="email" name="email" value="<?= e($oldInput['email'] ?? '') ?>" placeholder="name@example.com" type="email" autocomplete="email" required/>
                        </div>
                        <div>
                            <label class="text-label-caps font-label-caps text-on-surface-variant block mb-2" for="password">PASSWORD</label>
                            <input class="w-full border-b border-outline-variant focus:border-primary px-0 py-3 text-body-md font-body-md text-on-surface focus:ring-0 transition-colors bg-transparent placeholder:text-outline" id="password" name="password" placeholder="At least 8 characters" type="password" autocomplete="new-password" required/>
                        </div>
                        <div>
                            <label class="text-label-caps font-label-caps text-on-surface-variant block mb-2" for="password_confirmation">CONFIRM PASSWORD</label>
                            <input class="w-full border-b border-outline-variant focus:border-primary px-0 py-3 text-body-md font-body-md text-on-surface focus:ring-0 transition-colors bg-transparent placeholder:text-outline" id="password_confirmation" name="password_confirmation" placeholder="Repeat your password" type="password" autocomplete="new-password" required/>
                        </div>
                        <label class="flex items-center gap-3 pt-2 text-sm text-on-surface-variant">
                            <input class="rounded border-outline-variant text-primary focus:ring-primary/20" type="checkbox" name="remember" value="1">
                            <span>Keep me signed in after account creation</span>
                        </label>
                        <div class="pt-4">
                            <button class="w-full rounded-full bg-primary text-on-primary py-4 text-body-md font-body-md font-medium hover:opacity-90 transition-opacity flex justify-center items-center gap-2" type="submit">
                                Create Account
                                <span class="material-symbols-outlined text-[20px]">arrow_forward</span>
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <form class="space-y-6" method="post" action="<?= e(site_url('auth', ['mode' => 'signin', 'next' => (string) ($_GET['next'] ?? '')])) ?>">
                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="form_action" value="signin">
                        <div>
                            <label class="text-label-caps font-label-caps text-on-surface-variant block mb-2" for="email">EMAIL ADDRESS</label>
                            <input class="w-full border-b border-outline-variant focus:border-primary px-0 py-3 text-body-md font-body-md text-on-surface focus:ring-0 transition-colors bg-transparent placeholder:text-outline" id="email" name="email" value="<?= e($oldInput['email'] ?? '') ?>" placeholder="name@example.com" type="email" autocomplete="email" required/>
                        </div>
                        <div>
                            <div class="flex justify-between items-center mb-2">
                                <label class="text-label-caps font-label-caps text-on-surface-variant" for="password">PASSWORD</label>
                                <span class="text-label-caps font-label-caps text-outline">SECURE ACCESS</span>
                            </div>
                            <input class="w-full border-b border-outline-variant focus:border-primary px-0 py-3 text-body-md font-body-md text-on-surface focus:ring-0 transition-colors bg-transparent placeholder:text-outline" id="password" name="password" placeholder="••••••••" type="password" autocomplete="current-password" required/>
                        </div>
                        <label class="flex items-center gap-3 pt-2 text-sm text-on-surface-variant">
                            <input class="rounded border-outline-variant text-primary focus:ring-primary/20" type="checkbox" name="remember" value="1">
                            <span>Keep me signed in for 30 days</span>
                        </label>
                        <div class="pt-4">
                            <button class="w-full rounded-full bg-primary text-on-primary py-4 text-body-md font-body-md font-medium hover:opacity-90 transition-opacity flex justify-center items-center gap-2" type="submit">
                                Sign In
                                <span class="material-symbols-outlined text-[20px]">arrow_forward</span>
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>

            <div class="glass-panel rounded-xl p-12 flex flex-col justify-center bg-surface-tint/5 border-primary/10 h-full relative overflow-hidden">
                <div class="absolute -right-20 -top-20 w-64 h-64 bg-primary/5 rounded-full blur-3xl"></div>
                <h2 class="text-h3 font-h3 text-on-surface mb-8 relative z-10"><?= $isRegister ? 'Already have an account?' : 'Create an Account' ?></h2>
                <div class="space-y-6 mb-12 relative z-10">
                    <div class="flex gap-4 items-start">
                        <div class="w-10 h-10 rounded-full bg-primary/10 text-primary flex items-center justify-center shrink-0">
                            <span class="material-symbols-outlined text-[20px]">sync</span>
                        </div>
                        <div>
                            <h3 class="text-body-md font-body-md font-medium text-on-surface mb-1">Synced Experience</h3>
                            <p class="text-body-md font-body-md text-on-surface-variant text-sm">Access your wishlist and cart across all your devices seamlessly.</p>
                        </div>
                    </div>
                    <div class="flex gap-4 items-start">
                        <div class="w-10 h-10 rounded-full bg-primary/10 text-primary flex items-center justify-center shrink-0">
                            <span class="material-symbols-outlined text-[20px]">location_on</span>
                        </div>
                        <div>
                            <h3 class="text-body-md font-body-md font-medium text-on-surface mb-1">Faster Checkout</h3>
                            <p class="text-body-md font-body-md text-on-surface-variant text-sm">Save your shipping addresses and payment methods securely.</p>
                        </div>
                    </div>
                    <div class="flex gap-4 items-start">
                        <div class="w-10 h-10 rounded-full bg-primary/10 text-primary flex items-center justify-center shrink-0">
                            <span class="material-symbols-outlined text-[20px]">history</span>
                        </div>
                        <div>
                            <h3 class="text-body-md font-body-md font-medium text-on-surface mb-1">Order Tracking</h3>
                            <p class="text-body-md font-body-md text-on-surface-variant text-sm">View order history and track current shipments in real-time.</p>
                        </div>
                    </div>
                </div>
                <div class="mt-auto relative z-10">
                    <p class="text-body-md font-body-md text-on-surface-variant mb-4"><?= $isRegister ? 'Already a member?' : 'New to Phonix?' ?></p>
                    <a class="w-full rounded-full border border-primary text-primary py-4 text-body-md font-body-md font-medium hover:bg-primary/5 transition-colors flex justify-center items-center gap-2" href="<?= e(site_url('auth', ['mode' => $isRegister ? 'signin' : 'register', 'next' => (string) ($_GET['next'] ?? '')])) ?>">
                        <?= $isRegister ? 'Sign In' : 'Register Now' ?>
                    </a>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
