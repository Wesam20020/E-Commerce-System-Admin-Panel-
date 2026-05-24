<?php
require_once __DIR__ . '/../includes/layout.php';
$ctx = admin_boot('email');
$pdo = $ctx['pdo'];
$settings = $ctx['siteSettings'];
store_ensure_email_tables($pdo);

$emailStatuses = ['queued', 'processing', 'sent', 'failed', 'skipped'];

if (is_post_request()) {
    verify_csrf_or_fail($_POST['_csrf'] ?? null);
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'save_settings') {
            admin_store_setting($pdo, 'email_notifications_enabled', isset($_POST['email_notifications_enabled']) ? '1' : '0');
            admin_store_setting($pdo, 'email_from_name', mb_substr(trim((string) ($_POST['email_from_name'] ?? 'Phonix')), 0, 120));
            admin_store_setting($pdo, 'email_from_email', mb_substr(trim((string) ($_POST['email_from_email'] ?? 'no-reply@phonix.local')), 0, 190));
            admin_store_setting($pdo, 'email_admin_alert_email', mb_substr(trim((string) ($_POST['email_admin_alert_email'] ?? 'support@phonix.com')), 0, 190));
            admin_store_setting($pdo, 'email_delivery_enabled', isset($_POST['email_delivery_enabled']) ? '1' : '0');
            $batchSize = max(1, min(50, (int) ($_POST['email_delivery_batch_size'] ?? 10)));
            admin_store_setting($pdo, 'email_delivery_batch_size', (string) $batchSize);
            if (isset($_POST['regenerate_cron_token']) || trim((string) ($settings['email_cron_token'] ?? '')) === '') {
                admin_store_setting($pdo, 'email_cron_token', bin2hex(random_bytes(24)));
            }
            flash_set('success', 'Email notification and delivery settings saved.');
            admin_log_activity($pdo, 'email_settings_updated', 'email', null, 'Notification settings were updated.');
        } elseif ($action === 'save_template') {
            $id = (int) ($_POST['id'] ?? 0);
            $name = mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 190);
            $subject = mb_substr(trim((string) ($_POST['subject'] ?? '')), 0, 255);
            $body = mb_substr(trim((string) ($_POST['body'] ?? '')), 0, 20000);
            $description = mb_substr(trim((string) ($_POST['description'] ?? '')), 0, 255) ?: null;
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            if ($id <= 0 || $name === '' || $subject === '' || $body === '') {
                throw new RuntimeException('Template name, subject, and body are required.');
            }
            $stmt = $pdo->prepare('UPDATE email_templates SET name = :name, subject = :subject, body = :body, description = :description, is_active = :is_active WHERE id = :id LIMIT 1');
            $stmt->execute([
                'name' => $name,
                'subject' => $subject,
                'body' => $body,
                'description' => $description,
                'is_active' => $isActive,
                'id' => $id,
            ]);
            flash_set('success', 'Email template saved.');
            admin_log_activity($pdo, 'email_template_updated', 'email_template', $id, $name);
        } elseif ($action === 'reset_template') {
            $templateKey = mb_substr(trim((string) ($_POST['template_key'] ?? '')), 0, 120);
            $defaults = store_email_default_templates();
            if (!isset($defaults[$templateKey])) {
                throw new RuntimeException('Template cannot be reset because it has no default version.');
            }
            $template = $defaults[$templateKey];
            $stmt = $pdo->prepare('UPDATE email_templates SET name = :name, subject = :subject, body = :body, description = :description, is_active = 1 WHERE template_key = :template_key LIMIT 1');
            $stmt->execute([
                'name' => $template['name'],
                'subject' => $template['subject'],
                'body' => $template['body'],
                'description' => $template['description'],
                'template_key' => $templateKey,
            ]);
            flash_set('success', 'Template reset to default.');
            admin_log_activity($pdo, 'email_template_reset', 'email_template', null, $templateKey);
        } elseif ($action === 'process_queue') {
            $limit = max(1, min(50, (int) ($_POST['limit'] ?? store_email_batch_size())));
            $result = store_process_email_queue($pdo, $limit);
            $type = ($result['failed'] ?? 0) > 0 ? 'warning' : 'success';
            flash_set($type, ($result['message'] ?? 'Email queue processed.') . ' Sent: ' . (int) ($result['sent'] ?? 0) . ', failed: ' . (int) ($result['failed'] ?? 0) . '.');
            admin_log_activity($pdo, 'email_queue_processed', 'email_outbox', null, 'Processed ' . (int) ($result['processed'] ?? 0) . ' queued emails.');
        } elseif ($action === 'requeue_failed') {
            $count = store_requeue_failed_emails($pdo, 200);
            flash_set('success', $count . ' failed email(s) were moved back to queued.');
            admin_log_activity($pdo, 'email_failed_requeued', 'email_outbox', null, (string) $count);
        } elseif ($action === 'mark_outbox') {
            $id = (int) ($_POST['id'] ?? 0);
            $status = preg_replace('/[^a-z_]+/', '', strtolower((string) ($_POST['status'] ?? 'queued'))) ?: 'queued';
            if ($id <= 0 || !in_array($status, $emailStatuses, true)) {
                throw new RuntimeException('Invalid outbox update.');
            }
            $sentSql = $status === 'sent' ? 'sent_at = NOW(),' : ($status !== 'sent' ? 'sent_at = NULL,' : '');
            $lastAttemptSql = $status === 'queued' ? 'last_attempt_at = NULL,' : '';
            $stmt = $pdo->prepare("UPDATE email_outbox SET status = :status, {$sentSql} {$lastAttemptSql} error_message = NULL WHERE id = :id LIMIT 1");
            $stmt->execute(['status' => $status, 'id' => $id]);
            flash_set('success', 'Outbox message updated.');
            admin_log_activity($pdo, 'email_outbox_status_updated', 'email_outbox', $id, 'Set to ' . $status);
        } elseif ($action === 'clear_old_sent') {
            $pdo->exec("DELETE FROM email_outbox WHERE status IN ('sent','skipped') AND queued_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
            flash_set('success', 'Old sent/skipped email logs were cleared.');
            admin_log_activity($pdo, 'email_outbox_old_cleared', 'email_outbox', null, 'Older than 30 days.');
        }
    } catch (Throwable $e) {
        admin_flash_from_exception($e);
    }
    admin_redirect('email');
}

