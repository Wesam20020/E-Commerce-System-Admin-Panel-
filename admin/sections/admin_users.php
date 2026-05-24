<?php
require_once __DIR__ . '/../includes/layout.php';
$ctx = admin_boot('admin_users');
$pdo = $ctx['pdo'];
$currentEmail = mb_strtolower(trim((string) ($ctx['currentUser']['email'] ?? '')));

function admin_users_active_owner_count(PDO $pdo, ?int $ignoreId = null): int
{
    $sql = "SELECT COUNT(*) FROM admins WHERE role = 'owner' AND status = 'active'";
    $params = [];
    if ($ignoreId !== null) {
        $sql .= ' AND id <> :id';
        $params['id'] = $ignoreId;
    }
    return (int) admin_scalar($pdo, $sql, $params);
}

function admin_users_password_hash_for_email(PDO $pdo, string $email, string $name, ?string &$temporaryPassword = null): string
{
    $stmt = $pdo->prepare('SELECT id, password_hash FROM users WHERE LOWER(email) = :email LIMIT 1');
    $stmt->execute(['email' => mb_strtolower($email)]);
    $user = $stmt->fetch();
    if ($user && !empty($user['password_hash'])) {
        return (string) $user['password_hash'];
    }

    $temporaryPassword = 'Phonix-' . bin2hex(random_bytes(4)) . '!';
    $hash = password_hash($temporaryPassword, PASSWORD_DEFAULT);
    $insertSql = admin_table_has_column($pdo, 'users', 'email_verified_at')
        ? 'INSERT INTO users (name, email, password_hash, email_verified_at) VALUES (:name, :email, :password_hash, NOW())'
        : 'INSERT INTO users (name, email, password_hash) VALUES (:name, :email, :password_hash)';
    $stmt = $pdo->prepare($insertSql);
    $stmt->execute([
        'name' => $name,
        'email' => mb_strtolower($email),
        'password_hash' => $hash,
    ]);
    return $hash;
}

