<?php

use App\Support\MigrationConstraints;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Areas Master Table
        if (!Schema::hasTable('areas')) {
            Schema::create('areas', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
                $table->string('name', 100);
                $table->string('code', 50)->nullable();
                $table->string('city', 100)->nullable();
                $table->string('state', 100)->nullable();
                $table->string('pincode', 20)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
            MigrationConstraints::unique('areas', 'areas_company_name_unique', ['company_id', 'name']);
        }

        // 2. Agents / Brokers Master Table (Sales & Purchase "Through")
        if (!Schema::hasTable('agents')) {
            Schema::create('agents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
                $table->string('name', 150);
                $table->string('code', 50)->nullable();
                $table->string('phone', 50)->nullable();
                $table->string('email', 100)->nullable();
                $table->text('address')->nullable();
                $table->decimal('commission_rate', 8, 2)->default(0);
                $table->unsignedInteger('account_id')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
            MigrationConstraints::unique('agents', 'agents_company_name_unique', ['company_id', 'name']);
        }

        // 3. Bill Sundries Master Table (Freight, Insurance, Discounts, Round Off, etc.)
        if (!Schema::hasTable('bill_sundries')) {
            Schema::create('bill_sundries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
                $table->string('name', 150);
                $table->string('nature', 20)->default('both'); // sales, purchase, both
                $table->string('calculation_type', 20)->default('percentage'); // percentage, amount
                $table->decimal('default_value', 15, 4)->default(0);
                $table->boolean('affect_cost')->default(false); // affects item cost / sales rate
                $table->boolean('calculate_before_tax')->default(false);
                $table->decimal('tax_rate', 8, 2)->default(0);
                $table->unsignedInteger('account_id')->nullable(); // Expense / Income / Liability ledger
                $table->unsignedInteger('cgst_account_id')->nullable();
                $table->unsignedInteger('sgst_account_id')->nullable();
                $table->unsignedInteger('igst_account_id')->nullable();
                $table->unsignedInteger('cess_account_id')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
            MigrationConstraints::unique('bill_sundries', 'bill_sundries_company_name_unique', ['company_id', 'name']);
        }

        // 4. Sale Types Master Table (5%-GST, 18%-GST, 5%-IGST, L/MultiTax, SEZ, Export, etc.)
        if (!Schema::hasTable('sale_types')) {
            Schema::create('sale_types', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
                $table->string('name', 150);
                $table->string('code', 50)->nullable();
                $table->string('tax_nature', 30)->default('local'); // local, interstate, export, sez, exempted
                $table->decimal('tax_rate', 8, 2)->default(0);
                $table->unsignedInteger('sales_account_id')->nullable();
                $table->unsignedInteger('cgst_account_id')->nullable();
                $table->unsignedInteger('sgst_account_id')->nullable();
                $table->unsignedInteger('igst_account_id')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
            MigrationConstraints::unique('sale_types', 'sale_types_company_name_unique', ['company_id', 'name']);
        }

        // 5. Purchase Types Master Table (Local MultiTax, Interstate, Import, Capital Goods, Exempted)
        if (!Schema::hasTable('purchase_types')) {
            Schema::create('purchase_types', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
                $table->string('name', 150);
                $table->string('code', 50)->nullable();
                $table->string('tax_nature', 30)->default('local'); // local, interstate, import, exempted
                $table->decimal('tax_rate', 8, 2)->default(0);
                $table->unsignedInteger('purchase_account_id')->nullable();
                $table->unsignedInteger('cgst_account_id')->nullable();
                $table->unsignedInteger('sgst_account_id')->nullable();
                $table->unsignedInteger('igst_account_id')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
            MigrationConstraints::unique('purchase_types', 'purchase_types_company_name_unique', ['company_id', 'name']);
        }

        // 6. Standard Remarks Master Table (predefined terms, invoice notes)
        if (!Schema::hasTable('standard_remarks')) {
            Schema::create('standard_remarks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
                $table->string('title', 100);
                $table->string('type', 30)->default('all'); // sale, purchase, voucher, all
                $table->text('remark');
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // 7. Extend Customers with Master references
        Schema::table('customers', function (Blueprint $table) {
            if (!Schema::hasColumn('customers', 'print_name')) {
                $table->string('print_name', 150)->nullable()->after('name');
            }
            if (!Schema::hasColumn('customers', 'area_id')) {
                $table->unsignedBigInteger('area_id')->nullable()->after('postal_code');
            }
            if (!Schema::hasColumn('customers', 'agent_id')) {
                $table->unsignedBigInteger('agent_id')->nullable()->after('area_id');
            }
            if (!Schema::hasColumn('customers', 'cd_days')) {
                $table->integer('cd_days')->default(0)->after('credit_days');
            }
            if (!Schema::hasColumn('customers', 'cd_percent')) {
                $table->decimal('cd_percent', 8, 2)->default(0)->after('cd_days');
            }
            if (!Schema::hasColumn('customers', 'bill_by_bill')) {
                $table->boolean('bill_by_bill')->default(true)->after('cd_percent');
            }
            if (!Schema::hasColumn('customers', 'is_sez')) {
                $table->boolean('is_sez')->default(false)->after('tax_no');
            }
            if (!Schema::hasColumn('customers', 'contact_person')) {
                $table->string('contact_person', 100)->nullable()->after('company_name');
            }
            if (!Schema::hasColumn('customers', 'tin_no')) {
                $table->string('tin_no', 50)->nullable()->after('is_sez');
            }
        });

        // 8. Extend Suppliers with Master references
        Schema::table('suppliers', function (Blueprint $table) {
            if (!Schema::hasColumn('suppliers', 'print_name')) {
                $table->string('print_name', 150)->nullable()->after('name');
            }
            if (!Schema::hasColumn('suppliers', 'area_id')) {
                $table->unsignedBigInteger('area_id')->nullable()->after('postal_code');
            }
            if (!Schema::hasColumn('suppliers', 'agent_id')) {
                $table->unsignedBigInteger('agent_id')->nullable()->after('area_id');
            }
            if (!Schema::hasColumn('suppliers', 'credit_days')) {
                $table->integer('credit_days')->default(0)->after('opening_balance');
            }
            if (!Schema::hasColumn('suppliers', 'credit_limit')) {
                $table->decimal('credit_limit', 15, 4)->default(0)->after('credit_days');
            }
            if (!Schema::hasColumn('suppliers', 'cd_days')) {
                $table->integer('cd_days')->default(0)->after('credit_limit');
            }
            if (!Schema::hasColumn('suppliers', 'cd_percent')) {
                $table->decimal('cd_percent', 8, 2)->default(0)->after('cd_days');
            }
            if (!Schema::hasColumn('suppliers', 'bill_by_bill')) {
                $table->boolean('bill_by_bill')->default(true)->after('cd_percent');
            }
            if (!Schema::hasColumn('suppliers', 'contact_person')) {
                $table->string('contact_person', 100)->nullable()->after('company_name');
            }
            if (!Schema::hasColumn('suppliers', 'tax_no')) {
                $table->string('tax_no', 50)->nullable()->after('vat_number');
            }
            if (!Schema::hasColumn('suppliers', 'tin_no')) {
                $table->string('tin_no', 50)->nullable()->after('tax_no');
            }
        });

        // 9. Extend Products with Master references
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'print_name')) {
                $table->string('print_name', 150)->nullable()->after('name');
            }
            if (!Schema::hasColumn('products', 'neg_stock_allowed')) {
                $table->boolean('neg_stock_allowed')->default(true)->after('alert_quantity');
            }
            if (!Schema::hasColumn('products', 'sales_account_id')) {
                $table->unsignedInteger('sales_account_id')->nullable()->after('tax_category_id');
            }
            if (!Schema::hasColumn('products', 'purchase_account_id')) {
                $table->unsignedInteger('purchase_account_id')->nullable()->after('sales_account_id');
            }
        });

        // 10. Extend Sales with Master relations
        Schema::table('sales', function (Blueprint $table) {
            if (!Schema::hasColumn('sales', 'sale_type_id')) {
                $table->unsignedBigInteger('sale_type_id')->nullable()->after('sale_type');
            }
            if (!Schema::hasColumn('sales', 'agent_id')) {
                $table->unsignedBigInteger('agent_id')->nullable()->after('sale_type_id');
            }
        });

        // 11. Extend Purchases with Master relations & vendor invoice details
        Schema::table('purchases', function (Blueprint $table) {
            if (!Schema::hasColumn('purchases', 'purchase_type_id')) {
                $table->unsignedBigInteger('purchase_type_id')->nullable()->after('purchase_type');
            }
            if (!Schema::hasColumn('purchases', 'agent_id')) {
                $table->unsignedBigInteger('agent_id')->nullable()->after('purchase_type_id');
            }
            if (!Schema::hasColumn('purchases', 'supplier_invoice_no')) {
                $table->string('supplier_invoice_no', 100)->nullable()->after('reference_no');
            }
            if (!Schema::hasColumn('purchases', 'supplier_invoice_date')) {
                $table->date('supplier_invoice_date')->nullable()->after('supplier_invoice_no');
            }
            if (!Schema::hasColumn('purchases', 'update_item_cost')) {
                $table->boolean('update_item_cost')->default(false)->after('note');
            }
            if (!Schema::hasColumn('purchases', 'update_item_hsn')) {
                $table->boolean('update_item_hsn')->default(false)->after('update_item_cost');
            }
            if (!Schema::hasColumn('purchases', 'is_reverse_charge')) {
                $table->boolean('is_reverse_charge')->default(false)->after('update_item_hsn');
            }
        });
    }

    public function down(): void
    {
        // Safe reversible migration
        Schema::dropIfExists('standard_remarks');
        Schema::dropIfExists('purchase_types');
        Schema::dropIfExists('sale_types');
        Schema::dropIfExists('bill_sundries');
        Schema::dropIfExists('agents');
        Schema::dropIfExists('areas');
    }
};
