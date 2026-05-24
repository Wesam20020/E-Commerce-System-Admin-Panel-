<?php
require __DIR__ . '/includes/layout.php';
$initialSection = admin_normalize_section((string) ($_GET['section'] ?? 'index'));
admin_boot($initialSection);
admin_shell($initialSection);
