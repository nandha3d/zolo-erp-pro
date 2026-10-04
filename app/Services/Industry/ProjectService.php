<?php

namespace App\Services\Industry;

use App\Models\Customer;
use App\Models\Inventory\StockIdentity;
use App\Models\Operations\Bom;
use App\Models\Operations\Project;
use App\Models\Operations\ProjectInstallation;
use App\Models\Operations\SerialReservation;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\Commercial\SaleApplicationService;
use App\Services\Commercial\SaleCommand;
use App\Services\ERP\CompanyWriteGuard;
use App\Services\Inventory\InventoryMovementService;
use App\Services\Inventory\StockLine;
use App\Services\Operations\OperationPosting;
use App\Services\Operations\OperationStock;
use App\Services\Platform\CompanyContext;
use App\Support\LedgerAmount;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Extends existing projects and links shared documents; installed serials keep their full history. */
class ProjectService
{
    public function create(array $data, string $key, CompanyContext $context, int $actor): Project
    {
        return app(OperationPosting::class)->run('project', Project::class, 'operations.projects', 'projects.manage',
            $data, $key, $context, $actor, function ($data, $date) use ($context, $actor) {
                validator($data, ['title' => 'required|string|max:191', 'client_id' => 'required|integer|min:1', 'site_address' => 'required|string|max:2000',
                    'contact' => 'nullable|string|max:255', 'system_kw' => 'required|numeric|gt:0|max:1000000', 'site_notes' => 'nullable|string|max:5000',
                    'budget' => 'sometimes|numeric|min:0', 'deadline' => 'nullable|date_format:Y-m-d|after_or_equal:business_date',
                    'survey_attachments' => 'sometimes|array|max:20', 'survey_attachments.*' => 'string|max:500'])->validate();
                app(CompanyWriteGuard::class)->owned(Customer::class, $data['client_id'], $context, 'client_id');
                // References are company-owned storage keys, never arbitrary file paths or remote fetches.
                foreach ($data['survey_attachments'] ?? [] as $path) {
                    if (!str_starts_with($path, 'companies/'.$context->companyId.'/') || str_contains($path, '..')) {
                        throw ValidationException::withMessages(['survey_attachments' => 'Use company-owned survey storage keys.']);
                    }
                }
                $site = Warehouse::forceCreate(['name' => 'Site: '.$data['title'], 'address' => $data['site_address'], 'is_active' => true,
                    'company_id' => $context->companyId, 'branch_id' => $context->branchId]);
                $category = DB::table('project_categories')->where('name', 'Site / Project')->value('id');
                $category ??= DB::table('project_categories')->insertGetId(['name' => 'Site / Project', 'created_at' => now(), 'updated_at' => now()]);
                return Project::create(app(OperationPosting::class)->header($context, $actor) + ['title' => $data['title'], 'category_id' => $category,
                    'client_id' => $data['client_id'], 'start_date' => $date, 'deadline' => $data['deadline'] ?? null, 'budget' => $data['budget'] ?? 0,
                    'site_warehouse_id' => $site->id, 'status' => 'Not Started', 'site_json' => array_intersect_key($data, array_flip([
                        'site_address', 'contact', 'system_kw', 'site_notes', 'survey_attachments'])), 'posted_at' => now()]);
            });
    }

    public function kitLines(int $bomId, float $qty, string $date, CompanyContext $context, int $actor): array
    {
        app(OperationPosting::class)->authorize('manufacturing.bom', 'projects.manage', $context, $actor);
        $bom = Bom::forCompany($context)->where('status', 'published')->findOrFail($bomId);
        if ($qty <= 0 || !is_finite($qty) || $date < $bom->effective_from || ($bom->effective_to && $date > $bom->effective_to)) {
            throw ValidationException::withMessages(['bom_id' => 'Select an effective system template with positive quantity.']);
        }
        $lines = [];
        foreach (DB::table('bom_lines')->where('bom_id', $bomId)->orderBy('line_no')->get() as $component) {
            $product = Product::forCompany($context)->where('is_active', true)->findOrFail($component->component_product_id);
            $lines[] = ['product_id' => $product->id, 'qty' => round($component->qty * $qty / $bom->output_qty, 4),
                'sale_unit_id' => $component->uom_id, 'variant_id' => $component->variant_id,
                'net_unit_price' => round($product->price * app(\App\Services\Inventory\UomConversionService::class)->toBase($product, 1, (int) $component->uom_id), 4),
                'attributes' => ['system_bom_id' => $bom->id, 'system_bom_version' => $bom->version]];
        }
        return $lines;
    }

