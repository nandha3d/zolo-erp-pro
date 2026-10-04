<?php

namespace App\Traits;

/** Compatibility responses for the retired browser updater; deploy reviewed releases through the operator runbook. */
trait AutoUpdateTrait
{
    public function isUpdateAvailable(): array
    {
        return [];
    }

    public function versionUpgradeFileUrl($purchaseCode): ?string
    {
        return null;
    }
}
