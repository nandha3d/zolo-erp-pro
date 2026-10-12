<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Company master details printed on documents (address, contact, PAN, logo) and the Indian Rupee as a currency. */
return new class extends Migration
{
    private const COLUMNS = ['address', 'city', 'postal_code', 'phone', 'email', 'website', 'pan', 'logo'];

    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            foreach (['address' => 1000, 'city' => 100, 'postal_code' => 20, 'phone' => 100, 'email' => 191, 'website' => 191, 'pan' => 10, 'logo' => 191] as $column => $length) {
                if (!Schema::hasColumn('companies', $column)) {
                    $column === 'address' ? $table->text($column)->nullable() : $table->string($column, $length)->nullable();
                }
            }
        });
        if (Schema::hasTable('currencies') && !DB::table('currencies')->where('code', 'INR')->exists()) {
            $row = ['name' => 'Indian Rupee', 'code' => 'INR', 'exchange_rate' => 1, 'created_at' => now(), 'updated_at' => now()];
            if (Schema::hasColumn('currencies', 'symbol')) $row['symbol'] = '₹';
            if (Schema::hasColumn('currencies', 'is_active')) $row['is_active'] = true;
            DB::table('currencies')->insert($row);
        }
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            foreach (self::COLUMNS as $column) {
                if (Schema::hasColumn('companies', $column)) $table->dropColumn($column);
            }
        });
    }
};
