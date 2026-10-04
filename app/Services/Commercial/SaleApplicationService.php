<?php

namespace App\Services\Commercial;

use App\Models\Sale;

class SaleApplicationService
{
    public function create(SaleCommand $command): Sale
    {
        return app(CommercialApplicationService::class)->create('sale', $command->data, $command->key, $command->actor, $command->context);
    }
}
