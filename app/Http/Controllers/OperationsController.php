<?php

namespace App\Http\Controllers;

use App\Models\Operations\Bom;
use App\Models\Operations\JobWorkDispatch;
use App\Models\Operations\JobWorkOrder;
use App\Models\Operations\JobWorkReceipt;
use App\Models\Operations\ProductionOrder;
use App\Models\Operations\Project;
use App\Models\Product;
use App\Services\Industry\FmcgInventoryService;
use App\Services\Industry\IndustryProfileService;
use App\Services\Industry\ProjectService;
use App\Services\JobWork\JobWorkService;
use App\Services\Manufacturing\BomService;
use App\Services\Manufacturing\ProductionService;
use App\Services\Operations\OperationPosting;
use App\Services\Platform\CapabilityService;
use App\Services\Platform\CompanyContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OperationsController extends Controller
{
    private function context(Request $request): CompanyContext { return $request->attributes->get(CompanyContext::class); }
    private function key(Request $request): string
    {
        $key = $request->header('Idempotency-Key') ?? $request->input('idempotency_key', '');
        validator(['key' => $key], ['key' => 'nullable|string|max:150'])->validate();
        return $key ?? '';
    }

    public function hub(Request $request, string $area)
    {
        $context = $this->context($request); $actor = $request->user()->id;
        [$capability, $permission] = match ($area) {
            'manufacturing' => ['manufacturing.bom', 'manufacturing.read'], 'job-work' => ['operations.job_work', 'job_work.read'],
            'profiles' => ['core.inventory', 'profiles.manage'], 'projects' => ['operations.projects', 'projects.read'],
            'stock' => ['core.inventory', 'products-index'], default => abort(404),
        };
        app(OperationPosting::class)->authorize($capability, $permission, $context, $actor);
        $data = $this->lookups($context) + ['area' => $area, 'context' => $context];
        $data['records'] = match ($area) {
            'manufacturing' => ProductionOrder::visibleIn($context)->orderByDesc('id')->limit(100)->get(),
            'job-work' => JobWorkOrder::visibleIn($context)->orderByDesc('id')->limit(100)->get(),
            'projects' => Project::visibleIn($context)->orderByDesc('id')->limit(100)->get(), default => collect(),
        };
        $data['boms'] = Bom::forCompany($context)->orderByDesc('id')->limit(100)->get();
        $data['profile'] = app(IndustryProfileService::class)->settings($context);
        $data['processes'] = DB::table('process_types')->where('company_id', $context->companyId)->where('is_active', true)->get();
        $data['pending'] = $area === 'job-work' ? app(JobWorkService::class)->pending($context, $actor) : [];
        $data['batchBalances'] = $area === 'stock' && in_array('inventory.batch_expiry', $data['navigation'])
            ? app(\App\Services\Industry\IndustryStockQueries::class)->batchBalances($context, $actor) : [];
        $data['shrinkage'] = $area === 'job-work' ? JobWorkReceipt::visibleIn($context)->whereNull('reversed_at')->orderByDesc('id')->limit(100)->get() : collect();
        $data['legacyProductions'] = $area === 'manufacturing' && \Illuminate\Support\Facades\Schema::hasTable('productions')
            ? DB::table('productions')->where('company_id', $context->companyId)->where('branch_id', $context->branchId)->orderByDesc('id')->limit(100)->get() : collect();
        return $request->expectsJson() ? response()->json(['data' => $data]) : view('backend.operations.hub', $data);
    }

    private function lookups(CompanyContext $context): array
    {
        return ['navigation' => app(CapabilityService::class)->forNavigation($context),
            'products' => Product::forCompany($context)->where('is_active', true)->orderBy('name')->limit(200)->get(['id', 'name', 'code', 'type', 'unit_id']),
            'units' => DB::table('units')->where('company_id', $context->companyId)->get(['id', 'unit_name']),
            'warehouses' => DB::table('warehouses')->where('company_id', $context->companyId)->where('branch_id', $context->branchId)
                ->whereNull('external_job_order_id')->where('is_active', true)->get(['id', 'name']),
            'suppliers' => DB::table('suppliers')->where('company_id', $context->companyId)->where('is_active', true)->orderBy('name')->limit(200)->get(['id', 'name']),
            'customers' => DB::table('customers')->where('company_id', $context->companyId)->where('is_active', true)->orderBy('name')->limit(200)->get(['id', 'name']),
            'date' => \Carbon\CarbonImmutable::now(\App\Models\Company::findOrFail($context->companyId)->timezone)->toDateString()];
    }

    public function detail(Request $request, string $kind, int $id)
    {
        $context = $this->context($request); $actor = $request->user()->id;
        [$model, $capability, $permission] = match ($kind) {
            'production' => [ProductionOrder::class, 'manufacturing.production', 'manufacturing.read'],
            'job-work' => [JobWorkOrder::class, 'operations.job_work', 'job_work.read'],
            'project' => [Project::class, 'operations.projects', 'projects.read'], default => abort(404),
        };
        app(OperationPosting::class)->authorize($capability, $permission, $context, $actor);
        $record = $model::visibleIn($context)->findOrFail($id);
        $data = $this->lookups($context) + compact('kind', 'record', 'context');
        if ($kind === 'production') {
            $data['bom'] = Bom::forCompany($context)->findOrFail($record->bom_id);
            $data['components'] = DB::table('bom_lines as l')->join('products as p', 'p.id', '=', 'l.component_product_id')
                ->where('p.company_id', $context->companyId)->where('l.bom_id', $record->bom_id)->select('l.*', 'p.name')->get();
            $data['outputs'] = DB::table('production_outputs as l')->join('products as p', 'p.id', '=', 'l.product_id')
                ->where('p.company_id', $context->companyId)->where('l.production_order_id', $id)->select('l.*', 'p.name')->get();
        } elseif ($kind === 'job-work') {
            $data['dispatches'] = JobWorkDispatch::visibleIn($context)->where('job_work_order_id', $id)->get();
            $data['dispatchLines'] = DB::table('job_work_dispatch_lines as l')->join('job_work_dispatches as d', 'd.id', '=', 'l.dispatch_id')
                ->join('products as p', 'p.id', '=', 'l.product_id')->where('d.company_id', $context->companyId)->where('d.branch_id', $context->branchId)
                ->where('p.company_id', $context->companyId)->where('d.job_work_order_id', $id)->select('l.*', 'p.name')->get()->groupBy('dispatch_id');
            $data['receipts'] = JobWorkReceipt::visibleIn($context)->whereIn('dispatch_id', $data['dispatches']->pluck('id'))->get();
        } else {
            $data['serials'] = DB::table('stock_identities as i')->join('products as p', 'p.id', '=', 'i.product_id')
                ->join('warehouses as w', 'w.id', '=', 'i.warehouse_id')->where('i.company_id', $context->companyId)
                ->where('p.company_id', $context->companyId)->where('w.company_id', $context->companyId)->where('w.branch_id', $context->branchId)
                ->where('i.identity_type', 'serial')->where('i.status', 'in_stock')
                ->whereNotIn('i.id', DB::table('project_serial_reservations')->whereNotNull('active_key')->select('stock_identity_id'))
                ->select('i.id', 'i.identity_no', 'p.name')->limit(200)->get();
            $data['reservations'] = \App\Models\Operations\SerialReservation::visibleIn($context)->where('project_id', $id)->get();
            $data['installations'] = \App\Models\Operations\ProjectInstallation::visibleIn($context)->where('project_id', $id)->get();
            $data['reservedIdentities'] = \App\Models\Inventory\StockIdentity::forCompany($context)
                ->whereIn('id', $data['reservations']->pluck('stock_identity_id'))->pluck('identity_no', 'id');
            $data['contracts'] = DB::table('warranty_contracts')->where('company_id', $context->companyId)
                ->whereIn('installation_id', $data['installations']->pluck('id'))->orderBy('ends_on')->get()->groupBy('installation_id');
            $data['serviceEvents'] = DB::table('serial_service_events')->where('company_id', $context->companyId)
                ->whereIn('installation_id', $data['installations']->pluck('id'))->orderBy('business_date')->orderBy('id')->get()->groupBy('installation_id');
            $data['documentLinks'] = DB::table('project_document_links')->where('company_id', $context->companyId)->where('project_id', $id)->get();
            $data['margin'] = app(ProjectService::class)->margin($id, $context, $actor);
            $data['templates'] = Bom::forCompany($context)->where('status', 'published')->get();
        }
        return $request->expectsJson() ? response()->json(['data' => $data]) : view('backend.operations.detail', $data);
    }

    public function action(Request $request, string $action, ?int $id = null)
    {
        $context = $this->context($request); $actor = $request->user()->id; $data = $request->all(); $key = $this->key($request);
        $record = match ($action) {
            'bom' => app(BomService::class)->create($data, $key, $context, $actor),
            'production-plan' => app(ProductionService::class)->plan($data, $key, $context, $actor),
            'production-complete' => app(ProductionService::class)->complete($id, $data, $key, $context, $actor),
            'production-reverse' => app(ProductionService::class)->reverse($id, $request->string('business_date')->toString(), $request->string('reason')->toString(), $context, $actor),
            'job-order' => app(JobWorkService::class)->order($data, $key, $context, $actor),
            'job-dispatch' => app(JobWorkService::class)->dispatch($id, $data, $key, $context, $actor),
            'job-receive' => app(JobWorkService::class)->receive($id, $data, $key, $context, $actor),
            'job-bill' => app(JobWorkService::class)->serviceBill($id, $data, $key, $context, $actor),
            'job-reverse-dispatch' => app(JobWorkService::class)->reverse('dispatch', $id, $request->string('business_date')->toString(), $request->string('reason')->toString(), $context, $actor),
            'job-reverse-receipt' => app(JobWorkService::class)->reverse('receipt', $id, $request->string('business_date')->toString(), $request->string('reason')->toString(), $context, $actor),
            'project' => app(ProjectService::class)->create($data, $key, $context, $actor),
            'project-quotation' => app(ProjectService::class)->quotation($id, $data, $key, $context, $actor),
            'project-allocate' => app(ProjectService::class)->allocate($id, $data, $key, $context, $actor),
            'project-dispatch' => app(ProjectService::class)->dispatch($id, $data, $key, $context, $actor),
            'project-install' => app(ProjectService::class)->install($id, $data, $key, $context, $actor),
            'project-commission' => app(ProjectService::class)->commission($id, $data, $key, $context, $actor),
            'project-service' => app(ProjectService::class)->recordService($id, $data, $key, $context, $actor),
            'project-link' => $this->linkProject($id, $request, $context, $actor),
            'expiry-writeoff' => app(FmcgInventoryService::class)->writeOff($data, $key, $context, $actor),
            default => abort(404),
        };
        $url = match (true) {
            $record instanceof ProductionOrder => url('/operations/production/'.$record->id),
            $record instanceof JobWorkOrder => url('/operations/job-work/'.$record->id),
            $record instanceof JobWorkDispatch => url('/operations/job-work/'.$record->job_work_order_id),
            $record instanceof JobWorkReceipt => url('/operations/job-work/'.JobWorkDispatch::visibleIn($context)->findOrFail($record->dispatch_id)->job_work_order_id),
            $record instanceof Project => url('/operations/project/'.$record->id),
            $record instanceof \App\Models\Operations\SerialReservation || $record instanceof \App\Models\Operations\ProjectInstallation => url('/operations/project/'.$record->project_id),
            $record instanceof Bom => url('/operations/manufacturing'), default => url('/operations/stock'),
        };
        return $request->expectsJson() ? response()->json(['success' => true, 'data' => $record, 'redirect' => $url], 201)
            : redirect($url)->with('message', 'Operation saved.');
    }

    public function configure(Request $request, string $action)
    {
        $context = $this->context($request); $actor = $request->user()->id;
        $data = match ($action) {
            'profile' => app(IndustryProfileService::class)->apply($request->string('profile')->toString(), $request->input('subtype'), $context, $actor),
            'process' => ['id' => app(JobWorkService::class)->configureProcess($request->all(), $context, $actor)],
            'scheme' => ['id' => app(FmcgInventoryService::class)->configureScheme($request->all(), $context, $actor)],
            default => abort(404),
        };
        return $request->expectsJson() ? response()->json(['data' => $data]) : back()->with('message', 'Configuration saved.');
    }

    private function linkProject(int $id, Request $request, CompanyContext $context, int $actor): Project
    {
        $data = $request->validate(['source_type' => 'required|in:sale,purchase,expense', 'source_id' => 'required|integer|min:1']);
        app(ProjectService::class)->link($id, $data['source_type'], $data['source_id'], $context, $actor);
        return Project::visibleIn($context)->findOrFail($id);
    }

    public function attributes(Request $request, int $id)
    {
        $values = $request->isMethod('POST') ? $request->validate(['attributes' => 'required|array'])['attributes'] : null;
        return response()->json(['data' => app(IndustryProfileService::class)->attributes($id, $this->context($request), $request->user()->id, $values)]);
    }

    public function fefo(Request $request)
    {
        $data = $request->validate(['product_id' => 'required|integer|min:1', 'warehouse_id' => 'required|integer|min:1',
            'qty' => 'required|numeric|gt:0', 'date' => 'required|date_format:Y-m-d']);
        return response()->json(['data' => app(FmcgInventoryService::class)->suggest($data['product_id'], $data['warehouse_id'], $data['qty'], $data['date'], $this->context($request), $request->user()->id)]);
    }

    public function pieces(Request $request)
    {
        $data = $request->validate(['product_id' => 'required|integer|min:1', 'warehouse_id' => 'required|integer|min:1', 'q' => 'nullable|string|max:100']);
        return response()->json(['data' => app(\App\Services\Industry\IndustryStockQueries::class)->pieces($data['product_id'], $data['warehouse_id'],
            $data['q'] ?? '', $this->context($request), $request->user()->id)]);
    }

    public function materialDocument(Request $request, int $id)
    {
        $context = $this->context($request);
        app(OperationPosting::class)->authorize('operations.job_work', 'job_work.read', $context, $request->user()->id);
        $dispatch = JobWorkDispatch::visibleIn($context)->findOrFail($id);
        $order = JobWorkOrder::visibleIn($context)->findOrFail($dispatch->job_work_order_id);
        $lines = DB::table('job_work_dispatch_lines as l')->join('products as p', 'p.id', '=', 'l.product_id')
            ->where('p.company_id', $context->companyId)->where('l.dispatch_id', $id)->select('l.*', 'p.name', 'p.code')->get();
        $company = \App\Models\Company::findOrFail($context->companyId);
        $profile = app(IndustryProfileService::class)->settings($context);
        $title = $profile['settings']['labels']['dispatch'] ?? 'Material Dispatch';
        if ($request->input('format') === 'dot_matrix') {
            app(CapabilityService::class)->assertEnabled('printing.dot_matrix', $context);
            $rows = [$company->legal_name, $title.' '.$dispatch->reference_no, 'Date: '.$dispatch->business_date, 'Order: '.$order->reference_no,
                'Own material sent for external processing.', str_repeat('-', 80)];
            foreach ($lines as $line) $rows[] = $line->code.' '.$line->name.'  Qty: '.$line->qty_base;
            $pages = [];
            foreach (array_chunk($rows, 68) as $page) $pages[] = implode("\r\n", array_map(fn ($row) => mb_strimwidth($row, 0, 80), array_pad($page, 68, '')));
            return response(implode("\f", $pages), 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }
        abort_unless(in_array($request->input('format', 'a4'), ['a4'], true), 422);
        return view('backend.operations.material_document', compact('dispatch', 'order', 'lines', 'company', 'title'));
    }
}
