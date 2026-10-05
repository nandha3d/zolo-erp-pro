<?php

use App\Support\MigrationConstraints;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Only audited company-owned masters and children. Users and shared platform references remain global.
    private const REFERENCES = [
        'variants' => [], 'discounts' => [], 'discount_plans' => [], 'taxes' => [],
        'departments' => [], 'designations' => [], 'shifts' => [], 'leave_types' => [],
        'employees' => ['department_id' => 'departments', 'designation_id' => 'designations', 'shift_id' => 'shifts',
            'warehouse_id' => 'warehouses', 'biller_id' => 'billers'],
        'product_variants' => ['product_id' => 'products', 'variant_id' => 'variants'],
        'discount_plan_customers' => ['discount_plan_id' => 'discount_plans', 'customer_id' => 'customers'],
        'discount_plan_discounts' => ['discount_plan_id' => 'discount_plans', 'discount_id' => 'discounts'],
        'attendances' => ['employee_id' => 'employees'], 'overtimes' => ['employee_id' => 'employees'],
        'leaves' => ['employee_id' => 'employees', 'leave_types' => 'leave_types'],
        'employee_transactions' => ['employee_id' => 'employees'],
        'payrolls' => ['employee_id' => 'employees', 'account_id' => 'accounts'],
        'bom_lines' => ['bom_id' => 'boms', 'component_product_id' => 'products', 'uom_id' => 'units', 'variant_id' => 'variants'],
        'production_outputs' => ['production_order_id' => 'production_orders', 'product_id' => 'products', 'uom_id' => 'units'],
        'job_work_dispatch_lines' => ['dispatch_id' => 'job_work_dispatches', 'product_id' => 'products'],
        'job_work_receipt_lines' => ['receipt_id' => 'job_work_receipts', 'dispatch_line_id' => 'job_work_dispatch_lines'],
    ];

    private const UNIQUE = [
        'variants' => ['name'], 'discounts' => ['name'], 'discount_plans' => ['name'],
        'departments' => ['name'], 'designations' => ['name'], 'shifts' => ['name'], 'leave_types' => ['name'],
        'product_variants' => ['product_id', 'variant_id'],
        'discount_plan_customers' => ['discount_plan_id', 'customer_id'],
        'discount_plan_discounts' => ['discount_plan_id', 'discount_id'],
    ];

    public function up(): void
    {
        if (!Schema::hasTable('companies')) throw new RuntimeException('Company foundation must be installed before ownership hardening.');
        $tables = array_filter(self::REFERENCES, fn ($refs, $table) => Schema::hasTable($table), ARRAY_FILTER_USE_BOTH);
        [$rows, $ownership] = $this->reviewOwnership($tables);
        $foundationReady = DB::table('companies')->exists();
        $this->preflightArtifacts($tables);
        // Every retained row and artifact is reviewed before MySQL's independently committed DDL.
        foreach ($tables as $table => $references) {
            if (Schema::hasColumn($table, 'company_id')) {
                $column = collect(Schema::getColumns($table))->firstWhere('name', 'company_id');
                if (!in_array(strtolower($column['type']), ['integer', 'bigint unsigned'], true)) {
                    throw new RuntimeException("Incompatible {$table}.company_id; no ownership DDL was applied.");
                }
            }
            foreach (self::UNIQUE[$table] ?? [] as $field) MigrationConstraints::requireColumns($table, [$field]);
            $seen = [];
            foreach ($rows[$table] as $row) {
                if (!isset(self::UNIQUE[$table])) continue;
                $key = json_encode([$ownership[$table.':'.$row->id], ...array_map(fn ($field) => $row->$field, self::UNIQUE[$table])]);
                if (isset($seen[$key])) throw new RuntimeException("Duplicate company-owned {$table} rows {$seen[$key]} and {$row->id}; review duplicates before migration. No ownership DDL was applied.");
                $seen[$key] = $row->id;
            }
        }
        foreach ($tables as $table => $references) {
            if (!Schema::hasColumn($table, 'company_id')) Schema::table($table, fn (Blueprint $t) => $t->unsignedBigInteger('company_id')->nullable());
            if ($table === 'employees') foreach (['warehouse_id', 'biller_id'] as $field) {
                if (!Schema::hasColumn($table, $field)) Schema::table($table, fn (Blueprint $t) => $t->unsignedInteger($field)->nullable());
            }
            foreach ($rows[$table] as $row) DB::table($table)->where('id', $row->id)->whereNull('company_id')->update(['company_id' => $ownership[$table.':'.$row->id]]);
            // Fresh installers seed legacy rows before the reviewed DEFAULT-company backfill.
            // That command resumes this migration after assigning the core parents.
            if (!$foundationReady) continue;
            if ($table === 'payrolls' && Schema::hasColumn($table, 'account_id')) {
                $account = collect(Schema::getColumns($table))->firstWhere('name', 'account_id');
                if (!$account['nullable']) Schema::table($table, fn (Blueprint $t) => $t->integer('account_id')->nullable()->change());
                DB::table($table)->where('account_id', 0)->update(['account_id' => null]);
            }
            $column = collect(Schema::getColumns($table))->firstWhere('name', 'company_id');
            if ($column['nullable']) Schema::table($table, fn (Blueprint $t) => $t->unsignedBigInteger('company_id')->nullable(false)->change());
            $this->foreign($table, $table.'_owner_fk', ['company_id'], 'companies');
            MigrationConstraints::unique($table, $table.'_owner_id_unique', ['company_id', 'id']);
            if (isset(self::UNIQUE[$table])) MigrationConstraints::unique($table, $table.'_owner_key_unique', ['company_id', ...self::UNIQUE[$table]]);
            if (isset(self::UNIQUE[$table])) foreach (Schema::getIndexes($table) as $index) {
                if ($index['unique'] && !$index['primary'] && $index['columns'] === self::UNIQUE[$table]) {
                    Schema::table($table, fn (Blueprint $t) => $t->dropUnique($index['name']));
                }
            }
        }
        if (!$foundationReady) return;
        foreach ($tables as $table => $references) foreach ($references as $field => $parent) {
            if (!Schema::hasColumn($table, $field) || !Schema::hasTable($parent)) continue;
            MigrationConstraints::unique($parent, $parent.'_owner_id_unique', ['company_id', 'id']);
            // Historical integer references are signed even when their parent IDs are unsigned.
            if (DB::getDriverName() !== 'sqlite') {
                $column = collect(Schema::getColumns($table))->firstWhere('name', $field);
                $parentId = collect(Schema::getColumns($parent))->firstWhere('name', 'id');
                if ($column['type'] !== $parentId['type']) {
                    $method = str_contains($parentId['type'], 'bigint') ? 'unsignedBigInteger' : 'unsignedInteger';
                    Schema::table($table, fn (Blueprint $t) => $t->$method($field)->nullable($column['nullable'])->change());
                }
            }
            $this->foreign($table, $table.'_'.$field.'_owner_fk', ['company_id', $field], $parent, ['company_id', 'id']);
        }
    }

    private function preflightArtifacts(array $tables): void
    {
        $indexes = []; $foreigns = [];
        foreach ($tables as $table => $references) {
            $indexes[$table][$table.'_owner_id_unique'] = ['company_id', 'id'];
            if (isset(self::UNIQUE[$table])) $indexes[$table][$table.'_owner_key_unique'] = ['company_id', ...self::UNIQUE[$table]];
            $foreigns[$table][$table.'_owner_fk'] = [['company_id'], 'companies', ['id']];
            foreach ($references as $field => $parent) {
                if (!Schema::hasTable($parent)) continue;
                if (!isset($tables[$parent])) MigrationConstraints::requireColumns($parent, ['id', 'company_id']);
                $indexes[$parent][$parent.'_owner_id_unique'] = ['company_id', 'id'];
                $foreigns[$table][$table.'_'.$field.'_owner_fk'] = [['company_id', $field], $parent, ['company_id', 'id']];
            }
        }
        foreach ($indexes as $table => $expected) foreach (Schema::getIndexes($table) as $index) {
            if (isset($expected[$index['name']]) && ($index['columns'] !== $expected[$index['name']] || !$index['unique'])) {
                throw new RuntimeException("Incompatible ownership index {$table}.{$index['name']}; no ownership DDL was applied.");
            }
        }
        foreach ($foreigns as $table => $expected) foreach (Schema::getForeignKeys($table) as $key) {
            $definition = $expected[$key['name'] ?? ''] ?? null;
            if ($definition && ([$key['columns'], $key['foreign_table'], $key['foreign_columns']] !== $definition
                || !in_array(strtolower($key['on_delete']), ['restrict', 'no action'], true))) {
                throw new RuntimeException("Incompatible ownership foreign key {$table}.{$key['name']}; no ownership DDL was applied.");
            }
        }
    }

    private function foreign(string $table, string $name, array $columns, string $parent, array $references = ['id']): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            MigrationConstraints::foreign($table, $name, $columns, $parent, $references);
            return;
        }
        $existing = collect(Schema::getForeignKeys($table))->first(fn ($key) => $key['columns'] === $columns && $key['foreign_table'] === $parent);
        if ($existing) {
            if ($existing['foreign_columns'] !== $references || !in_array(strtolower($existing['on_delete']), ['restrict', 'no action'], true)) {
                throw new RuntimeException("Unexpected existing foreign key {$table}.{$name}.");
            }
            return;
        }
        // Laravel 10 intentionally ignores ALTER ADD FOREIGN on SQLite. DBAL rebuilds
        // the table while preserving columns, data, indexes and prior constraints.
        $connection = DB::connection();
        $manager = $connection->getDoctrineSchemaManager();
        $before = $manager->introspectTable($table); $after = clone $before;
        $after->addForeignKeyConstraint($parent, $columns, $references, ['onDelete' => 'RESTRICT'], $name);
        $platform = $connection->getDoctrineConnection()->getDatabasePlatform();
        $sql = $platform->getAlterTableSQL($manager->createComparator()->compareTables($before, $after));
        Schema::disableForeignKeyConstraints();
        try {
            DB::transaction(function () use ($sql) { foreach ($sql as $statement) DB::statement($statement); });
        } finally {
            Schema::enableForeignKeyConstraints();
        }
        if (DB::select('PRAGMA foreign_key_check')) throw new RuntimeException("Foreign-key verification failed after rebuilding {$table}.");
    }

    /** Ownership flows through reviewed references in either direction; conflicting anchors always fail. */
    private function reviewOwnership(array $tables): array
    {
        $rows = []; $anchors = []; $edges = []; $ownership = [];
        $companies = DB::table('companies')->pluck('id')->map(fn ($id) => (int) $id)->all();
        foreach ($tables as $table => $references) {
            $rows[$table] = DB::table($table)->get()->keyBy('id');
            foreach ($rows[$table] as $row) {
                $node = $table.':'.$row->id;
                $edges[$node] ??= [];
                if (!empty($row->company_id)) {
                    if (!in_array((int) $row->company_id, $companies, true)) throw new RuntimeException("Unknown company for {$node}; review retained ownership before migration.");
                    $anchors[$node] = (int) $row->company_id;
                }
            }
        }
        foreach ($tables as $table => $references) foreach ($rows[$table] as $row) {
            $node = $table.':'.$row->id;
            foreach ($references as $field => $parent) {
                if (!isset($row->$field) || $row->$field === '') continue;
                if ($table === 'payrolls' && $field === 'account_id' && (int) $row->$field === 0) continue;
                $this->connect($node, $parent, $row->$field, $rows, $edges, $anchors, $tables);
            }
            if ($table === 'discounts' && trim((string) ($row->product_list ?? '')) !== '') {
                foreach (explode(',', $row->product_list) as $id) $this->connect($node, 'products', trim($id), $rows, $edges, $anchors, $tables);
            }
            if ($table === 'employees' && !empty($row->user_id) && Schema::hasTable('company_user')) {
                $memberships = DB::table('company_user')->where('user_id', $row->user_id)->pluck('company_id')->unique()->all();
                if (count($memberships) === 1) {
                    $memberNode = 'employee_user:'.$row->id;
                    $anchors[$memberNode] = (int) $memberships[0];
                    $edges[$node][] = $memberNode; $edges[$memberNode][] = $node;
                }
            }
        }
        foreach (array_keys($edges) as $start) {
            if (isset($ownership[$start])) continue;
            $pending = [$start]; $component = []; $found = [];
            while ($pending) {
                $node = array_pop($pending);
                if (isset($component[$node])) continue;
                $component[$node] = true;
                if (isset($anchors[$node])) $found[$anchors[$node]] = true;
                foreach ($edges[$node] ?? [] as $next) $pending[] = $next;
            }
            if (count($found) > 1) throw new RuntimeException("Conflicting companies for {$start}; review connected legacy parents before migration. No ownership DDL was applied.");
            $company = array_key_first($found) ?? (count($companies) === 1 ? $companies[0] : null);
            if (!$company) throw new RuntimeException("Ambiguous company for {$start}; supply reviewed ownership before migration. No ownership DDL was applied.");
            foreach ($component as $node => $_) $ownership[$node] = $company;
        }
        return [$rows, $ownership];
    }

    private function connect(string $node, string $parent, mixed $id, array $rows, array &$edges, array &$anchors, array $tables): void
    {
        if (filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false || !Schema::hasTable($parent)) {
            throw new RuntimeException("Invalid {$parent} reference for {$node}; review retained parents before migration. No ownership DDL was applied.");
        }
        $record = isset($tables[$parent]) ? ($rows[$parent][(int) $id] ?? null) : DB::table($parent)->find($id);
        if (!$record) throw new RuntimeException("Missing {$parent}:{$id} referenced by {$node}; review retained parents before migration. No ownership DDL was applied.");
        $target = $parent.':'.$id;
        if (!isset($tables[$parent])) {
            if (empty($record->company_id)) throw new RuntimeException("Unreviewed company for {$target} referenced by {$node}; run the reviewed foundation backfill first. No ownership DDL was applied.");
            $anchors[$target] = (int) $record->company_id;
            if (!DB::table('companies')->where('id', $record->company_id)->exists()) {
                throw new RuntimeException("Unknown company for {$target}; review retained ownership before migration. No ownership DDL was applied.");
            }
        }
        $edges[$node][] = $target; $edges[$target][] = $node;
    }

    public function down(): void
    {
        throw new RuntimeException('Company ownership protects retained business history; use a reviewed data-preserving forward rollback.');
    }
};
