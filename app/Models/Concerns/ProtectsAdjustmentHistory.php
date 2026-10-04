<?php

namespace App\Models\Concerns;

trait ProtectsAdjustmentHistory
{
    public static function bootProtectsAdjustmentHistory(): void
    {
        static::updating(function ($model) {
            if ($model->getOriginal('posted_at') && $model->isDirty(array_diff(array_keys($model->getAttributes()), ['updated_at']))) {
                throw new \LogicException('Posted adjustments are immutable.');
            }
        });
        static::deleting(function ($model) {
            if ($model->posted_at) throw new \LogicException('Posted adjustments cannot be deleted.');
        });
    }
}
