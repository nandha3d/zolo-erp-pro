<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Account;
use App\Models\Employee;
use App\Models\Payroll;
use Auth;
use DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Mail\PayrollDetails;
use App\Models\Attendance;
use App\Models\Expense;
use App\Models\Leave;
use Mail;
use App\Models\MailSetting;
use App\Models\Overtime;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\Warehouse;
use Illuminate\Support\Carbon;

class PayrollController extends Controller
{
    use \App\Traits\MailInfo;

    public function index()
    {
        $role = Role::find(Auth::user()->role_id);
        if(!$role->hasPermissionTo('payroll')){
            return redirect()->back()->with('not_permitted', __('db.Sorry! You are not allowed to access this module'));
        }

        $lims_account_list = Account::where('is_active', true)->get();
        $lims_employee_list = Employee::where('is_active', true)->get();
        $lims_warehouse_list = Warehouse::where('is_active', true)->get();
        $general_setting = DB::table('general_settings')->latest()->first();

        // Fetch payrolls with employee info, leaves, attendance, and work duration
        $lims_payroll_all = Payroll::with('employee')
            ->orderBy('id', 'desc')
            ->when(Auth::user()->role_id > 2 && $general_setting->staff_access == 'own', function($query){
                $query->where('user_id', Auth::id());
            })
            ->get()
            ->map(function($payroll){
                $employeeId = $payroll->employee_id;

                // Leaves count (approved leaves)
                $payroll->leaves = Leave::where('employee_id', $employeeId)
                                        ->where('status', 'approved')
                                        ->sum('days');

                // Attendance days count
                $payroll->attendance = Attendance::where('employee_id', $employeeId)
                                                ->where('status', 'Present')
                                                ->count();

                // Work duration in hours (checkout - checkin)
                $workDurationSeconds = Attendance::where('employee_id', $employeeId)
                                                ->where('status', 'Present')
                                                ->sum(DB::raw('TIME_TO_SEC(TIMEDIFF(checkout, checkin))'));
                $payroll->work_duration = round($workDurationSeconds / 3600, 2);

                return $payroll;
            });

        return view('backend.hrm.payroll.index', compact(
            'lims_warehouse_list',
            'lims_account_list',
            'lims_employee_list',
            'lims_payroll_all'
        ));
    }


    public function create()
    {
        //
    }

    public function store(Request $request)
    {
        $data = $request->all();
        if(isset($data['created_at']))
            $data['created_at'] = date("Y-m-d", strtotime(str_replace("/", "-", $data['created_at'])));
        else
            $data['created_at'] = date("Y-m-d");
        $data['reference_no'] = 'payroll-' . date("Ymd") . '-'. date("his");
        $data['user_id'] = Auth::id();
        Payroll::create($data);
        $message = 'Payroll creared succesfully';
        //collecting mail data
        $lims_employee_data = Employee::find($data['employee_id']);
        $mail_data['reference_no'] = $data['reference_no'];
        $mail_data['amount'] = $data['amount'];
        $mail_data['name'] = $lims_employee_data->name;
        $mail_data['email'] = $lims_employee_data->email;
        $mail_data['currency'] = config('currency');
        $mail_setting = MailSetting::latest()->first();
        if($mail_setting) {
            $this->setMailInfo($mail_setting);
            try{
                Mail::to($mail_data['email'])->send(new PayrollDetails($mail_data));
            }
            catch(\Exception $e){
                $message = ' Payroll created successfully. Please setup your <a href="setting/mail_setting">mail setting</a> to send mail.';
            }
        }
        return redirect('payroll')->with('message', $message);
    }

    public function edit($id)
    {
        //
    }

