<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyGroup;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\Purchase;
use App\Services\Platform\CompanyInsightService;
use Tests\Support\OperationsTestCase;

class CompanyGroupInsightTest extends OperationsTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (require database_path('migrations/2026_10_13_000001_create_company_groups.php'))->up();
    }

    public function test_company_insight_computes_revenue_profit_margin_and_loss_status(): void
    {
        $service = app(CompanyInsightService::class);
        $insight = $service->companyInsight($this->company->id);

        $this->assertSame($this->company->id, $insight['company_id']);
        $this->assertArrayHasKey('revenue', $insight);
        $this->assertArrayHasKey('profit', $insight);
        $this->assertArrayHasKey('margin_percent', $insight);
        $this->assertArrayHasKey('is_loss', $insight);
        $this->assertArrayHasKey('receivables', $insight);
        $this->assertArrayHasKey('payables', $insight);
        $this->assertArrayHasKey('monthly_trend', $insight);
        $this->assertCount(6, $insight['monthly_trend']);
    }

    public function test_directory_insights_consolidates_group_rollups_and_detects_losses(): void
    {
        $group = CompanyGroup::create(['name' => 'Apex Group', 'code' => 'APEX']);
        $this->company->update(['company_group_id' => $group->id]);
        $this->other->update(['company_group_id' => $group->id]);

        $directory = [
            'groups' => [
                [
                    'id' => $group->id,
                    'name' => 'Apex Group',
                    'code' => 'APEX',
                    'companies' => [
                        ['id' => $this->company->id, 'name' => 'Company A', 'code' => 'CMPA', 'industry' => 'general_trading'],
                        ['id' => $this->other->id, 'name' => 'Company B', 'code' => 'CMPB', 'industry' => 'textile'],
                    ],
                ],
            ],
            'ungrouped' => [],
        ];

        $service = app(CompanyInsightService::class);
        $insights = $service->directoryInsights($directory);

        $this->assertArrayHasKey($group->id, $insights['groups']);
        $gRollup = $insights['groups'][$group->id];

        $this->assertSame('Apex Group', $gRollup['name']);
        $this->assertSame(2, $gRollup['company_count']);
        $this->assertArrayHasKey('total_revenue', $gRollup);
        $this->assertArrayHasKey('net_profit', $gRollup);
        $this->assertArrayHasKey('loss_count', $gRollup);
        $this->assertArrayHasKey('loss_companies', $gRollup);
        $this->assertArrayHasKey('profitable_count', $gRollup);
        $this->assertArrayHasKey('monthly_trend', $gRollup);
        $this->assertCount(6, $gRollup['monthly_trend']);
    }

    public function test_workspace_home_renders_master_group_card_and_big_company_cards(): void
    {
        $group = CompanyGroup::create(['name' => 'Apex Group', 'code' => 'APEX']);
        $this->company->update(['company_group_id' => $group->id]);
        $this->other->update(['company_group_id' => $group->id]);
        $this->other->users()->attach(1);

        $response = $this->get('/workspace');
        $response->assertOk();

        // Master Group Rollup Card elements & Visual Charts
        $response->assertSee('Master Group Rollup', false);
        $response->assertSee('Apex Group', false);
        $response->assertSee('APEX', false);
        $response->assertSee('Consolidated Revenue', false);
        $response->assertSee('Consolidated Net Profit', false);
        $response->assertSee('Revenue Distribution & Contribution', false);
        $response->assertSee('data-zw-master-trend', false);
        $response->assertSee('data-zw-donut-share', false);
        $response->assertSee('data-zw-donut-cost', false);
        $response->assertSee('zw-dues-meter-card', false);

        // Big Expanded Company Cards, Dedicated Monthly Charts & In-Card Switch action
        $response->assertSee('zw-company-card-expanded', false);
        $response->assertSee('Current company', false);
        $response->assertSee('Switch', false);
        $response->assertSee('Net Revenue', false);
        $response->assertSee('6-Month Sales Trajectory', false);
        $response->assertSee('data-zw-comp-trend', false);
        $response->assertSee('data-zw-comp-donut', false);
    }
}
