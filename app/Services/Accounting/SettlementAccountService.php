<?php

namespace App\Services\Accounting;

use App\Models\{Account, Company};
use App\Models\Accounting\ChartOfAccount;
use App\Services\ERP\CompanyWriteGuard;
use App\Services\Platform\{CompanyContext, CompanyContextResolver};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Named payment accounts and their exact cash/bank ledgers. Never creates money or changes default mappings. */
class SettlementAccountService
{
    public function options(CompanyContext $context): array
    {
        return Account::forCompany($context)->with('ledger')->whereHas('ledger', fn ($q) => $q
            ->forCompany($context)->where('is_active', true)->whereIn('control_type', ['cash', 'bank'])->whereDoesntHave('children'))
            ->orderBy('name')->get()->filter(fn ($account) => !isset($account->is_active) || $account->is_active)
            ->map(fn ($account) => ['id' => $account->id, 'name' => $account->name, 'kind' => $account->ledger->control_type,
                'ledger_id' => $account->ledger->id, 'ledger_name' => $account->ledger->name,
                'last_four' => preg_match('/\d/', (string) $account->account_no) ? mb_substr($account->account_no, -4) : null])->values()->all();
    }

    /** Link an explicitly classified cash/bank asset without changing its balance or posting defaults. */
    public function syncFromLedger(ChartOfAccount $ledger, CompanyContext $context, ?string $previousName = null): ?Account
    {
        return DB::transaction(function () use ($ledger, $context, $previousName) {
            Company::whereKey($context->companyId)->lockForUpdate()->firstOrFail();
            $ledger = ChartOfAccount::forCompany($context)->lockForUpdate()->findOrFail($ledger->id);
            $subType = mb_strtolower(trim($ledger->sub_type));
            if (in_array($subType, ['cash', 'bank'], true) && in_array($ledger->control_type, ['cash', 'bank'], true)
                && $subType !== $ledger->control_type) {
                throw ValidationException::withMessages(['sub_type' => 'The cash/bank subtype conflicts with the existing control role. Review the ledger mapping.']);
            }
            $kind = in_array($ledger->control_type, ['cash', 'bank'], true) ? $ledger->control_type : $subType;
            if (!in_array($kind, ['cash', 'bank'], true) || !$ledger->is_active || $ledger->children()->exists()) return null;
            if ($ledger->type !== 'asset' || !in_array($ledger->control_type, ['none', $kind], true)) {
                throw ValidationException::withMessages(['sub_type' => 'Cash and bank payment accounts must be asset ledgers with the matching control role.']);
            }
            $ledger->forceFill(['control_type' => $kind] + (in_array($subType, ['cash', 'bank'], true) ? ['sub_type' => $kind] : []))->save();
            $linked = Account::forCompany($context)->where('chart_of_account_id', $ledger->id)->orderBy('id')->lockForUpdate()->get();
            if ($linked->isNotEmpty()) {
                if ($previousName !== null && $previousName !== $ledger->name) {
                    foreach ($linked as $account) {
                        if ($account->name === $previousName) $account->forceFill(['name' => $ledger->name])->save();
                    }
                }
                return $linked->first();
            }
            if (Account::forCompany($context)->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($ledger->name))])->exists()) {
                throw ValidationException::withMessages(['name' => 'A payment account already uses this name. Link it to this ledger in Voucher Hub instead of creating a duplicate.']);
            }
            return Account::forceCreate(['company_id' => $context->companyId, 'name' => $ledger->name, 'account_no' => '',
                'chart_of_account_id' => $ledger->id, 'initial_balance' => 0, 'total_balance' => 0, 'is_default' => false, 'is_active' => true]);
        });
    }

    public function validateSelection(int $id, string $method, CompanyContext $context): void
    {
        $kind = $method === 'Cash' ? 'cash' : 'bank';
        $account = collect($this->options($context))->firstWhere('id', $id);
        if (!$account || $account['kind'] !== $kind) {
            throw ValidationException::withMessages(['account_id' => 'Choose an active '.($kind === 'bank' ? 'bank account' : 'cash account').' for this payment method.']);
        }
    }

    public function create(array $data, CompanyContext $context, int $actor): Account
    {
        abort_unless(app(CompanyContextResolver::class)->canManageFinancialYears($actor, $context->companyId), 403);
        $data = validator($data, ['name' => 'required|string|max:150', 'kind' => 'required|in:cash,bank',
            'account_no' => 'nullable|string|max:50'])->validate();
        $data['name'] = trim($data['name']);
        $data['account_no'] = trim($data['account_no'] ?? '');
        if ($data['name'] === '') throw ValidationException::withMessages(['name' => 'Enter an account name.']);
        return DB::transaction(function () use ($data, $context, $actor) {
            Company::whereKey($context->companyId)->lockForUpdate()->firstOrFail();
            app(CompanyWriteGuard::class)->begin($context, null);
            $existing = Account::forCompany($context)->whereRaw('LOWER(name) = ?', [mb_strtolower($data['name'])])->first();
            if ($existing) {
                if ($existing->ledger?->control_type === $data['kind'] && (string) $existing->account_no === $data['account_no']
                    && collect($this->options($context))->contains('id', $existing->id)) return $existing;
                throw ValidationException::withMessages(['name' => 'This account name already exists. Use a distinct name or link the existing account in Voucher Hub.']);
            }
            if ($data['account_no'] !== '' && Account::forCompany($context)->where('account_no', $data['account_no'])->exists()) {
                throw ValidationException::withMessages(['account_no' => 'This account number already exists. Select the existing account.']);
            }
            $ledgers = ChartOfAccount::forCompany($context)->whereRaw('LOWER(name) = ?', [mb_strtolower($data['name'])])->lockForUpdate()->get();
            if ($ledgers->isNotEmpty()) {
                $ledger = $ledgers->count() === 1 ? $ledgers->first() : null;
                $ledgerKind = $ledger && in_array($ledger->control_type, ['cash', 'bank'], true) ? $ledger->control_type : mb_strtolower(trim($ledger?->sub_type ?? ''));
                if (!$ledger || $ledger->type !== 'asset' || $ledgerKind !== $data['kind']) {
                    throw ValidationException::withMessages(['name' => 'A COA ledger already uses this name. Select or correct that ledger instead of creating a duplicate.']);
                }
                $account = $this->syncFromLedger($ledger, $context);
                if (!$account) throw ValidationException::withMessages(['name' => 'This COA ledger must be active and have no child accounts.']);
                $account->forceFill(['account_no' => $data['account_no']])->save();
                return $account;
            }
            $sequence = 1;
            do { $code = strtoupper($data['kind']).'-'.str_pad((string) $sequence++, 4, '0', STR_PAD_LEFT); }
            while (ChartOfAccount::forCompany($context)->where('code', $code)->exists());
            $ledger = ChartOfAccount::forceCreate(['company_id' => $context->companyId, 'code' => $code,
                'name' => $data['name'], 'type' => 'asset', 'sub_type' => $data['kind'], 'control_type' => $data['kind'],
                'allow_manual_posting' => true, 'opening_balance' => 0, 'current_balance' => 0, 'is_active' => true, 'is_system' => false]);
            return Account::forceCreate(['company_id' => $context->companyId, 'name' => $data['name'], 'account_no' => $data['account_no'],
                'chart_of_account_id' => $ledger->id, 'initial_balance' => 0, 'total_balance' => 0, 'is_default' => false, 'is_active' => true]);
        });
    }
}
