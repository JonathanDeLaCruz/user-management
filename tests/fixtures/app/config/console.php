<?php
$config = require __DIR__ . '/common.php';
unset($config['components']['user']);
$config['modules']['user-management']['controllerNamespace'] = 'webvimark\\modules\\UserManagement\\commands';
return $config;
