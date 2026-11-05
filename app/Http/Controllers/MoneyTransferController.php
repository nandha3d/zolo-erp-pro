<?php

namespace App\Http\Controllers;

use App\Models\MoneyTransfer;
use App\Models\Account;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Auth;

class MoneyTransferController extends Controller
{
    use \App\Http\Controllers\Concerns\NumbersLegacyDocuments;

    public function index()
    {
        if (config('commercial.enabled')) {
            $context = app(\App\Services\Platform\CompanyContextResolver::class)->forActor();
            app(\App\Services\Commercial\CommercialPermission::class)->assert('money-transfer', $context, Auth::id());
            $lims_money_transfer_all = MoneyTransfer::forCompany($context)->with(['fromAccount', 'toAccount'])->get();
            $ids = array_column(app(\App\Services\Accounting\SettlementAccountService::class)->options($context), 'id');
            $lims_account_list = Account::forCompany($context)->whereIn('id', $ids)->get();
            return view('backend.money_transfer.index', compact('lims_money_transfer_all', 'lims_account_list'));
        }
        $role = Role::find(Auth::user()->role_id);
        if($role->hasPermissionTo('money-transfer')){
            $lims_money_transfer_all = MoneyTransfer::get();
            $lims_account_list = Account::where('is_active', true)->get();
            return view('backend.money_transfer.index', compact('lims_money_transfer_all', 'lims_account_list'));
        }
        else
            return redirect()->back()->with('not_permitted', __('db.Sorry! You are not allowed to access this module'));
    }

    public function create()
    {
        //
    }

    public function store(Request $request)
    {
        abort_if(config('commercial.enabled'), 409, 'Create cash/bank transfers as Contra vouchers in Voucher Hub so both COA ledgers are posted.');
        $data = $request->all();
        \Illuminate\Support\Facades\DB::transaction(function () use ($data) {
            $numberReservation = $this->reserveNumber('money_transfer');
            $transfer = MoneyTransfer::create($data + ['reference_no' => $numberReservation->formatted_number]);
            $this->assignNumber($numberReservation, $transfer);
        });
        return redirect()->back()->with('message', __('db.Money transfered successfully'));
    }

    public function update(Request $request, $id)
    {
        abort_if(config('commercial.enabled'), 409, 'Legacy transfer history is read-only. Correct posted transfers with a Contra voucher reversal.');
        $data = $request->all();
        MoneyTransfer::find($data['id'])->update($data);
        return redirect()->back()->with('message', __('db.Money transfer updated successfully'));
    }

    public function destroy($id)
    {
        abort_if(config('commercial.enabled'), 409, 'Legacy transfer history is read-only. Correct posted transfers with a Contra voucher reversal.');
        MoneyTransfer::find($id)->delete();
        return redirect()->back()->with('not_permitted', __('db.Data deleted successfully'));
    }
}
