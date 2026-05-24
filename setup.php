<?php
require __DIR__ . '/includes/bootstrap.php';

if (!app_flag('allow_setup')) {
    http_response_code(404);
    exit('Not found');
}

function executeSqlFile(PDO $pdo, string $path): void
{
    $sql = file_get_contents($path);
    if ($sql === false) {
        throw new RuntimeException('Cannot read SQL file: ' . $path);
    }

    $parts = preg_split('/;\s*\n/', $sql);
    if ($parts === false) {
        throw new RuntimeException('Cannot split SQL file.');
    }

    foreach ($parts as $part) {
        $statement = trim($part);
        if ($statement === '' || str_starts_with($statement, '--')) {
            continue;
        }
        $pdo->exec($statement);
    }
}

$message = null;
$error = null;
$adminPassword = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_fail($_POST['_csrf'] ?? null);
    try {
        executeSqlFile($pdo, __DIR__ . '/database/schema.sql');
        executeSqlFile($pdo, __DIR__ . '/database/seed.sql');

        $adminEmail = 'admin@phonix.local';
        $adminPassword = 'Phonix-' . bin2hex(random_bytes(6)) . '!';
        $adminName = 'Phonix Admin';

        $stmt = $pdo->prepare('SELECT id FROM admins WHERE email = ? LIMIT 1');
        $stmt->execute([$adminEmail]);
        $existing = $stmt->fetchColumn();

        if (!$existing) {
            $hash = password_hash($adminPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO admins (name, email, password_hash, role, status) VALUES (?, ?, ?, 'owner', 'active')");
            $stmt->execute([$adminName, $adminEmail, $hash]);
        } else {
            $adminPassword = 'Existing admin password was not changed.';
        }

        $message = 'Done. Database tables were created and starter data was inserted successfully.';
    } catch (Throwable $e) {
        error_log('[Phonix] setup.php failed: ' . $e->getMessage());
        $error = app_debug() ? $e->getMessage() : 'Setup failed. Check the server error log.';
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Phonix Setup</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body{padding:40px 0}.setup-box{max-width:860px;margin:0 auto;padding:32px;background:var(--surface);border:1px solid var(--line);border-radius:24px;box-shadow:var(--shadow)}
        code,pre{background:var(--surface-3);padding:.2rem .45rem;border-radius:10px}
        pre{padding:1rem;overflow:auto}
        .ok{padding:1rem 1.2rem;background:rgba(16,185,129,.12);color:#059669;border-radius:18px;border:1px solid rgba(16,185,129,.25)}
        .err{padding:1rem 1.2rem;background:rgba(239,68,68,.12);color:#dc2626;border-radius:18px;border:1px solid rgba(239,68,68,.25)}
        ul{line-height:1.9}
    </style>
</head>
<body>
<div class="container">
    <div class="setup-box">
        <div class="page-head" style="padding-top:0">
            <div>
                <h1>Phonix Setup</h1>
                <p>This page is disabled in production by default. Enable it only temporarily with <code>PHONIX_ALLOW_SETUP=1</code>.</p>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="ok"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
            <div style="height:16px"></div>
            <ul>
                <li>Admin email: <code>admin@phonix.local</code></li>
                <li>Admin password: <code><?= htmlspecialchars((string) $adminPassword, ENT_QUOTES, 'UTF-8') ?></code></li>
                <li>Disable <code>PHONIX_ALLOW_SETUP</code> immediately after use.</li>
            </ul>
        <?php elseif ($error): ?>
            <div class="err"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php else: ?>
            <ul>
                <li>The database connection is already configured.</li>
                <li>Use this only once on a fresh installation.</li>
                <li>Disable it again immediately after success.</li>
            </ul>
            <form method="post">
                <?= csrf_field() ?>
                <button class="primary-btn" type="submit">Create Tables and Insert Starter Data</button>
            </form>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
