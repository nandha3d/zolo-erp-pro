<?php

namespace Tests\Feature;

use App\Services\Industry\IndustryProfileService;
use App\Services\Platform\{InventoryNavigation, NavigationAccess, WorkspaceNavigation};
use Illuminate\Support\Facades\DB;
use Tests\Support\OperationsTestCase;

class InventoryWorkspaceTest extends OperationsTestCase
{
    private function openInventory(): void
    {
        app(IndustryProfileService::class)->apply('textile', 'manufacturing', $this->context(), 1);
        $this->get('/operations/stock')->assertOk();
        request()->attributes->set(\App\Services\Platform\CompanyContext::class, $this->context());
        request()->setUserResolver(fn () => auth()->user());
    }

    public function test_inventory_has_three_destinations_and_preserves_restricted_permission_paths(): void
    {
        $this->openInventory();
        $navigation = app(InventoryNavigation::class);
        $sections = $navigation->sections(new NavigationAccess());
        $this->assertSame(['stock', 'corrections', 'transfers'], array_keys($sections));
        $this->assertSame(route('inventory-reports.stock-by-lot'), $sections['stock']['url']);
        $this->assertSame(route('stock-count.index'), $sections['corrections']['url']);
        $this->assertSame('/inventory-reports/stock-by-lot', collect(app(WorkspaceNavigation::class)->items($this->context(), 1))->firstWhere('label', 'Inventory')['url']);
        $sidebar = view('backend.layout.sidebar')->render();
        $document = new \DOMDocument(); @$document->loadHTML($sidebar);
        $xpath = new \DOMXPath($document);
        $this->assertSame(3, $xpath->query('//ul[@id="inventory"]/li')->length);
        $this->assertStringNotContainsString('Import Transfers</a>', $sidebar);

        DB::table('company_user')->where('company_id', $this->company->id)->where('user_id', 1)->update(['role_id_override' => 4]);
        foreach ([['sidebar_product', 'stock_count'], ['sidebar_product', 'adjustment'], ['sidebar_transfer', 'transfers-import']] as $permissions) {
            $access = new NavigationAccess(collect($permissions)->map(fn ($name) => (object) ['name' => $name]));
            $restricted = $navigation->sections($access);
            $this->assertCount(1, $restricted);
            $expected = in_array('stock_count', $permissions) ? route('stock-count.index')
                : (in_array('adjustment', $permissions) ? route('qty_adjustment.index') : url('/transfers/transfer_by_csv'));
            $this->assertSame($expected, reset($restricted)['url']);
        }
        $this->assertSame([], $navigation->sections(new NavigationAccess(collect())));
    }

    public function test_transfer_and_correction_views_share_inventory_navigation_without_changing_form_contracts(): void
    {
        $this->openInventory();
        $base = ['lims_warehouse_list' => DB::table('warehouses')->where('company_id', $this->company->id)->get(),
            'lims_tax_list' => collect(), 'lims_category_list' => collect(), 'lims_brand_list' => collect(),
            'lims_stock_count_all' => collect(), 'lims_adjustment_all' => collect(),
            'lims_product_list_without_variant' => collect(), 'lims_product_list_with_variant' => collect(),
            'starting_date' => '2026-10-01', 'ending_date' => '2026-10-03', 'from_warehouse_id' => 0, 'to_warehouse_id' => 0,
            'lims_product_transfer_data' => collect(),
            'lims_transfer_data' => (new \App\Models\Transfer)->forceFill(['id' => 999999, 'reference_no' => 'TRANSFER-FIXTURE', 'created_at' => now(),
                'from_warehouse_id' => 1, 'to_warehouse_id' => 2, 'status' => 1, 'shipping_cost' => 0, 'grand_total' => 0, 'note' => '']),
            'all_permission' => ['transfers-index', 'transfers-add', 'transfers-import', 'transfers-delete']];
        $paths = ['transfer/index' => '/transfers', 'transfer/create' => '/transfers/create', 'transfer/import' => '/transfers/transfer_by_csv', 'transfer/edit' => '/transfers/999999/edit',
            'stock_count/index' => '/stock-count', 'adjustment/index' => '/qty_adjustment'];
        foreach ($paths as $page => $path) {
            $request = \Illuminate\Http\Request::create($path);
            $request->attributes->set(\App\Services\Platform\CompanyContext::class, $this->context());
            $request->setUserResolver(fn () => auth()->user());
            $request->setLaravelSession(app('session.store'));
            $this->app->instance('request', $request);
            $html = view('backend.'.$page, $base)->render();
            $this->assertStringContainsString('aria-label="Inventory screens"', $html);
            $this->assertStringContainsString('Stock Corrections', $html);
            $sidebarDocument = new \DOMDocument(); @$sidebarDocument->loadHTML($html);
            $this->assertSame(1, (new \DOMXPath($sidebarDocument))->query('//ul[@id="inventory"]/li[contains(@class, "active")]')->length);
            if ($page === 'transfer/index') {
                $this->assertStringContainsString('id="transfer-filters"', $html);
                $this->assertStringContainsString('name="starting_date"', $html);
                $document = new \DOMDocument(); @$document->loadHTML($html);
                $xpath = new \DOMXPath($document);
                $this->assertSame(0, $xpath->query('//form[@id="transfer-filters"]//input[contains(@class, "daterangepicker-field")]')->length);
                $this->assertSame(10, $xpath->query('//table[@id="transfer-table"]/tfoot/tr/th')->length);
            }
            if ($page === 'transfer/create') $this->assertStringContainsString('id="transfer-form"', $html);
            if ($page === 'transfer/import') $this->assertStringContainsString('action="'.route('transfer.import').'"', $html);
            if (getenv('ERP_EXPORT_INVENTORY_BROWSER')) file_put_contents(base_path('scratch/inventory-'.str_replace('/', '-', $page).'.html'), $html);
        }
        $report = $this->get('/inventory-reports/stock-by-lot?from=2026-10-01&to=2026-10-03')->assertOk()->assertSee('Combined by product')->assertSee('Individual lots')->getContent();
        if (getenv('ERP_EXPORT_INVENTORY_BROWSER')) file_put_contents(base_path('scratch/inventory-stock.html'), $report);
    }
}
