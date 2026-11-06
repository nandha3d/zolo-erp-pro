<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Company;
use App\Models\Warehouse;
use App\Services\Platform\CompanyContextResolver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardSmokeTest extends TestCase
{
    use DatabaseTransactions;

    public function test_dashboard_renders_content(): void
    {
        $user = User::first();
        $company = Company::first();

        $response = $this->actingAs($user)
            ->withSession([
                'company_id' => $company->id,
                'branch_id' => $company->branches()->first()->id ?? 1,
            ])
            ->get('/dashboard');

        $response->assertOk()
            ->assertSeeInOrder(['Financial overview', 'Cash Flow', 'Monthly breakdown', 'Recent activity', 'Stock alerts', 'Sales and purchases', 'Best sellers this month'])
            ->assertDontSee('data-step-title="Cash flow"', false)
            ->assertDontSee('Industry addons');

        if (getenv('ERP_EXPORT_DASHBOARD_BROWSER')) {
            file_put_contents(base_path('scratch/dashboard-browser.html'), $response->getContent());
        }
    }

    public function test_dashboard_totals_include_base_currency_bills_and_exclude_purchase_orders(): void
    {
        $user = User::where('role_id', 1)->firstOrFail();
        $company = Company::firstOrFail();
        $branch = $company->branches()->firstOrFail();
        $context = app(CompanyContextResolver::class)->resolve($user->id, $company->id, $branch->id);
        $year = $company->fiscalYears()->findOrFail($context->financialYearId);
        $warehouse = Warehouse::forCompany($context)->where('branch_id', $branch->id)->where('is_active', true)->firstOrFail();
        $date = max($year->start_date->toDateString(), min(now()->toDateString(), $year->end_date->toDateString()));

        $this->actingAs($user)->withSession([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'financial_year_id' => $year->id,
        ]);
        $filter = "/dashboard-filter/{$date}/{$date}/{$warehouse->id}";
        $before = $this->getJson($filter)->assertOk()->json();

        $bill = [
            'company_id' => $company->id, 'branch_id' => $branch->id, 'financial_year_id' => $year->id,
            'warehouse_id' => $warehouse->id, 'user_id' => $user->id, 'created_at' => $date,
            'item' => 1, 'total_qty' => 1, 'total_discount' => 0, 'total_tax' => 0, 'total_cost' => 125,
            'grand_total' => 125, 'paid_amount' => 20, 'exchange_rate' => null, 'status' => 1, 'payment_status' => 1,
        ];
        DB::table('purchases')->insert([
            ['reference_no' => 'DASHBOARD-TEST-BILL'] + $bill,
            ['reference_no' => 'DASHBOARD-TEST-ORDER', 'status' => 4] + $bill,
        ]);

        $after = $this->getJson($filter)->assertOk()->json();
        $this->assertEqualsWithDelta(125, $after[6] - $before[6], 0.01);
        $this->assertEqualsWithDelta(105, $after[7] - $before[7], 0.01);
        $this->get('/dashboard?period=today&warehouse_id='.$warehouse->id)->assertOk()
            ->assertSee('Period breakdown')
            ->assertViewHas('purchase', $after[6]);
    }
}