$templates = admin_rows($pdo, 'SELECT * FROM email_templates ORDER BY template_key ASC');
$editTemplateId = (int) ($_GET['edit_template'] ?? 0);
$editTemplate = null;
if ($editTemplateId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM email_templates WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $editTemplateId]);
    $editTemplate = $stmt->fetch() ?: null;
}
$statusFilter = preg_replace('/[^a-z_]+/', '', strtolower((string) ($_GET['status'] ?? 'queued'))) ?: 'queued';
if (!in_array($statusFilter, $emailStatuses, true) && $statusFilter !== 'all') {
    $statusFilter = 'queued';
}
$outboxParams = [];
$outboxWhere = '';
if ($statusFilter !== 'all') {
    $outboxWhere = 'WHERE status = :status';
    $outboxParams['status'] = $statusFilter;
}
$outbox = admin_rows($pdo, "SELECT * FROM email_outbox {$outboxWhere} ORDER BY queued_at DESC, id DESC LIMIT 80", $outboxParams);
$counts = [
    'enabled' => (($settings['email_notifications_enabled'] ?? '0') === '1') ? 1 : 0,
    'delivery_enabled' => (($settings['email_delivery_enabled'] ?? '0') === '1') ? 1 : 0,
    'templates' => (int) admin_scalar($pdo, 'SELECT COUNT(*) FROM email_templates'),
    'active_templates' => (int) admin_scalar($pdo, 'SELECT COUNT(*) FROM email_templates WHERE is_active = 1'),
    'queued' => (int) admin_scalar($pdo, "SELECT COUNT(*) FROM email_outbox WHERE status = 'queued'"),
    'sent' => (int) admin_scalar($pdo, "SELECT COUNT(*) FROM email_outbox WHERE status = 'sent'"),
    'failed' => (int) admin_scalar($pdo, "SELECT COUNT(*) FROM email_outbox WHERE status = 'failed'"),
    'processing' => (int) admin_scalar($pdo, "SELECT COUNT(*) FROM email_outbox WHERE status = 'processing'"),
];
$tokens = ['{{site_name}}', '{{customer_name}}', '{{customer_email}}', '{{order_number}}', '{{order_total}}', '{{shipping_method}}', '{{payment_method}}', '{{support_id}}', '{{support_subject}}', '{{support_excerpt}}', '{{order_status}}', '{{payment_status}}', '{{tracking_number}}'];

$cronToken = trim((string) ($settings['email_cron_token'] ?? ''));
$cronUrl = $cronToken !== '' ? admin_root_url('admin/email_worker.php', ['token' => $cronToken]) : '';
admin_header('Email Center', 'Prepare templates, queue notifications, and deliver pending mail through the built-in PHP mail runner.', 'email');
?>
<section class="admin-metrics-grid" aria-label="Email metrics">
    <?php admin_metric_card('Notifications', $counts['enabled'] ? 'Enabled' : 'Off', 'mark_email_unread', $counts['enabled'] ? 'Queueing active' : 'No email queueing'); ?>
    <?php admin_metric_card('Templates', (string) $counts['active_templates'], 'article', $counts['templates'] . ' total'); ?>
    <?php admin_metric_card('Queued', (string) $counts['queued'], 'pending_actions', $counts['delivery_enabled'] ? 'Ready to send' : 'Delivery off'); ?>
    <?php admin_metric_card('Sent / Failed', $counts['sent'] . ' / ' . $counts['failed'], 'outgoing_mail', 'Outbox log'); ?>
