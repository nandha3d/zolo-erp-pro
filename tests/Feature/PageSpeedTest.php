<?php

namespace Tests\Feature;

use App\Services\Commercial\CommercialPermission;
use App\Services\Platform\CompanyContext;
use App\Services\Platform\WorkspaceNavigation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Tests\Support\OperationsTestCase;

class PageSpeedTest extends OperationsTestCase
{
    public function test_workspace_authorizes_the_actor_once_for_all_navigation_items(): void
    {
        $context = $this->resolver->resolve(1);
        DB::connection()->enableQueryLog();
        DB::connection()->flushQueryLog();
        $items = app(WorkspaceNavigation::class)->items($context, 1);
        $queries = DB::getQueryLog();
        $actorReads = array_filter($queries, fn ($query) => preg_match('/\busers\b/', $query['query']));
        // One actor validation plus the fresh administrator check for permissions and Settings.
        $this->assertCount(3, $actorReads);
        $this->assertContains('Sales', array_column($items, 'label'));
        $this->assertContains('Settings', array_column($items, 'label'));
    }

    public function test_permission_batch_observes_role_override_revocation_and_inactive_roles(): void
    {
        $context = $this->resolver->resolve(1);
        DB::table('company_user')->where('user_id', 1)->update(['role_id_override' => 4]);
        $id = DB::table('permissions')->insertGetId(['name' => 'sales-add', 'guard_name' => 'web']);
        DB::table('role_has_permissions')->insert(['role_id' => 4, 'permission_id' => $id]);
        $permissions = app(CommercialPermission::class);
        $this->assertSame(['sales-add'], $permissions->allowed(['sales-add', 'purchases-add'], $context, 1));
        DB::table('role_has_permissions')->where('role_id', 4)->delete();
        $this->assertFalse($permissions->allows('sales-add', $context, 1));
        DB::table('role_has_permissions')->insert(['role_id' => 4, 'permission_id' => $id]);
        DB::table('roles')->where('id', 4)->update(['is_active' => false]);
        $this->assertSame([], $permissions->allowed(['sales-add'], $context, 1));
    }

    public function test_permission_batch_rejects_foreign_context_and_revoked_membership(): void
    {
        $permissions = app(CommercialPermission::class);
        $context = $this->resolver->resolve(1);
        try {
            $permissions->allowed(['sales-add'], new CompanyContext($this->other->id, $this->otherBranch->id, $this->otherYear->id), 1);
            $this->fail('Foreign company must be rejected.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }
        DB::table('company_user_branches')->where('user_id', 1)->delete();
        DB::table('company_user')->where('user_id', 1)->delete();
        $this->expectException(AuthorizationException::class);
        $permissions->allowed(['sales-add'], $context, 1);
    }

    public function test_sidebar_reuses_loaded_permissions_without_per_menu_queries(): void
    {
        $context = $this->resolver->resolve(1);
        request()->attributes->set(CompanyContext::class, $context);
        DB::table('company_user')->where('user_id', 1)->update(['role_id_override' => 4]);
        DB::connection()->enableQueryLog();
        DB::connection()->flushQueryLog();
        $html = view('backend.layout.sidebar', [
            'role' => (object) ['id' => 4],
            'role_has_permissions_list' => collect([(object) ['name' => 'sidebar_sale'], (object) ['name' => 'sales-index']]),
        ])->render();
        $permissionReads = array_filter(DB::getQueryLog(), fn ($query) => str_contains($query['query'], 'role_has_permissions'));
        $this->assertCount(0, $permissionReads);
        $this->assertStringContainsString('sale-list-menu', $html);
        $this->assertStringNotContainsString('purchase-list-menu', $html);
    }
}
