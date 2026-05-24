<?php
require_once __DIR__ . '/../includes/layout.php';
$ctx = admin_boot('orders');
$pdo = $ctx['pdo'];
$currency = $ctx['siteCurrency'];
store_ensure_checkout_tables($pdo);

$allowedStatuses = store_order_status_values();
$allowedPayments = store_payment_status_values();
$statusLabels = array_map(static fn(array $item): string => (string) $item['label'], store_order_status_options());
$paymentLabels = array_map(static fn(array $item): string => (string) $item['label'], store_payment_status_options());

function admin_order_tracking_href(?string $url): string
{
    $url = trim((string) $url);
    if ($url === '') {
        return '';
    }
    return filter_var($url, FILTER_VALIDATE_URL) ? mb_substr($url, 0, 500) : '';
}

if (is_post_request()) {
    verify_csrf_or_fail($_POST['_csrf'] ?? null);
    $action = (string) ($_POST['admin_action'] ?? 'order_update');
    try {
        if ($action === 'order_bulk_update') {
            $ids = admin_int_array_input('order_ids');
            if (!$ids) {
                throw new RuntimeException('Select at least one order.');
            }
            $bulkStatus = (string) ($_POST['bulk_status'] ?? '');
            $bulkPayment = (string) ($_POST['bulk_payment_status'] ?? '');
            if ($bulkStatus === '' && $bulkPayment === '') {
                throw new RuntimeException('Choose a status or payment update.');
            }
            if ($bulkStatus !== '' && !in_array($bulkStatus, $allowedStatuses, true)) {
                throw new RuntimeException('Invalid bulk order status.');
            }
            if ($bulkPayment !== '' && !in_array($bulkPayment, $allowedPayments, true)) {
                throw new RuntimeException('Invalid bulk payment status.');
            }
            [$placeholders, $bulkParams] = admin_sql_placeholders($ids, 'order');
            $inSql = implode(',', $placeholders);
            $rows = admin_rows($pdo, "SELECT id, order_number, status, payment_status FROM orders WHERE id IN ({$inSql})", $bulkParams);
            $note = admin_clean_text('bulk_note', 1200) ?: 'Bulk order update.';
            $updateParts = [];
            $updateParams = $bulkParams;
            if ($bulkStatus !== '') {
                $updateParts[] = 'status = :bulk_status';
                $updateParams['bulk_status'] = $bulkStatus;
            }
            if ($bulkPayment !== '') {
                $updateParts[] = 'payment_status = :bulk_payment_status';
                $updateParams['bulk_payment_status'] = $bulkPayment;
            }
            $pdo->prepare('UPDATE orders SET ' . implode(', ', $updateParts) . " WHERE id IN ({$inSql})")->execute($updateParams);
            foreach ($rows as $row) {
                $nextStatus = $bulkStatus !== '' ? $bulkStatus : (string) $row['status'];
                $nextPayment = $bulkPayment !== '' ? $bulkPayment : (string) $row['payment_status'];
                admin_record_order_event($pdo, (int) $row['id'], $nextStatus, $nextPayment, $note);
                admin_log_activity($pdo, 'order_bulk_updated', 'order', (int) $row['id'], $row['order_number'] . ': ' . $note);
            }
            flash_set('success', count($rows) . ' orders updated.');
            admin_redirect('orders');
        }

        $id = admin_int_input('order_id');
        $order = null;
        if ($id > 0) {
            $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $id]);
            $order = $stmt->fetch();
        }
        if (!$order) {
            throw new RuntimeException('Order was not found.');
        }

        if ($action === 'order_update') {
            $status = store_normalize_order_status((string) ($_POST['status'] ?? 'pending'));
            $payment = store_normalize_payment_status((string) ($_POST['payment_status'] ?? 'unpaid'));
            $note = admin_clean_text('event_note', 1200);
            $tracking = admin_clean_text('tracking_number', 190) ?: null;
            $carrier = admin_clean_text('tracking_carrier', 120) ?: null;
            $trackingUrl = trim((string) ($_POST['tracking_url'] ?? ''));
            if ($trackingUrl !== '' && !filter_var($trackingUrl, FILTER_VALIDATE_URL)) {
                throw new RuntimeException('Tracking URL must be a valid URL or left empty.');
            }
            $trackingUrl = $trackingUrl !== '' ? mb_substr($trackingUrl, 0, 500) : null;
            $internalNotes = mb_substr(trim((string) ($_POST['internal_notes'] ?? '')), 0, 4000) ?: null;

            $stmt = $pdo->prepare('UPDATE orders SET status = :status, payment_status = :payment_status, tracking_number = :tracking_number, tracking_carrier = :tracking_carrier, tracking_url = :tracking_url, internal_notes = :internal_notes WHERE id = :id LIMIT 1');
            $stmt->execute([
                'status' => $status,
                'payment_status' => $payment,
                'tracking_number' => $tracking,
                'tracking_carrier' => $carrier,
                'tracking_url' => $trackingUrl,
                'internal_notes' => $internalNotes,
                'id' => $id,
            ]);

            $changed = [];
            if ((string) $order['status'] !== $status) {
                $changed[] = 'status ' . $order['status'] . ' → ' . $status;
            }
            if ((string) $order['payment_status'] !== $payment) {
                $changed[] = 'payment ' . $order['payment_status'] . ' → ' . $payment;
            }
            if ((string) ($order['tracking_number'] ?? '') !== (string) ($tracking ?? '')) {
                $changed[] = 'tracking updated';
            }
            $eventNote = $note ?: ($changed ? implode(', ', $changed) : 'Order details updated.');
            admin_record_order_event($pdo, $id, $status, $payment, $eventNote);
            admin_log_activity($pdo, 'order_updated', 'order', $id, $order['order_number'] . ': ' . $eventNote);
            if ($changed) {
                store_queue_email($pdo, 'order_status_update', (string) ($order['email'] ?? ''), (string) ($order['full_name'] ?? ''), [
                    'order_number' => $order['order_number'] ?? '',
                    'customer_name' => $order['full_name'] ?? 'Customer',
                    'customer_email' => $order['email'] ?? '',
                    'order_status' => $statusLabels[$status] ?? ucfirst($status),
                    'payment_status' => $paymentLabels[$payment] ?? ucfirst($payment),
                    'tracking_number' => $tracking ?: 'Not available yet',
                ], 'order', $id);
            }
            flash_set('success', 'Order updated.');
        } elseif ($action === 'order_note') {
            $note = trim((string) ($_POST['note'] ?? ''));
            if ($note === '') {
                throw new RuntimeException('Note cannot be empty.');
            }
            admin_record_order_event($pdo, $id, (string) $order['status'], (string) $order['payment_status'], mb_substr($note, 0, 2000), false);
            admin_log_activity($pdo, 'order_note_added', 'order', $id, $order['order_number']);
            flash_set('success', 'Internal note added.');
        } else {
            throw new RuntimeException('Unknown order action.');
        }
    } catch (Throwable $e) {
        admin_flash_from_exception($e);
    }

    $query = [];
    if (isset($_POST['view'])) {
        $query['view'] = (int) $_POST['view'];
    }
    admin_redirect('orders', $query);
}

