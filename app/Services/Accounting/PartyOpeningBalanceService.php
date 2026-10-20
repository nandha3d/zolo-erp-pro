<?php

namespace App\Services\Accounting;

use App\Models\Accounting\FiscalYear;
use App\Models\Accounting\JournalEntry;
use App\Models\Customer;
use App\Models\Supplier;
use App\Services\Platform\CompanyContext;
use App\Services\Platform\CompanyContextResolver;
use Illuminate\Validation\ValidationException;

/**
 * A party's due carried in from before go-live. It posts on the selected financial year's start date as an open item on
 * the party's control account (Dr Debtors or Cr Creditors) against Opening Balance Adjustment, so receipts, payments and
 * ageing see it like any bill. It replaces the old unposted "Opening balance" sale / purchase row.
 */
class PartyOpeningBalanceService
{
    /** Check before the party is saved, so a missing mapping or a locked year start never leaves a half-created party. */
    public function assertPostable(string $partyType, float $amount, CompanyContext $context): void
    {
        if ($amount <= 0) {
            return;
        }
        try {
            $resolver = app(SemanticAccountResolver::class);
            $resolver->resolve($partyType === 'customer' ? 'ar' : 'ap', $context);
            $resolver->resolve('opening_balance', $context);
            app(CompanyContextResolver::class)->assertPostingDate($context, $this->date($context));
        } catch (\InvalidArgumentException|ValidationException $error) {
            throw $this->error($error, $context);
        }
    }

    public function post(Customer|Supplier $party, float $amount, CompanyContext $context): ?JournalEntry
    {
        if ($amount <= 0) {
            return null;
        }
        $customer = $party instanceof Customer;
        $type = $customer ? 'customer' : 'supplier';
        $resolver = app(SemanticAccountResolver::class);
        try {
            $control = $resolver->resolve($customer ? 'ar' : 'ap', $context);
            $offset = $resolver->resolve('opening_balance', $context);
            $partyLine = ['chart_of_account_id' => $control->id, 'partner_type' => $type, 'partner_id' => $party->id, 'memo' => 'Opening balance'];
            $offsetLine = ['chart_of_account_id' => $offset->id, 'memo' => 'Opening balance: '.$party->name];
            return app(AccountingPostingService::class)->postJournalEntry([
                'entry_date' => $this->date($context), 'reference_type' => $type.'_opening', 'reference_id' => $party->id,
                'reference_no' => 'OPENING', 'description' => 'Opening balance: '.$party->name,
                'posting_key' => $type.'_opening:'.$party->id.':v1',
            ], $customer
                ? [$partyLine + ['debit' => $amount], $offsetLine + ['credit' => $amount]]
                : [$offsetLine + ['debit' => $amount], $partyLine + ['credit' => $amount]], $context);
        } catch (\InvalidArgumentException|ValidationException $error) {
            throw $this->error($error, $context);
        }
    }

    private function date(CompanyContext $context): string
    {
        return FiscalYear::where('company_id', $context->companyId)->findOrFail($context->financialYearId)->start_date->toDateString();
    }

    private function error(\Throwable $error, CompanyContext $context): ValidationException
    {
        $reason = $error instanceof ValidationException ? collect($error->errors())->flatten()->first() : $error->getMessage();
        return ValidationException::withMessages(['opening_balance' => 'The opening balance posts on '.$this->date($context)
            .' (start of the financial year) and could not be posted: '.$reason]);
    }
}
