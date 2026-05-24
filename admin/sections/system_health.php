<?php
require_once __DIR__ . '/../includes/layout.php';
$ctx = admin_boot('system_health');
$pdo = $ctx['pdo'];

$checks = admin_system_health_checks($pdo, $ctx);
$summary = admin_system_health_summary($checks);

$filter = strtolower(trim((string) ($_GET['severity'] ?? 'all')));
$allowedFilters = ['all', 'critical', 'danger', 'warning', 'info', 'good'];
if (!in_array($filter, $allowedFilters, true)) {
    $filter = 'all';
}

$visibleChecks = array_values(array_filter($checks, static function (array $check) use ($filter): bool {
    return $filter === 'all' || (string) ($check['severity'] ?? '') === $filter;
}));

$groups = [];
foreach ($visibleChecks as $check) {
    $groups[(string) $check['group']][] = $check;
}

$scoreLabel = $summary['score'] >= 90 ? 'Excellent' : ($summary['score'] >= 75 ? 'Healthy' : ($summary['score'] >= 55 ? 'Needs review' : 'Action needed'));
$scoreClass = $summary['score'] >= 90 ? 'good' : ($summary['score'] >= 75 ? 'info' : ($summary['score'] >= 55 ? 'warning' : 'danger'));

admin_header('System Health', 'A read-only audit for database compatibility, checkout readiness, catalog hygiene, security flags, email queue, and support backlog.', 'system_health');
?>
<section class="admin-metrics-grid" aria-label="System health summary">
    <?php admin_metric_card('Health score', (string) $summary['score'] . '%', 'health_and_safety', $scoreLabel); ?>
    <?php admin_metric_card('Critical', (string) $summary['critical'], 'priority_high', 'Blocking issues'); ?>
    <?php admin_metric_card('Danger', (string) $summary['danger'], 'error', 'High priority'); ?>
    <?php admin_metric_card('Warnings', (string) $summary['warning'], 'warning', 'Review soon'); ?>
    <?php admin_metric_card('Good checks', (string) $summary['good'], 'verified', 'Passing'); ?>
</section>

<section class="admin-card glass-panel admin-health-hero">
    <div>
        <p class="admin-eyebrow">Read-only diagnostics</p>
        <h2>Store audit snapshot</h2>
        <p>This page does not change data. It highlights launch blockers, risky settings, incomplete storefront configuration, and operational backlog so you can fix each issue from its related section.</p>
    </div>
    <div class="admin-health-score is-<?= e($scoreClass) ?>">
        <strong><?= (int) $summary['score'] ?>%</strong>
        <span><?= e($scoreLabel) ?></span>
    </div>
</section>

<section class="admin-card glass-panel">
    <div class="admin-section-head">
        <div><p class="admin-eyebrow">Filters</p><h2>Audit checks</h2></div>
        <a class="admin-text-link" href="<?= e(admin_page_url('system_health')) ?>">Run again</a>
    </div>
    <div class="admin-health-tabs">
        <?php foreach (['all' => 'All', 'critical' => 'Critical', 'danger' => 'Danger', 'warning' => 'Warnings', 'info' => 'Info', 'good' => 'Good'] as $key => $label): ?>
            <a class="<?= $filter === $key ? 'is-active' : '' ?>" href="<?= e(admin_page_url('system_health', $key === 'all' ? [] : ['severity' => $key])) ?>">
                <span><?= e($label) ?></span>
                <strong><?= $key === 'all' ? (int) $summary['total'] : (int) ($summary[$key] ?? 0) ?></strong>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if (!$visibleChecks): ?>
        <?php admin_empty_state('No checks in this filter', 'Choose another severity filter to review the rest of the audit.'); ?>
    <?php else: ?>
        <div class="admin-health-groups">
            <?php foreach ($groups as $group => $items): ?>
                <section class="admin-health-group">
                    <div class="admin-health-group-title">
                        <h3><?= e($group) ?></h3>
                        <span><?= count($items) ?> check(s)</span>
                    </div>
                    <div class="admin-health-list">
                        <?php foreach ($items as $check): ?>
                            <?php
                                $severity = (string) ($check['severity'] ?? 'info');
                                $pillClass = match ($severity) {
                                    'critical', 'danger' => 'danger',
                                    'warning' => 'info',
                                    'good' => 'good',
                                    default => 'neutral',
                                };
                            ?>
                            <article class="admin-health-check is-<?= e($severity) ?>">
                                <div class="admin-health-check-icon"><span class="material-symbols-outlined"><?= e((string) $check['icon']) ?></span></div>
                                <div class="admin-health-check-body">
                                    <div class="admin-health-check-title">
                                        <strong><?= e((string) $check['title']) ?></strong>
                                        <span class="admin-pill <?= e($pillClass) ?>"><?= e($severity) ?></span>
                                    </div>
                                    <p><?= e((string) $check['body']) ?></p>
                                    <?php if (($check['value'] ?? null) !== null): ?><small>Value: <?= e((string) $check['value']) ?></small><?php endif; ?>
                                </div>
                                <a class="admin-ghost-btn" href="<?= e((string) $check['url']) ?>">Open</a>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="admin-two-col">
    <article class="admin-card glass-panel">
        <div class="admin-section-head"><div><p class="admin-eyebrow">Recommended flow</p><h2>How to use this audit</h2></div></div>
        <div class="admin-action-list">
            <a href="<?= e(admin_page_url('shipping_payments')) ?>"><span>Fix checkout blockers first</span><strong>Shipping / Payments</strong></a>
            <a href="<?= e(admin_page_url('products')) ?>"><span>Review catalog status, images, and stock</span><strong>Products</strong></a>
            <a href="<?= e(admin_page_url('seo')) ?>"><span>Complete missing metadata</span><strong>SEO / Pages</strong></a>
            <a href="<?= e(admin_page_url('email')) ?>"><span>Process or fix queued mail</span><strong>Email Center</strong></a>
        </div>
    </article>

    <article class="admin-card glass-panel">
        <div class="admin-section-head"><div><p class="admin-eyebrow">Severity guide</p><h2>What the labels mean</h2></div></div>
        <div class="admin-health-legend">
            <div><span class="admin-pill danger">critical</span><p>Likely breaks launch or checkout.</p></div>
            <div><span class="admin-pill danger">danger</span><p>High-risk configuration that should be fixed.</p></div>
            <div><span class="admin-pill info">warning</span><p>Important quality or operational issue.</p></div>
            <div><span class="admin-pill neutral">info</span><p>Useful signal, not necessarily broken.</p></div>
            <div><span class="admin-pill good">good</span><p>Check passed.</p></div>
        </div>
    </article>
</section>
<?php admin_footer(); ?>
