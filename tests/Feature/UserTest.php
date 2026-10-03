<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class UserTest extends TestCase
{
    public function testExample(): void
    {
        $user = \App\Models\User::find(1) ?? \App\Models\User::first();
        if (!$user) {
            $user = \App\Models\User::factory()->create(['role_id' => 1, 'is_active' => 1]);
        }

        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertStatus(200);
        $response->assertSee('Accounting');
        $response->assertSee('Sale');
        $response->assertSee('Purchase');
        $response->assertSee('Settings');
    }
}
