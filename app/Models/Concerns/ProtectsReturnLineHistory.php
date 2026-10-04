<?php

namespace App\Models\Concerns;

trait ProtectsReturnLineHistory
{
    public static function bootProtectsReturnLineHistory(): void
    {
        $check = function ($line) {
            $parent = $line instanceof \App\Models\ProductReturn ? \App\Models\Returns::class : \App\Models\ReturnPurchase::class;
            if ($parent::find($line->return_id)?->posted_at) throw new \LogicException('Posted note lines are immutable.');
        };
        static::creating($check); static::updating($check); static::deleting($check);
    }
}
