<?php
$maintenanceTitle = store_setting($siteSettings ?? [], 'maintenance_title', 'Store maintenance in progress');
$maintenanceMessage = store_setting($siteSettings ?? [], 'maintenance_message', 'We are upgrading the storefront and will be back shortly.');
$maintenanceSupportEmail = store_setting($siteSettings ?? [], 'support_email', 'support@phonix.com');
$maintenanceSiteName = store_setting($siteSettings ?? [], 'site_name', 'Phonix');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($maintenanceTitle) ?> | <?= e($maintenanceSiteName) ?></title>
    <link rel="stylesheet" href="<?= e(site_url('assets/css/maintenance.css')) ?>">
</head>
<body class="maintenance-page">
    <main class="maintenance-shell" aria-labelledby="maintenance-title">
        <section class="maintenance-card">
            <span class="maintenance-kicker"><?= e($maintenanceSiteName) ?></span>
            <h1 id="maintenance-title"><?= e($maintenanceTitle) ?></h1>
            <p><?= e($maintenanceMessage) ?></p>
            <a href="mailto:<?= e($maintenanceSupportEmail) ?>"><?= e($maintenanceSupportEmail) ?></a>
        </section>
    </main>
</body>
</html>
