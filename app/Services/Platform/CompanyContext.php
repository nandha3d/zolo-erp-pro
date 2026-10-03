<?php

namespace App\Services\Platform;

use InvalidArgumentException;

final readonly class CompanyContext
{
    public function __construct(
        public int $companyId,
        public int $branchId,
        public int $financialYearId,
    ) {
        if ($companyId < 1 || $branchId < 1 || $financialYearId < 1) {
            throw new InvalidArgumentException('Company, branch and financial year IDs must be positive.');
        }
    }
}
