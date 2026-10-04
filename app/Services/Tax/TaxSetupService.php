<?php

namespace App\Services\Tax;

use App\Services\Platform\{CompanyContext, CompanyContextResolver};
use App\Services\ERP\CompanyWriteGuard;
use Illuminate\Support\Facades\{DB, Crypt};
use Illuminate\Validation\ValidationException;

class TaxSetupService
{
    public function save(string $resource, array $data, CompanyContext $context, int $actor): void
    {
        $resolver = app(CompanyContextResolver::class); $resolver->forActor($context, $actor);
        abort_unless($resolver->canManageFinancialYears($actor, $context->companyId), 403, 'Company administrator required.');
        DB::transaction(function () use ($resource, $data, $context, $actor) {
            \App\Models\Company::whereKey($context->companyId)->lockForUpdate()->firstOrFail();
            $owner = ['company_id' => $context->companyId];
            switch ($resource) {
                case 'close-period':
                    validator($data, ['period_type' => 'required|in:registration,rate', 'id' => 'required|integer|min:1',
                        'effective_to' => 'required|date_format:Y-m-d'])->validate();
                    $table = $data['period_type'] === 'registration' ? 'tax_registrations' : 'tax_rates';
                    $query = DB::table($table)->where($owner)->where('id', $data['id']);
                    if ($table === 'tax_registrations') $query->where('branch_id', $context->branchId);
                    $period = $query->lockForUpdate()->first(); abort_unless($period, 404);
                    if ($period->effective_to || $data['effective_to'] < $period->effective_from) {
                        throw ValidationException::withMessages(['effective_to' => 'Close an open period on or after its start.']);
                    }
                    $query->update(['effective_to' => $data['effective_to'], 'updated_at' => now()]);
                    break;
                case 'registration':
                    validator($data, ['gstin' => 'required|string|max:15', 'legal_name' => 'required|string|max:191',
                        'effective_from' => 'required|date_format:Y-m-d', 'effective_to' => 'nullable|date_format:Y-m-d|after_or_equal:effective_from',
                        'registration_type' => 'required|in:regular,composition', 'address' => 'required|string|max:1000'])->validate();
                    $gstin = Gstin::normalize($data['gstin']);
                    $this->noOverlap('tax_registrations', $owner + ['branch_id' => $context->branchId], $data);
                    DB::table('tax_registrations')->insert($owner + ['branch_id' => $context->branchId, 'gstin' => $gstin,
                        'legal_name' => $data['legal_name'], 'address' => $data['address'], 'state_code' => substr($gstin, 0, 2), 'registration_type' => $data['registration_type'],
                        'effective_from' => $data['effective_from'], 'effective_to' => $data['effective_to'] ?? null, 'created_at' => now(), 'updated_at' => now()]);
                    break;
                case 'party':
                    validator($data, ['party_type' => 'required|in:customer,supplier', 'party_id' => 'required|integer|min:1',
                        'registration_type' => 'required|in:regular,unregistered,composition', 'gstin' => 'nullable|string|max:15', 'state_code' => 'required|string|size:2'])->validate();
                    app(CompanyWriteGuard::class)->owned($data['party_type'] === 'customer' ? \App\Models\Customer::class : \App\Models\Supplier::class, $data['party_id'], $context, 'party_id');
                    $gstin = empty($data['gstin']) ? null : Gstin::normalize($data['gstin']); $state = Gstin::state($data['state_code']);
                    if (($data['registration_type'] !== 'unregistered') !== (bool) $gstin || ($gstin && substr($gstin, 0, 2) !== $state)) {
                        throw ValidationException::withMessages(['gstin' => 'Registration type, GSTIN and state must agree.']);
                    }
                    DB::table('party_tax_profiles')->updateOrInsert($owner + ['party_type' => $data['party_type'], 'party_id' => $data['party_id']],
                        ['gstin' => $gstin, 'state_code' => $state, 'registration_type' => $data['registration_type'], 'verified_at' => null, 'verification_json' => null, 'updated_at' => now()]);
                    break;
                case 'category':
                    validator($data, ['name' => 'required|string|max:191', 'supply_type' => 'required|in:taxable,exempt,nil_rated,non_gst', 'input_credit_allowed' => 'sometimes|boolean'])->validate();
                    DB::table('tax_categories')->insert($owner + ['name' => $data['name'], 'supply_type' => $data['supply_type'],
                        'input_credit_allowed' => (bool) ($data['input_credit_allowed'] ?? false), 'created_at' => now(), 'updated_at' => now()]);
                    break;
                case 'rate':
                    validator($data, ['tax_category_id' => 'required|integer|min:1', 'rate' => 'required|numeric|min:0|max:100',
                        'cess_rate' => 'required|numeric|min:0|max:100', 'effective_from' => 'required|date_format:Y-m-d',
                        'effective_to' => 'nullable|date_format:Y-m-d|after_or_equal:effective_from'])->validate();
                    abort_unless(DB::table('tax_categories')->where($owner)->where('id', $data['tax_category_id'])->exists(), 404);
                    if ($data['rate'] + $data['cess_rate'] > 100) throw ValidationException::withMessages(['rate' => 'Combined tax cannot exceed 100.']);
                    $this->noOverlap('tax_rates', $owner + ['tax_category_id' => $data['tax_category_id']], $data);
                    DB::table('tax_rates')->insert($owner + array_intersect_key($data, array_flip(['tax_category_id', 'rate', 'cess_rate', 'effective_from', 'effective_to'])) + ['created_at' => now(), 'updated_at' => now()]);
                    break;
                case 'hsn':
                    validator($data, ['code' => 'required|regex:/^[0-9]{4}(?:[0-9]{2})?(?:[0-9]{2})?$/', 'kind' => 'required|in:hsn,sac', 'description' => 'required|string|max:191'])->validate();
                    if ($data['kind'] === 'sac' && !preg_match('/^99[0-9]{4}$/D', $data['code'])) throw ValidationException::withMessages(['code' => 'SAC must contain six digits starting with 99.']);
                    DB::table('hsn_sac_codes')->updateOrInsert($owner + ['code' => $data['code']], ['kind' => $data['kind'], 'description' => $data['description'], 'updated_at' => now()]);
                    break;
                case 'product':
                    validator($data, ['product_id' => 'required|integer|min:1', 'tax_category_id' => 'required|integer|min:1', 'hsn_code' => 'required|string|max:8'])->validate();
                    $product = app(CompanyWriteGuard::class)->owned(\App\Models\Product::class, $data['product_id'], $context, 'product_id');
                    abort_unless(DB::table('tax_categories')->where($owner)->where('id', $data['tax_category_id'])->exists()
                        && DB::table('hsn_sac_codes')->where($owner)->where('code', $data['hsn_code'])
                            ->where('kind', in_array($product->type, ['service', 'digital'], true) ? 'sac' : 'hsn')->exists(), 404);
                    $product->forceFill(['tax_category_id' => $data['tax_category_id'], 'hsn_code' => $data['hsn_code']])->save();
                    break;
                case 'return-policy':
                    validator($data, ['amount' => 'required|numeric|min:0|max:1000000000', 'days' => 'required|integer|min:0|max:3650'])->validate();
                    $company = \App\Models\Company::findOrFail($context->companyId);
                    $company->update(['settings_json' => array_replace($company->settings_json ?? [], ['return_policy' => ['amount' => (float) $data['amount'], 'days' => (int) $data['days']]])]);
                    break;
                case 'quarantine':
                    validator($data, ['warehouse_id' => 'required|integer|min:1'])->validate();
                    $warehouse = app(CompanyWriteGuard::class)->warehouse($data['warehouse_id'], $context, $actor);
                    if (DB::table('product_warehouse')->where('warehouse_id', $warehouse->id)->where('qty', '<>', 0)->exists()) {
                        throw ValidationException::withMessages(['warehouse_id' => 'Only an empty warehouse can become quarantine.']);
                    }
                    DB::table('warehouses')->where('id', $warehouse->id)->update(['is_quarantine' => true]);
                    break;
                case 'profile':
                    validator($data, ['document_type' => 'required|in:sale,purchase,sale_note,purchase_note', 'name' => 'required|string|max:191',
                        'format' => 'required|in:a4,thermal,dot_matrix', 'paper_width' => 'required|integer|min:58|max:300',
                        'height_lines' => 'required|integer|min:20|max:200', 'columns' => 'required|integer|min:60|max:200',
                        'copy_label' => 'required|string|max:60'])->validate();
                    DB::table('print_profiles')->insert($owner + array_intersect_key($data, array_flip(['document_type', 'name', 'format', 'paper_width', 'height_lines'])) +
                        ['copies_json' => json_encode([$data['copy_label']]), 'settings_json' => json_encode(['columns' => (int) $data['columns']]), 'created_at' => now(), 'updated_at' => now()]);
                    break;
                case 'channel':
                    validator($data, ['channel' => 'required|in:email,sms,whatsapp', 'from' => ($data['channel'] ?? '') === 'email' ? 'required|email:rfc|max:191' : 'required|regex:/^\+[1-9][0-9]{7,14}$/',
                        'account_sid' => 'required_unless:channel,email|nullable|regex:/^AC[0-9a-fA-F]{32}$/',
                        'auth_token' => 'required_unless:channel,email|nullable|string|min:16|max:200',
                        'content_sid' => 'required_if:channel,whatsapp|nullable|regex:/^HX[0-9a-fA-F]{32}$/'])->validate();
                    $settings = array_intersect_key($data, array_flip(['from', 'account_sid', 'auth_token', 'content_sid']));
                    DB::table('document_channels')->updateOrInsert($owner + ['channel' => $data['channel']],
                        ['configuration_encrypted' => Crypt::encryptString(json_encode($settings, JSON_THROW_ON_ERROR)), 'is_active' => true, 'updated_at' => now()]);
                    break;
                default: abort(404);
            }
        });
    }

    private function noOverlap(string $table, array $scope, array $dates): void
    {
        if (DB::table($table)->where($scope)->where('effective_from', '<=', $dates['effective_to'] ?? '9999-12-31')
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $dates['effective_from']))->exists()) {
            throw ValidationException::withMessages(['effective_from' => 'Effective periods cannot overlap. End the existing period before adding another.']);
        }
    }
}
