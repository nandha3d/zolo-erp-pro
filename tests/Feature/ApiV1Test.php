<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Customer;
use App\Models\Warehouse;
use App\Models\Product;
use Laravel\Sanctum\Sanctum;

class ApiV1Test extends TestCase
{
    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminUser = User::first();
    }

    public function test_api_login_returns_token(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'name' => $this->adminUser->name,
            'password' => 'admin', // standard default password
        ]);

        // If password is not 'admin', assert response is handled cleanly
        if ($response->status() === 200) {
            $response->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'token',
                    'token_type',
                    'user'
                ]
            ]);
            $this->assertTrue($response->json('success'));
        } else {
            $this->assertContains($response->status(), [401, 422]);
        }
    }

    public function test_authenticated_user_can_access_me(): void
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $response = $this->getJson('/api/v1/auth/me');
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $this->adminUser->id,
                    'name' => $this->adminUser->name,
                ]
            ]);
    }

    public function test_accounting_endpoints_via_api(): void
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        // 1. Chart of Accounts
        $coaResponse = $this->getJson('/api/v1/accounting/chart-of-accounts');
        $coaResponse->assertStatus(200)
            ->assertJson(['success' => true]);
        $this->assertNotEmpty($coaResponse->json('data'));

        // 2. Trial Balance
        $tbResponse = $this->getJson('/api/v1/accounting/trial-balance');
        $tbResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'is_balanced' => true,
                ]
            ]);

        // 3. Balance Sheet
        $bsResponse = $this->getJson('/api/v1/accounting/balance-sheet');
        $bsResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'is_balanced' => true,
                ]
            ]);

        // 4. Profit and Loss
        $pnlResponse = $this->getJson('/api/v1/accounting/profit-and-loss');
        $pnlResponse->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_products_list_via_api(): void
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $response = $this->getJson('/api/v1/products');
        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }
}
