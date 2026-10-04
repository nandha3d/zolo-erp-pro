<?php

namespace App\Providers;

use App\Http\Middleware\RequireCapability;
use App\Services\Platform\LegacyModuleAdapter;
use Illuminate\Support\ServiceProvider;

class CapabilityServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(base_path('routes/capabilities.php'));
        // Apply to both loaded and cached routes, including module routes registered later.
        $this->app->booted(function () {
            foreach ($this->app['router']->getRoutes() as $route) {
                $controller = class_basename(explode('@', $route->getActionName())[0]);
                $key = match ($controller) {
                    'WaterLogisticsController', 'WaterLogisticsApiController' => 'operations.water_logistics',
                    'CafeOperationsController', 'CafeApiController' => 'operations.cafe_bakery',
                    'RepairController', 'RepairApiController' => 'service.repair',
                    'ProjectManagementController' => 'operations.projects',
                    'ProductionController', 'RecipeController', 'ManufacturingController' => 'manufacturing.production',
                    'OptechJobWorkController' => 'operations.job_work',
                    'OptechSpeedBillingController' => 'sales.fast_counter',
                    'OptechPrintingController' => 'printing.dot_matrix',
                    'DamageStockController' => 'inventory.damage_stock',
                    'ExchangeController' => 'sales.exchange',
                    'InstallmentController', 'InstallmentPlanController' => 'sales.installment_plans',
                    'CatalogueQrController' => 'sales.catalogue_qr',
                    'WhatsappController' => 'communications.whatsapp',
                    default => null,
                };
                $action = $route->getActionName();
                foreach (['Ecommerce' => 'ecommerce', 'Woocommerce' => 'woocommerce', 'Project' => 'project', 'Restaurant' => 'restaurant'] as $module => $legacy) {
                    if (str_starts_with($action, 'Modules\\'.$module.'\\') && in_array('common', $route->middleware(), true)) {
                        $key = LegacyModuleAdapter::MODULES[$legacy];
                    }
                }
                if ($route->getName() === 'sale.wappnotification') {
                    $key = 'communications.whatsapp';
                }
                if ($key) {
                    $auth = str_starts_with($route->uri(), 'api/') ? 'auth:sanctum' : 'auth';
                    $route->middleware([$auth, 'company.context', RequireCapability::class.':'.$key]);
                }
                if ($route->getName() === 'api.v1.addons.status') {
                    $route->middleware(['auth:sanctum', 'company.context']);
                }
            }
        });
    }
}
