<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CarbonDateDifferencesTest extends TestCase
{
    use DatabaseTransactions;

    public function test_payroll_cards_count_positive_whole_minutes_for_complete_attendance(): void
    {
        $admin = User::firstOrFail();
        $company = Company::firstOrFail();
        $department = DB::table('departments')->insertGetId([
            'company_id' => $company->id,
            'name' => 'Payroll regression department',
            'is_active' => true,
        ]);
        $employee = DB::table('employees')->insertGetId([
            'company_id' => $company->id,
            'name' => 'Payroll regression employee',
            'email' => 'payroll-regression@example.test',
            'phone_number' => '0000000000',
            'department_id' => $department,
            'is_active' => true,
        ]);

        DB::table('attendances')->insert([
            ['date' => '2026-11-03', 'employee_id' => $employee, 'company_id' => $company->id, 'user_id' => $admin->id, 'checkin' => '09:00:00', 'checkout' => '17:30:40', 'status' => 1],
            ['date' => '2026-11-04', 'employee_id' => $employee, 'company_id' => $company->id, 'user_id' => $admin->id, 'checkin' => '09:00:00', 'checkout' => null, 'status' => 1],
        ]);

        $response = $this->actingAs($admin)->withSession(['company_id' => $company->id])
            ->post(route('payroll.generateCards'), ['month' => '2026-11', 'employee_ids' => [$employee]]);
        $response->assertOk();
        $this->assertSame('8.50', $response->viewData('employees')->sole()->total_work_hours);
    }

    public function test_due_views_show_positive_whole_days_on_both_sides_of_the_due_date(): void
    {
        $this->travelTo(Carbon::parse('2026-11-15 12:00:00'));
        $admin = User::firstOrFail();
        $company = Company::firstOrFail();
        $branch = $company->branches()->firstOrFail();
        $year = $company->fiscalYears()->firstOrFail();
        $warehouse = DB::table('warehouses')->where('company_id', $company->id)->where('branch_id', $branch->id)->firstOrFail();
        $biller = DB::table('billers')->where('company_id', $company->id)->firstOrFail();
        $customer = DB::table('customers')->where('company_id', $company->id)->firstOrFail();
        $supplier = DB::table('suppliers')->where('company_id', $company->id)->firstOrFail();
        DB::table('suppliers')->where('id', $supplier->id)->update(['credit_days' => 10]);

        $this->actingAs($admin)->withSession([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'financial_year_id' => $year->id,
        ]);

        foreach ([
            ['sales', 'receivables', ['customer_id' => $customer->id, 'biller_id' => $biller->id, 'total_price' => 100, 'sale_status' => 1, 'credit_days' => 10]],
            ['purchases', 'payables', ['supplier_id' => $supplier->id, 'total_cost' => 100, 'status' => 1]],
        ] as [$table, $reportType, $fields]) {
            foreach ([
                'past' => '2026-10-31 18:00:00',
                'today' => '2026-11-05 00:00:00',
                'future' => '2026-11-10 06:00:00',
            ] as $label => $date) {
                DB::table($table)->insert($fields + [
                    'reference_no' => 'carbon-'.$label,
                    'company_id' => $company->id,
                    'branch_id' => $branch->id,
                    'financial_year_id' => $year->id,
                    'warehouse_id' => $warehouse->id,
                    'user_id' => $admin->id,
                    'item' => 1,
                    'total_qty' => 1,
                    'total_discount' => 0,
                    'total_tax' => 0,
                    'grand_total' => 100,
                    'paid_amount' => 0,
                    'payment_status' => 1,
                    'created_at' => $date,
                    'posted_at' => $date,
                ]);
            }

            foreach ([
                route('dues.index', ['tab' => $table, 'search' => 'carbon-']),
                route('report.outstandingDues', ['type' => $reportType, 'search' => 'carbon-', 'end_date' => '2026-11-15']),
            ] as $url) {
                $response = $this->get($url)->assertOk();
                $rows = $response->viewData('rows')->keyBy('reference_no');
                foreach (['past' => [4, 0], 'today' => [0, 0], 'future' => [0, 5]] as $label => $expected) {
                    $row = $rows->get('carbon-'.$label);
                    $this->assertNotNull($row, $url);
                    $this->assertSame($expected, [$row->days_overdue, $row->days_left], $url);
                }
            }
        }
    }
}
