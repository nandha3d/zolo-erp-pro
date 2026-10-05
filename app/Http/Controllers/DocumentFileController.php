<?php

namespace App\Http\Controllers;

use App\Models\Adjustment;
use App\Models\Delivery;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\Quotation;
use App\Models\ReturnPurchase;
use App\Models\Returns;
use App\Models\Sale;
use App\Models\Transfer;
use App\Models\Employee;
use App\Models\Supplier;
use App\Services\Commercial\CommercialPermission;
use App\Services\Documents\NotificationFileAccess;
use App\Services\Documents\PrivateFileStorage;
use App\Services\Platform\BranchAccess;
use App\Services\Platform\CompanyContext;
use App\Services\Platform\CompanyContextResolver;
use Illuminate\Support\Facades\DB;
use Modules\Manufacturing\Entities\Production;

/**
 * Attachments of company documents are not served straight from public/documents (the web server refuses
 * those paths). A file is delivered only to a signed-in member of the owning company, and only when a
 * document of that company, found through the company-scoped model, actually references it.
 */
class DocumentFileController extends Controller
{
    /** folder => [model, column, permission] */
    private const FOLDERS = [
        'sale' => [Sale::class, 'document', 'sales-index'],
        'purchase' => [Purchase::class, 'document', 'purchases-index'],
        'sale_return' => [Returns::class, 'document', 'returns-index'],
        'purchase_return' => [ReturnPurchase::class, 'document', 'returns-index'],
        'quotation' => [Quotation::class, 'document', 'quotes-index'],
        'expense' => [Expense::class, 'document', 'expenses-index'],
        'delivery' => [Delivery::class, 'file', 'delivery'],
        'transfer' => [Transfer::class, 'document', 'transfers-index'],
        'adjustment' => [Adjustment::class, 'document', 'adjustment'],
        'add-payment' => [Payment::class, 'document', ''],
        'production' => [Production::class, 'document', 'manufacturing.read'],
        'employee' => [Employee::class, 'image', 'employees-index'],
        'sale_agent' => [Employee::class, 'image', 'employees-index'],
        'supplier' => [Supplier::class, 'image', 'suppliers-index'],
    ];

    public function show(string $folder, string $file)
    {
        abort_unless(auth()->check(), 401);
        $context = request()->attributes->get(CompanyContext::class);
        abort_unless($context instanceof CompanyContext, 403);
        $context = app(CompanyContextResolver::class)->forActor($context);
        $storage = app(PrivateFileStorage::class);
        abort_unless($storage->validFilename($file), 404);
        if ($folder === 'notification') {
            $references = DB::table('notifications')->where('data->document_name', $file)->get();
            abort_unless($references->isNotEmpty() && $references->every(fn ($row) => app(NotificationFileAccess::class)
                ->canRead($row, $context, auth()->id())), 404);
        } else {
            abort_unless(isset(self::FOLDERS[$folder]), 404);
            [$model, $column, $permission] = self::FOLDERS[$folder];
            if ($permission !== '') {
                app(CommercialPermission::class)->assert($permission, $context, auth()->id());
            }
            $connection = DB::connection();
            $query = new \Illuminate\Database\Query\Builder($connection, $connection->getQueryGrammar(), $connection->getPostProcessor());
            $rows = $query->from((new $model)->getTable())->where($column, $file)->get();
            $records = $model::hydrate($rows->map(fn ($row) => (array) $row)->all());
            // A shared legacy filename cannot prove which upload survived an overwrite. Fail closed if any
            // reference is foreign, deleted or unauthorized; never let an owned reference grant foreign content.
            abort_unless($records->isNotEmpty() && $records->every(function ($record) use ($folder, $context) {
                return ($folder === 'production' || (int) $record->company_id === $context->companyId)
                    && !$record->deleted_at
                    && (!in_array($folder, ['employee', 'sale_agent', 'supplier'], true) || $record->is_active)
                    && $this->authorizedRecord($record, $context);
            }), 404);
        }
        $path = $storage->path($folder, $file);
        abort_unless($path !== null, 404);

        $headers = ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'];
        if (in_array($folder, ['employee', 'sale_agent', 'supplier'], true)) {
            abort_unless(in_array(mime_content_type($path), ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true), 404);
            return response()->file($path, $headers);
        }
        return response()->download($path, $file, $headers);
    }

    private function authorizedRecord($record, CompanyContext $context): bool
    {
        try {
            $branches = app(BranchAccess::class);
            if ($record instanceof Delivery) {
                return $record->sale !== null && $this->authorizedRecord($record->sale, $context);
            }
            if ($record instanceof Payment) {
                $linked = false;
                foreach (['sale', 'purchase'] as $source) {
                    if ($record->{$source.'_id'} && (!$record->$source || !$this->authorizedRecord($record->$source, $context))) {
                        return false;
                    }
                    if ($record->{$source.'_id'}) {
                        app(CommercialPermission::class)->assert($source === 'sale' ? 'sales-index' : 'purchases-index', $context, auth()->id());
                        $linked = true;
                    }
                }
                if (!$linked) {
                    app(CommercialPermission::class)->assert('accounting.reports.view', $context, auth()->id());
                }
            }
            foreach (['warehouse_id', 'from_warehouse_id', 'to_warehouse_id'] as $column) {
                if ($record->$column) {
                    $branches->assertWarehouse((int) $record->$column, $context);
                }
            }
            if ($record instanceof Production && !$record->warehouse_id) {
                return false;
            }
            if ($record->branch_id && !in_array((int) $record->branch_id, $branches->authorizedBranchIds($context), true)) {
                return false;
            }

            return true;
        } catch (\Illuminate\Auth\Access\AuthorizationException|\Illuminate\Validation\ValidationException $exception) {
            return false;
        }
    }
}
