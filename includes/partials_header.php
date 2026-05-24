<?php
$currentPage = $currentPage ?? '';
$pageTitle = $pageTitle ?? ($siteName ?? 'Phonix');
$pageDescription = $pageDescription ?? 'Premium phones, trusted accessories, and clear support for shoppers in Turkey.';
$includeSiteCss = $includeSiteCss ?? true;

if (!function_exists('phonix_asset_url')) {
    function phonix_asset_url(string $path): string
    {
        $relative = ltrim($path, '/');
        $absolute = dirname(__DIR__) . '/' . $relative;
        $version = is_file($absolute) ? (string) filemtime($absolute) : (string) time();
        return site_url($relative) . '?v=' . rawurlencode($version);
    }
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($pageDescription) ?>">
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@300;400;500;600;700;800&amp;display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet">
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
                        "on-secondary-container": "#5e656c",
                        "surface": "#f9f9fa",
                        "on-background": "#1a1c1d",
                        "on-secondary-fixed": "#151c22",
                        "on-surface": "#1a1c1d",
                        "error": "#ba1a1a",
                        "on-primary-fixed": "#001d34",
                        "surface-container-low": "#f3f3f4",
                        "on-tertiary-fixed": "#131b2e",
                        "error-container": "#ffdad6",
                        "tertiary-fixed": "#dae2fd",
                        "primary-fixed-dim": "#99cbff",
                        "surface-container-high": "#e8e8e9",
                        "on-tertiary-fixed-variant": "#3f465c",
                        "surface-variant": "#e2e2e3",
                        "primary-fixed": "#cfe5ff",
                        "inverse-on-surface": "#f0f1f2",
                        "surface-container-lowest": "#ffffff",
                        "on-tertiary-container": "#2d3549",
                        "on-secondary-fixed-variant": "#40484e",
                        "surface-tint": "#00629e",
                        "on-primary-fixed-variant": "#004a78",
                        "tertiary-container": "#969db6",
                        "secondary-fixed-dim": "#c0c7cf",
                        "on-error": "#ffffff",
                        "inverse-primary": "#99cbff",
                        "primary-container": "#5ea3e3",
                        "outline": "#717881",
                        "secondary-fixed": "#dce3eb",
                        "secondary": "#585f66",
                        "on-secondary": "#ffffff",
                        "surface-container": "#eeeeef",
                        "surface-container-highest": "#e2e2e3",
                        "surface-dim": "#d9dadb",
                        "on-tertiary": "#ffffff",
                        "background": "#f9f9fa",
                        "outline-variant": "#c0c7d1",
                        "on-surface-variant": "#414750",
                        "inverse-surface": "#2f3132",
                        "primary": "#00629e",
                        "secondary-container": "#dce3eb",
                        "on-primary-container": "#00385c"
                    },
                    borderRadius: {
                        DEFAULT: "1rem",
                        lg: "2rem",
                        xl: "3rem",
                        full: "9999px"
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
                        "body-lg": ["18px", { "lineHeight": "1.6", "fontWeight": "400" }],
                        "label-caps": ["12px", { "lineHeight": "1", "letterSpacing": "0.1em", "fontWeight": "700" }],
                        "h3": ["24px", { "lineHeight": "1.3", "fontWeight": "600" }],
                        "body-md": ["16px", { "lineHeight": "1.6", "fontWeight": "400" }],
                        "h1": ["64px", { "lineHeight": "1.1", "letterSpacing": "-0.02em", "fontWeight": "700" }],
                        "h2": ["40px", { "lineHeight": "1.2", "letterSpacing": "-0.01em", "fontWeight": "600" }]
                    }
                }
            }
        }
    </script>
    <style>
        .glass-panel {
            background: rgba(255, 255, 255, 0.4);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            box-shadow: 0 20px 40px rgba(94, 163, 227, 0.08);
        }
        .material-symbols-outlined { font-variation-settings:'FILL' 0,'wght' 300,'GRAD' 0,'opsz' 24; }
    </style>
    <link rel="stylesheet" href="<?= e(phonix_asset_url('assets/css/top_nav.css')) ?>">
    <?php if ($includeSiteCss): ?>
        <link rel="stylesheet" href="<?= e(phonix_asset_url('assets/css/style.css')) ?>">
    <?php endif; ?>
    <?php foreach (($pageStyles ?? []) as $pageStyle): ?>
        <link rel="stylesheet" href="<?= e(phonix_asset_url((string) $pageStyle)) ?>">
    <?php endforeach; ?>
</head>
<body class="bg-background text-on-background font-body-md antialiased selection:bg-primary-container selection:text-on-primary-container overflow-x-hidden min-h-screen flex flex-col" data-page="<?= e($currentPage ?: 'site') ?>" data-store-endpoint="<?= e(site_url('api_store')) ?>" data-csrf="<?= e(csrf_token()) ?>">
<?php require __DIR__ . '/top_nav.php'; ?>
<main class="relative flex-grow">
