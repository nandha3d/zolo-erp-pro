<?php

namespace App\Models\Concerns;

trait ProtectsCommercialLineHistory
{
    public static function bootProtectsCommercialLineHistory(): void
    {
        static::updating(function ($line) {
            $purchase = $line instanceof \App\Models\ProductPurchase;
            $document = $purchase ? $line->purchase : $line->sale;
            $mutable = $purchase ? ['recieved', 'updated_at'] : ['updated_at'];
            if ($document?->posted_at && $line->isDirty(array_diff(array_keys($line->getAttributes()), $mutable))) {
                throw new \LogicException('Posted commercial lines are immutable.');
            }
        });
        static::deleting(function ($line) {
            $document = $line instanceof \App\Models\ProductPurchase ? $line->purchase : $line->sale;
            if ($document?->posted_at) {
                throw new \LogicException('Posted commercial lines cannot be deleted.');
            }
        });
    }
}
