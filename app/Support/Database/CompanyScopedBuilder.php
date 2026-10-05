<?php

namespace App\Support\Database;

use App\Services\Platform\CompanyContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Query\JoinClause;

/**
 * While an authorized request carries a CompanyContext, selects, updates and deletes against company-owned
 * tables get "alias.company_id = <company>" (joined tables get it in their ON clause). Without a context
 * (installer, console, tests building fixtures) nothing changes. The company id is an integer from the
 * trusted context and is written as a literal so the binding order of existing clauses is untouched.
 * Query-builder subqueries are scoped before SQL compilation, including derived tables and EXISTS.
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
        'variants', 'product_variants', 'discounts', 'discount_plans', 'discount_plan_customers', 'taxes',
        'discount_plan_discounts', 'departments', 'designations', 'shifts', 'leave_types', 'employees',
        'attendances', 'overtimes', 'leaves', 'employee_transactions', 'bom_lines', 'production_outputs',
        'job_work_dispatch_lines', 'job_work_receipt_lines',
    ];

    private bool $companyScoped = false;
    private bool $conditionOnly = false;

    /** For checks that must see every company's rows, e.g. refusing foreign or duplicate stock projections. */
    public function withoutCompanyScope(): static
    {
        $this->companyScoped = true;

        return $this;
    }

    public function runSelect()
    {
        if ($this->needsScope()) return $this->scopedCopy()->runSelect();
        $this->scopeToRequestCompany();

        return parent::runSelect();
    }

    public function toSql()
    {
        if ($this->needsScope()) return $this->scopedCopy()->toSql();
        $this->scopeToRequestCompany();

        return parent::toSql();
    }

    public function getBindings()
    {
        if ($this->needsScope()) return $this->scopedCopy()->getBindings();
        // EXISTS captures bindings when attached to its parent, before the parent compiles SQL.
        $this->scopeToRequestCompany();

        return parent::getBindings();
    }

    public function forNestedWhere()
    {
        $nested = parent::forNestedWhere();
        $nested->conditionOnly = true;

        return $nested;
    }

    public function insert(array $values)
    {
        return parent::insert($this->ownedValues($values));
    }

    public function insertOrIgnore(array $values)
    {
        return parent::insertOrIgnore($this->ownedValues($values));
    }

    public function insertGetId(array $values, $sequence = null)
    {
        return parent::insertGetId($this->ownedValues([$values])[0], $sequence);
    }

    public function upsert(array $values, $uniqueBy, $update = null)
    {
        return parent::upsert($this->ownedValues($values), $uniqueBy, $update);
    }

    public function exists()
    {
        if ($this->needsScope()) return $this->scopedCopy()->exists();
        $this->scopeToRequestCompany();

        return parent::exists();
    }

    public function update(array $values)
    {
        if ($this->needsScope()) return $this->scopedCopy()->update($values);
        $this->scopeToRequestCompany();
        $values = $this->ownedValues([$values], false)[0];

        return parent::update($values);
    }

    public function delete($id = null)
    {
        if ($this->needsScope()) return $this->scopedCopy()->delete($id);
        $this->scopeToRequestCompany();

        return parent::delete($id);
    }

    private function needsScope(): bool
    {
        return !$this->conditionOnly && !$this->companyScoped && app()->bound('request')
            && request()->attributes->get(CompanyContext::class) instanceof CompanyContext;
    }

    private function scopedCopy(): static
    {
        $copy = clone $this;
        $copy->scopeToRequestCompany();
        return $copy;
    }

    public function __clone()
    {
        foreach ($this->wheres ?? [] as $index => $where) {
            if (($where['query'] ?? null) instanceof Builder) $this->wheres[$index]['query'] = clone $where['query'];
        }
        foreach ($this->joins ?? [] as $index => $join) $this->joins[$index] = clone $join;
        foreach ($this->unions ?? [] as $index => $union) $this->unions[$index]['query'] = clone $union['query'];
    }

    private function scopeToRequestCompany(): void
    {
        if ($this->conditionOnly) {
            $this->scopeSubqueries();
            return;
        }
        if ($this->companyScoped) {
            return;
        }
        $context = app()->bound('request') ? request()->attributes->get(CompanyContext::class) : null;
        if (!$context instanceof CompanyContext) {
            return;
        }
        $this->companyScoped = true;
        $company = (int) $context->companyId;
        $this->groupDisjunction($this);
        if ([$table, $alias] = $this->parse($this->from)) {
            if ($this->owned($table)) {
                $this->whereRaw($this->grammar->wrap($alias.'.company_id').' = '.$company);
            }
            app(\App\Services\Platform\BranchAccess::class)->scope($this, $table, $alias, $context);
        }
        $this->scopeSubqueries();
        foreach ($this->joins ?? [] as $join) {
            /** @var JoinClause $join */
            if ([$table, $alias] = $this->parse($join->table)) {
                $this->groupDisjunction($join);
                if ($this->owned($table)) {
                    $join->whereRaw($this->grammar->wrap($alias.'.company_id').' = '.$company);
                }
                app(\App\Services\Platform\BranchAccess::class)->scope($join, $table, $alias, $context);
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
        return $this->connection->getSchemaBuilder()->hasColumn($table, 'company_id');
    }

    private function scopeSubqueries(?Builder $builder = null): void
    {
        $builder ??= $this;
        foreach ($builder->wheres ?? [] as $where) {
            if (($where['query'] ?? null) instanceof Builder) {
                if ($where['type'] === 'Nested') {
                    $this->scopeSubqueries($where['query']);
                } elseif ($where['query'] instanceof self) {
                    $where['query']->scopeToRequestCompany();
                }
            }
        }
        foreach ($builder->unions ?? [] as $union) {
            if ($union['query'] instanceof self) {
                $union['query']->scopeToRequestCompany();
            }
        }
    }

    private function groupDisjunction(Builder $builder): void
    {
        if (!collect($builder->wheres ?? [])->contains(fn ($where) => ($where['boolean'] ?? 'and') === 'or')) {
            return;
        }
        $nested = clone $builder;
        $builder->wheres = [];
        $builder->setBindings([], 'where');
        $builder->addNestedWhereQuery($nested);
    }

    private function ownedValues(array $values, bool $stamp = true): array
    {
        if ($values === [] || !$context = (app()->bound('request') ? request()->attributes->get(CompanyContext::class) : null)) {
            return $values;
        }
        $source = $this->parse($this->from);
        if (!$source) {
            return $values;
        }
        [$table] = $source;
        $multiple = is_array(reset($values));
        $rows = $multiple ? $values : [$values];
        $owned = $this->owned($table);
        foreach ($rows as &$row) {
            if ($owned) {
                if (array_key_exists('company_id', $row) && (!$stamp || $row['company_id'] !== null) && (int) $row['company_id'] !== $context->companyId) {
                    throw new \Illuminate\Auth\Access\AuthorizationException('The record belongs to another company.');
                }
                if ($stamp) {
                    $row['company_id'] = $context->companyId;
                }
            }
            app(\App\Services\Platform\BranchAccess::class)->validateReferences($table, $row, $context);
        }
        unset($row);
        return $multiple ? $rows : $rows[0];
    }
}