$status = (string) ($_GET['status'] ?? 'all');
$payment = (string) ($_GET['payment'] ?? 'all');
$q = trim((string) ($_GET['q'] ?? ''));
$viewId = isset($_GET['view']) && is_numeric($_GET['view']) ? (int) $_GET['view'] : 0;

$where = [];
$params = [];
if ($status !== 'all' && in_array($status, $allowedStatuses, true)) {
    $where[] = 'status = :status';
    $params['status'] = $status;
}
if ($payment !== 'all' && in_array($payment, $allowedPayments, true)) {
    $where[] = 'payment_status = :payment';
    $params['payment'] = $payment;
}
if ($q !== '') {
    $where[] = '(order_number LIKE :q OR full_name LIKE :q OR email LIKE :q OR phone LIKE :q OR tracking_number LIKE :q OR shipping_method_name LIKE :q OR payment_method_name LIKE :q OR coupon_code LIKE :q)';
    $params['q'] = '%' . $q . '%';
}

$sql = 'SELECT * FROM orders';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY created_at DESC, id DESC LIMIT 100';
$orders = admin_rows($pdo, $sql, $params);

$metrics = [
    'pending' => (int) admin_scalar($pdo, "SELECT COUNT(*) FROM orders WHERE status = 'pending'"),
    'processing' => (int) admin_scalar($pdo, "SELECT COUNT(*) FROM orders WHERE status = 'processing'"),
    'shipped' => (int) admin_scalar($pdo, "SELECT COUNT(*) FROM orders WHERE status = 'shipped'"),
    'unpaid' => (int) admin_scalar($pdo, "SELECT COUNT(*) FROM orders WHERE payment_status = 'unpaid'"),
    'paid_revenue' => (float) admin_scalar($pdo, "SELECT COALESCE(SUM(total),0) FROM orders WHERE payment_status = 'paid'"),
    'today' => (int) admin_scalar($pdo, 'SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE()'),
];

