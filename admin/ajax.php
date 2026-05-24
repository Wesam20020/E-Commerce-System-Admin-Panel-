<?php
if (!defined('ADMIN_AJAX_SECTION')) {
    define('ADMIN_AJAX_SECTION', true);
}
require_once __DIR__ . '/includes/layout.php';

$section = admin_normalize_section((string) ($_GET['section'] ?? 'index'));

try {
    admin_boot($section);
    $file = admin_section_file($section);
    if (!is_file($file)) {
        http_response_code(404);
        ?>
        <section class="admin-card glass-panel"><div class="admin-empty-state"><span class="material-symbols-outlined">error</span><strong>Admin section not found</strong><p>The requested admin section does not exist.</p></div></section>
        <?php
        exit;
    }
    include $file;
} catch (Throwable $e) {
    error_log('[Phonix admin ajax] ' . $e->getMessage());
    http_response_code(500);
    $message = function_exists('app_debug') && app_debug() ? $e->getMessage() : 'Could not load admin section.';
    ?>
    <section class="admin-card glass-panel"><div class="admin-empty-state"><span class="material-symbols-outlined">error</span><strong>Section could not load</strong><p><?= e($message) ?></p></div></section>
    <?php
}
