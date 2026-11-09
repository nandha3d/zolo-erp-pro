<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Company;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Product_Warehouse;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class StockReportTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::first() ?? User::factory()->create(['role_id' => 1]);
        $this->actingAs($this->user);

        $this->company = Company::first();
        if ($this->company) {
            $this->withSession(['company_id' => $this->company->id]);
        }
    }

    public function test_sidebar_contains_stock_report_and_inventory_links(): void
    {
        $response = $this->get('/dashboard');
        $response->assertOk();

        // Assert Stock Report is in the sidebar under Inventory
        $response->assertSee('id="stock-report-menu"', false);
        $response->assertSee(route('report.stock'), false);
        $response->assertSee('Stock Report', false);

        // Assert existing warehouse stock chart and product reports still exist
        $response->assertSee('id="warehouse-stock-report-menu"', false);
        $response->assertSee(route('report.warehouseStock'), false);
        $response->assertSee('id="product-report-menu"', false);
        $response->assertSee(route('report.product'), false);
    }

    public function test_stock_report_page_loads_successfully_with_kpis_and_table(): void
    {
        $response = $this->get(route('report.stock'));
        $response->assertOk();
        $response->assertViewIs('backend.report.stock_report');

        // Check Command Center and KPI strip
        $response->assertSee('Stock Report Command Center', false);
        $response->assertSee('TOTAL CATALOG SKUS', false);
        $response->assertSee('TOTAL STOCK UNITS', false);
        $response->assertSee('STOCK VALUE (BY COST)', false);
        $response->assertSee('STOCK VALUE (BY RETAIL)', false);
        $response->assertSee('PROJECTED GROSS PROFIT', false);

        // Check Table & Desk structure
        $response->assertSee('id="stock-inventory-table"', false);
        $response->assertSee('Filtered Inventory Totals:', false);
        $response->assertSee('data-filter="all"', false);
        $response->assertSee('data-filter="in_stock"', false);
        $response->assertSee('data-filter="low_stock"', false);
        $response->assertSee('data-filter="out_of_stock"', false);

        // Check Action buttons
        $response->assertSee('Print Statement', false);
        $response->assertSee('Export CSV', false);
    }

    public function test_stock_report_warehouse_filter(): void
    {
        $warehouse = Warehouse::where('is_active', true)->first();
        $this->assertNotNull($warehouse, 'At least one active warehouse must exist');

        $response = $this->get(route('report.stock', ['warehouse_id' => $warehouse->id]));
        $response->assertOk();
        $response->assertSee($warehouse->name, false);
    }

    public function test_stock_report_category_filter(): void
    {
        $category = Category::where('is_active', true)->first();
        if ($category) {
            $response = $this->get(route('report.stock', ['category_id' => $category->id]));
            $response->assertOk();
            $response->assertSee($category->name);
        }
    }

    public function test_stock_report_stock_status_filter(): void
    {
        // Filter by in_stock
        $responseInStock = $this->get(route('report.stock', ['stock_status' => 'in_stock']));
        $responseInStock->assertOk();

        // Filter by low_stock
        $responseLowStock = $this->get(route('report.stock', ['stock_status' => 'low_stock']));
        $responseLowStock->assertOk();

        // Filter by out_of_stock
        $responseOut = $this->get(route('report.stock', ['stock_status' => 'out_of_stock']));
        $responseOut->assertOk();
    }

    public function test_stock_report_data_ajax_endpoint(): void
    {
        $response = $this->post(route('report.stockData'), [
            'draw' => 1,
            'warehouse_id' => 0,
            'category_id' => 0,
            'brand_id' => 0,
            'stock_status' => 'all',
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'draw',
            'recordsTotal',
            'recordsFiltered',
            'data',
        ]);
    }

    public function test_warehouse_stock_chart_cross_links_to_stock_report(): void
    {
        $response = $this->get(route('report.warehouseStock'));
        $response->assertOk();
        $response->assertSee(route('report.stock'), false);
        $response->assertSee('Stock Report', false);
    }

    public function test_stock_report_date_range_filter(): void
    {
        $startDate = '2026-01-01';
        $endDate = '2026-11-09';

        $response = $this->get(route('report.stock', [
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]));
        $response->assertOk();

        // Assert date range inputs are present in the filter card
        $response->assertSee('daterangepicker-field', false);
        $response->assertSee('name="start_date"', false);
        $response->assertSee('name="end_date"', false);
        $response->assertSee('value="2026-01-01 To 2026-11-09"', false);
        $response->assertSee('Period: <strong>01 Jan 2026 — 09 Nov 2026</strong>', false);
    }

    public function test_stock_report_print_and_screen_layout_standards(): void
    {
        $response = $this->get(route('report.stock'));
        $response->assertOk();
        $html = $response->getContent();

        // 1. Assert exactly ONE .erp-print-header in the rendered HTML (no duplicate header overlay)
        $this->assertEquals(1, substr_count($html, 'class="erp-print-header"'), 'There must be exactly one .erp-print-header on the page');

        // 2. Assert screen flex unconstraining rules are present to prevent vertical clipping
        $response->assertSee('html.app-shell #content > .report-workspace-view', false);
        $response->assertSee('html.app-shell #content .report-workspace-view .desk-card-wrapper', false);

        // 3. Assert explicit 8-column layout classes for zero-overflow A4 print
        $response->assertSee('col-product', false);
        $response->assertSee('col-category', false);
        $response->assertSee('col-warehouse', false);
        $response->assertSee('col-qty', false);
        $response->assertSee('col-cost', false);
        $response->assertSee('col-val-cost', false);
        $response->assertSee('col-price', false);
        $response->assertSee('col-val-price', false);
        $response->assertSee('erp-print-signatures', false);

        // 4. Assert row attributes for zero-TypeError numeric recalculation
        $response->assertSee('data-cost-val', false);
        $response->assertSee('data-price-val', false);

        // 5. Assert print safety hides style and script tags to avoid raw CSS printing
        $response->assertSee('style,', false);
        $this->assertStringNotContainsString('<div id="content"><style>', $html);
    }
}
