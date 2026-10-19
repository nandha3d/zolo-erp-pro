<?php

use App\Models\Purchase;
use App\Models\User;
use App\Services\Commercial\CommercialApplicationService;
use App\Services\Commercial\PurchaseReceiptService;
use App\Services\Platform\CompanyContext;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// Usage: php commercial_lifecycle_worker.php <payload-json-path> <start-at-file>
// This worker never creates or resets schemas, and accepts only the dedicated concurrency fixture database.
if (getenv('ERP_TEST_MYSQL') !== '1' || getenv('ERP_TEST_MYSQL_DATABASE') !== 'zolo_test_textile_concurrency') {
    fwrite(STDERR, 'Commercial concurrency worker requires zolo_test_textile_concurrency exclusively.');
    exit(1);
}
$root = dirname(__DIR__, 2);
$loader = require $root.'/vendor/autoload.php';
// A copied checkout may retain Composer class-map paths from another checkout.
foreach ($loader->getClassMap() as $class => $path) {
    if (str_starts_with($class, 'App\\')) {
        $local = $root.'/app/'.str_replace('\\', '/', substr($class, 4)).'.php';
        if (is_file($local)) $loader->addClassMap([$class => $local]);
    }
}
$loader->setPsr4('App\\', $root.'/app');
$_ENV['APP_BASE_PATH'] = $root;
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['database.default' => 'erp_regression', 'database.connections.erp_regression' => [
    'driver' => 'mysql', 'host' => getenv('ERP_TEST_MYSQL_HOST') ?: '127.0.0.1',
    'port' => getenv('ERP_TEST_MYSQL_PORT') ?: '3306', 'database' => 'zolo_test_textile_concurrency',
    'username' => getenv('ERP_TEST_MYSQL_USER') ?: 'zolo_test', 'password' => getenv('ERP_TEST_MYSQL_PASSWORD') ?: '',
    'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'strict' => true,
], 'cache.default' => 'array', 'commercial.enabled' => true, 'operations.enabled' => false, 'compliance.enabled' => false]);
DB::purge('erp_regression');
$held = false;
$lockWaitMs = 0.0;
try {
    $payload = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
    $context = new CompanyContext(...array_map('intval', $payload['context']));
    Auth::setUser(User::findOrFail(1));
    Carbon\CarbonImmutable::setTestNow('2026-10-03 06:00:00 UTC');
    DB::listen(function (QueryExecuted $query) use (&$held, &$lockWaitMs) {
        if (!$held && str_contains(strtolower($query->sql), 'companies') && str_contains(strtolower($query->sql), 'for update')) {
            $held = true;
            $lockWaitMs = $query->time;
            // Keep the actual application lock for a bounded interval after both processes cross the start barrier.
            usleep(400000);
        }
    });
    file_put_contents($argv[1].'.ready', 'ready');
    $deadline = microtime(true) + 25;
    do {
        clearstatcache(true, $argv[2]);
        $startAt = (float) file_get_contents($argv[2]);
        if ($startAt > 0 && microtime(true) >= $startAt) break;
        if (microtime(true) > $deadline) throw new RuntimeException('Concurrency start barrier timed out.');
        usleep(1000);
    } while (true);
    $data = $payload['data'];
    $record = $payload['action'] === 'receipt'
        ? app(PurchaseReceiptService::class)->receive(Purchase::visibleIn($context)->findOrFail($data['purchase_id']),
            array_diff_key($data, ['purchase_id' => true]), $payload['key'], $context)
        : app(CommercialApplicationService::class)->create($payload['action'], $data, $payload['key'], 1, $context);
    echo json_encode(['posted' => true, 'id' => $record->id, 'lock_held' => $held, 'lock_wait_ms' => $lockWaitMs], JSON_THROW_ON_ERROR);
} catch (ValidationException $error) {
    echo json_encode(['posted' => false, 'error_type' => 'validation', 'errors' => $error->errors(), 'lock_held' => $held,
        'lock_wait_ms' => $lockWaitMs], JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    fwrite(STDERR, get_class($error).': '.$error->getMessage());
    exit(1);
}
