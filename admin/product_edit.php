<?php
require __DIR__ . '/includes/common.php';
$query = $_GET;
unset($query['section']);
redirect_to(admin_page_url('product_edit', $query));
