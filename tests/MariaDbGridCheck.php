<?php
require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/vendor/yiisoft/yii2/Yii.php';
$db = new yii\db\Connection(['dsn' => getenv('UM_TEST_DB_DSN'), 'username' => getenv('UM_TEST_DB_USER'), 'password' => getenv('UM_TEST_DB_PASSWORD')]);
$database = 'um_gridcheck_' . bin2hex(random_bytes(6));
$created = false;
try {
    $db->createCommand('CREATE DATABASE ' . $db->quoteTableName($database) . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci')->execute();
    $created = true;
    $dsn = preg_replace('/;dbname=[^;]+/', ';dbname=' . $database, getenv('UM_TEST_DB_DSN'));
    if (!str_ends_with($dsn, ';dbname=' . $database)) throw new RuntimeException('Cannot isolate the grid test database.');
    $environment = getenv();
    $environment['UM_TEST_GRID_DB_DSN'] = $dsn;
    $process = proc_open([PHP_BINARY, dirname(__DIR__) . '/vendor/phpunit/phpunit/phpunit', '--configuration', 'phpunit.xml.dist',
        '--filter', 'GridQueryTest|AuditSearchTest|UserFiltersTest'], [0 => STDIN, 1 => STDOUT, 2 => STDERR], $pipes, dirname(__DIR__), $environment);
    if (!is_resource($process) || proc_close($process) !== 0) throw new RuntimeException('MariaDB grid query tests failed.');
} finally {
    if ($created && preg_match('/^um_gridcheck_[a-f0-9]{12}$/D', $database)) $db->createCommand('DROP DATABASE ' . $db->quoteTableName($database))->execute();
}