    public function update(Request $request, $id)
    {
        try {
            $data = $request->all();

            // Format date
            if (isset($data['created_at']) && !empty($data['created_at'])) {
                $data['created_at'] = date("Y-m-d", strtotime(str_replace("/", "-", $data['created_at'])));
            } else {
                $data['created_at'] = date("Y-m-d");
            }

            // Find payroll
            $payroll = Payroll::findOrFail($request->payroll_id);

            // Get input values
            $salary = floatval($data['salary_amount'] ?? 0);
            $previous = floatval($data['expense'] ?? 0);
            $commissionInput = floatval($data['commission'] ?? 0);
            $isAgent = intval($data['is_agent'] ?? 0); // optional if you track agents
            $percent = floatval($data['commission_percent'] ?? 0);

            // Calculate commission if needed
            $commission = $commissionInput;
            if ($isAgent && $percent > 0) {
                $commission = ($salary * $percent) / 100;
            }

            // Calculate total
            $total = $request->amount;

            // Store calculated totals in an array (optional)
            $amountArray = [
                'salary' => $salary,
                'commission' => $commission,
                'previous' => $previous,
                'total' => $request->amount,
            ];

            // Update payroll
            $payroll->update([
                'employee_id' => $data['employee_id'] ?? $payroll->employee_id,
                'account_id' => $data['account_id'] ?? $payroll->account_id,
                'amount' => $total,
                'salary_amount' => $salary,
                'commission' => $commission,
                'expense' => $previous,
                'paying_method' => $data['paying_method'] ?? $payroll->paying_method,
                'note' => $data['note'] ?? $payroll->note,
                'month' => $data['month'] ?? $payroll->month,
                'created_at' => $data['created_at'],
                'amount_array' => json_encode($amountArray),
            ]);

            return redirect()->route('payroll.index')->with('message', __('db.Payroll updated successfully'));

        } catch (\Exception $e) {
            dd($e);
            \Log::error('Payroll update error: ' . $e->getMessage());
            return redirect()->back()->with('error', __('db.Something went wrong while updating payroll'));
        }
    }


    public function deleteBySelection(Request $request)
    {
        $payroll_id = $request['payrollIdArray'];
        foreach ($payroll_id as $id) {
            $lims_payroll_data = Payroll::find($id);
            $lims_payroll_data->delete();
        }
        return 'Payroll deleted successfully!';
    }

    public function destroy($id)
    {
        $lims_payroll_data = Payroll::find($id);
        $lims_payroll_data->delete();
        return redirect('payroll')->with('not_permitted', __('db.Payroll deleted succesfully'));
    }

    public function monthlyData(Request $request)
    {
        $employeeId = $request->employee_id;
        $month = $request->month;


        $dummyData = [
            1 => ['salary' => 25000, 'transactions' => 1200, 'commission' => 800],
            2 => ['salary' => 30000, 'transactions' => 2500, 'commission' => 1500],
            3 => ['salary' => 18000, 'transactions' => 1000, 'commission' => 500],
            4 => ['salary' => 22000, 'transactions' => 0, 'commission' => 2000],
            5 => ['salary' => 27000, 'transactions' => 500, 'commission' => 1200],
        ];


        $data = $dummyData[$employeeId] ?? ['salary' => 20000, 'transactions' => 2500, 'commission' => 1500];


        if ($month == '2025-01') {
            $data['commission'] += 500;
        } elseif ($month == '2025-02') {
            $data['transactions'] += 300;
        }

        return response()->json($data);
    }

    public function getEmployeesByWarehouse(Request $request)
    {
        $warehouse_id = $request->warehouse_id;
        if (!$warehouse_id || $warehouse_id == 0) {
            $employees = \App\Models\Employee::where('is_active', true)
                ->get(['id', 'name']);
        } else {
            $employees = \App\Models\Employee::whereHas('user', function ($query) use ($warehouse_id) {
                            $query->where('warehouse_id', $warehouse_id);
                        })
                        ->where('is_active', true)
                        ->get(['id', 'name']);
        }

        return response()->json($employees);
    }



