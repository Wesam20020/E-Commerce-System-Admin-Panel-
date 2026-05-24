<?php
require_once __DIR__ . '/../includes/layout.php';
$ctx = admin_boot('customers');
$pdo = $ctx['pdo'];
$currency = $ctx['siteCurrency'];
$q = trim((string) ($_GET['q'] ?? ''));
$where = '';
$params = [];
if ($q !== '') { $where = 'WHERE u.name LIKE :q OR u.email LIKE :q OR u.phone LIKE :q'; $params['q'] = '%' . $q . '%'; }
$customers = admin_rows($pdo, "SELECT u.id, u.name, u.email, u.phone, u.city, u.country, u.created_at, COUNT(o.id) order_count, COALESCE(SUM(o.total),0) total_spent FROM users u LEFT JOIN orders o ON o.user_id = u.id {$where} GROUP BY u.id, u.name, u.email, u.phone, u.city, u.country, u.created_at ORDER BY u.created_at DESC LIMIT 100", $params);
admin_header('Customers', 'Review customer accounts, order counts, and purchase value.', 'customers');
?>
<section class="admin-card glass-panel">
    <div class="admin-section-head"><div><p class="admin-eyebrow">People</p><h2>Customers</h2></div></div>
    <form method="get" class="admin-filter-bar"><input name="q" value="<?= e($q) ?>" placeholder="Search name, email, phone..."><button class="admin-ghost-btn" type="submit">Search</button></form>
    <?php if (!$customers): ?><?php admin_empty_state('No customers found', 'Registered customer accounts will appear here.'); ?><?php else: ?>
    <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Customer</th><th>Phone</th><th>Location</th><th>Orders</th><th>Total spent</th><th>Joined</th></tr></thead><tbody>
    <?php foreach ($customers as $customer): ?>
        <tr><td><strong><?= e($customer['name']) ?></strong><small><?= e($customer['email']) ?></small></td><td><?= e($customer['phone'] ?: '—') ?></td><td><?= e(trim(($customer['city'] ?? '') . ', ' . ($customer['country'] ?? ''), ', ') ?: '—') ?></td><td><?= (int)$customer['order_count'] ?></td><td><?= e(admin_money($customer['total_spent'], $currency)) ?></td><td><?= e((string)$customer['created_at']) ?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
</section>
<?php admin_footer(); ?>
