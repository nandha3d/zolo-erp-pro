<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('users')) {
            throw new RuntimeException('The core users table must exist before company foundation migration.');
        }
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('legal_name');
            $table->string('trade_name')->nullable();
            $table->char('country_code', 2)->nullable();
            $table->unsignedInteger('base_currency_id')->nullable();
            $table->string('state_code', 20)->nullable();
            $table->string('timezone', 100)->default('UTC');
            $table->string('status', 20)->default('active');
            $table->json('settings_json')->nullable();
            $table->timestamps();
        });

        Schema::create('company_branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->string('branch_type', 30)->default('main');
            $table->text('address')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code']);
            $table->unique(['id', 'company_id']);
        });

        Schema::create('company_user', function (Blueprint $table) {
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            // The existing users table uses increments(), not BIGINT IDs.
            $table->unsignedInteger('user_id');
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('role_id_override')->nullable();
            $table->timestamps();
            $table->primary(['company_id', 'user_id']);
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
        });

        Schema::create('company_user_branches', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id');
            $table->unsignedInteger('user_id');
            $table->unsignedBigInteger('branch_id');
            $table->timestamps();
            $table->primary(['company_id', 'user_id', 'branch_id']);
            $table->foreign(['company_id', 'user_id'])->references(['company_id', 'user_id'])
                ->on('company_user')->restrictOnDelete();
            $table->foreign(['branch_id', 'company_id'])->references(['id', 'company_id'])
                ->on('company_branches')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_user_branches');
        Schema::dropIfExists('company_user');
        Schema::dropIfExists('company_branches');
        Schema::dropIfExists('companies');
    }
};
