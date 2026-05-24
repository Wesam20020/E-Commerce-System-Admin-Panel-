<?php
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/../includes/bootstrap.php';

if (!app_flag('allow_debug_endpoints')) {
    http_response_code(404);
    echo json_encode([
        'ok' => false,
        'message' => 'Not found'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $stmt = $pdo->query('SELECT DATABASE() AS db_name');
    $row = $stmt->fetch();
    echo json_encode([
        'ok' => true,
        'database' => $row['db_name'] ?? null,
        'message' => 'Database connection works.'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('[Phonix] api/test-db.php failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => app_debug() ? $e->getMessage() : 'Database test failed.'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
