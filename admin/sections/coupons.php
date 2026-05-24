<?php
require_once __DIR__ . '/../includes/layout.php';
$ctx = admin_boot('coupons');
$pdo = $ctx['pdo'];
$currency = $ctx['siteCurrency'];

if (is_post_request()) {
    verify_csrf_or_fail($_POST['_csrf'] ?? null);
    $action = (string) ($_POST['admin_action'] ?? '');
    try {
        if ($action === 'coupon_create' || $action === 'coupon_update') {
            $code = admin_coupon_code();
            if ($code === '') { throw new RuntimeException('Coupon code is required.'); }
            $payload = [
                'code' => $code,
                'description' => admin_clean_text('description', 255) ?: null,
                'discount_type' => ($_POST['discount_type'] ?? '') === 'fixed' ? 'fixed' : 'percent',
                'discount_value' => admin_decimal_input('discount_value'),
                'min_order_total' => admin_decimal_input('min_order_total'),
                'starts_at' => admin_nullable_datetime_input('starts_at'),
                'ends_at' => admin_nullable_datetime_input('ends_at'),
                'max_uses' => admin_int_input('max_uses') ?: null,
                'is_active' => admin_bool_from_post('is_active'),
            ];
            if ($action === 'coupon_create') {
                $stmt = $pdo->prepare('INSERT INTO coupons (code, description, discount_type, discount_value, min_order_total, starts_at, ends_at, max_uses, is_active) VALUES (:code, :description, :discount_type, :discount_value, :min_order_total, :starts_at, :ends_at, :max_uses, :is_active)');
                $stmt->execute($payload);
                flash_set('success', 'Coupon created.');
            } else {
                $payload['id'] = admin_int_input('coupon_id');
                $stmt = $pdo->prepare('UPDATE coupons SET code=:code, description=:description, discount_type=:discount_type, discount_value=:discount_value, min_order_total=:min_order_total, starts_at=:starts_at, ends_at=:ends_at, max_uses=:max_uses, is_active=:is_active WHERE id=:id LIMIT 1');
                $stmt->execute($payload);
                flash_set('success', 'Coupon updated.');
            }
        } elseif ($action === 'coupon_delete') {
            $pdo->prepare('DELETE FROM coupons WHERE id = :id LIMIT 1')->execute(['id' => admin_int_input('coupon_id')]);
            flash_set('success', 'Coupon deleted.');
        } else { throw new RuntimeException('Unknown coupon action.'); }
    } catch (Throwable $e) { admin_flash_from_exception($e); }
    admin_redirect('coupons');
}

$coupons = admin_rows($pdo, 'SELECT * FROM coupons ORDER BY created_at DESC, id DESC');
admin_header('Coupons', 'Create and maintain promotional discounts for the checkout flow.', 'coupons');
?>
<section class="admin-card glass-panel">
    <div class="admin-section-head"><div><p class="admin-eyebrow">New promotion</p><h2>Create coupon</h2></div></div>
    <form method="post" class="admin-form-grid">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="admin_action" value="coupon_create">
        <label class="admin-field"><span>Code</span><input name="code" placeholder="WELCOME10" required></label>
        <label class="admin-field"><span>Description</span><input name="description"></label>
        <label class="admin-field"><span>Type</span><select name="discount_type"><option value="percent">Percent</option><option value="fixed">Fixed amount</option></select></label>
        <label class="admin-field"><span>Value</span><input type="number" step="0.01" name="discount_value" required></label>
        <label class="admin-field"><span>Min order</span><input type="number" step="0.01" name="min_order_total" value="0"></label>
        <label class="admin-field"><span>Max uses</span><input type="number" name="max_uses" placeholder="Unlimited"></label>
        <label class="admin-field"><span>Starts</span><input type="datetime-local" name="starts_at"></label>
        <label class="admin-field"><span>Ends</span><input type="datetime-local" name="ends_at"></label>
        <div class="admin-check-row"><label><input type="checkbox" name="is_active" checked> Active</label></div>
        <div class="admin-form-actions"><button class="admin-primary-btn" type="submit">Create coupon</button></div>
    </form>
</section>
<section class="admin-card glass-panel">
    <div class="admin-section-head"><div><p class="admin-eyebrow">Promotions</p><h2>Coupons</h2></div></div>
    <?php if (!$coupons): ?><?php admin_empty_state('No coupons yet', 'Create your first promotion above.'); ?><?php else: ?>
    <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Code</th><th>Discount</th><th>Minimum</th><th>Uses</th><th>Dates</th><th>Status</th><th>Update</th><th>Delete</th></tr></thead><tbody>
    <?php foreach ($coupons as $coupon): ?>
        <tr><form method="post"><td><input class="admin-table-input" name="code" value="<?= e($coupon['code']) ?>"></td><td><select name="discount_type"><option value="percent" <?= $coupon['discount_type'] === 'percent' ? 'selected' : '' ?>>%</option><option value="fixed" <?= $coupon['discount_type'] === 'fixed' ? 'selected' : '' ?>><?= e($currency) ?></option></select><input class="admin-table-input mini" type="number" step="0.01" name="discount_value" value="<?= e((string)$coupon['discount_value']) ?>"></td><td><input class="admin-table-input mini" type="number" step="0.01" name="min_order_total" value="<?= e((string)$coupon['min_order_total']) ?>"></td><td><input class="admin-table-input mini" type="number" name="max_uses" value="<?= e((string)($coupon['max_uses'] ?? '')) ?>"><small><?= (int)$coupon['used_count'] ?> used</small></td><td><input class="admin-table-input" type="datetime-local" name="starts_at" value="<?= e(admin_datetime_value($coupon['starts_at'])) ?>"><input class="admin-table-input" type="datetime-local" name="ends_at" value="<?= e(admin_datetime_value($coupon['ends_at'])) ?>"></td><td><label class="admin-small-check"><input type="checkbox" name="is_active" <?= (int)$coupon['is_active'] === 1 ? 'checked' : '' ?>> Active</label></td><td><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="admin_action" value="coupon_update"><input type="hidden" name="coupon_id" value="<?= (int)$coupon['id'] ?>"><input type="hidden" name="description" value="<?= e($coupon['description'] ?? '') ?>"><button class="admin-table-btn" type="submit">Save</button></td></form><td><form method="post"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="admin_action" value="coupon_delete"><input type="hidden" name="coupon_id" value="<?= (int)$coupon['id'] ?>"><button class="admin-table-btn danger" type="submit">Delete</button></form></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
</section>
<?php admin_footer(); ?>
