<?php

// Fixture-only worker: never migrate, reset or connect to a configured production database.
$root = dirname(__DIR__, 2);
$loader = require $root.'/vendor/autoload.php';
foreach ($loader->getClassMap() as $class => $path) {
    foreach (['App\\' => 'app', 'Modules\\' => 'Modules'] as $prefix => $directory) {
        if (str_starts_with($class, $prefix)) {
            $local = $root.'/'.$directory.'/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
            if (is_file($local)) $loader->addClassMap([$class => $local]);
        }
    }
}
$loader->setPsr4('App\\', $root.'/app');
$_ENV['APP_BASE_PATH'] = $root;
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$database = getenv('ERP_TEST_MYSQL_DATABASE') ?: '';
if (getenv('ERP_TEST_MYSQL') !== '1' || !preg_match('/^zolo_(test|audit)_[a-z0-9_]+$/D', $database)) {
    throw new RuntimeException('Operations worker requires a named disposable MySQL database.');
}
config(['database.default' => 'erp_regression', 'database.connections.erp_regression' => [
    'driver' => 'mysql', 'host' => getenv('ERP_TEST_MYSQL_HOST') ?: '127.0.0.1', 'port' => getenv('ERP_TEST_MYSQL_PORT') ?: '3306',
    'database' => $database, 'username' => getenv('ERP_TEST_MYSQL_USER') ?: 'zolo_test', 'password' => getenv('ERP_TEST_MYSQL_PASSWORD') ?: '',
    'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'strict' => true,
], 'cache.default' => 'array', 'operations.enabled' => true, 'commercial.enabled' => true]);
$app->instance(App\Services\Platform\CapabilityService::class, new class extends App\Services\Platform\CapabilityService {
    protected function optionalActivationReady(): bool { return true; }
});
Illuminate\Support\Facades\Auth::setUser(App\Models\User::findOrFail(1));
Carbon\CarbonImmutable::setTestNow('2026-10-03 06:00:00 UTC');
$context = new App\Services\Platform\CompanyContext((int) $argv[1], (int) $argv[2], (int) $argv[3]);
// Widen the race window after obtaining the common posting lock.
Illuminate\Support\Facades\DB::listen(function ($query) {
    if (str_contains($query->sql, 'companies') && str_contains($query->sql, 'for update')) usleep(150000);
});
try {
    $data = ['business_date' => '2026-10-03'];
    $record = match ($argv[4]) {
        'production' => app(App\Services\Manufacturing\ProductionService::class)->complete((int) $argv[5], $data + ['completed_qty' => 10], 'race-production', $context, 1),
        'receipt' => app(App\Services\JobWork\JobWorkService::class)->receive((int) $argv[5], $data + ['warehouse_id' => 1,
            'lines' => [['dispatch_line_id' => (int) $argv[6], 'accepted_qty' => 300, 'rejected_qty' => 0, 'loss_qty' => 0]]], 'race-receipt:'.$argv[7], $context, 1),
        'serial' => app(App\Services\Industry\ProjectService::class)->allocate((int) $argv[5], $data + ['stock_identity_id' => (int) $argv[6]], 'race-serial:'.$argv[7], $context, 1),
    };
    echo json_encode(['posted' => true, 'id' => $record->id], JSON_THROW_ON_ERROR);
} catch (Illuminate\Validation\ValidationException $error) {
    echo json_encode(['posted' => false, 'message' => $error->getMessage()], JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage()); exit(1);
}