$viewOrder = null;
$orderItems = [];
$orderEvents = [];
if ($viewId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $viewId]);
    $viewOrder = $stmt->fetch() ?: null;
    if ($viewOrder) {
        $orderItems = admin_rows($pdo, 'SELECT * FROM order_items WHERE order_id = :order_id ORDER BY id ASC', ['order_id' => $viewId]);
        $orderEvents = admin_rows($pdo, 'SELECT * FROM order_status_events WHERE order_id = :order_id ORDER BY created_at DESC, id DESC LIMIT 50', ['order_id' => $viewId]);
    }
}

admin_header('Orders', 'Track orders, payment state, fulfillment workflow, customer details, invoices, and order history.', 'orders');
?>
<section class="admin-metrics-grid">
    <?php admin_metric_card('Today', (string) $metrics['today'], 'today', 'New orders'); ?>
    <?php admin_metric_card('Pending', (string) $metrics['pending'], 'pending_actions', 'Needs review'); ?>
    <?php admin_metric_card('Processing', (string) $metrics['processing'], 'local_shipping', 'In fulfillment'); ?>
    <?php admin_metric_card('Shipped', (string) $metrics['shipped'], 'package_2', 'On the way'); ?>
    <?php admin_metric_card('Unpaid', (string) $metrics['unpaid'], 'credit_card_off', 'Payment follow-up'); ?>
    <?php admin_metric_card('Paid revenue', admin_money($metrics['paid_revenue'], $currency), 'payments', 'Paid orders'); ?>
</section>

