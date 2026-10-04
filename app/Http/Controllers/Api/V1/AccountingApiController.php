<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\Accounting\AccountingService;
use App\Models\Accounting\ChartOfAccount;
use App\Models\Accounting\JournalEntry;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Exception;

class AccountingApiController extends BaseApiController
{
    protected AccountingService $accountingService;

    public function __construct(AccountingService $accountingService)
    {
        $this->accountingService = $accountingService;
    }

    /**
     * Get Chart of Accounts hierarchy with current balances.
     */
    public function chartOfAccounts(Request $request): JsonResponse
    {
        $context = $this->companyContext($request);
        $accounts = ChartOfAccount::forCompany($context)->with(['children' => fn ($q) => $q->forCompany($context)])->whereNull('parent_id')->orderBy('code')->get();
        return $this->sendResponse($accounts, 'Chart of Accounts retrieved successfully');
    }

    /**
     * Get Trial Balance statement.
     */
    public function trialBalance(Request $request): JsonResponse
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $trialBalance = $this->accountingService->getTrialBalance($startDate, $endDate, $this->companyContext($request));
        return $this->sendResponse($trialBalance, 'Trial Balance statement generated');
    }

    /**
     * Get Profit and Loss (Income Statement).
     */
    public function profitAndLoss(Request $request): JsonResponse
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $pnl = $this->accountingService->getProfitAndLoss($startDate, $endDate, $this->companyContext($request));
        return $this->sendResponse($pnl, 'Profit and Loss statement generated');
    }

    /**
     * Get Balance Sheet.
     */
    public function balanceSheet(Request $request): JsonResponse
    {
        $asOfDate = $request->input('as_of_date');

        $balanceSheet = $this->accountingService->getBalanceSheet($asOfDate, $this->companyContext($request));
        return $this->sendResponse($balanceSheet, 'Balance Sheet statement generated');
    }

    /**
     * Get General Ledger for a specific account.
     */
    public function generalLedger(int $id, Request $request): JsonResponse
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        try {
            $ledger = $this->accountingService->getGeneralLedger($id, $startDate, $endDate, $this->companyContext($request));
            return $this->sendResponse($ledger, 'General Ledger retrieved');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->sendError('Account not found', [], 404);
        }
    }

    /**
     * Post a manual double-entry journal entry.
     */
    public function storeJournalEntry(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'entry_date' => 'required|date',
            'description' => 'required|string',
            'items' => 'required|array|min:2',
            'items.*.chart_of_account_id' => 'required|integer|exists:chart_of_accounts,id',
            'items.*.debit' => 'nullable|numeric|min:0',
            'items.*.credit' => 'nullable|numeric|min:0',
            'items.*.memo' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors(), 422);
        }

        try {
            $entry = $this->accountingService->postJournalEntry([
                'entry_date' => $request->entry_date,
                'description' => $request->description,
                'reference_type' => 'manual',
                'created_by' => $request->user()?->id,
            ], $request->items, $this->companyContext($request));

            return $this->sendResponse($entry->load('items.account'), 'Journal Entry posted successfully', 201);
        } catch (\Illuminate\Validation\ValidationException | \Illuminate\Auth\Access\AuthorizationException | \Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            throw $e;
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), [], 400);
        }
    }
}
