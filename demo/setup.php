<?php
require __DIR__ . '/vendor/autoload.php';
foreach (['runtime','runtime/sessions','web/assets'] as $dir) if (!is_dir(__DIR__ . '/' . $dir)) mkdir(__DIR__ . '/' . $dir, 0755, true);
$keyFile = __DIR__ . '/runtime/cookie-key.php';
if (!is_file($keyFile)) file_put_contents($keyFile, '<?php return ' . var_export(bin2hex(random_bytes(32)),true) . ';');
echo "Directorios y clave local preparados.\n";
