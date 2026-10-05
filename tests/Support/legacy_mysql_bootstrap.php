<?php

// Legacy ERP tests need a seeded schema. This bootstrap only clears an explicitly opted-in fixture database.
require __DIR__.'/../../vendor/autoload.php';

$fixtureDatabase = getenv('ERP_TEST_MYSQL_DATABASE') ?: '';
if (getenv('ERP_TEST_MYSQL') !== '1' || !preg_match('/^zolo_(test|audit)_[a-z0-9_]+$/D', $fixtureDatabase)) {
    throw new RuntimeException('Legacy rehearsal requires explicit disposable MySQL opt-in and database name.');
}
$fixtureEnvironment = [
    'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => $fixtureDatabase,
    'DB_HOST' => getenv('ERP_TEST_MYSQL_HOST') ?: '127.0.0.1',
    'DB_PORT' => getenv('ERP_TEST_MYSQL_PORT') ?: '3306',
    'DB_USERNAME' => getenv('ERP_TEST_MYSQL_USER') ?: 'zolo_test',
    'DB_PASSWORD' => getenv('ERP_TEST_MYSQL_PASSWORD') ?: '',
    'VIEW_COMPILED_PATH' => dirname(__DIR__, 2).'/storage/framework/views',
];
foreach ($fixtureEnvironment as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $_SERVER[$key] = $value;
}
if (!is_dir($fixtureEnvironment['VIEW_COMPILED_PATH'])) {
    mkdir($fixtureEnvironment['VIEW_COMPILED_PATH'], 0777, true);
}
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (Illuminate\Support\Facades\DB::getDriverName() !== 'mysql'
    || Illuminate\Support\Facades\DB::connection()->getDatabaseName() !== $fixtureDatabase) {
    throw new RuntimeException('Refusing to clear a connection outside the named MySQL fixture database.');
}
Illuminate\Support\Facades\Schema::dropAllTables();
$started = microtime(true);
foreach ([
    ['migrate', ['--force' => true]],
    ['db:seed', ['--class' => Database\Seeders\Tenant\TenantDatabaseSeeder::class, '--force' => true]],
    ['db:seed', ['--class' => Database\Seeders\ChartOfAccountsSeeder::class, '--force' => true]],
] as [$command, $options]) {
    if (Illuminate\Support\Facades\Artisan::call($command, $options) !== 0) {
        throw new RuntimeException('Legacy fixture preparation failed: '.$command);
    }
}
// The repository seed is self-consistent (no dangling brand/unit parents), so the company backfill runs directly.
$maintenance = Mockery::mock(Illuminate\Contracts\Foundation\MaintenanceMode::class);
$maintenance->shouldReceive('active')->andReturn(true);
$app->instance(Illuminate\Contracts\Foundation\MaintenanceMode::class, $maintenance);
foreach ([['--dry-run' => true], [], ['--dry-run' => true]] as $options) {
    if (Illuminate\Support\Facades\Artisan::call('erp:backfill-company-context', $options) !== 0) {
        throw new RuntimeException('Seeded backfill rehearsal failed: '.Illuminate\Support\Facades\Artisan::output());
    }
}
printf("Legacy fixture migration, original seeders and dry-run/write/dry-run: %.3f seconds\n", microtime(true) - $started);
foreach (App\Models\Company::pluck('id') as $companyId) {
    app(App\Services\Accounting\SemanticAccountResolver::class)->seedCompany((int) $companyId);
}
// Each original test creates its own application using the fixture environment above.
Illuminate\Support\Facades\DB::purge('mysql');
Illuminate\Support\Facades\Facade::clearResolvedInstances();
Mockery::close();
