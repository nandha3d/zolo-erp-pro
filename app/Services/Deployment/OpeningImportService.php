<?php

namespace App\Services\Deployment;

use App\Models\ImportBatch;
use App\Models\Product;
use App\Models\Accounting\ChartOfAccount;
use App\Services\Accounting\AccountingPostingService;
use App\Services\Accounting\LedgerReconciliationService;
use App\Services\Accounting\SemanticAccountResolver;
use App\Services\ERP\CompanyWriteGuard;
use App\Services\Inventory\InventoryMovementService;
use App\Services\Inventory\InventoryReconciliationService;
use App\Services\Inventory\StockLine;
use App\Services\Inventory\StockMovementCommand;
use App\Services\Platform\CompanyContext;
use App\Services\Platform\CompanyContextResolver;
use App\Support\LedgerAmount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Opening-only migration into a clean target; validation executes the exact posting path then rolls it back. */
class OpeningImportService
{
    public function stage(array $source, CompanyContext $context, int $actor): ImportBatch
    {
        $context = $this->authorize($context, $actor);
        if (array_diff(array_keys($source), ['batch_key', 'source_name', 'business_date', 'mappings', 'rows', 'expected'])) {
            throw ValidationException::withMessages(['source' => 'Unexpected source fields. Use the reviewed opening schema.']);
        }
        Validator::make($source, ['batch_key' => 'required|string|max:100', 'source_name' => 'required|string|max:100',
            'business_date' => 'required|date_format:Y-m-d', 'mappings' => 'required|array|min:1|max:20000',
            'mappings.*' => 'array:entity,source_key,target_id', 'mappings.*.entity' => 'required|in:product,warehouse,customer,supplier,account',
            'mappings.*.source_key' => 'required|string|max:100', 'mappings.*.target_id' => 'required|integer|min:1',
            'rows' => 'required|array|min:1|max:10000', 'rows.*.source_key' => 'required|string|max:100|distinct',
            'rows.*' => 'array:source_key,kind,payload', 'rows.*.kind' => 'required|in:stock,receivable,payable,journal', 'rows.*.payload' => 'required|array',
            'expected' => 'required|array:stock_qty,stock_value,ar,ap', 'expected.stock_qty' => 'required|numeric|min:0', 'expected.stock_value' => 'required|numeric|min:0',
            'expected.ar' => 'required|numeric', 'expected.ap' => 'required|numeric',
        ])->validate();
        return DB::transaction(function () use ($source, $context, $actor) {
            \App\Models\Company::whereKey($context->companyId)->lockForUpdate()->firstOrFail();
            $hash = $this->hash($source);
            $existing = ImportBatch::forCompany($context)->where('batch_key', $source['batch_key'])->first();
            if ($existing) {
                if ($existing->source_hash !== $hash || (int) $existing->branch_id !== $context->branchId || (int) $existing->financial_year_id !== $context->financialYearId) {
                    throw ValidationException::withMessages(['batch_key' => 'Batch key already identifies different source data or context.']);
                }
                return $existing;
            }
            $batch = ImportBatch::create(['company_id' => $context->companyId, 'branch_id' => $context->branchId,
                'financial_year_id' => $context->financialYearId, 'actor_id' => $actor, 'batch_key' => $source['batch_key'],
                'source_name' => $source['source_name'], 'business_date' => $source['business_date'], 'source_hash' => $hash, 'expected_json' => $source['expected']]);
            $seen = [];
            foreach ($source['mappings'] as $map) {
                $key = $map['entity'].':'.$map['source_key'];
                if (isset($seen[$key])) throw ValidationException::withMessages(['mappings' => 'Resolve duplicate or ambiguous source mappings before staging.']);
                $seen[$key] = true;
                $this->ownedMapping($map['entity'], $map['target_id'], $context, $actor);
                DB::table('import_mappings')->insert(['batch_id' => $batch->id] + $map);
            }
            foreach ($source['rows'] as $row) DB::table('import_rows')->insert(['batch_id' => $batch->id,
                'source_key' => $row['source_key'], 'kind' => $row['kind'], 'payload_json' => json_encode($row['payload'], JSON_THROW_ON_ERROR)]);
            return $batch->refresh();
        });
    }

