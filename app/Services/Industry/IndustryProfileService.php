<?php

namespace App\Services\Industry;

use App\Models\Product;
use App\Services\ERP\CompanyWriteGuard;
use App\Services\Operations\OperationPosting;
use App\Services\Platform\CapabilityService;
use App\Services\Platform\CompanyContext;
use App\Services\Platform\CompanyContextResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IndustryProfileService
{
    public function apply(string $profile, ?string $subtype, CompanyContext $context, int $actor): array
    {
        app(OperationPosting::class)->authorize('core.inventory', 'profiles.manage', $context, $actor);
        $resolver = app(CompanyContextResolver::class);
        $resolver->forActor($context, $actor);
        if (!$resolver->canManageFinancialYears($actor, $context->companyId)) throw new \Illuminate\Auth\Access\AuthorizationException('Company administrator required.');
        $choices = IndustryCatalog::SUBTYPES[$profile] ?? null;
        $subtype ??= $choices ? array_key_first($choices) : null;
        if (!$choices || !array_key_exists($subtype, $choices)) throw ValidationException::withMessages(['profile' => 'Select a supported profile and subtype.']);
        return DB::transaction(function () use ($profile, $subtype, $choices, $context, $actor) {
            app(CapabilityService::class)->applyProfile($profile, $context, $actor);
            foreach ($choices[$subtype] as $capability) app(CapabilityService::class)->enable($capability, [], $context, $actor);
            DB::table('company_industry_settings')->where('company_id', $context->companyId)->update(['subtype' => $subtype]);
            app(OperationPosting::class)->audit('profile_apply', $context->companyId, $context, $actor, compact('profile', 'subtype'));
            return $this->settings($context);
        }, 3);
    }

    /** Called within CapabilityService's profile transaction, including the existing setup API. */
    public function installDefaults(string $profile, CompanyContext $context, int $actor): void
    {
        $settings = IndustryCatalog::SETTINGS[$profile];
        DB::table('company_industry_settings')->updateOrInsert(['company_id' => $context->companyId], [
            'profile_key' => $profile, 'subtype' => array_key_first(IndustryCatalog::SUBTYPES[$profile]),
            'settings_json' => json_encode($settings, JSON_THROW_ON_ERROR), 'updated_by' => $actor, 'updated_at' => now(), 'created_at' => now(),
        ]);
        DB::table('product_attribute_definitions')->where('company_id', $context->companyId)->update(['enabled' => false]);
        foreach (IndustryCatalog::ATTRIBUTES[$profile] as $key => [$label, $type]) DB::table('product_attribute_definitions')->updateOrInsert([
            'company_id' => $context->companyId, 'key' => $key], ['label' => $label, 'type' => $type, 'profile_key' => $profile, 'enabled' => true]);
        foreach (IndustryCatalog::PROCESSES[$profile] ?? [] as [$code, $name, $loss, $conversion]) {
            if (!DB::table('process_types')->where('company_id', $context->companyId)->where('code', $code)->exists()) {
                DB::table('process_types')->insert(['company_id' => $context->companyId, 'code' => $code, 'name' => $name,
                    'max_loss_percent' => $loss, 'conversion' => $conversion, 'quantity_basis' => $profile === 'timber' ? 'volume_cbm' : 'base_qty',
                    'created_at' => now(), 'updated_at' => now()]);
            }
        }
        // Presets never change existing unit factors, stock flags, product values or company valuation policies.
    }

    public function settings(CompanyContext $context): array
    {
        $row = DB::table('company_industry_settings')->where('company_id', $context->companyId)->first();
        return $row ? ['profile' => $row->profile_key, 'subtype' => $row->subtype,
            'settings' => json_decode($row->settings_json, true, 512, JSON_THROW_ON_ERROR)] : ['profile' => 'general_trading', 'subtype' => 'trading', 'settings' => IndustryCatalog::SETTINGS['general_trading']];
    }

    public function attributes(int $productId, CompanyContext $context, int $actor, ?array $values = null): array
    {
        app(CompanyWriteGuard::class)->context($context, $actor);
        app(\App\Services\Commercial\CommercialPermission::class)->assert($values === null ? 'products-index' : 'products-edit', $context, $actor);
        $product = Product::forCompany($context)->findOrFail($productId);
        return DB::transaction(function () use ($product, $values, $context, $actor) {
            \App\Models\Company::whereKey($context->companyId)->lockForUpdate()->firstOrFail();
            $definitions = DB::table('product_attribute_definitions')->where('company_id', $context->companyId)->where('enabled', true)->get()->keyBy('key');
            if ($values !== null) {
                if (array_diff(array_keys($values), $definitions->keys()->all())) throw ValidationException::withMessages(['attributes' => 'Only enabled profile attributes can be written.']);
                foreach ($values as $key => $value) {
                    $definition = $definitions[$key];
                    $rule = match ($definition->type) { 'number' => 'numeric|min:0|max:999999999', 'integer' => 'integer|min:0|max:1200', default => 'string|max:255' };
                    validator(['value' => $value], ['value' => 'required|'.$rule])->validate();
                    DB::table('product_attribute_values')->updateOrInsert(['product_id' => $product->id, 'definition_id' => $definition->id],
                        ['company_id' => $context->companyId, 'value_json' => json_encode($value, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);
                }
                app(OperationPosting::class)->audit('product_attributes', $product->id, $context, $actor, $values);
            }
            $saved = DB::table('product_attribute_values')->where('company_id', $context->companyId)->where('product_id', $product->id)->pluck('value_json', 'definition_id');
            return $definitions->map(fn ($definition) => ['key' => $definition->key, 'label' => $definition->label, 'type' => $definition->type,
                'value' => isset($saved[$definition->id]) ? json_decode($saved[$definition->id], true) : null])->values()->all();
        });
    }
}
