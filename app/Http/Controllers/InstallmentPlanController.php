<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Customer;
use App\Models\GiftCard;
use App\Models\Installment;
use App\Models\InstallmentPlan;
use App\Models\Payment;
use App\Models\PosSetting;
use App\Models\RewardPointSetting;
use App\Models\Sale;
use App\Services\Accounting\AccountingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InstallmentPlanController extends Controller
{
    protected $accountingService;

    public function __construct(AccountingService $accountingService)
    {
        $this->accountingService = $accountingService;
    }

    public function index()
    {
        $installmentPlans = InstallmentPlan::with(['installments'])->latest()->paginate(15);
        $customers = Customer::where('is_active', true)->select('id', 'name', 'phone_number')->get();
        $sales = Sale::latest()->take(50)->get();

        return view('backend.installment_plans.index', compact('installmentPlans', 'customers', 'sales'));
    }

    public function storePlan(Request $request)
    {
        $request->validate([
            'sale_id' => 'required',
            'customer_id' => 'required',
            'total_amount' => 'required|numeric|min:1',
            'down_payment' => 'required|numeric|min:0',
            'months' => 'required|integer|min:1|max:60',
        ]);

        $sale = Sale::findOrFail($request->sale_id);
        $remaining = $request->total_amount - $request->down_payment;
        $monthlyEmi = $remaining / $request->months;

        DB::transaction(function () use ($request, $remaining, $monthlyEmi) {
            $plan = InstallmentPlan::create([
                'sale_id' => $request->sale_id,
                'customer_id' => $request->customer_id,
                'total_amount' => $request->total_amount,
                'down_payment' => $request->down_payment,
                'status' => 'active',
            ]);

            $startDate = now();
            for ($i = 1; $i <= $request->months; $i++) {
                $plan->installments()->create([
                    'status' => 'pending',
                    'reference_type' => 'sale',
                    'reference_id' => $request->sale_id,
                    'payment_date' => $startDate->copy()->addMonths($i),
                    'amount' => $monthlyEmi,
                ]);
            }
        });

        return redirect()->back()->with('message', 'Installment plan with ' . $request->months . ' monthly EMIs generated successfully.');
    }

    public function pay(Request $request, $id)
    {
        $installment = Installment::findOrFail($id);
        $installment->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        return redirect()->back()->with('message', 'Installment payment of ' . number_format($installment->amount, 2) . ' recorded successfully.');
    }

    public function show($id)
    {
        $plan = InstallmentPlan::with('installments')->findOrFail($id);
        $lims_gift_card_list = GiftCard::where("is_active", true)->get();
        $lims_pos_setting_data = PosSetting::latest()->first();
        $lims_reward_point_setting_data = RewardPointSetting::latest()->first();
        $lims_account_list = Account::where('is_active', true)->get();

        $options = $lims_pos_setting_data ? explode(',', $lims_pos_setting_data->payment_options) : [];

        return view('backend.installment_plans.show', compact('plan', 'lims_pos_setting_data', 'options', 'lims_reward_point_setting_data', 'lims_account_list', 'lims_gift_card_list'));
    }
}
