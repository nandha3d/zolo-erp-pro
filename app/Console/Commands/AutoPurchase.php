<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\ProductPurchase;
use App\Services\Inventory\LegacyInventoryPosting;
use App\Services\Inventory\StockLine;
use DB;

class AutoPurchase extends Command
{
    use \App\Http\Controllers\Concerns\NumbersLegacyDocuments;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'purchase:auto {--company= : Company ID} {--actor= : Authorized administrator ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatic purchase if the qty exeeds alert qty';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('companies') || !\App\Models\Company::query()->exists()) {
            // Before company foundation the installation is a single implicit company.
            DB::transaction(fn () => $this->purchase(null));
            return self::SUCCESS;
        }
        if (!$this->option('company') || !$this->option('actor')) {
            $this->error('Supply --company and --actor; the job never runs across companies.');
            return self::FAILURE;
        }
        $resolver = app(\App\Services\Platform\CompanyContextResolver::class);
        $actor = (int) $this->option('actor');
        try {
            $company = $resolver->authorizedCompany($actor, (int) $this->option('company'));
            if (!$resolver->canManageFinancialYears($actor, $company->id)) {
                $this->error('Company administrator required.');
                return self::FAILURE;
            }
            $context = $resolver->resolve($actor, $company->id);
        } catch (\Throwable $error) {
            $this->error($error->getMessage());
            return self::FAILURE;
        }
        \Illuminate\Support\Facades\Auth::onceUsingId($actor);
        // The company scope and numbering read the trusted request context, exactly as a web request would.
        request()->attributes->set(\App\Services\Platform\CompanyContext::class, $context);
        try {
            DB::transaction(fn () => $this->purchase($actor));
        } finally {
            request()->attributes->remove(\App\Services\Platform\CompanyContext::class);
        }

        return self::SUCCESS;
    }

    private function purchase(?int $actor): void
    {
        $product_data = Product::where('is_active', true)
                        ->whereColumn('alert_quantity', '>', 'qty')
                        ->where('type', 'standard')
                        ->where(fn ($query) => $query->whereNull('is_variant')->orWhere('is_variant', false))
                        ->where(fn ($query) => $query->whereNull('is_batch')->orWhere('is_batch', false))
                        ->where(fn ($query) => $query->whereNull('is_imei')->orWhere('is_imei', false))
                        ->orderBy('id')->lockForUpdate()->get();
        if(count($product_data)) {
            $pos_setting_data = DB::table('pos_setting')
                                ->select('warehouse_id')
                                ->latest()
                                ->first();
            $user_id = $actor ?? DB::table('users')->where([['is_active', true], ['role_id', 1]])->value('id');
            if ($actor !== null) {
                $numberReservation = $this->reserveNumber('purchase');
                $data['reference_no'] = $numberReservation->formatted_number;
                if (!\App\Models\Warehouse::whereKey($pos_setting_data->warehouse_id)->exists()) {
                    throw new \RuntimeException('The default POS warehouse does not belong to this company.');
                }
            } else {
                $data['reference_no'] = 'pr-' . date('Ymd') . '-'. date('his');
            }
            $data['user_id'] = $user_id;
            $data['warehouse_id'] = $pos_setting_data->warehouse_id;
            $data['item'] = count($product_data);
            $data['total_qty'] = 10 * $data['item'];
            $data['total_discount'] = 0;
            $data['paid_amount'] = 0;
            $data['status'] = 1;
            $data['payment_status'] = 1;
            $data['total_tax'] = 0;
            $data['total_cost'] = 0;
            foreach($product_data as $key => $product) {
                if($product->tax_id) {
                    $tax_data = DB::table('taxes')->select('rate')->find($product->tax_id);
                    if($product->tax_method == 1) {
                        $net_unit_cost = number_format($product->cost, 2, '.', '');
                        $tax = number_format($product->cost * 10 * ($tax_data->rate / 100), 2, '.', '');
                        $cost = number_format(($product->cost * 10) + $tax, 2, '.', '');
                    }
                    else {
                        $net_unit_cost = number_format((100 / (100 + $tax_data->rate)) * $product->cost, 2, '.', '');
                        $tax = number_format(($product->cost - $net_unit_cost) * 10, 2, '.', '');
                        $cost = number_format($product->cost * 10, 2, '.', '');
                    }
                    $tax_rate = $tax_data->rate;
                    $data['total_tax'] += $tax;
                    $data['total_cost'] += $cost;
                }
                else {
                    $data['total_tax'] += 0.00;
                    $data['total_cost'] += number_format($product->cost * 10, 2, '.', '');
                    $net_unit_cost = number_format($product->cost, 2, '.', '');
                    $tax_rate = 0.00;
                    $tax = 0.00;
                    $cost = number_format($product->cost * 10, 2, '.', '');
                }

                $data['product_id'][$key] = $product->id;
                $data['unit_id'][$key] = $product->unit_id;
                $data['net_unit_cost'][$key] = $net_unit_cost;
                $data['tax_rate'][$key] = $tax_rate;
                $data['tax'][$key] = $tax;
                $data['total'][$key] = $cost;

            }
            $data['order_tax'] = 0;
            $data['grand_total'] = $data['total_cost'];
            $purchase_data = Purchase::create($data);
            if (isset($numberReservation)) {
                $this->assignNumber($numberReservation, $purchase_data);
            }
            foreach ($data['product_id'] as $key => $product_id) {
                ProductPurchase::create([
                    'purchase_id' => $purchase_data->id,
                    'product_id' => $product_id,
                    'qty' => 10,
                    'recieved' => 10,
                    'purchase_unit_id' => $data['unit_id'][$key],
                    'net_unit_cost' => $data['net_unit_cost'][$key],
                    'discount' => 0,
                    'tax_rate' => $data['tax_rate'][$key],
                    'tax' => $data['tax'][$key],
                    'total' => $data['total'][$key],
                ]);
            }
            app(LegacyInventoryPosting::class)->post($purchase_data, 'receive', array_map(
                fn ($key) => new StockLine((int) $data['product_id'][$key], 10, unitCost: (float) $data['net_unit_cost'][$key]),
                array_keys($data['product_id'])
            ), (int) $data['warehouse_id']);
        }
    }
}
