<?php
require_once __DIR__ . '/includes/layout.php';

$ctx = admin_boot('maintenance_tools');
$pdo = $ctx['pdo'];
verify_csrf_or_fail($_GET['_csrf'] ?? null);

try {
    admin_log_maintenance_run($pdo, 'database_backup', count(admin_database_tables($pdo)), 'Downloaded SQL database backup.');
    admin_log_activity($pdo, 'database_backup_downloaded', 'maintenance', null, 'SQL database backup downloaded.');
    admin_stream_database_backup($pdo);
} catch (Throwable $e) {
    error_log('[Phonix database backup] ' . $e->getMessage());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
    }
    echo app_debug() ? $e->getMessage() : 'Database backup could not be generated.';
}
exit;
