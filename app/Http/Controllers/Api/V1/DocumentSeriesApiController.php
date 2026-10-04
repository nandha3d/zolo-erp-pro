<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\Platform\DocumentNumberService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DocumentSeriesApiController extends BaseApiController
{
    public function index(Request $request)
    {
        $context = $this->companyContext($request);
        return $this->sendResponse(DB::table('document_series')->where([
            'company_id' => $context->companyId, 'branch_id' => $context->branchId,
            'financial_year_id' => $context->financialYearId,
        ])->get());
    }

    public function store(Request $request, DocumentNumberService $service)
    {
        $id = $service->configure($this->companyContext($request), $request->all(), $request->user()->id);
        return $this->sendResponse(['id' => $id], 'Document series configured.', 201);
    }
}
