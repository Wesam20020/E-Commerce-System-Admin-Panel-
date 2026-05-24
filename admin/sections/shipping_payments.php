<?php
require_once __DIR__ . '/../includes/layout.php';
$ctx = admin_boot('shipping_payments');
$pdo = $ctx['pdo'];
$currency = $ctx['siteCurrency'];

if (is_post_request()) {
    verify_csrf_or_fail($_POST['_csrf'] ?? null);
    $action = (string) ($_POST['admin_action'] ?? '');
    try {
        if ($action === 'shipping_save') {
            $id = admin_int_input('shipping_id');
            $code = admin_slugify(admin_clean_text('code', 80));
            $name = admin_clean_text('name', 160);
            if ($code === '' || $name === '') {
                throw new RuntimeException('Shipping code and name are required.');
            }
            $payload = [
                'code' => $code,
                'name' => $name,
                'description' => trim((string) ($_POST['description'] ?? '')) ?: null,
                'price' => admin_decimal_input('price'),
                'free_over' => trim((string) ($_POST['free_over'] ?? '')) === '' ? null : admin_decimal_input('free_over'),
                'eta_min_days' => trim((string) ($_POST['eta_min_days'] ?? '')) === '' ? null : admin_int_input('eta_min_days'),
                'eta_max_days' => trim((string) ($_POST['eta_max_days'] ?? '')) === '' ? null : admin_int_input('eta_max_days'),
                'region_label' => admin_clean_text('region_label', 190) ?: null,
                'sort_order' => (int) ($_POST['sort_order'] ?? 0),
                'is_active' => admin_bool_from_post('is_active'),
            ];
            if ($id > 0) {
                $payload['id'] = $id;
                $stmt = $pdo->prepare('UPDATE shipping_methods SET code=:code, name=:name, description=:description, price=:price, free_over=:free_over, eta_min_days=:eta_min_days, eta_max_days=:eta_max_days, region_label=:region_label, sort_order=:sort_order, is_active=:is_active WHERE id=:id LIMIT 1');
                $stmt->execute($payload);
                admin_log_activity($pdo, 'shipping_method_updated', 'shipping_method', $id, $name);
                flash_set('success', 'Shipping method updated.');
            } else {
                $stmt = $pdo->prepare('INSERT INTO shipping_methods (code, name, description, price, free_over, eta_min_days, eta_max_days, region_label, sort_order, is_active) VALUES (:code, :name, :description, :price, :free_over, :eta_min_days, :eta_max_days, :region_label, :sort_order, :is_active)');
                $stmt->execute($payload);
                $newId = (int) $pdo->lastInsertId();
                admin_log_activity($pdo, 'shipping_method_created', 'shipping_method', $newId, $name);
                flash_set('success', 'Shipping method created.');
            }
        } elseif ($action === 'shipping_delete') {
            $id = admin_int_input('shipping_id');
            $ordersUsing = (int) admin_scalar($pdo, 'SELECT COUNT(*) FROM orders WHERE shipping_method_id = :id', ['id' => $id]);
            if ($ordersUsing > 0) {
                $pdo->prepare('UPDATE shipping_methods SET is_active = 0 WHERE id = :id LIMIT 1')->execute(['id' => $id]);
                flash_set('success', 'Shipping method is used by orders, so it was hidden instead of deleted.');
            } else {
                $pdo->prepare('DELETE FROM shipping_methods WHERE id = :id LIMIT 1')->execute(['id' => $id]);
                flash_set('success', 'Shipping method deleted.');
            }
            admin_log_activity($pdo, 'shipping_method_deleted', 'shipping_method', $id);
        } elseif ($action === 'payment_save') {
            $id = admin_int_input('payment_id');
            $code = admin_slugify(admin_clean_text('code', 80));
            $name = admin_clean_text('name', 160);
            if ($code === '' || $name === '') {
                throw new RuntimeException('Payment code and name are required.');
            }
            $payload = [
                'code' => $code,
                'name' => $name,
                'provider' => admin_clean_text('provider', 120) ?: null,
                'instructions' => trim((string) ($_POST['instructions'] ?? '')) ?: null,
                'manual_followup' => admin_bool_from_post('manual_followup'),
                'sort_order' => (int) ($_POST['sort_order'] ?? 0),
                'is_active' => admin_bool_from_post('is_active'),
            ];
            if ($id > 0) {
                $payload['id'] = $id;
                $stmt = $pdo->prepare('UPDATE payment_methods SET code=:code, name=:name, provider=:provider, instructions=:instructions, manual_followup=:manual_followup, sort_order=:sort_order, is_active=:is_active WHERE id=:id LIMIT 1');
                $stmt->execute($payload);
                admin_log_activity($pdo, 'payment_method_updated', 'payment_method', $id, $name);
                flash_set('success', 'Payment method updated.');
            } else {
                $stmt = $pdo->prepare('INSERT INTO payment_methods (code, name, provider, instructions, manual_followup, sort_order, is_active) VALUES (:code, :name, :provider, :instructions, :manual_followup, :sort_order, :is_active)');
                $stmt->execute($payload);
                $newId = (int) $pdo->lastInsertId();
                admin_log_activity($pdo, 'payment_method_created', 'payment_method', $newId, $name);
                flash_set('success', 'Payment method created.');
            }
        } elseif ($action === 'payment_delete') {
            $id = admin_int_input('payment_id');
            $ordersUsing = (int) admin_scalar($pdo, 'SELECT COUNT(*) FROM orders WHERE payment_method_id = :id', ['id' => $id]);
            if ($ordersUsing > 0) {
                $pdo->prepare('UPDATE payment_methods SET is_active = 0 WHERE id = :id LIMIT 1')->execute(['id' => $id]);
                flash_set('success', 'Payment method is used by orders, so it was hidden instead of deleted.');
            } else {
                $pdo->prepare('DELETE FROM payment_methods WHERE id = :id LIMIT 1')->execute(['id' => $id]);
                flash_set('success', 'Payment method deleted.');
            }
            admin_log_activity($pdo, 'payment_method_deleted', 'payment_method', $id);
        } else {
            throw new RuntimeException('Unknown shipping/payment action.');
        }
    } catch (Throwable $e) {
        admin_flash_from_exception($e);
    }
    admin_redirect('shipping_payments');
}