    public function quotation(int $projectId, array $data, string $key, CompanyContext $context, int $actor): Project
    {
        $data['project_id'] = $projectId;
        return app(OperationPosting::class)->run('project_quotation', Project::class, 'operations.projects', 'projects.manage',
            $data, $key, $context, $actor, function ($data, $date) use ($projectId, $key, $context, $actor) {
                validator($data, ['bom_id' => 'required|integer|min:1', 'qty' => 'required|numeric|gt:0', 'warehouse_id' => 'required|integer|min:1'])->validate();
                $project = Project::visibleIn($context)->findOrFail($projectId);
                $items = $this->kitLines($data['bom_id'], (float) $data['qty'], $date, $context, $actor);
                $sale = app(SaleApplicationService::class)->create(new SaleCommand(['customer_id' => $project->client_id,
                    'warehouse_id' => $data['warehouse_id'], 'business_date' => $date, 'sale_status' => 2,
                    'items' => $items, 'sale_note' => 'Project '.$project->title], 'project-quote:'.$key, $actor, $context));
                $this->link($projectId, 'sale', $sale->id, $context, $actor);
                return $project;
            });
    }

    public function link(int $projectId, string $type, int $id, CompanyContext $context, int $actor): void
    {
        app(OperationPosting::class)->authorize('operations.projects', 'projects.manage', $context, $actor);
        DB::transaction(function () use ($projectId, $type, $id, $context, $actor) {
            app(CompanyWriteGuard::class)->begin($context, null);
            $project = Project::visibleIn($context)->lockForUpdate()->findOrFail($projectId);
            $model = match ($type) { 'sale' => \App\Models\Sale::class, 'purchase' => \App\Models\Purchase::class,
                'expense' => \App\Models\Expense::class, default => throw ValidationException::withMessages(['source_type' => 'Link a shared sale, purchase or expense.']) };
            $source = in_array($type, ['sale', 'purchase'], true) ? $model::visibleIn($context)->findOrFail($id)
                : app(CompanyWriteGuard::class)->owned($model, $id, $context, 'source_id');
            if ($type === 'sale' && (int) $source->customer_id !== (int) $project->client_id) {
                throw ValidationException::withMessages(['source_id' => 'Project sales must belong to the project customer.']);
            }
            if ($type === 'expense' && $source->warehouse_id) app(CompanyWriteGuard::class)->warehouse($source->warehouse_id, $context, $actor);
            $existing = DB::table('project_document_links')->where('company_id', $context->companyId)->where('source_type', $type)->where('source_id', $id)->first();
            if ($existing && (int) $existing->project_id !== $projectId) throw ValidationException::withMessages(['source_id' => 'Document already belongs to another project.']);
            if (!$existing) DB::table('project_document_links')->insert(['company_id' => $context->companyId, 'project_id' => $projectId,
                'source_type' => $type, 'source_id' => $id, 'linked_by' => $actor, 'created_at' => now()]);
            app(OperationPosting::class)->audit('project_link', $projectId, $context, $actor, compact('type', 'id'));
        });
    }