</section>

<section class="admin-two-col admin-two-col-wide">
    <article class="admin-card glass-panel">
        <div class="admin-section-head"><div><p class="admin-eyebrow">Email settings</p><h2>Notification queue</h2></div><span class="admin-pill <?= $counts['enabled'] ? 'good' : 'neutral' ?>"><?= $counts['enabled'] ? 'Enabled' : 'Disabled' ?></span></div>
        <form method="post" class="admin-form-grid">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="save_settings">
            <label class="admin-field"><span>From name</span><input name="email_from_name" value="<?= e($settings['email_from_name'] ?? 'Phonix') ?>"></label>
            <label class="admin-field"><span>From email</span><input type="email" name="email_from_email" value="<?= e($settings['email_from_email'] ?? 'no-reply@phonix.local') ?>"></label>
            <label class="admin-field admin-field-wide"><span>Admin alert email</span><input type="email" name="email_admin_alert_email" value="<?= e($settings['email_admin_alert_email'] ?? ($settings['support_email'] ?? 'support@phonix.com')) ?>"></label>
            <label class="admin-field"><span>Delivery batch size</span><input type="number" min="1" max="50" name="email_delivery_batch_size" value="<?= e((string) ($settings['email_delivery_batch_size'] ?? '10')) ?>"></label>
            <div class="admin-check-row"><label><input type="checkbox" name="email_notifications_enabled" <?= $counts['enabled'] ? 'checked' : '' ?>> Queue emails after checkout and support submissions</label></div>
            <div class="admin-check-row"><label><input type="checkbox" name="email_delivery_enabled" <?= $counts['delivery_enabled'] ? 'checked' : '' ?>> Deliver queued emails with PHP mail()</label></div>
            <div class="admin-check-row"><label><input type="checkbox" name="regenerate_cron_token"> Regenerate cron token</label></div>
            <div class="admin-form-actions"><button class="admin-primary-btn" type="submit">Save email settings</button></div>
        </form>
        <p class="admin-help-text">Queueing stores messages. Delivery sends queued messages with the server's PHP mail() function. Keep delivery disabled until your hosting mail sender is configured.</p>
        <?php if ($cronUrl !== ''): ?><p class="admin-help-text"><strong>Cron URL:</strong> <code><?= e($cronUrl) ?></code></p><?php endif; ?>
    </article>

    <article class="admin-card glass-panel">
        <div class="admin-section-head"><div><p class="admin-eyebrow">Template tokens</p><h2>Available placeholders</h2></div></div>
        <div class="admin-token-cloud">
            <?php foreach ($tokens as $token): ?><code><?= e($token) ?></code><?php endforeach; ?>
        </div>
    </article>
</section>

<section class="admin-card glass-panel">
    <div class="admin-section-head"><div><p class="admin-eyebrow">Delivery runner</p><h2>Send queued email batch</h2></div><span class="admin-pill <?= $counts['delivery_enabled'] ? 'good' : 'neutral' ?>"><?= $counts['delivery_enabled'] ? 'Delivery enabled' : 'Delivery disabled' ?></span></div>
    <div class="admin-actions-row">
        <form method="post" class="admin-inline-form" data-admin-confirm="Process the next queued email batch now?">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="process_queue">
            <input type="number" min="1" max="50" name="limit" value="<?= e((string) ($settings['email_delivery_batch_size'] ?? '10')) ?>" aria-label="Batch size">
            <button class="admin-primary-btn" type="submit">Process queue now</button>
        </form>
        <form method="post" class="admin-inline-form" data-admin-confirm="Move failed emails back to queued?">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="requeue_failed">
            <button class="admin-table-btn" type="submit">Requeue failed</button>
        </form>
    </div>
    <p class="admin-help-text">Last runner execution: <?= e((string) ($settings['email_last_run_at'] ?: 'Never')) ?>. Processing locks each queued row before sending to reduce duplicate delivery.</p>
</section>

<section class="admin-card glass-panel">
    <div class="admin-section-head"><div><p class="admin-eyebrow">Templates</p><h2>Email templates</h2></div></div>
    <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Template</th><th>Subject</th><th>Status</th><th>Action</th></tr></thead><tbody>
        <?php foreach ($templates as $template): ?>
            <tr>
                <td><strong><?= e($template['name']) ?></strong><small><?= e($template['template_key']) ?> · <?= e($template['description'] ?? '') ?></small></td>
                <td><?= e($template['subject']) ?></td>
                <td><span class="admin-pill <?= (int) $template['is_active'] === 1 ? 'good' : 'neutral' ?>"><?= (int) $template['is_active'] === 1 ? 'Active' : 'Disabled' ?></span></td>
                <td class="admin-row-actions"><a class="admin-table-btn" href="<?= e(admin_page_url('email', ['edit_template' => (int) $template['id']])) ?>">Edit</a></td>
            </tr>
        <?php endforeach; ?>
    </tbody></table></div>
