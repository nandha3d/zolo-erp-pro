<?php

namespace App\Services\Tax;

use App\Models\Accounting\ChartOfAccount;
use App\Models\Company;
use App\Models\Product;
use App\Services\Accounting\SemanticAccountResolver;
use App\Services\Platform\{CompanyContext, CompanyContextResolver};
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Validation\ValidationException;

/**
 * One owner for the GST master data: rate slabs, HSN/SAC catalog, company registration, party profiles and the
 * GST ledgers. The legacy `taxes` table stays for non-GST (VAT) companies only.
 */
class GstSetupService
{
    public const EFFECTIVE_FROM = '2017-07-01';

    /** name => [supply type, GST %, input credit allowed] */
    public const SLABS = [
        'GST 0% (Nil rated)' => ['nil_rated', 0, false],
        'GST Exempt' => ['exempt', 0, false],
        'Non-GST supply' => ['non_gst', 0, false],
        'GST 0.25%' => ['taxable', 0.25, true],
        'GST 3%' => ['taxable', 3, true],
        'GST 5%' => ['taxable', 5, true],
        'GST 12%' => ['taxable', 12, true],
        'GST 18%' => ['taxable', 18, true],
        'GST 28%' => ['taxable', 28, true],
        'GST 40%' => ['taxable', 40, true],
    ];

    /** semantic role => [code, name, type, parent code] */
    public const LEDGERS = [
        'input_tax_cgst' => ['1310', 'Input CGST', 'asset', '1000'],
        'input_tax_sgst' => ['1311', 'Input SGST', 'asset', '1000'],
        'input_tax_igst' => ['1312', 'Input IGST', 'asset', '1000'],
        'input_tax_cess' => ['1313', 'Input GST Cess', 'asset', '1000'],
        'output_tax_cgst' => ['2050', 'Output CGST', 'liability', '2000'],
        'output_tax_sgst' => ['2051', 'Output SGST', 'liability', '2000'],
        'output_tax_igst' => ['2052', 'Output IGST', 'liability', '2000'],
        'output_tax_cess' => ['2053', 'Output GST Cess', 'liability', '2000'],
        'rounding' => ['6095', 'Round Off', 'expense', '6000'],
    ];

    public static function available(): bool
    {
        return (bool) config('compliance.enabled') && Schema::hasTable('tax_categories');
    }

    /** The signed-in company bills under GST rules (India or not yet set); VAT companies keep the legacy tax list. */
    public static function usesGst(?int $companyId = null): bool
    {
        if (!self::available()) return false;
        try {
            $companyId ??= app(CompanyContextResolver::class)->forActor()->companyId;
        } catch (\Throwable) {
            return false;
        }
        return in_array(Company::whereKey($companyId)->value('country_code'), [null, '', 'IN'], true);
    }

    /** GST mode: compliance on and the branch holds an active Indian registration for the date. */
    public function enabled(CompanyContext $context, ?string $date = null): bool
    {
        return self::available() && $this->registration($context, $date) !== null
            && Company::whereKey($context->companyId)->value('country_code') === 'IN';
    }

