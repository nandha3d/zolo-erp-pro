<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\Support\CommercialTestCase;

/** Opt-in disposable browser data, always written beneath scratch and never to a configured database. */
class CommercialBrowserFixtureTest extends CommercialTestCase
{
    public function test_render_fast_modes_and_optionally_export_disposable_browser_fixture(): void
    {
        $this->withoutExceptionHandling();
        $this->stock(100)->update(['unit_id' => 1]);
        // Fixture-only gate bypass; the real controller supplies the cumulative profile/printing contract.
        $this->withoutMiddleware(\App\Http\Middleware\RequireCapability::class);
        foreach (['sale', 'purchase'] as $kind) {
            $html = $this->get(($kind === 'sale' ? '/sales' : '/purchases').'?entry=fast')->assertOk()->getContent();
            $this->assertStringContainsString('id="entry-form"', $html);
            $this->assertStringContainsString('id="tracking-dialog"', $html);
            $this->assertStringContainsString($kind === 'sale' ? 'Sales Command Center' : 'Purchase Command Center', $html);
            $this->assertStringContainsString('command-center-nav', $html);
            $this->assertStringNotContainsString('id="desk-sidebar"', $html);
            $this->assertStringNotContainsString('/commercial/'.$kind.'/entry', $html);
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

    public function test_old_entry_links_redirect_to_canonical_modes_and_preserve_workflow_context(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\RequireCapability::class);
        $this->get('/commercial/sale/entry?project_id=12&exchange_return_id=34')
            ->assertRedirect(route('sales.index', ['project_id' => 12, 'exchange_return_id' => 34, 'entry' => 'fast']));
        $this->get('/commercial/purchase/entry')->assertRedirect(route('purchases.index', ['entry' => 'fast']));
    }

    public function test_canonical_fast_modes_keep_feature_and_capability_gates(): void
    {
        config(['commercial.enabled' => false]);
        foreach (['/sales', '/purchases'] as $path) {
            $this->getJson($path.'?entry=fast')->assertStatus(503);
        }
        config(['commercial.enabled' => true]);
        $this->app->instance(\App\Services\Platform\CapabilityService::class, new class extends \App\Services\Platform\CapabilityService {
            public function enabled(string $key, \App\Services\Platform\CompanyContext|int|null $company = null): bool { return false; }
        });
        foreach (['/sales', '/purchases'] as $path) {
            $this->getJson($path.'?entry=fast')->assertForbidden();
        }
    }

    public function test_canonical_fast_modes_keep_company_role_permissions(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\RequireCapability::class);
        \Illuminate\Support\Facades\Schema::create('permissions', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->increments('id'); $table->string('name');
        });
        \Illuminate\Support\Facades\Schema::create('role_has_permissions', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->unsignedInteger('role_id'); $table->unsignedInteger('permission_id');
        });
        DB::table('company_user')->where('company_id', $this->company->id)->where('user_id', 1)->update(['role_id_override' => 4]);
        foreach (['/sales', '/purchases'] as $path) {
            $this->getJson($path.'?entry=fast')->assertForbidden();
        }
    }
}
