<?php

namespace App\Services\ERP;

use App\Models\Payment;
use App\Models\Purchase;
use App\Models\Sale;
use App\Services\Accounting\AccountingService;
use App\Services\Platform\CompanyContext;
use App\Services\Platform\DocumentNumberService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Owns customer and supplier settlements over the reviewed commercial documents. */
class PaymentService
{
    public function __construct(private AccountingService $accountingService)
    {
    }

    public function addPayment(Sale|Purchase $source, array $data, ?int $actor = null, ?CompanyContext $context = null): Payment
    {
        $actor = $actor ?: auth()->id();
        $guard = app(CompanyWriteGuard::class);
        $context = $guard->context($context, $actor);
        $amount = (float) \App\Support\LedgerAmount::decimal(\App\Support\LedgerAmount::units($data['amount'] ?? 0));
        if (!is_numeric($data['amount'] ?? null) || !is_finite($amount) || $amount <= 0) {
            throw new InvalidArgumentException('Payment amount must be finite and positive.');
        }

        return DB::transaction(function () use ($source, $data, $actor, $context, $guard, $amount) {
            $date = $guard->begin($context, $guard->businessDate($data));
            $guard->rejectUnscopedReferences($data);
            $source = $source::visibleIn($context)->whereKey($source->id)->lockForUpdate()->firstOrFail();
            $type = $source instanceof Sale ? 'sale' : 'purchase';
            $accountId = $guard->paymentAccount($data, $context);
            if (!empty($data['idempotency_key'])) {
                if (!is_string($data['idempotency_key']) || strlen($data['idempotency_key']) > 100) {
                    throw new InvalidArgumentException('Payment idempotency key must contain at most 100 characters.');
                }
                $existingJournal = \App\Models\Accounting\JournalEntry::forCompany($context)
                    ->where('idempotency_key', 'settlement:'.$data['idempotency_key'])->first();
                if ($existingJournal) {
                    $existing = Payment::visibleIn($context)->whereKey($existingJournal->reference_id)->first();
                    if (!$existing || (int) $existing->{$type.'_id'} !== (int) $source->id
                        || \App\Support\LedgerAmount::units((float) $existing->amount) !== \App\Support\LedgerAmount::units($amount)
                        || $existing->paying_method !== ($data['paying_method'] ?? 'Cash')
                        || (int) $existing->account_id !== $accountId || $existing->payment_at->toDateString() !== $date) {
                        throw new InvalidArgumentException('Payment key was already used for a different settlement.');
                    }
                    return $existing;
                }
            }
            if ($source->reversed_at || ($source->branch_id && !$source->posted_at)) {
                throw new InvalidArgumentException('Only an active posted document can receive payment.');
            }
            if ($source instanceof Purchase && (int) $source->status === 4) {
                throw new InvalidArgumentException('An unbilled purchase order cannot receive payment here.');
            }
            $outstanding = round((float) $source->grand_total - (float) $source->paid_amount, 4);
            if ($amount > $outstanding) {
                throw new InvalidArgumentException('Payment exceeds the outstanding document amount.');
            }
            if ($date < $source->created_at->toDateString()) {
                throw new InvalidArgumentException('Payment date cannot precede the document date.');
            }
            $numbers = app(DocumentNumberService::class);
            $reservation = $numbers->reserve($type.'_payment', $context, $date, $actor);
            $payment = (new Payment)->forceFill([
                'company_id' => $context->companyId, 'created_at' => $date, 'payment_at' => $date,
                'payment_reference' => $reservation->formatted_number, 'user_id' => $actor,
                $type.'_id' => $source->id, 'account_id' => $accountId, 'amount' => $amount, 'change' => 0,
                'paying_method' => $data['paying_method'] ?? 'Cash', 'payment_note' => $data['payment_note'] ?? null,
            ]);
            $payment->save();
            $numbers->assign($reservation, $payment);
            $paidAmount = round((float) $source->paid_amount + $amount, 4);
            $source->update(['paid_amount' => $paidAmount, 'payment_status' => $paidAmount >= (float) $source->grand_total ? 4 : 3]);
            $this->accountingService->postPaymentJournal($payment, $context, $actor, $data['idempotency_key'] ?? null);

            return $payment;
        });
    }
}
