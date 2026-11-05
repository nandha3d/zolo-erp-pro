<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Company;
use Tests\TestCase;

class DashboardSmokeTest extends TestCase
{
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
}
