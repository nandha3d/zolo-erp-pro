<?php

namespace App\Exceptions;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CompanyFinancialYearSetupRequired extends ValidationException
{
    public function __construct(public readonly int $companyId)
    {
        parent::__construct(Validator::make([], []));
        $this->validator->errors()->add('financial_year_id', 'Business date must match exactly one company financial year.');
        $this->status = 409;
    }
}
