<?php

namespace App\Http\Controllers;

use App\Services\Commercial\CommercialPermission;
use App\Services\Platform\{CompanyContext, CompanyContextResolver};
use App\Services\Tax\{Gstin, GstSetupService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Settings › GST: registration, rate slabs, HSN/SAC, product and party tax in one place. */
class GstSettingsController extends Controller
{
    private function context(Request $request): CompanyContext { return $request->attributes->get(CompanyContext::class); }

    public function index(Request $request, GstSetupService $gst)
    {
        $context = $this->context($request);
        app(CommercialPermission::class)->assert('gst-index', $context, $request->user()->id);
        $scope = ['company_id' => $context->companyId];
        $today = now()->toDateString();
        $rates = DB::table('tax_rates')->where($scope)->orderBy('effective_from')->get()->groupBy('tax_category_id');
        $usage = DB::table('products')->where($scope)->whereNotNull('tax_category_id')->groupBy('tax_category_id')
            ->selectRaw('tax_category_id, count(*) as total')->pluck('total', 'tax_category_id');
        $categories = DB::table('tax_categories')->where($scope)->get()->map(function ($category) use ($rates, $usage, $today) {
            $history = $rates[$category->id] ?? collect();
            $category->current = $history->first(fn ($r) => $r->effective_from <= $today && (!$r->effective_to || $r->effective_to >= $today));
            $category->history = $history;
            $category->products = (int) ($usage[$category->id] ?? 0);
            return $category;
        })->sortBy(fn ($c) => [$c->supply_type === 'taxable' ? 1 : 0, (float) ($c->current->rate ?? 0), $c->name])->values();
        $filter = $request->query('products', 'missing');
        $products = DB::table('products')->where($scope)->where('is_active', true)
            ->when($filter === 'missing', fn ($q) => $q->where(fn ($q) => $q->whereNull('tax_category_id')->orWhereNull('hsn_code')->orWhere('hsn_code', '')))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$request->q.'%')->orWhere('code', 'like', '%'.$request->q.'%')))
            ->orderBy('name')->limit(300)->get(['id', 'name', 'code', 'type', 'hsn_code', 'tax_category_id']);
        $profiles = DB::table('party_tax_profiles')->where($scope)->get()->keyBy(fn ($p) => $p->party_type.':'.$p->party_id);
        $parties = collect(['supplier' => 'suppliers', 'customer' => 'customers'])->flatMap(fn ($table, $type) => DB::table($table)->where($scope)
            ->where('is_active', true)->orderBy('name')->limit(300)->get()->map(fn ($p) => (object) ['type' => $type, 'id' => $p->id, 'name' => $p->name,
                'gstin' => $type === 'supplier' ? ($p->vat_number ?? null) : ($p->tax_no ?? null), 'state' => $p->state ?? null,
                'profile' => $profiles[$type.':'.$p->id] ?? null]));
        $ledgers = collect(GstSetupService::LEDGERS)->map(fn ($ledger, $role) => (object) ['role' => $role, 'name' => $ledger[1],
            'account' => DB::table('semantic_account_mappings as m')->join('chart_of_accounts as a', 'a.id', '=', 'm.account_id')
                ->where('m.company_id', $context->companyId)->where('m.semantic_role', $role)->first(['a.code', 'a.name'])]);
        return view('backend.gst.settings', [
            'registration' => $gst->registration($context),
            'registrations' => DB::table('tax_registrations')->where($scope)->where('branch_id', $context->branchId)->orderByDesc('effective_from')->get(),
            'categories' => $categories, 'codes' => DB::table('hsn_sac_codes')->where($scope)->orderBy('code')->get(),
            'products' => $products, 'productFilter' => $filter, 'parties' => $parties, 'ledgers' => $ledgers,
            'missing' => DB::table('products')->where($scope)->where('is_active', true)
                ->where(fn ($q) => $q->whereNull('tax_category_id')->orWhereNull('hsn_code')->orWhere('hsn_code', ''))->count(),
            'states' => array_diff_key(Gstin::$states, ['97' => 1]), 'gstMode' => $gst->enabled($context),
            'canManage' => app(CompanyContextResolver::class)->canManageFinancialYears($request->user()->id, $context->companyId),
            'legacyTaxes' => \Illuminate\Support\Facades\Schema::hasTable('taxes') ? DB::table('taxes')->where('is_active', true)->count() : 0,
        ]);
    }

    public function save(Request $request, string $action, GstSetupService $gst)
    {
        $context = $this->context($request); $actor = $request->user()->id;
        $message = match ($action) {
            'registration' => tap('GST registration saved.', fn () => $gst->registerCompany($request->only(['gstin', 'legal_name', 'trade_name', 'address', 'registration_type', 'effective_from']), $context, $actor)),
            'install' => (function () use ($gst, $context, $actor) {
                $result = $gst->installDefaults($context, $actor);
                return "Standard GST set up: {$result['categories']} new rates, {$result['products']} products mapped, {$result['parties']} parties synced.";
            })(),
            'category' => tap('GST rate added.', fn () => $gst->createCategory($request->only(['name', 'supply_type', 'rate', 'cess_rate', 'input_credit_allowed', 'effective_from']), $context, $actor)),
            'rename' => tap('GST rate updated.', fn () => $gst->updateCategory($request->integer('id'), $request->only(['name', 'input_credit_allowed']), $context, $actor)),
            'rate' => tap('New rate period saved. Bills already posted keep their old rate.', fn () => $gst->changeRate($request->integer('id'),
                (float) $request->input('rate'), (float) $request->input('cess_rate', 0), (string) $request->input('effective_from'), $context, $actor)),
            'code' => tap('HSN/SAC code saved.', function () use ($request, $gst, $context, $actor) {
                $gst->authorize($context, $actor);
                $data = $request->validate(['code' => 'required|string|max:8', 'kind' => 'required|in:hsn,sac', 'description' => 'required|string|max:191']);
                $gst->validateCode($data['code'], $data['kind']);
                DB::table('hsn_sac_codes')->updateOrInsert(['company_id' => $context->companyId, 'code' => $data['code']],
                    ['kind' => $data['kind'], 'description' => $data['description'], 'updated_at' => now(), 'created_at' => now()]);
            }),
            'products' => (function () use ($request, $gst, $context, $actor) {
                $rows = $request->validate(['products' => 'required|array|max:500', 'products.*.tax_category_id' => 'nullable|integer|min:1',
                    'products.*.hsn_code' => 'nullable|string|max:8'])['products'];
                DB::transaction(function () use ($rows, $gst, $context, $actor) {
                    foreach ($rows as $id => $row) {
                        $gst->assignProduct((int) $id, isset($row['tax_category_id']) ? (int) $row['tax_category_id'] : null, $row['hsn_code'] ?? null, $context, $actor);
                    }
                });
                return count($rows).' products saved.';
            })(),
            'parties' => (function () use ($gst, $context, $actor) {
                $gst->authorize($context, $actor);
                return $gst->syncAllParties($context->companyId).' party GST profiles synced from party masters.';
            })(),
            'party' => tap('Party GST profile saved.', fn () => app(\App\Services\Tax\TaxSetupService::class)->save('party',
                $request->only(['party_type', 'party_id', 'registration_type', 'gstin', 'state_code']), $context, $actor)),
            default => abort(404),
        };
        return $request->expectsJson() ? response()->json(['success' => true, 'message' => $message])
            : redirect()->to(url('/settings/gst').'#'.$request->input('tab', 'rates'))->with('message', $message);
    }
}
