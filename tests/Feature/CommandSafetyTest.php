<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CommandSafetyTest extends TestCase
{
    public function test_database_reset_is_never_scheduled(): void
    {
        $events = $this->app->make(Schedule::class)->events();
        foreach ($events as $event) {
            $this->assertStringNotContainsString('reset:db', $event->command ?? '');
        }
        $this->assertNotEmpty($events);
    }

    public function test_even_confirmed_database_reset_is_rejected_outside_demo_before_queries(): void
    {
        DB::shouldReceive('statement')->never();
        DB::shouldReceive('select')->never();
        DB::shouldReceive('unprepared')->never();
        $this->assertSame(1, Artisan::call('reset:db', ['--confirm-demo-reset' => true]));
        $this->assertStringContainsString('requires the demo environment', Artisan::output());
    }

    public function test_demo_reset_requires_explicit_confirmation(): void
    {
        $this->app->instance('env', 'demo');
        DB::shouldReceive('statement')->never();
        $this->assertSame(1, Artisan::call('reset:db'));
    }

    public function test_accounting_commands_require_company_actor_and_explicit_projection_rebuild(): void
    {
        DB::shouldReceive('select')->never();
        DB::shouldReceive('statement')->never();
        $this->assertSame(1, Artisan::call('erp:account-mappings'));
        $this->assertSame(1, Artisan::call('erp:ledger-reconcile'));
        $this->assertSame(1, Artisan::call('erp:ledger-reconcile', ['--company' => 1, '--actor' => 1, '--rebuild' => true]));
        $this->assertStringContainsString('--force', Artisan::output());
    }
}