$shippingMethods = admin_rows($pdo, 'SELECT * FROM shipping_methods ORDER BY is_active DESC, sort_order ASC, id ASC');
$paymentMethods = admin_rows($pdo, 'SELECT * FROM payment_methods ORDER BY is_active DESC, sort_order ASC, id ASC');
$editShipping = null;
$editPayment = null;
if (isset($_GET['edit_shipping']) && is_numeric($_GET['edit_shipping'])) {
    $stmt = $pdo->prepare('SELECT * FROM shipping_methods WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => (int) $_GET['edit_shipping']]);
    $editShipping = $stmt->fetch() ?: null;
}
if (isset($_GET['edit_payment']) && is_numeric($_GET['edit_payment'])) {
    $stmt = $pdo->prepare('SELECT * FROM payment_methods WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => (int) $_GET['edit_payment']]);
    $editPayment = $stmt->fetch() ?: null;
}

$shippingForm = array_merge([
    'id' => '', 'code' => '', 'name' => '', 'description' => '', 'price' => '0.00', 'free_over' => '', 'eta_min_days' => '', 'eta_max_days' => '', 'region_label' => '', 'sort_order' => '0', 'is_active' => 1,
], $editShipping ?: []);
$paymentForm = array_merge([
    'id' => '', 'code' => '', 'name' => '', 'provider' => '', 'instructions' => '', 'manual_followup' => 0, 'sort_order' => '0', 'is_active' => 1,
], $editPayment ?: []);

$activeShipping = (int) admin_scalar($pdo, 'SELECT COUNT(*) FROM shipping_methods WHERE is_active = 1');
$activePayment = (int) admin_scalar($pdo, 'SELECT COUNT(*) FROM payment_methods WHERE is_active = 1');
$freeShipping = (int) admin_scalar($pdo, 'SELECT COUNT(*) FROM shipping_methods WHERE is_active = 1 AND free_over IS NOT NULL AND free_over > 0');
$manualPayments = (int) admin_scalar($pdo, 'SELECT COUNT(*) FROM payment_methods WHERE is_active = 1 AND manual_followup = 1');

admin_header('Shipping / Payments', 'Manage checkout delivery options and payment methods without leaving the AJAX admin console.', 'shipping_payments');
?>
<section class="admin-metrics-grid" aria-label="Shipping and payment metrics">
    <?php admin_metric_card('Active shipping', (string) $activeShipping, 'local_shipping', 'Visible at checkout'); ?>
    <?php admin_metric_card('Active payment', (string) $activePayment, 'payments', 'Visible at checkout'); ?>
    <?php admin_metric_card('Free thresholds', (string) $freeShipping, 'redeem', 'Shipping methods with free_over'); ?>
    <?php admin_metric_card('Manual payments', (string) $manualPayments, 'support_agent', 'Need follow-up'); ?>
</section>

<section class="admin-two-col admin-shipping-payments-grid">
    <article class="admin-card glass-panel">
        <div class="admin-section-head">
            <div><p class="admin-eyebrow"><?= $editShipping ? 'Edit shipping' : 'New shipping' ?></p><h2><?= $editShipping ? e($editShipping['name']) : 'Shipping method' ?></h2></div>
            <?php if ($editShipping): ?><a class="admin-text-link" href="<?= e(admin_page_url('shipping_payments')) ?>">New shipping</a><?php endif; ?>
        </div>
        <form method="post" class="admin-form-grid compact">
            <?= csrf_field() ?>
            <input type="hidden" name="admin_action" value="shipping_save">
            <input type="hidden" name="shipping_id" value="<?= e((string) $shippingForm['id']) ?>">
            <label class="admin-field"><span>Code</span><input name="code" value="<?= e((string) $shippingForm['code']) ?>" maxlength="80" placeholder="standard" required></label>
            <label class="admin-field"><span>Name</span><input name="name" value="<?= e((string) $shippingForm['name']) ?>" maxlength="160" placeholder="Standard Shipping" required></label>
            <label class="admin-field"><span>Price</span><input type="number" step="0.01" min="0" name="price" value="<?= e((string) $shippingForm['price']) ?>"></label>
            <label class="admin-field"><span>Free over</span><input type="number" step="0.01" min="0" name="free_over" value="<?= e((string) $shippingForm['free_over']) ?>" placeholder="50.00"></label>
            <label class="admin-field"><span>ETA min days</span><input type="number" min="0" name="eta_min_days" value="<?= e((string) $shippingForm['eta_min_days']) ?>"></label>
            <label class="admin-field"><span>ETA max days</span><input type="number" min="0" name="eta_max_days" value="<?= e((string) $shippingForm['eta_max_days']) ?>"></label>
            <label class="admin-field"><span>Region / countries</span><input name="region_label" value="<?= e((string) $shippingForm['region_label']) ?>" maxlength="190" placeholder="Türkiye, GCC, EU..."></label>
            <label class="admin-field"><span>Sort order</span><input type="number" name="sort_order" value="<?= e((string) $shippingForm['sort_order']) ?>"></label>
            <label class="admin-field admin-field-wide"><span>Description</span><textarea name="description" rows="3" placeholder="Shown to customers at checkout"><?= e((string) $shippingForm['description']) ?></textarea></label>
            <label class="admin-small-check"><input type="checkbox" name="is_active" value="1" <?= (int) $shippingForm['is_active'] === 1 ? 'checked' : '' ?>> Active at checkout</label>
            <div class="admin-form-actions"><button class="admin-primary-btn" type="submit"><?= $editShipping ? 'Save shipping' : 'Create shipping' ?></button></div>
        </form>
    </article>

    <article class="admin-card glass-panel">
        <div class="admin-section-head">
            <div><p class="admin-eyebrow"><?= $editPayment ? 'Edit payment' : 'New payment' ?></p><h2><?= $editPayment ? e($editPayment['name']) : 'Payment method' ?></h2></div>
            <?php if ($editPayment): ?><a class="admin-text-link" href="<?= e(admin_page_url('shipping_payments')) ?>">New payment</a><?php endif; ?>
        </div>
        <form method="post" class="admin-form-grid compact">
            <?= csrf_field() ?>
            <input type="hidden" name="admin_action" value="payment_save">
            <input type="hidden" name="payment_id" value="<?= e((string) $paymentForm['id']) ?>">
            <label class="admin-field"><span>Code</span><input name="code" value="<?= e((string) $paymentForm['code']) ?>" maxlength="80" placeholder="cod" required></label>
            <label class="admin-field"><span>Name</span><input name="name" value="<?= e((string) $paymentForm['name']) ?>" maxlength="160" placeholder="Cash on Delivery" required></label>
            <label class="admin-field"><span>Provider</span><input name="provider" value="<?= e((string) $paymentForm['provider']) ?>" maxlength="120" placeholder="Manual / Stripe / Iyzico"></label>
            <label class="admin-field"><span>Sort order</span><input type="number" name="sort_order" value="<?= e((string) $paymentForm['sort_order']) ?>"></label>
            <label class="admin-field admin-field-wide"><span>Instructions</span><textarea name="instructions" rows="3" placeholder="Shown to the customer after choosing this method"><?= e((string) $paymentForm['instructions']) ?></textarea></label>
            <label class="admin-small-check"><input type="checkbox" name="manual_followup" value="1" <?= (int) $paymentForm['manual_followup'] === 1 ? 'checked' : '' ?>> Needs manual follow-up</label>
            <label class="admin-small-check"><input type="checkbox" name="is_active" value="1" <?= (int) $paymentForm['is_active'] === 1 ? 'checked' : '' ?>> Active at checkout</label>
            <div class="admin-form-actions"><button class="admin-primary-btn" type="submit"><?= $editPayment ? 'Save payment' : 'Create payment' ?></button></div>
        </form>
    </article>
</section>

<section class="admin-two-col">
    <article class="admin-card glass-panel">
        <div class="admin-section-head"><div><p class="admin-eyebrow">Shipping</p><h2>Shipping methods</h2></div></div>
        <?php if (!$shippingMethods): ?>
            <?php admin_empty_state('No shipping methods', 'Create a shipping method before opening checkout.'); ?>
        <?php else: ?>
            <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Method</th><th>Price</th><th>ETA</th><th>Status</th><th>Actions</th></tr></thead><tbody>
            <?php foreach ($shippingMethods as $method): ?>
                <tr>
                    <td><strong><?= e($method['name']) ?></strong><small><?= e($method['code'] . ($method['region_label'] ? ' · ' . $method['region_label'] : '')) ?></small></td>
                    <td><?= e(admin_money((float) $method['price'], $currency)) ?><small><?= $method['free_over'] !== null ? e('Free over ' . admin_money((float) $method['free_over'], $currency)) : 'No free threshold' ?></small></td>
                    <td><?= e(($method['eta_min_days'] !== null ? (string) $method['eta_min_days'] : '—') . '–' . ($method['eta_max_days'] !== null ? (string) $method['eta_max_days'] : '—') . ' days') ?></td>
                    <td><span class="admin-pill <?= (int) $method['is_active'] === 1 ? 'good' : 'danger' ?>"><?= (int) $method['is_active'] === 1 ? 'Active' : 'Hidden' ?></span></td>
                    <td><div class="admin-row-actions"><a href="<?= e(admin_page_url('shipping_payments', ['edit_shipping' => (int) $method['id']])) ?>">Edit</a><details class="admin-more-menu"><summary><span>More</span><span class="material-symbols-outlined">expand_more</span></summary><div class="admin-more-panel"><form method="post" class="admin-inline-form" data-admin-confirm="Delete this shipping method?"><?= csrf_field() ?><input type="hidden" name="admin_action" value="shipping_delete"><input type="hidden" name="shipping_id" value="<?= (int) $method['id'] ?>"><button class="danger" type="submit">Delete</button></form></div></details></div></td>
                </tr>
            <?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
    </article>

    <article class="admin-card glass-panel">
        <div class="admin-section-head"><div><p class="admin-eyebrow">Payments</p><h2>Payment methods</h2></div></div>
        <?php if (!$paymentMethods): ?>
            <?php admin_empty_state('No payment methods', 'Create a payment method before opening checkout.'); ?>
        <?php else: ?>
            <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Method</th><th>Provider</th><th>Mode</th><th>Status</th><th>Actions</th></tr></thead><tbody>
            <?php foreach ($paymentMethods as $method): ?>
                <tr>
                    <td><strong><?= e($method['name']) ?></strong><small><?= e($method['code']) ?></small></td>
                    <td><?= e($method['provider'] ?: '—') ?></td>
                    <td><span class="admin-pill <?= (int) $method['manual_followup'] === 1 ? 'info' : 'neutral' ?>"><?= (int) $method['manual_followup'] === 1 ? 'Manual' : 'Gateway' ?></span></td>
                    <td><span class="admin-pill <?= (int) $method['is_active'] === 1 ? 'good' : 'danger' ?>"><?= (int) $method['is_active'] === 1 ? 'Active' : 'Hidden' ?></span></td>
                    <td><div class="admin-row-actions"><a href="<?= e(admin_page_url('shipping_payments', ['edit_payment' => (int) $method['id']])) ?>">Edit</a><details class="admin-more-menu"><summary><span>More</span><span class="material-symbols-outlined">expand_more</span></summary><div class="admin-more-panel"><form method="post" class="admin-inline-form" data-admin-confirm="Delete this payment method?"><?= csrf_field() ?><input type="hidden" name="admin_action" value="payment_delete"><input type="hidden" name="payment_id" value="<?= (int) $method['id'] ?>"><button class="danger" type="submit">Delete</button></form></div></details></div></td>
                </tr>
            <?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
    </article>
</section>
<?php admin_footer(); ?>
