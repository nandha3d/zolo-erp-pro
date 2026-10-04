<?php

namespace App\Services\Commercial;

use App\Models\Accounting\ChartOfAccount;
use App\Models\Payment;
use App\Services\Accounting\AccountingService;
use App\Services\Platform\CompanyContext;
use Illuminate\Validation\ValidationException;

class PostingAccounts
{
    public function account(string $role, CompanyContext $context, ?int $actor = null): int
    {
        $account = app(AccountingService::class)->getAccount($role, $context, $actor);
        if (!$account || !$account->is_active || $account->children()->exists()) {
            throw ValidationException::withMessages(['accounts' => 'Configure an active leaf account for '.$role.'.']);
        }
        return $account->id;
    }

    public function settlement(Payment $payment, CompanyContext $context, ?int $actor = null): int
    {
        $role = in_array($payment->paying_method, ['Bank', 'Cheque', 'Credit Card'], true) ? 'bank' : 'cash';
        $legacy = \App\Models\Account::forCompany($context)->findOrFail($payment->account_id);
        if ($legacy->chart_of_account_id) {
            $account = ChartOfAccount::forCompany($context)->find($legacy->chart_of_account_id);
            if (!$account || !$account->is_active || $account->control_type !== $role || $account->children()->exists()) {
                throw ValidationException::withMessages(['account_id' => 'Payment account must link to an active cash or bank leaf account.']);
            }
            return $account->id;
        }
        return $this->account($role, $context, $actor);
    }
}
