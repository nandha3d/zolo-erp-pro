<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\Commercial\CommercialApplicationService;
use App\Services\Commercial\CommercialDraftService;
use App\Services\Commercial\CommercialPermission;
use App\Services\Commercial\CommercialPricing;
use App\Services\Commercial\CommercialReversalService;
use App\Services\Commercial\CreditControlService;
use App\Services\Commercial\LegacyCommercialCommand;
use App\Services\Commercial\PartyQueryService;
use App\Services\Commercial\ProductQueryService;
use App\Services\Commercial\SaleApplicationService;
use App\Services\Commercial\SaleCommand;
use App\Services\Commercial\PurchaseApplicationService;
use App\Services\Commercial\PurchaseCommand;
use App\Services\ERP\CompanyWriteGuard;
use App\Services\ERP\PaymentService;
use App\Services\Platform\CompanyContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommercialController extends Controller
{
    private function context(Request $request): CompanyContext
    {
        return $request->attributes->get(CompanyContext::class);
    }

    private function idempotencyKey(Request $request, int $max = 150): string
    {
        $key = $request->hasHeader('Idempotency-Key') ? $request->header('Idempotency-Key') : $request->input('idempotency_key', '');
        abort_unless(is_string($key) && trim($key) !== '' && strlen($key) <= $max,
            422, 'An idempotency key of at most '.$max.' characters is required.');
        return $key;
    }

    public function entry(Request $request, string $kind)
    {
        $context = $this->context($request);
        app(CommercialPermission::class)->assert($kind === 'sale' ? 'sales-add' : 'purchases-add', $context, $request->user()->id);
        return view('backend.commercial.entry', [
            'kind' => $kind, 'context' => $context,
            'warehouses' => Warehouse::forCompany($context)->where('branch_id', $context->branchId)->get(['id', 'name']),
            'units' => DB::table('units')->where('company_id', $context->companyId)->get(['id', 'unit_name']),
            'categories' => DB::table('categories')->where('company_id', $context->companyId)->get(['id', 'name']),
            'groups' => $kind === 'sale' ? DB::table('customer_groups')->where('company_id', $context->companyId)->get(['id', 'name']) : collect(),
            'accounts' => DB::table('accounts')->where('company_id', $context->companyId)->get(['id', 'name']),
            'businessDate' => \Carbon\CarbonImmutable::now(\App\Models\Company::findOrFail($context->companyId)->timezone)->toDateString(),
        ]);
    }

    public function store(Request $request, string $kind, bool $legacy = false)
    {
        $context = $this->context($request);
        $data = $legacy ? app(LegacyCommercialCommand::class)->data($request, $kind === 'purchase', $context) : $request->all();
        $key = $this->idempotencyKey($request);
        $document = DB::transaction(function () use ($request, $kind, $data, $key, $context) {
            $document = $kind === 'sale'
                ? app(SaleApplicationService::class)->create(new SaleCommand($data, $key, $request->user()->id, $context))
                : app(PurchaseApplicationService::class)->create(new PurchaseCommand($data, $key, $request->user()->id, $context));
            if ($request->filled('draft_id')) {
                app(CommercialDraftService::class)->query($kind, $context, $request->user()->id)->where('id', $request->draft_id)->delete();
            }
            return $document;
        });
        if ($legacy && $request->filled('pos')) {
            return response()->json($document->id);
        }
        if ($legacy && !$request->expectsJson()) {
            return redirect($kind === 'sale' ? '/sales' : '/purchases')->with('message', 'Document posted successfully.');
        }
        return response()->json(['success' => true, 'data' => $document, 'message' => 'Document posted successfully.'], 201);
    }

    public function preview(Request $request, string $kind)
    {
        app(CommercialPermission::class)->assert($kind === 'sale' ? 'sales-add' : 'purchases-add', $this->context($request), $request->user()->id);
        return response()->json(['data' => app(CommercialPricing::class)->preview($request->all(), $kind === 'purchase', $this->context($request))]);
    }

    public function reverse(Request $request, string $kind, int $id)
    {
        if (!$request->expectsJson() && !$request->filled('reason')) {
            return redirect('/commercial/'.$kind.'/'.$id.'/reverse');
        }
        $request->validate(['business_date' => 'required|date_format:Y-m-d', 'reason' => 'required|string|min:3|max:500']);
        $document = ($kind === 'sale' ? Sale::class : Purchase::class)::visibleIn($this->context($request))->findOrFail($id);
        $document = app(CommercialReversalService::class)->reverse($document, $request->business_date, $request->reason, $this->context($request));
        return $request->expectsJson() ? response()->json(['success' => true, 'data' => $document])
            : redirect($kind === 'sale' ? '/sales' : '/purchases')->with('message', 'Document reversed; original history preserved.');
    }

    public function reversalForm(Request $request, string $kind, int $id)
    {
        $context = $this->context($request);
        app(CommercialPermission::class)->assert($kind === 'sale' ? 'sales-delete' : 'purchases-delete', $context, $request->user()->id);
        $document = ($kind === 'sale' ? Sale::class : Purchase::class)::visibleIn($context)->findOrFail($id);
        return view('backend.commercial.reversal', compact('kind', 'document', 'context'));
    }

    public function reverseSelection(Request $request, string $kind)
    {
        $request->validate(['ids' => 'required|array|min:1|max:100', 'ids.*' => 'integer|min:1',
            'business_date' => 'required|date_format:Y-m-d', 'reason' => 'required|string|min:3|max:500']);
        DB::transaction(function () use ($request, $kind) {
            foreach ($request->ids as $id) {
                $this->reverse($request, $kind, $id);
            }
        });
        return response()->json(['success' => true]);
    }

    public function replace(Request $request, string $kind, int $id)
    {
        $request->validate(['business_date' => 'required|date_format:Y-m-d', 'reason' => 'required|string|min:3|max:500']);
        $context = $this->context($request);
        $data = app(LegacyCommercialCommand::class)->data($request, $kind === 'purchase', $context);
        $document = ($kind === 'sale' ? Sale::class : Purchase::class)::visibleIn($context)->findOrFail($id);
        $replacement = app(CommercialReversalService::class)->replace($document, $data,
            $this->idempotencyKey($request), $request->business_date, $request->reason, $context);
        return $request->expectsJson() ? response()->json(['success' => true, 'data' => $replacement], 201)
            : redirect($kind === 'sale' ? '/sales' : '/purchases')->with('message', 'Replacement document posted successfully.');
    }

    public function search(Request $request, string $kind, string $resource)
    {
        $request->validate(['q' => 'nullable|string|max:100']);
        app(CommercialPermission::class)->assert($kind === 'sale' ? 'sales-add' : 'purchases-add', $this->context($request), $request->user()->id);
        $data = $resource === 'parties' ? app(PartyQueryService::class)->search($kind, $request->input('q', ''), $this->context($request))
            : app(ProductQueryService::class)->search($request->input('q', ''), $this->context($request));
        return response()->json(['data' => $data]);
    }

    public function party(Request $request, string $kind, int $id)
    {
        $context = $this->context($request);
        app(CommercialPermission::class)->assert($kind === 'sale' ? 'sales-index' : 'purchases-index', $context, $request->user()->id);
        $party = ($kind === 'sale' ? Customer::class : Supplier::class)::forCompany($context)->findOrFail($id);
        $type = $kind === 'sale' ? 'customer' : 'supplier';
        $credit = $kind === 'sale' ? app(CreditControlService::class)->summary($party, $context,
            \Carbon\CarbonImmutable::now(\App\Models\Company::findOrFail($context->companyId)->timezone)->toDateString()) : [];
        $request->validate(['page' => 'nullable|integer|min:1|max:1000000', 'pending' => 'nullable|boolean']);
        $page = (int) $request->input('page', 1);
        $items = DB::table('account_open_items')->where('company_id', $context->companyId)
            ->where('party_type', $type)->where('party_id', $id)
            ->when($request->boolean('pending'), fn ($query) => $query->where('open_amount', '!=', 0))
            ->orderByDesc('document_date')->orderByDesc('id')->offset(($page - 1) * 100)->limit(101)->get();
        return response()->json(['data' => ['party' => $party, 'items' => $items->take(100)->values(),
            'next_page' => $items->count() > 100 ? $page + 1 : null, 'credit' => $credit, 'outstanding' => round((float) DB::table('account_open_items')
            ->where('company_id', $context->companyId)->where('party_type', $type)->where('party_id', $id)->sum('open_amount'), 4)]]);
    }

    public function previousRates(Request $request, string $kind)
    {
        $request->validate(['party_id' => 'required|integer|min:1', 'product_id' => 'required|integer|min:1']);
        $context = $this->context($request);
        app(CommercialPermission::class)->assert($kind === 'sale' ? 'sales-index' : 'purchases-index', $context, $request->user()->id);
        app(CompanyWriteGuard::class)->owned($kind === 'sale' ? Customer::class : Supplier::class, $request->party_id, $context, 'party_id');
        app(CompanyWriteGuard::class)->owned(Product::class, $request->product_id, $context, 'product_id');
        $documents = ($kind === 'sale' ? Sale::class : Purchase::class)::visibleIn($context)
            ->where($kind === 'sale' ? 'customer_id' : 'supplier_id', $request->party_id)->whereNotNull('posted_at')->whereNull('reversed_at')->select('id');
        $lines = DB::table($kind === 'sale' ? 'product_sales' : 'product_purchases')->where('company_id', $context->companyId)
            ->whereIn($kind.'_id', $documents)->where('product_id', $request->product_id)->orderByDesc('id')->limit(10)->get();
        return response()->json(['data' => $lines]);
    }

    public function cloneDocument(Request $request, string $kind, int $id)
    {
        $context = $this->context($request);
        app(CommercialPermission::class)->assert($kind === 'sale' ? 'sales-add' : 'purchases-add', $context, $request->user()->id);
        $document = ($kind === 'sale' ? Sale::class : Purchase::class)::visibleIn($context)->findOrFail($id);
        $lines = ($kind === 'sale' ? $document->productSales() : $document->productPurchases())->forCompany($context)->with('product')->get();
        $items = $lines->map(function ($line) use ($kind, $context) {
            abort_unless((int) $line->product?->company_id === $context->companyId, 409, 'Corrupt document line requires review.');
            return ['product_id' => $line->product_id, 'name' => $line->product->name, 'code' => $line->product->code, 'qty' => $line->qty,
                $kind === 'sale' ? 'net_unit_price' : 'net_unit_cost' => $kind === 'sale' ? $line->net_unit_price : $line->net_unit_cost,
                $kind === 'sale' ? 'sale_unit_id' : 'purchase_unit_id' => $kind === 'sale' ? $line->sale_unit_id : $line->purchase_unit_id];
        });
        return response()->json(['data' => [$kind === 'sale' ? 'customer_id' : 'supplier_id' => $document->{$kind === 'sale' ? 'customer_id' : 'supplier_id'},
            'warehouse_id' => $document->warehouse_id, 'items' => $items]]);
    }

    public function draft(Request $request, string $kind)
    {
        $request->validate(['payload' => 'required|array', 'id' => 'nullable|integer|min:1', 'version' => 'required|integer|min:0']);
        $draft = app(CommercialDraftService::class)->save($kind, $request->payload, $request->id, $request->version,
            $this->context($request), $request->user()->id);
        return response()->json(['data' => $draft]);
    }

    public function drafts(Request $request, string $kind)
    {
        app(CommercialPermission::class)->assert($kind === 'sale' ? 'sales-add' : 'purchases-add', $this->context($request), $request->user()->id);
        return response()->json(['data' => app(CommercialDraftService::class)->query($kind, $this->context($request), $request->user()->id)->orderByDesc('id')->limit(30)->get()]);
    }

    public function payment(Request $request, string $kind, int $id)
    {
        $context = $this->context($request);
        app(CommercialPermission::class)->assert($kind === 'sale' ? 'sale-payment-add' : 'purchase-payment-add', $context, $request->user()->id);
        $document = ($kind === 'sale' ? Sale::class : Purchase::class)::visibleIn($context)->findOrFail($id);
        abort_if($document->reversed_at || !$document->posted_at, 409, 'Only an active posted document can receive payment.');
        $data = $request->all();
        $data['idempotency_key'] = $this->idempotencyKey($request, 100);
        $request->validate(['amount' => 'required|numeric|gt:0|max:1000000000',
            'paying_method' => 'required|in:Cash,Bank,Cheque,Credit Card']);
        try {
            $payment = app(PaymentService::class)->addPayment($document, $data, $request->user()->id, $context);
        } catch (\InvalidArgumentException $error) {
            throw \Illuminate\Validation\ValidationException::withMessages(['payment' => $error->getMessage()]);
        }
        return $request->expectsJson() ? response()->json(['success' => true, 'data' => $payment], 201)
            : redirect($kind === 'sale' ? '/sales' : '/purchases')->with('message', 'Payment recorded successfully.');
    }

    public function receive(Request $request, int $id)
    {
        $context = $this->context($request);
        $purchase = Purchase::visibleIn($context)->findOrFail($id);
        $data = $request->except(['_token', 'idempotency_key']);
        return response()->json(['data' => app(\App\Services\Commercial\PurchaseReceiptService::class)->receive($purchase, $data,
            $this->idempotencyKey($request), $context)]);
    }

    public function inlineMaster(Request $request, string $kind, string $resource)
    {
        $context = $this->context($request);
        $actor = $request->user()->id;
        $request->validate(['name' => 'required|string|max:100', 'city' => 'nullable|string|max:100',
            'phone_number' => 'nullable|string|max:50', 'address' => 'nullable|string|max:255', 'search_alias' => 'nullable|string|max:100',
            'credit_days' => 'nullable|integer|min:0|max:3650', 'credit_limit' => 'nullable|numeric|min:0|max:1000000000']);
        app(CommercialPermission::class)->assert($resource === 'products' ? 'products-add' : ($kind === 'sale' ? 'customers-add' : 'suppliers-add'), $context, $actor);
        $key = $this->idempotencyKey($request);
        $type = $resource === 'products' ? 'product' : ($kind === 'sale' ? 'customer' : 'supplier');
        $model = match ($type) { 'product' => Product::class, 'customer' => Customer::class, default => Supplier::class };
        $values = $request->except(['_token', 'idempotency_key', 'company_id']); ksort($values);
        $hash = hash('sha256', json_encode([$type, $values], JSON_THROW_ON_ERROR));
        $record = DB::transaction(function () use ($request, $kind, $resource, $context, $actor, $key, $type, $model, $hash) {
            \App\Models\Company::whereKey($context->companyId)->lockForUpdate()->firstOrFail();
            $retry = DB::table('idempotency_keys')->where('company_id', $context->companyId)->where('key', $key)->first();
            if ($retry) {
                abort_unless($retry->request_hash === $hash && $retry->response_type === $type, 409, 'Key already used for a different request.');
                return $model::forCompany($context)->findOrFail($retry->response_ref);
            }
            app(CompanyWriteGuard::class)->begin($context, null);
            if ($resource === 'products') {
                app(CommercialPermission::class)->assert('products-add', $context, $actor);
                $request->validate(['code' => 'required|string|max:100', 'category_id' => 'required|integer|min:1',
                    'unit_id' => 'required|integer|min:1', 'price' => 'required|numeric|min:0', 'cost' => 'required|numeric|min:0']);
                app(CompanyWriteGuard::class)->owned(\App\Models\Category::class, $request->category_id, $context, 'category_id');
                app(CompanyWriteGuard::class)->owned(\App\Models\Unit::class, $request->unit_id, $context, 'unit_id');
                abort_if(Product::forCompany($context)->where('code', $request->code)->exists(), 422, 'Product code already exists.');
                $record = Product::forceCreate(['company_id' => $context->companyId, 'name' => $request->name, 'code' => $request->code,
                    'category_id' => $request->category_id, 'unit_id' => $request->unit_id, 'sale_unit_id' => $request->unit_id,
                    'purchase_unit_id' => $request->unit_id, 'type' => 'standard', 'barcode_symbology' => 'C128',
                    'price' => app(CommercialPricing::class)->number($request->price, 'price'),
                    'cost' => app(CommercialPricing::class)->number($request->cost, 'cost'), 'qty' => 0, 'is_active' => true]);
            } else {
                $attributes = $request->only(['name', 'city', 'phone_number', 'address', 'search_alias']);
                $attributes += ['phone_number' => '', 'address' => '', 'city' => '', 'company_name' => '', 'email' => ''];
                if ($kind === 'sale') {
                    app(CompanyWriteGuard::class)->owned(\App\Models\CustomerGroup::class, $request->customer_group_id, $context, 'customer_group_id');
                    $attributes['customer_group_id'] = $request->customer_group_id;
                    $attributes += $request->only(['credit_days', 'credit_limit']);
                }
                $record = $model::forceCreate($attributes + ['company_id' => $context->companyId, 'is_active' => true]);
            }
            DB::table('idempotency_keys')->insert(['company_id' => $context->companyId, 'key' => $key, 'request_hash' => $hash,
                'response_type' => $type, 'response_ref' => $record->id, 'created_at' => now(), 'updated_at' => now()]);
            return $record;
        });
        return response()->json(['data' => $record], 201);
    }
}