    public function registration(CompanyContext $context, ?string $date = null): ?object
    {
        if (!Schema::hasTable('tax_registrations')) return null;
        $date ??= now()->toDateString();
        return DB::table('tax_registrations')->where('company_id', $context->companyId)->where('branch_id', $context->branchId)
            ->where('status', 'active')->where('effective_from', '<=', $date)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date))->orderByDesc('effective_from')->first();
    }

    /** Company GST registration. Same GSTIN updates the open period; a new GSTIN closes it the day before. */
    public function registerCompany(array $data, CompanyContext $context, int $actor): void
    {
        $this->authorize($context, $actor);
        validator($data, ['gstin' => 'required|string|max:15', 'legal_name' => 'required|string|max:191', 'trade_name' => 'nullable|string|max:191',
            'address' => 'required|string|max:1000', 'registration_type' => 'required|in:regular,composition',
            'effective_from' => 'required|date_format:Y-m-d'])->validate();
        $gstin = Gstin::normalize($data['gstin']);
        DB::transaction(function () use ($data, $gstin, $context) {
            Company::whereKey($context->companyId)->lockForUpdate()->firstOrFail();
            $scope = ['company_id' => $context->companyId, 'branch_id' => $context->branchId];
            $open = DB::table('tax_registrations')->where($scope)->whereNull('effective_to')->lockForUpdate()->first();
            $fields = ['legal_name' => $data['legal_name'], 'trade_name' => $data['trade_name'] ?? null, 'address' => $data['address'],
                'registration_type' => $data['registration_type'], 'status' => 'active', 'updated_at' => now()];
            if ($open && $open->gstin === $gstin) {
                DB::table('tax_registrations')->where('id', $open->id)->update($fields);
            } else {
                if ($open) {
                    if ($data['effective_from'] <= $open->effective_from) {
                        throw ValidationException::withMessages(['effective_from' => 'A new GSTIN must start after '.$open->effective_from.'.']);
                    }
                    DB::table('tax_registrations')->where('id', $open->id)
                        ->update(['effective_to' => date('Y-m-d', strtotime($data['effective_from'].' -1 day')), 'updated_at' => now()]);
                }
                DB::table('tax_registrations')->insert($scope + $fields + ['gstin' => $gstin, 'state_code' => substr($gstin, 0, 2),
                    'effective_from' => $data['effective_from'], 'created_at' => now()]);
            }
            Company::whereKey($context->companyId)->update(['country_code' => 'IN', 'state_code' => substr($gstin, 0, 2)]);
            // A registered company bills GST from its first document: make sure the GST and round-off ledgers are mapped.
            $this->ensureLedgers($context->companyId);
        });
    }

    /** Idempotent: slabs, GST ledgers, legacy product mapping and party profiles. Never changes an existing rate. */
    public function installDefaults(CompanyContext $context, int $actor): array
    {
        $this->authorize($context, $actor);
        return DB::transaction(function () use ($context) {
            Company::whereKey($context->companyId)->lockForUpdate()->firstOrFail();
            $created = 0;
            foreach (self::SLABS as $name => [$supply, $rate, $credit]) {
                if (DB::table('tax_categories')->where('company_id', $context->companyId)->where('name', $name)->exists()) continue;
                $id = DB::table('tax_categories')->insertGetId(['company_id' => $context->companyId, 'name' => $name, 'supply_type' => $supply,
                    'input_credit_allowed' => $credit, 'created_at' => now(), 'updated_at' => now()]);
                DB::table('tax_rates')->insert(['company_id' => $context->companyId, 'tax_category_id' => $id, 'rate' => $rate, 'cess_rate' => 0,
                    'effective_from' => self::EFFECTIVE_FROM, 'created_at' => now(), 'updated_at' => now()]);
                $created++;
            }
            $this->ensureLedgers($context->companyId);
            $mapped = $this->mapLegacyProducts($context->companyId);
            $parties = $this->syncAllParties($context->companyId);
            return ['categories' => $created, 'products' => $mapped, 'parties' => $parties];
        });
    }

    /** Creates the GST and round-off ledgers (or only $roles) when missing and maps any unmapped posting role to them. */
    public function ensureLedgers(int $companyId, ?array $roles = null): void
    {
        if (!Schema::hasTable('chart_of_accounts') || !Schema::hasTable('semantic_account_mappings')) return;
        foreach ($roles === null ? self::LEDGERS : array_intersect_key(self::LEDGERS, array_flip($roles)) as $role => [$code, $name, $type, $parentCode]) {
            $account = ChartOfAccount::where('company_id', $companyId)->where('sub_type', $role)->where('type', $type)->first();
            if (!$account) {
                $suffix = 0;
                while (ChartOfAccount::where('company_id', $companyId)->where('code', $suffix ? $code.'-'.$suffix : $code)->exists()) $suffix++;
                $account = (new ChartOfAccount)->forceFill(['company_id' => $companyId, 'code' => $suffix ? $code.'-'.$suffix : $code,
                    'name' => $name, 'type' => $type, 'sub_type' => $role, 'is_system' => true, 'is_active' => true,
                    'parent_id' => ChartOfAccount::where('company_id', $companyId)->where('code', $parentCode)->value('id'),
                    'description' => 'GST ledger for '.str_replace('_', ' ', $role)]);
                $account->save();
            }
            $mapping = \App\Models\Accounting\SemanticAccountMapping::where('company_id', $companyId)->where('semantic_role', $role)->first();
            if (!$mapping || !$mapping->account_id) {
                ($mapping ?? new \App\Models\Accounting\SemanticAccountMapping)->forceFill([
                    'company_id' => $companyId, 'semantic_role' => $role, 'account_id' => $account->id, 'account_code' => $account->code,
                    'account_name' => $account->name, 'category' => 'core', 'description' => 'Company posting role: '.$role, 'is_active' => true,
                ])->save();
            }
        }
    }

    /** Products still on a legacy `taxes` rate move to the matching GST slab; unmatched rates stay for review. */
    public function mapLegacyProducts(int $companyId): int
    {
        if (!Schema::hasTable('taxes')) return 0;
        $slabs = DB::table('tax_categories as c')->join('tax_rates as r', 'r.tax_category_id', '=', 'c.id')
            ->where('c.company_id', $companyId)->where('c.supply_type', 'taxable')->whereNull('r.effective_to')
            ->whereIn('c.name', array_keys(self::SLABS))->pluck('c.id', 'r.rate');
        $count = 0;
        foreach (DB::table('products')->where('company_id', $companyId)->whereNull('tax_category_id')->whereNotNull('tax_id')->get(['id', 'tax_id']) as $product) {
            $rate = DB::table('taxes')->where('id', $product->tax_id)->value('rate');
            $category = $rate === null ? null : $slabs->first(fn ($id, $slabRate) => (float) $slabRate === (float) $rate);
            if ($category && (float) $rate > 0) {
                DB::table('products')->where('id', $product->id)->update(['tax_category_id' => $category]);
                $count++;
            }
        }
        return $count;
    }

    /** New open rate period from a date; the current open period ends the day before. Posted bills keep their snapshot. */
    public function changeRate(int $categoryId, float $rate, float $cess, string $from, CompanyContext $context, int $actor): void
    {
        $this->authorize($context, $actor);
        validator(compact('rate', 'cess', 'from'), ['rate' => 'numeric|min:0|max:100', 'cess' => 'numeric|min:0|max:100',
            'from' => 'date_format:Y-m-d'])->validate();
        if ($rate + $cess > 100) throw ValidationException::withMessages(['rate' => 'Combined tax cannot exceed 100.']);
        DB::transaction(function () use ($categoryId, $rate, $cess, $from, $context) {
            Company::whereKey($context->companyId)->lockForUpdate()->firstOrFail();
            abort_unless(DB::table('tax_categories')->where('company_id', $context->companyId)->where('id', $categoryId)->exists(), 404);
            $scope = ['company_id' => $context->companyId, 'tax_category_id' => $categoryId];
            if (DB::table('tax_rates')->where($scope)->where('effective_from', '>=', $from)->exists()) {
                throw ValidationException::withMessages(['effective_from' => 'Choose a date after the latest rate period starts.']);
            }
            DB::table('tax_rates')->where($scope)->whereNull('effective_to')
                ->update(['effective_to' => date('Y-m-d', strtotime($from.' -1 day')), 'updated_at' => now()]);
            DB::table('tax_rates')->insert($scope + ['rate' => $rate, 'cess_rate' => $cess, 'effective_from' => $from,
                'created_at' => now(), 'updated_at' => now()]);
        });
    }

    public function createCategory(array $data, CompanyContext $context, int $actor): int
    {
        $this->authorize($context, $actor);
        validator($data, ['name' => 'required|string|max:191', 'supply_type' => 'required|in:taxable,exempt,nil_rated,non_gst',
            'rate' => 'required|numeric|min:0|max:100', 'cess_rate' => 'nullable|numeric|min:0|max:100',
            'input_credit_allowed' => 'sometimes|boolean', 'effective_from' => 'nullable|date_format:Y-m-d'])->validate();
        if (DB::table('tax_categories')->where('company_id', $context->companyId)->where('name', $data['name'])->exists()) {
            throw ValidationException::withMessages(['name' => 'A GST rate with this name already exists.']);
        }
        $rate = $data['supply_type'] === 'taxable' ? (float) $data['rate'] : 0.0;
        return DB::transaction(function () use ($data, $rate, $context) {
            $id = DB::table('tax_categories')->insertGetId(['company_id' => $context->companyId, 'name' => $data['name'],
                'supply_type' => $data['supply_type'], 'input_credit_allowed' => $data['supply_type'] === 'taxable' && ($data['input_credit_allowed'] ?? true),
                'created_at' => now(), 'updated_at' => now()]);
            DB::table('tax_rates')->insert(['company_id' => $context->companyId, 'tax_category_id' => $id, 'rate' => $rate,
                'cess_rate' => $data['supply_type'] === 'taxable' ? (float) ($data['cess_rate'] ?? 0) : 0,
                'effective_from' => $data['effective_from'] ?? self::EFFECTIVE_FROM, 'created_at' => now(), 'updated_at' => now()]);
            return $id;
        });
    }

    public function updateCategory(int $id, array $data, CompanyContext $context, int $actor): void
    {
        $this->authorize($context, $actor);
        validator($data, ['name' => 'required|string|max:191', 'input_credit_allowed' => 'sometimes|boolean'])->validate();
        $category = DB::table('tax_categories')->where('company_id', $context->companyId)->where('id', $id)->first();
        abort_unless($category, 404);
        if (DB::table('tax_categories')->where('company_id', $context->companyId)->where('name', $data['name'])->where('id', '<>', $id)->exists()) {
            throw ValidationException::withMessages(['name' => 'A GST rate with this name already exists.']);
        }
        DB::table('tax_categories')->where('id', $id)->update(['name' => $data['name'], 'updated_at' => now(),
            'input_credit_allowed' => $category->supply_type === 'taxable' && !empty($data['input_credit_allowed'])]);
    }

    /** Sets a product's GST rate and HSN/SAC; the code joins the company catalog automatically. */
    public function assignProduct(int $productId, ?int $categoryId, ?string $hsn, CompanyContext $context, int $actor): void
    {
        app(\App\Services\Commercial\CommercialPermission::class)->assert('products-edit', $context, $actor);
        $product = Product::where('company_id', $context->companyId)->findOrFail($productId);
        $hsn = $hsn === null ? null : preg_replace('/\s+/', '', $hsn);
        if ($categoryId && !DB::table('tax_categories')->where('company_id', $context->companyId)->where('id', $categoryId)->exists()) {
            throw ValidationException::withMessages(['tax_category_id' => 'Select a GST rate of this company.']);
        }
        if ($hsn !== null && $hsn !== '') $this->validateCode($hsn, $this->kindFor($product->type));
        $product->forceFill(['tax_category_id' => $categoryId ?: null, 'hsn_code' => $hsn ?: null])->save();
    }

    public function kindFor(?string $productType): string
    {
        return in_array($productType, ['service', 'digital'], true) ? 'sac' : 'hsn';
    }

    public function validateCode(string $code, string $kind): void
    {
        $valid = $kind === 'sac' ? preg_match('/^99[0-9]{4}$/D', $code) : preg_match('/^[0-9]{4}(?:[0-9]{2})?(?:[0-9]{2})?$/D', $code);
        if (!$valid) {
            throw ValidationException::withMessages(['hsn_code' => $kind === 'sac'
                ? 'SAC must be six digits starting with 99.' : 'HSN must be 4, 6 or 8 digits.']);
        }
    }

    /** Adds a code to the company catalog when its shape is valid; invalid shapes are left for the engine to reject. */
    public function ensureCode(int $companyId, ?string $code, string $kind, string $description): void
    {
        if (!$code || !Schema::hasTable('hsn_sac_codes')) return;
        try { $this->validateCode($code, $kind); } catch (ValidationException) { return; }
        DB::table('hsn_sac_codes')->insertOrIgnore(['company_id' => $companyId, 'code' => $code, 'kind' => $kind,
            'description' => mb_substr($description, 0, 191), 'created_at' => now(), 'updated_at' => now()]);
    }

    /** Effective GST rate of a product for a date, or null when the product has no GST rate. */
    public function rateFor(object $product, ?string $date = null): ?array
    {
        if (empty($product->tax_category_id) || !Schema::hasTable('tax_categories')) return null;
        $date ??= now()->toDateString();
        $row = DB::table('tax_categories as c')->leftJoin('tax_rates as r', function ($join) use ($date) {
            $join->on('r.tax_category_id', '=', 'c.id')->where('r.effective_from', '<=', $date)
                ->where(fn ($q) => $q->whereNull('r.effective_to')->orWhere('r.effective_to', '>=', $date));
        })->where('c.id', $product->tax_category_id)->first(['c.id', 'c.name', 'c.supply_type', 'r.rate', 'r.cess_rate']);
        if (!$row) return null;
        $rate = $row->supply_type === 'taxable' ? (float) $row->rate : 0.0;
        return ['category_id' => $row->id, 'name' => $row->name, 'rate' => $rate, 'cess_rate' => (float) $row->cess_rate,
            'supply_type' => $row->supply_type];
    }

    /** category id => [rate, name] effective on the date, for page product lists. */
    public function currentRates(?int $companyId = null, ?string $date = null): array
    {
        if (!Schema::hasTable('tax_categories')) return [];
        try {
            $companyId ??= app(CompanyContextResolver::class)->forActor()->companyId;
        } catch (\Throwable) {
            return [];
        }
        $date ??= now()->toDateString();
        return DB::table('tax_categories as c')->leftJoin('tax_rates as r', function ($join) use ($date) {
            $join->on('r.tax_category_id', '=', 'c.id')->where('r.effective_from', '<=', $date)
                ->where(fn ($q) => $q->whereNull('r.effective_to')->orWhere('r.effective_to', '>=', $date));
        })->where('c.company_id', $companyId)->orderBy('r.rate')->orderBy('c.name')
            ->get(['c.id', 'c.name', 'c.supply_type', 'r.rate', 'r.cess_rate'])
            ->mapWithKeys(fn ($row) => [$row->id => ['rate' => $row->supply_type === 'taxable' ? (float) $row->rate : 0.0,
                'cess_rate' => (float) $row->cess_rate, 'name' => $row->name, 'supply_type' => $row->supply_type]])->all();
    }

    /** Page config for the GST entry script; null when the company bills without GST. */
    public function entryConfig(?CompanyContext $context = null): ?array
    {
        try {
            $context ??= request()->attributes->get(CompanyContext::class) ?? app(CompanyContextResolver::class)->forActor();
        } catch (\Throwable) {
            return null;
        }
        if (!$context || !$this->enabled($context)) return null;
        $registration = $this->registration($context);
        return ['enabled' => true, 'company_state' => $registration->state_code, 'gstin' => $registration->gstin,
            'registration_type' => $registration->registration_type, 'states' => array_diff_key(Gstin::$states, ['97' => 1])];
    }

    /** Tax rate a document line should start with: the GST slab when set, else the legacy VAT rate. */
    public function lineRate(object $product, ?string $date = null): float
    {
        if ($gst = $this->rateFor($product, $date)) return $gst['rate'];
        return $product->tax_id ? (float) (DB::table('taxes')->where('id', $product->tax_id)->value('rate') ?? 0) : 0.0;
    }

    /** Derives a party's GST profile from its master: GSTIN decides registration and state, else its state name. */
    public function deriveParty(string $type, object $party): ?array
    {
        $raw = $type === 'supplier' ? ($party->vat_number ?? null) : ($party->tax_no ?? null);
        $gstin = null;
        if (is_string($raw) && trim($raw) !== '') {
            try { $gstin = Gstin::normalize($raw); } catch (ValidationException) { $gstin = null; }
        }
        $state = $gstin ? substr($gstin, 0, 2) : ($this->stateCode($party->state ?? null) ?? $this->companyState((int) $party->company_id));
        return $state ? ['gstin' => $gstin, 'state_code' => $state, 'registration_type' => $gstin ? 'regular' : 'unregistered'] : null;
    }

    /** Keeps party_tax_profiles in step with the party master. A same-GSTIN composition choice is preserved. */
    public function syncParty(string $type, object $party): ?object
    {
        if (!Schema::hasTable('party_tax_profiles') || empty($party->company_id)) return null;
        $derived = $this->deriveParty($type, $party);
        if (!$derived) return null;
        $scope = ['company_id' => $party->company_id, 'party_type' => $type, 'party_id' => $party->id];
        $existing = DB::table('party_tax_profiles')->where($scope)->first();
        if ($existing && $existing->gstin === $derived['gstin'] && $existing->state_code === $derived['state_code']) return $existing;
        if ($existing && $existing->gstin && $existing->gstin === $derived['gstin'] && $existing->registration_type === 'composition') {
            $derived['registration_type'] = 'composition';
        }
        DB::table('party_tax_profiles')->updateOrInsert($scope, $derived + ['verified_at' => null, 'verification_json' => null,
            'updated_at' => now(), 'created_at' => $existing->created_at ?? now()]);
        return DB::table('party_tax_profiles')->where($scope)->first();
    }

    public function syncAllParties(int $companyId): int
    {
        $count = 0;
        foreach (['supplier' => 'suppliers', 'customer' => 'customers'] as $type => $table) {
            foreach (DB::table($table)->where('company_id', $companyId)->get() as $party) {
                if ($this->syncParty($type, $party)) $count++;
            }
        }
        return $count;
    }

    public function stateCode(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') return null;
        if (preg_match('/^(0[1-9]|[12][0-9]|3[0-8])$/D', $value)) return $value;
        $needle = strtolower(preg_replace('/[^a-z]/i', '', $value));
        foreach (Gstin::$states as $code => $name) {
            if ($code !== '97' && strtolower(preg_replace('/[^a-z]/i', '', $name)) === $needle) return (string) $code;
        }
        return null;
    }

    private function companyState(int $companyId): ?string
    {
        $state = Schema::hasTable('tax_registrations') ? DB::table('tax_registrations')->where('company_id', $companyId)
            ->whereNull('effective_to')->value('state_code') : null;
        return $state ?: (Company::whereKey($companyId)->value('state_code') ?: null);
    }

    public function authorize(CompanyContext $context, int $actor): void
    {
        $resolver = app(CompanyContextResolver::class);
        $resolver->forActor($context, $actor);
        abort_unless($resolver->canManageFinancialYears($actor, $context->companyId), 403, 'Company administrator required.');
    }
}