    public function validate(int $id, CompanyContext $context, int $actor): ImportBatch
    {
        $context = $this->authorize($context, $actor);
        return DB::transaction(function () use ($id, $context, $actor) {
            \App\Models\Company::whereKey($context->companyId)->lockForUpdate()->firstOrFail();
            $batch = $this->batch($id, $context);
            if ($batch->status === 'committed') return $batch;
            DB::beginTransaction();
            try { $summary = $this->execute($batch, $context, $actor); $errors = []; }
            catch (\Throwable $error) {
                $summary = null;
                $errors = $error instanceof ValidationException ? $error->errors() : ['source' => 'Opening validation failed. Review owned mappings, dates, stock identities, balanced accounts and source totals.'];
            } finally { DB::rollBack(); }
            $batch->refresh()->forceFill(['status' => $errors ? 'staged' : 'validated', 'validated_at' => $errors ? null : now(),
                'summary_json' => $summary, 'validation_errors' => $errors])->save();
            return $batch;
        });
    }

    public function commit(int $id, CompanyContext $context, int $actor): ImportBatch
    {
        $context = $this->authorize($context, $actor);
        return DB::transaction(function () use ($id, $context, $actor) {
            \App\Models\Company::whereKey($context->companyId)->lockForUpdate()->firstOrFail();
            $batch = $this->batch($id, $context);
            if ($batch->status === 'committed') return $batch;
            if ($batch->status !== 'validated') throw ValidationException::withMessages(['batch' => 'Validate the opening batch before committing.']);
            $summary = $this->execute($batch, $context, $actor);
            $batch->forceFill(['status' => 'committed', 'summary_json' => $summary, 'committed_at' => now()])->save();
            return $batch;
        });
    }

