<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Real sales Blade proof; all permission changes roll back with the transaction. */
class ProductAliasCostWebTest extends TestCase
{
    use DatabaseTransactions;

    public function test_alias_manager_loads_shell_data_on_its_first_request(): void
    {
        $this->actingAs(User::firstOrFail());
        $this->get('/product-aliases')->assertOk()->assertSee('Alternate product names');
    }

    public function test_sales_catalog_excludes_purchase_cost_for_restricted_company_role(): void
    {
        config(['commercial.enabled' => true]);
        putenv('ERP_OPTIONAL_ACTIVATION_READY=true');
        $_ENV['ERP_OPTIONAL_ACTIVATION_READY'] = 'true';
        $actor = User::firstOrFail();
        $this->actingAs($actor);
        $role = DB::table('roles')->whereNotIn('id', [1, 2])->where('is_active', true)->first();
        $this->assertNotNull($role, 'A non-administrator fixture role is required.');
        DB::table('role_has_permissions')->where('role_id', $role->id)->delete();
        foreach (['sales-index', 'sales-add', 'products-index'] as $name) {
            $permission = DB::table('permissions')->where('name', $name)->value('id');
            $this->assertNotNull($permission);
            DB::table('role_has_permissions')->insert(['role_id' => $role->id, 'permission_id' => $permission]);
        }
        DB::table('company_user')->where('user_id', $actor->id)->update(['role_id_override' => $role->id]);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $response = $this->get('/sales?new=1')->assertOk();
        foreach (['lims_product_list_without_variant', 'lims_product_list_with_variant'] as $key) {
            foreach ($response->viewData($key) as $product) {
                $this->assertArrayNotHasKey('cost', $product->getAttributes());
                $this->assertArrayNotHasKey('additional_cost', $product->getAttributes());
            }
        }
        $this->assertFalse($response->viewData('catalogCostVisible'));
        preg_match('/var allProducts = (\[.*?\]);/s', $response->getContent(), $matches);
        $this->assertNotEmpty($matches);
        $catalog = json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR);
        $this->assertNotEmpty($catalog);
        foreach ($catalog as $product) {
            $this->assertArrayNotHasKey('cost', $product);
            $this->assertArrayNotHasKey('additional_cost', $product);
        }
    }
}