    public function allocate(int $projectId, array $data, string $key, CompanyContext $context, int $actor): SerialReservation
    {
        $data['project_id'] = $projectId;
        return app(OperationPosting::class)->run('project_allocate', SerialReservation::class, 'operations.installation', 'projects.install',
            $data, $key, $context, $actor, function ($data, $date) use ($projectId, $context, $actor) {
                validator($data, ['stock_identity_id' => 'required|integer|min:1'])->validate();
                $project = Project::visibleIn($context)->findOrFail($projectId);
                $identity = StockIdentity::forCompany($context)->where('identity_type', 'serial')->where('status', 'in_stock')->lockForUpdate()->findOrFail($data['stock_identity_id']);
                app(CompanyWriteGuard::class)->warehouse($identity->warehouse_id, $context, $actor);
                if ($identity->warehouse_id === $project->site_warehouse_id || SerialReservation::forCompany($context)->where('active_key', 'serial:'.$identity->id)->exists()) {
                    throw ValidationException::withMessages(['stock_identity_id' => 'Serial is already allocated or at this site.']);
                }
                $product = Product::forCompany($context)->findOrFail($identity->product_id);
                $attribute = DB::table('product_attribute_values as v')->join('product_attribute_definitions as d', 'd.id', '=', 'v.definition_id')
                    ->where('v.company_id', $context->companyId)->where('d.company_id', $context->companyId)
                    ->where('v.product_id', $product->id)->where('d.key', 'warranty_months')->value('v.value_json');
                return SerialReservation::create(app(OperationPosting::class)->header($context, $actor) + ['project_id' => $projectId,
                    'stock_identity_id' => $identity->id, 'warehouse_id' => $identity->warehouse_id, 'active_key' => 'serial:'.$identity->id,
                    'details_json' => ['allocated_date' => $date, 'warranty_months' => $attribute ? (int) json_decode($attribute) : 0], 'posted_at' => now()]);
            });
    }

    public function dispatch(int $reservationId, array $data, string $key, CompanyContext $context, int $actor): SerialReservation
    {
        $data['reservation_id'] = $reservationId;
        return app(OperationPosting::class)->run('project_dispatch', SerialReservation::class, 'operations.installation', 'projects.install',
            $data, $key, $context, $actor, function ($data, $date) use ($reservationId, $context, $actor) {
                $reservation = SerialReservation::visibleIn($context)->lockForUpdate()->findOrFail($reservationId);
                $project = Project::visibleIn($context)->findOrFail($reservation->project_id);
                if ($reservation->status !== 'allocated' || $date < $reservation->details_json['allocated_date']) {
                    throw ValidationException::withMessages(['reservation' => 'Dispatch an allocated serial on or after allocation date.']);
                }
                $identity = StockIdentity::forCompany($context)->findOrFail($reservation->stock_identity_id);
                $movement = app(InventoryMovementService::class)->transfer(app(OperationStock::class)->command($reservation, $date,
                    [new StockLine(productId: $identity->product_id, qty: 1, identityId: $identity->id)],
                    $reservation->warehouse_id, $context, $actor, 'dispatch', $project->site_warehouse_id));
                $details = $reservation->details_json + ['dispatch_date' => $date];
                DB::table('project_serial_reservations')->where('id', $reservationId)->update(['status' => 'dispatched',
                    'dispatch_movement_id' => $movement->id, 'details_json' => json_encode($details)]);
                DB::table('projects')->where('id', $project->id)->update(['status' => 'In Progress']);
                return $reservation->fresh();
            });
    }

    public function install(int $reservationId, array $data, string $key, CompanyContext $context, int $actor): ProjectInstallation
    {
        $data['reservation_id'] = $reservationId;
        return app(OperationPosting::class)->run('project_install', ProjectInstallation::class, 'operations.installation', 'projects.install',
            $data, $key, $context, $actor, function ($data, $date) use ($reservationId, $context, $actor) {
                $reservation = SerialReservation::visibleIn($context)->lockForUpdate()->findOrFail($reservationId);
                $project = Project::visibleIn($context)->findOrFail($reservation->project_id);
                $identity = StockIdentity::forCompany($context)->lockForUpdate()->findOrFail($reservation->stock_identity_id);
                if ($reservation->status !== 'dispatched' || $date < $reservation->details_json['dispatch_date']
                    || $identity->status !== 'in_stock' || (int) $identity->warehouse_id !== (int) $project->site_warehouse_id) {
                    throw ValidationException::withMessages(['reservation' => 'Install a dispatched serial present at the project site.']);
                }
                $replaces = null;
                if (!empty($data['replaces_installation_id'])) {
                    $replaces = ProjectInstallation::visibleIn($context)->where('project_id', $project->id)->where('status', 'commissioned')
                        ->lockForUpdate()->findOrFail($data['replaces_installation_id']);
                    if (ProjectInstallation::where('replaces_installation_id', $replaces->id)->exists()) throw ValidationException::withMessages(['replacement' => 'Installation already replaced.']);
                }
                $installation = ProjectInstallation::create(app(OperationPosting::class)->header($context, $actor) + ['project_id' => $project->id,
                    'reservation_id' => $reservation->id, 'stock_identity_id' => $identity->id, 'installed_date' => $date,
                    'replaces_installation_id' => $replaces?->id, 'details_json' => $reservation->details_json, 'posted_at' => now()]);
                // Installed stock remains at the site until the shared invoice issues it. Installation never issues it twice.
                DB::table('project_serial_reservations')->where('id', $reservationId)->update(['status' => 'installed']);
                if ($replaces) {
                    DB::table('project_installations')->where('id', $replaces->id)->update(['status' => 'replaced']);
                    $this->serviceEvent($replaces->id, 'replacement', $date, 'Replaced by installation '.$installation->id, $context, $actor);
                }
                return $installation;
            });
    }

