<?php

namespace App\Services\Commercial;

use App\Models\Customer;
use App\Services\Platform\CompanyContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreditControlService
{
    public function summary(Customer $customer, CompanyContext $context, string $date): array
    {
        $query = DB::table('account_open_items')->where('company_id', $context->companyId)
            ->where('party_type', 'customer')->where('party_id', $customer->id)->where('status', '!=', 'closed');
        $outstanding = round((float) (clone $query)->sum('open_amount'), 4);
        $overdue = round((float) (clone $query)->where('due_date', '<', $date)->where('open_amount', '>', 0)->sum('open_amount'), 4);
        $limit = (float) ($customer->credit_limit ?? 0);
        return ['outstanding' => $outstanding, 'overdue' => $overdue, 'credit_limit' => $limit,
            'available_credit' => $limit > 0 ? max(0, $limit - $outstanding) : null];
    }

    public function validate(Customer $customer, array $data, CompanyContext $context, int $actor, string $date): ?array
    {
        $newCredit = round((float) $data['grand_total'] - (float) $data['paid_amount'], 4);
        if ($newCredit <= 0 || (int) ($data['sale_status'] ?? 1) !== 1) {
            return null;
        }
        $summary = $this->summary($customer, $context, $date);
        $violations = [];
        if ($summary['credit_limit'] > 0 && $summary['outstanding'] + $newCredit > $summary['credit_limit']) {
            $violations[] = 'Credit limit exceeded.';
        }
        if (($customer->credit_days ?? 0) > 0 && $summary['overdue'] > 0) {
            $violations[] = 'Customer has overdue invoices.';
        }
        if ($violations === []) {
            return null;
        }
        if (empty($data['credit_override_reason'])) {
            throw ValidationException::withMessages(['credit' => implode(' ', $violations)]);
        }
        app(CommercialPermission::class)->assert('sales.override_credit', $context, $actor);
        if (!is_string($data['credit_override_reason']) || strlen(trim($data['credit_override_reason'])) < 3
            || strlen($data['credit_override_reason']) > 500) {
            throw ValidationException::withMessages(['credit_override_reason' => 'Give an override reason between 3 and 500 characters.']);
        }
        return $summary + ['new_credit' => $newCredit, 'reason' => $data['credit_override_reason'], 'violations' => $violations];
    }
}
