<?php

namespace Tests\Feature;

use App\Http\Controllers\CouponController;
use App\Http\Controllers\SettingController;
use App\Models\User;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Whole-database wipes must be unreachable by links and by ordinary users. These tests never run the wipe
 * itself (that needs MySQL and would truncate the configured database); they cover every rejection path.
 */
class DestructiveRoutesTest extends TestCase
{
    public function test_the_update_coupon_route_that_truncated_every_table_is_gone(): void
    {
        foreach (app('router')->getRoutes() as $route) {
            $this->assertStringNotContainsString('update-coupon', $route->uri());
        }
        $this->assertFalse(method_exists(CouponController::class, 'updateCoupon'));
    }

    public function test_empty_database_is_post_only_so_a_link_or_prefetch_cannot_trigger_it(): void
    {
        $route = app('router')->getRoutes()->getByName('setting.emptyDatabase');

        $this->assertSame(['POST'], $route->methods());
    }

    public static function usersWhoMayNotWipe(): array
    {
        return [
            'staff role' => [3, 1],
            'other role' => [4, 1],
            'no role' => [0, 1],
            'inactive admin' => [1, 0],
            'inactive owner' => [2, 0],
        ];
    }

    #[DataProvider('usersWhoMayNotWipe')]
    public function test_only_an_active_owner_or_admin_reaches_the_wipe(int $roleId, int $active): void
    {
        $this->actingAs(new User(['role_id' => $roleId, 'is_active' => $active, 'name' => 'Not allowed']));

        try {
            app(SettingController::class)->emptyDatabase(Request::create('/setting/empty-database', 'POST',
                ['confirmation' => SettingController::EMPTY_DATABASE_CONFIRMATION]));
            $this->fail('The wipe must be refused.');
        } catch (HttpException $error) {
            $this->assertSame(403, $error->getStatusCode());
        }
    }

    #[DataProvider('adminsWithoutTheTypedPhrase')]
    public function test_even_an_admin_must_type_the_confirmation(?string $confirmation): void
    {
        foreach ([1, 2] as $roleId) {
            $this->actingAs(new User(['role_id' => $roleId, 'is_active' => 1, 'name' => 'Admin']));
            $data = $confirmation === null ? [] : ['confirmation' => $confirmation];

            $response = app(SettingController::class)->emptyDatabase(Request::create('/setting/empty-database', 'POST', $data));

            $this->assertTrue($response->isRedirection());
            $this->assertStringContainsString('DELETE ALL DATA', session('not_permitted'));
        }
    }

    public static function adminsWithoutTheTypedPhrase(): array
    {
        return ['missing' => [null], 'yes' => ['yes'], 'wrong case' => ['delete all data'], 'padded' => [' DELETE ALL DATA']];
    }
}
