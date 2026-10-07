<?php

namespace Tests\Support;

use Illuminate\Support\Facades\View;

/** Application shell data for isolated fixtures that bypass installation settings middleware. */
final class CommandCenterViewFixture
{
    public static function share(): void
    {
        View::share([
            'general_setting' => (object) ['is_rtl' => false, 'site_logo' => '', 'site_title' => 'Fixture ERP',
                'theme' => 'default.css', 'font_css' => '', 'custom_css' => '', 'modules' => '', 'date_format' => 'Y-m-d'],
            'theme' => 'light', 'languages' => collect(), 'role_has_permissions_list' => collect(),
            'alert_product' => 0, 'dso_alert_product_no' => 0, 'expire_alert_products' => 0,
        ]);
    }
}