    private function execute(ImportBatch $batch, CompanyContext $context, int $actor): array
    {
        $rows = DB::table('import_rows')->where('batch_id', $batch->id)->orderBy('id')->get();
        $maps = DB::table('import_mappings')->where('batch_id', $batch->id)->orderBy('id')->get();
        $source = ['batch_key' => $batch->batch_key, 'source_name' => $batch->source_name, 'business_date' => $batch->business_date,
            'mappings' => $maps->map(fn ($m) => ['entity' => $m->entity, 'source_key' => $m->source_key, 'target_id' => $m->target_id])->all(),
            'rows' => $rows->map(fn ($r) => ['source_key' => $r->source_key, 'kind' => $r->kind, 'payload' => json_decode($r->payload_json, true)])->all(),
            'expected' => $batch->expected_json];
        if (!hash_equals($batch->source_hash, $this->hash($source))) throw ValidationException::withMessages(['source' => 'Staged source data changed after review. Stage a new batch.']);
        $guard = app(CompanyWriteGuard::class); $date = $guard->begin($context, $batch->business_date);
        foreach ($maps as $map) $this->ownedMapping($map->entity, $map->target_id, $context, $actor);
        if (DB::table('journal_entries')->where('company_id', $context->companyId)->where('status', 'posted')->exists()
            || DB::table('stock_movements')->where('company_id', $context->companyId)->exists()
            || DB::table('chart_of_accounts')->where('company_id', $context->companyId)->where(fn ($q) => $q->where('opening_balance', '!=', 0)->orWhere('current_balance', '!=', 0))->exists()
            || DB::table('products')->where('company_id', $context->companyId)->where('qty', '!=', 0)->exists()) {
            throw ValidationException::withMessages(['target' => 'Opening-only migration requires a clean company stock and accounting target.']);
        }
        $batch->forceFill(['status' => 'committing'])->save();
        $lookup = $maps->keyBy(fn ($m) => $m->entity.':'.$m->source_key);
        $resolve = function (string $entity, string $key) use ($lookup): int {
            if (!$map = $lookup->get($entity.':'.$key)) throw ValidationException::withMessages(['mapping' => 'Every source key needs an explicit reviewed target mapping.']);
            return (int) $map->target_id;
        };
        $summary = ['stock_qty' => 0, 'stock_value' => 0, 'ar' => 0, 'ap' => 0, 'rows' => $rows->count()];
        foreach ($rows as $row) {
            $data = json_decode($row->payload_json, true, 512, JSON_THROW_ON_ERROR);
            $key = 'import:'.$batch->id.':'.$row->id;
            if ($row->kind === 'stock') {
                Validator::make($data, ['product_key' => 'required|string', 'warehouse_key' => 'required|string',
                    'qty' => 'required|numeric|gt:0', 'unit_cost' => 'required|numeric|min:0', 'offset_account_key' => 'required|string'])->validate();
                $product = $resolve('product', $data['product_key']); $warehouse = $resolve('warehouse', $data['warehouse_key']);
                $stock = app(InventoryMovementService::class)->importOpening(new StockMovementCommand(date: $date,
                    lines: [StockLine::fromArray(array_diff_key($data, array_flip(['uom_id', 'product_id', 'warehouse_id', 'batch_id', 'product_batch_id', 'stock_identity_id'])) + ['product_id' => $product, 'warehouse_id' => $warehouse])],
                    warehouseId: $warehouse, sourceType: 'import_batch', sourceId: $batch->id, sourceNo: $row->source_key,
                    idempotencyKey: $key, context: $context, userId: $actor, reason: 'Reviewed opening source '.$batch->source_name));
                $value = LedgerAmount::units($stock->lines()->sum('value'));
                $summary['stock_qty'] += LedgerAmount::units($stock->lines()->sum('qty_base')); $summary['stock_value'] += $value;
                if ($value) $this->journal($batch, $row, $date, $key, [[
                    'chart_of_account_id' => app(SemanticAccountResolver::class)->resolve('inventory', $context)->id, 'debit' => LedgerAmount::decimal($value),
                ], ['chart_of_account_id' => $this->offset($resolve('account', $data['offset_account_key']), $context), 'credit' => LedgerAmount::decimal($value)]], $context, $actor);
            } elseif (in_array($row->kind, ['receivable', 'payable'])) {
                Validator::make($data, ['party_key' => 'required|string', 'amount' => 'required|numeric',
                    'offset_account_key' => 'required|string', 'due_date' => 'required|date_format:Y-m-d', 'document_date' => 'required|date_format:Y-m-d|before_or_equal:'.$date])->validate();
                $role = $row->kind === 'receivable' ? 'ar' : 'ap'; $party = $role === 'ar' ? 'customer' : 'supplier';
                $amount = LedgerAmount::units($data['amount']);
                if (!$amount) throw ValidationException::withMessages(['amount' => 'Opening amount must be nonzero.']);
                $summary[$role] += $amount;
                $debit = ($role === 'ar') === ($amount > 0);
                $this->journal($batch, $row, $date, $key, [[
                    'chart_of_account_id' => app(SemanticAccountResolver::class)->resolve($role, $context)->id,
                    $debit ? 'debit' : 'credit' => LedgerAmount::decimal(abs($amount)), 'partner_type' => $party, 'partner_id' => $resolve($party, $data['party_key']),
                ], ['chart_of_account_id' => $this->offset($resolve('account', $data['offset_account_key']), $context),
                    $debit ? 'credit' : 'debit' => LedgerAmount::decimal(abs($amount))]], $context, $actor, $data['due_date'], $data['document_date']);
            } else {
                Validator::make($data, ['items' => 'required|array|min:2|max:100', 'items.*.account_key' => 'required|string',
                    'items.*.debit' => 'nullable|numeric|min:0', 'items.*.credit' => 'nullable|numeric|min:0'])->validate();
                $items = [];
                foreach ($data['items'] as $line) $items[] = ['chart_of_account_id' => $this->offset($resolve('account', $line['account_key']), $context, true),
                    'debit' => $line['debit'] ?? 0, 'credit' => $line['credit'] ?? 0];
                $this->journal($batch, $row, $date, $key, $items, $context, $actor);
            }
        }
        foreach (['stock_qty', 'stock_value', 'ar', 'ap'] as $field) {
            if ($summary[$field] !== LedgerAmount::units($batch->expected_json[$field])) throw ValidationException::withMessages(['expected.'.$field => 'Posted opening totals differ from the reviewed source snapshot.']);
            $summary[$field] = LedgerAmount::decimal($summary[$field]);
        }
        if (app(InventoryReconciliationService::class)->differences($context->companyId)
            || !app(LedgerReconciliationService::class)->reconcile($context, false, $actor)['is_reconciled']) {
            throw ValidationException::withMessages(['reconciliation' => 'Opening stock, journals or control accounts do not reconcile.']);
        }
        $inventory = app(SemanticAccountResolver::class)->resolve('inventory', $context);
        if (LedgerAmount::units($inventory->fresh()->current_balance) !== LedgerAmount::units($summary['stock_value'])) throw ValidationException::withMessages(['inventory_value' => 'Opening inventory accounting differs from stock value.']);
        return $summary + ['reconciled' => true];
    }

