<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('collection_records')) {
            Schema::create('collection_records', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('branch_id');
                $table->unsignedBigInteger('open_item_id');
                $table->string('kind', 20);
                $table->string('status', 20);
                $table->date('action_date');
                $table->string('notes', 2000);
                $table->decimal('amount', 18, 4)->nullable();
                $table->string('cheque_no', 100)->nullable();
                $table->string('bank_name', 150)->nullable();
                $table->unsignedBigInteger('receipt_entry_id')->nullable();
                $table->unsignedInteger('version')->default(1);
                $table->unsignedInteger('created_by');
                $table->timestamps();
                $table->index(['company_id', 'branch_id', 'action_date'], 'collection_branch_date');
                $table->foreign('open_item_id')->references('id')->on('account_open_items');
                $table->foreign('receipt_entry_id')->references('id')->on('journal_entries');
            });
        }
        if (!Schema::hasTable('collection_events')) {
            Schema::create('collection_events', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('collection_record_id');
                $table->unsignedInteger('version');
                $table->unsignedInteger('actor_id');
                $table->string('idempotency_key', 100);
                $table->char('payload_hash', 64);
                $table->text('snapshot');
                $table->timestamp('created_at');
                $table->unique(['company_id', 'idempotency_key'], 'collection_event_key');
                $table->unique(['collection_record_id', 'version'], 'collection_event_version');
                $table->foreign('collection_record_id')->references('id')->on('collection_records');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('collection_records') && DB::table('collection_records')->exists()) {
            throw new RuntimeException('Collection audit history exists; restore a reviewed backup instead of dropping it.');
        }
        Schema::dropIfExists('collection_events');
        Schema::dropIfExists('collection_records');
    }
};
