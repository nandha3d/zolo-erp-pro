<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CompanyContextTestCase;

class MaintenanceEndpointSecurityTest extends CompanyContextTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app->bind(VerifyCsrfToken::class, EnforcedMaintenanceCsrfToken::class);
        Schema::table('users', fn (Blueprint $table) => $table->timestamps());
        Schema::create('permissions', function (Blueprint $table) {
            $table->id(); $table->string('name'); $table->string('guard_name'); $table->timestamps();
        });
        Schema::create('role_has_permissions', function (Blueprint $table) {
            $table->unsignedInteger('role_id'); $table->unsignedBigInteger('permission_id');
        });
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_cache_clear_requires_post_authentication_admin_permission_and_csrf(): void
    {
        Artisan::shouldReceive('call')->never();
        $this->getJson('/clear')->assertStatus(405);
        $this->withSession(['_token' => 'csrf-fixture'])->postJson('/clear', ['_token' => 'csrf-fixture'])->assertUnauthorized();
        $operator = User::findOrFail(2);
        $operator->update(['role_id' => 4]);
        $this->actingAs($operator)->postJson('/clear', ['_token' => 'csrf-fixture'])->assertForbidden();
        $this->actingAs(User::findOrFail(1))->postJson('/clear')->assertStatus(419);
        $this->postJson('/clear', ['_token' => 'wrong-token'])->assertStatus(419);
    }

    public function test_admin_cache_clear_removes_keys_and_logs_only_correlation_metadata(): void
    {
        Log::spy();
        Artisan::shouldReceive('call')->once()->with('optimize:clear')->andReturnUsing(fn () => cache()->flush() ? 0 : 1);
        cache()->put('warehouse_list', 'cached fixture');
        cache()->put('role_has_permissions_list1', 'cached permissions');
        $this->actingAs(User::findOrFail(1))->withSession(['_token' => 'csrf-fixture', 'company_id' => $this->company->id])
            ->postJson('/clear', ['_token' => 'csrf-fixture', 'token' => 'secret-fixture'], ['X-Request-ID' => 'cache_review_123'])
            ->assertOk()->assertJsonPath('status', 'success')->assertHeader('X-Request-ID', 'cache_review_123');
        $this->assertNull(cache()->get('warehouse_list'));
        $this->assertNull(cache()->get('role_has_permissions_list1'));
        Log::shouldHaveReceived('info')->with('erp.audit', \Mockery::on(fn (array $metadata) =>
            array_keys($metadata) === ['action', 'actor_id', 'company_id', 'request_id', 'timestamp']
            && $metadata['action'] === 'cache_clear' && $metadata['actor_id'] === 1
            && $metadata['company_id'] === $this->company->id && $metadata['request_id'] === 'cache_review_123'
            && is_string($metadata['timestamp']) && !str_contains(json_encode($metadata), 'secret-fixture')))->once();
    }

    public function test_cache_clear_rejects_inactive_admin_and_forged_company_session(): void
    {
        Artisan::shouldReceive('call')->never();
        $admin = User::findOrFail(1);
        $this->actingAs($admin)->withSession(['_token' => 'csrf-fixture', 'company_id' => $this->other->id])
            ->postJson('/clear', ['_token' => 'csrf-fixture'])->assertForbidden();
        $admin->update(['is_active' => false]);
        $this->withSession(['company_id' => $this->company->id])->postJson('/clear', ['_token' => 'csrf-fixture'])->assertForbidden();
    }

    public function test_cache_clear_does_not_report_failed_command_as_success(): void
    {
        Artisan::shouldReceive('call')->once()->with('optimize:clear')->andReturn(1);
        Log::spy();
        $this->actingAs(User::findOrFail(1))->withSession(['_token' => 'csrf-fixture'])
            ->postJson('/clear', ['_token' => 'csrf-fixture'])->assertStatus(500);
        Log::shouldNotHaveReceived('info', ['erp.audit', \Mockery::any()]);
    }

    #[DataProvider('allowedRedirects')]
    public function test_webview_auth_accepts_internal_destinations_and_merges_query(string $destination, string $expected): void
    {
        $token = User::findOrFail(1)->createToken('webview')->plainTextToken;
        $this->getJson('/webview/auth?'.http_build_query(['redirect' => $destination]), ['Authorization' => 'Bearer '.$token])
            ->assertRedirect($expected);
        $this->assertAuthenticatedAs(User::findOrFail(1));
        $this->assertNotNull(DB::table('personal_access_tokens')->value('last_used_at'));
        $this->assertSame(['web'], config('sanctum.guard'));
    }

    public static function allowedRedirects(): array
    {
        return [
            ['/', '/?app=true'], ['/dashboard', '/dashboard?app=true'], ['/sales', '/sales?app=true'],
            ['/products?status=active', '/products?status=active&app=true'],
            ['/products?app=false&status=active#stock', '/products?app=true&status=active#stock'],
            ['/products?search=hello%20world&filter=x%26y%3Dz', '/products?search=hello%20world&filter=x%26y%3Dz&app=true'],
            ['%2Fdashboard', '/dashboard?app=true'],
        ];
    }

    #[DataProvider('rejectedRedirects')]
    public function test_webview_auth_rejects_unsafe_destinations_before_login(mixed $destination): void
    {
        $token = User::findOrFail(1)->createToken('webview')->plainTextToken;
        $this->getJson('/webview/auth?'.http_build_query(['redirect' => $destination]), ['Authorization' => 'Bearer '.$token])
            ->assertUnprocessable()->assertJsonValidationErrors('redirect');
        $this->assertGuest();
    }

    public static function rejectedRedirects(): array
    {
        return array_map(fn ($destination) => [$destination], [
            'https://example.com', 'http://example.com', '//example.com', '\\\\example.com',
            'javascript:alert(1)', 'data:text/html,...', '%2F%2Fevil.example', '/%2Fevil.example',
            '%252F%252Fevil.example', '/%5Cevil.example', '%2f%5cevil.example', '/\\evil.example',
            '/%255Cevil.example', "/dashboard\r\nLocation: https://evil.example", '/%250Aevil.example',
            'dashboard', ' https://evil.example', '/products%3Fredirect=//evil.example', ['nested' => '/sales'],
        ]);
    }

    public function test_webview_auth_requires_valid_bearer_even_with_existing_session(): void
    {
        $this->actingAs(User::findOrFail(1));
        $this->getJson('/webview/auth')->assertUnauthorized();
        $this->getJson('/webview/auth', ['Authorization' => 'Bearer invalid-token'])->assertUnauthorized();
    }

    public function test_webview_auth_rejects_expired_revoked_inactive_and_deleted_token_users(): void
    {
        $user = User::findOrFail(1);
        $token = $user->createToken('webview', ['*'], now()->subMinute());
        $this->getJson('/webview/auth', ['Authorization' => 'Bearer '.$token->plainTextToken])->assertUnauthorized();
        $token->accessToken->forceFill(['expires_at' => null, 'created_at' => now()->subHours(2)])->save();
        config(['sanctum.expiration' => 60]);
        $this->getJson('/webview/auth', ['Authorization' => 'Bearer '.$token->plainTextToken])->assertUnauthorized();
        config(['sanctum.expiration' => null]);
        $user->update(['is_active' => false]);
        $this->getJson('/webview/auth', ['Authorization' => 'Bearer '.$token->plainTextToken])->assertUnauthorized();
        $user->update(['is_active' => true, 'is_deleted' => true]);
        $this->getJson('/webview/auth', ['Authorization' => 'Bearer '.$token->plainTextToken])->assertUnauthorized();
        $user->update(['is_deleted' => false]);
        $token->accessToken->delete();
        $this->getJson('/webview/auth', ['Authorization' => 'Bearer '.$token->plainTextToken])->assertUnauthorized();
        $this->assertGuest();
    }

    public function test_webview_auth_respects_sanctum_authentication_callback(): void
    {
        $token = User::findOrFail(1)->createToken('webview')->plainTextToken;
        Sanctum::authenticateAccessTokensUsing(fn () => false);
        try {
            $this->getJson('/webview/auth', ['Authorization' => 'Bearer '.$token])->assertUnauthorized();
        } finally {
            Sanctum::$accessTokenAuthenticationCallback = null;
        }
    }
}

class EnforcedMaintenanceCsrfToken extends VerifyCsrfToken
{
    protected function runningUnitTests()
    {
        return false;
    }
}
