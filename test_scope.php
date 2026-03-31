<?php
require_once __DIR__ . '/includes/db.php';
ob_start();
require_once __DIR__ . '/views/admin/students.php';
$out = ob_get_clean();
echo "SUCCESS: " . strlen($out);
?>