if (is_post_request()) {
    verify_csrf_or_fail($_POST['_csrf'] ?? null);
    $action = (string) ($_POST['admin_action'] ?? '');
    try {
        if ($action === 'admin_save') {
            $adminId = admin_int_input('admin_id');
            $name = admin_clean_text('name', 150);
            $email = mb_strtolower(admin_clean_text('email', 190));
            $role = admin_role_from_post('role');
            $status = admin_status_from_post('status');

            if ($name === '') {
                throw new RuntimeException('Admin name is required.');
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Enter a valid admin email.');
            }

            if ($email === $currentEmail) {
                $role = 'owner';
                $status = 'active';
            }

            $temporaryPassword = null;
            $passwordHash = admin_users_password_hash_for_email($pdo, $email, $name, $temporaryPassword);

            if ($adminId > 0) {
                $stmt = $pdo->prepare('SELECT * FROM admins WHERE id = :id LIMIT 1');
                $stmt->execute(['id' => $adminId]);
                $existing = $stmt->fetch();
                if (!$existing) {
                    throw new RuntimeException('Admin user was not found.');
                }
                if ((string) $existing['role'] === 'owner' && ($role !== 'owner' || $status !== 'active') && admin_users_active_owner_count($pdo, $adminId) < 1) {
                    throw new RuntimeException('You must keep at least one active owner account.');
                }
                $stmt = $pdo->prepare('UPDATE admins SET name = :name, email = :email, password_hash = :password_hash, role = :role, status = :status WHERE id = :id LIMIT 1');
                $stmt->execute([
                    'name' => $name,
                    'email' => $email,
                    'password_hash' => $passwordHash,
                    'role' => $role,
                    'status' => $status,
                    'id' => $adminId,
                ]);
                admin_log_activity($pdo, 'admin_user_updated', 'admin', $adminId, $email . ' / ' . $role . ' / ' . $status);
                flash_set('success', 'Admin user updated.');
            } else {
                $stmt = $pdo->prepare('INSERT INTO admins (name, email, password_hash, role, status, created_by) VALUES (:name, :email, :password_hash, :role, :status, :created_by) ON DUPLICATE KEY UPDATE name = VALUES(name), password_hash = VALUES(password_hash), role = VALUES(role), status = VALUES(status)');
                $stmt->execute([
                    'name' => $name,
                    'email' => $email,
                    'password_hash' => $passwordHash,
                    'role' => $role,
                    'status' => $status,
                    'created_by' => admin_current_email(),
                ]);
                $newId = (int) admin_scalar($pdo, 'SELECT id FROM admins WHERE LOWER(email) = :email LIMIT 1', ['email' => $email]);
                admin_log_activity($pdo, 'admin_user_saved', 'admin', $newId, $email . ' / ' . $role . ' / ' . $status);
                $message = 'Admin user saved.';
                if ($temporaryPassword !== null) {
                    $message .= ' Temporary password: ' . $temporaryPassword;
                }
                flash_set('success', $message);
            }
        } elseif ($action === 'admin_delete') {
            $adminId = admin_int_input('admin_id');
            $stmt = $pdo->prepare('SELECT * FROM admins WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $adminId]);
            $admin = $stmt->fetch();
            if (!$admin) {
                throw new RuntimeException('Admin user was not found.');
            }
            if (mb_strtolower((string) $admin['email']) === $currentEmail) {
                throw new RuntimeException('You cannot remove your own admin access.');
            }
            if ((string) $admin['role'] === 'owner' && admin_users_active_owner_count($pdo, $adminId) < 1) {
                throw new RuntimeException('You must keep at least one active owner account.');
            }
            $pdo->prepare('DELETE FROM admins WHERE id = :id LIMIT 1')->execute(['id' => $adminId]);
            admin_log_activity($pdo, 'admin_user_deleted', 'admin', $adminId, (string) $admin['email']);
            flash_set('success', 'Admin user removed from the admin allowlist.');
        } else {
            throw new RuntimeException('Unknown admin users action.');
        }
    } catch (Throwable $e) {
        admin_flash_from_exception($e, 'Admin user action failed.');
    }
    admin_redirect('admin_users');
}

$admins = admin_rows($pdo, 'SELECT a.*, u.id AS user_id, u.last_login_at AS user_last_login_at FROM admins a LEFT JOIN users u ON LOWER(u.email) = LOWER(a.email) ORDER BY FIELD(a.role, \'owner\', \'manager\', \'products\', \'orders\', \'support\'), a.status ASC, a.name ASC');
$counts = [
    'total' => count($admins),
    'active' => (int) admin_scalar($pdo, "SELECT COUNT(*) FROM admins WHERE status = 'active'"),
    'owners' => (int) admin_scalar($pdo, "SELECT COUNT(*) FROM admins WHERE role = 'owner' AND status = 'active'"),
    'suspended' => (int) admin_scalar($pdo, "SELECT COUNT(*) FROM admins WHERE status = 'suspended'"),
];
$permissions = admin_section_permissions();

admin_header('Admin Users', 'Manage administrator accounts, role assignments, and the first permission layer for the admin console.', 'admin_users');
?>
<section class="admin-metrics-grid" aria-label="Admin user metrics">
    <?php admin_metric_card('Admin users', (string) $counts['total'], 'admin_panel_settings', 'Allowlisted accounts'); ?>
    <?php admin_metric_card('Active', (string) $counts['active'], 'verified_user', 'Can access admin'); ?>
    <?php admin_metric_card('Owners', (string) $counts['owners'], 'workspace_premium', 'Full control'); ?>
    <?php admin_metric_card('Suspended', (string) $counts['suspended'], 'block', 'Access disabled'); ?>
</section>

<section class="admin-card glass-panel">
    <div class="admin-section-head">
        <div><p class="admin-eyebrow">Access control</p><h2>Add admin user</h2></div>
    </div>
    <form method="post" class="admin-form-grid compact">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="admin_action" value="admin_save">
        <input type="hidden" name="admin_id" value="0">
        <label class="admin-field"><span>Name</span><input name="name" required placeholder="Store Manager"></label>
        <label class="admin-field"><span>Email</span><input type="email" name="email" required placeholder="manager@example.com"></label>
        <label class="admin-field"><span>Role</span><select name="role"><?php foreach (admin_roles() as $value => $label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?></select></label>
        <label class="admin-field"><span>Status</span><select name="status"><?php foreach (admin_status_options() as $value => $label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?></select></label>
        <div class="admin-form-actions"><button class="admin-primary-btn" type="submit">Save admin</button></div>
    </form>
</section>

<section class="admin-card glass-panel">
    <div class="admin-section-head">
        <div><p class="admin-eyebrow">Team</p><h2>Admin allowlist</h2></div>
    </div>
    <?php if (!$admins): ?>
        <?php admin_empty_state('No admin users yet', 'Create at least one owner admin account to manage the console safely.'); ?>
    <?php else: ?>
        <div class="admin-stack-list admin-users-list">
            <?php foreach ($admins as $admin): ?>
                <?php
                    $adminEmail = mb_strtolower((string) ($admin['email'] ?? ''));
                    $isSelf = $adminEmail === $currentEmail;
                    $role = strtolower((string) ($admin['role'] ?? 'manager'));
                    $status = strtolower((string) ($admin['status'] ?? 'active'));
                ?>
                <article class="admin-edit-panel admin-user-panel">
                    <div class="admin-user-summary">
                        <div>
                            <strong><?= e((string) $admin['name']) ?></strong>
                            <span><?= e((string) $admin['email']) ?></span>
                        </div>
                        <div class="admin-user-badges">
                            <span class="admin-status <?= e($role === 'owner' ? 'good' : 'info') ?>"><?= e(admin_role_label($role)) ?></span>
                            <span class="admin-status <?= e($status === 'active' ? 'good' : 'danger') ?>"><?= e($status) ?></span>
                            <?php if ($isSelf): ?><span class="admin-status neutral">You</span><?php endif; ?>
                        </div>
                    </div>
                    <form method="post" class="admin-form-grid compact">
                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="admin_action" value="admin_save">
                        <input type="hidden" name="admin_id" value="<?= (int) $admin['id'] ?>">
                        <label class="admin-field"><span>Name</span><input name="name" value="<?= e((string) $admin['name']) ?>" required></label>
                        <label class="admin-field"><span>Email</span><input type="email" name="email" value="<?= e((string) $admin['email']) ?>" required <?= $isSelf ? 'readonly' : '' ?>></label>
                        <label class="admin-field"><span>Role</span><select name="role" <?= $isSelf ? 'disabled' : '' ?>><?php foreach (admin_roles() as $value => $label): ?><option value="<?= e($value) ?>" <?= $role === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select><?php if ($isSelf): ?><input type="hidden" name="role" value="owner"><?php endif; ?></label>
                        <label class="admin-field"><span>Status</span><select name="status" <?= $isSelf ? 'disabled' : '' ?>><?php foreach (admin_status_options() as $value => $label): ?><option value="<?= e($value) ?>" <?= $status === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select><?php if ($isSelf): ?><input type="hidden" name="status" value="active"><?php endif; ?></label>
                        <div class="admin-user-meta admin-field-wide">
                            <span>User account: <?= !empty($admin['user_id']) ? '#' . (int) $admin['user_id'] : 'created on save if missing' ?></span>
                            <span>Last admin seen: <?= e((string) ($admin['last_seen_at'] ?: 'Never')) ?></span>
                            <span>Last store login: <?= e((string) ($admin['user_last_login_at'] ?: 'Never')) ?></span>
                        </div>
                        <div class="admin-form-actions"><button class="admin-primary-btn" type="submit">Update admin</button></div>
                    </form>
                    <?php if (!$isSelf): ?>
                        <form method="post" class="admin-delete-strip" data-admin-confirm="Remove this account from the admin allowlist?">
                            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="admin_action" value="admin_delete">
                            <input type="hidden" name="admin_id" value="<?= (int) $admin['id'] ?>">
                            <button type="submit">Remove admin access</button>
                        </form>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="admin-card glass-panel">
    <div class="admin-section-head">
        <div><p class="admin-eyebrow">Permission map</p><h2>Role coverage</h2></div>
    </div>
    <div class="admin-role-grid">
        <?php foreach (admin_roles() as $roleValue => $roleLabel): ?>
            <article>
                <strong><?= e($roleLabel) ?></strong>
                <div>
                    <?php foreach ($permissions as $section => $roles): ?>
                        <?php if (in_array($roleValue, $roles, true)): ?>
                            <span><?= e(str_replace('_', ' ', $section)) ?></span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php admin_footer(); ?>
