<?php
require_once __DIR__ . '/helpers.php';

function ensure_auth_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS user_remember_tokens (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL,
        selector VARCHAR(24) NOT NULL,
        token_hash VARCHAR(255) NOT NULL,
        expires_at DATETIME NOT NULL,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_user_remember_selector (selector),
        KEY idx_user_remember_user (user_id),
        CONSTRAINT fk_user_remember_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $columns = [];
    $stmt = $pdo->query('SHOW COLUMNS FROM users');
    foreach ($stmt as $column) {
        $columns[strtolower((string) $column['Field'])] = true;
    }

    if (!isset($columns['email_verified_at'])) {
        $pdo->exec('ALTER TABLE users ADD COLUMN email_verified_at DATETIME NULL AFTER country');
    }
    if (!isset($columns['last_login_at'])) {
        $pdo->exec('ALTER TABLE users ADD COLUMN last_login_at DATETIME NULL AFTER email_verified_at');
    }

    $done = true;
}

function auth_cookie_name(): string
{
    return 'phonix_remember';
}

function auth_guest_session_key(): string
{
    if (empty($_SESSION['guest_session_key']) || !is_string($_SESSION['guest_session_key'])) {
        $_SESSION['guest_session_key'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['guest_session_key'];
}

function auth_current_user(PDO $pdo): ?array
{
    static $user = false;
    if ($user !== false) {
        return $user;
    }

    ensure_auth_schema($pdo);
    auth_guest_session_key();

    $user = null;
    $id = $_SESSION['auth_user_id'] ?? null;
    if (is_numeric($id)) {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => (int) $id]);
        $found = $stmt->fetch();
        if ($found) {
            $user = $found;
            return $user;
        }
        unset($_SESSION['auth_user_id']);
    }

    if (!empty($_COOKIE[auth_cookie_name()])) {
        $parts = explode(':', (string) $_COOKIE[auth_cookie_name()], 2);
        if (count($parts) === 2) {
            [$selector, $validator] = $parts;
            if ($selector !== '' && $validator !== '') {
                $stmt = $pdo->prepare('SELECT rt.*, u.*
                    FROM user_remember_tokens rt
                    INNER JOIN users u ON u.id = rt.user_id
                    WHERE rt.selector = :selector AND rt.expires_at > NOW()
                    LIMIT 1');
                $stmt->execute(['selector' => $selector]);
                $row = $stmt->fetch();
                if ($row && password_verify($validator, (string) $row['token_hash'])) {
                    $_SESSION['auth_user_id'] = (int) $row['user_id'];
                    session_regenerate_id(true);
                    auth_rotate_remember_token($pdo, (int) $row['user_id'], $selector);
                    $user = extract_user_columns($row);
                    return $user;
                }
            }
        }
        auth_forget_remember_cookie();
    }

    return $user;
}

function extract_user_columns(array $row): array
{
    $user = [];
    $allowed = [
        'id', 'name', 'email', 'password_hash', 'phone', 'address_line1', 'address_line2',
        'city', 'country', 'email_verified_at', 'last_login_at', 'created_at', 'updated_at'
    ];
    foreach ($allowed as $field) {
        $user[$field] = $row[$field] ?? null;
    }
    return $user;
}

function auth_is_logged_in(PDO $pdo): bool
{
    return auth_current_user($pdo) !== null;
}

function auth_login(PDO $pdo, array $user, bool $remember = false): void
{
    ensure_auth_schema($pdo);
    session_regenerate_id(true);
    $_SESSION['auth_user_id'] = (int) $user['id'];
    $_SESSION['guest_session_key'] = $_SESSION['guest_session_key'] ?? bin2hex(random_bytes(24));

    $stmt = $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
    $stmt->execute(['id' => (int) $user['id']]);

    if ($remember) {
        auth_issue_remember_token($pdo, (int) $user['id']);
    } else {
        auth_forget_remember_cookie();
    }
}

function auth_logout(PDO $pdo): void
{
    ensure_auth_schema($pdo);
    if (!empty($_COOKIE[auth_cookie_name()])) {
        $parts = explode(':', (string) $_COOKIE[auth_cookie_name()], 2);
        if (count($parts) === 2) {
            $selector = $parts[0];
            $stmt = $pdo->prepare('DELETE FROM user_remember_tokens WHERE selector = :selector');
            $stmt->execute(['selector' => $selector]);
        }
    }

    auth_forget_remember_cookie();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $params['path'], $params['domain'] ?? '', (bool) $params['secure'], (bool) $params['httponly']);
    }
    session_destroy();
}

