<?php
require_once __DIR__ . '/../includes/layout.php';
$ctx = admin_boot('notifications');
$pdo = $ctx['pdo'];
$showDismissed = (string) ($_GET['dismissed'] ?? '') === '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_fail($_POST['_csrf'] ?? null);
    $action = (string) ($_POST['admin_action'] ?? '');
    try {
        if ($action === 'notification_dismiss') {
            admin_dismiss_notification($pdo, (string) ($_POST['notification_key'] ?? ''));
            flash_set('success', 'Notification dismissed.');
        } elseif ($action === 'notification_restore') {
            admin_restore_notification($pdo, (string) ($_POST['notification_key'] ?? ''));
            flash_set('success', 'Notification restored.');
        } elseif ($action === 'notification_dismiss_all') {
            foreach (admin_system_notifications($pdo, $ctx, false) as $item) {
                admin_dismiss_notification($pdo, (string) $item['key']);
            }
            flash_set('success', 'All current notifications dismissed.');
        } elseif ($action === 'notification_restore_all') {
            $email = mb_strtolower(trim((string) admin_current_email()));
            if ($email !== '') {
                $stmt = $pdo->prepare('DELETE FROM admin_notification_dismissals WHERE LOWER(admin_email) = :email');
                $stmt->execute(['email' => $email]);
            }
            flash_set('success', 'Dismissed notifications restored.');
        }
    } catch (Throwable $e) {
        admin_flash_from_exception($e, 'Notification action could not be completed.');
    }
    admin_redirect('notifications', $showDismissed ? ['dismissed' => '1'] : []);
}

$notifications = admin_system_notifications($pdo, $ctx, $showDismissed);
$counts = admin_notification_counts($pdo, $ctx);
$recentActivity = admin_rows($pdo, 'SELECT * FROM admin_activity_logs ORDER BY created_at DESC, id DESC LIMIT 12');

admin_header('Activity Center', 'Operational notifications, store risks, and recent admin activity in one focused workspace.', 'notifications');
?>
<section class="admin-metrics-grid" aria-label="Notification metrics">
    <?php admin_metric_card('Active alerts', (string) $counts['active'], 'notifications_active', 'Visible to your role'); ?>
    <?php admin_metric_card('Critical', (string) $counts['critical'], 'priority_high', 'Needs immediate review'); ?>
    <?php admin_metric_card('Dismissed', (string) $counts['dismissed'], 'notifications_off', 'Hidden by you'); ?>
    <?php admin_metric_card('Signals tracked', (string) $counts['total'], 'hub', 'Role-aware checks'); ?>
</section>

<section class="admin-card glass-panel admin-notification-toolbar">
    <div>
        <p class="admin-eyebrow">Notification controls</p>
        <h2>Activity Center</h2>
        <p>Dismissed items stay hidden until their count changes or you restore them. This keeps the dashboard clean without losing important operational checks.</p>
    </div>
    <div class="admin-page-actions">
        <a class="admin-ghost-btn" href="<?= e(admin_page_url('notifications', $showDismissed ? [] : ['dismissed' => '1'])) ?>"><?= $showDismissed ? 'Hide dismissed' : 'Show dismissed' ?></a>
        <?php if ($counts['active'] > 0): ?>
            <form method="post" class="admin-inline-form" data-admin-confirm="Dismiss all currently visible notifications?">
                <?= csrf_field() ?>
                <input type="hidden" name="admin_action" value="notification_dismiss_all">
                <button type="submit">Dismiss current</button>
            </form>
        <?php endif; ?>
        <?php if ($counts['dismissed'] > 0): ?>
            <form method="post" class="admin-inline-form">
                <?= csrf_field() ?>
                <input type="hidden" name="admin_action" value="notification_restore_all">
                <button type="submit">Restore all</button>
            </form>
        <?php endif; ?>
    </div>
</section>

<section class="admin-two-col admin-notification-layout">
    <article class="admin-card glass-panel">
        <div class="admin-section-head">
            <div><p class="admin-eyebrow">Notifications</p><h2>Operational signals</h2></div>
            <a class="admin-text-link" href="<?= e(admin_page_url('index')) ?>">Dashboard</a>
        </div>
        <?php if (!$notifications): ?>
            <?php admin_empty_state('No visible notifications', $showDismissed ? 'There are no notifications matching this view.' : 'Your store has no active operational alerts for your role right now.'); ?>
        <?php else: ?>
            <div class="admin-notification-list">
                <?php foreach ($notifications as $item): ?>
                    <article class="admin-notification-card is-<?= e((string) $item['severity']) ?> <?= !empty($item['dismissed']) ? 'is-dismissed' : '' ?>">
                        <div class="admin-notification-icon"><span class="material-symbols-outlined"><?= e((string) $item['icon']) ?></span></div>
                        <div class="admin-notification-body">
                            <div class="admin-notification-titleline">
                                <strong><?= e((string) $item['title']) ?></strong>
                                <span class="admin-pill <?= e($item['severity'] === 'danger' ? 'danger' : ($item['severity'] === 'warning' ? 'info' : 'neutral')) ?>"><?= e((string) $item['severity']) ?></span>
                            </div>
                            <p><?= e((string) $item['body']) ?></p>
                            <small>Signal: <?= e((string) $item['source']) ?> · Value: <?= (int) $item['value'] ?></small>
                            <div class="admin-row-actions admin-notification-actions">
                                <a href="<?= e((string) $item['url']) ?>">Open section</a>
                                <?php if (!empty($item['dismissed'])): ?>
                                    <form method="post" class="admin-inline-form">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="admin_action" value="notification_restore">
                                        <input type="hidden" name="notification_key" value="<?= e((string) $item['key']) ?>">
                                        <button type="submit">Restore</button>
                                    </form>
                                <?php else: ?>
                                    <form method="post" class="admin-inline-form">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="admin_action" value="notification_dismiss">
                                        <input type="hidden" name="notification_key" value="<?= e((string) $item['key']) ?>">
                                        <button type="submit">Dismiss</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </article>

    <article class="admin-card glass-panel">
        <div class="admin-section-head"><div><p class="admin-eyebrow">Audit trail</p><h2>Recent admin activity</h2></div></div>
        <?php if (!$recentActivity): ?>
            <?php admin_empty_state('No activity yet', 'Admin actions such as product edits, order updates, and stock changes will appear here.'); ?>
        <?php else: ?>
            <div class="admin-timeline compact">
                <?php foreach ($recentActivity as $activity): ?>
                    <article>
                        <span class="admin-timeline-dot"></span>
                        <div>
                            <strong><?= e(str_replace('_', ' ', ucfirst((string) $activity['action']))) ?></strong>
                            <?php if (!empty($activity['details'])): ?><p><?= e((string) $activity['details']) ?></p><?php endif; ?>
                            <small><?= e(($activity['admin_email'] ?: 'Admin') . ' · ' . ($activity['entity_type'] ?: 'system') . ($activity['entity_id'] ? ' #' . $activity['entity_id'] : '') . ' · ' . $activity['created_at']) ?></small>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </article>
</section>
<?php admin_footer(); ?>
