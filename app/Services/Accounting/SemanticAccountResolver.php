<?php

namespace App\Services\Accounting;

use App\Models\Accounting\ChartOfAccount;
use App\Models\Accounting\SemanticAccountMapping;
use App\Services\Platform\CompanyContext;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Role definitions are shared by setup, posting and mapping validation. */
class SemanticAccountResolver
{
    public const ROLES = [
        'ar' => ['accounts_receivable', 'asset'], 'ap' => ['accounts_payable', 'liability'],
        'cash' => ['cash', 'asset'], 'bank' => ['bank', 'asset'],
        'inventory' => ['inventory', 'asset'], 'sales' => ['sales_revenue', 'revenue'],
        'cogs' => ['cogs', 'expense'], 'tax_output' => ['tax_payable', 'liability'],
        'tax_input' => ['tax_receivable', 'asset'], 'discounts' => ['sales_discount', ['revenue', 'expense']],
        'freight' => ['shipping_income', 'revenue'], 'goods_in_transit' => ['goods_in_transit', 'asset'],
        'expense' => ['operating_expense', 'expense'], 'sales_returns' => ['sales_returns', 'revenue'],
        'purchase_returns' => ['purchase_returns', 'revenue'], 'rounding' => ['rounding', 'expense'],
        'damage' => ['inventory_loss', 'expense'], 'variance' => ['inventory_variance', 'expense'],
        'output_tax_cgst' => ['output_tax_cgst', 'liability'], 'output_tax_sgst' => ['output_tax_sgst', 'liability'],
        'output_tax_igst' => ['output_tax_igst', 'liability'], 'input_tax_cgst' => ['input_tax_cgst', 'asset'],
        'input_tax_sgst' => ['input_tax_sgst', 'asset'], 'input_tax_igst' => ['input_tax_igst', 'asset'],
        'output_tax_cess' => ['output_tax_cess', 'liability'], 'input_tax_cess' => ['input_tax_cess', 'asset'],
    ];

    private const ALIASES = [
        'accounts_receivable' => 'ar', 'accounts_payable' => 'ap', 'sales_revenue' => 'sales',
        'inventory_asset' => 'inventory', 'tax_payable' => 'tax_output', 'sales_discount' => 'discounts',
        'shipping_income' => 'freight', 'operating_expense' => 'expense',
    ];

    public function role(string $role): string
    {
        return self::ALIASES[$role] ?? $role;
    }

    public function resolve(string $role, CompanyContext $context, bool $required = true): ?ChartOfAccount
    {
        $role = $this->role($role);
        $mapping = SemanticAccountMapping::forCompany($context)->whereIn('semantic_role', [$role, ...array_keys(array_filter(self::ALIASES, fn ($canonical) => $canonical === $role))])->get();
        if ($mapping->count() > 1) {
            throw new InvalidArgumentException("Ambiguous company account mapping for {$role}.");
        }
        $mapping = $mapping->first();
        $account = $mapping && ($mapping->is_active ?? true)
            ? ChartOfAccount::forCompany($context)->where('is_active', true)->find($mapping->account_id) : null;
        if ($account) {
            $this->validate($role, $account);
            return $account;
        }
        if ($required) {
            throw new InvalidArgumentException("Configure an active company account mapping for {$role} before posting.");
        }
        return null;
    }

    public function validate(string $role, ChartOfAccount $account): void
    {
        $role = $this->role($role);
        if (isset(self::ROLES[$role]) && !in_array($account->type, (array) self::ROLES[$role][1], true)) {
            throw new InvalidArgumentException("Account type is invalid for {$role}.");
        }
        if ($account->children()->exists()) {
            throw new InvalidArgumentException('Posting roles must use leaf accounts.');
        }
        if (in_array($role, ['ar', 'ap', 'cash', 'bank'], true)
            && $account->control_type !== 'none' && $account->control_type !== $role) {
            throw new InvalidArgumentException('Account already belongs to another control role.');
        }
    }

    /** Explicit setup only. Existing mappings and all monetary balances are preserved. */
    public function seedCompany(int $companyId): void
    {
        DB::transaction(function () use ($companyId) {
            \App\Models\Company::whereKey($companyId)->lockForUpdate()->firstOrFail();
            foreach (self::ROLES as $role => [$subType, $type]) {
                $aliases = array_keys(array_filter(self::ALIASES, fn ($canonical) => $canonical === $role));
                $existing = SemanticAccountMapping::where('company_id', $companyId)->whereIn('semantic_role', [$role, ...$aliases])->first();
                if ($existing) {
                    $account = ChartOfAccount::where('company_id', $companyId)->find($existing->account_id);
                } else {
                    $candidates = ChartOfAccount::where('company_id', $companyId)->where('is_active', true)
                        ->whereIn('type', (array) $type)->where('sub_type', $subType)->whereDoesntHave('children')->get();
                    $account = $candidates->count() === 1 ? $candidates->sole() : null;
                    (new SemanticAccountMapping)->forceFill([
                        'company_id' => $companyId, 'semantic_role' => $role, 'account_id' => $account?->id,
                        'account_code' => $account?->code, 'account_name' => $account?->name,
                        'category' => 'core', 'description' => 'Company posting role: '.$role,
                    ])->save();
                }
                if ($account && in_array($role, ['ar', 'ap', 'cash', 'bank'], true)) {
                    $this->validate($role, $account);
                    $account->forceFill(['control_type' => $role, 'allow_manual_posting' => !in_array($role, ['ar', 'ap'], true)])->save();
                }
            }
        });
    }
}
