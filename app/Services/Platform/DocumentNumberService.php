<?php

namespace App\Services\Platform;

use App\Models\DocumentNumberReservation;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Transfer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use LogicException;

class DocumentNumberService
{
    private const TYPES = [
        'production' => [\App\Models\Operations\ProductionOrder::class, 'reference_no', 'PROD'],
        'job_work_order' => [\App\Models\Operations\JobWorkOrder::class, 'reference_no', 'JWO'],
        'job_work_dispatch' => [\App\Models\Operations\JobWorkDispatch::class, 'reference_no', 'JWD'],
        'job_work_receipt' => [\App\Models\Operations\JobWorkReceipt::class, 'reference_no', 'JWR'],
        'sale' => [Sale::class, 'reference_no', 'SAL'],
        'purchase' => [Purchase::class, 'reference_no', 'PUR'],
        'transfer' => [Transfer::class, 'reference_no', 'TRF'],
        'sale_payment' => [Payment::class, 'payment_reference', 'REC'],
        'purchase_payment' => [Payment::class, 'payment_reference', 'PAY'],
        'journal' => [\App\Models\Accounting\JournalEntry::class, 'entry_number', 'JE'],
        'sale_credit_note' => [\App\Models\Returns::class, 'reference_no', 'SCN'],
        'sale_debit_note' => [\App\Models\Returns::class, 'reference_no', 'SDN'],
        'purchase_debit_note' => [\App\Models\ReturnPurchase::class, 'reference_no', 'PDN'],
        'purchase_credit_note' => [\App\Models\ReturnPurchase::class, 'reference_no', 'PCN'],
        'damage' => [\App\Models\DamageStock::class, 'reference_no', 'LOSS'],
        'exchange' => [\App\Models\Exchange::class, 'reference_no', 'EXC'],
        'adjustment' => [\App\Models\Adjustment::class, 'reference_no', 'ADJ'],
        'expense' => [\App\Models\Expense::class, 'reference_no', 'EXP'],
        'quotation' => [\App\Models\Quotation::class, 'reference_no', 'QUO'],
        'delivery' => [\App\Models\Delivery::class, 'reference_no', 'DEL'],
        'income' => [\App\Models\Income::class, 'reference_no', 'INC'],
        'money_transfer' => [\App\Models\MoneyTransfer::class, 'reference_no', 'MTR'],
        'payroll' => [\App\Models\Payroll::class, 'reference_no', 'PAYR'],
    ];

