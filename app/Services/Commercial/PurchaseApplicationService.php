<?php

namespace App\Services\Commercial;

use App\Models\Purchase;

class PurchaseApplicationService
{
    public function create(PurchaseCommand $command): Purchase
    {
        return app(CommercialApplicationService::class)->create('purchase', $command->data, $command->key, $command->actor, $command->context);
    }
}
