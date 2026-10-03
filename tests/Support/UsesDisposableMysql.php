<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

trait UsesDisposableMysql
{
    protected function configureDisposableMysql(): void
    {
        if (getenv('ERP_TEST_MYSQL') !== '1') {
            $this->markTestSkipped('Set ERP_TEST_MYSQL=1 to run disposable MySQL proof.');
        }
        $database = getenv('ERP_TEST_MYSQL_DATABASE') ?: '';
        if (!preg_match('/^zolo_(test|audit)_[a-z0-9_]+$/D', $database)) {
            throw new RuntimeException('MySQL fixtures require an explicitly named zolo_test_* or zolo_audit_* disposable database.');
        }
        config([
            'database.default' => 'erp_regression',
            'database.connections.erp_regression' => [
                'driver' => 'mysql', 'host' => getenv('ERP_TEST_MYSQL_HOST') ?: '127.0.0.1',
                'port' => getenv('ERP_TEST_MYSQL_PORT') ?: '3306', 'database' => $database,
                'username' => getenv('ERP_TEST_MYSQL_USER') ?: 'zolo_test',
                'password' => getenv('ERP_TEST_MYSQL_PASSWORD') ?: '',
                'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '', 'strict' => true,
            ],
        ]);
        DB::purge('erp_regression');
        // Only the explicitly opted-in, name-validated fixture database is cleared.
        Schema::dropAllTables();
    }
}
