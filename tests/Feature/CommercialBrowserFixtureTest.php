<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\Support\CommercialTestCase;

/** Opt-in disposable browser data, always written beneath scratch and never to a configured database. */
class CommercialBrowserFixtureTest extends CommercialTestCase
{
    public function test_render_fast_modes_and_optionally_export_disposable_browser_fixture(): void
    {
        $this->stock(100)->update(['unit_id' => 1]);
        // Fixture-only gate bypass; the real controller supplies the cumulative profile/printing contract.
        $this->withoutMiddleware(\App\Http\Middleware\RequireCapability::class);
        foreach (['sale', 'purchase'] as $kind) {
            $html = $this->get('/commercial/'.$kind.'/entry')->assertOk()->getContent();
            $this->assertStringContainsString('id="entry-form"', $html);
            $this->assertStringContainsString('id="tracking-dialog"', $html);
            $this->assertStringContainsString($kind === 'sale' ? 'Fast Sales' : 'Fast Purchase', $html);
        }
        if (getenv('ERP_EXPORT_COMMERCIAL_BROWSER') === '1') {
            $this->assertSame('sqlite', DB::connection()->getDriverName());
            $directory = base_path('scratch');
            if (!is_dir($directory)) { mkdir($directory, 0777, true); }
            $path = $directory.'/commercial-browser-'.bin2hex(random_bytes(6)).'.sqlite';
            DB::statement('VACUUM INTO '.DB::connection()->getPdo()->quote($path));
            file_put_contents($directory.'/commercial-browser-path.txt', $path);
        }
    }
}
