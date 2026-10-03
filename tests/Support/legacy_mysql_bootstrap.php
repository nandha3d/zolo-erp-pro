<?php

// Original SalePro tests need a seeded schema. This bootstrap only clears an explicitly opted-in fixture database.
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
// The original seed export references three absent brands and two absent units.
// Prove dry-run rejection, then supply fixture-only parent rows. Retained installations need reviewed master data.
if (Illuminate\Support\Facades\Artisan::call('erp:backfill-company-context', ['--dry-run' => true]) !== 1
    || !str_contains(Illuminate\Support\Facades\Artisan::output(), 'orphan references')) {
    throw new RuntimeException('Expected original seed orphan-reference rejection was not observed.');
}
foreach ([
    ['brands', 'brand_id', 'title', [10, 16, 17]],
    ['units', 'unit_id', 'unit_name', [4, 9]],
] as [$parentTable, $referenceColumn, $labelColumn, $expectedMissing]) {
    $missing = Illuminate\Support\Facades\DB::table('products as product')
        ->leftJoin($parentTable.' as parent', 'product.'.$referenceColumn, '=', 'parent.id')
        ->whereNotNull('product.'.$referenceColumn)->where('product.'.$referenceColumn, '<>', 0)
        ->whereNull('parent.id')->distinct()->orderBy('product.'.$referenceColumn)->pluck('product.'.$referenceColumn)
        ->map(fn ($id) => (int) $id)->all();
    if ($missing !== $expectedMissing) {
        throw new RuntimeException('Original seed reference set changed; review fixture repair before backfill.');
    }
    $template = (array) Illuminate\Support\Facades\DB::table($parentTable)->where('id', 1)->first();
    foreach ($missing as $id) {
        $row = $template;
        $row['id'] = $id;
        $row[$labelColumn] = 'Legacy fixture '.$parentTable.' '.$id;
        if ($parentTable === 'units') {
            $row['unit_code'] = 'Fixture'.$id;
        }
        Illuminate\Support\Facades\DB::table($parentTable)->insert($row);
    }
}
$maintenance = Mockery::mock(Illuminate\Contracts\Foundation\MaintenanceMode::class);
$maintenance->shouldReceive('active')->andReturn(true);
$app->instance(Illuminate\Contracts\Foundation\MaintenanceMode::class, $maintenance);
foreach ([['--dry-run' => true], [], ['--dry-run' => true]] as $options) {
    if (Illuminate\Support\Facades\Artisan::call('erp:backfill-company-context', $options) !== 0) {
        throw new RuntimeException('Seeded backfill rehearsal failed: '.Illuminate\Support\Facades\Artisan::output());
    }
}
printf("Legacy fixture migration, original seeders and dry-run/write/dry-run: %.3f seconds\n", microtime(true) - $started);
// Each original test creates its own application using the fixture environment above.
Illuminate\Support\Facades\DB::purge('mysql');
Illuminate\Support\Facades\Facade::clearResolvedInstances();
Mockery::close();
