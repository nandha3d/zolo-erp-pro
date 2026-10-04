<?php

use App\Services\Inventory\InventoryMovementService;
use App\Services\Inventory\StockLine;
use App\Services\Inventory\StockMovementCommand;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

// Usage: php stock_issue_worker.php <product_id> <start_at_unix_float>
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$database = getenv('ERP_TEST_MYSQL_DATABASE') ?: '';
if (getenv('ERP_TEST_MYSQL') !== '1' || !preg_match('/^zolo_(test|audit)_[a-z0-9_]+$/D', $database)) {
    throw new RuntimeException('Concurrent stock worker requires an explicitly named disposable MySQL database.');
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
DB::connection()->getPdo();
// Hold the stock row lock long enough that an unlocked read-check-write would double-sell.
DB::listen(function (QueryExecuted $query) {
    if (str_contains($query->sql, 'product_warehouse') && str_contains($query->sql, 'for update')) {
        usleep(300000);
    }
});
while (microtime(true) < (float) $argv[2]) {
    usleep(1000);
}
try {
    $movement = app(InventoryMovementService::class)->issue(new StockMovementCommand(
        date: '2026-10-04',
        lines: [new StockLine(productId: (int) $argv[1], qty: 1)],
        warehouseId: 1,
    ));
    echo json_encode(['movement' => $movement->id], JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage());
    exit(1);
}
