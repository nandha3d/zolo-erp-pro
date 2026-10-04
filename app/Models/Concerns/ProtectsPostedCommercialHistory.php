<?php

namespace App\Models\Concerns;

trait ProtectsPostedCommercialHistory
{
    public static function bootProtectsPostedCommercialHistory(): void
    {
        static::updating(function ($document) {
            if ($document->getOriginal('posted_at') && $document->isDirty(array_diff(array_keys($document->getAttributes()), [
                'paid_amount', 'payment_status', 'reversed_at', 'reversal_reason', 'updated_at',
            ]))) {
                throw new \LogicException('Posted commercial history is immutable; reverse and replace the document.');
            }
        });
        static::deleting(function ($document) {
            if ($document->posted_at) {
                throw new \LogicException('Posted commercial documents must be reversed, not deleted.');
            }
        });
    }
}