<?php if ($viewOrder): ?>
<?php
$trackingHref = admin_order_tracking_href($viewOrder['tracking_url'] ?? null);
$shippingText = trim((string) ($viewOrder['shipping_method_name'] ?? '')) ?: 'No shipping method';
$paymentText = trim((string) ($viewOrder['payment_method_name'] ?? '')) ?: (trim((string) ($viewOrder['payment_method'] ?? '')) ?: 'No payment method');
?>
<section class="admin-card glass-panel admin-order-detail-card">
    <div class="admin-section-head admin-order-titlebar">
        <div>
            <p class="admin-eyebrow">Order detail</p>
            <h2><?= e($viewOrder['order_number']) ?></h2>
            <div class="admin-inline-pills">
                <span class="admin-pill <?= e(admin_status_class($viewOrder['status'])) ?>"><?= e($statusLabels[$viewOrder['status']] ?? ucfirst((string) $viewOrder['status'])) ?></span>
                <span class="admin-pill <?= e(admin_status_class($viewOrder['payment_status'])) ?>"><?= e($paymentLabels[$viewOrder['payment_status']] ?? ucfirst((string) $viewOrder['payment_status'])) ?></span>
                <span class="admin-muted-chip"><?= e((string) $viewOrder['created_at']) ?></span>
            </div>
        </div>
        <div class="admin-page-actions">
            <a class="admin-ghost-btn" href="<?= e('order_invoice.php?order_id=' . (int) $viewOrder['id']) ?>" target="_blank" rel="noopener">Invoice</a>
            <a class="admin-ghost-btn" href="<?= e(admin_page_url('orders')) ?>">Close details</a>
        </div>
    </div>

    <div class="admin-detail-grid admin-order-detail-grid">
        <article class="admin-detail-box">
            <span class="material-symbols-outlined">person</span>
            <div><strong><?= e($viewOrder['full_name']) ?></strong><p><?= e($viewOrder['email']) ?><?= $viewOrder['phone'] ? ' · ' . e($viewOrder['phone']) : '' ?></p></div>
        </article>
        <article class="admin-detail-box">
            <span class="material-symbols-outlined">home_pin</span>
            <div><strong><?= e($viewOrder['city'] . ', ' . $viewOrder['country']) ?></strong><p><?= e($viewOrder['address_line1']) ?><?= $viewOrder['address_line2'] ? ' · ' . e($viewOrder['address_line2']) : '' ?></p></div>
        </article>
        <article class="admin-detail-box">
            <span class="material-symbols-outlined">payments</span>
            <div><strong><?= e(admin_money((float) $viewOrder['total'], $currency)) ?></strong><p>Subtotal <?= e(admin_money((float) $viewOrder['subtotal'], $currency)) ?> · Shipping <?= e(admin_money((float) ($viewOrder['shipping_total'] ?? 0), $currency)) ?></p></div>
        </article>
        <article class="admin-detail-box">
            <span class="material-symbols-outlined">local_shipping</span>
            <div><strong><?= e($shippingText) ?></strong><p><?= e(($viewOrder['tracking_carrier'] ?? '') ? 'Carrier: ' . $viewOrder['tracking_carrier'] : 'Carrier not set') ?><?= ($viewOrder['tracking_number'] ?? '') ? ' · ' . e('Tracking: ' . $viewOrder['tracking_number']) : '' ?></p></div>
        </article>
        <article class="admin-detail-box">
            <span class="material-symbols-outlined">credit_card</span>
            <div><strong><?= e($paymentText) ?></strong><p><?= e('Payment status: ' . ($paymentLabels[$viewOrder['payment_status']] ?? $viewOrder['payment_status'])) ?></p></div>
        </article>
        <article class="admin-detail-box">
            <span class="material-symbols-outlined">local_offer</span>
            <div><strong><?= e(!empty($viewOrder['coupon_code']) ? $viewOrder['coupon_code'] : 'No coupon') ?></strong><p><?= !empty($viewOrder['coupon_code']) ? e('Discount: -' . admin_money((float) ($viewOrder['discount_total'] ?? 0), $currency)) : 'No promotional discount used' ?></p></div>
        </article>
        <article class="admin-detail-box">
            <span class="material-symbols-outlined">link</span>
            <div><strong>Tracking link</strong><p><?php if ($trackingHref !== ''): ?><a class="admin-text-link" href="<?= e($trackingHref) ?>" target="_blank" rel="noopener">Open carrier tracking</a><?php else: ?>No tracking URL yet<?php endif; ?></p></div>
        </article>
    </div>

    <div class="admin-two-col-wide admin-order-workspace">
        <div class="admin-order-left-stack">
            <section class="admin-inner-card">
                <h3 class="admin-subtitle">Items</h3>
                <?php if (!$orderItems): ?>
                    <?php admin_empty_state('No order items', 'This order has no items attached.'); ?>
                <?php else: ?>
                    <div class="admin-table-wrap">
                        <table class="admin-table admin-order-items-table">
                            <thead><tr><th>Item</th><th>Qty</th><th>Unit</th><th>Total</th></tr></thead>
                            <tbody>
                            <?php foreach ($orderItems as $item): ?>
                                <tr>
                                    <?php $itemOptionsLabel = store_options_label(store_options_from_json($item['selected_options_json'] ?? null)); ?>
                                    <td><strong><?= e($item['product_name']) ?></strong><small><?= $item['product_id'] ? '#' . (int) $item['product_id'] : 'Deleted product' ?><?= $itemOptionsLabel !== '' ? ' · ' . e($itemOptionsLabel) : '' ?></small></td>
                                    <td><?= (int) $item['qty'] ?></td>
                                    <td><?= e(admin_money((float) $item['unit_price'], $currency)) ?></td>
                                    <td><strong><?= e(admin_money((float) $item['line_total'], $currency)) ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

            <section class="admin-inner-card">
                <h3 class="admin-subtitle">Totals</h3>
                <div class="admin-total-breakdown">
                    <div><span>Subtotal</span><strong><?= e(admin_money((float) $viewOrder['subtotal'], $currency)) ?></strong></div>
                    <div><span>Shipping</span><strong><?= e(admin_money((float) ($viewOrder['shipping_total'] ?? 0), $currency)) ?></strong></div>
                    <div><span>Discount<?= !empty($viewOrder['coupon_code']) ? ' · ' . e($viewOrder['coupon_code']) : '' ?></span><strong>-<?= e(admin_money((float) ($viewOrder['discount_total'] ?? 0), $currency)) ?></strong></div>
                    <div><span>Tax</span><strong><?= e(admin_money((float) ($viewOrder['tax_total'] ?? 0), $currency)) ?></strong></div>
                    <div class="admin-total-row"><span>Total</span><strong><?= e(admin_money((float) $viewOrder['total'], $currency)) ?></strong></div>
                </div>
            </section>

            <?php if (!empty($viewOrder['notes'])): ?>
                <section class="admin-note-card"><strong>Customer notes</strong><p><?= nl2br(e($viewOrder['notes'])) ?></p></section>
            <?php endif; ?>
            <?php if (!empty($viewOrder['internal_notes'])): ?>
                <section class="admin-note-card"><strong>Internal saved notes</strong><p><?= nl2br(e($viewOrder['internal_notes'])) ?></p></section>
            <?php endif; ?>
        </div>

        <aside class="admin-order-side-stack">
            <section class="admin-inner-card">
                <h3 class="admin-subtitle">Fulfillment update</h3>
                <form method="post" class="admin-form-stack admin-edit-panel">
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="admin_action" value="order_update">
                    <input type="hidden" name="order_id" value="<?= (int) $viewOrder['id'] ?>">
                    <input type="hidden" name="view" value="<?= (int) $viewOrder['id'] ?>">
                    <label class="admin-field"><span>Status</span><select name="status"><?php foreach ($allowedStatuses as $s): ?><option value="<?= e($s) ?>" <?= $viewOrder['status'] === $s ? 'selected' : '' ?>><?= e($statusLabels[$s]) ?></option><?php endforeach; ?></select></label>
                    <label class="admin-field"><span>Payment</span><select name="payment_status"><?php foreach ($allowedPayments as $p): ?><option value="<?= e($p) ?>" <?= $viewOrder['payment_status'] === $p ? 'selected' : '' ?>><?= e($paymentLabels[$p]) ?></option><?php endforeach; ?></select></label>
                    <label class="admin-field"><span>Carrier</span><input name="tracking_carrier" value="<?= e((string) ($viewOrder['tracking_carrier'] ?? '')) ?>" placeholder="DHL, UPS, local courier..."></label>
                    <label class="admin-field"><span>Tracking number</span><input name="tracking_number" value="<?= e((string) ($viewOrder['tracking_number'] ?? '')) ?>" placeholder="Carrier tracking reference"></label>
                    <label class="admin-field"><span>Tracking URL</span><input name="tracking_url" value="<?= e((string) ($viewOrder['tracking_url'] ?? '')) ?>" placeholder="https://..."></label>
                    <label class="admin-field"><span>Internal saved notes</span><textarea name="internal_notes" rows="4" placeholder="Private notes visible only in admin"><?= e((string) ($viewOrder['internal_notes'] ?? '')) ?></textarea></label>
                    <label class="admin-field"><span>Customer-visible update note</span><textarea name="event_note" rows="3" placeholder="Optional note visible in the customer account timeline"></textarea></label>
                    <button class="admin-primary-btn" type="submit">Save update</button>
                </form>
            </section>

            <section class="admin-inner-card">
                <h3 class="admin-subtitle">Private internal note</h3>
                <form method="post" class="admin-form-stack admin-edit-panel">
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="admin_action" value="order_note">
                    <input type="hidden" name="order_id" value="<?= (int) $viewOrder['id'] ?>">
                    <input type="hidden" name="view" value="<?= (int) $viewOrder['id'] ?>">
                    <label class="admin-field"><span>Internal note</span><textarea name="note" rows="3" placeholder="Add a private note for the admin team..."></textarea></label>
                    <button class="admin-ghost-btn" type="submit">Add note</button>
                </form>
            </section>
        </aside>
    </div>

    <section class="admin-inner-card">
        <h3 class="admin-subtitle">Order timeline</h3>
        <?php if (!$orderEvents): ?>
            <?php admin_empty_state('No timeline yet', 'Status updates and private notes will appear here.'); ?>
        <?php else: ?>
            <div class="admin-timeline admin-order-timeline">
                <?php foreach ($orderEvents as $event): ?>
                    <article>
                        <span class="admin-timeline-dot"></span>
                        <div>
                            <strong><?= e(($statusLabels[$event['status']] ?? ucfirst((string) ($event['status'] ?: 'Note'))) . ' / ' . ($paymentLabels[$event['payment_status']] ?? ucfirst((string) ($event['payment_status'] ?: 'Payment')))) ?></strong>
                            <?php if (!empty($event['note'])): ?><p><?= nl2br(e($event['note'])) ?></p><?php endif; ?>
                            <small><?= e(((int) ($event['is_customer_visible'] ?? 1) === 1 ? 'Customer-visible' : 'Internal') . ' · ' . ($event['admin_email'] ?: 'System') . ' · ' . $event['created_at']) ?></small>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</section>