function auth_forget_remember_cookie(): void
{
    setcookie(auth_cookie_name(), '', [
        'expires' => time() - 3600,
        'path' => '/',
        'httponly' => true,
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'samesite' => 'Lax',
    ]);
    unset($_COOKIE[auth_cookie_name()]);
}

function auth_issue_remember_token(PDO $pdo, int $userId): void
{
    ensure_auth_schema($pdo);
    $selector = bin2hex(random_bytes(6));
    $validator = bin2hex(random_bytes(32));
    $expiresAt = (new DateTimeImmutable('+30 days'))->format('Y-m-d H:i:s');

    $pdo->prepare('DELETE FROM user_remember_tokens WHERE user_id = :user_id')->execute(['user_id' => $userId]);
    $stmt = $pdo->prepare('INSERT INTO user_remember_tokens (user_id, selector, token_hash, expires_at) VALUES (:user_id, :selector, :token_hash, :expires_at)');
    $stmt->execute([
        'user_id' => $userId,
        'selector' => $selector,
        'token_hash' => password_hash($validator, PASSWORD_DEFAULT),
        'expires_at' => $expiresAt,
    ]);

    setcookie(auth_cookie_name(), $selector . ':' . $validator, [
        'expires' => strtotime($expiresAt),
        'path' => '/',
        'httponly' => true,
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'samesite' => 'Lax',
    ]);
}

function auth_rotate_remember_token(PDO $pdo, int $userId, string $selector): void
{
    $pdo->prepare('DELETE FROM user_remember_tokens WHERE selector = :selector')->execute(['selector' => $selector]);
    auth_issue_remember_token($pdo, $userId);
}

function auth_require_user(PDO $pdo): array
{
    $user = auth_current_user($pdo);
    if (!$user) {
        flash_set('error', 'Please sign in to access your account.');
        redirect_to(site_url('auth', ['mode' => 'signin', 'next' => site_url('account')]));
    }
    return $user;
}

function auth_find_user_by_email(PDO $pdo, string $email): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => mb_strtolower(trim($email))]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function auth_validate_registration(PDO $pdo, array $input): array
{
    $errors = [];
    $name = trim((string) ($input['name'] ?? ''));
    $email = mb_strtolower(trim((string) ($input['email'] ?? '')));
    $password = (string) ($input['password'] ?? '');
    $passwordConfirm = (string) ($input['password_confirmation'] ?? '');

    if ($name === '' || mb_strlen($name) < 2) {
        $errors[] = 'Name must be at least 2 characters.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }
    if (mb_strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        $errors[] = 'Password must include letters and numbers.';
    }
    if ($password !== $passwordConfirm) {
        $errors[] = 'Password confirmation does not match.';
    }
    if ($email !== '' && auth_find_user_by_email($pdo, $email)) {
        $errors[] = 'An account with this email already exists.';
    }

    return $errors;
}

function auth_register_user(PDO $pdo, array $input): array
{
    $name = trim((string) $input['name']);
    $email = mb_strtolower(trim((string) $input['email']));
    $password = (string) $input['password'];

    $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, email_verified_at) VALUES (:name, :email, :password_hash, NOW())');
    $stmt->execute([
        'name' => $name,
        'email' => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    ]);

    $id = (int) $pdo->lastInsertId();
    $user = auth_find_user_by_email($pdo, $email);
    if (!$user) {
        throw new RuntimeException('Could not load the new user record.');
    }
    return $user;
}
