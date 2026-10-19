<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('product_aliases')) return;
        Schema::create('product_aliases', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedInteger('product_id');
            $table->string('party_type', 12)->default('company');
            $table->unsignedInteger('party_id')->default(0);
            $table->string('name', 150);
            $table->string('normalized_name', 191);
            $table->unsignedInteger('created_by');
            $table->timestamps();
            $table->unique(['company_id', 'party_type', 'party_id', 'product_id', 'normalized_name'], 'product_alias_scope_unique');
            $table->index(['company_id', 'normalized_name'], 'product_alias_lookup');
        });
    }

    public function down(): void { Schema::dropIfExists('product_aliases'); }
};
