<?php

namespace App\Services\ERP;

use App\Models\Company;
use App\Models\Product;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Services\Platform\CompanyContext;
use App\Services\Platform\CompanyContextResolver;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Shared ownership checks for the commercial engine; never accepts ownership from payloads. */
class CompanyWriteGuard
{
    public function context(?CompanyContext $context, ?int $actor): CompanyContext
    {
        return app(CompanyContextResolver::class)->forActor($context, $actor);
    }

    /** The company lock serializes reviewed stock/balance writers and account setup. */
    public function begin(CompanyContext $context, ?string $businessDate): string
    {
        $company = Company::whereKey($context->companyId)->lockForUpdate()->firstOrFail();
        $date = $businessDate ?? CarbonImmutable::now($company->timezone)->toDateString();
        app(CompanyContextResolver::class)->assertPostingDate($context, $date);
        return $date;
    }

    public function owned(string $model, mixed $id, CompanyContext $context, string $field): Model
    {
        if (filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            throw ValidationException::withMessages([$field => 'Select a company-owned record.']);
        }
        $record = $model::query()->where('company_id', $context->companyId)->whereKey($id)->lockForUpdate()->first();
        if (!$record) {
            throw ValidationException::withMessages([$field => 'Select a company-owned record.']);
        }
        return $record;
    }

    public function warehouse(mixed $id, CompanyContext $context, int $actor, bool $selectedBranch = true): Warehouse
    {
        $warehouse = $this->owned(Warehouse::class, $id, $context, 'warehouse_id');
        if ($selectedBranch && (int) $warehouse->branch_id !== $context->branchId) {
            throw ValidationException::withMessages(['warehouse_id' => 'Warehouse is outside the selected branch.']);
        }
        app(CompanyContextResolver::class)->resolve($actor, $context->companyId, $warehouse->branch_id, $context->financialYearId);
        return $warehouse;
    }

    public function product(array &$line, CompanyContext $context, string $unitField): Product
    {
        $product = $this->owned(Product::class, $line['product_id'] ?? null, $context, 'items.product_id');
        $line[$unitField] ??= $product->unit_id ?: Unit::where('company_id', $context->companyId)->orderBy('id')->value('id');
        $this->owned(Unit::class, $line[$unitField], $context, 'items.'.$unitField);
        foreach (['product_batch_id', 'variant_id'] as $field) {
            if (empty($line[$field])) {
                $line[$field] = null;
                continue;
            }
            $query = $field === 'product_batch_id'
                ? DB::table('product_batches')->where('id', $line[$field])->where('product_id', $product->id)
                : DB::table('product_variants')->where('variant_id', $line[$field])->where('product_id', $product->id);
            if (!$query->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['items.'.$field => 'The reference must belong to the selected product.']);
            }
        }
        // Serials, batches and projection ownership are validated by InventoryMovementService.
        return $product;
    }

    public function businessDate(array $data): ?string
    {
        $date = $data['business_date'] ?? $data['payment_at'] ?? $data['created_at'] ?? null;
        foreach (['business_date', 'payment_at', 'created_at'] as $field) {
            if (isset($data[$field]) && $data[$field] !== $date) {
                throw ValidationException::withMessages(['business_date' => 'Document and posting dates must agree.']);
            }
        }
        if ($date !== null && !is_string($date)) {
            throw ValidationException::withMessages(['business_date' => 'Use a YYYY-MM-DD business date.']);
        }
        return $date;
    }

    public function paymentAccount(array $data, CompanyContext $context): int
    {
        $id = $data['account_id'] ?? \App\Models\Account::where('company_id', $context->companyId)->orderBy('id')->value('id');
        $account = $this->owned(\App\Models\Account::class, $id, $context, 'account_id');
        if (array_key_exists('is_active', $account->getAttributes()) && !$account->is_active) {
            throw ValidationException::withMessages(['account_id' => 'Select an active company-owned account.']);
        }
        return $account->id;
    }

    public function rejectUnscopedReferences(array $data): void
    {
        foreach (['coupon_id', 'cash_register_id', 'table_id', 'service_id', 'waiter_id', 'installment_id'] as $field) {
            if (!empty($data[$field])) {
                throw ValidationException::withMessages([$field => 'This reference requires its company-owned operational path.']);
            }
        }
    }
}