</section>

<?php if ($editTemplate): ?>
<section class="admin-card glass-panel" id="edit-template">
    <div class="admin-section-head"><div><p class="admin-eyebrow">Editing template</p><h2><?= e($editTemplate['name']) ?></h2></div><span class="admin-pill info"><?= e($editTemplate['template_key']) ?></span></div>
    <form method="post" class="admin-form-grid admin-form-grid--email">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="save_template">
        <input type="hidden" name="id" value="<?= (int) $editTemplate['id'] ?>">
        <label class="admin-field"><span>Name</span><input name="name" value="<?= e($editTemplate['name']) ?>"></label>
        <label class="admin-field"><span>Subject</span><input name="subject" value="<?= e($editTemplate['subject']) ?>"></label>
        <label class="admin-field admin-field-wide"><span>Description</span><input name="description" value="<?= e($editTemplate['description'] ?? '') ?>"></label>
        <label class="admin-field admin-field-wide"><span>Body</span><textarea name="body" rows="12"><?= e($editTemplate['body']) ?></textarea></label>
        <div class="admin-check-row"><label><input type="checkbox" name="is_active" <?= (int) $editTemplate['is_active'] === 1 ? 'checked' : '' ?>> Template active</label></div>
        <div class="admin-form-actions"><button class="admin-primary-btn" type="submit">Save template</button></div>
    </form>
    <form method="post" class="admin-inline-form" data-admin-confirm="Reset this template to its default text?">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="reset_template">
        <input type="hidden" name="template_key" value="<?= e($editTemplate['template_key']) ?>">
        <button class="admin-table-btn danger" type="submit">Reset default</button>
    </form>
</section>
<?php endif; ?>

<section class="admin-card glass-panel">
    <div class="admin-section-head"><div><p class="admin-eyebrow">Outbox</p><h2>Email queue</h2></div><form method="get" class="admin-inline-form"><input type="hidden" name="section" value="email"><select name="status"><?php foreach (['queued'=>'Queued','processing'=>'Processing','sent'=>'Sent','failed'=>'Failed','skipped'=>'Skipped','all'=>'All'] as $value => $label): ?><option value="<?= e($value) ?>" <?= $statusFilter === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select><button class="admin-table-btn" type="submit">Filter</button></form></div>
    <?php if (!$outbox): ?>
        <?php admin_empty_state('No matching email logs', 'Queued order and support notifications will appear here after email notifications are enabled.'); ?>
    <?php else: ?>
        <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Recipient</th><th>Subject</th><th>Status</th><th>Related</th><th>Queued</th><th>Attempts</th><th>Error</th><th>Action</th></tr></thead><tbody>
        <?php foreach ($outbox as $email): ?>
            <tr>
                <td><strong><?= e($email['recipient_name'] ?: 'Recipient') ?></strong><small><?= e($email['recipient_email']) ?></small></td>
                <td><strong><?= e($email['subject']) ?></strong><small><?= e($email['template_key'] ?: 'raw') ?></small></td>
                <td><span class="admin-pill <?= e(admin_status_class($email['status'])) ?>"><?= e($email['status']) ?></span></td>
                <td><?= e(($email['related_type'] ?: '—') . ($email['related_id'] ? ' #' . $email['related_id'] : '')) ?></td>
                <td><?= e((string) $email['queued_at']) ?></td>
                <td><?= e((string) ($email['attempts'] ?? 0)) ?><small><?= e((string) ($email['last_attempt_at'] ?? '')) ?></small></td>
                <td><small><?= e(mb_substr((string) ($email['error_message'] ?? ''), 0, 120)) ?></small></td>
                <td>
                    <form method="post" class="admin-inline-form">
                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="mark_outbox">
                        <input type="hidden" name="id" value="<?= (int) $email['id'] ?>">
                        <select name="status"><?php foreach ($emailStatuses as $status): ?><option value="<?= e($status) ?>" <?= $email['status'] === $status ? 'selected' : '' ?>><?= e(ucfirst($status)) ?></option><?php endforeach; ?></select>
                        <button class="admin-table-btn" type="submit">Update</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
    <form method="post" class="admin-inline-form admin-cleanup-form" data-admin-confirm="Clear sent/skipped logs older than 30 days?">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="clear_old_sent">
        <button class="admin-table-btn danger" type="submit">Clear old sent logs</button>
    </form>
</section>
<?php admin_footer(); ?>