<?php endif; ?>

<section class="admin-card glass-panel">
    <div class="admin-section-head"><div><p class="admin-eyebrow">Order ledger</p><h2>Orders</h2></div></div>
    <form method="get" class="admin-filter-bar admin-order-filter">
        <input name="q" value="<?= e($q) ?>" placeholder="Search order, customer, tracking, method, coupon...">
        <select name="status"><option value="all">All statuses</option><?php foreach ($allowedStatuses as $s): ?><option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= e($statusLabels[$s]) ?></option><?php endforeach; ?></select>
        <select name="payment"><option value="all">All payment</option><?php foreach ($allowedPayments as $p): ?><option value="<?= e($p) ?>" <?= $payment === $p ? 'selected' : '' ?>><?= e($paymentLabels[$p]) ?></option><?php endforeach; ?></select>
        <button class="admin-ghost-btn" type="submit">Filter</button>
    </form>
    <?php if (!$orders): ?><?php admin_empty_state('No orders found', 'Orders matching the current filters will appear here.'); ?><?php else: ?>
    <form method="post" class="admin-bulk-toolbar" id="orderBulkForm" data-admin-confirm="Apply this update to selected orders?">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="admin_action" value="order_bulk_update">
        <label class="admin-field"><span>Status</span><select name="bulk_status"><option value="">Keep status</option><?php foreach ($allowedStatuses as $s): ?><option value="<?= e($s) ?>"><?= e($statusLabels[$s]) ?></option><?php endforeach; ?></select></label>
        <label class="admin-field"><span>Payment</span><select name="bulk_payment_status"><option value="">Keep payment</option><?php foreach ($allowedPayments as $p): ?><option value="<?= e($p) ?>"><?= e($paymentLabels[$p]) ?></option><?php endforeach; ?></select></label>
        <label class="admin-field admin-bulk-note"><span>Timeline note</span><input name="bulk_note" placeholder="Optional note for selected orders"></label>
        <button class="admin-primary-btn" type="submit">Update selected</button>
    </form>
    <div class="admin-table-wrap"><table class="admin-table admin-orders-table"><thead><tr><th><input type="checkbox" data-admin-select-all="[data-order-row-check]" aria-label="Select all orders"></th><th>Order</th><th>Customer</th><th>Fulfillment</th><th>Total</th><th>Created</th><th>Status</th><th>Payment</th><th>Actions</th></tr></thead><tbody>
    <?php foreach ($orders as $order): ?>
        <tr>
            <td><input type="checkbox" name="order_ids[]" value="<?= (int) $order['id'] ?>" form="orderBulkForm" data-order-row-check aria-label="Select order <?= e($order['order_number']) ?>"></td>
            <td><strong><?= e($order['order_number']) ?></strong><small><?= e($order['city'] . ', ' . $order['country']) ?></small></td>
            <td><?= e($order['full_name']) ?><small><?= e($order['email']) ?></small></td>
            <td><strong><?= e($order['shipping_method_name'] ?: 'No shipping') ?></strong><small><?= e($order['tracking_number'] ? 'Tracking: ' . $order['tracking_number'] : ($order['payment_method_name'] ?: 'No tracking')) ?></small></td>
            <td><?= e(admin_money((float) $order['total'], $currency)) ?><small>Ship <?= e(admin_money((float) ($order['shipping_total'] ?? 0), $currency)) ?><?= !empty($order['coupon_code']) ? ' · Coupon ' . e($order['coupon_code']) : '' ?></small></td>
            <td><?= e((string) $order['created_at']) ?></td>
            <td><span class="admin-pill <?= e(admin_status_class($order['status'])) ?>"><?= e($statusLabels[$order['status']] ?? $order['status']) ?></span></td>
            <td><span class="admin-pill <?= e(admin_status_class($order['payment_status'])) ?>"><?= e($paymentLabels[$order['payment_status']] ?? $order['payment_status']) ?></span></td>
            <td><div class="admin-row-actions"><a href="<?= e(admin_page_url('orders', ['view' => (int)$order['id']])) ?>">View</a><details class="admin-more-menu"><summary><span>More</span><span class="material-symbols-outlined">expand_more</span></summary><div class="admin-more-panel"><a href="<?= e('order_invoice.php?order_id=' . (int) $order['id']) ?>" target="_blank" rel="noopener">Invoice</a></div></details></div></td>
        </tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
</section>
<?php admin_footer(); ?>
