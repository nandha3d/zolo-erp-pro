<?php

namespace App\Services\Commercial;

use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductAlias;
use App\Models\Supplier;
use App\Services\ERP\CompanyWriteGuard;
use App\Services\Platform\CompanyContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ProductAliasService
{
    public static function normalize(string $name): string
    {
        if (class_exists(\Normalizer::class)) $name = \Normalizer::normalize($name, \Normalizer::FORM_KC);
        return mb_strtolower(trim(preg_replace('/[\p{Z}\s]+/u', ' ', $name)), 'UTF-8');
    }

    public function party(?string $type, ?int $id, CompanyContext $context): void
    {
        if (!$id) return;
        $model = match ($type) { 'customer' => Customer::class, 'supplier' => Supplier::class, default => null };
        if (!$model) throw ValidationException::withMessages(['party_type' => 'Select a valid alias scope.']);
        app(CompanyWriteGuard::class)->owned($model, $id, $context, 'party_id');
    }

    public function create(array $data, CompanyContext $context, int $actor): ProductAlias
    {
        app(CommercialPermission::class)->assert('products-edit', $context, $actor);
        return DB::transaction(function () use ($data, $context, $actor) {
            app(CompanyWriteGuard::class)->owned(Product::class, $data['product_id'], $context, 'product_id');
            $type = $data['party_type'] ?? 'company';
            $id = $type === 'company' ? 0 : (int) ($data['party_id'] ?? 0);
            if (!in_array($type, ['company', 'customer', 'supplier'], true) || ($type !== 'company' && $id < 1)) {
                throw ValidationException::withMessages(['party_id' => 'Select a company-owned party for this scope.']);
            }
            if ($type === 'company' && !empty($data['party_id'])) throw ValidationException::withMessages(['party_id' => 'Company aliases have no party.']);
            $this->party($type, $id, $context);
            $name = trim(preg_replace('/[\p{Z}\s]+/u', ' ', $data['name']));
            $normalized = self::normalize($name);
            if ($normalized === '') throw ValidationException::withMessages(['name' => 'Enter an alternate name.']);
            if (mb_strlen($name) > 150 || mb_strlen($normalized) > 191) throw ValidationException::withMessages(['name' => 'The alternate name is too long after Unicode normalization.']);
            return ProductAlias::firstOrCreate(['company_id' => $context->companyId, 'product_id' => $data['product_id'],
                'party_type' => $type, 'party_id' => $id, 'normalized_name' => $normalized], ['name' => $name, 'created_by' => $actor]);
        });
    }

    /** Only common aliases and the selected, owned party can enter a product lookup. */
    public function visible(CompanyContext $context, ?string $type = null, ?int $id = null)
    {
        $this->party($type, $id, $context);
        return ProductAlias::forCompany($context)->where(function ($query) use ($type, $id) {
            $query->where('party_type', 'company')->where('party_id', 0);
            if ($id) $query->orWhere(fn ($party) => $party->where('party_type', $type)->where('party_id', $id));
        });
    }

    public function commonLabels(CompanyContext $context): array
    {
        if (!Schema::hasTable('product_aliases')) return [];
        return $this->visible($context)->orderBy('name')->get()->groupBy('product_id')
            ->map(fn ($aliases) => $aliases->pluck('name')->implode(' / '))->all();
    }
}
