<?php

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$database = getenv('ERP_TEST_MYSQL_DATABASE') ?: '';
if (getenv('ERP_TEST_MYSQL') !== '1' || !preg_match('/^zolo_(test|audit)_[a-z0-9_]+$/D', $database)) {
    throw new RuntimeException('Return worker requires an explicitly named disposable MySQL database.');
}
config(['database.default' => 'erp_regression', 'database.connections.erp_regression' => [
    'driver' => 'mysql', 'host' => getenv('ERP_TEST_MYSQL_HOST') ?: '127.0.0.1', 'port' => getenv('ERP_TEST_MYSQL_PORT') ?: '3306',
    'database' => $database, 'username' => getenv('ERP_TEST_MYSQL_USER') ?: 'zolo_test', 'password' => getenv('ERP_TEST_MYSQL_PASSWORD') ?: '',
    'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'strict' => true,
], 'cache.default' => 'array', 'commercial.enabled' => true, 'compliance.enabled' => true]);
$context = new App\Services\Platform\CompanyContext((int) $argv[1], (int) $argv[2], (int) $argv[3]);
while (microtime(true) < (float) $argv[5]) usleep(10000);
try {
    $note = app(App\Services\Commercial\ReturnService::class)->approve('sale', (int) $argv[4], $context, 1);
    echo json_encode(['posted' => true, 'id' => $note->id], JSON_THROW_ON_ERROR);
} catch (Illuminate\Validation\ValidationException $error) {
    echo json_encode(['posted' => false, 'fields' => array_keys($error->errors())], JSON_THROW_ON_ERROR);
} catch (Throwable $error) { fwrite(STDERR, $error->getMessage()); exit(1); }