    public function commission(int $id, array $data, string $key, CompanyContext $context, int $actor): ProjectInstallation
    {
        $data['installation_id'] = $id;
        return app(OperationPosting::class)->run('project_commission', ProjectInstallation::class, 'service.warranty_amc', 'projects.warranty',
            $data, $key, $context, $actor, function ($data, $date) use ($id, $context, $actor) {
                validator($data, ['amc_months' => 'sometimes|integer|min:0|max:1200'])->validate();
                $installation = ProjectInstallation::visibleIn($context)->lockForUpdate()->findOrFail($id);
                if ($installation->status !== 'installed' || $date < $installation->installed_date) {
                    throw ValidationException::withMessages(['installation' => 'Commission an installed serial on or after installation date.']);
                }
                $replacedContracts = $installation->replaces_installation_id ? DB::table('warranty_contracts')->where('installation_id', $installation->replaces_installation_id)->get()->keyBy('kind') : collect();
                foreach (['warranty' => (int) ($installation->details_json['warranty_months'] ?? 0), 'amc' => (int) ($data['amc_months'] ?? 0)] as $kind => $months) {
                    $previous = $replacedContracts->get($kind);
                    if ($previous) {
                        $starts = $previous->starts_on; $ends = $previous->ends_on;
                    } elseif ($months > 0) {
                        $starts = $date; $ends = CarbonImmutable::parse($date)->addMonthsNoOverflow($months)->subDay()->toDateString();
                    } else continue;
                    DB::table('warranty_contracts')->insert(['company_id' => $context->companyId, 'installation_id' => $id,
                        'kind' => $kind, 'starts_on' => $starts, 'ends_on' => $ends, 'replaces_contract_id' => $previous?->id, 'created_at' => now()]);
                }
                DB::table('project_installations')->where('id', $id)->update(['status' => 'commissioned', 'commissioned_date' => $date]);
                DB::table('project_serial_reservations')->where('id', $installation->reservation_id)->update(['status' => 'commissioned']);
                $pending = SerialReservation::visibleIn($context)->where('project_id', $installation->project_id)
                    ->whereIn('status', ['allocated', 'dispatched', 'installed'])->count();
                $total = SerialReservation::visibleIn($context)->where('project_id', $installation->project_id)->count();
                DB::table('projects')->where('id', $installation->project_id)->update(['status' => $pending ? 'In Progress' : 'Completed',
                    'progress_percent' => $total ? (int) floor(($total - $pending) / $total * 100) : 0]);
                $this->serviceEvent($id, 'commission', $date, 'Commissioned', $context, $actor);
                return $installation->fresh();
            });
    }

    public function recordService(int $id, array $data, string $key, CompanyContext $context, int $actor): ProjectInstallation
    {
        $data['installation_id'] = $id;
        return app(OperationPosting::class)->run('project_service', ProjectInstallation::class, 'service.warranty_amc', 'projects.warranty',
            $data, $key, $context, $actor, function ($data, $date) use ($id, $context, $actor) {
                validator($data, ['event' => 'required|in:inspection,repair', 'notes' => 'required|string|max:5000'])->validate();
                $record = ProjectInstallation::visibleIn($context)->findOrFail($id);
                $this->serviceEvent($id, $data['event'], $date, $data['notes'], $context, $actor);
                return $record;
            });
    }

