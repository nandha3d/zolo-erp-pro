<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use DB;

class ResetDB extends Command
{
    use \App\Traits\CacheForget;

    protected $signature = 'reset:db {--confirm-demo-reset : Confirm resetting the demo database}';

    protected $description = 'Reset DB in the demo';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        if (!$this->laravel->environment('demo') || !$this->option('confirm-demo-reset')) {
            $this->error('Database reset requires the demo environment and --confirm-demo-reset.');
            return self::FAILURE;
        }
        $dumpPath = base_path('salepropos.sql');
        if (!is_file($dumpPath) || !is_readable($dumpPath) || !filesize($dumpPath)) {
            $this->error('Demo database dump is missing or unreadable; no data changed.');
            return self::FAILURE;
        }
        $dump = file_get_contents($dumpPath);
        if ($dump === false || trim($dump) === '') {
            $this->error('Demo database dump is empty; no data changed.');
            return self::FAILURE;
        }
        //clearing all the cached queries
        $this->cacheForget('biller_list');
        $this->cacheForget('brand_list');
        $this->cacheForget('category_list');
        $this->cacheForget('coupon_list');
        $this->cacheForget('customer_list');
        $this->cacheForget('customer_group_list');
        $this->cacheForget('product_list');
        $this->cacheForget('product_list_with_variant');
        $this->cacheForget('warehouse_list');
        $this->cacheForget('table_list');
        $this->cacheForget('tax_list');
        $this->cacheForget('currency');
        $this->cacheForget('general_setting');
        $this->cacheForget('pos_setting');
        $this->cacheForget('user_role');
        $this->cacheForget('permissions');
        $this->cacheForget('role_has_permissions');
        $this->cacheForget('role_has_permissions_list');

        // Disable foreign key checks to avoid constraint issues
        DB::statement('SET FOREIGN_KEY_CHECKS = 0;');
        $tables = DB::select('SHOW TABLES');
        $key = 'Tables_in_' . env('DB_DATABASE');
        foreach ($tables as $table) {
            Schema::drop($table->$key);
        }
        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS = 1;');

        //importing data from DB
        DB::unprepared($dump);
        return self::SUCCESS;
    }
}
