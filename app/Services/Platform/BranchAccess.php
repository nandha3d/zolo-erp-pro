<?php

namespace App\Services\Platform;

use App\Services\ERP\CompanyWriteGuard;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** Legacy lists and reports may aggregate authorized branches; writes keep their selected-branch policy. */
class BranchAccess
{
    private const WAREHOUSE_COLUMNS = [
        'sales' => ['warehouse_id'], 'purchases' => ['warehouse_id'],
        'returns' => ['warehouse_id'], 'return_purchases' => ['warehouse_id'],
        'adjustments' => ['warehouse_id'], 'stock_counts' => ['warehouse_id'],
        'product_warehouse' => ['warehouse_id'], 'cash_registers' => ['warehouse_id'],
        'quotations' => ['warehouse_id'], 'expenses' => ['warehouse_id'], 'incomes' => ['warehouse_id'],
        'damage_stocks' => ['warehouse_id'], 'exchanges' => ['warehouse_id'], 'employees' => ['warehouse_id'],
        'productions' => ['warehouse_id'], 'transfers' => ['from_warehouse_id', 'to_warehouse_id'],
    ];

    private const PARENTS = [
        'product_sales' => ['sales', 'sale_id'],
        'product_purchases' => ['purchases', 'purchase_id'],
        'product_returns' => ['returns', 'return_id'],
        'purchase_product_return' => ['return_purchases', 'return_id'],
        'product_transfer' => ['transfers', 'transfer_id'],
        'product_adjustments' => ['adjustments', 'adjustment_id'],
        'deliveries' => ['sales', 'sale_id'], 'packing_slips' => ['sales', 'sale_id'],
        'packing_slip_products' => ['packing_slips', 'packing_slip_id'],
    ];

    private const PAYMENT_PARENTS = ['sales' => 'sale_id', 'purchases' => 'purchase_id', 'cash_registers' => 'cash_register_id'];

    public function authorizedBranchIds(CompanyContext $context, ?int $actorId = null): array
    {
        $actorId ??= auth()->id();
        if (!$actorId) {
            return [];
        }
        return DB::table('company_user_branches as access')
            ->join('company_branches as branch', 'branch.id', '=', 'access.branch_id')
            ->join('company_user as member', function ($join) {
                $join->on('member.company_id', '=', 'access.company_id')->on('member.user_id', '=', 'access.user_id');
            })
            ->where('access.company_id', $context->companyId)->where('access.user_id', $actorId)
            ->where('branch.company_id', $context->companyId)->where('branch.is_active', true)
            ->pluck('branch.id')->map(fn ($id) => (int) $id)->all();
    }

    public function assertWarehouse(int $warehouseId, CompanyContext $context, ?int $actorId = null, bool $selectedOnly = false): void
    {
        $actorId ??= auth()->id();
        app(CompanyContextResolver::class)->forActor($context, $actorId);
        app(CompanyWriteGuard::class)->warehouse($warehouseId, $context, $actorId, $selectedOnly);
    }

    public function scope(Builder $query, string $table, string $alias, CompanyContext $context): void
    {
        $schema = $query->getConnection()->getSchemaBuilder();
        if ($table === 'warehouses' && $schema->hasColumn($table, 'branch_id')) {
            $query->whereIntegerInRaw($alias.'.branch_id', $this->authorizedBranchIds($context));
        }
        foreach (self::WAREHOUSE_COLUMNS[$table] ?? [] as $column) {
            if (!$schema->hasColumn($table, $column)) {
                continue;
            }
            $query->where(function ($nested) use ($query, $column, $alias, $context, $table) {
                if (in_array($table, ['expenses', 'incomes', 'employees'], true)) {
                    // Expenses without a warehouse are company expenses, not assigned to an invented branch.
                    $nested->whereNull($alias.'.'.$column)->orWhereIn($alias.'.'.$column, $this->warehouses($query, $context));
                } else {
                    $nested->whereIn($alias.'.'.$column, $this->warehouses($query, $context));
                }
            });
        }
        if (isset(self::PARENTS[$table])) {
            [$parent, $column] = self::PARENTS[$table];
            if ($schema->hasColumn($table, $column)) {
                $parents = $this->plain($query)->from($parent)->select($parent.'.id');
                if ($schema->hasColumn($parent, 'company_id')) {
                    $parents->whereRaw($parents->getGrammar()->wrap($parent.'.company_id').' = '.(int) $context->companyId);
                }
                $this->scope($parents, $parent, $parent, $context);
                $query->whereIn($alias.'.'.$column, $parents);
            }
        }
        if ($table === 'payments') {
            foreach (self::PAYMENT_PARENTS as $parent => $column) {
                if (!$schema->hasColumn($table, $column) || !$schema->hasTable($parent)) continue;
                $query->where(function ($nested) use ($query, $context, $alias, $schema, $parent, $column) {
                    $parents = $this->plain($query)->from($parent)->select($parent.'.id');
                    if ($schema->hasColumn($parent, 'company_id')) {
                        $parents->whereRaw($parents->getGrammar()->wrap($parent.'.company_id').' = '.(int) $context->companyId);
                    }
                    $this->scope($parents, $parent, $parent, $context);
                    // Legacy zero is an unassigned reference, as in Payment::visibleIn.
                    $nested->whereNull($alias.'.'.$column)
                        ->orWhereRaw($nested->getGrammar()->wrap($alias.'.'.$column).' = 0')
                        ->orWhereIn($alias.'.'.$column, $parents);
                });
            }
        }
    }

    public function validateReferences(string $table, array $attributes, CompanyContext $context): void
    {
        if ($table === 'payments') {
            foreach (self::PAYMENT_PARENTS as $parent => $column) {
                if (!empty($attributes[$column]) && !DB::table($parent)->where('id', $attributes[$column])->exists()) {
                    throw new \Illuminate\Auth\Access\AuthorizationException('The payment source is outside the authorized branches.');
                }
            }
        }
        foreach (self::WAREHOUSE_COLUMNS[$table] ?? [] as $column) {
            if (isset($attributes[$column])) {
                $this->assertWarehouse((int) $attributes[$column], $context, selectedOnly: !in_array($table, ['transfers', 'product_warehouse'], true));
            }
        }
        if (isset(self::PARENTS[$table])) {
            [$parent, $column] = self::PARENTS[$table];
            if (isset($attributes[$column])) {
                $query = DB::table($parent)->where('id', $attributes[$column]);
                $this->scope($query, $parent, $parent, $context);
                if (!$query->exists()) {
                    throw new \Illuminate\Auth\Access\AuthorizationException('The parent record is outside the authorized branches.');
                }
            }
        }
        if ($table === 'warehouses' && isset($attributes['branch_id'])) {
            app(CompanyContextResolver::class)->resolve(auth()->id(), $context->companyId, (int) $attributes['branch_id'], $context->financialYearId);
        }
    }

    private function warehouses(Builder $query, CompanyContext $context): Builder
    {
        return $this->plain($query)->from('warehouses')->select('warehouses.id')
            ->whereRaw($query->getGrammar()->wrap('warehouses.company_id').' = '.(int) $context->companyId)
            ->whereIntegerInRaw('warehouses.branch_id', $this->authorizedBranchIds($context));
    }

    private function plain(Builder $query): Builder
    {
        return new Builder($query->getConnection(), $query->getGrammar(), $query->getProcessor());
    }
}
