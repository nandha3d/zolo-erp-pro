<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AddonApiController extends BaseApiController
{
    /**
     * Get active addons and enabled features for current tenant / instance
     */
    public function status(): JsonResponse
    {
        $general_setting = DB::table('general_settings')->first();
        $activeModules = array_filter(explode(',', $general_setting->modules ?? ''));

        $features = [
            'water_logistics' => in_array('water_logistics', $activeModules),
            'cafe_bakery' => in_array('cafe_bakery', $activeModules),
            'repair' => in_array('repair', $activeModules),
            'project_management' => in_array('project_management', $activeModules),
            'manufacturing' => in_array('manufacturing', $activeModules),
            'installment_plans' => in_array('installment_plans', $activeModules),
            'catalogue_qr' => in_array('catalogue_qr', $activeModules),
            'damage_stock' => in_array('damage_stock', $activeModules),
            'exchange' => in_array('exchange', $activeModules),
            'ecommerce' => in_array('ecommerce', $activeModules),
            'woocommerce' => in_array('woocommerce', $activeModules),
            'api' => in_array('api', $activeModules),
        ];

        return $this->sendResponse([
            'system' => 'zoloERP SaaS Engine',
            'company_name' => $general_setting->company_name ?? 'zoloERP Enterprise',
            'currency' => $general_setting->currency ?? 'INR',
            'active_modules' => array_values($activeModules),
            'features' => $features,
        ], 'Active addons and tenant features retrieved successfully');
    }
}
