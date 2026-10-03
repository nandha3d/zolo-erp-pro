<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Exchange;
use App\Models\Product;
use App\Models\Product_Warehouse;
use App\Models\Sale;
use App\Models\Warehouse;
use App\Services\Accounting\AccountingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ExchangeController extends Controller
{
    protected $accountingService;

    public function __construct(AccountingService $accountingService)
    {
        $this->accountingService = $accountingService;
    }

    public function index(Request $request)
    {
        $query = Exchange::with(['customer', 'warehouse', 'originalSale']);

        if ($request->warehouse_id) {
            $query->where('warehouse_id', $request->warehouse_id);
        }
        if ($request->start_date && $request->end_date) {
            $query->whereDate('created_at', '>=', $request->start_date)
                  ->whereDate('created_at', '<=', $request->end_date);
        }

        $exchanges = $query->latest()->paginate(15);
        $warehouses = Warehouse::where('is_active', true)->get();

        return view('backend.exchange.index', compact('exchanges', 'warehouses'));
    }

    public function create(Request $request)
    {
        $referenceNo = $request->get('reference_no');
        $sale = null;
        if ($referenceNo) {
            $sale = Sale::with(['productSales.product'])->where('reference_no', $referenceNo)->first();
        }

        $customers = Customer::where('is_active', true)->get();
        $warehouses = Warehouse::where('is_active', true)->get();
        $products = Product::where('is_active', true)->select('id', 'name', 'code', 'price', 'cost', 'qty')->get();

        return view('backend.exchange.create', compact('sale', 'referenceNo', 'customers', 'warehouses', 'products'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'warehouse_id' => 'required',
            'customer_id' => 'required',
            'returned_products' => 'required|array',
            'exchanged_products' => 'required|array',
        ]);

        $refNo = 'EXC-' . date('Ymd') . '-' . rand(1000, 9999);
        $returnedTotal = 0;
        $exchangedTotal = 0;

        $returnedItems = [];
        foreach ($request->returned_products as $item) {
            if (!empty($item['product_id']) && !empty($item['qty']) && $item['qty'] > 0) {
                $sub = (float)$item['unit_price'] * (float)$item['qty'];
                $returnedTotal += $sub;
                $returnedItems[] = [
                    'product_id' => $item['product_id'],
                    'name' => $item['name'] ?? 'Item',
                    'qty' => $item['qty'],
                    'unit_price' => $item['unit_price'],
                    'subtotal' => $sub
                ];
            }
        }

        $exchangedItems = [];
        foreach ($request->exchanged_products as $item) {
            if (!empty($item['product_id']) && !empty($item['qty']) && $item['qty'] > 0) {
                $sub = (float)$item['unit_price'] * (float)$item['qty'];
                $exchangedTotal += $sub;
                $exchangedItems[] = [
                    'product_id' => $item['product_id'],
                    'name' => $item['name'] ?? 'Item',
                    'qty' => $item['qty'],
                    'unit_price' => $item['unit_price'],
                    'subtotal' => $sub
                ];
            }
        }

        $diff = $exchangedTotal - $returnedTotal;

        DB::transaction(function () use ($request, $refNo, $returnedItems, $returnedTotal, $exchangedItems, $exchangedTotal, $diff) {
            // Create exchange
            Exchange::create([
                'reference_no' => $refNo,
                'original_sale_id' => $request->sale_id,
                'warehouse_id' => $request->warehouse_id,
                'customer_id' => $request->customer_id,
                'biller_id' => $request->biller_id ?? 1,
                'returned_items' => $returnedItems,
                'returned_total' => $returnedTotal,
                'exchanged_items' => $exchangedItems,
                'exchanged_total' => $exchangedTotal,
                'difference_amount' => $diff,
                'payment_status' => 'completed',
                'payment_method' => $request->payment_method ?? 'Cash',
                'note' => $request->note,
                'user_id' => Auth::id() ?? 1,
            ]);

            // Stock adjustments: Add returned items back to stock
            foreach ($returnedItems as $ret) {
                $pw = Product_Warehouse::firstOrCreate(
                    ['warehouse_id' => $request->warehouse_id, 'product_id' => $ret['product_id']],
                    ['qty' => 0]
                );
                $pw->increment('qty', $ret['qty']);
                Product::where('id', $ret['product_id'])->increment('qty', $ret['qty']);
            }

            // Deduct exchanged items from stock
            foreach ($exchangedItems as $exc) {
                $pw = Product_Warehouse::firstOrCreate(
                    ['warehouse_id' => $request->warehouse_id, 'product_id' => $exc['product_id']],
                    ['qty' => 0]
                );
                $pw->decrement('qty', $exc['qty']);
                Product::where('id', $exc['product_id'])->decrement('qty', $exc['qty']);
            }

            // Accounting journal if diff != 0
            if (abs($diff) > 0) {
                try {
                    $items = [];
                    if ($diff > 0) {
                        // Customer pays difference
                        $items[] = ['account_code' => '1010', 'debit' => $diff, 'credit' => 0, 'memo' => 'Exchange differential cash collected'];
                        $items[] = ['account_code' => '4010', 'debit' => 0, 'credit' => $diff, 'memo' => 'Exchange net revenue'];
                    } else {
                        // Store refunds difference
                        $refund = abs($diff);
                        $items[] = ['account_code' => '4010', 'debit' => $refund, 'credit' => 0, 'memo' => 'Exchange sales refund'];
                        $items[] = ['account_code' => '1010', 'debit' => 0, 'credit' => $refund, 'memo' => 'Exchange cash refund disbursed'];
                    }

                    $this->accountingService->postJournalEntry([
                        'entry_date' => now()->toDateString(),
                        'reference_type' => 'Exchange',
                        'reference_id' => $refNo,
                        'narration' => "Product Exchange [{$refNo}] Net Difference: {$diff}",
                        'items' => $items
                    ]);
                } catch (\Exception $e) {}
            }
        });

        return redirect()->route('exchange.index')->with('message', "Product Exchange {$refNo} completed successfully.");
    }
}
