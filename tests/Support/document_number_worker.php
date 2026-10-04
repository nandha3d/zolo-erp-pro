<?php

use App\Services\ERP\SaleService;
use App\Services\Platform\CompanyContext;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$database = getenv('ERP_TEST_MYSQL_DATABASE') ?: '';
if (getenv('ERP_TEST_MYSQL') !== '1' || !preg_match('/^zolo_(test|audit)_[a-z0-9_]+$/D', $database)) {
    throw new RuntimeException('Concurrent numbering worker requires an explicitly named disposable MySQL database.');
}
config([
    'database.default' => 'erp_regression',
    'database.connections.erp_regression' => [
        'driver' => 'mysql', 'host' => getenv('ERP_TEST_MYSQL_HOST') ?: '127.0.0.1',
        'port' => getenv('ERP_TEST_MYSQL_PORT') ?: '3306', 'database' => $database,
        'username' => getenv('ERP_TEST_MYSQL_USER') ?: 'zolo_test', 'password' => getenv('ERP_TEST_MYSQL_PASSWORD') ?: '',
        'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'strict' => true,
    ],
    'cache.default' => 'array',
]);
$context = new CompanyContext((int) $argv[1], (int) $argv[2], (int) $argv[3]);
try {
    $sale = app(SaleService::class)->createSale([
    'customer_id' => 1, 'warehouse_id' => 1, 'business_date' => '2026-10-03', 'sale_status' => 2,
    'items' => [['product_id' => 1, 'qty' => 1, 'net_unit_price' => 10]],
    ], 1, $context);
    echo json_encode(['number' => $sale->reference_no], JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage());
    exit(1);
}
