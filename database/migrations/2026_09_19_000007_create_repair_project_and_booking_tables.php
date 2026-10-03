<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // 1. Repair Device Types
        if (!Schema::hasTable('repair_device_types')) {
            Schema::create('repair_device_types', function (Blueprint $table) {
                $table->id();
                $table->string('name'); // Smartphone, Laptop, Tablet, Printer, Water Dispenser
                $table->string('description')->nullable();
                $table->string('icon')->default('dripicons-device-mobile');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // 2. Repair Service Jobs
        if (!Schema::hasTable('repair_services')) {
            Schema::create('repair_services', function (Blueprint $table) {
                $table->id();
                $table->string('job_sheet_no')->unique();
                $table->unsignedInteger('customer_id');
                $table->unsignedBigInteger('device_type_id');
                $table->string('device_brand');
                $table->string('device_model');
                $table->string('imei_serial')->nullable();
                $table->text('defects_reported');
                $table->unsignedInteger('technician_id')->nullable();
                $table->date('received_date');
                $table->date('expected_completion_date')->nullable();
                $table->double('estimated_cost', 12, 2)->default(0);
                $table->double('spare_parts_cost', 12, 2)->default(0);
                $table->double('labor_charge', 12, 2)->default(0);
                $table->double('total_charge', 12, 2)->default(0);
                $table->string('status')->default('Received'); // Received, Diagnosing, Waiting Parts, Completed, Delivered, Cancelled
                $table->text('technician_notes')->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        // 3. Project Categories
        if (!Schema::hasTable('project_categories')) {
            Schema::create('project_categories', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('description')->nullable();
                $table->timestamps();
            });
        }

        // 4. Projects
        if (!Schema::hasTable('projects')) {
            Schema::create('projects', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->unsignedBigInteger('category_id');
                $table->unsignedInteger('client_id')->nullable();
                $table->date('start_date');
                $table->date('deadline')->nullable();
                $table->double('budget', 14, 2)->default(0);
                $table->string('status')->default('Not Started'); // Not Started, In Progress, On Hold, Completed
                $table->integer('progress_percent')->default(0);
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        // 5. Project Tasks
        if (!Schema::hasTable('project_tasks')) {
            Schema::create('project_tasks', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('project_id');
                $table->string('title');
                $table->unsignedInteger('assigned_to')->nullable();
                $table->date('due_date')->nullable();
                $table->string('priority')->default('Medium'); // Low, Medium, High, Urgent
                $table->string('status')->default('Todo'); // Todo, In Progress, Review, Done
                $table->timestamps();
            });
        }

        // 6. Bookings & Appointments Calendar
        if (!Schema::hasTable('bookings')) {
            Schema::create('bookings', function (Blueprint $table) {
                $table->id();
                $table->string('booking_no')->unique();
                $table->unsignedInteger('warehouse_id');
                $table->unsignedInteger('customer_id');
                $table->date('booking_date');
                $table->string('time_slot')->default('10:00 AM');
                $table->unsignedInteger('table_id')->nullable();
                $table->string('service_type')->default('General');
                $table->text('notes')->nullable();
                $table->string('status')->default('Confirmed'); // Pending, Confirmed, Completed, Cancelled
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('project_tasks');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('project_categories');
        Schema::dropIfExists('repair_services');
        Schema::dropIfExists('repair_device_types');
    }
};