    public function storeMultiple(Request $request)
    {
        $payrolls = $request->input('payrolls', []);
        if (empty($payrolls)) {
            return redirect()->route('payroll.index')->with('error', 'No payroll data found!');
        }

        try {
            foreach ($payrolls as $empId => $payrollData) {

                if (!isset($payrollData['employee_id']) || !isset($payrollData['amount'])) {
                    continue;
                }

                // Reference No
                $reference_no = 'payroll-' . date("Ymd") . '-' . date("His") . '-' . $empId;

                // Calculate totals
                $salary = floatval($payrollData['amount']);
                $expense = floatval($payrollData['expense'] ?? 0);
                $overtime = floatval($payrollData['overtime'] ?? 0);
                $commission = floatval($payrollData['commission'] ?? 0);
                $total = $salary + $commission - $expense;

                $amountArray = [
                    'salary' => $salary,
                    'commission' => $commission,
                    'expense' => $expense,
                    'overtime' => $overtime,
                    'total' => $total,
                ];


                // ✅ Check if payroll for this employee & month already exists
                $existingPayroll = Payroll::where('employee_id', $payrollData['employee_id'])
                    ->where('month', $request->month)
                    ->first();

                if ($existingPayroll) {
                    // Update existing payroll
                    $existingPayroll->update([
                        'reference_no' => $reference_no,
                        'user_id' => Auth::id(),
                        'account_id' => $request->account_id ?? 0,
                        'amount' => $total,
                        'paying_method' => $payrollData['paying_method'] ?? 'Cash',
                        'note' => $payrollData['note'] ?? null,
                        'status' => $request->payroll_group_status ?? 'draft',
                        'amount_array' => json_encode($amountArray),
                    ]);
                    $payroll = $existingPayroll;
                } else {
                    // Create new payroll
                    $payroll = Payroll::create([
                        'reference_no' => $reference_no,
                        'employee_id' => $payrollData['employee_id'],
                        'user_id' => Auth::id(),
                        'account_id' => $request->account_id ?? 0,
                        'amount' => $total,
                        'paying_method' => $payrollData['paying_method'] ?? 'Cash',
                        'note' => $payrollData['note'] ?? null,
                        'status' => $request->payroll_group_status ?? 'draft',
                        'amount_array' => json_encode($amountArray),
                        'month' => $request->month,
                    ]);
                }

                // Send email
                $employee = Employee::find($payrollData['employee_id']);
                if ($employee) {
                    $mail_data = [
                        'reference_no' => $reference_no,
                        'amount' => $total,
                        'name' => $employee->name,
                        'email' => $employee->email,
                        'currency' => config('currency'),
                    ];

                    $mail_setting = MailSetting::latest()->first();
                    if ($mail_setting) {
                        $this->setMailInfo($mail_setting);

                        try {
                            Mail::to($mail_data['email'])->send(new PayrollDetails($mail_data));
                        } catch (\Exception $e) {
                            \Log::error('Mail send failed: ' . $e->getMessage());
                        }
                    }
                }
            }

            return redirect()->route('payroll.index')->with('message', 'All payrolls processed successfully!');

        } catch (\Exception $e) {
            \Log::error('Payroll store error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Something went wrong while generating payrolls.');
        }
    }

