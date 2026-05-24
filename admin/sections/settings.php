<?php
require_once __DIR__ . '/../includes/layout.php';
$ctx = admin_boot('settings');
$pdo = $ctx['pdo'];
$settings = $ctx['siteSettings'];

if (is_post_request()) {
    verify_csrf_or_fail($_POST['_csrf'] ?? null);
    try {
        $allowed = [
            'site_name' => 120,
            'site_currency' => 10,
            'support_email' => 190,
            'support_phone' => 80,
            'support_chat_label' => 120,
            'footer_tagline' => 255,
            'announcement_text' => 255,
            'shipping_info_text' => 255,
            'maintenance_title' => 190,
            'maintenance_message' => 500,
            'maintenance_mode' => 5,
        ];
        foreach ($allowed as $key => $max) {
            if ($key === 'maintenance_mode') {
                admin_store_setting($pdo, $key, isset($_POST[$key]) ? '1' : '0');
                continue;
            }
            $value = mb_substr(trim((string) ($_POST[$key] ?? '')), 0, $max);
            admin_store_setting($pdo, $key, $value);
        }
        flash_set('success', 'Settings saved.');
    } catch (Throwable $e) { admin_flash_from_exception($e); }
    admin_redirect('settings');
}

admin_header('Settings', 'Manage global storefront identity, support details, currency, and operational flags.', 'settings');
?>
<section class="admin-card glass-panel">
    <div class="admin-section-head"><div><p class="admin-eyebrow">Store control</p><h2>General settings</h2></div></div>
    <form method="post" class="admin-form-grid">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <label class="admin-field"><span>Site name</span><input name="site_name" value="<?= e($settings['site_name'] ?? 'Phonix') ?>"></label>
        <label class="admin-field"><span>Currency</span><select name="site_currency"><?php foreach (['TRY','USD','EUR','GBP'] as $currency): ?><option value="<?= e($currency) ?>" <?= ($settings['site_currency'] ?? 'TRY') === $currency ? 'selected' : '' ?>><?= e($currency) ?></option><?php endforeach; ?></select></label>
        <label class="admin-field"><span>Support email</span><input type="email" name="support_email" value="<?= e($settings['support_email'] ?? 'support@phonix.com') ?>"></label>
        <label class="admin-field"><span>Support phone</span><input name="support_phone" value="<?= e($settings['support_phone'] ?? '1-800-PHONIX-1') ?>"></label>
        <label class="admin-field"><span>Live chat label</span><input name="support_chat_label" value="<?= e($settings['support_chat_label'] ?? 'Available 24/7') ?>"></label>
        <label class="admin-field"><span>Footer tagline</span><input name="footer_tagline" value="<?= e($settings['footer_tagline'] ?? 'Precision Engineered.') ?>"></label>
        <label class="admin-field admin-field-wide"><span>Announcement text</span><input name="announcement_text" value="<?= e($settings['announcement_text'] ?? '') ?>"></label>
        <label class="admin-field admin-field-wide"><span>Shipping info text</span><input name="shipping_info_text" value="<?= e($settings['shipping_info_text'] ?? 'Selected shipping and payment are saved directly into the order.') ?>"></label>
        <label class="admin-field"><span>Maintenance title</span><input name="maintenance_title" value="<?= e($settings['maintenance_title'] ?? 'Store maintenance in progress') ?>"></label>
        <label class="admin-field admin-field-wide"><span>Maintenance message</span><input name="maintenance_message" value="<?= e($settings['maintenance_message'] ?? 'We are upgrading the storefront and will be back shortly.') ?>"></label>
        <div class="admin-check-row"><label><input type="checkbox" name="maintenance_mode" <?= ($settings['maintenance_mode'] ?? '0') === '1' ? 'checked' : '' ?>> Maintenance mode for visitors</label></div>
        <div class="admin-form-actions"><button class="admin-primary-btn" type="submit">Save settings</button></div>
    </form>
</section>
<section class="admin-card glass-panel">
    <div class="admin-section-head"><div><p class="admin-eyebrow">Next layer</p><h2>Recommended future settings</h2></div></div>
    <div class="admin-roadmap-grid">
        <article><strong>Payments</strong><span>Gateway keys, sandbox/live mode, webhook status.</span></article>
        <article><strong>Shipping</strong><span>Zones, shipping fees, free-shipping thresholds.</span></article>
        <article><strong>SEO</strong><span>Default meta title, description, OpenGraph image.</span></article>
        <article><strong>Admin Users</strong><span>Roles are now available under Admin Users; next step is finer per-action permissions.</span></article>
    </div>
</section>
<?php admin_footer(); ?>
