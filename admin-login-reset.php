<?php
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/db.php';

send_security_headers();

if (session_status() !== PHP_SESSION_ACTIVE) {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? null) === '443');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => $https,
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();
}

$resetKey = trim((string) env_value('PHONIX_ADMIN_RESET_KEY', ''));
$providedKey = (string) ($_GET['key'] ?? '');

if ($resetKey === '' || !hash_equals($resetKey, $providedKey)) {
    http_response_code(404);
    exit('Not found');
}

$adminEmail = 'admin@phonix.local';
$adminName = 'Phonix Admin';
$message = null;
$error = null;
$temporaryPassword = null;

function phonix_column_exists(PDO $pdo, string $table, string $column): bool
{
    $config = app_config();
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = :schema_name AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name'
    );
    $stmt->execute([
        'schema_name' => (string) $config['db_name'],
        'table_name' => $table,
        'column_name' => $column,
    ]);

    return (int) $stmt->fetchColumn() > 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_fail($_POST['_csrf'] ?? null);
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS admins (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(150) NOT NULL,
            email VARCHAR(190) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            role VARCHAR(40) NOT NULL DEFAULT 'owner',
            status VARCHAR(30) NOT NULL DEFAULT 'active',
            permissions_json LONGTEXT NULL,
            last_seen_at DATETIME NULL,
            created_by VARCHAR(190) NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_admins_email (email),
            KEY idx_admins_role_status (role, status),
            KEY idx_admins_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $adminColumns = [
            'role' => "ALTER TABLE admins ADD COLUMN role VARCHAR(40) NOT NULL DEFAULT 'owner' AFTER password_hash",
            'status' => "ALTER TABLE admins ADD COLUMN status VARCHAR(30) NOT NULL DEFAULT 'active' AFTER role",
            'permissions_json' => "ALTER TABLE admins ADD COLUMN permissions_json LONGTEXT NULL AFTER status",
            'last_seen_at' => "ALTER TABLE admins ADD COLUMN last_seen_at DATETIME NULL AFTER permissions_json",
            'created_by' => "ALTER TABLE admins ADD COLUMN created_by VARCHAR(190) NULL AFTER last_seen_at",
        ];
        foreach ($adminColumns as $column => $sql) {
            if (!phonix_column_exists($pdo, 'admins', $column)) {
                $pdo->exec($sql);
            }
        }

        $pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(150) NOT NULL,
            email VARCHAR(190) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            phone VARCHAR(50) NULL,
            address_line1 VARCHAR(255) NULL,
            address_line2 VARCHAR(255) NULL,
            city VARCHAR(120) NULL,
            country VARCHAR(120) NULL,
            email_verified_at TIMESTAMP NULL DEFAULT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_users_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        if (!phonix_column_exists($pdo, 'users', 'email_verified_at')) {
            $pdo->exec('ALTER TABLE users ADD email_verified_at TIMESTAMP NULL DEFAULT NULL AFTER password_hash');
        }

        $temporaryPassword = 'Phonix-' . bin2hex(random_bytes(6)) . '!';
        $hash = password_hash($temporaryPassword, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare('SELECT id FROM admins WHERE email = ? LIMIT 1');
        $stmt->execute([$adminEmail]);
        $adminId = $stmt->fetchColumn();

        if ($adminId) {
            $stmt = $pdo->prepare("UPDATE admins SET name = ?, password_hash = ?, role = 'owner', status = 'active' WHERE id = ?");
            $stmt->execute([$adminName, $hash, $adminId]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO admins (name, email, password_hash, role, status, created_by) VALUES (?, ?, ?, 'owner', 'active', 'secure-reset')");
            $stmt->execute([$adminName, $adminEmail, $hash]);
        }

        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$adminEmail]);
        $userId = $stmt->fetchColumn();

        if ($userId) {
            $stmt = $pdo->prepare('UPDATE users SET name = ?, password_hash = ?, email_verified_at = NOW() WHERE id = ?');
            $stmt->execute([$adminName, $hash, $userId]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, email_verified_at) VALUES (?, ?, ?, NOW())');
            $stmt->execute([$adminName, $adminEmail, $hash]);
        }

        $message = 'Admin login was reset successfully.';
    } catch (Throwable $e) {
        error_log('[Phonix] admin-login-reset.php failed: ' . $e->getMessage());
        $error = app_debug() ? $e->getMessage() : 'Reset failed. Check the server error log.';
    }
}
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Phonix Admin Login Reset</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <main class="container" style="max-width:760px;padding:48px 20px">
        <section class="card" style="padding:28px">
            <h1>Phonix Admin Login Reset</h1>
            <?php if ($message): ?>
                <p><?= e($message) ?></p>
                <p><strong>Email:</strong> <code><?= e($adminEmail) ?></code></p>
                <p><strong>Temporary password:</strong> <code><?= e($temporaryPassword ?? '') ?></code></p>
                <p><a class="btn primary-btn" href="auth.php?mode=signin&amp;next=admin/index.php">Go to sign in</a></p>
                <p>Delete <code>admin-login-reset.php</code> from the server after signing in, or unset <code>PHONIX_ADMIN_RESET_KEY</code>.</p>
            <?php elseif ($error): ?>
                <p><?= e($error) ?></p>
            <?php else: ?>
                <p>This will create/update the owner account in both <code>admins</code> and <code>users</code>.</p>
                <form method="post">
                    <?= csrf_field() ?>
                    <button class="btn primary-btn" type="submit">Reset admin login</button>
                </form>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
