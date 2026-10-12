<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PagePerformanceWebTest extends TestCase
{
    use DatabaseTransactions;

    public function test_register_keeps_exports_without_loading_heavy_tools_or_waiting_for_window_load(): void
    {
        $html = $this->actingAs(User::first())->get('/customer')->assertOk()->getContent();
        $this->assertStringContainsString('js/table-exports.js', $html);
        $this->assertStringContainsString('vendor/keyboard/js/jquery.keyboard.js', $html);
        $this->assertStringContainsString('vendor/datatable/dataTables.responsive.min.js', $html);
        foreach (['pdfmake.min.js', 'vfs_fonts.js', 'jszip.min.js', 'tinymce.min.js', 'Chart.min.js', 'cdn.datatables.net', 'onload="myFunction()"'] as $asset) {
            $this->assertStringNotContainsString($asset, $html);
        }
        $this->assertStringContainsString('$(showPage)', $html);
    }

    public function test_dashboard_and_product_form_keep_required_tools(): void
    {
        $this->actingAs(User::first())->get('/dashboard')->assertOk()->assertSee('vendor/chart.js/Chart.min.js', false);
        $this->get('/products/create')->assertOk()->assertSee('tinymce.min.js', false)->assertSee('js/dropzone.js', false);
    }
}