    public function serviceEvent(int $id, string $event, string $date, string $notes, CompanyContext $context, int $actor): void
    {
        app(OperationPosting::class)->authorize('service.warranty_amc', 'projects.warranty', $context, $actor);
        validator(['event' => $event, 'date' => $date, 'notes' => $notes], ['event' => 'required|in:commission,inspection,repair,replacement',
            'date' => 'required|date_format:Y-m-d', 'notes' => 'required|string|max:5000'])->validate();
        DB::transaction(function () use ($id, $event, $date, $notes, $context, $actor) {
            app(CompanyWriteGuard::class)->begin($context, $date);
            $installation = ProjectInstallation::visibleIn($context)->findOrFail($id);
            if ($date < $installation->installed_date) throw ValidationException::withMessages(['business_date' => 'Service history cannot precede installation.']);
            DB::table('serial_service_events')->insert(['company_id' => $context->companyId, 'installation_id' => $id,
                'event' => $event, 'business_date' => $date, 'notes' => $notes, 'created_by' => $actor, 'created_at' => now()]);
        });
    }

    public function margin(int $projectId, CompanyContext $context, int $actor): array
    {
        app(OperationPosting::class)->authorize('operations.projects', 'projects.read', $context, $actor);
        Project::visibleIn($context)->findOrFail($projectId);
        $links = DB::table('project_document_links')->where('company_id', $context->companyId)->where('project_id', $projectId)->get();
        $revenue = $material = $service = $expense = 0;
        foreach ($links as $link) {
            $model = match ($link->source_type) { 'sale' => \App\Models\Sale::class, 'purchase' => \App\Models\Purchase::class, 'expense' => \App\Models\Expense::class };
            $source = $model::forCompany($context)->find($link->source_id);
            if (!$source || $source->reversed_at || ($link->source_type !== 'expense' && !$source->posted_at)) continue;
            $notes = collect();
            $noteTable = $link->source_type === 'sale' ? 'returns' : 'return_purchases';
            if ($link->source_type !== 'expense' && \Illuminate\Support\Facades\Schema::hasColumn($noteTable, 'posted_at')) {
                $notes = DB::table($noteTable)->where('company_id', $context->companyId)->where('branch_id', $context->branchId)
                    ->where($link->source_type.'_id', $link->source_id)->whereNotNull('posted_at')->get(['id', 'note_type']);
            }
            // Approved commercial adjustments belong to their source project without another link or posting.
            $journals = DB::table('journal_entries')->where('company_id', $context->companyId)->where('branch_id', $context->branchId)
                ->where('status', 'posted')->where(function ($query) use ($link, $notes) {
                    $query->where(fn ($q) => $q->where('reference_type', $link->source_type)->where('reference_id', $link->source_id));
                    foreach ($notes as $note) $query->orWhere(fn ($q) => $q->where('reference_type', $link->source_type.'_'.$note->note_type.'_note')->where('reference_id', $note->id));
                })->pluck('id');
            $cogs = $link->source_type === 'sale' ? app(\App\Services\Accounting\SemanticAccountResolver::class)->resolve('cogs', $context)->id : null;
            $rows = DB::table('journal_items as i')->join('chart_of_accounts as a', 'a.id', '=', 'i.chart_of_account_id')
                ->whereIn('i.journal_entry_id', $journals)->where('i.company_id', $context->companyId)->where('a.company_id', $context->companyId)->get(['i.debit', 'i.credit', 'i.chart_of_account_id', 'a.type', 'a.sub_type']);
            foreach ($rows as $row) {
                $debit = LedgerAmount::units((string) $row->debit); $credit = LedgerAmount::units((string) $row->credit);
                if ($link->source_type === 'sale' && $row->type === 'revenue') $revenue += $credit - $debit;
                if ($link->source_type === 'sale' && (int) $row->chart_of_account_id === (int) $cogs) $material += $debit - $credit;
                if ($link->source_type === 'purchase' && $row->type === 'expense') $service += $debit - $credit;
                if ($link->source_type === 'expense' && $row->type === 'expense') $expense += $debit - $credit;
            }
        }
        return ['revenue' => LedgerAmount::decimal($revenue), 'material_cost' => LedgerAmount::decimal($material),
            'service_cost' => LedgerAmount::decimal($service), 'expense' => LedgerAmount::decimal($expense),
            'margin' => LedgerAmount::decimal($revenue - $material - $service - $expense)];
    }
}
