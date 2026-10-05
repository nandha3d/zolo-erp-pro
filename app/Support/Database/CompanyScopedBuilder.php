<?php

namespace App\Support\Database;

use App\Services\Platform\CompanyContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\Schema;

/**
 * While an authorized request carries a CompanyContext, selects, updates and deletes against company-owned
 * tables get "alias.company_id = <company>" (joined tables get it in their ON clause). Without a context
 * (installer, console, tests building fixtures) nothing changes. The company id is an integer from the
 * trusted context and is written as a literal so the binding order of existing clauses is untouched.
 * Subqueries compiled into a parent query are not rewritten; the parent's tables are.
 */
class CompanyScopedBuilder extends Builder
{
    /** Company-owned tables whose rows carry company_id. Accounting tables scope themselves explicitly. */
    public const TABLES = [
        'categories', 'brands', 'units', 'customer_groups', 'customers', 'suppliers', 'billers', 'warehouses',
        'products', 'sales', 'product_sales', 'purchases', 'product_purchases', 'payments', 'product_warehouse',
        'transfers', 'product_transfer', 'returns', 'product_returns', 'return_purchases', 'purchase_product_return',
        'adjustments', 'product_adjustments', 'stock_counts', 'expenses', 'accounts', 'quotations', 'incomes',
        'money_transfers', 'payrolls', 'deliveries', 'damage_stocks', 'exchanges',
    ];

    private static array $hasColumn = [];

    private bool $companyScoped = false;

    /** For checks that must see every company's rows, e.g. refusing foreign or duplicate stock projections. */
    public function withoutCompanyScope(): static
    {
        $this->companyScoped = true;

        return $this;
    }

    public function runSelect()
    {
        $this->scopeToRequestCompany();

        return parent::runSelect();
    }

    public function exists()
    {
        $this->scopeToRequestCompany();

        return parent::exists();
    }

    public function update(array $values)
    {
        $this->scopeToRequestCompany(false);

        return parent::update($values);
    }

    public function delete($id = null)
    {
        $this->scopeToRequestCompany(false);

        return parent::delete($id);
    }

    private function scopeToRequestCompany(bool $joins = true): void
    {
        if ($this->companyScoped) {
            return;
        }
        $context = app()->bound('request') ? request()->attributes->get(CompanyContext::class) : null;
        if (!$context instanceof CompanyContext) {
            return;
        }
        $this->companyScoped = true;
        $company = (int) $context->companyId;
        if ([$table, $alias] = $this->parse($this->from)) {
            if ($this->owned($table) && !$this->alreadyScoped($this->wheres)) {
                $this->whereRaw("`{$alias}`.`company_id` = {$company}");
            }
        }
        if (!$joins) {
            return;
        }
        foreach ($this->joins ?? [] as $join) {
            /** @var JoinClause $join */
            if ([$table, $alias] = $this->parse($join->table)) {
                if ($this->owned($table) && !$this->alreadyScoped($join->wheres)) {
                    $join->whereRaw("`{$alias}`.`company_id` = {$company}");
                }
            }
        }
    }

    /** @return array{0: string, 1: string}|null table and alias of a plain "table [as alias]" source */
    private function parse(mixed $source): ?array
    {
        if ($source instanceof Expression || !is_string($source)) {
            return null;
        }
        if (!preg_match('/^`?([A-Za-z0-9_]+)`?(?:\s+as\s+`?([A-Za-z0-9_]+)`?)?$/i', trim($source), $match)) {
            return null;
        }

        return [$match[1], $match[2] ?? $match[1]];
    }

    private function owned(string $table): bool
    {
        if (!in_array($table, self::TABLES, true)) {
            return false;
        }
        $key = $this->connection->getName().'.'.$table;

        return self::$hasColumn[$key] ??= Schema::connection($this->connection->getName())->hasColumn($table, 'company_id');
    }

    private function alreadyScoped(?array $wheres): bool
    {
        foreach ($wheres ?? [] as $where) {
            $column = $where['column'] ?? ($where['sql'] ?? '');
            if (is_string($column) && str_contains($column, 'company_id')) {
                return true;
            }
        }

        return false;
    }
}