    /** Call inside the document transaction. A failed posting rolls back reservation and increment. */
    public function reserve(string $type, CompanyContext $context, string $businessDate, int $actor, ?string $code = null): DocumentNumberReservation
    {
        $this->requireTransaction();
        if (!isset(self::TYPES[$type])) {
            throw ValidationException::withMessages(['document_type' => 'Unsupported document type.']);
        }
        $resolver = app(CompanyContextResolver::class);
        $context = $resolver->forActor($context, $actor);
        // Consistent company/FY/series lock order also serializes first-series creation.
        $company = \App\Models\Company::whereKey($context->companyId)->lockForUpdate()->firstOrFail();
        $gstNumber = config('compliance.enabled') && $company->country_code === 'IN'
            && in_array($type, ['sale', 'sale_credit_note', 'sale_debit_note'], true)
            && \Illuminate\Support\Facades\Schema::hasTable('tax_registrations')
            && DB::table('tax_registrations')->where('company_id', $context->companyId)->where('branch_id', $context->branchId)
                ->where('status', 'active')->where('effective_from', '<=', $businessDate)
                ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $businessDate))->exists();
        $resolver->assertPostingDate($context, $businessDate);
        $scope = [
            'company_id' => $context->companyId, 'branch_id' => $context->branchId,
            'financial_year_id' => $context->financialYearId, 'document_type' => $type,
        ];
        $query = DB::table('document_series')->where($scope);
        $series = ($code === null ? $query->where('is_default', true) : $query->where('code', $code))->lockForUpdate()->first();
        if (!$series) {
            if ($code !== null) {
                throw ValidationException::withMessages(['series' => 'Series does not belong to this document context.']);
            }
            if (DB::table('document_series')->where($scope)->exists()) {
                throw ValidationException::withMessages(['series' => 'Configure a default series for this document context.']);
            }
            $branch = \App\Models\CompanyBranch::whereKey($context->branchId)->firstOrFail();
            $prefix = 'ERP-'.self::TYPES[$type][2].'-'.$company->id.'-'.$branch->id.'-'.$context->financialYearId.'-';
            if ($gstNumber) $prefix = ($type === 'sale' ? 'S' : self::TYPES[$type][2]).$branch->id.'-'.$context->financialYearId.'-';
            $id = DB::table('document_series')->insertGetId($scope + [
                'code' => 'MAIN', 'prefix' => $prefix, 'suffix' => '', 'next_number' => 1,
                'padding' => 6, 'reset_policy' => 'financial_year', 'is_default' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $series = DB::table('document_series')->where('id', $id)->lockForUpdate()->first();
        }
        if ($series->reset_policy !== 'financial_year' || $series->padding < 1 || $series->padding > 18
            || $series->next_number < 1 || $series->next_number >= 999999999999999) {
            throw ValidationException::withMessages(['series' => 'Series configuration or number range is invalid.']);
        }
        $number = $series->prefix.str_pad((string) $series->next_number, $series->padding, '0', STR_PAD_LEFT).$series->suffix;
        if ($gstNumber && !preg_match('/^[A-Za-z0-9\/-]{1,16}$/D', $number)) {
            throw ValidationException::withMessages(['series' => 'GST invoices and notes require at most 16 letters, digits, slashes or hyphens. Review the existing series; retained numbers are never rewritten.']);
        }
        if (strlen($number) > ($type === 'journal' ? 50 : 100)) {
            throw ValidationException::withMessages(['series' => 'Formatted document number exceeds the source or linked journal reference width.']);
        }
        [$sourceClass, $referenceField] = self::TYPES[$type];
        $existing = (new $sourceClass)->newQueryWithoutScopes()->where('company_id', $context->companyId)->where($referenceField, $number)->exists();
        if ($existing) {
            throw ValidationException::withMessages(['series' => 'Document number already exists in retained history.']);
        }
        DB::table('document_series')->where('id', $series->id)->update(['next_number' => $series->next_number + 1, 'updated_at' => now()]);
        return DocumentNumberReservation::create([
            'series_id' => $series->id, 'company_id' => $context->companyId,
            'document_type' => $type, 'reserved_number' => $series->next_number,
            'formatted_number' => $number, 'status' => 'reserved',
        ]);
    }

    /** Bind once to a persisted source; reprints read its existing number without allocating. */
    public function assign(DocumentNumberReservation $reservation, Model $source): void
    {
        $this->requireTransaction();
        $reservation = DocumentNumberReservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();
        [$class, $field] = self::TYPES[$reservation->document_type];
        $persisted = $source instanceof $class && $source->exists ? $source->fresh() : null;
        if (!$persisted || (int) $persisted->company_id !== $reservation->company_id
            || $persisted->$field !== $reservation->formatted_number) {
            throw new AuthorizationException('Document does not match the reserved number and company.');
        }
        if ($reservation->status === 'assigned' && $reservation->source_type === $class
            && (int) $reservation->source_id === (int) $source->getKey()) {
            return;
        }
        if ($reservation->status !== 'reserved' || $reservation->source_id !== null) {
            throw ValidationException::withMessages(['reservation' => 'Document number has already been assigned.']);
        }
        $reservation->update(['source_type' => $class, 'source_id' => $source->getKey(), 'status' => 'assigned']);
    }

    /** Configure an unused series. Used formats/counters remain immutable to preserve history. */
    public function configure(CompanyContext $context, array $data, int $actor): int
    {
        $resolver = app(CompanyContextResolver::class);
        $context = $resolver->forActor($context, $actor);
        if (!$resolver->canManageFinancialYears($actor, $context->companyId)) {
            throw new AuthorizationException('Company administrator required to configure series.');
        }
        $data = Validator::make($data, [
            'document_type' => 'required|in:'.implode(',', array_keys(self::TYPES)),
            'code' => 'required|string|max:50|regex:/^[A-Z0-9_-]+$/D',
            'prefix' => 'sometimes|string|max:100', 'suffix' => 'sometimes|string|max:50',
            'padding' => 'sometimes|integer|min:1|max:18',
            'next_number' => 'sometimes|integer|min:1|max:999999999999998',
            'is_default' => 'sometimes|boolean',
        ])->validate();
        return DB::transaction(function () use ($context, $data) {
            \App\Models\Company::whereKey($context->companyId)->lockForUpdate()->firstOrFail();
            $scope = ['company_id' => $context->companyId, 'branch_id' => $context->branchId,
                'financial_year_id' => $context->financialYearId, 'document_type' => $data['document_type']];
            $existing = DB::table('document_series')->where($scope)->where('code', $data['code'])->lockForUpdate()->first();
            if ($existing && DB::table('document_number_reservations')->where('series_id', $existing->id)->exists()) {
                throw ValidationException::withMessages(['series' => 'Used series cannot be reconfigured. Create a new series code.']);
            }
            $default = $data['is_default'] ?? true;
            if ($default) {
                DB::table('document_series')->where($scope)->update(['is_default' => false, 'updated_at' => now()]);
            }
            $values = ['prefix' => $data['prefix'] ?? '', 'suffix' => $data['suffix'] ?? '',
                'padding' => $data['padding'] ?? 6, 'next_number' => $data['next_number'] ?? 1,
                'reset_policy' => 'financial_year', 'is_default' => $default, 'updated_at' => now()];
            if ($existing) {
                DB::table('document_series')->where('id', $existing->id)->update($values);
                return (int) $existing->id;
            }
            return DB::table('document_series')->insertGetId($scope + ['code' => $data['code'], 'created_at' => now()] + $values);
        }, 3);
    }

    private function requireTransaction(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Document numbering requires the document posting transaction.');
        }
    }
}