    private function journal(ImportBatch $batch, object $row, string $date, string $key, array $items, CompanyContext $context, int $actor, ?string $due = null, ?string $originalDate = null): void
    {
        app(AccountingPostingService::class)->postJournalEntry(['reference_type' => 'migration_opening', 'reference_id' => $batch->id,
            'reference_no' => $row->source_key, 'entry_date' => $date, 'due_date' => $due, 'source_document_date' => $originalDate, 'description' => 'Reviewed opening '.$batch->source_name,
            'posting_key' => $key, 'idempotency_key' => $key, 'created_by' => $actor], $items, $context);
    }

    private function offset(int $id, CompanyContext $context, bool $journalLine = false): int
    {
        $account = ChartOfAccount::forCompany($context)->findOrFail($id);
        if (!in_array($account->control_type, $journalLine ? ['none', 'cash', 'bank'] : ['none'], true) || !$account->is_active || $account->children()->exists()) {
            throw ValidationException::withMessages(['offset_account' => 'Opening offsets require active non-control leaf accounts. Journal lines also allow cash/bank; AR/AP require invoice rows.']);
        }
        return $id;
    }

    private function ownedMapping(string $entity, int $id, CompanyContext $context, int $actor): void
    {
        $guard = app(CompanyWriteGuard::class);
        if ($entity === 'warehouse') { $guard->warehouse($id, $context, $actor); return; }
        $model = match ($entity) { 'product' => Product::class, 'customer' => \App\Models\Customer::class, 'supplier' => \App\Models\Supplier::class, 'account' => ChartOfAccount::class };
        $guard->owned($model, $id, $context, 'mappings');
    }

    private function authorize(CompanyContext $context, int $actor): CompanyContext
    {
        $context = app(CompanyContextResolver::class)->forActor($context, $actor);
        if (!app(CompanyContextResolver::class)->canManageFinancialYears($actor, $context->companyId)) throw new \Illuminate\Auth\Access\AuthorizationException('Company administrator required for reviewed opening imports.');
        return $context;
    }

    private function batch(int $id, CompanyContext $context): ImportBatch
    {
        return ImportBatch::forCompany($context)->where('branch_id', $context->branchId)->where('financial_year_id', $context->financialYearId)->lockForUpdate()->findOrFail($id);
    }

    private function hash(array $source): string
    {
        $normalize = function ($value) use (&$normalize) {
            if (!is_array($value)) return is_numeric($value) ? (string) $value : $value;
            if (!array_is_list($value)) ksort($value);
            return array_map($normalize, $value);
        };
        return hash('sha256', json_encode($normalize($source), JSON_THROW_ON_ERROR));
    }
}