    public function generateCards(Request $request)
    {
        $warehouse_id = $request->warehouse_id;
        $month = $request->month; // Format: YYYY-MM
        $employee_ids = $request->employee_ids;

        // Parse start and end of month from YYYY-MM format
        $monthStart = Carbon::createFromFormat('Y-m', $month)->startOfMonth()->toDateString();
        $monthEnd = Carbon::createFromFormat('Y-m', $month)->endOfMonth()->toDateString();

        // Get employees (all active or filtered)
        if (!$employee_ids || count($employee_ids) == 0) {
            $employees = Employee::where('is_active', true)->get();
        } else {
            $employees = Employee::whereIn('id', $employee_ids)
                ->where('is_active', true)->get();
        }

        $warehouse = $warehouse_id ? Warehouse::find($warehouse_id) : null;
        $lims_warehouse_list = Warehouse::where('is_active', true)->get();
        $lims_account_list = Account::where('is_active', true)->get();

        foreach ($employees as $employee) {

            // Check if payroll exists for this employee and month
            $existingPayroll = Payroll::where('employee_id', $employee->id)
                ->where('month', $month)
                ->first();

            // Leaves: approved leaves in this month
            $leaves = Leave::where('employee_id', $employee->id)
                ->where('status', 'approved')
                ->where(function($q) use ($monthStart, $monthEnd) {
                    $q->whereBetween('start_date', [$monthStart, $monthEnd])
                    ->orWhereBetween('end_date', [$monthStart, $monthEnd])
                    ->orWhere(function($q2) use ($monthStart, $monthEnd) {
                        $q2->where('start_date', '<', $monthStart)
                            ->where('end_date', '>', $monthEnd);
                    });
                })->get();

            // Attendance dates in the month
            $attendanceDates = Attendance::where('employee_id', $employee->id)
                ->whereBetween('date', [$monthStart, $monthEnd])
                ->pluck('date')
                ->map(fn($d) => Carbon::parse($d)->toDateString())
                ->toArray();

            $totalLeaveDays = 0;

            foreach ($leaves as $leave) {
                $start = Carbon::parse($leave->start_date)->greaterThan($monthStart) ? $leave->start_date : $monthStart;
                $end   = Carbon::parse($leave->end_date)->lessThan($monthEnd) ? $leave->end_date : $monthEnd;

                for ($date = Carbon::parse($start); $date->lte(Carbon::parse($end)); $date->addDay()) {
                    if (!in_array($date->toDateString(), $attendanceDates)) {
                        $totalLeaveDays++;
                    }
                }
            }
            $employee->total_leaves = $totalLeaveDays;

            // Attendance days
            $employee->attendance_days = Attendance::where('employee_id', $employee->id)
                ->whereBetween('date', [$monthStart, $monthEnd])
                ->count();

            // Total work hours
            $attendances = Attendance::where('employee_id', $employee->id)
                ->whereBetween('date', [$monthStart, $monthEnd])
                ->get();

            $totalHours = 0;
            foreach ($attendances as $att) {
                if ($att->checkin && $att->checkout) {
                    $checkin = Carbon::parse($att->checkin);
                    $checkout = Carbon::parse($att->checkout);
                    $totalHours += $checkout->diffInMinutes($checkin) / 60;
                }
            }
            $employee->total_work_hours = number_format($totalHours, 2);

            // Total sales for sale agents
            if ($employee->is_sale_agent) {
                $employee->total_sales = Payment::where('user_id', $employee->user_id)
                    ->whereBetween('payment_at', [$monthStart, $monthEnd])
                    ->sum('amount');
            } else {
                $employee->total_sales = 0;
            }

            // Employee Expenses
            $employee->expenses = Expense::where('employee_id', $employee->id)
                ->where('expense_category_id', 0)
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->sum('amount');

            // Overtime: approved hours & amount
            $employee->overtime_hours = Overtime::where('employee_id', $employee->id)
                ->where('status', 'approved')
                ->whereBetween('date', [$monthStart, $monthEnd])
                ->sum('hours');

            $employee->overtime_amount = Overtime::where('employee_id', $employee->id)
                ->where('status', 'approved')
                ->whereBetween('date', [$monthStart, $monthEnd])
                ->sum('amount');

            // Payroll existing
            if ($existingPayroll) {
                $amountArray = json_decode($existingPayroll->amount_array, true);
                $employee->existing_payroll = [
                    'salary' => $amountArray['salary'] ?? ($existingPayroll->amount ?? 0),
                    'commission' => $amountArray['commission'] ?? 0,
                    'expense' => $amountArray['expense'] ?? ($employee->expense ?? 0),
                    'overtime' => $amountArray['overtime'] ?? ($employee->overtime ?? 0),
                    'total_amount' => $amountArray['total'] ?? ($existingPayroll->amount ?? 0),
                    'method' => $existingPayroll->paying_method ?? '0',
                    'note' => $existingPayroll->note ?? '',
                    'status' => $existingPayroll->status ?? 'draft',
                    'date' => Carbon::parse($existingPayroll->created_at)->format('d-m-Y'),
                ];
            } else {
                $employee->existing_payroll = [
                    'salary' => $employee->basic_salary,
                    'commission' => 0,
                    'expense' => $employee->expenses,
                    'overtime' => $employee->overtime_amount ?? 0,
                    'total_amount' => 0,
                    'method' => '0',
                    'note' => '',
                    'status' => 'draft',
                    'date' => now()->format('d-m-Y'),
                ];
            }
        }

        return view('backend.hrm.payroll.generate-payroll', compact(
            'warehouse',
            'lims_account_list',
            'employees',
            'month',
            'warehouse_id',
            'lims_warehouse_list'
        ));
    }



}
