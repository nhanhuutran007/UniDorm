<?php
$dirs = [
    'c:/xampp/htdocs/UniDorm/views/admin/',
    'c:/xampp/htdocs/UniDorm/views/student/'
];

foreach ($dirs as $dir) {
    if (!is_dir($dir)) continue;
    $files = glob($dir . '*.php');
    foreach ($files as $file) {
        $content = file_get_contents($file);
        if (strpos($content, 'includes/db.php') === false && strpos($content, 'ob_start();') !== false) {
            $content = str_replace("ob_start();", "ob_start();\n\nrequire_once __DIR__ . '/../../includes/db.php';", $content);
            file_put_contents($file, $content);
            echo "Updated $file\n";
        }
    }
}
echo "Done.";
?>
