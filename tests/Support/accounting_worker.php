<?php

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$database = getenv('ERP_TEST_MYSQL_DATABASE') ?: '';
if (getenv('ERP_TEST_MYSQL') !== '1' || !preg_match('/^zolo_(test|audit)_[a-z0-9_]+$/D', $database)) {
    throw new RuntimeException('Accounting worker requires an explicitly named disposable MySQL database.');
}
config(['database.default' => 'erp_regression', 'database.connections.erp_regression' => [
    'driver' => 'mysql', 'host' => getenv('ERP_TEST_MYSQL_HOST') ?: '127.0.0.1',
    'port' => getenv('ERP_TEST_MYSQL_PORT') ?: '3306', 'database' => $database,
    'username' => getenv('ERP_TEST_MYSQL_USER') ?: 'zolo_test', 'password' => getenv('ERP_TEST_MYSQL_PASSWORD') ?: '',
    'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'strict' => true,
], 'cache.default' => 'array']);
$context = new App\Services\Platform\CompanyContext((int) $argv[1], (int) $argv[2], (int) $argv[3]);
try {
    if ($argv[4] === 'journal') {
        $entry = app(App\Services\Accounting\AccountingPostingService::class)->postJournalEntry([
            'entry_date' => '2026-10-03', 'description' => 'Concurrent replay', 'idempotency_key' => 'concurrent-replay', 'created_by' => 1,
        ], [['chart_of_account_id' => (int) $argv[6], 'debit' => 1], ['chart_of_account_id' => (int) $argv[7], 'credit' => 1]], $context);
        echo json_encode(['posted' => true, 'id' => $entry->id], JSON_THROW_ON_ERROR);
    } else {
        try {
            $allocation = app(App\Services\Accounting\OpenItemService::class)->allocate((int) $argv[6],
                (int) $argv[7 + (int) $argv[5]], 8, '2026-10-04', 'race:'.$argv[5], $context, null, 1);
            echo json_encode(['posted' => true, 'id' => $allocation->id], JSON_THROW_ON_ERROR);
        } catch (InvalidArgumentException $error) {
            if ($error->getMessage() !== 'Allocation exceeds the remaining open amount.') {
                throw $error;
            }
            echo json_encode(['posted' => false], JSON_THROW_ON_ERROR);
        }
    }
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage());
    exit(1);
}
