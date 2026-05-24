<?php
require_once __DIR__ . '/../includes/bootstrap.php';

store_ensure_email_tables($pdo);
$settings = fetch_site_settings($pdo);
$token = trim((string) ($_GET['token'] ?? ($_SERVER['HTTP_X_PHONIX_EMAIL_TOKEN'] ?? '')));
$expected = trim((string) ($settings['email_cron_token'] ?? ''));

if ($expected === '' || $token === '' || !hash_equals($expected, $token)) {
    json_response(['ok' => false, 'message' => 'Invalid email worker token.'], 403);
}

$limit = max(1, min(50, (int) ($_GET['limit'] ?? ($settings['email_delivery_batch_size'] ?? 10))));
$result = store_process_email_queue($pdo, $limit);
json_response([
    'ok' => true,
    'processed' => (int) ($result['processed'] ?? 0),
    'sent' => (int) ($result['sent'] ?? 0),
    'failed' => (int) ($result['failed'] ?? 0),
    'message' => (string) ($result['message'] ?? ''),
]);
