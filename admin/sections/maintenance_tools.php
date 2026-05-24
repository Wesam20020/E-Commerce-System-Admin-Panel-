<?php
require_once __DIR__ . '/../includes/layout.php';
$ctx = admin_boot('maintenance_tools');
$pdo = $ctx['pdo'];

if (is_post_request()) {
    verify_csrf_or_fail($_POST['_csrf'] ?? null);
    $action = preg_replace('/[^a-z0-9_]+/', '', (string) ($_POST['action'] ?? ''));
    try {
        $result = admin_run_maintenance_action($pdo, $action);
        flash_set('success', $result['label'] . ' — ' . (int) $result['affected'] . ' row(s) affected.');
        admin_redirect('maintenance_tools');
    } catch (Throwable $e) {
        admin_flash_from_exception($e, 'Maintenance action could not be completed.');
        admin_redirect('maintenance_tools');
    }
}

$dbName = admin_database_name($pdo);
$dbTables = admin_database_tables($pdo);
$dbSize = admin_database_size_bytes($pdo);
$lastBackup = admin_last_maintenance_run($pdo, 'database_backup');
$lastMaintenance = admin_last_maintenance_run($pdo);
$candidates = admin_maintenance_candidates($pdo);
$recentRuns = admin_recent_maintenance_runs($pdo, 12);
$csrf = csrf_token();
$totalCleanup = array_sum(array_map(static fn(array $item): int => (int) $item['count'], $candidates));
$lastBackupLabel = $lastBackup ? (string) $lastBackup['created_at'] : 'Never recorded';

admin_header('Maintenance Tools', 'Owner-only tools for safe database backups, cleanup, and operational housekeeping.', 'maintenance_tools');
?>
<section class="admin-metrics-grid" aria-label="Maintenance metrics">
    <?php admin_metric_card('Database', $dbName ?: 'Unknown', 'database', count($dbTables) . ' tables'); ?>
    <?php admin_metric_card('DB size', admin_bytes_label($dbSize), 'sd_storage', 'Approximate'); ?>
    <?php admin_metric_card('Cleanup candidates', (string) $totalCleanup, 'cleaning_services', 'Old records'); ?>
    <?php admin_metric_card('Last backup', $lastBackupLabel, 'backup', $lastBackup ? 'Recorded in admin log' : 'No backup log'); ?>
</section>

<section class="admin-two-col admin-maintenance-grid">
    <article class="admin-card glass-panel">
        <div class="admin-section-head">
            <div><p class="admin-eyebrow">Database backup</p><h2>Download SQL backup</h2></div>
            <span class="admin-pill danger">Owner only</span>
        </div>
        <p class="admin-muted-block">Exports the current MySQL database as a SQL file containing table structure and data. Keep this file private because it may contain customer, order, and admin data.</p>
        <div class="admin-action-list compact">
            <a href="#"><span>Database name</span><strong><?= e($dbName ?: 'Unknown') ?></strong></a>
            <a href="#"><span>Tables included</span><strong><?= count($dbTables) ?></strong></a>
            <a href="#"><span>Approx. size</span><strong><?= e(admin_bytes_label($dbSize)) ?></strong></a>
        </div>
        <div class="admin-form-actions">
            <a class="admin-primary-btn" href="<?= e('backup.php?_csrf=' . rawurlencode($csrf)) ?>" download><span class="material-symbols-outlined">download</span> Download SQL backup</a>
        </div>
    </article>

    <article class="admin-card glass-panel">
        <div class="admin-section-head">
            <div><p class="admin-eyebrow">Housekeeping</p><h2>Safe cleanup actions</h2></div>
            <span class="admin-pill info">No catalog/order deletion</span>
        </div>
        <p class="admin-muted-block">These actions only remove old operational records such as sent email history, dismissed alerts, and old activity logs. They do not delete products, orders, customers, media files, or settings.</p>
        <div class="admin-maintenance-actions">
            <?php foreach ($candidates as $item): ?>
                <form method="post" class="admin-maintenance-action" data-admin-form>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="<?= e($item['action']) ?>">
                    <div>
                        <strong><?= e($item['title']) ?></strong>
                        <p><?= e($item['desc']) ?></p>
                    </div>
                    <div class="admin-maintenance-action-side">
                        <span class="admin-pill <?= ((int) $item['count']) > 0 ? 'warning' : 'good' ?>"><?= (int) $item['count'] ?> rows</span>
                        <button type="submit" class="admin-ghost-btn" <?= ((int) $item['count']) <= 0 ? 'disabled' : '' ?>>Run</button>
                    </div>
                </form>
            <?php endforeach; ?>
        </div>
    </article>
</section>

<section class="admin-card glass-panel">
    <div class="admin-section-head">
        <div><p class="admin-eyebrow">Database tables</p><h2>Backup coverage</h2></div>
        <span class="admin-pill neutral"><?= count($dbTables) ?> table(s)</span>
    </div>
    <?php if (!$dbTables): ?>
        <?php admin_empty_state('No tables detected', 'The connected database did not return any base tables.'); ?>
    <?php else: ?>
        <div class="admin-chip-cloud">
            <?php foreach ($dbTables as $table): ?>
                <span><?= e($table) ?></span>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="admin-card glass-panel">
    <div class="admin-section-head">
        <div><p class="admin-eyebrow">Maintenance history</p><h2>Recent runs</h2></div>
        <?php if ($lastMaintenance): ?><span class="admin-pill info">Latest: <?= e((string) $lastMaintenance['created_at']) ?></span><?php endif; ?>
    </div>
    <?php if (!$recentRuns): ?>
        <?php admin_empty_state('No maintenance history yet', 'Backups and cleanup actions will be recorded here automatically.'); ?>
    <?php else: ?>
        <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Action</th><th>Affected</th><th>Admin</th><th>Details</th><th>Date</th></tr></thead><tbody>
            <?php foreach ($recentRuns as $run): ?>
                <tr>
                    <td><strong><?= e(str_replace('_', ' ', (string) $run['action'])) ?></strong></td>
                    <td><?= (int) $run['affected_rows'] ?></td>
                    <td><?= e((string) ($run['admin_email'] ?: 'System')) ?></td>
                    <td><?= e((string) ($run['details'] ?: '—')) ?></td>
                    <td><?= e((string) $run['created_at']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
</section>
<?php admin_footer(); ?>
