<?php

namespace App\Services\Commercial;

use App\Services\Platform\CompanyContext;

final readonly class PurchaseCommand
{
    public function __construct(public array $data, public string $key, public ?int $actor = null, public ?CompanyContext $context = null) {}
}
